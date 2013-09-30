<?php

class Application_Service_Participants {
	
	public function getUsersParticipants($userSystemId = null){
		if($userSystemId == null){
			$authNameSpace = new Zend_Session_Namespace('Zend_Auth');
			$userSystemId = $authNameSpace->UserSystemID;
		}
		
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->getParticipantsByUserSystemId($userSystemId);
		
	}
	
	public function getParticipantDetails($partSysId){
		
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->getParticipant($partSysId);
		
	}
	
	public function addParticipant($params){
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->addParticipant($params);
    }
	public function updateParticipant($params){
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->updateParticipant($params);
	}
	public function getAllParticipants($params){
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->getAllParticipants($params);
	}

}
