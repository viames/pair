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

## Status

| Line | Status | Use |
| --- | --- | --- |
| Pair 4 | Stable | New and current applications |
| Pair 3 | Maintenance | Existing applications awaiting migration |

Pair 4.1.1 is the current stable release. Pair 4 is used in production across
the maintainer's applications. See
[Releases](https://github.com/viames/pair/releases) for version history and
[UPGRADE_V4.md](https://github.com/viames/pair/blob/main/UPGRADE_V4.md) for migration guidance.

## Quick start

Install Pair 4.1.1 or a later compatible Pair 4 release:

```sh
composer require "viames/pair:^4.1.1"
```

Bootstrap the application in `public/index.php` after configuring its `.env`
and database (see the [setup guide](https://github.com/viames/pair/wiki/index)):

```php
<?php

use Pair\Core\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = Application::getInstance();
$app->run();
```

For authentication, ACL, migrations, localization and a ready-to-run
application structure, start with the boilerplate:

```sh
composer create-project viames/pair_boilerplate my-project
```

## Why Pair

- Server-rendered applications without a mandatory frontend build chain
- Explicit controllers, responses and typed page-state objects in Pair 4
- ActiveRecord-style ORM with casting, relations and query helpers
- CRUD generators and OpenAPI-oriented API contracts
- PairUI progressive enhancement with no runtime dependency
- PWA, push, passkey and native mobile helpers
- Conservative, dry-run-first migration tools
- Focused tests and CI across supported PHP versions
- Optional integrations without forcing them into the core runtime

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

## Main capabilities

### Web and data

- MVC routing and explicit page, JSON, redirect, file and stream responses
- ActiveRecord-style persistence, collections, relations and type casting
- Form controls, validation presets and CSRF protection
- Authentication, ACL and session helpers
- Logging, SQL traces and development diagnostics

### APIs and clients

- CRUD-oriented API controllers and explicit read models
- OpenAPI-oriented response contracts
- OAuth2 and bearer-session building blocks
- iOS and Android helpers for Pair-backed applications

### Progressive enhancement

PairUI adds lightweight `data-*` directives for text, visibility, attributes,
events, models and repeated content. Additional helpers cover PWA installation,
service workers, routing, skeleton states, validation, passkeys and push
notifications while preserving server-rendered behavior.

See [PairUI.js](https://github.com/viames/pair/wiki/PairUI.js) and the
[documentation index](https://github.com/viames/pair/wiki) for examples.

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

```sh
composer install
composer test
composer run benchmark-v4
```

Generate Pair 4 code from an application that has Pair installed:

```sh
vendor/bin/pair make:module orders
vendor/bin/pair make:api api
vendor/bin/pair make:crud order --table=orders --fields=id,customer_id,total_amount
```

Upgrade tools operate in dry-run mode first and report application-specific
code that still requires manual migration. Follow [UPGRADE_V4.md](https://github.com/viames/pair/blob/main/UPGRADE_V4.md)
and begin from a clean working tree or verified backup.

## Documentation

- [Application](https://github.com/viames/pair/wiki/Application)
- [Router](https://github.com/viames/pair/wiki/Router)
- [Controller](https://github.com/viames/pair/wiki/Controller)
- [ActiveRecord](https://github.com/viames/pair/wiki/ActiveRecord)
- [Forms](https://github.com/viames/pair/wiki/Form)
- [API exposure](https://github.com/viames/pair/wiki/ApiExposable)
- [CRUD controllers](https://github.com/viames/pair/wiki/CrudController)
- [PairUI](https://github.com/viames/pair/wiki/PairUI.js)
- [Configuration](https://github.com/viames/pair/wiki/Configuration-file)
- [Pair 4 design](https://github.com/viames/pair/blob/main/PAIR_V4_DESIGN.md)
- [Pair 4 upgrade guide](https://github.com/viames/pair/blob/main/UPGRADE_V4.md)

## Security

Report vulnerabilities privately by following [SECURITY.md](https://github.com/viames/pair/blob/main/SECURITY.md). Do
not open a public issue for an unassessed security report.

## Contributing

Focused pull requests are welcome. Include tests when behavior changes and
update the owning documentation when commands, requirements or public contracts
change.

## License

MIT
