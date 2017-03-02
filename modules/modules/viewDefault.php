<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Options;
use Pair\View;
use Pair\Widget;

class ModulesViewDefault extends View {

	/**
	 * {@inheritdoc}
	 * 
	 * @see View::Render()
	 */
	public function render() {

		$options = Options::getInstance();

		$this->app->pageTitle		= $this->lang('MODULES');
		$this->app->activeMenuItem	= 'modules/default';

		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');
		
		$modules = $this->model->getActiveRecordObjects('Pair\Module', 'name');

		// if development mode is switched on, hide the delete button
		$devMode = ($options->getValue('development') and $this->app->currentUser->admin) ? TRUE : FALSE;

		foreach ($modules as $module) {

			// check if plugin is compatible with current application version
			$module->compatible = (version_compare(PRODUCT_VERSION, $module->appVersion) <= 0) ?
				'<span class="fa fa-check-square-o"></span>' :
				'<div style="color:red">v' . $module->appVersion . '</div>';

			$module->downloadIcon = '<a href="modules/download/'. $module->id .'">'.
					'<span class="fa fa-lg fa-download"></span></a>';

			if ($devMode) {
				$module->deleteIcon = '<a href="modules/delete/'. $module->id .'" class="confirmDelete">'.
					'<span class="fa fa-lg fa-times"></span></a>';
			} else {
				$module->deleteIcon = '<span class="fa fa-lg fa-times disabled"></span>';
			}

		}

		$this->assign('modules', $modules);

	}

}
