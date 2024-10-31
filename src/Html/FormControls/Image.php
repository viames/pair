<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Image extends FormControl {

	public function render(): string {

		return parent::renderInput('image');

	}

}