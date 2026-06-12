<?php

declare(strict_types=1);

namespace Pair\Web;

use Pair\Core\Application;
use Pair\Core\Router;
use Pair\Data\ReadModel;
use Pair\Helpers\LogBar;
use Pair\Http\Input;
use Pair\Http\JsonResponse;
use Pair\Http\ResponseInterface;

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
	 * Build a standard JSON data response.
	 *
	 * @param	array<string, mixed>	$meta	Additional non-domain metadata.
	 */
	protected function dataResponse(mixed $data = null, ?string $message = null, array $meta = [], int $httpCode = 200): JsonResponse {

		$payload = [];

		if (!is_null($data)) {
			$payload['data'] = $this->normalizeResponseData($data);
		}

		if (!is_null($message)) {
			$meta['message'] = $message;
		}

		// add ajax diagnostics outside the domain payload.
		$eventList = LogBar::isRuntimeAvailable() ? LogBar::getInstance()->renderForAjax() : '';

		if ($eventList) {
			$meta['logBar'] = $eventList;
		}

		if ($meta) {
			$payload['meta'] = $meta;
		}

		return new JsonResponse($payload, $httpCode);

	}

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
	 * Build an explicit HTML fragment response for a progressive UI region.
	 */
	protected function fragment(string $layout, object $state, string $region, int $httpCode = 200): FragmentResponse {

		$region = $this->normalizeRegionName($region);

		if ('' === $region) {
			throw new \InvalidArgumentException('Fragment region name must not be empty.');
		}

		return new FragmentResponse($this->layoutPath($layout), $state, $region, $httpCode);

	}

	/**
	 * Build a page response, or the requested fragment when a region was explicitly requested.
	 */
	protected function pageOrFragment(string $pageLayout, string $fragmentLayout, object $state, string $region, ?string $title = null): ResponseInterface {

		if ($this->wantsRegion($region)) {
			return $this->fragment($fragmentLayout, $state, $region);
		}

		return $this->page($pageLayout, $state, $title);

	}

	/**
	 * Build a page response, or one fragment from a region-to-layout map.
	 *
	 * @param	array<string, string>	$fragmentLayouts	Fragment layouts indexed by region name.
	 */
	protected function pageOrFragments(string $pageLayout, array $fragmentLayouts, object $state, ?string $title = null): ResponseInterface {

		$requestedRegion = $this->requestedRegion();

		if (!is_null($requestedRegion) and array_key_exists($requestedRegion, $fragmentLayouts)) {
			return $this->fragment($fragmentLayouts[$requestedRegion], $state, $requestedRegion);
		}

		return $this->page($pageLayout, $state, $title);

	}

	/**
	 * Build an explicit JSON response.
	 */
	protected function json(ReadModel|\stdClass|array|null $payload, int $httpCode = 200): JsonResponse {

		return new JsonResponse($payload, $httpCode);

	}

	/**
	 * Return the progressive UI region explicitly requested by the client.
	 */
	protected function requestedRegion(): ?string {

		$region = $this->normalizeRegionName((string)$this->input()->header('X-Pair-Region', ''));

		return '' === $region ? null : $region;

	}

	/**
	 * Return true when the current request explicitly asks for the given UI region.
	 */
	protected function wantsRegion(string $region): bool {

		$requestedRegion = $this->requestedRegion();

		return !is_null($requestedRegion) and $requestedRegion === $this->normalizeRegionName($region);

	}

	/**
	 * Build a legacy JSON response compatible with Utilities::pairJsonData().
	 *
	 * @deprecated Use dataResponse() or problemResponse() for new code.
	 */
	protected function pairJsonDataResponse(mixed $data, ?string $message = null, bool $error = false, ?int $code = null, ?int $httpCode = null): JsonResponse {

		$payload = [
			'error' => $error,
		];

		if (!is_null($data)) {
			$payload['data'] = $this->normalizeResponseData($data);
		}

		if (!is_null($message)) {
			$payload['message'] = $message;
		}

		if (!is_null($code)) {
			$payload['code'] = $code;
		}

		// preserve the ajax diagnostics field used by legacy clients.
		$eventList = LogBar::isRuntimeAvailable() ? LogBar::getInstance()->renderForAjax() : '';

		if ($eventList) {
			$payload['logBar'] = $eventList;
		}

		return new JsonResponse($payload, $httpCode ?? 200);

	}

	/**
	 * Build a standard RFC 9457 problem details response.
	 *
	 * @param	array<string, mixed>	$extensions	Problem-specific extension members.
	 */
	protected function problemResponse(string $type, string $title, int $httpCode, ?string $detail = null, ?string $code = null, array $extensions = []): JsonResponse {

		$payload = $extensions;
		$payload['type'] = trim($type) !== '' ? trim($type) : 'about:blank';
		$payload['title'] = $title;
		$payload['status'] = $httpCode;

		if (!is_null($detail)) {
			$payload['detail'] = $detail;
		}

		if (!is_null($code)) {
			$payload['code'] = $code;
		}

		// RFC 9457 allows extension members, so ajax diagnostics remain available.
		$eventList = LogBar::isRuntimeAvailable() ? LogBar::getInstance()->renderForAjax() : '';

		if ($eventList) {
			$payload['logBar'] = $eventList;
		}

		return new JsonResponse($payload, $httpCode, [
			'Content-Type' => 'application/problem+json',
		]);

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

	/**
	 * Normalize a client-provided progressive UI region name.
	 */
	private function normalizeRegionName(string $region): string {

		return str_replace(["\r", "\n"], '', trim($region));

	}

	/**
	 * Normalize response payload values before wrapping them in an envelope.
	 */
	private function normalizeResponseData(mixed $value): mixed {

		if ($value instanceof ReadModel) {
			return $value->toArray();
		}

		if (!is_array($value)) {
			return $value;
		}

		$normalized = [];

		foreach ($value as $key => $item) {
			$normalized[$key] = $this->normalizeResponseData($item);
		}

		return $normalized;

	}

}
