<?php

namespace Pair\Html\FormControls;

use DateTime;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Html\FormControl;

class Time extends FormControl {

	/**
	 * Minimum allowed length for value.
	 */
	protected string|DateTime|null $min = null;

	/**
	 * Maximum allowed length for value.
	 */
	protected string|DateTime|null $max = null;

	/**
	 * Set the minimum value for this control. It’s a chainable method.
	 *
	 * @param string|DateTime If string, valid format is 'H:i'.
	 */
	public function min(string|DateTime $minValue): self {

		$this->min = is_a($minValue, 'DateTime')
		? $minValue->format('Y-m-d')
		: (string)$minValue;

		return $this;

	}

	/**
	 * Set the maximum value for this control. It’s a chainable method.
	 *
	 * @param string|DateTime If string, valid format is 'H:i'.
	 */
	public function max(string|DateTime $maxValue): self {

		$this->max = is_a($maxValue, 'DateTime')
		? $maxValue->format('Y-m-d')
		: (string)$maxValue;

		return $this;

	}

	public function render(): string {

		$ret = '<input ' . $this->nameProperty();
		$ret .= ' type="time"';

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
	 * @param	string|int|float|DateTime|null Value to set.
	 */
	public function value(string|int|float|DateTime|null $value): static {

		if (is_a($value, 'DateTime')) {

			// if UTC date, set user timezone
			if (Env::get('UTC_DATE')) {
				$app = Application::getInstance();
				$value->setTimezone($app->currentUser->getDateTimeZone());
			}

			$this->value = $value->format('H:i');

		} else {

			$this->value = (string)$value;

		}

		return $this;

	}

}