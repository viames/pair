<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

/**
 * Manage HTTP requests.
 */
class Input {
	
	/**
	 * Gets data by POST or GET array returning the specified type or default value. In case
	 * of not found, return default value. Manage array inputs.
	 * 
	 * @param	string	Name of POST or GET parameter.
	 * @param	string	Type string -default-, int, bool, date or datetime will be casted.
	 * @param	string	Default value to return, in case of null or empty.
	 * 
	 * @return	multitype
	 */
	public static function get($name, $type='string', $default=NULL) {

		$val = "";

		switch ($_SERVER['REQUEST_METHOD']) {

			case 'GET':
				$request = &$_GET;
				break;
				
			case 'POST':
				$request = &$_POST;
				break;
		}
					
		// remove [] from array
		if (substr($name, -2) == '[]') {
			$name = substr($name, 0, -2);
		}
		
		// seeking a possible name
		if (array_key_exists($name, $request)) {
			
			// evaluates whether it’s an array
			if (is_array($request[$name])) {
				
				$values = array();

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
	 * Gets all submitted field name that matches regular expressions. 
	 *
	 * @param	string	Regular expression, for instance #[A-Z][A-Z_]+#.
	 * @param	string	Type string, int, bool, date or datetime will be casted.
	 * @param	string	Default value to return, in case of null or empty.
	 *
	 * @return	array
	 */
	public static function getInputsByRegex($pattern, $type='string', $default=NULL) {
	
		$list = array();
		
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
	public static function formPostSubmitted() {
		
		return (bool)count($_POST);
		
	}
	
	/**
	 * Casts a value to a type string, integer or boolean.
	 * 
	 * @param	string	Type string, int or bool, will be casted.
	 * 
	 * @return	multitype
	 */
	private static function castTo($val, $type) {
		
		$app = Application::getInstance();
		
		switch ($type) {
				
			case 'string':
			case 'text':
				$val = (string)trim($val);
				break;
		
			case 'int':
			case 'integer':
				$val = (int)$val;
				break;
		
			case 'bool':
			case 'boolean':
				$val = (bool)$val;
				break;
				
			// creates a DateTime object from datetimepicker
			case 'date':
			case 'datetime':
				if ($val) {
					$format = 'datetime'==$type ? '!Y-m-d H:i' : '!Y-m-d';
					$val = \DateTime::createFromFormat($format, $val, $app->currentUser->getDateTimeZone());
					// not valid DateTime is FALSE
					if (FALSE === $val) $val = NULL;
				} else {
					$val = NULL;
				}
				break;

		}
		
		return $val;
		
	}
	
}
