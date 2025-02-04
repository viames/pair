<?php

namespace Pair\Exceptions;

/**
 * Custom exception for handling errors in the view.
 */
class ViewException extends PairException {

	/**
	 * Constructor for the ViewException.
	 *
	 * @param string $message The error message, default to 'Error in view'.
	 * @param int $code The error code, default to 0.
	 * @param Exception|NULL $previous Optional previous exception for exception chaining.
	 */
	public function __construct(string $message = 'Error in view', int $code = 0, ?\Throwable $previous = NULL) {

		parent::__construct($message, $code, $previous);

	}

}