<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;
use Pair\Support\Post;
use Pair\Support\Logger;

class Email extends FormControl {

	public function render(): string {

		return parent::renderInput('email');

	}

	/**
	 * Validates this control against empty values, minimum length, maximum length,
	 * and returns TRUE if is all set checks pass.
	 */
	public function validate(): bool {

		$value	= Post::get($this->name);

		if ($this->required and !filter_var($value, FILTER_VALIDATE_EMAIL)) {
			Logger::event('Control validation on field “' . $this->name . '” has failed (email required)');
			return FALSE;
		}

		return parent::validate();

	}

}