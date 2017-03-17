<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

/**
 * Singleton object globally available for caching, queuing messages and render the template.
 */
class Application {

	/**
	 * Framework version.
	 * @var string
	 */
	const VERSION = '1.0';

	/**
	 * Framework build.
	 * @var string
	 */
	const BUILD = '1557';

	/**
	 * Framework date of last change.
	 * @var string
	 */
	const DATE = '2016-11-30 09:33:01Z';

	/**
	 * Singleton property.
	 * @var Application|NULL
	 */
	static private $instance;

	/**
	 * List of temporary variables.
	 * @var array:mixed
	 */
	private $state = array();

	/**
	 * Web page title, in plain text.
	 * @var string
	 */
	private $pageTitle = '';

	/**
	 * HTML content of web page.
	 * @var string
	 */
	private $pageContent = '';

	/**
	 * Contains a list of plain text javascript to add.
	 * @var array:string
	 */
	private $scriptList = array();

	/**
	 * Contains all JS files to load.
	 * @var array:string
	 */
	private $jsList = array();

	/**
	 * Contains all CSS files to load.
	 * @var array:string
	 */
	private $cssList = array();

	/**
	 * Message list.
	 * @var array:stdClass
	 */
	private $messages = array();

	/**
	 * Currently connected user.
	 * @var User
	 */
	private $currentUser;

	/**
	 * Contents variables for layouts.
	 * @var array
	 */
	private $vars = array();

	/**
	 * URL of the active menu item.
	 * @var string
	 */
	private $activeMenuItem;

	/**
	 * Template’s object.
	 * @var NULL|Template
	 */
	private $template;

	/**
	 * Template-style’s file name (without extension).
	 * @var string
	 */
	private $style = 'default';

