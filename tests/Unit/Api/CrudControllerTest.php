<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Api\ApiErrorResponse;
use Pair\Api\CrudResourceConfig;
use Pair\Api\Request;
use Pair\Core\Application;
use Pair\Core\Logger;
use Pair\Http\JsonResponse;
use Pair\Tests\Support\FakeCrudController;
use Pair\Tests\Support\FakeCrudDatabase;
use Pair\Tests\Support\FakeCrudDeletableRecord;
use Pair\Tests\Support\FakeCrudIncludePreloader;
use Pair\Tests\Support\FakeCrudExposeableModel;
use Pair\Tests\Support\FakeCrudIncludeReadModel;
use Pair\Tests\Support\FakeCrudReadModel;
use Pair\Tests\Support\FakeCrudRecord;
use Pair\Tests\Support\FakeCrudResource;
use Pair\Tests\Support\TestCase;
use Pair\Orm\Database;
use Pair\Orm\Collection;

/**
 * Covers the parts of CrudController that can be exercised without bootstrapping the full MVC stack.
 */
class CrudControllerTest extends TestCase {

	/**
	 * Define the minimal runtime constants needed when the CRUD dispatch test instantiates Router.
	 */
	protected function setUp(): void {

		parent::setUp();

		if (!defined('URL_PATH')) {
			define('URL_PATH', null);
		}

		if (!defined('BASE_TIMEZONE')) {
			define('BASE_TIMEZONE', date_default_timezone_get() ?: 'UTC');
		}

		$this->resetDatabaseSingleton();
		$this->setApplicationState();
		$this->resetLoggerSingleton();
		$_ENV['PAIR_LOGGER_DISABLED'] = true;
		\Pair\Core\Router::getInstance();

	}

	/**
	 * Reset static fake find results and request globals after each test so CRUD dispatch stays isolated.
	 */
	protected function tearDown(): void {

		FakeCrudRecord::resetFindResults();
		FakeCrudDeletableRecord::resetFindResult();
		FakeCrudIncludePreloader::reset();
		$this->setInaccessibleProperty(\Pair\Core\Router::getInstance(), 'vars', []);
		$_GET = [];
		unset($_SERVER['CONTENT_TYPE'], $_SERVER['REQUEST_METHOD']);
		$this->resetDatabaseSingleton();
		$this->resetApplicationSingleton();
		$this->resetLoggerSingleton();

		parent::tearDown();

	}

	/**
	 * Verify resource registration pulls the merged ApiExposable configuration from the model class.
	 */
	public function testRegisterCrudResourceUsesMergedApiConfigFromModel(): void {

		$controller = $this->newCrudController();
		$controller->registerCrudResource('users', FakeCrudExposeableModel::class);

		$config = $controller->getResourceConfig('users');

		$this->assertSame(['users'], $controller->getRegisteredResources());
		$this->assertSame(FakeCrudExposeableModel::class, $config['class']);
		$this->assertSame(FakeCrudReadModel::class, $config['config']['readModel']);
		$this->assertSame(['name'], $config['config']['searchable']);
		$this->assertSame(['createdAt'], $config['config']['sortable']);
		$this->assertSame(['status'], $config['config']['filterable']);
		$this->assertSame(['group', 'tags'], $config['config']['includes']);
		$this->assertSame(FakeCrudIncludeReadModel::class, $config['config']['includeReadModels']['group']);
		$this->assertSame(FakeCrudIncludeReadModel::class, $config['config']['includeReadModels']['tags']);
		$this->assertSame(15, $config['config']['perPage']);
		$this->assertSame(30, $config['config']['maxPerPage']);
		$this->assertSame('-createdAt', $config['config']['defaultSort']);
		$this->assertSame(['name' => 'required|string'], $config['config']['rules']['create']);
		$this->assertSame(['name' => 'string'], $config['config']['rules']['update']);

	}

	/**
	 * Verify resource registration accepts a typed config while keeping public config inspection array-compatible.
	 */
	public function testRegisterCrudResourceAcceptsTypedConfig(): void {

		$controller = $this->newCrudController();
		$controller->registerCrudResource('users', FakeCrudRecord::class, CrudResourceConfig::fromArray([
			'readModel' => FakeCrudReadModel::class,
			'perPage' => 12,
		]));

		$config = $controller->getResourceConfig('users');

		$this->assertSame(FakeCrudRecord::class, $config['class']);
		$this->assertSame(FakeCrudReadModel::class, $config['config']['readModel']);
		$this->assertSame(12, $config['config']['perPage']);
		$this->assertSame(100, $config['config']['maxPerPage']);

	}

