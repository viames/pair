<?php

namespace Pair\Api;

/**
 * Executes a stack of Middleware instances in FIFO order.
 * Each middleware receives the request and a callable to the next handler.
 */
class MiddlewarePipeline {

	/**
	 * The middleware stack.
	 */
	private array $middlewares = [];

	/**
	 * Add a middleware to the pipeline.
	 */
	public function add(Middleware $middleware): static {

		$this->middlewares[] = $middleware;
		return $this;

	}

	/**
	 * Run the pipeline. Each middleware calls $next to pass control to the next one.
	 * The final callable is invoked after all middleware have run.
	 *
	 * @param	Request		$request		The current HTTP request.
	 * @param	callable	$destination	The final action to execute after all middleware.
	 */
	public function run(Request $request, callable $destination): void {

		$pipeline = $this->buildPipeline($destination);
		$pipeline($request);

	}

	/**
	 * Build the nested callable chain from the middleware stack.
	 * The last middleware wraps the destination; each previous one wraps the next.
	 */
	private function buildPipeline(callable $destination): callable {

		$next = $destination;

		// build from last to first so execution order is FIFO
		foreach (array_reverse($this->middlewares) as $middleware) {
			$next = function (Request $request) use ($middleware, $next) {
				$middleware->handle($request, $next);
			};
		}

		return $next;

	}

}
