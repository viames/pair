<?php

namespace Pair\Api\OpenApi;

use Pair\Api\CrudController;
use Pair\Api\CrudResourceConfig;

/**
 * Generates a complete OpenAPI 3.1 specification from registered CRUD resources
 * and custom endpoint definitions. The spec can be served as JSON or YAML from
 * an API endpoint for auto-discovery and SDK generation.
 *
 * Usage:
 *   $spec = new SpecGenerator('My API', '1.0.0');
 *   $spec->setDescription('REST API for my application');
 *   $spec->setServer('https://api.example.com');
 *   $spec->addSecurityScheme('bearerAuth', 'http', ['scheme' => 'bearer']);
 *
 *   // Auto-generate from CrudController resources:
 *   $spec->addCrudResources($apiController);
 *
 *   // Add custom endpoints manually:
 *   $spec->addPath('/api/auth/login', 'post', [...]);
 *
 *   // Output the spec:
 *   $json = $spec->toJson();
 */
class SpecGenerator {

	/**
	 * Custom endpoint paths added manually.
	 *
	 * @var array<string, array<string, array>>
	 */
	private array $customPaths = [];

	/**
	 * Custom component schemas added manually.
	 *
	 * @var array<string, array>
	 */
	private array $customSchemas = [];

	/**
	 * API description.
	 */
	private string $description = '';

	/**
	 * OpenAPI info contact object.
	 *
	 * @var array<string, string>|null
	 */
	private ?array $contact = null;

	/**
	 * OpenAPI info license object.
	 *
	 * @var array<string, string>|null
	 */
	private ?array $license = null;

	/**
	 * CRUD resources to document.
	 *
	 * @var array<string, array{class: string, config: CrudResourceConfig|array, basePath?: string}>
	 */
	private array $resources = [];

	/**
	 * Schema generator instance.
	 */
	private SchemaGenerator $schemaGenerator;

	/**
	 * Security schemes.
	 *
	 * @var array<string, array>
	 */
	private array $securitySchemes = [];

	/**
	 * Server URLs.
	 *
	 * @var array<int, array{url: string, description: string}>
	 */
	private array $servers = [];

	/**
	 * Tags for endpoint grouping.
	 *
	 * @var array<int, array{name: string, description: string}>
	 */
	private array $tags = [];

	/**
	 * API title.
	 */
	private string $title;

	/**
	 * API version.
	 */
	private string $version;

	/**
	 * Create a new SpecGenerator.
	 *
	 * @param	string	$title		API title.
	 * @param	string	$version	API version (semver).
	 */
	public function __construct(string $title, string $version = '1.0.0') {

		$this->title = $title;
		$this->version = $version;
		$this->schemaGenerator = new SchemaGenerator();

	}

	/**
	 * Add a contact info to the spec.
	 *
	 * @param	string		$name	Contact name.
	 * @param	string|null	$email	Contact email.
	 * @param	string|null	$url	Contact URL.
	 */
	public function addContact(string $name, ?string $email = null, ?string $url = null): void {

		$this->contact = ['name' => $name];

		if ($email) {
			$this->contact['email'] = $email;
		}

		if ($url) {
			$this->contact['url'] = $url;
		}

	}

	/**
	 * Register all CRUD resources from a CrudController instance.
	 *
	 * @param	CrudController	$controller	The controller with registered resources.
	 * @param	string			$basePath	URL base path for resources (default '/api').
	 */
	public function addCrudResources(CrudController $controller, string $basePath = '/api'): void {

		$slugs = $controller->getRegisteredResources();

		foreach ($slugs as $slug) {

			$config = $controller->getResourceConfig($slug);

			if ($config) {
				$this->resources[$slug] = [
					'class'    => $config['class'],
					'config'   => CrudResourceConfig::from($config['config']),
					'basePath' => $basePath,
				];
			}

		}

	}

	/**
	 * Add a license to the spec.
	 *
	 * @param	string		$name	License name.
	 * @param	string|null	$url	License URL.
	 */
	public function addLicense(string $name, ?string $url = null): void {

		$this->license = ['name' => $name];

		if ($url) {
			$this->license['url'] = $url;
		}

	}

