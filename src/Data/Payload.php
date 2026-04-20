<?php

declare(strict_types=1);

namespace Pair\Data;

use Pair\Orm\ActiveRecord;

/**
 * Minimal explicit payload object used mainly as a migration bridge.
 */
final readonly class Payload implements ReadModel, MapsFromRecord {

	use ArraySerializableData;

	/**
	 * Build the payload from a plain associative array.
	 *
	 * @param	array<string, mixed>	$data	Plain payload data.
	 */
	public function __construct(private array $data) {}

	/**
	 * Build a payload from an array without relying on implicit serialization.
	 *
	 * @param	array<string, mixed>	$data	Plain payload data.
	 */
	public static function fromArray(array $data): self {

		return new self($data);

	}

	/**
	 * Build the payload from an ActiveRecord instance.
	 */
	public static function fromRecord(ActiveRecord $record): static {

		return new self($record->toArray());

	}

	/**
	 * Export the payload as-is.
	 *
	 * @return	array<string, mixed>
	 */
	public function toArray(): array {

		return $this->data;

	}

}
