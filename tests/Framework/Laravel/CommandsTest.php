<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Framework\Laravel;

use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Framework\Laravel\Commands\CheckCommand;
use HarunGecit\EmailValidator\Framework\Laravel\Commands\InstallCommand;
use HarunGecit\EmailValidator\Framework\Laravel\Commands\StatsCommand;
use HarunGecit\EmailValidator\Framework\Laravel\Commands\SyncCommand;
use HarunGecit\EmailValidator\Framework\Laravel\Commands\ValidateCommand;
use HarunGecit\EmailValidator\Sync\HttpDownloader;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

class CommandsTest extends LaravelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(Command::class) || !class_exists(CommandTester::class)) {
            $this->markTestSkipped('illuminate/console is not installed.');
        }
    }

    public function testCommandNames(): void
    {
        $this->assertSame('email-validator:install', (new InstallCommand())->getName());
        $this->assertSame('email-validator:check', (new CheckCommand())->getName());
        $this->assertSame('email-validator:validate', (new ValidateCommand())->getName());
        $this->assertSame('email-validator:sync', (new SyncCommand())->getName());
        $this->assertSame('email-validator:stats', (new StatsCommand())->getName());
    }

    public function testCheckCommand(): void
    {
        $this->bootApplication();

        $tester = $this->tester(new CheckCommand());
        $exitCode = $tester->execute(['inputs' => ['user@eu.mailinator.com', 'gmail.com']]);
        $display = $tester->getDisplay();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('DISPOSABLE user@eu.mailinator.com (matched mailinator.com in blocklist)', $display);
        $this->assertStringContainsString('OK         gmail.com', $display);

        $this->assertSame(0, $tester->execute(['inputs' => ['user@gmail.com']]));
    }

    public function testCheckCommandJson(): void
    {
        $this->bootApplication();

        $tester = $this->tester(new CheckCommand());
        $tester->execute(['inputs' => ['user@mailinator.com'], '--json' => true]);

        $json = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($json);
        $this->assertTrue($json[0]['disposable']);
        $this->assertSame('mailinator.com', $json[0]['matched_domain']);
    }

    public function testValidateCommand(): void
    {
        $this->bootApplication(['checks' => ['format' => true, 'mx' => true, 'disposable' => true]]);

        $tester = $this->tester(new ValidateCommand());
        $exitCode = $tester->execute(['emails' => ['john@gmail.com', 'user@mailinator.com'], '--no-mx' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('VALID   john@gmail.com', $tester->getDisplay());
        $this->assertStringContainsString('INVALID user@mailinator.com — Disposable email address', $tester->getDisplay());
        // --no-mx is restored afterwards.
        $this->assertTrue($this->app->make(EmailValidator::class)->getConfiguration()->isMxCheckEnabled());
    }

    public function testStatsCommand(): void
    {
        $this->bootApplication();

        $tester = $this->tester(new StatsCommand());
        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('Total blocked domains', $tester->getDisplay());

        $tester->execute(['--json' => true]);
        $stats = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($stats);
        $this->assertGreaterThan(1000, $stats['blocklist']);
        $this->assertArrayHasKey('synced_path', $stats);
    }

    public function testSyncCommandWritesSyncedList(): void
    {
        $path = $this->basePath . '/storage/synced.txt';
        $app = $this->bootApplication([
            'lists' => ['synced_blocklist_path' => $path],
            'sync' => ['urls' => ['https://lists.test/a.txt']],
        ]);
        $app->instance(HttpDownloader::class, $this->downloader([
            'https://lists.test/a.txt' => "# list\nfresh-throwaway-domain-test.com\nmailinator.com\n",
        ]));

        // Resolve before the sync: the command must refresh the singleton afterwards.
        $before = $app->make(EmailValidator::class);
        $this->assertFalse($before->isDisposable('a@fresh-throwaway-domain-test.com'));

        $tester = $this->tester(new SyncCommand());
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
        $this->assertStringContainsString('https://lists.test/a.txt (2 domains)', $tester->getDisplay());
        $this->assertStringContainsString('Updated', $tester->getDisplay());
        $this->assertSame("fresh-throwaway-domain-test.com\n", file_get_contents($path));

        $after = $app->make(EmailValidator::class);
        $this->assertNotSame($before, $after);
        $check = $after->checkDisposable('a@fresh-throwaway-domain-test.com');
        $this->assertTrue($check->isDisposable());
        $this->assertSame('synced', $check->getSource());
    }

    public function testSyncCommandFailureKeepsExistingList(): void
    {
        $path = $this->basePath . '/storage/synced.txt';
        mkdir(dirname($path), 0777, true);
        file_put_contents($path, "kept-domain-test.com\n");

        $app = $this->bootApplication([
            'lists' => ['synced_blocklist_path' => $path],
            'sync' => ['urls' => ['https://lists.test/down.txt']],
        ]);
        $app->instance(HttpDownloader::class, $this->downloader([]));

        $tester = $this->tester(new SyncCommand());
        $exitCode = $tester->execute(['--json' => true]);

        $this->assertSame(1, $exitCode);
        $result = json_decode($tester->getDisplay(), true);
        $this->assertFalse($result['successful']);
        $this->assertSame("kept-domain-test.com\n", file_get_contents($path));
    }

    public function testSyncCommandWithoutTargetPath(): void
    {
        $this->bootApplication(['lists' => ['synced_blocklist_path' => null]]);

        $tester = $this->tester(new SyncCommand());
        $this->assertSame(2, $tester->execute([]));
        $this->assertStringContainsString('synced_blocklist_path', $tester->getDisplay());
    }

    private function tester(Command $command): CommandTester
    {
        $command->setLaravel($this->app);

        return new CommandTester($command);
    }

    /**
     * @param array<string, string> $responses URL => body; other URLs fail.
     */
    private function downloader(array $responses): HttpDownloader
    {
        return new class ($responses) extends HttpDownloader {
            /** @param array<string, string> $responses */
            public function __construct(private array $responses)
            {
            }

            public function get(string $url, int $timeout): string
            {
                if (!isset($this->responses[$url])) {
                    throw new RuntimeException("Download failed for {$url}: HTTP 503");
                }

                return $this->responses[$url];
            }
        };
    }
}
