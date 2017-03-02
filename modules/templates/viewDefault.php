<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Options;
use Pair\View;
use Pair\Widget;

class TemplatesViewDefault extends View {

	/**
	 * {@inheritdoc}
	 * 
	 * @see View::Render()
	 */
	public function render() {

		$options = Options::getInstance();

		$this->app->pageTitle		= $this->lang('TEMPLATES');
		$this->app->activeMenuItem	= 'templates/default';

		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');
		
		$templates = $this->model->getTemplates();
		
		// if development mode is switched on, hide the delete button
		$devMode = ($options->getValue('development') and $this->app->currentUser->admin) ? TRUE : FALSE;
		
		foreach ($templates as $template) {

			// check if plugin is compatible with current application version
			$template->compatible = (version_compare(PRODUCT_VERSION, $template->appVersion) <= 0) ?
				'<span class="fa fa-check-square-o"></span>' :
				'<div style="color:red">v' . $template->appVersion . '</div>';
			
			$template->defaultIcon = $template->default ? '<span class="fa fa-star"></span>' : '';
			
			$template->derivedIcon = $template->derived ? '<span class="fa fa-check-square-o"></span>' : '';

			$template->downloadIcon = '<a class="btn btn-default btn-xs" href="templates/download/'. $template->id .'">'.
					'<span class="fa fa-lg fa-download"></span></a>';

			if ($devMode) {
				$template->deleteIcon = $template->default ? '' : '<a href="templates/delete/'. $template->id .'" class="confirmDelete">'.
					'<span class="fa fa-lg fa-times"></span></a>';
			} else {
				$template->deleteIcon = '<span class="fa fa-lg fa-times disabled"></span>';
			}
			
		}
		
		$this->assign('templates', $templates);
		
	}
	
}
