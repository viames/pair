<?php

namespace Pair\Orm;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Core\Logger;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Helpers\Post;
use Pair\Helpers\Translator;
use Pair\Helpers\Utilities;
use Pair\Html\Form;

/**
 * Base class for active record pattern. Supports tables with a primary key, not suitable for compound key.
 */
abstract class ActiveRecord implements \JsonSerializable {

	/**
	 * Db handler object.
	 */
	protected Database $db;

	/**
	 * List of properties that maps db primary keys.
	 */
	protected array $keyProperties = [];

	/**
	 * TRUE if object has been loaded from database.
	 */
	private bool $loadedFromDb = FALSE;

	/**
	 * List of special properties that will be cast (name => type).
	 */
	private array $typeList = [];

	/**
	 * Cache for any variable type.
	 */
	private array $cache = [];

	/**
	 * List of all errors tracked.
	 */
	private array $errors = [];

	/**
	 * Keep track of update properties name.
	 */
	private array $updatedProperties = [];

	/**
	 * List of dynamic properties, deprecated by PHP 8.2 onwards.
	 */
	private array $dynamicProperties = [];

	/**
	 * Return the table name of the object.
	 */
	const TABLE_KEY = '';

	/**
	 * Return the table name of the object.
	 */
	const TABLE_NAME = '';

	/**
	 * Table structure [Field => Type, Null, Key, Default, Extra].
	 */
	const TABLE_DESCRIPTION = [];

	/**
	 * List of columns that stores encrypted data.
	 */
	const ENCRYPTABLES = [];

	/**
	 * List of table foreign keys.
	 */
	const FOREIGN_KEYS = [];

	/**
	 * List of properties that relates to other ActiveRecord’s classes.
	 */
	const SHARED_CACHE_PROPERTIES = [];

	/**
	 * Constructor, if param is db-row, will bind it on this object, if it’s id,
	 * with load the object data from db, otherwise the object will be empty.
	 *
	 * @param	mixed	Record object from db table or just table key value (int, string or array, optional).
	 */
	final public function __construct($initParam=NULL) {

		// get DB instance
		$this->db = Database::getInstance();

		// initialize class name and property binds
		$class = get_called_class();
		$binds = $class::getBinds();

		$tableKey = (array)$class::TABLE_KEY;

		// initialize property name
		$this->keyProperties = [];

		// find and assign each field of compound key as array item
		foreach ($tableKey as $field) {
			$this->keyProperties[] = array_search($field, $binds);
		}

		// load any table description
		if (count(static::TABLE_DESCRIPTION)) {
			$this->db->setTableDescription(static::TABLE_NAME, static::TABLE_DESCRIPTION);
		}

		try {
			$this->_init();
		} catch (\Exception $e) {

		}

		// db row, will populate each property with bound field value
		if (is_a($initParam, '\stdClass')) {

			$this->populate($initParam);

		// primary or compound key, loads the whole object from db
		} else if (is_int($initParam) or (is_string($initParam) and strlen($initParam)>0)
				or (static::hasCompoundKey() and is_array($initParam) and count((array)$this->keyProperties) == count($initParam))) {

			// try to load the object from db
			if (!$this->loadFromDb($initParam)) {

				// force init params to array
				$initParam = (array)$initParam;

				// populate this object with passed key properties
				foreach((array)$this->keyProperties as $index => $prop) {
					$this->__set($prop, isset($initParam[$index]) ? $initParam[$index] : NULL);
				}

			}

		}

	}

	/**
	 * Handles calls to dummy methods that return objects linked by foreign keys of the db between
	 * the table of this object and other tables in two directions. Furthermore, it prevents fatal
	 * errors on non-existent functions.
	 *
	 * @param	string	Called method name.
	 * @param	array	Arguments.
	 */
	public function __call(string $name, array $arguments): mixed {

		$getRelatedObject = function(string $class): ?ActiveRecord {

			// search for a static foreign-key list in object class in order to speed-up
			if (count(static::FOREIGN_KEYS)) {

				foreach (static::FOREIGN_KEYS as $fk) {
					if ($class::TABLE_NAME == $fk['REFERENCED_TABLE_NAME']) {
						$property = (string)static::getMappedProperty($fk['COLUMN_NAME']);
						return $this->getParent($property);
					}
				}

			// get foreign-key by DB query
			} else {

				// get inverse foreign keys list
				$inverseForeignKeys = $this->db->getInverseForeignKeys($class::TABLE_NAME);

				// search for the object property that matches db fk
				foreach ($inverseForeignKeys as $ifk) {

					// when found, return the related object
					if (static::TABLE_NAME == $ifk->TABLE_NAME) {
						$property = (string)static::getMappedProperty($ifk->COLUMN_NAME);
						return $this->getParent($property, $class);
					}

				}

			}

			return NULL;

		};

		// build Pair’s and ActiveRecord’s class name
		$evenClass = substr($name,3);
		$evenPairClass = 'Pair\\Models\\' . $evenClass;

		// check the opposite case, a series of objects belonging to this
		$multiClass = Utilities::getSingularObjectName(substr($name,3));
		$multiPairClass = 'Pair\\Models\\' . $multiClass;

		// maybe call is referring to
		if ('get'==substr($name,0,3)) {

			// check if invoked a virtual method on Pair class
			if (is_subclass_of($evenPairClass,'Pair\Orm\ActiveRecord')) {

				return $getRelatedObject($evenPairClass);

			// check if invoked a virtual method on other ActiveRecord’s class
			} else if (is_subclass_of($evenClass,'Pair\Orm\ActiveRecord')) {

				return $getRelatedObject($evenClass);

			} else if (is_subclass_of($multiPairClass,'Pair\Orm\ActiveRecord')) {

				return $this->getRelateds($multiPairClass);

			} else if (is_subclass_of($multiClass,'Pair\Orm\ActiveRecord')) {

				return $this->getRelateds($multiClass);

			}

		// notify the problem only to the developers
		} else if ('development' == Application::getEnvironment()) {

			$backtrace = debug_backtrace();
			Logger::error('Method '. get_called_class() . $backtrace[0]['type'] . $name .'(), which doesn’t exist, has been called by '. $backtrace[0]['file'] .' on line '. $backtrace[0]['line']);

		}

		// build the Exception message
		$msg = 'Method '. get_called_class() . '::' . $name .'() doesn’t exist';
		$code = ErrorCodes::METHOD_NOT_FOUND;

		throw new PairException($msg, $code);

	}

	public function __clone() {

		$class = get_called_class();

		// reset primary key
		foreach((array)$this->keyProperties as $keyProperty) {
			$this->$keyProperty = ('int' == $this->getPropertyType($keyProperty) ? 0 : NULL);
		}

		// reset updated properties
		$this->loadedFromDb = FALSE;
		$this->cache = [];

		$this->resetErrors();

		// log the reload
		Logger::notice('Cloned ' . $class . ' object', Logger::DEBUG);

	}

	/**
	 * Called by var_dump() when dumping an object to get the relevant object properties.
	 */
	public function __debugInfo(): array {

		$debug = [];

		$properties = $this->getAllProperties();

		foreach ($properties as $name => $value) {
			$debug[$name] = $value;
		}

		return $debug;

	}

	/**
	 * Return property’s value if set. Throw an exception and return NULL if not set.
	 *
	 * @param	string	Property’s name.
	 * @throws	PairException
	 */
	public function __get(string $name): mixed {

		if (array_key_exists($name, static::getBinds()) or in_array($name, ['keyProperties', 'db', 'loadedFromDb', 'typeList', 'cache', 'errors', 'updatedProperties', 'dynamicProperties'])) {

			return isset($this->$name) ? $this->$name : NULL;

		// it’s a dynamic property
		} else if (array_key_exists($name, $this->dynamicProperties)) {

			return $this->dynamicProperties[$name];

		}

		// property not found
		throw new PairException('Property “' . $name . '” not found for class ' . get_called_class(), ErrorCodes::PROPERTY_NOT_FOUND);

	}

	/**
	 * Magic method required by array_columns().
	 */
	public function __isset(string $name): bool {

        return isset($this->$name);

    }

	/**
	 * Magic method to set an object property value. If DateTime property, will properly manage integer or string date.
	 *
	 * @param	string	Property’s name.
	 * @param	mixed	Property’s value.
	 */
	public function __set(string $name, mixed $value): void {

		// it’s a dynamic property, deprecated since PHP 8.2
		if (!array_key_exists($name, static::getBinds())) {
			$this->dynamicProperties[$name] = @$value;
			return;
		}

		// check that’s not the initial object population
		if (isset(debug_backtrace()[1]) and !in_array(debug_backtrace()[1]['function'], ['populate'])) {
			$previousValue = $this->__get($name);
		}

		// if it is a virtually generated column, it does not set the corresponding property
		$columnName = array_search($name, static::getBinds());
		if ($this->db->isVirtualGenerated(static::TABLE_NAME, $columnName)) {
			$this->addError('Cannot set value for virtual generated column “'. $columnName .'”');
			return;
		}

		$this->$name = $this->castBindedProperty($name, $value);

		// keep track of updated properties
		if (!in_array($name, $this->updatedProperties) and isset(debug_backtrace()[1]) and !in_array(debug_backtrace()[1]['function'], ['populate'])
			and in_array($name, static::getBinds()) and $previousValue != $this->__get($name)) {
			$this->updatedProperties[] = $name;
		}

	}

