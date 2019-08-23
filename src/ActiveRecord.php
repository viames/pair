<?php

namespace Pair;

/**
 * Base class for active record pattern. Supports tables with a primary key, not suitable for compound key.
 */
abstract class ActiveRecord implements \JsonSerializable {
	
	/**
	 * Db handler object.
	 * @var Database
	 */
	protected $db;
	
	/**
	 * List of properties that maps db primary keys.
	 * @var string[]
	 */
	protected $keyProperties;
	
	/**
	 * TRUE if object has been loaded from database.
	 * @var bool
	 */
	private $loadedFromDb = FALSE;

	/**
	 * List of special properties that will be cast (name => type).
	 * @var array:string
	 */
	private $typeList = [];
	
	/**
	 * Cache for any variable type.
	 * @var array:multitype
	 */
	private $cache = [];
	
	/**
	 * List of all errors tracked.
	 * @var array
	 */
	private $errors = [];

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
		$this->keyProperties = array();
		
		// find and assign each field of compound key as array item
		foreach ($tableKey as $field) {
			$this->keyProperties[] = array_search($field, $binds);
		}
		
		$this->init();

		// db row, will populate each property with bound field value
		if (is_a($initParam, 'stdClass')) {
			
			$this->populate($initParam);
			
		// primary or compound key, loads the whole object from db
		} else if (is_int($initParam) or (is_string($initParam) and strlen($initParam)>0)
				or (static::hasCompoundKey() and is_array($initParam) and count($this->keyProperties) == count($initParam))) {
			
			// try to load the object from db
			if (!$this->loadFromDb($initParam)) {
				
				// force init params to array 
				$initParam = (array)$initParam;
				
				// populate this object with passed key properties
				foreach($this->keyProperties as $index => $prop) {
					$this->$prop = isset($initParam[$index]) ? $initParam[$index] : NULL;
				}
				
			}
			
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
						if ('' === $value and $this->isNullable($this->getMappedField($name))) {
							$this->$name = NULL;
						} else {
							$this->$name = (int)$value;
						}
						break;

					case 'json':
						if ('' === $value and $this->isNullable($this->getMappedField($name))) {
							$this->$name = NULL;
						} else {
							$this->$name = json_decode($value);
						}
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
	
		if (!method_exists($this, $name)) {
			if (Application::isDevelopmentHost()) {
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
	 * @return	array
	 */
	protected static function getBinds() {

		$db = Database::getInstance();
		$columns = $db->describeTable(static::TABLE_NAME);
		
		$maps = [];

		foreach ($columns as $col) {
			
			// get a camelCase name, with first low case
			$property = lcfirst(str_replace(' ', '', ucwords(str_replace(['_','\\'], ' ', $col->Field))));
			$maps[$property] = $col->Field;
			
		}

		return $maps;
		
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
	 * @return	mixed
	 */
	final private function prepareData($properties) {
	
		// trigger before preparing data
		$this->beforePrepareData();
		
		// properly cast a property of this object and return it
		$cast = function($prop) {
			
			$field = static::getMappedField($prop);

			switch ($this->getPropertyType($prop)) {
				
				// integer or bool will cast to integer
				case 'int':
				case 'bool':
					if (is_null($this->$prop) and static::isNullable($field)) {
						$ret = NULL;
					} else {
						$ret = (int)$this->$prop;
					}
					break;
				
				// should be DateTime, maybe null
				case 'DateTime':
					if (is_a($this->$prop, 'DateTime')) {
						$dt = clone($this->$prop);
						$dt->setTimezone(new \DateTimeZone(BASE_TIMEZONE));
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
					$ret = implode(',', array_filter((array)$this->$prop));
					break;
					
				case 'float':
					if (is_null($this->$prop) and static::isNullable($field)) {
						$ret = NULL;
					} else {
						$curr = setlocale(LC_NUMERIC, 0);
						setlocale(LC_NUMERIC, 'en_US');
						$ret = (string)$this->$prop;
						setlocale(LC_NUMERIC, $curr);
					}
					break;

				case 'json':
					if (is_null($this->$prop) and static::isNullable($field)) {
						$ret = NULL;
					} else {
						$ret = json_encode($this->$prop);
					}
					break;

				// assign with no convertion
				default:
					if ((is_null($this->$prop) or (''==$this->$prop and !static::isEmptiable($field))) and static::isNullable($field)) {
						$ret = NULL;
					} else {
						$ret = $this->$prop;
					}
					break;
					
			}
			
			return $ret;
			
		};
		
		// force to array
		$properties	= (array)$properties;

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
		$query = 'SELECT ' . static::getQueryColumns() . ' FROM `' . $class::TABLE_NAME . '`' . $where . ' LIMIT 1';
		$this->db->setQuery($query);
		$obj = $this->db->loadObject((array)$key);

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
		$propertiesToSave = array('keyProperties', 'db', 'loadedFromDb', 'typeList', 'cache', 'errors');
		
		// save key from being unset
		$propertiesToSave = array_merge($propertiesToSave, $this->keyProperties);

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
	 * @return		boolean
	 * 
	 * @deprecated	Use areKeysPopulated() instead.
	 */
	public function isPopulated() {

		return $this->areKeysPopulated();

	}
	
	/**
	 * Return TRUE if each key property has a value.
	 *
	 * @return boolean
	 */
	public function areKeysPopulated() {
		
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
	 * @return bool
	 */
	final private static function hasCompoundKey(): bool {

		$class = get_called_class();
		$res = (is_array($class::TABLE_KEY) and count($class::TABLE_KEY) > 1);
		return $res;

	}
	
	/**
	 * Check if a property is mapped to a table primary or compound key field for this object.
	 * 
	 * @param	string	Single key name.
	 * 
	 * @return	bool
	 */
	final private function isKeyProperty(string $propertyName): bool {
		
		return (in_array($propertyName, $this->keyProperties));

	}

	/**
	 * Build a list of SQL conditions to select the current mapped object into DB.
	 * 
	 * @return string[]
	 */
	final private function getSqlKeyConditions(): array {
	
		$class		= get_called_class();
		$tableKey	= (array)$class::TABLE_KEY;
		$conds		= array();
		
			foreach ($tableKey as $field) {
				$conds[] = '`' . $field . '` = ?';
			}
		
		return $conds;

	}

	/**
	 * Return an indexed array with current table key values regardless of object
	 * properties value.
	 *
	 * @return array
	 */
	final private function getSqlKeyValues(): array {
		
		// force to array
		$propertyNames = (array)$this->keyProperties;

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

		// force to array
		$properties = (array)$this->keyProperties;

		$keyParts = array();

		foreach ($properties as $propertyName) {
			$keyParts[] = $propertyName . '=' . $this->$propertyName;
		}

		return implode(', ', $keyParts);

	}
	
	/**
	 * Create into database the current object values or update it if exists based on table’s
	 * keys and auto-increment property. Return TRUE if write is completed succesfully.
	 * 
	 * @return	bool
	 */
	final public function store() {
		
		// hook for tasks to be executed before store
		$this->beforeStore();
		
		// create if object’s keys are populated
		if ($this->areKeysPopulated() and static::exists($this->getId())) {
			$ret = $this->update();
		} else {
			$ret = $this->create();
		}

		// hook for tasks to be executed after store
		$this->afterStore();
		
		return $ret;

	}
	
	/**
	 * Trigger function called before store() method execution.
	 */
	protected function beforeStore() {}
	
	/**
	 * Trigger function called after store() method execution.
	 */
	protected function afterStore() {}
	
	/**
	 * Create this object as new database record and will assign its primary key
	 * as $id property. Null properties won’t be written in the new row.
	 * Return TRUE if success.
	 * 
	 * @return bool
	 */
	final public function create() {

		$app = Application::getInstance();
		$class = get_called_class();
		
		if (!$this->areKeysPopulated() and !$this->db->isAutoIncrement(static::TABLE_NAME)) {
			$app->logEvent('The object’s ' . implode(', ', $this->keyProperties) . ' properties must be populated in order to create a ' . $class . ' record');
			return FALSE;
		}
		
		// hook for tasks to be executed before creation
		$this->beforeCreate();
		
		// get list of class property names
		$props = array_keys(static::getBinds());
		
		// insert the object as db record
		$dbObj = $this->prepareData($props);
		$res = $this->db->insertObject(static::TABLE_NAME, $dbObj, static::getEncryptableFields());

		// get last insert id if not compound key
		if (!static::hasCompoundKey()) {

			$lastInsertId = $this->db->getLastInsertId();
		
			$key = $this->keyProperties[0];
			
			if ('int' == $this->getPropertyType($key)) {
				$this->{$key} = (int)$lastInsertId;
			} else {
				$this->{$key} = $lastInsertId;
			}
			
		}

		// set logs
		$keyParts = array();
			
		foreach ($this->keyProperties as $prop) {
			$keyParts[] = $prop . '=' . $this->{$prop};
		}
		
		// log as application event
		$app->logEvent('Created a new ' . $class . ' object with ' . implode(', ' , $keyParts));
		
		// hook for tasks to be executed after creation
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
		
		// hook for tasks to be executed before creation
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
		if ($this->areKeysPopulated()) {

			// set an object with fields to update
			$dbObj = $this->prepareData($properties);

			// force to array
			$key = (array)$this->keyProperties;

			$dbKey = new \stdClass();

			// set the table key with values
			foreach ($key as $k) {
				
				// get object property value
				$dbKey->{$binds[$k]} = $this->$k;
					
			}
			
			$res = (bool)$this->db->updateObject($class::TABLE_NAME, $dbObj, $dbKey, static::getEncryptableFields());
			
			$app->logEvent('Updated ' . $class . ' object with ' . $logParam);

		// object is not populated
		} else {

			$res = FALSE;
			$app->logError('The ' . $class . ' object with ' . $logParam . ' cannot be updated');

		}
		
		// hook for tasks to be executed after creation
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
		
		$query = 'DELETE FROM `' . $class::TABLE_NAME . '`' . $where . ' LIMIT 1';
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
		$references = $this->db->getInverseForeignKeys(static::TABLE_NAME);
		
		foreach ($references as $r) {
			
			// get object property name
			$property = array_search($r->REFERENCED_COLUMN_NAME, static::getBinds());
			
			// count for existing records that references
			$query = 'SELECT COUNT(*) FROM `' . $r->TABLE_NAME . '` WHERE `' . $r->COLUMN_NAME . '` = ?';
			$count = Database::load($query, [$this->$property], PAIR_DB_COUNT);

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
	 * Set JSON type variable names for convertion when populating the object and
	 * writing into db.
	 *
	 * @param	string	List of variable names.
	 */
	final protected function bindAsJson() {
	
		foreach (func_get_args() as $name) {
			$this->typeList[$name] = 'json';
		}
	
	}
	
	/**
	 * Return the Pair\ActiveRecord inherited object related to this by a ForeignKey in DB-table. Cached method.
	 * 
	 * @param	string	Related property name.
	 * 
	 * @return	multitype|NULL
	 */
	final public function getRelated(string $relatedProperty) {
		
		$cacheName = $relatedProperty . 'RelatedObject';
		
		// object exists in cache, return it
		if ($this->issetCache($cacheName)) {
			return $this->getCache($cacheName);
		}
		
		// get table foreign-keys
		$foreignKeys = $this->db->getForeignKeys(static::TABLE_NAME);
		
		// get field name by mapped property
		$relatedField = $this->getMappedField($relatedProperty);
		
		// the table referenced by fk
		$referencedTable = NULL;
			
		// search the fk-table
		foreach ($foreignKeys as $fk) {
			if ($fk->COLUMN_NAME == $relatedField) {
				$referencedTable  = $fk->REFERENCED_TABLE_NAME;
				$referencedColumn = $fk->REFERENCED_COLUMN_NAME;
				break;
			}
		}
		
		// if not table is referenced, raise an error
		if (!$referencedTable) {
			$this->addError('Property ' . $relatedProperty . ' has not a foreign-key mapped into DB');
			return NULL;
		}
		
		// class that maps the referenced table
		$relatedClass = NULL;
		$loadedClasses = \get_declared_classes();
		
		// search in loaded classes
		foreach ($loadedClasses as $c) {
			if (is_subclass_of($c, 'Pair\ActiveRecord') and property_exists($c, 'TABLE_NAME') and $c::TABLE_NAME == $referencedTable) {
				$relatedClass = $c;
				break;
			}
		}
			
		// class cannot be found
		if (!$relatedClass) {
			
			// if not found, search in the whole application (FIXME encapsulation violated here...)
			$classes = Utilities::getActiveRecordClasses();
			
			// search for required one
			foreach ($classes as $class => $opts) {
				if ($opts['tableName'] == $referencedTable) {
					include_once($opts['folder'] . '/' . $opts['file']);
					$relatedClass = $class;
					break;
				}
			}
			
		}
		
		// class cannot be found
		if (!$relatedClass) {
			$this->addError('Table ' . $referencedTable . ' has not any Pair-class mapping');
			return NULL;
		}
		
		// create the new wanted Pair object
		$obj = new $relatedClass($this->$relatedProperty);
		
		// if loaded, return it otherwise NULL
		$ret = ($obj->isLoaded() ? $obj : NULL);
		
		// related object is being registered in cache of this object
		$this->setCache($cacheName, $ret);
		
		return $ret;
		
	}

	/**
	 * Extended method to return a property value of the Pair\ActiveRecord inherited object related to
	 * this by a ForeignKey in DB-table. Cached method.
	 *
	 * @param	string	Related property name, belongs to this object.
	 * @param	string	Wanted property name, belongs to related object.
	 *
	 * @return	multitype|NULL
	 */
	final public function getRelatedProperty(string $relatedProperty, string $wantedProperty) {
		
		$obj = $this->getRelated($relatedProperty);
		
		if ($obj) {
			return $obj->$wantedProperty;
		} else {
			return NULL;
		}
		
	}
		
	/**
	 * Create an object for a table column configuration within an object or NULL if column
	 * doesn’t exist.
	 * 
	 * @param	string	Field name.
	 * 
	 * @return	NULL|stdClass
	 */
	final private static function getColumnType(string $fieldName): ?\stdClass {
		
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
	 * Check whether the DB-table-field is capable to store null values.
	 * 
	 * @param	string	DB-table-field name.
	 * 
	 * @return	bool|NULL
	 */
	final public static function isNullable(string $fieldName): ?bool {
		
		$db = Database::getInstance();
		$column = $db->describeColumn(static::TABLE_NAME, $fieldName);
		
		if (is_null($column)) {
			return NULL;
		}
		
		return ('YES'==$column->Null ? TRUE : FALSE);
		
	}
	
	/**
	 * Check whether the DB-table-field is capable to store empty strings.
	 *
	 * @param	string	DB-table-field name.
	 *
	 * @return	bool|NULL
	 */
	final public static function isEmptiable(string $fieldName): ?bool {
		
		$column = static::getColumnType($fieldName);
		
		if (is_null($column)) {
			return NULL;
		}
		
		$emptiables = ['CHAR','VARCHAR','TINYTEXT','TEXT','MEDIUMTEXT','BIGTEXT'];
		
		if (in_array($column->type, $emptiables) or ('ENUM' == $column->type and in_array('', $column->length))) {
			return TRUE;
		} else {
			return FALSE;
		}
		
		
	}

	/**
	 * Check whether record of this object is deletable based on inverse foreign-key list.
	 *
	 * @return	bool
	 */
	public function isDeletable(): bool {
		
		$inverseForeignKeys = $this->db->getInverseForeignKeys(static::TABLE_NAME);
		
		foreach ($inverseForeignKeys as $r) {
			
			// only if restrict it could be not deletable
			if ('RESTRICT' != $r->DELETE_RULE) continue;
			
			// get the property name
			$propertyName = $this->getMappedProperty($r->REFERENCED_COLUMN_NAME);
			
			return !$this->checkRecordExists($r->TABLE_NAME, $r->COLUMN_NAME, $this->$propertyName);
			
		}
		
		// nothing found, is deletable
		return TRUE;
		
	}
	
	/**
	 * Check if a record with column=value exists.
	 *
	 * @param	string	Table name.
	 * @param	string	Column name.
	 * @param	mixed	Value to search.
	 *
	 * @return	bool
	 */
	private function checkRecordExists(string $table, string $column, $value): bool {
		
		if (!$value) {
			return FALSE;
		}
		
		// build the query
		$query = 'SELECT COUNT(1) FROM `' . $table . '` WHERE ' . $column . ' = ?';
		
		// search the record into the db
		return (bool)Database::load($query, (array) $value, PAIR_DB_COUNT);
		
	}
	
	/**
	 * Return the property PHP type (bool, DateTime, float, int, string, csv and json).
	 * 
	 * @return string|NULL
	 */
	final public function getPropertyType(string $name): ?string {

		if (in_array($name, ['db', 'loadedFromDb', 'typeList', 'errors'])) {
			$type = NULL;
		} else if (array_key_exists($name, $this->typeList)) {
			$type = $this->typeList[$name];
		} else {
			$type = 'string';
		}

		return $type;
	
	}
	
	/**
	 * This method will populates a Datetime property with strings or DateTime obj. It
	 * will also sets time zone for all created datetimes with daylight saving value.
	 * Integer timestamps are only managed as UTC.
	 *  
	 * @param	string	Property’s name.
	 * @param	mixed	Property’s value.
	 */
	final private function setDatetimeProperty(string $propertyName, $value) {
		
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
			
			if (in_array($value, ['0000-00-00 00:00:00','0000-00-00',''])) {

				$this->$propertyName = NULL;

			}  else {

				// acquired as current user timezone
				try {
					$this->$propertyName = new \DateTime($value, $dtz);
				} catch (\Exception $e) {
					$this->$propertyName = NULL;
					$this->addError($e->getMessage());
				}
				
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
	final public function hasChanged(): bool {

		$class = get_called_class();
		$varFields = $class::getBinds();

		// create a new similar object that populates properly
		$newObj = new $class($this->{$this->keyProperties});
		
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
	final public function existsInDb(): bool {

		$class = get_called_class();
		$conds = implode(' AND ', $this->getSqlKeyConditions());

		$this->db->setQuery('SELECT COUNT(1) FROM `' . $class::TABLE_NAME . '` WHERE ' . $conds);
		
		return (bool)$this->db->loadCount($this->getId());
	
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
	 * Return last insert record object for single, auto-increment primary key.
	 *
	 * @return	NULL|Mixed
	 */
	public static function getLast() {
		
		$class = get_called_class();

		// check about single primary key
		if ($class::hasCompoundKey()) {
			return NULL;
		}
		
		// check if auto-increment key
		$db = Database::getInstance();
		if (!$db->isAutoIncrement($class::TABLE_NAME)) {
			return NULL;
		}
		
		// cast to string
		$tableKey = (is_array($class::TABLE_KEY) and array_key_exists(0, $class::TABLE_KEY)) ? $class::TABLE_KEY[0] : $class::TABLE_KEY;

		$db->setQuery('SELECT * FROM `' . $class::TABLE_NAME . '` ORDER BY `' . $tableKey . '` DESC LIMIT 1');
		$obj = $db->loadObject();

		return (is_a($obj, 'stdClass') ? new $class($obj) : NULL);
		
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
		$orderBy	= (array)$orderBy;
		
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
					if (!$direction or !in_array(strtolower($direction), array('asc','desc'))) {
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
	
		$objects = array();

		if (is_array($list)) {

			// builds each object
			foreach ($list as $row) {
				$object = new $class($row);
				$object->loadedFromDb = TRUE;
				$objects[] = $object;
			}
			
		}
		
		$app->logEvent('Loaded ' . count($objects) . ' ' . $class . ' objects' . $whereLog);
		
		return $objects;
	
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
						
					trigger_error('In method ' . $class . '::countAllObjects() unexistent property “' . $property . '” can’t be used as filter');
						
				}
					
			}
				
			// log message
			$whereLog .= count($conds) ? ' under condition ' . implode(' AND ', $conds) : '';
				
			// builds where
			$where = count($conds) ? ' WHERE ' . implode(' AND ', $conds) : '';
	
		}

		// runs query
		$db->setQuery('SELECT COUNT(1) FROM `' . $class::TABLE_NAME . '`' . $where);
		$count = $db->loadCount();
	
		$app->logEvent('Counted ' . $count . ' ' . $class . ' objects' . $whereLog);
	
		return $count;
	
	}
	
	/**
	 * Get one object of inherited class as result of the query run.
	 *
	 * @param	string	Query to run.
	 * @param	array	Optional bind parameters for query.
	 *
	 * @return	mixed|NULL
	 */
	final public static function getObjectByQuery(string $query, $params=[]): ?self {
		
		$app	= Application::getInstance();
		$db		= Database::getInstance();
		$class	= get_called_class();
		
		// run query
		$db->setQuery($query);
		$row = $db->loadObject($params);
		
		// initialize custom binds
		$customBinds = [];
		
		if (!is_a($row, 'stdClass')) {
			return NULL;
		}
			
		$binds = $class::getBinds();
		
		// get object properties from query
		$fields  = get_object_vars($row);
		
		// search for custom field names
		foreach ($fields as $field=>$value) {
			if (!array_search($field, $binds)) {
				$customBinds[Utilities::getCamelCase($field)] = $field;
			}
		}
		
		$object = new $class($row);
		
		// populate custom properties
		foreach ($customBinds as $customProp=>$customField) {
			$object->$customProp = $row->$customField;
		}
			
		// turn on loaded-from-db flag
		$object->loadedFromDb = TRUE;
			
		$app->logEvent('Loaded a ' . $class . ' object' . (count($customBinds) ? ' with custom fields ' . implode(',', $customBinds) : ''));
		
		return $object;
		
	}
	
	/**
	 * Get all objects of inherited class as result of the query run.
	 *
	 * @param	string	Query to run.
	 * @param	array	Optional bind parameters for query.
	 *
	 * @return	array:mixed
	 */
	final public static function getObjectsByQuery(string $query, $params=[]): ?array {
		
		$app	= Application::getInstance();
		$db		= Database::getInstance();
		$class	= get_called_class();

		// run query
		$db->setQuery($query);
		$list = $db->loadObjectList((array)$params);
		
		// array that returns and custom binds
		$objects = [];
		$customBinds = [];
		
		if (is_array($list) and isset($list[0])) {
			
			$binds = $class::getBinds();
			
			// get object properties from query
			$fields  = get_object_vars($list[0]);

			// search for custom field names
			foreach ($fields as $field=>$value) {
				if (!array_search($field, $binds)) {
					$customBinds[Utilities::getCamelCase($field)] = $field;
				}
			}

			// build each object
			foreach ($list as $row) {
				
				$object = new $class($row);

				// populate custom properties
				foreach ($customBinds as $customProp=>$customField) {
					$object->$customProp = $row->$customField;
				}
				
				// turn on loaded-from-db flag
				$object->loadedFromDb = TRUE;

				$objects[] = $object;
				
			}
			
		}
		
		$app->logEvent('Loaded ' . count($objects) . ' ' . $class . ' objects with custom fields ' . implode(',', $customBinds));
		
		return $objects;
		
	}
	
	/**
	 * Return TRUE if db record with passed primary or compound key exists. Faster method.
	 * 
	 * @param	mixed	Primary or compound key for this object table.
	 * 
	 * @return	bool
	 */
	final public static function exists($keys): bool {
		
		// initialize some vars
		$db			= Database::getInstance();
		$tableKey	= (array)static::TABLE_KEY;
		$conds		= array();

		foreach ($tableKey as $field) {
			$conds[] = $field . ' = ?';
		}
		
		$query = 'SELECT COUNT(1) FROM `' . static::TABLE_NAME . '` WHERE ' . implode(' AND ', $conds);
		
		// execute and return value
		return (bool)Database::load($query, (array)$keys, PAIR_DB_COUNT);
		
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
		
		// for guests, use default TimeZone
		if (is_a($app->currentUser, 'User')) {
			$dtz = $app->currentUser->getDateTimeZone();
		} else {
			$dtz = new \DateTimeZone(BASE_TIMEZONE);
		}
		
		$this->$prop->setTimeZone($dtz);

		// check if format is specified
		if (!$format) {

			$tran = Translator::getInstance();

			// if is set a locale date format, use it
			if ($tran->stringExists('LC_DATETIME_FORMAT')) {

				return strftime(Translator::do('LC_DATETIME_FORMAT'), $this->$prop->getTimestamp());

			// otherwise choose another format
			} else {
				
				$format = $tran->stringExists('DATETIME_FORMAT') ? Translator::do('DATETIME_FORMAT') : 'Y-m-d H:i:s';
				
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
		
		$app  = Application::getInstance();
		$tran = Translator::getInstance();
		
		// for guests, use default TimeZone
		if (is_a($app->currentUser, 'User')) {
			$dtz = $app->currentUser->getDateTimeZone();
		} else {
			$dtz = new \DateTimeZone(BASE_TIMEZONE);
		}
		
		$this->$prop->setTimeZone($dtz);

		// if is set a locale date format, use it
		if ($tran->stringExists('LC_DATE_FORMAT')) {

			return strftime(Translator::do('LC_DATE_FORMAT'), $this->$prop->getTimestamp());

		// otherwise choose another format
		} else {

			$format = $tran->stringExists('DATE_FORMAT') ? Translator::do('DATE_FORMAT') : 'Y-m-d';

			return $this->formatDateTime($prop, $format);

		}
	
	}

	/**
	 * Output an object property or method properly formatted and escaped.
	 * 
	 * @param	string	Property or method (with or without parentheses) name.
	 */
	final public function printHtml($name) {
		
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
					$printBoolean($this->$name);
					break;
					
				case 'DateTime':
					print $this->formatDateTime($this->$name);
					break;
					
				case 'csv':
					print htmlspecialchars(implode(', ', $this->$name));
					break;

				case 'json':
					print Utilities::varToText($this->$name);
					break;
					
				default:
					print htmlspecialchars($this->$name);
					break;
					
			}
			
		// name is a method with or without parentheses
		} else if ('()' == substr($name, -2) or method_exists($this, $name)) {
			
			$methodName = '()' == substr($name, -2) ? substr($name, 0, -2) : $name;
		
			if (!method_exists($this, $methodName)) {
				$this->addError('The ' . $methodName . '() method to printHtml was not found in the ' . get_called_class() . ' class');
				return;
			}
			
			// run the method
			$result = $this->$methodName();
			
			switch (gettype($result)) {
				
				case 'boolean':
					$printBoolean($result);
					break;

				case 'array':
					htmlspecialchars(implode(', ', $result));
					break;
					
				// integer, double, string, object, resource, NULL, unknown type
				default:
					print htmlspecialchars($result);
					break;
						
			}
			
		// property not found
		} else {
			
			$this->addError('You can not printHtml for the ' . $name . ' property of the class ' . get_called_class());
			
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
	 * Get the name of class property mapped by db field. NULL if not found.
	 * 
	 * @param	string	Field name.
	 * 
	 * @return	NULL|string
	 */
	final static public function getMappedProperty($fieldName) {
		
		$binds = static::getBinds();
		return in_array($fieldName, $binds) ? array_search($fieldName, $binds) : NULL;
		
	}
	
	/**
	 * Get the name of db field mapped by a class property. NULL if not found.
	 *
	 * @param	string	Property name.
	 * @return	NULL|string
	 */
	final static public function getMappedField(string $propertyName): ?string {
		
		$binds = static::getBinds();
		return isset($binds[$propertyName]) ? $binds[$propertyName] : NULL;
		
	}

	/**
	 * Load all records in a table from the DB and store them in the Application cache,
	 * then look for the required property in this list. It is very useful for repeated
	 * searches on small tables of the DB, eg. less than 1000 records.
	 * 
	 * @param	string	Property name.
	 * @param	mixed	Property value. If not unique property, return the first table item.
	 * @return	ActiveRecord|NULL
	 */
	final public static function getObjectByCachedList(string $property, $value): ?self {
		
		$app = Application::getInstance();
		$class = get_called_class();
		$cacheName = $class . 'ObjectList';
		
		if (!$app->issetState($cacheName)) {
			$app->setState($cacheName, $class::getAllObjects());
		}
		
		foreach ($app->getState($cacheName) as $object) {
			if ($object->$property == $value) {
				return $object;
			}
		}
		
		return NULL;
		
	}

	/**
	 * Populates the inherited object with input vars with same name as properties.
	 * 
	 * @param	string	Optional list of properties to populate, comma separated. If no items,
	 * 					will tries to populate all fields.
	 */
	final public function populateByRequest() {

		$args = func_get_args();

		// all subclass binds
		$binds = static::getBinds();

		foreach ($binds as $property => $field) {

			// check that property is in the args or that args is not defined at all
			if (!count($args) or (isset($args[0]) and in_array($property, $args[0]))) {
				
				$type = $this->getPropertyType($property);
				
				if (Input::isSent($property) or 'bool' == $type) {
					
					// assign the value to this object property
					$this->__set($property, Input::get($property));
					
				}

			}

		}

	}

	/**
	 * Generate a Form object with proper controls type already populated with object properties.
	 * 
	 * @return	Form
	 */
	public function getForm() {

		$form = new Form();
		
		// build a select control
		$getSelectControl = function ($property, $field, $values) use ($form) {
			
			$control = $form->addSelect($property)->setListByAssociativeArray($values, $values);
			
			if (static::isNullable($field) or static::isEmptiable($field)) {
				$control->prependEmpty();
			}
			
			return $control;
			
		};

		$properties = $this->getAllProperties();

		// these db column types will go into a textarea
		$textAreaTypes = ['tinytext', 'text', 'mediumtext', 'longtext'];

		foreach ($properties as $propName => $value) {

			$field = static::getMappedField($propName);

			// primary key
			if ($this->isKeyProperty($propName)) {

				$control = $form->addInput($propName)->setType('hidden');

			} else {
				
				$column = static::getColumnType($field);
				
				switch ($this->getPropertyType($propName)) {

					// checkbox
					case 'bool':
						$control = $form->addInput($propName)->setType('bool');
						break;
					
					// date or datetime
					case 'DateTime':
						$type = 'date' == $column->type ? 'date' : 'datetime'; 
						$control = $form->addInput($propName)->setType($type);
						break;

					// number with two decimals
					case 'float':
						$control = $form->addInput($propName)->setType('number')->setStep('0.01');
						break;

					// integer
					case 'int':
						$control = $form->addInput($propName)->setType('number');
						break;

					// multiple select
					case 'csv':
						$control = $getSelectControl($propName, $field, $column->length);
						$control->setMultiple();
						break;

					// textarea for json
					case 'json':
						$control = $form->addTextarea($propName);
						break;
					
					// select, textarea or text
					default:
						if ('enum' == $column->type) {
							$control = $getSelectControl($propName, $field, $column->length);
						} else if ('set' == $column->type) {
							$control = $getSelectControl($propName, $field, $column->length);
							$control->setMultiple();
						} else if (in_array($column->type, $textAreaTypes)) {
							$control = $form->addTextarea($propName);
						} else {
							$control = $form->addInput($propName);
							if (isset($column->length[0])) {
								$control->setMaxLength($column->length[0]);
							}
						}
						break;

				}

			}
			
			// check if is required
			if (!static::isNullable($field) and !static::isEmptiable($field)) {
				$control->setRequired();
			}

			// set the object value
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
			
		foreach ($this->keyProperties as $propertyName) {
			$ids[] = $this->{$propertyName};
		}
		
		return (static::hasCompoundKey() ? $ids : $ids[0]);
		
	}

	/**
	 * Function for serializing the object through json response.
	 *
	 * @return array
	 */
	public function jsonSerialize() {

		$vars = get_object_vars($this);
		unset($vars['keyProperties']);
		unset($vars['db']);
		unset($vars['loadedFromDb']);
		unset($vars['typeList']);
		unset($vars['cache']);
		unset($vars['errors']);

		return $vars;

	}

	/**
	 * Check wheter options crypt key has been defined into config.php file.
	 * 
	 * @return boolean
	 */
	public function isCryptAvailable() {
		
		return (defined('AES_CRYPT_KEY') and strlen(AES_CRYPT_KEY) > 0);

	}

	/**
	 * Return list of encryptable db-column names, if any.
	 * 
	 * @return	array
	 */
	private static function getEncryptableFields(): array {

		$encryptables = [];

		// list encryptables fields
		if (defined('static::ENCRYPTABLES') and
		 is_array(static::ENCRYPTABLES)) {
			foreach (static::ENCRYPTABLES as $property) {
				$encryptables[] = self::getMappedField($property);
			}
		}
		
		return $encryptables;
	
	}

	/**
	 * Return the query column list, in case there are encryptable fields or just *.
	 *
	 * @return	string
	 */
	public static function getQueryColumns(): string {

		$fields = '*';
		$db = Database::getInstance();
		$encryptables = static::getEncryptableFields();

		if ($encryptables) {

			$items = [];
			
			foreach ($encryptables as $e) {
				$items[] = 'AES_DECRYPT(`' . $e . '`,' . $db->quote(AES_CRYPT_KEY) . ') AS `' . $e . '`';
			}

			$fields .= ',' . implode(',',$items);

		}

		return $fields;
	
	}

}