<?php

namespace HarunGecit\EmailValidator;

use RuntimeException;

/**
 * Class Fetcher
 *
 * Utility class for loading blocklist and allowlist data from configuration files.
 * Supports loading from default package files or custom file paths.
 *
 * @package HarunGecit\EmailValidator
 * @author Harun Geçit <info@harungecit.com>
 * @link https://github.com/harungecit
 * @license MIT
 * @version 2.0.0
 */
class Fetcher
{
    /**
     * @var string Path to the default blocklist file
     */
    private const BLOCKLIST_PATH = __DIR__ . '/../data/blocklist.conf';

    /**
     * @var string Path to the default allowlist file
     */
    private const ALLOWLIST_PATH = __DIR__ . '/../data/allowlist.conf';

    /**
     * @var array<string>|null Cached blocklist data
     */
    private static ?array $blocklistCache = null;

    /**
     * @var array<string>|null Cached allowlist data
     */
    private static ?array $allowlistCache = null;

    /**
     * Loads the blocklist from the default .conf file.
     *
     * @param bool $useCache Whether to use cached data if available (default: true).
     * @return array<string> Array of disposable email domains.
     * @throws RuntimeException If the file cannot be read.
     */
    public static function loadBlocklist(bool $useCache = true): array
    {
        if ($useCache && self::$blocklistCache !== null) {
            return self::$blocklistCache;
        }

        $list = self::loadList(self::BLOCKLIST_PATH);

        if ($useCache) {
            self::$blocklistCache = $list;
        }

        return $list;
    }

    /**
     * Loads the allowlist from the default .conf file.
     *
     * @param bool $useCache Whether to use cached data if available (default: true).
     * @return array<string> Array of allowed email domains.
     * @throws RuntimeException If the file cannot be read.
     */
    public static function loadAllowlist(bool $useCache = true): array
    {
        if ($useCache && self::$allowlistCache !== null) {
            return self::$allowlistCache;
        }

        $list = self::loadList(self::ALLOWLIST_PATH);

        if ($useCache) {
            self::$allowlistCache = $list;
        }

        return $list;
    }

    /**
     * Loads a custom blocklist from a specified file path.
     *
     * @param string $filePath Path to the custom blocklist file.
     * @return array<string> Array of domains.
     * @throws RuntimeException If the file cannot be read.
     */
    public static function loadCustomBlocklist(string $filePath): array
    {
        return self::loadList($filePath);
    }

    /**
     * Loads a custom allowlist from a specified file path.
     *
     * @param string $filePath Path to the custom allowlist file.
     * @return array<string> Array of domains.
     * @throws RuntimeException If the file cannot be read.
     */
    public static function loadCustomAllowlist(string $filePath): array
    {
        return self::loadList($filePath);
    }

    /**
     * Loads both blocklist and allowlist at once.
     *
     * @param bool $useCache Whether to use cached data if available (default: true).
     * @return array{blocklist: array<string>, allowlist: array<string>}
     * @throws RuntimeException If any file cannot be read.
     */
    public static function loadAll(bool $useCache = true): array
    {
        return [
            'blocklist' => self::loadBlocklist($useCache),
            'allowlist' => self::loadAllowlist($useCache),
        ];
    }

    /**
     * Clears the cached blocklist and allowlist data.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$blocklistCache = null;
        self::$allowlistCache = null;
    }

    /**
     * Gets the path to the default blocklist file.
     *
     * @return string
     */
    public static function getBlocklistPath(): string
    {
        return self::BLOCKLIST_PATH;
    }

    /**
     * Gets the path to the default allowlist file.
     *
     * @return string
     */
    public static function getAllowlistPath(): string
    {
        return self::ALLOWLIST_PATH;
    }

    /**
     * Checks if the blocklist file exists.
     *
     * @return bool
     */
    public static function blocklistExists(): bool
    {
        return file_exists(self::BLOCKLIST_PATH);
    }

    /**
     * Checks if the allowlist file exists.
     *
     * @return bool
     */
    public static function allowlistExists(): bool
    {
        return file_exists(self::ALLOWLIST_PATH);
    }

    /**
     * Gets the count of domains in the blocklist without loading the full list.
     *
     * @return int
     * @throws RuntimeException If the file cannot be read.
     */
    public static function getBlocklistCount(): int
    {
        return count(self::loadBlocklist());
    }

    /**
     * Gets the count of domains in the allowlist without loading the full list.
     *
     * @return int
     * @throws RuntimeException If the file cannot be read.
     */
    public static function getAllowlistCount(): int
    {
        return count(self::loadAllowlist());
    }

    /**
     * Merges multiple list files into a single array.
     *
     * @param array<string> $filePaths Array of file paths to merge.
     * @return array<string> Merged and deduplicated array of domains.
     * @throws RuntimeException If any file cannot be read.
     */
    public static function mergeLists(array $filePaths): array
    {
        $merged = [];

        foreach ($filePaths as $filePath) {
            $list = self::loadList($filePath);
            $merged = array_merge($merged, $list);
        }

        return array_values(array_unique($merged));
    }

    /**
     * Saves a list of domains to a file.
     *
     * @param string $filePath Path to save the file.
     * @param array<string> $domains Array of domains to save.
     * @return bool True on success.
     * @throws RuntimeException If the file cannot be written.
     */
    public static function saveList(string $filePath, array $domains): bool
    {
        $domains = array_unique(array_map('strtolower', array_map('trim', $domains)));
        sort($domains, SORT_STRING);

        $content = implode(PHP_EOL, $domains);
        $result = file_put_contents($filePath, $content);

        if ($result === false) {
            throw new RuntimeException("Unable to write list file: {$filePath}");
        }

        return true;
    }

    /**
     * Reads the .conf file and returns an array of lines.
     *
     * @param string $filePath Path to the configuration file.
     * @return array<string> Array of cleaned domain strings.
     * @throws RuntimeException If the file does not exist or cannot be read.
     */
    private static function loadList(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("List file not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new RuntimeException("List file is not readable: {$filePath}");
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new RuntimeException("Failed to read list file: {$filePath}");
        }

        $domains = [];
        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments (lines starting with # or ;)
            if (empty($line) || $line[0] === '#' || $line[0] === ';') {
                continue;
            }

            $domains[] = strtolower($line);
        }

        return $domains;
    }
}
