<?php

namespace Pair\Exceptions;

/**
 * Custom exception for handling critical errors.
 */
class CriticalException extends PairException {

	/**
	 * Constructor for the CriticalException.
	 *
	 * @param string $message The error message.
	 * @param int $code The error code.
	 * @param Exception|NULL $previous Optional previous exception for exception chaining.
	 */
	public function __construct(string $message, int $code = 0, ?\Throwable $previous = NULL) {

		$this->handleError($message, $code);

	}

	private function handleError(string $message, int $code): void {

		switch ($code) {

			// env file is not found
        	case ErrorCodes::LOADING_ENV_FILE:
				
				// cannot load the template info from DB, load the fallback template
				$this->loadFallbackTemplate();
				print '<div class="error-desc">' . $message . '</div>';
				break;

			// the template file is not found, so we load a fallback template
			case ErrorCodes::TEMPLATE_NOT_FOUND:
					
				$this->loadFallbackTemplate();
				print '<div class="error-desc">' . $message . '</div>';
				break;

		}

		http_response_code(500);

	}

    /**
	 * Manage the template to load. In this case, the template is a simple PHP file that contains the HTML code to display the error message.
	 */
    public function loadFallbackTemplate(): void {

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
					include ($file->getPathname());
					return;
				}
	
			}

		}

		die ('Critical error: template file not found.');

	}

}