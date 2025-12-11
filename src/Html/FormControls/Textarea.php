<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Textarea extends FormControl {

	private $rows = 2;

	private $cols = 20;

	/**
	 * Sets columns for this textarea. Chainable method.
	 */
	public function cols(int $columnsNumber): self {

		$this->cols = $columnsNumber;
		return $this;

	}

	/**
	 * Renders a TextArea field tag as HTML code.
	 */
	public function render(): string {

		$ret  = '<textarea ' . $this->nameProperty();
		$ret .= ' rows="' . $this->rows . '" cols="' . $this->cols . '"';
		$ret .= $this->processProperties() . '>';
		$ret .= htmlspecialchars((string)$this->caption) . '</textarea>';

		return $ret;

	}

	/**
	 * Sets rows for this textarea. Chainable method.
	 */
	public function rows(int $rowsNumber): self {

		$this->rows = $rowsNumber;
		return $this;

	}

	/**
	 * Useful to set the caption on textarea by ActiveRecord automated methods.
	 */
	public function value(string|int|float|\DateTime|null $value): static {

		$this->caption((string)$value);
		return $this;

	}

}