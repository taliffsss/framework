<?php 

class Unit extends My_Controller {

	function __construct() {
		parent::__construct();
	}

	/**
	 * Insert Unit table & category unit
	 */
	public function add() {
		
		if(Input::_method()) {

			$rows = $this->_category->get_all_category();

			$data = array(
				'unit_name' => Input::post('uName'),
				'unit_desc' => Input::post('unit_desc'),
				'created_by' => Session::userdata('uid'),
				'created_at' => date('Y-m-d H:i:s')
			);

			$_insert = $this->_unit->_insert_data($data);

			$id = $this->db->InsertId();

			if($_insert) {
				foreach ($rows as $key) {
					$cat_unit = array(
						'unit_id' => $id,
						'category_id' => $key['category_id']
					);
					$this->_unit->insertCatUnit($cat_unit);
				}
			} else {
				header("HTTP/1.0 404 Not Found");
				exit;
			}

		}
	}

	/**
	 * Check Unit name if existing or not (ajax call)
	 * @param String
	 * @return Boolean
	 */
	public function _check_unit() {
		$uname = Input::post('uname');

		if(is_string($uname)) {

			$row = $this->_unit->_count($uname);

			if($row > 0) {
				$arr = array('msg' => Constant::UNIT_NAME);
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
	 * Show all units
	 */
	public function _show_all_units() {
		return $this->_show_units();
	}

	/**
	 * Check unit name if existing or not (ajax call)
	 * @return string json
	 */
	public function check_unit_name() {

		$uname = Input::post('uname');

		if(is_string($uname)) {

			$row = $this->_unit->_count($uname);

			if($row > 0) {
				$arr = array('msg' => Constant::UNIT_EXIST);
				echo json_encode($arr);
			} else {
				header("HTTP/1.0 404 Not Found");
				exit;
			}

		} else {
			header("HTTP/1.0 404 Not Found");
			exit;
		}
		
	}
}

?>