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
use Pair\Router;
use Pair\Translator;

class DeveloperController extends Controller {
	
	protected function init() {
		
		$breadcrumb = Breadcrumb::getInstance();
		$breadcrumb->addPath('Developer module', 'developer');
		
	}
	
	public function classWizardAction() {
		
		$route = Router::getInstance();
		
		if (!$route->getParam(0)) {
			$this->enqueueError($this->lang('TABLENAME_WAS_NOT_SPECIFIED'));
		}
		
	}
	
	public function moduleWizardAction() {
		
		$route = Router::getInstance();
		
		if (!$route->getParam(0)) {
			$this->enqueueError($this->lang('TABLENAME_WAS_NOT_SPECIFIED'));
		}
		
	}

	public function classCreationAction() {
		
		$this->view = 'default';

		$tableName	= Input::get('tableName');
		$objectName	= Input::get('objectName');

		if ($tableName and $objectName) {
			
			$this->model->setupVariables($tableName, $objectName);
			
			$file = APPLICATION_PATH . '/classes/' . $this->model->objectName . '.php';

			if (!file_exists($file)) {

				$this->model->saveClass($file);
				$this->enqueueMessage($this->lang('CLASS_HAS_BEEN_CREATED', $this->model->objectName));
				
			} else {
				
				$this->enqueueError($this->lang('CLASS_FILE_ALREADY_EXISTS', $this->model->objectName));
				
			}
				
		} else {

			$this->enqueueError($this->lang('CLASS_HAS_NOT_BEEN_CREATED'));
								
		}
		
	}
	
	public function moduleCreationAction() {
		
		$this->view = 'default';

		$language = Translator::getInstance();
		
		$tableName	= Input::get('tableName');
		$objectName	= Input::get('objectName');
		$moduleName	= Input::get('moduleName');
			
		if ($tableName and $objectName and $moduleName) {
			
			$this->model->setupVariables($tableName, $objectName, $moduleName);
			
			$folder = APPLICATION_PATH . '/modules/' . $this->model->moduleName;
				
			if (!file_exists($folder)) {
				
				// module folders
				$folders = array(
					$folder,
					$folder . '/classes/',
					$folder . '/languages/',
					$folder . '/layouts/');
				
				foreach ($folders as $f) {
					$old = umask(0);
					mkdir($f, 0777, TRUE);
					umask($old);
				}
				
				// object class file
				$this->model->saveClass($folder . '/classes/' . $this->model->objectName . '.php');
				
				// languages
				$this->model->saveLanguage($folder . '/languages/' . $language->default . '.ini');
				
				// controller
				$this->model->saveController($folder . '/controller.php');
				
				// model
				$this->model->saveModel($folder . '/model.php');
				
				// view default
				$this->model->saveViewDefault($folder . '/viewDefault.php');

				// view new
				$this->model->saveViewNew($folder . '/viewNew.php');
				
				// view edit
				$this->model->saveViewEdit($folder . '/viewEdit.php');
				
				// layout default
				$this->model->saveLayoutDefault($folder . '/layouts/default.php');

				// layout new
				$this->model->saveLayoutNew($folder . '/layouts/new.php');
				
				// layout edit
				$this->model->saveLayoutEdit($folder . '/layouts/edit.php');
				
				$this->enqueueMessage($this->lang('MODULE_HAS_BEEN_CREATED', $this->model->moduleName));
	
			} else {
	
				$this->enqueueError($this->lang('MODULE_FOLDER_ALREADY_EXISTS', $folder));
	
			}
			
			// creates plugin object
			$modPlugin					= new Module();
			$modPlugin->name			= $this->model->moduleName;
			$modPlugin->version			= '1.0';
			$modPlugin->dateReleased	= date('Y-m-d H:i:s');
			$modPlugin->appVersion		= PRODUCT_VERSION;
			$modPlugin->installedBy		= $this->app->currentUser->id;
			$modPlugin->dateInstalled	= date('Y-m-d H:i:s');
			$modPlugin->create();
			
			// creates manifest file
			$plugin = $modPlugin->getPlugin();
			$plugin->createManifestFile();
	
		} else {
	
			$this->enqueueError($this->lang('MODULE_HAS_NOT_BEEN_CREATED'));
	
		}
	
	}
	
}
