<?php

namespace Pair\Helpers;

use Pair\Core\Application;
use Pair\Core\Router;
use Pair\Models\Session;
use Pair\Models\User;

/**
 * Singleton class that allows to trace query, API calls and custom events with partial
 * and total execution times.
 */
class LogBar {

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = NULL;

	/**
	 * Start time.
	 */
	private float $timeStart;

	/**
	 * Time chrono of last event.
	 */
	private float $lastChrono;

	/**
	 * Full event list.
	 * @var \stdClass[]
	 */
	private array $events = [];

	/**
	 * Flag force log disabled.
	 */
	private bool $disabled = FALSE;

	/**
	 * Disabled constructor.
	 */
	private function __construct() {}

	public function __toString() {

		return $this->render();

	}

	/**
	 * Singleton instance method.
	 */
	final public static function getInstance(): self {

		if (NULL == self::$instance) {
			self::$instance = new self();
			self::$instance->startChrono();
		}

		return self::$instance;

	}

	/**
	 * Shutdown the log.
	 */
	final public function disable(): void {

		$this->disabled = TRUE;

	}

	/**
	 * Check that logBar can be collected by checking "disabled" flag, cli,
	 * API, router module and Options.
	 */
	final public function isEnabled(): bool {

		$router = Router::getInstance();

		if ($this->disabled or 'cli' == php_sapi_name() or 'api' == $router->module
				or ('user' == $router->module and 'login' == $router->action)) {
			return FALSE;
		}

		return TRUE;

	}

	/**
	 * Check if the log can appear in the current session.
	 */
	public function canBeShown(): bool {

		// user is defined, could be admin
		if (User::current() and Options::get('show_log')) {

			// get current session
			$session = Session::current();

			// if impersonating, use the former user attribs
			if ($session->hasFormerUser()) {
				$formerUser = $session->getFormerUser();
				return ($formerUser ? $formerUser->admin : FALSE);
			}

			return (bool)User::current()->admin;

		} else {

			return FALSE;

		}

	}

	/**
	 * Starts the time chrono.
	 */
	private function startChrono(): void {

		$this->timeStart = $this->lastChrono = $this->getMicrotime();

	}

	/**
	 * Returns current time as float value.
	 */
	private function getMicrotime(): float {

		list ($msec, $sec) = explode(' ', microtime());
		$time = ((float)$msec + (float)$sec);
		return $time;

	}

