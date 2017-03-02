<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Acl;
use Pair\Controller;
use Pair\Group;
use Pair\Input;
use Pair\Router;
use Pair\User;

class UsersController extends Controller {

	public function defaultAction() {
		
		$this->view = 'userList';
		
	}
	
	public function userAddAction() {
		
		$username = Input::get('username');
		$password = Input::get('password');
		
		// check if username exist
		if (count(User::getAllObjects(array('username'=>$username)))) {
			$this->enqueueError($this->lang('USER_EXIST', $username));
			$this->app->redirect('users/userList');
		}

		$form = $this->model->getUserForm();

		$group = new Group(Input::get('groupId', 'int'));

		$user				= new User();
		$user->name			= Input::get('name');
		$user->surname		= Input::get('surname');
		$user->email		= Input::get('email') ? Input::get('email') : NULL;
		$user->ldapUser		= Input::get('ldapUser') ? Input::get('ldapUser') : NULL;
		$user->username		= $username;
		$user->enabled		= Input::get('enabled', 'bool');
		$user->groupId		= Input::get('groupId', 'int');
		$user->languageId	= Input::get('languageId', 'int');
		$user->admin		= FALSE;
		$user->faults		= 0;

		if ($password) {
			$user->hash = User::getHashedPasswordWithSalt($password);
		}

		if ($form->isValid() and $user->create()) {
			$this->enqueueMessage($this->lang('USER_HAS_BEEN_CREATED', $user->fullName));
			$this->app->redirect('users/userList');
		} else {
			$this->enqueueError($this->lang('USER_HAS_NOT_BEEN_CREATED', $user->fullName));
			foreach ($user->getErrors() as $error) {
				$this->enqueueError($error);
			}
			$this->view = 'userList';
		}

	}
	
	public function userEditAction() {
	
		$user = $this->getRequestedUser();
	
		if (is_a($user, 'Pair\User') and $user->isLoaded()) {
			$this->view = 'userEdit';
		} else {
			$this->view = 'userList';
		}
	
	}

	/**
	 * Do the user change.
	 */
	public function userChangeAction() {
	
		$form	= $this->model->getUserForm();
		$user	= new User(Input::get('id', 'int'));
		$group	= new Group(Input::get('groupId', 'int'));
		
		$password = Input::get('password');
		
		$user->name			= Input::get('name');
		$user->surname		= Input::get('surname');
		$user->email		= Input::get('email') ? Input::get('email') : NULL;
		$user->ldapUser		= Input::get('ldapUser') ? Input::get('ldapUser') : NULL;
		$user->username		= Input::get('username');
		$user->enabled		= Input::get('enabled', 'bool');
		$user->languageId	= Input::get('languageId', 'int');
		$user->groupId		= Input::get('groupId', 'int');

		if ($password) {
			$user->hash = User::getHashedPasswordWithSalt($password);
		}
		
		if (!$form->isValid()) {
			$this->enqueueError($this->lang('USER_HAS_NOT_BEEN_CHANGED', $user->fullName));
		} else if ($user->updateNotNull()) {
			$this->enqueueMessage($this->lang('USER_HAS_BEEN_CHANGED', $user->fullName));
		}
		
		$this->app->redirect('users/userList');
	
	}

	/**
	 * Do the user deletion.
	 */
	public function userDeleteAction() {

		$route	= Router::getInstance();
		$user	= new User($route->getParam(0));
		$group	= new Group($user->groupId);
		
		$fullName = $user->fullName;

		if ($user->delete()) {
			$this->enqueueMessage($this->lang('USER_HAS_BEEN_DELETED', $fullName));
		} else {
			$this->enqueueError($this->lang('USER_HAS_NOT_BEEN_DELETED', $fullName));
		}

		$this->app->redirect('users/userList');

	}

	public function groupAddAction() {
		
		$form = $this->model->getGroupForm();
				
		$group				= new Group();
		$group->name		= Input::get('name');
		$group->default		= Input::get('default', 'bool');

		if ($form->isValid() and $group->create()) {
			$this->enqueueMessage($this->lang('GROUP_HAS_BEEN_CREATED',   $group->name));
		} else {
			$this->enqueueError($this->lang('GROUP_HAS_NOT_BEEN_CREATED', $group->name));
		}
		
		$this->app->redirect('users/groupList');
		
	}
	
	/**
	 * Shows group-edit page.
	 */
	public function groupEditAction() {

		$group = $this->getRequestedGroup();

		if ($group) {
			$this->view = 'groupEdit';
		} else {
			$this->view = 'groupList';
		}

	}
	
