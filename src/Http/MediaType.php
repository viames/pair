<?php

declare(strict_types=1);

namespace Pair\Http;

/** HTTP media types and Accept negotiation (RFC 9110). */
final class MediaType {
	/** Split lists and parameters without splitting quoted delimiters. */
	public static function split(string $value, string $delimiter): array {
		$parts = [];
		$start = 0;
		$quoted = false;
		$escaped = false;
		for ($i = 0, $length = strlen($value); $i < $length; $i++) {
			$char = $value[$i];
			if ($escaped) {
				$escaped = false;
				continue;
			}
			if ($quoted && $char === '\\') {
				$escaped = true;
				continue;
			}
			if ($char === '"') {
				$quoted = !$quoted;
			}
			if (!$quoted && $char === $delimiter) {
				$parts[] = trim(substr($value, $start, $i - $start), " \t");
				$start = $i + 1;
			}
		}
		if ($quoted || $escaped || preg_match('/[\x00-\x08\x0a-\x1f\x7f]/', $value)) {
			throw new \InvalidArgumentException('Invalid header syntax.');
		}
		$parts[] = trim(substr($value, $start), " \t");
		return $parts;
	}

	/** Decode an HTTP token or quoted-string without changing its value. */
	private static function parameter(string $value): string {
		if (preg_match('/^[!#$%&\x27*+.^_`|~0-9A-Za-z-]+$/D', $value)) {
			return $value;
		}
		if (!preg_match('/^"(?:[\x09\x20\x21\x23-\x5b\x5d-\xff]|\\\\[\x09\x20-\x7e\x80-\xff])*"$/D', $value)) {
			throw new \InvalidArgumentException('Invalid header parameter.');
		}
		return preg_replace('/\\\\([\s\S])/', '$1', substr($value, 1, -1));
	}

	/** Parse media type parameters and, only for ranges, a quality weight. */
	public static function parse(string $value, bool $range = false): array {
		$parts = self::split($value, ';');
		$type = strtolower(array_shift($parts));
		if (!preg_match('/^[!#$%&\x27*+.^_`|~0-9a-z-]+\/[!#$%&\x27*+.^_`|~0-9a-z-]+$/D', $type)) {
			throw new \InvalidArgumentException('Invalid media type.');
		}
		[$major, $minor] = explode('/', $type);
		if ((!$range and ($major === '*' or $minor === '*')) or ($major === '*' and $minor !== '*')) {
			throw new \InvalidArgumentException('Invalid media type wildcard.');
		}
		$params = [];
		$quality = 1000;
		foreach ($parts as $part) {
			if ($part === '') {
				continue;
			}
			if (!preg_match('/^([!#$%&\x27*+.^_`|~0-9A-Za-z-]+)=([\s\S]+)$/D', $part, $match)) {
				throw new \InvalidArgumentException('Invalid media type parameter.');
			}
			$name = strtolower($match[1]);
			if (array_key_exists($name, $params)) {
				throw new \InvalidArgumentException('Duplicate media type parameter.');
			}
			$params[$name] = self::parameter($match[2]);
			if ($name === 'charset') {
				$params[$name] = strtolower($params[$name]);
			}
			if ($range && $name === 'q') {
				if (!preg_match('/^(?:0(?:\.[0-9]{0,3})?|1(?:\.0{0,3})?)$/D', $match[2])) {
					throw new \InvalidArgumentException('Invalid Accept quality weight.');
				}
				$quality = (int)round((float)$match[2] * 1000);
			}
		}
		if ($range) {
			unset($params['q']);
		}
		return [$type, $params, $quality];
	}

	/** Apply Accept specificity and quality to the selected representation. */
	public static function accepts(?string $accept, string $representation = 'application/json'): bool {
		if ($accept === null) {
			return true;
		}
		$best = -1;
		$quality = 0;
		[$selected, $selectedParams] = self::parse($representation);
		[$selectedType] = explode('/', $selected);
		foreach (self::split($accept, ',') as $item) {
			if ($item === '') {
				continue;
			}
			[$type, $params, $q] = self::parse($item, true);
			// Every range parameter must match the selected representation.
			if (array_diff_assoc($params, $selectedParams)) {
				continue;
			}
			$specificity = match ($type) { $selected=>200 + count($params), $selectedType . '/*'=>100 + count($params), '*/*'=>count($params), default=>-1 };
			if ($specificity > $best) {
				$best = $specificity;
				$quality = $q;
			} elseif ($specificity === $best) {
				$quality = max($quality, $q);
			}
		}
		return $best >= 0 && $quality > 0;
	}

}
