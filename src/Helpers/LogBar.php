<?php

namespace Pair\Helpers;

use Pair\Core\Application;
use Pair\Core\Router;
use Pair\Models\Session;
use Pair\Models\User;

/**
 * Singleton helper class to collect and render application logs. It allows to trace query,
 * API calls and custom events with partial and total execution times.
 */
class LogBar {

	/**
	 * Memory usage ratio percent above which only warnings and errors are logged.
	 */
	private const MEMORY_ALERT_RATIO = 60.0;

	/**
	 * Cookie names for log visibility.
	 */
	private const COOKIE_SHOW_QUERIES = 'LogBarShowQueries';

	/**
	 * Legacy cookie name for log visibility.
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
	 * Start time.
	 */
	private float $timeStart;

	/**
	 * Time chrono of last event.
	 */
	private float $lastChrono;

	/**
	 * Logged events.
	 * @var \stdClass[]
	 */
	private array $events = [];

	/**
	 * Disabled flag.
	 */
	private bool $disabled = false;

	/**
	 * Disabled constructor.
	 */
	private function __construct() {}

	public function __toString() {

		return $this->render();

	}

	/**
	 * Build HTML for a single event.
	 *
	 * @param	\stdClass	$event		Event object.
	 * @param	string		$eventDomId	Event DOM id attribute.
	 * @return	string		HTML code of event.
	 */
	private function buildEventHtml(\stdClass $event, string $eventDomId): string {

		return
			'<div ' . $eventDomId . 'class="' . $event->type . '">' .
			'<span class="time' . ($event->chrono>1 ? ' slow' : '') . '">' . $this->formatChrono($event->chrono) . '</span> ' .
			htmlspecialchars((string)$event->description) .
			($event->subtext ? ' <span>| ' . htmlspecialchars((string)$event->subtext) . '</span>' : '') . '</div>';

	}

	/**
	 * Build HTML for the full log card.
	 *
	 * @param	float	$sum			Total time.
	 * @param	float	$apiChrono		Total API time.
	 * @param	int		$queryCount		Number of queries.
	 * @param	float	$queryChrono	Total query time.
	 * @param	int		$warningCount	Number of warnings.
	 * @param	int		$errorCount		Number of errors.
	 * @param	bool	$showQueries	Flag to show queries.
	 * @param	bool	$showEvents		Flag to show events.
	 * @param	string	$log			HTML code of log events.
	 * @return	string	HTML code of full log card.
	 */
	private function buildLogHtml(
		float $sum,
		float $apiChrono,
		int $queryCount,
		float $queryChrono,
		int $warningCount,
		int $errorCount,
		bool $showQueries,
		bool $showEvents,
		string $log
	): string {

		// card open and header
		$ret = '<div class="card mt-5" id="logbar">
			<div class="card-header">
				<div class="float-end">';

		// show queries toggle
		$ret .= $showEvents
			? '<div id="toggle-events" class="item expanded">Hide</div>'
			: '<div id="toggle-events" class="item">Show</div>';

		// card header close
		$ret .= '</div>
				<h4>LogBar</h4>
			</div>
			<div class="card-body">
			<div class="head">';

		// total time
		$ret .= '<div class="item"><span class="icon fa fa-tachometer-alt"></span><span class="emph">' . $this->formatChrono($sum) .'</span> total</div>';

		// external api
		$ret .= $apiChrono ? '<div class="item"><span class="icon fa fa-exchange"></span><span class="emph">API ' . $this->formatChrono($apiChrono) . '</span></div>' : '';

		// database
		$ret .= '<div class="item database multi"><div class="icon fa fa-database"></div><div class="desc"><span class="emph">' . $queryCount .'</span> queries <div class="sub">(' . $this->formatChrono($queryChrono) .')</div></div></div>';

		// memory peak
		$ret .= '<div class="item"><span class="icon fa fa-heartbeat"></span><span class="emph">' . floor(memory_get_peak_usage()/1024/1024) . ' MB</span> memory</div>';

		// warnings
		if ($warningCount) {
			$ret .= '<a href="javascript:;" onclick="document.location.hash=\'logFirstWarning\';" class="item warning"><span class="icon fa fa-exclamation-triangle"></span><span class="emph">' . $warningCount . '</span> ' . ($warningCount>1 ? 'warnings' : 'warning') . '</a>';
		}

		// errors
		if ($errorCount) {
			$ret .= '<a href="javascript:;" onclick="document.location.hash=\'logFirstError\';" class="item error"><span class="icon fa fa-times-circle"></span><span class="emph">' . $errorCount . '</span> ' . ($errorCount>1 ? 'errors' : 'error') . '</a>';
		}

		// head and header close
		$ret .= '</div>';

		// log content
		$ret .= '<div class="events' . ($showQueries ? ' show-queries' : '') . ($showEvents ? '' : ' hidden') . '">' . $log . '</div>';

		// card-body and card close
		$ret .= '</div></div>';

		return $ret;

	}