	/**
	 * Add a custom endpoint path to the spec.
	 *
	 * @param	string	$path		URL path (e.g. '/api/auth/login').
	 * @param	string	$method		HTTP method (get, post, put, delete).
	 * @param	array	$operation	OpenAPI operation object.
	 */
	public function addPath(string $path, string $method, array $operation): void {

		$this->customPaths[$path][strtolower($method)] = $operation;

	}

	/**
	 * Add the standard Pair mobile authentication paths and schemas.
	 *
	 * @param	string	$basePath	API base path, for example `/api` or `/api/v1`.
	 */
	public function addMobileAuthPaths(string $basePath = '/api'): void {

		$basePath = trim($basePath, '/');
		$basePath = ('' === $basePath) ? '' : '/' . $basePath;
		$tag = 'Authentication';

		$this->ensureTag($tag, 'Mobile authentication');

		// Keep an application-defined bearer scheme when the spec already has one.
		if (!isset($this->securitySchemes['bearerAuth'])) {
			$this->addSecurityScheme('bearerAuth', 'http', [
				'scheme'       => 'bearer',
				'bearerFormat' => 'Opaque',
			]);
		}

		$this->addMobileAuthSchemas();

		$this->addPath($basePath . '/auth/login', 'post', [
			'tags'        => [$tag],
			'summary'     => 'Log in and issue a mobile bearer session',
			'operationId' => 'mobileAuthLogin',
			'requestBody' => $this->jsonRequestBody('PairAuthLoginRequest'),
			'responses'   => [
				'200' => $this->jsonResponse('PairAuthSessionEnvelope', 'Authenticated mobile session'),
				'400' => ['description' => 'Missing or invalid fields'],
				'401' => ['description' => 'Invalid credentials'],
			],
		]);

		$this->addPath($basePath . '/auth/register', 'post', [
			'tags'        => [$tag],
			'summary'     => 'Register and issue a mobile bearer session',
			'operationId' => 'mobileAuthRegister',
			'requestBody' => $this->jsonRequestBody('PairAuthRegisterRequest'),
			'responses'   => [
				'201' => $this->jsonResponse('PairAuthSessionEnvelope', 'Registered mobile session'),
				'400' => ['description' => 'Missing or invalid fields'],
				'501' => ['description' => 'Registration hook is not implemented'],
			],
		]);

		$this->addPath($basePath . '/auth/refresh', 'post', [
			'tags'        => [$tag],
			'summary'     => 'Rotate a refresh token and issue a fresh mobile bearer session',
			'operationId' => 'mobileAuthRefresh',
			'requestBody' => $this->jsonRequestBody('PairAuthRefreshRequest'),
			'responses'   => [
				'200' => $this->jsonResponse('PairAuthSessionEnvelope', 'Refreshed mobile session'),
				'400' => ['description' => 'Missing refresh token'],
				'401' => ['description' => 'Invalid refresh token'],
			],
		]);

		$this->addPath($basePath . '/auth/me', 'get', [
			'tags'        => [$tag],
			'summary'     => 'Return the current mobile user snapshot',
			'operationId' => 'mobileAuthMe',
			'security'    => [['bearerAuth' => []]],
			'responses'   => [
				'200' => $this->jsonResponse('PairCurrentUserEnvelope', 'Current user snapshot'),
				'401' => ['description' => 'Invalid or missing bearer token'],
			],
		]);

		$this->addPath($basePath . '/auth/logout', 'post', [
			'tags'        => [$tag],
			'summary'     => 'Revoke the current mobile bearer session',
			'operationId' => 'mobileAuthLogout',
			'security'    => [['bearerAuth' => []]],
			'requestBody' => [
				'required' => false,
				'content'  => [
					'application/json' => [
						'schema' => ['$ref' => '#/components/schemas/PairAuthLogoutRequest'],
					],
				],
			],
			'responses'   => [
				'200' => $this->jsonResponse('PairEmptyDataEnvelope', 'Logout completed'),
				'401' => ['description' => 'Invalid or missing bearer token'],
			],
		]);

	}

	/**
	 * Add a custom component schema to the generated spec.
	 */
	public function addSchema(string $name, array $schema): void {

		$name = trim($name);

		if ('' === $name) {
			throw new \InvalidArgumentException('OpenAPI schema name cannot be empty');
		}

		$this->customSchemas[$name] = $schema;

	}

