<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Color extends FormControl {

	public function render(): string {

		return parent::renderInput('color');

	}

}