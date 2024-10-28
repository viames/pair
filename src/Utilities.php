<?php

namespace Pair;

/**
 * Collection of useful methods for both internal framework needs and common application needs.
 */
class Utilities {

	/**
	 * List of characters allowed for the composition of a random string.
	 */
	const RANDOM_STRING_CHARS = '123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	/**
	 * Custom error handler.
	 *
	 * @param	int		Error number.
	 * @param	string	Error text message.
	 * @param	string	Error full file path.
	 * @param	int		Error line.
	 */
	public static function customErrorHandler(int $errno, string $errstr, string $errfile, string $errline): void {

		$backtrace = self::getDebugBacktrace();

		// command line log has date and plain text
		if ('cli' == php_sapi_name()) {

			print "\n" . date(DATE_RFC2822) . "\n";
			print "Error [" . $errno . ']: ' . $errstr . ' (' . $errfile . ' line ' . $errline . ")\n";

			if ($backtrace) {
				print "Debug backtrace:\n" . implode("\n", $backtrace) . "\n";
		}

		// list all errors in framework log
		} else {

			if (Options::get('show_log')) {

				// start to show detailed error in log event
				Logger::error('Debug backtrace for “' . $errstr .  '” in ' . $errfile . ' line ' . $errline);

				foreach ($backtrace as $event) {
					Logger::event($event);
				}

				Logger::event('Debug backtrace finished');

			// show error in app because log is disabled
			} else {

				$app = Application::getInstance();
				$app->enqueueError($errstr . ' (' . $errfile . ' line ' . $errline .')', 'Error [' . $errno . ']');

			}

		}

		if (defined('SENTRY_DSN') and SENTRY_DSN) {
			\Sentry\captureLastError();
			Logger::event('The error was sent to Sentry');
		}

	}

	/**
	 * Manages fatal errors (out of memory etc.) sending email to all address in options.
	 */
	public static function fatalErrorHandler(): void {

		// get fatal error array
		$error = error_get_last();

		if (!is_null($error)) {

			// command line log has date and plain text
			if ('cli' == php_sapi_name()) {

				print date(DATE_RFC2822) . "\nFatal error (" . $error['type'] . ') on file ' .
					$error['file'] . ' line ' . $error['line'] . ': ' . $error['message'] . "\n";

			// format for an HTML web print
			} else {

				print '<div style="background-color:#f1f1f1;border:2px solid red;margin:10px 0;padding:12px 16px">' .
						PRODUCT_NAME . ' fatal error: ' . htmlspecialchars($error['message']) . '</div>';

			}

			// send email to every admin if no development environment
			if (defined(PAIR_DEVELOPMENT) and PAIR_DEVELOPMENT) {

				// get admin emails by options
				$emails = array_filter(explode(',', Options::get('admin_emails')));

				if (count($emails)) {

					// get proper server description
					if (isset($_SERVER['SERVER_NAME'])) {
						$server = $_SERVER['SERVER_NAME'];
					} else if (isset($_SERVER['SERVER_ADDR'])) {
						$server = $_SERVER['SERVER_ADDR'];
					} else {
						$server = '[Unknown]';
					}

					// build email parameters
					$subject = PRODUCT_NAME . ' fatal error on ' . $server;
					$message = PRODUCT_NAME . ' generated a fatal error on server ' .  BASE_HREF . ' as of ' .
						date(DATE_RFC2822) . " UTC." . PHP_EOL . PHP_EOL .
						'Error [type ' . $error['type'] . ']: ' . PHP_EOL .
						$error['message'] . ' (' . $error['file'] . ' line ' . $error['line'] . ')';
					$headers = 'From: ' . PRODUCT_NAME . ' <' . strtolower(PRODUCT_NAME) . '@' . $server . '>';

					// send email to each admin
					foreach ($emails as $email) {
						mail($email, $subject, $message, $headers);
					}

				}

			}

		}

		if (defined('SENTRY_DSN') and SENTRY_DSN) {
			\Sentry\captureLastError();
			Logger::event('The error was sent to Sentry');
		}

	}

