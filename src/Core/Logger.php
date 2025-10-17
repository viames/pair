<?php

declare(strict_types=1);

namespace Pair\Core;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Helpers\LogBar;
use Pair\Helpers\Mailer;
use Pair\Helpers\Utilities;
use Pair\Models\ErrorLog;
use Pair\Orm\Database;
use Pair\Services\AmazonSes;
use Pair\Services\InsightHub;
use Pair\Services\SendMail;
use Pair\Services\SmtpMailer;
use Pair\Services\TelegramSender;

use Psr\Log\LoggerInterface;

use \Sentry\captureLastError as sentryCaptureLastError;
use \Sentry\init as sentryInit;

/**
 * Singleton object for managing internal logger functions. It can log errors in the database,
 * send e-mail and Telegram notifications, and log messages in LogBar.
 * It also provides custom error and exception handlers.
 * Requires Sentry SDK for error tracking: composer require sentry/sdk
 * Requires Insight Hub SDK (formerly Bugsnag) for error tracking: composer require bugsnag/bugsnag
 */
class Logger implements LoggerInterface {

	/**
	 * Singleton property.
	 */
	protected static ?self $instance = NULL;

	/**
	 * Default property is TRUE, can be disabled either by config or by method disable().
	 */
	protected bool $enabled = TRUE;

	/**
	 * If a log has a level equal or lower than this number, an e-mail is sent.
	 */
	protected int $emailThreshold = 4;

	/**
	 * E-mail recipients for error notifications.
	 */
	protected array $emailRecipients = [];

	/**
	 * Mailer object.
	 */
	protected ?Mailer $mailer = NULL;

	/**
	 * If a log has a level equal or lower than this number, a Telegram notification is sent.
	 */
	protected int $telegramThreshold = 4;

	/**
	 * Telegram bot token.
	 */
	protected ?string $telegramBotToken = NULL;

	/**
	 * Telegram chat ID recipients for error notifications.
	 */
	protected array $telegramChatIds = [];

	/**
	 * Description: The most critical level. Indicates that the system is completely unusable.
	 * Example: The entire platform is down or a critical dependency has failed.
	 * Typical Message: "System is unusable."
	 */
	const EMERGENCY = 1;

	/**
	 * Description: Requires immediate action.
	 * Example: Data loss or a critical security issue that needs attention right away.
	 * Typical Message: "Database connection lost."
	 */
	const ALERT = 2;

	/**
	 * Description: Critical conditions that may prevent key application features from working.
	 * Example: A critical service is down.
	 * Typical Message: "Payment system unavailable."
	 */
	const CRITICAL = 3;

	/**
	 * Description: Runtime errors that do not halt the application but require fixing.
	 * Example: An exception that was caught or a missing file.
	 * Typical Message: "File not found."
	 */
	const ERROR = 4;

	/**
	 * Description: Exceptional conditions that are not errors but should be looked into.
	 * Example: High memory usage or deprecated features being used.
	 * Typical Message: "Memory usage is high."
	 */
	const WARNING = 5;

	/**
	 * Description: Normal but noteworthy events that may be of interest for monitoring.
	 * Example: A configuration setting is suboptimal.
	 * Typical Message: "Using default configuration."
	 */
	const NOTICE = 6;

	/**
	 * Description: Informational messages about the system’s normal operations.
	 * Example: A user logs in or a connection is successfully established.
	 * Typical Message: "User logged in."
	 */
	const INFO = 7;

	/**
	 * Description: Detailed debugging information for development purposes.
	 * Example: API responses during testing or SQL query execution.
	 * Typical Message: "Query executed: SELECT * FROM users."
	 */
	const DEBUG = 8;

