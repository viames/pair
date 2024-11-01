<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class File extends FormControl {

	const MIME_TYPES = [
		'audio' => [
			'.aac',
			'.flac',
			'.mp3',
			'.m4a',
			'.oga',
			'.ogg',
			'.wav',
			'audio/*',
			'audio/aac',
			'audio/flac',
			'audio/mpeg',
			'audio/mpeg3',
			'audio/mp3',
			'audio/m4a',
			'audio/ogg',
			'audio/wav'
		],
		'binary' => [
			'application/octet-stream'
		],
		'csv' => [
			'.csv',
			'application/csv',
			'text/comma-separated-values',
			'text/csv',
			'text/plain'
		],
		'document' => [
			'.doc',
			'.docx',
			'.docm',
			'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/vnd.ms-word.document.macroEnabled.12'
		],
		'images' => [
			'.bmp',
			'.gif',
			'.jpeg',
			'.jpg',
			'.png',
			'.svg',
			'.tiff',
			'.webp',
			'image/*',
			'image/apng',
			'image/avif',
			'image/bmp',
			'image/gif',
			'image/jpeg',
			'image/pjpeg',
			'image/png',
			'image/svg',
			'image/tiff',
			'image/x-windows-bmp'
		],
		'pdf' => [
			'.pdf',
			'application/pdf',
			'application/x-pdf',
			'application/acrobat',
			'applications/vnd.pdf',
			'text/pdf',
			'text/x-pdf'
		],
		'presentation' => [
			'.ppt',
			'.pptx',
			'.pptm',
			'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'application/vnd.ms-powerpoint',
			'vnd.openxmlformats-officedocument.presentationml.presentation',
			'application/vnd.ms-powerpoint.presentation.macroEnabled.12'
		],
		'spreadsheet' => [
			'.xls',
			'.xlsx',
			'.xlsm',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'application/vnd.ms-excel',
			'vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'application/vnd.ms-excel.sheet.macroEnabled.12'
		],
		'video' => [
			'.avi',
			'.mp4',
			'.mpeg',
			'.mpg',
			'.qt',
			'video/*',
			'video/mpeg',
			'video/mp4',
			'video/x-mpeg',
			'video/quicktime',
			'video/x-msvideo'
		],
		'zip' => [
			'.bz2',
			'.gz',
			'.zip',
			'application/octet-stream',
			'application/x-bzip2',
			'application/x-zip-compressed',
			'application/zip'
		]
	];

	/**
	 * Accepted file type file_extension, audio/*, video/*, image/* or media_type.
	 */
	protected ?string $accept = NULL;

	/**
	 * Set accepted file type by input field. Chainable method.
	 *
	 * @param	string	MIME type or file extension.
	 */
	public function accept(string $mimeType): self {

		if (!is_null($this->accept)) {
			$this->accept .= ',';
		}

		$this->accept .= $mimeType;
		return $this;

	}

	/**
	 * Add a whole group of MIME types to the accepted list.
	 */
	public function acceptCategory(string $mimeCategory): self {

		if (array_key_exists($mimeCategory, self::MIME_TYPES)) {

			if (!is_null($this->accept)) {
				$this->accept .= ',';
			}

			$this->accept .= implode(',', self::MIME_TYPES[$mimeCategory]);

		}

		return $this;

	}

	/**
	 * Returns the non-standard MIME category of the specified MIME type string. NULL if not found.
	 */
	public static function mimeCategory(string $mimeType): ?string {

		foreach (self::MIME_TYPES as $category => $types) {
			if (in_array($mimeType, $types)) {
				return $category;
			}
		}

		return NULL;

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