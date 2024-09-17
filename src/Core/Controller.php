<?php

namespace Pair\Core;

use Pair\Models\ErrorLog;
use Pair\Orm\ActiveRecord;
use Pair\Support\Logger;
use Pair\Support\Translator;
use Pair\Support\Utilities;

abstract class Controller {

	/**
	 * Application object.
	 * @var	Application
	 */
	protected $app;

	/**
	 * Router object.
	 * @var	Router
	 */
	protected $router;

	/**
	 * Model for this MVC stack.
	 * @var mixed
	 */
	protected $model;

	/**
	 * View’s file name, without file extension.
	 * @var string
	 */
	protected $view;

	/**
	 * Translator object.
	 * @var Translator
	 */
	protected $translator;

	/**
	 * Controller’s name, without “Controller” suffix.
	 * @var string
	 */
	private $name;

	/**
	 * Path to the module for this controller.
	 * @var string
	 */
	private $modulePath;

	final public function __construct() {

		// useful singleton objects
		$this->app = Application::getInstance();
		$this->router = Router::getInstance();
		$this->translator = Translator::getInstance();

		// set controller’s name
		$class = get_called_class();
		$this->name = substr($class, 0, strpos($class, 'Controller'));

		// path to the module folder
		$ref = new \ReflectionClass($this);
		$this->modulePath = dirname($ref->getFileName());

		// sets language subfolder’s name
		$this->translator->setModuleName($this->name);

		// sets same view as the controller action
		$this->view = $this->router->action ? $this->router->action : 'default';

		$this->init();

		// if a model is not specified, load the default one
		if (!isset($this->model) or is_null($this->model)) {
			include ($this->modulePath .'/model.php');
			$modelName = $this->name . 'Model';
			$this->model = new $modelName();
		}

		// look for extended classes
		if (is_dir($this->modulePath . '/classes')) {

			// get all folder files
			$filenames = Utilities::getDirectoryFilenames($this->modulePath . '/classes');

			// include each class file
			foreach ($filenames as $filename) {
				include_once $this->modulePath . '/classes/' . $filename;
			}

		}

	}

	/**
	 * Start function, being executed before each method. Optional.
	 */
	protected function init() {}

	/**
	 * Empty function, could be overloaded.
	 */
	public function defaultAction() {}

	public function __get($name) {

		if ('route' == $name) {
			Logger::warning('$this->route is deprecated');
			return $this->router;
		}

		try {
			if (!isset($this->$name)) {
				throw new \Exception('Property “'. $name .'” doesn’t exist for this object '. get_called_class());
			}
			return $this->$name;
		} catch(\Exception $e) {
			return NULL;
		}

	}

	public function __set($name, $value) {

		try {
			$this->$name = $value;
		} catch(\Exception $e) {
			print $e->getMessage();
		}

	}

	/**
	 * Notices developer about a call to unexistent method.
	 *
	 * @param	string	Method name.
	 * @param	array	Method arguments.
	 */
	public function __call($name, $arguments) {

		// do nothing

	}

	/**
	 * Proxy to set a variable within global scope.
	 *
	 * @param	string	Variable name.
	 *
	 * @return	mixed	Any variable type.
	 */
	final public function setState($name, $value) {

		$this->app->setState($name, $value);

	}

	/**
	 * Proxy to unset a state variable.
	 *
	 * @param	string	Variable name.
	 */
	final public function unsetState($name) {

		$this->app->unsetState($name);

	}

	/**
	 * Proxy to get a variable within global scope.
	 *
	 * @param	string	Variable name.
	 */
	final public function getState($name) {

		return $this->app->getState($name);

	}

	/**
	 * Proxy to append a text message to queue.
	 *
	 * @param	string	Message’s text.
	 * @param	string	Optional title.
	 * @param	string	Message’s type (info, error).
	 */
	final public function enqueueMessage($text, $title='', $type=NULL) {

		$this->app->enqueueMessage($text, $title, $type);

	}

	/**
	 * Proxy to queue an error message.
	 *
	 * @param	string	Message’s text.
	 * @param	string	Optional title.
	 */
	final public function enqueueError($text, $title='') {

		$this->app->enqueueError($text, $title);

	}

	/**
	 * Load a custom model.
	 */
	public function loadModel(string $modelName): void {

		if (!file_exists($this->modulePath .'/'. $modelName .'.php')) {
			throw new \Exception('Model file '. $this->modulePath .'/'. $modelName .'.php has not been found');
		}

		include ($this->modulePath .'/'. $modelName .'.php');
		$modelClass = $this->name . ucfirst($modelName);
		$this->model = new $modelClass();

	}

