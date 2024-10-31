<?php

namespace Pair\Exceptions;

use Exception;

/**
 * Custom exception for handling errors when writing an ActiveRecord object to the database.
 */
class DatabaseException extends Exception {

	/**
	 * Generic database connection error.
	 */
	const CONNECTION_ERROR = 1001;

	/**
	 * Generic query error.
	 */
	const QUERY_ERROR = 1002;

	/**
	 * Query timeout error.
	 */
	const TIMEOUT_ERROR = 1003;

	/**
	 * Invalid SQL query syntax.
	 */
	const INVALID_QUERY_SYNTAX = 1004;

	/**
	 * Duplicate entry error.
	 */
	const DUPLICATE_ENTRY = 1005;

	/**
	 * Missing database error.
	 */
	const MISSING_DATABASE = 1006;

	/**
	 * Missing table error.
	 */
	const MISSING_TABLE = 1007;

	/**
	 * Permission denied error.
	 */
	const PERMISSION_DENIED = 1008;

	/**
	 * Constructor for the DatabaseException.
	 *
	 * @param string $message The error message, default to 'Database error'.
	 * @param int $code The error code, default to 0.
	 * @param Exception|NULL $previous Optional previous exception for exception chaining.
	 */
	public function __construct($message = 'Database error', $code = 0, Exception $previous = NULL) {

		parent::__construct($message, $code, $previous);

	}

}