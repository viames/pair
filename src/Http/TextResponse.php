<?php

declare(strict_types=1);

namespace Pair\Http;

/**
 * Explicit plain-text response for endpoints that must not emit JSON or HTML.
 */
final readonly class TextResponse implements ResponseInterface {

	/**
	 * Create a plain-text response with optional custom status and content type.
	 */
	public function __construct(
		private string $content,
		private int $httpCode = 200,
		private string $contentType = 'text/plain; charset=utf-8'
	) {}

	/**
	 * Send the configured text body with its headers.
	 */
	public function send(): void {

		header('Content-Type: ' . $this->contentType, true);
		http_response_code($this->httpCode);
		print $this->content;

	}

}
