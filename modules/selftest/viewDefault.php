<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Database;
use Pair\Options;
use Pair\View;
use Pair\Widget;

class SelftestViewDefault extends View {

	public function render() {

		$db = Database::getInstance();
		$this->app->pageTitle = $this->lang('SELF_TEST');

		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');

		// will needs some options
		$options = Options::getInstance();
		
		// starts the test
		$test = new SelfTest();
		
		// apache version and config
		$label = $this->lang('TEST_APACHE_CONFIGURATION');
		$result = $this->model->testApache();
		$test->assertTrue($label, $result, $this->lang('SERVER'));
		
		// php version and config
		$label = $this->lang('TEST_PHP_CONFIGURATION', phpversion());
		$result = $this->model->testPhp();
		$test->assertTrue($label, $result, $this->lang('SERVER'));
		
		// mysql version
		$label = $this->lang('TEST_MYSQL_VERSION', $db->getMysqlVersion());
		$result = $this->model->testMysql();
		$test->assertTrue($label, $result, $this->lang('SERVER'));
		
		// check configuration file
		$label = $this->lang('TEST_CONFIG_FILE');
		$result = $this->model->testConfigFile();
		$test->assertTrue($label, $result, $this->lang('SERVER'));
		
		// test folder permissions
		$label = $this->lang('TEST_FOLDERS');
		$result = $this->model->testFolders();
		$test->assertTrue($label, $result, $this->lang('APPLICATION'));

		// ActiveRecord for multiple classes
		$classes = $this->model->getActiveRecordClasses();
		$errors  = 0;
		
		foreach ($classes as $class) {
			$obj = new $class;
			$errors	+= $obj->selfTest();
		}
		
		$label = $errors ? $this->lang('ACTIVE_RECORDS_CLASSES_ERRORS', $errors) : $this->lang('TEST_ACTIVE_RECORDS_CLASSES');
		$test->assertIsZero($label, $errors, $this->lang('APPLICATION'));
		
		// scan all language files
		$unfound = $this->model->testLanguages();
		
		// unfound folder
		$label = $unfound['folders'] ? $this->lang('LANGUAGE_FOLDERS_NOT_FOUND', $unfound['folders']) : $this->lang('TEST_LANGUAGE_FOLDERS'); 
		$test->assertIsZero($label, $unfound['folders'], $this->lang('APPLICATION'));
		
		/*
		// unfound files
		$label = $unfound['files'] ? $this->lang('LANGUAGE_FILES_NOT_FOUND', $unfound['files']) : $this->lang('TEST_LANGUAGE_FILES');
		$test->assertIsZero($label, $unfound['files'], $this->lang('APPLICATION'));
		
		// double test on language lines
		if (!$unfound['lines'] and !$unfound['notNeeded']) {

			$label = $this->lang('TEST_LANGUAGE_LINES');
			$test->assertTrue($label, TRUE, $this->lang('APPLICATION'));

		} else {
		
			// untranslated language lines
			if ($unfound['lines']) {
				$label = $this->lang('UNTRANSLATED_LANGUAGE_LINES', $unfound['lines']);
				$test->assertIsZero($label, $unfound['lines'], $this->lang('APPLICATION'));
			}
			
			// lines not needed for this language
			if ($unfound['notNeeded']) {
				$label = $this->lang('UNNEEDED_LANGUAGE_LINES', $unfound['notNeeded']);
				$test->assertIsZero($label, $unfound['notNeeded'], $this->lang('APPLICATION'));
			}
			
		}
		*/

		// test plugins
		$label = $this->lang('TEST_PLUGINS');
		$result = $this->model->checkPlugins();
		$test->assertTrue($label, $result, $this->lang('APPLICATION'));
		
		$iconMark	= '<i class="fa fa-check-square-o"></i>';
		$iconCross	= '<i class="fa fa-square-o"></i>';
		
		$this->assign('iconMark',	$iconMark);
		$this->assign('iconCross',	$iconCross);
		$this->assign('sections',	$test->list);
		
	}
	
}
