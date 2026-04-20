<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Html;

use Pair\Core\Router;
use Pair\Html\Pagination;
use Pair\Html\UiTheme;
use Pair\Tests\Support\TestCase;

/**
 * Covers pagination rendering across supported UI themes.
 */
class PaginationTest extends TestCase {

	/**
	 * Ensure Router can be created in the isolated test runtime.
	 */
	protected function setUp(): void {

		parent::setUp();

		if (!defined('URL_PATH')) {
			define('URL_PATH', null);
		}

	}

	/**
	 * Verify the default renderer keeps the legacy Bootstrap-compatible structure.
	 */
	public function testRenderUsesBootstrapMarkupByDefault(): void {

		$pagination = $this->buildPaginationFixture(3, 15, 160);

		$html = $pagination->render();

		$this->assertStringContainsString('<div class="pagination"><nav aria-label="Page navigation"><ul class="pagination">', $html);
		$this->assertStringContainsString('class="page-item current active"', $html);
		$this->assertStringContainsString('class="page-link"', $html);

	}

	/**
	 * Verify Bulma markup is rendered when the theme configuration requests it.
	 */
	public function testRenderUsesBulmaMarkupWhenConfigured(): void {

		UiTheme::setCurrent('bulma');

		$pagination = $this->buildPaginationFixture(3, 15, 160);

		$html = $pagination->render();

		$this->assertStringContainsString('<nav class="pagination" role="navigation" aria-label="pagination">', $html);
		$this->assertStringContainsString('class="pagination-previous"', $html);
		$this->assertStringContainsString('class="pagination-next"', $html);
		$this->assertStringContainsString('class="pagination-link is-current"', $html);
		$this->assertStringContainsString('aria-current="page"', $html);

	}

	/**
	 * Prepare a pagination instance bound to a deterministic router state.
	 *
	 * @param	int	$page		Current page number.
	 * @param	int	$perPage	Rows shown per page.
	 * @param	int	$count		Total rows available.
	 */
	private function buildPaginationFixture(int $page, int $perPage, int $count): Pagination {

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/users/default';
		$_SERVER['SCRIPT_NAME'] = '/public/index.php';
		$_SERVER['HTTP_HOST'] = 'pair.test';

		$router = Router::getInstance();
		$router->module = 'users';
		$router->action = 'default';
		$router->vars = [];
		$router->page = $page;
		$router->order = null;

		$pagination = new Pagination();
		$pagination->page = $page;
		$pagination->perPage = $perPage;
		$pagination->count = $count;

		return $pagination;

	}

}