	/**
	 * Method called by constructor just before populate this object.
	 */
	protected function _init(): void {}

	/**
	 * Add an error to object’s error list.
	 *
	 * @param	string	Error message’s text.
	 */
	public function addError(string $message) {

		$this->errors[] = $message;

	}

	/**
	 * Trigger function called after create() method execution.
	 */
	protected function afterCreate() {}

	/**
	 * Trigger function called after the mapped DB record’s deletion.
	 */
	protected function afterDelete(): void {}

	/**
	 * Trigger function called after populate() method execution.
	 */
	protected function afterPopulate() {}

	/**
	 * Trigger function called after prepareData() method execution.
	 *
	 * @param	\stdClass	PrepareData() returned variable (passed here by reference).
	 */
	protected function afterPrepareData(\stdClass &$dbObj) {}

	/**
	 * Trigger function called after store() method execution.
	 */
	protected function afterStore() {}

	/**
	 * Trigger function called after update() or updateNotNull() method execution.
	 */
	protected function afterUpdate() {}

	/**
	 * Return a Collection with all ActiveRecord(s) found in the database.
	 */
	public static function all(): Collection {

		$collection = new Collection();
		$class = get_called_class();

		// runs query
		$records = Database::load('SELECT * FROM `' . $class::TABLE_NAME . '`');

		// builds each object
		foreach ($records as $row) {
			$object = new $class($row);
			$object->loadedFromDb = TRUE;
			$collection->push($object);
		}

		$className = basename(str_replace('\\', '/', $class));
		Logger::notice('Loaded ' . count($records) . ' ' . $className . ' objects', Logger::DEBUG);

		return $collection;

	}

	/**
	 * Return TRUE if each key property has a value.
	 */
	public function areKeysPopulated(): bool {

		$populated = TRUE;

		$keys = (array)$this->getId();

		if (!count($keys)) {
			throw new PairException('No key properties found for ' . get_called_class());
		}

		foreach ($keys as $k) {
			if (!$k) $populated = FALSE;
		}

		return $populated;

	}

	/**
	 * Trigger function called before populate() method execution.
	 *
	 * @param	\stdClass	Object with which populate(), here passed by reference.
	 */
	protected function beforePopulate(\stdClass &$dbRow) {}

	/**
	 * Trigger function called before prepareData() method execution.
	 */
	protected function beforePrepareData() {}

	/**
	 * Trigger function called before store() method execution.
	 */
	protected function beforeStore() {}

	/**
	 * Trigger function called before create() method execution.
	 */
	protected function beforeCreate() {}

	/**
	 * Trigger function called before update() or updateNotNull() method execution.
	 */
	protected function beforeUpdate() {}

	/**
	 * Trigger function called before delete() method execution.
	 */
	protected function beforeDelete(): void {}

	/**
	 * Set boolean variable names for convertion when populating the object and
	 * writing into db.
	 *
	 * @param	string	List of variable names.
	 */
	final protected function bindAsBoolean(): void {

		foreach (func_get_args() as $name) {
			$this->typeList[$name] = 'bool';
		}

	}

	/**
	 * Set CSV type variable names for convertion when populating the object and
	 * writing into db.
	 *
	 * @param	string	List of variable names.
	 */
	final protected function bindAsCsv(): void {

		foreach (func_get_args() as $name) {
			$this->typeList[$name] = 'csv';
		}

	}

	/**
	 * Set DateTime variable names for convertion when populating the object and
	 * writing into db.
	 *
	 * @param	string	List of variable names.
	 */
	final protected function bindAsDatetime(): void {

		foreach (func_get_args() as $name) {
			$this->typeList[$name] = 'DateTime';
		}

	}

	/**
	 * Set float variable names for convertion when populating the object and
	 * writing into db.
	 *
	 * @param	string	List of variable names.
	 */
	final protected function bindAsFloat(): void {

		foreach (func_get_args() as $name) {
			$this->typeList[$name] = 'float';
		}

	}

	/**
	 * Set integer variable names for convertion when populating the object and
	 * writing into db.
	 *
	 * @param	string	List of variable names.
	 */
	final protected function bindAsInteger(): void {

		foreach (func_get_args() as $name) {
			$this->typeList[$name] = 'int';
		}

	}

	/**
	 * Set JSON type variable names for convertion when populating the object and
	 * writing into db.
	 *
	 * @param	string	List of variable names.
	 */
	final protected function bindAsJson(): void {

		foreach (func_get_args() as $name) {
			$this->typeList[$name] = 'json';
		}

	}

	/**
	 * Return the casted value of a property, based on its Pair’s property-type.
	 */
	private function castBindedProperty(string $name, mixed $value): mixed {

		$type = $this->getPropertyType($name);

		if (is_null($value)) {

			// CSV NULL becomes empty array
			$castedValue = $type == 'csv' ? [] : NULL;

		} else if ('' === $value and $this->isNullable((string)static::getMappedField($name))) {

			$castedValue = NULL;

		} else {

			switch ($type) {

				case 'bool':
					$castedValue = (bool)$value;
					break;

				case 'float':
					$castedValue = (float)$value;
					break;

				case 'int':
					$castedValue = (int)$value;
					break;

				case 'json':
					$castedValue = (is_string($value) and json_validate($value))
					? json_decode($value)
					: $value;
					break;

				case 'DateTime':
					$castedValue = $this->convertToDatetime($value);
					break;

				// split string parts by comma in array
				case 'csv':
					if (is_string($value)) {
						$castedValue = ('' == $value ? [] : explode(',', $value));
					} else {
						$castedValue = (array)$value;
					}
					break;

				// as default it will be uncast
				default:
				case 'string':
					$castedValue = @$value;
					break;

			}

		}

		return $castedValue;

	}

	/**
	 * Check if a record with column=value exists.
	 *
	 * @param	string	Table name.
	 * @param	string	Column name.
	 * @param	mixed	Value to search.
	 */
	private function checkRecordExists(string $table, string $column, $value): bool {

		if (!$value) {
			return FALSE;
		}

		// build the query
		$query = 'SELECT COUNT(1) FROM `' . $table . '` WHERE ' . $column . ' = ?';

		// search the record into the db
		return (bool)Database::load($query, (array) $value, Database::COUNT);

	}

	/**
	 * Convert this object with hidden properties, to a stdClass. Useful, for example, to print the object as JSON.
	 *
	 * @param	array	Optional list of the properties you want to return, as a subset of those available.
	 */
	public function convertToStdClass(?array $wantedProperties=NULL): \stdClass {

		$stdClass = new \stdClass();
		$binds = static::getBinds();

		if (is_array($wantedProperties) and !count($wantedProperties)) {
			$wantedProperties = NULL;
		}

		foreach (array_keys($binds) as $property) {
			if (is_null($wantedProperties) or in_array($property, $wantedProperties)) {
				$stdClass->$property = $this->__get($property);
			}
		}

		return $stdClass;

	}

	/**
	 * Count all objects of the inherited class with where conditions and order clause.
	 *
	 * @param	array	Optional array of query filters, [property-name => value].
	 */
	final public static function countAllObjects($filters = []): int {

		$db			= Database::getInstance();
		$class		= get_called_class();
		$binds		= $class::getBinds();

		$where		= '';
		$conds		= [];
		$whereLog	= '';

		if (is_array($filters)) {

			// iterate all filters
			foreach ($filters as $property => $value) {

				// check if filter is valid and binds really
				if (is_string($property) and strlen($property) and array_key_exists($property, $binds)) {

					// gets the table field name
					$field = $binds[$property];

					// creates where condition
					$conds[] = '`' . $field . '`' . (is_null($value) ? ' IS NULL' : ' = ' . $db->quote($value));

				} else {

					trigger_error('In method ' . $class . '::countAllObjects() unexistent property “' . $property . '” can’t be used as filter');

				}

			}

			// log message
			$whereLog .= count($conds) ? ' under condition ' . implode(' AND ', $conds) : '';

			// builds where
			$where = count($conds) ? ' WHERE ' . implode(' AND ', $conds) : '';

		}

		// runs query
		$query = 'SELECT COUNT(1) FROM `' . $class::TABLE_NAME . '`' . $where;
		$count = Database::load($query, [], Database::COUNT);

		Logger::notice('Counted ' . $count . ' ' . $class . ' objects' . $whereLog, Logger::DEBUG);

		return $count;

	}

