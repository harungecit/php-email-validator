<?php

/**
 * Rebuilds data/blocklist.conf (and its compiled index data/blocklist.php) from
 * several upstream disposable-domain projects.
 *
 * Usage:
 *   composer update-lists
 *   php scripts/update-lists.php [--force] [--source=NAME=PATH_OR_URL ...]
 *                                [--psl=PATH_OR_URL] [--popular=PATH_OR_URL]
 *
 * Build strategy
 * --------------
 *   blocklist = trusted sources
 *             + untrusted sources (after the false-positive filters below)
 *             + data/blocklist.local.conf
 *             - data/allowlist.conf (and subdomains of allowlisted domains)
 *
 * Sources are either *trusted* (hand-curated, taken as-is) or *untrusted*
 * (large aggregators that also pull in spam-reputation feeds and therefore
 * contain some legitimate domains). Every upstream entry is normalized and
 * dropped when it:
 *   - is not a syntactically valid domain;
 *   - is a public suffix (e.g. "msk.ru", "pp.ua") — with parent-domain
 *     matching such an entry would block every domain below it;
 *   - is allowlisted, or is a subdomain of an allowlisted domain.
 * Entries that only an untrusted source lists are additionally dropped when
 * they are:
 *   - among the most popular registrable domains (Tranco top list), which
 *     catches companies, ISPs and portals mislabelled as disposable;
 *   - a known typo of a major provider (e.g. "gamil.com") — those are handled
 *     by the typo suggester, a "disposable" error would mislead the user;
 *   - a concatenation artifact: two listed domains glued together because an
 *     upstream merged files without trailing newlines ("a.com" + "b.net" →
 *     "a.comb.net").
 * data/blocklist.local.conf is the escape hatch: its entries bypass the
 * popularity and typo filters (but never the allowlist).
 *
 * Safety (the script runs unattended in CI)
 * -----------------------------------------
 *   - a trusted source, the Public Suffix List or the popularity list failing
 *     to download (or being suspiciously small) aborts the run;
 *   - an untrusted source failing switches the run to additive mode: nothing is
 *     removed, so a temporary outage never shrinks the list;
 *   - the run aborts if the blocklist would shrink by more than MAX_SHRINK_RATIO
 *     (override with --force);
 *   - files are only rewritten when their content changes, so an unchanged run
 *     leaves the working tree clean.
 */

declare(strict_types=1);

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\Fetcher;
use HarunGecit\EmailValidator\Support\Domain;
use HarunGecit\EmailValidator\Support\ListParser;

require __DIR__ . '/../vendor/autoload.php';

// Several 100k+ entry sets (sources, popularity list, current list) are held at once.
ini_set('memory_limit', '1G');

const SOURCES = [
    'disposable-email-domains' => [
        'url' => 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/main/disposable_email_blocklist.conf',
        'homepage' => 'https://github.com/disposable-email-domains/disposable-email-domains',
        'trusted' => true,
        'min_entries' => 1000,
    ],
    'eramitgupta/disposable-email' => [
        'url' => 'https://raw.githubusercontent.com/eramitgupta/disposable-email/main/disposable_email.txt',
        'homepage' => 'https://github.com/eramitgupta/disposable-email',
        'trusted' => false,
        'min_entries' => 10000,
    ],
];

const PUBLIC_SUFFIX_URL = 'https://publicsuffix.org/list/public_suffix_list.dat';
const POPULAR_DOMAINS_URL = 'https://tranco-list.eu/top-1m.csv.zip';
const POPULAR_DOMAINS_LIMIT = 100000;
const LOCAL_BLOCKLIST_PATH = __DIR__ . '/../data/blocklist.local.conf';
const MAX_SHRINK_RATIO = 0.05;

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function warn(string $message): void
{
    fwrite(STDERR, 'Warning: ' . $message . PHP_EOL);
}

/**
 * Reads a local file or downloads a URL.
 */
