<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Controller;

class SelftestController extends Controller {

	protected function init(){

		require APPLICATION_PATH . '/modules/selftest/classes/SelfTest.php';

	}
	
	public function defaultAction() {
		
		$this->view = 'default';
		
	}

}