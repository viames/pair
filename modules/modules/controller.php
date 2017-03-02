<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Breadcrumb;
use Pair\Controller;
use Pair\Input;
use Pair\Module;
use Pair\Plugin;
use Pair\Router;
use Pair\Upload;

class ModulesController extends Controller {

	/**
	 * This function being invoked before everything here.
	 * @see Controller::init()
	 */
	protected function init() {
		
		// removes files older than 30 minutes
		Plugin::removeOldFiles();
		
		$breadcrumb = Breadcrumb::getInstance();
		$breadcrumb->addPath('Moduli', 'modules/default');
		
	}
	
	public function newAction() {
	
		$this->view = 'new';
	
	}
	
	public function downloadAction() {
		
		$route		= Router::getInstance();
		$moduleId	= $route->getParam(0);
		
		$module	= new Module($moduleId);
		$plugin		= $module->getPlugin();
		
		$plugin->downloadPackage();
		
	}
	
	public function addAction() {
		
		$this->view = 'default';
		
		if ('add'==Input::get('action')) {
				
			// collects file infos
			$upload = new Upload($_FILES['package']);
	
			// checks for common upload errors
			if ($upload->getLastError()) {
				$this->logError($this->lang('ERROR_ON_UPLOADED_FILE'));
				return;
			} else if ('zip'!=$upload->type) {
				$this->logError($this->lang('UPLOADED_FILE_IS_NOT_ZIP'));
				return;
			} 
			
			// saves the file on /temp folder
			$upload->save(APPLICATION_PATH . '/' . Plugin::TEMP_FOLDER);
	
			// installs the package
			$plugin = new Plugin();
			$res = $plugin->installPackage($upload->path . $upload->filename);
			
			if ($res) {
				$this->enqueueMessage($this->lang('MODULE_HAS_BEEN_INSTALLED_SUCCESFULLY'));
			} else {
				$this->enqueueError($this->lang('MODULE_HAS_NOT_BEEN_INSTALLED'));
			}
			
		}
		
	}
	
	public function deleteAction() {
		
		$route = Router::getInstance();

		$module = new Module($route->getParam(0));
		
		if ($module->delete()) {
			$this->enqueueMessage($this->lang('MODULE_HAS_BEEN_REMOVED_SUCCESFULLY'));
		} else {
			$this->enqueueError($this->lang('MODULE_HAS_NOT_BEEN_REMOVED'));
		}

		$this->redirect('modules/default');

	}
	
}
