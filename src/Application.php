<?php

namespace Pair;

use \Pair\Oauth\Oauth2Token;

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
	 * @var mixed[]
	 */
	private $state = [];

	/**
	 * List of temporary variables, stored also in the browser cookie.
	 * @var mixed[]
	 */
	private $persistentState = [];

	/**
	 * Multi-array cache as list of class-names with list of ActiveRecord’s objects.
	 * @var \stdClass[ActiveRecord[]]
	 */
	private $activeRecordCache = [];

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
	 * @var string[]
	 */
	private $scriptContent = [];

	/**
	 * Contains all external script files to load.
	 * @var \stdClass[]
	 */
	private $scriptFiles = [];

	/**
	 * Contains all CSS files to load.
	 * @var string[]
	 */
	private $cssFiles = [];

	/**
	 * Contains Manifest files to load.
	 * @var string[]
	 */
	private $manifestFiles = [];

	/**
	 * Message list.
	 * @var \stdClass[]
	 */
	private $messages = [];

	/**
	 * Currently connected user.
	 * @var User|NULL
	 */
	private $currentUser;

	/**
	 * Contents variables for layouts.
	 * @var array
	 */
	private $vars = [];

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
		define('APPLICATION_PATH', dirname(dirname(dirname(dirname(dirname(__FILE__))))));

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
			'PAIR_AUTH_BY_EMAIL' => TRUE,
			'BASE_URI' => '',
			'DBMS' => 'mysql',
			'DB_UTF8' => TRUE,
			'PRODUCT_NAME' => 'NewProduct',
			'PRODUCT_VERSION' => '1.0',
			'UTC_DATE' => TRUE,
			'OAUTH2_TOKEN_LIFETIME' => Oauth2Token::LIFETIME,
			'PAIR_DEVELOPMENT' => FALSE,
			'PAIR_DEBUG' => FALSE,
			'PAIR_AUDIT_PASSWORD_CHANGED' => FALSE,
			'PAIR_AUDIT_LOGIN_FAILED' => FALSE,
			'PAIR_AUDIT_LOGIN_SUCCESSFUL' => FALSE,
			'PAIR_AUDIT_LOGOUT' => FALSE,
			'PAIR_AUDIT_SESSION_EXPIRED' => FALSE,
			'PAIR_AUDIT_REMEMBER_ME_LOGIN' => FALSE,
			'PAIR_AUDIT_USER_CREATED' => FALSE,
			'PAIR_AUDIT_USER_DELETED' => FALSE,
			'PAIR_AUDIT_USER_CHANGED' => FALSE,
			'PAIR_AUDIT_PERMISSIONS_CHANGED' => FALSE,
			'S3_ACCESS_KEY_ID' => FALSE,
			'S3_SECRET_ACCESS_KEY' => FALSE,
			'S3_BUCKET_REGION' => FALSE,
			'S3_BUCKET_NAME' => FALSE,
			'SENTRY_DSN' => NULL
		);

		// set default constants in case of missing
		foreach ($defaults as $key=>$val) {
			if (!defined($key)) {
				define($key, $val);
			}
		}

		// set the default user class if not customized
		if (!defined('PAIR_USER_CLASS')) {
			define ('PAIR_USER_CLASS', 'Pair\User');
		}

		// force php server date to UTC
		if (UTC_DATE) {
			ini_set('date.timezone', 'UTC');
			define('BASE_TIMEZONE', 'UTC');
		} else {
			$tz = date_default_timezone_get();
			define('BASE_TIMEZONE', ($tz ? $tz : 'UTC'));
		}

		// base URL is NULL
		if (static::isCli()) {
			$baseHref = NULL;
		// define full URL to web page index with trailing slash or NULL
		} else {
			$protocol = ($_SERVER['SERVER_PORT'] == 443 or (isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] !== 'off')) ? "https://" : "http://";
			$baseHref = isset($_SERVER['HTTP_HOST']) ? $protocol . $_SERVER['HTTP_HOST'] . BASE_URI . '/' : NULL;
		}
		define('BASE_HREF', $baseHref);

		if (SENTRY_DSN) {
			\Sentry\init([
				'dsn' => SENTRY_DSN,
				'environment' => (PAIR_DEVELOPMENT ? 'development' : 'production')
			]);
			Logger::event('Sentry activated');
		};

		// error management
		set_error_handler('\Pair\Utilities::customErrorHandler');
		register_shutdown_function('\Pair\Utilities::fatalErrorHandler');

		// routing initialization
		$router = Router::getInstance();
		$router->parseRoutes();

		// force utf8mb4
		if (DB_UTF8) {
			$db = Database::getInstance();
			$db->setUtf8unicode();
		}

		// default page title, maybe overwritten
		$this->pageTitle = PRODUCT_NAME;

		// raw calls will jump templates inclusion, so turn-out output buffer
		if (!$router->isRaw()) {

			$gzip  = (isset($_SERVER['HTTP_ACCEPT_ENCODING']) and substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip'));

			// if supported, output is compressed with gzip
			if (!PAIR_DEBUG and $gzip and extension_loaded('zlib') and !ini_get('zlib.output_compression')) {
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
	 * @return	mixed
	 */
	public function __get(string $name) {

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

				$allowedProperties = ['activeRecordCache', 'activeMenuItem', 'currentUser', 'pageTitle', 'pageContent', 'template', 'messages'];

				// search into variable assigned to the template as first
				if (array_key_exists($name, $this->vars)) {

					$value = $this->vars[$name];

				// then search in properties
				} else if (property_exists($this, $name) and in_array($name, $allowedProperties)) {

					$value = $this->$name;

				// then return NULL
				} else {

					Logger::error('Property “'. $name .'” doesn’t exist for this object '. get_called_class());
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
	public function __set(string $name, $value) {

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
	public function setCurrentUser(User $user) {

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
	 * @return	mixed|NULL
	 */
	final public function getState(string $name) {

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
	 * Return an ActiveRecord object cached or NULL if not found.
	 *
	 * @param	string		Name of the ActiveObject class.
	 * @param	int|string	Unique identifier of the class.
	 * @return	ActiveRecord|NULL
	 */
	final public function getActiveRecordCache(string $class, $id): ?ActiveRecord {

		return (isset($this->activeRecordCache[$class][$id])
			? $this->activeRecordCache[$class][$id]
			: NULL);

	}

	/**
	 * Store an ActiveRecord object into the common cache of Application singleton.
	 *
	 * @param	string			Name of the ActiveObject class.
	 * @param	ActiveRecord	Object to cache.
	 * @return	void
	 */
	final public function putActiveRecordCache(string $class, $object): void {

		// can’t manage composite key
		if (1 == count((array)$object->keyProperties) ) {
			$this->activeRecordCache[$class][(string)$object->getId()] = $object;
			Logger::event('Stored ' . get_class($object) . ' object with id=' . (string)$object->getId() . ' in common cache');
		}

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
	 * Collect Manifest files and includes into the page head.
	 *
	 * @param	string	Path to manifest file, absolute or relative with no trailing slash.
	 */
	public function loadManifest(string $href) {

		$this->manifestFiles[] = $href;

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
	 * @deprecated		Use static method Logger::event() instead.
	 */
	public function logEvent($description, $type='notice', $subtext=NULL) {

		Logger::event($description, $type, $subtext);

	}

	/**
	 * AddEvent’s proxy for warning event creations.
	 *
	 * @param	string	Event description.
	 * @deprecated		Use static method Logger::warning() instead.
	 */
	public function logWarning($description) {

		Logger::warning($description);

	}

	/**
	 * AddEvent’s proxy for error event creations.
	 *
	 * @param	string	Event description.
	 * @deprecated		Use static method Logger::error() instead.
	 */
	public function logError($description) {

		Logger::error($description);

	}

	/**
	 * Redirect HTTP on the URL param. Relative path as default. Queued messages
	 * get a persistent storage in a cookie in order to being retrieved later.
	 *
	 * @param	string	Location URL.
	 * @param	bool	If TRUE, will avoids to add base url (default FALSE).
	 */
	public function redirect(string $url, bool $externalUrl=FALSE) {

		// stores enqueued messages for next retrievement
		$this->makeQueuedMessagesPersistent();

		if (!$url) return;

		// external redirect
		if ($externalUrl) {

			header('Location: ' . $url);

		// redirect to internal path
		} else {

			$router = Router::getInstance();
			$page  = $router->getPage();

			if ($page > 1) {

				// removes a possible leading slash
				if ('/'==$url[0]) {
					$url = substr($url,1);
				}

				// if url contains just module, create a fake action placeholder
				if (FALSE == strpos($url, '/')) {
					$url .= '/';
				}

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
	 * @param	string	Name of module that executes API requests. Default is “api”.
	 */
	public function runApi(string $name = 'api') {

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

		// get SID or token via GET
		$sid		= Router::get('sid');
		$tokenValue	= Router::get('token');

		// read the Bearer token via HTTP header
		$bearerToken = Oauth2Token::readBearerToken();

		// assemble the API controller name
		$ctlName = $name . 'Controller';

		if (!class_exists($ctlName)) {
			print ('The API Controller class is incorrect');
			exit();
		}

		// new API Controller instance
		$apiCtl = new $ctlName();

		// set the action function
		$action = $router->action ? $router->action . 'Action' : 'defaultAction';

		// check token as first
		if ($tokenValue) {

			$token = Token::getByValue($tokenValue);

			// set token and start controller
			if ($token) {
				$token->updateLastUse();
				$apiCtl->setToken($token);
				$apiCtl->$action();
			} else {
				$apiCtl->sendError(19);
			}

		// or check for Oauth2 Bearer token via http header
		} else if ($bearerToken) {

			if (!Oauth2Token::validate($bearerToken)) {
				sleep(3);
				Oauth2Token::unauthorized('Authentication failed');
			}

			// verify that the bearer token is valid
			$apiCtl->setBearerToken($bearerToken);
			$apiCtl->$action();

		} else if ('login' == $router->action) {

			unset($_COOKIE[session_name()]);
			session_destroy();
			session_start();

			// user controller
			$apiCtl->$action();

		} else if ('logout' == $router->action) {

			session_start();

			// user controller
			$apiCtl->$action();

		// signup
		} else if ('signup' == $router->action) {

			// user controller
			$apiCtl->$action();

		// all the other requests with sid
		} else if ($sid) {

			// get passed session
			$session = new Session($sid);

			// check if sid is valid
			if (!$session->isLoaded()) {
				$apiCtl->sendError(27);
			}

			// if session exists, extend session timeout
			$session->extendTimeout();

			// create User object for API
			$userClass = PAIR_USER_CLASS;
			$user = new $userClass($session->idUser);
			$this->setCurrentUser($user);

			// set session and start controller
			$apiCtl->setSession($session);
			$apiCtl->$action();

		// unauthorized request
		} else {

			Oauth2Token::unauthorized(PRODUCT_NAME . '-API: Authentication failed');

		}

		exit();

	}

	/**
	 * Add the name of a module to the list of guest modules, for which authorization is not required.
	 *
	 * @param	string	Module name.
	 */
	public function setGuestModule(string $moduleName) {

		if (!in_array($moduleName, $this->guestModules)) {
			$this->guestModules[] = $moduleName;
		}

	}

	/**
	 * Start the session and set the User class (Pair/User or a custom one that inherites
	 * from Pair/User). Must use only for command-line and web application access.
	 */
	public function manageSession() {

		// can be customized before Application is initialized
		$userClass = PAIR_USER_CLASS;

		// get required singleton instances
		$router = Router::getInstance();

		// start session or resume session started by runApi
		session_start();

		// session time length in minutes
		$sessionTime = Options::get('session_time');

		// stop processing if it is a CLI or guest module
		if (static::isCli() or in_array($router->module, $this->guestModules)) {
			return;
		}

		// get existing previous session
		$session = new Session(session_id());

		// clean all old sessions
		Session::cleanOlderThan($sessionTime);

		// sets an empty user object
		$this->setCurrentUser(new $userClass());

		// session exists
		if ($session->isLoaded()) {

			// session is expired
			if ($session->isExpired($sessionTime)) {

				Audit::sessionExpired($session);

				// check RememberMe cookie
				if (User::loginByRememberMe()) {
					Audit::rememberMeLogin();
					return;
				}

				$comment = Translator::do('USER_SESSION_EXPIRED');

				// sends js message about session expired
				if ($router->isRaw()) {

					Utilities::printJsonError($comment);
					exit();

				// redirects to login page
				} else {

					// delete the expired session from DB
					$session->delete();

					// set the page coming from avoiding post requests
					if (isset($_SERVER['REQUEST_METHOD']) and 'POST' !== $_SERVER['REQUEST_METHOD']) {
						$this->setPersistentState('lastRequestedUrl', $router->getUrl());
					}

					// queue a message for the connected user
					$this->enqueueMessage($comment);

					// goes to login page
					$this->redirect('user/login');

				}

			// session loaded
			} else {

				// if session exists, extend session timeout
				$session->extendTimeout();

				// create User object
				$user = new $userClass($session->idUser);
				$this->setCurrentUser($user);

				$eventMessage = 'User session for ' . $user->fullName . ' is alive' .
					', user time zone is ' . $this->currentUser->tzName .
					' (' . sprintf('%+06.2f', (float)$this->currentUser->tzOffset) . ')';

				// add log about user session
				Logger::event($eventMessage);

				// set defaults in case of no module
				if (NULL == $router->module) {
					$landing = $user->getLanding();
					$router->module = $landing->module;
					$router->action = $landing->action;
				}

				$resource = $router->module . '/' . $router->action;

				// checking permission
				if ($this->currentUser->canAccess((string)$router->module, $router->action)) {

					// access granted
					Logger::event('Access granted on resource ' . $resource);

				} else {

					// access denied
					$this->enqueueError(Translator::do('ACCESS_FORBIDDEN', $resource));
					$this->redirect($router->defaults['module'] . '/' . $router->defaults['action']);

				}

			}

		// user is not logged in
		} else {

			// check RememberMe cookie
			if (User::loginByRememberMe()) {
				$this->currentUser->redirectToDefault();
			}

			// redirect to login page if action is not login or password reset
			if (!('user'==$router->module and in_array($router->action, ['login','reset','confirm','sendResetEmail','newPassword','setNewPassword']))) {
				$this->redirect('user/login');
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
	public function setPersistentState(string $name, $value) {

		$name = static::getCookiePrefix() . ucfirst($name);

		$this->persistentState[$name] = $value;

		setcookie($name, json_encode($value), 0, '/');

	}

	/**
	 * Retrieves variables of any type form a cookie named like in param.
	 *
	 * @param	string	State name.
	 *
	 * @return	mixed
	 */
	public function getPersistentState(string $name) {

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
	public function unsetPersistentState(string $name) {

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
	public static function getCookiePrefix(): string {

		return str_replace(' ', '', ucwords(str_replace('_', ' ', PRODUCT_NAME)));

	}

	/**
	 * Parse template file, replace variables and return it.
	 *
	 * @throws \Exception
	 */
	final public function startMvc() {

		$router	= Router::getInstance();

		// make sure to have a template set
		$template = $this->getTemplate();

		$controllerFile = APPLICATION_PATH . '/modules/' . $router->module . '/controller.php';

		// check controller file existence
		if (!file_exists($controllerFile) or '404' == $router->url) {

			$this->enqueueError(Translator::do('RESOURCE_NOT_FOUND', $router->url));
			$this->style = '404';
			$this->pageTitle = 'HTTP 404 error';
			http_response_code(404);

		} else {

			require ($controllerFile);

			// build controller object
			$controllerName = ucfirst($router->module) . 'Controller';
			$controller = new $controllerName();

			// set the action
			$action = $router->action ? $router->action . 'Action' : 'defaultAction';

			// set log of ajax call
			if ($router->ajax) {

				$params = array();
				foreach ($router->vars as $key=>$value) {
					$params[] = $key . '=' . Utilities::varToText($value);
				}
				Logger::event(date('Y-m-d H:i:s') . ' AJAX call on ' . $this->module . '/' . $this->action . ' with params ' . implode(', ', $params));

			// log controller method call
			} else {

				Logger::event('Called controller method ' . $controllerName . '->' . $action . '()');

			}

			// run the action
			$controller->$action();

			// raw calls will jump controller->display, ob and log
			if ($router->isRaw()) {
				return;
			}

			// invoke the view
			$controller->display();

			// get logger events into an object’s property
			$logger	= Logger::getInstance();
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

		// collect manifest
		foreach ($this->manifestFiles as $href) {

			$this->pageStyles .= '<link rel="manifest" href="' . $href . '">' . "\n";

		}

		// collect script files
		foreach ($this->scriptFiles as $script) {

			// initialize attributes render
			$attribsRender = '';

			// render each attribute based on its variable type (string or boolean)
			foreach ($script as $tag => $value) {
				$attribsRender .= ' ' . (is_bool($value) ? $tag : $tag . '="' . htmlspecialchars((string)$value) . '"');
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
	private function getMessageScript() {

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
	private function getTemplate() {

		if (!$this->template or !$this->template->areKeysPopulated()) {
			$this->template = Template::getDefault();
		}

		// if this is derived template, load derived.php file
		if ($this->template->derived) {
			$derivedFile = $this->template->getBaseFolder() . '/'  . strtolower($this->template->name) . '/derived.php';
			if (file_exists($derivedFile)) require $derivedFile;
		}

		return $this->template;

	}

	/**
	 * Return TRUE if this host is a developer server.
	 *
	 * @return bool
	 */
	final public static function isDevelopmentHost(): bool {

		// can be defined in config.php, default is false
		return PAIR_DEVELOPMENT;

	}

	/**
	 * Return TRUE if Pair was invoked by CLI.
	 */
	final public static function isCli(): bool {

		return (php_sapi_name() === 'cli');

	}

	/**
	 * Returns the time zone of the logged in user, otherwise the default for the application.
	 * @return \DateTimeZone
	 */
	public static final function getTimeZone(): \DateTimeZone {

		$app = Application::getInstance();

		// in login page the currentUser doesn’t exist
		return is_a($app->currentUser, 'User')
			? $app->currentUser->getDateTimeZone()
			: new \DateTimeZone(BASE_TIMEZONE);

	}

}