	/**
	 * Verify transformResource uses the configured read model, sparse fields, and singular includes.
	 */
	public function testTransformResourceUsesReadModelFieldsAndIncludes(): void {

		$controller = $this->newCrudController();
		$group = $this->newCrudRecord()->seed(['id' => 10, 'name' => 'Admins']);
		$record = $this->newCrudRecord()->seed([
			'id' => 7,
			'name' => 'Alice',
			'email' => 'alice@example.test',
		], [
			'group' => $group,
		]);

		$data = $this->invokeInaccessibleMethod($controller, 'transformResource', [
			$record,
			[
				'readModel' => FakeCrudReadModel::class,
				'includeReadModels' => ['group' => FakeCrudIncludeReadModel::class],
			],
			['identifier'],
			['group'],
		]);

		$this->assertSame([
			'identifier' => 7,
			'group' => [
				'id' => 10,
				'name' => 'Admins',
			],
		], $data);

	}

	/**
	 * Verify transformCollection applies the configured read model to every item in the array.
	 */
	public function testTransformCollectionUsesReadModelForEveryItem(): void {

		$controller = $this->newCrudController();
		$records = [
			$this->newCrudRecord()->seed(['id' => 1, 'name' => 'Alice', 'email' => 'a@example.test']),
			$this->newCrudRecord()->seed(['id' => 2, 'name' => 'Bob', 'email' => 'b@example.test']),
		];

		$data = $this->invokeInaccessibleMethod($controller, 'transformCollection', [
			$records,
			['readModel' => FakeCrudReadModel::class],
			['identifier', 'label'],
			[],
		]);

		$this->assertSame([
			['identifier' => 1, 'label' => 'ALICE'],
			['identifier' => 2, 'label' => 'BOB'],
		], $data);

	}

	/**
	 * Verify collection includes can be bulk-preloaded once before per-record transformation.
	 */
	public function testTransformCollectionUsesConfiguredIncludePreloader(): void {

		$controller = $this->newCrudController();
		$group = $this->newCrudRecord()->seed(['id' => 10, 'name' => 'Admins']);
		$record = $this->newCrudRecord()->seed([
			'id' => 7,
			'name' => 'Alice',
			'email' => 'alice@example.test',
		]);

		FakeCrudIncludePreloader::seed('group', 7, $group);

		$data = $this->invokeInaccessibleMethod($controller, 'transformCollection', [
			[$record],
			[
				'readModel' => FakeCrudReadModel::class,
				'includes' => ['group'],
				'includeReadModels' => ['group' => FakeCrudIncludeReadModel::class],
				'includePreloader' => FakeCrudIncludePreloader::class,
			],
			null,
			['group'],
		]);

		$this->assertSame(1, FakeCrudIncludePreloader::$calls);
		$this->assertSame(['group'], FakeCrudIncludePreloader::$lastIncludes);
		$this->assertSame([7], FakeCrudIncludePreloader::$lastParentIds);
		$this->assertSame([
			[
				'identifier' => 7,
				'label' => 'ALICE',
				'email' => 'alice@example.test',
				'group' => [
					'id' => 10,
					'name' => 'Admins',
				],
			],
		], $data);

	}

	/**
	 * Verify a configured preloader can return collection relations without changing output shape.
	 */
	public function testTransformCollectionUsesPreloadedCollectionIncludes(): void {

		$controller = $this->newCrudController();
		$record = $this->newCrudRecord()->seed([
			'id' => 7,
			'name' => 'Alice',
			'email' => 'alice@example.test',
		]);
		$tags = new Collection([
			$this->newCrudRecord()->seed(['id' => 20, 'name' => 'One']),
			$this->newCrudRecord()->seed(['id' => 21, 'name' => 'Two']),
		]);

		FakeCrudIncludePreloader::seed('tags', 7, $tags);

		$data = $this->invokeInaccessibleMethod($controller, 'transformCollection', [
			[$record],
			[
				'readModel' => FakeCrudReadModel::class,
				'includes' => ['tags'],
				'includeReadModels' => ['tags' => FakeCrudIncludeReadModel::class],
				'includePreloader' => FakeCrudIncludePreloader::class,
			],
			null,
			['tags'],
		]);

		$this->assertSame([
			[
				'identifier' => 7,
				'label' => 'ALICE',
				'email' => 'alice@example.test',
				'tags' => [
					20 => ['id' => 20, 'name' => 'One'],
					21 => ['id' => 21, 'name' => 'Two'],
				],
			],
		], $data);

	}