function fetch(string $location, int $timeout = 60): ?string
{
    if (preg_match('#^https?://#i', $location) !== 1) {
        $content = @file_get_contents($location);
        return $content === false ? null : $content;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'user_agent' => 'php-email-validator-list-updater (+https://github.com/harungecit/php-email-validator)',
            'follow_location' => 1,
        ],
    ]);

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $content = @file_get_contents($location, false, $context);
        if ($content !== false && $content !== '') {
            return $content;
        }
        sleep($attempt * 2);
    }

    return null;
}

/**
 * @return array<string, true> Public suffixes (wildcard and exception markers stripped).
 */
function loadPublicSuffixes(string $location): array
{
    $content = fetch($location) ?? fail("Unable to download the Public Suffix List: {$location}");
    $suffixes = [];

    foreach (preg_split('/\R/', $content) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '//')) {
            continue;
        }
        $line = ltrim(preg_replace('/^[!*]\.?/', '', $line) ?? $line, '.');
        $suffix = Domain::normalize($line);
        if ($suffix !== null && str_contains($suffix, '.')) {
            $suffixes[$suffix] = true;
        }
    }

    if (count($suffixes) < 1000) {
        fail('The Public Suffix List is suspiciously small (' . count($suffixes) . ' multi-label entries), aborting.');
    }

    return $suffixes;
}

/**
 * @return array<string, true> The POPULAR_DOMAINS_LIMIT most popular domains.
 */
function loadPopularDomains(string $location): array
{
    $content = fetch($location, 120) ?? fail("Unable to download the popular domains list: {$location}");

    if (str_starts_with($content, "PK\x03\x04")) {
        if (!class_exists(ZipArchive::class)) {
            fail('ext-zip is required to read the popular domains list.');
        }
        $temporary = tempnam(sys_get_temp_dir(), 'tranco');
        file_put_contents($temporary, $content);
        $zip = new ZipArchive();
        if ($zip->open($temporary) !== true || $zip->numFiles < 1) {
            fail('Unable to open the popular domains archive.');
        }
        $content = (string) $zip->getFromIndex(0);
        $zip->close();
        unlink($temporary);
    }

    // Walk the (1M-line) list line by line and stop at the limit instead of splitting it all.
    $popular = [];
    for ($line = strtok($content, "
"); $line !== false; $line = strtok("
")) {
        // Tranco format: "rank,domain"
        $columns = explode(',', trim($line));
        $domain = strtolower((string) end($columns));
        if ($domain !== '') {
            $popular[$domain] = true;
        }
        if (count($popular) >= POPULAR_DOMAINS_LIMIT) {
            break;
        }
    }

    if (count($popular) < POPULAR_DOMAINS_LIMIT / 2) {
        fail('The popular domains list is suspiciously small (' . count($popular) . ' entries), aborting.');
    }

    return $popular;
}

/**
 * @param array<string, int|bool> $allowlist
 */
function isAllowlisted(string $domain, array $allowlist): bool
{
    foreach (Domain::withParents($domain) as $candidate) {
        if (isset($allowlist[$candidate])) {
            return true;
        }
    }

    return false;
}

/**
 * Detects two listed domains glued together ("a.com" + "b.net" = "a.comb.net").
 *
 * @param array<string, mixed> $known
 */
function isConcatenationArtifact(string $domain, array $known): bool
{
    $length = strlen($domain);

    for ($i = 4; $i < $length - 3; $i++) {
        if (isset($known[substr($domain, 0, $i)]) && isset($known[substr($domain, $i)])) {
            return true;
        }
    }

    return false;
}

function writeIfChanged(string $path, string $content): bool
{
    if (is_file($path) && file_get_contents($path) === $content) {
        return false;
    }

    file_put_contents($path, $content);

    return true;
}

// ---------------------------------------------------------------------------
// Arguments
// ---------------------------------------------------------------------------

$force = false;
$overrides = [];
$pslLocation = PUBLIC_SUFFIX_URL;
$popularLocation = POPULAR_DOMAINS_URL;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--force') {
        $force = true;
    } elseif (str_starts_with($arg, '--source=')) {
        [$name, $location] = array_pad(explode('=', substr($arg, 9), 2), 2, '');
        if (!isset(SOURCES[$name]) || $location === '') {
            fail("Unknown source or missing location in {$arg}. Sources: " . implode(', ', array_keys(SOURCES)));
        }
        $overrides[$name] = $location;
    } elseif (str_starts_with($arg, '--psl=')) {
        $pslLocation = substr($arg, 6);
    } elseif (str_starts_with($arg, '--popular=')) {
        $popularLocation = substr($arg, 10);
    } else {
        fail("Unknown argument: {$arg}");
    }
}

