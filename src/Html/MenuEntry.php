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
    public ?string $url   = NULL;

	/**
	 * Font Awesome icon class.
	 */
    public ?string $icon  = NULL;

	/**
	 * Menu entry badge.
	 */
    public ?string $badge = NULL;

	/**
	 * Menu entry badge type, as Bootstrap's contextual classes.
	 */
    public string  $badgeType = 'primary';

	/**
	 * Target attribute (eg. _blank).
	 */
    public ?string $target = NULL;

	/**
	 * Active state is flagged TRUE if the menu entry is currently active.
	 */
    public bool $active = FALSE;

    /**
	 * List of submenu entries in case of a group menu entry.
	 * 
	 * @var MenuEntry[]
	 */
    public array $list = [];

}