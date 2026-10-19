<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator;

use HarunGecit\EmailValidator\Contracts\ValidatorInterface;
use HarunGecit\EmailValidator\Contracts\ConfigurableInterface;
use HarunGecit\EmailValidator\Contracts\CacheInterface;
use HarunGecit\EmailValidator\Contracts\RateLimiterInterface;
use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\Config\ConfigurationBuilder;
use HarunGecit\EmailValidator\Cache\CacheManager;
use HarunGecit\EmailValidator\Cache\MemoryCacheAdapter;
use HarunGecit\EmailValidator\RateLimiter\RateLimiterManager;
use HarunGecit\EmailValidator\RateLimiter\NullRateLimiter;
use HarunGecit\EmailValidator\Validators\RoleBasedValidator;
use HarunGecit\EmailValidator\Validators\TypoSuggester;
use HarunGecit\EmailValidator\Validators\SubaddressDetector;
use HarunGecit\EmailValidator\Validators\SmtpValidator;
use HarunGecit\EmailValidator\Validators\CatchAllDetector;
use HarunGecit\EmailValidator\Result\ValidationResult;
use HarunGecit\EmailValidator\Result\SuggestionResult;
use HarunGecit\EmailValidator\Result\DisposableCheckResult;
use HarunGecit\EmailValidator\Support\Domain;
use HarunGecit\EmailValidator\Exceptions\ConfigurationException;
use HarunGecit\EmailValidator\Exceptions\RateLimitExceededException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class EmailValidator
 *
 * A comprehensive email validation library that provides methods to validate email addresses.
 * It checks the format of the email, determines if the email is from a disposable domain,
 * verifies if the email domain has valid MX records, and supports batch validation.
 *
 * @package HarunGecit\EmailValidator
 * @author Harun Geçit <info@harungecit.com>
 * @link https://github.com/harungecit
 * @license MIT
 * @version 3.2.0
 */
class EmailValidator implements ValidatorInterface, ConfigurableInterface
{
    /**
     * @var Configuration Current configuration.
     */
    private Configuration $config;

    /**
     * @var CacheInterface Cache adapter.
     */
    private CacheInterface $cache;

    /**
     * @var RateLimiterInterface Rate limiter.
     */
    private RateLimiterInterface $rateLimiter;

    /**
     * @var LoggerInterface Logger instance.
     */
    private LoggerInterface $logger;

    /**
     * Lookup index of the base blocklist (bundled or `lists.blocklist_path`).
     *
     * The bundled index can hold 100k+ entries and may be an immutable
     * OPcache array, so it is never modified: runtime additions go to
     * $extraBlocklist and runtime removals to $removedBlocklist.
     *
     * @var array<string, int|bool>
     */
    private array $blocklistIndex = [];

    /**
     * @var array<string, string> Blocked domains on top of the base list => source (custom|synced).
     */
    private array $extraBlocklist = [];

    /**
     * @var array<string, bool> Base blocklist entries removed at runtime.
     */
    private array $removedBlocklist = [];

    /**
     * @var array<string, int|bool> Lookup index of the base allowlist (bundled or `lists.allowlist_path`).
     */
    private array $allowlistIndex = [];

    /**
     * @var array<string, bool> Domains allowed by the application (`lists.custom_allowlist*`, addToAllowlist()).
     */
    private array $customAllowlistIndex = [];

    /**
     * @var RoleBasedValidator|null Role-based validator instance.
     */
    private ?RoleBasedValidator $roleBasedValidator = null;

    /**
     * @var TypoSuggester|null Typo suggester instance.
     */
    private ?TypoSuggester $typoSuggester = null;

    /**
     * @var SubaddressDetector|null Subaddress detector instance.
     */
    private ?SubaddressDetector $subaddressDetector = null;

    /**
     * @var SmtpValidator|null SMTP validator instance.
     */
    private ?SmtpValidator $smtpValidator = null;

    /**
     * @var CatchAllDetector|null Catch-all detector instance.
     */
    private ?CatchAllDetector $catchAllDetector = null;

    /**
     * EmailValidator constructor.
     *
     * @param Configuration|array<string>|null $configOrBlocklist Configuration or blocklist for backward compatibility.
     * @param array<string> $allowlist Allowlist for backward compatibility.
     */
    public function __construct(Configuration|array|null $configOrBlocklist = null, array $allowlist = [])
    {
        // Backward compatibility: if array is passed, treat as blocklist
        if (is_array($configOrBlocklist)) {
            $this->config = new Configuration();
            $this->blocklistIndex = self::toIndex($configOrBlocklist);
            $this->allowlistIndex = self::toIndex($allowlist);
        } elseif ($configOrBlocklist instanceof Configuration) {
            $this->config = $configOrBlocklist;
            $this->loadLists();
        } else {
            $this->config = new Configuration();
            $this->loadLists();
        }

        $this->initializeServices();
    }

