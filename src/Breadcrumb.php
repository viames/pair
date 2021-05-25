<?php

namespace Pair;

class Breadcrumb {

	/**
	 * List of all paths.
	 * @var \stdClass[]
	 */
	protected $paths = [];

	/**
	 * Flag to remove last URL in getPaths().
	 * @var bool
	 */
	protected $lastUrlDisabled = FALSE;

	/**
	 * Singleton object.
	 * @var Breadcrumb
	 */
	protected static $instance = NULL;

	/**
	 * Initializes breadcrumb with Home path.
	 */
	private function __construct() {
		
		$app = Application::getInstance();
		
		// add user-landing path if user is available
		if (is_a($app->currentUser, 'Pair\User')) {
			$landing = $app->currentUser->getLanding();
			$resource = $landing->module . '/' . $landing->action;
			$this->addPath('Home', $resource);
		}

	}
	
	/**
	 * Returns singleton object.
	 *
	 * @return	Breadcrumb
	 */
	public static function getInstance(): self {
	
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
	
		return self::$instance;
	
	}
	
	/**
	 * Adds a new sub-path to Breadcrumb.
	 * 
	 * @param	string	Title of the sub-path.
	 * @param	string	Destination URL (default NULL).
	 */
	public static function path(string $title, string $url=NULL): void {

		$self = self::getInstance();
		
		$path			= new \stdClass();
		$path->title	= $title;
		$path->url		= $url;
		$path->active	= TRUE;

		// just last active path will remains active
		foreach ($self->paths as $p) {
			$p->active = FALSE;
		}
		
		$self->paths[]	= $path;
		
	}
	
	/**
	 * Adds a new sub-path to Breadcrumb. Chainable method.
	 * 
	 * @param	string	Title of the sub-path.
	 * @param	string	Destination URL (default NULL).
	 * @return	Breadcrumb
	 * @deprecated Use Breadcrumb::path() instead
	 */
	public function addPath($title, $url=NULL) {
		
		$path			= new \stdClass();
		$path->title	= $title;
		$path->url		= $url;
		$path->active	= TRUE;

		// just last active path will remains active
		foreach ($this->paths as $p) {
			$p->active = FALSE;
		}
		
		$this->paths[]	= $path;
		
		return $this;
	
	}
	
	/**
	 * Returns all paths as array.
	 * 
	 * @return \stdClass[]
	 */
	public function getPaths(): array {
		
		if ($this->lastUrlDisabled) {
			$newPaths = $this->paths;
			end($newPaths)->url = NULL;
			return $newPaths;
		} else {
			return $this->paths;
		}
		
	}
	
	/**
	 * Overwrite the standard Home path.
	 * 
	 * @param	string	Path title.
	 * @param	string	Optional URL.
	 */
	public function setHome($title, $url=NULL): void {
		
		$path			= new \stdClass();
		$path->title	= $title;
		$path->url		= $url;
		$path->active	= count($this->paths) > 1 ? FALSE : TRUE;
		
		$this->paths[0] = $path;
		
	}
	
	/**
	 * Returns title of last item of breadcrumb.
	 * 
	 * @return	string
	 */
	public function getLastPathTitle(): string {
		
		$path = end($this->paths);
		return $path->title;
		
	}
	
	public function disableLastUrl(): void {
		
		$this->lastUrlDisabled = TRUE;
		
	}
	
	public function getBackPath(): ?\stdClass {
		
		if (count($this->paths) > 2) {
			return $this->paths[count($this->paths)-2];
		} else {
			return NULL;
		}
		
	}

}