<?php

namespace Pair;

class Menu {

	/**
	 * Item object list.
	 * @var array
	 */
	protected $items = array();

	/**
	 * Add a Title item to menu.
	 * 
	 * @param	string	Title text.
	 */
	public function addTitle(string $title) {

		$item 			= new \stdClass();
		$item->type		= 'title';
		$item->title	= $title;
		$this->items[]	= $item;

	}

	/**
	 * Adds a single-item menu entry. The optional badge can be a subtitle. 
	 * 
	 * @param	string	Url of item.
	 * @param	string	Title shown.
	 * @param	string	Optional, can be an icon, a subtitle or icon placeholder.
	 * @param	string	Optional, is CSS extra class definition.
	 * @param	string	Optional, the anchor target.
	 * @param	string	Optional, the badge type as Bootstrap class (ex. primary, info, danger etc.).
	 */
	public function addItem(string $url, string $title, string $badge=NULL, string $class=NULL, string $target=NULL, string $badgeType=NULL) {

		$this->items[]	= self::getItemObject($url, $title, $badge, $class, $target, $badgeType ?? 'primary');

	}

	/**
	 * Adds a multi-entry menu item. The list is array of single-item objects.
	 * 
	 * @param	string	Title for this item.
	 * @param	array	List of single-item objects
	 * @param	string	Optional, can be an icon, a subtitle or icon placeholder.
	 */
	public function addMulti(string $title, array $list, string $class=NULL) {
	
		$multi 			= new \stdClass();
		$multi->type	= 'multi';
		$multi->title	= $title;
		$multi->class	= $class;
		$multi->list	= array();
		foreach ($list as $i) {
			$multi->list[]	= $i;
		}

		$this->items[] = $multi;

	}
	
	/**
	 * Adds a separator to menu.
	 * 
	 * @param	string	Separator title. Optional.
	 */
	public function addSeparator(string $title=NULL) {
	
		$item 			= new \stdClass();
		$item->type		= 'separator';
		$item->title	= $title;
		$this->items[]	= $item;
	
	}
	
	/**
	 * Static method utility to create single-item object.
	 *  
	 * @param	string	Url of item.
	 * @param	string	Title shown.
	 * @param	string	Optional, can be an icon, a subtitle or icon placeholder.
	 * @param	string	Optional, is CSS extra class definition.
	 * @param	string	Optional, the anchor target.
	 * @param	string	Optional, the badge type as Bootstrap class (ex. primary, info, error etc.).
	 * 
	 * @return	\stdClass
	 */
	public static function getItemObject(string $url, string $title, string $badge=NULL, string $class=NULL, string $target=NULL, string $badgeType=NULL): \stdClass {

		$item 			= new \stdClass();
		$item->type		= 'single';
		$item->url		= $url;
		$item->title	= $title;
		$item->badge	= $badge;
		$item->class	= $class;
		$item->target	= $target;
		$item->badgeType= $badgeType ?? 'primary';

		return $item;

	}

	/**
	 * Builds HTML of this menu.
	 *
	 * @return string
	 */
	public function render(): string {

		$app = Application::getInstance();
		$this->activeItem = $app->activeMenuItem;

		$ret = '';

		foreach ($this->items as $item) {

			switch ($item->type) {
				
				// menu title rendering
				case 'title':
					$ret .= $this->renderTitle($item);
					break;

				// single menu item rendering
				case 'single':
					$ret .= $this->renderSingle($item);
					break;

				// menu item with many sub-items rendering
				case 'multi':
					$ret .= $this->renderMulti($item);
					break;

				// menu separator rendering
				case 'separator':
					$ret .= $this->renderSeparator($item);
					break;

			}

		}

		return $ret;

	}

	/**
	 * Menu title rendering.
	 * 
	 * @param	\stdClass Menu item object.
	 * @return	string
	 */
	protected function renderTitle(\stdClass $item): string {

		return '<li class="menu-title">' . $item->title . '</li>';

	}

	/**
	 * Single menu item rendering.
	 * 
	 * @param	\stdClass Menu item object.
	 * @return	string
	 */
	protected function renderSingle(\stdClass $item): string {

		$app = Application::getInstance();

		// check permissions
		if (!isset($item->url) or (is_a($app->currentUser, 'Pair\User') and !$app->currentUser->canAccess($item->url))) {
			return '';
		}
		
		$active = ($item->url == $this->activeItem ? ' class="active"' : '');

		return '<li><a href="' . $item->url . '"' . ($item->target ? ' target="' . $item->target . '"' : '') .
			$active . '><i class="fal fa-lg fa-fw ' . $item->class . '"></i> <span class="nav-label">' . $item->title .'</span> ' .
			($item->badge ? '<span class="float-right label label-' . $item->badgeType . '">' . $item->badge . '</span>' : '') . '</a></li>';

	}

	/**
	 * Menu item with many sub-items rendering.
	 * 
	 * @param	\stdClass Menu item object.
	 * @return	string
	 */
	protected function renderMulti(\stdClass $item): string {

		$app = Application::getInstance();

		$links = $menuLi = $menuA = '';

		// builds each sub-item link
		foreach ($item->list as $i) {

			// check permissions
			if (isset($i->url) and (!is_a($app->currentUser, 'Pair\User') or !$app->currentUser->canAccess($i->url))) {
				continue;
			}

			// trigger the menu open
			if ($i->url == $this->activeItem) {
				$active		= 'active';
				$menuLi	= ' active';
				$menuA	= ' active subdrop';
			} else {
				$active		= '';
			}

			$links .=
				'<li class="' . $active . '"><a href="' . $i->url . '" class="' . $active . '">' .
				'<i class="fal fa-fw ' . $i->class . '"></i>' . $i->title .
				($i->badge ? '<span class="float-right label label-' . $item->badgeType . '">' . $i->badge . '</span>' : '') .
				'</a></li>';

		}
		
		// prevent empty multi-menu
		if ('' == $links) {
			return '';
		}

		// assembles the multi-menu
		return '<li class="has-sub' . $menuLi . '">' .
			'<a href="javascript: void(0);" class="waves-effect ' . $menuA . '">
					<i class="fal fa-fw ' . ($item->class ? $item->class : 'fa-th-large') . '"></i>
					<span class="nav-label">' . $item->title . '</span>
					<span class="fal fa-angle-down float-right"></span>
			</a>' .
			'<ul class="nav nav-second-level collapse">' . $links . '</ul></li>';

	}

	/**
	 * Menu separator rendering.
	 * 
	 * @param	\stdClass Menu item object.
	 * @return	string
	 */
	private function renderSeparator(\stdClass $item): string {

		if (!$item->title) $item->title = '&nbsp;';
		return '<div class="separator">' . $item->title . '</div>';

	}

}
