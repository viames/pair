<?php

namespace Pair;

/**
 * Singleton object globally available for caching, queuing messages and render the template.
 */
class Application {

	/**
	 * Singleton property.
	 * @var Application|NULL
	 */
	static protected $instance;

	/**
	 * List of temporary variables.
	 * @var array:mixed
	 */
	private $state = array();

	/**
	 * List of temporary variables, stored also in the browser cookie.
	 * @var array:mixed
	 */
	private $persistentState = array();
	
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
	 * Contains a list of plain text script to add.
	 * @var array:string
	 */
	private $scriptContent = array();

	/**
	 * Contains all external script files to load.
	 * @var array:stdClass
	 */
	private $scriptFiles = array();

	/**
	 * Contains all CSS files to load.
	 * @var array:string
	 */
	private $cssFiles = array();

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
	 * List of modules that can run with no authentication required.
	 * @var string[]
	 */
	private $guestModules = [];
	
	/**
	 * Private constructor called by getInstance(). No Logger calls here.
	 */
	private function __construct() {

		// override error settings on server
		ini_set('error_reporting',	E_ALL);
		ini_set('display_errors',	TRUE);

		// prevent loop error for recursive __construct
		if (defined('APPLICATION_PATH')) {
			return;
		}
		
		// application folder without trailing slash
		define('APPLICATION_PATH',	dirname(dirname(dirname(dirname(dirname(__FILE__))))));
		
		// Pair folder
		define('PAIR_FOLDER', substr(dirname(__FILE__), strlen(APPLICATION_PATH)+1));
		
		$config = APPLICATION_PATH . '/config.php';
		
		// check config file or start installation
		if (!file_exists($config)) {
			if (file_exists('installer/start.php')) {
				include 'installer/start.php';
			} else {
				die ('Configuration file is missing.');
			}
			exit();
		}
		
		// load configuration constants
		require $config;
		
		// default constants
		$defaults = array(
			'AUTH_SOURCE'		=> 'internal',
			'BASE_URI'			=> '',
			'DBMS'				=> 'mysql',
			'PRODUCT_NAME'		=> 'NewProduct',
			'PRODUCT_VERSION'	=> '1.0',
			'UTC_DATE'			=> TRUE
		);
		
		// set default constants in case of missing
		foreach ($defaults as $key=>$val) {
			if (!defined($key)) {
				define($key, $val);
			}
		}
		
		// force php server date to UTC
		if (defined('UTC_DATE') and UTC_DATE) {
			ini_set('date.timezone', 'UTC');
			define('BASE_TIMEZONE', 'UTC');
		} else {
			$tz = date_default_timezone_get();
			define('BASE_TIMEZONE', ($tz ? $tz : 'UTC'));
		}
		
		// define full URL to web page index with trailing slash or NULL
		$protocol = ($_SERVER['SERVER_PORT'] == 443 or (isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] !== 'off')) ? "https://" : "http://";
		$baseHref = isset($_SERVER['HTTP_HOST']) ? $protocol . $_SERVER['HTTP_HOST'] . BASE_URI . '/' : NULL;
		define('BASE_HREF', $baseHref);
		
		// error management
		set_error_handler('\Pair\Utilities::customErrorHandler');
		register_shutdown_function('\Pair\Utilities::fatalErrorHandler');
		
		// routing initialization
		$router = Router::getInstance();
		$router->parseRoutes();
		
		// force utf8mb4
		if (defined('DB_UTF8') and DB_UTF8) {
			$db = Database::getInstance();
			$db->setUtf8unicode();
		}
		
		// default page title, maybe overwritten
		$this->pageTitle = PRODUCT_NAME;

		// raw calls will jump templates inclusion, so turn-out output buffer
		if (!$router->isRaw()) {
			
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

			/**
			 * for login page we need a default template
			 * @deprecated
			 */
			case 'templatePath':
			
				$value = $this->getTemplate()->getPath();
				break;
				
			// useful in html tag to set language code
			case 'langCode':
				
				$translator = Translator::getInstance();
				$value = $translator->getCurrentLocale()->getRepresentation();
				break;
				
			default:
				
				$allowedProperties = ['activeMenuItem', 'currentUser', 'pageTitle', 'pageContent', 'template'];
				
				// search into variable assigned to the template as first
				if (array_key_exists($name, $this->vars)) {
			
					$value = $this->vars[$name];
				
				// then search in properties
				} else if (property_exists($this, $name) and in_array($name, $allowedProperties)) {
					
					$value = $this->$name;
				
				// then return NULL
				} else {
					
					$this->logError('Property “'. $name .'” doesn’t exist for this object '. get_called_class());
					$value = NULL;
					
				}
				break;
				
		}
		
