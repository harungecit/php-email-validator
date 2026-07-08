<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Cache;

use HarunGecit\EmailValidator\Contracts\CacheInterface;
use Psr\SimpleCache\CacheInterface as Psr16CacheInterface;

/**
 * Class Psr16CacheAdapter
 *
 * Adapter for PSR-16 compatible cache implementations.
 *
 * @package HarunGecit\EmailValidator\Cache
 */
class Psr16CacheAdapter implements CacheInterface
{
    /**
     * @var Psr16CacheInterface|object The PSR-16 cache instance.
     */
    private object $cache;

    /**
     * Psr16CacheAdapter constructor.
     *
     * @param Psr16CacheInterface|object $cache PSR-16 compatible cache instance.
     */
    public function __construct(object $cache)
    {
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->cache->set($key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        return $this->cache->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->cache->getMultiple($keys, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(iterable $values, ?int $ttl = null): bool
    {
        return $this->cache->setMultiple($values, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        return $this->cache->deleteMultiple($keys);
    }

    /**
     * Gets the underlying PSR-16 cache instance.
     *
     * @return Psr16CacheInterface|object
     */
    public function getCache(): object
    {
        return $this->cache;
    }
}
