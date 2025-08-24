<?php

class Application_Model_DbTable_TempMail extends Zend_Db_Table_Abstract
{
    protected $_name    = 'temp_mail';
    protected $_primary = 'temp_id';

    public function insertTempMailDetails($to, $cc, $bcc, $subject, $message, $fromMail = null, $fromName = null, $attachments = [])
    {
        if (trim((string)$message) === '') {
            return false;
        }

        // Normalize recipients using the parseRecipients helper
        $recips = Application_Service_Common::parseRecipients(
            (string)$to,
            $cc !== null ? (string)$cc : null,
            $bcc !== null ? (string)$bcc : null
        );

        // Must have at least one valid TO
        if (empty($recips['to'])) {
            // Log invalids (if any) to help ops
            if (!empty($recips['invalid'])) {
                error_log("TempMail insert rejected: no valid TO. Invalid: " . implode(', ', $recips['invalid']));
            }
            return false;
        }

        // Attachments: normalize to array of existing files
        $files = [];
        if (!empty($attachments)) {
            $list = is_array($attachments) ? $attachments : [$attachments];
            foreach ($list as $path) {
                if (!is_string($path) || !file_exists($path)) {
                    throw new Exception("Invalid attachment: " . (string)$path);
                }
                $files[] = $path;
            }
        }

        // From: fall back to config if not provided
        $conf     = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $fromMail = Application_Service_Common::validateEmail((string)($fromMail ?: $conf->email->config->username)) ?: $conf->email->config->username;
        $fromName = $fromName ?: 'ePT Support';

        // Prepare row
        $row = [
            'from_mail'      => $fromMail,
            'from_full_name' => $fromName,
            'to_email'       => implode(',', $recips['to']),
            'cc'             => !empty($recips['cc'])  ? implode(',', $recips['cc'])  : '',
            'bcc'            => !empty($recips['bcc']) ? implode(',', $recips['bcc']) : '',
            'subject'        => (string)$subject,
            'message'        => (string)$message,
            'attachment'     => $files ? json_encode($files, JSON_UNESCAPED_SLASHES) : '',
            'status'         => 'pending',
        ];

        return $this->insert($row);
    }

    public function updateTempMailStatus($id, $status = 'picked-to-process')
    {
        return $this->update(
            ['status' => $status],
            $this->getAdapter()->quoteInto('temp_id = ?', (int)$id)
        );
    }

    public function deleteTempMail($id)
    {
        return $this->delete($this->getAdapter()->quoteInto('temp_id = ?', (int)$id));
    }
}
