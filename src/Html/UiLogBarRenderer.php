<?php

declare(strict_types=1);

namespace Pair\Html;

/**
 * Optional contract for UI renderers that customize LogBar chrome.
 */
interface UiLogBarRenderer {

	/**
	 * Return outer LogBar classes for the active UI framework.
	 *
	 * @return	array{root: string, header: string, body: string}
	 */
	public function logBarChromeClasses(): array;

	/**
	 * Return true when LogBar can expose named framework breakpoints.
	 */
	public function supportsLogBarBreakpoints(): bool;

}
