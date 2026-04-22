<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\AdapterKeys;
use Pair\Core\Application;
use Pair\Core\Observability;
use Pair\Core\ObservabilityAdapter;
use Pair\Core\ObservabilitySpan;
use Pair\Core\PluginInterface;
use Pair\Html\UiRenderers\NativeUiRenderer;
use Pair\Html\UiTheme;
use Pair\Tests\Support\TestCase;

/**
 * Covers explicit plugin registration through the application runtime.
 */
class ApplicationPluginTest extends TestCase {

	/**
	 * Verify plugins are registered manually and can publish adapters.
	 */
	public function testRegisterPluginCanPublishAdapters(): void {

		$app = $this->newApplicationStub();
		$adapter = new \ArrayObject(['driver' => 'fake']);

		$plugin = new class($adapter) implements PluginInterface {

			/**
			 * Adapter published by this fixture plugin.
			 */
			private \ArrayObject $adapter;

			/**
			 * Store the adapter fixture.
			 */
			public function __construct(\ArrayObject $adapter) {

				$this->adapter = $adapter;

			}

			/**
			 * Register the adapter explicitly into the application registry.
			 */
			public function register(Application $app): void {

				$app->setAdapter(AdapterKeys::PAYMENTS, $this->adapter);

			}

		};

		$this->assertSame($app, $app->registerPlugin($plugin));
		$this->assertTrue($app->hasAdapter(AdapterKeys::PAYMENTS));
		$this->assertSame($adapter, $app->adapter(AdapterKeys::PAYMENTS, \ArrayObject::class));

	}

	/**
	 * Verify plugins can register custom UI renderers without automatic discovery.
	 */
	public function testRegisterPluginCanPublishUiRenderer(): void {

		$app = $this->newApplicationStub();

		$plugin = new class implements PluginInterface {

			/**
			 * Register one custom renderer and select it for the current process.
			 */
			public function register(Application $app): void {

				UiTheme::registerRenderer(new class extends NativeUiRenderer {

					/**
					 * Return the fixture renderer name.
					 */
					public function name(): string {

						return 'fixture';

					}

					/**
					 * Return a visible fixture class for alert rendering.
					 */
					public function alertClass(string $variant = 'primary'): string {

						return 'fixture-alert';

					}

				});

				$app->uiFramework('fixture');

			}

		};

		$app->registerPlugin($plugin);

		$this->assertSame('fixture', UiTheme::current());
		$this->assertSame('fixture-alert', UiTheme::alertClass());

	}

	/**
	 * Verify the conventional observability adapter key also activates the runtime facade.
	 */
	public function testSetObservabilityAdapterActivatesFacade(): void {

		$app = $this->newApplicationStub();
		$adapter = new class implements ObservabilityAdapter {

			/**
			 * Captured spans.
			 *
			 * @var	list<ObservabilitySpan>
			 */
			public array $spans = [];

			/**
			 * Store the span for assertions.
			 */
			public function record(ObservabilitySpan $span): void {

				$this->spans[] = $span;

			}

		};

		$app->setAdapter(AdapterKeys::OBSERVABILITY, $adapter);
		Observability::trace('app.adapter', function (): void {});

		$this->assertTrue($app->hasAdapter(AdapterKeys::OBSERVABILITY));
		$this->assertCount(1, $adapter->spans);

	}

	/**
	 * Create a lightweight Application instance without invoking the framework bootstrap.
	 */
	private function newApplicationStub(): Application {

		$reflection = new \ReflectionClass(Application::class);

		return $reflection->newInstanceWithoutConstructor();

	}

}
