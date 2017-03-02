<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Options;
use Pair\Router;
use Pair\View;
use Pair\Widget;

class DeveloperViewModuleWizard extends View {

	public function render() {

		$options = Options::getInstance();
		
		$this->app->activeMenuItem = 'developer';
		
		$this->app->pageTitle = $this->lang('DEVELOPER');

		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');
		
		// prevents access to instances that are not under development
		if (!$this->app->currentUser->admin) {
			$this->layout = 'accessDenied';
		}

		$route = Router::getInstance();
		$tableName = $route->getParam(0);

		$this->model->setupVariables($tableName);
		
		$form = $this->model->getModuleWizardForm();
		$form->getControl('objectName')->setValue($this->model->objectName);
		$form->getControl('moduleName')->setValue($this->model->moduleName);
		$form->getControl('tableName')->setValue($tableName);
		
		$this->assign('form', $form);

	}
	
}
