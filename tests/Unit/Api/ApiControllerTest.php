<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Api\ApiErrorResponse;
use Pair\Api\ApiController;
use Pair\Api\Middleware;
use Pair\Api\MiddlewarePipeline;
use Pair\Api\Request;
use Pair\Api\ThrottleMiddleware;
use Pair\Core\Application;
use Pair\Exceptions\PairException;
use Pair\Http\JsonResponse;
use Pair\Http\ResponseInterface;
use Pair\Http\TextResponse;
use Pair\Models\User;
use Pair\Orm\ActiveRecord;
use Pair\Services\WhatsAppCloudClient;
use Pair\Tests\Support\TestCase;

/**
 * Covers ApiController helper methods without bootstrapping the legacy MVC constructor.
 */
class ApiControllerTest extends TestCase {

	/**
	 * Reset the application singleton so each test can control the authenticated user state explicitly.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->resetApplicationSingleton();

	}

	/**
	 * Restore the application singleton after each test.
	 */
	protected function tearDown(): void {

		$this->resetApplicationSingleton();

		parent::tearDown();

	}

	/**
	 * Verify getJsonBody() proxies the parsed JSON payload from the current request.
	 */
	public function testGetJsonBodyReturnsParsedPayload(): void {

		$controller = $this->newApiController();
		$request = $this->newRequestWithJsonBody([
			'name' => 'Alice',
			'active' => true,
		]);

		$this->primeController($controller, $request);

		$this->assertSame([
			'name' => 'Alice',
			'active' => true,
		], $controller->exposeGetJsonBody());

	}

	/**
	 * Verify requireJsonPost() accepts valid JSON POST requests and returns the decoded payload.
	 */
	public function testRequireJsonPostReturnsPayloadForValidJsonPost(): void {

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['CONTENT_TYPE'] = 'application/json';

		$controller = $this->newApiController();
		$request = $this->newRequestWithJsonBody([
			'email' => 'alice@example.test',
		]);

		$this->primeController($controller, $request);

		$this->assertSame([
			'email' => 'alice@example.test',
		], $controller->exposeRequireJsonPost());

	}

	/**
	 * Verify requireAuthOrResponse() returns an explicit UNAUTHORIZED response when no user is authenticated.
	 */
	public function testRequireAuthOrResponseReturnsExplicitErrorWhenUserIsMissing(): void {

		$controller = $this->newApiController();

		$this->setApplicationCurrentUser(null);

		$result = $controller->exposeRequireAuthOrResponse();

		$this->assertInstanceOf(ApiErrorResponse::class, $result);
		$this->assertSame('UNAUTHORIZED', $this->readPrivateProperty($result, ApiErrorResponse::class, 'errorCode'));
		$this->assertSame(401, $this->readPrivateProperty($result, ApiErrorResponse::class, 'httpCode'));

	}

	/**
	 * Verify requireBearer() returns the configured token when one has been assigned already.
	 */
	public function testRequireBearerReturnsConfiguredToken(): void {

		$controller = $this->newApiController();
		$this->setPrivateProperty($controller, ApiController::class, 'bearerToken', 'top-secret-token');

		$this->assertSame('top-secret-token', $controller->exposeRequireBearer());

	}

	/**
	 * Verify requireBearerOrResponse() returns an explicit AUTH_TOKEN_MISSING response when no token is assigned.
	 */
	public function testRequireBearerOrResponseReturnsExplicitErrorWhenTokenIsMissing(): void {

		$controller = $this->newApiController();

		$result = $controller->exposeRequireBearerOrResponse();

		$this->assertInstanceOf(ApiErrorResponse::class, $result);
		$this->assertSame('AUTH_TOKEN_MISSING', $this->readPrivateProperty($result, ApiErrorResponse::class, 'errorCode'));
		$this->assertSame(401, $this->readPrivateProperty($result, ApiErrorResponse::class, 'httpCode'));

	}

	/**
	 * Verify getUser() returns the loaded authenticated user exposed by the application singleton.
	 */
	public function testGetUserReturnsLoadedCurrentUser(): void {

		$controller = $this->newApiController();
		$user = $this->newLoadedUser(12);

		$this->setApplicationCurrentUser($user);

		$this->assertSame($user, $controller->getUser());

	}

	/**
	 * Verify getUser() returns null when the current user is missing or not loaded from persistence.
	 */
	public function testGetUserReturnsNullWhenCurrentUserIsMissingOrUnloaded(): void {

		$controller = $this->newApiController();

		$this->setApplicationCurrentUser(null);
		$this->assertNull($controller->getUser());

		$this->setApplicationCurrentUser($this->newUnloadedUser());
		$this->assertNull($controller->getUser());

	}

