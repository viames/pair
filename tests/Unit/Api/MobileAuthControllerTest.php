<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Api\ApiController;
use Pair\Api\ApiErrorResponse;
use Pair\Api\Request;
use Pair\Core\Application;
use Pair\Core\Router;
use Pair\Http\JsonResponse;
use Pair\Models\ApiToken;
use Pair\Models\User;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;
use Pair\Tests\Support\TestCase;

/**
 * Covers the built-in mobile auth action on API controllers.
 */
class MobileAuthControllerTest extends TestCase {

	/**
	 * Ensure framework constants used by Router and ActiveRecord are available.
	 */
	protected function setUp(): void {

		parent::setUp();

		if (!defined('BASE_TIMEZONE')) {
			define('BASE_TIMEZONE', 'UTC');
		}

		if (!defined('URL_PATH')) {
			define('URL_PATH', null);
		}

		if (!defined('BASE_HREF')) {
			define('BASE_HREF', null);
		}

	}

	/**
	 * Reset shared singletons after each test.
	 */
	protected function tearDown(): void {

		$this->resetApplicationSingleton();
		$this->resetDatabaseSingleton();

		parent::tearDown();

	}

	/**
	 * Verify /auth/me returns a Pair data envelope with user snapshot and optional context.
	 */
	public function testAuthActionReturnsCurrentUserEnvelope(): void {

		$_SERVER['REQUEST_METHOD'] = 'GET';

		$controller = $this->newController();
		$router = Router::getInstance();
		$user = $this->newLoadedUser(21);

		$this->setApplicationState($user);
		$this->primeController($controller, new Request(), [0 => 'me']);

		$response = $controller->authAction();

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame([
			'data' => [
				'user' => [
					'id' => 21,
					'username' => 'mario',
					'email' => 'mario@example.test',
					'name' => 'Mario',
					'surname' => 'Rossi',
				],
				'context' => ['tenant' => 'demo'],
			],
		], $this->readJsonResponseProperty($response, 'payload'));
		$this->assertSame([0 => 'me'], $this->readPrivateProperty($router, Router::class, 'vars'));

	}

	/**
	 * Verify default registration requires applications to provide their own user creation hook.
	 */
	public function testAuthActionRegisterReturnsNotImplementedByDefault(): void {

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['CONTENT_TYPE'] = 'application/json';

		$controller = $this->newPlainController();
		$request = $this->requestWithJsonBody(['email' => 'new@example.test']);

		$this->primeController($controller, $request, [0 => 'register']);

		$response = $controller->authAction();

		$this->assertInstanceOf(ApiErrorResponse::class, $response);
		$this->assertSame('NOT_IMPLEMENTED', $this->readApiErrorResponseProperty($response, 'errorCode'));

	}

	/**
	 * Verify login issues an ApiToken session without requiring PHP web session state.
	 */
	public function testAuthActionLoginReturnsIssuedTokenSession(): void {

		$_ENV['PAIR_AUDIT_ALL'] = false;
		$_ENV['PAIR_AUTH_RATE_LIMIT_ENABLED'] = false;
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['CONTENT_TYPE'] = 'application/json';
		$_SERVER['REMOTE_ADDR'] = '203.0.113.20';
		$_SERVER['HTTP_USER_AGENT'] = 'PairMobileKit';

		$this->setSqliteDatabase([
			$this->usersSchema(),
			$this->apiTokensSchema(),
		]);

		Database::run(
			"INSERT INTO users (id, group_id, locale_id, username, hash, name, surname, email, super, enabled, last_login, faults, pw_reset)
			 VALUES (?, 1, 1, 'mario', ?, 'Mario', 'Rossi', 'mario@example.test', 0, 1, NULL, 0, 'reset-token')",
			[41, User::getHashedPasswordWithSalt('correct-password')]
		);

		$this->setApplicationState(null);

		$controller = $this->newController();
		$request = $this->requestWithJsonBody([
			'email'			=> 'mario@example.test',
			'password'		=> 'correct-password',
			'device_name'	=> 'iPhone 15',
		]);

		$this->primeController($controller, $request, [0 => 'login']);

		$response = $controller->authAction();
		$payload = $this->readJsonResponseProperty($response, 'payload');
		$row = Database::load('SELECT * FROM `api_tokens` LIMIT 1', [], Database::OBJECT);

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame(41, $payload['data']['user']['id']);
		$this->assertSame('Mario', $payload['data']['user']['name']);
		$this->assertSame('Bearer', $payload['data']['token_type']);
		$this->assertSame(900, $payload['data']['expires_in']);
		$this->assertNotEmpty($payload['data']['access_token']);
		$this->assertNotEmpty($payload['data']['refresh_token']);
		$this->assertSame(ApiToken::hashToken($payload['data']['access_token']), $row->access_token_hash);
		$this->assertSame(ApiToken::hashToken($payload['data']['refresh_token']), $row->refresh_token_hash);
		$this->assertSame('iPhone 15', $row->device_name);
		$this->assertSame('203.0.113.20', $row->ip_address);

	}

