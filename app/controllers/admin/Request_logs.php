<?php
use Mark\libraries\Constant;

class Request_logs extends My_Controller {

	function __construct() {
		parent::__construct();
		$this->_expired_request();
	}

	/**
	 * homepage of request logs
	 */
	public function index() {
		$data['ticker'] = $this->ticker();
		$data['navbar'] = $this->_navigation_bar();
		$data['transaction'] = $this->get_all_request_logs();
		Common::view('admin/includes/header');
		Common::view('admin/pages/request-logs',$data);
		Common::view('admin/includes/footer');
	}


	public function view() {
		$data['ticker'] = $this->ticker();
		$data['navbar'] = $this->_navigation_bar();
		$data['transaction'] = $this->getSingleTransaction();
		$data['print'] = $this->_printTemplate();
		Common::view('admin/includes/header');
		Common::view('admin/pages/request-logs',$data);
		Common::view('admin/includes/footer');
	}

	/**
	 * Get All transaction
	 * @return string
	 */
	public function get_all_request_logs() {
		$data = $this->_request_logs->_getTransaction();

		$thName = (Session::userdata('role') == 1 || Session::userdata('role') == 3) ? '<th>Requestor</th>' : '';

		$output = '
			<div class="col-sm-9 cont"><h4 class="title">Request Logs</h4><table id="reqlog_list" class="table">
			<thead><tr><th>Request ID</th>'.$thName.'<th>Date Requested</th><th>Queue</th><th>Status</th><th>Last Updated</th><th>Remarks(if any)</th><th>Action</th></tr></thead><tbody>
		';
		
		foreach ($data as $val) {
			$queue = $this->_role->_getRole($val['queue']);
			$button = ($val["status"] == 'Ready for Issuance') ? '<button class="btn dashbtn btn-'.$val["request_code"].'" onclick="_update_inventory(\''.$val["request_code"].'\')">Update Inventory</button>' : '';

			
			$role = (Session::userdata('role') == 1) ? '<td>'.$button.'</td>' : '<td></td>';
			$modified = ($val["modified_at"] != NULL) ? date('M d, Y g:i:s A', strtotime($val["modified_at"])) : '';
			$rName = (Session::userdata('role') == 1 || Session::userdata('role') == 3) ? '<td>'.$val["fullname"].'</td>' : '';

			$output .= '
				<tr>
					<td><u><a href="'.Url::baseUrl().'/request-logs/view/'.$val["request_code"].'" class="request-logs">'.$val["request_code"].'</a></u></td>
					'.$rName.'
					<td>'.date('M d, Y g:i:s A', strtotime($val["request_date"])).'</td>
					<td>'.$queue["role"].'</td>
					<td>'.$val["status"].'</td>
					<td>'.$modified.'</td>
					<td>'.$val["rejection_reason"].'</td>
					'.$role.'
				</tr>
			';

		}
		$output .= '
			</tbody></table></div>
		';

		return $output;
	}

