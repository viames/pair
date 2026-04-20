<?php

declare(strict_types=1);

namespace Pair\Data;

/**
 * Reuse the array representation for JSON serialization.
 */
trait ArraySerializableData {

	/**
	 * Serialize the current object through its array export.
	 *
	 * @return	array<string, mixed>
	 */
	public function jsonSerialize(): array {

		return $this->toArray();

	}

}
