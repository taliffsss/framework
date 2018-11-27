<?php
/**
 *
 * @package Activity Class
 * @author  Kristallynn
 */

class M_request extends My_Model {

	function __construct() {
		parent::__construct();
	}

	/**
	 * Get the SUM of QTY Requested based on Item ID
	 * @param Int
	 * @return Array
	 */
	public function getTotal_qty_requested($item_id) {
		try {
			
			$this->db->query("SELECT SUM(item_qty_request) qty FROM {$this->_ais_transaction} WHERE status IN ('Approval Pending – Items Reserved.' , 'Approved – For Preparation.', 'Ready for Issuance.') AND item_id= :item_id");

			$this->db->bind(":item_id", $item_id);

			$row = $this->db->single();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();

		}

	}

	/**
	 * Insert Item request 
	 * @param array
	 * @return String
	 */
	public function insertTransaction($data) {
		try {
			
				/**
				* Status
				* Status = Approval Pending – Items Reserved. ; Queue = Manager
				* Status = Request Expired – No stocks Available ; Queue = Manager (if wasn’t approved within 24 hrs)
				**/

				$this->db->query("INSERT INTO {$this->_ais_transaction} (request_code, dept_id, sub_dept_id, emp_id, item_id, item_qty_request, request_date, request_exp_date, status, queue, created_by, created_at) VALUES(:request_code, :dept_id, :sub_dept_id, :emp_id, :item_id, :item_qty_request, :request_date, :request_exp_date, :status, :queue, :created_by, :created_at)");

				$this->db->bind(":request_code", $data['request_code']);
				$this->db->bind(":dept_id", $data['dept_id']);
				$this->db->bind(":sub_dept_id", $data['sub_dept_id']);
				$this->db->bind(":emp_id", $data['emp_id']);
				$this->db->bind(":item_id", $data['item_id']);
				$this->db->bind(":item_qty_request",$data['item_qty_request']);
				$this->db->bind(":request_date",$data['request_date']);
				$this->db->bind(":request_exp_date",$data['request_exp_date']);
				$this->db->bind(":status",$data['status']);
				$this->db->bind(":queue",$data['queue']);
				$this->db->bind(":created_by",$data['created_by']);
				$this->db->bind(":created_at",$data['created_at']);
				
				$this->db->execute();
					
				return true;
			
		} catch (Exception $e) {

			$e->getMessage();

		}

	}

	public function _myMngrEmail($empid) {

		try {
			
			$this->db->query("SELECT CONCAT(first_name, ' ', middle_name, ' ', last_name) as requestor, (SELECT manager FROM {$this->_ais_employee} WHERE emp_id = :emp_id) as mngr, (SELECT email FROM {$this->_ais_users} WHERE emp_id = mngr) as mngrEmail, (SELECT CONCAT(first_name, ' ', middle_name, ' ', last_name) FROM ais_employee WHERE `emp_id` = mngr) as fullname FROM ais_employee WHERE emp_id = :emp_id");

			$this->db->bind(":emp_id",$empid);

			$row = $this->db->single();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();
			
		}
	}

	/**
	 * Update ais_transaction 
	 *  
	 * @param array
	 * @return String
	 */
	public function _update_status_completed($data) {
		try {
			
				/**
				* Status
				* Status = Completed. ; Queue = Requester
				**/

				$this->db->query("UPDATE {$this->_ais_transaction} SET received_by= :received_by, status=:status, queue=:queue, modified_by=:modified_by, modified_at=:modified_at WHERE request_code=:request_code");

				$this->db->bind(":received_by",$data['received_by']);
				$this->db->bind(":status",$data['status']);
				$this->db->bind(":queue",$data['queue']);
				$this->db->bind(":modified_by",$data['created_by']);
				$this->db->bind(":modified_at",$data['created_at']);
				$this->db->bind(":request_code", $data['request_code']);
				
				$this->db->execute();
					
				return true;
			
		} catch (Exception $e) {

			$e->getMessage();

		}

	}
	
}
?>