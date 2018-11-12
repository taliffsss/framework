<?php

define('ENVIRONMENT', isset($_SERVER['MAN']) ? $_SERVER['MAN'] : 'development');

switch (ENVIRONMENT) {
	case 'development':
		error_reporting(-1);
		ini_set('display_errors', 1);
		ini_set("error_log", "app/logs/".date('Y-m-d').'.log');
	break;
	case 'production':
		ini_set('display_errors', 0);
		ini_set("error_log", "app/logs".Constant::ERROR_LOG.date('Y-m-d').'.log');
		error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
	break;
	default:
		header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
		echo 'The application environment is not set correctly.';
		exit(1);
}

$GLOBALS["config"] = array(
		"addName" => "MAN",
		"domain" => "http://localhost:8080/mark",
		"path" => array(
			"app" => "app/",
			"core" => "core/",
			"index" => "index.php",
		),
		"defaults" => array(
			"controller" => "Main",
			"method" => "index"
		),
		"routes" => array(),
		"database" => array(
			"host" => "localhot",
			"username" => "",
			"password" => "",
			"name" => ""
		)
	);
require_once $GLOBALS["config"]["path"]["core"]."spl_register.php";
new Routes();