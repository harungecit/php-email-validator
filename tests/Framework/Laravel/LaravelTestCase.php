<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Framework\Laravel;

use HarunGecit\EmailValidator\Framework\Laravel\EmailValidatorFacade;
use HarunGecit\EmailValidator\Framework\Laravel\EmailValidatorServiceProvider;
use HarunGecit\EmailValidator\Tests\Framework\Laravel\Fixtures\ConfigRepository;
use HarunGecit\EmailValidator\Tests\Framework\Laravel\Fixtures\TestApplication;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/helpers.php';

/**
 * Boots the service provider in a minimal container (no laravel/framework):
 * config repository, translator with an ArrayLoader and the validation factory.
 */
abstract class LaravelTestCase extends TestCase
{
    protected TestApplication $app;

    protected string $basePath = '';

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([Container::class, Factory::class, Translator::class, ValidationRule::class] as $class) {
            if (!class_exists($class) && !interface_exists($class)) {
                $this->markTestSkipped("{$class} is not available.");
            }
        }

        $this->basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ev-laravel-' . bin2hex(random_bytes(6));
        mkdir($this->basePath, 0777, true);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        EmailValidatorFacade::clearResolvedInstances();
        $this->removeDirectory($this->basePath);

        parent::tearDown();
    }

    /**
     * Creates the application and registers + boots the provider.
     *
     * @param array<string, mixed> $config Top-level emailvalidator keys (merged like mergeConfigFrom does).
     * @param bool $console Whether the app runs in the console.
     * @param callable|null $beforeBoot Called with the app after register(), before boot().
     */
    protected function bootApplication(array $config = [], bool $console = true, ?callable $beforeBoot = null): TestApplication
    {
        $this->app = $app = new TestApplication($this->basePath, $console);
        Container::setInstance($app);
        EmailValidatorFacade::setFacadeApplication($app);

        $config += [
            'checks' => [
                'format' => true,
                'mx' => false, // no DNS lookups in tests
                'disposable' => true,
                'role_based' => false,
                'smtp' => false,
                'typo_suggestion' => true,
                'subaddress' => false,
                'catch_all' => false,
                'spoof' => false,
            ],
            'cache' => ['driver' => 'memory', 'ttl' => 0],
        ];

        $app->instance('config', new ConfigRepository(['emailvalidator' => $config]));

        $loader = new ArrayLoader();
        $app->instance(ArrayLoader::class, $loader);
        $app->singleton('translator', fn () => new Translator($loader, 'en'));
        $app->singleton('validator', fn ($app) => new Factory($app->make('translator'), $app));

        $provider = new EmailValidatorServiceProvider($app);
        $provider->register();

        if ($beforeBoot !== null) {
            $beforeBoot($app);
        }

        $provider->boot();

        return $app;
    }

    /**
     * Runs the validation factory and returns the error messages.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @return array<string, array<string>>
     */
    protected function validate(array $data, array $rules): array
    {
        /** @var Factory $factory */
        $factory = $this->app->make('validator');

        return $factory->make($data, $rules)->errors()->toArray();
    }

    protected function removeDirectory(string $path): void
    {
        if ($path === '' || !is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}
