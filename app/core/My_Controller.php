<?php

class My_Controller extends Controller {

	/**
	 * Question Model class Instance
	 * @var Obj
	 */
	public $_question;
	/**
	 * Category Model class Instance
	 * @var Obj
	 */
	public $_category;
	/**
	 * Stock Model class Instance
	 * @var Obj
	 */
	public $_stock;
	/**
	 * Department Model class Instance
	 * @var Obj
	 */
	public $_department;

	/**
	 * Email Verification Model class Instance
	 * @var Obj
	 */
	public $_email_verification;
	/**
	 * Login Model class Instance
	 * @var Obj
	 */
	public $_login;
	/**
	 * Password Reset Model class Instance
	 * @var Obj
	 */
	public $_password_reset;
	/**
	 * Registration Model class Instance
	 * @var Obj
	 */
	public $_registration;
	/**
	 * Unit Model class Instance
	 * @var Obj
	 */
	public $_unit;
	/**
	 * Purchase Model class Instance
	 * @var Obj
	 */
	public $_purchase;
	/**
	 * Upload Model class Instance
	 * @var Obj
	 */
	public $_upload;
	/**
	 * User Model class Instance
	 * @var Obj
	 */
	public $_user;
	/**
	 * Request Logs Model class Instance
	 * @var Obj
	 */
	public $_request_logs;
	/**
	 * Role Model class Instance
	 * @var Obj
	 */
	public $_role;
	/**
	 * Item Model class Instance
	 * @var Obj
	 */
	public $_item;
	/**
	 * Request Model class Instance
	 * @var Obj
	 */
	public $_request;
	/**
	 * Activity Model class Instance
	 * @var Obj
	 */
	public $_activity_logs;

	function __construct() {
		parent::__construct();
		$this->_question = new M_question;
		$this->_category = new M_category;
		$this->_stock = new M_stock;
		$this->_department = new M_department;
		$this->_email_verification = new M_email_verification;
		$this->_login = new M_login;
		$this->_password_reset = new M_Password_Reset;
		$this->_registration = new M_registration;
		$this->_unit = new M_unit;
		$this->_purchase = new M_purchase;
		$this->_upload = new M_upload;
		$this->_user = new M_users;
		$this->_request_logs = new M_request_logs;
		$this->_role = new M_role;
		$this->_item = new M_items;
		$this->_request = new M_request;
		$this->_activity_logs = new M_activity_logs;
	}

	/**
	 * Mail body
	 * @param string
	 * @param string
	 * @return string
	 */
	public function _mail_body($data,$res,$msg,$code) {

		$hello = (Session::userdata('role') == 1) ? 'Kristel Diana Catral' : $res['fullname'];

		$output = "
			<table cellpadding='0' cellspacing='0' width='100%'><tr><td><table align='center' cellpadding='0' cellspacing='0' width='600' style='border-collapse: collapse;'>

				<tr>
					<td style='background: url(".Url::baseUrl()."assets/img/bg.png) no-repeat;background-size: cover;padding: 50px 0;text-align: center;'>
					<img src='".Url::baseUrl()."assets/img/logo-white.png' style='width: 300px;'>
					</td>
				</tr>

				<tr>
					<td style='padding: 40px;'>
						<table cellpadding='0' cellspacing='0' width='100%'>
							<tr>
								<td style='padding: 10px 0;'>
									Hello ".$hello.",
								</td>
							</tr>

							<tr>
								<td style='padding: 10px 0;'><table cellpadding='0' cellspacing='0' width='100%'>
									<tr>
										<td style='width: 160px; padding: 5px 0; font-weight: 700'>
											Request Code
										</td>
										<td style='padding: 5px 0;'>
											".$code."
										</td>
									</tr>
									<tr>
										<td style='width: 160px; padding: 5px 0; font-weight: 700'>
											Requester's Name
										</td>
										<td style='padding: 5px 0;'>
											".$res['requestor']."
										</td>
									</tr>
								</td>
						</table>
					</td>
				</tr>

				<tr>
					<td style='padding: 10px 0;'>
						".$msg."
					</td>
				</tr>
				<tr>
					<td style='padding: 20px;'>
						<table cellpadding='0' cellspacing='0' width='100%'>
							<tr>
								<td style='width: 150px; padding: 5px 0; text-align: center; font-weight: 700'>
									Item Name
								</td>
								<td style='width: 150px; padding: 5px 0; text-align: center; font-weight: 700'>
									Quantity Requested
								</td>
							</tr>
							".$data."
							<tr>
								<td colspan='2' style='text-align: center; height: 80px; padding: 10px;'>
									<a href='".Url::baseUrl().'/request-logs/view/'.$code."' style='padding: 10px; background: #f5f5f5; border: 1px solid #b2b2b2; text-transform: uppercase; text-decoration: none; color: #000'>
										Approve Request/s
									</a>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
				<td style='padding: 20px 0 0 0;'>Best Regards,</td></tr><tr><td style='font-weight: 600;'>MSW ADMIN</td></tr></table></td></tr><tr><td style='background: url(".Url::baseUrl()."assets/img/bg.png) no-repeat;background-size: cover;padding: 10px 0;text-align: center;'></td></tr></table></td></tr></table>
		";

		return $output;
	}

