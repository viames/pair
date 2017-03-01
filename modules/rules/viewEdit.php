<?php

/**
 * @version	$Id$
 * @author	Judmir Karriqi
 * @package	VMS
 */

use VMS\Module;
use VMS\Router;
use VMS\Rule;
use VMS\View;
use VMS\Widget;

class RulesViewEdit extends View {

	public function render() {

		$route = Router::getInstance();

		$this->app->pageTitle = $this->lang('EDIT_RULE');
		$this->app->activeMenuItem = 'rules/default';

		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');

		$modules = Module::getAllObjects(NULL, array('name'));

		$rule = new Rule($route->getParam(0));

		$form = $this->model->getRulesForm();
		
		$form->getControl('id')->setValue($rule->id);
		$form->getControl('module')->setListByObjectArray($modules, 'id', 'name')->setValue($rule->moduleId);
		$form->getControl('actionAcl')->setValue($rule->action);
		$form->getControl('adminOnly')->setValue($rule->adminOnly);

		$this->assign('form', $form);
		
	}
	
}
