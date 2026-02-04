<?php

namespace Pair\Api;

/**
 * Middleware for handling Cross-Origin Resource Sharing (CORS).
 * Manages preflight OPTIONS requests and sets appropriate CORS headers.
 */
class CorsMiddleware implements Middleware {

	/**
	 * List of allowed origins. Use ['*'] to allow all origins.
	 */
	private array $allowedOrigins;

	/**
	 * List of allowed HTTP methods.
	 */
	private array $allowedMethods;

	/**
	 * List of allowed request headers.
	 */
	private array $allowedHeaders;

	/**
	 * Max age in seconds for preflight cache.
	 */
	private int $maxAge;

	/**
	 * Create a new CORS middleware instance.
	 *
	 * @param	array	$options	Configuration options: allowedOrigins, allowedMethods,
	 *								allowedHeaders, maxAge.
	 */
	public function __construct(array $options = []) {

		$this->allowedOrigins = $options['allowedOrigins'] ?? ['*'];
		$this->allowedMethods = $options['allowedMethods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
		$this->allowedHeaders = $options['allowedHeaders'] ?? ['Content-Type', 'Authorization', 'X-Requested-With'];
		$this->maxAge = $options['maxAge'] ?? 86400;

	}

	/**
	 * Handle the request by setting CORS headers. Responds to OPTIONS preflight
	 * with 204 No Content. Passes other requests to the next handler.
	 */
	public function handle(Request $request, callable $next): void {

		$this->setCorsHeaders($request);

		// handle preflight request
		if ($request->method() === 'OPTIONS') {
			http_response_code(204);
			exit();
		}

		$next($request);

	}

	/**
	 * Set CORS response headers based on the configuration and request origin.
	 */
	private function setCorsHeaders(Request $request): void {

		$origin = $request->header('Origin');

		// determine the allowed origin header value
		if (in_array('*', $this->allowedOrigins)) {
			header('Access-Control-Allow-Origin: *');
		} else if ($origin and in_array($origin, $this->allowedOrigins)) {
			header('Access-Control-Allow-Origin: ' . $origin);
			header('Vary: Origin');
		}

		header('Access-Control-Allow-Methods: ' . implode(', ', $this->allowedMethods));
		header('Access-Control-Allow-Headers: ' . implode(', ', $this->allowedHeaders));
		header('Access-Control-Max-Age: ' . $this->maxAge);

	}

}
