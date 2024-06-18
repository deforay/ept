<?php

use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Hackzilla\PasswordGenerator\Generator\RequirementPasswordGenerator;

class Application_Service_Common
{

    public static function isDateValid($date): bool
    {
        $date = trim($date);

        if (empty($date) || 'undefined' === $date || 'null' === $date) {
            $response = false;
        } else {
            try {
                $dateTime = new DateTimeImmutable($date);
                $errors = DateTimeImmutable::getLastErrors();
                if (
                    !empty($errors['warning_count'])
                    || !empty($errors['error_count'])
                ) {
                    $response = false;
                } else {
                    $response = true;
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
                $response = false;
            }
        }

        return $response;
    }

    public static function isoDateFormat($date, $includeTime = false)
    {

        if (false === self::isDateValid($date)) {
            return null;
        } else {
            $format = "Y-m-d";
            if ($includeTime === true) {
                $format = $format . " H:i:s";
            }
            return (new DateTimeImmutable($date))->format($format);
        }
    }

    // Returns the given date in d-M-Y format
    // (with or without time depending on the $includeTime parameter)
    public static function humanReadableDateFormat($date, $includeTime = false, $format = "d-M-Y")
    {
        if (false === self::isDateValid($date)) {
            return null;
        } else {

            if ($includeTime === true) {
                $format = $format . " H:i";
            }

            return (new DateTimeImmutable($date))->format($format);
        }
    }

    public function getDateTime($returnFormat = 'Y-m-d H:i:s')
    {
        $date = new \DateTime(date('Y-m-d H:i:s'));
        return $date->format($returnFormat);
    }
    public static function generateRandomString($length = 8): string
    {
        $bytes = ceil($length * 3 / 4);
        try {
            $randomBytes = random_bytes($bytes);
            $base64String = base64_encode($randomBytes);
            // Replace base64 characters with some alphanumeric characters
            $customBase64String = strtr($base64String, '+/=', 'ABC');
            return substr($customBase64String, 0, $length);
        } catch (Throwable $e) {
            throw new Exception('Failed to generate random string: ' . $e->getMessage());
        }
    }

    public static function generateRandomNumber(int $length = 8): string
    {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= random_int(0, 9);
        }
        return $result;
    }

