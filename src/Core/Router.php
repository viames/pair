<?php

namespace Pair\Core;

use Pair\Exceptions\ErrorCodes;

class Router {

	/**
	 * Singleton object.
	 */
	static private Router $instance;

	/**
	 * Request URL.
	 */
	private ?string $url = NULL;

	/**
	 * Flag that’s true if request is AJAX.
	 */
	private bool $ajax = FALSE;

	/**
	 * Flag that’s true if page will avoid any templating.
	 */
	private bool $raw = FALSE;

	/**
	 * Module name.
	 */
	private ?string $module = NULL;

	/**
	 * Action name.
	 */
	private ?string $action = NULL;

	/**
	 * Extended variables.
	 */
	private array $vars = [];

	/**
	 * Defautls value when empty URL.
	 */
	private array $defaults = ['module'=>NULL,'action'=>NULL];

	/**
	 * Current page number.
	 */
	private ?int $page = NULL;

	/**
	 * Current ordering value.
	 */
	private ?int $order = NULL;

	/**
	 * List of custom routing paths.
	 */
	private array $routes = [];

	/**
	 * Flag for show log informations on AJAX calls.
	 */
	private bool $sendLog = TRUE;

	/**
	 * Private constructor, called by getInstance() method.
	 */
	private function __construct() {

		// request URL, NULL for CLI
		$this->url = Application::isCli() ? NULL : $_SERVER['REQUEST_URI'];

		// remove URL_PATH from URL
		if (URL_PATH and (is_null($this->url) or strpos((string)$this->url,URL_PATH)===0)) {
			$this->url = substr((string)$this->url,strlen(URL_PATH));
		}

		// force initial slash
		if ($this->url and '/' != $this->url[0]) $this->url = '/' . $this->url;

	}

	public function parseRoutes(): void {

		// parse, add and remove from URL any CGI param after question mark
		$this->parseCgiParameters();

		// remove special prefixes and return parameters
		$params = $this->getParameters();

		// try matches in /root/routes.php file
		$routeMatches1 = $this->parseCustomRoutes($params, APPLICATION_PATH . '/routes.php');

		// set module and define the MODULE_PATH constant
		if (!defined('MODULE_PATH') and isset($params[0])) {
			$this->setModule(urldecode($params[0]));
		}

		// try matches in module specifics routes.php file
		if (defined('MODULE_PATH')) {
			$routeMatches2 = $this->parseCustomRoutes($params, APPLICATION_PATH . '/' . MODULE_PATH . 'routes.php', TRUE);
		}

		// if custom routes don't match, go for standard
		if (!$routeMatches1 and !$routeMatches2) {
			$this->parseStandardRoutes($params);
		}

	}

	/**
	 * Checks if there are any GET vars in the URL, adds these to object
	 * vars and removes from URL.
	 */
	private function parseCgiParameters(): void {

		if (FALSE===strpos((string)$this->url, '?')) {
			return;
		}

		$temp = explode('?', $this->url);
		$this->url = $temp[0];

		if (!array_key_exists(1, $temp)) {
			return;
		}

		// adds to $this->params
		$couples = explode('&', $temp[1]);

		foreach ($couples as $c) {

			$var = explode('=', $c);

			if (array_key_exists(1, $var)) {
				list ($key, $value) = $var;
				$this->setParam($key, $value);
			}

		}

	}

	/**
	 * Remove special prefixes (ajax, raw) and return all the parameters found in the URL.
	 */
	private function getParameters(): array {

		$url = ($this->url and '/' == $this->url[0]) ? substr($this->url,1) : (string)$this->url;

		// split parameters by slash
		$params = explode('/', $url);

		// reveal ajax calls and raw requests and cuts special prefix from URL
		if (array_key_exists(0, $params)) {

			switch ($params[0]) {

				case 'ajax':
					$this->ajax = $this->raw = TRUE;
					array_shift($params);
					break;

				case 'raw':
					$this->raw = TRUE;
					array_shift($params);
					break;

			}

		}

		return $params;

	}

