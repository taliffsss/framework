<?php

class M_activity_logs extends My_Model {

	function __construct() {
		parent::__construct();
	}

	/**
	 * Save logs in activity log
	 * @param array string
	 * @return bool
	 */
	public function _save_logs($data) {

		try {
			
			$date = date('Y-m-d H:i:s');
			$uid = Session::userdata('uid');

			$this->db->query("INSERT INTO {$this->_activity_log} (user_id,module,action,obj_ids,obj_values,date_audit) VALUES (:user_id,:module,:action,:obj_ids,:obj_values,:date_audit)");

			$this->db->bind(":module",$data['module']);
			$this->db->bind(":action",$data['action']);
			$this->db->bind(":obj_ids",$data['obj_ids']);
			$this->db->bind(":obj_values",$data['obj_values']);
			$this->db->bind(":user_id",$uid);
			$this->db->bind(":date_audit",$date);

			$this->db->execute();

			return true;

		} catch (Exception $e) {
			
			$e->getMessage();

		}
	}
}
?>