<?php

class M_login extends My_Model {

	function __construct() {
		parent::__construct();
	}

	/**
	 * Get single Data of department
	 * @param String
	 * @return String
	 */
	public function verifyUsername($uname) {

		try {
			
			$this->db->query("SELECT u.*, e.*, d.* FROM {$this->_ais_users} u INNER JOIN {$this->_ais_employee} e ON u.emp_id = e.emp_id INNER JOIN {$this->_ais_sub_department} d ON e.sub_dept_id = d.sub_dept_id WHERE u.username = :uname AND u.status = '1'");

			$this->db->bind(":uname",$uname);

			$row = $this->db->single();

			return $row;

		} catch (Exception $e) {
			
			$e->getMessage();

		}

	}

	/**
	 * Count record column
	 * @param string
	 * @return int
	 */
	public function _count($uname) {
		return $this->count_column_results($this->_ais_users,'username',$uname);
	}
}
?>