	/**
	 * Parse an URL searching for a custom route that matches and store parameter values.
	 *
	 * @param	array:string	List of URL parameters.
	 * @param	string			Path to routes file.
	 * @param	bool			Flag to set as module routes.
	 */
	private function parseCustomRoutes(array $params, string $routesFile, bool $moduleRoute=FALSE): bool {

		// check if controller file exists
		if (!file_exists($routesFile)) {
			return FALSE;
		}

		// trigger for route processed
		$routeMatches = FALSE;

		// temporary store previous routes
		$routesBackup = $this->routes;

		// fall-back in case of no adds in routesFile
		$this->routes = [];

		// read the custom routes by php file
		require $routesFile;

		// check about the third parameter $module in the custom-routes available as of now
		foreach ($this->routes as &$r) {

			// add module prefix if not set
			if ($moduleRoute and strpos($r->path,'/'.$this->module) !== 0) {
				$r->path = '/' . $this->module . $r->path;
			}

			// compare current URL to regex
			if (!Application::isCli() and $this->routePathMatchesUrl($r->path, $this->url)) {

				// assign action
				$this->action = $r->action;
				$this->raw = $r->raw;

				// assign even module
				if (!$moduleRoute and !$this->module) {
					if ($r->module) {
						$this->setModule($r->module);
					} else if (isset($params[0])) {
						$this->setModule($params[0]);
					}
				}

				// clean-up and split the route path
				$parts = array_values(array_filter(explode('/', $r->path)));

				// initialize array of temporary variables
				$variables = [];

				// store the variables found with name and position
				foreach ($parts as $pos => $part) {

					// search for the colon symbol that precedes the variable name
					if (substr($part,0,1) == ':') {

						// search for a possible regular expression
						$regexPos = strpos($part, '(');

						// remove the colon and any regex after variable name
						$variables[$pos] = FALSE === $regexPos ?
							substr($part,1) :
							substr($part,1,$regexPos-1);

					}

				}

				// assign params to vars array and set page, order and log
				foreach ($params as $pos => $value) {

					// flag to not send back log (useful for AJAX)
					if ('noLog' == $value) {

						$this->sendLog = FALSE;

					// ordering
					} else if ('order-' == substr($value, 0, 6)) {

						$nr = intval(substr($value, 6));
						if ($nr) $this->order = $nr;

					// pagination
					} else if ('page-' == substr($value, 0, 5)) {

						$nr = intval(substr($value, 5));
						$this->setPage($nr);

					// create a var with parsed name
					} else if (isset($variables[$pos]) and $variables[$pos]) {

						$this->vars[$variables[$pos]] = $value;

					// otherwise assign the param by its index position
					} else {

						$this->vars[$pos] = $value;

					}

				}

				$routeMatches = TRUE;

				break;

			}

		}

		// restore the Router::routes array property
		$this->routes = array_merge($routesBackup, $this->routes);

		return $routeMatches;

	}

	/**
	 * Populates router variables by standard parameter login.
	 *
	 * @param	string[]	List of URL parameters.
	 */
	private function parseStandardRoutes(array $params) {

		// set module, action and page nr by parameters
		foreach ($params as $pos => $value) {

			switch ($pos) {

				case 0:

					// module name, nothing to do here
					break;

				case 1:

					$this->action = urldecode($value);
					break;

				default:

					$param = urldecode($value);

					// flag to not send back log (useful for AJAX)
					if ('noLog' == $param) {
						$this->sendLog = FALSE;
						// ordering
					} else if ('order-' == substr($param, 0, 6)) {
						$nr = intval(substr($param, 6));
						if ($nr) $this->order = $nr;
						// pagination
					} else if ('page-' == substr($param, 0, 5)) {
						$nr = intval(substr($param, 5));
						$this->setPage($nr);
					} else {
						if (''!=$param and !is_null($param)) {
							$this->vars[] = $param;
						}
					}
					break;

			}

		}

	}

