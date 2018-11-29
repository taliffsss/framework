<?php

class Forgot extends My_Controller {

	function __construct() {
		parent::__construct();
	}

	/**
	 * Check if credential are valid
	 */
	public function index() {
		if(Session::userdata('logged_in')) Url::redirect('dashboard');

		$data['page'] = 'admin/pages/forgot-password';
		
		Common::view('admin/includes/header');
		Common::view('admin/pages/forgot-password',$data);
		Common::view('admin/includes/footer');
	}
}

?>