	/**
	 * Verify middleware() and runMiddleware() cooperate so controller-level middleware wraps the destination and preserves its return value.
	 */
	public function testMiddlewareAndRunMiddlewareUseControllerPipeline(): void {

		$controller = $this->newApiController();
		$request = new Request();
		$trace = new \ArrayObject();

		$this->primeController($controller, $request);
		$controller->middleware(new class($trace) implements Middleware {

			/**
			 * Shared trace storage.
			 */
			private \ArrayObject $trace;

			/**
			 * Store the trace storage used to assert pipeline execution order.
			 *
			 * @param	\ArrayObject	$trace	Execution trace.
			 */
			public function __construct(\ArrayObject $trace) {

				$this->trace = $trace;

			}

			/**
			 * Record before and after markers around the controller destination.
			 *
			 * @param	Request		$request	Current request.
			 * @param	callable	$next		Next middleware or destination.
			 */
			public function handle(Request $request, callable $next): mixed {

				$this->trace[] = 'middleware:before';
				$result = $next($request);
				$this->trace[] = 'middleware:after';

				return $result;

			}

		});

		$result = $controller->runMiddleware(function () use ($trace): string {

			$trace[] = 'destination';
			return 'response-from-destination';

		});

		$this->assertSame([
			'middleware:before',
			'destination',
			'middleware:after',
		], $trace->getArrayCopy());
		$this->assertSame('response-from-destination', $result);

	}

	/**
	 * Verify runMiddleware() can return an explicit response object produced by a blocking middleware.
	 */
	public function testRunMiddlewareReturnsMiddlewareResponseWhenChainShortCircuits(): void {

		$controller = $this->newApiController();
		$this->primeController($controller, new Request());
		$controller->middleware(new class implements Middleware {

			/**
			 * Stop the chain with an explicit API error response.
			 */
			public function handle(Request $request, callable $next): mixed {

				return new ApiErrorResponse('TOO_MANY_REQUESTS', 'Too many requests', 429, [
					'retryAfter' => 15,
				]);

			}

		});

		$result = $controller->runMiddleware(function (): string {

			return 'destination';

		});

		$this->assertInstanceOf(ApiErrorResponse::class, $result);

	}

	/**
	 * Verify registerDefaultMiddleware() adds the throttle middleware only when rate limiting is enabled.
	 */
	public function testRegisterDefaultMiddlewareRespectsEnvToggle(): void {

		$_ENV['PAIR_API_RATE_LIMIT_ENABLED'] = true;
		$_ENV['PAIR_API_RATE_LIMIT_MAX_ATTEMPTS'] = 9;
		$_ENV['PAIR_API_RATE_LIMIT_DECAY_SECONDS'] = 30;

		$enabledController = $this->newApiController();
		$this->primeController($enabledController, new Request());
		$enabledController->exposeRegisterDefaultMiddleware();

		$middlewares = $this->readPipelineMiddlewares($enabledController);

		$this->assertCount(1, $middlewares);
		$this->assertInstanceOf(ThrottleMiddleware::class, $middlewares[0]);

		$_ENV['PAIR_API_RATE_LIMIT_ENABLED'] = false;

		$disabledController = $this->newApiController();
		$this->primeController($disabledController, new Request());
		$disabledController->exposeRegisterDefaultMiddleware();

		$this->assertSame([], $this->readPipelineMiddlewares($disabledController));

	}

	/**
	 * Verify missing actions return an explicit 404 API error response instead of sending output immediately.
	 */
	public function testCallReturnsExplicitNotFoundErrorResponse(): void {

		$controller = $this->newApiController();

		$response = $controller->missingAction();

		$this->assertInstanceOf(ResponseInterface::class, $response);
		$this->assertInstanceOf(ApiErrorResponse::class, $response);

	}

	/**
	 * Verify the WhatsApp GET webhook branch now returns an explicit text response.
	 */
	public function testWhatsAppWebhookActionReturnsTextResponseForGetChallenge(): void {

		$_SERVER['REQUEST_METHOD'] = 'GET';

		$controller = $this->newApiController();
		$this->primeController($controller, new Request());
		$this->setPrivateProperty($controller, ApiController::class, 'whatsAppCloudClient', new FakeWhatsAppCloudClient(challenge: 'challenge-token'));

		$response = $controller->whatsappWebhookAction();

		$this->assertInstanceOf(TextResponse::class, $response);

		ob_start();
		$response->send();
		$output = ob_get_clean();

		$this->assertSame(200, http_response_code());
		$this->assertSame('challenge-token', $output);

	}

