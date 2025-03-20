<?php

namespace Pair\Models;

use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;

class Rule extends ActiveRecord {

	/**
	 * Table primary key.
	 */
	protected int $id;

	/**
	 * Optional value to set permission on just one action. If null, it means all actions.
	 */
	protected ?string $action = NULL;

	/**
	 * Flag to set access granted on administrators only
	 */
	protected bool $adminOnly;

	/**
	 * Module ID.
	 */
	protected int $moduleId;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'rules';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Table structure [Field => Type, Null, Key, Default, Extra].
	 */
	const TABLE_DESCRIPTION = [
		'id'			=> ['int unsigned', 'NO', 'PRI', NULL, 'auto_increment'],
		'action'		=> ['varchar(30)', 'YES', '', NULL, ''],
		'admin_only'	=> ['tinyint(1)', 'NO', '', '0', ''],
		'module_id'		=> ['int unsigned', 'NO', 'MUL', NULL, '']
	];
	
	/**
	 * Set for converts from string to Datetime, integer or boolean object in two ways.
	 */
	protected function _init(): void {
	
		$this->bindAsInteger('id');
	
		$this->bindAsBoolean('adminOnly');
	
	}

	/**
	 * Returns array with matching object property name on related db fields.
	 *
	 * @return array
	 */
	protected static function getBinds(): array {

		$varFields = [
			'id'		=> 'id',
			'action'	=> 'action',
			'adminOnly'	=> 'admin_only',
			'moduleId'	=> 'module_id'
		];

		return $varFields;

	}
	
	/**
	 * Deletes all Acl of this Rule.
	 */
	protected function beforeDelete(): void {
	
		$acls = Acl::getAllObjects(['ruleId' => $this->id]);
		foreach ($acls as $acl) {
			$acl->delete();
		}
	
	}
	
	/**
	 * Returns the db-record of the current Rule object, NULL otherwise.
	 * 
	 * @param	int		Module ID.
	 * @param	string	Action name.
	 * @param	bool	Optional flag to get admin-only rules.
	 */
	public static function getRuleModuleName(int $module_id, string $action, bool $adminOnly=FALSE): ?\stdClass {

		$query =
			'SELECT m.`name` AS `moduleName`, r.`action` AS `ruleAction`, r.`admin_only`
			FROM `rules` AS r
			INNER JOIN `modules` AS m ON m.`id` = r.`module_id`
			WHERE m.`id` = ? AND r.`action` = ? AND r.`admin_only` = ?';

		return Database::load($query, [$module_id, $action, $adminOnly], Database::OBJECT);

	}

}