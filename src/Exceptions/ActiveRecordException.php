<?php

namespace Pair\Exceptions;

/**
 * Custom exception for handling errors when writing an ActiveRecord object to the database.
 */
class ActiveRecordException extends PairException {

	/**
	 * Constructor for the ActiveRecordException.
	 *
	 * @param string $message The error message, default to 'Error writing ActiveRecord to database'.
	 * @param int $code The error code, default to 0.
	 * @param Exception|NULL $previous Optional previous exception for exception chaining.
	 */
	public function __construct(string $message = 'Error writing ActiveRecord to database', int $code = 0, ?\Throwable $previous = NULL) {

		parent::__construct($message, $code, $previous);

	}

}