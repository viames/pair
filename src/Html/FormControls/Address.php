<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Address extends FormControl {

	/**
	 * Renders and returns an HTML input form control.
	 */
	public function render(): string {

		$ret = '<input ' . $this->nameProperty();

		$ret .= ' type="text" value="'. htmlspecialchars((string)$this->value) .'" size="50" autocomplete="on" placeholder=""';
		$this->class('googlePlacesAutocomplete');

		// set minlength attribute
		if ($this->minLength) {
			$ret .= ' minlength="' . (int)$this->minLength . '"';
		}

		// set maxlength attribute
		if ($this->maxLength) {
			$ret .= ' maxlength="' . (int)$this->maxLength . '"';
		}

		$ret .= $this->processProperties() . ' />';

		return $ret;

	}

}