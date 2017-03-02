<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Breadcrumb;
use Pair\Form;
use Pair\Options;
use Pair\View;
use Pair\Widget;

class OptionsViewDefault extends View {

	public function render() {

		$options = Options::getInstance();

		$this->app->pageTitle = $this->lang('OPTIONS');
		$this->app->activeMenuItem = 'options/default';

		$breadcrumb = Breadcrumb::getInstance();
		$breadcrumb->addPath($this->lang('OPTIONS'));

		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');

		$form = new Form();
		$form->addControlClass('form-control');
		
		$groupedOptions = array();
		
		foreach ($options->getAll() as $o) {
			
			$groupedOptions[$o->group][] = $o;
			
			// if uppercase, label is a language key
			if (preg_match('#^[A-Z\_]+$#', $o->label)) {
				$o->label = $this->lang($o->label);
			}
		
			switch ($o->type) {
		
				default:
				case 'text':
					$form->addInput($o->name)->setType('text')->setValue($o->value);
					break;
		
				case 'int':
					$form->addInput($o->name)->setType('number')->setValue($o->value);
					break;
		
				case 'bool':
					$form->addInput($o->name)->setType('bool')->addClass('icheck')->setValue($o->value);
					break;
				/*
				case 'list':
					//$o->field = Form::buildSelect($o->name, $o->listItems, 'value', 'text', $o->value);
					$form->addSelect($o->name)->setType('bool')->setValue($o->value);
					break;

				case 'custom':
					$func = 'get' . ucfirst($o->name) . 'Field';
					if (method_exists($this, $func)) {
						$o->field = $this->$func($o->name, $o->value);
					} else {
						$o->field = NULL;
					}
				*/	
			}
		
		}
		
		$this->assign('form',	$form);
		//$this->assign('options',$options);
		$this->assign('groupedOptions',$groupedOptions);

	}
	
}
