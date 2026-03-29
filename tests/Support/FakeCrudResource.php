<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

use Pair\Api\Resource;

/**
 * Resource test double that makes CrudController output transformations explicit in assertions.
 */
class FakeCrudResource extends Resource {

	/**
	 * Transform the fake record into a small deterministic payload.
	 *
	 * @return	array<string, mixed>
	 */
	public function toArray(): array {

		$payload = $this->data->toArray();

		return [
			'identifier' => $payload['id'] ?? null,
			'label' => strtoupper((string)($payload['name'] ?? '')),
			'email' => $payload['email'] ?? null,
		];

	}

}
