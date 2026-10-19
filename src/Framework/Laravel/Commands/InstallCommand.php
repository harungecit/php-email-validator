<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Laravel\Commands;

use Illuminate\Console\Command;

/**
 * Class InstallCommand
 *
 * `php artisan email-validator:install` publishes config/emailvalidator.php
 * and prints the next steps.
 *
 * @package HarunGecit\EmailValidator\Framework\Laravel\Commands
 */
class InstallCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'email-validator:install
        {--translations : Also publish the validation messages}
        {--force : Overwrite existing files}';

    /**
     * @var string
     */
    protected $description = 'Publish the email validator configuration and show the next steps';

    /**
     * Executes the command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->publish('emailvalidator-config');

        if ($this->option('translations')) {
            $this->publish('emailvalidator-translations');
        }

        $this->newLine();
        $this->info('Email validator installed. Next steps:');
        $this->line('  1. Review config/emailvalidator.php (checks, lists, sync).');
        $this->line('  2. Validate input:  \'email\' => [\'required\', \'email\', new NotDisposableEmail()]');
        $this->line('     or the string rules: valid_email, not_disposable, not_role_based, has_mx, not_subaddressed, not_spoofed.');
        $this->line('  3. Download the latest disposable domains:  php artisan email-validator:sync');
        $this->line('  4. Keep them fresh: set EMAIL_VALIDATOR_SYNC_SCHEDULE=daily in .env (requires the Laravel scheduler).');
        $this->line('  5. Inspect lists:  php artisan email-validator:stats  /  php artisan email-validator:check user@example.com');

        return self::SUCCESS;
    }

    /**
     * Publishes one vendor:publish tag.
     *
     * @param string $tag
     * @return void
     */
    private function publish(string $tag): void
    {
        $parameters = ['--tag' => $tag];
        if ($this->option('force')) {
            $parameters['--force'] = true;
        }

        $this->call('vendor:publish', $parameters);
    }
}
