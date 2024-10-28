<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;
use Pair\Support\Post;

class Date extends FormControl {

	/**
	 * Default date format.
	 * @var string
	 */
	protected $dateFormat = 'Y-m-d';

	/**
	 * Minimum allowed length for value.
	 * @var string
	 */
	protected $min;

	/**
	 * Maximum allowed length for value.
	 * @var string
	 */
	protected $max;

	/**
	 * Extends parent constructor in order to sets default type to text.
	 *
	 * @param	string	Control name.
	 * @param	array	Additional attributes (tag=>value).
	 */
	public function __construct(string $name, array $attributes = []) {

		parent::__construct($name, $attributes);

		if (Post::usingCustomDatepicker() and defined('PAIR_FORM_DATE_FORMAT')) {
			$this->setDateFormat(PAIR_FORM_DATE_FORMAT);
		}

	}

	/**
	 * Set date format. Chainable method.
	 *
	 * @param	string	Date format.
	 */
	public function setDateFormat(string $format): self {

		$this->dateFormat = $format;
		return $this;

	}

	/**
	 * Set the minimum value for this control. It’s a chainable method.
	 *
	 * @param	mixed	Minimum value.
	 */
	public function setMin($minValue): self {

		$this->min = (int)$minValue;
		return $this;

	}

	/**
	 * Set the maximum value for this control. It’s a chainable method.
	 *
	 * @param	mixed		Maximum value.
	 */
	public function setMax($maxValue): self {

		$this->max = (int)$maxValue;
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
			$ret .= ' min="' . htmlspecialchars((string)$this->min) . '"';
		}

		if (!is_null($this->max)) {
			$ret .= ' max="' . htmlspecialchars((string)$this->max) . '"';
		}

		$ret .= $this->processProperties() . ' />';

		return $ret;

	}

}