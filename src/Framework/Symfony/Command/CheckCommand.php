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
 * Class CheckCommand
 *
 * `bin/console email-validator:check <email|domain>...` explains whether each
 * input is disposable, which list entry matched and which list it came from.
 * Exit code 1 when at least one input is disposable.
 *
 * @package HarunGecit\EmailValidator\Framework\Symfony\Command
 */
#[AsCommand(name: self::NAME, description: self::DESCRIPTION)]
class CheckCommand extends Command
{
    public const NAME = 'email-validator:check';
    public const DESCRIPTION = 'Check whether email addresses or domains are disposable and show the matching list entry';

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
            ->addArgument('inputs', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Email addresses or domains')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output machine-readable JSON');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var array<string> $inputs */
        $inputs = (array) $input->getArgument('inputs');

        return ConsoleActions::check($this->emailValidator, $inputs, (bool) $input->getOption('json'), $output);
    }
}
