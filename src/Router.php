<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

class Router {
	
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

		$this->baseUrl = BASE_URI;

		// request URL
		$this->url = $_SERVER['REQUEST_URI'];
		
		// remove baseUrl from URL
		if ($this->baseUrl and strpos($this->url,$this->baseUrl)===0) {
			$this->url = substr($this->url,strlen($this->baseUrl));
		}
		
		// remove trail slash, if any
		if ('/'==$this->url{0}) $this->url = substr($this->url,1);
			
		$this->parseUrl();

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
	 * Will deletes all parameters.
	 */
	public function resetParams() {
	
		$this->vars = array();
	
	}
	
	/**
	 * Returns pagination page number.
	 * 
	 * @return int
	 */
	public function getPage() {
		
		return ((int)$this->page > 0 ? $this->page : 1);
		
	}

	/**
	 * Sets page number.
	 * 
	 * @param	int		Page number.
	 */
	public function setPage($num) {
	
		if (intval($num) > 0) {
			$this->page = (int)$num;
		}
	
	}
	
	public function setModule($module) {
	
		$this->module = $module;
	
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
	 * Parses an URL and will store parameter values.
	 * 
	 * @return	void
	 */
	private function parseUrl() {		
		
		if ($this->url) {
			
			// will parse, add and removes from URL any CGI param after question mark
			$this->parseCgiParameters();
		
			// parsing SEF parameters
			$params = explode('/',$this->url);
			
			// reveal AJAX calls or other raw requests and cuts special word from URL
			if (array_key_exists(0,$params)) {
				
				switch ($params[0]) {
					
					case 'ajax':
						$this->ajax = $this->raw = TRUE;
 						array_shift($params);
						break;
						
					case 'raw':
						$this->raw = TRUE;
 						array_shift($params);
						break;
					
					default:
						
						// recognize module
						$this->module = urldecode($params[0]);
						
						// define module constant
						define('MODULE_PATH', 'modules/' . $this->module . '/');
												
						break;
						
				}

			}
			
			// trigger for route processed
			$routeProcessed = FALSE;
			
			// search for custom routes
			$routesFile = MODULE_PATH . 'routes.php';

			// check if controller file exists 
			if (file_exists($routesFile)) {
				
				// read the custom routes by php file
				require $routesFile;
				
				// search for the first route that matches in inverse order
				foreach (array_reverse(self::$instance->routes) as $r) {
					
					// replace any regex after param name in path
					$pathRegex = preg_replace('|/:[^/(]+\(([^)]+)\)|', '/($1)', $r->path);

					// then replace simple param name in path
					$pathRegex = preg_replace('|/(:[^/]+)|', '/([^/]+)', $pathRegex);
					
					// compare current URL to regex
					if (preg_match('|^' . $this->module . $pathRegex . '$|', $this->url)) {

						// assign action
						$this->action = $r->action;

						// assign even module
						if (is_null($this->module)) {
							$this->module = $r->module;
						}
						
						// split the route path
						$parts = explode('/', $r->path);
						
						// initialize array of temporary variables
						$variables = array();
						
						// store the variables found with name and position
						foreach ($parts as $pos => $part) {

							// remove the braces that surround the variable name
							if (substr($part,0,1) == ':') {
								
								$regexPos = strpos($part, '(');
								
								// remove any regex after param name
								$variables[$pos] = FALSE === $regexPos ?
									substr($part,1) :
									substr($part,1,$regexPos-1);
								
							}
							
						}
						
						// assign params to vars array
						foreach ($params as $pos => $value) {

							if (isset($variables[$pos])) {
								
								// create a var with parsed name 
								$this->vars[$variables[$pos]] = $value;
								
							} else {
								
								// otherwise assign the param by its index position
								$this->vars[$pos] = $value;
								
							}
							
						}
						
						$routeProcessed = TRUE;
						
						break;
						
					}
					
				}
				
			}

			// if still not processed, go to conventional way
			if (!$routeProcessed) {
				
				// set module, action and page nr by parameters
				foreach ($params as $pos => $value) {
					
					switch ($pos) {
	
						case 0:
							// module is already detected
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
			
		}
		
	}

	/**
	 * Returns current URL, with order and optional pagination.
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
	 * Proxy method to get the current URL with a different page number. If NULL param, will
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
		$this->page = $page;
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
	 * Checks if there are any GET vars in the URL, adds these to object
	 * vars and removes from URL.
	 */ 
	private function parseCgiParameters() {
		
		if (FALSE!=strpos($this->url, '?')) {
			
			$temp = explode('?', $this->url);
			$this->url = $temp[0];
			
			if (array_key_exists(1, $temp)) {
				
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
			
		}
		
	}
	
}
