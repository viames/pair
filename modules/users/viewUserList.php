<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Breadcrumb;
use Pair\User;
use Pair\View;
use Pair\Widget;

class UsersViewUserList extends View {

	public function render() {

		$this->app->pageTitle = 'Gestione utenti';
		$this->app->activeMenuItem = 'users/userList';

		$breadcrumb = Breadcrumb::getInstance();
		$breadcrumb->addPath($this->lang('USERS'), 'users/userList');
		
		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');

		$users = $this->model->getUsers();

		$this->pagination->count = User::countAllObjects(array('admin'=>0));
		
		foreach ($users as $user) {

			$user->enabledIcon	= $user->enabled ? '<i class="fa fa-lg fa-check-square-o"></i>' : '<i class="fa fa-lg fa-square-o"></i>';
			$user->adminIcon	= $user->admin ? 'admin' : NULL;
			
		}

		$this->assign('users', $users);

	}
	
}
