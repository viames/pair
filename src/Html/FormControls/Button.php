<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Button extends FormControl {

	/**
	 * Button type (submit, reset, button).
	 * @var string
	 */
	private $type;

	/**
	 * FontAwesome icon class.
	 * @var string
	 */
	private $faIcon;

	/**
	 * Sets type for a Button (submit, reset, button). Chainable method.
	 *
	 * @param	string	The button type.
	 */
	public function type(string $type): self {

		$this->type = $type;
		return $this;

	}

	/**
	 * Sets a FontAwesome icon for this button object. Chainable method.
	 *
	 * @param	string	The icon class.
	 */
	public function setFaIcon(string $class): self {

		$this->faIcon = $class;
		return $this;

	}

	/**
	 * Renders an HTML button form control prepending an optional FontAwesome icon.
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