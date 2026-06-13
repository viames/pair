<?php

namespace Pair\Html\FormControls;

use Pair\Core\Logger;
use Pair\Helpers\Post;
use Pair\Html\FormControl;

class Url extends FormControl {

	/**
	 * Renders this control as a URL input.
	 */
	public function render(): string {

		return parent::renderInput('url');

	}

	/**
	 * Validates this control against empty values, minimum length, maximum length,
	 * and the URL format when no validation preset is configured.
	 */
	public function validate(): bool {

		if (!is_null($this->validationPreset)) {
			return parent::validate();
		}

		$value	= Post::get($this->name);

		if ($this->required and !filter_var($value, FILTER_VALIDATE_URL)) {
			$logger = Logger::getInstance();
			$logger->notice('Control validation on field “' . $this->name . '” has failed (url required)');
			return false;
		}

		return parent::validate();

	}

}
