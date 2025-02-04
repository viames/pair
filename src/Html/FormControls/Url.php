<?php

namespace Pair\Html\FormControls;

use Pair\Core\Logger;
use Pair\Helpers\Post;
use Pair\Html\FormControl;

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
			Logger::notice('Control validation on field “' . $this->name . '” has failed (url required)');
			return FALSE;
		}

		return parent::validate();

	}

}