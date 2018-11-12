<?php 
/**
 * @package Database Class
 * @author 	Mark Anthony Naluz <anthony.naluz15@gmail.com>
 * @copyright Jul 2018 <Mark Anthony Naluz>
 */

class CsrfToken {
	
	/**
	 * CSRF Token
	 *
	 * @var	random string
	 */
	protected $_hash;
	/**
	 * CSRF Token session name
	 *
	 * @var	string
	 */
	private $name = 'csrf-token';
	/**
	 * CSRF Token session time name
	 *
	 * @var	string
	 */
	private $time = 'csrf-time';

	function __construct() {
		$this->_hash = $this->_csrf_hash();
		$this->_set_csrf_token();
	}

	/**
	 * Check if CSRF token has been set if not it will generate new CSRF Token
	 * Stored in session
	 * @return session data value random string
	 */
	public function _set_csrf_token() {

		if(!isset($_SESSION[$this->name])) {
			$this->_generate_new_hash();
		} else {
			if($_SERVER['REQUEST_METHOD'] != "POST") {
				$this->_generate_new_hash();
			}
		}
	}

	/**
	 * Set hash in Session
	 */
	public function _generate_new_hash() {
		$_SESSION[$this->name] = $this->_hash;
		$_SESSION[$this->time] = time();
	}

	/**
	 * Checks whether CSRF is Valid
	 * if not valid generate new hash
	 * @return true or String
	 */
	public function _csrf_is_valid($key) {
		if($_SERVER['REQUEST_METHOD'] == 'POST') {
			if($this->_csrf_time_validity()) {
				if($_SESSION[$this->name] === $key) {
					return true;
				} else {
					$this->_generate_new_hash();
				}
			}
		}
	}

	/**
	 * Create CSRF Hash mixed openssl random pseudo bytes & salts
	 * Crypt One-way string hashing
	 * @return Random String
	 */
	private function _csrf_hash() {

		$_crypt = crypt($this->key(), '$2a$07$abulpE26zd/Qxe08b951b786e8e3AM/6KYyAhykKYiwTJyL7aa7f7e5a1390523e44fa9180salt$');

		return $_crypt;
	}

	/**
	 * Check csrf token validity (5 Mins validity)
	 * @return Boolean
	 */
	public function _csrf_time_validity() {

		$_csrf = time() - $_SESSION[$this->time];

		return ($_csrf < 300) ? true : false;
	}

	/**
	 * key generated in a cryptographically safe way.
	 * @return openssl random pseudo bytes
	 */
	public function key() {

		$key = bin2hex(openssl_random_pseudo_bytes(35));

		return $key;
	}
}
?>