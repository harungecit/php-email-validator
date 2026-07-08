<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Cache;

use HarunGecit\EmailValidator\Contracts\CacheInterface;
use HarunGecit\EmailValidator\Exceptions\CacheException;
use Redis;

/**
 * Class RedisCacheAdapter
 *
 * Redis cache adapter using ext-redis or Predis.
 *
 * @package HarunGecit\EmailValidator\Cache
 */
class RedisCacheAdapter implements CacheInterface
{
    /**
     * @var Redis|object Redis client instance.
     */
    private object $redis;

    /**
     * @var string Cache key prefix.
     */
    private string $prefix;

    /**
     * RedisCacheAdapter constructor.
     *
     * @param array<string, mixed> $options Connection options.
     * @param string $prefix Cache key prefix.
     * @throws CacheException
     */
    public function __construct(array $options = [], string $prefix = '')
    {
        $this->prefix = $prefix;

        // Try ext-redis first
        if (extension_loaded('redis')) {
            $this->redis = $this->createNativeRedis($options);
        }
        // Fall back to Predis
        elseif (class_exists('Predis\Client')) {
            $this->redis = $this->createPredis($options);
        } else {
            throw CacheException::extensionNotLoaded('redis', 'redis');
        }
    }

    /**
     * Creates a native Redis connection.
     *
     * @param array<string, mixed> $options
     * @return Redis
     * @throws CacheException
     */
    private function createNativeRedis(array $options): Redis
    {
        $redis = new Redis();

        $host = $options['host'] ?? '127.0.0.1';
        $port = $options['port'] ?? 6379;
        $timeout = $options['timeout'] ?? 2.5;

        try {
            $connected = $redis->connect($host, $port, $timeout);
            if (!$connected) {
                throw CacheException::connectionFailed('redis', "Failed to connect to {$host}:{$port}");
            }

            if (isset($options['password']) && $options['password'] !== null) {
                if (!$redis->auth($options['password'])) {
                    throw CacheException::connectionFailed('redis', 'Authentication failed');
                }
            }

            if (isset($options['database'])) {
                $redis->select((int) $options['database']);
            }
        } catch (CacheException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw CacheException::connectionFailed('redis', $e->getMessage());
        }

        return $redis;
    }

    /**
     * Creates a Predis connection.
     *
     * @param array<string, mixed> $options
     * @return object
     * @throws CacheException
     */
    private function createPredis(array $options): object
    {
        try {
            $params = [
                'scheme' => 'tcp',
                'host' => $options['host'] ?? '127.0.0.1',
                'port' => $options['port'] ?? 6379,
            ];

            if (isset($options['password'])) {
                $params['password'] = $options['password'];
            }

            if (isset($options['database'])) {
                $params['database'] = $options['database'];
            }

            /** @var object $client */
            $client = new \Predis\Client($params);
            $client->connect();

            return $client;
        } catch (\Exception $e) {
            throw CacheException::connectionFailed('redis', $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->prefix . $key);

        if ($value === false || $value === null) {
            return $default;
        }

        $data = @unserialize($value);
        return $data !== false ? $data : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $key = $this->prefix . $key;
        $value = serialize($value);

        if ($ttl !== null && $ttl > 0) {
            return (bool) $this->redis->setex($key, $ttl, $value);
        }

        return (bool) $this->redis->set($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return (bool) $this->redis->del($this->prefix . $key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        // Only clear keys with our prefix
        $pattern = $this->prefix . '*';
        $keys = $this->redis->keys($pattern);

        if (empty($keys)) {
            return true;
        }

        return (bool) $this->redis->del(...$keys);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($this->prefix . $key);
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

        $values = $this->redis->mget($prefixedKeys);
        $result = [];

        foreach ($prefixedKeys as $index => $prefixedKey) {
            $originalKey = $keyMap[$prefixedKey];
            $value = $values[$index] ?? null;

            if ($value === false || $value === null) {
                $result[$originalKey] = $default;
            } else {
                $data = @unserialize($value);
                $result[$originalKey] = $data !== false ? $data : $default;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(iterable $values, ?int $ttl = null): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
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

        if (empty($prefixedKeys)) {
            return true;
        }

        return (bool) $this->redis->del(...$prefixedKeys);
    }

    /**
     * Gets the Redis client instance.
     *
     * @return Redis|object
     */
    public function getClient(): object
    {
        return $this->redis;
    }
}
