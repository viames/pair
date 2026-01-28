<?php

namespace Pair\Orm;

/**
 * Simple query builder for SQL SELECT statements.
 */
class Query {

	/**
	 * Selected columns.
	 *
	 * @var	string[]
	 */
	protected array $columns = ['*'];

	/**
	 * Table to query.
	 */
	protected ?string $from = null;

	/**
	 * Join clauses.
	 *
	 * @var	array<int, array<string, mixed>>
	 */
	protected array $joins = [];

	/**
	 * Where clauses.
	 *
	 * @var	array<int, array<string, mixed>>
	 */
	protected array $wheres = [];

	/**
	 * Group by columns.
	 *
	 * @var	string[]
	 */
	protected array $groups = [];

	/**
	 * Having clauses.
	 *
	 * @var	array<int, array<string, mixed>>
	 */
	protected array $havings = [];

	/**
	 * Order by clauses.
	 *
	 * @var	array<int, array{column: string, direction: string}>
	 */
	protected array $orders = [];

	/**
	 * Union queries.
	 *
	 * @var	array<int, array{sql: string, all: bool, bindings: array<int, mixed>}>
	 */
	protected array $unions = [];

	/**
	 * Query limit.
	 */
	protected ?int $limit = null;

	/**
	 * Query offset.
	 */
	protected ?int $offset = null;

	/**
	 * Distinct flag.
	 */
	protected bool $distinct = false;

	/**
	 * Row lock clause.
	 */
	protected ?string $lock = null;

	/**
	 * Whether to wrap identifiers with backticks when safe.
	 */
	protected bool $wrapIdentifiers = true;

	/**
	 * Bindings grouped by clause type.
	 *
	 * @var	array<string, array<int, mixed>>
	 */
	protected array $bindings = [
		'select' => [],
		'from' => [],
		'join' => [],
		'where' => [],
		'group' => [],
		'having' => [],
		'union' => [],
		'order' => []
	];

	/**
	 * Create a new query instance.
	 *
	 * @param	string|null	$table	Optional table name.
	 */
	public function __construct(?string $table = null) {

		if ($table) {
			$this->from($table);
		}

	}

	/**
	 * Cast the query to string.
	 */
	public function __toString(): string {

		return $this->toSql();

	}

	/**
	 * Add additional columns to the select list.
	 *
	 * @param	string|array<int, string>	...$columns	Column names.
	 */
	public function addSelect(string|array ...$columns): static {

		if (count($columns) === 1 and is_array($columns[0])) {
			$columns = $columns[0];
		}

		$this->columns = array_merge($this->columns, $columns);

		return $this;

	}

	/**
	 * Add a raw select expression.
	 *
	 * @param	string	$sql		Raw SQL.
	 * @param	array<int, mixed>	$bindings	Bindings for the raw expression.
	 */
	public function selectRaw(string $sql, array $bindings = []): static {

		$this->addSelect($sql);

		if (count($bindings)) {
			$this->bindings['select'] = array_merge($this->bindings['select'], $bindings);
		}

		return $this;

	}

	/**
	 * Add a subquery select expression.
	 *
	 * @param	Query|callable|string	$query	Subquery builder, callback or SQL.
	 * @param	string					$as		Alias for the subquery.
	 */
	public function selectSub(Query|callable|string $query, string $as): static {

		$subquery = $this->createSubquery($query);
		$alias = $this->wrapIdentifier($as);

		$this->addSelect('(' . $subquery['sql'] . ') AS ' . $alias);

		if (count($subquery['bindings'])) {
			$this->bindings['select'] = array_merge($this->bindings['select'], $subquery['bindings']);
		}

		return $this;

	}

	/**
	 * Build a subquery SQL string and bindings.
	 *
	 * @param	Query|callable|string	$query	Subquery builder, callback or SQL.
	 * @return	array{sql: string, bindings: array<int, mixed>}
	 */
	protected function createSubquery(Query|callable|string $query): array {

		if ($query instanceof Query) {
			return [
				'sql' => $query->toSql(),
				'bindings' => $query->getBindings()
			];
		}

		if (is_callable($query)) {
			$subquery = new static();
			$subquery->wrapIdentifiers($this->wrapIdentifiers);
			$query($subquery);

			return [
				'sql' => $subquery->toSql(),
				'bindings' => $subquery->getBindings()
			];
		}

		return [
			'sql' => (string)$query,
			'bindings' => []
		];

	}

	/**
	 * Compile the column list.
	 */
	protected function compileColumns(): string {

		$columns = [];

		foreach ($this->columns as $column) {
			$columns[] = $this->wrapAliasedIdentifier($column);
		}

		return implode(', ', $columns);

	}

	/**
	 * Compile the join clauses.
	 */
	protected function compileJoins(): string {

		$joins = [];

		foreach ($this->joins as $join) {
			if (isset($join['sql'])) {
				$joins[] = $join['sql'];
				continue;
			}

			$table = $this->wrapTable($join['table']);

			if (isset($join['clauses'])) {
				$clauses = $this->compileJoinClauses($join['clauses']);
				$joins[] = $join['type'] . ' JOIN ' . $table . (strlen($clauses) ? ' ON ' . $clauses : '');
				continue;
			}

			$first = $this->wrapIdentifier($join['first']);
			$second = $this->wrapIdentifier($join['second']);
			$joins[] = $join['type'] . ' JOIN ' . $table . ' ON ' . $first . ' ' . $join['operator'] . ' ' . $second;
		}

		return implode(' ', $joins);

	}

