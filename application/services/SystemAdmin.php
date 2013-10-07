<?php

class Application_Service_SystemAdmin {
	
	public function getAllAdmin($params){
		$adminDb = new Application_Model_DbTable_SystemAdmin();
		return $adminDb->getAllAdmin($params);
	}
	public function addSystemAdmin($params){
		$adminDb = new Application_Model_DbTable_SystemAdmin();
		return $adminDb->addSystemAdmin($params);		
	}
	public function updateSystemAdmin($params){
		$adminDb = new Application_Model_DbTable_SystemAdmin();
		return $adminDb->updateSystemAdmin($params);		
	}
	public function getSystemAdminDetails($adminId){
		$adminDb = new Application_Model_DbTable_SystemAdmin();
		return $adminDb->getSystemAdminDetails($adminId);		
	}

}

