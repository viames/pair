<?php

declare(strict_types=1);

namespace Pair\Web;

use Pair\Core\Application;
use Pair\Core\Router;
use Pair\Data\ReadModel;
use Pair\Http\Input;
use Pair\Http\JsonResponse;

/**
 * Explicit controller base for Pair v4 modules.
 */
abstract class Controller {

	use \Pair\Traits\AppTrait;
	use \Pair\Traits\LogTrait;

	/**
	 * Application singleton.
	 */
	protected Application $app;

	/**
	 * Router singleton.
	 */
	protected Router $router;

	/**
	 * Immutable request input.
	 */
	protected Input $requestInput;

	/**
	 * Absolute path to the current module directory.
	 */
	private string $modulePath;

	/**
	 * Build the controller without implicit model or view loading.
	 */
	final public function __construct() {

		$this->app = Application::getInstance();
		$this->router = Router::getInstance();
		$this->requestInput = Input::fromGlobals();

		$reflection = new \ReflectionClass($this);
		$this->modulePath = dirname((string)$reflection->getFileName());

		$this->boot();

	}

	/**
	 * Optional boot hook for subclasses.
	 */
	protected function boot(): void {}

	/**
	 * Return the immutable request input.
	 */
	protected function input(): Input {

		return $this->requestInput;

	}

	/**
	 * Build an explicit HTML page response.
	 */
	protected function page(string $layout, object $state, ?string $title = null): PageResponse {

		return new PageResponse($this->layoutPath($layout), $state, $title);

	}

	/**
	 * Build an explicit JSON response.
	 */
	protected function json(ReadModel|\stdClass|array|null $payload, int $httpCode = 200): JsonResponse {

		return new JsonResponse($payload, $httpCode);

	}

	/**
	 * Resolve a relative path inside the current module.
	 */
	protected function modulePath(?string $path = null): string {

		if (is_null($path) or $path === '') {
			return $this->modulePath;
		}

		return $this->modulePath . '/' . ltrim($path, '/');

	}

	/**
	 * Resolve the layout file for the current module.
	 */
	private function layoutPath(string $layout): string {

		return $this->modulePath('layouts/' . $layout . '.php');

	}

}
