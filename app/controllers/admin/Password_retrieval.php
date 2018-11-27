<?php 

class Password_retrieval extends \Mark\core\My_Controller {

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
	 * Admin reset password for staff account
	 * @return true
	 */
	public function _admin_reset() {

		$id = $this->input->post('uid');

		$res = $this->_password_reset->_validate_userid($id);

		if($res > 0) {

			$_res = $this->_password_reset->getData_id($id);

			$key = $this->random_string('alnum',100);

			$_success = array(
				'userid' => $_res['user_id'],
				'token' => $key,
				'datetime' => date("Y-m-d H:i:s")
			);

			$body = $this->admin_mail_body($_res,$key);

			$subject = Constant::PASSWORD_RETRIEVAL;

			$from = 'reset-password@'.trim($_SERVER['SERVER_NAME']);

			$this->_email->setFrom($from,'Admin Inventory');
			$this->_email->setTo($_res['email']);
			$this->_email->setSubject(Constant::PASSWORD_RETRIEVAL);
			$this->_email->setMessage($body);

			$success = $this->_password_reset->_insert_token($_success);

			if($success) {
				if($this->_email->send()) {
					return true;
				} else {
					header("HTTP/1.0 404 Not Found");
					exit;
				}
			} else {
				header("HTTP/1.0 404 Not Found");
				exit;
			}

		} else {
			return false;
		}

	}

	/**
	 * Password retrieval mail triger to send email
	 * @param string $email
	 * @return true
	 */	
	public function _password_retrieval() {

		$_count = 0;

		if($_POST) {

			$email = urldecode($this->input->post("email"));
			$token = $this->input->post('csrf_token');
			$key = $this->random_string('alnum',100);

			$res = $this->_password_reset->getData(urldecode($email));
			
			$question = $this->_question->getquestionByName($this->input->post('question'));

			$data = array(
				'email' => $email,
				'question' => $question['security_id'],
				'answer' => strtolower($this->input->post('answer'))
			);

			$row = $this->_password_reset->_employee_checked_qa($data);

			$this->_triggered_email($email,$res,$key);

			$_success = array(
				'userid' => $res['user_id'],
				'token' => $key,
				'datetime' => date("Y-m-d H:i:s")
			);

			if($this->_token->_csrf_is_valid($token)){

				$_c = $this->session->userdata('error_login');
				$_s = $this->session->userdata('success_login');
				$femail = $this->session->userdata('femail');
				$_counter = $_c += 1;
				$success = $_s += 1;

				if($row > 0) {

            		if($_s > 0) {
						if($_s > 2) {
							
							echo $this->_getLocked($femail);

						} else {

							if($_s > 1) {
								$_sess = array('success_login' => $success);
							} else {
								$_sess = array('success_login' => $_count += 1, 'femail' => $email);
							}
							
							$this->session->set_userdata($_sess);

							$this->_password_reset->_insert_token($_success);
							
							if($this->_email->send()) {

								echo Constant::SUCCESS_RESET;

							}
						}
					}
	
				} else {

					if($_c > 0) {
						if($_c > 2) {
							
							echo $this->_getLocked($femail);

						} else {
							if($_c > 1) {
								$_sess = array('error_login' => $_counter);
							} else {
								$_sess = array('error_login' => $_count += 1, 'femail' => $email);
							}
							
							$this->session->set_userdata($_sess);
							echo Constant::SECURITY_ANSWER;
						}
					}
				}
			} else {

				echo Constant::CSRF_EXPIRED;

			}
		}

	}

	/**
	 * Mail function
	 * @param string
	 * @param array string
	 * @param string token
	 * @return void
	 */
	public function _triggered_email($email,$res,$key) {
		$body = $this->retrieval_mail_body($res,$key);

		$from = 'reset-password@'.trim($_SERVER['SERVER_NAME']);

		$this->_email->setFrom($from,'Admin Inventory');
		$this->_email->setTo($email);
		$this->_email->setSubject(Constant::PASSWORD_RETRIEVAL);
		$this->_email->setMessage($body);
	}

	/**
	 * Password retrieval mail body frontend
	 * @param array string $res
	 * @return true
	 */
	public function retrieval_mail_body($res,$key) {

		$url = $this->url->baseUrl().'/reset-password/token/'.$key;

		$output = '<table cellpadding="0" cellspacing="0" width="100%"><tr><td><table align="center" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse;"><tr><td style="background: url('.$this->url->baseUrl().'/assets/img/bg.png) no-repeat;background-size: cover;padding: 50px 0;text-align: center;"><img src="'.$this->url->baseUrl().'/assets/img/logo-white.png" style="width: 300px;"></td></tr><tr><td style="padding: 40px;"><table cellpadding="0" cellspacing="0" width="100%"><tr><td style="padding: 10px 0; ">Hello '.$res['username'].',</td></tr><tr><td style="padding: 10px 0; ">You are receiving this email because we received a password reset request from your account</td></tr><tr><td colspan="2" style="text-align: center; height: 80px; padding: 10px;"><a href="'.$url.'" style="padding: 10px; background: #f5f5f5; border: 1px solid #b2b2b2; text-transform: uppercase; text-decoration: none; color: #000">Reset Password</a></td></tr><tr><td>If you did not request a password reset, no further action is required</td></tr><tr><td style="padding: 20px 0 0 0;">Best Regards,</td></tr><tr><td style="font-weight: 600;">MSW ADMIN</td></tr></table></td></tr><tr><td style="background: url('.$this->url->baseUrl().'/assets/img/bg.png) no-repeat;background-size: cover;padding: 10px 0;text-align: center;"></td></tr></table></td></tr></table>';

		return $output;
	}

