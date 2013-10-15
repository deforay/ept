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
	public function getShipments(){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select();
	}
	public function getUnshippedDistributions(){
		$disrtibutionDb = new Application_Model_DbTable_Distribution();
		return $disrtibutionDb->getUnshippedDistributions();		
	}

}

