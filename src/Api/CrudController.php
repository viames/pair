<?php

namespace Pair\Api;

use Pair\Core\Logger;
use Pair\Core\Router;
use Pair\Data\RecordMapper;
use Pair\Http\JsonResponse;
use Pair\Http\ResponseInterface;
use Pair\Orm\ActiveRecord;
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
	 * @var array<string, array{class: string, config: array}>
	 */
	private array $resources = [];

	/**
	 * Create a new resource. Validates the request body, creates the ActiveRecord
	 * object, and returns it through the configured Resource transformer.
	 *
	 * @param	array	$resource	Resource configuration.
	 */
	private function createResource(array $resource): ResponseInterface {

		$class = $resource['class'];
		$config = $resource['config'];

		// validate content type
		if (!$this->request->isJson()) {
			ApiResponse::error('UNSUPPORTED_MEDIA_TYPE', ['expected' => 'application/json']);
		}

		// validate request data
		$data = $this->request->json();

		if (is_null($data)) {
			ApiResponse::error('BAD_REQUEST', ['detail' => 'Invalid or empty JSON body']);
		}

		// apply validation rules if configured
		$rules = $config['rules']['create'] ?? [];

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
			ApiResponse::error('INTERNAL_SERVER_ERROR', ['detail' => 'Failed to create resource']);
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
	 * @param	array|null	$config		Optional config override (defaults to model's apiConfig).
	 */
	protected function crud(string $slug, string $modelClass, ?array $config = null): void {

		// get config from model if it uses ApiExposable trait
		if (is_null($config) and method_exists($modelClass, 'getApiConfig')) {
			$config = $modelClass::getApiConfig();
		} else if (is_null($config)) {
			$config = [];
		}

		$this->resources[$slug] = [
			'class'  => $modelClass,
			'config' => $config,
		];

	}

	/**
	 * Delete a resource by its primary key. Returns a 204 No Content on success.
	 *
	 * @param	array		$resource	Resource configuration.
	 * @param	string|int	$id			Primary key value.
	 */
	private function deleteResource(array $resource, string|int $id): JsonResponse {

		$class = $resource['class'];
		$object = $class::find($id);

		if (!$object) {
			ApiResponse::error('NOT_FOUND', ['class' => $class, 'id' => $id]);
		}

		// check if deletable
		if (method_exists($object, 'isDeletable') and !$object->isDeletable()) {
			ApiResponse::error('CONFLICT', ['detail' => 'Resource is referenced and cannot be deleted']);
		}

		if (!$object->delete()) {
			ApiResponse::error('INTERNAL_SERVER_ERROR', ['detail' => 'Failed to delete resource']);
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
	 * @return	array|null
	 */
	public function getResourceConfig(string $slug): ?array {

		return $this->resources[$slug] ?? null;

	}

	/**
	 * Route a CRUD action to the appropriate handler based on HTTP method and URL params.
	 *
	 * @param	string	$slug	The resource slug that matched.
	 * @return	mixed	Explicit response objects when a migrated branch returns one.
	 */
	private function handleCrudAction(string $slug): mixed {

		$resource = $this->resources[$slug];
		$id = Router::get(0);

		// Let migrated response objects bubble up while legacy static emitters keep the existing behavior.
		return match ($this->request->method()) {
			'GET'    => $id ? $this->showResource($resource, $id) : $this->listResources($resource),
			'POST'   => $this->createResource($resource),
			'PUT',
			'PATCH'  => $id ? $this->updateResource($resource, $id) : ApiResponse::error('BAD_REQUEST', ['detail' => 'Resource ID is required']),
			'DELETE' => $id ? $this->deleteResource($resource, $id) : ApiResponse::error('BAD_REQUEST', ['detail' => 'Resource ID is required']),
			default  => ApiResponse::error('METHOD_NOT_ALLOWED'),
		};

	}

	/**
	 * List resources with filtering, sorting, searching, pagination, and field selection.
	 *
	 * @param	array	$resource	Resource configuration.
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
	 * @param	array		$resource	Resource configuration.
	 * @param	string|int	$id			Primary key value.
	 */
	private function showResource(array $resource, string|int $id): JsonResponse {

		$class = $resource['class'];
		$config = $resource['config'];
		$object = $class::find($id);

		if (!$object) {
			ApiResponse::error('NOT_FOUND', ['class' => $class, 'id' => $id]);
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
			$allowed = $config['includes'] ?? [];
			$includes = array_values(array_intersect($requested, $allowed));
		}

		$data = $this->transformResource($object, $config, $fields, $includes);

		return ApiResponse::jsonResponse($data);

	}

	/**
	 * Transform a collection of ActiveRecord objects for API output.
	 *
	 * @param	ActiveRecord[]		$objects	The objects to transform.
	 * @param	array				$config		Resource config.
	 * @param	string[]|null		$fields		Sparse fieldset (null for all fields).
	 * @param	string[]			$includes	Relationship includes.
	 * @return	array
	 */
	private function transformCollection(array $objects, array $config, ?array $fields = null, array $includes = []): array {

		$resourceClass = $config['resource'] ?? null;

		$data = [];

		foreach ($objects as $object) {
			$data[] = $this->transformResource($object, $config, $fields, $includes);
		}

		return $data;

	}

	/**
	 * Transform a single ActiveRecord object for API output using an explicit read-model
	 * or a legacy resource adapter with optional field filtering.
	 *
	 * @param	ActiveRecord	$object		The object to transform.
	 * @param	array			$config		Resource config.
	 * @param	string[]|null	$fields		Sparse fieldset (null for all fields).
	 * @param	string[]		$includes	Relationship includes.
	 * @return	array
	 */
	private function transformResource(ActiveRecord $object, array $config, ?array $fields = null, array $includes = []): array {

		$readModelClass = $config['readModel'] ?? null;
		$resourceClass = $config['resource'] ?? null;

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
			$data = $this->loadIncludes($object, $data, $config, $includes);
		}

		return $data;

	}

	/**
	 * Load requested relationship includes and attach them to the response data.
	 *
	 * @param	ActiveRecord	$object		The parent object.
	 * @param	array			$data		The transformed data array.
	 * @param	array			$config		Resource config.
	 * @param	string[]		$includes	Relationship names to include.
	 * @return	array			Data with includes attached.
	 */
	private function loadIncludes(ActiveRecord $object, array $data, array $config, array $includes): array {

		foreach ($includes as $include) {

			$methodName = 'get' . ucfirst($include);

			try {
				$related = $object->$methodName();
			} catch (\Exception $e) {
				Logger::getInstance()->warning('Failed to load include "' . $include . '": ' . $e->getMessage());
				continue;
			}

			if (is_null($related)) {
				$data[$include] = null;
			} else if ($related instanceof \Pair\Orm\Collection) {
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
	 * @param	array					$config		Resource config.
	 * @param	string					$include	Include name.
	 * @return	array<int|string, array<string, mixed>>
	 */
	private function transformIncludedCollection(\Pair\Orm\Collection $collection, array $config, string $include): array {

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
	 * Transform an included relation through explicit mapping rules.
	 *
	 * @param	array<string, mixed>	$config	Resource config.
	 * @return	array<string, mixed>
	 */
	private function transformIncludedRecord(ActiveRecord $record, array $config, string $include): array {

		$includeReadModels = $config['includeReadModels'] ?? [];

		if (isset($includeReadModels[$include])) {
			return RecordMapper::map($record, $includeReadModels[$include])->toArray();
		}

		$includeResources = $config['includeResources'] ?? [];

		if (isset($includeResources[$include]) and class_exists($includeResources[$include])) {
			$resourceClass = $includeResources[$include];
			return (new $resourceClass($record))->toArray();
		}

		if (method_exists($record::class, 'getApiConfig')) {

			$relatedConfig = $record::getApiConfig();

			if (!empty($relatedConfig['readModel'])) {
				return RecordMapper::map($record, $relatedConfig['readModel'])->toArray();
			}

			if (!empty($relatedConfig['resource']) and class_exists($relatedConfig['resource'])) {
				$resourceClass = $relatedConfig['resource'];
				return (new $resourceClass($record))->toArray();
			}

		}

		throw new \LogicException('Include "' . $include . '" requires an explicit readModel or resource mapping');

	}

	/**
	 * Update a resource by its primary key. Validates the request body, updates the
	 * ActiveRecord object, and returns it through the configured Resource transformer.
	 *
	 * @param	array		$resource	Resource configuration.
	 * @param	string|int	$id			Primary key value.
	 */
	private function updateResource(array $resource, string|int $id): ResponseInterface {

		$class = $resource['class'];
		$config = $resource['config'];
		$object = $class::find($id);

		if (!$object) {
			ApiResponse::error('NOT_FOUND', ['class' => $class, 'id' => $id]);
		}

		// validate content type
		if (!$this->request->isJson()) {
			ApiResponse::error('UNSUPPORTED_MEDIA_TYPE', ['expected' => 'application/json']);
		}

		$data = $this->request->json();

		if (is_null($data)) {
			ApiResponse::error('BAD_REQUEST', ['detail' => 'Invalid or empty JSON body']);
		}

		// apply validation rules if configured
		$rules = $config['rules']['update'] ?? [];

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
			ApiResponse::error('INTERNAL_SERVER_ERROR', ['detail' => 'Failed to update resource']);
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
