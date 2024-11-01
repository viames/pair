<?php

namespace Pair\Exceptions;

use Exception;

/**
 * Custom exception for handling errors in the controllers.
 */
class ControllerException extends Exception {

	/**
	 * Error code for controller not found.
	 */
	const ERROR_CONTROLLER_NOT_FOUND = 1;

	/**
	 * Error code for action not found.
	 */
	const ERROR_ACTION_NOT_FOUND = 2;

	/**
	 * Error code for view not found.
	 */
	const ERROR_VIEW_NOT_FOUND = 3;

	/**
	 * Error code for model not found.
	 */
	const ERROR_MODEL_NOT_FOUND = 4;

	/**
	 * Error code for invalid data.
	 */
	const ERROR_INVALID_DATA = 5;

	/**
	 * Error code for invalid request.
	 */
	const ERROR_INVALID_REQUEST = 6;

	/**
	 * Unexpected error code.
	 */
	const ERROR_UNEXPECTED = 7;

	/**
	 * Constructor for the ControllerException.
	 *
	 * @param string $message The error message, default to 'Error in controller'.
	 * @param int $code The error code, default to 0.
	 * @param Exception|NULL $previous Optional previous exception for exception chaining.
	 */
	public function __construct($message = 'Error in controller', $code = 0, Exception $previous = NULL) {

		parent::__construct($message, $code, $previous);

	}

}