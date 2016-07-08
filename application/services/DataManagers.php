<?php

class Application_Service_DataManagers {

    public function addUser($params){
	$userDb = new Application_Model_DbTable_DataManagers();
        return $userDb->addUser($params);
    }
    
    public function updateUser($params){
        $userDb = new Application_Model_DbTable_DataManagers();
        return $userDb->updateUser($params);
    }
    
    public function updateLastLogin($dmId){
        $userDb = new Application_Model_DbTable_DataManagers();
        return $userDb->updateLastLogin($dmId);
    }
	
	
    public function getAllUsers($params){
        $userDb = new Application_Model_DbTable_DataManagers();
        return $userDb->getAllUsers($params);
    }
    
    public function getUserInfo($userId = null){

	$userDb = new Application_Model_DbTable_DataManagers();
        if($userId == null){
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $userId = $authNameSpace->UserID;
        }
		return $userDb->getUserDetails($userId);
    }    
    public function getUserInfoBySystemId($userSystemId = null){

	$userDb = new Application_Model_DbTable_DataManagers();
        if($userSystemId == null){
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $userSystemId = $authNameSpace->dm_id;
        }
	return $userDb->getUserDetailsBySystemId($userSystemId);
    }
	
    public function resetPassword($email){
	$userDb = new Application_Model_DbTable_DataManagers();
	$newPassword = $userDb->resetPasswordForEmail($email);
	$sessionAlert = new Zend_Session_Namespace('alertSpace');
	if($newPassword != false){
		$common = new Application_Service_Common();
		$message = "Hi,<br/> We have reset your password. Please use <strong>$newPassword</strong> as your new password.<br/><small>This is a system generated email. Please do not reply.</small>";
		$fromMail = Application_Service_Common::getConfig('admin_email');
		$fromName = Application_Service_Common::getConfig('admin-name');
		$common->sendMail($email,null,null,"Password Reset - e-PT",$message,$fromMail,$fromName);
		$sessionAlert->message = "Your password has been reset. Please check your registered mail id for the instructions.";
		$sessionAlert->status = "success";
	}else{
		$sessionAlert->message = "Sorry, we could not reset your password. Please make sure that you enter your registered primary email id";
		$sessionAlert->status = "failure";
	}
    }
	
	
    public function getDataManagerList(){
	    $userDb = new Application_Model_DbTable_DataManagers();
	    return $userDb->getAllDataManagers();
    }
	
    public function getParticipantDatamanagerList($participantId){
	$db = Zend_Db_Table_Abstract::getDefaultAdapter();
	return $db->fetchAll($db->select()->from('participant_manager_map')->where("participant_id= ?",$participantId));
    }
	
    public function getDatamanagerParticipantList($datamanagerId){
	$db = Zend_Db_Table_Abstract::getDefaultAdapter();
	return $db->fetchAll($db->select()->from('participant_manager_map')->where("dm_id= ?",$datamanagerId)->group('participant_id'));
    }
	
    public function changePassword($oldPassword,$newPassword){
	    $userDb = new Application_Model_DbTable_DataManagers();
	    $newPassword = $userDb->updatePassword($oldPassword,$newPassword);
	    $sessionAlert = new Zend_Session_Namespace('alertSpace');
	    if($newPassword != false){
		    $sessionAlert->message = "Your password has been updated.";
		    $sessionAlert->status = "success";
		    return true;
	    }else{
		    $sessionAlert->message = "Sorry, we could not update your password. Please try again";
		    $sessionAlert->status = "failure";
		    return false;
	    }
    }
    
    
}