	/**
	 * Create this object as new database record and will assign its primary key
	 * as $id property. Null properties won’t be written in the new row.
	 * Return TRUE if success.
	 */
	final public function create(): bool {

		$app = Application::getInstance();
		$class = get_called_class();

		$autoIncrement = $this->db->isAutoIncrement(static::TABLE_NAME);

		if (!$autoIncrement and !$this->areKeysPopulated()) {

			$errCode = static::hasSimpleKey()
				? ErrorCodes::PRIMARY_KEY_NOT_POPULATED
				: ErrorCodes::COMPOSITE_PRIMARY_KEY_NOT_POPULATED;

			throw new PairException(implode(', ', $this->keyProperties) . ' not populated', $errCode);

		}

		// hook for tasks to be executed before creation
		$this->beforeCreate();

		// get list of class property names
		$props = array_keys(static::getBinds());

		// populate createdAt if it exists
		if (property_exists($class, 'createdAt') and is_null($this->__get('createdAt'))) {
			$this->createdAt = new \DateTime('now', Application::getTimeZone());
		}

		// populate createdBy if it exists
		if (isset($app->currentUser->id) and property_exists($class, 'createdBy') and is_null($this->__get('createdBy'))) {
			$this->createdBy = $app->currentUser->id;
		}

		// populate updatedAt if it exists
		if (property_exists($class, 'updatedAt') and is_null($this->__get('updatedAt'))) {
			$this->updatedAt = new \DateTime('now', Application::getTimeZone());
		}

		// populate updatedBy if it exists
		if (isset($app->currentUser->id) and property_exists($class, 'updatedBy') and is_null($this->__get('updatedBy'))) {
			$this->updatedBy = $app->currentUser->id;
		}

		// insert the object as db record
		$dbObj = $this->prepareData($props);

		$this->db->insertObject(static::TABLE_NAME, $dbObj, static::getEncryptableFields());

		// get last insert id if not compound key
		if (!static::hasCompoundKey() and $autoIncrement) {

			$lastInsertId = $this->db->getLastInsertId();

			$key = $this->keyProperties[0];

			if ('int' == $this->getPropertyType($key)) {
				$this->{$key} = (int)$lastInsertId;
			} else {
				$this->{$key} = $lastInsertId;
			}

		}

		// reset updated-properties tracker
		$this->updatedProperties = [];

		// suppress notices for error logs to avoid loops
		if ('error_logs' != static::TABLE_NAME) {
			Logger::notice('Created a new ' . $class . ' object with ' . $this->getKeysForEventlog(), Logger::DEBUG);
		}

		// hook for tasks to be executed after creation
		$this->afterCreate();

		return TRUE;

	}

	/**
	 * Deletes this object’s line from database and returns deletion success.
	 */
	final public function delete(): bool {

		if (!$this->getId()) return FALSE;

		// trigger a custom function before deletion
		$this->beforeDelete();

		$class = get_called_class();

		// build the SQL where line
		$where = ' WHERE ' . implode(' AND ', $this->getSqlKeyConditions());

		$query = 'DELETE FROM `' . $class::TABLE_NAME . '`' . $where . ' LIMIT 1';
		$res = Database::run($query, $this->getSqlKeyValues());

		// trigger a custom function after DB record’s deletion
		$this->afterDelete();

		// list properties to not remove
		$activeRecordsProperties = ['keyProperties', 'db', 'loadedFromDb', 'typeList', 'errors'];

		// unset all properties
		foreach ($this as $key => $value) {
			if (!in_array($key, $activeRecordsProperties)) {
				unset($this->$key);
			}
		}

		$this->loadedFromDb = FALSE;
		$this->errors = [];

		return (bool)$res;

	}

	/**
	 * Return TRUE if db record with passed primary or compound key exists. Faster method.
	 *
	 * @param	mixed	Primary or compound key for this object table.
	 */
	final public static function exists(mixed $keys): bool {

		// initialize some vars
		$tableKey	= (array)static::TABLE_KEY;
		$conds		= [];

		foreach ($tableKey as $field) {
			$conds[] = $field . ' = ?';
		}

		$query = 'SELECT COUNT(1) FROM `' . static::TABLE_NAME . '` WHERE ' . implode(' AND ', $conds);

		// execute and return value
		return (bool)Database::load($query, (array)$keys, Database::COUNT);

	}

	/**
	 * Check if this object still exists in DB as record. Return TRUE if exists.
	 */
	final public function existsInDb(): bool {

		$conds = implode(' AND ', $this->getSqlKeyConditions());

		return (bool)Database::load(
			'SELECT COUNT(1) FROM `' . static::TABLE_NAME . '` WHERE ' . $conds,
			$this->getSqlKeyValues(),
			Database::COUNT
		);

	}

	/**
	 * Search an object in the database with the primary key equivalent to the value passed in the parameter
	 * and returns it as an ActiveRecord of this class, if found. NULL if not found.
	 */
	public static function find(int|string|array $primaryKey): ?static {

		$self = new static();

		// primary or compound key, loads the whole object from db
		if (static::hasSimpleKey() or (static::hasCompoundKey() and is_array($primaryKey) and count($self->keyProperties) == count($primaryKey))) {

			// try to load the object from db
			$obj = new static();
			$obj->loadFromDb($primaryKey);
			return $obj->loadedFromDb ? $obj : NULL;

		}

		return NULL;

	}

	/**
	 * Search and return an object in the database with fields and values passed in the parameter.
	 */
	public static function findByAttributes(\stdClass $attributes): ?static {

		$query = 'SELECT * FROM `' . static::TABLE_NAME . '` WHERE ';

		$params = [];
		$conds  = [];
		$binds  = static::getBinds();

		foreach ($attributes as $property => $value) {

			if (isset($binds[$property])) {

				if (is_null($value)) {

					$conds[] = '`' . $binds[$property] . '` IS NULL';
					$params[] = NULL;

				} else {

					$conds[] = '`' . $binds[$property] . '` = ?';

					if ($value instanceof \DateTime) {
						$params[] = $value->format('Y-m-d H:i:s');
					} else if (is_bool($value)) {
						$params[] = (int)$value;
					} else {
						$params[] = $value;
					}

				}

			}

		}

		$query .= implode(' AND ', $conds);

		return static::getObjectByQuery($query, $params);

	}

	/**
	 * Search an object in the database with the primary key equivalent to the value passed in the parameter
	 * and returns it as an ActiveRecord of this class, if found. If not found, throws an exception.
	 *
	 * @throws	PairException
	 */
	public static function findOrFail(int|string|array $primaryKey): static {

		$obj = static::find($primaryKey);

		if (!$obj) {
			throw new PairException(Translator::do('OBJECT_NOT_FOUND'), ErrorCodes::RECORD_NOT_FOUND);
		}

		return $obj;

	}

	/**
	 * Securely formats and returns the value of a DateTime field with the date only if valid, otherwise NULL.
	 * @param	string	Property name of DateTime object.
	 * @param	string	Optional pattern in the format provided by the DateTime object.
	 * @return	string|NULL
	 */
	final public function formatDate(string $prop, ?string $format=NULL): ?string {

		if (!is_a($this->$prop, '\DateTime')) {
			return NULL;
		}

		// for guests, use default TimeZone
		$this->$prop->setTimeZone(Application::getTimeZone());

		// if the format is not specified, use the default and localized formatting pattern
		if ($format) {
			return $this->$prop->format($format);
		}

		// read the date format from the language file
		$dateFormat = Translator::do('DATE_FORMAT');
		return Utilities::intlFormat($dateFormat, $this->$prop);

	}

	/**
	 * Securely formats and returns the value of a DateTime field with the time if valid, otherwise NULL.
	 * @param	string	Property name of DateTime object.
	 * @param	string	Optional pattern in the format provided by the DateTime object.
	 * @return	string|NULL
	 */
	final public function formatDateTime(string $prop, ?string $format=NULL): ?string {

		if (!is_a($this->$prop, '\DateTime')) {
			return NULL;
		}

		// for guests, use default TimeZone
		$this->$prop->setTimeZone(Application::getTimeZone());

		// if the format is not specified, use the default and localized formatting pattern
		if ($format) {
			return $this->$prop->format($format);
		}

		// read the date format from the language file
		$dateTimeFormat = Translator::do('DATETIME_FORMAT');
		return Utilities::intlFormat($dateTimeFormat, $this->$prop);

	}

