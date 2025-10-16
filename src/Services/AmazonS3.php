<?php

namespace Pair\Services;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

use Composer\InstalledVersions;

use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Amazon S3 wrapper built on Flysystem v3.
 *
 * Requirements:
 *   composer require league/flysystem:^3 league/flysystem-aws-s3-v3:^3 aws/aws-sdk-php:^3
 *
 * This class exposes common file operations (exists, read, put, delete, deleteDir),
 * plus a simple presigned URL generator and a URL validator designed for S3 SigV4.
 */
class AmazonS3 {

	/**
	 * FilesystemOperator object (for internal use).
	 */
	protected FilesystemOperator $filesystem;

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

			// creates the S3 adapter to cast to the filesystem object
			$adapter = new AwsS3V3Adapter($this->client, $this->bucket);

			// default config, can be overridden per-call
			$this->filesystem = new Filesystem($adapter, [
				'visibility' => 'private',
			]);

		} catch (\Throwable $e) {
			throw new PairException('Unable to initialize Amazon S3 driver: ' . $e->getMessage(), ErrorCodes::AMAZON_S3_ERROR);
		}

	}

	/**
	 * Verifies that required Composer packages are installed:
	 * 1. league/flysystem-aws-s3-v3 (implies league/flysystem ^3)
	 * 2. aws/aws-sdk-php ^3
	 *
	 * @throws PairException if any dependency is missing
	 */
	private function assertDependencies(): void {

		$missing = [];

		if (class_exists(InstalledVersions::class) and method_exists(InstalledVersions::class, 'isInstalled')) {

			if (!InstalledVersions::isInstalled('league/flysystem-aws-s3-v3')) {
				$missing[] = 'league/flysystem-aws-s3-v3:^3';
			}

			if (!InstalledVersions::isInstalled('aws/aws-sdk-php')) {
				$missing[] = 'aws/aws-sdk-php:^3';
			}

		} else {

			// fallback if InstalledVersions is not available
			if (!class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class)) {
				$missing[] = 'league/flysystem-aws-s3-v3:^3';
			}

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
	 * Checks if a remote file exists in S3.
	 *
	 * @param string	Path of the remote file in the bucket.
	 * @return bool		TRUE if the file exists, FALSE otherwise.
	 * @throws PairException
	 */
	public function exists(string $remoteFile): bool {

		try {
			return $this->filesystem->fileExists($remoteFile);
		} catch (\Throwable $e) {
			throw new PairException('S3 exists() failed: ' . $e->getMessage(), ErrorCodes::AMAZON_S3_ERROR);
		}

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
	 * Returns a presigned URL for the file if it exists, NULL otherwise.
	 *
	 * @param string Object key within the bucket.
	 * @param int Expiration time in seconds (default 3600, max 604800).
	 * @return string|NULL The presigned URL or NULL if the file does not exist.
	 * @throws PairException In case of error generating the URL.
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
				return NULL;
			}

			throw new PairException('Failed to check object existence: ' . $e->getMessage(), 0, $e);

		} catch (\Throwable $e) {

			throw new PairException('Failed to check object existence: ' . $e->getMessage(), 0, $e);

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

			throw new PairException('S3 presignedUrl() AWS error: ' . $e->getAwsErrorMessage(), ErrorCodes::AMAZON_S3_ERROR);

		} catch (\Throwable $e) {

			throw new PairException('Failed to generate presigned URL: ' . $e->getMessage(), 0, $e);

		}

	}

	/**
	 * Uploads a local file to S3 (streaming) with optional automatic MIME.
	 *
	 * @param string	Local source path
	 * @param string	Remote path in bucket
	 * @throws PairException
	 */
	public function put(string $filePath, string $destination): void {

		if (!is_file($filePath) or !is_readable($filePath)) {
			throw new PairException('Local file not readable: ' . $filePath, ErrorCodes::AMAZON_S3_ERROR);
		}

		$stream = fopen($filePath, 'rb');
		if (FALSE === $stream) {
			throw new PairException('Unable to open local file: ' . $filePath, ErrorCodes::AMAZON_S3_ERROR);
		}

		// try to detect MIME for proper Content-Type on S3
		$mime = function_exists('mime_content_type') ? @mime_content_type($filePath) : NULL;
		$options = [];
		if ($mime) {
			// Flysystem v3 S3 adapter maps "mimetype" to S3 ContentType
			$options['mimetype'] = $mime;
		}

		try {
			$this->filesystem->writeStream($destination, $stream, $options);
		} catch (\Throwable $e) {
			throw new PairException('S3 put() failed: ' . $e->getMessage(), ErrorCodes::AMAZON_S3_ERROR);
		} finally {
			@fclose($stream);
		}

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
	 * @return	bool	TRUE if the URL is still valid, FALSE otherwise.
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