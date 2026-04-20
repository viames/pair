<?php

declare(strict_types=1);

namespace Pair\Http;

/**
 * Explicit response contract handled by the v4 request flow.
 */
interface ResponseInterface {

	/**
	 * Send the response to the current output channel.
	 */
	public function send(): void;

}
