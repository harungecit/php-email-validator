<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit\RateLimiter;

use HarunGecit\EmailValidator\RateLimiter\TokenBucketLimiter;
use HarunGecit\EmailValidator\Cache\MemoryCacheAdapter;
use PHPUnit\Framework\TestCase;

class TokenBucketLimiterTest extends TestCase
{
    private MemoryCacheAdapter $cache;
    private TokenBucketLimiter $limiter;

    protected function setUp(): void
    {
        $this->cache = new MemoryCacheAdapter();
        $this->limiter = new TokenBucketLimiter($this->cache, 5, 60);
    }

    public function testAttemptSucceedsWithinLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($this->limiter->attempt('test-key'));
        }
    }

    public function testAttemptFailsAfterLimitExceeded(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->attempt('test-key');
        }

        $this->assertFalse($this->limiter->attempt('test-key'));
    }

    public function testRemainingAttempts(): void
    {
        $this->assertEquals(5, $this->limiter->remainingAttempts('test-key'));

        $this->limiter->attempt('test-key');
        $this->assertEquals(4, $this->limiter->remainingAttempts('test-key'));

        $this->limiter->attempt('test-key');
        $this->limiter->attempt('test-key');
        $this->assertEquals(2, $this->limiter->remainingAttempts('test-key'));
    }

    public function testTooManyAttempts(): void
    {
        $this->assertFalse($this->limiter->tooManyAttempts('test-key'));

        for ($i = 0; $i < 5; $i++) {
            $this->limiter->attempt('test-key');
        }

        $this->assertTrue($this->limiter->tooManyAttempts('test-key'));
    }

    public function testClear(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->attempt('test-key');
        }
        $this->assertTrue($this->limiter->tooManyAttempts('test-key'));

        $this->limiter->clear('test-key');

        $this->assertFalse($this->limiter->tooManyAttempts('test-key'));
        $this->assertEquals(5, $this->limiter->remainingAttempts('test-key'));
    }

    public function testDifferentKeysAreIndependent(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->attempt('key1');
        }

        $this->assertTrue($this->limiter->tooManyAttempts('key1'));
        $this->assertFalse($this->limiter->tooManyAttempts('key2'));
        $this->assertEquals(5, $this->limiter->remainingAttempts('key2'));
    }

    public function testAvailableIn(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->attempt('test-key');
        }

        $availableIn = $this->limiter->availableIn('test-key');
        $this->assertGreaterThan(0, $availableIn);
        $this->assertLessThanOrEqual(60, $availableIn);
    }

    public function testAvailableInReturnsZeroForNewKey(): void
    {
        $this->assertEquals(0, $this->limiter->availableIn('new-key'));
    }

    public function testCustomMaxAttemptsAndDecay(): void
    {
        $limiter = new TokenBucketLimiter($this->cache, 3, 30);

        $this->assertEquals(3, $limiter->remainingAttempts('key'));

        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($limiter->attempt('key'));
        }
        $this->assertFalse($limiter->attempt('key'));
    }

    public function testGetMaxAttempts(): void
    {
        $this->assertEquals(5, $this->limiter->getMaxAttempts());

        $limiter = new TokenBucketLimiter($this->cache, 10, 30);
        $this->assertEquals(10, $limiter->getMaxAttempts());
    }

    public function testGetDecaySeconds(): void
    {
        $this->assertEquals(60, $this->limiter->getDecaySeconds());

        $limiter = new TokenBucketLimiter($this->cache, 10, 120);
        $this->assertEquals(120, $limiter->getDecaySeconds());
    }

    public function testSetPrefix(): void
    {
        $this->limiter->setPrefix('custom_');
        $this->limiter->attempt('key');

        $this->assertEquals(4, $this->limiter->remainingAttempts('key'));
    }

    public function testMinimumValues(): void
    {
        $limiter = new TokenBucketLimiter($this->cache, 0, 0);

        $this->assertEquals(1, $limiter->getMaxAttempts());
        $this->assertEquals(1, $limiter->getDecaySeconds());
    }
}
