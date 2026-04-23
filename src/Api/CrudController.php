<?php

namespace Pair\Api;

use Pair\Core\Logger;
use Pair\Core\Router;
use Pair\Data\RecordMapper;
use Pair\Http\JsonResponse;
use Pair\Http\ResponseInterface;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Collection;
use Pair\Orm\Database;

/**
 * Abstract controller that provides automatic CRUD API endpoints for ActiveRecord models
 * that use the ApiExposable trait. Extends ApiController with resource registration and
 * HTTP method-based routing.
 *
 * Usage in application's ApiController:
 *
 *   class ApiController extends CrudController {
 *       protected function _init(): void {
 *           parent::_init();
 *           $this->crud('faqs', Faq::class);
 *           $this->crud('users', EpUser::class);
 *       }
 *
 *       // Custom endpoints still work alongside auto-CRUD:
 *       public function createContractAction(): void { ... }
 *   }
 *
 * This generates the following endpoints:
 *   GET    /api/faqs                List with filtering, sorting, pagination, field selection.
 *   GET    /api/faqs/{id}           Show single resource.
 *   POST   /api/faqs                Create new resource.
 *   PUT    /api/faqs/{id}           Update existing resource.
 *   DELETE /api/faqs/{id}           Delete resource.
 */
abstract class CrudController extends ApiController {

	/**
	 * Registered CRUD resources keyed by slug.
	 *
	 * @var array<string, array{class: string, config: CrudResourceConfig}>
	 */
	private array $resources = [];

	/**
	 * Create a new resource. Validates the request body, creates the ActiveRecord
	 * object, and returns it through the configured Resource transformer.
	 *
	 * @param	array{class: string, config: CrudResourceConfig}	$resource	Resource configuration.
	 */
	private function createResource(array $resource): ResponseInterface {

		$class = $resource['class'];
		$config = $resource['config'];

		// validate content type
		if (!$this->request->isJson()) {
			return ApiResponse::errorResponse('UNSUPPORTED_MEDIA_TYPE', ['expected' => 'application/json']);
		}

		// validate request data
		$data = $this->request->json();

		if (is_null($data)) {
			return ApiResponse::errorResponse('BAD_REQUEST', ['detail' => ApiResponse::localizedMessage('API_DETAIL_INVALID_OR_EMPTY_JSON_BODY')]);
		}

		// apply validation rules if configured
		$rules = $config->createRules();

		if (count($rules)) {
			$data = $this->request->validateOrResponse($rules);

			// Let validation failures bubble back as explicit responses on the migrated v4 path.
			if ($data instanceof ApiErrorResponse) {
				return $data;
			}
		}

		// create the object
		$object = new $class();
		$binds = $class::getBinds();

		foreach ($data as $property => $value) {

			if (array_key_exists($property, $binds)) {
				$object->__set($property, $value);
			}

		}

		if (!$object->create()) {
			return ApiResponse::errorResponse('INTERNAL_SERVER_ERROR', ['detail' => ApiResponse::localizedMessage('API_DETAIL_FAILED_TO_CREATE_RESOURCE')]);
		}

		// return the created resource
		$responseData = $this->transformResource($object, $config);

		return ApiResponse::jsonResponse($responseData, 201);

	}

	/**
	 * Register a model class as an auto-CRUD resource.
	 *
	 * @param	string		$slug		URL slug for the resource (e.g. 'faqs', 'users').
	 * @param	string		$modelClass	Fully qualified ActiveRecord class name.
	 * @param	array<string, mixed>|CrudResourceConfig|null	$config	Optional config override (defaults to model's apiConfig).
	 */
	protected function crud(string $slug, string $modelClass, array|CrudResourceConfig|null $config = null): void {

		$config = $this->resolveCrudResourceConfig($modelClass, $config);

		$this->resources[$slug] = [
			'class'  => $modelClass,
			'config' => $config,
		];

	}

	/**
	 * Resolve explicit config overrides or model-level ApiExposable config into a typed value object.
	 *
	 * @param	string										$modelClass	Model class name.
	 * @param	array<string, mixed>|CrudResourceConfig|null	$config		Optional config override.
	 */
	private function resolveCrudResourceConfig(string $modelClass, array|CrudResourceConfig|null $config): CrudResourceConfig {

		if (!is_null($config)) {
			return CrudResourceConfig::from($config);
		}

		if (method_exists($modelClass, 'getCrudResourceConfig')) {
			return CrudResourceConfig::from($modelClass::getCrudResourceConfig());
		}

		if (method_exists($modelClass, 'getApiConfig')) {
			return CrudResourceConfig::from($modelClass::getApiConfig());
		}

		return CrudResourceConfig::from(null);

	}