		return $value;
	
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
	 * Sets current user, default template and translation locale.
	 * 
	 * @param	User	User object or inherited class object. 
	 */
	protected function setCurrentUser($user) {
		
		if (is_a($user,'Pair\User')) {

			$this->currentUser = $user;
			
			// sets user language
			$tran = Translator::getInstance();
			$tran->setLocale($user->getLocale());
				
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
	 * @param	string	Name of the state variable.
	 * @param	string	Value of any type as is, like strings, custom objects etc.
	 */
	public function setState($name, $value) {
		
		$this->state[$name] = $value;
		
	}

	/**
	 * Deletes a session state variable.
	 *
	 * @param	string	Name of the state variable.
	 */
	public function unsetState($name) {
	
		unset($this->state[$name]);
	
	}
	
	/**
	 * Returns the requested session state variable.
	 * 
	 * @param	string	State’s name.
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
	
	/**
	 * Returns TRUE if state has been previously set, NULL value included.
	 *
	 * @param	string	Name of the state variable.
	 *
	 * @return	bool
	 */
	final public function issetState($name) {
		
		return (array_key_exists($name, $this->state));
		
	}
	
	/**
	 * Add script content that will be loaded by jQuery into the #scriptContainer DOM element.
	 * 
	 * @param	string	Javascript content.
	 */
	public function addScript($script) {
		
		$this->scriptContent[] = $script;
		
	}

	/**
	 * Set esternal script file load with optional attributes.
	 * 
	 * @param	string	Path to script, absolute or relative with no trailing slash.
	 * @param	bool	Defer attribute (default FALSE).
	 * @param	bool	Async attribute (default FALSE).
	 * @param	array	Optional attribute list (type, integrity, crossorigin, charset).
	 */
	public function loadScript($src, $defer = FALSE, $async = FALSE, $attribs=[]) {
		
		// force casting to array
		$attribs = (array)$attribs;
		
		// the script object
		$script = new \stdClass();
		
		$script->src = $src;
		
		if ($defer)	$script->defer = TRUE;
		if ($async) $script->async = TRUE;

		// list of valid type attributes
		$validTypes = ['text/javascript','text/ecmascript','application/javascript','application/ecmascript'];
		
		if (isset($attribs['type']) and in_array($attribs['type'], $validTypes)) {
			$script->type = $attribs['type'];
		}

		if (isset($attribs['integrity']) and strlen(trim($attribs['integrity']))) {
			$script->integrity = $attribs['integrity'];
		}
		
		if (isset($attribs['crossorigin']) and strlen(trim($attribs['crossorigin']))) {
			$script->crossorigin = $attribs['crossorigin'];
		}
		
		if (isset($attribs['charset']) and strlen(trim($attribs['charset']))) {
			$script->charset = $attribs['charset'];
		}
		
		$this->scriptFiles[] = $script;
		
	}

	/**
	 * Useful to collect CSS file list and render tags into page head.
	 * 
	 * @param	string	Path to stylesheet, absolute or relative with no trailing slash.
	 */
	public function loadCss($href) {
	
		$this->cssFiles[] = $href;
	
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
	 * Redirect HTTP on the URL param. Relative path as default. Queued messages
	 * get a persistent storage in a cookie in order to being retrieved later.
	 *
	 * @param	string	Location URL.
	 * @param	bool	If TRUE, will avoids to add base url (default FALSE).
	 */
	public function redirect($url, $externalUrl=FALSE) {
	
		// stores enqueued messages for next retrievement
		$this->makeQueuedMessagesPersistent();
		
		if (!$url) return;
		
		if ($externalUrl) {
			
			header('Location: ' . $url);
			
		} else {
			
			$router = Router::getInstance();
			$page  = $router->getPage();
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
	 * Store enqueued messages for next retrievement.
	 */
	public function makeQueuedMessagesPersistent() {
		
		$this->setPersistentState('EnqueuedMessages', $this->messages);
		
	}

	/**
	 * Manage API login, logout and custom requests.
	 * 
	 * @param	string	Name of module that executes API requests.
	 */
	public function runApi($name) {

		$router = Router::getInstance();

		// check if API has been called
		if (!trim($name) or $name != $router->module or !file_exists(MODULE_PATH . 'controller.php')) {
			return;
		}
		
		// set as raw request
		Router::setRaw();
		
		$logger = Logger::getInstance();
		$logger->disable();

		// require controller file
		require (MODULE_PATH . 'controller.php');
		
		// reveal SID by both GET and POST
		$sid = Input::get('sid');

		$ctlName = $name . 'Controller';
		$apiCtl = new $ctlName();

		// set the action function
		$action = $router->action ? $router->action . 'Action' : 'defaultAction';

		// login and logout
		if ('login' == $router->action or 'logout' == $router->action) {
			
			// start the PHP session
			session_start();
			
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
	
	/**
	 * Add the name of a module to the list of guest modules, for which authorization is not required.
	 * 
	 * @param	string	Module name.
	 */
	public function setGuestModule($moduleName) {
		
		if (!in_array($moduleName, $this->guestModules)) {
			$this->guestModules[] = $moduleName;
		}
		
	}

	/**
	 * Start the session and set the User class (Pair/User or a custom one that inherites
	 * from Pair/User).
	 * 
	 * @param	string	Custom user class (optional).
	 */
	public function manageSession($userClass = 'Pair\User') {
	
		// get required singleton instances
		$logger	 = Logger::getInstance();
		$options = Options::getInstance();
		$router	 = Router::getInstance();
		$tran	 = Translator::getInstance();
		
		// start session or resume session started by runApi
		session_start();
		
		// session time length in minutes
		$sessionTime = $options->getValue('session_time');
		
		if (in_array($router->module, $this->guestModules)) {
			return;
		}
		
		// get existing previous session
		$session = new Session(session_id());
		
		// session exists but expired
		if ($session->isLoaded() and $session->isExpired($sessionTime)) {
		
			$comment = $tran->get('USER_SESSION_EXPIRED');
		
			// sends js message about session expired
			if ($router->isRaw()) {
		
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
		$this->setCurrentUser(new $userClass());
		
		// user is not logged in
		if (!$session->isLoaded()) {
		
			if (isset($_SERVER['HTTP_REFERER'])) {
				$this->setState('referer', $_SERVER['HTTP_REFERER']);
			}
		
			// redirect to login page
			if (!('user'==$router->module and 'login'==$router->action)) {
				$this->redirect('user/login');
			}
		
		} else {
		
			// if session exists, extend session timeout
			$session->extendTimeout();
		
			// create User object
			$user = new $userClass($session->idUser);
			$this->setCurrentUser($user);
		
			$logger->addEvent('User session for ' . $user->fullName . ' is alive' .
					', user time zone is ' . $this->currentUser->tzName .
					' (' . sprintf('%+06.2f', (float)$this->currentUser->tzOffset) . ')');

			// set defaults in case of no module
			if (NULL == $router->module) {
				$landing = $user->getLanding();
				$router->module = $landing->module;
				$router->action = $landing->action;
			}

			$resource = $router->module . '/' . $router->action;
		
			// checking permission
			if ($this->currentUser->canAccess($router->module, $router->action)) {
		
				// access granted
				$logger->addEvent('Access granted on resource ' . $resource);
		
			} else {
		
				// access denied
				$this->enqueueError($tran->get('ACCESS_FORBIDDEN', $resource));
				$this->redirect($router->defaults['module'] . '/' . $router->defaults['action']);
		
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
		
		$name = static::getCookiePrefix() . ucfirst($name);
		
		$this->persistentState[$name] = $value;
				
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
	
		$name = static::getCookiePrefix() . ucfirst($name);
		
		if (array_key_exists($name, $this->persistentState)) {
			return $this->persistentState[$name];
		} else if (isset($_COOKIE[$name])) {
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
		
		$name = static::getCookiePrefix() . ucfirst($name);
		
		unset($this->persistentState[$name]);
		
		if (isset($_COOKIE[$name])) {
			unset($_COOKIE[$name]);
			setcookie($name, '', -1, '/');
		}
		
	}

	/**
	 * Removes all state variables from cookies.
	 */
	public function unsetAllPersistentStates() {
	
		$prefix = static::getCookiePrefix();

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
	public static function getCookiePrefix() {
		
		return str_replace(' ', '', ucwords(str_replace('_', ' ', PRODUCT_NAME)));
		
	}
	
	/**
	 * Parse template file, replace variables and return it.
	 *
	 * @throws \Exception
	 */
	final public function startMvc() {
		
		$router	= Router::getInstance();
		$tran	= Translator::getInstance();
		
		// make sure to have a template set
		$template = $this->getTemplate();
		
		$controllerFile = 'modules/' . $router->module . '/controller.php';
		
		// check controller file existence
		if (!file_exists($controllerFile) or '404' == $router->url) {
			
			$this->enqueueError($tran->get('RESOURCE_NOT_FOUND', $router->url));
			$this->style = '404';
			$this->pageTitle = 'HTTP 404 error';
			
		} else {
		
			require ($controllerFile);
			
			// build controller object
			$controllerName = ucfirst($router->module) . 'Controller';
			$controller = new $controllerName();
			
			// set the action
			$action = $router->action ? $router->action . 'Action' : 'defaultAction';

			// run the action
			$controller->$action();
			
			// log some events
			$logger	= Logger::getInstance();
			
			// set log of ajax call
			if ($router->ajax) {
				
				$params = array();
				foreach ($router->vars as $key=>$value) {
					$params[] = $key . '=' . Utilities::varToText($value);
				}
				$logger->addEvent(date('Y-m-d H:i:s') . ' AJAX call on ' . $this->module . '/' . $this->action . ' with params ' . implode(', ', $params));
				
			// log controller method call
			} else {
				
				$logger->addEvent('Called controller method ' . $controllerName . '->' . $action . '()');
				
			}
			
			// raw calls will jump controller->display, ob and log
			if ($router->isRaw()) {
				return;
			}
			
			// invoke the view
			$controller->display();
			
			// set the event log
			$this->log = $logger->getEventList();
			
		}
		
		// populate the placeholder for the content
		$this->pageContent = ob_get_clean();
		
		// initialize CSS and scripts
		$this->pageStyles = '';
		$this->pageScripts = '';

		// collect stylesheets
		foreach ($this->cssFiles as $href) {
				
			$this->pageStyles .= '<link rel="stylesheet" href="' . $href . '">' . "\n";
				
		}
		
		// collect script files
		foreach ($this->scriptFiles as $script) {
			
			// initialize attributes render
			$attribsRender = '';

			// render each attribute based on its variable type (string or boolean)
			foreach ($script as $tag => $value) {
				$attribsRender .= ' ' . (is_bool($value) ? $tag : $tag . '="' . htmlspecialchars($value) . '"');
			}
			
			// add each script
			$this->pageScripts .= '<script' . $attribsRender . '></script>' . "\n";
			
		}

		// collect plain text scripts
		if (count($this->scriptContent) or count($this->messages)) {

			$this->pageScripts .= "<div id=\"scriptContainer\"><script>\n";
			$this->pageScripts .= "$(document).ready(function(){\n";
			
			foreach ($this->scriptContent as $s) {
				$this->pageScripts .= $s ."\n";
			}
			
			$this->pageScripts .= $this->getMessageScript();
			$this->pageScripts .= "});\n";
			$this->pageScripts .= "</script></div>";
			
		}
		
		try {
			$template->loadStyle($this->style);
		} catch (\Exception $e) {
			print $e->getMessage();
		}
	
		// get output buffer and cleans it
		$page = ob_get_clean();
	
		print $page;

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
				$script .= '$.showMessage("' .
					addslashes($m->title) . '","' .
					addcslashes($m->text,"\"\n\r") . '","' . // removes carriage returns and quotes
					addslashes($m->type) . "\");\n";
				
			}
			
		}

		return $script;
		
	}
	
	/**
	 * If current selected template is not valid, replace it with the default one. It’s private to avoid
	 * loops on derived templates load.
	 * 
	 * @return	Pair\Template
	 */
	final private function getTemplate() {
		
		if (!$this->template or !$this->template->isPopulated()) {
			$this->template = Template::getDefault();
		}
		
		// if this is derived template, load derived.php file
		if ($this->template->derived) {
			$derivedFile = $this->template->getBaseFolder() . '/'  . strtolower($this->template->name) . '/derived.php';
			if (file_exists($derivedFile)) require $derivedFile;
		}
		
		return $this->template;

	}

}