	/**
	 * Get Single Data
	 * @param int
	 * @return string
	 */
	public function getSingleTransaction() {

		$code = Url::uri_segment(4);
		$data = $this->_item->_getItem($code,Session::userdata('uid'),Session::userdata('role'));
		$stat = $this->_request_logs->_getOne($code,Session::userdata('uid'),Session::userdata('role'));

		if(is_array($data) && (count($data) > 0)) {

			$approved = ($stat['status'] == Constant::APPROVED && Session::userdata('role') == 1) ? 'Issue Request' : 'Approved';

			$status = ($stat['status'] == Constant::REQUEST_EXPIRED_NOT_AVAILABLE || $stat['status'] == Constant::ISSUANCE) ? 'Disabled' : '';

			$staff = ($stat['status'] == Constant::ISSUANCE || $stat['status'] == Constant::COMPLETED) ? '' : '<a href="'.Url::baseUrl().'/'.Url::uri_segment(2).'" class="btn btn-secondary" style="margin-right: 20px;">Cancel</a><button class="btn btn-danger" type="button" id="btnreject" style="margin-right: 20px;" onclick="_rejected_all(\''.$code.'\')" '.$status.'>Reject</button><button class="btn thmBtn" id="btnapproved" type="button" onclick="_approved_all(\''.$code.'\')" '.$status.'>'.$approved.'</button>';

			$mystaff = (Session::userdata('role') == 2) ? '' : $staff;

			$_btns = ($stat['status'] == Constant::COMPLETED && Session::userdata('role') == 2) ? '<button class="btn thmBtn" id="btnapproved" type="button" onclick="_completed_print_me()">Print</button>' : $mystaff;

			$_issuance = (Session::userdata('role') == 1 || Session::userdata('role') == 3) ? '<a class="btntrans" onclick="this.parentNode.querySelector(\'input[type=number]\').stepDown()" ><i class="fa fa-minus"></i></a>' : '';

			$role = ($stat['status'] == Constant::ISSUANCE || $stat['status'] == Constant::COMPLETED) ? '' : $_issuance;

			$action = (Session::userdata('role') == 1 || Session::userdata('role') == 3) ? 'Action' : '';

			$output = '
				<div class="col-sm-9 cont">
					<form method="POST" action="" id="formAction" onsubmit="return _reject_all(\''.$code.'\')"><h4 class="title">Store & Supplies Requisition Form</h4><div class="m_detail"><div class="row justify-content-center"><div class="col-md-4 "><div class="frm_div"><label>Request ID:</label><input class="frm_inpt" type="text" id="req_id" value="'.$code.'" readonly /></div></div></div><div class="row justify-content-between"><div class="col-md-3 "><div class="frm_div"><label>Department:</label><input class="frm_inpt" type="text" id="dept" value="'.$stat['dept_name'].'" readonly/></div></div><div class="col-md-3 "><div class="frm_div"><label>Date:</label><input class="frm_inpt" type="text" id="date" value="'.date('M d, Y', strtotime($stat["created_at"])).'" readonly/></div></div></div></div>

					<table class="table"><thead><tr><th>Item/Code No</th><th>Description</th><th>Qty/Unit Required</th><th>Qty Issued</th><th>Remarks</th><th style="text-align: center">'.$action.'</th></tr></thead><tbody>
			';

			foreach ($data as $val) {

				$disabled = ($val['status'] == Constant::DISAPPROVED) ? 'Disabled style="color: red;"' : '';

				$remarks = ($val['status'] == Constant::DISAPPROVED) ? $val['remarks'] : '';

				$btnrejected = ($val['status'] == Constant::DISAPPROVED) ? 'Rejected' : 'Reject';

				$btn = (Session::userdata('role') == 1 || Session::userdata('role') == 3) ? '<input type="button" class="btn dashbtn request-item" id="btn-'.$val["item_code"].'" value="'.$btnrejected.'" onclick="_rejected_yes_No(\''.$val["item_code"].'\','.$val["item_id"].')" '.$disabled.$status.'>' : '';

				$staffbtn = (Session::userdata('role') == 2 || $val['status'] == Constant::COMPLETED) ? '' : $btn;

				$textarea = (Session::userdata('role') == 2 || $val['status'] == Constant::COMPLETED) ? '' : '<textarea class="form-control" placeholder="State your reason..." id="'.$val["item_code"].'" '.$disabled.$status.'>'.$remarks.'</textarea>';

				$_stats = ($val['status'] == Constant::DISAPPROVED || $val['status'] == Constant::REQUEST_EXPIRED_NOT_AVAILABLE) ? '0' : $val["item_qty_request"];

				$output .= '
					<input class="code-hidden" id="code-'.$val["item_id"].'" type="hidden" value="'.$code.'" readonly '.$disabled.'/>
					<tr>
						<input type="hidden" name="itemName[]" class="form-control" value="'.htmlspecialchars($val["item_name"]).'">
						<input class="hidden-'.$val["item_code"].'" id="item-'.$val["item_id"].'" name="item_id[]" type="hidden" value="'.$val["item_id"].'" '.$status.' '.$disabled.' />
						<input class="btn-status" value="'.$val['status'].'" type="hidden"/>
						<td id="td-'.$val["item_id"].'">'.$val["item_name"].'</td>
						<td>'.$val["item_description"].'</td>
						<td>'.$val["item_qty_request"].'</td>
						<td class="td-transac">'.$role.'&nbsp;<input type="number" name="issued[]" class="trans-val" id="trans-val-'.$val["item_code"].'" min="0" value="'.$_stats.'" readonly></td>
						<td>'.$textarea.'</td>
						<td style="text-align: center">
							'.$staffbtn.'
						</td>
					</tr>
				';
			}

			$_st = (Session::userdata('role') == 1) ? Constant::ISSUANCE : Constant::APPROVED;

			$output .= '
				</tbody></table>
						<div class="m_details_btm"><div class="row justify-content-between"><div class="col-md-4 "><div class="frm_div"><label>Requested By:</label><input class="frm_inpt" type="text" id="reqBY" value="'.$stat["requestedBy"].'" readonly/></div></div><div class="col-md-4 "><div class="frm_div"><label>Approved By(DPH):</label><input class="frm_inpt" type="text" id="approvedBy" value="'.$stat["mngr"].'" readonly/></div></div></div><div class="row justify-content-between"><div class="col-md-4 "><div class="frm_div"><label>Issued By:</label><input class="frm_inpt" type="text" id="issuedBy" value="'.$stat["admin"].'" readonly/></div></div><div class="col-md-4 "><div class="frm_div"><label>Received By:</label><input class="frm_inpt" type="text" id="recievedBy" value="'.$stat["recievedBy"].'" readonly/></div></div></div></div>
						<div class="modal fade" id="modal_yN">
						<div class="modal-dialog" role="document">
							<input type="hidden" name="status" value="'.$_st.'">
							<input type="hidden" name="queue" value="1">
							<div class="modal-content">
								<div class="modal-header">
									<h5 class="modal-title" id="modal_header"></h5>
								</div>
								<div class="modal-body" id="modal_body">
								</div>
								<div class="modal-footer" id="modal_Footer">
								</div>
							</div>
						</div>
					</div>
					<div class="modal fade" id="rejected_yN">
						<div class="modal-dialog" role="document">
							<div class="modal-content">
								<div class="modal-header">
									<h5 class="modal-title" id="rejected_header"></h5>
								</div>
								<div class="modal-body" id="rejected_body">
									<textarea class="form-control" id="reject-reason" name="rejection_reason" placeholder="State your reason..."></textarea>
								</div>
								<div class="modal-footer" id="rejected_Footer">
								</div>
							</div>
						</div>
					</div>
						<div class="row  justify-content-center"><div class="col-md-4" style="text-align: center; margin-top: 20px;">
							'.$_btns.'</div></div>
					</form></div>
					';

			return $output;
		} else {
			Url::redirect('error/404.html');
		}
	}

