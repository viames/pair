<?php

namespace Pair\Api;

use Pair\Core\Application;

/**
 * Middleware that throttles API requests using the RateLimiter.
 * Returns 429 Too Many Requests when the limit is exceeded.
 */
class ThrottleMiddleware implements Middleware {

	/**
	 * The rate limiter instance.
	 */
	private RateLimiter $limiter;

	/**
	 * Create a new throttle middleware instance.
	 *
	 * @param	int	$maxAttempts	Maximum requests within the window (default 60).
	 * @param	int	$decaySeconds	Window duration in seconds (default 60).
	 */
	public function __construct(int $maxAttempts = 60, int $decaySeconds = 60) {

		$this->limiter = new RateLimiter($maxAttempts, $decaySeconds);

	}

	/**
	 * Handle the request. Checks rate limit by the best available client identity:
	 * session, bearer token, authenticated user, and finally client IP.
	 * If the limit is exceeded, sends a 429 error. Otherwise, records the hit
	 * and passes the request to the next handler.
	 */
	public function handle(Request $request, callable $next): void {

		$key = $this->resolveKey($request);
		$result = $this->limiter->attempt($key);
		$result->applyHeaders();

		if (!$result->allowed) {
			ApiResponse::error('TOO_MANY_REQUESTS', [
				'retryAfter' => $result->retryAfter,
				'resetAt' => $result->resetAt,
			]);
		}

		$next($request);

	}

	/**
	 * Build the throttle key from the most stable identity available for the request.
	 * Sensitive credentials are hashed before they are used as part of the storage key.
	 */
	private function resolveKey(Request $request): string {

		$sessionId = trim((string)$request->query('sid', ''));

		if (strlen($sessionId)) {
			return 'throttle:session:' . hash('sha256', $sessionId);
		}

		$bearerToken = $request->bearerToken();

		if (!is_null($bearerToken) and strlen(trim($bearerToken))) {
			return 'throttle:bearer:' . hash('sha256', trim($bearerToken));
		}

		$app = Application::getInstance();
		$user = $app->currentUser;

		if ($user and $user->isLoaded()) {
			return 'throttle:user:' . $user->id;
		}

		return 'throttle:ip:' . $request->ip();

	}

}
