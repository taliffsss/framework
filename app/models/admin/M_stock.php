<?php
/**
 *
 * @package Mdeol Class
 * @author  Kristallynn
 */

class M_stock extends My_Model {

	function __construct() {
		parent::__construct();
	}

	public function _get_all_stocks(){
		try {

			$this->db->query("SELECT DISTINCT c.category_code, c.category_name, u.unit_code, u.unit_name, t.item_id, t.item_code, t.item_name, t.standard_qty, t.reorder_point, t.current_stock FROM {$this->_ais_item} t LEFT JOIN {$this->_ais_category_unit} cu ON t.unit_id = cu.unit_id LEFT JOIN {$this->_ais_unit} u ON cu.unit_id=u.unit_id LEFT JOIN {$this->_ais_category} c ON t.category_id=c.category_id");
			
			$this->db->execute();

			$row = $this->db->resultset();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();

		}
	}

	public function _update_item($data) {

		try {
			
			$this->db->query("UPDATE {$this->_ais_item} SET current_stock = (:itemCurrentStocks + :purchaseQuantity), modified_by = :modified_by, modified_at = :modified_at WHERE item_id = :item_id");

			$this->db->bind(":itemCurrentStocks",$data['itemCurrentStocks']);
			$this->db->bind(":purchaseQuantity",$data['purchaseQuantity']);
			$this->db->bind(":modified_by",$data['modified_by']);
			$this->db->bind(":modified_at",$data['modified_at']);
			$this->db->bind(":item_id",$data['itemid']);

			$this->db->execute();

			return true;

		} catch (Exception $e) {
			
			$e->getMessage();

		}

	}

	/**
	* Get Item / Stock details via item_id
	* @param int item_id
	**/ 
	public function _get_stock_by_id($item_id){
		try {
			$this->db->query("SELECT DISTINCT c.category_code, c.category_name, u.unit_code, u.unit_name, t.item_id, t.item_code, t.item_name, t.item_description, t.standard_qty, t.reorder_point, t.current_stock FROM {$this->_ais_item} t LEFT JOIN {$this->_ais_category_unit} cu ON t.unit_id = cu.unit_id LEFT JOIN {$this->_ais_unit} u ON cu.unit_id=u.unit_id LEFT JOIN {$this->_ais_category} c ON t.category_id=c.category_id WHERE t.item_id= :item_id");

			$this->db->bind(":item_id",$item_id);

			$this->db->execute();

			$row = $this->db->single();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();

		}
	}

	/**
	* Get Unit list based on category id
	* @param int category_id
	**/ 
	public function _get_unit_based_on_category($category_id){
		try {

			$this->db->query("SELECT * FROM `ais_unit` WHERE `unit_id` IN (SELECT `unit_id` FROM `ais_category_unit` WHERE `category_id` = :category_id)");

			$this->db->bind(':category_id', $category_id);

			$this->db->execute();

			$row = $this->db->resultset();

			return $row;

		} catch (Exception $e) {
			$e->getMessage();
		}
	}

	/**
	* Insert added item details
	* @param array 
	* @return boolean
	**/
	public function insert_item($data){
		try {
				
				$itemcode = $this->setCode($data['itemname']);

				$this->db->query("INSERT INTO {$this->_ais_item} (item_code, item_name, item_description, unit_id, category_id, standard_qty, reorder_point, created_by, created_at) VALUES(:item_code, :itemname, :itemdesc, :unit_id, :category_id, :standard_qty, :reorder_point, :created_by, :created_at)");


				$this->db->bind(":category_id",$data['catid']);
				$this->db->bind(":unit_id",$data['unitid']);
				$this->db->bind(":item_code", $itemcode);
				$this->db->bind(":itemname",$data['itemname']);
				$this->db->bind(":itemdesc",$data['itemdesc']);
				$this->db->bind(":standard_qty",$data['standard_qty']);
				$this->db->bind(":reorder_point",$data['reorder_point']);
				$this->db->bind(":created_by", $data['created_by']);
				$this->db->bind(":created_at",$data['created_at']);
				
				$this->db->execute();
				
				return true;

		} catch (Exception $e) {
			$e->getMessage();
		}
	}

	/**
	* Update item details
	* @param array 
	* @return boolean
	**/
	public function edit_item($data){
		try {
				$this->db->query("UPDATE {$this->_ais_item} SET item_name=:itemname, item_description=:itemdesc, standard_qty=:standard_qty, reorder_point=:reorder_point, modified_by=:modified_by, modified_at=:modified_at WHERE item_id=:itemid");

				$this->db->bind(":itemid",$data['itemid']);
				$this->db->bind(":itemname",$data['itemname']);
				$this->db->bind(":itemdesc",$data['itemdesc']);
				$this->db->bind(":standard_qty",$data['standard_qty']);
				$this->db->bind(":reorder_point",$data['reorder_point']);
				$this->db->bind(":modified_by", $data['modified_by']);
				$this->db->bind(":modified_at",$data['modified_at']);
				
				$this->db->execute();
				
				return true;

		} catch (Exception $e) {
			$e->getMessage();
		}
	}
	
	/**
	 * Count record column
	 * @param string
	 * @return int
	 */
	public function _item_count($item_name) {
		return $this->count_column_results($this->_ais_item,'item_name',$item_name);
	}

	/**
	* Get Item / Stock details via category id
	* @param int item_id
	**/ 
	public function _get_stock_by_catid($category_id){
		try {
			$this->db->query("SELECT DISTINCT u.unit_code, u.unit_name, t.item_id, t.item_code, t.item_name, t.item_description, t.standard_qty, t.reorder_point, t.current_stock FROM {$this->_ais_item} t LEFT JOIN {$this->_ais_category_unit} cu ON t.unit_id = cu.unit_id LEFT JOIN {$this->_ais_unit} u ON cu.unit_id=u.unit_id LEFT JOIN {$this->_ais_category} c ON t.category_id=c.category_id WHERE t.category_id= :category_id");

			$this->db->bind(":category_id",$category_id);

			$this->db->execute();

			$row = $this->db->resultset();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();

		}
	}

	/**
	* Get Item / Stock details via category id
	* @param int item_id
	**/ 
	public function _get_stocks(){
		try {
			$this->db->query("SELECT item_id, current_stock FROM {$this->_ais_item}");
			
			$this->db->execute();

			$row = $this->db->resultset();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();

		}
	}


	public function _decrease_item($data) {

		try {
			
			$this->db->query("UPDATE {$this->_ais_item} SET current_stock = :new_stock , modified_by = :modified_by, modified_at = :modified_at WHERE item_id = :item_id");

			$this->db->bind(":new_stock",$data['new_stock']);
			$this->db->bind(":modified_by",$data['modified_by']);
			$this->db->bind(":modified_at",$data['modified_at']);
			$this->db->bind(":item_id",$data['item_id']);

			$this->db->execute();

			return true;

		} catch (Exception $e) {
			
			$e->getMessage();

		}

	}

	/**
	* Get ALL item via IN condition 
	* @param string item_id
	**/ 
	public function _get_current_stock($item_ids){
		try {
			$this->db->query("SELECT DISTINCT t.item_id, t.item_code, t.item_name, t.current_stock FROM {$this->_ais_item} WHERE t.item_id IN ($item_ids) ");

			$this->db->bind(":item_ids",$item_id);

			$this->db->execute();

			$row = $this->db->single();

			return $row;

		} catch (Exception $e) {

			$e->getMessage();

		}
	}

}
?>