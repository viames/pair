<?php

namespace Pair\Core;

use Pair\Exceptions\ErrorCodes;

/**
 * Router class that parses the URL and determines the module, action and parameters for the request.
 * It also supports custom routing rules defined in separate files.
 */
class Router {

	/**
	 * Singleton object.
	 */
	static private Router $instance;

	/**
	 * Request URL.
	 */
	private ?string $url = null;

	/**
	 * Flag that’s true if request is AJAX.
	 */
	private bool $ajax = false;

	/**
	 * Flag that’s true if page will avoid any templating.
	 */
	private bool $raw = false;

	/**
	 * Module name.
	 */
	private ?string $module = null;

	/**
	 * Action name.
	 */
	private ?string $action = null;

	/**
	 * Extended variables.
	 */
	private array $vars = [];

	/**
	 * Defautls value when empty URL.
	 */
	private array $defaults = ['module' => null,'action' => null];

	/**
	 * Current page number.
	 */
	private ?int $page = null;

	/**
	 * Current ordering value.
	 */
	private ?int $order = null;

	/**
	 * Flag that’s true if the order has been changed.
	 */
	private bool $orderChanged = false;

	/**
	 * List of custom routing paths.
	 */
	private array $routes = [];

	/**
	 * Flag for show log informations on AJAX calls.
	 */
	private bool $sendLog = true;

	/**
	 * Private constructor, called by getInstance() method.
	 */
	private function __construct() {

		// request URL, null for CLI
		$this->url = Application::isCli() ? null : $_SERVER['REQUEST_URI'];

		// remove URL_PATH from URL
		if (URL_PATH and (is_null($this->url) or strpos((string)$this->url,URL_PATH)===0)) {
			$this->url = substr((string)$this->url,strlen(URL_PATH));
		}

		// force initial slash
		if ($this->url and '/' != $this->url[0]) $this->url = '/' . $this->url;

	}

	/**
	 * Returns property’s value or null.
	 *
	 * @param string $name Property’s name.
	 * @throws \Exception If property doesn’t exist.
	 */
	public function __get(string $name): mixed {

		if (!property_exists($this, $name)) {
			throw new \Exception('Property “'. $name .'” doesn’t exist for '. get_called_class(), ErrorCodes::PROPERTY_NOT_FOUND);
		}
		
		return isset($this->$name) ? $this->$name : null;
	
	}

	/**
	 * Set a property of this object.
	 *
	 * @param string $name Property’s name.
	 * @param mixed $value Property’s value.
	 */
	public function __set(string $name, mixed $value): void {

		$this->$name = $value;

	}

	/**
	 * Print the calculated URL based on parameters.
	 */
	public function __toString(): string {

		return $this->getUrl();

	}

	/**
	 * Add a new route path.
	 *
	 * @param string $path Path with optional variable placeholders.
	 * @param string $action Action.
	 * @param string|null $module Optional module name.
	 * @param bool|null $raw Optional raw flag.
	 */
	public static function addRoute(string $path, string $action, ?string $module = null, ?bool $raw = false): void {

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

		if (!self::hasInstance()) {
			die('Router instance has not been created yet');
		}

		self::$instance->routes[] = $route;

	}

	/**
	 * If the page number is greater than 1, it returns the content to the first page. To be
	 * used when there are no data to display in the lists with pagination.
	 */
	public static function exceedingPaginationFallback(): void {

		if (!self::hasInstance()) return;

		$self = self::$instance;

		if ($self->getPage() > 1) {
			$self->resetPage();
			$app = Application::getInstance();
			$app->redirect($self->getUrl());
		}

	}

	/**
	 * Return an URL parameter, if exists. Exclude routing base params (module and action).
	 *
	 * @param mixed	$paramIdx Parameter position (zero based) or Key name.
	 * @param bool	$decode Flag to decode a previously encoded value as char-only.
	 * @return string|array|null The parameter value or null if not found.
	 */
	public static function get(int|string $paramIdx, bool $decode = false): string|array|null {

		if (!self::hasInstance()) return null;

		$self = self::$instance;

		return $self->getStoredParam($paramIdx, $decode);

	}

