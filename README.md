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

[Quick start](#quick-start) · [Requirements](#requirements) ·
[Documentation](#documentation) · [Development](#development)

## Status

| Line | Status | Use |
| --- | --- | --- |
| Pair 4 | Stable | New and current applications |
| Pair 3 | Maintenance | Existing applications awaiting migration |

Pair 4 is used in production across the maintainer's applications. The
[release history](https://github.com/viames/pair/releases) lists published versions;
see the [upgrade guide](https://github.com/viames/pair/blob/main/UPGRADE_V4.md)
for migration from earlier lines.

## Quick start

With the [requirements](#requirements) installed, create a ready-to-run application
with authentication, ACL, migrations and localization:

```sh
composer create-project viames/pair_boilerplate my-project
```

To add the framework to an existing project:

```sh
composer require "viames/pair:^4.1.6"
```

Configure the application's `.env` and database using the
[setup guide](https://github.com/viames/pair/wiki/index), then bootstrap in `public/index.php`:

```php
<?php

use Pair\Core\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

Application::getInstance()->run();
```

## Why Pair

- [Explicit controllers](https://github.com/viames/pair/wiki/Controller), typed page state and page, fragment, JSON, text and empty responses
- [ActiveRecord](https://github.com/viames/pair/wiki/ActiveRecord) persistence, casting, relations and collections
- [Forms](https://github.com/viames/pair/wiki/Form), validation, CSRF, authentication, ACL and sessions
- [API and CRUD tooling](https://github.com/viames/pair/wiki/API) with explicit read models and OpenAPI contracts
- [PairUI](https://github.com/viames/pair/wiki/PairUI.js) progressive enhancement, PWA, push and passkeys without a mandatory frontend build
- Native iOS/Android helpers and [optional integrations](https://github.com/viames/pair/wiki/Integrations)
- Conservative upgrade tooling, logging, diagnostics and tests across supported PHP versions

## A Pair 4 page

Pair 4 favors explicit responses over hidden view bootstrapping:

```php
<?php

use Pair\Web\Controller;
use Pair\Web\PageResponse;

final class UserController extends Controller {

	public function defaultAction(): PageResponse {

		$state = new class ('Hello Pair') {

			public function __construct(public string $message) {}

		};

		return $this->page('default', $state, 'User');

	}

}
```

The corresponding layout stays presentation-focused:

```php
<main class="user-page">
	<h1><?= htmlspecialchars($state->message, ENT_QUOTES, 'UTF-8') ?></h1>
</main>
```

Default routes use the form `/<module>/<action>/<params...>`. Legacy Pair
controllers and views remain available as migration bridges, while new Pair 4
modules should use explicit response contracts.

For a complete controller/state/layout example, see the
[implementation patterns](https://github.com/viames/pair/blob/main/PAIR_PATTERNS.md#controller-and-page-response).

## Requirements

- PHP 8.4.1 or later within PHP 8.x; PHP 8.5 recommended
- MySQL 8.0 or later for the default database driver
- Composer 2
- Apache 2.4 with `mod_rewrite` for the standard web setup
- PHP extensions: `curl`, `intl`, `json`, `mbstring`, `pdo`, `pdo_mysql`

Feature-specific extensions are optional for the core runtime: `fileinfo` for
MIME detection, `openssl` for passkeys, `redis` for Redis integrations and
`xdebug` for debugging. AWS S3 and Stripe integrations also require their
respective SDKs; see [Integrations](https://github.com/viames/pair/wiki/Integrations).

## Development

From the framework checkout:

```sh
composer install
composer test
```

Use `composer run benchmark-v4` for migration/performance checks when relevant.

Generate Pair 4 code from an application that has Pair installed:

```sh
vendor/bin/pair make:module orders
vendor/bin/pair make:api api
vendor/bin/pair make:crud order --table=orders --fields=id,customer_id,total_amount
```

For upgrades, follow [UPGRADE_V4.md](https://github.com/viames/pair/blob/main/UPGRADE_V4.md),
start from a clean working tree or verified backup, and pass `--dry-run` explicitly
before writing changes. Tool mode defaults differ between upgrade generations.

## Documentation

Choose a guide by task:

| Task | Guide |
| --- | --- |
| Set up an application | [Bootstrap](https://github.com/viames/pair/wiki/index), [configuration](https://github.com/viames/pair/wiki/Configuration-file), [starter project](https://github.com/viames/pair_boilerplate) |
| Learn the framework or find a class | [Wiki learning paths and reference index](https://github.com/viames/pair/wiki) |
| Generate a web, API or CRUD module | [Generator](https://github.com/viames/pair/wiki/Generator) |
| Migrate an existing application | [Pair 4 upgrade guide](https://github.com/viames/pair/blob/main/UPGRADE_V4.md) |
| Connect native clients | [Mobile auth setup](https://github.com/viames/pair/blob/main/docs/MOBILE_AUTH_APP_SETUP.md), [iOS](https://github.com/viames/pair/blob/main/docs/MOBILE_IOS_STACK.md), [Android](https://github.com/viames/pair/blob/main/docs/MOBILE_ANDROID_STACK.md) |
| Work on the framework with an agent | [AGENTS.md](https://github.com/viames/pair/blob/main/AGENTS.md), then [quick start and source map](https://github.com/viames/pair/blob/main/SKILL.md) |
| Understand v4 internals and design | [Architecture](https://github.com/viames/pair/blob/main/PAIR_ARCHITECTURE.md), [design rationale](https://github.com/viames/pair/blob/main/PAIR_V4_DESIGN.md) |
| Release or inspect package contents | [Release workflow](https://github.com/viames/pair/blob/main/RELEASING.md), [Composer dist contents](https://github.com/viames/pair/blob/main/docs/COMPOSER_DIST_CONTENTS.md) |

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

Database introspection and optional versioned caching: [metadata cache](docs/DATABASE_METADATA_CACHE.md).
