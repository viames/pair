<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Console;

use Pair\Console\MakeCommand;
use Pair\Tests\Support\TestCase;

/**
 * Covers the lightweight Pair generator command.
 */
class MakeCommandTest extends TestCase {

	/**
	 * Remove generated fixture files after each test.
	 */
	protected function tearDown(): void {

		$this->removeDirectory($this->fixturePath());

		parent::tearDown();

	}

	/**
	 * Verify make:module creates a Pair v4 web module with typed state and mostly-HTML layout.
	 */
	public function testMakeModuleCreatesExplicitWebModule(): void {

		$result = $this->runMakeCommand(['make:module', 'orders', '--path=' . $this->fixturePath(), '--with-js', '--with-test']);

		$this->assertSame(0, $result['exitCode']);
		$this->assertStringContainsString('Created files:', $result['stdout']);

		$controllerPath = $this->fixturePath() . '/modules/orders/controller.php';
		$pageStatePath = $this->fixturePath() . '/modules/orders/classes/OrdersDefaultPageState.php';
		$layoutPath = $this->fixturePath() . '/modules/orders/layouts/default.php';
		$javascriptPath = $this->fixturePath() . '/modules/orders/assets/orders.js';
		$testPath = $this->fixturePath() . '/tests/Unit/Modules/OrdersModuleTest.php';

		$this->assertFileExists($controllerPath);
		$this->assertFileExists($pageStatePath);
		$this->assertFileExists($layoutPath);
		$this->assertFileExists($javascriptPath);
		$this->assertFileExists($testPath);

		$this->assertStringContainsString('final class OrdersController extends Controller', (string)file_get_contents($controllerPath));
		$this->assertStringContainsString('public function defaultAction(): PageResponse', (string)file_get_contents($controllerPath));
		$this->assertStringContainsString("return \$this->page('default', new OrdersDefaultPageState('Orders'), 'Orders');", (string)file_get_contents($controllerPath));
		$this->assertStringContainsString('final readonly class OrdersDefaultPageState implements ReadModel', (string)file_get_contents($pageStatePath));
		$this->assertStringStartsWith('<section class="pair-page">', (string)file_get_contents($layoutPath));
		$this->assertStringNotContainsString('declare(strict_types=1)', (string)file_get_contents($layoutPath));
		$this->assertStringContainsString('function initOrdersModule()', (string)file_get_contents($javascriptPath));

	}

	/**
	 * Verify make:api creates a minimal API controller with an explicit response action.
	 */
	public function testMakeApiCreatesExplicitApiController(): void {

		$result = $this->runMakeCommand(['make:api', 'api', '--path=' . $this->fixturePath(), '--with-test']);

		$this->assertSame(0, $result['exitCode']);

		$controllerPath = $this->fixturePath() . '/modules/api/controller.php';
		$testPath = $this->fixturePath() . '/tests/Unit/Modules/ApiApiModuleTest.php';

		$this->assertFileExists($controllerPath);
		$this->assertFileExists($testPath);
		$this->assertStringContainsString('final class ApiController extends CrudController', (string)file_get_contents($controllerPath));
		$this->assertStringContainsString('public function healthAction(): ResponseInterface', (string)file_get_contents($controllerPath));
		$this->assertStringContainsString("return ApiResponse::jsonResponse(['ok' => true]);", (string)file_get_contents($controllerPath));

	}

	/**
	 * Verify make:crud creates a model and read model from table and field metadata.
	 */
	public function testMakeCrudCreatesModelAndReadModel(): void {

		$result = $this->runMakeCommand([
			'make:crud',
			'order',
			'--path=' . $this->fixturePath(),
			'--table=orders',
			'--fields=id,customer_id,total_amount',
			'--with-test',
		]);

		$this->assertSame(0, $result['exitCode']);

		$modelPath = $this->fixturePath() . '/models/Order.php';
		$readModelPath = $this->fixturePath() . '/classes/OrderReadModel.php';
		$testPath = $this->fixturePath() . '/tests/Unit/Models/OrderCrudTest.php';

		$this->assertFileExists($modelPath);
		$this->assertFileExists($readModelPath);
		$this->assertFileExists($testPath);

		$model = (string)file_get_contents($modelPath);
		$readModel = (string)file_get_contents($readModelPath);

		$this->assertStringContainsString("public const TABLE_NAME = 'orders';", $model);
		$this->assertStringContainsString('public mixed $customerId = null;', $model);
		$this->assertStringContainsString("'customerId' => 'customer_id',", $model);
		$this->assertStringContainsString("'readModel' => OrderReadModel::class,", $model);
		$this->assertStringContainsString('final readonly class OrderReadModel implements ReadModel, MapsFromRecord', $readModel);
		$this->assertStringContainsString('public mixed $totalAmount', $readModel);
		$this->assertStringContainsString("'totalAmount' => \$this->totalAmount,", $readModel);

	}

	/**
	 * Verify existing user-edited files are not overwritten unless --force is provided.
	 */
	public function testMakeModuleBlocksExistingDifferentFilesWithoutForce(): void {

		$first = $this->runMakeCommand(['make:module', 'orders', '--path=' . $this->fixturePath()]);
		$this->assertSame(0, $first['exitCode']);

		$controllerPath = $this->fixturePath() . '/modules/orders/controller.php';
		file_put_contents($controllerPath, "<?php\n// user edit\n");

		$second = $this->runMakeCommand(['make:module', 'orders', '--path=' . $this->fixturePath()]);

		$this->assertSame(1, $second['exitCode']);
		$this->assertStringContainsString('Blocked existing files:', $second['stdout']);
		$this->assertSame("<?php\n// user edit\n", file_get_contents($controllerPath));

	}

	/**
	 * Return the isolated fixture path for generated files.
	 */
	private function fixturePath(): string {

		return TEMP_PATH . 'pair-make-fixture';

	}

	/**
	 * Execute the generator and capture stdout/stderr for assertions.
	 *
	 * @param	string[]	$arguments	Command arguments after the executable name.
	 * @return	array{stdout: string, stderr: string, exitCode: int}
	 */
	private function runMakeCommand(array $arguments): array {

		$command = new MakeCommand();

		ob_start();
		$exitCode = $command->run(array_merge(['pair'], $arguments), dirname(__DIR__, 3));
		$stdout = ob_get_clean();

		return [
			'stdout' => is_string($stdout) ? $stdout : '',
			'stderr' => '',
			'exitCode' => $exitCode,
		];

	}

}
