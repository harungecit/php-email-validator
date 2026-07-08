<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Config\Configuration;

/**
 * Class EmailValidatorExtension
 *
 * Symfony dependency injection extension for email validator.
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
        $container->setAlias('email_validator', EmailValidator::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'email_validator';
    }
}
