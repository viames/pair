<?php

namespace Pair\Html;

use Pair\Core\Application;
use Pair\Exceptions\AppException;
use Pair\Helpers\Translator;

class Menu {

	/**
	 * Item object list.
	 */
	protected array $items = [];

	/**
	 * FontAwesome style. Default is 'fa-solid'.
	 */
	protected string $faStyle = 'fa-solid';

	/**
	 * FontAwesome size. Default is 'fa-lg'.
	 */
	protected string $faSize = 'fa-lg';

	/**
	 * URL of the active menu item.
	 */
	protected ?string $activeItem = NULL;

	public function __toString(): string {

		return $this->render();

	}

	/**
	 * Create an item object for single-item menu entry. The optional badge can be a subtitle.
	 *
	 * @param	string	Url of item.
	 * @param	string	Title shown.
	 * @param	string	Optional, can be an icon, a subtitle or icon placeholder.
	 * @param	string	Optional, is CSS extra class definition.
	 * @param	string	Optional, the anchor target.
	 * @param	string	Optional, the badge type as Bootstrap class (ex. primary, info, error etc.).
	 */
	public function item(string $url, string $title, ?string $class=NULL, ?string $badge=NULL, ?string $badgeType=NULL, ?string $target=NULL): void {

		$item 			= new \stdClass();
		$item->type		= 'single';
		$item->url		= $url;
		$item->title	= $this->translateTitle($title);
		$item->class	= $class;
		$item->badge	= $badge;
		$item->target	= $target;
		$item->badgeType= $badgeType ?? 'primary';

		$this->items[] = $item;

	}

	/**
	 * Adds a multi-entry menu item. The list is array of single-item objects.
	 * @param	string	Title for this item.
	 * @param	array	List of single-item menu entry.
	 * @param	string	Optional, can be an icon, a subtitle or icon placeholder.
	 */
	public function multiItem(string $title, array $list, ?string $class=NULL): void {

		$multi 			= new \stdClass();
		$multi->type	= 'multi';
		$multi->title	= $this->translateTitle($title);
		$multi->class	= $class;
		$multi->list	= [];

		foreach ($list as $i) {

			// required fields
			if (!isset($i[0]) or !isset($i[1])) {
				continue;
			}

			$item = new \stdClass();
			$item->type		= 'single';
			$item->url		= $i[0];
			$item->title	= $this->translateTitle($i[1]);
			$item->class	= $i[2] ?? NULL;
			$item->badge	= $i[3] ?? NULL;
			$item->badgeType= $i[4] ?? 'primary';
			$item->target	= $i[5] ?? NULL;

			$multi->list[] = $item;

		}

		$this->items[] = $multi;

	}

	/**
	 * Builds HTML of this menu.
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
	 * @param	\stdClass Menu item object.
	 */
	protected function renderTitle(\stdClass $item): string {

		return '<li class="menu-title">' . $item->title . '</li>';

	}

	/**
	 * Single menu item rendering.
	 * @param	\stdClass Menu item object.
	 */
	protected function renderSingle(\stdClass $item): string {

		$app = Application::getInstance();

		// check permissions
		if (!isset($item->url) or (is_a($app->currentUser, 'Pair\Models\User') and !$app->currentUser->canAccess($item->url))) {
			return '';
		}

		if ($item->url == $this->activeItem) {
			$active = ' class="active"';
			$app->pageHeading($app->pageHeading ?? $item->title);
		} else {
			$active = '';
		}

		return '<li><a href="' . $item->url . '"' . ($item->target ? ' target="' . $item->target . '"' : '') .
			$active . '><i class="' . $this->faStyle . ' ' . $this->faSize . ' fa-fw ' . $item->class . '"></i> <span class="nav-label">' . $item->title .'</span> ' .
			($item->badge ? '<span class="float-end badge badge-' . $item->badgeType . '">' . $item->badge . '</span>' : '') . '</a></li>';

	}

	/**
	 * Menu item with many sub-items rendering.
	 * @param	\stdClass Menu item object.
	 */
	protected function renderMulti(\stdClass $item): string {

		$app = Application::getInstance();

		$links = $menuLi = $menuA = '';

		// builds each sub-item link
		foreach ($item->list as $i) {

			// check permissions
			if (isset($i->url) and (!is_a($app->currentUser, 'Pair\Models\User') or !$app->currentUser->canAccess($i->url))) {
				continue;
			}

			// trigger the menu open
			if ($i->url == $this->activeItem) {
				$active	= 'active';
				$menuLi	= ' active';
				$menuA	= ' active subdrop';
				$app->pageHeading($app->pageHeading ?? $item->title);
			} else {
				$active		= '';
			}

			$links .=
				'<li class="' . $active . '"><a href="' . $i->url . '" class="' . $active . '">' .
				'<i class="' . $this->faStyle . ' fa-fw ' . $i->class . '"></i>' . $i->title .
				($i->badge ? '<span class="float-end badge badge-' . $item->badgeType . '">' . $i->badge . '</span>' : '') .
				'</a></li>';

		}

		// prevent empty multi-menu
		if ('' == $links) {
			return '';
		}

		// assembles the multi-menu
		return '<li class="has-sub' . $menuLi . '">' .
			'<a href="javascript: void(0);" class="waves-effect ' . $menuA . '">
					<i class="' . $this->faStyle . ' fa-fw ' . ($item->class ? $item->class : 'fa-th-large') . '"></i>
					<span class="nav-label">' . $item->title . '</span>
					<span class="' . $this->faStyle . ' fa-angle-down float-right"></span>
			</a>' .
			'<ul class="nav nav-second-level collapse">' . $links . '</ul></li>';

	}

