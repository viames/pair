<?php

namespace Pair\Exceptions;

use Pair\Core\Application;
use Pair\Core\Logger;
use Pair\Core\Router;
use Pair\Helpers\Translator;
use Pair\Helpers\Utilities;

/**
 * Custom exception for handling errors in the Pair Framework.
 */
class PairException extends \Exception {

	/**
	 * Tracks the error message in the LogBar, takes a system snapshot and sends e-mail/telegram
	 * notifications. In case of critical errors, throws a CriticalException.
	 *
	 * @param string The error message.
	 * @param int The error code.
	 * @param Throwable|null Optional previous exception for exception chaining.
	 */
	public function __construct(string $message, int $code = 0, ?\Throwable $previous = null) {

		$criticalCodes = [
			ErrorCodes::DB_CONNECTION_FAILED,
			ErrorCodes::NO_VALID_TEMPLATE,
			ErrorCodes::LOADING_ENV_FILE,
			ErrorCodes::MYSQL_GENERAL_ERROR,
			ErrorCodes::MISSING_DB,
			ErrorCodes::MISSING_DB_TABLE
		];

		// run drastic steps to close the application
		if (in_array($code, $criticalCodes)) {
			throw new CriticalException($message, $code, $previous);
		}

		// intercept any previous message and track it
		$trackedMessage = ($previous and $previous->getMessage()) ? $previous->getMessage() : $message;
		$logger = Logger::getInstance();
		$logger->error($trackedMessage, ['errorCode' => $code]);

	}

	/**
	 * Return a user-facing localized message.
	 */
	protected static function localizeOutputMessage(string $message): string {

		$message = trim($message);

		if ('' === $message) {
			return Translator::do('AN_ERROR_OCCURRED');
		}

		// if message is a translation key, translate it
		$localized = Translator::do($message, null, false, $message);

		if ($localized !== $message) {
			return $localized;
		}

		// avoid showing english hard-coded messages on non-english locales
		$currentLanguage = strtolower((string)Translator::getCurrentLanguageCode());

		if ($currentLanguage and !str_starts_with($currentLanguage, 'en')) {
			return Translator::do('AN_ERROR_OCCURRED');
		}

		return $message;

	}

	/**
	 * Front-end notification.
	 */
	public static function frontEnd(string $message): void {

		// overwrite the message with a more user-friendly one in production
		if ('production' == Application::getEnvironment()) {
			$message = Translator::do('AN_ERROR_OCCURRED');
		} else {
			$message = static::localizeOutputMessage($message);
		}
		
		$app = Application::getInstance();
		$router = Router::getInstance();
		
		// JSON error for AJAX requests or modal for web requests
		if ($app->headless) {
			Utilities::jsonError('INTERNAL_SERVER_ERROR',$message);
		} else {
			$app->modal(Translator::do('ERROR'), $message, 'error')->confirm(Translator::do('OK'));
		}

	}

}