	/**
	 * Get the backtrace and assemble an array with comprehensible items.
	 *
	 * @return string[]
	 */
	public static function getDebugBacktrace(): array {

		// get the backtrace and remove the error handler entry
		$backtrace = debug_backtrace();
		array_shift($backtrace);

		$ret = [];

		foreach ($backtrace as $event) {

			$args = [];

			if (array_key_exists('args', $event)) {
				foreach ($event['args'] as $arg) {
					$args[] = is_array($arg) ? 'array' : (is_object($arg) ? get_class($arg) : (string)$arg);
				}
			}

			// assemble all event’s info
			$ret[] =
			(isset($event['class']) ? $event['class'] .  $event['type'] : '') . $event['function'] .
			'(' . implode(', ', $args) . ')' .
			(isset($event['file']) ? ' | file ' . basename($event['file']) : '') .
			(isset($event['line']) ? ' line ' . $event['line'] : '');

		}

		return $ret;

	}

	/**
	 * Determines whether the brightness of the color code passed as a parameter is less
	 * than 128, so it is a dark color. Useful for dynamically choosing a foreground or
	 * background color that contrasts with the color passed.
	 */
	public static function isDarkColor(string $hexColor): bool {

		// removes the # symbol, if present
		$hexColor = ltrim($hexColor, '#');

		// converts HEX color to RGB components
		$r = hexdec(substr($hexColor, 0, 2));
		$g = hexdec(substr($hexColor, 2, 2));
		$b = hexdec(substr($hexColor, 4, 2));

		// calculate brightness using the perceived formula
		$brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;

		// if the brightness is less than 128, the color is dark
		return $brightness < 128;

	}

	/**
	 * Converts an array of strings to an array of integers.
	 * @return int[]
	 */
	public static function arrayToInt(array $array): array {

		return array_map('intval', $array);

	}

	/**
	 * Creates a plain text string from any variable type.
	 *
	 * @param	mixed	Variable of any type.
	 * @param	bool	Flag to hide var type.
	 */
	public static function varToText($var, bool $showTypes=TRUE, ?int $indent=0): string {

		$getIndent = function(int $incr=0) use ($indent) {
			$indent += $incr;
			return str_repeat("\t", abs($indent));

		};

		$type = gettype($var);
		$text = is_null($type) ?
			'NULL' :
			($showTypes ? $type . ':' : '');

		switch ($type) {

			case 'boolean':
				$text .= $var ? 'true' : 'false';
				break;

			case 'integer':
			case 'double':
				$text .= $var;
				break;

			default:
				if (self::isJson($var)) {
					$text .= '"' . nl2br($var) . '"';
				} else {
					$text .= '"' . $var . '"';
				}
				break;

			case 'array':
				$parts = [];
				foreach ($var as $k=>$v) {
					$parts[] = $getIndent(1) . '"' . $k . '"=' . self::varToText($v, $showTypes, $indent+1);
				}
				$text .= $getIndent(1) . '[<br>' . implode(',<br>', $parts) . '<br>'. $getIndent(-1) . ']<br>';
				break;

			case 'object':
				$text .= $getIndent(0) . get_class($var) . '{<br>' .
					self::varToText(get_object_vars($var), $showTypes, $indent+1) .
					$getIndent(0) . '}';
				break;

			case 'NULL':
				$text .= 'null';
				break;

		}

		return $text;

	}

	/**
	 * Proxy method to print a JSON error message.
	 *
	 * @param	string	Error message to print on user.
	 * @param	int|NULL	Error code (optional).
	 * @param	int|NULL	HTTP code (optional, 400 by default).
	 */
	public static function printJsonError(string $message, int $code=NULL, int $httpCode=NULL): void {

		if (is_null($httpCode)) {
			$httpCode = 400;
		}

		self::printJsonData(NULL, $message, TRUE, $code, $httpCode);

	}

	/**
	 * Prints a JSON object with three properties, useful for ajax returns: (string)message,
	 * (bool)error, (string)log.
	 *
	 * @param	string	Error message to print on user.
	 */
	public static function printJsonMessage(string $message): void {

		self::printJsonData(NULL, $message);

	}

	/**
	 * Prints a JSON object with four properties, useful for ajax returns: (object)data,
	 * (string)message, (bool)error, (string)log.
	 *
	 * @param	object	Structured object containing data.
	 * @param	string	Error message to print on user (optional).
	 * @param	bool	Error flag, set TRUE to notice about error (optional).
	 * @param	bool	Error code (optional).
	 */
	public static function printJsonData(mixed $data, string $message='', bool $error=FALSE, int $code=NULL, int $httpCode=NULL): void {

		$ret = new \stdClass();

		// per messaggi o errori, data non viene restituito
		if (!is_null($data)) {
			$ret->data = $data;
		}

		$ret->message	= $message;
		$ret->error		= $error;
		$ret->code		= $code;

		// contiene gli eventi registrati dal Logger, se attivo
		$logger = Logger::getInstance();
		$eventList = $logger->getEventListForAjax();
		if ($eventList) {
			$ret->log = $logger->getEventListForAjax();
		}

		$json = json_encode($ret);

		if (is_int($httpCode)) {
			http_response_code($httpCode);
		}

		header('Content-Type: application/json', TRUE);
		print $json;
		exit((int)$error);

	}

