<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\Application;
use Pair\Core\Router;
use Pair\Models\User;
use Pair\Tests\Support\TestCase;

/**
 * Covers browser-facing security helpers in the Application runtime.
 */
class ApplicationSecurityTest extends TestCase {

	/**
	 * Verify baseline security headers stay compatible with legacy script loading.
	 */
	public function testSecurityHeadersIncludeNonBreakingBrowserHardening(): void {

		$headers = $this->invokeApplicationStatic('securityHeaders');

		$this->assertSame('nosniff', $headers['X-Content-Type-Options']);
		$this->assertSame('SAMEORIGIN', $headers['X-Frame-Options']);
		$this->assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy']);
		$this->assertSame("frame-ancestors 'self'; base-uri 'self'; object-src 'none'", $headers['Content-Security-Policy']);
		$this->assertSame('geolocation=(), camera=()', $headers['Permissions-Policy']);
		$this->assertStringNotContainsString('script-src', $headers['Content-Security-Policy']);

	}

	/**
	 * Verify internal redirect sanitization rejects external and ambiguous URL forms.
	 */
	public function testSanitizeInternalRedirectUrlRejectsExternalTargets(): void {

		$this->assertSame('dashboard/default', Application::sanitizeInternalRedirectUrl('dashboard/default'));
		$this->assertSame('/dashboard/default', Application::sanitizeInternalRedirectUrl('/dashboard/default'));
		$this->assertSame('orders/default?state=open', Application::sanitizeInternalRedirectUrl('orders/default?state=open'));

		$this->assertNull(Application::sanitizeInternalRedirectUrl('https://evil.example/login'));
		$this->assertNull(Application::sanitizeInternalRedirectUrl('//evil.example/login'));
		$this->assertNull(Application::sanitizeInternalRedirectUrl('javascript:alert(1)'));
		$this->assertNull(Application::sanitizeInternalRedirectUrl("dashboard/default\nLocation: https://evil.example"));
		$this->assertNull(Application::sanitizeInternalRedirectUrl('admin\\evil'));
		$this->assertNull(Application::sanitizeInternalRedirectUrl(''));

	}

	/**
	 * Verify anonymous requests never resolve an ACL-backed user landing route.
	 */
	public function testAnonymousUserDoesNotResolveLandingPage(): void {

		if (!defined('URL_PATH')) {
			define('URL_PATH', null);
		}
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/';
		$app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
		$user = new class extends User {

			/** Whether the landing lookup was requested. */
			public bool $landingRequested = false;

			/** Record an unexpected landing lookup without touching the database. */
			public function landing(): ?\stdClass {

				$this->landingRequested = true;

				return (object)['module' => 'dashboard', 'action' => 'default'];

			}

		};
		$currentUser = new \ReflectionProperty(Application::class, 'currentUser');
		$currentUser->setValue($app, $user);
		$router = Router::getInstance();
		$router->module = null;
		$router->action = null;

		$method = new \ReflectionMethod(Application::class, 'initializeLandingPage');
		$method->invoke($app);

		$this->assertFalse($user->landingRequested);
		$this->assertNull($router->module);

	}

	/**
	 * Invoke a non-public static Application method for focused unit assertions.
	 *
	 * @param	string	$method	Static method to invoke.
	 * @param	array<int,mixed>	$args	Arguments to pass to the method.
	 */
	private function invokeApplicationStatic(string $method, array $args = []): mixed {

		$reflection = new \ReflectionMethod(Application::class, $method);

		return $reflection->invoke(null, ...$args);

	}

}
