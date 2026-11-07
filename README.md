# Pair

**A lightweight PHP framework for maintainable, server-rendered applications.**

[Website](https://viames.github.io/pair/) ·
[Documentation](https://github.com/viames/pair/wiki) ·
[Starter project](https://github.com/viames/pair_boilerplate) ·
[Releases](https://github.com/viames/pair/releases) ·
[Security](https://github.com/viames/pair/blob/main/SECURITY.md)

[![CI](https://github.com/viames/pair/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/viames/pair/actions/workflows/ci.yml?query=branch%3Amain)
[![Total downloads](https://poser.pugx.org/viames/pair/downloads)](https://packagist.org/packages/viames/pair)
[![Latest release](https://img.shields.io/packagist/v/viames/pair)](https://packagist.org/packages/viames/pair)
[![License](https://poser.pugx.org/viames/pair/license)](https://packagist.org/packages/viames/pair)
[![PHP requirement](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2Fviames%2Fpair%2Fmain%2Fcomposer.json&query=%24.require.php&label=PHP&color=777BB4)](https://github.com/viames/pair/blob/main/composer.json)

Pair is designed for small and medium PHP/MySQL applications where clear
architecture, low operational overhead and long-term maintainability matter
more than a heavy frontend toolchain.

It combines explicit MVC responses, an ActiveRecord-style ORM, CRUD and OpenAPI
tooling, progressive enhancement, authentication helpers and conservative
upgrade tooling in a codebase intended to remain inspectable by both people and
AI-assisted workflows.

[Quick start](#quick-start) · [Application development](#application-development) · [Requirements](#requirements) ·
[Documentation](#documentation) · [Development](#development)

## Status

| Line | Status | Use |
| --- | --- | --- |
| Pair 4 releases | Stable | New and current applications |
| Pair 4 `main` | Unreleased | Framework development and upcoming changes |
| Pair 3 | Maintenance | Existing applications awaiting migration |

The current stable release is [Pair 4.1.12](https://github.com/viames/pair/releases/tag/4.1.12).

Pair 4 is used in production across the maintainer's applications. The
[release history](https://github.com/viames/pair/releases) lists published versions;
see the [upgrade guide](https://github.com/viames/pair/blob/main/UPGRADE_V4.md)
for migration from earlier lines.

## Quick start

With the [requirements](#requirements) installed, create an application with
authentication, ACL, migrations and localization:

```sh
composer create-project viames/pair_boilerplate my-project dev-main
cd my-project
composer require "viames/pair:^4.1.12"
```

The Pair 4 starter is currently on the boilerplate's `main` branch, selected by
`dev-main`; its current stable release, `3.0.0`, installs Pair 3. The final command
selects the stable Pair 4 framework instead of keeping the starter's older lock.
The next stable starter, 4.0.0, is being prepared with the fixed Composer CLI;
see its [release procedure](https://github.com/viames/pair_boilerplate/blob/main/RELEASING.md).

1. Serve the project through Apache with `mod_rewrite` and `.htaccess` overrides
   enabled. If it is inside your local document root, open
   `http://localhost/my-project/`; otherwise use your configured virtual-host URL.
   See the [starter setup](https://github.com/viames/pair_boilerplate#installation)
   for the Apache configuration.
2. Follow the browser installer using a dedicated MySQL database and an account
   permitted to initialize it. The installer imports the schema and seed data,
   writes `.env` and creates the first administrator. It requires `fileinfo`
   in addition to the framework's required PHP extensions.
3. Log in with the generated credentials, change the initial password and run
   the administrator's **Self test** module.

For an existing application, install the framework directly:

```sh
composer require "viames/pair:^4.1.12"
```

Then follow the [bootstrap guide](https://github.com/viames/pair/wiki/index)
and [environment reference](https://github.com/viames/pair/wiki/Configuration-file)
to configure its database and `public/index.php`.

## Why Pair

- [Explicit controllers](https://github.com/viames/pair/wiki/Controller), typed page state and page, fragment, JSON, text and empty responses
- [ActiveRecord](https://github.com/viames/pair/wiki/ActiveRecord) persistence, casting, relations and collections
- [Forms](https://github.com/viames/pair/wiki/Form), validation, CSRF, authentication, ACL and sessions
- [API and CRUD tooling](https://github.com/viames/pair/wiki/API) with explicit read models and OpenAPI contracts
- [PairUI](https://github.com/viames/pair/wiki/PairUI.js) progressive enhancement, PWA, push and passkeys without a mandatory frontend build
- Native iOS/Android helpers and [optional integrations](https://github.com/viames/pair/wiki/Integrations)
- Conservative upgrade tooling, logging, diagnostics and tests across supported PHP versions

## A Pair 4 page

An explicit controller passes a named, immutable state object to its layout.
These files belong to an application's `modules/welcome/` directory.

`classes/WelcomePageState.php`:

```php
<?php

/** Data rendered by the welcome page. */
final readonly class WelcomePageState {

	/** Create the page state. */
	public function __construct(public string $message) {}

}
```

`controller.php`:

```php
<?php

use Pair\Web\Controller;
use Pair\Web\PageResponse;

require_once __DIR__ . '/classes/WelcomePageState.php';

/** Render the welcome module. */
final class WelcomeController extends Controller {

	/** Return the default page with explicit state. */
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

Default routes use the form `/<module>/<action>/<params...>`. Legacy Pair
controllers and views remain available as migration bridges, while new Pair 4
modules should use explicit response contracts.

For a complete controller/state/layout example, see the
[implementation patterns](https://github.com/viames/pair/blob/main/PAIR_PATTERNS.md#controller-and-page-response).

## Application development

In the starter project, run generators through its local CLI wrapper:

```sh
composer run pair -- make:module orders
composer run pair -- make:api catalogapi
composer run pair -- make:crud order --table=orders --fields=id,customer_id,total_amount
```

Choose unused module names and review generated code using the
[generator guide](https://github.com/viames/pair/wiki/Generator).
For an existing application's major-version upgrade, follow
[UPGRADE_V4.md](https://github.com/viames/pair/blob/main/UPGRADE_V4.md), start from
a clean working tree or verified backup, and pass `--dry-run` explicitly before
writing changes. Tool mode defaults differ between upgrade generations.

## Requirements

- PHP 8.4.1 or later within PHP 8.x; PHP 8.5 recommended
- MySQL 8.0 or later for the default database driver
- Composer 2
- Apache 2.4 with `mod_rewrite` for the standard web setup
- PHP extensions: `curl`, `intl`, `json`, `mbstring`, `pdo`, `pdo_mysql`

Pair 4.1.12 supports MySQL 26.10 alongside MySQL 8.0/8.4 and uses `utf8mb4`
connection defaults. The test matrix is described under [Development](#development).
See the [database reference](https://github.com/viames/pair/wiki/Database#mysql-compatibility-and-unicode)
and [upgrade notes](https://github.com/viames/pair/blob/main/UPGRADE_V4.md#pair-4112-mysql-2610-and-utf8mb4)
for connection behavior, existing schema requirements and rollout guidance.

Feature-specific extensions are optional for the core runtime: `fileinfo` for
MIME detection, `openssl` for passkeys, `redis` for Redis integrations and
`xdebug` for debugging. AWS S3 and Stripe integrations also require their
respective SDKs; see [Integrations](https://github.com/viames/pair/wiki/Integrations).

## Development

For framework contributors, start with
[AGENTS.md](https://github.com/viames/pair/blob/main/AGENTS.md) and the
[source map](https://github.com/viames/pair/blob/main/SKILL.md).
Run checks from the framework checkout:

```sh
composer install
composer test
composer test:install
```

The [installation check](https://github.com/viames/pair/tree/main/tests/Integration/Composer)
tests real Composer archives and consuming projects offline. Add
`-- ../pair_boilerplate` to verify the prepared stable starter as well; it needs
the development-only PHP `zip` extension. Optional database checks use a guarded
disposable schema and never read an application's credentials.

Database compatibility has a separate real-server suite: `composer test:mysql`.
Provision a disposable schema and a restricted account using the
[MySQL integration guide](https://github.com/viames/pair/tree/main/tests/Integration/MySql).
The configured CI matrix targets PHP 8.4/8.5 with MySQL 8.0, 8.4 and 26.10;
the unit suite does not require a MySQL server.

Use `composer run benchmark-v4` for migration/performance checks when relevant.

For internals, see the [architecture](https://github.com/viames/pair/blob/main/PAIR_ARCHITECTURE.md)
and [database metadata cache](https://github.com/viames/pair/blob/main/docs/DATABASE_METADATA_CACHE.md).
For publication, follow the [release workflow](https://github.com/viames/pair/blob/main/RELEASING.md)
and [Composer package contents](https://github.com/viames/pair/blob/main/docs/COMPOSER_DIST_CONTENTS.md).

## Documentation

Choose a guide by task:

| Task | Guide |
| --- | --- |
| Start an application | [Starter project](https://github.com/viames/pair_boilerplate), [bootstrap](https://github.com/viames/pair/wiki/index), [configuration](https://github.com/viames/pair/wiki/Configuration-file) |
| Build modules and APIs | [Wiki learning paths and class reference](https://github.com/viames/pair/wiki), [generator](https://github.com/viames/pair/wiki/Generator) |
| Upgrade an application | [Pair 4 upgrade guide](https://github.com/viames/pair/blob/main/UPGRADE_V4.md) |
| Contribute to Pair | [Framework development](#development), [implementation patterns](https://github.com/viames/pair/blob/main/PAIR_PATTERNS.md) |

For native clients, start with [mobile auth setup](https://github.com/viames/pair/blob/main/docs/MOBILE_AUTH_APP_SETUP.md),
then the [iOS](https://github.com/viames/pair/blob/main/docs/MOBILE_IOS_STACK.md)
or [Android](https://github.com/viames/pair/blob/main/docs/MOBILE_ANDROID_STACK.md) guide.

The wiki follows `main` and may document unreleased changes. Compare examples
with the source for the Pair version installed in your application.

## Maintainer

Pair is created and maintained by [Viames Marino](https://github.com/viames).
His professional practice, [VM Ingegneria](https://vmingegneria.it/), covers
external technical direction, software modernization and governed AI integration.

For a professional project, use the [VM Ingegneria contact form](https://vmingegneria.it/#contatti).
For framework usage and contributions, use the documentation and repository channels above.

## Security

Report vulnerabilities privately by following [SECURITY.md](https://github.com/viames/pair/blob/main/SECURITY.md). Do
not open a public issue for an unassessed security report.

## Contributing

Focused pull requests are welcome. Include tests when behavior changes and
update the owning documentation when commands, requirements or public contracts
change.

## License

MIT
