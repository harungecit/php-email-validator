<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Contracts;

/**
 * Interface CacheInterface
 *
 * PSR-16 compatible cache interface for email validation caching.
 *
 * @package HarunGecit\EmailValidator\Contracts
 */
interface CacheInterface
{
    /**
     * Fetches a value from the cache.
     *
     * @param string $key The unique key of this item in the cache.
     * @param mixed $default Default value to return if the key does not exist.
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Persists data in the cache.
     *
     * @param string $key The key of the item to store.
     * @param mixed $value The value of the item to store.
     * @param int|null $ttl Optional. The TTL value of this item in seconds.
     * @return bool True on success and false on failure.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     * @return bool True if the item was successfully removed, false otherwise.
     */
    public function delete(string $key): bool;

    /**
     * Wipes clean the entire cache.
     *
     * @return bool True on success and false on failure.
     */
    public function clear(): bool;

    /**
     * Determines whether an item is present in the cache.
     *
     * @param string $key The cache item key.
     * @return bool True if item exists, false otherwise.
     */
    public function has(string $key): bool;

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable<string> $keys A list of keys.
     * @param mixed $default Default value for keys that do not exist.
     * @return iterable<string, mixed> A list of key => value pairs.
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable;

    /**
     * Persists a set of key => value pairs in the cache.
     *
     * @param iterable<string, mixed> $values A list of key => value pairs.
     * @param int|null $ttl Optional. The TTL value of this item in seconds.
     * @return bool True on success and false on failure.
     */
    public function setMultiple(iterable $values, ?int $ttl = null): bool;

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable<string> $keys A list of string-based keys to be deleted.
     * @return bool True if all items were successfully removed, false otherwise.
     */
    public function deleteMultiple(iterable $keys): bool;
}
