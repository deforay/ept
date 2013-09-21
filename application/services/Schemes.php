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
								->joinLeft(array('res'=>'reference_result_eid'),'s.eid_shipment_id=res.eid_shipment_id')
								->where('res.eid_sample_id = ref.eid_sample_id')
								->where('s.eid_shipment_id = ? ',$sId)
								->where('s.participant_id = ? ',$pId)
								;
		return $db->fetchAll($sql);
		
	}
	
	public function getShipmentEid($sId,$pId){
		
		$db = new Application_Model_DbTable_ShipmentEid();
		return $db->getShipmentEid($sId,$pId);
		
	}
	
	public function updateEidResult($params){
		
	}

}

