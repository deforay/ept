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
		$alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
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
		$message = "<h3>The following details were entered by ".$params['first_name']." " .$params['last_name']."</h3>";
		$message .= "Name : ".$params['first_name']." " .$params['last_name']."<br/>";
		$message .= "Email : ".$params['email']."<br/>";
		$message .= "Phone/Mobile : ".$params['phone']."<br/>";
		$message .= "Selected Reason to Contact : ".$params['reason']."<br/>";
		$message .= "Lab/Agency : ".$params['agency']."<br/>";
		$message .= "Additional Info : ".$params['additionalInfo']."<br/>";
		
		$db = new Application_Model_DbTable_ContactUs();
		
		$data = array('first_name'=>$params['first_name'],'last_name'=>$params['last_name'],'email'=>$params['email'],'phone'=>$params['phone'],'reason'=>$params['reason'],'lab'=>$params['agency'],'additional_info'=>$params['additionalInfo'], 'contacted_on' => new Zend_Db_Expr('now()'),'ip_address'=>$_SERVER['REMOTE_ADDR']);
		$db->addContact($data);
		
		$fromEmail = $params['email'];
		$fromName  = $params['first_name']." " .$params['last_name'];
		
		$to = Application_Service_Common::getConfig('admin_email');
		
		$mailSent = $this->sendMail($to,null,null,"New contact message from the ePT program",$message,$fromEmail,$fromName);
		if($mailSent){
			return 1;
		}else{
			return 0;
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
    public function removespecials($url){
        $url=str_replace(" ","-",$url);
        
        $url = preg_replace('/[^a-zA-Z0-9\-]/', '', $url);
        $url = preg_replace('/^[\-]+/', '', $url);
        $url = preg_replace('/[\-]+$/', '', $url);
        $url = preg_replace('/[\-]{2,}/', '', $url);
        
        return strtolower($url);
	
    }
    
    public function getCountriesList(){
	$countriesDb = new Application_Model_DbTable_Countries();
	return $countriesDb->getAllCountries();
    }
    public function getAllnetwork(){
	$networkDb = new Application_Model_DbTable_NetworkTires();
	return $networkDb->getAllnetwork();
    }
    public function getAllParticipantAffiliates(){
	$participantAffiliateDb = new Application_Model_DbTable_ParticipantAffiliates();
	return $participantAffiliateDb->getAllParticipantAffiliates();
    }
    public function getGlobalConfigDetails() {
	$db = new Application_Model_DbTable_GlobalConfig();
	return $db->getGlobalConfig();
    }
    public function getFullSchemesDetails() {
	$db = new Application_Model_DbTable_SchemeList();
	return $db->getFullSchemeList();
    }
    
    public function updateConfig($params) {	
	$db = new Application_Model_DbTable_GlobalConfig();
	$db->updateConfigDetails($params);
    }
    public function getEmailTemplate($purpose){
        $db = new Application_Model_DbTable_MailTemplate();
        return $db->getEmailTemplateDetails($purpose);
    }
    public function updateTemplate($params){
        $filterRules = array(
                    '*' => 'StripTags',
                    '*' => 'StringTrim'
                );

        $filter = new Zend_Filter_Input($filterRules, null, $params);

        if ($filter->isValid()) {

            $params = $filter->getEscaped();
            $db= new Application_Model_DbTable_MailTemplate();
            $db->getAdapter()->beginTransaction();

            try {
                $result=$db->updateMailTemplateDetails($params);
                $db->getAdapter()->commit();
            } catch (Exception $exc) {
                $db->getAdapter()->rollBack();
                error_log($exc->getMessage());
                error_log($exc->getTraceAsString());
            }
        }        
    }
    public function insertTempMail($to, $cc,$bcc, $subject, $message, $fromMail = null, $fromName = null) {
        $db = new Application_Model_DbTable_TempMail();
        return $db->insertTempMailDetails($to, $cc,$bcc, $subject, $message, $fromMail, $fromName);
    }
	
    public function getAllModeOfReceipt(){
	$db = new Application_Model_DbTable_ModeOfReceipt();
	return $db->fetchAllModeOfReceipt();
    }
    
    public function updateHomeBanner($params){
	$filterRules = array(
                    '*' => 'StripTags',
                    '*' => 'StringTrim'
                );

        $filter = new Zend_Filter_Input($filterRules, null, $params);

        if ($filter->isValid()) {

            $params = $filter->getEscaped();
            $db= new Application_Model_DbTable_HomeBanner();
            $db->getAdapter()->beginTransaction();

            try {
                $result=$db->updateHomeBannerDetails($params);
                $db->getAdapter()->commit();
            } catch (Exception $exc) {
                $db->getAdapter()->rollBack();
                error_log($exc->getMessage());
                error_log($exc->getTraceAsString());
            }
        }
    }
    
    public function getHomeBannerDetails(){
	$db= new Application_Model_DbTable_HomeBanner();
	return $db->fetchHomeBannerDetails();
    }
    
    public function getHomeBanner(){
	$db= new Application_Model_DbTable_HomeBanner();
	return $db->fetchHomeBanner();
    }
}