	/**
	 * Gets all objects of the inherited class with where conditions and order clause.
	 *
	 * @param	array	Optional array of query filters, [property_name => value].
	 * @param	array	Optional array of order by, [property_name] or [property_name => 'DESC'].
	 */
	final public static function getAllObjects(?array $filters = [], string|array $orderBy = []): Collection {

		$db			= Database::getInstance();
		$class		= get_called_class();
		$binds		= $class::getBinds();

		$where		= '';
		$conds		= [];
		$whereLog	= '';

		$order		= '';
		$orderClause= [];
		$orderBy	= (array)$orderBy;

		if (is_array($filters)) {

			// iterate all filters
			foreach ($filters as $property => $value) {

				// check if filter is valid and binds really
				if (is_string($property) and strlen($property) and array_key_exists($property, $binds)) {

					// convert bool to int
					if (is_bool($value)) {
						$value = (int)$value;
					}

					// gets the table field name
					$field = $binds[$property];

					// creates where condition
					$conds[] = '`' . $field . '`' . (is_null($value) ? ' IS NULL' : ' = ' . (is_int($value) ? $value : $db->quote($value)));

				} else {

					throw new PairException('In method ' . $class . '::getAllObjects() unexistent property “' . $property . '” can’t be used as filter');

				}

			}

			// log message
			$whereLog .= count($conds) ? ' under condition WHERE ' . implode(' AND ', $conds) : '';

			// builds where
			$where = count($conds) ? ' WHERE ' . implode(' AND ', $conds) : '';

		}

		if (count($orderBy)) {

			foreach ($orderBy as $property => $direction) {

				// simple key, so direction is intended as property name
				if (is_int($property)) {
					$property	= $direction;
					$direction	= 'ASC';
				}

				// checks if it’s a valid order by field
				if (is_string($property) and strlen($property) and array_key_exists($property, $binds)) {

					// gets the table field name
					$field = $binds[$property];

					// validates direction
					if (!$direction or !in_array(strtolower($direction), ['asc','desc'])) {
						$direction = '';
					}

					$orderClause[] = '`' . $field . '` ' . strtoupper($direction);

				} else {

					trigger_error('In method ' . $class . '::getAllObjects() unexistent property “' . $property . '” can’t be used as filter');

				}

			}

			// builds order by
			$order = count($orderClause) ? ' ORDER BY ' . implode(', ', $orderClause) : '';

		}

		// runs query
		$list = Database::load('SELECT ' . static::getQueryColumns() . ' FROM `' . $class::TABLE_NAME . '`' . $where . $order);

		$objects = [];

		if (is_array($list)) {

			// builds each object
			foreach ($list as $row) {
				$object = new $class($row);
				$object->loadedFromDb = TRUE;
				$objects[] = $object;
			}

		}

		$className = basename(str_replace('\\', '/', $class));
		Logger::notice('Loaded ' . count($objects) . ' ' . $className . ' objects' . $whereLog, Logger::DEBUG);

		return new Collection($objects);

	}

	/**
	 * Utility that works like \get_object_vars() but restricted to bound properties.
	 */
	final public function getAllProperties(): array {

		$class = get_called_class();

		// all subclass binds
		$binds = $class::getBinds();

		$properties = [];

		foreach (array_keys($binds) as $property) {
			$properties[$property] = $this->__get($property);
		}

		return $properties;

	}

	/**
	 * Returns array with matching object property name on mapped db columns.
	 */
	protected static function getBinds(): array {

		$db = Database::getInstance();
		$columns = $db->describeTable(static::TABLE_NAME);

		$maps = [];

		foreach ($columns as $col) {

			// get a camelCase name, with first low case
			$property = lcfirst(str_replace(' ', '', ucwords(str_replace(['_','\\'], ' ', $col->Field))));
			
			// if property doesn’t exist in the class, it will be handled as dynamic property
			if (!property_exists(static::class, $property)) {
				throw new PairException('Property “' . $property . '” not found for class ' . static::class, ErrorCodes::PROPERTY_NOT_FOUND);
			} else {
				$maps[$property] = $col->Field;
			}

		}

		return $maps;

	}

	/**
	 * Returns a variable, NULL in case of variable not found.
	 *
	 * @param	string	Name of the cached variable.
	 */
	final public function getCache($name): mixed {

		return ((is_array($this->cache) and array_key_exists($name, $this->cache)) ? $this->cache[$name] : NULL);

	}

	/**
	 * Create an object for a table column configuration within an object or NULL if column
	 * doesn’t exist.
	 *
	 * @param	string	Field name.
	 */
	private static function getColumnType(string $fieldName): ?\stdClass {

		$db = Database::getInstance();
		$column = $db->describeColumn(static::TABLE_NAME, $fieldName);

		if (is_null($column)) {
			return NULL;
		}

		// split the column Type to recognize field type and length
		preg_match('#^([\w]+)(\([^\)]+\))? ?(unsigned)?#i', $column->Type, $matches);

		$field = new \stdClass();

		$field->name	= $fieldName;
		$field->type	= $matches[1];
		$field->unsigned= (isset($matches[3]));
		$field->nullable= 'YES' == $column->Null ? TRUE : FALSE;
		$field->key		= $column->Key;
		$field->default	= $column->Default;
		$field->extra	= $column->Extra;

		if (isset($matches[2])) {
			if (in_array($field->type, ['enum','set'])) {
				$field->length = explode("','", substr($matches[2], 2, -2));
			} else {
				$field->length = explode(",", substr($matches[2], 1, -1));
			}
		} else {
			$field->length = NULL;
		}

		return $field;

	}

	/**
	 * Return list of encryptable db-column names, if any.
	 *
	 * @return	string[]
	 */
	private static function getEncryptableFields(): array {

		$encryptables = [];

		// list encryptables columns
		if (defined('static::ENCRYPTABLES') and
		 is_array(static::ENCRYPTABLES)) {
			foreach (static::ENCRYPTABLES as $property) {
				$encryptables[] = (string)static::getMappedField($property);
			}
		}

		return $encryptables;

	}

	/**
	 * Return the SELECT query code for columns mapped to encrypted properties. Empty string in case of no encrypted properties.
	 *
	 * @param	string|NULL	Table alias.
	 */
	public static function getEncryptedColumnsQuery(?string $tableAlias=NULL): string {

		$encryptables = static::getEncryptableFields();

		if ($encryptables) {

			$db = Database::getInstance();
			$items = [];

			foreach ($encryptables as $e) {
				$items[] = 'AES_DECRYPT(' . ($tableAlias ? $tableAlias . '.' : '') .'`' . $e . '`,' .
					$db->quote(Env::get('AES_CRYPT_KEY')) . ') AS `' . $e . '`';
			}

			return implode(',',$items);

		} else {

			return '';

		}

	}

	/**
	 * Return an array with text of all errors.
	 */
	final public function getErrors(): array {

		return $this->errors;

	}

	/**
	 * Generate a Form object with proper controls type already populated with object properties.
	 */
	public function getForm(): Form {

		$form = new Form();

		// build a select control
		$getSelectControl = function ($property, $field, $values) use ($form) {

			$control = $form->select($property)->options($values, $values);

			if (static::isNullable($field) or static::isEmptiable($field)) {
				$control->empty();
			}

			return $control;

		};

		$properties = $this->getAllProperties();

		// these db column types will go into a textarea
		$textAreaTypes = ['tinytext', 'text', 'mediumtext', 'longtext'];

		foreach ($properties as $propName => $value) {

			$field = (string)static::getMappedField($propName);

			// primary key
			if ($this->isKeyProperty($propName)) {

				$control = $form->hidden($propName);

			} else {

				$column = static::getColumnType($field);

				switch ($this->getPropertyType($propName)) {

					// checkbox
					case 'bool':
						$control = $form->checkbox($propName);
						break;

					// date or datetime
					case 'DateTime':
						$type = 'date' == $column->type ? 'date' : 'datetime';
						$control = $form->$type($propName);
						break;

					// number with two decimals
					case 'float':
						$control = $form->number($propName)->step('0.01');
						break;

					// integer
					case 'int':
						$control = $form->number($propName);
						break;

					// multiple select
					case 'csv':
						$control = $getSelectControl($propName, $field, $column->length);
						$control->multiple();
						break;

					// textarea for json
					case 'json':
						$control = $form->textarea($propName);
						break;

					// select, textarea or text
					default:
						if ('enum' == $column->type) {
							$control = $getSelectControl($propName, $field, $column->length);
						} else if ('set' == $column->type) {
							$control = $getSelectControl($propName, $field, $column->length);
							$control->multiple();
						} else if (in_array($column->type, $textAreaTypes)) {
							$control = $form->textarea($propName);
						} else {
							$control = $form->text($propName);
							if (isset($column->length[0])) {
								$control->maxLength($column->length[0]);
							}
						}
						break;

				}

			}

			// check if is required
			if (!static::isNullable($field) and !static::isEmptiable($field)) {
				$control->required();
			}

			// set the object value
			$control->value($value);

		}

		return $form;

	}

