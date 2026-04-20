<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Data;

use Pair\Data\ArraySerializableData;
use Pair\Data\ReadModel;
use Pair\Tests\Support\TestCase;

/**
 * Covers the shared JSON serialization trait used by explicit Pair v4 read models.
 */
class ArraySerializableDataTest extends TestCase {

	/**
	 * Verify jsonSerialize() delegates directly to the concrete toArray() export.
	 */
	public function testJsonSerializeDelegatesToArrayExport(): void {

		$data = new ArraySerializableDataFixture([
			'id' => 7,
			'name' => 'Alice',
		]);

		$this->assertSame([
			'id' => 7,
			'name' => 'Alice',
		], $data->jsonSerialize());
		$this->assertSame(1, $data->calls);

	}

	/**
	 * Verify the trait does not cache array exports between successive serializations.
	 */
	public function testJsonSerializeDoesNotCacheArrayExport(): void {

		$data = new ArraySerializableDataFixture([
			'count' => 1,
		]);

		$this->assertSame([
			'count' => 1,
		], $data->jsonSerialize());

		$data->payload = [
			'count' => 2,
		];

		$this->assertSame([
			'count' => 2,
		], $data->jsonSerialize());
		$this->assertSame(2, $data->calls);

	}

}

/**
 * Minimal read-model fixture used to expose the ArraySerializableData contract directly.
 */
final class ArraySerializableDataFixture implements ReadModel {

	use ArraySerializableData;

	/**
	 * Mutable payload returned by toArray().
	 *
	 * @var	array<string, mixed>
	 */
	public array $payload;

	/**
	 * Number of times toArray() has been called by the trait.
	 */
	public int $calls = 0;

	/**
	 * Seed the fixture with a mutable payload.
	 *
	 * @param	array<string, mixed>	$payload
	 */
	public function __construct(array $payload) {

		$this->payload = $payload;

	}

	/**
	 * Return the current payload while tracking trait delegation.
	 *
	 * @return	array<string, mixed>
	 */
	public function toArray(): array {

		$this->calls++;

		return $this->payload;

	}

}
