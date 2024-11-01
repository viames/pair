<?php

namespace Pair\Html;

use Pair\Helpers\LogBar;

/**
 * Class for management of an HTML widget.
 */
class Widget {

	/**
	 * Path to the file with a trailing slash.
	 */
	private string $scriptPath = APPLICATION_PATH . '/widgets/';

	/**
	 * Renders the widget layout and returns it.
	 * @param	string	Name of widget file without file’s extension (.php).
	 */
	public function render(string $name): string {

		LogBar::event('Rendering ' . $name . ' widget');

		$file = $this->scriptPath . $name .'.php';

		// close buffer and parse file
		ob_start();
		require $file;
		$widget = ob_get_clean();

		return $widget;

	}

}