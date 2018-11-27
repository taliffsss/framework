<?php

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

		self::$_url[] = $uri;

		self::$_method[$uri] = $methods;

	}

	/**
	 * publish a valid url pass thru
	 */
	public static function _autoload() {

		Routes::routePart();

		$method = str_replace("::", "/", self::$_method);

		$dir = scandir($GLOBALS["autoload"]["path"]["app"].'controllers/');

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

		$_uri = rtrim(str_replace("/man/", "", $_SERVER["REQUEST_URI"]), '/');

		foreach (self::$_url as $route) {

			$trim = rtrim($route,'/');
				
			$key = str_replace(array(':any', ':num'), array('[^/]+', '[0-9]+'), $trim);

			if(preg_match('#^'.$key.'$#', $_uri, $matches)) {

				self::$_part = rtrim($route, '/');

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

		if($parts[1] == $GLOBALS["autoload"]["path"]["index"]) {
			$part++;
		}

		return (isset($parts[$part])) ? $parts[$part] : "";
	}

}
?>