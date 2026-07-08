<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class SymfonyConfiguration
 *
 * Symfony configuration definition for email validator.
 *
 * @package HarunGecit\EmailValidator\Framework\Symfony\DependencyInjection
 */
class SymfonyConfiguration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('email_validator');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('checks')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('format')->defaultTrue()->end()
                        ->booleanNode('mx')->defaultTrue()->end()
                        ->booleanNode('disposable')->defaultTrue()->end()
                        ->booleanNode('role_based')->defaultFalse()->end()
                        ->booleanNode('smtp')->defaultFalse()->end()
                        ->booleanNode('typo_suggestion')->defaultTrue()->end()
                        ->booleanNode('subaddress')->defaultFalse()->end()
                        ->booleanNode('catch_all')->defaultFalse()->end()
                    ->end()
                ->end()

                ->arrayNode('cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('driver')
                            ->values(['memory', 'file', 'redis', 'memcached', 'null', 'psr16'])
                            ->defaultValue('memory')
                        ->end()
                        ->integerNode('ttl')->defaultValue(3600)->end()
                        ->scalarNode('prefix')->defaultValue('email_validator_')->end()
                        ->arrayNode('options')
                            ->prototype('variable')->end()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('rate_limit')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->integerNode('max_attempts')->defaultValue(100)->end()
                        ->integerNode('decay_seconds')->defaultValue(60)->end()
                    ->end()
                ->end()

                ->arrayNode('lists')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('blocklist_path')->defaultNull()->end()
                        ->scalarNode('allowlist_path')->defaultNull()->end()
                        ->scalarNode('role_based_path')->defaultNull()->end()
                    ->end()
                ->end()

                ->arrayNode('role_based')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('prefixes')
                            ->prototype('scalar')->end()
                            ->defaultValue([
                                'admin', 'info', 'support', 'sales', 'contact', 'noreply',
                                'no-reply', 'help', 'webmaster', 'postmaster', 'hostmaster',
                            ])
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('typo')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('common_domains')
                            ->prototype('scalar')->end()
                            ->defaultValue([
                                'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
                            ])
                        ->end()
                        ->arrayNode('mappings')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('smtp')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('timeout')->defaultValue(10)->end()
                        ->scalarNode('from_email')->defaultNull()->end()
                        ->scalarNode('from_domain')->defaultNull()->end()
                    ->end()
                ->end()

                ->arrayNode('dns')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('timeout')->defaultValue(5)->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
