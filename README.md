<img src="https://github.com/viames/Pair/wiki/files/pair-logo.png" width="240">

[Website](https://viames.github.io/pair/) | [Wiki](https://github.com/viames/pair/wiki) | [Issues](https://github.com/viames/pair/issues)

[![Total Downloads](https://poser.pugx.org/viames/pair/downloads)](https://packagist.org/packages/viames/pair)
[![Latest Stable Version](https://poser.pugx.org/viames/pair/v/stable)](https://packagist.org/packages/viames/pair)
[![GitHub Release](https://img.shields.io/github/v/release/viames/pair)](https://github.com/viames/pair/releases)
[![License](https://poser.pugx.org/viames/pair/license)](https://packagist.org/packages/viames/pair)
[![PHP Version Require](https://poser.pugx.org/viames/pair/require/php)](https://packagist.org/packages/viames/pair)

Pair is a lightweight PHP framework for server-rendered web applications.
It focuses on fast setup, clear MVC routing, practical ORM features, and optional integrations (Aircall, S3, SES, Sentry, Telegram, Push, Passkey) without heavy tooling.

## What's New

Pair v3 is currently in alpha and includes breaking changes compared to previous major versions.

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

## Why Pair

- Small and fast for small/medium projects.
- MVC structure with SEO-friendly routing.
- ActiveRecord-style ORM with automatic type casting.
- Plugin-oriented architecture (modules/templates).
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
- controller: `/modules/user/controller.php` (extends `Pair/Core/Controller.php`)
- action: `loginAction()` when present
- auto-loaded by convention: `model.php`, `viewLogin.php` (`UserViewLogin`), and `/modules/user/layouts/login.php`

Docs: [Router](https://github.com/viames/pair/wiki/Router)

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
- [Aircall](https://developer.aircall.io/)
- [Amazon S3](https://aws.amazon.com/s3/)
- [Amazon SES](https://aws.amazon.com/ses/)
- [ELK Stack](https://www.elastic.co/what-is/elk-stack)
- [InsightHub](https://insighthub.smartbear.com/)
- [Sentry](https://sentry.io/)
- [Telegram Bot API](https://core.telegram.org/bots/api)
- [OneSignal](https://onesignal.com/)
- [Stripe](https://stripe.com/docs)

Configuration reference: [Configuration (.env)](https://github.com/viames/pair/wiki/Configuration-file)

## Upgrading

If you are upgrading between major versions:

```sh
composer run upgrade-to-v2
composer run upgrade-to-v3
```

To test unreleased code from `main`:

```sh
composer require viames/pair dev-main
```

## Documentation

Main docs live in the [Wiki](https://github.com/viames/pair/wiki).

Useful pages:
- [Application](https://github.com/viames/pair/wiki/Application)
- [Controller](https://github.com/viames/pair/wiki/Controller)
- [View](https://github.com/viames/pair/wiki/View)
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

## Support

- Issues: [github.com/viames/pair/issues](https://github.com/viames/pair/issues)
- Wiki: [github.com/viames/pair/wiki](https://github.com/viames/pair/wiki)
- Source: [github.com/viames/pair/tree/master/src](https://github.com/viames/pair/tree/master/src)
- Homepage: [viames.github.io/pair](https://viames.github.io/pair/)

## Changelog

Version history is available in GitHub Releases: [github.com/viames/pair/releases](https://github.com/viames/pair/releases)

## Security

If you discover a security issue, please open a GitHub issue with a clear description and steps to reproduce.

## Contributing

Feedback, code contributions, and documentation improvements are welcome via pull request.

## License

MIT
