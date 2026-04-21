<?php

namespace Pair\Cache;

/**
 * APCu-backed cache store for single-node production deployments.
 */
class ApcuCacheStore implements CacheStore {

	/**
	 * Prefix applied to every APCu key owned by this store.
	 */
	private string $prefix;

	/**
	 * Create an APCu cache store.
	 *
	 * @param	string	$prefix	Logical namespace for cache keys.
	 */
	public function __construct(string $prefix = 'pair') {

		if (!self::isAvailable()) {
			throw new \RuntimeException('APCu cache store requires the APCu extension to be enabled.');
		}

		$this->prefix = trim($prefix) . ':';

	}

	/**
	 * Return whether APCu can be used by the current PHP process.
	 */
	public static function isAvailable(): bool {

		return function_exists('apcu_fetch') and function_exists('apcu_enabled') and apcu_enabled();

	}

	/**
	 * Remove all entries owned by this store.
	 */
	public function clear(): bool {

		if (class_exists(\APCUIterator::class)) {
			$result = apcu_delete(new \APCUIterator('/^' . preg_quote($this->prefix, '/') . '/'));

			return is_array($result) ? !count($result) : (bool)$result;
		}

		return false;

	}

	/**
	 * Delete one cache entry.
	 *
	 * @param	string	$key	Cache key.
	 */
	public function delete(string $key): bool {

		return apcu_delete($this->key($key)) or !$this->has($key);

	}

	/**
	 * Return a cached value or the provided default when missing or expired.
	 *
	 * @param	string	$key		Cache key.
	 * @param	mixed	$default	Value returned when the key is missing.
	 */
	public function get(string $key, mixed $default = null): mixed {

		$success = false;
		$value = apcu_fetch($this->key($key), $success);

		return $success ? $value : $default;

	}

	/**
	 * Return true when the cache key exists and has not expired.
	 *
	 * @param	string	$key	Cache key.
	 */
	public function has(string $key): bool {

		return apcu_exists($this->key($key));

	}

	/**
	 * Store a value, optionally with a TTL in seconds.
	 *
	 * @param	string		$key		Cache key.
	 * @param	mixed		$value		Serializable value.
	 * @param	int|null	$ttlSeconds	Optional TTL; null means no expiration.
	 */
	public function set(string $key, mixed $value, ?int $ttlSeconds = null): bool {

		if (!is_null($ttlSeconds) and $ttlSeconds <= 0) {
			return $this->delete($key);
		}

		return apcu_store($this->key($key), $value, $ttlSeconds ?? 0);

	}

	/**
	 * Return the namespaced APCu key.
	 *
	 * @param	string	$key	Cache key.
	 */
	private function key(string $key): string {

		return $this->prefix . $key;

	}

}
