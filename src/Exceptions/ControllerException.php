<?php

namespace Pair\Exceptions;

/**
 * Custom exception for handling errors in the controllers.
 */
class ControllerException extends PairException {

	/**
	 * Constructor for the ControllerException.
	 *
	 * @param string $message The error message, default to 'Error in controller'.
	 * @param int $code The error code, default to 0.
	 * @param Exception|NULL $previous Optional previous exception for exception chaining.
	 */
	public function __construct(string $message = 'Error in controller', int $code = 0, ?\Throwable $previous = NULL) {

		parent::__construct($message, $code, $previous);

	}

}