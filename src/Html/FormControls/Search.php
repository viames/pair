<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Search extends FormControl {

	public function render(): string {

		return parent::renderInput('search');

	}

}