<?php
class Controller {

	/**
	 * Main Core instantiate
	 * Usefull if class instantiated again, properties will be assigned
	 * from this stored instance, avoiding re processing
	 * Url Class instantiate
	 * @var Obj
	 */
	public $url;
	/**
	 * Session libraries class Instance
	 * @var Obj
	 */
	public $session;
	/**
	 * Common class Instance
	 * @var Obj
	 */
	public $load;
	/**
	 * Database class Instance
	 * @var Obj
	 */
	private $db;

	function __construct() {
		$GLOBALS["instances"][] = &$this;
		$this->_token = new Token;
	}

}
?>