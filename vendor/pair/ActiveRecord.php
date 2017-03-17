<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

/**
 * Base class for active record pattern. Supports tables with a primary key, not suitable for compound key.
 */
abstract class ActiveRecord {
	
	/**
	 * Db handler object.
	 * @var Database
	 */
	protected $db;
	
	/**
	 * ID name or name list for inherit object.
	 * @var int|string|array
	 */
	protected $keyProperty;
	
	/**
	 * TRUE if object has been loaded from database.
	 * @var bool
	 */
	private $loadedFromDb = FALSE;

	/**
	 * List of special properties that will be cast (name => type).
	 * @var array:string
	 */
	private $typeList = array();
	
	/**
	 * Cache for any variable type.
	 * @var array:multitype
	 */
	private $cache = array();
	
	/**
	 * List of all errors tracked.
	 * @var array
	 */
	private $errors = array();
	
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
		$this->keyProperty = array();
		
		// find and assign each field of compound key as array item
		foreach ($tableKey as $field) {
			$this->keyProperty[] = array_search($field, $binds);
		}
		
		$this->init();

		// db row, will populate each property with bound field value
		if (is_a($initParam, 'stdClass')) {
			
			$this->populate($initParam);
			
		// primary or compound key, loads the whole object from db
		} else if (is_int($initParam) or (is_string($initParam) and strlen($initParam)>0)
				or (static::hasCompoundKey() and count($this->keyProperty) == count($initParam))) {
			
			$this->loadFromDb($initParam);
			
		}

	}
	
	/**
	 * Return property’s value if set. Throw an exception and return NULL if not set.
	 *
	 * @param	string	Property’s name.
	 * 
	 * @throws	Exception
	 * 
	 * @return	mixed|NULL
	 */
	public function __get($name) {
	
		try {
	
			if (!property_exists($this, $name)) {
				throw new \Exception('Property “'. $name .'” doesn’t exist for object '. get_called_class());
			}
	
			return $this->$name;
	
		} catch (\Exception $e) {
				
			trigger_error($e->getMessage());
			return NULL;
	
		}
	
	}
	
	/**
	 * Magic method to set an object property value. If DateTime property, will properly
	 * manage integer or string date.
	 * 
	 * @param	string	Property’s name.
	 * @param	mixed	Property’s value.
	 */
	public function __set($name, $value) {

		try {
			
			$type = $this->getPropertyType($name);
				
			if (is_null($value)) {

				// CSV NULL becomes empty array
				$this->$name = $type == 'csv' ? array() : NULL;

			} else {

				switch ($type) {

					case 'bool':
						$this->$name = (bool)$value;
						break;
						
					case 'float':
						$this->$name = (float)$value;
						break;
						
					case 'int':
						$this->$name = (int)$value;
						break;

					case 'DateTime':
						$this->setDatetimeProperty($name, $value);
						break;

					// split string parts by comma in array 
					case 'csv':
						if (is_string($value)) {
							$this->$name = '' == $value ? array() : explode(',', $value);
						} else {
							$this->$name = (array)$value;
						}
						break;

					// as default it will be uncast
					default:
					case 'string':
						$this->$name = @$value;
						break;
					
				}

			}

		} catch (\Exception $e) {

			$txt = 'Property ' . $name . ' cannot get value ' . $value . ': ' . $e->getMessage();
			$this->addError($txt);

			$app = Application::getInstance();
			$app->logError($txt);

		}
	
	}

	/**
	 * Prevents fatal error on unexistent functions.
	 *
	 * @param	string	$name
	 * @param	array	$arguments
	 */
	public function __call($name, $arguments) {
	
		$options = Options::getInstance();
		
		if (!method_exists($this, $name)) {
			if ($options->getValue('development')) {
				$backtrace = debug_backtrace();
				$app = Application::getInstance();
				$app->logError('Method '. get_called_class() . $backtrace[0]['type'] . $name .'(), which doesn’t exist, has been called by '. $backtrace[0]['file'] .' on line '. $backtrace[0]['line']);
			}
		}
	
	}
	
	/**
	 * Method called by constructor just before populate this object.
	 */
	protected function init() {}
	
	/**
	 * Returns array with matching object property name on related db fields.
	 *
	 * @return array
	 */
	protected static function getBinds() {
		
		return array();
		
	}
	
	/**
	 * Bind the object properties with all fields coming from database translating the
	 * field names into object properties names. DateTime, Boolean and Integer will be
	 * properly managed.
	 * 
	 * @param	stdClass	Record object as extracted from db table.
	 */
	final private function populate($dbRow) {
		
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
	 * Trigger function called before populate() method execution.
	 * 
	 * @param	stdClass	Object with which populate(), here passed by reference.
	 */
	protected function beforePopulate(&$dbRow) {}
	
	/**
	 * Trigger function called after populate() method execution.
	 */
	protected function afterPopulate() {}
	
	/**
	 * Creates an object with all instance properties for an easy next SQL query for
	 * save data. Datetime properties will be converted to Y-m-d H:i:s or NULL.
	 *
	 * @param	array	List of property name to prepare.
	 * 
	 * @return	stdClass
	 */
	final private function prepareData($properties) {
	
		// trigger before preparing data
		$this->beforePrepareData();
		
		// force to array
		$properties	= (array)$properties;
		
		$class = get_called_class();
		$binds = $class::getBinds();
		
		// properly cast a property of this object and return it
		$cast = function($prop) {

			switch ($this->getPropertyType($prop)) {
				
				// integer or bool will cast to integer
				case 'int':
				case 'bool':
					$ret = is_null($this->$prop) ? NULL : (int)$this->$prop;
					break;
				
				// should be DateTime, maybe null
				case 'DateTime':
					if (is_a($this->$prop, 'DateTime')) {
						$dt = clone($this->$prop);
					$dt->setTimezone(new \DateTimeZone(BASE_TIMEZONE));
						$ret = $dt->format('Y-m-d H:i:s');
					} else {
						$ret = NULL;
					}
					break;
						
				// join array strings in CSV format 
				case 'csv':
					$ret = implode(',', array_filter($this->$prop));
					break;

				// assign with no convertion
				default:
					$ret = $this->$prop;
					break;
					
			}
			
			return $ret;
			
		};
		
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
	 * Trigger function called before prepareData() method execution.
	 */
	protected function beforePrepareData() {}
	
	/**
	 * Trigger function called after prepareData() method execution.
	 * 
	 * @param	stdClass	PrepareData() returned variable (passed here by reference).
	 */
	protected function afterPrepareData(&$dbObj) {}
	
	/**
	 * Load object from DB and bind with its properties. If DB record is not found,
	 * unset any properties of inherited object, but required props by ActiveRecord.
	 * 
	 * @param	int|string|array	Object primary or compound key ID to load.
	 */
	final private function loadFromDb($key) {
		
		// inherited class
		$class = get_called_class();
		
		// build the SQL where line
		$where = ' WHERE ' . implode(' AND ', $this->getSqlKeyConditions());
		
		// load the requested record
		$query = 'SELECT * FROM ' . $class::TABLE_NAME . $where . ' LIMIT 1';
		$this->db->setQuery($query);
		$obj = $this->db->loadObject($key);

		// if db record exists, will populate the object properties
		if (is_object($obj)) {
			
			$this->populate($obj);
			$this->loadedFromDb = TRUE;

		} else {
			
			$this->loadedFromDb = FALSE;
			
		}
		
	}
	
	/**
	 * Update this object from the current DB record with same primary key.
	 */
	final public function reload() {

		$app = Application::getInstance();
		$class = get_called_class();
		
		// properties to not reset
		$propertiesToSave = array('keyProperty', 'db', 'loadedFromDb', 'typeList', 'cache', 'errors');
		
		// save key from being unset
		$propertiesToSave = array_merge($propertiesToSave, $this->keyProperty);

		// unset all the other properties
		foreach ($this as $key => $value) {
			if (!in_array($key, $propertiesToSave)) {
				unset($this->$key);
			}
		}
		
		$this->cache  = array();
		$this->errors = array();
		
		$this->loadFromDb($this->getSqlKeyValues());
		
		// log the reload 
		$app->logEvent('Reloaded ' . $class . ' object with ' . $this->getKeyForEventlog());
		
	}
	
	/**
	 * Returns TRUE if inherited object has been loaded from db.
	 * 
	 * @return boolean
	 */
	final public function isLoaded() {
		
		return $this->loadedFromDb;
		
	}

	/**
	 * Return TRUE if the ID(s) property variable has a value.
	 *
	 * @return boolean
	 */
	public function isPopulated() {

		$populated = TRUE;
		
		$keys = (array)$this->getId();
		
		if (!count($keys)) return FALSE;
		
		foreach ($keys as $k) {
			if (!$k) $populated = FALSE;
		}

		return $populated;

	}

	/**
	 * Reveal if children class has a compound key as array made by one field at least.
	 * 
	 * @return boolean
	 */
	final private static function hasCompoundKey() {

		$class = get_called_class();
		$res = (is_array($class::TABLE_KEY) and count($class::TABLE_KEY) > 1);
		return $res;

	}
	
	/**
	 * Check if key name is set as table key or is into compound key array for this object.
	 * 
	 * @param	string|int	Single key name.
	 * 
	 * @return	boolean
	 */
	final private function isTableKey($keyName) {
		
		return (in_array($keyName, $this->keyProperty));

	}

	/**
	 * Build a list of SQL conditions to select the current mapped object into DB.
	 * 
	 * @return string[]
	 */
	final private function getSqlKeyConditions() {
	
		$class		= get_called_class();
		$tableKey	= (array)$class::TABLE_KEY;
		$conds		= array();
		
			foreach ($tableKey as $field) {
				$conds[] = $this->db->escape($field) . ' = ?';
			}
		
		return $conds;

	}

	/**
	 * Return an indexed array with current table key values regardless of object
	 * properties value.
	 *
	 * @return array
	 */
	final private function getSqlKeyValues() {
		
		// force to array
		$propertyNames = (array)$this->keyProperty;

		// list to return
		$values = array();

		foreach ($propertyNames as $name) {
			$values[] = $this->{$name};
		}

		return $values;

	}

	/**
	 * Return a list of primary or compound key of this object. 
	 * 
	 * @return string
	 */
	final private function getKeyForEventlog() {

		$class = get_called_class();

		// force to array
		$properties = (array)$this->keyProperty;

		$keyParts = array();

		foreach ($properties as $propertyName) {
			$keyParts[] = $propertyName . '=' . $this->$propertyName;
		}

		return implode(', ', $keyParts);

	}
	
	/**
	 * Store into database the current object values and return the result.
	 * 
	 * @return	bool
	 */
	final public function store() {
		
		if ($this->isPopulated()) {
			return $this->update();
		} else {
			return $this->create();
		}
		
	}
	
	/**
	 * Create this object as new database record and will assign its primary key
	 * as $id property. Null properties won’t be written in the new row.
	 * Return TRUE if success.
	 * 
	 * @return bool
	 */
	final public function create() {
		
		$app = Application::getInstance();
		
		// trigger for tasks to be executed before creation
		$this->beforeCreate();

		// get list of class property names
		$class = get_called_class();
		$list = array_keys($class::getBinds());
		
		$properties = array();
		
		// assemble list of not null properties
		foreach ($list as $prop) {
			if (!is_null($this->$prop)) $properties[] = $prop;
		}
		
		$dbObj = $this->prepareData($properties);
		$res = $this->db->insertObject($class::TABLE_NAME, $dbObj);

		if (!static::hasCompoundKey()) {

			$lastInsertId = $this->db->getLastInsertId();
		
			$key = $this->keyProperty[0];
			
			if ('int' == $this->getPropertyType($key)) {
				$this->{$key} = (int)$lastInsertId;
			} else {
				$this->{$key} = $lastInsertId;
			}
			
		}

		// set logs
		$keyParts = array();
			
		foreach ($this->keyProperty as $prop) {
			$keyParts[] = $prop . '=' . $this->{$prop};
		}
			
		$app->logEvent('Created a new ' . $class . ' object with ' . implode(', ' , $keyParts));
		
		// trigger for tasks to be executed before creation
		$this->afterCreate();
		
		return (bool)$res;
	
	}
	
	/**
	 * Trigger function called before create() method execution.
	 */
	protected function beforeCreate() {}
	
	/**
	 * Trigger function called after create() method execution.
	 */
	protected function afterCreate() {}
	
	/**
	 * Store into db the current object properties with option to write only a subset of
	 * declared properties.
	 * 
	 * @param	mixed	Optional array of subject properties or single property to update.
	 * 
	 * @return	bool
	 */
	final public function update($properties=NULL) {
		
		$this->beforeUpdate();
		
		$app	= Application::getInstance();
		$class	= get_called_class();
		$binds	= static::getBinds();
		
		// if property list is empty, will include all
		$properties	= (array)$properties;
		if (!count($properties)) {
			$properties = array_keys($class::getBinds());
		}
		
		$logParam = $this->getKeyForEventlog();

		// require table primary key and force its assign
		if ($this->isPopulated()) {

			// set an object with fields to update
			$dbObj = $this->prepareData($properties);

			// force to array
			$key = (array)$this->keyProperty;

			$dbKey = new \stdClass();

			// set the table key with values
			foreach ($key as $k) {
				
				// get object property value
				$dbKey->{$binds[$k]} = $this->$k;
					
			}
				
			$res = (bool)$this->db->updateObject($class::TABLE_NAME, $dbObj, $dbKey);
			
			$app->logEvent('Updated ' . $class . ' object with ' . $logParam);

		// object is not populated
		} else {

			$res = FALSE;
			$app->logError('The ' . $class . ' object with ' . $logParam . ' cannot be updated');

		}

		$this->afterUpdate();
		
		return $res;
		
	}
	
	/**
	 * Store into db the current object properties avoiding null properties.
	 * 
	 * @return	bool
	 */
	final public function updateNotNull() {
	
		$class		= get_called_class();
		$binds		= $class::getBinds();
		$properties	= array();
		
		foreach ($binds as $objProp => $dbField) {
				
			if (!is_null($this->$objProp))  {
				$properties[] = $objProp;
			}
				
		}
		
		$ret = $this->update($properties);
		
		return $ret;

	}
	
	/**
	 * Trigger function called before update() or updateNotNull() method execution.
	 */
	protected function beforeUpdate() {}
	
	/**
	 * Trigger function called after update() or updateNotNull() method execution.
	 */
	protected function afterUpdate() {}
	
	/**
	 * Deletes this object’s line from database and returns deletion success.
	 * 
	 * @return	bool
	 */
	final public function delete() {
	
		if (!$this->getId()) return FALSE;
		
		// trigger a custom function before deletion
		$this->beforeDelete();
		
		$class = get_called_class();
		
		// build the SQL where line
		$where = ' WHERE ' . implode(' AND ', $this->getSqlKeyConditions());
		
		$query = 'DELETE FROM ' . $class::TABLE_NAME . $where . ' LIMIT 1';
		$res = $this->db->exec($query, $this->getSqlKeyValues());
		
		// list properties to not remove
		$activeRecordsProperties = array('db', 'loadedFromDb', 'typeList', 'errors');
		
		// unset all properties
		foreach ($this as $key => $value) {
			if (!in_array($key, $activeRecordsProperties)) {
				unset($this->$key);
			}
		}
		
		$this->loadedFromDb = FALSE;
		
		// trigger a custom function after deletion
		$this->afterDelete();

		return (bool)$res;
		
	}
	
	/**
	 * Trigger function called before delete() method execution.
	 */
	protected function beforeDelete() {}
	
	/**
	 * Trigger function called after delete() method execution.
	 */
	protected function afterDelete() {}
	
	/**
	 * Check if this object has foreign keys that constraint it. Return TRUE in case of
	 * existing constraints.
	 * 
	 * @return boolean
	 */
	public function isReferenced() {
		
		// return flag
		$exists = FALSE;
		
		// get list of references to check
		$references = $this->db->getTableReferences(static::TABLE_NAME);
		
		foreach ($references as $r) {
			
			// get object property name
			$property = array_search($r->REFERENCED_COLUMN_NAME, static::getBinds());
			
			// count for existing records that references
			$query =
				'SELECT COUNT(*)' .
				' FROM ' . $this->db->escape($r->TABLE_NAME) .
				' WHERE ' . $this->db->escape($r->COLUMN_NAME) . ' = ?';
			
			$this->db->setQuery($query);
			$count = $this->db->loadCount($this->$property);

			// set flag as true
			if ($count) $exists = TRUE;
			
		}
		
		return $exists;
		
	}
	
	/**
	 * Set boolean variable names for convertion when populating the object and
	 * writing into db.
	 *
	 * @param	string	List of variable names.
	 */
	final protected function bindAsBoolean() {
	
		foreach (func_get_args() as $name) {
			$this->typeList[$name] = 'bool';
		}
	
	}
	
	/**
	 * Set DateTime variable names for convertion when populating the object and
	 * writing into db.
	 *
	 * @param	string	List of variable names.
	 */
	final protected function bindAsDatetime() {
	
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
	final protected function bindAsFloat() {
	
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
	final protected function bindAsInteger() {
		
		foreach (func_get_args() as $name) {
			$this->typeList[$name] = 'int';
		}
		
	}
	
	/**
	 * Set CSV type variable names for convertion when populating the object and
	 * writing into db.
	 *
	 * @param	string	List of variable names.
	 */
	final protected function bindAsCsv() {
	
		foreach (func_get_args() as $name) {
			$this->typeList[$name] = 'csv';
		}
	
	}
	
	/**
	 * Return the property PHP type (bool, int, DateTime, float or string).
	 * 
	 * @return string|NULL
	 */
	final private function getPropertyType($name) {
		
		return array_key_exists($name, $this->typeList) ? $this->typeList[$name] : NULL;
	
	}
	
	/**
	 * This method will populates a Datetime property with strings or DateTime obj. It
	 * will also sets time zone for all created datetimes with daylight saving value.
	 * Integer timestamps are only managed as UTC.
	 *  
	 * @param	string	Property’s name.
	 * @param	mixed	Property’s value.
	 */
	final private function setDatetimeProperty($propertyName, $value) {
		
		$app = Application::getInstance();
		
		// in login page the currentUser doesn’t exist
		if (is_a($app->currentUser, 'User')) {
			$dtz = $app->currentUser->getDateTimeZone();
		} else {
			$dtz = new \DateTimeZone(BASE_TIMEZONE);
		}
		
		// timestamp is acquired in UTC only, any DTZ doesn't affect its value
		if (defined('UTC_DATE') and UTC_DATE and (is_int($value) or ctype_digit($value))) {

			$this->$propertyName = new \DateTime('@' . (int)$value);
			$this->$propertyName->setTimezone($dtz);
			
		// data generic string datetime or date
		} else if (is_string($value)) {
			
			if ('0000-00-00 00:00:00'==$value or '0000-00-00'==$value) {

				$this->$propertyName = NULL;

			}  else {

				// acquired as current user timezone
				$this->$propertyName = new \DateTime($value, $dtz);
				
			}
			
		// already DateTime object
		} else if (is_a($value, 'DateTime')) {

			// sets the user current tz and assigns
			$value->setTimeZone($dtz);
			$this->$propertyName = $value;

		// no recognized type/format
		} else {

			//$app->logWarning('Unrecognized date variable for ' . $propertyName . ' type ' . gettype($value) . ' having value ' . Utilities::varToText($value));
			$this->$propertyName = NULL;
			
		}
		
	}
	
	/**
	 * Compare object properties with related DB table fields, with proper cast. Doesn’t
	 * compare other object fields.
	 * 
	 * @return	bool
	 */
	final public function hasChanged() {

		$class = get_called_class();
		$varFields = $class::getBinds();

		// create a new similar object that populates properly
		$newObj = new $class($this->{$this->keyProperty});
		
		if (!$newObj) return TRUE;
		
		foreach ($varFields as $property => $field) {
			if ($this->$property != $newObj->$property) {
				return TRUE;
			}
		}
		
		return FALSE;
		
	}

	/**
	 * Check if this object still exists in DB as record. Return TRUE if exists.
	 * 
	 * @return	bool
	 */
	final public function existsInDb() {

		$class = get_called_class();
		$conds = implode(' AND ', $this->getSqlKeyConditions());

		$this->db->setQuery('SELECT COUNT(1) FROM ' . $class::TABLE_NAME . ' WHERE ' . $conds);
		
		return (bool)$this->db->loadCount($this->getId());
	
	}
	
	/**
	 * Class to self-test its children on properties-dbfields couples. Returns error count.
	 * 
	 * @return int
	 */
	final public function selfTest() {
		
		$app = Application::getInstance();
		
		$class = get_called_class();
		
		// count nr of errors found on each class
		$errorCount = 0;
		
		// all binds
		$binds = $class::getBinds();
		
		// all properties
		$properties = get_object_vars($this);
		
		// all db fields
		$this->db->setQuery('SHOW COLUMNS FROM ' . $this->db->escape($class::TABLE_NAME));
		$dbFields = $this->db->loadResultList();
		
		foreach ($binds as $property=>$field) {
			
			// looks for object declared property and db bind field
			if (!array_key_exists($property, $properties)) {
				$errorCount++;
				$app->logError('Class ' . $class . ' is missing property “' . $property . '”');
			}
			
			if (!in_array($field, $dbFields)) {
				$errorCount++;
				$app->logError('Class ' . $class . ' is managing unexistent field “' . $field . '”');
			}
			
		}
		
		// second scan for binding added db fields
		foreach ($dbFields as $field) {
				
			if (!in_array($field, $binds)) {
				$errorCount++;
				$app->logError('Class ' . get_called_class() . ' is not binding “' . $field . '” in method getBinds()');
			}
				
		}

		return $errorCount;
		
	}
	
	/**
	 * Add an error to object’s error list.
	 *
	 * @param	string	Error message’s text.
	 */
	final public function addError($message) {
	
		$this->errors[] = $message;
	
	}
	
	/**
	 * Return text of latest error. In case of no errors, return FALSE.
	 *
	 * @return FALSE|string
	 */
	final public function getLastError() {
	
		return end($this->errors);
	
	}

	/**
	 * Return an array with text of all errors.
	 *
	 * @return array
	 */
	final public function getErrors() {
	
		return $this->errors;
	
	}
	
	/**
	 * Reset the object error list.
	 */
	final public function resetErrors() {
		
		$this->errors = array();

	}
	
	/**
	 * Gets all objects of the inherited class with where conditions and order clause.
	 * 
	 * @param	array	Optional array of query filters, array(property-name => value). 
	 * @param	array	Optional array of order by, array(property-name) or array(property-name => 'DESC').
	 * 
	 * @return	array:mixed
	 */
	final public static function getAllObjects($filters = array(), $orderBy = array()) {

		$app		= Application::getInstance();
		$db			= Database::getInstance();
		$class		= get_called_class();
		$binds		= $class::getBinds();
		
		$where		= '';
		$conds		= array();
		$whereLog	= '';
		
		$order		= '';
		$orderClause= array();
		
		if (is_array($filters)) {
			
			// iterate all filters
			foreach ($filters as $property => $value) {
			
				// check if filter is valid and binds really
				if (is_string($property) and strlen($property) and array_key_exists($property, $binds)) {
	
					// gets the table field name
					$field = $binds[$property];

					// creates where condition
					$conds[] = $field . (is_null($value) ? ' IS NULL' : ' = ' . (is_int($value) ? $value : $db->quote($value)));
					
				} else {
					
					trigger_error('In method ' . $class . '::getAllObject() unexistent property “' . $property . '” can’t be used as filter');
					
				}
					
			}
			
			// log message
			$whereLog .= count($conds) ? ' under condition WHERE ' . implode(' AND ', $conds) : '';
			
			// builds where
			$where = count($conds) ? ' WHERE ' . implode(' AND ', $conds) : '';

		}
		
		if (is_array($orderBy)) {
			
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
					if (!$direction or !in_array(strtolower($direction), array('asc','desc'))) {
						$direction = '';
					}
					
					$orderClause[] = '`' . $field . '` ' . strtoupper($direction);
					
				} else {
					
					trigger_error('In method ' . $class . '::getAllObject() unexistent property “' . $property . '” can’t be used as filter');
					
				}

			}
			
			// builds order by
			$order = count($orderClause) ? ' ORDER BY ' . implode(', ', $orderClause) : '';

		}
		
		// runs query
		$db->setQuery('SELECT * FROM ' . $class::TABLE_NAME . $where . $order);
		$list = $db->loadObjectList();
	
		$rets = array();

		if (is_array($list)) {

			// builds each object
			foreach ($list as $row) {
				$rets[] = new $class($row);
			}
			
		}
		
		$app->logEvent('Loaded ' . count($rets) . ' ' . $class . ' objects' . $whereLog);
		
		return $rets;
	
	}
	
	/**
	 * Count all objects of the inherited class with where conditions and order clause.
	 *
	 * @param	array	Optional array of query filters, array(property-name => value).
	 *
	 * @return	int
	 */
	final public static function countAllObjects($filters = array()) {
	
		$app		= Application::getInstance();
		$db			= Database::getInstance();
		$class		= get_called_class();
		$binds		= $class::getBinds();
	
		$where		= '';
		$conds		= array();
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
						
					trigger_error('In method ' . $class . '::getAllObject() unexistent property “' . $property . '” can’t be used as filter');
						
				}
					
			}
				
			// log message
			$whereLog .= count($conds) ? ' under condition ' . implode(' AND ', $conds) : '';
				
			// builds where
			$where = count($conds) ? ' WHERE ' . implode(' AND ', $conds) : '';
	
		}

		// runs query
		$db->setQuery('SELECT COUNT(1) FROM ' . $class::TABLE_NAME . $where);
		$count = $db->loadCount();
	
		$app->logEvent('Counted ' . $count . ' ' . $class . ' objects' . $whereLog);
	
		return $count;
	
	}
	
	/**
	 * Return TRUE if db record with passed primary or compound key exists. Faster method.
	 * 
	 * @param	mixed	Primary or compound key for this object table.
	 * 
	 * @return	bool
	 */
	final public static function exists($key) {
		
		// initialize some vars
		$db			= Database::getInstance();
		$class		= get_called_class();
		$tableKey	= $class::TABLE_KEY;
		$conds		= array();

		foreach ($tableKey as $field) {
			$conds[] = $field . ' = ?';
		}
		
		// run the query
		$db->setQuery('SELECT COUNT(1) FROM ' . $class::TABLE_NAME . ' WHERE ' . implode(' AND ', $conds));
		
		// execute and return value
		return (bool)$db->loadCount($key);
		
	}
	
	/**
	 * Returns a variable, NULL in case of variable not found.
	 *
	 * @param	string	Name of the cached variable.
	 *
	 * @return	NULL|multitype
	 */
	final public function getCache($name) {
	
		return ((is_array($this->cache) and array_key_exists($name, $this->cache)) ? $this->cache[$name] : NULL);
	
	}
	
	/**
	 * Adds to object’s cache a variable.
	 * 
	 * @param	string		Name of the cached variable.
	 * @param	multitype	Variable value to cache.
	 */
	final public function setCache($name, $value) {
	
		$this->cache[$name] = $value;
	
	}

	/**
	 * Returns TRUE if object’s cache variable has been previously set.
	 *
	 * @param	string	Name of the cached variable.
	 * 
	 * @return	bool
	 */
	final public function issetCache($name) {
	
		return ((is_array($this->cache) and array_key_exists($name, $this->cache)) ? TRUE : FALSE);
	
	}
	
	/**
	 * Reset a cache variable by its name.
	 *
	 * @param	string	Name of the cached variable.
	 */
	final public function unsetCache($name) {
	
		if (is_array($this->cache) and isset($this->cache[$name])) {
		unset ($this->cache[$name]);
		}
	
	}

	/**
	 * Safely formats and returns a DateTime if valid. If language string LC_DATETIME_FORMAT
	 * is set, a locale translated date is returned.
	 * 
	 * @param	string	Property name of DateTime object.
	 * @param	string	Optional date format, if not passed will get format by language strings.
	 * 
	 * @return	string|NULL
	 */
	final public function formatDateTime($prop, $format=NULL) {

		if (!is_a($this->$prop, 'DateTime')) {
			return NULL;
		}

		$app = Application::getInstance();
		$this->$prop->setTimeZone($app->currentUser->getDateTimeZone());

		// check if format is specified
		if (!$format) {

			$tran = Translator::getInstance();

			// if is set a locale date format, use it
			if ($tran->stringExists('LC_DATETIME_FORMAT')) {

				return strftime($tran->translate('LC_DATETIME_FORMAT'), $this->$prop->getTimestamp());

			// otherwise choose another format
			} else {
				
				$format = $tran->stringExists('DATETIME_FORMAT') ?
						$tran->translate('DATETIME_FORMAT') :
						'Y-m-d H:i:s';
				
			}
			
		}
		
		return $this->$prop->format($format);

	}
	
	/**
	 * Safely format and return a valid DateTime into a readable date. If language string
	 * LC_DATE_FORMAT is set, a locale translated date is returned.
	 *
	 * @param	string	Property name of DateTime object.
	 *
	 * @return	string|NULL
	 */
	final public function formatDate($prop) {
	
		if (!is_a($this->$prop, 'DateTime')) {
			return NULL;
		}
		
		$tran = Translator::getInstance();

		// if is set a locale date format, use it
		if ($tran->stringExists('LC_DATE_FORMAT')) {

			return strftime($tran->translate('LC_DATE_FORMAT'), $this->$prop->getTimestamp());

		// otherwise choose another format
		} else {

			$format = $tran->stringExists('DATE_FORMAT') ?
					$tran->translate('DATE_FORMAT') :
					'Y-m-d H:i:s';

			return $this->formatDateTime($prop, $format);

		}
	
	}

	/**
	 * Utility that works like \get_object_vars() but restricted to bound properties.
	 *   
	 * @return array:multitype
	 */
	final public function getAllProperties() {
		
		$class = get_called_class();
		
		// all subclass binds
		$binds = $class::getBinds();
		
		$properties = array();
		
		foreach ($binds as $property=>$field) {
			$properties[$property] = $this->$property;
		}
		
		return $properties;
		
	}

	/**
	 * Populates the inherited object with input vars with same name as properties.
	 * 
	 * @param	string	Optional list of properties to populate, comma separated. If no items,
	 * 					will tries to populate all fields.
	 */
	final public function populateByRequest() {

		$args  = func_get_args();
		$class = get_called_class();

		// all subclass binds
		$binds = $class::getBinds();

		$properties = array();

		foreach ($binds as $property=>$field) {

			if (!count($args) or (isset($args[0]) and in_array($property, $args[0]))) {

				if ('datetime' == $this->getPropertyType($property)) {
					$inputType = strlen(Input::get($property)) > 10 ? 'datetime' : 'date';
				} else {
					$inputType = 'string';
				}

				$this->$property = Input::get($property, $inputType);

			}

		}

	}

	/**
	 * Generate a Form object with all controls populated and type based on variable.
	 * 
	 * @return	Form
	 */
	public function getForm() {

		$props = $this->getAllProperties();

		$form = new Form();

		foreach ($props as $varName => $value) {

			// primary key
			if ($this->keyProperty == $varName) {

				$control = $form->addInput($varName)->setType('hidden');

			} else {

				switch ($this->getPropertyType($varName)) {

					// bool
					case 'bool':
						$control = $form->addInput($varName)->setType('bool');
						break;

					// datatime
					case 'DateTime':
						$control = $form->addInput($varName)->setType('datetime');
						break;

					// integer
					case 'int':
						$control = $form->addInput($varName)->setType('number');
						break;

					// TODO
					case 'csv':
					default:
						$control = $form->addInput($varName);
						break;

				}

			}

			$control->setValue($value);

		}

		return $form;

	}
	
	/**
	 * Returns unique ID of inherited object or in case of compound key, an indexed array.
	 *
	 * @return int|string|array
	 */
	final public function getId() {
		
		$ids = array();
			
		foreach ($this->keyProperty as $propertyName) {
			$ids[] = $this->{$propertyName};
		}
		
		return (static::hasCompoundKey() ? $ids : $ids[0]);
		
	}

}