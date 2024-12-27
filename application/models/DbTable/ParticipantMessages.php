<?php

class Application_Model_DbTable_ParticipantMessages extends Zend_Db_Table_Abstract
{
    protected $_name = 'participant_messages';
    protected $_primary = 'id';

    public function addParticipantMessage($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $common = new Application_Service_Common();
        $attachedFile = null;
        if (isset($params['subject']) && $params['subject'] != "") {
            $attachedFile = null;
            if (isset($_FILES['attachment']['name']) && !empty($_FILES['attachment']['name'])) {
                $fileNameSanitized = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['attachment']['name']);
                $fileNameSanitized = str_replace(" ", "-", $fileNameSanitized);
                $pathPrefix = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'mail-attachments';
                if (!is_dir($pathPrefix)) {
                    mkdir($pathPrefix, 0777, true);
                }
                $extension = strtolower(pathinfo($pathPrefix . DIRECTORY_SEPARATOR . $fileNameSanitized, PATHINFO_EXTENSION));
                $fileName =   $common->generateRandomString(4) . '.' . $extension;
                if (move_uploaded_file($_FILES['attachment']["tmp_name"], $pathPrefix . DIRECTORY_SEPARATOR . $fileName)) {
                    $attachedFile = $fileName;
                    $attachedFilePath = $pathPrefix . DIRECTORY_SEPARATOR . $fileName; // Full file path
                }
            }
            $data =  [
                "subject" => $params['subject'],
                "message" => $params['message'],
                "status" => 'pending',
                "attached_file" => $attachedFile,
                "created_at" => new Zend_Db_Expr('now()')
            ];

            $db->insert('participant_messages', $data);
            $insertId = $db->lastInsertId();
            $message = $params['message'];
            $subject = $params['subject'];
            $toMail = Application_Service_Common::getConfig('admin_email');
            $attachedFile = $attachedFilePath;
            //$fromName = Application_Service_Common::getConfig('admin-name');
            // Send email with the attachment
            $emailSent = $common->sendMail($toMail, null, null, $subject, $message, null, "ePT Admin", [$attachedFilePath]);

            // If the email was sent successfully, update the status in the database
            if ($emailSent) {
                $db->update(
                    'participant_messages',
                    ['status' => 'sent'],  // Update status to 'sent'
                    ['id = ?' => $insertId]  // Only update the record with the last inserted ID
                );
                $response['status'] = 'success';
            } else {
                // If the email failed, you can update the status to 'failed' or leave it as 'pending'
                $db->update(
                    'participant_messages',
                    ['status' => 'failed'],  // Update status to 'failed' if email sending failed
                    ['id = ?' => $insertId]  // Only update the record with the last inserted ID
                );
                $response['status'] = 'failure';
            }

            return $response;
        }
    }
}
