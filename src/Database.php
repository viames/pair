<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

/**
 * Manages a PDO DB connection using the singleton pattern.
 */
class Database {

	/**
	 * Application object.
	 * @var Application
	 */
	private $app;
	
	/**
	 * Singleton object for database.
	 * @var Database|NULL
	 */
	protected static $instance = NULL;

	/**
	 * DB Handler.
	 * @var PDO
	 */
	private $handler;
	
	/**
	 * Temporary store for the SQL Query.
	 * @var string
	 */
	private $query;

	/**
	 * Registered error list.
	 * @var array
	 */
	private $errors = array();
	
	/**
	 * Private constructor.
	 */
	private function __construct() {}
		
	/**
	 * List of temporary table structures (describe, foreignKeys, inverseForeignKeys). 
	 * @var array
	 */
	private $definitions = [];
	
	/**
	 * Connects to db just the first time, returns singleton object everytime.
	 * 
	 * @return	Database
	 * 
	 * @throws	Exception
	 */
	public static function getInstance() {

		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		
		return self::$instance;
		
	}

	/**
	 * Proxy to open a persistent connection to DBMS if current PDO handler is NULL.
	 *
	 * @throws	PDOException
	 */
	public function connectPersistent() {

		$this->openConnection(TRUE);

	}

	/**
	 * Proxy to open a connection to DBMS if current PDO handler is NULL.
	 *
	 * @throws	PDOException
	 */
	public function connect() {

		$this->openConnection(FALSE);
		
	}
	
	/**
	 * Connects to DBMS with params only if PDO handler property is null, so not connected.
	 * 
	 * @param	bool	Flag to open a persistent connection (TRUE). Default is FALSE.
	 * 
	 * @throws	PDOException
	 */
	private function openConnection($persistent=FALSE) {

		// continue only if not already connected
		if (is_a($this->handler, 'PDO')) {
			return;
		}
		
		switch (DBMS) {
			
			default:
			case 'mysql':
				$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME;
				$options = array(
					\PDO::ATTR_PERSISTENT			=> (bool)$persistent,
					\PDO::MYSQL_ATTR_INIT_COMMAND	=> "SET NAMES utf8",
					\PDO::MYSQL_ATTR_FOUND_ROWS		=> TRUE);
				break;

			case 'mssql':
				$dsn = 'dblib:host=' . DB_HOST . ';dbname=' . DB_NAME;
				$options = [];
				break;
		
		}
		
		try {
				
			$this->handler = new \PDO($dsn, DB_USER, DB_PASS, $options);
				
			if (is_a($this->handler, 'PDO')) {
				$this->handler->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			} else {
				throw new \PDOException('Db handler is not valid, connection failed');
			}
			
		} catch (\Exception $e) {

			exit();
			
		}
		
	}
	
	/**
	 * Close PDO connection explicitly.
	 */
	public function disconnect() {
		
		unset($this->handler);
		
		$logger = Logger::getInstance();
		$logger->addEvent('Database is disconnected');
		
	}
	
	/**
	 * Set query for next result-set load.
	 * 
	 * @param	string	SQL query.
	 */
	public function setQuery($query) {
		
		$this->query = $query;
		
	}
	
	/**
	 * Executes a query and returns TRUE if success.
	 * 
	 * @param	string	SQL Query da eseguire.
	 * @param	mixed	Parameters to bind on sql query in array or simple value.
	 * 
	 * @return	mixed	Number of affected items.
	 */
	public function exec($query, $params=array()) {

		$this->openConnection();
		
		$this->query = $query;
		
		try {

			$stat = $this->handler->prepare($this->query);
			$stat->execute((array)$params);
			$affected = $stat->rowCount();

		} catch (\PDOException $e) {

			// logger
			$this->logParamQuery($this->query, 0, $params);

			switch ($e->getCode()) {
				
				// integrity constraint violation
				/*
				case '23000':
					$message = 'Database integrity constraint violation';
					break;
				*/
				default:
					$message = $e->getMessage();
					break;
			}
			
			$this->addError($message);
				
			$affected = 0;
		
		} catch (\Exception $e) {
			
			// logger
			$this->logParamQuery($this->query, 0, $params);
			$this->addError($e->getMessage());
			$affected = 0;
		
		}
		
		$stat->closeCursor();
		$this->logParamQuery($this->query, $affected, $params);
		
		return $affected;
		
	}

