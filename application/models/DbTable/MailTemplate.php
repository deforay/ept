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

    /** Form labels of the fields an admin edits, for the audit log. */
    private const AUDIT_LABELS = [
        'from_name' => 'From Name',
        'mail_from' => 'From Email',
        'mail_cc' => 'Cc',
        'mail_bcc' => 'Bcc',
        'mail_subject' => 'Subject',
        'mail_content' => 'Message',
        'mail_footer' => 'Footer',
    ];

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
        $before = null;
        if (isset($params['mailId']) && $params['mailId'] != '') {
            $before = $this->auditFields($params['mailId']);
            $this->update($data, ['mail_temp_id = ?' => $params['mailId']]);
            $templateId = $params['mailId'];
        } else {
            $templateId = $this->insert($data);
        }
        $extra = [
            'entity' => [
                'type' => 'mail-template',
                'id' => $templateId,
                'label' => $data['mail_purpose'] . ' (' . $data['audience'] . ')',
            ],
        ];
        if ($before !== null) {
            $extra['changes'] = Application_Model_DbTable_AuditLog::changes($before, $this->auditFields($templateId), self::AUDIT_LABELS);
        } else {
            $extra['facts'] = ['Written for' => $data['audience'], 'Subject' => $data['mail_subject']];
        }
        Application_Model_DbTable_AuditLog::record('Updated mail template - ' . $params['mailPurpose'] . ' (' . $data['audience'] . ')', 'mail-template', $extra);
    }

    /** @return array<string, string>|null */
    private function auditFields($templateId): ?array
    {
        try {
            $row = $this->getAdapter()->fetchRow(
                $this->getAdapter()->select()
                    ->from($this->_name, array_keys(self::AUDIT_LABELS))
                    ->where('mail_temp_id = ?', $templateId)
            );
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
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