	/**
	 * Add a security scheme to the spec.
	 *
	 * @param	string	$name	Scheme name (e.g. 'bearerAuth').
	 * @param	string	$type	Scheme type (http, apiKey, oauth2, openIdConnect).
	 * @param	array	$config	Additional configuration (scheme, bearerFormat, in, name, etc.).
	 */
	public function addSecurityScheme(string $name, string $type, array $config = []): void {

		$this->securitySchemes[$name] = array_merge(['type' => $type], $config);

	}

	/**
	 * Add a server URL to the spec.
	 *
	 * @param	string	$url			Server URL.
	 * @param	string	$description	Server description.
	 */
	public function addServer(string $url, string $description = ''): void {

		$this->servers[] = [
			'url'         => $url,
			'description' => $description,
		];

	}

	/**
	 * Add a tag for endpoint grouping.
	 *
	 * @param	string	$name			Tag name.
	 * @param	string	$description	Tag description.
	 */
	public function addTag(string $name, string $description = ''): void {

		$this->tags[] = [
			'name'        => $name,
			'description' => $description,
		];

	}

	/**
	 * Build the complete OpenAPI 3.1 specification as an array.
	 *
	 * @return array The complete OpenAPI spec.
	 */
	public function build(): array {

		$spec = [
			'openapi' => '3.1.0',
			'info'    => $this->buildInfo(),
		];

		if (count($this->servers)) {
			$spec['servers'] = $this->servers;
		}

		if (count($this->tags)) {
			$spec['tags'] = $this->tags;
		}

		// build paths
		$paths = $this->customPaths;

		foreach ($this->resources as $slug => $resource) {
			$paths = array_merge($paths, $this->buildCrudPaths($slug, $resource));
		}

		$spec['paths'] = $paths;

		// build components
		$components = [];

		// schemas
		$schemas = $this->buildSchemas();

		if (count($schemas)) {
			$components['schemas'] = $schemas;
		}

		// security schemes
		if (count($this->securitySchemes)) {
			$components['securitySchemes'] = $this->securitySchemes;
		}

		if (count($components)) {
			$spec['components'] = $components;
		}

		return $spec;

	}

	/**
	 * Register the reusable schemas used by the standard mobile auth endpoints.
	 */
	private function addMobileAuthSchemas(): void {

		$this->addSchema('PairAuthUser', [
			'type' => 'object',
			'properties' => [
				'id'       => ['type' => 'integer'],
				'username' => ['type' => ['string', 'null']],
				'email'    => ['type' => ['string', 'null'], 'format' => 'email'],
				'name'     => ['type' => ['string', 'null']],
				'surname'  => ['type' => ['string', 'null']],
			],
			'required' => ['id'],
			'additionalProperties' => true,
		]);

		$this->addSchema('PairAuthLoginRequest', [
			'type' => 'object',
			'properties' => [
				'email'       => ['type' => 'string', 'format' => 'email'],
				'username'    => ['type' => 'string'],
				'password'    => ['type' => 'string', 'format' => 'password'],
				'remember_me' => ['type' => 'boolean', 'default' => true],
				'device_name' => ['type' => 'string', 'maxLength' => 120],
			],
			'required' => ['password'],
			'anyOf' => [
				['required' => ['email']],
				['required' => ['username']],
			],
			'additionalProperties' => true,
		]);

		$this->addSchema('PairAuthRegisterRequest', [
			'type' => 'object',
			'properties' => [
				'name'             => ['type' => 'string'],
				'email'            => ['type' => 'string', 'format' => 'email'],
				'password'         => ['type' => 'string', 'format' => 'password'],
				'privacy_accepted' => ['type' => 'boolean'],
				'remember_me'      => ['type' => 'boolean', 'default' => true],
				'device_name'      => ['type' => 'string', 'maxLength' => 120],
			],
			'required' => ['name', 'email', 'password', 'privacy_accepted'],
			'additionalProperties' => true,
		]);

		$this->addSchema('PairAuthRefreshRequest', [
			'type' => 'object',
			'properties' => [
				'refresh_token' => ['type' => 'string'],
			],
			'required' => ['refresh_token'],
			'additionalProperties' => false,
		]);

		$this->addSchema('PairAuthLogoutRequest', [
			'type' => 'object',
			'properties' => [
				'refresh_token' => ['type' => 'string'],
			],
			'additionalProperties' => false,
		]);

		$this->addSchema('PairAuthSession', [
			'type' => 'object',
			'properties' => [
				'user'          => ['$ref' => '#/components/schemas/PairAuthUser'],
				'access_token'  => ['type' => 'string'],
				'refresh_token' => ['type' => 'string'],
				'expires_in'    => ['type' => 'integer', 'minimum' => 1],
				'expires_at'    => ['type' => 'string', 'format' => 'date-time'],
				'token_type'    => ['type' => 'string', 'enum' => ['Bearer']],
				'context'       => ['type' => 'object', 'additionalProperties' => true],
			],
			'required' => ['user', 'access_token', 'expires_in', 'expires_at', 'token_type'],
			'additionalProperties' => true,
		]);

		$this->addSchema('PairAuthSessionEnvelope', $this->dataEnvelopeSchema('PairAuthSession'));
		$this->addSchema('PairCurrentUserEnvelope', [
			'type' => 'object',
			'properties' => [
				'data' => [
					'type' => 'object',
					'properties' => [
						'user'    => ['$ref' => '#/components/schemas/PairAuthUser'],
						'context' => ['type' => 'object', 'additionalProperties' => true],
					],
					'required' => ['user'],
					'additionalProperties' => true,
				],
			],
			'required' => ['data'],
			'additionalProperties' => false,
		]);
		$this->addSchema('PairEmptyDataEnvelope', [
			'type' => 'object',
			'properties' => [
				'data' => [
					'type' => 'object',
					'additionalProperties' => false,
				],
			],
			'required' => ['data'],
			'additionalProperties' => false,
		]);

	}