	/**
	 * User ticker
	 * @return html string
	 */
	public function ticker() {

		$reorderPoint = $this->_item->_ticker_reorderPoint();
		$userTicker = $this->_item->_user_tickerCount_all();

		$output = '
			<div class="col-sm-2 ">
					<div class="user-ticker">
						<div id="accordion">
							<div class="card">';

								$output .= (Session::userdata('role') == 1) ? '<div class="card-header" id="reorderPoint" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne"> 
									Reorder Point Items <span class="badge">'.$userTicker['reorderCount'].'</span>
								</div>

								<div id="collapseOne" class="collapse" aria-labelledby="reorderPoint" data-parent="#accordion">
									<div class="card-body">' : '';

									foreach ($reorderPoint as $key) {

										$output .= (Session::userdata('role') == 1) ? '
										<a href="">
											<div class="sub-reorder">'.$key['category_name'].'<span class="badge badge-secondary" style="float: right;">'.$key['mycount'].'</span></div>
										</a>
										' : '';

									}
									
									$output .= (Session::userdata('role') == 1) ? '</div>
								</div>
							</div>' : '';
							
							$output .= (Session::userdata('role') == 3) ? '<div class="card">
								<a href="" class="card-header" id="reorderPoint"> 
									Approval Pending – Items Reserved <span class="badge">'.$userTicker['pending'].'</span>
								</a>
							</div>' : '';

							$output .= (Session::userdata('role') == 1) ? '<div class="card">
								<a href="" class="card-header" id="reorderPoint"> 
									Approved - For Preparation <span class="badge">'.$userTicker['approved'].'</span>
								</a>
							</div>
							<div class="card">
								<a href="" class="card-header" id="reorderPoint"> 
								Ready for Issuance <span class="badge">'.$userTicker['issuance'].'</span>
								</a>
							</div>' : '';
				$output .= '</div>
					</div>
	 			</div>
		';

		return $output;
	}

	/**
	 * Navigation Bar
	 * @return string
	 */
	public function _navigation_bar() {
		$stock = (Url::uri_segment(2) == "dashboard") ? "active" : "";
		$user = (Url::uri_segment(2) == "users-list") ? "active" : "";
		$import = (Url::uri_segment(2) == "upload") ? "active" : "";

		$output = '
			<nav class="navbar fixed-top navbar-custom justify-content-between">
				<a class="navbar-brand" href="#">
					<img src="'.Url::baseUrl().'/assets/img/logo-long-white.png" alt="Admin Inventory System">
					<div class="navbar-right-align">
						<div class="username">'.Session::userdata('uname').'</div>
						<button class="btn dashbtn" id="logout">Logout</button>
					</div>
				</a>
			</nav>
			<div class="navi nav-header-sticky">
				<ul class="nav">';

			if(Session::userdata('role') == 1){
		$output .='<li class="nav-item">
				    <a class="nav-link '.$stock.'" href="'.Url::baseUrl().'/dashboard">Stocks</a>
				  </li>
				  <li class="nav-item dropdown">
				    <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">Reports</a>
				    <div class="dropdown-menu">
				      <a class="dropdown-item" href="'.Url::baseUrl().'/summary-report">Inventory Summary Report</a>
				      <a class="dropdown-item" href="'.Url::baseUrl().'/detailed-report">Inventory Detailed Report</a>
				      <a class="dropdown-item" href="'.Url::baseUrl().'/turnaround-report">Turnaround Time</a>
				    </div>
				  </li>
				  <li class="nav-item">
				  	<a class="nav-link '.$import.'" href="'.Url::baseUrl().'/upload">File Import</a>
				  </li>
				  <li class="nav-item">
				    <a class="nav-link '.$user.'" href="'.Url::baseUrl().'/users-list">User\'s List</a>
				  </li>';
			}


		$output .=	  '<li class="nav-item dropdown">
				    <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">Requisition</a>
				    <div class="dropdown-menu">
				      <a class="dropdown-item" href="'.Url::baseUrl().'/requisition-form">Request Form</a>
				      <a class="dropdown-item" href="'.Url::baseUrl().'/request-logs">Request Log</a>
				    </div>
				  </li>
				</ul>
			</div>';

		return $output;
	}

