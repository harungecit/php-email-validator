<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit;

use HarunGecit\EmailValidator\Fetcher;
use HarunGecit\EmailValidator\Support\Domain;
use PHPUnit\Framework\TestCase;

/**
 * Integrity checks for the generated data files shipped with the package.
 */
class BundledDataTest extends TestCase
{
    /**
     * @return array<string>
     */
    private static function blocklist(): array
    {
        return explode("\n", trim((string) file_get_contents(Fetcher::getBlocklistPath())));
    }

    public function testBlocklistIsLargeSortedAndValid(): void
    {
        $domains = self::blocklist();
        $sorted = $domains;
        sort($sorted, SORT_STRING);

        $this->assertGreaterThan(100000, count($domains));
        $this->assertSame($sorted, $domains, 'blocklist.conf must be sorted');
        $this->assertSame(count($domains), count(array_unique($domains)), 'blocklist.conf must not contain duplicates');

        $invalid = array_values(array_filter($domains, static fn (string $domain): bool => !Domain::isValid($domain)));
        $this->assertSame([], array_slice($invalid, 0, 20), 'blocklist.conf must only contain valid domains');
    }

    public function testCompiledIndexMatchesBlocklist(): void
    {
        $this->assertFileExists(Fetcher::getBlocklistIndexPath());

        $index = require Fetcher::getBlocklistIndexPath();
        $conf = array_flip(self::blocklist());

        $this->assertIsArray($index);
        $this->assertCount(count($conf), $index);
        $this->assertSame([], array_slice(array_keys(array_diff_key($index, $conf)), 0, 20));
    }

    public function testNoAllowlistedDomainIsBlocked(): void
    {
        $blocked = array_flip(self::blocklist());
        $conflicts = [];

        foreach (Fetcher::loadAllowlist(false) as $allowed) {
            if (isset($blocked[$allowed])) {
                $conflicts[] = $allowed;
            }
        }

        $this->assertSame([], $conflicts);
    }

    public function testNoBlocklistEntryIsUnderAnAllowlistedDomain(): void
    {
        $allowed = array_flip(Fetcher::loadAllowlist(false));
        $local = array_flip(Fetcher::loadCustomBlocklist(dirname(Fetcher::getBlocklistPath()) . '/blocklist.local.conf'));
        $conflicts = [];

        foreach (self::blocklist() as $domain) {
            if (isset($local[$domain])) {
                continue;
            }
            foreach (Domain::withParents($domain) as $parent) {
                if (isset($allowed[$parent])) {
                    $conflicts[] = "{$domain} (under {$parent})";
                    break;
                }
            }
        }

        $this->assertSame([], array_slice($conflicts, 0, 20));
    }

    public function testMetadataDescribesTheBuild(): void
    {
        $metadata = Fetcher::getMetadata();

        $this->assertArrayHasKey('generated_at', $metadata);
        $this->assertSame(count(self::blocklist()), $metadata['total']);
        $this->assertArrayHasKey('disposable-email-domains', $metadata['sources']);
        $this->assertArrayHasKey('eramitgupta/disposable-email', $metadata['sources']);
    }

    public function testListFilesEndWithANewline(): void
    {
        foreach ([Fetcher::getBlocklistPath(), Fetcher::getAllowlistPath()] as $path) {
            $this->assertStringEndsWith("\n", (string) file_get_contents($path), basename($path));
        }
    }
}
