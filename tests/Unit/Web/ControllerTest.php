<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Web;

use Pair\Data\Payload;
use Pair\Data\ReadModel;
use Pair\Http\Input;
use Pair\Http\JsonResponse;
use Pair\Tests\Support\FakePageState;
use Pair\Tests\Support\TestCase;
use Pair\Web\Controller;
use Pair\Web\PageResponse;

/**
 * Covers the explicit Pair v4 controller base helpers.
 */
class ControllerTest extends TestCase {

	/**
	 * Ensure the router can be created safely in the isolated test runtime.
	 */
	protected function setUp(): void {

		parent::setUp();

		if (!defined('URL_PATH')) {
			define('URL_PATH', null);
		}

	}

	/**
	 * Verify the constructor triggers the explicit boot hook and exposes immutable input.
	 */
	public function testBootHookRunsAndInputReadsCurrentRequestData(): void {

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_GET = ['page' => '3'];
		$_POST = ['name' => 'Alice'];

		$controller = new TestWebController();
		$input = $controller->exposeInput();

		$this->assertTrue($controller->booted);
		$this->assertInstanceOf(Input::class, $input);
		$this->assertSame('POST', $input->method());
		$this->assertSame('3', $input->query('page'));
		$this->assertSame('Alice', $input->body('name'));

	}

	/**
	 * Verify modulePath resolves relative paths from the controller file directory.
	 */
	public function testModulePathBuildsPathsInsideControllerDirectory(): void {

		$controller = new TestWebController();
		$expectedBasePath = __DIR__;

		$this->assertSame($expectedBasePath, $controller->exposeModulePath());
		$this->assertSame($expectedBasePath . '/layouts/default.php', $controller->exposeModulePath('layouts/default.php'));
		$this->assertSame($expectedBasePath . '/layouts/default.php', $controller->exposeModulePath('/layouts/default.php'));

	}

	/**
	 * Verify page() creates a PageResponse bound to the controller layout path and typed state.
	 */
	public function testPageHelperBuildsPageResponse(): void {

		$controller = new TestWebController();
		$state = new FakePageState('Hello Pair v4');
		$response = $controller->exposePage('default', $state, 'Demo page');

		$this->assertInstanceOf(PageResponse::class, $response);
		$this->assertSame(__DIR__ . '/layouts/default.php', $this->readInaccessibleProperty($response, 'templateFile'));
		$this->assertSame($state, $this->readInaccessibleProperty($response, 'state'));
		$this->assertSame('Demo page', $this->readInaccessibleProperty($response, 'title'));

	}

	/**
	 * Verify json() creates a JsonResponse with the provided payload and HTTP status.
	 */
	public function testJsonHelperBuildsJsonResponse(): void {

		$controller = new TestWebController();
		$payload = Payload::fromArray([
			'id' => 7,
			'name' => 'Alice',
		]);
		$response = $controller->exposeJson($payload, 202);

		$this->assertInstanceOf(JsonResponse::class, $response);
		$this->assertSame($payload, $this->readInaccessibleProperty($response, 'payload'));
		$this->assertSame(202, $this->readInaccessibleProperty($response, 'httpCode'));

	}

	/**
	 * Read one private property from an object under test.
	 *
	 * @param	object	$object	Object under test.
	 * @param	string	$name	Property name.
	 */
	private function readInaccessibleProperty(object $object, string $name): mixed {

		$reflection = new \ReflectionProperty($object, $name);

		return $reflection->getValue($object);

	}

}

/**
 * Small explicit controller double used to exercise the Pair v4 base helper methods.
 */
final class TestWebController extends Controller {

	/**
	 * Track whether the explicit boot hook was executed.
	 */
	public bool $booted = false;

	/**
	 * Mark the controller as booted.
	 */
	protected function boot(): void {

		$this->booted = true;

	}

	/**
	 * Expose the protected input() helper for unit tests.
	 */
	public function exposeInput(): Input {

		return $this->input();

	}

	/**
	 * Expose the protected json() helper for unit tests.
	 */
	public function exposeJson(ReadModel|\stdClass|array|null $payload, int $httpCode = 200): JsonResponse {

		return $this->json($payload, $httpCode);

	}

	/**
	 * Expose the protected modulePath() helper for unit tests.
	 */
	public function exposeModulePath(?string $path = null): string {

		return $this->modulePath($path);

	}

	/**
	 * Expose the protected page() helper for unit tests.
	 */
	public function exposePage(string $layout, object $state, ?string $title = null): PageResponse {

		return $this->page($layout, $state, $title);

	}

}
