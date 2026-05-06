<?php

namespace Pair\Html;

use Pair\Core\Logger;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Class for management of an HTML widget.
 */
class Widget {

	private ?string $name = null;

	/**
	 * Path to the file with a trailing slash.
	 */
	const WIDGET_FOLDER = APPLICATION_PATH . '/widgets/';

	/**
	 * Create a widget reference by file name without extension.
	 */
	public function __construct(string $name) {

		$this->name = $name;

	}

	/**
	 * Return read-only widget metadata.
	 */
	public function __get(string $name): mixed {

		return match ($name) {
			'name' => $this->name,
			default => null
		};

	}

	/**
	 * Returns the list of widget files available in the application.
	 *
	 * @return	array<int, string>
	 */
	public static function availableWidgets(): array {

		$widgets = [];
		$files = glob(self::WIDGET_FOLDER . '*.php');

		foreach ($files as $file) {
			$widgets[] = basename($file, '.php');
		}

		return $widgets;
	}

	/**
	 * Return true when a safe widget file exists for the provided name.
	 */
	public static function exists(string $name): bool {

		$file = self::filePath($name);

		return !is_null($file) and is_file($file);

	}

	/**
	 * Renders the widget layout and returns it.
	 * 
	 * @param	string	Name of widget file without file’s extension (.php).
	 */
	public function render(): string {

		$logger = Logger::getInstance();
		$logger->debug('Rendering ' . $this->name . ' widget');

		$file = self::filePath((string)$this->name);

		if (is_null($file) or !file_exists($file)) {
			throw new PairException('Widget file not found: ' . $this->name, ErrorCodes::WIDGET_NOT_FOUND);
		}

		// close buffer and parse file
		ob_start();
		require $file;
		$widget = ob_get_clean();

		return $widget;

	}

	/**
	 * Return the widget file path only for names that cannot escape the widgets directory.
	 */
	private static function filePath(string $name): ?string {

		if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
			return null;
		}

		return self::WIDGET_FOLDER . $name . '.php';

	}

}
