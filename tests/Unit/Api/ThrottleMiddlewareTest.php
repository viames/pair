<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Api\ApiErrorResponse;
use Pair\Api\RateLimitResult;
use Pair\Api\RateLimiter;
use Pair\Api\Request;
use Pair\Api\ThrottleMiddleware;
use Pair\Core\Application;
use Pair\Models\User;
use Pair\Orm\ActiveRecord;
use Pair\Tests\Support\TestCase;

/**
 * Covers throttle-key resolution for the API middleware without bootstrapping the full runtime.
 */
class ThrottleMiddlewareTest extends TestCase {

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
	 * Verify the middleware prefers the session identifier over every other identity source.
	 */
	public function testHandlePrefersSessionIdentifier(): void {

		$_GET['sid'] = 'session-123';
		$_SERVER['REMOTE_ADDR'] = '203.0.113.20';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret-token';

		$this->setApplicationCurrentUser($this->newLoadedUser(55));

		$limiter = new TrackingRateLimiter(new RateLimitResult(true, 60, 59, time() + 60, 1, 'file'));
		$middleware = $this->newThrottleMiddlewareWithLimiter($limiter);
		$nextCalled = false;

		$middleware->handle(new Request(), function (Request $request) use (&$nextCalled): void {

			$nextCalled = true;

		});

		$this->assertTrue($nextCalled);
		$this->assertSame(
			'throttle:session:' . hash('sha256', 'session-123'),
			$limiter->lastKey
		);

	}

	/**
	 * Verify the middleware uses the bearer token when no session identifier is present.
	 */
	public function testHandleUsesBearerTokenWhenSessionIsMissing(): void {

		$_SERVER['REMOTE_ADDR'] = '203.0.113.21';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret-token';

		$this->setApplicationCurrentUser($this->newLoadedUser(77));

		$limiter = new TrackingRateLimiter(new RateLimitResult(true, 60, 58, time() + 60, 2, 'file'));
		$middleware = $this->newThrottleMiddlewareWithLimiter($limiter);

		$middleware->handle(new Request(), function (Request $request): void {});

		$this->assertSame(
			'throttle:bearer:' . hash('sha256', 'secret-token'),
			$limiter->lastKey
		);

	}

	/**
	 * Verify the middleware falls back to the authenticated user when session and bearer identifiers are absent.
	 */
	public function testHandleUsesAuthenticatedUserIdentifier(): void {

		$_SERVER['REMOTE_ADDR'] = '203.0.113.22';

		$this->setApplicationCurrentUser($this->newLoadedUser(91));

		$limiter = new TrackingRateLimiter(new RateLimitResult(true, 60, 57, time() + 60, 3, 'file'));
		$middleware = $this->newThrottleMiddlewareWithLimiter($limiter);

		$middleware->handle(new Request(), function (Request $request): void {});

		$this->assertSame('throttle:user:91', $limiter->lastKey);

	}

	/**
	 * Verify the middleware falls back to the client IP when no stronger identity source is available.
	 */
	public function testHandleFallsBackToClientIp(): void {

		$_SERVER['REMOTE_ADDR'] = '203.0.113.23';

		$this->setApplicationCurrentUser(null);

		$limiter = new TrackingRateLimiter(new RateLimitResult(true, 60, 56, time() + 60, 4, 'file'));
		$middleware = $this->newThrottleMiddlewareWithLimiter($limiter);

		$middleware->handle(new Request(), function (Request $request): void {});

		$this->assertSame('throttle:ip:203.0.113.23', $limiter->lastKey);

	}

	/**
	 * Verify blocked requests now return an explicit API error response instead of sending output immediately.
	 */
	public function testHandleReturnsExplicitErrorResponseWhenBlocked(): void {

		$_SERVER['REMOTE_ADDR'] = '203.0.113.24';

		$limiter = new TrackingRateLimiter(new RateLimitResult(false, 60, 0, time() + 15, 15, 'file'));
		$middleware = $this->newThrottleMiddlewareWithLimiter($limiter);
		$nextCalled = false;

		$response = $middleware->handle(new Request(), function (Request $request) use (&$nextCalled): void {

			$nextCalled = true;

		});

		$this->assertInstanceOf(ApiErrorResponse::class, $response);
		$this->assertFalse($nextCalled);
		$this->assertSame('throttle:ip:203.0.113.24', $limiter->lastKey);

	}

	/**
	 * Create a middleware instance with an injected tracking limiter.
	 *
	 * @param	TrackingRateLimiter	$limiter	Limiter used to capture the resolved throttle key.
	 */
	private function newThrottleMiddlewareWithLimiter(TrackingRateLimiter $limiter): ThrottleMiddleware {

		$middleware = new ThrottleMiddleware();
		$reflection = new \ReflectionProperty(ThrottleMiddleware::class, 'limiter');
		$reflection->setValue($middleware, $limiter);

		return $middleware;

	}

	/**
	 * Create a lightweight loaded user without invoking the ActiveRecord constructor.
	 *
	 * @param	int	$id	User identifier exposed to the middleware.
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

}

/**
 * Tracking limiter used to assert which throttle key the middleware resolved.
 */
final class TrackingRateLimiter extends RateLimiter {

	/**
	 * Last key passed to attempt().
	 */
	public ?string $lastKey = null;

	/**
	 * Result returned to the middleware under test.
	 */
	private RateLimitResult $result;

	/**
	 * Store the canned result returned by attempt().
	 *
	 * @param	RateLimitResult	$result	Rate-limit decision returned for every request.
	 */
	public function __construct(RateLimitResult $result) {

		$this->result = $result;

	}

	/**
	 * Record the resolved key and return the canned rate-limit decision.
	 *
	 * @param	string	$key	Resolved throttle key.
	 */
	public function attempt(string $key): RateLimitResult {

		$this->lastKey = $key;

		return $this->result;

	}

}
