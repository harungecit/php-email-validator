<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Laravel\Commands;

use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Framework\Symfony\Command\ConsoleActions;
use Illuminate\Console\Command;

/**
 * Class StatsCommand
 *
 * `php artisan email-validator:stats` shows blocklist / allowlist sizes, the
 * synced list file and when it was last synced.
 *
 * @package HarunGecit\EmailValidator\Framework\Laravel\Commands
 */
class StatsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'email-validator:stats
        {--json : Output machine-readable JSON}';

    /**
     * @var string
     */
    protected $description = 'Show disposable blocklist and allowlist statistics';

    /**
     * Executes the command.
     *
     * @param EmailValidator $validator
     * @return int
     */
    public function handle(EmailValidator $validator): int
    {
        return ConsoleActions::stats($validator, (bool) $this->option('json'), $this->output);
    }
}
