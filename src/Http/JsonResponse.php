<?php

declare(strict_types=1);

namespace Pair\Http;

use Pair\Data\ReadModel;
/**
 * Explicit JSON response for Pair v4 controllers.
 */
final readonly class JsonResponse implements ResponseInterface {

	/**
	 * Create a JSON response.
	 */
	public function __construct(
		private mixed $payload,
		private int $httpCode = 200
	) {}

	/**
	 * Send the normalized payload as JSON.
	 */
	public function send(): void {

		$payload = $this->normalizePayload();
		$httpCode = empty($payload) ? 204 : $this->httpCode;

		// Preserve the historical no-content promotion while avoiding hidden exits.
		header('Content-Type: application/json', true);
		http_response_code($httpCode);
		print json_encode($payload);

	}

	/**
	 * Normalize the payload into the shape expected by the legacy JSON emitter.
	 */
	private function normalizePayload(): mixed {

		if ($this->payload instanceof ReadModel) {
			return $this->payload->toArray();
		}

		return $this->payload;

	}

}
