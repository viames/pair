<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Textarea extends FormControl {

	private $rows = 2;

	private $cols = 20;

	/**
	 * Sets rows for this textarea. Chainable method.
	 *
	 * @param	int		Rows number.
	 */
	public function rows(int $num): self {

		$this->rows = $num;
		return $this;

	}

	/**
	 * Sets columns for this textarea. Chainable method.
	 *
	 * @param	int		Columns number.
	 */
	public function cols(int $num): self {

		$this->cols = $num;
		return $this;

	}

	/**
	 * Renders a TextArea field tag as HTML code.
	 */
	public function render(): string {

		$ret  = '<textarea ' . $this->nameProperty();
		$ret .= ' rows="' . $this->rows . '" cols="' . $this->cols . '"';
		$ret .= $this->processProperties() . '>';
		$ret .= htmlspecialchars((string)$this->value) . '</textarea>';

		return $ret;

	}

}