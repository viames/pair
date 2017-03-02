<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Breadcrumb;
use Pair\Form;
use Pair\Language;
use Pair\Router;
use Pair\View;
use Pair\Widget;

class LanguagesViewEdit extends View {

	/**
	 * {@inheritdoc}
	 *
	 * @see View::Render()
	 */
	public function render() {

		$this->app->pageTitle		= $this->lang('LANGUAGES');
		$this->app->activeMenuItem	= 'languages/default';
		
		// get requested Language object
		$route		= Router::getInstance();
		$language	= new Language($route->getParam(0));
		$module		= $route->getParam(1);

		// add breadcrumb path
		Breadcrumb::getInstance()
			->addPath($this->lang('LANGUAGE_X', $language->languageName), 'languages/details/' . $language->id)
			->addPath($this->lang('MODULE_X', ucfirst($module)));
		
		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');

		if (!$language->default) {

			$defLang = Language::getDefault();
			
			// get strings
			$strings	= $language->getStrings($module);
			$defStrings	= $defLang->getStrings($module);

			$form = new Form();
			$form->addControlClass('form-control');
			
			$form->addInput('l')->setType('hidden')->setValue($language->id);
			$form->addInput('m')->setType('hidden')->setValue($module);
			
			foreach ($defStrings as $key=>$value) {
			
				$control = $form->addInput($key);
				
				if (isset($strings[$key])) {
					$control->setValue($strings[$key]);
				}
				
			}
			
			$referer = 'languages/details/' . $language->id . '/' . $module;
			
			$this->assign('form', $form);
			$this->assign('referer', $referer);
			$this->assign('defStrings', $defStrings);

		} else {

			// TODO default language cannot be edited, needs to generate an error page
			$this->layout = 'error';

		}
		
		$this->assign('module',		$module);
		$this->assign('language',	$language);

	}

}