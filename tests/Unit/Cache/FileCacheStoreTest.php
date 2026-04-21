<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Cache;

use Pair\Cache\FileCacheStore;
use Pair\Tests\Support\TestCase;

/**
 * Covers the dependency-free file cache store.
 */
class FileCacheStoreTest extends TestCase {

	/**
	 * Remove file cache fixtures after each test.
	 */
	protected function tearDown(): void {

		$this->removeDirectory(TEMP_PATH . 'cache-tests');

		parent::tearDown();

	}

	/**
	 * Verify values can be stored, read, checked, deleted, and defaulted.
	 */
	public function testStoresReadsAndDeletesValues(): void {

		$store = $this->newStore();

		$this->assertSame('fallback', $store->get('missing', 'fallback'));
		$this->assertFalse($store->has('profile'));

		$this->assertTrue($store->set('profile', ['id' => 10, 'name' => 'Ada']));

		$this->assertTrue($store->has('profile'));
		$this->assertSame(['id' => 10, 'name' => 'Ada'], $store->get('profile'));

		$this->assertTrue($store->delete('profile'));
		$this->assertFalse($store->has('profile'));
		$this->assertNull($store->get('profile'));

	}

	/**
	 * Verify non-positive TTL values remove the key immediately.
	 */
	public function testNonPositiveTtlExpiresImmediately(): void {

		$store = $this->newStore();

		$this->assertTrue($store->set('temporary', 'value', 0));

		$this->assertFalse($store->has('temporary'));
		$this->assertSame('fallback', $store->get('temporary', 'fallback'));

	}

	/**
	 * Verify clear only removes entries owned by the same prefix.
	 */
	public function testClearRemovesOnlyMatchingPrefix(): void {

		$directory = TEMP_PATH . 'cache-tests';
		$first = new FileCacheStore($directory, 'first');
		$second = new FileCacheStore($directory, 'second');

		$first->set('same-key', 'one');
		$second->set('same-key', 'two');

		$this->assertTrue($first->clear());

		$this->assertFalse($first->has('same-key'));
		$this->assertTrue($second->has('same-key'));
		$this->assertSame('two', $second->get('same-key'));

	}

	/**
	 * Return a store isolated to this test case.
	 */
	private function newStore(): FileCacheStore {

		return new FileCacheStore(TEMP_PATH . 'cache-tests', 'file-test');

	}

}
