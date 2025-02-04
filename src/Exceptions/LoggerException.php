<?php

namespace Pair\Exceptions;

/**
 * Custom exception for handling Logger errors.
 */
class LoggerException extends PairException {

	/**
	 * Constructor for the LoggerException.
	 *
	 * @param string $message The error message.
	 * @param int $code The error code, default to 0.
	 * @param Exception|NULL $previous Optional previous exception for exception chaining.
	 */
	public function __construct(string $message, int $code = 0, ?\Throwable $previous = NULL) {

		parent::__construct($message, $code, $previous);

	}

}