	/**
	 * Password retrieval mail body Backend
	 * @param array string $res
	 * @return true
	 */
	public function admin_mail_body($res,$key) {

		$ques = $this->_question->getquestion($res['question_id']);

	    $url = $this->url->baseUrl().'/reset-password/token/'.$key;

		$output = '<table cellpadding="0" cellspacing="0" width="100%"><tr><td><table align="center" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse;"><tr><td style="background: url('.$this->url->baseUrl().'/assets/img/bg.png) no-repeat;background-size: cover;padding: 50px 0;text-align: center;"><img src="'.$this->url->baseUrl().'assets/img/logo-white.png" style="width: 300px;"></td></tr><tr><td style="padding: 40px;"><table cellpadding="0" cellspacing="0" width="100%"><tr><td style="padding: 10px 0; ">Hello '.$res['username'].',</td></tr><tr><td style="padding: 10px 0; ">You are receiving this email because you contacted ADMIN for assistance regarding your "Security Answer" to reset your password. Please take note of your registered security answer below and click "Reset Password" to proceed</td></tr><tr><td style="padding: 20px;"><table cellpadding="0" cellspacing="0" width="100%"><tr><td style="width: 150px; padding: 5px 0;">Security Question</td><td>'.$ques['security_question'].'</td></tr><tr><td style="width: 150px; padding: 5px 0;">Security Answer</td><td>'.$res['answer'].'</td></tr><tr><td colspan="2" style="text-align: center; height: 80px; padding: 10px;"><a href="'.$url.'" style="padding: 10px; background: #f5f5f5; border: 1px solid #b2b2b2; text-transform: uppercase; text-decoration: none; color: #000">Reset Password</a></td></tr></table></td></tr><tr><td style="padding: 20px 0 0 0;">Best Regards,</td></tr><tr><td style="font-weight: 600;">MSW ADMIN</td></tr></table></td></tr><tr><td style="background: url('.$this->url->baseUrl().'/assets/img/bg.png) no-repeat;background-size: cover;padding: 10px 0;text-align: center;"></td></tr></table></td></tr></table>';

		return $output;
	}

	/**
	 * Password reset
	 * @param string hash $token
	 * @return true
	 */
	public function _reset_password($uri_token) {

		if($_POST) {

			$token = $this->input->post('csrf_token');
			$pass = urldecode($this->input->post('new_password'));
			$hash = $this->_password_hash($pass);
            
            if($this->_token->_csrf_is_valid($token)){

				$this->_password_reset->_update_password($hash,$uri_token);

				$this->session->set_message('success',"<div class='alert alert-success' role='alert'>Password has been reset.</div>");
				
				$this->url->redirect('login');
                
			} else {
				header("HTTP/1.0 404 Not Found");
				exit;
			}
		}
	}

	/**
	 * Reset link validation
	 * @param string $token
	 * @return boolean
	 */
	public function _reset_link($token) {

		$row = $this->_verify->_validate_token($token);
		
		if($row > 0) {
			$this->_reset_password($token);
		} else {
			$this->url->redirect('error/404.html');
		}
	}

	/**
	 * Check if email are existing (ajax call)
	 * @return boolean
	 */
	public function _check_email() {

		$email = $this->input->post('email');
    
		if(is_string($email)) {
			
			$this->_password_reset->_unlocked_me($email);
			$row = $this->_password_reset->_validate_email($email);
			$res = $this->_password_reset->getData($email);
			$_res = $this->_password_reset->_check_if_reg($email);
			$ques = $this->_question->getquestion($res['question_id']);

			if($row > 0) {
				if($_res > 0) {
					if($res['status'] != 0) {
						$_arr = array('msg' =>Constant::EMAIL_CONFIRM, 'question' => $ques['security_question']);
						echo json_encode($_arr);
					} else {
						$_arr = array('msg' =>Constant::LOCKED);
						echo json_encode($_arr);
					}
				} else {
					$_arr = array('msg' =>Constant::NOT_REGISTERED);
					echo json_encode($_arr);
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
	 * Locked user's acct if reach max request & destroy session
	 * @param string email
	 * @return string
	 */
	public function _getLocked($femail) {

		Session::logout();

		$_data = array('email' => $femail, 'datetime' => date('Y-m-d H:i:s'));

		$this->_password_reset->_employee_locked_on($_data);

		if($this->_email->send()) {

			return Constant::RESET_FAILED;
			
		}

	}
}

?>