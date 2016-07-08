<?php

include_once 'CronInit.php';

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

try {

    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $smtpTransportObj = new Zend_Mail_Transport_Smtp($conf->email->host, $conf->email->config->toArray());

    $limit = '10';
    $sQuery = $db->select()->from(array('tm' => 'temp_mail'))->where("tm.status=?",'pending')->limit($limit);
    $mailResult = $db->fetchAll($sQuery);
    
    //error_log('RUNNING CRON TO SEND MAIL PA');
    
    if (count($mailResult) > 0) {
        foreach ($mailResult as $result) {
            $alertMail = new Zend_Mail();
            $id = "temp_id=" . $result['temp_id'];
            $db->update('temp_mail',array('status'=>'not-sent'), 'temp_id=' . $result['temp_id']);
                $fromEmail = $conf->email->config->username;
                $fromFullName = "ePT System";
                $subject = $result['subject'];
                $alertMail->setBodyHtml($result['message']);
                $alertMail->setFrom($fromEmail, $fromFullName);
                $alertMail->setReplyTo($fromEmail, $fromFullName);
                
                $toArray = explode(",",$result['to_email']);
                foreach($toArray as $toId){
                    if($toId!=''){
                       $alertMail->addTo($toId); 
                    }
                }
                 if (isset($result['cc']) && trim($result['cc']) != "") {
                        $ccArray = explode(",", $result['cc']);
                        foreach ($ccArray as $ccId) {
                            if ($ccId != '') {
                                $alertMail->addCc($ccId);
                            }
                        }
                    }

                    if (isset($result['bcc']) && trim($result['bcc']) != "") {
                        $bccArray = explode(",", $result['bcc']);
                        foreach ($bccArray as $bccId) {
                            if ($bccId != '') {
                                $alertMail->addBcc($bccId);
                            }
                        }
                    }
                
                $alertMail->setSubject($subject);
                $sendResult=$alertMail->send($smtpTransportObj);
                if($sendResult==true){
                  $db->delete('temp_mail', $id);
                }
               
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in cron/SendMailAlerts.php');
}
