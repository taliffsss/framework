<?php

/**
 *
 * @package Activity Class
 * @author  Kristallynn
 */

class M_category extends My_Model {

	function __construct() {
		parent::__construct();
	}

	/**
	* Get all category
	*/
	public function get_all_category() {
		return $this->fetchall($this->_ais_category);
	}

	/**
	* Insert added category details
	* @param array 
	* @return boolean
	**/
	public function insert_category($data){
		try {
				
				$catcode = $this->setCode($data['catname']);

				$this->db->query("INSERT INTO {$this->_ais_category} (category_code, category_name, category_desc, created_by, created_at) VALUES(:category_code, :category_name, :category_desc, :created_by, :created_at)");

				$this->db->bind(":category_code", $catcode);
				$this->db->bind(":category_name",$data['catname']);
				$this->db->bind(":category_desc",$data['catdesc']);
				$this->db->bind(":created_by", $data['created_by']);
				$this->db->bind(":created_at",$data['created_at']);
				
				$this->db->execute();
				
								
				return true;

		} catch (Exception $e) {
			$e->getMessage();
		}
	}

	/**
	 * Count record column
	 * @param string
	 * @return int
	 */
	public function _cat_count($category_name) {
		return $this->count_column_results($this->_ais_category,'category_name',$category_name);
	}
	
}
?>