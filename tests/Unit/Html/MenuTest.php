<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Html;

use Pair\Core\Application;
use Pair\Core\Router;
use Pair\Html\BootstrapMenu;
use Pair\Html\Menu;
use Pair\Tests\Support\TestCase;

/**
 * Covers menu rendering helpers that build navigation HTML.
 */
class MenuTest extends TestCase {

	/**
	 * Prepare singleton state required by menu construction.
	 */
	protected function setUp(): void {

		parent::setUp();

		if (!defined('URL_PATH')) {
			define('URL_PATH', null);
		}

		$this->resetApplicationSingleton();
		$router = Router::getInstance();
		$router->module = 'dashboard';
		$router->action = 'default';

	}

	/**
	 * Reset shared singleton state after each menu rendering test.
	 */
	protected function tearDown(): void {

		$this->resetApplicationSingleton();

		parent::tearDown();

	}

	/**
	 * Verify the default menu escapes user-controlled entry fields.
	 */
	public function testMenuEscapesEntryFields(): void {

		$menu = new Menu();
		$menu->item(
			'/profile?name=<script>alert(1)</script>',
			'Profile <script>',
			'fa-user" onclick="x',
			'new <b>',
			'danger" onclick="x',
			'_blank" onclick="x'
		);

		$html = $menu->render();

		$this->assertStringNotContainsString('<script>', $html);
		$this->assertStringNotContainsString('onclick="x"', $html);
		$this->assertStringContainsString('href="/profile?name=&lt;script&gt;alert(1)&lt;/script&gt;"', $html);
		$this->assertStringContainsString('target="_blank&quot; onclick=&quot;x"', $html);
		$this->assertStringContainsString('fa-user&quot; onclick=&quot;x', $html);
		$this->assertStringContainsString('Profile &lt;script&gt;', $html);
		$this->assertStringContainsString('badge-danger&quot; onclick=&quot;x', $html);
		$this->assertStringContainsString('new &lt;b&gt;', $html);

	}

	/**
	 * Verify the Bootstrap menu renderer uses the same escaped fields.
	 */
	public function testBootstrapMenuEscapesEntryFields(): void {

		$menu = new BootstrapMenu();
		$menu->item(
			'/profile?name=<script>alert(1)</script>',
			'Profile <script>',
			'fa-user" onclick="x',
			'new <b>',
			'danger" onclick="x',
			'_blank" onclick="x'
		);

		$html = $menu->render();

		$this->assertStringNotContainsString('<script>', $html);
		$this->assertStringNotContainsString('onclick="x"', $html);
		$this->assertStringContainsString('href="/profile?name=&lt;script&gt;alert(1)&lt;/script&gt;"', $html);
		$this->assertStringContainsString('target="_blank&quot; onclick=&quot;x"', $html);
		$this->assertStringContainsString('fa-user&quot; onclick=&quot;x', $html);
		$this->assertStringContainsString('Profile &lt;script&gt;', $html);
		$this->assertStringContainsString('badge-danger&quot; onclick=&quot;x', $html);
		$this->assertStringContainsString('new &lt;b&gt;', $html);

	}

	/**
	 * Reset the Application singleton to avoid leaking menu state between tests.
	 */
	private function resetApplicationSingleton(): void {

		$property = new \ReflectionProperty(Application::class, 'instance');
		$property->setValue(null, null);

	}

}