	/**
	 * Registration Template
	 * @param array string
	 * @return html string
	 */
	public function _register_template($param) {
		$output = '
			<div class="input_div">
				<span class="input input--filled">
					<input class="input__field" type="text" id="email" name="email" value="'.$param['email'].'" readonly autofocus>
					<label class="input__label" for="email">
					<span class="input__label-content">Email Address</span>
					</label>
				</span>
			</div>

			<div class="input_div">
				<span class="input input--filled">
					<input class="input__field" type="text" id="department" name="department" value="'.$param['dept'].'" readonly autofocus>
					<label class="input__label" for="department">
					<span class="input__label-content">Department</span>
					</label>
				</span>
			</div>

			<div class="input_div">
				<span class="input input--filled">
					<input class="input__field" type="text" id="unit" name="unit" value="'.$param['subdept'].'" readonly autofocus>
					<label class="input__label" for="unit">
					<span class="input__label-content">Unit</span>
					</label>
				</span>
			</div>

			<div class="input_div">
				<span class="input input--filled">
					<input class="input__field new_username" type="text" id="new_username" name="username" onblur="_username_Check()"/>
					<label class="input__label" for="new_username">
					<span class="input__label-content">Username</span>
					</label>
				</span>
				<div class="text-danger err-msg-uname"></div>
			</div>

			<div class="input_div">
				<span class="input input--filled">
					<input class="input__field new_password" type="password" id="new_password" name="password" onblur="_password_Check()"/>
					<button class="showPassword" onclick="viewPassword(`new_password`); return false"><i class="fas fa-eye"></i></button>
					<label class="input__label" for="new_password">
					<span class="input__label-content">Password</span>
					</label>
				</span>
				<div class="text-danger err-msg-pass"></div>
			</div>

			<div class="input_div">
				<span class="input input--filled">
					<input class="input__field confirm_password" type="password" name="cpass" id="confirm_password" onblur="_matching_pass()"/>
					<button class="showPassword" onclick="viewPassword(`confirm_password`); return false"><i class="fas fa-eye"></i></button>
					<label class="input__label" for="confirm_password">
					<span class="input__label-content">Confirm Password</span>
					</label>
				</span>
				<div class="text-danger err-msg-cpass"></div>
			</div>

			<div class="input_div">
				'.$this->_show_questionaire().'
			</div>

			<div class="input_div">
				<span class="input input--filled">
					<input class="input__field security_answer" type="password" id="security_answer" name="answer" />
					<button class="showPassword" onclick="viewPassword(`security_answer`); return false"><i class="fas fa-eye"></i></button>
					<label class="input__label" for="security_answer">
					<span class="input__label-content">Security Answer</span>
					</label>
				</span>
			</div>

			<div class="input_div input__submit-container">
				<input type="submit" class="input__submit disabled-btn-reg" value="Register" id="disabled-btn" name="submit">
				</div>
			</div>
		';

		return $output;
	}

	/**
	 * Get all category
	 * @return html string
	 */
	public function _show_category() {
		$rows = $this->_category->get_all_category();
		$output = '<select name="category_name" id="category_name" class="input__select category_name">
					<option value="" disabled selected hidden>Select Category</option>';
		foreach ($rows as $row) {
			$output .= "<option value='".$row['category_id']."'>".$row['category_name']."</option>";
		}
		$output .= "</select>";

		return $output;
	}

	public function _get_unit_name($category_id){
		$rows = $this->_stock->_get_unit_based_on_category($category_id);
		
			// $output = print_r($rows);
			$output = "<select name='unit_name' id='unit_name' class='input__select item_unit'>
						<option value='' selected name='unit_name' id='unit_name'>Select Unit</option>";
		foreach ($rows as $row) {
			$output .= "<option value='".$row['unit_id']."'>".$row['unit_name']."</option>";
		}

			$output .= "</select>";
			
		return $output;
	}