	/**
	 * Delete a resource by its primary key. Returns a 204 No Content on success.
	 *
	 * @param	array{class: string, config: CrudResourceConfig}	$resource	Resource configuration.
	 * @param	string|int	$id			Primary key value.
	 */
	private function deleteResource(array $resource, string|int $id): ResponseInterface {

		$class = $resource['class'];
		$object = $class::find($id);

		if (!$object) {
			return ApiResponse::errorResponse('NOT_FOUND', ['class' => $class, 'id' => $id]);
		}

		// check if deletable
		if (method_exists($object, 'isDeletable') and !$object->isDeletable()) {
			return ApiResponse::errorResponse('CONFLICT', ['detail' => ApiResponse::localizedMessage('API_DETAIL_RESOURCE_REFERENCED_CANNOT_DELETE')]);
		}

		if (!$object->delete()) {
			return ApiResponse::errorResponse('INTERNAL_SERVER_ERROR', ['detail' => ApiResponse::localizedMessage('API_DETAIL_FAILED_TO_DELETE_RESOURCE')]);
		}

		return ApiResponse::jsonResponse(null, 204);

	}

	/**
	 * Return the list of all registered CRUD resource slugs.
	 *
	 * @return string[]
	 */
	public function getRegisteredResources(): array {

		return array_keys($this->resources);

	}

	/**
	 * Return the full resource configuration for a given slug, or null if not registered.
	 *
	 * @param	string	$slug	The resource slug.
	 * @return	array{class: string, config: array<string, mixed>}|null
	 */
	public function getResourceConfig(string $slug): ?array {

		if (!isset($this->resources[$slug])) {
			return null;
		}

		$resource = $this->resources[$slug];

		return [
			'class' => $resource['class'],
			'config' => $resource['config']->toArray(),
		];

	}

	/**
	 * Route a CRUD action to the appropriate handler based on HTTP method and URL params.
	 *
	 * @param	string	$slug	The resource slug that matched.
	 */
	private function handleCrudAction(string $slug): ResponseInterface {

		$resource = $this->resources[$slug];
		$id = Router::get(0);

		return match ($this->request->method()) {
			'GET'    => $id ? $this->showResource($resource, $id) : $this->listResources($resource),
			'POST'   => $this->createResource($resource),
			'PUT',
			'PATCH'  => $id ? $this->updateResource($resource, $id) : ApiResponse::errorResponse('BAD_REQUEST', ['detail' => ApiResponse::localizedMessage('API_DETAIL_RESOURCE_ID_REQUIRED')]),
			'DELETE' => $id ? $this->deleteResource($resource, $id) : ApiResponse::errorResponse('BAD_REQUEST', ['detail' => ApiResponse::localizedMessage('API_DETAIL_RESOURCE_ID_REQUIRED')]),
			default  => ApiResponse::errorResponse('METHOD_NOT_ALLOWED'),
		};

	}

	/**
	 * List resources with filtering, sorting, searching, pagination, and field selection.
	 *
	 * @param	array{class: string, config: CrudResourceConfig}	$resource	Resource configuration.
	 */
	private function listResources(array $resource): JsonResponse {

		$class = $resource['class'];
		$config = $resource['config'];

		// build and apply query filters
		$queryFilter = new QueryFilter($class, $this->request, $config);
		$total = $queryFilter->count();
		$result = $queryFilter->apply();

		$query = $result['query'];
		$page = $result['page'];
		$perPage = $result['perPage'];
		$fields = $result['fields'];
		$includes = $result['includes'];

		// load objects
		$rows = Database::load($query->toSql(), $query->getBindings());
		$objects = [];

		if (is_array($rows)) {
			foreach ($rows as $row) {
				$object = new $class($row);
				$objects[] = $object;
			}
		}

		// transform through Resource class or convert to array
		$data = $this->transformCollection($objects, $config, $fields, $includes);

		return ApiResponse::paginatedResponse($data, $page, $perPage, $total);

	}

