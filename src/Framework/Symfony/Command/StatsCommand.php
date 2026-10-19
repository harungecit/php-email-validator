<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Symfony\Command;

use HarunGecit\EmailValidator\EmailValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class StatsCommand
 *
 * `bin/console email-validator:stats` shows blocklist / allowlist sizes, the
 * synced list file and when it was last synced.
 *
 * @package HarunGecit\EmailValidator\Framework\Symfony\Command
 */
#[AsCommand(name: self::NAME, description: self::DESCRIPTION)]
class StatsCommand extends Command
{
    public const NAME = 'email-validator:stats';
    public const DESCRIPTION = 'Show disposable blocklist and allowlist statistics';

    /**
     * @param EmailValidator $emailValidator
     */
    public function __construct(private EmailValidator $emailValidator)
    {
        parent::__construct(self::NAME);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription(self::DESCRIPTION)
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output machine-readable JSON');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return ConsoleActions::stats($this->emailValidator, (bool) $input->getOption('json'), $output);
    }
}
