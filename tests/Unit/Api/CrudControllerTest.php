<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Api;

use Pair\Tests\Support\FakeCrudController;
use Pair\Tests\Support\FakeCrudExposeableModel;
use Pair\Tests\Support\FakeCrudIncludeReadModel;
use Pair\Tests\Support\FakeCrudReadModel;
use Pair\Tests\Support\FakeCrudRecord;
use Pair\Tests\Support\FakeCrudResource;
use Pair\Tests\Support\TestCase;
use Pair\Orm\Collection;

/**
 * Covers the parts of CrudController that can be exercised without bootstrapping the full MVC stack.
 */
class CrudControllerTest extends TestCase {

	/**
	 * Verify resource registration pulls the merged ApiExposable configuration from the model class.
	 */
	public function testRegisterCrudResourceUsesMergedApiConfigFromModel(): void {

		$controller = $this->newCrudController();
		$controller->registerCrudResource('users', FakeCrudExposeableModel::class);

		$config = $controller->getResourceConfig('users');

		$this->assertSame(['users'], $controller->getRegisteredResources());
		$this->assertSame(FakeCrudExposeableModel::class, $config['class']);
		$this->assertSame(FakeCrudReadModel::class, $config['config']['readModel']);
		$this->assertSame(['name'], $config['config']['searchable']);
		$this->assertSame(['createdAt'], $config['config']['sortable']);
		$this->assertSame(['status'], $config['config']['filterable']);
		$this->assertSame(['group', 'tags'], $config['config']['includes']);
		$this->assertSame(FakeCrudIncludeReadModel::class, $config['config']['includeReadModels']['group']);
		$this->assertSame(FakeCrudIncludeReadModel::class, $config['config']['includeReadModels']['tags']);
		$this->assertSame(15, $config['config']['perPage']);
		$this->assertSame(30, $config['config']['maxPerPage']);
		$this->assertSame('-createdAt', $config['config']['defaultSort']);
		$this->assertSame(['name' => 'required|string'], $config['config']['rules']['create']);
		$this->assertSame(['name' => 'string'], $config['config']['rules']['update']);

	}

	/**
	 * Verify transformResource uses the configured read model, sparse fields, and singular includes.
	 */
	public function testTransformResourceUsesReadModelFieldsAndIncludes(): void {

		$controller = $this->newCrudController();
		$group = $this->newCrudRecord()->seed(['id' => 10, 'name' => 'Admins']);
		$record = $this->newCrudRecord()->seed([
			'id' => 7,
			'name' => 'Alice',
			'email' => 'alice@example.test',
		], [
			'group' => $group,
		]);

		$data = $this->invokeInaccessibleMethod($controller, 'transformResource', [
			$record,
			[
				'readModel' => FakeCrudReadModel::class,
				'includeReadModels' => ['group' => FakeCrudIncludeReadModel::class],
			],
			['identifier'],
			['group'],
		]);

		$this->assertSame([
			'identifier' => 7,
			'group' => [
				'id' => 10,
				'name' => 'Admins',
			],
		], $data);

	}

	/**
	 * Verify transformCollection applies the configured read model to every item in the array.
	 */
	public function testTransformCollectionUsesReadModelForEveryItem(): void {

		$controller = $this->newCrudController();
		$records = [
			$this->newCrudRecord()->seed(['id' => 1, 'name' => 'Alice', 'email' => 'a@example.test']),
			$this->newCrudRecord()->seed(['id' => 2, 'name' => 'Bob', 'email' => 'b@example.test']),
		];

		$data = $this->invokeInaccessibleMethod($controller, 'transformCollection', [
			$records,
			['readModel' => FakeCrudReadModel::class],
			['identifier', 'label'],
			[],
		]);

		$this->assertSame([
			['identifier' => 1, 'label' => 'ALICE'],
			['identifier' => 2, 'label' => 'BOB'],
		], $data);

	}

	/**
	 * Verify collection includes are serialized as nested arrays on the output payload.
	 */
	public function testLoadIncludesSerializesCollectionRelations(): void {

		$controller = $this->newCrudController();
		$tags = new Collection([
			$this->newCrudRecord()->seed(['id' => 20, 'name' => 'One']),
			$this->newCrudRecord()->seed(['id' => 21, 'name' => 'Two']),
		]);
		$record = $this->newCrudRecord()->seed([
			'id' => 7,
			'name' => 'Alice',
		], [
			'tags' => $tags,
		]);

		$data = $this->invokeInaccessibleMethod($controller, 'loadIncludes', [
			$record,
			['id' => 7],
			[
				'includes' => ['tags'],
				'includeReadModels' => ['tags' => FakeCrudIncludeReadModel::class],
			],
			['tags'],
		]);

		$this->assertSame([
			'id' => 7,
			'tags' => [
				20 => ['id' => 20, 'name' => 'One'],
				21 => ['id' => 21, 'name' => 'Two'],
			],
		], $data);

	}

	/**
	 * Verify legacy Resource adapters still work as an explicit migration bridge.
	 */
	public function testTransformResourceStillSupportsLegacyResourceAdapters(): void {

		$controller = $this->newCrudController();
		$record = $this->newCrudRecord()->seed([
			'id' => 7,
			'name' => 'Alice',
			'email' => 'alice@example.test',
		]);

		$data = $this->invokeInaccessibleMethod($controller, 'transformResource', [
			$record,
			['resource' => FakeCrudResource::class],
		]);

		$this->assertSame([
			'identifier' => 7,
			'label' => 'ALICE',
			'email' => 'alice@example.test',
		], $data);

	}

	/**
	 * Verify missing explicit transformers no longer fall back to ActiveRecord::toArray().
	 */
	public function testTransformResourceRejectsImplicitActiveRecordSerialization(): void {

		$controller = $this->newCrudController();
		$record = $this->newCrudRecord()->seed([
			'id' => 7,
			'name' => 'Alice',
		]);

		$this->expectException(\LogicException::class);

		$this->invokeInaccessibleMethod($controller, 'transformResource', [
			$record,
			[],
		]);

	}

	/**
	 * Create a CrudController instance without invoking the MVC constructor.
	 */
	private function newCrudController(): FakeCrudController {

		$reflection = new \ReflectionClass(FakeCrudController::class);

		return $reflection->newInstanceWithoutConstructor();

	}

	/**
	 * Create a fake ActiveRecord instance without hitting the database constructor.
	 */
	private function newCrudRecord(): FakeCrudRecord {

		$reflection = new \ReflectionClass(FakeCrudRecord::class);

		return $reflection->newInstanceWithoutConstructor();

	}

}
