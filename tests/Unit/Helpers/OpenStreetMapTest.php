<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Helpers;

use Pair\Tests\Support\TestCase;

/**
 * Covers OpenStreetMap asset registration for Pair views.
 */
class OpenStreetMapTest extends TestCase {

	/**
	 * Verify the helper registers the stylesheet and deferred client script.
	 */
	public function testLoadRegistersCssAndJavascriptAssets(): void {

		$result = $this->runPhpSnippet(implode("\n", [
			'\\Pair\\Helpers\\OpenStreetMap::load("custom/assets");',
			'$app = \\Pair\\Core\\Application::getInstance();',
			'echo $app->styles();',
			'echo "---scripts---" . PHP_EOL;',
			'echo $app->scripts();',
		]));

		$this->assertSame(0, $result['exitCode'], 'STDOUT: ' . $result['stdout'] . ' STDERR: ' . $result['stderr']);
		$this->assertStringContainsString('<link rel="stylesheet" href="/custom/assets/pair.css">', $result['stdout']);
		$this->assertStringContainsString('<script src="/custom/assets/PairOpenStreetMap.js" defer></script>', $result['stdout']);

	}

}
