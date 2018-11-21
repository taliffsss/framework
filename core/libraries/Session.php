<?php
namespace AIS\libraries;

/**
 * This class can set or start session and stored session in folder name Sessions 
 *
 * @package Session Class
 * @author 	Mark Anthony Naluz <anthony.naluz15@gmail.com>
 * @copyright Jul 2018 <Mark Anthony Naluz>
 */

class Session {

	function __construct() {

	}

	/**
	 * Set message to show
	 * @param string
	 * @param string
	 * @return  void 
	 */
	public function set_message($key, $value = NULL) {

		$this->set_userdata($key, $value);

	}

	/**
	 * Flash message to show
	 * @param string
	 * @return string 
	 */
	public function flash_message($key) {

		$data = $this->userdata($key);
		
		unset($_SESSION[$key]);

		return $data;


	}

	/**
	* Session Variable
	* @param    string  $key    Session data key
	* @return   mixed   Session data value or NULL if not found
	*/
	public function userdata($key) {

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
	public function set_userdata($data, $value = NULL) {

		if (is_array($data)) {
			foreach ($data as $key => &$value) {
				$_SESSION[$key] = $value;
			}
			return;
		}
		$_SESSION[$data] = $value;

	}

	/**
	* Logout
	* Destroy Session Variable
	*/
	public function logout() {

		if(!isset($_SESSION)) {
			session_start();
		}

		if (ini_get("session.use_cookies")) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000,
				$params["path"], $params["domain"],
				$params["secure"], $params["httponly"]
			);
		}
		session_destroy();
	}

	/**
	* Set to Start Session
	*/
	static function set_session() {
		Session::save_session();
		if(!isset($_SESSION)) {
			session_start();
		}

	}

	/**
	* Save Session Path
	*/
	static function save_session() {

		$dirName = $GLOBALS["config"]["path"]["app"]."sessions";

		if(!is_dir($dirName)) {
			mkdir($dirName,0755, true);
		}

		ini_set("session.save_handler", "files");
        ini_set("session.save_path", $dirName);
		
	}
}