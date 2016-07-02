<?php

class Application_Service_Publication {
    
    public function addPublication($params){
        $publicationsDb = new Application_Model_DbTable_Publications();
	return $publicationsDb->addSPublicationDetails($params);
    }
    
    public function getAllPublication($parameters){
        $publicationsDb = new Application_Model_DbTable_Publications();
	return $publicationsDb->fetchAllPublication($parameters);
    }
    
    public function getPublication($publicationId){
        $publicationsDb = new Application_Model_DbTable_Publications();
	return $publicationsDb->fetchPublication($publicationId);
    }
    
    public function updatePublication($params){
        $publicationsDb = new Application_Model_DbTable_Publications();
	return $publicationsDb->updatePublicationDetails($params);
    }
    
    public function getAllActivePublications(){
        $publicationsDb = new Application_Model_DbTable_Publications();
	return $publicationsDb->fetchAllActivePublications();
    }
}