<?php
namespace Mark\core;

class Config {

	/**
	 * Get attribute data in global variable
	 * @param db path
	 * @return string
	 */
	public static function get($config,$path = NULL) {
		if($path) {

			switch ($config) {

				case 'autoload':
					$config = $GLOBALS["autoload"];
					$path = explode('/', $path);
					foreach ($path as &$key) {
						if(isset($config[$key])) {
							$config = $config[$key];
						}
					}

					return $config;

					break;

				case 'config':
					$config = $GLOBALS["config"];
					
					foreach ($config as $key => &$value) {
						if($path == $key) {
							$res = $value;
						}
					}

					return $res;

				case 'instances':
					$config = $GLOBALS["instances"];
					
					foreach ($config as $key => &$value) {
						if($path == $key) {
							$res = $value;
						}
					}

					return $res;
					break;
			}
					
		}

		return false;
	}
}	

?>