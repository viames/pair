<?php

namespace Pair\Services;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\Exception\AwsException;

use Composer\InstalledVersions;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Amazon S3 wrapper built on AWS SDK v3.
 *
 * Requirements:
 *   composer require aws/aws-sdk-php:^3
 *
 * This class exposes common file operations (exists, read, put, delete, deleteDir),
 * plus a simple presigned URL generator and a URL validator designed for S3 SigV4.
 */
class AmazonS3 {

	/**
	 * Amazon S3 Client (for internal use).
	 */
	protected S3Client $client;

	/**
	 * Bucket name (for internal use).
	 */
	protected string $bucket;

	/**
	 * Starts Amazon S3 driver with AWS SDK v3 and Flysystem v3.
	 *
	 * @param	string	Access Key ID.
	 * @param	string	Secret Access Key.
	 * @param	string	Bucket Region.
	 * @param	string	Bucket Name.
	 */
	public function __construct(string $key, string $secret, string $region, string $bucket) {

		$this->assertDependencies();

		try {

			$this->client = new S3Client([
				'version' => 'latest',
				'region' => $region,
				'credentials' => [
					'key' => $key,
					'secret' => $secret
				]
			]);

			$this->bucket = $bucket;

		} catch (\Throwable $e) {
			throw new PairException('Unable to initialize Amazon S3 driver: ' . $e->getMessage(), ErrorCodes::AMAZON_S3_ERROR);
		}

	}

	/**
	 * Verifies that required Composer package aws/aws-sdk-php ^3 is installed.
	 *
	 * @throws PairException If any dependency is missing.
	 */
	private function assertDependencies(): void {

		$missing = [];

		if (class_exists(InstalledVersions::class) and method_exists(InstalledVersions::class, 'isInstalled')) {

			if (!InstalledVersions::isInstalled('aws/aws-sdk-php')) {
				$missing[] = 'aws/aws-sdk-php:^3';
			}

		} else {

			if (!class_exists(\Aws\S3\S3Client::class)) {
				$missing[] = 'aws/aws-sdk-php:^3';
			}

		}

		if ($missing) {
			$cmd = 'composer require ' . implode(' ', $missing);
			throw new PairException(
				'Missing required packages for AmazonS3: ' . implode(', ', $missing) . '. Install with: ' . $cmd,
				ErrorCodes::AMAZON_S3_ERROR
			);
		}

	}

	/**
	 * Deletes the remote file at the specified path.
	 *
	 * @param	string	$remoteFile	Path of the remote file.
	 * @return	bool				True if the file was deleted, false if it did not exist.
	 * @throws	\Exception			In case of error during deletion.
	 */
	public function delete(string $remoteFile): bool {

		if (!$this->exists($remoteFile)) {
			return false;
		}

		try {
			$this->client->deleteObject([
				'Bucket' => $this->bucket,
				'Key' => $remoteFile,
			]);
		} catch (\Throwable $e) {
			throw new \Exception('S3 delete() failed: ' . $e->getMessage(), ErrorCodes::AMAZON_S3_ERROR, $e);
		}

		return true;

	}

	/**
	 * Deletes the remote directory at the specified path.
	 * 
	 * @param	string	$remoteDir	Path of the remote directory.
	 * @return	bool				True if the directory was deleted, false if it did not exist.
	 * @throws	\Exception			In case of error during deletion.
	 */
	public function deleteDir(string $remoteDir): bool {

		$prefix = rtrim($remoteDir, '/');
		if ($prefix !== '') {
			$prefix .= '/';
		}

		$result = $this->client->listObjectsV2([
			'Bucket' => $this->bucket,
			'Prefix' => $prefix,
		]);

		$objects = [];
		foreach ($result['Contents'] ?? [] as $object) {
			$objects[] = ['Key' => $object['Key']];
		}

		if (!$objects) {
			return false;
		}

		$this->client->deleteObjects([
			'Bucket' => $this->bucket,
			'Delete' => [
				'Objects' => $objects,
				'Quiet' => true,
			],
		]);

		return true;

	}

	/**
	 * Checks if a file exists in the S3 bucket.
	 *
	 * @param string	$remoteFile	Path of the remote file to check.
	 * @return bool					True if the file exists, false otherwise.
	 * @throws PairException		In case of error during the check.
	 */
	public function exists(string $remoteFile): bool {

		try {
			$this->client->headObject([
				'Bucket' => $this->bucket,
				'Key' => $remoteFile,
			]);
			return true;
		} catch (S3Exception $e) {
			$status = $e->getStatusCode();
			if (in_array($status, [403, 404], true)) {
				return false;
			}
			throw new PairException('S3 exists() error: ' . $e->getMessage(), ErrorCodes::AMAZON_S3_ERROR, $e);
		} catch (\Throwable $e) {
			throw new PairException('S3 exists() error: ' . $e->getMessage(), ErrorCodes::AMAZON_S3_ERROR, $e);
		}

	}

	/**
	 * Downloads the remote file on local filesystem.
	 *
	 * @param	string $remoteFile		Path of the remote file.
	 * @param	string $localFilePath	Path of the local file (including file name).
	 * @throws	\Exception				In case of error creating the local file.
	 */
	public function get(string $remoteFile, string $localFilePath): bool {

		if (!$this->exists($remoteFile)) return false;

		try {
			file_put_contents($localFilePath, $this->read($remoteFile));
		} catch(\Throwable $e) {
			throw new \Exception($e->getMessage(), ErrorCodes::AMAZON_S3_ERROR, $e);
		}

		return true;

	}

