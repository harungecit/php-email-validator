<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Symfony\Command;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\Sync\ListSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SyncCommand
 *
 * `bin/console email-validator:sync [--replace]` downloads the remote lists
 * configured in `email_validator.sync.urls` into
 * `email_validator.lists.synced_blocklist_path`. A failed download never
 * damages the existing list. Run it from cron or the Symfony Scheduler to keep
 * the disposable list current between package releases.
 *
 * @package HarunGecit\EmailValidator\Framework\Symfony\Command
 */
#[AsCommand(name: self::NAME, description: self::DESCRIPTION)]
class SyncCommand extends Command
{
    public const NAME = 'email-validator:sync';
    public const DESCRIPTION = 'Download the configured remote disposable-domain lists into the synced blocklist file';

    /**
     * @param ListSynchronizer $synchronizer
     * @param Configuration $configuration The configuration used by the synchronizer.
     */
    public function __construct(private ListSynchronizer $synchronizer, private Configuration $configuration)
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
            ->addOption('replace', null, InputOption::VALUE_NONE, 'Rebuild the list instead of appending (only when every source succeeds)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output machine-readable JSON');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return ConsoleActions::sync(
            $this->synchronizer,
            $this->configuration,
            (bool) $input->getOption('replace'),
            (bool) $input->getOption('json'),
            $output
        );
    }
}
