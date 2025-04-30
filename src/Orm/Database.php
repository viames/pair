<?php

namespace Pair\Orm;

use Pair\Core\Env;
use Pair\Core\Logger;
use Pair\Exceptions\CriticalException;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Manages a PDO DB connection using the singleton pattern.
 */
class Database {

	/**
	 * Singleton object for database.
	 */
	protected static ?self $instance = NULL;

	/**
	 * DB Handler.
	 */
	private ?\PDO $handler = NULL;

	/**
	 * Temporary store for the SQL Query.
	 */
	private ?string $query;

	/**
	 * List of temporary table structures (describe, foreignKeys, inverseForeignKeys).
	 */
	private array $definitions = [];

	/**
	 * Constant for array of objects return of the load() method.
	 */
	const OBJECT_LIST = 1;

	/**
	 * Costant for single object return of the load() method.
	 */
	const OBJECT = 2;

	/**
	 * Constant for array of results return of the load() method.
	 */
	const RESULT_LIST = 3;

	/**
	 * Constant for single result return of the load() method.
	 */
	const RESULT = 4;

	/**
	 * Constant for count of results return of the load() method.
	 */
	const COUNT = 5;

	/**
	 * Constant for dictionary return of the load() method.
	 */
	const DICTIONARY = 6;

	/**
	 * Constant for Collection return of the load() method.
	 */
	const COLLECTION = 7;

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Commits a transaction.
	 */
	public static function commit(): void {

		static::run('COMMIT');

	}

	private function castParams(&$params): void {

		foreach ($params as $key=>$value) {
			if (is_bool($value)) {
				$params[$key] = $value ? 1 : 0;
			} else if (is_null($value)) {
				$params[$key] = NULL;
			} else if (is_a($value, \DateTime::class)) {
				$params[$key] = $value->format('Y-m-d H:i:s');
			}
		}

	}

	/**
	 * Proxy to open a connection to DBMS if current PDO handler is NULL.
	 *
	 * @throws	PairException
	 */
	public function connect(): void {

		$this->openConnection(FALSE);

	}

	/**
	 * Proxy to open a persistent connection to DBMS if current PDO handler is NULL.
	 *
	 * @throws	PairException
	 */
	public function connectPersistent(): void {

		$this->openConnection(TRUE);

	}

	/**
	 * Return data about a column scheme trying to load table description records by object cache.
	 * FALSE in case of unvalid column name.
	 *
	 * @param	string	Name of table to describe.
	 * @param	string	Column name.
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

		$res = self::load('DESCRIBE `' . $tableName . '` `' . $column . '`', [], Database::OBJECT);

		return ($res ? $res : NULL);

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

			$res = self::load('DESCRIBE `' . $tableName . '`');
			$this->definitions[$tableName]['describe'] = is_null($res) ? [] : $res;

		}

		return $this->definitions[$tableName]['describe'];

	}

	/**
	 * Close PDO connection explicitly.
	 */
	public function disconnect(): void {

		unset($this->handler);

		Logger::notice('Database connection closed', Logger::DEBUG);

	}

	/**
	 * Wrap a column name in a couple of backticks.
	 *
	 * @param	string	The column name.
	 */
	public function escape(string $text): string {

		return '`' . $text . '`';

	}

	/**
	 * Executes a query and returns TRUE if success.
	 *
	 * @param	string		SQL Query da eseguire.
	 * @param	array|NULL	Parameters to bind on sql query in array or simple value.
	 * @return	int			Number of affected items.
	 * @throws	PairException
	 */
	public function exec(string $query, array $params=[]): int {

		$this->openConnection();

		$this->query = $query;
		$stat = $this->handler->prepare($this->query);

		try {
			$stat->execute($params);
		} catch (\PDOException $e) {
			throw new PairException($e->getMessage(), ErrorCodes::DB_QUERY_FAILED, $e);
		}

		$affected = $stat->rowCount();
		$stat->closeCursor();
		$this->logParamQuery($this->query, $affected, $params);

		return $affected;

	}

