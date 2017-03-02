<?php 

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Application;
use Pair\Translator;

$app = Application::getInstance();
$translator	= Translator::getInstance();

require 'classes/BootstrapMenu.php';
$menu = new BootstrapMenu();

$menu->addItem(NULL, PRODUCT_NAME, NULL, 'sidebar-brand');
$menu->addItem('users', $translator->translate('USERS'), NULL, 'fa-user');
$menu->addItem('users/groupList', $translator->translate('GROUPS'), NULL, 'fa-users');
$menu->addItem('options/default', $translator->translate('OPTIONS'), NULL, 'fa-sliders');
$menu->addItem('selftest/default', $translator->translate('SELF_TEST'), NULL, 'fa-check-square-o');
$menu->addItem('languages/default', $translator->translate('LANGUAGES'), NULL, 'fa-globe');
$menu->addItem('modules/default', 'Moduli', NULL, 'fa-puzzle-piece');
$menu->addItem('templates/default', 'Template', NULL, 'fa-puzzle-piece');
$menu->addItem('developer/default', $translator->translate('DEVELOPER'), NULL, 'fa-magic');
$menu->addItem('user/logout', $translator->translate('LOGOUT'), NULL, 'fa-sign-out');

print $menu->render();