	/**
	 * Compile join clauses.
	 *
	 * @param	array<int, array<string, mixed>>	$clauses	Clauses to compile.
	 */
	protected function compileJoinClauses(array $clauses): string {

		$parts = [];

		foreach ($clauses as $index => $clause) {

			$prefix = $index === 0 ? '' : strtoupper($clause['boolean']) . ' ';

			switch ($clause['type']) {

				case 'raw':
					$sql = $clause['sql'];
					break;

				case 'null':
					$sql = $this->wrapIdentifier($clause['column']) . ($clause['not'] ? ' IS NOT NULL' : ' IS NULL');
					break;

				case 'in':
					if (!count($clause['values'])) {
						$sql = $clause['not'] ? '1 = 1' : '0 = 1';
					} else {
						$placeholders = implode(', ', array_fill(0, count($clause['values']), '?'));
						$sql = $this->wrapIdentifier($clause['column']) . ($clause['not'] ? ' NOT IN ' : ' IN ') . '(' . $placeholders . ')';
					}
					break;

				case 'where':
					$sql = $this->wrapIdentifier($clause['column']) . ' ' . $clause['operator'] . ' ?';
					break;

				case 'on':
				default:
					$sql = $this->wrapIdentifier($clause['first']) . ' ' . $clause['operator'] . ' ' . $this->wrapIdentifier($clause['second']);
					break;

			}

			$parts[] = $prefix . $sql;

		}

		return implode(' ', $parts);

	}

	/**
	 * Compile order by clauses.
	 */
	protected function compileOrders(): string {

		$orders = [];

		foreach ($this->orders as $order) {
			$column = $this->wrapIdentifier($order['column']);
			$orders[] = $order['direction'] ? $column . ' ' . $order['direction'] : $column;
		}

		return implode(', ', $orders);

	}

	/**
	 * Compile a single where clause.
	 *
	 * @param	array<string, mixed>	$where	Clause data.
	 */
	protected function compileWhere(array $where): string {

		switch ($where['type']) {

			case 'raw':
				return $where['sql'];

			case 'nested':
				$clauses = $where['clauses'] ?? $where['query']->wheres;
				return '(' . $this->compileWheres($clauses) . ')';

			case 'column':
				return $this->wrapIdentifier($where['first']) . ' ' . $where['operator'] . ' ' . $this->wrapIdentifier($where['second']);

			case 'exists':
				return ($where['not'] ? 'NOT ' : '') . 'EXISTS (' . $where['sql'] . ')';

			case 'null':
				return $this->wrapIdentifier($where['column']) . ($where['not'] ? ' IS NOT NULL' : ' IS NULL');

			case 'in':
				if (!count($where['values'])) {
					return $where['not'] ? '1 = 1' : '0 = 1';
				}
				$placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
				return $this->wrapIdentifier($where['column']) . ($where['not'] ? ' NOT IN ' : ' IN ') . '(' . $placeholders . ')';

			case 'inSub':
				return $this->wrapIdentifier($where['column']) . ($where['not'] ? ' NOT IN ' : ' IN ') . '(' . $where['sql'] . ')';

			case 'between':
				return $this->wrapIdentifier($where['column']) . ($where['not'] ? ' NOT BETWEEN ' : ' BETWEEN ') . '? AND ?';

			case 'basic':
			default:
				return $this->wrapIdentifier($where['column']) . ' ' . $where['operator'] . ' ?';

		}

	}

	/**
	 * Compile where or having clauses.
	 *
	 * @param	array<int, array<string, mixed>>	$clauses	Clauses to compile.
	 */
	protected function compileWheres(array $clauses): string {

		$parts = [];

		foreach ($clauses as $index => $where) {

			$prefix = $index === 0 ? '' : strtoupper($where['boolean']) . ' ';
			$parts[] = $prefix . $this->compileWhere($where);

		}

		return implode(' ', $parts);

	}

	/**
	 * Force the query to return distinct results.
	 *
	 * @param	bool	$value	Distinct flag.
	 */
	public function distinct(bool $value = true): static {

		$this->distinct = $value;

		return $this;

	}

	/**
	 * Set the row lock mode.
	 *
	 * @param	bool|string	$value	Lock mode (true for FOR UPDATE, false for LOCK IN SHARE MODE).
	 */
	public function lock(bool|string $value = true): static {

		if (is_string($value)) {
			$this->lock = $value;
			return $this;
		}

		$this->lock = $value ? 'FOR UPDATE' : 'LOCK IN SHARE MODE';

		return $this;

	}

	/**
	 * Lock the selected rows for update.
	 */
	public function lockForUpdate(): static {

		return $this->lock(true);

	}

	/**
	 * Share lock the selected rows.
	 */
	public function sharedLock(): static {

		return $this->lock(false);

	}

	/**
	 * Returns the first result of the query.
	 */
	public function first(): ?\stdClass {

		$query = clone $this;
		$query->limit(1);

		return Database::load($query->toSql(), $query->getBindings(), Database::OBJECT);

	}

	/**
	 * Set the table to select from.
	 *
	 * @param	string	$table	Table name.
	 */
	public function from(string $table): static {

		$this->from = $table;

		return $this;

	}

	/**
	 * Set the table using a raw expression.
	 *
	 * @param	string	$sql		Raw SQL.
	 * @param	array<int, mixed>	$bindings	Bindings for the raw expression.
	 */
	public function fromRaw(string $sql, array $bindings = []): static {

		$this->from = $sql;

		if (count($bindings)) {
			$this->bindings['from'] = array_merge($this->bindings['from'], $bindings);
		}

		return $this;

	}

	/**
	 * Set the table using a subquery.
	 *
	 * @param	Query|callable|string	$query	Subquery builder, callback or SQL.
	 * @param	string					$as		Alias for the subquery.
	 */
	public function fromSub(Query|callable|string $query, string $as): static {

		$subquery = $this->createSubquery($query);
		$alias = $this->wrapIdentifier($as);

		$this->from = '(' . $subquery['sql'] . ') AS ' . $alias;

		if (count($subquery['bindings'])) {
			$this->bindings['from'] = array_merge($this->bindings['from'], $subquery['bindings']);
		}

		return $this;

	}

	/**
	 * Returns a Collection instance containing the results of the query where each
	 * result is an instance of the PHP stdClass object. You may access each column's
	 * value by accessing the column as a property of the object.
	 */
	public function get(): Collection {

		return Database::load($this->toSql(), $this->getBindings(), Database::COLLECTION);

	}