	/**
	 * Starts a transaction.
	 */
	public function start() {
		
		$this->exec('START TRANSACTION');
		
	}

	/**
	 * Commits a transaction.
	 */
	public function commit() {
		
		$this->exec('COMMIT');
		
	}

	/**
	 * Quotes a string for use in a query.
	 * 
	 * @param	string	String to quote.
	 * 
	 * @return	string
	 */
	public function quote($text) {
		
		$this->openConnection();
		
		return $this->handler->quote($text);
		
	}
	
	/**
	 * Wrap a field name in a couple of backticks.
	 *  
	 * @param	string	The field name.
	 * 
	 * @return string
	 */
	public function escape($text) {
		
		return '`' . $text . '`';
		
	}
	
	/**
	 * Gets the first returned record from a previously setQuery(), otherwise NULL if record
	 * doesn’t exist.
	 * 
	 * @param	mixed	Parameters to bind on sql query in array or simple value.
	 * 
	 * @return	object|NULL
	 */
	public function loadObject($params=array()) {

		$this->openConnection();
		
		$obj = NULL;
		
		try {
			
			$stat = $this->handler->prepare($this->query);
			$stat->execute((array)$params);
			$obj = $stat->fetch(\PDO::FETCH_OBJ);
			
			// logger
			$this->logParamQuery($this->query, (bool)$obj, $params);

		} catch (\PDOException $e) {

			$this->handleException($e, $params);
		
		}
		
		$stat->closeCursor();

		return $obj;
		
	} 
	
	/**
	 * Returns a recordset executing the query previously set with setQuery() method and
	 * optional parameters as array. 
	 * 
	 * @param	array	List of parameters to bind on sql query.
	 * @return	array
	 */
	public function loadObjectList($params=array()) {

		$this->openConnection();
		
		$ret = NULL;
		
		try {

			$stat = $this->handler->prepare($this->query);
			$stat->execute((array)$params);
			$ret = $stat->fetchAll(\PDO::FETCH_OBJ);
			
			// logger
			$this->logParamQuery($this->query, count($ret), $params);

		} catch (\PDOException $e) {

			$this->handleException($e, $params);
		
		}
		
		$stat->closeCursor();

		return $ret;
		
	}

	/**
	 * Returns array of first field value fetched from the resultset.
	 * 
	 * @param	array	List of parameters to bind on sql query.
	 * @return	array
	 */
	public function loadResultList($params=array()) {

		$this->openConnection();
		
		$ret = NULL;
		
		try {
			
			$stat = $this->handler->prepare($this->query);
			$stat->execute((array)$params);
			$ret = $stat->fetchAll(\PDO::FETCH_COLUMN);

			// logger
			$this->logParamQuery($this->query, count($ret), $params);
			
		} catch (\PDOException $e) {

			$this->handleException($e, $params);
		
		}
		
		$stat->closeCursor();
		
		return $ret;
		
	}
		
	/**
	 * Returns first field value or NULL if row is not found.
	 * 
	 * @param	mixed	List of parameters to bind on sql query.
	 * 
	 * @return	multitype|NULL
	 */
	public function loadResult($params=array()) {
		
		$this->openConnection();
		
		$res = NULL;

		try {

			$stat = $this->handler->prepare($this->query);
			$stat->execute((array)$params);
			
			// logger
			$count = $this->handler->query('SELECT FOUND_ROWS()')->fetchColumn();
			$this->logParamQuery($this->query, $count, $params);
			$res = $stat->fetch(\PDO::FETCH_COLUMN);	
		
		} catch (\PDOException $e) {

			$this->handleException($e, $params);

		}
		
		$stat->closeCursor();

		return $res;
		
	}

