<img src="https://github.com/viames/Pair/wiki/files/pair-logo.png" width="240">

[Website](https://viames.github.io/pair/) | [Wiki](https://github.com/viames/pair/wiki) | [Issues](https://github.com/viames/pair/issues)

[![Tests](https://github.com/viames/pair/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/viames/pair/actions/workflows/ci.yml?query=branch%3Amain)
[![Total Downloads](https://poser.pugx.org/viames/pair/downloads)](https://packagist.org/packages/viames/pair)
[![Latest Stable Version](https://poser.pugx.org/viames/pair/v/stable)](https://packagist.org/packages/viames/pair)
[![GitHub Release](https://img.shields.io/github/v/release/viames/pair)](https://github.com/viames/pair/releases)
[![License](https://poser.pugx.org/viames/pair/license)](https://packagist.org/packages/viames/pair)
[![PHP Version Require](https://poser.pugx.org/viames/pair/require/php)](https://packagist.org/packages/viames/pair)

Pair is a lightweight PHP framework for server-rendered web applications.
It focuses on fast setup, clear MVC routing, practical ORM features, and optional integrations (S3, SES, Telegram, Stripe, Push, Passkey) without heavy tooling.

## What's New

Pair v4 is currently in alpha and may include breaking changes while the next major version is under development.
Pair v3 is the current stable release line on the `v3` branch and through the `^3.0` tags.

Pair v4 now has an explicit core path built around:

- `Pair\Http\Input` for immutable request input
- `Pair\Data\ReadModel` for reusable HTML/API read contracts
- `Pair\Web\Controller` + `Pair\Web\PageResponse` for server-rendered actions
- `Pair\Http\JsonResponse` for explicit JSON endpoints
- `Pair\Api\ApiExposable::readModel` for CRUD output contracts

OpenAPI generation for CRUD resources now follows the explicit response contract too: when a resource defines `readModel`, the generated response schema is built from that read model instead of the persistence class.
`Pair\Core\Controller` and `Pair\Core\View` now remain only as legacy MVC bridges in Pair v4 and emit deprecation notices in development or staging environments.
The Pair v3 to v4 upgrader is conservative on purpose: it rewrites only the low-risk patterns automatically and reports legacy controller/view flows that still need a manual migration.
For classic MVC modules it now also generates readonly `*PageState` skeletons from legacy `View::assign()` usage, so migration work starts from concrete typed-state files instead of ad-hoc arrays or magic view variables.

## Quick Start

### 1) Install with Composer

```sh
composer require viames/pair
```

### 2) Bootstrap the application

```php
<?php

use Pair\Core\Application;

require 'vendor/autoload.php';

$app = Application::getInstance();
$app->run();
```

### 3) Next Steps

- [Wiki](https://github.com/viames/pair/wiki)
- [Routing](https://github.com/viames/pair/wiki/Router)
- [Configuration (.env)](https://github.com/viames/pair/wiki/Configuration-file)
- [Boilerplate project](https://github.com/viames/pair_boilerplate)

### 4) Generate Pair v4 skeletons

```sh
vendor/bin/pair make:module orders
vendor/bin/pair make:api api
vendor/bin/pair make:crud order --table=orders --fields=id,customer_id,total_amount
```

The generator writes explicit Pair v4 files and avoids overwriting user-edited files unless `--force` is provided.

## Why Pair

- Small and fast for small/medium projects.
- MVC structure with SEO-friendly routing.
- ActiveRecord-style ORM with automatic type casting.
- Installable Package architecture for ZIP-delivered modules, templates, providers, and custom package records.
- Runtime Extensions for explicit optional integrations.
- Good defaults for timezone, logging, and framework utilities.
- Optional third-party integrations when needed.

## Core Features

### ActiveRecord

Pair maps classes to DB tables and supports automatic casts (`int`, `bool`, `DateTime`, `float`, `csv`), relation helpers, and caching-oriented query helpers.

Docs: [ActiveRecord](https://github.com/viames/pair/wiki/ActiveRecord)

### Routing Basics

Default route format (after base path):

`/<module>/<action>/<params...>`

Example: `example.com/user/login`

- module: `/modules/user`
- controller legacy path: `/modules/user/controller.php` (extends `Pair/Core/Controller.php`, legacy bridge in v4)
- action: `loginAction()` when present
- auto-loaded by the legacy MVC bridge: `model.php`, `viewLogin.php` (`UserViewLogin`), and `/modules/user/layouts/login.php`

Docs: [Router](https://github.com/viames/pair/wiki/Router)

### Pair v4 Controller Path

Pair v4 prefers explicit responses over hidden controller/view bootstrapping:

```php
<?php

use Pair\Web\Controller;
use Pair\Web\PageResponse;

final class UserController extends Controller {

	/**
	 * Render the default user page.
	 */
	public function defaultAction(): PageResponse {

		$state = new class ('Hello Pair v4') {
			/**
			 * Store the page message.
			 */
			public function __construct(public string $message) {}
		};

		return $this->page('default', $state, 'User');

	}

}
```

For reusable output contracts, Pair v4 prefers `ReadModel` objects built explicitly from persistence records.
Layout files in the v4 path should remain mostly HTML. Optional file-level preambles such as `declare(strict_types=1)` or `/** @var UserPageState $state */` are only IDE/static-analysis hints and are not part of the runtime contract.

Minimal layout example:

```php
<main class="user-page">
	<h1><?= htmlspecialchars($state->message, ENT_QUOTES, 'UTF-8') ?></h1>
</main>
```

Legacy `Pair\Core\Controller` and `Pair\Core\View` remain available only as a migration path and should not be used for new Pair v4 modules.

### Log Bar and Debugging

Built-in log bar for loaded objects, memory usage, timings, SQL traces, backtraces, and custom debug messages.

## Frontend Helpers

### PairUI

Dependency-free helper for progressive enhancement in server-rendered apps (`assets/PairUI.js`).

Main directives:
- `data-text`, `data-html`, `data-show`, `data-if`
- `data-class`, `data-attr`, `data-prop`, `data-style`
- `data-model`, `data-on`, `data-each`

Docs: [PairUI.js](https://github.com/viames/pair/wiki/PairUI.js)

### PWA Helpers (No Build Step)

Available assets:
- `PairPWA.js`
- `PairSW.js`
- `PairRouter.js`
- `PairSkeleton.js`
- `PairDevice.js`
- `PairPasskey.js`

Minimal frontend setup:

```html
<script src="/assets/PairUI.js" defer></script>
<script src="/assets/PairPWA.js" defer></script>
<script src="/assets/PairRouter.js" defer></script>
<script src="/assets/PairSkeleton.js" defer></script>
<script src="/assets/PairDevice.js" defer></script>
<script src="/assets/PairPasskey.js" defer></script>
```

Important notes:
- Keep progressive enhancement.
- Service workers require HTTPS (except localhost).
- Use a single SW URL if you also enable Push.

## Passkey Quick Start

Backend:

```php
class ApiController extends \Pair\Api\PasskeyController {}
```

This enables:
- `POST /api/passkey/login/options`
- `POST /api/passkey/login/verify`
- `POST /api/passkey/register/options` (requires `sid`)
- `POST /api/passkey/register/verify` (requires `sid`)
- `GET /api/passkey/list` (requires `sid`)
- `DELETE /api/passkey/revoke/{id}` (requires `sid`)

## Third-Party Integrations

Pair includes optional support for services such as:
- [Amazon S3](https://aws.amazon.com/s3/)
- [Amazon SES](https://aws.amazon.com/ses/)
- [Telegram Bot API](https://core.telegram.org/bots/api)
- [OneSignal](https://onesignal.com/)
- [Stripe](https://stripe.com/docs)

In Pair v4 these integrations should be exposed through Runtime Extensions and manually registered adapters. This is separate from Installable Packages, the ZIP/manifest mechanism used for modules, templates, providers, and custom package records.

Configuration reference: [Configuration (.env)](https://github.com/viames/pair/wiki/Configuration-file)

## Upgrading

If you are upgrading a Pair v3 application to Pair v4:

```sh
composer run upgrade-to-v4 -- --dry-run
composer run upgrade-to-v4 -- --write
```

To test unreleased Pair 4 development code from `main`:

```sh
composer require viames/pair dev-main
```

Additional migration and design docs:

- [PAIR_V4_DESIGN.md](PAIR_V4_DESIGN.md)
- [UPGRADE_V4.md](UPGRADE_V4.md)

## Documentation

Main docs live in the [Wiki](https://github.com/viames/pair/wiki).
The release and branching workflow for the v3 stable / v4 dev transition is documented in [RELEASING.md](RELEASING.md).

Useful pages:
- [Generator](https://github.com/viames/pair/wiki/Generator)
- [Application](https://github.com/viames/pair/wiki/Application)
- [Controller](https://github.com/viames/pair/wiki/Controller)
- [View](https://github.com/viames/pair/wiki/View)
- [ApiExposable](https://github.com/viames/pair/wiki/ApiExposable)
- [CrudController](https://github.com/viames/pair/wiki/CrudController)
- [Form](https://github.com/viames/pair/wiki/Form)
- [Collection](https://github.com/viames/pair/wiki/Collection)
- [Push notifications](https://github.com/viames/pair/wiki/Push-notifications)
- [index.php](https://github.com/viames/pair/wiki/index)
- [.htaccess](https://github.com/viames/pair/wiki/htaccess)
- [classes](https://github.com/viames/pair/wiki/Classes-folder)

## Requirements

| Software | Recommended | Minimum | Configuration |
| --- | :---: | :---: | --- |
| Apache | 2.4+ | 2.4 | `modules:` mod_rewrite |
| MySQL | 8.0+ | 8.0 | `character_set:` utf8mb4 <br> `collation:` utf8mb4_unicode_ci <br> `storage_engine:` InnoDB |
| PHP | 8.4+ | 8.3 | Composer-required extensions: `curl`, `intl`, `json`, `mbstring`, `PDO` |

Runtime notes:
- `pdo_mysql` is required when using the default MySQL driver (`Pair\\Orm\\Database`).
- `fileinfo` is strongly recommended for reliable MIME detection in uploads.
- `openssl` is required only for Passkey/WebAuthn features.
- `pcre` and `Reflection` are part of standard PHP 8+ builds.

## Example Project

Start from [pair_boilerplate](https://github.com/viames/pair_boilerplate) to bootstrap a new app quickly.

## Benchmarks

The repository includes a lightweight benchmark harness for the new v4 path:

```sh
composer run benchmark-v4
```

It measures:

- minimal request bootstrap primitives
- simple server-rendered page rendering
- simple JSON endpoint payload preparation
- record-to-read-model mapping cost
- response serialization cost

## Support

- Issues: [github.com/viames/pair/issues](https://github.com/viames/pair/issues)
- Wiki: [github.com/viames/pair/wiki](https://github.com/viames/pair/wiki)
- Source: [github.com/viames/pair/tree/main/src](https://github.com/viames/pair/tree/main/src)
- Homepage: [viames.github.io/pair](https://viames.github.io/pair/)

## Changelog

Version history is available in GitHub Releases: [github.com/viames/pair/releases](https://github.com/viames/pair/releases)

## Security

If you discover a security issue, follow the private reporting guidance in [SECURITY.md](SECURITY.md).

## Contributing

Feedback, code contributions, and documentation improvements are welcome via pull request.

## License

MIT
