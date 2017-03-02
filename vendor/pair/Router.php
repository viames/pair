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
	 * @var string
	 */
	private $module;
	
	/**
	 * Action name.
	 * @var string
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
	 * @var int
	 */
	private $page = 1;

	/**
	 * Current ordering value.
	 * @var int
	 */
	private $order;
	
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
		if (strpos($this->url,$this->baseUrl)===0) {
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
	
	public function __set($name, $value) {
	
		$this->$name = $value;

		/*
		if ('url'==$name) {

			// removes base path from URL
			if ($this->baseUrl and strpos($this->url,$this->baseUrl)===0) {
				$this->url = substr($this->url,strlen($this->baseUrl));
			}
			
			$this->parseUrl();
			
		}
		*/
	
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
	 * Parses an URL and will store parameter values.
	 * 
	 * @return	void
	 */
	private function parseUrl() {		
		
		// the page nr called by URL
		$directPage = NULL;
		
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
					
					case 'api':
						$this->raw = TRUE;
						break;
						
				}

			}

			// set module, action and page nr by parameters
			foreach ($params as $id=>$p) {
				
				switch ($id) {
					
					case 0:
						
						$this->module = urldecode($p);
						break;
						
					case 1:
						
						$this->action = urldecode($p);
						break;
						
					default:
						
						$param = urldecode($p);
						
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
							$directPage = $nr > 0 ? $nr : NULL;
						} else {
							if (''!=$param and !is_null($param)) {
								$this->vars[] = $param;
							}
						}
						break;
	
				}
				
			}
			
			// set log of ajax methods
			if ($this->ajax) {
				$logger = Logger::getInstance();
				$params = array();
				foreach ($this->vars as $key=>$value) {
					$params[] = $key . '=' . Utilities::varToText($value);
				}
				$logger->addEvent(date('Y-m-d H:i:s') . ' AJAX call on ' . $this->module . '/' . $this->action . ' with params ' . implode(', ', $params));
			}
			
		// if empty url, sets default values
		} else {

			$this->module = $this->defaults['module'];
			$this->action = $this->defaults['action'];
		
		}

		/* FIXME
		$cookieName = ucfirst($this->module) . ucfirst($this->action);

		// set a persistent state about pagination
		if ($directPage) {
			
			if ($this->getPage() > 1) {
				$app->setPersistentState($cookieName, $this->page);
			} else {
				$app->unsetPersistentState($cookieName);
			} 
			
		// otherwise load an old pagination state
		} else if ($app->getPersistentState($cookieName)) {
			
			$this->page = $app->getPersistentState($cookieName);
			$app->unsetPersistentState($cookieName);
			$app->logEvent('Page ' . $this->page . ' has been forced by Application');
			
		}
		*/
		
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
