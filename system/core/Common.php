<?php
use Mark\core\Config;

class Common {

	/**
	 * Load view file
	 * @param file (html or php)
	 * @param array string (or not array)
	 */
	static function view($viewFile, $viewVars = array()){

		extract($viewVars);

		$viewFileCheck = explode(".", $viewFile);

		if(!isset($viewFileCheck[1])){
			$viewFile .= ".php";
		}

		$viewFile = str_replace("::", "/", $viewFile);
		$filename = Config::get('autoload','path/app')."views/{$viewFile}";

		if(file_exists($filename)){

			require_once $filename;

		}else{

			die("Trying to Load Non Existing View");
		}
	}

	/**
	 * Load helper
	 * @param file
	 * @return void
	 */
	public static function helper($file) {

		$fileCheck = explode(".", $file);

		if(!isset($fileCheck[1])){
			$file .= ".php";
		}

		$app = Config::get('autoload','path/app')."helpers/{$file}";
		$default = Config::get('autoload','path/system')."helpers/{$file}";

		if(file_exists($app)) {

			include_once $app;

		} elseif(file_exists($default)) {

			include_once $default;

		} else {

			die("helper file does not exist");

		}
	} 
}

?>