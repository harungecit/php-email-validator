<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Config;

use HarunGecit\EmailValidator\Exceptions\ConfigurationException;
use Psr\Log\LoggerInterface;

/**
 * Class Configuration
 *
 * Central configuration class for the email validator.
 * Supports fluent interface for easy configuration.
 *
 * @package HarunGecit\EmailValidator\Config
 */
class Configuration
{
    // Validation checks
    protected bool $checkFormat = true;
    protected bool $checkMx = true;
    protected bool $checkDisposable = true;
    protected bool $checkRoleBased = false;
    protected bool $checkSmtp = false;
    protected bool $checkTypos = false;
    protected bool $checkSubaddress = false;
    protected bool $checkCatchAll = false;

    // Cache settings
    protected string $cacheDriver = 'memory';
    protected int $cacheTtl = 3600;
    protected string $cachePrefix = 'email_validator_';
    /** @var array<string, mixed> */
    protected array $cacheOptions = [];

    // Rate limiting
    protected bool $rateLimitEnabled = false;
    protected int $rateLimitMaxAttempts = 100;
    protected int $rateLimitDecaySeconds = 60;

    // Custom list paths
    protected ?string $blocklistPath = null;
    protected ?string $allowlistPath = null;
    protected ?string $roleBasedPath = null;

    // Role-based prefixes
    /** @var array<string> */
    protected array $roleBasedPrefixes = [
        'admin', 'info', 'support', 'sales', 'contact', 'noreply',
        'no-reply', 'help', 'webmaster', 'postmaster', 'hostmaster',
        'abuse', 'billing', 'marketing', 'hr', 'jobs', 'careers',
        'press', 'media', 'office', 'team', 'hello', 'enquiries',
        'enquiry', 'feedback', 'newsletter', 'subscribe', 'unsubscribe'
    ];

