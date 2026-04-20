<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

use Pair\Data\ArraySerializableData;
use Pair\Data\MapsFromRecord;
use Pair\Data\ReadModel;
use Pair\Orm\ActiveRecord;

/**
 * Explicit read model used to verify CrudController mapping without falling back to ActiveRecord output.
 */
final readonly class FakeCrudReadModel implements ReadModel, MapsFromRecord {

	use ArraySerializableData;

	/**
	 * Create the fake read model from scalar values.
	 */
	public function __construct(
		public ?int $identifier,
		public string $label,
		public ?string $email
	) {}

	/**
	 * Map the fake ActiveRecord payload into the read model.
	 */
	public static function fromRecord(ActiveRecord $record): static {

		$payload = $record->toArray();

		return new self(
			isset($payload['id']) ? (int)$payload['id'] : null,
			strtoupper((string)($payload['name'] ?? '')),
			isset($payload['email']) ? (string)$payload['email'] : null
		);

	}

	/**
	 * Export the read model to a deterministic array.
	 *
	 * @return	array<string, mixed>
	 */
	public function toArray(): array {

		return [
			'identifier' => $this->identifier,
			'label' => $this->label,
			'email' => $this->email,
		];

	}

}
