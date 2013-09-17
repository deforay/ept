<?php

class Application_Model_DbTable_Users extends Zend_Db_Table_Abstract {

    protected $_name = 'users';
    protected $_primary = 'UserSystemID';

    public function addUser($params) {

        // TODO
    }
    
    public function getUserDetails($userId){
        return $this->fetchRow("UserID = '".$userId."'")->toArray();
    }

    public function updateUser($params) {

        $data = array(
            'UserFName' => $params['fname'],
            'UserLName' => $params['lname'],
            'UserPhoneNumber' => $params['phone2'],
            'UserCellNumber' => $params['phone1'],
            'UserSecondaryemail' => $params['semail']
        );

        return $this->update($data, "UserSystemID = " . $params['userSystemId']);
    }
    
    public function resetPasswordForEmail($email){
        $row = $this->fetchRow("UserID = '".$email."'");
        if($row != null && count($row) ==1){
            $randomPassword = Application_Service_Common::getRandomString(15);
            $row->Password = $randomPassword;
            $row->force_password_reset = 1;
            $row->save();
            return $randomPassword;
        }else{
            return false;
        }
    }    
    public function updatePassword($oldPassword,$newPassword){
        $authNameSpace = new Zend_Session_Namespace('Zend_Auth');
    	$email = $authNameSpace->UserID;
        $noOfRows = $this->update(array('Password' => $newPassword,'force_password_reset'=>0),"UserID = '".$email."' and Password = '".$oldPassword."'");
        if($noOfRows != null && count($noOfRows) ==1){
            $authNameSpace->ForcePasswordReset = 0;
            return true;
        }else{
            return false;
        }
    }

}

