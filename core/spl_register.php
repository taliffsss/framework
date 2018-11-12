<?php

spl_autoload_register(function($class){
	$core = $GLOBALS["config"]["path"]["core"];
	$app = $GLOBALS["config"]["path"]["app"];

	if(file_exists("{$core}libraries/{$class}.php")) {

		require_once "{$core}libraries/{$class}.php";

	} elseif (file_exists("{$app}controllers/{$class}.php")) {

		require_once "{$app}controllers/{$class}.php";

	} elseif (file_exists("{$app}libraries/{$class}.php")) {

		require_once "{$app}libraries/{$class}.php";

	} elseif (file_exists("{$app}models/{$class}.php")) {

		require_once "{$app}models/{$class}.php";

	}

});
?>