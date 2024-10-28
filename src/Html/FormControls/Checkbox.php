<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Checkbox extends FormControl {

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

		$ret = '<input ' . $this->nameProperty(). ' type="checkbox" value="1"';

		if ($this->value) {
			$ret .= ' checked="checked"';
		}

		$ret .= $this->processProperties() . ' />';

		return $ret;

	}

	/**
	 * Checkbox's validation is always TRUE.
	 */
	public function validate(): bool {

		return TRUE;

	}

}