	/**
	 * Check if the log can appear in the current session.
	 *
	 * @return	bool	True if can be shown.
	 */
	public function canBeShown(): bool {

		// user is defined, could be super
		if (User::current() and Options::get('show_log')) {

			// get current session
			$session = Session::current();

			// if impersonating, use the former user attribs
			if ($session and $session->hasFormerUser()) {
				$formerUser = $session->getFormerUser();
				return ($formerUser ? $formerUser->super : false);
			}

			return (bool)User::current()->super;

		} else {

			return false;

		}

	}

	/**
	 * Shutdown the log.
	 */
	final public function disable(): void {

		$this->disabled = true;

	}

	/**
	 * Singleton instance method.
	 *
	 * @return	self	Instance of LogBar.
	 */
	final public static function getInstance(): self {

		if (null == self::$instance) {
			self::$instance = new self();
			self::$instance->startChrono();
		}

		return self::$instance;

	}

	/**
	 * Adds an event, storing its chrono time.
	 *
	 * @param	string	Event description.
	 * @param	string	Event type notice, query, api, warning or error (default is notice).
	 * @param	null|string	Optional additional text.
	 */
	final public static function event(string $description, string $type = 'notice', ?string $subtext = null): void {

		$self = self::getInstance();

		if (!$self->isEnabled()) return;

		$now = $self->getMicrotime();

		$event				= new \stdClass();
		$event->description = $description;
		$event->type		= $type;
		$event->subtext		= $subtext;
		$event->chrono		= abs($now - $self->lastChrono);

		$self->events[]		= $event;

		$self->lastChrono	= $now;

	}

	/**
	 * Choose if use sec or millisec based on amount of time to show (for instance 1.23 s or 345 ms).
	 *
	 * @param	float	$chrono	Time in seconds.
	 * @return	string	Formatted time string.
	 */
	private function formatChrono(float $chrono): string {

		return ($chrono >= 1) ? round($chrono, 2).' s' : round($chrono*1000) .' ms';

	}

	/**
	 * Returns count of registered error.
	 *
	 * @return	int	Number of errors.
	 */
	final public function getErrorCount(): int {

		$count = 0;

		foreach ($this->events as $e) {
			if ('error' == $e->type) $count++;
		}

		return $count;

	}

	/**
	 * Returns [showQueries, showEvents] cookie settings.
	 *
	 * @return	array{0: bool, 1: bool}
	 */
	private function getLogVisibility(): array {

		$showQueries = $this->getCookieBool(self::COOKIE_SHOW_QUERIES);
		$showEvents = $this->getCookieBool(self::COOKIE_SHOW_EVENTS);

		return [$showQueries, $showEvents];

	}

	/**
	 * Returns a cookie value as bool.
	 *
	 * @param	string	$name	Cookie name.
	 * @return	bool	Cookie value or false.
	 */
	private function getCookieBool(string $name): bool {

		return isset($_COOKIE[$name]) ? (bool)$_COOKIE[$name] : false;

	}

	/**
	 * Returns showQueries cookie, with legacy fallback.
	 *
	 * @return	bool	True if queries should be shown.
	 */
	private function getShowQueriesForAjax(): bool {

		if (isset($_COOKIE[self::COOKIE_SHOW_QUERIES])) {
			return (bool)$_COOKIE[self::COOKIE_SHOW_QUERIES];
		}

		return isset($_COOKIE[self::LEGACY_COOKIE_SHOW_QUERIES]) ? (bool)$_COOKIE[self::LEGACY_COOKIE_SHOW_QUERIES] : false;

	}

	/**
	 * Returns memory limit in bytes.
	 *
	 * @return	int	Memory limit in bytes.
	 */
	private function getMemoryLimitBytes(): int {

		$limit = ini_get('memory_limit');
		if ('-1' === $limit) {
			return 0;
		}

		// get number multiplier
		switch (substr($limit, -1)) {
			case 'G': case 'g': $multiplier = 1073741824;	break;
			case 'M': case 'm': $multiplier = 1048576;		break;
			case 'K': case 'k': $multiplier = 1024;			break;
			default:			$multiplier = 1;			break;
		}

		// convert string memory limit to integer
		return (int)$limit * $multiplier;

	}

