<?php

namespace Pair\Exceptions;

/**
 * Custom exception for handling errors when writing an ActiveRecord object to the database.
 */
class ActiveRecordException extends \Exception {

	/**
	 * Error connecting to the database.
	 */
	const ERROR_CONNECTION_FAILED = 1001;

	/**
	 * Duplicate of a unique or primary key.
	 */
	const ERROR_DUPLICATE_ENTRY = 1002;

	/**
	 * Data validation failed.
	 */
	const ERROR_VALIDATION_FAILED = 1003;

	/**
	 * Foreign key constraint violation.
	 */
	const ERROR_FOREIGN_KEY_CONSTRAINT = 1004;

	/**
	 * Query failed.
	 */
	const ERROR_QUERY_FAILED = 1005;

	/**
	 * Unexpected error during writing.
	 */
	const ERROR_UNEXPECTED = 1006;

	/**
	 * Primary key is not populated, record creation failed.
	 */
	const ERROR_PRIMARY_KEY_NOT_POPULATED = 1007;

	/**
	 * Composite primary key is not populated, record creation failed.
	 */
	const ERROR_COMPOSITE_PRIMARY_KEY_NOT_POPULATED = 1008;

	/**
	 * Record not found.
	 */
	const ERROR_RECORD_NOT_FOUND = 1009;

	/**
	 * Method not found.
	 */
	const ERROR_METHOD_NOT_FOUND = 1010;

	/**
	 * Constructor for the ActiveRecordException.
	 *
	 * @param string $message The error message, default to 'Error writing ActiveRecord to database'.
	 * @param int $code The error code, default to 0.
	 * @param Exception|NULL $previous Optional previous exception for exception chaining.
	 */
	public function __construct($message = 'Error writing ActiveRecord to database', $code = 0, \Exception $previous = NULL) {

		parent::__construct($message, $code, $previous);

	}

}