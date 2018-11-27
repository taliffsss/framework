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
}
?>