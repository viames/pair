<?php

namespace Pair\Html;

use Pair\Core\Application;

class Breadcrumb {

	/**
	 * List of all path segments.
	 * @var \stdClass[]
	 */
	protected array $segments = [];

	/**
	 * Flag to remove last URL in getPath().
	 */
	protected bool $lastUrlDisabled = FALSE;

	/**
	 * Singleton object.
	 */
	protected static ?Breadcrumb $instance = NULL;

	/**
	 * Initializes breadcrumb with Home path.
	 */
	private function __construct() {

		$app = Application::getInstance();
		
		$this->segment('Home', BASE_HREF);

	}

	/**
	 * Disables last URL in getPath().
	 */
	public function disableLastUrl(): void {

		$this->lastUrlDisabled = TRUE;

	}

	/**
	 * Returns the path before the last one, or NULL if not available.
	 */
	public function getBackPath(): ?\stdClass {

		if (count($this->segments) > 2) {
			return $this->segments[count($this->segments)-2];
		} else {
			return NULL;
		}

	}

	/**
	 * Returns the singleton instance.
	 */
	public static function getInstance(): self {

		if (is_null(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;

	}

	/**
	 * Returns all path’s segments as array.
	 * 
	 * @return \stdClass[]
	 */
	public function getPath(): array {

		if ($this->lastUrlDisabled) {
			$newPaths = $this->segments;
			end($newPaths)->url = NULL;
			return $newPaths;
		} else {
			return $this->segments;
		}

	}

	/**
	 * Overwrite the standard Home path.
	 */
	public function home(string $title, ?string $url=NULL): void {

		$path			= new \stdClass();
		$path->title	= $title;
		$path->url		= $url;
		$path->active	= count($this->segments) > 1 ? FALSE : TRUE;

		$this->segments[0] = $path;

	}

	/**
	 * Returns title of last item of breadcrumb.
	 */
	public function lastPathTitle(): string {

		$path = end($this->segments);
		return $path->title;

	}

	/**
	 * Adds a new sub-path to Breadcrumb or a list of sub-paths.
	 */
	public static function path(array|string $titleOrList, ?string $url=NULL): void {

		$self = self::getInstance();

		if (is_array($titleOrList)) {

			foreach ($titleOrList as $title => $url) {
				$self->segment($title, $url);
			}

		} else {

			$self->segment($titleOrList, $url);

		}

	}

	/**
	 * Adds a new sub-path to Breadcrumb.
	 */
	public function segment(string $title, ?string $url): void {

		$path			= new \stdClass();
		$path->title	= $title;
		$path->url		= $url;
		$path->active	= TRUE;

		// just last active path will remains active
		foreach ($this->segments as $p) {
			$p->active = FALSE;
		}

		$this->segments[] = $path;

	}

}