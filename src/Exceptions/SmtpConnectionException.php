<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Exceptions;

use Throwable;

/**
 * Class SmtpConnectionException
 *
 * Exception thrown when SMTP connection fails.
 *
 * @package HarunGecit\EmailValidator\Exceptions
 */
class SmtpConnectionException extends EmailValidatorException
{
    /**
     * @var string The SMTP host.
     */
    protected string $host;

    /**
     * @var int The SMTP port.
     */
    protected int $port;

    /**
     * @var string The SMTP error message.
     */
    protected string $smtpError;

    /**
     * SmtpConnectionException constructor.
     *
     * @param string $message The exception message.
     * @param string $host The SMTP host.
     * @param int $port The SMTP port.
     * @param string $smtpError The SMTP error message.
     * @param string $email The email being verified.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous throwable.
     */
    public function __construct(
        string $message,
        string $host = '',
        int $port = 25,
        string $smtpError = '',
        string $email = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            $code,
            $previous,
            $email,
            [
                'host' => $host,
                'port' => $port,
                'smtp_error' => $smtpError
            ]
        );

        $this->host = $host;
        $this->port = $port;
        $this->smtpError = $smtpError;
    }

    /**
     * Gets the SMTP host.
     *
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Gets the SMTP port.
     *
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Gets the SMTP error message.
     *
     * @return string
     */
    public function getSmtpError(): string
    {
        return $this->smtpError;
    }

    /**
     * Creates an exception for connection failure.
     *
     * @param string $host The SMTP host.
     * @param int $port The SMTP port.
     * @param string $error The error message.
     * @param string $email The email being verified.
     * @return self
     */
    public static function connectionFailed(string $host, int $port, string $error, string $email = ''): self
    {
        return new self(
            "Failed to connect to SMTP server {$host}:{$port}: {$error}",
            $host,
            $port,
            $error,
            $email
        );
    }

    /**
     * Creates an exception for timeout.
     *
     * @param string $host The SMTP host.
     * @param int $port The SMTP port.
     * @param int $timeout The timeout in seconds.
     * @param string $email The email being verified.
     * @return self
     */
    public static function timeout(string $host, int $port, int $timeout, string $email = ''): self
    {
        return new self(
            "SMTP connection to {$host}:{$port} timed out after {$timeout} seconds",
            $host,
            $port,
            "Connection timeout",
            $email
        );
    }

    /**
     * Creates an exception for unexpected response.
     *
     * @param string $host The SMTP host.
     * @param int $expectedCode The expected response code.
     * @param int $actualCode The actual response code.
     * @param string $response The full response.
     * @param string $email The email being verified.
     * @return self
     */
    public static function unexpectedResponse(
        string $host,
        int $expectedCode,
        int $actualCode,
        string $response,
        string $email = ''
    ): self {
        return new self(
            "Unexpected SMTP response from {$host}: expected {$expectedCode}, got {$actualCode}",
            $host,
            25,
            $response,
            $email,
            $actualCode
        );
    }
}
