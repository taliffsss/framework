<?php

/**
 * This class can encrypt and decrypt any types you want.
 *
 * @package Encryption Class
 * @author 	Mark Anthony Naluz <anthony.naluz15@gmail.com>
 * @copyright Jul 2018 <Mark Anthony Naluz>
 */

class Encryption {
	
	/**
	* Reference
	* @link	http://php.net/manual/en/function.openssl-encrypt.php	PHP Web Page
	*/

	/**
	* Set cryptographically safe way.
	* @var openssl random pseudo bytes
	*/
	protected static $_key;

	/**
	* ciphers and modes
	*/
	protected static $_cipher;

	/**
	* @var openssl random pseudo bytes
	*/
	protected static $iv;

	/**
	* @var Int
	*/
	protected static $_option;

	//Constructor
	function __construct() {

		self::$_key = self::key();

	}

	/**
	* Encrypt Data
	* key generated in a cryptographically safe way.
	* @var String
	* @return String
	*/
	public static function encrypt($data) {

		$ivlen = self::ivlen();

		$iv = self::_iv($ivlen);

		$crypt = self::_openssl_encrypt($data,self::$_key,$iv);

		$_hmac = self::_hash_hmac($crypt,self::$_key);

		$_encrypt = base64_encode($iv.$_hmac.$crypt);

		return $_encrypt;
	}

	/**
	* Decrypt Data
	* key generated in a cryptographically safe way.
	* @var Int
	* @var String
	* @return String
	*/
	public static function decrypt($data,$sha2len=32) {

		$_decode = base64_decode($data);

		$ivlen = self::ivlen();

		$iv = substr($_decode, 0, $ivlen);

		$hmac = substr($_decode, $ivlen, $sha2len);

		$crypt = substr($_decode, $ivlen + $sha2len);

		$_decrypt = openssl_decrypt($crypt, self::cipher(), self::$_key, self::option(), $iv);

		$_hash = self::_hash_hmac($crypt,self::$_key);

		$res = self::_hash_equals($hmac,$_hash,$_decrypt);

		return $res;
		
	}

	/**
	* Compares two strings using the same time whether they're equal or not
	* 
	* @var String
	*/
	public static function _hash_equals($hmac,$_hash,$_decrypt) {

		if (hash_equals($hmac, $_hash)) {

			return $_decrypt;
		}

	}

	/**
	* Decrypt given data with given method and key
	* @var String & openssl random pseudo bytes
	*/
	public static function _openssl_decrypt($data,$key,$iv) {

		$_decrypt = openssl_decrypt($crypt, self::cipher(), $key, self::option(), $iv);

		return $_decrypt;
	}

	/**
	* Generate a keyed hash value using the HMAC method
	* @var String & openssl random pseudo bytes & Int
	*/
	public static function _hash_hmac($crypt,$key,$binary=true) {

		$_hmac = hash_hmac('sha256', $crypt, $key, $binary);

		return $_hmac;

	}

	/**
	* Encrypts given data with given method and key
	* @var String & openssl random pseudo bytes
	*/
	public static function _openssl_encrypt($data,$key,$iv) {

		$_cipher_text = openssl_encrypt($data, self::cipher(), $key, self::option(), $iv);

		return $_cipher_text;
	}

	/**
	* @var cipher methods
	*/
	public static function cipher() {

		self::$_cipher = trim("AES-256-CBC");

		return self::$_cipher;
	}

	/**
	* key generated in a cryptographically safe way.
	* @var openssl random pseudo bytes
	*/
	public static function key() {

		$key = bin2hex(openssl_random_pseudo_bytes(35));

		return $key;
	}

	/**
	* cipher initialization vector (iv) length.
	* @var Int
	*/
	public static function ivlen() {

		$ivlen = openssl_cipher_iv_length(self::cipher());

		return $ivlen;

	}

	/**
	* Generate a pseudo-random string of bytes
	* @var openssl random pseudo bytes
	*/
	public static function _iv($ivlen) {

		$iv = openssl_random_pseudo_bytes($ivlen);

		return $iv;
	}

	/**
	* Can set either of the two OPENSSL_RAW_DATA and OPENSSL_ZERO_PADDING.
	* @var Int
	*/
	public static function option() {

		self::$_option = OPENSSL_ZERO_PADDING;

		return self::$_option;
	}
	
}