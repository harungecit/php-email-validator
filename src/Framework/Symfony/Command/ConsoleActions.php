<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Symfony\Command;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Exceptions\ConfigurationException;
use HarunGecit\EmailValidator\Sync\ListSynchronizer;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Class ConsoleActions
 *
 * Shared implementation of the `email-validator:*` console commands of the
 * Symfony bundle and the Laravel service provider. Output and exit codes
 * mirror the framework-agnostic CLI (`vendor/bin/email-validator`):
 * 0 = success / nothing flagged, 1 = something flagged or the sync failed,
 * 2 = usage or configuration error.
 *
 * @internal
 *
 * @package HarunGecit\EmailValidator\Framework\Symfony\Command
 */
final class ConsoleActions
{
    public const EXIT_OK = 0;
    public const EXIT_FLAGGED = 1;
    public const EXIT_USAGE = 2;

    private const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Explains disposable-domain lookups for email addresses or bare domains.
     *
     * @param EmailValidator $validator
     * @param array<string> $inputs Email addresses or domains.
     * @param bool $json Print JSON instead of one line per input.
     * @param OutputInterface $output
     * @return int Exit code.
     */
    public static function check(EmailValidator $validator, array $inputs, bool $json, OutputInterface $output): int
    {
        if ($inputs === []) {
            self::error($output, 'Pass at least one email address or domain.');
            return self::EXIT_USAGE;
        }

        $results = [];
        $flagged = false;

        foreach ($inputs as $input) {
            $check = $validator->checkDisposable($input);
            $results[] = $check->toArray();
            $flagged = $flagged || $check->isDisposable();

            if (!$json) {
                $label = $check->isDisposable()
                    ? '<fg=red>DISPOSABLE</>'
                    : ($check->isAllowlisted() ? '<fg=cyan>ALLOWED   </>' : '<fg=green>OK        </>');
                $detail = $check->getMatchedDomain() !== null
                    ? sprintf(' (matched %s in %s)', $check->getMatchedDomain(), (string) $check->getSource())
                    : '';
                $output->writeln($label . ' ' . OutputFormatter::escape($input . $detail));
            }
        }

        if ($json) {
            self::json($output, $results);
        }

        return $flagged ? self::EXIT_FLAGGED : self::EXIT_OK;
    }

    /**
     * Runs the full configured validation pipeline.
     *
     * @param EmailValidator $validator
     * @param array<string> $emails
     * @param bool $json Print JSON instead of one line per email.
     * @param OutputInterface $output
     * @param bool $skipMx Skip the MX record check for this run.
     * @return int Exit code.
     */
    public static function validate(
        EmailValidator $validator,
        array $emails,
        bool $json,
        OutputInterface $output,
        bool $skipMx = false
    ): int {
        if ($emails === []) {
            self::error($output, 'Pass at least one email address.');
            return self::EXIT_USAGE;
        }

        $config = $validator->getConfiguration();
        $mxEnabled = $config->isMxCheckEnabled();
        if ($skipMx) {
            $config->enableMxCheck(false);
        }

        $results = [];
        $flagged = false;

        try {
            foreach ($emails as $email) {
                $result = $validator->validate($email);
                $results[] = $result->toArray();
                $flagged = $flagged || !$result->isValid();

                if (!$json) {
                    $line = sprintf('%s %s', $result->isValid() ? 'VALID  ' : 'INVALID', $email);
                    if (!$result->isValid()) {
                        $line .= ' — ' . implode('; ', $result->getErrors());
                    }
                    if ($result->hasSuggestion()) {
                        $line .= ' (did you mean ' . $result->getSuggestion() . '?)';
                    }
                    $color = $result->isValid() ? 'green' : 'red';
                    $output->writeln(sprintf('<fg=%s>%s</>', $color, OutputFormatter::escape($line)));
                }
            }
        } finally {
            $config->enableMxCheck($mxEnabled);
        }

        if ($json) {
            self::json($output, $results);
        }

        return $flagged ? self::EXIT_FLAGGED : self::EXIT_OK;
    }

    /**
     * Downloads the configured remote lists into the synced blocklist file.
     *
     * @param ListSynchronizer $synchronizer
     * @param Configuration $config The configuration the synchronizer uses.
     * @param bool $replace Rebuild the list instead of appending to it.
     * @param bool $json Print the SyncResult as JSON.
     * @param OutputInterface $output
     * @return int Exit code.
     */
    public static function sync(
        ListSynchronizer $synchronizer,
        Configuration $config,
        bool $replace,
        bool $json,
        OutputInterface $output
    ): int {
        if ($config->getSyncedBlocklistPath() === null) {
            self::error($output, 'No target file: set lists.synced_blocklist_path in the email validator configuration.');
            return self::EXIT_USAGE;
        }

        try {
            $result = $synchronizer->sync($replace);
        } catch (ConfigurationException $e) {
            self::error($output, 'Error: ' . $e->getMessage());
            return self::EXIT_USAGE;
        } catch (Throwable $e) {
            self::error($output, 'Sync failed: ' . $e->getMessage());
            return self::EXIT_FLAGGED;
        }

        if ($json) {
            self::json($output, $result->toArray());
            return $result->isSuccessful() ? self::EXIT_OK : self::EXIT_FLAGGED;
        }

        foreach ($result->getSources() as $url => $source) {
            $output->writeln($source['error'] === null
                ? sprintf('  <fg=green>✔</> %s (%s domains)', OutputFormatter::escape((string) $url), number_format($source['domains']))
                : sprintf('  <fg=red>✘</> %s — %s', OutputFormatter::escape((string) $url), OutputFormatter::escape($source['error'])));
        }

        if (!$result->isSuccessful()) {
            self::error($output, 'Sync failed: no source could be downloaded. The existing list was left untouched.');
            return self::EXIT_FLAGGED;
        }

        $output->writeln(OutputFormatter::escape(sprintf(
            '%s %s: +%d / -%d, %s domains on top of the bundled list.',
            $result->hasChanged() ? 'Updated' : 'Unchanged',
            $result->getPath(),
            $result->getAdded(),
            $result->getRemoved(),
            number_format($result->getTotal())
        )));

        return self::EXIT_OK;
    }

    /**
     * Prints list statistics.
     *
     * @param EmailValidator $validator
     * @param bool $json Print JSON instead of a table.
     * @param OutputInterface $output
     * @return int Exit code.
     */
    public static function stats(EmailValidator $validator, bool $json, OutputInterface $output): int
    {
        $stats = $validator->getListStatistics();

        if ($json) {
            self::json($output, $stats);
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
            $output->writeln(OutputFormatter::escape(str_pad($label, $width) . '  ' . $value));
        }

        return self::EXIT_OK;
    }

    /**
     * Returns the installed package version, or "dev".
     *
     * @return string
     */
    public static function version(): string
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

    /**
     * Writes a value as pretty-printed JSON, without console formatting.
     *
     * @param OutputInterface $output
     * @param mixed $data
     * @return void
     */
    private static function json(OutputInterface $output, mixed $data): void
    {
        $output->writeln((string) json_encode($data, self::JSON_FLAGS), OutputInterface::OUTPUT_RAW);
    }

    /**
     * Writes an error line to stderr when the output supports it.
     *
     * @param OutputInterface $output
     * @param string $message
     * @return void
     */
    private static function error(OutputInterface $output, string $message): void
    {
        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        } elseif ($output instanceof SymfonyStyle) {
            $output = $output->getErrorStyle();
        }

        $output->writeln('<error>' . OutputFormatter::escape($message) . '</error>');
    }
}
