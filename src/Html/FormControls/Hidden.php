<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Hidden extends FormControl {

	public function render(): string {

		return parent::renderInput('hidden');

	}

}