<?php

namespace Pair\Html;

use Pair\Core\Logger;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Class for management of an HTML widget.
 */
class Widget {

	private ?string $name = NULL;

	/**
	 * Path to the file with a trailing slash.
	 */
	const WIDGET_FOLDER = APPLICATION_PATH . '/widgets/';

	public function __construct(string $name) {

		$this->name = $name;

	}

	public function __get(string $name): mixed {

		return match ($name) {
			'name' => $this->name,
			default => NULL
		};

	}

	public static function availableWidgets(): array {

		$widgets = [];
		$files = glob(self::WIDGET_FOLDER . '*.php');

		foreach ($files as $file) {
			$widgets[] = basename($file, '.php');
		}

		return $widgets;
	}

	/**
	 * Renders the widget layout and returns it.
	 * 
	 * @param	string	Name of widget file without file’s extension (.php).
	 */
	public function render(): string {

		Logger::notice('Rendering ' . $this->name . ' widget', Logger::DEBUG);

		$file = self::WIDGET_FOLDER . $this->name .'.php';

		if (!file_exists($file)) {
			throw new PairException('Widget file not found: ' . $this->name, ErrorCodes::WIDGET_NOT_FOUND);
		}

		// close buffer and parse file
		ob_start();
		require $file;
		$widget = ob_get_clean();

		return $widget;

	}

}