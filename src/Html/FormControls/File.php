<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class File extends FormControl {

	/**
	 * Accepted file type file_extension, audio/*, video/*, image/* or media_type.
	 */
	protected $accept;

	/**
	 * Extends parent constructor in order to sets default type to text.
	 *
	 * @param	string	Control name.
	 * @param	array	Additional attributes (tag=>value).
	 */
	public function __construct(string $name, array $attributes = []) {

		parent::__construct($name, $attributes);

	}

	/**
	 * Set accepted file type by input field (only affects the “file” input). Chainable method.
	 *
	 * @param	string	File type: file_extension, audio/*, video/*, image/*, media_type.
	 */
	public function setAccept(string $fileType): self {

		$this->accept = $fileType;
		return $this;

	}

	/**
	 * Renders and returns an HTML input form control.
	 */
	public function render(): string {

		return '<input ' . $this->nameProperty() . ' type="file"' . $this->processProperties() . ' />';

	}

}