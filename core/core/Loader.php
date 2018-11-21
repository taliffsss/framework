<?php
namespace AIS\core;
use AIS\libraries\Routes;
use AIS\libraries\Session;
use AIS\libraries\CsrfToken;

/**
 * This class can autoload routes view
 *
 * @package Loader Class
 * @author 	Mark Anthony Naluz <anthony.naluz15@gmail.com>
 * @copyright Nov 2018 <Mark Anthony Naluz>
 */

class Loader{

	function __construct() {
		$this->_autoload();
		Session::set_session();
		Routes::_autoload();
		$this->csrf_token();
	}

	public function csrf_token() {

		CsrfToken::_set_csrf_token();

	}

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
		$filename = $GLOBALS["config"]["path"]["app"]."views/{$viewFile}";

		if(file_exists($filename)){

			require_once $filename;

		}else{

			die("Trying to Load Non Existing View");
		}
	}

	/**
	 * Scan controllers folder
	 * @return Array folder path
	 */
	public function _scanControllerFolder() {
		$loc = $GLOBALS["config"]["path"]["app"].'controllers/';

		$dir = scandir($loc);

		foreach ($dir as $key => &$value) {
			if($value != '.' && $value != '..') {
				$controller[] = $loc.$value;
			}
		}

		return (isset($controller)) ? $controller : array();
	}

	/**
	 * Scan models folder
	 * @return Array folder path
	 */
	public function _scanModelFolder() {
		$loc = $GLOBALS["config"]["path"]["app"].'models/';

		$dir = scandir($loc);

		foreach ($dir as $key => &$value) {
			if($value != '.' && $value != '..') {
				$model[] = $loc.$value;
			}
		}

		return (isset($model)) ? $model : array();
	}

	/**
	 * Update spl register 
	 */
	public function _autoload() {

		$file = $GLOBALS["config"]["path"]["core"]."spl_register.php";
		
		$controllers = $this->_scanControllerFolder();

		$models = $this->_scanModelFolder();

		$log = '<?php

spl_autoload_register(function($class){
	$core = $GLOBALS["config"]["path"]["core"];
	$app = $GLOBALS["config"]["path"]["app"];

	if(file_exists("{$core}libraries/{$class}.php")) {

		require_once "{$core}libraries/{$class}.php";

	} elseif (file_exists("{$app}controllers/{$class}.php")) {

		require_once "{$app}controllers/{$class}.php";
	';

for ($i=0; $i < count($controllers); $i++) { 
	if(is_dir($controllers[$i])) {
		$log .= '
	} elseif (file_exists("'.$controllers[$i].'/{$class}.php")) {

		require_once "'.$controllers[$i].'/{$class}.php";
		';
	}
}

for ($i=0; $i < count($models); $i++) { 
	if(is_dir($models[$i])) {
		$log .= PHP_EOL.'
	} elseif (file_exists("{$app}models/'.$models[$i].'/{$class}.php")) {

		require_once "{$app}models/'.$models[$i].'/{$class}.php";
		';
	}
}

$log .= '
	} elseif (file_exists("{$app}models/{$class}.php")) {

		require_once "{$app}models/{$class}.php";

	} elseif (file_exists("{$app}libraries/{$class}.php")) {

		require_once "{$app}libraries/{$class}.php";

	}

});
?>
		';

		try {

			if(unlink($file)) {
				file_put_contents($file, $log, FILE_APPEND | LOCK_EX);
			}

			if(!file_exists($file)) {
				file_put_contents($file, $log, FILE_APPEND | LOCK_EX);
			}
			
		} catch (Exception $e) {

			$e->getMessage();
			
		}
	}

}
?>