	/**
	 * Private constructor called by getInstance().
	 */
	private function __construct() {

		// can be disabled both by config and by disable() method
		if (TRUE === Env::get('PAIR_LOGGER_DISABLED')) {
			$this->enabled = FALSE;
		}

		if (Env::get('PAIR_LOGGER_EMAIL_RECIPIENTS')) {
			$this->emailRecipients = array_unique(Utilities::arrayToEmail(explode(',', Env::get('PAIR_LOGGER_EMAIL_RECIPIENTS'))));
		}

		if (Env::get('PAIR_LOGGER_EMAIL_THRESHOLD')) {
			$val = (int) Env::get('PAIR_LOGGER_EMAIL_THRESHOLD');
			if ($val < 1 or $val > 8) {
				$this->emailThreshold = 4; // default
				// avoid Logger->error() here: potential recursion
				trigger_error('Invalid PAIR_LOGGER_EMAIL_THRESHOLD; fallback to 4', E_USER_WARNING);
			} else {
				$this->emailThreshold = $val;
			}
		}

		if (Env::get('TELEGRAM_BOT_TOKEN')) {
			$this->telegramBotToken = Env::get('TELEGRAM_BOT_TOKEN');
		}

		if (Env::get('PAIR_LOGGER_TELEGRAM_CHAT_IDS')) {
			$this->telegramChatIds = array_unique(Utilities::arrayToInt(explode(',', (string)Env::get('PAIR_LOGGER_TELEGRAM_CHAT_IDS'))));
		}

		if (Env::get('PAIR_LOGGER_TELEGRAM_THRESHOLD')) {
			$val = (int) Env::get('PAIR_LOGGER_TELEGRAM_THRESHOLD');
			if ($val < 1 or $val > 8) {
				$this->telegramThreshold = 4;
				trigger_error('Invalid PAIR_LOGGER_TELEGRAM_THRESHOLD; fallback to 4', E_USER_WARNING);
			} else {
				$this->telegramThreshold = $val;
			}
		}

	}

	/**
	 * Requires immediate action.
	 * Example: Data loss or a critical security issue that needs attention right away.
	 * Typical Message: "Database connection lost."
	 */
	public function alert(\Stringable|string $message, array $context = []): void {

		$this->log(self::ALERT, $message, $context);

	}

	/**
	 * Check if the mailer is configured.
	 */
	private function checkMailer(): void {

		if (!$this->mailer) {
			$this->error('Mailer not configured.', ['errorCode' => ErrorCodes::INVALID_LOGGER_CONFIGURATION]);
		}

		$this->mailer->checkConfig();

	}

	/**
	 * Critical conditions that may prevent key application features from working.
	 * Example: A critical service is down.
	 * Typical Message: "Payment system unavailable."
	 */
	public function critical(\Stringable|string $message, array $context = []): void {

		$this->log(self::CRITICAL, $message, $context);

	}

	/**
	 * Detailed debugging information for development purposes.
	 * Example: API responses during testing or SQL query execution.
	 * Typical Message: "Query executed: SELECT * FROM users."
	 */
	public function debug(\Stringable|string $message, array $context = []): void {

		$this->log(self::DEBUG, $message, $context);

	}

	/**
	 * Disables the logger.
	 */
	public function disable(): void {

		$this->enabled = FALSE;

	}

	/**
	 * The most critical level. Indicates that the system is completely unusable.
	 * Example: The entire platform is down or a critical dependency has failed.
	 * Typical Message: "System is unusable."
	 */
	public function emergency(\Stringable|string $message, array $context = []): void {

		$this->log(self::EMERGENCY, $message, $context);

	}

