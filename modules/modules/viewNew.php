<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Breadcrumb;
use Pair\View;
use Pair\Widget;

class ModulesViewNew extends View {

	public function render() {

		$breadcrumb = Breadcrumb::getInstance();
		$breadcrumb->addPath('Nuovo modulo', 'modules/new');
		
		$this->app->pageTitle		= $this->lang('MODULES');
		$this->app->activeMenuItem	= 'modules/default';

		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');
		
		$form = $this->model->getModuleForm();
		$this->assign('form', $form);

	}
	
}
