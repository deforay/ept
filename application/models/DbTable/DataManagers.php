<?php

use Pt_Commons_MiscUtility as MiscUtility;
use Application_Service_Common as Common;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Application_Model_DbTable_DataManagers extends Zend_Db_Table_Abstract
{

    protected $_name = 'data_manager';
    protected $_primary = ['dm_id'];

    public function addUser($params)
    {
        // echo '<pre>'; print_r($params); die;
        $db = Zend_Db_Table_Abstract::getAdapter();
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $data = [
            'first_name' => $params['fname'],
            'last_name' => $params['lname'],
            'institute' => $params['institute'],
            'data_manager_type' => (isset($params['ptcc']) && !empty($params['ptcc']) && $params['ptcc'] == 'yes') ? 'ptcc' : 'manager',
            'country_id' => $params['countryId'], // for datamanaer add
            // 'country_id' => $params['country'][0],
            'phone' => $params['phone2'],
            'mobile' => $params['phone1'],
            'secondary_email' => $params['semail'],
            'primary_email' => $params['dmUserId'],
            'force_password_reset' => 1,
            'qc_access' => $params['qcAccess'],
            'enable_adding_test_response_date' => $params['receiptDateOption'],
            'enable_choosing_mode_of_receipt' => $params['modeOfReceiptOption'],
            'view_only_access' => $params['viewOnlyAccess'],
            'status' => $params['status'],
            'created_by' => $authNameSpace->admin_id,
            'created_on' => new Zend_Db_Expr('now()')
        ];
        if (isset($params['dmPassword']) && !empty($params['dmPassword'])) {
            $password = Common::passwordHash($params['dmPassword']);
            $data['password'] = $password;
        }
        $isPtcc = (isset($params['ptcc']) && $params['ptcc'] == 'yes') ? true : false;

        $dmId = $this->insert($data);
        if ($dmId === false || $dmId === 0) {
            return 0;
        }
        if ($dmId > 0) {
            $params['participantsList'] = isset($params['allparticipant']) ? Common::removeEmpty($params['allparticipant']) : [];
            $this->dmParticipantMap($params, $dmId, $isPtcc);

            $firstName = isset($params['fname']) && $params['fname'] != '' ? $params['fname'] :  null;
            $lastName =  isset($params['lname']) && $params['lname'] != '' ? $params['lname'] :  null;
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $name = $firstName . " " . $lastName;
            $userName = isset($name) != '' ? $name : $authNameSpace->primary_email;
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Added a new data-manager - $userName", "participants");
        }
        return $dmId;
    }

    public function getAllUsers($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */
        if (isset($parameters['ptcc']) && $parameters['ptcc'] == 1) {
            $aColumns = array('u.first_name', 'u.last_name', 'u.mobile', 'u.primary_email', 'u.status', 'c.iso_name', 'state', 'district');
        } else {
            $aColumns = array('u.institute', 'u.first_name', 'u.last_name', 'u.mobile', 'u.primary_email', 'u.status');
        }


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
				 	" . ($parameters['sSortDir_' . $i]) . ", ";
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
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
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




        $sQuery = $this->getAdapter()->select()
            ->from(array('u' => $this->_name), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS *')))
            ->group('u.dm_id');

        if (isset($parameters['ptcc']) && $parameters['ptcc'] == 1) {
            $sQuery = $sQuery->where("data_manager_type = 'ptcc'");
        } else {
            $sQuery = $sQuery->where("(data_manager_type like 'manager')");
        }
        $adminNameSpace = new Zend_Session_Namespace('administrators');
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id) && empty($adminNameSpace->admin_id)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.dm_id=u.dm_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        if (isset($parameters['ptcc']) && $parameters['ptcc'] == 1) {
            $sQuery = $sQuery->joinLeft(array('pcm' => 'ptcc_countries_map'), 'pcm.ptcc_id=u.dm_id', array(
                'state' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pcm.state SEPARATOR ', ')"),
                'district' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pcm.district SEPARATOR ', ')")
            ));
            $sQuery = $sQuery->joinLeft(array('c' => 'countries'), 'c.id=pcm.country_id', array('c.iso_name'));
        }

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        // die($sQuery);

        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $iTotal = $iFilteredTotal = $this->getAdapter()->fetchOne('SELECT FOUND_ROWS()');

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        foreach ($rResult as $aRow) {
            $row = [];
            //if(isset($aRow['participant_id'])&& $aRow['participant_id']!=''){
            //$participantDetails='<a href="javascript:void(0);" onclick="layoutModal(\'/admin/participants/view-participants/id/'.$aRow['participant_id'].'\',\'980\',\'500\');" class="btn btn-primary btn-xs"><i class="icon-search"></i></a>';
            //}else{
            //$participantDetails='';
            //}
            if (!isset($parameters['ptcc']) || $parameters['ptcc'] != 1) {
                $row[] = $aRow['institute'];
            }
            // $row[] = $participantDetails.' '.$aRow['institute'];
            $row[] = $aRow['first_name'];
            $row[] = $aRow['last_name'];
            $row[] = $aRow['mobile'];
            $row[] = $aRow['primary_email'];
            //$row[] = '<a href="javascript:void(0);" onclick="layoutModal(\'/admin/participants/view-participants/id/' . $aRow['dm_id'] . '\',\'980\',\'500\');" >' . $aRow['participantCount'] . '</a>';
            $row[] = ucwords($aRow['status']);
            if (isset($parameters['ptcc']) && $parameters['ptcc'] == 1) {
                $row[] = ucwords($aRow['iso_name']);
                $row[] = ucwords($aRow['state']);
                $row[] = ucwords($aRow['district']);
            }
            if (isset($parameters['from']) && $parameters['from'] == 'participant') {
                $edit = '<a href="/data-managers/edit/id/' . $aRow['dm_id'] . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';
            } elseif (isset($aRow['data_manager_type']) && $aRow['data_manager_type'] == 'ptcc') {
                $edit = '<a href="/admin/data-managers/edit/id/' . $aRow['dm_id'] . '/ptcc/1" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';
            } else {
                $edit = '<a href="/admin/data-managers/edit/id/' . $aRow['dm_id'] . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';
            }
            if (isset($parameters['from']) && $parameters['from'] == 'participant') {
                $passwordReset = '<a href="javascript:void(0);" class="btn btn-info btn-xs" onclick="layoutModal(\'/data-managers/reset-password/id/' . $aRow['dm_id'] . '\',\'980\',\'500\');" >Reset Password</a>';
            } else {
                $passwordReset = '<a href="javascript:void(0);" class="btn btn-info btn-xs" onclick="layoutModal(\'/admin/data-managers/reset-password/id/' . $aRow['dm_id'] . '\',\'980\',\'500\');" >Reset Password</a>';
            }
            $row[] = $edit . $passwordReset;

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getUserDetails($userId)
    {
        $sql = $this->select()->from('data_manager')->where("primary_email = ?", $userId);
        return $this->fetchRow($sql);
    }

    public function fetchUserCuntryMap($userId, $type = null, $ptcc = false)
    {
        $sql = $this->getAdapter()->select()->from('ptcc_countries_map')->where("ptcc_id = ?", $userId);
        $response =  $this->getAdapter()->fetchAll($sql);
        if ($type == "implode") {
            $countryList = [];
            foreach ($response as $cu) {
                if ($ptcc) {
                    $countryList['country'][] = $cu['country_id'];
                    $countryList['state'][] = $cu['state'];
                    $countryList['district'][] = $cu['district'];
                } else {
                    $countryList[] = $cu['country_id'];
                }
            }
            return $countryList;
        }
        return $response;
    }

    public function getUserDetailsBySystemId($userSystemId)
    {
        $sql = $this->select()->from('data_manager')->where("dm_id = ?", $userSystemId);
        return $this->fetchRow($sql);
    }

    public function updateUser($params)
    {

        $db = Zend_Db_Table_Abstract::getAdapter();
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $dmNameSpace = new Zend_Session_Namespace('datamanagers');
        $data = [
            'data_manager_type' => (isset($params['ptcc']) && !empty($params['ptcc']) && $params['ptcc'] == 'yes') ? 'ptcc' : 'manager',
            'country_id' => $params['countryId'],
            'first_name' => $params['fname'],
            'last_name' => $params['lname'],
            'phone' => $params['phone2'],
            'mobile' => $params['phone1'],
            'secondary_email' => $params['semail'],
            'updated_by' => $authNameSpace->admin_id,
            'updated_on' => new Zend_Db_Expr('now()')
        ];

        if (
            $dmNameSpace->force_profile_check_primary == 'yes' ||
            (isset($params['pemail']) && $params['pemail'] != "" &&
                isset($params['oldpemail']) && $params['oldpemail'] != "" &&
                $params['oldpemail'] != $params['pemail']
            )
        ) {
            $data['new_email'] = $params['pemail'];
        }
        if (isset($params['pemail']) && $params['pemail'] != "") {
            $data['primary_email'] = $params['pemail'];
        }
        if (isset($params['oldpemail']) && $params['oldpemail'] != "") {
            $data['primary_email'] = $params['oldpemail'];
        }
        if (isset($params['institute']) && $params['institute'] != "") {
            $data['institute'] = $params['institute'];
        }
        if (isset($params['qcAccess']) && $params['qcAccess'] != "") {
            $data['qc_access'] = $params['qcAccess'];
        }
        if (isset($params['receiptDateOption']) && $params['receiptDateOption'] != "") {
            $data['enable_adding_test_response_date'] = $params['receiptDateOption'];
        }
        if (isset($params['modeOfReceiptOption']) && $params['modeOfReceiptOption'] != "") {
            $data['enable_choosing_mode_of_receipt'] = $params['modeOfReceiptOption'];
        }
        if (isset($params['viewOnlyAccess']) && $params['viewOnlyAccess'] != "") {
            $data['view_only_access'] = $params['viewOnlyAccess'];
        }
        if (isset($params['dmPassword']) && !empty($params['dmPassword'])) {
            $password = Common::passwordHash($params['dmPassword']);
            $data['password'] = $password;
            $data['force_password_reset'] = 1;
        }
        if (isset($params['status']) && $params['status'] != "") {
            $data['status'] = $params['status'];
        }
        if (isset($params['language']) && $params['language'] != "") {
            $data['language'] = $params['language'];
        }

        $isPtcc = (isset($params['ptcc']) && $params['ptcc'] == 'yes') ? true : false;

        $dmId = (int) $params['userSystemId'];
        if ($dmId !== false && $dmId > 0) {
            $this->update($data, "dm_id = $dmId");
            if (isset($params['deleteSystemId']) && count($params['deleteSystemId']) > 0) {
                $db->delete('participant_manager_map', "dm_id = {$params['deleteSystemId']}");
                $db->delete('ptcc_countries_map', "ptcc_id = " . $params['deleteSystemId']);
                $this->delete("dm_id = {$params['deleteSystemId']}");
            }
            $params['participantsList'] = isset($params['allparticipant']) ? Common::removeEmpty($params['allparticipant']) : [];
            $this->dmParticipantMap($params, $dmId, $isPtcc);

            $firstName = isset($params['fname']) && $params['fname'] != '' ? $params['fname'] :  NULL;
            $lastName =  isset($params['lname']) && $params['lname'] != '' ? $params['lname'] :  NULL;
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $name = "$firstName $lastName";
            $userName = isset($name) != '' ? $name : $authNameSpace->primary_email;
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Updated data manager $userName", "participants");
        }
        return $dmId;
    }

    public function mapPtccLocations($params, $dmId)
    {
        $common = new Application_Service_Common();
        $db = Zend_Db_Table_Abstract::getAdapter();
        foreach ($params['country'] as $country) {
            $countryDuplicate = true;
            if (isset($params['province'][0]) && sizeof($params['province']) > 0) {
                $provinceList = $common->getParticipantsProvinceList($country, 'list');
                foreach ($params['province'] as $state) {
                    if (isset($provinceList) && count($provinceList) > 0 && in_array($state, $provinceList)) {
                        if (isset($params['district'][0]) && sizeof($params['district']) > 0) {
                            $districtList = $common->getParticipantsDistrictList($state, 'list');
                            foreach ($params['district'] as $district) {
                                $_districtData = array('ptcc_id' => $dmId, 'country_id' => $country);
                                $_districtData['state'] = $state;
                                if (isset($districtList) && count($districtList) > 0 && in_array($district, $districtList)) {
                                    $_districtData['district'] = $district;
                                }
                                $db->insert('ptcc_countries_map', $_districtData);
                            }
                        } else {
                            if (isset($provinceList) && count($provinceList) > 0 && in_array($state, $provinceList)) {
                                $db->insert('ptcc_countries_map', array('ptcc_id' => $dmId, 'country_id' => $country, 'state' => $state));
                            }
                        }
                    } else {
                        if (isset($provinceList) && count($provinceList) > 0 && in_array($state, $provinceList)) {
                            $db->insert('ptcc_countries_map', array('ptcc_id' => $dmId, 'country_id' => $country, 'state' => $state));
                        } else {
                            if ($countryDuplicate) {
                                $db->insert('ptcc_countries_map', array('ptcc_id' => $dmId, 'country_id' => $country));
                                $countryDuplicate = false;
                            }
                        }
                    }
                }
            } else {
                $db->insert('ptcc_countries_map', array('ptcc_id' => $dmId, 'country_id' => $country));
            }
        }
    }
    public function updateForceProfileCheckByEmail($email, $result = "")
    {
        $row = $this->fetchRow(array("new_email = '" . $email . "' AND new_email IS NOT NULL"));
        if ((isset($result) && $result != "") || isset($row) && $row != "") {
            return $this->update(array('force_profile_check' => 'no', 'primary_email' => $result['new_email'], 'new_email' => null), "new_email = '" . base64_decode($email) . "'");
        }
        return $this->update(array('force_profile_check' => 'no'), "primary_email = '" . base64_decode($email) . "'");
    }

    public function resetpasswordForEmail($email)
    {

        $sql = $this->select()->from('data_manager')->where("primary_email = ?", $email);
        return $this->fetchRow($sql);
    }

    public function getAllDataManagers($ptcc)
    {
        $sql = $this->select()->order("first_name");
        if (!$ptcc) {
            $sql = $sql->where("data_manager_type = 'manager'");
        }
        return $this->fetchAll($sql);
    }

    public function updatePasswordFromAdmin($email, $newpassword)
    {
        $common = new Application_Service_Common();
        $newpassword = Common::passwordHash($newpassword);
        $noOfRows = $this->update(['password' => $newpassword, 'force_password_reset' => 0], "primary_email = '" . $email . "'");
        if ($noOfRows != null && $noOfRows == 1) {
            return true;
        } else {
            return false;
        }
    }

    public function updatePassword($oldpassword, $newpassword)
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $email = $authNameSpace->email;
        $common = new Application_Service_Common();
        $result = $this->fethDataByCredentials(trim($email), trim($oldpassword));
        $passwordVerify = true;
        if (isset($result) && !empty($result)) {
            $passwordVerify = password_verify((string) $oldpassword, (string) $result['password']);
        }
        if ($passwordVerify) {
            $newpassword = Common::passwordHash($newpassword);
            $noOfRows = $this->update(['password' => $newpassword, 'force_password_reset' => 0], "primary_email = '$email'");
            if ($noOfRows != null && $noOfRows == 1) {
                $authNameSpace->forcePasswordReset = 0;
                return true;
            } else {
                return false;
            }
        }
    }

    public function updateLastLogin($dmId)
    {

        $noOfRows = $this->update(array('last_login' => new Zend_Db_Expr('now()')), "dm_id = " . $dmId);
        if ($noOfRows != null && $noOfRows == 1) {
            return true;
        } else {
            return false;
        }
    }

    public function fetchParticipantDatamanagerSearch($searchParams)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(['u' => $this->_name]);
        //$searchParams = explode(" ", $searchParams);
        //foreach($searchParams as $s){
        if (isset($searchParams) && !empty($searchParams))
            $sql =  $sql->where("primary_email LIKE '%" . $searchParams . "%' OR first_name LIKE '%" . $searchParams . "%' OR last_name LIKE '%" . $searchParams . "%' OR institute LIKE '%" . $searchParams . "%'");
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (isset($searchParams['from']) && $searchParams['from'] == 'participant' && $authNameSpace->ptcc == 1) {
            $sql =  $sql->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.dm_id=u.dm_id', ['pmm.dm_id'])
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        } else {
            $sql =  $sql->where("data_manager_type != 'ptcc'");
        }
        //}

        die($sql);
        return $db->fetchAll($sql);
    }

    public function saveNewPassword($params)
    {
        $password = Common::passwordHash($params['password']);
        $noOfRows = $this->update(['password' => $password], "primary_email = '{$params['registeredEmail']}'");
        return $noOfRows === 1;
    }

    public function fetchEmailById($email)
    {
        $sql = $this->select()->from('data_manager')->where("primary_email = ?", base64_decode($email));
        return $this->fetchRow($sql);
    }

    public function fetchVerifyEmailById($email)
    {
        $sql = $this->select()->from('data_manager')->where("new_email = ?", base64_decode($email));
        return $this->fetchRow($sql);
    }

    public function fetchForceProfileEmail($link)
    {
        $db = Zend_Db_Table_Abstract::getAdapter();
        $email = base64_decode($link);

        $sql = $this->select()->from('data_manager')->where("primary_email=?", $email);

        $list = $this->fetchRow($sql);

        if (!empty($list) && $list != null) {

            if (date('Ymd', strtotime($list['last_date_for_email_reset'])) >= date('Ymd')) {
                $psql = $db->select()->from(array('dm' => 'data_manager'), array('dm_id'))
                    ->join(array('pmm' => 'participant_manager_map'), 'pmm.dm_id=dm.dm_id')
                    ->join(array('p' => 'participant'), 'p.participant_id=pmm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.lab_name', 'p.institute_name', 'p.state', 'country'))
                    ->join(array('c' => 'countries'), 'c.id=p.country', array('*'))
                    ->where("dm.dm_id=" . $list['dm_id']);
                return array('id' => $list['dm_id'], 'email' => $list['primary_email'], 'participants' => $db->fetchAll($psql));
            } else {
                return false;
            }
        }
        // die;
        return false;
    }

    public function changeForceProfileCheckByEmail($params)
    {
        return $this->update(array('force_profile_check' => 'no', 'new_email' => $params['registeredEmail'], 'last_date_for_email_reset' => date('Y-m-d', strtotime('+30 days'))), "dm_id =" . base64_decode($params['dmId']));
    }

    public function loginDatamanagerByAPI($params)
    {
        $apiService = new Application_Service_ApiServices();
        $transactionId = Pt_Commons_General::generateULID();
        $payload = [];
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, APPLICATION_ENV);

        if (!isset($params['userId']) && !isset($params['key'])) {
            return [
                'status' => 'fail',
                'message' => 'Please enter valid login credentials'
            ];
        }

        $result = $this->fetchRow("new_email != primary_email AND new_email = '{$params['userId']}'");

        $passwordVerify = true;
        if (isset($result) && !empty($result)) {
            $passwordVerify = password_verify((string) $params['key'], (string) $result['password']);
        }
        if ($result && $passwordVerify) {
            $resultData['resendMail'] = '/api/participant/resend?id=' . base64_encode($result['new_email'] . '##' . $result['primary_email']);
            $payload = [
                'status' => 'fail',
                'message' => 'Please verify the change of your primary email from ' . $result['primary_email'] . ' to ' . $result['new_email'] . ' by clicking on verification link sent to <b>' . $result['new_email'] . '</b>',
                'data' => $resultData
            ];
            $apiService->addApiTracking($transactionId, $result['dm_id'], 1, 'login', 'common', $_SERVER['REQUEST_URI'], $params, $payload, 'json');
            return $payload;
        }
        /* Check the login credential */
        $result = $this->fetchRow("primary_email='" . $params['userId'] . "'");
        $passwordVerify = true;
        if (isset($result) && !empty($result)) {
            $passwordVerify = password_verify((string) $params['key'], (string) $result['password']);
        }
        if (!$result || !$passwordVerify) {
            $payload = [
                'status' => 'fail',
                'message' => 'Please enter valid login credentials'
            ];
            $apiService->addApiTracking($transactionId, $result['dm_id'], 1, 'login', 'common', $_SERVER['REQUEST_URI'], $params, $payload, 'json');
            return $payload;
        }
        /* Check the status for data manager */
        if (isset($result['status']) && $result['status'] != "active") {
            $payload = [
                'status' => 'fail',
                'message' => 'Please enter valid login credentials'
            ];
            $apiService->addApiTracking($transactionId, $result['dm_id'], 1, 'login', 'common', $_SERVER['REQUEST_URI'], $params, $payload, 'json');
            return $payload;
        }
        /* Update the new auth token */
        $params['authToken'] = Common::generateRandomString(32);
        $params['download_link'] = Common::generateRandomString(32);

        $this->update(['auth_token' => $params['authToken'], 'download_link' => $params['download_link'] ?? null, 'last_login' => new Zend_Db_Expr('now()'), 'api_token_generated_datetime' => new Zend_Db_Expr('now()')], "dm_id = " . $result['dm_id']);
        $aResult = $this->fetchAuthToken($params);

        /* Validate new auth token and app-version */
        if (!$aResult) {
            $payload = [
                'status' => 'auth-fail',
                'message' => 'Please enter valid login credentials'
            ];
            $apiService->addApiTracking($transactionId, $result['dm_id'], 1, 'login', 'common', $_SERVER['REQUEST_URI'], $params, $payload, 'json');
            return $payload;
        }

        /* Check last login before 6 month */
        $lastLogin = date('Ymd', strtotime($result['last_login']));
        $current = date("Ymd", strtotime(" -6 months"));
        if (($current > $lastLogin)) {
            $aResult['force_profile_check'] = 'yes';
        }
        // To get the API version from system config model
        $systemDb = new Application_Model_DbTable_SystemConfig();
        $apiVersion = $systemDb->getValueByName('api_version')['value'];
        /* Create a new response to the API service */
        $resultData = [
            'id' => $result['dm_id'],
            'authToken' => $params['authToken'],
            'viewOnlyAccess' => (isset($aResult['view_only_access']) && $aResult['view_only_access'] != "") ? $aResult['view_only_access'] : 'no',
            'qcAccess' => (isset($aResult['qc_access']) && $aResult['qc_access'] != "") ? $aResult['qc_access'] : 'no',
            'enableAddingTestResponseDate' => (isset($aResult['enable_adding_test_response_date']) && $aResult['enable_adding_test_response_date'] != "") ? $aResult['enable_adding_test_response_date'] : 'no',
            'enableChoosingModeOfReceipt' => (isset($aResult['enable_choosing_mode_of_receipt']) && $aResult['enable_choosing_mode_of_receipt'] != "") ? $aResult['enable_choosing_mode_of_receipt'] : 'no',
            'forcePasswordReset' => (isset($aResult['force_password_reset']) && $aResult['force_password_reset'] != "" && $aResult['force_password_reset'] == 1) ? 'yes' : 'no',
            'forceProfileCheck' => (isset($aResult['force_profile_check']) && $aResult['force_profile_check'] != "") ? $aResult['force_profile_check'] : 'no',
            'dtsOptionalTest3' => (isset($config->evaluation->dts->dtsOptionalTest3) && $config->evaluation->dts->dtsOptionalTest3 != "") ? $config->evaluation->dts->dtsOptionalTest3 : "no",
            'displaySampleConditionFields' => (isset($config->evaluation->dts->displaySampleConditionFields) && $config->evaluation->dts->displaySampleConditionFields != "") ? $config->evaluation->dts->displaySampleConditionFields : "no",
            'allowRepeatTests' => (isset($config->evaluation->dts->allowRepeatTests) && $config->evaluation->dts->allowRepeatTests != "") ? $config->evaluation->dts->allowRepeatTests : "no",
            'covid19MaximumTestAllowed' => (isset($config->evaluation->covid19->covid19MaximumTestAllowed) && $config->evaluation->covid19->covid19MaximumTestAllowed != "") ? $config->evaluation->covid19->covid19MaximumTestAllowed : "1",
            'name' => $result['first_name'] . ' ' . $result['last_name'],
            'phone' => $result['phone'],
            'appVersion' => $apiVersion ?? null,
            'pushStatus' => null,
            'profileInfo' => $aResult['profileInfo'],
            'resendMail' => ''
        ];

        /* Finalizing the response data and return */
        if (empty($resultData) || trim($resultData['authToken']) == '') {
            $payload = [
                'status' => 'fail',
                'message' => 'Something went wrong please try again later'
            ];
        } else {
            $row = $this->fetchRow('auth_token="' . $params['authToken'] . '" AND new_email IS NOT NULL');
            if (!$row) {
                $payload = [
                    'status' => 'success',
                    'data' => $resultData
                ];
            } else {
                $resultData['resendMail'] = '/api/participant/resend?id=' . base64_encode($row['new_email'] . '##' . $row['primary_email']);
                $payload = ['status' => 'success', 'message' => 'Please verify your primary email change to “' . $row['new_email'] . '”', 'data' => $resultData];
            }
        }
        $apiService->addApiTracking($transactionId, $result['dm_id'], 1, 'login', 'common', $_SERVER['REQUEST_URI'], $params, $payload, 'json');
        return $payload;
    }

    public function fetchLoggedInDetails($params)
    {
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, APPLICATION_ENV);
        /* Check the app versions */
        /* if (!isset($params['appVersion'])) {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        if (!isset($params['authToken'])) {
            return array('status' => 'auth-fail', 'message' => 'Please check your credentials and try to log in again');
        }

        /* Check the login credential */
        $result = $this->fetchRow("auth_token= '" . $params['authToken'] . "'");

        $aResult = $this->fetchAuthToken($params);
        /* App version check */
        /* if ($aResult == 'app-version-failed') {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        /* Validate new auth token and app-version */
        if (!$aResult) {
            return array('status' => 'auth-fail', 'message' => 'Please check your credentials and try to log in again');
        }
        /* Create a new response to the API service */
        $resultData = [
            'id' => $result['dm_id'],
            'authToken' => $params['authToken'],
            'viewOnlyAccess' => (isset($aResult['view_only_access']) && $aResult['view_only_access'] == "yes") ? true : false,
            'qcAccess' => (isset($aResult['qc_access']) && $aResult['qc_access'] == "yes") ? true : false,
            'enableAddingTestResponseDate' => (isset($aResult['enable_adding_test_response_date']) && $aResult['enable_adding_test_response_date'] == "yes") ? true : false,
            'enableChoosingModeOfReceipt' => (isset($aResult['enable_choosing_mode_of_receipt']) && $aResult['enable_choosing_mode_of_receipt'] == "yes") ? true : false,
            'forcePasswordReset' => (isset($aResult['force_password_reset']) && $aResult['force_password_reset'] != "" && $aResult['force_password_reset'] == 1) ? true : false,
            'forceProfileCheck' => (isset($aResult['force_profile_check']) && $aResult['force_profile_check'] == "yes") ? true : false,
            'dtsOptionalTest3' => (isset($config->evaluation->dts->dtsOptionalTest3) && $config->evaluation->dts->dtsOptionalTest3 == "yes") ? true : false,
            'displaySampleConditionFields' => (isset($config->evaluation->dts->displaySampleConditionFields) && $config->evaluation->dts->displaySampleConditionFields == "yes") ? true : false,
            'allowRepeatTests' => (isset($config->evaluation->dts->allowRepeatTests) && $config->evaluation->dts->allowRepeatTests == "yes") ? true : false,
            'dtsSchemeType' => (isset($config->evaluation->dts->dtsSchemeType) && $config->evaluation->dts->dtsSchemeType != "") ? $config->evaluation->dts->dtsSchemeType : "standard",
            'covid19MaximumTestAllowed' => (isset($config->evaluation->covid19->covid19MaximumTestAllowed) && $config->evaluation->covid19->covid19MaximumTestAllowed != "") ? $config->evaluation->covid19->covid19MaximumTestAllowed : "1",
            'name' => $result['first_name'] . ' ' . $result['last_name'],
            'phone' => $result['phone'],
            'appVersion' => $aResult['app_version'],
            'pushStatus' => null,
            'profileInfo' => $aResult['profileInfo'],
            'resendMail' => null
        ];

        /* Finalizing the response data and return */
        if (!isset($resultData) || trim($resultData['authToken']) == '') {
            return ['status' => 'fail', 'message' => 'Something went wrong please try again later'];
        } else {
            $row = $this->fetchRow('auth_token="' . $params['authToken'] . '" AND new_email IS NOT NULL');
            if (!$row) {
                return array('status' => 'success', 'data' => $resultData);
            } else {
                $resultData['resendMail'] = '/api/participant/resend?id=' . base64_encode($row['new_email'] . '##' . $row['primary_email']);
                return array('status' => 'success', 'message' => 'Please verify your primary email change to “' . $row['new_email'] . '”', 'data' => $resultData);
            }
        }
    }

    public function fetchAuthToken($params)
    {
        // $configDb = new Application_Model_DbTable_SystemConfig();
        // $appVersion = $configDb->getValue($params['appVersion']);
        /* Check the app versions */
        /*if (!$appVersion) {
            return 'app-version-failed';
        } */
        /* Check the token  */
        $db = Zend_Db_Table_Abstract::getAdapter();
        $sQuery = $db->select()->from(array('dm' => 'data_manager'), array('dm.dm_id', 'api_token_generated_datetime', 'view_only_access', 'qc_access', 'enable_adding_test_response_date', 'enable_choosing_mode_of_receipt', 'force_password_reset', 'force_profile_check', 'new_email'))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.dm_id=dm.dm_id')
            ->join(array('p' => 'participant'), 'p.participant_id=pmm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.state'))
            ->where("dm.auth_token=?", $params['authToken']);
        $aResult = $db->fetchRow($sQuery);
        // Zend_Debug::dump($sQuery->assemble());die;
        if (!isset($aResult['dm_id'])) {
            return false;
        }
        /* Return the response data */
        $conf = new Zend_Config_Ini(APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini", APPLICATION_ENV);

        return  array(
            'dm_id'                             => $aResult['dm_id'],
            'view_only_access'                  => $aResult['view_only_access'],
            'qc_access'                         => $aResult['qc_access'],
            'enable_adding_test_response_date'  => $aResult['enable_adding_test_response_date'],
            'enable_choosing_mode_of_receipt'   => $aResult['enable_choosing_mode_of_receipt'],
            'participant_id'                    => $aResult['participant_id'],
            'unique_identifier'                 => $aResult['unique_identifier'],
            'first_name'                        => $aResult['first_name'],
            'last_name'                         => $aResult['last_name'],
            'state'                             => $aResult['state'],
            'force_password_reset'              => $aResult['force_password_reset'],
            'force_profile_check'               => (isset($aResult['force_profile_check']) && $aResult['force_profile_check'] != '') ? $aResult['force_profile_check'] : null,
            'app_version'                       => (isset($params['value']) && $params['value'] != '') ? $params['value'] : null,
            'profileInfo'                       => $this->checkTokenExpired($params['authToken'])
        );
    }

    public function checkTokenExpired($authToken)
    {
        /* Check If token got expired and need to update the new one */
        $db = Zend_Db_Table_Abstract::getAdapter();
        $sql = $db->select()->from(array('dm' => 'data_manager'), array('dm.dm_id', 'status', 'api_token_generated_datetime', 'view_only_access', 'qc_access', 'enable_adding_test_response_date', 'enable_choosing_mode_of_receipt', 'force_password_reset', 'force_profile_check', 'new_email'))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.dm_id=dm.dm_id')
            ->join(array('p' => 'participant'), 'p.participant_id=pmm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.state'))
            ->where("dm.auth_token=?", $authToken);
        $result = $db->fetchRow($sql);
        $response['token-updated'] = false;
        $response['force-logout'] = false;
        $response['newAuthToken'] = null;
        if (($result['api_token_generated_datetime'] < date('Y-m-d H:i:s', strtotime('-365 days'))) || $result['status'] == 'inactive') {
            if ($result['status'] == 'inactive') {
                $response['force-logout'] = true;
            } else {
                $response['force-logout'] = false;
            }

            $response['newAuthToken'] = Common::generateRandomString(6);
            $id = $this->update(array('auth_token' => $response['newAuthToken'], 'api_token_generated_datetime' => new Zend_Db_Expr('now()')), "dm_id = " . $result['dm_id']);
            if ($id > 0) {
                $response['token-updated'] = true;
            } else {
                $response['token-updated'] = false;
            }
        } else {
        }
        return $response;
    }

    public function changePasswordDatamanagerByAPI($params)
    {
        /* Check the app versions */
        /* if (!isset($params['appVersion'])) {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        /* App version check */
        $aResult = $this->fetchAuthToken($params);
        /* if ($aResult == 'app-version-failed') {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        if (!$aResult) {
            return array('status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again');
        }

        $oldPassResult = $this->fetchRow("auth_token = '" . $params['authToken'] . "'");
        $passwordVerify = true;
        if (isset($oldPassResult) && !empty($oldPassResult)) {
            $passwordVerify = password_verify((string) $params['oldPassword'], (string) $oldPassResult['password']);
        }
        if (!$oldPassResult && !$passwordVerify) {
            return array('status' => 'fail', 'message' => 'Your old password is incorrect', 'profileInfo' => $aResult['profileInfo']);
        }
        /* Update the new password to the server */
        $newpassword = Common::passwordHash($params['password']);
        $update = $this->update(array('password' => $newpassword), array('dm_id = ?' => (int) $aResult['dm_id']));
        if ($update < 1) {
            return array('status' => 'fail', 'message' => 'You have entered old password', 'profileInfo' => $aResult['profileInfo']);
        }
        $this->update(array('updated_on' => new Zend_Db_Expr('now()')), array('dm_id = ?' => $aResult['dm_id']));
        return array('status' => 'success', 'message' => 'Password Updated Successfully', 'profileInfo' => $aResult['profileInfo']);
    }

    public function setForgetPasswordDatamanagerAPI($params)
    {
        /* Check the app versions */
        /* if (!isset($params['appVersion'])) {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        /* App version check */
        $aResult = $this->fetchRow("primary_email='" . $params['email'] . "'");
        if (!$aResult) {
            return ['status' => 'fail', 'message' => 'Your email id is not registered. Please check again.'];
        }
        /* Update the new password to the server */
        /* $update = $this->update(array('password' => $params['password']), array('dm_id = ?' => $aResult['dm_id']));
        if($update < 1){
            return array('status' =>'fail','message'=>'You have entered old password');
        }
        $this->update(array('updated_on'=>new Zend_Db_Expr('now()')), array('dm_id = ?' => $aResult['dm_id']));
        return array('status' =>'success','message'=>'Password Updated Successfully'); */
        $email = $params['email'];
        $row = $this->fetchRow("primary_email = '" . $email . "'");
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

        $eptDomain = rtrim($conf->domain, "/");

        if ($row) {
            $common = new Application_Service_Common();
            $message = "Dear Participant,<br/><br/> You have requested a password reset for the PT account for email " . $email . ". <br/><br/>If you requested for the password reset, please click on the following link <a href='" . $eptDomain . "/auth/new-password/email/" . base64_encode($email) . "'>" . $eptDomain . "auth/new-password/email/" . base64_encode($email) . "</a> or copy and paste it in a browser address bar.<br/><br/> If you did not request for password reset, you can safely ignore this email.<br/><br/><small>Thanks,<br/> ePT Support</small>";
            $fromMail = Common::getConfig('admin_email');
            $fromName = Common::getConfig('admin-name');
            $check = $common->insertTempMail($email, null, null, "Password Reset - e-PT", $message, $fromMail, $fromName);
            if (!$check) {
                return ['status' => 'fail', 'message' => 'Something went wrong please try again later.'];
            }
            return ['status' => 'success', 'message' => 'Your password has been reset. Please check your registered mail id for the instructions.'];
        } else {
            return ['status' => 'fail', 'message' => 'You have entered primary email not found'];
        }
    }

    public function fetchAuthTokenByToken($params)
    {
        return $this->fetchRow("auth_token='" . $params['authToken'] . "'");
    }

    public function fetchProfileCheckDetailsAPI($params)
    {
        /* Check the app versions & parameters */
        /* if (!isset($params['appVersion'])) {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        if (!isset($params['authToken'])) {
            return array('status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again');
        }

        /* Validate new auth token and app-version */
        $aResult = $this->fetchAuthToken($params);
        /* if ($aResult == 'app-version-failed') {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        if (!$aResult) {
            return array('status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again');
        }

        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $result = $this->fetchRow("auth_token = '" . $params['authToken'] . "'");
        if (isset($result) && trim($result['dm_id'] != '')) {

            $sql = $this->getAdapter()->select()->from(array('dm' => 'data_manager'), array(''))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.dm_id=dm.dm_id')
                ->join(array('p' => 'participant'), 'p.participant_id=pmm.participant_id', array('*'))
                ->where("dm.auth_token=?", $params['authToken']);
            $mappedParticipants = $this->getAdapter()->fetchAll($sql);

            $response['status'] = 'success';
            $response['data'] = [
                'dmId' => $result['dm_id'],
                'primaryEmail' => $result['primary_email'],
                'firstName' => $result['first_name'],
                'lastName' => $result['last_name'],
                'secondaryEmail' => $result['secondary_email'],
                'mobile' => $result['mobile'],
                'phone' => $result['phone'],
                'profileInfo' => $aResult['profileInfo'],
                'mappedParticipants' => $mappedParticipants
            ];
            $this->update(array('force_profile_check' => 'no'), 'dm_id = ' . $result['dm_id']);
        } else {
            $response['status'] = 'fail';
            $response['message'] = 'No participant found.';
            $response['profileInfo'] = $aResult['profileInfo'];
        }
        return $response;
    }

    public function saveProfileDetailsByAPI($params)
    {
        /* Check the app versions & parameters */
        /* if (!isset($params['appVersion'])) {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        if (!isset($params['authToken'])) {
            return array('status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again');
        }

        /* Validate new auth token and app-version */
        $aResult = $this->fetchAuthToken($params);
        /* if ($aResult == 'app-version-failed') {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        if (!$aResult) {
            return array('status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again');
        }
        /* started save profile details */

        /* check old data */
        $fetchOldMail = $this->fetchRow("auth_token = '" . $params['authToken'] . "'");
        $updateData = array(
            'first_name'        => $params['firstName'],
            'last_name'         => $params['lastName'],
            'secondary_email'   => $params['secondaryEmail'],
            'mobile'            => $params['mobile'],
            'phone'             => $params['phone']
        );
        /* check primary email already exist or not */
        $result = $this->fetchRow("auth_token = '" . $params['authToken'] . "' AND primary_email LIKE '" . $params['primaryEmail'] . "' AND (new_email NOT LIKE '" . $params['primaryEmail'] . "' OR new_email IS NULL)");
        $forceLogin = false;
        if (!$result) {
            $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
            $common = new Application_Service_Common();
            $eptDomain = rtrim($conf->domain, "/");
            $message = "Dear Participant,<br/><br/> You or someone using your email requested to change your ePT login email address from " . $fetchOldMail['primary_email'] . " to " . $params['primaryEmail'] . ". <br/><br/> Please confirm your new primary email by clicking on the following link: <br/><br/><a href='" . $eptDomain . "/auth/verify/email/" . base64_encode($params['primaryEmail']) . "'>" . $eptDomain . "/auth/verify/email/" . base64_encode($params['primaryEmail']) . "</a> <br/><br/> If you are not able to click the link, you can copy and paste it in a browser address bar.<br/><br/> If you did not request for this update, you can safely ignore this email.<br/><br/><small>Thanks,<br/> Online PT Team<br/> <i>Please note: This is a system generated email.</i></small>";
            $fromMail = $common->getConfig('admin_email');
            $fromName = $common->getConfig('admin-name');
            $common->insertTempMail($params['primaryEmail'], null, null, "ePT | Change of login email id", $message, $fromMail, $fromName);
            // $response['status'] = 'force-login';
            $forceLogin = true;
            if ($params['primaryEmail'] != $result['primary_email']) {
                $updateData['new_email'] = $params['primaryEmail'];
            }
            // $this->setStatusByEmail('inactive', $fetchOldMail['primary_email']);
        }
        $response['status'] = 'success';

        $update = $this->update($updateData, "dm_id = " . $fetchOldMail['dm_id']);
        if ($update > 0) {
            if (!$forceLogin || $result) {
                $response['message'] = 'Profile saved successfully.';
            } else {
                $response['message'] = 'Please check your email ' . $params['primaryEmail'] . '. Once you verify, you can use ' . $params['primaryEmail'] . ' to login to ePT.';
            }
        } else {
            $response['status'] = 'fail';
            $response['message'] = 'Not found any update.';
        }
        $response['profileInfo'] = $aResult['profileInfo'];
        return $response;
    }

    public function setStatusByEmail($status, $email)
    {
        return $this->update(array('status' => $status), 'primary_email = "' . $email . '"');
    }

    public function addQuickDm($params, $participantId)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $password = Common::passwordHash($params['dmPassword']);
        $newDmId =  $this->insert([
            'primary_email' => $params['pemail'],
            'password' => $password,
            'data_manager_type' => 'manager',
            'first_name' => $params['pfname'],
            'last_name' => $params['plname'],
            'institute' => $params['instituteName'],
            'phone' => $params['pphone2'],
            'country_id' => $params['country'],
            'mobile' => $params['pphone1'],
            'force_password_reset' => 1,
            'status' => 'active',
            'created_on' => new Zend_Db_Expr('now()'),
            'created_by' => $authNameSpace->admin_id
        ]);
        if ($newDmId) {
            $db = Zend_Db_Table_Abstract::getAdapter();
            $db->insert('participant_manager_map', array('dm_id' => $newDmId, 'participant_id' => $participantId));
        }
    }
    public function mapDataManagerToParticipant($dmId, $participants)
    {
        // Ensure $participants is an array
        if (!is_array($participants)) {
            $participants = [$participants];
        }

        // Get the db adapter
        $db = Zend_Db_Table::getDefaultAdapter();

        // Get the unmapped participants
        $select = $db->select()
            ->from('participant_manager_map', 'participant_id')
            ->where('participant_id NOT IN (?)', $participants);
        $unmappedParticipants = $db->fetchCol($select);

        // Prepare the data for insertion
        $data = [];
        foreach ($unmappedParticipants as $participantId) {
            $data[] = [
                'dm_id' => $dmId,
                'participant_id' => $participantId
            ];
        }

        // Insert all rows in a single query
        if (!empty($data)) {
            $common = new Application_Service_Common();
            return $common->insertMultiple('participant_manager_map', $data, true);
        } else {
            return false;
        }
    }

    public function fetchRelaventPtcc($field, $value)
    {
        // Get the db adapter
        $db = Zend_Db_Table::getDefaultAdapter();
        $select = $db->select()
            ->from('ptcc_countries_map', 'ptcc_id')
            ->group('ptcc_id');
        if (is_array($field)) {
            foreach ($field as $key => $f) {
                $select = $select->orWhere($f . " LIKE '" . $value[$key] . "'");
            }
        } else {
            $select = $select->orWhere($field . " LIKE '" . $value . "'");
        }
        return  $db->fetchCol($select);
    }

    public function mapDataManagerToParticipants($dmIds, $participant, $locations)
    {
        // Ensure $participants is an array
        if (!is_array($dmIds)) {
            $dmIds = [$dmIds];
        }

        // Get the db adapter
        $db = Zend_Db_Table::getDefaultAdapter();

        // Get the mapped participants
        $select = $db->select()
            ->from('participant_manager_map', 'dm_id')
            ->where('dm_id IN (?)', implode(",", $dmIds))
            ->where('participant_id IN (?)', $participant);
        $mappedParticipants = $db->fetchCol($select);

        // Remove the duplications
        if (isset($mappedParticipants) && !empty($mappedParticipants) && count($mappedParticipants) > 0) {
            $dmIds = array_diff($dmIds, $mappedParticipants);
        }
        // Map the unmapped participants
        if (isset($dmIds) && !empty($dmIds) && count($dmIds) > 0) {
            foreach ($dmIds as $dm) {
                $data = [
                    'dm_id' => $dm,
                    'participant_id' => $participant
                ];
                $db->insert('participant_manager_map', $data);
            }
        }
    }

    public function processBulkImport($fileName, $allFakeEmail = false, $params = null)
    {
        try {
            $response = [];
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();

            $objPHPExcel = IOFactory::load($fileName);
            $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $count = count($sheetData);

            // Pre-load cached data to reduce database queries
            $countryCache = $this->buildCountryCache();
            $duplicateChecks = $this->batchCheckDataManagerDuplicates($sheetData);

            // Single transaction for entire operation
            $db->beginTransaction();

            for ($i = 2; $i <= $count; ++$i) {
                $lastInsertedId = 0;

                if (
                    empty($sheetData[$i]['B']) &&
                    empty($sheetData[$i]['C']) &&
                    empty($sheetData[$i]['D']) &&
                    empty($sheetData[$i]['H']) &&
                    empty($sheetData[$i]['I']) &&
                    empty($sheetData[$i]['J']) &&
                    empty($sheetData[$i]['K'])
                ) {
                    continue;
                }

                $sheetData[$i]['B'] = filter_var(trim($sheetData[$i]['B']), FILTER_SANITIZE_EMAIL);
                $sheetData[$i]['K'] = Common::removeEmpty(explode(",", $sheetData[$i]['K'])) ?? [];
                $sheetData[$i]['L'] = Common::removeEmpty(explode(",", $sheetData[$i]['L'])) ?? [];

                $originalEmail = null;
                if (!empty($sheetData[$i]['B']) && filter_var($sheetData[$i]['B'], FILTER_VALIDATE_EMAIL)) {
                    $originalEmail = $sheetData[$i]['B'];
                }

                if (empty($originalEmail) || $allFakeEmail) {
                    $originalEmail = $sheetData[$i]['B'] = MiscUtility::generateFakeEmailId($sheetData[$i]['C'], $sheetData[$i]['D'] . " " . $sheetData[$i]['E']);
                }

                $originalEmail = $originalEmail ?? $sheetData[$i]['B'];

                // Use cached country lookup instead of individual query
                $countryId = $this->getCountryIdFromCache($sheetData[$i]['J'], $countryCache);

                $common = new Application_Service_Common();
                $password = (!isset($sheetData[$i]['M']) || empty($sheetData[$i]['M'])) ? 'ept1@)(*&^' : trim($sheetData[$i]['M']);
                $password = Common::passwordHash($password);

                $dataManagerData = [
                    'first_name'        => ($sheetData[$i]['C']),
                    'last_name'         => ($sheetData[$i]['D']),
                    'institute'         => ($sheetData[$i]['E']),
                    'mobile'            => ($sheetData[$i]['H']),
                    'secondary_email'   => ($sheetData[$i]['F']),
                    'view_only_access'  => ($sheetData[$i]['I']),
                    'country_id'        => $countryId,
                    'primary_email'     => $originalEmail,
                    'password'          => $password,
                    'created_by'        => $authNameSpace->admin_id,
                    'created_on'        => new Zend_Db_Expr('now()'),
                    'data_manager_type' => 'ptcc',
                    'status'            => 'active'
                ];

                // Use cached duplicate check instead of individual query
                $dmresult = $duplicateChecks['dataManagers'][$originalEmail] ?? null;

                if (empty($dmresult)) {
                    $db->insert('data_manager', $dataManagerData);
                    $lastInsertedId = $db->lastInsertId();
                } elseif (isset($params['bulkUploadDuplicateSkip']) && $params['bulkUploadDuplicateSkip'] != 'skip-duplicates') {
                    $db->update('data_manager', $dataManagerData, 'primary_email = "' . $originalEmail . '"');
                    $lastInsertedId = $dmresult['dm_id'];
                } else {
                    $lastInsertedId = $dmresult['dm_id'];
                }

                // PTCC manager location wise mapping
                if (isset($sheetData[$i]['K']) && !empty($sheetData[$i]['K'])) {
                    $sheetData[$i]['K'] = Common::removeEmpty(explode(",", $sheetData[$i]['K'])) ?? [];
                }
                if (isset($sheetData[$i]['L']) && !empty($sheetData[$i]['L'])) {
                    $sheetData[$i]['L'] = Common::removeEmpty(explode(",", $sheetData[$i]['L'])) ?? [];
                }

                if ((isset($sheetData[$i]['J']) && !empty($sheetData[$i]['J'])) ||
                    (isset($sheetData[$i]['K']) && count($sheetData[$i]['K']) > 0) ||
                    (isset($countryId) && !empty($countryId))
                ) {

                    if (isset($lastInsertedId) && !empty(($lastInsertedId))) {
                        $params['district'] = $sheetData[$i]['L'];
                        $params['province'] = $sheetData[$i]['K'];
                        $params['country'] = $countryId;
                        $this->dmParticipantMap($params, $lastInsertedId, true);
                    }
                }
            }

            // Commit the entire transaction at once
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
            throw $e;
        }

        $authNameSpace = new Zend_Session_Namespace('administrators');
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog("Bulk imported participants", "participants");

        $alertMsg->message = 'Your file was imported successfully';
        return $response;
    }

    // Helper methods for optimization
    private function buildCountryCache()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from('countries', ['iso_name', 'iso2', 'iso3', 'id']);
        $results = $db->fetchAll($sql);

        $cache = [];
        foreach ($results as $row) {
            $cache[strtolower($row['iso_name'])] = $row['id'];
            if (!empty($row['iso2'])) {
                $cache[strtolower($row['iso2'])] = $row['id'];
            }
            if (!empty($row['iso3'])) {
                $cache[strtolower($row['iso3'])] = $row['id'];
            }
        }

        return $cache;
    }

    private function batchCheckDataManagerDuplicates($sheetData)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $emails = [];

        for ($i = 2; $i <= count($sheetData); $i++) {
            $email = filter_var(trim($sheetData[$i]['B'] ?? ''), FILTER_SANITIZE_EMAIL);
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            } else {
                // Handle fake email generation case
                if (!empty($sheetData[$i]['C'])) {
                    $fakeEmail = MiscUtility::generateFakeEmailId($sheetData[$i]['C'], ($sheetData[$i]['D'] ?? '') . " " . ($sheetData[$i]['E'] ?? ''));
                    if ($fakeEmail) {
                        $emails[] = $fakeEmail;
                    }
                }
            }
        }

        $existingDataManagers = [];
        if (!empty($emails)) {
            $sql = $db->select()
                ->from('data_manager', ['primary_email', 'dm_id'])
                ->where('primary_email IN (?)', $emails);
            $results = $db->fetchAll($sql);
            foreach ($results as $row) {
                $existingDataManagers[$row['primary_email']] = $row;
            }
        }

        return [
            'dataManagers' => $existingDataManagers
        ];
    }

    private function getCountryIdFromCache($countryInput, $countryCache)
    {
        $countryId = 236; // Default is USA

        if (!empty($countryInput)) {
            $key = strtolower(trim($countryInput));
            $countryId = $countryCache[$key] ?? 236;
        }

        return $countryId;
    }

    public function exportPTCCDetails($params)
    {

        $headings = ['PTCC Name', 'Cell/Mobile', 'Primary Email', 'Status', 'Country', 'State', 'District'];
        if ($params['type'] == 'mapped') {
            $headings[] = 'Participant ID';
            $headings[] = 'Lab Name/Participant Name';
            $headings[] = 'Cell/Mobile';
            $headings[] = 'Email';
        }
        try {
            $excel = new Spreadsheet();

            $output = [];
            $sheet = $excel->getActiveSheet();
            $styleArray = array(
                'font' => array(
                    'bold' => true,
                ),
                'alignment' => array(
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ),
                'borders' => array(
                    'outline' => array(
                        'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ),
                )
            );

            $colNo = 0;
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $ptccQuery = $this->getAdapter()->select()
                ->from(array('u' => $this->_name), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS *')))
                ->joinLeft(array('pcm' => 'ptcc_countries_map'), 'pcm.ptcc_id=u.dm_id', array(
                    'state' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pcm.state SEPARATOR ', ')"),
                    'district' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pcm.district SEPARATOR ', ')")
                ))->joinLeft(array('c' => 'countries'), 'c.id=pcm.country_id', array('c.iso_name'))
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.dm_id=u.dm_id', array())
                ->where("data_manager_type = 'ptcc'")
                ->group('u.dm_id');
            if ($params['type'] == 'mapped') {
                $ptccQuery = $ptccQuery->joinLeft(array('p' => 'participant'), 'pmm.participant_id=p.participant_id', array('unique_identifier', 'labName' => 'lab_name', 'pmobile' => 'mobile', 'email'));
                $ptccQuery = $ptccQuery->group('p.participant_id');
            }
            $totalResult = $db->fetchAll($ptccQuery);

            foreach ($headings as $field => $value) {
                $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNo + 1) . 1)
                    ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNo + 1) . 1, null, null)->getFont()->setBold(true);
                $colNo++;
            }
            if (isset($totalResult) && !empty($totalResult)) {
                foreach ($totalResult as $aRow) {
                    $row = [];
                    $row[] = ucwords($aRow['first_name'] . ' ' . $aRow['last_name']) ?? null;
                    $row[] = $aRow['mobile'] ?? null;
                    $row[] = $aRow['primary_email'] ?? null;
                    $row[] = ucwords($aRow['status']) ?? null;
                    $row[] = ucwords($aRow['iso_name']) ?? null;
                    $row[] = ucwords($aRow['state']) ?? null;
                    $row[] = ucwords($aRow['district']) ?? null;
                    if ($params['type'] == 'mapped') {
                        $row[] = $aRow['unique_identifier'] ?? null;
                        $row[] = ucwords($aRow['labName']) ?? null;
                        $row[] = $aRow['pmobile'] ?? null;
                        $row[] = $aRow['email'] ?? null;
                    }
                    $output[] = $row;
                }
            } else {
                $row = [];
                $row[] = 'No result found';
                $output[] = $row;
            }

            foreach ($output as $rowNo => $rowData) {
                $colNo = 0;
                foreach ($rowData as $field => $value) {
                    if (!isset($value)) {
                        $value = "";
                    }
                    $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 2)
                        ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(100);
                        $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 2, null, null)->getAlignment()->setWrapText(true);
                    }
                    $colNo++;
                }
            }
            foreach (range('A', 'Z') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }
            $tempUploadFolder = realpath(TEMP_UPLOAD_PATH);
            if (!file_exists($tempUploadFolder) && !is_dir($tempUploadFolder)) {
                mkdir($tempUploadFolder);
            }

            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'PTCC-MANAGER-LIST-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save($tempUploadFolder . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            error_log("PTCC-MANAGER-LIST--REPORT-EXCEL--" . $exc->getMessage());
            error_log($exc->getTraceAsString());

            return "";
        }
    }

    public function dmParticipantMap($params, $dmId, bool $isPtcc = false, bool $participantSide = false)
    {
        // echo '<pre>'; print_r($params); die;

        try {
            $db = Zend_Db_Table_Abstract::getAdapter();
            if (!isset($dmId) || empty($dmId)) {
                return false;
            }
            $common = new Application_Service_Common();
            if (!$isPtcc) {

                if ($participantSide) {
                    $params['participantsList'] = (array) $params['participantsList'];
                    $db->delete('participant_manager_map', ['participant_id IN(' . implode(',', $params['participantsList']) . ')']);
                    foreach ($dmId as $dm) {
                        $data[] = [
                            'participant_id' => $params['participantsList'][0],
                            'dm_id' => $dm
                        ];
                    }
                } else {

                    $db->delete('participant_manager_map', array('participant_id NOT IN(' . implode(',', $params['participantsList']) . ')', 'dm_id LIKE ' . $dmId));
                    foreach ($params['participantsList'] as $p) {
                        $data[] = array(
                            'participant_id' => $p,
                            'dm_id' => $dmId
                        );
                    }
                }
                $common->insertMultiple('participant_manager_map', $data, true);
            } elseif ($isPtcc) {
                $params['district'] = isset($params['district']) ? $common->removeEmpty((array)$params['district']) : [];
                $params['province'] = isset($params['province']) ? $common->removeEmpty((array)$params['province']) : [];
                $params['country'] = isset($params['country']) ? $common->removeEmpty((array)$params['country']) : [];
                $locationWiseSwitch = false; //This variable for check if the any one of the location wise participant mapping
                $sql = $db->select()->from(array('p' => 'participant'), array('participant_id')); // Initiate the participants list table

                if (!empty($params['district'])) {
                    $locationWiseSwitch = true;
                    $params['district'] = !is_array($params['district']) ? [$params['district']] : $params['district'];
                    $sql = $sql->where('district IN("' . implode('","', $params['district']) . '")');
                } elseif (!empty($params['province'])) {
                    $locationWiseSwitch = true;
                    $params['province'] = !is_array($params['province']) ? [$params['province']] : $params['province'];
                    $sql = $sql->where('state IN("' . implode('","', $params['province']) . '")');
                } elseif (!empty($params['country'])) {
                    $locationWiseSwitch = true;
                    $params['country'] = !is_array($params['country']) ? [$params['country']] : $params['country'];
                    $sql = $sql->where('country IN("' . implode('","', $params['country']) . '")');
                }

                $pmmData = []; // Declare the participant manager mapping variable
                if ($locationWiseSwitch) { // Check the status activated or not
                    // Fetch list of participants from location wise
                    $locationwiseparticipants = $db->fetchAll($sql);
                    foreach ($locationwiseparticipants as $value) {
                        $pmmData[] = ['dm_id' => $dmId, 'participant_id' => $value['participant_id']]; // Create the inserting data
                        $params['participantsList'][] = $value['participant_id'];
                    }
                    $ptccQuery = $this->getAdapter()->select()
                        ->from(['pmm' => 'participant_manager_map'], [new Zend_Db_Expr('SQL_CALC_FOUND_ROWS *')])
                        ->where("dm_id = ?", $dmId);
                    if ($db->fetchRow($ptccQuery)) {
                        if (!empty($params['participantsList'])) {
                            $db->delete('participant_manager_map', array('participant_id NOT IN(' . implode(',', $params['participantsList']) . ')', 'dm_id LIKE ' . $dmId));
                        }
                        // if (isset($params['province'][0]) && !empty($params['province'][0])) {
                        //     $db->delete('ptcc_countries_map', "ptcc_id = " . $dmId);
                        // }
                    }
                    // Save locatons details
                    // if (isset($params['province'][0]) && !empty($params['province'][0])) {
                    $db->delete('ptcc_countries_map', "ptcc_id = " . $dmId);
                    $this->mapPtccLocations($params, $dmId);
                    // }
                    $common = new Application_Service_Common(); // Common objection creation for accessing the multiinsert functionality
                    if (isset($pmmData) && !empty($pmmData)) {
                        $common->insertMultiple('participant_manager_map', $pmmData, true); // Inserting the mulitiple pmm data at one go
                    }
                }
            }
        } catch (Exception $e) {
            // If any of the queries failed and threw an exception,
            // we want to roll back the whole transaction, reversing
            // changes made in the transaction, even those that succeeded.
            // Thus all changes are committed together, or none are.
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
        }
    }

    public function setLoginAtempBan($email)
    {
        return $this->update(array('login_ban' => 'yes'), 'primary_email = "' . $email . '"');
    }

    public function fethDataByCredentials($email, $password)
    {

        $sql = $this->select()->from('data_manager')->where("primary_email = '" . $email . "' OR password = '" . $password . "'");
        return $this->fetchRow($sql);
    }

    public function fetchDataManaersByParticipantId($participantId)
    {
        $query = $this->getAdapter()->select()
            ->from(['pmm' => 'participant_manager_map'], [])
            ->joinLeft(array('dm' => 'data_manager'), 'pmm.dm_id=dm.dm_id', ['dm_id', 'first_name', 'last_name', 'institute', 'primary_email'])
            ->where("pmm.participant_id = ?", $participantId);
        return $this->getAdapter()->fetchAll($query);
    }
}
