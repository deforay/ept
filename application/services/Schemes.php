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
	

	public function getSchemeControls($schemeId){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		return $db->fetchAll($db->select()->from('r_control')->where("for_scheme='$schemeId'"));
	}
	
	public function getPossibleResults($schemeId){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		return $db->fetchAll($db->select()->from('r_possibleresult')->where("SchemeCode='$schemeId'"));
	}

}

