<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Password extends FormControl {

	public function render(): string {

		return parent::renderInput('password');

	}

}