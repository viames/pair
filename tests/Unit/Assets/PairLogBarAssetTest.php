<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Assets;

use Pair\Tests\Support\TestCase;

/**
 * Covers static safety contracts in the bundled LogBar client script.
 */
class PairLogBarAssetTest extends TestCase {

	/**
	 * Verify framework breakpoint detection is present for Bootstrap and Bulma LogBar shells.
	 */
	public function testBreakpointDetectionSupportsBootstrapAndBulma(): void {

		$source = $this->pairLogBarSource();

		$this->assertStringContainsString('const BOOTSTRAP_BREAKPOINTS', $source);
		$this->assertStringContainsString('{ name: "lg", min: 992 }', $source);
		$this->assertStringContainsString('const BULMA_BREAKPOINTS', $source);
		$this->assertStringContainsString('{ name: "desktop", min: 1024 }', $source);
		$this->assertStringContainsString('metric.querySelector(".logbar-context-value")', $source);
		$this->assertStringContainsString('window.addEventListener("resize", scheduleBreakpointUpdate)', $source);
		$this->assertStringContainsString('global.requestAnimationFrame ? global.requestAnimationFrame(refresh) : global.setTimeout(refresh, 16)', $source);

	}

	/**
	 * Verify runtime context values can be copied without adding external dependencies.
	 */
	public function testRuntimeContextCopyIsHandledByLogBarClient(): void {

		$source = $this->pairLogBarSource();

		$this->assertStringContainsString('[data-logbar-copy-value]', $source);
		$this->assertStringContainsString('function copyContextValue(button)', $source);
		$this->assertStringContainsString('global.navigator.clipboard.writeText(value)', $source);

	}

	/**
	 * Verify actionable finding cards can switch tabs and apply filters.
	 */
	public function testFindingActionsAreHandledByLogBarClient(): void {

		$source = $this->pairLogBarSource();

		$this->assertStringContainsString('[data-logbar-finding-action]', $source);
		$this->assertStringContainsString('function applyFindingAction(logbar, button)', $source);
		$this->assertStringContainsString('data-logbar-finding-duplicates-only', $source);
		$this->assertStringContainsString('row.getAttribute("data-logbar-duplicate") !== "1"', $source);
		$this->assertStringContainsString('function openFirstVisibleQueryGroup(logbar)', $source);
		$this->assertStringContainsString('logbar.classList.toggle("logbar-tab-" + name, name === tabName)', $source);

	}

	/**
	 * Verify the DB metric opens the query pane instead of toggling an invisible state.
	 */
	public function testDatabaseMetricOpensQueryPane(): void {

		$source = $this->pairLogBarSource();

		$this->assertStringContainsString('[data-logbar-query-toggle]', $source);
		$this->assertStringContainsString('function showQueries(logbar)', $source);
		$this->assertStringContainsString('body.classList.remove("hidden")', $source);
		$this->assertStringContainsString('setActiveTab(logbar, "queries")', $source);
		$this->assertStringContainsString('writeCookie("LogBarShowEvents", "1")', $source);

	}

	/**
	 * Verify SQL previews use explicit ellipsis controls without layout measurement.
	 */
	public function testQueryDetailTogglesAreHandledByLogBarClient(): void {

		$source = $this->pairLogBarSource();

		$this->assertStringContainsString('[data-logbar-query-detail-toggle]', $source);
		$this->assertStringContainsString('data-logbar-query-detail-active-class', $source);
		$this->assertStringContainsString('function renderQueryDetail(group)', $source);
		$this->assertStringContainsString('function setQueryDetailButtonState(group, expanded)', $source);
		$this->assertStringContainsString('function toggleQueryDetail(button)', $source);
		$this->assertStringNotContainsString('scrollWidth', $source);
		$this->assertStringNotContainsString('scrollHeight', $source);
		$this->assertStringContainsString('.logbar-query-summary', $source);

	}

	/**
	 * Return the Pair LogBar client source code.
	 */
	private function pairLogBarSource(): string {

		$source = file_get_contents(dirname(__DIR__, 3) . '/assets/PairLogBar.js');

		if (!is_string($source)) {
			$this->fail('Unable to read PairLogBar.js');
		}

		return $source;

	}

}
