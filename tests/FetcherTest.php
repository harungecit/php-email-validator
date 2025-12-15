<?php

namespace HarunGecit\EmailValidator\Tests;

use PHPUnit\Framework\TestCase;
use HarunGecit\EmailValidator\Fetcher;
use RuntimeException;

/**
 * Test suite for Fetcher class
 *
 * @covers \HarunGecit\EmailValidator\Fetcher
 */
class FetcherTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear cache before each test
        Fetcher::clearCache();
    }

    // ========================================
    // Load Blocklist Tests
    // ========================================

    public function testLoadBlocklist(): void
    {
        $blocklist = Fetcher::loadBlocklist();

        $this->assertIsArray($blocklist);
        $this->assertNotEmpty($blocklist);
    }

    public function testLoadBlocklistContainsKnownDomains(): void
    {
        $blocklist = Fetcher::loadBlocklist();

        $this->assertContains('mailinator.com', $blocklist);
        $this->assertContains('guerrillamail.com', $blocklist);
        $this->assertContains('tempmail.com', $blocklist);
    }

    public function testLoadBlocklistWithCache(): void
    {
        // First load
        $blocklist1 = Fetcher::loadBlocklist(true);

        // Second load should use cache
        $blocklist2 = Fetcher::loadBlocklist(true);

        $this->assertEquals($blocklist1, $blocklist2);
    }

    public function testLoadBlocklistWithoutCache(): void
    {
        $blocklist = Fetcher::loadBlocklist(false);

        $this->assertIsArray($blocklist);
        $this->assertNotEmpty($blocklist);
    }

    // ========================================
    // Load Allowlist Tests
    // ========================================

    public function testLoadAllowlist(): void
    {
        $allowlist = Fetcher::loadAllowlist();

        $this->assertIsArray($allowlist);
        $this->assertNotEmpty($allowlist);
    }

    public function testLoadAllowlistWithCache(): void
    {
        // First load
        $allowlist1 = Fetcher::loadAllowlist(true);

        // Second load should use cache
        $allowlist2 = Fetcher::loadAllowlist(true);

        $this->assertEquals($allowlist1, $allowlist2);
    }

    // ========================================
    // Load All Tests
    // ========================================

    public function testLoadAll(): void
    {
        $lists = Fetcher::loadAll();

        $this->assertArrayHasKey('blocklist', $lists);
        $this->assertArrayHasKey('allowlist', $lists);
        $this->assertIsArray($lists['blocklist']);
        $this->assertIsArray($lists['allowlist']);
    }

    // ========================================
    // Cache Tests
    // ========================================

    public function testClearCache(): void
    {
        // Load to populate cache
        Fetcher::loadBlocklist();
        Fetcher::loadAllowlist();

        // Clear cache
        Fetcher::clearCache();

        // This should load fresh data without errors
        $blocklist = Fetcher::loadBlocklist();
        $this->assertNotEmpty($blocklist);
    }

    // ========================================
    // Path Tests
    // ========================================

    public function testGetBlocklistPath(): void
    {
        $path = Fetcher::getBlocklistPath();

        $this->assertIsString($path);
        $this->assertStringContainsString('blocklist.conf', $path);
    }

    public function testGetAllowlistPath(): void
    {
        $path = Fetcher::getAllowlistPath();

        $this->assertIsString($path);
        $this->assertStringContainsString('allowlist.conf', $path);
    }

    // ========================================
    // File Exists Tests
    // ========================================

    public function testBlocklistExists(): void
    {
        $this->assertTrue(Fetcher::blocklistExists());
    }

    public function testAllowlistExists(): void
    {
        $this->assertTrue(Fetcher::allowlistExists());
    }

    // ========================================
    // Count Tests
    // ========================================

    public function testGetBlocklistCount(): void
    {
        $count = Fetcher::getBlocklistCount();

        $this->assertIsInt($count);
        $this->assertGreaterThan(0, $count);
    }

    public function testGetAllowlistCount(): void
    {
        $count = Fetcher::getAllowlistCount();

        $this->assertIsInt($count);
        $this->assertGreaterThan(0, $count);
    }

    // ========================================
    // Custom List Tests
    // ========================================

    public function testLoadCustomBlocklist(): void
    {
        // Use the existing blocklist as a custom list
        $customList = Fetcher::loadCustomBlocklist(Fetcher::getBlocklistPath());

        $this->assertIsArray($customList);
        $this->assertNotEmpty($customList);
    }

    public function testLoadCustomAllowlist(): void
    {
        // Use the existing allowlist as a custom list
        $customList = Fetcher::loadCustomAllowlist(Fetcher::getAllowlistPath());

        $this->assertIsArray($customList);
        $this->assertNotEmpty($customList);
    }

    public function testLoadCustomListThrowsExceptionForMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('List file not found');

        Fetcher::loadCustomBlocklist('/nonexistent/path/file.conf');
    }

    // ========================================
    // Merge Lists Tests
    // ========================================

    public function testMergeLists(): void
    {
        $merged = Fetcher::mergeLists([
            Fetcher::getBlocklistPath(),
            Fetcher::getAllowlistPath(),
        ]);

        $this->assertIsArray($merged);
        $this->assertNotEmpty($merged);

        // Merged list should contain domains from both lists
        $blocklist = Fetcher::loadBlocklist();
        $allowlist = Fetcher::loadAllowlist();

        // Check that some domains from each list are present
        $this->assertContains($blocklist[0], $merged);
        $this->assertContains($allowlist[0], $merged);
    }

    public function testMergeListsDeduplicates(): void
    {
        // Create a temporary file with duplicate content
        $tempFile = sys_get_temp_dir() . '/test_list_' . uniqid() . '.conf';
        file_put_contents($tempFile, "domain1.com\ndomain2.com\n");

        try {
            $merged = Fetcher::mergeLists([$tempFile, $tempFile]);

            // Should only have unique entries
            $this->assertCount(2, $merged);
        } finally {
            unlink($tempFile);
        }
    }

    // ========================================
    // Save List Tests
    // ========================================

    public function testSaveList(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_save_' . uniqid() . '.conf';
        $domains = ['domain3.com', 'domain1.com', 'domain2.com'];

        try {
            $result = Fetcher::saveList($tempFile, $domains);

            $this->assertTrue($result);
            $this->assertFileExists($tempFile);

            // Read back and verify
            $savedDomains = Fetcher::loadCustomBlocklist($tempFile);

            // Should be sorted and lowercase
            $this->assertEquals(['domain1.com', 'domain2.com', 'domain3.com'], $savedDomains);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testSaveListDeduplicatesAndNormalizes(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_save_dedup_' . uniqid() . '.conf';
        $domains = ['DOMAIN.COM', 'domain.com', '  Domain.Com  '];

        try {
            Fetcher::saveList($tempFile, $domains);

            $savedDomains = Fetcher::loadCustomBlocklist($tempFile);

            // Should have only one entry, normalized
            $this->assertCount(1, $savedDomains);
            $this->assertEquals('domain.com', $savedDomains[0]);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    // ========================================
    // Data Quality Tests
    // ========================================

    public function testBlocklistDomainsAreLowercase(): void
    {
        $blocklist = Fetcher::loadBlocklist();

        foreach (array_slice($blocklist, 0, 100) as $domain) {
            $this->assertEquals(
                strtolower($domain),
                $domain,
                "Domain '{$domain}' should be lowercase"
            );
        }
    }

    public function testAllowlistDomainsAreLowercase(): void
    {
        $allowlist = Fetcher::loadAllowlist();

        foreach ($allowlist as $domain) {
            $this->assertEquals(
                strtolower($domain),
                $domain,
                "Domain '{$domain}' should be lowercase"
            );
        }
    }

    public function testBlocklistHasNoDuplicates(): void
    {
        $blocklist = Fetcher::loadBlocklist();

        $uniqueCount = count(array_unique($blocklist));
        $totalCount = count($blocklist);

        $this->assertEquals($uniqueCount, $totalCount, 'Blocklist should have no duplicates');
    }

    public function testAllowlistHasNoDuplicates(): void
    {
        $allowlist = Fetcher::loadAllowlist();

        $uniqueCount = count(array_unique($allowlist));
        $totalCount = count($allowlist);

        $this->assertEquals($uniqueCount, $totalCount, 'Allowlist should have no duplicates');
    }
}
