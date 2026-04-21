<?php

namespace Pair\Api;

/**
 * Contract for custom endpoint request objects built from validated request data.
 */
interface RequestData {

	/**
	 * Build the request object from validated associative data.
	 *
	 * @param	array<string, mixed>	$data	Validated request data.
	 */
	public static function fromArray(array $data): static;

}
