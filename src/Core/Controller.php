<?php

namespace Pair\Core;

use Pair\Exceptions\AppException;
use Pair\Exceptions\CriticalException;
use Pair\Exceptions\ErrorCodes;
use Pair\Helpers\Translator;
use Pair\Helpers\Utilities;
use Pair\Orm\ActiveRecord;

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
	 * View object.
	 */
	protected ?View $view = NULL;

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

	/**
	 * Inizialize name, module path, translator and view.
	 */
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

		// if a model is not specified, load the default one
		if (!isset($this->model) or is_null($this->model)) {
			include ($this->modulePath .'/model.php');
			$modelName = $this->name . 'Model';
			$this->model = new $modelName();
		}

		$this->_init();

		// children classes could have not set a view, so we set a default one
		if (!$this->app->headless and (!isset($this->view) or is_null($this->view))) {
			$this->setView($this->router->action ?: 'default');
		}

	}

	/**
	 * Returns property’s value or NULL.
	 *
	 * @param	string	Property’s name.
	 * @throws	\Exception	If property doesn’t exist.
	 */
	public function __get(string $name): mixed {

		if (!property_exists($this, $name)) {
			throw new \Exception('Property “'. $name .'” doesn’t exist for '. get_called_class(), ErrorCodes::PROPERTY_NOT_FOUND);
		}

		return isset($this->$name) ? $this->$name : NULL;

	}

	public function __set(string $name, mixed $value): void {

		$this->$name = $value;

	}

	/**
	 * Start function, being executed before each method. Optionally implemented by inherited classes.
	 */
	protected function _init(): void {}

	/**
	 * Print a toast notification and redirect to default action.
	 *
	 * @param	string	Optional message to enqueue.
	 */
	protected function accessDenied(?string $message=NULL): void {

		$this->toastRedirect(Translator::do('ERROR'), ($message ?: Translator::do('ACCESS_DENIED')), strtolower($this->name));

	}

	/**
	 * Returns the object of inherited class when called with id as first parameter.
	 *
	 * @param	string	Expected object class type.
	 *
	 * @throws	AppException
	 */
	protected function getObjectRequestedById(string $class): ?ActiveRecord {

		// reads from url requested item id
		$itemId = Router::get(0);

		if (!$itemId) {
			throw new AppException(Translator::do('ID_OF_ITEM_TO_EDIT_IS_NOT_VALID', $class));
		}

		$object = new $class($itemId);

		if (!$object->isLoaded()) {
			throw new AppException(Translator::do('ID_OF_ITEM_TO_EDIT_IS_NOT_VALID', $class));
		}

		return $object;

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
	 * Get the View object related to this controller.
	 */
	final public function getView(): ?View {

		return $this->view;

	}

	/**
	 * Proxy function to translate a string, used for AJAX return messages.
	 *
	 * @param	string	The language key.
	 * @param	string|array|NULL	Parameter or list of parameters to bind on translation string (optional).
	 */
	public function lang(string $key, string|array|NULL $vars=NULL): string {

		return Translator::do($key, $vars);

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
	 * Load a view from the file system and return View object related to this controller.
	 *
	 * @throws CriticalException If view is not set.
	 * @throws AppException If view file or class is not found.
	 */
	private function loadView(string $viewName): View {

		if (!$viewName) {
			throw new CriticalException('View page not set for module ' . $this->name, ErrorCodes::CONTROLLER_CONFIG_ERROR);
		}

		$file = $this->modulePath .'/view'. ucfirst($viewName) .'.php';

		if (!file_exists($file)) {
			$viewName = 'default';
			$file = $this->modulePath .'/view'. ucfirst($viewName) .'.php';
		}

		// if view file still not found, throw an exception
		if (!file_exists($file)) {
			throw new AppException('The page ' . strtolower($this->name) . '/' . $viewName . ' does not exist', ErrorCodes::VIEW_LOAD_ERROR);
		}

		include_once($file);

		$className = ucfirst($this->name) .'View'. ucfirst($viewName);

		if (!class_exists($className)) {
			throw new AppException('Class ' . $className . ' was not found in file ' . $file, ErrorCodes::VIEW_LOAD_ERROR);
		}

		return new $className($this->model);

	}

	/**
	 * Get error list from an ActiveRecord object and show it to the user.
	 *
	 * @param	ActiveRecord	The inherited object.
	 * @throws	\Exception
	 */
	protected function raiseError(ActiveRecord $object): void {

		// get error list from the ActiveRecord object
		$errors = $object->getErrors();

		// choose the error messages
		$message = $errors
			? implode(" \n", $errors)
			: Translator::do('ERROR_ON_LAST_REQUEST');

		// after the message has been queued, store the error data
		$logger = Logger::getInstance();
		$logger->error('Failure in {objectClass} class', ['objectClass' => \get_class($object)]);

		// enqueue a toast notification to the UI
		throw new \Exception($message);

	}

	/**
	 * Shortcut to HTTP redirect and show a toast notification error.
	 */
	public function redirectWithError(string $message, ?string $url=NULL): void {

		$this->toastError($message);
		$this->redirect($url);

	}

	/**
	 * Include the file for View formatting. Display an error with a notification and
	 * redirect to default view as fallback in case of view not found for non-ajax requests.
	 */
	public function renderView(): void {

		if (!is_subclass_of($this->view, 'Pair\Core\View')) {
			throw new CriticalException('View class not found');
		}

		$this->view->display();

	}

	/**
	 * Set a view name and load the related View object.
	 *
	 * @param	string	The view name.
	 */
	public function setView(string $viewName): void {

		$viewClassName = ucfirst($this->name) .'View'. ucfirst($viewName);

		if (!isset($this->view) or get_class($this->view) !== $viewClassName) {
			$this->view = $this->loadView($viewName);
		}

	}

}