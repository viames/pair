<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

class Pagination {
	
	/**
	 * Active page (1 index).
	 * @var int
	 */
	private $page = 1;
	
	/**
	 * Number of items per page.
	 * @var int
	 */
	private $perPage = 15;
	
	/**
	 * Number of items to paginate.
	 * @var NULL|int
	 */
	private $count = NULL;
	
	/**
	 * Flag per nascondere la barra se con una sola pagina.
	 * @var bool
	 */
	private $hideEmpty = TRUE;
	
	/**
	 * Return start or limit values for SQL queries.
	 * 
	 * @param	string	Desired value name.
	 * 
	 * @return	multitype
	 */
	public function __get($name) {
	
		switch ($name) {
			
			case 'start':
				$val = intval(($this->page - 1) * $this->perPage);
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
	 * @param	string		Property’s name.
	 * @param	multitype	Value.
	 */
	public function __set($name, $value) {
	
		try {
			$this->$name = $value;
		} catch (\Exception $e) {
			print $e->getMessage();
		}
	
	}

	/**
	 * Render and return the navigation bar for pages.
	 * 
	 * @return	string
	 */
	public function render() {
		
		$route = Router::getInstance();
		
		// count can’t be null
		if (!$this->count) {
			return '';
		}
		
		// round the page count
		$pages = ceil((int)$this->count / $this->perPage);
		
		// hide bar in case of 1 page only
		if ($pages < 2 and $this->hideEmpty) {
			return '';
		}
		
		// start main pagination DOM object
		$render = '<div class="pagination"><ul class="pagination">';
		
		// left arrow for first page
		if ($this->page > 1) {
			$render .= '<li class="arrow"><a href="' . $route->getPageUrl(1) . '">⇤</a></li>';
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
				$render .= '<li class="current active"><a href="' . $route->getPageUrl($i) . '">' . $i . '</a></li>';
			} else {
				$render .= '<li><a href="' . $route->getPageUrl($i) . '">' . $i . '</a></li>';
			}
			
		}
		
		// right arrow for last page
		if ($this->page < $pages) {
			$render .= '<li class="arrow"><a href="' . $route->getPageUrl($pages) . '">⇥</a></li>';
		}
		
		// close the bar
		$render .= '</ul></div>';
		
		return $render;
		
	}
	
}
