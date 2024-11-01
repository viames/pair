<?php

namespace Pair\Html\FormControls;

use Pair\Core\Config;
use Pair\Html\FormControl;
use Pair\Helpers\Post;

class Date extends FormControl {

	/**
	 * Default date format.
	 */
	protected string $dateFormat = 'Y-m-d';

	/**
	 * Minimum allowed length for value.
	 */
	protected string|DateTime|NULL $min = NULL;

	/**
	 * Maximum allowed length for value.
	 */
	protected string|DateTime|NULL $max = NULL;

	/**
	 * Extends parent constructor in order to sets default type to text.
	 *
	 * @param	string	Control name.
	 * @param	array	Additional attributes (tag=>value).
	 */
	public function __construct(string $name, array $attributes = []) {

		parent::__construct($name, $attributes);

		if (Post::usingCustomDatepicker() and Config::get('PAIR_FORM_DATE_FORMAT')) {
			$this->dateFormat(Config::get('PAIR_FORM_DATE_FORMAT'));
		}

	}

	/**
	 * Set date format. Chainable method.
	 *
	 * @param	string	Date format.
	 */
	public function dateFormat(string $format): self {

		$this->dateFormat = $format;
		return $this;

	}

	/**
	 * Set the minimum value for this control. It’s a chainable method.
	 *
	 * @param string|\DateTime If string, valid format is 'Y-m-d'.
	 */
	public function min(string|\DateTime $minValue): self {

		$this->min = is_a($minValue, 'DateTime')
		? $minValue->format('Y-m-d')
		: (string)$minValue;

		return $this;

	}

	/**
	 * Set the maximum value for this control. It’s a chainable method.
	 *
	 * @param string|\DateTime If string, valid format is 'Y-m-d'.
	 */
	public function max(string|\DateTime $maxValue): self {

		$this->max = is_a($maxValue, 'DateTime')
		? $maxValue->format('Y-m-d')
		: (string)$maxValue;

		return $this;

	}

	/**
	 * Renders and returns an HTML input form control.
	 */
	public function render(): string {

		$ret = '<input ' . $this->nameProperty();

		$value = is_a($this->value, 'DateTime') ? $this->value->format($this->dateFormat) : (string)$this->value;
		$ret .= ' type="date" value="' . htmlspecialchars($value) . '"';

		if (!is_null($this->min)) {
			$ret .= ' min="' . $this->min . '"';
		}

		if (!is_null($this->max)) {
			$ret .= ' max="' . $this->max . '"';
		}

		$ret .= $this->processProperties() . ' />';

		return $ret;

	}

}