<?php
namespace AIS\libraries;

class Token {

	public static function generate() {
		return Session::put(Config::get('session/token'), md5(uniqid()));
	}

	/**
	 * Check if token is being exist
	 * @param string
	 * @return bool
	 */
	public static function check($token) {
		$tokenName = Config::get('session/token');

		if(Session::has($tokenName) && $token = Session::get($tokenName)) {
			Session::delete($tokenName);
			return true;
		}

		return false;
	}
}

?>