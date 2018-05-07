# Pair

light weight and versatile PHP framework

[![Latest Stable Version](https://poser.pugx.org/viames/pair/v/stable)](https://packagist.org/packages/viames/pair)
[![Total Downloads](https://poser.pugx.org/viames/pair/downloads)](https://packagist.org/packages/viames/pair)
[![Latest Unstable Version](https://poser.pugx.org/viames/pair/v/unstable)](https://packagist.org/packages/viames/pair)
[![License](https://poser.pugx.org/viames/pair/license)](https://packagist.org/packages/viames/pair)
[![composer.lock](https://poser.pugx.org/viames/pair/composerlock)](https://packagist.org/packages/viames/pair)

## Features

Pair is simple and fast, few frills, maybe none. It was written with simplicity in mind, while trying to achieve the most frequent needs of web applications. It implements [Model-View-Controller](https://en.wikipedia.org/wiki/Model-View-Controller) pattern and a search friendly route logic.

#### ActiveRecord

Pair connects to MySQL DBMS with an ORM based on [Active Record](https://en.wikipedia.org/wiki/Active_record_pattern) pattern. It does not require the configuration of XML files. Objects retrieved from the DB are cast in both directions to the required type (int, bool, DateTime, float, csv).

In addition, each class inherited from ActiveRecord supports many convenient methods including those for managing the internal cache for saving data that saves queries.

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
use Pair\Application;

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
composer require viames/pair dev-master
```
If you don’t have Composer, you can [download it](https://getcomposer.org/download/).

## Requirements

| Software | Recommended | Minimum | Configuration          |
| ---      |    :---:    |  :---:  | ---                    |
| Apache   | 2.4+        | 2.2     | `modules:` mod_rewrite |
| MySQL    | 5.7+        | 5.6     | `character_set:` utf8mb4 <br> `collation:` utf8mb4\_unicode_ci <br> `storage_engine:` InnoDB |
| PHP      | 7+          | 5.6     | `extensions:` curl, fileinfo, gd, json, mcrypt, openssl, pcre, PDO, pdo_mysql, Reflection |

## Examples

The [Pair_example](https://github.com/viames/Pair_example) is a good starting point to build your new web project in a breeze with Pair PHP framework using the installer wizard.

## Contributing

If you would like to contribute to this project, please feel free to submit a pull request.

# License

MIT