	/**
	 * Return the query count as integer number.
	 *
	 * @param	mixed	List of parameters to bind on sql query.
	 *
	 * @return	int
	 */
	public function loadCount($params=array()) {
		
		$this->openConnection();
		
		$res = 0;
		
		try {
		
			$stat = $this->handler->prepare($this->query);
			$stat->execute((array)$params);

			// logger
			$res = (int)$stat->fetch(\PDO::FETCH_COLUMN);
			$this->logParamQuery($this->query, $res, $params);
		
		} catch (\PDOException $e) {
		
			$this->handleException($e, $params);
		
		}
		
		$stat->closeCursor();

		return $res;
		
	}
	
	/**
	 * Inserts a new row in param table with all properties value as fields value.
	 *
	 * @param	string	Table name.
	 * @param	object	Object with property name as each field name.
	 * 
	 * @return	bool	TRUE if insert was succesfully done.
	 */
	public function insertObject($table, $object) {

		$fields = array();
		$values = array();

		// converts all object properties in vars SQL ready
		foreach (get_object_vars($object) as $k => $v) {
			if (is_string($v) or is_numeric($v)) {
				$fields[] = '`' . $k . '`';
				$values[] = $this->quote($v);
			} else if (is_null($v)) {
				$fields[] = '`' . $k . '`';
				$values[] = 'NULL';
			}
		}

		$sql = 'INSERT INTO '. $table .' (%s) VALUES (%s)';
		$this->query = sprintf($sql, implode(', ', $fields), implode(', ', $values));
		
		$res = $this->exec($this->query);
		
		return (bool)$res;
		
	}
	
	/**
	 * Insert more than one row into param table.
	 * 
	 * @param	string	Table name.
	 * @param	array	Object list, named as the table fields.
	 * 
	 * @return	integer	Number of inserted rows.
	 */
	public function insertObjects($table, $list) {
		
		if (!is_array($list) or 0==count($list)) {
			return 0;
		}
		
		$records = array();
		$fields  = array();
		
		foreach ($list as $object) {

			$values = array();
		
			foreach (get_object_vars($object) as $k => $v) {
				
				if (!count($records))	{
					$fields[] = '`' . $k . '`';
				}
				
				$values[] = is_null($v) ? 'NULL' : $this->quote($v);

			}

			$records[] = '('. implode(',', $values) .')';
			
		}
		
		$sql = 'INSERT INTO '. $table .' (%s) VALUES %s';

		$this->query = sprintf($sql, implode(',', $fields), implode(',', $records));

		$res = $this->exec($this->query);

		return $res;
		
	}

	/**
	 * Update record of given key on the param object. Properly manage NULL values.
	 * 
	 * @param	string		DB Table.
	 * @param	stdClass	Object with properties of new values to update.
	 * @param	array		Object with keys and values for where clause.
	 * 
	 * @return	int			Numbers of affected rows.
	 */
	public function updateObject($table, &$object, $key) {

		$sets		= array();
		$where		= array();
		$fieldVal	= array();
		$condVal	= array();

		// set table key and values on where conditions
		foreach (get_object_vars($key) as $field => $value) {

			$where[] = $field . ' = ?';
			$condVal[] = $value;

		}

		// set new row values
		foreach (get_object_vars($object) as $field => $value) {
				
			if (is_null($value)) {
				$sets[] = '`' . $field . '` = NULL';
			} else {
				$sets[] = '`' . $field . '` = ?';
				$fieldVal[] = $value;
			}

		}

		// create one list of values to bind
		$values = array_merge($fieldVal, $condVal);

		if (count($sets) and count($where)) {

			// build the SQL query
			$query =
				'UPDATE ' . $table .
				' SET ' . implode(', ', $sets) .
				' WHERE ' . implode(' AND ', $where);		

			// execute the SQL query
			$res = $this->exec($query, $values);

			} else {
			
			$res = 0;
			
		}
		
		return $res;
		
	}
	
