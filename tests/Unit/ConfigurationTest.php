<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit;

use HarunGecit\EmailValidator\Config\Configuration;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new Configuration();

        $this->assertTrue($config->isFormatCheckEnabled());
        $this->assertTrue($config->isMxCheckEnabled());
        $this->assertTrue($config->isDisposableCheckEnabled());
        $this->assertFalse($config->isRoleBasedCheckEnabled());
        $this->assertFalse($config->isSmtpCheckEnabled());
        $this->assertFalse($config->isTypoSuggestionEnabled());
        $this->assertFalse($config->isSubaddressCheckEnabled());
        $this->assertFalse($config->isCatchAllCheckEnabled());
        $this->assertEquals('memory', $config->getCacheDriver());
        $this->assertEquals(3600, $config->getCacheTtl());
        $this->assertFalse($config->isRateLimitEnabled());
    }

    public function testEnableChecks(): void
    {
        $config = new Configuration();

        $config->enableFormatCheck(false);
        $config->enableMxCheck(false);
        $config->enableDisposableCheck(false);
        $config->enableRoleBasedCheck(true);
        $config->enableSmtpCheck(true);
        $config->enableTypoSuggestion(true);
        $config->enableSubaddressCheck(true);
        $config->enableCatchAllCheck(true);

        $this->assertFalse($config->isFormatCheckEnabled());
        $this->assertFalse($config->isMxCheckEnabled());
        $this->assertFalse($config->isDisposableCheckEnabled());
        $this->assertTrue($config->isRoleBasedCheckEnabled());
        $this->assertTrue($config->isSmtpCheckEnabled());
        $this->assertTrue($config->isTypoSuggestionEnabled());
        $this->assertTrue($config->isSubaddressCheckEnabled());
        $this->assertTrue($config->isCatchAllCheckEnabled());
    }

    public function testCacheSettings(): void
    {
        $config = new Configuration();

        $config->setCacheDriver('redis');
        $config->setCacheTtl(7200);
        $config->setCachePrefix('custom_');
        $config->setCacheOptions(['host' => '127.0.0.1']);

        $this->assertEquals('redis', $config->getCacheDriver());
        $this->assertEquals(7200, $config->getCacheTtl());
        $this->assertEquals('custom_', $config->getCachePrefix());
        $this->assertEquals(['host' => '127.0.0.1'], $config->getCacheOptions());
    }

    public function testRateLimitSettings(): void
    {
        $config = new Configuration();

        $config->enableRateLimiting(true);
        $config->setRateLimitMaxAttempts(50);
        $config->setRateLimitDecaySeconds(120);

        $this->assertTrue($config->isRateLimitEnabled());
        $this->assertEquals(50, $config->getRateLimitMaxAttempts());
        $this->assertEquals(120, $config->getRateLimitDecaySeconds());
    }

    public function testRoleBasedPrefixes(): void
    {
        $config = new Configuration();
        $prefixes = $config->getRoleBasedPrefixes();

        $this->assertContains('admin', $prefixes);
        $this->assertContains('info', $prefixes);
        $this->assertContains('support', $prefixes);

        $config->setRoleBasedPrefixes(['custom', 'prefix']);
        $this->assertEquals(['custom', 'prefix'], $config->getRoleBasedPrefixes());

        $config->addRoleBasedPrefix('extra');
        $this->assertContains('extra', $config->getRoleBasedPrefixes());
    }

    public function testRemoveRoleBasedPrefix(): void
    {
        $config = new Configuration();
        $config->setRoleBasedPrefixes(['admin', 'info', 'support']);

        $config->removeRoleBasedPrefix('info');
        $prefixes = $config->getRoleBasedPrefixes();

        $this->assertContains('admin', $prefixes);
        $this->assertNotContains('info', $prefixes);
        $this->assertContains('support', $prefixes);
    }

    public function testCommonDomains(): void
    {
        $config = new Configuration();
        $domains = $config->getCommonDomains();

        $this->assertContains('gmail.com', $domains);
        $this->assertContains('yahoo.com', $domains);

        $config->setCommonDomains(['example.com']);
        $this->assertEquals(['example.com'], $config->getCommonDomains());

        $config->addCommonDomain('test.com');
        $this->assertContains('test.com', $config->getCommonDomains());
    }

    public function testTypoMappings(): void
    {
        $config = new Configuration();

        $config->setTypoMappings(['gmial.com' => 'gmail.com']);
        $this->assertEquals(['gmial.com' => 'gmail.com'], $config->getTypoMappings());

        $config->addTypoMapping('yaho.com', 'yahoo.com');
        $mappings = $config->getTypoMappings();
        $this->assertEquals('gmail.com', $mappings['gmial.com']);
        $this->assertEquals('yahoo.com', $mappings['yaho.com']);
    }

    public function testListPaths(): void
    {
        $config = new Configuration();

        $config->setBlocklistPath('/path/to/blocklist.txt');
        $config->setAllowlistPath('/path/to/allowlist.txt');
        $config->setRoleBasedPath('/path/to/rolebased.txt');

        $this->assertEquals('/path/to/blocklist.txt', $config->getBlocklistPath());
        $this->assertEquals('/path/to/allowlist.txt', $config->getAllowlistPath());
        $this->assertEquals('/path/to/rolebased.txt', $config->getRoleBasedPath());
    }

    public function testSmtpSettings(): void
    {
        $config = new Configuration();

        $config->setSmtpTimeout(15);
        $config->setSmtpFromEmail('test@example.com');
        $config->setSmtpFromDomain('example.com');

        $this->assertEquals(15, $config->getSmtpTimeout());
        $this->assertEquals('test@example.com', $config->getSmtpFromEmail());
        $this->assertEquals('example.com', $config->getSmtpFromDomain());
    }

    public function testDnsSettings(): void
    {
        $config = new Configuration();

        $config->setDnsTimeout(10);

        $this->assertEquals(10, $config->getDnsTimeout());
    }

    public function testFromArray(): void
    {
        $data = [
            'checks' => [
                'format' => false,
                'mx' => true,
                'disposable' => false,
                'role_based' => true,
            ],
            'cache' => [
                'driver' => 'file',
                'ttl' => 1800,
                'prefix' => 'test_',
            ],
            'rate_limit' => [
                'enabled' => true,
                'max_attempts' => 200,
                'decay_seconds' => 30,
            ],
        ];

        $config = Configuration::fromArray($data);

        $this->assertFalse($config->isFormatCheckEnabled());
        $this->assertTrue($config->isMxCheckEnabled());
        $this->assertFalse($config->isDisposableCheckEnabled());
        $this->assertTrue($config->isRoleBasedCheckEnabled());
        $this->assertEquals('file', $config->getCacheDriver());
        $this->assertEquals(1800, $config->getCacheTtl());
        $this->assertEquals('test_', $config->getCachePrefix());
        $this->assertTrue($config->isRateLimitEnabled());
        $this->assertEquals(200, $config->getRateLimitMaxAttempts());
        $this->assertEquals(30, $config->getRateLimitDecaySeconds());
    }

    public function testToArray(): void
    {
        $config = new Configuration();
        $config->enableRoleBasedCheck(true);
        $config->setCacheDriver('redis');

        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('checks', $array);
        $this->assertArrayHasKey('cache', $array);
        $this->assertArrayHasKey('rate_limit', $array);
        $this->assertTrue($array['checks']['role_based']);
        $this->assertEquals('redis', $array['cache']['driver']);
    }

    public function testFluentInterface(): void
    {
        $config = new Configuration();

        $this->assertSame($config, $config->enableFormatCheck());
        $this->assertSame($config, $config->enableMxCheck());
        $this->assertSame($config, $config->setCacheDriver('memory'));
        $this->assertSame($config, $config->enableRateLimiting());
    }
}
