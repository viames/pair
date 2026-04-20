<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Api\ApiController;
use Pair\Api\Middleware;
use Pair\Api\MiddlewarePipeline;
use Pair\Api\Request;
use Pair\Api\ThrottleMiddleware;
use Pair\Core\Application;
use Pair\Models\User;
use Pair\Orm\ActiveRecord;
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
	 * Verify requireBearer() returns the configured token when one has been assigned already.
	 */
	public function testRequireBearerReturnsConfiguredToken(): void {

		$controller = $this->newApiController();
		$this->setPrivateProperty($controller, ApiController::class, 'bearerToken', 'top-secret-token');

		$this->assertSame('top-secret-token', $controller->exposeRequireBearer());

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
	 * Verify middleware() and runMiddleware() cooperate so controller-level middleware wraps the destination.
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
			public function handle(Request $request, callable $next): void {

				$this->trace[] = 'middleware:before';
				$next($request);
				$this->trace[] = 'middleware:after';

			}

		});

		$controller->runMiddleware(function () use ($trace): void {

			$trace[] = 'destination';

		});

		$this->assertSame([
			'middleware:before',
			'destination',
			'middleware:after',
		], $trace->getArrayCopy());

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
	 * Expose the bearer-token helper for focused unit tests.
	 */
	public function exposeRequireBearer(): string {

		return $this->requireBearer();

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