	/**
	 * Build a standard JSON request body object referencing a component schema.
	 */
	private function jsonRequestBody(string $schemaName): array {

		return [
			'required' => true,
			'content'  => [
				'application/json' => [
					'schema' => ['$ref' => '#/components/schemas/' . $schemaName],
				],
			],
		];

	}

	/**
	 * Build a standard JSON response object referencing a component schema.
	 */
	private function jsonResponse(string $schemaName, string $description): array {

		return [
			'description' => $description,
			'content' => [
				'application/json' => [
					'schema' => ['$ref' => '#/components/schemas/' . $schemaName],
				],
			],
		];

	}

	/**
	 * Build a standard Pair data-envelope schema around one component schema.
	 */
	private function dataEnvelopeSchema(string $schemaName): array {

		return [
			'type' => 'object',
			'properties' => [
				'data' => ['$ref' => '#/components/schemas/' . $schemaName],
			],
			'required' => ['data'],
			'additionalProperties' => false,
		];

	}

	/**
	 * Add a tag when it does not already exist.
	 */
	private function ensureTag(string $name, string $description = ''): void {

		foreach ($this->tags as $tag) {
			if (($tag['name'] ?? null) === $name) {
				return;
			}
		}

		$this->addTag($name, $description);

	}

	/**
	 * Build CRUD paths for a resource.
	 *
	 * @param	string	$slug		Resource slug.
	 * @param	array	$resource	Resource config with class, config, basePath.
	 * @return	array<string, array>	Paths array for this resource.
	 */
	private function buildCrudPaths(string $slug, array $resource): array {

		$basePath = $resource['basePath'];
		$config = CrudResourceConfig::from($resource['config']);
		$schemaName = $this->slugToSchemaName($slug);
		$tag = ucfirst($slug);

		// ensure tag exists
		$tagExists = false;
		foreach ($this->tags as $t) {
			if ($t['name'] === $tag) {
				$tagExists = true;
				break;
			}
		}
		if (!$tagExists) {
			$this->tags[] = ['name' => $tag, 'description' => $tag . ' management'];
		}

		$paths = [];

		// collection path: GET (list), POST (create)
		$collectionPath = $basePath . '/' . $slug;
		$paths[$collectionPath] = [];

		// GET - list
		$listOp = [
			'tags'        => [$tag],
			'summary'     => 'List ' . $slug,
			'operationId' => 'list' . $schemaName,
			'parameters'  => $this->buildListParameters($config),
			'responses'   => [
				'200' => [
					'description' => 'Paginated list of ' . $slug,
					'content' => [
						'application/json' => [
							'schema' => [
								'type'       => 'object',
								'properties' => [
									'data' => [
										'type'  => 'array',
										'items' => ['$ref' => '#/components/schemas/' . $schemaName],
									],
									'meta' => [
										'type'       => 'object',
										'properties' => [
											'page'    => ['type' => 'integer'],
											'perPage' => ['type' => 'integer'],
											'total'   => ['type' => 'integer'],
											'lastPage'=> ['type' => 'integer'],
										],
									],
								],
							],
						],
					],
				],
			],
		];

		$paths[$collectionPath]['get'] = $listOp;

		// POST - create
		$createOp = [
			'tags'        => [$tag],
			'summary'     => 'Create a ' . rtrim($slug, 's'),
			'operationId' => 'create' . $schemaName,
			'requestBody' => [
				'required' => true,
				'content'  => [
					'application/json' => [
						'schema' => ['$ref' => '#/components/schemas/' . $schemaName . 'Create'],
					],
				],
			],
			'responses' => [
				'201' => [
					'description' => 'Resource created successfully',
					'content' => [
						'application/json' => [
							'schema' => ['$ref' => '#/components/schemas/' . $schemaName],
						],
					],
				],
				'400' => ['description' => 'Validation error'],
			],
		];

		$paths[$collectionPath]['post'] = $createOp;

		// item path: GET (show), PUT (update), DELETE (delete)
		$itemPath = $basePath . '/' . $slug . '/{id}';
		$idParam = [
			'name'     => 'id',
			'in'       => 'path',
			'required' => true,
			'schema'   => ['type' => 'string'],
			'description' => 'Resource ID',
		];

		// GET - show
		$paths[$itemPath]['get'] = [
			'tags'        => [$tag],
			'summary'     => 'Get a ' . rtrim($slug, 's'),
			'operationId' => 'get' . $schemaName,
			'parameters'  => [$idParam],
			'responses'   => [
				'200' => [
					'description' => 'Single resource',
					'content' => [
						'application/json' => [
							'schema' => ['$ref' => '#/components/schemas/' . $schemaName],
						],
					],
				],
				'404' => ['description' => 'Resource not found'],
			],
		];

		// PUT - update
		$paths[$itemPath]['put'] = [
			'tags'        => [$tag],
			'summary'     => 'Update a ' . rtrim($slug, 's'),
			'operationId' => 'update' . $schemaName,
			'parameters'  => [$idParam],
			'requestBody' => [
				'required' => true,
				'content'  => [
					'application/json' => [
						'schema' => ['$ref' => '#/components/schemas/' . $schemaName . 'Update'],
					],
				],
			],
			'responses' => [
				'200' => [
					'description' => 'Resource updated successfully',
					'content' => [
						'application/json' => [
							'schema' => ['$ref' => '#/components/schemas/' . $schemaName],
						],
					],
				],
				'404' => ['description' => 'Resource not found'],
				'400' => ['description' => 'Validation error'],
			],
		];

		// DELETE - delete
		$paths[$itemPath]['delete'] = [
			'tags'        => [$tag],
			'summary'     => 'Delete a ' . rtrim($slug, 's'),
			'operationId' => 'delete' . $schemaName,
			'parameters'  => [$idParam],
			'responses'   => [
				'204' => ['description' => 'Resource deleted successfully'],
				'404' => ['description' => 'Resource not found'],
				'409' => ['description' => 'This resource is referenced and cannot be deleted'],
			],
		];

		return $paths;

	}