	/**
	 * Return a list of primary or compound key of this object.
	 */
	private function getKeysForEventlog(): string {

		$keysValues = [];

		foreach ($this->getKeysValues() as $key => $value) {
			$keysValues[] = $key . '=' . $value;
		}

		return implode(', ', $keysValues);

	}

	private function getKeysValues(): array {

		// force to array
		$propertyNames = (array)$this->keyProperties;

		// list to return
		$keysValues = [];

		foreach ($propertyNames as $propertyName) {

			$value = is_a($this->__get($propertyName), '\DateTime')
			? $this->__get($propertyName)->format('Y-m-d H:i:s')
			: $this->__get($propertyName);

			$keysValues[$propertyName] = $value;
		}

		return $keysValues;


	}

	/**
	 * Returns unique ID of inherited object or in case of compound key, an indexed array.
	 */
	final public function getId(): int|string|array|NULL {

		$ids = [];

		foreach ($this->keyProperties as $propertyName) {
			if (isset($this->{$propertyName})) {
				$ids [] = is_a($this->{$propertyName}, 'DateTime')
				? $this->{$propertyName}->format('Y-m-d H:i:s')
				: $this->{$propertyName};
			}
		}

		return (static::hasCompoundKey() ? $ids : ($ids[0] ?? NULL));

	}

	/**
	 * Return a list of ActiveRecord objects related to this object. Can be filtered by a
	 * specific class. If no related objects are found, an empty Collection is returned.
	 */
	final public function getRelateds(?string $refClass=NULL): Collection {

		// foreign keys flag
		$foreignKeyFound = FALSE;

		$relateds = new Collection();

		// retrieves all related objects for each foreign key
		if (is_null($refClass)) {

			$inverseForeignKeys = $this->db->getInverseForeignKeys(static::TABLE_NAME);

			foreach ($inverseForeignKeys as $ifk) {

				// search for the object property that matches db fk
				$refClass = Utilities::getActiveRecordClassByTable($ifk->TABLE_NAME);

				if (!$refClass) {
					throw new PairException('Class not found for table ' . $ifk->TABLE_NAME, ErrorCodes::CLASS_NOT_FOUND);
				}

				$foreignKeyFound = TRUE;

				$selfProperty = (string)static::getMappedProperty($ifk->REFERENCED_COLUMN_NAME);
				$refProperty = (string)$refClass::getMappedProperty($ifk->COLUMN_NAME);

				$relateds->merge($refClass::getAllObjects([$refProperty=>$this->$selfProperty]));

			}

		} else if (class_exists($refClass)) {

			$foreignKeys = $this->db->getForeignKeys($refClass::TABLE_NAME);

			// the class was not found
			if (!class_exists($refClass) or !is_subclass_of($refClass, 'Pair\Orm\ActiveRecord')) {
				throw new PairException('Class ' . $refClass . ' not found', ErrorCodes::CLASS_NOT_FOUND);
			}

			// search for the object property that matches db fk
			foreach ($foreignKeys as $fk) {

				// when found, return the related object
				if (static::TABLE_NAME == $fk->REFERENCED_TABLE_NAME) {

					$foreignKeyFound = TRUE;

					$selfProperty = (string)static::getMappedProperty($fk->REFERENCED_COLUMN_NAME);
					$refProperty = (string)$refClass::getMappedProperty($fk->COLUMN_NAME);

					// load the new wanted Pair objects
					$relateds->merge($refClass::getAllObjects([$refProperty=>$this->$selfProperty]));

				}

			}

		}

		if (!$foreignKeyFound) {

			$message = $refClass
				? Translator::do('NO_USEFUL_FOREIGN_KEY_FOR_CLASS', [$refClass, static::TABLE_NAME])
				: Translator::do('NO_USEFUL_FOREIGN_KEY', [static::TABLE_NAME]);

			throw new PairException($message, ErrorCodes::NO_FOREIGN_KEY);

		}

		return $relateds;

	}

	/**
	 * Return last insert record object for single, auto-increment primary key.
	 */
	public static function getLast(): mixed {

		$class = get_called_class();

		// check about single primary key
		if ($class::hasCompoundKey()) {
			return NULL;
		}

		// check if auto-increment key
		$db = Database::getInstance();
		if (!$db->isAutoIncrement($class::TABLE_NAME)) {
			if (property_exists($class, 'createdAt')) {
				return static::getObjectByQuery('SELECT * FROM `' . $class::TABLE_NAME . '` ORDER BY `created_at` DESC LIMIT 1');
			} else {
				return NULL;
			}
		}

		// cast to string
		$tableKey = (is_array($class::TABLE_KEY) and array_key_exists(0, $class::TABLE_KEY)) ? $class::TABLE_KEY[0] : $class::TABLE_KEY;

		return static::getObjectByQuery('SELECT * FROM `' . $class::TABLE_NAME . '` ORDER BY `' . $tableKey . '` DESC LIMIT 1');

	}

	/**
	 * Return text of latest error. In case of no errors, return FALSE.
	 */
	final public function getLastError(): FALSE|string {

		return end($this->errors);

	}

	/**
	 * Get the name of db field mapped by a class property. NULL if not found.
	 *
	 * @param	string	Property name.
	 */
	final static public function getMappedField(string $propertyName): ?string {

		$binds = static::getBinds();
		return isset($binds[$propertyName]) ? $binds[$propertyName] : NULL;

	}

	/**
	 * Get the name of class property mapped by db field. NULL if not found.
	 *
	 * @param	string	Field name.
	 */
	final static public function getMappedProperty(string $fieldName): ?string {

		$binds = static::getBinds();
		return in_array($fieldName, $binds) ? array_search($fieldName, $binds) : NULL;

	}

	/**
	 * Load all records in a table from the DB and store them in the Application cache,
	 * then look for the required property in this list. It is very useful for repeated
	 * searches on small tables of the DB, eg. less than 1000 records.
	 *
	 * @param	string	Property name.
	 * @param	mixed	Property value. If not unique property, return the first table item.
	 */
	final public static function getObjectByCachedList(string $property, $value): ?self {

		$app = Application::getInstance();
		$class = get_called_class();
		$cacheName = $class . 'ObjectList';

		if (!$app->issetState($cacheName)) {
			$app->setState($cacheName, $class::all());
		}

		foreach ($app->getState($cacheName) as $object) {
			if ($object->$property == $value) {
				return $object;
			}
		}

		return NULL;

	}

	/**
	 * Get one object of inherited class as result of the query run.
	 *
	 * @param	string	Query to run.
	 * @param	array	Optional bind parameters for query.
	 */
	final public static function getObjectByQuery(string $query, array $params=[]): ?static {

		// run query
		$row = Database::load($query, $params, Database::OBJECT);

		// initializes the binding of the dynamic properties
		$dynamicBinds = [];

		if (!is_a($row, '\stdClass')) {
			return NULL;
		}

		$class = get_called_class();
		$binds = $class::getBinds();

		// get object properties from query
		$columns  = get_object_vars($row);

		// search for custom column names
		foreach (array_keys($columns) as $column) {
			if (!array_search($column, $binds)) {
				$dynamicBinds[Utilities::getCamelCase($column)] = $column;
			}
		}

		$object = new $class($row);

		// populate custom properties
		foreach ($dynamicBinds as $dynamicProp=>$customColumn) {
			$object->__set($dynamicProp, $row->$customColumn);
		}

		// turn on loaded-from-db flag
		$object->loadedFromDb = TRUE;

		$className = basename(str_replace('\\', '/', $class));
		Logger::notice('Loaded a ' . $className . ' object' . (count($dynamicBinds) ? ' with custom columns ' . implode(',', $dynamicBinds) : ''), Logger::DEBUG);

		return $object;

	}

	/**
	 * Get all objects of inherited class as result of the query run.
	 *
	 * @param	string	Query to run.
	 * @param	array	Optional bind parameters for query.
	 */
	final public static function getObjectsByQuery(string $query, array $params=[]): Collection {

		$class = get_called_class();

		// run query
		$list = Database::load($query, $params);

		// objects to be returned
		$objects = [];

		// initializes the binding of the dynamic properties
		$dynamicBinds = [];

		if (is_array($list) and isset($list[0])) {

			$binds = $class::getBinds();

			// get object properties from query
			$columns = get_object_vars($list[0]);

			// search for custom field names
			foreach (array_keys($columns) as $column) {
				if (!array_search($column, $binds)) {
					$dynamicBinds[Utilities::getCamelCase($column)] = $column;
				}
			}

			// build each object
			foreach ($list as $row) {

				$object = new $class($row);

				// populate custom properties
				foreach ($dynamicBinds as $dynamicProp=>$customField) {
					$object->__set($dynamicProp, $row->$customField);
				}

				// turn on loaded-from-db flag
				$object->loadedFromDb = TRUE;

				$objects[] = $object;

			}

		}

		$className = basename(str_replace('\\', '/', $class));
		Logger::notice('Loaded ' . count($objects) . ' ' . $className . ' objects with custom columns ' . implode(',', $dynamicBinds), Logger::DEBUG);

		return new Collection($objects);

	}

