<?php

class Application_Model_DbTable_Shipments extends Zend_Db_Table_Abstract
{

    protected $_name = 'shipment';
    protected $_primary = 'shipment_id';
    protected $_session = null;

    public function __construct()
    {
        parent::__construct();
        $this->_session = new Zend_Session_Namespace('datamanagers');
    }

    public function getShipmentData($sId, $pId)
    {
        return $this->getAdapter()->fetchRow($this->getAdapter()->select()->from(array('s' => $this->_name))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_name'))
            ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
            ->joinLeft(array('r_vl_r' => 'response_vl_not_tested_reason'), 'r_vl_r.vl_not_tested_reason_id=sp.vl_not_tested_reason', array('vlNotTestedReason' => 'vl_not_tested_reason'))
            ->where("s.shipment_id = ?", $sId)
            ->where("sp.participant_id = ?", $pId));
    }

    public function getShipmentRowInfo($sId)
    {
        $result = $this->getAdapter()->fetchRow($this->getAdapter()->select()->from(array('s' => 'shipment'))
            ->join(array('d' => 'distributions'), 'd.distribution_id = s.distribution_id', array('distribution_code', 'distribution_date'))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('sl.scheme_name'))
            ->group('s.shipment_id')
            ->where("s.shipment_id = ?", $sId));
        if ($result != "") {
            $tableName = "reference_result_dts";
            if ($result['scheme_type'] == 'vl') {
                $tableName = "reference_result_vl";
            } elseif ($result['scheme_type'] == 'eid') {
                $tableName = "reference_result_eid";
            } elseif ($result['scheme_type'] == 'dts') {
                $tableName = "reference_result_dts";
            }
            $result['referenceResult'] = $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(array($tableName))
                ->where('shipment_id = ? ', $result['shipment_id']));
        }
        return $result;
    }

    public function updateShipmentStatus($shipmentId, $status)
    {
        if (isset($status) && $status != null && $status != "") {
            return $this->update(array('status' => $status), "shipment_id = $shipmentId");
        } else {
            return 0;
        }
    }

    public function responseSwitch($shipmentId, $switchStatus)
    {
        if (isset($switchStatus) && $switchStatus != null && $switchStatus != "") {
            $this->update(array('response_switch' => $switchStatus), "shipment_id = $shipmentId");
            return "Shipment Response Status updated to " . strtoupper($switchStatus) . " successfully";
        } else {
            return "Unable to change Shipment Response Status to " . strtoupper($switchStatus) . ". Please try again later.";
        }
    }

    public function updateShipmentStatusByDistribution($distributionId, $status)
    {
        if (isset($status) && $status != null && $status != "") {
            return $this->update(array('response_switch' => 'on', 'status' => $status), "distribution_id = $distributionId");
        } else {
            return 0;
        }
    }

    public function getPendingShipmentsByDistribution($distributionId)
    {
        return $this->fetchAll("status ='pending' AND distribution_id = $distributionId");
    }

    public function getShipmentOverviewDetails($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('year(shipment_date)', 'scheme_name');
        $orderColumns = array('shipment_date', 'scheme_name');

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
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
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
         * Get data to display */
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 'SHIP_YEAR' => 'year(s.shipment_date)', 'TOTALSHIPMEN' => new Zend_Db_Expr("COUNT('s.shipment_id')")))
            ->joinLeft(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id', array('ONTIME' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,3,1) WHEN 1 THEN 1 END)"), 'NORESPONSE' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,2,1) WHEN 9 THEN 1 END)"), 'reported_count' => new Zend_Db_Expr("SUM(shipment_test_date <> '0000-00-00' OR is_pt_test_not_performed ='yes')")))
            ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=sp.participant_id')
            ->joinLeft(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type')
            ->where("s.status='shipped' OR s.status='evaluated' OR s.status='finalized'")
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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 'SHIP_YEAR' => 'year(s.shipment_date)', 'TOTALSHIPMEN' => new Zend_Db_Expr("COUNT('s.shipment_id')")))
            ->joinLeft(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id', array('ONTIME' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,3,1) WHEN 1 THEN 1 END)"), 'NORESPONSE' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,2,1) WHEN 9 THEN 1 END)"), 'reported_count' => new Zend_Db_Expr("SUM(shipment_test_date <> '0000-00-00' OR is_pt_test_not_performed ='yes')")))
            ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=sp.participant_id')
            ->joinLeft(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type')
            ->where("s.status='shipped' OR s.status='evaluated' OR s.status='finalized'")
            ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
            ->where("pmm.dm_id=?", $this->_session->dm_id)
            ->group('s.scheme_type')
            ->group('SHIP_YEAR');

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
            $row[] = ($aRow['scheme_name']);
            $row[] = $aRow['TOTALSHIPMEN'];
            $row[] = $aRow['ONTIME'];
            $row[] = $aRow['TOTALSHIPMEN'] - $aRow['reported_count'];

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getShipmentCurrentDetails($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'scheme_name', 'shipment_code', 'unique_identifier', 'first_name', 'DATE_FORMAT(lastdate_response,"%d-%b-%Y")', 'DATE_FORMAT(spm.shipment_test_report_date,"%d-%b-%Y")');
        $orderColumns = array('shipment_date', 'scheme_name', 'shipment_code', 'unique_identifier', 'first_name', 'lastdate_response', 'spm.shipment_test_report_date');

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
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id', 's.status', 's.response_switch'))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array("spm.map_id", "spm.evaluation_status", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')"))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.state'))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
            ->where("pmm.dm_id=?", $this->_session->dm_id)
            ->where("s.status='shipped' OR s.status='evaluated'")
            //->where("year(s.shipment_date)  + 5 > year(CURDATE())")
            //->where("s.lastdate_response >=  CURDATE()")
            //->order('s.shipment_date')
            //->order('spm.participant_id')
        ;

        if (isset($parameters['currentType'])) {
            if ($parameters['currentType'] == 'active') {
                $sQuery = $sQuery->where("s.response_switch = 'on'");
            } else if ($parameters['currentType'] == 'inactive') {
                $sQuery = $sQuery->where("s.response_switch = 'off'");
            }
        }

        if (isset($parameters['shipmentCode']) && $parameters['shipmentCode'] != "") {
            $sQuery = $sQuery->where("s.shipment_code = '" . $parameters['shipmentCode'] . "'");
        }
        if (isset($parameters['province']) && $parameters['province'] != "") {
            $sQuery = $sQuery->where("p.state = '" . $parameters['province'] . "'");
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
        // echo $sQuery;die;
        $rResult = $this->getAdapter()->fetchAll($sQuery);
        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.status', 's.shipment_code'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.map_id'))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.state'))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array(''))
            ->where("pmm.dm_id=?", $this->_session->dm_id)
            ->where("s.status='shipped' OR s.status='evaluated'")
            ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
            //->where("s.lastdate_response >=  CURDATE()")
        ;

        if (isset($parameters['currentType'])) {
            if ($parameters['currentType'] == 'active') {
                $sQuery = $sQuery->where("s.response_switch = 'on'");
            } else if ($parameters['currentType'] == 'inactive') {
                $sQuery = $sQuery->where("s.response_switch = 'off'");
            }
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

        $general = new Pt_Commons_General();
        $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
        foreach ($rResult as $aRow) {
            $delete = '';
            $download = '';
            $isEditable = $shipmentParticipantDb->isShipmentEditable($aRow['shipment_id'], $aRow['participant_id']);
            $row = array();
            $row[] = $general->humanDateFormat($aRow['shipment_date']);
            $row[] = ($aRow['scheme_name']);
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = $general->humanDateFormat($aRow['lastdate_response']);
            $row[] = $general->humanDateFormat($aRow['RESPONSEDATE']);

            $buttonText = "View/Edit";
            $download = '';
            $delete = '';
            if ($isEditable) {
                if ($aRow['RESPONSEDATE'] != '' && $aRow['RESPONSEDATE'] != '0000-00-00') {
                    if ($this->_session->view_only_access == 'no') {
                        $delete = '<br/><a href="javascript:void(0);" onclick="removeSchemes(\'' . $aRow['scheme_type'] . '\',\'' . base64_encode($aRow['map_id']) . '\')" class="btn btn-danger" style="margin:3px 0;"> <i class="icon icon-remove-sign"></i> Delete Response</a>';
                    }
                } else {
                    $buttonText = "Enter Response";
                    $download = '<br/><a href="/' . $aRow['scheme_type'] . '/download/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" class="btn btn-default"  style="margin:3px 0;" target="_BLANK" download > <i class="icon icon-download"></i> Download Form</a>';
                }
            }

            $row[] = '<a href="/' . $aRow['scheme_type'] . '/response/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" class="btn btn-success" style="margin:3px 0;"> <i class="icon icon-edit"></i>  ' . $buttonText . ' </a>'
                . $delete
                . $download;


            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getShipmentDefaultDetails($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('year(shipment_date)', 'DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'scheme_name', 'shipment_code', 'unique_identifier', 'first_name', 'DATE_FORMAT(lastdate_response,"%d-%b-%Y")', 'DATE_FORMAT(spm.shipment_test_report_date,"%d-%b-%Y")');
        $orderColumns = array('shipment_date', 'shipment_date', 'scheme_name', 'shipment_code', 'unique_identifier', 'first_name', 'lastdate_response', 'spm.shipment_test_report_date');

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
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.status', 'SHIP_YEAR' => 'year(s.shipment_date)', 's.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id', 's.response_switch'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array("spm.map_id", "spm.evaluation_status", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", "ACTION" => new Zend_Db_Expr("CASE  WHEN substr(spm.evaluation_status,2,1)='1' THEN 'View' WHEN (substr(spm.evaluation_status,2,1)='9' AND s.lastdate_response>= CURDATE()) OR (s.status= 'finalized') THEN 'Enter Result' END"), "STATUS" => new Zend_Db_Expr("CASE substr(spm.evaluation_status,3,1) WHEN 1 THEN 'On Time' WHEN '2' THEN 'Late' WHEN '0' THEN 'No Response' END")))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.participant_id'))
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
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.participant_id'))
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
            $delete = '';
            $isEditable = $shipmentParticipantDb->isShipmentEditable($aRow['shipment_id'], $aRow['participant_id']);
            $row = array();
            if ($aRow['ACTION'] == "View") {
                $aRow['ACTION'] = "View";
                if ($aRow['response_switch'] == 'on' && $aRow['status'] != 'finalized') {
                    $aRow['ACTION'] = "Edit/View";
                }
            }

            $row[] = $aRow['SHIP_YEAR'];
            $row[] = $general->humanDateFormat($aRow['shipment_date']);
            $row[] = ($aRow['scheme_name']);
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = $general->humanDateFormat($aRow['lastdate_response']);
            $row[] = $aRow['STATUS'];
            $row[] = $general->humanDateFormat($aRow['RESPONSEDATE']);

            // if($aRow['status']!='finalized' && $aRow['RESPONSEDATE']!='' && $aRow['RESPONSEDATE']!='0000-00-00'){
            // $delete='<a href="javascript:void(0);" onclick="removeSchemes(\'' . $aRow['scheme_type']. '\',\'' . base64_encode($aRow['map_id']) . '\')" style="text-decoration : underline;"> Delete</a>';
            //}
            //if($isEditable){
            //$row[] = '<a href="/' . $aRow['scheme_type'] . '/response/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" style="text-decoration : underline;">' . $aRow['ACTION'] . '</a> '.$delete;
            //}else{
            //    $row[] ='';
            //}
            $buttonText = "View/Edit";
            $download = '';
            $delete = '';
            if ($isEditable) {
                if ($aRow['RESPONSEDATE'] != '' && $aRow['RESPONSEDATE'] != '0000-00-00') {
                    if ($this->_session->view_only_access == 'no') {
                        $delete = '<br/><a href="javascript:void(0);" onclick="removeSchemes(\'' . $aRow['scheme_type'] . '\',\'' . base64_encode($aRow['map_id']) . '\')" class="btn btn-danger"  style="margin:3px 0;"> <i class="icon icon-remove-sign"></i> Delete Response</a>';
                    }
                } else {
                    $buttonText = "Enter Response";
                    $download = '<br/><a href="/' . $aRow['scheme_type'] . '/download/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" class="btn btn-default" style="margin:3px 0;" target="_BLANK" download> <i class="icon icon-download"></i> Download Form</a>';
                }
            }

            $row[] = '<a href="/' . $aRow['scheme_type'] . '/response/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '/comingFrom/defaulted-schemes' . '" class="btn btn-success"  style="margin:3px 0;"> <i class="icon icon-edit"></i>  ' . $buttonText . ' </a>'
                . $delete
                . $download;

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getShipmentAllDetails($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('s.shipment_id', 'year(shipment_date)', 'DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'scheme_name', 'shipment_code', 'unique_identifier', 'first_name', 'DATE_FORMAT(spm.shipment_test_report_date,"%d-%b-%Y")');
        $orderColumns = array('s.shipment_id', 'shipment_date', 'shipment_date', 'scheme_name', 'shipment_code', 'unique_identifier', 'first_name', 'spm.shipment_test_report_date');

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
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
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

        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('SHIP_YEAR' => 'year(s.shipment_date)', 's.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id', 's.status', 's.response_switch'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.report_generated', 'spm.map_id', "spm.evaluation_status", "qc_date", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", "RESPONSE" => new Zend_Db_Expr("CASE  WHEN substr(spm.evaluation_status,3,1)='1' THEN 'View' WHEN (substr(spm.evaluation_status,3,1)='9' AND s.lastdate_response >= CURDATE()) OR (substr(spm.evaluation_status,3,1)='9' AND s.status= 'finalized') THEN 'Enter Result' END"), "REPORT" => new Zend_Db_Expr("CASE  WHEN spm.report_generated='yes' AND s.status='finalized' THEN 'Report' END")))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.participant_id'))
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
        if (isset($parameters['qualityChecked']) && trim($parameters['qualityChecked']) != "") {
            if ($parameters['qualityChecked'] == 'yes') {
                $sQuery = $sQuery->where("spm.qc_date IS NOT NULL");
            } else {
                $sQuery = $sQuery->where("spm.qc_date IS NULL");
            }
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
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.participant_id'))
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
        $globalQcAccess = Application_Service_Common::getConfig('qc_access');
        $general = new Pt_Commons_General();
        $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
        foreach ($rResult as $aRow) {
            $delete = '';
            $download = '';
            $qcChkbox = '';
            $qcResponse = '';

            $isEditable = $shipmentParticipantDb->isShipmentEditable($aRow['shipment_id'], $aRow['participant_id']);
            $row = array();
            if ($aRow['RESPONSE'] == "View") {
                $aRow['RESPONSE'] = "View";
                if ($aRow['response_switch'] == 'on' && $aRow['status'] != 'finalized') {
                    $aRow['RESPONSE'] = "Edit/View";
                }
            }

            //$aRow['lastdate_response'];

            $qcBtnText = " Quality Check";
            if ($aRow['RESPONSEDATE'] != '' && $aRow['RESPONSEDATE'] != '0000-00-00') {
                if ($aRow['qc_date'] != "") {
                    $qcBtnText = " Edit Quality Check";
                    $aRow['qc_date'] = $general->humanDateFormat($aRow['qc_date']);
                }
                if ($globalQcAccess == 'yes') {
                    if ($this->_session->qc_access == 'yes') {
                        $qcChkbox = '<input type="checkbox" class="checkTablePending" name="subchk[]" id="' . $aRow['map_id'] . '"  value="' . $aRow['map_id'] . '" onclick="addQc(\'' . $aRow['map_id'] . '\',this);"  />';
                        $qcResponse = '<br/><a href="javascript:void(0);" onclick="addSingleQc(\'' . $aRow['map_id'] . '\',\'' . $aRow['qc_date'] . '\')" class="btn btn-primary"  style="margin:3px 0;"> <i class="icon icon-edit"></i>' . $qcBtnText . '</a>';
                    }
                }
            }
            $row[] = $qcChkbox;
            $row[] = $aRow['SHIP_YEAR'];
            $row[] = $general->humanDateFormat($aRow['shipment_date']);
            $row[] = ($aRow['scheme_name']);
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = $general->humanDateFormat($aRow['RESPONSEDATE']);

            //            if($aRow['status']!='finalized' && $aRow['RESPONSEDATE']!='' && $aRow['RESPONSEDATE']!='0000-00-00'){
            //             $delete='<a href="javascript:void(0);" onclick="removeSchemes(\'' . $aRow['scheme_type']. '\',\'' . base64_encode($aRow['map_id']) . '\')" style="text-decoration : underline;"> Delete</a>';
            //            }
            //            if($aRow['RESPONSE']=="Enter Result"){
            //				$download='<a href="/' . $aRow['scheme_type'] . '/download/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" style="text-decoration : underline;" target="_BLANK"> Download</a>';
            //			}
            //            if($isEditable){
            //            $row[] = '<a href="/' . $aRow['scheme_type'] . '/response/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" style="text-decoration : underline;">' . $aRow['RESPONSE'] . '</a>  '.$download.$delete;
            //            }else{
            //                $row[] ='';
            //            }

            $buttonText = "View";
            $download = '';
            $delete = '';


            if ($isEditable) {
                if ($aRow['RESPONSEDATE'] != '' && $aRow['RESPONSEDATE'] != '0000-00-00') {
                    if ($this->_session->view_only_access == 'no') {
                        $delete = '<br/><a href="javascript:void(0);" onclick="removeSchemes(\'' . $aRow['scheme_type'] . '\',\'' . base64_encode($aRow['map_id']) . '\')" class="btn btn-danger"  style="margin:3px 0;"> <i class="icon icon-remove-sign"></i> Delete Response</a>';
                    }
                } else {
                    $buttonText = "Enter Response";
                    $download = '<br/><a href="/' . $aRow['scheme_type'] . '/download/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" class="btn btn-default"  style="margin:3px 0;" target="_BLANK"> <i class="icon icon-download"></i> Download Form</a>';
                }
            }

            $row[] = '<a href="/' . $aRow['scheme_type'] . '/response/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '/comingFrom/all-schemes' . '" class="btn btn-success"  style="margin:3px 0;"> <i class="icon icon-edit"></i>  ' . $buttonText . ' </a>'
                . $delete
                . $download
                . $qcResponse;

            $downloadReports = " N.A. ";
            if ($aRow['status'] == 'finalized') {
                $downloadReports = "";
                $summaryFilePath = (DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . "-summary.pdf");
                if (file_exists($summaryFilePath)) {
                    $downloadReports .= '<a href="/d/' . base64_encode($summaryFilePath) . '" class="btn btn-primary" style="text-decoration : none;overflow:hidden;" target="_BLANK" download><i class="icon icon-download"></i> Summary Report</a>';
                }
                $invididualFilePath = (DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . "-" . $aRow['map_id'] . ".pdf");
                if (!file_exists($invididualFilePath)) {
                    // Search this file name using the map id
                    $files = glob(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . "*" . $aRow['map_id'] . ".pdf");
                    $invididualFilePath = isset($files[0]) ? $files[0] : '';
                }
                if (file_exists($invididualFilePath)) {
                    $downloadReports .= '<br><a href="/d/' . base64_encode($invididualFilePath) . '" class="btn btn-primary"   style="text-decoration : none;overflow:hidden;margin-top:4px;"  target="_BLANK" download><i class="icon icon-download"></i> Individual Report</a>';
                }
            }
            $row[] = $downloadReports;




            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getShipmentReportDetails($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('year(shipment_date)', 'DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'scheme_type', 'shipment_code');
        $orderColumns = array('shipment_date', 'shipment_date', 'scheme_type', 'shipment_code');

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
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('SHIP_YEAR' => 'year(s.shipment_date)', 's.scheme_type', 's.shipment_date', 's.shipment_code', 's.shipment_id', 's.status'))
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
            if (file_exists($filePath) && $aRow['status'] == 'finalized') {
                $downloadFilePath = "/uploads" . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $fileName;
                $report = '<a href="' . $downloadFilePath . '"  style="text-decoration : underline;" target="_BLANK">Report</a>';
            }
            $row[] = $report;

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getindividualReportDetails($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('scheme_type', 'shipment_code', 'DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'unique_identifier', 'first_name', 'DATE_FORMAT(spm.shipment_test_report_date,"%d-%b-%Y")');
        $orderColumns = array('scheme_type', 'shipment_code', 'shipment_date', 'unique_identifier', 'first_name', 'spm.shipment_test_report_date');

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
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('SHIP_YEAR' => 'year(s.shipment_date)', 's.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.map_id', "spm.evaluation_status", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", "RESPONSE" => new Zend_Db_Expr("CASE substr(spm.evaluation_status,3,1) WHEN 1 THEN 'View' WHEN '9' THEN 'Enter Result' END"), "REPORT" => new Zend_Db_Expr("CASE  WHEN spm.report_generated='yes' AND s.status='finalized' THEN 'Report' END")))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name'))
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
        //echo($sQuery);die;
        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.shipment_id'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array(''))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name'))
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
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = $general->humanDateFormat($aRow['RESPONSEDATE']);
            $row[] = '<a href="/participant/download/d92nl9d8d/' . base64_encode($aRow['map_id']) . '"  style="text-decoration : underline;" target="_BLANK" download>' . $aRow['REPORT'] . '</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getSummaryReportDetails($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('scheme_type', 'shipment_code', 'DATE_FORMAT(shipment_date,"%d-%b-%Y")');
        $orderColumns = array('scheme_type', 'shipment_code', 'shipment_date');

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
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 's.shipment_date', 's.shipment_code', 's.status'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array())
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array())
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
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
        $$sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 's.shipment_date', 's.shipment_code'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array())
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array())
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
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
            if (file_exists(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . "-summary.pdf") && $aRow['status'] == 'finalized') {
                $filePath = base64_encode(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . "-summary.pdf");
                $row[] = '<a href="/d/' . $filePath . '"  style="text-decoration : none;" download target="_BLANK">Download Report</a>';
            } else {
                $row[] = '';
            }
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getAllShipmentFormDetails($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        //$aColumns = array('project_name','project_code','e.employee_name','client_name','architect_name','project_value','building_type_name','DATE_FORMAT(p.project_date,"%d-%b-%Y")','DATE_FORMAT(p.deadline,"%d-%b-%Y")','refered_by','emp.employee_name');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $aColumns = array("sl.scheme_name", "shipment_code", 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')");
        $orderColumns = array("sl.scheme_name", "shipment_code", 'distribution_code', 'distribution_date');


        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = "shipment_id";


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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . "
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

        $sQuery = $db->select()->from(array('s' => 'shipment'))
            ->join(array('d' => 'distributions'), 'd.distribution_id = s.distribution_id', array('distribution_code', 'distribution_date'))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('SCHEME' => 'sl.scheme_name'))
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
        //die($sQuery);

        $rResult = $db->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $db->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $db->select()->from('shipment', new Zend_Db_Expr("COUNT('shipment_id')"));
        $aResultTotal = $db->fetchCol($sQuery);
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
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['SCHEME'];
            $row[] = $aRow['distribution_code'];
            $row[] = Pt_Commons_General::humanDateFormat($aRow['distribution_date']);
            $row[] = '<a href="/shipment-form/download/sId/' . base64_encode($aRow['shipment_id']) . '"  style="text-decoration : underline;" target="_BLANK" download> Download </a>';
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function fecthAllFinalizedShipments($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array("DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'distribution_code', 's.shipment_code', 'd.status');
        $orderColumns = array('distribution_date', 'distribution_code', 's.shipment_code', 'd.status');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = 'distribution_id';

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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . "
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
                    if ($aColumns[$i] == "" || $aColumns[$i] == null) {
                        continue;
                    }
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

        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('d' => 'distributions'))
            ->joinLeft(array('s' => 'shipment'), 's.distribution_id=d.distribution_id', array('shipments' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT s.shipment_code SEPARATOR ', ')")))
            ->where("s.status='finalized'")
            ->group('d.distribution_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //die($sQuery);
        $rResult = $dbAdapter->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $dbAdapter->select()->from(array('d' => 'distributions'))
            ->joinLeft(array('s' => 'shipment'), 's.distribution_id=d.distribution_id', array(''))
            ->where("s.status='finalized'")
            ->group('d.distribution_id');
        $aResultTotal = $dbAdapter->fetchAll($sQuery);
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

        $shipmentDb = new Application_Model_DbTable_Shipments();
        foreach ($rResult as $aRow) {

            $shipmentResults = $shipmentDb->getPendingShipmentsByDistribution($aRow['distribution_id']);

            $row = array();
            $row['DT_RowId'] = "dist" . $aRow['distribution_id'];
            $row[] = Pt_Commons_General::humanDateFormat($aRow['distribution_date']);
            $row[] = $aRow['distribution_code'];
            $row[] = $aRow['shipments'];
            $row[] = ucwords($aRow['status']);
            $row[] = '<a class="btn btn-primary btn-xs" href="javascript:void(0);" onclick="getShipmentInReports(\'' . ($aRow['distribution_id']) . '\')"><span><i class="icon-search"></i> View</span></a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function fetchParticipantSchemesBySchemeId($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'shipment_code', 'unique_identifier', 'first_name', 'DATE_FORMAT(spm.shipment_test_report_date,"%d-%b-%Y")', 'shipment_score');
        $orderColumns = array('shipment_date', 'shipment_code', 'unique_identifier', 'first_name', 'spm.shipment_test_report_date', 'shipment_score');

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
                        $sOrder .= "shipment_date " . ($parameters['sSortDir_' . $i]) . ", ";
                    } else {
                        $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ($parameters['sSortDir_' . $i]) . ", ";
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

        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id', 's.status', 's.response_switch'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.report_generated', 'spm.map_id', "spm.evaluation_status", "qc_date", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", 'spm.shipment_score'))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.participant_id'))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
            ->where("pmm.dm_id=?", $this->_session->dm_id)
            ->where("s.status=?", "finalized")
            ->where("s.scheme_type=?", $parameters['scheme']);
        //->order('s.shipment_date')
        //->order('spm.participant_id')
        // error_log($this->_session->dm_id);
        //echo $sQuery;die;
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
        $tQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id', 's.status', 's.response_switch'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.report_generated', 'spm.map_id', "spm.evaluation_status", "qc_date", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", 'spm.shipment_score'))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.participant_id'))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
            ->where("pmm.dm_id=?", $this->_session->dm_id)
            ->where("s.scheme_type=?", $parameters['scheme']);
        $aResultTotal = $this->getAdapter()->fetchAll($tQuery);
        $shipmentArray = array();
        foreach ($aResultTotal as $total) {
            if (!in_array($total['shipment_code'], $shipmentArray)) {
                $shipmentArray[] = $total['shipment_code'];
            }
        }
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
            $row[] = $general->humanDateFormat($aRow['shipment_date']);
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = $general->humanDateFormat($aRow['RESPONSEDATE']);
            $row[] = $aRow['shipment_score'];
            $output['aaData'][] = $row;
        }
        $output['shipmentArray'] = $shipmentArray;
        echo json_encode($output);
    }
    public function fetchUniqueShipmentCode()
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()
            ->select()->from(array('s' => $this->_name), array('shipment_code' => new Zend_Db_Expr(" DISTINCT s.shipment_code "), 'shipment_id'))
            ->where("s.shipment_code IS NOT NULL")
            ->where("trim(s.shipment_code)!=''"));
    }

    public function fetchShipmentDetailsInAPI($params, $type)
    {
        /* Check the app versions & parameters */
        if (!isset($params['appVersion'])) {
            return array('status' =>'fail','message'=>'There is an error in APP version. Please update to latest version APP');
        }
        if (!isset($params['appVersion'])) {
            return array('status' =>'fail','message'=>'Authentication error');
        }
        $dmDb = new Application_Model_DbTable_DataManagers();
        $aResult = $dmDb->fetchAuthToken($params);
        /* App version check */
        if ($aResult == 'app-version-failed') {
            return array('status' =>'fail','message'=>'Please update to latest version APP');
        }
        /* Validate new auth token and app-version */
        if(!$aResult){
            return array('status' =>'fail','message'=>'Participant authentication error');
        }
        /* To check the shipment details for the data managers mapped participants */
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id', 's.status', 's.response_switch'))
        ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
        ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array("spm.map_id", "spm.evaluation_status", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')"))
        ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.state'))
        ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
        ->where("pmm.dm_id=?", $aResult['dm_id'])
        ->where("s.status='shipped' OR s.status='evaluated'");
        $rResult = $this->getAdapter()->fetchAll($sQuery);
        if (!isset($rResult) && count($rResult) == 0) {
            return array('status' =>'fail','message'=>'Shipment Details not available');
        }
        /* Start the API services */
        $data = array();$formData = array();$getParticipantDetails = array();
        $checkFormSatatus = false;
        foreach ($rResult as $key => $row) {
            $data[] = array(
                'schemeType'       => $row['scheme_type'],
                'shipmentId'       => $row['shipment_id'],
                'participantId'    => $row['participant_id'],
                'evaluationStatus' => $row['evaluation_status'],

                'shipmentDate'     => $row['shipment_date'],
                'shipmentCode'     => $row['shipment_code'],
                'resultDueDate'    => $row['lastdate_response'],
                'responseDate'     => $row['RESPONSEDATE'],
                'status'           => $row['status'],
                'responseSwitch'   => $row['response_switch'],
                'schemeName'       => $row['scheme_name'],
                'mapId'            => $row['map_id'],
                'uniqueIdentifier' => $row['unique_identifier'],
                'participantName'  => $row['first_name'] . ' ' . $row['last_name'],
                'state'            => $row['state'],
                'dmId'             => $row['dm_id']
            );
            /* This API to get the shipments form using type form */
            if ($type == 'form') {
                $formData[$key]['schemeType']       = $row['scheme_type'];
                $formData[$key]['shipmentId']       = $row['shipment_id'];
                $formData[$key]['participantId']    = $row['participant_id'];
                $formData[$key]['evaluationStatus'] = $row['evaluation_status'];

                $formData[$key][$row['scheme_type'] . 'Data'] = $this->fetchShipmentFormDetails($row, $aResult);
                if (isset($formData[$key][$row['scheme_type'] . 'Data']) && count($formData[$key][$row['scheme_type'] . 'Data']) > 0) {
                    $checkFormSatatus = true;
                    $getParticipantDetails[$key]['schemeType']       = $row['scheme_type'];
                    $getParticipantDetails[$key]['shipmentId']       = $row['shipment_id'];
                    $getParticipantDetails[$key]['participantId']    = $row['participant_id'];
                    $getParticipantDetails[$key]['evaluationStatus'] = $row['evaluation_status'];
                }
            }
        }
        /* This API to get the shipments form using type form and returning the response*/
        if ($type == 'form') {
            if ($checkFormSatatus) {
                return array('status'=>'success','data'=>$formData);
            } else {
                return array('status' =>'fail','message'=>"The following shipments doesn't have the shipment forms",'data'=>$getParticipantDetails);
            }
        }
        return array('status'=>'success','data'=>$data);
    }

    public function fetchShipmentFormDetails($params,$dm){
        // Service / Model Calling
        $general = new Pt_Commons_General();
        $participantDb  = new Application_Model_DbTable_Participants();
        $schemeService  = new Application_Service_Schemes();
        $commonService = new Application_Service_Common();
        $spMap = new Application_Model_DbTable_ShipmentParticipantMap();
        $date = new Zend_Date();

        // Initialte the global functions
        $participant    = $participantDb->getParticipant($params['participant_id']);
        if($params['scheme_type'] == 'dts'){
            $allSamples     = $schemeService->getDtsSamples($params['shipment_id'],$params['participant_id']);
        } else if($params['scheme_type'] == 'vl'){
            $allSamples     = $schemeService->getVlSamples($params['shipment_id'],$params['participant_id']);
        }
        $shipment = $schemeService->getShipmentData($params['shipment_id'],$params['participant_id']);
        $shipment['attributes'] = json_decode($shipment['attributes'],true);
        
        $modeOfReceipt=$commonService->getAllModeOfReceipt();
        $globalQcAccess=$commonService->getConfig('qc_access');
        $isEditable = $spMap->isShipmentEditable($params['shipment_id'],$params['participant_id']);
        $lastDate = new Zend_Date($shipment['lastdate_response'], Zend_Date::ISO_8601);
        $responseAccess = $date->compare($lastDate,Zend_Date::DATES);
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, APPLICATION_ENV);

        if($params['scheme_type'] == 'dts'){
            $dts = array();$testThreeOptional = false;
            if(isset($config->evaluation->dts->dtsOptionalTest3) && $config->evaluation->dts->dtsOptionalTest3 == 'yes'){
                $testThreeOptional = true;
            }
            $reportAccess = array();
            if($isEditable && $dm['view_only_access'] != 'yes'){
                if ($responseAccess == 1 && $shipment['status'] == 'finalized') {
                    $reportAccess['status'] = 'Fail';
                    $reportAccess['message'] = 'Your response is late and this shipment has been finalized. Your result will not be evaluated';
                } else if($responseAccess == 1){
                    $reportAccess['status'] = 'Fail';
                    $reportAccess['message'] = 'Your response is late and this shipment has been finalized. Your result will not be evaluated';
                } else if($shipment['status'] == 'finalized'){
                    $reportAccess['status'] = 'Fail';
                    $reportAccess['message'] = 'This shipment has been finalized. Your result will not be evaluated. Please contact your PT Provider for any clarifications';
                } else{
                    $reportAccess['status'] = 'success';
                }
            }else{
                $reportAccess['status'] = 'Fail';
                $reportAccess['message'] = 'Responding for this shipment is not allowed at this time. Please contact your PT Provider for any clarifications';
            }
            $dts['access'] = $reportAccess;
            // Check the data manager having for access to the form
            $access = $participantDb->checkParticipantAccess($params['participant_id'],$params['dm_id'],'API');
            if($access == false){
                return 'Participant does not having the shipments';
            }
            
            // Heading 1 start // First participant details start
            if(isset($participant) && count($participant) > 0){
                $dts['Heading1']['status'] = true;
                $dts['Heading1']['data']= array(
                    'participantName'   => $participant['first_name'].' '.$participant['last_name'],
                    'participantCode'   => $participant['unique_identifier'],
                    'affiliation'       => $participant['affiliation'],
                    'phone'             => $participant['phone'],
                    'mobile'            => $participant['mobile']
                );
            }else{
                $dts['Heading1']['status'] = false;
            }
            // First participant details end // Heading 1 end // Heading 2 start // Shipement Result start
            $modeOfReceiptSelect = array();
            foreach ($modeOfReceipt as $receipt){
                $modeOfReceiptSelect[]= array(
                    'value'   =>  $receipt['mode_id'],
                    'show' =>  $receipt['mode_name'],
                    'selected' => ($shipment["mode_id"] == $receipt['mode_id'])?'selected':''
                );
            }
            // Shipement Result end // For algorithmUsed start
            $algorithmUsedSelect = array();
            if(!empty($config->evaluation->dts->dtsEnforceAlgorithmCheck) && $config->dtsEnforceAlgorithmCheck == 'yes') {
                $algorithmUsedSelectOptions = array('not-reported','serial','parallel');
            }else{
                $algorithmUsedSelectOptions = array('serial','parallel');
            }
            foreach($algorithmUsedSelectOptions as $row){
                $algorithmUsedSelect[] = array('value' => $row,'show' => ucwords($row),'selected'=>(isset($shipment['attributes']["algorithm"]) && ($shipment['attributes']["algorithm"] == $row)?'selected':''));
            }
            
            if(isset($participant) && count($participant) > 0){
                $dts['Heading2']['status'] = true;
                // For algorithmUsed end
                $heading2 = array(
                    'shipmentDate'              => $general->humanDateFormat($shipment['shipment_date']),
                    'resultDueDate'             => date('d-M-Y',strtotime($shipment['lastdate_response'])),
                    'shipmentReceiptDate'       => date('d-M-Y',strtotime($shipment['shipment_receipt_date'])),
                    'sampleRehydrationDate'     => $general->humanDateFormat($shipment['attributes']["sample_rehydration_date"]),
                    'testingDate'               => $general->humanDateFormat($shipment['shipment_test_date']),
                    'algorithmUsedSelect'       => $algorithmUsedSelect,
                );
                $dts['Heading2']['data'] = $heading2;
                if((isset($dm['enable_adding_test_response_date']) && $dm['enable_adding_test_response_date'] == 'yes') || (isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes')){
                    if(isset($dm['enable_adding_test_response_date']) && $dm['enable_adding_test_response_date'] == 'yes'){
                        $dts['Heading2']['data']['responseDate'] = date('d-M-Y',strtotime($shipment['shipment_test_report_date']));
                    }
                    if(isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes'){
                        $dts['Heading2']['data']['modeOfReceiptSelect'] = $modeOfReceiptSelect;
                    }
                }
                $qcArray = array('yes','no');$qc = array();
                foreach($qcArray as $row){
                    if($globalQcAccess == 'yes' && isset($dm['qc_access']) && $dm['qc_access'] == 'yes'){
                        $qcResponseArr[] = array('value' =>$row,'show' =>ucwords($row),'selected'=>($shipment['qc_done'] == "yes")?'selected':'');
                    }else{
                        $qcResponseArr[] = array('value' =>'','show' =>'','selected'=>'');
                    }
                }
                $qc['qcRadio'] = $qcResponseArr;
                $qc['qcDate']   = (isset($shipment['qc_date'])&&$shipment['qc_date']!='')?$general->humanDateFormat($shipment['qc_date']):'';
                $qc['qcDoneBy'] = (isset($shipment['qc_done_by'])&&$shipment['qc_done_by']!='')?$shipment['qc_done_by']:'';
                if($globalQcAccess != 'yes' || $dm['qc_access'] != 'yes'){
                    $qc['status'] = false;
                    $dts['Heading2']['data']['qcData'] = $qc;
                }else{
                    $qc['status'] = true;
                    $dts['Heading2']['data']['qcData'] = $qc;
                }
            }else{
                $dts['Heading2']['status'] = false;
            }
            // Heading 2 end // Heading 3 start
            $testKitArray = array();$testKitKey = 0;
            $allTestKits = $schemeService->getAllDtsTestKitList(true);
            foreach ($allTestKits as $testKitKey => $testkit) {
                if($testkit['testkit_1'] == '1'){
                    $testKitArray['kitName']['Test-1']['status'] = true;
                    $testKitArray['kitName']['Test-1']['data'][] = array(
                        'value' => $testkit['TESTKITNAMEID'],
                        'show'  => $testkit['TESTKITNAME'],
                        'selected' => (isset($allSamples[0]["test_kit_name_1"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["test_kit_name_1"])?'selected':''
                    );
                }
                if($testkit['testkit_2'] == '1'){
                    $testKitArray['kitName']['Test-2']['status'] = true;
                    $testKitArray['kitName']['Test-2']['data'][] = array(
                        'value' => $testkit['TESTKITNAMEID'],
                        'show'  => $testkit['TESTKITNAME'],
                        'selected' => (isset($allSamples[0]["test_kit_name_2"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["test_kit_name_2"])?'selected':''
                    );
                }
                if(!$testThreeOptional && $testkit['testkit_3'] == '1'){
                    $testKitArray['kitName']['Test-3']['status'] = true;
                    $testKitArray['kitName']['Test-3']['data'][] = array(
                        'value' => $testkit['TESTKITNAMEID'],
                        'show'  => $testkit['TESTKITNAME'],
                        'selected' => (isset($allSamples[0]["test_kit_name_3"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["test_kit_name_3"])?'selected':''
                    );
                }else{
                    $testKitArray['kitName']['Test-3']['status'] = false;
                }
            }
            $testKitArray['kitText'] = array('Test-1','Test-2','Test-3');
            if(isset($allSamples) && count($allSamples) > 0){
                $dts['Heading3']['status'] = true;
                $testKitArray['lotNo']['lot1']['status'] = true;
                $testKitArray['lotNo']['lot1']  = (isset($allSamples[0]["lot_no_1"]) && trim($allSamples[0]["lot_no_1"]) != "")?$allSamples[0]["lot_no_1"]:'';
                $testKitArray['lotNo']['lot2']['status'] = true;
                $testKitArray['lotNo']['lot2']  = (isset($allSamples[0]["lot_no_2"]) && trim($allSamples[0]["lot_no_2"]) != "")?$allSamples[0]["lot_no_2"]:'';
                if(!$testThreeOptional){
                    $testKitArray['lotNo']['lot3']['status'] = true;
                    $testKitArray['lotNo']['lot3']  = (isset($allSamples[0]["lot_no_3"]) && trim($allSamples[0]["lot_no_3"]) != "")?$allSamples[0]["lot_no_3"]:'';
                }else{
                    $testKitArray['lotNo']['lot3']['status'] = false;
                }
                $testKitArray['expDate']['exp1']['status'] = true;
                $testKitArray['expDate']['exp1'] = (isset($allSamples[0]["exp_date_1"]) && trim($allSamples[0]["exp_date_1"]) != "")?$general->humanDateFormat($allSamples[0]["exp_date_1"]):'';
                $testKitArray['expDate']['exp2']['status'] = true;
                $testKitArray['expDate']['exp2'] = (isset($allSamples[0]["exp_date_2"]) && trim($allSamples[0]["exp_date_2"]) != "")?$general->humanDateFormat($allSamples[0]["exp_date_2"]):'';
                if(!$testThreeOptional){
                    $testKitArray['expDate']['exp3']['status'] = true;
                    $testKitArray['expDate']['exp3'] = (isset($allSamples[0]["exp_date_3"]) && trim($allSamples[0]["exp_date_2"]) != "")?$general->humanDateFormat($allSamples[0]["exp_date_3"]):'';
                }else{
                    $testKitArray['lotNo']['lot3']['status'] = false;
                }
                $dts['Heading3']['data'] = $testKitArray;
            }else{
                $dts['Heading3']['status'] = false;
            }
            // Heading 3 end // Heading 4 Start
            $dtsPossibleResults = $schemeService->getPossibleResults('dts');
            $allSamplesResult = array();
            foreach ($allSamples as $sample) {
                $possibleFinalResults = array();
                foreach ($dtsPossibleResults as $pr) {
                    if ($pr['scheme_sub_group'] == 'DTS_FINAL') {
                        $possibleFinalResults[] = array('value'=>$pr['id'],'show'=>$pr['response'],'selected'=>($sample['reported_result'] == $pr['id'])?'selected':'');
                    }
                }
                $allSamplesResult['samples'][] = $sample['sample_label'];
                foreach(range(1,3) as $row){
                    $possibleResults = array();
                    if($row == 3){
                        foreach ($dtsPossibleResults as $pr) {
                            if ($pr['scheme_sub_group'] == 'DTS_TEST') {
                                $possibleResults[] = array('value'=>$pr['id'],'show'=>$pr['response'],'selected'=>($sample['test_result_3'] == $pr['id'])?'selected':'');
                            }
                        }
                        if(!$testThreeOptional){
                            $allSamplesResult[$sample['sample_label']]['Result-'.$row]['status'] = true;
                            $allSamplesResult[$sample['sample_label']]['Result-'.$row]['data'] = $possibleResults;
                        }else{
                            
                            $allSamplesResult[$sample['sample_label']]['Result-'.$row]['data'] = $possibleResults;
                            $allSamplesResult[$sample['sample_label']]['Result-'.$row]['status'] = false;
                        }
                    }else{
                        foreach ($dtsPossibleResults as $pr) {
                            if ($pr['scheme_sub_group'] == 'DTS_TEST') {
                                $possibleResults[] = array('value'=>$pr['id'],'show'=>$pr['response'],'selected'=>(($sample['test_result_1'] == $pr['id'] && $row == 1) || ($sample['test_result_2'] == $pr['id'] && $row == 2))?'selected':'');
                            }
                        }
                        $allSamplesResult[$sample['sample_label']]['Result-'.$row]['status'] = true;
                        $allSamplesResult[$sample['sample_label']]['Result-'.$row]['data'] = $possibleResults;
                    }
                }
                $allSamplesResult['resultsText'] = array('Result-1','Result-2','Result-3','Final-Result');
                $allSamplesResult[$sample['sample_label']]['Final-Result'] = $possibleFinalResults;
            }
            if((isset($allSamples) && count($allSamples) > 0) && (isset($dtsPossibleResults) && count($dtsPossibleResults) > 0)){
                $dts['Heading4']['status'] = true;
                $dts['Heading4']['data'] = $allSamplesResult;
            }else{
                $dts['Heading4']['status'] = false;
                $dts['Heading4']['data'] = $allSamplesResult;
            }
            // Heading 4 End // Heading 5 Start
            $reviewArray = array();$commentArray = array('yes','no');$revieArr = array();
            foreach($commentArray as $row){
                $revieArr[] = array('value' =>$row,'show' =>ucwords($row),'selected'=>($shipment['supervisor_approval'] == $row)?'selected':'');
            }
            $reviewArray['supervisorReview'] = $revieArr;
            $reviewArray['approvalLabel'] = (isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] == 'yes')?'Supervisor Name':'';
            $reviewArray['approvalInputText'] = (isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] == 'yes')?$shipment['participant_supervisor']:'';
            $reviewArray['comments'] = (isset($shipment['user_comment']) && $shipment['user_comment'] != '')?$shipment['user_comment']:'';
            $dts['Heading5']['status'] = true;
            $dts['Heading5']['data'] = $reviewArray;
            // Heading 5 End
            return $dts;
        }else if($params['scheme_type'] == 'vl'){
            $reportAccess = array();$vl = array();
            if($isEditable && $dm['view_only_access'] != 'yes'){
                if ($responseAccess == 1 && $shipment['status'] == 'finalized') {
                    $reportAccess['status'] = 'Fail';
                    $reportAccess['message'] = 'Your response is late and this shipment has been finalized. Your result will not be evaluated';
                } else if($responseAccess == 1){
                    $reportAccess['status'] = 'Fail';
                    $reportAccess['message'] = 'Your response is late and this shipment has been finalized. Your result will not be evaluated';
                } else if($shipment['status'] == 'finalized'){
                    $reportAccess['status'] = 'Fail';
                    $reportAccess['message'] = 'This shipment has been finalized. Your result will not be evaluated. Please contact your PT Provider for any clarifications';
                } else{
                    $reportAccess['status'] = 'success';
                }
            }else{
                $reportAccess['status'] = 'Fail';
                $reportAccess['message'] = 'Responding for this shipment is not allowed at this time. Please contact your PT Provider for any clarifications';
            }
            $vl['access'] = $reportAccess;
            // Heading 1 start
            $heading1= array(
                'participantName'   => ((isset($participant['first_name']) && $participant['first_name'] != '')?$participant['first_name']:'').((isset($participant['last_name']) && $participant['last_name'] != '')?' '.$participant['last_name']:''),
                'participantCode'   => (isset($participant['unique_identifier']) && $participant['unique_identifier'] != '')?$participant['unique_identifier']:'',
                'affiliation'       => (isset($participant['affiliation']) && $participant['affiliation'] != '')?$participant['affiliation']:'',
                'phone'             => (isset($participant['phone']) && $participant['phone'] != '')?$participant['phone']:'',
                'mobile'            => (isset($participant['mobile']) && $participant['mobile'] != '')?$participant['mobile']:''
            );
            if(isset($participant) && count($participant) > 0){
                $vl['Heading1']['status'] = true;
                $vl['Heading1']['data'] = $heading1;
            }else{
                $vl['Heading1']['status'] = false;
                $vl['Heading1']['data'] = $heading1;
            }
            // Heading1 end // Heading2 start
            $heading2 = array();$vlAssayArr = array();
            $vlAssay = $schemeService->getVlAssay();
            if(isset($shipment) && count($shipment) > 0){
                foreach($vlAssay as $id=>$name){
                    $vlAssayArr[] = array(
                        'value' => $id,
                        'show'  => $name,
                        'selected' => ($shipment['attributes']['vl_assay'] == $id)?'selected':''
                    );
                }
                $modeOfReceiptSelect = array();
                foreach ($modeOfReceipt as $receipt){
                    $modeOfReceiptSelect[]= array(
                        'value'     =>  $receipt['mode_id'],
                        'show'      =>  $receipt['mode_name'],
                        'selected'   => ($shipment["mode_id"] == $receipt['mode_id'])?'selected':''
                    );
                }
                $heading2['status']    = true;
                $heading2['data']['shipmentDate'] = $general->humanDateFormat($shipment['shipment_date']);
                $heading2['data']['resultDueDate'] = date('d-M-Y',strtotime($shipment['lastdate_response']));
                $heading2['data']['receiptDate'] = date('d-M-Y',strtotime($shipment['shipment_test_report_date']));
                $heading2['data']['sampleRehydrationDate'] = $general->humanDateFormat($shipment['attributes']["sample_rehydration_date"]);
                $heading2['data']['testDate'] = $general->humanDateFormat($shipment["shipment_test_date"]);
                $heading2['data']['specimenVolume'] = $shipment['attributes']['specimen_volume'];
                $heading2['data']['vlAssaySelect'] = $vlAssayArr;
                $heading2['data']['otherAssay'] = (isset($shipment['attributes']['other_assay']) && $shipment['attributes']['other_assay'] != '')?$shipment['attributes']['other_assay']:'';
                $heading2['data']['assayExpirationDate'] = $general->humanDateFormat($shipment['attributes']['assay_expiration_date']);
                $heading2['data']['assayLotNumber'] = $shipment['attributes']['assay_lot_number'];
                if((isset($dm['enable_adding_test_response_date']) && $dm['enable_adding_test_response_date'] == 'yes') || (isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes')){
                    if(isset($dm['enable_adding_test_response_date']) && $dm['enable_adding_test_response_date'] == 'yes'){
                        $heading2['data']['responseDate'] = (isset($shipment['shipment_test_report_date']) && $shipment['shipment_test_report_date'] != '')?date('d-M-Y',strtotime($shipment['shipment_test_report_date'])):date('d-M-Y');
                    }
                    if(isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes'){
                        $heading2['data']['modeOfReceiptSelect'] = $modeOfReceiptSelect;
                    }
                }
            }

            $qcArray = array('yes','no');$qc = array();
            foreach($qcArray as $row){
                if($globalQcAccess == 'yes' && isset($dm['qc_access']) && $dm['qc_access'] == 'yes' && $dm['qc_access'] != null && $dm['qc_access'] != ""){
                    $qcResponseArr[] = array('value' =>$row,'show' =>ucwords($row),'selected'=>($shipment['qc_done'] == $row)?'selected':'');
                }else{
                    $qcResponseArr[] = array('value' =>'','show' =>'','selected'=>'');
                }
            }
            $qc['qcRadio'] = $qcResponseArr;
            $qc['qcDate']   = (isset($shipment['qc_date'])&&$shipment['qc_date']!='')?$general->humanDateFormat($shipment['qc_date']):'';
            $qc['qcDoneBy'] = (isset($shipment['qc_done_by'])&&$shipment['qc_done_by']!='')?$shipment['qc_done_by']:'';
            if($globalQcAccess != 'yes' && $dm['qc_access'] != 'yes'){
                $qc['status'] = false;
            }else{
                $qc['status'] = true;
            }
            $heading2['data']['qcData'] = $qc;

            $vl['Heading2'] = $heading2;
            // Heading 2 end // Heading 3 start
            $heading3 = array();
            $heading3['status'] = true;
            if((!isset($shipment['is_pt_test_not_performed']) || isset($shipment['is_pt_test_not_performed'])) && ($shipment['is_pt_test_not_performed'] == 'no' || $shipment['is_pt_test_not_performed'] == '')){
                $heading3['data']['isPtTestNotPerformedRadio'] = 'no';
                $heading3['data']['note'][] = htmlentities("Viral Load must be entered in log<sub>10</sub> copies/ml. There's a conversion calculator (from cp/mL to log) below. Please use if needed.");
                $heading3['data']['note'][] = htmlentities("Please provide numerical results (such as: 0.00 to 7.00 log<sub>10</sub> copies/ml).");
                $heading3['data']['note'][] = htmlentities("For negative or undetectable result (TND), please enter 0.00.");
                $heading3['data']['note'][] = htmlentities("For result value that is &lt;LOD, please enter the value of assay LOD (such as 1.6 for &lt;40 copies/mL) and provide “&lt;40 copies/mL” under comment section.");
                $heading3['data']['vlResultSectionLabel'] = htmlentities("Viral Load Calculator (Convert copies/ml to Log<sub>10</sub>)");
                $heading3['data']['tableHeading'][] = 'Control/Sample';
                $heading3['data']['tableHeading'][] = htmlentities('Viral Load (log<sub>10</sub> copies/ml)');
                $heading3['data']['tableHeading'][] = htmlentities('TND(Target Not Detected)');
                // return $allSamples;
                foreach ($allSamples as $key=>$sample) {
                    if (isset($shipment['is_pt_test_not_performed']) && $shipment['is_pt_test_not_performed'] == 'yes') {
                        $sample['mandatory'] = 0;
                    }
                    $vlArray = array('yes','no');
                    $vlResult = $sample['reported_viral_load'];
                    if ($sample['is_tnd'] == 'yes') {
                        $vlResult = 0.00;
                    }
                    $vlResponseArr = array();
                    foreach($vlArray as $row){
                        $vlResponseArr[] = array('value' =>$row,'show' =>ucwords($row),'selected'=>($sample['is_tnd'] == $row || ($sample['is_tnd'] == '' && $row == 'no'))?'selected':'');
                    }
                    $heading3['data']['tableRowTxt']['label'][] = $sample['sample_label'];
                    $heading3['data']['tableRowTxt']['mandatory'][] = (isset($sample['mandatory']) && $sample['mandatory'] == 1)?true:false;
                    $heading3['data']['vlResult'][] = $vlResult;
                    $heading3['data']['tndReferenceRadio'][] = $vlResponseArr;
                }
            }else{
                $allNotTestedReason = $schemeService->getVlNotTestedReasons();
                $heading3['data']['isPtTestNotPerformedRadio'] = 'yes';
                $allNotTestedArray = array();
                foreach ($allNotTestedReason as $reason) {
                    $allNotTestedArray[] = array(
                        'value'     => $reason['vl_not_tested_reason_id'],
                        'show'      => ucwords($reason['vl_not_tested_reason']),
                        'selected'  => ($shipment['vl_not_tested_reason'] == $reason['vl_not_tested_reason_id'])?'selected':''
                    );
                }
                $heading3['data']['vlNotTestedReasonText'] = 'Reason for not testing the PT Panel';
                $heading3['data']['vlNotTestedReasonSelect'] = $allNotTestedArray;
                $heading3['data']['commentsText'] = 'Your comments';
                $heading3['data']['commentsTextArea'] = $shipment['pt_test_not_performed_comments'];
                $heading3['data']['supportText'] = 'Do you need any support from the PT Provider ?';
                $heading3['data']['supportTextArea'] = $shipment['pt_support_comments'];
            }
            $vl['Heading3'] = $heading3;
            // Heading 3 end // Heading 4 Start
            $reviewArray = array();$commentArray = array('yes','no');$revieArr = array();
            foreach($commentArray as $row){
                $revieArr[] = array('value' =>$row,'show' =>ucwords($row),'selected'=>(isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] == $row)?'selected':'');
            }
            $reviewArray['supervisorReview'] = $revieArr;
            $reviewArray['approvalLabel'] = (isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] == 'yes')?'Supervisor Name':'';
            $reviewArray['approvalInputText'] = (isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] == 'yes')?$shipment['participant_supervisor']:'';
            $reviewArray['comments'] = (isset($shipment['user_comment']) && $shipment['user_comment'] != '')?$shipment['user_comment']:'';
            $vl['Heading4']['status'] = true;
            $vl['Heading4']['data'] = $reviewArray;
            // Heading 4 End
            return $vl;
        }
    }

    public function fetchIndividualReportAPI($params)
    {
        /* Check the app versions & parameters */
        if (!isset($params['appVersion'])) {
            return array('status' =>'fail','message'=>'There is an error in APP version. Please update to latest version APP.');
        }
        if (!isset($params['authToken'])) {
            return array('status' =>'fail','message'=>'Authentication error.');
        }
        
        /* Validate new auth token and app-version */
        $dmDb = new Application_Model_DbTable_DataManagers();
        $aResult = $dmDb->fetchAuthToken($params);
        if ($aResult == 'app-version-failed') {
            return array('status' =>'fail','message'=>'Please update to latest version APP.');
        }
        if(!$aResult){
            return array('status' =>'fail','message'=>'Participant authentication error.');
        }

        /* Get individual reports using data manager */
        $resultData = array();
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('SHIP_YEAR' => 'year(s.shipment_date)', 's.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.map_id', "spm.evaluation_status", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", "RESPONSE" => new Zend_Db_Expr("CASE substr(spm.evaluation_status,3,1) WHEN 1 THEN 'View' WHEN '9' THEN 'Enter Result' END"), "REPORT" => new Zend_Db_Expr("CASE  WHEN spm.report_generated='yes' AND s.status='finalized' THEN 'Report' END")))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name'))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
            ->where("pmm.dm_id=?", $aResult['dm_id'])
            ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'");
        $resultData = $this->getAdapter()->fetchAll($sQuery);
        if (!isset($resultData) && count($resultData) == 0) {
            return array('status' =>'fail','message'=>'Report not ready.');
        }
        /* Started the API service for individual report */
        $data = array();$general = new Pt_Commons_General();
        foreach ($resultData as $aRow) {
            $data[] = array(
                'schemeType'        => strtoupper($aRow['scheme_type']),
                'shipmentCode'      => $aRow['shipment_code'],
                'shipmentDate'      => $general->humanDateFormat($aRow['shipment_date']),
                'uniqueIdentifier'  => $aRow['unique_identifier'],
                'name'              => $aRow['first_name'] . " " . $aRow['last_name'],
                'responseDate'      => $general->humanDateFormat($aRow['RESPONSEDATE']),
                'downloadLink'      => '/participant/download/d92nl9d8d/' . base64_encode($aRow['map_id']) . ''
            );
        }
        if (isset($data) && count($data) > 0) {
            return array('status'=>'success','data'=>$data);
        } else {
            return array('status'=>'fail','message'=>'Report not ready');
        }
    }

    public function fetchSummaryReportAPI($params)
    {
        /* Check the app versions & parameters */
        if (!isset($params['appVersion'])) {
            return array('status' =>'fail','message'=>'There is an error in APP version. Please update to latest version APP.');
        }
        if (!isset($params['authToken'])) {
            return array('status' =>'fail','message'=>'Authentication error.');
        }
        
        /* Validate new auth token and app-version */
        $dmDb = new Application_Model_DbTable_DataManagers();
        $aResult = $dmDb->fetchAuthToken($params);
        if ($aResult == 'app-version-failed') {
            return array('status' =>'fail','message'=>'Please update to latest version APP.');
        }
        if(!$aResult){
            return array('status' =>'fail','message'=>'Participant authentication error.');
        }
        /* Get summary reports using data manager */
        $resultData = array();
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 's.shipment_date', 's.shipment_code', 's.status'))
        ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.map_id'))
        ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array())
        ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
        ->where("pmm.dm_id=?", $aResult['dm_id'])
        ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'");
        $resultData = $this->getAdapter()->fetchAll($sQuery);
        if (!isset($resultData) && count($resultData) == 0) {
            return array('status' =>'fail','message'=>'Report not ready.');
        }
        /* Started the API service for summary report */
        $general = new Pt_Commons_General();
        $data = array();
        foreach ($resultData as $aRow) {
            $data[] = array(
                'schemeType'    => strtoupper($aRow['scheme_type']),
                'shipmentCode'  => $aRow['shipment_code'],
                'shipmentDate'  => $general->humanDateFormat($aRow['shipment_date']),
                'downloadLink'  => '/participant/download/d92nl9d8d/' . base64_encode($aRow['map_id']) . ''
            );
        }
        if (isset($data) && count($data) > 0) {
            return array('status'=>'success','data'=>$data);
        } else {
            return array('status'=>'fail','message'=>'Report not ready');
        }
    }
}
