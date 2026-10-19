<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Framework\Laravel;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Framework\Laravel\EmailValidatorFacade;
use HarunGecit\EmailValidator\Sync\ListSynchronizer;
use Illuminate\Console\Scheduling\EventMutex;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\SchedulingMutex;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\View\Compilers\BladeCompiler;

class ServiceProviderTest extends LaravelTestCase
{
    public function testRegistersSingletonsAndAlias(): void
    {
        $app = $this->bootApplication();

        $validator = $app->make(EmailValidator::class);
        $this->assertInstanceOf(EmailValidator::class, $validator);
        $this->assertSame($validator, $app->make('email-validator'));
        $this->assertSame($app->make(Configuration::class), $validator->getConfiguration());
        $this->assertInstanceOf(ListSynchronizer::class, $app->make(ListSynchronizer::class));
        $this->assertTrue(EmailValidatorFacade::isDisposable('user@mailinator.com'));
    }

    public function testDefaultConfigurationIsUsable(): void
    {
        // The package config uses the "laravel" cache driver and storage_path(); no
        // cache store is bound here, so the provider must fall back gracefully.
        $app = $this->bootApplication(['checks' => ['mx' => false], 'cache' => ['driver' => 'laravel', 'ttl' => 60]]);
        $config = $app->make(Configuration::class);

        $this->assertSame('memory', $config->getCacheDriver());
        $this->assertSame('filter', $config->getFormatMode());
        $this->assertSame([Configuration::DEFAULT_SYNC_URL], $config->getSyncUrls());
        $this->assertSame(30, $config->getSyncTimeout());
        $this->assertTrue($config->isSubdomainBlockingEnabled());
        $this->assertSame(
            $app->storagePath('app/email-validator/synced-blocklist.txt'),
            $config->getSyncedBlocklistPath()
        );
        $this->assertSame((new Configuration())->getTypoMappings(), $config->getTypoMappings());
    }

    public function testConfigurationKeysAreApplied(): void
    {
        $app = $this->bootApplication([
            'format' => ['mode' => 'filter_unicode'],
            'lists' => [
                'custom_blocklist' => ['blocked-domain.test'],
                'custom_allowlist' => ['mailinator.com'],
                'synced_blocklist_path' => null,
                'block_subdomains' => false,
            ],
            'sync' => ['urls' => ['https://example.com/list.txt'], 'timeout' => 5],
            'typo' => ['mappings' => ['gmaiil.com' => 'gmail.com']],
        ]);

        $config = $app->make(Configuration::class);
        $this->assertSame('filter_unicode', $config->getFormatMode());
        $this->assertSame(['https://example.com/list.txt'], $config->getSyncUrls());
        $this->assertSame(5, $config->getSyncTimeout());
        $this->assertNull($config->getSyncedBlocklistPath());
        $this->assertFalse($config->isSubdomainBlockingEnabled());

        // Custom typo mappings are added to the built-in ones.
        $mappings = $config->getTypoMappings();
        $this->assertSame('gmail.com', $mappings['gmaiil.com']);
        $this->assertSame('gmail.com', $mappings['gmial.com']);

        $validator = $app->make(EmailValidator::class);
        $this->assertTrue($validator->isDisposable('user@blocked-domain.test'));
        $this->assertFalse($validator->isDisposable('user@mailinator.com'));
    }

    public function testRegistersTranslationNamespace(): void
    {
        $app = $this->bootApplication([], true, function ($app): void {
            $app->singleton('translator', fn () => new Translator(
                new FileLoader(new Filesystem(), $this->basePath . '/lang'),
                'en'
            ));
        });

        $translator = $app->make('translator');
        $this->assertTrue($translator->has('emailvalidator::validation.not_disposable'));
        $this->assertSame(
            'The email must not be a disposable email address.',
            $translator->get('emailvalidator::validation.not_disposable', ['attribute' => 'email'])
        );
    }

    public function testPublishesConfigAndTranslationsInConsole(): void
    {
        $this->bootApplication();

        $config = ServiceProvider::pathsToPublish(null, 'emailvalidator-config');
        $this->assertCount(1, $config);
        $this->assertSame($this->app->configPath('emailvalidator.php'), array_values($config)[0]);

        $translations = ServiceProvider::pathsToPublish(null, 'emailvalidator-translations');
        $this->assertCount(1, $translations);
        $this->assertStringEndsWith('lang/vendor/emailvalidator', str_replace('\\', '/', array_values($translations)[0]));
    }

    public function testBladeDirective(): void
    {
        if (!class_exists(BladeCompiler::class)) {
            $this->markTestSkipped('illuminate/view is not installed.');
        }

        $app = $this->bootApplication();
        $app->singleton('blade.compiler', fn () => new BladeCompiler(new Filesystem(), $this->basePath));

        /** @var BladeCompiler $blade */
        $blade = $app->make('blade.compiler');

        $this->assertTrue($blade->check('disposableEmail', 'user@mailinator.com'));
        $this->assertTrue($blade->check('disposableEmail', 'mailinator.com'));
        $this->assertFalse($blade->check('disposableEmail', 'user@gmail.com'));
        $this->assertFalse($blade->check('disposableEmail', null));

        $compiled = $blade->compileString('@disposableEmail($email) yes @else no @enddisposableEmail');
        $this->assertStringContainsString("check('disposableEmail', \$email)", $compiled);
        $this->assertStringContainsString('endif', $compiled);
    }

    public function testSchedulesSyncCommandWhenConfigured(): void
    {
        $app = $this->bootApplication(['sync' => ['schedule' => 'daily']]);
        $this->bindSchedulerMutexes($app);

        $events = $app->make(Schedule::class)->events();

        $this->assertCount(1, $events);
        $this->assertStringContainsString('email-validator:sync', (string) $events[0]->command);
        $this->assertSame('0 0 * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
    }

    public function testSchedulesWithCronExpression(): void
    {
        $app = $this->bootApplication(['sync' => ['schedule' => '15 3 * * 1']]);
        $this->bindSchedulerMutexes($app);

        $events = $app->make(Schedule::class)->events();

        $this->assertCount(1, $events);
        $this->assertSame('15 3 * * 1', $events[0]->expression);
    }

    public function testDoesNotScheduleByDefault(): void
    {
        $app = $this->bootApplication();
        $this->bindSchedulerMutexes($app);

        $this->assertSame([], $app->make(Schedule::class)->events());
    }

    private function bindSchedulerMutexes($app): void
    {
        $mutex = new class () implements EventMutex, SchedulingMutex {
            public function create($event, $time = null): bool
            {
                return true;
            }

            public function exists($event, $time = null): bool
            {
                return false;
            }

            public function forget($event): void
            {
            }
        };

        $app->instance(EventMutex::class, $mutex);
        $app->instance(SchedulingMutex::class, $mutex);
    }
}