	/**
	 * Forces the HTTP response as download of an XML file
	 *
	 * @param	string	string containing XML data.
	 * @param	string	name that the downloaded file will have on client.
	 */
	public static function printXmlData(string $data, string $filename): void {

		header('Content-type: text/xml');
		header('Content-Disposition: attachment; filename="' . $filename . '"');

		print $data;
		die();

	}

	/**
	 * Proxy method to output HTML code that prints a JS message in page at runtime.
	 *
	 * @param	string	Message title.
	 * @param	string	Message text.
	 * @param	string	Type, can be info, error or warning.
	 * @return	void
	 */
	public static function printJsMessage(string $title, string $message, string $type='info'): void {

		print self::getJsMessage($title, $message, $type);

	}

	/**
	 * Return the HTML code that prints a JS message in page at runtime.
	 *
	 * @param	string	Message title.
	 * @param	string	Message text.
	 * @param	string	Type, can be info, error or warning.
	 * @return	string
	 */
	public static function getJsMessage(string $title, string $message, string $type='info'): string {

		$types = ['info', 'warning', 'error'];
		if (!in_array($type, $types)) $type = 'info';

		$message = '<script>$(document).ready(function(){$.showMessage("'.
				addslashes($title) .'","'.
				addslashes($message) .'","'.
				addslashes($type) .'");});</script>';

		return $message;

	}

	/**
	 * Will returns a div tag with timeago content.
	 *
	 * @param	mixed	DateTime, integer-timestamp or string date.
	 * @return	string
	 */
	public static function getTimeago($date): string {

		$app = Application::getInstance();

		// create DateTime object with int or strings
		if (is_int($date)) {
			$dateTime = new \DateTime();
			$dateTime->setTimestamp($date);
			$dateTime->setTimezone($app->currentUser->getDateTimeZone());
		} else if (is_string($date)) {
			if (0!==strpos($date, '0000-00-00 00:00:00')) {
				$dateTime = new \DateTime($date);
				$dateTime->setTimezone($app->currentUser->getDateTimeZone());
			} else {
				$dateTime = NULL;
			}
		} else if (is_a($date,'\DateTime')) {
			$dateTime = $date;
			$dateTime->setTimezone($app->currentUser->getDateTimeZone());
		} else {
			$dateTime = NULL;
		}

		// if date valid, create the expected object
		if (is_a($dateTime,'\DateTime')) {

			$humanDate = self::intlFormat(NULL, $dateTime);

			return '<span class="timeago" title="' . $dateTime->format(DATE_W3C) . '">' . $humanDate . '</span>';

		// otherwise return n.a. date
		} else {

			return '<span class="timeago">' . Translator::do('NOT_AVAILABLE') . '</span>';

		}

	}

	/**
	 * Prints a message "NoData..." in a special container.
	 *
	 * @param	string	Custom message to print in the container.
	 */
	public static function printNoDataMessageBox(?string $customMessage=NULL) {

		Router::exceedingPaginationFallback();

		?><div class="messageNodata"><?php print ($customMessage ? $customMessage : Translator::do('NOTHING_TO_SHOW')) ?></div><?php

	}

	/**
	 * Checks and fixes path with missing trailing slash.
	 *
	 * @param	string	Path.
	 * @return	void
	 */
	public static function fixTrailingSlash(string &$path): void {

		if (substr($path,strlen($path)-1, 1) != DIRECTORY_SEPARATOR) $path .= DIRECTORY_SEPARATOR;

	}

	/**
	 * Cleans out a string from any unwanted char. Useful for URLs, file-names.
	 *
	 * @param	string	Original string.
	 * @param	string	Optional separator.
	 * @return	string	Sanitized string.
	 */
	public static function localCleanFilename(string $string, $sep=NULL): string {

		$string = strtolower($string);

		// cleans accents and unify separators -
		$pattern = ['_',' ','\'','à','è','é','ì','ò','ù'];
		$replace = ['-','-','-', 'a','e','e','i','o','u'];
		$string	= str_replace($pattern,$replace,$string);

		// deletes everything is not words, numbers, dots and minus
		$string = preg_replace('/([^[:alnum:]\.\-]*)/', '', $string);

		// reduces multiple separators
		$string	= preg_replace('/([-]+)/', ($sep ? $sep : '-'), $string);

		// trim separators in name only
		$dot	= strrpos($string,'.');
		$ext	= substr($string,$dot);
		$name	= trim(substr($string,0,$dot), '-');
		$string	= $name . $ext;

		return $string;

	}