    /**
     * Initialize services based on configuration.
     */
    private function initializeServices(): void
    {
        try {
            $this->cache = CacheManager::create($this->config);
        } catch (\Exception $e) {
            $this->cache = new MemoryCacheAdapter();
        }

        $this->rateLimiter = RateLimiterManager::create($this->config, $this->cache);
        $this->logger = $this->config->getLogger() ?? new NullLogger();
    }

    /**
     * Load blocklist and allowlist from files and configuration.
     *
     * Missing custom or synced list files are skipped (a synced list does not
     * exist until the first sync); a missing base list path throws.
     */
    private function loadLists(): void
    {
        $blocklistPath = $this->config->getBlocklistPath();
        $allowlistPath = $this->config->getAllowlistPath();

        $this->blocklistIndex = $blocklistPath !== null
            ? self::toIndex(Fetcher::loadCustomBlocklist($blocklistPath))
            : Fetcher::loadBlocklistIndex();

        $this->allowlistIndex = $allowlistPath !== null
            ? self::toIndex(Fetcher::loadCustomAllowlist($allowlistPath))
            : Fetcher::loadAllowlistIndex();

        $this->customAllowlistIndex = self::toIndex(array_merge(
            $this->config->getCustomAllowlist(),
            Fetcher::loadPaths($this->config->getCustomAllowlistPaths(), true)
        ));

        $this->extraBlocklist = [];
        $this->removedBlocklist = [];

        $syncedPath = $this->config->getSyncedBlocklistPath();
        if ($syncedPath !== null) {
            foreach (Fetcher::loadPaths([$syncedPath], true) as $domain) {
                $this->addExtraBlocked($domain, DisposableCheckResult::SOURCE_SYNCED);
            }
        }

        $custom = array_merge(
            Fetcher::loadPaths($this->config->getCustomBlocklistPaths(), true),
            $this->config->getCustomBlocklist()
        );
        foreach ($custom as $domain) {
            $this->addExtraBlocked($domain, DisposableCheckResult::SOURCE_CUSTOM);
        }
    }

    /**
     * Records a blocked domain that is not part of the base blocklist.
     */
    private function addExtraBlocked(string $domain, string $source): void
    {
        if (!isset($this->blocklistIndex[$domain])) {
            $this->extraBlocklist[$domain] = $source;
        }
    }

    /**
     * Builds a lookup index from a list of domains.
     *
     * @param array<string> $domains
     * @return array<string, bool>
     */
    private static function toIndex(array $domains): array
    {
        $index = [];
        foreach ($domains as $domain) {
            $index[strtolower(trim((string) $domain))] = true;
        }
        return $index;
    }

    // ==================== Factory Methods ====================

    /**
     * Creates an EmailValidator instance with default lists loaded from files.
     *
     * @return self
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Creates an EmailValidator instance with configuration.
     *
     * @param Configuration $config The configuration instance.
     * @return self
     */
    public static function withConfig(Configuration $config): self
    {
        return new self($config);
    }

    /**
     * Creates an EmailValidator instance from a configuration file.
     *
     * @param string $path Path to the configuration file.
     * @return self
     */
    public static function fromConfigFile(string $path): self
    {
        return new self(Configuration::fromFile($path));
    }

    /**
     * Creates an EmailValidator instance with strict validation settings.
     *
     * @return self
     */
    public static function strict(): self
    {
        $config = ConfigurationBuilder::create()->strict()->build();
        return new self($config);
    }

    /**
     * Creates an EmailValidator instance with basic validation settings.
     *
     * @return self
     */
    public static function basic(): self
    {
        $config = ConfigurationBuilder::create()->basic()->build();
        return new self($config);
    }

    // ==================== Main Validation Methods ====================