	/**
	 * Verify an invalid preloader class fails explicitly.
	 */
	public function testTransformCollectionRejectsMissingIncludePreloader(): void {

		$controller = $this->newCrudController();
		$record = $this->newCrudRecord()->seed(['id' => 7, 'name' => 'Alice']);

		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage('CRUD include preloader "MissingCrudIncludePreloader" does not exist');

		$this->invokeInaccessibleMethod($controller, 'transformCollection', [
			[$record],
			[
				'readModel' => FakeCrudReadModel::class,
				'includePreloader' => 'MissingCrudIncludePreloader',
			],
			null,
			['group'],
		]);

	}

	/**
	 * Verify configured preloader classes must implement the public contract.
	 */
	public function testTransformCollectionRejectsInvalidIncludePreloaderContract(): void {

		$controller = $this->newCrudController();
		$record = $this->newCrudRecord()->seed(['id' => 7, 'name' => 'Alice']);

		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage('must implement ' . \Pair\Api\CrudIncludePreloader::class);

		$this->invokeInaccessibleMethod($controller, 'transformCollection', [
			[$record],
			[
				'readModel' => FakeCrudReadModel::class,
				'includePreloader' => \stdClass::class,
			],
			null,
			['group'],
		]);

	}

	/**
	 * Verify collection includes are serialized as nested arrays on the output payload.
	 */
	public function testLoadIncludesSerializesCollectionRelations(): void {

		$controller = $this->newCrudController();
		$tags = new Collection([
			$this->newCrudRecord()->seed(['id' => 20, 'name' => 'One']),
			$this->newCrudRecord()->seed(['id' => 21, 'name' => 'Two']),
		]);
		$record = $this->newCrudRecord()->seed([
			'id' => 7,
			'name' => 'Alice',
		], [
			'tags' => $tags,
		]);

		$data = $this->invokeInaccessibleMethod($controller, 'loadIncludes', [
			$record,
			['id' => 7],
			[
				'includes' => ['tags'],
				'includeReadModels' => ['tags' => FakeCrudIncludeReadModel::class],
			],
			['tags'],
		]);

		$this->assertSame([
			'id' => 7,
			'tags' => [
				20 => ['id' => 20, 'name' => 'One'],
				21 => ['id' => 21, 'name' => 'Two'],
			],
		], $data);

	}

	/**
	 * Verify legacy Resource adapters still work as an explicit migration bridge.
	 */
	public function testTransformResourceStillSupportsLegacyResourceAdapters(): void {

		$controller = $this->newCrudController();
		$record = $this->newCrudRecord()->seed([
			'id' => 7,
			'name' => 'Alice',
			'email' => 'alice@example.test',
		]);

		$data = $this->invokeInaccessibleMethod($controller, 'transformResource', [
			$record,
			['resource' => FakeCrudResource::class],
		]);

		$this->assertSame([
			'identifier' => 7,
			'label' => 'ALICE',
			'email' => 'alice@example.test',
		], $data);

	}

	/**
	 * Verify missing explicit transformers no longer fall back to ActiveRecord::toArray().
	 */
	public function testTransformResourceRejectsImplicitActiveRecordSerialization(): void {

		$controller = $this->newCrudController();
		$record = $this->newCrudRecord()->seed([
			'id' => 7,
			'name' => 'Alice',
		]);

		$this->expectException(\LogicException::class);

		$this->invokeInaccessibleMethod($controller, 'transformResource', [
			$record,
			[],
		]);

	}

