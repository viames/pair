<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Acl;
use Pair\Application;
use Pair\Form;
use Pair\Group;
use Pair\Language;
use Pair\Model;
use Pair\Translator;
use Pair\User;

class UsersModel extends Model {

	/**
	 * Returns User objects of an instance found into DB.
	 *
	 * @return	array:User
	 */
	public function getUsers() {
	
		$query =
			'SELECT u.*, g.name AS group_name' .
			' FROM users AS u' .
			' INNER JOIN groups AS g ON u.group_id = g.id' .
			' ORDER BY u.name ASC' .
			' LIMIT ' . $this->pagination->start . ', ' . $this->pagination->limit;
		
		$this->db->setQuery($query);
		$list = $this->db->loadObjectList();
	
		$users = array();
	
		// creates all objects and populates
		foreach ($list as $item) {
			$user = new User($item);
			$user->groupName	= $item->group_name;
			$users[] = $user;
		}
	
		return $users;

	}

	/**
	 * Returns array of Group objects of an instance with userCount for Users that belongs to.
	 *
	 * @return	array:Group
	 */
	public function getGroups() {

		$query = 
			'SELECT g.*, m.name AS module_name,' .
			' (SELECT COUNT(*) FROM users WHERE group_id = g.id) AS user_count,' .
			' (SELECT COUNT(*) FROM acl WHERE group_id = g.id) AS acl_count' .
			' FROM groups AS g' .
			' LEFT JOIN acl AS a ON (g.id = a.group_id AND a.is_default=1)' .
			' LEFT JOIN rules AS r ON r.id = a.rule_id' .
			' LEFT JOIN modules AS m ON m.id = r.module_id' .
			' ORDER BY g.name';
		
		$this->db->setQuery($query);
		$list = $this->db->loadObjectList();
	
		$groups = array();
	
		foreach ($list as $row) {
			$group = new Group($row);
			$group->userCount	= $row->user_count;
			$group->aclCount	= $row->acl_count;
			$group->moduleName	= $row->module_name;
			$groups[] = $group;
		}
	
		return $groups;

	}
	
	/**
	 * Returns all access list of a group by its ID.
	 *
	 * @param	int		Group ID.
	 *
	 * @return	array:Acl
	 */
	public function getAcl($groupId) {
	
		$query =
			'SELECT a.*, r.action, m.name AS module_name,' .
			' CONCAT_WS(" ", m.name, r.action) AS module_action' .
			' FROM acl AS a' .
			' INNER JOIN rules AS r ON a.rule_id = r.id' .
			' INNER JOIN modules AS m ON r.module_id = m.id' .
			' WHERE a.group_id = ?' .
			' ORDER BY m.name ASC, r.action ASC';
	
		$this->db->setQuery($query);
		$list = $this->db->loadObjectList($groupId);
		
		$acls = array();
	
		foreach ($list as $row) {
			$acl = new Acl($row);
			$acl->action		= $row->action;
			$acl->moduleName	= $row->module_name;
			$acl->moduleAction	= $row->module_action;
			$acls[] = $acl;
		}
	
		return $acls;

	}
	
	/**
	 * Returns all rules.
	 *
	 * @return	array:Rule
	 */
	public function getRules() {
	
		$query =
			'SELECT r.*, m.name AS module_name,' .
			' CONCAT_WS(" ", m.name, r.action) AS module_action' .
			' FROM rules AS r' .
			' INNER JOIN modules AS m ON r.module_id = m.id' .
			' WHERE admin_only = 0' .
			' ORDER BY m.name ASC, r.action ASC';
	
		$this->db->setQuery($query);
		$list = $this->db->loadObjectList();
	
		$rules = array();
	
		foreach ($list as $row) {
			$rule = new Acl($row);
			$rule->action		= $row->action;
			$rule->moduleName	= $row->module_name;
			$rule->moduleAction	= $row->module_action;
			$rules[] = $rule;
		}
	
		return $rules;
	
	}

	/**
	 * Returns the Form object for create/edit User objects.
	 *
	 * @return Form
	 */
	public function getUserForm() {

		$app	= Application::getInstance();
		$tran	= Translator::getInstance();

		// lists for select
		$groups	= Group::getAllObjects(NULL, 'name');
		$languages = Language::getAllObjects(NULL, array('languageName'));
		
		$form	= new Form();
		$form->addControlClass('form-control');
		
		$form->addInput('id')->setType('hidden');
		$form->addInput('name')->setRequired()->setMinLength(2);
		$form->addInput('surname')->setRequired()->setMinLength(2);
		$form->addInput('email')->setType('email');
		$form->addInput('enabled')->setType('bool')->addClass('icheck');
		$form->addInput('ldapUser');
		$form->addInput('username', array('autocomplete'=>'off'))->setRequired()->setMinLength(3);
		$form->addInput('password', array('autocomplete'=>'off'))->setType('password')
			->setMinLength(5)->addClass('m-b');
		$form->addInput('showPassword')->setType('bool')->addClass('icheck');
		$form->addSelect('groupId')->setRequired()->setListByObjectArray($groups,'id','name');
		$form->addSelect('languageId')->setRequired()->setListByObjectArray($languages,'id','languageName');

		return $form;

	}

	/**
	 * Returns the Form object for create/edit Group objects.
	 *
	 * @return Form
	 */
	public function getGroupForm() {

		$form = new Form();
		$form->addControlClass('form-control');
		
		$form->addInput('id')->setType('hidden');
		$form->addInput('name')->setRequired()->setMinLength(3);
		$form->addInput('default')->setType('bool')->addClass('icheck');
		$form->addSelect('defaultAclId');

		return $form;

	}

	/**
	 * Returns the Form object for create/edit Acl objects.
	 *
	 * @return Form
	 */
	public function getAclListForm() {

		$form = new Form();
		$form->addControlClass('form-control');

		$form->addInput('groupId')->setType('hidden');

		return $form;

	}
	
}
