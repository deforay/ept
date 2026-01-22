<?php

use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\Filesystem\Filesystem;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;
use Hackzilla\PasswordGenerator\Generator\RequirementPasswordGenerator;

class Application_Service_Common
{
    const MAIL_FAILURE_REASON_MAX = 1000;

    protected $db;

    /** @var Zend_Translate */
    protected $translator;

    public function __construct()
    {
        $this->translator = Zend_Registry::get('translate');
        $this->db = Zend_Db_Table_Abstract::getDefaultAdapter();
    }
    public static function isDateValid($date): bool
    {
        $date = trim($date ?? '');

        if (empty($date) || 'undefined' === $date || 'null' === $date || '0000-00-00' === $date) {
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
                error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
                error_log($e->getTraceAsString());
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

    public static function getDateTime($returnFormat = 'Y-m-d H:i:s')
    {
        $date = new \DateTime(date('Y-m-d H:i:s'));
        return $date->format($returnFormat);
    }
    public static function generateRandomString($length = 32): string
    {
        return Pt_Commons_MiscUtility::generateRandomString($length);
    }

    public static function generateRandomNumber(int $length = 8): string
    {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= random_int(0, 9);
        }
        return $result;
    }

    public function sendMail($to, $cc, $bcc, $subject, $message, $fromMail = null, $fromName = null, $attachments = array())
    {
        // Normalize scalars/arrays to strings for parseRecipients()
        $toStr = is_array($to) ? implode(',', array_values($to)) : (string) $to;
        $ccStr = is_array($cc) ? implode(',', array_values($cc)) : (string) $cc;
        $bccStr = is_array($bcc) ? implode(',', array_values($bcc)) : (string) $bcc;

        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $smtpTransportObj = new Zend_Mail_Transport_Smtp($conf->email->host, $conf->email->config->toArray());

        $fromMail = $fromMail ?: $conf->email->config->username;
        $fromName = $fromName ?: "ePT System";

        $systemMail = new Zend_Mail();

        $originalMessage = html_entity_decode((string) $message, ENT_QUOTES, 'UTF-8');
        $originalMessage = str_replace(array("&nbsp;", "&amp;nbsp;"), "", $originalMessage);

        $systemMail->setSubject((string) $subject);
        $systemMail->setBodyHtml($originalMessage);
        $systemMail->setFrom($fromMail, $fromName);
        $systemMail->setReplyTo($fromMail, $fromName);

        // NEW: unified parsing/validation + dedupe
        $recips = self::parseRecipients(trim($toStr), trim($ccStr) ?: null, trim($bccStr) ?: null);

        if (!empty($recips['invalid'])) {
            error_log("Invalid emails in sendMail(): " . implode(', ', $recips['invalid']));
        }
        if (empty($recips['to'])) {
            error_log("sendMail(): no valid 'To' recipients; aborting send.");
            return false;
        }

        foreach ($recips['to'] as $addr) {
            $systemMail->addTo($addr);
        }
        foreach ($recips['cc'] as $addr) {
            $systemMail->addCc($addr);
        }
        foreach ($recips['bcc'] as $addr) {
            $systemMail->addBcc($addr);
        }

        // Attach files if any
        if (!empty($attachments)) {
            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $attachment = file_get_contents($filePath);
                    $fileName = basename($filePath);
                    $systemMail->createAttachment(
                        $attachment,
                        Zend_Mime::TYPE_OCTETSTREAM,
                        Zend_Mime::DISPOSITION_ATTACHMENT,
                        Zend_Mime::ENCODING_BASE64,
                        $fileName
                    );
                } else {
                    error_log("Attachment file does not exist: " . $filePath);
                }
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

    public static function getConfig($name)
    {
        $gc = new Application_Model_DbTable_GlobalConfig();
        return $gc->getValue($name);
    }
    public function contactForm($params)
    {
        $name = (isset($params['firstName']) && !empty($params['firstName'])) ? true : false;
        $id = (isset($params['participantId']) && !empty($params['participantId'])) ? true : false;
        $subject = (isset($params['subject']) && !empty($params['subject'])) ? true : false;
        if ($name && $id && $subject) {

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
            $fromName = "Online PT Team";

            $toArray[] = Application_Service_Common::getConfig('admin_email');
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1) {
                $toArray[] = $authNameSpace->email;
            }

            $mailSent = $this->insertTempMail(implode(",", $toArray), null, null, $params['subject'], $message, $fromEmail, $fromName, null, $params['email']);
            if ($mailSent) {
                return 1;
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }
    public function checkDuplicate($params): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $tableName = trim($params['tableName'], "'");
        $fieldName = trim($params['fieldName'], "'");
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
                ->where("$table[0]!= '$table[1]'");
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
        $sql = $db->select()->distinct()->from(['p' => 'participant'], ["state"])->group(["state"])->order(["state"]);
        if (isset($cid) && !empty($cid)) {
            $sql = $sql->where("p.country IN (?)", $cid);
        }
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sql = $sql->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
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
        $sql = $db->select()->distinct()->from('participant', ["district"])->group(["district"])->order(["district"]);
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
        $sql = $db->select()->distinct()->from('participant', ["institute_name"])->group(["institute_name"])->order(["institute_name"]);
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
        $filterRules = [
            '*' => 'StripTags',
            '*' => 'StringTrim'
        ];

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
    public function insertTempMail($to, $cc, $bcc, $subject, $message, $fromMail = null, $fromName = null, $attachedFile = null, $replyTo = null)
    {
        $db = new Application_Model_DbTable_TempMail();
        $replyTo ??= $fromMail;
        return $db->insertTempMailDetails($to, $cc, $bcc, $subject, $message, $fromMail, $fromName, $attachedFile, $replyTo);
    }

    public function getAllModeOfReceipt()
    {
        $db = new Application_Model_DbTable_ModeOfReceipt();
        return $db->fetchAllModeOfReceipt();
    }

    public function updateHomeBanner($params)
    {
        $filterRules = [
            '*' => 'StripTags',
            '*' => 'StringTrim'
        ];

        $filter = new Zend_Filter_Input($filterRules, [], $params);

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
        $smtpTransportObj = new Zend_Mail_Transport_Smtp($conf->email->host, $conf->email->config->toArray());

        $limit = '10';
        $sQuery = $tempMailDb->getAdapter()->select()
            ->from(array('tm' => 'temp_mail'))
            ->where("status='pending'")
            ->limit($limit);

        $mailResult = $tempMailDb->getAdapter()->fetchAll($sQuery);
        if (count($mailResult) <= 0) {
            return; // nothing to do
        }

        foreach ($mailResult as $result) {
            $id = $result['temp_id'];
            // mark picked
            $tempMailDb->updateTempMailStatus($id);

            $fromEmail = $result['from_mail'];
            $fromFullName = $result['from_full_name'];
            $subject = $result['subject'];
            $bodyHtml = html_entity_decode((string) $result['message'], ENT_QUOTES, 'UTF-8');
            $bodyHtml = str_replace(array("&nbsp;", "&amp;nbsp;"), "", $bodyHtml);

            $mail = new Zend_Mail();
            $mail->setSubject((string) $subject);
            $mail->setBodyHtml($bodyHtml);
            $mail->setFrom($fromEmail, $fromFullName);
            $mail->setReplyTo($fromEmail, $fromFullName);

            // NEW: use parseRecipients for To/CC/BCC
            $recips = self::parseRecipients(
                trim((string) ($result['to_email'] ?? '')),
                isset($result['cc']) ? trim((string) $result['cc']) : null,
                isset($result['bcc']) ? trim((string) $result['bcc']) : null
            );

            if (!empty($recips['invalid'])) {
                error_log("Invalid emails in sendTempMail(temp_id={$id}): " . implode(', ', $recips['invalid']));
            }
            if (empty($recips['to'])) {
                error_log("sendTempMail(temp_id={$id}): no valid 'To' recipients; marking not-sent.");
                // revert status to not-sent and continue
                self::markTempMailFailed(
                    (int) $id,
                    'No valid To recipients'
                );
                continue;
            }

            foreach ($recips['to'] as $addr) {
                $mail->addTo($addr);
            }
            foreach ($recips['cc'] as $addr) {
                $mail->addCc($addr);
            }
            foreach ($recips['bcc'] as $addr) {
                $mail->addBcc($addr);
            }

            try {
                $mail->send($smtpTransportObj);
                $tempMailDb->deleteTempMail($id);
                // keep looping: do not return early so we can send up to $limit emails
            } catch (Exception $exc) {
                error_log("===== MAIL SENDING FAILED (temp_id={$id}) - START =====");
                error_log($exc->getMessage());
                error_log($exc->getTraceAsString());
                error_log("===== MAIL SENDING FAILED - END =====");
                // mark not-sent and continue to next
                self::markTempMailFailed(
                    (int) $id,
                    $exc->getMessage()
                );
                continue;
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
                } elseif ($optId == $selectedOptions) {
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
        elseif (getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        elseif (getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        elseif (getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        elseif (getenv('HTTP_FORWARDED'))
            $ipaddress = getenv('HTTP_FORWARDED');
        elseif (getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }

    public function getOperatingSystem($userAgent = null)
    {
        $osPlatform = "Unknown OS - " . $userAgent;

        $osArray = array(
            '/windows nt 6.3/i' => 'Windows 8.1',
            '/windows nt 6.2/i' => 'Windows 8',
            '/windows nt 6.1/i' => 'Windows 7',
            '/windows nt 6.0/i' => 'Windows Vista',
            '/windows nt 5.2/i' => 'Windows Server 2003/XP x64',
            '/windows nt 5.1/i' => 'Windows XP',
            '/windows xp/i' => 'Windows XP',
            '/windows nt 5.0/i' => 'Windows 2000',
            '/windows me/i' => 'Windows ME',
            '/win98/i' => 'Windows 98',
            '/win95/i' => 'Windows 95',
            '/win16/i' => 'Windows 3.11',
            '/macintosh|mac os x/i' => 'Mac OS X',
            '/mac_powerpc/i' => 'Mac OS 9',
            '/linux/i' => 'Linux',
            '/ubuntu/i' => 'Ubuntu',
            '/iphone/i' => 'iPhone',
            '/ipod/i' => 'iPod',
            '/ipad/i' => 'iPad',
            '/android/i' => 'Android',
            '/blackberry/i' => 'BlackBerry',
            '/webos/i' => 'Mobile'
        );

        foreach ($osArray as $regex => $value) {
            if (preg_match($regex, $userAgent)) {
                $osPlatform = $value;
            }
        }
        return $osPlatform;
    }

    public function getBrowser($userAgent = null)
    {

        $browser = "Unknown Browser - " . $userAgent;
        $browserArray = array(
            '/msie/i' => 'Internet Explorer',
            '/firefox/i' => 'Firefox',
            '/safari/i' => 'Safari',
            '/chrome/i' => 'Chrome',
            '/opera/i' => 'Opera',
            '/netscape/i' => 'Netscape',
            '/maxthon/i' => 'Maxthon',
            '/konqueror/i' => 'Konqueror',
            '/mobile/i' => 'Handheld Browser'
        );

        foreach ($browserArray as $regex => $value) {

            if (preg_match($regex, $userAgent)) {
                $browser = $value;
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

        try {
            return $db->query($sql);
        } catch (Zend_Db_Adapter_Exception $e) {
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
            return false;
        }
    }



    public function getOptionsByValue($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from([$params['table']], [$params['returnfield']])
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
            ->setOptionValue(ComputerPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_LOWER_CASE, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_NUMBERS, true)
            ->setOptionValue(ComputerPasswordGenerator::OPTION_SYMBOLS, false)
            ->setMinimumCount(ComputerPasswordGenerator::OPTION_UPPER_CASE, 2)
            ->setMinimumCount(ComputerPasswordGenerator::OPTION_LOWER_CASE, 2)
            ->setMinimumCount(ComputerPasswordGenerator::OPTION_NUMBERS, 2);

        $password = $generator->generatePassword();
        return $password;
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
        $range = "$startCell:$highestColumn$startRow";

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
            $range = "{$column}1:$column$highestRow";

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

    public function getAllTestKitBySearch($text)
    {
        $db = new Application_Model_DbTable_Testkitnames();
        $sql = $db->select()->from(array('r_testkitnames'), array('TESTKITNAMEID' => 'TestKitName_ID', 'TESTKITNAME' => 'TestKit_Name'))->where("TESTKIT_NAME LIKE '%" . $text . "%'");
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

    public function getMappedTestKits($pid, $sid = "")
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
        $query = $db->select()->from(['pff' => 'r_participant_feedback_form_question_map'], ['pff.question_id'])
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

    public static function parseRecipients(string $to = '', ?string $cc = null, ?string $bcc = null): array
    {
        $buckets = ['to' => $to, 'cc' => $cc, 'bcc' => $bcc];
        $out = ['to' => [], 'cc' => [], 'bcc' => [], 'invalid' => []];
        $seen = [];

        $split = static function (?string $list): array {
            if ($list === null || trim($list) === '')
                return [];
            return preg_split('/[;,]+/', $list) ?: [];
        };

        foreach ($buckets as $bucket => $rawList) {
            foreach ($split($rawList) as $raw) {
                $normalized = self::validateEmail($raw); // returns normalized or null
                if ($normalized !== null) {
                    $key = strtolower($normalized);
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;              // cross-bucket dedupe
                        $out[$bucket][] = $normalized;   // already normalized
                    }
                } else {
                    $out['invalid'][] = $raw;           // keep original for logging
                }
            }
        }

        return $out;
    }

    public static function formatMailFailureReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/', ' ', (string) $reason));
        if ($normalized === '') {
            return null;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($normalized, 0, self::MAIL_FAILURE_REASON_MAX);
        }

        return substr($normalized, 0, self::MAIL_FAILURE_REASON_MAX);
    }

    /**
     * Mark a temp_mail row as not-sent with an optional failure reason.
     */
    public static function classifyMailFailure(string $reason): string
    {
        $r = strtolower($reason);

        if (strpos($r, 'authentication') !== false || strpos($r, 'authenticat') !== false) {
            return 'smtp-auth';
        }
        if (
            strpos($r, 'connection timed out') !== false ||
            strpos($r, 'unable to connect') !== false ||
            strpos($r, 'could not connect') !== false
        ) {
            return 'connectivity';
        }
        if (
            strpos($r, 'recipient') !== false &&
            (strpos($r, 'rejected') !== false || strpos($r, 'invalid') !== false)
        ) {
            return 'bad-recipient';
        }
        if (strpos($r, 'quota') !== false || strpos($r, 'too many messages') !== false) {
            return 'rate-limit';
        }
        if (strpos($r, 'spam') !== false || strpos($r, 'blocked') !== false) {
            return 'content';
        }

        return 'other';
    }


    /**
     * Mark a temp_mail row as failed with an optional failure reason.
     */
    public static function markTempMailFailed(
        int $tempId,
        string $failureReason
    ): void {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $failureReason = mb_substr($failureReason, 0, 1024); // keep it bounded
        $failureType = self::classifyMailFailure($failureReason);

        $db->update(
            'temp_mail',
            [
                'status' => 'failed',
                'failure_reason' => $failureReason,
                'failure_type' => $failureType,
                'updated_at' => new Zend_Db_Expr('NOW()'),
            ],
            ['temp_id = ?' => $tempId]
        );
    }

    /**
     * Validate and normalize an email address
     * 
     * Requires a valid TLD (e.g., .com, .org, .net) in the domain
     * Rejects single-word domains without TLD (e.g., user@ept, user@localhost)
     * 
     * @param string $email The email address to validate
     * @return string|null Normalized email on success, null on failure
     */
    public static function validateEmail(string $email): ?string
    {
        static $cache = []; // Memoize results per-process for performance

        $original = trim($email);
        if ($original === '') {
            return null;
        }

        try {
            // Extract email from "Name <user@host>" format
            if (preg_match('/<([^>]+)>/', $original, $m)) {
                $original = trim($m[1]);
            }

            // Strip wrapping quotes and whitespace
            $email = trim($original, " \t\n\r\0\x0B\"'");

            // Split into local and domain parts
            $at = strrpos($email, '@');
            if ($at === false || $at === 0 || $at === strlen($email) - 1) {
                // No @ sign, or @ is at start/end
                return null;
            }

            $local = substr($email, 0, $at);
            $domain = substr($email, $at + 1);

            // Validate local part is not empty
            if ($local === '') {
                return null;
            }

            // Normalize domain using IDN (Internationalized Domain Names) if available
            if (function_exists('idn_to_ascii')) {
                try {
                    // PHP 8.1+ requires INTL_IDNA_VARIANT_UTS46
                    if (defined('INTL_IDNA_VARIANT_UTS46')) {
                        $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
                    } else {
                        $ascii = idn_to_ascii($domain, IDNA_DEFAULT);
                    }
                    if ($ascii !== false) {
                        $domain = $ascii;
                    }
                } catch (Exception $e) {
                    // IDN conversion failed, continue with original domain
                    error_log("IDN conversion failed for domain '{$domain}': " . $e->getMessage());
                }
            }

            // Normalize domain to lowercase
            $domain = strtolower($domain);

            // CRITICAL: Require domain to have at least one dot (to have a TLD)
            // This rejects emails like user@ept, user@localhost
            // This accepts emails like user@example.com, user@mail.ept.com
            if (strpos($domain, '.') === false) {
                error_log("Email rejected - no TLD found: {$email}");
                return null;
            }

            // Validate domain has valid TLD (at least 2 characters after last dot)
            $lastDot = strrpos($domain, '.');
            if ($lastDot === false || $lastDot === strlen($domain) - 1) {
                error_log("Email rejected - invalid TLD position: {$email}");
                return null;
            }

            $tld = substr($domain, $lastDot + 1);
            if (strlen($tld) < 2 || !preg_match('/^[a-z]{2,}$/', $tld)) {
                error_log("Email rejected - invalid TLD '{$tld}': {$email}");
                return null;
            }

            $normalized = $local . '@' . $domain;
            $key = strtolower($normalized);

            // Use cache to avoid revalidating the same email multiple times
            if (!isset($cache[$key])) {
                // Use PHP's built-in filter which now will pass because domain has TLD
                $isValid = filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false;

                // Additional validation checks
                if ($isValid) {
                    // RFC 5321 length limits
                    if (strlen($local) > 64 || strlen($domain) > 255) {
                        $isValid = false;
                    }

                    // Check for consecutive dots in local part
                    if (strpos($local, '..') !== false) {
                        $isValid = false;
                    }

                    // Check if local part starts or ends with a dot
                    if ($local[0] === '.' || $local[strlen($local) - 1] === '.') {
                        $isValid = false;
                    }
                }

                $cache[$key] = $isValid;
            }

            return $cache[$key] ? $normalized : null;
        } catch (Exception $e) {
            // Log unexpected errors and return null
            error_log("Error validating email '{$email}': " . $e->getMessage());
            return null;
        }
    }


    public static function makeDirectory($path, $mode = 0755, $recursive = true): bool
    {
        $filesystem = new Filesystem();

        if ($filesystem->exists($path)) {
            return true; // Directory already exists
        }

        try {
            $filesystem->mkdir($path, $mode); // Handles recursive creation automatically
            return true;
        } catch (Throwable $exception) {
            return false; // Directory creation failed
        }
    }

    public static function removeDirectory($dirname): bool
    {
        if (!file_exists($dirname)) {
            return false;
        }

        if (is_file($dirname) || is_link($dirname)) {
            return unlink($dirname);
        }

        $dir = dir($dirname);
        while (false !== ($entry = $dir->read())) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            $fullPath = $dirname . DIRECTORY_SEPARATOR . $entry;
            if (!self::removeDirectory($fullPath)) {
                $dir->close(); // Close the directory handle if a recursive delete fails.
                return false;
            }
        }

        $dir->close();
        return rmdir($dirname);
    }

    public function getEmailParticipantSubjects($search)
    {
        $db = new Application_Model_DbTable_EmailParticipants();
        return $db->fetchEmailParticipantSubjects($search);
    }

    public function getEmailTemplateBySubject($subject)
    {
        $db = new Application_Model_DbTable_EmailParticipants();
        return $db->fetchEmailParticipantSubjects($subject);
    }

    public function svgRectPertangeToHeightConverter($score, $svgHeight = 100, $topOffset = 20)
    {
        return $svgHeight + $topOffset - $score;
    }
    public static function flattenPdf($inputFilePath, $outputFilePath, $deleteOriginal = true)
    {
        // Escape shell arguments to handle spaces and special characters
        $inputFilePath = escapeshellarg($inputFilePath);
        $outputFilePath = escapeshellarg($outputFilePath);

        // Construct the pdftk command
        $command = "pdftk {$inputFilePath} output {$outputFilePath} flatten";

        // Execute the command and capture the output and return code
        $output = shell_exec($command);
        $returnCode = shell_exec("echo $?");

        // Check if the command was successful
        if (intval($returnCode) !== 0) {
            throw new RuntimeException("pdftk error: $output");
        }
        if ($deleteOriginal && file_exists($inputFilePath)) {
            unlink($inputFilePath);
        }
    }

    // Convert a JSON string to a string that can be used with JSON_SET()
    public static function jsonToSetString(?string $json, string $column, $newData = []): ?string
    {
        // Decode JSON string to array
        $jsonData = $json && self::isJSON($json) ? json_decode($json, true) : [];

        // Decode newData if it's a string
        if (is_string($newData)) {
            $newData = json_decode($newData, true);
        }

        // Combine original data and new data
        $data = array_merge($jsonData, $newData);

        // Return null if there's nothing to set
        if (empty($data)) {
            return null;
        }

        // Build the set string
        $setString = '';
        foreach ($data as $key => $value) {
            $setString .= ', "$.' . $key . '", ' . self::jsonValueToString($value);
        }

        // Construct and return the JSON_SET query
        return 'JSON_SET(COALESCE(' . $column . ', "{}")' . $setString . ')';
    }

    // Convert data to JSON string
    public static function toJSON($data, int $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): ?string
    {
        // Check if the data is already a valid JSON string
        if (is_string($data) && self::isJSON($data)) {
            return $data;
        }

        // Convert the data to JSON
        $json = json_encode($data, $flags);
        if ($json === false) {
            throw new Exception('error', 'Data could not be encoded as JSON: ' . json_last_error_msg());
            //return null;
        }
        return $json;
    }

    public static function isJSON($string, bool $logError = false): bool
    {
        return Pt_Commons_JsonUtility::isJSON($string, $logError);
    }

    // Convert a value to a JSON-compatible string representation
    public static function jsonValueToString($value): string
    {
        if (is_null($value)) {
            return 'null';
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_numeric($value)) {
            return (string) $value;
        } elseif (is_array($value)) {
            return "'" . addslashes(json_encode($value)) . "'";
        } else {
            return "'" . addslashes((string) $value) . "'";
        }
    }

    public static function passwordHash($password)
    {
        if (empty($password)) {
            return null;
        }

        // Check if the password appears to be already hashed
        if (self::isBcryptHash($password)) {
            return $password;
        }

        $options = ['cost' => 14];
        return password_hash((string) $password, PASSWORD_BCRYPT, $options);
    }

    /**
     * Check if the given string is a BCRYPT hash
     */
    private static function isBcryptHash($string)
    {
        return preg_match('/^\$2[ayb]\$\d{2}\$.{53}$/', $string);
    }


    public function validatePassword($password, $name = null, $email = null, $minLength = 8, $requireSymbols = false)
    {
        // Validate input types
        if (!is_string($password)) {
            return $this->translator->_("Password must be a string.");
        }

        // Check length - use mb_strlen for proper multibyte character support
        if (mb_strlen($password, 'UTF-8') < $minLength) {
            return $this->translator->_("Password must be at least {$minLength} characters long.");
        }

        // Check maximum length to prevent DoS attacks
        if (mb_strlen($password, 'UTF-8') > 128) {
            return $this->translator->_("Password must not exceed 128 characters.");
        }

        // Check for at least one letter
        if (!preg_match('/[a-zA-Z]/', $password)) {
            return $this->translator->_("Password must contain at least one letter.");
        }

        // Check for at least one number
        if (!preg_match('/[0-9]/', $password)) {
            return $this->translator->_("Password must contain at least one number.");
        }

        // Check for symbols if required
        if ($requireSymbols && !preg_match('/[\W_]/', $password)) {
            return $this->translator->_("Password must contain at least one symbol.");
        }

        // Check against name parts (minimum 3 characters to avoid false positives)
        if (!empty($name) && is_string($name)) {
            $nameParts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

            foreach ($nameParts as $part) {
                // Only check parts that are 3+ characters
                if (mb_strlen($part, 'UTF-8') >= 3) {
                    if (stripos($password, $part) !== false) {
                        return $this->translator->_("Password must not contain parts of your name.");
                    }
                }
            }
        }

        // Check against email local part (minimum 3 characters)
        if (!empty($email) && is_string($email)) {
            $emailParts = explode('@', $email, 2);

            if (isset($emailParts[0]) && !empty($emailParts[0])) {
                $emailLocal = $emailParts[0];

                // Split email local part by common separators
                $localParts = preg_split('/[._\-+]/', $emailLocal, -1, PREG_SPLIT_NO_EMPTY);

                foreach ($localParts as $part) {
                    // Only check parts that are 3+ characters
                    if (mb_strlen($part, 'UTF-8') >= 3) {
                        if (stripos($password, $part) !== false) {
                            return $this->translator->_("Password must not contain parts of your email address.");
                        }
                    }
                }
            }
        }

        // Check for common weak patterns
        $weakPatterns = [
            '/^(.)\1+$/',           // All same character (e.g., "aaaaaaaa")
            '/^(..+)\1+$/',         // Repeating patterns (e.g., "abcabcabc")
            '/^(?:0123|1234|2345|3456|4567|5678|6789|7890)+/', // Sequential numbers
            '/^(?:abcd|bcde|cdef|defg|efgh|fghi|ghij|hijk|ijkl|jklm|klmn|lmno|mnop|nopq|opqr|pqrs|qrst|rstu|stuv|tuvw|uvwx|vwxy|wxyz)+/i', // Sequential letters
        ];

        foreach ($weakPatterns as $pattern) {
            if (preg_match($pattern, $password)) {
                return $this->translator->_("Password is too weak. Please choose a more complex password.");
            }
        }

        return 'success';
    }

    public static function displayProgressBar($current, $total = null, $size = 30)
    {
        static $startTime;

        // Start the timer
        if (empty($startTime)) {
            $startTime = time();
        }

        // Calculate elapsed time
        $elapsed = time() - $startTime;

        if ($total !== null) {
            // Calculate the percentage
            $progress = $current / $total;
            $bar = floor($progress * $size);

            // Generate the progress bar string
            $progressBar = str_repeat('=', $bar) . str_repeat(' ', $size - $bar);

            // Output the progress bar
            printf("\r[%s] %d%% Complete (%d/%d) - %d sec elapsed", $progressBar, $progress * 100, $current, $total, $elapsed);
        } else {
            // Output the current progress without percentage
            printf("\rProcessed %d items - %d sec elapsed", $current, $elapsed);
        }

        // Flush output
        if ($total !== null && $current === $total) {
            echo "\n";
        }
    }

    /**
     * Securely checks if the file can be uploaded based on allowed extensions and inferred MIME types.
     *
     * @param array $file The raw file array from $_FILES
     * @param array $allowedExtensions Array of allowed file extensions
     * @return bool|string Returns true if the file is valid, otherwise returns an error message.
     */
    public static function isFileAllowedToUpload($file, array $allowedExtensions)
    {
        $error = null;

        // Check if file was uploaded without errors
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $error = "No valid file uploaded.";
        }

        // Get the file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Check if the extension is allowed
        if (!$error && in_array($extension, $allowedExtensions)) {
            // Infer allowed MIME types based on the allowed extensions
            $mimeTypes = self::getMimeTypesForExtensions($allowedExtensions);

            // Get the real MIME type of the uploaded file
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            // Check if the MIME type is allowed
            if (!in_array($mimeType, $mimeTypes)) {
                $error = "File MIME type not allowed.";
            }
        } elseif (!$error) {
            $error = "File extension not allowed.";
        }

        return $error ?: true;
    }

    /**
     * Helper function to map allowed file extensions to MIME types.
     *
     * @param array $allowedExtensions Array of allowed file extensions
     * @return array Array of inferred MIME types
     */
    private static function getMimeTypesForExtensions(array $allowedExtensions)
    {
        // Map common file extensions to MIME types
        $mimeTypesMap = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'csv' => ['text/csv', 'application/csv', 'text/plain'],
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'html' => 'text/html',
            'xml' => 'application/xml',
            'json' => 'application/json'
        ];

        $mimeTypes = [];
        foreach ($allowedExtensions as $extension) {
            if (isset($mimeTypesMap[$extension])) {
                $mimeType = $mimeTypesMap[$extension];
                if (is_array($mimeType)) {
                    $mimeTypes = array_merge($mimeTypes, $mimeType);  // Merge if multiple MIME types
                } else {
                    $mimeTypes[] = $mimeType;  // Single MIME type
                }
            }
        }

        return $mimeTypes;
    }

    /**
     * Safely constructs a file path by combining predefined and user-supplied components.
     * Recursively creates the folder structure if it doesn't exist.
     *
     * @param string $baseDirectory The predefined base directory.
     * @param array $pathComponents An array of path components, where some may be user-supplied.
     * @return string|bool Returns the constructed, sanitized path if valid, or false if the path is invalid.
     */
    public static function buildSafePath($baseDirectory, array $pathComponents)
    {
        if (!is_dir($baseDirectory) && !self::makeDirectory($baseDirectory)) {
            return false; // Failed to create the directory
        }

        // Normalize the base directory
        $baseDirectory = realpath($baseDirectory);

        // Clean and sanitize each component of the path
        $cleanComponents = [];
        foreach ($pathComponents as $component) {
            // Remove dangerous characters from user-supplied components
            $cleanComponent = preg_replace('/[^a-zA-Z0-9-_]/', '', $component);
            $cleanComponents[] = $cleanComponent;
        }

        // Join the base directory with the cleaned components to create the full path
        $fullPath = $baseDirectory . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $cleanComponents);

        // Check if the directory exists, if not, create it recursively
        if (!is_dir($fullPath) && !self::makeDirectory($fullPath)) {
            return false; // Failed to create the directory
        }

        return realpath($fullPath); // Clean and validated path
    }

    /**
     * Cleans up the input file name, removing any unsafe characters and returning the base file name with its extension.
     *
     * @param string $filePath The input file name or full path.
     * @return string The cleaned base file name with its extension.
     */
    public static function cleanFileName($filePath)
    {
        // Extract the base file name (removes the path if provided)
        $baseFileName = basename($filePath);

        // Separate the file name from its extension
        $extension = strtolower(pathinfo($baseFileName, PATHINFO_EXTENSION));
        $fileNameWithoutExtension = pathinfo($baseFileName, PATHINFO_FILENAME);

        // Clean the file name, keeping only alphanumeric characters, dashes, and underscores
        $cleanFileName = preg_replace('/[^a-zA-Z0-9-_]/', '', $fileNameWithoutExtension);

        // Reconstruct the file name with its extension
        return $cleanFileName . ($extension ? ".$extension" : '');
    }

    public function stringToCamelCase($string, $character = ' ')
    {
        // Split the string by underscores
        $words = explode($character, $string);

        // Convert the first word to lowercase (for lowerCamelCase)
        $camelCaseString = strtolower(array_shift($words));

        // Capitalize the first letter of each remaining word
        $camelCaseString .= implode('', array_map('ucfirst', $words));

        return $camelCaseString;
    }


    public function dataToZippedFile(string $stringData, string $fileName): bool
    {
        if (empty($stringData) || empty($fileName)) {
            return false;
        }

        $zip = new ZipArchive();
        $zipPath = "$fileName.zip";

        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            $zip->addFromString(basename($fileName), $stringData);
            $result = $zip->status == ZipArchive::ER_OK;
            $zip->close();
            return $result;
        }

        return false;
    }

    public function fetchAjaxDropdownList($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Fix: Decode JSON strings if needed
        if (isset($params['concat']) && is_string($params['concat'])) {
            $params['concat'] = json_decode($params['concat'], true);
        }
        if (isset($params['fieldNames']) && is_string($params['fieldNames'])) {
            $params['fieldNames'] = json_decode($params['fieldNames'], true);
        }

        // Handle concat fields properly
        $concat = [];
        if (is_array($params['concat'])) {
            foreach ($params['concat'] as $field) {
                // Sanitize field name to prevent SQL injection
                $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
                $concat[] = "COALESCE(`$field`,'')";
            }
        } else {
            $field = preg_replace('/[^a-zA-Z0-9_]/', '', $params['concat']);
            $concat[] = "COALESCE(`$field`,'')";
        }

        // Build the SQL query
        $sql = $db->select()->from($params['tableName'], [
            $params['returnId'],
            'concat' => new Zend_Db_Expr("CONCAT(" . implode(", ' ', ", $concat) . ")")
        ]);

        // Handle search across multiple fields properly
        if (isset($params['search']) && !empty($params['search'])) {
            // Escape the search term to prevent SQL injection
            $searchTerm = $db->quote('%' . $params['search'] . '%');

            if (is_array($params['fieldNames'])) {
                // Create OR conditions for searching across multiple fields
                $searchConditions = [];
                foreach ($params['fieldNames'] as $field) {
                    // Sanitize field name
                    $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
                    $searchConditions[] = "`$field` LIKE $searchTerm";
                }
                $sql = $sql->where('(' . implode(' OR ', $searchConditions) . ')');
            } else {
                $field = preg_replace('/[^a-zA-Z0-9_]/', '', $params['fieldNames']);
                $sql = $sql->where("`$field` LIKE $searchTerm");
            }
        }

        // Group by primary key to avoid duplicates
        $sql = $sql->group($params['returnId']);

        // Add ordering for better UX
        $sql = $sql->order('concat ASC');

        // Add pagination if needed
        if (isset($params['page'])) {
            $page = (int) $params['page'];
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $sql = $sql->limit($limit, $offset);
        }
        return $db->fetchAll($sql);
    }

    public static function isValidEmail(string $email, array $excludedDomains = []): bool
    {
        // Check email syntax
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Extract domain
        $domain = substr(strrchr($email, "@"), 1);

        // Check if the domain is in the excluded list
        if (in_array(strtolower($domain), array_map('strtolower', $excludedDomains), true)) {
            return false;
        }

        // Check DNS records for the domain
        if (!checkdnsrr($domain, 'MX')) {
            return false;
        }

        return true;
    }

    public function exportConfig($data)
    {
        if (isset($data['file']) && !empty($data['file']) && $data['file'] == 'config') {
            $filePath = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";

            $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
            $eptDomain = rtrim($conf->domain, "/");
            $eptDomain = parse_url($eptDomain, PHP_URL_HOST);

            $gzdata = gzencode(file_get_contents($filePath));
            $gipPath = realpath(TEMP_UPLOAD_PATH) . DIRECTORY_SEPARATOR . 'config-' . $eptDomain . '-' . str_replace([':', ' ', '_'], '-', Pt_Commons_DateUtility::getCurrentDateTime()) . ".ini.gz";
            file_put_contents($gipPath, $gzdata);

            return $gipPath;
        } else {
            $responseData = $this->unserializeForm($data);
            /* unset the csrf token that was not needed for config export */
            unset($responseData['csrf_token']);
            $output = [
                'timestamp' => time(),
                'data' => $responseData
            ];
            /* File name creation */
            $fileName = Pt_Commons_MiscUtility::generateRandomString(12) . '-' . time() . '-' . $data['scheme'] . '.json';
            $filePath = realpath(TEMP_UPLOAD_PATH) . DIRECTORY_SEPARATOR . $fileName;
            $fp = fopen($filePath, 'w');
            fwrite($fp, json_encode($output));
            fclose($fp);
            $gzdata = gzencode(file_get_contents($filePath));
            file_put_contents($filePath . ".gz", $gzdata);
            return $filePath . ".gz";
        }
    }

    public function unserializeForm($str)
    {
        $returndata = [];
        $strArray = explode("&", $str['formPost'] ?? '');
        foreach ($strArray as $item) {
            $array = explode("=", $item, 2);
            $key = str_replace('[]', '', urldecode($array[0] ?? ''));
            $val = urldecode($array[1] ?? '');
            $returndata[$key][] = $val;
        }
        return $returndata;
    }


    /**
     * Compute email sending health from temp_mail.
     *
     * @param Zend_Db_Adapter_Abstract $db
     * @param array $options {
     *      @type int   $days             Look-back window in days (default 7)
     *      @type int   $min_total        Min emails in window before we care (default 20)
     *      @type float $warn_threshold   Failure ratio for "warning" (default 0.05 = 5%)
     *      @type float $critical_threshold Failure ratio for "critical" (default 0.15 = 15%)
     * }
     * @return array {
     *      @type int    window_days
     *      @type string window_from
     *      @type string window_to
     *      @type int    sent
     *      @type int    failed
     *      @type int    pending
     *      @type int    in_flight
     *      @type int    total_considered   sent + failed
     *      @type float  failure_rate       0.0–1.0
     *      @type string severity           'ok'|'warning'|'critical'
     *      @type string summary            Human-readable summary
     *      @type array  breakdown          [ [ 'failure_type' => 'smtp-auth', 'count' => 10 ], ... ]
     * }
     */
    public static function getEmailQueueHealth(array $options = []): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $days = isset($options['days']) ? (int) $options['days'] : 7;
        $minTotal = isset($options['min_total']) ? (int) $options['min_total'] : 20;
        $warnThreshold = isset($options['warn_threshold']) ? (float) $options['warn_threshold'] : 0.05;
        $criticalThreshold = isset($options['critical_threshold']) ? (float) $options['critical_threshold'] : 0.15;

        if ($days < 1) {
            $days = 1;
        }

        // Time window (DB-side)
        $windowExpr = sprintf("NOW() - INTERVAL %d DAY", $days);

        // Totals
        $sqlTotals = "
            SELECT
                SUM(status = 'sent')                         AS sent_count,
                SUM(status IN ('failed','not-sent'))         AS failed_count,
                SUM(status = 'pending')                      AS pending_count,
                SUM(status = 'picked-to-process')            AS inflight_count
            FROM temp_mail
            WHERE queued_on >= {$windowExpr}
        ";

        $row = $db->fetchRow($sqlTotals) ?: [];

        $sent = (int) ($row['sent_count'] ?? 0);
        $failed = (int) ($row['failed_count'] ?? 0);
        $pending = (int) ($row['pending_count'] ?? 0);
        $inFlight = (int) ($row['inflight_count'] ?? 0);

        $totalConsidered = $sent + $failed;
        $failureRate = $totalConsidered > 0 ? ($failed / $totalConsidered) : 0.0;

        // Breakdown by failure_type
        $sqlBreakdown = "
            SELECT
                COALESCE(failure_type, 'other') AS failure_type,
                COUNT(*) AS cnt
            FROM temp_mail
            WHERE status IN ('failed','not-sent')
              AND queued_on >= {$windowExpr}
            GROUP BY failure_type
            ORDER BY cnt DESC
        ";
        $breakdown = $db->fetchAll($sqlBreakdown) ?: [];

        // Determine severity
        $severity = 'ok';
        if ($totalConsidered >= $minTotal && $failed > 0) {
            if ($failureRate >= $criticalThreshold) {
                $severity = 'critical';
            } elseif ($failureRate >= $warnThreshold) {
                $severity = 'warning';
            }
        }

        // Human-readable summary
        $percent = $failureRate * 100;
        if ($totalConsidered === 0 && $pending === 0 && $inFlight === 0) {
            $summary = sprintf(
                'No email activity in the last %d day(s).',
                $days
            );
        } elseif ($totalConsidered === 0) {
            $summary = sprintf(
                'No emails completed in the last %d day(s). Pending: %d, In-flight: %d.',
                $days,
                $pending,
                $inFlight
            );
        } else {
            $summary = sprintf(
                '%d of %d emails (%.1f%%) failed in the last %d day(s). Pending: %d, In-flight: %d.',
                $failed,
                $totalConsidered,
                $percent,
                $days,
                $pending,
                $inFlight
            );
        }

        // Window timestamps (PHP-side, for display)
        $now = new DateTimeImmutable('now');
        $from = $now->sub(new DateInterval('P' . $days . 'D'));

        return [
            'window_days' => $days,
            'window_from' => $from->format('Y-m-d H:i:s'),
            'window_to' => $now->format('Y-m-d H:i:s'),
            'sent' => $sent,
            'failed' => $failed,
            'pending' => $pending,
            'in_flight' => $inFlight,
            'total_considered' => $totalConsidered,
            'failure_rate' => $failureRate,
            'severity' => $severity,
            'summary' => $summary,
            'breakdown' => $breakdown,
            'config' => [
                'min_total' => $minTotal,
                'warn_threshold' => $warnThreshold,
                'critical_threshold' => $criticalThreshold,
            ],
        ];
    }

    public function getEmailFailureInGrid($search)
    {
        $db = new Application_Model_DbTable_TempMail();
        return $db->fetchEmailFailureInGrid($search);
    }

    public static function makeFileNameFriendly($str, $toLowerCase = false)
    {
        // Remove special characters except hyphens
        $str = preg_replace('/[^a-zA-Z0-9\-]/', '', trim($str));

        // Convert spaces to hyphens
        $str = str_replace(' ', '-', $str);

        // Convert multiple hyphens into one
        $str = preg_replace('/-+/', '-', $str);

        if ($toLowerCase === true) {
            // Convert to lowercase
            $str = strtolower($str);
        }

        return $str;
    }

    public static function fileExists($filePath): bool
    {
        return !empty($filePath) && file_exists($filePath) && !is_dir($filePath) && filesize($filePath) > 0;
    }

    public function saveConfigByName($value, $name)
    {
        $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
        return $globalConfigDb->saveConfigByName($value, $name);
    }

    public function saveSchemeConfigByName($value, $name)
    {
        $sc = new Application_Model_DbTable_SchemeConfig();
        return $sc->saveSchemeConfigByName($value, $name);
    }

    public static function getSchemeConfig($name)
    {
        return Pt_Commons_SchemeConfig::get($name);
    }
}
