<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Exceptions;

use Exception;
use Throwable;

/**
 * Class EmailValidatorException
 *
 * Base exception class for all email validator exceptions.
 *
 * @package HarunGecit\EmailValidator\Exceptions
 */
class EmailValidatorException extends Exception
{
    /**
     * @var string The email address associated with this exception.
     */
    protected string $email = '';

    /**
     * @var array<string, mixed> Additional context information.
     */
    protected array $context = [];

    /**
     * EmailValidatorException constructor.
     *
     * @param string $message The exception message.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous throwable.
     * @param string $email The email address associated with this exception.
     * @param array<string, mixed> $context Additional context information.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        string $email = '',
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->email = $email;
        $this->context = $context;
    }

    /**
     * Gets the email address associated with this exception.
     *
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Gets additional context information.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Gets a specific context value.
     *
     * @param string $key The context key.
     * @param mixed $default The default value if key doesn't exist.
     * @return mixed
     */
    public function getContextValue(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }
}
