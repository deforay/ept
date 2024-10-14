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
        $sql = $this->getAdapter()->select()->from(array('s' => $this->_name), array('*', 'panelName' => new Zend_Db_Expr('shipment_attributes->>"$.panelName"')))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_name', 'is_user_configured', 'user_test_config'))
            ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id', array('*', "RESPONSEDATE" => "DATE_FORMAT(sp.shipment_test_report_date,'%Y-%m-%d')"))
            ->joinLeft(array('p' => 'participant'), 'sp.participant_id=p.participant_id', array('participant_id', 'unique_identifier', 'institute_name', 'anc'))
            ->joinLeft(array('c' => 'countries'), 'p.country=c.id', array('c.iso_name'))
            ->joinLeft(array('dm' => 'data_manager'), 'dm.dm_id=sp.updated_by_user', array('last_updated_by' => new Zend_Db_Expr("CONCAT(COALESCE(dm.first_name,''),' ', COALESCE(dm.last_name,''))")))
            // ->joinLeft(array('r_vl_r' => 'r_response_vl_not_tested_reason'), 'r_vl_r.vl_not_tested_reason_id=sp.vl_not_tested_reason', array('vlNotTestedReason' => 'vl_not_tested_reason'))
            ->joinLeft(array('ntr' => 'r_response_not_tested_reasons'), 'ntr.ntr_id=sp.vl_not_tested_reason', array('notTestedReason' => 'ntr_reason'))
            ->where("s.shipment_id = ?", $sId)
            ->where("sp.participant_id = ?", $pId);
        return $this->getAdapter()->fetchRow($sql);
    }

    public function getShipmentRowInfo($sId)
    {
        $result = $this->getAdapter()->fetchRow($this->getAdapter()->select()->from(array('s' => 'shipment'))
            ->joinLeft(array('d' => 'distributions'), 'd.distribution_id = s.distribution_id', array('distribution_code', 'distribution_date'))
            ->joinLeft(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id', array('map_id', 'number_of_tests'))
            ->joinLeft(array('p' => 'participant'), 'sp.participant_id=p.participant_id', array('participant_id', 'unique_identifier', 'institute_name', 'anc'))
            ->joinLeft(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('sl.scheme_name'))
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
            } elseif ($result['scheme_type'] == 'recency') {
                $tableName = "reference_result_recency";
            } elseif ($result['scheme_type'] == 'covid19') {
                $tableName = "reference_result_covid19";
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
        $commonServices = new Application_Service_Common();
        $shipmentRow =  $this->fetchRow("distribution_id = " . $distributionId);
        /* New shipment mail alert start */
        $notParticipatedMailContent = $commonServices->getEmailTemplate('new_shipment');
        $subQuery = $this->select()
            ->from(['s' => 'shipment'], ['shipment_code', 'scheme_type'])
            ->join(['spm' => 'shipment_participant_map'], 'spm.shipment_id=s.shipment_id', ['map_id', 'participant_id'])
            ->join(['pmm' => 'participant_manager_map'], 'pmm.participant_id=spm.participant_id', ['dm_id'])
            ->join(['p' => 'participant'], 'p.participant_id=pmm.participant_id', ['participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")])
            ->join(['dm' => 'data_manager'], 'pmm.dm_id=dm.dm_id', ['primary_email'])
            ->where("s.shipment_id=?", $shipmentRow['shipment_id'])
            ->group('dm.dm_id')->setIntegrityCheck(false);
        // echo $subQuery;die;
        $subResult = $this->fetchAll($subQuery);
        foreach ($subResult as $dm) {
            $search = array('##NAME##', '##SHIPCODE##', '##SHIPTYPE##', '##SURVEYCODE##', '##SURVEYDATE##',);
            $replace = array($dm['participantName'], $dm['shipment_code'], $dm['scheme_type'], '', '');
            if (isset($notParticipatedMailContent['mail_content']) && !empty($notParticipatedMailContent['mail_content'])) {
                $content = $notParticipatedMailContent['mail_content'];
                $message = str_replace($search, $replace, $content);
                // $subject = $notParticipatedMailContent['mail_subject'];
                $subject = str_replace($search, $replace, $notParticipatedMailContent['mail_subject']);
                $fromEmail = $notParticipatedMailContent['mail_from'];
                $fromFullName = $notParticipatedMailContent['from_name'];
                $toEmail = $dm['primary_email'];
                $cc = $notParticipatedMailContent['mail_cc'];
                $bcc = $notParticipatedMailContent['mail_bcc'];
                $commonServices->insertTempMail($toEmail, $cc, $bcc, $subject, $message, $fromEmail, $fromFullName);
            }
        }
        /* New shipment mail alert end */
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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS s.scheme_type'), 'SHIP_YEAR' => 'year(s.shipment_date)', 'TOTALSHIPMEN' => new Zend_Db_Expr("COUNT('s.shipment_id')")))
            ->joinLeft(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id', array('ONTIME' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,3,1) WHEN 1 THEN 1 END)"), 'NORESPONSE' => new Zend_Db_Expr("COUNT(CASE substr(sp.evaluation_status,2,1) WHEN 9 THEN 1 END)"), 'reported_count' => new Zend_Db_Expr("SUM(response_status is not null AND response_status like 'responded')")))
            ->joinLeft(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type')
            ->where("s.status='shipped' OR s.status='evaluated' OR s.status='finalized'")
            ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
            ->group('s.scheme_type')
            ->group('SHIP_YEAR');
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.participant_id'));
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
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

        $aColumns = array('DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'scheme_name', 'shipment_code', 'distribution_code', 'unique_identifier', new Zend_Db_Expr("CONCAT(COALESCE(p.first_name,''),' ', COALESCE(p.last_name,''))"), 'p.institute_name', 'DATE_FORMAT(lastdate_response,"%d-%b-%Y")', 'DATE_FORMAT(spm.shipment_test_report_date,"%d-%b-%Y")');
        $orderColumns = array('shipment_date', 'scheme_name', 'shipment_code', 'distribution_code', 'unique_identifier', 'first_name', 'p.institute_name', 'lastdate_response', 'spm.shipment_test_report_date');

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

        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder[] = $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]);
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
        $sQuery = $this->getAdapter()->select()
            ->from(array('s' => 'shipment'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS s.scheme_type'), 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id', 's.status', 's.response_switch', 'allow_editing_response', 'panelName' => new Zend_Db_Expr('shipment_attributes->>"$.panelName"')))
            ->join(array('d' => 'distributions'), 'd.distribution_id = s.distribution_id', array('distribution_code', 'distribution_date'))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name', 'is_user_configured'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array("spm.map_id", "spm.evaluation_status", "spm.response_status", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')"))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.state', 'p.institute_name', 'p.country'))
            ->joinLeft(array('c' => 'countries'), 'p.country=c.id', array('c.iso_name'))
            ->where("s.status='shipped' OR s.status='evaluated'");

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }

        if (isset($parameters['currentType'])) {
            if ($parameters['currentType'] == 'active') {
                $sQuery = $sQuery->where("s.response_switch = 'on'");
            } elseif ($parameters['currentType'] == 'inactive') {
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

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        // error_log($sQuery);
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

        $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
        foreach ($rResult as $aRow) {
            $delete = '';
            $download = '';
            $isEditable = $shipmentParticipantDb->isShipmentEditable($aRow['shipment_id'], $aRow['participant_id']);
            $row = [];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['shipment_date']);
            $row[] = ($aRow['panelName'] ?? $aRow['scheme_name']);
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['distribution_code'];
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = $aRow['institute_name'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['lastdate_response']);
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['RESPONSEDATE']);

            $buttonText = "View/Edit";
            $buttonType = 'btn-primary';
            if ($aRow['response_status'] === 'draft') {
                $buttonText = "View/Edit Draft";
                $buttonType = 'btn-danger';
            }
            if (isset($aRow['is_user_configured']) && $aRow['is_user_configured'] == 'yes') {
                $aRow['scheme_type'] = 'generic-test';
            }

            $download = '';
            $delete = '';
            // $delete = '<br/><a href="javascript:void(0);" onclick="removeSchemes(\'' . $aRow['scheme_type'] . '\',\'' . base64_encode($aRow['map_id']) . '\', \'' . $aRow['is_user_configured'] . '\')" class="btn btn-danger" style="margin:3px 0;"> <i class="icon icon-remove-sign"></i> Delete Response</a>';
            if ($isEditable) {
                if ($aRow['RESPONSEDATE'] != '' && $aRow['RESPONSEDATE'] != '0000-00-00') {
                    if ($this->_session->view_only_access == 'no') {
                        $delete = '<br/><a href="javascript:void(0);" onclick="removeSchemes(\'' . $aRow['scheme_type'] . '\',\'' . base64_encode($aRow['map_id']) . '\', \'' . $aRow['is_user_configured'] . '\')" class="btn btn-danger" style="margin:3px 0;"> <i class="icon icon-remove-sign"></i> Delete Response</a>';
                    }
                } else {
                    $buttonType = 'btn-success';
                    $buttonText = "Enter Response";
                    if ($aRow['scheme_type'] == "tb") {
                        $downloadLink = base64_encode(TEMP_UPLOAD_PATH . '/' . $aRow['shipment_code'] . '/TB-FORM-' . $aRow['shipment_code'] . '-' . $aRow['unique_identifier'] . '.pdf');
                        $download = "<br/><a href='/participant/download-tb/sid/" . $aRow['shipment_id'] . "/pid/" . $aRow['participant_id'] . "/file/" . $downloadLink . "' class='btn btn-default' style='margin:3px 0;' target='_BLANK'> <i class='icon icon-download'></i> Download Form</a>";
                    } else {
                        $download = '<br/><a href="/' . $aRow['scheme_type'] . '/download/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" class="btn btn-default"  style="margin:3px 0;" target="_BLANK"> <i class="icon icon-download"></i> Download Form</a>';
                    }
                }
            }
            if (isset($aRow['allow_editing_response']) && !empty($aRow['allow_editing_response']) && $aRow['allow_editing_response'] == 'no' && ($aRow['RESPONSEDATE'] != '' && $aRow['RESPONSEDATE'] != '0000-00-00')) {
                // $row[] = "<a href='javascript:void(0);' class='btn btn-default' style='margin:3px 0;'><i class='icon icon-ban-circle'></i> View</a>$delete$download";
                $row[] = "<a href='/{$aRow['scheme_type']}/response/sid/{$aRow['shipment_id']}/pid/{$aRow['participant_id']}/eid/{$aRow['evaluation_status']}/uc/{$aRow['is_user_configured']}' class='btn btn-default' style='margin:3px 0;'><i class='icon icon-edit'></i> View </a>$delete$download";
            } else {
                $row[] = "<a href='/{$aRow['scheme_type']}/response/sid/{$aRow['shipment_id']}/pid/{$aRow['participant_id']}/eid/{$aRow['evaluation_status']}/uc/{$aRow['is_user_configured']}' class='btn $buttonType' style='margin:3px 0;'><i class='icon icon-edit'></i> $buttonText </a>$delete$download";
            }

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getDefaultedShipments($parameters)
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

        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder[] = $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]);
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
        $sQuery = $this->getAdapter()->select()
            ->from(array('s' => 'shipment'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS s.scheme_type'), 's.status', 'SHIP_YEAR' => 'year(s.shipment_date)', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id', 's.response_switch'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array("spm.map_id", "spm.evaluation_status", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", "ACTION" => new Zend_Db_Expr("CASE  WHEN substr(spm.evaluation_status,2,1)='1' THEN 'View' WHEN (substr(spm.evaluation_status,2,1)='9' AND s.lastdate_response>= CURDATE()) OR (s.status= 'finalized') THEN 'Enter Result' END"), "STATUS" => new Zend_Db_Expr("CASE substr(spm.evaluation_status,3,1) WHEN 1 THEN 'On Time' WHEN '2' THEN 'Late' WHEN '0' THEN 'No Response' END")))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name', 'is_user_configured'))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.participant_id', 'p.country'))
            ->joinLeft(array('c' => 'countries'), 'p.country=c.id', array('c.iso_name'))

            ->where("s.status='shipped' OR s.status='evaluated'")
            ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
            ->where("s.lastdate_response <  CURDATE()")
            ->where("substr(spm.evaluation_status,3,1) <> '1'")
            ->order('s.shipment_date')
            ->order('spm.participant_id');
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
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

        $general = new Pt_Commons_General();
        $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
        foreach ($rResult as $aRow) {
            $delete = '';
            $isEditable = $shipmentParticipantDb->isShipmentEditable($aRow['shipment_id'], $aRow['participant_id']);
            $row = [];
            if ($aRow['ACTION'] == "View") {
                $aRow['ACTION'] = "View";
                if ($aRow['response_switch'] == 'on' && $aRow['status'] != 'finalized') {
                    $aRow['ACTION'] = "Edit/View";
                }
            }

            $row[] = $aRow['SHIP_YEAR'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['shipment_date']);
            $row[] = ($aRow['scheme_name']);
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['lastdate_response']);
            $row[] = $aRow['STATUS'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['RESPONSEDATE']);

            $buttonText = "View/Edit";
            $download = '';
            $delete = '';
            if (isset($aRow['is_user_configured']) && $aRow['is_user_configured'] == 'yes') {
                $aRow['scheme_type'] = 'generic-test';
            }
            if ($isEditable) {
                if ($aRow['RESPONSEDATE'] != '' && $aRow['RESPONSEDATE'] != '0000-00-00') {
                    if ($this->_session->view_only_access == 'no') {
                        $delete = '<br/><a href="javascript:void(0);" onclick="removeSchemes(\'' . $aRow['scheme_type'] . '\',\'' . base64_encode($aRow['map_id']) . '\', \'' . $aRow['is_user_configured'] . '\')" class="btn btn-danger"  style="margin:3px 0;"> <i class="icon icon-remove-sign"></i> Delete Response</a>';
                    }
                } else {
                    $buttonText = "Enter Response";
                    if ($aRow['scheme_type'] == "tb") {
                        $downloadLink = TEMP_UPLOAD_PATH . '/' . $aRow['shipment_code'] . '/TB-FORM-' . $aRow['shipment_code'] . '-' . $aRow['unique_identifier'] . '.pdf';
                        // if(file_exists($downloadLink)){
                        $download = '<br/><a href="/participant/download-tb/file/' . base64_encode($downloadLink) . '" class="btn btn-default" style="margin:3px 0;" target="_BLANK"> <i class="icon icon-download"></i> Download Form</a>';
                        /* }else{
                            $download = '<br/><a href="/shipment-form/tb-download/sid/' . base64_encode($aRow['shipment_id']) . '/pid/' . base64_encode($aRow['participant_id']) . '"   class="btn btn-default"  style="margin:3px 0;" target="_BLANK"> <i class="icon icon-download"></i> Download Form</a>';
                        } */
                    } else {
                        $download = '<br/><a href="/' . $aRow['scheme_type'] . '/download/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" class="btn btn-default" style="margin:3px 0;" target="_BLANK" download> <i class="icon icon-download"></i> Download Form</a>';
                    }
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

        $sQuery = $this->getAdapter()->select()
            ->from(array('s' => 'shipment'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS s.scheme_type'), 'SHIP_YEAR' => 'year(s.shipment_date)', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id', 's.status', 's.response_switch'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.report_generated', 'spm.map_id', "spm.evaluation_status", "qc_date", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", "RESPONSE" => new Zend_Db_Expr("CASE  WHEN substr(spm.evaluation_status,3,1)='1' THEN 'View' WHEN (substr(spm.evaluation_status,3,1)='9' AND s.lastdate_response >= CURDATE()) OR (substr(spm.evaluation_status,3,1)='9' AND s.status= 'finalized') THEN 'Enter Result' END"), "REPORT" => new Zend_Db_Expr("CASE  WHEN spm.report_generated='yes' AND s.status='finalized' THEN 'Report' END")))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name', 'is_user_configured'))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.participant_id'))


            ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'")
            ->where("year(s.shipment_date)  + 5 > year(CURDATE())");
        //->order('s.shipment_date')
        //->order('spm.participant_id')
        // error_log($this->_session->dm_id);
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
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
        $output = [
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        ];
        $globalQcAccess = Application_Service_Common::getConfig('qc_access');
        $general = new Pt_Commons_General();
        $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
        foreach ($rResult as $aRow) {
            $delete = '';
            $download = '';
            $qcChkbox = '';
            $qcResponse = '';

            $isEditable = $shipmentParticipantDb->isShipmentEditable($aRow['shipment_id'], $aRow['participant_id']);
            $row = [];
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
                    $aRow['qc_date'] = Pt_Commons_General::humanReadableDateFormat($aRow['qc_date']);
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
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['shipment_date']);
            $row[] = ($aRow['scheme_name']);
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['RESPONSEDATE']);

            //            if($aRow['status']!='finalized' && $aRow['RESPONSEDATE']!='' && $aRow['RESPONSEDATE']!='0000-00-00'){
            //             $delete='<a href="javascript:void(0);" onclick="removeSchemes(\'' . $aRow['scheme_type']. '\',\'' . base64_encode($aRow['map_id']) . '\', \'' . $aRow['is_user_configured'] . '\')" style="text-decoration : underline;"> Delete</a>';
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
            if (isset($aRow['is_user_configured']) && $aRow['is_user_configured'] == 'yes') {
                $aRow['scheme_type'] = 'generic-test';
            }

            if ($isEditable) {
                if ($aRow['RESPONSEDATE'] != '' && $aRow['RESPONSEDATE'] != '0000-00-00') {
                    if ($this->_session->view_only_access == 'no') {
                        $delete = '<br/><a href="javascript:void(0);" onclick="removeSchemes(\'' . $aRow['scheme_type'] . '\',\'' . base64_encode($aRow['map_id']) . '\', \'' . $aRow['is_user_configured'] . '\')" class="btn btn-danger"  style="margin:3px 0;"> <i class="icon icon-remove-sign"></i> Delete Response</a>';
                    }
                } else {
                    $buttonText = "Enter Response";
                    $download = ''; //<br/><a href="/' . $aRow['scheme_type'] . '/download/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/eid/' . $aRow['evaluation_status'] . '" class="btn btn-default"  style="margin:3px 0;" target="_BLANK"> <i class="icon icon-download"></i> Download Form</a>';
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
                    $downloadReports .= '<a href="/d/' . base64_encode($summaryFilePath) . '" onclick="updateReportDownloadDateTime(' . $aRow['map_id'] . ', \'summary\');"  class="btn btn-primary" style="text-decoration : none;overflow:hidden;" target="_BLANK" download><i class="icon icon-download"></i> Summary Report</a>';
                }
                $invididualFilePath = (DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . "-" . $aRow['map_id'] . ".pdf");
                if (!file_exists($invididualFilePath)) {
                    // Search this file name using the map id
                    $files = glob(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . "*" . "-" . $aRow['map_id'] . ".pdf");
                    $invididualFilePath = isset($files[0]) ? $files[0] : '';
                }
                if (file_exists($invididualFilePath)) {
                    $downloadReports .= '<br><a href="/d/' . base64_encode($invididualFilePath) . '" class="btn btn-primary" onclick="updateReportDownloadDateTime(' . $aRow['map_id'] . ', \'individual\');"   style="text-decoration : none;overflow:hidden;margin-top:4px;"  target="_BLANK" download><i class="icon icon-download"></i> Individual Report</a>';
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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS s.scheme_type'), 'SHIP_YEAR' => 'year(s.shipment_date)', 's.shipment_date', 's.shipment_code', 's.shipment_id', 's.status'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.map_id', "spm.participant_id"))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.first_name', 'p.last_name'))
            // ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
            ->join(array('dm' => 'data_manager'), 'dm.dm_id=pmm.dm_id', array('dm.institute'))
            // ->where("pmm.dm_id=?", $this->_session->dm_id)
            ->where("s.status='shipped' OR s.status='evaluated' OR s.status='finalized'")
            ->where("year(s.shipment_date)  + 5 > year(CURDATE())")
            ->group('s.shipment_id');
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
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

        $general = new Pt_Commons_General();
        foreach ($rResult as $aRow) {
            $row = [];
            $report = "";
            $fileName = $aRow['shipment_code'] . "-" . $aRow['institute'] . $aRow['dm_id'] . ".pdf";
            $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $fileName);
            $fileName = str_replace(" ", "-", $fileName);

            $row[] = $aRow['SHIP_YEAR'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['shipment_date']);
            $row[] = strtoupper($aRow['scheme_name']);
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
        $orderColumns = array('scheme_type', 'shipment_code', 'shipment_date', 'unique_identifier', 'first_name', 'spm.shipment_test_report_date', 's.created_on_admin');

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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS s.scheme_type'), 'SHIP_YEAR' => 'year(s.shipment_date)', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id', 's.corrective_action_file', 'shipmentStatus' => 's.status', 'collect_feedback', 'feedback_expiry_date'))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_name'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.map_id', 'final_result', "spm.evaluation_status", "spm.participant_id", "shipment_score", "documentation_score", "is_excluded", "is_pt_test_not_performed", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", "RESPONSE" => new Zend_Db_Expr("CASE substr(spm.evaluation_status,3,1) WHEN 1 THEN 'View' WHEN '9' THEN 'Enter Result' END"), "response_status", "REPORT" => new Zend_Db_Expr("CASE  WHEN spm.report_generated='yes' AND s.status='finalized' THEN 'Report' END")))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name'))
            ->where("s.status='finalized'");

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = " . $authNameSpace->dm_id);
        }
        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", Pt_Commons_General::isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", Pt_Commons_General::isoDateFormat($parameters['endDate']));
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
        //echo($sQuery);die;
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
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $general = new Pt_Commons_General();
        $common = new Application_Service_Common();
        $feedbackOption = $common->getConfig('feed_back_option');
        foreach ($rResult as $aRow) {
            $download = _("Not Available");
            $corrective = "";
            $feedback = "";
            $row = [];

            $displayResult = " - ";
            if ($aRow['is_pt_test_not_performed'] == 'yes') {
                $displayResult = _('Participant unable to test');
            } elseif ($aRow['final_result'] == 1) {
                $displayResult = _('Satisfactory');
            } elseif ($aRow['final_result'] == 2) {
                $displayResult = _('Unsatisfactory');
            } elseif ($aRow['final_result'] == 3 || $aRow['is_excluded'] == 'yes') {
                $displayResult = _('Excluded from evaluation');
            }

            $row[] = strtoupper($aRow['scheme_name']);
            $row[] = $aRow['shipment_code'];
            $row[] = $general->humanReadableDateFormat($aRow['shipment_date']);
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = $general->humanReadableDateFormat($aRow['RESPONSEDATE']);
            $row[] = $displayResult;
            if ($aRow['is_excluded'] != 'yes' && isset($aRow['REPORT']) && $aRow['REPORT'] != "") {
                $invididualFilePath = (DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . "-" . $aRow['map_id'] . ".pdf");
                if (!file_exists($invididualFilePath)) {
                    // Search this file name using the map id
                    $files = glob(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . "*" . "-" . $aRow['map_id'] . ".pdf");
                    $invididualFilePath = isset($files[0]) ? $files[0] : '';
                }
                if (file_exists($invididualFilePath)) {
                    $download = '<a href="/d/' . base64_encode($invididualFilePath) . '" class="btn btn-primary" onclick="updateReportDownloadDateTime(' . $aRow['map_id'] . ', \'individual\');"   style="text-decoration : none;overflow:hidden;margin-top:4px;"  target="_BLANK" download><i class="icon icon-download"></i> ' . $aRow['REPORT'] . '</a>';
                }
            }
            if (($aRow['final_result'] == '2') && (isset($aRow['corrective_action_file']) && $aRow['corrective_action_file'] != "")) {
                $corrective = '<a href="/uploads/corrective-action-files/' . $aRow['corrective_action_file'] . '"   class="btn btn-warning"   style="text-decoration : none;overflow:hidden;margin-top:4px; clear:both !important;display:block;" target="_BLANK" download><i class="fa fa-fw fa-download"></i> Corrective Actions</a>';
            }
            if ($aRow['shipmentStatus'] == 'finalized' && $aRow['collect_feedback'] == 'yes' && $aRow['feedback_expiry_date'] >= date('Y-m-d') && $aRow['response_status'] == 'responded' && isset($feedbackOption) && !empty($feedbackOption) && $feedbackOption == 'yes') {
                $result = $db->fetchRow($db->select()->from(array('participant_feedback_answer'))->where("shipment_id =?", $aRow['shipment_id'])->where("participant_id =?", $aRow['participant_id'])->where("map_id =?", $aRow['map_id']));
                if ($result) {
                    $feedback = '<a href="/participant/feed-back/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/mid/' . $aRow['map_id'] . '"   class="btn btn-default" style="text-decoration : none;overflow:hidden;margin-top:4px; clear:both !important;display:block;"><i class="icon-comments"></i> Feedback</a>';
                } else {
                    $feedback = '<a href="/participant/feed-back/sid/' . $aRow['shipment_id'] . '/pid/' . $aRow['participant_id'] . '/mid/' . $aRow['map_id'] . '"   class="btn btn-default" style="text-decoration : none;overflow:hidden;margin-top:4px; clear:both !important;display:block;"><i class="icon-comments"></i> Feedback</a>';
                }
            }
            $row[] = $download . $corrective . $feedback;

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function fetchCorrectiveActionReport($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('shipment_code', 'DATE_FORMAT(shipment_date,"%d-%b-%Y")', 'scheme_type', 'unique_identifier', 'first_name', 'DATE_FORMAT(spm.shipment_test_report_date,"%d-%b-%Y")');
        $orderColumns = array('shipment_code', 'shipment_date', 'scheme_type', 'unique_identifier', 'first_name', 'spm.shipment_test_report_date', 's.created_on_admin');

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
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS s.scheme_type'), 'SHIP_YEAR' => 'year(s.shipment_date)', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id', 's.corrective_action_file'))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_name'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.map_id', 'final_result', "spm.evaluation_status", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", "RESPONSE" => new Zend_Db_Expr("CASE substr(spm.evaluation_status,3,1) WHEN 1 THEN 'View' WHEN '9' THEN 'Enter Result' END"), "REPORT" => new Zend_Db_Expr("CASE  WHEN spm.report_generated='yes' AND s.status='finalized' THEN 'Report' END")))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name'))
            // ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
            // ->where("pmm.dm_id=?", $this->_session->dm_id)
            ->where("s.status='finalized'")
            ->where("s.corrective_action_file NOT LIKE ''")
            ->where("spm.final_result = 2");

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
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

        $general = new Pt_Commons_General();
        foreach ($rResult as $aRow) {
            $corrective = "";
            $row = [];
            $row[] = $aRow['shipment_code'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['shipment_date']);
            $row[] = ($aRow['scheme_name']);
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['RESPONSEDATE']);
            if (isset($aRow['corrective_action_file']) && $aRow['corrective_action_file'] != "") {
                $corrective = '<a href="/uploads/corrective-action-files/' . $aRow['corrective_action_file'] . '" onclick="updateReportDownloadDateTime(' . $aRow['map_id'] . ', \'individual\');" style="text-decoration : underline;" target="_BLANK" download>Corrective Action</a>';
            }
            $row[] = $corrective;

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
        $sQuery = $this->getAdapter()->select()->distinct()->from(array('s' => 'shipment'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS s.shipment_id'), 's.scheme_type', 's.shipment_date', 's.shipment_code', 's.status'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array())
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_name'))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array())
            ->where("s.status like 'finalized'");
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", Pt_Commons_General::isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", Pt_Commons_General::isoDateFormat($parameters['endDate']));
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

        $general = new Pt_Commons_General();
        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = (!empty($aRow['scheme_name'])) ? ($aRow['scheme_name']) : null;
            $row[] = $aRow['shipment_code'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['shipment_date']);
            if (file_exists(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . "-summary.pdf") && $aRow['status'] == 'finalized') {
                $filePath = base64_encode(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . "-summary.pdf");
                $row[] = '<a href="/d/' . $filePath . '" onclick="updateReportDownloadDateTime(' . $aRow['map_id'] . ', \'summary\');"  style="text-decoration : none;" download target="_BLANK">Download Report</a>';
            } else {
                $row[] = _('Not Available');
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

        foreach ($parameters as $key => $value) {
            $parameters[$key] = trim($db->quote($value), "'");
        }

        $aColumns = array("sl.scheme_name", "shipment_code", 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", "DATE_FORMAT(lastdate_response,'%d-%b-%Y')");
        $orderColumns = array("sl.scheme_name", "shipment_code", 'distribution_code', 'distribution_date', 'lastdate_response');


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

        $sQuery = $db->select()->from(array('s' => 'shipment'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS *')))
            ->join(array('d' => 'distributions'), 'd.distribution_id = s.distribution_id', array('distribution_code', 'distribution_date'))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('SCHEME' => 'sl.scheme_name', 'scheme_id'))
            ->where('s.status NOT IN ("pending","finalized")')
            ->where('s.response_switch like "on"')
            ->group('s.shipment_id');

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

        $rResult = $db->fetchAll($sQuery);

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
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['SCHEME'];
            $row[] = $aRow['distribution_code'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['distribution_date']);
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['lastdate_response']);
            if ($aRow['scheme_id'] == "tb") {
                $row[] = '<a href="/shipment-form/tb-download/sid/' . base64_encode($aRow['shipment_id']) . '"  style="text-decoration : underline;" target="_blank" download> Download </a>';
            } else {
                $row[] = '<a href="/shipment-form/download/sid/' . base64_encode($aRow['shipment_id']) . '"  style="text-decoration : underline;" target="_blank" download> Download </a>';
            }
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
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = [];
        if ($adminSession->privileges != "") {
            $privileges = explode(',', $adminSession->privileges);
        }
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('d' => 'distributions'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS *')))
            ->joinLeft(array('s' => 'shipment'), 's.distribution_id=d.distribution_id', array('shipments' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT s.shipment_code SEPARATOR ', ')"), 'shipment_id'))
            ->where("s.status='finalized'")
            ->group('d.distribution_id');

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
        $rResult = $dbAdapter->fetchAll($sQuery);

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

        $shipmentDb = new Application_Model_DbTable_Shipments();
        foreach ($rResult as $aRow) {
            $replaceSummaryRportBtn = "";
            $shipmentResults = $shipmentDb->getPendingShipmentsByDistribution($aRow['distribution_id']);

            $row = [];
            $row['DT_RowId'] = "dist" . $aRow['distribution_id'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['distribution_date']);
            $row[] = $aRow['distribution_code'];
            $row[] = $aRow['shipments'];
            $row[] = ucwords($aRow['status']);
            $sendReportMail = '<a class="btn btn-warning btn-xs send-report-btn-' . ($aRow['shipment_id']) . '" href="javascript:void(0);" onclick="sendReportsInMail(\'' . ($aRow['shipment_id']) . '\')"><span><i class="icon-bullhorn"></i>&nbsp; Send Reports via Email</span></a>';
            $view = '<a class="btn btn-primary btn-xs" href="javascript:void(0);" onclick="getShipmentInReports(\'' . ($aRow['distribution_id']) . '\')" style=" margin-left: 10px; "><span><i class="icon-search"></i> View</span></a>';
            if (isset($privileges) && !empty($privileges) && in_array('replace-finalized-summary-report', $privileges)) {
                $replaceSummaryRportBtn = '<a class="btn btn-primary btn-xs" href="/reports/finalize/replace-summary-report/id/' . base64_encode($aRow['shipment_id']) . '" style=" margin-left: 10px; "><span><i class="icon-exchange"></i> Replace Summary Report</span></a>';
            }
            $row[] = $sendReportMail . $replaceSummaryRportBtn . $view;
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

        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder[] = $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]);
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

        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS s.scheme_type'), 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id', 's.status', 's.response_switch'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.report_generated', 'spm.map_id', "spm.evaluation_status", "qc_date", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", 'spm.shipment_score'))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.participant_id'))
            ->where("s.status=?", "finalized")
            ->where("s.scheme_type=?", $parameters['scheme']);
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
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
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['shipment_date']);
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['RESPONSEDATE']);
            $row[] = $aRow['shipment_score'];
            $output['aaData'][] = $row;
        }
        echo json_encode($output);
    }
    public function fetchUniqueShipmentCode()
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()
            ->select()->from(array('s' => $this->_name), array('shipment_code' => new Zend_Db_Expr(" DISTINCT s.shipment_code "), 'shipment_id', 'response_switch'))
            ->where("s.shipment_code IS NOT NULL")
            ->where("trim(s.shipment_code)!=''"));
    }

    public function fetchShipmentDetailsInAPI($params, $type)
    {
        /* Check the app versions & parameters */
        /* if (!isset($params['appVersion'])) {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        if (!isset($params['authToken'])) {
            return array('status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again');
        }
        $dmDb = new Application_Model_DbTable_DataManagers();
        $aResult = $dmDb->fetchAuthToken($params);
        /* App version check */
        /* if ($aResult == 'app-version-failed') {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        /* Validate new auth token and app-version */
        if (!$aResult) {
            return array('status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again');
        }

        /* To check the shipment details for the data managers mapped participants */
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id', 's.status', 's.response_switch', 's.updated_on_admin'))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array("spm.map_id", "spm.evaluation_status", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", 'created_on_admin', 'created_on_user', 'updated_on_user', 'is_excluded'))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.state'))
            ->where("(s.status='shipped' OR s.status='evaluated' OR s.status='finalized')")
            ->order('spm.created_on_admin DESC')
            ->order('spm.created_on_user DESC');
        // echo $sQuery;die;
        if (!empty($aResult['dm_id'])) {
            $sQuery = $sQuery->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $aResult['dm_id']);
        }
        $rResult = $this->getAdapter()->fetchAll($sQuery);
        if (empty($rResult)) {
            return array('status' => 'fail', 'message' => 'Shipment Details not available');
        }
        /* Start the API services */
        $token = $dmDb->fetchAuthTokenByToken($params);
        $data = [];
        $formData = [];
        $getParticipantDetails = [];
        $checkFormSatatus = false;
        foreach ($rResult as $key => $row) {
            $downloadInReports = '';
            $downloadSummaryReports = '';
            $summaryFilePath = '';
            $invididualFilePath = '';
            if ($row['status'] == 'finalized') {
                $invididualFilePath = (DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $row['shipment_code'] . DIRECTORY_SEPARATOR . $row['shipment_code'] . "-" . $row['map_id'] . ".pdf");
                if (!file_exists($invididualFilePath)) {
                    // Search this file name using the map id
                    $files = glob(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $row['shipment_code'] . DIRECTORY_SEPARATOR . "*" . "-" . $row['map_id'] . ".pdf");
                    $invididualFilePath = isset($files[0]) ? $files[0] : '';
                }
                if (file_exists($invididualFilePath) && trim($token['download_link']) != '') {
                    $downloadInReports .= '/api/participant/download/' . $token['download_link'] . '/' . base64_encode($row['map_id']);
                }

                $summaryFilePath = (DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $row['shipment_code'] . DIRECTORY_SEPARATOR . $row['shipment_code'] . "-summary.pdf");
                if (file_exists($summaryFilePath) && trim($token['download_link']) != '') {
                    $downloadSummaryReports .= '/api/participant/download-summary/' . $token['download_link'] . '/' . base64_encode($row['map_id']);
                }
            }

            $data[] = array(
                'isSynced'         => '',
                'schemeType'       => $row['scheme_type'],
                'schemeName'       => ($row['scheme_name']),
                'shipmentCode'     => ($row['shipment_code']),
                'shipmentId'       => $row['shipment_id'],
                'participantId'    => $row['participant_id'],
                'evaluationStatus' => $row['evaluation_status'],
                'is_excluded'      => $row['is_excluded'] ?? 'no',
                'shipmentDate'     => $row['shipment_date'],
                'resultDueDate'    => $row['lastdate_response'],
                'responseDate'     => $row['RESPONSEDATE'],
                'status'           => $row['status'],
                'statusUpdatedOn'  => $row['updated_on_admin'],
                'responseSwitch'   => $row['response_switch'],
                'mapId'            => $row['map_id'],
                'uniqueIdentifier' => $row['unique_identifier'],
                'participantName'  => $row['first_name'] . ' ' . $row['last_name'],
                'state'            => $row['state'],
                'dmId'             => $row['dm_id'],
                'createdOn'        => (isset($row['created_on_user']) && $row['created_on_user'] != '') ? $row['created_on_user'] : ((isset($row['created_on_admin']) && $row['created_on_admin'] != '') ? $row['created_on_admin'] : ''),
                'updatedStatus'    => (isset($row['updated_on_user']) && $row['updated_on_user'] != '') ? true : false,
                'updatedOn'        => (isset($row['updated_on_user']) && $row['updated_on_user'] != '') ? $row['updated_on_user'] : '',
                'invididualReport' => $downloadInReports,
                'invididualFileName' => (file_exists($invididualFilePath)) ? basename($invididualFilePath) : '',
                'summaryReport'   => $downloadSummaryReports,
                'summaryFileName'  => (file_exists($summaryFilePath)) ? basename($summaryFilePath) : '',
            );
            /* This API to get the shipments form using type form */
            if ($type == 'form') {

                $formData[$key]['schemeType']       = $row['scheme_type'];
                $formData[$key]['schemeName']       = ucwords($row['scheme_name']);
                $formData[$key]['shipmentId']       = $row['shipment_id'];
                $formData[$key]['shipmentCode']     = $row['shipment_code'];
                $formData[$key]['participantId']    = $row['participant_id'];
                $formData[$key]['evaluationStatus'] = $row['evaluation_status'];
                $formData[$key]['createdOn']        = (isset($row['created_on_user']) && $row['created_on_user'] != '') ? $row['created_on_user'] : ((isset($row['created_on_admin']) && $row['created_on_admin'] != '') ? $row['created_on_admin'] : '');
                $formData[$key]['updatedStatus']    = (isset($row['updated_on_user']) && $row['updated_on_user'] != '') ? true : false;
                $formData[$key]['updatedOn']        = (isset($row['updated_on_user']) && $row['updated_on_user'] != '' && $row['RESPONSEDATE'] != '' && $row['RESPONSEDATE'] != '0000-00-00') ? $row['updated_on_user'] : '';
                $formData[$key]['mapId']            = $row['map_id'];

                $formData[$key][$row['scheme_type'] . 'Data'] = $this->fetchShipmentFormDetails($row, $aResult);
                if (isset($formData[$key][$row['scheme_type'] . 'Data']) && count($formData[$key][$row['scheme_type'] . 'Data']) > 0) {
                    $checkFormSatatus = true;
                    $getParticipantDetails[$key]['schemeType']       = $row['scheme_type'];
                    $getParticipantDetails[$key]['shipmentId']       = $row['shipment_id'];
                    $getParticipantDetails[$key]['shipmentCode']       = $row['shipment_code'];
                    $getParticipantDetails[$key]['participantId']    = $row['participant_id'];
                    $getParticipantDetails[$key]['evaluationStatus'] = $row['evaluation_status'];
                }
            }
        }
        /* This API to get the shipments form using type form and returning the response*/
        if ($type == 'form') {
            if ($checkFormSatatus) {
                return array('status' => 'success', 'data' => $formData, 'profileInfo' => $aResult['profileInfo']);
            } else {
                return array('status' => 'fail', 'message' => "The following shipments doesn't have the shipment forms", 'data' => $getParticipantDetails, 'profileInfo' => $aResult['profileInfo']);
            }
        }
        return array('status' => 'success', 'data' => $data, 'profileInfo' => $aResult['profileInfo']);
    }

    public function fetchDtsShipmentDetailsInAPI($params)
    {
        /* Check the app versions & parameters */
        if (!isset($params['appVersion'])) {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        }
        if (!isset($params['authToken'])) {
            return array('status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again');
        }
        $dmDb = new Application_Model_DbTable_DataManagers();
        $aResult = $dmDb->fetchAuthToken($params);
        /* App version check */
        if ($aResult == 'app-version-failed') {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        }
        /* Validate new auth token and app-version */
        if (!$aResult) {
            return array('status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again');
        }

        /* To check the shipment details for the data managers mapped participants */
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 's.shipment_attributes', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id', 's.status', 's.response_switch', 's.updated_on_admin'))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array("spm.map_id", "spm.evaluation_status", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", 'created_on_admin', 'created_on_user', 'updated_on_user', 'is_excluded'))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.state', 'p.affiliation', 'p.phone', 'p.mobile'))
            ->joinLeft(array('c' => 'countries'), 'p.country=c.id', array('c.iso_name'))
            ->joinLeft(array('dm' => 'data_manager'), 'dm.dm_id=spm.updated_by_user', array('last_updated_by' => new Zend_Db_Expr("CONCAT(COALESCE(dm.first_name,''),' ', COALESCE(dm.last_name,''))")))
            ->joinLeft(array('ntr' => 'r_response_not_tested_reasons'), 'ntr.ntr_id=spm.vl_not_tested_reason', array('notTestedReason' => 'ntr_reason'))
            ->where("(s.status='shipped' OR s.status='evaluated' OR s.status='finalized')")
            ->where("(s.scheme_type like 'dts')")
            ->order('spm.created_on_admin DESC')
            ->order('spm.created_on_user DESC');
        // echo $sQuery;die;
        if (!empty($aResult['dm_id'])) {
            $sQuery = $sQuery->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array('*'))
                ->where("pmm.dm_id = ?", $aResult['dm_id']);
        }
        $rResult = $this->getAdapter()->fetchAll($sQuery);
        if (empty($rResult)) {
            return array('status' => 'fail', 'message' => 'Shipment Details not available');
        }
        /* Start the API services */
        $token = $dmDb->fetchAuthTokenByToken($params);
        $participantDb  = new Application_Model_DbTable_Participants();
        $schemeService  = new Application_Service_Schemes();
        $commonService = new Application_Service_Common();
        $spMap = new Application_Model_DbTable_ShipmentParticipantMap();
        $date = new Zend_Date();
        $dtsModel = new Application_Model_Dts();
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, APPLICATION_ENV);

        $data = [];
        $formData = [];
        foreach ($rResult as $key => $row) {
            $downloadInReports = '';
            $downloadSummaryReports = '';
            $summaryFilePath = '';
            $invididualFilePath = '';
            if ($row['status'] == 'finalized') {
                $invididualFilePath = (DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $row['shipment_code'] . DIRECTORY_SEPARATOR . $row['shipment_code'] . "-" . $row['map_id'] . ".pdf");
                if (!file_exists($invididualFilePath)) {
                    // Search this file name using the map id
                    $files = glob(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $row['shipment_code'] . DIRECTORY_SEPARATOR . "*" . "-" . $row['map_id'] . ".pdf");
                    $invididualFilePath = isset($files[0]) ? $files[0] : '';
                }
                if (file_exists($invididualFilePath) && trim($token['download_link']) != '') {
                    $downloadInReports .= '/api/participant/download/' . $token['download_link'] . '/' . base64_encode($row['map_id']);
                }

                $summaryFilePath = (DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $row['shipment_code'] . DIRECTORY_SEPARATOR . $row['shipment_code'] . "-summary.pdf");
                if (file_exists($summaryFilePath) && trim($token['download_link']) != '') {
                    $downloadSummaryReports .= '/api/participant/download-summary/' . $token['download_link'] . '/' . base64_encode($row['map_id']);
                }
            }

            $shipmentAttributes = json_decode($row['shipment_attributes'], true);
            $attributes = json_decode($row['attributes'], true);
            $data[] = array(
                'isSynced'         => '',
                'schemeType'       => $row['scheme_type'],
                'attributes'       => $attributes,
                'shipment_attributes' => $shipmentAttributes,
                'schemeName'       => ($row['scheme_name']),
                'shipmentCode'     => ($row['shipment_code']),
                'shipmentId'       => $row['shipment_id'],
                'participantId'    => $row['participant_id'],
                'evaluationStatus' => $row['evaluation_status'],
                'is_excluded'      => $row['is_excluded'] ?? 'no',
                'shipmentDate'     => $row['shipment_date'],
                'resultDueDate'    => $row['lastdate_response'],
                'responseDate'     => $row['RESPONSEDATE'],
                'testingDate'     => $row['shipment_test_date'],
                'status'           => $row['status'],
                'statusUpdatedOn'  => $row['updated_on_admin'],
                'responseSwitch'   => $row['response_switch'],
                'mapId'            => $row['map_id'],
                'uniqueIdentifier' => $row['unique_identifier'],
                'participantName'  => $row['first_name'] . ' ' . $row['last_name'],
                'affiliation'      => $row['affiliation'],
                'phone'            => $row['phone'],
                'mobile'           => $row['mobile'],
                'state'            => $row['state'],
                'dmId'             => $row['dm_id'],
                'createdOn'        => (isset($row['created_on_user']) && $row['created_on_user'] != '') ? $row['created_on_user'] : ((isset($row['created_on_admin']) && $row['created_on_admin'] != '') ? $row['created_on_admin'] : ''),
                'updatedStatus'    => (isset($row['updated_on_user']) && $row['updated_on_user'] != '') ? true : false,
                'updatedOn'        => (isset($row['updated_on_user']) && $row['updated_on_user'] != '') ? $row['updated_on_user'] : '',
                'invididualReport' => $downloadInReports,
                'invididualFileName' => (file_exists($invididualFilePath)) ? basename($invididualFilePath) : '',
                'summaryReport'   => $downloadSummaryReports,
                'summaryFileName'  => (file_exists($summaryFilePath)) ? basename($summaryFilePath) : '',
            );
            $dtsModel = new Application_Model_Dts();
            $allSamples =   $dtsModel->getDtsSamples($row['shipment_id'], $row['participant_id']);
            $modeOfReceipt = $commonService->getAllModeOfReceipt();
            $globalQcAccess = $commonService->getConfig('qc_access');
            $isEditable = $spMap->isShipmentEditable($row['shipment_id'], $row['participant_id']);
            $lastDate = new Zend_Date($row['lastdate_response']);
            $responseAccess = $date->compare($lastDate, Zend_Date::DATES);
            $dtsSchemeType = (isset($shipment['shipment_attributes']["dtsSchemeType"]) && $shipment['shipment_attributes']["dtsSchemeType"] != '') ? $shipment['shipment_attributes']["dtsSchemeType"] : null;

            $reportAccess = [];
            if ($isEditable && $row['view_only_access'] != 'yes') {
                if ($responseAccess == 1 && $row['status'] == 'finalized') {
                    $reportAccess['status'] = 'fail';
                    $reportAccess['message'] = 'Your response is late and this shipment has been finalized. Your result will not be evaluated';
                } elseif ($responseAccess == 1 && $row['response_switch'] == 'on') {
                    $reportAccess['status'] = 'fail';
                    $reportAccess['message'] = 'Your response is late';
                } elseif ($responseAccess == 1) {
                    $reportAccess['status'] = 'fail';
                    $reportAccess['message'] = 'Your response is late';
                } elseif ($row['status'] == 'finalized') {
                    $reportAccess['status'] = 'fail';
                    $reportAccess['message'] = 'This shipment has already been finalized. Your result will not be evaluated. Please contact your PT Provider for any clarifications';
                } else {
                    $reportAccess['status'] = 'success';
                }
            } else {
                $reportAccess['status']     = 'fail';
                $reportAccess['message']    = 'Responding for this shipment is not allowed at this time. Please contact your PT Provider for any clarifications.';
            }
            $data['access'] = $reportAccess;
            $access = $participantDb->checkParticipantAccess($row['participant_id'], $row['dm_id'], 'API');
            if ($access == false) {
                return 'Participant does not having the shipments';
            }
            $data['algorithm'] = $attributes["algorithm"] ?? null;
            $data['sampleType'] = $attributes["sampleType"] ?? null;
            $data['screeningTest'] = $dtsSchemeType;
            $data['conditionOfPTSamples'] = $shipment['attributes']["condition_pt_samples"] ?? null;
            $data['refridgerator'] = $shipment['attributes']["refridgerator"] ?? null;
            $data['roomTemperature'] = $shipment['attributes']["room_temperature"] ?? null;
            $data['stopWatch'] = $shipment['attributes']["stop_watch"] ?? null;
            $data['sampleRehydrationDate'] = (isset($shipment['attributes']["sample_rehydration_date"]) && $shipment['attributes']["sample_rehydration_date"] != '' && $shipment['attributes']["sample_rehydration_date"] != '0000-00-00') ? date('d-M-Y', strtotime($shipment['attributes']["sample_rehydration_date"])) : '';
            $data['modeOfReceipt'] = $row["mode_id"] ?? null;
            $data['qcDone'] = $row["qc_done"] ?? null;
            $data['qcDate'] = $row["qc_date"] ?? null;
            $data['qcDoneBy'] = $row["qc_done_by"] ?? null;
            $data['isPtTestNotPerformedRadio'] = $row["is_pt_test_not_performed"] ?? null;
            $data['receivedPtPanel'] = $row["received_pt_panel"] ?? null;
            $data['collectShipmentReceiptDate'] = $row["collect_panel_receipt_date"] ?? 'yes';
            $data['notTestedReason'] = $row["vl_not_tested_reason"] ?? null;
            $data['ptNotTestedComments'] = $row["pt_test_not_performed_comments"] ?? null;
            $data['ptSupportComment'] = $row["pt_support_comments"] ?? null;
            if (isset($allSamples) && !empty($allSamples)) {
                foreach ($allSamples as $sample) {
                    foreach (range(1, 3) as $no) {
                        $data['test_kit_name_' . $no] = $sample["test_kit_name_" . $no] ?? null;
                        $data['repeat_test_kit_name_' . $no] = $sample["repeat_test_kit_name_" . $no] ?? null;
                        $data['exp_date_' . $no] = $sample["exp_date_" . $no] ?? null;
                        $data['repeat_exp_date_' . $no] = $sample["repeat_exp_date_" . $no] ?? null;
                        $data['lot_no_' . $no] = $sample["lot_no_" . $no] ?? null;
                        $data['repeat_lot_no_' . $no] = $sample["repeat_lot_no_" . $no] ?? null;
                        $data['test_result_' . $no] = $sample["test_result_" . $no] ?? null;
                        $data['repeat_test_result_' . $no] = $sample["repeat_test_result_" . $no] ?? null;
                    }
                    $data['reported_result'] = $sample["reported_result"] ?? null;
                }
            }
            $data['supervisorReview'] = $row["supervisor_approval"] ?? null;
            $data['supervisorName'] = $row["participant_supervisor"] ?? null;
            $data['comments'] = $row["user_comment"] ?? null;
            $data['custom_field_1'] = $row["custom_field_1"] ?? null;
            $data['custom_field_2'] = $row["custom_field_2"] ?? null;
        }
        /* This API to get the shipments form using type form and returning the response*/
        return array('status' => 'success', 'data' => $data, 'profileInfo' => $aResult['profileInfo']);
    }

    public function fetchShipmentFormDetails($params, $dm)
    {
        // ini_set("memory_limit", -1);
        // Service / Model Calling
        $participantDb  = new Application_Model_DbTable_Participants();
        $schemeService  = new Application_Service_Schemes();
        $commonService = new Application_Service_Common();
        $spMap = new Application_Model_DbTable_ShipmentParticipantMap();
        $date = new Zend_Date();

        // Initialte the global functions
        $participant    =   $participantDb->getParticipant($params['participant_id']);
        if ($params['scheme_type'] == 'dts') {
            $dtsModel = new Application_Model_Dts();
            $allSamples =   $dtsModel->getDtsSamples($params['shipment_id'], $params['participant_id']);
        }
        if ($params['scheme_type'] == 'vl') {
            $allSamples =   $schemeService->getVlSamples($params['shipment_id'], $params['participant_id']);
        }
        if ($params['scheme_type'] == 'eid') {
            $allSamples =   $schemeService->getEidSamples($params['shipment_id'], $params['participant_id']);
        }
        if ($params['scheme_type'] == 'recency') {
            $allSamples =   $schemeService->getRecencySamples($params['shipment_id'], $params['participant_id']);
        }
        if ($params['scheme_type'] == 'covid19') {
            $allSamples =   $schemeService->getCovid19Samples($params['shipment_id'], $params['participant_id']);
        }
        $shipment = $schemeService->getShipmentData($params['shipment_id'], $params['participant_id']);
        $shipment['attributes'] = json_decode($shipment['attributes'], true);
        $shipment['shipment_attributes'] = json_decode($shipment['shipment_attributes'], true);

        $modeOfReceipt = $commonService->getAllModeOfReceipt();
        $globalQcAccess = $commonService->getConfig('qc_access');
        $isEditable = $spMap->isShipmentEditable($params['shipment_id'], $params['participant_id']);
        $lastDate = new Zend_Date($shipment['lastdate_response']);
        $responseAccess = $date->compare($lastDate, Zend_Date::DATES);
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, APPLICATION_ENV);

        if ($params['scheme_type'] == 'dts') {
            $dts = [];
            $dtsModel = new Application_Model_Dts();
            $dtsSchemeType = (isset($shipment['shipment_attributes']["dtsSchemeType"]) && $shipment['shipment_attributes']["dtsSchemeType"] != '') ? $shipment['shipment_attributes']["dtsSchemeType"] : null;
            $testThreeOptional = false;
            if (isset($config->evaluation->dts->dtsOptionalTest3) && $config->evaluation->dts->dtsOptionalTest3 == 'yes') {
                $testThreeOptional = true;
            }

            $allowRepeatTests = (isset($config->evaluation->dts->allowRepeatTests) && $config->evaluation->dts->allowRepeatTests == 'yes') ? true : false;
            if ($dtsSchemeType == 'updated-3-tests') {
                $allowRepeatTests = true;
                $testThreeOptional = false;
            }

            if ($dtsSchemeType == 'malawi' || $dtsSchemeType == 'myanmar') {
                $testThreeOptional = false;
            }

            $reportAccess = [];
            if ($isEditable && $dm['view_only_access'] != 'yes') {
                if ($responseAccess == 1 && $shipment['status'] == 'finalized') {
                    $reportAccess['status'] = 'fail';
                    $reportAccess['message'] = 'Your response is late and this shipment has been finalized. Your result will not be evaluated';
                } elseif ($responseAccess == 1 && $params['response_switch'] == 'on') {
                    $reportAccess['status'] = 'success';
                    $reportAccess['message'] = 'Your response is late';
                } elseif ($responseAccess == 1) {
                    $reportAccess['status'] = 'fail';
                    $reportAccess['message'] = 'Your response is late';
                } elseif ($shipment['status'] == 'finalized') {
                    $reportAccess['status'] = 'fail';
                    $reportAccess['message'] = 'This shipment has already been finalized. Your result will not be evaluated. Please contact your PT Provider for any clarifications';
                } else {
                    $reportAccess['status'] = 'success';
                }
            } else {
                $reportAccess['status']     = 'fail';
                $reportAccess['message']    = 'Responding for this shipment is not allowed at this time. Please contact your PT Provider for any clarifications.';
            }
            $dts['access'] = $reportAccess;
            // Check the data manager having for access to the form
            $access = $participantDb->checkParticipantAccess($params['participant_id'], $params['dm_id'], 'API');
            if ($access == false) {
                return 'Participant does not having the shipments';
            }

            // Section 1 start // First participant details start
            if (isset($participant) && !empty($participant)) {
                $dts['Section1']['status'] = true;
                $dts['Section1']['data'] = array(
                    'participantName'   => $participant['first_name'] . ' ' . $participant['last_name'],
                    'participantCode'   => $participant['unique_identifier'],
                    'affiliation'       => $participant['affiliation'],
                    'phone'             => $participant['phone'],
                    'mobile'            => $participant['mobile']
                );
            } else {
                $dts['Section1']['status'] = false;
            }
            // First participant details end // Section 1 end // Section 2 start // Shipement Result start
            $modeOfReceiptSelect = [];
            foreach ($modeOfReceipt as $receipt) {
                $modeOfReceiptSelect[] = array(
                    'value'     =>  (string) $receipt['mode_id'],
                    'show'      =>  $receipt['mode_name'],
                    'selected'  => ($shipment["mode_id"] == $receipt['mode_id']) ? 'selected' : ''
                );
            }
            // Shipement Result end // For algorithmUsed start
            $allowedAlgorithms = isset($config->evaluation->dts->allowedAlgorithms) ? explode(",", $config->evaluation->dts->allowedAlgorithms) : array();
            $algorithmUsedSelect = [];
            $algorithmUsedSelectOptions = [];

            if ($dtsSchemeType == 'updated-3-tests') {
                $allowedAlgorithms = array('dts-3-tests');
            }

            if (!empty($allowedAlgorithms) && in_array('serial', $allowedAlgorithms)) {
                array_push($algorithmUsedSelectOptions, 'serial');
            }
            if (!empty($allowedAlgorithms) && in_array('parallel', $allowedAlgorithms)) {
                array_push($algorithmUsedSelectOptions, 'parallel');
            }

            if (!empty($allowedAlgorithms) && in_array('myanmarNationalDtsAlgo', $allowedAlgorithms)) {
                array_push($algorithmUsedSelectOptions, 'myanmarNationalDtsAlgo');
            }
            if (!empty($allowedAlgorithms) && in_array('malawiNationalDtsAlgo', $allowedAlgorithms)) {
                array_push($algorithmUsedSelectOptions, 'malawiNationalDtsAlgo');
            }

            if (!empty($allowedAlgorithms) && in_array('sierraLeoneNationalDtsAlgo', $allowedAlgorithms)) {
                array_push($algorithmUsedSelectOptions, 'sierraLeoneNationalDtsAlgo');
            }

            if (!empty($allowedAlgorithms) && in_array('ghanaNationalDtsAlgo', $allowedAlgorithms)) {
                array_push($algorithmUsedSelectOptions, 'ghanaNationalDtsAlgo');
            }

            if (!empty($allowedAlgorithms) && in_array('dts-3-tests', $allowedAlgorithms)) {
                array_push($algorithmUsedSelectOptions, 'dts-3-tests');
            }

            foreach ($algorithmUsedSelectOptions as $row) {
                $show = "";
                if ($row == 'myanmarNationalDtsAlgo') {
                    $show = 'Myanmar National Algorithm';
                } elseif ($row == 'ghanaNationalDtsAlgo') {
                    $show = 'Ghana National Algorithm';
                } elseif ($row == 'malawiNationalDtsAlgo') {
                    $show = 'Malawi National Algorithm';
                } elseif ($row == 'dts-3-tests') {
                    $show = '3 Test DTS Algorithm';
                } elseif ($row == 'sierraLeoneNationalDtsAlgo') {
                    $show = 'Sierra Leone National DTS Algorithm';
                }
                $algorithmUsedSelect[]      = array(
                    'value' => $row,
                    'show' => (!empty($show)) ? ucwords($show) : ucwords($row),
                    'selected' => (isset($shipment['attributes']["algorithm"]) && ($shipment['attributes']["algorithm"] == $row) ? 'selected' : '')
                );
            }

            if (isset($participant) && !empty($participant)) {
                $dts['Section2']['status'] = true;
                // For algorithmUsed end
                $section2 = array(
                    'shipmentDate'              => date('d-M-Y', strtotime($shipment['shipment_date'])),
                    'resultDueDate'             => date('d-M-Y', strtotime($shipment['lastdate_response'])),
                    'testReceiptDate'           => (isset($shipment['shipment_receipt_date']) && $shipment['shipment_receipt_date'] != '' && $shipment['shipment_receipt_date'] != '0000:00:00') ? date('d-M-Y', strtotime($shipment['shipment_receipt_date'])) : '',
                    // 'sampleRehydrationDate'     => (isset($shipment['attributes']["sample_rehydration_date"]) && $shipment['attributes']["sample_rehydration_date"] != '' && $shipment['attributes']["sample_rehydration_date"] != '0000:00:00') ? date('d-M-Y', strtotime($shipment['attributes']["sample_rehydration_date"])) : '',
                    'testingDate'               => (isset($shipment['shipment_test_date']) && $shipment['shipment_test_date'] != '' && $shipment['shipment_test_date'] != '0000-00-00') ? date('d-M-Y', strtotime($shipment['shipment_test_date'])) : '',
                    'algorithmUsedSelect'       => $algorithmUsedSelect,
                    'algorithmUsedSelected'     => (isset($shipment['attributes']["algorithm"]) && $shipment['attributes']["algorithm"] != '') ? $shipment['attributes']["algorithm"] : '',
                    'sampleType'                => (isset($shipment['shipment_attributes']["sampleType"]) && $shipment['shipment_attributes']["sampleType"] != '') ? $shipment['shipment_attributes']["sampleType"] : '',
                    'screeningTest'             => (isset($shipment['shipment_attributes']["screeningTest"]) && $shipment['shipment_attributes']["screeningTest"] != '') ? $shipment['shipment_attributes']["screeningTest"] : '',
                    'dtsSchemeType'             => $dtsSchemeType,
                );
                if ($dtsSchemeType == 'malawi' || (isset($config->evaluation->dts->displaySampleConditionFields) && $config->evaluation->dts->displaySampleConditionFields == "yes")) {
                    $section2['conditionOfPTSamples'] = (isset($shipment['attributes']["condition_pt_samples"]) && $shipment['attributes']["condition_pt_samples"] != '') ? $shipment['attributes']["condition_pt_samples"] : '';
                    $section2['refridgerator'] = (isset($shipment['attributes']["refridgerator"]) && $shipment['attributes']["refridgerator"] != '') ? $shipment['attributes']["refridgerator"] : '';
                    $section2['roomTemperature'] = (isset($shipment['attributes']["room_temperature"]) && $shipment['attributes']["room_temperature"] != '') ? $shipment['attributes']["room_temperature"] : '';
                    $section2['stopWatch'] = (isset($shipment['attributes']["stop_watch"]) && $shipment['attributes']["stop_watch"] != '') ? $shipment['attributes']["stop_watch"] : '';

                    $section2['conditionOfPTSamplesSelect'] = array(
                        array(
                            'value'     =>  'good',
                            'show'      =>  'Good',
                            'selected'  => (isset($shipment['attributes']["condition_pt_samples"]) && $shipment['attributes']["condition_pt_samples"] == 'good') ? 'selected' : ''
                        ),
                        array(
                            'value'     =>  'bad',
                            'show'      =>  'Bad',
                            'selected'  => (isset($shipment['attributes']["condition_pt_samples"]) && $shipment['attributes']["condition_pt_samples"] == 'bad') ? 'selected' : ''
                        ),
                        array(
                            'value'     =>  'not-sure',
                            'show'      =>  'Not Sure',
                            'selected'  => (isset($shipment['attributes']["condition_pt_samples"]) && $shipment['attributes']["condition_pt_samples"] == 'not-sure') ? 'selected' : ''
                        )
                    );

                    $section2['refridgeratorSelect'] = array(
                        array(
                            'value'     =>  'available',
                            'show'      =>  'Available',
                            'selected'  => (isset($shipment['attributes']["refridgerator"]) && $shipment['attributes']["refridgerator"] == 'available') ? 'selected' : ''
                        ),
                        array(
                            'value'     =>  'not-available',
                            'show'      =>  'Not Available',
                            'selected'  => (isset($shipment['attributes']["refridgerator"]) && $shipment['attributes']["refridgerator"] == 'not-available') ? 'selected' : ''
                        )
                    );

                    $section2['stopWatchSelect'] = array(
                        array(
                            'value'     =>  'available',
                            'show'      =>  'Available',
                            'selected'  => (isset($shipment['attributes']["stop_watch"]) && $shipment['attributes']["stop_watch"] == 'available') ? 'selected' : ''
                        ),
                        array(
                            'value'     =>  'not-available',
                            'show'      =>  'Not Available',
                            'selected'  => (isset($shipment['attributes']["stop_watch"]) && $shipment['attributes']["stop_watch"] == 'not-available') ? 'selected' : ''
                        )
                    );
                }
                if ((isset($shipment['shipment_attributes']["sampleType"]) && $shipment['shipment_attributes']["sampleType"] != 'serum' && $shipment['shipment_attributes']["sampleType"] != 'plasma')) {
                    $section2['sampleRehydrationDate'] = (isset($shipment['attributes']["sample_rehydration_date"]) && $shipment['attributes']["sample_rehydration_date"] != '' && $shipment['attributes']["sample_rehydration_date"] != '0000-00-00') ? date('d-M-Y', strtotime($shipment['attributes']["sample_rehydration_date"])) : '';
                }
                $dts['Section2']['data'] = $section2;
                if ((isset($dm['enable_adding_test_response_date']) && $dm['enable_adding_test_response_date'] == 'yes') || (isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes')) {
                    if (isset($dm['enable_adding_test_response_date']) && $dm['enable_adding_test_response_date'] == 'yes' && isset($shipment['updated_on_user']) && $shipment['updated_on_user'] != '') {
                        $dts['Section2']['data']['responseDate']        = (isset($shipment['shipment_test_report_date']) && $shipment['shipment_test_report_date'] != '' && $shipment['shipment_test_report_date'] != '0000-00-00') ? date('d-M-Y', strtotime($shipment['shipment_test_report_date'])) : date('d-M-Y');
                    } else {
                        $dts['Section2']['data']['responseDate'] = '';
                    }
                    if (isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes') {
                        $dts['Section2']['data']['modeOfReceiptSelected']     = (isset($shipment["mode_id"]) && $shipment["mode_id"] != "" && $shipment["mode_id"] != 0) ? $shipment["mode_id"] : '';
                    } else {
                        $dts['Section2']['data']['modeOfReceiptSelected']     = '';
                    }
                } else {
                    $dts['Section2']['data']['responseDate']            = '';
                    $dts['Section2']['data']['modeOfReceiptSelected']     = '';
                }
                $dts['Section2']['data']['modeOfReceiptSelect'] = $modeOfReceiptSelect;
                $qcArray = array('yes', 'no');
                $qc = [];
                foreach ($qcArray as $row) {
                    $qcResponseArr[] = array('value' => $row, 'show' => ucwords($row), 'selected' => (isset($shipment['qc_done']) && $shipment['qc_done'] == $row || (($shipment['qc_done'] == null || $shipment['qc_done'] == '') && $row == 'no')) ? 'selected' : '');
                }
                $qc['qcRadio']          = $qcResponseArr;
                $qc['qcRadioSelected']  = (isset($shipment['qc_done']) && $shipment['qc_done'] == "no" || $shipment['qc_done'] == null || $shipment['qc_done'] == '') ? 'no' : 'yes';
                $qc['qcDate']   = (isset($shipment['qc_date']) && $shipment['qc_date'] != '' && $shipment['qc_date'] != '0000:00:00' && $shipment['qc_date'] != null && $shipment['qc_date'] != '1969-12-31') ? date('d-M-Y', strtotime($shipment['qc_date'])) : '';
                $qc['qcDoneBy'] = (isset($shipment['qc_done_by']) && $shipment['qc_done_by'] != '') ? $shipment['qc_done_by'] : '';
                if ($globalQcAccess != 'yes' || $dm['qc_access'] != 'yes') {
                    $qc['status']                       = false;
                    $dts['Section2']['data']['qcData']  = $qc;
                } else {
                    $qc['status']                       = true;
                    $dts['Section2']['data']['qcData']  = $qc;
                }
            } else {
                $dts['Section2']['status'] = false;
            }
            // Section 2 end // Section 3 start

            $allNotTestedReason = $schemeService->getNotTestedReasons('dts');
            $allNotTestedArray = [];
            foreach ($allNotTestedReason as $reason) {
                $allNotTestedArray[] = array(
                    'value'     => (string) $reason['ntr_id'],
                    'show'      => ucwords($reason['ntr_reason']),
                    'selected'  => ($shipment['vl_not_tested_reason'] == $reason['ntr_id']) ? 'selected' : ''
                );
            }
            if ((!isset($shipment['is_pt_test_not_performed']) || isset($shipment['is_pt_test_not_performed'])) && ($shipment['is_pt_test_not_performed'] == 'no' || $shipment['is_pt_test_not_performed'] == '')) {
                $dtsPtNotTested['isPtTestNotPerformedRadio'] = 'no';
            } else {
                $dtsPtNotTested['isPtTestNotPerformedRadio'] = 'yes';
            }
            $dtsPtNotTested['receivedPtPanel']         = (isset($shipment['received_pt_panel']) && $shipment['received_pt_panel'] != "") ? $shipment['received_pt_panel'] : "";
            $dtsPtNotTested['receivedPtPanelSelect']   = array(
                array("value" => "yes", "show" => "Yes", "selected" => ($shipment['received_pt_panel'] == "yes") ? 'selected' : ''),
                array("value" => "no", "show" => "No", "selected" => ($shipment['received_pt_panel'] == "no") ? 'selected' : ''),
            );
            $dtsPtNotTested['collectShipmentReceiptDate'] = (isset($shipment['collect_panel_receipt_date']) && $shipment['collect_panel_receipt_date'] != "") ? $shipment['collect_panel_receipt_date'] : "yes";
            $dtsPtNotTested['notTestedReasonText']     = 'Reason for not testing the PT Panel';
            $dtsPtNotTested['notTestedReasons']        = $allNotTestedArray;
            $dtsPtNotTested['notTestedReasonSelected'] = (isset($shipment['vl_not_tested_reason']) && $shipment['vl_not_tested_reason'] != "") ? $shipment['vl_not_tested_reason'] : "";
            $dtsPtNotTested['ptNotTestedCommentsText'] = 'Your comments';
            $dtsPtNotTested['ptNotTestedComments']     = (isset($shipment['pt_test_not_performed_comments']) && $shipment['pt_test_not_performed_comments'] != '') ? $shipment['pt_test_not_performed_comments'] : '';
            $dtsPtNotTested['ptSupportCommentsText']   = 'Do you need any support from the PT Provider ?';
            $dtsPtNotTested['ptSupportComments']       = (isset($shipment['pt_support_comments']) && $shipment['pt_support_comments'] != '') ? $shipment['pt_support_comments'] : '';

            $dts['Section3']['status']                 = true;
            $dts['Section3']['data']                   = $dtsPtNotTested;
            // Section 3 end // Section 4 Start

            $teskitArray = [];
            $testKitKey = 0;

            $allTestKits = $dtsModel->getAllDtsTestKitList(true);
            foreach ($allTestKits as $testKitKey => $testkit) {
                if ($testkit['testkit_1'] == '1') {
                    $teskitArray['kitNameDropdown']['Test-1']['status'] = true;
                    $teskitArray['kitNameDropdown']['Test-1']['data'][] = array(
                        'value'         => (string) $testkit['TESTKITNAMEID'],
                        'show'          => $testkit['TESTKITNAME'],
                        'selected'      => (isset($allSamples[0]["test_kit_name_1"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["test_kit_name_1"]) ? 'selected' : ''
                    );
                    if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                        $teskitArray['kitNameDropdown']['Repeat Test-1']['status'] = true;
                        $teskitArray['kitNameDropdown']['Repeat Test-1']['data'][] = array(
                            'value'         => (string) $testkit['TESTKITNAMEID'],
                            'show'          => $testkit['TESTKITNAME'],
                            'selected'      => (isset($allSamples[0]["repeat_test_kit_name_1"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["repeat_test_kit_name_1"]) ? 'selected' : ''
                        );
                    }
                    /* if(isset($allSamples[0]["test_kit_name_1"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["test_kit_name_1"]){
                        $teskitArray['Test-1']['data'][] = array(
                            'kitNameDropdown'   => $testkit['TESTKITNAME'],
                            'kitValue'  => (string)$testkit['TESTKITNAMEID']
                        );
                    } */
                }
                if ($testkit['testkit_1'] == '1' && isset($allSamples[0]["test_kit_name_1"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["test_kit_name_1"]) {
                    $teskitArray['kitName'][0] = $testkit['TESTKITNAMEID'];
                }
                if ($testkit['testkit_2'] == '1' && isset($allSamples[0]["test_kit_name_2"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["test_kit_name_2"]) {
                    $teskitArray['kitName'][1] = $testkit['TESTKITNAMEID'];
                }
                if (!$testThreeOptional) {
                    if ($testkit['testkit_3'] == '1' && isset($allSamples[0]["test_kit_name_3"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["test_kit_name_3"]) {
                        $teskitArray['kitName'][2] = $testkit['TESTKITNAMEID'];
                    }
                }

                if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                    if ($testkit['testkit_1'] == '1' && isset($allSamples[0]["repeat_test_kit_name_1"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["repeat_test_kit_name_1"]) {
                        $teskitArray['kitName'][3] = $testkit['TESTKITNAME'];
                    }
                }
                if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                    if ($testkit['testkit_2'] == '1' && isset($allSamples[0]["repeat_test_kit_name_2"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["repeat_test_kit_name_2"]) {
                        $teskitArray['kitName'][4] = $testkit['TESTKITNAME'];
                    }
                }
                if (!$testThreeOptional) {
                    if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                        if ($testkit['testkit_3'] == '1' && isset($allSamples[0]["repeat_test_kit_name_3"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["repeat_test_kit_name_3"]) {
                            $teskitArray['kitName'][5] = $testkit['TESTKITNAME'];
                        }
                    }
                }

                if ($testkit['testkit_2'] == '1') {
                    if (isset($shipment['shipment_attributes']["screeningTest"]) && $shipment['shipment_attributes']["screeningTest"] == 'yes') {
                        $teskitArray['kitNameDropdown']['Test-2']['status'] = false;
                        if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                            $teskitArray['kitNameDropdown']['Repeat Test-2']['status'] = false;
                        }
                    } else {
                        $teskitArray['kitNameDropdown']['Test-2']['status'] = true;
                        if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                            $teskitArray['kitNameDropdown']['Repeat Test-2']['status'] = true;
                        }
                    }
                    $teskitArray['kitNameDropdown']['Test-2']['data'][] = array(
                        'value'         => (string) $testkit['TESTKITNAMEID'],
                        'show'          => $testkit['TESTKITNAME'],
                        'selected'      => (isset($allSamples[0]["test_kit_name_2"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["test_kit_name_2"]) ? 'selected' : ''
                    );
                    if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                        $teskitArray['kitNameDropdown']['Repeat Test-2']['data'][] = array(
                            'value'         => (string) $testkit['TESTKITNAMEID'],
                            'show'          => $testkit['TESTKITNAME'],
                            'selected'      => (isset($allSamples[0]["repeat_test_kit_name_2"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["repeat_test_kit_name_2"]) ? 'selected' : ''
                        );
                    }
                    /* if(isset($allSamples[0]["test_kit_name_2"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["test_kit_name_2"]){
                        $teskitArray['Test-2']['data'][] = array(
                            'kitNameDropdown'   => $testkit['TESTKITNAME'],
                            'kitValue'  => (string)$testkit['TESTKITNAMEID']
                        );
                    } */
                }
                if (!$testThreeOptional) {
                    if (isset($shipment['shipment_attributes']["screeningTest"]) && $shipment['shipment_attributes']["screeningTest"] == 'yes') {
                        $teskitArray['kitNameDropdown']['Test-3']['status'] = false;
                        if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                            $teskitArray['kitNameDropdown']['Repeat Test-3']['status'] = false;
                        }
                    } else {
                        $teskitArray['kitNameDropdown']['Test-3']['status'] = true;
                        if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                            $teskitArray['kitNameDropdown']['Repeat Test-3']['status'] = true;
                        }
                    }
                } else {
                    $teskitArray['kitNameDropdown']['Test-3']['status'] = false;
                    if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                        $teskitArray['kitNameDropdown']['Repeat Test-3']['status'] = false;
                    }
                }
                if ($testkit['testkit_3'] == '1') {
                    if (!$testThreeOptional) {
                        $teskitArray['kitNameDropdown']['Test-3']['data'][] = array(
                            'value'         => (string) $testkit['TESTKITNAMEID'],
                            'show'          => $testkit['TESTKITNAME'],
                            'selected'      => (isset($allSamples[0]["test_kit_name_3"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["test_kit_name_3"]) ? 'selected' : ''
                        );
                        if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                            $teskitArray['kitNameDropdown']['Repeat Test-3']['data'][] = array(
                                'value'         => (string) $testkit['TESTKITNAMEID'],
                                'show'          => $testkit['TESTKITNAME'],
                                'selected'      => (isset($allSamples[0]["repeat_test_kit_name_3"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["repeat_test_kit_name_3"]) ? 'selected' : ''
                            );
                        }
                    } else {
                        $teskitArray['kitNameDropdown']['Test-3']['data'] = [];
                        if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                            $teskitArray['kitNameDropdown']['Repeat Test-3']['data'] = [];
                        }
                    }
                }
                /* if(isset($allSamples[0]["test_kit_name_3"]) && $testkit['TESTKITNAMEID'] == $allSamples[0]["test_kit_name_3"]){
                    $teskitArray['Test-3']['data'][] = array(
                        'kitNameDropdown'   => $testkit['TESTKITNAME'],
                        'kitValue'  => (string)$testkit['TESTKITNAMEID']
                    );
                } */
            }
            if (!isset($teskitArray['kitName'][0])) {
                $teskitArray['kitName'][0] = '';
            }
            if (!isset($teskitArray['kitName'][1])) {
                $teskitArray['kitName'][1] = '';
            }
            if (!$testThreeOptional) {
                if (!isset($teskitArray['kitName'][2])) {
                    $teskitArray['kitName'][2] = '';
                }
            }
            if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                if (!isset($teskitArray['kitName'][3])) {
                    $teskitArray['kitName'][3] = '';
                }
                if (!isset($teskitArray['kitName'][4])) {
                    $teskitArray['kitName'][4] = '';
                }
                if (!$testThreeOptional) {
                    if (!isset($teskitArray['kitName'][5])) {
                        $teskitArray['kitName'][5] = '';
                    }
                }
            }
            // if($allSamples[0]["test_kit_name_1"] == ''){
            //     $teskitArray['kitNameDropdown']['Test-1'] = array(
            //         'kitNameDropdown'   => '',
            //         'kitValue'  => ''
            //     );
            // }
            // if($allSamples[0]["test_kit_name_2"] == ''){
            //     $teskitArray['kitNameDropdown']['Test-2'] = array(
            //         'kitNameDropdown'   => '',
            //         'kitValue'  => ''
            //     );
            // }
            // if($allSamples[0]["test_kit_name_3"] == ''){
            //     $teskitArray['kitNameDropdown']['Test-3'] = array(
            //         'kitNameDropdown'   => '',
            //         'kitValue'  => ''
            //     );
            // }
            if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                if (!$testThreeOptional) {
                    $teskitArray['kitText'] = array('Test-1', 'Test-2', 'Test-3', 'Repeat Test-1', 'Repeat Test-2', 'Repeat Test-3');
                } else {
                    $teskitArray['kitText'] = array('Test-1', 'Test-2', 'Repeat Test-1', 'Repeat Test-2');
                }
            } else {
                $teskitArray['kitText'] = array('Test-1', 'Test-2', 'Test-3');
            }
            if (isset($allSamples) && count($allSamples) > 0) {
                $dts['Section4']['status'] = true;
                $teskitArray['expDate'][0]  = (isset($allSamples[0]["exp_date_1"]) && trim($allSamples[0]["exp_date_1"]) != "" && $allSamples[0]["exp_date_1"] != "0000-00-00" && $allSamples[0]["exp_date_1"] != '1969-12-31') ? date('d-M-Y', strtotime($allSamples[0]["exp_date_1"])) : '';
                $teskitArray['expDate'][1]  = (isset($allSamples[0]["exp_date_2"]) && trim($allSamples[0]["exp_date_2"]) != "" && $allSamples[0]["exp_date_2"] != "0000-00-00" && $allSamples[0]["exp_date_2"] != '1969-12-31') ? date('d-M-Y', strtotime($allSamples[0]["exp_date_2"])) : '';
                if (!$testThreeOptional) {
                    $teskitArray['expDate'][2]  = (isset($allSamples[0]["exp_date_3"]) && trim($allSamples[0]["exp_date_2"]) != "" && $allSamples[0]["exp_date_3"] != "0000-00-00" && $allSamples[0]["exp_date_3"] != '1969-12-31') ? date('d-M-Y', strtotime($allSamples[0]["exp_date_3"])) : '';
                }

                $teskitArray['expDate'][3]  = (isset($allSamples[0]["repeat_exp_date_1"]) && trim($allSamples[0]["repeat_exp_date_1"]) != "" && $allSamples[0]["repeat_exp_date_1"] != "0000-00-00" && $allSamples[0]["repeat_exp_date_1"] != '1969-12-31') ? date('d-M-Y', strtotime($allSamples[0]["repeat_exp_date_1"])) : '';
                $teskitArray['expDate'][4]  = (isset($allSamples[0]["repeat_exp_date_2"]) && trim($allSamples[0]["repeat_exp_date_2"]) != "" && $allSamples[0]["repeat_exp_date_2"] != "0000-00-00" && $allSamples[0]["repeat_exp_date_2"] != '1969-12-31') ? date('d-M-Y', strtotime($allSamples[0]["repeat_exp_date_2"])) : '';
                if (!$testThreeOptional) {
                    $teskitArray['expDate'][5]  = (isset($allSamples[0]["repeat_exp_date_3"]) && trim($allSamples[0]["repeat_exp_date_3"]) != "" && $allSamples[0]["repeat_exp_date_3"] != "0000-00-00" && $allSamples[0]["repeat_exp_date_3"] != '1969-12-31') ? date('d-M-Y', strtotime($allSamples[0]["repeat_exp_date_3"])) : '';
                }

                $teskitArray['kitValue'][0] = (isset($allSamples[0]["test_kit_name_1"]) && trim($allSamples[0]["test_kit_name_1"]) != "") ? $allSamples[0]["test_kit_name_1"] : '';
                $teskitArray['kitValue'][1] = (isset($allSamples[0]["test_kit_name_2"]) && trim($allSamples[0]["test_kit_name_2"]) != "") ? $allSamples[0]["test_kit_name_2"] : '';
                if (!$testThreeOptional) {
                    $teskitArray['kitValue'][2] = (isset($allSamples[0]["test_kit_name_3"]) && trim($allSamples[0]["test_kit_name_3"]) != "") ? $allSamples[0]["test_kit_name_3"] : '';
                }

                $teskitArray['kitValue'][3] = (isset($allSamples[0]["repeat_test_kit_name_1"]) && trim($allSamples[0]["repeat_test_kit_name_1"]) != "") ? $allSamples[0]["repeat_test_kit_name_1"] : '';
                $teskitArray['kitValue'][4] = (isset($allSamples[0]["repeat_test_kit_name_2"]) && trim($allSamples[0]["repeat_test_kit_name_2"]) != "") ? $allSamples[0]["repeat_test_kit_name_2"] : '';
                if (!$testThreeOptional) {
                    $teskitArray['kitValue'][5] = (isset($allSamples[0]["repeat_test_kit_name_3"]) && trim($allSamples[0]["repeat_test_kit_name_3"]) != "") ? $allSamples[0]["repeat_test_kit_name_3"] : '';
                }

                $teskitArray['lot'][0]      = (isset($allSamples[0]["lot_no_1"]) && trim($allSamples[0]["lot_no_1"]) != "") ? $allSamples[0]["lot_no_1"] : '';
                $teskitArray['lot'][1]      = (isset($allSamples[0]["lot_no_2"]) && trim($allSamples[0]["lot_no_2"]) != "") ? $allSamples[0]["lot_no_2"] : '';
                if (!$testThreeOptional) {
                    $teskitArray['lot'][2]      = (isset($allSamples[0]["lot_no_3"]) && trim($allSamples[0]["lot_no_3"]) != "") ? $allSamples[0]["lot_no_3"] : '';
                }

                $teskitArray['lot'][3]      = (isset($allSamples[0]["repeat_lot_no_1"]) && trim($allSamples[0]["repeat_lot_no_1"]) != "") ? $allSamples[0]["repeat_lot_no_1"] : '';
                $teskitArray['lot'][4]      = (isset($allSamples[0]["repeat_lot_no_2"]) && trim($allSamples[0]["repeat_lot_no_2"]) != "") ? $allSamples[0]["repeat_lot_no_2"] : '';
                if (!$testThreeOptional) {
                    $teskitArray['lot'][5]      = (isset($allSamples[0]["repeat_lot_no_3"]) && trim($allSamples[0]["repeat_lot_no_3"]) != "") ? $allSamples[0]["repeat_lot_no_3"] : '';
                }
                if (!$testThreeOptional) {
                    $teskitArray['kitOther']   = array('', '', '', '', '', '');
                } else {
                    $teskitArray['kitOther']   = array('', '', '', '');
                }
                if ($allSamples[0]["test_kit_name_1"] == '') {
                    $teskitArray['kitName'][0] = '';
                }
                if ($allSamples[0]["test_kit_name_2"] == '') {
                    $teskitArray['kitName'][1] = '';
                }
                if (!$testThreeOptional) {
                    if ($allSamples[0]["test_kit_name_3"] == '') {
                        $teskitArray['kitName'][2] = '';
                    }
                }
                if ($allSamples[0]["repeat_test_kit_name_1"] == '') {
                    $teskitArray['kitName'][3] = '';
                }
                if ($allSamples[0]["repeat_test_kit_name_2"] == '') {
                    $teskitArray['kitName'][4] = '';
                }
                if (!$testThreeOptional) {
                    if ($allSamples[0]["repeat_test_kit_name_3"] == '') {
                        $teskitArray['kitName'][5] = '';
                    }
                }
                // $teskitArray['testKitTextArray'] = array('Test-1','Test-2','Test-3');

                $teskitArray['kitNameDropdown']['Test-1']['data'][]    = array(
                    'value'         => 'other',
                    'show'          => 'Other',
                    'selected'      => (isset($allSamples[0]["test_kit_name_1"]) && 'other' == $allSamples[0]["test_kit_name_1"]) ? 'selected' : ''
                );
                $teskitArray['kitNameDropdown']['Test-2']['data'][] = array(
                    'value'         => 'other',
                    'show'          => 'Other',
                    'selected'      => (isset($allSamples[0]["test_kit_name_2"]) && 'other' == $allSamples[0]["test_kit_name_2"]) ? 'selected' : ''
                );
                if (!$testThreeOptional) {
                    $teskitArray['kitNameDropdown']['Test-3']['data'][] = array(
                        'value'         => 'other',
                        'show'          => 'Other',
                        'selected'      => (isset($allSamples[0]["test_kit_name_3"]) && 'other' == $allSamples[0]["test_kit_name_3"]) ? 'selected' : ''
                    );
                }

                if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                    $teskitArray['kitNameDropdown']['Repeat Test-1']['data'][]    = array(
                        'value'         => 'other',
                        'show'          => 'Other',
                        'selected'      => (isset($allSamples[0]["repeat_test_kit_name_1"]) && 'other' == $allSamples[0]["repeat_test_kit_name_1"]) ? 'selected' : ''
                    );
                    $teskitArray['kitNameDropdown']['Repeat Test-2']['data'][] = array(
                        'value'         => 'other',
                        'show'          => 'Other',
                        'selected'      => (isset($allSamples[0]["repeat_test_kit_name_2"]) && 'other' == $allSamples[0]["repeat_test_kit_name_2"]) ? 'selected' : ''
                    );
                    if (!$testThreeOptional) {
                        $teskitArray['kitNameDropdown']['Repeat Test-3']['data'][] = array(
                            'value'         => 'other',
                            'show'          => 'Other',
                            'selected'      => (isset($allSamples[0]["repeat_test_kit_name_3"]) && 'other' == $allSamples[0]["repeat_test_kit_name_3"]) ? 'selected' : ''
                        );
                    }
                }
                $dts['Section4']['data'] = $teskitArray;
            } else {
                $dts['Section4']['status'] = false;
            }
            /* Section 4 end Section 5 start */
            $dtsPossibleResults = $schemeService->getPossibleResults('dts');
            $dtsPossibleArray = [];
            $dtsPossibleResponseArray = [];
            foreach ($dtsPossibleResults as $row) {
                /* For get response code */
                $dtsPossibleArray[$row['id']] = $row['result_code'];

                /* For get response results */
                $dtsPossibleResponseArray[$row['id']] = $row['response'];
            }
            $allSamplesResult = [];
            foreach ($allSamples as $sample) {
                if (isset($shipment['is_pt_test_not_performed']) && $shipment['is_pt_test_not_performed'] == 'yes') {
                    $sample['mandatory'] = 0;
                }
                $allSamplesResult['samples']['label'][]         = $sample['sample_label'];
                $allSamplesResult['samples']['id'][]            = $sample['sample_id'];

                $dtsResponseCode1 = (isset($dtsPossibleArray[$sample['test_result_1']]) && $dtsPossibleArray[$sample['test_result_1']] != '' && $dtsPossibleArray[$sample['test_result_1']] != null) ? $dtsPossibleArray[$sample['test_result_1']] : 'X';
                $dtsResponseCode2 = (isset($dtsPossibleArray[$sample['test_result_2']]) && $dtsPossibleArray[$sample['test_result_2']] != '' && $dtsPossibleArray[$sample['test_result_2']] != null) ? $dtsPossibleArray[$sample['test_result_2']] : 'X';
                $dtsResponseCode3 = (isset($dtsPossibleArray[$sample['test_result_3']]) && $dtsPossibleArray[$sample['test_result_3']] != '' && $dtsPossibleArray[$sample['test_result_3']] != null) ? $dtsPossibleArray[$sample['test_result_3']] : 'X';
                if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                    $dtsRepeatResponseCode1 = (isset($dtsPossibleArray[$sample['repeat_test_result_1']]) && $dtsPossibleArray[$sample['repeat_test_result_1']] != '' && $dtsPossibleArray[$sample['repeat_test_result_1']] != null) ? $dtsPossibleArray[$sample['repeat_test_result_1']] : 'X';
                    $dtsRepeatResponseCode2 = (isset($dtsPossibleArray[$sample['repeat_test_result_2']]) && $dtsPossibleArray[$sample['repeat_test_result_2']] != '' && $dtsPossibleArray[$sample['repeat_test_result_2']] != null) ? $dtsPossibleArray[$sample['repeat_test_result_2']] : 'X';
                    $dtsRepeatResponseCode3 = (isset($dtsPossibleArray[$sample['repeat_test_result_3']]) && $dtsPossibleArray[$sample['repeat_test_result_3']] != '' && $dtsPossibleArray[$sample['repeat_test_result_3']] != null) ? $dtsPossibleArray[$sample['repeat_test_result_3']] : 'X';
                }
                $dtsResponseCodeFinal = (isset($dtsPossibleArray[$sample['reported_result']]) && $dtsPossibleArray[$sample['reported_result']] != '' && $dtsPossibleArray[$sample['reported_result']] != null) ? $dtsPossibleArray[$sample['reported_result']] : 'X';

                $dtsResponseResult1 = (isset($dtsPossibleResponseArray[$sample['test_result_1']]) && $dtsPossibleResponseArray[$sample['test_result_1']] != '' && $dtsPossibleResponseArray[$sample['test_result_1']] != null) ? $dtsPossibleResponseArray[$sample['test_result_1']] : '';
                $dtsResponseResult2 = (isset($dtsPossibleResponseArray[$sample['test_result_2']]) && $dtsPossibleResponseArray[$sample['test_result_2']] != '' && $dtsPossibleResponseArray[$sample['test_result_2']] != null) ? $dtsPossibleResponseArray[$sample['test_result_2']] : '';
                $dtsResponseResult3 = (isset($dtsPossibleResponseArray[$sample['test_result_3']]) && $dtsPossibleResponseArray[$sample['test_result_3']] != '' && $dtsPossibleResponseArray[$sample['test_result_3']] != null) ? $dtsPossibleResponseArray[$sample['test_result_3']] : '';
                if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                    $dtsRepeatResponseResult1 = (isset($dtsPossibleResponseArray[$sample['repeat_test_result_1']]) && $dtsPossibleResponseArray[$sample['repeat_test_result_1']] != '' && $dtsPossibleResponseArray[$sample['repeat_test_result_1']] != null) ? $dtsPossibleResponseArray[$sample['repeat_test_result_1']] : '';
                    $dtsRepeatResponseResult2 = (isset($dtsPossibleResponseArray[$sample['repeat_test_result_2']]) && $dtsPossibleResponseArray[$sample['repeat_test_result_2']] != '' && $dtsPossibleResponseArray[$sample['repeat_test_result_2']] != null) ? $dtsPossibleResponseArray[$sample['repeat_test_result_2']] : '';
                    $dtsRepeatResponseResult3 = (isset($dtsPossibleResponseArray[$sample['repeat_test_result_3']]) && $dtsPossibleResponseArray[$sample['repeat_test_result_3']] != '' && $dtsPossibleResponseArray[$sample['repeat_test_result_3']] != null) ? $dtsPossibleResponseArray[$sample['repeat_test_result_3']] : '';
                }
                $dtsResponseResultFinal = (isset($dtsPossibleResponseArray[$sample['reported_result']]) && $dtsPossibleResponseArray[$sample['reported_result']] != '' && $dtsPossibleResponseArray[$sample['reported_result']] != null) ? $dtsPossibleResponseArray[$sample['reported_result']] : '';

                $allSamplesResult['samples']['result1'][]       = array(
                    'resultCode'    => (isset($sample['test_result_1']) && $sample['test_result_1'] != '' && $sample['test_result_1'] != null) ? $dtsResponseCode1 : 'X',
                    'selected'      => (isset($sample['test_result_1']) && $sample['test_result_1'] != '') ? 'selected' : '',
                    'show'          => (isset($sample['test_result_1']) && $sample['test_result_1'] != '' && $sample['test_result_1'] != null) ? $dtsResponseResult1 : '',
                    'value'         => (isset($sample['test_result_1']) && $sample['test_result_1'] != '') ? $sample['test_result_1'] : '',
                );
                $allSamplesResult['samples']['result2'][]       = array(
                    'resultCode'    => (isset($sample['test_result_2']) && $sample['test_result_2'] != '' && $sample['test_result_2'] != null) ? $dtsResponseCode2 : 'X',
                    'selected'      => (isset($sample['test_result_2']) && $sample['test_result_2'] != '') ? 'selected' : '',
                    'show'          => (isset($sample['test_result_2']) && $sample['test_result_2'] != '' && $sample['test_result_2'] != null) ? $dtsResponseResult2 : '',
                    'value'         => (isset($sample['test_result_2']) && $sample['test_result_2'] != '') ? $sample['test_result_2'] : '',
                );
                $allSamplesResult['samples']['result3'][]       = array(
                    'resultCode'    => (isset($sample['test_result_3']) && $sample['test_result_3'] != '' && $sample['test_result_3'] != null) ? $dtsResponseCode3 : 'X',
                    'selected'      => (isset($sample['test_result_3']) && $sample['test_result_3'] != '') ? 'selected' : '',
                    'show'          => (isset($sample['test_result_3']) && $sample['test_result_3'] != '' && $sample['test_result_3'] != null) ? $dtsResponseResult3 : '',
                    'value'         => (isset($sample['test_result_3']) && $sample['test_result_3'] != '') ? $sample['test_result_3'] : '',
                );
                if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                    $allSamplesResult['samples']['repeatResult1'][]       = array(
                        'resultCode'    => (isset($sample['repeat_test_result_1']) && $sample['repeat_test_result_1'] != '' && $sample['repeat_test_result_1'] != null) ? $dtsRepeatResponseCode1 : 'X',
                        'selected'      => (isset($sample['repeat_test_result_1']) && $sample['repeat_test_result_1'] != '') ? 'selected' : '',
                        'show'          => (isset($sample['repeat_test_result_1']) && $sample['repeat_test_result_1'] != '' && $sample['repeat_test_result_1'] != null) ? $dtsRepeatResponseResult1 : '',
                        'value'         => (isset($sample['repeat_test_result_1']) && $sample['repeat_test_result_1'] != '') ? $sample['repeat_test_result_1'] : '',
                    );
                    $allSamplesResult['samples']['repeatResult2'][]       = array(
                        'resultCode'    => (isset($sample['repeat_test_result_2']) && $sample['repeat_test_result_2'] != '' && $sample['repeat_test_result_2'] != null) ? $dtsRepeatResponseCode2 : 'X',
                        'selected'      => (isset($sample['repeat_test_result_2']) && $sample['repeat_test_result_2'] != '') ? 'selected' : '',
                        'show'          => (isset($sample['repeat_test_result_2']) && $sample['repeat_test_result_2'] != '' && $sample['repeat_test_result_2'] != null) ? $dtsRepeatResponseResult2 : '',
                        'value'         => (isset($sample['repeat_test_result_2']) && $sample['repeat_test_result_2'] != '') ? $sample['repeat_test_result_2'] : '',
                    );
                    $allSamplesResult['samples']['repeatResult3'][]       = array(
                        'resultCode'    => (isset($sample['repeat_test_result_3']) && $sample['repeat_test_result_3'] != '' && $sample['repeat_test_result_3'] != null) ? $dtsRepeatResponseCode3 : 'X',
                        'selected'      => (isset($sample['repeat_test_result_3']) && $sample['repeat_test_result_3'] != '') ? 'selected' : '',
                        'show'          => (isset($sample['repeat_test_result_3']) && $sample['repeat_test_result_3'] != '' && $sample['repeat_test_result_3'] != null) ? $dtsRepeatResponseResult3 : '',
                        'value'         => (isset($sample['repeat_test_result_3']) && $sample['repeat_test_result_3'] != '') ? $sample['repeat_test_result_3'] : '',
                    );
                }
                $allSamplesResult['samples']['finalResult'][]       = array(
                    'resultCode'    => (isset($sample['reported_result']) && $sample['reported_result'] != '' && $sample['reported_result'] != null) ? $dtsResponseCodeFinal : 'X',
                    'selected'      => (isset($sample['reported_result']) && $sample['reported_result'] != '') ? 'selected' : '',
                    'show'          => (isset($sample['reported_result']) && $sample['reported_result'] != '' && $sample['reported_result'] != null) ? $dtsResponseResultFinal : '',
                    'value'         => (isset($sample['reported_result']) && $sample['reported_result'] != '') ? $sample['reported_result'] : '',
                );
                $allSamplesResult['samples']['result1Code'][]       = (isset($sample['test_result_1']) && $sample['test_result_1'] != '' && $sample['test_result_1'] != null) ? $dtsResponseCode1 : 'X';
                $allSamplesResult['samples']['result2Code'][]       = (isset($sample['test_result_2']) && $sample['test_result_2'] != '' && $sample['test_result_2'] != null) ? $dtsResponseCode2 : 'X';
                $allSamplesResult['samples']['result3Code'][]       = (isset($sample['test_result_3']) && $sample['test_result_3'] != '' && $sample['test_result_3'] != null) ? $dtsResponseCode3 : 'X';
                if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                    $allSamplesResult['samples']['repeatResult1Code'][]       = (isset($sample['repeat_test_result_1']) && $sample['repeat_test_result_1'] != '' && $sample['repeat_test_result_1'] != null) ? $dtsRepeatResponseCode1 : 'X';
                    $allSamplesResult['samples']['repeatResult2Code'][]       = (isset($sample['repeat_test_result_2']) && $sample['repeat_test_result_2'] != '' && $sample['repeat_test_result_2'] != null) ? $dtsRepeatResponseCode2 : 'X';
                    $allSamplesResult['samples']['repeatResult3Code'][]       = (isset($sample['repeat_test_result_3']) && $sample['repeat_test_result_3'] != '' && $sample['repeat_test_result_3'] != null) ? $dtsRepeatResponseCode3 : 'X';
                }
                $allSamplesResult['samples']['finalResultCode'][]   = (isset($sample['reported_result']) && $sample['reported_result'] != '' && $sample['reported_result'] != null) ? $dtsResponseResultFinal : 'X';
                $allSamplesResult['samples']['mandatory'][]     = ($sample['mandatory'] == 1) ? true : false;
                foreach (range(1, 3) as $row) {
                    $possibleResults = [];
                    if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                        $repeatPossibleResults = [];
                    }
                    if ($row == 3) {
                        foreach ($dtsPossibleResults as $pr) {
                            if ($pr['scheme_sub_group'] == 'DTS_TEST') {
                                $possibleResults[] = array('value' => (string) $pr['id'], 'show' => $pr['response'], 'resultCode' => $pr['result_code'], 'selected' => ($sample['test_result_3'] == $pr['id']) ? 'selected' : '');
                                if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                                    $repeatPossibleResults[] = array('value' => (string) $pr['id'], 'show' => $pr['response'], 'resultCode' => $pr['result_code'], 'selected' => ($sample['repeat_test_result_3'] == $pr['id']) ? 'selected' : '');
                                }
                                // if($sample['test_result_3'] == $pr['id']){
                                //     $allSamplesResult['sampleName'][$sample['sample_label']][]  = array('resultName'=>'Result-3','resultValue'=>(string)$sample['test_result_3']);
                                //     $sample3Select                                              = $sample['test_result_3'];
                                // }
                            }
                        }
                        if (!$testThreeOptional) {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['status'] = true;
                            if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                                $allSamplesResult['sampleList'][$sample['sample_label']]['Repeat Result-' . $row]['status'] = true;
                            }
                        } else {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['status'] = false;
                            if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                                $allSamplesResult['sampleList'][$sample['sample_label']]['Repeat Result-' . $row]['status'] = false;
                            }
                        }
                        if (!$testThreeOptional) {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['data']      = $possibleResults;
                            if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                                $allSamplesResult['sampleList'][$sample['sample_label']]['Repeat Result-' . $row]['data']      = $repeatPossibleResults;
                            }
                        } else {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['data']      = [];
                            if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                                $allSamplesResult['sampleList'][$sample['sample_label']]['Repeat Result-' . $row]['data']      = [];
                            }
                        }
                        if (isset($sample['test_result_3']) && $sample['test_result_3'] != "") {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['value'] = $sample['test_result_3'];
                        } else {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['value'] = "";
                        }
                        if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                            if (isset($sample['repeat_test_result_3']) && $sample['repeat_test_result_3'] != "") {
                                $allSamplesResult['sampleList'][$sample['sample_label']]['Repeat Result-' . $row]['value'] = $sample['repeat_test_result_3'];
                            } else {
                                $allSamplesResult['sampleList'][$sample['sample_label']]['Repeat Result-' . $row]['value'] = "";
                            }
                        }
                    } else {
                        foreach ($dtsPossibleResults as $pr) {
                            if ($pr['scheme_sub_group'] == 'DTS_TEST') {
                                $possibleResults[] = array('value' => (string) $pr['id'], 'show' => $pr['response'], 'resultCode' => $pr['result_code'], 'selected' => (($sample['test_result_1'] == $pr['id'] && $row == 1) || ($sample['test_result_2'] == $pr['id'] && $row == 2)) ? 'selected' : '');
                                if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                                    $repeatPossibleResults[] = array('value' => (string) $pr['id'], 'show' => $pr['response'], 'resultCode' => $pr['result_code'], 'selected' => (($sample['repeat_test_result_1'] == $pr['id'] && $row == 1) || ($sample['repeat_test_result_2'] == $pr['id'] && $row == 2)) ? 'selected' : '');
                                }
                                // if($sample['test_result_1'] == $pr['id'] && $row == 1){
                                //     $allSamplesResult['sampleName'][$sample['sample_label']][]  = array('resultName'=>'Result-1','resultValue'=>$sample['test_result_1']);
                                //     $sample1Select                                              = $sample['test_result_1'];
                                // }else if($sample['test_result_2'] == $pr['id'] && $row == 2){
                                //     $allSamplesResult['sampleName'][$sample['sample_label']][]  = array('resultName'=>'Result-2','resultValue'=>(string)$sample['test_result_2']);
                                //     $sample2Select                                              = $sample['test_result_2'];
                                // }
                            }
                        }
                        if (((isset($shipment['shipment_attributes']["screeningTest"]) && $shipment['shipment_attributes']["screeningTest"] == 'no') && $row == 2) || $row == 1) {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['status']    = true;
                            if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                                $allSamplesResult['sampleList'][$sample['sample_label']]['Repeat Result-' . $row]['status']    = true;
                            }
                        } elseif ($row == 2) {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['status']    = false;
                            if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                                $allSamplesResult['sampleList'][$sample['sample_label']]['Repeat Result-' . $row]['status']    = false;
                            }
                        }
                        $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['data']      = $possibleResults;
                        if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Repeat Result-' . $row]['data']      = $repeatPossibleResults;
                        }

                        if (isset($sample['test_result_1']) && $sample['test_result_1'] != "" && $row == 1) {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['value'] = $sample['test_result_1'];
                        } elseif (isset($sample['test_result_2']) && $sample['test_result_2'] != "" && $row == 2) {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['value'] = $sample['test_result_2'];
                        } else {
                            if ($row == 1) {
                                $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['value'] = "";
                            }
                        }
                        if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                            if (isset($sample['repeat_test_result_1']) && $sample['repeat_test_result_1'] != "" && $row == 1) {
                                $allSamplesResult['sampleList'][$sample['sample_label']]['Repeat Result-' . $row]['value'] = $sample['repeat_test_result_1'];
                            } elseif (isset($sample['repeat_test_result_2']) && $sample['repeat_test_result_2'] != "" && $row == 2) {
                                $allSamplesResult['sampleList'][$sample['sample_label']]['Repeat Result-' . $row]['value'] = $sample['repeat_test_result_2'];
                            } else {
                                if ($row == 1) {
                                    $allSamplesResult['sampleList'][$sample['sample_label']]['Repeat Result-' . $row]['value'] = "";
                                }
                            }
                        }
                    }
                }
                $possibleFinalResults = [];
                foreach ($dtsPossibleResults as $pr) {
                    if ($pr['scheme_sub_group'] == 'DTS_FINAL') {
                        $possibleFinalResults[] = array('value' => (string) $pr['id'], 'show' => $pr['response'], 'resultCode' => $pr['result_code'], 'selected' => ($sample['reported_result'] == $pr['id']) ? 'selected' : '');
                        /* if($sample['reported_result'] == $pr['id']){
                            $allSamplesResult['sampleName'][$sample['sample_label']][]  = array('resultName'=>'Final-Result','resultValue'=>(string)$sample['reported_result']);
                            $sampleFinalSelect                                          = $sample['reported_result'];
                        } */
                    }
                }
                // $allSamplesResult['sampleSelect'][$sample['sample_label']][]=array($sample1Select,$sample2Select,$sample3Select,$sampleFinalSelect);

                /* if((isset($shipment['shipment_attributes']["screeningTest"]) && $shipment['shipment_attributes']["screeningTest"] == 'yes')){
                    $allSamplesResult['resultsText'] = array('Result-1','Final-Result');
                } else{ */
                if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                    $allSamplesResult['resultsText'] = array('Result-1', 'Result-2', 'Result-3', 'Repeat Result-1', 'Repeat Result-2', 'Repeat Result-3', 'Final-Result');
                } else {
                    $allSamplesResult['resultsText'] = array('Result-1', 'Result-2', 'Result-3', 'Repeat Result-1', 'Repeat Result-2', 'Repeat Result-3', 'Final-Result');
                }
                // }

                if (!$testThreeOptional) {
                    $allSamplesResult['resultStatus'] = array(
                        true, // Result-1
                        true, // Result-2
                        true, // Result-3
                        false, // Repeat Result-1
                        false, // Repeat Result-2
                        false, // Repeat Result-3
                        true  // Final Result
                    );
                } else {
                    $allSamplesResult['resultStatus'] = array(
                        true, // Result-1
                        true, // Result-2
                        false, // Result-3
                        false, // Repeat Result-1
                        false, // Repeat Result-2
                        false, // Repeat Result-3
                        true  // Final Result
                    );
                }
                if ((isset($shipment['shipment_attributes']["screeningTest"]) && $shipment['shipment_attributes']["screeningTest"] == 'yes')) {
                    $allSamplesResult['resultStatus'] = array(
                        true, // Result-1
                        false, // Result-2
                        false, // Result-3
                        false, // Repeat Result-1
                        false, // Repeat Result-2
                        false, // Repeat Result-3
                        true  // Final Result
                    );
                }
                if ((isset($allowRepeatTests) && $allowRepeatTests)) {
                    if (!$testThreeOptional) {
                        $allSamplesResult['resultStatus'] = array(
                            true, // Result-1
                            true, // Result-2
                            true, // Result-3
                            true, // Repeat Result-1
                            true, // Repeat Result-2
                            true, // Repeat Result-3
                            true  // Final Result
                        );
                    } else {
                        $allSamplesResult['resultStatus'] = array(
                            true, // Result-1
                            true, // Result-2
                            false, // Result-3
                            true, // Repeat Result-1
                            true, // Repeat Result-2
                            false, // Repeat Result-3
                            true  // Final Result
                        );
                    }
                    if ((isset($shipment['shipment_attributes']["screeningTest"]) && $shipment['shipment_attributes']["screeningTest"] == 'yes')) {
                        $allSamplesResult['resultStatus'] = array(
                            true, // Result-1
                            false, // Result-2
                            false, // Result-3
                            true, // Repeat Result-1
                            false, // Repeat Result-2
                            false, // Repeat Result-3
                            true  // Final Result
                        );
                    }
                }
                $allSamplesResult['sampleList'][$sample['sample_label']]['Final-Result']['status']    = true;
                $allSamplesResult['sampleList'][$sample['sample_label']]['Final-Result']['data']      = $possibleFinalResults;
                $allSamplesResult['sampleList'][$sample['sample_label']]['Final-Result']['value']     = (isset($sample['reported_result']) && $sample['reported_result'] != '') ? $sample['reported_result'] : '';
            }
            if ((isset($allSamples) && count($allSamples) > 0) && (isset($dtsPossibleResults) && count($dtsPossibleResults) > 0)) {
                $dts['Section5']['status']  = true;
            } else {
                $dts['Section5']['status']  = false;
            }
            $dts['Section5']['data']        = $allSamplesResult;
            // Section 5 End // Section 6 Start
            $reviewArray = [];
            $commentArray = array('yes', 'no');
            $revieArr = [];
            foreach ($commentArray as $row) {
                $revieArr[] = array('value' => $row, 'show' => ucwords($row), 'selected' => (isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] == $row || (($shipment['supervisor_approval'] != null || $shipment['supervisor_approval'] != '') && $row == 'yes')) ? 'selected' : '');
            }
            $reviewArray['supervisorReview']        = $revieArr;
            $reviewArray['supervisorReviewSelected'] = (isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] != '') ? $shipment['supervisor_approval'] : '';
            $reviewArray['approvalLabel']           = 'Supervisor Name';
            $reviewArray['approvalInputText']       = (isset($shipment['participant_supervisor']) && $shipment['participant_supervisor'] != '') ? $shipment['participant_supervisor'] : '';
            $reviewArray['comments']                = (isset($shipment['user_comment']) && $shipment['user_comment'] != '') ? $shipment['user_comment'] : '';
            $dts['Section6']['status']              = true;
            $dts['Section6']['data']                = $reviewArray;
            // Section 6 End
            $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
            $customField1 = $globalConfigDb->getValue('custom_field_1');
            $customField2 = $globalConfigDb->getValue('custom_field_2');
            $haveCustom = $globalConfigDb->getValue('custom_field_needed');
            if (isset($haveCustom) && $haveCustom != 'no') {
                $dts['customFields']['status'] = true;
                if (isset($customField1) && trim($customField1) != "") {
                    $dts['customFields']['data']['customField1Text'] = $customField1;
                    $dts['customFields']['data']['customField1Val'] = (isset($shipment['custom_field_1']) && $shipment['custom_field_1'] != "") ? $shipment['custom_field_1'] : '';
                }

                if (isset($customField2) && trim($customField2) != "") {
                    $dts['customFields']['data']['customField2Text'] = $customField2;
                    $dts['customFields']['data']['customField2Val'] = (isset($shipment['custom_field_2']) && $shipment['custom_field_2'] != "") ? $shipment['custom_field_2'] : '';
                }
            } else {
                $dts['customFields']['status'] = false;
            }
            return $dts;
        }
        if ($params['scheme_type'] == 'vl') {
            $reportAccess = [];
            $vl = [];
            if ($isEditable && $dm['view_only_access'] != 'yes') {
                if ($responseAccess == 1 && $shipment['status'] == 'finalized') {
                    $reportAccess['status'] = 'fail';
                    $reportAccess['message'] = 'Your response is late and this shipment has been finalized. Your result will not be evaluated';
                } elseif ($responseAccess == 1 && $params['response_switch'] == 'on') {
                    $reportAccess['status'] = 'success';
                    $reportAccess['message'] = 'Your response is late';
                } elseif ($responseAccess == 1) {
                    $reportAccess['status'] = 'fail';
                    $reportAccess['message'] = 'Your response is late';
                } elseif ($shipment['status'] == 'finalized') {
                    $reportAccess['status'] = 'fail';
                    $reportAccess['message'] = 'This shipment has been finalized. Your result will not be evaluated. Please contact your PT Provider for any clarifications';
                } else {
                    $reportAccess['status'] = 'success';
                }
            } else {
                $reportAccess['status']     = 'fail';
                $reportAccess['message']    = 'Responding for this shipment is not allowed at this time. Please contact your PT Provider for any clarifications.';
            }
            $vl['access'] = $reportAccess;
            // Section 1 start
            $labDirectorName = (isset($shipment['lab_director_name']) && $shipment['lab_director_name'] != "") ? $shipment['lab_director_name'] : $participant['lab_director_name'];
            $labDirectorEmail = (isset($shipment['lab_director_email']) && $shipment['lab_director_email'] != "") ? $shipment['lab_director_email'] : $participant['lab_director_email'];
            $contactPersonName = (isset($shipment['contact_person_name']) && $shipment['contact_person_name'] != "") ? $shipment['contact_person_name'] : $participant['contact_person_name'];
            $contactPersonEmail = (isset($shipment['contact_person_email']) && $shipment['contact_person_email'] != "") ? $shipment['contact_person_email'] : $participant['contact_person_email'];
            $contactPersonTelephone = (isset($shipment['contact_person_telephone']) && $shipment['contact_person_telephone'] != "") ? $shipment['contact_person_telephone'] : $participant['contact_person_telephone'];

            $section1 = array(
                'participantName'           => ((isset($participant['first_name']) && $participant['first_name'] != '') ? $participant['first_name'] : '') . ((isset($participant['last_name']) && $participant['last_name'] != '') ? ' ' . $participant['last_name'] : ''),
                'participantCode'           => (isset($participant['unique_identifier']) && $participant['unique_identifier'] != '') ? $participant['unique_identifier'] : '',
                'affiliation'               => (isset($participant['affiliation']) && $participant['affiliation'] != '') ? $participant['affiliation'] : '',
                'phone'                     => (isset($participant['phone']) && $participant['phone'] != '') ? $participant['phone'] : '',
                'mobile'                    => (isset($participant['mobile']) && $participant['mobile'] != '') ? $participant['mobile'] : '',
                'laboratoryName'            => (isset($participant['first_name']) && $participant['first_name'] != '') ? $participant['first_name'] . $participant['last_name'] : '',
                'laboratoryId'              => (isset($participant['unique_identifier']) && $participant['unique_identifier'] != '') ? $participant['unique_identifier'] : '',
                'labDirectorName'           => (isset($labDirectorName) && $labDirectorName != '') ? $labDirectorName : '',
                'labDirectorEmail'          => (isset($labDirectorEmail) && $labDirectorEmail != '') ? $labDirectorEmail : '',
                'contactPersonName'         => (isset($contactPersonName) && $contactPersonName != '') ? $contactPersonName : '',
                'contactPersonEmail'        => (isset($contactPersonEmail) && $contactPersonEmail != '') ? $contactPersonEmail : '',
                'contactPersonTelephone'    => (isset($contactPersonTelephone) && $contactPersonTelephone != '') ? $contactPersonTelephone : '',
            );
            if (isset($participant) && !empty($participant)) {
                $vl['Section1']['status']   = true;
                $vl['Section1']['data']     = $section1;
            } else {
                $vl['Section1']['status']   = false;
                $vl['Section1']['data']     = $section1;
            }
            // Section1 end // Section2 start
            $section2 = [];
            $vlAssayArr = [];
            $vlAssay = $schemeService->getVlAssay();
            if (isset($shipment) && count($shipment) > 0) {
                foreach ($vlAssay as $id => $name) {
                    $vlAssayArr[] = array(
                        'value'     => (string) $id,
                        'show'      => $name,
                        'selected'  => (isset($shipment['attributes']['vl_assay']) && $shipment['attributes']['vl_assay'] == $id) ? 'selected' : ''
                    );
                }
                $modeOfReceiptSelect = [];
                foreach ($modeOfReceipt as $receipt) {
                    $modeOfReceiptSelect[] = array(
                        'value'     =>  (string) $receipt['mode_id'],
                        'show'      =>  $receipt['mode_name'],
                        'selected'  => ($shipment["mode_id"] == $receipt['mode_id']) ? 'selected' : ''
                    );
                }
                $section2['status']                         = true;
                $section2['data']['shipmentDate']           = date('d-M-Y', strtotime($shipment['shipment_date']));
                $section2['data']['resultDueDate']          = date('d-M-Y', strtotime($shipment['lastdate_response']));
                $section2['data']['testReceiptDate']        = (isset($shipment['shipment_receipt_date']) && $shipment['shipment_receipt_date'] != '' && $shipment['shipment_receipt_date'] != '0000-00-00') ? date('d-M-Y', strtotime($shipment['shipment_receipt_date'])) : '';
                $section2['data']['sampleRehydrationDate']  = (isset($shipment['attributes']["sample_rehydration_date"]) && $shipment['attributes']["sample_rehydration_date"] != '' && $shipment['attributes']["sample_rehydration_date"] != '0000-00-00') ? date('d-M-Y', strtotime($shipment['attributes']["sample_rehydration_date"])) : '';
                $section2['data']['testDate']               = (isset($shipment["shipment_test_date"]) && $shipment["shipment_test_date"] != '' && $shipment["shipment_test_date"] != '0000-00-00') ? date('d-M-Y', strtotime($shipment["shipment_test_date"])) : '';
                $section2['data']['specimenVolume']         = (isset($shipment['attributes']['specimen_volume']) && $shipment['attributes']['specimen_volume'] != "") ? $shipment['attributes']['specimen_volume'] : null;
                $section2['data']['vlAssaySelect']          = $vlAssayArr;
                $section2['data']['vlAssaySelected']        = (isset($shipment['attributes']['vl_assay']) && $shipment['attributes']['vl_assay'] != "") ? (string) $shipment['attributes']['vl_assay'] : '';
                $section2['data']['otherAssay']             = (isset($shipment['attributes']['other_assay']) && $shipment['attributes']['other_assay'] != '') ? $shipment['attributes']['other_assay'] : '';
                $section2['data']['assayExpirationDate']    = (isset($shipment['attributes']['assay_expiration_date']) && $shipment['attributes']['assay_expiration_date'] != '' && $shipment['attributes']['assay_expiration_date'] != '0000-00-00') ? date('d-M-Y', strtotime($shipment['attributes']['assay_expiration_date'])) : '';
                $section2['data']['assayLotNumber']         = !empty($shipment['attributes']['assay_lot_number']) ? $shipment['attributes']['assay_lot_number'] : null;
                if ((isset($dm['enable_adding_test_response_date']) && $dm['enable_adding_test_response_date'] == 'yes') || (isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes')) {
                    if (isset($dm['enable_adding_test_response_date']) && $dm['enable_adding_test_response_date'] == 'yes' && isset($shipment['updated_on_user']) && $shipment['updated_on_user'] != '') {
                        $section2['data']['responseDate']        = (isset($shipment['shipment_test_report_date']) && $shipment['shipment_test_report_date'] != '' && $shipment['shipment_test_report_date'] != '0000-00-00') ? date('d-M-Y', strtotime($shipment['shipment_test_report_date'])) : date('d-M-Y');
                    } else {
                        $section2['data']['responseDate'] = null;
                    }
                    if (isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes') {
                        $section2['data']['modeOfReceiptSelected']  = (isset($shipment['mode_id']) && $shipment['mode_id'] != "" && $shipment['mode_id'] != 0) ? $shipment['mode_id'] : '';
                    } else {
                        $section2['data']['modeOfReceiptSelected']      = "";
                    }
                } else {
                    $section2['data']['responseDate']               = "";
                    $section2['data']['modeOfReceiptSelected']      = "";
                }
                $section2['data']['modeOfReceiptSelect']    = $modeOfReceiptSelect;
            }

            $qcArray = array('yes', 'no');
            $qc = [];
            foreach ($qcArray as $row) {
                $qcResponseArr[] = array('value' => (string) $row, 'show' => ucwords($row), 'selected' => (isset($shipment['qc_done']) && $shipment['qc_done'] == $row || (($shipment['qc_done'] == null || $shipment['qc_done'] == '') && $row == 'no')) ? 'selected' : '');
            }
            $qc['qcRadio']          = $qcResponseArr;
            $qc['qcRadioSelected']  = (isset($shipment['qc_done']) && $shipment['qc_done'] == "no" || $shipment['qc_done'] == null || $shipment['qc_done'] == '') ? 'no' : 'yes';
            $qc['qcDate']           = (isset($shipment['qc_date']) && $shipment['qc_date'] != '' && $shipment['qc_date'] != '0000:00:00' && $shipment['qc_date'] != null && $shipment['qc_date'] != '1969-12-31') ? date('d-M-Y', strtotime($shipment['qc_date'])) : '';
            $qc['qcDoneBy']         = (isset($shipment['qc_done_by']) && $shipment['qc_done_by'] != '') ? $shipment['qc_done_by'] : '';
            if ($globalQcAccess != 'yes' || $dm['qc_access'] != 'yes') {
                $qc['status'] = false;
            } else {
                $qc['status'] = true;
            }
            $section2['data']['qcData'] = $qc;

            $vl['Section2'] = $section2;
            // Section 2 end // Section 3 start
            $section3 = [];
            $section3['status'] = true;
            $allNotTestedReason = $schemeService->getNotTestedReasons('vl');
            if ((!isset($shipment['is_pt_test_not_performed']) || isset($shipment['is_pt_test_not_performed'])) && ($shipment['is_pt_test_not_performed'] == 'no' || $shipment['is_pt_test_not_performed'] == '')) {
                $section3['data']['isPtTestNotPerformedRadio'] = 'no';
            } else {
                $section3['data']['isPtTestNotPerformedRadio'] = 'yes';
            }
            $section3['data']['receivedPtPanel']         = (isset($shipment['received_pt_panel']) && $shipment['received_pt_panel'] != "") ? $shipment['received_pt_panel'] : "";
            $section3['data']['receivedPtPanelSelect']   = array(
                array("value" => "yes", "show" => "Yes", "selected" => ($shipment['received_pt_panel'] == "yes") ? 'selected' : ''),
                array("value" => "no", "show" => "No", "selected" => ($shipment['received_pt_panel'] == "no") ? 'selected' : ''),
            );
            $section3['data']['no']['note'][]               = "Viral Load must be entered in log<sub>10</sub> copies/ml. There's a conversion calculator (from cp/mL to log) below. Please use if needed.";
            $section3['data']['no']['note'][]               = "Please provide numerical results (such as: 0.00 to 7.00 log<sub>10</sub> copies/ml).";
            $section3['data']['no']['note'][]               = "For negative or undetectable result (TND), please enter 0.00.";
            $section3['data']['no']['note'][]               = "For result value that is &lt;LOD, please enter the value of assay LOD (such as 1.6 for &lt;40 copies/mL) and provide “&lt;40 copies/mL” under comment section.";
            $section3['data']['no']['vlResultSectionLabel'] = "VL Calc (Convert copies/ml to Log<sub>10</sub>)";
            $section3['data']['no']['tableSection'][]       = 'Control/Sample';
            $section3['data']['no']['tableSection'][]       = 'Viral Load (log<sub>10</sub> copies/ml)';
            $section3['data']['no']['tableSection'][]       = 'TND(Target Not Detected)';
            $allNotTestedArray = [];
            foreach ($allNotTestedReason as $reason) {
                $allNotTestedArray[] = array(
                    'value'     => (string) $reason['ntr_id'],
                    'show'      => ucwords($reason['ntr_reason']),
                    'selected'  => ($shipment['vl_not_tested_reason'] == $reason['ntr_id']) ? 'selected' : ''
                );
            }
            $section3['data']['yes']['vlNotTestedReasonText']       = 'Reason for not testing the PT Panel';
            $section3['data']['yes']['vlNotTestedReasonSelect']     = $allNotTestedArray;
            $section3['data']['yes']['vlNotTestedReasonSelected']   = (isset($shipment['vl_not_tested_reason']) && $shipment['vl_not_tested_reason'] != "") ? $shipment['vl_not_tested_reason'] : '';
            $section3['data']['yes']['commentsText']                = 'Your comments';
            $section3['data']['yes']['commentsTextArea']            = $shipment['pt_test_not_performed_comments'];
            $section3['data']['yes']['supportText']                 = 'Do you need any support from the PT Provider ?';
            $section3['data']['yes']['supportTextArea']             = $shipment['pt_support_comments'];
            // return $allSamples;
            // Zend_Debug::dump($allSamples);die;
            foreach ($allSamples as $key => $sample) {
                if (isset($shipment['is_pt_test_not_performed']) && $shipment['is_pt_test_not_performed'] == 'yes') {
                    $sample['mandatory'] = 0;
                }
                $vlArray = array('yes', 'no');
                $vlResult = (isset($sample['reported_viral_load']) && $sample['reported_viral_load'] != "") ? $sample['reported_viral_load'] : '';
                if ($sample['is_tnd'] == 'yes') {
                    $vlResult = 0.00;
                }
                $vlResponseArr = [];
                foreach ($vlArray as $row) {
                    $vlResponseArr[] = array('value' => (string) $row, 'show' => ucwords($row), 'selected' => ($sample['is_tnd'] == $row || ($sample['is_tnd'] == '' && $row == 'no')) ? 'selected' : '');
                }
                $section3['data']['no']['tableRowTxt']['label'][]       = $sample['sample_label'];
                $section3['data']['no']['tableRowTxt']['id'][]          = $sample['sample_id'];
                $section3['data']['no']['tableRowTxt']['mandatory'][]   = ($sample['mandatory'] == 1) ? true : false;
                $section3['data']['no']['vlResult'][]                   = $vlResult;
                $section3['data']['no']['tndReferenceRadio'][]          = $vlResponseArr;
                $section3['data']['no']['tndReferenceRadioSelected'][]  = (isset($sample['is_tnd']) && ($sample['is_tnd'] != '' && $sample['is_tnd'] == 'yes')) ? 'yes' : 'no';
            }
            $vl['Section3'] = $section3;
            // Section 3 end // Section 4 Start
            $reviewArray = [];
            $commentArray = array('yes', 'no');
            $revieArr = [];
            foreach ($commentArray as $row) {
                $revieArr[] = array('value' => (string) $row, 'show' => ucwords($row), 'selected' => (isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] == $row || (($shipment['supervisor_approval'] != null || $shipment['supervisor_approval'] != '') && $row == 'yes')) ? 'selected' : '');
            }
            $reviewArray['supervisorReview']            = $revieArr;
            $reviewArray['supervisorReviewSelected']    = (isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] != '') ? $shipment['supervisor_approval'] : '';
            $reviewArray['approvalLabel']               = 'Supervisor Name';
            $reviewArray['approvalInputText']           = (isset($shipment['participant_supervisor']) && $shipment['participant_supervisor'] != '') ? $shipment['participant_supervisor'] : '';
            $reviewArray['comments']                    = (isset($shipment['user_comment']) && $shipment['user_comment'] != '') ? $shipment['user_comment'] : '';
            $vl['Section4']['status']                   = true;
            $vl['Section4']['data']                     = $reviewArray;
            // Section 4 End
            $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
            $customField1 = $globalConfigDb->getValue('custom_field_1');
            $customField2 = $globalConfigDb->getValue('custom_field_2');
            $haveCustom = $globalConfigDb->getValue('custom_field_needed');
            if (isset($haveCustom) && $haveCustom != 'no') {
                $vl['customFields']['status'] = true;
                if (isset($customField1) && trim($customField1) != "") {
                    $vl['customFields']['data']['customField1Text'] = $customField1;
                    $vl['customFields']['data']['customField1Val'] = (isset($shipment['custom_field_1']) && $shipment['custom_field_1'] != "") ? $shipment['custom_field_1'] : '';
                }

                if (isset($customField2) && trim($customField2) != "") {
                    $vl['customFields']['data']['customField2Text'] = $customField2;
                    $vl['customFields']['data']['customField2Val'] = (isset($shipment['custom_field_2']) && $shipment['custom_field_2'] != "") ? $shipment['custom_field_2'] : '';
                }
            } else {
                $vl['customFields']['status'] = false;
            }
            return $vl;
        }
        if ($params['scheme_type'] == 'eid') {
            $eid = [];
            $extractionAssay = $schemeService->getEidExtractionAssay();
            $detectionAssay = $schemeService->getEidDetectionAssay();
            $participant = $participantDb->getParticipant($params['participant_id']);
            $eidPossibleResults = $schemeService->getPossibleResults('eid');
            // return $eidPossibleResults;
            $reportAccess = [];
            $vl = [];
            if ($isEditable && $dm['view_only_access'] != 'yes') {
                if ($responseAccess == 1 && $shipment['status'] == 'finalized') {
                    $reportAccess['status']         = 'fail';
                    $reportAccess['message']        = 'Your response is late and this shipment has been finalized. Your result will not be evaluated';
                } elseif ($responseAccess == 1 && $params['response_switch'] == 'on') {
                    $reportAccess['status'] = 'success';
                    $reportAccess['message'] = 'Your response is late';
                } elseif ($responseAccess == 1) {
                    $reportAccess['status'] = 'fail';
                    $reportAccess['message'] = 'Your response is late';
                } elseif ($shipment['status'] == 'finalized') {
                    $reportAccess['status']         = 'fail';
                    $reportAccess['message']        = 'This shipment has been finalized. Your result will not be evaluated. Please contact your PT Provider for any clarifications';
                } else {
                    $reportAccess['status']         = 'success';
                }
            } else {
                $reportAccess['status'] = 'fail';
                $reportAccess['message'] = 'Responding for this shipment is not allowed at this time. Please contact your PT Provider for any clarifications.';
            }
            $eid['access'] = $reportAccess;
            // Section 1 start
            $labDirectorName = (isset($shipment['lab_director_name']) && $shipment['lab_director_name'] != "") ? $shipment['lab_director_name'] : $participant['lab_director_name'];
            $labDirectorEmail = (isset($shipment['lab_director_email']) && $shipment['lab_director_email'] != "") ? $shipment['lab_director_email'] : $participant['lab_director_email'];
            $contactPersonName = (isset($shipment['contact_person_name']) && $shipment['contact_person_name'] != "") ? $shipment['contact_person_name'] : $participant['contact_person_name'];
            $contactPersonEmail = (isset($shipment['contact_person_email']) && $shipment['contact_person_email'] != "") ? $shipment['contact_person_email'] : $participant['contact_person_email'];
            $contactPersonTelephone = (isset($shipment['contact_person_telephone']) && $shipment['contact_person_telephone'] != "") ? $shipment['contact_person_telephone'] : $participant['contact_person_telephone'];
            $section1 = array(
                'participantName'           => ((isset($participant['first_name']) && $participant['first_name'] != '') ? $participant['first_name'] : '') . ((isset($participant['last_name']) && $participant['last_name'] != '') ? ' ' . $participant['last_name'] : ''),
                'participantCode'           => (isset($participant['unique_identifier']) && $participant['unique_identifier'] != '') ? $participant['unique_identifier'] : '',
                'affiliation'               => (isset($participant['affiliation']) && $participant['affiliation'] != '') ? $participant['affiliation'] : '',
                'phone'                     => (isset($participant['phone']) && $participant['phone'] != '') ? $participant['phone'] : '',
                'mobile'                    => (isset($participant['mobile']) && $participant['mobile'] != '') ? $participant['mobile'] : '',
                'laboratoryName'            => (isset($participant['first_name']) && $participant['first_name'] != '') ? $participant['first_name'] . $participant['last_name'] : '',
                'laboratoryId'              => (isset($participant['unique_identifier']) && $participant['unique_identifier'] != '') ? $participant['unique_identifier'] : '',
                'labDirectorName'           => (isset($labDirectorName) && $labDirectorName != '') ? $labDirectorName : '',
                'labDirectorEmail'          => (isset($labDirectorEmail) && $labDirectorEmail != '') ? $labDirectorEmail : '',
                'contactPersonName'         => (isset($contactPersonName) && $contactPersonName != '') ? $contactPersonName : '',
                'contactPersonEmail'        => (isset($contactPersonEmail) && $contactPersonEmail != '') ? $contactPersonEmail : '',
                'contactPersonTelephone'    => (isset($contactPersonTelephone) && $contactPersonTelephone != '') ? $contactPersonTelephone : '',
            );
            if (isset($participant) && !empty($participant)) {
                $eid['Section1']['status'] = true;
                $eid['Section1']['data'] = $section1;
            } else {
                $eid['Section1']['status'] = false;
                $eid['Section1']['data'] = $section1;
            }
            // Section1 end // Section2 start
            $section2 = [];
            if (isset($shipment) && count($shipment) > 0) {
                $modeOfReceiptSelect = [];
                foreach ($modeOfReceipt as $receipt) {
                    $modeOfReceiptSelect[] = array(
                        'value'     =>  (string) $receipt['mode_id'],
                        'show'      =>  $receipt['mode_name'],
                        'selected'   => ($shipment["mode_id"] == $receipt['mode_id']) ? 'selected' : ''
                    );
                }
                $extractionAssaySelect = [];
                foreach ($extractionAssay as $eAssayId => $eAssayName) {
                    if (isset($eAssayName) && $eAssayName != "") {
                        $extractionAssaySelect[] = array(
                            'value'     =>  (string) $eAssayId,
                            'show'      =>  $eAssayName,
                            'selected'   => (isset($shipment['attributes']['extraction_assay']) && $shipment['attributes']['extraction_assay'] == $eAssayId) ? 'selected' : ''
                        );
                    }
                }
                $detectionAssaySelect = [];
                foreach ($detectionAssay as $dAssayId => $dAssayName) {
                    if (isset($dAssayName) && $dAssayName != "") {
                        $detectionAssaySelect[] = array(
                            'value'     =>  (string) $dAssayId,
                            'show'      =>  $dAssayName,
                            'selected'   => (isset($shipment['attributes']['detection_assay']) && $shipment['attributes']['detection_assay'] == $dAssayId) ? 'selected' : ''
                        );
                    }
                }

                $section2['status']    = true;
                $section2['data']['shipmentDate']               = date('d-M-Y', strtotime($shipment['shipment_date']));
                $section2['data']['resultDueDate']              = date('d-M-Y', strtotime($shipment['lastdate_response']));
                $section2['data']['testReceiptDate']            = (isset($shipment['shipment_receipt_date']) && $shipment['shipment_receipt_date'] != '' && $shipment['shipment_receipt_date'] != '0000:00:00') ? date('d-M-Y', strtotime($shipment['shipment_receipt_date'])) : '';
                $section2['data']['sampleRehydrationDate']      = (isset($shipment['attributes']["sample_rehydration_date"]) && $shipment['attributes']["sample_rehydration_date"] != '' && $shipment['attributes']["sample_rehydration_date"] != '0000:00:00') ? date('d-M-Y', strtotime($shipment['attributes']["sample_rehydration_date"])) : '';
                $section2['data']['testDate']                   = (isset($shipment["shipment_test_date"]) && $shipment["shipment_test_date"] != '' && $shipment["shipment_test_date"] != '0000-00-00') ? date('d-M-Y', strtotime($shipment["shipment_test_date"])) : '';
                $section2['data']['extractionAssaySelect']      = $extractionAssaySelect;
                $section2['data']['extractionAssaySelected']    = (isset($shipment['attributes']['extraction_assay']) && $shipment['attributes']['extraction_assay'] != "") ? (string) $shipment['attributes']['extraction_assay'] : '';
                $section2['data']['detectionAssaySelect']       = $detectionAssaySelect;
                $section2['data']['detectionAssaySelected']     = (isset($shipment['attributes']['detection_assay']) && $shipment['attributes']['detection_assay'] != "") ? (string) $shipment['attributes']['extraction_assay'] : '';
                $section2['data']['extractionLotNumber']        = (isset($shipment['attributes']['extraction_assay_lot_no']) && $shipment['attributes']['extraction_assay_lot_no'] != "") ? $shipment['attributes']['extraction_assay_lot_no'] : '';
                $section2['data']['detectionLotNumber']         = (isset($shipment['attributes']['detection_assay_lot_no']) && $shipment['attributes']['detection_assay_lot_no'] != "") ? $shipment['attributes']['detection_assay_lot_no'] : '';
                $section2['data']['extractionExpirationDate']   = (isset($shipment['attributes']['extraction_assay_expiry_date']) && $shipment['attributes']['extraction_assay_expiry_date'] != "" && $shipment['attributes']['extraction_assay_expiry_date'] != '0000:00:00') ? date('d-M-Y', strtotime($shipment['attributes']['extraction_assay_expiry_date'])) : '';
                $section2['data']['detectionExpirationDate']    = (isset($shipment['attributes']['detection_assay_expiry_date']) && $shipment['attributes']['detection_assay_expiry_date'] != '' && $shipment['attributes']['detection_assay_expiry_date'] != '0000:00:00') ? date('d-M-Y', strtotime($shipment['attributes']['detection_assay_expiry_date'])) : '';

                if ((isset($dm['enable_adding_test_response_date']) && $dm['enable_adding_test_response_date'] == 'yes') || (isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes')) {
                    if (isset($dm['enable_adding_test_response_date']) && $dm['enable_adding_test_response_date'] == 'yes' && isset($shipment['updated_on_user']) && $shipment['updated_on_user'] != '') {
                        $section2['data']['responseDate']        = (isset($shipment['shipment_test_report_date']) && $shipment['shipment_test_report_date'] != '' && $shipment['shipment_test_report_date'] != '0000-00-00') ? date('d-M-Y', strtotime($shipment['shipment_test_report_date'])) : date('d-M-Y');
                    } else {
                        $section2['data']['responseDate'] = null;
                    }
                    if (isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes') {
                        $section2['data']['modeOfReceiptSelected'] = (isset($shipment["mode_id"]) && $shipment["mode_id"] != "" && $shipment["mode_id"] != 0) ? $shipment["mode_id"] : '';
                    } else {
                        $section2['data']['modeOfReceiptSelected'] = '';
                    }
                } else {
                    $section2['data']['responseDate'] = null;
                    $section2['data']['modeOfReceiptSelected'] = '';
                }
                $section2['data']['modeOfReceiptSelect'] = $modeOfReceiptSelect;
            }

            $qcArray = array('yes', 'no');
            $qc = [];
            foreach ($qcArray as $row) {
                $qcResponseArr[] = array('value' => (string) $row, 'show' => ucwords($row), 'selected' => (isset($shipment['qc_done']) && $shipment['qc_done'] == $row || (($shipment['qc_done'] == null || $shipment['qc_done'] == '') && $row == 'no')) ? 'selected' : '');
            }
            $qc['qcRadio']          = $qcResponseArr;
            $qc['qcRadioSelected']  = (isset($shipment['qc_done']) && $shipment['qc_done'] == "no" || $shipment['qc_done'] == null || $shipment['qc_done'] == '') ? 'no' : 'yes';
            $qc['qcDate']           = (isset($shipment['qc_date']) && $shipment['qc_date'] != '' && $shipment['qc_date'] != '0000-00-00' && $shipment['qc_date'] != null && $shipment['qc_date'] != '1969-12-31') ? date('d-M-Y', strtotime($shipment['qc_date'])) : '';
            $qc['qcDoneBy']         = (isset($shipment['qc_done_by']) && $shipment['qc_done_by'] != '') ? $shipment['qc_done_by'] : '';
            if ($globalQcAccess != 'yes' || $dm['qc_access'] != 'yes') {
                $qc['status'] = false;
            } else {
                $qc['status'] = true;
            }
            $section2['data']['qcData'] = $qc;

            $eid['Section2'] = $section2;
            // Section 2 end // Section 3 start
            $allNotTestedReason = $schemeService->getNotTestedReasons('eid');

            $allSamplesResult = [];
            foreach ($allSamples as $sample) {
                if (isset($shipment['is_pt_test_not_performed']) && $shipment['is_pt_test_not_performed'] == 'yes') {
                    $sample['mandatory'] = 0;
                }
                $allSamplesResult['samples']['label'][]         = $sample['sample_label'];
                $allSamplesResult['samples']['id'][]            = $sample['sample_id'];
                $allSamplesResult['samples']['mandatory'][]     = ($sample['mandatory'] == 1) ? true : false;
                $allSamplesResult['samples']['yourResults'][]   = (isset($sample['reported_result']) && $sample['reported_result'] != "") ? $sample['reported_result'] : '';
                $allSamplesResult['samples']['hivCtOd'][]       = (isset($sample['hiv_ct_od']) && $sample['hiv_ct_od'] != '') ? $sample['hiv_ct_od'] : '';
                $allSamplesResult['samples']['IcQsValues'][]    = (isset($sample['ic_qs']) && $sample['ic_qs'] != '') ? $sample['ic_qs'] : '';
                $possibleEIDResults = [];
                foreach ($eidPossibleResults as $pr) {
                    if ($pr['scheme_sub_group'] == 'EID_FINAL') {
                        $possibleEIDResults[] = array('value' => (string) $pr['id'], 'show' => $pr['response'], 'resultCode' => $pr['result_code'], 'selected' => ($sample['reported_result'] == $pr['id']) ? 'selected' : '');
                    }
                }

                $allSamplesResult['resultsText'] = array('Control/Sample', 'Your-Results', 'HIV-CT/OD', 'IC/QS-Values');
                $allSamplesResult['resultStatus'] = array(true, true, true, true);
                $allSamplesResult['sampleSelected'][$sample['sample_label']]['Your-Results']   = (isset($sample['reported_result']) && $sample['reported_result'] != "") ? $sample['reported_result'] : '';
                $allSamplesResult['sampleSelected'][$sample['sample_label']]['HIV-CT/OD']      = (isset($sample['hiv_ct_od']) && $sample['hiv_ct_od'] != '') ? $sample['hiv_ct_od'] : '';
                $allSamplesResult['sampleSelected'][$sample['sample_label']]['IC/QS-Values']   = (isset($sample['ic_qs']) && $sample['ic_qs'] != '') ? $sample['ic_qs'] : '';
                $allSamplesResult['samplesList'][$sample['sample_label']]['Your-Results']      = $possibleEIDResults;
                $allSamplesResult['samplesList'][$sample['sample_label']]['HIV-CT/OD']         = (isset($sample['hiv_ct_od']) && $sample['hiv_ct_od'] != '') ? $sample['hiv_ct_od'] : '';
                $allSamplesResult['samplesList'][$sample['sample_label']]['IC/QS-Values']      = (isset($sample['ic_qs']) && $sample['ic_qs'] != '') ? $sample['ic_qs'] : '';
            }
            // return $eidPossibleResults;
            $allNotTestedArray = [];
            foreach ($allNotTestedReason as $reason) {
                $allNotTestedArray[] = array(
                    'value'             => (string) $reason['ntr_id'],
                    'show'              => ucwords($reason['ntr_reason']),
                    'receivedPtPanel'   => (string) $reason['collect_panel_receipt_date'],
                    'selected'          => ($shipment['vl_not_tested_reason'] == $reason['ntr_id']) ? 'selected' : ''
                );
            }

            if ((isset($allSamples) && count($allSamples) > 0) && (isset($eidPossibleResults) && count($eidPossibleResults) > 0)) {
                $eid['Section3']['status'] = true;
            } else {
                $eid['Section3']['status'] = false;
            }
            $eid['Section3']['data'] = $allSamplesResult;
            if ((!isset($shipment['is_pt_test_not_performed']) || isset($shipment['is_pt_test_not_performed'])) && ($shipment['is_pt_test_not_performed'] == 'no' || $shipment['is_pt_test_not_performed'] == '')) {
                $eid['Section3']['data']['isPtTestNotPerformedRadio'] = 'no';
            } else {
                $eid['Section3']['data']['isPtTestNotPerformedRadio'] = 'yes';
            }
            $eid['Section3']['data']['receivedPtPanel']         = (isset($shipment['received_pt_panel']) && $shipment['received_pt_panel'] != "") ? $shipment['received_pt_panel'] : "";
            $eid['Section3']['data']['receivedPtPanelSelect']   = array(
                array("value" => "yes", "show" => "Yes", "selected" => ($shipment['received_pt_panel'] == "yes") ? 'selected' : ''),
                array("value" => "no", "show" => "No", "selected" => ($shipment['received_pt_panel'] == "no") ? 'selected' : ''),
            );
            $eid['Section3']['data']['vlNotTestedReasonText']       = 'Reason for not testing the PT Panel';
            $eid['Section3']['data']['vlNotTestedReason']           = $allNotTestedArray;
            $eid['Section3']['data']['vlNotTestedReasonSelected']   = (isset($shipment['vl_not_tested_reason']) && $shipment['vl_not_tested_reason'] != "") ? $shipment['vl_not_tested_reason'] : "";
            $eid['Section3']['data']['ptNotTestedCommentsText']     = 'Your comments';
            $eid['Section3']['data']['ptNotTestedComments']         = (isset($shipment['pt_test_not_performed_comments']) && $shipment['pt_test_not_performed_comments'] != '') ? $shipment['pt_test_not_performed_comments'] : '';
            $eid['Section3']['data']['ptSupportCommentsText']       = 'Do you need any support from the PT Provider ?';
            $eid['Section3']['data']['ptSupportComments']           = (isset($shipment['pt_support_comments']) && $shipment['pt_support_comments'] != '') ? $shipment['pt_support_comments'] : '';
            // Section 3 End // Section 4 Start
            $reviewArray = [];
            $commentArray = array('yes', 'no');
            $revieArr = [];
            foreach ($commentArray as $row) {
                $revieArr[] = array('value' => (string) $row, 'show' => ucwords($row), 'selected' => (isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] == $row || (($shipment['supervisor_approval'] != null || $shipment['supervisor_approval'] != '') && $row == 'yes')) ? 'selected' : '');
            }
            $reviewArray['supervisorReview']            = $revieArr;
            $reviewArray['supervisorReviewSelected']    = (isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] != '') ? $shipment['supervisor_approval'] : '';
            $reviewArray['approvalLabel']               = 'Supervisor Name';
            $reviewArray['approvalInputText']           = (isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] == 'yes') ? $shipment['participant_supervisor'] : '';
            $reviewArray['comments']                    = (isset($shipment['user_comment']) && $shipment['user_comment'] != '') ? $shipment['user_comment'] : '';
            $eid['Section4']['status']                  = true;
            $eid['Section4']['data']                    = $reviewArray;
            // Section 4 end
            $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
            $customField1 = $globalConfigDb->getValue('custom_field_1');
            $customField2 = $globalConfigDb->getValue('custom_field_2');
            $haveCustom = $globalConfigDb->getValue('custom_field_needed');
            if (isset($haveCustom) && $haveCustom != 'no') {
                $eid['customFields']['status'] = true;
                if (isset($customField1) && trim($customField1) != "") {
                    $eid['customFields']['data']['customField1Text'] = $customField1;
                    $eid['customFields']['data']['customField1Val'] = (isset($shipment['custom_field_1']) && $shipment['custom_field_1'] != "") ? $shipment['custom_field_1'] : '';
                }

                if (isset($customField2) && trim($customField2) != "") {
                    $eid['customFields']['data']['customField2Text'] = $customField2;
                    $eid['customFields']['data']['customField2Val'] = (isset($shipment['custom_field_2']) && $shipment['custom_field_2'] != "") ? $shipment['custom_field_2'] : '';
                }
            } else {
                $eid['customFields']['status'] = false;
            }
            return $eid;
        }
        if ($params['scheme_type'] == 'recency') {
            $recency = [];
            $participant = $participantDb->getParticipant($params['participant_id']);
            $recencyPossibleResults = $schemeService->getPossibleResults('recency');
            $recencyAssay = $schemeService->getRecencyAssay();

            $reportAccess = [];
            $vl = [];

            if ($isEditable && $dm['view_only_access'] != 'yes') {
                if ($responseAccess == 1 && $shipment['status'] == 'finalized') {
                    $reportAccess['status']         = 'fail';
                    $reportAccess['message']        = 'Your response is late and this shipment has been finalized. Your result will not be evaluated';
                } elseif ($responseAccess == 1 && $params['response_switch'] == 'on') {
                    $reportAccess['status'] = 'success';
                    $reportAccess['message'] = 'Your response is late';
                } elseif ($responseAccess == 1) {
                    $reportAccess['status'] = 'fail';
                    $reportAccess['message'] = 'Your response is late';
                } elseif ($shipment['status'] == 'finalized') {
                    $reportAccess['status']         = 'fail';
                    $reportAccess['message']        = 'This shipment has been finalized. Your result will not be evaluated. Please contact your PT Provider for any clarifications.';
                } else {
                    $reportAccess['status']         = 'success';
                }
            } else {
                $reportAccess['status'] = 'fail';
                $reportAccess['message'] = 'Responding for this shipment is not allowed at this time. Please contact your PT Provider for any clarifications.';
            }
            $recency['access'] = $reportAccess;
            // Section 1 start
            $section1 = array(
                'participantName'   => ((isset($participant['first_name']) && $participant['first_name'] != '') ? $participant['first_name'] : '') . ((isset($participant['last_name']) && $participant['last_name'] != '') ? ' ' . $participant['last_name'] : ''),
                'participantCode'   => (isset($participant['unique_identifier']) && $participant['unique_identifier'] != '') ? $participant['unique_identifier'] : '',
                'affiliation'       => (isset($participant['affiliation']) && $participant['affiliation'] != '') ? $participant['affiliation'] : '',
                'phone'             => (isset($participant['phone']) && $participant['phone'] != '') ? $participant['phone'] : '',
                'mobile'            => (isset($participant['mobile']) && $participant['mobile'] != '') ? $participant['mobile'] : ''
            );
            if (isset($participant) && !empty($participant)) {
                $recency['Section1']['status'] = true;
                $recency['Section1']['data'] = $section1;
            } else {
                $recency['Section1']['status'] = false;
                $recency['Section1']['data'] = $section1;
            }
            // Section1 end // Section2 start
            $section2 = [];
            if (isset($shipment) && count($shipment) > 0) {
                $modeOfReceiptSelect = [];
                foreach ($modeOfReceipt as $receipt) {
                    $modeOfReceiptSelect[] = array(
                        'value'     =>  (string) $receipt['mode_id'],
                        'show'      =>  $receipt['mode_name'],
                        'selected'   => ($shipment["mode_id"] == $receipt['mode_id']) ? 'selected' : ''
                    );
                }
                $recencyAssaySelect = [];
                foreach ($recencyAssay as $eAssayId => $eAssayName) {
                    $recencyAssaySelect[] = array(
                        'value'     =>  (string) $eAssayId,
                        'show'      =>  $eAssayName,
                        'selected'   => (isset($shipment['attributes']['recency_assay']) && $shipment['attributes']['recency_assay'] == $eAssayId) ? 'selected' : ''
                    );
                }

                $section2['status']    = true;
                $section2['data']['shipmentDate']               = date('d-M-Y', strtotime($shipment['shipment_date']));
                $section2['data']['resultDueDate']              = date('d-M-Y', strtotime($shipment['lastdate_response']));
                $section2['data']['testReceiptDate']            = (isset($shipment['shipment_receipt_date']) && $shipment['shipment_receipt_date'] != '' && $shipment['shipment_receipt_date'] != '0000:00:00') ? date('d-M-Y', strtotime($shipment['shipment_receipt_date'])) : '';
                $section2['data']['sampleRehydrationDate']      = (isset($shipment['attributes']["sample_rehydration_date"]) && $shipment['attributes']["sample_rehydration_date"] != '' && $shipment['attributes']["sample_rehydration_date"] != '0000:00:00') ? date('d-M-Y', strtotime($shipment['attributes']["sample_rehydration_date"])) : '';
                $section2['data']['testDate']                   = (isset($shipment["shipment_test_date"]) && $shipment["shipment_test_date"] != '' && $shipment["shipment_test_date"] != '0000-00-00') ? date('d-M-Y', strtotime($shipment["shipment_test_date"])) : '';
                $section2['data']['recencyAssaySelect']         = $recencyAssaySelect;
                $section2['data']['recencyAssaySelected']       = (isset($shipment['attributes']['recency_assay']) && $shipment['attributes']['recency_assay'] != "") ? (string) $shipment['attributes']['recency_assay'] : '';
                $section2['data']['recencyAssayLotNumber']        = (isset($shipment['attributes']['recency_assay_lot_no']) && $shipment['attributes']['recency_assay_lot_no'] != "") ? $shipment['attributes']['recency_assay_lot_no'] : '';
                $section2['data']['recencyAssayExpirayDate']   = (isset($shipment['attributes']['recency_assay_expiry_date']) && $shipment['attributes']['recency_assay_expiry_date'] != "" && $shipment['attributes']['recency_assay_expiry_date'] != '0000:00:00') ? date('d-M-Y', strtotime($shipment['attributes']['recency_assay_expiry_date'])) : '';

                if ((isset($dm['enable_adding_test_response_date']) && $dm['enable_adding_test_response_date'] == 'yes') || (isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes')) {
                    if (isset($dm['enable_adding_test_response_date']) && $dm['enable_adding_test_response_date'] == 'yes' && isset($shipment['updated_on_user']) && $shipment['updated_on_user'] != '') {
                        $section2['data']['responseDate']        = (isset($shipment['shipment_test_report_date']) && $shipment['shipment_test_report_date'] != '' && $shipment['shipment_test_report_date'] != '0000-00-00') ? date('d-M-Y', strtotime($shipment['shipment_test_report_date'])) : date('d-M-Y');
                    } else {
                        $section2['data']['responseDate'] = null;
                    }
                    if (isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes') {
                        $section2['data']['modeOfReceiptSelected'] = (isset($shipment["mode_id"]) && $shipment["mode_id"] != "" && $shipment["mode_id"] != 0) ? $shipment["mode_id"] : '';
                    } else {
                        $section2['data']['modeOfReceiptSelected'] = '';
                    }
                } else {
                    $section2['data']['responseDate'] = '';
                    $section2['data']['modeOfReceiptSelected'] = '';
                }
                $section2['data']['modeOfReceiptSelect'] = $modeOfReceiptSelect;
            }

            $qcArray = array('yes', 'no');
            $qc = [];
            foreach ($qcArray as $row) {
                $qcResponseArr[] = array('value' => (string) $row, 'show' => ucwords($row), 'selected' => (isset($shipment['qc_done']) && $shipment['qc_done'] == $row || (($shipment['qc_done'] == null || $shipment['qc_done'] == '') && $row == 'no')) ? 'selected' : '');
            }
            $qc['qcRadio']          = $qcResponseArr;
            $qc['qcRadioSelected']  = (isset($shipment['qc_done']) && $shipment['qc_done'] == "no" || $shipment['qc_done'] == null || $shipment['qc_done'] == '') ? 'no' : 'yes';
            $qc['qcDate']           = (isset($shipment['qc_date']) && $shipment['qc_date'] != '' && $shipment['qc_date'] != '0000-00-00' && $shipment['qc_date'] != null && $shipment['qc_date'] != '1969-12-31') ? date('d-M-Y', strtotime($shipment['qc_date'])) : '';
            $qc['qcDoneBy']         = (isset($shipment['qc_done_by']) && $shipment['qc_done_by'] != '') ? $shipment['qc_done_by'] : '';
            if ($globalQcAccess != 'yes' || $dm['qc_access'] != 'yes') {
                $qc['status'] = false;
            } else {
                $qc['status'] = true;
            }
            $section2['data']['qcData'] = $qc;

            $recency['Section2'] = $section2;
            // Section 2 end // Section 3 start
            $allNotTestedReason = $schemeService->getNotTestedReasons('recency');

            $allSamplesResult = [];
            foreach ($allSamples as $sample) {
                if (isset($shipment['is_pt_test_not_performed']) && $shipment['is_pt_test_not_performed'] == 'yes') {
                    $sample['mandatory'] = 0;
                }
                $recency['Section3']['data']['samples']['label'][]             = $sample['sample_label'];
                $recency['Section3']['data']['samples']['id'][]                = $sample['sample_id'];
                $recency['Section3']['data']['samples']['mandatory'][]         = ($sample['mandatory'] == 1) ? true : false;
                $recency['Section3']['data']['samples']['controlLine'][]       = (isset($sample['control_line']) && $sample['control_line'] != "") ? $sample['control_line'] : '';
                $recency['Section3']['data']['samples']['verificationLine'][]     = (isset($sample['diagnosis_line']) && $sample['diagnosis_line'] != '') ? $sample['diagnosis_line'] : '';
                $recency['Section3']['data']['samples']['longtermLine'][]      = (isset($sample['longterm_line']) && $sample['longterm_line'] != '') ? $sample['longterm_line'] : '';
                $recency['Section3']['data']['samples']['yourResults'][]       = (isset($sample['reported_result']) && $sample['reported_result'] != '') ? $sample['reported_result'] : '';

                $possibleRecencyResults = [];
                foreach ($recencyPossibleResults as $pr) {
                    if ($pr['scheme_sub_group'] == 'RECENCY_FINAL') {
                        $possibleRecencyResults[] = array('value' => (string) $pr['id'], 'show' => $pr['response'], 'resultCode' => $pr['result_code'], 'selected' => ($sample['reported_result'] == $pr['id']) ? 'selected' : '');
                    }
                }
                $possibleRecencyResults[] = array('value' => '', 'show' => '', 'resultCode' => '', 'selected' => '');

                $ctlLineResults = [];
                $verifyLineResults = [];
                $longLineResults = [];
                $resultArray = array('present', 'absent');
                foreach ($resultArray as $pr) {
                    $ctlLineResults[] = array('value' => (string) $pr, 'show' => ucwords($pr), 'selected' => ($sample['control_line'] == $pr) ? 'selected' : '');
                }
                foreach ($resultArray as $pr) {
                    $verifyLineResults[] = array('value' => (string) $pr, 'show' => ucwords($pr), 'selected' => ($sample['diagnosis_line'] == $pr) ? 'selected' : '');
                }
                foreach ($resultArray as $pr) {
                    $longLineResults[] = array('value' => (string) $pr, 'show' => ucwords($pr), 'selected' => ($sample['longterm_line'] == $pr) ? 'selected' : '');
                }

                $recency['Section3']['data']['resultsText'] = array('Control/Sample', 'Control Line', 'Verification Line', 'Longterm Line', 'Your Result');
                $recency['Section3']['data']['resultStatus'] = array(true, true, true, true);
                $recency['Section3']['data']['sampleSelected'][$sample['sample_label']]['Control Line']     = (isset($sample['control_line']) && $sample['control_line'] != '') ? $sample['control_line'] : '';
                $recency['Section3']['data']['sampleSelected'][$sample['sample_label']]['Verification Line'] = (isset($sample['diagnosis_line']) && $sample['diagnosis_line'] != '') ? $sample['diagnosis_line'] : '';
                $recency['Section3']['data']['sampleSelected'][$sample['sample_label']]['Longterm Line']    = (isset($sample['longterm_line']) && $sample['longterm_line'] != '') ? $sample['longterm_line'] : '';
                $recency['Section3']['data']['sampleSelected'][$sample['sample_label']]['Your Results']     = (isset($sample['reported_result']) && $sample['reported_result'] != '') ? $sample['reported_result'] : '';
                $recency['Section3']['data']['samplesList'][$sample['sample_label']]['Control Line']        = $ctlLineResults;
                $recency['Section3']['data']['samplesList'][$sample['sample_label']]['Verification Line']   = $verifyLineResults;
                $recency['Section3']['data']['samplesList'][$sample['sample_label']]['Longterm Line']       = $longLineResults;
                $recency['Section3']['data']['samplesList'][$sample['sample_label']]['Your Result']        = $possibleRecencyResults;
            }

            $allNotTestedArray = [];
            foreach ($allNotTestedReason as $reason) {
                $allNotTestedArray[] = array(
                    'value'     => (string) $reason['ntr_id'],
                    'show'      => ucwords($reason['ntr_reason']),
                    'selected'  => ($shipment['vl_not_tested_reason'] == $reason['ntr_id']) ? 'selected' : ''
                );
            }

            if (isset($allSamples) && count($allSamples) > 0) {
                $recency['Section3']['status'] = true;
            } else {
                $recency['Section3']['status'] = false;
            }

            if ((!isset($shipment['is_pt_test_not_performed']) || isset($shipment['is_pt_test_not_performed'])) && ($shipment['is_pt_test_not_performed'] == 'no' || $shipment['is_pt_test_not_performed'] == '')) {
                $recency['Section3']['data']['isPtTestNotPerformedRadio'] = 'no';
            } else {
                $recency['Section3']['data']['isPtTestNotPerformedRadio'] = 'yes';
            }
            $recency['Section3']['data']['vlNotTestedReasonText']       = 'Reason for not testing the PT Panel';
            $recency['Section3']['data']['vlNotTestedReason']           = $allNotTestedArray;
            $recency['Section3']['data']['vlNotTestedReasonSelected']   = (isset($shipment['vl_not_tested_reason']) && $shipment['vl_not_tested_reason'] != "") ? $shipment['vl_not_tested_reason'] : "";
            $recency['Section3']['data']['ptNotTestedCommentsText']     = 'Your comments';
            $recency['Section3']['data']['ptNotTestedComments']         = (isset($shipment['pt_test_not_performed_comments']) && $shipment['pt_test_not_performed_comments'] != '') ? $shipment['pt_test_not_performed_comments'] : '';
            $recency['Section3']['data']['ptSupportCommentsText']       = 'Do you need any support from the PT Provider ?';
            $recency['Section3']['data']['ptSupportComments']           = (isset($shipment['pt_support_comments']) && $shipment['pt_support_comments'] != '') ? $shipment['pt_support_comments'] : '';
            // Section 3 End // Section 4 Start
            $reviewArray = [];
            $commentArray = array('yes', 'no');
            $revieArr = [];
            foreach ($commentArray as $row) {
                $revieArr[] = array('value' => (string) $row, 'show' => ucwords($row), 'selected' => (isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] == $row || (($shipment['supervisor_approval'] != null || $shipment['supervisor_approval'] != '') && $row == 'yes')) ? 'selected' : '');
            }
            $reviewArray['supervisorReview']            = $revieArr;
            $reviewArray['supervisorReviewSelected']    = (isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] != '') ? $shipment['supervisor_approval'] : '';
            $reviewArray['approvalLabel']               = 'Supervisor Name';
            $reviewArray['approvalInputText']           = (isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] == 'yes') ? $shipment['participant_supervisor'] : '';
            $reviewArray['comments']                    = (isset($shipment['user_comment']) && $shipment['user_comment'] != '') ? $shipment['user_comment'] : '';
            $recency['Section4']['status']                  = true;
            $recency['Section4']['data']                    = $reviewArray;
            // Section 4 end
            $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
            $customField1 = $globalConfigDb->getValue('custom_field_1');
            $customField2 = $globalConfigDb->getValue('custom_field_2');
            $haveCustom = $globalConfigDb->getValue('custom_field_needed');
            if (isset($haveCustom) && $haveCustom != 'no') {
                $recency['customFields']['status'] = true;
                if (isset($customField1) && trim($customField1) != "") {
                    $recency['customFields']['data']['customField1Text'] = $customField1;
                    $recency['customFields']['data']['customField1Val'] = (isset($shipment['custom_field_1']) && $shipment['custom_field_1'] != "") ? $shipment['custom_field_1'] : '';
                }

                if (isset($customField2) && trim($customField2) != "") {
                    $recency['customFields']['data']['customField2Text'] = $customField2;
                    $recency['customFields']['data']['customField2Val'] = (isset($shipment['custom_field_2']) && $shipment['custom_field_2'] != "") ? $shipment['custom_field_2'] : '';
                }
            } else {
                $recency['customFields']['status'] = false;
            }
            return $recency;
        }

        if ($params['scheme_type'] == 'covid19') {
            $covid19 = [];
            $testThreeOptional = false;
            $testTwoOptional = false;
            $testAllowed = $config->evaluation->covid19->covid19MaximumTestAllowed;
            if (isset($testAllowed) && ($testAllowed == '1' || $testAllowed == '2')) {
                $testThreeOptional = true;
            }

            if (isset($testAllowed) && $testAllowed != '3' && $testAllowed != '2') {
                $testTwoOptional = true;
            }

            $reportAccess = [];
            if ($isEditable && $dm['view_only_access'] != 'yes') {
                if ($responseAccess == 1 && $shipment['status'] == 'finalized') {
                    $reportAccess['status'] = 'fail';
                    $reportAccess['message'] = 'Your response is late and this shipment has been finalized. Your result will not be evaluated';
                } elseif ($responseAccess == 1 && $params['response_switch'] == 'on') {
                    $reportAccess['status'] = 'success';
                    $reportAccess['message'] = 'Your response is late';
                } elseif ($responseAccess == 1) {
                    $reportAccess['status'] = 'fail';
                    $reportAccess['message'] = 'Your response is late';
                } elseif ($shipment['status'] == 'finalized') {
                    $reportAccess['status'] = 'fail';
                    $reportAccess['message'] = 'This shipment has already been finalized. Your result will not be evaluated. Please contact your PT Provider for any clarifications';
                } else {
                    $reportAccess['status'] = 'success';
                }
            } else {
                $reportAccess['status']     = 'fail';
                $reportAccess['message']    = 'Responding for this shipment is not allowed at this time. Please contact your PT Provider for any clarifications.';
            }
            $covid19['access'] = $reportAccess;
            // Check the data manager having for access to the form
            $access = $participantDb->checkParticipantAccess($params['participant_id'], $params['dm_id'], 'API');
            if ($access == false) {
                return 'Participant does not having the shipments';
            }

            // Section 1 start // First participant details start
            if (isset($participant) && !empty($participant)) {
                $covid19['Section1']['status'] = true;
                $covid19['Section1']['data'] = array(
                    'participantName'   => $participant['first_name'] . ' ' . $participant['last_name'],
                    'participantCode'   => $participant['unique_identifier'],
                    'affiliation'       => $participant['affiliation'],
                    'phone'             => $participant['phone'],
                    'mobile'            => $participant['mobile']
                );
            } else {
                $covid19['Section1']['status'] = false;
            }
            // First participant details end // Section 1 end // Section 2 start // Shipement Result start
            $modeOfReceiptSelect = [];
            foreach ($modeOfReceipt as $receipt) {
                $modeOfReceiptSelect[] = array(
                    'value'     =>  (string) $receipt['mode_id'],
                    'show'      =>  $receipt['mode_name'],
                    'selected'  => ($shipment["mode_id"] == $receipt['mode_id']) ? 'selected' : ''
                );
            }

            if (isset($participant) && !empty($participant)) {
                $covid19['Section2']['status'] = true;
                $section2 = array(
                    'shipmentDate'              => date('d-M-Y', strtotime($shipment['shipment_date'])),
                    'resultDueDate'             => date('d-M-Y', strtotime($shipment['lastdate_response'])),
                    'testReceiptDate'           => (isset($shipment['shipment_receipt_date']) && $shipment['shipment_receipt_date'] != '' && $shipment['shipment_receipt_date'] != '0000:00:00') ? date('d-M-Y', strtotime($shipment['shipment_receipt_date'])) : '',
                    'testingDate'               => (isset($shipment['shipment_test_date']) && $shipment['shipment_test_date'] != '' && $shipment['shipment_test_date'] != '0000-00-00') ? date('d-M-Y', strtotime($shipment['shipment_test_date'])) : '',
                    // 'sampleType'                => (isset($shipment['shipment_attributes']["sampleType"]) && $shipment['shipment_attributes']["sampleType"] != '') ? $shipment['shipment_attributes']["sampleType"] : '',
                    // 'screeningTest'             => (isset($shipment['shipment_attributes']["screeningTest"]) && $shipment['shipment_attributes']["screeningTest"] != '') ? $shipment['shipment_attributes']["screeningTest"] : '',
                );
                // if ((isset($shipment['shipment_attributes']["sampleType"]) && $shipment['shipment_attributes']["sampleType"] != 'serum' && $shipment['shipment_attributes']["sampleType"] != 'plasma')) {
                $section2['sampleRehydrationDate'] = (isset($shipment['attributes']["sample_rehydration_date"]) && $shipment['attributes']["sample_rehydration_date"] != '' && $shipment['attributes']["sample_rehydration_date"] != '0000:00:00') ? date('d-M-Y', strtotime($shipment['attributes']["sample_rehydration_date"])) : '';
                // }
                $covid19['Section2']['data'] = $section2;
                if ((isset($dm['enable_adding_test_response_date']) && $dm['enable_adding_test_response_date'] == 'yes') || (isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes')) {
                    if (isset($dm['enable_adding_test_response_date']) && $dm['enable_adding_test_response_date'] == 'yes' && isset($shipment['updated_on_user']) && $shipment['updated_on_user'] != '') {
                        $covid19['Section2']['data']['responseDate']        = (isset($shipment['shipment_test_report_date']) && $shipment['shipment_test_report_date'] != '' && $shipment['shipment_test_report_date'] != '0000-00-00') ? date('d-M-Y', strtotime($shipment['shipment_test_report_date'])) : date('d-M-Y');
                    } else {
                        $covid19['Section2']['data']['responseDate'] = '';
                    }
                    if (isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes') {
                        $covid19['Section2']['data']['modeOfReceiptSelected']     = (isset($shipment["mode_id"]) && $shipment["mode_id"] != "" && $shipment["mode_id"] != 0) ? $shipment["mode_id"] : '';
                    } else {
                        $covid19['Section2']['data']['modeOfReceiptSelected']     = '';
                    }
                } else {
                    $covid19['Section2']['data']['responseDate']            = '';
                    $covid19['Section2']['data']['modeOfReceiptSelected']     = '';
                }
                $covid19['Section2']['data']['modeOfReceiptSelect'] = $modeOfReceiptSelect;
                $qcArray = array('yes', 'no');
                $qc = [];
                foreach ($qcArray as $row) {
                    $qcResponseArr[] = array('value' => $row, 'show' => ucwords($row), 'selected' => (isset($shipment['qc_done']) && $shipment['qc_done'] == $row || (($shipment['qc_done'] == null || $shipment['qc_done'] == '') && $row == 'no')) ? 'selected' : '');
                }
                $qc['qcRadio']          = $qcResponseArr;
                $qc['qcRadioSelected']  = (isset($shipment['qc_done']) && $shipment['qc_done'] == "no" || $shipment['qc_done'] == null || $shipment['qc_done'] == '') ? 'no' : 'yes';
                $qc['qcDate']   = (isset($shipment['qc_date']) && $shipment['qc_date'] != '' && $shipment['qc_date'] != '0000:00:00' && $shipment['qc_date'] != null && $shipment['qc_date'] != '1969-12-31') ? date('d-M-Y', strtotime($shipment['qc_date'])) : '';
                $qc['qcDoneBy'] = (isset($shipment['qc_done_by']) && $shipment['qc_done_by'] != '') ? $shipment['qc_done_by'] : '';
                if ($globalQcAccess != 'yes' || $dm['qc_access'] != 'yes') {
                    $qc['status']                       = false;
                    $covid19['Section2']['data']['qcData']  = $qc;
                } else {
                    $qc['status']                       = true;
                    $covid19['Section2']['data']['qcData']  = $qc;
                }

                if ($testAllowed > 1) {
                    foreach (range(1, $testAllowed) as $no) {
                        // $default = (isset($testAllowed) && $testAllowed == $no)?"selected":"";
                        $numberOfTestSelect[] = array(
                            'value'     => (int) $no,
                            'show'      => (int) $no,
                            'selected'  => ($shipment['number_of_tests'] == $no) ? 'selected' : ""
                        );
                    }
                } else {
                    $numberOfTestSelect[] = array(
                        'value'     => (int) 1,
                        'show'      => (int) 1,
                        'selected'  => 'selected'
                    );
                }
                $covid19['Section2']['data']['numberOfTestsSelected']        = $shipment['number_of_tests'];
                $covid19['Section2']['data']['numberOfTestsSelect']          = $numberOfTestSelect;
            } else {
                $covid19['Section2']['status'] = false;
            }
            // Section 2 end // Section 3 start
            if ((!isset($shipment['is_pt_test_not_performed']) || isset($shipment['is_pt_test_not_performed'])) && ($shipment['is_pt_test_not_performed'] == 'no' || $shipment['is_pt_test_not_performed'] == '')) {
                $covid19['Section3']['data']['isPtTestNotPerformedRadio'] = 'no';
            } else {
                $covid19['Section3']['data']['isPtTestNotPerformedRadio'] = 'yes';
            }
            $allNotTestedReason = $schemeService->getNotTestedReasons('covid19');
            $allNotTestedArray = [];
            foreach ($allNotTestedReason as $reason) {
                $allNotTestedArray[] = array(
                    'value'     => (string) $reason['ntr_id'],
                    'show'      => ucwords($reason['ntr_reason']),
                    'selected'  => ($shipment['vl_not_tested_reason'] == $reason['ntr_id']) ? 'selected' : ''
                );
            }

            $covid19['Section3']['data']['vlNotTestedReasonText']       = 'Reason for not testing the PT Panel';
            $covid19['Section3']['data']['vlNotTestedReason']           = $allNotTestedArray;
            $covid19['Section3']['data']['vlNotTestedReasonSelected']   = (isset($shipment['vl_not_tested_reason']) && $shipment['vl_not_tested_reason'] != "") ? $shipment['vl_not_tested_reason'] : "";
            $covid19['Section3']['data']['ptNotTestedCommentsText']     = 'Your comments';
            $covid19['Section3']['data']['ptNotTestedComments']         = (isset($shipment['pt_test_not_performed_comments']) && $shipment['pt_test_not_performed_comments'] != '') ? $shipment['pt_test_not_performed_comments'] : '';
            $covid19['Section3']['data']['ptSupportCommentsText']       = 'Do you need any support from the PT Provider ?';
            $covid19['Section3']['data']['ptSupportComments']           = (isset($shipment['pt_support_comments']) && $shipment['pt_support_comments'] != '') ? $shipment['pt_support_comments'] : '';
            $covid19['Section3']['status']                              = true;

            // Section 3 end // Section 4 Start
            $testPlatformArray = [];
            $allTestTypes = $schemeService->getAllCovid19TestTypeResponseWise(true);
            foreach ($allTestTypes as $testtype) {
                if ($testtype['test_type_1'] == '1') {
                    $testPlatformArray['testPlatformDropDown']['Test-1']['status'] = true;
                    $testPlatformArray['testPlatformDropDown']['Test-1']['data'][] = array(
                        'value'         => (string) $testtype['test_type_id'],
                        'show'          => $testtype['test_type_name'],
                        'selected'      => (isset($allSamples[0]["test_type_1"]) && $testtype['test_type_id'] == $allSamples[0]["test_type_1"]) ? 'selected' : ''
                    );
                    /* if(isset($allSamples[0]["test_type_1"]) && $testtype['test_type_id'] == $allSamples[0]["test_type_1"]){
                        $testPlatformArray['Test-1']['data'][] = array(
                            'testPlatformDropDown'   => $testtype['test_type_name'],
                            'typeValue'  => (string)$testtype['test_type_id']
                        );
                    } */
                }
                if ($testtype['test_type_1'] == '1' && isset($allSamples[0]["test_type_1"]) && $testtype['test_type_id'] == $allSamples[0]["test_type_1"]) {
                    $testPlatformArray['typeName'][0] = $testtype['test_type_name'];
                }

                if ($testtype['test_type_2'] == '1' && isset($allSamples[0]["test_type_2"]) && $testtype['test_type_id'] == $allSamples[0]["test_type_2"]) {
                    $testPlatformArray['typeName'][1] = $testtype['test_type_name'];
                }
                if ($testtype['test_type_3'] == '1' && isset($allSamples[0]["test_type_3"]) && $testtype['test_type_id'] == $allSamples[0]["test_type_3"]) {
                    $testPlatformArray['typeName'][2] = $testtype['test_type_name'];
                }

                if ($testtype['test_type_2'] == '1') {
                    // if (isset($shipment['shipment_attributes']["screeningTest"]) && $shipment['shipment_attributes']["screeningTest"] == 'no') {
                    if (!$testTwoOptional) {
                        $testPlatformArray['testPlatformDropDown']['Test-2']['status'] = true;
                    } else {
                        $testPlatformArray['testPlatformDropDown']['Test-2']['status'] = false;
                    }
                    $testPlatformArray['testPlatformDropDown']['Test-2']['data'][] = array(
                        'value'         => (string) $testtype['test_type_id'],
                        'show'          => $testtype['test_type_name'],
                        'selected'      => (isset($allSamples[0]["test_type_2"]) && $testtype['test_type_id'] == $allSamples[0]["test_type_2"]) ? 'selected' : ''
                    );
                }
                if (!$testThreeOptional) {
                    // if (isset($shipment['shipment_attributes']["screeningTest"]) && $shipment['shipment_attributes']["screeningTest"] == 'no') {
                    $testPlatformArray['testPlatformDropDown']['Test-3']['status'] = true;
                    /* } else {
                        $testPlatformArray['testPlatformDropDown']['Test-3']['status'] = false;
                    } */
                } else {
                    $testPlatformArray['testPlatformDropDown']['Test-3']['status'] = false;
                }
                if ($testtype['test_type_3'] == '1') {
                    $testPlatformArray['testPlatformDropDown']['Test-3']['data'][] = array(
                        'value'         => (string) $testtype['test_type_id'],
                        'show'          => $testtype['test_type_name'],
                        'selected'      => (isset($allSamples[0]["test_type_3"]) && $testtype['test_type_id'] == $allSamples[0]["test_type_3"]) ? 'selected' : ''
                    );
                }
            }
            if (!isset($testPlatformArray['typeName'][0])) {
                $testPlatformArray['typeName'][0] = '';
            }
            if (!isset($testPlatformArray['typeName'][1])) {
                $testPlatformArray['typeName'][1] = '';
            }
            if (!isset($testPlatformArray['typeName'][2])) {
                $testPlatformArray['typeName'][2] = '';
            }

            $testPlatformArray['typeText'] = array('Test-1', 'Test-2', 'Test-3');
            if (isset($allSamples) && count($allSamples) > 0) {
                $covid19['Section4']['status'] = true;
                $testPlatformArray['expDate'][0]  = (isset($allSamples[0]["exp_date_1"]) && trim($allSamples[0]["exp_date_1"]) != "" && $allSamples[0]["exp_date_1"] != "0000-00-00" && $allSamples[0]["exp_date_1"] != '1969-12-31') ? date('d-M-Y', strtotime($allSamples[0]["exp_date_1"])) : '';
                $testPlatformArray['expDate'][1]  = (isset($allSamples[0]["exp_date_2"]) && trim($allSamples[0]["exp_date_2"]) != "" && $allSamples[0]["exp_date_2"] != "0000-00-00" && $allSamples[0]["exp_date_2"] != '1969-12-31') ? date('d-M-Y', strtotime($allSamples[0]["exp_date_2"])) : '';
                $testPlatformArray['expDate'][2]  = (isset($allSamples[0]["exp_date_3"]) && trim($allSamples[0]["exp_date_2"]) != "" && $allSamples[0]["exp_date_3"] != "0000-00-00" && $allSamples[0]["exp_date_3"] != '1969-12-31') ? date('d-M-Y', strtotime($allSamples[0]["exp_date_3"])) : '';

                $testPlatformArray['typeValue'][0] = (isset($allSamples[0]["test_type_1"]) && trim($allSamples[0]["test_type_1"]) != "") ? $allSamples[0]["test_type_1"] : '';
                $testPlatformArray['typeValue'][1] = (isset($allSamples[0]["test_type_2"]) && trim($allSamples[0]["test_type_2"]) != "") ? $allSamples[0]["test_type_2"] : '';
                $testPlatformArray['typeValue'][2] = (isset($allSamples[0]["test_type_3"]) && trim($allSamples[0]["test_type_3"]) != "") ? $allSamples[0]["test_type_3"] : '';

                $testPlatformArray['lot'][0]      = (isset($allSamples[0]["lot_no_1"]) && trim($allSamples[0]["lot_no_1"]) != "") ? $allSamples[0]["lot_no_1"] : '';
                $testPlatformArray['lot'][1]      = (isset($allSamples[0]["lot_no_2"]) && trim($allSamples[0]["lot_no_2"]) != "") ? $allSamples[0]["lot_no_2"] : '';
                $testPlatformArray['lot'][2]      = (isset($allSamples[0]["lot_no_3"]) && trim($allSamples[0]["lot_no_3"]) != "") ? $allSamples[0]["lot_no_3"] : '';

                $testPlatformArray['typeOther']   = array('', '', '');
                if ($allSamples[0]["test_type_1"] == '') {
                    $testPlatformArray['typeName'][0] = '';
                }
                if ($allSamples[0]["test_type_2"] == '') {
                    $testPlatformArray['typeName'][1] = '';
                }
                if ($allSamples[0]["test_type_3"] == '') {
                    $testPlatformArray['typeName'][2] = '';
                }

                $testPlatformArray['testPlatformDropDown']['Test-1']['data'][]    = array(
                    'value'         => 'other',
                    'show'          => 'Other',
                    'selected'      => (isset($allSamples[0]["test_type_1"]) && 'other' == $allSamples[0]["test_type_1"]) ? 'selected' : ''
                );
                $testPlatformArray['testPlatformDropDown']['Test-2']['data'][] = array(
                    'value'         => 'other',
                    'show'          => 'Other',
                    'selected'      => (isset($allSamples[0]["test_type_2"]) && 'other' == $allSamples[0]["test_type_2"]) ? 'selected' : ''
                );
                $testPlatformArray['testPlatformDropDown']['Test-3']['data'][] = array(
                    'value'         => 'other',
                    'show'          => 'Other',
                    'selected'      => (isset($allSamples[0]["test_type_3"]) && 'other' == $allSamples[0]["test_type_3"]) ? 'selected' : ''
                );
                $covid19['Section4']['data']    = $testPlatformArray;
            } else {
                $covid19['Section4']['status']  = false;
            }
            // Zend_Debug::dump($allSamples);die;
            // Section 4 end // Section 5 Start
            $covid19PossibleResults = $schemeService->getPossibleResults('covid19');
            $covid19PossibleResponse['code'] =  array();
            $covid19PossibleResponse['result'] = [];
            foreach ($covid19PossibleResults as $row) {
                $covid19PossibleResponse['code'][$row['id']] = $row['result_code'];
                $covid19PossibleResponse['result'][$row['id']] = $row['response'];
            }

            $allSamplesResult = [];
            foreach ($allSamples as $sample) {
                $allSamplesResult['samples']['label'][]         = $sample['sample_label'];
                $allSamplesResult['samples']['id'][]            = $sample['sample_id'];

                $responseCode1 = (isset($covid19PossibleResponse['code'][$sample['test_result_1']]) && $covid19PossibleResponse['code'][$sample['test_result_1']] != '' && $covid19PossibleResponse['code'][$sample['test_result_1']] != null) ? $covid19PossibleResponse['code'][$sample['test_result_1']] : 'X';
                $responseCode2 = (isset($covid19PossibleResponse['code'][$sample['test_result_2']]) && $covid19PossibleResponse['code'][$sample['test_result_2']] != '' && $covid19PossibleResponse['code'][$sample['test_result_2']] != null) ? $covid19PossibleResponse['code'][$sample['test_result_2']] : 'X';
                $responseCode3 = (isset($covid19PossibleResponse['code'][$sample['test_result_3']]) && $covid19PossibleResponse['code'][$sample['test_result_3']] != '' && $covid19PossibleResponse['code'][$sample['test_result_3']] != null) ? $covid19PossibleResponse['code'][$sample['test_result_3']] : 'X';
                $finalResponseCode = (isset($covid19PossibleResponse['code'][$sample['reported_result']]) && $covid19PossibleResponse['code'][$sample['reported_result']] != '' && $covid19PossibleResponse['code'][$sample['reported_result']] != null) ? $covid19PossibleResponse['code'][$sample['reported_result']] : 'X';

                $responseResult1 = (isset($covid19PossibleResponse['result'][$sample['test_result_1']]) && $covid19PossibleResponse['result'][$sample['test_result_1']] != '' && $covid19PossibleResponse['result'][$sample['test_result_1']] != null) ? $covid19PossibleResponse['result'][$sample['test_result_1']] : '';
                $responseResult2 = (isset($covid19PossibleResponse['result'][$sample['test_result_2']]) && $covid19PossibleResponse['result'][$sample['test_result_2']] != '' && $covid19PossibleResponse['result'][$sample['test_result_2']] != null) ? $covid19PossibleResponse['result'][$sample['test_result_2']] : '';
                $responseResult3 = (isset($covid19PossibleResponse['result'][$sample['test_result_3']]) && $covid19PossibleResponse['result'][$sample['test_result_3']] != '' && $covid19PossibleResponse['result'][$sample['test_result_3']] != null) ? $covid19PossibleResponse['result'][$sample['test_result_3']] : '';
                $finalResponseResult = (isset($covid19PossibleResponse['result'][$sample['reported_result']]) && $covid19PossibleResponse['result'][$sample['reported_result']] != '' && $covid19PossibleResponse['result'][$sample['reported_result']] != null) ? $covid19PossibleResponse['result'][$sample['reported_result']] : '';

                $allSamplesResult['samples']['result1'][]       = array(
                    'resultCode'    => (isset($sample['test_result_1']) && $sample['test_result_1'] != '' && $sample['test_result_1'] != null) ? $responseCode1 : 'X',
                    'selected'      => (isset($sample['test_result_1']) && $sample['test_result_1'] != '') ? 'selected' : '',
                    'show'          => (isset($sample['test_result_1']) && $sample['test_result_1'] != '' && $sample['test_result_1'] != null) ? $responseResult1 : '',
                    'value'         => (isset($sample['test_result_1']) && $sample['test_result_1'] != '') ? $sample['test_result_1'] : '',
                );
                $allSamplesResult['samples']['result2'][]       = array(
                    'resultCode'    => (isset($sample['test_result_2']) && $sample['test_result_2'] != '' && $sample['test_result_2'] != null) ? $responseCode2 : 'X',
                    'selected'      => (isset($sample['test_result_2']) && $sample['test_result_2'] != '') ? 'selected' : '',
                    'show'          => (isset($sample['test_result_2']) && $sample['test_result_2'] != '' && $sample['test_result_2'] != null) ? $responseResult2 : '',
                    'value'         => (isset($sample['test_result_2']) && $sample['test_result_2'] != '') ? $sample['test_result_2'] : '',
                );
                $allSamplesResult['samples']['result3'][]       = array(
                    'resultCode'    => (isset($sample['test_result_3']) && $sample['test_result_3'] != '' && $sample['test_result_3'] != null) ? $responseCode3 : 'X',
                    'selected'      => (isset($sample['test_result_3']) && $sample['test_result_3'] != '') ? 'selected' : '',
                    'show'          => (isset($sample['test_result_3']) && $sample['test_result_3'] != '' && $sample['test_result_3'] != null) ? $responseResult3 : '',
                    'value'         => (isset($sample['test_result_3']) && $sample['test_result_3'] != '') ? $sample['test_result_3'] : '',
                );
                $allSamplesResult['samples']['finalResult'][]       = array(
                    'resultCode'    => (isset($sample['reported_result']) && $sample['reported_result'] != '' && $sample['reported_result'] != null) ? $finalResponseCode : 'X',
                    'selected'      => (isset($sample['reported_result']) && $sample['reported_result'] != '') ? 'selected' : '',
                    'show'          => (isset($sample['reported_result']) && $sample['reported_result'] != '' && $sample['reported_result'] != null) ? $finalResponseResult : '',
                    'value'         => (isset($sample['reported_result']) && $sample['reported_result'] != '') ? $sample['reported_result'] : '',
                );
                $allSamplesResult['samples']['result1Code'][]       = (isset($sample['test_result_1']) && $sample['test_result_1'] != '' && $sample['test_result_1'] != null) ? $responseCode1 : 'X';
                $allSamplesResult['samples']['result2Code'][]       = (isset($sample['test_result_2']) && $sample['test_result_2'] != '' && $sample['test_result_2'] != null) ? $responseCode2 : 'X';
                $allSamplesResult['samples']['result3Code'][]       = (isset($sample['test_result_3']) && $sample['test_result_3'] != '' && $sample['test_result_3'] != null) ? $responseCode3 : 'X';
                $allSamplesResult['samples']['finalResultCode'][]   = (isset($sample['reported_result']) && $sample['reported_result'] != '' && $sample['reported_result'] != null) ? $finalResponseCode : 'X';
                $allSamplesResult['samples']['mandatory'][]     = ($sample['mandatory'] == 1) ? true : false;
                foreach (range(1, 3) as $row) {
                    $possibleResults = [];
                    if ($row == 3) {
                        foreach ($covid19PossibleResults as $pr) {
                            if ($pr['scheme_sub_group'] == 'COVID19_TEST') {
                                $possibleResults[] = array('value' => (string) $pr['id'], 'show' => $pr['response'], 'resultCode' => $pr['result_code'], 'selected' => ($sample['test_result_3'] == $pr['id']) ? 'selected' : '');
                                // if($sample['test_result_3'] == $pr['id']){
                                //     $allSamplesResult['sampleName'][$sample['sample_label']][]  = array('resultName'=>'Result-3','resultValue'=>(string)$sample['test_result_3']);
                                //     $sample3Select                                              = $sample['test_result_3'];
                                // }
                            }
                        }
                        if (!$testThreeOptional) {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['status'] = true;
                        } else {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['status'] = false;
                        }
                        $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['data']      = $possibleResults;
                        if (isset($sample['test_result_3']) && $sample['test_result_3'] != "") {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['value'] = $sample['test_result_3'];
                        } else {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['value'] = "";
                        }
                    } else {
                        foreach ($covid19PossibleResults as $pr) {
                            if ($pr['scheme_sub_group'] == 'COVID19_TEST') {
                                $possibleResults[] = array('value' => (string) $pr['id'], 'show' => $pr['response'], 'resultCode' => $pr['result_code'], 'selected' => (($sample['test_result_1'] == $pr['id'] && $row == 1) || ($sample['test_result_2'] == $pr['id'] && $row == 2)) ? 'selected' : '');
                                // if($sample['test_result_1'] == $pr['id'] && $row == 1){
                                //     $allSamplesResult['sampleName'][$sample['sample_label']][]  = array('resultName'=>'Result-1','resultValue'=>$sample['test_result_1']);
                                //     $sample1Select                                              = $sample['test_result_1'];
                                // }else if($sample['test_result_2'] == $pr['id'] && $row == 2){
                                //     $allSamplesResult['sampleName'][$sample['sample_label']][]  = array('resultName'=>'Result-2','resultValue'=>(string)$sample['test_result_2']);
                                //     $sample2Select                                              = $sample['test_result_2'];
                                // }
                            }
                        }
                        if ((!$testTwoOptional && $row == 2) || $row == 1) {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['status']    = true;
                        } elseif ($row == 2) {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['status']    = false;
                        }
                        $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['data']      = $possibleResults;

                        if (isset($sample['test_result_1']) && $sample['test_result_1'] != "" && $row == 1) {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['value'] = $sample['test_result_1'];
                        } elseif (isset($sample['test_result_2']) && $sample['test_result_2'] != "" && $row == 2) {
                            $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['value'] = $sample['test_result_2'];
                        } else {
                            if ($row == 1) {
                                $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['value'] = "";
                            } elseif ($row == 2) {
                                $allSamplesResult['sampleList'][$sample['sample_label']]['Result-' . $row]['value'] = "";
                            }
                        }
                    }
                }
                $possibleFinalResults = [];
                foreach ($covid19PossibleResults as $pr) {
                    if ($pr['scheme_sub_group'] == 'COVID19_FINAL') {
                        $possibleFinalResults[] = array('value' => (string) $pr['id'], 'show' => $pr['response'], 'resultCode' => $pr['result_code'], 'selected' => ($sample['reported_result'] == $pr['id']) ? 'selected' : '');
                        /* if($sample['reported_result'] == $pr['id']){
                            $allSamplesResult['sampleName'][$sample['sample_label']][]  = array('resultName'=>'Final-Result','resultValue'=>(string)$sample['reported_result']);
                            $sampleFinalSelect                                          = $sample['reported_result'];
                        } */
                    }
                }

                $allSamplesResult['resultsText'] = array('Result-1', 'Result-2', 'Result-3', 'Final-Result');

                if (!$testThreeOptional) {
                    $allSamplesResult['resultStatus'] = array(true, true, true, true);
                } elseif (!$testTwoOptional) {
                    $allSamplesResult['resultStatus'] = array(true, false, false, true);
                } else {
                    $allSamplesResult['resultStatus'] = array(true, true, false, true);
                }
                /* if ((isset($shipment['shipment_attributes']["screeningTest"]) && $shipment['shipment_attributes']["screeningTest"] == 'yes')) {
                    $allSamplesResult['resultStatus'] = array(true, false, false, true);
                } */
                $allSamplesResult['sampleList'][$sample['sample_label']]['Final-Result']['status']    = true;
                $allSamplesResult['sampleList'][$sample['sample_label']]['Final-Result']['data']      = $possibleFinalResults;
                $allSamplesResult['sampleList'][$sample['sample_label']]['Final-Result']['value']     = (isset($sample['reported_result']) && $sample['reported_result'] != '') ? $sample['reported_result'] : '';
            }
            if ((isset($allSamples) && count($allSamples) > 0) && (isset($covid19PossibleResults) && count($covid19PossibleResults) > 0)) {
                $covid19['Section5']['status']  = true;
            } else {
                $covid19['Section5']['status']  = false;
            }
            $covid19['Section5']['data']        = $allSamplesResult;
            // Section 5 End // Section 6 Start
            $reviewArray = [];
            $commentArray = array('yes', 'no');
            $revieArr = [];
            foreach ($commentArray as $row) {
                $revieArr[] = array('value' => $row, 'show' => ucwords($row), 'selected' => (isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] == $row || (($shipment['supervisor_approval'] != null || $shipment['supervisor_approval'] != '') && $row == 'yes')) ? 'selected' : '');
            }
            $reviewArray['supervisorReview']        = $revieArr;
            $reviewArray['supervisorReviewSelected'] = (isset($shipment['supervisor_approval']) && $shipment['supervisor_approval'] != '') ? $shipment['supervisor_approval'] : '';
            $reviewArray['approvalLabel']           = 'Supervisor Name';
            $reviewArray['approvalInputText']       = (isset($shipment['participant_supervisor']) && $shipment['participant_supervisor'] != '') ? $shipment['participant_supervisor'] : '';
            $reviewArray['comments']                = (isset($shipment['user_comment']) && $shipment['user_comment'] != '') ? $shipment['user_comment'] : '';
            $covid19['Section6']['status']              = true;
            $covid19['Section6']['data']                = $reviewArray;
            // Section 5 End
            $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
            $customField1 = $globalConfigDb->getValue('custom_field_1');
            $customField2 = $globalConfigDb->getValue('custom_field_2');
            $haveCustom = $globalConfigDb->getValue('custom_field_needed');
            if (isset($haveCustom) && $haveCustom != 'no') {
                $covid19['customFields']['status'] = true;
                if (isset($customField1) && trim($customField1) != "") {
                    $covid19['customFields']['data']['customField1Text'] = $customField1;
                    $covid19['customFields']['data']['customField1Val'] = (isset($shipment['custom_field_1']) && $shipment['custom_field_1'] != "") ? $shipment['custom_field_1'] : '';
                }

                if (isset($customField2) && trim($customField2) != "") {
                    $covid19['customFields']['data']['customField2Text'] = $customField2;
                    $covid19['customFields']['data']['customField2Val'] = (isset($shipment['custom_field_2']) && $shipment['custom_field_2'] != "") ? $shipment['custom_field_2'] : '';
                }
            } else {
                $covid19['customFields']['status'] = false;
            }
            return $covid19;
        }
    }

    public function fetchIndividualReportAPI($params)
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
            return array('status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again');
        }

        /* Get individual reports using data manager */
        $resultData = [];
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('SHIP_YEAR' => 'year(s.shipment_date)', 's.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id', 's.status', 's.updated_on_admin'))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.map_id', "spm.evaluation_status", "spm.participant_id", "RESPONSEDATE" => "DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')", "RESPONSE" => new Zend_Db_Expr("CASE substr(spm.evaluation_status,3,1) WHEN 1 THEN 'View' WHEN '9' THEN 'Enter Result' END"), "REPORT" => new Zend_Db_Expr("CASE  WHEN spm.report_generated='yes' AND s.status='finalized' THEN 'Report' END")))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name'))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id')
            ->where("pmm.dm_id=?", $aResult['dm_id'])
            ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'");
        $resultData = $this->getAdapter()->fetchAll($sQuery);
        if (empty($resultData)) {
            return array('status' => 'fail', 'message' => 'Report not ready.', 'profileInfo' => $aResult['profileInfo']);
        }
        /* Started the API service for individual report */
        $data = [];
        $general = new Pt_Commons_General();
        $token = $dmDb->fetchAuthTokenByToken($params);
        foreach ($resultData as $aRow) {
            $downloadReports = '';
            $invididualFilePath = '';
            if ($aRow['status'] == 'finalized') {
                $invididualFilePath = (DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . "-" . $aRow['map_id'] . ".pdf");
                if (!file_exists($invididualFilePath)) {
                    // Search this file name using the map id
                    $files = glob(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . "*" . "-" . $aRow['map_id'] . ".pdf");
                    $invididualFilePath = isset($files[0]) ? $files[0] : '';
                }
                if (file_exists($invididualFilePath) && trim($token['download_link']) != '') {
                    $downloadReports .= '/api/participant/download/' . $token['download_link'] . '/' . base64_encode($aRow['map_id']);
                }
            }
            $data[] = array(
                'schemeType'        => strtoupper($aRow['scheme_type']),
                'shipmentCode'      => $aRow['shipment_code'],
                'shipmentDate'      => Pt_Commons_General::humanReadableDateFormat($aRow['shipment_date']),
                'uniqueIdentifier'  => $aRow['unique_identifier'],
                'name'              => $aRow['first_name'] . " " . $aRow['last_name'],
                'responseDate'      => Pt_Commons_General::humanReadableDateFormat($aRow['RESPONSEDATE']),
                'fileName'          => (file_exists($invididualFilePath)) ? basename($invididualFilePath) : '',
                'schemeName'        => $aRow['scheme_name'],
                'status'            => $aRow['status'],
                'statusUpdatedOn'   => $aRow['updated_on_admin'],
                'downloadLink'      => $downloadReports
            );
        }
        if (isset($data) && count($data) > 0) {
            return array('status' => 'success', 'data' => $data, 'profileInfo' => $aResult['profileInfo']);
        } else {
            return array('status' => 'fail', 'message' => 'Report not ready', 'profileInfo' => $aResult['profileInfo']);
        }
    }

    public function fetchSummaryReportAPI($params)
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
            return array('status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again');
        }
        /* Get summary reports using data manager */
        $resultData = [];
        $sQuery = $this->getAdapter()->select()->from(array('s' => 'shipment'), array('s.scheme_type', 's.shipment_date', 's.shipment_code', 's.status', 's.updated_on_admin'))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.map_id'))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array())
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
            ->where("pmm.dm_id=?", $aResult['dm_id'])
            ->where("s.status='shipped' OR s.status='evaluated'OR s.status='finalized'");
        $resultData = $this->getAdapter()->fetchAll($sQuery);
        if (empty($resultData)) {
            return array('status' => 'fail', 'message' => 'Report not ready.', 'profileInfo' => $aResult['profileInfo']);
        }
        /* Started the API service for summary report */
        $data = [];
        $general = new Pt_Commons_General();
        $token = $dmDb->fetchAuthTokenByToken($params);
        foreach ($resultData as $aRow) {
            $downloadReports = '';
            $summaryFilePath = '';
            if ($aRow['status'] == 'finalized') {
                $summaryFilePath = (DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . "-summary.pdf");
                if (file_exists($summaryFilePath) && trim($token['download_link']) != '') {
                    $downloadReports .= '/api/participant/download-summary/' . $token['download_link'] . '/' . base64_encode($aRow['map_id']);
                }
            }
            $data[] = array(
                'schemeType'        => strtoupper($aRow['scheme_type']),
                'shipmentCode'      => $aRow['shipment_code'],
                'shipmentDate'      => Pt_Commons_General::humanReadableDateFormat($aRow['shipment_date']),
                'fileName'          => (file_exists($summaryFilePath)) ? basename($summaryFilePath) : '',
                'schemeName'        => $aRow['scheme_name'],
                'status'            => $aRow['status'],
                'statusUpdatedOn'   => $aRow['updated_on_admin'],
                'downloadLink'  => $downloadReports
            );
        }
        if (isset($data) && count($data) > 0) {
            return array('status' => 'success', 'data' => $data, 'profileInfo' => $aResult['profileInfo']);
        } else {
            return array('status' => 'fail', 'message' => 'Report not ready', 'profileInfo' => $aResult['profileInfo']);
        }
    }

    public function saveShipmentsFormDetailsByAPI($params)
    {
        // Zend_Debug::dump($params);die;
        /* Check the app versions & parameters */
        /* if (!isset($params['appVersion'])) {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        if (!isset($params['authToken'])) {
            return array('status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again');
        }

        /* Validate new auth token and app-version */
        $dmDb = new Application_Model_DbTable_DataManagers();
        $dm = $dmDb->fetchAuthToken($params);
        /* if ($dm == 'app-version-failed') {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        if (!$dm) {
            return array('status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again');
        }
        /* To check the form have group of array or single array */
        $returnResposne = [];
        $responseStatus = false;
        if (isset($params['syncType']) && $params['syncType'] == 'group') {
            foreach ($params['data'] as $key => $row) {
                $status  = $this->saveShipmentByType((array) $row, $dm);
                if (!$status) {
                    $responseStatus = true;
                    $returnResposne[$key]['status']    = 'fail';
                } else {
                    $returnResposne[$key]['status']    = 'success';
                }
                $returnResposne[$key]['data']['mapId'] = $row->mapId;
            }
            if ($responseStatus) {
                return array('status' => 'failure', 'data' => $returnResposne, 'profileInfo' => $dm['profileInfo']);
            } else {
                return array('status' => 'success', 'data' => $returnResposne, 'profileInfo' => $dm['profileInfo']);
            }
        }
        if (isset($params['syncType']) && $params['syncType'] == 'single') {
            $status = $this->saveShipmentByType((array) $params['data'], $dm);
            // die($status);
            if ($status) {
                return array('status' => 'success', 'message' => 'Thank you for submitting your result. We have received it and the PT Results will be published on or after the due date.', 'profileInfo' => $dm['profileInfo']);
            } else {
                return array('status' => 'fail', 'message' => 'Please check your network connection and try again.', 'profileInfo' => $dm['profileInfo']);
            }
        }
        /* throw the expection if post data type not came */
        if ((isset($params['syncType']) || !isset($params['syncType'])) && (($params['syncType'] == 'single' && $params['syncType'] == 'group') || $params['syncType'] == '')) {
            return array('status' => 'fail', 'message' => 'Please check your network connection and try again.', 'profileInfo' => $dm['profileInfo']);
        }
    }

    public function saveShipmentByType($params, $dm)
    {
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, APPLICATION_ENV);

        /* Save shipments form details */
        $schemeService  = new Application_Service_Schemes();
        $spMap = new Application_Model_DbTable_ShipmentParticipantMap();
        $isEditable = $spMap->isShipmentEditable($params['shipmentId'], $params['participantId']);
        if ($params['schemeType'] == 'dts') {
            $dtsModel = new Application_Model_Dts();
            $allSamples =   $dtsModel->getDtsSamples($params['shipmentId'], $params['participantId']);
        }
        if ($params['schemeType'] == 'vl') {
            $allSamples =   $schemeService->getVlSamples($params['shipmentId'], $params['participantId']);
        }
        if ($params['schemeType'] == 'eid') {
            $allSamples =   $schemeService->getEidSamples($params['shipmentId'], $params['participantId']);
        }
        if ($params['schemeType'] == 'recency') {
            $allSamples =   $schemeService->getRecencySamples($params['shipmentId'], $params['participantId']);
        }
        if ($params['schemeType'] == 'covid19') {
            $allSamples =   $schemeService->getCovid19Samples($params['shipmentId'], $params['participantId']);
        }
        if (!$isEditable && $dm['view_only_access'] == 'yes') {
            return array('status' => 'fail', 'message' => 'Responding for this shipment is not allowed at this time. Please contact your PT Provider for any clarifications..');
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $eidResponseStatus = 0;
            $updateShipmentParticipantStatus = 0;
            $ptGeneral = new Pt_Commons_General();
            $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
            if ($params['schemeType'] == 'vl') {
                // Zend_Debug::dump($params["vlData"]->Section2->data->sampleRhdDate);die;
                if (isset($params["vlData"]->Section2->data->sampleRhdDate) && trim($params["vlData"]->Section2->data->sampleRhdDate) != "") {
                    $params["vlData"]->Section2->data->sampleRhdDate = date('Y-m-d', strtotime($params["vlData"]->Section2->data->sampleRhdDate));
                }
                if (isset($params["vlData"]->Section2->data->assayExpDate) && trim($params["vlData"]->Section2->data->assayExpDate) != "") {
                    $params["vlData"]->Section2->data->assayExpDate = date('Y-m-d', strtotime($params["vlData"]->Section2->data->assayExpDate));
                }
                $attributes = array(
                    "sample_rehydration_date"   => date('Y-m-d', strtotime($params["vlData"]->Section2->data->sampleRehydrationDate)),
                    "vl_assay"                  => (string) $params["vlData"]->Section2->data->vlAssaySelected,
                    "assay_lot_number"          => $params["vlData"]->Section2->data->assayLotNumber,
                    "assay_expiration_date"     => date('Y-m-d', strtotime($params["vlData"]->Section2->data->assayExpirationDate)),
                    "specimen_volume"           => $params["vlData"]->Section2->data->specimenVolume,
                    // "uploaded_file" => $params['uploadedFilePath']
                );

                if (isset($params["vlData"]->Section2->data->otherAssay) && $params["vlData"]->Section2->data->otherAssay != "") {
                    $attributes['other_assay'] = $params["vlData"]->Section2->data->otherAssay;
                }

                $attributes = json_encode($attributes);
                $data = array(
                    "shipment_receipt_date"         =>  date('Y-m-d', strtotime($params["vlData"]->Section2->data->testReceiptDate)),
                    "shipment_test_date"            =>  date('Y-m-d', strtotime($params["vlData"]->Section2->data->testDate)),
                    // "lastdate_response"         => (isset($params['vlData']->Section2->data->responseDate) && trim($params['vlData']->Section2->data->responseDate) != '')?date('Y-m-d',strtotime($params['vlData']->Section2->data->responseDate)):date('Y-m-d'),
                    "response_status"               => (isset($params['vlData']->Section2->data->responseStatus) && !empty($params['vlData']->Section2->data->responseStatus)) ? $params['vlData']->Section2->data->responseStatus : null,
                    "attributes"                    =>  $attributes,
                    "shipment_test_report_date"     => (isset($params["vlData"]->Section2->data->responseDate) && trim($params["vlData"]->Section2->data->responseDate) != '') ? date('Y-m-d', strtotime($params["vlData"]->Section2->data->responseDate)) : date('Y-m-d'),
                    "supervisor_approval"           =>  $params["vlData"]->Section4->data->supervisorReviewSelected,
                    "participant_supervisor"        =>  $params["vlData"]->Section4->data->approvalInputText,
                    "user_comment"                  =>  $params["vlData"]->Section4->data->comments,
                    "updated_by_user"               =>  $dm['dm_id'],
                    "mode_id"                       => (isset($params["vlData"]->Section2->data->modeOfReceiptSelected) && $params["vlData"]->Section2->data->modeOfReceiptSelected != "" && isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes') ? $params["vlData"]->Section2->data->modeOfReceiptSelected : null,
                    "updated_on_user"               =>  new Zend_Db_Expr('now()')
                );
                if (isset($params['vlData']->Section2->data->responseStatus) && $params['vlData']->Section2->data->responseStatus = "deleted") {
                    $shipmentService = new Application_Service_Shipments();
                    $shipmentService->removeDtsVlResults($params['mapId']);
                }
                $data['is_pt_test_not_performed']       = (isset($params["vlData"]->Section3->data->isPtTestNotPerformedRadio) && $params["vlData"]->Section3->data->isPtTestNotPerformedRadio == 'yes') ? 'yes' : 'no';
                if ($data['is_pt_test_not_performed'] == 'yes') {
                    $data['vl_not_tested_reason']           = $params["vlData"]->Section3->data->yes->vlNotTestedReasonSelected;
                    $data['pt_test_not_performed_comments'] = $params["vlData"]->Section3->data->yes->commentsTextArea;
                    $data['pt_support_comments']            = $params["vlData"]->Section3->data->yes->supportTextArea;
                } else {
                    $data['vl_not_tested_reason']           = '';
                    $data['pt_test_not_performed_comments'] = '';
                    $data['pt_support_comments']            = '';
                }

                if (isset($dm['qc_access']) && $dm['qc_access'] == 'yes') {
                    $data['qc_done'] = $params['vlData']->Section2->data->qcData->qcRadioSelected;
                    if (isset($data['qc_done']) && trim($data['qc_done']) == "yes") {
                        $data['qc_date'] = (isset($params['vlData']->Section2->data->qcData->qcDate) && $params['vlData']->Section2->data->qcData->qcDate != '') ? date('Y-m-d', strtotime($params['vlData']->Section2->data->qcData->qcDate)) : '';
                        $data['qc_done_by'] = (isset($params['vlData']->Section2->data->qcData->qcDoneBy) && $params['vlData']->Section2->data->qcData->qcDoneBy != '') ? $params['vlData']->Section2->data->qcData->qcDoneBy : '';
                        $data['qc_created_on'] = new Zend_Db_Expr('now()');
                    } else {
                        $data['qc_date'] = '';
                        $data['qc_done_by'] = '';
                        $data['qc_created_on'] = '';
                    }
                }
                // Zend_Debug::dump($params['mapId']);
                // die;

                $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
                $haveCustom = $globalConfigDb->getValue('custom_field_needed');
                // $haveCustom;
                if (isset($haveCustom) && $haveCustom != 'no') {
                    // if (isset($params['vlData']->customFields->data->customField1Val) && trim($params['vlData']->customFields->data->customField1Val) != "") {
                    $data['custom_field_1'] = $params['vlData']->customFields->data->customField1Val;
                    // }

                    // if (isset($params['vlData']->customFields->data->customField2Val) && trim($params['vlData']->customFields->data->customField2Val) != "") {
                    $data['custom_field_2'] = $params['vlData']->customFields->data->customField2Val;
                    // }
                }
                if (isset($params['vlData']->Section1->data->labDirectorName) && $params['vlData']->Section1->data->labDirectorName != "") {
                    $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
                    /* Shipment Participant table updation */
                    $sectionData = array(
                        'lab_director_name'         => $params['vlData']->Section1->data->labDirectorName,
                        'lab_director_email'        => $params['vlData']->Section1->data->labDirectorEmail,
                        'contact_person_name'       => $params['vlData']->Section1->data->contactPersonName,
                        'contact_person_email'      => $params['vlData']->Section1->data->contactPersonEmail,
                        'contact_person_telephone'  => $params['vlData']->Section1->data->contactPersonTelephone
                    );
                    $dbAdapter->update('shipment_participant_map', $sectionData, 'map_id = ' . $params['mapId']);
                    /* Participant table updation */
                    $dbAdapter->update('participant', $sectionData, 'participant_id = ' . $params['participantId']);
                }
                $updateShipmentParticipantStatus = $shipmentParticipantDb->updateShipmentByAPI($data, $dm, $params);

                $eidResponseDb = new Application_Model_DbTable_ResponseVl();
                $eidResponseStatus = $eidResponseDb->updateResultsByAPI($params, $dm);
                if ($eidResponseStatus > 0 || $updateShipmentParticipantStatus > 0) {
                    $db->commit();
                    return true;
                } else {
                    $db->rollBack();
                    return false;
                }
            }
            if ($params['schemeType'] == 'dts') {
                // Zend_Debug::dump($params);die;
                $attributes["sample_rehydration_date"] = (isset($params['dtsData']->Section2->data->sampleRehydrationDate) && $params['dtsData']->Section2->data->sampleRehydrationDate != '') ? date('Y-m-d', strtotime($params['dtsData']->Section2->data->sampleRehydrationDate)) : '';
                $attributes["algorithm"] = (isset($params['dtsData']->Section2->data->algorithmUsedSelected) && $params['dtsData']->Section2->data->algorithmUsedSelected != '') ? $params['dtsData']->Section2->data->algorithmUsedSelected : '';
                if ((isset($config->evaluation->dts->displaySampleConditionFields) && $config->evaluation->dts->displaySampleConditionFields == "yes")) {
                    $attributes["condition_pt_samples"] = (isset($params['dtsData']->Section2->data->conditionOfPTSamples) && $params['dtsData']->Section2->data->conditionOfPTSamples != '') ? $params['dtsData']->Section2->data->conditionOfPTSamples : '';
                    $attributes["refridgerator"] = (isset($params['dtsData']->Section2->data->refridgerator) && $params['dtsData']->Section2->data->refridgerator != '') ? $params['dtsData']->Section2->data->refridgerator : '';
                    $attributes["room_temperature"] = (isset($params['dtsData']->Section2->data->roomTemperature) && $params['dtsData']->Section2->data->roomTemperature != '') ? $params['dtsData']->Section2->data->roomTemperature : '';
                    $attributes["stop_watch"] = (isset($params['dtsData']->Section2->data->stopWatch) && $params['dtsData']->Section2->data->stopWatch != '') ? $params['dtsData']->Section2->data->stopWatch : '';
                }
                $attributes = json_encode($attributes);

                $data = array(
                    "shipment_receipt_date"     => date('Y-m-d', strtotime($params['dtsData']->Section2->data->testReceiptDate)),
                    "shipment_test_date"        => date('Y-m-d', strtotime($params['dtsData']->Section2->data->testingDate)),
                    "shipment_test_report_date" => (isset($params['dtsData']->Section2->data->responseDate) && trim($params['dtsData']->Section2->data->responseDate) != '') ? date('Y-m-d', strtotime($params['dtsData']->Section2->data->responseDate)) : date('Y-m-d'),
                    // "lastdate_response"         => (isset($params['dtsData']->Section2->data->respDate) && trim($params['dtsData']->Section2->data->respDate) != '')?date('Y-m-d',strtotime($params['dtsData']->Section2->data->respDate)):date('Y-m-d'),
                    "response_status"           => (isset($params['dtsData']->Section2->data->responseStatus) && !empty($params['dtsData']->Section2->data->responseStatus)) ? $params['dtsData']->Section2->data->responseStatus : null,
                    "attributes"                => $attributes,
                    "supervisor_approval"       => (isset($params['dtsData']->Section5->data->supervisorReviewSelected) && $params['dtsData']->Section5->data->supervisorReviewSelected != '') ? $params['dtsData']->Section5->data->supervisorReviewSelected : '',
                    "participant_supervisor"    => (isset($params['dtsData']->Section5->data->approvalInputText) && $params['dtsData']->Section5->data->approvalInputText != '') ? $params['dtsData']->Section5->data->approvalInputText : '',
                    "user_comment"              => (isset($params['dtsData']->Section5->data->comments) && $params['dtsData']->Section5->data->comments != '') ? $params['dtsData']->Section5->data->comments : '',
                    "updated_by_user"           => $dm['dm_id'],
                    "mode_id"                   => (isset($params['dtsData']->Section2->data->modeOfReceiptSelected) && $params['dtsData']->Section2->data->modeOfReceiptSelected != '' && isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes') ? $params['dtsData']->Section2->data->modeOfReceiptSelected : '',
                    "updated_on_user"           => new Zend_Db_Expr('now()')
                );
                if (isset($params['dtsData']->Section2->data->responseStatus) && $params['dtsData']->Section2->data->responseStatus = "deleted") {
                    $shipmentService = new Application_Service_Shipments();
                    $shipmentService->removeDtsResults($params['mapId']);
                }
                $data['is_pt_test_not_performed']       = (isset($params["dtsData"]->Section3->data->isPtTestNotPerformedRadio) && $params["dtsData"]->Section3->data->isPtTestNotPerformedRadio == 'yes') ? 'yes' : 'no';
                if ($data['is_pt_test_not_performed'] == 'yes') {
                    $data['received_pt_panel']              = $params["dtsData"]->Section3->data->receivedPtPanel;
                    $data['vl_not_tested_reason']           = $params["dtsData"]->Section3->data->notTestedReasonSelected;
                    $data['pt_test_not_performed_comments'] = $params["dtsData"]->Section3->data->ptNotTestedComments;
                    $data['pt_support_comments']            = $params["dtsData"]->Section3->data->ptSupportComments;
                } else {
                    $data['vl_not_tested_reason']           = '';
                    $data['pt_test_not_performed_comments'] = '';
                    $data['pt_support_comments']            = '';
                }

                if (isset($dm['qc_access']) && $dm['qc_access'] == 'yes') {
                    $data['qc_done'] = $params['dtsData']->Section2->data->qcData->qcRadioSelected;
                    if (isset($data['qc_done']) && trim($data['qc_done']) == "yes") {
                        $data['qc_date'] = (isset($params['dtsData']->Section2->data->qcData->qcDate) && $params['dtsData']->Section2->data->qcData->qcDate != '') ? date('Y-m-d', strtotime($params['dtsData']->Section2->data->qcData->qcDate)) : '';
                        $data['qc_done_by'] = (isset($params['dtsData']->Section2->data->qcData->qcDoneBy) && $params['dtsData']->Section2->data->qcData->qcDoneBy != '') ? $params['dtsData']->Section2->data->qcData->qcDoneBy : '';
                        $data['qc_created_on'] = new Zend_Db_Expr('now()');
                    } else {
                        $data['qc_date'] = '';
                        $data['qc_done_by'] = '';
                        $data['qc_created_on'] = '';
                    }
                }

                $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
                $haveCustom = $globalConfigDb->getValue('custom_field_needed');
                // $haveCustom;
                if (isset($haveCustom) && $haveCustom != 'no') {
                    // if (isset($params['dtsData']->customFields->data->customField1Val) && trim($params['dtsData']->customFields->data->customField1Val) != "") {
                    $data['custom_field_1'] = $params['dtsData']->customFields->data->customField1Val;
                    // }

                    // if (isset($params['dtsData']->customFields->data->customField2Val) && trim($params['dtsData']->customFields->data->customField2Val) != "") {
                    $data['custom_field_2'] = $params['dtsData']->customFields->data->customField2Val;
                    // }
                }

                $updateShipmentParticipantStatus = $shipmentParticipantDb->updateShipmentByAPI($data, $dm, $params);

                $dtsResponseDb = new Application_Model_DbTable_ResponseDts();
                $eidResponseStatus = $dtsResponseDb->updateResultsByAPI($params, $dm, $allSamples);
                if ($eidResponseStatus > 0 || $updateShipmentParticipantStatus > 0) {
                    $db->commit();
                    return true;
                } else {
                    $db->rollBack();
                    return false;
                }
            }
            if ($params['schemeType'] == 'eid') {
                // Zend_Debug::dump($params);die;
                $attributes = array(
                    "sample_rehydration_date"       => date('Y-m-d', strtotime($params['eidData']->Section2->data->sampleRehydrationDate)),
                    "extraction_assay"              => (isset($params['eidData']->Section2->data->extractionAssaySelected) && $params['eidData']->Section2->data->extractionAssaySelected != "") ? $params['eidData']->Section2->data->extractionAssaySelected : '',
                    "detection_assay"               => (isset($params['eidData']->Section2->data->detectionAssaySelected) && $params['eidData']->Section2->data->detectionAssaySelected != "") ? $params['eidData']->Section2->data->detectionAssaySelected : '',
                    "extraction_assay_expiry_date"  => (isset($params['eidData']->Section2->data->extractionExpirationDate) && $params['eidData']->Section2->data->extractionExpirationDate != "") ? date('Y-m-d', strtotime($params['eidData']->Section2->data->extractionExpirationDate)) : '',
                    "detection_assay_expiry_date"   => (isset($params['eidData']->Section2->data->detectionExpirationDate) && $params['eidData']->Section2->data->detectionExpirationDate != "") ? date('Y-m-d', strtotime($params['eidData']->Section2->data->detectionExpirationDate)) : '',
                    "extraction_assay_lot_no"       => (isset($params['eidData']->Section2->data->extractionLotNumber) && $params['eidData']->Section2->data->extractionLotNumber != "") ? $params['eidData']->Section2->data->extractionLotNumber : '',
                    "detection_assay_lot_no"        => (isset($params['eidData']->Section2->data->detectionLotNumber) && $params['eidData']->Section2->data->detectionLotNumber != "") ? $params['eidData']->Section2->data->detectionLotNumber : '',
                );
                $attributes = json_encode($attributes);

                $data = array(
                    "shipment_receipt_date"     => date('Y-m-d', strtotime($params['eidData']->Section2->data->testReceiptDate)),
                    "shipment_test_date"        => date('Y-m-d', strtotime($params['eidData']->Section2->data->testDate)),
                    "shipment_test_report_date" => (isset($params['eidData']->Section2->data->responseDate) && trim($params['eidData']->Section2->data->responseDate) != '') ? date('Y-m-d', strtotime($params['eidData']->Section2->data->responseDate)) : date('Y-m-d'),
                    // "lastdate_response"         => (isset($params['eidData']->Section2->data->respDate) && trim($params['eidData']->Section2->data->respDate) != '')?date('Y-m-d',strtotime($params['eidData']->Section2->data->respDate)):date('Y-m-d'),
                    "response_status"           => (isset($params['eidData']->Section2->data->responseStatus) && !empty($params['eidData']->Section2->data->responseStatus)) ? $params['eidData']->Section2->data->responseStatus : null,
                    "attributes"                => $attributes,
                    "supervisor_approval"       => $params['eidData']->Section4->data->supervisorReviewSelected,
                    "participant_supervisor"    => $params['eidData']->Section4->data->approvalInputText,
                    "user_comment"              => $params['eidData']->Section4->data->comments,
                    "updated_by_user"           => $dm['dm_id'],
                    "mode_id"                   => (isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes') ? $params['eidData']->Section2->data->modeOfReceiptSelected : '',
                    "updated_on_user"           => new Zend_Db_Expr('now()')
                );
                if (isset($params['eidData']->Section2->data->responseStatus) && $params['eidData']->Section2->data->responseStatus = "deleted") {
                    $shipmentService = new Application_Service_Shipments();
                    $shipmentService->removeDtsEidResults($params['mapId']);
                }
                if (isset($dm['qc_access']) && $dm['qc_access'] == 'yes') {
                    $data['qc_done'] = $params['eidData']->Section2->data->qcData->qcRadioSelected;
                    if (isset($data['qc_done']) && trim($data['qc_done']) == "yes") {
                        $data['qc_date'] = date('Y-m-d', strtotime($params['eidData']->Section2->data->qcData->qcDate));
                        $data['qc_done_by'] = trim($params['eidData']->Section2->data->qcData->qcDoneBy);
                        $data['qc_created_on'] = new Zend_Db_Expr('now()');
                    } else {
                        $data['qc_date'] = '';
                        $data['qc_done_by'] = '';
                        $data['qc_created_on'] = '';
                    }
                }

                $data['is_pt_test_not_performed']       = $params['eidData']->Section3->data->isPtTestNotPerformedRadio;
                if ($data['is_pt_test_not_performed'] == 'yes') {
                    $data['vl_not_tested_reason']           = $params['eidData']->Section3->data->vlNotTestedReasonSelected;
                    $data['pt_test_not_performed_comments'] = $params['eidData']->Section3->data->ptNotTestedComments;
                    $data['pt_support_comments']            = $params['eidData']->Section3->data->ptSupportComments;
                } else {
                    $data['vl_not_tested_reason']           = '';
                    $data['pt_test_not_performed_comments'] = '';
                    $data['pt_support_comments']            = '';
                }

                $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
                $haveCustom = $globalConfigDb->getValue('custom_field_needed');
                // $haveCustom;
                if (isset($haveCustom) && $haveCustom != 'no') {
                    // if (isset($params['eidData']->customFields->data->customField1Val) && trim($params['eidData']->customFields->data->customField1Val) != "") {
                    $data['custom_field_1'] = $params['eidData']->customFields->data->customField1Val;
                    // }

                    // if (isset($params['eidData']->customFields->data->customField2Val) && trim($params['eidData']->customFields->data->customField2Val) != "") {
                    $data['custom_field_2'] = $params['eidData']->customFields->data->customField2Val;
                    // }
                }

                if (isset($params['eidData']->Section1->data->labDirectorName) && $params['eidData']->Section1->data->labDirectorName != "") {
                    $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
                    /* Shipment Participant table updation */
                    $sectionData = array(
                        'lab_director_name'         => $params['eidData']->Section1->data->labDirectorName,
                        'lab_director_email'        => $params['eidData']->Section1->data->labDirectorEmail,
                        'contact_person_name'       => $params['eidData']->Section1->data->contactPersonName,
                        'contact_person_email'      => $params['eidData']->Section1->data->contactPersonEmail,
                        'contact_person_telephone'  => $params['eidData']->Section1->data->contactPersonTelephone
                    );
                    $dbAdapter->update('shipment_participant_map', $sectionData, 'map_id = ' . $params['mapId']);
                    /* Participant table updation */
                    $dbAdapter->update('participant', $sectionData, 'participant_id = ' . $params['participantId']);
                }

                $updateShipmentParticipantStatus = $shipmentParticipantDb->updateShipmentByAPI($data, $dm, $params);

                $eidResponseDb = new Application_Model_DbTable_ResponseEid();
                $eidResponseStatus = $eidResponseDb->updateResultsByAPI($params, $dm);
                if ($eidResponseStatus > 0 || $updateShipmentParticipantStatus > 0) {
                    $db->commit();
                    return true;
                } else {
                    $db->rollBack();
                    return false;
                }
            }
            if ($params['schemeType'] == 'recency') {
                // Zend_Debug::dump($params['recencyData']->Section2->data->recencyAssayLotNumber);die;
                $attributes = array(
                    "sample_rehydration_date"   => date('Y-m-d', strtotime($params['recencyData']->Section2->data->sampleRehydrationDate)),
                    "recency_assay"             => (isset($params['recencyData']->Section2->data->recencyAssaySelected) && $params['recencyData']->Section2->data->recencyAssaySelected != "") ? $params['recencyData']->Section2->data->recencyAssaySelected : '',
                    "recency_assay_lot_no"      => (isset($params['recencyData']->Section2->data->recencyAssayLotNumber) && $params['recencyData']->Section2->data->recencyAssayLotNumber != "") ? $params['recencyData']->Section2->data->recencyAssayLotNumber : '',
                    "recency_assay_expiry_date" => (isset($params['recencyData']->Section2->data->recencyAssayExpirayDate) && $params['recencyData']->Section2->data->recencyAssayExpirayDate != "") ? date('Y-m-d', strtotime($params['recencyData']->Section2->data->recencyAssayExpirayDate)) : '',
                );
                $attributes = json_encode($attributes);

                $data = array(
                    "shipment_receipt_date"     => date('Y-m-d', strtotime($params['recencyData']->Section2->data->testReceiptDate)),
                    "shipment_test_date"        => date('Y-m-d', strtotime($params['recencyData']->Section2->data->testDate)),
                    "response_status"           => (isset($params['recencyData']->Section2->data->responseStatus) && !empty($params['recencyData']->Section2->data->responseStatus)) ? $params['recencyData']->Section2->data->responseStatus : null,
                    "attributes"                => $attributes,
                    "supervisor_approval"       => $params['recencyData']->Section4->data->supervisorReviewSelected,
                    "participant_supervisor"    => $params['recencyData']->Section4->data->approvalInputText,
                    "user_comment"              => $params['recencyData']->Section4->data->comments,
                    "updated_by_user"           => $dm['dm_id'],
                    "mode_id"                   => (isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes') ? $params['recencyData']->Section2->data->modeOfReceiptSelected : '',
                    "updated_on_user"           => new Zend_Db_Expr('now()')
                );
                if (isset($params['testReceiptDate']) && trim($params['testReceiptDate']) != '') {
                    $data['shipment_test_report_date'] = Pt_Commons_General::isoDateFormat($params['testReceiptDate']);
                } else {
                    $data['shipment_test_report_date'] = new Zend_Db_Expr('now()');
                }
                if (isset($params['recencyData']->Section2->data->responseStatus) && $params['recencyData']->Section2->data->responseStatus = "deleted") {
                    $shipmentService = new Application_Service_Shipments();
                    $shipmentService->removeRecencyResults($params['mapId']);
                }
                if (isset($dm['qc_access']) && $dm['qc_access'] == 'yes') {
                    $data['qc_done'] = $params['recencyData']->Section2->data->qcData->qcRadioSelected;
                    if (isset($data['qc_done']) && trim($data['qc_done']) == "yes") {
                        $data['qc_date']        = date('Y-m-d', strtotime($params['recencyData']->Section2->data->qcData->qcDate));
                        $data['qc_done_by']     = trim($params['recencyData']->Section2->data->qcData->qcDoneBy);
                        $data['qc_created_on']  = new Zend_Db_Expr('now()');
                    } else {
                        $data['qc_date'] = '';
                        $data['qc_done_by'] = '';
                        $data['qc_created_on'] = '';
                    }
                }

                $data['is_pt_test_not_performed']       = $params['recencyData']->Section3->data->isPtTestNotPerformedRadio;
                if ($data['is_pt_test_not_performed'] == 'yes') {
                    $data['vl_not_tested_reason']           = $params['recencyData']->Section3->data->vlNotTestedReasonSelected;
                    $data['pt_test_not_performed_comments'] = $params['recencyData']->Section3->data->ptNotTestedComments;
                    $data['pt_support_comments']            = $params['recencyData']->Section3->data->ptSupportComments;
                } else {
                    $data['vl_not_tested_reason']           = '';
                    $data['pt_test_not_performed_comments'] = '';
                    $data['pt_support_comments']            = '';
                }

                $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
                $haveCustom = $globalConfigDb->getValue('custom_field_needed');
                // $haveCustom;
                if (isset($haveCustom) && $haveCustom != 'no') {
                    $data['custom_field_1'] = $params['recencyData']->customFields->data->customField1Val;
                    $data['custom_field_2'] = $params['recencyData']->customFields->data->customField2Val;
                }

                $updateShipmentParticipantStatus = $shipmentParticipantDb->updateShipmentByAPI($data, $dm, $params);

                $recencyResponseDb = new Application_Model_DbTable_ResponseRecency();
                $eidResponseStatus = $recencyResponseDb->updateResultsByAPI($params, $dm);
                $db->commit();
                return true;
            }
            if ($params['schemeType'] == 'covid19') {
                // Zend_Debug::dump($params['covid19Data']->Section6->data->supervisorReviewSelected);die;
                $attributes["sample_rehydration_date"] = (isset($params['covid19Data']->Section2->data->sampleRehydrationDate) && $params['covid19Data']->Section2->data->sampleRehydrationDate != '') ? date('Y-m-d', strtotime($params['covid19Data']->Section2->data->sampleRehydrationDate)) : '';
                $attributes = json_encode($attributes);

                $data = array(
                    "shipment_receipt_date"     => date('Y-m-d', strtotime($params['covid19Data']->Section2->data->testReceiptDate)),
                    "shipment_test_date"        => date('Y-m-d', strtotime($params['covid19Data']->Section2->data->testingDate)),
                    "shipment_test_report_date" => (isset($params['covid19Data']->Section2->data->responseDate) && trim($params['covid19Data']->Section2->data->responseDate) != '') ? date('Y-m-d', strtotime($params['covid19Data']->Section2->data->responseDate)) : date('Y-m-d'),
                    "response_status"           => (isset($params['covid19Data']->Section2->data->responseStatus) && !empty($params['covid19Data']->Section2->data->responseStatus)) ? $params['covid19Data']->Section2->data->responseStatus : null,
                    "attributes"                => $attributes,
                    "supervisor_approval"       => (isset($params['covid19Data']->Section6->data->supervisorReviewSelected) && $params['covid19Data']->Section6->data->supervisorReviewSelected != '') ? $params['covid19Data']->Section6->data->supervisorReviewSelected : '',
                    "participant_supervisor"    => (isset($params['covid19Data']->Section6->data->approvalInputText) && $params['covid19Data']->Section6->data->approvalInputText != '') ? $params['covid19Data']->Section6->data->approvalInputText : '',
                    "user_comment"              => (isset($params['covid19Data']->Section6->data->comments) && $params['covid19Data']->Section6->data->comments != '') ? $params['covid19Data']->Section6->data->comments : '',
                    "updated_by_user"           => $dm['dm_id'],
                    "mode_id"                   => (isset($params['covid19Data']->Section2->data->modeOfReceiptSelected) && $params['covid19Data']->Section2->data->modeOfReceiptSelected != '' && isset($dm['enable_choosing_mode_of_receipt']) && $dm['enable_choosing_mode_of_receipt'] == 'yes') ? $params['covid19Data']->Section2->data->modeOfReceiptSelected : '',
                    "updated_on_user"           => new Zend_Db_Expr('now()')
                );

                if (isset($params['covid19Data']->Section2->data->responseStatus) && $params['covid19Data']->Section2->data->responseStatus = "deleted") {
                    $shipmentService = new Application_Service_Shipments();
                    $shipmentService->removeCovid19Results($params['mapId']);
                }
                if (isset($dm['qc_access']) && $dm['qc_access'] == 'yes') {
                    $data['qc_done'] = $params['covid19Data']->Section2->data->qcData->qcRadioSelected;
                    if (isset($data['qc_done']) && trim($data['qc_done']) == "yes") {
                        $data['qc_date'] = (isset($params['covid19Data']->Section2->data->qcData->qcDate) && $params['covid19Data']->Section2->data->qcData->qcDate != '') ? date('Y-m-d', strtotime($params['covid19Data']->Section2->data->qcData->qcDate)) : '';
                        $data['qc_done_by'] = (isset($params['covid19Data']->Section2->data->qcData->qcDoneBy) && $params['covid19Data']->Section2->data->qcData->qcDoneBy != '') ? $params['covid19Data']->Section2->data->qcData->qcDoneBy : '';
                        $data['qc_created_on'] = new Zend_Db_Expr('now()');
                    } else {
                        $data['qc_date'] = '';
                        $data['qc_done_by'] = '';
                        $data['qc_created_on'] = '';
                    }
                }
                $data['is_pt_test_not_performed']       = $params['covid19Data']->Section3->data->isPtTestNotPerformedRadio;
                if ($data['is_pt_test_not_performed'] == 'yes') {
                    $data['vl_not_tested_reason']           = $params['covid19Data']->Section3->data->vlNotTestedReasonSelected;
                    $data['pt_test_not_performed_comments'] = $params['covid19Data']->Section3->data->ptNotTestedComments;
                    $data['pt_support_comments']            = $params['covid19Data']->Section3->data->ptSupportComments;
                } else {
                    $data['vl_not_tested_reason']           = '';
                    $data['pt_test_not_performed_comments'] = '';
                    $data['pt_support_comments']            = '';
                }
                $data['number_of_tests'] = $params['covid19Data']->Section2->data->numberOfTestsSelected;

                $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
                $haveCustom = $globalConfigDb->getValue('custom_field_needed');
                // $haveCustom;
                if (isset($haveCustom) && $haveCustom != 'no') {
                    // if (isset($params['covid19Data']->customFields->data->customField1Val) && trim($params['covid19Data']->customFields->data->customField1Val) != "") {
                    $data['custom_field_1'] = $params['covid19Data']->customFields->data->customField1Val;
                    // }

                    // if (isset($params['covid19Data']->customFields->data->customField2Val) && trim($params['covid19Data']->customFields->data->customField2Val) != "") {
                    $data['custom_field_2'] = $params['covid19Data']->customFields->data->customField2Val;
                    // }
                }

                $updateShipmentParticipantStatus = $shipmentParticipantDb->updateShipmentByAPI($data, $dm, $params);
                $covid19ResponseDb = new Application_Model_DbTable_ResponseCovid19();
                $eidResponseStatus = $covid19ResponseDb->updateResultsByAPI($params, $dm, $allSamples);
                if ($eidResponseStatus > 0 || $updateShipmentParticipantStatus > 0) {
                    $db->commit();
                    return true;
                } else {
                    $db->rollBack();
                    return false;
                }
            }
        } catch (Exception $e) {
            // If any of the queries failed and threw an exception,
            // we want to roll back the whole transaction, reversing
            // changes made in the transaction, even those that succeeded.
            // Thus all changes are committed together, or none are.

            error_log($e->getMessage());
            error_log($e->getTraceAsString());
            $db->rollBack();
            return false;
        }
    }

    public function getStatusOfMappedSites($parameters)
    {
        $sQuery = $this->getAdapter()->select()
            ->from(array('s' => 'shipment'), array('s.scheme_type', 's.shipment_date', 's.shipment_code', 's.lastdate_response', 's.shipment_id', 's.status', 's.response_switch', 'panelName' => new Zend_Db_Expr('shipment_attributes->>"$.panelName"')))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name', 'is_user_configured'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array("spm.map_id", "spm.evaluation_status", "spm.response_status", "spm.participant_id", "response_date" => "DATE_FORMAT(spm.shipment_test_report_date,'%d-%b-%Y')"))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.department_name', 'p.address', 'p.city', 'p.district', 'p.state', 'p.institute_name', 'p.country', 'p.email', 'p.mobile'))
            ->joinLeft(array('c' => 'countries'), 'p.country=c.id', array('c.iso_name'))
            //->where("IFNULL(spm.response_status, 'noresponse') != 'responded'")
            ->where("s.status != 'finalized'");

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }

        if (isset($parameters['currentType'])) {
            if ($parameters['currentType'] == 'active') {
                $sQuery = $sQuery->where("s.response_switch = 'on'");
            } elseif ($parameters['currentType'] == 'inactive') {
                $sQuery = $sQuery->where("s.response_switch = 'off'");
            }
        }

        if (isset($parameters['shipmentCode']) && $parameters['shipmentCode'] != "") {
            $sQuery = $sQuery->where("s.shipment_code = '" . $parameters['shipmentCode'] . "'");
        }
        if (isset($parameters['province']) && $parameters['province'] != "") {
            $sQuery = $sQuery->where("p.state = '" . $parameters['province'] . "'");
        }
        return $this->getAdapter()->fetchAll($sQuery);
    }
}
