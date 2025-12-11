<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Meter extends FormControl {

	/**
	 * Minimum allowed length for value.
	 */
	protected int|float|null $min = null;

	/**
	 * Maximum allowed length for value.
	 */
	protected int|float|null $max = null;

	/**
	 * Range that is considered to be low within the meter’s scale.
	 */
	protected int|float|null $low = null;

	/**
	 * Range that is considered to be high within the meter’s scale.
	 */
	protected int|float|null $high = null;

	/**
	 * Range that is considered to be optimal within the meter’s scale.
	 */
	protected int|float|null $optimum = null;

	/**
	 * Set the minimum value for this control. Chainable method.
	 */
	public function min(int|float $minValue): self {

		$this->min = $minValue;
		return $this;

	}

	/**
	 * Set the maximum value for this control. Chainable method.
	 */
	public function max(int|float $maxValue): self {

		$this->max = $maxValue;
		return $this;

	}

	/**
	 * Set the low value for this control. Chainable method.
	 */
	public function low(int|float $lowValue): self {

		$this->low = $lowValue;
		return $this;

	}

	/**
	 * Set the high value for this control. Chainable method.
	 */
	public function high(int|float $highValue): self {

		$this->high = $highValue;
		return $this;

	}

	/**
	 * Set the optimum value for this control. Chainable method.
	 */
	public function optimum(int|float $optimumValue): self {

		$this->optimum = $optimumValue;
		return $this;

	}

	/**
	 * Renders a Meter field tag as HTML code.
	 */
	public function render(): string {

		$ret  = '<meter ' . $this->nameProperty();
		$ret .= ' value="' . $this->value . '"';
		$ret .= ' max="' . $this->max . '"';
		$ret .= $this->processProperties() . '>';
		$ret .= htmlspecialchars((string)$this->caption) . '</meter>';

		return $ret;

	}

}