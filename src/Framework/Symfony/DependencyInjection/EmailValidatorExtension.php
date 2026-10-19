<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Symfony\DependencyInjection;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Framework\Symfony\Command\CheckCommand;
use HarunGecit\EmailValidator\Framework\Symfony\Command\StatsCommand;
use HarunGecit\EmailValidator\Framework\Symfony\Command\SyncCommand;
use HarunGecit\EmailValidator\Framework\Symfony\Command\ValidateCommand;
use HarunGecit\EmailValidator\Framework\Symfony\Validator\NotDisposableEmailValidator;
use HarunGecit\EmailValidator\Framework\Symfony\Validator\NotRoleBasedEmailValidator;
use HarunGecit\EmailValidator\Framework\Symfony\Validator\ValidEmailValidator;
use HarunGecit\EmailValidator\Sync\HttpDownloader;
use HarunGecit\EmailValidator\Sync\ListSynchronizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Validator\ConstraintValidatorInterface;

/**
 * Class EmailValidatorExtension
 *
 * Symfony dependency injection extension for email validator.
 *
 * Services:
 * - `HarunGecit\EmailValidator\EmailValidator` (public, alias `email_validator`)
 * - `HarunGecit\EmailValidator\Config\Configuration` (alias `email_validator.configuration`)
 * - `HarunGecit\EmailValidator\Sync\ListSynchronizer` (alias `email_validator.synchronizer`)
 * - constraint validators for #[NotDisposableEmail], #[ValidEmail] and
 *   #[NotRoleBasedEmail] (when symfony/validator is installed)
 * - `email-validator:check|validate|sync|stats` console commands (when
 *   symfony/console is installed)
 *
 * @package HarunGecit\EmailValidator\Framework\Symfony\DependencyInjection
 */
class EmailValidatorExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new SymfonyConfiguration();
        $config = $this->processConfiguration($configuration, $configs);

        // Custom typo mappings extend the built-in ones instead of replacing them.
        $config['typo']['mappings'] = array_merge(
            (new Configuration())->getTypoMappings(),
            $config['typo']['mappings'] ?? []
        );

        // Store configuration as parameter
        $container->setParameter('email_validator.config', $config);

        // Register Configuration service
        $configDefinition = new Definition(Configuration::class);
        $configDefinition->setFactory([Configuration::class, 'fromArray']);
        $configDefinition->setArguments(['%email_validator.config%']);
        $container->setDefinition(Configuration::class, $configDefinition);
        $container->setAlias('email_validator.configuration', Configuration::class);

        // Register EmailValidator service
        $validatorDefinition = new Definition(EmailValidator::class);
        $validatorDefinition->setArguments([new Reference(Configuration::class)]);
        $validatorDefinition->setPublic(true);
        $container->setDefinition(EmailValidator::class, $validatorDefinition);
        $container->setAlias('email_validator', EmailValidator::class)->setPublic(true);

        // Remote list synchronization
        $container->setDefinition(HttpDownloader::class, new Definition(HttpDownloader::class));
        $synchronizerDefinition = new Definition(ListSynchronizer::class);
        $synchronizerDefinition->setArguments([
            new Reference(Configuration::class),
            new Reference(HttpDownloader::class),
        ]);
        $container->setDefinition(ListSynchronizer::class, $synchronizerDefinition);
        $container->setAlias('email_validator.synchronizer', ListSynchronizer::class);

        if (interface_exists(ConstraintValidatorInterface::class)) {
            $this->registerConstraintValidators($container);
        }

        if (class_exists(Command::class)) {
            $this->registerCommands($container);
        }
    }

    /**
     * Registers the constraint validators with the configured EmailValidator injected.
     *
     * @param ContainerBuilder $container
     * @return void
     */
    private function registerConstraintValidators(ContainerBuilder $container): void
    {
        $validators = [
            NotDisposableEmailValidator::class,
            ValidEmailValidator::class,
            NotRoleBasedEmailValidator::class,
        ];

        foreach ($validators as $class) {
            $definition = new Definition($class);
            $definition->setArguments([new Reference(EmailValidator::class)]);
            $definition->addTag('validator.constraint_validator', ['alias' => $class]);
            $container->setDefinition($class, $definition);
        }
    }

    /**
     * Registers the console commands.
     *
     * @param ContainerBuilder $container
     * @return void
     */
    private function registerCommands(ContainerBuilder $container): void
    {
        $commands = [
            CheckCommand::class => [new Reference(EmailValidator::class)],
            ValidateCommand::class => [new Reference(EmailValidator::class)],
            StatsCommand::class => [new Reference(EmailValidator::class)],
            SyncCommand::class => [new Reference(ListSynchronizer::class), new Reference(Configuration::class)],
        ];

        foreach ($commands as $class => $arguments) {
            $definition = new Definition($class);
            $definition->setArguments($arguments);
            $definition->addTag('console.command', [
                'command' => $class::NAME,
                'description' => $class::DESCRIPTION,
            ]);
            $container->setDefinition($class, $definition);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'email_validator';
    }
}
