<?php

namespace Pair\Core;

/**
 * Centralizes Pair v4 legacy MVC notices for the old controller/view path.
 */
final class LegacyMvc {

	/**
	 * Tracks which legacy classes already emitted a deprecation notice.
	 *
	 * @var	array<string, true>
	 */
	private static array $emitted = [];

	/**
	 * Emit a deprecation notice for one legacy controller class.
	 */
	public static function emitControllerDeprecation(string $controllerClass): void {

		self::emitOnce(
			'controller:' . $controllerClass,
			$controllerClass . ' extends Pair\Core\Controller, which is a legacy MVC bridge in Pair v4. '
			. 'Migrate this module to Pair\Web\Controller and explicit PageResponse or JsonResponse returns.'
		);

	}

	/**
	 * Emit a deprecation notice for one legacy view class.
	 */
	public static function emitViewDeprecation(string $viewClass): void {

		self::emitOnce(
			'view:' . $viewClass,
			$viewClass . ' extends Pair\Core\View, which is a legacy MVC bridge in Pair v4. '
			. 'Move layout state into a typed page-state object and render it from Pair\Web\Controller.'
		);

	}

	/**
	 * Emit one deprecation notice only once and never in production.
	 */
	private static function emitOnce(string $key, string $message): void {

		if (Application::getEnvironment() === 'production') {
			return;
		}

		if (isset(self::$emitted[$key])) {
			return;
		}

		self::$emitted[$key] = true;
		trigger_error($message, E_USER_DEPRECATED);

	}

}
