<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Aws\Result;
use Aws\S3\S3Client;
use Pair\Exceptions\PairException;
use Pair\Services\AmazonS3;
use Pair\Tests\Support\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Covers the Amazon S3 bridge without performing network calls.
 */
class AmazonS3Test extends TestCase {

	/**
	 * Verify local uploads delegate file-handle ownership to the AWS SDK.
	 */
	public function testPutUsesSourceFileInsteadOfManagedBodyStream(): void {

		$this->skipWhenAwsSdkIsMissing();

		$client = new FakeAmazonS3Client();
		$amazonS3 = $this->amazonS3WithClient($client);
		$filePath = tempnam(sys_get_temp_dir(), 'pair-s3-');

		$this->assertIsString($filePath);
		file_put_contents($filePath, 'test body');

		try {
			$amazonS3->put($filePath, 'uploads/test.txt');
		} finally {
			if (is_file($filePath)) {
				unlink($filePath);
			}
		}

		$this->assertSame('test-bucket', $client->lastPutObjectArgs['Bucket']);
		$this->assertSame('uploads/test.txt', $client->lastPutObjectArgs['Key']);
		$this->assertSame($filePath, $client->lastPutObjectArgs['SourceFile']);
		$this->assertArrayNotHasKey('Body', $client->lastPutObjectArgs);

	}

	/**
	 * Verify empty object keys are rejected before invoking the AWS SDK.
	 */
	public function testPutRejectsEmptyObjectKey(): void {

		$this->skipWhenAwsSdkIsMissing();

		$client = new FakeAmazonS3Client();
		$amazonS3 = $this->amazonS3WithClient($client);
		$filePath = tempnam(sys_get_temp_dir(), 'pair-s3-');

		$this->assertIsString($filePath);
		file_put_contents($filePath, 'test body');

		try {
			$amazonS3->put($filePath, '   ');
			$this->fail('Expected an exception for an empty S3 object key.');
		} catch (PairException $e) {
			$this->assertSame([], $client->lastPutObjectArgs);
		} finally {
			if (is_file($filePath)) {
				unlink($filePath);
			}
		}

	}

	/**
	 * Build an AmazonS3 instance with fake dependencies.
	 */
	private function amazonS3WithClient(FakeAmazonS3Client $client): AmazonS3 {

		$reflectionClass = new ReflectionClass(AmazonS3::class);
		$amazonS3 = $reflectionClass->newInstanceWithoutConstructor();

		$this->setProtectedProperty($amazonS3, 'client', $client);
		$this->setProtectedProperty($amazonS3, 'bucket', 'test-bucket');

		return $amazonS3;

	}

	/**
	 * Set a protected AmazonS3 property for the isolated test double.
	 */
	private function setProtectedProperty(AmazonS3 $amazonS3, string $propertyName, mixed $value): void {

		$property = new ReflectionProperty(AmazonS3::class, $propertyName);
		$property->setValue($amazonS3, $value);

	}

	/**
	 * Skip S3 behavior tests when the optional AWS SDK is not installed.
	 */
	private function skipWhenAwsSdkIsMissing(): void {

		if (!class_exists(S3Client::class)) {
			$this->markTestSkipped('The optional aws/aws-sdk-php package is not installed.');
		}

	}

}

/**
 * Fake AWS client that records putObject calls without network access.
 */
if (class_exists(S3Client::class)) {
	class FakeAmazonS3Client extends S3Client {

		/**
		 * Arguments received by the last putObject call.
		 *
		 * @var array<string, mixed>
		 */
		public array $lastPutObjectArgs = [];

		/**
		 * Skip real AWS client initialization in unit tests.
		 */
		public function __construct() {

		}

		/**
		 * Record upload arguments and return a fake AWS result.
		 *
		 * @param array<string, mixed> $args Upload request arguments.
		 */
		public function putObject(array $args = []): Result {

			$this->lastPutObjectArgs = $args;

			return new Result([]);

		}

	}
}
