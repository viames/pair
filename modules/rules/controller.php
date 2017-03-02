<?php

/**
 * @version	$Id$
 * @author	Judmir Karriqi
 * @package	Pair
 */

use Pair\Controller;
use Pair\Input;
use Pair\Module;
use Pair\Rule;

class RulesController extends Controller {

	/**
	 * Adds a new object.
	 */
	public function addAction() {

		// get input value
		$moduleId   = Input::get('module', 'int');
		$actionAcl  = Input::get('actionAcl') ? Input::get('actionAcl') : NULL;
		$adminOnly  = Input::get('adminOnly', 'bool');

		$rule = Rule::getRuleModuleName($moduleId, $actionAcl, $adminOnly);

		if (!$rule) {

			$rules = new Rule();
			$rules->moduleId	= $moduleId;
			$rules->action		= $actionAcl;
			$rules->adminOnly   = $adminOnly;

			// TODO remove this after remove module field from rules table
			$module = new Module($moduleId);
			$rules->module = $module->name;

			if ($rules->create()) {
				$this->enqueueMessage($this->lang('RULE_HAS_BEEN_CREATED', $module->name));
			} else {
				$this->enqueueError($this->lang('RULE_HAS_NOT_BEEN_CREATED'));
			}

		}  else {
			
			$this->enqueueError($this->lang('RULE_EXISTS', array($rule->moduleName, $rule->ruleAction)));
			
		}

		$this->redirect('rules/default');

	}

	public function editAction() {

		$rules = $this->getObjectRequestedById('Pair\Rule');

		if ($rules) {
			$this->view = 'edit';
		}

	}

	/**
	 * Modify or delete an object.
	 */
	public function changeAction() {

		$this->view = 'default';
		$rule = new Rule(Input::get('id'));

		switch (Input::get('action')) {

			case 'edit':

				// get input value
				$moduleId   = Input::get('module');
				$actionAcl  = Input::get('actionAcl');
				$adminOnly  = Input::get('adminOnly');

				// checks if record already exists
				$checkRule = Rule::getRuleModuleName($moduleId, $actionAcl, $adminOnly);

				// get module name
				$module			 = new Module($moduleId);

				// if nothing found or record has the same ID
				if (!$checkRule) {

					$rule->moduleId  = Input::get('module');
					$rule->action	= Input::get('actionAcl');
					$rule->adminOnly = Input::get('adminOnly', 'bool');

					if ($rule->update()) {
						$this->enqueueMessage($this->lang('RULE_HAS_BEEN_CHANGED_SUCCESSFULLY', $module->name));
					}

				} else {
					$this->enqueueError($this->lang('RULE_EDIT_EXISTS',array($module->name,$checkRule->ruleAction)));
				}
				break;

			case 'delete':

				if ($rule->delete()) {
					$this->enqueueMessage($this->lang('RULE_HAS_BEEN_DELETED_SUCCESSFULLY'));
				} else {
					$this->enqueueError($this->lang('ERROR_DELETING_RULES'));
				}
				break;

		}

	}

}