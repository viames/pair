<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Text extends FormControl {

	public function render(): string {

		return parent::renderInput('text');

	}

}