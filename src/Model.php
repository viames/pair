<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

abstract class Model {
	
	/**
	 * Application object.
	 * @var Application
	 */
	protected $app;
	
	/**
	 * Pagination object, started from the View.
	 * @var object
	 */
	private $pagination;

	/**
	 * Database handler object.
	 * @var Database
	 */
	private $db;

	/**
	 * List of all errors tracked.
	 * @var array
	 */
	private $errors = array();
	
	/**
	 * Constructor, connects to db.
	*/
	final public function __construct() {
		
		// singleton objects
		$this->app	= Application::getInstance();
		$this->db	= Database::getInstance();

	}
	
	public function __get($name) {
		
		return $this->$name;
		
	}

	public function __set($name, $value) {
	
		$this->$name = $value;
	
	}

	/**
	 * Management of unknown model's function.
	 * 
	 * @param	string	$name
	 * @param	array	$arguments
	 */
	public function __call($name, $arguments) {
		
		$options = Options::getInstance();
		
		if ($options->getValue('development')) {
	
			$backtrace = debug_backtrace();
			$this->app->logError('Method '. get_called_class() . $backtrace[0]['type'] . $name .'(), which doesn’t exist, has been called by '. $backtrace[0]['file'] .' on line '. $backtrace[0]['line']);
		
		}		

	}
	 
	/**
	 * Adds an error to error list.
	 * 
	 * @param	string	Error message’s text.
	 */
	public function addError($message) {
		
		$this->errors[] = $message;
		
	}
	
	/**
	 * Returns text of latest error. In case of no errors, returns FALSE.
	 * 
	 * @return FALSE|string
	 */
	public function getLastError() {
		
		return end($this->errors);
		
	}
	
	/**
	 * Returns an array with text of all errors.
	 *
	 * @return array
	 */
	public function getErrors() {
	
		return $this->errors;
	
	}
	
	/**
	 * Adds an event to framework’s logger, storing its chrono time.
	 * 
	 * @param	string	Event description.
	 * @param	string	Event type notice or error (default notice).
	 * @param	string	Optional additional text.
	 */
	public function logEvent($description, $type='notice', $subtext=NULL) {
		
		$this->app->logEvent($description, $type, $subtext);
		
	}
	
	/**
	 * AddEvent’s proxy for warning event creations.
	 *
	 * @param	string	Event description.
	 */
	public function logWarning($description) {
	
		$this->app->logWarning($description);
	
	}
	
	/**
	 * AddEvent’s proxy for error event creations.
	 *
	 * @param	string	Event description.
	 */
	public function logError($description) {
	
		$this->app->logError($description);
	
	}
	
	/**
	 * Returns list of all object specified in param, within pagination limit and sets
	 * pagination count.
	 *
	 * @param	string	Name of desired class.
	 * @param	string	Ordering db field.
	 * @param	bool	Sorting direction ASC or DESC (optional)
	 * 
	 * @return	array:multitype
	 */
	public function getActiveRecordObjects($class, $orderBy=NULL, $descOrder=FALSE) {

		if (!class_exists($class) or !is_subclass_of($class, 'Pair\ActiveRecord')) {
			return array();
		}
		
		// sets pagination count
		$this->db->setQuery('SELECT COUNT(*) FROM ' . $class::TABLE_NAME);
		$this->pagination->count = $this->db->loadCount();
	
		$orderDir = $descOrder ? 'DESC' : 'ASC';
		
		$query =
			'SELECT *' .
			' FROM ' . $class::TABLE_NAME .
			($orderBy ? ' ORDER BY `' . $orderBy . '` ' . $orderDir : NULL) .
			' LIMIT ' . $this->pagination->start . ', ' . $this->pagination->limit;
	
		$this->db->setQuery($query);
		$list = $this->db->loadObjectList();
	
		$objects = array();
	
		foreach ($list as $item) {
			$objects[] = new $class($item);
		}

		return $objects;
	
	}
	
	/**
	 * Return empty array as default in case isn’t overloaded by children class.
	 * 
	 * @return	array
	 */
	protected function getOrderOptions() {
		
		return array();
		
	}
	
	/**
	 * Create SQL code about ORDER and LIMIT.
	 * 
	 * @return string
	 */
	protected function getOrderLimitSql() {

		$route = Router::getInstance();
		
		$ret = '';
		
		if ($route->order) {
			$orderOptions = $this->getOrderOptions();
			if (isset($orderOptions[$route->order])) { 
				$ret = ' ORDER BY ' . $orderOptions[$route->order];
			}
		}

		$ret .= ' LIMIT ' . $this->pagination->start . ', ' . $this->pagination->limit;
		
		return $ret;
		
	}
	
}