	/**
	 * Custom error handler.
	 *
	 * @param	int		Error number.
	 * @param	string	Error text message.
	 * @param	string	Error full file path.
	 * @param	int		Error line.
	 * @param	array	(Optional) it will be passed an array that points to the active symbol table at the point the error occurred.
	 */
	public static function errorHandler(int $errno, string $errstr, ?string $errfile=NULL, ?int $errline=NULL): bool {

		// log the error internally
		$context = [
			'type'		=> $errno,
			'file'		=> $errfile ?? 'n/a',
			'line'		=> $errline ?? 0,
			'message'	=> $errstr
		];
		$fullMsg = 'Error {type}: {message} in {file} line {line}';
		$self = Logger::getInstance();
		$self->error($fullMsg, $context);

		// send the error to Sentry if enabled
		if (Env::get('SENTRY_DSN')) {
			sentryInit(['dsn' => Env::get('SENTRY_DSN'),'environment'=>Application::getEnvironment()]);
			sentryCaptureLastError();
		}

		// send the error to Insight Hub if enabled
		InsightHub::handle($errstr, $errno);

		 // in debug mode, allows the PHP internal error handler to run and display the error
		if (Env::get('APP_DEBUG')) {
			return FALSE;
		}

		// suppress the error and prevent it from being displayed
		return TRUE;

	}

	/**
	 * Runtime errors that do not halt the application but require fixing.
	 * Example: An exception that was caught or a missing file.
	 * Typical Message: "File not found."
	 * Log a critical error in LogBar, database and send notifications.
	 */
	public function error(\Stringable|string $message, array $context = []): void {

		$this->log(self::ERROR, $message, $context);

	}

	/**
	 * Custom exception handler for all uncaught exceptions.
	 */
	public static function exceptionHandler(\Throwable $e): void {

		// send the error to Sentry if enabled
		if (Env::get('SENTRY_DSN') and function_exists('\\Sentry\\init') and function_exists('\\Sentry\\captureLastError')) {
			sentryInit(['dsn' => Env::get('SENTRY_DSN'),'environment'=>Application::getEnvironment()]);
			sentryCaptureLastError();
		}

		// send the error to Insight Hub if enabled
		InsightHub::exception($e);

		// log the error internally
		$context = [
			'exception'	=> get_class($e),
			'file'		=> $e->getFile(),
			'line'		=> $e->getLine(),
			'message'	=> $e->getMessage(),
			'errorCode' => $e->getCode()
		];
		$fullMsg = '{exception}: {message} in {file} line {line}';
		$self = Logger::getInstance();
    	$self->error($fullMsg, $context);

	}

	/**
	 * Singleton method.
	 */
	public static function getInstance(): self {

		return self::$instance ??= new self();

	}

	/**
	 * Register an error in the database and send telegram and e-mail notifications.
	 *
	 * @param string	Description message of the error.
	 * @param int		PSR-3 log level number equivalent.
	 */
	private function handle(int $level, string $description, array $context=[], ?int $errorCode=NULL): void {

		if (!$this->enabled) {
			return;
		}

		// register error in database only if not a DB connection error
		$dbErrorCodes = [
			ErrorCodes::DB_CONNECTION_FAILED,
			ErrorCodes::MYSQL_GENERAL_ERROR,
			ErrorCodes::MISSING_DB,
		];

		if ((!$errorCode or ($errorCode and !in_array($errorCode, $dbErrorCodes, TRUE))) and Database::getInstance()->isConnected()) {
			$this->storeError($description, $level);
		}

		$levels = [
			self::EMERGENCY	=> 'Emergency',
			self::ALERT		=> 'Alert',
			self::CRITICAL	=> 'Critical',
			self::ERROR		=> 'Error',
			self::WARNING	=> 'Warning',
			self::NOTICE	=> 'Notice',
			self::INFO		=> 'Info',
			self::DEBUG		=> 'Debug'
		];

		$levelDescription = $levels[$level] ?? 'Unknown';

		// send e-mail, if level is below threshold and recipients are set
		if ($this->mailer and count($this->emailRecipients) and $level <= $this->emailThreshold) {

			$this->checkMailer();

			$app = Application::getInstance();

			$subject = $levelDescription . ' level in ' . Env::get('APP_NAME') . ' ' . Env::get('APP_VERSION') . ' ' . $app->getEnvironment();
			$title = 'Error occurred';
			$text = $subject . ' ' . $app->getEnvironment() . ' at ' . date('Y-m-d H:i:s') . "\n\n" . $description;
			$text .= "\n\nUser ID: " . ($app->currentUser->id ?? 'Guest');
			$text .= "\nUser IP: " . ($_SERVER['REMOTE_ADDR']     ?? 'CLI');
			$text .= "\nUser Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'n/a');
			$text .= "\nReferer: " . ($_SERVER['HTTP_REFERER']   ?? 'n/a');
			$text .= "\nYou can personalize the error notification threshold in the configuration file.";

			$this->mailer->send($this->emailRecipients, $subject, $title, $text);

		}

		// send Telegram notification, if level is below threshold and recipients are set
		if ($this->telegramThreshold >= $level and $this->telegramBotToken and count($this->telegramChatIds)) {

			$sender = new TelegramSender($this->telegramBotToken);

			$message = $levelDescription . ' level in ' . Env::get('APP_NAME') . ' ' . Env::get('APP_VERSION') . ' ' . Application::getEnvironment() . ' at ' . date('Y-m-d H:i:s');
			$message .= "\n\n" . $description;

			foreach ($this->telegramChatIds as $chatId) {
				if ($chatId>0) {
					$sender->message($chatId, $message);
				}
			}

		}

	}

