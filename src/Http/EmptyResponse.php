<?php

declare(strict_types=1);

namespace Pair\Http;

use Pair\Core\Observability;

/**
 * Explicit response with headers and status code but no body.
 */
final readonly class EmptyResponse implements ResponseInterface {

	/**
	 * Create an empty response.
	 *
	 * @param	array<string, string>	$headers	Additional HTTP headers.
	 */
	public function __construct(
		private int $httpCode = 204,
		private array $headers = []
	) {}

	/**
	 * Send headers and status without emitting a response body.
	 */
	public function send(): void {

		$headers = array_merge(Observability::debugHeaders(), $this->headers);

		foreach ($headers as $name => $value) {
			header($name . ': ' . $value, true);
		}

		http_response_code($this->httpCode);

	}

}