	public function _printTemplate() {

		$code = Url::uri_segment(4);

		$stat = $this->_request_logs->_getOne($code,Session::userdata('uid'),Session::userdata('role'));

		$data = $this->_item->_getItem($code,Session::userdata('uid'),Session::userdata('role'));

		$output = '<div id="printMe" style="background: #fff; min-height: 100%; padding: 30px; display: none; font-family: "Open Sans", sans-serif; font-size: 14px;">
			<h4 style="text-align: center; font-size: 1.5rem; margin-bottom: .5rem; font-weight: 500; line-height: 1.2;">Store & Supplies Requisition Form</h4>
			<div>
				<div style="width: 420px; margin: 0 auto; text-align: center">
	              <label>Request ID:</label>
	              <input type="text" id="req_id" value="'.$code.'" style="border: none; border-bottom: 1px solid #000;padding: 0 10px;width: 50%; margin: 5px 10px;"/>
				</div>
				<div style="display: flex; -webkit-box-pack: justify !important;-ms-flex-pack: justify !important; justify-content: space-between !important;">
					<div style="width: 25%; padding-left: 15px; padding-right: 15px;">
						<div style="width: 420px; padding: 10px;">
							<label>Department:</label>
			              <input type="text" value="'.$stat['dept_name'].'" id="dept" style="border: none; border-bottom: 1px solid #000;padding: 0 10px;width: 50%; margin: 5px 10px;"/>
			            </div>
					</div>
					<div style="width: 25%; padding-left: 15px; padding-right: 15px;">
						<div style="width: 420px; padding: 10px;">
			              <label>Date:</label>
			              <input type="text" id="dept" value="'.date('M d, Y', strtotime($stat["created_at"])).'" style="border: none; border-bottom: 1px solid #000;padding: 0 10px;width: 50%; margin: 5px 10px;"/>
			            </div>
					</div>
				</div>
			</div>
	 		<table style="width: 100%; max-width: 100%; background-color: transparent; margin-bottom: 1rem;">
			    <thead style="color: #000;">
			        <tr>
			            <th style="padding: .75rem; border-top: 1px solid #dee2e6;">Item/Code No</th>
			            <th style="padding: .75rem; border-top: 1px solid #dee2e6;">Description</th>
			            <th style="padding: .75rem; border-top: 1px solid #dee2e6;">Qty/Unit Required</th>
			            <th style="padding: .75rem; border-top: 1px solid #dee2e6;">Qty Issued</th>
			            <th style="padding: .75rem; border-top: 1px solid #dee2e6;">Remarks</th>
			        </tr>
			    </thead>
			    <tbody>';

			    foreach ($data as $val) {

			    	$_stats = ($val['status'] == Constant::DISAPPROVED || $val['status'] == Constant::REQUEST_EXPIRED_NOT_AVAILABLE) ? '0' : $val["item_qty_request"];

			    	$output .= '
			    	<tr>
			            <td style="padding: .75rem; vertical-align: top; border-top: 1px solid #dee2e6;">'.$val["item_name"].'</td>
			            <td style="padding: .75rem; vertical-align: top; border-top: 1px solid #dee2e6;">'.$val["item_description"].'</td>
			            <td style="padding: .75rem; vertical-align: top; border-top: 1px solid #dee2e6;">'.$val["item_qty_request"].'</td>
			            <td style="padding: .75rem; vertical-align: top; border-top: 1px solid #dee2e6;">'.$_stats.'</td>
			            <td style="padding: .75rem; vertical-align: top; border-top: 1px solid #dee2e6;"></td>
			        </tr>';
			    }
			        
		$output .= '</tbody>
			</table>
	 		<div>
				<div style="display: flex; -webkit-box-pack: justify !important;-ms-flex-pack: justify !important; justify-content: space-between !important;">
					<div style="flex: 0 0 33.333333%; max-width: 33.333333%; padding-right: 15px; padding-left: 15px;">
						<div style="padding: 10px; width: 420px;">
			              <label>Requested By:</label>
			              <input type="text" id="dept" value="'.$stat["requestedBy"].'" style="border: none; border-bottom: 1px solid #000;padding: 0 10px;width: 50%; width: 100%; margin: 5px 0;"/>
			            </div>
					</div>
					<div style="flex: 0 0 33.333333%; max-width: 33.333333%; padding-right: 15px; padding-left: 15px;">
						<div style="padding: 10px; width: 420px;">
			              <label>Approved By(DPH):</label>
			              <input type="text" id="dept" value="'.$stat["mngr"].'" style="border: none; border-bottom: 1px solid #000;padding: 0 10px;width: 50%; width: 100%; margin: 5px 0;"/>
			            </div>
					</div>
				</div>
				<div style="display: flex; -webkit-box-pack: justify !important;-ms-flex-pack: justify !important; justify-content: space-between !important;">
					<div style="flex: 0 0 33.333333%; max-width: 33.333333%; padding-right: 15px; padding-left: 15px;">
						<div style="padding: 10px; width: 420px;">
			              <label>Issued By:</label>
			              <input type="text" value="'.$stat["admin"].'" id="dept" style="border: none; border-bottom: 1px solid #000;padding: 0 10px;width: 50%; width: 100%; margin: 5px 0;"/>
			            </div>
					</div>
					<div style="flex: 0 0 33.333333%; max-width: 33.333333%; padding-right: 15px; padding-left: 15px;">
						<div style="padding: 10px; width: 420px;">
			              <label>Received By:</label>
			              <input type="text" id="dept" value="'.$stat["recievedBy"].'" style="border: none; border-bottom: 1px solid #000;padding: 0 10px;width: 50%; width: 100%; margin: 5px 0;"/>
			            </div>
					</div>
				</div>
			</div>
		</div>';
		
		$myOutput = ($stat['status'] == Constant::COMPLETED && Session::userdata('role') == 2) ? $output : '';
		
		return $myOutput;
	}

