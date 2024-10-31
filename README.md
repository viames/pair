<img src="https://github.com/viames/Pair/wiki/files/pair-logo.png" width="240">

[![Latest Stable Version](https://poser.pugx.org/viames/pair/v/stable)](https://packagist.org/packages/viames/pair)
[![Total Downloads](https://poser.pugx.org/viames/pair/downloads)](https://packagist.org/packages/viames/pair)
[![Latest Unstable Version](https://poser.pugx.org/viames/pair/v/unstable)](https://packagist.org/packages/viames/pair)
[![License](https://poser.pugx.org/viames/pair/license)](https://packagist.org/packages/viames/pair)

## Features

Pair is a simple and fast PHP framework with little or no frills. It was written with simplicity in mind, trying to satisfy the most frequent needs of web applications. It implements the [Model-View-Controller](https://en.wikipedia.org/wiki/Model-View-Controller) pattern and intuitive, search engine friendly [routing](https://github.com/viames/pair/wiki/Router) by default.

Pair is a simple and fast PHP framework with little or no frills. It was written with simplicity in mind, trying to satisfy the most frequent needs of web applications. It implements the Model-View-Controller pattern and intuitive routing logic by default.

Everyone knows that you don't need a truck to go grocery shopping. You don't even need a car to go to the local newsstand to buy the newspaper. You need the right vehicle for every occasion.

If starting a new web project is a hassle because of the complexity and slowness of the famous pachyderm frameworks used around, you should take a look at Pair. For a small or medium web project, it might surprise you.

Pair learns the referential constraints of database tables and automatically uses magic functions to reuse the data already read via cache. While Pair sends the complete web page to the browser, some of the most popular frameworks still have to load all the server-side libraries.

Additionally using Pair's advanced features for efficiently managing information already extracted from the DB, you can create a web page of an application that reads millions of records and numerous related tables in the incredible time of just 10 ms.

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

## Documentation

Please consult the [Wiki](https://github.com/viames/pair/wiki) of this project. Below are its most interesting pages that illustrate some features of Pair.

* [ActiveRecord](https://github.com/viames/pair/wiki/ActiveRecord)
* [Application](https://github.com/viames/pair/wiki/Application)
* [Controller](https://github.com/viames/pair/wiki/Controller)
* [Form](https://github.com/viames/pair/wiki/Form)
* [Router](https://github.com/viames/pair/wiki/Router)
* [index.php](https://github.com/viames/pair/wiki/index)
* [.htaccess](https://github.com/viames/pair/wiki/htaccess)
* [config.php](https://github.com/viames/pair/wiki/Configuration-file)
* [classes](https://github.com/viames/pair/wiki/Classes-folder)

## Requirements

| Software | Recommended | Minimum | Configuration          |
| ---      |    :---:    |  :---:  | ---                    |
| Apache   | 2.4+        | 2.2     | `modules:` mod_rewrite |
| MySQL    | 8.5         | 8.0     | `character_set:` utf8mb4 <br> `collation:` utf8mb4\_unicode_ci <br> `storage_engine:` InnoDB |
| PHP      | 8.3+        | 8.0     | `extensions:` fileinfo, json, pcre, PDO, pdo_mysql, Reflection |

## Examples

The [pair_boilerplate](https://github.com/viames/pair_boilerplate) is a good starting point to build your new web project in a breeze with Pair PHP framework using the installer wizard.

## Contributing

If you would like to contribute to this project, please feel free to submit a pull request.

# Migration to Pair v2 from Pair v1.9

Some classes and methods have been moved or renamed in Pair 2.0. Apply renaming as shown below to update your code.

#### Namespace changes
```php
<?php

/**
 * The old code valid for Pair v1.9 is commented, rename for Pair v2.0 is below.
 */

// Core classes
use Pair\Core\Application;      	// renamed from Pair\Application
use Pair\Core\Controller;       	// renamed from Pair\Controller
use Pair\Core\Model;            	// renamed from Pair\Model
use Pair\Core\Router;           	// renamed from Pair\Router
use Pair\Core\View;             	// renamed from Pair\View

// Html classes
use Pair\Html\BootstrapMenu;    	// renamed from Pair\BootstrapMenu
use Pair\Html\Breadcrumb;       	// renamed from Pair\Breadcrumb
use Pair\Html\Form;             	// renamed from Pair\Form
use Pair\Html\Menu;             	// renamed from Pair\Menu
use Pair\Html\Pagination;       	// renamed from Pair\Pagination
use Pair\Html\Widget;           	// renamed from Pair\Widget

// Models classes
use Pair\Model\Acl;                     // renamed from Pair\Acl
use Pair\Model\Audit;                   // renamed from Pair\Audit
use Pair\Model\Country;                 // renamed from Pair\Country
use Pair\Model\ErrorLog;                // renamed from Pair\ErrorLog
use Pair\Model\Group;                   // renamed from Pair\Group
use Pair\Model\Language;                // renamed from Pair\Language
use Pair\Model\Locale;                  // renamed from Pair\Locale
use Pair\Model\Module;                  // renamed from Pair\Module
use Pair\Model\Oauth2Client;		// renamed from Pair\Oauth\Oauth2Client
use Pair\Model\Oauth2Token;             // renamed from Pair\Oauth\Oauth2Token
use Pair\Model\Rule;			// renamed from Pair\Rule
use Pair\Model\Session;                 // renamed from Pair\Session
use Pair\Model\Template;                // renamed from Pair\Template
use Pair\Model\Token;                   // renamed from Pair\Token
use Pair\Model\User;                    // renamed from Pair\User
use Pair\Model\UserRemember;            // renamed from Pair\UserRemember

// Orm classes
use Pair\Orm\ActiveRecord;      	// renamed from Pair\ActiveRecord
use Pair\Orm\Database;          	// renamed from Pair\Database

// Services classes
use Pair\Services\AmazonS3;      	// renamed from Pair\AmazonS3
use Pair\Services\Report;        	// renamed from Pair\Report

// Support classes
use Pair\Support\Logger;        	// renamed from Pair\Logger
use Pair\Support\Plugin;        	// renamed from Pair\Plugin
use Pair\Support\PluginInterface;	// renamed from Pair\PluginInterface
use Pair\Support\Post; 	        	// renamed from Pair\Input
use Pair\Support\Options;       	// renamed from Pair\Options
use Pair\Support\Schedule;      	// renamed from Pair\Schedule
use Pair\Support\Translator;    	// renamed from Pair\Translator
use Pair\Support\Upload;        	// renamed from Pair\Upload
use Pair\Support\Utilities;     	// renamed from Pair\Utilities
```

#### Menu widget changes

```php
<?php

// Menu widget
$menu = new BootstrapMenu();
$menu->item();		    // renamed from $menu->addItem()
$menu->separator();		// renamed from $menu->addSeparator()
$menu->title();		    // renamed from $menu->addTitle()
$menu->multiItem();		// faster creation of a list of menu sub-items
$menu->addMulti();		// removed
$menu->getItemObject();	// removed
```

#### JSON utilities changes

```php
<?php

// JS methods that return JSON to the client
Utilities::pairJsonMessage();   // renamed from Utilities::printJsonMessage()
Utilities::pairJsonError();     // renamed from Utilities::printJsonError()
Utilities::pairJsonData();      // renamed from Utilities::printJsonData()
```

### Input class renamed to Post

Input class has been renamed to Post. Update your code accordingly.

```php
<?php

Post::get();		// renamed from Input::get()
Post::int();		// renamed from Input::getInt()
Post::bool();		// renamed from Input::getBool()
Post::date();		// renamed from Input::getDate()
Post::datetime();	// renamed from Input::getDatetime()
Post::trim();		// renamed from Input::getTrim()
Post::sent();		// renamed from Input::isSent()
Post::submitted();	// renamed from Input::formPostSubmitted()
```

### Form

Methods `setListByAssociativeArray()` and `setListByObjectArray()` of the `FormControlSelect` class have been replaced by `options()`.

The `options()` method populates select control options using a `Pair\Collection` or an object array. Each object must have properties for value and text. If property text includes a couple of round parenthesys, will invoke a function without parameters. It’s a chainable method.

```php
<?php

$form = new Form();

$form->select('controlName')    // renamed from $form->addSelect()
     ->options($collection)     // renamed from $form->setListByObjectArray()
     ->value()                  // renamed from $form->setValue()
     ->multiple();              // renamed from $form->setMultiple()
     ->empty();                 // renamed from $form->prependEmpty()
```

#### Methods for creating form controls

Molti metodi per la creazione dei FormControl sono stati rinominati o estesi per essere più specifici del tipo di controllo che creano. Si prega di rinominare i metodi come indicato di seguito.

```php
<?php

$form = new Form();

$form->text('controlName')      // renamed from $form->addInput()
     ->readonly();			    // renamed from $form->setReadonly()
     ->disabled();			    // renamed from $form->setDisabled()
     ->required();			    // renamed from $form->setRequired()
     ->placeholder();	        // renamed from $form->setPlaceholder()
     ->label('CONTROL_LABEL');  // renamed from $form->setLabel()

$form->textarea('controlName');	// renamed from $form->addTextarea()
$form->button('controlName');   // renamed from $form->addButton()
$form->values($activeRecordObj);// renamed from $form->setValuesByObject()
$form->classForControls();		// renamed from $form->addControlClass()
$form->control();       		// renamed from $form->getControl()
$form->controls();              // renamed from $form->getAllControls()
```

New methods for creating form controls have been added to the `Form` class. These methods are more specific to the type of control they create. Update your application code accordingly to use these new methods because `FormControl` class no longer has `setType()` method.

```php
<?php

$form->address();	// changed from $form->addInput()->setType('address')
$form->checkbox();	// changed from $form->addInput()->setType('bool')
$form->color();		// changed from $form->addInput()->setType('color')
$form->date();		// changed from $form->addInput()->setType('date')
$form->datetime();	// changed from $form->addInput()->setType('datetime')
$form->email();		// changed from $form->addInput()->setType('email')
$form->file();		// changed from $form->addInput()->setType('file')
$form->hidden();	// changed from $form->addInput()->setType('hidden')
$form->image();		// changed from $form->addInput()->setType('image')
$form->number();	// changed from $form->addInput()->setType('number')
$form->password();	// changed from $form->addInput()->setType('password')
$form->tel();		// changed from $form->addInput()->setType('tel')
$form->url();		// changed from $form->addInput()->setType('url')
```

#### Labels

The behavior of the `Form::printLabel()` method has changed; this method now also generates the `<label>` tag around the label text itself. Update your application code accordingly to remove the `<label>` tag from the HTML, to avoid having a duplicate tag.

The CSS class of the label can be specified individually, for each label, with `FormControl::labelClass()` method or massively, with the `Form::classForLabels('form-label')` method.
See the example below.

```php
<?php

$form = new Form();

$form->text('serialNumber')			    // return a FormControl subclass
     ->label('Serial number')			// set the label text and return the FormControl object
     ->labelClass('my-label-class');    // set the class for the label of this FormControl

// or apply to all labels in the Form as follows

$form->classForLabels('form-label');	// set the same class for all labels in all HTML controls in the Form
```

Example of the old code and the new code in template file:

```php
<?php

// old code
?><label class="col-md-3 col-form-label"><?php $this->form->printLabel('serialNumber') ?></label><?php

// new code
?><div class="col-md-3"><?php $this->form->printLabel('serialNumber') ?></div><?php

```

# License

MIT
