<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Helpers;

use Pair\Core\Router;
use Pair\Helpers\Utilities;
use Pair\Html\UiTheme;
use Pair\Tests\Support\TestCase;

/**
 * Covers theme-aware helper output that renders small HTML fragments.
 */
class UtilitiesTest extends TestCase {

	/**
	 * Reset Router singleton state so the no-data helper does not trigger redirects.
	 */
	protected function setUp(): void {

		parent::setUp();

		if (!defined('URL_PATH')) {
			define('URL_PATH', null);
		}

		if (!defined('BASE_HREF')) {
			define('BASE_HREF', '/');
		}

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/helpers/default';
		$_SERVER['SCRIPT_NAME'] = '/public/index.php';
		$_SERVER['HTTP_HOST'] = 'pair.test';

		$router = Router::getInstance();
		$router->module = 'helpers';
		$router->action = 'default';
		$router->vars = [];
		$router->page = 1;
		$router->order = null;

	}

	/**
	 * Verify the no-data helper keeps Bootstrap-compatible output by default.
	 */
	public function testShowNoDataAlertUsesBootstrapMarkupByDefault(): void {

		ob_start();
		Utilities::showNoDataAlert('Nothing to show');
		$html = (string)ob_get_clean();

		$this->assertStringContainsString('class="alert alert-primary"', $html);
		$this->assertStringContainsString('Nothing to show', $html);

	}

	/**
	 * Verify the no-data helper switches to Bulma notification markup when configured.
	 */
	public function testShowNoDataAlertUsesBulmaMarkupWhenConfigured(): void {

		UiTheme::setCurrent('bulma');

		ob_start();
		Utilities::showNoDataAlert('Nothing to show');
		$html = (string)ob_get_clean();

		$this->assertStringContainsString('class="notification is-primary"', $html);
		$this->assertStringContainsString('Nothing to show', $html);

	}

}
