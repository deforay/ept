<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
class Application_Model_DbTable_Participants extends Zend_Db_Table_Abstract
{

    protected $_name = 'participant';
    protected $_primary = 'participant_id';

    public function getParticipantsByUserSystemId($userSystemId)
    {
        $sql = $this->getAdapter()->select()->from(array('p' => $this->_name))
            ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array('data_manager' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pmm.dm_id SEPARATOR ', ')")))
            ->where("pmm.dm_id = ?", $userSystemId)
            //->where("p.status = 'active'")
            ->group('p.participant_id');
        return $this->getAdapter()->fetchAll($sql);
    }

    public function checkParticipantAccess($participantId, $dmId = '', $comingFrom = '')
    {
        if ($comingFrom != 'API') {
            $authNameSpace =  new Zend_Session_Namespace('datamanagers');
            $dmId = $authNameSpace->dm_id;
        }

        $sql = $this->getAdapter()->select()
            ->from(array('pmm' => 'participant_manager_map'))
            ->where("pmm.participant_id = ?", $participantId);

        if (isset($dmDb) && !empty($dmDb) && $dmDb != "") {
            $sql = $sql->where("pmm.dm_id = ?", $dmId);
        }
        $row = $this->getAdapter()->fetchRow($sql);
        if ($row === false) {
            return false;
        } else {
            return true;
        }
    }

