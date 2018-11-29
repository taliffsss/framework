<?php 

class Stock extends My_Controller {

	function __construct() {
		parent::__construct();
	}

	/**
	* Display Category list 
	**/
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

	/**
	 * Replacement HTML Output
	 */
	public function _replinish_template() {

		if($_POST) {

			$id = Input::post('code');

			$_data = $this->_stock->_get_stock_by_id($id);

			if(is_array($_data) || (count($_data) > 0)) {

				$_arr = array('itemCode' => $_data['item_code'],'catName' => $_data['category_name'],'itemName' => $_data['item_name'],'standard' => $_data['standard_qty'],'reorder' => $_data['reorder_point'],'c_count' => $_data['current_stock'], 'uName' => $_data['unit_name'], 'itemid' => $id, 'msg' => Constant::SUCCESSFULL);
				echo json_encode($_arr);

			} else {

				$_arr = array('msg' => Constant::FAILED);
				echo json_encode($_arr);

			}
		}

	}

	/**
	 * Update Item & insert data in purchase table
	 */
	public function _replenish_update() {
		if($_POST) {

			$itemID = Input::post('itemID');
			$curStock = Input::post('itemCurrentStocks');
			$purQuan = Input::post('purchaseQuantity');

			$item = array(
				'itemid' => $itemID,
				'itemCurrentStocks' => $curStock,
				'purchaseQuantity' => $purQuan,
				'modified_at' => date('Y-m-d H:i:s'),
				'modified_by' => $this->session->userdata('uid')
			);

			$purchase = array(
				'itemid' => $itemID,
				'purchaseQuantity' => $purQuan,
				'entryNote' => Input::post('entryNote'),
				'created_at' => date('Y-m-d H:i:s'),
				'created_by' => $this->session->userdata('uid')
			);

			$_data = $this->_stock->_update_item($item);

			if($_data) {
				$this->_activity->_save_logs(array('module' => Constant::M_STOCKS, 'action' => Constant::A_UPDATE, 'obj_ids' => $itemID, 'obj_values' => $curStock));
				if($this->_purchase->_insert_purchase($purchase)) {
					$this->_activity->_save_logs(array('module' => Constant::M_STOCKS, 'action' => Constant::A_CREATE, 'obj_ids' => $itemID, 'obj_values' => $purQuan));
				}
			} else {
				header("HTTP/1.0 404 Not Found");
				exit;
			}
		}
	}

	/**
	* Display Unit name list 
	* @param $category
	**/
	public function opt_unit(){
		$id = Url::uri_segment(4);
		echo $this->_get_unit_name($id);
	}

	/**
	 * Check category name if existing or not (ajax call)
	 * @param String
	 * @return Boolean
	 */
	public function _check_item() {
		$iname = Input::post('iname');

		if(is_string($iname)) {

			$row = $this->_m_stock->_item_count($iname);

			if($row > 0) {
				$arr = array('msg' => Constant::ITEM);
				echo json_encode($arr);
			} else {
				$arr = array('msg' => Constant::FAILED);
				echo json_encode($arr);
			}

		} else {
			header("HTTP/1.0 404 Not Found");
			exit;
		}
	}


	/**
	* Add New Item function 
	**/
	public function add_item() {

		if(Input::_method()) {
			$catid = Input::post('category_name');
			$unitid = Input::post('unit_name');
			$itemname = Input::post('iname');
			$itemdesc = Input::post('idesc');
			$standard_qty = Input::post('s__qty');
			$reorder_point = Input::post('r__pt');
			
			$data = array(
				'catid' => $catid,
				'unitid' => $unitid,
				'itemname' => $itemname,
				'itemdesc' => $itemdesc,
				'standard_qty' => $standard_qty,
				'reorder_point' => $reorder_point,
				'created_at' => date("Y-m-d H:i:s"),
				'created_by' => Session::userdata('uid')
			);

			$this->_stock->insert_item($data);
		}
	}

	/**
	 * Replacement HTML Output
	 */
	public function _item_template() {

		if($_POST) {

			$id = Input::post('item_id');

			$_data = $this->_stock->_get_stock_by_id($id);

			if(is_array($_data) || (count($_data) > 0)) {

				$_arr = array('cName' => $_data['category_name'],'uName' => $_data['unit_name'], 'iName' => $_data['item_name'],'standard' => $_data['standard_qty'],'reorder' => $_data['reorder_point'],'desc' => $_data['item_description'], 'itemid' => $id, 'msg' => Constant::SUCCESSFULL);
				echo json_encode($_arr);

			} else {

				$_arr = array('msg' => Constant::FAILED);
				echo json_encode($_arr);

			}
		}

	}

	/**
	* Add New Item function 
	**/
	public function _edit_item() {
		$this->_is_login();

		if($_POST) {
			$itemid = Input::post('item_id');
			$itemname = Input::post('it_name');
			$itemdesc = Input::post('i_description');
			$standard_qty = Input::post('item_standard');
			$reorder_point = Input::post('reorder');
			
			$data = array(
				'itemid' => $itemid,
				'itemname' => $itemname,
				'itemdesc' => $itemdesc,
				'standard_qty' => $standard_qty,
				'reorder_point' => $reorder_point,
				'modified_at' => date("Y-m-d H:i:s"),
				'modified_by' => $this->session->userdata('uid')
			);

			$this->_m_stock->edit_item($data);
		}
	}
}

?>