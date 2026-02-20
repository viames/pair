<?php

namespace Pair\Helpers;

use Pair\Core\Router;

/**
 * Lightweight Markdown converter optimized for speed. Handles a small Markdown
 * subset intended for simple pages.
 */
class Markdown {

	/**
	 * Convert inline Markdown elements to HTML.
	 * 
	 * @param string $text The text to convert.
	 * @return string The converted HTML.
	 */
	private static function inline(string $text): string {

		// escape HTML special characters to prevent injection, while allowing valid tags
		$text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		$codeSpans = [];
		// protect inline code from further formatting
		$text = preg_replace_callback('/`([^`]+)`/', function($match) use (&$codeSpans) {
			$key = '[[CODE' . count($codeSpans) . ']]';
			$codeSpans[$key] = '<code>' . $match[1] . '</code>';
			return $key;
		}, $text);

		// strong emphasis must be processed before regular emphasis to avoid conflicts
		$text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
		$text = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $text);

		// regular emphasis (not preceded or followed by another * or _ to avoid conflicts with strong emphasis)
		$text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text);
		$text = preg_replace('/(?<!_)_([^_]+)_(?!_)/', '<em>$1</em>', $text);

		// images must be processed before links to avoid conflicts
		$text = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', function($match) {
			$src = self::sanitizeUrl($match[2]);
			if ('' === $src) {
				return $match[1];
			}
			return '<img alt="' . $match[1] . '" src="' . $src . '">';
		}, $text);

		// links (must be processed after images to avoid conflicts with image syntax)
		$text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function($match) {
			$href = self::sanitizeUrl($match[2]);
			if ('' === $href) {
				return $match[1];
			}
			// convert internal links starting with # to absolute URLs based on the current page URL
			if ('#' === $href[0]) {
				$href = Router::getInstance()->getUrl() . $href;
			}
			return '<a href="' . $href . '">' . $match[1] . '</a>';
		}, $text);

		if ($codeSpans) {
			// restore inline code after other replacements
			$text = strtr($text, $codeSpans);
		}

		return str_replace("\n", '<br>', $text);

	}

	/**
	 * Sanitize a URL for use in links and images.
	 * 
	 * @param string $url The URL to sanitize.
	 * @return string The sanitized URL, or empty string if invalid.
	 */
	private static function sanitizeUrl(string $url): string {

		$url = trim($url);
		if ('' === $url) {
			return '';
		}

		// allow fragment identifiers for internal links
		if ('#' === $url[0]) {
			return $url;
		}

		// parse_url returns false on malformed URLs, which we treat as invalid
		$parts = parse_url($url);
		if (false === $parts) {
			return '';
		}

		// URLs without a scheme are treated as relative and allowed by default
		if (!isset($parts['scheme'])) {
			return $url;
		}

		$scheme = strtolower($parts['scheme']);
		$allowedSchemes = ['http', 'https', 'mailto', 'tel'];

		// reject dangerous schemes like javascript:
		return in_array($scheme, $allowedSchemes, true) ? $url : '';

	}

	/**
	 * Determine whether a line is a potential table row.
	 * 
	 * @param string $line The line to check.
	 * @return bool True if the line can be interpreted as a table row.
	 */
	private static function isTableRow(string $line): bool {

		$line = trim($line);
		if ('' === $line) {
			return false;
		}

		if (str_starts_with($line, '|') and str_ends_with($line, '|')) {
			return 2 <= substr_count($line, '|');
		}

		return false;

	}

	/**
	 * Determine whether a line is a valid Markdown table separator row.
	 * 
	 * @param string $line The line to check.
	 * @return bool True when the line matches a table separator pattern.
	 */
	private static function isTableSeparator(string $line): bool {

		if (!self::isTableRow($line)) {
			return false;
		}

		$cells = self::splitTableRow($line);
		if ([] === $cells) {
			return false;
		}

		foreach ($cells as $cell) {
			if (!preg_match('/^:?-{3,}:?$/', $cell)) {
				return false;
			}
		}

		return true;

	}

	/**
	 * Convert a Markdown table alignment cell to CSS text-align value.
	 * 
	 * @param string $separatorCell The separator cell (e.g. :---, ---:, :---:).
	 * @return string|null The CSS alignment value or null when not specified.
	 */
	private static function parseTableAlignment(string $separatorCell): ?string {

		$separatorCell = trim($separatorCell);
		$hasLeft = str_starts_with($separatorCell, ':');
		$hasRight = str_ends_with($separatorCell, ':');

		if ($hasLeft and $hasRight) {
			return 'center';
		}

		if ($hasRight) {
			return 'right';
		}

		if ($hasLeft) {
			return 'left';
		}

		return null;

	}

	/**
	 * Render a Markdown table block as HTML.
	 * 
	 * @param array<int, string> $tableLines Raw table lines including header, separator, and optional rows.
	 * @return string The rendered table HTML.
	 */
	private static function renderTable(array $tableLines): string {

		$headerCells = self::splitTableRow($tableLines[0]);
		$separatorCells = self::splitTableRow($tableLines[1]);
		$alignments = [];
		foreach ($separatorCells as $separatorCell) {
			$alignments[] = self::parseTableAlignment($separatorCell);
		}

		$maxColumns = max(count($headerCells), count($alignments));
		if (0 === $maxColumns) {
			return '';
		}

		$headerCells = array_pad($headerCells, $maxColumns, '');
		$alignments = array_pad($alignments, $maxColumns, null);

		$html = [];
		$html[] = '<table>';
		$html[] = '<thead>';
		$html[] = '<tr>';

		for ($i = 0; $i < $maxColumns; $i++) {
			$style = null === $alignments[$i] ? '' : ' style="text-align:' . $alignments[$i] . '"';
			$html[] = '<th' . $style . '>' . self::inline($headerCells[$i]) . '</th>';
		}

		$html[] = '</tr>';
		$html[] = '</thead>';

		if (2 < count($tableLines)) {
			$html[] = '<tbody>';
			for ($rowIndex = 2; $rowIndex < count($tableLines); $rowIndex++) {
				$rowCells = array_pad(self::splitTableRow($tableLines[$rowIndex]), $maxColumns, '');
				$html[] = '<tr>';
				for ($i = 0; $i < $maxColumns; $i++) {
					$style = null === $alignments[$i] ? '' : ' style="text-align:' . $alignments[$i] . '"';
					$html[] = '<td' . $style . '>' . self::inline($rowCells[$i]) . '</td>';
				}
				$html[] = '</tr>';
			}
			$html[] = '</tbody>';
		}

		$html[] = '</table>';

		return implode("\n", $html);

	}

	/**
	 * Split a Markdown table row into cells.
	 * 
	 * @param string $line The table row line.
	 * @return array<int, string> The trimmed table cells.
	 */
	private static function splitTableRow(string $line): array {

		$line = trim($line);
		$line = trim($line, '|');
		if ('' === $line) {
			return [];
		}

		$cells = explode('|', $line);

		return array_map('trim', $cells);

	}

	/**
	 * Generate a URL-friendly slug from a heading text, compatible with common
	 * Markdown implementations (GitHub, GitLab, etc.).
	 * 
	 * @param string $text The heading text to slugify.
	 * @return string The generated slug.
	 */
	private static function slugify(string $text): string {

		// strip inline markdown (bold, italic, code, links, images)
		$text = strip_tags(self::inline($text));
		$text = mb_strtolower($text, 'UTF-8');
		$text = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $text);
		$text = preg_replace('/[\s-]+/', '-', trim($text));

		return $text;

	}

	/**
	 * Convert a Markdown string to HTML.
	 * 
	 * @param string $markdown The Markdown content.
	 * @return string The converted HTML.
	 */
	public static function toHtml(string $markdown): string {

		$markdown = trim($markdown);

		if ('' === $markdown) {
			return '';
		}

		$markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
		$lines = explode("\n", $markdown);

		$html = [];
		$paragraph = '';
		$listType = null;
		$inCodeBlock = false;
		$codeLines = [];

		$flushParagraph = function() use (&$paragraph, &$html): void {
			// render the buffered paragraph as a single block
			if ('' === $paragraph) {
				return;
			}
			$html[] = '<p>' . self::inline($paragraph) . '</p>';
			$paragraph = '';
		};

		$closeList = function() use (&$listType, &$html): void {
			// close the current list when leaving list context
			if ($listType) {
				$html[] = '</' . $listType . '>';
				$listType = null;
			}
		};

		for ($lineIndex = 0; $lineIndex < count($lines); $lineIndex++) {

			$line = $lines[$lineIndex];

			// handle fenced code blocks first, since they disable all other formatting
			if (preg_match('/^```/', $line)) {
				// toggle fenced code blocks
				if ($inCodeBlock) {
					$html[] = '<pre><code>' . htmlspecialchars(implode("\n", $codeLines), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
					$codeLines = [];
					$inCodeBlock = false;
				} else {
					$flushParagraph();
					$closeList();
					$inCodeBlock = true;
				}
				continue;
			}

			// inside a code block, just buffer lines until we hit the closing fence
			if ($inCodeBlock) {
				$codeLines[] = $line;
				continue;
			}

			// blank lines separate paragraphs and close lists
			if ('' === trim($line)) {
				$flushParagraph();
				$closeList();
				continue;
			}

			// tables (header row followed by a separator row, optional data rows)
			if (isset($lines[$lineIndex + 1]) and self::isTableRow($line) and self::isTableSeparator($lines[$lineIndex + 1])) {
				$flushParagraph();
				$closeList();

				$tableLines = [$line, $lines[$lineIndex + 1]];
				$lineIndex += 2;
				while ($lineIndex < count($lines) and self::isTableRow($lines[$lineIndex])) {
					$tableLines[] = $lines[$lineIndex];
					$lineIndex++;
				}
				$lineIndex--;

				$html[] = self::renderTable($tableLines);
				continue;
			}

			// headings (lines starting with 1-6 # characters)
			if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $match)) {
				$flushParagraph();
				$closeList();
				$level = strlen($match[1]);
				$slug = self::slugify($match[2]);
				$html[] = '<h' . $level . ' id="' . $slug . '">' . self::inline($match[2]) . '</h' . $level . '>';
				continue;
			}

			// unordered lists (lines starting with -, *, or +)
			if (preg_match('/^\s*([-*+])\s+(.*)$/', $line, $match)) {
				$flushParagraph();
				if ('ul' !== $listType) {
					$closeList();
					$html[] = '<ul>';
					$listType = 'ul';
				}
				$html[] = '<li>' . self::inline($match[2]) . '</li>';
				continue;
			}

			// ordered lists (lines starting with a number followed by . or ))
			if (preg_match('/^\s*\d+[.)]\s+(.*)$/', $line, $match)) {
				$flushParagraph();
				if ('ol' !== $listType) {
					$closeList();
					$html[] = '<ol>';
					$listType = 'ol';
				}
				$html[] = '<li>' . self::inline($match[1]) . '</li>';
				continue;
			}

			// horizontal rules (lines containing only 3 or more -, *, or _ characters)
			if (preg_match('/^\s*([-*_])(?:\s*\\1){2,}\s*$/', $line)) {
				$flushParagraph();
				$closeList();
				$html[] = '<hr>';
				continue;
			}

			// blockquotes (lines starting with >)
			if (preg_match('/^>\s?(.*)$/', $line, $match)) {
				$flushParagraph();
				$closeList();
				$html[] = '<blockquote>' . self::inline($match[1]) . '</blockquote>';
				continue;
			}

			$closeList();

			if ('' === $paragraph) {
				$paragraph = $line;
			} else {
				$paragraph .= "\n" . $line;
			}

		}

		if ($inCodeBlock) {
			// close unbalanced code fences
			$html[] = '<pre><code>' . htmlspecialchars(implode("\n", $codeLines), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
		}

		$flushParagraph();
		$closeList();

		return implode("\n", $html);

	}

}
