# Pair Framework — Implementation patterns

Read this after locating the owning component. [AGENTS.md](AGENTS.md) owns
conventions; [SKILL.md](SKILL.md) provides the source map. Examples below use
Pair v4 APIs; application-specific classes must be defined or autoloaded by the app.

## Controller and page response

New web modules extend `Pair\Web\Controller`. Keep orchestration in the action
and shared business logic in the existing model, service or utility that owns it.

For a self-contained read-only example, place these files in an application's
`modules/welcome/` directory.

`classes/WelcomePageState.php`:

```php
<?php

/**
 * Data rendered by the welcome page.
 */
final readonly class WelcomePageState {

	/**
	 * Create the page state.
	 */
	public function __construct(public string $message) {}

}
```

`controller.php`:

```php
<?php

use Pair\Web\Controller;
use Pair\Web\PageResponse;

require_once __DIR__ . '/classes/WelcomePageState.php';

/**
 * Render the welcome module.
 */
final class WelcomeController extends Controller {

	/**
	 * Return the default page with explicit state.
	 */
	public function defaultAction(): PageResponse {

		return $this->page('default', new WelcomePageState('Hello Pair'), 'Welcome');

	}

}
```

`layouts/default.php`:

```php
<main>
	<h1><?= htmlspecialchars($state->message, ENT_QUOTES, 'UTF-8') ?></h1>
</main>
```

`page('default', ...)` resolves the layout relative to the controller's directory.
The layout receives `$state`; it should render escaped output, simple conditions
and loops. Editor hints and docblocks do not establish its runtime contract.

Use `boot()` for explicit controller initialization. `_init()`, implicit model/view
loading, `$this->set(...)` and `View::assign(...)` belong to the legacy MVC bridge.
Preserve that flow when fixing an existing legacy module; use the
[upgrade guide](UPGRADE_V4.md) when migrating it.

For partial updates, inspect `pageOrFragment()` or `pageOrFragments()` in
[src/Web/Controller.php](src/Web/Controller.php). They retain full-page responses
unless the request explicitly selects a matching region.

## Persistence and relations

Models extend `Pair\Orm\ActiveRecord` and declare their table, keys, properties
and bindings. Use [Group](src/Models/Group.php) as a concrete mapping example;
an empty subclass is not a complete table mapping.

Existing APIs include:

```php
use Pair\Models\User;

$users = User::getAllObjects();
$user = new User($id);

if ($user->isLoaded()) {
	$group = $user->getGroup();
}
```

Here `$id` is an already validated identifier. Query/filter/pagination choices
belong to the application; avoid loading every row for an unbounded list.
For writes, use `create()`, `update()` or `store()` as appropriate and follow the
owning model's error handling. These are the persistence methods; `save()` is not
an `ActiveRecord` method.

`User::getGroup()` and `Group::getUsers()` are explicit model methods. Dynamic
helpers can also resolve relationships through `ActiveRecord::__call()` when
foreign-key metadata and mapped classes exist. A `group_id` column by itself does
not establish that contract. Inspect `getParent()` and `getRelateds()` before
adding a custom lookup or join, and check query cost when traversing many records.

## Input and form handling

In explicit web controllers, `$this->input()` returns immutable `Pair\Http\Input`:

```php
$name = $this->input()->string('name', '');
$page = $this->input()->int('page', 1);
```

Typed accessors read and cast input; they do not establish authorization,
business validation or CSRF protection. Before a browser form writes data, use
the existing access checks, validate the submitted token and fields, and handle
persistence failures before redirecting or returning a response.

[Form](src/Html/Form.php) provides `generateToken()`, `checkToken()` and
`validateToken()`; see the [form reference](https://github.com/viames/pair/wiki/Form)
for rendering and validation. For custom API endpoints, inspect
`Pair\Api\Request::validateObjectOrResponse()` and `Pair\Api\RequestData`.
Keep request validation and public read-model serialization separate from persistence.

## Progressive frontend behavior

Keep usable server-rendered HTML and add PairUI or small vanilla JavaScript
where needed. A minimal binding, after loading `PairUI.js`:

```html
<div id="welcome">
	<span data-text="message">Hello Pair</span>
</div>
<script>
	const store = PairUI.createStore({ message: 'Hello Pair' });
	PairUI.mount(document.getElementById('welcome'), store);
</script>
```

The initial text remains available before enhancement. Use `data-text` for plain
text; inspect the [PairUI reference](https://github.com/viames/pair/wiki/PairUI.js)
and [source](assets/PairUI.js) for directives, region updates and lifecycle handling.

## Atomic callbacks and nested transactions

`Database::transaction(callable $callback): mixed` owns a PDO transaction when none exists, otherwise uses a unique savepoint. The result is returned after success; failures roll back only this scope unless MySQL already aborted the whole transaction (for example a deadlock). The callback must not issue DDL or commit/rollback the surrounding transaction. There is no automatic retry. Record objects may require reload after rollback.

Models whose delete hooks perform multiple writes can override `protected function usesAtomicDelete(): bool` to return `true`; the existing `delete()` lifecycle then runs inside this callback, including hooks and the final DELETE. Default behavior remains unchanged. External side effects must be queued transactionally, not sent inside the callback.
