<?php
use Mark\core\Config;

class Routes {

	private static $_url = array();
	private static $_method = array();
	private static $_part;


	/**
	 * Map url and Class store in array
	 * @param string
	 * @param string
	 * @return void
	 */
	public static function add($uri,$methods) {

		$url = str_replace("/", "index", $uri);

		self::$_url[] = $url;

		self::$_method[$url] = $methods;

	}

	/**
	 * publish a valid url pass thru
	 */
	public static function _autoload() {

		Routes::routePart();

		$method = str_replace("::", "/", self::$_method);

		$dir = scandir(Config::get('autoload','path/app').'controllers/');

		//return only a file or folder
		foreach ($dir as $key => &$value) {
			if($value != '.' && $value != '..') {
				$val[] = $value;
			}
		}

		//trim slash
		foreach (self::$_url as &$value) {

			$myUrl[] = rtrim($value,'/');
		}

		//check if class are inside in folder
		if(in_array(self::$_part, $myUrl)) {
			foreach ($method as $key => &$value) {
				if(rtrim($key,'/') == self::$_part) {
					$_m = explode('/', $value);
					if(in_array($_m[0], $val)) {
						$myClass = $_m[1];
						$myMethod = $_m[2];
					} else {
						$myClass = $_m[0];
						$myMethod = $_m[1];
					}
				}
			}

		} else {
			MyError::show_error(404);
		}

		//check if url and class are existing
		if(in_array(self::$_part, $myUrl)) {
			if(class_exists($myClass)) {
				$controller = new $myClass();
				if(method_exists($controller, $myMethod)) {
					$controller->$myMethod();
				} else {
					MyError::show_error(404);
				}
			} else {
				MyError::show_error(404);
			}
		}
	}

	/**
	 * Explode url by slash
	 * @param string
	 * @return array string
	 */
	private static function routePart() {

		$_uri = ($_SERVER['SERVER_NAME'] == 'localhost') ? str_replace('/'.self::uri(0).'/', "", $_SERVER["REQUEST_URI"]) : rtrim($_SERVER["REQUEST_URI"], '/');

		if($_uri == '') {

			self::$_part = Config::get('autoload','defaults/method');

		} else {
			foreach (self::$_url as $route) {

				$trim = rtrim($route,'/');
					
				$key = str_replace(array(':any', ':num'), array('[^/]+', '[0-9]+'), $trim);

				if(preg_match('#^'.$key.'$#', $_uri, $matches)) {

					self::$_part = $trim;

				} else {

					self::$_part = $trim;

				}

			}
		}
	}

	/**
	 * Set Request uri
	 * @param int
	 * @return string
	 */
	private static function uri($part) {

		$parts = explode("/", $_SERVER["REQUEST_URI"]);

		$_uri = ($_SERVER['SERVER_NAME'] == 'localhost') ? $parts[2] : $parts[1];

		if($_uri == '') {
			$part++;
			
		}
		return (isset($parts[$part])) ? $parts[$part] : "";
	}

}
?>