	/**
	 * Return the action string.
	 * 
	 * @return string|null The action string or null if not set.
	 */
	public function getAction(): ?string {

		return $this->action;

	}

	/**
	 * Returns URL of default module + default action.
	 * 
	 * @return string The URL of default module and action, in the format "module/action".
	 */
	public function getDefaultUrl(): string {

		return $this->defaults['module'] . '/' . $this->defaults['action'];

	}

	/**
	 * Return the singleton instance of the Router class, creating it if it doesn’t exist.
	 * 
	 * @return Router The singleton instance of the Router class.
	 */
	public static function getInstance(): self {

		if (!isset(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;

	}

	/**
	 * Return true when the singleton has already been initialized.
	 */
	private static function hasInstance(): bool {

		return isset(self::$instance);

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

		return null;

	}

	/**
	 * Proxy method to get the current URL with a different order value. If null param, will
	 * reset ordering. When the requested order differs from the current one, pagination is
	 * removed from the generated URL so the list restarts from the first page.
	 *
	 * @param	int|null	Optional order value to build the URL with.
	 */
	public function getOrderUrl(?int $val = null): string {

		// save current order val
		$tmp = $this->order;
		$tmpPage = $this->page;

		$currentOrder = (int)($tmp ?? 0);
		$targetOrder = (int)($val ?? 0);

		// build url with order value in param
		$this->order = $val;
		if ($currentOrder !== $targetOrder) {
			// A different order must always start from the first page.
			$this->page = null;
		}
		$url = $this->getUrl();
		$this->order = $tmp;
		$this->page = $tmpPage;

		return $url;

	}

	/**
	 * Decode a URL-safe encoded parameter value produced by setParam().
	 */
	private function decodeParamValue(string $value): ?string {

		$compressed = base64_decode(strtr($value, '-_', '+/'), true);

		if (false === $compressed) {
			return null;
		}

		$decoded = @gzinflate($compressed);

		if (false === $decoded) {
			return null;
		}

		$json = json_decode($decoded);

		if (is_null($json)) {
			return null;
		}

		return (string)$json;

	}

	/**
	 * Encode one path segment for safe use in generated URLs.
	 */
	private function encodePathSegment(mixed $value): string {

		return rawurlencode((string)$value);

	}

	/**
	 * Return the current page number, checking if it’s set in the URL or in a cookie for persistent state.
	 * If not set or invalid, it defaults to 1.
	 * 
	 * @return int The current page number, defaulting to 1 if not set or invalid.
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
	 * Special method to get the current URL with a different page number. If null param, will
	 * reset pagination.
	 *
	 * @param int|null $page Optional page number to build the URL with.
	 * @return string The URL with the specified page number, or with pagination reset if null is passed.
	 */
	public function getPageUrl(?int $page = null): string {

		// save current page val
		$tmp = $this->page;

		// build url with page value in param
		$this->page = (int)$page;
		$url = $this->getUrl();
		$this->page = $tmp;

		return $url;

	}

	/**
	 * Return an URL parameter, if exists. It escludes routing base params (module and action).
	 *
	 * @param mixed $paramIdx Parameter position (zero based) or Key name.
	 * @param bool $decode Flag to decode a previously encoded value as char-only before returning it.
	 * @return string|array|null The parameter value or null if not found.
	 */
	public function getParam(int|string $paramIdx, bool $decode = false): string|array|null {

		return $this->getStoredParam($paramIdx, $decode);

	}

	/**
	 * Return an array of URL parameters, excluding routing base params (module and action).
	 * 
	 * @return string[] An array of URL parameters, excluding routing base params (module and action).
	 * Each parameter is a string, and the array keys are the parameter positions (zero based) or key names.
	 */
	private function getParameters(): array {

		$url = ($this->url and '/' == $this->url[0]) ? substr($this->url,1) : (string)$this->url;

		// split parameters by slash
		return explode('/', $url);

	}

	/**
	 * Return a stored parameter value after applying Pair's optional transport decoding.
	 */
	private function getStoredParam(int|string $paramIdx, bool $decode = false): string|array|null {

		if (!array_key_exists($paramIdx, $this->vars)) {
			return null;
		}

		$value = $this->vars[$paramIdx];

		if (is_array($value)) {
			return $value;
		}

		if ('' == $value) {
			return null;
		}

		if ($decode) {
			return $this->decodeParamValue((string)$value);
		}

		return (string)$value;

	}

	/**
	 * Return the current relative URL, with order and optional pagination, in the format "module/action/param1/param2?key=value".
	 * 
	 * @return string The current relative URL.
	 */
	public function getUrl(): string {

		$sefParams = [];
		$cgiParams = [];

		// queue all parameters
		foreach ($this->vars as $key=>$val) {
			if (is_int($key)) {
				$sefParams[] = $this->encodePathSegment($val);
			} else {
				$cgiParams[$key] = $val;
			}
		}

		$url = $this->encodePathSegment($this->module) . '/' . $this->encodePathSegment($this->action);

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
			$url .= '?' . http_build_query($cgiParams, '', '&', PHP_QUERY_RFC3986);
		}

		return $url;

	}

	/**
	 * Checks if there are any GET vars in the URL, adds these to object
	 * vars and removes from URL.
	 */
	private function parseCgiParameters(): void {

		if (false === strpos((string)$this->url, '?')) {
			return;
		}

		$temp = explode('?', (string)$this->url, 2);
		$this->url = $temp[0];

		if (!array_key_exists(1, $temp) or '' === $temp[1]) {
			return;
		}

		$queryParams = [];
		parse_str($temp[1], $queryParams);

		// Store decoded CGI parameters so getUrl() can safely encode them once.
		foreach ($queryParams as $key => $value) {
			$this->setParam((string)$key, $value);
		}

	}

	/**
	 * Detect and remove legacy route mode prefixes such as /raw and /ajax from the current URL.
	 */
	private function parseRouteModePrefixes(): void {

		if (!$this->url) {
			return;
		}

		// Multiple prefixes are accepted for legacy URLs such as /raw/ajax/module/action.
		while (true) {

			if ($this->consumeRouteModePrefix('raw')) {
				continue;
			}

			if ($this->consumeRouteModePrefix('ajax')) {
				continue;
			}

			break;

		}

	}

	/**
	 * Parse an URL searching for a custom route that matches and store parameter values.
	 *
	 * @param string[] $params List of URL parameters.
	 * @param string $routesFile Path to routes file.
	 * @param bool $moduleRoute Flag to set as module routes.
	 */
	private function parseCustomRoutes(array $params, string $routesFile, bool $moduleRoute = false): bool {

		// Skip route imports quickly when the file was already found missing.
		if (!FilesystemMetadata::fileExists($routesFile)) {
			return false;
		}

		// trigger for route processed
		$routeMatches = false;

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
				$this->raw = $this->raw or (bool)($r->raw ?? false);

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
						$variables[$pos] = false === $regexPos ?
							substr($part,1) :
							substr($part,1,$regexPos-1);

					}

				}

