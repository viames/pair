<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Application;

// initialize the framework
require 'vendor/pair/loader.php';

// declare product version
define ('PRODUCT_VERSION', '1.0');

// start the Application
$app = Application::getInstance();

// any API requests
$app->runApi('api');

// any session
$app->manageSession();

// CSS
//$app->loadCss($app->templatePath . '[path_to_your_css_file]');

// javascripts
//$app->loadJs('[path_to_your_js_file]');
//$app->loadJs('http://maps.googleapis.com/maps/api/js?libraries=places');

// start controller and then display
$app->startMvc();