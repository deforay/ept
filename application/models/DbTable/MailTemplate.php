<?php

class Application_Model_DbTable_MailTemplate extends Zend_Db_Table_Abstract
{
    protected $_name = 'mail_template';
    protected $_primary = 'mail_temp_id';

    /** Who a template is written for; the emails ePT sends on its own use 'participant' */
    public const AUDIENCES = ['participant', 'datamanager', 'ptcc'];

    public static function normalizeAudience($audience): string
    {
        return in_array($audience, self::AUDIENCES, true) ? $audience : 'participant';
    }

    public function updateMailTemplateDetails($params)
    {
        $data = [
            'mail_purpose' => $params['mailPurpose'],
            'audience' => self::normalizeAudience($params['audience'] ?? null),
            'from_name' => $params['adminName'],
            'mail_from' => $params['adminEmail'],
            'mail_cc' => $params['adminCc'],
            'mail_bcc' => $params['adminBcc'],
            'mail_subject' => $params['subject'],
            'mail_content' => $params['message'],
            'mail_footer' => $params['footer'],
        ];
        // A version saved twice from the same open panel has no id the
        // second time either; it is still the one version for its audience
        if (empty($params['mailId'])) {
            $existing = $this->getEmailTemplateDetails($data['mail_purpose'], $data['audience']);
            if ($existing !== null) {
                $params['mailId'] = $existing['mail_temp_id'];
            }
        }
        if (isset($params['mailId']) && $params['mailId'] != '') {
            $this->update($data, ['mail_temp_id = ?' => $params['mailId']]);
        } else {
            $this->insert($data);
        }
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog('Updated mail template - ' . $params['mailPurpose'] . ' (' . $data['audience'] . ')', 'mail-template');
    }
    public function getEmailTemplateDetails($mailPurpose, string $audience = 'participant')
    {
        return $this->fetchRow([
            'mail_purpose = ?' => $mailPurpose,
            'audience = ?' => self::normalizeAudience($audience),
        ]);
    }

    /** @return array<int, array{mail_purpose: string, audience: string}> */
    public function fetchAllEmailTemplateDetails()
    {
        return $this->fetchAll($this->select()->from($this->_name, ['mail_purpose', 'audience']))->toArray();
    }
}
