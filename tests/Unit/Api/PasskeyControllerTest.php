<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Api\ApiController;
use Pair\Api\ApiErrorResponse;
use Pair\Api\PasskeyController;
use Pair\Api\Request;
use Pair\Core\Application;
use Pair\Core\Router;
use Pair\Http\JsonResponse;
use Pair\Models\User;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;
use Pair\Services\PasskeyAuth;
use Pair\Tests\Support\FakeCrudDatabase;
use Pair\Tests\Support\TestCase;

/**
 * Covers the isolated helper methods inside PasskeyController without bootstrapping full passkey flows.
 */
class PasskeyControllerTest extends TestCase {

	/**
	 * Define the minimal runtime constants and reset shared singletons between passkey tests.
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
		$this->resetApplicationSingleton();

	}

	/**
	 * Reset router vars and the application singleton after each passkey test.
	 */
	protected function tearDown(): void {

		$this->setPrivateProperty(Router::getInstance(), Router::class, 'vars', []);
		$this->resetDatabaseSingleton();
		$this->resetApplicationSingleton();

		parent::tearDown();

	}

	/**
	 * Verify optionalJsonPost() converts an empty JSON body into an empty array.
	 */
	public function testOptionalJsonPostReturnsEmptyArrayForEmptyJsonBody(): void {

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['CONTENT_TYPE'] = 'application/json';

		$controller = $this->newPasskeyController();
		$request = $this->newRequestWithRawBody('');

		$this->primeController($controller, $request);

		$this->assertSame([], $controller->exposeOptionalJsonPost());

	}

	/**
	 * Verify optionalJsonPost() returns the decoded associative payload for valid JSON requests.
	 */
	public function testOptionalJsonPostReturnsDecodedJsonPayload(): void {

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['CONTENT_TYPE'] = 'application/json';

		$controller = $this->newPasskeyController();
		$request = $this->newRequestWithRawBody(json_encode([
			'username' => 'alice@example.test',
			'timezone' => 'Europe/Rome',
		]));

		$this->primeController($controller, $request);

		$this->assertSame([
			'username' => 'alice@example.test',
			'timezone' => 'Europe/Rome',
		], $controller->exposeOptionalJsonPost());

	}

	/**
	 * Verify normalizeTimezone() preserves valid identifiers and falls back to UTC otherwise.
	 */
	public function testNormalizeTimezonePreservesValidIdentifiersAndFallsBackToUtc(): void {

		$controller = $this->newPasskeyController();

		$this->assertSame('Europe/Rome', $controller->exposeNormalizeTimezone('Europe/Rome'));
		$this->assertSame('UTC', $controller->exposeNormalizeTimezone(''));
		$this->assertSame('UTC', $controller->exposeNormalizeTimezone('Mars/Olympus'));
		$this->assertSame('UTC', $controller->exposeNormalizeTimezone(null));

	}

