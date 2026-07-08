<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\RateLimiter;

use HarunGecit\EmailValidator\Contracts\RateLimiterInterface;
use HarunGecit\EmailValidator\Contracts\CacheInterface;

/**
 * Class TokenBucketLimiter
 *
 * Token bucket rate limiter implementation.
 *
 * @package HarunGecit\EmailValidator\RateLimiter
 */
class TokenBucketLimiter implements RateLimiterInterface
{
    /**
     * @var CacheInterface Cache adapter.
     */
    private CacheInterface $cache;

    /**
     * @var int Maximum number of attempts allowed.
     */
    private int $maxAttempts;

    /**
     * @var int Time window in seconds.
     */
    private int $decaySeconds;

    /**
     * @var string Cache key prefix.
     */
    private string $prefix = 'rate_limit_';

    /**
     * TokenBucketLimiter constructor.
     *
     * @param CacheInterface $cache Cache adapter.
     * @param int $maxAttempts Maximum attempts allowed.
     * @param int $decaySeconds Time window in seconds.
     */
    public function __construct(
        CacheInterface $cache,
        int $maxAttempts = 100,
        int $decaySeconds = 60
    ) {
        $this->cache = $cache;
        $this->maxAttempts = max(1, $maxAttempts);
        $this->decaySeconds = max(1, $decaySeconds);
    }

    /**
     * {@inheritdoc}
     */
    public function attempt(string $key): bool
    {
        $cacheKey = $this->prefix . $key;

        $data = $this->cache->get($cacheKey);

        if ($data === null) {
            // First attempt
            $this->cache->set($cacheKey, [
                'attempts' => 1,
                'started' => time(),
            ], $this->decaySeconds);
            return true;
        }

        // Check if we need to reset
        if (!is_array($data) || !isset($data['started']) || !isset($data['attempts'])) {
            $this->cache->set($cacheKey, [
                'attempts' => 1,
                'started' => time(),
            ], $this->decaySeconds);
            return true;
        }

        $elapsed = time() - $data['started'];

        // Window expired, reset
        if ($elapsed >= $this->decaySeconds) {
            $this->cache->set($cacheKey, [
                'attempts' => 1,
                'started' => time(),
            ], $this->decaySeconds);
            return true;
        }

        // Check if limit exceeded
        if ($data['attempts'] >= $this->maxAttempts) {
            return false;
        }

        // Increment attempts
        $data['attempts']++;
        $remainingTtl = $this->decaySeconds - $elapsed;
        $this->cache->set($cacheKey, $data, $remainingTtl);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function tooManyAttempts(string $key): bool
    {
        return $this->remainingAttempts($key) <= 0;
    }

    /**
     * {@inheritdoc}
     */
    public function remainingAttempts(string $key): int
    {
        $cacheKey = $this->prefix . $key;
        $data = $this->cache->get($cacheKey);

        if ($data === null || !is_array($data) || !isset($data['attempts'])) {
            return $this->maxAttempts;
        }

        // Check if window expired
        if (isset($data['started'])) {
            $elapsed = time() - $data['started'];
            if ($elapsed >= $this->decaySeconds) {
                return $this->maxAttempts;
            }
        }

        return max(0, $this->maxAttempts - $data['attempts']);
    }

    /**
     * {@inheritdoc}
     */
    public function availableIn(string $key): int
    {
        $cacheKey = $this->prefix . $key;
        $data = $this->cache->get($cacheKey);

        if ($data === null || !is_array($data) || !isset($data['started'])) {
            return 0;
        }

        $elapsed = time() - $data['started'];
        $remaining = $this->decaySeconds - $elapsed;

        return max(0, $remaining);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(string $key): void
    {
        $this->cache->delete($this->prefix . $key);
    }

    /**
     * Gets the maximum number of attempts allowed.
     *
     * @return int
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Gets the decay time in seconds.
     *
     * @return int
     */
    public function getDecaySeconds(): int
    {
        return $this->decaySeconds;
    }

    /**
     * Sets the cache key prefix.
     *
     * @param string $prefix The prefix to use.
     * @return self
     */
    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }
}
