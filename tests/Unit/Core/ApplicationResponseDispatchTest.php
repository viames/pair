<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Core\Router;
use Pair\Models\Template;
use Pair\Tests\Support\TestCase;

/**
 * Covers the explicit response dispatch path handled by the Pair application runtime.
 */
class ApplicationResponseDispatchTest extends TestCase {

	/**
	 * Output-buffer level observed before each test starts.
	 */
	private int $outputBufferLevel = 0;

	/**
	 * Prepare a minimal application tree for the runtime-dispatch scenarios.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->outputBufferLevel = ob_get_level();
		$this->resetApplicationSingleton();
		$this->prepareFixtureApplication();
		$this->defineRuntimeConstants();
		$this->writeEnvFile(implode("\n", [
			'APP_NAME="Pair Test Application"',
			'APP_ENV=development',
			'DB_UTF8=false',
		]));
		Env::load();

	}

	/**
	 * Remove the runtime fixtures and restore the application singleton after each test.
	 */
	protected function tearDown(): void {

		while (ob_get_level() > $this->outputBufferLevel) {
			ob_end_clean();
		}

		$this->resetApplicationSingleton();
		$this->removeDirectory(APPLICATION_PATH . '/modules');
		$this->removeDirectory(APPLICATION_PATH . '/templates');

		parent::tearDown();

	}

	/**
	 * Verify page responses keep flowing through the template wrapper.
	 */
	public function testPageResponseIsWrappedByTemplate(): void {

		$app = $this->bootApplicationForRoute('page', 'default');

		ob_start();
		ob_start();
		$app->run();
		$output = ob_get_clean();

		$this->assertIsString($output);
		$this->assertStringContainsString('<html>', $output);
		$this->assertStringContainsString('<title>Demo page</title>', $output);
		$this->assertStringContainsString('<div class="shell"><main data-page="demo">Hello Pair v4</main></div>', $output);

	}

	/**
	 * Verify non-page responses can own the full body without being wrapped by the template.
	 */
	public function testNonPageResponseBypassesTemplateWrapping(): void {

		$app = $this->bootApplicationForRoute('raw', 'default');

		ob_start();
		ob_start();
		$app->run();
		$output = ob_get_clean();

		$this->assertSame('{"status":"ok"}', $output);

	}

	/**
	 * Verify Pair v4 action failures stop before the legacy view fallback.
	 */
	public function testFailure(): void {

		$app = $this->bootApplicationForRoute('failing', 'default');

		ob_start();
		ob_start();
		$app->run();
		$output = ob_get_clean();

		$this->assertIsString($output);
		$this->assertStringContainsString('<div class="shell"></div>', $output);
		$this->assertContains('Pair v4 failure', $app->getAllNotificationsMessages());

	}

	/**
	 * Boot the application singleton for one controller action and inject a fake template record.
	 */
	private function bootApplicationForRoute(string $module, string $action): Application {

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/' . $module . '/' . $action;
		$_SERVER['SCRIPT_NAME'] = '/public/index.php';
		$_SERVER['HTTP_HOST'] = 'pair.test';

		$app = Application::getInstance();
		$router = Router::getInstance();

		// Override the CLI-parsed router state with the web module under test.
		$router->module = $module;
		$router->action = $action;
		$router->vars = [];
		$router->ajax = false;
		$router->raw = false;

		$this->injectDefaultTemplate($app);

		return $app;

	}

