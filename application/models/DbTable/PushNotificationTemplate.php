<?php

class Application_Model_DbTable_PushNotificationTemplate extends Zend_Db_Table_Abstract
{

    protected $_name = 'push_notification_template';
    protected $_primary = 'id';

    public function updatePushTemplateDetails($params)
    {
        $data = array(
            'notify_title'  => $params['title'],
            'notify_body'   => $params['msgBody'],
            'data_msg'      => $params['dataMsg'],
            'icon'          => $params['icon'],
        );
        if (isset($params['purpose']) && $params['purpose'] != '') {
            return $this->update($data, "purpose='" . $params['purpose'] . "'");
        } else {
            $data['purpose'] = str_replace(" ", "-", $params['title']);
            return $this->insert($data);
        }
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog("Updated a push notification template " . $params['purpose'], "mail-template");
    }
    public function fetchPushTemplateByPurpose($purpose)
    {
        return $this->fetchRow("purpose='$purpose'");
    }
}
