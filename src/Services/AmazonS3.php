<?php

namespace Pair\Services;

use Aws\S3\S3Client;

use Etime\Flysystem\Plugin\AWS_S3\PresignedUrl;

use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

class AmazonS3 {
	/**
	 * FileSystem Variable (for internal use).
	 */
	protected Filesystem $filesystem;

	/**
	 * Starts Amazon S3 Driver.
	 * 
	 * @param	string	Access Key ID.
	 * @param	string	Secret Access Key.
	 * @param	string	Bucket Region.
	 * @param	string	Bucket Name.
	 */
	public function __construct(string $key, string $secret, string $region, string $bucket) {

		// Creates the S3 Client
		$client = new S3Client([
			'credentials' => [
				'key'    => $key,
				'secret' => $secret,
			],
			'region' => $region,
			'version' => 'latest',
		]);

		// Creates the S3 adapter to cast to the filesystem object
		$adapter = new AwsS3Adapter($client, $bucket);

		// Creates the filesystem object
		$this->filesystem = new Filesystem($adapter);

		// Adds the plugin to create file URLs
		$this->filesystem->addPlugin(new PresignedUrl());

	}

	/**
	 * Loads local file on S3 bucket.
	 *
	 * @param string Local file path, including file name.
	 * @param string Remote file path, including file name.
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
	 * @throws PairException In case of error creating the local file.
	 */
	public function get(string $remoteFile, string $localFilePath): bool {

		if (!$this->exists($remoteFile)) return FALSE;

		try {
			file_put_contents($localFilePath, $this->read($remoteFile));
		} catch(\Throwable $e) {
			throw new PairException($e->getMessage(), ErrorCodes::AMAZON_S3_ERROR, $e);
		}

		return TRUE;

	}

	/**
	 * Reads the remote file and returns the content.
	 *
	 * @param string Path of the remote file.
	 */
	public function read(string $remoteFile): string {

		if (!$this->exists($remoteFile)) {
			throw new PairException('File not found: ' . $remoteFile, ErrorCodes::AMAZON_S3_ERROR);
		}

		try {
			$content = $this->filesystem->read($remoteFile);
		} catch (\Exception $e) {
			throw new PairException($e->getMessage(), ErrorCodes::AMAZON_S3_ERROR, $e);
		}

		if (FALSE === $content) {
			throw new PairException('Error reading file: ' . $remoteFile, ErrorCodes::AMAZON_S3_ERROR);
		}

		return $content;

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
	 * @throws PairException
	 */
	public function exists(string $remoteFile): bool {

		return $this->filesystem->has($remoteFile);

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
				return TRUE;
			}
		} catch (\Exception $e) {
			throw new PairException($e->getMessage(), ErrorCodes::AMAZON_S3_ERROR, $e);
		}

		return FALSE;

	}

	/**
	 * Deletes the remote directory at the specified path.
	 */
	public function deleteDir(string $remoteDir): bool {

		try {
			if ($this->exists($remoteDir)) {
				$this->filesystem->deleteDir($remoteDir);
				return TRUE;
			}
		} catch (\Exception $e) {
			throw new PairException($e->getMessage(), ErrorCodes::AMAZON_S3_ERROR, $e);
		}

		return FALSE;

	}

}