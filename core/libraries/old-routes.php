<?php
namespace AIS\libraries;

class Routes {

	private $_url = array();
	private $_method = array();


	public function add($uri,$methods) {

		$this->_url[] = $uri;
		$this->_method[$uri] = $methods;

	}

	public function post() {
		$route = Routes::findRoute();

		$method = str_replace("::", "/", $this->_method);

		$dir = scandir($GLOBALS["config"]["path"]["app"].'controllers/');

		$myurl = ($route['method'] == 'index') ? $route['controller'] : $route['controller'].'/'.$route['method'];

		//return only a file or folder
		foreach ($dir as $key => &$value) {
			if($value != '.' && $value != '..') {
				$val[] = $value;
			}
		}


		//check if class are inside in folder
		if(in_array($myurl, $this->_url)) {
			foreach ($method as $key => &$value) {
				if($key == $myurl) {
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
		if(in_array($myurl, $this->_url)) {
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
	private static function routePart($route) {
		if(is_array($route)) {
			$route = $route["url"];
		}
		$parts = explode("/", $route);

		return $parts;

	}

	/**
	 * Set Request uri
	 * @param int
	 * @return string
	 */
	private static function uri($part) {
		$parts = explode("/", $_SERVER["REQUEST_URI"]); 

		if($parts[1] == $GLOBALS["config"]["path"]["index"]) {
			$part++;
		}

		return (isset($parts[$part])) ? $parts[$part] : "";
	}

	/**
	 * Find route
	 */
	private static function findRoute() {

		$routes_path = $GLOBALS["config"]["routes"];

		foreach ($routes_path as $route) {
			
			$parts = Routes::routePart($route);

			$allMatch = true;

			foreach ($parts as $key => $value) {

				if($value != "*") {
					if(Routes::uri($key) != $value) {
						$allMatch = false;
					}
				}
			}
			if($allMatch) {
				return $route;
			}
		}

		$controller = (Routes::uri(2) == '') ? $GLOBALS["config"]["defaults"]["controller"] : Routes::uri(2);
		$method = (Routes::uri(3) == '') ? $GLOBALS["config"]["defaults"]["method"] : Routes::uri(3);

		$route = array(
			"controller" => $controller,
			"method" => $method
		);

		return $route;
	}

}
?>