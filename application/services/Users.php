<?php

class Application_Service_Users {

    public function addUser($params){
        // TO DO : Add User
    }
    
    public function updateUser($params){
        $userDb = new Application_Model_DbTable_Users();
        return $userDb->updateUser($params);
    }
    
    public function getUserInfo($userId = null){

	$userDb = new Application_Model_DbTable_Users();
        if($userId == null){
            $authNameSpace = new Zend_Session_Namespace('Zend_Auth');
            $userId = $authNameSpace->UserID;
        }
	return $userDb->getUserDetails($userId);
    }

}

