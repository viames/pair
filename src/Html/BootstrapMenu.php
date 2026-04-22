<?php

namespace Pair\Html;

use Pair\Core\Application;

/**
 * Create a side menu with Bootstrap classes and FontAwesome icons.
 */
class BootstrapMenu extends Menu {

	/**
	 * Render a dropdown menu entry with Bootstrap-compatible classes.
	 */
	protected function renderDropdown(MenuEntry $entry): string {

		$app = Application::getInstance();
		$ret = '';
		$links = '';
		$menuClass = '';
		$faStyle = $this->escapeAttribute($this->faStyle);

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
				'<li' . $liClass . '><a ' . $aria . 'href="' . $this->escapeAttribute($subitem->url) . '">' .
				'<i aria-hidden="true" class="' . $faStyle . ' fa-fw ' . $this->escapeAttribute($subitem->icon) . '"></i> ' . $this->escapeText($subitem->title) .
				$this->renderBadge($subitem) .
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
				<i aria-hidden="true" class="' . $faStyle . ' fa-fw ' . $this->escapeAttribute($entry->icon ?: 'fa-th-large') . '"></i>
				<span class="nav-label">' . $this->escapeText($entry->title) . '</span>
			</a>' .
			'<ul class="sub-menu">' . $links . '</ul></li>';

		return $ret;

	}

	/**
	 * Render a Bootstrap nav heading separator.
	 */
	protected function renderSeparator(MenuEntry $entry): string {

		return '<li aria-hidden="true" class="nav-heading" role="separator">' . $this->escapeText($entry->title) . '</li>';

	}

	/**
	 * Render a single Bootstrap menu entry.
	 */
	protected function renderSingle(MenuEntry $entry): string {

		$app = Application::getInstance();

		// check permissions
		if (!isset($entry->url) or (is_a($app->currentUser, 'Pair\Models\User') and !$app->currentUser->canAccess($entry->url))) {
			return '';
		}

		$target = $entry->target ? ' target="' . $this->escapeAttribute($entry->target) . '"' : '';
		$iconClass = $this->escapeAttribute(trim($this->faStyle . ' ' . $this->faSize . ' fa-fw ' . $entry->icon));

		$ret = '<li' . ($entry->active ? ' class="active"' : '') . '>' .
			'<a' . ($entry->active ? ' aria-current="page"' : '') . ' href="' . $this->escapeAttribute($entry->url) . '"' . $target .
			'><i aria-hidden="true" class="' . $iconClass . '"></i> <span class="nav-label">' . $this->escapeText($entry->title) .'</span> ' .
			$this->renderBadge($entry) .
			'</a></li>';

		return $ret;

	}

}
