<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Web;

use Pair\Core\Application;
use Pair\Core\Logger;
use Pair\Exceptions\CriticalException;
use Pair\Tests\Support\FakePageState;
use Pair\Tests\Support\TestCase;
use Pair\Web\FragmentResponse;

/**
 * Covers explicit fragment rendering for progressive Pair UI regions.
 */
class FragmentResponseTest extends TestCase {

	/**
	 * Reset application and logger singletons so fragment-response tests stay isolated.
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
	 * Verify the response exposes only the typed state object to the fragment template.
	 */
	public function testSendRendersTheTypedStateIntoTheFragment(): void {

		$templateFile = TEMP_PATH . 'fragment-response-test.php';
		file_put_contents($templateFile, '<?php print htmlspecialchars($state->message, ENT_QUOTES, "UTF-8"); ?>');

		$response = new FragmentResponse($templateFile, new FakePageState('Hello fragment'), 'orders-list');

		$output = '';
		$bufferLevel = ob_get_level();
		ob_start();

		try {
			$response->send();
			$output = ob_get_clean();
		} finally {
			if (ob_get_level() > $bufferLevel) {
				ob_end_clean();
			}

			if (is_file($templateFile)) {
				unlink($templateFile);
			}
		}

		$this->assertSame('Hello fragment', $output);

	}

	/**
	 * Verify missing templates raise a CriticalException when the application is running headless.
	 */
	public function testSendRejectsMissingTemplateFile(): void {

		$app = $this->newApplicationStub();
		$this->setPrivateProperty($app, Application::class, 'headless', true);
		$this->setApplicationSingleton($app);

		$this->expectException(CriticalException::class);

		$response = new FragmentResponse(TEMP_PATH . 'missing-fragment-response-template.php', new FakePageState('Missing fragment'), 'orders-list');
		$response->send();

	}

	/**
	 * Verify fragment responses cannot be created without a usable region name.
	 */
	public function testConstructorRejectsEmptyRegion(): void {

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Fragment region name must not be empty.');

		new FragmentResponse(TEMP_PATH . 'fragment-response-test.php', new FakePageState('Missing region'), "\n");

	}

	/**
	 * Verify constructor normalization stores the same safe region used by response headers.
	 */
	public function testConstructorNormalizesRegionName(): void {

		$response = new FragmentResponse(TEMP_PATH . 'fragment-response-test.php', new FakePageState('Region'), " orders-list\n");

		$this->assertSame('orders-list', $this->readInaccessibleProperty($response, 'region'));

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
	 * Read one private property from an object under test.
	 *
	 * @param	object	$object	Object under test.
	 * @param	string	$name	Property name.
	 */
	private function readInaccessibleProperty(object $object, string $name): mixed {

		$reflection = new \ReflectionProperty($object, $name);

		return $reflection->getValue($object);

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

}
