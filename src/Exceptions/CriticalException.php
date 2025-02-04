<?php

namespace Pair\Exceptions;

use Pair\Core\Application;
use Pair\Core\Logger;

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
	 * @param Throwable|NULL $previous Optional previous exception for exception chaining.
	 */
	public function __construct(string $message, int $code = 0, ?\Throwable $previous = NULL) {

		$trackedMessage = ($previous and $previous->getMessage()) ? $previous->getMessage() : $message;
		self::track($trackedMessage);

		self::terminate($message, $code);

	}

	/**
	 * Performs appropriate operations to terminate the application following a critical error,
	 * paginating the cause of the error in the first available template.
	 */
	public static function terminate(string $message, int $code = 0): void {

		if (ErrorCodes::LOADING_ENV_FILE == $code) {
			$logger = Logger::getInstance();
			$logger->disable();
		}

		// get the style file to load
		$styleFile = self::getFallbackStyleFile();

		// load the style page file content
		$templateHtml = file_get_contents($styleFile);

		// replace minimal placeholders in the template
		$placeholders = [
			'content'	=> 'production' == Application::getEnvironment() ? 'Application is not available' : $message,
			'title'		=> 'Critical error'
		];
		
		foreach ($placeholders as $placeholder => $replacement) {
		
			// regex for both {{placeholder}} and {{ placeholder }}
			$pattern = '/\{\{\s*' . preg_quote($placeholder, '/') . '\s*\}\}/';
		
			// replace in template
			$templateHtml = preg_replace($pattern, $replacement, $templateHtml);
		
		}

		eval('?>' . $templateHtml);

		http_response_code(500);

		exit;

	}

    /**
	 * Manage the template to load. In this case, the template is a simple PHP file that contains the HTML code to display the error message.
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

}