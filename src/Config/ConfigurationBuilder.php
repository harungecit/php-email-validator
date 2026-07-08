<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Config;

/**
 * Class ConfigurationBuilder
 *
 * Fluent builder for creating Configuration instances with preset configurations.
 *
 * @package HarunGecit\EmailValidator\Config
 */
class ConfigurationBuilder
{
    /**
     * @var Configuration The configuration being built.
     */
    protected Configuration $config;

    /**
     * ConfigurationBuilder constructor.
     */
    public function __construct()
    {
        $this->config = new Configuration();
    }

    /**
     * Creates a new builder instance.
     *
     * @return self
     */
    public static function create(): self
    {
        return new self();
    }

    // ==================== Preset Configurations ====================

    /**
     * Applies strict validation settings.
     * Enables all validation checks for maximum accuracy.
     *
     * @return self
     */
    public function strict(): self
    {
        $this->config
            ->enableFormatCheck(true)
            ->enableMxCheck(true)
            ->enableDisposableCheck(true)
            ->enableRoleBasedCheck(true)
            ->enableSmtpCheck(true)
            ->enableTypoSuggestion(true)
            ->enableSubaddressCheck(true)
            ->enableCatchAllCheck(true);
        return $this;
    }

    /**
     * Applies basic validation settings.
     * Only performs format and disposable checks.
     *
     * @return self
     */
    public function basic(): self
    {
        $this->config
            ->enableFormatCheck(true)
            ->enableMxCheck(false)
            ->enableDisposableCheck(true)
            ->enableRoleBasedCheck(false)
            ->enableSmtpCheck(false)
            ->enableTypoSuggestion(false)
            ->enableSubaddressCheck(false)
            ->enableCatchAllCheck(false);
        return $this;
    }

    /**
     * Applies standard validation settings.
     * Performs format, MX, and disposable checks.
     *
     * @return self
     */
    public function standard(): self
    {
        $this->config
            ->enableFormatCheck(true)
            ->enableMxCheck(true)
            ->enableDisposableCheck(true)
            ->enableRoleBasedCheck(false)
            ->enableSmtpCheck(false)
            ->enableTypoSuggestion(true)
            ->enableSubaddressCheck(false)
            ->enableCatchAllCheck(false);
        return $this;
    }

    /**
     * Applies minimal validation settings.
     * Only performs format validation.
     *
     * @return self
     */
    public function minimal(): self
    {
        $this->config
            ->enableFormatCheck(true)
            ->enableMxCheck(false)
            ->enableDisposableCheck(false)
            ->enableRoleBasedCheck(false)
            ->enableSmtpCheck(false)
            ->enableTypoSuggestion(false)
            ->enableSubaddressCheck(false)
            ->enableCatchAllCheck(false);
        return $this;
    }

    // ==================== Cache Configuration ====================

    /**
     * Configures memory cache.
     *
     * @param int $ttl Cache TTL in seconds.
     * @return self
     */
    public function withMemoryCache(int $ttl = 3600): self
    {
        $this->config
            ->setCacheDriver('memory')
            ->setCacheTtl($ttl);
        return $this;
    }

    /**
     * Configures file cache.
     *
     * @param string $directory Cache directory path.
     * @param int $ttl Cache TTL in seconds.
     * @return self
     */
    public function withFileCache(string $directory, int $ttl = 3600): self
    {
        $this->config
            ->setCacheDriver('file')
            ->setCacheTtl($ttl)
            ->setCacheOptions(['directory' => $directory]);
        return $this;
    }

    /**
     * Configures Redis cache.
     *
     * @param string $host Redis host.
     * @param int $port Redis port.
     * @param string|null $password Redis password.
     * @param int $database Redis database number.
     * @param int $ttl Cache TTL in seconds.
     * @return self
     */
    public function withRedisCache(
        string $host = '127.0.0.1',
        int $port = 6379,
        ?string $password = null,
        int $database = 0,
        int $ttl = 3600
    ): self {
        $options = [
            'host' => $host,
            'port' => $port,
            'database' => $database,
        ];

        if ($password !== null) {
            $options['password'] = $password;
        }

        $this->config
            ->setCacheDriver('redis')
            ->setCacheTtl($ttl)
            ->setCacheOptions($options);
        return $this;
    }

    /**
     * Configures Memcached cache.
     *
     * @param string $host Memcached host.
     * @param int $port Memcached port.
     * @param int $ttl Cache TTL in seconds.
     * @return self
     */
    public function withMemcachedCache(
        string $host = '127.0.0.1',
        int $port = 11211,
        int $ttl = 3600
    ): self {
        $this->config
            ->setCacheDriver('memcached')
            ->setCacheTtl($ttl)
            ->setCacheOptions([
                'host' => $host,
                'port' => $port,
            ]);
        return $this;
    }

