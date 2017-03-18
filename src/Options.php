<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

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
	 * Returns an option’s value.
	 * 
	 * @param	string	The option’s name.
	 * @throws	Exception
	 * @return	mixed|NULL
	 */
	public function getValue($name) {
		
		$this->checkPopulated();
		
		try {
		
			if (array_key_exists($name, $this->list)) {
		
				return $this->list[$name]->value;
		
			} else {
				
				throw new \Exception('Option “'. $name .'” doesn’t exist for this object '. __CLASS__);
				
			}

		
		} catch(\Exception $e) {
			
			return NULL;
	
		}

	}
	
	/**
	 * Method to set an object property value.
	 * 
	 * @param	string	Property’s name.
	 * @param	mixed	Property’s value.
	 */
	public function setValue($name, $value) {

		$this->checkPopulated();
		
		if (array_key_exists($name, $this->list)) {
			
			if ('bool'==$this->list[$name]->type) {
				$value = $value ? 1 : 0;
			}

			$query = 'UPDATE options SET value = ? WHERE name = ?';

		}
		
		$this->db->exec($query, array($value, $name));
		$this->list[$name]->value = $value;
		
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
	
		$this->db->setQuery('SELECT * FROM options ORDER BY `group`');
		$res = $this->db->loadObjectList();
			
		$this->list = array();
			
		foreach ($res as $o) {
			
			// esplode le options
			if ('list'==$o->type) {
				
				$listItems = explode('|', $o->list_options);
				
				foreach ($listItems as $listEntry) {
					
					list ($val,$text)	= explode(',', $listEntry);
					
					$listItem			= new \stdClass(); 
					$listItem->value	= $val;
					$listItem->text 	= $text;
					
					$o->listItems[]		= $listItem;
					
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
			case 'text':	$value = (string)$value;	break;
			case 'int':		$value = (int)$value;		break;
			case 'bool':	$value = (bool)$value;		break;
			case 'custom':	break;
			case 'list':	break;
				
		}
		
		return $value;
	
	}
	
}