	/**
	 * Inserisce una riga in una tabella o l’aggiorna se presente in base ai campi Primary
	 * oppure Unique.
	 *
	 * @param	string	Nome tabella.
	 * @param	object	Oggetto con le proprietà corrispondenti ai nomi dei campi.
	 * @return	bool	Esito della modifica.
	 */
	public function insertUpdateObject($table, $object) {
	
		$this->openConnection();
		
		$fields  = array();
		$values  = array();
		$updates = array();
	
		foreach (get_object_vars($object) as $k => $v) {
			if (is_string($v) or is_numeric($v)) {
				$values[]  = $this->quote($v);
			} else if (is_null($v)) {
				$values[] = 'NULL';
			}
			$fields[] = '`' . $k . '`';
			$updates[] = $v!==NULL ? $k.'='.$this->quote($v) : $k.'=NULL';
		}
	
		$sql = 'INSERT INTO '. $table .' (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s';
		
		$this->query = sprintf($sql, implode(', ', $fields), implode(', ', $values), implode(', ', $updates));
		
		$res = $this->exec($this->query);
	
		return (bool)$res;
	
	}
	
	/**
	 * Returns the list of columns that are restricting a passed DB-table, an fk list.
	 * Require grant on “references” permissions of connected db-user.
	 *
	 * @param	string	Name of table to check.
	 *
	 * @return	stdClass[]
	 */
	public function getForeignKeys($tableName) {

		if (!isset($this->definitions[$tableName]['foreignKeys'])) {
			
			// old-style join because of speedness
			$query =
				'SELECT k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME,' .
				' k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE' .
				' FROM information_schema.KEY_COLUMN_USAGE AS k' .
				' JOIN information_schema.REFERENTIAL_CONSTRAINTS AS r' .
				' WHERE k.CONSTRAINT_NAME != "PRIMARY"' .
				' AND k.TABLE_SCHEMA = ? AND k.TABLE_NAME = ?' .
				' AND r.CONSTRAINT_SCHEMA = ? AND r.TABLE_NAME = ?' .
				' AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME';
			
			$this->setQuery($query);
			$this->definitions[$tableName]['foreignKeys'] = $this->loadObjectList([DB_NAME, $tableName, DB_NAME, $tableName]);

		}
		
		return $this->definitions[$tableName]['foreignKeys'];
		
	}
	
	/**
	 * Load and return the list of columns that are restricted by a passed DB-table, the inverse fk list.
	 * Require grant on “references” permissions of connected db-user. Memory cached.
	 * 
	 * @param	string	Name of external table to check.
	 * 
	 * @return	stdClass[]
	 */
	public function getInverseForeignKeys($tableName) {
		
		if (!isset($this->definitions[$tableName]['inverseForeignKeys'])) {
			
			// old-style join because of speedness
			$query =
				'SELECT k.CONSTRAINT_NAME, k.REFERENCED_COLUMN_NAME, k.TABLE_NAME, ' .
				' k.COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE' .
				' FROM information_schema.KEY_COLUMN_USAGE AS k' .
				' JOIN information_schema.REFERENTIAL_CONSTRAINTS AS r' .
				' WHERE k.CONSTRAINT_NAME != "PRIMARY"' .
				' AND k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME = ?' .
				' AND r.CONSTRAINT_SCHEMA = ? AND r.REFERENCED_TABLE_NAME = ?' .
				' AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME';

			$this->setQuery($query);
			$this->definitions[$tableName]['inverseForeignKeys'] = $this->loadObjectList([DB_NAME, $tableName, DB_NAME, $tableName]);
			
		}
		
		return $this->definitions[$tableName]['inverseForeignKeys'];

	}
	
	/**
	 * Return data about table scheme. Memory cached.
	 * 
	 * @param	string	Name of table to describe.
	 * 
	 * @return	stdClass[]
	 */
	public function describeTable($tableName): array {
	
		if (!isset($this->definitions[$tableName]['describe'])) {
			
			$this->setQuery('DESCRIBE `' . $tableName . '`');
			$res = $this->loadObjectList();
			$this->definitions[$tableName]['describe'] = is_null($res) ? [] : $res;
			
		}
		
		return $this->definitions[$tableName]['describe'];
		
	}
	
	/**
	 * Return data about a column scheme trying to load table description records by object cache.
	 * FALSE in case of unvalid column name.
	 *
	 * @param	string	Name of table to describe.
	 * @param	string	Column name.
	 *
	 * @return	stdClass|NULL
	 */
	public function describeColumn($tableName, $column) {
		
		// search in cached table structure
		if (isset($this->definitions[$tableName]['describe'])) {
			foreach ($this->definitions[$tableName]['describe'] as $d) {
				if ($column == $d->Field) {
					return $d;
				}
			}
		}
		
		$this->setQuery('DESCRIBE `' . $tableName . '` `' . $column . '`' );
		$res = $this->loadObject();
		
		return ($res ? $res : NULL);
		
	}
	
