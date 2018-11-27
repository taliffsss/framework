<?php
class SessionHandler { 
	private static $lifetime = 0; 

	private function __construct() { 
		session_set_save_handler(
			array($this,'open'),
			array($this,'close'),
			array($this,'read'),
			array($this,'write'),
			array($this,'destroy'),
			array($this,'gc')
		);
	}

	public function start($session_name = null) {
		session_start($session_name); //Start it here
	}

	public static function open() {
		//Connect to mysql, if already connected, check the connection state here.

		return true;
	}

	public static function read($id) {
		//Get data from DB with id = $id;
	}

	public static function write($id, $data) {
		//insert data to DB, take note of serialize
	}

	public static function destroy($id) {
		//MySql delete sessions where ID = $id
	}

	public static function gc() {
		return true;
	}

	public static function close() {
		return true;
	}
	
	public function __destruct() {
		session_write_close();
	}
}
?>