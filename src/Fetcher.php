<?php

namespace HarunGecit\EmailValidator;

use HarunGecit\EmailValidator\Support\ListParser;
use RuntimeException;

/**
 * Class Fetcher
 *
 * Utility class for loading blocklist and allowlist data from configuration files.
 * Supports loading from default package files or custom file paths.
 *
 * The bundled blocklist is large (100k+ domains), so it is loaded as a lookup
 * index (domain => value) rather than a list:
 *
 * - When OPcache is active, the precompiled data/blocklist.php is included. Its
 *   array is immutable and lives in shared memory, so loading costs nothing and
 *   uses no per-request memory.
 * - Otherwise data/blocklist.conf is read with a single explode()/array_flip(),
 *   which is several times faster than compiling the PHP file without OPcache.
 *
 * @package HarunGecit\EmailValidator
 * @author Harun Geçit <info@harungecit.com>
 * @link https://github.com/harungecit
 * @license MIT
 * @version 3.2.0
 */
class Fetcher
{
    /**
     * @var string Path to the default blocklist file
     */
    private const BLOCKLIST_PATH = __DIR__ . '/../data/blocklist.conf';

    /**
     * @var string Path to the precompiled blocklist index (generated from blocklist.conf)
     */
    private const BLOCKLIST_INDEX_PATH = __DIR__ . '/../data/blocklist.php';

    /**
     * @var string Path to the default allowlist file
     */
    private const ALLOWLIST_PATH = __DIR__ . '/../data/allowlist.conf';

    /**
     * @var string Path to the metadata of the bundled lists (build date, sources)
     */
    private const METADATA_PATH = __DIR__ . '/../data/metadata.json';

    /**
     * @var array<string> File extensions picked up when a directory is given as a list path
     */
    private const LIST_EXTENSIONS = ['conf', 'txt', 'list', 'json'];

    /**
     * @var array<string>|null Cached blocklist data
     */
    private static ?array $blocklistCache = null;

    /**
     * @var array<string>|null Cached allowlist data
     */
    private static ?array $allowlistCache = null;

    /**
     * @var array<string, int|bool>|null Cached blocklist lookup index
     */
    private static ?array $blocklistIndexCache = null;

    /**
     * @var array<string, int|bool>|null Cached allowlist lookup index
     */
    private static ?array $allowlistIndexCache = null;

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

        $list = array_map('strval', array_keys(self::loadBlocklistIndex($useCache)));

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
     * Loads the bundled blocklist as a lookup index (domain => truthy value).
     *
     * Use isset($index[$domain]) for O(1) lookups.
     *
     * @param bool $useCache Whether to use cached data if available (default: true).
     * @return array<string, int|bool>
     * @throws RuntimeException If the file cannot be read.
     */
    public static function loadBlocklistIndex(bool $useCache = true): array
    {
        if ($useCache && self::$blocklistIndexCache !== null) {
            return self::$blocklistIndexCache;
        }

        $index = null;

        if (self::isOpcacheActive() && is_file(self::BLOCKLIST_INDEX_PATH)) {
            $compiled = require self::BLOCKLIST_INDEX_PATH;
            if (is_array($compiled) && $compiled !== []) {
                $index = $compiled;
            }
        }

        if ($index === null) {
            $index = self::loadNormalizedIndex(self::BLOCKLIST_PATH);
        }

        if ($useCache) {
            self::$blocklistIndexCache = $index;
        }

        return $index;
    }

