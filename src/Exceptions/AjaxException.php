<?php

namespace Pair\Exceptions;

use Pair\Core\Application;
use Pair\Core\Logger;
use Pair\Helpers\Translator;
use Pair\Helpers\Utilities;

/**
 * Custom exception for handling AJAX errors, useful for logging the error message of AJAX requests.
 */
class AjaxException extends PairException {

	/**
	 * Constructor for the AjaxException.
	 *
	 * @param string $message The error message.
	 * @param int $code The error code.
	 * @param Throwable|NULL $previous Optional previous exception for exception chaining.
	 */
	public function __construct(string $message, int $code = 0, ?\Throwable $previous = NULL) {

		$trackedMessage = ($previous and $previous->getMessage()) ? $previous->getMessage() : $message;
		Logger::error($trackedMessage, Logger::ERROR, $code);

	}

	/**
	 * Send the error message to the client via JSON response.
	 */
	public static function reply(string $message, ?int $code = NULL, int $httpCode = 400): void {

		// overwrite the message with a more user-friendly one in production
		if ('production' == Application::getEnvironment()) {
			$message = Translator::do('AN_ERROR_OCCURRED');
		}

		// send the error message to the client
		Utilities::jsonError($code, $message, $httpCode);

	}

}