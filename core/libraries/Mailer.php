<?php

/**
 * @package PHP Mailer Class
 * @author 	Mark Anthony Naluz <anthony.naluz15@gmail.com>
 * @copyright Oct 2018 <Mark Anthony Naluz>
 */

class Mailer {

	/**
	 * Set Attachment mail-addresses.
	 */
	private $_attachment = array();
	/**
	 * Set Reply to mail-addresses.
	 */
	private $_replyTo = array();
	/**
	 * Set From email mail-addresses.
	 */
	private $_sender;
	/**
	 * Set From name mail-addresses.
	 */
	private $_SenderName;
	/**
	 * Collection of all CC (Copy Carbon) mail-addresses.
	 */
	private $_cc = array();
	/**
	 * Collection of all reciever mail-addresses.
	 */
	private $_to = array();
	/**
	 * Collection of all BCC (Blind Copy Carbon) mail-addresses.
	 */
	private $_bcc = array();
	/**
	 * The subject of the mail.
	 */
	private $_subject;
	/**
	 * The Message of the mail.
	 */
	private $_message;

	/**
	 * Wrap Message of the mail.
	 */
	private $_wordWrap = 100;
	/**
	 * The message of the mail.
	 */
	private $_body;
	/**
	 * The return path of the mail.
	 */
	private $_returnPath;
	/**
	 * The header of the mail.
	 */
	private $_header;
	/**
	 * The header of the mail.
	 */
	private $_ContentType = 'text/plain';

	/**
	 * Set CC for mail
	 * @param string
	 * @return Bool
	 */
	public function setCc($email) {
		if($this->_validateEmails($email) === true){
			$this->_cc[] = $email;
		}
		return false;
	}

	/**
	 * Set BCC for mail
	 * @param string
	 * @return Bool
	 */
	public function setBcc($email) {
		if($this->_validateEmails($email) === true){
			$this->_bcc[] = $email;
		}
		return false;
	}

	/**
	 * Set Reply to for mail
	 * @param string
	 * @return Bool
	 */
	public function setReply_to($email) {
		if($this->_validateEmails($email) === true){
			$this->_replyTo[] = $email;
		}
		return false;
	}

	/**
	 * Set reciever for mail
	 * @param string
	 * @return Bool
	 */
	public function setTo($email) {
		if($this->_validateEmails($email) === true){
			$this->_to[] = $email;
		}
		return false;
	}

	/**
	 * Set From for mail
	 * @param string $email
	 * @param string $name
	 * @return void
	 */
	public function setFrom($email,$name = NULL) {
		if(!empty($name)) {
			$this->_SenderName = $name;
			$this->_sender = $email;
		} else {
			$this->_sender = $email;
			$this->_SenderName = $email;
		}
	}

	/**
	 * Set attachment for mail
	 * @param resource $file
	 * @return void
	 */
	public function setAttachment($file) {
		if(isFile($file)) {
			$this->_attachment[] = $file;
		}
	}

	/**
	 * Check file exists or not
	 * @param resource $file
	 * @return boolean
	 */
	public function isFile($file) {
		if(is_array($file) && (count($file) > 0)) {
			foreach ($file as $key => $value) {
				if(file_exists($value)) {
					return true;
				} else {
					return false;
				}
			}
		}
	}

	/**
	 * Set Attachment for mail
	 * @param string
	 * @return void
	 */
	private function _prepAttachment($boundary) {
		if(count($this->_attachment) > 0){
			for($i=0;$i<count($this->_attachment);$i++){
				if(is_file($this->_attachment[$i])){
					$this->_message .= "--".$boundary."\n";
					$fp =    @fopen($this->_attachment[$i],"rb");
					$data =  @fread($fp,filesize($this->_attachment[$i]));
					@fclose($fp);
					$data = chunk_split(base64_encode($data));
					$this->_message .= "Content-Type: application/octet-stream; name=\"".basename($this->_attachment[$i])."\"\n" . 
					"Content-Description: ".basename($this->_attachment[$i])."\n" .
					"Content-Disposition: attachment;\n" . " filename=\"".basename($this->_attachment[$i])."\"; size=".filesize($this->_attachment[$i]).";\n" . "Content-Transfer-Encoding: base64\n\n" . $data . "\n\n";
				}
			}
		}
	}

	/**
	 * Set Subject for mail
	 * @param string
	 * @return void
	 */
	public function setSubject($subject) {
		$this->_subject = $subject;
	}

	/**
	 * Set Message body for mail
	 * @param string
	 * @return void
	 */
	public function setMessage($message) {
		$this->_body = $message;
	}

	/**
	 * Set Content type for mail
	 * @param bool
	 * @return void
	 */
	public function isHTML($html = true) {
		if($html) {
			$this->_ContentType = 'text/html';
		} else {
			$this->_ContentType = 'text/plain';
		}
	}

	/**
	 * validate Email if valid
	 * @param array string $email
	 * @param string $email
	 * @return Boolean
	 */
	private function _validateEmails($email) {

		if(is_array($email)) {
			foreach ($email as $key => $value) {
				if(filter_var($value, FILTER_VALIDATE_EMAIL)) {
					if(filter_var($value, FILTER_SANITIZE_EMAIL)) {
						return true;
					}
				} else {
					return false;
				}
			}
		} else {
			if(filter_var($email, FILTER_VALIDATE_EMAIL)) {
				if(filter_var($email, FILTER_SANITIZE_EMAIL)) {
					return true;
				}
			} else {
				return false;
			}
		}
	}

	/**
	 * Mail triger sent
	 * @return bool
	 */
	public function send() {

		$this->_header = "From: ".$this->_SenderName." <".$this->_sender.">";
		$rand = md5(time()); 
		$boundary = "==Multipart_Boundary_x{$rand}x";
		$this->_header .= "\nMIME-Version: 1.0\n" . "Content-Type: text/html;charset=UTF-8\r\n" . " boundary=\"{$boundary}\"\n";

		if(!empty($this->_replyTo)) {
			if(is_array($this->_replyTo) && (count($this->_replyTo) > 0)) {
				$this->_header .= 'Reply-To: '.implode(',', $this->_replyTo)."\r\n";
			}
		}

		if(!empty($this->_bcc)) {
			if(is_array($this->_bcc) && (count($this->_bcc) > 0)) {
				$this->_header .= 'Bcc: '.implode(',', $this->_bcc)."\r\n";
			}
		}

		if(!empty($this->_cc)) {
			if(is_array($this->_cc) && (count($this->_cc) > 0)) {
				$this->_header .= 'Cc: '.implode(',', $this->_cc)."\r\n";
			}
		}
		if(!empty($this->_attachment)) {
			$this->_prepAttachment($boundary);
		}
		$this->_header .= 'X-Mailer: PHP/' . phpversion();

		$this->_returnPath = "-f" . $this->_sender;

		$to = implode(',', $this->_to);

		$body = wordwrap($this->_body, $this->_wordWrap);

		$sent = mail($to, $this->_subject, $body, $this->_header, $this->_returnPath);

		if($sent){ return TRUE; } else { return FALSE; }

	}

}

?>