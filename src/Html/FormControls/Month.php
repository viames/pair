<?php

namespace Pair\Html\FormControls;

use Pair\Core\Env;
use Pair\Html\FormControl;

class Month extends FormControl {

	/**
	 * Minimum allowed length for value.
	 */
	protected string|DateTime|NULL $min = NULL;

	/**
	 * Maximum allowed length for value.
	 */
	protected string|DateTime|NULL $max = NULL;

	/**
	 * Set the minimum value for this control. It’s a chainable method.
	 *
	 * @param string|\DateTime If string, valid format is 'Y-m'.
	 */
	public function min(string|\DateTime $minValue): self {

		$this->min = is_a($minValue, 'DateTime')
		? $minValue->format('Y-m')
		: (string)$minValue;

		return $this;

	}

	/**
	 * Set the maximum value for this control. It’s a chainable method.
	 *
	 * @param string|\DateTime If string, valid format is 'Y-m'.
	 */
	public function max(string|\DateTime $maxValue): self {

		$this->max = is_a($maxValue, 'DateTime')
		? $maxValue->format('Y-m')
		: (string)$maxValue;

		return $this;

	}

	public function render(): string {

		$ret = '<input ' . $this->nameProperty();
		$ret .= ' type="month"';

		if ($this->value) {
			$ret .= ' value="' . $this->value . '"';
		}

		if (!is_null($this->min)) {
			$ret .= ' min="' . (string)$this->min . '"';
		}

		if (!is_null($this->max)) {
			$ret .= ' max="' . (string)$this->max . '"';
		}

		$ret .= $this->processProperties() . ' />';

		return $ret;

	}

	/**
	 * Sets the value for this control. Chainable method.
	 *
	 * @param	string|int|float|\DateTime|NULL Value to set.
	 */
	public function value(string|int|float|\DateTime|NULL $value): static {

		if (is_a($value, '\DateTime')) {

			// if UTC date, set user timezone
			if (Env::get('UTC_DATE')) {
				$app = Application::getInstance();
				$value->setTimezone($app->currentUser->getDateTimeZone());
			}

			$this->value = $value->format('Y-m');

		} else {

			$this->value = (string)$value;

		}

		return $this;

	}

}