	/**
	 * Adds an event, storing its chrono time.
	 *
	 * @param	string	Event description.
	 * @param	string	Event type notice, query, api, warning or error (default is notice).
	 * @param	NULL|string	Optional additional text.
	 */
	final public static function event(string $description, string $type='notice', ?string $subtext=NULL): void {

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
	 * Returns a formatted event list of all chrono steps.
	 *
	 * @return	string	HTML code of log list.
	 */
	final public function render(): string {

		if (!$this->isEnabled() or !$this->canBeShown()) {
			return '';
		}

		$app = Application::getInstance();

		$sum			= 0;
		$log			= '';

		$apiChrono		= 0;
		$queryChrono	= 0;
		$queryCount		= 0;
		$warningCount	= 0;
		$errorCount		= 0;

		$firstError		= FALSE;
		$firstWarning	= FALSE;

		// cookie infos
		$showQueries	= isset($_COOKIE['LogBarShowQueries']) ? (bool)$_COOKIE['LogBarShowQueries'] : FALSE;
		$showEvents		= isset($_COOKIE['LogBarShowEvents'])  ? (bool)$_COOKIE['LogBarShowEvents']  : FALSE;

		// create a date in timezone of connected user
		$timeStart = new \DateTime('@' . (int)$this->timeStart, new \DateTimeZone(BASE_TIMEZONE));
		if ($app->currentUser and $app->currentUser->areKeysPopulated()) {
			$timeStart->setTimezone($app->currentUser->getDateTimeZone());
		}

		// get memory limit
		$limit = ini_get('memory_limit');

		// get number multiplier
		switch (substr($limit,-1)) {
			case 'G': case 'g': $multiplier = 1073741824;	break;
			case 'M': case 'm': $multiplier = 1048576;		break;
			case 'K': case 'k': $multiplier = 1024;			break;
			default:			$multiplier = 1;			break;
		}

		// convert string memory limit to integer
		$limit = (int)$limit * $multiplier;

		// get current usage and percentual ratio
		$ratio = memory_get_usage() / $limit * 100;

		// alert about risk of “out of memory”
		if (($ratio) > 60) {
			self::event('Memory usage is ' . round($ratio,0) . '% of limit, will reduce logs');
		}

		foreach ($this->events as $e) {

			// update ratio with current memory usage
			$ratio = memory_get_usage() / $limit * 100;

			// prevent out memory errors by adding just warnings and errors
			if ($ratio > 60 and !in_array($e->type, ['warning','error'])) {
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
						$firstWarning = TRUE;
					}
					break;

				// error counter
				case 'error':
					$errorCount++;
					if (!$firstError) {
						$eventDomId = 'id="logFirstError" ';
						$firstError = TRUE;
					}
					break;

			}

			$log .=
				'<div ' . $eventDomId . 'class="' . $e->type . '">' .
				'<span class="time' . ($e->chrono>1 ? ' slow' : '') . '">' . $this->formatChrono($e->chrono) . '</span> ' .
				htmlspecialchars((string)$e->description) .
				($e->subtext ? ' <span>| ' . htmlspecialchars((string)$e->subtext) . '</span>' : '') . '</div>';

			$sum += $e->chrono;

		}

		// log header
		$ret = '<div class="card mt-5" id="logbar">
			<div class="card-header">
				<div class="float-end">';

		if ($showEvents) {
			$ret.= '<div id="toggle-events" class="item expanded">Hide</div>';
		} else {
			$ret.= '<div id="toggle-events" class="item">Show</div>';
		}
		//<a class="p-4 btn btn-sm btn-outline-primary mt-1 float-right" href="compositerewards/new"><i class="fal fa-plus-large fa-fw"></i></a>
		$ret.= '</div>
				<h4>LogBar</h4>
			</div>
			<div class="card-body">
			<div class="head">';

		// total time
		$ret.= '<div class="item"><span class="icon fa fa-tachometer-alt"></span><span class="emph">' . $this->formatChrono($sum) .'</span> total</div>';

		// external api
		$ret.= $apiChrono ? '<div class="item"><span class="icon fa fa-exchange"></span><span class="emph">API ' . $this->formatChrono($apiChrono) . '</span></div>' : '';

		// database
		$ret.= '<div class="item database multi"><div class="icon fa fa-database"></div><div class="desc"><span class="emph">' . $queryCount .'</span> queries <div class="sub">(' . $this->formatChrono($queryChrono) .')</div></div></div>';

		// memory peak
		$ret.= '<div class="item"><span class="icon fa fa-heartbeat"></span><span class="emph">' . floor(memory_get_peak_usage()/1024/1024) . ' MB</span> memory</div>';

		// warnings
		if ($warningCount) {
			$ret.= '<a href="javascript:;" onclick="document.location.hash=\'logFirstWarning\';" class="item warning"><span class="icon fa fa-exclamation-triangle"></span><span class="emph">' . $warningCount . '</span> ' . ($warningCount>1 ? 'warnings' : 'warning') . '</a>';
		}

		// errors
		if ($errorCount) {
			$ret.= '<a href="javascript:;" onclick="document.location.hash=\'logFirstError\';" class="item error"><span class="icon fa fa-times-circle"></span><span class="emph">' . $errorCount . '</span> ' . ($errorCount>1 ? 'errors' : 'error') . '</a>';
		}

		$ret.= '</div>';

		// log content
		$ret.= '<div class="events' . ($showQueries ? ' show-queries' : '') . ($showEvents ? '' : ' hidden') . '">' . $log . '</div>';

		// card-body and card close
		$ret.= '</div></div>';

		return $ret;

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
		if (Options::get('show_log') and $app->currentUser->admin) {

			$showQueries = isset($_COOKIE['LogShowQueries']) ? $_COOKIE['LogShowQueries'] : 0;

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
	 * Choose if use sec or millisec based on amount of time to show (for instance 1.23 s or 345 ms).
	 *
	 * @param	float	Time value to show (in seconds).
	 */
	private function formatChrono(float $chrono): string {

		return ($chrono >= 1) ? round($chrono, 2).' s' : round($chrono*1000) .' ms';

	}

	/**
	 * Returns count of registered error.
	 */
	final public function getErrorCount(): int {

		$count = 0;

		foreach ($this->events as $e) {
			if ('error' == $e->type) $count++;
		}

		return $count;

	}

	/**
	 * Reset events and start chrono again.
	 */
	final public function reset() {

		$this->events = [];
		$this->startChrono();

	}

}