	/**
	 * Show a single resource by its primary key.
	 *
	 * @param	array{class: string, config: CrudResourceConfig}	$resource	Resource configuration.
	 * @param	string|int	$id			Primary key value.
	 */
	private function showResource(array $resource, string|int $id): ResponseInterface {

		$class = $resource['class'];
		$config = $resource['config'];
		$object = $class::find($id);

		if (!$object) {
			return ApiResponse::errorResponse('NOT_FOUND', ['class' => $class, 'id' => $id]);
		}

		$fields = null;
		$fieldsParam = $this->request->query('fields');
		if ($fieldsParam and is_string($fieldsParam)) {
			$fields = array_map('trim', explode(',', $fieldsParam));
		}

		$includes = [];
		$includeParam = $this->request->query('include');
		if ($includeParam and is_string($includeParam)) {
			$requested = array_map('trim', explode(',', $includeParam));
			$allowed = $config->includes();
			$includes = array_values(array_intersect($requested, $allowed));
		}

		$data = $this->transformResource($object, $config, $fields, $includes);

		return ApiResponse::jsonResponse($data);

	}

	/**
	 * Transform a collection of ActiveRecord objects for API output.
	 *
	 * @param	ActiveRecord[]		$objects	The objects to transform.
	 * @param	array<string, mixed>|CrudResourceConfig	$config	Resource config.
	 * @param	string[]|null		$fields		Sparse fieldset (null for all fields).
	 * @param	string[]			$includes	Relationship includes.
	 * @return	array
	 */
	private function transformCollection(array $objects, array|CrudResourceConfig $config, ?array $fields = null, array $includes = []): array {

		$config = CrudResourceConfig::from($config);
		$data = [];
		$preloadedIncludes = $this->preloadIncludes($objects, $config, $includes);

		foreach ($objects as $key => $object) {
			$data[] = $this->transformResource($object, $config, $fields, $includes, $preloadedIncludes, $key);
		}

		return $data;

	}

	/**
	 * Transform a single ActiveRecord object for API output using an explicit read-model
	 * or a legacy resource adapter with optional field filtering.
	 *
	 * @param	ActiveRecord	$object		The object to transform.
	 * @param	array<string, mixed>|CrudResourceConfig	$config	Resource config.
	 * @param	string[]|null	$fields		Sparse fieldset (null for all fields).
	 * @param	string[]		$includes	Relationship includes.
	 * @param	array			$preloadedIncludes	Preloaded include values grouped by include and parent key.
	 * @param	int|string|null	$collectionKey	Original collection key for this object.
	 * @return	array
	 */
	private function transformResource(ActiveRecord $object, array|CrudResourceConfig $config, ?array $fields = null, array $includes = [], array $preloadedIncludes = [], int|string|null $collectionKey = null): array {

		$config = CrudResourceConfig::from($config);
		$readModelClass = $config->readModel();
		$resourceClass = $config->resource();

		// Prefer the explicit read-model path in Pair v4.
		if ($readModelClass) {
			$data = RecordMapper::map($object, $readModelClass)->toArray();
		} else if ($resourceClass and class_exists($resourceClass)) {
			$resource = new $resourceClass($object);
			$data = $resource->toArray();
		} else {
			throw new \LogicException('CRUD resource "' . get_class($object) . '" requires an explicit readModel or resource configuration');
		}

		// apply sparse fieldsets
		if (is_array($fields) and count($fields)) {
			$data = array_intersect_key($data, array_flip($fields));
		}

		// load and attach includes
		if (count($includes)) {
			$data = $this->loadIncludes($object, $data, $config, $includes, $preloadedIncludes, $collectionKey);
		}

		return $data;

	}

