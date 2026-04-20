<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Data;

use Pair\Data\RecordMapper;
use Pair\Tests\Support\FakeCrudReadModel;
use Pair\Tests\Support\FakeCrudRecord;
use Pair\Tests\Support\TestCase;

/**
 * Covers explicit record-to-read-model mapping.
 */
class RecordMapperTest extends TestCase {

	/**
	 * Verify the mapper builds the requested read model from an ActiveRecord instance.
	 */
	public function testMapReturnsExplicitReadModel(): void {

		$reflection = new \ReflectionClass(FakeCrudRecord::class);
		$record = $reflection->newInstanceWithoutConstructor();
		$record->seed([
			'id' => 7,
			'name' => 'Alice',
			'email' => 'alice@example.test',
		]);

		$readModel = RecordMapper::map($record, FakeCrudReadModel::class);

		$this->assertSame([
			'identifier' => 7,
			'label' => 'ALICE',
			'email' => 'alice@example.test',
		], $readModel->toArray());

	}

	/**
	 * Verify invalid read-model classes are rejected explicitly.
	 */
	public function testMapRejectsClassesWithoutTheRequiredContracts(): void {

		$reflection = new \ReflectionClass(FakeCrudRecord::class);
		$record = $reflection->newInstanceWithoutConstructor();
		$record->seed(['id' => 1, 'name' => 'Alice']);

		$this->expectException(\InvalidArgumentException::class);

		RecordMapper::map($record, \stdClass::class);

	}

}
