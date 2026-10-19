<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Support;

/**
 * Class ListParser
 *
 * Parses domain lists in the formats published by disposable-domain projects:
 * plain text (one domain per line, "#" / ";" / "//" comments allowed) and
 * JSON (a flat or nested array of domain strings, e.g. ["a.com", "b.com"] or
 * {"domains": [...]}).
 *
 * @package HarunGecit\EmailValidator\Support
 */
final class ListParser
{
    private function __construct()
    {
    }

    /**
     * Parses list content and returns normalized, valid, unique domains.
     *
     * @param string $content Raw list content (text or JSON).
     * @param int|null $invalid Receives the number of non-empty entries that were rejected.
     * @return array<string> Domains in input order, without duplicates.
     */
    public static function parse(string $content, ?int &$invalid = null): array
    {
        $invalid = 0;

        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
            $content = substr($content, 3);
        }

        $trimmed = ltrim($content);

        $values = null;
        if ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $values = self::flatten($decoded);
            }
        }

        if ($values === null) {
            $values = preg_split('/\R/', $content) ?: [];
        }

        $domains = [];
        foreach ($values as $value) {
            $value = trim($value);

            if ($value === '' || $value[0] === '#' || $value[0] === ';' || strncmp($value, '//', 2) === 0) {
                continue;
            }

            // Strip inline comments ("domain.com # note") and wildcard prefixes ("*.domain.com").
            $value = preg_replace('/\s+[#;].*$/', '', $value) ?? $value;
            if (strncmp($value, '*.', 2) === 0) {
                $value = substr($value, 2);
            }

            $domain = Domain::normalize($value);
            if ($domain === null || !Domain::isValid($domain)) {
                $invalid++;
                continue;
            }

            $domains[$domain] = true;
        }

        return array_keys($domains);
    }

    /**
     * Reads and parses a list file.
     *
     * @param string $path Path to the list file.
     * @return array<string>|null Domains, or null if the file cannot be read.
     */
    public static function parseFile(string $path): ?array
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content === false ? null : self::parse($content);
    }

    /**
     * Detects responses that are clearly not a domain list (HTML error pages,
     * captive portals, rate-limit pages).
     *
     * @param string $content Raw response body.
     * @return bool
     */
    public static function looksLikeHtml(string $content): bool
    {
        $head = strtolower(ltrim(substr($content, 0, 512)));

        return strncmp($head, '<!doctype', 9) === 0
            || strncmp($head, '<html', 5) === 0
            || strpos($head, '<head') !== false
            || strpos($head, '<body') !== false;
    }

    /**
     * @param array<mixed> $items
     * @return array<string>
     */
    private static function flatten(array $items): array
    {
        $values = [];

        array_walk_recursive($items, static function ($item) use (&$values): void {
            if (is_string($item)) {
                $values[] = $item;
            }
        });

        return $values;
    }
}
