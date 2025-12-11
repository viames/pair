<?php

namespace Pair\Exceptions;

use Pair\Core\Application;
use Pair\Helpers\Translator;

/**
 * Custom exception for handling application errors of the web-app GUI front-end; after logging the error, notify
 * the user with modal or JSON (for AJAX).
 */
class AppException extends PairException {

	/**
	 * Constructor for the ApiException.
	 *
	 * @param string $message The error message.
	 * @param int $code The error code.
	 * @param Throwable|null $previous Optional previous exception for exception chaining.
	 */
	public function __construct(string $message, int $code = 0, ?\Throwable $previous = null) {

		// for CSRF errors, notify the user with a modal and reload
		if (ErrorCodes::CSRF_TOKEN_INVALID == $code) {

			$app = Application::getInstance();
			$app->modal(Translator::do('ERROR'),Translator::do('SESSION_EXPIRED_RELOAD_PAGE'),'error')
				->confirm(Translator::do('OK'), 'location.reload()')
				->cancel(Translator::do('CANCEL'));

		} else {

			// notify the user
			self::frontEnd($message);

		}

		// track the error message
		parent::__construct($message, $code, $previous);

	}

}