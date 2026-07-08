<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Cache;

use HarunGecit\EmailValidator\Contracts\CacheInterface;
use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\Exceptions\CacheException;

/**
 * Class CacheManager
 *
 * Factory class for creating cache adapter instances.
 *
 * @package HarunGecit\EmailValidator\Cache
 */
class CacheManager
{
    /**
     * @var array<string, class-string<CacheInterface>> Custom registered drivers.
     */
    private static array $customDrivers = [];

    /**
     * Creates a cache adapter based on configuration.
     *
     * @param Configuration $config The configuration instance.
     * @return CacheInterface
     * @throws CacheException
     */
    public static function create(Configuration $config): CacheInterface
    {
        $driver = $config->getCacheDriver();
        $options = $config->getCacheOptions();
        $prefix = $config->getCachePrefix();

        // Check for custom registered drivers first
        if (isset(self::$customDrivers[$driver])) {
            $class = self::$customDrivers[$driver];
            return new $class($options, $prefix);
        }

        return match ($driver) {
            'memory' => new MemoryCacheAdapter($prefix),
            'file' => self::createFileCache($options, $prefix),
            'redis' => self::createRedisCache($options, $prefix),
            'memcached' => self::createMemcachedCache($options, $prefix),
            'null' => new NullCacheAdapter(),
            'psr16' => self::createPsr16Cache($options),
            default => throw CacheException::driverNotFound($driver)
        };
    }

    /**
     * Creates a file cache adapter.
     *
     * @param array<string, mixed> $options
     * @param string $prefix
     * @return FileCacheAdapter
     */
    private static function createFileCache(array $options, string $prefix): FileCacheAdapter
    {
        $directory = $options['directory'] ?? sys_get_temp_dir();
        return new FileCacheAdapter($directory, $prefix);
    }

    /**
     * Creates a Redis cache adapter.
     *
     * @param array<string, mixed> $options
     * @param string $prefix
     * @return RedisCacheAdapter
     * @throws CacheException
     */
    private static function createRedisCache(array $options, string $prefix): RedisCacheAdapter
    {
        return new RedisCacheAdapter($options, $prefix);
    }

    /**
     * Creates a Memcached cache adapter.
     *
     * @param array<string, mixed> $options
     * @param string $prefix
     * @return MemcachedCacheAdapter
     * @throws CacheException
     */
    private static function createMemcachedCache(array $options, string $prefix): MemcachedCacheAdapter
    {
        return new MemcachedCacheAdapter($options, $prefix);
    }

    /**
     * Creates a PSR-16 cache adapter.
     *
     * @param array<string, mixed> $options
     * @return Psr16CacheAdapter
     * @throws CacheException
     */
    private static function createPsr16Cache(array $options): Psr16CacheAdapter
    {
        if (!isset($options['cache'])) {
            throw new CacheException("PSR-16 cache adapter requires 'cache' option with a PSR-16 compatible cache instance");
        }

        return new Psr16CacheAdapter($options['cache']);
    }

    /**
     * Registers a custom cache driver.
     *
     * @param string $name Driver name.
     * @param class-string<CacheInterface> $class Driver class name.
     * @return void
     */
    public static function registerDriver(string $name, string $class): void
    {
        self::$customDrivers[$name] = $class;
    }

    /**
     * Checks if a driver is available.
     *
     * @param string $driver Driver name.
     * @return bool
     */
    public static function isDriverAvailable(string $driver): bool
    {
        if (isset(self::$customDrivers[$driver])) {
            return true;
        }

        return match ($driver) {
            'memory', 'null', 'file' => true,
            'redis' => extension_loaded('redis') || class_exists('Predis\Client'),
            'memcached' => extension_loaded('memcached'),
            'psr16' => true,
            default => false
        };
    }

    /**
     * Gets a list of available drivers.
     *
     * @return array<string>
     */
    public static function getAvailableDrivers(): array
    {
        $drivers = ['memory', 'null', 'file'];

        if (extension_loaded('redis') || class_exists('Predis\Client')) {
            $drivers[] = 'redis';
        }

        if (extension_loaded('memcached')) {
            $drivers[] = 'memcached';
        }

        $drivers[] = 'psr16';

        return array_merge($drivers, array_keys(self::$customDrivers));
    }
}
