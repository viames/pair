<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;
use Pair\Helpers\Post;
use Pair\Helpers\LogBar;

class Url extends FormControl {

	public function render(): string {

		return parent::renderInput('url');

	}

	/**
	 * Validates this control against empty values, minimum length, maximum length,
	 * and returns TRUE if is all set checks pass.
	 */
	public function validate(): bool {

		$value	= Post::get($this->name);

		if ($this->required and !filter_var($value, FILTER_VALIDATE_URL)) {
			LogBar::event('Control validation on field “' . $this->name . '” has failed (url required)');
			return FALSE;
		}

		return parent::validate();

	}

}