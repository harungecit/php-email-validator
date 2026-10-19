<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Laravel\Commands;

use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Framework\Symfony\Command\ConsoleActions;
use Illuminate\Console\Command;

/**
 * Class ValidateCommand
 *
 * `php artisan email-validator:validate <email>...` runs the full configured
 * validation pipeline. Exit code 1 when at least one email is invalid.
 *
 * @package HarunGecit\EmailValidator\Framework\Laravel\Commands
 */
class ValidateCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'email-validator:validate
        {emails* : Email addresses}
        {--no-mx : Skip the MX record check}
        {--json : Output machine-readable JSON}';

    /**
     * @var string
     */
    protected $description = 'Validate email addresses with the configured checks (format, disposable, MX, ...)';

    /**
     * Executes the command.
     *
     * @param EmailValidator $validator
     * @return int
     */
    public function handle(EmailValidator $validator): int
    {
        /** @var array<string> $emails */
        $emails = (array) $this->argument('emails');

        return ConsoleActions::validate(
            $validator,
            $emails,
            (bool) $this->option('json'),
            $this->output,
            (bool) $this->option('no-mx')
        );
    }
}
