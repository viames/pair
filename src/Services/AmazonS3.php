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
	 * Amazon S3 Client (for internal use).
	 */
	protected S3Client $client;

	/**
	 * Bucket name (for internal use).
	 */
	protected string $bucket;

	/**
	 * Bucket region (for internal use).
	 */
	protected string $region;

	/**
	 * Default TTL for presigned URLs (in seconds).
	 */
	protected int $defaultTtl = 3600;

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
		$this->client = new S3Client([
			'credentials' => [
				'key'    => $key,
				'secret' => $secret,
			],
			'region' => $region,
			'version' => 'latest',
		]);

		$this->bucket = $bucket;
		$this->region = $region;

		// Creates the S3 adapter to cast to the filesystem object
		$adapter = new AwsS3Adapter($this->client, $this->bucket);

		// Creates the filesystem object
		$this->filesystem = new Filesystem($adapter);

		// Adds the plugin to create file URLs
		$this->filesystem->addPlugin(new PresignedUrl());

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
	 * Returns a presigned URL for the file if it exists, null otherwise.
	 *
	 * @param string Path of the remote file.
	 * @param int Expiration time in seconds (default 3600, max 604800).
	 * @return string|null The presigned URL or null if the file does not exist.
	 * @throws PairException In case of error generating the URL.
	 */
	public function presignedUrl(string $remoteFile, int $expiration = 3600): ?string {
		
		// clamp to the S3 allowed range: 1 to 7 days
		$ttl = max(1, min(604800, (int)$expiration));

		// Verify object existence with a cheap HEAD request
		try {
			
			$this->client->headObject([
				'Bucket' => $this->bucket,
				'Key'    => $remoteFile,
			]);

		} catch (S3Exception $e) {

			// Treat 404/403 as “not available”
			if ($e->getStatusCode() === 404 || $e->getStatusCode() === 403) {
				return null;
			}

			throw new \Exception('Failed to check object existence: ' . $e->getMessage(), 0, $e);

		} catch (\Throwable $e) {

			throw new \Exception('Failed to check object existence: ' . $e->getMessage(), 0, $e);

		}

		// Generate a presigned URL using a relative expiration
		try {
			$cmd = $this->client->getCommand('GetObject', [
				'Bucket' => $this->bucket,
				'Key'    => $remoteFile,
			]);

			// e.g. "+3600 seconds"
			$req = $this->client->createPresignedRequest($cmd, '+' . $ttl . ' seconds');

			return (string)$req->getUri();

		} catch (\Throwable $e) {

			throw new \Exception('Failed to generate presigned URL: ' . $e->getMessage(), 0, $e);
		
		}
	
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
	 * Verify if a given URL is still valid (not expired).
	 *
	 * @param	string	The URL to verify.
	 * @param	int		Optional time skew in seconds to allow for clock differences (default 30s).
	 * @return	bool	True if the URL is still valid, false otherwise.
	 */
	public function validUrl(string $url, int $skew = 30): bool {

		$p = parse_url($url);

		// invalid URL
		if (!$p or empty($p['host'])) {
			return FALSE;
		}

		// parse query string
		parse_str($p['query'] ?? '', $q);

		// presigned S3 (SigV4)
		if (isset($q['X-Amz-Date'], $q['X-Amz-Expires'])) {
			$dt = \DateTimeImmutable::createFromFormat('Ymd\THis\Z', $q['X-Amz-Date'], new \DateTimeZone('UTC'));
			if (!$dt) return FALSE;
			return (time() + $skew) < ($dt->getTimestamp() + (int)$q['X-Amz-Expires']);
		}

		// CloudFront simply signed URL
		if (isset($q['Expires'])) {
			return (time() + $skew) < (int)$q['Expires'];
		}

		// non presigned, optional check HEAD
		$ch = curl_init($url);

		curl_setopt_array($ch, [
			CURLOPT_NOBODY         => TRUE,  // HEAD-like request
			CURLOPT_CUSTOMREQUEST  => 'HEAD',
			CURLOPT_RETURNTRANSFER => TRUE,  // do not output anything
			CURLOPT_FOLLOWLOCATION => FALSE, // treat 3xx as success without following
			CURLOPT_CONNECTTIMEOUT => $timeout,
			CURLOPT_TIMEOUT        => $timeout,
			CURLOPT_SSL_VERIFYPEER => TRUE,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_USERAGENT      => 'Pair/HTTP-Check'
		]);

		curl_exec($ch);
		$err  = curl_errno($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		curl_close($ch);
		
		// network/transport error
		if (0 !== $err) {
			return FALSE;
		}

		return ($code >= 200 and $code < 400);

	}

}