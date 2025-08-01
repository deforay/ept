<?php


use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Application_Service_Common as Common;
use Pt_Commons_MiscUtility as MiscUtility;

class Application_Model_DbTable_Participants extends Zend_Db_Table_Abstract
{

    protected $_name = 'participant';
    protected $_primary = 'participant_id';
    protected $_defaultPassword  = 'ept1@)(*&^';
    protected $_defaultPasswordHash = null;

    public function __construct()
    {
        parent::__construct();
        $this->_defaultPasswordHash = Common::passwordHash($this->_defaultPassword);
    }

    public function getParticipantsByUserSystemId($userSystemId)
    {
        $sql = $this->getAdapter()->select()->from(array('p' => $this->_name))
            ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', ['data_manager' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pmm.dm_id SEPARATOR ', ')")])
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
            ->from(['pmm' => 'participant_manager_map'])
            ->where("pmm.participant_id = ?", $participantId);

        if (isset($dmDb) && !empty($dmDb) && $dmDb != "") {
            $sql = $sql->where("pmm.dm_id = ?", $dmId);
        }
        $row = $this->getAdapter()->fetchRow($sql);
        return $row !== false;
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

        if (isset($parameters['pid']) && !empty($parameters['pid'])) {
            $pid = (is_array($parameters['pid'])) ? implode(",", $parameters['pid']) : $parameters['pid'];
            $sQuery = $sQuery->where("p.institute_name IN (?)", $pid);
        }
        if (isset($parameters['country']) && !empty($parameters['country'])) {
            $cid = (is_array($parameters['country'])) ? implode(",", $parameters['country']) : $parameters['country'];
            $sQuery = $sQuery->where('p.country IN(' . $cid . ')');
        }
        if (isset($parameters['pstatus']) && !empty($parameters['pstatus'])) {
            $sQuery = $sQuery->where('p.status LIKE"' . $parameters['pstatus'] . '"');
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

        $data = [
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
        ];
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
        /* get previous unique id for changes */
        $exist = $this->fetchRow($this->select()->where("participant_id = " . $params['participantId']));
        $noOfRows = $this->update($data, "participant_id = " . $params['participantId']);
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
        $dmDb = new Application_Model_DbTable_DataManagers();
        $configDb = new Application_Model_DbTable_GlobalConfig();
        // $directParticipantLogin = $configDb->getValue('direct_participant_login');
        // if (isset($directParticipantLogin) && $directParticipantLogin == 'yes') {
        if ((isset($params['dmPassword']) && !empty($params['dmPassword'])) && isset($params['pemail']) && !empty($params['pemail'])) {
            $globalDb = new Application_Model_DbTable_GlobalConfig();
            $prefix = $globalDb->getValue('participant_login_prefix');

            $dmData = [
                'data_manager_type' => 'participant',
                'primary_email' => $params['pemail'],
                'first_name' => $params['pfname'],
                'last_name' => $params['plname'],
                'institute' => $params['instituteName'],
                'phone' => $params['pphone2'],
                'country_id' => $params['country'],
                'mobile' => $params['pphone1'],
                'updated_on' => new Zend_Db_Expr('now()'),
                'updated_by' => $authNameSpace->admin_id
            ];
            if (isset($params['dmPassword']) && !empty($params['dmPassword'])) {
                $dmData['password'] = Common::passwordHash($params['dmPassword']);
            }
            $dmDb->update($dmData, 'participant_ulid = "' . $exist['ulid'] . '"');
        }
        if (isset($params['dataManager']) && $params['dataManager'] != "") {
            $params['participantsList'][] = $params['participantId'];
            $dmDb->dmParticipantMap($params, $params['dataManager'], false, true);
        }

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

        $globalDb = new Application_Model_DbTable_GlobalConfig();
        $prefix = $globalDb->getValue('participant_login_prefix');
        $ulid = Pt_Commons_General::generateULID();
        $firstName = isset($params['pfname']) && $params['pfname'] != '' ? $params['pfname'] :  null;
        $lastName =  isset($params['plname']) && $params['plname'] != '' ? $params['plname'] :  null;
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $data = [
            'unique_identifier' => $params['pid'],
            'ulid' => $ulid,
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
        ];
        if (isset($params['individualParticipant']) && $params['individualParticipant'] == 'on') {
            $data['individual'] = 'yes';
        } else {
            $data['individual'] = 'no';
        }

        $participantId = $this->insert($data);

        $dmDb = new Application_Model_DbTable_DataManagers();
        $db = Zend_Db_Table_Abstract::getAdapter();
        $configDb = new Application_Model_DbTable_GlobalConfig();
        // $directParticipantLogin = $configDb->getValue('direct_participant_login');
        if ((isset($params['dmPassword']) && !empty($params['dmPassword'])) && isset($params['pemail']) && !empty($params['pemail'])) {
            $newDmId =  $dmDb->insert([
                'primary_email' => $params['pemail'] ?? $prefix . $params['pid'],
                'participant_ulid' => $ulid,
                'data_manager_type' => 'participant',
                'password' => Common::passwordHash($params['dmPassword'] ?? 'ept1@)(*&^'),
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
        } else {
            if (isset($params['dataManager']) && $params['dataManager'] != "") {
                $params['participantsList'][] = $participantId;
                $dmDb->dmParticipantMap($params, $params['dataManager'], false, true);
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
        }

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
        $toMail = Common::getConfig('admin_email');
        //$fromName = Common::getConfig('admin-name');
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




        $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'), new Zend_Db_Expr('SQL_CALC_FOUND_ROWS p.*'))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array('sp.map_id', 'sp.created_on_user', 'sp.attributes', 'sp.final_result', 'sp.shipment_test_date', "RESPONSE" => new Zend_Db_Expr("CASE WHEN (sp.is_excluded ='yes') THEN 'Excluded'  WHEN (sp.shipment_test_date not like '' AND sp.shipment_test_date not like '0000-00-00' AND sp.shipment_test_date not like 'NULL') THEN 'Responded' ELSE 'Not Responded' END")))
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

            $row[] = '<a href="javascript:void(0);" onclick="removeParticipants(\'' . base64_encode($aRow['map_id']) . '\',\'' . base64_encode($aRow['shipment_id']) . '\')" class="btn btn-primary btn-xs"><i class="icon-remove"></i> Delete</a>';
            if (trim($aRow['created_on_user']) == "" && trim($aRow['final_result']) == "" && $aRow['shipmentStatus'] != 'finalized') {
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




        $subQuery = $this->getAdapter()->select()->from(array('p' => 'participant'), array(new Zend_Db_Expr('p.participant_id')))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array())
            ->join(array('s' => 'shipment'), 'sp.shipment_id=s.shipment_id', array())
            ->where("p.status='active'");

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $subQuery = $subQuery->where("s.shipment_id = ? ", $parameters['shipmentId']);
        }

        $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS p.*')))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country', array('c.iso_name'))->where("p.status='active'")->where("p.participant_id NOT IN ?", $subQuery);

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
                $allowedExtensions = ['xls', 'xlsx', 'csv'];
                $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['bulkMap']['name']);
                $fileName = str_replace(" ", "-", $fileName);
                $random = MiscUtility::generateRandomString(6);
                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $fileName = "$random-$fileName";
                if (in_array($extension, $allowedExtensions)) {
                    $tempDirectory = realpath(TEMP_UPLOAD_PATH);
                    if (!file_exists($tempDirectory . DIRECTORY_SEPARATOR . $fileName)) {
                        if (move_uploaded_file($_FILES['bulkMap']['tmp_name'], $tempDirectory . DIRECTORY_SEPARATOR . $fileName)) {
                            $objPHPExcel = IOFactory::load($tempDirectory . DIRECTORY_SEPARATOR . $fileName);

                            $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);

                            unset($sheetData[1]);

                            $authNameSpace = new Zend_Session_Namespace('administrators');

                            foreach ($sheetData as $row) {

                                $row['D'] = MiscUtility::sanitizeAndValidateEmail(trim($row['D']));
                                $row['E'] = MiscUtility::sanitizeAndValidateEmail(trim($row['E']));

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
                                        'first_name'        => MiscUtility::cleanString($row['C']),
                                        'primary_email'     => $row['D'],
                                        'secondary_email'   => $row['E'],
                                        'mobile'            => MiscUtility::cleanString($row['F']),
                                        'password'          => $this->_defaultPasswordHash,
                                        'force_password_reset' => 0,
                                        'force_profile_check' => 0,
                                        'data_manager_type' => 'manager',
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
                    $dm->dmParticipantMap($params, $params['datamanagerId'], false);

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


        $sQuery = $this->getAdapter()->select()->from(array('sp' => 'shipment_participant_map'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS sp.map_id'), 'sp.participant_id',  'sp.shipment_test_date', 'shipment_id', "RESPONSE" => new Zend_Db_Expr("CASE WHEN (sp.is_excluded ='yes') THEN 'Excluded'  WHEN (sp.shipment_test_date not like '' AND sp.shipment_test_date!='0000-00-00' AND sp.shipment_test_date not like 'NULL') THEN 'Responded' ELSE 'Not Responded' END")))
            ->joinLeft(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['p.participant_id', 'p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.city', 'p.state', 'p.district', 'p.country', 'p.mobile', 'p.state', 'p.phone', 'p.affiliation', 'p.email', 'p.phone', 'p.status', 'participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")])
            ->joinLeft(['c' => 'countries'], 'c.id=p.country')
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
        $output = [
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => []
        ];


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

    public function fetchFilterValues()
    {
        $query = $this->getAdapter()->select()
            ->from(
                ['p' => $this->_name],
                [
                    'country_details' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT CONCAT(con.id, ':', con.iso_name) SEPARATOR ',')"),
                    'districts'       => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.district SEPARATOR ',')"),
                    'regions'         => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.region SEPARATOR ',')"),
                    'states'          => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.state SEPARATOR ',')"),
                    'cities'          => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.city SEPARATOR ',')")
                ]
            )
            ->joinLeft(['con' => 'countries'], 'con.id = p.country', [])
            ->where("p.status = 'active'");

        $result = $this->getAdapter()->fetchRow($query);

        // Process country_details into an id => name array
        $countries = [];
        if (!empty($result['country_details'])) {
            $countryPairs = explode(',', $result['country_details']);
            foreach ($countryPairs as $pair) {
                list($id, $name) = explode(':', $pair);
                $countries[$id] = $name;
            }
        }

        return [
            'countries' => $countries,
            'districts' => $result['districts'] ? explode(',', $result['districts']) : [],
            'regions'   => $result['regions'] ? explode(',', $result['regions']) : [],
            'states'    => $result['states'] ? explode(',', $result['states']) : [],
            'cities'    => $result['cities'] ? explode(',', $result['cities']) : [],
        ];
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
        $sql =  $sql->where("first_name LIKE '%" . $search . "%'
                OR last_name LIKE '%" . $search . "%'
                OR unique_identifier LIKE '%" . $search . "%'
                OR institute_name LIKE '%" . $search . "%'
                OR region LIKE '%" . $search . "%'")
            ->where("status like 'active'");
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


    // public function processBulkImport($fileName, $allFakeEmail = false, $params = null)
    // {
    //     $response = [];
    //     $alertMsg = new Zend_Session_Namespace('alertSpace');
    //     $common = new Application_Service_Common();
    //     $db = Zend_Db_Table_Abstract::getDefaultAdapter();

    //     $templateFilePath = realpath(WEB_ROOT) . "/files/Participant-Bulk-Import-Excel-Format-v2.xlsx";

    //     if (!$this->validateUploadedFile($fileName, $templateFilePath)) {
    //         $alertMsg->message = 'The uploaded file does not match the expected format.';
    //         return $response;
    //     }

    //     $objPHPExcel = IOFactory::load($fileName);
    //     $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
    //     $authNameSpace = new Zend_Session_Namespace('administrators');
    //     $count = count($sheetData);

    //     $configDb = new Application_Model_DbTable_GlobalConfig();
    //     $directParticipantLogin = $configDb->getValue('direct_participant_login');
    //     $prefix = $configDb->getValue('participant_login_prefix');
    //     $tempUploadDirectory = realpath(TEMP_UPLOAD_PATH);

    //     // Pre-load cached data to reduce database queries
    //     $countryCache = $this->buildCountryCache();
    //     $duplicateChecks = $this->batchCheckDuplicates($sheetData);

    //     // Single transaction for entire operation
    //     $db->beginTransaction();

    //     try {
    //         for ($i = 2; $i <= $count; ++$i) {
    //             // Direct Participant Login ULID generation
    //             $ulid = null;
    //             if (isset($directParticipantLogin) && $directParticipantLogin == 'yes') {
    //                 $ulid = MiscUtility::generateULID();
    //             }
    //             $lastInsertedId = 0;

    //             if (empty($sheetData[$i]['A']) && empty($sheetData[$i]['C']) && empty($sheetData[$i]['D'])) {
    //                 continue;
    //             }

    //             $sheetData[$i]['R'] = MiscUtility::sanitizeAndValidateEmail($sheetData[$i]['R']);
    //             $sheetData[$i]['T'] = MiscUtility::sanitizeAndValidateEmail($sheetData[$i]['T']);
    //             $sheetData[$i]['B'] = MiscUtility::slugify($sheetData[$i]['B']);

    //             if (empty($sheetData[$i]['B'])) {
    //                 $sheetData[$i]['B'] = "PT-" . strtoupper(MiscUtility::generateRandomString(5));
    //             }

    //             $originalEmail = $sheetData[$i]['R'] ?? null;

    //             if (empty($originalEmail) || $allFakeEmail) {
    //                 $originalEmail = $sheetData[$i]['R'] = MiscUtility::generateFakeEmailId($sheetData[$i]['B'], $sheetData[$i]['D'] . " " . $sheetData[$i]['E']);
    //             }

    //             // Use cached duplicate check instead of individual query
    //             $participantRow = $duplicateChecks['participants'][$sheetData[$i]['B']] ?? null;

    //             if (isset($params['bulkUploadDuplicateSkip']) && !empty($params['bulkUploadDuplicateSkip']) && $params['bulkUploadDuplicateSkip'] == 'skip-duplicates') {
    //                 $params['resetPassword'] = 'yes';
    //                 if (!empty($participantRow)) {
    //                     $dataForStatistics['error'] = "Unique ID {$sheetData[$i]['B']} already exists.";
    //                     continue;
    //                 }
    //             }

    //             if (!empty($originalEmail)) {
    //                 // Use cached data manager check
    //                 $dataManagerRow = $duplicateChecks['dataManagers'][$originalEmail] ?? null;
    //                 if (isset($params['bulkUploadAllowEmailRepeat']) && !empty($params['bulkUploadAllowEmailRepeat']) && $params['bulkUploadAllowEmailRepeat'] == 'do-not-allow-existing-email' && !empty($dataManagerRow)) {
    //                     $dataForStatistics['error'] = "Data Manager email $originalEmail already exists. Skipping for participant {$sheetData[$i]['B']}.";
    //                     continue;
    //                 }
    //             } else {
    //                 $dataForStatistics['error'] = "Email is empty for participant {$sheetData[$i]['B']}.";
    //                 continue;
    //             }

    //             $dataForStatistics = [
    //                 's_no'                  => $sheetData[$i]['A'],
    //                 'participant_id'        => $sheetData[$i]['B'],
    //                 'individual'            => $sheetData[$i]['C'] ?? 'no',
    //                 'participant_lab_name'  => $sheetData[$i]['D'],
    //                 'participant_last_name' => $sheetData[$i]['E'],
    //                 'institute_name'        => $sheetData[$i]['F'] ?? null,
    //                 'department'            => $sheetData[$i]['G'] ?? null,
    //                 'address'               => $sheetData[$i]['H'] ?? null,
    //                 'district'              => $sheetData[$i]['J'] ?? null,
    //                 'country'               => $sheetData[$i]['M'],
    //                 'zip'                   => $sheetData[$i]['N'] ?? null,
    //                 'longitude'             => $sheetData[$i]['O'] ?? null,
    //                 'latitude'              => $sheetData[$i]['P'] ?? null,
    //                 'mobile_number'         => $sheetData[$i]['Q'] ?? null,
    //                 'participant_email'     => $originalEmail,
    //                 'participant_password'  => $sheetData[$i]['S'],
    //                 'additional_email'      => $sheetData[$i]['T'] ?? null,
    //                 'filename'              => $tempUploadDirectory . DIRECTORY_SEPARATOR . $fileName,
    //                 'updated_datetime'      => Common::getDateTime()
    //             ];

    //             $dmId = 0;
    //             $isIndividual = strtolower($sheetData[$i]['C']);
    //             if (!in_array($isIndividual, ['yes', 'no'])) {
    //                 $isIndividual = 'yes';
    //             }

    //             // Use cached country lookup
    //             $countryId = $this->getCountryIdFromCache($sheetData[$i]['M'], $countryCache);

    //             $participantData = [
    //                 'unique_identifier' => MiscUtility::cleanString($sheetData[$i]['B']),
    //                 'individual'        => $isIndividual,
    //                 'first_name'        => MiscUtility::cleanString($sheetData[$i]['D']),
    //                 'last_name'         => MiscUtility::cleanString($sheetData[$i]['E']) ?? null,
    //                 'institute_name'    => MiscUtility::cleanString($sheetData[$i]['F']) ?? null,
    //                 'department_name'   => MiscUtility::cleanString($sheetData[$i]['G']) ?? null,
    //                 'address'           => MiscUtility::cleanString($sheetData[$i]['H']) ?? null,
    //                 'shipping_address'  => MiscUtility::cleanString($sheetData[$i]['I']) ?? null,
    //                 'district'          => MiscUtility::cleanString($sheetData[$i]['J']) ?? null,
    //                 'state'             => MiscUtility::cleanString($sheetData[$i]['K']) ?? null,
    //                 'region'            => MiscUtility::cleanString($sheetData[$i]['L']) ?? null,
    //                 'country'           => $countryId,
    //                 'zip'               => MiscUtility::cleanString($sheetData[$i]['N']) ?? null,
    //                 'long'              => MiscUtility::cleanString($sheetData[$i]['O']) ?? null,
    //                 'lat'               => MiscUtility::cleanString($sheetData[$i]['P']) ?? null,
    //                 'mobile'            => MiscUtility::cleanString($sheetData[$i]['Q']) ?? null,
    //                 'email'             => $originalEmail,
    //                 'additional_email'  => MiscUtility::cleanString($sheetData[$i]['T']) ?? null,
    //                 'force_profile_updation' => 0,
    //                 'created_by'        => $authNameSpace->admin_id,
    //                 'created_on'        => new Zend_Db_Expr('now()'),
    //                 'status'            => 'active'
    //             ];

    //             $dataManagerData = [
    //                 'first_name'        => MiscUtility::cleanString($sheetData[$i]['D']),
    //                 'last_name'         => MiscUtility::cleanString($sheetData[$i]['E']),
    //                 'institute'         => MiscUtility::cleanString($sheetData[$i]['F']),
    //                 'mobile'            => MiscUtility::cleanString($sheetData[$i]['O']),
    //                 'secondary_email'   => MiscUtility::cleanString($sheetData[$i]['T']),
    //                 'primary_email'     => $originalEmail,
    //                 'force_password_reset' => 1,
    //                 'created_by'        => $authNameSpace->admin_id,
    //                 'created_on'        => new Zend_Db_Expr('now()'),
    //                 'status'            => 'active'
    //             ];

    //             if (isset($params['resetPassword']) && !empty($params['resetPassword']) && $params['resetPassword'] == 'yes') {
    //                 $password = (!isset($sheetData[$i]['S']) || empty($sheetData[$i]['S'])) ? $this->_defaultPassword : trim($sheetData[$i]['S']);
    //                 $dataManagerData['password'] = ($password == $this->_defaultPassword) ? $this->_defaultPasswordHash : Common::passwordHash($password);
    //             }

    //             // Use cached data manager check
    //             $dmresult = $duplicateChecks['dataManagers'][$originalEmail] ?? null;
    //             if (empty($dmresult)) {
    //                 $db->insert('data_manager', $dataManagerData);
    //                 $dmId = $db->lastInsertId();
    //             } else {
    //                 $dmId = $dmresult['dm_id'];
    //             }

    //             // Direct Participant Login logic
    //             $dmId2 = 0;
    //             if (isset($directParticipantLogin) && $directParticipantLogin == 'yes') {
    //                 $dataManagerData2 = $dataManagerData;
    //                 $dataManagerData2['data_manager_type'] = 'participant';
    //                 $dataManagerData2['primary_email'] = $prefix . $sheetData[$i]['B'];

    //                 $participantData['ulid'] = $ulid;
    //                 $dataManagerData2['participant_ulid'] = $ulid;

    //                 // Check for duplicate direct participant login
    //                 $dmresult2 = $duplicateChecks['dataManagers'][$dataManagerData2['primary_email']] ?? null;

    //                 if (isset($params['resetPassword']) && !empty($params['resetPassword']) && $params['resetPassword'] == 'yes') {
    //                     $password = (!isset($sheetData[$i]['S']) || empty($sheetData[$i]['S'])) ? $this->_defaultPassword : trim($sheetData[$i]['S']);
    //                     $dataManagerData2['password'] = ($password == $this->_defaultPassword) ? $this->_defaultPasswordHash : Common::passwordHash($password);
    //                 }

    //                 if (empty($dmresult2)) {
    //                     $db->insert('data_manager', $dataManagerData2);
    //                     $dmId2 = $db->lastInsertId();
    //                 }
    //             }

    //             // ORIGINAL UPDATE/INSERT LOGIC PRESERVED
    //             if (empty($participantRow)) {
    //                 try {
    //                     $lastInsertedId = $db->insert('participant', $participantData);
    //                     $lastInsertedId = $db->lastInsertId();
    //                 } catch (Exception $e) {
    //                     error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
    //                     error_log($e->getTraceAsString());
    //                     continue;
    //                 }
    //             } else {
    //                 // UPDATE existing participant - ORIGINAL LOGIC PRESERVED
    //                 try {
    //                     $db->update('participant', $participantData, ' unique_identifier like "' . $participantRow['unique_identifier'] . '"');
    //                     $lastInsertedId = $participantRow['participant_id'];
    //                 } catch (Exception $e) {
    //                     error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
    //                     error_log($e->getTraceAsString());
    //                     continue;
    //                 }
    //             }

    //             if ($lastInsertedId > 0) {
    //                 // Direct Participant Login mapping
    //                 if ($dmId2 > 0) {
    //                     $db->delete(
    //                         'participant_manager_map',
    //                         "participant_id = $lastInsertedId AND dm_id NOT IN ( SELECT dm_id FROM data_manager WHERE IFNULL(data_manager_type, 'manager') like 'ptcc')"
    //                     );
    //                     $db->insert('participant_manager_map', ['dm_id' => $dmId2, 'participant_id' => $lastInsertedId]);
    //                 }

    //                 if ($dmId != null && $dmId > 0) {
    //                     $dmData = ['dm_id' => $dmId, 'participant_id' => $lastInsertedId];
    //                     $common->insertIgnore('participant_manager_map', $dmData);
    //                     $response['data'][] = $dataForStatistics;
    //                 } else {
    //                     $dataForStatistics['error'] = 'Could not add Participant Login';
    //                     $db->insert('participants_not_uploaded', $dataForStatistics);
    //                     $response['error-data'][] = $dataForStatistics;
    //                     throw new Zend_Exception('Could not add Participant Login');
    //                 }
    //             } else {
    //                 $dataForStatistics['error'] = 'Could not add Participant';
    //                 $db->insert('participants_not_uploaded', $dataForStatistics);
    //                 $response['error-data'][] = $dataForStatistics;
    //                 throw new Zend_Exception('Could not add Participant');
    //             }
    //         }

    //         // Commit the entire transaction at once
    //         $db->commit();
    //     } catch (Exception $e) {
    //         $db->rollBack();
    //         error_log("BULK IMPORT ERROR: " . $e->getMessage());
    //         error_log($e->getTraceAsString());
    //         $alertMsg->message = 'File not uploaded. Something went wrong please try again later!';
    //         return false;
    //     }

    //     $authNameSpace = new Zend_Session_Namespace('administrators');
    //     $auditDb = new Application_Model_DbTable_AuditLog();
    //     $auditDb->addNewAuditLog("Bulk imported participants", "participants");

    //     $alertMsg->message = 'Your file was imported successfully';
    //     return $response;
    // }

    public function processBulkImport($fileName, $allFakeEmail = false, $params = null)
    {
        $response = ['data' => [], 'error-data' => []];
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        $common = new Application_Service_Common();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $authNameSpace = new Zend_Session_Namespace('administrators');

        // Validate file format
        $templateFilePath = realpath(WEB_ROOT) . "/files/Participant-Bulk-Import-Excel-Format-v2.xlsx";
        if (!$this->validateUploadedFile($fileName, $templateFilePath)) {
            $alertMsg->message = 'The uploaded file does not match the expected format.';
            return $response;
        }

        // Load Excel data
        $objPHPExcel = IOFactory::load($fileName);
        $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
        $count = count($sheetData);

        // Get configuration
        $configDb = new Application_Model_DbTable_GlobalConfig();
        $directParticipantLogin = $configDb->getValue('direct_participant_login');
        $prefix = $configDb->getValue('participant_login_prefix');
        $tempUploadDirectory = realpath(TEMP_UPLOAD_PATH);

        // Build cache for performance
        $countryCache = $this->buildCountryCache();
        $duplicateChecks = $this->batchCheckDuplicates($sheetData);

        // Process data in single transaction
        $db->beginTransaction();

        try {
            for ($i = 2; $i <= $count; ++$i) {
                $row = $sheetData[$i];

                // Skip empty rows
                if (empty($row['A']) && empty($row['C']) && empty($row['D'])) {
                    continue;
                }

                // Clean and prepare data
                $row['R'] = MiscUtility::sanitizeAndValidateEmail($row['R'] ?? '');
                $row['T'] = MiscUtility::sanitizeAndValidateEmail($row['T'] ?? '');
                $row['B'] = empty($row['B']) ? "PT-" . strtoupper(MiscUtility::generateRandomString(5)) : MiscUtility::slugify($row['B']);

                // Handle email
                $originalEmail = $row['R'] ?? null;
                if (empty($originalEmail) || $allFakeEmail) {
                    $originalEmail = MiscUtility::generateFakeEmailId($row['B'], trim(($row['D'] ?? '') . " " . ($row['E'] ?? '')));
                }

                // Validation checks
                if (empty($originalEmail)) {
                    $this->addError($response, $row, "Email is empty for participant {$row['B']}.");
                    continue;
                }

                // Check duplicates
                $participantExists = $duplicateChecks['participants'][$row['B']] ?? null;
                if (isset($params['bulkUploadDuplicateSkip']) && $params['bulkUploadDuplicateSkip'] == 'skip-duplicates' && $participantExists) {
                    $this->addError($response, $row, "Unique ID {$row['B']} already exists.");
                    continue;
                }

                // Check data manager email duplicates
                $dataManagerExists = $duplicateChecks['dataManagers'][$originalEmail] ?? null;
                if (isset($params['bulkUploadAllowEmailRepeat']) && $params['bulkUploadAllowEmailRepeat'] == 'do-not-allow-existing-email' && $dataManagerExists) {
                    $this->addError($response, $row, "Data Manager email $originalEmail already exists. Skipping for participant {$row['B']}.");
                    continue;
                }

                // Prepare participant data
                $isIndividual = strtolower($row['C'] ?? 'yes');
                if (!in_array($isIndividual, ['yes', 'no'])) {
                    $isIndividual = 'yes';
                }

                $countryId = $this->getCountryIdFromCache($row['M'] ?? '', $countryCache);
                $ulid = ($directParticipantLogin == 'yes') ? MiscUtility::generateULID() : null;

                $participantData = [
                    'unique_identifier' => MiscUtility::cleanString($row['B']),
                    'individual' => $isIndividual,
                    'first_name' => MiscUtility::cleanString($row['D'] ?? ''),
                    'last_name' => MiscUtility::cleanString($row['E'] ?? ''),
                    'institute_name' => MiscUtility::cleanString($row['F'] ?? ''),
                    'department_name' => MiscUtility::cleanString($row['G'] ?? ''),
                    'address' => MiscUtility::cleanString($row['H'] ?? ''),
                    'shipping_address' => MiscUtility::cleanString($row['I'] ?? ''),
                    'district' => MiscUtility::cleanString($row['J'] ?? ''),
                    'state' => MiscUtility::cleanString($row['K'] ?? ''),
                    'region' => MiscUtility::cleanString($row['L'] ?? ''),
                    'country' => $countryId,
                    'zip' => MiscUtility::cleanString($row['N'] ?? ''),
                    'long' => MiscUtility::cleanString($row['O'] ?? ''),
                    'lat' => MiscUtility::cleanString($row['P'] ?? ''),
                    'mobile' => MiscUtility::cleanString($row['Q'] ?? ''),
                    'email' => $originalEmail,
                    'additional_email' => MiscUtility::cleanString($row['T'] ?? ''),
                    'force_profile_updation' => 0,
                    'created_by' => $authNameSpace->admin_id,
                    'created_on' => new Zend_Db_Expr('now()'),
                    'status' => 'active'
                ];

                if ($ulid) {
                    $participantData['ulid'] = $ulid;
                }

                // Prepare data manager data
                $dataManagerData = [
                    'first_name' => MiscUtility::cleanString($row['D'] ?? ''),
                    'last_name' => MiscUtility::cleanString($row['E'] ?? ''),
                    'institute' => MiscUtility::cleanString($row['F'] ?? ''),
                    'mobile' => MiscUtility::cleanString($row['Q'] ?? ''),
                    'secondary_email' => MiscUtility::cleanString($row['T'] ?? ''),
                    'primary_email' => $originalEmail,
                    'force_password_reset' => 1,
                    'created_by' => $authNameSpace->admin_id,
                    'created_on' => new Zend_Db_Expr('now()'),
                    'status' => 'active'
                ];

                // Handle password
                if (isset($params['resetPassword']) && $params['resetPassword'] == 'yes') {
                    $password = empty($row['S']) ? $this->_defaultPassword : trim($row['S']);
                    $dataManagerData['password'] = ($password == $this->_defaultPassword) ? $this->_defaultPasswordHash : Common::passwordHash($password);
                }

                // Insert/update data manager
                $dmId = 0;
                if (empty($dataManagerExists)) {
                    $db->insert('data_manager', $dataManagerData);
                    $dmId = $db->lastInsertId();
                } else {
                    $dmId = $dataManagerExists['dm_id'];
                }

                // Handle direct participant login
                $dmId2 = 0;
                if ($directParticipantLogin == 'yes') {
                    $dataManagerData2 = $dataManagerData;
                    $dataManagerData2['data_manager_type'] = 'participant';
                    $dataManagerData2['primary_email'] = $prefix . $row['B'];
                    $dataManagerData2['participant_ulid'] = $ulid;

                    if (isset($params['resetPassword']) && $params['resetPassword'] == 'yes') {
                        $password = empty($row['S']) ? $this->_defaultPassword : trim($row['S']);
                        $dataManagerData2['password'] = ($password == $this->_defaultPassword) ? $this->_defaultPasswordHash : Common::passwordHash($password);
                    }

                    $dmExists2 = $duplicateChecks['dataManagers'][$dataManagerData2['primary_email']] ?? null;
                    if (empty($dmExists2)) {
                        $db->insert('data_manager', $dataManagerData2);
                        $dmId2 = $db->lastInsertId();
                    }
                }

                // Insert/update participant
                $lastInsertedId = 0;
                try {
                    if (empty($participantExists)) {
                        $db->insert('participant', $participantData);
                        $lastInsertedId = $db->lastInsertId();
                    } else {
                        $db->update('participant', $participantData, 'unique_identifier = "' . $participantExists['unique_identifier'] . '"');
                        $lastInsertedId = $participantExists['participant_id'];
                    }
                } catch (Exception $e) {
                    error_log("Participant save error: " . $e->getMessage());
                    continue;
                }

                // Handle mappings and finalize
                if ($lastInsertedId > 0) {
                    // Direct participant login mapping
                    if ($dmId2 > 0) {
                        $db->delete('participant_manager_map', "participant_id = $lastInsertedId AND dm_id NOT IN (SELECT dm_id FROM data_manager WHERE IFNULL(data_manager_type, 'manager') = 'ptcc')");
                        $db->insert('participant_manager_map', ['dm_id' => $dmId2, 'participant_id' => $lastInsertedId]);
                    }

                    // Regular data manager mapping
                    if ($dmId > 0) {
                        $common->insertIgnore('participant_manager_map', ['dm_id' => $dmId, 'participant_id' => $lastInsertedId]);

                        // Success - add to response
                        $response['data'][] = [
                            'participant_id'        => $sheetData[$i]['B'],
                            'individual'            => $sheetData[$i]['C'] ?? 'no',
                            'participant_lab_name'  => $sheetData[$i]['D'],
                            'participant_last_name' => $sheetData[$i]['E'],
                            'institute_name'        => $sheetData[$i]['F'] ?? null,
                            'department'            => $sheetData[$i]['G'] ?? null,
                            'address'               => $sheetData[$i]['H'] ?? null,
                            'district'              => $sheetData[$i]['J'] ?? null,
                            'country'               => $sheetData[$i]['M'],
                            'zip'                   => $sheetData[$i]['N'] ?? null,
                            'longitude'             => $sheetData[$i]['O'] ?? null,
                            'latitude'              => $sheetData[$i]['P'] ?? null,
                            'mobile_number'         => $sheetData[$i]['Q'] ?? null,
                            'participant_email'     => $originalEmail,
                            'participant_password'  => $sheetData[$i]['S'],
                            'additional_email'      => $sheetData[$i]['T'] ?? null,
                            'filename'              => $tempUploadDirectory . DIRECTORY_SEPARATOR . $fileName,
                            'updated_datetime'      => Common::getDateTime()
                        ];
                    } else {
                        $this->addError($response, $row, 'Could not add Participant Login');
                    }
                } else {
                    $this->addError($response, $row, 'Could not add Participant');
                }
            }

            $db->commit();

            // Log audit
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Bulk imported participants", "participants");

            $alertMsg->message = 'Your file was imported successfully';
        } catch (Exception $e) {
            $db->rollBack();
            error_log("BULK IMPORT ERROR: " . $e->getMessage());
            $alertMsg->message = 'File not uploaded. Something went wrong please try again later!';
            return false;
        }

        return $response;
    }

    private function addError(&$response, $row, $errorMessage)
    {
        $errorData = [
            'participant_id' => $row['B'] ?? 'Unknown',
            'error' => $errorMessage,
            'updated_datetime' => Common::getDateTime()
        ];

        $response['error-data'][] = $errorData;

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->insert('participants_not_uploaded', $errorData);
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

    private function batchCheckDuplicates($sheetData)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $uniqueIds = [];
        $emails = [];

        for ($i = 2; $i <= count($sheetData); $i++) {
            if (!empty($sheetData[$i]['B'])) {
                $cleanId = MiscUtility::slugify($sheetData[$i]['B']);
                if (empty($cleanId)) {
                    $cleanId = "PT-" . strtoupper(MiscUtility::generateRandomString(5));
                }
                $uniqueIds[] = $cleanId;
            }

            $email = MiscUtility::sanitizeAndValidateEmail($sheetData[$i]['R']);
            if ($email) {
                $emails[] = $email;
            }

            // Also check for direct participant login emails
            $configDb = new Application_Model_DbTable_GlobalConfig();
            $prefix = $configDb->getValue('participant_login_prefix');
            if ($prefix && !empty($sheetData[$i]['B'])) {
                $directEmail = $prefix . MiscUtility::slugify($sheetData[$i]['B']);
                if ($directEmail) {
                    $emails[] = $directEmail;
                }
            }
        }

        // Get existing participants with full row data for update logic
        $existingParticipants = [];
        if (!empty($uniqueIds)) {
            $sql = $db->select()
                ->from('participant', ['unique_identifier', 'participant_id'])
                ->where('unique_identifier IN (?)', $uniqueIds);
            $results = $db->fetchAll($sql);
            foreach ($results as $row) {
                $existingParticipants[$row['unique_identifier']] = $row;
            }
        }

        // Get existing data managers
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
            'participants' => $existingParticipants,
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

    public function deleteParticipantBId($participantId)
    {
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            if ($participantId > 0 && is_numeric($participantId)) {
                $sQuery = $this->getAdapter()->select()->from(array('p' => $this->_name), array('mapCount' => new Zend_Db_Expr("COUNT(pmm.dm_id)"), "pmm.dm_id"))
                    ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
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
                $partcipant = $this->fetchRow(["participant_id = $participantId"]);
                $id = $db->delete("enrollments", ["participant_id = $participantId"]);
                $id = $db->delete("participant", ["participant_id = $participantId"]);
                // $id = $db->query("SET FOREIGN_KEY_CHECKS=1");
                if ($participantId > 0) {
                    $auditDb = new Application_Model_DbTable_AuditLog();
                    $auditDb->addNewAuditLog("Deleted a participant - " . $partcipant['unique_identifier'], "participants");
                }

                return ($id);
            }
        } catch (Exception $e) {
            echo ($e->getMessage()) . PHP_EOL;
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
        }
    }

    public function fetchShipmentResponseReport($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = [new Zend_Db_Expr("CONCAT(COALESCE(p.first_name,''),' ', COALESCE(p.last_name,''))"), 'institute_name', 'iso_name', 'state', 'district', 'shipment_code', 'response_status', 'shipment_test_report_date', 'final_result'];

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
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", Common::isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", Common::isoDateFormat($parameters['endDate']));
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
                ->from(array('p' => $this->_name), array('unique_identifier', 'labName' => new Zend_Db_Expr("CASE WHEN (lab_name IS NOT NULL AND lab_name NOT LIKE '') THEN lab_name ELSE first_name END"), 'pmobile' => 'mobile', 'email'))
                ->joinLeft(array('c' => 'countries'), 'c.id=p.country', array('c.iso_name'))
                ->where("participant_id NOT IN(SELECT DISTINCT participant_id FROM participant_manager_map WHERE dm_id in (SELECT dm_id FROM data_manager WHERE data_manager_type like 'manager' or data_manager_type = '' or data_manager_type is null))")
                ->group(array('p.participant_id'));
            $totalResult = $db->fetchAll($pQuery);

            foreach ($headings as $field => $value) {
                $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNo + 1) . 1)
                    ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNo + 1) . 1, null, null)->getFont()->setBold(true);
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
            $tempUploadDirectory = realpath(TEMP_UPLOAD_PATH);
            if (!file_exists($tempUploadDirectory) && !is_dir($tempUploadDirectory)) {
                mkdir($tempUploadDirectory);
            }

            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'UNMAPPED-PARTICIPANT-LIST-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save($tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            error_log("UNMAPPED-PARTICIPANT-LIST--REPORT-EXCEL--" . $exc->getMessage());
            error_log($exc->getTraceAsString());

            return "";
        }
    }

    public function excludeUnrollParticipantById($params)
    {
        try {
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $pQuery = $this->getAdapter()->select()
                ->from('system_admin', array('primary_email'))
                ->where('primary_email = ?', $authNameSpace->primary_email)
                ->where('password = ?', $params['password'])->limit(1);
            $verify = $db->fetchRow($pQuery);
            if ($verify) {
                $db->query("SET FOREIGN_KEY_CHECKS = 0;"); // Disable foreign key checks
                $db->delete('response_result_' . $params['testType'], 'shipment_map_id = ' . $params['smid']);
                $db->delete('shipment_participant_map', 'map_id = ' . $params['smid']);
                $db->query("SET FOREIGN_KEY_CHECKS = 1;"); // Enable foreign key checks
                return true;
            }
            return false;
        } catch (Exception $exc) {
            error_log("EXCLUDED-PARTICIPANT-ERROR-" . $exc->getMessage());
            error_log($exc->getTraceAsString());
            return false;
        }
    }

    public function notRespondedParticipants($shipmentId)
    {
        $sQuery = $this->getAdapter()->select()->from(array('sp' => 'shipment_participant_map'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS sp.map_id'), 'sp.participant_id',  'sp.shipment_test_date', 'shipment_id', "RESPONSE" => new Zend_Db_Expr("CASE WHEN (sp.is_excluded ='yes') THEN 'Excluded'  WHEN (sp.shipment_test_date not like '' AND sp.shipment_test_date!='0000-00-00' AND sp.shipment_test_date not like 'NULL') THEN 'Responded' ELSE 'Not Responded' END")))
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.participant_id', 'p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.city', 'p.state', 'p.district', 'p.country', 'p.mobile', 'p.state', 'p.phone', 'p.affiliation', 'p.email', 'p.phone', 'p.status', 'participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country')
            ->where("(sp.shipment_test_report_date IS NULL OR DATE(sp.shipment_test_report_date) = '0000-00-00' OR response_status like 'noresponse')")
            ->where("sp.shipment_id = ?", $shipmentId)
            ->group("sp.participant_id");
        return $this->getAdapter()->fetchAll($sQuery);
    }

    public function fetchParticipantList($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('p' => 'participant'), array('participant_id', 'unique_identifier', 'first_name', 'last_name'))
            ->where("p.status= ?", 'active');
        if (isset($params['country']) && $params['country'] != "") {
            $country = (is_array($params['country'])) ? implode(",", $params['country']) : $params['country'];
            $sql = $sql->where("country IN (?)", $country);
        }
        if (isset($params['state']) && $params['state'] != "") {
            $state = (is_array($params['state'])) ? implode(",", $params['state']) : $params['state'];
            $sql = $sql->where("state IN (?)", $state);
        }
        if (isset($params['region']) && $params['region'] != "") {
            $region = (is_array($params['region'])) ? implode(",", $params['region']) : $params['region'];
            $sql = $sql->where("region IN (?)", $region);
        }
        if (isset($params['district']) && $params['district'] != "") {
            $district = (is_array($params['district'])) ? implode(",", $params['district']) : $params['district'];
            $sql = $sql->where("district IN (?)", $district);
        }
        if (isset($params['city']) && $params['city'] != "") {
            $city = (is_array($params['city'])) ? implode(",", $params['city']) : $params['city'];
            $sql = $sql->where("city IN (?)", $city);
        }
        if (isset($params['network']) && $params['network'] != "") {
            $network = (is_array($params['network'])) ? implode(",", $params['network']) : $params['network'];
            $sql = $sql->where("network_tier IN (?)", $network);
        }
        if (isset($params['affiliation']) && $params['affiliation'] != "") {
            $affiliation = (is_array($params['affiliation'])) ? implode(",", $params['affiliation']) : $params['affiliation'];
            $sql = $sql->where("affiliation IN (?)", $affiliation);
        }
        if (isset($params['institute']) && $params['institute'] != "") {
            $institute = (is_array($params['institute'])) ? implode(",", $params['institute']) : $params['institute'];
            $sql = $sql->where("institute_name IN (?)", $institute);
        }
        return $db->fetchAll($sql);
    }
}
