<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

/**
 * Base abstract class to manage the HTML layout layer.
 */
abstract class View {
	
	/**
	 * Application object.
	 */
	protected $app;
	
	/**
	 * Path to the file, with trailing slash.
	 * @var string
	 */
	private $scriptPath = 'layouts/';
	
	/**
	 * Content variables for layout.
	 * @var array
	 */
	private $vars = array();
	
	/**
	 * Pagination variable.
	 * @var Pagination
	 */
	private $pagination;
	
	/**
	 * View name, without “View” suffix.
	 * @var string
	 */
	private $name;
	
	/**
	 * Layout file name, default as view name set by __construct().
	 * @var string
	 */
	protected $layout;
	
	/**
	 * Path to this module view with no trailing slash.
	 * @var string
	 */
	private $modulePath;
	
	/**
	 * Translator object.
	 * @var object
	 */
	protected $translator;
	
	/**
	 * Public URL for this module with no trailing slash.
	 * @var string
	 */
	private $moduleUrl;
	
	/**
	 * Model class object.
	 * @var multitype
	 */
	protected $model;
	
	/**
	 * Constructor.
	 */
	final public function __construct() {

		// singleton objects
		$this->app		= Application::getInstance();
		$route			= Router::getInstance();
		$options		= Options::getInstance();
		$this->translator = Translator::getInstance();
		
		// sets view name and default layout
		$class = get_called_class();
		$this->name = substr($class, 0, strpos($class, 'View'));
		$this->layout = strtolower(substr($class, strpos($class, 'View') + 4, 1)) . substr($class, strpos($class, 'View') + 5);
		
		// path to module folder
		$ref = new \ReflectionClass($this);
		$this->modulePath = dirname($ref->getFileName());
		
		// url to the module
		$this->moduleUrl = 'modules/' . strtolower($this->name); // BASE_HREF .
		
		// pagination
		$this->pagination			= new Pagination();
		$this->pagination->perPage	= $options->getValue('pagination_pages');
		$this->pagination->page		= $route->page;
		
		// includes and instance default model
		include_once ($this->modulePath .'/model.php');
		$modelName = $this->name . 'Model';
		$this->model = new $modelName();

		$this->model->pagination = $this->pagination;
		
		// sets language subfolder’s name
		$this->translator->module = $this->name;
		
		// sets the default menu item -- can be overwritten if needed
		$this->app->activeMenuItem = $route->module . '/' . $route->action;
		
	}
	
	/**
	 * Formats page layout including variables and returns.
	 * 
	 * @param	string	Layout file name without extension (.php).
	 * 
	 * @return	string
	 */
	final public function display($name=NULL) {

		$this->render();
		
		// look for css files
		if (is_dir($this->modulePath . '/css')) {
			
			// get all folder files
			$files = Utilities::getDirectoryFilenames($this->modulePath . '/css');
			
			// load files as script and add timestamp to ignore browser caching
			foreach ($files as $file) {
				$fullPath = $this->moduleUrl . '/css/' . $file;
				$this->app->loadCss($fullPath . '?' . filemtime($fullPath));
			}
			
		}
		
		// look for javascript files
		if (is_dir($this->modulePath . '/js')) {
				
			// get all folder files
			$files = Utilities::getDirectoryFilenames($this->modulePath . '/js');
				
			// load files as script and add timestamp to ignore browser caching
			foreach ($files as $file) {
				$fullPath = $this->moduleUrl . '/js/' . $file;
				$this->app->loadJs($fullPath . '?' . filemtime($fullPath));
			}
				
		}
		
		if (!$name) {
			$name = $this->layout;
		}

		$file = $this->modulePath .'/'. $this->scriptPath . $name .'.php';

		$this->app->logEvent('Applying ' . $this->layout . ' layout');
		
		// includes layout file
		try {

			if (file_exists($file)) {
				include $file;
			} else {
				throw new \Exception('Layout file ' . $file . ' was not found');
			}
			
		} catch (\Exception $e) {
			
			$this->app->enqueueError($e->getMessage());
			
		}
		
	}
	
	/**
	 * Adds a variable-item to the object array “vars”.
	 * 
	 * @param	string	Variable-item name.
	 * @param	mixed	Variable-item value.
	 */
	public function assign($name, $val) {
		
		$this->vars[$name] = $val;
		
	}
	
	/**
	 * Restituisce, se esiste, la variabile assegnata al layout,
	 * altrimenti la proprietà del metodo, altrimenti NULL.
	 * 
	 * @param	string	Nome della proprietà richiesta.
	 * @return	multitype
	 */
	public function __get($name) {
		
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
	public function __call($name, $arguments) {
	
		$backtrace = debug_backtrace();
		$this->logError('Method '. get_called_class() . $backtrace[0]['type'] . $name .'(), which doesn’t exist, has been called by '. $backtrace[0]['file'] .' on line '. $backtrace[0]['line']);
	
	}
	
	final public function setState($name, $value) {
	
		$this->app->setState($name, $value);
	
	}
	
	/**
	 * Returns the requested session state variable.
	 *
	 * @param	integer	Variable’s name.
	 * @return	multitype
	 */
	final public function getState($name) {
	
		return $this->app->getState($name);
	
	}
	
	/**
	 * Appends a text message to queue.
	 *
	 * @param	string	Message’s text.
	 * @param	string	Optional title.
	 * @param	string	Message’s type (info, error).
	 */
	public function enqueueMessage($text, $title='', $type=NULL) {
	
		$this->app->enqueueMessage($text, $title, $type);
	
	}
	
	public function enqueueError($text, $title='') {
	
		$this->app->enqueueError($text, $title);
	
	}

	/**
	 * Adds an event to framework’s logger, storing its chrono time.
	 * 
	 * @param	string	Event description.
	 * @param	string	Event type notice or error (default notice).
	 * @param	string	Optional additional text.
	 */
	public function logEvent($description, $type='notice', $subtext=NULL) {
		
		$this->app->logEvent($description, $type, $subtext);
		
	}
	
	/**
	 * AddEvent’s proxy for warning event creations.
	 *
	 * @param	string	Event description.
	 */
	public function logWarning($description) {
	
		$this->app->logWarning($description);
	
	}
	
	/**
	 * AddEvent’s proxy for error event creations.
	 *
	 * @param	string	Event description.
	 */
	public function logError($description) {
	
		$this->app->logError($description);
	
	}
	
	/**
	 * Proxy function that returns a translated string.
	 * 
	 * @param	string	The language key.
	 * @param	array	List of parameters to bind on string (optional).
	 */
	public function lang($key, $vars=NULL) {
		
		return $this->translator->translate($key, $vars);
		
	}
	
	/**
	 * Proxy function that prints a translated string.
	 *
	 * @param	string	The language key.
	 * @param	array	List of parameters to bind on string (optional).
	 */
	public function _($key, $vars=NULL) {
	
		print $this->translator->translate($key, $vars);
	
	}
	
	/**
	 * Computes data and assigns values to layout.
	 * 
	 * @return	string
	 */
	abstract function render();
	
	/**
	 * Return the HTML code of pagination bar.
	 * 
	 * @return string
	 */
	public function getPaginationBar() {
		
		if (is_null($this->pagination->count)) {
			$this->logError('The “count” parameter needed for pagination has not been set');
		}
		
		return $this->pagination->render();
		
	}
	
}
