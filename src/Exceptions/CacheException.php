<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Exceptions;

/**
 * Class CacheException
 *
 * Exception thrown when cache operations fail.
 *
 * @package HarunGecit\EmailValidator\Exceptions
 */
class CacheException extends EmailValidatorException
{
    /**
     * Creates an exception for cache driver not found.
     *
     * @param string $driver The driver name.
     * @return self
     */
    public static function driverNotFound(string $driver): self
    {
        return new self(
            "Cache driver not found: {$driver}",
            1,
            null,
            '',
            ['driver' => $driver]
        );
    }

    /**
     * Creates an exception for missing extension.
     *
     * @param string $extension The extension name.
     * @param string $driver The driver that requires it.
     * @return self
     */
    public static function extensionNotLoaded(string $extension, string $driver): self
    {
        return new self(
            "PHP extension '{$extension}' is required for cache driver '{$driver}'",
            2,
            null,
            '',
            ['extension' => $extension, 'driver' => $driver]
        );
    }

    /**
     * Creates an exception for connection failure.
     *
     * @param string $driver The cache driver.
     * @param string $error The error message.
     * @return self
     */
    public static function connectionFailed(string $driver, string $error): self
    {
        return new self(
            "Failed to connect to {$driver} cache: {$error}",
            3,
            null,
            '',
            ['driver' => $driver, 'error' => $error]
        );
    }

    /**
     * Creates an exception for directory creation failure.
     *
     * @param string $directory The directory path.
     * @return self
     */
    public static function directoryCreationFailed(string $directory): self
    {
        return new self(
            "Failed to create cache directory: {$directory}",
            4,
            null,
            '',
            ['directory' => $directory]
        );
    }

    /**
     * Creates an exception for write failure.
     *
     * @param string $key The cache key.
     * @param string $error The error message.
     * @return self
     */
    public static function writeFailed(string $key, string $error = ''): self
    {
        $message = "Failed to write cache key: {$key}";
        if ($error) {
            $message .= " - {$error}";
        }

        return new self(
            $message,
            5,
            null,
            '',
            ['key' => $key, 'error' => $error]
        );
    }

    /**
     * Creates an exception for read failure.
     *
     * @param string $key The cache key.
     * @param string $error The error message.
     * @return self
     */
    public static function readFailed(string $key, string $error = ''): self
    {
        $message = "Failed to read cache key: {$key}";
        if ($error) {
            $message .= " - {$error}";
        }

        return new self(
            $message,
            6,
            null,
            '',
            ['key' => $key, 'error' => $error]
        );
    }
}
