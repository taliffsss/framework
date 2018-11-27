<?php

spl_autoload_register(function($class){
	$core = $GLOBALS["autoload"]["path"]["system"];
	$app = $GLOBALS["autoload"]["path"]["app"];

	$file = str_replace('Mark\\', ' ', $class);

	if(file_exists("{$core}libraries/{$file}.php")) {

		require_once "{$core}libraries/{$file}.php";

	} elseif (file_exists("{$core}core/{$file}.php")) {

		require_once "{$core}core/{$file}.php";

	} elseif (file_exists("{$app}controllers/{$file}.php")) {

		require_once "{$app}controllers/{$file}.php";

	} elseif (file_exists("{$app}core/{$file}.php")) {

		require_once "{$app}core/{$file}.php";
	
	} elseif (file_exists("app/controllers/admin/{$file}.php")) {

		require_once "app/controllers/admin/{$file}.php";
		
	} elseif (file_exists("app/models/admin/{$file}.php")) {

		require_once "app/models/admin/{$file}.php";
		
	} elseif (file_exists("{$app}models/{$file}.php")) {

		require_once "{$app}models/{$file}.php";

	} elseif (file_exists("{$app}libraries/{$file}.php")) {

		require_once "{$app}libraries/{$file}.php";

	}

});
?>
		