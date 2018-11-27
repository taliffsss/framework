<?php 

class Category extends \Mark\core\My_Controller {

	private $_m_category;
	private $_m_stock;

	function __construct() {
		parent::__construct();
		$this->_m_category = new M_category;
		$this->_m_stock = new M_stock;
	}

	/**
	* Add New Category function 
	* @return Boolean
	**/
	public function _add_category() {
		$this->_is_login();

		if($_POST) {
			$catname = $this->input->post('cname');
			$catdesc = $this->input->post('cdesc');
			
			$data = array(
				'catname' => $catname,
				'catdesc' => $catdesc,
				'created_at' => date("Y-m-d H:i:s"),
				'created_by' => $this->session->userdata('uid')
			);

			$this->_m_category->insert_category($data);
		}
	}

	/**
	 * Check category name if existing or not (ajax call)
	 * @param String
	 * @return Boolean
	 */
	public function _check_category() {

		$cname = $this->input->post('cname');

		if(is_string($cname)) {

			$row = $this->_m_category->_cat_count($cname);

			if($row > 0) {
				$arr = array('msg' => Constant::CATEGORY);
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
	* Display Category list 
	**/
	public function _category_list() {
		$data = $this->_m_category->get_all_category();
		$output = NULL;
		
		foreach ($data as $val) {

			$output .= "<tr>
							<td class='tbl-cId'>{$val['category_id']}</td>
							<td class='tbl-cCode'>{$val['category_code']}</td>
							<td class='tbl-cName'>{$val['category_name']}</td>
							<td class='tbl-cDesc'>{$val['category_desc']}</td>
						  </tr>";
		}

		return $output;
	} 

	/**
	* Display all item based on category 
	**/
	public function _category_and_item() {
		$data = $this->_m_category->_get_cat_and_item();
		$output = NULL;
		
		foreach ($data as $val) {

			$output .= "";
		}

		return $output;
	} 

	/**
	* Show all Category
	* @return Array String
	*/
	public function _category() {
		return $this->_show_category();
	}

}

?>