// ---------------------------------------------------------------------------
// Download sources and filter lists
// ---------------------------------------------------------------------------

$downloaded = [];
$sourceStats = [];
$additive = false;

foreach (SOURCES as $name => $source) {
    $location = $overrides[$name] ?? $source['url'];
    $invalid = 0;
    $content = fetch($location);
    $domains = $content === null || ListParser::looksLikeHtml($content) ? [] : ListParser::parse($content, $invalid);

    if (count($domains) < $source['min_entries']) {
        $reason = $content === null ? 'download failed' : 'only ' . count($domains) . ' valid entries';
        if ($source['trusted']) {
            fail("Trusted source {$name} is unusable ({$reason}), aborting.");
        }
        warn("Untrusted source {$name} is unusable ({$reason}); running in additive mode (nothing is removed).");
        $additive = true;
        $sourceStats[$name] = ['entries' => 0, 'error' => $reason];
        continue;
    }

    $downloaded[$name] = $domains;
    $sourceStats[$name] = ['entries' => count($domains), 'invalid' => $invalid];
}

$publicSuffixes = loadPublicSuffixes($pslLocation);
$popular = loadPopularDomains($popularLocation);
$allowlist = array_flip(Fetcher::loadAllowlist(false));
$typoDomains = array_flip(array_keys((new Configuration())->getTypoMappings()));
$local = file_exists(LOCAL_BLOCKLIST_PATH) ? Fetcher::loadCustomBlocklist(LOCAL_BLOCKLIST_PATH) : [];
$current = Fetcher::loadBlocklist(false);

$trusted = [];
foreach ($downloaded as $name => $domains) {
    if (SOURCES[$name]['trusted']) {
        foreach ($domains as $domain) {
            $trusted[$domain] = true;
        }
    }
}

$known = [];
foreach ($downloaded as $domains) {
    foreach ($domains as $domain) {
        $known[$domain] = true;
    }
}

$filtered = ['public_suffix' => 0, 'allowlisted' => 0, 'popular' => 0, 'typo' => 0, 'artifact' => 0];
$final = [];
$included = array_fill_keys(array_keys($downloaded), 0);

foreach ($downloaded as $name => $domains) {
    foreach ($domains as $domain) {
        if (isset($final[$domain])) {
            $included[$name]++;
            continue;
        }
        if (isset($publicSuffixes[$domain])) {
            $filtered['public_suffix']++;
            continue;
        }
        if (isAllowlisted($domain, $allowlist)) {
            $filtered['allowlisted']++;
            continue;
        }
        if (!isset($trusted[$domain])) {
            if (isset($popular[$domain])) {
                $filtered['popular']++;
                continue;
            }
            if (isset($typoDomains[$domain])) {
                $filtered['typo']++;
                continue;
            }
            if (isConcatenationArtifact($domain, $known)) {
                $filtered['artifact']++;
                continue;
            }
        }
        $final[$domain] = true;
        $included[$name]++;
    }
}

foreach ($local as $domain) {
    if (!isset($allowlist[$domain])) {
        $final[$domain] = true;
    }
}

if ($additive) {
    foreach ($current as $domain) {
        if (!isset($allowlist[$domain]) && !isset($publicSuffixes[$domain])) {
            $final[$domain] = true;
        }
    }
}

