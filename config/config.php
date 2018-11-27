<?php
$GLOBALS["autoload"] = array(
	"addName" => "MAN",
	"domain" => array(
		"production" => "http://leaveform.info/login",
		"development" => "http://localhost:8080/man",
		"testing" => "http://localhost:8080/test",
	),
	"path" => array(
		"app" => "app/",
		"system" => "system/",
		"vendor" => 'vendor/',
		"index" => "index.php",
	),
	"defaults" => array(
		"controller" => "Main",
		"method" => "index"
	),
	"remember" => array(
		"cookie_name" => "hash",
		"cookie_expiry" => 604800
	),
	"session" => array(
		"session_name" => "MAFN",
		"token" => "csrf-token"
	),
	"mysql" => array(
		"host" => "localhost",
		"username" => "root",
		"password" => "",
		"name" => "ais"
	)
);
$GLOBALS["instances"] = array();
$GLOBALS["config"] = array();

require_once $GLOBALS["autoload"]["path"]["system"]."spl_register.php";
require_once $GLOBALS["autoload"]["path"]["vendor"]."autoload.php";
require_once $GLOBALS["autoload"]["path"]["app"]."config/Router.php";
require_once $GLOBALS["autoload"]["path"]["app"]."config/config.php";

new Autoload();
?>