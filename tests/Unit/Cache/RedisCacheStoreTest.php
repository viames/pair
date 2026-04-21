<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Cache;

use Pair\Cache\RedisCacheStore;
use Pair\Tests\Support\TestCase;

/**
 * Covers the Redis cache adapter with a Redis-compatible test double.
 */
class RedisCacheStoreTest extends TestCase {

	/**
	 * Verify the adapter stores serialized values through the provided Redis client.
	 */
	public function testStoresReadsAndDeletesValues(): void {

		$redis = new FakeRedisClient();
		$store = new RedisCacheStore($redis, 'pair:test:');

		$this->assertSame('fallback', $store->get('missing', 'fallback'));

		$this->assertTrue($store->set('profile', ['id' => 10], 60));
		$this->assertTrue($store->has('profile'));
		$this->assertSame(['id' => 10], $store->get('profile'));
		$this->assertSame(60, $redis->ttls['pair:test:profile']);

		$this->assertTrue($store->delete('profile'));
		$this->assertFalse($store->has('profile'));

	}

	/**
	 * Verify clear removes only keys matching this store prefix.
	 */
	public function testClearRemovesOnlyPrefixedKeys(): void {

		$redis = new FakeRedisClient();
		$store = new RedisCacheStore($redis, 'pair:test:');

		$store->set('first', 'one');
		$redis->set('other:first', serialize('two'));

		$this->assertTrue($store->clear());

		$this->assertFalse($store->has('first'));
		$this->assertArrayHasKey('other:first', $redis->values);

	}

}

/**
 * Minimal Redis-compatible client for RedisCacheStore tests.
 */
final class FakeRedisClient {

	/**
	 * Values keyed by Redis key.
	 *
	 * @var	array<string, string>
	 */
	public array $values = [];

	/**
	 * TTL values keyed by Redis key.
	 *
	 * @var	array<string, int>
	 */
	public array $ttls = [];

	/**
	 * Delete one or more keys.
	 *
	 * @param	string|array<int, string>	$keys	Redis keys.
	 */
	public function del(string|array $keys): int {

		$keys = is_array($keys) ? $keys : [$keys];
		$count = 0;

		foreach ($keys as $key) {
			if (array_key_exists($key, $this->values)) {
				unset($this->values[$key], $this->ttls[$key]);
				$count++;
			}
		}

		return $count;

	}

	/**
	 * Return whether a key exists.
	 */
	public function exists(string $key): int {

		return array_key_exists($key, $this->values) ? 1 : 0;

	}

	/**
	 * Return one value or false when missing.
	 */
	public function get(string $key): string|false {

		return $this->values[$key] ?? false;

	}

	/**
	 * Return keys matching a suffix wildcard pattern.
	 */
	public function keys(string $pattern): array {

		$prefix = rtrim($pattern, '*');
		$keys = [];

		foreach (array_keys($this->values) as $key) {
			if (str_starts_with($key, $prefix)) {
				$keys[] = $key;
			}
		}

		return $keys;

	}

	/**
	 * Store one value without expiration.
	 */
	public function set(string $key, string $value): bool {

		$this->values[$key] = $value;
		unset($this->ttls[$key]);

		return true;

	}

	/**
	 * Store one value with expiration.
	 */
	public function setex(string $key, int $ttl, string $value): bool {

		$this->values[$key] = $value;
		$this->ttls[$key] = $ttl;

		return true;

	}

}
