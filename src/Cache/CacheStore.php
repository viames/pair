<?php

namespace Pair\Cache;

/**
 * Small contract for cache stores used by framework and application code.
 */
interface CacheStore {

	/**
	 * Remove all entries owned by this cache store.
	 */
	public function clear(): bool;

	/**
	 * Delete one cache entry.
	 *
	 * @param	string	$key	Cache key.
	 */
	public function delete(string $key): bool;

	/**
	 * Return a cached value or the provided default when missing or expired.
	 *
	 * @param	string	$key		Cache key.
	 * @param	mixed	$default	Value returned when the key is missing.
	 */
	public function get(string $key, mixed $default = null): mixed;

	/**
	 * Return true when the cache key exists and has not expired.
	 *
	 * @param	string	$key	Cache key.
	 */
	public function has(string $key): bool;

	/**
	 * Store a value, optionally with a TTL in seconds.
	 *
	 * @param	string		$key		Cache key.
	 * @param	mixed		$value		Serializable value.
	 * @param	int|null	$ttlSeconds	Optional TTL; null means no expiration.
	 */
	public function set(string $key, mixed $value, ?int $ttlSeconds = null): bool;

}