	/**
	 * Build the info section of the spec.
	 *
	 * @return array OpenAPI info object.
	 */
	private function buildInfo(): array {

		$info = [
			'title'   => $this->title,
			'version' => $this->version,
		];

		if ($this->description) {
			$info['description'] = $this->description;
		}

		if ($this->contact) {
			$info['contact'] = $this->contact;
		}

		if ($this->license) {
			$info['license'] = $this->license;
		}

		return $info;

	}

	/**
	 * Build query parameters for list endpoints from the resource config.
	 *
	 * @param	CrudResourceConfig	$config	API config from the model.
	 * @return	array	List of OpenAPI parameter objects.
	 */
	private function buildListParameters(CrudResourceConfig $config): array {

		$params = [];

		// pagination
		$params[] = [
			'name'        => 'page',
			'in'          => 'query',
			'schema'      => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
			'description' => 'Page number',
		];

		$params[] = [
			'name'        => 'perPage',
			'in'          => 'query',
			'schema'      => [
				'type'    => 'integer',
				'default' => $config->perPage(),
				'minimum' => 1,
				'maximum' => $config->maxPerPage(),
			],
			'description' => 'Items per page',
		];

		// sort
		$sortable = $config->sortable();

		if (count($sortable)) {
			$params[] = [
				'name'        => 'sort',
				'in'          => 'query',
				'schema'      => ['type' => 'string'],
				'description' => 'Comma-separated sort fields. Prefix with - for DESC. Allowed: ' . implode(', ', $sortable),
				'example'     => '-' . $sortable[0],
			];
		}

		// search
		$searchable = $config->searchable();

		if (count($searchable)) {
			$params[] = [
				'name'        => 'search',
				'in'          => 'query',
				'schema'      => ['type' => 'string'],
				'description' => 'Search keyword across: ' . implode(', ', $searchable),
			];
		}

		// fields (sparse fieldsets)
		$params[] = [
			'name'        => 'fields',
			'in'          => 'query',
			'schema'      => ['type' => 'string'],
			'description' => 'Comma-separated list of fields to include in response',
		];

		// includes
		$includes = $config->includes();

		if (count($includes)) {
			$params[] = [
				'name'        => 'include',
				'in'          => 'query',
				'schema'      => ['type' => 'string'],
				'description' => 'Comma-separated relationships to include. Allowed: ' . implode(', ', $includes),
			];
		}

		// filter parameters
		$filterable = $config->filterable();

		foreach ($filterable as $field) {
			$params[] = [
				'name'        => 'filter[' . $field . ']',
				'in'          => 'query',
				'schema'      => ['type' => 'string'],
				'description' => 'Filter by ' . $field . '. Supports: exact match, !value (not equal), >=N, <=N, >N, <N, val1,val2 (IN), null, !null',
			];
		}

		return $params;

	}

