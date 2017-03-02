<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Language;
use Pair\View;
use Pair\Widget;

class UserViewProfileEdit extends View {

	public function render() {

		$this->app->activeMenuItem = 'users/userList';

		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');
		
		$user		= $this->app->currentUser;
		$languages	= Language::getAllObjects(NULL, array('languageName'));

		$this->app->pageTitle = $this->lang('USER_EDIT', $user->fullName);

		$form = $this->model->getUserForm();
		$form->setValuesByObject($user);
		$form->getControl('languageId')->setListByObjectArray($languages,'id','languageName')->setValue($user->languageId);
		
		$this->assign('user',	$user);
		$this->assign('form',	$form);
		
	}

}
