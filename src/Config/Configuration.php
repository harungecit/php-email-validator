<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Config;

use HarunGecit\EmailValidator\Exceptions\ConfigurationException;
use HarunGecit\EmailValidator\Support\Domain;
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
    protected bool $checkSpoof = false;

    /**
     * Supported format validation modes.
     *
     * - filter:         PHP's FILTER_VALIDATE_EMAIL (default, ASCII only)
     * - filter_unicode: FILTER_VALIDATE_EMAIL with FILTER_FLAG_EMAIL_UNICODE (internationalized local parts)
     * - rfc:            RFC 5322 parsing via egulias/email-validator (RFCValidation)
     * - strict:         RFC 5322 without warnings via egulias/email-validator (NoRFCWarningsValidation)
     */
    public const FORMAT_MODES = ['filter', 'filter_unicode', 'rfc', 'strict'];

    /**
     * Default remote list used by the synchronizer: this package's own
     * blocklist on the main branch, already merged from all upstream sources
     * and filtered for false positives. It is refreshed daily.
     */
    public const DEFAULT_SYNC_URL = 'https://raw.githubusercontent.com/harungecit/php-email-validator/main/data/blocklist.conf';

    // Format validation
    protected string $formatMode = 'filter';

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

    // Additional lists (merged with the bundled/configured ones)
    /** @var array<string> */
    protected array $customBlocklist = [];
    /** @var array<string> */
    protected array $customAllowlist = [];
    /** @var array<string> */
    protected array $customBlocklistPaths = [];
    /** @var array<string> */
    protected array $customAllowlistPaths = [];
    protected ?string $syncedBlocklistPath = null;
    protected bool $blockSubdomains = true;

    // Remote list synchronization
    /** @var array<string> */
    protected array $syncUrls = [self::DEFAULT_SYNC_URL];
    protected int $syncTimeout = 30;

    // Role-based prefixes
    /** @var array<string> */
    protected array $roleBasedPrefixes = [
        'admin', 'administrator', 'info', 'support', 'sales', 'contact',
        'noreply', 'no-reply', 'donotreply', 'do-not-reply',
        'help', 'webmaster', 'postmaster', 'hostmaster',
        'abuse', 'billing', 'marketing', 'hr', 'jobs', 'careers',
        'press', 'media', 'office', 'team', 'hello', 'enquiries',
        'enquiry', 'feedback', 'newsletter', 'subscribe', 'unsubscribe',
        'security', 'privacy', 'legal', 'compliance', 'notifications'
    ];

    // Typo correction domains
    /** @var array<string> */
    protected array $commonDomains = [
        'gmail.com', 'googlemail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
        'icloud.com', 'aol.com', 'protonmail.com', 'proton.me', 'pm.me',
        'mail.com', 'yandex.com', 'yandex.ru', 'zoho.com', 'zohomail.com',
        'gmx.com', 'gmx.de', 'gmx.net', 'fastmail.com',
        'live.com', 'msn.com', 'me.com', 'mac.com',
        'hey.com', 'tuta.com', 'tutanota.com',
        'yahoo.co.uk', 'hotmail.co.uk', 'btinternet.com',
        'yahoo.fr', 'orange.fr', 'free.fr',
        'web.de', 't-online.de',
        'yahoo.de', 'yahoo.es', 'yahoo.it',
        'hotmail.fr', 'hotmail.de', 'hotmail.es', 'hotmail.it',
        'outlook.fr', 'outlook.de', 'outlook.es', 'outlook.it'
    ];

    // Known typos mapping
    /** @var array<string, string> */
    protected array $typoMappings = [
        // Gmail typos
        'gmial.com' => 'gmail.com',
        'gmal.com' => 'gmail.com',
        'gmali.com' => 'gmail.com',
        'gmaill.com' => 'gmail.com',
        'gmail.co' => 'gmail.com',
        'gmail.cm' => 'gmail.com',
        'gmail.om' => 'gmail.com',
        'gmail.con' => 'gmail.com',
        'gamil.com' => 'gmail.com',
        'gnail.com' => 'gmail.com',
        'gmai.com' => 'gmail.com',
        'gmailc.om' => 'gmail.com',
        'gmaul.com' => 'gmail.com',
        'gmsil.com' => 'gmail.com',
        'gemail.com' => 'gmail.com',
        'gimail.com' => 'gmail.com',
        // Yahoo typos
        'yaho.com' => 'yahoo.com',
        'yahooo.com' => 'yahoo.com',
        'yhoo.com' => 'yahoo.com',
        'yahoo.co' => 'yahoo.com',
        'yahoo.con' => 'yahoo.com',
        'yhaoo.com' => 'yahoo.com',
        'yaoo.com' => 'yahoo.com',
        'yajoo.com' => 'yahoo.com',
        'uahoo.com' => 'yahoo.com',
        // Hotmail typos
        'hotmal.com' => 'hotmail.com',
        'hotmial.com' => 'hotmail.com',
        'hotmil.com' => 'hotmail.com',
        'hotamil.com' => 'hotmail.com',
        'hotmail.co' => 'hotmail.com',
        'hotmail.con' => 'hotmail.com',
        'hotmain.com' => 'hotmail.com',
        'hotmaol.com' => 'hotmail.com',
        'hoymail.com' => 'hotmail.com',
        'jotmail.com' => 'hotmail.com',
        // Outlook typos
        'outlok.com' => 'outlook.com',
        'outloo.com' => 'outlook.com',
        'outlook.co' => 'outlook.com',
        'outlook.con' => 'outlook.com',
        'outloook.com' => 'outlook.com',
        'outlool.com' => 'outlook.com',
        'oitlook.com' => 'outlook.com',
        'putlook.com' => 'outlook.com',
        // iCloud typos
        'icoud.com' => 'icloud.com',
        'iclod.com' => 'icloud.com',
        'icloud.co' => 'icloud.com',
        'icloud.con' => 'icloud.com',
        // AOL typos
        'aol.co' => 'aol.com',
        'aol.con' => 'aol.com',
        'alo.com' => 'aol.com',
        'aoll.com' => 'aol.com',
        // Proton typos
        'protonmal.com' => 'protonmail.com',
        'protonmai.com' => 'protonmail.com',
        'protonmail.co' => 'protonmail.com',
        'protonmail.con' => 'protonmail.com',
        'protonmaill.com' => 'protonmail.com',
        'porton.me' => 'proton.me',
        'protom.me' => 'proton.me',
        // Live typos
        'live.co' => 'live.com',
        'live.con' => 'live.com',
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

    public function isSpoofCheckEnabled(): bool
    {
        return $this->checkSpoof;
    }

    /**
     * Enables detection of visually deceptive (homograph / mixed-script)
     * addresses. Requires ext-intl; skipped with a warning when unavailable.
     */
    public function enableSpoofCheck(bool $enable = true): self
    {
        $this->checkSpoof = $enable;
        return $this;
    }

    // ==================== Format Mode Methods ====================

    public function getFormatMode(): string
    {
        return $this->formatMode;
    }

    /**
     * Sets how the email format is validated (see FORMAT_MODES).
     *
     * @throws ConfigurationException If the mode is not supported.
     */
    public function setFormatMode(string $mode): self
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, self::FORMAT_MODES, true)) {
            throw ConfigurationException::invalidValue('format.mode', $mode, implode('|', self::FORMAT_MODES));
        }
        $this->formatMode = $mode;
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

    // ==================== Additional List Methods ====================

    /**
     * @return array<string>
     */
    public function getCustomBlocklist(): array
    {
        return $this->customBlocklist;
    }

    /**
     * Domains to block in addition to the bundled/configured blocklist.
     *
     * @param array<string> $domains
     */
    public function setCustomBlocklist(array $domains): self
    {
        $this->customBlocklist = self::normalizeDomains($domains);
        return $this;
    }

    /**
     * @return array<string>
     */
    public function getCustomAllowlist(): array
    {
        return $this->customAllowlist;
    }

    /**
     * Domains to always allow, in addition to the bundled/configured allowlist.
     *
     * @param array<string> $domains
     */
    public function setCustomAllowlist(array $domains): self
    {
        $this->customAllowlist = self::normalizeDomains($domains);
        return $this;
    }

    /**
     * @return array<string>
     */
    public function getCustomBlocklistPaths(): array
    {
        return $this->customBlocklistPaths;
    }

    /**
     * Extra blocklist files or directories (*.conf, *.txt, *.list, *.json),
     * merged with the bundled/configured blocklist.
     *
     * @param array<string>|string $paths
     */
    public function setCustomBlocklistPaths(array|string $paths): self
    {
        $this->customBlocklistPaths = self::normalizePaths($paths);
        return $this;
    }

    /**
     * @return array<string>
     */
    public function getCustomAllowlistPaths(): array
    {
        return $this->customAllowlistPaths;
    }

    /**
     * Extra allowlist files or directories, merged with the bundled/configured allowlist.
     *
     * @param array<string>|string $paths
     */
    public function setCustomAllowlistPaths(array|string $paths): self
    {
        $this->customAllowlistPaths = self::normalizePaths($paths);
        return $this;
    }

    public function getSyncedBlocklistPath(): ?string
    {
        return $this->syncedBlocklistPath;
    }

    /**
     * File where the list synchronizer stores downloaded domains. When set and
     * the file exists, its domains are blocked in addition to the bundled list.
     */
    public function setSyncedBlocklistPath(?string $path): self
    {
        $this->syncedBlocklistPath = $path === '' ? null : $path;
        return $this;
    }

    public function isSubdomainBlockingEnabled(): bool
    {
        return $this->blockSubdomains;
    }

    /**
     * When enabled (default), a listed domain also matches its subdomains:
     * "mailinator.com" blocks "user@eu.mailinator.com", and an allowlisted
     * domain allows its subdomains.
     */
    public function enableSubdomainBlocking(bool $enable = true): self
    {
        $this->blockSubdomains = $enable;
        return $this;
    }

    // ==================== Sync Methods ====================

    /**
     * @return array<string>
     */
    public function getSyncUrls(): array
    {
        return $this->syncUrls;
    }

    /**
     * Remote lists (plain text or JSON) downloaded by the synchronizer.
     * Only http(s) URLs are accepted; anything else is ignored.
     *
     * @param array<string>|string $urls
     */
    public function setSyncUrls(array|string $urls): self
    {
        $valid = [];
        foreach ((array) $urls as $url) {
            $url = trim((string) $url);
            if (filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $url) === 1) {
                $valid[$url] = true;
            }
        }
        $this->syncUrls = array_keys($valid);
        return $this;
    }

    public function getSyncTimeout(): int
    {
        return $this->syncTimeout;
    }

    public function setSyncTimeout(int $seconds): self
    {
        $this->syncTimeout = max(1, $seconds);
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
            if (isset($checks['spoof'])) {
                $instance->enableSpoofCheck((bool) $checks['spoof']);
            }
        }

        // Format settings
        if (isset($config['format']['mode'])) {
            $instance->setFormatMode((string) $config['format']['mode']);
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
            if (isset($lists['custom_blocklist']) && is_array($lists['custom_blocklist'])) {
                $instance->setCustomBlocklist($lists['custom_blocklist']);
            }
            if (isset($lists['custom_allowlist']) && is_array($lists['custom_allowlist'])) {
                $instance->setCustomAllowlist($lists['custom_allowlist']);
            }
            if (isset($lists['custom_blocklist_paths'])) {
                $instance->setCustomBlocklistPaths($lists['custom_blocklist_paths']);
            }
            if (isset($lists['custom_allowlist_paths'])) {
                $instance->setCustomAllowlistPaths($lists['custom_allowlist_paths']);
            }
            if (isset($lists['synced_blocklist_path'])) {
                $instance->setSyncedBlocklistPath((string) $lists['synced_blocklist_path']);
            }
            if (isset($lists['block_subdomains'])) {
                $instance->enableSubdomainBlocking((bool) $lists['block_subdomains']);
            }
        }

        // Remote list synchronization
        if (isset($config['sync'])) {
            $sync = $config['sync'];
            if (isset($sync['urls'])) {
                $instance->setSyncUrls($sync['urls']);
            }
            if (isset($sync['timeout'])) {
                $instance->setSyncTimeout((int) $sync['timeout']);
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
                'spoof' => $this->checkSpoof,
            ],
            'format' => [
                'mode' => $this->formatMode,
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
                'custom_blocklist' => $this->customBlocklist,
                'custom_allowlist' => $this->customAllowlist,
                'custom_blocklist_paths' => $this->customBlocklistPaths,
                'custom_allowlist_paths' => $this->customAllowlistPaths,
                'synced_blocklist_path' => $this->syncedBlocklistPath,
                'block_subdomains' => $this->blockSubdomains,
            ],
            'sync' => [
                'urls' => $this->syncUrls,
                'timeout' => $this->syncTimeout,
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

    /**
     * Normalizes a list of domains (trim, lowercase, no trailing dot, no duplicates).
     *
     * @param array<mixed> $domains
     * @return array<string>
     */
    private static function normalizeDomains(array $domains): array
    {
        $normalized = [];
        foreach ($domains as $domain) {
            if (!is_string($domain)) {
                continue;
            }
            $domain = Domain::normalize($domain);
            if ($domain !== null) {
                $normalized[$domain] = true;
            }
        }
        return array_keys($normalized);
    }

    /**
     * @param array<mixed>|string $paths
     * @return array<string>
     */
    private static function normalizePaths(array|string $paths): array
    {
        $normalized = [];
        foreach ((array) $paths as $path) {
            if (is_string($path) && trim($path) !== '') {
                $normalized[] = trim($path);
            }
        }
        return array_values(array_unique($normalized));
    }
}
