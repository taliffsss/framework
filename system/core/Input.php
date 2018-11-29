<?php
/**
 * @package Database Class
 * @author 	Mark Anthony Naluz <anthony.naluz15@gmail.com>
 * @copyright Jul 2018 <Mark Anthony Naluz>
 */

class Input {

	/**
	 * Request method if has return true
	 * @return bool
	 */
	public static function _method() {
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'POST':
				return (!empty($_POST)) ? true : false;
				break;
			case 'GET':
				return (!empty($_GET)) ? true : false;
				break;
			default:
				return false;
				break;
		}
	}

	/**
	 * use for return value of input box if error exist
	 * @param string
	 * @return string
	 */
	public static function set_value($value) {

		if(isset($_POST[$value])) {

			return htmlentities($_POST[$value]);

		} elseif(isset($_GET[$value])) {

			return htmlentities($_GET[$value]);

		}
		return false;
	}

	/**
	* Set Get Variable
	* @param String & Int
	* @return True
	*/
	public static function get($data) {
		
		$trim = trim(stripslashes(htmlspecialchars($data)));


		$_data = Input::filter_data($trim);

		if(isset($_GET[$_data])){
			return $_GET[$_data];
		}

	}

	/**
	* Set POST Variable
	* @param String & Int
	* @return True
	*/
	public static function post($data) {

		$trim = trim(stripslashes(htmlspecialchars($data)));

		$_data = Input::filter_data($trim);

		if(isset($_POST[$_data])){
			return $_POST[$_data];
		}

	}

	/**
	* Filter POST Data Sanitizing
	* @param String
	* @param Int
	* @param Url
	* @return String|Int|Url
	*/
	private static function filter_data($data) {

		$email = "/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/i";
		$url = '/^(http|https):\\/\\/[a-z0-9]+([\\-\\.]{1}[a-z0-9]+)*\\.[a-z]{2,5}'.'((:[0-9]{1,5})?\\/.*)?$/i';

		if(is_numeric($data)) {
			$_data = filter_var($data, FILTER_VALIDATE_INT);
		} else {
			if(preg_match($email, $data)) {
				$_data = filter_var($_trim, FILTER_VALIDATE_EMAIL);
				$_trim = filter_var($data, FILTER_SANITIZE_EMAIL);
			} else {
				if(preg_match($url, $data)) {
					filter_var($data, FILTER_VALIDATE_URL);
				} else {
					$_data = filter_var($data, FILTER_SANITIZE_STRING);
				}
			}
		}

		return $_data;
	}
}
?>