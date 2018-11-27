<?php

class M_question extends My_Model {

	function __construct() {
		parent::__construct();
	}

	/**
	 * Get security question
	 * @return array string
	 */
	public function getSecurity_Question() {

		return $this->fetchall($this->_ais_security_question);

	}

	/**
	 * Get single question
	 * @param Int
	 * @return array string
	 */
	public function getquestion($id) {

		return $this->fetch($this->_ais_security_question,'security_id',$id);

	}
	
	/**
	 * Get single question
	 * @param string
	 * @return array string
	 */
	public function getquestionByName($question) {

		return $this->fetch($this->_ais_security_question,'security_question',$question);

	}
}
?>