<?php

use Hackzilla\PasswordGenerator\Generator\RequirementPasswordGenerator;

class Application_Service_Common
{

    public function dbDateFormat($date)
    {
        if (!isset($date) || $date == null || $date == "" || $date == "0000-00-00") {
            return "0000-00-00";
        } else {
            $dateArray = explode('-', $date);
            if (sizeof($dateArray) == 0) {
                return;
            }
            $newDate = $dateArray[2] . "-";

            $monthsArray = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
            $mon = 1;
            $mon += array_search(ucfirst($dateArray[1]), $monthsArray);

            if (strlen($mon) == 1) {
                $mon = "0" . $mon;
            }
            return $newDate .= $mon . "-" . $dateArray[0];
        }
    }

    public function humanDateTimeFormat($date)
    {
        if ($date == "0000-00-00 00:00:00") {
            return "";
        } else {
            $dateTimeArray = explode(' ', $date);
            $dateArray = explode('-', $dateTimeArray[0]);
            $newDate = $dateArray[2] . "-";
            $monthsArray = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
            $mon = $monthsArray[$dateArray[1] - 1];
            return $newDate .= $mon . "-" . $dateArray[0] . " " . $dateTimeArray[1];
        }
    }

    public function getDateTime($returnFormat = 'Y-m-d H:i:s')
    {
        $date = new \DateTime(date('Y-m-d H:i:s'));
        return $date->format($returnFormat);
    }
    public function generateRandomString($length = 8)
    {
        $random_string = '';
        for ($i = 0; $i < $length; $i++) {
            $number = random_int(0, 36);
            $character = base_convert($number, 10, 36);
            $random_string .= $character;
        }

        return $random_string;
    }
    public function generateFakeEmailId($uniqueId, $participantName)
    {
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $eptDomain = !empty($conf->domain) ? rtrim($conf->domain, "/") : 'ept';
        $uniqueId = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $uniqueId));
        $participantName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $participantName));

        $fakeEmail = $uniqueId . "_" . $participantName . "@" . parse_url($eptDomain, PHP_URL_HOST);
        return $fakeEmail;
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
        if ($result === FALSE) {
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
        $message = "<h3>The following details were entered by " . $params['participantId'] . "</h3>";
        $message .= "Name : " . $params['firstName'] . " " . $params['lastName'] . "<br/>";
        $message .= "ID : " . $params['participantId'] . "<br/>";
        $message .= "Email : " . $params['email'] . "<br/>";
        $message .= "Subject : " . $params['subject'] . "<br/>";
        $message .= "Country Name : " . $params['country'] . "<br/>";
        $message .= "Message : " . $params['message'] . "<br/>";

        $db = new Application_Model_DbTable_ContactUs();

        $data = array('first_name' => $params['firstName'], 'last_name' => $params['lastName'], 'email' => $params['email'], 'country' => $params['country'], 'subject' => $params['subject'], 'message' => $params['message'], 'participant_id' => $params['participantId'], 'contacted_on' => new Zend_Db_Expr('now()'), 'ip_address' => $_SERVER['REMOTE_ADDR']);
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
    }
    public function checkDuplicate($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $session = new Zend_Session_Namespace('credo');
        $tableName = trim(($params['tableName']), "'");
        $fieldName = trim(($params['fieldName']), "'");
        $value = trim(trim($params['value']), "'");
        $fnct = $params['fnct'];

        // no point in checking duplication if the value is null or empty
        if (empty($value) || empty($tableName) || empty($fieldName)) {
            return 0;
        }

        if ($fnct == 'null' || empty($fnct)) {
            $sql = $db->select()->from($tableName)->where("$fieldName = ?", $value);
            $result = $db->fetchAll($sql);
            $data = count($result);
        } else {
            $table = explode("##", $fnct);
            $sql = $db->select()->from($tableName)->where("$fieldName = ?", $value)->where($table[0] . "!=" . $table[1]);
            $result = $db->fetchAll($sql);
            $data = count($result);
        }
        return $data;
    }
    public function removespecials($url)
    {
        $url = str_replace(" ", "-", $url);

        $url = preg_replace('/[^a-zA-Z0-9\-]/', '', $url);
        $url = preg_replace('/^[\-]+/', '', $url);
        $url = preg_replace('/[\-]+$/', '', $url);
        $url = preg_replace('/[\-]{2,}/', '', $url);

        return strtolower($url);
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
    public function getParticipantsProvinceList($cid = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql =  $db->select()->distinct()->from('participant')->columns(array("state"))->group(array("state"))->order(array("state"));
        if (isset($cid) && !empty($cid)) {
            $sql = $sql->where("country like ?", $cid);
        }
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1 && !empty($authNameSpace->ptccMappedCountries)) {
            $sql = $sql->where("country IN(" . $authNameSpace->ptccMappedCountries . ")");
        } else if (isset($authNameSpace->mappedParticipants) && !empty($authNameSpace->mappedParticipants)) {
            $sql = $sql->where("participant_id IN(" . $authNameSpace->mappedParticipants . ")");
        }
        return $db->fetchAll($sql);
    }
    public function getParticipantsDistrictList($pid = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql =  $db->select()->distinct()->from('participant')->columns(array("district"))->group(array("district"))->order(array("district"));
        if (isset($pid) && !empty($pid)) {
            $sql = $sql->where("state like ?", $pid);
        }
        return $db->fetchAll($sql);
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
        $sql =  $db->select()->distinct()->from('participant')->columns(array("institute_name"))->group(array("institute_name"))->order(array("institute_name"));
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
                $result = $db->updateMailTemplateDetails($params);
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
        $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);

        // Setup SMTP transport using LOGIN authentication
        $smtpTransportObj = new Zend_Mail_Transport_Smtp($conf->email->host, $conf->email->config->toArray());

        $limit = '10';
        $sQuery = $this->getAdapter()->select()->from(array('tm' => 'temp_mail'))
            ->where("status='pending'")->limit($limit);
        $mailResult = $this->getAdapter()->fetchAll($sQuery);
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
                    if (is_array($cc)) {
                        foreach ($cc as $name => $mail) {
                            $systemMail->addCc($mail, $name);
                        }
                    } else {
                        $systemMail->addCc($cc);
                    }
                }

                if (isset($result['bcc']) && trim($result['bcc']) != "") {
                    if (is_array($cc)) {
                        foreach ($cc as $name => $mail) {
                            $systemMail->addBcc($mail);
                        }
                    } else {
                        $systemMail->addBcc($cc);
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
                    return true;
                    $tempMailDb->deleteTempMail($id);
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

    public function insertMultiple($table, array $data)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        // Get a list of columns from the first row of data
        $cols = array_keys(reset($data));

        // Quote the columns names
        $cols = array_map([$this, 'quoteIdentifier'], $cols);

        // Build the values list
        $vals = [];
        foreach ($data as $row) {
            $vals[] = '(' . implode(', ', $db->quote($row)) . ')';
        }

        // Build the insert query
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $db->quoteIdentifier($table),
            implode(', ', $cols),
            implode(', ', $vals)
        );

        // Execute the query
        return $db->query($sql);
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
}
