<?php

class Application_Model_DbTable_Participants extends Zend_Db_Table_Abstract
{

    protected $_name = 'participant';
    protected $_primary = 'ParticipantID';


    public function getParticipantsByUserSystemId($userSystemId){
        return $this->fetchAll("UserSystemID = $userSystemId");
    }

    public function getParticipant($partSysId){
        return $this->fetchRow("ParticipantSystemID = '".$partSysId."'");
    }

    public function updateParticipant($params){
			$authNameSpace = new Zend_Session_Namespace('Zend_Auth');
			 
        $data = array(
                      'ParticipantID'=>$params['pid'],
                      'ParticipantFName'=>$params['pfname'],
                      'ParticipantLName'=>$params['plname'],
                      'ParticipantMobile'=>$params['pphone2'],
                      'ParticipantPhone'=>$params['pphone1'],
                      'ParticipanteMail'=>$params['pemail'],
                      'ParticipantAffiliation'=>$params['partAff'],
                      'Updated_on'=>new Zend_Db_Expr('now()'),
                      'Updated_by'=>$authNameSpace->UserID,
                      );
        return $this->update($data,"ParticipantSystemID = '".$params['PartSysID']."'");
    }

}

