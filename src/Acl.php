<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

class Acl extends ActiveRecord {

	/**
	 * Property that binds db primary key id.
	 * @var int
	 */
	protected $id;

	/**
	 * Property that binds db field rule_id.
	 * @var int
	 */
	protected $ruleId;

	/**
	 * Property that binds db field group_id.
	 * @var int
	 */
	protected $groupId;
	
	/**
	 * Property that binds db field is_default.
	 * @var bool
	 */
	protected $default;

	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'acl';

	/**
	 * Name of primary key db field.
	 * @var string
	 */
	const TABLE_KEY = 'id';

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init() {
			
		$this->bindAsInteger('id', 'ruleId', 'groupId');
		
		$this->bindAsBoolean('default');
			
	}
	
	/**
	 * Returns array with matching object property name on related db fields.
	 *
	 * @return array
	 */
	protected static function getBinds() {

		$varFields = array(
			'id'		=> 'id',
			'ruleId'	=> 'rule_id',
			'groupId'	=> 'group_id',
			'default'	=> 'is_default');

		return $varFields;

	}

	/**
	 * Checks if user-group is allowed on a module/action doing a single sql query to db.
	 * Expensive function.
	 *
	 * @param	bool	Flag for admin user (=TRUE).
	 * @param	int		User group ID.
	 * @param	string	Name of invoked module.
	 * @param	string	Name of invoked action or null if any action is valid.
	 *
	 * @return	bool
	 */
	public static function checkPermission($admin, $groupId, $module, $action=NULL) {
		
		$app = Application::getInstance();
		$db  = Database::getInstance();

		// login and logout are always allowed
		if ('user'==$module or $admin) {
			
			return TRUE;
			
		} else {
		 
			$query =
				'SELECT COUNT(*)' .
				' FROM rules AS r' .
				' INNER JOIN acl AS a ON a.rule_id = r.id'.
				' WHERE a.group_id = ?' .
				' AND r.admin_only = 0' .
				' AND ((r.module = ? AND (r.action IS NULL OR r.action=""))' .
				'  OR (r.module = ? AND r.action = ?))';
			
			$db->setQuery($query);
			
			$count = $db->loadCount(array($groupId, $module, $module, $action));
			
			return (bool)$count;
			
		}

	}
	
	/**
	 * Returns module name for this ACL.
	 *
	 * @return	string
	 */
	public function getModuleName() {
	
		$query =
			' SELECT m.name' .
			' FROM rules as r ' .
			' INNER JOIN modules as m ON m.id = r.module_id'.
			' WHERE r.id = ?';
	
		$this->db->setQuery($query);
		$name = $this->db->loadResult($this->ruleId);
	
		return $name;
	
	}

}
