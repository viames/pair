<?php

namespace Pair\Orm;

/**
 * Compiles query components into SQL.
 * This class is responsible for turning a Query object into a raw SQL string.
 */
class QueryGrammar {

	/**
	 * Compile a select query into SQL.
	 * 
	 * @param Query $query The query builder instance to compile.
	 * @return string The compiled SQL query.
	 */
	public function compileSelect(Query $query): string {

		// If no columns are specified, select all
		if (empty($query->columns)) {
			$query->columns = ['*'];
		}

		$sql = trim(implode(' ', array_filter([
			$this->compileColumns($query),
			$this->compileFrom($query),
			$this->compileJoins($query),
			$this->compileWheres($query),
			$this->compileGroups($query),
			$this->compileHavings($query),
			$this->compileOrders($query),
			$this->compileLimit($query),
			$this->compileOffset($query),
		])));

		if ($query->distinct) {
			$sql = 'SELECT DISTINCT ' . substr($sql, 7);
		}

		return $sql;

	}

	/**
	 * Compile clauses into SQL.
	 * 
	 * @param array<int, array<string, mixed>> $clauses Clauses to compile.
	 * @param bool $wrap Whether to wrap identifiers.
	 * @return string Compiled SQL for the clauses.
	 */
	protected function compileClauses(array $clauses, bool $wrap): string {

		$parts = [];

		foreach ($clauses as $index => $clause) {
			$prefix = $index === 0 ? '' : strtoupper($clause['boolean']) . ' ';
			$parts[] = $prefix . $this->compileWhere($clause, $wrap);
		}

		return implode(' ', $parts);

	}

	/**
	 * Compile the FROM clause of a query.
	 * 
	 * @param Query $query The query builder instance to compile.
	 * @return string The compiled SQL for the FROM clause.
	 */
	protected function compileFrom(Query $query): string {

		return 'FROM ' . $this->wrapTable($query->from, $query->wrapIdentifiers);

	}

	/**
	 * Compile a group by clause into SQL.
	 * 
	 * @param Query $query The query builder instance to compile.
	 * @return string The compiled SQL for the group by clause.
	 */
	protected function compileGroups(Query $query): string {

		return !empty($query->groups) ? 'GROUP BY ' . implode(', ', array_map(fn($g) => $this->wrapIdentifier($g, $query->wrapIdentifiers), $query->groups)) : '';

	}

	/**
	 * Compile the columns of a select query into SQL.
	 * 
	 * @param Query $query The query builder instance to compile.
	 * @return string The compiled SQL for the columns.
	 */
	protected function compileColumns(Query $query): string {

		$columns = [];

		foreach ($query->columns as $column) {
			$columns[] = $this->wrapAliasedIdentifier($column, $query->wrapIdentifiers);
		}

		return 'SELECT ' . implode(', ', $columns);

	}

	/**
	 * Compile the having clauses of a query into SQL.
	 */
	protected function compileHavings(Query $query): string {

		return !empty($query->havings) ? 'HAVING ' . $this->compileClauses($query->havings, $query->wrapIdentifiers) : '';

	}

	/**
	 * Compile the join clauses of a query into SQL.
	 *
	 * @param Query $query The query builder instance to compile.
	 * @return string The compiled SQL for the joins.
	 */
	protected function compileJoins(Query $query): string {

		$sql = [];

		foreach ($query->joins as $join) {

			if ($join instanceof JoinClause) {

				$table = $this->wrapTable($join->table, $query->wrapIdentifiers);
				$clauses = $this->compileJoinClauses($join->clauses, $query->wrapIdentifiers);

				$sql[] = strtoupper($join->type) . " JOIN {$table} ON {$clauses}";

			} elseif (isset($join['sql'])) {

				// raw join
				$sql[] = $join['sql'];

			} else {

				// fallback for legacy array structure if any
				$table = $this->wrapTable($join['table'], $query->wrapIdentifiers);
				$first = $this->wrapIdentifier($join['first'], $query->wrapIdentifiers);
				$second = $this->wrapIdentifier($join['second'], $query->wrapIdentifiers);
				$sql[] = "{$join['type']} JOIN {$table} ON {$first} {$join['operator']} {$second}";

			}
		}

		return implode(' ', $sql);

	}

	/**
	 * Compile join clauses into SQL.
	 * 
	 * @param array<int, array<string, mixed>> $clauses Join clauses to compile.
	 * @param bool $wrap Whether to wrap identifiers.
	 * @return string Compiled SQL for the join clauses.
	 */
	protected function compileJoinClauses(array $clauses, bool $wrap): string {

		$parts = [];

		foreach ($clauses as $index => $clause) {
			$prefix = $index === 0 ? '' : strtoupper($clause['boolean']) . ' ';

			switch ($clause['type']) {
				case 'on':
					$parts[] = $prefix . $this->wrapIdentifier($clause['first'], $wrap) . ' ' . $clause['operator'] . ' ' . $this->wrapIdentifier($clause['second'], $wrap);
					break;
				case 'where':
					$parts[] = $prefix . $this->wrapIdentifier($clause['column'], $wrap) . ' ' . $clause['operator'] . ' ?';
					break;
				case 'in':
					$placeholders = implode(', ', array_fill(0, count($clause['values']), '?'));
					$parts[] = $prefix . $this->wrapIdentifier($clause['column'], $wrap) . ($clause['not'] ? ' NOT IN ' : ' IN ') . '(' . $placeholders . ')';
					break;
				case 'null':
					$parts[] = $prefix . $this->wrapIdentifier($clause['column'], $wrap) . ($clause['not'] ? ' IS NOT NULL' : ' IS NULL');
					break;
				case 'raw':
					$parts[] = $prefix . $clause['sql'];
					break;
				default:
					// Basic fallback
					$parts[] = $prefix . $this->wrapIdentifier($clause['first'] ?? $clause['column'], $wrap) . ' ' . ($clause['operator'] ?? '=') . ' ' . ($clause['second'] ?? '?');
			}
		}

		return implode(' ', $parts);

	}