	/**
	 * Rename a file if another with same name exists, adding hash of current date. It keep safe
	 * the filename extension.
	 *
	 * @param	string	Original file-name with or without extension.
	 * @param	string	Path to file, with or without trailing slash.
	 *
	 * @return	string
	 */
	public static function uniqueFilename(string $filename, string $path): string {

		// fixes path if not containing trailing slash
		self::fixTrailingSlash($path);

		if (file_exists($path . $filename)) {
			$dot  = strrpos($filename,'.');
			$ext  = substr($filename,$dot);
			$name = substr($filename,0,$dot);
			$hash = substr(md5(time()),0,6);
			$filename = $name . '-' . $hash . $ext;
		}

		return $filename;

	}

	/**
	 * Creates a random file name and checks it doesn’t exists.
	 *
	 * @param	string	File extension or the whole filename.
	 * @param	string	Path to file, with or without trailing slash.
	 *
	 * @return	string
	 */
	public static function randomFilename(string $extension, string $path): string {

		// fixes path if not containing trailing slash
		self::fixTrailingSlash($path);

		$pathParts = pathinfo($extension);
		$ext = isset($pathParts['extension']) ? $pathParts['extension'] : $extension;

		$newName	= substr(md5(microtime(TRUE)),0,10);
		$filename	= $newName . '.' . $ext;

		// in case exists, create a new name
		while (file_exists($path . $filename)) {
			$newName = substr(md5(microtime(TRUE)),0,10);
			$filename = $newName . '.' . $ext;
		}

		return $filename;

	}

	/**
	 * List of directory’s file recursively with subfolders.
	 *
	 * @param	string	Relative path of the interested folder to scan.
	 * @param	string	Internal, subfolder recursive name-cache.
	 * @param	array	Internal, empty array at first scan.
	 */
	public static function getDirectoryFilenames(string $path, string $subfolder=NULL, array $fileList=[]): array {

		// usually insignificant files
		$excludes = ['..', '.', '.DS_Store', 'thumbs.db', '.htaccess'];

		try {

			// reads folders file and dirs as plain array
			$filelist = scandir($path, 0);

			if (!$filelist) {
				throw new \Exception('Unable to scan directory ' . $path);
			}

			$filenames = array_diff($filelist, $excludes);

			// we look at each file/dir
			foreach ($filenames as $filename) {

				// relative file-path
				$file = $path . '/' . $filename;

				// it’s a directory, scan it
				if (is_dir($file)) {

					// recursive callback to scan new directory
					$fileList = self::getDirectoryFilenames($file, $subfolder . $filename . '/', $fileList);

				// it’s file, add it
				} else {

					$fileList[] = $subfolder . $filename;

				}

			}

		} catch (\Exception $e) {

			trigger_error($e->getMessage());
			return [];

		}

		return $fileList;

	}

	/**
	 * Deletes folder with all files and subfolders. Returns TRUE if deletion happens,
	 * FALSE if folder or file is not found or in case of errors.
	 *
	 * @param	string	Relative or absolute directory.
	 */
	public static function deleteFolder($dir) {

		if (!file_exists($dir)) return true;

		if (!is_dir($dir) or is_link($dir)) return unlink($dir);

		foreach (scandir($dir) as $item) {

			if ($item == '.' or $item == '..') continue;

			if (!self::deleteFolder($dir . "/" . $item)) {
				chmod($dir . "/" . $item, 0777);
				if (!self::deleteFolder($dir . "/" . $item)) return false;
			}

		}

		return rmdir($dir);

	}

	/**
	 * Replaces diacritics chars with similars in standard ascii.
	 *
	 * @param	string	Text to clean.
	 * @return	string
	 */
	public static function convertDiacritics(string $text): string {

		$search = [
			'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ß', 'ç', 'ð', 'è', 'é', 'ê', 'ë',
			'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'œ', 'ù', 'ú',
			'û', 'ü', 'Þ', 'þ', 'ÿ'
		];

		$replace = [
			'a', 'a', 'a', 'a','ae', 'a','ae','ss', 'c', 'd', 'e', 'e', 'e', 'e',
			'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o','oe', 'o','oe', 'u', 'u',
			'u','ue', 't', 't','yu'
		];

		return str_replace($search, $replace, $text);

	}

