<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Cache;

use HarunGecit\EmailValidator\Contracts\CacheInterface;
use HarunGecit\EmailValidator\Exceptions\CacheException;
use Memcached;

/**
 * Class MemcachedCacheAdapter
 *
 * Memcached cache adapter.
 *
 * @package HarunGecit\EmailValidator\Cache
 */
class MemcachedCacheAdapter implements CacheInterface
{
    /**
     * @var Memcached Memcached client instance.
     */
    private Memcached $memcached;

    /**
     * @var string Cache key prefix.
     */
    private string $prefix;

    /**
     * MemcachedCacheAdapter constructor.
     *
     * @param array<string, mixed> $options Connection options.
     * @param string $prefix Cache key prefix.
     * @throws CacheException
     */
    public function __construct(array $options = [], string $prefix = '')
    {
        if (!extension_loaded('memcached')) {
            throw CacheException::extensionNotLoaded('memcached', 'memcached');
        }

        $this->prefix = $prefix;
        $this->memcached = new Memcached();

        $host = $options['host'] ?? '127.0.0.1';
        $port = $options['port'] ?? 11211;
        $weight = $options['weight'] ?? 0;

        // Add server if not already added
        $servers = $this->memcached->getServerList();
        $serverExists = false;

        foreach ($servers as $server) {
            if ($server['host'] === $host && $server['port'] === $port) {
                $serverExists = true;
                break;
            }
        }

        if (!$serverExists) {
            if (!$this->memcached->addServer($host, $port, $weight)) {
                throw CacheException::connectionFailed('memcached', "Failed to add server {$host}:{$port}");
            }
        }

        // Set options
        $this->memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
        $this->memcached->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);

        if (isset($options['username']) && isset($options['password'])) {
            $this->memcached->setSaslAuthData($options['username'], $options['password']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->memcached->get($this->prefix . $key);

        if ($this->memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            return $default;
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $key = $this->prefix . $key;
        $expiration = $ttl ?? 0;

        return $this->memcached->set($key, $value, $expiration);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $result = $this->memcached->delete($this->prefix . $key);

        // Consider "not found" as success for delete
        if (!$result && $this->memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            return true;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        // Note: This clears ALL keys, not just prefixed ones
        // Memcached doesn't support key pattern deletion
        return $this->memcached->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        $this->memcached->get($this->prefix . $key);
        return $this->memcached->getResultCode() !== Memcached::RES_NOTFOUND;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $prefixedKeys = [];
        $keyMap = [];

        foreach ($keys as $key) {
            $prefixedKey = $this->prefix . $key;
            $prefixedKeys[] = $prefixedKey;
            $keyMap[$prefixedKey] = $key;
        }

        $values = $this->memcached->getMulti($prefixedKeys);
        $result = [];

        foreach ($keys as $key) {
            $prefixedKey = $this->prefix . $key;
            $result[$key] = $values[$prefixedKey] ?? $default;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(iterable $values, ?int $ttl = null): bool
    {
        $items = [];

        foreach ($values as $key => $value) {
            $items[$this->prefix . $key] = $value;
        }

        $expiration = $ttl ?? 0;

        return $this->memcached->setMulti($items, $expiration);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $prefixedKeys = [];

        foreach ($keys as $key) {
            $prefixedKeys[] = $this->prefix . $key;
        }

        $results = $this->memcached->deleteMulti($prefixedKeys);

        foreach ($results as $result) {
            if ($result !== true && $result !== Memcached::RES_NOTFOUND) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gets the Memcached client instance.
     *
     * @return Memcached
     */
    public function getClient(): Memcached
    {
        return $this->memcached;
    }
}
