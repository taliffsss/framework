<?php
namespace Mark\core;
use Mark\core\Config;

use \PDO;

/**
 * @package Database Class
 * @author 	Mark Anthony Naluz <anthony.naluz15@gmail.com>
 * @copyright Jul 2018 <Mark Anthony Naluz>
 */

class Database {

	private static $_instance = null;

	private static $_config = null;


	/**
	* set up the variables in the class
	* $dbh for Database Connection
	* $error for handling error
	* $stmt for Statement handling
	*/
	private $dbh;
	private $error;
	private $stmt;
	public $_results;

	/**
	* $dsn Set Host and DBName
	* $options Set options
	* try Create a new PDO instanace
	* Catch, Catch any errors
	*/

	private function __construct(){

		$this->connect();

	}

	/**
	 * Established MySQL connection
	 */
	private function connect() {
		$dsn = 'mysql:host=' . Config::get('autoload','mysql/host') . ';dbname=' . Config::get('autoload','mysql/name');

		$options = array(
			PDO::ATTR_PERSISTENT => true,
			PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		);

		try{

			$this->dbh = new PDO($dsn, Config::get('autoload','mysql/username'), Config::get('autoload','mysql/password'), $options);

			return $this->dbh;
			
		} catch(PDOException $e){

			$this->error = $e->getMessage();

		}
	}

	/**
	 * Create an instance
	 */
	public static function getIntance() {

		self::$_config = Config::get('config','database');

		if(self::$_config == TRUE) {
			if(!isset(self::$_instance)) {
				self::$_instance = new Database();
			}

			return self::$_instance;
		}

		return false;
	}

	/**
	* Prepare function allows you to bind values into your SQL statements.
	* it takes away the threat of SQL Injection
	* @param $query Mysql query
	*/
	public function query($query){
		$this->stmt = $this->dbh->prepare($query);
	}

	/**
	* Select function
	* @param $table TableName
	* @param $where Where clause
	* @param $fields FieldName
	* @param $order Order By
	* @param $Limit Limit
	*/
	public function select($table, $where = '', $fields = '', $order = '', $limit = NULL, $offset = '') {

		$query = "SELECT $fields FROM $table "
				.($where ? " WHERE $where ": '' )
				.($limit ? " LIMIT $where ": '' )
				.($offset && $limit ? " OFFSET $offset ": '' )
				.($order ? " ORDER BY $order ": '' );

		$this->query($query);

	}

	/**
	* Select function
	* @param $table TableName
	* @param $data FieldName & FieldValue
	*/
	public function insert($table,$data) {

		ksort($data);

		$fieldsName = implode(",", array_keys($data));
		$fieldValue = ':'.implode(", :", array_keys($data));

		$query = "INSERT INTO $table ($fieldsName) VALUES ($fieldValue)";

		$this->query($query);

		foreach ($data as $key => $value) {
			$this->bind(":$key",$value);
		}

		$this->execute();

	}

	/**
	* Update data
	* @param $table TableName
	* @param Array $data FieldName & FieldData
	* @param $where Where clause
	*/
	public function update($table, array $data, $where = '') {

		ksort($data);
		$fieldsDetails = NULL;

		foreach ($data as $key => $value) {
			$fieldsDetails .= "$key = :$key,";
		}

		$fieldsDetails = rtrim($fieldsDetails,',');

		$query = "UPDATE $table SET $fieldsDetails ".($where ? "WHERE ".$where : '');

		$this->query($query);

		foreach ($data as $key => $value) {
			$this->bind(":$key",$value);
		}

		$this->execute();
	}

	/**
	* Prepare function allows you to bind values into your SQL statements.
	* it takes away the threat of SQL Injection
	* @param $query Mysql query
	*/
	public function _query($query, $params = array()){
		$this->error = false;

		if($this->stmt = $this->dbh->prepare($query)) {
			$x = 1;

			if(count($params)) {
				foreach ($params as $param) {
					$this->stmt->bindValue($x,$param);
					$x++;
				}
			}

			if($this->stmt->execute()) {
				$this->_results = $this->stmt->fetchAll(PDO::FETCH_OBJ);
				$this->rowCount();
			} else {
				$this->error = true;
			}
		}
		return $this;
	}

	/**
	 * Check and validate query in where clause
	 * @param string $action
	 * @param string $table
	 * @param array $where
	 * @return bool
	 */
	public function action($action,$table, $where = array()) {

		if(count($where) === 3) {
			$operators = array('=','>','<','>=','<=','!=');

			$field = $where[0];
			$operator = $where[1];
			$value = $where[2];

			if(in_array($operator, $operators)) {
				$sql = "{$action} FROM {$table} WHERE {$field} {$operator} ?";

				if($this->_query($sql, array($value))) {
					return $this;
				}
			}
		}
		return false;
	}

	/**
	 * Get data in where clause
	 * @param string $table Name
	 * @param string $where
	 * @return void
	 */
	public function get($table,$where) {
		return $this->action('SELECT *',$table,$where);
	}

	/**
	 * Delete data
	 * @param string $table Name
	 * @param string $where
	 * @return void
	 */
	public function delete($table,$where) {
		return $this->action('DELETE ',$table,$where);
	}

	/**
	* the variable is bound as a reference and will only be evaluated at the time that PDOStatement::execute()
	* @param $param is the placeholder value that we will be using in our SQL statement :param
	* @param $value is the actual value that we want to bind to the placeholder
	* @param $type is the datatype of the parameter
	*/
	public function bind($param, $value, $type = null){  
		if (is_null($type)) {
			switch (true) {
				case is_int($value):
				$type = PDO::PARAM_INT;
				break;
			case is_bool($value):
				$type = PDO::PARAM_BOOL;
				break;
			case is_null($value):
				$type = PDO::PARAM_NULL;
				break;
			default:
				$type = PDO::PARAM_STR;
			}
		}  
		$this->stmt->bindValue($param, $value, $type);
	}

	/**
	* The execute method executes the prepared statement.
	*/
	public function execute(){
		return $this->stmt->execute();
	}

	/**
	* The Result Set function returns an array of the result set rows
	*/
	public function resultSet($param = null){
		$this->execute();
		if($param != null) {
			return $this->stmt->fetchAll($param);
		} else {
			return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
		}
		
	}

	public function results() {
		return $this->_results;
	}

	public function first() {
		return $this->_results[0];
	}

	/**
	* the Single method simply returns a single record from the database
	*/
	public function single($param = null){
		$this->execute();
		if($param != null) {
			return $this->stmt->fetch($param);
		} else {
			return $this->stmt->fetch(PDO::FETCH_ASSOC);
		}
	}

	/**
	* the fetch column method simply returns number of rows from the database
	*/
	public function fetchColumn($param = null) {
		$this->execute();
		return $this->stmt->fetchColumn($param);
	}

	/**
	* returns the number of effected rows
	*/
	public function rowCount(){
		return $this->stmt->rowCount();
	}

	/**
	* returns the last inserted Id as a string
	*/
	public function InsertId(){
		return $this->dbh->lastInsertId();
	}

	/**
	* Transactions allows you to run multiple changes to a database
	*/
	public function _beginTransaction(){
		return $this->dbh->beginTransaction();
	}

	/**
	* End a transaction and commit your changes
	*/
	public function endTransaction(){
		return $this->dbh->commit();
	}

	/**
	* Cancel a transaction and roll back your changes
	*/
	public function cancelTransaction(){
		return $this->dbh->rollBack();
	}

	/**
	* dumps the the information that was contained in the Prepared Statement
	*/
	public function _debugDumpParams(){
		return $this->stmt->debugDumpParams();
	}
}