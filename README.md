# Pair
light weight and versatile PHP framework

[![Latest Stable Version](https://poser.pugx.org/viames/pair/v/stable)](https://packagist.org/packages/viames/pair)
[![Latest Unstable Version](https://poser.pugx.org/viames/pair/v/unstable)](https://packagist.org/packages/viames/pair)
[![License](https://poser.pugx.org/viames/pair/license)](https://packagist.org/packages/viames/pair)
[![composer.lock](https://poser.pugx.org/viames/pair/composerlock)](https://packagist.org/packages/viames/pair)

## Features
The framework implements [Model-View-Controller](https://en.wikipedia.org/wiki/Model-View-Controller) pattern, the [Active Record](https://en.wikipedia.org/wiki/Active_record_pattern) pattern and a search friendly route logic.

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

### Don’t have Composer?

You can download it here: [https://getcomposer.org/](https://getcomposer.org/)

## Examples

An example project that uses Pair PHP framework can be found [here](https://github.com/viames/Pair_example).

## Contributing

If you would like to contribute to this project, please feel free to submit a pull request.

# License

MIT
