<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Application;
use Pair\Menu;

class BootstrapMenu extends Menu {

	/**
	 * Builds HTML of this menu.
	 *
	 * @return string
	 */
	public function render() {
		
		$ret = '';
		
		$app = Application::getInstance();
		$this->activeItem = $app->activeMenuItem;

		foreach ($this->items as $item) {

			switch ($item->type) {

				// single menu item rendering
				case 'single':

					$active = ($item->url == $this->activeItem ? ' class="active"' : '');

					$ret .= '<li' . $active . '><a href="' . $item->url . '"' . ($item->target ? ' target="' . $item->target . '"' : '') .
						'><i class="fa fa-lg ' . $item->class . '"></i> <span class="nav-label">' . $item->title .'</span> ' .
						'<span class="pull-right label label-primary">' . $item->badge . '</span> </a></li>';

					break;

				// menu item with many sub-items rendering
				case 'multi':

					$links		= '';
					$menuClass	= '';
					$secLevel	= '';

					// builds each sub-item link
					foreach ($item->list as $i) {

						if ($i->url == $this->activeItem) {
							$active		= 'active';
							$menuClass	= 'active';
							$secLevel	= ' in';
						} else {
							$active		= '';
						}

						$links .=
							'<li class="' . $active . '"><a href="' . $i->url . '">' .
							'<i class="fa ' . $i->class . '"></i>' . $i->title .
							'<span class="pull-right label label-primary">' . $i->badge . '</span>' .
							'</a></li>';

					}

					// assembles the multi-menu
					$ret .=
						'<li class="' . $menuClass . '">' .
						'<a href="#">
								<i class="fa fa-th-large"></i>
								<span class="nav-label">' . $item->title . '</span>
								<span class="fa arrow"></span>
						</a>' .
						'<ul class="nav nav-second-level collapse">' . $links . '</ul></li>';
					break;

			}

		}

		return $ret;

	}

}
