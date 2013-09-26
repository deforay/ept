<?php

class Application_Model_DbTable_Users extends Zend_Db_Table_Abstract {

    protected $_name = 'users';
    protected $_primary = array('UserID','UserSystemID');

    public function addUser($params) {

     $data = array(
            'UserFName' => $params['fname'],
            'UserLName' => $params['lname'],
            'UserPhoneNumber' => $params['phone2'],
            'UserCellNumber' => $params['phone1'],
            'UserSecondaryemail' => $params['semail'],
            'UserID' => $params['userId'],
            'Password' => $params['password'],
            'force_password_reset' => 1,
            'status' => $params['status']
        );

        return $this->insert($data);

    }
    public function getAllUsers($parameters) {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('UserFName','UserLName', 'UserPhoneNumber', 'UserID', 'UserSecondaryemail', 'status');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = "UserSystemID";


        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */
        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ( $parameters['sSortDir_' . $i] ) . ", ";
                }
            }

            $sOrder = substr_replace($sOrder, "", -2);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */
        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }


        /*
         * SQL queries
         * Get data to display
         */

        $sQuery = $this->getAdapter()->select()->from(array('u' => $this->_name));

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //error_log($sQuery);

        $rResult = $this->getAdapter()->fetchAll($sQuery);


        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from($this->_name, new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
        $aResultTotal = $this->getAdapter()->fetchCol($sQuery);
        $iTotal = $aResultTotal[0];

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        
        $aColumns = array('UserFName','UserLName', 'UserPhoneNumber', 'UserID', 'UserSecondaryemail', 'status');
        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = $aRow['UserFName']; 
            $row[] = $aRow['UserLName']; 
            $row[] = $aRow['UserCellNumber'];
            $row[] = $aRow['UserID'];
            $row[] = $aRow['UserSecondaryemail'];
            $row[] = $aRow['status'];
            $row[] = '<a href="/admin/users/edit/id/' . $aRow['UserSystemID'] . '" class="btn" style="margin-right: 2px;"><i class="icon-pencil"></i></a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }    
    
    public function getUserDetails($userId){
        return $this->fetchRow("UserID = '".$userId."'")->toArray();
    }
    
    public function getUserDetailsBySystemId($userSystemId){
        return $this->fetchRow("UserSystemID = '".$userSystemId."'")->toArray();
    }

    public function updateUser($params) {

        $data = array(
            'UserFName' => $params['fname'],
            'UserLName' => $params['lname'],
            'UserPhoneNumber' => $params['phone2'],
            'UserCellNumber' => $params['phone1'],
            'UserSecondaryemail' => $params['semail']
        );
        
        if(isset($params['userId']) && $params['userId'] != ""){
            $data['UserID'] = $params['userId'];
        }
        if(isset($params['status']) && $params['status'] != ""){
            $data['status'] = $params['status'];
        }

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

