<?php

declare(strict_types=1);

namespace Pair\Http;

/**
 * Helpers for explicit HTTP caching on read responses.
 */
final class HttpCache {

	/**
	 * Build a Cache-Control header value.
	 */
	public static function cacheControl(int $maxAge, string $visibility = 'public', bool $mustRevalidate = false): string {

		$visibility = strtolower(trim($visibility));

		if ('no-store' === $visibility) {
			return 'no-store';
		}

		if (!in_array($visibility, ['public', 'private'], true)) {
			$visibility = 'public';
		}

		$directives = [
			$visibility,
			'max-age=' . max(0, $maxAge),
		];

		if ($mustRevalidate) {
			$directives[] = 'must-revalidate';
		}

		return implode(', ', $directives);

	}

	/**
	 * Build a deterministic ETag for a response payload.
	 */
	public static function etag(mixed $value, bool $weak = false): string {

		$payload = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

		if (!is_string($payload)) {
			$payload = serialize($value);
		}

		return ($weak ? 'W/' : '') . '"' . hash('sha256', $payload) . '"';

	}

	/**
	 * Build an RFC 7231 Last-Modified header value.
	 */
	public static function lastModified(\DateTimeInterface|int|string $time): string {

		return gmdate('D, d M Y H:i:s', self::timestamp($time)) . ' GMT';

	}

	/**
	 * Return headers for a cacheable response.
	 *
	 * @return	array<string, string>
	 */
	public static function headers(?string $etag = null, \DateTimeInterface|int|string|null $lastModified = null, ?string $cacheControl = null): array {

		$headers = [];

		if (!is_null($etag) and strlen(trim($etag))) {
			$headers['ETag'] = $etag;
		}

		if (!is_null($lastModified)) {
			$headers['Last-Modified'] = self::lastModified($lastModified);
		}

		if (!is_null($cacheControl) and strlen(trim($cacheControl))) {
			$headers['Cache-Control'] = $cacheControl;
		}

		return $headers;

	}

	/**
	 * Return true when request validators match the supplied response validators.
	 *
	 * @param	array<string, mixed>|null	$server	Server values; defaults to $_SERVER.
	 */
	public static function isNotModified(?string $etag = null, \DateTimeInterface|int|string|null $lastModified = null, ?array $server = null): bool {

		$server ??= $_SERVER;
		$method = strtoupper((string)($server['REQUEST_METHOD'] ?? 'GET'));

		if (!in_array($method, ['GET', 'HEAD'], true)) {
			return false;
		}

		$ifNoneMatch = trim((string)($server['HTTP_IF_NONE_MATCH'] ?? ''));

		if (!is_null($etag) and strlen($ifNoneMatch)) {
			return self::etagMatches($etag, $ifNoneMatch);
		}

		$ifModifiedSince = trim((string)($server['HTTP_IF_MODIFIED_SINCE'] ?? ''));

		if (is_null($lastModified) or !strlen($ifModifiedSince)) {
			return false;
		}

		$requestTimestamp = strtotime($ifModifiedSince);

		return false !== $requestTimestamp and $requestTimestamp >= self::timestamp($lastModified);

	}

	/**
	 * Build an explicit cacheable JSON response or a 304 response when validators match.
	 *
	 * @param	array<string, mixed>|null	$server	Server values; defaults to $_SERVER.
	 */
	public static function json(mixed $payload, int $httpCode = 200, ?string $etag = null, \DateTimeInterface|int|string|null $lastModified = null, ?string $cacheControl = null, ?array $server = null): ResponseInterface {

		$headers = self::headers($etag, $lastModified, $cacheControl);

		if (self::isNotModified($etag, $lastModified, $server)) {
			return new EmptyResponse(304, $headers);
		}

		return new JsonResponse($payload, $httpCode, $headers);

	}

	/**
	 * Build an explicit 304 Not Modified response with cache validators.
	 */
	public static function notModified(?string $etag = null, \DateTimeInterface|int|string|null $lastModified = null, ?string $cacheControl = null): EmptyResponse {

		return new EmptyResponse(304, self::headers($etag, $lastModified, $cacheControl));

	}

	/**
	 * Return true when an If-None-Match header matches an ETag.
	 */
	private static function etagMatches(string $etag, string $ifNoneMatch): bool {

		foreach (explode(',', $ifNoneMatch) as $candidate) {

			$candidate = trim($candidate);

			if ('*' === $candidate or self::normalizeEtag($candidate) === self::normalizeEtag($etag)) {
				return true;
			}

		}

		return false;

	}

	/**
	 * Normalize weak and strong validators to their opaque tag.
	 */
	private static function normalizeEtag(string $etag): string {

		$etag = trim($etag);

		if (str_starts_with($etag, 'W/')) {
			$etag = substr($etag, 2);
		}

		return trim($etag);

	}

	/**
	 * Normalize a supported time value to a Unix timestamp.
	 */
	private static function timestamp(\DateTimeInterface|int|string $time): int {

		if ($time instanceof \DateTimeInterface) {
			return $time->getTimestamp();
		}

		if (is_int($time)) {
			return $time;
		}

		$timestamp = strtotime($time);

		if (false === $timestamp) {
			throw new \InvalidArgumentException('Invalid HTTP cache timestamp.');
		}

		return $timestamp;

	}

}
