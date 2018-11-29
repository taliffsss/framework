<?php
use Mark\libraries\Constant;

class Report extends My_Controller {

	function __construct() {
		parent::__construct();

	}

	public function index() {
		if(Input::_method()) {

			$data = array(
						"category_id" => Input::post('category_name'),
						"date_from" => date("Y-m-d", strtotime(Input::post('date_from'))),
						"date_to" => date("Y-m-d", strtotime(Input::post('date_to'))),
						"status" => Constant::COMPLETED
					);

			$report_detailed = $this->_request->_getTransactionPerMonth($data);

			$purchased_detailed = $this->_purchase->_getPurchasedPerMonth($data);

			$output = NULL;
			
			foreach ($report_detailed as $key => $value) {
				$output .= "<tr> <td>".$value['request_code']."</td> <td>".Constant::ISSUED."</td> <td>".$value['created_at']."</td> <td>".$value['category_name']."</td> <td>".$value['item_name']."</td> <td>".$value['item_qty_issued']."</td> <td>".$value['first_name']." ".$value['last_name']."</td> <td>".$value['sub_dept_name']."</td>"
							."</tr>";
			}

			foreach ($purchased_detailed as $key => $value) {
				$output .= "<tr> <td></td> <td>".Constant::PURCHASED."</td> <td>".$value['created_at']."</td> <td>".$value['category_name']."</td> <td>".$value['item_name']."</td> <td>".$value['purchased_qty']."</td> <td>".$value['first_name']." ".$value['last_name']."</td> <td>".$value['sub_dept_name']."</td>"
							."</tr>";
			}
		}

		$data['detailed_row'] = (isset($output)) ? $output : '';
		$data['navbar'] = $this->_navigation_bar();
		$data['ticker'] = $this->ticker();
		$data['category'] = $this->_show_category();
		Common::view('admin/includes/header');
		Common::view('admin/pages/detailed-report',$data);
		Common::view('admin/includes/footer');
	}


	public function turnaround() {
		$data['navbar'] = $this->_navigation_bar();
		$data['ticker'] = $this->ticker();
		Common::view('admin/includes/header');
		Common::view('admin/pages/turnarround-report',$data);
		Common::view('admin/includes/footer');
	}

	public function summary() {
		$data['navbar'] = $this->_navigation_bar();
		$data['ticker'] = $this->ticker();
		Common::view('admin/includes/header');
		Common::view('admin/pages/summary-report',$data);
		Common::view('admin/includes/footer');
	}
}

?>