<?php

namespace Pair\Api;

use Pair\Orm\Database;
use Pair\Orm\Query;

/**
 * Parses API query parameters and applies them to a Query builder.
 *
 * Supported query parameters:
 *   - filter[property]=value   Exact match filter.
 *   - filter[property]=!value  Not equal filter.
 *   - filter[property]=>=N     Greater than or equal filter.
 *   - filter[property]=<=N     Less than or equal filter.
 *   - filter[property]=val1,val2  IN filter.
 *   - sort=-field,field        Sorting (- prefix for DESC).
 *   - fields=field1,field2     Sparse fieldsets (field selection).
 *   - search=keyword           Full-text search across searchable properties.
 *   - include=rel1,rel2        Relationship inclusion.
 *   - page=N                   Page number (1-based).
 *   - perPage=N                Items per page.
 */
class QueryFilter {

	/**
	 * The parsed fields to select.
	 *
	 * @var string[]|null
	 */
	private ?array $fields = null;

	/**
	 * The parsed includes.
	 *
	 * @var string[]
	 */
	private array $includes = [];

	/**
	 * Page number.
	 */
	private int $page = 1;

	/**
	 * Items per page.
	 */
	private int $perPage = 20;

	/**
	 * The model class being queried.
	 */
	private string $modelClass;

	/**
	 * The API config for the model.
	 *
	 * @var array<string, mixed>
	 */
	private array $config;

	/**
	 * The HTTP request.
	 */
	private Request $request;

	/**
	 * Create a new QueryFilter instance.
	 *
	 * @param	string					$modelClass	The ActiveRecord model class name.
	 * @param	Request					$request	The HTTP request.
	 * @param	array<string, mixed>	$config		The API config from ApiExposable.
	 */
	public function __construct(string $modelClass, Request $request, array $config) {

		$this->modelClass = $modelClass;
		$this->request = $request;
		$this->config = $config;
		$this->perPage = $config['perPage'] ?? 20;

	}

	/**
	 * Apply all query parameters to a Query builder and return paginated results.
	 *
	 * @return array{query: Query, page: int, perPage: int, fields: ?array, includes: string[]}
	 */
	public function apply(): array {

		$class = $this->modelClass;
		$binds = $class::getBinds();

		$query = Query::table($class::TABLE_NAME)
			->select($class::getQueryColumns());

		$this->applyFilters($query, $binds);
		$this->applySearch($query, $binds);
		$this->applySorting($query, $binds);
		$this->applyPagination($query);
		$this->parseFields();
		$this->parseIncludes();

		return [
			'query'    => $query,
			'page'     => $this->page,
			'perPage'  => $this->perPage,
			'fields'   => $this->fields,
			'includes' => $this->includes,
		];

	}

	/**
	 * Apply filter[property]=value parameters to the query.
	 *
	 * @param	Query	$query	The query builder.
	 * @param	array	$binds	Property-to-column bindings.
	 */
	private function applyFilters(Query $query, array $binds): void {

		$filters = $this->request->query('filter');

		if (!is_array($filters)) {
			return;
		}

		$filterable = $this->config['filterable'] ?? [];

		foreach ($filters as $property => $value) {

			// skip disallowed filter properties
			if (!in_array($property, $filterable) or !isset($binds[$property])) {
				continue;
			}

			$column = $binds[$property];

			// null filter
			if ($value === 'null') {
				$query->whereNull($column);
				continue;
			}

			// not-null filter
			if ($value === '!null') {
				$query->whereNotNull($column);
				continue;
			}

			// not equal filter
			if (is_string($value) and str_starts_with($value, '!')) {
				$query->where($column, '!=', substr($value, 1));
				continue;
			}

			// greater than or equal filter
			if (is_string($value) and str_starts_with($value, '>=')) {
				$query->where($column, '>=', substr($value, 2));
				continue;
			}

			// less than or equal filter
			if (is_string($value) and str_starts_with($value, '<=')) {
				$query->where($column, '<=', substr($value, 2));
				continue;
			}

			// greater than filter
			if (is_string($value) and str_starts_with($value, '>')) {
				$query->where($column, '>', substr($value, 1));
				continue;
			}

			// less than filter
			if (is_string($value) and str_starts_with($value, '<')) {
				$query->where($column, '<', substr($value, 1));
				continue;
			}

			// IN filter (comma-separated values)
			if (is_string($value) and str_contains($value, ',')) {
				$values = array_map('trim', explode(',', $value));
				$query->whereIn($column, $values);
				continue;
			}

			// exact match
			$query->where($column, $value);

		}

	}

