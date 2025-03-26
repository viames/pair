<?php

namespace Pair\Html\FormControls;

use Pair\Helpers\Post;
use Pair\Html\FormControl;
use Pair\Helpers\Translator;

class Datetime extends FormControl {

	/**
	 * Default datetime format.
	 */
	protected string $datetimeFormat = 'Y-m-d\TH:i:s';

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

		if (Post::usingCustomDatetimepicker() and Translator::do('FORM_DATETIME_FORMAT')) {
			$this->datetimeFormat(Translator::do('FORM_DATETIME_FORMAT'));
		}

	}

	/**
	 * Set datetime format. Chainable method.
	 *
	 * @param	string	Datetime format.
	 */
	public function datetimeFormat(string $format): self {

		$this->datetimeFormat = $format;
		return $this;

	}

	/**
	 * Set the minimum value for this control. It’s a chainable method.
	 *
	 * @param string|\DateTime If string, valid format is 'Y-m-d H:i:s'.
	 */
	public function min(string|\DateTime $minValue): self {

		$this->min = is_a($minValue, 'DateTime')
		? $minValue->format('Y-m-d\TH:i:s')
		: (string)$minValue;

		return $this;

	}

	/**
	 * Set the maximum value for this control. It’s a chainable method.
	 *
	 * @param string|\DateTime If string, valid format is 'Y-m-d H:i:s'.
	 */
	public function max(string|\DateTime $maxValue): self {

		$this->max = is_a($maxValue, 'DateTime')
		? $maxValue->format('Y-m-d\TH:i:s')
		: (string)$maxValue;

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
			$ret .= ' min="' . $this->min . '"';
		}

		if (!is_null($this->max)) {
			$ret .= ' max="' . $this->max . '"';
		}

		$ret .= $this->processProperties() . ' />';

		return $ret;

	}

}