	/**
	 * Manager Approved all request
	 */
	public function approved_all() {
		
		if($_POST) {

			$code = Url::uri_segment(4);
			$item = ($this->input->post('item_id')) ? $this->input->post('item_id') : '';
			$iName = ($this->input->post('itemName')) ? $this->input->post('itemName') : '';
			$qty = ($this->input->post('issued')) ? $this->input->post('issued') : '';
			$mngr = (Session::userdata('role') == 3) ? Session::userdata('empid') : NULL;
			$admin = (Session::userdata('role') == 1) ? Session::userdata('empid') : NULL;
			$output = NULL;

			if(!empty($item)) {
				for($x = 0; $x < sizeof($item); $x++) {

					$data = array(
						'rejection_reason' => NULL,
						'queue' => $this->input->post('queue'),
						'request_code' => $code,
						'item_id' => $item[$x],
						'item_qty_issued' => $qty[$x],
						'approved_by' => $mngr,
						'issued_by' => $admin,
						'status' => $this->input->post('status'),
						'modified_at' => date("Y-m-d H:i:s"),
						'modified_by' => Session::userdata('uid')
					);

					$output .= "
						<tr>
							<td style='width: 150px; padding: 5px 0; text-align: center'>".$iName[$x]."</td>
							<td style='width: 150px; padding: 5px 0; text-align: center'>".$qty[$x]."</td>
						</tr>";

					if($this->_request_logs->_getApprovedAllUpdate($data)) {
						
						if(Session::userdata('role') == 1) {
							Session::set_message('success',"Item/s listed on  <b>".Url::uri_segment(4)."</b>  is ready for issuance");
						}
						Url::redirect('request-logs');
					}
				}
				foreach ($_POST['item_id'] as $key) {

					$itemIDs[] = $key;

				}
				foreach ($_POST['issued'] as $key) {

					$qtys[] = $key;

				}
				$itemID = implode(',',$itemIDs);
				$qty = implode(',',$qtys);

				if(Session::userdata('role') == 3) {
					$this->send_email_to_admin($output,$code);
				}

				$this->_activity->_save_logs(array('module' => Constant::M_REQUEST_LOG, 'action' => Constant::A_APPROVED, 'obj_ids' => $itemID, 'obj_values' => $qty));
			} else {
				Url::redirect('request-logs');
			}
		}
	}

