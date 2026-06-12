<?php

declare(strict_types=1);

namespace Pair\Web;

use Pair\Core\Observability;
use Pair\Exceptions\CriticalException;
use Pair\Exceptions\ErrorCodes;
use Pair\Http\ResponseInterface;

/**
 * HTML fragment response for opt-in progressive Pair UI regions.
 */
final readonly class FragmentResponse implements ResponseInterface {

	/**
	 * Normalized progressive UI region name.
	 */
	private string $region;

	/**
	 * Create a fragment response for a specific layout file and region name.
	 */
	public function __construct(
		private string $templateFile,
		private object $state,
		string $region,
		private int $httpCode = 200
	) {

		$this->region = $this->safeHeaderValue($region);

		if ('' === $this->region) {
			throw new \InvalidArgumentException('Fragment region name must not be empty.');
		}

	}

	/**
	 * Render the configured template without wrapping it in the page template.
	 */
	public function send(): void {

		if (!file_exists($this->templateFile)) {
			throw new CriticalException('Fragment template "' . $this->templateFile . '" was not found', ErrorCodes::VIEW_LOAD_ERROR);
		}

		header('Content-Type: text/html; charset=utf-8', true);
		header('X-Pair-Region: ' . $this->safeHeaderValue($this->region), true);
		$this->sendObservabilityHeaders();
		http_response_code($this->httpCode);

		// Expose only the typed state object to keep the fragment contract explicit.
		$state = $this->state;

		Observability::trace('fragment.response', function () use ($state): void {
			include $this->templateFile;
		}, [
			'template' => basename($this->templateFile),
			'region' => $this->region,
			'state' => $state::class,
		]);

	}

	/**
	 * Send optional debug observability headers for explicit fragment responses.
	 */
	private function sendObservabilityHeaders(): void {

		foreach (Observability::debugHeaders() as $name => $value) {
			header($name . ': ' . $value, true);
		}

	}

	/**
	 * Return a safe single-line value for HTTP headers.
	 */
	private function safeHeaderValue(string $value): string {

		return str_replace(["\r", "\n"], '', trim($value));

	}

}
