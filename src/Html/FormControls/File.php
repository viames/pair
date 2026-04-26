<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;
use Pair\Http\FileMediaType;

/**
 * Renders file upload inputs and exposes shared MIME category helpers.
 */
class File extends FormControl {

	public const MIME_TYPES = FileMediaType::CATEGORIES;

	/**
	 * Accepted file type file_extension, audio/*, video/*, image/* or media_type.
	 */
	protected ?string $accept = null;

	/**
	 * Sets accepted file type by input field. Chainable method.
	 *
	 * @param	string|array	Single MIME type or file extension, or an array of them.
	 */
	public function accept(string|array $mimeType): self {

		if (!is_null($this->accept)) {
			$this->accept .= ',';
		}

		if (is_array($mimeType) and count($mimeType)) {
			$this->accept .= implode(',', $mimeType);
		} else if (is_string($mimeType)) {
			$this->accept .= $mimeType;
		}

		return $this;

	}

	/**
	 * Adds a whole group of MIME types to the accepted list by category (audio|binary|csv|document|image|pdf|presentation|spreadsheet|video|zip).
	 * Chainable method.
	 */
	public function acceptCategory(string $mimeCategory): self {

		$types = FileMediaType::categoryTypes($mimeCategory);

		if ($types) {

			if (!is_null($this->accept)) {
				$this->accept .= ',';
			}

			$this->accept .= implode(',', $types);

		}

		return $this;

	}

	/**
	 * Adds groups of MIME types to the accepted list by category (audio|binary|csv|document|image|pdf|presentation|spreadsheet|video|zip).
	 * Chainable method.
	 */
	public function acceptCategories(array $mimeCategories): self {

		foreach ($mimeCategories as $mimeCategory) {
			$this->acceptCategory($mimeCategory);
		}

		return $this;

	}

	/**
	 * Sets the camera facing mode for the file input.
	 */
	public function capture(?string $cameraFacingMode = null): self {

		$this->attributes['capture'] = $cameraFacingMode;

		return $this;

	}

	/**
	 * Returns the non-standard MIME category of the specified MIME type string. null if not found.
	 */
	public static function mimeCategory(string $mimeType): ?string {

		return FileMediaType::category($mimeType);

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