	/**
	 * Returns a presigned URL for the file if it exists, null otherwise.
	 *
	 * @param	string	$remoteFile	Object key within the bucket.
	 * @param	int		$expiration	Expiration time in seconds (default 3600, max 604800).
	 * @return	string|null	The presigned URL or null if the file does not exist.
	 * @throws	\Exception	In case of error generating the URL.
	 */
	public function presignedUrl(string $remoteFile, int $expiration = 3600): ?string {

		// clamp to the S3 allowed range: 1 sec to 7 days
		$ttl = max(1, min(604800, (int)$expiration));

		// verify object existence with a cheap HEAD request
		try {

			$this->client->headObject([
				'Bucket' => $this->bucket,
				'Key'    => $remoteFile,
			]);

		} catch (S3Exception $e) {

			// treat 404/403 as “not available”
			if ($e->getStatusCode() === 404 || $e->getStatusCode() === 403) {
				return null;
			}

			throw new \Exception('Failed to check object existence: ' . $e->getMessage(), 0, $e);

		} catch (\Throwable $e) {

			throw new \Exception('Failed to check object existence: ' . $e->getMessage(), 0, $e);

		}

		// generate a presigned URL using a relative expiration
		try {
			$cmd = $this->client->getCommand('GetObject', [
				'Bucket' => $this->bucket,
				'Key'    => $remoteFile,
			]);

			// e.g. "+3600 seconds"
			$req = $this->client->createPresignedRequest($cmd, '+' . $ttl . ' seconds');

			return (string)$req->getUri();

		} catch (AwsException $e) {

			throw new \Exception('S3 presignedUrl() AWS error: ' . $e->getAwsErrorMessage(), ErrorCodes::AMAZON_S3_ERROR);

		} catch (\Throwable $e) {

			throw new \Exception('Failed to generate presigned URL: ' . $e->getMessage(), 0, $e);

		}

	}

	/**
	 * Uploads a local file to S3 (streaming) with optional automatic MIME.
	 *
	 * @param string	$filePath		Local file path.
	 * @param string	$destination	Remote path in bucket.
	 * @throws \Exception
	 */
	public function put(string $filePath, string $destination): void {

		if (!is_file($filePath) or !is_readable($filePath)) {
			throw new \Exception('Local file not readable: ' . $filePath, ErrorCodes::AMAZON_S3_ERROR);
		}

		$stream = fopen($filePath, 'rb');
		if (false === $stream) {
			throw new \Exception('Unable to open local file: ' . $filePath, ErrorCodes::AMAZON_S3_ERROR);
		}

		// try to detect MIME for proper Content-Type on S3
		$mime = function_exists('mime_content_type') ? @mime_content_type($filePath) : null;
		$options = [];
		if ($mime) {
			$options['ContentType'] = $mime;
		}

		try {
			$this->client->putObject(array_merge([
				'Bucket' => $this->bucket,
				'Key' => $destination,
				'Body' => $stream,
			], $options));
		} catch (\Throwable $e) {
			throw new \Exception('S3 put() failed: ' . $e->getMessage(), ErrorCodes::AMAZON_S3_ERROR);
		} finally {
			@fclose($stream);
		}

	}

	/**
	 * Reads the remote file and returns the content.
	 *
	 * @param string Path of the remote file.
	 * @return string Content of the remote file.
	 * @throws \Exception In case of error during reading.
	 */
	public function read(string $remoteFile): string {

		if (!$this->exists($remoteFile)) {
			throw new \Exception('File not found: ' . $remoteFile, ErrorCodes::AMAZON_S3_ERROR);
		}

		try {
			$result = $this->client->getObject([
				'Bucket' => $this->bucket,
				'Key' => $remoteFile,
			]);
			$content = (string)$result['Body'];
		} catch (\Throwable $e) {
			throw new \Exception($e->getMessage(), ErrorCodes::AMAZON_S3_ERROR, $e);
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

		$timeout = 5;

		$p = parse_url($url);

		// invalid URL
		if (!$p or empty($p['host'])) {
			return false;
		}

		// parse query string
		parse_str($p['query'] ?? '', $q);

		// presigned S3 (SigV4)
		if (isset($q['X-Amz-Date'], $q['X-Amz-Expires'])) {
			$dt = \DateTimeImmutable::createFromFormat('Ymd\THis\Z', $q['X-Amz-Date'], new \DateTimeZone('UTC'));
			if (!$dt) return false;
			return (time() + $skew) < ($dt->getTimestamp() + (int)$q['X-Amz-Expires']);
		}

		// CloudFront simply signed URL
		if (isset($q['Expires'])) {
			return (time() + $skew) < (int)$q['Expires'];
		}

		// non presigned, optional check HEAD
		$ch = curl_init($url);

		curl_setopt_array($ch, [
			CURLOPT_NOBODY         => true,  // HEAD-like request
			CURLOPT_CUSTOMREQUEST  => 'HEAD',
			CURLOPT_RETURNTRANSFER => true,  // do not output anything
			CURLOPT_FOLLOWLOCATION => false, // treat 3xx as success without following
			CURLOPT_CONNECTTIMEOUT => $timeout,
			CURLOPT_TIMEOUT        => $timeout,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_USERAGENT      => 'Pair/HTTP-Check'
		]);

		curl_exec($ch);
		$err  = curl_errno($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		curl_close($ch);

		// network/transport error
		if (0 !== $err) {
			return false;
		}

		return ($code >= 200 and $code < 400);

	}

}
