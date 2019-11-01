<?php
		
namespace Pair;

class ErrorLog extends ActiveRecord {

	/**
	 * Property that binds db field id.
	 * @var int
	 */
	protected $id;

	/**
	 * Property that binds db field created_time.
	 * @var DateTime
	 */
	protected $createdTime;

	/**
	 * Property that binds db field user_id.
	 * @var int|NULL
	 */
	protected $userId;

	/**
	 * Property that binds db field path.
	 * @var string
	 */
	protected $path;
	
	/**
	 * Property that binds db field get_data.
	 * @var array
	 */
	protected $getData;

	/**
	 * Property that binds db field post_data.
	 * @var array
	 */
	protected $postData;

	/**
	 * Property that binds db field cookie_data.
	 * @var array
	 */
	protected $cookieData;

	/**
	 * Property that binds db field description.
	 * @var string
	 */
	protected $description;
	
	/**
	 * Property that binds db field user_messages.
	 * @var string
	 */
	protected $userMessages;

	/**
	 * Property that binds db field referer.
	 * @var string
	 */
	protected $referer;

	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'error_logs';
		
	/**
	 * Name of primary key db field.
	 * @var string
	 */
	const TABLE_KEY = 'id';
	
	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init() {

		$this->bindAsDatetime('createdTime');
		$this->bindAsInteger('id', 'userId');

	}

	/**
	 * Returns array with matching object property name on related db fields.
	 *
	 * @return	array
	 */
	protected static function getBinds(): array {
		
		$varFields = array (
			'id'			=> 'id',
			'createdTime'	=> 'created_time',
			'userId'		=> 'user_id',
			'path'			=> 'path',
			'getData'		=> 'get_data',
			'postData'		=> 'post_data',
			'cookieData'	=> 'cookie_data',
			'description'	=> 'description',
			'userMessages'	=> 'user_messages',
			'referer'		=> 'referer');
		
		return $varFields;
		
	}

	/**
	 * Serialize some properties before prepareData() method execution.
	 */
	protected function beforePrepareData() {

		$this->getData		= serialize($this->getData);
		$this->postData		= serialize($this->postData);
		$this->cookieData	= serialize($this->cookieData);
		$this->userMessages	= serialize($this->userMessages);

	}

	/**
	 * Unserialize some properties after populate() method execution.
	 */
	protected function afterPopulate() {

		$this->getData		= unserialize($this->getData);
		$this->postData		= unserialize($this->postData);
		$this->cookieData	= unserialize($this->cookieData);
		$this->userMessages	= unserialize($this->userMessages);
		
	}
	
	/**
	 * Allows to keep the current Application and browser state.
	 * 
	 * @param	string	Description of the snapshot moment.
	 * @return	bool	TRUE if save was succesful.
	 */
	public static function keepSnapshot($description): bool {
		
		$app = Application::getInstance();
		$router = Router::getInstance();
		
		$snap = new self();
		
		$snap->createdTime	= new \DateTime();
		$snap->userId		= $app->currentUser->id;
		$snap->path			= substr($router->url,1);
		$snap->getData		= $_GET;
		$snap->postData		= $_POST;
		$snap->cookieData	= $_COOKIE;
		$snap->description	= $description;
		$snap->userMessages	= $app->messages;
		
		if (isset($_SERVER['HTTP_REFERER'])) {

			// removes application base url from referer
			if (0 === strpos($_SERVER['HTTP_REFERER'], BASE_HREF)) {
				$snap->referer = substr($_SERVER['HTTP_REFERER'], strlen(BASE_HREF));
			} else {
				$snap->referer = (string)$_SERVER['HTTP_REFERER'];
			}
			
		} else {
			$snap->referer = '';
		}

		return $snap->create();
		
	}
	
}