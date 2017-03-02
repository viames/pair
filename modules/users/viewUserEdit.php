<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Breadcrumb;
use Pair\Group;
use Pair\Router;
use Pair\User;
use Pair\View;
use Pair\Widget;

class UsersViewUserEdit extends View {

	public function render() {
		
		$route = Router::getInstance();
		
		$this->app->pageTitle = $this->lang('USER_EDIT');
		$this->app->activeMenuItem = 'users/userList';
		
		$userId	= $route->getParam(0);
		$user	= new User($userId);
		
		$breadcrumb = Breadcrumb::getInstance();
		$breadcrumb->addPath($this->lang('USERS'), 'users/userList');
		$breadcrumb->addPath('Modifica utente ' . $user->fullName);
		
		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');

		// get user group
		$groupName = new Group($user->groupId);
		$user->groupName = $groupName->name;

		$form = $this->model->getUserForm();

		$form->getControl('id')->setValue($user->id)->setRequired();
		$form->getControl('groupId')->setValue($user->groupId);
		$form->getControl('languageId')->setValue($user->languageId);
		$form->getControl('name')->setValue($user->name);
		$form->getControl('surname')->setValue($user->surname);
		$form->getControl('email')->setValue($user->email);
		$form->getControl('enabled')->setValue($user->enabled);
		$form->getControl('ldapUser')->setValue($user->ldapUser);
		$form->getControl('username')->setValue($user->username);

		$this->assign('user', $user);
		$this->assign('form', $form);
		
	}

}