	/**
	 * Verify optionalJsonPost() rejects requests that do not use POST.
	 */
	public function testOptionalJsonPostRejectsNonPostRequests(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

final class PasskeyOptionalJsonPostSnippetController extends \Pair\Api\PasskeyController {

	/**
	 * Expose the private optionalJsonPost() helper for subprocess assertions.
	 *
	 * @return	array<string, mixed>
	 */
	public function exposeOptionalJsonPost(): array {

		$method = new ReflectionMethod(\Pair\Api\PasskeyController::class, 'optionalJsonPost');

		return $method->invoke($this);

	}

}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['CONTENT_TYPE'] = 'application/json';

$controller = (new ReflectionClass(PasskeyOptionalJsonPostSnippetController::class))->newInstanceWithoutConstructor();
$request = new \Pair\Api\Request();
(new ReflectionProperty(\Pair\Api\ApiController::class, 'request'))->setValue($controller, $request);

$controller->exposeOptionalJsonPost();
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(405, $this->extractReportedStatusCode($result['stderr']));
		$this->assertJsonStringEqualsJsonString(
			json_encode([
				'code' => 'METHOD_NOT_ALLOWED',
				'error' => 'Method not allowed',
				'expected' => 'POST',
				'actual' => 'GET',
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			$result['stdout']
		);

	}

	/**
	 * Verify optionalJsonPost() rejects requests that are not marked as JSON.
	 */
	public function testOptionalJsonPostRejectsUnsupportedMediaType(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

final class PasskeyOptionalJsonPostSnippetController extends \Pair\Api\PasskeyController {

	/**
	 * Expose the private optionalJsonPost() helper for subprocess assertions.
	 *
	 * @return	array<string, mixed>
	 */
	public function exposeOptionalJsonPost(): array {

		$method = new ReflectionMethod(\Pair\Api\PasskeyController::class, 'optionalJsonPost');

		return $method->invoke($this);

	}

}

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'text/plain';

$controller = (new ReflectionClass(PasskeyOptionalJsonPostSnippetController::class))->newInstanceWithoutConstructor();
$request = new \Pair\Api\Request();
(new ReflectionProperty(\Pair\Api\ApiController::class, 'request'))->setValue($controller, $request);

$controller->exposeOptionalJsonPost();
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(415, $this->extractReportedStatusCode($result['stderr']));
		$this->assertJsonStringEqualsJsonString(
			json_encode([
				'code' => 'UNSUPPORTED_MEDIA_TYPE',
				'error' => 'Unsupported media type',
				'expected' => 'application/json',
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			$result['stdout']
		);

	}

	/**
	 * Verify optionalJsonPost() rejects scalar JSON values returned by a custom Request implementation.
	 */
	public function testOptionalJsonPostRejectsScalarJsonPayloads(): void {

		$result = $this->runPhpSnippet(<<<'PHP'
register_shutdown_function(function (): void {
	fwrite(STDERR, 'HTTP_CODE=' . http_response_code() . PHP_EOL);
});

final class PasskeyOptionalJsonPostSnippetController extends \Pair\Api\PasskeyController {

	/**
	 * Expose the private optionalJsonPost() helper for subprocess assertions.
	 *
	 * @return	array<string, mixed>
	 */
	public function exposeOptionalJsonPost(): array {

		$method = new ReflectionMethod(\Pair\Api\PasskeyController::class, 'optionalJsonPost');

		return $method->invoke($this);

	}

}

final class ScalarJsonRequest extends \Pair\Api\Request {

	/**
	 * Force a JSON-compatible HTTP method.
	 */
	public function method(): string {

		return 'POST';

	}

	/**
	 * Force a JSON media type for the helper under test.
	 */
	public function isJson(): bool {

		return true;

	}

	/**
	 * Return a scalar payload to exercise the INVALID_OBJECT branch explicitly.
	 */
	public function json(?string $key = null, mixed $default = null): mixed {

		return 'unexpected-scalar';

	}

}

$controller = (new ReflectionClass(PasskeyOptionalJsonPostSnippetController::class))->newInstanceWithoutConstructor();
$request = new ScalarJsonRequest();
(new ReflectionProperty(\Pair\Api\ApiController::class, 'request'))->setValue($controller, $request);

$controller->exposeOptionalJsonPost();
PHP);

		$this->assertSame(0, $result['exitCode']);
		$this->assertSame(400, $this->extractReportedStatusCode($result['stderr']));
		$this->assertJsonStringEqualsJsonString(
			json_encode([
				'code' => 'INVALID_OBJECT',
				'error' => 'Invalid object',
				'field' => 'body',
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			$result['stdout']
		);

	}

	/**
	 * Verify passkeyAction() returns an explicit JsonResponse for list when the authenticated user has active passkeys.
	 */
	public function testPasskeyActionReturnsExplicitListResponse(): void {

		$_SERVER['REQUEST_METHOD'] = 'GET';

		$controller = $this->newPasskeyController();
		$router = Router::getInstance();
		$database = $this->setDatabaseInstance();
		$user = $this->newLoadedUser(12);

		$database->useSqliteMemoryDatabase([
			'CREATE TABLE user_passkeys (
				id INTEGER PRIMARY KEY,
				user_id INTEGER NOT NULL,
				credential_id TEXT NOT NULL,
				public_key TEXT NOT NULL,
				sign_count INTEGER NOT NULL DEFAULT 0,
				label TEXT NULL,
				transports TEXT NULL,
				last_used_at TEXT NULL,
				revoked_at TEXT NULL,
				created_at TEXT NOT NULL,
				updated_at TEXT NOT NULL
			)',
			"INSERT INTO user_passkeys (id, user_id, credential_id, public_key, sign_count, label, transports, last_used_at, revoked_at, created_at, updated_at) VALUES
				(5, 12, 'credential-5', 'pk-5', 0, 'Office Key', '[\"usb\",\"nfc\"]', '2026-04-19 09:30:00', NULL, '2026-04-20 10:15:00', '2026-04-20 10:15:00'),
				(4, 12, 'credential-4', 'pk-4', 0, NULL, NULL, NULL, NULL, '2026-04-18 08:00:00', '2026-04-18 08:00:00')",
		]);

		$this->setApplicationCurrentUser($user);
		$this->primeController($controller, new Request());
		$this->setPrivateProperty($controller, \Pair\Core\Controller::class, 'router', $router);
		$this->setPrivateProperty($router, Router::class, 'vars', [0 => 'list']);

		$response = $controller->passkeyAction();

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame([
			[
				'id' => 5,
				'label' => 'Office Key',
				'credentialId' => 'credential-5',
				'createdAt' => '2026-04-20 10:15:00',
				'lastUsedAt' => '2026-04-19 09:30:00',
				'transports' => ['usb', 'nfc'],
			],
			[
				'id' => 4,
				'label' => null,
				'credentialId' => 'credential-4',
				'createdAt' => '2026-04-18 08:00:00',
				'lastUsedAt' => null,
				'transports' => [],
			],
		], $this->readJsonResponseProperty($response, 'payload'));
		$this->assertSame(200, $this->readJsonResponseProperty($response, 'httpCode'));

	}

	/**
	 * Verify passkeyAction() returns an explicit JsonResponse for login/options when the passkey service succeeds.
	 */
	public function testPasskeyActionReturnsExplicitLoginOptionsResponse(): void {

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['CONTENT_TYPE'] = 'application/json';

		$controller = $this->newPasskeyController();
		$request = $this->newRequestWithRawBody(json_encode([]));
		$router = Router::getInstance();
		$passkeyAuth = $this->newFakePasskeyAuth();

		$passkeyAuth->authenticationOptions = [
			'challenge' => 'login-challenge',
			'rpId' => 'example.test',
		];

		$this->primeController($controller, $request);
		$this->setPrivateProperty($controller, \Pair\Core\Controller::class, 'router', $router);
		$this->setPrivateProperty($router, Router::class, 'vars', [0 => 'login', 1 => 'options']);
		$this->setPrivateProperty($controller, PasskeyController::class, 'passkeyAuth', $passkeyAuth);

		$response = $controller->passkeyAction();

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertNull($passkeyAuth->lastAuthenticationUser);
		$this->assertSame([
			'publicKey' => [
				'challenge' => 'login-challenge',
				'rpId' => 'example.test',
			],
		], $this->readJsonResponseProperty($response, 'payload'));
		$this->assertSame(200, $this->readJsonResponseProperty($response, 'httpCode'));

	}

	/**
	 * Verify passkeyAction() returns an explicit JsonResponse for register/options when the user is authenticated.
	 */
	public function testPasskeyActionReturnsExplicitRegisterOptionsResponse(): void {

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['CONTENT_TYPE'] = 'application/json';

		$controller = $this->newPasskeyController();
		$request = $this->newRequestWithRawBody(json_encode([
			'displayName' => 'Alice Device',
		]));
		$router = Router::getInstance();
		$passkeyAuth = $this->newFakePasskeyAuth();
		$user = $this->newLoadedUser(12);

		$passkeyAuth->registrationOptions = [
			'challenge' => 'register-challenge',
			'rp' => ['id' => 'example.test'],
		];

		$this->setApplicationCurrentUser($user);
		$this->primeController($controller, $request);
		$this->setPrivateProperty($controller, \Pair\Core\Controller::class, 'router', $router);
		$this->setPrivateProperty($router, Router::class, 'vars', [0 => 'register', 1 => 'options']);
		$this->setPrivateProperty($controller, PasskeyController::class, 'passkeyAuth', $passkeyAuth);

		$response = $controller->passkeyAction();

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame($user, $passkeyAuth->lastRegistrationUser);
		$this->assertSame('Alice Device', $passkeyAuth->lastRegistrationDisplayName);
		$this->assertSame([
			'publicKey' => [
				'challenge' => 'register-challenge',
				'rp' => ['id' => 'example.test'],
			],
		], $this->readJsonResponseProperty($response, 'payload'));
		$this->assertSame(200, $this->readJsonResponseProperty($response, 'httpCode'));

	}

	/**
	 * Verify passkeyAction() returns an explicit JsonResponse for login/verify when authentication succeeds.
	 */
	public function testPasskeyActionReturnsExplicitLoginVerifyResponse(): void {

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['CONTENT_TYPE'] = 'application/json';

		$controller = $this->newPasskeyController();
		$request = $this->newRequestWithRawBody(json_encode([
			'credential' => ['id' => 'credential-1'],
			'timezone' => 'Europe/Rome',
		]));
		$router = Router::getInstance();
		$passkeyAuth = $this->newFakePasskeyAuth();
		$app = $this->setApplicationState(null, ['lastRequestedUrl' => 'orders/default']);

		$result = new \stdClass();
		$result->error = false;
		$result->userId = 12;
		$result->sessionId = 'session-123';
		$passkeyAuth->authenticationResult = $result;

		$this->primeController($controller, $request);
		$this->setPrivateProperty($controller, \Pair\Core\Controller::class, 'router', $router);
		$this->setPrivateProperty($router, Router::class, 'vars', [0 => 'login', 1 => 'verify']);
		$this->setPrivateProperty($controller, PasskeyController::class, 'passkeyAuth', $passkeyAuth);

		$response = $controller->passkeyAction();

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame(['id' => 'credential-1'], $passkeyAuth->lastAuthenticationCredential);
		$this->assertSame('Europe/Rome', $passkeyAuth->lastAuthenticationTimezone);
		$this->assertNull($passkeyAuth->lastAuthenticationUser);
		$this->assertSame([
			'message' => 'Authenticated',
			'userId' => 12,
			'sessionId' => 'session-123',
			'redirectUrl' => 'orders/default',
		], $this->readJsonResponseProperty($response, 'payload'));
		$this->assertSame(200, $this->readJsonResponseProperty($response, 'httpCode'));
		$this->assertNull($this->readPrivateProperty($app, Application::class, 'persistentState')['lastRequestedUrl'] ?? null);

	}

	/**
	 * Verify passkeyAction() returns an explicit JsonResponse for register/verify when registration succeeds.
	 */
	public function testPasskeyActionReturnsExplicitRegisterVerifyResponse(): void {

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['CONTENT_TYPE'] = 'application/json';

		$controller = $this->newPasskeyController();
		$request = $this->newRequestWithRawBody(json_encode([
			'credential' => ['id' => 'credential-2'],
			'label' => 'Office Key',
		]));
		$router = Router::getInstance();
		$passkeyAuth = $this->newFakePasskeyAuth();
		$user = $this->newLoadedUser(22);

		$createdAt = new \DateTime('2026-04-20 10:15:00');
		$passkey = $this->newUserPasskey(5, 'Office Key', 'credential-2', $createdAt);
		$passkeyAuth->registeredPasskey = $passkey;

		$this->setApplicationCurrentUser($user);
		$this->primeController($controller, $request);
		$this->setPrivateProperty($controller, \Pair\Core\Controller::class, 'router', $router);
		$this->setPrivateProperty($router, Router::class, 'vars', [0 => 'register', 1 => 'verify']);
		$this->setPrivateProperty($controller, PasskeyController::class, 'passkeyAuth', $passkeyAuth);

		$response = $controller->passkeyAction();

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame($user, $passkeyAuth->lastRegistrationVerifyUser);
		$this->assertSame(['id' => 'credential-2'], $passkeyAuth->lastRegistrationVerifyCredential);
		$this->assertSame('Office Key', $passkeyAuth->lastRegistrationVerifyLabel);
		$this->assertSame([
			'message' => 'Passkey registered',
			'passkey' => [
				'id' => 5,
				'label' => 'Office Key',
				'credentialId' => 'credential-2',
				'createdAt' => '2026-04-20 10:15:00',
			],
		], $this->readJsonResponseProperty($response, 'payload'));
		$this->assertSame(201, $this->readJsonResponseProperty($response, 'httpCode'));

	}

	/**
	 * Verify passkeyAction() returns an explicit JsonResponse for revoke when the authenticated user owns the passkey.
	 */
	public function testPasskeyActionReturnsExplicitRevokeResponse(): void {

		$_SERVER['REQUEST_METHOD'] = 'DELETE';

		$controller = $this->newPasskeyController();
		$router = Router::getInstance();
		$database = $this->setDatabaseInstance();
		$user = $this->newLoadedUser(12);

		$database->useSqliteMemoryDatabase([
			'CREATE TABLE user_passkeys (
				id INTEGER PRIMARY KEY,
				user_id INTEGER NOT NULL,
				credential_id TEXT NOT NULL,
				public_key TEXT NOT NULL,
				sign_count INTEGER NOT NULL DEFAULT 0,
				label TEXT NULL,
				transports TEXT NULL,
				last_used_at TEXT NULL,
				revoked_at TEXT NULL,
				created_at TEXT NOT NULL,
				updated_at TEXT NOT NULL
			)',
			"INSERT INTO user_passkeys (id, user_id, credential_id, public_key, sign_count, label, transports, last_used_at, revoked_at, created_at, updated_at) VALUES
				(5, 12, 'credential-5', 'pk-5', 0, 'Office Key', NULL, NULL, NULL, '2026-04-20 10:15:00', '2026-04-20 10:15:00')",
		]);

		$this->setApplicationCurrentUser($user);
		$this->primeController($controller, new Request());
		$this->setPrivateProperty($controller, \Pair\Core\Controller::class, 'router', $router);
		$this->setPrivateProperty($router, Router::class, 'vars', [0 => 'revoke', 1 => '5']);

		$response = $controller->passkeyAction();

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertNull($this->readJsonResponseProperty($response, 'payload'));
		$this->assertSame(204, $this->readJsonResponseProperty($response, 'httpCode'));
		$this->assertCount(1, $database->updatedObjects);
		$this->assertSame('user_passkeys', $database->updatedObjects[0]['table']);
		$this->assertSame(5, $database->updatedObjects[0]['key']->id);
		$this->assertNotEmpty((array)$database->updatedObjects[0]['object']);

	}

	/**
	 * Verify unknown passkey routes now return an explicit API error response instead of sending JSON immediately.
	 */
	public function testPasskeyActionReturnsExplicitNotFoundResponseForUnknownRoute(): void {

		$_SERVER['REQUEST_METHOD'] = 'GET';

		$controller = $this->newPasskeyController();
		$router = Router::getInstance();

		$this->primeController($controller, new Request());
		$this->setPrivateProperty($controller, \Pair\Core\Controller::class, 'router', $router);
		$this->setPrivateProperty($router, Router::class, 'vars', [0 => 'unknown', 1 => 'route']);

		$response = $controller->passkeyAction();

		$this->assertInstanceOf(ApiErrorResponse::class, $response);

	}

	/**
	 * Create a PasskeyController instance without invoking the legacy MVC constructor.
	 */
	private function newPasskeyController(): TestPasskeyController {

		$reflection = new \ReflectionClass(TestPasskeyController::class);

		return $reflection->newInstanceWithoutConstructor();

	}

	/**
	 * Prime the controller with a request object by assigning the inherited ApiController property.
	 *
	 * @param	TestPasskeyController	$controller	Controller under test.
	 * @param	Request					$request	Current request object.
	 */
	private function primeController(TestPasskeyController $controller, Request $request): void {

		$this->setPrivateProperty($controller, ApiController::class, 'request', $request);

	}

	/**
	 * Create a Request instance backed by a configurable raw body payload.
	 *
	 * @param	string	$rawBody	Raw body exposed through Request::rawBody().
	 */
	private function newRequestWithRawBody(string $rawBody): Request {

		$request = new Request();
		$this->setPrivateProperty($request, Request::class, 'rawBody', $rawBody);

		return $request;

	}

	/**
	 * Create a lightweight fake passkey service without invoking the production constructor.
	 */
	private function newFakePasskeyAuth(): FakePasskeyAuth {

		$reflection = new \ReflectionClass(FakePasskeyAuth::class);

		return $reflection->newInstanceWithoutConstructor();

	}

	/**
	 * Create a lightweight loaded user without invoking the ActiveRecord constructor.
	 *
	 * @param	int	$id	User identifier exposed through Application::getInstance().
	 */
	private function newLoadedUser(int $id): User {

		$reflection = new \ReflectionClass(User::class);
		$user = $reflection->newInstanceWithoutConstructor();

		$this->setPrivateProperty($user, User::class, 'id', $id);
		$this->setPrivateProperty($user, ActiveRecord::class, 'loadedFromDb', true);

		return $user;

	}

	/**
	 * Set the current user on a lightweight Application singleton stub.
	 *
	 * @param	User|null	$user	User to expose through Application::getInstance().
	 */
	private function setApplicationCurrentUser(?User $user): void {

		$this->setApplicationState($user);

	}

	/**
	 * Set the lightweight Application singleton stub with optional current user and persistent state.
	 *
	 * @param	User|null				$user				User to expose through Application::getInstance().
	 * @param	array<string, mixed>	$persistentState	Short-lived persistent-state map.
	 */
	private function setApplicationState(?User $user, array $persistentState = []): Application {

		$reflection = new \ReflectionClass(Application::class);
		$app = $reflection->newInstanceWithoutConstructor();
		$this->setPrivateProperty($app, Application::class, 'currentUser', $user);
		$this->setPrivateProperty($app, Application::class, 'persistentState', $persistentState);

		$instanceReflection = new \ReflectionProperty(Application::class, 'instance');
		$instanceReflection->setValue(null, $app);

		return $app;

	}

	/**
	 * Install and return a fake database singleton for passkey list/revoke flows.
	 */
	private function setDatabaseInstance(): FakeCrudDatabase {

		$reflection = new \ReflectionClass(FakeCrudDatabase::class);
		$database = $reflection->newInstanceWithoutConstructor();
		$property = new \ReflectionProperty(Database::class, 'instance');
		$property->setValue(null, $database);

		return $database;

	}

	/**
	 * Reset the shared database singleton between passkey tests.
	 */
	private function resetDatabaseSingleton(): void {

		$property = new \ReflectionProperty(Database::class, 'instance');
		$property->setValue(null, null);

	}

	/**
	 * Reset the Application singleton to avoid leaking state between tests.
	 */
	private function resetApplicationSingleton(): void {

		$reflection = new \ReflectionProperty(Application::class, 'instance');
		$reflection->setValue(null, null);

	}

	/**
	 * Read one private JsonResponse property for focused assertions on explicit passkey responses.
	 */
	private function readJsonResponseProperty(JsonResponse $response, string $name): mixed {

		$property = new \ReflectionProperty($response, $name);

		return $property->getValue($response);

	}

	/**
	 * Read one private property for focused assertions on lightweight application stubs.
	 *
	 * @param	object	$object	Object under test.
	 * @param	string	$class	Declaring class of the property.
	 * @param	string	$name	Property name.
	 */
	private function readPrivateProperty(object $object, string $class, string $name): mixed {

		$property = new \ReflectionProperty($class, $name);

		return $property->getValue($object);

	}

	/**
	 * Create a lightweight UserPasskey instance with the minimum data required by the response payload.
	 */
	private function newUserPasskey(int $id, ?string $label, string $credentialId, \DateTimeInterface $createdAt): \Pair\Models\UserPasskey {

		$reflection = new \ReflectionClass(\Pair\Models\UserPasskey::class);
		$passkey = $reflection->newInstanceWithoutConstructor();

		$this->setPrivateProperty($passkey, \Pair\Models\UserPasskey::class, 'id', $id);
		$this->setPrivateProperty($passkey, \Pair\Models\UserPasskey::class, 'label', $label);
		$this->setPrivateProperty($passkey, \Pair\Models\UserPasskey::class, 'credentialId', $credentialId);
		$this->setPrivateProperty($passkey, \Pair\Models\UserPasskey::class, 'createdAt', $createdAt);

		return $passkey;

	}

	/**
	 * Assign a private property value on a specific declaring class through reflection.
	 *
	 * @param	object	$object	Object under test.
	 * @param	string	$class	Declaring class of the property.
	 * @param	string	$name	Property name.
	 * @param	mixed	$value	Value to assign.
	 */
	private function setPrivateProperty(object $object, string $class, string $name, mixed $value): void {

		$reflection = new \ReflectionProperty($class, $name);
		$reflection->setValue($object, $value);

	}

	/**
	 * Parse the HTTP status code emitted by the subprocess shutdown hook.
	 *
	 * @param	string	$stderr	Standard error captured from the subprocess.
	 */
	private function extractReportedStatusCode(string $stderr): int {

		if (!preg_match('/HTTP_CODE=(\d+)/', $stderr, $matches)) {
			$this->fail('The subprocess did not report an HTTP status code. STDERR was: ' . $stderr);
		}

		return (int)$matches[1];

	}

}

/**
 * Lightweight PasskeyController test double exposing private helpers through reflection.
 */
final class TestPasskeyController extends PasskeyController {

	/**
	 * Expose the optionalJsonPost() helper for focused unit tests.
	 *
	 * @return	array<string, mixed>
	 */
	public function exposeOptionalJsonPost(): array {

		$method = new \ReflectionMethod(PasskeyController::class, 'optionalJsonPost');

		return $method->invoke($this);

	}

	/**
	 * Expose the timezone-normalization helper for focused unit tests.
	 */
	public function exposeNormalizeTimezone(?string $timezone): string {

		$method = new \ReflectionMethod(PasskeyController::class, 'normalizeTimezone');

		return $method->invoke($this, $timezone);

	}

}

/**
 * Lightweight PasskeyAuth fake used to exercise explicit response paths without WebAuthn setup.
 */
final class FakePasskeyAuth extends PasskeyAuth {

	/**
	 * Authentication options returned to the controller.
	 *
	 * @var	array<string, mixed>
	 */
	public array $authenticationOptions = [];

	/**
	 * Registration options returned to the controller.
	 *
	 * @var	array<string, mixed>
	 */
	public array $registrationOptions = [];

	/**
	 * Last user passed to beginAuthentication().
	 */
	public ?User $lastAuthenticationUser = null;

	/**
	 * Last credential passed to completeAuthentication().
	 *
	 * @var	array<string, mixed>|null
	 */
	public ?array $lastAuthenticationCredential = null;

	/**
	 * Last timezone passed to completeAuthentication().
	 */
	public ?string $lastAuthenticationTimezone = null;

	/**
	 * Last user passed to beginRegistration().
	 */
	public ?User $lastRegistrationUser = null;

	/**
	 * Last display name passed to beginRegistration().
	 */
	public ?string $lastRegistrationDisplayName = null;

	/**
	 * Result returned by completeAuthentication().
	 */
	public ?\stdClass $authenticationResult = null;

	/**
	 * Last user passed to registerCredential().
	 */
	public ?User $lastRegistrationVerifyUser = null;

	/**
	 * Last credential passed to registerCredential().
	 *
	 * @var	array<string, mixed>|null
	 */
	public ?array $lastRegistrationVerifyCredential = null;

	/**
	 * Last label passed to registerCredential().
	 */
	public ?string $lastRegistrationVerifyLabel = null;

	/**
	 * Passkey returned by registerCredential().
	 */
	public ?\Pair\Models\UserPasskey $registeredPasskey = null;

	/**
	 * Return canned authentication options and capture the optional user argument.
	 *
	 * @param	User|null	$user				Optionally restricted user.
	 * @param	string[]	$allowCredentialIds	Optional allow-list.
	 * @param	array		$options			Optional payload overrides.
	 * @return	array<string, mixed>
	 */
	public function beginAuthentication(?User $user = null, array $allowCredentialIds = [], array $options = []): array {

		$this->lastAuthenticationUser = $user;

		return $this->authenticationOptions;

	}

	/**
	 * Return the canned authentication result and capture the credential arguments.
	 *
	 * @param	array		$credential	Assertion payload from browser.
	 * @param	string		$timezone	IANA time zone identifier.
	 * @param	User|null	$user		Optional expected user.
	 */
	public function completeAuthentication(array $credential, string $timezone, ?User $user = null): \stdClass {

		$this->lastAuthenticationCredential = $credential;
		$this->lastAuthenticationTimezone = $timezone;
		$this->lastAuthenticationUser = $user;

		return $this->authenticationResult ?? (object)[
			'error' => true,
			'userId' => null,
			'sessionId' => null,
		];

	}

	/**
	 * Return canned registration options and capture the user and display name arguments.
	 *
	 * @param	User		$user					Authenticated user registering a passkey.
	 * @param	string|null	$userDisplayName		Optional display name.
	 * @param	string[]	$excludeCredentialIds	Optional exclude-list.
	 * @param	array		$options				Optional payload overrides.
	 * @return	array<string, mixed>
	 */
	public function beginRegistration(User $user, ?string $userDisplayName = null, array $excludeCredentialIds = [], array $options = []): array {

		$this->lastRegistrationUser = $user;
		$this->lastRegistrationDisplayName = $userDisplayName;

		return $this->registrationOptions;

	}

	/**
	 * Return the canned registered passkey and capture the registration verification arguments.
	 *
	 * @param	User		$user		Authenticated user registering a passkey.
	 * @param	array		$credential	Attestation payload from browser.
	 * @param	string|null	$label		Optional application label.
	 */
	public function registerCredential(User $user, array $credential, ?string $label = null): \Pair\Models\UserPasskey {

		$this->lastRegistrationVerifyUser = $user;
		$this->lastRegistrationVerifyCredential = $credential;
		$this->lastRegistrationVerifyLabel = $label;

		if (!$this->registeredPasskey) {
			throw new \LogicException('FakePasskeyAuth::registeredPasskey must be seeded before registerCredential() is used in tests.');
		}

		return $this->registeredPasskey;

	}

}
