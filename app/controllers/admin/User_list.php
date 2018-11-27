<?php 

class User_list extends My_Controller {

	function __construct() {
		parent::__construct();
		if(!Session::userdata('logged_in')) Url::redirect('login');
	}

	public function index() {
		$data['navbar'] = $this->_navigation_bar();
		$data['ticker'] = $this->ticker();
		$data['userlist'] = $this->_show_list();
		Common::view('admin/includes/header');
		Common::view('admin/pages/users-list',$data);
		Common::view('admin/includes/footer');
	}

	/**
	 * Show user's list
	 * @return String
	 */
	public function _show_list() {

		$results = $this->_user->_getUser_all();

		$output = NULL;

		foreach ($results as $res) {
			$output .= "<tr>
							<td class='tbl-cCode'>{$res['emp_id']}</td>
							<td class='tbl-cName'>{$res['fullname']}</td>
							<td class='tbl-cName'>{$res['dept_name']}</td>
							<td class='tbl-cName'>{$res['sub_dept_name']}</td>
							<td class='tbl-cName'>{$res['position']}</td>
							<td>
					            <button class='btn dashbtn _reset__btn_".$res['username']."' onclick='_pass_reset(".$res['user_id'].",\"".$res['username']."\")'>Reset Password</button>
					        </td>
						  </tr>";
		}
		
		return $output;

	}
}

?>