<?php

namespace Pair\Core;

use Pair\Exceptions\PairException;
use Pair\Helpers\LogBar;
use Pair\Helpers\Options;
use Pair\Helpers\Translator;
use Pair\Helpers\Utilities;
use Pair\Html\Pagination;
use Pair\Orm\ActiveRecord;

/**
 * Base abstract class to manage the HTML layout layer.
 */
abstract class View {

	use \Pair\Traits\AppTrait;
	use \Pair\Traits\LogTrait;

	/**
	 * Application object.
	 */
	protected Application $app;

	/**
	 * Path to the file, with trailing slash.
	 */
	private string $scriptPath = 'layouts/';

	/**
	 * Content variables for layout.
	 */
	private array $vars = [];

	/**
	 * Pagination variable.
	 */
	private Pagination $pagination;

	/**
	 * View name, without “View” suffix.
	 */
	private string $name;

	/**
	 * Layout file name, default as view name set by __construct().
	 */
	protected string $layout;

	/**
	 * Path to this module view with no trailing slash.
	 */
	private string $modulePath;

	/**
	 * Translator object.
	 */
	protected Translator $translator;

	/**
	 * Public URL for this module with no trailing slash.
	 */
	private string $moduleUrl;

	/**
	 * Model class object.
	 */
	protected Model $model;

	/**
	 * Constructor.
	 */
	final public function __construct(Model $model) {

		// singleton objects
		$this->app = Application::getInstance();
		$router	= Router::getInstance();
		$this->translator = Translator::getInstance();

		// sets view name and default layout
		$class = get_called_class();
		$this->name = substr($class, 0, strpos($class, 'View'));
		$this->layout = strtolower(substr($class, strpos($class, 'View') + 4, 1)) . substr($class, strpos($class, 'View') + 5);

		// path to module folder
		$ref = new \ReflectionClass($this);
		$this->modulePath = dirname($ref->getFileName());

		// url to the module
		$this->moduleUrl = 'modules/' . strtolower($this->name);

		// pagination
		$this->pagination			= new Pagination();
		$this->pagination->perPage	= Options::get('pagination_pages');
		$this->pagination->page		= $router->getPage();

		// copy the model object
		$this->model = $model;
		$this->model->pagination = $this->pagination;

		// sets the default menu item -- can be overwritten if needed
		$this->app->activeMenuItem = $router->module;

		try {
			$this->init();
		} catch (PairException $e) {
			$this->logError('View initialization error: ' . $e->getMessage());
		}

	}

	/**
	 * Adds a variable-item to the object array “vars”.
	 *
	 * @param	string	Variable-item name.
	 * @param	mixed	Variable-item value.
	 */
	public function assign($name, $val): void {

		$this->vars[$name] = $val;

	}

	/**
	 * Returns, if it exists, the variable assigned to the layout,
	 * otherwise the property of the method, otherwise NULL.
	 *
	 * @param	string	Nome della proprietà richiesta.
	 */
	public function __get($name): mixed {

		if (array_key_exists($name, $this->vars)) {
			return $this->vars[$name];
		} else if (property_exists($this, $name)) {
			return $this->$name;
		} else {
			$this->logError('The ' . get_called_class() . '->' . $name. ' property doesn’t exist; Null will be returned');
			return NULL;
		}

	}

	/**
	 * Management of unknown view’s function.
	 *
	 * @param	string	$name
	 * @param	array	$arguments
	 */
	public function __call($name, $arguments): void {

		$backtrace = debug_backtrace();
		$this->logError('Method '. get_called_class() . $backtrace[0]['type'] . $name .'(), which doesn’t exist, has been called by '. $backtrace[0]['file'] .' on line '. $backtrace[0]['line']);

	}

	/**
	 * Formats page layout including variables and returns.
	 *
	 * @param	string	Layout file name without extension (.php).
	 */
	final public function display(?string $name=NULL): void {

		$this->render();

		// look for css files
		if (is_dir($this->modulePath . '/css')) {

			// get all folder files
			$files = Utilities::getDirectoryFilenames($this->modulePath . '/css');

			// load files as script and add timestamp to ignore browser caching
			foreach ($files as $file) {
				$subPath = '/css/' . $file;
				$this->app->loadCss($this->moduleUrl . $subPath . '?' . filemtime($this->modulePath . $subPath));
			}

		}

		// look for javascript files
		if (is_dir($this->modulePath . '/js')) {

			// get all folder files
			$files = Utilities::getDirectoryFilenames($this->modulePath . '/js');

			// load files as script and add timestamp to ignore browser caching
			foreach ($files as $file) {
				$subPath = '/js/' . $file;
				$this->app->loadScript($this->moduleUrl . $subPath . '?' . filemtime($this->modulePath . $subPath));
			}

		}

		if (!$name) {
			$name = $this->layout;
		}

		$file = $this->modulePath .'/'. $this->scriptPath . $name .'.php';

		if (!file_exists($file)) {
			throw new PairException('Layout “' . $name . '” was not found');
		}

		// includes layout file
		include $file;

	}