	/**
	 * Menu separator rendering.
	 * @param	\stdClass Menu item object.
	 */
	protected function renderSeparator(\stdClass $item): string {

		if (!$item->title) $item->title = '&nbsp;';
		return '<div class="separator">' . $item->title . '</div>';

	}

	/**
	 * Add a graphic or text separator to the menu.
	 * @param	string	Separator title (optional).
	 */
	public function separator(?string $title=NULL): void {

		$item 			= new \stdClass();
		$item->type		= 'separator';
		$item->title	= $title;
		$this->items[]	= $item;

	}

	/**
	 * Set the FontAwesome icon size. Like Font Awesome’s icons, the relative sizing scale
	 * is created with modern browsers’ default 16px font-size in mind and creates steps
	 * up/down from there. Default size is 'fa-lg'.
	 * 
	 * @param	string	FontAwesome size (ex. fa-xs, fa-sm, fa-lg, fa-xl, fa-2x, etc.).
	 * @throws	AppException
	 * @see		https://docs.fontawesome.com/web/style/size
	 */
	public function setFontAwesomeSize(string $size): void {

		$valid = [
			'fa-2xs', // 0.625x
			'fa-xs',  // 0.75x
			'fa-sm',  // 0.875x
			'fa-lg',  // 1.33x
			'fa-xl',  // 1.5x
			'fa-2xl', // 2x
			'fa-1x',
			'fa-2x',
			'fa-3x',
			'fa-4x',
			'fa-5x',
			'fa-6x',
			'fa-7x',
			'fa-8x',
			'fa-9x',
			'fa-10x'
		];

		if (!in_array($size, $valid)) {
			throw new AppException('Invalid FontAwesome size: ' . $size);
		}

		$this->faSize = $size;

	}

	/**
	 * Set the FontAwesome icon style. Default style is 'fa-solid'.
	 * 
	 * @param	string	FontAwesome style (ex. fa-solid, fa-regular, fa-brands, fa-light, fa-thin).
	 * @throws	AppException
	 * @see		https://docs.fontawesome.com/web/setup/upgrade/whats-changed
	 */
	public function setFontAwesomeStyle(string $style): void {

		// new style class => old style class (still works)
		$valid = [
			'fa'							=> 'fa',  // free, legacy
			'fa-solid'						=> 'fas', // free
			'fa-brands'						=> 'fab', // free
			'fa-regular'					=> 'far',
			'fa-light'						=> 'fal',
			'fa-thin'						=> 'fat',
			'fa-duotone'					=> 'fad',
			'fa-duotone fa-solid'			=> 'fad',
			'fa-duotone fa-regular'			=> 'fadr',
			'fa-duotone fa-light'			=> 'fadl',
			'fa-duotone fa-thin'			=> 'fadt',
			'fa-sharp fa-solid'				=> 'fass',
			'fa-sharp fa-regular'			=> 'fasr',
			'fa-sharp fa-light'				=> 'fasl',
			'fa-sharp fa-thin'				=> 'fast',
			'fa-sharp-duotone fa-solid'		=> 'fasds',
			'fa-sharp-duotone fa-regular'	=> 'fasdr',
			'fa-sharp-duotone fa-light'		=> 'fasdl',
			'fa-sharp-duotone fa-thin'		=> 'fasdt'
		];

		if (!array_key_exists($style, $valid) and !in_array($style, $valid)) {
			throw new AppException('Invalid FontAwesome style: ' . $style);
		}

		$this->faStyle = $style;

	}

	/**
	 * Add a Title item to menu.
	 */
	public function title(string $title): void {

		$item 			= new \stdClass();
		$item->type		= 'title';
		$item->title	= $title;
		$this->items[]	= $item;

	}

	/**
	 * Translate the title string if it is uppercase.
	 */
	private function translateTitle(string $title): string {

		if (strtoupper($title) === $title) {
			$translator = Translator::getInstance();
			return $translator->do($title, [], FALSE);
		} else {
			return $title;
		}

	}

}