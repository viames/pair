<?php

namespace Pair\Helpers;

/**
 * Provides safe SQL normalization and redaction helpers for LogBar output.
 */
final class LogBarSql {

	/**
	 * Placeholder used when a SQL value must not be rendered.
	 */
	private const REDACTED = '[redacted]';

	/**
	 * Return a stable short fingerprint for a query after value normalization.
	 */
	public static function fingerprint(string $query): string {

		return substr(hash('sha1', self::fingerprintSource($query)), 0, 16);

	}

	/**
	 * Return the leading SQL operation without exposing query values.
	 */
	public static function operation(string $query): string {

		if (preg_match('/^\s*([A-Za-z]+)/', $query, $matches)) {
			return strtoupper($matches[1]);
		}

		return 'UNKNOWN';

	}

	/**
	 * Return normalized SQL with placeholders instead of literal or bound values.
	 */
	public static function normalize(string $query): string {

		$query = self::replaceQuotedStrings($query, '?');
		$query = self::stripComments($query);
		$query = preg_replace('/:[A-Za-z_][A-Za-z0-9_]*/', '?', $query) ?? $query;
		$query = preg_replace('/\b0x[0-9A-F]+\b/i', '?', $query) ?? $query;
		$query = preg_replace('/(?<![A-Za-z0-9_`])[-+]?\d+(?:\.\d+)?(?![A-Za-z0-9_`])/', '?', $query) ?? $query;

		return self::collapseWhitespace($query);

	}

	/**
	 * Redact sensitive free-form LogBar text before it is rendered.
	 */
	public static function redactText(string $text): string {

		$text = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer ' . self::REDACTED, $text) ?? $text;
		$text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', self::REDACTED, $text) ?? $text;

		// Mask key-value fragments that usually carry credentials or stable identifiers.
		return preg_replace(
			'/\b([A-Za-z0-9_.-]*(?:token|secret|password|authorization|cookie|session|sid|email|key|hash)[A-Za-z0-9_.-]*)\b(\s*[:=]\s*)("[^"]*"|\'[^\']*\'|[^\s,;&?]+)/i',
			'$1$2' . self::REDACTED,
			$text
		) ?? $text;

	}

	/**
	 * Return the SQL text that may be rendered in LogBar for a query event.
	 *
	 * @param	array<int|string, mixed>	$params	Bound parameters, if any.
	 */
	public static function render(string $query, array $params = [], bool $showValues = false): string {

		if (!$showValues) {
			return self::normalize($query);
		}

		$query = self::stripComments($query);
		$query = self::substituteParameters($query, $params);

		return self::redactInlineValues($query);

	}

