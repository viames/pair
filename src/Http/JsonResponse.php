<?php

declare(strict_types=1);

namespace Pair\Http;

use Pair\Core\Observability;
use Pair\Data\ReadModel;

/**
 * Explicit JSON response for Pair v4 controllers.
 */
final readonly class JsonResponse implements ResponseInterface {

	/**
	 * Create a JSON response.
	 *
	 * @param	array<string, string>	$headers	Additional HTTP headers.
	 */
	public function __construct(
		private mixed $payload,
		private int $httpCode = 200,
		private array $headers = [],
		private ?bool $head = null,
		private int $jsonFlags = 0
	) {}

	/**
	 * Send the normalized payload as JSON.
	 */
	public function send(): void {

		$bodyless = ($this->httpCode < 200 or in_array($this->httpCode, [204, 205, 304], true));
		// Encode before sending any headers, so invalid JSON cannot become a successful empty response.
		$body = $bodyless ? '' : json_encode($this->normalizePayload(), JSON_THROW_ON_ERROR | $this->jsonFlags);
		if (!$bodyless) {
			header('Content-Type: application/json', true);
		}
		$this->sendHeaders();
		http_response_code($this->httpCode);
		$head = $this->head ?? (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD');
		if (!$head) {
			print $body;
		}

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

	/**
	 * Send every additional response header.
	 */
	private function sendHeaders(): void {

		$headers = array_merge(Observability::debugHeaders(), $this->headers);

		foreach ($headers as $name => $value) {
			header($name . ': ' . $value, true);
		}

	}

}
