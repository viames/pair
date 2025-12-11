<?php

namespace Pair\Html;

use Pair\Core\Application;
use Pair\Core\Router;
use Pair\Exceptions\AppException;
use Pair\Helpers\Translator;

class Menu {

	/**
	 * URL of the active menu item.
	*/
	protected ?string $activeItem = null;

	/**
	 * Menu item object list.
	 *
	 * @var MenuEntry[]
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

	public function __construct() {

		$app = Application::getInstance();
		$router = Router::getInstance();

		$this->activeItem = $app->menuUrl ?? $router->module . ($router->action ? '/' . $router->action : '');

	}

	public function __toString(): string {

		return $this->render();

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
	public function fontAwesomeSize(string $size): void {

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
	public function fontAwesomeStyle(string $style): void {

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
	 * Adds a dropdown with a header and a list of single items.
	 *
	 * @param string      Dropdown header shown in the menu.
	 * @param array       List of items. Each item must be a numeric tuple:
	 *                        [string $url, string $title, ?string $icon, ?string $badge, ?string $badgeType, ?string $target]
	 *                      `$badgeType` maps to Bootstrap styles (e.g. "primary", "info", "danger"). Default is "primary".
	 * @param string|null Optional FontAwesome icon class for the dropdown header (e.g. "fal-cog").
	 *
	 * Example:
	 * $menu->group('Settings', [
	 *   ['/profile',  'Profile',  'fa-user'],
	 *   ['/security', 'Security', 'fa-lock', '23', 'info', '_blank'],
	 *   ['url'=>'/about','title'=>'About','icon'=>'fa-info-circle','badge'=>'new','badge_type'=>'success'],
	 * ], 'fa-cog');
	 */
	public function group(string $title, array $items, ?string $icon = null): void {

        $group = new MenuEntry();
        $group->type  = 'dropdown';
        $group->title = $this->translateTitle($title);
        $group->icon  = $icon;
        $group->list  = [];

        foreach ($items as $entry) {

			if (!is_array($entry)) {
                continue;
            }

            try {
                [$url, $t, $i, $b, $bt, $tg] = $this->normalizeItem($entry);
            } catch (\InvalidArgumentException $e) {
                continue;
            }

            $item = $this->makeItem($url, $t, $i, $b, $bt, $tg);
            $group->list[] = $item;

		}

        // avoids empty groups
        if ($group->list) {
            $this->items[] = $group;
        }

	}

	/**
     * Creates a single menu item.
     *
     * Each item can include a title, URL, an optional icon (FontAwesome class),
     * an optional badge (e.g. status label or subtitle), and other properties.
     *
	 * @param string      	URL of the menu item.
	 * @param string      	Displayed title of the menu item.
	 * @param string|null	Optional FontAwesome icon class (e.g. "fa-user", "fal-cog").
	 * @param string|null	Optional text used as a badge or subtitle.
	 * @param string|null	Optional badge type, usually mapped to Bootstrap classes (e.g. "primary", "info", "danger"). Default: "primary".
	 * @param string|null	Optional link target attribute (e.g. "_blank").
	 */
	public function item(string $url, string $title, ?string $icon = null, ?string $badge = null, ?string $badgeType = null, ?string $target = null): void {

        $e = $this->makeItem($url, $title, $icon, $badge, $badgeType, $target);
        $this->items[] = $e;

	}

	/**
	 * Creates a single MenuEntry and manages the active state.
     */
    private function makeItem(string $url, string $title, ?string $icon, ?string $badge, ?string $badgeType, ?string $target): MenuEntry {

        $e = new MenuEntry();

        $e->type      = 'single';
        $e->url       = $url;
        $e->title     = $this->translateTitle($title);
        $e->icon      = $icon;
        $e->badge     = $badge;
        $e->badgeType = $badgeType ?? 'primary';
        $e->target    = $target;
        $e->active    = ($url === $this->activeItem);

        if ($e->active) {
            $app = Application::getInstance();
            $app->menuLabel = $e->title;
        }

        return $e;

	}

    /**
	 * Normalizes an item into a tuple: [url, title, icon, badge, badgeType, target].
	 * Accepts both numeric tuples and associative arrays.
     *
     * @param	array $a
     * @return	array{0:string,1:string,2:?string,3:?string,4:?string,5:?string}
     */
    private function normalizeItem(array $a): array {

		// associative
        if (isset($a['url'], $a['title'])) {
            return [
                (string)$a['url'],
                (string)$a['title'],
                $a['icon']       ?? null,
                $a['badge']      ?? null,
                $a['badge_type'] ?? 'primary',
                $a['target']     ?? null,
            ];
        }

        // numeric tuple
        if (isset($a[0], $a[1])) {

            // pad to 6 items: url, title, icon, badge, badgeType, target
            $b = $a + [0=>null, 1=>null, 2=>null, 3=>null, 4=>'primary', 5=>null];
            return [
                (string)$b[0],
                (string)$b[1],
                $b[2],
                $b[3],
                $b[4],
                $b[5],
            ];
        }

        throw new \InvalidArgumentException('Invalid menu item shape');

    }

