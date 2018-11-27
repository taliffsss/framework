<?php

class M_registration extends My_Model {

	function __construct() {
		parent::__construct();
	}

	/**
	 * Update data in employee table
	 * @param Int
	 * @param String
	 * @return boolean
	 */
	public function _activate_data($token) {
		$active = 1;
		try {

			$this->db->query("UPDATE ais_employee e LEFT JOIN ais_users u ON u.emp_id = e.emp_id
				SET u.status = :status, u.activation_token = NULL, e.emp_status = :emp_status WHERE u.activation_token = :token");

			$this->db->bind(":token", $token);
			$this->db->bind(":status", $active);
			$this->db->bind(":emp_status", $active);

			$this->db->execute();

			return true;

		} catch (Exception $e) {

			$e->getMessage();

		}
	}

	/**
	 * Update data in employee table
	 * @param Int
	 * @param String
	 * @return boolean
	 */
	public function _employee($data) {

		try {

			$this->db->query("UPDATE ais_employee e LEFT JOIN ais_users u ON u.emp_id = e.emp_id
				SET u.email = :email, e.dept_id = :deptid,e.sub_dept_id = :subdeptid,u.username = :uname,u.password = :pass, u.question_id = :question, u.answer = :answer, u.activation_token = :activation_token, e.created_at = :created_at,e.created_by = :created_by 
					WHERE e.emp_id = :empid");

			$this->db->bind(":empid", $data['empid']);
			$this->db->bind(":email", $data['email']);
			$this->db->bind(":deptid", $data['deptid']);
			$this->db->bind(":subdeptid", $data['subdeptid']);
			$this->db->bind(":uname", $data['uname']);
			$this->db->bind(":pass", $data['hash_pass']);
			$this->db->bind(":question", $data['question']);
			$this->db->bind(":answer", $data['answer']);
			$this->db->bind(":created_at", $data['created_at']);
			$this->db->bind(":created_by", $data['created_by']);
			$this->db->bind(":activation_token", $data['token']);

			$this->db->execute();

			return true;

		} catch (Exception $e) {

			$e->getMessage();

		}
	}

	/**
	 * Check if employee id is existed
	 * @param Int
	 * @return String
	 */
	public function getEmpid($param) {

		return $this->count_column_results($this->_ais_employee,'emp_id',$param);

	}

	/**
	 * Get single data of employee
	 * @param Int
	 * @return String
	 */
	public function getSingleEmployee($param) {

		try {

			$this->db->query("SELECT e.*, u.* FROM {$this->_ais_employee} e LEFT JOIN {$this->_ais_users} u ON u.emp_id = e.emp_id WHERE e.emp_id = :param");

			$this->db->bind(":param",$param);

			$row = $this->db->single();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();

		}

	}

}
?>