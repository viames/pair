<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

class Menu {

	/**
	 * Item object list.
	 * @var array
	 */
	protected $items = array();

	/**
	 * Adds a single-item menu entry. The optional badge can be a subtitle. 
	 * 
	 * @param	string	Url of item.
	 * @param	string	Title shown.
	 * @param	string	Optional, can be an icon, a subtitle or icon placeholder.
	 * @param	string	Optional, is CSS extra class definition.
	 * @param	string	Optional, the anchor target.
	 */
	public function addItem($url, $title, $badge=NULL, $class=NULL, $target=NULL) {

		$this->items[]	= self::getItemObject($url, $title, $badge, $class, $target);

	}

	/**
	 * Adds a multi-entry menu item. The list is array of single-item objects.
	 * 
	 * @param	string	Title for this item.
	 * @param	array	List of single-item objects
	 */
	public function addMulti($title, $list) {

		$multi 			= new \stdClass();
		$multi->type	= 'multi';
		$multi->title	= $title;
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
	public function addSeparator($title=NULL) {
	
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
	 * 
	 * @return	stdClass
	 */
	public static function getItemObject($url, $title, $badge=NULL, $class=NULL, $target=NULL) {

		$item 			= new \stdClass();
		$item->type		= 'single';
		$item->url		= $url;
		$item->title	= $title;
		$item->badge	= $badge;
		$item->class	= $class;
		$item->target	= $target;

		return $item;

	}

	/**
	 * Builds HTML of this menu.
	 *
	 * @return string
	 */
	public function render() {

		$app = Application::getInstance();

		$ret = '';

		foreach ($this->items as $item) {

			switch ($item->type) {

				// single menu item rendering
				case 'single':

					$class  = ($item->url == $app->activeMenuItem ? ' active' : '');

					if ($item->class) {
						$class .= ' ' . $item->class;
					}

					// if url set <a>, otherwise set <div>
					$ret .= $item->url ? '<a href="' . $item->url . '"' : '<div'; 
					
					$ret .= ' class="item' . $class . '"' .
						($item->target ? ' target="' . $item->target . '"' : '') .
						(!is_null($item->badge) ? ' data-badge="' . (int)$item->badge . '" ' : '') .
						'>' .
						(!is_null($item->badge) ? '<span class="badge">' . $item->badge . '</span>' : '') .
						'<span class="title">' . $item->title . '</span>';

					// if url close </a>, otherwise close </div>
					$ret .= $item->url ? '</a>' : '</div>'; 
					break;

				// menu item with many sub-items rendering
				case 'multi':

					$links		= '';
					$menuClass	= '';

					// builds each sub-item link
					foreach ($item->list as $i) {

						if ($i->url == $app->activeMenuItem) {
							$class		= ' active';
							$menuClass	= ' open';
						} else {
							$class		= '';
						}

						if ($i->class) {
							$class .= ' ' . $i->class;
						}

						$links .= '<a class="item' . $class . '" href="' . $i->url . '">' .
							(!is_null($i->badge) ? '<span class="badge">' . $i->badge . '</span>' : '') .
							'<span class="title">' . $i->title . '</span>' .
							'</a>';
					}

					// assembles the multi-menu
					$ret .= '<div class="dropDownMenu' . $menuClass . '">' .
						'<div class="title">' . $item->title . '</div>' .
						'<div class="itemGroup">' . $links . '</div></div>';
					break;
					
				case 'separator':

					if (!$item->title) $item->title = '&nbsp;';
					$ret .= '<div class="separator">' . $item->title . '</div>';
					break;

			}

		}

		return $ret;

	}

}
