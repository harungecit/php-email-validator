<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Exceptions;

use Throwable;

/**
 * Class RateLimitExceededException
 *
 * Exception thrown when rate limit is exceeded.
 *
 * @package HarunGecit\EmailValidator\Exceptions
 */
class RateLimitExceededException extends EmailValidatorException
{
    /**
     * @var int Seconds until the rate limit resets.
     */
    protected int $retryAfter;

    /**
     * @var int Maximum allowed attempts.
     */
    protected int $maxAttempts;

    /**
     * RateLimitExceededException constructor.
     *
     * @param string $message The exception message.
     * @param int $retryAfter Seconds until the rate limit resets.
     * @param int $maxAttempts Maximum allowed attempts.
     * @param string $key The rate limit key.
     * @param Throwable|null $previous The previous throwable.
     */
    public function __construct(
        string $message = 'Too many validation attempts',
        int $retryAfter = 60,
        int $maxAttempts = 100,
        string $key = '',
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            429,
            $previous,
            '',
            [
                'retry_after' => $retryAfter,
                'max_attempts' => $maxAttempts,
                'key' => $key
            ]
        );

        $this->retryAfter = $retryAfter;
        $this->maxAttempts = $maxAttempts;
    }

    /**
     * Gets the number of seconds until the rate limit resets.
     *
     * @return int
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Gets the maximum allowed attempts.
     *
     * @return int
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Creates a new instance with specific details.
     *
     * @param int $retryAfter Seconds until reset.
     * @param int $maxAttempts Maximum attempts allowed.
     * @param string $key The rate limit key.
     * @return self
     */
    public static function create(int $retryAfter, int $maxAttempts, string $key = ''): self
    {
        return new self(
            "Rate limit exceeded. Please try again in {$retryAfter} seconds.",
            $retryAfter,
            $maxAttempts,
            $key
        );
    }
}
