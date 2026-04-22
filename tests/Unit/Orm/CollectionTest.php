<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Orm;

use Pair\Exceptions\PairException;
use Pair\Orm\Collection;
use Pair\Tests\Support\TestCase;

/**
 * Covers Pair collection behavior that is not tied to database-backed records.
 */
class CollectionTest extends TestCase {

	/**
	 * Define the minimal Router constant needed when PairException logs failures.
	 */
	protected function setUp(): void {

		parent::setUp();

		if (!defined('URL_PATH')) {
			define('URL_PATH', null);
		}

	}

	/**
	 * Verify associative collection keys are exposed correctly through Iterator.
	 */
	public function testAssociativeKeysRemainIterable(): void {

		$collection = new Collection([
			'a' => 1,
			'b' => 2,
		]);

		$items = [];

		foreach ($collection as $key => $value) {
			$items[$key] = $value;
		}

		$this->assertSame(['a' => 1, 'b' => 2], $items);

	}

	/**
	 * Verify key-preserving collection operations still return iterable collections.
	 */
	public function testKeyPreservingOperationsRemainIterable(): void {

		$collection = new Collection([
			'a' => 1,
			'b' => 2,
			'c' => 3,
		]);

		$items = [];

		foreach ($collection->slice(1) as $key => $value) {
			$items[$key] = $value;
		}

		$this->assertSame(['b' => 2, 'c' => 3], $items);

	}

	/**
	 * Verify numeric collections can be averaged directly and empty averages remain stable.
	 */
	public function testAverageHandlesNumericAndEmptyCollections(): void {

		$this->assertSame(2.0, (new Collection([1, 2, 3]))->avg());
		$this->assertSame(0.0, (new Collection())->avg());

	}

	/**
	 * Verify keyed numeric values still support average calculations.
	 */
	public function testAverageHandlesKeyedObjectValues(): void {

		$collection = new Collection([
			(object)['score' => 2],
			(object)['score' => 4],
		]);

		$this->assertSame(3.0, $collection->avg('score'));

	}

	/**
	 * Verify invalid chunk sizes fail instead of creating an infinite loop.
	 */
	public function testChunkRejectsNonPositiveSizes(): void {

		$this->expectException(PairException::class);

		(new Collection([1, 2, 3]))->chunk(0);

	}

	/**
	 * Verify chunkWhile keeps the legacy fixed-size chunk behavior through chunk().
	 */
	public function testChunkWhileUsesFixedSizeChunking(): void {

		$chunks = (new Collection([1, 2, 3]))->chunkWhile(2);

		$this->assertSame([[1, 2], [3]], $chunks->all());

	}

}