	/**
	 * Check wheter a table exists by its name.
	 *
	 * @param	string	Table name.
	 *
	 * @return	boolean
	 */
	public function tableExists(string $tableName): bool {
		
		$this->setQuery('SHOW TABLES LIKE ?');
		return (bool)$this->loadResult($tableName);
		
	}
	
	/**
	 * Returns last inserted ID, if any.
	 * 
	 * @return	mixed
	 */
	public function getLastInsertId() {

		$this->openConnection();
		
		return $this->handler->lastInsertId();
		
	}

	/**
	 * Return the MySQL version number
	 */
	public function getMysqlVersion() {
		
		if ('mysql' == DBMS) {
			$this->setQuery('SELECT VERSION()');
			return $this->loadResult();
		}

		return NULL;
		
	}

	/**
	 * Set MySQL connection as UTF8mb4 and collation as utf8mb4_unicode_ci, useful to
	 * support extended unicode like Emoji.
	 */
	public function setUtf8unicode() {
	
		$this->openConnection();
		
		try {
			
			$this->handler->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
	
			$this->handler->exec(
				'SET character_set_client = "utf8mb4", character_set_connection = "utf8mb4",' . 
				' character_set_database = "utf8mb4", character_set_results = "utf8mb4",' .
				' character_set_server = "utf8mb4", collation_connection = "utf8mb4_unicode_ci",' .
				' collation_database = "utf8mb4_unicode_ci", collation_server = "utf8mb4_unicode_ci"');
		
		} catch (\Exception $e) {
			
			// cannot log right now
			
		}
			
		
	}

	/**
	 * Add error to list.
	 *
	 * @param	string	Text error message.
	 */
	public function addError($message) {
		
		trigger_error($message);
		$this->errors[] = $message;
	
	}
	
	/**
	 * Returns text of latest error message, or FALSE if not errors.
	 *
	 * @return	string|NULL
	 */
	public function getLastError() {
	
		return end($this->errors);
	
	}
	
	/**
	 * Adds an entry item on system log.
	 * 
	 * @param	string	SQL query.
	 * @param	int		Number of items in result-set or affected rows.
	 */
	private function logQuery($query, $result) {
		
		$subtext = (int)$result . ' ' . (1==$result ? 'row' : 'rows');

		$logger = Logger::getInstance();
		$logger->addEvent($query, 'query', $subtext);
		
	}
	
	/**
	 * Proxy for logQuery() that binds query parameters.
	 * 
	 * @param	string	SQL query.
	 * @param	int		Number of items in result-set or affected rows.
	 * @param	array	Parameters to bind.
	 */
	private function logParamQuery($query, $result, $params) {
		
		$params = (array)$params;

		// indexed is binding with "?" 
		$indexed = $params==array_values($params);

		foreach ($params as $k=>$v) {
			
			if (is_string($v)) $v="'$v'";
			
			$query = $indexed ? preg_replace('/\?/', $v, $query, 1) : str_replace(":$k", $v, $query);
		
		}
		
		$this->logQuery($query, $result);
		
	}
	
	/**
	 * Log query, switch error and add to DB class error list.
	 *  
	 * @param	stdClass	Error object.
	 * @param	array		Parameters.
	 */
	private function handleException($e, $params) {
		
		// logger
		$this->logParamQuery($this->query, 0, $params);
		
		switch ($e->getCode()) {
		
			// calls with wrong params type or count
			case 'HY093':
				if (is_array($params)) {
					$message = 'Parameters count is ' . count($params) . ', an array with different number is expected by function call';
				} else {
					$message = 'Parameters are expected in array format by function call, type ' . gettype($params) . ' was passed';
				}
				break;
		
			default:
				$message = $e->getMessage();
				break;
		
		}
		
		$this->addError($message);
		
	}
	
}
