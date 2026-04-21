<?php

namespace Pair\Html;

use Pair\Core\Router;

class Pagination {

	/**
	 * Active page (1 index).
	 */
	private int $page = 1;

	/**
	 * Number of items per page.
	 */
	private int $perPage = 15;

	/**
	 * Number of items to paginate.
	 */
	private ?int $count = null;

	/**
	 * Flag to hide bar if one page only.
	 */
	private bool $hideEmpty = true;

	/**
	 * Return start or limit values for SQL queries.
	 *
	 * @param	string	Desired value name.
	 */
	public function __get(string $name): mixed {

		switch ($name) {

			case 'start':
				$val = intval(($this->page - 1) * $this->perPage);
				if ($val < 0) $val = 0;
				break;

			case 'limit':
				$val = intval($this->perPage);
				break;

			case 'pages':
				$val = ceil((int)$this->count / $this->perPage);
				break;

			// useful for count
			default:
				$val = $this->$name;
				break;

		}

		return $val;

	}

	/**
	 * Set value for any private property.
	 *
	 * @param	string	Property’s name.
	 * @param	mixed	Value.
	 */
	public function __set(string $name, mixed $value): void {

		$this->$name = $value;

	}

	/**
	 * Render and return the navigation bar for pages.
	 */
	public function render(): string {

		$router = Router::getInstance();

		// count can’t be null
		if (!$this->count) {
			return '';
		}

		// round the page count
		$pages = (int)ceil((int)$this->count / $this->perPage);

		// hide bar in case of 1 page only
		if ($pages < 2 and $this->hideEmpty) {
			return '';
		}

		return UiTheme::pagination($router, $this->page, $pages);

	}

}