	/**
	 * Return previous record object, for single, auto-increment primary key.
	 */
	public function getPrevious(): ?self {

		if (!$this->db->isAutoIncrement(static::TABLE_NAME) or (is_array(static::TABLE_KEY) and count((array)static::TABLE_KEY)>1)) {
			return NULL;
		}

		$tableKey = is_array(static::TABLE_KEY) ? static::TABLE_KEY[0] : static::TABLE_KEY;

		$query = 'SELECT * FROM `' . static::TABLE_NAME . '` WHERE `' . $tableKey . '` < ? ORDER BY `' . $tableKey . '` DESC';

		return static::getObjectByQuery($query, [$this->$tableKey]);

	}

	/**
	 * Return the property PHP type (bool, DateTime, float, int, string, csv and json).
	 */
	final public function getPropertyType(string $name): ?string {

		if (in_array($name, ['db', 'loadedFromDb', 'typeList', 'errors', 'updatedProperties'])) {
			$type = NULL;
		} else if (array_key_exists($name, $this->typeList)) {
			$type = $this->typeList[$name];
		} else {
			$type = 'string';
		}

		return $type;

	}

	/**
	 * Return the query column list, in case there are encryptable columns or just *.
	 */
	public static function getQueryColumns(): string {

		$query = '*';

		$encryptedColumns = static::getEncryptedColumnsQuery();
		if ($encryptedColumns) {
			$query .= ',' . $encryptedColumns;
		}

		return $query;

	}

	/**
	 * Return the Pair\Orm\ActiveRecord inherited object parented to this by a ForeignKey in DB-table. Cached method.
	 *
	 * @param	string	Parent property name.
	 * @param	string	Parent object class.
	 */
	final public function getParent(string $parentProperty, ?string $className=NULL): ?self {

		$cacheName = $parentProperty . 'RelatedObject';

		// object exists in cache, return it
		if (!$this->isInSharedCache($parentProperty) and $this->issetCache($cacheName)) {
			return $this->getCache($cacheName);
		}

		// search for a static foreign-key list in object class in order to speed-up
		if (count(static::FOREIGN_KEYS)) {

			// initialize
			$foreignKeys = [];

			// cast to \stdClass
			foreach (static::FOREIGN_KEYS as $fk) {
				$obj = (object)$fk;
				$foreignKeys[] = $obj;
			}

		// get foreign-key by DB query
		} else {

			$foreignKeys = $this->db->getForeignKeys(static::TABLE_NAME);

		}

		// get field name by mapped property
		$parentField = (string)static::getMappedField($parentProperty);

		// the table referenced by fk
		$referencedTable = NULL;

		// search the fk-table
		foreach ($foreignKeys as $fk) {
			if ($fk->COLUMN_NAME == $parentField) {
				$referencedTable  = $fk->REFERENCED_TABLE_NAME;
				break;
			}
		}

		// if not table is referenced, raise an error
		if (!$referencedTable) {
			$this->addError('Property ' . $parentProperty . ' has not a foreign-key mapped into DB');
			return NULL;
		}

		// class that maps the parent table
		$parentClass = NULL;

		// if the class name is specified, it quickly searches the array
		if (!is_null($className) and is_subclass_of($className, 'Pair\Orm\ActiveRecord') and defined($className . '::TABLE_NAME') and $className::TABLE_NAME == $referencedTable) {
			$parentClass = $className;
		// otherwise it must iterate all the loaded classes
		} else {
			$loadedClasses = Utilities::getDeclaredClasses();
			foreach ($loadedClasses as $c) {
				if (is_subclass_of($c, 'Pair\Orm\ActiveRecord') and defined($c . '::TABLE_NAME') and $c::TABLE_NAME == $referencedTable) {
					$parentClass = $c;
					break;
				}
			}
		}

		// class cannot be found
		if (!$parentClass) {

			// if not found, search in the whole application
			$classes = Utilities::getActiveRecordClasses();

			// search for required one
			foreach ($classes as $class => $opts) {
				if ($opts['tableName'] == $referencedTable) {
					include_once($opts['folder'] . '/' . $opts['file']);
					$parentClass = $class;
					break;
				}
			}

		}

		// class cannot be found
		if (!$parentClass) {
			throw new PairException('Table ' . $referencedTable . ' has not any Pair-class mapping', ErrorCodes::CLASS_NOT_FOUND);
		}

		$parentValue = $this->__get($parentProperty);

		//  check if is managed by common cache
		if ($parentValue and $this->isInSharedCache($parentProperty)) {

			$app = Application::getInstance();

			// assemble any composite key
			$obj = $app->getActiveRecordCache($parentClass, $parentValue);

			// if got it from common cache, return it
			if ($obj) {
				return $obj;
			// otherwise load from DB, store into common cache and return it
			} else {
				$obj = new $parentClass($parentValue);
				if ($obj->isLoaded()) {
					$app->putActiveRecordCache($parentClass, $obj);
					return $obj;
				}
			}

		}

		// no common cache, so proceed to load the new wanted Pair object
		$obj = new $parentClass($parentValue);

		// if loaded, return it otherwise NULL
		$ret = ($obj->isLoaded() ? $obj : NULL);

		// parent object is being registered in cache of this object
		$this->setCache($cacheName, $ret);

		return $ret;

	}

	/**
	 * Extended method to return a property value of the Pair\Orm\ActiveRecord inherited object parent to
	 * this by a ForeignKey in DB-table. Cached method.
	 *
	 * @param	string	Parent property name, belongs to this object.
	 * @param	string	Wanted property name, belongs to parent object.
	 */
	final public function getParentProperty(string $parentProperty, string $wantedProperty): mixed {

		$obj = $this->getParent($parentProperty);

		if ($obj) {
			return $obj->$wantedProperty;
		} else {
			return NULL;
		}

	}

	/**
	 * Build a list of SQL conditions to select the current mapped object into DB.
	 *
	 * @return string[]
	 */
	private function getSqlKeyConditions(): array {

		$class		= get_called_class();
		$tableKey	= (array)$class::TABLE_KEY;
		$conds		= [];

			foreach ($tableKey as $field) {
				$conds[] = '`' . $field . '` = ?';
			}

		return $conds;

	}

	/**
	 * Returns the list of properties whose value has changed since the record was last
	 * written to the DB.
	 */
	final protected function getUpdatedProperties(): array {

		return $this->updatedProperties;

	}

	/**
	 * Return an indexed array with current table key values regardless of object
	 * properties value.
	 */
	private function getSqlKeyValues(): array {

		return array_values($this->getKeysValues());

	}

	/**
	 * Compare object properties with related DB table columns, with proper cast. Doesn’t
	 * compare other object properties.
	 */
	final public function hasChanged(): bool {

		$class = get_called_class();
		$binds = $class::getBinds();

		// create a new similar object that populates properly
		$newObj = new $class($this->{$this->keyProperties});

		if (!$newObj) return TRUE;

		foreach (array_keys($binds) as $property) {
			if ($this->$property != $newObj->$property) {
				return TRUE;
			}
		}

		return FALSE;

	}

	/**
	 * Reveal if children class has a compound key as array made by one field at least.
	 */
	private static function hasCompoundKey(): bool {

		$class = get_called_class();
		return (is_array($class::TABLE_KEY) and count($class::TABLE_KEY) > 1);

	}

	/**
	 * Checks whether the property with the name passed as a parameter has changed with
	 * respect to the corresponding record in the DB.
	 *
	 * @param	string	Property name.
	 */
	final protected function hasPropertyUpdated(string $name): bool {

		return in_array($name, $this->updatedProperties);

	}

	/**
	 * Reveal if children class has a simple key as integer or string.
	 */
	public static function hasSimpleKey(): bool {

		$class = get_called_class();

		if (is_array($class::TABLE_KEY) and count($class::TABLE_KEY) > 1) return FALSE;

		$key = (is_array($class::TABLE_KEY) and isset($class::TABLE_KEY[0]))
		? $class::TABLE_KEY[0] ?? NULL
		: $class::TABLE_KEY;

		return ($key and (is_int($key) or is_string($key)));

	}

	/**
	 * Check if a property of this inherited object is stored in common cache.
	 *
	 * @param	string	Name of property of this object to check.
	 */
	private function isInSharedCache(string $property): bool {

		// list shared properties
		return (is_array(static::SHARED_CACHE_PROPERTIES) and
				in_array($property, static::SHARED_CACHE_PROPERTIES));

	}

