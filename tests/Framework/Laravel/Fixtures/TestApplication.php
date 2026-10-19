<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Framework\Laravel\Fixtures;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\CachesConfiguration;

/**
 * Minimal stand-in for Illuminate\Foundation\Application (laravel/framework is
 * not a dev dependency): a container with the path helpers and environment
 * checks the service provider and console commands use.
 *
 * The package config file calls env(), which needs phpoption/phpoption (a
 * laravel/framework dependency). When it is missing the app reports a cached
 * configuration, like `php artisan config:cache`, so mergeConfigFrom() does
 * not load the file and the tests provide the configuration themselves.
 */
class TestApplication extends Container implements CachesConfiguration
{
    public function __construct(private string $basePath, private bool $console = true)
    {
    }

    public static function canLoadPackageConfig(): bool
    {
        return class_exists(\PhpOption\Option::class);
    }

    public function configurationIsCached()
    {
        return !self::canLoadPackageConfig();
    }

    public function getCachedConfigPath()
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'config.php';
    }

    public function getCachedServicesPath()
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'services.php';
    }

    public function runningInConsole(): bool
    {
        return $this->console;
    }

    public function runningUnitTests(): bool
    {
        return true;
    }

    public function basePath(string $path = ''): string
    {
        return $this->join($this->basePath, $path);
    }

    public function configPath(string $path = ''): string
    {
        return $this->join($this->basePath . DIRECTORY_SEPARATOR . 'config', $path);
    }

    public function langPath(string $path = ''): string
    {
        return $this->join($this->basePath . DIRECTORY_SEPARATOR . 'lang', $path);
    }

    public function storagePath(string $path = ''): string
    {
        return $this->join($this->basePath . DIRECTORY_SEPARATOR . 'storage', $path);
    }

    private function join(string $base, string $path): string
    {
        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, '\\/');
    }
}
