<?php

	/**
	* This is a Model class 
	* This will handle all the insertion of data from an array 
	* And other Query statement to be use related when a user upload a file 
	*
	* @category Model
	* @author Kristallynn Tolentino 
	* @copyright September 2018
	**/

	/**
	* Create constant.php 
	* For path names to be use
	**/
	
	class M_upload extends My_Model {
		
		function __construct(){
			parent::__construct();
		}


		public function check_if_has($data) {

			try {
				
				$this->db->query("SELECT * FROM {$this->_ais_category_unit} WHERE category_id = :category_id AND unit_id = :unit_id");

				$this->db->bind(":unit_id",$data['unit_id']);
				$this->db->bind(":category_id",$data['category_id']);

				$this->db->execute();

				$row = $this->db->rowCount();

				return $row;

			} catch (Exception $e) {

				$e->getMessage();
				
			}
		}

		public function _getID_by_name($unitName,$date) {

			try {
				
				$this->db->query("SELECT * FROM {$this->_ais_unit} WHERE unit_name = :unit_name AND (created_at = :created_at OR modified_at = :modified_at)");

				$this->db->bind(":unit_name",$unitName);
				$this->db->bind(":created_at",$date);
				$this->db->bind(":modified_at",$date);

				$row = $this->db->single(); //$row = $this->db->resultset();

				return $row;

			} catch (Exception $e) {
				
				$e->getMessage();

			}

		}

		/**
		* Insert function for Sub department
		* @param array 
		* @return boolean 
		**/
		public function insertSub_dept($details){
			try {
				$current_date = date("Y-m-d");
				
				foreach ($details as $key => $title) {
						$dept_id = $this->getDept_id($title['dept_name']);

						$this->db->query("INSERT INTO {$this->_ais_sub_department} (sub_dept_name, dept_id, created_by, created_at, modified_by, modified_at) VALUES(:sub_dept_name, :dept_id, :created_by, :created_at, :modified_by, :modified_at)
							ON DUPLICATE KEY UPDATE sub_dept_name = VALUES(sub_dept_name), dept_id = VALUES(dept_id), modified_by = VALUES(modified_by), modified_at = VALUES(modified_at)");

						$this->db->bind(":sub_dept_name",$title['sub_dept_name']);
						$this->db->bind(":dept_id",$dept_id['dept_id']);
						$this->db->bind(":created_by",1);
						$this->db->bind(":created_at",$current_date);
						$this->db->bind(":modified_by",1);
						$this->db->bind(":modified_at",$current_date);
						
						$this->db->execute();
				}
								
				return true;

			} catch (Exception $e) {
				return $e->getMessage();
			}
		}

		/**
		* Insert function for Employee details
		* @param array 
		* @return boolean 
		**/
		public function insertEmployee($emp){
			try {
					
					$dept_id = $this->getDept_id($emp['department']);
					$sub_dept_id = $this->getSub_dept_id($emp['unit']);

					

					$this->db->query("INSERT INTO {$this->_ais_employee} (emp_id, first_name, middle_name, last_name, suffix,  position, dept_id, sub_dept_id, created_by, created_at) 
						VALUES(:emp_id, :first_name, :middle_name, :last_name, :suffix, :position, :dept_id, :sub_dept_id, :created_by, :created_at)");
					
					$this->db->bind(":emp_id",$emp['emp_id']);
					$this->db->bind(":first_name",$emp['first_name']);
					$this->db->bind(":middle_name",$emp['middle_name']);
					$this->db->bind(":last_name",$emp['last_name']);
					$this->db->bind(":suffix", $emp['suffix']);
					$this->db->bind(":position",$emp['position']);
					$this->db->bind(":dept_id",$dept_id['dept_id']);
					$this->db->bind(":sub_dept_id",$sub_dept_id['sub_dept_id']);
					$this->db->bind(":created_by", $emp['created_by']);
					$this->db->bind(":created_at",  $emp['created_at']);

					$this->db->execute();

					
					return true;

			} catch (Exception $e) {
				return $e->getMessage();
			}
		}

		public function insertUser($user){
			try{
				$role_id = $this->getRole_id($user['role']);

				$this->db->query("INSERT INTO {$this->_ais_users} (emp_id, role_id, email, created_by, created_at) 
							VALUES(:emp_id, :role_id, :email, :created_by, :created_at)");

				$this->db->bind(":emp_id",$user['emp_id']);
				$this->db->bind(":role_id",$role_id['role_id']);
				$this->db->bind(":email", $user['email']);
				$this->db->bind(":created_by",$user['created_by']);
				$this->db->bind(":created_at", $user['created_at']);
				
				$this->db->execute();

				return true;

			}catch (Exception $e) {
				return $e->getMessage();
			}
							
		}

		/**
		* Insert function for Stocks
		* @param array 
		* @return boolean 
		**/
		public function insertStock($details){
			try {

				$current_date = date("Y-m-d H:i:s");
				
				foreach ($details as $key => $value) {
					$item_code = $this->setCode($value['item_name']);
					$unit_id = $this->getUnit_id($value['unit']);
					$category_id = $this->getCat_id($value['category']);


					$this->db->query("INSERT INTO {$this->_ais_item} (item_code, item_name, item_description, unit_id, category_id, standard_qty, reorder_point, current_stock, created_by, created_at, modified_by, modified_at) 
						VALUES(:item_code, :item_name, :item_description, :unit_id, :category_id, :standard_qty, :reorder_point, :current_stock, :created_by, :created_at, :modified_by, :modified_at)
						ON DUPLICATE KEY UPDATE item_name = VALUES(item_name), item_description = VALUES(item_description), unit_id = VALUES(unit_id), category_id = VALUES(category_id), standard_qty = VALUES(standard_qty), reorder_point = VALUES(reorder_point), current_stock = VALUES(current_stock), modified_by = VALUES(modified_by), modified_at = VALUES(modified_at)");
		
					$this->db->bind(":item_code",$item_code);
					$this->db->bind(":item_name",$value['item_name']);
					$this->db->bind(":item_description",$value['item_description']);
					$this->db->bind(":unit_id", $unit_id['unit_id']);
					$this->db->bind(":category_id", $category_id['category_id']);
					$this->db->bind(":standard_qty", $value['standard_quantity']);
					$this->db->bind(":reorder_point", $value['reorder_point']);
					$this->db->bind(":current_stock", $value['current_stock']);
					$this->db->bind(":created_by",1);
					$this->db->bind(":created_at", $current_date);
					$this->db->bind(":modified_by",1);
					$this->db->bind(":modified_at", $current_date);
					
					$this->db->execute();
				}
								
				return true;

			} catch (Exception $e) {
				return $e->getMessage();
			}
		}

		/**
		* Insert function for Stocks
		* @param array 
		* @return boolean 
		**/
		public function first_upload_stock($details){
			try {

					$this->db->query("INSERT INTO {$this->_activity_log} (user_id, module, action, obj_ids, obj_values, date_audit) 
						VALUES(:user_id, :module, :action, :obj_ids, :obj_values, :date_audit)");

					$this->db->bind(":user_id",$details['user_id']);
					$this->db->bind(":module", $details['module']);
					$this->db->bind(":action", $details['action']);
					$this->db->bind(":obj_ids", $details['item_ids']);
					$this->db->bind(":obj_values", $details['current_stock']);
					$this->db->bind(":date_audit",  $details['date_audit']);

					$this->db->execute();

					return true;

			} catch (Exception $e) {
				 return $e->getMessage();
			}
		}

	/**
	* Update data in employee table
	* @param array
	* @return boolean
	*/
	public function updateByEmpId($param) {

		try {

			$dept_id = $this->getDept_id($param['department']);
			$sub_dept_id = $this->getSub_dept_id($param['unit']);

			$role_id = $this->getRole_id($param['role']);


			$this->db->query("UPDATE ais_employee e LEFT JOIN ais_users u ON u.emp_id = e.emp_id
				SET  e.first_name= :fname, e.middle_name = :mname, e.last_name= :lname, e.suffix = :suffix,  e.position= :position, e.dept_id=:dept_id, e.sub_dept_id=:sub_dept_id, e.modified_by=:modified_by, e.modified_at=:modified_at, u.role_id=:role_id, u.modified_by=:modified_by, u.modified_at=:modified_at WHERE e.emp_id = :empid");

			$this->db->bind(":empid", $param['emp_id']);
			$this->db->bind(":fname", $param['first_name']);
			$this->db->bind(":mname", $param['middle_name']);
			$this->db->bind(":lname", $param['last_name']);
			$this->db->bind(":suffix",$param['suffix']);
			$this->db->bind(":position", $param['position']);
			$this->db->bind(":dept_id", $dept_id['dept_id']);
			$this->db->bind(":sub_dept_id", $sub_dept_id['sub_dept_id']);
			$this->db->bind(":modified_by", $param['modified_by']);
			$this->db->bind(":modified_at", $param['modified_at']);
			$this->db->bind(":role_id", $role_id['role_id']);
			
			$this->db->execute();

			return true;

		} catch (Exception $e) {

			return $e->getMessage();

		}
	}

	public function deleteEmployee($empid){
		
		try {
			$this->db->query("DELETE FROM {$this->_ais_employee} WHERE emp_id =:emp_id");

			$this->db->bind(":emp_id", $empid);

			$this->db->execute();

			return true;

		} catch (Exception $e) {
			
			$e->getMessage();

		}
	}

		/**
		* Insert function for Category
		* @param array 
		* @return boolean 
		**/
		public function insertCategory($details){
			try {

				$current_date = date("Y-m-d H:i:s");
				
				foreach ($details as $key => $value) {
					$category_code = $this->setCode($value['category_name']);

					$this->db->query("INSERT INTO {$this->_ais_category} (category_code, category_name, category_desc, created_by, created_at, modified_by, modified_at) 
						VALUES(:category_code, :category_name, :category_desc, :created_by, :created_at, :modified_by, :modified_at)
						ON DUPLICATE KEY UPDATE category_name = VALUES(category_name), category_desc = VALUES(category_desc), modified_by = VALUES(modified_by), modified_at = VALUES(modified_at)");

					$this->db->bind(":category_code",$category_code);
					$this->db->bind(":category_name",$value['category_name']);
					$this->db->bind(":category_desc",$value['category_desc']);
					$this->db->bind(":created_by",1);
					$this->db->bind(":created_at", $current_date);
					$this->db->bind(":modified_by",1);
					$this->db->bind(":modified_at", $current_date);
					
					$this->db->execute();
				}
								
				return true;

			} catch (Exception $e) {
				return $e->getMessage();
			}
		}

		/**
		* Insert function for Unit (Category)
		* @param array 
		* @return boolean 
		**/
		public function insertUnit($data){
			try {

				
				$this->db->query("INSERT INTO {$this->_ais_unit} (unit_code, unit_name, unit_desc, created_by, created_at) 
						VALUES(:unit_code, :unit_name, :unit_desc, :created_by, :created_at) 
						ON DUPLICATE KEY UPDATE unit_name = VALUES(unit_name), unit_desc = VALUES(unit_desc), modified_by = VALUES(created_by), modified_at = VALUES(created_at)");

				$this->db->bind(":unit_code",$data['unit_code']);
				$this->db->bind(":unit_name",$data['unit_name']);
				$this->db->bind(":unit_desc",$data['unit_desc']);
				$this->db->bind(":created_by",$data['created_by']);
				$this->db->bind(":created_at",$data['created_at']);

				$this->db->execute();
							
				return true;

			} catch (Exception $e) {
				return $e->getMessage();
			}
		}

		public function getDept_id($dept_name){
			return $this->fetch($this->_ais_department,'dept_name',$dept_name);
		}

		public function getSub_dept_id($sub_dept_name){
			return $this->fetch($this->_ais_sub_department,'sub_dept_name',$sub_dept_name);
		}

		public function getRole_id($role){
			return $this->fetch($this->_ais_role,'role',$role);
		}

		public function setEmp_status($status){
			if(strtolower($status)=="active"){
				return 1;
			}else{
				return 2;
			}
		}
		
		public function getCat_id($category_name){
			return $this->fetch($this->_ais_category,'category_name',$category_name);
		}

		public function getUnit_id($unit_name){
			return  $this->fetch($this->_ais_unit,'unit_name',$unit_name);
		}

		public function getCode($name){
			return $this->setCode($name);
		}

	}

?>