<?php

declare(strict_types=1);

namespace Pair\Http;

use Pair\Data\ReadModel;
use Pair\Helpers\Utilities;

/**
 * Explicit JSON response for Pair v4 controllers.
 */
final readonly class JsonResponse implements ResponseInterface {

	/**
	 * Create a JSON response.
	 */
	public function __construct(
		private ReadModel|\stdClass|array|null $payload,
		private int $httpCode = 200
	) {}

	/**
	 * Send the normalized payload as JSON.
	 */
	public function send(): void {

		Utilities::jsonResponse($this->normalizePayload(), $this->httpCode);

	}

	/**
	 * Normalize the payload into the shape expected by the legacy JSON emitter.
	 */
	private function normalizePayload(): \stdClass|array|null {

		if ($this->payload instanceof ReadModel) {
			return $this->payload->toArray();
		}

		return $this->payload;

	}

}