	/**
	 * Verify refresh endpoint rotates refresh tokens and returns a mobile session payload.
	 */
	public function testAuthActionRefreshReturnsRotatedSession(): void {

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['CONTENT_TYPE'] = 'application/json';

		$this->setSqliteDatabase([
			$this->usersSchema(),
			$this->apiTokensSchema(),
			"INSERT INTO users (id, group_id, locale_id, username, hash, name, surname, email, super, enabled, last_login, faults, pw_reset)
			 VALUES (31, 1, 1, 'luisa', 'unused', 'Luisa', 'Bianchi', 'luisa@example.test', 0, 1, NULL, 0, NULL)",
		]);

		$this->setApplicationState(null);
		$user = new User(31);
		$issued = ApiToken::issueForUser($user, true);
		$controller = $this->newController();
		$request = $this->requestWithJsonBody(['refresh_token' => $issued['refreshToken']]);

		$this->primeController($controller, $request, [0 => 'refresh']);

		$response = $controller->authAction();
		$payload = $this->readJsonResponseProperty($response, 'payload');

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame(31, $payload['data']['user']['id']);
		$this->assertNotSame($issued['accessToken'], $payload['data']['access_token']);
		$this->assertNotSame($issued['refreshToken'], $payload['data']['refresh_token']);
		$this->assertSame('Bearer', $payload['data']['token_type']);

	}

	/**
	 * Create a controller with custom context.
	 */
	private function newController(): TestMobileAuthController {

		$reflection = new \ReflectionClass(TestMobileAuthController::class);

		return $reflection->newInstanceWithoutConstructor();

	}

	/**
	 * Create a plain controller using the base registration hook.
	 */
	private function newPlainController(): PlainMobileAuthController {

		$reflection = new \ReflectionClass(PlainMobileAuthController::class);

		return $reflection->newInstanceWithoutConstructor();

	}

	/**
	 * Prime the controller with a request and route vars.
	 */
	private function primeController(ApiController $controller, Request $request, array $vars): void {

		$router = Router::getInstance();
		$this->setPrivateProperty($router, Router::class, 'vars', $vars);
		$this->setPrivateProperty($controller, ApiController::class, 'request', $request);
		$this->setPrivateProperty($controller, \Pair\Web\Controller::class, 'router', $router);

	}

	/**
	 * Create a Request instance backed by a preloaded JSON body.
	 */
	private function requestWithJsonBody(array $body): Request {

		$request = new Request();
		$this->setPrivateProperty($request, Request::class, 'rawBody', json_encode($body));

		return $request;

	}

	/**
	 * Create a lightweight loaded user with snapshot fields.
	 */
	private function newLoadedUser(int $id): User {

		$reflection = new \ReflectionClass(User::class);
		$user = $reflection->newInstanceWithoutConstructor();

		$this->setPrivateProperty($user, User::class, 'id', $id);
		$this->setPrivateProperty($user, User::class, 'username', 'mario');
		$this->setPrivateProperty($user, User::class, 'email', 'mario@example.test');
		$this->setPrivateProperty($user, User::class, 'name', 'Mario');
		$this->setPrivateProperty($user, User::class, 'surname', 'Rossi');
		$this->setPrivateProperty($user, ActiveRecord::class, 'loadedFromDb', true);

		return $user;

	}

	/**
	 * Set the lightweight Application singleton stub.
	 */
	private function setApplicationState(?User $user): void {

		$reflection = new \ReflectionClass(Application::class);
		$app = $reflection->newInstanceWithoutConstructor();
		$this->setPrivateProperty($app, Application::class, 'currentUser', $user);
		$this->setPrivateProperty($app, Application::class, 'userClass', User::class);

		$instanceReflection = new \ReflectionProperty(Application::class, 'instance');
		$instanceReflection->setValue(null, $app);

	}