	/**
	 * Determine if any rows exist for the current query.
	 */
	public function exists(): bool {

		$query = clone $this;
		$query->selectRaw('1');
		$query->limit(1);

		return !is_null(Database::load($query->toSql(), $query->getBindings(), Database::OBJECT));

	}

	/**
	 * Determine if no rows exist for the current query.
	 */
	public function doesntExist(): bool {

		return !$this->exists();

	}

	/**
	 * Get a single column's value from the first result.
	 *
	 * @param	string	$column	Column name or expression.
	 */
	public function value(string $column): mixed {

		$query = clone $this;
		$query->select($column);
		$query->limit(1);

		$result = Database::load($query->toSql(), $query->getBindings(), Database::RESULT_LIST);

		return $result[0] ?? null;

	}

	/**
	 * Get a list of column values.
	 *
	 * @param	string		$column	Column name or expression.
	 * @param	string|null	$key	Optional key column.
	 * @return	array<int|string, mixed>
	 */
	public function pluck(string $column, ?string $key = null): array {

		$query = clone $this;

		if (is_null($key)) {
			$query->select($column);
			return Database::load($query->toSql(), $query->getBindings(), Database::RESULT_LIST);
		}

		$query->columns = [];
		$query->bindings['select'] = [];
		$query->selectRaw($column . ' AS value');
		$query->addSelect($key . ' AS key');

		$rows = Database::load($query->toSql(), $query->getBindings(), Database::DICTIONARY);
		$result = [];

		foreach ($rows as $row) {
			$result[$row['key']] = $row['value'];
		}

		return $result;

	}

	/**
	 * Execute an aggregate function on the query.
	 *
	 * @param	string	$function	Aggregate function.
	 * @param	string	$column		Column name or expression.
	 */
	public function aggregate(string $function, string $column = '*'): mixed {

		$query = clone $this;
		$query->columns = [];
		$query->bindings['select'] = [];
		$query->orders = [];
		$query->bindings['order'] = [];
		$query->limit = null;
		$query->offset = null;

		$column = $column === '*' ? '*' : $this->wrapIdentifier($column);

		$query->selectRaw(strtoupper($function) . '(' . $column . ') AS aggregate');

		$result = Database::load($query->toSql(), $query->getBindings(), Database::RESULT_LIST);

		return $result[0] ?? null;

	}

	/**
	 * Get the count of the results.
	 *
	 * @param	string	$column	Column name or expression.
	 */
	public function count(string $column = '*'): int {

		return (int)$this->aggregate('COUNT', $column);

	}

	/**
	 * Get the maximum value of a given column.
	 *
	 * @param	string	$column	Column name or expression.
	 */
	public function max(string $column): mixed {

		return $this->aggregate('MAX', $column);

	}

	/**
	 * Get the minimum value of a given column.
	 *
	 * @param	string	$column	Column name or expression.
	 */
	public function min(string $column): mixed {

		return $this->aggregate('MIN', $column);

	}

	/**
	 * Get the sum of the given column.
	 *
	 * @param	string	$column	Column name or expression.
	 */
	public function sum(string $column): mixed {

		return $this->aggregate('SUM', $column);

	}

	/**
	 * Get the average of the given column.
	 *
	 * @param	string	$column	Column name or expression.
	 */
	public function avg(string $column): mixed {

		return $this->aggregate('AVG', $column);

	}

	/**
	 * Get the bindings for the query.
	 *
	 * @return	array<int, mixed>
	 */
	public function getBindings(): array {

		return array_merge(
			$this->bindings['select'],
			$this->bindings['from'],
			$this->bindings['join'],
			$this->bindings['where'],
			$this->bindings['group'],
			$this->bindings['having'],
			$this->bindings['union'],
			$this->bindings['order']
		);

	}

	/**
	 * Add a group by clause.
	 *
	 * @param	string|array<int, string>	...$columns	Columns to group by.
	 */
	public function groupBy(string|array ...$columns): static {

		if (count($columns) === 1 and is_array($columns[0])) {
			$columns = $columns[0];
		}

		$this->groups = array_merge($this->groups, $columns);

		return $this;

	}

	/**
	 * Add a raw group by clause.
	 *
	 * @param	string	$sql		Raw SQL.
	 * @param	array<int, mixed>	$bindings	Bindings for the raw expression.
	 */
	public function groupByRaw(string $sql, array $bindings = []): static {

		$this->groups[] = $sql;

		if (count($bindings)) {
			$this->bindings['group'] = array_merge($this->bindings['group'], $bindings);
		}

		return $this;

	}

