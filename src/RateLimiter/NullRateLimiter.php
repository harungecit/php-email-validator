<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\RateLimiter;

use HarunGecit\EmailValidator\Contracts\RateLimiterInterface;

/**
 * Class NullRateLimiter
 *
 * Null rate limiter that allows all attempts.
 * Used when rate limiting is disabled.
 *
 * @package HarunGecit\EmailValidator\RateLimiter
 */
class NullRateLimiter implements RateLimiterInterface
{
    /**
     * {@inheritdoc}
     */
    public function attempt(string $key): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function tooManyAttempts(string $key): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function remainingAttempts(string $key): int
    {
        return PHP_INT_MAX;
    }

    /**
     * {@inheritdoc}
     */
    public function availableIn(string $key): int
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(string $key): void
    {
        // No-op
    }
}