	/**
	* Send email to admin
	* @param array $data
	* @return Bool
	**/
	public function send_email_to_admin($data,$code){

		$empid = Session::userdata('empid');
		$res = $this->_request->_myMngrEmail($empid);
		$msg = 'Approving below requested item/s below. For you issuance, please.';
		$body = $this->_mail_body($data,$res,$msg,$code);
		$from = 'admin-inventory-noreply@'.trim($_SERVER['SERVER_NAME']);

		$this->_email->setFrom($from,'Admin Inventory');
		$this->_email->setTo('KristelDiana.Catral@megasportsworld.com');
		$this->_email->setSubject("Inventory System Alert: For Approval");
		$this->_email->setMessage($body);
		$this->_email->send();
	}

	/**
	 * Reject all request
	 */
	public function reject_all() {
		
		if($_POST) {
			$item = (isset($_POST['item_id'])) ? $_POST['item_id'] : '';
			$qty = (isset($_POST['issued'])) ? $_POST['issued'] : '';
			$mngr = (Session::userdata('role') == 3) ? Session::userdata('empid') : NULL;
			$admin = (Session::userdata('role') == 1) ? Session::userdata('empid') : NULL;

			if(!empty($item)) {
				for($x = 0; $x < sizeof($item); $x++) {

					$data = array(
						'rejection_reason' => $this->input->post('rejection_reason'),
						'queue' => Session::userdata('role'),
						'request_code' => Url::uri_segment(4),
						'item_id' => $item[$x],
						'approved_by' => $mngr,
						'issued_by' => $admin,
						'item_qty_issued' => 0,
						'status' => Constant::DISAPPROVED,
						'modified_at' => date("Y-m-d H:i:s"),
						'modified_by' => Session::userdata('uid')
					);
					if($this->_request_logs->_getApprovedAllUpdate($data)) {
						Url::redirect('request-logs');
					}
				}
			} else {
				Url::redirect('request-logs');
			}
		}
	}

