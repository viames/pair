<?php

namespace Pair\Html;

use Pair\Core\Router;

/**
 * Resolve the active UI renderer and keep the legacy theme facade compatible.
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
	 * Built-in renderer classes loaded only when their UI framework is selected.
	 *
	 * @var	array<string, class-string<UiRenderer>>
	 */
	private const BUILT_IN_RENDERERS = [
		self::NATIVE => 'Pair\Html\UiRenderers\NativeUiRenderer',
		self::BOOTSTRAP => 'Pair\Html\UiRenderers\BootstrapUiRenderer',
		self::BULMA => 'Pair\Html\UiRenderers\BulmaUiRenderer',
	];

	/**
	 * Runtime override for the active UI renderer.
	 */
	private static ?string $currentTheme = null;

	/**
	 * Registered UI renderer classes keyed by renderer name.
	 *
	 * @var	array<string, class-string<UiRenderer>>
	 */
	private static array $rendererClasses = self::BUILT_IN_RENDERERS;

	/**
	 * Registered UI renderers keyed by renderer name.
	 *
	 * @var	array<string, UiRenderer>
	 */
	private static array $renderers = [];

	/**
	 * Resolve the current theme name from the active renderer.
	 */
	public static function current(): string {

		return self::renderer()->name();

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
	 * Reset renderer registration and restore native HTML rendering.
	 */
	public static function reset(): void {

		self::$currentTheme = null;
		self::$rendererClasses = self::BUILT_IN_RENDERERS;
		self::$renderers = [];

	}

	/**
	 * Register or replace a UI renderer for the current process.
	 */
	public static function registerRenderer(UiRenderer $renderer): void {

		$name = self::normalizeRendererName($renderer->name());
		self::$renderers[$name] = $renderer;

	}

	/**
	 * Register or replace a UI renderer class to be instantiated only when selected.
	 *
	 * @param	string					$name		Renderer name used by Application::uiFramework().
	 * @param	class-string<UiRenderer>	$className	Renderer class name.
	 */
	public static function registerRendererClass(string $name, string $className): void {

		$name = self::normalizeRendererName($name);
		$className = ltrim(trim($className), '\\');

		if ('' === $className) {
			throw new \InvalidArgumentException('Invalid UI renderer class name');
		}

		self::$rendererClasses[$name] = $className;
		unset(self::$renderers[$name]);

	}

	/**
	 * Return the active or requested UI renderer.
	 */
	public static function renderer(?string $theme = null): UiRenderer {

		$name = self::normalizeTheme($theme ?? self::$currentTheme);

		return self::resolveRenderer($name);

	}

	/**
	 * Return all registered renderers.
	 *
	 * @return	array<string, UiRenderer>
	 */
	public static function renderers(): array {

		foreach (array_keys(self::$rendererClasses) as $name) {
			self::resolveRenderer($name);
		}

		return self::$renderers;

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

		return self::renderer()->controlClasses($control);

	}

	/**
	 * Return classes for a label element.
	 *
	 * @param	string|null	$customClasses	Caller-provided classes.
	 * @return	string|null	Normalized class list or null when not needed.
	 */
	public static function labelClasses(?string $customClasses = null): ?string {

		return self::renderer()->labelClasses($customClasses);

	}

	/**
	 * Render the small help marker appended to labels with descriptions.
	 *
	 * @param	string	$description	Description shown by the active tooltip implementation.
	 */
	public static function labelHelpTooltip(string $description): string {

		return self::renderer()->labelHelpTooltip($description);

	}

	/**
	 * Return the wrapper classes required by a select control.
	 */
	public static function selectWrapperClasses(bool $multiple = false): ?string {

		return self::renderer()->selectWrapperClasses($multiple);

	}

	/**
	 * Return the alert class for the active theme.
	 *
	 * @param	string	$variant	Contextual variant such as primary or danger.
	 */
	public static function alertClass(string $variant = 'primary'): string {

		return self::renderer()->alertClass($variant);

	}

	/**
	 * Return the badge class for the active theme.
	 *
	 * @param	string	$variant	Contextual variant such as primary or info.
	 */
	public static function badgeClass(string $variant = 'primary'): string {

		return self::renderer()->badgeClass($variant);

	}

	/**
	 * Return the alignment helper used by badge-like inline elements.
	 */
	public static function endAlignmentClass(): string {

		return self::renderer()->endAlignmentClass();

	}

	/**
	 * Render pagination markup for the active theme.
	 */
	public static function pagination(Router $router, int $page, int $pages): string {

		return self::renderer()->pagination($router, $page, $pages);

	}

	/**
	 * Return LogBar chrome classes from the active renderer when supported.
	 *
	 * @return	array{root: string, header: string, body: string}
	 */
	public static function logBarChromeClasses(): array {

		$renderer = self::renderer();

		if ($renderer instanceof UiLogBarRenderer) {
			return $renderer->logBarChromeClasses();
		}

		return self::nativeLogBarRenderer()->logBarChromeClasses();

	}

	/**
	 * Return true when the active renderer exposes named LogBar breakpoints.
	 */
	public static function supportsLogBarBreakpoints(): bool {

		$renderer = self::renderer();

		if ($renderer instanceof UiLogBarRenderer) {
			return $renderer->supportsLogBarBreakpoints();
		}

		return false;

	}

	/**
	 * Normalize supported framework identifiers and optionally reject invalid values.
	 *
	 * @param	string|null	$theme	UI framework identifier.
	 * @param	bool		$strict	When true, invalid values raise an exception.
	 */
	private static function normalizeTheme(?string $theme, bool $strict = false): string {

		$theme = strtolower(trim((string)$theme));

		if ('' === $theme) {
			if ($strict) {
				throw new \InvalidArgumentException('Unsupported UI framework: ' . $theme);
			}

			return self::NATIVE;
		}

		$theme = self::normalizeRendererName($theme);

		if (array_key_exists($theme, self::$renderers) or array_key_exists($theme, self::$rendererClasses)) {
			return $theme;
		}

		if ($strict) {
			throw new \InvalidArgumentException('Unsupported UI framework: ' . $theme);
		}

		return self::NATIVE;

	}

	/**
	 * Normalize renderer names into explicit stable keys.
	 */
	private static function normalizeRendererName(string $name): string {

		$name = strtolower(trim($name));

		if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $name)) {
			throw new \InvalidArgumentException('Invalid UI renderer name: ' . $name);
		}

		return $name;

	}

	/**
	 * Resolve and cache one UI renderer instance by name.
	 */
	private static function resolveRenderer(string $name): UiRenderer {

		if (array_key_exists($name, self::$renderers)) {
			return self::$renderers[$name];
		}

		if (!array_key_exists($name, self::$rendererClasses)) {
			throw new \InvalidArgumentException('Unsupported UI framework: ' . $name);
		}

		$className = self::$rendererClasses[$name];

		// Validate and instantiate renderer classes only at first use so unselected frameworks stay unloaded.
		if (!class_exists($className)) {
			throw new \InvalidArgumentException('UI renderer class not found: ' . $className);
		}

		$renderer = new $className();

		if (!$renderer instanceof UiRenderer) {
			throw new \InvalidArgumentException('UI renderer class must implement ' . UiRenderer::class . ': ' . $className);
		}

		$rendererName = self::normalizeRendererName($renderer->name());

		if ($rendererName !== $name) {
			throw new \InvalidArgumentException('UI renderer name mismatch: expected ' . $name . ', got ' . $rendererName);
		}

		self::$renderers[$name] = $renderer;

		return $renderer;

	}

	/**
	 * Return the native renderer as the compatibility fallback for optional UI hooks.
	 */
	private static function nativeLogBarRenderer(): UiLogBarRenderer {

		$renderer = self::resolveRenderer(self::NATIVE);

		if (!$renderer instanceof UiLogBarRenderer) {
			throw new \LogicException('Native UI renderer must provide LogBar chrome defaults');
		}

		return $renderer;

	}

}
