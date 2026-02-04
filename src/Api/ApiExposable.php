<?php

namespace Pair\Api;

/**
 * Trait for ActiveRecord models that should be exposed through auto-CRUD API endpoints.
 * Provides configuration for filtering, sorting, searching, field selection, and validation.
 *
 * Usage:
 *   class Faq extends ActiveRecord {
 *       use ApiExposable;
 *
 *       public static function apiConfig(): array {
 *           return [
 *               'resource'   => FaqResource::class,
 *               'searchable' => ['question', 'answer'],
 *               'sortable'   => ['createdAt', 'position'],
 *               'filterable' => ['category', 'isPublished'],
 *               'includes'   => ['author'],
 *               'perPage'    => 20,
 *               'maxPerPage' => 100,
 *               'rules'      => [
 *                   'create' => ['question' => 'required|string', 'answer' => 'required|string'],
 *                   'update' => ['question' => 'string', 'answer' => 'string'],
 *               ],
 *           ];
 *       }
 *   }
 */
trait ApiExposable {

	/**
	 * Return the API configuration for this model. Override in each model class.
	 *
	 * Supported keys:
	 *   - resource:    (string) Resource class for response transformation.
	 *   - searchable:  (string[]) Property names searchable via ?search= parameter.
	 *   - sortable:    (string[]) Property names sortable via ?sort= parameter.
	 *   - filterable:  (string[]) Property names filterable via ?filter[prop]= parameter.
	 *   - includes:    (string[]) Allowed relationship names for ?include= parameter.
	 *   - perPage:     (int) Default items per page (default 20).
	 *   - maxPerPage:  (int) Maximum items per page (default 100).
	 *   - rules:       (array) Validation rules keyed by 'create' and 'update'.
	 *   - defaultSort: (string) Default sort string, e.g. '-createdAt' (default: primary key asc).
	 *
	 * @return array<string, mixed>
	 */
	public static function apiConfig(): array {

		return [];

	}

	/**
	 * Return the merged API config with defaults applied.
	 *
	 * @return array<string, mixed>
	 */
	public static function getApiConfig(): array {

		$defaults = [
			'resource'    => null,
			'searchable'  => [],
			'sortable'    => [],
			'filterable'  => [],
			'includes'    => [],
			'perPage'     => 20,
			'maxPerPage'  => 100,
			'rules'       => ['create' => [], 'update' => []],
			'defaultSort' => null,
		];

		return array_merge($defaults, static::apiConfig());

	}

	/**
	 * Check if a property is allowed for filtering.
	 */
	public static function isFilterable(string $property): bool {

		$config = static::getApiConfig();
		return in_array($property, $config['filterable']);

	}

	/**
	 * Check if a property is allowed for sorting.
	 */
	public static function isSortable(string $property): bool {

		$config = static::getApiConfig();
		return in_array($property, $config['sortable']);

	}

	/**
	 * Check if a property is allowed for searching.
	 */
	public static function isSearchable(string $property): bool {

		$config = static::getApiConfig();
		return in_array($property, $config['searchable']);

	}

	/**
	 * Check if a relationship include is allowed.
	 */
	public static function isIncludable(string $relation): bool {

		$config = static::getApiConfig();
		return in_array($relation, $config['includes']);

	}

}
