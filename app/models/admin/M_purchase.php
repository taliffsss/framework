<?php

class M_purchase extends My_Model {

	function __construct() {
		parent::__construct();
	}

	/**
	 * Get single Data of department
	 * @param String
	 * @return String
	 */
	public function _insert_purchase($data) {

		try {

			$this->db->query("INSERT INTO {$this->_ais_purchased} (item_id, purchased_qty, entry_note, created_by, created_at) VALUES (:item_id, :purchased_qty, :entry_note, :created_by, :created_at)");

			$this->db->bind(":item_id", $data['itemid']);
			$this->db->bind(":purchased_qty",$data['purchaseQuantity']);
			$this->db->bind(":entry_note",$data['entryNote']);
			$this->db->bind(":created_at",$data['created_at']);
			$this->db->bind(":created_by", $data['created_by']);
			
			$this->db->execute();

			return true;

		} catch (Exception $e) {
			$e->getMessage();
		}

	}

	/**
	 * Get Purchased items for certain month 
	 * @param array
	 * @return array 
	 */
	public function _getPurchasedPerMonth($data) {
		
		try {
			
			$this->db->query("SELECT p.*, item_name, category_name, first_name, last_name, sub_dept_name FROM {$this->_ais_purchased} p JOIN {$this->_ais_item} i ON p.item_id=i.item_id JOIN {$this->_ais_category} c ON i.category_id=c.category_id JOIN ais_employee e ON p.created_by=e.user_id JOIN ais_sub_department sub ON e.sub_dept_id=sub.sub_dept_id WHERE i.category_id=:category_id AND p.created_at BETWEEN :date_from AND :date_to");

			$this->db->bind(":category_id",$data['category_id']);
			$this->db->bind(":date_from",$data['date_from']." 00:00:00");
			$this->db->bind(":date_to",$data['date_to']." 23:59:59");

			$this->db->execute();

			$row = $this->db->resultset();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();
			
		}

	}
}
?>