	/**
	 * Verify the migrated list-resource path returns an explicit JsonResponse with the standard paginated envelope.
	 */
	public function testCrudActionReturnsExplicitListResponse(): void {

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET = [
			'sort' => '-name',
			'page' => '2',
			'perPage' => '1',
		];

		$controller = $this->newCrudController();
		$database = $this->setDatabaseInstance();
		$database->useSqliteMemoryTable([
			['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.test'],
			['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.test'],
		]);

		$controller->registerCrudResource('users', FakeCrudRecord::class, [
			'readModel' => FakeCrudReadModel::class,
			'sortable' => ['name'],
		]);
		$this->setInaccessibleProperty($controller, 'request', new Request());

		$response = $controller->usersAction();

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame([
			'data' => [
				[
					'identifier' => 1,
					'label' => 'ALICE',
					'email' => 'alice@example.test',
				],
			],
			'meta' => [
				'page' => 2,
				'perPage' => 1,
				'total' => 2,
				'lastPage' => 2,
			],
		], $this->readJsonResponseProperty($response, 'payload'));
		$this->assertSame(200, $this->readJsonResponseProperty($response, 'httpCode'));

	}

	/**
	 * Verify the migrated delete-resource path returns an explicit JsonResponse and removes the matching record.
	 */
	public function testCrudActionReturnsExplicitDeleteResponse(): void {

		$_SERVER['REQUEST_METHOD'] = 'DELETE';

		$controller = $this->newCrudController();
		$record = new FakeCrudDeletableRecord();
		$router = \Pair\Core\Router::getInstance();

		FakeCrudDeletableRecord::seedFindResult($record);
		$controller->registerCrudResource('records', FakeCrudDeletableRecord::class, []);
		$this->setInaccessibleProperty($controller, 'request', new Request());
		$this->setInaccessibleProperty($router, 'vars', [0 => '5']);

		$response = $controller->recordsAction();

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertNull($this->readJsonResponseProperty($response, 'payload'));
		$this->assertSame(204, $this->readJsonResponseProperty($response, 'httpCode'));
		$this->assertTrue($record->deleteCalled);

	}

	/**
	 * Verify the migrated show-resource path returns an explicit JsonResponse through CrudController dispatch.
	 */
	public function testCrudActionReturnsExplicitShowResponse(): void {

		$controller = $this->newCrudController();
		$record = $this->newCrudRecord()->seed([
			'id' => 7,
			'name' => 'Alice',
			'email' => 'alice@example.test',
		]);
		$router = \Pair\Core\Router::getInstance();

		$controller->registerCrudResource('users', FakeCrudRecord::class, [
			'readModel' => FakeCrudReadModel::class,
		]);
		$this->setInaccessibleProperty($controller, 'request', new Request());
		$this->setInaccessibleProperty($router, 'vars', [0 => '7']);
		FakeCrudRecord::seedFindResult(7, $record);

		$response = $controller->usersAction();

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame([
			'identifier' => 7,
			'label' => 'ALICE',
			'email' => 'alice@example.test',
		], $this->readJsonResponseProperty($response, 'payload'));
		$this->assertSame(200, $this->readJsonResponseProperty($response, 'httpCode'));

	}

	/**
	 * Verify the migrated create-resource path returns an explicit JsonResponse and uses the fake database insert bridge.
	 */
	public function testCrudActionReturnsExplicitCreateResponse(): void {

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['CONTENT_TYPE'] = 'application/json';

		$controller = $this->newCrudController();
		$database = $this->setDatabaseInstance();
		$database->lastInsertId = '55';

		$controller->registerCrudResource('users', FakeCrudRecord::class, [
			'readModel' => FakeCrudReadModel::class,
		]);
		$this->setInaccessibleProperty($controller, 'request', $this->newJsonRequest([
			'name' => 'Alice',
			'email' => 'alice@example.test',
		]));

		$response = $controller->usersAction();

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame([
			'identifier' => 55,
			'label' => 'ALICE',
			'email' => 'alice@example.test',
		], $this->readJsonResponseProperty($response, 'payload'));
		$this->assertSame(201, $this->readJsonResponseProperty($response, 'httpCode'));
		$this->assertCount(1, $database->insertedObjects);
		$this->assertSame(FakeCrudRecord::TABLE_NAME, $database->insertedObjects[0]['table']);
		$this->assertSame('Alice', $database->insertedObjects[0]['object']->name);
		$this->assertSame('alice@example.test', $database->insertedObjects[0]['object']->email);

	}

