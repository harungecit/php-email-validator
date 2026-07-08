<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Contracts;

/**
 * Interface RateLimiterInterface
 *
 * Defines the contract for rate limiting implementations.
 *
 * @package HarunGecit\EmailValidator\Contracts
 */
interface RateLimiterInterface
{
    /**
     * Attempts to acquire a rate limit slot.
     *
     * @param string $key The unique identifier for the rate limit (e.g., IP address, user ID).
     * @return bool True if the attempt is allowed, false if rate limit exceeded.
     */
    public function attempt(string $key): bool;

    /**
     * Checks if too many attempts have been made.
     *
     * @param string $key The unique identifier for the rate limit.
     * @return bool True if rate limit has been exceeded.
     */
    public function tooManyAttempts(string $key): bool;

    /**
     * Gets the number of remaining attempts.
     *
     * @param string $key The unique identifier for the rate limit.
     * @return int The number of attempts remaining.
     */
    public function remainingAttempts(string $key): int;

    /**
     * Gets the number of seconds until the rate limit resets.
     *
     * @param string $key The unique identifier for the rate limit.
     * @return int Seconds until the rate limit resets.
     */
    public function availableIn(string $key): int;

    /**
     * Clears the rate limit for a given key.
     *
     * @param string $key The unique identifier for the rate limit.
     * @return void
     */
    public function clear(string $key): void;
}
