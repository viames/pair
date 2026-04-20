<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Tests\Support\TestCase;

/**
 * Covers script rendering for Pair application pages.
 */
class ApplicationScriptsTest extends TestCase {

	/**
	 * Verify Pair no longer injects Insight Hub client scripts from the core runtime.
	 */
	public function testScriptsIgnoreLegacyInsightHubConfiguration(): void {

		$this->writeEnvFile(implode("\n", [
			'INSIGHT_HUB_API_KEY=test-key',
			'INSIGHT_HUB_PERFORMANCE=true',
		]));
		Env::load();

		$reflection = new \ReflectionClass(Application::class);
		$app = $reflection->newInstanceWithoutConstructor();

		$scripts = $app->scripts();

		$this->assertStringContainsString('window.PairToastConfig', $scripts);
		$this->assertStringNotContainsString('bugsnag-js', $scripts);
		$this->assertStringNotContainsString('BugsnagPerformance', $scripts);
		$this->assertStringNotContainsString('test-key', $scripts);

	}

}
