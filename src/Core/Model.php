<?php

namespace Pair\Core;

use Pair\Exceptions\ErrorCodes;
use Pair\Html\Pagination;
use Pair\Orm\Collection;
use Pair\Orm\Database;
use Pair\Orm\Query;

abstract class Model {

	use \Pair\Traits\AppTrait;
	use \Pair\Traits\LogTrait;

	/**
	 * Application object.
	 */
	protected Application $app;

	/**
	 * Pagination object, started from the View.
	 */
	private ?Pagination $pagination = null;

	/**
	 * Database handler object.
	 */
	private Database $db;

	/**
	 * List of all errors tracked.
	 */
	private array $errors = [];

	/**
	 * Constructor, connects to db.
	*/
	final public function __construct() {

		// singleton objects
		$this->app = Application::getInstance();

		$this->db = Database::getInstance();

		try {
			$this->_init();
		} catch (\Exception $e) {

		}

	}

	/**
	 * Returns property’s value or null.
	 *
	 * @param	string	Property’s name.
	 * @throws	\Exception	If property doesn’t exist.
	 */
	public function __get(string $name): mixed {

		if (!property_exists($this, $name)) {
			throw new \Exception('Property “'. $name .'” doesn’t exist for '. get_called_class(), ErrorCodes::PROPERTY_NOT_FOUND);
		}
		
		return isset($this->$name) ? $this->$name : null;
	
	}

	public function __set(string $name, $value): void {

		$this->$name = $value;

	}

	/**
	 * Management of unknown model's function.
	 *
	 * @param	string	$name
	 * @param	array	$arguments
	 * @throws	\Exception
	 */
	public function __call(string $name, array $arguments): void {

		throw new \Exception('Method '. get_called_class() . '->' . $name .'(), which doesn’t exist, has been called', ErrorCodes::METHOD_NOT_FOUND);

	}

	/**
	 * Start function, being executed before each method. Optionally implemented by inherited classes.
	 */
	protected function _init(): void {}

	/**
	 * Adds an error to error list.
	 *
	 * @param	string	Error message’s text.
	 */
	public function addError(string $message): void {

		$this->errors[] = $message;

	}

	/**
	 * Returns text of latest error. In case of no errors, returns false.
	 */
	public function getLastError(): array|bool {

		return end($this->errors);

	}

	/**
	 * Returns an array with text of all errors.
	 */
	public function getErrors(): array {

		return $this->errors;

	}

	/**
	 * Returns list of all object specified in param, within pagination limit and sets
	 * pagination count.
	 * 
	 * @param	string	Name of desired class.
	 * @param	string	Ordering db field.
	 * @param	bool	Sorting direction ASC or DESC (optional)
	 */
	public function getActiveRecordObjects(string $class, ?string $orderBy = null, bool $descOrder = false): Collection {

		if (!class_exists($class) or !is_subclass_of($class, 'Pair\Orm\ActiveRecord')) {
			throw new \Exception('Class ' . $class . ' not found or not a subclass of Pair\Orm\ActiveRecord');
		}

		$this->pagination->count = $class::countAllObjects();

		$orderDir = $descOrder ? 'DESC' : 'ASC';

		$query =
			'SELECT *
			FROM `' . $class::TABLE_NAME . '`
			' . ($orderBy ? ' ORDER BY `' . $orderBy . '` ' . $orderDir : null) . '
			LIMIT ' . $this->pagination->start . ', ' . $this->pagination->limit;

		return $class::getObjectsByQuery($query);

	}

	/**
	 * Return empty array as default in case isn’t overloaded by children class.
	 */
	protected function getOrderOptions(): array {

		return [];

	}

	/**
	 * Returns SQL for ordering and limiting results based on the current pagination settings
	 * and the order options defined by parameter (priority) or defined in the child model.
	 */
	protected function getOrderLimitSql(array $orderOptions = []): string {

		$ret = '';

		$router = Router::getInstance();

		// retrieves any options defined in the child model
		$orderOptions = count($orderOptions) ? $orderOptions : $this->getOrderOptions();

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
	 * @param	Query|string	Optional query.
	 */
	public function getItems(string $class, Query|string|null $optionalQuery = null): Collection {

		// class must inherit Pair\Orm\ActiveRecord
		if (!class_exists($class) or !is_subclass_of($class, 'Pair\Orm\ActiveRecord')) {
			return new Collection();
		}

		$query = $optionalQuery ?? $this->getQuery($class) . $this->getOrderLimitSql();

		return $class::getObjectsByQuery($query, []);

	}

	/**
	 * Returns count of available objects.
	 * 
	 * @param	string		Active record class name.
	 * @param	Query|string	Optional query.
	 */
	public function countItems(string $class, Query|string|null $optionalQuery = null): int {

		$type = gettype($optionalQuery);

		switch ($type) {
			case 'object':
				$query = $optionalQuery->toSql();
				break;

			case 'string':
				$query = $optionalQuery;
				break;

			default:
				$query = $this->getQuery($class);
		}

		return Database::load('SELECT COUNT(1) FROM (' . $query . ') AS `result`', [], Database::COUNT);

	}

	/**
	 * Create and return the SQL to retrieve the elements of the default item list.
	 * 
	 * @param	string	ActiveRecord’s class name.
	 */
	protected function getQuery(string $class): Query|string {

		return 'SELECT * FROM `' . $class::TABLE_NAME . '`';

	}

}