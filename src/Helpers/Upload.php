<?php

namespace Pair\Helpers;

use Pair\Core\Logger;
use Pair\Exceptions\PairException;
use Pair\Helpers\Utilities;
use Pair\Services\AmazonS3;

/**
 * This class manages http file uploads.
 */
class Upload {

	/**
	 * File name, without path, as coming by $_FILE variable.
	 */
	protected string $filename;

	/**
	 * File size (in bytes), as coming by $_FILE variable.
	 */
	protected int $filesize;

	/**
	 * Absolute path where store uploaded file, with trailing slash.
	 */
	protected string $path;

	/**
	 * Array key error as coming by $_FILE variable.
	 */
	protected string $fileError;

	/**
	 * Array key tmp_name as coming by $_FILE variable.
	 */
	protected string $fileTmpname;

	/**
	 * Array key type as coming by $_FILE variable.
	 */
	protected string $fileType;

	/**
	 * MIME data for this file.
	 */
	protected string $mime;

	/**
	 * File type (audio,document,flash,image,movie,unknown)
	 */
	protected string $type;

	/**
	 * File extension, if exists.
	 */
	protected string $ext;

	/**
	 * MD5 file hash.
	 */
	protected string $hash;

	/**
	 * The former file name.
	 */
	protected string $formerName;

	/**
	 * List of all errors tracked.
	 */
	private array $errors = [];

	/**
	 * Constructor, sets file uploaded variables as object properties.
	 *
	 * @param	string	HTTP field name.
	 * @throws	PairException
	 */
	public function __construct(string $fieldName) {

		// check on field name
		if (!isset($_FILES[$fieldName])) {
			throw new PairException('Field name “' . $fieldName . '” not found in $_FILES array');
		}

		// shortcut
		$file = $_FILES[$fieldName];

		if (!isset($file['tmp_name']) or !is_uploaded_file($file['tmp_name'])) {
			throw new PairException('File ' . $fieldName . ' is not an uploaded file');
		}

		// assign array values to the object properties
		$this->filename		= $file['name'];
		$this->formerName	= $file['name'];
		$this->filesize		= $file['size'];
		$this->fileError	= $file['error'];
		$this->fileTmpname	= $file['tmp_name'];
		$this->fileType		= $file['type'];
		$this->ext			= strtolower(substr($this->filename,strrpos($this->filename,'.')+1));

		// Sets MIME and type
		$info = $this->getMime($file['tmp_name']);
		$this->mime = $info->mime; // deprecated
		$this->type = $info->type; // deprecated

		// sets file hash
		$this->hash	= md5_file($file['tmp_name']);

		// sets the upload error as readable message
		if (UPLOAD_ERR_OK != $this->fileError) {
			throw new PairException('Upload error: ' . $this->getErrorMessage());
		}

	}

	public function __get(string $name) {

		return $this->$name;

	}

	/**
	 * Return an error message based on the PHP file upload error.
	 */
	private function getErrorMessage(): string {

		switch ($this->fileError) {

			case UPLOAD_ERR_OK:
				return 'No error';

			case UPLOAD_ERR_INI_SIZE:
				return 'Uploaded file exceeds upload_max_filesize parameter set in php.ini: (' . ini_get('upload_max_filesize') . ')';

			case UPLOAD_ERR_FORM_SIZE:
				return 'Uploaded file (' . $this->filesize  . ') exceeds MAX_FILE_SIZE attribute set in HTML form-field.';

			case UPLOAD_ERR_PARTIAL:
				return 'File was uploaded partially';

			case UPLOAD_ERR_NO_FILE:
				return 'No file set for upload';

			case UPLOAD_ERR_NO_TMP_DIR:
				return 'Temporary file directory is missing';

			case UPLOAD_ERR_CANT_WRITE:
				return 'Writing of file is failed';

			case UPLOAD_ERR_EXTENSION:
				return 'File upload failed because of unvalid file extension';

			default:
				return 'Unexpected file upload error';

		}

	}

	/**
	 * Returns text of latest error. In case of no errors, returns null.
	 */
	public function getLastError(): ?string {

		return (count($this->errors) ? end($this->errors) : null);

	}

