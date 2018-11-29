<?php
use Mark\core\Config;

class Session {

	private static $_start = false;

	/**
	 * Session message name for timer
	 * @var	string
	 */
	private static $start = 'start';

	/**
	 * Check if Session name is already exist
	 * @param string
	 * @return bool
	 */
	public static function has($name) {
		return isset($_SESSION[$name]) ? true : false;
	}

	/**
	 * Retrieve session variable
	 * @param string
	 * @return void
	 */
	public static function get($name) {
		return $_SESSION[$name];
	}

	/**
	 * Delete session variable
	 * @param string
	 * @return void
	 */
	public static function delete($name) {
		if(self::has($name)) {
			unset($_SESSION[$name]);
		}
	}

	/**
	 * Set message to show && time for unset session message
	 * @param string
	 * @param string
	 * @return  void 
	 */
	public static function setMessage($key, $value = NULL) {

		self::put($key, $value);
		self::put(self::$start, time());

	}

	/**
	 * Display message using session it will delete after 5 seconds display
	 * @param string
	 * @param string
	 * @return string
	 */
	public static function flash($name, $string = null) {

		if(self::has($name)) {
			$session = self::get($name);
			$start = time() - self::get('start');

			if($start < 5) {
				return $session;
			} else {
				self::delete($name);
			}
		}
	}

	/**
	 * Destroy session
	 */
	public static function destroy() {
		session_destroy();
	}

	/**
	 * Start Session
	 */
	public static function start() {

		Session::save();

		if(!isset($_SESSION)) {
			self::$_start = session_start();
		}

		return self::$_start;
	}

	/**
	 * Session Variable
	 * @param    string  $key    Session data key
	 * @return   mixed   Session data value or NULL if not found
	 */
	public static function userdata($key) {

		$data = NULL;

		if(isset($_SESSION[$key])){
			$data = $_SESSION[$key];
		}
		return $data;

	}

	/**
	 * Set userdata
	 *
	 * @param   mixed   $data   Session data key or an associative array
	 * @param   mixed   $value  Value to store
	 * @return  void
	 */
	public static function put($data, $value = NULL) {

		if (is_array($data)) {
			foreach ($data as $key => &$value) {
				$_SESSION[$key] = $value;
			}
			return;
		}
		$_SESSION[$data] = $value;

	}

	/**
	 * Set session save in file
	 */
	private static function save() {

		$dirName = Config::get('autoload','path/app')."sessions";

		if(!is_dir($dirName)) {
			mkdir($dirName,0755, true);
		}

		ini_set("session.save_handler", "files");
		ini_set("session.save_path", $dirName);
		
	}
}
?>