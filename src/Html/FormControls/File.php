<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class File extends FormControl {

	/**
	 * Accepted file type file_extension, audio/*, video/*, image/* or media_type.
	 */
	protected $accept;

	/**
	 * Set accepted file type by input field. Chainable method.
	 *
	 * @param	string	File type: file_extension, audio/*, video/*, image/*, media_type.
	 */
	public function setAccept(string $fileType): self {

		$this->accept = $fileType;
		return $this;

	}

	/**
	 * Returns the HTML code of the file control adding the accept attribute which limits
	 * the type of files that can be uploaded through the form.
	 */
	public function render(): string {

		$ret = '<input ' . $this->nameProperty();

		$ret .= ' type="file" value="'. htmlspecialchars((string)$this->value) .'"';

		if ($this->accept) {
			$ret .= ' accept="' . $this->accept . '"';
		}

		if ($this->minLength) {
			$ret .= ' minlength="' . (int)$this->minLength . '"';
		}

		if ($this->maxLength) {
			$ret .= ' maxlength="' . (int)$this->maxLength . '"';
		}

		$ret .= $this->processProperties() . ' />';

		return $ret;

	}

}