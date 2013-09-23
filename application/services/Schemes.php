<?php

class Application_Service_Schemes {

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
								->joinLeft(array('res'=>'response_result_eid'),'s.eid_shipment_id=res.eid_shipment_id')
								->where('res.eid_sample_id = ref.eid_sample_id')
								->where('s.eid_shipment_id = ? ',$sId)
								->where('s.participant_id = ? ',$pId);
		return $db->fetchAll($sql);
		
	}
	
	public function getShipmentEid($sId,$pId){
		
		$db = new Application_Model_DbTable_ShipmentEid();
		return $db->getShipmentEid($sId,$pId);
		
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

}

