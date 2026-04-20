<?php

declare(strict_types=1);

namespace Pair\Data;

/**
 * Explicit read contract shared by HTML state and API responses.
 */
interface ReadModel extends \JsonSerializable {

	/**
	 * Export the read model as an array.
	 *
	 * @return	array<string, mixed>
	 */
	public function toArray(): array;

}
