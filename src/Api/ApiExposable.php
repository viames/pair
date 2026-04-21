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
 *               'readModel'  => FaqReadModel::class,
 *               'searchable' => ['question', 'answer'],
 *               'sortable'   => ['createdAt', 'position'],
 *               'filterable' => ['category', 'isPublished'],
 *               'includes'   => ['author'],
 *               'includeReadModels' => ['author' => AuthorReadModel::class],
 *               'includePreloader'  => FaqIncludePreloader::class,
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
	 *   - readModel:   (string) Read-model class for explicit response transformation.
	 *   - resource:    (string) Legacy Resource class kept as a migration bridge.
	 *   - searchable:  (string[]) Property names searchable via ?search= parameter.
	 *   - sortable:    (string[]) Property names sortable via ?sort= parameter.
	 *   - filterable:  (string[]) Property names filterable via ?filter[prop]= parameter.
	 *   - includes:    (string[]) Allowed relationship names for ?include= parameter.
	 *   - includeReadModels: (array<string, string>) Explicit read models for included relations.
	 *   - includeResources:  (array<string, string>) Legacy resources for included relations.
	 *   - includePreloader:  (string) Optional CrudIncludePreloader class for bulk includes.
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

		return static::getCrudResourceConfig()->toArray();

	}

	/**
	 * Return the typed API config used by internal CRUD consumers.
	 */
	public static function getCrudResourceConfig(): CrudResourceConfig {

		return CrudResourceMetadata::configFor(static::class);

	}

	/**
	 * Check if a property is allowed for filtering.
	 */
	public static function isFilterable(string $property): bool {

		return in_array($property, static::getCrudResourceConfig()->filterable());

	}

	/**
	 * Check if a property is allowed for sorting.
	 */
	public static function isSortable(string $property): bool {

		return in_array($property, static::getCrudResourceConfig()->sortable());

	}

	/**
	 * Check if a property is allowed for searching.
	 */
	public static function isSearchable(string $property): bool {

		return in_array($property, static::getCrudResourceConfig()->searchable());

	}

	/**
	 * Check if a relationship include is allowed.
	 */
	public static function isIncludable(string $relation): bool {

		return in_array($relation, static::getCrudResourceConfig()->includes());

	}

}
