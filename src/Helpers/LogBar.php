<?php

namespace Pair\Helpers;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Core\Observability;
use Pair\Core\Router;
use Pair\Models\Session;
use Pair\Models\User;

/**
 * Singleton facade that collects Pair v4 request inspector events.
 */
class LogBar {

	/**
	 * Memory usage ratio percent above which only warnings and errors are logged.
	 */
	private const MEMORY_ALERT_RATIO = 60.0;

	/**
	 * Memory usage ratio percent above which the overview reports memory pressure.
	 */
	private const MEMORY_NEAR_LIMIT_RATIO = 80.0;

	/**
	 * Cookie name for query visibility.
	 */
	private const COOKIE_SHOW_QUERIES = 'LogBarShowQueries';

	/**
	 * Cookie name for inspector body visibility.
	 */
	private const COOKIE_SHOW_EVENTS = 'LogBarShowEvents';

	/**
	 * Legacy cookie name for show queries.
	 */
	private const LEGACY_COOKIE_SHOW_QUERIES = 'LogShowQueries';

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

	/**
	 * Request start time.
	 */
	private float $timeStart;

	/**
	 * Chrono timestamp of the last event.
	 */
	private float $lastChrono;

	/**
	 * Logged request entries.
	 *
	 * @var	LogBarEntry[]
	 */
	private array $events = [];

	/**
	 * Disabled flag.
	 */
	private bool $disabled = false;

	/**
	 * Tracks whether the retention warning has already been emitted.
	 */
	private bool $eventLimitWarningAdded = false;

	/**
	 * Tracks whether the LogBar client asset has already been registered.
	 */
	private bool $assetsRegistered = false;

	/**
	 * Incremental event identifier.
	 */
	private int $nextEventId = 1;

	/**
	 * Disabled constructor.
	 */
	private function __construct() {}

	/**
	 * Render the LogBar when the object is used as a string.
	 */
	public function __toString(): string {

		return $this->render();

	}

	/**
	 * Add a normalized event to the current request log.
	 *
	 * @param	array<string, mixed>	$attributes	Structured attributes used by the inspector renderer.
	 */
	private function addEvent(
		string $description,
		string $type = 'notice',
		?string $subtext = null,
		array $attributes = [],
		?string $status = null,
		?float $start = null,
		?float $duration = null
	): void {

		if (!$this->isEnabled()) {
			return;
		}

		$type = $this->normalizeType($type);

		if ($this->shouldDropEvent($type)) {
			return;
		}

		$now = $this->getMicrotime();
		$duration = $duration ?? abs($now - $this->lastChrono);
		$start = $start ?? max(0.0, $now - $this->timeStart - $duration);

		$this->events[] = new LogBarEntry(
			'logbar-event-' . $this->nextEventId++,
			$type,
			LogBarSql::redactText($description),
			is_null($subtext) ? null : LogBarSql::redactText($subtext),
			$start,
			$duration,
			$status ?: $this->statusForType($type),
			$this->sanitizeAttributes($attributes),
		);
		$this->lastChrono = $now;

	}

	/**
	 * Check if the log can appear in the current session.
	 *
	 * @return	bool	True if can be shown.
	 */
	public function canBeShown(): bool {

		// The LogBar is intentionally restricted to super users with the show_log option.
		if (User::current() and Options::get('show_log')) {

			$session = Session::current();

			if ($session and $session->hasFormerUser()) {
				$formerUser = $session->getFormerUser();
				return ($formerUser ? $formerUser->super : false);
			}

			return (bool)User::current()->super;

		}

		return false;

	}

	/**
	 * Build structured inspector data for the rendered LogBar.
	 *
	 * @return	array<string, mixed>
	 */
	private function collectInspectorData(): array {

		$inspector = $this->inspector();

		return $inspector->collect(
			$this->events,
			$this->getMicrotime() - $this->timeStart,
			memory_get_peak_usage(),
			$this->getMemoryLimitBytes(),
		);

	}

	/**
	 * Shutdown the log.
	 */
	final public function disable(): void {

		$this->disabled = true;

	}

