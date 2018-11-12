<?php

/**
 * @package Database Class
 * @author 	Mark Anthony Naluz <anthony.naluz15@gmail.com>
 * @copyright Jul 2018 <Mark Anthony Naluz>
 */

class Input {

	/**
	* Set Get Variable
	* @param String & Int
	* @return True
	*/
	public function get($data) {
		
		$trim = trim(stripslashes(htmlspecialchars($data)));


		$_data = $this->filter_data($trim);

		if(isset($_GET[$_data])){
			return $_GET[$_data];
		}

	}

	/**
	* Set POST Variable
	* @param String & Int
	* @return True
	*/
	public function post($data) {

		$trim = trim(stripslashes(htmlspecialchars($data)));

		$_data = $this->filter_data($trim);

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
	private function filter_data($data) {

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