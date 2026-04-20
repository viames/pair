<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

use Pair\Data\ArraySerializableData;
use Pair\Data\ReadModel;

/**
 * Nested read model used to verify recursive OpenAPI schema generation.
 */
final readonly class FakeOpenApiAddressReadModel implements ReadModel {

	use ArraySerializableData;

	/**
	 * Build the nested address payload.
	 */
	public function __construct(
		public string $city,
		public ?string $country = null
	) {}

	/**
	 * Export the nested read model as an array.
	 *
	 * @return	array<string, mixed>
	 */
	public function toArray(): array {

		return [
			'city' => $this->city,
			'country' => $this->country,
		];

	}

}