	/**
	 * Load requested relationship includes and attach them to the response data.
	 *
	 * @param	ActiveRecord	$object		The parent object.
	 * @param	array			$data		The transformed data array.
	 * @param	array<string, mixed>|CrudResourceConfig	$config	Resource config.
	 * @param	string[]		$includes	Relationship names to include.
	 * @param	array			$preloadedIncludes	Preloaded include values grouped by include and parent key.
	 * @param	int|string|null	$collectionKey	Original collection key for this object.
	 * @return	array			Data with includes attached.
	 */
	private function loadIncludes(ActiveRecord $object, array $data, array|CrudResourceConfig $config, array $includes, array $preloadedIncludes = [], int|string|null $collectionKey = null): array {

		$config = CrudResourceConfig::from($config);

		foreach ($includes as $include) {

			$related = null;

			if (!$this->findPreloadedInclude($object, $include, $preloadedIncludes, $collectionKey, $related)) {

				$methodName = 'get' . ucfirst($include);

				try {
					$related = $object->$methodName();
				} catch (\Exception $e) {
					Logger::getInstance()->warning('Failed to load include "' . $include . '": ' . $e->getMessage());
					continue;
				}

			}

			if (is_null($related)) {
				$data[$include] = null;
			} else if ($related instanceof Collection) {
				$data[$include] = $this->transformIncludedCollection($related, $config, $include);
			} else if ($related instanceof ActiveRecord) {
				$data[$include] = $this->transformIncludedRecord($related, $config, $include);
			}

		}

		return $data;

	}

	/**
	 * Transform an included collection through explicit include mapping.
	 *
	 * @param	\Pair\Orm\Collection	$collection	Loaded relation collection.
	 * @param	CrudResourceConfig		$config		Resource config.
	 * @param	string					$include	Include name.
	 * @return	array<int|string, array<string, mixed>>
	 */
	private function transformIncludedCollection(Collection $collection, CrudResourceConfig $config, string $include): array {

		$data = [];

		foreach ($collection as $key => $item) {

			if (!$item instanceof ActiveRecord) {
				continue;
			}

			// Preserve record identifiers as collection keys when available.
			$recordKey = $item->getId();
			$data[is_null($recordKey) ? $key : $recordKey] = $this->transformIncludedRecord($item, $config, $include);

		}

		return $data;

	}

	/**
	 * Return true and assign the relation when a preloaded include exists for this object.
	 *
	 * @param	ActiveRecord	$object				Parent object.
	 * @param	string			$include			Include name.
	 * @param	array			$preloadedIncludes	Preloaded include map.
	 * @param	int|string|null	$collectionKey		Original collection key for this object.
	 * @param	mixed			$related			Resolved relation value.
	 */
	private function findPreloadedInclude(ActiveRecord $object, string $include, array $preloadedIncludes, int|string|null $collectionKey, mixed &$related): bool {

		if (!isset($preloadedIncludes[$include]) or !is_array($preloadedIncludes[$include])) {
			return false;
		}

		foreach ($this->preloadLookupKeys($object, $collectionKey) as $key) {

			if (array_key_exists($key, $preloadedIncludes[$include])) {
				$related = $preloadedIncludes[$include][$key];
				return true;
			}

		}

		return false;

	}

	/**
	 * Return lookup keys accepted for preloaded include maps.
	 *
	 * @param	ActiveRecord	$object			Parent object.
	 * @param	int|string|null	$collectionKey	Original collection key for this object.
	 * @return	array<int, int|string>
	 */
	private function preloadLookupKeys(ActiveRecord $object, int|string|null $collectionKey): array {

		$keys = [];

		if (is_int($collectionKey) or is_string($collectionKey)) {
			$keys[] = $collectionKey;
		}

		$id = $object->getId();

		if (is_int($id) or is_string($id)) {
			$keys[] = $id;
		}

		$keys[] = spl_object_id($object);

		return array_values(array_unique($keys, SORT_REGULAR));

	}

	/**
	 * Bulk-load includes through an optional resource-level preloader.
	 *
	 * @param	ActiveRecord[]		$objects	Parent objects being transformed.
	 * @param	CrudResourceConfig	$config		Resource config.
	 * @param	string[]			$includes	Requested includes.
	 * @return	array<string, array<int|string, mixed>>
	 */
	private function preloadIncludes(array $objects, CrudResourceConfig $config, array $includes): array {

		$preloaderClass = $config->includePreloader();

		if (!count($objects) or !count($includes) or !$preloaderClass) {
			return [];
		}

		if (!class_exists($preloaderClass)) {
			throw new \LogicException('CRUD include preloader "' . $preloaderClass . '" does not exist');
		}

		$preloader = new $preloaderClass();

		if (!$preloader instanceof CrudIncludePreloader) {
			throw new \LogicException('CRUD include preloader "' . $preloaderClass . '" must implement ' . CrudIncludePreloader::class);
		}

		// Keep the accepted relation map limited to includes requested and allowed for this response.
		return array_intersect_key($preloader->preload($objects, $includes, $config), array_flip($includes));

	}

