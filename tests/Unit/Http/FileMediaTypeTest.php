<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Http;

use Pair\Http\FileMediaType;
use Pair\Tests\Support\TestCase;

/**
 * Covers the shared MIME category registry used by uploads and file controls.
 */
class FileMediaTypeTest extends TestCase {

	/**
	 * Verify MIME types and file extensions resolve to the expected shared categories.
	 */
	public function testCategoryMatchesMimeTypesAndExtensions(): void {

		$this->assertSame('image', FileMediaType::category('IMAGE/PNG'));
		$this->assertSame('pdf', FileMediaType::category('application/pdf'));
		$this->assertSame('zip', FileMediaType::category('.zip'));
		$this->assertNull(FileMediaType::category('application/x-custom'));

	}

	/**
	 * Verify unknown categories return an empty list.
	 */
	public function testCategoryTypesReturnsEmptyArrayForUnknownCategory(): void {

		$this->assertSame([], FileMediaType::categoryTypes('unknown'));
		$this->assertContains('image/png', FileMediaType::categoryTypes('image'));

	}

	/**
	 * Verify ambiguous MIME types can be checked against all categories that accept them.
	 */
	public function testMatchesCategorySupportsAmbiguousMimeTypes(): void {

		$this->assertTrue(FileMediaType::matchesCategory('text/plain', 'csv'));
		$this->assertTrue(FileMediaType::matchesCategory('text/plain', 'document'));
		$this->assertContains('csv', FileMediaType::categories('text/plain'));
		$this->assertContains('document', FileMediaType::categories('text/plain'));

	}

}
