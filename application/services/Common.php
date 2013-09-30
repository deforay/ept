<?php

class Application_Service_Common {

	public function sendMail($to, $cc, $bcc, $subject, $message, $fromMail = null, $fromName = null, $attachments = array()) {
        //Send to email
        $to = explode(",",$to);
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $smtpTransportObj = new Zend_Mail_Transport_Smtp($conf->email->host, $conf->email->config->toArray());

        if ($fromMail == null || $fromMail == "") {
            $fromMail = $conf->email->config->username;
        }
        if ($fromName == null || $fromName == "") {
            $fromName = "ePT";
        }
        $originalMessage=html_entity_decode($message,ENT_QUOTES,'UTF-8');
        $systemMail = new Zend_Mail();
        
        $originalMessage= str_replace("&nbsp;","",strval($originalMessage));
        $originalMessage= str_replace("&amp;nbsp;","",strval($originalMessage));
        
        $systemMail->setSubject($subject);
        $systemMail->setBodyHtml(html_entity_decode($originalMessage, ENT_QUOTES, 'UTF-8'));

        $systemMail->setFrom($fromMail, $fromName);
        $systemMail->setReplyTo($fromMail, $fromName);

        if (is_array($to)) {
            foreach ($to as $name => $mail) {
                $systemMail->addTo($mail, $name);
            }
        } else {
            $systemMail->addTo($to);
        }
        if (isset($cc) && $cc != "" && $cc != null) {
            if (is_array($cc)) {
                foreach ($cc as $name => $mail) {
                    $systemMail->addCc($mail, $name);
                }
            } else {
                $systemMail->addCc($cc);
            }
        }
        if (isset($bcc) && $bcc != "" && $bcc != null) {
            if (is_array($bcc)) {
                foreach ($bcc as $name => $mail) {
                    $systemMail->addBcc($mail, $name);
                }
            } else {
                $systemMail->addBcc($bcc);
            }
        }

        try {
            $systemMail->send($smtpTransportObj);
            return true;
        } catch (Exception $exc) {
            error_log("===== MAIL SENDING FAILED - START =====");
            error_log($exc->getMessage());
            error_log($exc->getTraceAsString());
            error_log("===== MAIL SENDING FAILED - END =====");
			return false;
        }
    }
	
	public static function getRandomString($length = 8) {
		$alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789!#$%^&{}()";
		$randStr = "";
		$alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
		for ($i = 0; $i < $length; $i++) {
			$n = rand(0, $alphaLength);
			$randStr .= $alphabet[$n];
		}
		return $randStr; //turn the array into a string
    }	
	public static function getConfig($name) {
		$gc = new Application_Model_DbTable_GlobalConfig();
		return $gc->getValue($name);
    }		
	public function contactForm($params) {
		$message = "<h3>The following details were entered by ".$params['name']."</h3>";
		$message .= "Name : ".$params['name']."<br/>";
		$message .= "Email : ".$params['email']."<br/>";
		$message .= "Phone/Mobile : ".$params['phone']."<br/>";
		$message .= "Lab/Agency : ".$params['agency']."<br/>";
		$message .= "Additional Info : ".$params['additionalInfo']."<br/>";
		
		$fromEmail = $params['email'];
		$fromName = $params['name'];
		
		$to = Application_Service_Common::getConfig('admin-email');
		
		$mailSent = $this->sendMail($to,null,null,"New enquiry for ePT",$message,$fromEmail,$fromName);
		if($mailSent){
			return "Thank you for showing interest in this Program. We will contact you shortly";
		}else{
			return "Sorry, unable to send your message now. Please try again later;";
		}
		
    }
    public function checkDuplicate($params) {
        $session = new Zend_Session_Namespace('credo');
        $tableName = $params['tableName'];
        $fieldName = $params['fieldName'];
        $value = trim($params['value']);
        $fnct = $params['fnct'];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($fnct == '' || $fnct == 'null') {
            $sql = $db->select()->from($tableName)->where($fieldName . "=" . "'$value'");
            $result = $db->fetchAll($sql);
            $data = count($result);
            
        } else {
            $table = explode("##", $fnct);
            // first trying $table[1] without quotes. If this does not work, then in catch we try with single quotes
            try {
                
				$sql = $db->select()->from($tableName)->where($fieldName . "=" . "'$value'")->where($table[0] . "!=" . $table[1]);
				$result = $db->fetchAll($sql);
				$data = count($result);
                
            } catch (Exception $e) {
                $sql = $db->select()->from($tableName)->where($fieldName . "=" . "'$value'")->where($table[0] . "!='" . $table[1] . "'");
                $result = $db->fetchAll($sql);
                $data = count($result);
            }
        }
        return $data;
    }	

}

