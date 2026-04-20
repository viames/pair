<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Tests\Support\FakeCrudRecord;
use Pair\Tests\Support\FakeCrudResource;
use Pair\Tests\Support\TestCase;

/**
 * Covers the explicit transformation contract exposed by the legacy Resource bridge.
 */
class ResourceTest extends TestCase {

	/**
	 * Verify jsonSerialize() delegates to the concrete resource array transformation.
	 */
	public function testJsonSerializeDelegatesToConcreteArrayTransformation(): void {

		$record = $this->newCrudRecord()->seed([
			'id' => 7,
			'name' => 'Alice',
			'email' => 'alice@example.test',
		]);
		$resource = new FakeCrudResource($record);

		$this->assertSame([
			'identifier' => 7,
			'label' => 'ALICE',
			'email' => 'alice@example.test',
		], $resource->jsonSerialize());

	}

	/**
	 * Verify collection() transforms every item in an iterable using the concrete resource class.
	 */
	public function testCollectionTransformsEveryIterableItem(): void {

		$records = new \ArrayIterator([
			$this->newCrudRecord()->seed([
				'id' => 1,
				'name' => 'Alice',
				'email' => 'alice@example.test',
			]),
			$this->newCrudRecord()->seed([
				'id' => 2,
				'name' => 'Bob',
				'email' => 'bob@example.test',
			]),
		]);

		$this->assertSame([
			[
				'identifier' => 1,
				'label' => 'ALICE',
				'email' => 'alice@example.test',
			],
			[
				'identifier' => 2,
				'label' => 'BOB',
				'email' => 'bob@example.test',
			],
		], FakeCrudResource::collection($records));

	}

	/**
	 * Create a fake ActiveRecord instance without hitting the database constructor.
	 */
	private function newCrudRecord(): FakeCrudRecord {

		$reflection = new \ReflectionClass(FakeCrudRecord::class);

		return $reflection->newInstanceWithoutConstructor();

	}

}
