<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Cache;

use Pair\Cache\SchemaCache;
use Pair\Cache\Cache;
use Pair\Cache\CacheStore;
use Pair\Core\Env;
use Pair\Tests\Support\TestCase;

/** Verifies schema-version isolation and optional cross-request metadata reuse. */
final class SchemaCacheTest extends TestCase {

	/** Disabled caching must always use the authoritative resolver. */
	public function testDisabled(): void {
		$count = 0;
		$load = static function () use (&$count): array { return [++$count]; };
		$this->assertSame([1], SchemaCache::remember('users:inverse', $load));
		$this->assertSame([2], SchemaCache::remember('users:inverse', $load));
	}

	/** An unavailable cache must resolve each read through SQL without hiding application errors. */
	public function testCacheFailure(): void {
		$_ENV['PAIR_SCHEMA_CACHE_VERSION'] = 'fixture-1';
		$store = $this->createStub(CacheStore::class);
		$store->method('get')->willThrowException(new \RuntimeException('cache unavailable'));
		Cache::setStore($store);
		$count = 0;
		$load = static function () use (&$count): array { return [++$count]; };
		$this->assertSame([1], SchemaCache::remember('users:inverse', $load));
		$this->assertSame([2], SchemaCache::remember('users:inverse', $load));
	}

	/** Empty metadata is a valid cache hit and invalidation changes its generation. */
	public function testInvalidation(): void {
		$_ENV['PAIR_SCHEMA_CACHE_VERSION'] = 'fixture-1';
		$count = 0;
		$load = static function () use (&$count): array { $count++; return []; };
		$this->assertSame([], SchemaCache::remember('users:inverse', $load));
		$this->assertSame([], SchemaCache::remember('users:inverse', $load));
		$this->assertSame(1, $count);
		SchemaCache::invalidate();
		$this->assertSame([], SchemaCache::remember('users:inverse', $load));
		$this->assertSame(2, $count);
	}

	/** Database identity, metadata kind and explicit schema version must never share values. */
	public function testIsolation(): void {
		$_ENV['PAIR_SCHEMA_CACHE_VERSION'] = 'fixture-1';
		$_ENV['DB_NAME'] = 'fixture_a';
		$count = 0;
		$load = static function () use (&$count): array { return [(object)['name' => ++$count]]; };
		$this->assertEquals([(object)['name' => 1]], SchemaCache::remember('users:inverse', $load));
		$this->assertEquals([(object)['name' => 1]], SchemaCache::remember('users:inverse', $load));
		$this->assertEquals([(object)['name' => 2]], SchemaCache::remember('users:foreign', $load));
		$_ENV['DB_NAME'] = 'fixture_b';
		$this->assertEquals([(object)['name' => 3]], SchemaCache::remember('users:inverse', $load));
		$_ENV['PAIR_SCHEMA_CACHE_VERSION'] = 'fixture-2';
		$this->assertEquals([(object)['name' => 4]], SchemaCache::remember('users:inverse', $load));
	}

	/** Resolver errors propagate and never become a cached empty schema. */
	public function testResolverFailure(): void {
		$_ENV['PAIR_SCHEMA_CACHE_VERSION'] = 'fixture-1';
		try {
			SchemaCache::remember('users:inverse', static fn(): array => throw new \RuntimeException('fixture'));
			$this->fail('Resolver failure was swallowed');
		} catch (\RuntimeException $error) {
			$this->assertSame('fixture', $error->getMessage());
		}
		$this->assertSame(['fresh'], SchemaCache::remember('users:inverse', static fn(): array => ['fresh']));
	}
}
