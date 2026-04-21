<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\Application;
use Pair\Html\UiTheme;
use Pair\Tests\Support\TestCase;

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
