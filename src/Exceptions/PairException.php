<?php

namespace Pair\Exceptions;

/**
 * Custom exception for handling errors when writing an ActiveRecord object to the database.
 */
class PairException extends \Exception {

	/**
	 * Constructor for the PairException.
	 *
	 * @param string $message The error message.
	 * @param int $code The error code.
	 * @param Exception|NULL $previous Optional previous exception for exception chaining.
	 */
	public function __construct(string $message, int $code = 0, ?\Throwable $previous = NULL) {

		parent::__construct($message, $code, $previous);

	}

}