<?php

/**
 * Rebuilds data/blocklist.conf from the upstream disposable-email-domains repository.
 *
 * Usage:
 *   composer update-lists
 *   php scripts/update-lists.php [--force] [path-to-local-upstream-file]
 *
 * Build strategy: blocklist = upstream + data/blocklist.local.conf - data/allowlist.conf.
 * Domains removed upstream (usually false-positive fixes) are removed here too;
 * domains that must stay blocked regardless of upstream belong in
 * data/blocklist.local.conf. Domains present in data/allowlist.conf are never
 * blocked. The local and allowlist files are re-normalized (lowercased,
 * deduplicated, sorted) on every run.
 *
 * Safety checks (the script runs unattended in CI): malformed upstream lines are
 * skipped, and the run aborts if upstream is suspiciously small or the blocklist
 * would shrink by more than MAX_SHRINK_RATIO. Pass --force to skip the shrink check.
 */

declare(strict_types=1);

use HarunGecit\EmailValidator\Fetcher;

require __DIR__ . '/../vendor/autoload.php';

const UPSTREAM_BLOCKLIST_URL = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/main/disposable_email_blocklist.conf';
const LOCAL_BLOCKLIST_PATH = __DIR__ . '/../data/blocklist.local.conf';
const MIN_UPSTREAM_ENTRIES = 1000;
const MAX_SHRINK_RATIO = 0.05;
const DOMAIN_PATTERN = '/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/';

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
    $skipped = 0;
    foreach (preg_split('/\R/', $content) ?: [] as $line) {
        $line = strtolower(trim($line));
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }
        if (preg_match(DOMAIN_PATTERN, $line) !== 1) {
            $skipped++;
            continue;
        }
        $domains[] = $line;
    }

    if ($skipped > 0) {
        fwrite(STDERR, "Skipped {$skipped} malformed upstream line(s)." . PHP_EOL);
    }

    if (count($domains) < MIN_UPSTREAM_ENTRIES) {
        fwrite(STDERR, 'Upstream list is suspiciously small (' . count($domains) . ' entries), aborting.' . PHP_EOL);
        exit(1);
    }

    return array_values(array_unique($domains));
}

$args = array_slice($argv, 1);
$force = in_array('--force', $args, true);
$args = array_values(array_diff($args, ['--force']));

$upstream = fetchUpstreamBlocklist($args[0] ?? null);
$current = Fetcher::loadBlocklist(false);
$allowlist = Fetcher::loadAllowlist(false);
$local = file_exists(LOCAL_BLOCKLIST_PATH) ? Fetcher::loadCustomBlocklist(LOCAL_BLOCKLIST_PATH) : [];

$merged = array_unique(array_merge($upstream, $local));
$final = array_values(array_diff($merged, $allowlist));

$added = array_diff($final, $current);
$removed = array_diff($current, $final);

if (!$force && count($current) > 0 && count($removed) > count($current) * MAX_SHRINK_RATIO) {
    fwrite(
        STDERR,
        'Refusing to remove ' . count($removed) . ' of ' . count($current)
        . ' domains in one run (limit: ' . (MAX_SHRINK_RATIO * 100) . '%). Re-run with --force if this is expected.' . PHP_EOL
    );
    exit(1);
}

Fetcher::saveList(Fetcher::getBlocklistPath(), $final);
Fetcher::saveList(Fetcher::getAllowlistPath(), $allowlist);
if ($local !== []) {
    Fetcher::saveList(LOCAL_BLOCKLIST_PATH, $local);
}

echo 'Upstream entries:      ' . count($upstream) . PHP_EOL;
echo 'Local additions:       ' . count($local) . PHP_EOL;
echo 'Previous blocklist:    ' . count($current) . PHP_EOL;
echo 'Domains added:         ' . count($added) . PHP_EOL;
echo 'Domains removed:       ' . count($removed) . PHP_EOL;
echo 'Allowlist conflicts:   ' . (count($merged) - count($final)) . PHP_EOL;
echo 'Final blocklist:       ' . count($final) . PHP_EOL;

if (count($removed) > 0 && count($removed) <= 50) {
    echo 'Removed: ' . implode(', ', $removed) . PHP_EOL;
}
