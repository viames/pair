<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\FilesystemMetadata;
use Pair\Tests\Support\TestCase;

/**
 * Covers process-local filesystem metadata caching.
 */
class FilesystemMetadataTest extends TestCase {

	/**
	 * Temporary paths created by this test case.
	 *
	 * @var string[]
	 */
	private array $temporaryPaths = [];

	/**
	 * Clean temporary files and cached metadata after each test.
	 */
	protected function tearDown(): void {

		foreach ($this->temporaryPaths as $path) {
			if (file_exists($path)) {
				unlink($path);
			}
		}

		FilesystemMetadata::clear();

		parent::tearDown();

	}

	/**
	 * Verify cached missing paths remain missing until their specific cache key is cleared.
	 */
	public function testFileExistsCachesMissingPathUntilCleared(): void {

		$path = $this->temporaryPath();

		$this->assertFalse(FilesystemMetadata::fileExists($path));

		file_put_contents($path, 'cached');

		$this->assertFalse(FilesystemMetadata::fileExists($path));

		FilesystemMetadata::clear($path);

		$this->assertTrue(FilesystemMetadata::fileExists($path));

	}

	/**
	 * Verify clearing all metadata invalidates cached existing paths.
	 */
	public function testClearInvalidatesAllCachedPaths(): void {

		$path = $this->temporaryPath();

		file_put_contents($path, 'cached');

		$this->assertTrue(FilesystemMetadata::fileExists($path));

		unlink($path);

		$this->assertTrue(FilesystemMetadata::fileExists($path));

		FilesystemMetadata::clear();

		$this->assertFalse(FilesystemMetadata::fileExists($path));

	}

	/**
	 * Return a unique temporary path without creating the file.
	 */
	private function temporaryPath(): string {

		$path = tempnam(sys_get_temp_dir(), 'pair-filesystem-metadata-');

		if (false === $path) {
			$this->fail('Unable to create a temporary path for filesystem metadata tests.');
		}

		unlink($path);
		$this->temporaryPaths[] = $path;

		return $path;

	}

}