	/**
	 * Returns the list of columns that are restricting a passed DB-table, an fk list.
	 * Require grant on “references” permissions of connected db-user. Memory cached.
	 *
	 * @param	string	Name of table to check.
	 * @return	\stdClass[]
	 */
	public function getForeignKeys(string $tableName): array {

		// check the internal memory-cache
		if (!isset($this->definitions[$tableName]['foreignKeys'])) {

			// old-style join because of speedness
			$query =
				'SELECT k.`CONSTRAINT_NAME`, k.`COLUMN_NAME`, k.`REFERENCED_TABLE_NAME`,
				k.`REFERENCED_COLUMN_NAME`, r.`UPDATE_RULE`, r.`DELETE_RULE`
				FROM information_schema.`KEY_COLUMN_USAGE` AS k
				JOIN information_schema.`REFERENTIAL_CONSTRAINTS` AS r
				WHERE k.`CONSTRAINT_NAME` != "PRIMARY"
				AND k.`TABLE_SCHEMA` = :dbName AND k.`TABLE_NAME` = :tableName
				AND r.`CONSTRAINT_SCHEMA` = :dbName AND r.`TABLE_NAME` = :tableName
				AND k.`CONSTRAINT_NAME` = r.`CONSTRAINT_NAME`';

			$params = [
				'dbName' => Env::get('DB_NAME'),
				'tableName' => $tableName
			];

			$this->definitions[$tableName]['foreignKeys'] = self::load($query, $params);

		}

		return $this->definitions[$tableName]['foreignKeys'];

	}

