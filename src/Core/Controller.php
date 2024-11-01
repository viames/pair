<?php

namespace Pair\Core;

use Pair\Exceptions\ControllerException;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Models\ErrorLog;
use Pair\Orm\ActiveRecord;
use Pair\Helpers\Translator;
use Pair\Helpers\Utilities;

abstract class Controller {

	use \Pair\Traits\AppTrait;
	use \Pair\Traits\LogTrait;

	/**
	 * Application object.
	 */
	protected Application $app;

	/**
	 * Router object.
	 */
	protected Router $router;

	/**
	 * Model for this MVC stack.
	 */
	protected Model $model;

	/**
	 * View’s file name, without file extension.
	 */
	protected string $view;

	/**
	 * Translator object.
	 */
	protected Translator $translator;

	/**
	 * Controller’s name, without “Controller” suffix.
	 */
	private string $name;

	/**
	 * Path to the module for this controller.
	 */
	private string $modulePath;

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

		try {
			$this->init();
		} catch (ControllerException $e) {
			$this->logError('Controller initialization error: ' . $e->getMessage());
		}

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

	public function __get(string $name): mixed {

		try {
			if (!isset($this->$name)) {
				throw new PairException('Property “'. $name .'” doesn’t exist for this object '. get_called_class());
			}
			return $this->$name;
		} catch(PairException $e) {
			return NULL;
		}

	}

	public function __set(string $name, mixed $value): void {

		try {
			$this->$name = $value;
		} catch(PairException $e) {
			print $e->getMessage();
		}

	}

	/**
	 * Print a toast notification and redirect to default action.
	 *
	 * @param	string	Optional message to enqueue.
	 */
	protected function accessDenied(?string $message=NULL): void {

		$this->toastRedirect(
			($message ? $message : Translator::do('ACCESS_DENIED')),
			'',
			strtolower($this->name)
		);

	}

	/**
	 * Include the file for View formatting. Display an error with a toast notification and
	 * redirect to default view as fallback in case of view not found for non-ajax requests.
	 */
	public function display(): void {

		try {
			$view = $this->getView();
		} catch (PairException $e) {
			$this->redirectWithError($e->getMessage());
		}

		if (!is_subclass_of($view, 'Pair\Core\View')) {
			if (!$this->router->isRaw()) {
				$this->app->modal('Error', Translator::do('RESOURCE_NOT_FOUND', $this->router->module . '/' . $this->router->action))->confirm('OK');
			}
			$this->redirect();
		}

		try {

			$view->display();

		} catch (\Exception $e) {

			ErrorLog::snapshot($e->getMessage(), ErrorLog::ERROR);
			throw new PairException($e->getMessage());

		}

	}

	/**
	 * Proxy to get a variable within global scope.
	 *
	 * @param	string	Variable name.
	 */
	final public function getState(string $name): mixed {

		return $this->app->getState($name);

	}

	/**
	 * Start function, being executed before each method. Optional.
	 */
	protected function init(): void {}

	/**
	 * Load a custom model.
	 */
	public function loadModel(string $modelName): void {

		if (!file_exists($this->modulePath .'/'. $modelName .'.php')) {
			throw new PairException('Model file '. $this->modulePath .'/'. $modelName .'.php has not been found');
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
	 * Returns the object of inherited class when called with id as first parameter.
	 *
	 * @param	string	Expected object class type.
	 */
	protected function getObjectRequestedById(string $class): ?ActiveRecord {

		// reads from url requested item id
		$itemId = Router::get(0);

		if (!$itemId) {
			throw new ControllerException($this->lang('ID_OF_ITEM_TO_EDIT_IS_NOT_VALID', $class));
		}

		$object = new $class($itemId);

		if (!$object->isLoaded()) {
			throw new ControllerException($this->lang('ID_OF_ITEM_TO_EDIT_IS_NOT_VALID', $class));
		}

		return $object;

	}

	/**
	 * Return View object related to this controller.
	 * @throws ControllerException
	 */
	public function getView(): ?View {

		if (!$this->view) {
			throw new ControllerException('View page not set for module ' . $this->name);
		}

		$file = $this->modulePath .'/view'. ucfirst($this->view) .'.php';

		if (!file_exists($file)) {
			$this->view = 'default';
			$file = $this->modulePath .'/view'. ucfirst($this->view) .'.php';
		}

		if (!file_exists($file)) {
			if ($this->app->currentUser and $this->app->currentUser->areKeysPopulated()) {
				throw new ControllerException('The page ' . $this->name . '/' . $this->view . ' does not exist');
			} else {
				die('Access denied');
			}
		}

		include_once($file);

		$viewName = ucfirst($this->name) .'View'. ucfirst($this->view);

		if (!class_exists($viewName)) {
			throw new ControllerException('Class ' . $viewName . ' was not found in file ' . $file, ErrorCodes::CLASS_NOT_FOUND);
		}

		return new $viewName($this->model);

	}

	/**
	 * Proxy function to translate a string, used for AJAX return messages.
	 *
	 * @param	string	The language key.
	 * @param	string|array|NULL	Parameter or list of parameters to bind on translation string (optional).
	 */
	public function lang(string $key, string|array|NULL $vars=NULL): string {

		return Translator::do($key, (array)$vars);

	}

	/**
	 * Get error list from an ActiveRecord object and show it to the user.
	 *
	 * @param	ActiveRecord	The inherited object.
	 */
	protected function raiseError(ActiveRecord $object): void {

		// get error list from the ActiveRecord object
		$errors = $object->getErrors();

		// choose the error messages
		$message = $errors
			? implode(" \n", $errors)
			: $this->lang('ERROR_ON_LAST_REQUEST');

		// enqueue a toast notification to the UI
		throw new ControllerException($message);

		// after the message has been queued, store the error data
		ErrorLog::snapshot('Failure in ' . \get_class($object) . ' class', ErrorLog::ERROR);

	}

	/**
	 * Shortcut to HTTP redirect and show a toast notification error.
	 */
	public function redirectWithError(string $message, ?string $url=NULL): void {

		$this->toastError($message);
		$this->redirect($url);

	}

	/**
	 * Proxy to set a variable within global scope.
	 *
	 * @param	string	Variable name.
	 */
	final public function setState(string $name, mixed $value): void {

		$this->app->setState($name, $value);

	}

	/**
	 * Proxy to unset a state variable.
	 *
	 * @param	string	Variable name.
	 */
	final public function unsetState(string $name): void {

		$this->unsetState($name);

	}

}