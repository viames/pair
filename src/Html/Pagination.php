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
	private ?int $count = NULL;

	/**
	 * Flag to hide bar if one page only.
	 */
	private bool $hideEmpty = TRUE;

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

		// start main pagination DOM object
		$render = '<div class="pagination"><nav aria-label="Page navigation"><ul class="pagination">';

		// left arrow for first page
		if ($this->page > 1) {
			$render .= '<li class="page-item arrow"><a class="page-link" href="' . $router->getPageUrl(1) . '">«</a></li>';
		}

		// calculate page range
		if ($this->page > 5 and $this->page+5 > $pages) {
			$max = $pages;
			$min = ($max-10) > 0 ? $max-10 : 1;
		} else if ($this->page <= 5) {
			$min = 1;
			$max = $pages < 10 ? $pages : 10;
		} else {
			$min = ($this->page-5) > 0 ? $this->page-5 : 1;
			$max = ($this->page+5 <= $pages) ? $this->page+5 : $pages;
		}

		// render all pages number
		for ($i=$min; $i <= $max; $i++) {

			if ($i==$this->page) {
				$render .= '<li class="page-item current active"><a class="page-link" href="' . $router->getPageUrl($i) . '">' . $i . '</a></li>';
			} else {
				$render .= '<li class="page-item"><a class="page-link" href="' . $router->getPageUrl($i) . '">' . $i . '</a></li>';
			}

		}

		// right arrow for last page
		if ($this->page < $pages) {
			$render .= '<li class="page-item arrow"><a class="page-link" href="' . $router->getPageUrl($pages) . '">»</a></li>';
		}

		// close the bar
		$render .= '</ul></nav></div>';

		return $render;

	}

}