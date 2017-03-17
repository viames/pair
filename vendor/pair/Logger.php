<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

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
	 * @var array:stdClass
	 */
	private $events = array();
	
	/**
	 * Disabled constructor.
	 */
	final private function __construct() {}
	
	/**
	 * Singleton instance method.
	 * 
	 * @return	Logger
	 */
	final public static function getInstance() {
	
		if (NULL == self::$instance) {
			self::$instance = new self();
			self::$instance->startChrono();
		}
		
		return self::$instance;
	
	}

	/**
	 * Checks that logger is enabled by options and group of connected user.
	 * 
	 * @return boolean
	 */
	final public function isEnabled() {
		
		$app = Application::getInstance();
		$route = Router::getInstance();
		
		// no logs on command line and web API
		if ('cli' == php_sapi_name() or 'api' == $route->module) {
			
			return FALSE;
			
		// user is defined, could be admin
		} else if ($app->currentUser) {
			
			$options = Options::getInstance();
			return ($options->getValue('show_log') and $app->currentUser->admin);
		
		// no logs on login page
		} else if ('user' == $route->module and 'login' == $route->action) {
				
			return FALSE;
					
		// no login, no user defined, collects logs
		} else {
			
			return TRUE;
			
		}
		
	}
	
	/**
	 * Starts the time chrono.
	 */
	final private function startChrono() {
		
		$this->timeStart = $this->lastChrono = $this->getMicrotime();
		
		$this->addEvent('Starting Pair framework ' . Application::VERSION . ', base timezone is ' . BASE_TIMEZONE);
		
	}
	
	/**
	 * Returns current time as float value.
	 *
	 * @return float
	 */
	final private function getMicrotime() {
	
		list ($msec, $sec) = explode(' ', microtime());
		$time = ((float)$msec + (float)$sec);
		return $time;
		 
	}
	
	/**
	 * Adds an event, storing its chrono time.
	 * 
	 * @param	string	Event description.
	 * @param	string	Event type notice, query, api, warning or error (default is notice).
	 * @param	string	Optional additional text.
	 */
	final public function addEvent($description, $type='notice', $subtext=NULL) {
		
		if (!$this->isEnabled()) return;
		
		$now = $this->getMicrotime();
		
		$event				= new \stdClass();
		$event->description = $description;
		$event->type		= $type;
		$event->subtext		= $subtext;
		$event->chrono		= abs($now - $this->lastChrono);
		
		$this->events[]		= $event;
		
		$this->lastChrono	= $now;
		
	}
	
	/**
	 * AddEvent’s proxy for warning event creations.
	 *
	 * @param	string	Event description.
	 */
	final public function addWarning($description) {
	
		$this->addEvent($description, 'warning');
	
	}
	
	/**
	 * AddEvent’s proxy for error event creations.
	 *
	 * @param	string	Event description.
	 */
	final public function addError($description) {
	
		$this->addEvent($description, 'error');
	
	}
	
	/**
	 * Returns a formatted event list of all chrono steps.
	 * 
	 * @return	string	HTML code of log list.
	 */
	final public function getEventList() {

		$app = Application::getInstance();
		$route = Router::getInstance();
		
		if (!$this->isEnabled()) {
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
		if ($app->currentUser and $app->currentUser->isPopulated()) {
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
			$this->addWarning('Memory usage is ' . round($ratio,0) . '% of limit, will reduce logs');			
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
				htmlspecialchars($e->description) .
				($e->subtext ? ' <span>| ' . htmlspecialchars($e->subtext) . '</span>' : '') . '</div>';

			$sum += $e->chrono;
			
		}
		
		// log header
		$ret = '<div id="log">';
		$ret.= '<div class="head">';
		
		// log date
		$ret.= '<div class="item"><span class="icon fa fa-clock-o"></span><span class="emph">' . $timeStart->format('Y-m-d H:i:s') . '</span></div>';

		// external api
		$ret.= $apiChrono ? '<div class="item"><span class="icon fa fa-exchange"></span><span class="emph">API ' . $this->formatChrono($apiChrono) . '</span></div>' : '';
		
		// database 
		$ret.= '<div class="item database multi' . ($showQueries ? ' active' : '') . '"><div class="icon fa fa-database"></div><div class="desc"><span class="emph">' . $queryCount .'</span> queries <div class="sub">(' . $this->formatChrono($queryChrono) .')</div></div></div>';
		
		// total time
		$ret.= '<div class="item"><span class="icon fa fa-tachometer"></span><span class="emph">' . $this->formatChrono($sum) .'</span> total</div>';
		
		// memory peak
		$ret.= '<div class="item"><span class="icon fa fa-heartbeat"></span><span class="emph">' . floor(memory_get_peak_usage()/1024/1024) . ' MB</span> memory</div>';

		// warnings
		if ($warningCount) {
			$ret.= '<a href="' . $route->getUrl() . '#logFirstWarning" class="item warning"><span class="icon fa fa-warning"></span><span class="emph">' . $warningCount . '</span> ' . ($warningCount>1 ? 'warnings' : 'warning') . '</a>';
		}
		
		// errors
		if ($errorCount) {
			$ret.= '<a href="' . $route->getUrl() . '#logFirstError" class="item error"><span class="icon fa fa-times-circle"></span><span class="emph">' . $errorCount . '</span> ' . ($errorCount>1 ? 'errors' : 'error') . '</a>';
		}
		
		// logo
		//$ret.= '<div class="logo"></div>';
		
		// show/hide log button
		if ($showEvents) {
			$ret.= '<div id="logShowEvents" class="item">Hide <span class="fa fa-caret-up"></span></div>';
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
	 * @return	string	HTML code of log list.
	 */
	final public function getEventListForAjax() {

		$app		= Application::getInstance();
		$route		= Router::getInstance();
		$options	= Options::getInstance();
		
		if (!$this->isEnabled() or !$route->sendLog()) {
			return NULL;
		}
		
		$log = '';

		// shows the log
		if ($options->getValue('show_log') and $app->currentUser->admin) {
		
			$showQueries = isset($_COOKIE['LogShowQueries']) ? $_COOKIE['LogShowQueries'] : 0;
			
			foreach ($this->events as $e) {
			
				// if it's query, verifies cookie val
				$class = $e->type . (('query'==$e->type and !$showQueries) ? ' hidden' : '');
				
				$log .=
					'<div class="' . $class . '"><span class="time">' .
					$this->formatChrono($e->chrono) . '</span> ' . htmlspecialchars($e->description) .
					($e->subtext ? ' <span>| ' . htmlspecialchars($e->subtext) . '</span>' : '') . '</div>';
				
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
	final private function formatChrono($chrono) {

		return ($chrono >= 1) ? round($chrono, 2).' s' : round($chrono*1000) .' ms';
		
	}

	/**
	 * Returns count of registered error.
	 * 
	 *  @return	int
	 */
	final public function getErrorCount() {
		
		$count = 0;
		
		foreach ($this->events as $e) {
			if ('error' == $e->type) $count++;
		}
		
	}
		
	/**
	 * Reset events and start chrono again.
	 */
	final public function reset() {
		
		$this->events = array();
		$this->startChrono();
		
	}
	
}
