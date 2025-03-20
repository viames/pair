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

Pair is a simple and fast PHP framework with little or no frills. It was written with simplicity in mind, trying to satisfy the most frequent needs of web applications. It implements the Model-View-Controller pattern and intuitive routing logic by default.

Everyone knows that you don’t need a truck to go grocery shopping. You don't even need a car to go to the local newsstand to buy the newspaper. You need the right vehicle for every occasion.

If starting a new web project is a hassle because of the complexity and slowness of the famous pachyderm frameworks used around, you should take a look at Pair. For a small or medium web project, it might surprise you.

Pair learns the referential constraints of database tables and automatically uses magic functions to reuse the data already read via cache. While Pair sends the complete web page to the browser, some of the most popular frameworks still have to load all the server-side libraries.

Additionally using Pair's advanced features for efficiently managing information already extracted from the DB, you can create a web page of an application that reads millions of records and numerous related tables in the incredible time of just 10 ms.

Pair supports third-party products and services to make it easy to create lightning-fast server-side web applications, including: `Amazon S3`, `Amazon SES`, `Chart.js`, `ELK logger`, `InsightHub`, `Sentry`, `Telegram`.

#### ActiveRecord

Pair allows the creation of objects related to each respective database table using the [ActiveRecord class](https://github.com/viames/pair/wiki/ActiveRecord). Objects retrieved from the DB are cast in both directions to the required type (int, bool, DateTime, float, csv). See [Automatic properties cast](https://github.com/viames/pair/wiki/ActiveRecord#automatic-properties-cast) page in the wiki.

In addition, each class inherited from ActiveRecord supports many convenient methods including those for caching data that save queries.

The Pair base tables are InnoDB utf-8mb4.

#### Plugins

Pair supports modules and templates as installable plugins, but can easily be extended to other types of custom plugins. The Pair’s Plugin class allows you to create the manifest file, the ZIP package with the contents of the plugin and the installation of the plugin of your Pair’s application.

#### Time zone

The automatic time zone management allows to store the data on UTC and to obtain it already converted according to the connected user’s time zone automatically.

#### Log bar

A nice log bar shows all the details of the loaded objects, the system memory load, the time taken for each step and for the queries, the SQL code of the executed queries and the backtrace of the detected errors. Custom messages can be added for each step of the code.

## Installation

### Composer

```sh
composer require viames/pair
```
After having installed Pair framework you can get singleton object `$app` and the just start MVC. You can check any session before MVC, like in the following example.

```php
use Pair\Core\Application;

// initialize the framework
require 'vendor/autoload.php';

// intialize the Application
$app = Application::getInstance();

// any session
$app->manageSession();

// start controller and then display
$app->startMvc();
```

If you want to test code that is in the master branch, which hasn’t been pushed as a release, you can use master.

```
composer require viames/pair dev-main
```
If you don’t have Composer, you can [download it](https://getcomposer.org/download/).

## Documentation

Please consult the [Wiki](https://github.com/viames/pair/wiki) of this project. Below are its most interesting pages that illustrate some features of Pair.

* [ActiveRecord](https://github.com/viames/pair/wiki/ActiveRecord)
* [Application](https://github.com/viames/pair/wiki/Application)
* [Collection](https://github.com/viames/pair/wiki/Collection)
* [Controller](https://github.com/viames/pair/wiki/Controller)
* [Form](https://github.com/viames/pair/wiki/Form)
* [Router](https://github.com/viames/pair/wiki/Router)
* [index.php](https://github.com/viames/pair/wiki/index)
* [.htaccess](https://github.com/viames/pair/wiki/htaccess)
* [.env](https://github.com/viames/pair/wiki/Configuration-file)
* [classes](https://github.com/viames/pair/wiki/Classes-folder)

## Requirements

| Software | Recommended | Minimum | Configuration          |
| ---      |    :---:    |  :---:  | ---                    |
| Apache   | 2.4+        | 2.2     | `modules:` mod_rewrite |
| MySQL    | 9.2         | 8.0     | `character_set:` utf8mb4 <br> `collation:` utf8mb4\_unicode_ci <br> `storage_engine:` InnoDB |
| PHP      | 8.3        | 8.3     | `extensions:` fileinfo, json, pcre, PDO, pdo_mysql, Reflection |

## Examples

The [pair_boilerplate](https://github.com/viames/pair_boilerplate) is a good starting point to build your new web project in a breeze with Pair PHP framework using the installer wizard.

## Contributing

Feedback, code integration, and documentation adjustments are welcome.

If you would like to contribute to this project, please feel free to submit a pull request.

## Update from Pair v1 to v2

Instructions and a script for upgrading applications from Pair 1 to Pair 2 are available in the file [UPGRADE.md](UPGRADE.md).

# License

MIT