	/**
	 * Adds an event, storing its chrono time.
	 *
	 * @param	string		$description	Event description.
	 * @param	string		$type			Event type notice, query, api, warning or error.
	 * @param	null|string	$subtext		Optional additional text.
	 */
	final public static function event(string $description, string $type = 'notice', ?string $subtext = null): void {

		$self = self::getInstance();

		if (!$self->isEnabled()) {
			return;
		}

		$attributes = [];

		if ('query' === $self->normalizeType($type)) {
			$attributes = self::queryAttributes($description, LogBarInspector::rowsFromSubtext($subtext));
			$description = LogBarSql::render($description, [], self::showSqlValues());
		}

		$self->addEvent($description, $type, $subtext, $attributes);

	}

	/**
	 * Returns a cookie value as bool.
	 *
	 * @param	string	$name	Cookie name.
	 */
	private function getCookieBool(string $name): bool {

		if (!isset($_COOKIE[$name])) {
			return false;
		}

		$value = $_COOKIE[$name];

		if (is_bool($value)) {
			return $value;
		}

		return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on', 'b:1;'], true);

	}

	/**
	 * Returns count of registered error.
	 */
	final public function getErrorCount(): int {

		return $this->countEventsByType('error');

	}

	/**
	 * Singleton instance method.
	 */
	final public static function getInstance(): self {

		if (null == self::$instance) {
			self::$instance = new self();
			self::$instance->startChrono();
		}

		return self::$instance;

	}

	/**
	 * Returns [showQueries, showEvents] cookie settings.
	 *
	 * @return	array{0: bool, 1: bool}
	 */
	private function getLogVisibility(): array {

		return [
			$this->getCookieBool(self::COOKIE_SHOW_QUERIES),
			$this->getCookieBool(self::COOKIE_SHOW_EVENTS),
		];

	}

	/**
	 * Returns memory limit in bytes.
	 */
	private function getMemoryLimitBytes(): int {

		$limit = ini_get('memory_limit');
		if ('-1' === $limit) {
			return 0;
		}

		// Convert shorthand memory limits such as 128M into bytes.
		switch (substr($limit, -1)) {
			case 'G': case 'g': $multiplier = 1073741824;	break;
			case 'M': case 'm': $multiplier = 1048576;		break;
			case 'K': case 'k': $multiplier = 1024;			break;
			default:			$multiplier = 1;			break;
		}

		return (int)$limit * $multiplier;

	}

	/**
	 * Returns current time as float value.
	 */
	private function getMicrotime(): float {

		return microtime(true);

	}

	/**
	 * Returns the query count.
	 */
	final public function getQueryCount(): int {

		return $this->countEventsByType('query');

	}

	/**
	 * Returns showQueries cookie, with legacy fallback for AJAX consumers.
	 */
	private function getShowQueriesForAjax(): bool {

		if (isset($_COOKIE[self::COOKIE_SHOW_QUERIES])) {
			return $this->getCookieBool(self::COOKIE_SHOW_QUERIES);
		}

		return isset($_COOKIE[self::LEGACY_COOKIE_SHOW_QUERIES]) ? $this->getCookieBool(self::LEGACY_COOKIE_SHOW_QUERIES) : false;

	}

	/**
	 * Returns the warning count.
	 */
	final public function getWarningCount(): int {

		return $this->countEventsByType('warning');

	}

	/**
	 * Build the request inspector service for current runtime thresholds.
	 */
	private function inspector(): LogBarInspector {

		return new LogBarInspector(
			$this->slowRequestMs(),
			$this->slowQueryMs(),
			$this->queryBudget(),
			$this->duplicateQueryBudget(),
			self::MEMORY_NEAR_LIMIT_RATIO,
		);

	}

	/**
	 * Check that LogBar can be collected by checking disabled flag, CLI, API, router module and Options.
	 */
	final public function isEnabled(): bool {

		if ($this->disabled or 'cli' == php_sapi_name() or !self::runtimeEnabled()) {
			return false;
		}

		$router = Router::getInstance();

		if ('api' == $router->module or ('user' == $router->module and 'login' == $router->action)) {
			return false;
		}

		return true;

	}