	/**
	 * Install a real Database singleton backed by in-memory SQLite.
	 *
	 * @param	list<string>	$statements	Schema or seed statements.
	 */
	private function setSqliteDatabase(array $statements): void {

		$reflection = new \ReflectionClass(Database::class);
		$database = $reflection->newInstanceWithoutConstructor();
		$handler = new \PDO('sqlite::memory:');
		$handler->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

		foreach ($statements as $statement) {
			$handler->exec($statement);
		}

		$handlerProperty = new \ReflectionProperty(Database::class, 'handler');
		$handlerProperty->setValue($database, $handler);

		$instanceProperty = new \ReflectionProperty(Database::class, 'instance');
		$instanceProperty->setValue(null, $database);

		$database->setTableDescription('users', [
			'id'			=> ['int unsigned', 'NO', 'PRI', null, 'auto_increment'],
			'group_id'		=> ['int unsigned', 'NO', '', null, ''],
			'locale_id'		=> ['int unsigned', 'NO', '', null, ''],
			'username'		=> ['varchar(100)', 'NO', '', null, ''],
			'hash'			=> ['varchar(255)', 'NO', '', null, ''],
			'name'			=> ['varchar(100)', 'NO', '', null, ''],
			'surname'		=> ['varchar(100)', 'NO', '', null, ''],
			'email'			=> ['varchar(190)', 'YES', '', null, ''],
			'super'			=> ['tinyint(1)', 'NO', '', '0', ''],
			'enabled'		=> ['tinyint(1)', 'NO', '', '1', ''],
			'last_login'	=> ['datetime', 'YES', '', null, ''],
			'faults'		=> ['int unsigned', 'NO', '', '0', ''],
			'pw_reset'		=> ['varchar(255)', 'YES', '', null, ''],
		]);

	}

	/**
	 * Reset the Application singleton.
	 */
	private function resetApplicationSingleton(): void {

		$reflection = new \ReflectionProperty(Application::class, 'instance');
		$reflection->setValue(null, null);

	}

	/**
	 * Reset the shared database singleton.
	 */
	private function resetDatabaseSingleton(): void {

		$property = new \ReflectionProperty(Database::class, 'instance');
		$property->setValue(null, null);

	}

	/**
	 * Read one private JsonResponse property.
	 */
	private function readJsonResponseProperty(JsonResponse $response, string $name): mixed {

		$property = new \ReflectionProperty($response, $name);

		return $property->getValue($response);

	}

	/**
	 * Read one private ApiErrorResponse property.
	 */
	private function readApiErrorResponseProperty(ApiErrorResponse $response, string $name): mixed {

		$property = new \ReflectionProperty($response, $name);

		return $property->getValue($response);

	}

	/**
	 * Read one private property from an object.
	 */
	private function readPrivateProperty(object $object, string $class, string $name): mixed {

		$property = new \ReflectionProperty($class, $name);

		return $property->getValue($object);

	}

	/**
	 * Assign a private or protected property value through reflection.
	 */
	private function setPrivateProperty(object $object, string $class, string $name, mixed $value): void {

		$reflection = new \ReflectionProperty($class, $name);
		$reflection->setValue($object, $value);

	}

	/**
	 * Return the SQLite users schema needed by refresh tests.
	 */
	private function usersSchema(): string {

		return 'CREATE TABLE users (
			id INTEGER PRIMARY KEY,
			group_id INTEGER NOT NULL,
			locale_id INTEGER NOT NULL,
			username TEXT NOT NULL,
			hash TEXT NOT NULL,
			name TEXT NOT NULL,
			surname TEXT NOT NULL,
			email TEXT NULL,
			super INTEGER NOT NULL,
			enabled INTEGER NOT NULL,
			last_login TEXT NULL,
			faults INTEGER NOT NULL,
			pw_reset TEXT NULL
		)';

	}

	/**
	 * Return the SQLite api_tokens schema needed by refresh tests.
	 */
	private function apiTokensSchema(): string {

		return 'CREATE TABLE api_tokens (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL,
			access_token_hash TEXT NOT NULL UNIQUE,
			refresh_token_hash TEXT NULL UNIQUE,
			access_expires_at TEXT NOT NULL,
			refresh_expires_at TEXT NULL,
			device_hash TEXT NULL,
			password_version_hash TEXT NULL,
			device_name TEXT NULL,
			ip_address TEXT NULL,
			user_agent TEXT NULL,
			last_used_at TEXT NULL,
			revoked_at TEXT NULL,
			created_at TEXT NOT NULL,
			updated_at TEXT NOT NULL
		)';

	}

}

/**
 * Test controller that exposes a small application context.
 */
final class TestMobileAuthController extends ApiController {

	/**
	 * Return deterministic context for auth response assertions.
	 */
	protected function mobileAuthContext(User $user): ?array {

		return ['tenant' => 'demo'];

	}

}

/**
 * Test controller that keeps the base registration behavior.
 */
final class PlainMobileAuthController extends ApiController {}
