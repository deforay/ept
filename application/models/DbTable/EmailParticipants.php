<?php

class Application_Model_DbTable_EmailParticipants extends Zend_Db_Table_Abstract {
    protected $_name = 'email_participants';
    protected $_primary = 'id';

    public function saveEmailParticipants($data){
        $authNameSpace = new Zend_Session_Namespace('administrators');
        if (isset($data['subject']) && !empty($data['subject'])) {
            return $this->insert(array(
                "subject" => $data['subject'] ?? null,
                "content" => $data['message'] ?? null,
                "receivers" => $data['email'] ?? null,
                "shipment_code" => $data['scode'] ?? null,
                "initiated_by" => $authNameSpace->primary_email,
                "date_initiated" => new Zend_Db_Expr('now()')
            ));
        }
    }
}