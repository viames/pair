<?php

namespace Pair\Helpers;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Helper used to load Google Maps browser assets inside Pair views.
 * It keeps script registration centralized and exposes a small
 * framework-level entrypoint for Google address widgets.
 */
class GoogleMaps {

	/**
	 * Load Pair Google Maps frontend support together with the Google Maps JavaScript API.
	 *
	 * Supported options:
	 * - language: Google language code
	 * - region: region code
	 * - version: Google Maps JS API version
	 */
	public static function load(string $assetsPath = '/assets', ?string $browserApiKey = null, array $libraries = ['places'], array $options = []): void {

		$browserApiKey = trim((string)($browserApiKey ?? Env::get('GOOGLE_MAPS_BROWSER_API_KEY')));

		if ('' === $browserApiKey) {
			throw new PairException('Missing Google Maps browser API key. Set GOOGLE_MAPS_BROWSER_API_KEY.', ErrorCodes::MISSING_CONFIGURATION);
		}

		$app = Application::getInstance();
		$assetsPath = '/' . trim($assetsPath, '/');

		if ('/' === $assetsPath) {
			$assetsPath = '';
		}

		// PairGoogleMaps must register its global callback before Google loads the remote script.
		$app->loadScript($assetsPath . '/PairGoogleMaps.js');
		$app->loadScript(self::buildScriptUrl($browserApiKey, $libraries, $options), false, true);

	}

	/**
	 * Build the Google Maps JavaScript API URL.
	 *
	 * @param	string	$browserApiKey	Browser-restricted Google Maps API key.
	 * @param	array	$libraries		Optional library names.
	 * @param	array	$options		Optional Google loader options.
	 */
	private static function buildScriptUrl(string $browserApiKey, array $libraries, array $options = []): string {

		$normalizedLibraries = [];

		foreach ($libraries as $library) {
			$library = trim((string)$library);

			if ('' !== $library and !in_array($library, $normalizedLibraries)) {
				$normalizedLibraries[] = $library;
			}
		}

		if (!count($normalizedLibraries)) {
			$normalizedLibraries[] = 'places';
		}

		$query = [
			'key'		=> $browserApiKey,
			'loading'	=> 'async',
			'callback'	=> 'PairGoogleMaps.onGoogleMapsReady',
			'libraries'	=> implode(',', $normalizedLibraries),
		];

		if (isset($options['language']) and '' !== trim((string)$options['language'])) {
			$query['language'] = trim((string)$options['language']);
		}

		if (isset($options['region']) and '' !== trim((string)$options['region'])) {
			$query['region'] = trim((string)$options['region']);
		}

		if (isset($options['version']) and '' !== trim((string)$options['version'])) {
			$query['v'] = trim((string)$options['version']);
		}

		return 'https://maps.googleapis.com/maps/api/js?' . http_build_query($query);

	}

}
