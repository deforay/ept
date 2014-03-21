<?php

class Application_Service_Distribution {
	
	public function getAllDistributions($params){
		$disrtibutionDb = new Application_Model_DbTable_Distribution();
		return $disrtibutionDb->getAllDistributions($params);
	}
	public function addDistribution($params){
		$disrtibutionDb = new Application_Model_DbTable_Distribution();
		return $disrtibutionDb->addDistribution($params);		
	}
	public function getDistribution($did){
		$disrtibutionDb = new Application_Model_DbTable_Distribution();
		return $disrtibutionDb->getDistribution($did);		
	}
	public function updateDistribution($params){
		$disrtibutionDb = new Application_Model_DbTable_Distribution();
		return $disrtibutionDb->updateDistribution($params);		
	}
	public function getDistributionDates(){
		$disrtibutionDb = new Application_Model_DbTable_Distribution();
		return $disrtibutionDb->getDistributionDates();		
	}
	public function getShipments($distroId){
	    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('s'=>'shipment'))
				->where("distribution_id = ?",$distroId);
										  
	    return $db->fetchAll($sql);
	}
	
	public function getUnshippedDistributions(){
		$disrtibutionDb = new Application_Model_DbTable_Distribution();
		return $disrtibutionDb->getUnshippedDistributions();		
	}
	
	public function updateDistributionStatus($distributionId,$status){
		$disrtibutionDb = new Application_Model_DbTable_Distribution();
		return $disrtibutionDb->updateDistributionStatus($distributionId,$status);		
	}
	
	public function shipDistribution($distributionId){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$db->beginTransaction();
		try{
			$shipmentDb = new Application_Model_DbTable_Shipments();
			$shipmentDb->updateShipmentStatusByDistribution($distributionId,"shipped");		
			
			$disrtibutionDb = new Application_Model_DbTable_Distribution();		
			$disrtibutionDb->updateDistributionStatus($distributionId,"shipped");
			$db->commit();
			return "PT Event shipped!";
		}catch(Exception $e){
			$db->rollBack();
			error_log($e->getMessage());
			error_log($e->getTraceAsString());
			return "Unable to ship. Please try again later or contact system admin for help";		
		}
	}
	
	public function getAllDistributionReports($parameters){
		$disrtibutionDb = new Application_Model_DbTable_Distribution();
		return $disrtibutionDb->getAllDistributionReports($parameters);
	}
	public function getAllDistributionStatus(){
		$disrtibutionDb = new Application_Model_DbTable_Distribution();
		return $disrtibutionDb->getAllDistributionStatusDetails();		
	}

}

