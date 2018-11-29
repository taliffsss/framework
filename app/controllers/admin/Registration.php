<?php
use Mark\libraries\Constant;

class Registration extends My_Controller {
	/**
	 * CSRF Token Libraries class Instance
	 * @var Obj
	 */
	protected $_token;

	function __construct() {
		parent::__construct();
		$this->_token = new CsrfToken;
	}

	/**
	 * Activation Link
	 * @param string
	 */
	public function activation_link() {

		$token = Url::uri_segment(4);

		$row = $this->_email_verification->_validate_token($token);
		
		if($row > 0) {

			$this->_registration->_activate_data($token);

			Session::setMessage('success',"<div class='alert alert-success' role='alert'>".Constant::VERIFIED."</div>");

			Url::redirect('login');

		} else {

			Url::redirect('error/404.html');

		}

	}

	/**
	 * Registration function
	 * @return Boolean
	 */
	public function register() {

		if(Input::_method()) {
			
			$token = Input::post('csrf_token');
			$_empid = Input::post('empid');
			$pass = Input::post('password');
			$key = $this->random_string('alnum',100);
			$deptname = Input::post('department');
			$subdeptname = Input::post('unit');
			$dept = $this->_department->getDepartment_by_name($deptname);
			$subdept = $this->_department->getSub_department_by_name($subdeptname);
			$data = array(
				'empid' => $_empid,
				'email' => Input::post('email'),
				'deptid' => $dept['dept_id'],
				'subdeptid' => $subdept['sub_dept_id'],
				'uname' => Input::post('username'),
				'hash_pass' => $this->_password_hash($pass),
				'question' => Input::post('question'),
				'answer' => strtolower(Input::post('answer')),
				'created_at' => date("Y-m-d H:i:s"),
				'created_by' => $_empid,
				'token' => $key
			);

			if($this->_token->_csrf_is_valid($token)){ //check if csrf token are valid 

				$_data = $this->_registration->_employee($data); //insert data

				if($_data) {

					$from = 'no-reply@'.trim($_SERVER['SERVER_NAME']);

					$body = $this->_mail_body_registration($data,$key,$pass);
					
					$this->_email->setFrom($from,'Admin Inventory');
					$this->_email->setTo($data['email']);
					$this->_email->setSubject('Account Verification');
					$this->_email->setMessage($body);
					if($this->_email->send()) {

						echo Constant::SUCCESS_REGISTER;

					} else {

						echo Constant::ERROR_MSG;
					}

				} else {

					echo Constant::ERROR_MSG;

				}

			} else {
				echo Constant::CSRF_EXPIRED;
			}
		}
	}

	/**
	 * Mail Body
	 * @param Int
	 * @param String
	 * @return String
	 */
	public function _mail_body_registration($param,$key,$pass) {

		$dept = $this->_department->getDepartment($param['deptid']);

		$subdept = $this->_department->getSub_department($param['subdeptid']);

		$ques = $this->_question->getquestion($param['question']);

		$url = Url::baseUrl()."/activation/token/".$key;

		$output = '<table cellpadding="0" cellspacing="0" width="100%"><tr><td><table align="center" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse;"><tr><td style="background: url('.Url::baseUrl().'/assets/img/bg.png) no-repeat;background-size: cover;padding: 50px 0;text-align: center;"><img src="'.Url::baseUrl().'/assets/img/logo-white.png" style="width: 300px;"></td></tr><tr><td style="padding: 40px;"><table cellpadding="0" cellspacing="0" width="100%"><tr><td style="padding: 10px 0; ">Hello '.$param['uname'].',</td></tr><tr><td style="padding: 10px 0; ">Thank you for registering at MSW Inventory System. To complete your registration and activate your account, please click “Verify” below</td></tr><tr><td style="padding: 20px;"><table cellpadding="0" cellspacing="0" width="100%"><tr><td style="width: 150px; padding: 5px 0;">Email</td><td style="width: 150px; padding: 5px 0;">'.$param['email'].'</td></tr><tr><td style="width: 150px; padding: 5px 0;">Department</td><td>'.$dept['dept_name'].'</td></tr><tr><td style="width: 150px; padding: 5px 0;">Unit</td><td>'.$subdept['sub_dept_name'].'</td></tr><tr><td style="width: 150px; padding: 5px 0;">Username</td><td>'.$param['uname'].'</td></tr><tr><td style="width: 150px; padding: 5px 0;">Password</td><td>'.$pass.'</td></tr><tr><td style="width: 150px; padding: 5px 0;">Security Question</td><td>'.$ques['security_question'].'</td></tr><tr><td style="width: 150px; padding: 5px 0;">Security Answer</td><td>'.$param['answer'].'</td></tr><tr><td colspan="2" style="text-align: center; height: 80px; padding: 10px;"><a href="'.$url.'" style="padding: 10px; background: #f5f5f5; border: 1px solid #b2b2b2; text-transform: uppercase; text-decoration: none; color: #000">Verify</a></td></tr></table></td></tr><tr><td>'."\r\n".'Please don’t delete this email for your own copy of your registration details. However, verification link will be expired upon activation.'."\r\n".'</td></tr><tr><td style="padding: 20px 0 0 0;">Best Regards,</td></tr><tr><td style="font-weight: 600;">MSW ADMIN</td></tr></table></td></tr><tr><td style="background: url('.Url::baseUrl().'/assets/img/bg.png) no-repeat;background-size: cover;padding: 10px 0;text-align: center;"></td></tr></table></td></tr></table>';
		
		return $output;

	}

	/**
	 * Check employee id if existing or not (ajax call)
	 * @param Int
	 * @return Boolean
	 */
	public function check_empid() {

		$id = Input::post('empid');
		
		if($id) {

			$row = $this->_registration->getEmpid($id);
			$data = $this->_registration->getSingleEmployee($id);
			$dept = $this->_department->getDepartment($data['dept_id']);
			$subdept = $this->_department->getSub_department($data['sub_dept_id']);

			if($row > 0) {
				if($data['emp_status'] == 0) {
					$_arr = array('email' => $data['email'], 'dept' => $dept['dept_name'], 'subdept' => $subdept['sub_dept_name']);
					echo $this->_register_template($_arr);
				} else {
					echo Constant::ALREADY_REGISTER;
				}
			} else {
				header("HTTP/1.0 404 Not Found");
				exit;
			}

		} else {
			header("HTTP/1.0 404 Not Found");
			exit;
		}
		
	}

	/**
	 * Check username if existing or not (ajax call)
	 * @param String
	 * @return Boolean
	 */
	public function check_username() {

		$uname = Input::post('uname');

		if(is_string($uname)) {

			$row = $this->_login->_count($uname);

			if($row > 0) {
				$arr = array('msg' => Constant::USERNAME);
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