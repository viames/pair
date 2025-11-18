<?php

namespace Pair\Helpers;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Core\Logger;
use Pair\Core\Router;
use Pair\Exceptions\PairException;
use Pair\Html\FormControls\File;
use Pair\Orm\Collection;

/**
 * Collection of useful methods for both internal framework needs and common application needs.
 */
class Utilities {

	/**
	 * List of characters allowed for the composition of a random string.
	 */
	const RANDOM_STRING_CHARS = '123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	/**
	 * Get an array of email addresses, remove those that are not email addresses, returning a clean list of email addresses.
	 */
	public static function arrayToEmail(array $array): array {

		$ret = [];

		foreach ($array as $email) {

			$email = trim($email);

			if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$ret[] = $email;
			}

		}

		return $ret;

	}

	/**
	 * Converts an array of strings to an array of integers.
	 *
	 * @return int[]
	 */
	public static function arrayToInt(array $array): array {

		return array_map('intval', $array);

	}

	/**
	 * Converts an array of string|int to an array of positive integers.
	 */
	public static function arrayToPositive(array $array): array {

		return array_map(function($value) {
			return abs(intval($value));
		}, $array);

	}

	/**
	 * Check if passed file is one of passed MIME Content Type.
	 *
	 * @param	string	Path to file.
	 * @param	string|array	Expected MIME Content Type or array of MIME Content Types.
	 */
	private static function checkFileMime(string $file, string|array $validMime): bool {

		if (!function_exists('mime_content_type')) {
			throw new PairException('The PHP extention mime_content_type is not installed');
		}

		// force to array
		$validMime = (array)$validMime;

		foreach ($validMime as $item) {

			if ($item == mime_content_type($file)) {
				return true;
			}

		}

		return false;

	}

	/**
	 * Cleans out a string from any unwanted char. Useful for file-names.
	 *
	 * @param	string		Original string.
	 * @param	string|null	Optional custom separator.
	 */
	public static function cleanFilename(string $string, ?string $sep = null): string {

		$sep = $sep ?: '-';

		// separe file name from extension
		$dot	= strrpos($string,'.');
		$ext	= substr($string,$dot);
		$name	= trim(substr($string,0,$dot), $sep);

		return self::cleanUp($name, $sep) . $ext;

	}

	/**
	 * Cleans out a string from everything is not words, number and minus sign or custom separator.
	 * Also converts diacritics to standard ascii, reduces multiple separators and lowercases the string.
	 *
	 * @param	string	Original string.
	 * @param	string|null	Optional custom separator to use instead of minus.
	 */
	public static function cleanUp(string $string, ?string $sep = null): string {

		$sep = $sep ?: '-';

		$string = strtolower($string);

		$string = self::convertDiacritics($string);

		// unify separators
		$string	= str_replace(['_',' ','\''], $sep, $string);

		// deletes everything is not words, numbers and minus
		$string = preg_replace('/([^[:alnum:]\\'.$sep.']*)/', '', $string);

		// reduces multiple separators
		$string	= preg_replace('/(['.$sep.']+)/', $sep, $string);

		return $string;

	}

	/**
	 * Convert a CSV file to an Excel file using the https://github.com/mentax/csv2xlsx command line utility.
	 */
	public static function convertCsvToExcel(string $csvPath, ?string $excelPath = null, string $separator=','): string {

		$execPath = self::getExecutablePath('csv2xlsx', 'CSV2XLSX_PATH');

		if (is_null($execPath)) {
			throw new PairException('CSV2XLSX command line utility is not available on this server');
		}

		// check if the file is a CSV
		$fileMime = mime_content_type($csvPath);
		if ('csv' != File::mimeCategory($fileMime)) {
			throw new PairException('The file mime “' . $fileMime . '” is not CSV type');
		}

		// create Excel file name as the CSV file with the extension changed
		if (is_null($excelPath)) {
			$fileName = substr($csvPath, 0, strrpos($csvPath, '.'));
			$excelPath = $fileName ? $fileName . '.xlsx' : $csvPath . '.xlsx';
		}

		$command = $execPath . ' -d "' . $separator . '" -o ' . $excelPath . ' ' . $csvPath;

		shell_exec($command);

		if (!file_exists($excelPath)) {
			throw new PairException('Conversion failed');
		}

		// remove the CSV file
		unlink($csvPath);

		return $excelPath;

	}

	/**
	 * Replaces diacritics chars with similars in standard ascii.
	 *
	 * @param	string	Text to clean.
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
	 * Deletes folder with all files and subfolders. Returns true if deletion happens,
	 * false if folder or file is not found or in case of errors.
	 *
	 * @param	string	Relative or absolute directory.
	 */
	public static function deleteFolder($dir): bool {

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
	 * Download a CSV file with the lines passed in an array of objects.
	 *
	 * @param	array List of objects from which the header will be created from the first row.
	 * @param	string Optional name for the document (with extension).
	 * @return	bool
	 */
	public static function exportCsvForExcel(array $data, ?string $name = null): bool {

		// field delimiter
		$delimiter = ";";

		// document name
		$filename = $name ? $name : self::cleanUp(Env::get('APP_NAME')) . '_export_' . date('YmdHis') . '.csv';

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

		return true;

	}

	/**
	 * Returns the object with the property closest to the value passed as a parameter,
	 * searching the indicated property of the object list passed.
	 *
	 * @param Collection|array List of objects to search.
	 * @param string Name of the property that contains the name to compare.
	 * @param string Value to search for.
	 * @return object The closest object.
	 */
	public static function findSimilar(Collection|array $objectList, string $propertyName, string $searchedValue, ?bool $caseSensitive = false): object {

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

	/**
	 * Checks and fixes path with missing trailing slash.
	 *
	 * @param	string	Path.
	 */
	public static function fixTrailingSlash(string &$path): void {

		if (substr($path,strlen($path)-1, 1) != DIRECTORY_SEPARATOR) $path .= DIRECTORY_SEPARATOR;

	}

	/**
	 * Returns the class name of the ActiveRecord that has the same table name as the one passed.
	 */
	public static function getActiveRecordClassByTable(string $tableName): ?string {

		$classes = self::getActiveRecordClasses();

		foreach ($classes as $class => $data) {
			if ($data['tableName'] == $tableName) {
				return $class;
			}
		}

		return null;

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
	 * List of directory’s file recursively with subfolders.
	 *
	 * @param	string	Relative path of the interested folder to scan.
	 * @param	string	Internal, subfolder recursive name-cache.
	 * @param	array	Internal, empty array at first scan.
	 */
	public static function getDirectoryFilenames(string $path, ?string $subfolder = null, array $fileList = []): array {

		// usually insignificant files
		$excludes = ['..', '.', '.DS_Store', 'thumbs.db', '.htaccess'];

		// reads folders file and dirs as plain array
		$filelist = scandir($path, 0);

		if (!$filelist) {
			throw new PairException('Unable to scan directory ' . $path);
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

		return $fileList;

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
	 */
	public static function getDateTimeFromRfc(string $date): ?\DateTime {

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

		return null;

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
					$pair = false;
				} else {
					$class = $pairPrefix . $class;
					$pair = true;
				}

				// check on class exists
				if (!class_exists($class)) continue;

				$reflection = new \ReflectionClass($class);

				// check on right parent and no abstraction
				if (is_subclass_of($class, 'Pair\Orm\ActiveRecord') and !$reflection->isAbstract()) {

					$constructMethod = new \ReflectionMethod($class, '__construct');
					$constructor = $constructMethod->isPublic() ? true : false;

					$getInstance = method_exists($class, 'getInstance');

					$classes[$class] = ['file'=>$file, 'folder'=>$folder, 'tableName'=>$class::TABLE_NAME,
							'constructor' => $constructor, 'getInstance' => $getInstance, 'pair' => $pair];

				}

			}

		};

		// Pair framework
		$checkFolder(APPLICATION_PATH . '/' . PAIR_FOLDER, 'Pair\\');
		$checkFolder(APPLICATION_PATH . '/' . PAIR_FOLDER . '/Core', 'Pair\\Core\\');
		$checkFolder(APPLICATION_PATH . '/' . PAIR_FOLDER . '/Html', 'Pair\\Html\\');
		$checkFolder(APPLICATION_PATH . '/' . PAIR_FOLDER . '/Models', 'Pair\\Models\\');
		$checkFolder(APPLICATION_PATH . '/' . PAIR_FOLDER . '/Orm', 'Pair\\Orm\\');
		$checkFolder(APPLICATION_PATH . '/' . PAIR_FOLDER . '/Services', 'Pair\\Services\\');
		$checkFolder(APPLICATION_PATH . '/' . PAIR_FOLDER . '/Helpers', 'Pair\\Helpers\\');

		// custom classes
		$checkFolder(APPLICATION_PATH . '/classes', null);

		// modules classes
		$modules = array_diff(scandir(APPLICATION_PATH . '/modules'), ['..', '.', '.DS_Store']);
		foreach ($modules as $module) {
			$checkFolder(APPLICATION_PATH . '/modules/' . $module . '/classes', null);
		}

		return $classes;

	}

	/**
	 * Return a standard ascii one or a predefined icon HTML as constant.
	 *
	 * @param	bool	Value to show as an icon.
	 */
	public static function getBoolIcon(bool $value): string {

		if ($value) {
			return (defined('PAIR_CHECK_ICON') ? PAIR_CHECK_ICON : '<span style="color:green">√</span>');
		} else {
			return (defined('PAIR_TIMES_ICON') ? PAIR_TIMES_ICON : '<span style="color:red">×</span>');
		}

	}

	/**
	 * Convert a snake case variable name into camel case.
	 *
	 * @param	string	Snake case variable name.
	 * @param	bool	Optional to have first letter capital case.
	 */
	public static function getCamelCase(string $varName, bool $capFirst = false): string {

		$camelCase = str_replace(' ', '', ucwords(str_replace('_', ' ', $varName)));

		if (!$capFirst and strlen($camelCase)>0) {
			$camelCase[0] = lcfirst($camelCase[0]);
		}

		return $camelCase;

	}

	/**
	 * Returns the list of all declared classes, using the cache.
	 */
	public static final function getDeclaredClasses(): array {

		$app = Application::getInstance();

		if (!$app->issetState('declaredClasses')) {
			$app->setState('declaredClasses', \get_declared_classes());
		}

		return $app->getState('declaredClasses');

	}

	/**
	 * Check if there is an executable available in the operating system for direct execution. If the path
	 * cannot be changed in the system, the path to the executable can be specified in the Pair .env
	 * configuration file. Return the path to the executable if it is available, otherwise null.
	 */
	public static function getExecutablePath(string $executable, ?string $envKey = null): ?string {

		if ($envKey and Env::get($envKey) and is_executable(Env::get($envKey))) {
			return Env::get($envKey);
		}

		exec('which ' . $executable, $output, $resultCode);

		if (!isset($output[0]) or !is_executable($output[0])) {
			$logger = Logger::getInstance();
			$logger->error('{executable} is not available on this server', ['executable' => $executable, 'resultCode' => $resultCode, 'output' => $output]);
		}

		return ($output[0] ?? null);

	}

	/**
	 * Return public URL of a document by its path in the file system.
	 *
	 * @param	string		Full path into the file system.
	 * @param	bool		If true, add timestamp of the last file edit. Default false.
	 *
	 * @throws	PairException
	 */
	public static function getFileUrl($filePath, $addTimestamp = false) {

		if (!file_exists($filePath)) {
			throw new PairException('File not found at path ' . $filePath);
		}

		// check if valid path
		if (false === strpos($filePath, APPLICATION_PATH)) {
			throw new PairException('File path doesn’t match the application root folder ' . APPLICATION_PATH);
		}

		// BASE_HREF has trailing slash
		$url = BASE_HREF . substr($filePath, strlen(APPLICATION_PATH)+1);

		if ($addTimestamp) {
			$timestamp = filemtime($filePath);
			// check if valid timestamp
			if (false == $timestamp) {
				throw new PairException('File last change timestamp failed for ' . $filePath);
			}
			$url .= '?' . $timestamp;
		}

		return $url;

	}

	/**
	 * Return a random alpha-numeric string of specified length.
	 *
	 * @param	int 	Wanted string length.
	 */
	public static function getRandomString(int $length): string {

		// create a random string
		return substr(str_shuffle(self::RANDOM_STRING_CHARS . self::RANDOM_STRING_CHARS), 0, $length);

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
	 * Will returns a div tag with timeago content.
	 *
	 * @param	mixed	DateTime, integer-timestamp or string date.
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
				$dateTime = null;
			}
		} else if (is_a($date,'\DateTime')) {
			$dateTime = $date;
			$dateTime->setTimezone($app->currentUser->getDateTimeZone());
		} else {
			$dateTime = null;
		}

		// if date valid, create the expected object
		if (is_a($dateTime,'\DateTime')) {

			$humanDate = self::intlFormat(null, $dateTime);

			return '<span class="timeago" title="' . $dateTime->format(DATE_W3C) . '">' . $humanDate . '</span>';

		// otherwise return n.a. date
		} else {

			return '<span class="timeago">' . Translator::do('NOT_AVAILABLE') . '</span>';

		}

	}

	/**
	 * Return a date in the local language using the IntlDateFormatter::format() method.
	 * @param	string		Formatting pattern, for example “dd MMMM Y hh:mm”.
	 * @param	\DateTime	The date object to be formatted, it will be the current date if null.
	 */
	public static function intlFormat(?string $format = null, \DateTime|null $dateTime = null): string {

		$formatter = new \IntlDateFormatter(null, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
		if ($format) {
			$formatter->setPattern($format);
		}
		return $formatter->format($dateTime ?? new \DateTime());

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
	 * Check if passed file is an image.
	 *
	 * @param	string	Path to file.
	 */
	public static function isImage(string $file): bool {

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
	 * Check if a string is in JSON format.
	 *
	 * @param	string	The string to check.
	 */
	public static function isJson(string $string): bool {

		json_decode($string);
		return json_last_error() === JSON_ERROR_NONE;

	}

	/**
	 * Check if passed file is an Adobe PDF document.
	 *
	 * @param	string	Path to file.
	 */
	public static function isPdf(string $file): bool {

		return self::checkFileMime($file, ['application/pdf']);

	}

	/**
	 * Check if the passed string is a serialized data for PHP until version 8.3.
	 */
	public static function isSerialized(string $data, array $allowedClasses = []): bool {

		$data = trim($data);

		// serialized null
		if ('N;' === $data) return true;

		// check that it starts with a valid type
		if (!preg_match('/^([adObis]):/', $data, $match)) return false;

		// uses @ to suppress any unserialize warnings
		try {
			$result = unserialize($data, ['allowed_classes' => $allowedClasses]);
		} catch (\Throwable $e) {
			return false;
		}

		// if the result is false and the string is not "b:0;", it is not valid
		return false !== $result or $data === 'b:0;';

	}

	/**
	 * Check if the server’s user agent contains a word about mobile devices and return true if found.
	 */
	public static function isUserAgentMobile(): bool {

		$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';

		$devices = ['phone', 'iphone', 'itouch', 'ipod', 'symbian', 'android', 'htc_', 'htc-',
				'palmos', 'blackberry', 'opera mini', 'iemobile', 'windows ce', 'nokia', 'fennec',
				'hiptop', 'kindle', 'mot ', 'mot-', 'webos\/', 'samsung', 'sonyericsson', '^sie-',
				'nintendo'];

		if (preg_match('/' . implode('|', $devices) . '/', $user_agent)) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Proxy method to print a JSON error message with the error code and message passed as parameters.
	 *
	 * @param	string	Error code to print on user.
	 * @param	string	Error message to print on user.
	 * @param	int		Optional HTTP code (default 400).
	 * @param	array	Optional extra data to add to the JSON response.
	 */
	public static function jsonError(string $errorCode, string $errorMessage, int $httpCode=400, array $extra = []): void {

		self::jsonResponse(array_merge([
			'code' => $errorCode,
			'error' => $errorMessage
		], $extra), $httpCode);

	}

	/**
	 * Print a JSON response with the data passed as a parameter. The default HTTP code is 200, but it will be replaced
	 * with 204 if the data is empty.
	 *
	 * @param	object|array	Data to be printed in JSON format.
	 * @param	int			Optional HTTP code (default 200).
	 */
	public static function jsonResponse(object|array|null $data, int $httpCode=200): void {

		header('Content-Type: application/json', true);

		// no content response if data is empty
		if (empty($data)) {
			$httpCode = 204;
		}

		http_response_code($httpCode);
		print json_encode($data);

		exit();

	}

	/**
	 * Prints a JSON object with message property.
	 *
	 * @param	string	Message to print on user.
	 */
	public static function jsonSuccess(string $message): void {

		self::jsonResponse(['message' => $message]);

	}

	/**
	 * Proxy method to print a JSON error message.
	 *
	 * @param	string	Error message to print on user.
	 * @param	int|null	Error code (optional).
	 * @param	int|null	HTTP code (optional, 400 by default).
	 * @deprecated	Use jsonError() instead.
	 */
	public static function pairJsonError(string $message, ?int $code = null, ?int $httpCode = null): void {

		if (is_null($httpCode)) {
			$httpCode = 400;
		}

		self::pairJsonData(null, $message, true, $code, $httpCode);

	}

	/**
	 * Prints a JSON object with three properties, useful for ajax returns: (string)message,
	 * (bool)error, (string)log.
	 *
	 * @param	string	Error message to print on user.
	 * @deprecated	Use jsonResponse() instead.
	 */
	public static function pairJsonMessage(string $message): void {

		self::pairJsonData(null, $message);

	}

	/**
	 * Prints a JSON object with three properties, useful for ajax returns: (string)message,
	 * (bool)error, (string)log.
	 *
	 * @param	string	Error message to print on user.
	 * @deprecated	Use jsonResponse() instead.
	 */
	public static function pairJsonSuccess(): void {

		self::pairJsonData(null);

	}

	/**
	 * Prints a JSON object with four properties, useful for ajax returns: (object)data,
	 * (string)message, (bool)error, (string)log.
	 *
	 * @param	object	Structured object containing data.
	 * @param	string	Error message to print on user (optional).
	 * @param	bool	Error flag, set true to notice about error (optional).
	 * @param	bool	Error code (optional).
	 * @param	int		HTTP code (optional).
	 * @deprecated	Use jsonResponse() instead.
	 */
	public static function pairJsonData(mixed $data, ?string $message = null, bool $error = false, ?int $code = null, ?int $httpCode = null): void {

		$ret = new \stdClass();

		// per messaggi o errori, data non viene restituito
		if (!is_null($data)) {
			$ret->data = $data;
		}

		if (!is_null($message)) {
			$ret->message = $message;
		}

		$ret->error = $error;

		if (!is_null($code)) {
			$ret->code = $code;
		}

		// contains events registered by LogBar, if active
		$logBar = LogBar::getInstance();
		$eventList = $logBar->renderForAjax();
		if ($eventList) {
			$ret->logBar = $eventList;
		}

		$json = json_encode($ret);

		if (is_int($httpCode)) {
			http_response_code($httpCode);
		}

		header('Content-Type: application/json', true);
		print $json;
		exit((int)$error);

	}

	/**
	 * Prints an alphabetical filter with links from A to Z.
	 *
	 * @param	string	$selected	Current selected list item, if any.
	 */
	public static function printAlphaFilter(?string $selected = null): void {

		$router = Router::getInstance();

		$letters = [];

		foreach (range('A', 'Z') as $a) {

			$filter = new \stdClass();
			$filter->href	= $router->module . '/' . $router->action . '/' . strtolower($a);
			$filter->text	= $a;
			$filter->active	= ($selected and strtolower((string)$a) == strtolower((string)$selected));

			$letters[] = $filter;

		}

		?><a href="<?php print $router->module ?>/<?php print $router->action ?>"><?php Translator::do('ALL') ?></a><?php
		foreach ($letters as $letter) {
			?><a href="<?php print $letter->href ?>"<?php print ($letter->active ? ' class="active"' : '') ?>><?php print $letter->text ?></a><?php
		}

	}

	/**
	 * Forces the HTTP response as download of an XML file
	 *
	 * @param	string	$data		String containing XML data.
	 * @param	string	$filename	Name of the file to be downloaded.
	 */
	public static function printXmlData(string $data, string $filename): void {

		header('Content-type: text/xml');
		header('Content-Disposition: attachment; filename="' . $filename . '"');

		print $data;
		die();

	}

	/**
	 * Creates a random file name and checks it doesn’t exists.
	 *
	 * @param	string	File extension or the whole filename.
	 * @param	string	Path to file, with or without trailing slash.
	 */
	public static function randomFilename(string $extension, string $path): string {

		// fixes path if not containing trailing slash
		self::fixTrailingSlash($path);

		$pathParts = pathinfo($extension);
		$ext = isset($pathParts['extension']) ? $pathParts['extension'] : $extension;

		$newName	= substr(md5(microtime(true)),0,10);
		$filename	= $newName . '.' . $ext;

		// in case exists, create a new name
		while (file_exists($path . $filename)) {
			$newName = substr(md5(microtime(true)),0,10);
			$filename = $newName . '.' . $ext;
		}

		return $filename;

	}

	/**
	 * Prints a message "NoData..." in a contextual feedback messages.
	 *
	 * @param	string	Optional custom message to print in the alert message container.
	 */
	public static function showNoDataAlert(?string $customMessage = null) {

		Router::exceedingPaginationFallback();

		?><div class="alert alert-primary" role="alert"><?php print ($customMessage ? $customMessage : Translator::do('NO_DATA')) ?></div><?php

	}

	/**
	 * Generates a URL-friendly "slug" from any string.
	 */
	public static function slugify(string $text): string {

		// replace non letter or digits by -
		$text = preg_replace('~[^\pL\d]+~u', '-', $text);

		// transliterate
		$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

		// remove unwanted characters
		$text = preg_replace('~[^-\w]+~', '', $text);

		// trim
		$text = trim($text, '-');

		// remove duplicate -
		$text = preg_replace('~-+~', '-', $text);

		// lowercase
		$text = strtolower($text);

		if (empty($text)) {
			return 'n-a';
		}

		return $text;

	}

	/**
	 * Rename a file if another with same name exists, adding hash of current date. It keep safe
	 * the filename extension.
	 *
	 * @param	string	Original file-name with or without extension.
	 * @param	string	Path to file, with or without trailing slash.
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
	 * Extracts a ZIP archive into a custom folder and returns the list of files extracted.
	 */
	public static function unzipFile(string $filePath, string $folder): array {

		$zip = new \ZipArchive();

		// open the zip file
		if (true !== $zip->open($filePath)) {
			throw new PairException('Unable to open the ZIP file ' . $filePath);
		}

		// create the folder if not exists
		if (!is_dir($folder)) {
			mkdir($folder, 0777, true);
		}

		// extract all files
		$zip->extractTo($folder);

		// close the zip file
		$zip->close();

		// return the list of extracted files
		return self::getDirectoryFilenames($folder);

	}

	/**
	 * Creates a plain text string from any variable type.
	 *
	 * @param	mixed	Variable of any type.
	 * @param	bool	Flag to hide var type.
	 */
	public static function varToText($var, bool $showTypes = true, ?int $indent=0): string {

		$getIndent = function(int $incr=0) use ($indent) {
			$indent += $incr;
			return str_repeat("\t", abs($indent));

		};

		$type = gettype($var);
		$text = is_null($type) ?
			'null' :
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

			case 'null':
				$text .= 'null';
				break;

		}

		return $text;

	}

}