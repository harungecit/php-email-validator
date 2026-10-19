<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Laravel\Commands;

use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Framework\Symfony\Command\ConsoleActions;
use Illuminate\Console\Command;

/**
 * Class CheckCommand
 *
 * `php artisan email-validator:check <email|domain>...` explains whether each
 * input is disposable, which list entry matched and which list it came from.
 * Exit code 1 when at least one input is disposable.
 *
 * @package HarunGecit\EmailValidator\Framework\Laravel\Commands
 */
class CheckCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'email-validator:check
        {inputs* : Email addresses or domains}
        {--json : Output machine-readable JSON}';

    /**
     * @var string
     */
    protected $description = 'Check whether email addresses or domains are disposable and show the matching list entry';

    /**
     * Executes the command.
     *
     * @param EmailValidator $validator
     * @return int
     */
    public function handle(EmailValidator $validator): int
    {
        /** @var array<string> $inputs */
        $inputs = (array) $this->argument('inputs');

        return ConsoleActions::check($validator, $inputs, (bool) $this->option('json'), $this->output);
    }
}
