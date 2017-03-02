<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

class Rule extends ActiveRecord {

	/**
	 * Table primary key.
	 * @var int
	 */
	protected $id;

	/**
	 * Optional value to set permission on just one action. If null, it means all actions.
	 * @var string|NULL
	 */
	protected $action;

	/**
	 * Flag to set access granted on administrators only
	 * @var bool
	 */
	protected $adminOnly;

	/**
	 * Name of module, lower case.
	 * @var string
	 */
	protected $moduleId;

	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'rules';

	/**
	 * Name of primary key db field.
	 * @var string
	 */
	const TABLE_KEY = 'id';

	/**
	 * Set for converts from string to Datetime, integer or boolean object in two ways.
	 */
	protected function init() {
	
		$this->bindAsInteger('id');
	
		$this->bindAsBoolean('adminOnly');
	
	}

	/**
	 * Returns array with matching object property name on related db fields.
	 *
	 * @return array
	 */
	protected static function getBinds() {

		$varFields = array(
			'id'		=> 'id',
			'action'	=> 'action',
			'adminOnly'	=> 'admin_only',
			'moduleId'	=> 'module_id');

		return $varFields;

	}
	
	/**
	 * Deletes all Acl of this Rule.
	 */
	protected function beforeDelete() {
	
		$acls = Acl::getAllObjects(array('ruleId' => $this->id));
		foreach ($acls as $acl) {
			$acl->delete();
		}
	
	}
	
	/**
	 * Returns the current Rule object, NULL otherwise.
	 *
	 * @return	Rule|NULL
	 */
	public static function getRuleModuleName($module_id,$action, $adminOnly) {

		$db = Database::getInstance();

		$query =
			' SELECT m.name as moduleName,r.action as ruleAction, r.admin_only '.
			' FROM rules as r '.
			' INNER JOIN modules as m ON m.id = r.module_id '.
			' WHERE m.id = ? and r.action = ? and r.admin_only = ?';

		$db->setQuery($query);
		$module = $db->loadObject(array($module_id, $action, $adminOnly));

		return $module;

	}

}
