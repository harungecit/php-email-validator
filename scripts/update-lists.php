<?php

/**
 * Updates data/blocklist.conf from the upstream disposable-email-domains repository.
 *
 * Usage:
 *   composer update-lists
 *   php scripts/update-lists.php [path-to-local-upstream-file]
 *
 * Merge strategy: union of the current blocklist and the upstream list, so
 * domains removed upstream are kept. Domains present in data/allowlist.conf
 * are always removed from the blocklist. The allowlist file is re-normalized
 * (lowercased, deduplicated, sorted) on every run.
 */

declare(strict_types=1);

use HarunGecit\EmailValidator\Fetcher;

require __DIR__ . '/../vendor/autoload.php';

const UPSTREAM_BLOCKLIST_URL = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/main/disposable_email_blocklist.conf';

/**
 * @return array<string>
 */
function fetchUpstreamBlocklist(?string $localFile): array
{
    if ($localFile !== null) {
        $content = file_get_contents($localFile);
        if ($content === false) {
            fwrite(STDERR, "Unable to read local file: {$localFile}" . PHP_EOL);
            exit(1);
        }
    } else {
        $context = stream_context_create([
            'http' => ['timeout' => 30, 'user_agent' => 'php-email-validator-list-updater'],
        ]);
        $content = @file_get_contents(UPSTREAM_BLOCKLIST_URL, false, $context);
        if ($content === false) {
            fwrite(STDERR, 'Unable to download upstream blocklist: ' . UPSTREAM_BLOCKLIST_URL . PHP_EOL);
            exit(1);
        }
    }

    $domains = [];
    foreach (preg_split('/\R/', $content) ?: [] as $line) {
        $line = strtolower(trim($line));
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }
        $domains[] = $line;
    }

    if (count($domains) < 1000) {
        fwrite(STDERR, 'Upstream list is suspiciously small (' . count($domains) . ' entries), aborting.' . PHP_EOL);
        exit(1);
    }

    return $domains;
}

$upstream = fetchUpstreamBlocklist($argv[1] ?? null);
$current = Fetcher::loadBlocklist(false);
$allowlist = Fetcher::loadAllowlist(false);

$merged = array_unique(array_merge($current, $upstream));
$final = array_values(array_diff($merged, $allowlist));

Fetcher::saveList(Fetcher::getBlocklistPath(), $final);
Fetcher::saveList(Fetcher::getAllowlistPath(), $allowlist);

$added = count(array_diff($upstream, $current));
$removedByAllowlist = count($merged) - count($final);

echo 'Upstream entries:      ' . count($upstream) . PHP_EOL;
echo 'Previous blocklist:    ' . count($current) . PHP_EOL;
echo 'New domains added:     ' . $added . PHP_EOL;
echo 'Allowlist conflicts:   ' . $removedByAllowlist . PHP_EOL;
echo 'Final blocklist:       ' . count($final) . PHP_EOL;
