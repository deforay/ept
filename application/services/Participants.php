<?php
class Application_Service_Participants {
	
	public function getUsersParticipants($userSystemId = null){
		if($userSystemId == null){
			$authNameSpace = new Zend_Session_Namespace('datamanagers');
			$userSystemId = $authNameSpace->dm_id;
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
	
	public function addParticipantForDataManager($params){
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->addParticipantForDataManager($params);
	}
	
	public function updateParticipant($params){
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->updateParticipant($params);
	}
	public function getAllParticipants($params){
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->getAllParticipants($params);
	}
	
	public function getAllEnrollments($params){
		$enrollments = new Application_Model_DbTable_Enrollments();
		return $enrollments->getAllEnrollments($params);
	}
	public function getEnrollmentDetails($pid,$sid){
	    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
	    $sql = $db->select()->from(array('p'=>'participant'))
				  ->joinLeft(array('sp'=>'shipment_participant_map'),'p.participant_id=sp.participant_id')
				  ->joinLeft(array('s'=>'shipment'),'s.shipment_id=sp.shipment_id')
				  ->where("p.participant_id=".$pid);
	    return $db->fetchAll($sql);
	}
	public function getParticipantSchemes($dmId){
	    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
	    $sql = $db->select()->from(array('p'=>'participant'))
				  ->joinLeft(array('pmm'=>'participant_manager_map'),'p.participant_id=pmm.participant_id')
				  ->joinLeft(array('sp'=>'shipment_participant_map'),'p.participant_id=sp.participant_id')
				  ->joinLeft(array('s'=>'shipment'),'s.shipment_id=sp.shipment_id')
				  ->joinLeft(array('sl'=>'scheme_list'),'sl.scheme_id=s.scheme_type')
				  ->where("pmm.dm_id= ?",$dmId)
				  ->group(array("sp.participant_id","s.scheme_type"))
				  ->order("p.first_name");
	    return $db->fetchAll($sql);
	}
	public function getUnEnrolled($scheme){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$subSql = $db->select()->from(array('e'=>'enrollments'), 'participant_id')->where("scheme_id = ?", $scheme);
		$sql = $db->select()->from(array('p'=>'participant'))->where("participant_id NOT IN ?", $subSql)->where("p.status='active'")->order('first_name');
		return $db->fetchAll($sql);
	}
	public function getEnrolledBySchemeCode($scheme){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('e'=>'enrollments'), array())
				->join(array('p'=>'participant'),"p.participant_id=e.participant_id")->where("scheme_id = ?", $scheme)->where("p.status='active'")->order('first_name');
		return $db->fetchAll($sql);
	}
	
	public function getEnrolledByShipmentId($shipmentId){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('p'=>'participant'))
				->join(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array())
				->join(array('s'=>'shipment'),'sp.shipment_id=s.shipment_id',array())
				->where("s.shipment_id = ?", $shipmentId)
				->where("p.status='active'")
				->order('p.first_name');

		return $db->fetchAll($sql);
	}
	public function getSchemesByParticipantId($pid){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('p'=>'participant'),array())
				       ->joinLeft(array('e'=>'enrollments'),'e.participant_id=p.participant_id',array())
				       ->joinLeft(array('sl'=>'scheme_list'),'sl.scheme_id=e.scheme_id',array('scheme_id'))					   
				       ->where("p.participant_id = ?", $pid)
				       ->order('p.first_name');

		return $db->fetchCol($sql);
	}
	public function getUnEnrolledByShipmentId($shipmentId){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$subSql = $db->select()->from(array('p'=>'participant'),array('participant_id'))
				       ->join(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array())
					   ->join(array('s'=>'shipment'),'sp.shipment_id=s.shipment_id',array())
				       ->where("s.shipment_id = ?", $shipmentId)
				       ->where("p.status='active'");
		$sql = $db->select()->from(array('p'=>'participant'))->where("participant_id NOT IN ?", $subSql)
				       ->order('p.first_name');
		return $db->fetchAll($sql);
	}
	
	public function enrollParticipants($params){
		$enrollments = new Application_Model_DbTable_Enrollments();
		return $enrollments->enrollParticipants($params);
	}
        public function addParticipantManagerMap($params){
		$db = new Application_Model_DbTable_Participants();
		return $db->addParticipantManager($params);
	}
	public function getAffiliateList(){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		return $db->fetchAll($db->select()->from('r_participant_affiliates')->order('affiliate ASC'));
	}
	public function getEnrolledProgramsList(){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		return $db->fetchAll($db->select()->from('r_enrolled_programs')->order('enrolled_programs ASC'));
	}
	public function getSiteTypeList(){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		return $db->fetchAll($db->select()->from('r_site_type')->order('site_type ASC'));
	}
	public function getNetworkTierList(){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		return $db->fetchAll($db->select()->from('r_network_tiers')->order('network_name ASC'));
	}
	public function getAllParticipantRegion(){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('p'=>'participant'),array('p.region'))
		                  ->group('p.region')->where("p.region IS NOT NULL")->where("p.region != ''")->order("p.region");
	    return $db->fetchAll($sql);
	}
	
	public function getAllParticipantDetails($dmId){
	    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
	    $sql = $db->select()->from(array('p'=>'participant'))
	                          ->join(array('c'=>'countries'),'c.id=p.country')
				  ->joinLeft(array('pmm'=>'participant_manager_map'),'p.participant_id=pmm.participant_id')
				  ->where("pmm.dm_id= ?",$dmId)
				  ->group(array("p.participant_id"))
				  ->order("p.first_name");
	    return $db->fetchAll($sql);
	}
        
	
	public function getAllActiveParticipants(){
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->fetchAllActiveParticipants();
	}
	
	public function getSchemeWiseParticipants($schemeType){
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->getSchemeWiseParticipants($schemeType);
	}
	
	public function getShipmentEnrollement($parameters){
		$db = new Application_Model_DbTable_Participants();
		$db->getEnrolledByShipmentDetails($parameters);
	}
	
	public function getShipmentUnEnrollements($parameters){
		$db = new Application_Model_DbTable_Participants();
		$db->getUnEnrolledByShipments($parameters);
	}
        public function getShipmentRespondedParticipants($params){
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->getShipmentRespondedParticipants($params);
	}
	public function getShipmentNotRespondedParticipants($params){
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->getShipmentNotRespondedParticipants($params);
	}
        public function getShipmentNotEnrolledParticipants($params){
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->getShipmentNotEnrolledParticipants($params);
	}
}
