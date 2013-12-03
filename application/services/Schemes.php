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
	
	public function getDtsSamples($sId,$pId){
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('ref'=>'reference_result_dts'))
								->join(array('s'=>'shipment'),'s.shipment_id=ref.shipment_id')
								->join(array('sp'=>'shipment_participant_map'),'s.shipment_id=sp.shipment_id')
								->joinLeft(array('res'=>'response_result_dts'),'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id',array('test_kit_name_1',
																													   'lot_no_1',
																													   'exp_date_1',
																													   'test_result_1',
																													   'test_kit_name_2',
																													   'lot_no_2',
																													   'exp_date_2',
																													   'test_result_2',
																													   'test_kit_name_3',
																													   'lot_no_3',
																													   'exp_date_3',
																													   'test_result_3',
																													   'reported_result'
																													   ))
								->where('sp.shipment_id = ? ',$sId)
								->where('sp.participant_id = ? ',$pId);
		return $db->fetchAll($sql);
		
	}	
	public function getEidSamples($sId,$pId){
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('ref'=>'reference_result_eid'))
								->join(array('s'=>'shipment'),'s.shipment_id=ref.shipment_id')
								->join(array('sp'=>'shipment_participant_map'),'s.shipment_id=sp.shipment_id')
								->joinLeft(array('res'=>'response_result_eid'),'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id',array('reported_result','hiv_ct_od','ic_qs'))
								->where('sp.shipment_id = ? ',$sId)
								->where('sp.participant_id = ? ',$pId);
		return $db->fetchAll($sql);
		
	}	
	public function getVlSamples($sId,$pId){
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('ref'=>'reference_result_vl'))
								->join(array('s'=>'shipment'),'s.shipment_id=ref.shipment_id')
								->join(array('sp'=>'shipment_participant_map'),'s.shipment_id=sp.shipment_id')
								->joinLeft(array('res'=>'response_result_vl'),'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('reported_viral_load'))
								->where('sp.shipment_id = ? ',$sId)
								->where('sp.participant_id = ? ',$pId);
		return $db->fetchAll($sql);
		
	}
	
	public function getShipmentData($sId,$pId){
		
		$db = new Application_Model_DbTable_Shipments();
		return $db->getShipmentData($sId,$pId);
		
	}	
	//public function getShipmentVl($sId,$pId){
	//	
	//	$db = new Application_Model_DbTable_ShipmentVl();
	//	return $db->getShipmentVl($sId,$pId);
	//	
	//}
	

	public function getSchemeControls($schemeId){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		return $db->fetchAll($db->select()->from('r_control')->where("for_scheme='$schemeId'"));
	}

	public function getSchemeEvaluationComments($schemeId){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		return $db->fetchAll($db->select()->from('r_evaluation_comments')->where("scheme='$schemeId'"));
	}
	
	public function getPossibleResults($schemeId){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		return $db->fetchAll($db->select()->from('r_possibleresult')->where("scheme_id='$schemeId'"));
	}

}

