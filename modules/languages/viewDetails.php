<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Breadcrumb;
use Pair\Language;
use Pair\Router;
use Pair\View;
use Pair\Widget;

class LanguagesViewDetails extends View {

	/**
	 * {@inheritdoc}
	 *
	 * @see View::Render()
	 */
	public function render() {
		
		$this->app->pageTitle		= $this->lang('LANGUAGES');
		$this->app->activeMenuItem	= 'languages/default';
		
		// get requested Language object
		$route = Router::getInstance();
		$language = new Language($route->getParam(0));

		// add breadcrumb path
		Breadcrumb::getInstance()->addPath($this->lang('LANGUAGE_X', $language->languageName));
				
		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');

		if (!$language->default) {

			// get details of each single translation file
			$this->model->setLanguagePercentage(array($language));

			foreach ($language->details as $module=>$detail) {

				LanguagesModel::setProgressBar($detail);
				
				if ($language->isWritable($module)) {
					$detail->editButton = '<a href="languages/edit/' . $language->id . '/' . $module . '"><i class="fa fa-pencil"></i> ' . $this->lang('EDIT') . '</a>';
				} else {
					$detail->editButton = '<div title="' . $this->lang('LANGUAGE_FILE_IS_NOT_WRITABLE') . '"><i class="fa fa-lg fa-lock"></i></div>';
				}

				$detail->dateChanged = $detail->date ? date($this->lang('DATE_FORMAT'), $detail->date) : '-';

			}

		} else {

			// TODO default language cannot be edited, needs to generate an error page
			$this->layout = 'error';

		}
		
		$this->assign('language', $language);

	}
	
}