<?php

class Dashboard extends My_Controller {

	function __construct() {
		parent::__construct();
		if(!Session::userdata('logged_in')) Url::redirect('login');
	}

	public function index() {
		$data['units'] = $this->_show_units();
		$data['category'] = $this->_show_category();
		$data['navbar'] = $this->_navigation_bar();
		$data['ticker'] = $this->ticker();
		$data['stocks'] = $this->_stock_list();
		Common::view('admin/includes/header');
		Common::view('admin/pages/dashboard',$data);
		Common::view('admin/includes/modal',$data);
		Common::view('admin/includes/footer');

	}

	public function _stock_list() {

		$data = $this->_stock->_get_all_stocks();
		
		$output = NULL;

		foreach ($data as $val) {

			$output .= "<tr>
							<td class='tbl-cName'>{$val['category_name']}</td>
							<td class='tbl-uName'>{$val['unit_name']}</td>
							<td class='tbl-iName'>{$val['item_name']}</td>
							<td class='tbl-cDesc'>{$val['standard_qty']}</td>
							<td class='tbl-cDesc'>{$val['current_stock']}</td>
							<td class='tbl-cDesc'>{$val['reorder_point']}</td>
							<td>
					            <button class='btn dashbtn' onclick='_replinish(".$val['item_id'].")'>Replenish</button>
					            <button class='btn dashbtn' onclick='_item_details(".$val['item_id'].")'>Edit</button>
					        </td>
						  </tr>";
		}

		return $output;
	}
}

?>