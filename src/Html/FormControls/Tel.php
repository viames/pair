<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Tel extends FormControl {

	public function render(): string {

		return parent::renderInput('tel');

	}

}