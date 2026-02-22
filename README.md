<img src="https://github.com/viames/Pair/wiki/files/pair-logo.png" width="240">

[![Latest Stable Version](https://poser.pugx.org/viames/pair/v/stable)](https://packagist.org/packages/viames/pair)
[![Total Downloads](https://poser.pugx.org/viames/pair/downloads)](https://packagist.org/packages/viames/pair)
[![Latest Unstable Version](https://poser.pugx.org/viames/pair/v/unstable)](https://packagist.org/packages/viames/pair)
[![License](https://poser.pugx.org/viames/pair/license)](https://packagist.org/packages/viames/pair)
[![PHP Version Require](https://poser.pugx.org/viames/pair/require/php)](https://packagist.org/packages/viames/pair)

## What’s new

Welcome to Pair version 3 alpha. This is a major release that breaks compatibility with previous versions.

## Features

Pair is a simple and fast PHP framework with little or no frills. It was written with simplicity in mind, trying to satisfy the most frequent needs of web applications. It implements the [Model-View-Controller](https://en.wikipedia.org/wiki/Model-View-Controller) pattern and intuitive, search engine friendly [routing](https://github.com/viames/pair/wiki/Router) by default.

Everyone knows that you don’t need a truck to go grocery shopping. You don't even need a car to go to the local newsstand to buy the newspaper. You need the right vehicle for every occasion.

If starting a new web project feels heavy because of the complexity and slowness of larger frameworks, you should take a look at Pair. For a small or medium web project, it might surprise you.

Pair learns the referential constraints of database tables and automatically uses magic functions to reuse the data already read via cache. Pair sends complete pages to the browser while keeping server-side overhead low.

Additionally, by using Pair's advanced features for efficiently managing information already extracted from the DB, you can create pages that read millions of records and numerous related tables in a few milliseconds, depending on data, cache, and infrastructure.

Pair supports third-party products and services to make it easy to create lightning-fast server-side web applications, including: `Amazon S3`, `Amazon SES`, `Chart.js`, `ELK logger`, `InsightHub`, `Sentry`, `Telegram`.

### ActiveRecord

Pair allows the creation of objects related to each respective database table using the [ActiveRecord class](https://github.com/viames/pair/wiki/ActiveRecord). Objects retrieved from the DB are cast in both directions to the required type (int, bool, DateTime, float, csv). See [Automatic properties cast](https://github.com/viames/pair/wiki/ActiveRecord#automatic-properties-cast) page in the wiki.

In addition, each class inherited from ActiveRecord supports many convenient methods including those for caching data that save queries.

The Pair base tables use InnoDB with utf8mb4.

### Plugins

Pair supports modules and templates as installable plugins, but can easily be extended to other types of custom plugins. The Pair’s Plugin class allows you to create the manifest file, the ZIP package with the contents of the plugin and the installation of the plugin of your Pair’s application.

### Time zone

The automatic time zone management allows you to store data in UTC and return it converted according to the user's time zone automatically.

### Log bar

A log bar shows details about loaded objects, memory usage, timing for each step and query, the SQL code of executed queries, and backtraces for detected errors. Custom messages can be added for each step of the code.

### PairUI (frontend helpers)

Pair includes a small, dependency-free helper for server-rendered apps at `vendor/viames/pair/assets/PairUI.js`. It provides a minimal reactive store, safe `data-*` directives (no `eval`), and convenience utilities for progressive enhancement without a build step.

Main directives:
- `data-text`, `data-html`, `data-show`, `data-if`
- `data-class`, `data-attr`, `data-prop`, `data-style`
- `data-model` (two-way binding)
- `data-on` (event handlers with safe arguments)
- `data-each` (list rendering with `item`/`index` scope)

Also included: a tiny plugin system, `PairUI.http` helpers, and `PairUI.createApp()` for quick setup.

### Pair PWA helpers (no build step)

Pair includes standalone PWA-oriented frontend files under `vendor/viames/pair/assets/`, designed for direct usage with script tags and no compilation:

- `PairPWA.js`: service worker bootstrap, lifecycle events, online/offline helpers, background refresh.
- `PairSW.js`: default service worker with app-shell precache, runtime caching and offline fallback.
- `PairRouter.js`: progressive navigation and History API routing for server-rendered pages.
- `PairSkeleton.js`: loading skeleton toggles using `data-skeleton` markers.
- `PairDevice.js`: safe wrappers for camera, bluetooth, geolocation, permissions and vibration.

Backend quick start (Pair framework):

```php
use Pair\Helpers\PwaConfig;
use Pair\Helpers\PwaManifest;

// generate a manifest file once (for example during setup/deploy)
PwaManifest::write(APPLICATION_PATH . '/public/manifest.webmanifest', [
	'name' => 'My Pair App',
	'short_name' => 'PairApp',
	'start_url' => '/',
	'scope' => '/',
	'theme_color' => '#1b6ec2',
	'background_color' => '#ffffff',
]);

// in your controller/layout bootstrap
$this->loadManifest('/manifest.webmanifest');
$this->loadPwaScripts('/assets', true, true); // includes PairUI and PairPush too

// optional: centralized SW runtime policy
$swUrl = PwaConfig::buildServiceWorkerUrl('/assets/PairSW.js', [
	'offlineFallback' => '/offline.html',
	'cache' => [
		'pageStrategy' => 'network-first',
		'apiStrategy' => 'network-first',
		'assetStrategy' => 'stale-while-revalidate',
		'maxRuntimeEntries' => 400,
		'maxRuntimeAgeSeconds' => 604800,
	],
	'sync' => [
		'maxQueueEntries' => 300,
		'maxBodyBytes' => 262144,
		'maxAttempts' => 6,
	],
]);
```

Frontend quick start:

```html
<script src="/assets/PairUI.js" defer></script>
<script src="/assets/PairPWA.js" defer></script>
<script src="/assets/PairRouter.js" defer></script>
<script src="/assets/PairSkeleton.js" defer></script>
<script src="/assets/PairDevice.js" defer></script>
```

```html
<script>
document.addEventListener("DOMContentLoaded", async () => {
	await PairPWA.init({
		swUrl: "/assets/PairSW.js",
		scope: "/",
		swOfflineFallback: "/offline.html",
		serviceWorkerConfig: {
			cache: {
				pageStrategy: "network-first",
				apiStrategy: "network-first",
				assetStrategy: "stale-while-revalidate",
				maxRuntimeEntries: 400
			},
			sync: {
				maxQueueEntries: 300,
				maxAttempts: 6
			}
		},
		reloadOnControllerChange: true
	});

	PairRouter.start({
		viewSelector: "[data-pair-router-view]"
	});

	PairSkeleton.autoBind({ withStyles: true });

	// optional: install prompt support
	window.addEventListener("pair:pwa:install-available", async () => {
		// show your custom button/modal, then:
		// await PairPWA.promptInstall();
	});
});
</script>
```

Queue writes while offline (background sync):

```js
await PairPWA.fetchWithQueue("/api/orders/save", {
	method: "POST",
	headers: {
		"Content-Type": "application/json",
		"X-Pair-Background-Sync": "1"
	},
	body: JSON.stringify({ orderId: 123 })
});
```

Backend idempotency guard for replayed/offline requests:

```php
use Pair\Api\Idempotency;
use Pair\Api\ApiResponse;

// at the beginning of a mutating endpoint
Idempotency::respondIfDuplicate($this->request, 'orders:create');

$result = ['orderId' => 123, 'saved' => true];

// store canonical response for same idempotency key
Idempotency::storeResponse($this->request, 'orders:create', $result, 201);
ApiResponse::respond($result, 201);
```

Important notes:
- Keep progressive enhancement: pages must still work without advanced browser APIs.
- Keep your web app manifest linked in page head.
- Service workers require HTTPS (except localhost).
- For custom offline pages, use `swOfflineFallback` or PwaConfig `offlineFallback`.
- If you use `PairPush`, register the same service worker URL to avoid multiple workers.

### Routing basics (Pair apps)

In Pair applications, the default routing format (after the base path) is:

`/<module>/<action>/<params...>`

Example: `example.com/user/login`

- module: `/modules/user`
- controller: `/modules/user/controller.php` (extends `Pair/Core/Controller.php`)
- action: `loginAction()` when present
- auto-loaded: `model.php`, view `viewLogin.php` (class `UserViewLogin`), and layout `/modules/user/layouts/login.php`

The remaining segments are treated as parameters (e.g. resource IDs).

## Installation

### Composer

```sh
composer require viames/pair
```
After installing the Pair framework you can get the singleton `$app` and start MVC. You can check any session before MVC, like in the following example.

```php
use Pair\Core\Application;

// initialize composer
require 'vendor/autoload.php';

// initialize the Application
$app = Application::getInstance();

// start controller and then display
$app->run();
```

If you want to test code that is in the main branch, which hasn’t been pushed as a release, you can use `dev-main`.

```
composer require viames/pair dev-main
```
If you don’t have Composer, you can [download it](https://getcomposer.org/download/).

## Upgrading

If you are upgrading between major versions, Pair provides helper scripts via Composer:

```sh
composer run upgrade-to-v2
composer run upgrade-to-v3
```

## Documentation

Please consult the [Wiki](https://github.com/viames/pair/wiki) of this project. Below are its most interesting pages that illustrate some features of Pair.

* [ActiveRecord](https://github.com/viames/pair/wiki/ActiveRecord)
* [Application](https://github.com/viames/pair/wiki/Application)
* [Collection](https://github.com/viames/pair/wiki/Collection)
* [Controller](https://github.com/viames/pair/wiki/Controller)
* [Form](https://github.com/viames/pair/wiki/Form)
* [Router](https://github.com/viames/pair/wiki/Router)
* [View](https://github.com/viames/pair/wiki/View)
* [PairUI](https://github.com/viames/pair/wiki/PairUI.js)
* [PairPush](https://github.com/viames/pair/wiki/Push-notifications)
* [index.php](https://github.com/viames/pair/wiki/index)
* [.htaccess](https://github.com/viames/pair/wiki/htaccess)
* [.env](https://github.com/viames/pair/wiki/Configuration-file)
* [classes](https://github.com/viames/pair/wiki/Classes-folder)

## Requirements

| Software | Recommended | Minimum | Configuration          |
| ---      |    :---:    |  :---:  | ---                    |
| Apache   | 2.4+        | 2.2     | `modules:` mod_rewrite |
| MySQL    | 8.0+        | 8.0     | `character_set:` utf8mb4 <br> `collation:` utf8mb4\_unicode_ci <br> `storage_engine:` InnoDB |
| PHP      | 8.4         | 8.3     | `extensions:` curl, fileinfo, intl, json, mbstring, PDO, pdo_mysql |

## Examples

The [pair_boilerplate](https://github.com/viames/pair_boilerplate) is a good starting point to build your new web project in a breeze with Pair PHP framework using the installer wizard.

## Support

- Issues: https://github.com/viames/pair/issues
- Wiki: https://github.com/viames/pair/wiki
- Source: https://github.com/viames/pair/tree/master/src
- Homepage: https://viames.github.io/pair/

## Changelog

See the GitHub releases page for version history: https://github.com/viames/pair/releases

## Security

If you discover a security issue, please open a GitHub issue with a clear description and steps to reproduce.

## Contributing

Feedback, code integration, and documentation adjustments are welcome.

If you would like to contribute to this project, please feel free to submit a pull request.

## License

MIT
