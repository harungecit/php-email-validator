<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Sync;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\Exceptions\ConfigurationException;
use HarunGecit\EmailValidator\Fetcher;
use HarunGecit\EmailValidator\Support\Domain;
use HarunGecit\EmailValidator\Support\ListParser;
use RuntimeException;
use Throwable;

/**
 * Class ListSynchronizer
 *
 * Keeps an application's disposable-domain list current between package
 * releases by downloading remote lists (`sync.urls`, plain text or JSON) into
 * the file configured as `lists.synced_blocklist_path`. EmailValidator blocks
 * the domains in that file in addition to the bundled list.
 *
 * Safeguards — the last good list is never damaged by a bad download:
 * - failed requests, non-2xx responses, HTML pages and responses without a
 *   single valid domain are rejected before anything is written;
 * - by default the run is append-only; replace mode only drops domains when
 *   every source was downloaded successfully;
 * - allowlisted domains (and their subdomains) are never written;
 * - only domains that are not already in the base blocklist are stored, so the
 *   file stays small and cheap to load;
 * - the file is written to a temporary file and atomically renamed, under an
 *   exclusive lock, and left untouched when nothing changed.
 *
 * @package HarunGecit\EmailValidator\Sync
 */
class ListSynchronizer
{
    private HttpDownloader $downloader;

    /**
     * @param Configuration $config Configuration providing the target path, URLs and lists.
     * @param HttpDownloader|null $downloader HTTP client (injectable for tests).
     */
    public function __construct(private Configuration $config, ?HttpDownloader $downloader = null)
    {
        $this->downloader = $downloader ?? new HttpDownloader();
    }

    /**
     * Downloads all configured sources and updates the synced list.
     *
     * @param bool $replace Rebuild the list from the downloaded sources instead of appending to it.
     * @return SyncResult
     * @throws ConfigurationException If no target path or no source URL is configured.
     * @throws RuntimeException If the target file cannot be written.
     */
    public function sync(bool $replace = false): SyncResult
    {
        $path = $this->config->getSyncedBlocklistPath();
        if ($path === null) {
            throw ConfigurationException::missingRequired('lists.synced_blocklist_path');
        }

        $urls = $this->config->getSyncUrls();
        if ($urls === []) {
            throw ConfigurationException::missingRequired('sync.urls');
        }

        $sources = [];
        $downloaded = [];

        foreach ($urls as $url) {
            try {
                $domains = $this->download($url);
                $sources[$url] = ['domains' => count($domains), 'error' => null];
                foreach ($domains as $domain) {
                    $downloaded[$domain] = true;
                }
            } catch (Throwable $e) {
                $sources[$url] = ['domains' => 0, 'error' => $e->getMessage()];
            }
        }

        $succeeded = count(array_filter($sources, static fn (array $source): bool => $source['error'] === null));

        if ($succeeded === 0) {
            $existing = ListParser::parseFile($path) ?? [];
            return new SyncResult($path, false, false, 0, 0, count($existing), $sources);
        }

        $replace = $replace && $succeeded === count($urls);

        return $this->write($path, $downloaded, $replace, $sources);
    }

    /**
     * Downloads and parses one source.
     *
     * @param string $url
     * @return array<string>
     * @throws RuntimeException If the response is not a usable domain list.
     */
    protected function download(string $url): array
    {
        $body = $this->downloader->get($url, $this->config->getSyncTimeout());

        if (trim($body) === '') {
            throw new RuntimeException('Empty response');
        }

        if (ListParser::looksLikeHtml($body)) {
            throw new RuntimeException('Response is an HTML page, not a domain list');
        }

        $domains = ListParser::parse($body);

        if ($domains === []) {
            throw new RuntimeException('Response does not contain any valid domain');
        }

        return $domains;
    }

    /**
     * Merges, filters and atomically writes the synced list.
     *
     * @param string $path
     * @param array<string, bool> $downloaded
     * @param bool $replace
     * @param array<string, array{domains: int, error: string|null}> $sources
     * @return SyncResult
     */
    private function write(string $path, array $downloaded, bool $replace, array $sources): SyncResult
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create directory: {$directory}");
        }

        $lock = fopen($path . '.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException("Unable to open lock file: {$path}.lock");
        }

        try {
            flock($lock, LOCK_EX);

            $existing = array_fill_keys(ListParser::parseFile($path) ?? [], true);
            $candidates = $replace ? $downloaded : $existing + $downloaded;

            $base = $this->baseBlocklist();
            $allowed = $this->allowlist();

            $final = [];
            foreach (array_keys($candidates) as $domain) {
                $domain = (string) $domain;
                if (!isset($base[$domain]) && !$this->isAllowed($domain, $allowed)) {
                    $final[$domain] = true;
                }
            }

            $added = count(array_diff_key($final, $existing));
            $removed = count(array_diff_key($existing, $final));
            $changed = $added > 0 || $removed > 0 || !is_file($path);

            if ($changed) {
                $domains = array_map('strval', array_keys($final));
                sort($domains, SORT_STRING);
                $this->atomicWrite($path, $domains === [] ? '' : implode("\n", $domains) . "\n");
            }

            return new SyncResult($path, true, $changed, $added, $removed, count($final), $sources, $replace);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return array<string, int|bool>
     */
    private function baseBlocklist(): array
    {
        $path = $this->config->getBlocklistPath();

        return $path !== null ? array_flip(Fetcher::loadCustomBlocklist($path)) : Fetcher::loadBlocklistIndex();
    }

    /**
     * @return array<string, int|bool>
     */
    private function allowlist(): array
    {
        $path = $this->config->getAllowlistPath();
        $allowed = $path !== null ? array_flip(Fetcher::loadCustomAllowlist($path)) : Fetcher::loadAllowlistIndex();

        $extra = array_merge(
            $this->config->getCustomAllowlist(),
            Fetcher::loadPaths($this->config->getCustomAllowlistPaths(), true)
        );
        foreach ($extra as $domain) {
            $allowed[$domain] = true;
        }

        return $allowed;
    }

    /**
     * Whether a domain or one of its parents is allowlisted.
     *
     * @param string $domain
     * @param array<string, int|bool> $allowed
     * @return bool
     */
    private function isAllowed(string $domain, array $allowed): bool
    {
        foreach (Domain::withParents($domain) as $candidate) {
            if (isset($allowed[$candidate])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Writes a file atomically: temporary file in the same directory, then rename.
     */
    private function atomicWrite(string $path, string $content): void
    {
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (file_put_contents($temporary, $content) === false) {
            throw new RuntimeException("Unable to write temporary file: {$temporary}");
        }

        if (!@rename($temporary, $path)) {
            // Windows cannot rename over an existing file that another process holds open.
            if (!@copy($temporary, $path)) {
                @unlink($temporary);
                throw new RuntimeException("Unable to replace synced list: {$path}");
            }
            @unlink($temporary);
        }
    }
}
