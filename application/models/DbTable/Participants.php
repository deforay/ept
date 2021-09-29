<?php

class Application_Model_DbTable_Participants extends Zend_Db_Table_Abstract
{

    protected $_name = 'participant';
    protected $_primary = 'participant_id';

    public function getParticipantsByUserSystemId($userSystemId)
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(array('p' => $this->_name))
            ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array('data_manager' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pmm.dm_id SEPARATOR ', ')")))
            ->where("pmm.dm_id = ?", $userSystemId)
            //->where("p.status = 'active'")
            ->group('p.participant_id'));
    }

    public function checkParticipantAccess($participantId, $dmId = '', $comingFrom = '')
    {
        if ($comingFrom != 'API') {
            $authNameSpace =  new Zend_Session_Namespace('datamanagers');
            $dmId = $authNameSpace->dm_id;
        }

        $row = $this->getAdapter()->fetchRow($this->getAdapter()->select()
            ->from(array('pmm' => 'participant_manager_map'))
            ->where("pmm.participant_id = ?", $participantId)
            ->where("pmm.dm_id = ?", $dmId));

        if ($row == false) {
            return false;
        } else {
            return true;
        }
    }

    public function getParticipant($partSysId)
    {
        return $this->getAdapter()->fetchRow($this->getAdapter()->select()->from(array('p' => $this->_name))
            ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array('data_manager' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pmm.dm_id SEPARATOR ', ')")))
            ->joinLeft(array('pe' => 'participant_enrolled_programs_map'), 'pe.participant_id=p.participant_id', array('enrolled_prog' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pe.ep_id SEPARATOR ', ')")))
            ->where("p.participant_id = ?", $partSysId)
            ->group('p.participant_id'));
    }

    public function getAllParticipants($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('unique_identifier', new Zend_Db_Expr("CONCAT(p.first_name,' ',p.last_name)"), 'iso_name', 'mobile', 'phone', 'affiliation', 'email', 'status');

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

        /*
         * SQL queries
         * Get data to display
         */

        $sQuery = $this->getAdapter()->select()->from(array('p' => $this->_name), array('p.participant_id', 'p.unique_identifier', 'p.institute_name', 'p.country', 'p.mobile', 'p.phone', 'p.affiliation', 'p.email', 'p.status', 'participantName' => new Zend_Db_Expr("CONCAT(p.first_name,' ',p.last_name)")))
            ->join(array('c' => 'countries'), 'c.id=p.country')
            ->group("p.participant_id");

        if (isset($parameters['withStatus']) && $parameters['withStatus'] != "") {
            $sQuery = $sQuery->where("p.status = ? ", $parameters['withStatus']);
        }
        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //echo($sQuery);die;
        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $sQuerySession = new Zend_Session_Namespace('respondedParticipantsExcel');
        $sQuerySession->shipmentRespondedParticipantQuery = $sQuery;
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from(array("p" => $this->_name), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));

        if (isset($parameters['withStatus']) && $parameters['withStatus'] != "") {
            $sQuery = $sQuery->where("p.status = ? ", $parameters['withStatus']);
        }
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


        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['participantName'];
            $row[] = $aRow['iso_name'];
            $row[] = $aRow['mobile'];
            $row[] = $aRow['phone'];
            $row[] = $aRow['affiliation'];
            $row[] = $aRow['email'];
            $row[] = ucwords($aRow['status']);
            $row[] = '<a href="/admin/participants/edit/id/' . $aRow['participant_id'] . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function updateParticipant($params)
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');

        $data = array(
            'unique_identifier' => $params['pid'],
            'institute_name' => $params['instituteName'],
            'department_name' => $params['departmentName'],
            'address' => $params['address'],
            'city' => $params['city'],
            'state' => $params['state'],
            'country' => $params['country'],
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
            'region' => $params['region'],
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
            $db->delete('participant_manager_map', "participant_id = " . $params['participantId']);
            foreach ($params['dataManager'] as $dataManager) {
                $db->insert('participant_manager_map', array('dm_id' => $dataManager, 'participant_id' => $params['participantId']));
            }
        }

        if (isset($params['scheme']) && $params['scheme'] != "") {
            $enrollDb = new Application_Model_DbTable_Enrollments();
            $enrollDb->enrollParticipantToSchemes($params['participantId'], $params['scheme']);
        }

        return $noOfRows;
    }

    public function addParticipant($params)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $data = array(
            'unique_identifier' => $params['pid'],
            'institute_name' => $params['instituteName'],
            'department_name' => $params['departmentName'],
            'address' => $params['address'],
            'city' => $params['city'],
            'state' => $params['state'],
            'country' => $params['country'],
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
            'region' => $params['region'],
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
            $db->delete('participant_manager_map', "participant_id = " . $participantId);
            foreach ($params['dataManager'] as $dataManager) {
                $db->insert('participant_manager_map', array('dm_id' => $dataManager, 'participant_id' => $participantId));
            }
        }
        if (isset($params['enrolledProgram']) && $params['enrolledProgram'] != "") {
            foreach ($params['enrolledProgram'] as $epId) {
                $db->insert('participant_enrolled_programs_map', array('ep_id' => $epId, 'participant_id' => $participantId));
            }
        }

        return $participantId;
    }

    public function saveRequestParticipant($params)
    {
        $common = new Application_Service_Common();
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $db = Zend_Db_Table_Abstract::getAdapter();


        $data = array(
            'unique_identifier' => $common->getRandomString(4),
            'institute_name' => $params['instituteName'],
            'department_name' => $params['departmentName'],
            'address' => $params['address'],
            'city' => $params['city'],
            'state' => $params['state'],
            'country' => $params['country'],
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
            'region' => $params['region'],
            'created_on' => new Zend_Db_Expr('now()'),
            'created_by' => $authNameSpace->primary_email,
            'status' => 'pending'
        );
        if (isset($params['individualParticipant']) && $params['individualParticipant'] == 'on') {
            $data['individual'] = 'yes';
        } else {
            $data['individual'] = 'no';
        }
        $participantId = $this->insert($data);


        if (isset($params['enrolledProgram']) && $params['enrolledProgram'] != "") {
            foreach ($params['enrolledProgram'] as $epId) {
                $db->insert('participant_enrolled_programs_map', array('ep_id' => $epId, 'participant_id' => $participantId));
            }
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
        $common->sendMail($toMail, null, null, "New Participant Registered  ($participantName)", $message, $fromMail, "ePT Admin");

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

        /*
         * SQL queries
         * Get data to display
         */


        $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'))
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

        $sQuery = $this->getAdapter()->select()->from(array("p" => $this->_name), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array('sp.shipment_test_date'))
            ->join(array('s' => 'shipment'), 'sp.shipment_id=s.shipment_id', array())
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country', array('c.iso_name'))
            ->where("p.status='active'");

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ? ", $parameters['shipmentId']);
        }

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


        foreach ($rResult as $aRow) {
            $row = array();
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

        /*
         * SQL queries
         * Get data to display
         */


        $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'), array('p.participant_id'))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array())
            ->join(array('s' => 'shipment'), 'sp.shipment_id=s.shipment_id', array())
            ->where("p.status='active'");

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ? ", $parameters['shipmentId']);
        }

        $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'))->joinLeft(array('c' => 'countries'), 'c.id=p.country', array('c.iso_name'))->where("p.status='active'")->where("p.participant_id NOT IN ?", $sQuery);

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */


        $sQuery = $this->getAdapter()->select()->from(array("p" => $this->_name), array('p.participant_id'))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array())
            ->join(array('s' => 'shipment'), 'sp.shipment_id=s.shipment_id', array())
            ->where("p.status='active'");

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ? ", $parameters['shipmentId']);
        }
        $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"))->where("p.status='active'")->where("p.participant_id NOT IN ?", $sQuery);

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


        foreach ($rResult as $aRow) {
            $row = array();
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
    public function addParticipantManager($params)
    {
        $db = Zend_Db_Table_Abstract::getAdapter();
        if (isset($params['datamanagerId']) && $params['datamanagerId'] != "") {
            $db->delete('participant_manager_map', "dm_id = " . $params['datamanagerId']);

            foreach ($params['participants'] as $participants) {
                $db->insert('participant_manager_map', array('participant_id' => $participants, 'dm_id' => $params['datamanagerId']));
            }
        } else if (isset($params['participantId']) && $params['participantId'] != "") {
            $db->delete('participant_manager_map', "participant_id = " . $params['participantId']);

            foreach ($params['datamangers'] as $datamangers) {
                $db->insert('participant_manager_map', array('dm_id' => $datamangers, 'participant_id' => $params['participantId']));
            }
        }
    }

    public function getShipmentRespondedParticipants($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('unique_identifier', new Zend_Db_Expr("CONCAT(p.first_name,' ',p.last_name)"), 'institute_name', 'iso_name', 'mobile', 'phone', 'affiliation', 'email', 'status');

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

        /*
         * SQL queries
         * Get data to display
         */

        $sQuery = $this->getAdapter()->select()->from(array('sp' => 'shipment_participant_map'), array('sp.shipment_id', 'sp.map_id', 'sp.participant_id', 'sp.shipment_test_date', "RESPONSE" => new Zend_Db_Expr("CASE WHEN (sp.is_excluded ='yes') THEN 'Excluded'  WHEN (sp.shipment_test_date not like '' AND sp.shipment_test_date!='0000-00-00' AND sp.shipment_test_date not like 'NULL') THEN 'Responded' ELSE 'Not Responded' END")))
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.participant_id', 'p.unique_identifier', 'p.institute_name', 'p.country', 'p.mobile', 'p.phone', 'p.affiliation', 'p.email', 'p.status', 'participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
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

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        // error_log($sQuery);
        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);

        $sQuerySession = new Zend_Session_Namespace('respondedParticipantsExcel');
        $sQuerySession->shipmentRespondedParticipantQuery = $sQuery;
        //error_log($sQuery);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from(array('sp' => 'shipment_participant_map'), array())
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array())
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country')
            ->where("sp.shipment_test_date <>'0000-00-00'")
            ->where("sp.shipment_test_date IS NOT NULL ")
            ->where("sp.shipment_id = ?", $parameters['shipmentId'])
            ->group("sp.participant_id");

        if (isset($parameters['withStatus']) && $parameters['withStatus'] != "") {
            $sQuery = $sQuery->where("p.status = ? ", $parameters['withStatus']);
        }
        $aResultTotal = $this->getAdapter()->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

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
            $row = array();
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['participantName'];
            $row[] = $aRow['institute_name'];
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

        $aColumns = array('unique_identifier', new Zend_Db_Expr("CONCAT(p.first_name,' ',p.last_name)"), 'p.institute_name', 'iso_name', 'mobile', 'phone', 'affiliation', 'email', 'status');

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

        /*
         * SQL queries
         * Get data to display
         */
        $sQuery = $this->getAdapter()->select()->from(array('sp' => 'shipment_participant_map'), array('sp.participant_id', 'sp.map_id', 'sp.shipment_test_date', 'shipment_id', "RESPONSE" => new Zend_Db_Expr("CASE WHEN (sp.is_excluded ='yes') THEN 'Excluded'  WHEN (sp.shipment_test_date not like '' AND sp.shipment_test_date!='0000-00-00' AND sp.shipment_test_date not like 'NULL') THEN 'Responded' ELSE 'Not Responded' END")))
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.participant_id', 'p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.city', 'p.country', 'p.mobile', 'p.state', 'p.phone', 'p.affiliation', 'p.email', 'p.phone', 'p.status', 'participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country')
            ->where("(sp.shipment_test_date = '0000-00-00' OR sp.shipment_test_date IS NULL)")
            ->where("sp.shipment_id = ?", $parameters['shipmentId'])
            ->group("sp.participant_id");

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
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

        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from(array('sp' => 'shipment_participant_map'), array())
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array())
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country')
            ->where("(sp.shipment_test_date = '0000-00-00' OR sp.shipment_test_date IS NULL)")
            ->where("sp.shipment_id = ?", $parameters['shipmentId'])
            ->group("sp.participant_id");

        $aResultTotal = $this->getAdapter()->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

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
            $row = array();
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['participantName'];
            $row[] = $aRow['institute_name'];
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

        $aColumns = array('first_name', 'unique_identifier', new Zend_Db_Expr("CONCAT(p.first_name,' ',p.last_name)"), 'institute_name', 'iso_name', 'mobile', 'phone', 'affiliation', 'email', 'p.status');

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

        /*
         * SQL queries
         * Get data to display
         */
        $subSql = $this->getAdapter()->select()->from(array('sp' => 'shipment_participant_map'), array('sp.participant_id'))
            ->where("sp.shipment_id = ?", $parameters['shipmentId'])
            ->group("sp.participant_id");

        $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'), array('p.participant_id', 'p.unique_identifier', 'p.institute_name', 'p.country', 'p.mobile', 'p.phone', 'p.affiliation', 'p.email', 'p.status', 'participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country')
            ->where("p.participant_id NOT IN ?", $subSql)
            ->where("p.status='active'")
            ->order('first_name')
            ->group("p.participant_id");

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }


        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from(array('e' => 'enrollments'), array('e.participant_id'))
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=e.participant_id', array('p.unique_identifier', 'p.country', 'p.mobile', 'p.phone', 'p.affiliation', 'p.email', 'p.status', 'participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country')
            ->where("e.participant_id NOT IN ?", $subSql)->where("p.status='active'")->order('first_name')
            ->group("e.participant_id");


        $aResultTotal = $this->getAdapter()->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

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
            $row = array();

            $row[] = '<input type="checkbox" class="checkParticipants" id="chk' . base64_encode($aRow['participant_id']) . '"  value="' . base64_encode($aRow['participant_id']) . '" onclick="toggleSelect(this);"  />';
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['participantName'];
            $row[] = $aRow['institute_name'];
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
        if (count($result) > 0) {
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
                    'region'            => $row['region'],
                    'department_name'   => $row['department_name'],
                    'department_name'   => $row['department_name']
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

    public function processBulkImport($fileName, $allFakeEmail = false)
    {

        $response = array();
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        $common = new Application_Service_Common();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $objPHPExcel = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileName);

        $sheetData = $objPHPExcel->getActiveSheet()->toArray(NULL, true, true, true);

        $authNameSpace = new Zend_Session_Namespace('administrators');
        $count = count($sheetData);

        for ($i = 2; $i <= $count; ++$i) {

            $lastInsertedId = 0;

            if (empty($sheetData[$i]['A']) && empty($sheetData[$i]['C']) && empty($sheetData[$i]['D'])) {
                continue;
            }


            $sheetData[$i]['A'] = filter_var(trim($sheetData[$i]['A']), FILTER_SANITIZE_STRING);
            $sheetData[$i]['B'] = filter_var(trim($sheetData[$i]['B']), FILTER_SANITIZE_STRING);
            $sheetData[$i]['C'] = filter_var(trim($sheetData[$i]['C']), FILTER_SANITIZE_STRING);
            $sheetData[$i]['D'] = filter_var(trim($sheetData[$i]['D']), FILTER_SANITIZE_STRING);
            $sheetData[$i]['E'] = filter_var(trim($sheetData[$i]['E']), FILTER_SANITIZE_STRING);
            $sheetData[$i]['F'] = filter_var(trim($sheetData[$i]['F']), FILTER_SANITIZE_STRING);
            $sheetData[$i]['G'] = filter_var(trim($sheetData[$i]['G']), FILTER_SANITIZE_STRING);
            $sheetData[$i]['H'] = filter_var(trim($sheetData[$i]['H']), FILTER_SANITIZE_STRING);
            $sheetData[$i]['I'] = filter_var(trim($sheetData[$i]['I']), FILTER_SANITIZE_STRING);
            $sheetData[$i]['J'] = filter_var(trim($sheetData[$i]['J']), FILTER_SANITIZE_STRING);
            $sheetData[$i]['K'] = filter_var(trim($sheetData[$i]['K']), FILTER_SANITIZE_STRING);
            $sheetData[$i]['L'] = filter_var(trim($sheetData[$i]['L']), FILTER_SANITIZE_STRING);
            $sheetData[$i]['M'] = filter_var(trim($sheetData[$i]['M']), FILTER_SANITIZE_STRING);
            $sheetData[$i]['N'] = filter_var(trim($sheetData[$i]['N']), FILTER_SANITIZE_STRING);
            $sheetData[$i]['O'] = filter_var(trim($sheetData[$i]['O']), FILTER_SANITIZE_STRING);
            $sheetData[$i]['Q'] = filter_var(trim($sheetData[$i]['Q']), FILTER_SANITIZE_STRING);


            $sheetData[$i]['P'] = filter_var(trim($sheetData[$i]['P']), FILTER_SANITIZE_EMAIL);
            $sheetData[$i]['R'] = filter_var(trim($sheetData[$i]['R']), FILTER_SANITIZE_EMAIL);

            // if the unique_identifier is blank, we generate a new one
            $useUniqueIDForDuplicateCheck = true;
            $sheetData[$i]['B'] = str_replace("-", "", $sheetData[$i]['B']);
            $sheetData[$i]['B'] = str_replace(".", "", $sheetData[$i]['B']);
            if (empty($sheetData[$i]['B'])) {
                $useUniqueIDForDuplicateCheck = false;
                $sheetData[$i]['B'] = "PT-" . strtoupper($common->generateRandomString(5));
            }

            // if the email is blank, we generate a new one
            $useEmailForDuplicateCheck = true;
            if (empty($sheetData[$i]['P']) || $allFakeEmail) {
                $useEmailForDuplicateCheck = false;
                $sheetData[$i]['P'] = $common->generateFakeEmailId($sheetData[$i]['B'], $sheetData[$i]['D'] . " " . $sheetData[$i]['E']);
            }

            $dataForStatistics = array(
                's_no'     => $sheetData[$i]['A'],
                'participant_id' => $sheetData[$i]['B'],
                'individual' => $sheetData[$i]['C'],
                'participant_lab_name' => $sheetData[$i]['D'],
                'participant_last_name' => $sheetData[$i]['E'],
                'institute_name' => $sheetData[$i]['F'],
                'department' => $sheetData[$i]['G'],
                'address' => $sheetData[$i]['H'],
                'district' => $sheetData[$i]['I'],
                'province' => $sheetData[$i]['J'],
                'country' => $sheetData[$i]['K'],
                'zip' => $sheetData[$i]['L'],
                'longitude' => $sheetData[$i]['M'],
                'latitude' => $sheetData[$i]['N'],
                'mobile_number' => $sheetData[$i]['O'],
                'participant_email'     => $sheetData[$i]['P'],
                'participant_password'     => $sheetData[$i]['Q'],
                'additional_email'     => $sheetData[$i]['R'],
                'filename'     => TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName,
                'updated_datetime'     => $common->getDateTime()
            );

            if (!empty($sheetData[$i]['P']) && $sheetData[$i]['P'] != false) {

                $dmId = 0;
                $isIndividual = strtolower($sheetData[$i]['C']);
                if (!in_array($isIndividual, array('yes', 'no'))) {
                    $isIndividual = 'yes'; // Default we treat testers as individuals
                }

                $presult = $dmresult = false;
                if ($useUniqueIDForDuplicateCheck && $useEmailForDuplicateCheck) {
                    /* To check the duplication in participant table */
                    $psql = $db->select()->from('participant')
                        ->where("unique_identifier LIKE ?", $sheetData[$i]['B'])
                        ->orWhere("email LIKE ?", $sheetData[$i]['P']);
                    $presult = $db->fetchRow($psql);
                } else if ($useUniqueIDForDuplicateCheck) {
                    /* To check the duplication in participant table */
                    $psql = $db->select()->from('participant')
                        ->where("unique_identifier LIKE ?", $sheetData[$i]['B']);
                    $presult = $db->fetchRow($psql);
                } else if ($useEmailForDuplicateCheck) {
                    /* To check the duplication in participant table */
                    $psql = $db->select()->from('participant')
                        ->where("email LIKE ?", $sheetData[$i]['P']);
                    $presult = $db->fetchRow($psql);
                } else {
                    $psql = $db->select()->from('participant')
                        ->where("first_name LIKE ?", $sheetData[$i]['D'])
                        ->where("last_name LIKE ?", $sheetData[$i]['E'])
                        ->where("mobile LIKE ?", $sheetData[$i]['O'])
                        ->where("city LIKE ?", $sheetData[$i]['I']);
                    $presult = $db->fetchRow($psql);
                }

                /* To check the duplication in data manager table */
                $dmsql = $db->select()->from('data_manager')
                    ->where("primary_email LIKE ?", $sheetData[$i]['P']);
                $dmresult = $db->fetchRow($dmsql);


                // if($dmresult !== false){
                // 	$sheetData[$i]['P'] = $common->generateFakeEmailId($sheetData[$i]['B'], $sheetData[$i]['D'] . " " . $sheetData[$i]['E']);
                // 	$dmresult = false;
                // }

                /* To find the country id */
                $cmsql = $db->select()->from('countries')
                    ->where("iso_name LIKE ?", $sheetData[$i]['K'])
                    ->orWhere("iso2 LIKE  ?", $sheetData[$i]['K'])
                    ->orWhere("iso3 LIKE  ?", $sheetData[$i]['K']);

                //echo $cmsql;	
                $cresult = $db->fetchRow($cmsql);
                $countryId = 236; // Default is USA
                if (!$cresult) {
                    // $dataForStatistics['error'] = 'Invalid Country ' . $sheetData[$i]['K'];
                    // $response['error-data'][] = $dataForStatistics;
                    // continue;
                    $countryId = 236; // Default is USA
                } else {
                    $countryId = $cresult['id'];
                }

                if (!$presult && !$dmresult) {

                    $db->beginTransaction();
                    try {
                        $lastInsertedId = $db->insert('participant', array(
                            'unique_identifier' => ($sheetData[$i]['B']),
                            'individual'         => $isIndividual,
                            'first_name'         => ($sheetData[$i]['D']),
                            'last_name'         => ($sheetData[$i]['E']),
                            'institute_name'     => ($sheetData[$i]['F']),
                            'department_name'     => ($sheetData[$i]['G']),
                            'address'             => ($sheetData[$i]['H']),
                            'city'                 => ($sheetData[$i]['I']),
                            'state'             => ($sheetData[$i]['J']),
                            'country'             => $countryId,
                            'zip'                 => ($sheetData[$i]['L']),
                            'long'                 => ($sheetData[$i]['M']),
                            'lat'                 => ($sheetData[$i]['N']),
                            'mobile'             => ($sheetData[$i]['O']),
                            'email'             => ($sheetData[$i]['P']),
                            'additional_email'     => ($sheetData[$i]['R']),
                            'created_by'         => $authNameSpace->admin_id,
                            'created_on'         => new Zend_Db_Expr('now()'),
                            'status'            => 'active'
                        ));

                        $pasql = $db->select()->from('participant')
                            ->where("email LIKE ?", trim($sheetData[$i]['P']))
                            ->orWhere("unique_identifier LIKE ?", trim($sheetData[$i]['B']));
                        $paresult = $db->fetchRow($pasql);
                        $lastInsertedId = $paresult['participant_id'];
                        if ($lastInsertedId > 0) {
                            $db->insert('data_manager', array(
                                'first_name'         => ($sheetData[$i]['D']),
                                'last_name'         => ($sheetData[$i]['E']),
                                'institute'         => ($sheetData[$i]['F']),
                                'mobile'             => ($sheetData[$i]['O']),
                                'secondary_email'     => ($sheetData[$i]['R']),
                                'primary_email'     => ($sheetData[$i]['P']),
                                'password'             => (!isset($sheetData[$i]['Q']) || empty($sheetData[$i]['Q'])) ? 'ept1@)(*&^' : trim($sheetData[$i]['Q']),
                                'created_by'         => $authNameSpace->admin_id,
                                'created_on'         => new Zend_Db_Expr('now()'),
                                'status'            => 'active'
                            ));

                            $dmId = $db->lastInsertId();

                            if ($dmId != null && $dmId > 0) {
                                $db->insert('participant_manager_map', array('dm_id' => $dmId, 'participant_id' => $lastInsertedId));
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
                    if ($useUniqueIDForDuplicateCheck || $useEmailForDuplicateCheck) {
                        $dataForStatistics['error'] = 'Possible duplicate of Participant Email or Unique ID.';
                    } else {
                        $dataForStatistics['error'] = 'Possible duplicate of Name, Location, Mobile combination';
                    }

                    $db->insert('participants_not_uploaded', $dataForStatistics);
                    $response['error-data'][] = $dataForStatistics;
                }
                // if ($lastInsertedId > 0 || $dmId > 0) {
                // 	if (file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName)) {
                // 		unlink(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName);
                // 	}
                // 	$response['message'] = "File has expired please re-import again!";
                // }
            } else {
                $dataForStatistics['error'] = 'Primary Email Missing';
                $db->insert('participants_not_uploaded', $dataForStatistics);
                $response['error-data'][] = $dataForStatistics;
            }
        }

        $alertMsg->message = 'Your file was imported successfully';
        return $response;
    }
}
