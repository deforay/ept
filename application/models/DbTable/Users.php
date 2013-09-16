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

}

