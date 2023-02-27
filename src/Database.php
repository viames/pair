<?php

namespace Pair;

define ('PAIR_DB_OBJECT_LIST',	1);
define ('PAIR_DB_OBJECT',		2);
define ('PAIR_DB_RESULT_LIST',	3);
define ('PAIR_DB_RESULT',		4);
define ('PAIR_DB_COUNT',		5);

/**
 * Manages a PDO DB connection using the singleton pattern.
 */
class Database {

	/**
	 * Singleton object for database.
	 * @var Database|NULL
	 */
	protected static $instance = NULL;

	/**
	 * DB Handler.
	 * @var PDO
	 */
	private ?\PDO $handler = NULL;

	/**
	 * Temporary store for the SQL Query.
	 * @var string
	 */
	private ?string $query;

	/**
	 * Registered error list.
	 * @var array
	 */
	private array $errors = [];

	/**
	 * List of temporary table structures (describe, foreignKeys, inverseForeignKeys).
	 * @var array
	 */
	private array $definitions = [];

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Connects to db just the first time, returns singleton object everytime.
	 *
	 * @return	Database
	 *
	 * @throws	Exception
	 */
	public static function getInstance(): self {

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
	public function connectPersistent(): void {

		$this->openConnection(TRUE);

	}

	/**
	 * Proxy to open a connection to DBMS if current PDO handler is NULL.
	 *
	 * @throws	PDOException
	 */
	public function connect(): void {

		$this->openConnection(FALSE);

	}

	/**
	 * Connects to DBMS with params only if PDO handler property is null, so not connected.
	 *
	 * @param	bool	Flag to open a persistent connection (TRUE). Default is FALSE.
	 *
	 * @throws	PDOException
	 */
	private function openConnection(bool $persistent=FALSE): void {

		// continue only if not already connected
		if (is_a($this->handler, 'PDO')) {
			return;
		}

		$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME;
		$options = array(
			\PDO::ATTR_PERSISTENT			=> (bool)$persistent,
			\PDO::MYSQL_ATTR_INIT_COMMAND	=> "SET NAMES utf8",
			\PDO::MYSQL_ATTR_FOUND_ROWS		=> TRUE);

		try {

			$this->handler = new \PDO($dsn, DB_USER, DB_PASS, $options);

			if (is_a($this->handler, 'PDO')) {
				$this->handler->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			} else {
				throw new \PDOException('Db handler is not valid, connection failed');
			}

		} catch (\Exception $e) {

			exit($e->getMessage());

		}

	}

	/**
	 * Close PDO connection explicitly.
	 */
	public function disconnect(): void {

		unset($this->handler);

		Logger::event('Database is disconnected');

	}

	/**
	 * Set query for next result-set load.
	 *
	 * @param	string	SQL query.
	 */
	public function setQuery(string $query): void {

		$this->query = $query;

	}

	/**
	 * Executes a query and returns TRUE if success.
	 *
	 * @param	string		SQL Query da eseguire.
	 * @param	array|NULL	Parameters to bind on sql query in array or simple value.
	 * @return	int	Number of affected items.
	 */
	public function exec(string $query, $params=[]): int {

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
	public static function start(): void {

		static::run('START TRANSACTION');

	}

	/**
	 * Commits a transaction.
	 */
	public static function commit(): void {

		static::run('COMMIT');

	}

	/**
	 * Does the rollback of the transaction.
	 */
	public static function rollback(): void {

		static::run('ROLLBACK');

	}

	/**
	 * Quotes a string for use in a query.
	 *
	 * @param	string	String to quote.
	 * @return	string
	 */
	public function quote(string $text): string {

		$this->openConnection();

		return $this->handler->quote($text);

	}

	/**
	 * Wrap a column name in a couple of backticks.
	 *
	 * @param	string	The column name.
	 * @return	string
	 */
	public function escape(string $text): string {

		return '`' . $text . '`';

	}

	/**
	 * Return data in various formats by third string parameter. Default is PAIR_DB_OBJECT_LIST parameters
	 * as array. Support PDO parameters bind.
	 *
	 * @param	string		SQL query.
	 * @param	array|NULL	List of parameters to bind on the sql query.
	 * @param	int			Returned type (see constants PAIR_DB_*). PAIR_DB_OBJECT_LIST is default.
	 * @return	array|\stdClass|int|NULL
	 */
	public static function load(string $query, $params=[], int $option=NULL) {

		$self = static::getInstance();

		$self->openConnection();

		$res = NULL;

		try {

			// prepare query
			$stat = $self->handler->prepare($query);

			// bind parameters
			$stat->execute((array)$params);

			switch ($option) {

				// list of \stdClass objects
				default:
				case PAIR_DB_OBJECT_LIST:
					$res = $stat->fetchAll(\PDO::FETCH_OBJ);
					$count = count($res);
					break;

				// first row as \stdClass object
				case PAIR_DB_OBJECT:
					$res = $stat->fetch(\PDO::FETCH_OBJ);
					if (!$res) $res = NULL;
					$count = (bool)$res;
					break;

				// array of first column results
				case PAIR_DB_RESULT_LIST:
					$res = $stat->fetchAll(\PDO::FETCH_COLUMN);
					$count = count($res);
					break;

				// first column of first row
				case PAIR_DB_RESULT:
					$res = $stat->fetch(\PDO::FETCH_COLUMN);
					$count = $self->handler->query('SELECT FOUND_ROWS()')->fetchColumn();
					break;

				// result count as integer
				case PAIR_DB_COUNT:
					$res = (int)$stat->fetch(\PDO::FETCH_COLUMN);
					$count = $res;
					break;

			}

			$self->logParamQuery($query, $count, $params);

		} catch (\PDOException $e) {

			$self->handleException($e, $query, $params);

		}

		$stat->closeCursor();

		return $res;

	}

	/**
	 * Run a query with parameters and return TRUE if success. Support PDO parameters bind.
	 *
	 * @param	string		SQL query to run.
	 * @param	array|NULL	List of parameters to bind on the sql query.
	 * @return	int			Number of affected items.
	 */
	public static function run(string $query, $params=[]): int {

		$self = static::getInstance();

		$self->openConnection();

		$ret = NULL;

		try {

			// prepare query
			$stat = $self->handler->prepare($query);

			// bind parameters
			$stat->execute((array)$params);

			// count affected rows
			$affected = $stat->rowCount();

		} catch (\PDOException $e) {

			// logger
			$self->logParamQuery($query, 0, $params);

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

			$self->addError($message);

			$affected = 0;

		} catch (\Exception $e) {

			// logger
			$self->logParamQuery($query, 0, $params);
			$self->addError($e->getMessage());
			$affected = 0;

		}

		$stat->closeCursor();
		$self->logParamQuery($query, $affected, $params);

		return $affected;

	}

	/**
	 * Gets the first returned record from a previously setQuery(), otherwise NULL if record
	 * doesn’t exist.
	 *
	 * @param	array|NULL	Parameters to bind on sql query in array or simple value.
	 * @return	\stdClass|NULL
	 * @deprecated	Use Database::load($query, [], PAIR_DB_OBJECT) instead.
	 */
	public function loadObject($params=[]): ?\stdClass {

		$this->openConnection();

		$obj = NULL;

		try {

			$stat = $this->handler->prepare($this->query);
			$stat->execute((array)$params);
			$obj = $stat->fetch(\PDO::FETCH_OBJ);
			if (!$obj) $obj = NULL;

			// logger
			$this->logParamQuery($this->query, (int)(bool)$obj, $params);

		} catch (\PDOException $e) {

			$this->handleException($e, $this->query, $params);

		}

		$stat->closeCursor();

		return $obj;

	}

	/**
	 * Returns a recordset executing the query previously set with setQuery() method and
	 * optional parameters as array.
	 *
	 * @param	array|NULL	List of parameters to bind on sql query.
	 * @return	array
	 */
	public function loadObjectList($params=[]): ?array {

		$this->openConnection();

		$ret = NULL;

		try {

			$stat = $this->handler->prepare($this->query);
			$stat->execute((array)$params);
			$ret = $stat->fetchAll(\PDO::FETCH_OBJ);

			// logger
			$this->logParamQuery($this->query, count($ret), $params);

		} catch (\PDOException $e) {

			$this->handleException($e, $this->query, $params);

		}

		$stat->closeCursor();

		return $ret;

	}

	/**
	 * Returns array of first column value fetched from the resultset.
	 *
	 * @param	array|NULL	List of parameters to bind on sql query.
	 * @return	array|NULL
	 */
	public function loadResultList($params=[]): ?array {

		$this->openConnection();

		$ret = NULL;

		try {

			$stat = $this->handler->prepare($this->query);
			$stat->execute((array)$params);
			$ret = $stat->fetchAll(\PDO::FETCH_COLUMN);

			// logger
			$this->logParamQuery($this->query, count($ret), $params);

		} catch (\PDOException $e) {

			$this->handleException($e, $this->query, $params);

		}

		$stat->closeCursor();

		return $ret;

	}

	/**
	 * Returns first column value or NULL if row is not found.
	 *
	 * @param	array|NULL	List of parameters to bind on sql query.
	 * @return	string|NULL
	 */
	public function loadResult($params=[]): ?string {

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

			$this->handleException($e, $this->query, $params);

		}

		$stat->closeCursor();

		return $res;

	}

	/**
	 * Return the query count as integer number.
	 *
	 * @param	array|NULL	List of parameters to bind on sql query.
	 * @return	int
	 */
	public function loadCount($params=[]): int {

		$this->openConnection();

		$res = 0;

		try {

			$stat = $this->handler->prepare($this->query);
			$stat->execute((array)$params);
			$res = (int)$stat->fetch(\PDO::FETCH_COLUMN);

			// logger
			$this->logParamQuery($this->query, $res, $params);

		} catch (\PDOException $e) {

			$this->handleException($e, $this->query, $params);

		}

		$stat->closeCursor();

		return $res;

	}

	/**
	 * Inserts a new row in param table with all properties value as columns value.
	 *
	 * @param	string	Table name.
	 * @param	object	Object with property name as each column name.
	 * @param	array	Optional list of encryptable columns.
	 * @return	bool	TRUE if insert was succesfully done.
	 */
	public function insertObject(string $table, \stdClass $object, ?array $encryptables=[]): bool {

		$columns = [];
		$values  = [];

		// converts all object properties in vars SQL ready
		foreach (get_object_vars($object) as $column => $v) {

			// skip virtual generated columns
			if ($this->isVirtualGenerated($table, $column)) {
				continue;
			}

			if (is_null($v)) {
				$columns[] = '`' . $column . '`';
				$values[] = 'NULL';
			} else if (defined('AES_CRYPT_KEY') and in_array($column, $encryptables)) {
				$columns[] = '`' . $column . '`';
				$values[] = 'AES_ENCRYPT(' . $this->quote($v) . ',' . $this->quote(AES_CRYPT_KEY) . ')';
			} else if (is_string($v) or is_numeric($v)) {
				$columns[] = '`' . $column . '`';
				$values[] = $this->quote($v);
			}

		}

		$sql = 'INSERT INTO `' . $table . '` (%s) VALUES (%s)';
		$this->query = sprintf($sql, implode(',', $columns), implode(',', $values));

		$res = $this->exec($this->query);

		return (bool)$res;

	}

	/**
	 * Insert more than one row into param table.
	 *
	 * @param	string	Table name.
	 * @param	array	Object list, named as the table columns.
	 * @param	array	Optional list of encryptable columns.
	 * @return	int		Number of inserted rows.
	 */
	public function insertObjects(string $table, array $list, ?array $encryptables=[]): int {

		if (!is_array($list) or 0==count($list)) {
			return 0;
		}

		$records = [];
		$columns = [];

		foreach ($list as $object) {

			$values = [];

			foreach (get_object_vars($object) as $column => $v) {

				// skip virtual generated columns
				if ($this->isVirtualGenerated($table, $column)) {
					continue;
				}

				if (!count($records))	{
					$columns[] = '`' . $column . '`';
				}

				if (is_null($v)) {
					$values[] = 'NULL';
				} else if (defined('AES_CRYPT_KEY') and in_array($column, $encryptables)) {
					$values[] = 'AES_ENCRYPT(' . $this->quote($v) . ',' . $this->quote(AES_CRYPT_KEY) . ')';
				} else {
					$values[] = $this->quote($v);
				}

			}

			$records[] = '('. implode(',', $values) .')';

		}

		$sql = 'INSERT INTO `'. $table .'` (%s) VALUES %s';

		$this->query = sprintf($sql, implode(',', $columns), implode(',', $records));

		$res = $this->exec($this->query);

		return $res;

	}

	/**
	 * Update record of given key on the param object. Properly manage NULL values.
	 *
	 * @param	string		Table name.
	 * @param	\stdClass	Object with properties of new values to update.
	 * @param	\stdClass	Object with keys and values for where clause.
	 * @param	array		Optional list of encryptable columns.
	 * @return	int			Numbers of affected rows.
	 */
	public function updateObject(string $table, \stdClass &$object, \stdClass $key, ?array $encryptables=[]): int {

		$sets		= [];
		$where		= [];
		$columnVal	= [];
		$condVal	= [];

		// set table key and values on where conditions
		foreach (get_object_vars($key) as $column => $value) {

			$where[] = $column . ' = ?';
			$condVal[] = $value;

		}

		// set new row values
		foreach (get_object_vars($object) as $column => $value) {

			// skip virtual generated columns
			if ($this->isVirtualGenerated($table, $column)) {
				continue;
			}

			if (is_null($value)) {
				$sets[] = '`' . $column . '`=NULL';
			} else if (defined('AES_CRYPT_KEY') and in_array($column, $encryptables)) {
				$sets[] = '`' . $column . '`=AES_ENCRYPT(' . $this->quote($value) . ',' . $this->quote(AES_CRYPT_KEY) . ')';
			} else {
				$sets[] = '`' . $column . '`=?';
				$columnVal[] = $value;
			}

		}

		// create one list of values to bind
		$values = array_merge($columnVal, $condVal);

		if (count($sets) and count($where)) {

			// build the SQL query
			$query =
				'UPDATE `' . $table . '`' .
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
	 * Insert a row into a table or update it if present based on the Primary columns
	 * or Unique.
	 *
	 * @param	string		Table name.
	 * @param	\stdClass	Object with properties that equal columns name.
	 * @param	array		Optional list of encryptable columns.
	 * @return	bool		Execution result.
	 */
	public function insertUpdateObject(string $table, \stdClass $object, ?array $encryptables=[]): bool {

		$this->openConnection();

		$columns = [];
		$values  = [];
		$updates = [];

		foreach (get_object_vars($object) as $column => $v) {

			// skip virtual generated columns
			if ($this->isVirtualGenerated($table, $column)) {
				continue;
			}

			if (is_null($v)) {
				$columns[] = '`' . $column . '`';
				$values[]  = 'NULL';
			} else if (defined('AES_CRYPT_KEY') and in_array($column, $encryptables)) {
				$columns[] = '`' . $column . '`';
				$values[]  = 'AES_ENCRYPT(' . $this->quote($v) . ',' . $this->quote(AES_CRYPT_KEY) . ')';
			} else if (is_string($v) or is_numeric($v)) {
				$columns[] = '`' . $column . '`';
				$values[]  = $this->quote($v);
			}

			$updates[] = $v!==NULL ? $column.'='.$this->quote($v) : $column.'=NULL';

		}

		$sql = 'INSERT INTO `'. $table .'` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s';

		$query = sprintf($sql, implode(', ', $columns), implode(', ', $values), implode(', ', $updates));

		$res = static::run($query);

		return (bool)$res;

	}

	/**
	 * Returns the list of columns that are restricting a passed DB-table, an fk list.
	 * Require grant on “references” permissions of connected db-user. Memory cached.
	 *
	 * @param	string	Name of table to check.
	 * @return	\stdClass[]
	 */
	public function getForeignKeys(string $tableName): array {

		if (!isset($this->definitions[$tableName]['foreignKeys'])) {

			// old-style join because of speedness
			$query =
				'SELECT k.`CONSTRAINT_NAME`, k.`COLUMN_NAME`, k.`REFERENCED_TABLE_NAME`,' .
				' k.`REFERENCED_COLUMN_NAME`, r.`UPDATE_RULE`, r.`DELETE_RULE`' .
				' FROM information_schema.`KEY_COLUMN_USAGE` AS k' .
				' JOIN information_schema.`REFERENTIAL_CONSTRAINTS` AS r' .
				' WHERE k.`CONSTRAINT_NAME` != "PRIMARY"' .
				' AND k.`TABLE_SCHEMA` = ? AND k.`TABLE_NAME` = ?' .
				' AND r.`CONSTRAINT_SCHEMA` = ? AND r.`TABLE_NAME` = ?' .
				' AND k.`CONSTRAINT_NAME` = r.`CONSTRAINT_NAME`';

			$this->definitions[$tableName]['foreignKeys'] = self::load($query, [DB_NAME, $tableName, DB_NAME, $tableName]);

		}

		return $this->definitions[$tableName]['foreignKeys'];

	}

	/**
	 * Load and return the list of columns that are restricted by a passed DB-table, the inverse fk list.
	 * Require grant on “references” permissions of connected db-user. Memory cached.
	 *
	 * @param	string	Name of external table to check.
	 * @return	\stdClass[]
	 */
	public function getInverseForeignKeys(string $tableName): array {

		if (!isset($this->definitions[$tableName]['inverseForeignKeys'])) {

			// old-style join because of speedness
			$query =
				'SELECT k.CONSTRAINT_NAME, k.REFERENCED_COLUMN_NAME, k.TABLE_NAME, ' .
				' k.COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE' .
				' FROM information_schema.`KEY_COLUMN_USAGE` AS k' .
				' JOIN information_schema.`REFERENTIAL_CONSTRAINTS` AS r' .
				' WHERE k.CONSTRAINT_NAME != "PRIMARY"' .
				' AND k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME = ?' .
				' AND r.CONSTRAINT_SCHEMA = ? AND r.REFERENCED_TABLE_NAME = ?' .
				' AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME';

			$this->definitions[$tableName]['inverseForeignKeys'] = self::load($query, [DB_NAME, $tableName, DB_NAME, $tableName]);

		}

		return $this->definitions[$tableName]['inverseForeignKeys'];

	}

	/**
	 * Return data about table scheme. Memory cached.
	 *
	 * @param	string	Name of table to describe.
	 * @return	\stdClass[]
	 */
	public function describeTable(string $tableName): array {

		// check if was set in the object cache property
		if (!isset($this->definitions[$tableName]['describe'])) {

			$res = self::load('DESCRIBE `' . $tableName . '`', NULL);
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
	 * @return	\stdClass|NULL
	 */
	public function describeColumn(string $tableName, string $column): ?\stdClass {

		// search in cached table structure
		if (isset($this->definitions[$tableName]['describe'])) {
			foreach ($this->definitions[$tableName]['describe'] as $d) {
				if ($column == $d->Field) {
					return $d;
				}
			}
		}

		$res = self::load('DESCRIBE `' . $tableName . '` `' . $column . '`', NULL, PAIR_DB_OBJECT);

		return ($res ? $res : NULL);

	}

	/**
	 * Return an array of table-key names by using cached methods.
	 *
	 * @param	string	Name of table to which get the keys.
	 * @return	string[]
	 */
	public function getTableKeys(string $tableName): array {

		$keys = [];

		$columns = $this->describeTable($tableName);

		foreach ($columns as $column) {
			if ('PRI' == $column->Key) {
				$keys[] = $column->Field;
			}
		}

		return $keys;

	}

	/**
	 * Check if parameter table has auto-increment primary key by using cached method.
	 *
	 * @param	string	Name of table to check auto-increment flag.
	 * @return	bool
	 */
	public function isAutoIncrement(string $tableName): bool {

		$columns = $this->describeTable($tableName);

		foreach ($columns as $column) {
			if ('auto_increment' == $column->Extra) {
				return TRUE;
			}
		}

		return FALSE;

	}

	/**
	 * Checks if the indicated column is generated automatically and in this case returns TRUE
	 *
	 * @param	string	Name of table to check.
	 * @param	string	Name of the column in the table to check
	 * @return	bool
	 */
	public function isVirtualGenerated(string $tableName, string $columnName): bool {

		$columns = $this->describeTable($tableName);

		foreach ($columns as $column) {
			if ($column->Field == $columnName) {
				return ('VIRTUAL GENERATED' == $column->Extra);
			}
		}

		return FALSE;

	}

	/**
	 * Check wheter a table exists by its name.
	 *
	 * @param	string	Table name.
	 * @return	bool
	 */
	public function tableExists(string $tableName): bool {

		$this->setQuery('SHOW TABLES LIKE ?');
		return (bool)$this->loadResult([$tableName]);

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
	 * Return the MySQL version number.
	 *
	 * @return string|NULL
	 */
	public function getMysqlVersion(): ?string {

		$this->setQuery('SELECT VERSION()');
		return $this->loadResult();

	}

	/**
	 * Set MySQL connection as UTF8mb4 and collation as utf8mb4_unicode_ci, useful to
	 * support extended unicode like Emoji.
	 */
	public function setUtf8unicode() {

		$this->openConnection();

		try {

			// set names
			$this->handler->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

			// prepare query to discover db user privileges
			$stat = $this->handler->prepare('SELECT `PRIVILEGE_TYPE` FROM information_schema.user_privileges' .
				' WHERE `GRANTEE` = \'' . DB_USER . '\'@\'' . DB_HOST . '\'');

			// get user privileges
			$privilegeType = $stat->fetch(\PDO::FETCH_COLUMN);

			if (in_array($privilegeType, ['SUPER', 'SYSTEM_VARIABLES_ADMIN', 'SESSION_VARIABLES_ADMIN'])) {

				$this->handler->exec(
					'SET character_set_client = "utf8mb4", character_set_connection = "utf8mb4",' .
					' character_set_database = "utf8mb4", character_set_results = "utf8mb4",' .
					' character_set_server = "utf8mb4", collation_connection = "utf8mb4_unicode_ci",' .
					' collation_database = "utf8mb4_unicode_ci", collation_server = "utf8mb4_unicode_ci"');

			}

		} catch (\Exception $e) {

			// cannot log right now

		}

	}

	/**
	 * Add error to list.
	 *
	 * @param	string	Text error message.
	 */
	public function addError(string $message) {

		trigger_error($message);
		$this->errors[] = $message;

	}

	/**
	 * Returns text of latest error message, or FALSE if not errors.
	 *
	 * @return	string|bool
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
	private function logQuery(string $query, int $result) {

		$subtext = (int)$result . ' ' . (1==$result ? 'row' : 'rows');

		Logger::event($query, 'query', $subtext);

	}

	/**
	 * Proxy for logQuery() that binds query parameters.
	 *
	 * @param	string		SQL query.
	 * @param	int			Number of items in result-set or affected rows.
	 * @param	array|NULL	Parameters to bind.
	 */
	private function logParamQuery(string $query, int $result, $params=[]) {

		$params = (array)$params;

		// indexed is binding with "?"
		$indexed = $params==array_values($params);

		foreach ($params as $column=>$v) {

			if (is_string($v)) $v="'$v'";

			$query = $indexed ? preg_replace('/\?/', (string)$v, $query, 1) : str_replace($column, $v, $query);

		}

		$this->logQuery($query, $result);

	}

	/**
	 * Log query, switch error and add to DB class error list.
	 *
	 * @param	Exception	Error object.
	 * @param	string		SQL Query.
	 * @param	array|NULL	Parameters.
	 */
	private function handleException(\Exception $e, string $query, ?array $params) {

		$params = (array)$params;

		// logger
		$this->logParamQuery($query, 0, $params);

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
