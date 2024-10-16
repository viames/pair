<?php

namespace Pair\Exception;

use Exception;

/**
 * Custom exception for handling errors when reading an ActiveRecord object to the database.
 */
class ActiveRecordReadException extends Exception {

	/**
	 * Error connecting to the database.
	 */
	const ERROR_CONNECTION_FAILED = 1001;

	/**
	 * Query failed.
	 */
	const ERROR_QUERY_FAILED = 1002;

	/**
	 * Unexpected error during reading.
	 */
	const ERROR_UNEXPECTED = 1003;

	/**
	 * Record not found.
	 */
	const ERROR_RECORD_NOT_FOUND = 1004;

	/**
	 * Constructor for the ActiveRecordReadException.
	 *
	 * @param string $message The error message, default to 'Error reading ActiveRecord from database'.
	 * @param int $code The error code, default to 0.
	 * @param Exception|NULL $previous Optional previous exception for exception chaining.
	 */
	public function __construct($message = 'Error reading ActiveRecord to database', $code = 0, Exception $previous = NULL) {

		parent::__construct($message, $code, $previous);

	}

}