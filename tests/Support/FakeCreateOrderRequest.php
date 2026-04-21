<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

use Pair\Api\RequestData;

/**
 * Minimal request object fixture used by Request mapping tests.
 */
final readonly class FakeCreateOrderRequest implements RequestData {

	/**
	 * Build the mapped request object with already-normalized endpoint values.
	 */
	public function __construct(
		public int $customerId,
		public float $amount,
		public string $currency
	) {}

	/**
	 * Build a request object from validated request data.
	 *
	 * @param	array<string, mixed>	$data	Validated request data.
	 */
	public static function fromArray(array $data): static {

		return new self(
			(int)$data['customerId'],
			(float)$data['amount'],
			strtoupper((string)$data['currency'])
		);

	}

}
