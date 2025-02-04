<?php

namespace Pair\Services;

use Aws\S3\S3Client;

use Etime\Flysystem\Plugin\AWS_S3\PresignedUrl;

use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;

use Pair\Core\Config;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

class AmazonS3 {
	/**
	 * FileSystem Variable (for internal use)
	 */
	protected Filesystem $filesystem;

	/**
	 * List of all errors tracked.
	 */
	private array $errors = [];

	/**
	 * Starts S3 Driver
	 */
	public function __construct() {

		// Creates the S3 Client
		$client = new S3Client([
			'credentials' => [
				'key'    => Config::get('S3_ACCESS_KEY_ID'),
				'secret' => Config::get('S3_SECRET_ACCESS_KEY'),
			],
			'region' => Config::get('S3_BUCKET_REGION'),
			'version' => 'latest',
		]);

		// Creates the S3 adapter to cast to the filesystem object
		$adapter = new AwsS3Adapter($client, Config::get('S3_BUCKET_NAME'));

		// Creates the filesystem object
		$this->filesystem = new Filesystem($adapter);

		// Adds the plugin to create file URLs
		$this->filesystem->addPlugin(new PresignedUrl());
	}


	/**
	 * Loads local file on S3 bucket.
	 *
	 * @param string Local file path.
	 * @param string Remote file path (including file name).
	 */
	public function put(string $filePath, string $destination): bool {

		if (!file_exists($filePath)) return FALSE;

		$fileContents = file_get_contents($filePath);

		try {
			$result = $this->filesystem->put($destination, $fileContents);
		} catch (\Exception $e) {
			throw new PairException($e->getMessage(), ErrorCodes::AMAZON_S3_ERROR, $e);
		}

		return $result;

	}

	/**
	 * Downloads the remote file on local filesystem.
	 *
	 * @param string Path of the remote file.
	 * @param string Path of the local file (including file name).
	 */
	public function get(string $remoteFile, string $localFilePath): bool {

		$contents = $this->read($remoteFile);
		if (!$contents) return FALSE;

		try {
			file_put_contents($localFilePath, $contents);
		} catch (\Exception $e) {
			throw new PairException($e->getMessage(), ErrorCodes::AMAZON_S3_ERROR, $e);
		}

		return TRUE;
	}

	/**
	 * Reads the remote file and returns the content.
	 * 
	 * @param string Path of the remote file.
	 */
	public function read(string $remoteFile): ?string {

		if (!$this->exists($remoteFile)) return null;

		return $this->filesystem->read($remoteFile);

	}

	/**
	 * Returns the file URL if file exists, null otherwise.
	 * 
	 * @param string Path of the remote file.
	 */
	public function getLink(string $remoteFile): ?string {

		if (!$this->exists($remoteFile)) {
			return NULL;
		}

		return $this->filesystem->getPresignedUrl($remoteFile);

	}

	/**
	 * Controls if the remote file (with its path) exists.
	 *
	 * @param string Path of the remote file.
	 */
	public function exists(string $remoteFile): bool {

		$res = FALSE;

		try {
			if ($this->filesystem->has($remoteFile)) $res = TRUE;
		} catch (\Exception $e) {
			throw new PairException($e->getMessage(), ErrorCodes::AMAZON_S3_ERROR, $e);
		}

		return $res;

	}

	/**
	 * Deletes the remote file at the specified path.
	 *
	 * @param string Path of the remote file.
	 */
	public function delete(string $remoteFile): bool {

		try {
			if ($this->exists($remoteFile)) {
				$this->filesystem->delete($remoteFile);
			}
		} catch (\Exception $e) {
			throw new PairException($e->getMessage(), ErrorCodes::AMAZON_S3_ERROR, $e);
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
	 */
	final public function getLastError(): FALSE|string {

		return end($this->errors);

	}

	/**
	 * Return an array with text of all errors.
	 */
	final public function getErrors(): array {

		return $this->errors;

	}

}