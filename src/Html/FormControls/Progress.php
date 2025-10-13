<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Progress extends FormControl {

	/**
	 * Maximum allowed length for value.
	 */
	protected int|float|NULL $max = NULL;

	/**
	 * Set the maximum value for this control. It’s a chainable method.
	 */
	public function max(int|float $maxValue): self {

		$this->max = $maxValue;
		return $this;

	}

	/**
	 * Renders a Progress field tag as HTML code.
	 */
	public function render(): string {

		$ret  = '<progress ' . $this->nameProperty();
		$ret .= ' value="' . $this->value . '"';
		$ret .= ' max="' . $this->max . '"';
		$ret .= $this->processProperties() . '>';
		$ret .= htmlspecialchars((string)$this->caption) . '</progress>';

		return $ret;

	}

}