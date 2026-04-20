<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

use Pair\Data\ArraySerializableData;
use Pair\Data\ReadModel;

/**
 * Typed read model used to verify OpenAPI response schemas for Pair v4 CRUD resources.
 */
final readonly class FakeOpenApiUserReadModel implements ReadModel {

	use ArraySerializableData;

	/**
	 * Build the fake user payload.
	 */
	public function __construct(
		public int $id,
		public string $name,
		public ?string $email,
		public bool $enabled,
		public \DateTimeImmutable $createdAt,
		public FakeOpenApiAddressReadModel $address
	) {}

	/**
	 * Export the read model as an array.
	 *
	 * @return	array<string, mixed>
	 */
	public function toArray(): array {

		return [
			'id' => $this->id,
			'name' => $this->name,
			'email' => $this->email,
			'enabled' => $this->enabled,
			'createdAt' => $this->createdAt->format(DATE_ATOM),
			'address' => $this->address->toArray(),
		];

	}

}