    // Typo correction domains
    /** @var array<string> */
    protected array $commonDomains = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
        'icloud.com', 'aol.com', 'protonmail.com', 'mail.com',
        'yandex.com', 'zoho.com', 'gmx.com', 'fastmail.com',
        'live.com', 'msn.com', 'me.com', 'mac.com'
    ];

    // Known typos mapping
    /** @var array<string, string> */
    protected array $typoMappings = [
        'gmial.com' => 'gmail.com',
        'gmal.com' => 'gmail.com',
        'gmali.com' => 'gmail.com',
        'gmaill.com' => 'gmail.com',
        'gmail.co' => 'gmail.com',
        'gmail.cm' => 'gmail.com',
        'gamil.com' => 'gmail.com',
        'gnail.com' => 'gmail.com',
        'gmai.com' => 'gmail.com',
        'yaho.com' => 'yahoo.com',
        'yahooo.com' => 'yahoo.com',
        'yhoo.com' => 'yahoo.com',
        'yahoo.co' => 'yahoo.com',
        'yhaoo.com' => 'yahoo.com',
        'hotmal.com' => 'hotmail.com',
        'hotmial.com' => 'hotmail.com',
        'hotmil.com' => 'hotmail.com',
        'hotamil.com' => 'hotmail.com',
        'hotmail.co' => 'hotmail.com',
        'outlok.com' => 'outlook.com',
        'outloo.com' => 'outlook.com',
        'outlook.co' => 'outlook.com',
        'outloook.com' => 'outlook.com',
    ];

    // SMTP settings
    protected int $smtpTimeout = 10;
    protected ?string $smtpFromEmail = null;
    protected ?string $smtpFromDomain = null;

    // DNS settings
    protected int $dnsTimeout = 5;

    // Logger (PSR-3)
    protected ?LoggerInterface $logger = null;

    // ==================== Validation Check Methods ====================

    public function isFormatCheckEnabled(): bool
    {
        return $this->checkFormat;
    }

    public function enableFormatCheck(bool $enable = true): self
    {
        $this->checkFormat = $enable;
        return $this;
    }

    public function isMxCheckEnabled(): bool
    {
        return $this->checkMx;
    }

    public function enableMxCheck(bool $enable = true): self
    {
        $this->checkMx = $enable;
        return $this;
    }

    public function isDisposableCheckEnabled(): bool
    {
        return $this->checkDisposable;
    }

    public function enableDisposableCheck(bool $enable = true): self
    {
        $this->checkDisposable = $enable;
        return $this;
    }

    public function isRoleBasedCheckEnabled(): bool
    {
        return $this->checkRoleBased;
    }

    public function enableRoleBasedCheck(bool $enable = true): self
    {
        $this->checkRoleBased = $enable;
        return $this;
    }

    public function isSmtpCheckEnabled(): bool
    {
        return $this->checkSmtp;
    }

    public function enableSmtpCheck(bool $enable = true): self
    {
        $this->checkSmtp = $enable;
        return $this;
    }

    public function isTypoSuggestionEnabled(): bool
    {
        return $this->checkTypos;
    }

    public function enableTypoSuggestion(bool $enable = true): self
    {
        $this->checkTypos = $enable;
        return $this;
    }

    public function isSubaddressCheckEnabled(): bool
    {
        return $this->checkSubaddress;
    }

    public function enableSubaddressCheck(bool $enable = true): self
    {
        $this->checkSubaddress = $enable;
        return $this;
    }

    public function isCatchAllCheckEnabled(): bool
    {
        return $this->checkCatchAll;
    }

    public function enableCatchAllCheck(bool $enable = true): self
    {
        $this->checkCatchAll = $enable;
        return $this;
    }

    // ==================== Cache Methods ====================

    public function getCacheDriver(): string
    {
        return $this->cacheDriver;
    }

    public function setCacheDriver(string $driver): self
    {
        $validDrivers = ['memory', 'file', 'redis', 'memcached', 'null', 'psr16'];
        if (!in_array($driver, $validDrivers, true)) {
            throw ConfigurationException::invalidCacheDriver($driver);
        }
        $this->cacheDriver = $driver;
        return $this;
    }

    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    public function setCacheTtl(int $seconds): self
    {
        $this->cacheTtl = max(0, $seconds);
        return $this;
    }

    public function getCachePrefix(): string
    {
        return $this->cachePrefix;
    }

    public function setCachePrefix(string $prefix): self
    {
        $this->cachePrefix = $prefix;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCacheOptions(): array
    {
        return $this->cacheOptions;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setCacheOptions(array $options): self
    {
        $this->cacheOptions = $options;
        return $this;
    }

    // ==================== Rate Limiting Methods ====================

    public function isRateLimitEnabled(): bool
    {
        return $this->rateLimitEnabled;
    }

    public function enableRateLimiting(bool $enable = true): self
    {
        $this->rateLimitEnabled = $enable;
        return $this;
    }

    public function getRateLimitMaxAttempts(): int
    {
        return $this->rateLimitMaxAttempts;
    }

    public function setRateLimitMaxAttempts(int $attempts): self
    {
        $this->rateLimitMaxAttempts = max(1, $attempts);
        return $this;
    }

    public function getRateLimitDecaySeconds(): int
    {
        return $this->rateLimitDecaySeconds;
    }

    public function setRateLimitDecaySeconds(int $seconds): self
    {
        $this->rateLimitDecaySeconds = max(1, $seconds);
        return $this;
    }

    // ==================== List Path Methods ====================

    public function getBlocklistPath(): ?string
    {
        return $this->blocklistPath;
    }

    public function setBlocklistPath(?string $path): self
    {
        $this->blocklistPath = $path;
        return $this;
    }

    public function getAllowlistPath(): ?string
    {
        return $this->allowlistPath;
    }

    public function setAllowlistPath(?string $path): self
    {
        $this->allowlistPath = $path;
        return $this;
    }

    public function getRoleBasedPath(): ?string
    {
        return $this->roleBasedPath;
    }

    public function setRoleBasedPath(?string $path): self
    {
        $this->roleBasedPath = $path;
        return $this;
    }

    // ==================== Role-Based Methods ====================

    /**
     * @return array<string>
     */
    public function getRoleBasedPrefixes(): array
    {
        return $this->roleBasedPrefixes;
    }

    /**
     * @param array<string> $prefixes
     */
    public function setRoleBasedPrefixes(array $prefixes): self
    {
        $this->roleBasedPrefixes = array_map('strtolower', array_map('trim', $prefixes));
        return $this;
    }

    public function addRoleBasedPrefix(string $prefix): self
    {
        $prefix = strtolower(trim($prefix));
        if (!in_array($prefix, $this->roleBasedPrefixes, true)) {
            $this->roleBasedPrefixes[] = $prefix;
        }
        return $this;
    }

    public function removeRoleBasedPrefix(string $prefix): self
    {
        $prefix = strtolower(trim($prefix));
        $key = array_search($prefix, $this->roleBasedPrefixes, true);
        if ($key !== false) {
            unset($this->roleBasedPrefixes[$key]);
            $this->roleBasedPrefixes = array_values($this->roleBasedPrefixes);
        }
        return $this;
    }

    // ==================== Typo Suggestion Methods ====================

    /**
     * @return array<string>
     */
    public function getCommonDomains(): array
    {
        return $this->commonDomains;
    }

    /**
     * @param array<string> $domains
     */
    public function setCommonDomains(array $domains): self
    {
        $this->commonDomains = array_map('strtolower', array_map('trim', $domains));
        return $this;
    }

    public function addCommonDomain(string $domain): self
    {
        $domain = strtolower(trim($domain));
        if (!in_array($domain, $this->commonDomains, true)) {
            $this->commonDomains[] = $domain;
        }
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getTypoMappings(): array
    {
        return $this->typoMappings;
    }

    /**
     * @param array<string, string> $mappings
     */
    public function setTypoMappings(array $mappings): self
    {
        $this->typoMappings = [];
        foreach ($mappings as $typo => $correct) {
            $this->typoMappings[strtolower($typo)] = strtolower($correct);
        }
        return $this;
    }

    public function addTypoMapping(string $typo, string $correct): self
    {
        $this->typoMappings[strtolower($typo)] = strtolower($correct);
        return $this;
    }

    // ==================== SMTP Methods ====================

    public function getSmtpTimeout(): int
    {
        return $this->smtpTimeout;
    }

    public function setSmtpTimeout(int $seconds): self
    {
        $this->smtpTimeout = max(1, $seconds);
        return $this;
    }

    public function getSmtpFromEmail(): ?string
    {
        return $this->smtpFromEmail;
    }

    public function setSmtpFromEmail(?string $email): self
    {
        $this->smtpFromEmail = $email;
        return $this;
    }

    public function getSmtpFromDomain(): ?string
    {
        return $this->smtpFromDomain;
    }

    public function setSmtpFromDomain(?string $domain): self
    {
        $this->smtpFromDomain = $domain;
        return $this;
    }

    // ==================== DNS Methods ====================

    public function getDnsTimeout(): int
    {
        return $this->dnsTimeout;
    }

    public function setDnsTimeout(int $seconds): self
    {
        $this->dnsTimeout = max(1, $seconds);
        return $this;
    }

    // ==================== Logger Methods ====================

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function setLogger(?LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    // ==================== Factory Methods ====================

    /**
     * Creates a Configuration instance from an array.
     *
     * @param array<string, mixed> $config
     * @return self
     */
    public static function fromArray(array $config): self
    {
        $instance = new self();

        // Validation checks
        if (isset($config['checks'])) {
            $checks = $config['checks'];
            if (isset($checks['format'])) {
                $instance->enableFormatCheck((bool) $checks['format']);
            }
            if (isset($checks['mx'])) {
                $instance->enableMxCheck((bool) $checks['mx']);
            }
            if (isset($checks['disposable'])) {
                $instance->enableDisposableCheck((bool) $checks['disposable']);
            }
            if (isset($checks['role_based'])) {
                $instance->enableRoleBasedCheck((bool) $checks['role_based']);
            }
            if (isset($checks['smtp'])) {
                $instance->enableSmtpCheck((bool) $checks['smtp']);
            }
            if (isset($checks['typo_suggestion'])) {
                $instance->enableTypoSuggestion((bool) $checks['typo_suggestion']);
            }
            if (isset($checks['subaddress'])) {
                $instance->enableSubaddressCheck((bool) $checks['subaddress']);
            }
            if (isset($checks['catch_all'])) {
                $instance->enableCatchAllCheck((bool) $checks['catch_all']);
            }
        }

        // Cache settings
        if (isset($config['cache'])) {
            $cache = $config['cache'];
            if (isset($cache['driver'])) {
                $instance->setCacheDriver((string) $cache['driver']);
            }
            if (isset($cache['ttl'])) {
                $instance->setCacheTtl((int) $cache['ttl']);
            }
            if (isset($cache['prefix'])) {
                $instance->setCachePrefix((string) $cache['prefix']);
            }
            if (isset($cache['options']) && is_array($cache['options'])) {
                $instance->setCacheOptions($cache['options']);
            }
        }

        // Rate limiting
        if (isset($config['rate_limit'])) {
            $rateLimit = $config['rate_limit'];
            if (isset($rateLimit['enabled'])) {
                $instance->enableRateLimiting((bool) $rateLimit['enabled']);
            }
            if (isset($rateLimit['max_attempts'])) {
                $instance->setRateLimitMaxAttempts((int) $rateLimit['max_attempts']);
            }
            if (isset($rateLimit['decay_seconds'])) {
                $instance->setRateLimitDecaySeconds((int) $rateLimit['decay_seconds']);
            }
        }

        // Custom list paths
        if (isset($config['lists'])) {
            $lists = $config['lists'];
            if (isset($lists['blocklist_path'])) {
                $instance->setBlocklistPath($lists['blocklist_path']);
            }
            if (isset($lists['allowlist_path'])) {
                $instance->setAllowlistPath($lists['allowlist_path']);
            }
            if (isset($lists['role_based_path'])) {
                $instance->setRoleBasedPath($lists['role_based_path']);
            }
        }

        // Role-based settings
        if (isset($config['role_based'])) {
            $roleBased = $config['role_based'];
            if (isset($roleBased['prefixes']) && is_array($roleBased['prefixes'])) {
                $instance->setRoleBasedPrefixes($roleBased['prefixes']);
            }
        }

        // Typo settings
        if (isset($config['typo'])) {
            $typo = $config['typo'];
            if (isset($typo['common_domains']) && is_array($typo['common_domains'])) {
                $instance->setCommonDomains($typo['common_domains']);
            }
            if (isset($typo['mappings']) && is_array($typo['mappings'])) {
                $instance->setTypoMappings($typo['mappings']);
            }
        }

        // SMTP settings
        if (isset($config['smtp'])) {
            $smtp = $config['smtp'];
            if (isset($smtp['timeout'])) {
                $instance->setSmtpTimeout((int) $smtp['timeout']);
            }
            if (isset($smtp['from_email'])) {
                $instance->setSmtpFromEmail($smtp['from_email']);
            }
            if (isset($smtp['from_domain'])) {
                $instance->setSmtpFromDomain($smtp['from_domain']);
            }
        }

        // DNS settings
        if (isset($config['dns'])) {
            $dns = $config['dns'];
            if (isset($dns['timeout'])) {
                $instance->setDnsTimeout((int) $dns['timeout']);
            }
        }

        return $instance;
    }

    /**
     * Creates a Configuration instance from a file.
     *
     * @param string $path
     * @return self
     * @throws ConfigurationException
     */
    public static function fromFile(string $path): self
    {
        return ConfigLoader::load($path);
    }

    /**
     * Converts the configuration to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'checks' => [
                'format' => $this->checkFormat,
                'mx' => $this->checkMx,
                'disposable' => $this->checkDisposable,
                'role_based' => $this->checkRoleBased,
                'smtp' => $this->checkSmtp,
                'typo_suggestion' => $this->checkTypos,
                'subaddress' => $this->checkSubaddress,
                'catch_all' => $this->checkCatchAll,
            ],
            'cache' => [
                'driver' => $this->cacheDriver,
                'ttl' => $this->cacheTtl,
                'prefix' => $this->cachePrefix,
                'options' => $this->cacheOptions,
            ],
            'rate_limit' => [
                'enabled' => $this->rateLimitEnabled,
                'max_attempts' => $this->rateLimitMaxAttempts,
                'decay_seconds' => $this->rateLimitDecaySeconds,
            ],
            'lists' => [
                'blocklist_path' => $this->blocklistPath,
                'allowlist_path' => $this->allowlistPath,
                'role_based_path' => $this->roleBasedPath,
            ],
            'role_based' => [
                'prefixes' => $this->roleBasedPrefixes,
            ],
            'typo' => [
                'common_domains' => $this->commonDomains,
                'mappings' => $this->typoMappings,
            ],
            'smtp' => [
                'timeout' => $this->smtpTimeout,
                'from_email' => $this->smtpFromEmail,
                'from_domain' => $this->smtpFromDomain,
            ],
            'dns' => [
                'timeout' => $this->dnsTimeout,
            ],
        ];
    }
}