	/**
	 * Verify the migrated create-resource path returns an explicit INVALID_FIELDS error response on validation failure.
	 */
	public function testCrudActionReturnsExplicitCreateValidationErrorResponse(): void {

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['CONTENT_TYPE'] = 'application/json';

		$controller = $this->newCrudController();

		$controller->registerCrudResource('users', FakeCrudRecord::class, [
			'rules' => [
				'create' => [
					'email' => 'required|email',
				],
			],
		]);
		$this->setInaccessibleProperty($controller, 'request', $this->newJsonRequest([
			'email' => 'not-an-email',
		]));

		$response = $controller->usersAction();

		$this->assertInstanceOf(ApiErrorResponse::class, $response);
		$this->assertSame('INVALID_FIELDS', $this->readApiErrorResponseProperty($response, 'errorCode'));
		$this->assertSame(400, $this->readApiErrorResponseProperty($response, 'httpCode'));
		$this->assertSame([
			'errors' => [
				'email' => 'The field email must be a valid email address',
			],
		], $this->readApiErrorResponseProperty($response, 'extra'));

	}

	/**
	 * Verify the migrated create-resource path returns an explicit media-type error response.
	 */
	public function testCrudActionReturnsExplicitCreateMediaTypeErrorResponse(): void {

		$_SERVER['REQUEST_METHOD'] = 'POST';

		$controller = $this->newCrudController();

		$controller->registerCrudResource('users', FakeCrudRecord::class, [
			'readModel' => FakeCrudReadModel::class,
		]);
		$this->setInaccessibleProperty($controller, 'request', new Request());

		$response = $controller->usersAction();

		$this->assertInstanceOf(ApiErrorResponse::class, $response);
		$this->assertSame('UNSUPPORTED_MEDIA_TYPE', $this->readApiErrorResponseProperty($response, 'errorCode'));
		$this->assertSame(415, $this->readApiErrorResponseProperty($response, 'httpCode'));
		$this->assertSame(['expected' => 'application/json'], $this->readApiErrorResponseProperty($response, 'extra'));

	}

	/**
	 * Verify the migrated update-resource path returns an explicit JsonResponse and uses the fake database update bridge.
	 */
	public function testCrudActionReturnsExplicitUpdateResponse(): void {

		$_SERVER['REQUEST_METHOD'] = 'PUT';
		$_SERVER['CONTENT_TYPE'] = 'application/json';

		$controller = $this->newCrudController();
		$database = $this->setDatabaseInstance();
		$record = $this->newCrudRecord()->seed([
			'id' => 7,
			'name' => 'Before',
			'email' => 'before@example.test',
		]);
		$router = \Pair\Core\Router::getInstance();

		FakeCrudRecord::seedFindResult(7, $record);
		$controller->registerCrudResource('users', FakeCrudRecord::class, [
			'readModel' => FakeCrudReadModel::class,
		]);
		$this->setInaccessibleProperty($controller, 'request', $this->newJsonRequest([
			'name' => 'Bob',
			'email' => 'bob@example.test',
		]));
		$this->setInaccessibleProperty($router, 'vars', [0 => '7']);

		$response = $controller->usersAction();

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame([
			'identifier' => 7,
			'label' => 'BOB',
			'email' => 'bob@example.test',
		], $this->readJsonResponseProperty($response, 'payload'));
		$this->assertSame(200, $this->readJsonResponseProperty($response, 'httpCode'));
		$this->assertCount(1, $database->updatedObjects);
		$this->assertSame(FakeCrudRecord::TABLE_NAME, $database->updatedObjects[0]['table']);
		$this->assertSame('Bob', $database->updatedObjects[0]['object']->name);
		$this->assertSame('bob@example.test', $database->updatedObjects[0]['object']->email);
		$this->assertSame(7, $database->updatedObjects[0]['key']->id);

	}

