<?php

namespace Pair\Core;

use Pair\Exceptions\AppException;
use Pair\Exceptions\CriticalException;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Helpers\LogBar;
use Pair\Helpers\Options;
use Pair\Helpers\Translator;
use Pair\Helpers\Utilities;
use Pair\Html\IziToast;
use Pair\Html\SweetAlert;
use Pair\Html\TemplateRenderer;
use Pair\Html\Widget;
use Pair\Models\Audit;
use Pair\Models\OAuth2Token;
use Pair\Models\Session;
use Pair\Models\Template;
use Pair\Models\Token;
use Pair\Models\User;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;

/**
 * Singleton application core, globally available for caching, queuing messages
 * and rendering views/templates.
 */
class Application {

	/**
	 * Singleton property.
	 */
	static protected ?self $instance;

	/**
	 * Multi-array cache as list of class-names with list of ActiveRecord’s objects.
	 */
	private array $activeRecordCache = [];

	/**
	 * List of API modules.
	 */
	private array $apiModules = ['api'];

	/**
	 * List of modules [name => [actions]] that can run with no authentication required.
	 */
	private array $guestModules = ['oauth2' => []];

	/**
	 * Short-lived state variables persisted in browser cookies to survive redirects or a single subsequent request.
	 */
	private array $persistentState = [];

	/**
	 * List of temporary variables.
	 */
	private array $state = [];

	/**
	 * Web page title, in plain text.
	 */
	private string $pageTitle = '';

	/**
	 * The page heading text.
	 */
	private string $pageHeading = '';

	/**
	 * HTML content of web page.
	 */
	private string $pageContent = '';

	/**
	 * The label of the current selected menu item.
	 */
	private ?string $menuLabel = null;

	/**
	 * The url of the current selected menu item.
	 */
	private ?string $menuUrl = null;

	/**
	 * Contains a list of plain text script to add.
	 */
	private array $scriptContent = [];

	/**
	 * Contains all external script files to load.
	 */
	private array $scriptFiles = [];

	/**
	 * Contains all CSS files to load.
	 */
	private array $cssFiles = [];

	/**
	 * Contains Manifest files to load.
	 */
	private array $manifestFiles = [];

	/**
	 * Toast notifications, to be shown on page load.
	 * @var IziToast[]
	 */
	private array $toasts = [];

	/**
	 * Modal alert to be shown on page load.
	 */
	private ?SweetAlert $modal = null;

	/**
	 * Currently connected user.
	 */
	private ?User $currentUser = null;

	/**
	 * Current session object.
	 */
	private ?Session $session = null;

	/**
	 * Contents variables for layouts.
	 */
	private array $vars = [];

	/**
	 * Template’s object.
	 */
	private ?Template $template = null;

	/**
	 * Template-style’s file name (without extension).
	 */
	private string $style = 'default';

	/**
	 * Contains the page scripts to be loaded at the end of the page.
	 */
	protected string $pageScripts = '';

	/**
	 * Contains the LogBar rendered output for the current request, or null if LogBar is disabled.
	 */
	protected ?LogBar $logBar = null;

	/**
	 * Class for application user object.
	 */
	private string $userClass = 'Pair\Models\User';

	/**
	 * Headless mode flag to avoid any output.
	 */
	private bool $headless = false;

	/**
	 * List of reserved cookie names and related allowed classes.
	 */
	const RESERVED_COOKIE_NAMES = [
		'Modal' => 'Pair\Html\SweetAlert',
		'ToastNotifications' => 'Pair\Html\IziToast'
	];

