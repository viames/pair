<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Html;

use Pair\Exceptions\PairException;
use Pair\Html\TemplateRenderer;
use Pair\Html\Widget;
use Pair\Tests\Support\TestCase;

/**
 * Covers template placeholder rendering without directory-wide widget scans.
 */
class TemplateRendererTest extends TestCase {

	/**
	 * Prepare an isolated widgets folder for template rendering tests.
	 */
	protected function setUp(): void {

		parent::setUp();

		if (!is_dir(APPLICATION_PATH . '/widgets')) {
			mkdir(APPLICATION_PATH . '/widgets', 0777, true);
		}

	}

	/**
	 * Remove temporary widget files after each test.
	 */
	protected function tearDown(): void {

		$this->removeDirectory(APPLICATION_PATH . '/widgets');

		if (file_exists(APPLICATION_PATH . '/template-style.php')) {
			unlink(APPLICATION_PATH . '/template-style.php');
		}

		parent::tearDown();

	}

	/**
	 * Verify only real widget placeholders are rendered and app placeholders are preserved.
	 */
	public function testRenderWidgetsPreservesApplicationPlaceholders(): void {

		file_put_contents(APPLICATION_PATH . '/widgets/sampleWidget.php', '<?php print "WIDGET";');

		$method = new \ReflectionMethod(TemplateRenderer::class, 'renderWidgets');
		$html = $method->invoke(null, '{{title}} {{ sampleWidget }} {{missingWidget}}');

		$this->assertSame('{{title}} WIDGET {{missingWidget}}', $html);

	}

	/**
	 * Verify cached template contents are invalidated when the file signature changes.
	 */
	public function testStyleFileCacheInvalidatesOnFileChange(): void {

		$templateFile = APPLICATION_PATH . '/template-style.php';
		file_put_contents($templateFile, 'first');

		$cache = new \ReflectionProperty(TemplateRenderer::class, 'styleFileCache');
		$cache->setValue(null, []);

		$method = new \ReflectionMethod(TemplateRenderer::class, 'loadStyleFile');

		$this->assertSame('first', $method->invoke(null, $templateFile));

		file_put_contents($templateFile, 'second-content');

		$this->assertSame('second-content', $method->invoke(null, $templateFile));

	}

	/**
	 * Verify widget lookup rejects names that could escape the widget directory.
	 */
	public function testWidgetRejectsUnsafeNames(): void {

		$this->assertFalse(Widget::exists('../sampleWidget'));

		$this->expectException(PairException::class);

		(new Widget('../sampleWidget'))->render();

	}

}
