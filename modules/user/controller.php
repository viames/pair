<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Application;
use Pair\Controller;
use Pair\Input;
use Pair\User;
use Pair\Router;

class UserController extends Controller {
	
	public function defaultAction() {
		
		$this->view = 'login';
		
	}
	
	/**
	 * Shows login form or try login action based on the AUTH_SOURCE config param.
	 */
	public function loginAction() {
	
		// TODO implement internal security token_check

		$username	= Input::get('username');
		$password	= Input::get('password');
		$timezone	= Input::get('timezone');

		if (Input::formPostSubmitted()) {
			
			// found both username and password
			if ($username and $password) {

				// choose login source
				switch (AUTH_SOURCE) {
					
					case 'ldap':
						// TODO move ldap code into User class
						//$result = User::doLdapLogin($username, $password, $timezone);
						$this->enqueueError($this->lang('LDAP_IS_NOT_AVAILABLE'));
						return FALSE;
						break;
						
					default:
					case 'internal':
						$result = User::doLogin($username, $password, $timezone);
						break;
				
				}
				
				// login success
				if (!$result->error) {

					// userId of user that is ready logged in
					$user = new User($result->userId);

					//referer module of this user on current group
					$landing = $user->getLanding();

					if (isset($landing->module)) {
						$this->app->redirect($landing->module . '/' . $landing->action);
					} else {
						$route = Router::getInstance();
						$this->app->redirect($route->getDefaultUrl());
					}

				// login denied
				} else {
					
					sleep(3);
					$this->enqueueError($result->message);
					$this->app->redirect('user/login');
					
				}
			
			// username or password missing
			} else {
				
				$this->enqueueError($this->lang('AUTHENTICATION_REQUIRES_USERNAME_AND_PASSWORD'));
				return FALSE;
				
			}
			
		}

	}

	/**
	 * Do the logout action.
	 */
	public function logoutAction() {

		$app = Application::getInstance();
		
		User::doLogout(session_id());
		
		// manual redirect because of variables clean-up
		header('Location: ' . BASE_HREF . 'user/login');
		exit();
		
	}

	/**
	 * Do the user profile change.
	 */
	public function profileChangeAction() {
	
		$form = $this->model->getUserForm();
		
		$user				= new User($this->app->currentUser->id);
		$user->name			= Input::get('name');
		$user->surname		= Input::get('surname');
		$user->email		= Input::get('email') ? Input::get('email') : NULL;
		$user->ldapUser		= Input::get('ldapUser') ? Input::get('ldapUser') : NULL;
		$user->username		= Input::get('username');
		$user->languageId	= Input::get('languageId', 'int');
		
		if (Input::get('password')) {
			$user->hash = User::getHashedPasswordWithSalt(Input::get('password'));
		}

		// we notice just if user changes really
		if ($form->isValid() and $user->update()) {
			$this->enqueueMessage($this->lang('YOUR_PROFILE_HAS_BEEN_CHANGED'));
		}
		
		$this->app->redirect('user/profile');
	
	}

}