	/**
	 * Add a having clause.
	 *
	 * @param	string	$column	Column name.
	 * @param	mixed	$operator	Comparison operator or value.
	 * @param	mixed	$value		Value when operator is provided.
	 * @param	string	$boolean	Boolean glue (and/or).
	 */
	public function having(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static {

		if (func_num_args() === 2) {
			$value = $operator;
			$operator = '=';
		}

		$this->havings[] = [
			'type' => 'basic',
			'column' => $column,
			'operator' => $operator,
			'value' => $value,
			'boolean' => $boolean
		];

		$this->bindings['having'][] = $value;

		return $this;

	}

	/**
	 * Add a raw having clause.
	 *
	 * @param	string	$sql	Raw SQL for having.
	 * @param	array<int, mixed>	$bindings	Bindings for the raw clause.
	 * @param	string	$boolean	Boolean glue (and/or).
	 */
	public function havingRaw(string $sql, array $bindings = [], string $boolean = 'and'): static {

		$this->havings[] = [
			'type' => 'raw',
			'sql' => $sql,
			'boolean' => $boolean
		];

		if (count($bindings)) {
			$this->bindings['having'] = array_merge($this->bindings['having'], $bindings);
		}

		return $this;

	}

	/**
	 * Add a nested having clause.
	 *
	 * @param	callable	$callback	Callback to build nested clauses.
	 * @param	string		$boolean	Boolean glue (and/or).
	 */
	public function havingNested(callable $callback, string $boolean = 'and'): static {

		$query = new static();
		$query->wrapIdentifiers($this->wrapIdentifiers);
		$callback($query);

		if (!count($query->havings)) {
			return $this;
		}

		$this->havings[] = [
			'type' => 'nested',
			'clauses' => $query->havings,
			'boolean' => $boolean
		];

		$this->bindings['having'] = array_merge($this->bindings['having'], $query->bindings['having']);

		return $this;

	}

	/**
	 * Add an inner join clause.
	 *
	 * @param	string			$table	Join table.
	 * @param	string|callable	$first	First column or callback.
	 * @param	string|null		$operator	Comparison operator.
	 * @param	string|null		$second	Second column.
	 * @param	bool			$where	Whether to use bindings in the join.
	 */
	public function join(string $table, string|callable $first, ?string $operator = null, ?string $second = null, bool $where = false): static {

		return $this->joinWithType('inner', $table, $first, $operator, $second, $where);

	}

	/**
	 * Add a join clause with the given type.
	 *
	 * @param	string			$type	Join type.
	 * @param	string			$table	Join table.
	 * @param	string|callable	$first	First column or callback.
	 * @param	string|null		$operator	Comparison operator.
	 * @param	mixed			$second	Second column or value.
	 * @param	bool			$where	Whether to use bindings in the join.
	 */
	protected function joinWithType(string $type, string $table, string|callable $first, ?string $operator = null, mixed $second = null, bool $where = false): static {

		if (is_callable($first)) {

			$join = new JoinClause();
			$first($join);

			$this->joins[] = [
				'type' => strtoupper($type),
				'table' => $table,
				'clauses' => $join->getClauses()
			];

			if (count($join->getBindings())) {
				$this->bindings['join'] = array_merge($this->bindings['join'], $join->getBindings());
			}

			return $this;

		}

		if (is_null($second)) {
			$second = $operator;
			$operator = '=';
		}

		$operator = $operator ?? '=';

		if ($where) {

			$join = new JoinClause();
			$join->where($first, $operator, $second);

			$this->joins[] = [
				'type' => strtoupper($type),
				'table' => $table,
				'clauses' => $join->getClauses()
			];

			$this->bindings['join'] = array_merge($this->bindings['join'], $join->getBindings());

			return $this;

		}

		$this->joins[] = [
			'type' => strtoupper($type),
			'table' => $table,
			'first' => $first,
			'operator' => $operator,
			'second' => $second
		];

		return $this;

	}

	/**
	 * Add a join clause with bindings.
	 *
	 * @param	string	$table	Join table.
	 * @param	string	$first	First column.
	 * @param	string	$operator	Comparison operator.
	 * @param	mixed	$second	Value to compare.
	 */
	public function joinWhere(string $table, string $first, string $operator, mixed $second): static {

		return $this->join($table, $first, $operator, $second, true);

	}

	/**
	 * Add a raw join clause.
	 *
	 * @param	string	$sql		Raw SQL join clause.
	 * @param	array<int, mixed>	$bindings	Bindings for the raw join.
	 */
	public function joinRaw(string $sql, array $bindings = []): static {

		$this->joins[] = [
			'sql' => $sql
		];

		if (count($bindings)) {
			$this->bindings['join'] = array_merge($this->bindings['join'], $bindings);
		}

		return $this;

	}

	/**
	 * Add a subquery join clause.
	 *
	 * @param	Query|callable|string	$query	Subquery builder, callback or SQL.
	 * @param	string					$as		Alias for the subquery.
	 * @param	string|callable			$first	First column or callback.
	 * @param	string|null				$operator	Comparison operator.
	 * @param	string|null				$second	Second column.
	 * @param	string					$type	Join type.
	 * @param	bool					$where	Whether to use bindings in the join.
	 */
	public function joinSub(Query|callable|string $query, string $as, string|callable $first, ?string $operator = null, ?string $second = null, string $type = 'inner', bool $where = false): static {

		$subquery = $this->createSubquery($query);
		$alias = $this->wrapIdentifier($as);
		$table = '(' . $subquery['sql'] . ') AS ' . $alias;

		if (is_callable($first)) {

			$join = new JoinClause();
			$first($join);

			$this->joins[] = [
				'type' => strtoupper($type),
				'table' => $table,
				'clauses' => $join->getClauses()
			];

			$this->bindings['join'] = array_merge($this->bindings['join'], $subquery['bindings'], $join->getBindings());

			return $this;

		}

		if (is_null($second)) {
			$second = $operator;
			$operator = '=';
		}

		$operator = $operator ?? '=';

		if ($where) {

			$join = new JoinClause();
			$join->where($first, $operator, $second);

			$this->joins[] = [
				'type' => strtoupper($type),
				'table' => $table,
				'clauses' => $join->getClauses()
			];

			$this->bindings['join'] = array_merge($this->bindings['join'], $subquery['bindings'], $join->getBindings());

			return $this;

		}

		$this->joins[] = [
			'type' => strtoupper($type),
			'table' => $table,
			'first' => $first,
			'operator' => $operator,
			'second' => $second
		];

		$this->bindings['join'] = array_merge($this->bindings['join'], $subquery['bindings']);

		return $this;

	}

	/**
	 * Add a left join subquery clause.
	 *
	 * @param	Query|callable|string	$query	Subquery builder, callback or SQL.
	 * @param	string					$as		Alias for the subquery.
	 * @param	string|callable			$first	First column or callback.
	 * @param	string|null				$operator	Comparison operator.
	 * @param	string|null				$second	Second column.
	 * @param	bool					$where	Whether to use bindings in the join.
	 */
	public function leftJoinSub(Query|callable|string $query, string $as, string|callable $first, ?string $operator = null, ?string $second = null, bool $where = false): static {

		return $this->joinSub($query, $as, $first, $operator, $second, 'left', $where);

	}

	/**
	 * Add a right join subquery clause.
	 *
	 * @param	Query|callable|string	$query	Subquery builder, callback or SQL.
	 * @param	string					$as		Alias for the subquery.
	 * @param	string|callable			$first	First column or callback.
	 * @param	string|null				$operator	Comparison operator.
	 * @param	string|null				$second	Second column.
	 * @param	bool					$where	Whether to use bindings in the join.
	 */
	public function rightJoinSub(Query|callable|string $query, string $as, string|callable $first, ?string $operator = null, ?string $second = null, bool $where = false): static {

		return $this->joinSub($query, $as, $first, $operator, $second, 'right', $where);

	}

	/**
	 * Add a cross join clause.
	 *
	 * @param	string	$table	Join table.
	 */
	public function crossJoin(string $table): static {

		$this->joins[] = [
			'type' => 'CROSS',
			'table' => $table,
			'clauses' => []
		];

		return $this;

	}

	/**
	 * Add a left join clause.
	 *
	 * @param	string			$table	Join table.
	 * @param	string|callable	$first	First column or callback.
	 * @param	string|null		$operator	Comparison operator.
	 * @param	string|null		$second	Second column.
	 * @param	bool			$where	Whether to use bindings in the join.
	 */
	public function leftJoin(string $table, string|callable $first, ?string $operator = null, ?string $second = null, bool $where = false): static {

		return $this->joinWithType('left', $table, $first, $operator, $second, $where);

	}

	/**
	 * Add a left join clause with bindings.
	 *
	 * @param	string	$table	Join table.
	 * @param	string	$first	First column.
	 * @param	string	$operator	Comparison operator.
	 * @param	mixed	$second	Value to compare.
	 */
	public function leftJoinWhere(string $table, string $first, string $operator, mixed $second): static {

		return $this->leftJoin($table, $first, $operator, $second, true);

	}

	/**
	 * Set the query limit.
	 *
	 * @param	int	$limit	Maximum rows.
	 */
	public function limit(int $limit): static {

		$this->limit = $limit;

		return $this;

	}

	/**
	 * Set the query offset.
	 *
	 * @param	int	$offset	Offset rows.
	 */
	public function offset(int $offset): static {

		$this->offset = $offset;

		return $this;

	}

	/**
	 * Set the current page and limit for pagination.
	 *
	 * @param	int	$page		Page number (1-based).
	 * @param	int	$perPage	Items per page.
	 */
	public function forPage(int $page, int $perPage): static {

		$page = max(1, $page);

		return $this->skip(($page - 1) * $perPage)->take($perPage);

	}

	/**
	 * Paginate the given query.
	 *
	 * @param	int				$perPage	Items per page.
	 * @param	int				$page		Page number (1-based).
	 * @param	string|array	$columns	Columns to select.
	 * @return	array<string, mixed>
	 */
	public function paginate(int $perPage = 15, int $page = 1, string|array $columns = ['*']): array {

		$page = max(1, $page);

		$dataQuery = clone $this;
		$dataQuery->select($columns);
		$dataQuery->forPage($page, $perPage);

		$countQuery = clone $this;
		$countQuery->orders = [];
		$countQuery->bindings['order'] = [];
		$countQuery->limit = null;
		$countQuery->offset = null;

		$total = (int)$countQuery->count();
		$items = $dataQuery->get();
		$lastPage = $perPage > 0 ? (int)ceil($total / $perPage) : 0;

		return [
			'items' => $items,
			'total' => $total,
			'perPage' => $perPage,
			'currentPage' => $page,
			'lastPage' => $lastPage,
			'from' => $total ? (($page - 1) * $perPage + 1) : null,
			'to' => $total ? min($page * $perPage, $total) : null
		];

	}

	/**
	 * Add an order by clause.
	 *
	 * @param	string	$column	Column name.
	 * @param	string	$direction	Direction (asc/desc).
	 */
	public function orderBy(string $column, string $direction = 'asc'): static {

		$direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

		$this->orders[] = [
			'column' => $column,
			'direction' => $direction
		];

		return $this;

	}

	/**
	 * Add a descending order by clause.
	 *
	 * @param	string	$column	Column name.
	 */
	public function orderByDesc(string $column): static {

		return $this->orderBy($column, 'desc');

	}

	/**
	 * Add a raw order by clause.
	 *
	 * @param	string	$sql		Raw SQL.
	 * @param	array<int, mixed>	$bindings	Bindings for the raw expression.
	 */
	public function orderByRaw(string $sql, array $bindings = []): static {

		$this->orders[] = [
			'column' => $sql,
			'direction' => ''
		];

		if (count($bindings)) {
			$this->bindings['order'] = array_merge($this->bindings['order'], $bindings);
		}

		return $this;

	}

	/**
	 * Add an "order by created_at desc" clause.
	 *
	 * @param	string	$column	Column name.
	 */
	public function latest(string $column = 'created_at'): static {

		return $this->orderBy($column, 'desc');

	}

	/**
	 * Add an "order by created_at asc" clause.
	 *
	 * @param	string	$column	Column name.
	 */
	public function oldest(string $column = 'created_at'): static {

		return $this->orderBy($column, 'asc');

	}

	/**
	 * Add an "or having" clause.
	 *
	 * @param	string	$column	Column name.
	 * @param	mixed	$operator	Comparison operator or value.
	 * @param	mixed	$value		Value when operator is provided.
	 */
	public function orHaving(string $column, mixed $operator = null, mixed $value = null): static {

		return $this->having($column, $operator, $value, 'or');

	}

	/**
	 * Add a nested "or having" clause.
	 *
	 * @param	callable	$callback	Callback to build nested clauses.
	 */
	public function orHavingNested(callable $callback): static {

		return $this->havingNested($callback, 'or');

	}

	/**
	 * Add an "or having raw" clause.
	 *
	 * @param	string	$sql	Raw SQL for having.
	 * @param	array<int, mixed>	$bindings	Bindings for the raw clause.
	 */
	public function orHavingRaw(string $sql, array $bindings = []): static {

		return $this->havingRaw($sql, $bindings, 'or');

	}

	/**
	 * Add an "or where" clause.
	 *
	 * @param	string|callable	$column	Column name or callback.
	 * @param	mixed	$operator	Comparison operator or value.
	 * @param	mixed	$value		Value when operator is provided.
	 */
	public function orWhere(string|callable $column, mixed $operator = null, mixed $value = null): static {

		return $this->where($column, $operator, $value, 'or');

	}

	/**
	 * Add an "or where raw" clause.
	 *
	 * @param	string	$sql	Raw SQL for where.
	 * @param	array<int, mixed>	$bindings	Bindings for the raw clause.
	 */
	public function orWhereRaw(string $sql, array $bindings = []): static {

		return $this->whereRaw($sql, $bindings, 'or');

	}

	/**
	 * Add a nested "or where" clause.
	 *
	 * @param	callable	$callback	Callback to build nested clauses.
	 */
	public function orWhereNested(callable $callback): static {

		return $this->whereNested($callback, 'or');

	}

	/**
	 * Add an "or where column" clause.
	 *
	 * @param	string	$first	First column.
	 * @param	mixed	$operator	Comparison operator or second column.
	 * @param	string|null	$second	Second column.
	 */
	public function orWhereColumn(string $first, mixed $operator = null, ?string $second = null): static {

		return $this->whereColumn($first, $operator, $second, 'or');

	}

	/**
	 * Add an "or where exists" clause.
	 *
	 * @param	Query|callable|string	$query	Subquery builder, callback or SQL.
	 */
	public function orWhereExists(Query|callable|string $query): static {

		return $this->whereExists($query, 'or');

	}

	/**
	 * Add a "where not exists" clause.
	 *
	 * @param	Query|callable|string	$query	Subquery builder, callback or SQL.
	 * @param	string					$boolean	Boolean glue (and/or).
	 */
	public function whereNotExists(Query|callable|string $query, string $boolean = 'and'): static {

		return $this->whereExists($query, $boolean, true);

	}

	/**
	 * Add an "or where not exists" clause.
	 *
	 * @param	Query|callable|string	$query	Subquery builder, callback or SQL.
	 */
	public function orWhereNotExists(Query|callable|string $query): static {

		return $this->whereExists($query, 'or', true);

	}

	/**
	 * Add a right join clause.
	 *
	 * @param	string			$table	Join table.
	 * @param	string|callable	$first	First column or callback.
	 * @param	string|null		$operator	Comparison operator.
	 * @param	string|null		$second	Second column.
	 * @param	bool			$where	Whether to use bindings in the join.
	 */
	public function rightJoin(string $table, string|callable $first, ?string $operator = null, ?string $second = null, bool $where = false): static {

		return $this->joinWithType('right', $table, $first, $operator, $second, $where);

	}

	/**
	 * Add a right join clause with bindings.
	 *
	 * @param	string	$table	Join table.
	 * @param	string	$first	First column.
	 * @param	string	$operator	Comparison operator.
	 * @param	mixed	$second	Value to compare.
	 */
	public function rightJoinWhere(string $table, string $first, string $operator, mixed $second): static {

		return $this->rightJoin($table, $first, $operator, $second, true);

	}

	/**
	 * Set the columns to select.
	 *
	 * @param	string|array<int, string>	...$columns	Column names.
	 */
	public function select(string|array ...$columns): static {

		if (count($columns) === 1 and is_array($columns[0])) {
			$columns = $columns[0];
		}

		$this->columns = count($columns) ? $columns : ['*'];

		return $this;

	}

	/**
	 * Alias for offset().
	 *
	 * @param	int	$offset	Offset rows.
	 */
	public function skip(int $offset): static {

		return $this->offset($offset);

	}

	/**
	 * Start a new query for the given table.
	 *
	 * @param	string	$table	Table name.
	 */
	public static function table(string $table): static {

		return new static($table);

	}

	/**
	 * Alias for limit().
	 *
	 * @param	int	$limit	Maximum rows.
	 */
	public function take(int $limit): static {

		return $this->limit($limit);

	}

	/**
	 * Add a union clause.
	 *
	 * @param	Query|callable|string	$query	Subquery builder, callback or SQL.
	 * @param	bool					$all	Whether to use UNION ALL.
	 */
	public function union(Query|callable|string $query, bool $all = false): static {

		$subquery = $this->createSubquery($query);

		$this->unions[] = [
			'sql' => $subquery['sql'],
			'all' => $all,
			'bindings' => $subquery['bindings']
		];

		if (count($subquery['bindings'])) {
			$this->bindings['union'] = array_merge($this->bindings['union'], $subquery['bindings']);
		}

		return $this;

	}

	/**
	 * Add a union all clause.
	 *
	 * @param	Query|callable|string	$query	Subquery builder, callback or SQL.
	 */
	public function unionAll(Query|callable|string $query): static {

		return $this->union($query, true);

	}

	/**
	 * Compile the base select query.
	 *
	 * @param	bool	$includeOrders	Whether to include order/limit/offset.
	 */
	protected function compileSelect(bool $includeOrders = true): string {

		$sql = 'SELECT ';

		if ($this->distinct) {
			$sql .= 'DISTINCT ';
		}

		$sql .= $this->compileColumns();

		if ($this->from) {
			$sql .= ' FROM ' . $this->wrapTable($this->from);
		}

		if (count($this->joins)) {
			$sql .= ' ' . $this->compileJoins();
		}

		if (count($this->wheres)) {
			$sql .= ' WHERE ' . $this->compileWheres($this->wheres);
		}

		if (count($this->groups)) {
			$groups = [];
			foreach ($this->groups as $group) {
				$groups[] = $this->wrapIdentifier($group);
			}
			$sql .= ' GROUP BY ' . implode(', ', $groups);
		}

		if (count($this->havings)) {
			$sql .= ' HAVING ' . $this->compileWheres($this->havings);
		}

		if ($includeOrders) {

			if (count($this->orders)) {
				$sql .= ' ORDER BY ' . $this->compileOrders();
			}

			$sql .= $this->compileLimitOffset();

		}

		return $sql;

	}

	/**
	 * Compile limit and offset clauses.
	 */
	protected function compileLimitOffset(): string {

		$sql = '';

		if (is_null($this->limit) and !is_null($this->offset)) {
			$sql .= ' LIMIT 18446744073709551615';
		} else if (!is_null($this->limit)) {
			$sql .= ' LIMIT ' . (int)$this->limit;
		}

		if (!is_null($this->offset)) {
			$sql .= ' OFFSET ' . (int)$this->offset;
		}

		return $sql;

	}

	/**
	 * Get the raw SQL string for the query.
	 */
	public function toSql(): string {

		$sql = $this->compileSelect(count($this->unions) === 0);

		if (count($this->unions)) {

			$sql = '(' . $sql . ')';

			foreach ($this->unions as $union) {
				$sql .= ($union['all'] ? ' UNION ALL ' : ' UNION ') . '(' . $union['sql'] . ')';
			}

			if (count($this->orders)) {
				$sql .= ' ORDER BY ' . $this->compileOrders();
			}

			$sql .= $this->compileLimitOffset();

		}

		if (!is_null($this->lock)) {
			$sql .= ' ' . $this->lock;
		}

		return $sql;

	}

	/**
	 * Add a basic where clause.
	 *
	 * @param	string|callable	$column	Column name or callback.
	 * @param	mixed	$operator	Comparison operator or value.
	 * @param	mixed	$value		Value when operator is provided.
	 * @param	string	$boolean	Boolean glue (and/or).
	 */
	public function where(string|callable $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static {

		if (is_callable($column)) {
			return $this->whereNested($column, $boolean);
		}

		if (func_num_args() === 2) {
			$value = $operator;
			$operator = '=';
		}

		if (is_null($value) and in_array($operator, ['=', '=='], true)) {
			return $this->whereNull($column, $boolean);
		}

		if (is_null($value) and in_array($operator, ['!=', '<>'], true)) {
			return $this->whereNotNull($column, $boolean);
		}

		$this->wheres[] = [
			'type' => 'basic',
			'column' => $column,
			'operator' => $operator,
			'value' => $value,
			'boolean' => $boolean
		];

		$this->bindings['where'][] = $value;

		return $this;

	}

	/**
	 * Add a nested where clause.
	 *
	 * @param	callable	$callback	Callback to build nested clauses.
	 * @param	string		$boolean	Boolean glue (and/or).
	 */
	public function whereNested(callable $callback, string $boolean = 'and'): static {

		$query = new static();
		$query->wrapIdentifiers($this->wrapIdentifiers);
		$callback($query);

		if (!count($query->wheres)) {
			return $this;
		}

		$this->wheres[] = [
			'type' => 'nested',
			'clauses' => $query->wheres,
			'boolean' => $boolean
		];

		$this->bindings['where'] = array_merge($this->bindings['where'], $query->bindings['where']);

		return $this;

	}

	/**
	 * Add a "where column" clause.
	 *
	 * @param	string	$first	First column.
	 * @param	mixed	$operator	Comparison operator or second column.
	 * @param	string|null	$second	Second column.
	 * @param	string	$boolean	Boolean glue (and/or).
	 */
	public function whereColumn(string $first, mixed $operator = null, ?string $second = null, string $boolean = 'and'): static {

		if (func_num_args() === 2) {
			$second = (string)$operator;
			$operator = '=';
		}

		$this->wheres[] = [
			'type' => 'column',
			'first' => $first,
			'operator' => $operator,
			'second' => (string)$second,
			'boolean' => $boolean
		];

		return $this;

	}

	/**
	 * Add a "where exists" clause.
	 *
	 * @param	Query|callable|string	$query	Subquery builder, callback or SQL.
	 * @param	string					$boolean	Boolean glue (and/or).
	 * @param	bool					$not	Whether to use NOT EXISTS.
	 */
	public function whereExists(Query|callable|string $query, string $boolean = 'and', bool $not = false): static {

		$subquery = $this->createSubquery($query);

		$this->wheres[] = [
			'type' => 'exists',
			'sql' => $subquery['sql'],
			'boolean' => $boolean,
			'not' => $not
		];

		if (count($subquery['bindings'])) {
			$this->bindings['where'] = array_merge($this->bindings['where'], $subquery['bindings']);
		}

		return $this;

	}

	/**
	 * Add a "where in" clause.
	 *
	 * @param	string	$column	Column name.
	 * @param	array<int, mixed>|Query|callable|string	$values	Values or subquery for the IN clause.
	 * @param	string	$boolean	Boolean glue (and/or).
	 * @param	bool	$not		Whether to use NOT IN.
	 */
	public function whereIn(string $column, array|Query|callable|string $values, string $boolean = 'and', bool $not = false): static {

		if (!is_array($values)) {

			$subquery = $this->createSubquery($values);

			$this->wheres[] = [
				'type' => 'inSub',
				'column' => $column,
				'sql' => $subquery['sql'],
				'boolean' => $boolean,
				'not' => $not
			];

			if (count($subquery['bindings'])) {
				$this->bindings['where'] = array_merge($this->bindings['where'], $subquery['bindings']);
			}

			return $this;

		}

		$values = array_values($values);

		$this->wheres[] = [
			'type' => 'in',
			'column' => $column,
			'values' => $values,
			'boolean' => $boolean,
			'not' => $not
		];

		if (count($values)) {
			$this->bindings['where'] = array_merge($this->bindings['where'], $values);
		}

		return $this;

	}

	/**
	 * Add a "where not in" clause.
	 *
	 * @param	string	$column	Column name.
	 * @param	array<int, mixed>|Query|callable|string	$values	Values or subquery for the IN clause.
	 * @param	string	$boolean	Boolean glue (and/or).
	 */
	public function whereNotIn(string $column, array|Query|callable|string $values, string $boolean = 'and'): static {

		return $this->whereIn($column, $values, $boolean, true);

	}

	/**
	 * Add an "or where in" clause.
	 *
	 * @param	string	$column	Column name.
	 * @param	array<int, mixed>|Query|callable|string	$values	Values or subquery for the IN clause.
	 */
	public function orWhereIn(string $column, array|Query|callable|string $values): static {

		return $this->whereIn($column, $values, 'or');

	}

	/**
	 * Add an "or where not in" clause.
	 *
	 * @param	string	$column	Column name.
	 * @param	array<int, mixed>|Query|callable|string	$values	Values or subquery for the IN clause.
	 */
	public function orWhereNotIn(string $column, array|Query|callable|string $values): static {

		return $this->whereIn($column, $values, 'or', true);

	}

	/**
	 * Add a "where between" clause.
	 *
	 * @param	string	$column	Column name.
	 * @param	array<int, mixed>	$values	Two values for BETWEEN.
	 * @param	string	$boolean	Boolean glue (and/or).
	 * @param	bool	$not		Whether to use NOT BETWEEN.
	 */
	public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): static {

		$values = array_values($values);
		$values = array_pad($values, 2, null);

		$this->wheres[] = [
			'type' => 'between',
			'column' => $column,
			'values' => $values,
			'boolean' => $boolean,
			'not' => $not
		];

		$this->bindings['where'][] = $values[0];
		$this->bindings['where'][] = $values[1];

		return $this;

	}

	/**
	 * Add a "where not between" clause.
	 *
	 * @param	string	$column	Column name.
	 * @param	array<int, mixed>	$values	Two values for BETWEEN.
	 * @param	string	$boolean	Boolean glue (and/or).
	 */
	public function whereNotBetween(string $column, array $values, string $boolean = 'and'): static {

		return $this->whereBetween($column, $values, $boolean, true);

	}

	/**
	 * Add an "or where between" clause.
	 *
	 * @param	string	$column	Column name.
	 * @param	array<int, mixed>	$values	Two values for BETWEEN.
	 */
	public function orWhereBetween(string $column, array $values): static {

		return $this->whereBetween($column, $values, 'or');

	}

	/**
	 * Add an "or where not between" clause.
	 *
	 * @param	string	$column	Column name.
	 * @param	array<int, mixed>	$values	Two values for BETWEEN.
	 */
	public function orWhereNotBetween(string $column, array $values): static {

		return $this->whereBetween($column, $values, 'or', true);

	}

	/**
	 * Add a "where null" clause.
	 *
	 * @param	string	$column	Column name.
	 * @param	string	$boolean	Boolean glue (and/or).
	 */
	public function whereNull(string $column, string $boolean = 'and'): static {

		$this->wheres[] = [
			'type' => 'null',
			'column' => $column,
			'boolean' => $boolean,
			'not' => false
		];

		return $this;

	}

	/**
	 * Add a "where not null" clause.
	 *
	 * @param	string	$column	Column name.
	 * @param	string	$boolean	Boolean glue (and/or).
	 */
	public function whereNotNull(string $column, string $boolean = 'and'): static {

		$this->wheres[] = [
			'type' => 'null',
			'column' => $column,
			'boolean' => $boolean,
			'not' => true
		];

		return $this;

	}

	/**
	 * Add an "or where null" clause.
	 *
	 * @param	string	$column	Column name.
	 */
	public function orWhereNull(string $column): static {

		return $this->whereNull($column, 'or');

	}

	/**
	 * Add an "or where not null" clause.
	 *
	 * @param	string	$column	Column name.
	 */
	public function orWhereNotNull(string $column): static {

		return $this->whereNotNull($column, 'or');

	}

	/**
	 * Add a raw where clause.
	 *
	 * @param	string	$sql	Raw SQL for where.
	 * @param	array<int, mixed>	$bindings	Bindings for the raw clause.
	 * @param	string	$boolean	Boolean glue (and/or).
	 */
	public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): static {

		$this->wheres[] = [
			'type' => 'raw',
			'sql' => $sql,
			'boolean' => $boolean
		];

		if (count($bindings)) {
			$this->bindings['where'] = array_merge($this->bindings['where'], $bindings);
		}

		return $this;

	}

