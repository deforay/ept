<?php

include_once 'CronInit.php';
$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

$db = Zend_Db::factory($conf->resources->db);
Zend_Db_Table::setDefaultAdapter($db);
$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$smtpTransportObj = new Zend_Mail_Transport_Smtp($conf->email->host, $conf->email->config->toArray());

$limit = '10';
$sQuery = $db->select()->from(array('tm' => 'temp_mail'))->limit($limit);
$mailResult = $db->fetchAll($sQuery);
$tempMail = new Application_Model_DbTable_TempMail();
$deliveryCount = 0;
$tempId = "";

if (count($mailResult) > 0) {
    foreach ($mailResult as $result) {
        try {
            $alertMail = new Zend_Mail();
            $id = "temp_id=" . $result['temp_id'];
            $fromEmail = $result['from_mail'];
            $fromFullName = $result['from_full_name'];
            $subject = $result['subject'];

            $alertMail->setBodyHtml(html_entity_decode($result['message'], ENT_QUOTES, 'UTF-8'));
            $alertMail->setFrom($fromEmail, $fromFullName);
            $alertMail->setReplyTo($fromEmail, $fromFullName);

            $toArray = explode(",", $result['to_email']);
            foreach ($toArray as $toId) {
                $alertMail->addTo($toId);
            }

            if (trim($result['cc']) != "") {
                $ccArray = explode(",", $result['cc']);
                foreach ($ccArray as $ccId) {
                    //   error_log($ccId);
                    $alertMail->addCc($ccId);
                }
            }

            if (trim($result['bcc']) != "") {
                $bccArray = explode(",", $result['bcc']);
                foreach ($bccArray as $bccId) {
                    $alertMail->addBcc($bccId);
                }
            }
            //$tempMailObj = new Application_Model_DbTable_TempMail();
            //$deliveryCount=$result['delivery_count'];
            //$tempId=$result['temp_id'];
            //$deliveryCount= (int)$deliveryCount+1;
            //$tempMailObj->update(array("delivery_count"=>$deliveryCount),'temp_id='.$tempId);
            //
            //if($result['delivery_count']>3){
            //     $db->delete('temp_mail', $id);
            //     error_log("The unsuccessfull Mail delivery and temp_id is: $tempId");
            //}

            $alertMail->setSubject($subject);
            $alertMail->send($smtpTransportObj);
            $db->delete('temp_mail', $id);
        } catch (Exception $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
            continue;
            error_log('whoops! Something went wrong in cron/SendMailAlerts.php');
        }
    }
}
