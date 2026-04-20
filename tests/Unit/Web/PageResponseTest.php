<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Web;

use Pair\Tests\Support\FakePageState;
use Pair\Tests\Support\TestCase;
use Pair\Web\PageResponse;

/**
 * Covers explicit page rendering through the v4 response object.
 */
class PageResponseTest extends TestCase {

	/**
	 * Verify the response exposes only the typed state object to the template.
	 */
	public function testSendRendersTheTypedStateIntoTheTemplate(): void {

		$templateFile = TEMP_PATH . 'page-response-test.php';
		file_put_contents($templateFile, '<?php print htmlspecialchars($state->message, ENT_QUOTES, "UTF-8"); ?>');

		$response = new PageResponse($templateFile, new FakePageState('Hello Pair v4'));

		ob_start();
		$response->send();
		$output = ob_get_clean();

		unlink($templateFile);

		$this->assertSame('Hello Pair v4', $output);

	}

}
