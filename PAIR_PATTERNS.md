# PAIR_PATTERNS.md — Pair Framework

This document describes **idiomatic coding patterns** for the Pair PHP framework.
Read it when you already know where to change code and need to match Pair’s implementation style.
Workflow, change-hygiene rules, and technical conventions stay in `AGENTS.md`.

---

## Fast Pattern Guide

Use these defaults unless nearby code shows a different established pattern:

- Pair v4 controllers return explicit response objects.
- Controllers stay thin and coordinate work.
- Models and ORM relations carry data access behavior.
- Views stay simple and contain minimal logic.
- Frontend behavior stays progressive and lightweight.
- Prefer existing framework helpers over custom abstractions.

---

# Controller pattern

New Pair v4 controllers extend:

`Pair\Web\Controller`

Responsibilities:

- receive the request
- orchestrate business logic
- build typed page state or API/read-model output
- return an explicit response

Controllers should remain **thin**.

Example:

```php
use Pair\Web\Controller;
use Pair\Web\PageResponse;

final class UserController extends Controller {

	/**
	 * Render the user list page.
	 */
	public function defaultAction(): PageResponse {

		$users = User::getAll();
		$state = new UserListPageState($users);

		return $this->page('default', $state, 'Users');

	}

}
```

`Pair\Core\Controller` remains available only as a legacy MVC migration bridge and should not be the default for new Pair v4 modules.

Avoid putting heavy logic inside controllers.

Business logic should live in:

- models
- services
- framework utilities

Use the controller to orchestrate, not to accumulate branching business logic.

---

# Model / ORM pattern

Pair uses **ActiveRecord**.

Models represent database tables.

Example:

```php
class User extends \Pair\Orm\ActiveRecord {
}
```

Typical usage:

```php
$user = User::getById($id);
$group = $user->getGroup();
```

Prefer ORM helpers instead of raw SQL queries.

---

# Relationship pattern

Foreign keys automatically expose helper methods.

Example:

Database column:

```text
group_id
```

Available method:

```php
$user->getGroup()
```

Reverse relation:

```php
$group->getUsers()
```

Agents should prefer these methods over manual joins.

If relation helpers already express the intent, using manual joins is usually the wrong tradeoff.

---

# Page response pattern

Pair v4 layouts are simple PHP templates backed by an explicit state object.

Naming convention:

```text
layouts/<name>.php
```

Example:

```text
layouts/default.php
```

Layouts should contain minimal logic and read from the typed `$state` object.

Legacy `view<Action>.php` classes remain part of the migration bridge for `Pair\Core\Controller` modules.

---

# Passing data to pages

Controllers pass data to pages by returning a page response:

```php
$state = new UserPageState($user);

return $this->page('default', $state, 'User');
```

Legacy `$this->set(...)`, `View::assign(...)`, and `View::assignState(...)` calls should be treated as migration work, not new Pair v4 code.

---

# Frontend pattern (PairUI)

PairUI is used for progressive enhancement.

Example directives:

```html
<span data-text="user.name"></span>
<div data-show="isLogged"></div>
```

Prefer these directives instead of introducing frontend frameworks.

---

# JavaScript pattern

Prefer:

- small vanilla JS scripts
- progressive enhancement

Avoid:

- jQuery
- large frontend frameworks
- build pipelines

Keep JS local, explicit, and easy to remove.

---

# Form handling pattern

Typical pattern:

```php
if ($this->input()->method() === 'POST') {
	$user = new User();
	$user->name = $this->input()->string('name', '');
	$user->save();
}
```

Agents should follow existing request helpers. For custom API endpoints, prefer `RequestData` and explicit validation responses when that pattern is already used nearby.

---

## Pattern selection rule

If multiple patterns seem possible:

1. prefer the one already used in the same namespace or component
2. prefer the one with the smaller diff
3. prefer the one that preserves current public behavior most directly

---

# When adding new components

Before creating new utilities:

1. check existing framework utilities
2. inspect similar components
3. follow naming conventions
4. implement minimal additions

Avoid introducing new subsystems.

---

# Summary

Agents implementing code in Pair should:

1. follow MVC separation
2. keep controllers thin
3. prefer ORM helpers
4. return explicit Pair v4 responses for new modules
5. use PairUI for frontend behavior
6. avoid unnecessary abstractions

Pair values **clarity, stability, and predictability**.
