<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */
use Pair\Breadcrumb;
use Pair\Group;
use Pair\Router;
use Pair\View;
use Pair\Widget;

class UsersViewAclNew extends View {

	public function render() {

		$route = Router::getInstance();
		$groupId = $route->getParam(0);

		$group = new Group($groupId);

		$this->app->pageTitle = 'Aggiungi ACL';
		$this->app->activeMenuItem = 'users/groupList';

		$breadcrumb = Breadcrumb::getInstance();
		$breadcrumb->addPath($this->lang('GROUPS'), 'users/groupList');
		$breadcrumb->addPath('Gruppo ' . $group->name, 'users/groupEdit/' . $group->id);
		$breadcrumb->addPath('Access list', 'users/aclList/' . $group->id);
		$breadcrumb->addPath('Aggiungi ACL');
		
		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');

		$rules = $group->getAllNotExistRules();

		$form = $this->model->getAclListForm();

		$form->getControl('groupId')->setValue($group->id);

		$this->assign('group',	$group);
		$this->assign('rules',	$rules);
		$this->assign('form',	$form);

	}

}
