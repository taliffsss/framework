<?php
use Mark\core\Database;

class Model {


	function __construct() {
		$this->db = Database::getIntance();
	}

}
?>