<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Symfony\Command;

use HarunGecit\EmailValidator\EmailValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ValidateCommand
 *
 * `bin/console email-validator:validate <email>...` runs the full configured
 * validation pipeline. Exit code 1 when at least one email is invalid.
 *
 * @package HarunGecit\EmailValidator\Framework\Symfony\Command
 */
#[AsCommand(name: self::NAME, description: self::DESCRIPTION)]
class ValidateCommand extends Command
{
    public const NAME = 'email-validator:validate';
    public const DESCRIPTION = 'Validate email addresses with the configured checks (format, disposable, MX, ...)';

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
            ->addArgument('emails', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Email addresses')
            ->addOption('no-mx', null, InputOption::VALUE_NONE, 'Skip the MX record check')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output machine-readable JSON');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var array<string> $emails */
        $emails = (array) $input->getArgument('emails');

        return ConsoleActions::validate(
            $this->emailValidator,
            $emails,
            (bool) $input->getOption('json'),
            $output,
            (bool) $input->getOption('no-mx')
        );
    }
}
