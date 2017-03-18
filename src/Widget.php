<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

/**
 * Class for management of an HTML widget.
 */
class Widget {
	
	/**
	 * Path to the file with a trailing slash.
	 * @var string
	 */
	private $scriptPath = 'widgets/';
	
	/**
	 * Renders the widget layout and returns it.
	 * 
	 * @param	string	Name of widget file without file’s extension (.php).
	 * @return	string
	 */
	public function render($name) {
		
		$logger = Logger::getInstance();
		
		$logger->addEvent('Rendering widget ' . $name);
		
		$file = $this->scriptPath . $name .'.php';

		// close buffer and parse file
		ob_start();
		$script = require $file;
		$widget = ob_get_clean();

		return $widget;
		
	}
	
}
