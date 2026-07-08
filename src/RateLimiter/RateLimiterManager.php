<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\RateLimiter;

use HarunGecit\EmailValidator\Contracts\RateLimiterInterface;
use HarunGecit\EmailValidator\Contracts\CacheInterface;
use HarunGecit\EmailValidator\Config\Configuration;

/**
 * Class RateLimiterManager
 *
 * Factory class for creating rate limiter instances.
 *
 * @package HarunGecit\EmailValidator\RateLimiter
 */
class RateLimiterManager
{
    /**
     * Creates a rate limiter based on configuration.
     *
     * @param Configuration $config The configuration instance.
     * @param CacheInterface $cache The cache adapter to use.
     * @return RateLimiterInterface
     */
    public static function create(Configuration $config, CacheInterface $cache): RateLimiterInterface
    {
        if (!$config->isRateLimitEnabled()) {
            return new NullRateLimiter();
        }

        return new TokenBucketLimiter(
            $cache,
            $config->getRateLimitMaxAttempts(),
            $config->getRateLimitDecaySeconds()
        );
    }
}