	/**
	 * Connects to db just the first time, returns singleton object everytime.
	 */
	public static function getInstance(): self {

		if (is_null(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;

	}

	/**
	 * Load and return the list of columns that are restricted by a passed DB-table, the inverse fk list.
	 * Require grant on “references” permissions of connected db-user. Memory cached.
	 *
	 * @param	string	Name of external table to check.
	 * @return	\stdClass[]
	 */
	public function getInverseForeignKeys(string $tableName): array {

		// check the internal memory-cache
		if (!isset($this->definitions[$tableName]['inverseForeignKeys'])) {

			// old-style join because of speedness
			$query =
				'SELECT k.CONSTRAINT_NAME, k.REFERENCED_COLUMN_NAME, k.TABLE_NAME,
				k.COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE
				FROM information_schema.`KEY_COLUMN_USAGE` AS k
				JOIN information_schema.`REFERENTIAL_CONSTRAINTS` AS r
				WHERE k.CONSTRAINT_NAME != "PRIMARY"
				AND k.TABLE_SCHEMA = :dbName AND k.REFERENCED_TABLE_NAME = :tableName
				AND r.CONSTRAINT_SCHEMA = :dbName AND r.REFERENCED_TABLE_NAME = :tableName
				AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME';

			$params = [
				'dbName' => Env::get('DB_NAME'),
				'tableName' => $tableName
			];

			$this->definitions[$tableName]['inverseForeignKeys'] = self::load($query, $params);

		}

		return $this->definitions[$tableName]['inverseForeignKeys'];

	}

	/**
	 * Returns last inserted ID, if any.
	 */
	public function getLastInsertId(): string|bool {

		$this->openConnection();

		return $this->handler->lastInsertId();

	}

	/**
	 * Return the MySQL version number.
	 */
	public function getMysqlVersion(): FALSE|string {

		$this->setQuery('SELECT VERSION()');
		return $this->loadResult();

	}

	/**
	 * Inserts a new row in param table with all properties value as columns value.
	 *
	 * @param	string	Table name.
	 * @param	object	Object with property name as each column name.
	 * @param	array	Optional list of encryptable columns.
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
			} else if (Env::get('AES_CRYPT_KEY') and in_array($column, $encryptables)) {
				$columns[] = '`' . $column . '`';
				$values[] = 'AES_ENCRYPT(' . $this->quote($v) . ',' . $this->quote(Env::get('AES_CRYPT_KEY')) . ')';
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
	 * Check if parameter table has auto-increment primary key by using cached method.
	 *
	 * @param	string	Name of table to check auto-increment flag.
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
	 * Check if the current instance is connected to the DBMS.
	 */
	public function isConnected(): bool {

		return is_a($this->handler, 'PDO');

	}

	/**
	 * Checks if the indicated column is generated automatically and in this case returns TRUE
	 *
	 * @param	string	Name of table to check.
	 * @param	string	Name of the column in the table to check
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
	 * Return data in various formats by third string parameter. Default is self::OBJECT_LIST parameters
	 * as array. Support PDO parameters bind.
	 *
	 * @param	string	SQL query.
	 * @param	array	List of parameters to bind on the sql query.
	 * @param	int		Returned type (see class constants). self::OBJECT_LIST is default.
	 * @throws	PairException
	 */
	public static function load(string $query, array $params=[], int $option=self::OBJECT_LIST): array|Collection|\stdClass|string|int|NULL {

		$res = NULL;

		$self = static::getInstance();
		$self->openConnection();
		$self->castParams($params);

		$stat = $self->handler->prepare($query);

		try {

			$stat->execute($params);

		} catch (\PDOException $e) {

			// choose the right exception based on MySQL error code
			switch ($e->getCode()) {

				case '21000':
					throw new PairException($e->getMessage(), ErrorCodes::DB_CARDINALITY_VIOLATION, $e);

				case '42000':
					throw new PairException($e->getMessage(), ErrorCodes::INVALID_QUERY_SYNTAX, $e);

				case '42S02':
					if (strpos($e->getMessage(), 'Unknown database')!==FALSE) {
						throw new CriticalException($e->getMessage(), ErrorCodes::MISSING_DB, $e);
					} else if (strpos($e->getMessage(), 'Table')!==FALSE) {
						throw new CriticalException($e->getMessage(), ErrorCodes::MISSING_DB_TABLE, $e);
					} else {
						throw new PairException($e->getMessage(), ErrorCodes::INVALID_QUERY_SYNTAX, $e);
					}

				case 'HY000':
					throw new CriticalException($e->getMessage(), ErrorCodes::MYSQL_GENERAL_ERROR, $e);

				// invalid parameter number: mixed named and positional parameters
				case 'HY093':
					throw new PairException($e->getMessage(), ErrorCodes::INVALID_QUERY_SYNTAX, $e);

				default:
					throw new PairException($e->getMessage(), ErrorCodes::DB_QUERY_FAILED, $e);

			}

		}

		switch ($option) {

			// list of \stdClass objects
			default:
			case self::OBJECT_LIST:
				$res = $stat->fetchAll(\PDO::FETCH_OBJ);
				$count = count($res);
				break;

			// first row as \stdClass object
			case self::OBJECT:
				$res = $stat->fetch(\PDO::FETCH_OBJ);
				if (!$res) $res = NULL;
				$count = (bool)$res;
				break;

			// array of first column results
			case self::RESULT_LIST:
				$res = $stat->fetchAll(\PDO::FETCH_COLUMN);
				$count = count($res);
				break;

			// first column of first row
			case self::RESULT:
				$res = $stat->fetch(\PDO::FETCH_COLUMN);
				$count = $self->handler->query('SELECT FOUND_ROWS()')->fetchColumn();
				break;

			// result count as integer
			case self::COUNT:
				$res = (int)$stat->fetch(\PDO::FETCH_COLUMN);
				$count = $res;
				break;

			// associative array
			case self::DICTIONARY:
				$res = $stat->fetchAll(\PDO::FETCH_ASSOC);
				$count = count($res);
				break;

			case self::COLLECTION:
				$res = new Collection($stat->fetchAll(\PDO::FETCH_OBJ));
				$count = $res->count();
				break;

		}

		$self->logParamQuery($query, $count, $params);

		$stat->closeCursor();

		return $res;

	}

	/**
	 * Return the query count as integer number.
	 *
	 * @param	array	Optional list of parameters to bind on sql query.
	 * @throws	PairException
	 */
	public function loadCount(array $params=[]): int {

		$this->openConnection();

		$res = 0;
		$stat = $this->handler->prepare($this->query);

		try {
			$stat->execute($params);
		} catch (\PDOException $e) {
			throw new PairException($e->getMessage(), ErrorCodes::DB_QUERY_FAILED, $e);
		}

		$this->logParamQuery($this->query, $res, $params);
		$res = (int)$stat->fetch(\PDO::FETCH_COLUMN);

		$stat->closeCursor();

		return $res;

	}

	/**
	 * Returns a recordset executing the query previously set with setQuery() method and
	 * optional parameters as array.
	 *
	 * @param	array		List of parameters to bind on sql query.
	 * @throws	PairException
	 */
	public function loadObjectList(array $params=[]): array {

		$ret = NULL;

		$this->openConnection();

		$stat = $this->handler->prepare($this->query);

		try {
			$stat->execute($params);
		} catch (\PDOException $e) {
			throw new PairException($e->getMessage(), ErrorCodes::DB_QUERY_FAILED, $e);
		}

		$ret = $stat->fetchAll(\PDO::FETCH_OBJ);

		// logBar
		$this->logParamQuery($this->query, count($ret), $params);

		$stat->closeCursor();

		return $ret;

	}

	/**
	 * Returns first column value or NULL if row is not found.
	 *
	 * @param	array|NULL	List of parameters to bind on sql query.
	 * @throws	PairException
	 */
	private function loadResult(array $params=[]): FALSE|string {

		$this->openConnection();

		$res = NULL;
		$stat = $this->handler->prepare($this->query);

		try {
			$stat->execute((array)$params);
		} catch (\PDOException $e) {
			throw new PairException($e->getMessage(), ErrorCodes::DB_QUERY_FAILED, $e);
		}

		$res = $stat->fetch(\PDO::FETCH_COLUMN);

		// logBar
		$count = $this->handler->query('SELECT FOUND_ROWS()')->fetchColumn();
		$this->logParamQuery($this->query, $count, $params);

		$stat->closeCursor();

		return $res;

	}

	/**
	 * Proxy for logQuery() that binds query parameters.
	 *
	 * @param	string	SQL query.
	 * @param	int		Number of items in result-set or affected rows.
	 * @param	array	Optional parameters to bind.
	 */
	private function logParamQuery(string $query, int $result, array $params=[]): void {

		// indexed is binding with "?"
		$indexed = $params==array_values($params);

		foreach ($params as $column=>$value) {

			if (is_string($value)) {
				$value = "'$value'";
			} else if (is_null($value)) {
				$value = 'NULL';
			} else if (is_bool($value)) {
				$value = $value ? 'TRUE' : 'FALSE';
			} else if (is_array($value)) {
				$value = 'Array';
			} else if (is_object($value)) {
				$value = get_class($value);
			} else {
				$value = (string)$value;
			}

			// fix omitted ":" on named parameters
			if (':'!=substr($column,0,1)) {
				$column = ':'.$column;
			}

			$query = $indexed ? preg_replace('/\?/', $value, $query, 1) : str_replace($column, $value, $query);

		}

		$this->logQuery($query, $result);

	}

	/**
	 * Adds an entry item on system log.
	 *
	 * @param	string	SQL query.
	 * @param	int		Number of items in result-set or affected rows.
	 */
	private function logQuery(string $query, int $result): void {

		$subtext = (int)$result . ' ' . (1==$result ? 'row' : 'rows');

		Logger::query($query, $subtext);

	}

	/**
	 * Connects to DBMS with params only if PDO handler property is null, so not connected.
	 *
	 * @param	bool	Flag to open a persistent connection (TRUE). Default is FALSE.
	 * @throws	CriticalException
	 */
	private function openConnection(bool $persistent=FALSE): void {

		// continue only if not already connected
		if (is_a($this->handler, 'PDO')) {
			return;
		}

		$dsn = 'mysql:host=' . Env::get('DB_HOST') . ';dbname=' . Env::get('DB_NAME');
		$options = [
			\PDO::ATTR_PERSISTENT			=> $persistent,
			\PDO::MYSQL_ATTR_INIT_COMMAND	=> "SET NAMES utf8",
			\PDO::MYSQL_ATTR_FOUND_ROWS		=> TRUE
		];

		try {
			$this->handler = new \PDO($dsn, Env::get('DB_USER'), Env::get('DB_PASS'), $options);
		} catch (\PDOException $e) {
			throw new CriticalException($e->getMessage(), ErrorCodes::DB_CONNECTION_FAILED, $e);
		}

		if (!is_a($this->handler, 'PDO')) {
			throw new CriticalException('PDO connection failed', ErrorCodes::DB_CONNECTION_FAILED);
		}

		$this->handler->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

	}

	/**
	 * Quotes a string for use in a query.
	 *
	 * @param	string	String to quote.
	 */
	public function quote(string $text): string {

		$this->openConnection();

		return $this->handler->quote($text);

	}

	/**
	 * Does the rollback of the transaction.
	 */
	public static function rollback(): void {

		static::run('ROLLBACK');

	}

	/**
	 * Run a query with parameters and return the number of affected rows. Support PDO parameters bind.
	 *
	 * @param	string	SQL query to run.
	 * @param	array	List of parameters to bind on the sql query.
	 * @throws	PairException
	 */
	public static function run(string $query, array $params=[]): int {

		$self = static::getInstance();
		$self->openConnection();

		$stat = $self->handler->prepare($query);

		try {
			$stat->execute((array)$params);
		} catch (\PDOException $e) {
			throw new PairException($e->getMessage(), ErrorCodes::DB_QUERY_FAILED, $e);
		}

		// count affected rows
		$affected = $stat->rowCount();

		$stat->closeCursor();
		$self->logParamQuery($query, $affected, $params);

		return $affected;

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
	 * Set MySQL connection as UTF8mb4 and collation as utf8mb4_unicode_ci, useful to
	 * support extended unicode like Emoji.
	 */
	public function setUtf8unicode(): void {

		$this->openConnection();

		try {

			// set names
			$this->handler->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

			// prepare query to discover db user privileges
			$stat = $this->handler->prepare('SELECT `PRIVILEGE_TYPE` FROM information_schema.user_privileges' .
				' WHERE `GRANTEE` = \'' . Env::get('DB_USER') . '\'@\'' . Env::get('DB_HOST') . '\'');

			// get user privileges
			$privilegeType = $stat->fetch(\PDO::FETCH_COLUMN);

			if (in_array($privilegeType, ['SUPER', 'SYSTEM_VARIABLES_ADMIN', 'SESSION_VARIABLES_ADMIN'])) {

				$this->handler->exec(
					'SET character_set_client = "utf8mb4", character_set_connection = "utf8mb4",
					character_set_database = "utf8mb4", character_set_results = "utf8mb4",
					character_set_server = "utf8mb4", collation_connection = "utf8mb4_unicode_ci",
					collation_database = "utf8mb4_unicode_ci", collation_server = "utf8mb4_unicode_ci"');

			}

		} catch (\PDOException $e) {

			throw new PairException('Error setting utf8mb4 charset and collation', ErrorCodes::DB_QUERY_FAILED, $e);

		}

	}

	/**
	 * Set the table description in the object cache.
	 *
	 * @param	string	Name of table to describe.
	 * @param	array	Table description.
	 */
	public function setTableDescription(string $tableName, $tableDesc): void {

		foreach ($tableDesc as $name => $properties) {

			$column = new \stdClass();
			$column->Field = $name;
			$column->Type = $properties[0];
			$column->Null = $properties[1];
			$column->Key = $properties[2];
			$column->Default = $properties[3];
			$column->Extra = $properties[4];

			$this->definitions[$tableName]['describe'][] = $column;

		}

	}

	/**
	 * Starts a transaction.
	 */
	public static function start(): void {

		static::run('START TRANSACTION');

	}

	/**
	 * Check wheter a table exists by its name.
	 *
	 * @param	string	Table name.
	 */
	public function tableExists(string $tableName): bool {

		$this->setQuery('SHOW TABLES LIKE ?');
		return (bool)$this->loadResult([$tableName]);

	}

	/**
	 * Update record of given key on the param object. Properly manage NULL values.
	 *
	 * @param	string		Table name.
	 * @param	\stdClass	Object with properties of new values to update.
	 * @param	\stdClass	Object with keys and values for where clause.
	 * @param	array		Optional list of encryptable columns.
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
			} else if (Env::get('AES_CRYPT_KEY') and in_array($column, $encryptables)) {
				$sets[] = '`' . $column . '`=AES_ENCRYPT(' . $this->quote($value) . ',' . $this->quote(Env::get('AES_CRYPT_KEY')) . ')';
			} else {
				$sets[] = '`' . $column . '`=?';
				$columnVal[] = $value;
			}

		}

		// create one list of values to bind
		$values = array_merge($columnVal, $condVal);

		if (count($sets) and count($where)) {

			// build the SQL query
			$query = 'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE ' . implode(' AND ', $where);

			// execute the SQL query
			$res = $this->exec($query, $values);

		} else {

			$res = 0;

		}

		return $res;

	}

}