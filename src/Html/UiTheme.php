<?php

namespace Pair\Html;

/**
 * Resolve the active UI theme and expose small HTML-oriented mappings.
 *
 * The goal is to keep theme selection centralized without introducing
 * a heavy rendering subsystem. Native HTML is the default renderer, while
 * Bootstrap and Bulma can opt in through the application runtime.
 */
final class UiTheme {

	/**
	 * Native HTML renderer without framework-specific classes.
	 */
	public const NATIVE = 'native';

	/**
	 * Bootstrap-compatible theme identifier.
	 */
	public const BOOTSTRAP = 'bootstrap';

	/**
	 * Alternative Bulma theme identifier.
	 */
	public const BULMA = 'bulma';

	/**
	 * Runtime override for the active UI framework.
	 */
	private static ?string $currentTheme = null;

	/**
	 * Resolve the current theme from runtime configuration.
	 */
	public static function current(): string {

		return self::normalizeTheme(self::$currentTheme);

	}

	/**
	 * Override the active UI framework for the current runtime.
	 *
	 * @param	string	$theme	UI framework identifier.
	 * @throws	\InvalidArgumentException	If the framework is not supported.
	 */
	public static function setCurrent(string $theme): void {

		self::$currentTheme = self::normalizeTheme($theme, true);

	}

	/**
	 * Reset the runtime override and restore native HTML rendering.
	 */
	public static function reset(): void {

		self::$currentTheme = null;

	}

	/**
	 * Return true when the active theme is Bootstrap.
	 */
	public static function isBootstrap(): bool {

		return self::current() === self::BOOTSTRAP;

	}

	/**
	 * Return true when the active theme is Bulma.
	 */
	public static function isBulma(): bool {

		return self::current() === self::BULMA;

	}

	/**
	 * Return true when no supported UI framework has been selected.
	 */
	public static function isNative(): bool {

		return self::current() === self::NATIVE;

	}

	/**
	 * Return theme classes automatically injected on known controls.
	 *
	 * @param	FormControl	$control	Control currently being rendered.
	 * @return	string[]	List of theme classes to append.
	 */
	public static function controlClasses(FormControl $control): array {

		if (!self::isBulma()) {
			return [];
		}

		// Bulma styles these controls through component classes on the element itself.
		if (is_a($control, 'Pair\Html\FormControls\Button')) {
			return ['button'];
		}

		if (is_a($control, 'Pair\Html\FormControls\Textarea')) {
			return ['textarea'];
		}

		// Select and file inputs need dedicated wrappers in Bulma, while boolean and
		// semantic widgets already have bespoke Pair markup that should stay untouched.
		if (
			is_a($control, 'Pair\Html\FormControls\Select')
			or is_a($control, 'Pair\Html\FormControls\File')
			or is_a($control, 'Pair\Html\FormControls\Checkbox')
			or is_a($control, 'Pair\Html\FormControls\Toggle')
			or is_a($control, 'Pair\Html\FormControls\Hidden')
			or is_a($control, 'Pair\Html\FormControls\Image')
			or is_a($control, 'Pair\Html\FormControls\Meter')
			or is_a($control, 'Pair\Html\FormControls\Progress')
		) {
			return [];
		}

		return ['input'];

	}

	/**
	 * Return classes for a label element.
	 *
	 * @param	string|null	$customClasses	Caller-provided classes.
	 * @return	string|null	Normalized class list or null when not needed.
	 */
	public static function labelClasses(?string $customClasses = null): ?string {

		$classes = [];

		if (self::isBulma()) {
			$classes[] = 'label';
		}

		foreach (preg_split('/\s+/', trim((string)$customClasses)) ?: [] as $className) {
			if ('' !== $className and !in_array($className, $classes, true)) {
				$classes[] = $className;
			}
		}

		return count($classes) ? implode(' ', $classes) : null;

	}

	/**
	 * Render the small help marker appended to labels with descriptions.
	 *
	 * @param	string	$description	Description shown by the active tooltip implementation.
	 */
	public static function labelHelpTooltip(string $description): string {

		$description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
		$questionMark = '<span aria-hidden="true">?</span>';
		$ariaLabel = ' aria-label="' . $description . '"';

		if (self::isBootstrap()) {
			return '<span class="form-control-help" role="button" tabindex="0" data-toggle="tooltip" data-bs-toggle="tooltip" data-placement="auto" data-bs-placement="auto" title="' . $description . '"' . $ariaLabel . '>' . $questionMark . '</span>';
		}

		if (self::isBulma()) {
			return '<span class="form-control-help has-tooltip-arrow has-tooltip-multiline" role="button" tabindex="0" data-tooltip="' . $description . '"' . $ariaLabel . '>' . $questionMark . '</span>';
		}

		return '<abbr class="form-control-help" title="' . $description . '"' . $ariaLabel . '>' . $questionMark . '</abbr>';

	}

	/**
	 * Return the wrapper classes required by a Bulma select control.
	 */
	public static function selectWrapperClasses(bool $multiple = false): ?string {

		if (!self::isBulma()) {
			return null;
		}

		return $multiple ? 'select is-multiple' : 'select';

	}

	/**
	 * Return the alert class for the active theme.
	 *
	 * @param	string	$variant	Contextual variant such as primary or danger.
	 */
	public static function alertClass(string $variant = 'primary'): string {

		$variant = self::normalizeVariant($variant);

		if (self::isBulma()) {
			return 'notification is-' . $variant;
		}

		if (self::isBootstrap()) {
			return 'alert alert-' . $variant;
		}

		return '';

	}

	/**
	 * Return the badge class for the active theme.
	 *
	 * @param	string	$variant	Contextual variant such as primary or info.
	 */
	public static function badgeClass(string $variant = 'primary'): string {

		$variant = self::normalizeVariant($variant);

		if (self::isBulma()) {
			return 'tag is-' . $variant;
		}

		if (self::isBootstrap()) {
			return 'badge badge-' . $variant;
		}

		return '';

	}

	/**
	 * Return the alignment helper used by badge-like inline elements.
	 */
	public static function endAlignmentClass(): string {

		if (self::isBulma()) {
			return 'is-pulled-right';
		}

		if (self::isBootstrap()) {
			return 'float-end';
		}

		return '';

	}

	/**
	 * Normalize contextual variants between Bootstrap-like and Bulma-like names.
	 *
	 * @param	string	$variant	Contextual variant requested by callers.
	 */
	private static function normalizeVariant(string $variant): string {

		$variant = strtolower(trim($variant));

		return match ($variant) {
			'danger', 'error' => 'danger',
			'warning' => 'warning',
			'success' => 'success',
			'info' => 'info',
			'link' => 'link',
			'light' => 'light',
			'dark' => 'dark',
			'secondary' => self::isBulma() ? 'light' : 'secondary',
			default => 'primary',
		};

	}

	/**
	 * Normalize supported framework identifiers and optionally reject invalid values.
	 *
	 * @param	string|null	$theme	UI framework identifier.
	 * @param	bool		$strict	When true, invalid values raise an exception.
	 */
	private static function normalizeTheme(?string $theme, bool $strict = false): string {

		$theme = strtolower(trim((string)$theme));

		if (in_array($theme, [self::NATIVE, self::BOOTSTRAP, self::BULMA], true)) {
			return $theme;
		}

		if ($strict) {
			throw new \InvalidArgumentException('Unsupported UI framework: ' . $theme);
		}

		return self::NATIVE;

	}

}
