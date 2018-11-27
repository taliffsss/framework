<?php
use Mark\libraries\Constant;

class M_request_logs extends My_Model {

	function __construct() {
		parent::__construct();
	}

	/**
	 * Update data
	 * @param array string
	 * @return Boolean
	 */
	public function _getApprovedAllUpdate($data) {

		try {

			if(Session::userdata('role') == 3) {
				$this->db->query("UPDATE {$this->_ais_transaction} SET status = :status, rejection_reason = :rejection_reason, modified_at = :modified_at, modified_by = :modified_by, item_qty_issued = :item_qty_issued, queue = :queue, issued_by = :issued_by, approved_by = :approved_by WHERE item_id = :item_id AND request_code = :request_code");
			} else {
				$this->db->query("UPDATE {$this->_ais_transaction} SET status = :status, rejection_reason = :rejection_reason, modified_at = :modified_at, modified_by = :modified_by, item_qty_issued = :item_qty_issued, queue = :queue, issued_by = :issued_by WHERE item_id = :item_id AND request_code = :request_code");
			}

			$this->db->bind(":status",$data['status']);
			$this->db->bind(":modified_at",$data['modified_at']);
			$this->db->bind(":modified_by",$data['modified_by']);
			if(Session::userdata('role') == 3) {
				
				$this->db->bind(":approved_by",$data['approved_by']);

			}
			$this->db->bind(":issued_by",$data['issued_by']);
			$this->db->bind(":item_id",$data['item_id']);
			$this->db->bind(":item_qty_issued",$data['item_qty_issued']);
			$this->db->bind(":rejection_reason",$data['rejection_reason']);
			$this->db->bind(":queue",$data['queue']);
			$this->db->bind(":request_code",$data['request_code']);

			$this->db->execute();

			return true;
			
		} catch (Exception $e) {
			
			$e->getMessage();

		}

	}

	/**
	 * Update single data
	 * @param array string
	 * @return Boolean
	 */
	public function _getOneRejectedUpdate($data) {

		try {

			$this->db->query("UPDATE {$this->_ais_transaction} SET remarks = :remarks, status = :status, modified_at = :modified_at, modified_by = :modified_by, issued_by = :issued_by, approved_by = :approved_by WHERE item_id = :item_id AND request_code = :request_code");

			$this->db->bind(":request_code",$data['request_code']);
			$this->db->bind(":remarks",$data['remarks']);
			$this->db->bind(":status",$data['status']);
			$this->db->bind(":approved_by",$data['approved_by']);
			$this->db->bind(":issued_by",$data['issued_by']);
			$this->db->bind(":modified_at",$data['modified_at']);
			$this->db->bind(":modified_by",$data['modified_by']);
			$this->db->bind(":item_id",$data['item_id']);

			$this->db->execute();

			return true;
			
		} catch (Exception $e) {
			
			$e->getMessage();

		}

	}

	/**
	 * Update status if expired
	 * @param string
	 * @return Boolean
	 */
	public function _getExpired($code) {

		try {

			$status = Constant::REQUEST_EXPIRED_NOT_AVAILABLE;

			$this->db->query("UPDATE {$this->_ais_transaction} SET status = :status WHERE request_code = :request_code");

			$this->db->bind(":status",$status);
			$this->db->bind(":request_code",$code);

			$this->db->execute();

			return true;
			
		} catch (Exception $e) {
			
			$e->getMessage();

		}
	}

	/**
	 * Get all data
	 * @return array string
	 */
	public function _getTransaction() {

		try {

			$queue = Session::userdata('role');
			$created_by = Session::userdata('uid');
			$status = Constant::DISAPPROVED;
			$expired = Constant::REQUEST_EXPIRED_NOT_AVAILABLE;

			$this->db->query("SELECT DISTINCT t.request_code, t.queue, t.status, t.request_date, t.modified_at, t.rejection_reason, t.created_by, CONCAT(e.first_name, ' ', e.middle_name, ' ', e.last_name) as fullname FROM {$this->_ais_transaction} t INNER JOIN {$this->_ais_employee} e ON t.created_by = e.user_id WHERE t.status != :status AND t.status != :expired AND (t.queue = :queue OR t.created_by = :created_by) GROUP BY t.request_code");

			$this->db->bind(":status",$status);
			$this->db->bind(":queue",$queue);
			$this->db->bind(":created_by",$created_by);
			$this->db->bind(":expired",$expired);

			$row = $this->db->resultset();

			return $row;
			
		} catch (Exception $e) {
			
			$e->getMessage();

		}

	}

	/**
	 * Get data
	 * @param int
	 * @return array string
	 */
	public function _getAllTransaction() {

		return $this->fetchall($this->_ais_transaction);

	}

	/**
	 * Get Single data
	 * @param int
	 * @return string
	 */
	public function _getOne($id,$uid,$role) {

		try {
			
			$issuance = Constant::ISSUANCE;
			$approval = Constant::APPROVED;
			$disapproved = Constant::DISAPPROVED;
			$complete = Constant::COMPLETED;

			$this->db->query("SELECT d.dept_name, CONCAT(e.first_name, ' ', e.middle_name, ' ', e.last_name) as requestedBy,
							(SELECT DISTINCT CONCAT(b.first_name, ' ', b.middle_name, ' ', b.last_name) FROM ais_transaction a INNER JOIN ais_employee b ON b.emp_id = a.approved_by WHERE (a.status != :disapproved OR a.status = :complete OR a.status = :mngr) AND (a.created_by = :created_by OR a.queue = :queue) AND a.request_code = :request_code) AS mngr, 
							(SELECT DISTINCT CONCAT(d.first_name, ' ', d.middle_name, ' ', d.last_name) FROM ais_transaction c INNER JOIN ais_employee d ON d.emp_id = c.issued_by WHERE (c.status = :recievedBy OR c.status = :complete) AND c.issued_by IS NOT NULL AND t.queue = '1' AND c.request_code = :request_code) AS admin,
							(SELECT CONCAT(e.first_name, ' ', e.middle_name, ' ', e.last_name) FROM ais_transaction t INNER JOIN ais_employee e ON t.received_by = e.emp_id WHERE t.request_code = :request_code AND (t.status = :recievedBy OR t.status = :complete)) as recievedBy,
							t.* FROM ais_transaction t INNER JOIN ais_employee e ON t.emp_id = e.emp_id LEFT JOIN ais_department d ON t.dept_id = d.dept_id WHERE t.request_code = :request_code AND (t.created_by = :created_by OR t.queue = :queue)");

			$this->db->bind(":request_code",$id);
			$this->db->bind(":recievedBy",$issuance);
			$this->db->bind(":complete",$complete);
			$this->db->bind(":disapproved",$disapproved);
			$this->db->bind(":created_by",$uid);
			$this->db->bind(":queue",$role);
			$this->db->bind(":mngr",$approval);

			$row = $this->db->single();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();
			
		}

	}

	/**
	 * Check if data is greater than 24 hours SERVER TIMEZONE AMERICA/LOS_ANGELES 16hours diff thats why i put 8 hours 
	 * @param array string
	 */
	public function _get24hoursData() {

		try {
			
			$pending = Constant::PENDING;

			$this->db->query("SELECT * FROM {$this->_ais_transaction} WHERE request_exp_date <= SUBDATE(CURRENT_DATE, INTERVAL 8 HOUR) AND status = :status");

			$this->db->bind(":status",$pending);

			$row = $this->db->resultset();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();
			
		}
	}

	function _getTransactionDetails($request_code){
		return $this->fetchall_by_clause($this->_ais_transaction,'request_code',$request_code);
	}
}
?>