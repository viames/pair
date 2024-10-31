<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Button extends FormControl {

	/**
	 * Button type submit|reset|button, default is button.
	 */
	private string $type = 'button';

	/**
	 * FontAwesome icon class.
	 */
	private ?string $faIcon = NULL;

	/**
	 * Set type for a Button (submit, reset, button). Chainable method.
	 */
	public function type(string $type): self {

		$this->type = $type;
		return $this;

	}

	/**
	 * Set a FontAwesome icon for this button object. Chainable method.
	 */
	public function faIcon(string $class): self {

		$this->faIcon = $class;
		return $this;

	}

	/**
	 * Render an HTML button form control with an optional FontAwesome icon prefixed.
	 */
	public function render(): string {

		$ret = '<button type="' . $this->type . '"' ;

		if ($this->id) {
			$ret .= 'id=' . $this->id;
		}

		if ($this->name) {
			$ret .= ' ' . $this->nameProperty();
		}

		$ret .= $this->processProperties() . '>';

		if ($this->faIcon) {
			$ret .= '<i class="fa ' . $this->faIcon . '"></i> ';
		}

		$ret .= trim(htmlspecialchars((string)$this->value)) . ' </button>';

		return $ret;

	}

	/**
	 * Button’s validation is always TRUE.
	 */
	public function validate(): bool {

		return TRUE;

	}

}