    /**
     * {@inheritdoc}
     */
    public function validate(string $email): ValidationResult
    {
        $this->checkRateLimit($email);

        $result = new ValidationResult($email);

        // Format check
        if ($this->config->isFormatCheckEnabled()) {
            $formatValid = $this->isValidFormat($email);
            $result->addCheck('format', $formatValid, $formatValid ? null : 'Invalid email format');

            if (!$formatValid) {
                $this->logValidation($email, $result);
                return $result;
            }
        }

        // Spoof (homograph / mixed-script) check
        if ($this->config->isSpoofCheckEnabled()) {
            $isSpoofed = $this->isSpoofed($email);
            if ($isSpoofed === null) {
                $result->addWarning('Spoof check skipped: ext-intl is not available');
            } else {
                $result->addCheck('spoof', !$isSpoofed, $isSpoofed ? 'Email contains mixed-script or invisible characters' : null);
            }
        }

        // Disposable check
        if ($this->config->isDisposableCheckEnabled()) {
            $disposable = strpos($email, '@') !== false ? $this->checkDisposable($email) : null;
            $isDisposable = $disposable !== null && $disposable->isDisposable();
            $result->addCheck('disposable', !$isDisposable, $isDisposable ? 'Disposable email address' : null);

            if ($disposable !== null && $disposable->getMatchedDomain() !== null) {
                $result->addMetadata('disposable_match', $disposable->getMatchedDomain());
                $result->addMetadata('disposable_source', $disposable->getSource());
            }
        }

        // MX check
        if ($this->config->isMxCheckEnabled()) {
            $hasMx = $this->hasValidMX($email);
            $result->addCheck('mx', $hasMx, $hasMx ? null : 'No valid MX record found');
        }

        // Role-based check
        if ($this->config->isRoleBasedCheckEnabled()) {
            $isRoleBased = $this->isRoleBased($email);
            $result->addCheck('role_based', !$isRoleBased, $isRoleBased ? 'Role-based email address' : null);
        }

        // Subaddress detection (informational, doesn't fail validation by default)
        if ($this->config->isSubaddressCheckEnabled()) {
            $isSubaddressed = $this->isSubaddressed($email);
            $result->addMetadata('subaddressed', $isSubaddressed);
            if ($isSubaddressed) {
                $result->addMetadata('base_email', $this->getBaseEmail($email));
                $result->addWarning('Email contains plus addressing');
            }
        }

        // SMTP verification
        if ($this->config->isSmtpCheckEnabled()) {
            $smtpValid = $this->validateSMTP($email);
            $result->addCheck('smtp', $smtpValid, $smtpValid ? null : 'SMTP verification failed');
        }

        // Catch-all check
        if ($this->config->isCatchAllCheckEnabled()) {
            $isCatchAll = $this->isCatchAll($email);
            $result->addCheck('catch_all', !$isCatchAll, $isCatchAll ? 'Catch-all domain detected' : null);
        }

        // Typo suggestion (doesn't affect validity)
        if ($this->config->isTypoSuggestionEnabled()) {
            $suggestion = $this->getSuggestion($email);
            if ($suggestion !== null) {
                $result->setSuggestion($suggestion);
            }
        }

        $this->logValidation($email, $result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(string $email, ?bool $checkMX = null): bool
    {
        // Backward compatibility with $checkMX parameter
        if ($checkMX !== null) {
            $originalMxSetting = $this->config->isMxCheckEnabled();
            $this->config->enableMxCheck($checkMX);
            $result = $this->validate($email)->isValid();
            $this->config->enableMxCheck($originalMxSetting);
            return $result;
        }

        return $this->validate($email)->isValid();
    }

    /**
     * {@inheritdoc}
     */
    public function validateBatch(array $emails): array
    {
        $results = [];
        foreach ($emails as $email) {
            $results[$email] = $this->validate($email);
        }
        return $results;
    }

    // ==================== Individual Check Methods ====================

    /**
     * Validates the format of the given email address.
     *
     * @param string $email The email address to validate.
     * @return bool Returns true if the email format is valid, false otherwise.
     */
    public function isValidFormat(string $email): bool
    {
        if ($email === '') {
            return false;
        }

        switch ($this->config->getFormatMode()) {
            case 'filter_unicode':
                return filter_var($email, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) !== false;
            case 'rfc':
            case 'strict':
                return $this->isValidRfcFormat($email, $this->config->getFormatMode() === 'strict');
            default:
                return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        }
    }

    /**
     * Checks if the given email address is from a disposable email provider.
     *
     * The domain and each of its parent domains are checked, from most to
     * least specific (e.g. "a.b.mailinator.com", "b.mailinator.com",
     * "mailinator.com"), so subdomains of a disposable provider are detected
     * too (disable with `lists.block_subdomains = false`). The most specific
     * match wins; on the same level the allowlist takes priority.
     *
     * @param string $email The email address to check.
     * @return bool Returns true if the email is disposable, false otherwise.
     */
    public function isDisposable(string $email): bool
    {
        if (strpos($email, '@') === false) {
            return false;
        }

        return $this->checkDisposable($email)->isDisposable();
    }

    /**
     * Checks if a bare domain (e.g. "mailinator.com") is disposable.
     *
     * @param string $domain The domain to check.
     * @return bool
     */
    public function isDisposableDomain(string $domain): bool
    {
        return $this->checkDisposable($domain)->isDisposable();
    }

    /**
     * Explains a disposable-domain lookup for an email address or bare domain:
     * whether it is disposable, which list entry matched (the domain or one of
     * its parents) and which list that entry came from.
     *
     * ```php
     * $check = $validator->checkDisposable('user@eu.mailinator.com');
     * $check->isDisposable();      // true
     * $check->getMatchedDomain();  // 'mailinator.com'
     * $check->getSource();         // 'blocklist'
     * ```
     *
     * @param string $emailOrDomain An email address or a bare domain.
     * @return DisposableCheckResult
     */
    public function checkDisposable(string $emailOrDomain): DisposableCheckResult
    {
        $domain = Domain::normalize($emailOrDomain);

        if ($domain === null) {
            return new DisposableCheckResult($emailOrDomain, null, false);
        }

        $candidates = $this->config->isSubdomainBlockingEnabled() ? Domain::withParents($domain) : [$domain];

        // Precedence on each level: the application's own lists first (allow, then block),
        // then the bundled allowlist, then the bundled and synced blocklists.
        foreach ($candidates as $candidate) {
            if (isset($this->customAllowlistIndex[$candidate])) {
                return new DisposableCheckResult($emailOrDomain, $domain, false, $candidate, DisposableCheckResult::SOURCE_ALLOWLIST);
            }

            $extraSource = $this->extraBlocklist[$candidate] ?? null;

            if ($extraSource === DisposableCheckResult::SOURCE_CUSTOM) {
                return new DisposableCheckResult($emailOrDomain, $domain, true, $candidate, $extraSource);
            }

            if (isset($this->allowlistIndex[$candidate])) {
                return new DisposableCheckResult($emailOrDomain, $domain, false, $candidate, DisposableCheckResult::SOURCE_ALLOWLIST);
            }

            if ($extraSource !== null) {
                return new DisposableCheckResult($emailOrDomain, $domain, true, $candidate, $extraSource);
            }

            if (isset($this->blocklistIndex[$candidate]) && !isset($this->removedBlocklist[$candidate])) {
                return new DisposableCheckResult($emailOrDomain, $domain, true, $candidate, DisposableCheckResult::SOURCE_BLOCKLIST);
            }
        }

        return new DisposableCheckResult($emailOrDomain, $domain, false);
    }

    /**
     * Detects visually deceptive addresses: a local part or domain label that
     * mixes scripts (e.g. a Cyrillic "а" inside "gmаil") or contains invisible
     * characters. Each part is checked separately, so legitimate addresses in
     * a single non-Latin script (e.g. "иван@почта.рф") are not flagged.
     *
     * @param string $email The email address to check.
     * @return bool|null True if suspicious, false if not, null if ext-intl is unavailable.
     */
    public function isSpoofed(string $email): ?bool
    {
        if (!class_exists(\Spoofchecker::class)) {
            return null;
        }

        $checker = new \Spoofchecker();
        $checker->setChecks(\Spoofchecker::SINGLE_SCRIPT | \Spoofchecker::INVISIBLE);

        $atPos = strrpos($email, '@');
        $parts = $atPos === false ? [$email] : [substr($email, 0, $atPos)];

        if ($atPos !== false) {
            foreach (explode('.', substr($email, $atPos + 1)) as $label) {
                if (strncasecmp($label, 'xn--', 4) === 0 && function_exists('idn_to_utf8')) {
                    $label = idn_to_utf8($label, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $label;
                }
                $parts[] = $label;
            }
        }

        foreach ($parts as $part) {
            if ($part !== '' && $checker->isSuspicious($part)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the given email address has valid MX records.
     *
     * @param string $email The email address to check.
     * @return bool Returns true if the email domain has valid MX records, false otherwise.
     */
    public function hasValidMX(string $email): bool
    {
        $domain = $this->extractDomain($email);

        if ($domain === null) {
            return false;
        }

        $cacheKey = 'mx_' . $domain;

        if ($this->cache->has($cacheKey)) {
            return (bool) $this->cache->get($cacheKey, false);
        }

        $result = checkdnsrr($domain, 'MX');

        $this->cache->set($cacheKey, $result, $this->config->getCacheTtl());

        return $result;
    }

    /**
     * Checks if the domain has valid A or AAAA records.
     *
     * @param string $email The email address to check.
     * @return bool Returns true if the domain has A or AAAA records.
     */
    public function hasValidDNS(string $email): bool
    {
        $domain = $this->extractDomain($email);

        if ($domain === null) {
            return false;
        }

        return checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
    }

    /**
     * Checks if the email address is role-based.
     *
     * @param string $email The email address to check.
     * @return bool Returns true if the email is role-based.
     */
    public function isRoleBased(string $email): bool
    {
        $this->ensureRoleBasedValidator();
        return $this->roleBasedValidator->isRoleBased($email);
    }

    /**
     * Checks if the email uses subaddressing (plus addressing).
     *
     * @param string $email The email address to check.
     * @return bool Returns true if the email uses subaddressing.
     */
    public function isSubaddressed(string $email): bool
    {
        $this->ensureSubaddressDetector();
        return $this->subaddressDetector->isSubaddressed($email);
    }

    /**
     * Gets the base email without the subaddress tag.
     *
     * @param string $email The email address.
     * @return string|null The base email or null if invalid.
     */
    public function getBaseEmail(string $email): ?string
    {
        $this->ensureSubaddressDetector();
        return $this->subaddressDetector->getBaseEmail($email);
    }

    /**
     * Gets a typo correction suggestion for the email.
     *
     * @param string $email The email address to check.
     * @return SuggestionResult|null The suggestion or null if no typo detected.
     */
    public function getSuggestion(string $email): ?SuggestionResult
    {
        $this->ensureTypoSuggester();
        return $this->typoSuggester->getSuggestion($email);
    }

    /**
     * Checks if the email domain is a catch-all.
     *
     * @param string $email The email address to check.
     * @return bool Returns true if the domain is catch-all.
     */
    public function isCatchAll(string $email): bool
    {
        $this->ensureCatchAllDetector();
        return $this->catchAllDetector->isCatchAll($email);
    }

    /**
     * Verifies an email address via SMTP.
     *
     * @param string $email The email address to verify.
     * @return bool Returns true if the email appears valid.
     */
    public function validateSMTP(string $email): bool
    {
        $this->ensureSmtpValidator();
        return $this->smtpValidator->verify($email);
    }

    // ==================== Backward Compatible Methods ====================

    /**
     * Validates multiple email addresses at once.
     *
     * @param array<string> $emails Array of email addresses to validate.
     * @param bool $checkMX Whether to check MX records (default: true).
     * @return array<string, array{valid: bool, format: bool, disposable: bool, mx: bool|null, errors: array<string>}>
     */
    public function validateMultiple(array $emails, bool $checkMX = true): array
    {
        $results = [];

        foreach ($emails as $email) {
            $results[$email] = $this->validateWithDetails($email, $checkMX);
        }

        return $results;
    }

    /**
     * Validates an email and returns detailed results (backward compatible format).
     *
     * @param string $email The email address to validate.
     * @param bool $checkMX Whether to check MX records (default: true).
     * @return array{valid: bool, format: bool, disposable: bool, mx: bool|null, domain: string|null, errors: array<string>}
     */
    public function validateWithDetails(string $email, bool $checkMX = true): array
    {
        $result = [
            'valid' => true,
            'format' => false,
            'disposable' => false,
            'mx' => null,
            'domain' => $this->extractDomain($email),
            'errors' => [],
        ];

        $result['format'] = $this->isValidFormat($email);
        if (!$result['format']) {
            $result['valid'] = false;
            $result['errors'][] = 'Invalid email format';
        }

        if ($result['format']) {
            $result['disposable'] = $this->isDisposable($email);
            if ($result['disposable']) {
                $result['valid'] = false;
                $result['errors'][] = 'Disposable email address';
            }
        }

        if ($checkMX && $result['format']) {
            $result['mx'] = $this->hasValidMX($email);
            if (!$result['mx']) {
                $result['valid'] = false;
                $result['errors'][] = 'No valid MX record found';
            }
        }

        return $result;
    }

    /**
     * Filters an array of emails and returns only valid ones.
     *
     * @param array<string> $emails Array of email addresses to filter.
     * @param bool $checkMX Whether to check MX records (default: true).
     * @return array<string> Array of valid email addresses.
     */
    public function filterValid(array $emails, bool $checkMX = true): array
    {
        return array_values(array_filter($emails, fn($email) => $this->isValid($email, $checkMX)));
    }

    /**
     * Filters an array of emails and returns only invalid ones.
     *
     * @param array<string> $emails Array of email addresses to filter.
     * @param bool $checkMX Whether to check MX records (default: true).
     * @return array<string> Array of invalid email addresses.
     */
    public function filterInvalid(array $emails, bool $checkMX = true): array
    {
        return array_values(array_filter($emails, fn($email) => !$this->isValid($email, $checkMX)));
    }

    /**
     * Gets statistics about validation results for multiple emails.
     *
     * @param array<string> $emails Array of email addresses.
     * @param bool $checkMX Whether to check MX records (default: true).
     * @return array{total: int, valid: int, invalid: int, disposable: int, invalid_format: int, no_mx: int}
     */
    public function getStatistics(array $emails, bool $checkMX = true): array
    {
        $stats = [
            'total' => count($emails),
            'valid' => 0,
            'invalid' => 0,
            'disposable' => 0,
            'invalid_format' => 0,
            'no_mx' => 0,
        ];

        foreach ($emails as $email) {
            $result = $this->validateWithDetails($email, $checkMX);

            if ($result['valid']) {
                $stats['valid']++;
            } else {
                $stats['invalid']++;
            }

            if (!$result['format']) {
                $stats['invalid_format']++;
            }

            if ($result['disposable']) {
                $stats['disposable']++;
            }

            if ($checkMX && $result['mx'] === false) {
                $stats['no_mx']++;
            }
        }

        return $stats;
    }

    // ==================== Utility Methods ====================

    /**
     * Extracts the domain from an email address.
     *
     * @param string $email The email address.
     * @return string|null The domain or null if extraction fails.
     */
    public function extractDomain(string $email): ?string
    {
        if (!$this->isValidFormat($email)) {
            $atPos = strrpos($email, '@');
            if ($atPos === false) {
                return null;
            }
            return strtolower(substr($email, $atPos + 1));
        }

        return strtolower(substr(strrchr($email, '@'), 1));
    }

    /**
     * Extracts the local part (username) from an email address.
     *
     * @param string $email The email address.
     * @return string|null The local part or null if extraction fails.
     */
    public function extractLocalPart(string $email): ?string
    {
        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return null;
        }

        return substr($email, 0, $atPos);
    }

    /**
     * Normalizes an email address (lowercase, trim whitespace).
     *
     * @param string $email The email address to normalize.
     * @return string The normalized email address.
     */
    public function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Normalizes multiple email addresses.
     *
     * @param array<string> $emails Array of email addresses to normalize.
     * @return array<string> Array of normalized email addresses.
     */
    public function normalizeMultiple(array $emails): array
    {
        return array_map([$this, 'normalize'], $emails);
    }

    // ==================== List Management ====================

    /**
     * Checks if the email domain is in the allowlist.
     *
     * @param string $email The email address to check.
     * @return bool Returns true if domain is in allowlist.
     */
    public function isAllowlisted(string $email): bool
    {
        $domain = $this->extractDomain($email);
        return $domain !== null && (isset($this->customAllowlistIndex[$domain]) || isset($this->allowlistIndex[$domain]));
    }

    /**
     * Checks if the email domain is in the blocklist (exact match, parent
     * domains are not considered — use isDisposable() for that).
     *
     * @param string $email The email address to check.
     * @return bool Returns true if domain is in blocklist.
     */
    public function isBlocklisted(string $email): bool
    {
        $domain = $this->extractDomain($email);

        if ($domain === null) {
            return false;
        }

        return isset($this->extraBlocklist[$domain])
            || (isset($this->blocklistIndex[$domain]) && !isset($this->removedBlocklist[$domain]));
    }

    /**
     * Adds a domain to the blocklist.
     *
     * @param string $domain The domain to add.
     * @return self
     */
    public function addToBlocklist(string $domain): self
    {
        $domain = strtolower(trim($domain));

        if (isset($this->removedBlocklist[$domain])) {
            unset($this->removedBlocklist[$domain]);
        } elseif (!isset($this->blocklistIndex[$domain]) && !isset($this->extraBlocklist[$domain])) {
            $this->extraBlocklist[$domain] = DisposableCheckResult::SOURCE_CUSTOM;
        }

        return $this;
    }

    /**
     * Adds multiple domains to the blocklist.
     *
     * @param array<string> $domains The domains to add.
     * @return self
     */
    public function addMultipleToBlocklist(array $domains): self
    {
        foreach ($domains as $domain) {
            $this->addToBlocklist($domain);
        }
        return $this;
    }

    /**
     * Adds a domain to the allowlist.
     *
     * @param string $domain The domain to add.
     * @return self
     */
    public function addToAllowlist(string $domain): self
    {
        $this->customAllowlistIndex[strtolower(trim($domain))] = true;
        return $this;
    }

    /**
     * Adds multiple domains to the allowlist.
     *
     * @param array<string> $domains The domains to add.
     * @return self
     */
    public function addMultipleToAllowlist(array $domains): self
    {
        foreach ($domains as $domain) {
            $this->addToAllowlist($domain);
        }
        return $this;
    }

    /**
     * Removes a domain from the blocklist.
     *
     * @param string $domain The domain to remove.
     * @return self
     */
    public function removeFromBlocklist(string $domain): self
    {
        $domain = strtolower(trim($domain));

        unset($this->extraBlocklist[$domain]);

        if (isset($this->blocklistIndex[$domain])) {
            $this->removedBlocklist[$domain] = true;
        }

        return $this;
    }

    /**
     * Removes a domain from the allowlist.
     *
     * @param string $domain The domain to remove.
     * @return self
     */
    public function removeFromAllowlist(string $domain): self
    {
        $domain = strtolower(trim($domain));
        unset($this->customAllowlistIndex[$domain], $this->allowlistIndex[$domain]);
        return $this;
    }

    /**
     * Gets the current blocklist (base list plus custom and synced domains).
     *
     * @return array<string>
     */
    public function getBlocklist(): array
    {
        $base = $this->removedBlocklist === []
            ? array_keys($this->blocklistIndex)
            : array_keys(array_diff_key($this->blocklistIndex, $this->removedBlocklist));

        return array_map('strval', array_merge($base, array_keys($this->extraBlocklist)));
    }

    /**
     * Gets the current allowlist.
     *
     * @return array<string>
     */
    public function getAllowlist(): array
    {
        return array_map('strval', array_keys($this->allowlistIndex + $this->customAllowlistIndex));
    }

    /**
     * Gets the count of domains in the blocklist.
     *
     * @return int
     */
    public function getBlocklistCount(): int
    {
        return count($this->blocklistIndex) - count($this->removedBlocklist) + count($this->extraBlocklist);
    }

    /**
     * Gets the count of domains in the allowlist.
     *
     * @return int
     */
    public function getAllowlistCount(): int
    {
        return count($this->allowlistIndex + $this->customAllowlistIndex);
    }

    /**
     * Returns statistics about the loaded lists, e.g. for a status page or
     * the `email-validator stats` command.
     *
     * @return array{
     *     blocklist: int,
     *     custom: int,
     *     synced: int,
     *     total_blocked: int,
     *     allowlist: int,
     *     block_subdomains: bool,
     *     synced_path: string|null,
     *     last_synced: string|null,
     *     bundled_generated_at: string|null,
     *     bundled_sources: array<string>,
     *     index_strategy: string
     * }
     */
    public function getListStatistics(): array
    {
        $sources = array_count_values($this->extraBlocklist);
        $syncedPath = $this->config->getSyncedBlocklistPath();
        $syncedAt = $syncedPath !== null && is_file($syncedPath) ? filemtime($syncedPath) : false;
        $metadata = Fetcher::getMetadata();

        return [
            'blocklist' => count($this->blocklistIndex) - count($this->removedBlocklist),
            'custom' => $sources[DisposableCheckResult::SOURCE_CUSTOM] ?? 0,
            'synced' => $sources[DisposableCheckResult::SOURCE_SYNCED] ?? 0,
            'total_blocked' => $this->getBlocklistCount(),
            'allowlist' => $this->getAllowlistCount(),
            'block_subdomains' => $this->config->isSubdomainBlockingEnabled(),
            'synced_path' => $syncedPath,
            'last_synced' => $syncedAt !== false ? date(DATE_ATOM, $syncedAt) : null,
            'bundled_generated_at' => isset($metadata['generated_at']) ? (string) $metadata['generated_at'] : null,
            'bundled_sources' => array_keys((array) ($metadata['sources'] ?? [])),
            'index_strategy' => Fetcher::isOpcacheActive() ? 'opcache' : 'text',
        ];
    }

    /**
     * Adds a role-based prefix.
     *
     * @param string $prefix The prefix to add.
     * @return self
     */
    public function addRoleBasedPrefix(string $prefix): self
    {
        $this->ensureRoleBasedValidator();
        $this->roleBasedValidator->addPrefix($prefix);
        return $this;
    }

    // ==================== Configuration ====================

    /**
     * {@inheritdoc}
     */
    public function setConfiguration(Configuration $config): static
    {
        $this->config = $config;
        $this->loadLists();
        $this->initializeServices();
        $this->resetValidators();
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(): Configuration
    {
        return $this->config;
    }

    /**
     * Sets the logger instance.
     *
     * @param LoggerInterface $logger The logger instance.
     * @return self
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Enables or disables MX record caching (backward compatibility).
     *
     * @param bool $enabled Whether to enable caching.
     * @return self
     */
    public function setCacheEnabled(bool $enabled): self
    {
        if (!$enabled) {
            $this->config->setCacheDriver('null');
            $this->initializeServices();
        }
        return $this;
    }

    /**
     * Clears the cache.
     *
     * @return self
     */
    public function clearCache(): self
    {
        $this->cache->clear();
        return $this;
    }

    // ==================== Private Helper Methods ====================

    /**
     * Validates the format with egulias/email-validator (RFC 5322).
     *
     * @param string $email The email address.
     * @param bool $strict Reject addresses that are valid but produce RFC warnings.
     * @return bool
     * @throws ConfigurationException If egulias/email-validator is not installed.
     */
    private function isValidRfcFormat(string $email, bool $strict): bool
    {
        if (!class_exists(\Egulias\EmailValidator\EmailValidator::class)) {
            throw ConfigurationException::invalidValue(
                'format.mode',
                $this->config->getFormatMode(),
                'an installed egulias/email-validator package (composer require egulias/email-validator)'
            );
        }

        $validation = $strict
            ? new \Egulias\EmailValidator\Validation\NoRFCWarningsValidation()
            : new \Egulias\EmailValidator\Validation\RFCValidation();

        return (new \Egulias\EmailValidator\EmailValidator())->isValid($email, $validation);
    }

    /**
     * Ensures the role-based validator is initialized.
     */
    private function ensureRoleBasedValidator(): void
    {
        if ($this->roleBasedValidator === null) {
            $this->roleBasedValidator = new RoleBasedValidator($this->config);
        }
    }

    /**
     * Ensures the typo suggester is initialized.
     */
    private function ensureTypoSuggester(): void
    {
        if ($this->typoSuggester === null) {
            $this->typoSuggester = new TypoSuggester($this->config);
        }
    }

    /**
     * Ensures the subaddress detector is initialized.
     */
    private function ensureSubaddressDetector(): void
    {
        if ($this->subaddressDetector === null) {
            $this->subaddressDetector = new SubaddressDetector();
        }
    }

    /**
     * Ensures the SMTP validator is initialized.
     */
    private function ensureSmtpValidator(): void
    {
        if ($this->smtpValidator === null) {
            $this->smtpValidator = new SmtpValidator($this->config);
        }
    }

    /**
     * Ensures the catch-all detector is initialized.
     */
    private function ensureCatchAllDetector(): void
    {
        $this->ensureSmtpValidator();
        if ($this->catchAllDetector === null) {
            $this->catchAllDetector = new CatchAllDetector(
                $this->smtpValidator,
                $this->cache,
                $this->config
            );
        }
    }

    /**
     * Resets all lazy-loaded validators.
     */
    private function resetValidators(): void
    {
        $this->roleBasedValidator = null;
        $this->typoSuggester = null;
        $this->subaddressDetector = null;
        $this->smtpValidator = null;
        $this->catchAllDetector = null;
    }

    /**
     * Checks the rate limit.
     *
     * @param string $email The email address.
     * @throws RateLimitExceededException
     */
    private function checkRateLimit(string $email): void
    {
        if (!$this->rateLimiter->attempt($email)) {
            throw RateLimitExceededException::create(
                $this->rateLimiter->availableIn($email),
                $this->config->getRateLimitMaxAttempts(),
                $email
            );
        }
    }

    /**
     * Logs the validation result.
     *
     * @param string $email The email address.
     * @param ValidationResult $result The validation result.
     */
    private function logValidation(string $email, ValidationResult $result): void
    {
        $this->logger->debug('Email validation completed', [
            'email' => $email,
            'valid' => $result->isValid(),
            'checks' => $result->getChecks(),
            'errors' => $result->getErrors(),
        ]);
    }
}
