<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Application_Service_Reports
{
    protected $common;

    public function __construct()
    {
        $this->common = new Application_Service_Common();
    }

    public function getAllShipments($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */
        $searchColumns = array('distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", 's.shipment_code', "DATE_FORMAT(s.lastdate_response,'%d-%b-%Y')", 'sl.scheme_name', 's.number_of_samples', 'participant_count', 'reported_count', 'reported_percentage', 'number_passed', 's.status');

        $orderColumns = array('distribution_code', 'distribution_date', 's.shipment_code', 's.lastdate_response', 'sl.scheme_name', 's.number_of_samples', new Zend_Db_Expr('count("participant_id")'), new Zend_Db_Expr("SUM(response_status is not null AND response_status like 'responded')"), new Zend_Db_Expr("(SUM(shipment_test_date <> '0000-00-00')/count('participant_id'))*100"), new Zend_Db_Expr("SUM(final_result = 1)"), 's.status');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = 'shipment_id';
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
                $colSize = count($searchColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($searchColumns[$i] == "" || $searchColumns[$i] == null) {
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        //error_log($sHaving);
        /* Individual column filtering */
        for ($i = 0; $i < count($searchColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_id', 'scheme_name', 'is_user_configured'))
            ->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('distribution_id', 'distribution_code', 'distribution_date'))
            ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('report_generated', 'participant_count' => new Zend_Db_Expr('count("participant_id")'), 'reported_count' => new Zend_Db_Expr("SUM(response_status is not null AND response_status like 'responded')"), 'reported_percentage' => new Zend_Db_Expr("ROUND((SUM(response_status is not null AND response_status like 'responded')/count('participant_id'))*100,2)"), 'number_passed' => new Zend_Db_Expr("SUM(final_result = 1)")))
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array())
            ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id', array())
            ->group(array('s.shipment_id'));

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

            $sQuery = $sQuery->where("s.shipment_date >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("s.shipment_date <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['dataManager']) && $parameters['dataManager'] != "") {
            $sQuery = $sQuery->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id');
            $sQuery = $sQuery->where("pmm.dm_id = ?", $parameters['dataManager']);
        }

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->having($sWhere);
        }

        //if (isset($sHaving) && $sHaving != "") {
        // $sQuery = $sQuery->having($sHaving);
        // }


        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }


        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        // echo ($sQuery);
        // die;

        $rResult = $dbAdapter->fetchAll($sQuery);


        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
        $aResultTotal = $dbAdapter->fetchCol($sQuery);
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


        //$shipmentDb = new Application_Model_DbTable_Shipments();
        //$aColumns = array('distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')",
        //'s.shipment_code' ,'sl.scheme_name' ,'s.number_of_samples' ,
        //'sp.participant_count','sp.reported_count','sp.number_passed','s.status');
        foreach ($rResult as $aRow) {
            $download = ' No Download Available ';
            $zipFileDownload = "";
            if (isset($aRow['status']) && $aRow['status'] == 'finalized') {
                if (file_exists(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . "-summary.pdf")) {
                    $filePath = base64_encode(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . "-summary.pdf");
                    $download = '<a href="/d/' . $filePath . '" class=\'btn btn-info btn-xs\'><i class=\'icon-download\'></i> Summary</a>';
                }
                $zipFilePath = (DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . ".zip");
                if (file_exists($zipFilePath)) {
                    $zipFileDownload =  "<br><a href='/d/" . base64_encode($zipFilePath) . "' class='btn btn-info btn-xs' target='_blank' style=' float: none; margin-top: 20px; '><i class='icon-download'></i> &nbsp All Reports</a><br>";
                }
            }
            //$shipmentResults = $shipmentDb->getPendingShipmentsByDistribution($aRow['distribution_id']);
            $responsePercentage = ($aRow['reported_percentage'] != "") ? $aRow['reported_percentage'] : "0";

            $row = [];
            $row[] = $aRow['distribution_code'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['distribution_date']);
            $row[] = "<a href='javascript:void(0);' onclick='generateShipmentParticipantList(\"" . base64_encode($aRow['shipment_id']) . "\",\"" . $aRow['scheme_type'] . "\")'>" . $aRow['shipment_code'] . "</a>";
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['lastdate_response']);
            $row[] = $aRow['scheme_name'];
            $row[] = $aRow['number_of_samples'];
            $row[] = $aRow['participant_count'];
            $row[] = $aRow['reported_count'] ?? 0;
            // if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1 && !empty($authNameSpace->ptccMappedCountries)) {
            //     $row[] = '<a href="/participant/response-chart/id/' . base64_encode($aRow['shipment_id']) . '/shipmentDate/' . base64_encode($aRow['distribution_date']) . '/shipmentCode/' . base64_encode($aRow['distribution_code']) . '" target="_blank" style="text-decoration:underline">' . $responsePercentage . ' %</a>';
            // } else {
            $row[] = '<a href="/reports/shipments/response-chart/id/' . base64_encode($aRow['shipment_id']) . '/shipmentDate/' . base64_encode($aRow['distribution_date']) . '/shipmentCode/' . base64_encode($aRow['distribution_code']) . '" target="_blank" style="text-decoration:underline">' . $responsePercentage . ' %</a>';
            //}
            $row[] = $aRow['number_passed'];
            $row[] = ucwords($aRow['status']);

            $row[] = $download . $zipFileDownload;
            $row[] = "
            <a href='javascript:void(0);' class='btn btn-success btn-xs' onclick='generateShipmentParticipantList(\"" . base64_encode($aRow['shipment_id']) . "\",\"" . $aRow['scheme_type'] . "\")'>Export Report</a>
            <a href='javascript:void(0);' class='btn btn-danger btn-xs' onclick='exportNotRespondedShipment(\"" . $aRow['shipment_code'] . "\",\"" . $aRow['shipment_date'] . "\")'><i class='icon icon-download'></i> Pending Sites</a>
            ";
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function updateReportConfigs($params)
    {
        $filterRules = array('*' => 'StripTags', '*' => 'StringTrim');
        $filter = new Zend_Filter_Input($filterRules, null, $params);
        if ($filter->isValid()) {
            //$params = $filter->getEscaped();
            $db = new Application_Model_DbTable_ReportConfig();
            $db->getAdapter()->beginTransaction();
            try {
                $result = $db->updateReportDetails($params);
                //$alertMsg = new Zend_Session_Namespace('alert');
                //$alertMsg->msg=" documents submitted successfully.";

                $db->getAdapter()->commit();
                return $result;
            } catch (Exception $exc) {
                $db->getAdapter()->rollBack();
                error_log($exc->getMessage());
                error_log($exc->getTraceAsString());
            }
        }
    }

    public function getReportConfigValue($name)
    {
        $db = new Application_Model_DbTable_ReportConfig();
        return $db->getValue($name);
    }

    public function getParticipantDetailedReport($params)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        if (isset($params['reportType']) && $params['reportType'] == "network") {
            $sQuery = $dbAdapter->select()->from(array('n' => 'r_network_tiers'))
                ->joinLeft(array('p' => 'participant'), 'p.network_tier=n.network_id', array())
                //->joinLeft(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array('participant_count'=> new Zend_Db_Expr("SUM(shipment_test_date = '') + SUM(shipment_test_date <> '')"), 'reported_count'=> new Zend_Db_Expr("SUM(shipment_test_date <> '')"), 'number_passed'=> new Zend_Db_Expr("SUM(final_result = 1)")))
                ->joinLeft(array('shp' => 'shipment_participant_map'), 'shp.participant_id=p.participant_id', array())
                ->joinLeft(array('s' => 'shipment'), 's.shipment_id=shp.shipment_id', array('lastdate_response'))
                ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array('others' => new Zend_Db_Expr("SUM(sp.shipment_test_date IS NULL)"), 'excluded' => new Zend_Db_Expr("SUM(if(sp.is_excluded = 'yes', 1, 0))"), 'number_failed' => new Zend_Db_Expr("SUM(sp.final_result = 2 AND sp.shipment_test_date <= s.lastdate_response AND sp.is_excluded != 'yes')"), 'number_passed' => new Zend_Db_Expr("SUM(sp.final_result = 1 AND sp.shipment_test_date <= s.lastdate_response AND sp.is_excluded != 'yes')"), 'number_late' => new Zend_Db_Expr("SUM(sp.shipment_test_date > s.lastdate_response AND sp.is_excluded != 'yes')"), 'map_id'))
                ->joinLeft(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array())
                ->joinLeft(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array())
                ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id', array())
                ->group('n.network_id')/* ->where("p.status = 'active'") */;
        }

        if (isset($params['reportType']) && $params['reportType'] == "affiliation") {
            $sQuery = $dbAdapter->select()->from(array('pa' => 'r_participant_affiliates'))
                ->joinLeft(array('p' => 'participant'), 'p.affiliation=pa.affiliate', array())
                //->joinLeft(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array('participant_count'=> new Zend_Db_Expr("SUM(shipment_test_date = '') + SUM(shipment_test_date <> '')"), 'reported_count'=> new Zend_Db_Expr("SUM(shipment_test_date <> '')"), 'number_passed'=> new Zend_Db_Expr("SUM(final_result = 1)")))
                ->joinLeft(array('shp' => 'shipment_participant_map'), 'shp.participant_id=p.participant_id', array())
                ->joinLeft(array('s' => 'shipment'), 's.shipment_id=shp.shipment_id', array('lastdate_response'))
                ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array('others' => new Zend_Db_Expr("SUM(sp.shipment_test_date IS NULL)"), 'excluded' => new Zend_Db_Expr("SUM(if(sp.is_excluded = 'yes', 1, 0))"), 'number_failed' => new Zend_Db_Expr("SUM(sp.final_result = 2 AND sp.shipment_test_date <= s.lastdate_response AND sp.is_excluded != 'yes')"), 'number_passed' => new Zend_Db_Expr("SUM(sp.final_result = 1 AND sp.shipment_test_date <= s.lastdate_response AND sp.is_excluded != 'yes')"), 'number_late' => new Zend_Db_Expr("SUM(sp.shipment_test_date > s.lastdate_response AND sp.is_excluded != 'yes')")))
                ->joinLeft(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array())
                ->joinLeft(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array())
                ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id', array())
                ->group('pa.aff_id')/* ->where("p.status = 'active'") */;
        }
        if (isset($params['reportType']) && $params['reportType'] == "region") {
            $sQuery = $dbAdapter->select()->from(array('p' => 'participant'), array('p.region'))
                //->joinLeft(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array('participant_count'=> new Zend_Db_Expr("SUM(shipment_test_date = '') + SUM(shipment_test_date <> '')"), 'reported_count'=> new Zend_Db_Expr("SUM(shipment_test_date <> '')"), 'number_passed'=> new Zend_Db_Expr("SUM(final_result = 1)")))
                ->joinLeft(array('shp' => 'shipment_participant_map'), 'shp.participant_id=p.participant_id', array())
                ->joinLeft(array('s' => 'shipment'), 's.shipment_id=shp.shipment_id', array('lastdate_response'))
                ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array('others' => new Zend_Db_Expr("SUM(sp.shipment_test_date IS NULL)"), 'excluded' => new Zend_Db_Expr("SUM(if(sp.is_excluded = 'yes', 1, 0))"), 'number_failed' => new Zend_Db_Expr("SUM(sp.final_result = 2 AND sp.shipment_test_date <= s.lastdate_response AND sp.is_excluded != 'yes')"), 'number_passed' => new Zend_Db_Expr("SUM(sp.final_result = 1 AND sp.shipment_test_date <= s.lastdate_response AND sp.is_excluded != 'yes')"), 'number_late' => new Zend_Db_Expr("SUM(sp.shipment_test_date > s.lastdate_response AND sp.is_excluded != 'yes')")))
                ->joinLeft(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array())
                ->joinLeft(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array())
                ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id', array())
                ->group('p.region')->where("p.region IS NOT NULL")->where("p.region != ''")/* ->where("p.status = 'active'") */;
        }
        if (isset($params['reportType']) && $params['reportType'] == "enrolled-programs") {
            $sQuery = $dbAdapter->select()->from(array('p' => 'participant'), array())
                //->joinLeft(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array('participant_count'=> new Zend_Db_Expr("SUM(shipment_test_date = '') + SUM(shipment_test_date <> '')"), 'reported_count'=> new Zend_Db_Expr("SUM(shipment_test_date <> '')"), 'number_passed'=> new Zend_Db_Expr("SUM(final_result = 1)")))
                ->joinLeft(array('pe' => 'participant_enrolled_programs_map'), 'pe.participant_id=p.participant_id', array())
                ->joinLeft(array('rep' => 'r_enrolled_programs'), 'rep.r_epid=pe.ep_id', array('rep.enrolled_programs'))
                ->joinLeft(array('shp' => 'shipment_participant_map'), 'shp.participant_id=p.participant_id', array())
                ->joinLeft(array('s' => 'shipment'), 's.shipment_id=shp.shipment_id', array('lastdate_response'))
                ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array('others' => new Zend_Db_Expr("SUM(sp.shipment_test_date IS NULL)"), 'excluded' => new Zend_Db_Expr("SUM(if(sp.is_excluded = 'yes', 1, 0))"), 'number_failed' => new Zend_Db_Expr("SUM(sp.final_result = 2 AND sp.shipment_test_date <= s.lastdate_response AND sp.is_excluded != 'yes')"), 'number_passed' => new Zend_Db_Expr("SUM(sp.final_result = 1 AND sp.shipment_test_date <= s.lastdate_response AND sp.is_excluded != 'yes')"), 'number_late' => new Zend_Db_Expr("SUM(sp.shipment_test_date > s.lastdate_response AND sp.is_excluded != 'yes')")))
                ->joinLeft(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array())
                ->joinLeft(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array())
                ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id', array())
                ->group('rep.r_epid');
        }
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        if (isset($params['scheme']) && $params['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $params['scheme']);
        }

        //die($sQuery);
        if (isset($params['startDate']) && $params['startDate'] != "" && isset($params['endDate']) && $params['endDate'] != "") {

            $sQuery = $sQuery->where("s.shipment_date >= ?", $this->common->isoDateFormat($params['startDate']));
            $sQuery = $sQuery->where("s.shipment_date <= ?", $this->common->isoDateFormat($params['endDate']));
        }

        //echo $sQuery;die;
        return $dbAdapter->fetchAll($sQuery);
    }

    public function getAllParticipantDetailedReport($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        if (isset($parameters['reportType']) && $parameters['reportType'] == "network") {
            $aColumns = array('s.shipment_code', 'sl.scheme_name', 'network_name', 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'state', 'district');
        } else if (isset($parameters['reportType']) && $parameters['reportType'] == "affiliation") {
            $aColumns = array('s.shipment_code', 'sl.scheme_name', 'affiliate', 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'state', 'district');
        } else if (isset($parameters['reportType']) && $parameters['reportType'] == "region") {
            $aColumns = array('s.shipment_code', 'sl.scheme_name', 'region', 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'state', 'district');
        } else if (isset($parameters['reportType']) && $parameters['reportType'] == "enrolled-programs") {
            $aColumns = array('s.shipment_code', 'sl.scheme_name', 'enrolled_programs', 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'state', 'district');
        }



        /*
         * Paging
         */
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

        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        //////////////


        if (isset($parameters['reportType']) && $parameters['reportType'] == "network") {
            $sQuery = $dbAdapter->select()->from(array('n' => 'r_network_tiers'))
                ->joinLeft(array('p' => 'participant'), 'p.network_tier=n.network_id', array('p.state', 'p.district'))
                ->joinLeft(array('shp' => 'shipment_participant_map'), 'shp.participant_id=p.participant_id', array())
                ->joinLeft(array('s' => 'shipment'), 's.shipment_id=shp.shipment_id', array('shipment_code', 'lastdate_response'))
                ->joinLeft(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_name'))
                ->joinLeft(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('distribution_code', 'distribution_date'))
                ->group('n.network_id')->group('s.shipment_id')/* ->where("p.status = 'active'") */;
        } else if (isset($parameters['reportType']) && $parameters['reportType'] == "affiliation") {
            $sQuery = $dbAdapter->select()->from(array('pa' => 'r_participant_affiliates'))
                ->joinLeft(array('p' => 'participant'), 'p.affiliation=pa.affiliate', array('p.state', 'p.district'))
                ->joinLeft(array('shp' => 'shipment_participant_map'), 'shp.participant_id=p.participant_id', array())
                ->joinLeft(array('s' => 'shipment'), 's.shipment_id=shp.shipment_id', array('shipment_code', 'lastdate_response'))
                ->joinLeft(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_name'))
                ->joinLeft(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('distribution_code', 'distribution_date'))
                ->group('pa.aff_id')->group('s.shipment_id')/* ->where("p.status = 'active'") */;
        } else if (isset($parameters['reportType']) && $parameters['reportType'] == "region") {
            $sQuery = $dbAdapter->select()->from(array('p' => 'participant'), array('p.region', 'p.state', 'p.district'))
                ->joinLeft(array('shp' => 'shipment_participant_map'), 'shp.participant_id=p.participant_id', array())
                ->joinLeft(array('s' => 'shipment'), 's.shipment_id=shp.shipment_id', array('shipment_code', 'lastdate_response'))
                ->joinLeft(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_name'))
                ->joinLeft(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('distribution_code', 'distribution_date'))
                ->group('p.region')->where("p.region IS NOT NULL")->where("p.region != ''")->group('s.shipment_id')/* ->where("p.status = 'active'") */;
        } else if (isset($parameters['reportType']) && $parameters['reportType'] == "enrolled-programs") {
            $sQuery = $dbAdapter->select()->from(array('p' => 'participant'), array('p.state', 'p.district'))
                ->joinLeft(array('pe' => 'participant_enrolled_programs_map'), 'pe.participant_id=p.participant_id', array())
                ->joinLeft(array('rep' => 'r_enrolled_programs'), 'rep.r_epid=pe.ep_id', array('rep.enrolled_programs'))
                ->joinLeft(array('shp' => 'shipment_participant_map'), 'shp.participant_id=p.participant_id', array())
                ->joinLeft(array('s' => 'shipment'), 's.shipment_id=shp.shipment_id', array('shipment_code', 'lastdate_response'))
                ->joinLeft(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_name'))
                ->joinLeft(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('distribution_code', 'distribution_date'))
                ->group('rep.r_epid')->group('s.shipment_id')/* ->where("p.status = 'active'") */;
        }
        //        else{
        //          $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'))
        //                ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id')
        //                ->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id')
        //                ->group('s.shipment_id');
        //        }
        ///////////

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

            $sQuery = $sQuery->where("s.shipment_date >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("s.shipment_date <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
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
        $rResult = $dbAdapter->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */

        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = sizeof($aResultTotal);

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
            $row[] = ucwords($aRow['scheme_name']);
            if (isset($parameters['reportType']) && $parameters['reportType'] == "network") {
                $row[] = $aRow['network_name'];
            } else if (isset($parameters['reportType']) && $parameters['reportType'] == "affiliation") {
                $row[] = $aRow['affiliate'];
            } else if (isset($parameters['reportType']) && $parameters['reportType'] == "region") {
                $row[] = $aRow['region'];
            } else if (isset($parameters['reportType']) && $parameters['reportType'] == "enrolled-programs") {
                $row[] = (isset($aRow['enrolled_programs']) && $aRow['enrolled_programs'] != "" && $aRow['enrolled_programs'] != null) ? $aRow['enrolled_programs'] : "No Program";
            }

            $row[] = $aRow['distribution_code'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['distribution_date']);
            $row[] = ucwords($aRow['state']);
            $row[] = ucwords($aRow['district']);
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getParticipantTrendsReport($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array(
            'sl.scheme_name',
            "DATE_FORMAT(s.shipment_date,'%d-%b-%Y')",
            's.shipment_code',
            new Zend_Db_Expr('count("sp.map_id")'),
            new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
            new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
            new Zend_Db_Expr("SUM(final_result = 1)"),
            new Zend_Db_Expr("((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100"),
            //'average_score'
        );
        $searchColumns = array(
            'sl.scheme_name',
            "DATE_FORMAT(s.shipment_date,'%d-%b-%Y')",
            's.shipment_code', "total_shipped",
            'total_responses',
            'valid_responses',
            'total_passed',
            'pass_percentage',
            //'average_score'
        );
        $orderColumns = array(
            'sl.scheme_name',
            "s.shipment_date",
            's.shipment_code',
            new Zend_Db_Expr('count("sp.map_id")'),
            new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
            new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
            new Zend_Db_Expr("SUM(final_result = 1)"),
            new Zend_Db_Expr("((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100"),
            //'average_score'
        );

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = 'shipment_id';
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
                $colSize = count($searchColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($searchColumns[$i] == "" || $searchColumns[$i] == null) {
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        //error_log($sHaving);
        /* Individual column filtering */
        for ($i = 0; $i < count($searchColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */


        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id')
            ->joinLeft(
                array('sp' => 'shipment_participant_map'),
                'sp.shipment_id=s.shipment_id',
                array(
                    "DATE_FORMAT(s.shipment_date,'%d-%b-%Y')",
                    "total_shipped" => new Zend_Db_Expr('count("sp.map_id")'),
                    "total_responses" => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
                    "valid_responses" => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00%' AND is_excluded != 'yes'))"),
                    "total_passed" => new Zend_Db_Expr("(SUM(final_result = 1))"),
                    "pass_percentage" => new Zend_Db_Expr("((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100")
                )
            )
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id')
            ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id')
            ->group(array('s.shipment_id'));


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

            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }


        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->having($sWhere);
        }


        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        $chartSession = new Zend_Session_Namespace('timelinessChart');
        $chartSession->timelinessChartQuery = $sQuery;

        $sQuerySession = new Zend_Session_Namespace('ParticipantTrendsExcel');
        $sQuerySession->participantQuery = $sQuery;

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //        error_log($sQuery);

        $rResult = $dbAdapter->fetchAll($sQuery);


        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sWhere = "";
        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        $aResultTotal = $dbAdapter->fetchCol($sQuery);
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


            $row = [];
            $row['DT_RowId'] = "shipment" . $aRow['shipment_id'];
            $row[] = $aRow['scheme_name'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['shipment_date']);
            $row[] = "<a href='javascript:void(0);' onclick='shipmetRegionReport(\"" . $aRow['shipment_id'] . "\"),regionDetails(\"" . $aRow['scheme_name'] . "\",\"" . Pt_Commons_General::humanReadableDateFormat($aRow['shipment_date']) . "\",\"" . $aRow['shipment_code'] . "\")'>" . $aRow['shipment_code'] . "</a>";
            $row[] = $aRow['total_shipped'];
            $row[] = $aRow['total_responses'];
            $row[] = $aRow['valid_responses'];
            $row[] = $aRow['total_passed'];
            $row[] = round($aRow['pass_percentage'], 2);
            $row[] = round($aRow['average_score'], 2);


            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getParticipantPerformanceReport($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array(
            'sl.scheme_name',
            "DATE_FORMAT(s.shipment_date,'%d-%b-%Y')",
            's.shipment_code',
            new Zend_Db_Expr('count("sp.map_id")'),
            new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
            new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
            new Zend_Db_Expr("SUM(final_result = 1)"),
            new Zend_Db_Expr("((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100"),
            //'average_score'
        );
        $searchColumns = array(
            'sl.scheme_name',
            "DATE_FORMAT(s.shipment_date,'%d-%b-%Y')",
            's.shipment_code', "total_shipped",
            'total_responses',
            'valid_responses',
            'total_passed',
            'pass_percentage',
            //'average_score'
        );
        $orderColumns = array(
            'sl.scheme_name',
            "s.shipment_date",
            's.shipment_code',
            new Zend_Db_Expr('count("sp.map_id")'),
            new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
            new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
            new Zend_Db_Expr("SUM(final_result = 1)"),
            new Zend_Db_Expr("((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100"),
            //'average_score'
        );

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = 'shipment_id';
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
                $colSize = count($searchColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($searchColumns[$i] == "" || $searchColumns[$i] == null) {
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        //error_log($sHaving);
        /* Individual column filtering */
        for ($i = 0; $i < count($searchColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */


        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id')
            ->joinLeft(
                array('sp' => 'shipment_participant_map'),
                'sp.shipment_id=s.shipment_id',
                array(
                    "DATE_FORMAT(s.shipment_date,'%d-%b-%Y')",
                    "total_shipped" => new Zend_Db_Expr('count("sp.map_id")'),
                    "total_responses" => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
                    "valid_responses" => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00%' AND is_excluded != 'yes'))"),
                    "total_passed" => new Zend_Db_Expr("(SUM(final_result = 1))"),
                    "pass_percentage" => new Zend_Db_Expr("((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100")
                )
            )
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id')
            ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id')
            ->group(array('s.shipment_id'));


        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1 && !empty($authNameSpace->ptccMappedCountries)) {
            $sQuery = $sQuery->where("p.country IN(" . $authNameSpace->ptccMappedCountries . ")");
        } else if (isset($authNameSpace->mappedParticipants) && !empty($authNameSpace->mappedParticipants)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }


        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->having($sWhere);
        }


        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        $chartSession = new Zend_Session_Namespace('timelinessChart');
        $chartSession->timelinessChartQuery = $sQuery;

        $sQuerySession = new Zend_Session_Namespace('participantPerformanceExcel');
        $sQuerySession->participantQuery = $sQuery;

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //        error_log($sQuery);

        $rResult = $dbAdapter->fetchAll($sQuery);


        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sWhere = "";
        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        $aResultTotal = $dbAdapter->fetchCol($sQuery);
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


            $row = [];
            $row['DT_RowId'] = "shipment" . $aRow['shipment_id'];
            $row[] = $aRow['scheme_name'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['shipment_date']);
            $row[] = "<a href='javascript:void(0);' onclick='shipmetRegionReport(\"" . $aRow['shipment_id'] . "\"),regionDetails(\"" . $aRow['scheme_name'] . "\",\"" . Pt_Commons_General::humanReadableDateFormat($aRow['shipment_date']) . "\",\"" . $aRow['shipment_code'] . "\")'>" . $aRow['shipment_code'] . "</a>";
            $row[] = $aRow['total_shipped'];
            $row[] = $aRow['total_responses'];
            $row[] = $aRow['valid_responses'];
            $row[] = $aRow['total_passed'];
            $row[] = round($aRow['pass_percentage'], 2);
            $row[] = round($aRow['average_score'], 2);


            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
    public function getParticipantPerformanceReportByShipmentId($shipmentId, $testType = '')
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id')
            ->joinLeft(
                array('sp' => 'shipment_participant_map'),
                'sp.shipment_id=s.shipment_id',
                array(
                    "DATE_FORMAT(s.shipment_date,'%d-%b-%Y')",
                    "total_shipped" => new Zend_Db_Expr('count("sp.map_id")'),
                    // "total_responses" => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
                    "total_responses" => new Zend_Db_Expr("SUM(response_status is not null AND response_status like 'responded')"),
                    "valid_responses" => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00%' AND is_excluded != 'yes'))"),
                    "number_failed" => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result = 2 AND DATE(sp.shipment_test_report_date) <= s.lastdate_response) THEN 1 ELSE 0 END)"),
                    "score" => new Zend_Db_Expr("(SUM(sp.shipment_score) + SUM(sp.documentation_score))"),
                    "number_passed" => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result = 1 AND DATE(sp.shipment_test_report_date) <= s.lastdate_response) THEN 1 ELSE 0 END)")
                )
            )
            ->where("s.shipment_id = ?", $shipmentId);
        if (isset($testType) && !empty($testType)) {
            $sQuery = $sQuery->where("JSON_EXTRACT(sp.attributes, '$.dts_test_panel_type') = ?", $testType);
        }
        // echo $sQuery;die;
        return $dbAdapter->fetchRow($sQuery);
    }

    public function getShipmentResponseReport($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array(
            'sl.scheme_name',
            "ref.sample_label",
            'ref.reference_result',
            'positive_responses',
            'negative_responses',
            'invalid_responses',
            new Zend_Db_Expr("SUM(response_status is not null AND response_status like 'responded')"),
            new Zend_Db_Expr("SUM(sp.final_result=1)"),
            new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00'))"),
        );

        $searchColumns = array(
            'sl.scheme_name',
            "ref.sample_label",
            'ref.reference_result',
            'positive_responses',
            'negative_responses',
            'invalid_responses',
            'total_responses',
            "total_passed",
            'valid_responses'
        );
        $orderColumns = array(
            'sl.scheme_name',
            "ref.sample_label",
            'ref.reference_result',
            'positive_responses',
            'negative_responses',
            'invalid_responses',
            new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
            new Zend_Db_Expr("SUM(sp.final_result=1)"),
            new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
        );


        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = 'shipment_id';
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
                $colSize = count($searchColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($searchColumns[$i] == "" || $searchColumns[$i] == null) {
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        //error_log($sHaving);
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
        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $refTable = "reference_result_" . $parameters['scheme'];
            $resTable = "response_result_" . $parameters['scheme'];

            // to count the total positive and negative, we need to know which r_possibleresults are positive and negative
            // so the following ...
            $rInderminate = 0;
            if ($parameters['scheme'] == 'dts') {
                $rPositive = 4;
                $rNegative = 5;
                $rInderminate = 6;
            } else if ($parameters['scheme'] == 'dbs') {
                $rPositive = 7;
                $rNegative = 8;
            } else if ($parameters['scheme'] == 'eid') {
                $rPositive = 10;
                $rNegative = 11;
            }
        }

        //$aColumns = array('sl.scheme_name', "ref.sample_label", 'ref.reference_result', 'positive_responses', 'negative_responses', new Zend_Db_Expr("SUM(sp.shipment_test_date <> '')"), new Zend_Db_Expr("SUM(sp.final_result = 1) + SUM(sp.final_result = 2)"));
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'), array('shipment_code'))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id')
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array("total_responses" => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"), "total_passed" => new Zend_Db_Expr("SUM(sp.final_result=1)"), "valid_responses" => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))")))
            //->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id')
            ->join(array('ref' => $refTable), 's.shipment_id=ref.shipment_id')
            ->join(array('res' => $resTable), 'sp.map_id=res.shipment_map_id', array("positive_responses" => new Zend_Db_Expr('SUM(if(res.reported_result = ' . $rPositive . ', 1, 0))'), "negative_responses" => new Zend_Db_Expr('SUM(if(res.reported_result = ' . $rNegative . ', 1, 0))'), "invalid_responses" => new Zend_Db_Expr('SUM(if(res.reported_result = ' . $rInderminate . ', 1, 0))')))
            ->join(array('rr' => 'r_results'), 'sp.final_result=rr.result_id')
            ->join(array('rp' => 'r_possibleresult'), 'ref.reference_result=rp.id')
            ->where("res.sample_id = ref.sample_id")
            ->group(array('sp.shipment_id', 'ref.sample_label'));

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }


        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->having($sWhere);
        }


        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        $sQuerySession = new Zend_Session_Namespace('shipmentExportExcel');
        $sQuerySession->shipmentExportQuery = $sQuery;

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
        $sWhere = "";
        $sQuery = $dbAdapter->select()->from(array('ref' => $refTable), new Zend_Db_Expr("COUNT('ref.sample_label')"))
            ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id', array());


        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }


        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->having($sWhere);
        }

        $aResultTotal = $dbAdapter->fetchCol($sQuery);
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
            $row = [];
            $exclamation = "";
            if ($aRow['mandatory'] == 0) {
                $exclamation = "&nbsp;&nbsp;&nbsp;<i class='icon-exclamation' style='color:red;'></i>";
            }
            $row[] = $aRow['scheme_name'];
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['sample_label'] . $exclamation;
            $row[] = $aRow['response'];
            $row[] = $aRow['positive_responses'];
            $row[] = $aRow['negative_responses'];
            $row[] = $aRow['invalid_responses'];
            $row[] = $aRow['total_responses'];
            $row[] = $aRow['valid_responses'];
            // $row[] = $aRow['total_passed'];
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getTestKitReport($params)
    {
        //Zend_Debug::dump($params);die;
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('res' => 'response_result_dts'), array('totalTest' => new Zend_Db_Expr("CAST((COUNT('shipment_map_id')/s.number_of_samples) as UNSIGNED)")))
            ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.map_id=res.shipment_map_id', array())
            ->joinLeft(array('p' => 'participant'), 'sp.participant_id=p.participant_id', array())
            ->joinLeft(array('s' => 'shipment'), 's.shipment_id=sp.shipment_id', array());
        if (isset($params['kitType']) && $params['kitType'] == "testkit1") {
            $sQuery = $sQuery->joinLeft(array('tn' => 'r_testkitname_dts'), 'tn.TestKitName_ID=res.test_kit_name_1', array('TestKit_Name', 'TestKitName_ID'))
                ->group('tn.TestKitName_ID');
        } else if (isset($params['kitType']) && $params['kitType'] == "testkit2") {
            $sQuery = $sQuery->joinLeft(array('tn' => 'r_testkitname_dts'), 'tn.TestKitName_ID=res.test_kit_name_2', array('TestKit_Name', 'TestKitName_ID'))
                ->group('tn.TestKitName_ID');
        } else if (isset($params['kitType']) && $params['kitType'] == "testkit3") {
            $sQuery = $sQuery->joinLeft(array('tn' => 'r_testkitname_dts'), 'tn.TestKitName_ID=res.test_kit_name_3', array('TestKit_Name', 'TestKitName_ID'))
                ->group('tn.TestKitName_ID');
        } else {
            $sQuery = $sQuery->joinLeft(array('tn' => 'r_testkitname_dts'), 'tn.TestKitName_ID=res.test_kit_name_1 or tn.TestKitName_ID=res.test_kit_name_2 or tn.TestKitName_ID=res.test_kit_name_3', array('TestKit_Name', 'TestKitName_ID'))
                ->group('tn.TestKitName_ID');
        }
        if (isset($params['reportType']) && $params['reportType'] == "network") {
            if (isset($params['networkValue']) && $params['networkValue'] != "") {
                $sQuery = $sQuery->where("p.network_tier = ?", $params['networkValue']);
            } else {
                $sQuery = $sQuery->joinLeft(array('n' => 'r_network_tiers'), 'p.network_tier=n.network_id', array());
            }
        }

        if (isset($params['reportType']) && $params['reportType'] == "affiliation") {
            if (isset($params['affiliateValue']) && $params['affiliateValue'] != "") {
                $iQuery = $dbAdapter->select()->from(array('rpa' => 'r_participant_affiliates'))
                    ->where('rpa.aff_id=?', $params['affiliateValue']);
                $iResult = $dbAdapter->fetchRow($iQuery);
                $appliate = $iResult['affiliate'];
                $sQuery = $sQuery->where('p.affiliation="' . $appliate . '" OR p.affiliation=' . $params['affiliateValue']);
            } else {
                $sQuery = $sQuery->joinLeft(array('pa' => 'r_participant_affiliates'), 'p.affiliation=pa.affiliate', array());
            }
            //echo $sQuery;die;
        }
        if (isset($params['reportType']) && $params['reportType'] == "region") {
            if (isset($params['regionValue']) && $params['regionValue'] != "") {
                $sQuery = $sQuery->where("p.region= ?", $params['regionValue']);
            } else {
                $sQuery = $sQuery->where("p.region IS NOT NULL")->where("p.region != ''");
            }
        }
        if (isset($params['reportType']) && $params['reportType'] == "enrolled-programs") {
            if (isset($params['enrolledProgramsValue']) && $params['enrolledProgramsValue'] != "") {
                $sQuery = $sQuery->joinLeft(array('pe' => 'participant_enrolled_programs_map'), 'pe.participant_id=p.participant_id', array())
                    ->joinLeft(array('rep' => 'r_enrolled_programs'), 'rep.r_epid=pe.ep_id', array('rep.enrolled_programs'))
                    ->where("rep.r_epid= ?", $params['enrolledProgramsValue']);
            } else {
                $sQuery = $sQuery->joinLeft(array('pe' => 'participant_enrolled_programs_map'), 'pe.participant_id=p.participant_id', array())
                    ->joinLeft(array('rep' => 'r_enrolled_programs'), 'rep.r_epid=pe.ep_id', array('rep.enrolled_programs'));
            }
        }

        if (isset($params['startDate']) && $params['startDate'] != "" && isset($params['endDate']) && $params['endDate'] != "") {

            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($params['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($params['endDate']));
        }
        $sQuery = $sQuery->where("tn.TestKit_Name IS NOT NULL");
        //echo $sQuery;die;
        return $dbAdapter->fetchAll($sQuery);
    }

    public function getTestKitDetailedReport($parameters)
    {
        //Zend_Debug::dump($parameters);die;
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        //    $aColumns = array('tn.TestKit_Name',new Zend_Db_Expr("CAST((COUNT('shipment_map_id')/s.number_of_samples) as UNSIGNED)"));

        $aColumns = array(
            'tn.TestKit_Name',
            new Zend_Db_Expr("CAST((COUNT('shipment_map_id')/s.number_of_samples) as UNSIGNED)")
        );
        $searchColumns = array(
            'tn.TestKit_Name',
            'totalTest'
        );
        $orderColumns = array(
            'tn.TestKit_Name',
            'totalTest'
        );

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
                $colSize = count($searchColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($searchColumns[$i] == "" || $searchColumns[$i] == null) {
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        //error_log($sHaving);
        /* Individual column filtering */
        for ($i = 0; $i < count($searchColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */



        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('res' => 'response_result_dts'), array('totalTest' => new Zend_Db_Expr("CAST((COUNT('shipment_map_id')/s.number_of_samples) as UNSIGNED)")))
            ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.map_id=res.shipment_map_id', array())
            ->joinLeft(array('p' => 'participant'), 'sp.participant_id=p.participant_id', array('p.lab_name', 'participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
            ->joinLeft(array('s' => 'shipment'), 's.shipment_id=sp.shipment_id', array());
        //  ->group("p.participant_id");

        if (isset($parameters['kitType']) && $parameters['kitType'] == "testkit1") {
            $sQuery = $sQuery->joinLeft(array('tn' => 'r_testkitname_dts'), 'tn.TestKitName_ID=res.test_kit_name_1', array('tn.TestKit_Name', 'TestKitName_ID'))
                ->group('tn.TestKitName_ID');
        } else if (isset($parameters['kitType']) && $parameters['kitType'] == "testkit2") {
            $sQuery = $sQuery->joinLeft(array('tn' => 'r_testkitname_dts'), 'tn.TestKitName_ID=res.test_kit_name_2', array('tn.TestKit_Name', 'TestKitName_ID'))
                ->group('tn.TestKitName_ID');
        } else if (isset($parameters['kitType']) && $parameters['kitType'] == "testkit3") {
            $sQuery = $sQuery->joinLeft(array('tn' => 'r_testkitname_dts'), 'tn.TestKitName_ID=res.test_kit_name_3', array('tn.TestKit_Name', 'TestKitName_ID'))
                ->group('tn.TestKitName_ID');
        } else {
            $sQuery = $sQuery->joinLeft(array('tn' => 'r_testkitname_dts'), 'tn.TestKitName_ID=res.test_kit_name_1 or tn.TestKitName_ID=res.test_kit_name_2 or tn.TestKitName_ID=res.test_kit_name_3', array('TestKit_Name', 'TestKitName_ID'))
                ->group('tn.TestKitName_ID');
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == "network") {
            if (isset($parameters['networkValue']) && $parameters['networkValue'] != "") {
                $sQuery = $sQuery->where("p.network_tier = ?", $parameters['networkValue']);
            } else {
                $sQuery = $sQuery->joinLeft(array('n' => 'r_network_tiers'), 'p.network_tier=n.network_id', array());
            }
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == "affiliation") {
            if (isset($parameters['affiliateValue']) && $parameters['affiliateValue'] != "") {
                $iQuery = $dbAdapter->select()->from(array('rpa' => 'r_participant_affiliates'))
                    ->where('rpa.aff_id=?', $parameters['affiliateValue']);
                $iResult = $dbAdapter->fetchRow($iQuery);
                $appliate = $iResult['affiliate'];
                $sQuery = $sQuery->where('p.affiliation="' . $appliate . '" OR p.affiliation=' . $parameters['affiliateValue']);
            } else {
                $sQuery = $sQuery->joinLeft(array('pa' => 'r_participant_affiliates'), 'p.affiliation=pa.affiliate', array());
            }
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == "enrolled-programs") {
            if (isset($parameters['enrolledProgramsValue']) && $parameters['enrolledProgramsValue'] != "") {
                $sQuery = $sQuery->joinLeft(array('pe' => 'participant_enrolled_programs_map'), 'pe.participant_id=p.participant_id', array())
                    ->joinLeft(array('rep' => 'r_enrolled_programs'), 'rep.r_epid=pe.ep_id', array('rep.enrolled_programs'))
                    ->where("rep.r_epid= ?", $parameters['enrolledProgramsValue']);
            } else {
                $sQuery = $sQuery->joinLeft(array('pe' => 'participant_enrolled_programs_map'), 'pe.participant_id=p.participant_id', array())
                    ->joinLeft(array('rep' => 'r_enrolled_programs'), 'rep.r_epid=pe.ep_id', array('rep.enrolled_programs'));
            }
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == "region") {
            if (isset($parameters['regionValue']) && $parameters['regionValue'] != "") {
                $sQuery = $sQuery->where("p.region= ?", $parameters['regionValue']);
            } else {
                $sQuery = $sQuery->where("p.region IS NOT NULL")->where("p.region != ''");
            }
        }
        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

            $sQuery = $sQuery->where("s.shipment_date >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("s.shipment_date <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }
        $sQuery = $sQuery->where("tn.TestKit_Name IS NOT NULL");

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->having($sWhere);
        }
        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }
        $sQuerySession = new Zend_Session_Namespace('TestkitActionsExcel');
        $sQuerySession->testkitActionsQuery = $sQuery;


        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }
        $rResult = $dbAdapter->fetchAll($sQuery);


        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */

        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = sizeof($aResultTotal);

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
            $row['DT_RowId'] = "testkitId" . $aRow['TestKitName_ID'];
            //  $row[] = $aRow['participantName'];
            $row[] = "<a href='javascript:void(0);' onclick='participantReport(\"" . $aRow['TestKitName_ID'] . "\",\"" . $aRow['TestKit_Name'] . "\")'>" . stripslashes($aRow['TestKit_Name']) . "</a>";
            $row[] = $aRow['totalTest'];
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getShipmentResponseCount($shipmentId, $date, $step = 5, $maxDays = 60)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        $responseResult = [];
        $responseDate = [];
        $initialStartDate = $date;
        for ($i = $step; $i <= $maxDays; $i += $step) {

            $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'), array(''))
                ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('reported_count' => new Zend_Db_Expr("SUM(response_status is not null AND response_status like 'responded')")))
                ->where("s.shipment_id = ?", $shipmentId)
                ->group('s.shipment_id');
            $date = new DateTime($date);
            $date->add(new DateInterval('P' . $i . 'D'));
            $endDate = $date->format('Y-m-d');
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if (!empty($authNameSpace->dm_id)) {
                $sQuery = $sQuery
                    ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=sp.participant_id', array())
                    ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
            }
            if (isset($date) && $date != "" && $endDate != '' && $i < $maxDays) {
                $sQuery = $sQuery->where("sp.shipment_test_date >= ?", $date);
                $sQuery = $sQuery->where("sp.shipment_test_date <= ?", $endDate);
                $result = $dbAdapter->fetchAll($sQuery);
                $count = (isset($result[0]['reported_count']) && $result[0]['reported_count'] != "") ? $result[0]['reported_count'] : 0;
                $responseResult[] = (int) $count;
                $responseDate[] = Pt_Commons_General::humanReadableDateFormat($date) . ' ' . Pt_Commons_General::humanReadableDateFormat($endDate);
                $endDate = new DateTime($endDate);
                $endDate->add(new DateInterval('P1D'));
                $date = $endDate->format('Y-m-d');
            }

            if ($i == $maxDays) {
                $sQuery = $sQuery->where("sp.shipment_test_date >= ?", $date);
                $result = $dbAdapter->fetchAll($sQuery);
                $count = (isset($result[0]['reported_count']) && $result[0]['reported_count'] != "") ? $result[0]['reported_count'] : 0;
                $responseResult[] = (int) $count;
                $responseDate[] = Pt_Commons_General::humanReadableDateFormat($date) . '  and Above';
            }
        }
        return json_encode($responseResult) . '#' . json_encode($responseDate);
    }

    public function getShipmentParticipant($shipmentId, $schemeType = null)
    {
        $schemeDb = new Application_Model_DbTable_SchemeList();
        $uc = $schemeDb->checkUserConfig($schemeType);
        if ($schemeType == 'dts') {
            $dtsObj = new Application_Model_Dts();
            return $dtsObj->generateDtsRapidHivExcelReport($shipmentId);
        } else if ($schemeType == 'vl') {
            $vlObj = new Application_Model_Vl();
            return $vlObj->generateDtsViralLoadExcelReport($shipmentId);
        } else if ($schemeType == 'eid') {
            $eidObj = new Application_Model_Eid();
            return $eidObj->generateDbsEidExcelReport($shipmentId);
        } else if ($schemeType == 'recency') {
            $recencyObj = new Application_Model_Recency();
            return $recencyObj->generateRecencyExcelReport($shipmentId);
        } else if ($schemeType == 'covid19') {
            $rcovid19Obj = new Application_Model_Covid19();
            return $rcovid19Obj->generateCovid19ExcelReport($shipmentId);
        } else if ($schemeType == 'tb') {
            $tbObj = new Application_Model_Tb();
            return $tbObj->generateTbExcelReport($shipmentId);
        } else if ($schemeType == 'generic-test' || $uc = 'yes') {
            $genericTestObj = new Application_Model_GenericTest();
            return $genericTestObj->generateGenericTestExcelReport($shipmentId, $schemeType);
        } else {
            return false;
        }
    }

    public function getShipmentsByScheme($schemeType, $startDate, $endDate)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sQuery = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date',))
            ->where("s.scheme_type = ?", $schemeType)
            ->order("s.shipment_id");
        if (isset($startDate) && !empty($startDate) && isset($endDate) && !empty($endDate)) {
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($startDate));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($endDate));
        }
        return $db->fetchAll($sQuery);
    }

    public function getCorrectiveActionReport($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array(new Zend_Db_Expr('count("cam.corrective_action_id")'), 'ca.corrective_action');
        $searchColumns = array('total_corrective', 'ca.corrective_action');
        $orderColumns = array(new Zend_Db_Expr('count("cam.corrective_action_id")'), 'ca.corrective_action');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = 'shipment_id';
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
                $colSize = count($searchColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($searchColumns[$i] == "" || $searchColumns[$i] == null) {
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        //error_log($sHaving);
        /* Individual column filtering */
        for ($i = 0; $i < count($searchColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */


        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();



        $totalQuery = $dbAdapter->select()->from(array('s' => 'shipment'), array("average_score"))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array(
                "total_shipped" => new Zend_Db_Expr('count("sp.map_id")'),
                "total_responses" => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
                "valid_responses" => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
            ));

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $totalQuery = $totalQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

            $totalQuery = $totalQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $totalQuery = $totalQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $totalQuery = $totalQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }
        //die($totalQuery);
        $totalResult = $dbAdapter->fetchRow($totalQuery);

        $totalShipped = ($totalResult['total_shipped']);
        $totalResp = ($totalResult['total_responses']);
        $validResp = ($totalResult['valid_responses']);
        $avgScore = ($totalResult['average_score']);

        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'), array())
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array())
            ->join(array('cam' => 'dts_shipment_corrective_action_map'), 'sp.map_id=cam.shipment_map_id', array("total_corrective" => new Zend_Db_Expr("count('corrective_action_id')")))
            ->join(array('ca' => 'r_dts_corrective_actions'), 'cam.corrective_action_id=ca.action_id', array("action_id", "corrective_action"))
            ->where("sp.is_excluded = 'no'")
            ->group(array('ca.action_id'));

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }


        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->having($sWhere);
        }


        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        $sQuerySession = new Zend_Session_Namespace('CorrectiveActionsExcel');
        $sQuerySession->correctiveActionsQuery = $sQuery;

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //echo $sQuery;die;
        $rResult = $dbAdapter->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sWhere = "";
        //$sQuery = $dbAdapter->select()->from(array('s'=>'shipment'), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));


        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array())
            ->join(array('cam' => 'dts_shipment_corrective_action_map'), 'sp.map_id=cam.shipment_map_id', array())
            ->join(array('ca' => 'r_dts_corrective_actions'), 'cam.corrective_action_id=ca.action_id', array())
            ->where("sp.is_excluded = 'no'")
            ->group(array('ca.action_id'));

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }

        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

        /*
         * Output
         */

        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array(),
            "totalShipped" => (int) $totalShipped,
            "totalResponses" => (int) $totalResp,
            "validResponses" => (int) $validResp,
            "averageScore" => round((float) $avgScore, 2)
        );

        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = $aRow['corrective_action'];
            $row[] = $aRow['total_corrective'];


            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getCorrectiveActionReportByShipmentId($shipmentId, $testType = "")
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'), array('s.shipment_code'))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id')
            ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('map_id'))
            ->join(array('cam' => 'dts_shipment_corrective_action_map'), 'cam.shipment_map_id=sp.map_id', array("total_corrective" => new Zend_Db_Expr('count("cam.corrective_action_id")')))
            ->join(array('ca' => 'r_dts_corrective_actions'), 'ca.action_id=cam.corrective_action_id', array("action_id", "corrective_action"))
            ->where("s.shipment_id = ?", $shipmentId)
            ->group(array('cam.corrective_action_id'))
            ->order(array('total_corrective DESC'));
        if (isset($testType) && !empty($testType)) {
            $sQuery = $sQuery->where("JSON_EXTRACT(sp.attributes, '$.dts_test_panel_type') = ?", $testType);
        }
        return $dbAdapter->fetchAll($sQuery);
    }

    public function exportParticipantTrendsReport($params)
    {

        $headings = array('Scheme', 'Shipment Date', 'Shipment Code', 'No. of Shipments', 'No. of Responses', 'No. of Valid Responses', 'No. of Passed Responses', 'Pass %');
        try {
            $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

            // \PhpOffice\PhpSpreadsheet\Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
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
            $sheet->mergeCells('A1:I1');
            $sheet->getCellByColumnAndRow(1, 1)->setValueExplicit(html_entity_decode('Participant Performance Overview Report', ENT_QUOTES, 'UTF-8'));
            if (isset($params['shipmentName']) && trim($params['shipmentName']) != "") {
                $sheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode('Shipment', ENT_QUOTES, 'UTF-8'));
                $sheet->getCellByColumnAndRow(2, 2)->setValueExplicit(html_entity_decode($params['shipmentName'], ENT_QUOTES, 'UTF-8'));
            }
            $sheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode('Selected Date Range', ENT_QUOTES, 'UTF-8'));
            $sheet->getCellByColumnAndRow(2, 3)->setValueExplicit(html_entity_decode($params['dateRange'], ENT_QUOTES, 'UTF-8'));

            $sheet->getStyleByColumnAndRow(1, 1, null, null)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(1, 2, null, null)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(1, 3, null, null)->getFont()->setBold(true);

            foreach ($headings as $field => $value) {
                $sheet->getCellByColumnAndRow($colNo + 1, 5)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                $sheet->getStyleByColumnAndRow($colNo + 1, 5, null, null)->getFont()->setBold(true);
                $colNo++;
            }

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuerySession = new Zend_Session_Namespace('ParticipantTrendsExcel');
            $rResult = $db->fetchAll($sQuerySession->participantQuery);
            foreach ($rResult as $aRow) {

                $row = [];
                $row[] = $aRow['scheme_name'];
                $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['shipment_date']);
                $row[] = $aRow['shipment_code'];
                $row[] = $aRow['total_shipped'];
                $row[] = $aRow['total_responses'];
                $row[] = $aRow['valid_responses'];
                $row[] = $aRow['total_passed'];
                $row[] = round($aRow['pass_percentage'], 2);
                //$row[] = round($aRow['average_score'], 2);
                $output[] = $row;
            }

            foreach ($output as $rowNo => $rowData) {
                $colNo = 0;
                foreach ($rowData as $field => $value) {
                    if (!isset($value)) {
                        $value = "";
                    }
                    $sheet->getCellByColumnAndRow($colNo + 1, $rowNo + 6)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(150);
                        $sheet->getStyleByColumnAndRow($colNo + 1, $rowNo + 6, null, null)->getAlignment()->setWrapText(true);
                    }
                    $colNo++;
                }
            }

            if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
                mkdir(TEMP_UPLOAD_PATH);
            }

            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'participant-trends-report-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            $sQuerySession->participantQuery = '';
            error_log("GENERATE-PARTICIPANT-TRENDS-REPORT-EXCEL--" . $exc->getMessage());
            error_log($exc->getTraceAsString());

            return "";
        }
    }

    public function exportCorrectiveActionsReport($params)
    {

        $headings = array('Corrective Action', 'No. of Responses having this corrective action');
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
            $sheet->mergeCells('A1:I1');
            $sheet->getCellByColumnAndRow(1, 1)->setValueExplicit(html_entity_decode('Participant Corrective Action Overview', ENT_QUOTES, 'UTF-8'));
            if (isset($params['shipmentName']) && trim($params['shipmentName']) != "") {
                $sheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode('Shipment', ENT_QUOTES, 'UTF-8'));
                $sheet->getCellByColumnAndRow(2, 2)->setValueExplicit(html_entity_decode($params['shipmentName'], ENT_QUOTES, 'UTF-8'));
            }
            $sheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode('Selected Date Range', ENT_QUOTES, 'UTF-8'));
            $sheet->getCellByColumnAndRow(2, 3)->setValueExplicit(html_entity_decode($params['dateRange'], ENT_QUOTES, 'UTF-8'));


            $sheet->getStyleByColumnAndRow(1, 1, null, null)->getFont()->setBold(true);

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $totalQuery = $db->select()->from(array('s' => 'shipment'), array("average_score"))
                ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array(
                    "total_shipped" => new Zend_Db_Expr('count("sp.map_id")'),
                    "total_responses" => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
                    "valid_responses" => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
                ));

            if (isset($params['scheme']) && $params['scheme'] != "") {
                $totalQuery = $totalQuery->where("s.scheme_type = ?", $params['scheme']);
            }

            if (isset($params['dateStartDate']) && $params['dateStartDate'] != "" && isset($params['dateEndDate']) && $params['dateEndDate'] != "") {
                $totalQuery = $totalQuery->where("DATE(s.shipment_date) >= ?", $params['dateStartDate']);
                $totalQuery = $totalQuery->where("DATE(s.shipment_date) <= ?", $params['dateEndDate']);
            }

            if (isset($params['shipmentId']) && $params['shipmentId'] != "") {
                $totalQuery = $totalQuery->where("s.shipment_id = ?", $params['shipmentId']);
            }
            //die($totalQuery);
            $totalResult = $db->fetchRow($totalQuery);

            $totalShipped = ($totalResult['total_shipped']);
            $totalResp = ($totalResult['total_responses']);
            $validResp = ($totalResult['valid_responses']);
            $avgScore = round($totalResult['average_score'], 2) . '%';

            $sheet->mergeCells('A4:B4');
            $sheet->getCellByColumnAndRow(1, 4)->setValueExplicit(html_entity_decode('Total shipped :' . $totalShipped, ENT_QUOTES, 'UTF-8'));
            $sheet->getStyleByColumnAndRow(1, 4, null, null)->getFont()->setBold(true);
            $sheet->mergeCells('A5:B5');
            $sheet->getCellByColumnAndRow(1, 5)->setValueExplicit(html_entity_decode('Total number of responses :' . $totalResp, ENT_QUOTES, 'UTF-8'));
            $sheet->getStyleByColumnAndRow(1, 5, null, null)->getFont()->setBold(true);
            $sheet->mergeCells('A6:B6');
            $sheet->getCellByColumnAndRow(1, 6)->setValueExplicit(html_entity_decode('Total number of valid responses :' . $validResp, ENT_QUOTES, 'UTF-8'));
            $sheet->getStyleByColumnAndRow(1, 6, null, null)->getFont()->setBold(true);
            $sheet->mergeCells('A7:B7');
            //$sheet->getCellByColumnAndRow(0, 7)->setValueExplicit(html_entity_decode('Average score :' . $avgScore, ENT_QUOTES, 'UTF-8'));
            //$sheet->getStyleByColumnAndRow(0, 7)->getFont()->setBold(true);

            foreach ($headings as $field => $value) {
                $sheet->getCellByColumnAndRow($colNo + 1, 9)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                $sheet->getStyleByColumnAndRow($colNo + 1, 9, null, null)->getFont()->setBold(true);
                $colNo++;
            }


            $sQuerySession = new Zend_Session_Namespace('CorrectiveActionsExcel');
            $rResult = $db->fetchAll($sQuerySession->correctiveActionsQuery);

            if (count($rResult) > 0) {
                foreach ($rResult as $aRow) {
                    $row = [];
                    $row[] = $aRow['corrective_action'];
                    $row[] = $aRow['total_corrective'];
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
                    $sheet->getCellByColumnAndRow($colNo + 1, $rowNo + 10)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(100);
                        $sheet->getStyleByColumnAndRow($colNo + 1, $rowNo + 10, null, null)->getAlignment()->setWrapText(true);
                    }
                    $colNo++;
                }
            }

            if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
                mkdir(TEMP_UPLOAD_PATH);
            }

            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'Participant-Corrective-Actions-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            $sQuerySession->correctiveActionsQuery = '';
            error_log("GENERATE-PARTICIPANT-CORRECTIVE-ACTIONS--REPORT-EXCEL--" . $exc->getMessage());
            error_log($exc->getTraceAsString());

            return "";
        }
    }

    public function exportShipmentsReport($params)
    {

        $headings = array('Scheme', 'Shipment Code', 'Sample Label', 'Reference Result', 'Total Positive Responses', 'Total Negative Responses', 'Total Indeterminate Responses', 'Total Responses', 'Total Valid Responses(Total - Excluded)', 'Total Passed');
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
            $sheet->mergeCells('A1:I1');
            $sheet->getCellByColumnAndRow(1, 1)->setValueExplicit(html_entity_decode('Shipment Response Overview', ENT_QUOTES, 'UTF-8'));
            if (isset($params['shipmentName']) && trim($params['shipmentName']) != "") {
                $sheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode('Shipment', ENT_QUOTES, 'UTF-8'));
                $sheet->getCellByColumnAndRow(2, 2)->setValueExplicit(html_entity_decode($params['shipmentName'], ENT_QUOTES, 'UTF-8'));
            }
            $sheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode('Selected Date Range', ENT_QUOTES, 'UTF-8'));
            $sheet->getCellByColumnAndRow(2, 3)->setValueExplicit(html_entity_decode($params['dateRange'], ENT_QUOTES, 'UTF-8'));


            $sheet->getStyleByColumnAndRow(1, 3, null, null)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(1, 2, null, null)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(1, 1, null, null)->getFont()->setBold(true);
            foreach ($headings as $field => $value) {
                $sheet->getCellByColumnAndRow($colNo + 1, 5)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                $sheet->getStyleByColumnAndRow($colNo + 1, 5, null, null)->getFont()->setBold(true);
                $colNo++;
            }

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuerySession = new Zend_Session_Namespace('shipmentExportExcel');
            $rResult = $db->fetchAll($sQuerySession->shipmentExportQuery);
            foreach ($rResult as $aRow) {

                $row = [];
                $row[] = $aRow['scheme_name'];
                $row[] = $aRow['shipment_code'];
                $row[] = $aRow['sample_label'];
                $row[] = $aRow['response'];
                $row[] = $aRow['positive_responses'];
                $row[] = $aRow['negative_responses'];
                $row[] = $aRow['invalid_responses'];
                $row[] = $aRow['total_responses'];
                $row[] = $aRow['valid_responses'];
                $row[] = $aRow['total_passed'];
                $output[] = $row;
            }

            foreach ($output as $rowNo => $rowData) {
                $colNo = 0;
                foreach ($rowData as $field => $value) {
                    if (!isset($value)) {
                        $value = "";
                    }
                    $sheet->getCellByColumnAndRow($colNo + 1, $rowNo + 6)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(150);
                        $sheet->getStyleByColumnAndRow($colNo + 1, $rowNo + 6, null, null)->getAlignment()->setWrapText(true);
                    }
                    $colNo++;
                }
            }

            if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
                mkdir(TEMP_UPLOAD_PATH);
            }

            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'shipment-response-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            $sQuerySession->shipmentExportQuery = '';
            error_log("GENERATE-SHIPMENT_RESPONSE-REPORT-EXCEL--" . $exc->getMessage());
            error_log($exc->getTraceAsString());

            return "";
        }
    }

    public function exportParticipantTrendsReportInPdf()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuerySession = new Zend_Session_Namespace('ParticipantTrendsExcel');
        return $db->fetchAll($sQuerySession->participantQuery);
    }

    public function exportCorrectiveActionsReportInPdf($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $totalQuery = $db->select()->from(array('s' => 'shipment'), array())
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array(
                "total_shipped" => new Zend_Db_Expr('count("sp.map_id")'),
                "total_responses" => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
                "valid_responses" => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
                "average_score" => new Zend_Db_Expr("((SUM(Case When sp.is_excluded='yes' Then 0 Else sp.shipment_score End)+SUM(Case When sp.is_excluded='yes' Then 0 Else sp.documentation_score End))/(SUM(final_result = 1) + SUM(final_result = 2)))")
            ));

        if (isset($params['scheme']) && $params['scheme'] != "") {
            $totalQuery = $totalQuery->where("s.scheme_type = ?", $params['scheme']);
        }

        if (isset($params['dateStartDate']) && $params['dateStartDate'] != "" && isset($params['dateEndDate']) && $params['dateEndDate'] != "") {

            $totalQuery = $totalQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($params['dateStartDate']));
            $totalQuery = $totalQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($params['dateEndDate']));
        }

        if (isset($params['shipmentId']) && $params['shipmentId'] != "") {
            $totalQuery = $totalQuery->where("s.shipment_id = ?", $params['shipmentId']);
        }
        //die($totalQuery);
        $totalResult = $db->fetchRow($totalQuery);

        $sQuerySession = new Zend_Session_Namespace('CorrectiveActionsExcel');
        $rResult = $db->fetchAll($sQuerySession->correctiveActionsQuery);

        return $result = array('countCorrectiveAction' => $totalResult, 'correctiveAction' => $rResult);
    }

    public function exportShipmentsReportInPdf()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuerySession = new Zend_Session_Namespace('shipmentExportExcel');
        return $db->fetchAll($sQuerySession->shipmentExportQuery);
    }

    public function getParticipantTrendsRegionWiseReport($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array(
            'p.region',
            new Zend_Db_Expr('count("sp.map_id")'),
            new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
            new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
            new Zend_Db_Expr("SUM(final_result = 1)"),
            new Zend_Db_Expr("((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100"),
            'average_score'
        );
        $searchColumns = array(
            'p.region',
            'total_responses',
            'valid_responses',
            'total_passed',
            'pass_percentage',
            'average_score'
        );
        $orderColumns = array(
            'p.region',
            new Zend_Db_Expr('count("sp.map_id")'),
            new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
            new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
            new Zend_Db_Expr("SUM(final_result = 1)"),
            new Zend_Db_Expr("((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100"),
            'average_score'
        );

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = 'shipment_id';
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
                $colSize = count($searchColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($searchColumns[$i] == "" || $searchColumns[$i] == null) {
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        //error_log($sHaving);
        /* Individual column filtering */
        for ($i = 0; $i < count($searchColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */


        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id')
            ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array("DATE_FORMAT(s.shipment_date,'%d-%b-%Y')", "total_shipped" => new Zend_Db_Expr('count("sp.map_id")'), "total_responses" => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"), "valid_responses" => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"), "total_passed" => new Zend_Db_Expr("(SUM(final_result = 1))"), "pass_percentage" => new Zend_Db_Expr("((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100"), "average_score" => new Zend_Db_Expr("((SUM(Case When sp.is_excluded='yes' Then 0 Else sp.shipment_score End)+SUM(Case When sp.is_excluded='yes' Then 0 Else sp.documentation_score End))/(SUM(final_result = 1) + SUM(final_result = 2)))")))
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('region'))
            ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id')
            ->group(array('p.region'));

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

            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->having($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        $sQuerySession = new Zend_Session_Namespace('ParticipantTrendsExcel');
        $sQuerySession->participantRegionQuery = $sQuery;

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }


        $rResult = $dbAdapter->fetchAll($sQuery);


        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sWhere = "";
        //$sQuery = $dbAdapter->select()->from(array('s'=>'shipment'), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));


        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id')
            ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array())
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('region'))
            ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id')
            ->group(array('p.region'));
        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }

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


        foreach ($rResult as $aRow) {


            $row = [];

            $row[] = $aRow['region'];
            $row[] = $aRow['total_shipped'];
            $row[] = $aRow['total_responses'];
            $row[] = $aRow['valid_responses'];
            $row[] = $aRow['total_passed'];
            $row[] = round($aRow['pass_percentage'], 2);
            $row[] = round($aRow['average_score'], 2);


            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getParticipantPerformanceRegionWiseReport($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array(
            'p.region',
            new Zend_Db_Expr('count("sp.map_id")'),
            new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
            new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
            new Zend_Db_Expr("SUM(final_result = 1)"),
            new Zend_Db_Expr("((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100"),
            'average_score'
        );
        $searchColumns = array(
            'p.region',
            'total_responses',
            'valid_responses',
            'total_passed',
            'pass_percentage',
            'average_score'
        );
        $orderColumns = array(
            'p.region',
            new Zend_Db_Expr('count("sp.map_id")'),
            new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
            new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
            new Zend_Db_Expr("SUM(final_result = 1)"),
            new Zend_Db_Expr("((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100"),
            'average_score'
        );

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = 'shipment_id';
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
                $colSize = count($searchColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($searchColumns[$i] == "" || $searchColumns[$i] == null) {
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        //error_log($sHaving);
        /* Individual column filtering */
        for ($i = 0; $i < count($searchColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */


        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id')
            ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array("DATE_FORMAT(s.shipment_date,'%d-%b-%Y')", "total_shipped" => new Zend_Db_Expr('count("sp.map_id")'), "total_responses" => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"), "valid_responses" => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"), "total_passed" => new Zend_Db_Expr("(SUM(final_result = 1))"), "pass_percentage" => new Zend_Db_Expr("((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100"), "average_score" => new Zend_Db_Expr("((SUM(Case When sp.is_excluded='yes' Then 0 Else sp.shipment_score End)+SUM(Case When sp.is_excluded='yes' Then 0 Else sp.documentation_score End))/(SUM(final_result = 1) + SUM(final_result = 2)))")))
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('region'))
            ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id')
            ->group(array('p.region'));

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1 && !empty($authNameSpace->ptccMappedCountries)) {
            $sQuery = $sQuery->where("p.country IN(" . $authNameSpace->ptccMappedCountries . ")");
        } else if (isset($authNameSpace->mappedParticipants) && !empty($authNameSpace->mappedParticipants)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->having($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        $sQuerySession = new Zend_Session_Namespace('participantPerformanceExcel');
        $sQuerySession->participantRegionQuery = $sQuery;

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }


        $rResult = $dbAdapter->fetchAll($sQuery);


        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sWhere = "";
        //$sQuery = $dbAdapter->select()->from(array('s'=>'shipment'), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));


        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id')
            ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array())
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('region'))
            ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id')
            ->group(array('p.region'));
        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }

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


        foreach ($rResult as $aRow) {


            $row = [];

            $row[] = $aRow['region'];
            $row[] = $aRow['total_shipped'];
            $row[] = $aRow['total_responses'];
            $row[] = $aRow['valid_responses'];
            $row[] = $aRow['total_passed'];
            $row[] = round($aRow['pass_percentage'], 2);
            $row[] = round($aRow['average_score'], 2);


            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
    public function getChartInfo($parameters)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'))->columns(array('shipment_code'))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array(''))
            ->joinLeft(
                array('sp' => 'shipment_participant_map'),
                'sp.shipment_id=s.shipment_id',
                array(
                    "shipmentDate" => new Zend_Db_Expr("DATE_FORMAT(s.shipment_date,'%d-%b-%Y')"),
                    "total_shipped" => new Zend_Db_Expr('count("sp.map_id")'),
                    "beforeDueDate" => new Zend_Db_Expr("SUM(sp.shipment_test_report_date <= s.lastdate_response)"),
                    "afterDueDate" => new Zend_Db_Expr("SUM(sp.shipment_test_report_date > s.lastdate_response)"),
                    "pass_percentage" => new Zend_Db_Expr("((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100")
                )
            )
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('region'))
            ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id', array(''));

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }
        return $dbAdapter->fetchRow($sQuery);
    }

    public function getAberrantChartInfo($parameters)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'))->columns(array('shipment_code'))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array(''))
            ->joinLeft(
                array('sp' => 'shipment_participant_map'),
                'sp.shipment_id=s.shipment_id',
                array(
                    "shipmentDate" => new Zend_Db_Expr("DATE_FORMAT(s.shipment_date,'%d-%b-%Y')"),
                    "total_shipped" => new Zend_Db_Expr('count("sp.map_id")'),
                    "fail_percentage" => new Zend_Db_Expr("((SUM(final_result = 2))/(SUM(final_result = 2) + SUM(final_result = 1)))*100"),
                    "pass_percentage" => new Zend_Db_Expr("((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100")
                )
            );
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=sp.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }
        // die($sQuery);
        $rResult = $dbAdapter->fetchRow($sQuery);
        $rResult['failed'] = $this->getFaileParticipants($parameters);
        return $rResult;
    }

    public function getFaileParticipants($parameters)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'))->columns(array('shipment_code'))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array(''))
            ->joinLeft(
                array('sp' => 'shipment_participant_map'),
                'sp.shipment_id=s.shipment_id',
                array(
                    "shipmentDate" => new Zend_Db_Expr("DATE_FORMAT(s.shipment_date,'%d-%b-%Y')"),
                    "total_shipped" => new Zend_Db_Expr('count("sp.map_id")'),
                    "network_id" => new Zend_Db_Expr('count("p.network_tier")'),
                    "beforeDueDate" => new Zend_Db_Expr("SUM(DATE(sp.shipment_test_report_date) <= DATE(s.lastdate_response))"),
                    "afterDueDate" => new Zend_Db_Expr("SUM(DATE(sp.shipment_test_report_date) > DATE(s.lastdate_response))"),
                    "fail_percentage" => new Zend_Db_Expr("((SUM(final_result = 2))/(SUM(final_result = 2) + SUM(final_result = 1)))*100"),
                )
            )
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('participant_id', 'institute_name', 'region'))
            ->joinLeft(array('rn' => 'r_network_tiers'), 'p.network_tier=rn.network_id', array('network_name'))
            ->where('final_result = 2')
            ->group(array('p.network_tier'));

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }
        // die($sQuery);
        $rResult = $dbAdapter->fetchAll($sQuery);
        $row = [];
        foreach ($rResult as $key => $aRow) {
            $row['network_name'][$key]      = '"' . $aRow['network_name'] . '"';
            $row['totalShipped'][$key]      = '"N=' . $aRow['total_shipped'] . '"';
            $row['beforeDueDate'][$key]     = round($aRow['beforeDueDate'], 2);
            $row['afterDueDate'][$key]      = round($aRow['afterDueDate'], 2);
            $row['fail_percentage'][$key]   = round($aRow['fail_percentage'], 2);
            $row['network_id'][$key]        = round($aRow['network_id'], 2);
        }
        return $row;
    }
    public function exportParticipantTrendsRegionReport($params)
    {
        $headings = array('Region', 'No. of Shipments', 'No. of Responses', 'No. of Valid Responses', 'No. of Passed Responses', 'Pass %');
        try {
            $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

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
            $sheet->mergeCells('A1:I1');
            $sheet->getCellByColumnAndRow(1, 1)->setValueExplicit(html_entity_decode('Region Wise Participant Performance Report ', ENT_QUOTES, 'UTF-8'));

            $sheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode('Scheme', ENT_QUOTES, 'UTF-8'));
            $sheet->getCellByColumnAndRow(2, 2)->setValueExplicit(html_entity_decode($params['selectedScheme'], ENT_QUOTES, 'UTF-8'));

            $sheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode('Shipment Date', ENT_QUOTES, 'UTF-8'));
            $sheet->getCellByColumnAndRow(2, 3)->setValueExplicit(html_entity_decode($params['selectedDate'], ENT_QUOTES, 'UTF-8'));

            $sheet->getCellByColumnAndRow(1, 4)->setValueExplicit(html_entity_decode('Shipment Code', ENT_QUOTES, 'UTF-8'));
            $sheet->getCellByColumnAndRow(2, 4)->setValueExplicit(html_entity_decode($params['selectedCode'], ENT_QUOTES, 'UTF-8'));

            $sheet->getStyleByColumnAndRow(1, 1, null, null)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(1, 2, null, null)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(1, 3, null, null)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(1, 4, null, null)->getFont()->setBold(true);

            foreach ($headings as $field => $value) {
                $sheet->getCellByColumnAndRow($colNo + 1, 6)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                $sheet->getStyleByColumnAndRow($colNo + 1, 6, null, null)->getFont()->setBold(true);
                $colNo++;
            }

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuerySession = new Zend_Session_Namespace('ParticipantTrendsExcel');
            $rResult = $db->fetchAll($sQuerySession->participantRegionQuery);
            foreach ($rResult as $aRow) {
                $row = [];
                $row[] = $aRow['region'];
                $row[] = $aRow['total_shipped'];
                $row[] = $aRow['total_responses'];
                $row[] = $aRow['valid_responses'];
                $row[] = $aRow['total_passed'];
                $row[] = round($aRow['pass_percentage'], 2);
                //$row[] = round($aRow['average_score'], 2);
                $output[] = $row;
            }

            foreach ($output as $rowNo => $rowData) {
                $colNo = 0;
                foreach ($rowData as $field => $value) {
                    if (!isset($value)) {
                        $value = "";
                    }
                    $sheet->getCellByColumnAndRow($colNo + 1, $rowNo + 7)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(150);
                        $sheet->getStyleByColumnAndRow($colNo + 1, $rowNo + 7, null, null)->getAlignment()->setWrapText(true);
                    }
                    $colNo++;
                }
            }

            if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
                mkdir(TEMP_UPLOAD_PATH);
            }

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'participant-performance-region-wise' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            return "";
            $sQuerySession->participantRegionQuery = '';
            error_log("GENERATE-PARTICIPANT-PERFORMANCE-REGION-WISE-REPORT-EXCEL--" . $exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }

    public function exportParticipantPerformanceRegionReport($params)
    {
        $headings = array('Region', 'No. of Shipments', 'No. of Responses', 'No. of Valid Responses', 'No. of Passed Responses', 'Pass %');
        try {
            $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

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
            $sheet->mergeCells('A1:I1');
            $sheet->getCellByColumnAndRow(1, 1)->setValueExplicit(html_entity_decode('Region Wise Participant Performance Report ', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            $sheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode('Scheme', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getCellByColumnAndRow(2, 2)->setValueExplicit(html_entity_decode($params['selectedScheme'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            $sheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode('Shipment Date', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getCellByColumnAndRow(2, 3)->setValueExplicit(html_entity_decode($params['selectedDate'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            $sheet->getCellByColumnAndRow(1, 4)->setValueExplicit(html_entity_decode('Shipment Code', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getCellByColumnAndRow(2, 4)->setValueExplicit(html_entity_decode($params['selectedCode'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            $sheet->getStyleByColumnAndRow(1, 1, null, null)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(1, 2, null, null)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(1, 3, null, null)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(1, 4, null, null)->getFont()->setBold(true);

            foreach ($headings as $field => $value) {
                $sheet->getCellByColumnAndRow($colNo + 1, 6)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getStyleByColumnAndRow($colNo + 1, 6, null, null)->getFont()->setBold(true);
                $colNo++;
            }

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuerySession = new Zend_Session_Namespace('participantPerformanceExcel');
            $rResult = $db->fetchAll($sQuerySession->participantRegionQuery);
            foreach ($rResult as $aRow) {
                $row = [];
                $row[] = $aRow['region'];
                $row[] = $aRow['total_shipped'];
                $row[] = $aRow['total_responses'];
                $row[] = $aRow['valid_responses'];
                $row[] = $aRow['total_passed'];
                $row[] = round($aRow['pass_percentage'], 2);
                //$row[] = round($aRow['average_score'], 2);
                $output[] = $row;
            }

            foreach ($output as $rowNo => $rowData) {
                $colNo = 0;
                foreach ($rowData as $field => $value) {
                    if (!isset($value)) {
                        $value = "";
                    }
                    $sheet->getCellByColumnAndRow($colNo + 1, $rowNo + 7)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(150);
                        $sheet->getStyleByColumnAndRow($colNo + 1, $rowNo + 7, null, null)->getAlignment()->setWrapText(true);
                    }
                    $colNo++;
                }
            }

            if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
                mkdir(TEMP_UPLOAD_PATH);
            }

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'participant-performance-region-wise' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            return "";
            $sQuerySession->participantRegionQuery = '';
            error_log("GENERATE-PARTICIPANT-PERFORMANCE-REGION-WISE-REPORT-EXCEL--" . $exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }

    public function getTestKitParticipantReport($parameters)
    {
        //Zend_Debug::dump($parameters);die;
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        if (isset($parameters['reportType']) && $parameters['reportType'] == "network") {
            $aColumns = array('p.first_name', 'network_name');
        } else if (isset($parameters['reportType']) && $parameters['reportType'] == "affiliation") {
            $aColumns = array('p.first_name', 'affiliate');
        } else if (isset($parameters['reportType']) && $parameters['reportType'] == "region") {
            $aColumns = array('p.first_name', 'region');
        } else {
            $aColumns = array('p.first_name');
        }

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

        //error_log($sHaving);
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
        $sQuery = $dbAdapter->select()->from(array('res' => 'response_result_dts'), array())
            ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.map_id=res.shipment_map_id', array())
            ->joinLeft(array('p' => 'participant'), 'sp.participant_id=p.participant_id', array('p.first_name', 'p.last_name', 'p.region', 'p.affiliation'))
            ->joinLeft(array('s' => 'shipment'), 's.shipment_id=sp.shipment_id', array())
            ->group("p.participant_id");

        if (isset($parameters['kitType']) && $parameters['kitType'] == "testkit1") {
            $sQuery = $sQuery->joinLeft(array('tn' => 'r_testkitname_dts'), 'tn.TestKitName_ID=res.test_kit_name_1', array())->where("tn.TestKitName_ID = ?", $parameters['testkitId']);
        } else if (isset($parameters['kitType']) && $parameters['kitType'] == "testkit2") {
            $sQuery = $sQuery->joinLeft(array('tn' => 'r_testkitname_dts'), 'tn.TestKitName_ID=res.test_kit_name_2', array())->where("tn.TestKitName_ID = ?", $parameters['testkitId']);
        } else if (isset($parameters['kitType']) && $parameters['kitType'] == "testkit3") {
            $sQuery = $sQuery->joinLeft(array('tn' => 'r_testkitname_dts'), 'tn.TestKitName_ID=res.test_kit_name_3', array())->where("tn.TestKitName_ID = ?", $parameters['testkitId']);
        } else {
            $sQuery = $sQuery->joinLeft(array('tn' => 'r_testkitname_dts'), 'tn.TestKitName_ID=res.test_kit_name_1 or tn.TestKitName_ID=res.test_kit_name_2 or tn.TestKitName_ID=res.test_kit_name_3', array('TestKit_Name', 'TestKitName_ID'))
                ->group('tn.TestKitName_ID');
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == "network") {
            if (isset($parameters['networkValue']) && $parameters['networkValue'] != "") {
                $sQuery = $sQuery->joinLeft(array('n' => 'r_network_tiers'), 'p.network_tier=n.network_id', array('network_name'))->where("p.network_tier = ?", $parameters['networkValue']);
            } else {
                $sQuery = $sQuery->joinLeft(array('n' => 'r_network_tiers'), 'p.network_tier=n.network_id', array('network_name'));
            }
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == "affiliation") {
            if (isset($parameters['affiliateValue']) && $parameters['affiliateValue'] != "") {
                $iQuery = $dbAdapter->select()->from(array('rpa' => 'r_participant_affiliates'), array('affiliation' => 'affiliate'))
                    ->where('rpa.aff_id=?', $parameters['affiliateValue']);
                $iResult = $dbAdapter->fetchRow($iQuery);
                $appliate = $iResult['affiliation'];
                $sQuery = $sQuery->where('p.affiliation="' . $appliate . '" OR p.affiliation=' . $parameters['affiliateValue']);
            } else {
                $sQuery = $sQuery->joinLeft(array('pa' => 'r_participant_affiliates'), 'p.affiliation=pa.affiliate', array('affiliation' => 'affiliate'));
            }
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == "region") {
            if (isset($parameters['regionValue']) && $parameters['regionValue'] != "") {
                $sQuery = $sQuery->where("p.region= ?", $parameters['regionValue']);
            } else {
                $sQuery = $sQuery->where("p.region IS NOT NULL")->where("p.region != ''");
            }
        }
        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

            $sQuery = $sQuery->where("s.shipment_date >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("s.shipment_date <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }
        $sQuery = $sQuery->where("tn.TestKit_Name IS NOT NULL");

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->having($sWhere);
        }
        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }
        $rResult = $dbAdapter->fetchAll($sQuery);


        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */

        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = sizeof($aResultTotal);

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
            $row[] = $aRow['first_name'] . ' ' . $aRow['last_name'];
            if (isset($parameters['reportType']) && $parameters['reportType'] == "network") {
                $row[] = $aRow['network_name'];
            } else if (isset($parameters['reportType']) && $parameters['reportType'] == "affiliation") {
                $row[] = $aRow['affiliation'];
            } else if (isset($parameters['reportType']) && $parameters['reportType'] == "region") {
                $row[] = $aRow['region'];
            } else {
                $row[] = '';
            }
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function generatePdfTestKitDetailedReport($parameters)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuerySession = new Zend_Session_Namespace('TestkitActionsExcel');
        $rResult = $dbAdapter->fetchAll($sQuerySession->testkitActionsQuery);
        $pResult = '';
        if (isset($parameters['testkitId']) && $parameters['testkitId'] != '') {
            $sQuery = $dbAdapter->select()->from(array('res' => 'response_result_dts'), array())
                ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.map_id=res.shipment_map_id', array())
                ->joinLeft(array('p' => 'participant'), 'sp.participant_id=p.participant_id', array('p.first_name', 'p.last_name', 'p.region', 'p.affiliation'))
                ->joinLeft(array('s' => 'shipment'), 's.shipment_id=sp.shipment_id', array())
                ->group("p.participant_id");

            if (isset($parameters['kitType']) && $parameters['kitType'] == "testkit1") {
                $sQuery = $sQuery->joinLeft(array('tn' => 'r_testkitname_dts'), 'tn.TestKitName_ID=res.test_kit_name_1', array())->where("tn.TestKitName_ID = ?", $parameters['testkitId']);
            }
            if (isset($parameters['kitType']) && $parameters['kitType'] == "testkit2") {
                $sQuery = $sQuery->joinLeft(array('tn' => 'r_testkitname_dts'), 'tn.TestKitName_ID=res.test_kit_name_2', array())->where("tn.TestKitName_ID = ?", $parameters['testkitId']);
            }
            if (isset($parameters['kitType']) && $parameters['kitType'] == "testkit3") {
                $sQuery = $sQuery->joinLeft(array('tn' => 'r_testkitname_dts'), 'tn.TestKitName_ID=res.test_kit_name_3', array())->where("tn.TestKitName_ID = ?", $parameters['testkitId']);
            }
            if (isset($parameters['reportType']) && $parameters['reportType'] == "network") {
                if (isset($parameters['networkValue']) && $parameters['networkValue'] != "") {
                    $sQuery = $sQuery->joinLeft(array('n' => 'r_network_tiers'), 'p.network_tier=n.network_id', array('network_name'))->where("p.network_tier = ?", $parameters['networkValue']);
                } else {
                    $sQuery = $sQuery->joinLeft(array('n' => 'r_network_tiers'), 'p.network_tier=n.network_id', array('network_name'));
                }
            }
            if (isset($parameters['reportType']) && $parameters['reportType'] == "affiliation") {
                if (isset($parameters['affiliateValue']) && $parameters['affiliateValue'] != "") {
                    $iQuery = $dbAdapter->select()->from(array('rpa' => 'r_participant_affiliates'), array('affiliation' => 'affiliate'))
                        ->where('rpa.aff_id=?', $parameters['affiliateValue']);
                    $iResult = $dbAdapter->fetchRow($iQuery);
                    $appliate = $iResult['affiliation'];
                    $sQuery = $sQuery->where('p.affiliation="' . $appliate . '" OR p.affiliation=' . $parameters['affiliateValue']);
                } else {
                    $sQuery = $sQuery->joinLeft(array('pa' => 'r_participant_affiliates'), 'p.affiliation=pa.affiliate', array('affiliation' => 'affiliate'));
                }
            }
            if (isset($parameters['reportType']) && $parameters['reportType'] == "region") {
                if (isset($parameters['regionValue']) && $parameters['regionValue'] != "") {
                    $sQuery = $sQuery->where("p.region= ?", $parameters['regionValue']);
                } else {
                    $sQuery = $sQuery->where("p.region IS NOT NULL")->where("p.region != ''");
                }
            }
            if (isset($parameters['reportType']) && $parameters['reportType'] == "enrolled-programs") {
                if (isset($parameters['enrolledProgramsValue']) && $parameters['enrolledProgramsValue'] != "") {
                    $sQuery = $sQuery->joinLeft(array('pe' => 'participant_enrolled_programs_map'), 'pe.participant_id=p.participant_id', array())
                        ->joinLeft(array('rep' => 'r_enrolled_programs'), 'rep.r_epid=pe.ep_id', array('rep.enrolled_programs'))
                        ->where("rep.r_epid= ?", $parameters['enrolledProgramsValue']);
                } else {
                    $sQuery = $sQuery->joinLeft(array('pe' => 'participant_enrolled_programs_map'), 'pe.participant_id=p.participant_id', array())
                        ->joinLeft(array('rep' => 'r_enrolled_programs'), 'rep.r_epid=pe.ep_id', array('rep.enrolled_programs'));
                }
            }
            if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

                $sQuery = $sQuery->where("s.shipment_date >= ?", $this->common->isoDateFormat($parameters['startDate']));
                $sQuery = $sQuery->where("s.shipment_date <= ?", $this->common->isoDateFormat($parameters['endDate']));
            }
            $sQuery = $sQuery->where("tn.TestKit_Name IS NOT NULL");
            $pResult = $dbAdapter->fetchAll($sQuery);
        }
        $pieChart = $this->getTestKitReport($parameters);

        return array('testkitDtsReport' => $rResult, 'testkitDtsParticipantReport' => $pResult, 'testkitChart' => $pieChart);
    }

    //get vl assay distribution
    public function getAllVlAssayDistributionReports($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array(
            'sl.scheme_name',
            "DATE_FORMAT(s.shipment_date,'%d-%b-%Y')",
            's.shipment_code',
            'sp.shipment_score',
            'sp.documentation_score',
            "DATE_FORMAT(sp.shipment_test_date,'%d-%b-%Y')",
            "DATE_FORMAT(sp.shipment_receipt_date,'%d-%b-%Y')",
        );
        $searchColumns = array(
            'sl.scheme_name',
            "DATE_FORMAT(s.shipment_date,'%d-%b-%Y')",
            's.shipment_code',
            'sp.shipment_score',
            'sp.documentation_score',
            "DATE_FORMAT(sp.shipment_test_date,'%d-%b-%Y')",
            "DATE_FORMAT(sp.shipment_receipt_date,'%d-%b-%Y')",
        );
        $orderColumns = array(
            'sl.scheme_name',
            "s.shipment_date",
            's.shipment_code',
            'sp.shipment_score',
            'sp.documentation_score',
            'sp.shipment_test_date',
            'sp.shipment_receipt_date',
        );

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = 'map_id';
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
                $colSize = count($searchColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($searchColumns[$i] == "" || $searchColumns[$i] == null) {
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        //error_log($sHaving);
        /* Individual column filtering */
        for ($i = 0; $i < count($searchColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */


        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id')
            ->join(
                array('sp' => 'shipment_participant_map'),
                'sp.shipment_id=s.shipment_id',
                array("s.shipment_date", "sp.shipment_test_date", "sp.shipment_receipt_date", "sp.shipment_score", "sp.documentation_score")
            )
            ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id')
            ->where("s.scheme_type ='vl'");

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //echo ($sQuery);die;

        $rResult = $dbAdapter->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sWhere = "";
        $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id')
            ->join(
                array('sp' => 'shipment_participant_map'),
                'sp.shipment_id=s.shipment_id',
                array("s.shipment_date", "sp.shipment_test_date", "sp.shipment_receipt_date", "sp.shipment_score", "sp.documentation_score")
            )
            ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id')
            ->where("s.scheme_type ='vl'");

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {

            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

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

        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = $aRow['lab_name'];
            $row[] = $aRow['shipment_score'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['shipment_test_date']);
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['shipment_receipt_date']);
            $output['aaData'][] = $row;
        }
        echo json_encode($output);
    }

    //vl assay particpant count pie chart
    public function getAllVlAssayParticipantCount($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $shipmentId = null;
        if (isset($params['shipmentId']) && $params['shipmentId'] != "") {
            $shipmentId = $params['shipmentId'];
        }
        $vlQuery = $db->select()->from(array('vl' => 'r_vl_assay'), array('vl.id', 'vl.name', 'vl.short_name'))->where("`status` like 'active'");
        $assayResult = $db->fetchAll($vlQuery);


        $i = 0;
        $vlParticipantCount = [];
        foreach ($assayResult as $assayRow) {
            $cQuery = $db->select()->from(array('sp' => 'shipment_participant_map'), array('sp.map_id', 'sp.attributes'));
            if ($shipmentId != null) {
                $cQuery = $cQuery->where("sp.shipment_id='" . $shipmentId . "'");
            }

            $cResult = $db->fetchAll($cQuery);
            $k = 0;
            foreach ($cResult as $val) {
                $valAttributes = json_decode($val['attributes'], true);
                if ($assayRow['id'] == $valAttributes['vl_assay']) {
                    $k = $k + 1;
                }
            }
            $vlParticipantCount[$i]['count']  = $k;
            $vlParticipantCount[$i]['name']  = $assayRow['short_name'];
            $i++;
        }
        return $vlParticipantCount;
    }
    public function getAllVlSampleResult($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $totalResult = [];
        if ($params['shipmentId'] != '') {
            $shipmentId = $params['shipmentId'];
            $shQuery = $db->select()->from(array('s' => 'shipment'))->where("s.shipment_id='" . $shipmentId . "'");
            $shimentResult = $db->fetchAll($shQuery);
        } else {
            $shQuery = $db->select()->from(array('s' => 'shipment'))->where("s.scheme_type='vl'");
            if (isset($params['start']) && $params['start'] != "" && isset($params['end']) && $params['end'] != "") {
                $shQuery = $shQuery->where("DATE(s.shipment_date) >= ?", $params['start']);
                $shQuery = $shQuery->where("DATE(s.shipment_date) <= ?", $params['end']);
            }
            $shimentResult = $db->fetchAll($shQuery);
        }
        if ($shimentResult) {
            $vlQuery = $db->select()->from(array('vl' => 'r_vl_assay'), array('vl.id', 'vl.name', 'vl.short_name'))->where("`status` like 'active'");
            $assayResult = $db->fetchAll($vlQuery);
            $s = 0;
            foreach ($shimentResult as $shipData) {
                $shipmentId = $shipData['shipment_id'];
                $i = 0;
                $totalResult = [];
                foreach ($assayResult as $assayRow) {
                    $a = 0;
                    $f = 0;
                    $e = 0;
                    $cQuery = $db->select()->from(array('sp' => 'shipment_participant_map'), array('sp.map_id', 'sp.attributes'))
                        ->where("sp.shipment_id='" . $shipmentId . "'");
                    $cResult = $db->fetchAll($cQuery);
                    foreach ($cResult as $val) {
                        $valAttributes = json_decode($val['attributes'], true);
                        if ($assayRow['id'] == $valAttributes['vl_assay']) {
                            //check pass result
                            $pQuery = $db->select()->from(array('rrv' => 'response_result_vl'), array('passResult' => new Zend_Db_Expr("SUM(IF(rrv.calculated_score='pass',1,0))"), 'failResult' => new Zend_Db_Expr("SUM(IF(rrv.calculated_score='fail',1,0))"), 'exResult' => new Zend_Db_Expr("SUM(IF(rrv.calculated_score='excluded',1,0))")))
                                ->where("rrv.shipment_map_id='" . $val['map_id'] . "'")
                                ->group("rrv.shipment_map_id");
                            $pResult = $db->fetchRow($pQuery);
                            if ($pResult) {
                                $a = $a + $pResult['passResult'];
                                $f = $f + $pResult['failResult'];
                                $e = $e + $pResult['exResult'];
                            }
                        }
                    }
                    $totalResult[$s][$i]['accept'] = $a;
                    $totalResult[$s][$i]['fail'] = $f;
                    $totalResult[$s][$i]['excluded'] = $e;
                    $totalResult[$s][$i]['name']  = $assayRow['short_name'];
                    $i++;
                }
            }
            $resultAccept = [];
            $resultFail = [];
            $resultEx = [];
            foreach ($totalResult as $result) {
                foreach ($result as $data) {
                    array_push($resultAccept, $data['accept']);
                    array_push($resultFail, $data['fail']);
                    array_push($resultEx, $data['excluded']);
                }
            }
            $resultAcc[] = $resultAccept;
            $resultFa[] = $resultFail;
            $resultExe[] = $resultEx;

            $resultAcc['name'] = 'Acceptable Result';
            $resultFa['name'] = 'Unacceptable Result';
            $resultExe['name'] = 'Excluded from evaluation';
            $totalResult = array($resultAcc, $resultFa, $resultExe, 'nameList' => $totalResult);
        }
        return $totalResult;
    }

    public function getShipmentsByDate($schemeType, $startDate, $endDate)
    {
        $resultArray = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sQuery = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date',))
            ->where("s.status <= ?", 'finalized')
            ->order("s.shipment_id");
        if (!empty($startDate) && !empty($endDate)) {
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($startDate));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($endDate));
        }
        if (isset($schemeType) && !empty($schemeType)) {
            $sWhere = "";
            foreach ($schemeType as $val) {
                if ($sWhere != "") {
                    $sWhere .= " OR ";
                }
                $sWhere .= " s.scheme_type='" . $val . "' ";
            }
            if (!empty($sWhere)) {
                $sQuery = $sQuery->where($sWhere);
            }
        }

        $resultArray = $db->fetchAll($sQuery);
        return $resultArray;
    }

    public function getAnnualReport($params)
    {
        if (isset($params['startDate']) && trim($params['startDate']) != "" && trim($params['endDate']) != "") {

            $startDate = $this->common->isoDateFormat($params['startDate']);
            $endDate = $this->common->isoDateFormat($params['endDate']);

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $query = $db->select()
                ->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date', 's.lastdate_response'))
                ->where("DATE(s.shipment_date) >= ?", $startDate)
                ->where("DATE(s.shipment_date) <= ?", $endDate)
                ->order("s.scheme_type");

            if (isset($params['scheme']) && !empty($params['scheme']) && count($params['scheme']) > 0) {
                $sWhere = "";
                foreach ($params['scheme'] as $val) {
                    if ($sWhere != "") {
                        $sWhere .= " OR ";
                    }
                    $sWhere .= "s.scheme_type='" . $val . "'";
                }
                if (!empty($sWhere)) {
                    $query = $query->where($sWhere);
                }
            }
            $shipmentResult = $db->fetchAll($query);
            $shipmentIdArray = [];
            foreach ($shipmentResult as $val) {
                $shipmentIdArray[] = $val['shipment_id'];
                //$shipmentId[$val['scheme_type']][] = $val['shipment_id'];
                $shipmentCodeArray[$val['scheme_type']][] = $val['shipment_code'];
                $impShipmentId = implode(",", $shipmentIdArray);
            }

            $sQuery = $db->select()
                ->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id', 'spm.participant_id', 'spm.shipment_test_report_date', 'spm.shipment_score', 'spm.documentation_score', 'spm.final_result', 'spm.attributes', 'finalResult' => new Zend_Db_Expr("
                    CASE WHEN (spm.final_result = 1) THEN 'PASS' ELSE
                        (CASE WHEN (spm.final_result = 2) THEN 'FAIL' ELSE 'EXCLUDED' END)
                    END"), 'failure_reason'))
                ->join(array('s' => 'shipment'), 's.shipment_id=spm.shipment_id', ['*'])
                ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_name'))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('unique_identifier', 'first_name', 'last_name', 'email', 'city', 'district', 'state', 'address', 'institute_name'))
                ->joinLeft(array('c' => 'countries'), 'c.id=p.country', array('country_name' => 'iso_name'))
                // ->where("spm.final_result = 1 OR spm.final_result = 2")
                // ->where("spm.is_excluded NOT LIKE 'yes'")
                ->order("unique_identifier ASC")
                ->order("scheme_type ASC");

            if (isset($params['shipmentId']) && !empty($params['shipmentId']) && count($params['shipmentId']) > 0) {
                $impShipmentId = implode(",", $params['shipmentId']);
                $sQuery->where('spm.shipment_id IN (' . $impShipmentId . ')');
                $shQuery = $db->select()->from(array('s' => 'shipment'), array('s.shipment_code', 's.scheme_type'))
                    ->where('s.shipment_id IN (' . $impShipmentId . ')')
                    ->order("s.scheme_type");
                $shipmentResult = $db->fetchAll($shQuery);
                $shipmentCodeArray = [];

                foreach ($shipmentResult as $val) {
                    $shipmentCodeArray[$val['scheme_type']][] = $val['shipment_code'];
                }
            } else {
                //$sQuery->where('spm.shipment_id IN(?)', $impShipmentId);
                $sQuery->where('spm.shipment_id IN (' . $impShipmentId . ')');
            }
            // die($sQuery);
            //Zend_Debug::dump($shipmentCodeArray);die;
            $shipmentParticipantResult = $db->fetchAll($sQuery);
            $participants = [];
            foreach ($shipmentParticipantResult as $shipment) {
                if (in_array($shipment['unique_identifier'], $participants)) {
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']] = $shipment['shipment_score'];
                } else {
                    $participants[$shipment['unique_identifier']]['labName'] = trim($shipment['first_name'] . " " . $shipment['last_name']);
                    $participants[$shipment['unique_identifier']]['institute_name'] = $shipment['institute_name'] ?? '';
                    $participants[$shipment['unique_identifier']]['department_name'] = $shipment['department_name'] ?? '';
                    $participants[$shipment['unique_identifier']]['address'] = $shipment['address'] ?? '';
                    $participants[$shipment['unique_identifier']]['city'] = $shipment['city'] ?? '';
                    $participants[$shipment['unique_identifier']]['district'] = $shipment['district'] ?? '';
                    $participants[$shipment['unique_identifier']]['state'] = $shipment['state'] ?? '';
                    $participants[$shipment['unique_identifier']]['country_name'] = $shipment['country_name'] ?? '';
                    $participants[$shipment['unique_identifier']]['contact_name'] = isset($shipment['contact_name']) ? $shipment['contact_name'] : '';
                    $participants[$shipment['unique_identifier']]['email'] = $shipment['email'] ?? '';
                    $participants[$shipment['unique_identifier']]['additional_email'] = isset($shipment['additional_email']) ? $shipment['additional_email'] : '';
                    $participants[$shipment['unique_identifier']]['scheme_name'] = isset($shipment['scheme_name']) ? $shipment['scheme_name'] : '';
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['score'] = (float)($shipment['shipment_score'] + $shipment['documentation_score']);
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['result'] = $shipment['final_result'] ?? '';
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['finalResult'] = $shipment['finalResult'] ?? '';
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['failure_reason'] = $shipment['failure_reason'] ?? '';
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['number_of_samples'] = $shipment['number_of_samples'] ?? '';
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['attributes'] = json_decode($shipment['attributes'], true);
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['shipment_date'] = $shipment['shipment_date'] ?? '';
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['lastdate_response'] = $shipment['lastdate_response'] ?? '';
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['shipment_test_report_date'] = $shipment['shipment_test_report_date'] ?? '';
                }
            }
            $params['reportType'] = $params['reportType'] ?? 'csv';
            if ($params['reportType'] == 'csv') {
                $filename = $this->generateAnnualReportCSV($shipmentCodeArray, $participants);
            } elseif ($params['reportType'] == 'excel') {
                $filename = $this->generateAnnualReportExcel($shipmentCodeArray, $participants);
            } else {
                $filename = '';
            }

            return json_encode(['fileName' => $filename, 'reportType' => $params['reportType']]);
        }
    }

    public function getShipmentResponseReportReport($parameters)
    {
        $searchColumns = array(
            'noOfParticipants',
            'noOfResponded',
            'noOfPassed',
            'noOfFailed'
        );
        $orderColumns = array(
            'noOfParticipants',
            'noOfResponded',
            'noOfPassed',
            'noOfFailed'
        );

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
                $colSize = count($searchColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($searchColumns[$i] == "" || $searchColumns[$i] == null) {
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        //error_log($sHaving);
        /* Individual column filtering */
        for ($i = 0; $i < count($searchColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */


        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('p' => 'participant'), array('noOfParticipants' => new Zend_Db_Expr("COUNT(*)")))
            ->join(array('sp' => 'shipment_participant_map'), 'p.participant_id=sp.participant_id', array(
                "noOfResponded" => new Zend_Db_Expr("SUM(CASE WHEN (sp.response_status not like '' AND sp.response_status is not null AND sp.response_status like 'responded') THEN 1 ELSE 0 END)"),
                "noOfNotTested" => new Zend_Db_Expr("SUM(CASE WHEN (sp.is_pt_test_not_performed is not null and IFNULL(sp.is_pt_test_not_performed, 'no') like 'yes') THEN 1 ELSE 0 END)"),
                "noOfNotResponded" => new Zend_Db_Expr("SUM(CASE WHEN (sp.response_status like '' OR sp.response_status like 'noresponse' OR sp.response_status is null) THEN 1 ELSE 0 END)"),
                "noOfPassed" => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result like 1) THEN 1 ELSE 0 END)"),
                "noOfFailed" => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result like 2) THEN 1 ELSE 0 END)")
            ))
            ->join(array('s' => 'shipment'), 's.shipment_id=sp.shipment_id', array('shipment_code', 'scheme_type', 'lastdate_response'));

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type like ?", $parameters['scheme']);
        }


        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id like ?", $parameters['shipmentId']);
        }

        if (isset($parameters['country']) && $parameters['country'] != "") {
            $sQuery = $sQuery->where("p.country = ?", $parameters['country']);
        }

        // if (!empty($sOrder)) {
        //     $sQuery = $sQuery->order($sOrder);
        // }

        // if (isset($sLimit) && isset($sOffset)) {
        //     $sQuery = $sQuery->limit($sLimit, $sOffset);
        // }

        //echo $sQuery;
        //die;

        $rResult = $dbAdapter->fetchAll($sQuery);

        $iFilteredTotal = $iTotal = 1;

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
            $row[] = $aRow['noOfParticipants'];
            $row[] = $aRow['noOfResponded'];
            $row[] = $aRow['noOfNotResponded'];
            $row[] = $aRow['noOfNotTested'];
            $row[] = $aRow['noOfPassed'];
            $row[] = $aRow['noOfFailed'];
            $output['aaData'][] = $row;
        }
        echo json_encode($output);
    }

    public function generateAnnualReportExcel($shipmentCodeArray, $participants)
    {

        $schemeService = new Application_Service_Schemes();

        $vlAssayArray = $schemeService->getVlAssay();
        $eidAssayArray = $schemeService->getEidExtractionAssay();

        $headings = array('Participant ID', 'Participant Name', 'Institute Name', 'Department', 'Address', 'City', 'District', 'State', 'Country', 'Email', 'Additional Email', 'Scheme Type');
        foreach ($shipmentCodeArray as $participantArray) {
            foreach ($participantArray as $shipmentCode) {
                $headings[] = "Shipment Code";
                $headings[] = "Number of Samples - " . $shipmentCode;
                $headings[] = "Assay/Platform/Kit - " . $shipmentCode;
                $headings[] = "Score - " . $shipmentCode;
                $headings[] = "Final Result - " . $shipmentCode;
                $headings[] = "Warning/Errors - " . $shipmentCode;
                $headings[] = "Corrective Actions - " . $shipmentCode;
            }

            $headings[] = 'Certificate Type';
        }


        $excel = new Spreadsheet();

        $output = [];

        //$sheet = $excel->getActiveSheet();
        $firstSheet = new Worksheet($excel, '');
        $excel->addSheet($firstSheet, 0);
        $firstSheet->getDefaultColumnDimension()->setWidth(20);
        $firstSheet->getDefaultRowDimension()->setRowHeight(18);
        $firstSheet->setTitle('ePT Annual Report', true);

        foreach ($participants as $uniqueIdentifier => $participantArray) {
            $firstSheetRow = [];
            $firstSheetRow[] = $uniqueIdentifier;
            $firstSheetRow[] = $participantArray['labName'];
            $firstSheetRow[] = $participantArray['institute_name'];
            $firstSheetRow[] = $participantArray['department_name'];
            $firstSheetRow[] = $participantArray['address'];
            $firstSheetRow[] = $participantArray['city'];
            $firstSheetRow[] = $participantArray['district'];
            $firstSheetRow[] = $participantArray['state'];
            $firstSheetRow[] = $participantArray['country_name'];
            $firstSheetRow[] = $participantArray['email'];
            $firstSheetRow[] = $participantArray['additional_email'];
            $firstSheetRow[] = $participantArray['scheme_name'];
            foreach ($shipmentCodeArray as $shipmentType => $shipmentsList) {
                $certificate = true;
                $participated = true;
                foreach ($shipmentsList as $shipmentCode) {
                    $firstSheetRow[] = $shipmentCode;
                    $firstSheetRow[] = $participantArray[$shipmentType][$shipmentCode]['number_of_samples'];
                    $assayName = "";
                    if ($shipmentType == 'vl' && !empty($participantArray[$shipmentType][$shipmentCode]['attributes']['vl_assay'])) {
                        $assayName = $vlAssayArray[$participantArray[$shipmentType][$shipmentCode]['attributes']['vl_assay']];
                    } else if ($shipmentType == 'eid' && !empty($participantArray[$shipmentType][$shipmentCode]['attributes']['extraction_assay'])) {
                        $assayName = $eidAssayArray[$participantArray[$shipmentType][$shipmentCode]['attributes']['extraction_assay']];
                    }
                    $firstSheetRow[] = $assayName;
                    if (!empty($participantArray[$shipmentType][$shipmentCode]['result']) && $participantArray[$shipmentType][$shipmentCode]['result'] != 3) {

                        $firstSheetRow[] = $participantArray[$shipmentType][$shipmentCode]['score'];

                        if ($participantArray[$shipmentType][$shipmentCode]['result'] != 1) {
                            $certificate = false;
                        }
                    } else {
                        if (!empty($participantArray[$shipmentType][$shipmentCode]['result']) && $participantArray[$shipmentType][$shipmentCode]['result'] == 3) {
                            $firstSheetRow[] = 'EXCLUDED';
                        } else {
                            $firstSheetRow[] = '-';
                        }
                        //$participated = false;
                        $certificate = false;
                    }

                    if (empty($participantArray[$shipmentType][$shipmentCode]['shipment_test_report_date'])) {
                        $participated = false;
                    }
                    $firstSheetRow[] = $participantArray[$shipmentType][$shipmentCode]['finalResult'];
                    if (isset($participantArray[$shipmentType][$shipmentCode]['failure_reason']) && !empty($participantArray[$shipmentType][$shipmentCode]['failure_reason']) && $participantArray[$shipmentType][$shipmentCode]['failure_reason'] != '[]') {
                        $warnings = json_decode($participantArray[$shipmentType][$shipmentCode]['failure_reason'], true);
                        $txt = $note = "";
                        foreach ($warnings as $w) {
                            $txt .= $w['warning'] ?? "";
                            $note .= $w['correctiveAction'] ?? "";
                        }
                        $firstSheetRow[] = strip_tags($txt);
                        $firstSheetRow[] = strip_tags($note);
                    } else {
                        $firstSheetRow[] = "";
                        $firstSheetRow[] = "";
                    }
                }
                if ($certificate && $participated) {
                    $firstSheetRow[] = 'Excellence';
                } else if ($participated) {
                    $firstSheetRow[] = 'Participation';
                } else {
                    $firstSheetRow[] = 'N.A.';
                }
            }

            $output[] = $firstSheetRow;
        }

        // Generating Excel from the data
        $firstSheet->fromArray($headings, null, 'A1');
        $firstSheet->fromArray($output, null, 'A2');

        $firstSheet = $this->common->centerAndBoldRowInSheet($firstSheet, 'A1');
        $firstSheet = $this->common->applyBordersToSheet($firstSheet);
        $firstSheet = $this->common->setAllColumnWidthsInSheet($firstSheet, 20);

        if (!is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "annual-reports")) {
            mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "annual-reports", 0777, true);
        }

        $excel->setActiveSheetIndex(0);
        $writer = IOFactory::createWriter($excel, 'Xlsx');
        $filename = 'ePT-Annual-Performance-Report-' . $this->common->generateRandomString(16) . '-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "annual-reports" . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }

    public function generateAnnualReportCSV($shipmentCodeArray, $participants)
    {
        $schemeService = new Application_Service_Schemes();
        $vlAssayArray = $schemeService->getVlAssay();
        $eidAssayArray = $schemeService->getEidExtractionAssay();

        $headings = ['Participant ID', 'Participant Name', 'Institute Name', 'Department', 'Address', 'City', 'District', 'State', 'Country', 'Email', 'Additional Email', 'Scheme Type', 'Shipment Code', 'Number of Samples', 'Shipment Date', 'Result Due Date', 'Responded On', 'Assay/Platform/Kit', 'Score', 'Final Result', 'Warning/Errors', 'Corrective Actions'];

        $tempFile = tempnam(sys_get_temp_dir(), 'annual-report-');
        $tempFileHandle = fopen($tempFile, 'w');

        $chunkSize = 50; // Adjust this value based on your memory constraints
        $participantCount = count($participants);

        // Write the header row
        fputcsv($tempFileHandle, $headings);

        for ($i = 0; $i < $participantCount; $i += $chunkSize) {
            $participantChunk = array_slice($participants, $i, $chunkSize);
            foreach ($participantChunk as $uniqueIdentifier => $participantArray) {
                foreach ($shipmentCodeArray as $shipmentType => $shipmentsList) {
                    foreach ($shipmentsList as $shipmentCode) {
                        if (empty($participantArray[$shipmentType][$shipmentCode]['result'])) {
                            continue;
                        }

                        $csvRow = [];
                        $csvRow[] = $uniqueIdentifier;
                        $csvRow[] = $participantArray['labName'];
                        $csvRow[] = $participantArray['institute_name'];
                        $csvRow[] = $participantArray['department_name'];
                        $csvRow[] = $participantArray['address'];
                        $csvRow[] = $participantArray['city'];
                        $csvRow[] = $participantArray['district'];
                        $csvRow[] = $participantArray['state'];
                        $csvRow[] = $participantArray['country_name'];
                        $csvRow[] = $participantArray['email'];
                        $csvRow[] = $participantArray['additional_email'];
                        $csvRow[] = $participantArray['scheme_name'];
                        $csvRow[] = $shipmentCode;
                        $csvRow[] = $participantArray[$shipmentType][$shipmentCode]['number_of_samples'];
                        $csvRow[] = Pt_Commons_General::humanReadableDateFormat($participantArray[$shipmentType][$shipmentCode]['shipment_date']);
                        $csvRow[] = Pt_Commons_General::humanReadableDateFormat($participantArray[$shipmentType][$shipmentCode]['lastdate_response']);
                        $csvRow[] = Pt_Commons_General::humanReadableDateFormat($participantArray[$shipmentType][$shipmentCode]['shipment_test_report_date']);

                        $assayName = "";
                        if ($shipmentType == 'vl' && !empty($participantArray[$shipmentType][$shipmentCode]['attributes']['vl_assay'])) {
                            $assayName = $vlAssayArray[$participantArray[$shipmentType][$shipmentCode]['attributes']['vl_assay']];
                        } else if ($shipmentType == 'eid' && !empty($participantArray[$shipmentType][$shipmentCode]['attributes']['extraction_assay'])) {
                            $assayName = $eidAssayArray[$participantArray[$shipmentType][$shipmentCode]['attributes']['extraction_assay']];
                        }
                        $csvRow[] = $assayName;

                        if (!empty($participantArray[$shipmentType][$shipmentCode]['result']) && $participantArray[$shipmentType][$shipmentCode]['result'] != 3) {
                            $csvRow[] = $participantArray[$shipmentType][$shipmentCode]['score'];
                        } else {
                            if (!empty($participantArray[$shipmentType][$shipmentCode]['result']) && $participantArray[$shipmentType][$shipmentCode]['result'] == 3) {
                                $csvRow[] = 'EXCLUDED';
                            } else {
                                $csvRow[] = '-';
                            }
                        }

                        $csvRow[] = $participantArray[$shipmentType][$shipmentCode]['finalResult'];

                        if (isset($participantArray[$shipmentType][$shipmentCode]['failure_reason']) && !empty($participantArray[$shipmentType][$shipmentCode]['failure_reason']) && $participantArray[$shipmentType][$shipmentCode]['failure_reason'] != '[]') {
                            $warnings = json_decode($participantArray[$shipmentType][$shipmentCode]['failure_reason'], true);
                            $txt = $note = "";
                            foreach ($warnings as $w) {
                                $txt .= $w['warning'] ?? "";
                                $note .= $w['correctiveAction'] ?? "";
                            }
                            $csvRow[] = strip_tags($txt);
                            $csvRow[] = strip_tags($note);
                        } else {
                            $csvRow[] = "";
                            $csvRow[] = "";
                        }

                        fputcsv($tempFileHandle, $csvRow);
                    }
                }
            }
        }

        fclose($tempFileHandle);

        if (!is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "annual-reports")) {
            mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "annual-reports", 0777, true);
        }

        $filename = 'ePT-Annual-Performance-Report-' . $this->common->generateRandomString(16) . '-' . date('d-M-Y-H-i-s') . '.csv';
        $csvFile = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "annual-reports" . DIRECTORY_SEPARATOR . $filename;
        rename($tempFile, $csvFile);

        return $filename;
    }

    public function scheduleCertificationGeneration($params)
    {
        $scheduledDb = new Application_Model_DbTable_ScheduledJobs();
        return $scheduledDb->scheduleCertificationGeneration($params);
    }

    public function getResultsPerSiteReport($parameters)
    {

        $searchColumns = array('p.unique_identifier', 'c.iso_name', 'p.region', 'p.lab_name', 'spm.shipment_score', 'smp.final_result');
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
                    $sOrder .= $searchColumns[intval($parameters['iSortCol_' . $i])] . "
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
                $colSize = count($searchColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($searchColumns[$i] == "" || $searchColumns[$i] == null) {
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        //error_log($sHaving);
        /* Individual column filtering */
        for ($i = 0; $i < count($searchColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */


        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()
            ->from(
                array('spm' => 'shipment_participant_map'),
                array('map_id', 'final_result', 'shipment_score', 'documentation_score', 'shipment_test_report_date')
            )
            ->join(
                array('p' => 'participant'),
                'spm.participant_id=p.participant_id',
                array('unique_identifier', 'lab_name', 'region')
            )
            ->join(array('c' => 'countries'), 'p.country=c.id', array('iso_name'))
            ->order(new Zend_Db_Expr("CASE WHEN p.unique_identifier REGEXP '\d*' THEN CAST(CAST(p.unique_identifier AS DECIMAL) AS CHAR) ELSE TRIM(LEADING '0' FROM p.unique_identifier) END"));

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("spm.shipment_id like ?", $parameters['shipmentId']);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        // echo ($sQuery);die;
        $rResult = $dbAdapter->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sWhere = "";
        $sQuery = $dbAdapter->select()
            ->from(array('spm' => 'shipment_participant_map'), new Zend_Db_Expr("COUNT('spm.map_id')"))
            ->where('spm.shipment_id = ' . $parameters['shipmentId']);
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=spm.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

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

        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = $aRow['lab_name'] . ' (' . $aRow['unique_identifier'] . ')';
            $row[] = $aRow['iso_name'];
            $row[] = $aRow['region'];
            $row[] = $aRow['shipment_score'] + $aRow['documentation_score'];
            if ($aRow['shipment_test_report_date'] == null) {
                $row[] = 'Not Participated';
            } else if ($aRow['final_result'] == 1) {
                $row[] = 'Satisfactory';
            }
            if ($aRow['final_result'] == 2) {
                $row[] = 'Unsatisfactory';
            } else if ($aRow['final_result'] == 3) {
                $row[] = 'Excluded';
            }
            $output['aaData'][] = $row;
        }
        echo json_encode($output);
    }

    public function getFinalisedShipmentsByScheme($schemeType, $startDate, $endDate)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date',));
        if (isset($startDate) && $startDate != "") {
            $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($startDate));
        }
        if (isset($endDate) && $endDate != "") {
            $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($endDate));
        }
        if (isset($schemeType) && $schemeType != "") {
            $sQuery->where("s.scheme_type = ?", $schemeType);
        }
        $sQuery->where("s.status = 'finalized'");
        $sQuery->order("s.shipment_id");
        $resultArray = $db->fetchAll($sQuery);
        return $resultArray;
    }

    public function getResultsPerSiteCount($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $resultsQuery = $db->select()
            ->from(
                array('spm' => 'shipment_participant_map'),
                array(
                    'satisfactory' => 'sum(spm.final_result = 1)',
                    'unsatisfactory' => 'sum(spm.final_result = 2)',
                    'excluded' => 'sum(spm.final_result = 3)',
                    'not_participated' => 'sum(spm.shipment_test_report_date is null)',
                )
            )
            ->where('spm.shipment_id = ' . $params['shipmentId'])
            ->group(array('spm.shipment_id'));
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $resultsQuery = $resultsQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=spm.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        $resultsCountResult = $db->fetchRow($resultsQuery);
        return array(
            array('name' => 'Satisfactory', 'count' => $resultsCountResult['satisfactory']),
            array('name' => 'Unsatisfactory', 'count' => $resultsCountResult['unsatisfactory']),
            array('name' => 'Excluded', 'count' => $resultsCountResult['excluded']),
            array('name' => 'Not Participated', 'count' => $resultsCountResult['not_participated'])
        );
    }

    public function getParticipantsPerCountryReport($parameters)
    {

        $searchColumns = array('country_name', 'participant_count');
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
                    $sOrder .= $searchColumns[intval($parameters['iSortCol_' . $i])] . "
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
                $colSize = count($searchColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($searchColumns[$i] == "" || $searchColumns[$i] == null) {
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $searchColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        //error_log($sHaving);
        /* Individual column filtering */
        for ($i = 0; $i < count($searchColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $searchColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */


        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()
            ->from(array('spm' => 'shipment_participant_map'), array())
            ->join(
                array('p' => 'participant'),
                'spm.participant_id = p.participant_id',
                array('participant_count' => new Zend_Db_Expr('COUNT(p.participant_id)'))
            )
            ->join(
                array('c' => 'countries'),
                'p.country = c.id',
                array('id', 'country_name' => 'c.iso_name')
            )
            ->group('c.id');

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("spm.shipment_id like ?", $parameters['shipmentId']);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        // echo ($sQuery);die;
        $rResult = $dbAdapter->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sWhere = "";
        $sQuery = $dbAdapter->select()
            ->from(array('spm' => 'shipment_participant_map'), array())
            ->join(
                array('p' => 'participant'),
                'spm.participant_id = p.participant_id',
                new Zend_Db_Expr("COUNT(DISTINCT p.country)")
            );
        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("spm.shipment_id like ?", $parameters['shipmentId']);
        }
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

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

        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = $aRow['country_name'];
            $row[] = $aRow['participant_count'];
            $output['aaData'][] = $row;
        }
        echo json_encode($output);
    }

    public function getParticipantsPerCountryCount($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $resultsQuery = $db->select()
            ->from(array('spm' => 'shipment_participant_map'), array())
            ->join(
                array('p' => 'participant'),
                'spm.participant_id = p.participant_id',
                array('participant_count' => new Zend_Db_Expr('COUNT(p.participant_id)'))
            )
            ->join(
                array('c' => 'countries'),
                'p.country = c.id',
                array('id', 'country_name' => 'c.iso_name')
            )
            ->where('spm.shipment_id = ' . $params['shipmentId'])
            ->group(array('c.id'))
            ->order('participant_count DESC');
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $resultsQuery = $resultsQuery
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        $resultsCountResult = $db->fetchAll($resultsQuery);

        $output = array();
        foreach ($resultsCountResult as $aRow) {
            $row = array();
            $row['name'] = $aRow['country_name'];
            $row['count'] = $aRow['participant_count'];
            $output[] = $row;
        }

        return $output;
    }

    public function getXtptIndicatorsReport($params)
    {
        $db = new Application_Model_Tb();
        return $db->fetchXtptIndicatorsReport($params);
    }

    public function getTbAllSitesResultsSheet($db, $shipmentId, $excel, $sheetIndex)
    {
        $db = new Application_Model_Tb();
        return $db->fetchTbAllSitesResultsSheet($db, $shipmentId, $excel, $sheetIndex);
    }

    public function getTbAllSitesResultsReport($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $tbDb = new Application_Model_Tb();
        $excel = new Spreadsheet();
        $tbDb->fetchTbAllSitesResultsSheet($db, $params['shipmentId'], $excel, 0);
        $excel->setActiveSheetIndex(0);

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $shipmentQuery = $db->select()->from('shipment', array('shipment_code'))->where('shipment_id=?', $params['shipmentId']);
        $shipmentResult = $db->fetchRow($shipmentQuery);
        $writer = IOFactory::createWriter($excel, 'Xlsx');
        if (!file_exists(TEMP_UPLOAD_PATH  . DIRECTORY_SEPARATOR . "generated-tb-reports")) {
            mkdir(TEMP_UPLOAD_PATH  . DIRECTORY_SEPARATOR . "generated-tb-reports", 0777, true);
        }
        $fileSafeShipmentCode = str_replace(' ', '-', str_replace(array_merge(
            array_map('chr', range(0, 31)),
            array('<', '>', ':', '"', '/', '\\', '|', '?', '*')
        ), '', $shipmentResult['shipment_code']));
        $filename = $fileSafeShipmentCode . '-TB-ALL-SITES-RESULTS-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer->save(TEMP_UPLOAD_PATH  . DIRECTORY_SEPARATOR . "generated-tb-reports" . DIRECTORY_SEPARATOR . $filename);

        return array(
            "report-name" => $filename
        );
    }

    public function getStatusOfMappedSites($parameters)
    {
        try {
            $db = new Application_Model_DbTable_Shipments();
            $resultSet =  $db->getStatusOfMappedSites($parameters);
            if (isset($resultSet) && count($resultSet) > 0) {

                $excel = new Spreadsheet();

                $output = [];
                $sheet = $excel->getActiveSheet();
                $colNo = 0;

                $styleArray = array(
                    'font' => array(
                        'bold' => true,
                    ),
                    'alignment' => array(
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ),
                    'borders' => array(
                        'outline' => array(
                            'style' => Border::BORDER_THIN,
                        ),
                    )
                );

                $sheet->getCell('A1')->setValue(html_entity_decode("Scheme", ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('B1')->setValue(html_entity_decode("Shipment Code", ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('C1')->setValue(html_entity_decode("Shipment Date", ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('D1')->setValue(html_entity_decode("Result Due Date", ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('E1')->setValue(html_entity_decode("Participant ID", ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('F1')->setValue(html_entity_decode("Participant", ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('G1')->setValue(html_entity_decode("Instritute Name", ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('H1')->setValue(html_entity_decode("Department", ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('I1')->setValue(html_entity_decode("Email", ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('J1')->setValue(html_entity_decode("Mobile", ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('K1')->setValue(html_entity_decode("Address", ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('L1')->setValue(html_entity_decode("City", ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('M1')->setValue(html_entity_decode("State", ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('N1')->setValue(html_entity_decode("District", ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('O1')->setValue(html_entity_decode("Country", ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('P1')->setValue(html_entity_decode("Response Status", ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('Q1')->setValue(html_entity_decode("Response Date", ENT_QUOTES, 'UTF-8'));
                $sheet->getStyle('A1:Q1')->applyFromArray($styleArray, true);

                foreach ($resultSet as $aRow) {
                    $row = [];
                    $row[] = ($aRow['panelName'] ?? $aRow['scheme_name']);
                    $row[] = $aRow['shipment_code'];
                    $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['shipment_date']);
                    $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['lastdate_response']);
                    $row[] = $aRow['unique_identifier'];
                    $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
                    $row[] = $aRow['institute_name'];
                    $row[] = $aRow['department_name'];
                    $row[] = $aRow['email'];
                    $row[] = $aRow['mobile'];
                    $row[] = $aRow['address'];
                    $row[] = $aRow['city'];
                    $row[] = $aRow['state'];
                    $row[] = $aRow['district'];
                    $row[] = $aRow['iso_name'];
                    $row[] = strtoupper($aRow['response_status']);
                    $row[] = $aRow['response_date'];

                    $output[] = $row;
                }

                foreach ($output as $rowNo => $rowData) {
                    $colNo = 0;
                    foreach ($rowData as $field => $value) {
                        $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . ($rowNo + 2))
                            ->setValueExplicit(html_entity_decode($value));

                        $colNo++;
                    }
                }

                $writer = IOFactory::createWriter($excel, 'Xlsx');
                $filename = $resultSet[0]['shipment_code'] . '-Response-Status-' . date('d-M-Y-H-i-s') . '.xlsx';
                $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                $auditDb = new Application_Model_DbTable_AuditLog();
                $auditDb->addNewAuditLog("Downloaded a pending sites", "participants");
                return $filename;
            } else {
                return '';
            }
        } catch (Exception $exc) {
            error_log($exc->getFile() . "|" . $exc->getLine() . "|" . $exc->getMessage());
            error_log($exc->getTraceAsString());

            return "";
        }
    }

    public function saveReportDownloadDateTime($id, $type)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where = "map_id = $id";
        if ($type == "individual") {
            $data = ["individual_report_downloaded_on" => new Zend_Db_Expr('now()')];
        } elseif ($type == 'summary') {
            $data = ["summary_report_downloaded_on" => new Zend_Db_Expr('now()')];
        }
        return $dbAdapter->update('shipment_participant_map', $data, $where);
    }

    function getParticipantShipmentPerformanceReport($parameters)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sQuery = $dbAdapter->select()->from(array("p" => "participant"), array("p.first_name", "p.participant_id", 'participantName' => new Zend_Db_Expr("CONCAT(p.first_name, '(' ,p.unique_identifier, ')')")))
            ->join(array("spm" => "shipment_participant_map"), "p.participant_id = spm.participant_id", array("score" => new Zend_Db_Expr("AVG(spm.shipment_score + spm.documentation_score)")))
            ->join(array("s" => "shipment"), "spm.shipment_id = s.shipment_id", array("s.shipment_code", "s.shipment_id"))
            ->where('spm.response_status like "responded"')
            ->where('spm.is_pt_test_not_performed not like "yes"')
            ->where('spm.is_excluded not like "yes"')
            ->group("s.shipment_id")
            ->group("p.participant_id");

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

            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }
        $sQuerySession = new Zend_Session_Namespace('ParticipantPerformanceExcel');
        $sQuerySession->participantPerformanceQuery = $sQuery;
        return $dbAdapter->fetchAll($sQuery);
    }

    public function exportParticipantPerformanceReport()
    {

        try {
            $excel = new Spreadsheet();

            $output = [];
            $sheet = $excel->getActiveSheet();
            $styleArray = array(
                'font' => array(
                    'bold' => true,
                ),
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => array(
                    'outline' => array(
                        'style' => Border::BORDER_THIN,
                    ),
                )
            );
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuerySession = new Zend_Session_Namespace('ParticipantTrendsExcel');
            $sQuerySession = new Zend_Session_Namespace('ParticipantPerformanceExcel');
            $rResult = $db->fetchAll($sQuerySession->participantPerformanceQuery);
            $results = [];
            $shipments = [];
            foreach ($rResult as $row) {
                $results[$row['participantName']][$row['shipment_code']] = round($row['score']);
                if (!in_array($row['shipment_code'], $shipments)) {
                    $shipments[] = $row['shipment_code'];
                }
            }

            $headings = array('Participant Name');
            foreach ($shipments as $code) {
                array_push($headings, $code);
            }
            array_push($headings, 'Average Score');
            $colNo = 0;
            $sheet->mergeCells('A1:B1');
            $sheet->getCellByColumnAndRow(1, 1)->setValueExplicit(html_entity_decode('Participant Performance Report', ENT_QUOTES, 'UTF-8'));
            $sheet->getStyleByColumnAndRow(1, 1, null, null)->getFont()->setBold(true);
            foreach ($headings as $field => $value) {
                $sheet->getCellByColumnAndRow($colNo + 1, 5)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                $sheet->getStyleByColumnAndRow($colNo + 1, 5, null, null)->getFont()->setBold(true);
                $colNo++;
            }

            foreach ($results as $name => $row) {
                $row = [];
                $row[] = $name;
                foreach ($shipments as $vl) {
                    $scores[] = $results[$name][$vl] ?? 0;
                    $row[] = $results[$name][$vl] ?? 0;
                }
                $score_count = count($scores);
                $score_sum = array_sum($scores);
                $mean_average = $score_sum / $score_count;
                $row[] = $mean_average;
                $output[] = $row;
            }

            foreach ($output as $rowNo => $rowData) {
                $colNo = 0;
                foreach ($rowData as $field => $value) {
                    if (!isset($value)) {
                        $value = "";
                    }
                    $sheet->getCellByColumnAndRow($colNo + 1, $rowNo + 6)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(150);
                        $sheet->getStyleByColumnAndRow($colNo + 1, $rowNo + 6, null, null)->getAlignment()->setWrapText(true);
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
            $filename = 'participant-performance-report-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            $sQuerySession->participantQuery = '';
            error_log("GENERATE-PARTICIPANT-PERFORMANCE-REPORT-EXCEL--" . $exc->getMessage());
            error_log($exc->getTraceAsString());

            return "";
        }
    }
}
