<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

/**
 * Collection of useful methods for both internal framework needs and common application needs.
 */
class Utilities {

	/**
	 * Custom error handler.
	 *
	 * @param	int		Error number.
	 * @param	string	Error text message.
	 * @param	string	Error full file path.
	 * @param	string	Error line.
	 */
	public static function customErrorHandler($errno, $errstr, $errfile, $errline) {
	
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
			
			$options = Options::getInstance();
		
			if ($options->getValue('show_log')) {

				$logger = Logger::getInstance();
			
				// start to show detailed error in log event
				$logger->addError('Debug backtrace for “' . $errstr .  '” in ' . $errfile . ' line ' . $errline);
	
				foreach ($backtrace as $event) {
					$logger->addEvent($event);
				}
	
				$logger->addEvent('Debug backtrace finished');
	
			// show error in app because log is disabled
			} else {
			
				$app = Application::getInstance();
				$app->enqueueError($errstr . ' (' . $errfile . ' line ' . $errline .')', 'Error [' . $errno . ']');
		
			}
	
		}
	
	}

	/**
	 * Manages fatal errors (out of memory etc.) sending email to all address in options.
	 */
	public static function fatalErrorHandler() {
				
		$options = Options::getInstance();
		
		// get fatal error array
		$error = error_get_last();

		if (!is_null($error)) {

			// command line log has date and plain text
			if ('cli' == php_sapi_name()) {
				
				print date(DATE_RFC2822) . "\nFatal error (" . $error['type'] . ') on file ' .
					$error['file'] . ' line ' . $error['file'] . ': ' . $error['message'] . "\n";

			// format for an HTML web print
			} else {

				print '<div style="background-color:#f1f1f1;border:2px solid red;margin:10px 0;padding:12px 16px">A fatal error has been detected (' . $error['type'] . ') on file ' .
					$error['file'] . ' line ' . $error['file'] . ': ' . $error['message'] . '</div>';

			}
		
			// get admin emails by options
			$emails = array_filter(explode(',', $options->getValue('admin_emails')));
			
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
	
	/**
	 * Get the backtrace and assemble an array with comprehensible items.
	 *
	 * @return array:string
	 */
	public static function getDebugBacktrace() {
	
		// get the backtrace and remove the error handler entry
		$backtrace = debug_backtrace();
		array_shift($backtrace);
	
		$ret = array();
			
		foreach ($backtrace as $event) {
	
			$args = array();
				
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
	 * Creates a plain text string from any variable type.
	 * 
	 * @param	multitype	Variable of any type.
	 * @return	string
	 */
	public static function varToText($var) {
				
		$type = gettype($var);
		$text = is_null($type) ? 'NULL' : $type . ':';
		
		switch ($type) {

			case 'boolean':
				$text .= $var ? 'TRUE' : 'FALSE';
				break;

			default:
				$text .= $var;
				break;
				
			case 'array':
				$parts = array();
				foreach ($var as $k=>$v) {
					$parts[] = $k . '=>' . self::varToText($v);
				}
				$text .= 'array:[' . implode(',', $parts) . ']';
				break;
				
			case 'object':
				$text .= get_class($var);
				break;
				
			case 'NULL':
				break;

		}
					
		return $text;
		
	}
	
	/**
	 * Crop text to length in parameter, avoiding cutting last word, removing HTML tags and entities.
	 * 
	 * @param	string	Text content.
	 * @param	int		Maximum text length.
	 *  
	 * @return	string
	 */
	public static function cropText($text, $length) {
		
		// remove HTML tags
		$text = strip_tags($text);
		
		// remove encoded HTML entities that could be cropped
		$text = html_entity_decode($text);
		
		// short strings are returned with no further changes
		if (strlen($text) <= $length) return $text;

		// go to latest space
		$text = substr($text, 0, $length);
		if (substr($text,-1,1) != ' ') $text = substr($text, 0, strrpos($text, ' '));
		
		return $text .'…';
		
	}

	/**
	 * Proxy method to print a JSON error message.
	 * 
	 * @param	string	Error message to print on user.
	 */
	public static function printJsonError($message) {

		self::printJsonMessage($message, TRUE);
	
	}
	
	/**
	 * Prints a JSON object with three properties, useful for ajax returns: (string)message,
	 * (bool)error, (string)log.
	 * 
	 * @param	string	Error message to print on user.
	 * @param	bool	Error flag, set TRUE to notice about error (optional).
	 */
	public static function printJsonMessage($message, $error=FALSE) {

		$logger = Logger::getInstance();
		
		$ret			= new \stdClass();
		$ret->message	= $message;
		$ret->error		= $error;
		$ret->log		= $logger->getEventListForAjax();
		$json			= json_encode($ret);
		header('Content-Type: application/json', TRUE);
		print $json;
		die();
		
	}

	/**
	 * Prints a JSON object with four properties, useful for ajax returns: (object)data, 
	 * (string)message, (bool)error, (string)log.
	 * 
	 * @param	object	Structured object containing data.
	 * @param	string	Error message to print on user (optional).
	 * @param	bool	Error flag, set TRUE to notice about error (optional).
	 */
	public static function printJsonData($data, $message='', $error=FALSE) {

		$logger = Logger::getInstance();
		
		$ret			= new \stdClass();
		$ret->data		= $data;
		$ret->message	= $message;
		$ret->error		= $error;
		$ret->log		= $logger->getEventListForAjax();
		$json			= json_encode($ret);
		header('Content-Type: application/json', TRUE);
		print $json;
		die();
		
	}
	
	/**
	 * Convert date from DB format to W3C format.
	 * 
	 * @param	string	String date in format 2013-01-01 12:00:00.
	 * 
	 * @return	string
	 */
	public static function convertDateToIso($dbDate) {

		$date = new \DateTime($dbDate);
		return $date->format(DATE_W3C);
		
	}

	/**
	 * Converts a date from DB format to an human readable format based on current user language.
	 *
	 * @param	string	Date (ex. 2013-01-01 12:00:00).
	 * @param	bool	If this flag is TRUE, removes hours from result; default is FALSE.
	 * @return	string
	 */
	public static function convertDateToHuman($dbDate, $removeHours=FALSE) {

		$t = Translator::getInstance();

		$date = new \DateTime($dbDate);
		$dateFormat = $removeHours ? 'DATE_FORMAT' : 'DATETIME_FORMAT';

		return $date->format($t->translate($dateFormat));

	}
	
	/**
	 * Converts a DateTime object to date field type for db query. In case of UTC_DATE parameter on,
	 * date will be temporary converted to UTC.
	 * 
	 * @param	DateTime	Object to convert.
	 * 
	 * @return	string
	 */
	public static function convertToDbDate(\DateTime $dateTime) {
		
		$currentTz = $dateTime->getTimezone();
		$dateTime->setTimezone(new \DateTimeZone(BASE_TIMEZONE));
		$ret = $dateTime->format('Y-m-d');
		$dateTime->setTimezone($currentTz);
		return $ret;
		
	}

	/**
	 * Converts a DateTime object to datetime field type for db query. In case of UTC_DATE parameter on,
	 * date will be temporary converted to UTC.
	 * 
	 * @param	DateTime	Object to convert.
	 * 
	 * @return	string
	 */
	public static function convertToDbDatetime(\DateTime $dateTime) {
	
		$currentTz = $dateTime->getTimezone();
		$dateTime->setTimezone(new \DateTimeZone(BASE_TIMEZONE));
		$ret = $dateTime->format('Y-m-d H:i:s');
		$dateTime->setTimezone($currentTz);
		return $ret;
	
	}
	
	/**
	 * Create timestamp of a DateTime object as returned by jQuery datepicker.
	 *  
	 * @param	string	Date in format dd-mm-yyyy.
	 * @param	int		Optional hours.
	 * @param	int		Optional minutes.
	 * @param	int		Optional seconds.
	 * 
	 * @return	int
	 */
	public static function timestampFromDatepicker($date, $hour=NULL, $minute=NULL, $second=NULL) {
		
		/*
		 * TODO verify result with different timezones
		$dt = DateTime::createFromFormat('!d-m-Y', $date);
		if (is_a($dt, 'DateTime')) {
			$dt->setTime($hour, $minute, $second);
			$dt->getTimestamp();
		} else {
			return NULL;
		}
		*/

		$d = substr($date, 0, 2);
		$m = substr($date, 3, 2);
		$y = substr($date, 6, 4);
		$time = mktime($hour, $minute, $second, $m, $d, $y);
		
		return ($time ? $time : null);
		
	}
	
	/**
	 * Proxy method to output HTML code that prints a JS message in page at runtime.
	 * 
	 * @param	string	Message title.
	 * @param	string	Message text.
	 * @param	string	Type, can be info, error or warning.
	 */
	public static function printJsMessage($title, $message, $type='info') {
	
		print self::getJsMessage($title, $message, $type);
		
	}
	
	/**
	 * Return the HTML code that prints a JS message in page at runtime.
	 *
	 * @param	string	Message title.
	 * @param	string	Message text.
	 * @param	string	Type, can be info, error or warning.
	 * 
	 * @return	string
	 */
	public static function getJsMessage($title, $message, $type='info') {
	
		$types = array('info', 'warning', 'error');
		if (!in_array($type, $types)) $type = 'info';
	
		$message = '<script>$(document).ready(function(){$("#notificationArea").prependMessageBox("'.
				addslashes($title) .'","'.
				addslashes($message) .'","'.
				addslashes($type) .'");});</script>';
	
		return $message;
	
	}

	/**
	 * Will returns a div tag with timeago content.
	 * 
	 * @param	mixed	DateTime, integer-timestamp or string date.
	 * 
	 * @return	string
	 */
	public static function getTimeago($date) {

		$app  = Application::getInstance();
		$tran = Translator::getInstance();
		
		// create DateTime object with int or strings
		if (is_int($date)) {
			$dateTime = new \DateTime();
			$dateTime->setTimestamp($dateTime);
			$dateTime->setTimezone($app->currentUser->getDateTimeZone());
		} else if (is_string($date)) {
			if (0!==strpos($date, '0000-00-00 00:00:00')) {
				$dateTime = new \DateTime($date);
				$dateTime->setTimezone($app->currentUser->getDateTimeZone());
			} else {
				$dateTime = NULL;
			}
		} else if (is_a($date,'DateTime')) {
			$dateTime = $date;
			$dateTime->setTimezone($app->currentUser->getDateTimeZone());
		} else {
			$dateTime = NULL;
		}
		
		// if date valid, create the expected object
		if (is_a($dateTime,'DateTime')) {
			
			// if is set a locale date format, use it
			if ($tran->stringExists('LC_DATETIME_FORMAT')) {
			
				$localeTimestamp = $dateTime->getTimestamp() + $dateTime->getOffset();
				$humanDate = strftime($tran->translate('LC_DATETIME_FORMAT'), $localeTimestamp);
			
			// otherwise choose another format
			} else {
					
				$format = $tran->stringExists('DATETIME_FORMAT') ?
					$tran->translate('DATETIME_FORMAT') :
					'Y-m-d H:i:s';
				$humanDate = $dateTime->format($format);
					
			}
			
			return '<span class="timeago" title="' . $dateTime->format(DATE_W3C) . '">' . $humanDate . '</span>';

		// otherwise return n.a. date
		} else {

			return '<span class="timeago">' . $tran->translate('NOT_AVAILABLE') . '</span>';

		}

	}

	/**
	 * Adds a NULL select field, with translated text - Select - on top.
	 *
	 * @param	array	Array to which add first NULL value (reference var).
	 * @param	string	Optional name of id var(default is 'id').
	 * @param 	string	Optional name of value var (default is 'name').
	 * @param	string	Optional text label of first NULL value (default is SELECT_NULL_VALUE translated)
	 * 
	 * @return	array
	 */
	public static function prependNullOption(&$list,$idField=NULL,$nameField=NULL,$text=NULL) {
	
		$lang = Translator::getInstance();
		
		$idField	= $idField	? $idField	: 'id';
		$nameField	= $nameField? $nameField: 'name';
		$text		= $text		? $text		: $lang->translate('SELECT_NULL_VALUE');
	
		$nullItem = new \stdClass();
	
		$nullItem->$idField		= '';
		$nullItem->$nameField	= $text;
	
		array_unshift($list,$nullItem);
	
		return $list;
	
	}
	
	/**
	 * Cast to integer all array items. 
	 * 
	 * @param	array	Items to cast.
	 * 
	 * @return	array
	 */
	public static function arrayToInt($array) {
		
		$intArray = array();
		
		foreach ($array as $item) {
			
			$intArray[] = (int)$item;
			
		}
		
		return $intArray;
		
	}
	
	/**
	 * Prints a message "NoData..." in a special container.
	 * 
	 * @param	string	Custom message to print in the container.
	 */
	public static function printNoDataMessageBox($customMessage=NULL) {
		
		$lang = Translator::getInstance();
		?><div class="messageNodata"><?php print ($customMessage ? $customMessage : $lang->translate('NOTHING_TO_SHOW')) ?></div><?php
		
	}

	/**
	 * Prints an HTML message error in a special container.
	 */
	public static function printHtmlErrorBox($errorMessage) {
	
		?><div class="messageNodata"><?php print $errorMessage ?></div><?php
		
	}

	/**
	 * Checks and fixes path with missing trailing slash.
	 *
	 * @param	string	Path.
	 */
	public static function fixTrailingSlash(&$path) {
	
		if (substr($path,strlen($path)-1, 1) != DIRECTORY_SEPARATOR) $path .= DIRECTORY_SEPARATOR;
	
	}
	
	/**
	 * Cleans out a string from any unwanted char. Useful for URLs, file-names.
	 *
	 * @param	string	Original string.
	 * @param	string	Optional separator.
	 * @return	string	Sanitized string.
	 */
	public static function localCleanFilename($string, $sep=NULL) {
	
		$string = strtolower($string);
		
		// cleans accents and unify separators -
		$pattern = array ('_',' ','\'','à','è','é','ì','ò','ù');
		$replace = array ('-','-','-', 'a','e','e','i','o','u');
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
	public static function uniqueFilename($filename, $path) {
	
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
	 * @param	string	Original file name needed for taking extension, fake name accepted.
	 * @param	string	Path to file, with or without trailing slash.
	 * @return	string
	 */
	public static function randomFilename($filename, $path) {

		// fixes path if not containing trailing slash
		self::fixTrailingSlash($path);

		$ext		= substr($filename,strrpos($filename,'.'));
		$newName	= substr(md5(microtime(TRUE)),0,10);
		$filename	= $newName . $ext;

		// in case exists, create a new name
		while (file_exists($path . $filename)) {
			$newName = substr(md5(microtime(TRUE)),0,10);
			$filename = $newName . $ext;
		}

		return $filename;

	}
	
	/**
	 * List of directory’s file recursively with subfolders.
	 *
	 * @param	string	Relative path of the interested folder to scan.
	 * @param	string	Internal, subfolder recursive name-cache.
	 * @param	array	Internal, empty array at first scan.
	 *
	 * @return	array
	 */
	public static function getDirectoryFilenames($path, $subfolder=NULL, $fileList=array()) {
	
		// list of files to exclude
		$excludes = array('..', '.', '.DS_Store', 'thumbs.db', '.htaccess');
		
		try {
			
			// reads folders file and dirs as plain array
			$filenames = array_diff(scandir($path, 0), $excludes);
			
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
			return array();
			
		}
	
		return $fileList;
	
	}
	
	/**
	 * Deletes folder with all files and subfolders. Returns TRUE if deletion happens,
	 * FALSE if folder or file is not found or in case of errors.
	 * 
	 * @param	string	Relative or absolute directory.
	 * 
	 * @return	boolean
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
	public static function convertDiacritics($text) {
	
		$search = array(
				'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ß', 'ç', 'ð', 'è', 'é', 'ê', 'ë',
				'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'œ', 'ù', 'ú',
				'û', 'ü', 'Þ', 'þ', 'ÿ');
	
		$replace = array(
				'a', 'a', 'a', 'a','ae', 'a','ae','ss', 'c', 'd', 'e', 'e', 'e', 'e',
				'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o','oe', 'o','oe', 'u', 'u',
				'u','ue', 't', 't','yu');
	
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
		while($sCurrentDate < $sEndDate){
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
	 * @param	string	Standard mime-type.
	 *  
	 * @return	stdClass
	 */
	public static function getIconByMimeType($mimeType) {

		// return object
		$ret = new \stdClass();
		
		// extract first part of mimeType string
		@list ($type, $subtype) = explode('/', $mimeType);

		if ('image' == $type) {
			
			$ret->tag	= 'class="popupImage"';
			$ret->class	= 'fa-file-image-o';

		} else if ('video' == $type) {

			$ret->tag	= 'class="popupVideo"';
			$ret->class	= 'fa-video-camera';
			
		} else if ('text' == $type) {

			$ret->tag	= 'target="_attach"';
			$ret->class	= 'fa-file-text-o';

		} else if ('application' == $type) {

			switch ($subtype) {

				case 'pdf':
					$ret->tag	= 'target="_attach"';
					$ret->class	= 'fa-file-pdf-o';
					break;

				case 'msword':
				case 'vnd.oasis.opendocument.text':
				case 'vnd.openxmlformats-officedocument.wordprocessingml.document':
					$ret->tag	= 'target="_attach"';
					$ret->class	= 'fa-file-word-o';
					break;

				case 'vnd.ms-excel':
					$ret->tag	= 'target="_attach"';
					$ret->class	= 'fa-file-excel-o';
					break;

				case 'zip':
					$ret->tag	= '';
					$ret->class	= 'fa-file-zip-o';
					break;

			}

		} else {

			$ret->tag	= 'target="_attach"';
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
		$formats = array(
				'RFC2822' => \DateTime::RFC2822,	// Fri, 06 Nov 2015 09:23:05 +0100
				'RFC2046' => 'D, d M Y H:i:s O T',	// Fri, 06 Nov 2015 09:23:05 +0100 (CET)
				'CUSTOM1' => 'D, j M Y H:i:s O',	// Fri, 6 Feb 2015 09:23:05 +0100
				'CUSTOM2' => 'd M Y H:i:s O',		// 06 Nov 2015 09:23:05 +0100
				'RSS2'    => 'D, d M Y H:i:s T');	// Tue, 10 May 2016 15:33:54 GMT
		         // FIXME Tue, 21 Jun 2016 11:45:34 +0200 (ora legale Europa occidentale)
		
		// tries each date format
		foreach ($formats as $format) {

			$datetime = \DateTime::createFromFormat($format, $date, new \DateTimeZone(BASE_TIMEZONE));

			if (is_a($datetime, '\DateTime')) {
				return $datetime;
			}

		}

		return NULL;

	}

}