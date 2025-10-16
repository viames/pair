<?php

namespace Pair\Html\FormControls;

use Pair\Core\Logger;
use Pair\Helpers\Post;
use Pair\Html\FormControl;

class Number extends FormControl {

	/**
	 * Step value for number input controls.
	 */
	protected int|float|NULL $step = NULL;

	/**
	 * Minimum allowed length for value.
	 */
	protected int|float|NULL $min = NULL;

	/**
	 * Maximum allowed length for value.
	 */
	protected int|float|NULL $max = NULL;

	/**
	 * Set step value for input field of number type. Chainable method.
	 */
	public function step(int|float $value): self {

		$this->step = $value;
		return $this;

	}

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
	 * Renders and returns an HTML input form control.
	 */
	public function render(): string {

		$ret = '<input ' . $this->nameProperty();
		
		// adjust locale for number format
		$curr = setlocale(LC_NUMERIC, 0);
		setlocale(LC_NUMERIC, 'en_US');
		$ret .= ' type="number" value="' . (string)$this->value . '"';
		setlocale(LC_NUMERIC, $curr);

		if (!is_null($this->min)) {
			$ret .= ' min="' . (string)$this->min . '"';
		}

		if (!is_null($this->max)) {
			$ret .= ' max="' . (string)$this->max . '"';
		}

		// set minlength attribute
		if ($this->minLength) {
			$ret .= ' minlength="' . (int)$this->minLength . '"';
		}

		// set maxlength attribute
		if ($this->maxLength) {
			$ret .= ' maxlength="' . (int)$this->maxLength . '"';
		}

		// set step attribute
		if ($this->step) {
			$ret .= ' step="' . (string)$this->step . '"';
		}

		$ret .= $this->processProperties() . ' />';

		return $ret;

	}

	/**
	 * Validates this control against empty values, minimum length, maximum length,
	 * and returns TRUE if is all set checks pass.
	 */
	public function validate(): bool {

		$value	= Post::get($this->name);
		$valid	= TRUE;

		$logger = Logger::getInstance();

		if ($this->required and !is_numeric($value)) {
			$logger->notice('Control validation on field “' . $this->name . '” has failed (number required)');
			$valid = FALSE;
		}

		if ($this->min and $value < $this->min) {
			$logger->notice('Control validation on field “' . $this->name . '” has failed (min=' . $this->min . ')');
			$valid = FALSE;
		}

		if ($this->max and $value > $this->max) {
			$logger->notice('Control validation on field “' . $this->name . '” has failed (max=' . $this->max . ')');
			$valid = FALSE;
		}

		// check validity of minlength attribute
		if ($this->minLength and ''!=$value and strlen($value) < $this->minLength) {
			$logger->notice('Control validation on field “' . $this->name . '” has failed (minLength=' . $this->minLength . ')');
			$valid = FALSE;
		}

		// check validity of maxlength attribute
		if ($this->maxLength and strlen($value) > $this->maxLength) {
			$logger->notice('Control validation on field “' . $this->name . '” has failed (maxLength=' . $this->maxLength . ')');
			$valid = FALSE;
		}

		return $valid;

	}

}