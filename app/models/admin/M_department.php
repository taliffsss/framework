<?php

class M_department extends My_Model {

	function __construct() {
		parent::__construct();
	}

	/**
	 * Get single Data of department
	 * @param Int
	 * @return String
	 */
	public function getDepartment($id) {

		return $this->fetch($this->_ais_department,'dept_id',$id);

	}

	/**
	 * Get single Data of department
	 * @param string
	 * @return string
	 */
	public function getDepartment_by_name($deptname) {

		return $this->fetch($this->_ais_department,'dept_name',$deptname);

	}

	/**
	 * Get single Data of department
	 * @param string
	 * @return string
	 */
	public function getSub_department_by_name($subdeptname) {

		return $this->fetch($this->_ais_sub_department,'sub_dept_name',$subdeptname);

	}

	/**
	 * Get single Data of department
	 * @param Int
	 * @return String
	 */
	public function getSub_department($id) {

		return $this->fetch($this->_ais_sub_department,'sub_dept_id',$id);

	}
}
?>