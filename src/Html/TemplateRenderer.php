<?php

namespace Pair\Html;

use Pair\Core\Application;

/**
 * Responsible for rendering HTML templates with dynamic content.
 */
class TemplateRenderer {

	/**
	 * Parses the template style file and replaces placeholders with HTML code.
	 */
	public static function parse(string $styleFile): void {

		$app = Application::getInstance();

		// load the style page file
		$templateHtml = file_get_contents($styleFile);

		// find all widget placeholders in the template
		foreach (Widget::availableWidgets() as $name) {

			// prepare the regex pattern to find the widget placeholder
			$pattern = '/\{\{\s*' . preg_quote($name, '/') . '\s*\}\}/';

			// check if the widget placeholder exists in the template
			if (preg_match($pattern, $templateHtml)) {

				// create the widget instance
				$widget = new Widget($name);

				// replace the widget placeholder with the rendered HTML
				$templateHtml = preg_replace($pattern, $widget->render(), $templateHtml, 1);

			}

		}

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

}
