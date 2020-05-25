<?php

include_once 'CronInit.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

try {

    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

    $smtpTransportObj = new Zend_Mail_Transport_Smtp($conf->email->host, $conf->email->config->toArray());

    $limit = '100';
    $sQuery = $db->select()->from(array('tm' => 'temp_mail'))->where("tm.status=?", 'pending')->limit($limit);
    $mailResult = $db->fetchAll($sQuery);

    //error_log('RUNNING CRON TO SEND MAIL PA');

    if (count($mailResult) > 0) {
        foreach ($mailResult as $result) {
            $alertMail = new Zend_Mail();
            $id = "temp_id=" . $result['temp_id'];
            $db->update('temp_mail', array('status' => 'not-sent'), 'temp_id=' . $result['temp_id']);
            //$fromEmail = $conf->email->config->username;
            $fromEmail = "pt@vlsmartconnect.com";
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
                    echo $toId.PHP_EOL;
                    $alertMail->addTo(trim($toId));
                }
            }
            if (isset($result['cc']) && trim($result['cc']) != "") {
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

            if (isset($result['bcc']) && trim($result['bcc']) != "") {
                $bccArray = explode(",", $result['bcc']);
                foreach ($bccArray as $bccId) {
                    if ($bccId != '') {
                        $alertMail->addBcc(trim($bccId));
                    }
                }
            }

            $alertMail->setSubject($subject);
            $sendResult = $alertMail->send($smtpTransportObj);
            //var_dump($sendResult);
            if ($sendResult == true) {
                $db->delete('temp_mail', $id);
            }

        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    echo($e->getMessage()). PHP_EOL;
    error_log($e->getTraceAsString());
    echo('whoops! Something went wrong in scheduled-jobs/SendMailAlerts.php  - ' . $result['to_email']);
    error_log('whoops! Something went wrong in scheduled-jobs/SendMailAlerts.php  - ' . $result['to_email']);
}