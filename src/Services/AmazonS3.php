<?php

namespace Pair\Services;

use \Aws\S3\S3Client;

use \Etime\Flysystem\Plugin\AWS_S3\PresignedUrl;

use \League\Flysystem\AwsS3v3\AwsS3Adapter;
use \League\Flysystem\Filesystem;

class AmazonS3 {
	/**
	 * FileSystem Variable (for internal use)
	 *
	 * @var League\FlySystem\Filesystem
	 */
	protected $filesystem;

	/**
	 * List of all errors tracked.
	 * @var array
	 */
	private $errors = [];

	/**
	 * Starts S3 Driver
	 */
	public function __construct() {

		// Creates the S3 Client
		$client = new S3Client([
			'credentials' => [
				'key'    => S3_ACCESS_KEY_ID,
				'secret' => S3_SECRET_ACCESS_KEY,
			],
			'region' => S3_BUCKET_REGION,
			'version' => 'latest',
		]);

		// Creates the S3 adapter to cast to the filesystem object
		$adapter = new AwsS3Adapter($client, S3_BUCKET_NAME);

		// Creates the filesystem object
		$this->filesystem = new Filesystem($adapter);

		// Adds the plugin to create file URLs
		$this->filesystem->addPlugin(new PresignedUrl());
	}


	/**
	 * Loads local file on S3 bucket.
	 * $filePath is the local file path
	 * $destination is the remote file path (including file name)
	 *
	 * @param string $filePath
	 * @param string $destination
	 * @return bool
	 */
	public function put(string $filePath, string $destination): bool {

		if (!file_exists($filePath)) return FALSE;

		$fileContents = file_get_contents($filePath);

		try {
			$result = $this->filesystem->put($destination, $fileContents);
		} catch(\Exception $e) {
			$this->addError($e->getMessage());
			return FALSE;
		}

		return $result;

	}

	/**
	 * Downloads the remote file on local filesystem
	 * $remoteFile is the path of the remote file
	 * $localFilePath is the path of the local file (including file name)
	 *
	 * @param string $remoteFile
	 * @param string $localFilePath
	 * @return boolean
	 */
	public function get(string $remoteFile, string $localFilePath): bool {

		$contents = $this->read($remoteFile);
		if (!$contents) return FALSE;

		try {
			file_put_contents($localFilePath, $contents);
		} catch(\Exception $e) {
			$this->addError($e->getMessage());
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Reads the remote file and returns the content
	 */
	public function read(string $remoteFile) {

		if (!$this->exists($remoteFile)) return null;

		$contents = $this->filesystem->read($remoteFile);
		return $contents;

	}

	/**
	 * Returns the file URL if file exists, null otherwise
	 */
	public function getLink(string $remoteFile): ?string {

		if (!$this->exists($remoteFile)) {
			return NULL;
		}

		return $this->filesystem->getPresignedUrl($remoteFile);

	}

	/**
	 * Controls if the remote file (with its path) exists
	 *
	 * @param string $remoteFile
	 * @return boolean
	 */
	public function exists(string $remoteFile): bool {

		$res = FALSE;

		try {
			if ($this->filesystem->has($remoteFile)) $res = TRUE;
		} catch(\Exception $e) {
			$this->addError($e->getMessage());
		}

		return $res;

	}

	/**
	 * Deletes the remote file at the specified path
	 *
	 * @param string $remoteFile
	 * @return boolean
	 */
	public function delete(string $remoteFile): bool {

		try {
			if ($this->exists($remoteFile)) {
				$this->filesystem->delete($remoteFile);
			}
		} catch(\Exception $e) {
			$this->addError($e->getMessage());
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Deletes the remote directory at the specified path.
	 */
	public function deleteDir(string $remoteDir): bool {

		if ($this->exists($remoteDir)) {
			$this->filesystem->deleteDir($remoteDir);
		}

		return TRUE;
	}

	/**
	 * Add an error to object’s error list.
	 *
	 * @param	string	Error message’s text.
	 */
	public function addError(string $message) {

		$this->errors[] = $message;

	}

	/**
	 * Return text of latest error. In case of no errors, return FALSE.
	 *
	 * @return FALSE|string
	 */
	final public function getLastError() {

		return end($this->errors);

	}

	/**
	 * Return an array with text of all errors.
	 *
	 * @return array
	 */
	final public function getErrors() {

		return $this->errors;

	}

}
