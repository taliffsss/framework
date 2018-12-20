<?php
use Mark\libraries\Constant;

class Login extends My_Controller {

	function __construct() {
		parent::__construct();
		Common::helper('Security');
	}

	/**
	 * Check if credential are valid
	 */
	public function index() {

		echo ip_address();
		exit;
		
		if(Session::userdata('logged_in')) Url::redirect('dashboard');

		if(Input::_method()) {
			$token = Input::post('csrf_token');
			$uname = Input::post('username');
			$pass = Input::post('password');
			$recaptcha = Input::post('g-recaptcha-response');

			$res = $this->googleCaptcha($recaptcha);

			$_res = json_decode($res, true);

			if($_res['success']) {
				if($this->_token->_csrf_is_valid($token)){

					$count = $this->_login->_count($uname);

					if($count > 0) {

						$this->_password_reset->_unlocked_me($uname);

						$res = $this->_login->verifyUsername($uname);

						if(is_array($res) && (count($res) > 0)) {
							if($res['reset_date'] != NULL) {

								Session::setMessage('success', "<div class='alert alert-danger' role='alert'>".Constant::LOCKED."</div>");
								
							} else {
							
								$this->_password_matching($pass,$res);
							}
						} else {
							Session::setMessage('success', "<div class='alert alert-danger' role='alert'>".Constant::INACTIVE."</div>");
						}

					} else {
						Session::setMessage('success',Constant::INVALID_CREDENTIAL);
					}

				} else {

					Session::setMessage('success', "<div class='alert alert-danger' role='alert'>".Constant::CSRF_EXPIRED."</div>");

				}
			} else {
				Session::setMessage('success',Constant::RECAPTCHA);
			}
		}

		$data['question'] = $this->_show_questionaire();
		Common::view('admin/includes/header');
		Common::view('admin/pages/login',$data);
		Common::view('admin/includes/footer');
	}

	/**
	 * Password matching in database
	 * @param string
	 * @param array string
	 * @return string or JSON string
	 */
	public function _password_matching($pass,$res) {

		if($this->_password_verify($pass,$res['password'])) {

			$sess = $this->_attribute($res);

			if($res['role_id'] == 2 || $res['role_id'] == 3 || $res['role_id'] == 4 || $res['role_id'] == 1) {

				Url::redirect('request-logs');

			} else {
				echo "Role not set, please contact system administrator";
				exit;
			}

			return json_encode($sess);

		} else {

			Session::setMessage('success',Constant::INVALID_CREDENTIAL);
		}
	}

	/**
	 * set session attribute
	 * @param array string
	 * @return void
	 */
	public function _attribute($res) {

		$sess = array(
			'uname' => $res['username'],
			'uid' => $res['user_id'],
			'empid' => $res['emp_id'],
			'email' => $res['email'],
			'position' => $res['position'],
			'role' => $res['role_id'],
			'logged_in' => TRUE,
			'dept' => $res['dept_id'],
			'sub_dept' => $res['sub_dept_id']
		);

		Session::put($sess);
	}

	/**
	 * Logout
	 */
	public function signout() {
		Session::destroy();
		Url::redirect('login');
	}

	/**
	 * Recaptcha validation
	 * @return boolean
	 */
	public function googleCaptcha($str) {
		$google_url="https://www.google.com/recaptcha/api/siteverify";
		$secret = ($_SERVER['SERVER_NAME'] == 'localhost') ? "6Lei1l8UAAAAAPBwtbx5mqZVOImNcTNTudVRjW08" : "6Lf2n3oUAAAAAMs0vlWPX4XHa3CnTUUL7YBKVjhm";
		$ip = $_SERVER['REMOTE_ADDR'];
		$url = $google_url."?secret=".$secret."&response=".$str;
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
		$res = curl_exec($curl);
		curl_close($curl);

		return $res;
	}
}

?>