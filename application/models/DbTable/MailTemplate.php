<?php

class Application_Model_DbTable_MailTemplate extends Zend_Db_Table_Abstract
{

    protected $_name = 'mail_template';
    protected $_primary = 'mail_temp_id';

    public function updateMailTemplateDetails($params)
    {
        $data = array(
            'mail_purpose' => $params['mailPurpose'],
            'from_name' => $params['adminName'],
            'mail_from' => $params['adminEmail'],
            'mail_cc' => $params['adminCc'],
            'mail_bcc' => $params['adminBcc'],
            'mail_subject' => $params['subject'],
            'mail_content' => $params['message'],
            'mail_footer' => $params['footer']
        );
        if (isset($params['mailId']) && $params['mailId'] != '') {
            $this->update($data, "mail_temp_id=" . $params['mailId']);
        } else {
            $this->insert($data);
        }
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog("Updated a mail template - " . $params['mailPurpose'], "mail-template");
    }
    public function getEmailTemplateDetails($mailPurpose)
    {
        return $this->fetchRow("mail_purpose='$mailPurpose'");
    }

    public function fetchAllEmailTemplateDetails()
    {
        return $this->fetchAll($this->select()->from($this->_name, 'mail_purpose'))->toArray();
    }
}
