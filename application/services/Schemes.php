<?php

class Application_Service_Schemes {
	
	
	public function getAllSchemes(){
		$schemeListDb = new Application_Model_DbTable_SchemeList();
		return $schemeListDb->getAllSchemes();
	}

	public function getEidExtractionAssay(){
		
		$db = new Application_Model_DbTable_EidExtractionAssay();
		return $db->fetchAll();
		
	}	
	public function getEidDetectionAssay(){
		
		$db = new Application_Model_DbTable_EidDetectionAssay();
		return $db->fetchAll();		
		
	}	
	public function getVlAssay(){
		
		$db = new Application_Model_DbTable_VlAssay();
		return $db->fetchAll();		
		
	}
	
	public function getEidSamples($sId,$pId){
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('ref'=>'reference_result_eid'))
								->join(array('s'=>'shipment_eid'),'s.eid_shipment_id=ref.eid_shipment_id')
								->joinLeft(array('res'=>'response_result_eid'),'res.eid_sample_id = ref.eid_sample_id',array('reported_result','hiv_ct_od','ic_qs'))
								->where('s.eid_shipment_id = ? ',$sId)
								->where('s.participant_id = ? ',$pId);
		return $db->fetchAll($sql);
		
	}	
	public function getVlSamples($sId,$pId){
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('ref'=>'reference_result_vl'))
								->join(array('s'=>'shipment_vl'),'s.vl_shipment_id=ref.vl_shipment_id')
								->joinLeft(array('res'=>'response_result_vl'),'res.vl_sample_id = ref.vl_sample_id', array('reported_viral_load'))
								->where('s.vl_shipment_id = ? ',$sId)
								->where('s.participant_id = ? ',$pId);
		return $db->fetchAll($sql);
		
	}
	
	public function getShipmentEid($sId,$pId){
		
		$db = new Application_Model_DbTable_ShipmentEid();
		return $db->getShipmentEid($sId,$pId);
		
	}	
	public function getShipmentVl($sId,$pId){
		
		$db = new Application_Model_DbTable_ShipmentVl();
		return $db->getShipmentVl($sId,$pId);
		
	}
	
	public function updateEidResults($params){
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		
		$db->beginTransaction();
		try {
			$eidShipmentDb = new Application_Model_DbTable_ShipmentEid();
			$authNameSpace = new Zend_Session_Namespace('Zend_Auth');
			$data = array(
						  "shipment_receipt_date"=>Pt_Commons_General::dateFormat($params['receiptDate']),
						  "shipment_test_date"=>Pt_Commons_General::dateFormat($params['testDate']),
						  "sample_rehydration_date"=>Pt_Commons_General::dateFormat($params['sampleRehydrationDate']),
						  "extraction_assay"=>$params['extractionAssay'],
						  "detection_assay"=>$params['detectionAssay'],
						  "supervisor_approval"=>$params['supervisorApproval'],
						  "participant_supervisor"=>$params['participantSupervisor'],
						  "user_comment"=>$params['userComments'],
						  "updated_by_user"=>$authNameSpace->UserSystemID,
						  "updated_on_user"=>new Zend_Db_Expr('now()')
						  );
			
			$noOfRowsAffected = $eidShipmentDb->updateShipmentEid($data,$params['hdshipId'], $params['hdparticipantId']);
			
			$eidResponseDb = new Application_Model_DbTable_ResponseEid();
			$eidResponseDb->updateResults($params);
			$db->commit();
		 
		} catch (Exception $e) {
			// If any of the queries failed and threw an exception,
			// we want to roll back the whole transaction, reversing
			// changes made in the transaction, even those that succeeded.
			// Thus all changes are committed together, or none are.
			$db->rollBack();
			error_log($e->getMessage());
		}
		
	}
	public function updateVlResults($params){
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		
		$db->beginTransaction();
		try {
			$vlShipmentDb = new Application_Model_DbTable_ShipmentVl();
			$authNameSpace = new Zend_Session_Namespace('Zend_Auth');
			$data = array(
						  "shipment_receipt_date"=>Pt_Commons_General::dateFormat($params['receiptDate']),
						  "shipment_test_date"=>Pt_Commons_General::dateFormat($params['testDate']),
						  "sample_rehydration_date"=>Pt_Commons_General::dateFormat($params['sampleRehydrationDate']),
						  "vl_assay"=>$params['vlAssay'],
						  "assay_lot_number"=>$params['assayLotNumber'],
						  "assay_expiration_date"=>Pt_Commons_General::dateFormat($params['assayExpirationDate']),
						  "specimen_volume"=>$params['specimenVolume'],
						  "supervisor_approval"=>$params['supervisorApproval'],
						  "participant_supervisor"=>$params['participantSupervisor'],
						  "user_comment"=>$params['userComments'],
						  "updated_by_user"=>$authNameSpace->UserSystemID,
						  "updated_on_user"=>new Zend_Db_Expr('now()')
						  );
			
			$noOfRowsAffected = $vlShipmentDb->updateShipmentVl($data,$params['hdshipId'], $params['hdparticipantId']);
			
			$eidResponseDb = new Application_Model_DbTable_ResponseVl();
			$eidResponseDb->updateResults($params);
			$db->commit();
		 
		} catch (Exception $e) {
			// If any of the queries failed and threw an exception,
			// we want to roll back the whole transaction, reversing
			// changes made in the transaction, even those that succeeded.
			// Thus all changes are committed together, or none are.
			$db->rollBack();
			error_log($e->getMessage());
		}
		
	}
	
	public function getSchemeControls($schemeId){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		return $db->fetchAll($db->select()->from('r_control')->where("for_scheme='$schemeId'"));
	}

}

