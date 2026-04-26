<?php

declare(strict_types=1);

namespace Pair\Http;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Helpers\Utilities;
use Pair\Services\AmazonS3;

/**
 * Represents one uploaded file and provides explicit, testable storage operations.
 */
final readonly class UploadedFile {

	/**
	 * Create an uploaded file value object.
	 */
	private function __construct(
		private ?string $fieldName,
		private string $clientFilename,
		private string $temporaryPath,
		private int $error,
		private int $size,
		private ?string $clientMediaType,
		private ?string $detectedMediaType,
		private bool $httpUpload
	) {}

	/**
	 * Build an uploaded file from the PHP $_FILES superglobal.
	 */
	public static function fromGlobals(string $fieldName): self {

		if (!array_key_exists($fieldName, $_FILES)) {
			throw new PairException('Upload field ' . $fieldName . ' was not found', ErrorCodes::INVALID_REQUEST);
		}

		if (!is_array($_FILES[$fieldName])) {
			throw new PairException('Upload field ' . $fieldName . ' is malformed', ErrorCodes::INVALID_REQUEST);
		}

		return self::fromUploadArray($_FILES[$fieldName], $fieldName, true);

	}

	/**
	 * Build an uploaded file from one normalized $_FILES entry.
	 *
	 * @param array<string, mixed> $file Normalized $_FILES entry for one field.
	 */
	public static function fromArray(array $file, ?string $fieldName = null): self {

		return self::fromUploadArray($file, $fieldName, true);

	}

	/**
	 * Build an uploaded file object around a local file for tests, CLI flows, or trusted internal imports.
	 */
	public static function fromLocalFile(string $path, ?string $clientFilename = null, ?string $clientMediaType = null, ?string $fieldName = null): self {

		if (!is_file($path) or !is_readable($path)) {
			throw new PairException('Local upload source is not readable', ErrorCodes::INVALID_REQUEST);
		}

		$size = filesize($path);

		return new self(
			$fieldName,
			$clientFilename ?? basename($path),
			$path,
			UPLOAD_ERR_OK,
			false === $size ? 0 : $size,
			$clientMediaType,
			self::detectMediaTypeFromPath($path),
			false
		);

	}

	/**
	 * Return the submitted form field name, when known.
	 */
	public function fieldName(): ?string {

		return $this->fieldName;

	}

	/**
	 * Return the original client-provided filename.
	 */
	public function clientFilename(): string {

		return $this->clientFilename;

	}

	/**
	 * Return the client filename after Pair filename normalization.
	 */
	public function safeClientFilename(): string {

		return self::sanitizeFilename($this->clientFilename);

	}

	/**
	 * Return the temporary filesystem path.
	 */
	public function temporaryPath(): string {

		return $this->temporaryPath;

	}

	/**
	 * Return the upload size in bytes reported by PHP or the trusted local source.
	 */
	public function size(): int {

		return $this->size;

	}

	/**
	 * Return the raw PHP upload error code.
	 */
	public function error(): int {

		return $this->error;

	}

	/**
	 * Return true when PHP reported a successful upload.
	 */
	public function isOk(): bool {

		return UPLOAD_ERR_OK === $this->error;

	}

	/**
	 * Return a readable upload error message, or null when the upload is successful.
	 */
	public function errorMessage(): ?string {

		if ($this->isOk()) {
			return null;
		}

		return self::messageForUploadError($this->error, $this->size);

	}

	/**
	 * Return the MIME type reported by the browser, when present.
	 */
	public function clientMediaType(): ?string {

		return $this->clientMediaType;

	}

	/**
	 * Detect the MIME type from file contents.
	 */
	public function detectedMediaType(): ?string {

		return $this->detectedMediaType;

	}

	/**
	 * Return the best available MIME type, preferring server-side detection.
	 */
	public function mediaType(): ?string {

		return $this->detectedMediaType() ?? $this->clientMediaType;

	}

	/**
	 * Return the Pair media category for the best available MIME type.
	 */
	public function mediaCategory(): ?string {

		$mediaType = $this->mediaType();

		return is_string($mediaType) ? FileMediaType::category($mediaType) : null;

	}

	/**
	 * Return the normalized file extension without the dot.
	 */
	public function extension(): ?string {

		$extension = pathinfo($this->safeClientFilename(), PATHINFO_EXTENSION);

		if (!is_string($extension) or '' === $extension) {
			return null;
		}

		return $extension;

	}

	/**
	 * Return a checksum for the temporary file.
	 */
	public function checksum(string $algorithm = 'md5'): string {

		if (!in_array($algorithm, hash_algos(), true)) {
			throw new PairException('Hash algorithm ' . $algorithm . ' is not available', ErrorCodes::INVALID_REQUEST);
		}

		$this->assertReadableTemporaryFile();

		$hash = hash_file($algorithm, $this->temporaryPath);

		if (!is_string($hash)) {
			throw new PairException('Unable to hash uploaded file', ErrorCodes::UNEXPECTED);
		}

		return $hash;

	}

	/**
	 * Move the uploaded file to a local directory and return the final absolute path.
	 */
	public function moveTo(string $directory, ?string $filename = null, bool $overwrite = false): string {

		$this->assertMovable();

		$directory = self::ensureDirectory($directory);
		$filename = self::sanitizeFilename($filename ?? $this->clientFilename);
		$destination = self::resolveDestination($directory, $filename, $overwrite);

		$moved = $this->httpUpload
			? move_uploaded_file($this->temporaryPath, $destination)
			: $this->moveLocalFile($destination);

		if (!$moved) {
			throw new PairException('Unable to move uploaded file to destination', ErrorCodes::UNEXPECTED);
		}

		if (!chmod($destination, 0664)) {
			throw new PairException('Unable to set permissions on uploaded file', ErrorCodes::PERMISSION_DENIED);
		}

		return $destination;

	}

	/**
	 * Upload the temporary file directly to Amazon S3.
	 */
	public function putToS3(string $objectKey, AmazonS3 $amazonS3): void {

		$this->assertMovable();

		$objectKey = trim($objectKey);

		if ('' === $objectKey) {
			throw new PairException('S3 upload requires a non-empty object key', ErrorCodes::INVALID_REQUEST);
		}

		$amazonS3->put($this->temporaryPath, $objectKey);

	}

	/**
	 * Normalize a client-provided filename for safe filesystem storage.
	 */
	public static function sanitizeFilename(string $filename): string {

		$filename = str_replace(["\\", "\0"], ['/', ''], trim($filename));
		$filename = basename($filename);
		$parts = pathinfo($filename);
		$name = trim((string)($parts['filename'] ?? ''));
		$extension = strtolower((string)($parts['extension'] ?? ''));

		$name = Utilities::cleanUp($name);
		$extension = preg_replace('/[^a-z0-9]+/i', '', $extension);

		if ('' === $name) {
			$name = 'file';
		}

		if (!is_string($extension) or '' === $extension) {
			return $name;
		}

		return $name . '.' . $extension;

	}

	/**
	 * Return a readable message for a PHP upload error code.
	 */
	public static function messageForUploadError(int $error, ?int $size = null): string {

		return match ($error) {
			UPLOAD_ERR_OK => 'No upload error',
			UPLOAD_ERR_INI_SIZE => 'Uploaded file exceeds upload_max_filesize parameter set in php.ini: ' . ini_get('upload_max_filesize'),
			UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds MAX_FILE_SIZE attribute set in the HTML form' . (is_null($size) ? '' : ': ' . $size . ' bytes'),
			UPLOAD_ERR_PARTIAL => 'Uploaded file was received only partially',
			UPLOAD_ERR_NO_FILE => 'No file was submitted for upload',
			UPLOAD_ERR_NO_TMP_DIR => 'Temporary upload directory is missing',
			UPLOAD_ERR_CANT_WRITE => 'Unable to write uploaded file to disk',
			UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
			default => 'Unexpected file upload error',
		};

	}

	/**
	 * Build an uploaded file from a normalized file array.
	 *
	 * @param array<string, mixed> $file Normalized $_FILES entry for one field.
	 */
	private static function fromUploadArray(array $file, ?string $fieldName, bool $requireHttpUpload): self {

		foreach (['name', 'tmp_name', 'error', 'size'] as $key) {
			if (!array_key_exists($key, $file)) {
				throw new PairException('Upload entry is missing ' . $key, ErrorCodes::INVALID_REQUEST);
			}

			if (is_array($file[$key])) {
				throw new PairException('Nested upload arrays must be normalized before creating UploadedFile objects', ErrorCodes::INVALID_REQUEST);
			}
		}

		$error = (int)$file['error'];
		$temporaryPath = (string)$file['tmp_name'];

		if (UPLOAD_ERR_OK === $error) {
			if ('' === $temporaryPath) {
				throw new PairException('Successful upload is missing a temporary file path', ErrorCodes::INVALID_REQUEST);
			}

			if ($requireHttpUpload and !is_uploaded_file($temporaryPath)) {
				throw new PairException('Temporary file was not created by HTTP upload handling', ErrorCodes::INVALID_REQUEST);
			}
		}

		$clientMediaType = null;

		if (isset($file['type']) and !is_array($file['type'])) {
			$clientMediaType = (string)$file['type'];
		}

		$detectedMediaType = UPLOAD_ERR_OK === $error ? self::detectMediaTypeFromPath($temporaryPath) : null;

		return new self(
			$fieldName,
			(string)$file['name'],
			$temporaryPath,
			$error,
			(int)$file['size'],
			$clientMediaType,
			$detectedMediaType,
			$requireHttpUpload
		);

	}

	/**
	 * Ensure the upload completed successfully and still points to a readable file.
	 */
	private function assertMovable(): void {

		if (!$this->isOk()) {
			throw new PairException('Upload error: ' . $this->errorMessage(), ErrorCodes::INVALID_REQUEST);
		}

		$this->assertReadableTemporaryFile();

		if ($this->httpUpload and !is_uploaded_file($this->temporaryPath)) {
			throw new PairException('Temporary file is no longer a valid HTTP upload', ErrorCodes::INVALID_REQUEST);
		}

	}

	/**
	 * Ensure the temporary file path exists and can be read.
	 */
	private function assertReadableTemporaryFile(): void {

		if (!$this->hasReadableTemporaryFile()) {
			throw new PairException('Temporary upload file is not readable', ErrorCodes::INVALID_REQUEST);
		}

	}

	/**
	 * Return true when the temporary path still references a readable file.
	 */
	private function hasReadableTemporaryFile(): bool {

		return ('' !== $this->temporaryPath and is_file($this->temporaryPath) and is_readable($this->temporaryPath));

	}

	/**
	 * Detect a MIME type from a readable local path.
	 */
	private static function detectMediaTypeFromPath(string $path): ?string {

		if ('' === $path or !is_file($path) or !is_readable($path)) {
			return null;
		}

		if (extension_loaded('fileinfo')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);

			if (is_resource($finfo)) {
				$mediaType = finfo_file($finfo, $path);
				finfo_close($finfo);

				if (is_string($mediaType) and '' !== trim($mediaType)) {
					return $mediaType;
				}
			}
		}

		if (function_exists('mime_content_type')) {
			$mediaType = @mime_content_type($path);

			if (is_string($mediaType) and '' !== trim($mediaType)) {
				return $mediaType;
			}
		}

		return null;

	}

	/**
	 * Create a destination directory when needed and verify it can receive uploads.
	 */
	private static function ensureDirectory(string $directory): string {

		$directory = rtrim(trim($directory), DIRECTORY_SEPARATOR);

		if ('' === $directory) {
			throw new PairException('Upload destination directory is empty', ErrorCodes::INVALID_REQUEST);
		}

		if (file_exists($directory) and !is_dir($directory)) {
			throw new PairException('Upload destination is not a directory', ErrorCodes::INVALID_REQUEST);
		}

		if (!is_dir($directory)) {
			$old = umask(0002);

			try {
				$created = mkdir($directory, 0775, true);
			} finally {
				umask($old);
			}

			if (!$created and !is_dir($directory)) {
				throw new PairException('Upload destination directory could not be created', ErrorCodes::UNEXPECTED);
			}

			chmod($directory, 0775);
		}

		if (!is_writable($directory)) {
			throw new PairException('Upload destination directory is not writable', ErrorCodes::PERMISSION_DENIED);
		}

		return $directory;

	}

	/**
	 * Resolve the final destination path, adding a numeric suffix unless overwrite is allowed.
	 */
	private static function resolveDestination(string $directory, string $filename, bool $overwrite): string {

		$destination = $directory . DIRECTORY_SEPARATOR . $filename;

		if ($overwrite or !file_exists($destination)) {
			return $destination;
		}

		$parts = pathinfo($filename);
		$name = (string)($parts['filename'] ?? 'file');
		$extension = isset($parts['extension']) ? '.' . $parts['extension'] : '';

		for ($counter = 1; ; $counter++) {
			$destination = $directory . DIRECTORY_SEPARATOR . $name . '-' . $counter . $extension;

			if (!file_exists($destination)) {
				return $destination;
			}
		}

	}

	/**
	 * Move a trusted local file and fall back to copy/unlink when rename crosses filesystem boundaries.
	 */
	private function moveLocalFile(string $destination): bool {

		if (@rename($this->temporaryPath, $destination)) {
			return true;
		}

		if (@copy($this->temporaryPath, $destination)) {
			@unlink($this->temporaryPath);
			return true;
		}

		return false;

	}

}
