<?php 
namespace Mark\libraries;

class Ftp {

	private static $passive = true;

	private static $logMe = array();

	private static $_ftp_host;

	private static $_ftp_uname;

	private static $_ftp_pass;

	public function __construct($host,$username,$pass) {
		$this->_ftp_host = $host;
		$this->_ftp_uname = $username;
		$this->_ftp_pass = $pass;
	}

	/**
     * Establish connection
     *
     * @return true or False
     */
	public function connect() {

		$ftpConn = ftp_connect($this->_ftp_host);

		$login = ftp_login($ftpConn, $this->_ftp_uname, $this->_ftp_pass);

		ftp_pasv($ftpConn, $this->passive);

		if((!$ftpConn) || (!$login)) {
			$this->logMessage("Can't establish connection in " . $this->_ftp_host ." using ". $this->_ftp_uname.PHP_EOL, true);
			return false;
		} else {
			echo 'Connected!<br>';

			$this->logMessage('Connected to ' . $this->_ftp_host . ', using ' . $this->_ftp_uname.PHP_EOL);

			$this->remoteCount_Files($ftpConn);

			return true;
		}
	}

	/**
     * Scan & count files
     * Transfer File to Another server
     * @return true
     */
	public function remoteCount_Files($ftpConn) {

		$scanFiles = ftp_nlist($ftpConn, "/");

		$count = count($scanFiles); //Count Files in FTP Server

		$localFiles = $this->dirPath();

		$local = count($localFiles); //Count Files in Local Server

		if($local > $count) {

			$files = NULL;
			foreach ($localFiles as $lFiles) {

				$path = "readme.rst";

				$files .= $lFiles;

				if($lFiles == "." || $lFiles == "..") {

					continue;

				}

				ftp_nb_put($ftpConn,$lFiles,$path,FTP_BINARY);

			}

			$this->logMessage('Successfully copy ' . $files.PHP_EOL);

			ftp_close($ftpConn);

		} else {

		}
	}

	/**
     * Directory Path
     *
     * @return real path
     */
	public function dirPath() {

		$dir = APPPATH.'/config';

		$path = realpath($dir).DIRECTORY_SEPARATOR;

		$scanFiles = scandir($path);

		return $scanFiles;
	}

	/**
     * Log Error Message
     * 
     * @return true
     */
	private function logMessage($message) {

    	$this->logMe[] = $message;

    	$logFile = 'ftp_error_log.txt';

		file_put_contents($logFile, $this->logMe, FILE_APPEND | LOCK_EX);

	}

	/**
     * Display Error log
     * 
     * @return true
     */
	public function getMessage() {

		return $this->logMe;

	}

}