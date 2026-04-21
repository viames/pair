<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Cache;

use Pair\Cache\Cache;
use Pair\Cache\FileCacheStore;
use Pair\Core\Observability;
use Pair\Tests\Support\TestCase;

/**
 * Covers application-wide cache store resolution.
 */
class CacheTest extends TestCase {

	/**
	 * Reset cache store state after each test.
	 */
	protected function tearDown(): void {

		Cache::clearStore();
		$this->removeDirectory(TEMP_PATH . 'cache-tests');
		$this->removeDirectory(TEMP_PATH . 'configured-cache');

		parent::tearDown();

	}

	/**
	 * Verify the default store is file-backed.
	 */
	public function testStoreReturnsFileStoreByDefault(): void {

		$this->assertInstanceOf(FileCacheStore::class, Cache::store());

	}

	/**
	 * Verify a custom store can be installed for the current process.
	 */
	public function testSetStoreOverridesDefaultStore(): void {

		$store = new FileCacheStore(TEMP_PATH . 'cache-tests', 'custom');

		Cache::setStore($store);

		$this->assertSame($store, Cache::store());

	}

	/**
	 * Verify static cache helpers delegate to the configured store.
	 */
	public function testFacadeMethodsDelegateToConfiguredStore(): void {

		Cache::setStore(new FileCacheStore(TEMP_PATH . 'cache-tests', 'facade'));

		$this->assertFalse(Cache::has('profile'));
		$this->assertSame('fallback', Cache::get('profile', 'fallback'));

		$this->assertTrue(Cache::set('profile', ['id' => 10], 60));
		$this->assertTrue(Cache::has('profile'));
		$this->assertSame(['id' => 10], Cache::get('profile'));

		$this->assertTrue(Cache::delete('profile'));
		$this->assertFalse(Cache::has('profile'));

		Cache::set('first', 'one');
		Cache::set('second', 'two');

		$this->assertTrue(Cache::clear());
		$this->assertFalse(Cache::has('first'));
		$this->assertFalse(Cache::has('second'));

	}

	/**
	 * Verify remember() resolves a missing value once and reuses the cached value afterwards.
	 */
	public function testRememberCachesResolvedValue(): void {

		Cache::setStore(new FileCacheStore(TEMP_PATH . 'cache-tests', 'remember'));

		$count = 0;

		$first = Cache::remember('expensive', function () use (&$count): array {
			$count++;

			return ['count' => $count];
		}, 60);

		$second = Cache::remember('expensive', function () use (&$count): array {
			$count++;

			return ['count' => $count];
		}, 60);

		$this->assertSame(['count' => 1], $first);
		$this->assertSame(['count' => 1], $second);
		$this->assertSame(1, $count);

	}

	/**
	 * Verify cache facade helpers emit observability spans without exposing raw cache keys.
	 */
	public function testFacadeMethodsEmitSafeObservabilitySpans(): void {

		Observability::enable();
		Cache::setStore(new FileCacheStore(TEMP_PATH . 'cache-tests', 'observability'));

		Cache::set('profile:secret:7', ['id' => 7], 60);
		Cache::get('profile:secret:7');

		$spans = Observability::spans();

		$this->assertCount(2, $spans);
		$this->assertSame('cache.set', $spans[0]->name());
		$this->assertSame('cache.get', $spans[1]->name());
		$this->assertArrayHasKey('keyHash', $spans[0]->attributes());
		$this->assertArrayNotHasKey('key', $spans[0]->attributes());
		$this->assertSame(60, $spans[0]->attributes()['ttlSeconds']);

	}

	/**
	 * Verify file store path and prefix can be configured through environment values.
	 */
	public function testStoreUsesFileEnvironmentConfiguration(): void {

		$_ENV['PAIR_CACHE_DRIVER'] = 'file';
		$_ENV['PAIR_CACHE_PATH'] = TEMP_PATH . 'configured-cache';
		$_ENV['PAIR_CACHE_PREFIX'] = 'configured';

		Cache::clearStore();

		$store = Cache::store();

		$this->assertInstanceOf(FileCacheStore::class, $store);
		$this->assertTrue($store->set('answer', 42));
		$this->assertCount(1, glob(TEMP_PATH . 'configured-cache/configured-*.cache') ?: []);

	}

	/**
	 * Verify invalid cache drivers fail explicitly instead of silently falling back.
	 */
	public function testStoreRejectsUnsupportedDriver(): void {

		$_ENV['PAIR_CACHE_DRIVER'] = 'unsupported';

		Cache::clearStore();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unsupported cache driver "unsupported"');

		Cache::store();

	}

}
