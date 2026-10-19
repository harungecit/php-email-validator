<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Framework\Symfony;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Framework\Symfony\Command\CheckCommand;
use HarunGecit\EmailValidator\Framework\Symfony\Command\StatsCommand;
use HarunGecit\EmailValidator\Framework\Symfony\Command\SyncCommand;
use HarunGecit\EmailValidator\Framework\Symfony\Command\ValidateCommand;
use HarunGecit\EmailValidator\Sync\HttpDownloader;
use HarunGecit\EmailValidator\Sync\ListSynchronizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CommandsTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        if (!class_exists(Command::class)) {
            $this->markTestSkipped('symfony/console is not installed.');
        }

        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ev-symfony-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        if ($this->directory !== '' && is_dir($this->directory)) {
            foreach ((array) glob($this->directory . '/*') as $file) {
                @unlink((string) $file);
            }
            @rmdir($this->directory);
        }
    }

    public function testNamesAndDescriptions(): void
    {
        $validator = $this->emailValidator();
        $config = $validator->getConfiguration();

        $commands = [
            'email-validator:check' => new CheckCommand($validator),
            'email-validator:validate' => new ValidateCommand($validator),
            'email-validator:stats' => new StatsCommand($validator),
            'email-validator:sync' => new SyncCommand(new ListSynchronizer($config), $config),
        ];

        foreach ($commands as $name => $command) {
            $this->assertSame($name, $command->getName());
            $this->assertNotSame('', $command->getDescription());
        }
    }

    public function testCheckCommand(): void
    {
        $tester = new CommandTester(new CheckCommand($this->emailValidator()));

        $this->assertSame(1, $tester->execute(['inputs' => ['user@eu.mailinator.com', 'gmail.com']]));
        $this->assertStringContainsString('DISPOSABLE user@eu.mailinator.com (matched mailinator.com in blocklist)', $tester->getDisplay());
        $this->assertStringContainsString('OK         gmail.com', $tester->getDisplay());

        $this->assertSame(0, $tester->execute(['inputs' => ['jane@gmail.com'], '--json' => true]));
        $json = json_decode($tester->getDisplay(), true);
        $this->assertFalse($json[0]['disposable']);
        $this->assertSame('gmail.com', $json[0]['domain']);
    }

    public function testValidateCommand(): void
    {
        $validator = $this->emailValidator(['checks' => ['mx' => true]]);
        $tester = new CommandTester(new ValidateCommand($validator));

        $exitCode = $tester->execute(['emails' => ['jane@gmail.com', 'nope'], '--no-mx' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('VALID   jane@gmail.com', $tester->getDisplay());
        $this->assertStringContainsString('INVALID nope — Invalid email format', $tester->getDisplay());
        $this->assertTrue($validator->getConfiguration()->isMxCheckEnabled());
    }

    public function testStatsCommand(): void
    {
        $tester = new CommandTester(new StatsCommand($this->emailValidator(['lists' => ['custom_blocklist' => ['a-custom-domain.test']]])));

        $this->assertSame(0, $tester->execute(['--json' => true]));
        $stats = json_decode($tester->getDisplay(), true);
        $this->assertSame(1, $stats['custom']);
        $this->assertTrue($stats['block_subdomains']);

        $tester->execute([]);
        $this->assertStringContainsString('Custom blocklist', $tester->getDisplay());
        $this->assertStringContainsString('Package version', $tester->getDisplay());
    }

    public function testSyncCommand(): void
    {
        $path = $this->directory . '/synced.txt';
        $config = $this->configuration([
            'lists' => ['synced_blocklist_path' => $path],
            'sync' => ['urls' => ['https://lists.test/a.txt', 'https://lists.test/down.txt']],
        ]);
        $synchronizer = new ListSynchronizer($config, $this->downloader([
            'https://lists.test/a.txt' => "brand-new-throwaway-test.com\n",
        ]));

        $tester = new CommandTester(new SyncCommand($synchronizer, $config));
        $exitCode = $tester->execute([]);
        $display = $tester->getDisplay();

        $this->assertSame(0, $exitCode, $display);
        $this->assertStringContainsString('✔ https://lists.test/a.txt (1 domains)', $display);
        $this->assertStringContainsString('✘ https://lists.test/down.txt — Download failed', $display);
        $this->assertStringContainsString('Updated', $display);
        $this->assertSame("brand-new-throwaway-test.com\n", file_get_contents($path));

        $this->assertSame('synced', (new EmailValidator($config))->checkDisposable('brand-new-throwaway-test.com')->getSource());
    }

    public function testSyncCommandFailure(): void
    {
        $path = $this->directory . '/synced.txt';
        $config = $this->configuration([
            'lists' => ['synced_blocklist_path' => $path],
            'sync' => ['urls' => ['https://lists.test/down.txt']],
        ]);

        $tester = new CommandTester(new SyncCommand(new ListSynchronizer($config, $this->downloader([])), $config));

        $this->assertSame(1, $tester->execute(['--replace' => true]));
        $this->assertFileDoesNotExist($path);
    }

    public function testSyncCommandWithoutPath(): void
    {
        $config = $this->configuration();
        $tester = new CommandTester(new SyncCommand(new ListSynchronizer($config, $this->downloader([])), $config));

        $this->assertSame(2, $tester->execute([]));
        $this->assertStringContainsString('lists.synced_blocklist_path', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configuration(array $config = []): Configuration
    {
        return Configuration::fromArray(array_replace_recursive([
            'checks' => ['mx' => false],
            'cache' => ['driver' => 'memory', 'ttl' => 0],
        ], $config));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function emailValidator(array $config = []): EmailValidator
    {
        return new EmailValidator($this->configuration($config));
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
