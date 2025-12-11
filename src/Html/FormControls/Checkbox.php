<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Checkbox extends FormControl {

	/**
	 * Renders and returns the checkbox HTML form control.
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
	 * Checkbox's validation is always true.
	 */
	public function validate(): bool {

		return true;

	}

}