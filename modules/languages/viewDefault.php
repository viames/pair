<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\View;
use Pair\Widget;

class LanguagesViewDefault extends View {

	/**
	 * {@inheritdoc}
	 *
	 * @see View::Render()
	 */
	public function render() {
		
		$this->app->pageTitle		= $this->lang('LANGUAGES');
		$this->app->activeMenuItem	= 'languages/default';
		
		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');

		// all registered languages
		$languages = $this->model->getActiveRecordObjects('Pair\Language', 'code');

		// adds translated line count and percentage
		$this->model->setLanguagePercentage($languages);
		
		foreach ($languages as $language) {
			
			LanguagesModel::setProgressBar($language);
			
			$language->defaultIcon = $language->default ? '<i class="fa fa-lg fa-check-square-o"></i>' : NULL;
			
		}
		
		$this->assign('languages', $languages);
		
	}
	
}