	/**
	 * Verify the WhatsApp POST webhook branch returns an explicit JSON response after validation succeeds.
	 */
	public function testWhatsAppWebhookActionReturnsJsonResponseForValidPostPayload(): void {

		$_SERVER['REQUEST_METHOD'] = 'POST';

		$controller = $this->newApiController();
		$request = $this->newRequestWithRawBody('{"entry":[]}');
		$this->primeController($controller, $request);
		$this->setPrivateProperty($controller, ApiController::class, 'whatsAppCloudClient', new FakeWhatsAppCloudClient(
			decodedPayload: ['entry' => []],
			events: [
				['event' => 'message'],
				['event' => 'status'],
			]
		));

		$response = $controller->whatsappWebhookAction();

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame(200, $this->readPrivateProperty($response, JsonResponse::class, 'httpCode'));
		$this->assertSame([
			'received' => true,
			'events' => 2,
		], $this->readPrivateProperty($response, JsonResponse::class, 'payload'));

	}

	/**
	 * Verify unsupported WhatsApp webhook methods return an explicit API error response.
	 */
	public function testWhatsAppWebhookActionReturnsErrorResponseForUnsupportedMethod(): void {

		$_SERVER['REQUEST_METHOD'] = 'DELETE';

		$controller = $this->newApiController();
		$this->primeController($controller, new Request());

		$response = $controller->whatsappWebhookAction();

		$this->assertInstanceOf(ApiErrorResponse::class, $response);

	}

	/**
	 * Create a controller instance without invoking the legacy MVC constructor.
	 */
	private function newApiController(): TestApiController {

		$reflection = new \ReflectionClass(TestApiController::class);

		return $reflection->newInstanceWithoutConstructor();

	}

	/**
	 * Prime the controller with a request object and an empty middleware pipeline.
	 *
	 * @param	TestApiController	$controller	Controller under test.
	 * @param	Request				$request	Current request object.
	 */
	private function primeController(TestApiController $controller, Request $request): void {

		$this->setPrivateProperty($controller, ApiController::class, 'request', $request);
		$this->setPrivateProperty($controller, ApiController::class, 'pipeline', new MiddlewarePipeline());

	}

	/**
	 * Create a Request instance backed by a preloaded JSON body.
	 *
	 * @param	array	$payload	JSON payload to expose through Request::json().
	 */
	private function newRequestWithJsonBody(array $payload): Request {

		$request = new Request();
		$this->setPrivateProperty($request, Request::class, 'rawBody', json_encode($payload));

		return $request;

	}

	/**
	 * Create a Request instance backed by a configurable raw body.
	 *
	 * @param	string	$rawBody	Raw request body to expose through Request::rawBody().
	 */
	private function newRequestWithRawBody(string $rawBody): Request {

		$request = new Request();
		$this->setPrivateProperty($request, Request::class, 'rawBody', $rawBody);

		return $request;

	}

	/**
	 * Return the middleware list currently registered on the controller pipeline.
	 *
	 * @param	TestApiController	$controller	Controller under test.
	 * @return	list<object>
	 */
	private function readPipelineMiddlewares(TestApiController $controller): array {

		$pipeline = $this->readPrivateProperty($controller, ApiController::class, 'pipeline');

		return $this->readPrivateProperty($pipeline, MiddlewarePipeline::class, 'middlewares');

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
	 * Create a lightweight unloaded user used to verify getUser() returns null.
	 */
	private function newUnloadedUser(): User {

		$reflection = new \ReflectionClass(User::class);

		return $reflection->newInstanceWithoutConstructor();

	}

	/**
	 * Set the current user on a lightweight Application singleton stub.
	 *
	 * @param	User|null	$user	User to expose through Application::getInstance().
	 */
	private function setApplicationCurrentUser(?User $user): void {

		$reflection = new \ReflectionClass(Application::class);
		$app = $reflection->newInstanceWithoutConstructor();
		$this->setPrivateProperty($app, Application::class, 'currentUser', $user);

		$instanceReflection = new \ReflectionProperty(Application::class, 'instance');
		$instanceReflection->setValue(null, $app);

	}

	/**
	 * Reset the Application singleton to avoid leaking state between tests.
	 */
	private function resetApplicationSingleton(): void {

		$reflection = new \ReflectionProperty(Application::class, 'instance');
		$reflection->setValue(null, null);

	}

	/**
	 * Assign a private or protected property value through reflection for focused unit tests.
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
	 * Read a private or protected property value through reflection for focused unit tests.
	 *
	 * @param	object	$object	Object under test.
	 * @param	string	$class	Declaring class of the property.
	 * @param	string	$name	Property name.
	 */
	private function readPrivateProperty(object $object, string $class, string $name): mixed {

		$reflection = new \ReflectionProperty($class, $name);

		return $reflection->getValue($object);

	}

}

/**
 * Lightweight ApiController test double exposing protected helper methods.
 */
final class TestApiController extends ApiController {

