<?php

namespace Pair\Helpers;

/**
 * Helper class for generating Web App Manifest payloads.
 */
class PwaManifest {

	/**
	 * Builds and returns a manifest JSON string.
	 */
	public static function build(array $options = []): string {

		$manifest = static::toArray($options);

		$json = json_encode($manifest, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

		return is_string($json) ? $json : '{}';

	}

	/**
	 * Returns the manifest as normalized associative array.
	 */
	public static function toArray(array $options = []): array {

		$appName = trim((string)($_ENV['APP_NAME'] ?? ''));

		$name = static::normalizeText($options['name'] ?? ($appName ?: 'Pair App'));
		$shortName = static::normalizeText($options['short_name'] ?? $name);
		$description = static::normalizeText($options['description'] ?? 'Progressive Web App powered by Pair.');

		$manifest = [
			'id' => static::normalizePath($options['id'] ?? '/'),
			'name' => $name,
			'short_name' => $shortName,
			'description' => $description,
			'lang' => static::normalizeLang($options['lang'] ?? 'en'),
			'orientation' => static::normalizeOrientation($options['orientation'] ?? 'any'),
			'start_url' => static::normalizePath($options['start_url'] ?? '/'),
			'scope' => static::normalizePath($options['scope'] ?? '/'),
			'display' => static::normalizeDisplay($options['display'] ?? 'standalone'),
			'background_color' => static::normalizeColor($options['background_color'] ?? '#ffffff'),
			'theme_color' => static::normalizeColor($options['theme_color'] ?? '#1b6ec2'),
			'icons' => static::normalizeIcons($options['icons'] ?? []),
		];

		$shortcuts = static::normalizeShortcuts($options['shortcuts'] ?? []);
		if (count($shortcuts)) {
			$manifest['shortcuts'] = $shortcuts;
		}

		return $manifest;

	}

	/**
	 * Saves the manifest JSON to file and returns true on success.
	 */
	public static function write(string $path, array $options = []): bool {

		return (false !== file_put_contents($path, static::build($options)));

	}

	/**
	 * Normalizes color values with hex format fallback.
	 */
	private static function normalizeColor(mixed $color): string {

		$color = trim((string)$color);

		if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
			return strtolower($color);
		}

		return '#ffffff';

	}

	/**
	 * Normalizes display mode.
	 */
	private static function normalizeDisplay(mixed $display): string {

		$display = strtolower(trim((string)$display));

		$allowed = ['fullscreen', 'standalone', 'minimal-ui', 'browser'];

		return in_array($display, $allowed) ? $display : 'standalone';

	}

	/**
	 * Normalizes icon definitions.
	 */
	private static function normalizeIcons(array $icons): array {

		if (!count($icons)) {
			$icons = [
				[
					'src' => '/icons/icon-192.png',
					'sizes' => '192x192',
					'type' => 'image/png'
				],
				[
					'src' => '/icons/icon-512.png',
					'sizes' => '512x512',
					'type' => 'image/png',
					'purpose' => 'any maskable'
				]
			];
		}

		$ret = [];

		foreach ($icons as $icon) {

			$srcRaw = trim((string)($icon['src'] ?? ''));
			if (!$srcRaw) {
				continue;
			}

			$ret[] = [
				'src' => static::normalizePath($srcRaw),
				'sizes' => trim((string)($icon['sizes'] ?? '192x192')),
				'type' => trim((string)($icon['type'] ?? 'image/png')),
				'purpose' => trim((string)($icon['purpose'] ?? 'any')),
			];

		}

		return $ret;

	}

	/**
	 * Normalizes locale language tag.
	 */
	private static function normalizeLang(mixed $lang): string {

		$lang = trim((string)$lang);

		return $lang ?: 'en';

	}

	/**
	 * Normalizes orientation mode.
	 */
	private static function normalizeOrientation(mixed $orientation): string {

		$orientation = strtolower(trim((string)$orientation));

		$allowed = [
			'any',
			'natural',
			'landscape',
			'landscape-primary',
			'landscape-secondary',
			'portrait',
			'portrait-primary',
			'portrait-secondary'
		];

		return in_array($orientation, $allowed) ? $orientation : 'any';

	}

	/**
	 * Normalizes URL-like fields.
	 */
	private static function normalizePath(mixed $path): string {

		$path = trim((string)$path);

		if (!$path) {
			return '/';
		}

		if (preg_match('/^https?:\/\//i', $path)) {
			return $path;
		}

		return '/' . ltrim($path, '/');

	}

	/**
	 * Normalizes shortcut definitions.
	 */
	private static function normalizeShortcuts(array $shortcuts): array {

		$ret = [];

		foreach ($shortcuts as $shortcut) {

			$name = static::normalizeText($shortcut['name'] ?? '');
			$urlRaw = trim((string)($shortcut['url'] ?? ''));

			if (!$name or !$urlRaw) {
				continue;
			}

			$item = [
				'name' => $name,
				'url' => static::normalizePath($urlRaw),
			];

			$description = static::normalizeText($shortcut['description'] ?? '');
			if ($description) {
				$item['description'] = $description;
			}

			$ret[] = $item;

		}

		return $ret;

	}

	/**
	 * Normalizes generic text values.
	 */
	private static function normalizeText(mixed $value): string {

		return trim((string)$value);

	}

}
