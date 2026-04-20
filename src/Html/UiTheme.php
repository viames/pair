<?php

namespace Pair\Html;

/**
 * Resolve the active UI theme and expose small HTML-oriented mappings.
 *
 * The goal is to keep theme selection centralized without introducing
 * a heavy rendering subsystem. Bootstrap remains the default theme for
 * backward compatibility, while alternative frameworks can opt in through
 * the application runtime.
 */
final class UiTheme {

	/**
	 * Default Bootstrap-compatible theme identifier.
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
	 * Reset the runtime override and restore the default Bootstrap behavior.
	 */
	public static function reset(): void {

		self::$currentTheme = null;

	}

	/**
	 * Return true when the active theme is Bulma.
	 */
	public static function isBulma(): bool {

		return self::current() === self::BULMA;

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

		return 'alert alert-' . $variant;

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

		return 'badge badge-' . $variant;

	}

	/**
	 * Return the alignment helper used by badge-like inline elements.
	 */
	public static function endAlignmentClass(): string {

		return self::isBulma() ? 'is-pulled-right' : 'float-end';

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

		if (in_array($theme, [self::BOOTSTRAP, self::BULMA], true)) {
			return $theme;
		}

		if ($strict) {
			throw new \InvalidArgumentException('Unsupported UI framework: ' . $theme);
		}

		return self::BOOTSTRAP;

	}

}
