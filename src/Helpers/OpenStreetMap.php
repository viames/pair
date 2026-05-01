<?php

namespace Pair\Helpers;

use Pair\Core\Application;

/**
 * Helper used to load OpenStreetMap browser assets inside Pair views.
 */
class OpenStreetMap {

	/**
	 * Load Pair OpenStreetMap frontend support.
	 *
	 * @param	string	$assetsPath	Public directory containing Pair bundled assets.
	 */
	public static function load(string $assetsPath = '/assets'): void {

		$app = Application::getInstance();
		$assetsPath = '/' . trim($assetsPath, '/');

		if ('/' === $assetsPath) {
			$assetsPath = '';
		}

		$app->loadCss($assetsPath . '/pair.css');
		$app->loadScript($assetsPath . '/PairOpenStreetMap.js', true);

	}

}
