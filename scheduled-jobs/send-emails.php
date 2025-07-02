<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php';

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

$db = Zend_Db::factory($conf->resources->db);
Zend_Db_Table::setDefaultAdapter($db);

$smtpTransportObj = new Zend_Mail_Transport_Smtp($conf->email->host, $conf->email->config->toArray());

$limit = '100';
$sQuery = $db->select()->from(['tm' => 'temp_mail'])
    ->where("tm.status=?", 'pending')
    ->limit($limit);
$mailResult = $db->fetchAll($sQuery);

if (!empty($mailResult)) {
    foreach ($mailResult as $result) {
        try {
            $alertMail = new Zend_Mail();
            $db->update('temp_mail', ['status' => 'picked-to-process'], 'temp_id=' . $result['temp_id']);
            $fromEmail = $conf->email->config->username;
            $fromFullName = "ePT System";
            $subject = $result['subject'];
            $alertMail->setBodyHtml($result['message']);
            $alertMail->setFrom($fromEmail, $fromFullName);
            $alertMail->setReplyTo($fromEmail, $fromFullName);

            if (!isset($result['to_email']) || empty(trim($result['to_email']))) {
                continue;
            }

            $to = Application_Service_Common::validateEmails(trim($result['to_email'] ?? ''));

            if (empty($to['valid'])) {
                throw new Exception("No valid 'To' email found for temp_id={$result['temp_id']}");
            }

            foreach ($to['valid'] as $toId) {
                $alertMail->addTo($toId);
            }

            if (isset($result['cc']) && !empty(trim($result['cc']))) {
                $cc = Application_Service_Common::validateEmails(trim($result['cc']));
                if (isset($cc['valid']) && !empty($cc['valid'])) {
                    foreach ($cc['valid'] as $ccId) {
                        $alertMail->addCc($ccId);
                    }
                }
            }

            if (isset($result['bcc']) && !empty(trim($result['bcc']))) {
                $bcc = Application_Service_Common::validateEmails(trim($result['bcc']));
                if (isset($bcc['valid']) && !empty($bcc['valid'])) {
                    foreach ($bcc['valid'] as $bccId) {
                        $alertMail->addBcc($bccId);
                    }
                }
            }
            $attachments = json_decode($result['attachment'], true);
            if (!empty($attachments)) {
                foreach ($attachments as $filePath) {
                    if (file_exists($filePath)) {
                        try {
                            $attachmentContent = file_get_contents($filePath);
                            $fileName = basename($filePath);
                            $alertMail->createAttachment(
                                $attachmentContent,
                                Zend_Mime::TYPE_OCTETSTREAM, // General binary file
                                Zend_Mime::DISPOSITION_ATTACHMENT,
                                Zend_Mime::ENCODING_BASE64,
                                $fileName
                            );
                        } catch (Exception $e) {
                            error_log("Error processing attachment $filePath: " . $e->getMessage());
                        }
                    } else {
                        error_log("Attachment file does not exist: $filePath");
                    }
                }
            }


            $alertMail->setSubject($subject);
            $sendResult = $alertMail->send($smtpTransportObj);

            $db->delete('temp_mail', "temp_id=" . $result['temp_id']);
        } catch (Throwable $e) {
            $db->update('temp_mail', ['status' => 'not-sent'], 'temp_id=' . $result['temp_id']);
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
            continue;
        }
    }
}
