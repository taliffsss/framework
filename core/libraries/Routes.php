<?php

class Routes {

	private $routes;

	function __construct() {
		$this->routes = $GLOBALS["config"]["routes"];
		$route = $this->findRoute();

		if(class_exists($route['controller'])) {
			$controller = new $route['controller']();
			$method = $route["method"];
			if(method_exists($controller, $method)) {
				$controller->$method();
			} else {
				MyError::show_error(404);
			}
		} else {
			MyError::show_error(404);
		}
	}

	private function routePart($route) {
		if(is_array($route)) {
			$route = $route["url"];
		}
		$parts = explode("/", $route);

		return $parts;

	}

	static function uri($part) {
		$parts = explode("/", $_SERVER["REQUEST_URI"]); 

		if($parts[1] == $GLOBALS["config"]["path"]["index"]) {
			$part++;
		}

		return (isset($parts[$part])) ? $parts[$part] : "";
	}

	private function findRoute() {

		foreach ($this->routes as $route) {
			
			$parts = $this->routePart($route);
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

		$uri = Routes::uri(2);
		$_uri = Routes::uri(3);

		if($uri == "") {
			$uri = $GLOBALS["config"]["defaults"]["controller"];
		}

		if($_uri == "") {
			$_uri = $GLOBALS["config"]["defaults"]["method"];
		}

		$route = array(
			"controller" => $uri,
			"method" => $_uri
		);

		return $route;
	}

}
?>