	/**
	 *  Returns list of dates based on date range
	 *
	 * @param  string	Start Date
	 * @param  string	End Date
	 * @return array
	 */
	public static function getDatesFromRange($sStartDate, $sEndDate) {

		// Start the variable off with the start date
		$aDays[] = $sStartDate;

		// Set a 'temp' variable, sCurrentDate, with
		// the start date - before beginning the loop
		$sCurrentDate = $sStartDate;

		// While the current date is less than the end date
		while ($sCurrentDate < $sEndDate){
			// Add a day to the current date
			$sCurrentDate = date("Y-m-d", strtotime("+1 day", strtotime($sCurrentDate)));

			// Add this new day to the aDays array
			$aDays[] = $sCurrentDate;
		}

		// Once the loop has finished, return the
		// array of days.
		return $aDays;

	}

	/**
	 * Analyzes a MimeType string and returns a FontAwesome icon class
	 * based on its main type (image, pdf, word, excel, zip, generic).
	 *
	 * @param	string	IANA Standard mime-type, RFC 6838.
	 * @return	\stdClass
	 */
	public static function getIconByMimeType($mimeType): \stdClass {

		// return object
		$ret = new \stdClass();

		// extract first part of mimeType string
		@list ($type, $subtype) = explode('/', $mimeType);

		if ('image' == $type) {

			switch ($subtype) {

				case 'vnd.dwg':
					$ret->tag	= '';
					$ret->class	= 'fa-paperclip';
					break;

				default:
					$ret->tag	= 'class="popup-image"';
					$ret->class	= 'fa-file-image';
					break;

			}

		} else if ('video' == $type) {

			$ret->tag	= 'class="popup-video"';
			$ret->class	= 'fa-video-camera';

		} else if ('text' == $type) {

			$ret->tag	= '';
			$ret->class	= 'fa-file';

		} else if ('application' == $type) {

			switch ($subtype) {

				case 'pdf':
					$ret->tag	= '';
					$ret->class	= 'fa-file-pdf';
					break;

				case 'msword':
				case 'vnd.oasis.opendocument.text':
				case 'vnd.openxmlformats-officedocument.wordprocessingml.document':
					$ret->tag	= '';
					$ret->class	= 'fa-file-word';
					break;

				case 'vnd.ms-excel':
				case 'vnd.openxmlformats-officedocument.spreadsheetml.sheet':
					$ret->tag	= '';
					$ret->class	= 'fa-file-excel';
					break;

				case 'zip':
					$ret->tag	= '';
					$ret->class	= 'fa-file-archive';
					break;

				default:
					$ret->tag	= '';
					$ret->class	= 'fa-paperclip';
					break;

			}

		} else {

			$ret->tag	= '';
			$ret->class	= 'fa-paperclip';

		}

		return $ret;

	}

	/**
	 * Converts string date coming by email in various RFC formats to DateTime. If fails or gets
	 * a date over current time, sets as now.
	 *
	 * @param	string	Date in various formats.
	 *
	 * @return	DateTime
	 */
	public static function getDateTimeFromRfc($date) {

		// various date formats
		$formats = [
				'RFC2822' => \DateTime::RFC2822,	// Fri, 06 Nov 2015 09:23:05 +0100
				'RFC2046' => 'D, d M Y H:i:s O T',	// Fri, 06 Nov 2015 09:23:05 +0100 (CET)
				'CUSTOM1' => 'D, j M Y H:i:s O',	// Fri, 6 Feb 2015 09:23:05 +0100
				'CUSTOM2' => 'd M Y H:i:s O',		// 06 Nov 2015 09:23:05 +0100
				'RSS2'    => 'D, d M Y H:i:s T'		// Tue, 10 May 2016 15:33:54 GMT
		];

		// tries each date format
		foreach ($formats as $format) {

			$datetime = \DateTime::createFromFormat($format, $date, new \DateTimeZone(BASE_TIMEZONE));

			if (is_a($datetime, '\DateTime')) {
				return $datetime;
			}

		}

		return NULL;

	}

	/**
	 * Check if the server’s user agent contains a word about mobile devices and return TRUE if found.
	 */
	public static function isUserAgentMobile(): bool {

		$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';

		$devices = ['phone', 'iphone', 'itouch', 'ipod', 'symbian', 'android', 'htc_', 'htc-',
				'palmos', 'blackberry', 'opera mini', 'iemobile', 'windows ce', 'nokia', 'fennec',
				'hiptop', 'kindle', 'mot ', 'mot-', 'webos\/', 'samsung', 'sonyericsson', '^sie-',
				'nintendo'];

		if (preg_match('/' . implode('|', $devices) . '/', $user_agent)) {
			return TRUE;
		} else {
			return FALSE;
		}

	}

