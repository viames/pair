<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	VMS
 */

use VMS\Form;
use VMS\Model;

class ModulesModel extends Model {
	
	public function getModuleForm() {
	
		$form = new Form();
		$form->addInput('package')->setType('file')->setRequired();
		return $form;
	
	}
	
}