	/**
	 * Get All Units
	 * @return html string
	 */
	public function _show_units() {
		$data = $this->_unit->_get_all();
		$output = "<select class='input__select' name='unit_name'><option value='' selected hidden>Unit Name</option>";
		foreach ($data as $key) {
			$output .= "<option value='".$key['unit_id']."'>".$key['unit_name']."</option>";
		}
		$output .= "</select>";

		return $output;
	}

	/**
	 * Get all questionaire
	 * @return html string
	 */
	public function _show_questionaire() {
		$rows = $this->_question->getSecurity_Question();
		$output = "<select name='question' id='sec__ques' class='input__select sec__ques'><option value='' disabled selected hidden>Security Question</option>";
		foreach ($rows as $row) {
			$output .= "<option value='".$row['security_id']."'>".$row['security_question']."</option>";
		}
		$output .= "</select>";

		return $output;
	}

	/**
	 * Generate random String
	 */
	public function random_string($type = 'alnum', $len = 25) {
		
		switch ($type) {
			case 'alpha':
				$pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
				break;
			case 'alnum':
				$pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
				break;
			case 'numeric':
				$pool = '0123456789';
				break;
			case 'nozero':
				$pool = '123456789';
				break;
		}
		return substr(str_shuffle(str_repeat($pool, ceil($len / strlen($pool)))), 0, $len);
	}

	/**
	 * Image Compression
	 * @param string $source
	 * @param string $destination
	 * @param int $quality
	 * @return obj
	 */
	public function _compress($source, $destination, $quality) {

		$info = getimagesize($source);

		if ($info['mime'] == 'image/jpeg') 
			$image = imagecreatefromjpeg($source);

		elseif ($info['mime'] == 'image/gif') 
			$image = imagecreatefromgif($source);

		elseif ($info['mime'] == 'image/png') 
			$image = imagecreatefrompng($source);

		imagejpeg($image, $destination, $quality);

		return $destination;
	}

	/**
	 * Export csv format
	 * @param array $data
	 * @param string filename
	 * @param array $header
	 * @param string $delimiter
	 * @return True
	 */
	public function export_csv($data,$filename,$header,$delimiter=",") {

		$path = $this->create_folder($filename);

		$_path = dirname(__DIR__)."/".$path."/".$filename.".csv";

		$fp = fopen("text.csv", 'w');
		fputcsv($fp, $header);
		foreach ($data as $key) {
			$file = fputcsv($fp, $key, $delimiter);
		}

		fclose($fp);

		if($file == true) {
			copy("text.csv", $_path);
			unlink("text.csv");
		}
	}

	/**
	* Check if Folder is existing if not Create one
	* @return Boolean
	*/
	public function create_folder($path = NULL,$folder) {

		$fpath = ($path == NULL) ? Constant::FOLDER_PATH : $path;

		if(!is_dir($fpath.$folder)) {
			mkdir($fpath.$folder,0755, true);
		}

		$_path = $folder;

		return $_path;
	}

	/**
	 * Password hashing
	 * @link http://php.net/manual/en/function.password-hash.php
	 * @param string
	 * @return hash
	 */
	public function _password_hash($password) {

		$options = [
			'cost' => 12
		];

		$hash = password_hash($password, PASSWORD_BCRYPT, $options);

		return $hash;
	}

	/**
	 * Password Verify
	 * @link http://php.net/manual/en/function.password-verify.php
	 * @param string $password
	 * @param hash $hash
	 * @return bool
	 */
	public function _password_verify($password,$hash) {

		$verified = password_verify($password, $hash);

		return $verified;
	}

	/**
	*	Check user if logged in 
	**/ 
	public function _is_login(){
		if(Session::userdata('logged_in') == false){
			Url::redirect('login');
		}
	}

	/**
	* Get category id by category name
	* @param string
	**/
	public function _getCat_id($catName){
		return $this->_upload->getCat_id($catName);
	}

	/**
	* Get category id by unit name
	* @param string
	**/
	public function _getUnit_id($name){
		return $this->_upload->getUnit_id($name);
	}


	/**
	* Get category id by category name
	* @param string
	**/
	public function _generateCode($name){
		$res = $this->_upload->getCode($name);
		return $res;
	}

}

?>