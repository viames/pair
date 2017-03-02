<?php

/**
 * @version $Id$
 * @author	Viames Marino 
 * @package	Pair
 */

use Pair\Controller;
use Pair\Group;
use Pair\Language;
use Pair\Router;
use Pair\Session;
use Pair\User;

class ApiController extends Controller {
	
	/**
	 * Missing methods.
	 */
	public function __call($name, $arguments) {
		
		sleep(3);
		$name = substr($name, 0, -6);
		$this->sendError('The requested API method does not exist (' . $name . ')');
		
	}

	/**
	 * Do login and send session ID if valid.
	 */
	public function loginAction() {
		
		$route = Router::getInstance();
		
		$username = $route->getParam('username');
		$password = $route->getParam('password');
		$timezone = $route->getParam('timezone');
		
		if ($username and $password) {
	
			$result = User::doLogin($username, $password, $timezone);

			if (!$result->error) {

				$data = new stdClass();
				$data->sessionId = $result->sessionId;
				$this->sendData($data);
				
			} else {
				
				sleep(3);
				$this->sendError('Authentication failed');
				
			}
				
		} else {
			
			$this->sendError('Both username and password are required');
						
		}
		
	}
	
	/**
	 * Do logout and delete session ID.
	 */
	public function logoutAction() {
	
		$route	= Router::getInstance();
		$sid	= $route->getParam('sid');
		$res	= User::doLogout($sid);
		
		if ($res) {
			$this->sendSuccess();
		} else {
			$this->sendError('Session does not exist');
		}
		
	}
	
	/**
	 * Sends a JSON object with user name and instance name by user SID.
	 */
	public function getUserInformationsAction() {
		
		$route = Router::getInstance();
		$sid = $route->getParam('sid');

		$session	= new Session($sid);
		$user		= new User($session->idUser);
		$group		= new Group($user->groupId);
		$language	= new Language($user->languageId);
		
		$data = new stdClass();
		$data->name		= $user->name;
		$data->surname	= $user->surname;
		$data->fullname	= $user->fullName;
		$data->username = $user->username;
		$data->group	= $group->name;
		$data->language	= $language->languageName;
		$data->email	= $user->email;
		$data->timezone	= $user->tzName;
		
		$this->sendData($data);
		
	}
	
	/**
	 * Get a parameter from route by its name and return a DateTime object if valid.
	 * 
	 * @param	string	Name of the date param.
	 * 
	 * @return	DateTime
	 */
	private function getDateTimeParam($name) {
		
		$route = Router::getInstance();
		$param = $route->getParam($name);
		
		if (!$this->isTimestampValid($param)) {
			$this->sendError(ucfirst($name) . ' date is not valid');
		}
		
		$dateTime = new DateTime('@' . $param);
		
		return $dateTime;
		
	}

	/**
	 * This method checks if passed timestamp looks valid.
	 * 
	 * @param	mixed	Timestamp value.
	 * 
	 * @return	boolean
	 */
	private function isTimestampValid($timestamp) {
		
		return ((string)(int)$timestamp === $timestamp)
			&& ($timestamp <= PHP_INT_MAX)
			&& ($timestamp >= ~PHP_INT_MAX);
		
	}
	
	/**
	 * Outputs a JSON error message.
	 *
	 * @param	string	Error message to print.
	 */
	public function sendError($message) {

		$data = new stdClass();
		$data->error = TRUE;
		$data->message = $message;
		$this->printout($data);
				
	}

	/**
	 * Outputs a confirm of task done within a JSON.
	 */
	public function sendSuccess() {
	
		$data = new stdClass();
		$data->error = FALSE;
		$this->printout($data);
	
	}
	
	/**
	 * Outputs a JSON object with (object)data property.
	 *
	 * @param	object	Structured object containing data.
	 */
	public function sendData($data) {
	
		$this->printout($data);
		
	}
	
	private function printout($data) {
		
		// anonymous function to extract latest SVN
		$nodeRecursion = function (&$node, $name, $value) use (&$nodeRecursion) {
			
			switch (gettype($value)) {
			
				case 'boolean':
					$node->addChild($name, ($value ? 'true' : 'false'));
					break;
			
				case 'array':
					foreach ($value as $newName=>$newValue) {
						if (is_numeric($newName)) {
							$newName = 'item';
						}
						$nodeRecursion($node, $newName, $newValue);
					}
					break;

				case 'object':
					$props = get_object_vars($value);
					$newNode = $node->addChild($name);
					foreach ($props as $newName=>$newValue) {
						$nodeRecursion($newNode, $newName, $newValue);
					}
					break;

				default:
					$node->addChild($name, $value);
					break;

			}
			
		};
		
		// check if return is required to be XML.
		if ($this->route->getParam('xml')) {
			
			// initialize node
			$baseName = strtolower(PRODUCT_NAME);
			$base = new SimpleXMLElement('<' . $baseName . '></' . $baseName . '>');
			$nodeRecursion($base, 'response', $data);

			header('Content-Type: text/xml', TRUE);
			print $base->asXML();
			
		} else {

			$json = json_encode($data);
			header('Content-Type: application/json', TRUE);
			print $json;

		}

		die();
		
	}

}