<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

use Pair\Data\ArraySerializableData;
use Pair\Data\MapsFromRecord;
use Pair\Data\ReadModel;
use Pair\Orm\ActiveRecord;

/**
 * Small relation read model used for included CRUD relations.
 */
final readonly class FakeCrudIncludeReadModel implements ReadModel, MapsFromRecord {

	use ArraySerializableData;

	/**
	 * Build the relation read model.
	 */
	public function __construct(
		public ?int $id,
		public string $name
	) {}

	/**
	 * Map the related record into a small explicit payload.
	 */
	public static function fromRecord(ActiveRecord $record): static {

		$payload = $record->toArray();

		return new self(
			isset($payload['id']) ? (int)$payload['id'] : null,
			(string)($payload['name'] ?? '')
		);

	}

	/**
	 * Export the include payload.
	 *
	 * @return	array<string, mixed>
	 */
	public function toArray(): array {

		return [
			'id' => $this->id,
			'name' => $this->name,
		];

	}

}
