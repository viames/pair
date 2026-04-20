## Upgrade to Pair v4

### Goal

Pair v4 moves application code away from implicit `ActiveRecord` payloads and hidden MVC state toward:

- explicit read models
- immutable request input
- explicit page and JSON responses

The API documentation path follows the same rule: CRUD OpenAPI response schemas now derive from `readModel` when configured.

### Upgrade Tool

Run the upgrader from the application root:

```sh
php vendor/viames/pair/scripts/upgrade-to-v4.php --dry-run
php vendor/viames/pair/scripts/upgrade-to-v4.php --write
```

Inside this repository you can also run:

```sh
composer run upgrade-to-v4 -- --dry-run
composer run upgrade-to-v4 -- --write
```

### What the Upgrader Rewrites Automatically

- controller imports from `Pair\Core\Controller` to `Pair\Web\Controller` only when the controller already returns an explicit `PageResponse`, `JsonResponse`, or `ResponseInterface`
- legacy `_init()` hooks to `boot()` in controllers already migrated to the new response-oriented base
- legacy controller `lang()` calls to an explicit `translate()` helper when the controller is already safe to switch to `Pair\Web\Controller`
- `ApiExposable::apiConfig()` blocks that still lack both `readModel` and `resource`
- common `ApiResponse::respond($object->toArray())` and `Utilities::jsonResponse($object->toArray())` patterns by wrapping them through `Pair\Data\Payload`
- readonly `*PageState` skeleton classes inside `modules/*/classes/` for legacy `View` files that assign layout variables through `assign()`

### What the Upgrader Reports but Does Not Rewrite Blindly

- controllers that still depend on implicit MVC state such as `setView()`, `$this->model`, `$this->view`, `loadModel()`, or `getObjectRequestedById()`
- legacy `View` classes, especially when they still own `pageTitle()`, `Breadcrumb::path()`, or active-menu mutations
- `setView()` and `assign()`/`assignState()` calls
- `ActiveRecord::html()` usage
- `reload()` flows

These cases need manual migration because they depend on application-specific state and layout intent.
This rule was validated against the current `pair_boilerplate` baseline: legacy controllers are now reported, not silently rewritten to an incompatible base class.
The same boilerplate validation now generates concrete page-state skeletons for the legacy views, so the manual work can start from explicit code instead of from an empty file.
The framework now also emits deprecation notices in non-production environments when a module still extends `Pair\Core\Controller` or `Pair\Core\View`, so the remaining runtime legacy path stays visible during the migration.

### Target Pair v4 Shape

#### Web controller

```php
use Pair\Web\Controller;

final class UserController extends Controller {

	protected function boot(): void {}

	public function defaultAction(): \Pair\Web\PageResponse {

		$user = new User(7);
		$state = UserPageState::fromRecord($user);

		return $this->page('default', $state, 'User');

	}

}
```

#### Read model

```php
use Pair\Data\ArraySerializableData;
use Pair\Data\MapsFromRecord;
use Pair\Data\ReadModel;

final readonly class UserPageState implements ReadModel, MapsFromRecord {

	use ArraySerializableData;

	public function __construct(
		public int $id,
		public string $name
	) {}

	public static function fromRecord(\Pair\Orm\ActiveRecord $record): static {

		return new self(
			(int)$record->id,
			(string)$record->name
		);

	}

	public function toArray(): array {

		return [
			'id' => $this->id,
			'name' => $this->name,
		];

	}

}
```

#### API config

```php
use Pair\Api\ApiExposable;

final class User extends \Pair\Orm\ActiveRecord {

	use ApiExposable;

	public static function apiConfig(): array {

		return [
			'readModel' => UserReadModel::class,
			'includes' => ['group'],
			'includeReadModels' => ['group' => GroupReadModel::class],
		];

	}

}
```

### Recommended Manual Migration Order

1. Run the upgrader in `--dry-run`.
2. Fix every warning related to legacy controllers, `View`, `setView()`, `assign()`, `html()`, and `reload()`.
3. Replace `Pair\Data\Payload` bridges with app-specific readonly read models.
4. Refine the generated `*PageState` skeleton classes by replacing `mixed` with concrete application types.
5. Update layouts to read from the typed `$state` object.
6. Update API resources from legacy `resource` adapters to `readModel` classes.

### Validation

After the migration:

- run the application test suite
- hit one HTML route using the new `PageResponse` path
- hit one JSON route using the new `readModel` path
- run `scripts/benchmark-v4.php` to compare common-path costs
