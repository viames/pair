<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Group;
use Pair\View;
use Pair\Widget;

class UserViewProfile extends View {

	public function render() {
		
		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');

		$user	= $this->app->currentUser;
		$group	= new Group($user->groupId);

		$this->app->pageTitle = $this->lang('USER_PROFILE_OF', $user->fullName);
		
		$form = $this->model->getUserForm();
		$form->setValuesByObject($user);

		$form->getControl('name')->setDisabled();
		$form->getControl('surname')->setDisabled();
		$form->getControl('email')->setDisabled();
		$form->getControl('username')->setDisabled();
		$form->getControl('languageId')->setDisabled();
		
		$this->assign('user',  $user);
		$this->assign('form',  $form);
		$this->assign('group', $group);
		
	}

}