	/**
	 * Sends a 401 error to the browser with a "Unauthorized" JSON message, useful for raw/ajax requests.
	 */
	public static function jsonResponseSessionExpired(): void {

		http_response_code(401); // Unauthorized
		print json_encode(['error' => Translator::do('USER_SESSION_EXPIRED')]);
		exit();

	}

	/**
	 * Convert a snake case variable name into camel case.
	 *
	 * @param	string	Snake case variable name.
	 * @param	bool	Optional to have first letter capital case.
	 * @return	string
	 */
	public static function getCamelCase(string $varName, bool $capFirst=FALSE): string {

		$camelCase = str_replace(' ', '', ucwords(str_replace('_', ' ', $varName)));

		if (!$capFirst and strlen($camelCase)>0) {
			$camelCase[0] = lcfirst($camelCase[0]);
		}

		return $camelCase;

	}

	/**
	 * Check if there is an executable available in the operating system for direct execution. If the path
	 * cannot be changed in the system, the path to the executable can be specified in the Pair configuration.
	 */
	public static function getExecutablePath(string $executable, ?string $configConst=NULL): ?string {

		if (!is_null($configConst) and defined($configConst) and is_executable(constant($configConst))) {
			return constant($configConst);
		}

		exec('which ' . $executable, $output, $resultCode);

		if (!isset($output[0]) or !is_executable($output[0])) {
			Logger::error($executable . ' is not available on this server');
		}

		return ($output[0] ?? NULL);

	}

	/**
	 * Check if passed file is an Adobe PDF document.
	 *
	 * @param	string	Path to file.
	 *
	 * @return	NULL|bool
	 */
	public static function isPdf($file) {

		return self::checkFileMime($file, ['application/pdf']);

	}

	/**
	 * Check if passed file is an image.
	 *
	 * @param	string	Path to file.
	 *
	 * @return	bool
	 */
	public static function isImage($file): bool {

		$validMime = [
			'image/png',
			'image/jpeg',
			'image/pjpeg',
			'image/heif',
			'image/heic',
			'image/gif',
			'image/bmp',
			'image/vnd.microsoft.icon',
			'image/tiff',
			'image/svg+xml'];

		return self::checkFileMime($file, $validMime);

	}