	/**
	 * Build all component schemas from registered resources.
	 *
	 * @return array<string, array> Schema definitions.
	 */
	private function buildSchemas(): array {

		$schemas = $this->customSchemas;

		foreach ($this->resources as $slug => $resource) {

			$schemaName = $this->slugToSchemaName($slug);
			$class = $resource['class'];
			$config = CrudResourceConfig::from($resource['config']);
			$responseClass = $this->resolveResponseSchemaClass($class, $config);

			// The response schema must follow the explicit read contract when available.
			$schemas[$schemaName] = $this->schemaGenerator->generate($responseClass);

			// create schema
			$createRules = $config->createRules();
			$schemas[$schemaName . 'Create'] = $this->schemaGenerator->generateCreateSchema($class, $createRules);

			// update schema
			$updateRules = $config->updateRules();
			$schemas[$schemaName . 'Update'] = $this->schemaGenerator->generateUpdateSchema($class, $updateRules);

		}

		return $schemas;

	}

	/**
	 * Resolve the class that defines the public response schema for a CRUD resource.
	 *
	 * @param	string				$modelClass	Persistence model class.
	 * @param	CrudResourceConfig	$config		CRUD resource configuration.
	 */
	private function resolveResponseSchemaClass(string $modelClass, CrudResourceConfig $config): string {

		$readModelClass = $config->readModel();

		if (is_string($readModelClass) and class_exists($readModelClass)) {
			return $readModelClass;
		}

		$resourceClass = $config->resource();

		if (is_string($resourceClass) and class_exists($resourceClass) and is_callable([$resourceClass, 'openApiSchema'])) {
			return $resourceClass;
		}

		return $modelClass;

	}

	/**
	 * Set the API description.
	 *
	 * @param	string	$description	API description text.
	 */
	public function setDescription(string $description): void {

		$this->description = $description;

	}

	/**
	 * Convert a resource slug to a PascalCase schema name.
	 *
	 * @param	string	$slug	Resource slug (e.g. 'faqs', 'user-profiles').
	 * @return	string	PascalCase schema name (e.g. 'Faq', 'UserProfile').
	 */
	private function slugToSchemaName(string $slug): string {

		// remove trailing 's' for singular form
		$singular = rtrim($slug, 's');

		// convert kebab-case to PascalCase
		return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $singular)));

	}

	/**
	 * Export the spec as a JSON string.
	 *
	 * @param	int	$options	JSON encoding options (default: pretty print + unescaped slashes).
	 * @return	string	JSON string.
	 */
	public function toJson(int $options = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string {

		return json_encode($this->build(), $options);

	}

}
