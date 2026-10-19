<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Laravel\Commands;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Framework\Laravel\EmailValidatorFacade;
use HarunGecit\EmailValidator\Framework\Symfony\Command\ConsoleActions;
use HarunGecit\EmailValidator\Sync\ListSynchronizer;
use Illuminate\Console\Command;

/**
 * Class SyncCommand
 *
 * `php artisan email-validator:sync [--replace]` downloads the remote lists
 * configured in `emailvalidator.sync.urls` into
 * `emailvalidator.lists.synced_blocklist_path`. A failed download never
 * damages the existing list. Schedule it with `sync.schedule` in
 * config/emailvalidator.php (or EMAIL_VALIDATOR_SYNC_SCHEDULE).
 *
 * @package HarunGecit\EmailValidator\Framework\Laravel\Commands
 */
class SyncCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'email-validator:sync
        {--replace : Rebuild the list instead of appending (only when every source succeeds)}
        {--json : Output machine-readable JSON}';

    /**
     * @var string
     */
    protected $description = 'Download the configured remote disposable-domain lists into the synced blocklist file';

    /**
     * Executes the command.
     *
     * @param ListSynchronizer $synchronizer
     * @param Configuration $configuration
     * @return int
     */
    public function handle(ListSynchronizer $synchronizer, Configuration $configuration): int
    {
        $exitCode = ConsoleActions::sync(
            $synchronizer,
            $configuration,
            (bool) $this->option('replace'),
            (bool) $this->option('json'),
            $this->output
        );

        if ($exitCode === ConsoleActions::EXIT_OK) {
            // Make the next resolution load the freshly synced list (long-running workers, Octane).
            $this->laravel->forgetInstance(EmailValidator::class);
            EmailValidatorFacade::clearResolvedInstance('email-validator');
        }

        return $exitCode;
    }
}
