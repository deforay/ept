<?php

class Application_Model_DbTable_Shipments extends Zend_Db_Table_Abstract {

    protected $_name = 'shipment';
    protected $_primary = 'shipment_id';
    protected $_session = null;

    public function __construct() {
        parent::__construct();
        $this->_session = new Zend_Session_Namespace('datamanagers');
    }

    public function getShipmentData($sId, $pId) {

        return $this->getAdapter()->fetchRow($this->getAdapter()->select()->from(array('s' => $this->_name))
                                ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
                                ->where("s.shipment_id = ?", $sId)
                                ->where("sp.participant_id = ?", $pId));
    }

    public function updateShipmentStatus($shipmentId, $status) {
        if (isset($status) && $status != null && $status != "") {
            return $this->update(array('status' => $status), "shipment_id = $shipmentId");
        } else {
            return 0;
        }
    }

    public function updateShipmentStatusByDistribution($distributionId, $status) {
        if (isset($status) && $status != null && $status != "") {
            return $this->update(array('status' => $status), "distribution_id = $distributionId");
        } else {
            return 0;
        }
    }

    public function getPendingShipmentsByDistribution($distributionId) {
        return $this->fetchAll("status ='pending' AND distribution_id = $distributionId");
    }

    public function getShipmentOverviewDetails($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('year(shipment_date)', 'scheme_type');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;

        $sTable = $this->_name;
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
                    if ($parameters['iSortCol_' . $i] == 0) {
                        $sOrder .= "shipment_date " . ( $parameters['sSortDir_' . $i] ) . ", ";
                    } else {
                        $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ( $parameters['sSortDir_' . $i] ) . ", ";
                    }
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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 'SHIP_YEAR' => 'year(s.shipment_date)', 'TOTALSHIPMEN' => new Zend_Db_Expr("COUNT('s.shipment_id')")))
                ->joinLeft(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id', array('ONTIME' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,3,1) WHEN 1 THEN 1 END)"), 'NORESPONSE' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,2,1) WHEN 9 THEN 1 END)"), 'reported_count' => new Zend_Db_Expr("SUM(shipment_test_date <> '')")))
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=sp.participant_id')
                ->where("s.status='shipped' OR s.status='evaluated'")
                ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
                ->where("pmm.dm_id=?", $this->_session->dm_id)
                ->group('s.scheme_type')
                ->group('SHIP_YEAR');

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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type'))
                ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id', array(''))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=sp.participant_id', array(''))
                ->where("s.status='shipped' OR s.status='evaluated'")
                ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
                ->where("pmm.dm_id=?", $this->_session->dm_id)
                ->group('s.scheme_type');

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
            $row[] = $aRow['SHIP_YEAR'];
            $row[] = strtoupper($aRow['scheme_type']);
            $row[] = $aRow['TOTALSHIPMEN'];
            $row[] = $aRow['ONTIME'];
            $row[] = $aRow['TOTALSHIPMEN'] - $aRow['reported_count'];

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getShipmentCurrentDetails($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'scheme_type', 'shipment_code', 'first_name', 'DATE_FORMAT(lastdate_response,"%d-%b-%Y")', 'DATE_FORMAT(spm.shipment_test_report_date,"%d-%b-%Y")');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;

        $sTable = $this->_name;
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
                    if ($parameters['iSortCol_' . $i] == 0) {
                        $sOrder .= "shipment_date " . ( $parameters['sSortDir_' . $i] ) . ", ";
                    } else {
                        $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ( $parameters['sSortDir_' . $i] ) . ", ";
                    }
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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id','s.status'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array("spm.map_id","spm.evaluation_status", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')","RESPONSE" => new Zend_Db_Expr("CASE  WHEN substr(spm.evaluation_status,3,1)='1' THEN 'View' WHEN (substr(spm.evaluation_status,3,1)='9' AND s.lastdate_response>= CURDATE()) OR (s.status= 'finalized') THEN 'Enter Result' END")))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.first_name', 'p.last_name'))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
                ->where("pmm.dm_id=?", $this->_session->dm_id)
                ->where("s.status='shipped' OR s.status='evaluated'")
                ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
                ->where("s.lastdate_response >=  CURDATE()")
        //->order('s.shipment_date')
        //->order('spm.participant_id')
        ;

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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.shipment_id','s.status'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.map_id'))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array(''))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array(''))
                ->where("pmm.dm_id=?", $this->_session->dm_id)
                ->where("s.status='shipped' OR s.status='evaluated'")
                ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
                ->where("s.lastdate_response >=  CURDATE()");

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

        $general = new Pt_Commons_General();
        $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
        foreach ($rResult as $aRow) {
            $delete='';
            $isEditable=$shipmentParticipantDb->isShipmentEditable($aRow['shipment_id'],$aRow['participant_id']);
            $row = array();
            if ($aRow['RESPONSE'] == "View") {
                $aRow['RESPONSE'] = "View";
                if ($aRow['lastdate_response'] > date('Y-m-d')) {
                    $aRow['RESPONSE'] = "Edit/View";
                }
            }
            $row[] = $general->humanDateFormat($aRow['shipment_date']);
            $row[] = strtoupper($aRow['scheme_type']);
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = $general->humanDateFormat($aRow['lastdate_response']);
            $row[] = $general->humanDateFormat($aRow['RESPONSEDATE']);
            if($aRow['status']!='finalized' && $aRow['RESPONSEDATE']!='' && $aRow['RESPONSEDATE']!='0000-00-00'){
             $delete='<a href="javascript:void(0);" onclick="removeSchemes(\'' . $aRow['scheme_type']. '\',\'' . base64_encode($aRow['map_id']) . '\')" style="text-decoration : underline;"> Delete</a>';
            }
            if($isEditable){
            $row[] = '<a href="/' . $aRow['scheme_type'] . '/response/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" style="text-decoration : underline;">' . $aRow['RESPONSE'] . '</a> '.$delete;
            }else{
                $row[] = '';
            }

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getShipmentDefaultDetails($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('year(shipment_date)', 'DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'scheme_type', 'shipment_code', 'first_name', 'DATE_FORMAT(lastdate_response,"%d-%b-%Y")', 'DATE_FORMAT(spm.shipment_test_report_date,"%d-%b-%Y")');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;

        $sTable = $this->_name;
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
                    if ($parameters['iSortCol_' . $i] == 1) {
                        $sOrder .= "shipment_date " . ( $parameters['sSortDir_' . $i] ) . ", ";
                    } else {
                        $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ( $parameters['sSortDir_' . $i] ) . ", ";
                    }
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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.status','SHIP_YEAR' => 'year(s.shipment_date)', 's.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array("spm.map_id","spm.evaluation_status", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", "ACTION" => new Zend_Db_Expr("CASE  WHEN substr(spm.evaluation_status,2,1)='1' THEN 'View' WHEN (substr(spm.evaluation_status,2,1)='9' AND s.lastdate_response>= CURDATE()) OR (s.status= 'finalized') THEN 'Enter Result' END"), "STATUS" => new Zend_Db_Expr("CASE substr(spm.evaluation_status,3,1) WHEN 1 THEN 'On Time' WHEN '2' THEN 'Late' WHEN '0' THEN 'No Response' END")))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.first_name', 'p.last_name','p.participant_id'))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
                ->where("pmm.dm_id=?", $this->_session->dm_id)
                ->where("s.status='shipped' OR s.status='evaluated'")
                ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
                ->where("s.lastdate_response <  CURDATE()")
                ->where("substr(spm.evaluation_status,3,1) <> '1'")
                ->order('s.shipment_date')
                ->order('spm.participant_id');

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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.shipment_id'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array(''))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array(''))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array(''))
                ->where("pmm.dm_id=?", $this->_session->dm_id)
                ->where("s.status='shipped' OR s.status='evaluated'")
                ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
                ->where("s.lastdate_response <  CURDATE()")
                ->where("substr(spm.evaluation_status,3,1) <> '1'")
        //->order('s.shipment_date')
        //->order('spm.participant_id')
        ;

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

        $general = new Pt_Commons_General();
        $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
        foreach ($rResult as $aRow) {
            $delete='';
            $isEditable=$shipmentParticipantDb->isShipmentEditable($aRow['shipment_id'],$aRow['participant_id']);
            $row = array();
            if ($aRow['ACTION'] == "View") {
                $aRow['ACTION'] = "View";
                if ($aRow['lastdate_response'] > date('Y-m-d')) {
                    $aRow['ACTION'] = "Edit/View";
                }
            }

            $row[] = $aRow['SHIP_YEAR'];
            $row[] = $general->humanDateFormat($aRow['shipment_date']);
            $row[] = strtoupper($aRow['scheme_type']);
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = $general->humanDateFormat($aRow['lastdate_response']);
            $row[] = $aRow['STATUS'];
            $row[] = $general->humanDateFormat($aRow['RESPONSEDATE']);
            
             if($aRow['status']!='finalized' && $aRow['RESPONSEDATE']!='' && $aRow['RESPONSEDATE']!='0000-00-00'){
             $delete='<a href="javascript:void(0);" onclick="removeSchemes(\'' . $aRow['scheme_type']. '\',\'' . base64_encode($aRow['map_id']) . '\')" style="text-decoration : underline;"> Delete</a>';
            }
            if($isEditable){
            $row[] = '<a href="/' . $aRow['scheme_type'] . '/response/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" style="text-decoration : underline;">' . $aRow['ACTION'] . '</a> '.$delete;
            }else{
                $row[] ='';
            }

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getShipmentAllDetails($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('year(shipment_date)', 'DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'scheme_type', 'shipment_code', 'first_name', 'DATE_FORMAT(spm.shipment_test_report_date,"%d-%b-%Y")');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;

        $sTable = $this->_name;
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
                    if ($parameters['iSortCol_' . $i] == 1) {
                        $sOrder .= "shipment_date " . ( $parameters['sSortDir_' . $i] ) . ", ";
                    } else {
                        $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ( $parameters['sSortDir_' . $i] ) . ", ";
                    }
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
        
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('SHIP_YEAR' => 'year(s.shipment_date)', 's.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id','s.status'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.report_generated','spm.map_id', "spm.evaluation_status", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", "RESPONSE" => new Zend_Db_Expr("CASE  WHEN substr(spm.evaluation_status,3,1)='1' THEN 'View' WHEN (substr(spm.evaluation_status,3,1)='9' AND s.lastdate_response >= CURDATE()) OR (substr(spm.evaluation_status,3,1)='9' AND s.status= 'finalized') THEN 'Enter Result' END"), "REPORT" => new Zend_Db_Expr("CASE  WHEN spm.report_generated='yes' AND s.status='finalized' THEN 'Report' END")))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.first_name', 'p.last_name','p.participant_id'))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
                ->where("pmm.dm_id=?", $this->_session->dm_id)
                ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'")
                ->where("year(s.shipment_date)  + 5 > year(CURDATE())");
        //->order('s.shipment_date')
        //->order('spm.participant_id')
       // error_log($this->_session->dm_id);
     
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
        //$sQuery = $this->getAdapter()->select()->from('building_type', new Zend_Db_Expr("COUNT('building_id')"));
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.shipment_id'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array(''))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array(''))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array(''))
                ->where("pmm.dm_id=?", $this->_session->dm_id)
                ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'")
                ->where("year(s.shipment_date)  + 5 > year(CURDATE())");

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

        $general = new Pt_Commons_General();
        $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
        foreach ($rResult as $aRow) {
            $delete='';
            $isEditable=$shipmentParticipantDb->isShipmentEditable($aRow['shipment_id'],$aRow['participant_id']);
            $row = array();
            if ($aRow['RESPONSE'] == "View") {
                $aRow['RESPONSE'] = "View";
                if ($aRow['lastdate_response'] > date('Y-m-d')) {
                    $aRow['RESPONSE'] = "Edit/View";
                }
            }
            
            $aRow['lastdate_response'];
            $row[] = $aRow['SHIP_YEAR'].' '.$aRow['evaluation_status'];
            $row[] = $general->humanDateFormat($aRow['shipment_date']);
            $row[] = strtoupper($aRow['scheme_type']);
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = $general->humanDateFormat($aRow['RESPONSEDATE']);
            
            if($aRow['status']!='finalized' && $aRow['RESPONSEDATE']!='' && $aRow['RESPONSEDATE']!='0000-00-00'){
             $delete='<a href="javascript:void(0);" onclick="removeSchemes(\'' . $aRow['scheme_type']. '\',\'' . base64_encode($aRow['map_id']) . '\')" style="text-decoration : underline;"> Delete</a>';
            }
            
            if($isEditable){
            $row[] = '<a href="/' . $aRow['scheme_type'] . '/response/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" style="text-decoration : underline;">' . $aRow['RESPONSE'] . '</a>  '.$delete;
            }else{
                $row[] ='';
            }

            $row[] = '<a href="/participant/download/d92nl9d8d/' . base64_encode($aRow['map_id']) . '"  style="text-decoration : underline;" target="_BLANK">' . $aRow['REPORT'] . '</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getShipmentReportDetails($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('year(shipment_date)', 'DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'scheme_type', 'shipment_code');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;

        $sTable = $this->_name;
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
                    if ($parameters['iSortCol_' . $i] == 1) {
                        $sOrder .= "shipment_date " . ( $parameters['sSortDir_' . $i] ) . ", ";
                    } else {
                        $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ( $parameters['sSortDir_' . $i] ) . ", ";
                    }
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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('SHIP_YEAR' => 'year(s.shipment_date)', 's.scheme_type', 's.shipment_date', 's.shipment_code', 's.shipment_id','s.status'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.map_id', "spm.participant_id"))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.first_name', 'p.last_name'))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
                ->join(array('dm' => 'data_manager'), 'dm.dm_id=pmm.dm_id', array('dm.institute'))
                ->where("pmm.dm_id=?", $this->_session->dm_id)
                ->where("s.status='shipped' OR s.status='evaluated' OR s.status='finalized'")
                ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
                ->group('s.shipment_id');

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

        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.shipment_id'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array(''))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array(''))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array(''))
                ->where("pmm.dm_id=?", $this->_session->dm_id)
                ->where("s.status='shipped' OR s.status='evaluated' OR s.status='finalized'")
                ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
                ->group('s.shipment_id');

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

        $general = new Pt_Commons_General();
        foreach ($rResult as $aRow) {
            $row = array();
            $report = "";
            $fileName = $aRow['shipment_code'] . "-" . $aRow['institute'] . $aRow['dm_id'] . ".pdf";
            $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $fileName);
            $fileName = str_replace(" ", "-", $fileName);

            $row[] = $aRow['SHIP_YEAR'];
            $row[] = $general->humanDateFormat($aRow['shipment_date']);
            $row[] = strtoupper($aRow['scheme_type']);
            $row[] = $aRow['shipment_code'];

            $filePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $fileName;
            if (file_exists($filePath) && $aRow['status']=='finalized' ) {
                $downloadFilePath = "/uploads" . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $fileName;
                $report = '<a href="' . $downloadFilePath . '"  style="text-decoration : underline;" target="_BLANK">Report</a>';
            }
            $row[] = $report;

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getindividualReportDetails($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('scheme_type', 'shipment_code', 'DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'first_name', 'DATE_FORMAT(spm.shipment_test_report_date,"%d-%b-%Y")');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;

        $sTable = $this->_name;
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
                    if ($parameters['iSortCol_' . $i] == 1) {
                        $sOrder .= "shipment_date " . ( $parameters['sSortDir_' . $i] ) . ", ";
                    } else {
                        $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ( $parameters['sSortDir_' . $i] ) . ", ";
                    }
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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('SHIP_YEAR' => 'year(s.shipment_date)', 's.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.map_id', "spm.evaluation_status", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", "RESPONSE" => new Zend_Db_Expr("CASE substr(spm.evaluation_status,3,1) WHEN 1 THEN 'View' WHEN '9' THEN 'Enter Result' END"), "REPORT" => new Zend_Db_Expr("CASE  WHEN spm.report_generated='yes' AND s.status='finalized' THEN 'Report' END")))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.first_name', 'p.last_name'))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
                ->where("pmm.dm_id=?", $this->_session->dm_id)
                ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'");

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.shipment_id'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array(''))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array(''))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array(''))
                ->where("pmm.dm_id=?", $this->_session->dm_id)
                 ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'");

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

        $general = new Pt_Commons_General();
        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = strtoupper($aRow['scheme_type']);
            $row[] = $aRow['shipment_code'];
            $row[] = $general->humanDateFormat($aRow['shipment_date']);
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = $general->humanDateFormat($aRow['RESPONSEDATE']);
            $row[] = '<a href="/participant/download/d92nl9d8d/' . base64_encode($aRow['map_id']) . '"  style="text-decoration : underline;" target="_BLANK">' . $aRow['REPORT'] . '</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getSummaryReportDetails($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('scheme_type', 'shipment_code', 'DATE_FORMAT(shipment_date,"%d-%b-%Y")');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;

        $sTable = $this->_name;
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
                    if ($parameters['iSortCol_' . $i] == 1) {
                        $sOrder .= "shipment_date " . ( $parameters['sSortDir_' . $i] ) . ", ";
                    } else {
                        $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ( $parameters['sSortDir_' . $i] ) . ", ";
                    }
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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 's.shipment_date', 's.shipment_code','s.status'))
                ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'");


        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
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
        $$sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 's.shipment_date', 's.shipment_code'))
                ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'");


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

        $general = new Pt_Commons_General();
        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = strtoupper($aRow['scheme_type']);
            $row[] = $aRow['shipment_code'];
            $row[] = $general->humanDateFormat($aRow['shipment_date']);
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . "summary.pdf") && $aRow['status']=='finalized') {
                 $row[] = '<a href="/uploads/reports/' . $aRow['shipment_code']. '/summary.pdf"  style="text-decoration : none;" target="_BLANK">Report</a>';
            } else {
                $row[] = '';
            }
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

}
