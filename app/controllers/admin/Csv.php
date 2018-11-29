<?php 
	/**
	* This is a controller class
	* It's responsible for controlling the way that the user interacts with the system
	* This will handle the response to be send back to the user when the user upload a file
	*
	* @category Controller
	* @author Kristallynn Tolentino
	**/
	
	class Csv extends My_Controller {
		
		function __construct(){
			parent::__construct();
			if(!Session::userdata('logged_in')) Url::redirect('login');
		}

		public function index(){

			if(Input::_method()){
				
				if ($_FILES['csvfile']['size'] == 0){
    				Session::setMessage('import_msg',Constant::CVS_REQUIRED);
				}else{
					$mimes = array('application/vnd.ms-excel','text/plain','text/csv');
					if(in_array($_FILES['csvfile']['type'],$mimes)){
  					
	  					$file =$_FILES['csvfile'];

						//File details
						$filename = $file['name'];
						$tmp = $file['tmp_name'];
						$size = $file['size'];
						$error = $file['error'];
						$type = $file['type'];
						$foldername = "csvfolder";
						$path =  Constant::FOLDER_PATH.'/'.$foldername.'/';

						//create folder
						$this->create_folder(NULL,$foldername);
						//move file
						move_uploaded_file($tmp, $path.$filename);

						//Get data from csv
				    	$arr = $this->_csvreader->get_array($path.$filename);
				    	
				    	if(strpos(strtolower($filename), "sub_department") !== FALSE){
				    		
				    		$result = $this->_upload->insertSub_dept($arr);

				    		(($result == true) ? Session::setMessage('import_msg',Constant::SUCC_IMPORT) : Session::setMessage('import_msg',Constant::FAIL_IMPORT)	);
				    		
						}elseif(strpos(strtolower($filename), "employee") !== FALSE){
							
							$_arrangement = array('head'=> array(),
											'manager' => array(),
											'others' => array());


							foreach ($arr as $key) {

								$new_arr = array(
										'emp_id' => $key['id_no'],
										'first_name' => $key['first_name'],
										'middle_name' => $key['middle_name'],
										'last_name' => $key['last_name'],
										'suffix' => $key['suffix'],
										'department' => $key['department'],
										'unit'=>$key['unit'],
										'position'=> $key['position'],
										'created_at' => date('Y-m-d H:i:s'),
										'created_by' => Session::userdata('uid'),
										"role"=>$key['role'],
										'email'=>$key['email']
								);
		 
								if($key['role'] == 'Head Manager'){

									$_arrangement["head"][] = $new_arr;

								}elseif($key['role'] == 'Manager'){

									$_arrangement["manager"][] = $new_arr;

								}else{

									$_arrangement["others"][] = $new_arr;
									
								}

							}


							foreach ($_arrangement as $key => $values) {
								foreach ($values as $val) {
									
									$res = $this->_registration->getSingleEmployee($val['emp_id']);
									$valid = $this->_password_reset->_validate_email($val['email']);

									
									$employee = array(
											'emp_id' => $val['emp_id'],
											'first_name' => $val['first_name'],
											'middle_name' => $val['middle_name'],
											'last_name' => $val['last_name'],
											'suffix' => $val['suffix'],
											'department' => $val['department'],
											'unit'=>$val['unit'],
											'position'=> $val['position'],
											'created_at' => date('Y-m-d H:i:s'),
											'created_by' => Session::userdata('uid')
									);

									 
									$user = array(
											'emp_id'=> $val['emp_id'],
											'role'=>$val['role'],
											'email'=>$val['email'],
											'created_at' => date('Y-m-d H:i:s'),
											'created_by' => Session::userdata('uid')
									);

									$details = array(
										'emp_id' => $val['emp_id'],
										'first_name' => $val['first_name'],
										'middle_name' => $val['middle_name'],
										'last_name' => $val['last_name'],
										'suffix' => $val['suffix'],
										'department' => $val['department'],
										'unit'=>$val['unit'],
										'position'=> $val['position'],
										'modified_at' => date('Y-m-d H:i:s'),
										'modified_by' => Session::userdata('uid'),
										"role"=>$val['role']
									);

									if($res['emp_id'] != $val['emp_id']){

									 	$_s = $this->_upload->insertEmployee($employee);

										 if($_s) {
										 	if($valid > 0){

										 		$this->_upload->deleteEmployee($val['emp_id']);
										 		Session::setMessage('import_dup',Constant::CVS_DUPLICATE);

										 	}else{
										 		
										 		$this->_upload->insertUser($user);
										 	}
										 }
										
									 }else{
									 	$this->_upload->updateByEmpId($details);
									 }
								}

							}

							//Update manager and head manager emp id 
							$head_manager = $this->_get_dept_head(Constant::HEAD);
							$managers = $this->_get_all_manager(Constant::MNGR);

							Session::put('import_msg',Constant::SUCC_IMPORT);
							
						}elseif(strpos(strtolower($filename), "stock") !== FALSE || strpos(strtolower($filename), "item") !== FALSE){ 
							
							//Initialize Array 
							$item_ids = array();
							$current_stock = array();

							//Insert All Stocks in ais_item table 
							$result = $this->_upload->insertStock($arr);	
							
							//If successfull insert into ais_activity_log table
							if($result == true){

								$details = $this->_stock->_get_stocks();

								foreach ($details as $key => $value) {
									$item_ids[] = $value['item_id'];
									$current_stock[] = $value['current_stock'];
								}

								$details = array(
										'module' => Constant::M_REQUEST,
										'action'=> Constant::A_UPLOAD,
										'user_id' => Session::userdata('uid'),
										'item_ids' => json_encode($item_ids),
										'current_stock' => json_encode($current_stock),
										'date_audit' => date('Y-m-d H:i:s')
									);

								//insert to activity log 
								$activity_upload = $this->_upload->first_upload_stock($details);
								
								(($activity_upload == 1) ? Session::setMessage('import_msg',Constant::SUCC_IMPORT) : Session::put('import_msg',Constant::FAIL_IMPORT));
							
							}else{
								//display error message
								Session::setMessage('import_msg',Constant::FAIL_IMPORT);
							}

						}elseif(strpos(strtolower($filename), "category") !== FALSE){
							
							$result = $this->_upload->insertCategory($arr);

							(($result == true) ? Session::setMessage('import_msg',Constant::SUCC_IMPORT) : Session::put('import_msg',Constant::FAIL_IMPORT));

						}elseif(strpos(strtolower($filename), "unit") !== FALSE){
							$count_error = 0;

							foreach ($arr as $key) {

								$clause = date('Y-m-d H:i:s');
								$unitName = $key['unit_name'];

								$unit = array(
									'unit_code' => $this->_generateCode($unitName),
									'unit_name' => $unitName,
									'unit_desc' => $key['unit_desc'],
									'created_by' => 1,
									'created_at' => date('Y-m-d H:i:s')
								);

								$_done = $this->_upload->insertUnit($unit);
							
								if($_done == true) {
									$all_unit = $this->_unit->_get_all();
									$all_category = $this->_category->get_all_category();
									
									foreach ($all_unit as $val => $unit_id) {
										
										foreach ($all_category as $cat_key => $cat_id) {
											
											$cat_unit = array(
												'category_id' => $cat_id['category_id'],
												'unit_id' => $unit_id['unit_id']
											);
								
											$_row = $this->_upload->check_if_has($cat_unit);

											if($_row == 0) {
												$this->_unit->insertCatUnit($cat_unit);
											}	
										}
										
									}

								}else{
									$count_error++;
								}

							}

							(($count_error == 0) ? Session::setMessage('import_msg',Constant::SUCC_IMPORT) : Session::setMessage('import_msg',Constant::FAIL_IMPORT)	);

						}
					} else {
						Session::setMessage('import_msg',Constant::CVS_ONLY);
					}
				}
			}

			$data['category'] = $this->_show_category();
			$data['navbar'] = $this->_navigation_bar();
			$data['ticker'] = $this->ticker();
			Common::view('admin/includes/header');
			Common::view('admin/pages/upload',$data);
			Common::view('admin/includes/modal',$data);
			Common::view('admin/includes/footer');
		}

	/**
	* If position Position is department head get employee id and update head_manager column 
	* @param string 
	* @return boolean
	**/
	public function _get_dept_head($post){
		$head = $this->_user->_getDeptHead_details($post);
		return $this->_user->_updateHeadEmpID($head['emp_id']);
	}

	/**
	* If position Position is department head get employee id and update head_manager column 
	* @param string 
	* @return boolean
	**/
	public function _get_all_manager($post){
		$arr_manager = $this->_user->_get_manager_details($post);
		
		foreach ($arr_manager as $key => $value) {
			$this->_user->_updateManagerEmpID($value['sub_dept_id'], $value['emp_id']);
		}

	}

		
	}
?>