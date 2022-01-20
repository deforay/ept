<?php

class Application_Model_DbTable_AuditLog extends Zend_Db_Table_Abstract
{
    protected $_name = 'audit_log';
    protected $_primary = 'audit_log_id';

    public function addNewAuditLog($stateMent, $type = null)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        if (isset($stateMent) && $stateMent != "") {
            return $this->insert(array(
                "statement" => $stateMent,
                "created_by" => $authNameSpace->primary_email,
                "created_on" => new Zend_Db_Expr('now()'),
                "type" => $type
            ));
        }
    }
}
