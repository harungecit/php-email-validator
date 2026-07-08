<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Cache;

use HarunGecit\EmailValidator\Contracts\CacheInterface;

/**
 * Class MemoryCacheAdapter
 *
 * In-memory cache adapter. Data is stored for the duration of the request.
 *
 * @package HarunGecit\EmailValidator\Cache
 */
class MemoryCacheAdapter implements CacheInterface
{
    /**
     * @var array<string, mixed> Cached values.
     */
    private array $cache = [];

    /**
     * @var array<string, int|null> Expiration timestamps.
     */
    private array $expiration = [];

    /**
     * @var string Cache key prefix.
     */
    private string $prefix;

    /**
     * MemoryCacheAdapter constructor.
     *
     * @param string $prefix Cache key prefix.
     */
    public function __construct(string $prefix = '')
    {
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->prefix . $key;

        if (!$this->hasInternal($key)) {
            return $default;
        }

        return $this->cache[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $key = $this->prefix . $key;

        $this->cache[$key] = $value;
        $this->expiration[$key] = $ttl !== null ? time() + $ttl : null;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $key = $this->prefix . $key;

        unset($this->cache[$key], $this->expiration[$key]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->cache = [];
        $this->expiration = [];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->hasInternal($this->prefix . $key);
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(iterable $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * Internal check for key existence (without prefix).
     *
     * @param string $key The full key including prefix.
     * @return bool
     */
    private function hasInternal(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        // Check expiration
        if (isset($this->expiration[$key]) && $this->expiration[$key] !== null) {
            if ($this->expiration[$key] < time()) {
                unset($this->cache[$key], $this->expiration[$key]);
                return false;
            }
        }

        return true;
    }

    /**
     * Gets the number of items in the cache.
     *
     * @return int
     */
    public function count(): int
    {
        // Clean expired entries first
        $this->cleanExpired();

        return count($this->cache);
    }

    /**
     * Removes expired entries from the cache.
     *
     * @return void
     */
    private function cleanExpired(): void
    {
        $now = time();

        foreach ($this->expiration as $key => $expiration) {
            if ($expiration !== null && $expiration < $now) {
                unset($this->cache[$key], $this->expiration[$key]);
            }
        }
    }
}
