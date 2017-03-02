<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Breadcrumb;
use Pair\Controller;
use Pair\Input;
use Pair\Language;
use Pair\Router;

class LanguagesController extends Controller {

	protected function init() {
		
		Breadcrumb::getInstance()->addPath($this->lang('LANGUAGES'), 'languages/default');
		
	}
	
	/**
	 * Do the language strings change.
	 */
	public function changeAction() {
	
		$route = Router::getInstance();

		$langId = Input::get('l', 'int');
		$module = Input::get('m');
		
		$language = new Language($langId);

		$strings = Input::getInputsByRegex('#[A-Z][A-Z_]+#');

		$res = $language->setStrings($strings, $module);

		// user messages
		if ($res) {
			$this->enqueueMessage($this->lang('LANGUAGE_STRINGS_UPDATED', array($language->languageName, ucfirst($module))));
		} else {
			$this->enqueueError($this->lang('LANGUAGE_STRINGS_NOT_UPDATED', array($language->languageName, ucfirst($module))));
		}

		$this->app->redirect('languages/details/' . $langId);
	
	}
	
}