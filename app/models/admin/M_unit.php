<?php

class M_unit extends My_Model {

	function __construct() {
		parent::__construct();
	}

	/**
	 * Insert data into Unit table
	 * @param array string
	 * @return boolean
	 */
	public function _insert_data($data) {

		$itemcode = $this->setCode($data['unit_name']);

		try {

			$this->db->query("INSERT INTO {$this->_ais_unit} (unit_code, unit_name, unit_desc, created_by, created_at) VALUES(:unit_code, :unit_name, :unit_desc, :created_by, :created_at)");

			$this->db->bind(":unit_code", $itemcode);
			$this->db->bind(":unit_name",$data['unit_name']);
			$this->db->bind(":unit_desc",$data['unit_desc']);
			$this->db->bind(":created_by",$data['created_by']);
			$this->db->bind(":created_at",$data['created_at']);

			$this->db->execute();
				
			return true;
			
		} catch (Exception $e) {

			$e->getMessage();

		}
	}

	/**
	* Insert function for Unit (Category)
	* @param array 
	* @return boolean 
	**/
	public function insertCatUnit($data){

		return $this->db->insert($this->_ais_category_unit,$data);
		
	}

	/**
	 * Count record column
	 * @param string
	 * @return int
	 */
	public function _count($uname) {
		return $this->count_column_results($this->_ais_unit,'unit_name',$uname);
	}

	/**
	 * Get All Data
	 * @param string
	 * @return int
	 */
	public function _get_all() {
		return $this->fetchall($this->_ais_unit);
	}
}
?>