<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Symfony\DependencyInjection;

use HarunGecit\EmailValidator\Config\Configuration;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class SymfonyConfiguration
 *
 * Symfony configuration definition for email validator (`email_validator:`
 * in config/packages/email_validator.yaml). Defaults mirror the core
 * Configuration class.
 *
 * @package HarunGecit\EmailValidator\Framework\Symfony\DependencyInjection
 */
class SymfonyConfiguration implements ConfigurationInterface
{
    /**
     * Default location of the synced blocklist (resolved by the container).
     */
    public const DEFAULT_SYNCED_BLOCKLIST_PATH = '%kernel.project_dir%/var/email-validator/synced-blocklist.txt';

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $defaults = new Configuration();
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
                        ->booleanNode('spoof')
                            ->info('Detect mixed-script / invisible characters (requires ext-intl)')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('format')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('mode')
                            ->info('filter | filter_unicode | rfc | strict (rfc/strict require egulias/email-validator)')
                            ->values(Configuration::FORMAT_MODES)
                            ->defaultValue($defaults->getFormatMode())
                        ->end()
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
                        ->scalarNode('blocklist_path')
                            ->info('Replace the bundled blocklist with this file')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('allowlist_path')
                            ->info('Replace the bundled allowlist with this file')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('role_based_path')->defaultNull()->end()
                        ->arrayNode('custom_blocklist')
                            ->info('Extra disposable domains, blocked on top of the bundled list')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('custom_allowlist')
                            ->info('Domains that must never be treated as disposable')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('custom_blocklist_paths')
                            ->info('Extra blocklist files (one domain per line, or JSON)')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('custom_allowlist_paths')
                            ->info('Extra allowlist files (one domain per line, or JSON)')
                            ->scalarPrototype()->end()
                        ->end()
                        ->scalarNode('synced_blocklist_path')
                            ->info('File written by "bin/console email-validator:sync"; null disables the synced list')
                            ->defaultValue(self::DEFAULT_SYNCED_BLOCKLIST_PATH)
                        ->end()
                        ->booleanNode('block_subdomains')
                            ->info('Also block subdomains of listed domains (e.g. eu.mailinator.com)')
                            ->defaultValue($defaults->isSubdomainBlockingEnabled())
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('sync')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('urls')
                            ->info('Remote lists (plain text or JSON) downloaded by "email-validator:sync"')
                            ->scalarPrototype()->end()
                            ->defaultValue($defaults->getSyncUrls())
                        ->end()
                        ->integerNode('timeout')
                            ->info('HTTP timeout in seconds')
                            ->min(1)
                            ->defaultValue($defaults->getSyncTimeout())
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('role_based')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('prefixes')
                            ->scalarPrototype()->end()
                            ->defaultValue($defaults->getRoleBasedPrefixes())
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('typo')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('common_domains')
                            ->scalarPrototype()->end()
                            ->defaultValue($defaults->getCommonDomains())
                        ->end()
                        ->arrayNode('mappings')
                            ->info('Additional typo => correct domain mappings (merged with the built-in ones)')
                            ->useAttributeAsKey('typo')
                            ->normalizeKeys(false)
                            ->scalarPrototype()->end()
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
