<?php

namespace Pair\Support;

use Pair\Core\Application;

/**
 * Manage HTTP requests.
 */
class Post {

	/**
	 * Get data by POST or GET array returning an array. In case
	 * of not found, return default value.
	 *
	 * @param	string	HTTP parameter name.
	 * @param	string	Default value to return, in case of null or empty.
	 */
	public static function array($name, $default=NULL): array {

		return self::get($name, 'array', $default);

	}

	/**
	 * Get data by POST or GET array returning the specified type or default value. In case
	 * of not found, return default value. Manage array inputs.
	 *
	 * @param	string	HTTP parameter name.
	 * @param	string	Type string -default-, int, bool, date or datetime will be casted.
	 * @param	string	Default value to return, in case of null or empty.
	 */
	public static function get($name, $type='string', $default=NULL): mixed {

		$val = "";

		switch ($_SERVER['REQUEST_METHOD']) {

			case 'POST':
				$request = &$_POST;
				break;

			default:
				$request = &$_REQUEST;
				break;

		}

		// remove [] from array
		if (substr($name, -2) == '[]') {
			$name = substr($name, 0, -2);
		}

		// spaces are converted by PHP to underscores
		$name = str_replace(' ', '_', $name);

		// seeking a possible name
		if (array_key_exists($name, $request)) {

			// evaluates whether it’s an array
			if (is_array($request[$name])) {

				$values = [];

				foreach ($request[$name] as $val) {
					$values[] = self::castTo($val, $type);
				}

				return $values;

			} else {

				return self::castTo($request[$name], $type);

			}

		} else {

			return self::castTo($default, $type);

		}

	}

	/**
	 * Check wheter a field was submitted in the REQUEST array.
	 *
	 * @param	string	Field name.
	 */
	public static function sent(string $name): bool {

		// remove [] from array
		if (substr($name, -2) == '[]') {
			$name = substr($name, 0, -2);
		}

		// seeking a possible name
		return (array_key_exists($name, $_REQUEST));

	}

	/**
	 * Get data by POST or GET array returning a trimmed string. In case
	 * of not found, return default value.
	 *
	 * @param	string	HTTP parameter name.
	 * @param	string	Default value to return, in case of null or empty.
	 */
	public static function trim($name, $default=NULL): string {

		return self::get(trim($name), 'string', $default);

	}

	/**
	 * Get data by POST or GET array returning an integer. In case
	 * of not found, return default value. Manage array inputs.
	 *
	 * @param	string	HTTP parameter name.
	 * @param	string	Default value to return, in case of null or empty.
	 */
	public static function int($name, $default=NULL): int {

		return self::get($name, 'int', $default);

	}

	/**
	 * Get data by POST or GET array returning an boolean. In case
	 * of not found, return default value. Manage array inputs.
	 *
	 * @param	string	HTTP parameter name.
	 * @param	string	Default value to return, in case of null or empty.
	 */
	public static function bool($name, $default=NULL): bool {

		return self::get($name, 'bool', $default);

	}

	/**
	 * Get data by POST or GET array returning a DateTime object or NULL. In case
	 * of not found, return default value. Manage array inputs.
	 *
	 * @param	string	HTTP parameter name.
	 * @param	string	Default value to return, in case of null or empty.
	 */
	public static function date($name, $default=NULL): ?\DateTime {

		return self::get($name, 'date', $default);

	}

	/**
	 * Get data by POST or GET array returning a DateTime object or NULL. In case
	 * of not found, return default value. Manage array inputs.
	 *
	 * @param	string	HTTP parameter name.
	 * @param	string	Default value to return, in case of null or empty.
	 */
	public static function datetime($name, $default=NULL): ?\DateTime {

		return self::get($name, 'datetime', $default);

	}

	/**
	 * Gets all submitted field name that matches regular expressions.
	 *
	 * @param	string	Regular expression, for instance #[A-Z][A-Z_]+#.
	 * @param	string	Type string, int, bool, date or datetime will be casted.
	 * @param	string	Default value to return, in case of null or empty.
	 */
	public static function byRegex($pattern, $type='string', $default=NULL): array {

		$list = [];

		foreach ($_POST as $name=>$value) {

			if (preg_match($pattern, $name) or $default) {

				$val = $_POST[$name] ? $_POST[$name] : $default;
				$val = self::castTo($val, $type);

				$list[$name] = $val;

			}

		}

		return $list;

	}

	/**
	 * Method to understand if page comes from an HTTP post submit.
	 *
	 * @return	bool	TRUE if method is post.
	 */
	public static function submitted(): bool {

		return (bool)count($_POST);

	}

	/**
	 * Casts a value to a type string, integer or boolean.
	 *
	 * @param	mixed	Variable value.
	 * @param	string	Variable type.
	 */
	private static function castTo($val, string $type): mixed {

		$app = Application::getInstance();

		switch ($type) {

			case 'string':
			case 'text':
				$val = (string)$val;
				break;

			case 'int':
			case 'integer':
				$val = (int)$val;
				break;

			case 'bool':
			case 'boolean':
				if ('true'==strtolower((string)$val)) {
					$val = TRUE;
				} else if ('false'==strtolower((string)$val)) {
					$val = FALSE;
				} else {
					$val = (bool)$val;
				}
				break;

			// creates a DateTime object from datepicker
			case 'date':
				if ($val) {
					if ((defined('PAIR_FORM_DATE_FORMAT') and self::usingCustomDatepicker())) {
						$format = PAIR_FORM_DATE_FORMAT;
					} else {
						$format = 'Y-m-d';
					}
					$val = \DateTime::createFromFormat('!' . $format, $val, $app->currentUser->getDateTimeZone());
					// DateTime is not set
					if (FALSE === $val) $val = NULL;
				} else {
					$val = NULL;
				}
				break;

			// creates a DateTime object from datetimepicker
			case 'datetime':
				if ($val) {
					if ((defined('PAIR_FORM_DATETIME_FORMAT') and self::usingCustomDatetimepicker())) {
						$format = PAIR_FORM_DATETIME_FORMAT;
					} else {
						$format = 'Y-m-d H:i:s';
					}
					$val = \DateTime::createFromFormat('!' . $format, $val, $app->currentUser->getDateTimeZone());
					// DateTime is not set
					if (FALSE === $val) $val = NULL;
				} else {
					$val = NULL;
				}
				break;

		}

		return $val;

	}

	/**
	 * Check if the browser is using the native datepicker.
	 * @return	bool	TRUE if using the native datepicker.
	 */
	public static function usingCustomDatepicker(): bool {

		return !(isset($_COOKIE['InputTypesDate']) and $_COOKIE['InputTypesDate']);

	}

	/**
	 * Check if the browser is using the native datetimepicker.
	 * @return	bool	TRUE if using the native datetimepicker.
	 */
	public static function usingCustomDatetimepicker(): bool {

		return !(isset($_COOKIE['InputTypesDatetime']) and $_COOKIE['InputTypesDatetime']);

	}

}