	/**
	 * Private constructor called by getInstance().
	 */
	private function __construct() {

		// override error settings on server
		ini_set('error_reporting',	E_ALL);
		ini_set('display_errors',	TRUE);

		// application folder without trailing slash
		define('APPLICATION_PATH',	dirname(dirname(dirname(__FILE__))));
		
		$config = APPLICATION_PATH . '/config.php';

		// check configuration file
		if (file_exists($config)) {

			// load configuration constants
			require $config;
		
			// default constants
			$defaults = array(
				'AUTH_SOURCE'	=> 'internal',
				'UTC_DATE'		=> TRUE,
				'PRODUCT_NAME'	=> 'NewProduct',
				'BASE_URI'		=> '/',
				'DBMS'			=> 'mysql'
			);
	
			// set default constants in case of missing
			foreach ($defaults as $key=>$val) {
				if (!defined($key)) {
					define($key, $val);
				}
			}

		} else {

			// config file doesn’t exist, start install
			require APPLICATION_PATH . '/install.php';
			
		}

		// force php server date to UTC
		if (defined('UTC_DATE') and UTC_DATE) {
			ini_set('date.timezone', 'UTC');
			define('BASE_TIMEZONE', 'UTC');
		} else {
			define('BASE_TIMEZONE', ini_get('date.timezone'));
		}
		
		// define full URL to web page index with trailing slash or NULL
		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
		$baseHref = isset($_SERVER['HTTP_HOST']) ? $protocol . $_SERVER['HTTP_HOST'] . BASE_URI . '/' : NULL;
		define('BASE_HREF', $baseHref);
		
		// error management
		set_error_handler('\Pair\Utilities::customErrorHandler');
		register_shutdown_function('\Pair\Utilities::fatalErrorHandler');
		
		// FIXME
		// routing initialization
		$route = Router::getInstance();
		//$route->setDefaults('users', 'default');
		
		// default page title, will be overwritten
		$this->pageTitle = PRODUCT_NAME;

		// raw calls will jump templates inclusion, so turn-out output buffer
		if (!$route->isRaw()) {
			
			$debug = (defined('DEBUG') and DEBUG);
			$gzip  = (isset($_SERVER['HTTP_ACCEPT_ENCODING']) and substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip'));
			
			// if supported, output is compressed with gzip
			if (!$debug and $gzip) {
				ob_start('ob_gzhandler'); 
			} else {
				ob_start();
			}

		}
		
		// retrieve all cookie messages and puts in queue
		$persistentMsg = $this->getPersistentState('EnqueuedMessages');
		if (is_array($persistentMsg)) {
			$this->messages = $persistentMsg;
		}
		$this->unsetPersistentState('EnqueuedMessages');
		
	}
	
	/**
	 * Returns, if any, variable assigned to the template,
	 * otherwise the properties of the method, otherwise NULL
	 *
	 * @param	string	Requested property’s name.
	 * @return	multitype
	 */
	public function __get($name) {
	
		switch ($name) {
			
			case 'templatePath':
			
				// for login page we need a default template
				$this->checkTemplate();
				return 'templates/' . $this->template->name . '/';
				break;
				
			// useful in html tag to set language code
			case 'langCode':
				$language = new Language($this->currentUser->languageId);
				return $language->code;
				break;
				
			default:
				
				// search into variable assigned to the template as first
				if (array_key_exists($name, $this->vars)) {
			
					return $this->vars[$name];
				
				// then search in properties
				} else if (property_exists($this, $name)) {
					
					return $this->$name;
				
				// then return NULL
				} else {
					
					$this->logError('Property “'. $name .'” doesn’t exist for this object '. get_called_class());
					return NULL;
					
				}
				break;
				
		}
	
	}
	
	/**
	 * Magic method to set an object property value.
	 *
	 * @param	string	Property’s name.
	 * @param	mixed	Property’s value.
	 */
	public function __set($name, $value) {

		if (property_exists($this, $name)) {
			
			// object properties
			$this->$name = $value;
			
		} else {
			
			// layout’s variables
			$this->vars[$name] = $value;
			
		}
	
	}
	
	/**
	 * Sets current user, default template and translation language.
	 * 
	 * @param	User	User object or inherited class object. 
	 */
	public function setCurrentUser($user) {
		
		if (is_a($user,'Pair\User')) {

			$this->currentUser = $user;
			$this->checkTemplate();
			
			// sets user language
			$tran = Translator::getInstance();
			$lang = new Language($user->languageId);
			$tran->setLanguage($lang);
				
		}

	}
	
	/**
	 * Create singleton Application object and return it.
	 * 
	 * @return Application
	 */
	public static function getInstance() {

		// could be this class or inherited
		$class = get_called_class();

		if (is_null(static::$instance)) {
			static::$instance = new $class();
		}

		return static::$instance;

	}

	/**
	 * Sets a session state variable.
	 * 
	 * @param	string	Variable’s name.
	 * @param	string	Variable’s value of any type as is, like strings, custom objects etc.
	 */
	public function setState($name, $value) {
		
		$this->state[$name] = $value;
		
	}

	/**
	 * Deletes a session state variable.
	 *
	 * @param	string	Variable’s name.
	 */
	public function unsetState($name) {
	
		unset($this->state[$name]);
	
	}
	
	/**
	 * Returns the requested session state variable.
	 * 
	 * @param	string	Variable’s name.
	 * 
	 * @return	multitype|NULL
	 */
	final public function getState($name) {
		
		if (array_key_exists($name, $this->state)) {
			return $this->state[$name];
		} else {
			return NULL;
		}
		
	}
	
	public function addScript($script) {
		
		$this->scriptList[] = $script;
		
	}
	
	/**
	 * Adds a Javascript file or library to the page head.
	 * 
	 * @param	string	Path to script, absolute or relative with no trailing slash.
	 */
	public function loadJs($file) {
		
		$this->jsList[] = $file;
		
	}

	/**
	 * Adds a CSS file or library to the page head.
	 * 
	 * @param	string	Path to stylesheet, absolute or relative with no trailing slash.
	 */
	public function loadCss($file) {
	
		$this->cssList[] = $file;
	
	}
	
	/**
	 * Appends a text message to queue.
	 * 
	 * @param	string	Message’s text.
	 * @param	string	Optional title.
	 * @param	string	Message’s type (info, error).
	 */
	public function enqueueMessage($text, $title='', $type=NULL) {
	
		$message		= new \stdClass();
		$message->text	= $text;
		$message->title	= ($title ? $title : 'Info');
		$message->type	= ($type  ? $type  : 'info');
	
		$this->messages[] = $message;
	
	}
	
	/**
	 * Proxy function to append an error message to queue.
	 * 
	 * @param	string	Message’s text.
	 * @param	string	Optional title.
	 */
	public function enqueueError($text, $title='') {
	
		$this->enqueueMessage($text, ($title ? $title : 'Error'), 'error');
		
	}
	
	/**
	 * Adds an event to framework’s logger, storing its chrono time.
	 * 
	 * @param	string	Event description.
	 * @param	string	Event type notice, query, api, warning or error (default is notice).
	 * @param	string	Optional additional text.
	 */
	public function logEvent($description, $type='notice', $subtext=NULL) {
		
		$logger = Logger::getInstance();
		$logger->addEvent($description, $type, $subtext);
		
	}
	
	/**
	 * AddEvent’s proxy for warning event creations.
	 *
	 * @param	string	Event description.
	 */
	public function logWarning($description) {
	
		$logger = Logger::getInstance();
		$logger->addWarning($description);
	
	}
	
	/**
	 * AddEvent’s proxy for error event creations.
	 *
	 * @param	string	Event description.
	 */
	public function logError($description) {
	
		$logger = Logger::getInstance();
		$logger->addError($description);
	
	}
	
	/**
	 * Redirect HTTP on the URL param. Relative path as default.
	 *
	 * @param	string	Location URL.
	 * @param	bool	If TRUE, will avoids to add base url (default FALSE).
	 */
	public function redirect($url, $externalUrl=FALSE) {
	
		// stores enqueued messages for next retrievement
		$this->setPersistentState('EnqueuedMessages', $this->messages);
		
		if (!$url) return;
		
		if ($externalUrl) {
			
			header('Location: ' . $url);
			
		} else {
			
			$route = Router::getInstance();
			$page  = $route->getPage();
			if ($page > 1) {
				if ('/'==$url{0}) $url = substr($url,1); // removes slashes
				header('Location: ' . BASE_HREF . $url . '/page-' . $page);
			} else {
				header('Location: ' . BASE_HREF . $url);
			}
				
		}
	
		exit();

	}

	/**
	 * Manage API login, logout and custom requests.
	 * 
	 * @param	string	Name of module that executes API requests.
	 */
	public function runApi($name) {

		$route = Router::getInstance();

		// check if API has been called
		if (!trim($name) or $name != $route->module or !file_exists('modules/' . $name . '/controller.php')) {
			return;
		}

		// define module constant and require controller file
		define('MODULE_PATH', 'modules/' . $name . '/');
		require ('modules/' . $name . '/controller.php');
		
		$sid = $route->getParam('sid');

		$ctlName = $name . 'Controller';
		$apiCtl = new $ctlName();

		// set the action function
		$action = $route->action ? $route->action . 'Action' : 'defaultAction';

		// login and logout
		if ('login' == $route->action or 'logout' == $route->action) {

			// start controller
			$apiCtl->$action();

		// all the other requests with sid
		} else if ($sid) {

			// get passed session
			$session = new Session($sid);

			// check if sid is valid
			if (!$session->isLoaded()) {
				$apiCtl->sendError('Session is not valid');
			}

			// if session exists, extend session timeout
			$session->extendTimeout();

			// create User object for API
			$user = new User($session->idUser);
			$this->setCurrentUser($user);

			// start controller
			$apiCtl->$action();

		// unauthorized request
		} else {

			$apiCtl->sendError('Request is not valid');

		}

		exit();

	}

	public function manageSession() {
	
		// get required singleton instances
		$logger	 = Logger::getInstance();
		$options = Options::getInstance();
		$route	 = Router::getInstance();
		$tran	 = Translator::getInstance();
		
		// start global PHP session
		session_start();
		
		// session time length in minutes
		$sessionTime = $options->getValue('session_time');
		
		// get existing previous session
		$session = new Session(session_id());
		
		// session exists but expired
		if ($session->isLoaded() and $session->isExpired($sessionTime)) {
		
			$comment = $tran->translate('USER_SESSION_EXPIRED');
		
			// sends js message about session expired
			if ($route->isRaw()) {
		
				Utilities::printJsonError($comment);
				exit();
		
			// redirects to login page
			} else {
		
				// new empty session
				$session = new Session();
		
				// page coming from
				if (array_key_exists('HTTP_REFERER',$_SERVER)) {
					$this->setState('referer', $_SERVER['HTTP_REFERER']);
					$logger->addEvent('Referer: ' . $_SERVER['HTTP_REFERER']);
				}
		
				// message to user
				$this->enqueueMessage($comment);
		
			}
		
		}
	
		// clean all old sessions
		Session::cleanOlderThan($sessionTime);
		
		// sets an empty user object
		$this->setCurrentUser(new User());
		
		// user is not logged in
		if (!$session->isLoaded()) {
		
			if (isset($_SERVER['HTTP_REFERER'])) {
				$this->setState('referer', $_SERVER['HTTP_REFERER']);
			}
		
			// redirect to login page
			if (!('user'==$route->module and 'login'==$route->action)) {
				$this->redirect('user/login');
			}
		
		} else {
		
			// if session exists, extend session timeout
			$session->extendTimeout();
		
			// create User object
			$user = new User($session->idUser);
			$this->setCurrentUser($user);
		
			$logger->addEvent('User session for ' . $user->fullName . ' is alive' .
					', user time zone is ' . $this->currentUser->tzName .
					' (' . sprintf('%+06.2f', (float)$this->currentUser->tzOffset) . ')');
		
			$resource = $route->module . '/' . $route->action;
		
			// checking permission
			if ($this->currentUser->canAccess($route->module, $route->action)) {
		
				// access granted
				$logger->addEvent('Access granted on resource ' . $resource);
		
			} else {
		
				// access denied
				$this->enqueueError($tran->translate('ACCESS_FORBIDDEN', $resource));
				$this->redirect($route->defaults['module'] . '/' . $route->defaults['action']);
		
			}
		
		}
		
	}

	/**
	 * Store variables of any type in a cookie for next retrievement. Existent variables with
	 * same name will be overwritten.
	 * 
	 * @param	string	State name.
	 * @param	mixed	State value (any variable type).
	 */
	public function setPersistentState($name, $value) {
		
		$name = $this->getCookiePrefix() . ucfirst($name);
				
		setcookie($name, json_encode($value), NULL, '/');
		
	}

	/**
	 * Retrieves variables of any type form a cookie named like in param.
	 * 
	 * @param	string	State name.
	 * 
	 * @return	mixed
	 */
	public function getPersistentState($name) {
	
		$name = $this->getCookiePrefix() . ucfirst($name);
		
		if (isset($_COOKIE[$name])) {
			return json_decode($_COOKIE[$name]);
		} else {
			return NULL;
		}
		
	}
	
	/**
	 * Removes a state variable from cookie.
	 * 
	 * @param	string	State name.
	 */
	public function unsetPersistentState($name) {
		
		$name = $this->getCookiePrefix() . ucfirst($name);
		
		if (isset($_COOKIE[$name])) {
			unset($_COOKIE[$name]);
			setcookie($name, '', -1, '/');
		}
		
	}

	/**
	 * Removes all state variables from cookies.
	 */
	public function unsetAllPersistentStates() {
	
		$prefix = $this->getCookiePrefix();

		foreach ($_COOKIE as $name=>$content) {
			if (0 == strpos($name, $prefix)) {
				$this->unsetPersistentState($name);
			}
		}
		
	}
	
	/**
	 * Return a cookie prefix based on product name, like ProductName.
	 * 
	 * @return	string
	 */
	public function getCookiePrefix() {
		
		return str_replace(' ', '', ucwords(str_replace('_', ' ', PRODUCT_NAME)));
		
	}
	
	/**
	 * Parse template file, replace variables and return it.
	 *
	 * @throws \Exception
	 */
	final public function startMvc() {
		
		$route	= Router::getInstance();
		$tran	= Translator::getInstance();
		
		$controllerFile = 'modules/' . $route->module . '/controller.php';
		
		// check controller file existence
		if (!file_exists($controllerFile) or '404' == $route->url) {
			
			$this->enqueueError($tran->translate('RESOURCE_NOT_FOUND', $route->url));
			$this->style = '404';
			$this->pageTitle = 'HTTP 404 error';
			
		} else {
		
			// path to the required form
			define('MODULE_PATH', 'modules/'. $route->module .'/');
			
			require ($controllerFile);
			
			// build controller object
			$controllerName = ucfirst($route->module) . 'Controller';
			$controller = new $controllerName();

			// set the action
			$action = $route->action ? $route->action . 'Action' : 'defaultAction';

			// run the action
			$controller->$action();
			
			// log the event
			$logger	= Logger::getInstance();
			$logger->addEvent('Called controller method ' . $controllerName . '->' . $action . '()');
			
			// raw calls will jump controller->display, ob and log
			if ($route->isRaw()) {
				return;
			}
			
			// invoke the view
			$controller->display();
			
			// set the event log
			$this->log = $logger->getEventList();
			
		}
		
		// populate the placeholder for the content
		$this->pageContent = ob_get_clean();
		
		// login page has no template, needs a default
		$this->checkTemplate();
		
		$templatesPath = APPLICATION_PATH . '/templates/' ;

		// by default load template style
		$styleFile = $templatesPath . $this->template->name . '/' . $this->style . '.php';

		// in case of derived template, try to load style from default template
		if ($this->template->derived) {

			// if no template style, load default template style 
			if (!file_exists($styleFile)) {
				$defaultTemplate = Template::getDefault();
				$styleFile = $templatesPath . $defaultTemplate->name . '/' . $this->style . '.php';
			}

			// try to load derived extend file
			$derivedFile = $templatesPath . $this->template->name . '/derived.php';
			if (file_exists($derivedFile)) require $derivedFile;

		}
				
		// initialize CSS and JS
		$this->pageStyles = '';
		$this->pageScripts = '';

		// collect stylesheets
		foreach ($this->cssList as $css) {
				
			$this->pageStyles .= '<link rel="stylesheet" href="' . $css . '">' . "\n";
				
		}
		
		// collect script files
		foreach ($this->jsList as $js) {
			
			$this->pageScripts .= '<script src="' . htmlspecialchars($js) . '" type="text/javascript"></script>' . "\n";
			
		}

		// collect plain text scripts
		if (count($this->scriptList) or count($this->messages)) {

			$this->pageScripts .= "<div id=\"scriptContainer\"><script>\n";
			$this->pageScripts .= "$(document).ready(function(){\n";
			
			foreach ($this->scriptList as $s) {
				$this->pageScripts .= $s ."\n";
			}
			
			$this->pageScripts .= $this->getMessageScript();
			$this->pageScripts .= "});\n";
			$this->pageScripts .= "</script></div>";
			
		}
		
		try {
	
			if (!file_exists($styleFile)) {
				throw new \Exception('Template style file ' . $styleFile . ' was not found');
			}
			
			// load the style page file
			require $styleFile;
		
			// get output buffer and cleans it
			$page = ob_get_clean();
		
			print $page;
	
		} catch (\Exception $e) {
				
			print $e->getMessage();
				
		}
	
	}
	
	/**
	 * Returns javascript code for displaying a front-end user message.
	 * 
	 * @return string
	 */
	final private function getMessageScript() {
		
		$script = '';
		
		if (count($this->messages)) {

			foreach ($this->messages as $m) {
		
				$types = array('info', 'warning', 'error');
				if (!in_array($m->type, $types)) $m->type = 'info';
				$script .= '$("#notificationArea").prependMessageBox("'.
					addslashes($m->title) .'","' .
					addcslashes($m->text,"\"\n\r") . '","' . // removes carriage returns and quotes
					addslashes($m->type) ."\");\n";
			}
			
		}

		return $script;
		
	}
	
	final private function checkTemplate() {

		if (!$this->template or !$this->template->isPopulated()) {
			$this->template = Template::getDefault();
		}

	}

}
