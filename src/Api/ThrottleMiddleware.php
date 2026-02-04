<?php

namespace Pair\Api;

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
	 * Handle the request. Checks rate limit by client IP.
	 * If the limit is exceeded, sends a 429 error. Otherwise, records the hit
	 * and passes the request to the next handler.
	 */
	public function handle(Request $request, callable $next): void {

		$key = 'throttle:' . $request->ip();

		if ($this->limiter->tooManyAttempts($key)) {
			ApiResponse::error('TOO_MANY_REQUESTS');
		}

		$this->limiter->hit($key);

		$next($request);

	}

}
