<?php 
use Mark\libraries\Constant;

	class Request extends My_Controller {
	

		function __construct(){
			parent::__construct();
		}

		public function index() {
			$data['navbar'] = $this->_navigation_bar();
			$data['ticker'] = $this->ticker();
			$data['requestCode'] = $this->_generateCode('REQ');
			$data['details'] = $this->display_cat_per_row();
			Common::view('admin/includes/header');
			Common::view('admin/pages/requisition-form',$data);
			Common::view('admin/includes/modal',$data);
			Common::view('admin/includes/footer');
		}

		/**
		* Display Category per row
		**/
		public function display_cat_per_row() {

			$this->_is_login();

			$category = $this->_category->get_all_category();

			$output = NULL;

			foreach ($category as $cat_val) {

				$output .= '<div class="card-header" id="'.$cat_val['category_id'].'" data-toggle="collapse" data-target="#'.$cat_val['category_code'].'Collapse" aria-expanded="true" aria-controls="'.$cat_val['category_code'].'Collapse">

								<h5 class="mb-0"> '.$cat_val['category_name'].'</h5>
							</div>

							<div id="'.$cat_val['category_code'].'Collapse" class="collapse" aria-labelledby="binders" data-parent="#accordion">
								<div class="card-body">
									<div class="row">';

				$item = $this->_stock->_get_stock_by_catid($cat_val['category_id']);

				if(!empty($item)) {
					 
					 foreach($item as $item_val) {

					  $_reserved_item = $this->_request->getTotal_qty_requested($item_val['item_id']);
					  $total_item_available = $item_val['current_stock'] - $_reserved_item['qty'];

					  $remarks = ($total_item_available > $item_val['reorder_point']) ? $total_item_available : false;

					  $output .= '<div class="col-sm-2 itemContainer">
					      <div class="itemName" itemid="'.$item_val['item_id'].'" code="'.$item_val['item_code'].'" iname="'.$item_val['item_name'].'">'.$item_val['item_name'].'</div>
						      

							      <div class="itemForm">
								       <div class="itemAvail">
								        '.(($remarks) ? $remarks . ' Available' : '<p style="color:red;">Item has insufficient number of stocks</p>').'
								       </div>

								       <button class="btnitem" '.(($remarks) ? 'onclick="this.parentNode.querySelector(\'input[type=number]\').stepDown()"' : 'disabled').'>
								        <i class="fa fa-minus"></i>
								       </button>

							       		<input type="number" name="qty_req" class="form-control" '.(($remarks) ? 'value="0" min="0" max="'.$total_item_available.'" readonly' : 'disabled').'>

								       <button class="btnitem" '.(($remarks) ? 'onclick="this.parentNode.querySelector(\'input[type=number]\').stepUp()"' : 'disabled').'>
								        <i class="fa fa-plus"></i>
								       </button>

							       	<button class="btn dashbtn" '.(($remarks) ? '' : 'disabled').'>Add Item</button>

							      </div>
						     </div>';

					 }
				}


			$output .= '</div>
				</div>
			</div>';


			}

			return $output;
		}


		public function submit_request(){
			/**
			* Status
			* Status = Approval Pending – Items Reserved. ; Queue = Manager
			* Status = Request Expired – No stocks Available ; Queue = Manager (if wasn’t approved within 24 hrs)
			**/
			$output = NULL;

			if(isset($_POST)) {

				$request_code = (isset($_POST['request_code'])) ? $_POST['request_code'] : '';
				$item = (isset($_POST['item_ids'])) ? $_POST['item_ids'] : array();
				$qty = (isset($_POST['qty'])) ? $_POST['qty'] : array();
				$code = (Input::post('code')) ? Input::post('code') : array();

				$ids = array ('request_code' => $request_code,
							   'item_ids' => $item);

				for($x = 0; $x < sizeof($item); $x++) {
					
					$data = array(
						'request_code' => $request_code,
						'dept_id' => Session::userdata('dept'),
						'sub_dept_id' => Session::userdata('sub_dept'),
						'emp_id' => Session::userdata('empid'),
						'item_id' => $item[$x],
						'item_qty_request' => $qty[$x],
						'request_date'=> date("Y-m-d H:i:s"),
						'request_exp_date'=> date("Y-m-d H:i:s"),
						'status'=> Constant::PENDING,
						'queue'=> 3,
						'created_at' => date("Y-m-d H:i:s"),
						'created_by' => Session::userdata('uid')
					);

					$output .= "
						<tr>
							<td style='width: 150px; padding: 5px 0; text-align: center'>".$code[$x]."</td>
							<td style='width: 150px; padding: 5px 0; text-align: center'>".$qty[$x]."</td>
						</tr>";

					$this->_request->insertTransaction($data);
				}

				$this->_activity_logs->_save_logs(array('module' => Constant::M_REQUEST, 'action' => Constant::A_CREATE, 'obj_ids' => json_encode($ids), 'obj_values' => json_encode($qty)));

				$this->send_email_to_manager($output,$request_code);
			}

		}

		/**
		* This is use to alert requester's manager thru email
		* @param array $data
		* @return Bool
		**/
		public function send_email_to_manager($data,$code){

			$empid = Session::userdata('empid');
			$res = $this->_request->_myMngrEmail($empid);
			$msg = 'Below are the requested item/s for approval. Please be informed that the requested item/s will be reserved for 24 hours <b>ONLY</b> until approved.';
			$body = $this->_mail_body($data,$res,$msg,$code);
			$from = 'admin-inventory-noreply@'.trim($_SERVER['SERVER_NAME']);

			$this->_email->setFrom($from,'Admin Inventory');
			$this->_email->setTo($res['mngrEmail']);
			$this->_email->setSubject("Inventory System Alert: For Approval");
			$this->_email->setMessage($body);
			$this->_email->send();
		}
	}

?>