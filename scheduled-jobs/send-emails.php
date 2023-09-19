<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php');

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);


$db = Zend_Db::factory($conf->resources->db);
Zend_Db_Table::setDefaultAdapter($db);

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

$smtpTransportObj = new Zend_Mail_Transport_Smtp($conf->email->host, $conf->email->config->toArray());

$limit = '100';
$sQuery = $db->select()->from(array('tm' => 'temp_mail'))->where("tm.status=?", 'pending')->limit($limit);
$mailResult = $db->fetchAll($sQuery);

if (count($mailResult) > 0) {
    foreach ($mailResult as $result) {
        try {
            $alertMail = new Zend_Mail();
            $id = "temp_id=" . $result['temp_id'];
            $db->update('temp_mail', array('status' => 'not-sent'), 'temp_id=' . $result['temp_id']);
            $fromEmail = $conf->email->config->username;
            $fromFullName = "ePT System";
            $subject = $result['subject'];
            $alertMail->setBodyHtml($result['message']);
            $alertMail->setFrom($fromEmail, $fromFullName);
            $alertMail->setReplyTo($fromEmail, $fromFullName);

            $result['to_email'] = str_replace(";", ",", $result['to_email']);
            $result['to_email'] = str_replace("/", ",", $result['to_email']);
            $result['to_email'] = str_replace("?", ",", $result['to_email']);
            $result['to_email'] = str_replace(" ", "", $result['to_email']);

            $toArray = explode(",", $result['to_email']);
            foreach ($toArray as $toId) {
                if ($toId != '') {
                    echo $toId . PHP_EOL;
                    $alertMail->addTo(trim($toId));
                }
            }
            if (isset($result['cc']) && !empty(trim($result['cc']))) {
                $result['cc'] = str_replace(";", ",", $result['cc']);
                $result['cc'] = str_replace("/", ",", $result['cc']);
                $result['cc'] = str_replace(" ", "", $result['cc']);
                $ccArray = explode(",", $result['cc']);
                foreach ($ccArray as $ccId) {
                    if ($ccId != '' && strtoupper($ccId) != 'NULL' && $ccId != null) {
                        $alertMail->addCc(trim($ccId));
                    }
                }
            }

            if (isset($result['bcc']) && !empty(trim($result['bcc']))) {
                $result['bcc'] = str_replace(";", ",", $result['bcc']);
                $result['bcc'] = str_replace("/", ",", $result['bcc']);
                $result['bcc'] = str_replace(" ", "", $result['bcc']);
                $bccArray = explode(",", $result['bcc']);
                foreach ($bccArray as $bccId) {
                    if ($bccId != '') {
                        if ($bccId != '' && strtoupper($bccId) != 'NULL' && $bccId != null) {
                            $alertMail->addBcc(trim($bccId));
                        }
                    }
                }
            }

            $alertMail->setSubject($subject);
            $sendResult = $alertMail->send($smtpTransportObj);
            //var_dump($sendResult);
            if ($sendResult == true) {
                $db->delete('temp_mail', $id);
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
            error_log('whoops! Something went wrong in scheduled-jobs/send-emails.php  - ' . $result['to_email']);
            continue;
        }
    }
}
