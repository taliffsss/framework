<?php

class M_Password_Reset extends My_Model {

	function __construct() {
		parent::__construct();
	}

	/**
	 * INSERT data from User table
	 * @param string $hash
	 * @param string $token
	 * @return true
	 */
	public function _insert_token($data) {

		try {
			
			$this->db->query("UPDATE {$this->_ais_users} SET reset_token = :token, reset_date = :date_time WHERE user_id = :user_id");

			$this->db->bind(":user_id",$data['userid']);
			$this->db->bind(":token",$data['token']);
			$this->db->bind(":date_time",$data['datetime']);

			$this->db->execute();

			return true;

		} catch (Exception $e) {
			
			$e->getMessage();

		}
	}

	/**
	 * Update data from User table
	 * @param string $hash
	 * @param string $token
	 * @return true
	 */
	public function _update_password($hash,$token) {

		try {
			
			$this->db->query("UPDATE {$this->_ais_users} SET reset_token = NULL, password = :hash, reset_date = NULL WHERE reset_token = :token");

			$this->db->bind(":hash",$hash);
			$this->db->bind(":token",$token);

			$this->db->execute();

			return true;

		} catch (Exception $e) {
			
			$e->getMessage();

		}
	}

	/**
	 * Update data from employee table
	 * @param string $email
	 * @return true
	 */
	public function _employee_locked_on($data) {
		
		try {
			
			$this->db->query("UPDATE {$this->_ais_users} SET status = 0, modified_at = :date_time, reset_date = :reset_date WHERE email = :email");

			$this->db->bind(":email",$data['email']);
			$this->db->bind(":date_time",$data['datetime']);
			$this->db->bind(":reset_date",$data['datetime']);

			$this->db->execute();

			return true;

		} catch (Exception $e) {
			
			$e->getMessage();

		}
	}

	/**
	 * Update data from employee table
	 * @param string $email
	 * @return true
	 */
	public function _employee_checked_qa($data) {
		
		try {
			
			$this->db->query("SELECT * FROM {$this->_ais_users} WHERE email = :email AND question_id = :question AND answer = :answer");

			$this->db->bind(":email",$data['email']);
			$this->db->bind(":question",$data['question']);
			$this->db->bind(":answer",$data['answer']);

			$this->db->execute();

			$row = $this->db->rowCount();

			return $row;

		} catch (Exception $e) {
			
			$e->getMessage();

		}
	}

	/**
	 * Get single Data in Users table
	 * @param Int $id
	 * @return string
	 */
	public function getData_id($id) {

		return $this->fetch($this->_ais_users,'user_id',$id);

	}

	/**
	 * Check if userid are existing in Users table
	 * @param int $id
	 * @return int
	 */
	public function _validate_userid($id) {
		return $this->count_column_results($this->_ais_users,'user_id',$id);
	}

	/**
	 * Check if already registered
	 * @param string
	 * @return int
	 */
	public function _check_if_reg($email) {

		try {
			
			$this->db->query("SELECT * FROM {$this->_ais_users} WHERE email = :email AND question_id IS NOT NULL AND answer IS NOT NULL");

			$this->db->bind(":email",$email);

			$this->db->execute();

			$row = $this->db->rowCount();

			return $row;

		} catch (Exception $e) {
			
			$e->getMessage();

		}
	}

	/**
	 * Get single Data of Employee
	 * @param string $email
	 * @return string
	 */
	public function getData($email) {

		return $this->fetch($this->_ais_users,'email',$email);

	}

	/**
	 * Check if data is greater than 24 hours SERVER TIMEZONE AMERICA/LOS_ANGELES 16 hours diff thats why i put 8 hours
	 * if has Unlocked User
	 * @param string
	 * @return boolean
	 */
	public function _unlocked_me($email) {

		try {

			$this->db->query("UPDATE {$this->_ais_users} SET status = 1, reset_date = NULL WHERE `reset_date` < DATE_SUB(NOW(),INTERVAL 8 HOUR) AND (email = :email OR username = :email)");

			$this->db->bind(":email",$email);

			$this->db->execute();

			return true;

		} catch (Exception $e) {

			$e->getMessage();

		}
	}

	/**
	 * Check if email are existing
	 * @param string $email
	 * @return int
	 */
	public function _validate_email($email) {
		return $this->count_column_results($this->_ais_users,'email',$email);
	}
}
?>