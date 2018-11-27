<?php

class M_role extends My_Model {

	function __construct() {
		parent::__construct();
	}

	/**
	 * Get single data
	 * @param int
	 * @return string
	 */
	public function _getRole($id) {

		return $this->fetch($this->_ais_role,'role_id',$id);

	}
}
?>