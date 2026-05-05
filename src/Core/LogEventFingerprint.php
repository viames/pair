<?php

declare(strict_types=1);

namespace Pair\Core;

/**
 * Builds stable fingerprints for grouping equivalent log events.
 */
final class LogEventFingerprint {

	/**
	 * Builds a SHA-256 fingerprint from stable diagnostic fields.
	 */
	public static function build(int $level, string $message, ?string $exceptionClass, ?string $file, ?int $line, string $path): string {

		$parts = [
			(string)$level,
			self::normalize($message),
			$exceptionClass ?: '',
			self::normalizePath($file),
			is_null($line) ? '' : (string)$line,
			self::normalizePath($path),
		];

		return hash('sha256', implode('|', $parts));

	}

	/**
	 * Removes high-cardinality values from messages before hashing.
	 */
	private static function normalize(string $value): string {

		$value = preg_replace('/[a-f0-9]{32,64}/i', '{hash}', $value) ?? $value;
		$value = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '{uuid}', $value) ?? $value;
		$value = preg_replace('/\b\d+\b/', '{number}', $value) ?? $value;

		return trim($value);

	}

	/**
	 * Normalizes file and route paths before hashing.
	 */
	private static function normalizePath(?string $path): string {

		if (!$path) {
			return '';
		}

		return str_replace('\\', '/', $path);

	}

}
