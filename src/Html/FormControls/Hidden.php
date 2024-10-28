<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Hidden extends FormControl {

	/**
	 * Extends parent constructor in order to sets default type to text.
	 *
	 * @param	string	Control name.
	 * @param	array	Additional attributes (tag=>value).
	 */
	public function __construct(string $name, array $attributes = []) {

		parent::__construct($name, $attributes);

	}

	/**
	 * Renders and returns an HTML input form control.
	 */
	public function render(): string {

		$ret = '<input ' . $this->nameProperty() . ' type="hidden"';
		$ret .= ' value="' . htmlspecialchars((string)$this->value) . '"';
		$ret .= $this->processProperties() . ' />';

		return $ret;

	}

}