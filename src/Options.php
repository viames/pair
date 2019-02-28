<?php

namespace Pair;

class Options {
	
	/**
	 * Singleton instance object.
	 * @var Options|NULL
	 */
	private static $instance = NULL;
	
	/**
	 * DB connection object.
	 * @var Database
	 */
	protected $db;
	
	/**
	 * Option list.
	 * @var array
	 */
	private $list;
	
	/**
	 * Constructor, connects to database.
	 */
	private function __construct() {
		
		$this->db = Database::getInstance();

	}
	
	/**
	 * Returns this object’s singleton.
	 *
	 * @return	Options
	 */
	public static function getInstance() {
	
		if (NULL == self::$instance) {
			self::$instance = new self();
		}
	
		return self::$instance;
	
	}
	
	/**
	 * Returns all options as associative array.
	 * 
	 * @return array
	 */
	public function getAll() {
		
		$this->checkPopulated();		
		
		return $this->list;
		
	}
	
	/**
	 * Proxy method to return an option’s value.
	 *
	 * @param	string	The option’s name.
	 * @throws	Exception
	 * @return	mixed|NULL
	 */
	public static function get($name) {
		
		$self = static::getInstance();
		
		$self->checkPopulated();
		
		try {
			
			if (!array_key_exists($name, $self->list)) {
				throw new \Exception('Cannot read the value of option “'. $name .'” as it doesn’t exist.');
			}

			return $self->list[$name]->value;
			
		} catch(\Exception $e) {
			
			$app = Application::getInstance();
			$app->logWarning($e->getMessage());
			
		}
		
	}
	
	/**
	 * Proxy method to return an option’s value.
	 *
	 * @param	string	The option’s name.
	 * @throws	Exception
	 * @return	mixed|NULL
	 */
	public static function set($name, $value) {
		
		// instance of the singleton
		$self = static::getInstance();
		
		// populate all the options by reading db once
		$self->checkPopulated();
		
		// check if named option exists
		try {
			
			if (!array_key_exists($name, $self->list)) {
				throw new \Exception('Cannot write the value of option “'. $name .'” as it doesn’t exist.');
			}
				
			switch ($self->list[$name]->type) {
				
				case 'bool';
					$value = $value ? 1 : 0;
					break;
					
				case 'password':
					if ($self->isCryptAvailable()) {
						$value = openssl_encrypt($value, 'AES128', OPTIONS_CRYPT_KEY); 
					} else {
						throw new \Exception('OPTIONS_CRYPT_KEY constant must be defined into config.php file.');
					}
					break;
					
			}

			// update the value into db
			Database::run('UPDATE `options` SET `value` = ? WHERE `name` = ?', [$value, $name]);
			
			// update value into the singleton object
			$self->list[$name]->value = $value;
			
		} catch(\Exception $e) {
			
			$app = Application::getInstance();
			$app->logWarning($e->getMessage());
			
		}
		
	}
	
	/**
	 * Returns an option’s value.
	 * 
	 * @param	string	The option’s name.
	 * @throws	Exception
	 * @return	mixed|NULL
	 * 
	 * @deprecated Use static method get() instead.
	 */
	public function getValue($name) {
		
		return self::get($name);
		
	}
	
	/**
	 * Method to set an object property value.
	 * 
	 * @param	string	Property’s name.
	 * @param	mixed	Property’s value.
	 * 
	 * @deprecated Use static method set() instead.
	 */
	public function setValue($name, $value) {
		
		return self::set($name, $value);
		
	}
	
	/**
	 * Refresh the option values by loading from DB.
	 */
	public function refresh() {
		
		$this->populate();
		
	}
	
	/**
	 * If this option list is empty, start loading from DB.
	 */
	private function checkPopulated() {
	
		if (!is_array($this->list)) {
			$this->populate();
		}
	
	}
	
	/**
	 * Load from DB and sets all options to this object.
	 *
	 * @return array
	 */
	private function populate() {
	
		$res = Database::load('SELECT * FROM `options` ORDER BY `group`');
			
		$this->list = array();
			
		foreach ($res as $o) {
			
			// esplode le options
			if ('list'==$o->type) {
				
				$listItems = explode('|', $o->list_options);
				
				foreach ($listItems as $listEntry) {
					
					list ($val,$text)	= explode(',', $listEntry);
					
					$listItem = new \stdClass();
					
					$listItem->value = $val;
					$listItem->text  = $text;
					
					$o->listItems[] = $listItem;
					
				}
				
			}

			// casting
			$o->value = $this->castTo($o->value, $o->type);
			
			$this->list[$o->name] = $o;
			
		}
	
	}
	
	/**
	 * Private utility for casting to proper type.
	 * 
	 * @param	mixed	The variable value.
	 * @param	string	the variable type (text, int, bool, custom, list).
	 * 
	 * @return	mixed
	 */
	private function castTo($value, $type) {
	
		switch ($type) {
	
			default:
				$value = (string)$value;
				break;
				
			case 'int':
				$value = (int)$value;
				break;
				
			case 'bool':
				$value = (bool)$value;
				break;
				
			case 'password':
				if ($this->isCryptAvailable()) {
					$value = openssl_decrypt($value, 'AES128', OPTIONS_CRYPT_KEY);
				} else {
					$app = Application::getInstance();
					$app->logWarning('OPTIONS_CRYPT_KEY constant should be defined into config.php file.');
				}
				break;
				
			case 'list':
				break;
				
		}
		
		return $value;
	
	}
	
	/**
	 * Check wheter options crypt key has been defined into config.php file.
	 * 
	 * @return boolean
	 */
	public function isCryptAvailable() {
		
		return (defined('OPTIONS_CRYPT_KEY') and strlen(OPTIONS_CRYPT_KEY) > 0);
		
	}
	
}
