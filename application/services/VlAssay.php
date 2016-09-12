<?php

class Application_Service_VlAssay {
    
    public function addVlAssay($params){
        $vlAssayDb = new Application_Model_DbTable_VlAssay();
	return $vlAssayDb->addVlAssayDetails($params);
    }
    
    public function getAllVlAssay($parameters){
        $vlAssayDb = new Application_Model_DbTable_VlAssay();
	return $vlAssayDb->fetchAllVlAssay($parameters);
    }
    
    public function getVlAssay($id){
        $vlAssayDb = new Application_Model_DbTable_VlAssay();
	return $vlAssayDb->fetchVlAssay($id);
    }
    
    public function updateVlAssay($params){
        $vlAssayDb = new Application_Model_DbTable_VlAssay();
	return $vlAssayDb->updateVlAssayDetails($params);
    }
}