<?php

namespace HarunGecit\EmailValidator;

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
 * @version 2.0.0
 *
 * ### Usage Examples
 *
 * ```php
 * <?php
 *
 * require 'vendor/autoload.php';
 *
 * use HarunGecit\EmailValidator\EmailValidator;
 * use HarunGecit\EmailValidator\Fetcher;
 *
 * // Load blocklist and allowlist
 * $blocklist = Fetcher::loadBlocklist();
 * $allowlist = Fetcher::loadAllowlist();
 *
 * // Create an instance of EmailValidator
 * $validator = new EmailValidator($blocklist, $allowlist);
 *
 * // Single email validation
 * $email = "user@example.com";
 * if ($validator->isValid($email)) {
 *     echo "Email is valid!\n";
 * }
 *
 * // Batch email validation
 * $emails = ["user1@gmail.com", "user2@mailinator.com", "invalid-email"];
 * $results = $validator->validateMultiple($emails);
 * foreach ($results as $email => $result) {
 *     echo "$email: " . ($result['valid'] ? 'Valid' : 'Invalid') . "\n";
 * }
 * ```
 */
class EmailValidator
{
    /**
     * @var array<string> List of disposable email domains to block
     */
    private array $blocklist;

    /**
     * @var array<string> List of email domains to always allow
     */
    private array $allowlist;

    /**
     * @var array<string, bool> Cache for MX record lookups
     */
    private array $mxCache = [];

    /**
     * @var bool Enable/disable MX record caching
     */
    private bool $cacheEnabled = true;

    /**
     * EmailValidator constructor.
     *
     * @param array<string> $blocklist List of disposable email domains to block.
     * @param array<string> $allowlist List of email domains to allow.
     */
    public function __construct(array $blocklist = [], array $allowlist = [])
    {
        $this->blocklist = array_map('strtolower', $blocklist);
        $this->allowlist = array_map('strtolower', $allowlist);
    }

    /**
     * Creates an EmailValidator instance with default lists loaded from files.
     *
     * @return self
     */
    public static function create(): self
    {
        return new self(
            Fetcher::loadBlocklist(),
            Fetcher::loadAllowlist()
        );
    }

    /**
     * Validates the format of the given email address.
     *
     * @param string $email The email address to validate.
     * @return bool Returns true if the email format is valid, false otherwise.
     */
    public function isValidFormat(string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Checks if the given email address is from a disposable email provider.
     *
     * @param string $email The email address to check.
     * @return bool Returns true if the email is disposable, false otherwise.
     */
    public function isDisposable(string $email): bool
    {
        $domain = $this->extractDomain($email);

        if ($domain === null) {
            return false;
        }

        if (in_array($domain, $this->allowlist, true)) {
            return false;
        }

        return in_array($domain, $this->blocklist, true);
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

        if ($this->cacheEnabled && isset($this->mxCache[$domain])) {
            return $this->mxCache[$domain];
        }

        $result = checkdnsrr($domain, 'MX');

        if ($this->cacheEnabled) {
            $this->mxCache[$domain] = $result;
        }

        return $result;
    }

    /**
     * Checks if the domain has valid A or AAAA records (fallback for domains without MX).
     *
     * @param string $email The email address to check.
     * @return bool Returns true if the domain has A or AAAA records, false otherwise.
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
     * Performs complete email validation (format + not disposable + MX record).
     *
     * @param string $email The email address to validate.
     * @param bool $checkMX Whether to check MX records (default: true).
     * @return bool Returns true if email passes all validations, false otherwise.
     */
    public function isValid(string $email, bool $checkMX = true): bool
    {
        if (!$this->isValidFormat($email)) {
            return false;
        }

        if ($this->isDisposable($email)) {
            return false;
        }

        if ($checkMX && !$this->hasValidMX($email)) {
            return false;
        }

        return true;
    }

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
     * Validates an email and returns detailed results.
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
     * Checks if the email domain is in the allowlist.
     *
     * @param string $email The email address to check.
     * @return bool Returns true if domain is in allowlist.
     */
    public function isAllowlisted(string $email): bool
    {
        $domain = $this->extractDomain($email);
        return $domain !== null && in_array($domain, $this->allowlist, true);
    }

    /**
     * Checks if the email domain is in the blocklist.
     *
     * @param string $email The email address to check.
     * @return bool Returns true if domain is in blocklist.
     */
    public function isBlocklisted(string $email): bool
    {
        $domain = $this->extractDomain($email);
        return $domain !== null && in_array($domain, $this->blocklist, true);
    }

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

    /**
     * Adds a domain to the blocklist.
     *
     * @param string $domain The domain to add.
     * @return self
     */
    public function addToBlocklist(string $domain): self
    {
        $domain = strtolower(trim($domain));
        if (!in_array($domain, $this->blocklist, true)) {
            $this->blocklist[] = $domain;
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
        $domain = strtolower(trim($domain));
        if (!in_array($domain, $this->allowlist, true)) {
            $this->allowlist[] = $domain;
        }
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
        $key = array_search($domain, $this->blocklist, true);
        if ($key !== false) {
            unset($this->blocklist[$key]);
            $this->blocklist = array_values($this->blocklist);
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
        $key = array_search($domain, $this->allowlist, true);
        if ($key !== false) {
            unset($this->allowlist[$key]);
            $this->allowlist = array_values($this->allowlist);
        }
        return $this;
    }

    /**
     * Gets the current blocklist.
     *
     * @return array<string>
     */
    public function getBlocklist(): array
    {
        return $this->blocklist;
    }

    /**
     * Gets the current allowlist.
     *
     * @return array<string>
     */
    public function getAllowlist(): array
    {
        return $this->allowlist;
    }

    /**
     * Gets the count of domains in the blocklist.
     *
     * @return int
     */
    public function getBlocklistCount(): int
    {
        return count($this->blocklist);
    }

    /**
     * Gets the count of domains in the allowlist.
     *
     * @return int
     */
    public function getAllowlistCount(): int
    {
        return count($this->allowlist);
    }

    /**
     * Enables or disables MX record caching.
     *
     * @param bool $enabled Whether to enable caching.
     * @return self
     */
    public function setCacheEnabled(bool $enabled): self
    {
        $this->cacheEnabled = $enabled;
        return $this;
    }

    /**
     * Clears the MX record cache.
     *
     * @return self
     */
    public function clearCache(): self
    {
        $this->mxCache = [];
        return $this;
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
}
