<?php

declare(strict_types=1);

namespace Pair\Http;

/** Entity-tag syntax and precondition evaluation (RFC 9110). */
final class EntityTag {
	/** Parse entity-tag lists, including weak tags and the standalone wildcard. */
	public static function parse(string $value): array {
		if (trim($value, " \t") === '*') {
			return ['*'];
		}
		// opaque-tag has no quoted-pair syntax: backslash is a literal character.
		$tags = [];
		$offset = 0;
		$length = strlen($value);
		while ($offset < $length) {
			if (preg_match('/\G[ \t,]+/A', $value, $match, 0, $offset)) {
				$offset += strlen($match[0]);
				if ($offset === $length) {
					break;
				}
			}
			if (!preg_match('/\G(?:W\/)?"[\x21\x23-\x7e\x80-\xff]*"[ \t]*/A', $value, $match, 0, $offset)) {
				throw new \InvalidArgumentException('Invalid entity-tag.');
			}
			$tags[] = rtrim($match[0], " \t");
			$offset += strlen($match[0]);
			if ($offset < $length && $value[$offset] !== ',') {
				throw new \InvalidArgumentException('Invalid entity-tag list.');
			}
		}
		return $tags;
	}

	/** Evaluate strong If-Match before weak If-None-Match; only reads can return 304. */
	public static function evaluate(string $method, ?string $etag, ?string $ifMatch, ?string $ifNoneMatch, bool $exists = true): int {
		// These methods neither select nor modify a representation (RFC 9110 13.2.1).
		if (in_array($method, ['OPTIONS','TRACE','CONNECT'], true)) {
			return 200;
		}
		if ($ifMatch !== null) {
			$tags = self::parse($ifMatch);
			if (!$exists or (!in_array('*', $tags, true) and ($etag === null or !in_array($etag, $tags, true)))) {
				return 412;
			}
		}
		if ($ifNoneMatch !== null) {
			$tags = self::parse($ifNoneMatch);
			$matches = ($exists and in_array('*', $tags, true));
			foreach ($tags as $tag) {
				if (($tag[0] ?? '') === 'W') {
					$tag = substr($tag, 2);
				}
				if ($exists and $etag !== null and $tag === $etag) {
					$matches = true;
				}
			}
			if ($matches) {
				if (in_array($method, ['GET','HEAD'], true)) {
					return 304;
				}
				return 412;
			}
		}
		return 200;
	}
}
