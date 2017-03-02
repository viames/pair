<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\View;

class UserViewLogin extends View {

	public function render() {

		$this->app->style = 'login';

		$this->app->pageTitle = $this->lang('USER_LOGIN_PAGE_TITLE', PRODUCT_NAME);
		
		// if is set a page from where we are coming... 
		$referer = $this->app->getState('referer');

		$form = $this->model->getLoginForm();
		
		$form->getControl('referer')->setValue($referer);

		$this->assign('form', $form);
		
	}
	
}