	/**
	 * Apply pagination parameters to the query.
	 *
	 * @param	Query	$query	The query builder.
	 */
	private function applyPagination(Query $query): void {

		$page = (int)$this->request->query('page', 1);
		$perPage = (int)$this->request->query('perPage', $this->config['perPage'] ?? 20);

		// enforce bounds
		$this->page = max(1, $page);
		$maxPerPage = $this->config['maxPerPage'] ?? 100;
		$this->perPage = max(1, min($perPage, $maxPerPage));

		$query->forPage($this->page, $this->perPage);

	}

	/**
	 * Apply search=keyword parameter for full-text search across searchable properties.
	 *
	 * @param	Query	$query	The query builder.
	 * @param	array	$binds	Property-to-column bindings.
	 */
	private function applySearch(Query $query, array $binds): void {

		$search = $this->request->query('search');

		if (!$search or !is_string($search)) {
			return;
		}

		$searchable = $this->config['searchable'] ?? [];

		if (!count($searchable)) {
			return;
		}

		$searchTerm = '%' . $search . '%';

		$query->whereNested(function (Query $q) use ($searchable, $binds, $searchTerm) {

			$first = true;

			foreach ($searchable as $property) {

				if (!isset($binds[$property])) {
					continue;
				}

				$column = $binds[$property];

				if ($first) {
					$q->whereRaw('`' . $column . '` LIKE ?', [$searchTerm]);
					$first = false;
				} else {
					$q->orWhereRaw('`' . $column . '` LIKE ?', [$searchTerm]);
				}

			}

		});

	}

	/**
	 * Apply sort parameter to the query. Format: sort=-field,field (- prefix for DESC).
	 *
	 * @param	Query	$query	The query builder.
	 * @param	array	$binds	Property-to-column bindings.
	 */
	private function applySorting(Query $query, array $binds): void {

		$sort = $this->request->query('sort');

		// fall back to default sort
		if (!$sort or !is_string($sort)) {
			$sort = $this->config['defaultSort'] ?? null;
		}

		if (!$sort) {
			return;
		}

		$sortable = $this->config['sortable'] ?? [];
		$sortFields = array_map('trim', explode(',', $sort));

		foreach ($sortFields as $sortField) {

			// determine direction
			$direction = 'ASC';

			if (str_starts_with($sortField, '-')) {
				$direction = 'DESC';
				$sortField = substr($sortField, 1);
			}

			// skip disallowed sort properties
			if (!in_array($sortField, $sortable) or !isset($binds[$sortField])) {
				continue;
			}

			$query->orderBy($binds[$sortField], $direction);

		}

	}

	/**
	 * Build a count query from the current filters (without sorting, pagination, or field selection).
	 *
	 * @return int Total count of matching records.
	 */
	public function count(): int {

		$class = $this->modelClass;
		$binds = $class::getBinds();

		$query = Query::table($class::TABLE_NAME)
			->select('COUNT(1)');

		$this->applyFilters($query, $binds);
		$this->applySearch($query, $binds);

		return (int)Database::load($query->toSql(), $query->getBindings(), Database::COUNT);

	}

	/**
	 * Return the parsed fields list for sparse fieldsets.
	 *
	 * @return string[]|null
	 */
	public function getFields(): ?array {

		return $this->fields;

	}

	/**
	 * Return the parsed includes list.
	 *
	 * @return string[]
	 */
	public function getIncludes(): array {

		return $this->includes;

	}

	/**
	 * Parse the fields parameter for sparse fieldsets.
	 */
	private function parseFields(): void {

		$fields = $this->request->query('fields');

		if ($fields and is_string($fields)) {
			$this->fields = array_map('trim', explode(',', $fields));
		}

	}

	/**
	 * Parse the include parameter for relationship inclusion.
	 */
	private function parseIncludes(): void {

		$include = $this->request->query('include');

		if (!$include or !is_string($include)) {
			return;
		}

		$requested = array_map('trim', explode(',', $include));
		$allowed = $this->config['includes'] ?? [];

		// only allow configured includes
		$this->includes = array_values(array_intersect($requested, $allowed));

	}

}
