<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Options;
use Pair\View;
use Pair\Widget;

class DeveloperViewDefault extends View {

	public function render() {

		$options = Options::getInstance();
		
		$this->app->pageTitle = $this->lang('DEVELOPER');

		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');
		
		// prevents access to instances that are not under development
		if (!$this->app->currentUser->admin) {
			$this->layout = 'accessDenied';
		}
		
		$unmanagedTables = $this->model->getUnmappedTables();
		
		$this->assign('unmanagedTables', $unmanagedTables);

	}
	
}
