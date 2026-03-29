# PAIR_PATTERNS.md — Pair Framework

This document describes **idiomatic coding patterns** for the Pair PHP framework.
Read it when you already know where to change code and need to match Pair’s implementation style.
Workflow and change-hygiene rules stay in `AGENTS.md`; technical conventions stay in `GEMINI.md`.

---

## Fast Pattern Guide

Use these defaults unless nearby code shows a different established pattern:

- Controllers stay thin and coordinate work.
- Models and ORM relations carry data access behavior.
- Views stay simple and contain minimal logic.
- Frontend behavior stays progressive and lightweight.
- Prefer existing framework helpers over custom abstractions.

---

# Controller pattern

Controllers extend:

`Pair\Core\Controller`

Responsibilities:

- receive the request
- orchestrate business logic
- load models if needed
- pass data to views

Controllers should remain **thin**.

Example:

```php
class UserController extends \Pair\Core\Controller
{
	public function loginAction()
	{
		$users = User::getAll();

		$this->set('users', $users);
	}
}
```

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
class User extends \Pair\Orm\ActiveRecord
{
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

# View pattern

Views are simple PHP templates.

Naming convention:

```text
view<Action>.php
```

Example:

```text
viewLogin.php
```

Views should contain minimal logic.

---

# Passing data to views

Controllers pass data to views using:

```php
$this->set('variableName', $value);
```

Example:

```php
$this->set('user', $user);
```

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
if ($this->request->isPost()) {
	$user = new User();
	$user->name = $this->request->post('name');
	$user->save();
}
```

Agents should follow existing request helpers.

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
4. use PairUI for frontend behavior
5. avoid unnecessary abstractions

Pair values **clarity, stability, and predictability**.
