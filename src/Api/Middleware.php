<?php

namespace Pair\Api;

/**
 * Interface for API middleware. Middleware can inspect and modify the request,
 * short-circuit by sending a response, or pass control to the next handler.
 */
interface Middleware {

	/**
	 * Handle the incoming request.
	 *
	 * @param	Request		$request	The current HTTP request.
	 * @param	callable	$next		The next middleware or final action.
	 */
	public function handle(Request $request, callable $next): void;

}
