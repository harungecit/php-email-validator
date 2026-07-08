<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Config;

use HarunGecit\EmailValidator\Exceptions\ConfigurationException;

/**
 * Class ConfigLoader
 *
 * Loads configuration from various file formats (PHP, JSON, YAML).
 *
 * @package HarunGecit\EmailValidator\Config
 */
class ConfigLoader
{
    /**
     * Loads configuration from a file.
     *
     * @param string $path Path to the configuration file.
     * @return Configuration
     * @throws ConfigurationException
     */
    public static function load(string $path): Configuration
    {
        if (!file_exists($path)) {
            throw ConfigurationException::fileNotFound($path);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $config = match ($extension) {
            'php' => self::loadPhp($path),
            'json' => self::loadJson($path),
            'yaml', 'yml' => self::loadYaml($path),
            default => throw ConfigurationException::unsupportedFormat($extension)
        };

        return Configuration::fromArray($config);
    }

    /**
     * Loads configuration from a PHP file.
     *
     * @param string $path Path to the PHP configuration file.
     * @return array<string, mixed>
     * @throws ConfigurationException
     */
    private static function loadPhp(string $path): array
    {
        $config = require $path;

        if (!is_array($config)) {
            throw ConfigurationException::invalidValue(
                'config',
                $config,
                'array'
            );
        }

        return $config;
    }

    /**
     * Loads configuration from a JSON file.
     *
     * @param string $path Path to the JSON configuration file.
     * @return array<string, mixed>
     * @throws ConfigurationException
     */
    private static function loadJson(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new ConfigurationException("Failed to read configuration file: {$path}");
        }

        $config = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ConfigurationException(
                "Invalid JSON in configuration file: " . json_last_error_msg()
            );
        }

        if (!is_array($config)) {
            throw ConfigurationException::invalidValue(
                'config',
                $config,
                'array'
            );
        }

        return $config;
    }

    /**
     * Loads configuration from a YAML file.
     *
     * @param string $path Path to the YAML configuration file.
     * @return array<string, mixed>
     * @throws ConfigurationException
     */
    private static function loadYaml(string $path): array
    {
        if (!function_exists('yaml_parse_file')) {
            // Try Symfony YAML component
            if (class_exists('Symfony\Component\Yaml\Yaml')) {
                /** @var array<string, mixed> $config */
                $config = \Symfony\Component\Yaml\Yaml::parseFile($path);
                return $config;
            }

            throw new ConfigurationException(
                "YAML support requires either ext-yaml or symfony/yaml package"
            );
        }

        $config = yaml_parse_file($path);

        if ($config === false) {
            throw new ConfigurationException("Failed to parse YAML configuration file: {$path}");
        }

        if (!is_array($config)) {
            throw ConfigurationException::invalidValue(
                'config',
                $config,
                'array'
            );
        }

        return $config;
    }

    /**
     * Validates the configuration array structure.
     *
     * @param array<string, mixed> $config Configuration array to validate.
     * @return bool
     * @throws ConfigurationException
     */
    public static function validate(array $config): bool
    {
        $validSections = [
            'checks', 'cache', 'rate_limit', 'lists',
            'role_based', 'typo', 'smtp', 'dns'
        ];

        foreach (array_keys($config) as $key) {
            if (!in_array($key, $validSections, true)) {
                // Unknown sections are ignored for forward compatibility
                continue;
            }
        }

        // Validate cache driver if specified
        if (isset($config['cache']['driver'])) {
            $validDrivers = ['memory', 'file', 'redis', 'memcached', 'null', 'psr16'];
            if (!in_array($config['cache']['driver'], $validDrivers, true)) {
                throw ConfigurationException::invalidCacheDriver($config['cache']['driver']);
            }
        }

        return true;
    }

    /**
     * Merges multiple configuration arrays.
     *
     * @param array<string, mixed> ...$configs Configuration arrays to merge.
     * @return array<string, mixed>
     */
    public static function merge(array ...$configs): array
    {
        $result = [];

        foreach ($configs as $config) {
            $result = self::mergeRecursive($result, $config);
        }

        return $result;
    }

    /**
     * Recursively merges two arrays.
     *
     * @param array<string, mixed> $base Base array.
     * @param array<string, mixed> $override Override array.
     * @return array<string, mixed>
     */
    private static function mergeRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::mergeRecursive($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
