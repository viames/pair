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

		// CriticalException initializes Throwable metadata here without re-entering escalation.
		if (is_a(static::class, CriticalException::class, true)) {
			parent::__construct($message, $code, $previous);
			return;
		}

		$criticalCodes = [
			ErrorCodes::DB_CONNECTION_FAILED,
			ErrorCodes::NO_VALID_TEMPLATE,
			ErrorCodes::LOADING_ENV_FILE,
			ErrorCodes::MYSQL_GENERAL_ERROR,
			ErrorCodes::MISSING_DB,
			ErrorCodes::MISSING_DB_TABLE
		];

		// run drastic steps to close the application
		if (in_array($code, $criticalCodes, true)) {
			throw new CriticalException($message, $code, $previous);
		}

		parent::__construct($message, $code, $previous);

		// intercept any previous message and track it
		$trackedMessage = ($previous and $previous->getMessage()) ? $previous->getMessage() : $message;
		$logger = Logger::getInstance();

		// Expired or regenerated sessions can legitimately miss the CSRF token, so avoid escalating them as errors.
		if (ErrorCodes::CSRF_TOKEN_NOT_FOUND === $code) {
			$logger->notice($trackedMessage, ['errorCode' => $code]);
		} else {
			$logger->error($trackedMessage, ['errorCode' => $code]);
		}

	}

	/**
	 * Return a user-facing localized message.
	 */
	protected static function localizeOutputMessage(string $message): string {

		$message = trim($message);

		if ('' === $message) {
			return Translator::safeDo('AN_ERROR_OCCURRED', null, 'An error occurred.');
		}

		// Low-level exception rendering must also work before database-backed locales are ready.
		$localized = Translator::safeDo($message, null, $message);

		if ($localized !== $message) {
			return $localized;
		}

		// avoid showing english hard-coded messages on non-english locales
		try {
			$currentLanguage = strtolower((string)Translator::getCurrentLanguageCode());
		} catch (\Throwable) {
			return $message;
		}

		if ($currentLanguage and !str_starts_with($currentLanguage, 'en')) {
			return Translator::safeDo('AN_ERROR_OCCURRED', null, 'An error occurred.');
		}

		return $message;

	}

	/**
	 * Build a concise technical message for non-production environments.
	 */
	protected static function buildThrowableDebugMessage(\Throwable $error): string {

		$messages = [];
		$currentError = $error;
		$fallbackMessage = Translator::safeDo('TECHNICAL_ERROR_WITHOUT_DETAILS', null, 'Technical error without details.');

		do {
			$message = trim((string)$currentError->getMessage());

			if ('' === $message) {
				$message = $fallbackMessage;
			}

			if (!in_array($message, $messages, true)) {
				$messages[] = $message;
			}

			$currentError = $currentError->getPrevious();
		} while ($currentError);

		return end($messages) ?: $fallbackMessage;

	}

	/**
	 * Front-end notification.
	 *
	 * @param string|\Throwable $error Messaggio semplice oppure eccezione completa da notificare.
	 */
	public static function frontEnd(string|\Throwable $error): void {

		if ($error instanceof \Throwable) {
			Logger::exceptionHandler($error);
		}

		// overwrite the message with a more user-friendly one in production
		if ('production' == Application::getEnvironment()) {
			$message = Translator::safeDo('AN_ERROR_OCCURRED', null, 'An error occurred.');
		} else if ($error instanceof \Throwable) {
			$message = static::buildThrowableDebugMessage($error);
		} else {
			$message = static::localizeOutputMessage($error);
		}
		
		$app = Application::getInstance();
		$router = Router::getInstance();
		
		// JSON error for AJAX requests or modal for web requests
		if ($app->headless) {
			Utilities::jsonError('INTERNAL_SERVER_ERROR',$message);
		} else {
			$app->modal(Translator::safeDo('ERROR', null, 'Error'), $message, 'error')
				->confirm(Translator::safeDo('OK', null, 'OK'));
		}

	}

}
