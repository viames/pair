<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;
use Pair\Support\Post;
use Pair\Support\Logger;

class Input extends FormControl {

	/**
	 * Can be text, tel, url, password, number, image.
	 * @var string
	 */
	protected $type;

	/**
	 * Step value for number input controls.
	 * @var string
	 */
	protected $step;

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

		$this->type('text');

	}

	/**
	 * Sets type for a Input. Chainable method.
	 *
	 * @param	string	Input type (text, password, number, bool, tel, email, url, color, date, datetime, file, image, address,
	 * hidden)
	 * @deprecated		Deprecated in favor of specific FormControl classes.
	 */
	public function type(string $type): self {

		$this->type = $type;
		return $this;

	}

	/**
	 * Set step value for input field of number type. Chainable method.
	 *
	 * @param	mixed	Integer or decimal value for this control.
	 */
	public function setStep($value): self {

		$this->step = (string)$value;
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

		switch ($this->type) {

			default:
			case 'text':
			case 'tel':
			case 'url':
			case 'password':
				$ret .= ' type="' . htmlspecialchars((string)$this->type) . '" value="' . htmlspecialchars((string)$this->value) . '"';
				break;

			case 'number':
				$curr = setlocale(LC_NUMERIC, 0);
				setlocale(LC_NUMERIC, 'en_US');
				$ret .= ' type="number" value="' . htmlspecialchars((string)$this->value) . '"';
				setlocale(LC_NUMERIC, $curr);
				break;

			case 'image':
				$ret .= ' type="image"';
				break;

		}

		// set min and max value attribute for date and number only
		if (in_array($this->type, ['number','date'])) {

			if (!is_null($this->min)) {
				$ret .= ' min="' . htmlspecialchars((string)$this->min) . '"';
			}

			if (!is_null($this->max)) {
				$ret .= ' max="' . htmlspecialchars((string)$this->max) . '"';
			}

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
			$ret .= ' step="' . htmlspecialchars((string)$this->step) . '"';
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

		if ($this->required) {

			switch ($this->type) {

				default:
				case 'text':
				case 'password':
				case 'date':
				case 'datetime':
				case 'image':
				case 'tel':
				case 'address':
				case 'color':
					if (''==$value) {
						Logger::event('Control validation on field “' . $this->name . '” has failed (required)');
						$valid = FALSE;
					}
					break;

				case 'email':
					if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
						Logger::event('Control validation on field “' . $this->name . '” has failed (email required)');
						$valid = FALSE;
					}
					break;

				case 'url':
					if (!filter_var($value, FILTER_VALIDATE_URL)) {
						Logger::event('Control validation on field “' . $this->name . '” has failed (url required)');
						$valid = FALSE;
					}
					break;

				case 'number':
					if (!is_numeric($value)) {
						Logger::event('Control validation on field “' . $this->name . '” has failed (number required)');
						$valid = FALSE;
					}
					break;

			}

		}

		// set min and max value attribute for date and number only
		if (in_array($this->type, ['number'])) {

			if ($this->min and $value < $this->min) {
				Logger::event('Control validation on field “' . $this->name . '” has failed (min=' . $this->min . ')');
				$valid = FALSE;
			}

			if ($this->max and $value > $this->max) {
				Logger::event('Control validation on field “' . $this->name . '” has failed (max=' . $this->max . ')');
				$valid = FALSE;
			}

		}

		// check validity of minlength attribute
		if ($this->minLength and ''!=$value and strlen($value) < $this->minLength) {
			Logger::event('Control validation on field “' . $this->name . '” has failed (minLength=' . $this->minLength . ')');
			$valid = FALSE;
		}

		// check validity of minlength attribute
		if ($this->maxLength and strlen($value) > $this->maxLength) {
			Logger::event('Control validation on field “' . $this->name . '” has failed (maxLength=' . $this->maxLength . ')');
			$valid = FALSE;
		}

		return $valid;

	}

}