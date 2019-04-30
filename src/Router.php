<?php

namespace Pair;

class Router {
	
	/**
	 * Singleton object.
	 * @var Router
	 */
	static private $instance;
	
	/**
	 * Request URL.
	 * @var string
	 */
	private $url;
	
	/**
	 * Base URL for this web application.
	 * @var string
	 */
	private $baseUrl;
	
	/**
	 * Flag that’s true if request is AJAX.
	 * @var bool
	 */
	private $ajax = FALSE;

	/**
	 * Flag that’s true if page will avoid any templating.
	 * @var bool
	 */
	private $raw = FALSE;
	
	/**
	 * Module name.
	 * @var NULL|string
	 */
	private $module;
	
	/**
	 * Action name.
	 * @var NULL|string
	 */
	private $action;
	
	/**
	 * Extended variables.
	 * @var array
	 */
	private $vars = array();
	
	/**
	 * Defautls value when empty URL.
	 * @var array
	 */
	private $defaults = array('module'=>NULL,'action'=>NULL);
	
	/**
	 * Current page number.
	 * @var NULL|int
	 */
	private $page;

	/**
	 * Current ordering value.
	 * @var int
	 */
	private $order;
	
	/**
	 * List of custom routing paths.
	 * @var array
	 */
	private $routes = array();
	
	/**
	 * Flag for show log informations on AJAX calls.
	 * @var bool
	 */
	private $sendLog = TRUE;
	
	/**
	 * Private constructor, called by getInstance() method.
	 */
	private function __construct() {
		
		// get the BASE_URI constant defined in config.php file
		$this->baseUrl = BASE_URI;

		// request URL
		$this->url = $_SERVER['REQUEST_URI'];
		
		// remove baseUrl from URL
		if ($this->baseUrl and strpos($this->url,$this->baseUrl)===0) {
			$this->url = substr($this->url,strlen($this->baseUrl));
		}
		
		// force initial slash
		if ($this->url and '/' != $this->url{0}) $this->url = '/' . $this->url;
		
	}
	
	public function parseRoutes() {
		
		// parse, add and remove from URL any CGI param after question mark
		$this->parseCgiParameters();
		
		// remove special prefixes and return parameters
		$params = $this->getParameters();
		
		// search for routes.php file in the application root
		$custom = $this->parseCustomRoute($params, APPLICATION_PATH . '/routes.php');
		
		if (!$custom and isset($params[0])) {
			
			// set module and define the MODULE_PATH constant
			$this->setModule(urldecode($params[0]));
			
			// search for module specifics custom routes
			$custom = $this->parseCustomRoute($params, APPLICATION_PATH . '/' . MODULE_PATH . 'routes.php', TRUE);

		}
		
		// if custom routes don't match, go for standard
		if (!$custom) {
			$this->parseStandardRoute($params);
		}
			
	}
	
