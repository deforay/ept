<?php

class Application_Model_DbTable_TempMail extends Zend_Db_Table_Abstract
{
    protected $_name    = 'temp_mail';
    protected $_primary = 'temp_id';

    public function insertTempMailDetails(
        $to,
        $cc,
        $bcc,
        $subject,
        $message,
        $fromMail = null,
        $fromName = null,
        $attachments = [],
        $replyTo = null
    ) {
        if (trim((string)$message) === '') {
            return false;
        }

        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

        // Limits (overridable via application.ini)
        // email.limits.perAttachmentMb = 15
        // email.limits.totalAttachmentsMb = 22
        $perAttachMb    = (int)($conf->email->limits->perAttachmentMb ?? 15);
        $totalAttachMb  = (int)($conf->email->limits->totalAttachmentsMb ?? 22);
        $PER_BYTES      = $perAttachMb   * 1024 * 1024;
        $TOTAL_BYTES    = $totalAttachMb * 1024 * 1024;

        // Recipients
        $recips = Application_Service_Common::parseRecipients(
            (string)$to,
            $cc !== null  ? (string)$cc  : null,
            $bcc !== null ? (string)$bcc : null
        );
        if (empty($recips['to'])) {
            if (!empty($recips['invalid'])) {
                error_log("TempMail insert rejected: no valid TO. Invalid: " . implode(', ', $recips['invalid']));
            }
            return false;
        }

        // From + reply-to
        $fromMail = (string)($fromMail ?: $conf->email->config->username);
        $fromMail = Application_Service_Common::validateEmail($fromMail) ?: $conf->email->config->username;
        $fromName = $fromName ?: 'ePT Support';

        $replyToRaw   = (string)($replyTo ?? $fromMail);
        $replyToFirst = trim(preg_split('/[;,]+/', $replyToRaw)[0] ?? '');
        $replyToValid = $replyToFirst && Application_Service_Common::validateEmail($replyToFirst)
            ? $replyToFirst
            : $fromMail;

        // Attachments: SKIP invalid/oversize; enforce cumulative cap
        $files = [];
        $total = 0;
        if (!empty($attachments)) {
            $list = is_array($attachments) ? $attachments : [$attachments];
            foreach ($list as $path) {
                if (!is_string($path) || !file_exists($path)) {
                    error_log("Attachment skipped (not found): " . (string)$path);
                    continue;
                }
                $size = @filesize($path);
                if ($size === false) {
                    error_log("Attachment skipped (size unreadable): " . (string)$path);
                    continue;
                }
                if ($size > $PER_BYTES) {
                    error_log("Attachment skipped (per-file limit {$perAttachMb}MB): " . basename($path));
                    continue;
                }
                if ($total + $size > $TOTAL_BYTES) {
                    error_log("Attachment skipped (total limit {$totalAttachMb}MB would be exceeded): " . basename($path));
                    continue;
                }
                $files[] = $path;
                $total  += $size;
            }
        }

        // Row
        $row = [
            'from_mail'      => $fromMail,
            'from_full_name' => $fromName,
            'reply_to'       => $replyToValid,
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