	/**
	 * Update Transation for rejection (ajax call)
	 * @return Boolean
	 */
	public function rejected() {

		$mngr = (Session::userdata('role') == 3) ? Session::userdata('empid') : NULL;
		$admin = (Session::userdata('role') == 1) ? Session::userdata('empid') : NULL;

		if($_POST) {
			$code = $this->input->post('code');
			$res = $this->_item->_getID($code);
			$data = array(
				'request_code' => $this->input->post('transcode'),
				'item_id' => $res['item_id'],
				'approved_by' => $mngr,
				'issued_by' => $admin,
				'remarks' => $this->input->post('myid'),
				'status' => Constant::DISAPPROVED,
				'modified_at' => date("Y-m-d H:i:s"),
				'modified_by' => Session::userdata('uid')
			);

			$this->_request_logs->_getOneRejectedUpdate($data);

		}
	}

	/**
	 * Check if request is 24hours old if has update status in expired
	 */
	public function _expired_request() {

		$data = $this->_request_logs->_get24hoursData();

		foreach ($data as $val) {
			$this->_request_logs->_getExpired($val['request_code']);
		}

	}

	/**
	* When ADMIN clicked UPDATE INVENTORY button 
	* Decrease CURRENT_STOCK from ais_item table
	* Update status to COMPLETED and queue to REQUESTER from ais_request_logs table 
	*/
	public function _deduct_inventory(){
		
		$request_code = $this->input->post('reqcode');

		//Get details of transaction based on REQUEST_CODE
		$transaction_arr = $this->_request_logs->_getTransactionDetails($request_code);

		foreach ($transaction_arr as $key => $item) {
			//Get current_stock of item based on ITEM ID
			$current_stock = $this->_stock->_get_stock_by_id($item['item_id']);

			$item_to_decrease = array(
								"item_id"=> $item['item_id'],
								"new_stock"=> $current_stock['current_stock'] - $item['item_qty_issued'],
								"modified_by"=> Session::userdata('uid'),
								"modified_at"=> date("Y-m-d H:i:s")
							);

			//decrease item picked up
			$this->_stock->_decrease_item($item_to_decrease);
		}
		
		//2 = Requester
		$data = array(
				'request_code' => $request_code,
				'received_by'=> $transaction_arr[0]['created_by'],
				'status'=> Constant::COMPLETED,
				'queue'=> 2,
				"modified_by"=> Session::userdata('uid'),
				"modified_at"=> date("Y-m-d H:i:s")
				);

		//update status and queue 
		$this->_m_request->_update_status_completed($data);

	}

}

?>