	/**
	 * Return a best-effort primary table name for the query.
	 */
	public static function table(string $query): string {

		$operation = self::operation($query);
		$patterns = match ($operation) {
			'INSERT', 'REPLACE' => ['/^\s*(?:INSERT|REPLACE)\s+(?:IGNORE\s+)?INTO\s+(`?)([A-Za-z0-9_.$-]+)\1/i'],
			'UPDATE' => ['/^\s*UPDATE\s+(`?)([A-Za-z0-9_.$-]+)\1/i'],
			'DESCRIBE', 'DESC' => ['/^\s*(?:DESCRIBE|DESC)\s+(`?)([A-Za-z0-9_.$-]+)\1/i'],
			'SHOW' => ['/\bFROM\s+(`?)([A-Za-z0-9_.$-]+)\1/i'],
			default => ['/\bFROM\s+(`?)([A-Za-z0-9_.$-]+)\1/i'],
		};

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $query, $matches)) {
				return trim($matches[2], '`');
			}
		}

		return '';

	}

	/**
	 * Collapse repeated placeholder lists so equivalent parameter counts share one key.
	 */
	private static function collapsePlaceholderLists(string $query): string {

		return preg_replace('/\(\s*\?(?:\s*,\s*\?)+\s*\)/', '(?)', $query) ?? $query;

	}

	/**
	 * Collapse whitespace to make previews and fingerprints stable.
	 */
	private static function collapseWhitespace(string $query): string {

		return trim(preg_replace('/\s+/', ' ', $query) ?? $query);

	}

	/**
	 * Return the normalized string used as fingerprint input.
	 */
	private static function fingerprintSource(string $query): string {

		return strtolower(self::collapsePlaceholderLists(self::normalize($query)));

	}

	/**
	 * Format one bound value for an opt-in SQL preview.
	 */
	private static function formatValue(mixed $value, string $context): string {

		if (self::isSensitiveContext($context) or self::isSensitiveValue($value)) {
			return "'" . self::REDACTED . "'";
		}

		if (is_null($value)) {
			return 'NULL';
		}

		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}

		if (is_int($value) or is_float($value)) {
			return (string)$value;
		}

		if ($value instanceof \DateTimeInterface) {
			return "'" . str_replace("'", "''", $value->format('Y-m-d H:i:s')) . "'";
		}

		if (is_array($value)) {
			return "'" . self::REDACTED . "'";
		}

		if (is_object($value)) {
			return "'" . self::REDACTED . "'";
		}

		return "'" . str_replace("'", "''", (string)$value) . "'";

	}

	/**
	 * Find the zero-based offset of the nth positional placeholder outside strings.
	 */
	private static function findPlaceholderOffset(string $query, int $targetIndex): ?int {

		$index = 0;
		$length = strlen($query);
		$quote = null;

		for ($i = 0; $i < $length; $i++) {

			$char = $query[$i];

			if ($quote) {
				if ($char === $quote) {
					if ($i + 1 < $length and $query[$i + 1] === $quote) {
						$i++;
					} else {
						$quote = null;
					}
				} else if ($char === '\\') {
					$i++;
				}
				continue;
			}

			if ($char === "'" or $char === '"') {
				$quote = $char;
				continue;
			}

			if ($char === '?') {
				if ($index === $targetIndex) {
					return $i;
				}
				$index++;
			}

		}

		return null;

	}

	/**
	 * Infer a nearby column or parameter name for sensitivity checks.
	 */
	private static function inferContext(string $query, int $offset): string {

		$before = substr($query, max(0, $offset - 120), 120);

		if (preg_match('/([`A-Za-z0-9_.-]+)\s*(?:=|<>|!=|<|>|<=|>=|LIKE|IN\s*\()?\s*$/i', $before, $matches)) {
			return trim($matches[1], '`');
		}

		return '';

	}

	/**
	 * Return true when a parameter name or nearby SQL column is sensitive.
	 */
	private static function isSensitiveContext(string $context): bool {

		return (bool)preg_match('/(token|secret|password|authorization|cookie|session|sid|email|key|hash)/i', $context);

	}

	/**
	 * Return true when a scalar value looks like data LogBar should not render.
	 */
	private static function isSensitiveValue(mixed $value): bool {

		if (!is_string($value)) {
			return false;
		}

		if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value)) {
			return true;
		}

		return (bool)preg_match('/^Bearer\s+[A-Za-z0-9._~+\/=-]+$/i', $value);

	}

	/**
	 * Redact inline SQL literals whose surrounding column names look sensitive.
	 */
	private static function redactInlineValues(string $query): string {

		$query = self::replaceQuotedStrings($query, function (string $literal, int $offset) use ($query): string {
			$context = self::inferContext($query, $offset);

			if (self::isSensitiveContext($context) or self::isSensitiveValue($literal)) {
				return "'" . self::REDACTED . "'";
			}

			return "'" . str_replace("'", "''", $literal) . "'";
		});

		return preg_replace(
			'/((?:`?[A-Za-z0-9_.-]*(?:token|secret|password|authorization|cookie|session|sid|email|key|hash)[A-Za-z0-9_.-]*`?)\s*(?:=|<>|!=|<|>|<=|>=|LIKE)\s*)([-+]?\d+(?:\.\d+)?)/i',
			'$1' . self::REDACTED,
			$query
		) ?? $query;

	}

	/**
	 * Replace quoted SQL strings while respecting doubled and backslash escapes.
	 *
	 * @param	string|callable(string, int): string	$replacement	Static replacement or callback receiving literal and offset.
	 */
	private static function replaceQuotedStrings(string $query, string|callable $replacement): string {

		$result = '';
		$length = strlen($query);

		for ($i = 0; $i < $length; $i++) {

			$char = $query[$i];

			if ($char !== "'" and $char !== '"') {
				$result .= $char;
				continue;
			}

			$quote = $char;
			$offset = $i;
			$literal = '';
			$i++;

			for (; $i < $length; $i++) {

				$inner = $query[$i];

				if ($inner === $quote) {
					if ($i + 1 < $length and $query[$i + 1] === $quote) {
						$literal .= $quote;
						$i++;
						continue;
					}
					break;
				}

				if ($inner === '\\' and $i + 1 < $length) {
					$i++;
					$literal .= $query[$i];
					continue;
				}

				$literal .= $inner;

			}

			$result .= is_callable($replacement) ? $replacement($literal, $offset) : $replacement;

		}

		return $result;

	}

	/**
	 * Remove SQL comments from log previews so comments cannot carry secrets.
	 */
	private static function stripComments(string $query): string {

		$result = '';
		$length = strlen($query);
		$quote = null;

		for ($i = 0; $i < $length; $i++) {

			$char = $query[$i];

			if ($quote) {
				$result .= $char;

				if ($char === $quote) {
					if ($i + 1 < $length and $query[$i + 1] === $quote) {
						$i++;
						$result .= $query[$i];
					} else {
						$quote = null;
					}
				} else if ($char === '\\' and $i + 1 < $length) {
					$i++;
					$result .= $query[$i];
				}

				continue;
			}

			if ($char === "'" or $char === '"') {
				$quote = $char;
				$result .= $char;
				continue;
			}

			if ($char === '/' and $i + 1 < $length and $query[$i + 1] === '*') {
				$i += 2;
				while ($i < $length - 1 and !($query[$i] === '*' and $query[$i + 1] === '/')) {
					$i++;
				}
				$i++;
				$result .= ' ';
				continue;
			}

			if ($char === '-' and $i + 1 < $length and $query[$i + 1] === '-') {
				while ($i < $length and !in_array($query[$i], ["\r", "\n"], true)) {
					$i++;
				}
				$result .= ' ';
				continue;
			}

			if ($char === '#') {
				while ($i < $length and !in_array($query[$i], ["\r", "\n"], true)) {
					$i++;
				}
				$result .= ' ';
				continue;
			}

			$result .= $char;

		}

		return $result;

	}

	/**
	 * Substitute bound parameters with opt-in redacted values for legacy-style previews.
	 *
	 * @param	array<int|string, mixed>	$params	Bound parameters, if any.
	 */
	private static function substituteParameters(string $query, array $params): string {

		if (!$params) {
			return $query;
		}

		$indexed = array_values($params) === $params;

		return $indexed
			? self::substitutePositionalParameters($query, $params)
			: self::substituteNamedParameters($query, $params);

	}

	/**
	 * Substitute named parameters without revealing sensitive values.
	 *
	 * @param	array<int|string, mixed>	$params	Bound parameters keyed by placeholder name.
	 */
	private static function substituteNamedParameters(string $query, array $params): string {

		uksort($params, function (int|string $left, int|string $right): int {
			return strlen((string)$right) <=> strlen((string)$left);
		});

		foreach ($params as $name => $value) {

			$placeholder = ':' . ltrim((string)$name, ':');
			$offset = strpos($query, $placeholder);
			$context = trim((string)$name, ':');

			if (false !== $offset) {
				$context .= ' ' . self::inferContext($query, $offset);
			}

			$query = str_replace($placeholder, self::formatValue($value, $context), $query);

		}

		return $query;

	}

	/**
	 * Substitute positional parameters without touching question marks inside strings.
	 *
	 * @param	array<int, mixed>	$params	Bound parameters in positional order.
	 */
	private static function substitutePositionalParameters(string $query, array $params): string {

		$result = '';
		$length = strlen($query);
		$quote = null;
		$index = 0;

		for ($i = 0; $i < $length; $i++) {

			$char = $query[$i];

			if ($quote) {
				$result .= $char;

				if ($char === $quote) {
					if ($i + 1 < $length and $query[$i + 1] === $quote) {
						$i++;
						$result .= $query[$i];
					} else {
						$quote = null;
					}
				} else if ($char === '\\' and $i + 1 < $length) {
					$i++;
					$result .= $query[$i];
				}

				continue;
			}

			if ($char === "'" or $char === '"') {
				$quote = $char;
				$result .= $char;
				continue;
			}

			if ($char === '?' and array_key_exists($index, $params)) {
				$offset = self::findPlaceholderOffset($query, $index) ?? $i;
				$result .= self::formatValue($params[$index], self::inferContext($query, $offset));
				$index++;
				continue;
			}

			$result .= $char;

		}

		return $result;

	}

}
