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
use Pair\Services\Sendmail;
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
	protected static ?self $instance = null;

	/**
	 * Whether the logger is enabled. Can be turned off by config or by method disable().
	 */
	protected bool $enabled = true;

	/**
	 * If a log has a level equal or lower than this number, an e-mail is sent.
	 */
	protected int $emailThreshold = 4;

	/**
	 * E-mail recipients for alerts.
	 */
	protected array $emailRecipients = [];

	/**
	 * Mailer object.
	 */
	protected ?Mailer $mailer = null;

	/**
	 * If a log has a level equal or lower than this number, a Telegram notification is sent.
	 */
	protected int $telegramThreshold = 4;

	/**
	 * Telegram bot token.
	 */
	protected ?string $telegramBotToken = null;

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

	const LEVEL_NAMES = [
		self::EMERGENCY	=> 'emergency',
		self::ALERT		=> 'alert',
		self::CRITICAL	=> 'critical',
		self::ERROR		=> 'error',
		self::WARNING	=> 'warning',
		self::NOTICE	=> 'notice',
		self::INFO		=> 'info',
		self::DEBUG		=> 'debug'
	];

	/**
	 * Private constructor called by getInstance().
	 */
	private function __construct() {

		// can be disabled both by config and by disable() method
		if (true === Env::get('PAIR_LOGGER_DISABLED')) {
			$this->enabled = false;
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

		$this->enabled = false;

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
	 * Verify mailer configuration and throw on misconfiguration.
	 */
	private function ensureMailer(): void {

		if (!$this->mailer) {
			$this->error('Mailer not configured.', ['errorCode' => ErrorCodes::INVALID_LOGGER_CONFIGURATION]);
		}

		$this->mailer->checkConfig();

	}

	/**
	 * Custom PHP error handler. Returns true to suppress the default PHP handler; false to allow it.
	 *
	 * @param	int		Error number.
	 * @param	string	Error text message.
	 * @param	string	Error full file path.
	 * @param	int		Error line.
	 */
	public static function errorHandler(int $errno, string $errstr, ?string $errfile = null, ?int $errline = null): bool {

		// respect error suppression (@) and current error_reporting mask
		if (!(error_reporting() & $errno)) {
			return true;
		}

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
			return false;
		}

		// suppress the error and prevent it from being displayed
		return true;

	}

	/**
	 * Runtime errors that need attention but do not halt execution.
	 * Example: An exception that was caught or a missing file.
	 * Typical Message: "File not found."
	 */
	public function error(\Stringable|string $message, array $context = []): void {

		$this->log(self::ERROR, $message, $context);

	}

	/**
	 * Handle uncaught exceptions; optionally forward to Sentry/InsightHub and log internally.
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
	 * Return the singleton Logger instance.
	 */
	public static function getInstance(): self {

		return self::$instance ??= new self();

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
	 * Replace {placeholders} with scalar/Stringable values from $context; ignore others.
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

	/**
	 * PSR-3 core method. Log with an arbitrary level; interpolates {placeholders} from $context.
	 */
	public function log(mixed $level, \Stringable|string $message, array $context = []): void {

		if (!$this->enabled) {
			return;
		}

		// convert level name to number
		if (is_string($level)) {
			$level = array_search(strtolower($level), self::LEVEL_NAMES, true) ?: self::DEBUG;
		} else if (!is_int($level) or $level < 1 or $level > 8) {
			$level = self::DEBUG;
		}

		// render the message with context values
		$rendered = $this->interpolate((string)$message, $context);
		$errorCode = $context['errorCode'] ?? null;

		// log the message in LogBar
		if (in_array($level, [self::DEBUG, self::INFO, self::NOTICE], true)) {
			LogBar::event($rendered, 'notice');
		} else if ($level === self::WARNING) {
			LogBar::event($rendered, 'warning');
		} else {
			LogBar::event($rendered, 'error');
		}

		// process the message (store in DB, send notifications) if level is WARNING or worse
		if ($level <= self::WARNING) {
			$this->process($level, $rendered, $context, $errorCode);
		}

	}

	/**
	 * Normal but noteworthy events.
	 * Example: A configuration setting is suboptimal.
	 * Typical Message: “Using default configuration.”
	 */
	public function notice(\Stringable|string $message, array $context = []): void {

		$this->log(self::NOTICE, $message, $context);

	}

	/**
	 * Persist the log record and send notifications according to thresholds.
	 *
	 * @param string	Description message of the error.
	 * @param int		PSR-3 log level number equivalent.
	 * @param array		Context array.
	 * @param int|null	Optional error code to avoid logging certain errors.
	 */
	private function process(int $level, string $description, array $context = [], ?int $errorCode = null): void {

		// prefer explicit parameter, but allow fallback to context entry
		$errorCode ??= $context['errorCode'] ?? null;

		// register error in database only if not a DB connection error
		$dbErrorCodes = [
			ErrorCodes::DB_CONNECTION_FAILED,
			ErrorCodes::MYSQL_GENERAL_ERROR,
			ErrorCodes::MISSING_DB,
		];

		if ((!$errorCode or ($errorCode and !in_array($errorCode, $dbErrorCodes, true))) and Database::getInstance()->isConnected()) {
			$this->snapshot($description, $level);
		}

		$levelDescription = self::LEVEL_NAMES[$level] ?? 'Unknown';

		// send e-mail, if level is below threshold and recipients are set
		if ($this->mailer and count($this->emailRecipients) and $level <= $this->emailThreshold) {

			$this->ensureMailer();

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
		if ($errorCode !== ErrorCodes::TELEGRAM_FAILURE and $this->telegramThreshold >= $level and $this->telegramBotToken and count($this->telegramChatIds)) {

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
	 * Register error, exception, and shutdown handlers.
	 */
	public static function registerHandlers(): void {

		set_error_handler([self::class, 'errorHandler']);
		set_exception_handler([self::class, 'exceptionHandler']);
		register_shutdown_function([self::class, 'shutdownHandler']);

	}

	/**
	 * Configure Amazon SES transport for error emails.
	 */
	private function setSesConfig(array $config): void {

		$this->mailer = new AmazonSes($config);

	}

	/**
	 * Set the e-mail recipients for error notifications.
	 */
	public function setEmailRecipients(array $recipients): void {

		$this->emailRecipients = array_unique(Utilities::arrayToEmail($recipients));

	}

	/**
	 * Set the e-mail maximum level number that triggers notifications (1=most severe).
	 */
	public function setEmailThreshold(int $level): void {

		if ($level < 1 or $level > 8) {
			$this->error('Invalid E-mail error threshold value', ['errorCode' => ErrorCodes::INVALID_LOGGER_CONFIGURATION]);
		}

		$this->emailThreshold = $level;

	}

	/**
	 * Configure sendmail transport for error emails.
	 */
	private function setSendmailConfig(array $config): void {

		$this->mailer = new Sendmail($config);

	}

	/**
	 * Set SmtpMailer configuration for sending error notification e-mails.
	 */
	private function setSmtpMailerConfig(array $config): void {

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
	 * Set the Telegram maximum level number that triggers notifications (1=most severe).
	 */
	public function setTelegramThreshold(int $level): void {

		if ($level < 1 or $level > 8) {
			$this->error('Invalid Telegram error threshold value', ['errorCode' => ErrorCodes::INVALID_LOGGER_CONFIGURATION]);
		}

		$this->telegramThreshold = $level;

	}

	/**
	 * Handle fatal errors on shutdown and notify configured recipients.
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
	 * Persist a snapshot of request/app state to ErrorLog. Level defaults to DEBUG (1–8 bounds).
	 *
	 * @param	string	Description of the snapshot moment.
	 * @param	int		Optional PSR-3 log level number equivalent, default is 8 (DEBUG).
	 */
	private function snapshot(string $description, ?int $level = null): void {

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
		$errorLog->userId		= $app->currentUser->id ?? null;
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
	 * Configure the transport to use for sending error notification e-mails.
	 */
	public function useTransport(string $type, array $config): void {

		switch ($type) {
			case 'smtp':
				$this->setSmtpMailerConfig($config);
				break;
			case 'ses':
				$this->setSesConfig($config);
				break;
			case 'sendmail':
				$this->setSendmailConfig($config);
				break;
			default:
				throw new InvalidArgumentException('Unknown transport type: ' . $type);
		}

	}

	/**
	 * Exceptional occurrences that are not errors.
	 */
	public function warning(\Stringable|string $message, array $context = []): void {

		$this->log(self::WARNING, $message, $context);

	}

}