	/**
	 * Informational messages about the system’s normal operations.
	 * Example: A user logs in or a connection is successfully established.
	 * Typical Message: "User logged in."
	 */
	public function info(\Stringable|string $message, array $context = []): void {

		$this->log(self::INFO, $message, $context);

	}

	/**
	 * Interpolate context values into the message {placeholders}.
	 */
    private function interpolate(string $message, array $context): string {

        $replace = [];

		foreach ($context as $key => $val) {

            if (is_null($val) or is_scalar($val) or (is_object($val) and method_exists($val, '__toString'))) {

				$replace['{'.$key.'}'] = (string)$val;

			}

		}

		return strtr($message, $replace);

	}

	public function log($level, \Stringable|string $message, array $context = []): void {

		$rendered = $this->interpolate((string)$message, $context);

		if ($level === self::ERROR or $level === self::CRITICAL or $level === self::ALERT or $level === self::EMERGENCY) {
			LogBar::event($rendered, 'error');
		} elseif ($level === self::WARNING) {
			LogBar::event($rendered, 'warning');
		} else {
			LogBar::event($rendered, 'notice');
		}

		// handle the notice if debug mode is enabled
		if ($level >= self::NOTICE or Env::get('APP_DEBUG')) {
			$this->handle($level, $rendered, $context);
		}

	}

	/**
	 * Normal but noteworthy events that may be of interest for monitoring.
	 * Example: A configuration setting is suboptimal.
	 * Typical Message: “Using default configuration.”
	 * Log a notice in LogBar.
	 */
	public function notice(\Stringable|string $message, array $context = []): void {

		$this->log(self::NOTICE, $message, $context);

	}

	/**
	 * Set Amazon SES configuration for sending error notification e-mails.
	 */
	public function setAmazonSesConfig(array $config): void {

		$this->mailer = new AmazonSes($config);

	}

	/**
	 * Set custom handlers for errors and uncaught exceptions.
	 */
	public static function setCustomErrorHandlers(): void {

		set_error_handler([self::class, 'errorHandler']);
		set_exception_handler([self::class, 'exceptionHandler']);
		register_shutdown_function([self::class, 'shutdownHandler']);

	}

	/**
	 * Set the e-mail recipients for error notifications.
	 */
	public function setEmailRecipients(array $recipients): void {

		$this->emailRecipients = array_unique(Utilities::arrayToEmail($recipients));

	}

	/**
	 * Set the e-mail notification threshold.
	 */
	public function setEmailThreshold(int $threshold): void {

		if ($threshold < 1 or $threshold > 8) {
			$this->error('Invalid E-mail error threshold value', ['errorCode' => ErrorCodes::INVALID_LOGGER_CONFIGURATION]);
		}

		$this->emailThreshold = $threshold;

	}

