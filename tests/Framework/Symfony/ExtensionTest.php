<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Framework\Symfony;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Framework\Symfony\Command\CheckCommand;
use HarunGecit\EmailValidator\Framework\Symfony\Command\StatsCommand;
use HarunGecit\EmailValidator\Framework\Symfony\Command\SyncCommand;
use HarunGecit\EmailValidator\Framework\Symfony\Command\ValidateCommand;
use HarunGecit\EmailValidator\Framework\Symfony\DependencyInjection\EmailValidatorExtension;
use HarunGecit\EmailValidator\Framework\Symfony\DependencyInjection\SymfonyConfiguration;
use HarunGecit\EmailValidator\Framework\Symfony\EmailValidatorBundle;
use HarunGecit\EmailValidator\Framework\Symfony\Validator\NotDisposableEmailValidator;
use HarunGecit\EmailValidator\Framework\Symfony\Validator\NotRoleBasedEmailValidator;
use HarunGecit\EmailValidator\Framework\Symfony\Validator\ValidEmailValidator;
use HarunGecit\EmailValidator\Sync\ListSynchronizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(ContainerBuilder::class) || !class_exists(Processor::class)) {
            $this->markTestSkipped('symfony/dependency-injection and symfony/config are required.');
        }
    }

    public function testDefaultsMirrorCoreConfiguration(): void
    {
        $config = (new Processor())->processConfiguration(new SymfonyConfiguration(), []);
        $core = new Configuration();

        $this->assertSame('filter', $config['format']['mode']);
        $this->assertFalse($config['checks']['spoof']);
        $this->assertSame([], $config['lists']['custom_blocklist']);
        $this->assertSame([], $config['lists']['custom_allowlist']);
        $this->assertSame([], $config['lists']['custom_blocklist_paths']);
        $this->assertSame([], $config['lists']['custom_allowlist_paths']);
        $this->assertSame(SymfonyConfiguration::DEFAULT_SYNCED_BLOCKLIST_PATH, $config['lists']['synced_blocklist_path']);
        $this->assertSame($core->isSubdomainBlockingEnabled(), $config['lists']['block_subdomains']);
        $this->assertSame([Configuration::DEFAULT_SYNC_URL], $config['sync']['urls']);
        $this->assertSame($core->getSyncTimeout(), $config['sync']['timeout']);
        $this->assertSame($core->getRoleBasedPrefixes(), $config['role_based']['prefixes']);
        $this->assertSame($core->getCommonDomains(), $config['typo']['common_domains']);
    }

    public function testRegistersServices(): void
    {
        $container = $this->load([]);

        foreach ([EmailValidator::class, Configuration::class, ListSynchronizer::class] as $id) {
            $this->assertTrue($container->hasDefinition($id), $id);
        }
        $this->assertTrue($container->hasAlias('email_validator'));
        $this->assertTrue($container->hasAlias('email_validator.configuration'));
        $this->assertTrue($container->hasAlias('email_validator.synchronizer'));

        foreach ([NotDisposableEmailValidator::class, ValidEmailValidator::class, NotRoleBasedEmailValidator::class] as $id) {
            $this->assertTrue($container->getDefinition($id)->hasTag('validator.constraint_validator'), $id);
        }

        $commands = [
            CheckCommand::class => 'email-validator:check',
            ValidateCommand::class => 'email-validator:validate',
            SyncCommand::class => 'email-validator:sync',
            StatsCommand::class => 'email-validator:stats',
        ];
        foreach ($commands as $id => $name) {
            $tags = $container->getDefinition($id)->getTag('console.command');
            $this->assertSame($name, $tags[0]['command'] ?? null, $id);
        }
    }

    public function testCompiledContainerProvidesConfiguredValidator(): void
    {
        $projectDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ev-symfony-project';
        $container = $this->load([
            'checks' => ['mx' => false, 'spoof' => true],
            'format' => ['mode' => 'filter_unicode'],
            'lists' => [
                'custom_blocklist' => ['blocked-domain.test'],
                'custom_allowlist' => ['mailinator.com'],
                'block_subdomains' => false,
            ],
            'sync' => ['urls' => ['https://lists.test/a.txt'], 'timeout' => 7],
            'typo' => ['mappings' => ['gmaiil.com' => 'gmail.com']],
        ], $projectDir);

        $this->makeServicesPublic($container);
        $container->compile();

        $validator = $container->get(EmailValidator::class);
        $this->assertInstanceOf(EmailValidator::class, $validator);
        $this->assertSame($validator, $container->get('email_validator'));

        $config = $validator->getConfiguration();
        $this->assertFalse($config->isMxCheckEnabled());
        $this->assertTrue($config->isSpoofCheckEnabled());
        $this->assertSame('filter_unicode', $config->getFormatMode());
        $this->assertFalse($config->isSubdomainBlockingEnabled());
        $this->assertSame(['https://lists.test/a.txt'], $config->getSyncUrls());
        $this->assertSame(7, $config->getSyncTimeout());
        $this->assertSame(
            $projectDir . '/var/email-validator/synced-blocklist.txt',
            $config->getSyncedBlocklistPath()
        );

        // Custom typo mappings extend the built-in ones.
        $this->assertSame('gmail.com', $config->getTypoMappings()['gmaiil.com']);
        $this->assertSame('gmail.com', $config->getTypoMappings()['gmial.com']);

        $this->assertTrue($validator->isDisposable('user@blocked-domain.test'));
        $this->assertFalse($validator->isDisposable('user@mailinator.com'));

        $this->assertInstanceOf(ListSynchronizer::class, $container->get(ListSynchronizer::class));
        $this->assertInstanceOf(SyncCommand::class, $container->get(SyncCommand::class));
        $this->assertInstanceOf(NotDisposableEmailValidator::class, $container->get(NotDisposableEmailValidator::class));
    }

    public function testSyncedListCanBeDisabled(): void
    {
        $container = $this->load(['lists' => ['synced_blocklist_path' => null]]);
        $container->compile();

        $this->assertNull($container->get(EmailValidator::class)->getConfiguration()->getSyncedBlocklistPath());
    }

    public function testBundleReturnsExtension(): void
    {
        if (!class_exists(\Symfony\Component\HttpKernel\Bundle\Bundle::class)) {
            $this->markTestSkipped('symfony/http-kernel is not installed.');
        }

        $extension = (new EmailValidatorBundle())->getContainerExtension();
        $this->assertInstanceOf(EmailValidatorExtension::class, $extension);
        $this->assertSame('email_validator', $extension->getAlias());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config, ?string $projectDir = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $projectDir ?? sys_get_temp_dir());

        (new EmailValidatorExtension())->load([$config], $container);

        return $container;
    }

    private function makeServicesPublic(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class () implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ($container->getDefinitions() as $id => $definition) {
                    if (str_starts_with($id, 'HarunGecit\\')) {
                        $definition->setPublic(true);
                    }
                }
            }
        });
    }
}
