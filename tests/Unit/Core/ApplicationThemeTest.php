<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\Application;
use Pair\Html\UiRenderers\BootstrapUiRenderer;
use Pair\Html\UiRenderers\BulmaUiRenderer;
use Pair\Html\UiRenderers\NativeUiRenderer;
use Pair\Html\UiTheme;
use Pair\Tests\Fixtures\Html\LazyUiRenderer;
use Pair\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Covers the runtime API used to select the active UI framework.
 */
class ApplicationThemeTest extends TestCase {

	/**
	 * Verify the application runtime can switch the active UI framework with one method call.
	 */
	public function testUiFrameworkSelectsTheActiveUiFramework(): void {

		$reflection = new \ReflectionClass(Application::class);
		$app = $reflection->newInstanceWithoutConstructor();

		$this->assertSame($app, $app->uiFramework('bulma'));
		$this->assertSame(UiTheme::BULMA, UiTheme::current());

		$app->uiFramework('bootstrap');

		$this->assertSame(UiTheme::BOOTSTRAP, UiTheme::current());

		$app->uiFramework('native');

		$this->assertSame(UiTheme::NATIVE, UiTheme::current());

	}

	/**
	 * Verify native rendering does not load unused built-in framework renderers.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testNativeSelectionDoesNotLoadUnusedBuiltInRenderers(): void {

		UiTheme::reset();
		UiTheme::setCurrent('native');

		$this->assertFalse(class_exists(BootstrapUiRenderer::class, false));
		$this->assertFalse(class_exists(BulmaUiRenderer::class, false));

		$this->assertSame(UiTheme::NATIVE, UiTheme::current());

		$this->assertTrue(class_exists(NativeUiRenderer::class, false));
		$this->assertFalse(class_exists(BootstrapUiRenderer::class, false));
		$this->assertFalse(class_exists(BulmaUiRenderer::class, false));

	}

	/**
	 * Verify Bootstrap rendering does not load unrelated built-in framework renderers.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testBootstrapSelectionDoesNotLoadBulmaRenderer(): void {

		UiTheme::reset();
		UiTheme::setCurrent('bootstrap');

		$this->assertFalse(class_exists(BootstrapUiRenderer::class, false));
		$this->assertFalse(class_exists(BulmaUiRenderer::class, false));

		$this->assertSame('alert alert-primary', UiTheme::alertClass());

		$this->assertTrue(class_exists(BootstrapUiRenderer::class, false));
		$this->assertFalse(class_exists(BulmaUiRenderer::class, false));

	}

	/**
	 * Verify external renderer classes can be registered without loading them until selected.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testRendererClassesCanBeRegisteredLazily(): void {

		UiTheme::reset();

		$this->assertFalse(class_exists(LazyUiRenderer::class, false));

		UiTheme::registerRendererClass('lazy-fixture', LazyUiRenderer::class);
		UiTheme::setCurrent('native');

		$this->assertFalse(class_exists(LazyUiRenderer::class, false));

		UiTheme::setCurrent('lazy-fixture');

		$this->assertSame('lazy-fixture-alert', UiTheme::alertClass());
		$this->assertTrue(class_exists(LazyUiRenderer::class, false));

	}

	/**
	 * Verify resetting the theme returns to native HTML rendering.
	 */
	public function testUiThemeDefaultsToNativeHtmlRendering(): void {

		UiTheme::setCurrent('bootstrap');
		UiTheme::reset();

		$this->assertSame(UiTheme::NATIVE, UiTheme::current());
		$this->assertTrue(UiTheme::isNative());

	}

	/**
	 * Verify unsupported values are rejected so programming mistakes fail fast.
	 */
	public function testUiFrameworkRejectsUnknownValues(): void {

		$reflection = new \ReflectionClass(Application::class);
		$app = $reflection->newInstanceWithoutConstructor();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unsupported UI framework: unknown');

		$app->uiFramework('unknown');

	}

}
