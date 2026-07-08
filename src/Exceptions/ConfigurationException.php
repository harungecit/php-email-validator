<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Exceptions;

/**
 * Class ConfigurationException
 *
 * Exception thrown when configuration is invalid.
 *
 * @package HarunGecit\EmailValidator\Exceptions
 */
class ConfigurationException extends EmailValidatorException
{
    /**
     * Creates an exception for missing configuration file.
     *
     * @param string $path The path to the missing file.
     * @return self
     */
    public static function fileNotFound(string $path): self
    {
        return new self(
            "Configuration file not found: {$path}",
            1,
            null,
            '',
            ['path' => $path]
        );
    }

    /**
     * Creates an exception for unsupported configuration format.
     *
     * @param string $format The unsupported format.
     * @return self
     */
    public static function unsupportedFormat(string $format): self
    {
        return new self(
            "Unsupported configuration format: {$format}",
            2,
            null,
            '',
            ['format' => $format]
        );
    }

    /**
     * Creates an exception for invalid configuration value.
     *
     * @param string $key The configuration key.
     * @param mixed $value The invalid value.
     * @param string $expected The expected type or format.
     * @return self
     */
    public static function invalidValue(string $key, mixed $value, string $expected): self
    {
        $valueType = is_object($value) ? get_class($value) : gettype($value);
        return new self(
            "Invalid configuration value for '{$key}': expected {$expected}, got {$valueType}",
            3,
            null,
            '',
            ['key' => $key, 'value' => $value, 'expected' => $expected]
        );
    }

    /**
     * Creates an exception for missing required configuration.
     *
     * @param string $key The missing configuration key.
     * @return self
     */
    public static function missingRequired(string $key): self
    {
        return new self(
            "Missing required configuration: {$key}",
            4,
            null,
            '',
            ['key' => $key]
        );
    }

    /**
     * Creates an exception for invalid cache driver.
     *
     * @param string $driver The invalid driver name.
     * @return self
     */
    public static function invalidCacheDriver(string $driver): self
    {
        return new self(
            "Invalid cache driver: {$driver}. Supported: memory, file, redis, memcached, null, psr16",
            5,
            null,
            '',
            ['driver' => $driver]
        );
    }
}
