<?php

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
		$filename = $GLOBALS["autoload"]["path"]["app"]."views/{$viewFile}";

		if(file_exists($filename)){

			require_once $filename;

		}else{

			die("Trying to Load Non Existing View");
		}
	}
}

?>