$final = array_map('strval', array_keys($final));
sort($final, SORT_STRING);

$currentIndex = array_flip($current);
$finalIndex = array_flip($final);
$added = array_keys(array_diff_key($finalIndex, $currentIndex));
$removed = array_keys(array_diff_key($currentIndex, $finalIndex));

if (!$force && count($current) > 0 && count($removed) > count($current) * MAX_SHRINK_RATIO) {
    fail(
        'Refusing to remove ' . count($removed) . ' of ' . count($current)
        . ' domains in one run (limit: ' . (MAX_SHRINK_RATIO * 100) . '%). Re-run with --force if this is expected.'
    );
}

// ---------------------------------------------------------------------------
// Write
// ---------------------------------------------------------------------------

$changed = writeIfChanged(Fetcher::getBlocklistPath(), implode("\n", $final) . "\n");

$allowlistDomains = array_map('strval', array_keys($allowlist));
sort($allowlistDomains, SORT_STRING);
writeIfChanged(Fetcher::getAllowlistPath(), implode("\n", $allowlistDomains) . "\n");

if ($local !== []) {
    $localDomains = array_values(array_unique($local));
    sort($localDomains, SORT_STRING);
    writeIfChanged(LOCAL_BLOCKLIST_PATH, implode("\n", $localDomains) . "\n");
}

$indexPath = Fetcher::getBlocklistIndexPath();
if ($changed || !is_file($indexPath)) {
    Fetcher::compileIndex($indexPath, $final);
}

if ($changed || !is_file(Fetcher::getMetadataPath())) {
    $sources = [];
    foreach (SOURCES as $name => $source) {
        $sources[$name] = [
            'url' => $source['url'],
            'homepage' => $source['homepage'],
            'trusted' => $source['trusted'],
            'entries' => $sourceStats[$name]['entries'],
            'included' => $included[$name] ?? 0,
        ];
    }

    $metadata = [
        'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'total' => count($final),
        'allowlist' => count($allowlistDomains),
        'local_additions' => count($local),
        'sources' => $sources,
        'filtered' => $filtered,
        'popular_domains_limit' => POPULAR_DOMAINS_LIMIT,
        'additive_run' => $additive,
    ];

    file_put_contents(
        Fetcher::getMetadataPath(),
        json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

foreach (SOURCES as $name => $source) {
    $stats = $sourceStats[$name];
    printf(
        "%-30s %s%s\n",
        $name . ($source['trusted'] ? ' (trusted)' : ''),
        isset($stats['error']) ? 'FAILED: ' . $stats['error'] : number_format($stats['entries']) . ' entries',
        isset($stats['error']) ? '' : ', ' . number_format($included[$name]) . ' included'
    );
}
echo 'Local additions:               ' . count($local) . PHP_EOL;
echo 'Filtered (public suffix):      ' . $filtered['public_suffix'] . PHP_EOL;
echo 'Filtered (allowlisted):        ' . $filtered['allowlisted'] . PHP_EOL;
echo 'Filtered (popular, untrusted): ' . $filtered['popular'] . PHP_EOL;
echo 'Filtered (typo, untrusted):    ' . $filtered['typo'] . PHP_EOL;
echo 'Filtered (glued, untrusted):   ' . $filtered['artifact'] . PHP_EOL;
echo 'Previous blocklist:            ' . count($current) . PHP_EOL;
echo 'Domains added:                 ' . count($added) . PHP_EOL;
echo 'Domains removed:               ' . count($removed) . PHP_EOL;
echo 'Final blocklist:               ' . count($final) . PHP_EOL;
echo 'Mode:                          ' . ($additive ? 'additive (a source failed)' : 'full rebuild') . PHP_EOL;

if (count($removed) > 0 && count($removed) <= 50) {
    echo 'Removed: ' . implode(', ', $removed) . PHP_EOL;
}
