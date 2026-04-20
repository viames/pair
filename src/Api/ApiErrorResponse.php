<?php

declare(strict_types=1);

namespace Pair\Api;

use Pair\Http\JsonResponse;
use Pair\Http\ResponseInterface;

/**
 * Explicit API error response that keeps the standard Pair error payload shape.
 */
final readonly class ApiErrorResponse implements ResponseInterface {

	/**
	 * Create a standardized API error response.
	 *
	 * @param	array<string, mixed>	$extra	Additional payload fields merged into the error body.
	 */
	public function __construct(
		private string $errorCode,
		private string $errorMessage,
		private int $httpCode = 400,
		private array $extra = []
	) {}

	/**
	 * Send the standardized JSON error payload.
	 */
	public function send(): void {

		$response = new JsonResponse($this->payload(), $this->httpCode);
		$response->send();

	}

	/**
	 * Build the final error payload while dropping non-string extra keys.
	 *
	 * @return	array<string, mixed>
	 */
	private function payload(): array {

		$extra = [];

		foreach ($this->extra as $key => $value) {

			if (!is_string($key)) {
				continue;
			}

			$extra[$key] = $value;

		}

		return array_merge([
			'code' => $this->errorCode,
			'error' => $this->errorMessage,
		], $extra);

	}

}