    private function sanitizeInput($input)
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $input));
    }
    public function generateFakeEmailId($uniqueId, $participantName)
    {
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $eptDomain = !empty($conf->domain) ? rtrim($conf->domain, "/") : 'ept';
        $sanitizedUniqueId = $this->sanitizeInput($uniqueId);
        $sanitizedParticipantName = $this->sanitizeInput($participantName);
        $host = parse_url($eptDomain, PHP_URL_HOST) ?: 'ept';
        return $sanitizedUniqueId . "_" . $sanitizedParticipantName . "@" . $host;
    }

    public function sendMail($to, $cc, $bcc, $subject, $message, $fromMail = null, $fromName = null, $attachments = array())
    {
        //Send to email
        $to = explode(",", $to);
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $smtpTransportObj = new Zend_Mail_Transport_Smtp($conf->email->host, $conf->email->config->toArray());

        $fromMail = $conf->email->config->username;

        if ($fromName == null || $fromName == "") {
            $fromName = "ePT System";
        }
        $originalMessage = html_entity_decode($message, ENT_QUOTES, 'UTF-8');
        $systemMail = new Zend_Mail();

        $originalMessage = str_replace("&nbsp;", "", strval($originalMessage));
        $originalMessage = str_replace("&amp;nbsp;", "", strval($originalMessage));

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
                    $systemMail->addBcc($mail);
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

    // Returns current date time in Y-m-d H:i:s format or any specified format
    public static function getCurrentDateTime($format = 'Y-m-d H:i:s')
    {
        return (new DateTimeImmutable())->format($format);
    }

    public function sendAlert($params)
    {
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $headers = array(
            'Content-Type:application/json',
            'Authorization:key=' . $conf->fcm->serverkey
        );

        $json_data = array(
            "to"            =>  'ckXAefQQhug:APA91bGFIBUN1qgn4-z0zusKnLeHZ2Lo6f8MkAC20wR7AooYu1txAo3NGGMwK4FoUdxdc2aa6Qt70aZ_ZR8Z85fMcIlAphFPzUmkUhrtWC9WkhmUfnu7at6eEaKsZWMx0DPpIjJgFiGK',
            "notification"  =>  array(
                "body"  =>  "e-PT reports from Thanaseelan",
                "title" =>  "e-PT Reports",
                "icon"  =>  "ic_launcher"
            ),
            /* "data"          =>  array(
                "message"   => "Your reports was ready please see the result."
            ) */
        );

        $data = json_encode($json_data);
        /* Message to be send */
        $url = (isset($conf->fcm->url) && trim($conf->fcm->url) != '') ? $conf->fcm->url : 'https://fcm.googleapis.com/fcm/send';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        if ($result === false) {
            die('Oops! FCM Send Error: ' . curl_error($ch));
        } else {
            echo "<pre>";
            print_r($result);
        }
        curl_close($ch);
    }

    public static function getRandomString($length = 8)
    {
        $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
        $randStr = "";
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < $length; $i++) {
            $n = rand(0, $alphaLength);
            $randStr .= $alphabet[$n];
        }
        return $randStr; //turn the array into a string
    }
    public static function getConfig($name)
    {
        $gc = new Application_Model_DbTable_GlobalConfig();
        return $gc->getValue($name);
    }
    public function contactForm($params)
    {
        $name = (isset($params['firstName']) && !empty($params['firstName']))? true : false;
        $id = (isset($params['participantId']) && !empty($params['participantId']))? true : false;
        $subject = (isset($params['subject']) && !empty($params['subject']))? true : false;
        if($name && $id && $subject){

            $message = "<h3>The following details were entered by " . $params['participantId'] . "</h3>";
            $message .= "Name : " . $params['firstName'] . " " . $params['lastName'] . "<br/>";
            $message .= "ID : " . $params['participantId'] . "<br/>";
            $message .= "Email : " . $params['email'] . "<br/>";
            $message .= "Subject : " . $params['subject'] . "<br/>";
            $message .= "Country Name : " . $params['country'] . "<br/>";
            $message .= "Message : " . $params['message'] . "<br/>";
    
            $db = new Application_Model_DbTable_ContactUs();
    
            $data = [
                'first_name' => $params['firstName'],
                'last_name' => $params['lastName'],
                'email' => $params['email'],
                'country' => $params['country'],
                'subject' => $params['subject'],
                'message' => $params['message'],
                'participant_id' => $params['participantId'],
                'contacted_on' => new Zend_Db_Expr('now()'),
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ];
            $db->addContact($data);
    
            $fromEmail = Application_Service_Common::getConfig('admin_email');
            $fromName  = "Online PT Team";
    
            $toArray[] = Application_Service_Common::getConfig('admin_email');
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1) {
                $toArray[] = $authNameSpace->email;
            }
    
            $mailSent = $this->insertTempMail(implode(",", $toArray), null, null, $params['subject'], $message, $fromEmail, $fromName);
            if ($mailSent) {
                return 1;
            } else {
                return 0;
            }
        }else {
            return 0;
        }
    }
    public function checkDuplicate($params): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $tableName = trim(($params['tableName']), "'");
        $fieldName = trim(($params['fieldName']), "'");
        $value = trim(trim($params['value']), "'");
        $fnct = $params['fnct'];

        $data = 0;
        // no point in checking duplication if the value is null or empty
        if (empty($value) || empty($tableName) || empty($fieldName)) {
            $data = 0;
        } elseif ($fnct == 'null' || empty($fnct) || $fnct == 'undefined' || $fnct == '') {
            $sql = $db->select()->from($tableName)->where("$fieldName = ?", $value);
            $result = $db->fetchAll($sql);
            if (!empty($result)) {
                $data = count($result);
            }
        } else {
            $table = explode("##", $fnct);
            $sql = $db->select()->from($tableName)
                ->where("$fieldName = ?", $value)
                ->where($table[0] . "!= '" . $table[1] . "'");
            $result = $db->fetchAll($sql);
            if (!empty($result)) {
                $data = count($result);
            }
        }
        return (int) $data;
    }

    public function getAllCountries($search)
    {
        $countriesDb = new Application_Model_DbTable_Countries();
        return $countriesDb->fetchAllCountries($search);
    }

    public function getCountriesList()
    {
        $countriesDb = new Application_Model_DbTable_Countries();
        return $countriesDb->getAllCountries();
    }
    public function getParticipantsProvinceList($cid = null, $list = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql =  $db->select()->distinct()->from(array('p' => 'participant'), array("state"))->group(array("state"))->order(array("state"));
        if (isset($cid) && !empty($cid)) {
            $sql = $sql->where("p.country IN (?)", $cid);
        }
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sql = $sql->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        $result = $db->fetchAll($sql);
        if (isset($list) && !empty($list) && $list == 'list') {
            $response = [];
            foreach ($result as $key => $value) {
                if (isset($value['state']) && !empty($value['state'])) {
                    $response[] = $value['state'];
                }
            }
            return $response;
        }
        return $result;
    }
    public function getParticipantsDistrictList($sid = null, $list = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql =  $db->select()->distinct()->from('participant', array("district"))->group(array("district"))->order(array("district"));
        if (isset($sid) && !empty($sid)) {
            $sql = $sql->where("state IN (?)", $sid);
        }
        $result = $db->fetchAll($sql);
        if (isset($list) && !empty($list) && $list == 'list') {
            $response = [];
            foreach ($result as $key => $value) {
                if (isset($value['district']) && !empty($value['district'])) {
                    $response[] = $value['district'];
                }
            }
            return $response;
        }
        return $result;
    }
    public function getAllnetwork()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('r_network_tiers'));
    }
    public function getAllParticipantAffiliates()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('r_participant_affiliates'));
    }

    public function getAllInstitutes($pid = null, $did = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql =  $db->select()->distinct()->from('participant', array("institute_name"))->group(array("institute_name"))->order(array("institute_name"));
        if (isset($pid) && !empty($pid)) {
            $sql = $sql->where("state like ?", $pid);
        }
        if (isset($did) && !empty($did)) {
            $sql = $sql->where("district like ?", $did);
        }
        // die($sql);
        return $db->fetchAll($sql);
    }
    public function getGlobalConfigDetails()
    {
        $db = new Application_Model_DbTable_GlobalConfig();
        return $db->getGlobalConfig();
    }
    public function getFullSchemesDetails()
    {
        $db = new Application_Model_DbTable_SchemeList();
        return $db->getFullSchemeList();
    }

    public function updateConfig($params)
    {
        $db = new Application_Model_DbTable_GlobalConfig();
        $db->updateConfigDetails($params);
    }
    public function getEmailTemplate($purpose)
    {
        $db = new Application_Model_DbTable_MailTemplate();
        return $db->getEmailTemplateDetails($purpose);
    }
    public function getAllEmailTemplateDetails()
    {
        $db = new Application_Model_DbTable_MailTemplate();
        return $db->fetchAllEmailTemplateDetails();
    }
    public function updateTemplate($params)
    {
        $filterRules = array(
            '*' => 'StripTags',
            '*' => 'StringTrim'
        );

        $filter = new Zend_Filter_Input($filterRules, null, $params);

        if ($filter->isValid()) {

            $params = $filter->getEscaped();
            $db = new Application_Model_DbTable_MailTemplate();
            $db->getAdapter()->beginTransaction();

            try {
                $db->updateMailTemplateDetails($params);
                $db->getAdapter()->commit();
            } catch (Exception $exc) {
                $db->getAdapter()->rollBack();
                error_log($exc->getMessage());
                error_log($exc->getTraceAsString());
            }
        }
    }
    public function insertTempMail($to, $cc, $bcc, $subject, $message, $fromMail = null, $fromName = null)
    {
        $db = new Application_Model_DbTable_TempMail();
        return $db->insertTempMailDetails($to, $cc, $bcc, $subject, $message, $fromMail, $fromName);
    }

    public function getAllModeOfReceipt()
    {
        $db = new Application_Model_DbTable_ModeOfReceipt();
        return $db->fetchAllModeOfReceipt();
    }

    public function updateHomeBanner($params)
    {
        $filterRules = array(
            '*' => 'StripTags',
            '*' => 'StringTrim'
        );

        $filter = new Zend_Filter_Input($filterRules, null, $params);

        if ($filter->isValid()) {

            $params = $filter->getEscaped();
            $db = new Application_Model_DbTable_HomeBanner();
            $db->getAdapter()->beginTransaction();

            try {
                $result = $db->updateHomeBannerDetails($params);
                $db->getAdapter()->commit();
            } catch (Exception $exc) {
                $db->getAdapter()->rollBack();
                error_log($exc->getMessage());
                error_log($exc->getTraceAsString());
            }
        }
    }

    public function getHomeBannerDetails()
    {
        $db = new Application_Model_DbTable_HomeBanner();
        return $db->fetchHomeBannerDetails();
    }

    public function getHomeBanner()
    {
        $db = new Application_Model_DbTable_HomeBanner();
        return $db->fetchHomeBanner();
    }

    public function sendTempMail()
    {
        $tempMailDb = new Application_Model_DbTable_TempMail();
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

        // Setup SMTP transport using LOGIN authentication
        $smtpTransportObj = new Zend_Mail_Transport_Smtp($conf->email->host, $conf->email->config->toArray());

        $limit = '10';
        $sQuery = $tempMailDb->getAdapter()->select()->from(array('tm' => 'temp_mail'))
            ->where("status='pending'")->limit($limit);
        $mailResult = $tempMailDb->getAdapter()->fetchAll($sQuery);
        if (count($mailResult) > 0) {
            foreach ($mailResult as $result) {
                $id = $result['temp_id'];
                $tempMailDb->updateTempMailStatus($id);

                $fromEmail = $result['from_mail'];
                $fromFullName = $result['from_full_name'];
                $subject = $result['subject'];

                $originalMessage = html_entity_decode($result['message'], ENT_QUOTES, 'UTF-8');
                $systemMail = new Zend_Mail();

                $originalMessage = str_replace("&nbsp;", "", strval($originalMessage));
                $originalMessage = str_replace("&amp;nbsp;", "", strval($originalMessage));

                $systemMail->setSubject($subject);
                $systemMail->setBodyHtml(html_entity_decode($originalMessage, ENT_QUOTES, 'UTF-8'));

                $systemMail->setFrom($fromEmail, $fromFullName);
                $systemMail->setReplyTo($fromEmail, $fromFullName);

                $to = explode(",", $result['to_email']);

                if (isset($result['cc']) && trim($result['cc']) != "") {
                    if (is_array($result['cc'])) {
                        foreach ($result['cc'] as $name => $mail) {
                            $systemMail->addCc($mail, $name);
                        }
                    } else {
                        $systemMail->addCc($result['cc']);
                    }
                }

                if (isset($result['bcc']) && trim($result['bcc']) != "") {
                    if (is_array($result['bcc'])) {
                        foreach ($result['bcc'] as $name => $mail) {
                            $systemMail->addBcc($mail);
                        }
                    } else {
                        $systemMail->addBcc($result['bcc']);
                    }
                }

                if (is_array($to)) {
                    foreach ($to as $name => $mail) {
                        $systemMail->addTo($mail, $name);
                    }
                } else {
                    $systemMail->addTo($to);
                }

                try {
                    $systemMail->send($smtpTransportObj);
                    $tempMailDb->deleteTempMail($id);

                    return true;
                } catch (Exception $exc) {
                    error_log("===== MAIL SENDING FAILED - START =====");
                    error_log($exc->getMessage());
                    error_log($exc->getTraceAsString());
                    error_log("===== MAIL SENDING FAILED - END =====");
                    return false;
                }
            }
        }
    }

    public function fetchNotify()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('notify')->order('created_on DESC'));
    }

    public function saveNotifyStatus($id)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($id == "all") {
            return $db->update('notify', array("status" => 'read'), "status = 'unread'");
        }
        return $db->update('notify', array("status" => 'read'), "id = " . $id);
    }

    public function getAllPushNotify($params)
    {
        $db = new Application_Model_DbTable_PushNotification();
        return $db->fetchAllPushNotify($params);
    }

    public function approve($params)
    {
        $db = new Application_Model_DbTable_PushNotification();
        return $db->approveNotify($params);
    }

    public function getPushNotificationDetailsById($id)
    {
        $db = new Application_Model_DbTable_PushNotification();
        return $db->fetchPushNotificationDetailsById($id);
    }

    public function insertPushNotification($title, $msgBody, $dataMsg, $icon, $shipmentId, $identifyType, $notificationType, $announcementId = '')
    {
        $db = new Application_Model_DbTable_PushNotification();
        return $db->insertPushNotificationDetails($title, $msgBody, $dataMsg, $icon, $shipmentId, $identifyType, $notificationType, $announcementId);
    }

    public function fetchUnReadPushNotify()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $count = $db->fetchAll($db->select()->from('push_notification')->where('push_status ="refuse"'));
        return count($count);
    }

    public function updatePushTemplate($params)
    {
        $db = new Application_Model_DbTable_PushNotificationTemplate();
        return $db->updatePushTemplateDetails($params);
    }

    public function getPushTemplateByPurpose($purpose)
    {
        $db = new Application_Model_DbTable_PushNotificationTemplate();
        return $db->fetchPushTemplateByPurpose($purpose);
    }

    public function getNotificationByAPI($params)
    {
        $db = new Application_Model_DbTable_PushNotification();
        return $db->fetchNotificationByAPI($params);
    }

    public function generateSelectOptions($optionList, $selectedOptions = array(), $emptySelectText = false)
    {

        if (empty($optionList)) {
            return '';
        }
        $response = '';
        if ($emptySelectText !== false) {
            $response .= '<option value="">' . $emptySelectText . '</option>';
        }

        foreach ($optionList as $optId => $optName) {
            $selectedText = '';
            if (!empty($selectedOptions)) {
                if (is_array($selectedOptions) && in_array($optId, $selectedOptions)) {
                    $selectedText = 'selected="selected"';
                } else if ($optId == $selectedOptions) {
                    $selectedText = 'selected="selected"';
                }
            }
            $response .= '<option value="' . addslashes($optId) . '" ' . $selectedText . '>' . addslashes($optName) . '</option>';
        }
        return $response;
    }

    public function getIPAddress()
    {
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if (getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if (getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if (getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if (getenv('HTTP_FORWARDED'))
            $ipaddress = getenv('HTTP_FORWARDED');
        else if (getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }

    public function getOperatingSystem($userAgent = null)
    {
        $osPlatform = "Unknown OS - " . $userAgent;

        $osArray =  array(
            '/windows nt 6.3/i'     =>  'Windows 8.1',
            '/windows nt 6.2/i'     =>  'Windows 8',
            '/windows nt 6.1/i'     =>  'Windows 7',
            '/windows nt 6.0/i'     =>  'Windows Vista',
            '/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
            '/windows nt 5.1/i'     =>  'Windows XP',
            '/windows xp/i'         =>  'Windows XP',
            '/windows nt 5.0/i'     =>  'Windows 2000',
            '/windows me/i'         =>  'Windows ME',
            '/win98/i'              =>  'Windows 98',
            '/win95/i'              =>  'Windows 95',
            '/win16/i'              =>  'Windows 3.11',
            '/macintosh|mac os x/i' =>  'Mac OS X',
            '/mac_powerpc/i'        =>  'Mac OS 9',
            '/linux/i'              =>  'Linux',
            '/ubuntu/i'             =>  'Ubuntu',
            '/iphone/i'             =>  'iPhone',
            '/ipod/i'               =>  'iPod',
            '/ipad/i'               =>  'iPad',
            '/android/i'            =>  'Android',
            '/blackberry/i'         =>  'BlackBerry',
            '/webos/i'              =>  'Mobile'
        );

        foreach ($osArray as $regex => $value) {
            if (preg_match($regex, $userAgent)) {
                $osPlatform    =   $value;
            }
        }
        return $osPlatform;
    }

    public function getBrowser($userAgent = null)
    {

        $browser        =   "Unknown Browser - " . $userAgent;
        $browserArray  =   array(
            '/msie/i'       =>  'Internet Explorer',
            '/firefox/i'    =>  'Firefox',
            '/safari/i'     =>  'Safari',
            '/chrome/i'     =>  'Chrome',
            '/opera/i'      =>  'Opera',
            '/netscape/i'   =>  'Netscape',
            '/maxthon/i'    =>  'Maxthon',
            '/konqueror/i'  =>  'Konqueror',
            '/mobile/i'     =>  'Handheld Browser'
        );

        foreach ($browserArray as $regex => $value) {

            if (preg_match($regex, $userAgent)) {
                $browser    =   $value;
            }
        }

        return $browser;
    }

    public function getAllAuditLogDetailsByGrid($params)
    {
        $auditLogDb = new Application_Model_DbTable_AuditLog();
        return $auditLogDb->fetchAllAuditLogDetailsByGrid($params);
    }

    public function insertMultiple($table, array $data, $addIgnore = false)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Ensure there is data to insert
        if (empty($data)) {
            throw new Exception("No data provided for insertion");
        }

        // Get a list of columns from the first row of data
        $cols = array_keys(reset($data));

        // Quote the column names
        $quotedCols = array_map(function ($col) use ($db) {
            return $db->quoteIdentifier($col);
        }, $cols);

        // Start building the SQL statement
        $ignoreString = $addIgnore ? ' IGNORE ' : '';
        $sql = "INSERT" . $ignoreString . " INTO " . $db->quoteIdentifier($table) . " (" . implode(", ", $quotedCols) . ") VALUES ";

        // Build the VALUES part of the SQL statement
        $valuesList = [];
        foreach ($data as $row) {
            $quotedValues = array_map(function ($value) use ($db) {
                return $db->quote($value); // Assumes $db->quote() properly quotes strings; adjust as needed
            }, $row);
            $valuesList[] = "(" . implode(", ", $quotedValues) . ")";
        }
        $sql .= implode(", ", $valuesList);

        // Execute the query
        return $db->query($sql);
    }


    public function insertIgnore($table, array $data)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Quote the table name
        $quotedTable = $db->quoteIdentifier($table);

        // Quote and prepare columns and values
        $columns = array_map(function ($col) use ($db) {
            return $db->quoteIdentifier($col);
        }, array_keys($data));

        $values = array_map(function ($value) use ($db) {
            return $db->quote($value);
        }, array_values($data));

        // Construct the SQL statement
        $sql = sprintf(
            "INSERT IGNORE INTO %s (%s) VALUES (%s)",
            $quotedTable,
            implode(', ', $columns),
            implode(', ', $values)
        );

        error_log($sql);

        try {
            return $db->query($sql);
        } catch (Zend_Db_Adapter_Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }



    public function getOptionsByValue($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array($params['table']), array($params['returnfield']))
            ->where($params['returnfield'] . " IS NOT NULL")
            ->where($params['returnfield'] . " not like ''")
            ->where($params['searchfield'] . " IS NOT NULL")
            ->where($params['searchfield'] . " not like ''")
            ->where($params['searchfield'] . " like '" . $params['searchvalue'] . "'")
            ->group($params['returnfield'])
            ->order($params['returnfield']);
        return $db->fetchAll($sql);
    }

    public function generatePassword()
    {

        $generator = new RequirementPasswordGenerator();
        $generator
            ->setLength(12)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_LOWER_CASE, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_NUMBERS, true)
            ->setOptionValue(RequirementPasswordGenerator::OPTION_SYMBOLS, false)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_UPPER_CASE, 2)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_LOWER_CASE, 2)
            ->setMinimumCount(RequirementPasswordGenerator::OPTION_NUMBERS, 2);

        $password = $generator->generatePassword();
        echo $password;
    }

    public function checkAssayInvalid($sid = null, $pid = null, $status = false)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('r' => 'r_vl_assay'))->where('r.allow_invalid = "yes"');
        if ($status) {
            $sql = $sql->join(array('rvl' => 'response_result_vl'), 'r.id=rvl.vl_assay', array('shipment_map_id'));
            $sql = $sql->join(array('spm' => 'shipment_participant_map'), 'rvl.shipment_map_id=spm.map_id', array('shipment_id', 'participant_id'));
            $sql = $sql->where('spm.shipment_id = ' . $sid . ' AND spm.participant_id = ' . $pid);
            $sql = $sql->group('rvl.shipment_map_id');
        }
        return $db->fetchOne($sql);
    }
    // For accessing location details based on dm id
    public function ptccLocationMapByDmid($dmId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('pcm' => 'ptcc_countries_map'))->where('ptcc_id = ?', $dmId)->group('district, state, country_id');
        $result = $db->fetchAll($sql);
        $locations = array();
        if (isset($result) && !empty($result)) {
            foreach ($result as $row) {
                if (isset($row['district']) && !empty($row['district']) && $row['district'] != '' && !in_array($row['district'], $locations['district'])) {
                    $locations['district'][] = $row['district'];
                }
                if (isset($row['state']) && !empty($row['state']) && $row['state'] != '' && !in_array($row['state'], $locations['state'])) {
                    $locations['state'][] = $row['state'];
                }
                if (isset($row['country_id']) && !empty($row['country_id']) && $row['country_id'] != '' && !in_array($row['country_id'], $locations['countries'])) {
                    $locations['countries'][] = $row['country_id'];
                }
            }
        }
        return $locations;
    }

    public function applyBordersToSheet(Worksheet $sheet)
    {
        // Retrieve the highest row and highest column
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        // Define border style
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];

        // Calculate the range that covers all your data
        $range = "A1:{$highestColumn}{$highestRow}";

        // Apply border style to the range
        $sheet->getStyle($range)->applyFromArray($borderStyle);

        return $sheet;
    }

    public function setAllColumnWidthsInSheet(Worksheet $sheet, $width = 20)
    {
        // Set the default width for all columns in the sheet
        $sheet->getDefaultColumnDimension()->setWidth($width);

        return $sheet;
    }

    public function centerAndBoldRowInSheet(Worksheet $sheet, $startCell = 'A1')
    {
        // Extract the row number from the start cell
        $startRow = preg_replace('/[^0-9]/', '', $startCell);

        // Retrieve the highest column
        $highestColumn = $sheet->getHighestColumn();

        // Calculate the range for the entire row
        $range = $startCell . ':' . $highestColumn . $startRow;

        // Set alignment to center and font to bold for the range
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($range)->getFont()->setBold(true);

        return $sheet;
    }

    public function centerColumnsInSheet(Worksheet $sheet, ...$columns)
    {
        foreach ($columns as $column) {
            // Retrieve the highest row number for the current column
            $highestRow = $sheet->getHighestRow($column);

            // Define the range for the entire column
            $range = $column . '1:' . $column . $highestRow;

            // Apply center alignment to the range
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    public static function dumpToErrorLog($object = null, $useVarDump = true): void
    {
        ob_start();
        if ($useVarDump) {
            var_dump($object);
            $output = ob_get_clean();
            // Remove newline characters
            $output = str_replace("\n", "", $output);
        } else {
            print_r($object);
            $output = ob_get_clean();
        }

        // Additional context
        $timestamp = date('Y-m-d H:i:s');
        $output = "[{$timestamp}] " . $output;

        error_log($output);
    }

    function getAllTestKitBySearch($text)
    {
        $db = new Application_Model_DbTable_TestkitnameDts();
        $sql = $db->select()->from(array('r_testkitname_dts'), array('TESTKITNAMEID' => 'TESTKITNAME_ID', 'TESTKITNAME' => 'TESTKIT_NAME'))->where("TESTKIT_NAME LIKE '%" . $text . "%'");
        $cResult = $db->fetchAll($sql);
        $echoResult = [];
        if (count($cResult) > 0) {
            foreach ($cResult as $row) {
                $echoResult[] = array("id" => $row['TESTKITNAMEID'], "text" => ucwords((string) $row['TESTKITNAME']));
            }
        } else {
            $echoResult[] = array("id" => $text, 'text' => ucwords((string) $text));
        }

        return array("result" => $echoResult);
    }

    function getMappedTestKits($pid, $sid = "")
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from('participant_testkit_map', array('testkit_id'))
            ->where("participant_id = ?", $pid)
            ->group('testkit_id');
        if (isset($sid) && !empty($sid)) {
            $sql = $sql->where("shipment_id = ?", $sid);
        }
        // die($sql);
        return $db->fetchCol($sql);
    }

    public function getFeedBackQuestions($shipmentId, $headings)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()->from(['pff' => 'r_participant_feedback_form'], ['pff.question_id'])
            ->join(['fq' => 'r_feedback_questions'], "fq.question_id = pff.question_id", ['question_text'])
            ->where("pff.shipment_id = ?", $shipmentId);
        $result = $db->fetchAll($query);
        $questionId = [];
        foreach ($result as $res) {
            $questionId[$res['question_id']] = $res['question_id'];
            array_push($headings, $res['question_text']);
        }
        return ["heading" => $headings, "question" => $questionId];
    }

    /**
     * Validate the required fields in a form submission
     * @param array $formData
     * @param array $requiredFields
     * @return bool
     */
    public function validateRequiredFields(array $formData, array $requiredFields): bool
    {
        if (empty($formData)) {
            return false;  // No form data to validate
        }

        if (empty($requiredFields)) {
            return true;  // No required fields specified
        }

        foreach ($requiredFields as $field) {
            if (preg_match('/([^\[]+)\[([^\]]*)\]/', $field, $matches)) {
                $baseFieldName = $matches[1];
                $index = $matches[2];

                // Check that the field is set and is an array if no specific index is provided
                if ($index === '' && (!isset($formData[$baseFieldName]) || !is_array($formData[$baseFieldName]))) {
                    return false;  // Field not set or not an array as expected
                }

                if ($index === '') {
                    // Check all indices under the base field name
                    foreach ($formData[$baseFieldName] as $value) {
                        if ($value === '' || $value === null) {
                            return false; // An entry in the array is empty
                        }
                    }
                } else {
                    // Handling specific indexed arrays
                    if (!isset($formData[$baseFieldName][$index]) || $formData[$baseFieldName][$index] === '') {
                        return false; // Specific indexed field is empty
                    }
                }
            } else {
                // Regular field, not an array
                if (!isset($formData[$field]) || $formData[$field] === '') {
                    return false; // The field is empty or not set
                }
            }
        }

        return true;  // All required fields are correctly filled
    }

    public static function removeEmpty($array)
    {
        if (is_array($array) && !empty($array)) {
            return array_filter($array, function ($value) {
                return $value !== null && $value !== "";
            });
        } else {
            return $array;
        }
    }

    public static function validateEmails(string $emails): array
    {
        // Split the input string into individual emails using comma or semicolon
        $emailArray = preg_split('/[;,]/', $emails);

        // Trim whitespace and validate each email
        $validEmails = [];
        $invalidEmails = [];

        foreach ($emailArray as $email) {
            $email = trim($email);

            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalidEmails[] = $email;
                continue;
            }

            // Get domain from email
            $domain = substr(strrchr($email, "@"), 1);

            // Check MX records
            if (!checkdnsrr($domain, 'MX')) {
                $invalidEmails[] = $email;
                continue;
            }

            $validEmails[] = $email;
        }

        return [
            'valid' => $validEmails,
            'invalid' => $invalidEmails,
        ];
    }
}