	/**
	 * Transform an included relation through explicit mapping rules.
	 *
	 * @param	CrudResourceConfig	$config	Resource config.
	 * @return	array<string, mixed>
	 */
	private function transformIncludedRecord(ActiveRecord $record, CrudResourceConfig $config, string $include): array {

		$includeReadModels = $config->includeReadModels();

		if (isset($includeReadModels[$include])) {
			return RecordMapper::map($record, $includeReadModels[$include])->toArray();
		}

		$includeResources = $config->includeResources();

		if (isset($includeResources[$include]) and class_exists($includeResources[$include])) {
			$resourceClass = $includeResources[$include];
			return (new $resourceClass($record))->toArray();
		}

		if (method_exists($record::class, 'getCrudResourceConfig')) {

			$relatedConfig = CrudResourceConfig::from($record::getCrudResourceConfig());

			if ($relatedConfig->readModel()) {
				return RecordMapper::map($record, $relatedConfig->readModel())->toArray();
			}

			if ($relatedConfig->resource() and class_exists($relatedConfig->resource())) {
				$resourceClass = $relatedConfig->resource();
				return (new $resourceClass($record))->toArray();
			}

		}

		if (method_exists($record::class, 'getApiConfig')) {

			$relatedConfig = CrudResourceConfig::from($record::getApiConfig());

			if ($relatedConfig->readModel()) {
				return RecordMapper::map($record, $relatedConfig->readModel())->toArray();
			}

			if ($relatedConfig->resource() and class_exists($relatedConfig->resource())) {
				$resourceClass = $relatedConfig->resource();
				return (new $resourceClass($record))->toArray();
			}

		}

		throw new \LogicException('Include "' . $include . '" requires an explicit readModel or resource mapping');

	}

	/**
	 * Update a resource by its primary key. Validates the request body, updates the
	 * ActiveRecord object, and returns it through the configured Resource transformer.
	 *
	 * @param	array{class: string, config: CrudResourceConfig}	$resource	Resource configuration.
	 * @param	string|int	$id			Primary key value.
	 */
	private function updateResource(array $resource, string|int $id): ResponseInterface {

		$class = $resource['class'];
		$config = $resource['config'];
		$object = $class::find($id);

		if (!$object) {
			return ApiResponse::errorResponse('NOT_FOUND', ['class' => $class, 'id' => $id]);
		}

		// validate content type
		if (!$this->request->isJson()) {
			return ApiResponse::errorResponse('UNSUPPORTED_MEDIA_TYPE', ['expected' => 'application/json']);
		}

		$data = $this->request->json();

		if (is_null($data)) {
			return ApiResponse::errorResponse('BAD_REQUEST', ['detail' => ApiResponse::localizedMessage('API_DETAIL_INVALID_OR_EMPTY_JSON_BODY')]);
		}

		// apply validation rules if configured
		$rules = $config->updateRules();

		if (count($rules)) {
			$data = $this->request->validateOrResponse($rules);

			// Let validation failures bubble back as explicit responses on the migrated v4 path.
			if ($data instanceof ApiErrorResponse) {
				return $data;
			}
		}

		// update properties
		$binds = $class::getBinds();

		foreach ($data as $property => $value) {

			if (array_key_exists($property, $binds)) {
				$object->__set($property, $value);
			}

		}

		if (!$object->update()) {
			return ApiResponse::errorResponse('INTERNAL_SERVER_ERROR', ['detail' => ApiResponse::localizedMessage('API_DETAIL_FAILED_TO_UPDATE_RESOURCE')]);
		}

		// return the updated resource
		$responseData = $this->transformResource($object, $config);

		return ApiResponse::jsonResponse($responseData);

	}

	/**
	 * Intercept calls to undefined action methods and check if they match a registered
	 * CRUD resource. Falls back to parent's 404 handler if no match.
	 */
	public function __call(mixed $name, mixed $arguments): mixed {

		$action = str_replace('Action', '', $name);

		if (isset($this->resources[$action])) {
			return $this->handleCrudAction($action);
		}

		return parent::__call($name, $arguments);

	}

}
