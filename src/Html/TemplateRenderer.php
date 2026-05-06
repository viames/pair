<?php

namespace Pair\Html;

use Pair\Core\Application;
use Pair\Core\Observability;

/**
 * Responsible for rendering HTML templates with dynamic content.
 */
class TemplateRenderer {

	/**
	 * Process-local template file cache keyed by path, mtime and size.
	 *
	 * @var	array<string, array{signature: string, html: string}>
	 */
	private static array $styleFileCache = [];

	/**
	 * Parses the template style file and replaces placeholders with HTML code.
	 */
	public static function parse(string $styleFile): void {

		Observability::trace('template.render', function () use ($styleFile): void {
			self::renderStyleFile($styleFile);
		}, [
			'template' => basename($styleFile),
		]);

	}

	/**
	 * Render the template style file and replace placeholders with HTML code.
	 */
	private static function renderStyleFile(string $styleFile): void {

		$app = Application::getInstance();

		// load the style page file
		$templateHtml = self::loadStyleFile($styleFile);

		// render only widgets that are actually referenced by this template.
		$templateHtml = self::renderWidgets($templateHtml);

		// placeholders to replace with $app properties
		$placeholders = [
			'langCode'	=> 'langCode',
			'title'		=> 'pageTitle',
			'heading'	=> 'pageHeading',
			'content'	=> 'pageContent',
			'logBar'	=> 'logBar'
		];

		// common placeholders
		$commons = [
			'baseHref'	=> BASE_HREF,
			'styles'	=> $app->styles(),
			'scripts'	=> $app->scripts()
		];

		// get all variables set in $app
		$vars = $app->getVars();

		// replace all placeholders with their values
		$templateHtml = preg_replace_callback(
			'/\{\{\s*([A-Za-z0-9_:-]+)\s*\}\}/',
			function(array $matches) use ($app, $placeholders, $vars, $commons): string {

				// get the placeholder name
				$name = $matches[1];

				// check if it's a property of $app
				if (array_key_exists($name, $placeholders)) {
					$property = $placeholders[$name];
					$value = $app->$property ?? null;
					return $value !== null ? (string) $value : '';
				}

				// check if it's a common placeholder
				if (array_key_exists($name, $commons)) {
					return (string) $commons[$name];
				}

				// check if it's a variable set in $app
				if (array_key_exists($name, $vars)) {
					$value = $vars[$name];
					// convert to string if scalar or object with __toString
					if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
						return (string) $value;
					}
				}

				return '';

			},
			$templateHtml
		);

		print $templateHtml;

	}

	/**
	 * Load a template style file once per process while invalidating on file changes.
	 */
	private static function loadStyleFile(string $styleFile): string {

		clearstatcache(true, $styleFile);

		$signature = (string)(filemtime($styleFile) ?: 0) . ':' . (string)(filesize($styleFile) ?: 0);

		if (isset(self::$styleFileCache[$styleFile]) and self::$styleFileCache[$styleFile]['signature'] === $signature) {
			return self::$styleFileCache[$styleFile]['html'];
		}

		$html = file_get_contents($styleFile);
		$html = is_string($html) ? $html : '';

		self::$styleFileCache[$styleFile] = [
			'signature' => $signature,
			'html' => $html,
		];

		return $html;

	}

	/**
	 * Replace widget placeholders without scanning the whole widget directory.
	 */
	private static function renderWidgets(string $templateHtml): string {

		return preg_replace_callback(
			'/\{\{\s*([A-Za-z0-9_-]+)\s*\}\}/',
			function(array $matches): string {

				$name = $matches[1];

				// non-widget placeholders are preserved for the application placeholder pass.
				if (!Widget::exists($name)) {
					return $matches[0];
				}

				return (new Widget($name))->render();

			},
			$templateHtml
		) ?? $templateHtml;

	}

}
