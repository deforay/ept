<?php

class Application_Model_DbTable_Announcement extends Zend_Db_Table_Abstract
{
    protected $_name = 'announcements_notification';

    public function saveNewAnnouncement($params)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        return $this->insert(array(
            'subject'       => $params['subject'],
            'message'       => $params['message'],
            'participants'  => implode(",", $params['participants']),
            'created_on'    => new Zend_Db_Expr('now()'),
            'created_by'    => $authNameSpace->admin_id
        ));
    }
}
