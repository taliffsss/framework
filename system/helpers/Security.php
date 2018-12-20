<?php

function ip_address() {

	$ipaddress = $_SERVER['REMOTE_ADDR'];

	if (!empty($_SERVER['HTTP_CLIENT_IP'])) {

		$ipaddress = $_SERVER['HTTP_CLIENT_IP'];

	} else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {

		$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];

	} else if (!empty($_SERVER['HTTP_X_FORWARDED'])) {

		$ipaddress = $_SERVER['HTTP_X_FORWARDED'];

	} else if (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {

		$ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];

	} else if (!empty($_SERVER['HTTP_FORWARDED'])) {

		$ipaddress = $_SERVER['HTTP_FORWARDED'];

	} else if (!empty($_SERVER['REMOTE_ADDR'])) {

		$ipaddress = $_SERVER['REMOTE_ADDR'];

	} else {

		$ipaddress = 'UNKNOWN';

	}

	return $ipaddress;
}
?>