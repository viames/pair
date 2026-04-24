# Pair

**Lightweight PHP framework for fast server-rendered web applications.**

[Website](https://viames.github.io/pair/) ·
[Wiki](https://github.com/viames/pair/wiki) ·
[Boilerplate](https://github.com/viames/pair_boilerplate) ·
[Issues](https://github.com/viames/pair/issues) ·
[Releases](https://github.com/viames/pair/releases) ·
[Security](SECURITY.md)

[![Tests](https://github.com/viames/pair/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/viames/pair/actions/workflows/ci.yml?query=branch%3Amain)
[![Total Downloads](https://poser.pugx.org/viames/pair/downloads)](https://packagist.org/packages/viames/pair)
[![Latest Stable Version](https://poser.pugx.org/viames/pair/v/stable)](https://packagist.org/packages/viames/pair)
[![GitHub Release](https://img.shields.io/github/v/release/viames/pair)](https://github.com/viames/pair/releases)
[![License](https://poser.pugx.org/viames/pair/license)](https://packagist.org/packages/viames/pair)
[![PHP Version Require](https://poser.pugx.org/viames/pair/require/php)](https://packagist.org/packages/viames/pair)

Pair is a lightweight PHP framework for server-rendered web applications. It focuses on fast setup, clear MVC routing, practical ActiveRecord-style ORM features, API tooling, progressive enhancement and optional integrations without heavy tooling.

Pair is designed for small and medium web applications where you want a clear PHP/MySQL stack, server-rendered pages, useful defaults, low operational overhead and a framework that remains easy to inspect, extend and maintain.

## Version status

| Line | Status | Recommended use |
| --- | --- | --- |
| Pair v3 | Stable | Production applications |
| Pair v4 | Alpha / development | New architecture testing, migration work and early adopters |

Pair v3 is the current stable release line. Pair v4 is under active development on `main` and may include breaking changes while the next major version is being finalized.

## Quick start

### 1. Install Pair stable

```sh
composer require viames/pair:^3.0
```

Or simply:

```sh
composer require viames/pair
```

### 2. Bootstrap the application

```php
<?php

use Pair\Core\Application;

require __DIR__ . '/vendor/autoload.php';

$app = Application::getInstance();
$app->run();
```

### 3. Start from the boilerplate

For a ready-to-use application structure, start from:

```txt
https://github.com/viames/pair_boilerplate
```

## Why Pair

- Server-rendered web applications without a heavy frontend build chain.
- MVC routing with clear module/action conventions.
- ActiveRecord-style ORM with practical type casting and relation helpers.
- API tooling for CRUD resources and OpenAPI-oriented contracts.
- PairUI helpers for progressive enhancement.
- PWA, push and passkey helpers without forcing a SPA architecture.
- Runtime extensions for optional integrations.
- Installable package architecture for modules, templates, providers and custom package records.
- Useful defaults for timezone, logging, debugging and framework utilities.
- Small enough to understand, extend and maintain.

## Core features

### Routing and MVC

Default route format after the base path:

```txt
/<module>/<action>/<params...>
```

Example:

```txt
example.com/user/login
```

Typical legacy MVC module structure:

```txt
/modules/user/controller.php
/modules/user/model.php
/modules/user/viewLogin.php
/modules/user/layouts/login.php
```

In Pair v4, legacy `Pair\Core\Controller` and `Pair\Core\View` remain available as migration bridges, but new modules should prefer explicit controllers and responses.

Docs: [Router](https://github.com/viames/pair/wiki/Router)

### ActiveRecord ORM

Pair maps PHP classes to database tables and supports practical ORM features such as:

- automatic casts for `int`, `bool`, `DateTime`, `float` and `csv`
- relation helpers
- query helpers
- cache-oriented access patterns
- database-backed CRUD resources

Docs: [ActiveRecord](https://github.com/viames/pair/wiki/ActiveRecord)

### Pair v4 explicit controller path

Pair v4 prefers explicit responses over hidden controller/view bootstrapping.

```php
<?php

use Pair\Web\Controller;
use Pair\Web\PageResponse;

final class UserController extends Controller {

	public function defaultAction(): PageResponse {

		$state = new class ('Hello Pair v4') {

			public function __construct(public string $message) {}

		};

		return $this->page('default', $state, 'User');

	}

}
```

Minimal layout example:

```php
<main class="user-page">
	<h1><?= htmlspecialchars($state->message, ENT_QUOTES, 'UTF-8') ?></h1>
</main>
```

For reusable output contracts, Pair v4 prefers `ReadModel` objects built explicitly from persistence records.

### API and OpenAPI tooling

Pair includes API helpers for CRUD-oriented resources and explicit response contracts. In Pair v4, OpenAPI generation for CRUD resources can use `readModel` contracts, so generated response schemas describe the public output model instead of leaking persistence classes.

Useful docs:

- [ApiExposable](https://github.com/viames/pair/wiki/ApiExposable)
- [CrudController](https://github.com/viames/pair/wiki/CrudController)
- [Generator](https://github.com/viames/pair/wiki/Generator)

### Log bar and debugging

Pair includes a built-in log bar for development and diagnostics:

- loaded objects
- memory usage
- timings
- SQL traces
- backtraces
- custom debug messages

## Frontend helpers

### PairUI

PairUI is a dependency-free helper for progressive enhancement in server-rendered applications.

Main directives:

- `data-text`, `data-html`, `data-show`, `data-if`
- `data-class`, `data-attr`, `data-prop`, `data-style`
- `data-model`, `data-on`, `data-each`

Docs: [PairUI.js](https://github.com/viames/pair/wiki/PairUI.js)

### PWA helpers

Available assets:

- `PairUI.js`
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
- Service workers require HTTPS, except on localhost.
- Use a single service worker URL if you also enable push notifications.

## Passkey quick start

Backend:

```php
class ApiController extends \Pair\Api\PasskeyController {}
```

This enables:

```txt
POST   /api/passkey/login/options
POST   /api/passkey/login/verify
POST   /api/passkey/register/options
POST   /api/passkey/register/verify
GET    /api/passkey/list
DELETE /api/passkey/revoke/{id}
```

## Optional integrations

Pair includes optional support for services and runtime integrations such as:

- Amazon S3
- Amazon SES
- Telegram Bot API
- OneSignal
- Stripe
- Passkey/WebAuthn helpers
- Web push helpers

In Pair v4 these integrations should be exposed through Runtime Extensions and manually registered adapters. This is separate from Installable Packages, the ZIP/manifest mechanism used for modules, templates, providers and custom package records.

Configuration reference: [Configuration (.env)](https://github.com/viames/pair/wiki/Configuration-file)

## Pair v4 development line

To test unreleased Pair v4 development code from `main`:

```sh
composer require viames/pair:4.x-dev@dev
```

Generate Pair v4 skeletons:

```sh
vendor/bin/pair make:module orders
vendor/bin/pair make:api api
vendor/bin/pair make:crud order --table=orders --fields=id,customer_id,total_amount
```

The generator writes explicit Pair v4 files and avoids overwriting user-edited files unless `--force` is provided.

Additional migration and design docs:

- [PAIR_V4_DESIGN.md](PAIR_V4_DESIGN.md)
- [UPGRADE_V4.md](UPGRADE_V4.md)
- [RELEASING.md](RELEASING.md)

## Upgrading

If you are upgrading a Pair v3 application to Pair v4, run the upgrader in dry-run mode first.

From a Pair application that has Pair installed as a dependency:

```sh
php vendor/viames/pair/scripts/upgrade-to-v4.php --dry-run
php vendor/viames/pair/scripts/upgrade-to-v4.php --write
```

From inside the Pair repository itself:

```sh
composer run upgrade-to-v4 -- --dry-run
composer run upgrade-to-v4 -- --write
```

The upgrader is conservative by design. It rewrites low-risk patterns automatically and reports legacy controller/view flows that still require manual migration.

## Requirements

| Software | Minimum | Recommended | Notes |
| --- | :---: | :---: | --- |
| PHP | 8.3 | 8.4 / 8.5 | Required by Composer |
| Apache | 2.4 | 2.4+ | `mod_rewrite` recommended |
| MySQL | 8.0 | 8.0+ | `utf8mb4`, `utf8mb4_unicode_ci`, InnoDB |
| Composer | 2.x | Latest stable | Required for package installation |

Required PHP extensions:

- `curl`
- `intl`
- `json`
- `mbstring`
- `pdo`
- `pdo_mysql`

Recommended or optional extensions:

- `fileinfo` for reliable MIME detection in uploads
- `openssl` for Passkey/WebAuthn features
- `redis` for Redis-backed integrations
- `xdebug` for development and debugging

## Example project

Start from the boilerplate project to bootstrap a new application quickly:

```txt
https://github.com/viames/pair_boilerplate
```

## Documentation

Main documentation lives in the Wiki:

```txt
https://github.com/viames/pair/wiki
```

Useful pages:

- [Application](https://github.com/viames/pair/wiki/Application)
- [Router](https://github.com/viames/pair/wiki/Router)
- [Controller](https://github.com/viames/pair/wiki/Controller)
- [View](https://github.com/viames/pair/wiki/View)
- [ActiveRecord](https://github.com/viames/pair/wiki/ActiveRecord)
- [ApiExposable](https://github.com/viames/pair/wiki/ApiExposable)
- [CrudController](https://github.com/viames/pair/wiki/CrudController)
- [Form](https://github.com/viames/pair/wiki/Form)
- [Collection](https://github.com/viames/pair/wiki/Collection)
- [Push notifications](https://github.com/viames/pair/wiki/Push-notifications)
- [PairUI.js](https://github.com/viames/pair/wiki/PairUI.js)
- [Configuration (.env)](https://github.com/viames/pair/wiki/Configuration-file)
- [index.php](https://github.com/viames/pair/wiki/index)
- [.htaccess](https://github.com/viames/pair/wiki/htaccess)
- [Classes folder](https://github.com/viames/pair/wiki/Classes-folder)

## Development

Install dependencies:

```sh
composer install
```

Run tests:

```sh
composer test
```

Run the v4 benchmark harness:

```sh
composer run benchmark-v4
```

The benchmark harness measures:

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
- Packagist: [packagist.org/packages/viames/pair](https://packagist.org/packages/viames/pair)

## Changelog

Version history is available in GitHub Releases:

```txt
https://github.com/viames/pair/releases
```

## Security

If you discover a security issue, follow the private reporting guidance in [SECURITY.md](SECURITY.md).

## Contributing

Feedback, code contributions and documentation improvements are welcome via pull request.

## License

MIT