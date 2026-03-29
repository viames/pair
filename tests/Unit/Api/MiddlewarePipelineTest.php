<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Api\Middleware;
use Pair\Api\MiddlewarePipeline;
use Pair\Api\Request;
use Pair\Tests\Support\TestCase;

/**
 * Covers middleware ordering and short-circuit behavior without involving the MVC stack.
 */
class MiddlewarePipelineTest extends TestCase {

	/**
	 * Verify the middleware pipeline executes in FIFO order.
	 */
	public function testRunExecutesMiddlewaresInFifoOrder(): void {

		$trace = new \ArrayObject();
		$pipeline = new MiddlewarePipeline();
		$request = new Request();

		$pipeline->add(new class($trace) implements Middleware {

			/**
			 * Shared trace storage.
			 */
			private \ArrayObject $trace;

			/**
			 * Store the trace storage.
			 *
			 * @param	\ArrayObject	$trace	Execution trace.
			 */
			public function __construct(\ArrayObject $trace) {

				$this->trace = $trace;

			}

			/**
			 * Push before/after markers around the next middleware.
			 *
			 * @param	Request		$request	Current request.
			 * @param	callable	$next		Next middleware.
			 */
			public function handle(Request $request, callable $next): void {

				$this->trace[] = 'first:before';
				$next($request);
				$this->trace[] = 'first:after';

			}

		});

		$pipeline->add(new class($trace) implements Middleware {

			/**
			 * Shared trace storage.
			 */
			private \ArrayObject $trace;

			/**
			 * Store the trace storage.
			 *
			 * @param	\ArrayObject	$trace	Execution trace.
			 */
			public function __construct(\ArrayObject $trace) {

				$this->trace = $trace;

			}

			/**
			 * Push before/after markers around the next middleware.
			 *
			 * @param	Request		$request	Current request.
			 * @param	callable	$next		Next middleware.
			 */
			public function handle(Request $request, callable $next): void {

				$this->trace[] = 'second:before';
				$next($request);
				$this->trace[] = 'second:after';

			}

		});

		$pipeline->run($request, function (Request $request) use ($trace): void {

			$trace[] = 'destination';

		});

		$this->assertSame([
			'first:before',
			'second:before',
			'destination',
			'second:after',
			'first:after',
		], $trace->getArrayCopy());

	}

	/**
	 * Verify a middleware can stop the chain without invoking the destination.
	 */
	public function testRunAllowsMiddlewareShortCircuit(): void {

		$trace = new \ArrayObject();
		$pipeline = new MiddlewarePipeline();
		$request = new Request();

		$pipeline->add(new class($trace) implements Middleware {

			/**
			 * Shared trace storage.
			 */
			private \ArrayObject $trace;

			/**
			 * Store the trace storage.
			 *
			 * @param	\ArrayObject	$trace	Execution trace.
			 */
			public function __construct(\ArrayObject $trace) {

				$this->trace = $trace;

			}

			/**
			 * Stop the chain explicitly to emulate a blocking middleware.
			 *
			 * @param	Request		$request	Current request.
			 * @param	callable	$next		Next middleware.
			 */
			public function handle(Request $request, callable $next): void {

				$this->trace[] = 'blocked';

			}

		});

		$pipeline->run($request, function (Request $request) use ($trace): void {

			$trace[] = 'destination';

		});

		$this->assertSame(['blocked'], $trace->getArrayCopy());

	}

}