	/**
	 * Private constructor called by getInstance(). No LogBar calls here.
	 */
	private function __construct() {

		// override error settings on server
		ini_set('error_reporting',	E_ALL);
		ini_set('display_errors',	true);

		// prevent loop error for recursive __construct
		if (defined('APPLICATION_PATH')) {
			return;
		}

		// application folder without trailing slash
		define('APPLICATION_PATH', dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))));

		// check .env file or start installation
		if (!Env::fileExists() and file_exists(APPLICATION_PATH . '/installer/start.php')) {
			include APPLICATION_PATH . '/installer/start.php';
			die();
		}

		Env::load();

		$this->defineConstants();

		if (!Env::fileExists()) {
			CriticalException::terminate('Configuration file not found, check .env file', ErrorCodes::LOADING_ENV_FILE);
		}

		// custom error handlers
		Logger::registerHandlers();

		// routing initialization
		$router = Router::getInstance();
		$router->parseRoutes();

		// force utf8mb4
		if (Env::get('DB_UTF8')) {
			$db = Database::getInstance();
			$db->setUtf8unicode();
		}

		// raw calls will jump templates inclusion, so turn-out output buffer
		if (!$this->headless) {

			// default page title, maybe overwritten
			$this->pageTitle(Env::get('APP_NAME'));

			$gzip = (isset($_SERVER['HTTP_ACCEPT_ENCODING']) and substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip'));

			// if supported, output is compressed with gzip
			if ($gzip and extension_loaded('zlib') and !ini_get('zlib.output_compression')) {
				ob_start('ob_gzhandler');
			} else {
				ob_start();
			}

			// retrieve persistent notifications and modal
			$this->retrievePersistentNotifications();

			return;

		}

	}

	/**
	 * Magic getter.
	 *
	 * First tries to return a variable assigned to the template. If not found, falls
	 * back to selected Application properties. Returns null if the requested name does
	 * not match any of them.
	 *
	 * @param	string	Requested property name.
	 * @return	mixed	The resolved value or null if not found.
	 */
	public function __get(string $name): mixed {

		switch ($name) {

			// useful in html tag to set language code
			case 'langCode':

				$translator = Translator::getInstance();
				$value = $translator->getCurrentLocale()->getRepresentation();
				break;

			default:

				$allowedProperties = [
					'activeRecordCache',
					'currentUser',
					'session',
					'userClass',
					'pageTitle',
					'pageHeading',
					'pageContent',
					'menuLabel',
					'menuUrl',
					'template',
					'messages',
					'headless',
					'logBar'
				];

				// search into variable assigned to the template as first
				if (array_key_exists($name, $this->vars)) {

					$value = $this->vars[$name];

				// then search in properties
				} else if (property_exists($this, $name) and in_array($name, $allowedProperties)) {

					$value = $this->$name;

				// then return null
				} else {

					$logger = Logger::getInstance();
					$context = [
						'name' => $name,
						'class' => get_called_class()
					];
					$logger->error('Property “{name}” doesn’t exist for this object of class {class}', $context);
					$value = null;

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
	public function __set(string $name, mixed $value): void {

		if (property_exists($this, $name)) {

			// object properties
			$this->$name = $value;

		} else {

			// layout’s variables
			$this->vars[$name] = $value;

		}

	}

	/**
	 * Add script content that will be loaded at the end of the page.
	 *
	 * @param	string	Javascript content.
	 */
	public function addScript(string $script): void {

		$this->scriptContent[] = $script;

	}

	/**
	 * Defines core global constants derived from the current environment. This includes:
	 * - PAIR_FOLDER: Pair framework folder path relative to APPLICATION_PATH
	 * - TEMP_PATH:   path to the temporary folder
	 * - URL_PATH:    base URL path of the application
	 * - BASE_HREF:   absolute base URL, or null when not running under HTTP
	 */
	private function defineConstants(): void {

		// Pair folder
		define('PAIR_FOLDER', substr(dirname(dirname(__FILE__)), strlen(APPLICATION_PATH)+1));

		// path to temporary folder
		define('TEMP_PATH', APPLICATION_PATH . '/temp/');

		// force php server date to UTC
		if (Env::get('UTC_DATE')) {
			ini_set('date.timezone', 'UTC');
			define('BASE_TIMEZONE', 'UTC');
		} else {
			define('BASE_TIMEZONE', (date_default_timezone_get() ?? 'UTC'));
		}

		// base URL is null
		if (static::isCli()) {
			$baseHref = $urlPath = null;
		// define full URL to web page index with trailing slash or null
		} else {
			$protocol = ($_SERVER['SERVER_PORT'] == 443 or (isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] !== 'off')) ? "https://" : "http://";
			$urlPath = substr($_SERVER['SCRIPT_NAME'], 0, -strlen('/public/index.php'));
			$baseHref = isset($_SERVER['HTTP_HOST']) ? $protocol . $_SERVER['HTTP_HOST'] . $urlPath . '/' : null;
		}

		define('URL_PATH', $urlPath);

		define('BASE_HREF', $baseHref);

	}

	/**
	 * Check the temporary folder and, if it does not exist or is inaccessible, create it.
	 */
	public static function fixTemporaryFolder(): bool {

		if (!file_exists(TEMP_PATH) or !is_dir(TEMP_PATH) or !is_writable(TEMP_PATH)) {

			// remove any file named as wanted temporary folder
			if (file_exists(TEMP_PATH) and !unlink(TEMP_PATH)) {
				trigger_error('File ' . TEMP_PATH . ' exists and can’t be removed');
				return false;
			}

			// create the folder
			$old = umask(0);
			if (!mkdir(TEMP_PATH, 0777, true)) {
				trigger_error('Directory creation on ' . TEMP_PATH . ' failed');
				return false;
			}
			umask($old);

			// sets full permissions
			if (!chmod(TEMP_PATH, 0777)) {
				trigger_error('Set permissions on directory ' . TEMP_PATH . ' failed');
				return false;
			}

		}

		return true;

	}

	/**
	 * Return an ActiveRecord object cached or null if not found.
	 *
	 * @param	string		Name of the ActiveObject class.
	 * @param	int|string	Unique identifier of the class.
	 */
	final public function getActiveRecordCache(string $class, int|string $id): ?ActiveRecord {

		return (isset($this->activeRecordCache[$class][$id])
			? $this->activeRecordCache[$class][$id]
			: null);

	}

	/**
	 * Returns the plain-text messages of the current modal and all queued toasts. This
	 * is useful for logging, debugging or tests that need access to the human-readable
	 * notification messages.
	 *
	 * @return string[]
	 */
	final public function getAllNotificationsMessages(): array {

		$messages = [];

		if ($this->modal) {
			$messages[] = $this->modal->getText();
		}

		foreach ($this->toasts as $toast) {
			$messages[] = $toast->getText();
		}

		return $messages;

	}

	/**
	 * Builds the full cookie name for a given state name using the application prefix.
	 *
	 * @param	string	State variable name.
	 * @return	string	Fully qualified cookie name.
	 */
	private function getCookieName(string $stateName): string {

		return static::getCookiePrefix() . ucfirst($stateName);

	}

	/**
	 * Return a list of common parameters for cookies with custom expiration time.
	 */
	public static function getCookieParams(int $expires): array {

		return [
			'expires' => $expires,
			'path' => '/',
			'samesite' => 'Lax',
			'secure' => 'development' != Application::getEnvironment()
		];

	}

	/**
	 * Return a cookie prefix based on product name, like ProductName.
	 */
	public static function getCookiePrefix(): string {

		return str_replace(' ', '', ucwords(str_replace('_', ' ', Env::get('APP_NAME'))));

	}

	/**
	 * Return 'development', 'staging' or 'production' (default).
     */
    public static function getEnvironment(): string {

		if (in_array(Env::get('APP_ENV'), ['development','staging'])) {
        	return Env::get('APP_ENV');
		}

		return 'production';

    }

	/**
	 * Create singleton Application object and return it.
	 */
	public static function getInstance(): Application {

		// could be this class or inherited
		$class = get_called_class();

		if (!isset(static::$instance) or is_null(static::$instance)) {
			static::$instance = new $class();
		}

		return static::$instance;

	}

	/**
	 * Returns javascript code for displaying a modal alert.
	 */
	private function getModalScript(): string {

		return $this->modal ? $this->modal->render() : '';

	}

	/**
	 * Retrieves a persistent state value from cookies. The cookie name is derived
	 * from the given state name and the application cookie prefix. Values are
	 * unserialized with a whitelist of allowed classes for security and may throw
	 * an AppException on failure.
	 *
	 * @param	string	Name of the state variable.
	 * @return	mixed	The stored value, or null if not found.
	 */
	public function getPersistentState(string $stateName): mixed {

		// get the full prefixed key
		$cookieName = $this->getCookieName($stateName);

		if (array_key_exists($stateName, $this->persistentState)) {

			return $this->persistentState[$stateName];

		} else if (array_key_exists($cookieName, $_COOKIE)) {

			// for security reasons, unserialize only allowed classes
			$allowedClasses = array_key_exists($stateName, static::RESERVED_COOKIE_NAMES)
				? [self::RESERVED_COOKIE_NAMES[$stateName]]
				: [];

			// as of PHP 8.4.0 throws an Exception
			if (PHP_VERSION >= '8.4.0') {
				try {
					return unserialize($_COOKIE[$cookieName], ['allowed_classes' => $allowedClasses]);
				} catch (\Exception $e) {
					throw new AppException('Error unserializing cookie ' . $cookieName, ErrorCodes::UNSERIALIZE_ERROR, $e);
				}
			} else if (Utilities::isSerialized($_COOKIE[$cookieName], $allowedClasses)) {
				return unserialize($_COOKIE[$cookieName], ['allowed_classes' => $allowedClasses]);
			} else {
				$this->unsetPersistentState($stateName);
				throw new AppException('Error unserializing cookie ' . $cookieName, ErrorCodes::UNSERIALIZE_ERROR);
			}

		}

		return null;

	}

	/**
	 * Returns the requested session state variable.
	 *
	 * @param	string	State name.
	 */
	final public function getState(string $name): mixed {

		if (array_key_exists($name, $this->state)) {
			return $this->state[$name];
		} else {
			return null;
		}

	}

	/**
	 * If current selected template is not valid, replace it with the default one. It’s private to avoid
	 * loops on derived templates load.
	 */
	private function getTemplate(): Template {

		if (!$this->template or !$this->template->areKeysPopulated()) {
			$this->template = Template::getDefault();
		}

		if (!$this->template) {
			throw new CriticalException(Translator::do('NO_VALID_TEMPLATE'), ErrorCodes::NO_VALID_TEMPLATE);
		}

		// if this is derived template, load derived.php file
		if ($this->template->derived) {
			$derivedFile = $this->template->getBaseFolder() . '/'  . strtolower($this->template->name) . '/derived.php';
			if (file_exists($derivedFile)) require $derivedFile;
		}

		return $this->template;

	}

	/**
	 * Returns the time zone of the logged in user, otherwise the default for the application.
	 *
	 * @return	\DateTimeZone	The time zone object.
	 */
	public static final function getTimeZone(): \DateTimeZone {

		$app = Application::getInstance();

		if ($app->session and $app->session->timezoneName) {
			// assert valid timezone name
			try {
				return new \DateTimeZone((string)$app->session->timezoneName);
			} catch (\Exception $e) {
				// fall back to BASE_TIMEZONE
			}
		}

		return new \DateTimeZone(BASE_TIMEZONE);

	}

	/**
	 * Returns javascript code for displaying toast notifications.
	 */
	private function getToastsScript(): string {

		$script = '';

		foreach ($this->toasts as $toast) {
			$script .= $toast->render();
		}

		return $script;

	}

	/**
	 * Returns layout variables assigned to the template.
	 */
	public function getVars(): array {

		return $this->vars;

	}

	/**
	 * Add the name of a module to the list of guest modules, for which authorization is not required.
	 * The optional allowedActions array can contain the list of actions that are allowed without
	 * authentication, otherwise all actions are allowed.
	 *
	 * @param	string	Module or modules name.
	 * @param	array	Optional list of allowed actions.
	 */
	public function guestModule(string $moduleName, array $allowedActions = []): void {

		$this->guestModules[$moduleName] = $allowedActions;

	}

	/**
	 * Handle API login, logout and custom requests.
	 *
	 * @param	string	Name of module that executes API requests. Default is “api”.
	 */
	private function handleApiRequest(string $name = 'api'): void {

		$router = Router::getInstance();

		// check if API has been called
		if (!trim($name) or $name != $router->module or !file_exists(APPLICATION_PATH . '/' . MODULE_PATH . 'controller.php')) {
			return;
		}

		$this->logBar = LogBar::getInstance();
		$this->logBar->disable();

		// require controller file
		require (APPLICATION_PATH . '/' . MODULE_PATH . 'controller.php');

		// get SID or token via GET
		$sid		= Router::get('sid');
		$tokenValue	= Router::get('token');

		// read the Bearer token via HTTP header
		$bearerToken = OAuth2Token::readBearerToken();

		// assemble the API controller name
		$ctlName = $name . 'Controller';

		if (!class_exists($ctlName)) {
			print ('The API Controller class is incorrect');
			exit();
		}

		// new API Controller instance
		$apiCtl = new $ctlName();

		$this->headless(true);

		// set the action function
		$action = $router->action ? $router->action . 'Action' : 'defaultAction';

		// check token as first
		if ($tokenValue) {

			$token = Token::getByValue((string)$tokenValue);

			// set token and start controller
			if (!$token) {
				Utilities::jsonError('BAD_REQUEST','Token not found');
			}

			$token->updateLastUse();
			$apiCtl->setToken($token);

		// or check for Oauth2 Bearer token via http header
		} else if ($bearerToken) {

			if (!OAuth2Token::validate($bearerToken)) {
				sleep(3);
				OAuth2Token::unauthorized('Authentication failed');
			}

			// verify that the bearer token is valid
			$apiCtl->setBearerToken($bearerToken);

		} else if ('auth' == $router->action) {

			$param = $router->getParam(0);

			if ('login' == $param) {

				// destroy the current session
				Session::destroy();

				// start a new session
				session_start();

			} else if ('logout' == $param) {

				// destroy the current session
				Session::destroy();

			} else {

				Utilities::jsonError('BAD_REQUEST','Path not found',400,[
					'path' => $router->getUrl()
				]);

			}

		// signup
		} else if ('signup' == $router->action) {

			// continue with apiCtl action

		// all the other requests with sid
		} else if ($sid) {

			// get passed session
			$session = new Session($sid);

			// check if sid is valid
			if (!$session->isLoaded()) {
				Utilities::jsonError('SESSION_NOT_FOUND','Session not found');
			}

			// if session exists, extend session timeout
			$session->extendTimeout();

			// create User object for API
			$userClass = $this->userClass;
			$user = new $userClass($session->userId);
			$this->setCurrentUser($user);

			// set session and start controller
			$apiCtl->setSession($session);

		// unauthorized request
		} else {

			OAuth2Token::unauthorized(Env::get('APP_NAME') . '-API: Authentication failed');

		}

		try {
			$apiCtl->$action();
		} catch (\Throwable $e) {
			Utilities::jsonError('INTERNAL_SERVER_ERROR',$e->getMessage(),500);
		}

		exit();

	}

	/**
	 * Handles unauthenticated access according to the current context. If running headless,
	 * returns a JSON error with HTTP 401. Otherwise, optionally stores the last requested
	 * URL and redirects to the login page, unless the request targets a guest module/action.
	 */
	private function handleUnauthenticated(): void {

		// check RememberMe cookie
		if (User::loginByRememberMe()) {
			return;
		}

		$router = Router::getInstance();

		// sends js message about session expired
		if ($this->headless) {

			Utilities::jsonError('AUTH_SESSION_EXPIRED','User session expired',401);

		// redirects to login page
		} else {

			// avoid to return POST requests
			if (isset($_SERVER['REQUEST_METHOD']) and 'POST' !== $_SERVER['REQUEST_METHOD']) {
				$this->setPersistentState('lastRequestedUrl', $router->getUrl());
			}

			// check if the request is for user module/action
			$userRequest = ('user' == $router->module and in_array($router->action, ['login','reset','confirm','sendResetEmail','newPassword','setNewPassword']));

			// check if the request is for guest module/action
			$guestRequest = (in_array($router->module, array_keys($this->guestModules)) and in_array($router->action, $this->guestModules[$router->module]));

			// redirect to login page if action is not login or password reset or guest module
			if (!$userRequest and !$guestRequest) {
				$this->redirect('user/login');
			}

		}

	}

	/**
	 * Enable or disable headless mode, avoiding to render any output. Chainable.
	 */
	public function headless(bool $on = true): static {

		$this->headless = $on;
    	return $this;

	}

	/**
	 * Start the session and set the User class (Pair/Models/User or a custom one that inherites
	 * from Pair/Models/User). Must use only for command-line and web application access.
	 */
	private function initializeSession(): void {

		// get required singleton instances
		$router = Router::getInstance();

		// start session or resume session started by handleApiRequest
		session_start();

		// get existing previous session
		$this->session = Session::find(session_id());

		// session time length in minutes
		$sessionTime = Options::get('session_time');

		// clean all old sessions
		Session::cleanOlderThan($sessionTime);

		// can be customized before Application is initialized
		$userClass = $this->userClass;

		// sets an empty user object
		$this->setCurrentUser(new $userClass());

		// handle session not loaded
		if (!$this->session or !$this->session->isLoaded()) {
			$this->handleUnauthenticated();
			$this->initializeLandingPage();
			return;
		}

		// handle expired session
		if ($this->session->isExpired($sessionTime)) {
			Audit::sessionExpired($this->session);
			$this->session->delete();
			$this->handleUnauthenticated();
			$this->initializeLandingPage();
			return;
		}

		// if session exists, extend session timeout
		$this->session->extendTimeout();

		// create User object
		$user = new $userClass($this->session->userId);
		$this->setCurrentUser($user);

		// add log about user session
		$logger = Logger::getInstance();

		// set landing page if not module/action specified
		$this->initializeLandingPage();

		// access control
		if ('user'==$router->module and 'login'==$router->action) {
			$landing = $user->landing();
			$this->redirect($landing->module . '/' . $landing->action);
		}

		$resource = $router->module . '/' . $router->action;

		// access denied
		if (!$this->currentUser->canAccess((string)$router->module, $router->action)) {
			$landing = $user->landing();
			$this->toastError(Translator::do('ERROR'), 'Access denied to ' . $resource);
			// avoid infinite loop
			if ($resource != $landing->module . '/' . $landing->action) {
				$this->redirect($landing->module . '/' . $landing->action);
			} else {
				$this->redirect('user/logout');
			}
		}

	}

	/**
	 * If no module/action is specified in the router, set them according to the current user, if any.
	 */
	private function initializeLandingPage(): void {

		$router = Router::getInstance();

		if ($this->currentUser instanceof User and !$router->module) {
			$landing = $this->currentUser->landing();
			$router->module = $landing->module;
			$router->action = $landing->action;
		}

	}

	/**
	 * Return true if Pair was invoked by CLI.
	 */
	final public static function isCli(): bool {

		return (php_sapi_name() === 'cli');

	}

	/**
	 * Checks whether a persistent state exists in cookies.
	 *
	 * @param	string	Name of the state variable.
	 * @return	bool	True if the value is present, false otherwise.
	 */
	public function issetPersistentState(string $stateName): bool {

		$cookieName = $this->getCookieName($stateName);

		return (array_key_exists($stateName, $this->persistentState) or isset($_COOKIE[$cookieName]));

	}

	/**
	 * Returns true if state has been previously set, null value included.
	 *
	 * @param	string	Name of the state variable.
	 */
	final public function issetState(string $name): bool {

		return (array_key_exists($name, $this->state));

	}

	/**
	 * Useful to collect CSS file list and render tags into page head.
	 *
	 * @param	string	Path to stylesheet, absolute or relative with no trailing slash.
	 */
	public function loadCss(string $href): void {

		$this->cssFiles[] = $href;

	}

	/**
	 * Collect Manifest files and includes into the page head.
	 *
	 * @param	string	Path to manifest file, absolute or relative with no trailing slash.
	 */
	public function loadManifest(string $href): void {

		$this->manifestFiles[] = $href;

	}

	/**
	 * Registers an external script file to be loaded, with optional attributes.
	 *
	 * @param	string	Path to script, absolute or relative with no trailing slash.
	 * @param	bool	Defer attribute (default false).
	 * @param	bool	Async attribute (default false).
	 * @param	array	Optional attribute list (type, integrity, crossorigin, charset).
	 */
	public function loadScript(string $src, bool $defer = false, bool $async = false, array $attribs = []): void {

		// the script object
		$script = new \stdClass();

		$script->src = $src;

		if ($defer)	$script->defer = true;
		if ($async) $script->async = true;

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
	 * Stores toast notifications for retrieval on next web page load.
	 */
	public function makeToastNotificationsPersistent(): void {

		$this->setPersistentState('ToastNotifications', $this->toasts);

	}

	/**
	 * Adds a modal alert to be shown on the next page load. Chainable.
	 */
	public function modal(string $title, string $text, ?string $icon = null): SweetAlert {

		$this->modal = new SweetAlert($title, $text, $icon);

		return $this->modal;

	}

	/**
	 * Sets the label of the current selected menu item.
	 */
	public function menuUrl(string $url): void {

		$this->menuUrl = $url;

	}

	/**
	 * Sets the label of the current selected menu item.
	 */
	public function pageHeading(string $heading): void {

		$this->pageHeading = $heading;

	}

	/**
	 * Sets the web page title (displayed in the browser tab).
	 *
	 * @param	string	Title text.
	 */
	public function pageTitle(string $title): void {

		$this->pageTitle = $title;

	}

	/**
	 * Queues a persistent alert modal to be shown on the next page load.
	 *
	 * @param string $title   Modal title.
	 * @param string $message Modal message.
	 * @param string $type    Modal icon/type (info|success|error|warning|question), default 'info'.
	 */
	public function persistentModal(string $title, string $text, ?string $icon = null): void {

		$modal = new SweetAlert($title, $text, $icon);

		$this->setPersistentState('Modal', $modal);

	}

	/**
	 * Print a widget by name.
	 *
	 * @param	string	$name	Name of the widget to render.
	 */
	public function printWidget(string $name): void {

		$widget = new Widget($name);
		print $widget->render();

	}

	/**
	 * Stores an ActiveRecord object into the Application-wide cache. The cache is
	 * keyed by ActiveRecord class name and primary key. Composite primary keys are
	 * not supported.
	 *
	 * @param	string			$class	ActiveRecord class name.
	 * @param	ActiveRecord	$object	ActiveRecord instance to cache.
	 */
	final public function putActiveRecordCache(string $class, ActiveRecord $object): void {

		// can’t manage composite key
		if (1 == count((array)$object->keyProperties) ) {

			$this->activeRecordCache[$class][(string)$object->getId()] = $object;

			$class = get_class($object);
			$className = basename(str_replace('\\', '/', $class));
			$logger = Logger::getInstance();
			$logger->debug('Cached ' . $className . ' object with id=' . (string)$object->getId());

		}

	}

	/**
	 * Redirects the client to the given URL. If a relative URL is provided, the
	 * application base URL is automatically prepended (unless $externalUrl is true).
	 * Before redirecting, any queued toast notifications and modal are stored in
	 * cookies so they can be retrieved on the next request.
	 *
	 * @param	string|null	$url			Target URL. If null, redirects to the current module with its default action.
	 * @param	bool        $externalUrl	If true, the URL is treated as absolute and the base URL is not added.
	 */
	public function redirect(?string $url = null, bool $externalUrl = false): void {

		if (is_null($url)) {
			$router = Router::getInstance();
			$url = $router->module;
		}

		// stores modal and toast notifications for next retrievement
		$this->setPersistentState('Modal', $this->modal);
		$this->makeToastNotificationsPersistent();

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
				if (false == strpos($url, '/')) {
					$url .= '/';
				}

				header('Location: ' . BASE_HREF . $url . '/page-' . $page);

			} else {

				header('Location: ' . BASE_HREF . $url);

			}

		}

		die();

	}

	/**
	 * Redirects the current user to their default landing page. If no user is logged in,
	 * redirects to the login page instead.
	 */
	public function redirectToUserDefault(): void {

		if ($this->currentUser) {
			$this->currentUser->redirectToDefault();
		} else {
			$this->redirect('user/login');
		}

	}

	/**
	 * Restores persistent modal and toast notifications from cookies. Valid SweetAlert and
	 * IziToast instances are re-queued and removed from the persistent state so they are shown
	 * only once.
	 */
	private function retrievePersistentNotifications(): void {

		$persistentModal = $this->getPersistentState('Modal');

		// retrieve cookie modal and puts in queue
		if (is_a($persistentModal, 'Pair\Html\SweetAlert')) {
			$this->unsetPersistentState('Modal');
			$this->modal = $persistentModal;
		}

		$persistentToasts = $this->getPersistentState('ToastNotifications');

		// retrieve all cookie toasts and puts in queue
		if (is_array($persistentToasts)) {
			$this->unsetPersistentState('ToastNotifications');
			foreach ($persistentToasts as $toast) {
				if (is_a($toast, 'Pair\Html\IziToast')) {
					$this->toasts[]= $toast;
				}
			}
		}

	}

	/**
	 * Run the application.
	 *
	 * This method is the main entry point of the application. It initializes the session,
	 * handles API requests if applicable, and runs the MVC pattern to render the page.
	 */
	final public function run(): void {
		$this->initializeController();
		$this->renderTemplate();

	}

	/**
	 * Initialize controller and run its action.
	 *
	 * This method initializes the session (if needed) and runs the MVC controller
	 * flow without rendering the template, allowing template rendering to be invoked separately.
	 */
	final public function initializeController(): void {

		$router = Router::getInstance();

		if (in_array($router->module, $this->apiModules)) {
			$this->handleApiRequest();
			return;
		}

		if (!static::isCli()) {
			$this->initializeSession();
		}

		$this->runController();

	}

	/**
	 * Render the selected template with the captured page content.
	 */
	final public function renderTemplate(): void {

		$router = Router::getInstance();
		if (in_array($router->module, $this->apiModules) || $this->headless) {
			return;
		}

		$template = $this->template;
		if (!$template || !$template->areKeysPopulated()) {
			$template = $this->getTemplate();
		}

		// populate the placeholder for the content
		if (in_array($this->style, ['404','500'])) {
			ob_clean();
		} else {
			$this->pageContent = ob_get_clean();
		}

		$styleFile = $template->getStyleFile($this->style);

		// parse the template
		TemplateRenderer::parse($styleFile);

	}

	/**
	 * Runs the MVC pattern to handle the current request and render the appropriate view.
	 * 
	 * @throws	AppException	If an application-level error occurs.
	 */
	private function runController(): void {

		$router	= Router::getInstance();

		// make sure to have a template set
		$this->getTemplate();

		$controllerFile = APPLICATION_PATH . '/modules/' . $router->module . '/controller.php';

		// check controller file existence
		if (!file_exists($controllerFile) or '404' == $router->url) {

			$this->modal(Translator::do('ERROR'), Translator::do('RESOURCE_NOT_FOUND', $router->url));
			$this->style = '404';
			$this->pageTitle('HTTP 404 error');
			http_response_code(404);

		} else {

			require ($controllerFile);

			// build controller object
			$controllerName = ucfirst($router->module) . 'Controller';

			// set the action
			$action = $router->action ? $router->action . 'Action' : 'defaultAction';

			// set log of ajax call
			if ($router->ajax) {

				$params = [];
				foreach ($router->vars as $key=>$value) {
					$params[] = $key . '=' . Utilities::varToText($value);
				}
				$logger = Logger::getInstance();
				$logger->debug(date('Y-m-d H:i:s') . ' AJAX call on ' . $router->module . '/' . $router->action . ' with params ' . implode(', ', $params));

			// log controller method call
			} else {

				$logger = Logger::getInstance();
				$logger->debug('Called controller method ' . $controllerName . '->' . $action . '()');

			}

			if (!class_exists($controllerName)) {
				throw new CriticalException('Controller ' . $controllerName . ' not found', ErrorCodes::CONTROLLER_NOT_FOUND);
			}

			try {
				$controller = new $controllerName();
			} catch (\Throwable $e) {
				throw new CriticalException('Error instantiating controller ' . $controllerName . ': '
					. $e->getMessage(), ErrorCodes::CONTROLLER_INIT_FAILED, $e);
			}

			if (method_exists($controller, $action)) {
				try {
					$controller->$action();
				} catch (\Throwable $e) {
					PairException::frontEnd($e->getMessage());
				}
			} else {
				$logger = Logger::getInstance();
				$logger->info('Method ' . $controllerName . '->' . $action . '() not found');
			}

			// raw calls will jump controller->display, ob and log
			if ($this->headless) {
				return;
			}

			// invoke view rendering
			try {
				$controller->renderView();
			} catch (\Throwable $e) {
				PairException::frontEnd($e->getMessage());
			}

			$this->logBar = LogBar::getInstance();

		}

	}

	/**
	 * Sets the list of module names that should be treated as API endpoints.
	 *
	 * @param	string[]|string	$modules	One or more module names.
	 */
	public function setApiModules(array|string $modules): void {

		$this->apiModules = (array)$modules;

	}

	/**
	 * Sets current user, default template and translation locale.
	 *
	 * @param	User	$user	User object or inherited class object.
	 */
	public function setCurrentUser(User $user): void {

		$this->currentUser = $user;

		// sets user language
		$tran = Translator::getInstance();
		$tran->setLocale($user->getLocale());

	}

	/**
	 * Stores a persistent state value in a cookie for later retrieval. Existing
	 * values with the same state name are overwritten. Cookies are set with a
	 * lifetime of 30 days.
	 *
	 * @param	string		$stateName	Name of the state variable.
	 * @param	mixed		$value		Value to store (any serializable type).
	 * @throws	\Exception				If setting the cookie fails.
	 */
	public function setPersistentState(string $stateName, mixed $value): void {

		$this->persistentState[$stateName] = $value;

		// cookie lifetime is 30 days
		$params = self::getCookieParams(time() + 2592000);
		$cookieName = $this->getCookieName($stateName);

		if (!setcookie($cookieName, serialize($value), $params)) {
			throw new \Exception('Error setting persistent state cookie ' . $cookieName, ErrorCodes::COOKIE_ERROR);
		}

	}

	/**
	 * Sets an in-memory state variable for the current request.
	 *
	 * @param	string	$name	Name of the state variable.
	 * @param	mixed	$value	Value of any type as is, like strings, custom objects etc.
	 */
	public function setState(string $name, mixed $value): void {

		$this->state[$name] = $value;

	}

	/**
	 * Allow to set a custom User class that inherits from Pair\Models\User.
	 */
	public function setUserClass(string $class): void {

		$this->userClass = $class;

	}

	/**
	 * Returns the page scripts to be included before the closing body tag.
	 */
	final public function scripts(): string {

		$pageScripts = '';

		// collect script files
		foreach ($this->scriptFiles as $file) {

			// initialize attributes render
			$attribsRender = '';

			// render each attribute based on its variable type (string or boolean)
			foreach ($file as $tag => $value) {
				$attribsRender .= ' ' . (is_bool($value) ? $tag : $tag . '="' . htmlspecialchars((string)$value) . '"');
			}

			// add each script
			$pageScripts .= '<script' . $attribsRender . '></script>' . "\n";

		}

		// add Insight Hub script for error tracking and performance monitoring
		if (Env::get('INSIGHT_HUB_API_KEY') and Env::get('INSIGHT_HUB_PERFORMANCE')) {
			$pageScripts .= '<script src="https://cdn.jsdelivr.net/npm/bugsnag-js" crossorigin="anonymous"></script>' . "\n";
			$pageScripts .= '<script type="module">import BugsnagPerformance from "//d2wy8f7a9ursnm.cloudfront.net/v1/bugsnag-performance.min.js";BugsnagPerformance.start({apiKey:"' . Env::get('INSIGHT_HUB_API_KEY') .'"})</script>' . "\n";
		}

		// collect plain text scripts
		if (count($this->scriptContent) or $this->modal or count($this->toasts)) {

			$pageScripts .= "<script defer>\n";

			foreach ($this->scriptContent as $s) {
				$pageScripts .= $s ."\n";
			}

			// add modal and toasts
			if ($this->modal or count($this->toasts)) {
				$pageScripts .= "document.addEventListener('DOMContentLoaded', function() {\n";
				$pageScripts .= $this->getModalScript();
				$pageScripts .= $this->getToastsScript();
				$pageScripts .= "});\n";
			}

			$pageScripts .= "</script>";

		}

		return $pageScripts;

	}

	/**
	 * Returns the page stylesheets and manifest links to be included in the page head.
	 */
	final public function styles(): string {

		$pageStyles = '';

		// collect stylesheets
		foreach ($this->cssFiles as $href) {

			$pageStyles .= '<link rel="stylesheet" href="' . $href . '">' . "\n";

		}

		// collect manifest
		foreach ($this->manifestFiles as $href) {

			$pageStyles .= '<link rel="manifest" href="' . $href . '">' . "\n";

		}

		return $pageStyles;

	}

	/**
	 * Appends a toast notification message to queue.
	 *
	 * @param	string	Toast title (bold).
	 * @param	string	Error message.
	 * @param	string	Type of the toast (info|success|warning|error|question|progress), default info.
	 */
	public function toast(string $title, string $message = '', ?string $type = null): IziToast {

		$toast = new IziToast($title, $message, $type);
		$this->toasts[] = $toast;
		return $toast;

	}

	/**
	 * Proxy function to append an error toast notification to queue.
	 *
	 * @param	string	Toast title (bold).
	 * @param	string	Error message.
	 */
	public function toastError(string $title, string $message = ''): IziToast {

		return $this->toast($title, $message, 'error');

	}

	/**
	 * Proxy function to append an error toast notification to queue and redirect.
	 *
	 * @param	string	Toast title (bold).
	 * @param	string	Error message.
	 * @param	string	Redirect URL, optional.
	 */
	public function toastErrorRedirect(string $title, string $message = '', ?string $url = null): void {

		$this->toast($title, $message, 'error');
		$this->makeToastNotificationsPersistent();
		$this->redirect($url);

	}

	/**
	 * Proxy function to append a toast notification to queue and redirect.
	 *
	 * @param	string	Toast title (bold).
	 * @param	string	Message.
	 * @param	string	Redirect URL, optional.
	 */
	public function toastRedirect(string $title, string $message = '', ?string $url = null): void {

		$this->toast($title, $message, 'success');
		$this->makeToastNotificationsPersistent();
		$this->redirect($url);

	}

	/**
	 * Removes all state variables from cookies.
	 */
	public function unsetAllPersistentStates(): void {

		$prefix = static::getCookiePrefix();

		foreach (array_keys($_COOKIE) as $name) {
			if (0 == strpos($name, $prefix)) {
				$this->unsetPersistentState($name);
			}
		}

	}

	/**
	 * Removes a state variable from cookie.
	 */
	public function unsetPersistentState(string $stateName): void {

		$cookieName = $this->getCookieName($stateName);

		unset($this->persistentState[$stateName]);

		if (isset($_COOKIE[$cookieName])) {
			setcookie($cookieName, '', self::getCookieParams(-1));
			unset($_COOKIE[$cookieName]);
		}

	}

	/**
	 * Deletes a session state variable.
	 *
	 * @param	string	Name of the state variable.
	 */
	public function unsetState(string $name): void {

		unset($this->state[$name]);

	}

	/**
	 * Assigns variables to the template placeholder list.
	 *
	 * @param	string|array	$name	Name or array of name/value pairs.
	 * @param	mixed			$value	Value when $name is a string.
	 */
	public function var(string|array $name, mixed $value = null): void {

		if (is_array($name)) {
			foreach ($name as $key => $val) {
				$this->vars[$key] = $val;
			}
			return;
		}

		$this->vars[$name] = $value;

	}

}