	/**
	 * Return whether LogBar may collect request data in the current runtime.
	 */
	private static function runtimeEnabled(): bool {

		$enabled = Env::get('PAIR_LOGBAR_ENABLED');

		if (is_bool($enabled)) {
			return $enabled;
		}

		if (is_int($enabled) or is_float($enabled)) {
			return 0 !== (int)$enabled;
		}

		if (is_string($enabled) and '' !== trim($enabled)) {
			return in_array(strtolower(trim($enabled)), ['1', 'true', 'yes', 'on'], true);
		}

		// Production requests must avoid LogBar collection unless the app opts in explicitly.
		return 'production' !== Application::getEnvironment();

	}

	/**
	 * Normalize an event type for CSS classes and filters.
	 */
	private function normalizeType(string $type): string {

		$type = strtolower(trim($type));
		$type = preg_replace('/[^a-z0-9_-]+/', '-', $type) ?? 'notice';

		return trim($type, '-') ?: 'notice';

	}

	/**
	 * Adds a query event with safe SQL rendering and structured query attributes.
	 *
	 * @param	array<int|string, mixed>	$params	Bound query parameters.
	 */
	final public static function query(string $query, int $rows, array $params = [], ?float $durationMs = null, ?float $startedAt = null): void {

		$self = self::getInstance();

		if (!$self->isEnabled()) {
			return;
		}

		$description = LogBarSql::render($query, $params, self::showSqlValues());
		$subtext = $rows . ' ' . (1 === $rows ? 'row' : 'rows');
		$start = is_null($startedAt) ? null : max(0.0, $startedAt - $self->timeStart);
		$duration = is_null($durationMs) ? null : max(0.0, $durationMs / 1000);

		$self->addEvent($description, 'query', $subtext, self::queryAttributes($query, $rows), null, $start, $duration);

	}

	/**
	 * Return structured query attributes for LogBar aggregation.
	 *
	 * @return	array<string, mixed>
	 */
	private static function queryAttributes(string $query, ?int $rows = null): array {

		return [
			'fingerprint' => LogBarSql::fingerprint($query),
			'normalizedSql' => LogBarSql::normalize($query),
			'operation' => LogBarSql::operation($query),
			'rows' => $rows,
			'table' => LogBarSql::table($query),
		];

	}

	/**
	 * Returns a formatted event list of all chrono steps.
	 */
	final public function render(): string {

		if (!$this->isEnabled() or !$this->canBeShown()) {
			return '';
		}

		$this->registerAssets();

		[$showQueries, $showEvents] = $this->getLogVisibility();
		$limit = $this->getMemoryLimitBytes();

		// Alert about risk of out-of-memory before building the inspector summary.
		if ($limit > 0 and memory_get_usage() / $limit * 100 > self::MEMORY_ALERT_RATIO) {
			self::event('Memory usage is ' . round(memory_get_usage() / $limit * 100, 0) . '% of limit, will reduce logs');
		}

		$inspector = $this->inspector();
		$renderer = $this->renderer($inspector);
		$data = $inspector->collect($this->events, $this->getMicrotime() - $this->timeStart, memory_get_peak_usage(), $limit);

		return $renderer->render($data, $this->routeLabel(), Observability::correlationId(), $showQueries, $showEvents);

	}

	/**
	 * Returns a formatted event list with no header, useful for AJAX purpose.
	 */
	final public function renderForAjax(): string {

		$app = Application::getInstance();
		$router = Router::getInstance();

		if (!$this->isEnabled() or !$this->canBeShown() or !$router->sendLog()) {
			return '';
		}

		// Keep the AJAX payload as event rows only for existing injection code.
		if (Options::get('show_log') and isset($app->currentUser) and $app->currentUser->super) {
			$inspector = $this->inspector();

			return $this->renderer($inspector)->renderForAjax($this->events, $this->getShowQueriesForAjax());
		}

		return '';

	}

	/**
	 * Reset events and start chrono again.
	 */
	final public function reset(): void {

		$this->events = [];
		$this->disabled = false;
		$this->eventLimitWarningAdded = false;
		$this->nextEventId = 1;
		$this->startChrono();

	}

