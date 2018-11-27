<?php

class Cookie {

	/**
	 * Check if Cookie name is already exist
	 * @param string
	 * @return bool
	 */
	public static function has($name) {
		return isset($_COOKIE[$name]) ? true : false;
	}

	/**
	 * Retrieve Session variable
	 * @param string
	 * @return void
	 */
	public static function get($name) {
		return $_COOKIE[$name];
	}

	/**
	 * Set Cookie
	 * @param string
	 * @param string
	 * @param int
	 * @return void
	 */
	public static function put($name, $value, $expiry) {
		if(setcookie($name, $value, time() + $expiry, '/')) {
			return true;
		}
		return false;
	}

	/**
	 * Reset Cookie
	 * @param string
	 * @return void
	 */
	public static function delete($name) {
		self::put($name, '', time() - 1);
	}
}
?>