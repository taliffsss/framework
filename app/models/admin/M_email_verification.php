<?php

class M_email_verification extends My_Model {

	function __construct() {

		parent::__construct();

	}

	/**
	 * Validate if token is existed
	 * @param String
	 * @return String
	 */
	public function _validate_token($param) {

		try {

			$this->db->query("SELECT * FROM {$this->_ais_users} WHERE activation_token = :param OR reset_token = :param ");

			$this->db->bind(":param",$param);

			$row = $this->db->single();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();

		}

	}
}
?>