	/**
	 * Will returns Mime and Type for the file as parameter.
	 *
	 * @param	string	Path to file.
	 */
	private function getMime(string $file): \stdClass {

		$info	= new \stdClass;

		$audio	= ['audio/basic','audio/mpeg','audio/x-aiff','audio/x-pn-realaudio','audio/wav','audio/x-wav'];
		$docs	= ['text/plain','application/pdf','application/msword','application/vnd.ms-excel','application/vnd.ms-powerpoint'];
		$flash	= ['application/x-shockwave-flash'];
		$images = ['image/gif','image/jpeg','image/png','image/svg+xml','image/tiff'];
		$movies = ['video/mpeg','video/mp4','video/quicktime','video/webm','video/x-flv','video/x-msvideo','video/x-ms-asf'];
		$zip	= ['application/zip'];

		// reads MIME with the best function
		if (function_exists('mime_content_type')) {

			$info->mime = mime_content_type($file);

		} else if (extension_loaded('fileinfo')) {

			$const = defined('FILEINFO_MIME_TYPE') ? FILEINFO_MIME_TYPE : FILEINFO_MIME;
			$finfo = finfo_open($const);
			$info->mime = finfo_file($finfo, $file);
			finfo_close($finfo);

		} else {

			$this->setError('No extensions available for this file');
			$info->mime = null;
			$info->type = null;
			return $info;

		}

		// parses variable’s MIME and set its type
		if (in_array($info->mime, $docs)) {
			$info->type = 'document';
		} else if (in_array($info->mime, $movies)) {
			$info->type = 'movie';
		} else if (in_array($info->mime, $images)) {
			$info->type = 'image';
		} else if (in_array($info->mime, $flash)) {
			$info->type = 'flash';
		} else if (in_array($info->mime, $audio)) {
			$info->type = 'audio';
		} else if (in_array($info->mime, $zip)) {
			$info->type = 'zip';
		} else {
			$info->type = 'unknown';
		}

		return $info;

	}

	/**
	 * Manages saving of an upload file with POST.
	 *
	 * @param	string	Absolute destination folder for the file to be saved, with or without trailing slash.
	 * @param	string	Optional new file name, if null will be the same as uploaded.
	 * @param	bool	Optional flag to save with random file name, default false.
	 */
	public function save(string $path, ?string $name = null, bool $random = false): bool {

		// check upload errors
		if (UPLOAD_ERR_OK != $this->fileError) {
			$this->setError($this->getErrorMessage());
			return false;
		}

		// fixes path if not containing trailing slash
		Utilities::fixTrailingSlash($path);
		$this->path = $path;

		// sanitize file-name
		$this->filename = Utilities::cleanFilename($this->filename);

		if ($random) {
			$this->filename = Utilities::randomFilename($this->filename,$this->path);
		} else if ($name) {
			$this->filename = Utilities::uniqueFilename($name,$this->path);
		} else {
			$this->filename = Utilities::uniqueFilename($this->filename,$this->path);
		}

		// checks that file doesn’t exists
		if (file_exists($this->path . $this->filename)) {
			$this->setError('A file with same name has been found at the path ' . $this->path . $this->filename);
			return false;
		}

		// checks that destination folder exists and is writable
		if (!is_dir($this->path) or !is_readable($this->path)) {

			// if not, will creates
			$old = umask(0);
			if (!mkdir($this->path, 0777, true)) {
				$this->setError('Folder ' . $this->path . ' creation doesn’t succeded');
				return false;
			}
			umask($old);

		// checks that new folder is writable
		} else if (!is_writable($this->path)) {
			$this->setError('New folder ' . $this->path . ' is not writable');
			return false;
		}

		// checks file moving
		if (move_uploaded_file($this->fileTmpname, $this->path . $this->filename)) {

			// sets file permissions
			if (!chmod($this->path . $this->filename, 0777)) {
				$this->setError('Permissions set ' . $this->path . $this->filename . ' doesn’t succeded');
			}

		} else {
			$this->setError('Error moving temporary file into the path ' . $this->path . $this->filename);
			return false;
		}

		return true;

	}

	/**
	 * Manages saving the uploaded file directly to an Amazon S3 folder with the specified file name.
	 *
	 * @param	string	Relative destination path on Amazon S3.
	 * @param	AmazonS3	Instance of AmazonS3 service.
	 * @throws	PairException	If there is an upload error.
	 */
	public function saveS3(string $filePath, AmazonS3 $amazonS3): void {

		// check on upload errors
		if (UPLOAD_ERR_OK != $this->fileError) {
			throw new PairException('Upload error: ' . $this->getErrorMessage());
		}

		// sanitize the file name
		$this->filename = Utilities::cleanFilename($this->filename);

		// upload the file to S3 through the AmazonS3 service
		$amazonS3->put($this->fileTmpname, $filePath);

	}

	/**
	 * Will sets an error on queue of main Application singleton object.
	 *
	 * @param	string	Error text.
	 */
	private function setError(string $error): void {

		$this->errors[] = $error;
		$logger = Logger::getInstance();
		$logger->error($error);

	}

}
