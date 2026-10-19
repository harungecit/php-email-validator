<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\CodeIgniter\Config;

use CodeIgniter\Config\BaseConfig;
use HarunGecit\EmailValidator\Config\Configuration;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class EmailValidator
 *
 * CodeIgniter 4 configuration for the `emailValidator` service. The
 * properties mirror the keys of Configuration::fromArray() (camelCase
 * property names, snake_case keys inside the arrays).
 *
 * To customise it, create `app/Config/EmailValidator.php`:
 *
 * ```php
 * namespace Config;
 *
 * use HarunGecit\EmailValidator\Framework\CodeIgniter\Config\EmailValidator as BaseEmailValidator;
 *
 * class EmailValidator extends BaseEmailValidator
 * {
 *     public array $checks = [
 *         'format' => true, 'mx' => true, 'disposable' => true, 'role_based' => true,
 *         'smtp' => false, 'typo_suggestion' => false, 'subaddress' => false,
 *         'catch_all' => false, 'spoof' => false,
 *     ];
 * }
 * ```
 *
 * As with every BaseConfig class, values can also be overridden from `.env`,
 * e.g. `emailvalidator.checks.mx = false`.
 *
 * A value of `null` means "use the library default".
 *
 * Only loaded inside CodeIgniter (it extends a CodeIgniter class).
 *
 * @package HarunGecit\EmailValidator\Framework\CodeIgniter\Config
 */
class EmailValidator extends BaseConfig
{
    /**
     * Checks run by EmailValidator::validate() (and the `verified_email` rule).
     *
     * @var array<string, bool>
     */
    public array $checks = [
        'format' => true,
        'mx' => true,
        'disposable' => true,
        'role_based' => false,
        'smtp' => false,
        'typo_suggestion' => false,
        'subaddress' => false,
        'catch_all' => false,
        'spoof' => false,
    ];

    /**
     * Format validation mode: filter, filter_unicode, rfc or strict
     * (rfc/strict require egulias/email-validator).
     *
     * @var array<string, string>
     */
    public array $format = [
        'mode' => 'filter',
    ];

    /**
     * Cache for MX / SMTP lookups (driver: memory, file, redis, memcached, null, psr16).
     *
     * @var array<string, mixed>
     */
    public array $cache = [
        'driver' => 'memory',
        'ttl' => 3600,
        'prefix' => 'email_validator_',
        'options' => [],
    ];

    /**
     * Rate limiting of validate() calls.
     *
     * @var array<string, mixed>
     */
    public array $rateLimit = [
        'enabled' => false,
        'max_attempts' => 100,
        'decay_seconds' => 60,
    ];

    /**
     * Blocklists / allowlists. `synced_blocklist_path` is the file written by
     * `vendor/bin/email-validator sync --path=...` (e.g. WRITEPATH . 'email-validator/synced.conf').
     *
     * @var array<string, mixed>
     */
    public array $lists = [
        'blocklist_path' => null,
        'allowlist_path' => null,
        'role_based_path' => null,
        'custom_blocklist' => [],
        'custom_allowlist' => [],
        'custom_blocklist_paths' => [],
        'custom_allowlist_paths' => [],
        'synced_blocklist_path' => null,
        'block_subdomains' => true,
    ];

    /**
     * Remote list synchronization (used by ListSynchronizer).
     *
     * @var array<string, mixed>
     */
    public array $sync = [
        'urls' => [Configuration::DEFAULT_SYNC_URL],
        'timeout' => 30,
    ];

    /**
     * Role-based prefixes; null keeps the library defaults.
     *
     * @var array<string, array<string>|null>
     */
    public array $roleBased = [
        'prefixes' => null,
    ];

    /**
     * Typo suggestion domains and mappings; null keeps the library defaults.
     *
     * @var array<string, array<string>|array<string, string>|null>
     */
    public array $typo = [
        'common_domains' => null,
        'mappings' => null,
    ];

    /**
     * SMTP verification settings.
     *
     * @var array<string, int|string|null>
     */
    public array $smtp = [
        'timeout' => 10,
        'from_email' => null,
        'from_domain' => null,
    ];

    /**
     * DNS settings.
     *
     * @var array<string, int>
     */
    public array $dns = [
        'timeout' => 5,
    ];

    /**
     * Log validation results through CodeIgniter's logger (`service('logger')`).
     *
     * @var bool
     */
    public bool $logging = false;

    /**
     * Returns the application's `Config\EmailValidator` (when it extends this
     * class), or this class with its defaults.
     *
     * @return self
     */
    public static function resolve(): self
    {
        $config = function_exists('config') ? config('EmailValidator') : null;

        return $config instanceof self ? $config : new self();
    }

    /**
     * Converts the properties to the array accepted by Configuration::fromArray().
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'checks' => $this->checks,
            'format' => $this->format,
            'cache' => $this->cache,
            'rate_limit' => $this->rateLimit,
            'lists' => $this->lists,
            'sync' => $this->sync,
            'role_based' => $this->roleBased,
            'typo' => $this->typo,
            'smtp' => $this->smtp,
            'dns' => $this->dns,
        ];
    }

    /**
     * Builds the library Configuration (attaching CodeIgniter's logger when
     * `$logging` is enabled).
     *
     * @return Configuration
     */
    public function toConfiguration(): Configuration
    {
        $configuration = Configuration::fromArray($this->toArray());

        if ($this->logging && function_exists('service')) {
            try {
                $logger = service('logger');
            } catch (Throwable $e) {
                $logger = null;
            }

            if ($logger instanceof LoggerInterface) {
                $configuration->setLogger($logger);
            }
        }

        return $configuration;
    }
}
