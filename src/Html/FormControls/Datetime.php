<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;
use Pair\Support\Post;

class Datetime extends FormControl {

	/**
	 * Default datetime format
	 * @var string
	 */
	protected $datetimeFormat = 'Y-m-d\TH:i:s';

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

		if (Post::usingCustomDatetimepicker() and defined('PAIR_FORM_DATETIME_FORMAT')) {
			$this->setDatetimeFormat(PAIR_FORM_DATETIME_FORMAT);
		}

	}

	/**
	 * Set datetime format. Chainable method.
	 *
	 * @param	string	Datetime format.
	 */
	public function setDatetimeFormat(string $format): self {

		$this->datetimeFormat = $format;
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

		$type = Post::usingCustomDatetimepicker() ? 'datetime' : 'datetime-local';
		$value = is_a($this->value, 'DateTime') ? $this->value->format($this->datetimeFormat) : (string)$this->value;
		$ret .= ' type="' . $type . '" value="' . htmlspecialchars($value) . '"';

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