<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Console;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Sync\ListSynchronizer;
use Throwable;

/**
 * Class Cli
 *
 * Framework-agnostic command line interface (`vendor/bin/email-validator`).
 *
 *   email-validator check <email|domain>...   Explain disposable-domain lookups
 *   email-validator validate <email>...       Run the full validation pipeline
 *   email-validator sync                      Download remote lists into the synced list
 *   email-validator stats                     Show list statistics
 *
 * Exit codes: 0 = success / nothing flagged, 1 = something flagged or failed,
 * 2 = usage or configuration error.
 *
 * @package HarunGecit\EmailValidator\Console
 */
final class Cli
{
    public const EXIT_OK = 0;
    public const EXIT_FLAGGED = 1;
    public const EXIT_USAGE = 2;

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    /**
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public function __construct($stdout = null, $stderr = null)
    {
        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
    }

    /**
     * Runs the CLI.
     *
     * @param array<string> $argv Arguments including the script name.
     * @return int Exit code.
     */
    public function run(array $argv): int
    {
        [$command, $arguments, $options] = $this->parse(array_slice($argv, 1));

        if (isset($options['version'])) {
            $this->out('php-email-validator ' . self::version());
            return self::EXIT_OK;
        }

        if ($command === null || $command === 'help' || isset($options['help'])) {
            $this->out($this->usage());
            return $command === null && !isset($options['help']) ? self::EXIT_USAGE : self::EXIT_OK;
        }

        try {
            $config = $this->configuration($options);

            switch ($command) {
                case 'check':
                    return $this->check($config, $arguments, isset($options['json']));
                case 'validate':
                    return $this->validate($config, $arguments, $options);
                case 'sync':
                    return $this->sync($config, $options);
                case 'stats':
                    return $this->stats($config, isset($options['json']));
                default:
                    $this->err("Unknown command \"{$command}\".\n");
                    $this->out($this->usage());
                    return self::EXIT_USAGE;
            }
        } catch (Throwable $e) {
            $this->err('Error: ' . $e->getMessage());
            return self::EXIT_USAGE;
        }
    }

