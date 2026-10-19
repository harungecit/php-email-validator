<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit\Sync;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\Config\ConfigurationBuilder;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Exceptions\ConfigurationException;
use HarunGecit\EmailValidator\Sync\HttpDownloader;
use HarunGecit\EmailValidator\Sync\ListSynchronizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ListSynchronizerTest extends TestCase
{
    private string $directory;
    private string $path;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ev-sync-' . bin2hex(random_bytes(4));
        $this->path = $this->directory . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'synced.txt';
    }

    protected function tearDown(): void
    {
        foreach ([dirname($this->path), $this->directory] as $directory) {
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    /**
     * @param array<string, string|RuntimeException> $responses URL => body or exception
     */
    private function synchronizer(array $responses, ?Configuration $config = null): ListSynchronizer
    {
        $config ??= ConfigurationBuilder::create()->basic()->build();
        $config->setSyncedBlocklistPath($this->path)->setSyncUrls(array_keys($responses));

        $downloader = new class ($responses) extends HttpDownloader {
            /** @param array<string, string|RuntimeException> $responses */
            public function __construct(private array $responses)
            {
            }

            public function get(string $url, int $timeout): string
            {
                $response = $this->responses[$url];
                if ($response instanceof RuntimeException) {
                    throw $response;
                }
                return $response;
            }
        };

        return new ListSynchronizer($config, $downloader);
    }

    public function testFirstSyncCreatesTheFileWithOnlyNewDomains(): void
    {
        $result = $this->synchronizer([
            'https://a.example/list.txt' => "fresh-temp.example\nmailinator.com\n163.com\nsub.163.com\n",
            'https://b.example/list.json' => '["other-temp.example", "FRESH-TEMP.example"]',
        ])->sync();

        $this->assertTrue($result->isSuccessful());
        $this->assertTrue($result->hasChanged());
        $this->assertSame(2, $result->getAdded());
        $this->assertSame(2, $result->getTotal());
        $this->assertSame([], $result->getErrors());
        // Bundled domains and allowlisted domains (and their subdomains) are not stored.
        $this->assertSame("fresh-temp.example\nother-temp.example\n", file_get_contents($this->path));
    }

    public function testSyncedDomainsAreBlocked(): void
    {
        $this->synchronizer(['https://a.example/list.txt' => "fresh-temp.example\n"])->sync();

        $config = ConfigurationBuilder::create()->basic()->withSyncedBlocklist($this->path)->build();
        $check = (new EmailValidator($config))->checkDisposable('user@fresh-temp.example');

        $this->assertTrue($check->isDisposable());
        $this->assertSame('synced', $check->getSource());
    }

    public function testAppendModeKeepsExistingDomains(): void
    {
        $this->synchronizer(['https://a.example/list.txt' => "first-temp.example\n"])->sync();
        $result = $this->synchronizer(['https://a.example/list.txt' => "second-temp.example\n"])->sync();

        $this->assertSame(1, $result->getAdded());
        $this->assertSame(0, $result->getRemoved());
        $this->assertSame("first-temp.example\nsecond-temp.example\n", file_get_contents($this->path));
    }

    public function testReplaceModeDropsDomainsNoLongerListed(): void
    {
        $this->synchronizer(['https://a.example/list.txt' => "first-temp.example\n"])->sync();
        $result = $this->synchronizer(['https://a.example/list.txt' => "second-temp.example\n"])->sync(true);

        $this->assertTrue($result->wasReplaced());
        $this->assertSame(1, $result->getRemoved());
        $this->assertSame("second-temp.example\n", file_get_contents($this->path));
    }

    public function testReplaceFallsBackToAppendWhenASourceFails(): void
    {
        $this->synchronizer(['https://a.example/list.txt' => "first-temp.example\n"])->sync();
        $result = $this->synchronizer([
            'https://a.example/list.txt' => "second-temp.example\n",
            'https://b.example/list.txt' => new RuntimeException('timeout'),
        ])->sync(true);

        $this->assertTrue($result->isSuccessful());
        $this->assertFalse($result->wasReplaced());
        $this->assertSame(['https://b.example/list.txt' => 'timeout'], $result->getErrors());
        $this->assertSame("first-temp.example\nsecond-temp.example\n", file_get_contents($this->path));
    }

    public function testBadResponsesNeverTouchTheExistingFile(): void
    {
        $this->synchronizer(['https://a.example/list.txt' => "first-temp.example\n"])->sync();
        $before = file_get_contents($this->path);

        $result = $this->synchronizer([
            'https://html.example' => "<!DOCTYPE html><html><body>502 Bad Gateway</body></html>",
            'https://empty.example' => "   \n",
            'https://junk.example' => "not a list\n!!!\n",
            'https://down.example' => new RuntimeException('Connection refused'),
        ])->sync(true);

        $this->assertFalse($result->isSuccessful());
        $this->assertFalse($result->hasChanged());
        $this->assertCount(4, $result->getErrors());
        $this->assertSame(1, $result->getTotal());
        $this->assertSame($before, file_get_contents($this->path));
    }

    public function testUnchangedListIsNotRewritten(): void
    {
        $this->synchronizer(['https://a.example/list.txt' => "first-temp.example\n"])->sync();
        touch($this->path, time() - 3600);
        clearstatcache();
        $mtime = filemtime($this->path);

        $result = $this->synchronizer(['https://a.example/list.txt' => "first-temp.example\n"])->sync();

        clearstatcache();
        $this->assertFalse($result->hasChanged());
        $this->assertSame($mtime, filemtime($this->path));
        $this->assertSame(['path', 'successful', 'changed', 'replaced', 'added', 'removed', 'total', 'sources'], array_keys($result->toArray()));
    }

    public function testCustomAllowlistIsRespected(): void
    {
        $config = ConfigurationBuilder::create()->basic()->withCustomAllowlist(['keep-me.example'])->build();

        $this->synchronizer(['https://a.example/list.txt' => "keep-me.example\nmx.keep-me.example\nfresh-temp.example\n"], $config)->sync();

        $this->assertSame("fresh-temp.example\n", file_get_contents($this->path));
    }

    public function testRequiresATargetPath(): void
    {
        $this->expectException(ConfigurationException::class);
        (new ListSynchronizer(new Configuration()))->sync();
    }

    public function testRequiresAtLeastOneUrl(): void
    {
        $config = (new Configuration())->setSyncedBlocklistPath($this->path)->setSyncUrls([]);

        $this->expectException(ConfigurationException::class);
        (new ListSynchronizer($config))->sync();
    }

    public function testDownloaderRejectsNonHttpUrls(): void
    {
        $this->expectException(RuntimeException::class);
        (new HttpDownloader())->get('file:///etc/passwd', 5);
    }
}
