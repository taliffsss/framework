<?php
namespace AIS\libraries;

/**
 * This class can return, manipulate Url as well as query string
 *
 * @package Url Class
 * @author 	Mark Anthony Naluz <anthony.naluz15@gmail.com>
 * @copyright Jul 2018 <Mark Anthony Naluz>
 */

class Url {

	/**
	* Set URL Production or Local Machine
	*/
	protected $live = $GLOBALS["config"]["domain"]["production"];
	protected $local = $GLOBALS["config"]["domain"]["development"];

	/**
	* Set Request Uri
	*/
	protected $request_uri = NULL;

	//Constructor
	function __construct(){

		$this->request_uri = trim(urldecode($_SERVER['REQUEST_URI']));

	}

	/**
	* Setting up database environment Development of Production
	* Get base Url whether local or live sites
	* @return url
	*/
	public function baseUrl() {

		$url = ($_SERVER['SERVER_NAME'] == 'localhost') ? $this->local : $this->live;

		return $url;
	}

	/**
	* Setting up Redirection
	* if index will direct to base Url if not padding uri in base url
	* @param $url URL
	* @return url
	*/
	public function redirect($url) {

		if($url == 'index') {
			header('Location:'.$this->baseUrl());
		} else {
			header('Location:'.$this->baseUrl().'/'.$url);
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
	public function uri_segment($id) {

		$x = $this->request_uri;
		$uri = explode('/',$x);
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
		$_url = $this->baseUrl();

		$current = $_url.$_uri;

		return $current;
	}
}