<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Data;

use Pair\Data\MapsFromRecord;
use Pair\Data\Payload;
use Pair\Data\ReadModel;
use Pair\Tests\Support\FakeCrudRecord;
use Pair\Tests\Support\TestCase;

/**
 * Covers the minimal explicit payload bridge used in Pair v4 migrations.
 */
class PayloadTest extends TestCase {

	/**
	 * Verify fromArray() preserves the provided associative payload as-is.
	 */
	public function testFromArrayPreservesPayload(): void {

		$payload = Payload::fromArray([
			'id' => 7,
			'name' => 'Alice',
			'meta' => ['active' => true],
		]);

		$this->assertInstanceOf(ReadModel::class, $payload);
		$this->assertInstanceOf(MapsFromRecord::class, $payload);
		$this->assertSame([
			'id' => 7,
			'name' => 'Alice',
			'meta' => ['active' => true],
		], $payload->toArray());

	}

	/**
	 * Verify fromRecord() uses the record array export without relying on implicit runtime serialization.
	 */
	public function testFromRecordUsesActiveRecordArrayExport(): void {

		$reflection = new \ReflectionClass(FakeCrudRecord::class);
		$record = $reflection->newInstanceWithoutConstructor();
		$record->seed([
			'id' => 11,
			'name' => 'Bob',
			'email' => 'bob@example.test',
		]);

		$payload = Payload::fromRecord($record);

		$this->assertSame([
			'id' => 11,
			'name' => 'Bob',
			'email' => 'bob@example.test',
		], $payload->toArray());

	}

	/**
	 * Verify the payload bridge reuses its array export for JSON serialization.
	 */
	public function testJsonSerializeReusesArrayExport(): void {

		$payload = Payload::fromArray([
			'status' => 'ok',
			'count' => 3,
		]);

		$this->assertJsonStringEqualsJsonString(
			json_encode([
				'status' => 'ok',
				'count' => 3,
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
		);

	}

}
