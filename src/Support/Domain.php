<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Support;

/**
 * Class Domain
 *
 * Normalization and syntax helpers for domain names. Used wherever a domain
 * is compared against a list, so that "User@Mailinator.COM." and
 * "user@mailinator.com" resolve to the same list entry.
 *
 * @package HarunGecit\EmailValidator\Support
 */
final class Domain
{
    /**
     * Hostname syntax: at least two labels, LDH labels of 1–63 characters,
     * a TLD of at least two characters (punycode "xn--" labels included).
     */
    private const PATTERN = '/^(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/';

    private function __construct()
    {
    }

    /**
     * Extracts the domain part from an email address, or returns the input
     * unchanged when it does not contain an "@".
     *
     * @param string $emailOrDomain An email address or a bare domain.
     * @return string The raw (not yet normalized) domain part.
     */
    public static function extract(string $emailOrDomain): string
    {
        $atPos = strrpos($emailOrDomain, '@');

        return $atPos === false ? $emailOrDomain : substr($emailOrDomain, $atPos + 1);
    }

    /**
     * Normalizes an email address or domain to a comparable domain name:
     * trimmed, lowercased, without a trailing dot, and converted to its
     * ASCII (punycode) form when ext-intl is available.
     *
     * @param string $emailOrDomain An email address or a bare domain.
     * @return string|null The normalized domain, or null if nothing usable remains.
     */
    public static function normalize(string $emailOrDomain): ?string
    {
        $domain = strtolower(trim(self::extract(trim($emailOrDomain))));
        $domain = rtrim($domain, '.');

        if ($domain === '') {
            return null;
        }

        if (preg_match('/[^\x20-\x7e]/', $domain) === 1 && function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($ascii !== false) {
                $domain = strtolower($ascii);
            }
        }

        return $domain;
    }

    /**
     * Checks whether a (normalized) domain is a syntactically valid hostname
     * with at least two labels.
     *
     * @param string $domain The domain to check.
     * @return bool
     */
    public static function isValid(string $domain): bool
    {
        return preg_match(self::PATTERN, $domain) === 1;
    }

    /**
     * Returns the domain followed by each of its parent domains, from most
     * to least specific, stopping before the bare TLD.
     *
     * Example: "a.b.example.com" yields "a.b.example.com", "b.example.com", "example.com".
     *
     * @param string $domain A normalized domain.
     * @return array<string>
     */
    public static function withParents(string $domain): array
    {
        $candidates = [];

        while (strpos($domain, '.') !== false) {
            $candidates[] = $domain;
            $domain = substr($domain, strpos($domain, '.') + 1);
        }

        return $candidates;
    }
}