	/**
	 * Verify the migrated update-resource path returns an explicit INVALID_FIELDS error response on validation failure.
	 */
	public function testCrudActionReturnsExplicitUpdateValidationErrorResponse(): void {

		$_SERVER['REQUEST_METHOD'] = 'PUT';
		$_SERVER['CONTENT_TYPE'] = 'application/json';

		$controller = $this->newCrudController();
		$record = $this->newCrudRecord()->seed([
			'id' => 7,
			'name' => 'Before',
			'email' => 'before@example.test',
		]);
		$router = \Pair\Core\Router::getInstance();

		FakeCrudRecord::seedFindResult(7, $record);
		$controller->registerCrudResource('users', FakeCrudRecord::class, [
			'rules' => [
				'update' => [
					'email' => 'required|email',
				],
			],
		]);
		$this->setInaccessibleProperty($controller, 'request', $this->newJsonRequest([
			'email' => 'not-an-email',
		]));
		$this->setInaccessibleProperty($router, 'vars', [0 => '7']);

		$response = $controller->usersAction();

		$this->assertInstanceOf(ApiErrorResponse::class, $response);
		$this->assertSame('INVALID_FIELDS', $this->readApiErrorResponseProperty($response, 'errorCode'));
		$this->assertSame(400, $this->readApiErrorResponseProperty($response, 'httpCode'));
		$this->assertSame([
			'errors' => [
				'email' => 'The field email must be a valid email address',
			],
		], $this->readApiErrorResponseProperty($response, 'extra'));
		$this->assertSame('before@example.test', $record->email);

	}

	/**
	 * Verify the migrated update-resource path returns an explicit error when no resource ID is provided.
	 */
	public function testCrudActionReturnsExplicitUpdateMissingIdErrorResponse(): void {

		$_SERVER['REQUEST_METHOD'] = 'PUT';

		$controller = $this->newCrudController();

		$controller->registerCrudResource('users', FakeCrudRecord::class, [
			'readModel' => FakeCrudReadModel::class,
		]);
		$this->setInaccessibleProperty($controller, 'request', new Request());

		$response = $controller->usersAction();

		$this->assertInstanceOf(ApiErrorResponse::class, $response);
		$this->assertSame('BAD_REQUEST', $this->readApiErrorResponseProperty($response, 'errorCode'));
		$this->assertSame(400, $this->readApiErrorResponseProperty($response, 'httpCode'));
		$this->assertSame(['detail' => 'Resource ID is required'], $this->readApiErrorResponseProperty($response, 'extra'));

	}

	/**
	 * Verify the migrated show-resource path returns an explicit not-found error response.
	 */
	public function testCrudActionReturnsExplicitShowNotFoundErrorResponse(): void {

		$controller = $this->newCrudController();
		$router = \Pair\Core\Router::getInstance();

		$controller->registerCrudResource('users', FakeCrudRecord::class, [
			'readModel' => FakeCrudReadModel::class,
		]);
		$this->setInaccessibleProperty($controller, 'request', new Request());
		$this->setInaccessibleProperty($router, 'vars', [0 => '404']);

		$response = $controller->usersAction();

		$this->assertInstanceOf(ApiErrorResponse::class, $response);
		$this->assertSame('NOT_FOUND', $this->readApiErrorResponseProperty($response, 'errorCode'));
		$this->assertSame(404, $this->readApiErrorResponseProperty($response, 'httpCode'));
		$this->assertSame([
			'class' => FakeCrudRecord::class,
			'id' => '404',
		], $this->readApiErrorResponseProperty($response, 'extra'));

	}

	/**
	 * Verify the migrated delete-resource path returns an explicit conflict error response.
	 */
	public function testCrudActionReturnsExplicitDeleteConflictErrorResponse(): void {

		$_SERVER['REQUEST_METHOD'] = 'DELETE';

		$controller = $this->newCrudController();
		$record = (new FakeCrudDeletableRecord())->setDeletable(false);
		$router = \Pair\Core\Router::getInstance();

		FakeCrudDeletableRecord::seedFindResult($record);
		$controller->registerCrudResource('records', FakeCrudDeletableRecord::class, []);
		$this->setInaccessibleProperty($controller, 'request', new Request());
		$this->setInaccessibleProperty($router, 'vars', [0 => '5']);

		$response = $controller->recordsAction();

		$this->assertInstanceOf(ApiErrorResponse::class, $response);
		$this->assertSame('CONFLICT', $this->readApiErrorResponseProperty($response, 'errorCode'));
		$this->assertSame(409, $this->readApiErrorResponseProperty($response, 'httpCode'));
		$this->assertSame(['detail' => 'Resource is referenced and cannot be deleted'], $this->readApiErrorResponseProperty($response, 'extra'));
		$this->assertFalse($record->deleteCalled);

	}

