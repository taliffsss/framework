<?php
use Mark\core\Config;
/**
 * This class can autoload routes view csrf token
 *
 * @package Loader Class
 * @author 	Mark Anthony Naluz <anthony.naluz15@gmail.com>
 * @copyright Nov 2018 <Mark Anthony Naluz>
 */
class Loader {
	
	private $path = array();
	private $prefix = array();

	function __construct() {
		
		Session::start();
		Routes::_autoload();
	}
}
?>