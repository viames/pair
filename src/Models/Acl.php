<?php

namespace Pair\Models;

use Pair\Models\Audit;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;

/**
 * Access control list (ACL) model. Represents authorization rules linking user groups to allowed
 * modules/actions. Provides helpers to resolve rule-related metadata (e.g., module name) and to
 * evaluate permissions through database-backed checks.
 */
class Acl extends ActiveRecord {

	/**
	 * Primary key of this ACL record (table `acl`).
	 */
	protected int $id;

	/**
	 * Property that binds db field rule_id.
	 */
	protected int $ruleId;

	/**
	 * Property that binds db field group_id.
	 */
	protected ?int $groupId = null;

	/**
	 * Property that binds db field is_default.
	 */
	protected bool $default;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'acl';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function _init(): void {

		$this->bindAsInteger('id', 'ruleId', 'groupId');

		$this->bindAsBoolean('default');

	}

	/**
	 * Trigger function called after delete() method execution.
	 */
	protected function afterCreate(): void {

		Audit::aclAdded($this);

	}

	/**
	 * Trigger function called after delete() method execution.
	 */
	protected function beforeDelete(): void {

		Audit::aclRemoved($this);

	}

	/**
	 * Determines whether a user group can access a given module/action with a single SQL COUNT query.
	 * Behavior:
	 * - Bypasses ACL checks when $admin is true or when $module === 'user'.
	 * - Otherwise, validates the presence of a non-admin-only rule that either:
	 *   a) matches the module with a null/empty action (wildcard), or
	 *   b) matches the exact module + action pair.
	 *
	 * @param bool			Whether the current user is an administrator
	 * @param int			The ID of the user's group
	 * @param string		The target module name
	 * @param string|null	The target action name, or null to allow any action
	 * @return bool			True if access is granted by at least one matching rule, false otherwise
	 */
	public static function checkPermission($admin, $groupId, $module, $action = null): bool {

		// login and logout are always allowed
		if ('user'==$module or $admin) {
			return true;
		}

		// build a single-count query to verify at least one matching acl rule exists
		$query =
			'SELECT COUNT(*)
			FROM `rules` AS r
			INNER JOIN `acl` AS a ON a.`rule_id` = r.`id`
			WHERE a.`group_id` = ?
			AND r.`admin_only` = 0
			AND (
				(r.`module` = ? AND (r.`action` IS NULL OR r.`action`=""))
				OR (r.`module` = ? AND r.`action` = ?)
			)';

		$count = Database::load($query, [$groupId, $module, $module, $action], Database::COUNT);

		// true if at least one rule matches
		return (bool)$count;

	}

	/**
	 * Returns array with matching object property name on related db fields.
	 */
	protected static function getBinds(): array {

		$varFields = [
			'id'		=> 'id',
			'ruleId'	=> 'rule_id',
			'groupId'	=> 'group_id',
			'default'	=> 'is_default'
		];

		return $varFields;

	}

	/**
	 * Returns the human-readable module name associated with this rule.
	 *
	 * Resolves the module label by joining the `rules` table with `modules`
	 * using the current record's rule id.
	 *
	 * @return string the module name linked to this ACL rule
	 */
	public function getModuleName(): string {

		$query =
			'SELECT m.`name`
			FROM `rules` as r
			INNER JOIN `modules` as m ON m.`id` = r.`module_id`
			WHERE r.`id` = ?';

		// return single scalar result
		return Database::load($query, [$this->ruleId], Database::RESULT);

	}

}
