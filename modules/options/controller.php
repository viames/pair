<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	VMS
 */

use VMS\Controller;
use VMS\Input;
use VMS\Options;

class OptionsController extends Controller {
	
	protected function init() {
		
		$this->view = 'default';
		
	}
	
	/**
	 * Saves option values.
	 */
	public function saveAction() {

		$options = Options::getInstance();
		
		foreach ($options->getAll() as $option) {
			$options->setValue($option->name, Input::get($option->name, $option->type));
		}
		
		$this->enqueueMessage($this->lang('CHANGES_SAVED'));
		
		$this->app->redirect('options/default');
		
	}
	
}