	/**
	 * Load a custom model for a list of actions.
	 */
	public function loadModelForActions(string $modelName, array $actions): void {

		if (in_array($this->router->action, $actions)) {
			$this->loadModel($modelName);
		}

	}

	/**
	 * Proxy to redirect HTTP on the URL param. Relative path as default.
	 *
	 * @param	string	Location URL.
	 * @param	bool	If TRUE, will avoids to add base url (default FALSE).
	 */
	public function redirect(string $url, bool $absoluteUrl=FALSE): void {

		$this->app->redirect($url, $absoluteUrl);

	}

	/**
	 * Return View object related to this controller.
	 * @throws Exception
	 */
	public function getView(): ?View {

		try {

			if ($this->view) {

				$file = $this->modulePath .'/view'. ucfirst($this->view) .'.php';

				if (!file_exists($file)) {
					if ($this->app->currentUser->areKeysPopulated()) {
						throw new \Exception('View file '. $file .' has not been found');
					} else {
						die('Access denied');
					}
				}

				include_once($file);

				$viewName = ucfirst($this->name) .'View'. ucfirst($this->view);

				if (!class_exists($viewName)) {
					throw new \Exception('Class ' . $viewName . ' was not found in file ' . $file);
				}

				return new $viewName($this->model);

			} else {

				throw new \Exception('No view file has been set');

			}

		} catch (\Exception $e) {

			// set the error in the log
			Logger::error($e->getMessage());

			// get a fall-back referer or default and redirect the user
			$url = $_SERVER['REFERER'] ?? BASE_HREF;
			$this->redirect((string)$url, TRUE);

		}

		return NULL;

	}

	/**
	 * Include the file for View formatting. Display an error message and
	 * redirect to default view as fallback in case of view not found for non-ajax requests.
	 */
	public function display() {

		try {
			$view = $this->getView();
		} catch (\Exception $e) {
			$this->enqueueError($e->getMessage());
			$this->redirect($this->router->module);
		}

		if (is_subclass_of($view, 'Pair\Core\View')) {
			$view->display();
		} else {
			if (!$this->router->isRaw()) {
				$this->enqueueError(Translator::do('RESOURCE_NOT_FOUND', $this->router->module . '/' . $this->router->action));
			}
			$this->redirect($this->router->module);
		}

	}

	/**
	 * Proxy function to translate a string, used for AJAX return messages.
	 *
	 * @param	string	The language key.
	 * @param	string|array|NULL	Parameter or list of parameters to bind on translation string (optional).
	 */
	public function lang($key, mixed $vars=NULL) {

		return Translator::do($key, (array)$vars);

	}

	/**
	 * Returns the object of inherited class when called with id as first parameter.
	 *
	 * @param	string	Expected object class type.
	 * @return	object|NULL
	 */
	protected function getObjectRequestedById(string $class) {

		// reads from url requested item id
		$itemId = Router::get(0);

		if (!$itemId) {
			$this->enqueueError($this->lang('NO_ID_OF_ITEM_TO_EDIT', $class));
			return NULL;
		}

		$object = new $class($itemId);

		if ($object->isLoaded()) {

			return $object;

		} else {

			$this->enqueueError($this->lang('ID_OF_ITEM_TO_EDIT_IS_NOT_VALID', $class));
			Logger::error('Object ' . $class . ' id=' . $itemId . ' has not been loaded');
			return NULL;

		}

	}

	/**
	 * Get error list from an ActiveRecord object and show it to the user.
	 *
	 * @param	ActiveRecord	The inherited object.
	 */
	protected function raiseError(ActiveRecord $object) {

		// get error list from the ActiveRecord object
		$errors = $object->getErrors();

		// choose the error messages
		$message = $errors
			? implode(" \n", $errors)
			: $this->lang('ERROR_ON_LAST_REQUEST');

		// enqueue error message for UI
		$this->enqueueError($message);
		$this->view = 'default';

		// after the message has been queued, store the error data
		ErrorLog::keepSnapshot('Failure in ' . \get_class($object) . ' class');

	}

	/**
	 * Print an error message and redirect to default action.
	 *
	 * @param	string	Optional message to enqueue.
	 */
	protected function accessDenied(?string $message=NULL) {

		$this->enqueueError(($message ? $message : Translator::do('ACCESS_DENIED')));
		$this->redirect(strtolower($this->name));

	}

}