	/**
	 * Builds HTML of this menu.
	 */
	public function render(): string {

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
				case 'dropdown':
					$ret .= $this->renderDropdown($item);
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
	 * Menu entry with many sub-items rendering.
	 */
	protected function renderDropdown(MenuEntry $entry): string {

		$app = Application::getInstance();

		$links = $menuLi = $menuA = '';

		// builds each sub-item link
		foreach ($entry->list as $subitem) {

			// check permissions
			if (isset($subitem->url) and (!is_a($app->currentUser, 'Pair\Models\User') or !$app->currentUser->canAccess($subitem->url))) {
				continue;
			}

			// trigger the menu open
			if ($subitem->active) {
				$class = 'active';
				$aria = ' aria-current="page"';
				$menuLi	= ' active';
				$menuA	= ' active subdrop';
			} else {
				$class = $aria = '';
			}

			$links .=
				'<li class="' . $class . '"><a href="' . $subitem->url . '" class="' . $class . '" ' . $aria . '>' .
				'<i aria-hidden="true" class="' . $this->faStyle . ' fa-fw ' . $subitem->icon . '"></i>' . $subitem->title .
				(!is_null($subitem->badge) ? '<span aria-label="' . $subitem->badge . '" class="float-end badge badge-' . $subitem->badgeType . '">' . $subitem->badge . '</span>' : '') .
				'</a></li>';

		}

		// prevent empty dropdown
		if ('' == $links) {
			return '';
		}

		// assembles the dropdown
		return '<li class="has-sub' . $menuLi . '">' .
			'<a href="javascript: void(0);" class="waves-effect ' . $menuA . '">
					<i aria-hidden="true" class="' . $this->faStyle . ' fa-fw ' . ($entry->icon ?: 'fa-th-large') . '"></i>
					<span class="nav-label">' . $entry->title . '</span>
					<span class="' . $this->faStyle . ' fa-angle-down float-right"></span>
			</a>' .
			'<ul class="nav nav-second-level collapse">' . $links . '</ul></li>';

	}

	/**
	 * Menu separator rendering.
	 *
	 * @param	MenuEntry Menu item object.
	 */
	protected function renderSeparator(MenuEntry $item): string {

		if (!$item->title) $item->title = '&nbsp;';
		return '<div aria-hidden="true" class="separator" role="separator">' . $item->title . '</div>';

	}

	/**
	 * Single menu item rendering.
	 */
	protected function renderSingle(MenuEntry $item): string {

		$app = Application::getInstance();

		// check permissions
		if (!isset($item->url) or (is_a($app->currentUser, 'Pair\Models\User') and !$app->currentUser->canAccess($item->url))) {
			return '';
		}

		$current = $item->active ? ' class="active" aria-current="page"' : '';

		return '<li><a href="' . $item->url . '"' . ($item->target ? ' target="' . $item->target . '"' : '') .
			$current . '><i aria-hidden="true" class="' . $this->faStyle . ' ' . $this->faSize . ' fa-fw ' . $item->icon . '"></i> <span class="nav-label">' . $item->title .'</span> ' .
			(!is_null($item->badge)
				? '<span aria-label="' . $item->badge . '" class="float-end badge badge-' . $item->badgeType . '">' . $item->badge . '</span>'
				: '')
			. '</a></li>';

	}

	/**
	 * Menu title rendering.
	 */
	protected function renderTitle(MenuEntry $item): string {

		return '<li class="menu-title">' . $item->title . '</li>';

	}

	/**
	 * Adds a graphic or text separator to the menu.
	 *
	 * @param	string	Separator title (optional).
	 */
	public function separator(?string $title = null): void {

		$item 			= new MenuEntry();
		$item->type		= 'separator';
		$item->title	= $title;
		$this->items[]	= $item;

	}

	/**
	 * Adds a Title item to menu.
	 */
	public function title(string $title): void {

		$item 			= new MenuEntry();
		$item->type		= 'title';
		$item->title	= $title;
		$this->items[]	= $item;

	}

	/**
	 * Translates the title string if it is uppercase.
	 */
	private function translateTitle(string $title): string {

		if (strtoupper($title) === $title) {
			$translator = Translator::getInstance();
			return $translator->do($title, [], false);
		} else {
			return $title;
		}

	}

}