	/**
	 * Set Mailer configuration for sending error notification e-mails.
	 */
	public function setSendMailConfig(array $config): void {

		$this->mailer = new SendMail($config);

	}

	/**
	 * Set SmtpMailer configuration for sending error notification e-mails.
	 */
	public function setSmtpMailerConfig(array $config): void {

		$this->mailer = new SmtpMailer($config);

	}

	/**
	 * Set the Telegram bot token for sending error notifications.
	 */
	public function setTelegramBotToken(string $token): void {

		$this->telegramBotToken = $token;

	}

	/**
	 * Set the Telegram chat IDs for sending error notifications.
	 */
	public function setTelegramChatIds(array $chatIds): void {

		$this->telegramChatIds = array_unique(Utilities::arrayToInt($chatIds));

	}

	/**
	 * Set the Telegram notification threshold.
	 */
	public function setTelegramThreshold(int $threshold): void {

		if ($threshold < 1 or $threshold > 8) {
			$this->error('Invalid Telegram error threshold value', ['errorCode' => ErrorCodes::INVALID_LOGGER_CONFIGURATION]);
		}

		$this->telegramThreshold = $threshold;

	}

	/**
	 * Manages fatal errors (out of memory etc.) sending email to all address in options.
	 */
	public static function shutdownHandler(): void {

		$error = error_get_last();

		// premature exit without errors
		if (is_null($error) or !in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
			return;
		}

		// log the error internally
		$context = [
			'type'		=> $error['type'],
			'file'		=> $error['file'],
			'line'		=> $error['line'],
			'message'	=> $error['message'] ?? 'Unknown error'
		];
		$fullMsg = 'Fatal error [{type}]: {message} in {file} line {line}';
		$self = Logger::getInstance();
		$self->error($fullMsg, $context);

		// send the error to Sentry if enabled
		if (Env::get('SENTRY_DSN')) {
			sentryInit(['dsn' => Env::get('SENTRY_DSN'),'environment'=>Application::getEnvironment()]);
			sentryCaptureLastError();
		}

		// send the error to Insight Hub if enabled
		InsightHub::error(InsightHub::PHP_ERRORS[$error['type']] ?? 'FatalError', $error['message'] ?? 'Unknown error');

	}

	/**
	 * Allows to keep the current Application and browser state.
	 *
	 * @param	string	Description of the snapshot moment.
	 * @param	int		Optional PSR-3 log level number equivalent, default is 8 (DEBUG).
	 */
	private function storeError(string $description, ?int $level=NULL): void {

		if (!$this->enabled) {
			return;
		}

		if (!is_null($level) and ($level > 8 or $level < 1)) {
            $level = 8;
        }

		$app = Application::getInstance();
		$router = Router::getInstance();

		$errorLog = new ErrorLog();

		$errorLog->level 		= $level ?: self::DEBUG;
		$errorLog->userId		= $app->currentUser->id ?? NULL;
		$errorLog->path			= substr((string)$router->url,1);
		$errorLog->getData		= $_GET;
		$errorLog->postData		= $_POST;
		$errorLog->filesData	= $_FILES;
		$errorLog->cookieData	= []; //$_COOKIE;
		$errorLog->description	= substr($description, 0, 255);
		$errorLog->userMessages	= $app->getAllNotificationsMessages();

		if (isset($_SERVER['HTTP_REFERER'])) {

			// removes application base url from referer
			$errorLog->referer = (0 === strpos($_SERVER['HTTP_REFERER'], BASE_HREF))
				? substr($_SERVER['HTTP_REFERER'], strlen(BASE_HREF))
				: (string)$_SERVER['HTTP_REFERER'];

		} else {

			$errorLog->referer = '';

		}

		$errorLog->create();

	}

	/**
	 * Log a warning in LogBar.
	 */
	public function warning(\Stringable|string $message, array $context = []): void {

		$this->log(self::WARNING, $message, $context);

	}

}