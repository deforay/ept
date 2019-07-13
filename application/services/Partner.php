<?php

class Application_Service_Partner {
    
    public function addPartner($params){
        $partnersDb = new Application_Model_DbTable_Partners();
	return $partnersDb->addPartnerDetails($params);
    }
    
    public function getAllPartner($parameters){
        $partnersDb = new Application_Model_DbTable_Partners();
	return $partnersDb->fetchAllPartner($parameters);
    }
    
    public function getPartner($partnerId){
        $partnersDb = new Application_Model_DbTable_Partners();
	return $partnersDb->fetchPartner($partnerId);
    }
    
    public function updatePartner($params){
        $partnersDb = new Application_Model_DbTable_Partners();
	return $partnersDb->updatePartnerDetails($params);
    }
    
    public function getAllActivePartners(){
        $partnersDb = new Application_Model_DbTable_Partners();
	return $partnersDb->fetchAllActivePartners();
    }
}