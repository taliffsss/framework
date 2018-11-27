<?php

class My_Model extends Model {
	/**
	 * Database table
	 * @var string
	 */
	public $_activity_log = 'ais_activity_log';
	public $_ais_category = 'ais_category';
	public $_ais_category_unit = 'ais_category_unit';
	public $_ais_department = 'ais_department';
	public $_ais_employee = 'ais_employee';
	public $_ais_item = 'ais_item';
	public $_ais_login_history = 'ais_login_history';
	public $_ais_purchased = 'ais_purchased';
	public $_ais_reorder_history = 'ais_reorder_history';
	public $_ais_report = 'ais_report';
	public $_ais_report_details = 'ais_report_details';
	public $_ais_role = 'ais_role';
	public $_ais_security_question = 'ais_security_question';
	public $_ais_sub_department = 'ais_sub_department';
	public $_ais_transaction = 'ais_transaction';
	public $_ais_unit = 'ais_unit';
	public $_ais_users = 'ais_users';
	
	function __construct() {
		parent::__construct();
	}

	/**
	 * Get Single Data with where clause
	 * @param string $table
	 * @param string $attr
	 * @param string Int $param
	 * @return obj
	 */
	public function fetch($table,$attr,$param) {

		try {

			$this->db->query("SELECT * FROM {$table} WHERE {$attr} = :param");

			$this->db->bind(":param",$param);

			$row = $this->db->single();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();

		}

	}

	/**
	 * Get all Data
	 * @param string $table
	 * @return array obj
	 */
	public function fetchall($table) {

		try {

			$this->db->query("SELECT * FROM {$table}");

			$this->db->execute();

			$row = $this->db->resultset();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();

		}

	}

	/**
	 * Fetch by clause
	 * @param string $table
	 * @return array obj
	 */
	public function fetchall_by_clause($table,$attr,$param) {

		try {

			$this->db->query("SELECT * FROM {$table} WHERE {$attr} = :param");

			$this->db->bind(":param",$param);

			$this->db->execute();

			$row = $this->db->resultset();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();

		}

	}

	/**
	 * get the column count
	 * @param string $table
	 * @param string $attr
	 * @param string or int $param
	 * @return int
	 */
	public function count_column_results($table,$attr,$param) {

		try {

			$this->db->query("SELECT * FROM {$table} WHERE {$attr} = :param");

			$this->db->bind(":param",$param);

			$this->db->execute();

			$row = $this->db->rowCount();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();

		}

	}

	/**
	 * Count all data
	 * @param string $table
	 * @return int
	 */
	public function count_all($table) {

		try {

			$this->db->query("SELECT * FROM {$table}");

			$this->db->execute();

			$row = $this->db->rowCount();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();

		}

	}

	/**
	 * Delete data from table
	 * @param string $table
	 * @param string $attr
	 * @param string Int $param
	 * @return true
	 */
	public function _delete($table,$attr,$param) {

		try {

			$this->db->query("SELECT * FROM {$table} WHERE {$attr} = :param");

			$this->db->bind(":param",$param);

			$this->db->execute();

			return true;

		} catch (Exception $e) {

			$e->getMessage();

		}

	}

	/**
	 * Get columns in table
	 * @param string
	 * @return array string
	 */
	public function _getcolumns($table) {

		try {

			$this->db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME LIKE '{$table}'");

			$this->db->execute();

			$row = $this->db->resultset();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();

		}

	}

	/**
	 * Get ids Data 
	 * IN Condition
	 * @param string $table
	 * @param string $col_id
	 * @param string $col_name
	 * @param string $names
	 * @return array obj
	 */
	public function fetchids_based_on_name($table, $col_id, $col_name, $names) {

		try {

			$this->db->query("SELECT {$col_id} FROM {$table} WHERE {$col_name} IN ({$names})");
			
			$this->db->execute();

			$row = $this->db->resultset();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();

		}

	}

	/**
	* Generate code
	* @param string $name
	* @return mix string int $code
	**/
	public function setCode($name){
		$digits = 5;
		$number = str_pad(rand(0, pow(10, $digits)-1), $digits, '0', STR_PAD_LEFT);

		$code = strtoupper(substr($name, 0, 3)).$number;
		return $code;
	}
}

?>