<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Breadcrumb;
use Pair\View;
use Pair\Widget;

class UsersViewUserNew extends View {

	public function render() {
		
		$this->app->pageTitle = $this->lang('NEW_USER');
		$this->app->activeMenuItem = 'users/userList';
		
		$breadcrumb = Breadcrumb::getInstance();
		$breadcrumb->addPath($this->lang('USERS'), 'users/userList');
		$breadcrumb->addPath($this->lang('NEW_USER'), 'users/userNew');
		
		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');

		$form = $this->model->getUserForm();

		$form->getControl('enabled')->setValue(TRUE);
		$form->getControl('password')->setRequired();

		$this->assign('form', $form);
		
	}
	
}