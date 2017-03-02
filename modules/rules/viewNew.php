<?php

/**
 * @version	$Id$
 * @author	Judmir Karriqi
 * @package	Pair
 */

use Pair\Module;
use Pair\View;
use Pair\Widget;

class RulesViewNew extends View {

	public function render() {

		$this->app->pageTitle = $this->lang('NEW_RULE');
		$this->app->activeMenuItem = 'rules/default';

		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');

		$modules = Module::getAllObjects(NULL, array('name'));

		$form = $this->model->getRulesForm();

		$form->getControl('module')->setListByObjectArray($modules, 'id', 'name');

		$this->assign('form', $form);
		
	}

}