	/**
	 * Check if passed file is one of passed MIME Content Type.
	 *
	 * @param	string	Path to file.
	 * @param	string	Expected MIME Content Type.
	 *
	 * @return	bool
	 */
	private static function checkFileMime($file, $validMime): bool {

		if (!function_exists('mime_content_type')) {
			$app = Application::getInstance();
			$app->enqueueError('The PHP extention mime_content_type is not installed');
			return FALSE;
		}

		// force to array
		$validMime = (array)$validMime;

		foreach ($validMime as $item) {

			if ($item == mime_content_type($file)) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Returns the list of all declared classes, using the cache.
	 * @return array
	 */
	public static final function getDeclaredClasses(): array {

		$app = Application::getInstance();

		if (!$app->issetState('declaredClasses')) {
			$app->setState('declaredClasses', \get_declared_classes());
		}

		return $app->getState('declaredClasses');

	}

	/**
	 * Scan the whole system searching for ActiveRecord classes. These will be include_once.
	 *
	 * @return	array[class][file,folder,tableName,constructor,getInstance,pair]
	 */
	public static function getActiveRecordClasses(): array {

		$classes = [];

		// lambda method that check a folder and add each ActiveRecord class found
		$checkFolder = function (string $folder, ?string $pairPrefix) use(&$classes) {

			if (!is_dir($folder)) return;

			$files = array_diff(scandir($folder), ['..', '.', '.DS_Store']);

			foreach ($files as $file) {

				$pathParts = pathinfo($file);

				// avoid folders and hidden files
				if (is_dir($file) or !isset($pathParts['extension']) or 'php' != $pathParts['extension']) continue;

				// include the file code
				include_once ($folder . '/' . $file);

				// cut .php from file name
				$class = $pathParts['filename'];

				// Pair classes must prefix with namespace
				if (is_null($pairPrefix)) {
					$pair = FALSE;
				} else {
					$class = $pairPrefix . $class;
					$pair = TRUE;
				}

				// check on class exists
				if (!class_exists($class)) continue;

				$reflection = new \ReflectionClass($class);

				// check on right parent and no abstraction
				if (is_subclass_of($class, 'Pair\ActiveRecord') and !$reflection->isAbstract()) {

					$constructMethod = new \ReflectionMethod($class, '__construct');
					$constructor = $constructMethod->isPublic() ? TRUE : FALSE;

					$getInstance = method_exists($class, 'getInstance');

					$classes[$class] = ['file'=>$file, 'folder'=>$folder, 'tableName'=>$class::TABLE_NAME,
							'constructor' => $constructor, 'getInstance' => $getInstance, 'pair' => $pair];

				}

			}

		};

		// Pair framework
		$checkFolder(APPLICATION_PATH . '/' . PAIR_FOLDER, 'Pair\\');
		$checkFolder(APPLICATION_PATH . '/' . PAIR_FOLDER . '/oauth', 'Pair\\Oauth\\');

		// custom classes
		$checkFolder(APPLICATION_PATH . '/classes', NULL);

		// modules classes
		$modules = array_diff(scandir(APPLICATION_PATH . '/modules'), ['..', '.', '.DS_Store']);
		foreach ($modules as $module) {
			$checkFolder(APPLICATION_PATH . '/modules/' . $module . '/classes', NULL);
		}

		return $classes;

	}

	/**
	 * Return public URL of a document by its path in the file system.
	 *
	 * @param	string		Full path into the file system.
	 * @param	bool		If TRUE, add timestamp of the last file edit. Default FALSE.
	 *
	 * @return	string
	 *
	 * @throws	\Exception
	 */
	public static function getFileUrl($filePath, $addTimestamp = FALSE) {

		if (!file_exists($filePath)) {
			throw new \Exception('File not found at path ' . $filePath);
		}

		// check if valid path
		if (FALSE === strpos($filePath, APPLICATION_PATH)) {
			throw new \Exception('File path doesn’t match the application root folder ' . APPLICATION_PATH);
		}

		// BASE_HREF has trailing slash
		$url = BASE_HREF . substr($filePath, strlen(APPLICATION_PATH)+1);

		if ($addTimestamp) {
			$timestamp = filemtime($filePath);
			// check if valid timestamp
			if (FALSE == $timestamp) {
				throw new \Exception('File last change timestamp failed for ' . $filePath);
			}
			$url .= '?' . $timestamp;
		}

		return $url;

	}

	/**
	 * Return a random alpha-numeric string of specified length.
	 *
	 * @param	int 	Wanted string length.
	 * @return	string
	 */
	public static function getRandomString(int $length): string {

		// create a random string
		return substr(str_shuffle(self::RANDOM_STRING_CHARS . self::RANDOM_STRING_CHARS), 0, $length);

	}

	/**
	 * Return a standard ascii one or a predefined icon HTML as constant.
	 *
	 * @param	bool	Value to show as an icon.
	 * @return	string
	 */
	public static function getBoolIcon(bool $value): string {

		if ($value) {
			return (defined('PAIR_CHECK_ICON') ? PAIR_CHECK_ICON : '<span style="color:green">√</span>');
		} else {
			return (defined('PAIR_TIMES_ICON') ? PAIR_TIMES_ICON : '<span style="color:red">×</span>');
		}

	}

	/**
	 * Check if a string is in JSON format.
	 * @param mixed $string
	 * @return bool
	 */
	public static function isJson($string): bool {

		if (!is_string($string)) {
			return FALSE;
		}

		json_decode($string);
		return json_last_error() === JSON_ERROR_NONE;

	}

	/**
	 * Return a date in the local language using the IntlDateFormatter::format() method.
	 * @param	string		Formatting pattern, for example “dd MMMM Y hh:mm”.
	 * @param	\DateTime	The date object to be formatted, it will be the current date if NULL.
	 * @return	string
	 */
	public static function intlFormat(?string $format=NULL, ?\DateTime $dateTime=NULL): string {

		$formatter = new \IntlDateFormatter(NULL, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
		if ($format) {
			$formatter->setPattern($format);
		}
        return $formatter->format($dateTime ?? new \DateTime());

	}

	/**
	 * Download a CSV file with the lines passed in an array of objects.
	 *
	 * @param	array List of objects from which the header will be created from the first row.
	 * @param	string Optional name for the document (with extension).
	 * @return	bool
	 */
	public static function exportCsvForExcel(array $data, ?string $name=NULL): bool {

		// field delimiter
		$delimiter = ";";

		// document name
		$filename = $name ? $name : self::localCleanFilename(strtolower(PRODUCT_NAME) . '_export_' . date('YmdHis') . '.csv');

		// file pointer
		$file = fopen('php://memory', 'w');

		// add BOM to set UTF-8 in Excel
		fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

		// if there is at least one row, it writes header and data rows
		if (isset($data[0])) {

			// properties of the first object in the list
			$headValues = array_keys(get_object_vars($data[0]));

			// first line with the object keys
			fputcsv($file, $headValues, $delimiter);

			// all subsequent lines
			foreach ($data as $row) {
				fputcsv($file, get_object_vars($row), $delimiter);
			}

		}

		// rewinds the document
		fseek($file, 0);

		// set browser headers and force download
		header('Content-Type: text/csv; charset=UTF-8');
		header('Pragma: public'); // IE fix
		header('Content-Disposition: attachment; filename="' . $filename . '";');

		// writes the other data to a pointer to the file
		fpassthru($file);

		return TRUE;

	}

	/**
	 * Processes a plural object name and returns its name in the singular. Valid only for the English
	 * language. Respect the case of the plural noun.
	 * @param	string	Plural object name.
	 * @return	string	Singular object name.
	 */
	public static function getSingularObjectName(string $plural): string {

		$specials = [
			'woman' => 'women', 'man' => 'men', 'child' => 'children', 'tooth' => 'teeth',
			'foot' => 'feet', 'person' => 'people', 'leaf' => 'leaves', 'mouse' => 'mice',
			'goose' => 'geese', 'half' => 'halves', 'knife' => 'knives', 'wife' => 'wives',
			'life' => 'lives', 'elf' => 'elves', 'loaf' => 'loaves', 'potato' => 'potatoes',
			'tomato' => 'tomatoes', 'cactus' => 'cacti', 'focus' => 'foci', 'fungus' => 'fungi',
			'nucleus' => 'nuclei', 'syllabus' => 'syllabi', 'analysis' => 'analyses',
			'diagnosis' => 'diagnoses', 'oasis' => 'oases', 'thesis' => 'theses', 'crisis' => 'crises',
			'phenomenon' => 'phenomena', 'criterion' => 'criteria', 'datum' => 'data'
		];

		// irregular noun plurals
		if (in_array(strtolower($plural), $specials)) {
			$singular = array_search($plural, $specials);
		// singular noun ending in a consonant and then y makes the plural by dropping the y and adding-ies
		} else if ('ies' == substr($plural, -3)) {
			$singular = substr($plural,0,-3) . 'y';
		// a singular noun ending in s, x, z, ch, sh makes the plural by adding-es
		} else if ('es' == substr($plural,-2) and (in_array(substr($plural,-4,-2),['ch','sh']) or
				(in_array(substr($plural,-3,-2),['s','x','z'])))) {
			$singular = substr($plural,0,-2);
		// singular nouns form the plural by adding -s
		} else if ('s' == substr($plural,-1)) {
			$singular = substr($plural,0,-1);
		// maybe it’s already singular
		} else {
			$singular = $plural;
		}

		if ($plural == strtoupper($plural)) {
			return strtoupper($singular);
		} else if ($plural[0] = ucfirst($plural[0])) {
			return ucfirst($singular);
		} else {
			return $singular;
		}

	}

	/**
	 * Returns the object with the property closest to the value passed as a parameter,
	 * searching the indicated property of the object list passed.
	 *
	 * @param array List of objects to search.
	 * @param string Name of the property that contains the name to compare.
	 * @param string Value to search for.
	 * @return object The closest object.
	 */
	public static function findSimilar(array $objectList, string $propertyName, string $searchedValue, ?bool $caseSensitive=FALSE): object {

		// temporary list to sort by similarity
		$similarity = [];

		// check for similarity on each object
		foreach ($objectList as $index => $item) {

			// return the object that exactly matches

			// assign the similarity percentage
			if ($caseSensitive) {
				if ($item->$propertyName == $searchedValue) return $item;
				similar_text($item->$propertyName, $searchedValue, $percent);
			} else {
				$searchedValue = strtolower($searchedValue);
				$propertyValue = strtolower($item->$propertyName);
				if ($propertyValue == $searchedValue) return $item;
				similar_text($propertyValue, $searchedValue, $percent);
			}

			$similarity[$index] = $percent;

		}

		// get the array key of the maximum similarity value found
		$maxSimilarity = array_search(max($similarity), $similarity);

		return $objectList[$maxSimilarity];

	}

}