	/**
	 * Start function, being executed before each method. Optional.
	 */
	protected function init(): void {}

	/**
	 * Sets the requested session state variable.
	 */
	final public function setState($name, $value) {

		$this->app->setState($name, $value);

	}

	/**
	 * Returns the requested session state variable.
	 *
	 * @param	integer	Variable’s name.
	 */
	final public function getState($name): mixed {

		return $this->app->getState($name);

	}

	/**
	 * Proxy function that returns a translated string.
	 *
	 * @param	string	The language key.
	 * @param	string|array|null	List of parameters to bind on string (optional).
	 * @param	bool	Show a warning if the key is not found.
	 */
	public function lang(string $key, string|array|NULL $vars=NULL, bool $warning=TRUE): string {

		return Translator::do($key, $vars, $warning);

	}

	/**
	 * Proxy function that prints a translated string.
	 *
	 * @param	string	The language key.
	 * @param	array	List of parameters to bind on string (optional).
	 */
	public function _($key, $vars=NULL) {

		print Translator::do($key, $vars);

	}

	/**
	 * Return an A-Z list with link for build an alpha filter.
	 * @param	string	Current selected list item, if any.
	 */
	public function getAlphaFilter(?string $selected=NULL): \Generator {

		$router = Router::getInstance();

		foreach (range('A', 'Z') as $a) {

			$filter = new \stdClass();
			$filter->href	= $router->module . '/' . $router->action . '/' . strtolower($a);
			$filter->text	= $a;
			$filter->active	= ($selected and strtolower((string)$a) == strtolower((string)$selected));

			yield $filter;

		}

	}

	/**
	 * Returns the object of inherited class when called with id as first parameter.
	 *
	 * @param	string	Expected object class type.
	 */
	protected function getObjectRequestedById(string $class, ?int $pos=NULL): ?ActiveRecord {

		// reads from url requested item id
		$itemId = Router::get($pos ? abs($pos) : 0);

		if (!$itemId) {
			throw new PairException($this->lang('NO_ID_OF_ITEM_TO_EDIT', $class));
		}

		$object = new $class($itemId);

		if (!$object->isLoaded()) {
			throw new PairException($this->lang('ID_OF_ITEM_TO_EDIT_IS_NOT_VALID', $class));
		}

		return $object;

	}

	/**
	 * Return the HTML code of pagination bar.
	 */
	public function getPaginationBar(): string {

		if (is_null($this->pagination->count)) {
			LogBar::error('The “count” parameter needed for pagination has not been set');
		}

		return $this->pagination->render();

	}

	/**
	 * Determines whether the number of items displayed versus the number of items on the page
	 * requires the pagination bar.
	 */
	public function mustUsePagination(array $itemsToShow): bool {

		return (count($itemsToShow) >= $this->pagination->perPage or $this->pagination->page > 1);

	}

	/**
	 * Prints the alpha filter bar.
	 */
	public function printAlphaFilter(?string $selected=NULL): void {

		$router = Router::getInstance();
		$letters = $this->getAlphaFilter($selected);

		?><a href="<?php print $router->module ?>/<?php print $router->action ?>"><?php $this->_('ALL') ?></a><?php
		foreach ($letters as $letter) {
			?><a href="<?php print $letter->href ?>"<?php print ($letter->active ? ' class="active"' : '') ?>><?php print $letter->text ?></a><?php
		}

	}

	/**
	 * Computes data and assigns values to layout.
	 */
	abstract function render(): void;

	/**
	 * Prints a column header with sorting link.
	 */
	public function sortable(string $title, int $ascOrder, int $descOrder): void {

		$router = Router::getInstance();

		print '<div style="white-space:nowrap">';

		// check if the title is uppercase and translate it
		if (strtoupper($title) == $title) {
			$title = $this->lang($title);
		}

		if ($ascOrder == $router->order) {

			print '<a href="' . $router->getOrderUrl($descOrder) . '">' . $title . '</a> <i class="fa fa-arrow-up"></i>';

		} else if ($descOrder == $router->order) {

			print '<a href="' . $router->getOrderUrl(0) . '">' . $title . '</a> <i class="fa fa-arrow-down"></i>';

		} else {

			print '<a href="' . $router->getOrderUrl($ascOrder) . '">' . $title . '</a>';

		}

		print '</div>';

	}

}