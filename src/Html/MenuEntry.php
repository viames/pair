<?php

namespace Pair\Html;

final class MenuEntry {

    /**
	 * The menu entry type.
	 * @var 'single'|'group'|'separator'|'title'
	 */
    public string $type = 'single';

	/**
	 * Visible menu entry title.
	 */
    public string  $title = '';

	/**
	 * Menu entry URL.
	 */
    public ?string $url   = null;

	/**
	 * Font Awesome icon class.
	 */
    public ?string $icon  = null;

	/**
	 * Menu entry badge.
	 */
    public ?string $badge = null;

	/**
	 * Menu entry badge type, as Bootstrap's contextual classes.
	 */
    public string  $badgeType = 'primary';

	/**
	 * Target attribute (eg. _blank).
	 */
    public ?string $target = null;

	/**
	 * Active state is flagged true if the menu entry is currently active.
	 */
    public bool $active = false;

    /**
	 * List of submenu entries in case of a group menu entry.
	 * 
	 * @var MenuEntry[]
	 */
    public array $list = [];

}