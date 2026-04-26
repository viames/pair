<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Html;

use Pair\Html\FormControls\File;
use Pair\Tests\Support\TestCase;

/**
 * Covers file input MIME category rendering.
 */
class FileControlTest extends TestCase {

	/**
	 * Verify file controls render accept values from the shared MIME category registry.
	 */
	public function testAcceptCategoryUsesSharedMimeCategoryRegistry(): void {

		$html = (new File('attachment'))
			->acceptCategory('pdf')
			->acceptCategory('image')
			->render();

		$this->assertStringContainsString('accept="', $html);
		$this->assertStringContainsString('application/pdf', $html);
		$this->assertStringContainsString('image/png', $html);
		$this->assertSame('pdf', File::mimeCategory('application/pdf'));

	}

}