	/**
	 * Expose the parsed JSON body helper for focused unit tests.
	 */
	public function exposeGetJsonBody(): mixed {

		return $this->getJsonBody();

	}

	/**
	 * Expose the explicit authenticated-user guard for focused unit tests.
	 */
	public function exposeRequireAuthOrResponse(): User|ApiErrorResponse {

		return $this->requireAuthOrResponse();

	}

	/**
	 * Expose the bearer-token helper for focused unit tests.
	 */
	public function exposeRequireBearer(): string {

		return $this->requireBearer();

	}

	/**
	 * Expose the explicit bearer-token guard for focused unit tests.
	 */
	public function exposeRequireBearerOrResponse(): string|ApiErrorResponse {

		return $this->requireBearerOrResponse();

	}

	/**
	 * Expose the JSON POST validator for focused unit tests.
	 */
	public function exposeRequireJsonPost(): mixed {

		return $this->requireJsonPost();

	}

	/**
	 * Expose default middleware registration for focused unit tests.
	 */
	public function exposeRegisterDefaultMiddleware(): void {

		$this->registerDefaultMiddleware();

	}

}

/**
 * Lightweight WhatsApp Cloud client stub used to isolate webhook branches inside ApiController.
 */
final class FakeWhatsAppCloudClient extends WhatsAppCloudClient {

	/**
	 * Whether the app secret is considered configured.
	 */
	public bool $appSecretConfigured;

	/**
	 * Challenge string returned by verifyWebhookChallenge().
	 */
	public string $challenge;

	/**
	 * Optional exception thrown by verifyWebhookChallenge().
	 */
	public ?PairException $challengeException;

	/**
	 * Decoded webhook payload returned by decodeWebhookPayload().
	 *
	 * @var	array<string, mixed>
	 */
	public array $decodedPayload;

	/**
	 * Optional exception thrown by decodeWebhookPayload().
	 */
	public ?PairException $decodeException;

	/**
	 * Extracted normalized webhook events.
	 *
	 * @var	list<array<string, mixed>>
	 */
	public array $events;

	/**
	 * Whether verifyWebhookSignature() should accept the payload.
	 */
	public bool $signatureIsValid;

	/**
	 * Whether the webhook verify token is considered configured.
	 */
	public bool $verifyTokenConfigured;

	/**
	 * Seed the fake client with deterministic webhook behavior.
	 *
	 * @param	array<string, mixed>	$decodedPayload	Decoded payload returned by decodeWebhookPayload().
	 * @param	list<array<string, mixed>>	$events		Normalized events returned by extractWebhookEvents().
	 */
	public function __construct(
		bool $appSecretConfigured = true,
		bool $verifyTokenConfigured = true,
		bool $signatureIsValid = true,
		string $challenge = 'challenge',
		array $decodedPayload = [],
		array $events = [],
		?PairException $challengeException = null,
		?PairException $decodeException = null
	) {

		$this->appSecretConfigured = $appSecretConfigured;
		$this->verifyTokenConfigured = $verifyTokenConfigured;
		$this->signatureIsValid = $signatureIsValid;
		$this->challenge = $challenge;
		$this->decodedPayload = $decodedPayload;
		$this->events = $events;
		$this->challengeException = $challengeException;
		$this->decodeException = $decodeException;

	}

	/**
	 * Return whether the fake webhook app secret is configured.
	 */
	public function webhookAppSecretSet(): bool {

		return $this->appSecretConfigured;

	}

	/**
	 * Return whether the fake webhook verify token is configured.
	 */
	public function webhookVerifyTokenSet(): bool {

		return $this->verifyTokenConfigured;

	}

	/**
	 * Return the configured challenge or throw the seeded exception.
	 */
	public function verifyWebhookChallenge(?string $mode = null, ?string $verifyToken = null, ?string $challenge = null): string {

		if ($this->challengeException instanceof PairException) {
			throw $this->challengeException;
		}

		return $this->challenge;

	}

	/**
	 * Return the configured signature-validation result.
	 */
	public function verifyWebhookSignature(string $payload, ?string $signatureHeader = null): bool {

		return $this->signatureIsValid;

	}

	/**
	 * Return the configured decoded payload or throw the seeded exception.
	 *
	 * @return	array<string, mixed>
	 */
	public function decodeWebhookPayload(string $payload): array {

		if ($this->decodeException instanceof PairException) {
			throw $this->decodeException;
		}

		return $this->decodedPayload;

	}

	/**
	 * Return the configured normalized event list.
	 *
	 * @param	array<string, mixed>	$payload	Decoded webhook payload.
	 * @return	list<array<string, mixed>>
	 */
	public function extractWebhookEvents(array $payload): array {

		return $this->events;

	}

}
