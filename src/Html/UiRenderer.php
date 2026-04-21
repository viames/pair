<?php

declare(strict_types=1);

namespace Pair\Html;

use Pair\Core\Router;

/**
 * Contract for UI-framework-specific HTML helper rendering.
 */
interface UiRenderer {

	/**
	 * Return the renderer name used by Application::uiFramework().
	 */
	public function name(): string;

	/**
	 * Return theme classes automatically injected on known controls.
	 *
	 * @return	string[]	List of theme classes to append.
	 */
	public function controlClasses(FormControl $control): array;

	/**
	 * Return classes for a label element.
	 */
	public function labelClasses(?string $customClasses = null): ?string;

	/**
	 * Render the small help marker appended to labels with descriptions.
	 */
	public function labelHelpTooltip(string $description): string;

	/**
	 * Return the wrapper classes required by a select control.
	 */
	public function selectWrapperClasses(bool $multiple = false): ?string;

	/**
	 * Return the alert class for the active renderer.
	 */
	public function alertClass(string $variant = 'primary'): string;

	/**
	 * Return the badge class for the active renderer.
	 */
	public function badgeClass(string $variant = 'primary'): string;

	/**
	 * Return the alignment helper used by badge-like inline elements.
	 */
	public function endAlignmentClass(): string;

	/**
	 * Render pagination markup for the active renderer.
	 */
	public function pagination(Router $router, int $page, int $pages): string;

}
