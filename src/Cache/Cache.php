<?php

namespace Pair\Cache;

use Pair\Core\Env;
use Pair\Core\Observability;

/**
 * Resolves the application-wide cache store.
 */
final class Cache {

	/**
	 * Configured cache store for the current PHP process.
	 */
	private static ?CacheStore $store = null;

	/**
	 * Remove every cache entry owned by the configured store.
	 */
	public static function clear(): bool {

		return Observability::trace('cache.clear', function (): bool {
			return self::store()->clear();
		});

	}

	/**
	 * Clear the configured store so the next call resolves the default file store.
	 */
	public static function clearStore(): void {

		self::$store = null;

	}

	/**
	 * Delete one cache entry from the configured store.
	 *
	 * @param	string	$key	Cache key.
	 */
	public static function delete(string $key): bool {

		return Observability::trace('cache.delete', function () use ($key): bool {
			return self::store()->delete($key);
		}, self::attributesForKey($key));

	}

	/**
	 * Return a cached value from the configured store.
	 *
	 * @param	string	$key		Cache key.
	 * @param	mixed	$default	Value returned when the key is missing.
	 */
	public static function get(string $key, mixed $default = null): mixed {

		return Observability::trace('cache.get', function () use ($key, $default): mixed {
			return self::store()->get($key, $default);
		}, self::attributesForKey($key));

	}

	/**
	 * Return whether the configured store contains a non-expired cache key.
	 *
	 * @param	string	$key	Cache key.
	 */
	public static function has(string $key): bool {

		return Observability::trace('cache.has', function () use ($key): bool {
			return self::store()->has($key);
		}, self::attributesForKey($key));

	}

	/**
	 * Resolve and store a value only when the key is not already cached.
	 *
	 * @param	string		$key		Cache key.
	 * @param	callable	$resolver	Callback that returns the value on cache miss.
	 * @param	int|null	$ttlSeconds	Optional TTL; null means no expiration.
	 */
	public static function remember(string $key, callable $resolver, ?int $ttlSeconds = null): mixed {

		return Observability::trace('cache.remember', function () use ($key, $resolver, $ttlSeconds): mixed {

			$store = self::store();

			if ($store->has($key)) {
				return $store->get($key);
			}

			$value = $resolver();
			$store->set($key, $value, $ttlSeconds);

			return $value;

		}, self::attributesForKey($key, $ttlSeconds));

	}

	/**
	 * Store a value in the configured store.
	 *
	 * @param	string		$key		Cache key.
	 * @param	mixed		$value		Serializable value.
	 * @param	int|null	$ttlSeconds	Optional TTL; null means no expiration.
	 */
	public static function set(string $key, mixed $value, ?int $ttlSeconds = null): bool {

		return Observability::trace('cache.set', function () use ($key, $value, $ttlSeconds): bool {
			return self::store()->set($key, $value, $ttlSeconds);
		}, self::attributesForKey($key, $ttlSeconds));

	}

	/**
	 * Return the configured cache store, creating the file-backed default when needed.
	 */
	public static function store(): CacheStore {

		if (!self::$store) {
			self::$store = self::resolveStoreFromEnvironment();
		}

		return self::$store;

	}

	/**
	 * Build safe cache attributes without exposing raw application keys.
	 *
	 * @return	array<string, mixed>
	 */
	private static function attributesForKey(string $key, ?int $ttlSeconds = null): array {

		$attributes = [
			'keyHash' => hash('sha256', $key),
		];

		if (!is_null($ttlSeconds)) {
			$attributes['ttlSeconds'] = $ttlSeconds;
		}

		return $attributes;

	}

	/**
	 * Set the cache store used by application code in the current PHP process.
	 */
	public static function setStore(CacheStore $store): void {

		self::$store = $store;

	}

	/**
	 * Create the configured cache store from environment values.
	 */
	private static function resolveStoreFromEnvironment(): CacheStore {

		$driver = strtolower(trim((string)(Env::get('PAIR_CACHE_DRIVER') ?? 'file')));

		return match ($driver) {
			'', 'file' => self::fileStoreFromEnvironment(),
			'apcu' => new ApcuCacheStore(self::prefixFromEnvironment()),
			'redis' => self::redisStoreFromEnvironment(),
			default => throw new \InvalidArgumentException('Unsupported cache driver "' . $driver . '"'),
		};

	}

	/**
	 * Create the file-backed cache store from environment values.
	 */
	private static function fileStoreFromEnvironment(): FileCacheStore {

		$path = trim((string)(Env::get('PAIR_CACHE_PATH') ?? ''));
		$directory = strlen($path) ? $path : null;

		return new FileCacheStore($directory, self::prefixFromEnvironment());

	}

	/**
	 * Return the normalized cache prefix from environment values.
	 */
	private static function prefixFromEnvironment(): string {

		$prefix = trim((string)(Env::get('PAIR_CACHE_PREFIX') ?? 'pair'));

		return strlen($prefix) ? $prefix : 'pair';

	}

	/**
	 * Create the Redis-backed cache store from environment values.
	 */
	private static function redisStoreFromEnvironment(): RedisCacheStore {

		if (!class_exists(\Redis::class)) {
			throw new \RuntimeException('Redis cache driver requires the Redis PHP extension.');
		}

		$host = trim((string)(Env::get('REDIS_HOST') ?? ''));

		if (!strlen($host)) {
			throw new \RuntimeException('Redis cache driver requires REDIS_HOST.');
		}

		$redis = new \Redis();
		$timeout = Env::get('REDIS_TIMEOUT');
		$timeout = (is_numeric($timeout) and floatval($timeout) > 0) ? (float)$timeout : 1.0;
		$port = intval(Env::get('REDIS_PORT') ?? 6379);

		if (str_starts_with($host, '/') or str_starts_with($host, 'unix://')) {
			$connected = $redis->connect($host, 0, $timeout);
		} else {
			$connected = $redis->connect($host, $port > 0 ? $port : 6379, $timeout);
		}

		if (!$connected) {
			throw new \RuntimeException('Unable to connect to Redis cache driver.');
		}

		$password = Env::get('REDIS_PASSWORD');

		if (is_string($password) and strlen(trim($password))) {
			$redis->auth(trim($password));
		}

		$db = intval(Env::get('REDIS_DB') ?? 0);

		if ($db > 0) {
			$redis->select($db);
		}

		$prefix = trim((string)(Env::get('PAIR_CACHE_REDIS_PREFIX') ?? 'pair:cache:'));

		return new RedisCacheStore($redis, strlen($prefix) ? $prefix : 'pair:cache:');

	}

}
