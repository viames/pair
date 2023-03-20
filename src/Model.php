<?php

namespace Pair;

abstract class Model {

	/**
	 * Application object.
	 * @var Application
	 */
	protected $app;

	/**
	 * Pagination object, started from the View.
	 * @var Pagination
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
	private $errors = [];

	/**
	 * Constructor, connects to db.
	*/
	final public function __construct() {

		// singleton objects
		$this->app	= Application::getInstance();

		$this->db	= Database::getInstance();

		$this->init();

	}

	public function __get(string $name) {

		return $this->$name;

	}

	public function __set(string $name, $value) {

		$this->$name = $value;

	}

	/**
	 * Management of unknown model's function.
	 *
	 * @param	string	$name
	 * @param	array	$arguments
	 */
	public function __call(string $name, array $arguments) {

		if (Application::isDevelopmentHost()) {

			$backtrace = debug_backtrace();
			Logger::error('Method '. get_called_class() . $backtrace[0]['type'] . $name .'(), which doesn’t exist, has been called by '. $backtrace[0]['file'] .' on line '. $backtrace[0]['line']);

		}

	}

	/**
	 * Start function, being executed before each method. Optional.
	 */
	protected function init() {}

	/**
	 * Adds an error to error list.
	 *
	 * @param	string	Error message’s text.
	 */
	public function addError(string $message) {

		$this->errors[] = $message;

	}

	/**
	 * Returns text of latest error. In case of no errors, returns FALSE.
	 *
	 * @return mixed
	 */
	public function getLastError() {

		return end($this->errors);

	}

	/**
	 * Returns an array with text of all errors.
	 *
	 * @return array
	 */
	public function getErrors(): array {

		return $this->errors;

	}

	/**
	 * Adds an event to framework’s logger, storing its chrono time.
	 *
	 * @param	string	Event description.
	 * @param	string	Event type notice or error (default notice).
	 * @param	string	Optional additional text.
	 * @deprecated		Use static method Logger::event() instead.
	 */
	public function logEvent(string $description, string $type='notice', string $subtext=NULL) {

		Logger::event($description, $type, $subtext);

	}

	/**
	 * AddEvent’s proxy for warning event creations.
	 *
	 * @param	string	Event description.
	 * @deprecated		Use static method Logger::warning() instead.
	 */
	public function logWarning(string $description) {

		Logger::warning($description);

	}

	/**
	 * AddEvent’s proxy for error event creations.
	 *
	 * @param	string	Event description.
	 * @deprecated		Use static method Logger::error() instead.
	 */
	public function logError(string $description) {

		Logger::error($description);

	}

	/**
	 * Returns list of all object specified in param, within pagination limit and sets
	 * pagination count.
	 *
	 * @param	string	Name of desired class.
	 * @param	string	Ordering db field.
	 * @param	bool	Sorting direction ASC or DESC (optional)
	 * @return	mixed[]
	 */
	public function getActiveRecordObjects(string $class, string $orderBy=NULL, bool $descOrder=FALSE): array {

		if (!class_exists($class) or !is_subclass_of($class, 'Pair\ActiveRecord')) {
			return array();
		}

		// set pagination count
		$this->pagination->count = $class::countAllObjects();

		$orderDir = $descOrder ? 'DESC' : 'ASC';

		$query =
			'SELECT *' .
			' FROM `' . $class::TABLE_NAME . '`' .
			($orderBy ? ' ORDER BY `' . $orderBy . '` ' . $orderDir : NULL) .
			' LIMIT ' . $this->pagination->start . ', ' . $this->pagination->limit;

		return $class::getObjectsByQuery($query);

	}

	/**
	 * Return empty array as default in case isn’t overloaded by children class.
	 */
	protected function getOrderOptions(): array {

		return [];

	}

	/**
	 * Create SQL code about ORDER and LIMIT.
	 */
	protected function getOrderLimitSql() {

		$ret = '';

		$router = Router::getInstance();

		// retrieves any options defined in the child model
		$orderOptions = $this->getOrderOptions();

		// sort according to the router param or the default first option
		if (count($orderOptions)) {
			$sortColumn = $router->order ?? 1;
			if (isset($orderOptions[$sortColumn])) {
				$ret = ' ORDER BY ' . $orderOptions[$sortColumn];
			}
		}

		$ret .= ' LIMIT ' . $this->pagination->start . ', ' . $this->pagination->limit;

		return $ret;

	}

	/**
	 * Returns object list with pagination by running the query in getQuery() method.
	 *
	 * @param	string		Active record class name.
	 * @param	string|NULL	Optional query.
	 * @return	array
	 */
	public function getItems(string $class, string $optionalQuery=NULL): array {

		// class must inherit Pair\ActiveRecord
		if (!class_exists($class) or !is_subclass_of($class, 'Pair\ActiveRecord')) {
			return [];
		}

		$query = $optionalQuery ?? $this->getQuery($class) . $this->getOrderLimitSql();

		return $class::getObjectsByQuery($query, []);

	}

	/**
	 * Returns count of available objects.
	 *
	 * @param	string		Active record class name.
	 * @param	string|NULL	Optional query.
	 * @return	int
	 */
	public function countItems(string $class, string $optionalQuery=NULL): int {

		$query = $optionalQuery ? $optionalQuery : $this->getQuery($class);
		return (int)Database::load('SELECT COUNT(1) FROM (' . $query . ') AS `result`', [], PAIR_DB_COUNT);

	}

	/**
	 * Create and return the SQL to retrieve the elements of the default item list.
	 *
	 * @param	string	ActiveRecord’s class name.
	 * @return	string
	 */
	protected function getQuery(string $class): string {

		// assembla la query
		return 'SELECT * FROM `' . $class::TABLE_NAME . '`';

	}

}
