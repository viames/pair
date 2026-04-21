<?php

declare(strict_types=1);

namespace Pair\Web;

use Pair\Core\Application;
use Pair\Core\Observability;
use Pair\Exceptions\CriticalException;
use Pair\Exceptions\ErrorCodes;
use Pair\Http\ResponseInterface;

/**
 * HTML page response backed by a PHP layout file and a typed state object.
 */
final readonly class PageResponse implements ResponseInterface {

	/**
	 * Create a page response for a specific layout file.
	 */
	public function __construct(
		private string $templateFile,
		private object $state,
		private ?string $title = null
	) {}

	/**
	 * Render the configured template with the typed state object.
	 */
	public function send(): void {

		if (!file_exists($this->templateFile)) {
			throw new CriticalException('Page template "' . $this->templateFile . '" was not found', ErrorCodes::VIEW_LOAD_ERROR);
		}

		if (!is_null($this->title)) {
			Application::getInstance()->pageTitle($this->title);
		}

		// Expose only the typed state object to keep the layout contract explicit.
		$state = $this->state;

		Observability::trace('page.response', function () use ($state): void {
			include $this->templateFile;
		}, [
			'template' => basename($this->templateFile),
			'state' => $state::class,
		]);

	}

}
