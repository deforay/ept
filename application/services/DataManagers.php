<?php

class Application_Service_DataManagers
{

    public function addUser($params)
    {
        $userDb = new Application_Model_DbTable_DataManagers();
        return $userDb->addUser($params);
    }

    public function updateUser($params)
    {
        $userDb = new Application_Model_DbTable_DataManagers();
        return $userDb->updateUser($params);
    }

    public function updateLastLogin($dmId)
    {
        $userDb = new Application_Model_DbTable_DataManagers();
        return $userDb->updateLastLogin($dmId);
    }


    public function getAllUsers($params)
    {
        $userDb = new Application_Model_DbTable_DataManagers();
        return $userDb->getAllUsers($params);
    }

    public function getUserInfo($userId = null)
    {

        $userDb = new Application_Model_DbTable_DataManagers();
        if ($userId == null) {
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $userId = $authNameSpace->UserID;
        }
        return $userDb->getUserDetails($userId);
    }
    public function getUserInfoBySystemId($userSystemId = null)
    {

        $userDb = new Application_Model_DbTable_DataManagers();
        if ($userSystemId == null) {
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $userSystemId = $authNameSpace->dm_id;
        }
        return $userDb->getUserDetailsBySystemId($userSystemId);
    }

    public function resetPassword($email)
    {
        $userDb = new Application_Model_DbTable_DataManagers();
        $newPassword = $userDb->resetPasswordForEmail($email);
        $sessionAlert = new Zend_Session_Namespace('alertSpace');
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

        if ($newPassword != false) {
            $common = new Application_Service_Common();
            $message = "Dear Participant,<br/><br/> You have requested a password reset for the PT account for email ".$email.". <br/><br/>If you requested for the password reset, please click on the following link <a href='" . $conf->domain . "auth/new-password/email/" . base64_encode($email) . "'>" . $conf->domain . "auth/new-password/email/" . base64_encode($email) . "</a> or copy and paste it in a browser address bar.<br/><br/> If you did not request for password reset, you can safely ignore this email.<br/><br/><small>Thanks,<br/> ePT Support</small>";
            $fromMail = Application_Service_Common::getConfig('admin_email');
            $fromName = Application_Service_Common::getConfig('admin-name');
            $common->sendMail($email, null, null, "Password Reset - e-PT", $message, $fromMail, $fromName);
            $sessionAlert->message = "Your password has been reset. Please check your registered mail id for the instructions.";
            $sessionAlert->status = "success";
        } else {
            $sessionAlert->message = "Sorry, we could not reset your password. Please make sure that you enter your registered primary email id";
            $sessionAlert->status = "failure";
        }
    }


    public function getDataManagerList()
    {
        $userDb = new Application_Model_DbTable_DataManagers();
        return $userDb->getAllDataManagers();
    }

    public function getParticipantDatamanagerList($participantId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('participant_manager_map')->where("participant_id= ?", $participantId));
    }

    public function getDatamanagerParticipantList($datamanagerId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('participant_manager_map')->where("dm_id= ?", $datamanagerId)->group('participant_id'));
    }

    public function changePassword($oldPassword, $newPassword)
    {
        $userDb = new Application_Model_DbTable_DataManagers();
        $newPassword = $userDb->updatePassword($oldPassword, $newPassword);
        $sessionAlert = new Zend_Session_Namespace('alertSpace');
        if ($newPassword != false) {
            $sessionAlert->message = "Your password has been updated.";
            $sessionAlert->status = "success";
            return true;
        } else {
            $sessionAlert->message = "Sorry, we could not update your password. Please try again";
            $sessionAlert->status = "failure";
            return false;
        }
    }

    public function checkAnnouncementMessageShowing($dmId)
    {
        $response = '';
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('pmm' => 'participant_manager_map', array()))
            ->join(array('p' => 'participant'), 'p.participant_id=pmm.participant_id', array())
            ->join(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array('show_announcement_count' => new Zend_Db_Expr("SUM(show_announcement ='yes')")))
            ->where("pmm.dm_id = ?", $dmId)
            ->group('sp.participant_id');
        $result = $db->fetchRow($sql);
        if (isset($result['show_announcement_count']) && $result['show_announcement_count'] > 0) {
            $announcementMsg = $db->fetchRow($db->select()->from('announcements')->where("status = 'active' AND DATE(start_date) <= DATE(NOW()) AND DATE(end_date) >= DATE(NOW())"));
            if (isset($announcementMsg['announcement_msg']) && trim($announcementMsg['announcement_msg']) != '') {
                $response = $announcementMsg['announcement_msg'];
            }
        }
        return $response;
    }

    public function getParticipantDatamanagerSearch($participant)
    {
        $userDb = new Application_Model_DbTable_DataManagers();
        return $userDb->fetchParticipantDatamanagerSearch($participant);
    }

    public function newPassword($params){
        $userDb = new Application_Model_DbTable_DataManagers();
        $newPassword = $userDb->saveNewPassword($params);
        $sessionAlert = new Zend_Session_Namespace('alertSpace');
        if($newPassword  != false){
            $sessionAlert->message = "Your password has been updated.";
            $sessionAlert->status = "success";
            return '/auth/login';
        }else{
            $sessionAlert->message = "Sorry, we could not update your password. Please try again";
            $sessionAlert->status = "failure";
            return '/auth/new-password';
        }
    }

    public function checkEmail($email){
        $userDb = new Application_Model_DbTable_DataManagers();
        return $userDb->fetchEmailById($email);
    }

    public function loginDatamanagerAPI($params){
		$userDb = new Application_Model_DbTable_DataManagers();
		return $userDb->loginDatamanagerByAPI($params);
    }
    public static function getAuthToken($authToken)
    {
        $db = new Application_Model_DbTable_DataManagers();
        return $db->fetchAuthToken($authToken);
    }
}