	/**
	 * Register the client asset that powers LogBar controls.
	 */
	final public function registerAssets(string $assetsPath = ''): void {

		if ($this->assetsRegistered) {
			return;
		}

		[$cssPath, $jsPath] = $this->resolveAssetPaths($assetsPath);

		$app = Application::getInstance();

		$app->loadCss($cssPath);
		$app->loadScript($jsPath, true);
		$this->assetsRegistered = true;

	}

	/**
	 * Build the renderer for the active inspector.
	 */
	private function renderer(LogBarInspector $inspector): LogBarRenderer {

		return new LogBarRenderer($inspector);

	}

	/**
	 * Return the public LogBar CSS and JavaScript paths.
	 *
	 * @return	array{0: string, 1: string}
	 */
	private function resolveAssetPaths(string $assetsPath = '', ?string $urlPath = null, ?string $publicPath = null): array {

		$assetsPath = trim($assetsPath);

		if ('' !== $assetsPath) {
			$assetsPath = $this->normalizeAssetsPath($assetsPath, $urlPath);

			return [$assetsPath . '/pair.css', $assetsPath . '/PairLogBar.js'];
		}

		$cssPath = trim((string)Env::get('PAIR_LOGBAR_CSS_PATH'));
		$jsPath = trim((string)Env::get('PAIR_LOGBAR_JS_PATH'));

		return [
			'' !== $cssPath ? $this->normalizePublicAssetPath($cssPath, $urlPath) : $this->defaultCssPath($urlPath, $publicPath),
			'' !== $jsPath ? $this->normalizePublicAssetPath($jsPath, $urlPath) : $this->defaultJsPath($urlPath, $publicPath),
		];

	}

	/**
	 * Return the legacy Pair 3 CSS path when present, otherwise the Pair 4 assets path.
	 */
	private function defaultCssPath(?string $urlPath = null, ?string $publicPath = null): string {

		if ($this->publicAssetExists('css/pair.css', $publicPath)) {
			return 'css/pair.css';
		}

		return $this->normalizeAssetsPath('', $urlPath) . '/pair.css';

	}

	/**
	 * Return the legacy Pair 3 JavaScript path when present, otherwise the Pair 4 assets path.
	 */
	private function defaultJsPath(?string $urlPath = null, ?string $publicPath = null): string {

		if ($this->publicAssetExists('js/PairLogBar.js', $publicPath)) {
			return 'js/PairLogBar.js';
		}

		return $this->normalizeAssetsPath('', $urlPath) . '/PairLogBar.js';

	}

	/**
	 * Return the public assets directory used by Pair 4-style bundled assets.
	 */
	private function normalizeAssetsPath(string $assetsPath, ?string $urlPath = null): string {

		$assetsPath = trim($assetsPath);

		if ('' === $assetsPath) {
			// Default assets must follow applications mounted below the domain root.
			$urlPath = $urlPath ?? ((defined('URL_PATH') and URL_PATH) ? (string)URL_PATH : '');
			$assetsPath = rtrim($urlPath, '/') . '/assets';
		}

		if (preg_match('/^(?:https?:)?\/\//i', $assetsPath)) {
			return rtrim($assetsPath, '/');
		}

		$assetsPath = '/' . trim($assetsPath, '/');

		return '/' === $assetsPath ? '' : $assetsPath;

	}

	/**
	 * Return a normalized public asset file path while preserving app-relative paths.
	 */
	private function normalizePublicAssetPath(string $assetPath, ?string $urlPath = null): string {

		$assetPath = trim($assetPath);

		if (preg_match('/^(?:https?:)?\/\//i', $assetPath)) {
			return $assetPath;
		}

		if (str_starts_with($assetPath, '/')) {
			$urlPath = $urlPath ?? ((defined('URL_PATH') and URL_PATH) ? (string)URL_PATH : '');

			if ('' !== $urlPath and !str_starts_with($assetPath, rtrim($urlPath, '/') . '/')) {
				return rtrim($urlPath, '/') . $assetPath;
			}

			return $assetPath;
		}

		return $assetPath;

	}

