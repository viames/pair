<?php

namespace Pair\Core;

/**
 * Caches lightweight filesystem metadata for the current PHP process.
 */
final class FilesystemMetadata {

	/**
	 * Cached file_exists() results keyed by absolute or caller-provided path.
	 *
	 * @var array<string, bool>
	 */
	private static array $fileExists = [];

	/**
	 * Return whether a path exists, using a process-local cache after the first check.
	 *
	 * @param	string	$path	Path to inspect.
	 */
	public static function fileExists(string $path): bool {

		if (!array_key_exists($path, self::$fileExists)) {
			self::$fileExists[$path] = file_exists($path);
		}

		return self::$fileExists[$path];

	}

	/**
	 * Clear cached filesystem metadata, optionally for one path only.
	 *
	 * @param	string|null	$path	Optional path to invalidate.
	 */
	public static function clear(?string $path = null): void {

		if (null === $path) {
			self::$fileExists = [];
			return;
		}

		unset(self::$fileExists[$path]);

	}

}
