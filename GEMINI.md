# GEMINI.md — Pair Framework (single source of truth)

Project context, conventions, and guardrails for AI assistants working on the Pair framework.
All agents must follow this file unless a task explicitly overrides it.

If you are an automated agent, also read **AGENTS.md** for workflow and PR expectations.

---

## Operating principles

- **Minimal diff**: change only what the task requires.
- **No breaking changes**: maintain backward compatibility unless explicitly requested.
- **Security first**: never bypass security mechanisms, never weaken validation.
- **Follow existing patterns** instead of inventing new ones.

---

## Project overview

**Pair** is a PHP framework for building modern web applications.

Primary goals: provide a solid foundation for MVC architecture, database abstraction (ORM), user management, task scheduling (cron), push notifications, and more.

---

## Stack and conventions

- UI: The framework provides a lightweight UI library, **PairUI**.
- JavaScript:
  - Prefer **Vanilla JS** for new code.
  - Use PairUI directives for reactivity: `data-text`, `data-html`, `data-show`, `data-if`, `data-model` (two-way), `data-on` (events), `data-each` (lists).
  - Avoid `eval` or unsafe inline handlers.
- Framework: **Pair (PHP) v3-alpha**.

### Language and i18n
- Default user-facing framework messages should be in **English**.
- Internationalization should use the files under `/translations`.
- Avoid hardcoding language-specific strings when a translation key exists.

---

## PHP Environment (for framework developers)

- PHP Version: **8.3 / 8.4 (or higher if the framework has been upgraded)**.
- Required extensions (minimum): `fileinfo`, `json`, `pcre`, `PDO`, `intl`, `pdo_mysql`, `Reflection`.

---

## Project structure and architecture

### Folder layout (high-level)
- `/src`                Framework source code, organized by namespace (e.g. `Core`, `Orm`, `Html`).
- `/assets`             Frontend assets, such as `PairUI` JavaScript files.
- `/translations`       Translation files for framework strings (e.g. `it-IT.ini`).
- `/tests`              Unit tests and integration tests.

### Architecture
- The framework is designed to support the **MVC** pattern in applications that use it.
- Core classes reside under the `Pair\` namespace and follow the PSR-4 standard for autoloading.
- One class per file; **filename matches class name**.

### ORM relationship magic methods (important for AI agents)
- Pair `ActiveRecord` automatically exposes relation helpers for mapped foreign keys.
- **Automatic casting**: Properties are automatically cast to PHP types (int, bool, DateTime, float) based on the database schema.
- For a parent relation (e.g. `affiliate_id`), calling `getAffiliate()` returns the related `Affiliate` object.
- If the FK is null or not resolvable, the magic method returns `null` (it must be null-checked before use).
- Equivalent read access is also available via parent-property helpers (e.g. `getParentProperty('affiliateId', 'name')`) when only one field is needed.
- Reverse relations (1:N) are also supported. For example, if `User` has `group_id`, calling `$group->getUsers()` on a `Group` object returns a `Collection` of `User` objects.
- Agents should prefer these relation methods over manual duplicate queries when a mapped relation already exists.

### Routing and URL mapping (Pair apps)
- After the base path, URLs follow `/<module>/<action>/<params...>`.
- The module maps to `/modules/<module>` in the host app and its `controller.php` (extending `Pair\Core\Controller`).
- The action typically maps to an `<action>Action()` method if present.
- Pair auto-loads the module `model.php`, the view `view<Action>.php` (e.g. `UserViewLogin`), and the layout `/modules/<module>/layouts/<action>.php` by default.

---

## Coding standards (framework-specific)

### Formatting
- PHP indentation: **tabs only**, editor width **4 spaces**.
- Inline PHP: avoid short tags; use `<?php ... ?>`.
- Prefer `print` (framework convention) instead of `echo`.
- Keep conditions readable (no complex one-liners).
- Opening braces `{` stay on the **same line** as the statement (K&R style).

### Naming
- Variables: `camelCase`, descriptive.
- Classes: `CamelCase`, filename matches class name.
- Interfaces: suffix `Interface`.
- Constants: `UPPER_SNAKE_CASE`.
- Public method names: keep them **very short** and avoid the `get` prefix where possible.
- Private method names: keep them short when possible; medium-length names are acceptable when needed to disambiguate intent.

### Ordering
- Methods in classes should be ordered alphabetically (case-insensitive) when possible.

### Comments
- Single-line `//` in lowercase.
- Docblock `/** ... */` as complete sentences with punctuation.

### Control flow style
- Prefer multi-line `if/else` for non-trivial logic.
- Ternary operator only for simple expressions.
- Prefer `and` instead of `&&` and `or` instead of `||`.
  - Note: `and`/`or` have different precedence than `&&`/`||` in PHP. Use parentheses to remove ambiguity.

---

## Database and Migrations

- The framework provides an ORM (`Pair\Orm\ActiveRecord`) and migration tools.
- Framework development must ensure these tools are robust and, where possible, database-agnostic.
- Always follow safe query practices (e.g. prepared statements).

---

## Security

- Provide tools that are "secure-by-default".
- Key areas are: input validation, output encoding, CSRF protection, secure session management.
- Every contribution will be reviewed for security to avoid introducing vulnerabilities.

---

## Cronjob / Scheduler

- The framework provides an engine for executing scheduled tasks, typically initialized by a `cronjob.php` file in the host application.
- The framework does not define specific tasks but provides the tools to create and manage them.
- Tasks developed with the framework should be idempotent.

---

## Push Notifications

- The framework provides the `Pair\Push` component for the backend and `pair/push.js` for frontend integration.
- It handles subscription, VAPID key management, and notification sending.
- The host application must provide VAPID keys in its environment.