	/**
	 * Compile the order by clauses of a query into SQL.
	 * 
	 * @param Query $query The query builder instance to compile.
	 * @return string The compiled SQL for the order by clauses.
	 */
	protected function compileOrders(Query $query): string {

		if (empty($query->orders)) {
			return '';
		}
	
		$orders = array_map(function ($order) use ($query) {
	
			return $this->wrapIdentifier($order['column'], $query->wrapIdentifiers) . ($order['direction'] ? ' ' . $order['direction'] : '');
		}, $query->orders);

		return 'ORDER BY ' . implode(', ', $orders);

	}

	/**
	 * Compile the limit clause of a query into SQL.
	 *
	 * @param Query $query The query builder instance to compile.
	 * @return string The compiled SQL for the limit clause.
	 */
	protected function compileLimit(Query $query): string {
		return is_null($query->limit) ? '' : 'LIMIT ' . (int)$query->limit;
	}

	/**
	 * Compile the offset clause of a query into SQL.
	 *
	 * @param Query $query The query builder instance to compile.
	 * @return string The compiled SQL for the offset clause.
	 */
	protected function compileOffset(Query $query): string {
		return is_null($query->offset) ? '' : 'OFFSET ' . (int)$query->offset;
	}

	/**
	 * Compile a single where clause into SQL.
	 *
	 * @param array<string, mixed> $where The where clause to compile.
	 * @param bool $wrap Whether to wrap identifiers.
	 * @return string The compiled SQL for the where clause.
	 */
	protected function compileWhere(array $where, bool $wrap): string {

		switch ($where['type']) {
			case 'nested':
				return '(' . $this->compileClauses($where['clauses'], $wrap) . ')';
			case 'column':
				return $this->wrapIdentifier($where['first'], $wrap) . ' ' . $where['operator'] . ' ' . $this->wrapIdentifier($where['second'], $wrap);
			case 'basic':
				return $this->wrapIdentifier($where['column'], $wrap) . ' ' . $where['operator'] . ' ?';
			case 'raw':
				return $where['sql'];
			case 'null':
				return $this->wrapIdentifier($where['column'], $wrap) . ($where['not'] ? ' IS NOT NULL' : ' IS NULL');
			case 'in':
				if (empty($where['values'])) return $where['not'] ? '1 = 1' : '0 = 1';
				$placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
				return $this->wrapIdentifier($where['column'], $wrap) . ($where['not'] ? ' NOT IN ' : ' IN ') . '(' . $placeholders . ')';
			case 'inSub':
				return $this->wrapIdentifier($where['column'], $wrap) . ($where['not'] ? ' NOT IN ' : ' IN ') . '(' . $where['sql'] . ')';
			case 'between':
				return $this->wrapIdentifier($where['column'], $wrap) . ($where['not'] ? ' NOT BETWEEN ' : ' BETWEEN ') . '? AND ?';
			case 'exists':
				return ($where['not'] ? 'NOT ' : '') . 'EXISTS (' . $where['sql'] . ')';
			default:
				return $this->wrapIdentifier($where['column'], $wrap) . ' ' . ($where['operator'] ?? '=') . ' ?';
		}
	}

	/**
	 * Compile the wheres of a query into SQL.
	 * 
	 * @param Query $query The query builder instance to compile.
	 * @return string The compiled SQL for the where clauses.
	 */
	protected function compileWheres(Query $query): string {

		if (empty($query->wheres)) {
			return '';
		}

		return 'WHERE ' . $this->compileClauses($query->wheres, $query->wrapIdentifiers);

	}

	/**
	 * Wrap an identifier in backticks.
	 * 
	 * @param string $value The identifier to wrap.
	 * @param bool $wrap Whether to wrap the identifier.
	 * @return string The wrapped identifier.
	 */
	public function wrapIdentifier(string $value, bool $wrap = true): string {

		if (!$wrap || $value === '*') return $value;

		return '`' . str_replace('.', '`.`', $value) . '`';

	}

	/**
	 * Wrap an aliased identifier.
	 * 
	 * @param string $value The identifier to wrap.
	 * @param bool $wrap Whether to wrap the identifier.
	 * @return string The wrapped aliased identifier.
	 */
	public function wrapAliasedIdentifier(string $value, bool $wrap = true): string {

		if (!$wrap || $value === '*') return $value;

		// don't wrap raw SQL expressions or numeric literals
		if (is_numeric($value) or preg_match('/[\s\(\)\,]/', $value)) {
			return $value;
		}

		return $this->wrapIdentifier($value, $wrap);

	}

	/**
	 * Wrap a table name in backticks.
	 * 
	 * @param string $table The table name to wrap.
	 * @param bool $wrap Whether to wrap the table name.
	 * @return string The wrapped table name.
	 */
	public function wrapTable(string $table, bool $wrap = true): string {

		return $this->wrapIdentifier($table, $wrap);

	}

}
