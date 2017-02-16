<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	VMS
 */

use VMS\Application;
use VMS\Database;
use VMS\Logger;
use VMS\Options;
use VMS\Router;
use VMS\Session;
use VMS\Translator;
use VMS\User;
use VMS\Utilities;

// product version
define ('PRODUCT_VERSION',	'1.0');
define ('PRODUCT_BUILD',	'1');
define ('PRODUCT_DATE',		'2017-02-16 00:00:00Z');

// initialize the framework
require 'vendor/vms/index.php';

// global singleton objects
$app     = Application::getInstance();
$options = Options::getInstance();
$logger	 = Logger::getInstance();

// set extended utf8
$db = Database::getInstance();
$db->setUtf8unicode();

// print product name and version in the log
$logger->addEvent(PRODUCT_NAME . ' ' . PRODUCT_VERSION . ' build ' . PRODUCT_BUILD . ' (' . PRODUCT_DATE . ')');

// routing initialization
$route = Router::getInstance();
$route->setDefaults('basemodule', 'default');
$route->url = $_SERVER['REQUEST_URI'];

// config module for language
$tran = Translator::getInstance();

// session time length in minutes
$sessionTime = $options->getValue('session_time');

// get existing previous session
$session = new Session(session_id());

// session exists but expired
if ($session->isLoaded() and $session->isExpired($sessionTime)) {

	$comment = $tran->translate('USER_SESSION_EXPIRED');
	
	// sends js message about session expired
	if ($route->isRaw()) {

		Utilities::printJsonError($comment);
		exit();
		
	// redirects to login page
	} else {
	
		// new empty session
		$session = new Session();
	
		// page coming from
		if (array_key_exists('HTTP_REFERER',$_SERVER)) {
			$app->setState('referer', $_SERVER['HTTP_REFERER']);
			$logger->addEvent('Referer: ' . $_SERVER['HTTP_REFERER']);
		}
	
		// message to user
		$app->enqueueMessage($comment);

	}
	
}

// clean all old sessions
Session::cleanOlderThan($sessionTime);

// sets an empty user object
$app->setCurrentUser(new User());
	
// user is not logged in
if (!$session->isLoaded()) {
	
	if (isset($_SERVER['HTTP_REFERER'])) {
		$app->setState('referer', $_SERVER['HTTP_REFERER']);
	}

	// redirect to login page
	if (!('user'==$route->module and 'login'==$route->action)) {
		$app->redirect('user/login');
	}

} else {
	
	// if session exists, extend session timeout
	$session->extendTimeout();
	
	// create User object
	$user = new User($session->idUser);
	$app->setCurrentUser($user);
    
	$logger->addEvent('User session for ' . $user->fullName . ' is alive' .
			', user time zone is ' . $app->currentUser->tzName .
			' (' . sprintf('%+06.2f', (float)$app->currentUser->tzOffset) . ')');

	$resource = $route->module . '/' . $route->action;
	
	// checking permission
	if ($app->currentUser->canAccess($route->module, $route->action)) {
	
		// access granted
		$logger->addEvent('Access granted on resource ' . $resource);
		
	} else {

		// access denied
		$app->enqueueError($tran->translate('ACCESS_FORBIDDEN', $resource));
		$app->redirect($route->defaults['module'] . '/' . $route->defaults['action']);
		
	}
	
}

$controllerFile = 'modules/' . $route->module . '/controller.php';

// controller file was not found
if (!file_exists($controllerFile)) {
	
	$app->enqueueError($tran->translate('PAGE_NOT_FOUND', $route->module . '/' . $route->action));
	$app->redirect($route->defaults['module'] . '/' . $route->defaults['action']);
	
}

// path to the required form
define('MODULE_PATH', 'modules/'. $route->module .'/');

require ($controllerFile);

// start controller
$controllerName = ucfirst($route->module) . 'Controller';
$action = $route->action ? $route->action . 'Action' : 'defaultAction';
$logger->addEvent('Starting controller method ' . $controllerName . '->' . $action . '()');
$controller = new $controllerName();
$controller->$action();

// raw calls will jumps controller->display, ob and log
if (!$route->isRaw()) {
	
	// CSS
	//$app->loadCss($app->templatePath . '[path_to_your_css_file]');

	// javascripts
	//$app->loadJs('[path_to_your_js_file]');
	//$app->loadJs('http://maps.googleapis.com/maps/api/js?libraries=places');

	// invokes the view
	$controller->display();

	// sets the event log
	$app->log = $logger->getEventList();

	// processes the template
	$app->renderTemplate();

}