	/**
	 * Check if a property is mapped to a table primary or compound key field for this object.
	 *
	 * @param	string	Single key name.
	 */
	private function isKeyProperty(string $propertyName): bool {

		return (in_array($propertyName, (array)$this->keyProperties));

	}

	/**
	 * Returns TRUE if inherited object has been loaded from db.
	 */
	final public function isLoaded(): bool {

		return $this->loadedFromDb;

	}

	/**
	 * Returns TRUE if object’s cache variable has been previously set.
	 *
	 * @param	string	Name of the cached variable.
	 */
	final public function issetCache(string $name): bool {

		return ((is_array($this->cache) and array_key_exists($name, $this->cache)) ? TRUE : FALSE);

	}

	/**
	 * Check wheter options crypt key has been defined into .env file.
	 */
	public function isCryptAvailable(): bool {

		return (defined('AES_CRYPT_KEY') and strlen(Env::get('AES_CRYPT_KEY')) > 0);

	}

	/**
	 * Check whether record of this object is deletable based on inverse foreign-key list.
	 */
	public function isDeletable(): bool {

		// get the list of column with foreign keys from other tables
		$inverseForeignKeys = $this->db->getInverseForeignKeys(static::TABLE_NAME);

		foreach ($inverseForeignKeys as $r) {

			// only if restrict it could be not deletable
			if ('RESTRICT' != $r->DELETE_RULE) continue;

			// get the property name
			$propertyName = static::getMappedProperty($r->REFERENCED_COLUMN_NAME);

			// if a record that’s constraining exists, this is not deletable
			if ($this->checkRecordExists($r->TABLE_NAME, $r->COLUMN_NAME, $this->$propertyName)) {
				return FALSE;
			}

		}

		// nothing found, is deletable
		return TRUE;

	}

	/**
	 * Check whether the DB-table-column is capable to store empty strings.
	 *
	 * @param	string	DB-table-column name.
	 */
	final public static function isEmptiable(string $columnName): ?bool {

		$column = static::getColumnType($columnName);

		if (is_null($column)) {
			return NULL;
		}

		$emptiables = ['char','varchar','tinytext','text','mediumtext','bigtext'];

		if (in_array($column->type, $emptiables) or ('ENUM' == $column->type and in_array('', $column->length))) {
			return TRUE;
		} else {
			return FALSE;
		}


	}

	/**
	 * Check whether the DB-table-column is capable to store null values.
	 *
	 * @param	string	DB-table-column name.
	 */
	final public static function isNullable(string $columnName): ?bool {

		$db = Database::getInstance();
		$column = $db->describeColumn(static::TABLE_NAME, $columnName);

		if (is_null($column)) {
			return NULL;
		}

		return ('YES'==$column->Null ? TRUE : FALSE);

	}

	/**
	 * Check if this object has foreign keys that constraint it. Return TRUE in case of
	 * existing constraints.
	 */
	public function isReferenced(): bool {

		// return flag
		$exists = FALSE;

		// get list of references to check
		$references = $this->db->getInverseForeignKeys(static::TABLE_NAME);

		foreach ($references as $r) {

			// get object property name
			$property = array_search($r->REFERENCED_COLUMN_NAME, static::getBinds());

			// count for existing records that references
			$query = 'SELECT COUNT(*) FROM `' . $r->TABLE_NAME . '` WHERE `' . $r->COLUMN_NAME . '` = ?';
			$count = Database::load($query, [$this->$property], Database::COUNT);

			// set flag as true
			if ($count) $exists = TRUE;

		}

		return $exists;

	}

	/**
	 * Function for serializing the object through json response.
	 */
	public function jsonSerialize(): array {

		$vars = get_object_vars($this);
		unset($vars['keyProperties']);
		unset($vars['db']);
		unset($vars['loadedFromDb']);
		unset($vars['typeList']);
		unset($vars['cache']);
		unset($vars['errors']);
		unset($vars['updatedProperties']);
		unset($vars['dynamicProperties']);

		return $vars;

	}

	/**
	 * Load object from DB and bind with its properties. If DB record is not found,
	 * unset any properties of inherited object, but required props by ActiveRecord.
	 *
	 * @param	int|string|array	Object primary or compound key ID to load.
	 */
	private function loadFromDb(int|string|array $key): void {

		// inherited class
		$class = get_called_class();

		// build the SQL where line
		$where = ' WHERE ' . implode(' AND ', $this->getSqlKeyConditions());

		// load the requested record
		$query = 'SELECT ' . static::getQueryColumns() . ' FROM `' . $class::TABLE_NAME . '`' . $where . ' LIMIT 1';
		$obj = Database::load($query, (array)$key, Database::OBJECT);

		// if db record exists, will populate the object properties
		if (is_object($obj)) {

			$this->populate($obj);
			$this->loadedFromDb = TRUE;

		} else {

			$this->loadedFromDb = FALSE;

		}

	}

	/**
	 * Bind the object properties with all columns coming from database translating the
	 * field names into object properties names. DateTime, Boolean and Integer will be
	 * properly managed.
	 *
	 * @param	\stdClass	Record object as extracted from db table.
	 */
	private function populate(\stdClass $dbRow): void {

		$this->beforePopulate($dbRow);

		$class = get_called_class();
		$varFields = $class::getBinds();

		foreach ($varFields as $objProperty => $dbField) {

			// cast it and assign
			$this->__set($objProperty, $dbRow->$dbField);

		}

		$this->afterPopulate();

	}

	/**
	 * Populates the inherited object with input vars with same name as properties.
	 *
	 * @param	string	Optional list of properties to populate, comma separated. If no items, will tries to populate all columns.
	 */
	public function populateByRequest(): bool {

		$args = func_get_args();

		// all subclass binds
		$binds = static::getBinds();

		foreach (array_keys($binds) as $property) {

			// check that property is in the args or that args is not defined at all
			if (!count($args) or in_array($property, $args)) {

				// get property type
				$type = $this->getPropertyType($property);

				// if input type was set or is bool type
				if (Post::sent($property) or 'bool' == $type) {

					// assign the value to this object property
					$this->__set($property, Post::get($property));

				}

			}

		}

		return TRUE;

	}

	/**
	 * Creates an object with all instance properties for an easy next SQL query for
	 * save data. Datetime properties will be converted to Y-m-d H:i:s or NULL.
	 *
	 * @param	array	List of property name to prepare.
	 */
	private function prepareData(array $properties): \stdClass {

		// trigger before preparing data
		$this->beforePrepareData();

		// properly cast a property of this object and return it
		$cast = function($prop) {

			$field = (string)static::getMappedField($prop);

			if (is_null($this->__get($prop)) and static::isNullable($field)) {
				return NULL;
			}

			switch ($this->getPropertyType($prop)) {

				// integer or bool will cast to integer
				case 'int':
				case 'bool':
					$ret = (int)$this->__get($prop);
					break;

				// should be DateTime, maybe null
				case 'DateTime':
					if (is_a($this->__get($prop), 'DateTime')) {
						$dt = clone $this->__get($prop);
						$dt->setTimezone(Application::getTimeZone());
						$ret = $dt->format('Y-m-d H:i:s');
					} else if (static::isNullable($field)) {
						$ret = NULL;
					} else {
						$field = static::getColumnType($field);
						$ret = 'date' == $field->type ? '0000-00-00' :'0000-00-00 00:00:00';
					}
					break;

				// join array strings in CSV format
				case 'csv':
					$ret = implode(',', array_filter((array)$this->__get($prop)));
					break;

				case 'float':
					$curr = setlocale(LC_NUMERIC, 0);
					setlocale(LC_NUMERIC, 'en_US');
					$ret = (string)$this->__get($prop);
					setlocale(LC_NUMERIC, $curr);
					break;

				case 'json':
					$ret = json_encode($this->__get($prop));
					break;

				// assign with no convertion
				default:
					$ret = $this->__get($prop);
					break;

			}

			return $ret;

		};

		$class = get_called_class();
		$binds = $class::getBinds();

		// create the return object
		$dbObj = new \stdClass();

		foreach ($properties as $prop) {
			if (array_key_exists($prop, $binds)) {
				$dbObj->{$binds[$prop]} = $cast($prop);
			}
		}

		// trigger after prepared data
		$this->afterPrepareData($dbObj);

		return $dbObj;

	}

