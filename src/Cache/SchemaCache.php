<?php

namespace Pair\Cache;

use Pair\Core\Env;

/** Caches only column descriptions and foreign-key metadata within an explicitly versioned schema. */
final class SchemaCache {

	/** Invalidates this database namespace after successful DDL executed through Pair. */
	public static function invalidate(): void {
		$namespace = self::namespaceKey();
		if ($namespace === null) {
			return;
		}
		try {
			Cache::set($namespace . ':generation', bin2hex(random_bytes(16)));
		} catch (\Throwable) {
			// Cache availability must not change the outcome of an already completed DDL.
		}
	}

	/** Separates hosts, schemas, database users and application-controlled schema versions. */
	private static function namespaceKey(): ?string {
		$version = trim((string)Env::get('PAIR_SCHEMA_CACHE_VERSION'));
		if ($version === '') {
			return null;
		}
		return 'orm.schema.v1:' . hash('sha256', json_encode([
			Env::get('DB_HOST'), Env::get('DB_NAME'), Env::get('DB_USER'), $version
		], JSON_THROW_ON_ERROR));
	}

	/** Reuses metadata across requests; errors fall back to the authoritative database query. */
	public static function remember(string $name, callable $resolver): array {
		$namespace = self::namespaceKey();
		if ($namespace === null) {
			return $resolver();
		}
		$key = null;
		try {
			$generation = Cache::get($namespace . ':generation', 'initial');
			$key = $namespace . ':' . hash('sha256', (string)$generation . ':' . $name);
			$cached = Cache::get($key);
			if (is_array($cached) and isset($cached['rows']) and is_array($cached['rows'])) {
				return $cached['rows'];
			}
		} catch (\Throwable) {
			$key = null;
		}
		$rows = $resolver();
		if ($key !== null) {
			try {
				Cache::set($key, ['rows' => $rows], 3600);
			} catch (\Throwable) {
				// Metadata is an optional optimization, never an application dependency.
			}
		}
		return $rows;
	}

}