	/**
	 * Performs changes on a group.
	 */
	public function groupChangeAction() {

		$group = new Group(Input::get('id', 'int'));
				
		$form = $this->model->getGroupForm();

		$group->name = Input::get('name');
				
		// if this group is default, it will stay
		$group->default = $group->default ? 1 : Input::get('default', 'bool');

		if ($form->isValid() and $group->update(array('name', 'default'))) {

			// updates related acl to default
			$group->setDefaultAcl(Input::get('defaultAclId', 'int'));
			
			// notice only if group change
			$this->enqueueMessage($this->lang('GROUP_HAS_BEEN_CHANGED', $group->name));

			$this->app->redirect('users/groupList');

		} else {
		
			// warn of possible errors
			foreach ($group->getErrors() as $error) {
				$this->enqueueError($error);
			}
			
			$this->view = 'groupList';
			
		}
		
	}
				
	/**
	 * Performs deletion on a group.
	 */
	public function groupDeleteAction() {

		$route = Router::getInstance();

		$group = new Group($route->getParam(0));

		if ($group->canBeDeleted()) {

			$groupName = $group->name;

			if ($group->delete()) {
				$this->enqueueMessage($this->lang('GROUP_HAS_BEEN_DELETED', $groupName));
			} else {
				$this->enqueueError($this->lang('GROUP_HAS_NOT_BEEN_DELETED', $groupName));
			}

		} else {

			$this->enqueueError($this->lang('GROUP_CAN_NOT_BEEN_DELETED', $group->name));

		}
		
		$this->app->redirect('users/groupList');
		
	}

	public function aclAddAction() {
	
		$route		= Router::getInstance();
		$groupId	= Input::get('groupId', 'int');
		$group		= new Group($groupId);
				 
		foreach ($_POST['aclChecked'] as $c) {

			$acl			= new Acl();
			$acl->ruleId	= $c;
			$acl->groupId	= $group->id;

			$acl->create();
	
		}
	
		$this->enqueueMessage($this->lang('NEW_ACCESS_PERMISSION_HAS_BEEN_CREATED'));
		$this->redirect('users/aclList/' . $group->id);
	
	}
	
	/**
	 * Deletes an ACL upon a request coming by URL after a check on group ownership.
	 */
	public function aclDeleteAction() {
		
		$route	= Router::getInstance();
		$aclId	= $route->getParam(0);
		$acl	= new Acl($aclId);
		
		if ($acl->isLoaded()) {
			
			$group = new Group($acl->groupId);
			
			$moduleName	= $acl->getModuleName();
			$groupId	= $acl->groupId;

			if ($acl->delete()) {
				$this->enqueueMessage($this->lang('ACCESS_PERMISSION_HAS_BEEN_DELETED', $moduleName));
			} else {
				$this->enqueueError($this->lang('ACCESS_PERMISSION_HAS_NOT_BEEN_DELETED', $moduleName));
			}
						
		}

		$this->redirect('users/aclList/' . $groupId);
	
	}
	
	/**
	 * Private method to obtain User object to edit.
	 *
	 * @return User|NULL
	 */
	private function getRequestedUser() {
	
		$route = Router::getInstance();
	
		$userId = $route->getParam(0);
	
		if (!$userId) {
			$this->enqueueError($this->lang('ITEM_TO_EDIT_IS_NOT_VALID'));
			return NULL;
		}
			
		$user = new User($userId);
		
		// valid user
		if ($user->isLoaded()) {

			return $user;

		// not loaded
		} else {
	
			$this->enqueueError($this->lang('ITEM_TO_EDIT_IS_NOT_VALID'));
			$this->logError('User id=' . $userId . ' has not been loaded');
			return NULL;
	
		}
	
	}

	/**
	 * Private method to obtain Group object to edit.
	 *
	 * @return Group|NULL
	 */
	private function getRequestedGroup() {

		$route = Router::getInstance();

		$groupId = $route->getParam(0);

		if (!$groupId) {
			$this->enqueueError($this->lang('ITEM_TO_EDIT_IS_NOT_VALID'));
			return NULL;
		}

		$group = new Group($groupId);

		if ($group->isLoaded()) {
			
			return $group;
				
		// not loaded
		} else {

			$this->enqueueError($this->lang('ITEM_TO_EDIT_IS_NOT_VALID'));
			$this->logError('Group id=' . $groupId . ' has not been loaded');
			return NULL;

		}

	}

}