    /**
     * Loads the bundled allowlist as a lookup index (domain => truthy value).
     *
     * @param bool $useCache Whether to use cached data if available (default: true).
     * @return array<string, int|bool>
     * @throws RuntimeException If the file cannot be read.
     */
    public static function loadAllowlistIndex(bool $useCache = true): array
    {
        if ($useCache && self::$allowlistIndexCache !== null) {
            return self::$allowlistIndexCache;
        }

        $index = array_flip(self::loadAllowlist($useCache));

        if ($useCache) {
            self::$allowlistIndexCache = $index;
        }

        return $index;
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
     * Loads and merges domain lists from files and/or directories.
     *
     * Directories are scanned (non-recursively) for *.conf, *.txt, *.list and
     * *.json files. Text and JSON formats are both supported; entries are
     * normalized and invalid ones are skipped.
     *
     * @param array<string> $paths Files or directories.
     * @param bool $ignoreMissing Skip paths that do not exist instead of throwing.
     * @return array<string> Unique, normalized domains.
     * @throws RuntimeException If a path cannot be read.
     */
    public static function loadPaths(array $paths, bool $ignoreMissing = false): array
    {
        $domains = [];

        foreach ($paths as $path) {
            foreach (self::expandPath((string) $path, $ignoreMissing) as $file) {
                $parsed = ListParser::parseFile($file);
                if ($parsed === null) {
                    throw new RuntimeException("List file is not readable: {$file}");
                }
                foreach ($parsed as $domain) {
                    $domains[$domain] = true;
                }
            }
        }

        return array_keys($domains);
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
        self::$blocklistIndexCache = null;
        self::$allowlistIndexCache = null;
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
     * Gets the path to the precompiled blocklist index.
     *
     * @return string
     */
    public static function getBlocklistIndexPath(): string
    {
        return self::BLOCKLIST_INDEX_PATH;
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
     * Gets the path to the bundled list metadata file.
     *
     * @return string
     */
    public static function getMetadataPath(): string
    {
        return self::METADATA_PATH;
    }

    /**
     * Returns metadata about the bundled lists: when they were built, which
     * upstream sources were merged, and how many entries were filtered out.
     *
     * @return array<string, mixed> Empty array if no metadata is available.
     */
    public static function getMetadata(): array
    {
        if (!is_file(self::METADATA_PATH)) {
            return [];
        }

        $content = file_get_contents(self::METADATA_PATH);
        $decoded = $content === false ? null : json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
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
     * Gets the count of domains in the blocklist.
     *
     * @return int
     * @throws RuntimeException If the file cannot be read.
     */
    public static function getBlocklistCount(): int
    {
        return count(self::loadBlocklistIndex());
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

        // POSIX text file: one domain per line, each line (including the last) terminated by LF,
        // so appending a domain with `echo domain >> file` never merges two lines.
        $content = $domains === [] ? '' : implode("\n", $domains) . "\n";
        $result = file_put_contents($filePath, $content);

        if ($result === false) {
            throw new RuntimeException("Unable to write list file: {$filePath}");
        }

        return true;
    }

    /**
     * Writes a precompiled PHP lookup index for a list of domains.
     *
     * The generated file returns array<string, int> and is meant to be
     * included with OPcache enabled (see loadBlocklistIndex()).
     *
     * @param string $filePath Path of the PHP file to write.
     * @param array<string> $domains Normalized domains.
     * @return bool True on success.
     * @throws RuntimeException If the file cannot be written.
     */
    public static function compileIndex(string $filePath, array $domains): bool
    {
        $domains = array_values(array_unique($domains));
        sort($domains, SORT_STRING);

        $lines = ["<?php\n", '// Generated from blocklist.conf by scripts/update-lists.php. Do not edit.', 'return ['];
        foreach ($domains as $domain) {
            $lines[] = var_export((string) $domain, true) . '=>1,';
        }
        $lines[] = "];\n";

        if (file_put_contents($filePath, implode("\n", $lines)) === false) {
            throw new RuntimeException("Unable to write index file: {$filePath}");
        }

        return true;
    }

    /**
     * Whether OPcache will cache included files for the current SAPI.
     *
     * On the CLI the OPcache shared memory dies with the process, so the
     * compiled index only pays off when the file cache is configured.
     *
     * @return bool
     */
    public static function isOpcacheActive(): bool
    {
        if (!extension_loaded('Zend OPcache')) {
            return false;
        }

        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOLEAN)
                && (string) ini_get('opcache.file_cache') !== '';
        }

        return filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Reads a list file that is already normalized (one lowercase domain per
     * line, LF endings, no comments) — the format written by saveList() — with
     * a single explode(). Falls back to the tolerant parser otherwise.
     *
     * @param string $filePath Path to the list file.
     * @return array<string, int>
     * @throws RuntimeException If the file does not exist or cannot be read.
     */
    private static function loadNormalizedIndex(string $filePath): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException("List file not found or not readable: {$filePath}");
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new RuntimeException("Failed to read list file: {$filePath}");
        }

        $content = trim($content);

        if ($content === '') {
            return [];
        }

        if (strpbrk($content, "\r#; ABCDEFGHIJKLMNOPQRSTUVWXYZ") !== false) {
            return array_flip(self::loadList($filePath));
        }

        return array_flip(explode("\n", $content));
    }

    /**
     * Expands a list path to the files it refers to.
     *
     * @param string $path File or directory.
     * @param bool $ignoreMissing Return nothing for a missing path instead of throwing.
     * @return array<string>
     * @throws RuntimeException If the path does not exist.
     */
    private static function expandPath(string $path, bool $ignoreMissing): array
    {
        if (is_dir($path)) {
            $files = [];
            foreach (scandir($path) ?: [] as $entry) {
                $file = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $entry;
                $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (is_file($file) && in_array($extension, self::LIST_EXTENSIONS, true)) {
                    $files[] = $file;
                }
            }
            sort($files, SORT_STRING);
            return $files;
        }

        if (is_file($path)) {
            return [$path];
        }

        if ($ignoreMissing) {
            return [];
        }

        throw new RuntimeException("List file not found: {$path}");
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