    /**
     * Configures PSR-16 cache adapter.
     *
     * @param object $cache PSR-16 compatible cache instance.
     * @param int $ttl Cache TTL in seconds.
     * @return self
     */
    public function withPsr16Cache(object $cache, int $ttl = 3600): self
    {
        $this->config
            ->setCacheDriver('psr16')
            ->setCacheTtl($ttl)
            ->setCacheOptions(['cache' => $cache]);
        return $this;
    }

    /**
     * Disables caching.
     *
     * @return self
     */
    public function withoutCache(): self
    {
        $this->config->setCacheDriver('null');
        return $this;
    }

    /**
     * Generic cache configuration.
     *
     * @param string $driver Cache driver name.
     * @param array<string, mixed> $options Cache options.
     * @param int $ttl Cache TTL in seconds.
     * @return self
     */
    public function withCache(string $driver, array $options = [], int $ttl = 3600): self
    {
        $this->config
            ->setCacheDriver($driver)
            ->setCacheTtl($ttl)
            ->setCacheOptions($options);
        return $this;
    }

    // ==================== Rate Limiting Configuration ====================

    /**
     * Enables rate limiting.
     *
     * @param int $maxAttempts Maximum attempts allowed.
     * @param int $decaySeconds Time window in seconds.
     * @return self
     */
    public function withRateLimiting(int $maxAttempts = 100, int $decaySeconds = 60): self
    {
        $this->config
            ->enableRateLimiting(true)
            ->setRateLimitMaxAttempts($maxAttempts)
            ->setRateLimitDecaySeconds($decaySeconds);
        return $this;
    }

    /**
     * Disables rate limiting.
     *
     * @return self
     */
    public function withoutRateLimiting(): self
    {
        $this->config->enableRateLimiting(false);
        return $this;
    }

    // ==================== Validation Check Configuration ====================

    /**
     * Enables role-based email check.
     *
     * @param array<string>|null $prefixes Custom prefixes to use.
     * @return self
     */
    public function withRoleBasedCheck(?array $prefixes = null): self
    {
        $this->config->enableRoleBasedCheck(true);
        if ($prefixes !== null) {
            $this->config->setRoleBasedPrefixes($prefixes);
        }
        return $this;
    }

    /**
     * Enables typo suggestion.
     *
     * @param array<string>|null $domains Custom common domains.
     * @return self
     */
    public function withTypoSuggestion(?array $domains = null): self
    {
        $this->config->enableTypoSuggestion(true);
        if ($domains !== null) {
            $this->config->setCommonDomains($domains);
        }
        return $this;
    }

    /**
     * Enables SMTP verification.
     *
     * @param int $timeout SMTP timeout in seconds.
     * @param string|null $fromEmail Email address to use in MAIL FROM.
     * @return self
     */
    public function withSmtpCheck(int $timeout = 10, ?string $fromEmail = null): self
    {
        $this->config
            ->enableSmtpCheck(true)
            ->setSmtpTimeout($timeout);

        if ($fromEmail !== null) {
            $this->config->setSmtpFromEmail($fromEmail);
        }
        return $this;
    }

    /**
     * Enables subaddress detection.
     *
     * @return self
     */
    public function withSubaddressCheck(): self
    {
        $this->config->enableSubaddressCheck(true);
        return $this;
    }

    /**
     * Enables catch-all domain detection.
     *
     * @return self
     */
    public function withCatchAllCheck(): self
    {
        $this->config->enableCatchAllCheck(true);
        return $this;
    }

    /**
     * Disables MX record check.
     *
     * @return self
     */
    public function withoutMxCheck(): self
    {
        $this->config->enableMxCheck(false);
        return $this;
    }

    /**
     * Disables disposable email check.
     *
     * @return self
     */
    public function withoutDisposableCheck(): self
    {
        $this->config->enableDisposableCheck(false);
        return $this;
    }

    // ==================== Custom Lists Configuration ====================

    /**
     * Sets custom blocklist path.
     *
     * @param string $path Path to the blocklist file.
     * @return self
     */
    public function withBlocklist(string $path): self
    {
        $this->config->setBlocklistPath($path);
        return $this;
    }

    /**
     * Sets custom allowlist path.
     *
     * @param string $path Path to the allowlist file.
     * @return self
     */
    public function withAllowlist(string $path): self
    {
        $this->config->setAllowlistPath($path);
        return $this;
    }

    // ==================== Direct Access ====================

    /**
     * Gets the underlying configuration instance for direct manipulation.
     *
     * @return Configuration
     */
    public function getConfig(): Configuration
    {
        return $this->config;
    }

    // ==================== Build ====================

    /**
     * Builds and returns the configuration.
     *
     * @return Configuration
     */
    public function build(): Configuration
    {
        return $this->config;
    }
}
