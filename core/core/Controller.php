<?php
namespace AIS\core;

class Controller{

	function __construct() {
		$GLOBALS["instances"][] = &$this;
	}

}
?>