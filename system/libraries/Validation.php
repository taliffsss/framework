<?php
namespace Mark\libraries;
use Mark\core\Database;

class Validation {

	private $_passed = false;
	private $_error = array();
	private $db = false;

	private function __construct() {
		$this->db = Database::getIntance();
	}

	/**
	 * validate input field
	 * @param string (name of input fields)
	 * @param rules array (set of rules of fields)
	 * @return bool
	 */
	public function validate($source, $items = array()) {

		foreach ($items as $item => $rules) {
			foreach ($rules as $rule => $rule_value) {
				
				$value = trim($source[$item]);
				$item = escape($item);

				if($rule === 'required' && empty($value)) {
					$this->addError("{$item} is required");
				} elseif(!empty($value)) {
					
					switch ($rule) {
						case 'min':
							if(strlen($value) < $rule_value) {
								$this->addError("{$item} must be a minimum of {$rule_value} characters.");
							}
							break;
						case 'max':
							if(strlen($value) > $rule_value) {
								$this->addError("{$item} must be a maximum of {$rule_value} characters.");
							}
							break;
						case 'matches':
							if(strlen($value) < $rule_value) {
								$this->addError("{$item} must be a minimum of {$rule_value} characters.");
							}
							break;
						case 'unique':
							$this->db->get($rule_value, array($item, '=', $value));
							if($this->db->rowCount()) {
								$this->addError("{$item} already exist.");
							}
							break;
					}
				}
			}
		}

		if(empty($this->_error)) {
			$this->_passed = true;
		}
	}

	/**
	 * Stored error
	 * @param string
	 * @return void 
	 */
	private function addError($error) {
		$this->_error[] = $error;
	}

	/**
	 * get error
	 * @return mixed array 
	 */
	public function errors() {
		return $this->_error;
	}

	/**
	 * check if its passed or not
	 * @return bool 
	 */
	public function passed() {
		return $this->_passed;
	}

}

?>