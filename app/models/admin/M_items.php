<?php
use Mark\libraries\Constant;

class M_items extends My_Model {

	function __construct() {
		parent::__construct();
	}

	/**
	 * Get Single data
	 * @param int
	 * @return string
	 */
	public function _getItem($code,$uid,$role) {

		try {

			$this->db->query("SELECT CONCAT(e.first_name, ' ', e.middle_name, ' ', e.last_name) as fullname, t.created_at, t.item_id, d.dept_name,i.item_name, i.item_description, t.item_qty_request, t.status, i.item_code, t.remarks FROM ais_item i LEFT JOIN ais_transaction t ON t.item_id = i.item_id LEFT JOIN ais_department d ON t.dept_id = d.dept_id LEFT JOIN ais_employee e ON t.created_by = e.user_id WHERE t.request_code = :code AND (t.created_by = :created_by OR t.queue = :queue)");

			$this->db->bind(':code',$code);
			$this->db->bind(':created_by',$uid);
			$this->db->bind(':queue',$role);

			$row = $this->db->resultset();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();
			
		}

	}

	/**
	 * Get single data
	 * @param string
	 * @return string
	 */
	public function _getID($code) {

		return $this->fetch($this->_ais_item,'item_code',$code);
	}

	/**
	 * Count how many issuance status
	 * Count for approval status
	 * Count reoder count
	 * Count Pending status
	 * @return int
	 */
	public function _user_tickerCount_all() {

		try {
			
			$issuance = Constant::ISSUANCE;
			$approved = Constant::APPROVED;
			$pending = Constant::PENDING;

			$this->db->query("SELECT (SELECT COUNT(*) FROM {$this->_ais_transaction} WHERE status = :issuance) as issuance, (SELECT COUNT(*) FROM {$this->_ais_transaction} WHERE status = :approved) as approved, (SELECT COUNT(*) FROM {$this->_ais_item} WHERE current_stock <= reorder_point) as reorderCount, (SELECT COUNT(*) FROM {$this->_ais_transaction} WHERE status = :pending) as pending");

			$this->db->bind(':issuance',$issuance);
			$this->db->bind(':approved',$approved);
			$this->db->bind(':pending',$pending);

			$this->db->execute();

			$row = $this->db->single();

			return $row;


		} catch (Exception $e) {
			
			$e->getMessage();

		}
	}

	/**
	 * Get category name & ID that reach in reorder point
	 */
	public function _ticker_reorderPoint() {

		try {
			
			$this->db->query("SELECT c.category_name, COUNT(i.category_id) as mycount FROM {$this->_ais_item} i INNER JOIN {$this->_ais_category} c ON i.category_id = c.category_id WHERE i.current_stock <= i.reorder_point GROUP BY i.category_id");

			$row = $this->db->resultset();

			return $row;

		} catch (Exception $e) {
			
			$e->getMessage();

		}
	}
}
?>