    public function checkShipmentParticipantsEnrollment($enId)
    {
        return $this->getAdapter()
            ->fetchRow($this->getAdapter()->select()->from(array('d' => 'distributions'))
                ->join(array('s' => 'shipment'), 's.distribution_id=d.distribution_id', array('shipments' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT s.shipment_code SEPARATOR ', ')")))
                ->join(array('spm' => 'shipment_participant_map'), 's.shipment_id=spm.shipment_id')
                ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_name'))
                ->group('d.distribution_id')
                ->where("d.distribution_id = ?", $enId));
    }

    public function getParticipant($partSysId)
    {
        $sQuery = $this->getAdapter()->select()->from(array('p' => $this->_name))
            ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array('data_manager' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pmm.dm_id SEPARATOR ', ')")))
            ->joinLeft(array('pe' => 'participant_enrolled_programs_map'), 'pe.participant_id=p.participant_id', array('enrolled_prog' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pe.ep_id SEPARATOR ', ')")))
            ->joinLeft(array('site' => 'r_site_type'), 'site.r_stid=p.site_type', array('siteType' => 'site_type'))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country', array('iso_name'))
            ->where("p.participant_id = ?", $partSysId)
            ->group('p.participant_id');
        // $authNameSpace = new Zend_Session_Namespace('datamanagers');
        // if (isset($authNameSpace->mappedParticipants) && !empty($authNameSpace->mappedParticipants) && empty($partSysId)) {
        //     $sQuery = $sQuery
        //         ->where("pmm.participant_id IN(" . $authNameSpace->mappedParticipants . ")");
        // }
        return $this->getAdapter()->fetchRow($sQuery);
    }

    public function getAllParticipants($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('unique_identifier', new Zend_Db_Expr("CONCAT(COALESCE(p.first_name,''),' ', COALESCE(p.last_name,''))"), 'iso_name', 'mobile', 'phone', 'affiliation', 'email', 'status');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = "participant_id";
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
        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder[] = $aColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]);
                }
            }
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

        /*
         * SQL queries
         * Get data to display
         */

        $sQuery = $this->getAdapter()->select()->from(array('p' => $this->_name), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS p.participant_id'), 'p.unique_identifier', 'p.institute_name', 'p.country', 'p.mobile', 'p.phone', 'p.affiliation', 'p.email', 'p.status', 'participantName' => new Zend_Db_Expr("CONCAT(COALESCE(p.first_name,''),' ', COALESCE(p.last_name,''))"), 'mapCount' => new Zend_Db_Expr("COUNT(spm.map_id)")))
            ->join(array('c' => 'countries'), 'c.id=p.country')
            ->joinLeft(array('spm' => 'shipment_participant_map'), 'spm.participant_id=p.participant_id', array())
            ->group("p.participant_id");

        if (isset($parameters['withStatus']) && $parameters['withStatus'] != "") {
            $sQuery = $sQuery->where("p.status = ? ", $parameters['withStatus']);
        }
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
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

        // echo ($sQuery);die;
        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $sQuerySession = new Zend_Session_Namespace('respondedParticipantsExcel');
        $sQuerySession->shipmentRespondedParticipantQuery = $sQuery;

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
        $adminSession = new Zend_Session_Namespace('administrators');
        if ($adminSession->privileges != "") {
            $pstatus = false;
            $privileges = explode(',', $adminSession->privileges);
        } else {
            $pstatus = true;
            $privileges = [];
        }
        $deleteStatus = false;
        if (!$pstatus && in_array('delete-participants', $privileges)) {
            $deleteStatus = true;
        }
        foreach ($rResult as $aRow) {
            $edit = "";
            $delete = "";
            $row = [];
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['participantName'];
            $row[] = $aRow['iso_name'];
            $row[] = $aRow['mobile'];
            $row[] = $aRow['phone'];
            $row[] = $aRow['affiliation'];
            $row[] = $aRow['email'];
            $row[] = ucwords($aRow['status']);
            if (isset($parameters['from']) && $parameters['from'] == 'participant') {
                $edit = '<a href="/participant/edit-participant/id/' . $aRow['participant_id'] . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';
            } else {
                $edit = '<a href="/admin/participants/edit/id/' . $aRow['participant_id'] . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';
            }
            if ($aRow['mapCount'] == 0 && $deleteStatus) {
                //$delete = '<a href="javascript:void(0);" onclick="deleteParticipant(' . $aRow['participant_id'] . ');" class="btn btn-danger btn-xs" style="margin-right: 2px;"><i class="icon-trash"></i> Delete</a>';
            }
            $row[] = $edit . $delete;
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function updateParticipant($params)
    {
        $firstName = isset($params['pfname']) && $params['pfname'] != '' ? $params['pfname'] :  NULL;
        $lastName =  isset($params['plname']) && $params['plname'] != '' ? $params['plname'] :  NULL;
        $authNameSpace = new Zend_Session_Namespace('datamanagers');

        $data = array(
            'unique_identifier' => $params['pid'],
            'institute_name' => $params['instituteName'],
            'department_name' => $params['departmentName'],
            'address' => $params['address'],
            'country' => $params['country'],
            'region' => $params['region'],
            'state' => $params['state'],
            'district' => $params['district'],
            'city' => $params['city'],
            'zip' => $params['zip'],
            'long' => $params['long'],
            'lat' => $params['lat'],
            'shipping_address' => $params['shippingAddress'],
            'first_name' => $params['pfname'],
            'last_name' => $params['plname'],
            'mobile' => $params['pphone2'],
            'phone' => $params['pphone1'],
            'email' => $params['pemail'],
            'additional_email' => $params['additionalEmail'],
            'contact_name' => $params['contactname'],
            'affiliation' => $params['partAff'],
            'network_tier' => $params['network'],
            'testing_volume' => $params['testingVolume'],
            'funding_source' => $params['fundingSource'],
            'site_type' => $params['siteType'],
            'anc' => $params['anc'],
            'pepfar_id' => $params['pepfarID'],
            'updated_on' => new Zend_Db_Expr('now()')
        );
        if (isset($params['comingFrom']) && $params['comingFrom'] == 'participant') {
            $data['force_profile_updation'] = 0;
        }

        if (isset($params['individualParticipant']) && $params['individualParticipant'] == 'on') {
            $data['individual'] = 'yes';
        } else {
            $data['individual'] = 'no';
        }

        if (isset($params['status']) && $params['status'] != "" && $params['status'] != null) {
            $data['status'] = $params['status'];
        }

        if (isset($authNameSpace->dm_id) && $authNameSpace->dm_id != "") {
            $data['updated_by'] = $authNameSpace->dm_id;
        } else {
            $authNameSpace = new Zend_Session_Namespace('administrators');
            if (isset($authNameSpace->primary_email) && $authNameSpace->primary_email != "") {
                $data['updated_by'] = $authNameSpace->primary_email;
            }
        }

        // Zend_Debug::dump($data);die;
        $noOfRows = $this->update($data, "participant_id = " . $params['participantId']);
        //echo $authNameSpace->force_profile_updation =1;
        //Check profile update
        if (isset($authNameSpace->force_profile_updation) && trim($authNameSpace->force_profile_updation) > 0) {
            $profileUpdate = $this->checkParticipantsProfileUpdateByUserSystemId($authNameSpace->dm_id);
            if (count($profileUpdate) > 0) {
                $authNameSpace->profile_updation_pid = $profileUpdate[0]['participant_id'];
            } else {
                $authNameSpace->force_profile_updation = 0;
                $authNameSpace->profile_updation_pid = "";
            }
        }

        $db = Zend_Db_Table_Abstract::getAdapter();

        if (isset($params['enrolledProgram']) && $params['enrolledProgram'] != "") {
            $db->delete('participant_enrolled_programs_map', "participant_id = " . $params['participantId']);
            foreach ($params['enrolledProgram'] as $epId) {
                $db->insert('participant_enrolled_programs_map', array('ep_id' => $epId, 'participant_id' => $params['participantId']));
            }
        }
        if (isset($params['dataManager']) && $params['dataManager'] != "") {
            $dm = new Application_Model_DbTable_DataManagers();
            $params['participantsList'][] = $params['participantId'];
            $dm->dmParticipantMap($params, $params['dataManager'], false, true);
            /* $db->delete('participant_manager_map', "participant_id = " . $params['participantId']);
            foreach ($params['dataManager'] as $dataManager) {
                $db->insert('participant_manager_map', array('dm_id' => $dataManager, 'participant_id' => $params['participantId']));
            } */
        }
        /* if (isset($params['country']) && !empty($params['country'])) {
            $db->query("DELETE FROM participant_manager_map WHERE participant_id = ? AND dm_id in (select dm_id from data_manager where IFNULL(`ptcc`, 'no') like 'yes')", [$params['participantId']]);

            $dmDb = new Application_Model_DbTable_DataManagers();
            $result = $dmDb->fetchRelaventPtcc(array('country_id', 'state', 'district'), array($params['country'], $params['state'], $params['district']));
            if (isset($params['dataManager']) && !empty($params['dataManager'])) {
                $result = array_merge($params['dataManager'], $result);
            }
            $dmDb->mapDataManagerToParticipants($result, $params['participantId'], array($params['country'], $params['state'], $params['district']));
        } */
        if (isset($params['scheme']) && $params['scheme'] != "") {
            $enrollDb = new Application_Model_DbTable_Enrollments();
            $enrollDb->enrollParticipantToSchemes($params['participantId'], $params['scheme']);
        }

        if ($noOfRows > 0) {
            $name = $firstName . " " . $lastName;
            $userName = isset($name) != '' ? $name : $authNameSpace->primary_email;
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Updated Participant - " . $userName, "participants");
        }

        return $noOfRows;
    }

    public function addParticipant($params)
    {
        $firstName = isset($params['pfname']) && $params['pfname'] != '' ? $params['pfname'] :  NULL;
        $lastName =  isset($params['plname']) && $params['plname'] != '' ? $params['plname'] :  NULL;
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $data = array(
            'unique_identifier' => $params['pid'],
            'institute_name' => $params['instituteName'],
            'department_name' => $params['departmentName'],
            'address' => $params['address'],
            'country' => $params['country'],
            'region' => $params['region'],
            'state' => $params['state'],
            'district' => $params['district'],
            'city' => $params['city'],
            'zip' => $params['zip'],
            'long' => $params['long'],
            'lat' => $params['lat'],
            'shipping_address' => $params['shippingAddress'],
            'first_name' => $params['pfname'],
            'last_name' => $params['plname'],
            'mobile' => $params['pphone2'],
            'phone' => $params['pphone1'],
            'email' => $params['pemail'],
            'additional_email' => $params['additionalEmail'],
            'contact_name' => $params['contactname'],
            'affiliation' => $params['partAff'],
            'network_tier' => $params['network'],
            'testing_volume' => $params['testingVolume'],
            'funding_source' => $params['fundingSource'],
            'site_type' => $params['siteType'],
            'anc' => $params['anc'],
            'pepfar_id' => $params['pepfarID'],
            'created_on' => new Zend_Db_Expr('now()'),
            'created_by' => $authNameSpace->primary_email,
            'status' => $params['status']
        );
        if (isset($params['individualParticipant']) && $params['individualParticipant'] == 'on') {
            $data['individual'] = 'yes';
        } else {
            $data['individual'] = 'no';
        }

        $participantId = $this->insert($data);

        $db = Zend_Db_Table_Abstract::getAdapter();

        if (isset($params['dataManager']) && $params['dataManager'] != "") {
            $dm = new Application_Model_DbTable_DataManagers();
            $params['participantsList'][] = $participantId;
            $dm->dmParticipantMap($params, $params['dataManager'], false, true);
            /* $db->delete('participant_manager_map', "participant_id = " . $participantId);
            foreach ($params['dataManager'] as $dataManager) {
                $db->insert('participant_manager_map', array('dm_id' => $dataManager, 'participant_id' => $participantId));
            } */
        } else {
            if (isset($params['dmPassword']) && $params['dmPassword'] != "") {
                $dmDb = new Application_Model_DbTable_DataManagers();
                $dmDb->addQuickDm($params, $participantId);
            }
        }

        /* if (isset($params['country']) && !empty($params['country'])) {
            $db->query("DELETE FROM participant_manager_map WHERE participant_id = ? AND dm_id in (select dm_id from data_manager where IFNULL(`ptcc`, 'no') like 'yes')", [$participantId]);

            $dmDb = new Application_Model_DbTable_DataManagers();
            $result = $dmDb->fetchRelaventPtcc(array('country_id', 'state', 'district'), array($params['country'], $params['state'], $params['district']));
            if (isset($params['dataManager']) && !empty($params['dataManager'])) {
                $result = array_merge($params['dataManager'], $result);
            }
            $dmDb->mapDataManagerToParticipants($result, $participantId, array($params['country'], $params['state'], $params['district']));
        } */
        if (isset($params['enrolledProgram']) && $params['enrolledProgram'] != "") {
            foreach ($params['enrolledProgram'] as $epId) {
                $db->insert('participant_enrolled_programs_map', array('ep_id' => $epId, 'participant_id' => $participantId));
            }
        }

        if ($participantId > 0) {
            $name = $firstName . " " . $lastName;
            $userName = isset($name) != '' ? $name : $authNameSpace->primary_email;
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Added a new participant - " . $userName, "participants");
        }
        return $participantId;
    }

    public function addParticipantForDataManager($params)
    {
        //Zend_Debug::dump($params);die;
        $authNameSpace = new Zend_Session_Namespace('datamanagers');

        $data = array(
            'unique_identifier' => $params['pid'],
            'institute_name' => $params['instituteName'],
            'department_name' => $params['departmentName'],
            'address' => $params['address'],
            'city' => $params['city'],
            'state' => $params['state'],
            'zip' => $params['zip'],
            'country' => $params['country'],
            'long' => $params['long'],
            'lat' => $params['lat'],
            'shipping_address' => $params['shippingAddress'],
            'first_name' => $params['pfname'],
            'last_name' => $params['plname'],
            'mobile' => $params['pphone2'],
            'phone' => $params['pphone1'],
            'contact_name' => $params['contactname'],
            'email' => $params['pemail'],
            'affiliation' => $params['partAff'],
            'network_tier' => $params['network'],
            'status' => 'pending',
            'testing_volume' => $params['testingVolume'],
            'funding_source' => $params['fundingSource'],
            'region' => $params['region'],
            'site_type' => $params['siteType'],
            'created_on' => new Zend_Db_Expr('now()'),
            'created_by' => $authNameSpace->UserID,
        );

        if (isset($params['individualParticipant']) && $params['individualParticipant'] == 'on') {
            $data['individual'] = 'yes';
        } else {
            $data['individual'] = 'no';
        }


        //Zend_Debug::dump($data);die;
        //Zend_Debug::dump($data);die;
        $participantId = $this->insert($data);


        if (isset($params['enrolledProgram']) && $params['enrolledProgram'] != "") {
            $db = Zend_Db_Table_Abstract::getAdapter();
            $db->delete('participant_enrolled_programs_map', "participant_id = " . $participantId);
            foreach ($params['enrolledProgram'] as $epId) {
                $db->insert('participant_enrolled_programs_map', array('ep_id' => $epId, 'participant_id' => $participantId));
            }
        }

        if (isset($params['scheme']) && $params['scheme'] != "") {
            $enrollDb = new Application_Model_DbTable_Enrollments();
            $enrollDb->enrollParticipantToSchemes($participantId, $params['scheme']);
        }

        $db = Zend_Db_Table_Abstract::getAdapter();
        $db->insert('participant_manager_map', array('dm_id' => $authNameSpace->dm_id, 'participant_id' => $participantId));

        $participantName = $params['pfname'] . " " . $params['plname'];
        $dataManager = $authNameSpace->first_name . " " . $authNameSpace->last_name;
        $common = new Application_Service_Common();
        $message = "Hi,<br/>  A new participant ($participantName) was added by $dataManager <br/><small>This is a system generated email. Please do not reply.</small>";
        $toMail = Application_Service_Common::getConfig('admin_email');
        //$fromName = Application_Service_Common::getConfig('admin-name');
        $common->sendMail($toMail, null, null, "New Participant Registered  ($participantName)", $message, null, "ePT Admin");

        return $participantId;
    }

    public function fetchAllActiveParticipants()
    {
        return $this->fetchAll($this->select()->where("status='active'")->order("first_name"));
    }

    public function getSchemeWiseParticipants($schemeType)
    {
        if ($schemeType != "all") {
            $result = $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(array('p' => $this->_name), array('p.address', 'p.long', 'p.lat', 'p.first_name', 'p.last_name'))
                ->join(array('e' => 'enrollments'), 'e.participant_id=p.participant_id')
                ->where("e.scheme_id = ?", $schemeType)
                ->where("p.status='active'")
                ->group('p.participant_id'));
        } else {
            $result = $this->fetchAll($this->select()->where("status='active'"));
        }

        return $result;
    }

    public function getEnrolledByShipmentDetails($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('first_name', 'iso_name', 'mobile', 'email', 'p.status');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = "participant_id";

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
        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder[] = $aColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]);
                }
            }
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

        /*
         * SQL queries
         * Get data to display
         */


        $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'), new Zend_Db_Expr('SQL_CALC_FOUND_ROWS p.*'))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array('sp.map_id', 'sp.created_on_user', 'sp.attributes', 'sp.final_result', 'sp.shipment_test_date', "RESPONSE" => new Zend_Db_Expr("CASE WHEN (sp.is_excluded ='yes') THEN 'Excluded'  WHEN (sp.shipment_test_date!='' AND sp.shipment_test_date!='0000-00-00' AND sp.shipment_test_date!='NULL') THEN 'Responded' ELSE 'Not Responded' END")))
            ->join(array('s' => 'shipment'), 'sp.shipment_id=s.shipment_id', array('shipmentStatus' => 's.status'))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country', array('c.iso_name'))
            ->where("p.status='active'");

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ? ", $parameters['shipmentId']);
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

        //error_log($sQuery);

        $rResult = $this->getAdapter()->fetchAll($sQuery);

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
            $row[] = ucwords($aRow['first_name'] . " " . $aRow['last_name']);
            $row[] = ucwords($aRow['iso_name']);
            $row[] = $aRow['mobile'];
            $row[] = $aRow['email'];
            $row[] = ucwords($aRow['RESPONSE']);

            if (trim($aRow['created_on_user']) == "" && trim($aRow['final_result']) == "" && $aRow['shipmentStatus'] != 'finalized') {
                $row[] = '<a href="javascript:void(0);" onclick="removeParticipants(\'' . base64_encode($aRow['map_id']) . '\',\'' . base64_encode($aRow['shipment_id']) . '\')" class="btn btn-primary btn-xs"><i class="icon-remove"></i> Delete</a>';
            } else {
                $row[] = '';
            }

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getUnEnrolledByShipments($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('first_name', 'iso_name', 'mobile', 'email', 'p.status');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = "participant_id";

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
        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder[] = $aColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]);
                }
            }
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

        /*
         * SQL queries
         * Get data to display
         */


        $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS p.participant_id')))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array())
            ->join(array('s' => 'shipment'), 'sp.shipment_id=s.shipment_id', array())
            ->where("p.status='active'");

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ? ", $parameters['shipmentId']);
        }

        $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS p.*')))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country', array('c.iso_name'))->where("p.status='active'")->where("p.participant_id NOT IN ?", $sQuery);

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $this->getAdapter()->fetchAll($sQuery);

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
            $row[] = '<input type="checkbox" class="isRequired" name="participants[]" id="' . $aRow['participant_id'] . '" value="' . base64_encode($aRow['participant_id']) . '" onclick="checkParticipantName(' . $aRow['participant_id'] . ',this)" title="Select atleast one participant">';
            $row[] = ucwords($aRow['first_name'] . " " . $aRow['last_name']);
            $row[] = ucwords($aRow['iso_name']);
            $row[] = $aRow['mobile'];
            $row[] = $aRow['email'];
            // $row[] = ucwords($aRow['status']);
            $row[] = 'Unenrolled';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
    public function addParticipantManager($params, $type = null)
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        try {
            // Zend_Debug::dump($_FILES);die;
            $db = Zend_Db_Table_Abstract::getAdapter();

            $dataForStatistics = [];
            if (isset($_FILES['bulkMap']['tmp_name']) && !empty($_FILES['bulkMap']['tmp_name'])) {
                $common = new Application_Service_Common();
                $allowedExtensions = array('xls', 'xlsx', 'csv');
                $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['bulkMap']['name']);
                $fileName = str_replace(" ", "-", $fileName);
                $random = $common->generateRandomString(6);
                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $fileName = $random . "-" . $fileName;
                if (in_array($extension, $allowedExtensions)) {
                    if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName)) {
                        if (move_uploaded_file($_FILES['bulkMap']['tmp_name'], TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName)) {
                            $objPHPExcel = IOFactory::load(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName);

                            $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);

                            unset($sheetData[1]);

                            $authNameSpace = new Zend_Session_Namespace('administrators');

                            foreach ($sheetData as $row) {

                                $row['D'] = filter_var(trim($row['D']), FILTER_SANITIZE_EMAIL);
                                $row['E'] = filter_var(trim($row['E']), FILTER_SANITIZE_EMAIL);

                                if (empty($row['B']) || empty($row['D'])) {
                                    continue;
                                }

                                // Duplications check
                                $psql = $db->select()
                                    ->from('participant')
                                    ->where("unique_identifier LIKE ?", trim((string) $row['B']));
                                $participantRow = $db->fetchRow($psql);

                                if (empty($participantRow) || $participantRow === false) {
                                    continue;
                                }

                                /* To check the duplication in data manager table */
                                $dmsql = $db->select()->from('data_manager')
                                    ->where("primary_email LIKE ?", $row['D']);
                                $dmresult = $db->fetchRow($dmsql);
                                if (empty($dmresult) || $dmresult === false) {


                                    $dataManagerData = [
                                        'first_name'        => $row['C'],
                                        'primary_email'     => $row['D'],
                                        'secondary_email'   => $row['E'],
                                        'mobile'            => $row['F'],
                                        'password'          => 'ept1@)(*&^',
                                        'force_password_reset' => 0,
                                        'force_profile_check' => 0,
                                        'ptcc' => 'no',
                                        'created_by'        => $authNameSpace->admin_id,
                                        'created_on'        => new Zend_Db_Expr('now()'),
                                        'status'            => 'active'
                                    ];



                                    $db->insert('data_manager', $dataManagerData);
                                    $dmId = $db->lastInsertId();
                                } else {
                                    $dmId = $dmresult['dm_id'];
                                }

                                $participantId = $participantRow['participant_id'];


                                if ($dmId > 0) {
                                    $dmData = ['dm_id' => $dmId, 'participant_id' => $participantId];
                                    $common = new Application_Service_Common();
                                    $common->insertIgnore('participant_manager_map', $dmData);
                                }
                            }
                            $authNameSpace = new Zend_Session_Namespace('administrators');
                            $auditDb = new Application_Model_DbTable_AuditLog();
                            $auditDb->addNewAuditLog("Bulk imported participants map", "data-managers");
                            $alertMsg->message = 'Mapping completed successfully';
                        } else {
                            $alertMsg->message = 'Mapping import failed';
                            return false;
                        }
                    } else {
                        $alertMsg->message = 'File not uploaded. Please try again.';
                        return false;
                    }
                } else {
                    $alertMsg->message = 'File format not supported';
                    return false;
                }
            } else {
                if (isset($params['datamanagerId']) && $params['datamanagerId'] != "") {
                    $dm = new Application_Model_DbTable_DataManagers();
                    $params['participantsList'] = json_decode($params['selectedForMapping'], true);
                    $dm->dmParticipantMap($params, $params['dataManager'], false);

                    /* $db->delete('participant_manager_map', "dm_id = " . $params['datamanagerId']);
                    if ($type == null || $type != 'participant-side') {
                        $params['participants'] = json_decode($params['selectedForMapping'], true);
                        foreach ($params['participants'] as $participants) {
                            $db->insert('participant_manager_map', array('participant_id' => $participants, 'dm_id' => $params['datamanagerId']));
                        }
                    }*/

                    $alertMsg->message = "Participants mapped successfully";
                }


                if (isset($params['participantId']) && $params['participantId'] != "") {
                    $dm = new Application_Model_DbTable_DataManagers();
                    $params['participantsList'][] = $params['participantId'];
                    $dm->dmParticipantMap($params, $params['dataManager'], false);
                    
                    /* $db->delete('participant_manager_map', "participant_id = " . $params['participantId']);
                    foreach ($params['datamangers'] as $datamangers) {
                        $db->insert('participant_manager_map', array('dm_id' => $datamangers, 'participant_id' => $params['participantId']));
                    } */
                    $alertMsg->message = "Datamanager mapped successfully";
                }
            }
        } catch (Exception $exc) {
            error_log("IMPORT-PARTICIPANTS-MAP-EXCEL--" . $exc->getMessage());
            error_log($exc->getTraceAsString());
            $alertMsg->message = 'File not uploaded. Something went wrong please try again later!';
            return false;
        }
    }

    public function getShipmentRespondedParticipants($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('unique_identifier', new Zend_Db_Expr("CONCAT(COALESCE(p.first_name,''),' ', COALESCE(p.last_name,''))"), 'institute_name', 'state', 'district', 'iso_name', 'mobile', 'phone', 'affiliation', 'email', 'status');

        /* Indexed column (used for fast and accurate table cardinality) */
        //  $sIndexColumn = "participant_id";
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
        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder[] = $aColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]);
                }
            }
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

        /*
         * SQL queries
         * Get data to display
         */

        $sQuery = $this->getAdapter()->select()->from(array('sp' => 'shipment_participant_map'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS sp.map_id'), 'sp.shipment_id', 'sp.participant_id', 'sp.shipment_test_date', "RESPONSE" => new Zend_Db_Expr("CASE WHEN (sp.is_excluded ='yes') THEN 'Excluded'  WHEN (sp.shipment_test_date not like '' AND sp.shipment_test_date!='0000-00-00' AND sp.shipment_test_date is not NULL) THEN 'Responded' ELSE 'Not Responded' END")))
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.participant_id', 'p.unique_identifier', 'p.institute_name', 'p.country', 'p.state', 'p.district', 'p.mobile', 'p.phone', 'p.affiliation', 'p.email', 'p.status', 'participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country')
            ->where("sp.shipment_test_date <>'0000-00-00'")
            ->where("sp.shipment_test_date IS NOT NULL ")
            ->where("sp.shipment_id = ?", $parameters['shipmentId'])
            ->group("sp.participant_id");
        //  error_log($sQuery);
        if (isset($parameters['withStatus']) && $parameters['withStatus'] != "") {
            $sQuery = $sQuery->where("p.status = ? ", $parameters['withStatus']);
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

        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);

        $sQuerySession = new Zend_Session_Namespace('respondedParticipantsExcel');
        $sQuerySession->shipmentRespondedParticipantQuery = $sQuery;

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
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['participantName'];
            $row[] = $aRow['institute_name'];
            $row[] = $aRow['state'];
            $row[] = $aRow['district'];
            $row[] = $aRow['iso_name'];
            $row[] = $aRow['mobile'];
            $row[] = $aRow['phone'];
            $row[] = $aRow['affiliation'];
            $row[] = $aRow['email'];
            // $row[] = '<a href="javascript:void(0);" onclick="removeParticipants(\'' . base64_encode($aRow['map_id']) . '\',\''.base64_encode($aRow['shipment_id']).'\')" class="btn btn-primary btn-xs"><i class="icon-remove"></i> Delete</a>';
            $row[] = ucwords($aRow['RESPONSE']);

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
    public function getShipmentNotRespondedParticipants($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('unique_identifier', new Zend_Db_Expr("CONCAT(COALESCE(p.first_name,''),' ', COALESCE(p.last_name,''))"), 'p.institute_name', 'p.state', 'p.district', 'iso_name', 'mobile', 'phone', 'affiliation', 'email', 'status');

        /* Indexed column (used for fast and accurate table cardinality) */
        //  $sIndexColumn = "participant_id";
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
        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder[] = $aColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]);
                }
            }
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

        /*
         * SQL queries
         * Get data to display
         */
        $sQuery = $this->getAdapter()->select()->from(array('sp' => 'shipment_participant_map'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS sp.map_id'), 'sp.participant_id',  'sp.shipment_test_date', 'shipment_id', "RESPONSE" => new Zend_Db_Expr("CASE WHEN (sp.is_excluded ='yes') THEN 'Excluded'  WHEN (sp.shipment_test_date not like '' AND sp.shipment_test_date!='0000-00-00' AND sp.shipment_test_date not like 'NULL') THEN 'Responded' ELSE 'Not Responded' END")))
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.participant_id', 'p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.city', 'p.state', 'p.district', 'p.country', 'p.mobile', 'p.state', 'p.phone', 'p.affiliation', 'p.email', 'p.phone', 'p.status', 'participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country')
            // ->where("(sp.shipment_test_date = '0000-00-00' OR sp.shipment_test_date IS NULL)")
            ->where("(sp.shipment_test_report_date IS NULL OR DATE(sp.shipment_test_report_date) = '0000-00-00' OR response_status like 'noresponse')")
            ->where("sp.shipment_id = ?", $parameters['shipmentId'])
            ->group("sp.participant_id");

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }


        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);

        $sQuerySession = new Zend_Session_Namespace('notRespondedParticipantsExcel');
        $sQuerySession->shipmentRespondedParticipantQuery = $sQuery;

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
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['participantName'];
            $row[] = $aRow['institute_name'];
            $row[] = $aRow['state'];
            $row[] = $aRow['district'];
            $row[] = $aRow['iso_name'];
            $row[] = $aRow['mobile'];
            $row[] = $aRow['phone'];
            $row[] = $aRow['affiliation'];
            $row[] = $aRow['email'];
            $row[] = ucwords($aRow['RESPONSE']);
            $row[] = '<a href="javascript:void(0);" onclick="removeParticipants(\'' . base64_encode($aRow['map_id']) . '\',\'' . base64_encode($aRow['shipment_id']) . '\')" class="btn btn-primary btn-xs"><i class="icon-remove"></i> Delete</a>';
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getShipmentNotEnrolledParticipants($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('first_name', 'unique_identifier', new Zend_Db_Expr("CONCAT(COALESCE(p.first_name,''),' ', COALESCE(p.last_name,''))"), 'institute_name', 'state', 'district', 'iso_name', 'mobile', 'phone', 'affiliation', 'email', 'p.status');

        /* Indexed column (used for fast and accurate table cardinality) */
        //  $sIndexColumn = "participant_id";
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
        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder[] = $aColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]);
                }
            }
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

        /*
         * SQL queries
         * Get data to display
         */
        $subSql = $this->getAdapter()->select()->from(array('sp' => 'shipment_participant_map'), array('sp.participant_id'))
            ->where("sp.shipment_id = ?", $parameters['shipmentId'])
            ->group("sp.participant_id");

        $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS p.participant_id'), 'p.unique_identifier', 'p.institute_name', 'p.country', 'p.state', 'p.district', 'p.mobile', 'p.phone', 'p.affiliation', 'p.email', 'p.status', 'participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country')
            ->where("p.participant_id NOT IN ?", $subSql)
            ->where("p.status='active'")
            // ->order('first_name')
            ->group("p.participant_id");

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }


        $rResult = $this->getAdapter()->fetchAll($sQuery);

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

            $row[] = '<input type="checkbox" class="checkParticipants" id="chk' . base64_encode($aRow['participant_id']) . '"  value="' . base64_encode($aRow['participant_id']) . '" onclick="toggleSelect(this);"  />';
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['participantName'];
            $row[] = $aRow['institute_name'];
            $row[] = $aRow['state'];
            $row[] = $aRow['district'];
            $row[] = $aRow['iso_name'];
            $row[] = $aRow['mobile'];
            $row[] = $aRow['phone'];
            $row[] = $aRow['affiliation'];
            $row[] = $aRow['email'];
            $row[] = '<a href="javascript:void(0);" onclick="enrollParticipants(\'' . base64_encode($aRow['participant_id']) . '\',\'' .  base64_encode($parameters['shipmentId']) . '\')" class="btn btn-primary btn-xs"> Enroll</a>';
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function checkParticipantsProfileUpdateByUserSystemId($userSystemId)
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(array('p' => $this->_name))
            ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array('data_manager' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pmm.dm_id SEPARATOR ', ')")))
            ->where("pmm.dm_id = ?", $userSystemId)
            // ->where("p.force_profile_updation = ?", 1)
            ->group('p.participant_id'));
    }

    public function fetchUniqueCountry()
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(array('p' => $this->_name), array('country' => new Zend_Db_Expr(" DISTINCT con.iso_name "), "id" => "con.id"))
            ->join(array('con' => 'countries'), 'con.id=p.country')->where("p.status='active'")->where("p.country IS NOT NULL")->where("trim(p.country)!=''"));
    }

    public function fetchUniqueDistrict()
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(array('p' => $this->_name), array('district' => new Zend_Db_Expr(" DISTINCT p.district ")))
            ->where("p.status='active'")->where("p.district IS NOT NULL")->where("trim(p.district)!=''"));
    }

    public function fetchUniqueRegion()
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(array('p' => $this->_name), array('region' => new Zend_Db_Expr(" DISTINCT p.region ")))
            ->where("p.status='active'")->where("p.region IS NOT NULL")->where("trim(p.region)!=''"));
    }

    public function fetchUniqueState()
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(array('p' => $this->_name), array('state' => new Zend_Db_Expr(" DISTINCT p.state ")))
            ->where("p.status='active'")->where("p.state IS NOT NULL")->where("trim(p.state)!=''"));
    }
    public function fetchUniqueCity()
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(array('p' => $this->_name), array('city' => new Zend_Db_Expr(" DISTINCT p.city ")))
            ->where("p.status='active'")->where("p.city IS NOT NULL")->where("trim(p.city)!=''"));
    }

    public function fetchParticipantSearch($search)
    {
        $sql = $this->select();
        $sql =  $sql->where("first_name LIKE '%" . $search . "%'")
            ->orWhere("last_name LIKE '%" . $search . "%'")
            ->orWhere("unique_identifier LIKE '%" . $search . "%'")
            ->orWhere("institute_name LIKE '%" . $search . "%'")
            ->orWhere("region LIKE '%" . $search . "%'");
        return $this->fetchAll($sql);
    }

    public function fetchMapActiveParticipantDetails($userSystemId)
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(array('p' => $this->_name))
            ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array('data_manager' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pmm.dm_id SEPARATOR ', ')")))
            ->where("pmm.dm_id = ?", $userSystemId)->group('p.participant_id'));
    }

    public function fetchFilterDetailsAPI($params)
    {
        /* Check the app versions & parameters */
        /* if (!isset($params['appVersion'])) {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        if (!isset($params['authToken'])) {
            return array('status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again');
        }

        /* Validate new auth token and app-version */
        $dmDb = new Application_Model_DbTable_DataManagers();
        $aResult = $dmDb->fetchAuthToken($params);
        /* if ($aResult == 'app-version-failed') {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        if (!$aResult) {
            return array('status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again', 'profileInfo' => $aResult['profileInfo']);
        }

        $result = $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(array('p' => $this->_name))
            ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array('data_manager' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pmm.dm_id SEPARATOR ', ')")))
            ->where("pmm.dm_id = ?", $aResult['dm_id'])
            //->where("p.status = 'active'")
            ->group('p.participant_id'));
        if (!empty($result)) {
            $response['status'] = 'success';
            foreach ($result as $row) {
                $response['data']['participants'][] = array(
                    'participant_id'    => $row['participant_id'],
                    'unique_identifier' => $row['unique_identifier'],
                    'first_name'        => $row['first_name'],
                    'last_name'         => $row['last_name'],
                    'mobile'            => $row['mobile'],
                    'email'             => $row['email'],
                    'status'            => $row['status'],
                    'individual'        => $row['individual'],
                    'lab_name'          => $row['lab_name'],
                    'institute_name'    => $row['institute_name'],
                    'department_name'   => $row['department_name'],
                    'region'            => $row['region']
                );
            }
            $schemeDb = new Application_Model_DbTable_SchemeList();
            $schemeList =  $schemeDb->getFullSchemeList();
            // Zend_Debug::dump($schemeList);die;
            if (count($schemeList) > 0) {
                foreach ($schemeList as $scheme) {
                    if ($scheme['status'] == 'active') {
                        $response['data']['shipments'][] = array(
                            'scheme_id'     => $scheme['scheme_id'],
                            'scheme_name'   => $scheme['scheme_name']
                        );
                    }
                }
            }
        } else {
            $response['status'] = 'fail';
            $response['message'] = 'No participant found.';
        }
        $response['profileInfo'] = $aResult['profileInfo'];
        return $response;
    }

    private function validateUploadedFile($fileName, $templateFilePath)
    {
        // Load the uploaded Excel file
        $uploadedSpreadsheet = IOFactory::load($fileName);

        // Load the template Excel file
        $templateSpreadsheet = IOFactory::load($templateFilePath);

        // Get the first sheet of the uploaded file
        $uploadedSheet = $uploadedSpreadsheet->getSheet(0);

        // Get the first sheet of the template file
        $templateSheet = $templateSpreadsheet->getSheet(0);

        // Extract headers from both sheets for comparison
        $uploadedHeaders = $uploadedSheet->rangeToArray('A1:Z1')[0];  // Adjust range as needed
        $templateHeaders = $templateSheet->rangeToArray('A1:Z1')[0];  // Adjust range as needed

        // Normalize headers for case-insensitive comparison and remove spaces/newlines
        $normalizedUploadedHeaders = array_map(function ($header) {
            return strtolower(preg_replace('/\s+/', '', $header));
        }, $uploadedHeaders);

        $normalizedTemplateHeaders = array_map(function ($header) {
            return strtolower(preg_replace('/\s+/', '', $header));
        }, $templateHeaders);

        // Compare the column headers
        if ($normalizedUploadedHeaders !== $normalizedTemplateHeaders) {
            // The column headers do not match the template
            return false;
        }

        // Compare additional formatting, data types, or any other specific requirements
        // ...

        // If all checks pass, return true
        return true;
    }


    public function processBulkImport($fileName, $allFakeEmail = false, $params = null)
    {

        $response = [];
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        $common = new Application_Service_Common();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Path to the template Excel file
        $templateFilePath = UPLOAD_PATH . '/../files/Participant-Bulk-Import-Excel-Format-v2.xlsx';

        if (!$this->validateUploadedFile($fileName, $templateFilePath)) {
            $alertMsg->message = 'The uploaded file does not match the expected format.';
            return $response;
        }

        $objPHPExcel = IOFactory::load($fileName);

        $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
        // Zend_Debug::dump($sheetData);
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $count = count($sheetData);

        for ($i = 2; $i <= $count; ++$i) {

            $lastInsertedId = 0;

            if (
                empty($sheetData[$i]['A']) &&
                empty($sheetData[$i]['C']) &&
                empty($sheetData[$i]['D'])
            ) {
                continue;
            }

            $sheetData[$i]['R'] = filter_var(trim($sheetData[$i]['R']), FILTER_SANITIZE_EMAIL);
            $sheetData[$i]['T'] = filter_var(trim($sheetData[$i]['T']), FILTER_SANITIZE_EMAIL);


            $sheetData[$i]['B'] = preg_replace("/[^a-zA-Z0-9-]/", "-", $sheetData[$i]['B']);

            // if the unique_identifier is blank, we generate a new one
            if (empty($sheetData[$i]['B'])) {
                $sheetData[$i]['B'] = "PT-" . strtoupper($common->generateRandomString(5));
            }


            $originalEmail = null;
            if (!empty($sheetData[$i]['R']) && filter_var($sheetData[$i]['R'], FILTER_VALIDATE_EMAIL)) {
                $originalEmail = $sheetData[$i]['R'];
            }

            // if the email is blank, we generate a new one
            if (empty($originalEmail) || $allFakeEmail) {
                $originalEmail = $sheetData[$i]['R'] = $common->generateFakeEmailId($sheetData[$i]['B'], $sheetData[$i]['D'] . " " . $sheetData[$i]['E']);
            }

            $originalEmail = $originalEmail ?? $sheetData[$i]['R'];


            // Duplications check
            $psql = $db->select()->from('participant')
                ->where("unique_identifier LIKE ?", $sheetData[$i]['B']);
            $participantRow = $db->fetchRow($psql);

            if (isset($params['bulkUploadDuplicateSkip']) && !empty($params['bulkUploadDuplicateSkip']) && $params['bulkUploadDuplicateSkip'] == 'skip-duplicates') {
                if (!empty($participantRow)) {
                    $dataForStatistics['error'] = "Unique ID {$sheetData[$i]['B']} already exists.";
                    continue;
                }
            }

            if (!empty($originalEmail)) {
                $dmsql = $db->select()->from('data_manager')
                    ->where("primary_email LIKE ?", $originalEmail);

                $dataManagerRow = $db->fetchRow($dmsql);
                if (isset($params['bulkUploadAllowEmailRepeat']) && !empty($params['bulkUploadAllowEmailRepeat']) && $params['bulkUploadAllowEmailRepeat'] == 'do-not-allow-existing-email') {
                    if (!empty($dataManagerRow)) {
                        $dataForStatistics['error'] = "Data Manager email $originalEmail already exists. Skipping for participant {$sheetData[$i]['B']}.";
                        continue;
                    }
                }
            } else {
                $dataForStatistics['error'] = "Email is empty for participant {$sheetData[$i]['B']}.";
                continue;
            }


            $dataForStatistics = [
                's_no'                  => $sheetData[$i]['A'],
                'participant_id'        => $sheetData[$i]['B'],
                'individual'            => $sheetData[$i]['C'],
                'participant_lab_name'  => $sheetData[$i]['D'],
                'participant_last_name' => $sheetData[$i]['E'],
                'institute_name'        => $sheetData[$i]['F'],
                'department'            => $sheetData[$i]['G'],
                'address'               => $sheetData[$i]['H'],
                'shipping_address'      => $sheetData[$i]['I'],
                'district'              => $sheetData[$i]['J'],
                'state'                 => $sheetData[$i]['K'],
                'region'                => $sheetData[$i]['L'],
                'country'               => $sheetData[$i]['M'],
                'zip'                   => $sheetData[$i]['N'],
                'longitude'             => $sheetData[$i]['O'],
                'latitude'              => $sheetData[$i]['P'],
                'mobile_number'         => $sheetData[$i]['Q'],
                'participant_email'     => $originalEmail,
                'participant_password'  => $sheetData[$i]['S'],
                'additional_email'      => $sheetData[$i]['T'],
                'filename'              => TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName,
                'updated_datetime'      => $common->getDateTime()
            ];

            $dmId = 0;
            $isIndividual = strtolower($sheetData[$i]['C']);
            if (!in_array($isIndividual, array('yes', 'no'))) {
                $isIndividual = 'yes'; // Default we treat testers as individuals
            }


            // COUNTRY ID
            $countryId = 236; // Default is USA

            if (!empty($sheetData[$i]['M'])) {
                $cmsql = $db->select()->from('countries')
                    ->where("iso_name LIKE ?", $sheetData[$i]['M'])
                    ->orWhere("iso2 LIKE  ?", $sheetData[$i]['M'])
                    ->orWhere("iso3 LIKE  ?", $sheetData[$i]['M']);

                //echo $cmsql;
                $cresult = $db->fetchRow($cmsql);
                if (!empty($cresult)) {
                    $countryId = $cresult['id'];
                }
            }

            $participantData = [
                'unique_identifier' => ($sheetData[$i]['B']),
                'individual'        => $isIndividual,
                'first_name'        => ($sheetData[$i]['D']),
                'last_name'         => ($sheetData[$i]['E']),
                'institute_name'    => ($sheetData[$i]['F']),
                'department_name'   => ($sheetData[$i]['G']),
                'address'           => ($sheetData[$i]['H']),
                'shipping_address'  => ($sheetData[$i]['I']),
                'district'          => $sheetData[$i]['J'],
                'state'             => ($sheetData[$i]['K']),
                'region'            => ($sheetData[$i]['L']),
                'country'           => $countryId,
                'zip'               => ($sheetData[$i]['N']),
                'long'              => ($sheetData[$i]['O']),
                'lat'               => ($sheetData[$i]['P']),
                'mobile'            => ($sheetData[$i]['Q']),
                'email'             => $originalEmail,
                'additional_email'  => ($sheetData[$i]['T']),
                'force_profile_updation' => 0,
                'created_by'        => $authNameSpace->admin_id,
                'created_on'        => new Zend_Db_Expr('now()'),
                'status'            => 'active'
            ];

            $dataManagerData = [
                'first_name'        => ($sheetData[$i]['D']),
                'last_name'         => ($sheetData[$i]['E']),
                'institute'         => ($sheetData[$i]['F']),
                'mobile'            => ($sheetData[$i]['O']),
                'secondary_email'   => ($sheetData[$i]['T']),
                'primary_email'     => $originalEmail,
                'password'          => (!isset($sheetData[$i]['S']) || empty($sheetData[$i]['S'])) ? 'ept1@)(*&^' : trim($sheetData[$i]['S']),
                'force_password_reset' => 1,
                'created_by'        => $authNameSpace->admin_id,
                'created_on'        => new Zend_Db_Expr('now()'),
                'status'            => 'active'
            ];

            /* To check the duplication in data manager table */
            $dmsql = $db->select()->from('data_manager')
                ->where("primary_email LIKE ?", $originalEmail);
            $dmresult = $db->fetchRow($dmsql);

            if (empty($dmresult) || $dmresult === false) {
                $db->insert('data_manager', $dataManagerData);
                $dmId = $db->lastInsertId();
            } else {
                $dmId = $dmresult['dm_id'];
            }
            $db->beginTransaction();
            if (empty($participantRow) || $participantRow === false) {
                try {
                    $lastInsertedId = $db->insert('participant', $participantData);

                    $lastInsertedId = $db->lastInsertId();

                    $db->commit();
                } catch (Exception $e) {
                    // If any of the queries failed and threw an exception,
                    // we want to roll back the whole transaction, reversing
                    // changes made in the transaction, even those that succeeded.
                    // Thus all changes are committed together, or none are.
                    $db->rollBack();
                    error_log($e->getMessage());
                    error_log($e->getTraceAsString());
                    continue;
                }
            } else {
                try {
                    $db->update('participant', $participantData, ' unique_identifier like "' . $participantRow['unique_identifier'] . '"');
                    $db->commit();
                    $lastInsertedId = $participantRow['participant_id'];
                } catch (Exception $e) {
                    // If any of the queries failed and threw an exception,
                    // we want to roll back the whole transaction, reversing
                    // changes made in the transaction, even those that succeeded.
                    // Thus all changes are committed together, or none are.
                    $db->rollBack();
                    error_log($e->getMessage());
                    error_log($e->getTraceAsString());
                    continue;
                }
            }

            if ($lastInsertedId > 0) {
                if ($dmId != null && $dmId > 0) {

                    $dmData = ['dm_id' => $dmId, 'participant_id' => $lastInsertedId];

                    $common = new Application_Service_Common();
                    $common->insertIgnore('participant_manager_map', $dmData);

                    $response['data'][] = $dataForStatistics;
                } else {
                    $dataForStatistics['error'] = 'Could not add Participant Login';
                    $db->insert('participants_not_uploaded', $dataForStatistics);
                    $response['error-data'][] = $dataForStatistics;
                    throw new Zend_Exception('Could not add Participant Login');
                }
            } else {
                $dataForStatistics['error'] = 'Could not add Participant';
                $db->insert('participants_not_uploaded', $dataForStatistics);
                $response['error-data'][] = $dataForStatistics;
                throw new Zend_Exception('Could not add Participant');
            }
        }


        $authNameSpace = new Zend_Session_Namespace('administrators');
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog("Bulk imported participants", "participants");

        $alertMsg->message = 'Your file was imported successfully';
        return $response;
    }

    public function deleteParticipantBId($participantId)
    {
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            if ($participantId > 0 && is_numeric($participantId)) {
                $sQuery = $this->getAdapter()->select()->from(array('p' => $this->_name), array('mapCount' => new Zend_Db_Expr("COUNT(pmm.dm_id)"), "pmm.dm_id"))
                    ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                    ->where("pmm.participant_id", $participantId)
                    ->group("pmm.participant_id");
                $pmmCheck = $this->getAdapter()->fetchRow($sQuery);
                // $id = $db->query("SET FOREIGN_KEY_CHECKS=0");
                if ($pmmCheck['mapCount'] <= 1) {
                    $id = $db->delete("shipment_participant_map", array("participant_id = " . $participantId));
                    $id = $db->delete("participant_manager_map", array("participant_id = " . $participantId));
                    if ($pmmCheck['mapCount'] == 1 && $pmmCheck['dm_id'] > 0) {
                        $id = $db->delete("data_manager", array("dm_id" => $pmmCheck['dm_id']));
                    }
                }
                $partcipant = $this->fetchRow(array("participant_id = " . $participantId));
                $id = $db->delete("enrollments", array("participant_id = " . $participantId));
                $id = $db->delete("participant", array("participant_id = " . $participantId));
                // $id = $db->query("SET FOREIGN_KEY_CHECKS=1");
                if ($participantId > 0) {
                    $auditDb = new Application_Model_DbTable_AuditLog();
                    $auditDb->addNewAuditLog("Deleted a participant - " . $partcipant['unique_identifier'], "participants");
                }

                return ($id);
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            echo ($e->getMessage()) . PHP_EOL;
            error_log($e->getTraceAsString());
        }
    }

    public function fetchShipmentResponseReport($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array(new Zend_Db_Expr("CONCAT(COALESCE(p.first_name,''),' ', COALESCE(p.last_name,''))"), 'institute_name', 'iso_name', 'state', 'district', 'shipment_code', 'response_status', 'shipment_test_report_date', 'final_result');

        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */
        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder[] = $aColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]);
                }
            }
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

        /*
         * SQL queries
         * Get data to display
         */

        $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS p.participant_id'), 'p.unique_identifier', 'p.institute_name', 'p.country', 'p.state', 'p.district', 'p.status', 'participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country')
            ->joinLeft(array('sp' => 'shipment_participant_map'), 'p.participant_id=sp.participant_id', array('shipment_test_report_date', 'final_result', "RESPONSE" => new Zend_Db_Expr("CASE WHEN (sp.is_excluded ='yes') THEN 'Excluded'  WHEN (sp.shipment_test_date not like '' AND sp.shipment_test_date!='0000-00-00' AND sp.shipment_test_date not like 'NULL') THEN 'Responded' ELSE 'Not Responded' END")))
            ->joinLeft(array('s' => 'shipment'), 's.shipment_id=sp.shipment_id', array('shipment_code', 'scheme_type', 'lastdate_response', 'status'))
            ->group("p.participant_id");

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type like ?", $parameters['scheme']);
        }
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id like ?", $parameters['shipmentId']);
        }

        if (isset($parameters['country']) && $parameters['country'] != "") {
            $sQuery = $sQuery->where("p.country = ?", $parameters['country']);
        }

        if (isset($parameters['region']) && $parameters['region'] != "") {
            $sQuery = $sQuery->where("p.region = ?", $parameters['region']);
        }

        if (isset($parameters['state']) && $parameters['state'] != "") {
            $sQuery = $sQuery->where("p.state = ?", $parameters['state']);
        }

        if (isset($parameters['district']) && $parameters['district'] != "") {
            $sQuery = $sQuery->where("p.district = ?", $parameters['district']);
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

        //die($sQuery);
        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $sQuerySession = new Zend_Session_Namespace('participantResponseReportQuerySession');
        $sQuerySession->participantResponseReportQuerySession = $sQuery;
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

        $finalResult = array(1 => 'Pass', 2 => 'Fail', 3 => 'Excluded');
        $general = new Pt_Commons_General();
        foreach ($rResult as $aRow) {
            $row = [];

            $row[] = ucwords($aRow['participantName']);
            $row[] = ucwords($aRow['institute_name']);
            $row[] = ucwords($aRow['iso_name']);
            $row[] = ucwords($aRow['state']);
            $row[] = ucwords($aRow['district']);
            $row[] = $aRow['shipment_code'];
            $row[] = ucwords($aRow['RESPONSE']);
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['shipment_test_report_date'] ?? '');
            $row[] = (isset($finalResult[$aRow['final_result']]) && !empty($finalResult[$aRow['final_result']])) ? ucwords($finalResult[$aRow['final_result']]) : null;
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function fetchParticipantsByLocations($locationValue, $locationField = 'country', $returnFields = array('participant_id'), $group = array('participant_id'))
    {
        return $this->getAdapter()->fetchAll($sQuery = $this->getAdapter()->select()
            ->from(array('p' => $this->_name), $returnFields)
            ->where($locationField . ' LIKE "' . $locationValue . '"')
            ->group($group));
    }

    public function exportParticipantMapDetails()
    {
        $headings = array('Participant ID', 'Lab Name/Participant Name', 'Cell/Mobile', 'Email', 'Country');
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
            $pQuery = $this->getAdapter()->select()
            ->from(array('p' => $this->_name), array('unique_identifier', 'labName' => new Zend_Db_Expr("CASE WHEN (lab_name IS NOT NULL AND lab_name NOT LIKE '') THEN lab_name ELSE first_name END") ,'pmobile' => 'mobile', 'email'))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country', array('c.iso_name'))
            ->where("participant_id NOT IN(SELECT DISTINCT participant_id FROM participant_manager_map WHERE dm_id in (SELECT dm_id FROM data_manager WHERE ptcc like 'no' or ptcc = '' or ptcc is null))")
            ->group(array('p.participant_id'));
            $totalResult = $db->fetchAll($pQuery);

            foreach ($headings as $field => $value) {
                $sheet->getCellByColumnAndRow($colNo + 1, 1)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                $sheet->getStyleByColumnAndRow($colNo + 1, 1, null, null)->getFont()->setBold(true);
                $colNo++;   
            }
            if (isset($totalResult) && !empty($totalResult)) {
                foreach ($totalResult as $aRow) {
                    $row = [];
                    $row[] = $aRow['unique_identifier'] ?? null;
                    $row[] = ucwords($aRow['labName']) ?? null;
                    $row[] = $aRow['pmobile'] ?? null;
                    $row[] = $aRow['email'] ?? null;
                    $row[] = ucwords($aRow['iso_name']) ?? null;
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
                    $sheet->getCellByColumnAndRow($colNo + 1, $rowNo + 2)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(100);
                        $sheet->getStyleByColumnAndRow($colNo + 1, $rowNo + 2, null, null)->getAlignment()->setWrapText(true);
                    }
                    $colNo++;
                }
            }
            foreach (range('A', 'Z') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }
            if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
                mkdir(TEMP_UPLOAD_PATH);
            }

            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'UNMAPPED-PARTICIPANT-LIST-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            error_log("UNMAPPED-PARTICIPANT-LIST--REPORT-EXCEL--" . $exc->getMessage());
            error_log($exc->getTraceAsString());

            return "";
        }
    }
}
