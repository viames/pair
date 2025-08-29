<?php

namespace Pair\Html;

use Pair\Core\Application;

/**
 * Create a side menu with Bootstrap classes and FontAwesome icons.
 */
class BootstrapMenu extends Menu {

	protected function renderDropdown(MenuEntry $entry): string {

		$app = Application::getInstance();
		$ret = '';
		$links = '';
		$menuClass = '';

		// builds each sub-item link
		foreach ($entry->list as $subitem) {

			// check on permissions
			if (isset($subitem->url) and (!is_a($app->currentUser, 'Pair\Models\User') or !$app->currentUser->canAccess($subitem->url))) {
				continue;
			}

			if ($subitem->active) {
				$liClass = ' class="active"';
				$menuClass = 'active';
				$aria = ' aria-current="page"';
			} else {
				$liClass = $aria = '';
			}

			$links .=
				'<li' . $liClass . '><a ' . $aria . 'href="' . $subitem->url . '">' .
				'<i aria-hidden="true" class="' . $this->faStyle . ' fa-fw ' . $subitem->icon . '"></i> ' . $subitem->title .
				((isset($subitem->badge) and $subitem->badge) ? '<span aria-label="' . $subitem->badge . '" class="float-end badge badge-' . $subitem->badgeType . '">' . $subitem->badge . '</span>' : '') .
				'</a></li>';

		}

		// prevent empty dropdown
		if ('' == $links) {
			return '';
		}

		// assembles the dropdown
		$ret .=
			'<li class="has-sub ' . $menuClass . '">' .
			'<a href="javascript:;">
				<b class="caret float-right"></b>
				<i aria-hidden="true" class="' . $this->faStyle . ' fa-fw ' . ($entry->icon ?: 'fa-th-large') . '"></i>
				<span class="nav-label">' . $entry->title . '</span>
			</a>' .
			'<ul class="sub-menu">' . $links . '</ul></li>';

		return $ret;

	}

	protected function renderSeparator(MenuEntry $entry): string {

		return '<li aria-hidden="true" class="nav-heading" role="separator">' . $entry->title . '</li>';

	}

	protected function renderSingle(MenuEntry $entry): string {

		$app = Application::getInstance();

		// check permissions
		if (!isset($entry->url) or (is_a($app->currentUser, 'Pair\Models\User') and !$app->currentUser->canAccess($entry->url))) {
			return '';
		}

		$ret = '<li' . ($entry->active ? ' class="active"' : '') . '>' .
			'<a' . ($entry->active ? ' aria-current="page"' : '') . ' href="' . $entry->url . '"' . ($entry->target ? ' target="' . $entry->target . '"' : '') .
			'><i aria-hidden="true" class="' . $this->faStyle . ' ' . $this->faSize . ' fa-fw ' . $entry->icon . '"></i> <span class="nav-label">' . $entry->title .'</span> ' .
			((isset($entry->badge) and $entry->badge) ? '<span aria-label="' . $entry->badge . '" class="float-end badge badge-' . $entry->badgeType . '">' . $entry->badge . '</span>' : '') .
			'</a></li>';

		return $ret;

	}

}