	/**
	 * Build the minimal module and template files used by the runtime-dispatch tests.
	 */
	private function prepareFixtureApplication(): void {

		$pageModuleDirectory = APPLICATION_PATH . '/modules/page/layouts';
		$rawModuleDirectory = APPLICATION_PATH . '/modules/raw';
		$failingModuleDirectory = APPLICATION_PATH . '/modules/failing';
		$templateDirectory = APPLICATION_PATH . '/templates/default';

		if (!is_dir($pageModuleDirectory)) {
			mkdir($pageModuleDirectory, 0777, true);
		}

		if (!is_dir($rawModuleDirectory)) {
			mkdir($rawModuleDirectory, 0777, true);
		}

		if (!is_dir($failingModuleDirectory)) {
			mkdir($failingModuleDirectory, 0777, true);
		}

		if (!is_dir($templateDirectory)) {
			mkdir($templateDirectory, 0777, true);
		}

		file_put_contents(
			APPLICATION_PATH . '/modules/page/controller.php',
			<<<'PHP'
<?php

use Pair\Web\Controller;
use Pair\Web\PageResponse;

/**
 * Page module used to verify template-wrapped explicit responses.
 */
final class PageController extends Controller {

	/**
	 * Render a typed page response through the explicit Pair v4 path.
	 */
	public function defaultAction(): PageResponse {

		return $this->page('default', new PagePageState('Hello Pair v4'), 'Demo page');

	}

}

/**
 * Typed state object used by the demo page response.
 */
final readonly class PagePageState {

	/**
	 * Build the page state with the message shown by the layout.
	 */
	public function __construct(public string $message) {}

}

PHP
		);

		file_put_contents(
			APPLICATION_PATH . '/modules/page/layouts/default.php',
			'<?php print \'<main data-page="demo">\' . htmlspecialchars($state->message, ENT_QUOTES, "UTF-8") . \'</main>\';'
		);

		file_put_contents(
			APPLICATION_PATH . '/modules/raw/controller.php',
			<<<'PHP'
<?php

use Pair\Http\ResponseInterface;
use Pair\Web\Controller;

/**
 * Raw module used to verify bypass for non-page explicit responses.
 */
final class RawController extends Controller {

	/**
	 * Return a non-page response that already owns the complete response body.
	 */
	public function defaultAction(): ResponseInterface {

		return new RawDefaultResponse('{"status":"ok"}');

	}

}

/**
 * Minimal raw response used to verify template bypass for explicit responses.
 */
final class RawDefaultResponse implements ResponseInterface {

	/**
	 * Build the raw response with the complete body payload.
	 */
	public function __construct(private string $body) {}

	/**
	 * Print the raw response body without any extra framework wrapping.
	 */
	public function send(): void {

		print $this->body;

	}

}
PHP
		);

		file_put_contents(
			APPLICATION_PATH . '/modules/failing/controller.php',
			<<<'PHP'
<?php

use Pair\Exceptions\PairException;
use Pair\Web\Controller;

/**
 * Failing module used to verify Pair v4 exception dispatch.
 */
final class FailingController extends Controller {

	/**
	 * Throw a framework exception before an explicit response is built.
	 */
	public function defaultAction(): void {

		throw new PairException('Pair v4 failure');

	}

}
PHP
		);

		file_put_contents(
			APPLICATION_PATH . '/templates/default/default.php',
			'<!doctype html><html><head><title>{{ title }}</title></head><body><div class="shell">{{ content }}</div></body></html>'
		);

	}

	/**
	 * Define the minimal runtime constants normally prepared by the production bootstrap.
	 */
	private function defineRuntimeConstants(): void {

		if (!defined('PAIR_FOLDER')) {
			define('PAIR_FOLDER', 'pair');
		}

		if (!defined('BASE_TIMEZONE')) {
			define('BASE_TIMEZONE', date_default_timezone_get() ?: 'UTC');
		}

		if (!defined('URL_PATH')) {
			define('URL_PATH', null);
		}

		if (!defined('BASE_HREF')) {
			define('BASE_HREF', null);
		}

	}

	/**
	 * Inject a minimal default template record so the runtime avoids any database lookup.
	 */
	private function injectDefaultTemplate(Application $app): void {

		$templateReflection = new \ReflectionClass(Template::class);
		$template = $templateReflection->newInstanceWithoutConstructor();

		$this->setInaccessibleProperty($template, 'keyProperties', ['id']);
		$this->setInaccessibleProperty($template, 'id', 1);
		$this->setInaccessibleProperty($template, 'name', 'default');
		$this->setInaccessibleProperty($app, 'template', $template);

	}

	/**
	 * Reset the application singleton so each test gets a clean output buffer and runtime state.
	 */
	private function resetApplicationSingleton(): void {

		$reflection = new \ReflectionProperty(Application::class, 'instance');
		$reflection->setValue(null, null);

	}

}
