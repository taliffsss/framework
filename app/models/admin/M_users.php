<?php

class M_users extends My_Model {

	function __construct() {

		parent::__construct();
	}

	/**
	 * Get All users
	 * @return array string
	 */
	public function _getUser_all() {

		try {

			$this->db->query("SELECT CONCAT(e.first_name, ' ', e.middle_name, ' ', e.last_name) as fullname, e.emp_id, d.dept_name, sb.sub_dept_name, e.position, u.user_id, u.username FROM {$this->_ais_employee} e LEFT JOIN {$this->_ais_users} u ON u.emp_id = e.emp_id LEFT JOIN {$this->_ais_department} d ON d.dept_id = e.dept_id LEFT JOIN {$this->_ais_sub_department} sb ON e.sub_dept_id = sb.sub_dept_id WHERE e.emp_status = '1'");

			$this->db->execute();

			$row = $this->db->resultset();

			return $row;
			
		} catch (Exception $e) {

			$e->getMessage();
			
		}

	}

	/**
	 * Get user details based on userid
	 * @param int
	 * @return array string
	 */
	public function _getUserDetails($user_id){
		try {

			$this->db->query("SELECT * FROM {$this->_ais_employee} WHERE `user_id`=:user_id");

			$this->db->bind(':user_id', $user_id);

			$this->db->execute();

			$row = $this->db->single();

			return $row;

		} catch (Exception $e) {
			$e->getMessage();
		}
	}

	public function _getDeptHead_details($post){
		try {

			$this->db->query("SELECT * FROM {$this->_ais_employee} WHERE position=:post");

			$this->db->bind(':post', $post);

			$this->db->execute();

			$row = $this->db->single();

			return $row;

		} catch (Exception $e) {
			$e->getMessage();
		}
	}

	public function _updateHeadEmpID($emp_id){
		try {
				$this->db->query("UPDATE {$this->_ais_employee} SET head_manager=:emp_id");

				$this->db->bind(":emp_id",$emp_id);
				
				$this->db->execute();
				
				return true;

		} catch (Exception $e) {
			$e->getMessage();
		}
	}

	public function _get_manager_details($post){
		try {

			$this->db->query("SELECT emp_id, sub_dept_id FROM {$this->_ais_employee} WHERE position LIKE CONCAT('%', :post) AND sub_dept_id !=''");

			$this->db->bind(':post', $post);

			$this->db->execute();

			$row = $this->db->resultset();

			return $row;

		} catch (Exception $e) {
			$e->getMessage();
		}
	}

	public function _updateManagerEmpID($sub_dept_id, $emp_id){
		try {

			$this->db->query("UPDATE {$this->_ais_employee} SET manager=:emp_id WHERE sub_dept_id=:sub_dept_id AND emp_id!=:emp_id");

			$this->db->bind(':sub_dept_id', $sub_dept_id);
			$this->db->bind(':emp_id', $emp_id);

			$this->db->execute();
				
			return true;

		} catch (Exception $e) {
			$e->getMessage();
		}
	}
}
?>