<?php

namespace Pair\Orm;

/**
 * Builder for join clauses.
 */
class JoinClause {

	/**
	 * Join clauses.
	 *
	 * @var	array<int, array<string, mixed>>
	 */
	protected array $clauses = [];

	/**
	 * Join bindings.
	 *
	 * @var	array<int, mixed>
	 */
	protected array $bindings = [];

	/**
	 * Get join bindings.
	 *
	 * @return	array<int, mixed>
	 */
	public function getBindings(): array {

		return $this->bindings;

	}

	/**
	 * Get all join clauses.
	 *
	 * @return	array<int, array<string, mixed>>
	 */
	public function getClauses(): array {

		return $this->clauses;

	}

	/**
	 * Add an "on" clause.
	 *
	 * @param	string	$first	First column.
	 * @param	string	$operator	Comparison operator.
	 * @param	string	$second	Second column.
	 * @param	string	$boolean	Boolean glue (and/or).
	 */
	public function on(string $first, string $operator, string $second, string $boolean = 'and'): static {

		$this->clauses[] = [
			'type' => 'on',
			'first' => $first,
			'operator' => $operator,
			'second' => $second,
			'boolean' => $boolean
		];

		return $this;

	}

	/**
	 * Add an "or on" clause.
	 *
	 * @param	string	$first	First column.
	 * @param	string	$operator	Comparison operator.
	 * @param	string	$second	Second column.
	 */
	public function orOn(string $first, string $operator, string $second): static {

		return $this->on($first, $operator, $second, 'or');

	}

	/**
	 * Add an "or where" clause to the join.
	 *
	 * @param	string	$column	Column name.
	 * @param	mixed	$operator	Comparison operator or value.
	 * @param	mixed	$value		Value when operator is provided.
	 */
	public function orWhere(string $column, mixed $operator = null, mixed $value = null): static {

		return $this->where($column, $operator, $value, 'or');

	}

	/**
	 * Add an "or where in" clause to the join.
	 *
	 * @param	string	$column	Column name.
	 * @param	array<int, mixed>	$values	Values for the IN clause.
	 */
	public function orWhereIn(string $column, array $values): static {

		return $this->whereIn($column, $values, 'or');

	}

	/**
	 * Add an "or where not in" clause to the join.
	 *
	 * @param	string	$column	Column name.
	 * @param	array<int, mixed>	$values	Values for the IN clause.
	 */
	public function orWhereNotIn(string $column, array $values): static {

		return $this->whereIn($column, $values, 'or', true);

	}

	/**
	 * Add an "or where not null" clause to the join.
	 *
	 * @param	string	$column	Column name.
	 */
	public function orWhereNotNull(string $column): static {

		return $this->whereNull($column, 'or', true);

	}

	/**
	 * Add an "or where null" clause to the join.
	 *
	 * @param	string	$column	Column name.
	 */
	public function orWhereNull(string $column): static {

		return $this->whereNull($column, 'or');

	}

	/**
	 * Add a raw "or where" clause to the join.
	 *
	 * @param	string	$sql		Raw SQL for where.
	 * @param	array<int, mixed>	$bindings	Bindings for the raw clause.
	 */
	public function orWhereRaw(string $sql, array $bindings = []): static {

		return $this->whereRaw($sql, $bindings, 'or');

	}

	/**
	 * Add a "where" clause to the join.
	 *
	 * @param	string	$column	Column name.
	 * @param	mixed	$operator	Comparison operator or value.
	 * @param	mixed	$value		Value when operator is provided.
	 * @param	string	$boolean	Boolean glue (and/or).
	 */
	public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static {

		if (func_num_args() === 2) {
			$value = $operator;
			$operator = '=';
		}

		$this->clauses[] = [
			'type' => 'where',
			'column' => $column,
			'operator' => $operator,
			'value' => $value,
			'boolean' => $boolean
		];

		$this->bindings[] = $value;

		return $this;

	}

	/**
	 * Add a "where in" clause to the join.
	 *
	 * @param	string	$column	Column name.
	 * @param	array<int, mixed>	$values	Values for the IN clause.
	 * @param	string	$boolean	Boolean glue (and/or).
	 * @param	bool	$not		Whether to use NOT IN.
	 */
	public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): static {

		$values = array_values($values);

		$this->clauses[] = [
			'type' => 'in',
			'column' => $column,
			'values' => $values,
			'boolean' => $boolean,
			'not' => $not
		];

		if (count($values)) {
			$this->bindings = array_merge($this->bindings, $values);
		}

		return $this;

	}

	/**
	 * Add a "where not in" clause to the join.
	 *
	 * @param	string	$column	Column name.
	 * @param	array<int, mixed>	$values	Values for the IN clause.
	 * @param	string	$boolean	Boolean glue (and/or).
	 */
	public function whereNotIn(string $column, array $values, string $boolean = 'and'): static {

		return $this->whereIn($column, $values, $boolean, true);

	}

	/**
	 * Add a "where not null" clause to the join.
	 *
	 * @param	string	$column	Column name.
	 * @param	string	$boolean	Boolean glue (and/or).
	 */
	public function whereNotNull(string $column, string $boolean = 'and'): static {

		return $this->whereNull($column, $boolean, true);

	}

	/**
	 * Add a "where null" clause to the join.
	 *
	 * @param	string	$column	Column name.
	 * @param	string	$boolean	Boolean glue (and/or).
	 * @param	bool	$not		Whether to use IS NOT NULL.
	 */
	public function whereNull(string $column, string $boolean = 'and', bool $not = false): static {

		$this->clauses[] = [
			'type' => 'null',
			'column' => $column,
			'boolean' => $boolean,
			'not' => $not
		];

		return $this;

	}

	/**
	 * Add a raw where clause to the join.
	 *
	 * @param	string	$sql		Raw SQL for where.
	 * @param	array<int, mixed>	$bindings	Bindings for the raw clause.
	 * @param	string	$boolean	Boolean glue (and/or).
	 */
	public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): static {

		$this->clauses[] = [
			'type' => 'raw',
			'sql' => $sql,
			'boolean' => $boolean
		];

		if (count($bindings)) {
			$this->bindings = array_merge($this->bindings, $bindings);
		}

		return $this;

	}

}