	/**
	 * Wraps a column that may include an alias.
	 */
	protected function wrapAliasedIdentifier(string $value): string {

		if (!$this->wrapIdentifiers) {
			return $value;
		}

		if (preg_match('/^(.+)\\s+as\\s+(.+)$/i', $value, $matches)) {
			return $this->wrapIdentifier(trim($matches[1])) . ' AS ' . $this->wrapIdentifier(trim($matches[2]));
		}

		return $this->wrapIdentifier($value);

	}

	/**
	 * Wraps an identifier with backticks when it is a simple name.
	 */
	protected function wrapIdentifier(string $value): string {

		if (!$this->wrapIdentifiers) {
			return $value;
		}

		if ($value === '*' or str_contains($value, '`')) {
			return $value;
		}

		if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
			return '`' . $value . '`';
		}

		if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*\\.[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
			[$table, $column] = explode('.', $value, 2);
			return '`' . $table . '`.`' . $column . '`';
		}

		return $value;

	}

	/**
	 * Enable or disable automatic identifier wrapping.
	 *
	 * @param	bool	$value	Wrap identifiers flag.
	 */
	public function wrapIdentifiers(bool $value = true): static {

		$this->wrapIdentifiers = $value;

		return $this;

	}

	/**
	 * Wraps a table name when safe.
	 */
	protected function wrapTable(string $table): string {

		return $this->wrapIdentifier($table);

	}

}