	/**
	 * Verify unsupported CRUD methods return an explicit method-not-allowed response.
	 */
	public function testCrudActionReturnsExplicitMethodNotAllowedErrorResponse(): void {

		$_SERVER['REQUEST_METHOD'] = 'OPTIONS';

		$controller = $this->newCrudController();

		$controller->registerCrudResource('users', FakeCrudRecord::class, [
			'readModel' => FakeCrudReadModel::class,
		]);
		$this->setInaccessibleProperty($controller, 'request', new Request());

		$response = $controller->usersAction();

		$this->assertInstanceOf(ApiErrorResponse::class, $response);
		$this->assertSame('METHOD_NOT_ALLOWED', $this->readApiErrorResponseProperty($response, 'errorCode'));
		$this->assertSame(405, $this->readApiErrorResponseProperty($response, 'httpCode'));

	}

	/**
	 * Create a CrudController instance without invoking the MVC constructor.
	 */
	private function newCrudController(): FakeCrudController {

		$reflection = new \ReflectionClass(FakeCrudController::class);

		return $reflection->newInstanceWithoutConstructor();

	}

	/**
	 * Create a fake ActiveRecord instance without hitting the database constructor.
	 */
	private function newCrudRecord(): FakeCrudRecord {

		$reflection = new \ReflectionClass(FakeCrudRecord::class);

		return $reflection->newInstanceWithoutConstructor();

	}

	/**
	 * Create a request object backed by a JSON payload for CRUD dispatch tests.
	 *
	 * @param	array<string, mixed>	$payload	Request payload exposed through Request::json().
	 */
	private function newJsonRequest(array $payload): Request {

		$request = new Request();
		$this->setInaccessibleProperty($request, 'rawBody', json_encode($payload));

		return $request;

	}

	/**
	 * Install and return a lightweight fake database singleton for ActiveRecord create/update flows.
	 */
	private function setDatabaseInstance(): FakeCrudDatabase {

		$reflection = new \ReflectionClass(FakeCrudDatabase::class);
		$database = $reflection->newInstanceWithoutConstructor();
		$property = new \ReflectionProperty(Database::class, 'instance');
		$property->setValue(null, $database);

		return $database;

	}

	/**
	 * Reset the shared database singleton between tests so fake state cannot leak.
	 */
	private function resetDatabaseSingleton(): void {

		$property = new \ReflectionProperty(Database::class, 'instance');
		$property->setValue(null, null);

	}

	/**
	 * Install a lightweight Application singleton stub required by ActiveRecord create/update flows.
	 */
	private function setApplicationState(): void {

		$reflection = new \ReflectionClass(Application::class);
		$app = $reflection->newInstanceWithoutConstructor();
		$this->setInaccessibleProperty($app, 'currentUser', null);
		$this->setInaccessibleProperty($app, 'headless', true);
		$this->setInaccessibleProperty($app, 'session', null);

		$property = new \ReflectionProperty(Application::class, 'instance');
		$property->setValue(null, $app);

	}

	/**
	 * Reset the shared Application singleton between tests.
	 */
	private function resetApplicationSingleton(): void {

		$property = new \ReflectionProperty(Application::class, 'instance');
		$property->setValue(null, null);

	}

	/**
	 * Reset the Logger singleton so CRUD tests can run without framework logging side effects.
	 */
	private function resetLoggerSingleton(): void {

		$property = new \ReflectionProperty(Logger::class, 'instance');
		$property->setValue(null, null);

	}

	/**
	 * Read one private JsonResponse property for focused assertions on explicit CRUD responses.
	 */
	private function readJsonResponseProperty(JsonResponse $response, string $name): mixed {

		$property = new \ReflectionProperty($response, $name);

		return $property->getValue($response);

	}

	/**
	 * Read one private ApiErrorResponse property for focused assertions on explicit CRUD validation errors.
	 */
	private function readApiErrorResponseProperty(ApiErrorResponse $response, string $name): mixed {

		$property = new \ReflectionProperty($response, $name);

		return $property->getValue($response);

	}

}
