<?php

namespace Pair\Exceptions;

class ErrorCodes {

	/**
	 * Error connecting to the database.
	 */
	const CONNECTION_FAILED = 1001;

	/**
	 * Duplicate of a unique or primary key.
	 */
	const DUPLICATE_ENTRY = 1002;

	/**
	 * Data validation failed.
	 */
	const VALIDATION_FAILED = 1003;

	/**
	 * Foreign key constraint violation.
	 */
	const FOREIGN_KEY_CONSTRAINT = 1004;

	/**
	 * Query failed.
	 */
	const QUERY_FAILED = 1005;

	/**
	 * Unexpected error during writing.
	 */
	const UNEXPECTED = 1006;

	/**
	 * Primary key is not populated, record creation failed.
	 */
	const PRIMARY_KEY_NOT_POPULATED = 1007;

	/**
	 * Composite primary key is not populated, record creation failed.
	 */
	const COMPOSITE_PRIMARY_KEY_NOT_POPULATED = 1008;

	/**
	 * Record not found.
	 */
	const RECORD_NOT_FOUND = 1009;

	/**
	 * Method not found.
	 */
	const METHOD_NOT_FOUND = 1010;

	/**
	 * Invalid date format.
	 */
	const INVALID_DATE_FORMAT = 1011;

	/**
	 * Error storing the record.
	 */
	const STORE_FAILED = 1012;

	/**
	 * Property not found.
	 */
	const PROPERTY_NOT_FOUND = 1013;

	/**
	 * Error code for controller not found.
	 */
	const CONTROLLER_NOT_FOUND = 1014;

	/**
	 * Error code for action not found.
	 */
	const ACTION_NOT_FOUND = 1015;

	/**
	 * Error code for view not found.
	 */
	const VIEW_NOT_FOUND = 1016;

	/**
	 * Error code for model not found.
	 */
	const ERROR_MODEL_NOT_FOUND = 1017;

	/**
	 * Error code for invalid data.
	 */
	const ERROR_INVALID_DATA = 1018;

	/**
	 * Error code for invalid request.
	 */
	const ERROR_INVALID_REQUEST = 1019;

	/**
	 * Error loading template file.
	 */
	const TEMPLATE_NOT_FOUND = 1020;

	/**
	 * Generic database connection error.
	 */
	const CONNECTION_ERROR = 1021;

	/**
	 * Generic query error.
	 */
	const QUERY_ERROR = 1022;

	/**
	 * Query timeout error.
	 */
	const TIMEOUT_ERROR = 1023;

	/**
	 * Invalid SQL query syntax.
	 */
	const INVALID_QUERY_SYNTAX = 1024;

	/**
	 * Class not found.
	 */
	const CLASS_NOT_FOUND = 1025;

	/**
	 * Missing database error.
	 */
	const MISSING_DATABASE = 1026;

	/**
	 * Missing table error.
	 */
	const MISSING_TABLE = 1027;

	/**
	 * Permission denied error.
	 */
	const PERMISSION_DENIED = 1028;

	/**
	 * Malformed select control.
	 */
	const MALFORMED_SELECT = 1029;

	/**
	 * Error loading .env configuration file.
	 */
	const LOADING_ENV = 1030;

}