	/**
	 * Output an object property or method properly formatted and escaped.
	 *
	 * @param	string	Property or method (with or without parentheses) name.
	 */
	public function printHtml(string $name): void {

		// print standard ascii one or a predefined icon HTML as constant
		$printBoolean = function ($value) {
			if ($value) {
				print (defined('PAIR_CHECK_ICON') ? PAIR_CHECK_ICON : '<span style="color:green">√</span>');
			} else {
				print (defined('PAIR_TIMES_ICON') ? PAIR_TIMES_ICON : '<span style="color:red">×</span>');
			}
		};

		// print the class property in the proper way
		if (property_exists($this, $name)) {

			switch ($this->getPropertyType($name)) {

				case 'bool':
					$printBoolean($this->__get($name));
					break;

				case 'DateTime':
					$field = static::getMappedField($name);
					$column = static::getColumnType($field);
					print ('date' == $column->type
					? $this->formatDate($name)
					: $this->formatDateTime($name));
					break;

				case 'csv':
					print htmlspecialchars(implode(', ', $this->__get($name)));
					break;

				case 'json':
					print '<pre>' . Utilities::varToText($this->__get($name), FALSE) . '</pre>';
					break;

				default:
					print nl2br(htmlspecialchars((string)$this->__get($name)));
					break;

			}

		} else {

			// the name is a method, with or without brackets
			if ('()' == substr($name, -2) or method_exists($this, $name)) {

				$methodName = '()' == substr($name, -2) ? substr($name, 0, -2) : $name;

				if (!method_exists($this, $methodName)) {
					$this->addError('The ' . $methodName . '() method to printHtml was not found in the ' . get_called_class() . ' class');
					return;
				}

				// run the method
				$result = $this->$methodName();

			// otherwise the requested value is handled with __get()
			} else {

				$result = $this->__get($name);

			}

			switch (gettype($result)) {

				case 'boolean':
					$printBoolean($result);
					break;

				case 'array':
					htmlspecialchars(implode(', ', $result));
					break;

				// integer, double, string, object, resource, NULL, unknown type
				default:
					print htmlspecialchars((string)$result);
					break;

			}

		}

	}

	/**
	 * Update this object from the current DB record with same primary key.
	 */
	final public function reload(): void {

		$class = get_called_class();

		// properties to not reset
		$propertiesToSave = ['keyProperties', 'db', 'loadedFromDb', 'typeList', 'cache', 'errors'];

		// save key from being unset
		$propertiesToSave = array_merge($propertiesToSave, (array)$this->keyProperties);

		// unset all the other properties
		foreach ($this as $key => $value) {
			if (!in_array($key, $propertiesToSave)) {
				unset($this->$key);
			}
		}

		$this->cache  = [];
		$this->errors = [];

		$this->loadFromDb($this->getSqlKeyValues());

		// log the reload
		Logger::notice('Reloaded ' . $class . ' object with ' . $this->getKeysForEventlog(), Logger::DEBUG);

	}

	/**
	 * Reset the object error list.
	 */
	final public function resetErrors() {

		$this->errors = [];

	}

	public function serialize(): string {

		return serialize($this->getAllProperties());

	}

	/**
	 * Adds to object’s cache a variable.
	 *
	 * @param	string	Name of the cached variable.
	 * @param	mixed	Variable value to cache.
	 */
	final public function setCache(string $name, $value): void {

		$this->cache[$name] = $value;

	}

	/**
	 * This method will convert a value to a Datetime property starting from a string or DateTime obj.
	 * It will also sets time zone for all created datetimes with daylight saving value.
	 * Integer timestamps are only managed as UTC.
	 *
	 * @param	mixed	Property’s value.
	 */
	private function convertToDatetime(mixed $value): ?\DateTime  {

		$dtz = Application::getTimeZone();

		// timestamp is acquired in UTC only, any DTZ doesn't affect its value
		if (Env::get('UTC_DATE') and (is_int($value) or (is_string($value) and ctype_digit($value)))) {

			$castedValue = new \DateTime('@' . (int)$value);
			$castedValue->setTimezone($dtz);

		// data generic string datetime or date
		} else if (is_string($value)) {

			if (in_array($value, ['0000-00-00 00:00:00','0000-00-00',''])) {

				$castedValue = NULL;

			}  else {

				// acquired as current user timezone
				$castedValue = new \DateTime($value, $dtz);

			}

		// already DateTime object
		} else if (is_a($value, '\DateTime')) {

			// sets the user current tz and assigns
			$value->setTimeZone($dtz);
			$castedValue = $value;

		// no recognized type/format
		} else {

			$castedValue = NULL;

		}

		return $castedValue;

	}

	/**
	 * Create into database the current object values or update it if exists based on table’s
	 * keys and auto-increment property. Return TRUE if write is completed succesfully.
	 */
	final public function store(): bool {

		$objectId = $this->getId();

		// update if object’s keys are populated
		$update = ($objectId and $this->areKeysPopulated() and static::exists($objectId));

		// hook for tasks to be executed before store
		$this->beforeStore();

		$update ? $this->update() : $this->create();

		// hook for tasks to be executed after store
		$this->afterStore();

		return TRUE;

	}

	public function toArray(): array {

		$properties = $this->getAllProperties();
		$array = [];

		foreach ($properties as $property => $value) {
			$array[$property] = $value;
		}

		return $array;

	}

	public function toJson(int $options = 0): string {

		return json_encode($this->toArray(), $options);

	}

	public function unserialize(mixed $data): void {

		$unserializedData = unserialize($data);

		foreach ($unserializedData as $property => $value) {
			$this->__set($property, $value);
		}

	}

	/**
	 * Reset a cache variable by its name.
	 *
	 * @param	string	Name of the cached variable.
	 */
	final public function unsetCache(string $name): void {

		if (is_array($this->cache) and isset($this->cache[$name])) {
			unset ($this->cache[$name]);
		}

	}

	/**
	 * Load all records in a table from the DB and store them in the Application cache,
	 * then look for the required property in this list. It is very useful for repeated
	 * searches on small tables of the DB, eg. less than 1000 records.
	 */
	final public static function unsetCachedList(): void {

		$app = Application::getInstance();
		$class = get_called_class();
		$app->unsetState($class . 'ObjectList');

	}

	/**
	 * Store into db the current object properties with option to write only a subset of
	 * declared properties. Return TRUE if success.
	 *
	 * @param	mixed	Optional array of subject properties or single property to update.
	 */
	final public function update(mixed $properties=NULL): bool {

		if (!$this->areKeysPopulated()) {

			$errCode = static::hasSimpleKey()
				? ErrorCodes::PRIMARY_KEY_NOT_POPULATED
				: ErrorCodes::COMPOSITE_PRIMARY_KEY_NOT_POPULATED;

			throw new PairException(implode(', ', $this->keyProperties) . ' not populated', $errCode);

		}

		// hook for tasks to be executed before creation
		$this->beforeUpdate();

		$app	= Application::getInstance();
		$class	= get_called_class();
		$binds	= static::getBinds();

		// populate updatedAt if it exists
		if (property_exists($class, 'updatedAt')) {
			$this->updatedAt = new \DateTime('now', Application::getTimeZone());
		}

		// populate updatedBy if it exists
		if (isset($app->currentUser->id) and property_exists($class, 'updatedBy')) {
			$this->updatedBy = $app->currentUser->id;
		}

		// if the property list is empty, it will include everything
		$properties	= (array)$properties;
		if (!count($properties)) {
			$properties = array_keys($class::getBinds());
		}

		$logParam = $this->getKeysForEventlog();

		// set an object with the columns to update
		$dbObj = $this->prepareData($properties);

		// force to array
		$keysValues = $this->getKeysValues();

		$dbKey = new \stdClass();

		// set the table key with values
		foreach ($keysValues as $key => $value) {

			// get object property value
			$dbKey->{$binds[$key]} = $value;

		}

		$this->db->updateObject($class::TABLE_NAME, $dbObj, $dbKey, static::getEncryptableFields());

		// reset updated-properties tracker
		$this->updatedProperties = [];

		$className = basename(str_replace('\\', '/', $class));
		
		// suppress notices for error logs to avoid loops
		if ('error_logs' != static::TABLE_NAME) {
			Logger::notice('Updated ' . $className . ' object with ' . $logParam, Logger::DEBUG);
		}

		// check and update this object in the common cache
		$uniqueId = is_array($this->getId()) ? implode('-', $this->getId()) : (string)$this->getId();
		if (isset($app->activeRecordCache[$class][$uniqueId])) {
			$app->putActiveRecordCache($class, $this);
			if ('error_logs' != static::TABLE_NAME) {
				Logger::notice('Updated ' . $className . ' object with id=' . $uniqueId . ' in common cache', Logger::DEBUG);
			}
		}

		// hook for tasks to be executed after creation
		$this->afterUpdate();

		return TRUE;

	}

	/**
	 * Store into db the current object properties avoiding null properties.
	 */
	final public function updateNotNull(): bool {

		$class		= get_called_class();
		$binds		= $class::getBinds();
		$properties	= [];

		foreach (array_keys($binds) as $objProp) {

			if (!is_null($this->$objProp))  {
				$properties[] = $objProp;
			}

		}

		$ret = $this->update($properties);

		return $ret;

	}

}