	/**
	 * Checks if there are any GET vars in the URL, adds these to object
	 * vars and removes from URL.
	 */
	private function parseCgiParameters() {
		
		if (FALSE===strpos($this->url, '?')) {
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
	 * remove special prefixes and return parameters.
	 * 
	 * @return	array()
	 */
	private function getParameters() {
		
		$url = '/' == $this->url{0} ? substr($this->url,1) : $this->url;
		
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
	 *
	 * @return	bool
	 */
	private function parseCustomRoute($params, $routesFile, $moduleRoute=FALSE) {
		
		$this->routes = array();
		
		// trigger for route processed
		$routeMatches = FALSE;
			
		// check if controller file exists
		if (!file_exists($routesFile)) {
			return FALSE;
		}
			
		// read the custom routes by php file
		require $routesFile;
		
		// check about the third parameter $module in the custom-routes available as of now
		foreach ($this->routes as $r) {
			
			// force initial slash
			if (!$r->path) {
				$r->path = '/';
			} else if ('/' != $r->path{0}) {
				$r->path = '/' . $r->path;
			}
			
			// add module prefix if not set
			if ($moduleRoute and strpos($r->path,'/'.$this->module) !== 0) {
				$r->path = '/' . $this->module . $r->path;
			}

			// replace any regex after param name in path
			$pathRegex = preg_replace('|/:[^/(]+\(([^)]+)\)|', '/($1)', $r->path);
			
			// then replace simple param name in path
			$pathRegex = preg_replace('|/(:[^/]+)|', '/([^/]+)', $pathRegex);
			
			// compare current URL to regex
			if (preg_match('|^' . $pathRegex . '$|', $this->url)) {
				
				// assign action
				$this->action = $r->action;
				
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
				$variables = array();
				
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
		
		return $routeMatches;
		
	}
	
	/**
	 * Populates router variables by standard parameter login.
	 *
	 * @param	array:string	List of URL parameters.
	 */
	private function parseStandardRoute($params) {
		
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
	 * 
	 * @return Router
	 */
	public static function getInstance() {
	
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
	
		return self::$instance;
	
	}

	/**
	 * Will returns property’s value if set. Throw an exception and returns NULL if not set.
	 *
	 * @param	string	Property’s name.
	 * 
	 * @throws	Exception
	 * 
	 * @return	mixed|NULL
	 */
	public function __get($name) {
	
		try {
	
			if (!property_exists($this, $name)) {
				throw new \Exception('Parameter “'. $name .'” was not set');
			}
			
			return $this->$name;
	
		} catch (\Exception $e) {
				
			trigger_error($e->getMessage());
			return NULL;
	
		}
	
	}
	
	/**
	 * Set a property of this object.
	 * 
	 * @param	string	Property’s name.
	 * @param	mixed
	 */
	public function __set($name, $value) {
		
		$this->$name = $value;
		
	}
	
	/**
	 * Return an URL parameter, if exists. Exclude routing base params (module and action).
	 *
	 * @param	mixed	Parameter position (zero based) or Key name.
	 * @param	bool	Flag to decode a previously encoded value as char-only.
	 *
	 * @return	string|NULL
	 */
	public static function get($paramIdx, $decode=FALSE) {
		
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
	 *  
	 * @return	string
	 */
	public function getParam($paramIdx, $decode=FALSE) {
		
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
	public function setParam($paramIdx=NULL, $value, $encode=FALSE) {
		
		if ($encode) {
			$value = rtrim(strtr(base64_encode(gzdeflate(json_encode($value), 9)), '+/', '-_'), '=');
		}
		
		if (!is_null($paramIdx)) {
			$this->vars[$paramIdx] = $value;
		} else {
			$this->vars[] = $value;
		}
		
	}
	
	/**
	 * Delete all parameters.
	 */
	public function resetParams() {
		
		$this->vars = array();
		
	}
	
	/**
	 * Return the current list page number.
	 * 
	 * @return	int
	 */
	public function getPage() {
		
		$cookieName = Application::getCookiePrefix() . ucfirst($this->module) . ucfirst($this->action);
		
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
	public function setPage($number) {

		$number = (int)$number;
		
		$this->page = $number;

		// the cookie about persistent state
		$cookieName = Application::getCookiePrefix() . ucfirst($this->module) . ucfirst($this->action);
		
		// set the persistent state
		setcookie($cookieName, $number, NULL, '/');
		
	}
	
	/**
	 * Reset page number to 1.
	 */
	public function resetPage() {
		
		$this->page = 1;

		// the cookie about persistent state
		$cookieName = Application::getCookiePrefix() . ucfirst($this->module) . ucfirst($this->action);
		
		// unset the persistent state
		if (isset($_COOKIE[$cookieName])) {
			unset($_COOKIE[$cookieName]);
			setcookie($cookieName, '', -1, '/');
		}
		
	}
	
	/**
	 * Set the current module name and define the MODULE_PATH constant.
	 * 
	 * @param	string	Module name.
	 */
	public function setModule($moduleName) {
	
		if (!defined('MODULE_PATH')) {
			
			$this->module = $moduleName;
		
			define('MODULE_PATH', 'modules/' . $this->module . '/');
			
		}
	
	}
	
	public function setAction($action) {
	
		$this->action = $action;
	
	}
	
	/**
	 * Return action string.
	 * 
	 * @return	string
	 */
	public function getAction() {
	
		return $this->action;
	
	}
	
	/**
	 * Sets the default module and action, useful when module is missing in the URL.
	 * 
	 * @param	string	Default module name.
	 * @param	string	Default action.
	 */
	public function setDefaults($module, $action) {
		
		$this->defaults['module'] = $module;
		$this->defaults['action'] = $action;
		
	}
	
	/**
	 * Returns URL of default module + default action.
	 * 
	 * @return string
	 */
	public function getDefaultUrl() {
		
		return $this->defaults['module'] . '/' . $this->defaults['action'];
		
	}

	/**
	 * Set page to be viewed with no template, useful for ajax requests and API.
	 */
	public static function setRaw() {
		
		try {
			self::$instance->raw = TRUE;
		} catch(\Exception $e) {
			die('Router instance has not been created yet');
		}
		
	}
		
	/**
	 * Add a new route path.
	 *
	 * @param	string		Path with optional variable placeholders.
	 * @param	string		Action.
	 * @param	string|NULL	Optional module name.
	 */
	public static function addRoute($path, $action, $module=NULL) {
		
		$route = new \stdClass();
		$route->path	 = $path;
		$route->action	 = $action;
		$route->module	 = $module;

		try {
			self::$instance->routes[] = $route;
		} catch(\Exception $e) {
			die('Router instance has not been created yet');
		}		
		
	}

	/**
	 * Returns current relative URL, with order and optional pagination.
	 * 
	 * @return	string
	 */
	public function getUrl() {
		
		$sefParams = array();
		$cgiParams = array();
		
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
	 * @param	int		Optional order value to build the URL with.
	 * 
	 * @return	string
	 */
	public function getOrderUrl($val=NULL) {
		
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
	 * @param	int		Optional page number to build the URL with.
	 *
	 * @return	string
	 */
	public function getPageUrl($page=NULL) {
	
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
	 * 
	 * @return	string
	 */
	public function __toString() {
		
		$path = $this->module .'/'. $this->action;
		if (count($this->vars)) {
			$path .= '/'. implode('/', $this->vars);
		}
		return $path;
		
	}

	/**
	 * Returns TRUE if request is raw (API) or ajax.
	 * 
	 * @return boolean
	 */
	public function isRaw() {
		
		return $this->raw;
		
	}
	
	/**
	 * Returns TRUE if log must be printed to user via ajax.
	 *
	 * @return boolean
	 */
	public function sendLog() {
	
		return $this->sendLog;
	
	}
	
	/**
	 * If the page number is greater than 1, it returns the content to the first page. To be
	 * used when there are no data to display in the lists with pagination.
	 */
	public static function exceedingPaginationFallback() {
		
		$self = static::$instance;
		
		if (!$self) return NULL;
		
		if ($self->page > 1) {
			$self->resetPage();
			$app = Application::getInstance();
			$app->redirect($self->getUrl());
		}
		
	}

}