	/**
	 * Create then return the singleton object.
	 */
	public static function getInstance(): self {

		if (!isset(self::$instance) or is_null(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;

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

	/**
	 * Set a property of this object.
	 *
	 * @param	string	Property’s name.
	 * @param	mixed
	 */
	public function __set(string $name, mixed $value): void {

		$this->$name = $value;

	}

	/**
	 * Return an URL parameter, if exists. Exclude routing base params (module and action).
	 *
	 * @param	mixed	Parameter position (zero based) or Key name.
	 * @param	bool	Flag to decode a previously encoded value as char-only.
	 */
	public static function get(int|string $paramIdx, bool $decode=FALSE): ?string {

		$self = static::$instance;

		if (!$self) return NULL;

		if (array_key_exists($paramIdx, $self->vars) and ''!=$self->vars[$paramIdx]) {
			$value = $self->vars[$paramIdx];
			if ($decode) {
				$value = json_decode(gzinflate(base64_decode(strtr($value, '-_', '+/'))));
			}
			return $value;
		} else {
			return NULL;
		}

	}

	/**
	 * Return an URL parameter, if exists. It escludes routing base params (module and action).
	 *
	 * @param	mixed	Parameter position (zero based) or Key name.
	 * @param	bool	Flag to decode a previously encoded value as char-only.
	 */
	public function getParam(int|string $paramIdx, bool $decode=FALSE): ?string {

		if (array_key_exists($paramIdx, $this->vars) and ''!=$this->vars[$paramIdx]) {
			$value = $this->vars[$paramIdx];
			if ($decode) {
				$value = json_decode(gzinflate(base64_decode(strtr($value, '-_', '+/'))));
			}
			return $value;
		} else {
			return NULL;
		}

	}

	/**
	 * Add a param value to the URL, on index position if given and existent.
	 *
	 * @param	mixed	Zero based position on URL path or Key name.
	 * @param	string	Value to add.
	 * @param	bool	Flag to encode as char-only the value.
	 */
	public function setParam(mixed $paramIdx, string $value, bool $encode=FALSE): void {

		if ($encode) {
			$value = rtrim(strtr(base64_encode(gzdeflate(json_encode($value), 9)), '+/', '-_'), '=');
		}

		$this->vars[$paramIdx] = $value;

	}

	/**
	 * Delete all parameters.
	 */
	public function resetParams(): void {

		$this->vars = [];

	}

	/**
	 * Return the current list page number.
	 */
	public function getPage(): int {

		$cookieName = Application::getCookiePrefix() . ucfirst((string)$this->module) . ucfirst((string)$this->action);

		if (!is_null($this->page)) {

			return ((int)$this->page > 1 ? $this->page : 1);

		} else if (isset($_COOKIE[$cookieName]) and (int)$_COOKIE[$cookieName] > 0) {

			return (int)$_COOKIE[$cookieName];

		} else {

			return 1;

		}

	}

	/**
	 * Set a persistent state as current pagination index.
	 *
	 * @param	int		Page number.
	 */
	public function setPage(int $number): void {

		$number = (int)$number;

		$this->page = $number;

		// the cookie about persistent state
		$cookieName = Application::getCookiePrefix() . ucfirst((string)$this->module) . ucfirst((string)$this->action);

		// set the persistent state, lifetime is 30 days
		setcookie($cookieName, $number, Application::getCookieParams(time() + 2592000));

	}

	/**
	 * Reset page number to 1.
	 */
	public function resetPage(): void {

		$this->page = 1;

		// the cookie about persistent state
		$cookieName = Application::getCookiePrefix() . ucfirst((string)$this->module) . ucfirst((string)$this->action);

		// unset the persistent state
		if (isset($_COOKIE[$cookieName])) {
			unset($_COOKIE[$cookieName]);
			setcookie($cookieName, '', Application::getCookieParams(-1));
		}

	}

	/**
	 * Set the current module name and define the MODULE_PATH constant.
	 *
	 * @param	string	Module name.
	 */
	public function setModule(string $moduleName): void {

		if (!defined('MODULE_PATH')) {

			$this->module = $moduleName;

			define('MODULE_PATH', 'modules/' . $this->module . '/');

		}

	}

	/**
	 * Set the action
	 *
	 * @param  string|NULL	Action string or NULL.
	 */
	public function setAction(?string $action): void {

		$this->action = $action;

	}

	/**
	 * Return action string.
	 */
	public function getAction(): ?string {

		return $this->action;

	}

	/**
	 * Sets the default module and action, useful when module is missing in the URL.
	 *
	 * @param	string	Default module name.
	 * @param	string	Default action.
	 */
	public function setDefaults(string $module, string $action): void {

		$this->defaults['module'] = $module;
		$this->defaults['action'] = $action;

	}

	/**
	 * Returns URL of default module + default action.
	 */
	public function getDefaultUrl(): string {

		return $this->defaults['module'] . '/' . $this->defaults['action'];

	}

	/**
	 * Set page to be viewed with no template, useful for ajax requests and API.
	 */
	public static function setRaw(): void {

		try {
			self::$instance->raw = TRUE;
		} catch (\Exception $e) {
			die('Router instance has not been created yet');
		}

	}

	/**
	 * Add a new route path.
	 *
	 * @param	string		Path with optional variable placeholders.
	 * @param	string		Action.
	 * @param	string|NULL	Optional module name.
	 * @param	bool|NULL	Optional raw flag.
	 */
	public static function addRoute(string $path, string $action, ?string $module=NULL, ?bool $raw=FALSE) {

		// fix empty path
		if ('' == $path) {
			$path = '/';
		// force initial slash
		} else if ('/' != $path[0]) {
			$path = '/' . $path;
		}

		$route = new \stdClass();
		$route->path	= $path;
		$route->action	= $action;
		$route->module	= $module;
		$route->raw		= (bool)$raw;

		try {
			self::$instance->routes[] = $route;
		} catch (\Exception $e) {
			die('Router instance has not been created yet');
		}

	}

	/**
	 * Compare a custom route path as text or regex to the param URL and
	 * return TRUE if it matches.
	 *
	 * @param	string	The custom Route path.
	 * @param	string	The URL to check.
	 */
	public function routePathMatchesUrl(string $path, string $url): bool {

		// replace any regex after param name in path
		$pathRegex = preg_replace('|/:[^/(]+\(([^)]+)\)|', '/($1)', $path);

		// then replace simple param name in path
		$pathRegex = preg_replace('|/(:[^/]+)|', '/([^/]+)', $pathRegex);

		// remove prefix and compare current URL to regex
		$cleanUrl = preg_replace('#^([/]*raw)/|^([/]*ajax)/#','/', $url, 1);
		return preg_match('|^' . $pathRegex . '$|', $cleanUrl);

	}

	/**
	 * Return, if exists, the custom route object that matches the URL in param.
	 *
	 * @param	string	The URL as /module/action.
	 */
	public function getModuleActionFromCustomUrl(string $url): ?\stdClass {

		foreach ($this->routes as $r) {

			// compare current URL to regex
			if ($this->routePathMatchesUrl($r->path, $url)) {
				return $r;
			}

		}

		return NULL;

	}

	/**
	 * Returns current relative URL, with order and optional pagination.
	 */
	public function getUrl(): string {

		$sefParams = [];
		$cgiParams = [];

		// queue all parameters
		foreach ($this->vars as $key=>$val) {
			if (is_int($key)) {
				$sefParams[] = $val;
			} else {
				$cgiParams[$key] = $val;
			}
		}

		$url = $this->module . '/' . $this->action;

		// add slashed params
		if (count($sefParams)) {
			$url .= '/' . implode('/', $sefParams);
		}

		// add ordering
		if ($this->order) {
			$url .= '/order-' . $this->order;
		}

		// add pagination
		if ($this->page) {
			$url .= '/page-' . $this->getPage();
		}

		// add associative params
		if (count($cgiParams)) {
			$url .= '?' . http_build_query($cgiParams);
		}

		return $url;

	}

	/**
	 * Proxy method to get the current URL with a different order value. If NULL param, will
	 * reset ordering.
	 *
	 * @param	int|NULL	Optional order value to build the URL with.
	 */
	public function getOrderUrl(?int $val=NULL): string {

		// save current order val
		$tmp = $this->order;

		// build url with order value in param
		$this->order = $val;
		$url = $this->getUrl();
		$this->order = $tmp;

		return $url;

	}

	/**
	 * Special method to get the current URL with a different page number. If NULL param, will
	 * reset pagination.
	 *
	 * @param	int|NULL	Optional page number to build the URL with.
	 */
	public function getPageUrl(?int $page=NULL): string {

		// save current order val
		$tmp = $this->page;

		// build url with order value in param
		$this->page = (int)$page;
		$url = $this->getUrl();
		$this->page = $tmp;

		return $url;

	}

	/**
	 * Stampa l’URL calcolato in base ai parametri.
	 */
	public function __toString(): string {

		$path = $this->module .'/'. $this->action;
		if (count($this->vars)) {
			$path .= '/'. implode('/', $this->vars);
		}
		return $path;

	}

	/**
	 * Returns TRUE if request is raw (API) or ajax.
	 */
	public function isRaw(): bool {

		return $this->raw;

	}

	/**
	 * Returns TRUE if log must be printed to user via ajax.
	 */
	public function sendLog(): bool {

		return $this->sendLog;

	}

	/**
	 * If the page number is greater than 1, it returns the content to the first page. To be
	 * used when there are no data to display in the lists with pagination.
	 */
	public static function exceedingPaginationFallback(): void {

		$self = static::$instance;

		if (!$self) return;

		if ($self->getPage() > 1) {
			$self->resetPage();
			$app = Application::getInstance();
			$app->redirect($self->getUrl());
		}

	}

}
