<?php

namespace Pair\Cache;

/**
 * Redis-backed cache store for shared production deployments.
 */
class RedisCacheStore implements CacheStore {

	/**
	 * Prefix applied to every Redis key owned by this store.
	 */
	private string $prefix;

	/**
	 * Redis-compatible client object.
	 */
	private object $redis;

	/**
	 * Create a Redis cache store around an existing Redis-compatible client.
	 *
	 * @param	object	$redis	Redis-compatible client.
	 * @param	string	$prefix	Logical namespace for cache keys.
	 */
	public function __construct(object $redis, string $prefix = 'pair:cache:') {

		$this->redis = $redis;
		$this->prefix = trim($prefix);

		if (!str_ends_with($this->prefix, ':')) {
			$this->prefix .= ':';
		}

	}

	/**
	 * Remove all entries owned by this store.
	 */
	public function clear(): bool {

		$keys = $this->redis->keys($this->prefix . '*');

		if (!is_array($keys) or !count($keys)) {
			return true;
		}

		return (int)$this->redis->del($keys) >= 0;

	}

	/**
	 * Delete one cache entry.
	 *
	 * @param	string	$key	Cache key.
	 */
	public function delete(string $key): bool {

		return (int)$this->redis->del($this->key($key)) >= 0;

	}

	/**
	 * Return a cached value or the provided default when missing or expired.
	 *
	 * @param	string	$key		Cache key.
	 * @param	mixed	$default	Value returned when the key is missing.
	 */
	public function get(string $key, mixed $default = null): mixed {

		$payload = $this->redis->get($this->key($key));

		if (false === $payload or !is_string($payload)) {
			return $default;
		}

		$value = @unserialize($payload, ['allowed_classes' => true]);

		if (false === $value and $payload !== serialize(false)) {
			return $default;
		}

		return $value;

	}

	/**
	 * Return true when the cache key exists and has not expired.
	 *
	 * @param	string	$key	Cache key.
	 */
	public function has(string $key): bool {

		return (int)$this->redis->exists($this->key($key)) > 0;

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

		$payload = serialize($value);

		if (is_null($ttlSeconds)) {
			return (bool)$this->redis->set($this->key($key), $payload);
		}

		return (bool)$this->redis->setex($this->key($key), $ttlSeconds, $payload);

	}

	/**
	 * Return the namespaced Redis key.
	 *
	 * @param	string	$key	Cache key.
	 */
	private function key(string $key): string {

		return $this->prefix . $key;

	}

}
