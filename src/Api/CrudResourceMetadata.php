<?php

namespace Pair\Api;

/**
 * Caches normalized CRUD resource metadata for ApiExposable model classes.
 */
final class CrudResourceMetadata {

	/**
	 * Cached model configuration objects keyed by model class.
	 *
	 * @var array<string, CrudResourceConfig>
	 */
	private static array $configs = [];

	/**
	 * Clear cached CRUD resource metadata, optionally for one model class.
	 *
	 * @param	string|null	$modelClass	Optional model class to invalidate.
	 */
	public static function clear(?string $modelClass = null): void {

		if (null === $modelClass) {
			self::$configs = [];
			return;
		}

		unset(self::$configs[$modelClass]);

	}

	/**
	 * Return the normalized API config for a model class.
	 *
	 * @param	class-string	$modelClass	Model class using ApiExposable-style apiConfig().
	 */
	public static function configFor(string $modelClass): CrudResourceConfig {

		if (!array_key_exists($modelClass, self::$configs)) {
			self::$configs[$modelClass] = self::resolveConfig($modelClass);
		}

		return self::$configs[$modelClass];

	}

	/**
	 * Resolve raw model metadata and normalize it into a typed config object.
	 *
	 * @param	class-string	$modelClass	Model class using ApiExposable-style apiConfig().
	 */
	private static function resolveConfig(string $modelClass): CrudResourceConfig {

		if (method_exists($modelClass, 'apiConfig')) {
			return CrudResourceConfig::fromArray($modelClass::apiConfig());
		}

		return CrudResourceConfig::from(null);

	}

}
