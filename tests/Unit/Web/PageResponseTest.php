<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Web;

use Pair\Core\Application;
use Pair\Core\Logger;
use Pair\Exceptions\CriticalException;
use Pair\Tests\Support\FakePageState;
use Pair\Tests\Support\TestCase;
use Pair\Web\PageResponse;

/**
 * Covers explicit page rendering through the v4 response object.
 */
class PageResponseTest extends TestCase {

	/**
	 * Reset application and logger singletons so page-response tests can control them explicitly.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->resetApplicationSingleton();
		$this->resetLoggerSingleton();
		$_ENV['PAIR_LOGGER_DISABLED'] = true;

	}

	/**
	 * Restore application and logger singletons after each test.
	 */
	protected function tearDown(): void {

		$this->resetApplicationSingleton();
		$this->resetLoggerSingleton();

		parent::tearDown();

	}

	/**
	 * Verify the response exposes only the typed state object to the template.
	 */
	public function testSendRendersTheTypedStateIntoTheTemplate(): void {

		$templateFile = TEMP_PATH . 'page-response-test.php';
		file_put_contents($templateFile, '<?php print htmlspecialchars($state->message, ENT_QUOTES, "UTF-8"); ?>');

		$response = new PageResponse($templateFile, new FakePageState('Hello Pair v4'));

		ob_start();
		$response->send();
		$output = ob_get_clean();

		unlink($templateFile);

		$this->assertSame('Hello Pair v4', $output);

	}

	/**
	 * Verify the optional title is forwarded to the application singleton before rendering.
	 */
	public function testSendPropagatesPageTitleToApplicationSingleton(): void {

		$templateFile = TEMP_PATH . 'page-response-title-test.php';
		file_put_contents($templateFile, '<?php print htmlspecialchars($state->message, ENT_QUOTES, "UTF-8"); ?>');

		$app = $this->newApplicationStub();
		$this->setPrivateProperty($app, Application::class, 'headless', false);
		$this->setApplicationSingleton($app);

		$response = new PageResponse($templateFile, new FakePageState('Titled page'), 'Audit page');

		ob_start();
		$response->send();
		ob_end_clean();

		unlink($templateFile);

		$this->assertSame('Audit page', $this->readPrivateProperty($app, Application::class, 'pageTitle'));

	}

	/**
	 * Verify missing templates raise a CriticalException when the application is running headless.
	 */
	public function testSendRejectsMissingTemplateFile(): void {

		$app = $this->newApplicationStub();
		$this->setPrivateProperty($app, Application::class, 'headless', true);
		$this->setApplicationSingleton($app);

		$this->expectException(CriticalException::class);

		$response = new PageResponse(TEMP_PATH . 'missing-page-response-template.php', new FakePageState('Missing template'));
		$response->send();

	}

	/**
	 * Create a lightweight Application instance without invoking the framework bootstrap.
	 */
	private function newApplicationStub(): Application {

		$reflection = new \ReflectionClass(Application::class);

		return $reflection->newInstanceWithoutConstructor();

	}

	/**
	 * Publish a controlled Application singleton instance for the current test.
	 *
	 * @param	Application	$app	Application instance to expose through Application::getInstance().
	 */
	private function setApplicationSingleton(Application $app): void {

		$reflection = new \ReflectionProperty(Application::class, 'instance');
		$reflection->setValue(null, $app);

	}

	/**
	 * Reset the Application singleton to avoid leaking state between tests.
	 */
	private function resetApplicationSingleton(): void {

		$reflection = new \ReflectionProperty(Application::class, 'instance');
		$reflection->setValue(null, null);

	}

	/**
	 * Reset the Logger singleton so CriticalException tests can control logger startup.
	 */
	private function resetLoggerSingleton(): void {

		$reflection = new \ReflectionProperty(Logger::class, 'instance');
		$reflection->setValue(null, null);

	}

	/**
	 * Assign a private property value through reflection for focused response tests.
	 *
	 * @param	object	$object	Object under test.
	 * @param	string	$class	Declaring class of the property.
	 * @param	string	$name	Property name.
	 * @param	mixed	$value	Value to assign.
	 */
	private function setPrivateProperty(object $object, string $class, string $name, mixed $value): void {

		$reflection = new \ReflectionProperty($class, $name);
		$reflection->setValue($object, $value);

	}

	/**
	 * Read a private property value through reflection for focused response assertions.
	 *
	 * @param	object	$object	Object under test.
	 * @param	string	$class	Declaring class of the property.
	 * @param	string	$name	Property name.
	 */
	private function readPrivateProperty(object $object, string $class, string $name): mixed {

		$reflection = new \ReflectionProperty($class, $name);

		return $reflection->getValue($object);

	}

}
