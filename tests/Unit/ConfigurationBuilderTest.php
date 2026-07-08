<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit;

use HarunGecit\EmailValidator\Config\ConfigurationBuilder;
use HarunGecit\EmailValidator\Config\Configuration;
use PHPUnit\Framework\TestCase;

class ConfigurationBuilderTest extends TestCase
{
    public function testCreateReturnsBuilder(): void
    {
        $builder = ConfigurationBuilder::create();

        $this->assertInstanceOf(ConfigurationBuilder::class, $builder);
    }

    public function testBuildReturnsConfiguration(): void
    {
        $config = ConfigurationBuilder::create()->build();

        $this->assertInstanceOf(Configuration::class, $config);
    }

    public function testStrictPreset(): void
    {
        $config = ConfigurationBuilder::create()->strict()->build();

        $this->assertTrue($config->isFormatCheckEnabled());
        $this->assertTrue($config->isMxCheckEnabled());
        $this->assertTrue($config->isDisposableCheckEnabled());
        $this->assertTrue($config->isRoleBasedCheckEnabled());
        $this->assertTrue($config->isSmtpCheckEnabled());
        $this->assertTrue($config->isSubaddressCheckEnabled());
        $this->assertTrue($config->isCatchAllCheckEnabled());
    }

    public function testBasicPreset(): void
    {
        $config = ConfigurationBuilder::create()->basic()->build();

        $this->assertTrue($config->isFormatCheckEnabled());
        $this->assertFalse($config->isMxCheckEnabled());
        $this->assertTrue($config->isDisposableCheckEnabled());
        $this->assertFalse($config->isRoleBasedCheckEnabled());
    }

    public function testMinimalPreset(): void
    {
        $config = ConfigurationBuilder::create()->minimal()->build();

        $this->assertTrue($config->isFormatCheckEnabled());
        $this->assertFalse($config->isMxCheckEnabled());
        $this->assertFalse($config->isDisposableCheckEnabled());
        $this->assertFalse($config->isTypoSuggestionEnabled());
    }

    public function testStandardPreset(): void
    {
        $config = ConfigurationBuilder::create()->standard()->build();

        $this->assertTrue($config->isFormatCheckEnabled());
        $this->assertTrue($config->isMxCheckEnabled());
        $this->assertTrue($config->isDisposableCheckEnabled());
        $this->assertFalse($config->isRoleBasedCheckEnabled());
        $this->assertTrue($config->isTypoSuggestionEnabled());
    }

    public function testWithMemoryCache(): void
    {
        $config = ConfigurationBuilder::create()
            ->withMemoryCache(7200)
            ->build();

        $this->assertEquals('memory', $config->getCacheDriver());
        $this->assertEquals(7200, $config->getCacheTtl());
    }

    public function testWithFileCache(): void
    {
        $config = ConfigurationBuilder::create()
            ->withFileCache('/tmp/cache', 3600)
            ->build();

        $this->assertEquals('file', $config->getCacheDriver());
        $this->assertEquals(3600, $config->getCacheTtl());
        $this->assertEquals('/tmp/cache', $config->getCacheOptions()['directory']);
    }

    public function testWithRedisCache(): void
    {
        $config = ConfigurationBuilder::create()
            ->withRedisCache('localhost', 6380, 'secret', 1, 1800)
            ->build();

        $this->assertEquals('redis', $config->getCacheDriver());
        $this->assertEquals(1800, $config->getCacheTtl());
        $options = $config->getCacheOptions();
        $this->assertEquals('localhost', $options['host']);
        $this->assertEquals(6380, $options['port']);
        $this->assertEquals('secret', $options['password']);
        $this->assertEquals(1, $options['database']);
    }

    public function testWithoutCache(): void
    {
        $config = ConfigurationBuilder::create()
            ->withoutCache()
            ->build();

        $this->assertEquals('null', $config->getCacheDriver());
    }

    public function testWithRateLimiting(): void
    {
        $config = ConfigurationBuilder::create()
            ->withRateLimiting(50, 120)
            ->build();

        $this->assertTrue($config->isRateLimitEnabled());
        $this->assertEquals(50, $config->getRateLimitMaxAttempts());
        $this->assertEquals(120, $config->getRateLimitDecaySeconds());
    }

    public function testWithoutRateLimiting(): void
    {
        $config = ConfigurationBuilder::create()
            ->withRateLimiting(100, 60)
            ->withoutRateLimiting()
            ->build();

        $this->assertFalse($config->isRateLimitEnabled());
    }

    public function testWithRoleBasedCheck(): void
    {
        $config = ConfigurationBuilder::create()
            ->withRoleBasedCheck(['custom', 'prefix'])
            ->build();

        $this->assertTrue($config->isRoleBasedCheckEnabled());
        $prefixes = $config->getRoleBasedPrefixes();
        $this->assertContains('custom', $prefixes);
        $this->assertContains('prefix', $prefixes);
    }

    public function testWithTypoSuggestion(): void
    {
        $config = ConfigurationBuilder::create()
            ->withTypoSuggestion(['example.com', 'test.com'])
            ->build();

        $this->assertTrue($config->isTypoSuggestionEnabled());
        $domains = $config->getCommonDomains();
        $this->assertContains('example.com', $domains);
        $this->assertContains('test.com', $domains);
    }

    public function testWithSmtpCheck(): void
    {
        $config = ConfigurationBuilder::create()
            ->withSmtpCheck(15, 'test@example.com')
            ->build();

        $this->assertTrue($config->isSmtpCheckEnabled());
        $this->assertEquals(15, $config->getSmtpTimeout());
        $this->assertEquals('test@example.com', $config->getSmtpFromEmail());
    }

    public function testWithSubaddressCheck(): void
    {
        $config = ConfigurationBuilder::create()
            ->withSubaddressCheck()
            ->build();

        $this->assertTrue($config->isSubaddressCheckEnabled());
    }

    public function testWithCatchAllCheck(): void
    {
        $config = ConfigurationBuilder::create()
            ->withCatchAllCheck()
            ->build();

        $this->assertTrue($config->isCatchAllCheckEnabled());
    }

    public function testWithoutMxCheck(): void
    {
        $config = ConfigurationBuilder::create()
            ->withoutMxCheck()
            ->build();

        $this->assertFalse($config->isMxCheckEnabled());
    }

    public function testWithoutDisposableCheck(): void
    {
        $config = ConfigurationBuilder::create()
            ->withoutDisposableCheck()
            ->build();

        $this->assertFalse($config->isDisposableCheckEnabled());
    }

    public function testWithBlocklist(): void
    {
        $config = ConfigurationBuilder::create()
            ->withBlocklist('/path/to/blocklist.txt')
            ->build();

        $this->assertEquals('/path/to/blocklist.txt', $config->getBlocklistPath());
    }

    public function testWithAllowlist(): void
    {
        $config = ConfigurationBuilder::create()
            ->withAllowlist('/path/to/allowlist.txt')
            ->build();

        $this->assertEquals('/path/to/allowlist.txt', $config->getAllowlistPath());
    }

    public function testGetConfig(): void
    {
        $builder = ConfigurationBuilder::create();

        $this->assertInstanceOf(Configuration::class, $builder->getConfig());
    }

    public function testFluentInterface(): void
    {
        $builder = ConfigurationBuilder::create();

        $this->assertSame($builder, $builder->strict());
        $this->assertSame($builder, $builder->withMemoryCache());
        $this->assertSame($builder, $builder->withRateLimiting());
        $this->assertSame($builder, $builder->withRoleBasedCheck());
    }
}
