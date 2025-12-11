<?php

namespace Pair\Models;

use Pair\Core\Application;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Collection;
use Pair\Orm\Database;

class Group extends ActiveRecord {

	/**
	 * Property that binds db primary key id.
	 */
	protected int $id;

	/**
	 * Property that binds db field name.
	 */
	protected string $name;

	/**
	 * Property that binds db field is_default.
	 */
	protected bool $default;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'groups';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = ['id'];

	/**
	 * Set for converts from string to Datetime, integer or boolean object in two ways.
	 */
	protected function _init(): void {

		$this->bindAsInteger('id');

		$this->bindAsBoolean('default');

	}

	/**
	 * Adds all rules that don’t exist in ACL table for this group but admins rules.
	 */
	public function addAllAcl(): void {

		$query =
			'INSERT INTO `acl` (`rule_id`, `group_id`, `is_default`)
			SELECT `id`, ?, 0 FROM `rules`
			WHERE `admin_only` = 0 AND `id` NOT IN(
			  SELECT `rule_id` FROM `acl` WHERE `group_id` = ?
			)';

		Database::run($query, [$this->id, $this->id]);

	}

	/**
	 * If default is true, will set other groups to zero. Insert default Module as
	 * ACL default module when create a Group.
	 */
	protected function afterCreate(): void {

		// unset the other defaults
		$this->unsetSiblingsDefaults();

		// get the rule_id of default module
		$defaultRuleId = $this->getDefaultModule();

		if ($defaultRuleId) {

			// create first ACL with rule_id of default module
			$acl			= new Acl();
			$acl->ruleId	= $defaultRuleId;
			$acl->groupId   = $this->id;
			$acl->default	= true;

			$acl->create();

		}

	}

	/**
	 * If isDefault is 1 or true, will set other groups to zero.
	 */
	protected function beforeUpdate(): void {

		$this->unsetSiblingsDefaults();

	}

	/**
	 * Deletes all Acl and User objects of this Group.
	 */
	protected function beforeDelete(): void {

		$acls = Acl::getAllObjects(['groupId' => $this->id]);
		foreach ($acls as $acl) {
			$acl->delete();
		}

		$userClass = Application::getInstance()->userClass;
		$users = $userClass::getAllObjects(['groupId' => $this->id]);
		foreach ($users as $user) {
			$user->delete();
		}

	}

	/**
	 * Return Rule objects from acl table for this Group.
	 */
	public function getAcls(): Collection {

		return Acl::getAllObjects(['groupId'=>$this->id]);

	}

	/**
	 * Return Rule objects which does not exist in acl table for this Group.
	 */
	public function getAllNotExistRules(): Collection {

		$query =
			'SELECT r.*, m.`name` AS `module_name`
			FROM `rules` AS r
			INNER JOIN `modules` AS m ON m.`id` = r.`module_id`
			WHERE `admin_only` = 0
			AND r.`id` NOT IN(SELECT a.`rule_id` FROM `acl` AS a WHERE a.`group_id` = ?)';

		return Rule::getObjectsByQuery($query, [$this->id]);

	}

	/**
	 * Returns array with matching object property name on related db fields.
	 */
	protected static function getBinds(): array {

		return [
			'id'		=> 'id',
			'name'		=> 'name',
			'default' 	=> 'is_default'
		];

	}

	/**
	 * Returns the default Group object, null otherwise.
	 */
	public static function getDefault(): ?Group {

		return self::getObjectByQuery('SELECT * FROM `groups` WHERE `is_default` = 1');

	}

	/**
	 * Get default Acl object of this group, if any, null otherwise.
	 */
	public function getDefaultAcl(): ?Acl {

		$query = 'SELECT * FROM `acl` WHERE `group_id` = ? AND `is_default` = 1';
		return Acl::getObjectByQuery($query, [$this->id]);

	}

	/**
	 * Get rule ID of default module with "users" name.
	 */
	private function getDefaultModule(): int {

		$query =
			'SELECT r.`id`
			FROM `rules` as r
			INNER JOIN `modules` as m ON m.`id` = r.`module_id`
			WHERE m.`name` = "users"
			LIMIT 1';

		return (int)Database::load($query, [], Database::RESULT);

	}

	/**
	 * Returns list of User objects of this Group.
	 */
	public function getUsers(): Collection {

		// a subclass may have been defined for the user
		$userClass = Application::getInstance()->userClass;

		return $userClass::getObjectsByQuery('SELECT * FROM `users` WHERE group_id=?', [$this->id]);

	}

	/**
	 * Set a default Acl of this group.
	 */
	public function setDefaultAcl(int $aclId): void {

		// set no default to siblings
		Database::run('UPDATE `acl` SET `is_default` = 0 WHERE `group_id` = ? AND `id` <> ?', [$this->id, $aclId]);

		// set default to this
		Database::run('UPDATE `acl` SET `is_default` = 1 WHERE `group_id` = ? AND `id` = ?', [$this->id, $aclId]);

	}

	/**
	 * If default property is true, will set is_default=0 on all other groups.
	 */
	protected function unsetSiblingsDefaults() {

		if ($this->default) {
			Database::run('UPDATE `groups` SET `is_default` = 0 WHERE `id` <> ?', [$this->id]);
		}

	}

}