				// assign params to vars array and set page, order and log
				foreach ($params as $pos => $value) {

					// Keep custom-route parameters normalized like standard routes.
					$param = urldecode($value);

					// flag to not send back log (useful for AJAX)
					if ('noLog' == $param) {

						$this->sendLog = false;

					// ordering
					} else if ('order-' == substr($param, 0, 6)) {

						$nr = intval(substr($param, 6));
						if ($nr) $this->setOrder($nr);

					// pagination
					} else if ('page-' == substr($param, 0, 5)) {

						$nr = intval(substr($param, 5));
						$this->setPage($nr);

					// create a var with parsed name
					} else if (isset($variables[$pos]) and $variables[$pos]) {

						$this->vars[$variables[$pos]] = $param;

					// otherwise assign only dynamic or unmatched segments by index position
					} else if (!isset($parts[$pos]) || substr($parts[$pos], 0, 1) == ':') {

						$this->vars[$pos] = $param;

					}

				}

				$routeMatches = true;

				break;

			}

		}

		// restore the Router::routes array property
		$this->routes = array_merge($routesBackup, $this->routes);

		return $routeMatches;

	}

	/**
	 * Parse the URL and populate the Router object properties with module, action and variables.
	 */
	public function parseRoutes(): void {

		$span = Observability::start('router.parse');

		try {

			// parse, add and remove from URL any CGI param after question mark
			$this->parseCgiParameters();

			// parse and remove legacy route mode prefixes before matching routes
			$this->parseRouteModePrefixes();

			// remove special prefixes and return parameters
			$params = $this->getParameters();

			// try matches in /root/routes.php file
			$routeMatches1 = $this->parseCustomRoutes($params, APPLICATION_PATH . '/routes.php');

			// set module and define the MODULE_PATH constant
			if (!defined('MODULE_PATH') and isset($params[0])) {
				$this->setModule(urldecode($params[0]));
			}

			$routeMatches2 = false;

			// try matches in module specifics routes.php file
			if (defined('MODULE_PATH')) {
				$routeMatches2 = $this->parseCustomRoutes($params, APPLICATION_PATH . '/' . MODULE_PATH . 'routes.php', true);
			}

			// if custom routes don't match, go for standard
			if (!$routeMatches1 and !$routeMatches2) {
				$this->parseStandardRoutes($params);
			}

			Observability::finish($span, [
				'module' => $this->module,
				'action' => $this->action,
				'customRoute' => $routeMatches1 || $routeMatches2,
			]);

		} catch (\Throwable $e) {

			Observability::finish($span, [
				'exception' => get_class($e),
			], 'error');
			throw $e;

		}

	}

	/**
	 * Populates router variables by standard parameter login.
	 *
	 * @param string[] $params List of URL parameters.
	 */
	private function parseStandardRoutes(array $params): void {

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

						$this->sendLog = false;
					
					// ordering
					} else if ('order-' == substr($param, 0, 6)) {
					
						$nr = intval(substr($param, 6));
						if ($nr) $this->setOrder($nr);
					
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
	 * Delete all parameters.
	 */
	public function resetParams(): void {

		$this->vars = [];

	}

	/**
	 * Compare a custom route path as text or regex to the param URL and
	 * return true if it matches.
	 *
	 * @param string $path The custom Route path.
	 * @param string $url The URL to check.
	 */
	public function routePathMatchesUrl(string $path, string $url): bool {

		$pathRegex = $this->routePathToRegex($path);
		$cleanUrl = $this->stripRouteModePrefixes($url);

		return 1 === preg_match($pathRegex, $cleanUrl);

	}

	/**
	 * Consume one route mode prefix from the current URL and update the related flag.
	 */
	private function consumeRouteModePrefix(string $prefix): bool {

		$match = '/' . $prefix;

		if ($this->url !== $match and !str_starts_with((string)$this->url, $match . '/')) {
			return false;
		}

		if ('raw' == $prefix) {
			$this->raw = true;
		} else if ('ajax' == $prefix) {
			$this->ajax = true;
		}

		$this->url = substr((string)$this->url, strlen($match));
		$this->url = '' === $this->url ? '/' : $this->url;

		return true;

	}

	/**
	 * Build a safe regular expression for a custom route path.
	 */
	private function routePathToRegex(string $path): string {

		$delimiter = '~';

		if ('' == $path) {
			$path = '/';
		} else if ('/' != $path[0]) {
			$path = '/' . $path;
		}

		if ('/' == $path) {
			return $delimiter . '^/$' . $delimiter;
		}

		$segments = explode('/', trim($path, '/'));
		$regexSegments = [];

		foreach ($segments as $segment) {

			if (preg_match('/^:([^\/(]+)(?:\((.+)\))?$/', $segment, $matches)) {
				$constraint = isset($matches[2]) ? str_replace($delimiter, '\\' . $delimiter, $matches[2]) : null;
				$regexSegments[] = $constraint ? '(' . $constraint . ')' : '([^/]+)';
			} else {
				$regexSegments[] = preg_quote($segment, $delimiter);
			}

		}

		$regex = $delimiter . '^/' . implode('/', $regexSegments);

		if (str_ends_with($path, '/')) {
			$regex .= '/';
		}

		return $regex . '$' . $delimiter;

	}

	/**
	 * Returns true if log must be printed to user via ajax.
	 * 
	 * @return bool True if log must be printed to user via ajax, false otherwise.
	 */
	public function sendLog(): bool {

		return $this->sendLog;

	}

	/**
	 * Set the action
	 *
	 * @param  string|null	Action string or null.
	 */
	public function setAction(?string $action): void {

		$this->action = $action;

	}

	/**
	 * Sets the default module and action, useful when module is missing in the URL.
	 *
	 * @param string $module Default module name.
	 * @param string $action Default action.
	 */
	public function setDefaults(string $module, string $action): void {

		$this->defaults['module'] = $module;
		$this->defaults['action'] = $action;

	}

	/**
	 * Set the current module name and define the MODULE_PATH constant.
	 *
	 * @param string $moduleName Module name.
	 */
	public function setModule(string $moduleName): void {

		if (!defined('MODULE_PATH')) {

			$this->module = $moduleName;

			define('MODULE_PATH', 'modules/' . $this->module . '/');

		}

	}

	/**
	 * Set the current ordering value and reset pagination if different from referer.
	 * 
	 * @param int $order Ordering value.
	 */
	public function setOrder(int $order): void {
	
		$this->order = $order;

		// Reset persisted pagination when the request switches to a different order.
		if (isset($_SERVER['HTTP_REFERER'])) {
			if (preg_match('/\/order-(\d+)/', $_SERVER['HTTP_REFERER'], $matches)) {
				$oldOrder = (int)$matches[1];
				if ($oldOrder !== $order) {
					$this->orderChanged = true;
					$this->resetPage();
				}
			}
		}
	
	}

	/**
	 * Set a persistent state as current pagination index unless a previous order change already
	 * reset the current list to page 1.
	 *
	 * @param int $number Page number.
	 */
	public function setPage(int $number): void {

		$number = (int)$number;

		if ($this->orderChanged) {
			// Ignore stale page values carried over by URLs generated before the order change.
			$this->resetPage();
			return;
		}

		$this->page = $number;

		// the cookie about persistent state
		$cookieName = Application::getCookiePrefix() . ucfirst((string)$this->module) . ucfirst((string)$this->action);

		// set the persistent state, lifetime is 30 days
		setcookie($cookieName, $number, Application::getCookieParams(time() + 2592000));

	}

	/**
	 * Add a param value to the URL, on index position if given and existent.
	 *
	 * @param mixed $paramIdx Zero based position on URL path or Key name.
	 * @param string|array $value Value to add.
	 * @param bool $encode Flag to encode as char-only the value.
	 */
	public function setParam(mixed $paramIdx, string|array $value, bool $encode = false): void {

		if ($encode and is_string($value)) {
			$value = rtrim(strtr(base64_encode(gzdeflate(json_encode($value), 9)), '+/', '-_'), '=');
		}

		$this->vars[$paramIdx] = $value;

	}

	/**
	 * Remove legacy route mode prefixes from an arbitrary URL without changing router state.
	 */
	private function stripRouteModePrefixes(string $url): string {

		foreach (['raw', 'ajax'] as $prefix) {

			$match = '/' . $prefix;

			if ($url === $match or str_starts_with($url, $match . '/')) {
				$url = substr($url, strlen($match));
				$url = '' === $url ? '/' : $url;
				return $this->stripRouteModePrefixes($url);
			}

		}

		return $url;

	}

}
