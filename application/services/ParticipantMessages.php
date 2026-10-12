<?php

class Application_Service_ParticipantMessages
{
    protected $tempUploadDirectory;
    public function __construct()
    {
        $this->tempUploadDirectory = realpath(TEMP_UPLOAD_PATH);
    }

    public function addParticipantMessage($params)
    {
        $userDb = new Application_Model_DbTable_ParticipantMessages();
        return $userDb->addParticipantMessage($params);
    }

    /**
     * Best real mailbox to reply to for a data manager. primary_email is often a
     * login ID (e.g. PTID-12345) rather than an address, so fall back to the DM's
     * secondary email, then to the email of a lab they manage.
     */
    public function getReplyEmailForDm($dmId): ?string
    {
        if (empty($dmId)) {
            return null;
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        // login_only = generated login address; bounced/invalid-domain ones won't reach
        // anyone. invalid_syntax is not skipped: a valid comma-separated list gets that
        // status, and the per-address check below handles real syntax errors.
        $unusable = ['login_only', 'hard_bounce', 'invalid_domain'];
        $candidates = [];
        $dm = $db->fetchRow(
            $db->select()->from('data_manager', ['primary_email', 'primary_email_status', 'secondary_email', 'secondary_email_status'])
                ->where('dm_id = ?', $dmId)
        );
        if ($dm) {
            $candidates[] = [$dm['primary_email'], $dm['primary_email_status']];
            $candidates[] = [$dm['secondary_email'], $dm['secondary_email_status']];
        }

        $labs = $db->fetchAll(
            $db->select()->from(['pmm' => 'participant_manager_map'], [])
                ->join(['p' => 'participant'], 'p.participant_id = pmm.participant_id', ['email', 'email_status', 'additional_email', 'additional_email_status'])
                ->where('pmm.dm_id = ?', $dmId)
        );
        foreach ($labs as $lab) {
            $candidates[] = [$lab['email'], $lab['email_status']];
            $candidates[] = [$lab['additional_email'], $lab['additional_email_status']];
        }

        foreach ($candidates as [$candidate, $status]) {
            if (in_array($status, $unusable, true)) {
                continue;
            }
            // Fields sometimes hold several addresses separated by , or ;
            foreach (preg_split('/[;,]+/', (string) $candidate) as $part) {
                $valid = Application_Service_Common::validateEmail($part);
                if ($valid) {
                    return $valid;
                }
            }
        }
        return null;
    }

    public function getParticipantMessage($params)
    {
        $userDb = new Application_Model_DbTable_ParticipantMessages();
        return $userDb->getParticipantMessage($params);
    }

    public function getParticipantMessageById($pmId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['pm' => 'participant_messages'])
            ->where('id = ?', $pmId);
        $res = $db->fetchAll($sql);
        return $res[0];
    }
}
