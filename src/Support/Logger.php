<?php

namespace Pair\Support;

use Pair\Core\Application;
use Pair\Core\Router;;
use Pair\Models\Session;
use Pair\Models\User;

/**
 * Singleton class that allows to trace query, API calls and custom events with partial
 * and total execution times.
 */
class Logger {

	/**
	 * Singleton instance.
	 * @var Logger
	 */
	private static $instance = NULL;

	/**
	 * Start time.
	 * @var float
	 */
	private $timeStart;

	/**
	 * Time chrono of last event.
	 * @var float
	 */
	private $lastChrono;

	/**
	 * Full event list.
	 * @var \stdClass[]
	 */
	private $events = [];

	/**
	 * Flag force log disabled.
	 * @var bool
	 */
	private $disabled = FALSE;

	/**
	 * Disabled constructor.
	 */
	private function __construct() {}

	/**
	 * Singleton instance method.
	 *
	 * @return	Logger
	 */
	final public static function getInstance(): Logger {

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
	 * Check that logger can be collected by checking "disabled" flag, cli,
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
	 *
	 * @return bool
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

		self::event('Starting Pair framework with base timezone ' . BASE_TIMEZONE);

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
	 * @param	string	Event description.
	 * @param	string	Event type notice, query, api, warning or error (default is notice).
	 * @param	string	Optional additional text.
	 */
	final public static function event(string $description, string $type='notice', $subtext=NULL): void {

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
	 * AddEvent’s proxy for warning event creations.
	 * @param	string	Event description.
	 */
	final public static function warning(string $description): void {

		self::event($description, 'warning');

	}

	/**
	 * AddEvent’s proxy for error event creations.
	 * @param	string	Event description.
	 */
	final public static function error(string $description): void {

		self::event($description, 'error');

	}

	/**
	 * Returns a formatted event list of all chrono steps.
	 * @return	string|NULL	HTML code of log list.
	 */
	final public function getEventList(): ?string {

		$app = Application::getInstance();

		if (!$this->isEnabled() or !$this->canBeShown()) {
			return NULL;
		}

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
		$showQueries	= isset($_COOKIE['LogShowQueries']) ? (bool)$_COOKIE['LogShowQueries'] : FALSE;
		$showEvents		= isset($_COOKIE['LogShowEvents'])  ? (bool)$_COOKIE['LogShowEvents']  : FALSE;

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
			self::warning('Memory usage is ' . round($ratio,0) . '% of limit, will reduce logs');
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

			// if it's query, verifies cookie val
			$class = $e->type . (('query'==$e->type and !$showQueries) ? ' hidden' : '');

			$log .=
				'<div ' . $eventDomId . 'class="' . $class . '">' .
				'<span class="time' . ($e->chrono>1 ? ' slow' : '') . '">' . $this->formatChrono($e->chrono) . '</span> ' .
				htmlspecialchars((string)$e->description) .
				($e->subtext ? ' <span>| ' . htmlspecialchars((string)$e->subtext) . '</span>' : '') . '</div>';

			$sum += $e->chrono;

		}

		// log header
		$ret = '<div id="log">';
		$ret.= '<div class="head">';

		// log date
		$ret.= '<div class="item"><span class="icon fa fa-clock"></span><span class="emph">' . $timeStart->format('Y-m-d H:i:s') . '</span></div>';

		// external api
		$ret.= $apiChrono ? '<div class="item"><span class="icon fa fa-exchange"></span><span class="emph">API ' . $this->formatChrono($apiChrono) . '</span></div>' : '';

		// database
		$ret.= '<div class="item database multi' . ($showQueries ? ' active' : '') . '"><div class="icon fa fa-database"></div><div class="desc"><span class="emph">' . $queryCount .'</span> queries <div class="sub">(' . $this->formatChrono($queryChrono) .')</div></div></div>';

		// total time
		$ret.= '<div class="item"><span class="icon fa fa-tachometer-alt"></span><span class="emph">' . $this->formatChrono($sum) .'</span> total</div>';

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

		// show/hide log button
		if ($showEvents) {
			$ret.= '<div id="logShowEvents" class="item active">Hide <span class="fa fa-caret-up"></span></div>';
		} else {
			$ret.= '<div id="logShowEvents" class="item">Show <span class="fa fa-caret-down"></span></div>';
		}

		$ret.= '</div>';

		// log content
		$ret.= '<div class="events' . ($showEvents ? '' : ' hidden') . '">' . $log . '</div></div>';

		return $ret;

	}

	/**
	 * Returns a formatted event list with no header, useful for AJAX purpose.
	 *
	 * @return	string|NULL	HTML code of log list.
	 */
	final public function getEventListForAjax(): ?string {

		$app	= Application::getInstance();
		$router	= Router::getInstance();

		if (!$this->isEnabled() or !$this->canBeShown() or !$router->sendLog()) {
			return NULL;
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
	 * @return	string
	 */
	private function formatChrono($chrono): string {

		return ($chrono >= 1) ? round($chrono, 2).' s' : round($chrono*1000) .' ms';

	}

	/**
	 * Returns count of registered error.
	 *
	 * @return	int
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
