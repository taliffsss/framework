<?php
use Mark\core\Config;
/**
 * This class can return, manipulate Url as well as query string
 *
 * @package Url Class
 * @author 	Mark Anthony Naluz <anthony.naluz15@gmail.com>
 * @copyright Jul 2018 <Mark Anthony Naluz>
 */

class Url {

	/**
	* Set Request Uri
	*/
	protected static $request_uri = NULL;

	/**
	* Setting up database environment Development of Production
	* Get base Url whether local or live sites
	* @return url
	*/
	public static function baseUrl() {

		$url = ($_SERVER['SERVER_NAME'] == 'localhost') ? Config::get('autoload','domain/development') : Config::get('autoload','domain/production');

		return $url;
	}

	/**
	* Setting up Redirection
	* if index will direct to base Url if not padding uri in base url
	* @param $url URL
	* @return url
	*/
	public static function redirect($url) {

		if($url == 'index') {
			header('Location:'.Url::baseUrl());
		} else {
			header('Location:'.Url::baseUrl().'/'.$url);
		}
	}

	/**
	* To know what page are in service
	* @return filename
	*/
	public function page() {
		$page = basename($_SERVER['PHP_SELF']);
		return $page;
	}

	/**
	 * Get Request uri
	 * @param Int
	 * @param String
	 * @return Int or String
	 */
	public static function uri_segment($id) {

		self::$request_uri = trim(urldecode($_SERVER['REQUEST_URI']));

		$uri = explode('/',self::$request_uri);
		if(array_key_exists($id, $uri)) {
			$_uri = $uri[$id];
		} else {
			$_uri = NULL;
		}

		return $_uri;
	}

	/**
	 * Get Current Url
	 * @return Url
	 */
	public function currentUrl() {

		$_uri = $this->request_uri;
		$_url = Url::baseUrl();

		$current = $_url.$_uri;

		return $current;
	}
}