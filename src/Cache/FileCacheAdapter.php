<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Cache;

use HarunGecit\EmailValidator\Contracts\CacheInterface;
use HarunGecit\EmailValidator\Exceptions\CacheException;

/**
 * Class FileCacheAdapter
 *
 * File-based cache adapter for persistent caching.
 *
 * @package HarunGecit\EmailValidator\Cache
 */
class FileCacheAdapter implements CacheInterface
{
    /**
     * @var string Cache directory path.
     */
    private string $directory;

    /**
     * @var string Cache key prefix.
     */
    private string $prefix;

    /**
     * FileCacheAdapter constructor.
     *
     * @param string $directory Cache directory path.
     * @param string $prefix Cache key prefix.
     * @throws CacheException
     */
    public function __construct(string $directory, string $prefix = '')
    {
        $this->prefix = $prefix;
        $this->directory = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . 'email_validator_cache';

        if (!is_dir($this->directory)) {
            if (!@mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
                throw CacheException::directoryCreationFailed($this->directory);
            }
        }

        if (!is_writable($this->directory)) {
            throw new CacheException("Cache directory is not writable: {$this->directory}");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->getFilePath($key);

        if (!file_exists($path)) {
            return $default;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return $default;
        }

        $data = @unserialize($content);
        if ($data === false) {
            @unlink($path);
            return $default;
        }

        // Check expiration
        if ($data['expiration'] !== null && $data['expiration'] < time()) {
            @unlink($path);
            return $default;
        }

        return $data['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $path = $this->getFilePath($key);

        $data = [
            'value' => $value,
            'expiration' => $ttl !== null ? time() + $ttl : null,
            'created' => time(),
        ];

        $result = @file_put_contents($path, serialize($data), LOCK_EX);

        return $result !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $path = $this->getFilePath($key);

        if (file_exists($path)) {
            return @unlink($path);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.cache');

        if ($files === false) {
            return false;
        }

        $success = true;
        foreach ($files as $file) {
            if (!@unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
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
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Gets the file path for a cache key.
     *
     * @param string $key The cache key.
     * @return string The file path.
     */
    private function getFilePath(string $key): string
    {
        $hash = md5($this->prefix . $key);
        return $this->directory . DIRECTORY_SEPARATOR . $hash . '.cache';
    }

    /**
     * Cleans expired cache entries.
     *
     * @return int Number of entries cleaned.
     */
    public function cleanup(): int
    {
        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.cache');

        if ($files === false) {
            return 0;
        }

        $cleaned = 0;
        $now = time();

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $data = @unserialize($content);
            if ($data === false) {
                @unlink($file);
                $cleaned++;
                continue;
            }

            if ($data['expiration'] !== null && $data['expiration'] < $now) {
                @unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }

    /**
     * Gets the cache directory path.
     *
     * @return string
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }
}
