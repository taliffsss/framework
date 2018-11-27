<?php 

class Question extends \Mark\core\My_Controller {

	function __construct() {
		parent::__construct();
	}

	/**
	 * Security question dropdown list
	 * @return Array String
	 */
	public function _questionaire() {

		return $this->_show_questionaire();

	}
}

?>