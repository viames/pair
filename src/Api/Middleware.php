<?php

namespace Pair\Api;

/**
 * Interface for API middleware. Middleware can inspect and modify the request,
 * short-circuit by returning a response, or pass control to the next handler.
 */
interface Middleware {

	/**
	 * Handle the incoming request.
	 *
	 * @param	Request		$request	The current HTTP request.
	 * @param	callable	$next		The next middleware or final action.
	 * @return	mixed				An explicit response object or the result of the next pipeline stage.
	 */
	public function handle(Request $request, callable $next): mixed;

}