	/**
	 * Return true when an app-level public asset already exists.
	 */
	private function publicAssetExists(string $relativePath, ?string $publicPath = null): bool {

		$publicPath = $publicPath ?? ((defined('APPLICATION_PATH') and APPLICATION_PATH) ? APPLICATION_PATH . '/public' : '');

		if ('' === $publicPath) {
			return false;
		}

		return is_file(rtrim($publicPath, '/') . '/' . ltrim($relativePath, '/'));

	}

	/**
	 * Return a safe route label for the header.
	 */
	private function routeLabel(): string {

		$router = Router::getInstance();
		$module = isset($router->module) ? (string)$router->module : '';
		$action = isset($router->action) ? (string)$router->action : '';

		return trim($module . ($action ? '/' . $action : ''), '/');

	}

	/**
	 * Sanitize structured attributes before they are kept for rendering.
	 *
	 * @param	array<string, mixed>	$attributes	Raw event attributes.
	 * @return	array<string, mixed>
	 */
	private function sanitizeAttributes(array $attributes): array {

		$safe = [];

		foreach ($attributes as $name => $value) {

			if (!is_string($name)) {
				continue;
			}

			if (is_string($value)) {
				$safe[$name] = LogBarSql::redactText($value);
			} else if (is_null($value) or is_bool($value) or is_int($value) or is_float($value)) {
				$safe[$name] = $value;
			}

		}

		return $safe;

	}

	/**
	 * Return whether SQL values are explicitly allowed in LogBar previews.
	 */
	private static function showSqlValues(): bool {

		$value = Env::get('PAIR_LOGBAR_SHOW_SQL_VALUES');

		if (is_bool($value)) {
			return $value;
		}

		return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);

	}

	/**
	 * Return the duplicate query count budget.
	 */
	private function duplicateQueryBudget(): int {

		return max(1, (int)(Env::get('PAIR_LOGBAR_DUPLICATE_QUERY_BUDGET') ?? 3));

	}

	/**
	 * Return the event retention cap.
	 */
	private function maxEvents(): int {

		return max(0, (int)(Env::get('PAIR_LOGBAR_MAX_EVENTS') ?? 500));

	}

	/**
	 * Return the query count budget.
	 */
	private function queryBudget(): int {

		return max(1, (int)(Env::get('PAIR_LOGBAR_QUERY_BUDGET') ?? 30));

	}

	/**
	 * Return whether a non-critical event should be dropped because LogBar reached its cap.
	 */
	private function shouldDropEvent(string $type): bool {

		$maxEvents = $this->maxEvents();

		if ($maxEvents <= 0 or count($this->events) < $maxEvents) {
			return false;
		}

		if (in_array($type, ['warning', 'error'], true)) {
			return false;
		}

		if (!$this->eventLimitWarningAdded) {
			$this->eventLimitWarningAdded = true;
			$this->addEvent('LogBar event limit reached; non-critical events are being skipped.', 'warning');
		}

		return true;

	}

	/**
	 * Return the slow query threshold in milliseconds.
	 */
	private function slowQueryMs(): int {

		return max(1, (int)(Env::get('PAIR_LOGBAR_SLOW_QUERY_MS') ?? 20));

	}

	/**
	 * Return the slow request threshold in milliseconds.
	 */
	private function slowRequestMs(): int {

		return max(1, (int)(Env::get('PAIR_LOGBAR_SLOW_REQUEST_MS') ?? 250));

	}

	/**
	 * Starts the time chrono.
	 */
	private function startChrono(): void {

		$this->timeStart = $this->lastChrono = $this->getMicrotime();

	}

	/**
	 * Return the default status for a LogBar event type.
	 */
	private function statusForType(string $type): string {

		return match ($type) {
			'error' => 'error',
			'warning' => 'warning',
			default => 'ok',
		};

	}

	/**
	 * Count stored events by normalized type.
	 */
	private function countEventsByType(string $type): int {

		$count = 0;

		foreach ($this->events as $event) {
			if ($type === $event->type) {
				$count++;
			}
		}

		return $count;

	}

}