	/**
	 * Returns current memory usage ratio in percent.
	 *
	 * @param	int	$limit	Memory limit in bytes.
	 * @return	float	Memory usage ratio in percent.
	 */
	private function getMemoryUsageRatio(int $limit): float {

		if ($limit <= 0) {
			return 0.0;
		}

		return memory_get_usage() / $limit * 100;

	}

	/**
	 * Returns current time as float value.
	 *
	 * @return	float	Current time in seconds with microseconds.
	 */
	private function getMicrotime(): float {

		return microtime(true);

	}

	/**
	 * Check that logBar can be collected by checking "disabled" flag, cli,
	 * API, router module and Options.
	 *
	 * @return	bool	True if enabled.
	 */
	final public function isEnabled(): bool {

		$router = Router::getInstance();

		if ($this->disabled or 'cli' == php_sapi_name() or 'api' == $router->module
				or ('user' == $router->module and 'login' == $router->action)) {
			return false;
		}

		return true;

	}

	/**
	 * Returns a formatted event list of all chrono steps.
	 *
	 * @return	string	HTML code of log list.
	 */
	final public function render(): string {

		// check if log can be shown 
		if (!$this->isEnabled() or !$this->canBeShown()) {
			return '';
		}

		$sum = 0;
		$log = '';

		$apiChrono = 0;
		$queryChrono = 0;
		$queryCount = 0;
		$warningCount = 0;
		$errorCount = 0;

		// flags to mark first warning/error
		$firstError = false;
		$firstWarning = false;

		// get log visibility settings
		[$showQueries, $showEvents] = $this->getLogVisibility();

		// memory limit and usage ratio
		$limit = $this->getMemoryLimitBytes();
		$checkMemory = ($limit > 0);
		$ratio = $checkMemory ? $this->getMemoryUsageRatio($limit) : 0.0;

		// alert about risk of “out of memory”
		if ($checkMemory and $ratio > self::MEMORY_ALERT_RATIO) {
			self::event('Memory usage is ' . round($ratio,0) . '% of limit, will reduce logs');
		}

		foreach ($this->events as $e) {

			// update ratio with current memory usage
			if ($checkMemory) {
				$ratio = $this->getMemoryUsageRatio($limit);
			}

			// prevent out memory errors by adding just warnings and errors
			if ($checkMemory and $ratio > self::MEMORY_ALERT_RATIO and !in_array($e->type, ['warning','error'])) {
				continue;
			}

			$eventDomId = '';

			switch ($e->type) {

				// external API-call counters
				case 'api':
					$apiChrono += $e->chrono;
					break;

				// SQL queries counters
				case 'query':
					$queryCount++;
					$queryChrono += $e->chrono;
					break;

				// warning counter
				case 'warning':
					$warningCount++;
					if (!$firstWarning) {
						$eventDomId = 'id="logFirstWarning" ';
						$firstWarning = true;
					}
					break;

				// error counter
				case 'error':
					$errorCount++;
					if (!$firstError) {
						$eventDomId = 'id="logFirstError" ';
						$firstError = true;
					}
					break;

			}

			// build event HTML
			$log .= $this->buildEventHtml($e, $eventDomId);

			$sum += $e->chrono;

		}

		// log header
		return $this->buildLogHtml(
			$sum,
			$apiChrono,
			$queryCount,
			$queryChrono,
			$warningCount,
			$errorCount,
			$showQueries,
			$showEvents,
			$log
		);

	}

	/**
	 * Returns a formatted event list with no header, useful for AJAX purpose.
	 *
	 * @return	string	HTML code of log list.
	 */
	final public function renderForAjax(): string {

		$app	= Application::getInstance();
		$router	= Router::getInstance();

		if (!$this->isEnabled() or !$this->canBeShown() or !$router->sendLog()) {
			return '';
		}

		$log = '';

		// shows the log
		if (Options::get('show_log') and $app->currentUser->super) {

			$showQueries = $this->getShowQueriesForAjax();

			foreach ($this->events as $e) {

				// if it's query, verifies cookie val
				$class = $e->type . (('query'==$e->type and !$showQueries) ? ' hidden' : '');

				$log .=
					'<div class="' . $class . '"><span class="time">' .
					$this->formatChrono($e->chrono) . '</span> ' . htmlspecialchars((string)$e->description) .
					($e->subtext ? ' <span>| ' . htmlspecialchars((string)$e->subtext) . '</span>' : '') . '</div>';

			}

		}

		return $log;

	}

	/**
	 * Reset events and start chrono again.
	 */
	final public function reset(): void {

		$this->events = [];
		$this->startChrono();

	}

	/**
	 * Starts the time chrono.
	 */
	private function startChrono(): void {

		$this->timeStart = $this->lastChrono = $this->getMicrotime();

	}

}
