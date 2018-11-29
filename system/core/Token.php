<?php
use Mark\core\Config;
/**
 * @package Database Class
 * @author 	Mark Anthony Naluz <anthony.naluz15@gmail.com>
 * @copyright Jul 2018 <Mark Anthony Naluz>
 */

class Token {
	
	/**
	 * CSRF Token
	 *
	 * @var	random string
	 */
	protected static $_hash;
	/**
	 * CSRF Token session name
	 *
	 * @var	string
	 */
	private static $name;
	/**
	 * CSRF Token time validity
	 *
	 * @var	string
	 */
	private static $expired_at;
	/**
	 * CSRF Token session time name
	 *
	 * @var	string
	 */
	private static $time = 'csrf-time';

	private static $_csrf;

	private static $_start = null;

	function __construct() {
		self::start();
	}

	private static function start() {

		self::$_csrf = Config::get('config','csrf_token');

		if(self::$_csrf == TRUE) {
			if(!isset(self::$_start)) {
				self::$_start = self::_set_csrf_token();
			}

			return self::$_start;
		}
		return false;
	}

	/**
	 * Check if CSRF token has been set if not it will generate new CSRF Token
	 * Stored in session
	 * @return session data value random string
	 */
	static function _set_csrf_token() {

		self::$name = Config::get('config','csrf_name');
		self::$expired_at = Config::get('config','csrf_time');

		self::$_hash = self::_csrf_hash();

		if(!isset($_SESSION[self::$name])) {
			self::_generate_new_hash();
		} else {
			if($_SERVER['REQUEST_METHOD'] != "POST") {
				self::_generate_new_hash();
			}
		}
	}

	/**
	 * Set hash in Session
	 */
	public static function _generate_new_hash() {
		Session::put(self::$name,self::$_hash);
		Session::put(self::$time,time());
	}

	/**
	 * Checks whether CSRF is Valid
	 * if not valid generate new hash
	 * @return true or String
	 */
	public function _csrf_is_valid($key) {
		if(Input::_method()) {
			if(self::_csrf_time_validity()) {
				if(Session::get(self::$name) === $key) {
					return true;
				} else {
					self::_generate_new_hash();
				}
			}
		}
	}

	/**
	 * Create CSRF Hash mixed openssl random pseudo bytes & salts
	 * Crypt One-way string hashing
	 * @return Random String
	 */
	private static function _csrf_hash() {

		$_crypt = crypt(self::key(), '$2a$07$abulpE26zd/Qxe08b951b786e8e3AM/6KYyAhykKYiwTJyL7aa7f7e5a1390523e44fa9180salt$');

		return $_crypt;
	}

	/**
	 * Check csrf token validity (5 Mins validity)
	 * @return Boolean
	 */
	public function _csrf_time_validity() {

		$_csrf = time() - Session::get(self::$time);

		return ($_csrf < self::$expired_at) ? true : false;
	}

	/**
	 * key generated in a cryptographically safe way.
	 * @return openssl random pseudo bytes
	 */
	public static function key() {

		$key = bin2hex(openssl_random_pseudo_bytes(35));

		return $key;
	}
}
?>