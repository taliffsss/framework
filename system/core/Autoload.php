<?php
class Autoload {
	

	private static $autoload = NULL;

	function __construct() {
		Autoload::_autoload();
	}

	private static function _autoload() {

		if(!isset($_SESSION)) {
			self::$autoload = new Loader;
		}

		return self::$autoload;
	}
}

?>