    /**
     * @param array<string> $inputs
     */
    private function check(Configuration $config, array $inputs, bool $json): int
    {
        if ($inputs === []) {
            $this->err('Usage: email-validator check <email|domain>...');
            return self::EXIT_USAGE;
        }

        $validator = new EmailValidator($config);
        $results = [];
        $flagged = false;

        foreach ($inputs as $input) {
            $check = $validator->checkDisposable($input);
            $results[] = $check->toArray();
            $flagged = $flagged || $check->isDisposable();

            if (!$json) {
                $label = $check->isDisposable() ? 'DISPOSABLE' : ($check->isAllowlisted() ? 'ALLOWED   ' : 'OK        ');
                $detail = $check->getMatchedDomain() !== null
                    ? sprintf(' (matched %s in %s)', $check->getMatchedDomain(), $check->getSource())
                    : '';
                $this->out(sprintf('%s %s%s', $label, $input, $detail));
            }
        }

        if ($json) {
            $this->out((string) json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return $flagged ? self::EXIT_FLAGGED : self::EXIT_OK;
    }

    /**
     * @param array<string> $emails
     * @param array<string, string|array<string>|bool> $options
     */
    private function validate(Configuration $config, array $emails, array $options): int
    {
        if ($emails === []) {
            $this->err('Usage: email-validator validate <email>... [--no-mx] [--json]');
            return self::EXIT_USAGE;
        }

        if (isset($options['no-mx'])) {
            $config->enableMxCheck(false);
        }

        $validator = new EmailValidator($config);
        $results = [];
        $flagged = false;

        foreach ($emails as $email) {
            $result = $validator->validate($email);
            $results[] = $result->toArray();
            $flagged = $flagged || !$result->isValid();

            if (!isset($options['json'])) {
                $line = sprintf('%s %s', $result->isValid() ? 'VALID  ' : 'INVALID', $email);
                if (!$result->isValid()) {
                    $line .= ' — ' . implode('; ', $result->getErrors());
                }
                if ($result->hasSuggestion()) {
                    $line .= ' (did you mean ' . $result->getSuggestion() . '?)';
                }
                $this->out($line);
            }
        }

        if (isset($options['json'])) {
            $this->out((string) json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return $flagged ? self::EXIT_FLAGGED : self::EXIT_OK;
    }

    /**
     * @param array<string, string|array<string>|bool> $options
     */
    private function sync(Configuration $config, array $options): int
    {
        if ($config->getSyncedBlocklistPath() === null) {
            $this->err('No target file: pass --path=FILE or set lists.synced_blocklist_path in the --config file.');
            return self::EXIT_USAGE;
        }

        $result = (new ListSynchronizer($config))->sync(isset($options['replace']));

        if (isset($options['json'])) {
            $this->out((string) json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $result->isSuccessful() ? self::EXIT_OK : self::EXIT_FLAGGED;
        }

        foreach ($result->getSources() as $url => $source) {
            $this->out($source['error'] === null
                ? sprintf('  ✔ %s (%s domains)', $url, number_format($source['domains']))
                : sprintf('  ✘ %s — %s', $url, $source['error']));
        }

        if (!$result->isSuccessful()) {
            $this->err('Sync failed: no source could be downloaded. The existing list was left untouched.');
            return self::EXIT_FLAGGED;
        }

        $this->out(sprintf(
            '%s %s: +%d / -%d, %s domains on top of the bundled list.',
            $result->hasChanged() ? 'Updated' : 'Unchanged',
            $result->getPath(),
            $result->getAdded(),
            $result->getRemoved(),
            number_format($result->getTotal())
        ));

        return self::EXIT_OK;
    }

    private function stats(Configuration $config, bool $json): int
    {
        $stats = (new EmailValidator($config))->getListStatistics();

        if ($json) {
            $this->out((string) json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::EXIT_OK;
        }

        $rows = [
            'Bundled / base blocklist' => number_format($stats['blocklist']),
            'Custom blocklist' => number_format($stats['custom']),
            'Synced blocklist' => number_format($stats['synced']),
            'Total blocked domains' => number_format($stats['total_blocked']),
            'Allowlist' => number_format($stats['allowlist']),
            'Subdomain matching' => $stats['block_subdomains'] ? 'yes' : 'no',
            'Synced list file' => $stats['synced_path'] ?? '(not configured)',
            'Last synced' => $stats['last_synced'] ?? 'never',
            'Bundled list built' => $stats['bundled_generated_at'] ?? 'unknown',
            'Bundled list sources' => $stats['bundled_sources'] === [] ? 'unknown' : implode(', ', $stats['bundled_sources']),
            'Lookup index' => $stats['index_strategy'],
            'Package version' => self::version(),
        ];

        $width = max(array_map('strlen', array_keys($rows)));
        foreach ($rows as $label => $value) {
            $this->out(str_pad($label, $width) . '  ' . $value);
        }

        return self::EXIT_OK;
    }

    /**
     * @param array<string, string|array<string>|bool> $options
     */
    private function configuration(array $options): Configuration
    {
        $config = isset($options['config']) && is_string($options['config'])
            ? Configuration::fromFile($options['config'])
            : new Configuration();

        if (isset($options['path']) && is_string($options['path'])) {
            $config->setSyncedBlocklistPath($options['path']);
        }

        if (isset($options['url'])) {
            $config->setSyncUrls((array) $options['url']);
        }

        if (isset($options['timeout']) && is_string($options['timeout'])) {
            $config->setSyncTimeout((int) $options['timeout']);
        }

        if (isset($options['no-subdomains'])) {
            $config->enableSubdomainBlocking(false);
        }

        return $config;
    }

    /**
     * Splits arguments into command, positional arguments and options.
     * Supports --flag, --key=value and repeatable --url=...
     *
     * @param array<string> $args
     * @return array{0: string|null, 1: array<string>, 2: array<string, string|array<string>|bool>}
     */
    private function parse(array $args): array
    {
        $command = null;
        $positional = [];
        $options = [];

        foreach ($args as $arg) {
            if (strncmp($arg, '--', 2) === 0) {
                $parts = explode('=', substr($arg, 2), 2);
                $name = $parts[0];
                $value = $parts[1] ?? true;

                if ($name === 'url') {
                    $options['url'] = array_merge((array) ($options['url'] ?? []), [(string) $value]);
                } else {
                    $options[$name] = $value;
                }
                continue;
            }

            if ($arg === '-h') {
                $options['help'] = true;
                continue;
            }

            if ($arg === '-V') {
                $options['version'] = true;
                continue;
            }

            if ($command === null) {
                $command = $arg;
            } else {
                $positional[] = $arg;
            }
        }

        return [$command, $positional, $options];
    }

    private function usage(): string
    {
        return <<<'TXT'
php-email-validator — email validation & disposable email detection

Usage:
  email-validator <command> [arguments] [options]

Commands:
  check <email|domain>...   Is it disposable? Shows the matched list entry and its source
  validate <email>...       Full validation (format, disposable, MX, ...)
  sync                      Download remote lists into the synced blocklist file
  stats                     Show list statistics
  help                      Show this help

Options:
  --config=FILE             Load a PHP/JSON/YAML configuration file
  --path=FILE               Synced blocklist file (overrides lists.synced_blocklist_path)
  --url=URL                 Remote list to sync (repeatable; default: this package's daily list)
  --timeout=SECONDS         HTTP timeout for sync (default: 30)
  --replace                 sync: rebuild the list instead of appending (only if every source succeeds)
  --no-mx                   validate: skip the MX record check
  --no-subdomains           Match list entries exactly (no parent-domain matching)
  --json                    Machine-readable output
  -h, --help                Show help
  -V, --version             Show the package version

Exit codes: 0 = OK, 1 = disposable/invalid found or sync failed, 2 = usage error.
TXT;
    }

    private static function version(): string
    {
        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                return (string) \Composer\InstalledVersions::getPrettyVersion('harungecit/php-email-validator');
            } catch (Throwable $e) {
                // Not installed through Composer (e.g. running from a git checkout).
            }
        }

        return 'dev';
    }

    private function out(string $line): void
    {
        fwrite($this->stdout, $line . PHP_EOL);
    }

    private function err(string $line): void
    {
        fwrite($this->stderr, $line . PHP_EOL);
    }
}
