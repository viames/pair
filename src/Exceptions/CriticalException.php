<?php

namespace Pair\Exceptions;

use Pair\Core\Application;
use Pair\Core\Logger;
use Pair\Html\TemplateRenderer;

/**
 * Custom exception for handling critical errors. In production mode, the application will be
 * terminated with a 500 error page, while in development mode the error message will be displayed.
 */
class CriticalException extends PairException {

	/**
	 * Constructor for the CriticalException, useful for terminating the application because
	 * of missing configuration or other critical errors.
	 *
	 * @param string $message The error message.
	 * @param int $code The error code.
	 * @param Throwable|null $previous Optional previous exception for exception chaining.
	 */
	public function __construct(string $message, int $code = 0, ?\Throwable $previous = null) {

		// intercept any previous message
		$trackedMessage = ($previous and $previous->getMessage()) ? $previous->getMessage() : $message;

		$logger = Logger::getInstance();
		$logger->emergency($trackedMessage, ['errorCode' => $code]);

		self::terminate($message, $code);

	}

	/**
	 * Performs appropriate operations to terminate the application following a critical error,
	 * paginating the cause of the error in the first available template.
	 * 
	 * @param string $message The error message to display.
	 * @param int $code The error code.
	 */
	public static function terminate(string $message, int $code = 0): void {

		if (ErrorCodes::LOADING_ENV_FILE == $code) {
			$logger = Logger::getInstance();
			$logger->disable();
		}

		$app = Application::getInstance();
		
		http_response_code(500);

		if ($app->headless) {
			return;
		}

		// clear any previous output
		ob_clean();

		$styleFile = self::getFallbackStyleFile();
		TemplateRenderer::parse($styleFile);

		exit;

	}

    /**
	 * Manage the template to load. In this case, the template is a simple PHP file that contains the HTML code to display the error message.
	 * 
	 * @return string The path to the template file.
	 */
    private static function getFallbackStyleFile(): string {

        $templatePath = APPLICATION_PATH . '/templates';

        if (!is_dir($templatePath)) {
			die ('Critical error: template folder not found.');
		}

		// preferred style is 500.php, fallback is default.php
		$styles = ['500.php', 'default.php'];

		// base directory to search for the template file
		$directory = new \RecursiveDirectoryIterator($templatePath);

		// search for the first available 500.php or default.php template style file.
		foreach ($styles as $style) {

			foreach (new \RecursiveIteratorIterator($directory) as $filename => $file) {

				if ($style == $file->getFilename()) {
					return $filename;
				}

			}

		}

		die ('Critical error: no template file found.');

	}

	/**
	 * Renders a simple fallback template with the given error message.
	 * 
	 * @param string $message The error message to display.
	 */
	private static function renderFallbackTemplate(string $message): void {

		$styleFile = self::getFallbackStyleFile();

		// load the template file
		ob_start();
		include $styleFile;
		$templateHtml = ob_get_clean();

		// replace the {{content}} placeholder with the error message
		$templateHtml = str_replace('{{content}}', htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $templateHtml);

		// output the final HTML
		print $templateHtml;

	}

}
