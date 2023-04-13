<?php

class Application_Service_Reports
{

    public function getAllShipments($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", 's.shipment_code', "DATE_FORMAT(s.lastdate_response,'%d-%b-%Y')", 'sl.scheme_name', 's.number_of_samples', new Zend_Db_Expr('count("participant_id")'), new Zend_Db_Expr("SUM(shipment_test_date not like  '0000-00-00' OR is_pt_test_not_performed ='yes')"), new Zend_Db_Expr("(SUM(shipment_test_date <> '0000-00-00')/count('participant_id'))*100"), new Zend_Db_Expr("SUM(final_result = 1)"), 's.status');
        $searchColumns = array('distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", 's.shipment_code', "DATE_FORMAT(s.lastdate_response,'%d-%b-%Y')", 'sl.scheme_name', 's.number_of_samples', 'participant_count', 'reported_count', 'reported_percentage', 'number_passed', 's.status');
        $havingColumns = array('participant_count', 'reported_count');
        $orderColumns = array('distribution_code', 'distribution_date', 's.shipment_code', 's.lastdate_response', 'sl.scheme_name', 's.number_of_samples', new Zend_Db_Expr('count("participant_id")'), new Zend_Db_Expr("SUM(shipment_test_date not like  '0000-00-00' OR is_pt_test_not_performed ='yes')"), new Zend_Db_Expr("(SUM(shipment_test_date <> '0000-00-00')/count('participant_id'))*100"), new Zend_Db_Expr("SUM(final_result = 1)"), 's.status');

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
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_id', 'scheme_name'))
            ->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('distribution_id', 'distribution_code', 'distribution_date'))
            ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('report_generated', 'participant_count' => new Zend_Db_Expr('count("participant_id")'), 'reported_count' => new Zend_Db_Expr("SUM(shipment_test_date not like  '0000-00-00' OR is_pt_test_not_performed like 'yes')"), 'reported_percentage' => new Zend_Db_Expr("ROUND((SUM(shipment_test_date not like  '0000-00-00' OR is_pt_test_not_performed ='yes')/count('participant_id'))*100,2)"), 'number_passed' => new Zend_Db_Expr("SUM(final_result = 1)")))
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array())
            //->joinLeft(array('pmm'=>'participant_manager_map'),'pmm.participant_id=p.participant_id')
            ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id', array())
            ->group(array('s.shipment_id'));

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }
 
        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("s.shipment_date >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("s.shipment_date <= ?", $common->dbDateFormat($parameters['endDate']));
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


        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }


        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        // echo($sQuery);

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
            $row[] = Pt_Commons_General::humanDateFormat($aRow['distribution_date']);
            $row[] = "<a href='javascript:void(0);' onclick='generateShipmentParticipantList(\"" . base64_encode($aRow['shipment_id']) . "\",\"" . $aRow['scheme_type'] . "\")'>" . $aRow['shipment_code'] . "</a>";
            $row[] = Pt_Commons_General::humanDateFormat($aRow['lastdate_response']);
            $row[] = $aRow['scheme_name'];
            $row[] = $aRow['number_of_samples'];
            $row[] = $aRow['participant_count'];
            $row[] = ($aRow['reported_count'] != "") ? $aRow['reported_count'] : 0;
            // $row[] = ($aRow['reported_percentage'] != "") ? $aRow['reported_percentage'] : "0";
            $row[] = '<a href="/reports/shipments/response-chart/id/' . base64_encode($aRow['shipment_id']) . '/shipmentDate/' . base64_encode($aRow['distribution_date']) . '/shipmentCode/' . base64_encode($aRow['distribution_code']) . '" target="_blank" style="text-decoration:underline">' . $responsePercentage . ' %</a>';
            $row[] = $aRow['number_passed'];
            $row[] = ucwords($aRow['status']);
            
            $row[] = $download . $zipFileDownload;
            $row[] = "<a href='javascript:void(0);' class='btn btn-success btn-xs' onclick='generateShipmentParticipantList(\"" . base64_encode($aRow['shipment_id']) . "\",\"" . $aRow['scheme_type'] . "\")'>Export Report</a>";
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
        if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1) {
            $sQuery = $sQuery->where("p.country IN(".$authNameSpace->ptccMappedCountries.")");
        }
        if (isset($params['scheme']) && $params['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $params['scheme']);
        }

        //die($sQuery);
        if (isset($params['startDate']) && $params['startDate'] != "" && isset($params['endDate']) && $params['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("s.shipment_date >= ?", $common->dbDateFormat($params['startDate']));
            $sQuery = $sQuery->where("s.shipment_date <= ?", $common->dbDateFormat($params['endDate']));
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
        if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1) {
            $sQuery = $sQuery->where("p.country IN(".$authNameSpace->ptccMappedCountries.")");
        }
        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("s.shipment_date >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("s.shipment_date <= ?", $common->dbDateFormat($parameters['endDate']));
        }

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
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
            $row[] = Pt_Commons_General::humanDateFormat($aRow['distribution_date']);
            $row[] = ucwords($aRow['state']);
            $row[] = ucwords($aRow['district']);
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
        if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1) {
            $sQuery = $sQuery->where("p.country IN(".$authNameSpace->ptccMappedCountries.")");
        }
        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($parameters['endDate']));
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
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($parameters['endDate']));
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
            $row[] = Pt_Commons_General::humanDateFormat($aRow['shipment_date']);
            $row[] = "<a href='javascript:void(0);' onclick='shipmetRegionReport(\"" . $aRow['shipment_id'] . "\"),regionDetails(\"" . $aRow['scheme_name'] . "\",\"" . Pt_Commons_General::humanDateFormat($aRow['shipment_date']) . "\",\"" . $aRow['shipment_code'] . "\")'>" . $aRow['shipment_code'] . "</a>";
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

    public function getParticipantPerformanceReportByShipmentId($shipmentId)
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
                    "total_responses" => new Zend_Db_Expr("SUM(shipment_test_date not like  '0000-00-00' OR is_pt_test_not_performed ='yes')"),
                    "valid_responses" => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00%' AND is_excluded != 'yes'))"),
                    "number_failed" => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result = 2 AND DATE(sp.shipment_test_report_date) <= s.lastdate_response) THEN 1 ELSE 0 END)"),
                    "number_passed" => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result = 1 AND DATE(sp.shipment_test_report_date) <= s.lastdate_response) THEN 1 ELSE 0 END)")
                )
            )
            ->where("s.shipment_id = ?", $shipmentId);
        //echo $sQuery;die;
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
            new Zend_Db_Expr("SUM(shipment_test_date not like  '0000-00-00' OR is_pt_test_not_performed ='yes')"),
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
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($parameters['endDate']));
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
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($parameters['endDate']));
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
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($params['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($params['endDate']));
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
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("s.shipment_date >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("s.shipment_date <= ?", $common->dbDateFormat($parameters['endDate']));
        }
        $sQuery = $sQuery->where("tn.TestKit_Name IS NOT NULL");

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->having($sWhere);
        }
        if (isset($sOrder) && $sOrder != "") {
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
                ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('reported_count' => new Zend_Db_Expr("SUM(shipment_test_date not like  '0000-00-00' OR is_pt_test_not_performed ='yes')")))
                ->where("s.shipment_id = ?", $shipmentId)
                ->group('s.shipment_id');
            $endDate = strftime("%Y-%m-%d", strtotime("$date + $i day"));

            if (isset($date) && $date != "" && $endDate != '' && $i < $maxDays) {
                $sQuery = $sQuery->where("sp.shipment_test_date >= ?", $date);
                $sQuery = $sQuery->where("sp.shipment_test_date <= ?", $endDate);
                $result = $dbAdapter->fetchAll($sQuery);
                $count = (isset($result[0]['reported_count']) && $result[0]['reported_count'] != "") ? $result[0]['reported_count'] : 0;
                $responseResult[] = (int) $count;
                $responseDate[] = Pt_Commons_General::humanDateFormat($date) . ' ' . Pt_Commons_General::humanDateFormat($endDate);
                $date = strftime("%Y-%m-%d", strtotime("$endDate +1 day"));
            }

            if ($i == $maxDays) {
                $sQuery = $sQuery->where("sp.shipment_test_date >= ?", $date);
                $result = $dbAdapter->fetchAll($sQuery);
                $count = (isset($result[0]['reported_count']) && $result[0]['reported_count'] != "") ? $result[0]['reported_count'] : 0;
                $responseResult[] = (int) $count;
                $responseDate[] = Pt_Commons_General::humanDateFormat($date) . '  and Above';
            }
        }
        return json_encode($responseResult) . '#' . json_encode($responseDate);
    }

    public function getShipmentParticipant($shipmentId, $schemeType = null)
    {
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
        } else if ($schemeType == 'generic-test') {
            $genericTestObj = new Application_Model_GenericTest();
            return $genericTestObj->generateGenericTestExcelReport($shipmentId);
        } else {
            return false;
        }
    }

    public function getShipmentsByScheme($schemeType, $startDate, $endDate)
    {
        $resultArray = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $common = new Application_Service_Common();
        $sQuery = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date',))
            ->where("s.scheme_type = ?", $schemeType)
            ->order("s.shipment_id");
        if(isset($startDate) && !empty($startDate) && isset($endDate) && !empty($endDate)){
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($startDate));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($endDate));
        }
        // die($sQuery);
        $resultArray = $db->fetchAll($sQuery);
        return $resultArray;
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
            $common = new Application_Service_Common();
            $totalQuery = $totalQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($parameters['startDate']));
            $totalQuery = $totalQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($parameters['endDate']));
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
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($parameters['endDate']));
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
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($parameters['endDate']));
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

    public function getCorrectiveActionReportByShipmentId($shipmentId)
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

        return $dbAdapter->fetchAll($sQuery);
    }

    public function exportParticipantPerformanceReport($params)
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
            $sheet->getCellByColumnAndRow(1, 1)->setValueExplicit(html_entity_decode('Participant Performance Overview Report', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            if (isset($params['shipmentName']) && trim($params['shipmentName']) != "") {
                $sheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode('Shipment', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(2, 2)->setValueExplicit(html_entity_decode($params['shipmentName'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $sheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode('Selected Date Range', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getCellByColumnAndRow(2, 3)->setValueExplicit(html_entity_decode($params['dateRange'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            $sheet->getStyleByColumnAndRow(1, 1, null, null)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(1, 2, null, null)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(1, 3, null, null)->getFont()->setBold(true);

            foreach ($headings as $field => $value) {
                $sheet->getCellByColumnAndRow($colNo + 1, 5)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getStyleByColumnAndRow($colNo + 1, 5, null, null)->getFont()->setBold(true);
                $colNo++;
            }

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuerySession = new Zend_Session_Namespace('participantPerformanceExcel');
            $rResult = $db->fetchAll($sQuerySession->participantQuery);
            foreach ($rResult as $aRow) {

                $row = [];
                $row[] = $aRow['scheme_name'];
                $row[] = Pt_Commons_General::humanDateFormat($aRow['shipment_date']);
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
                    $sheet->getCellByColumnAndRow($colNo + 1, $rowNo + 6)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
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

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'participant-performance-report-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            return "";
            $sQuerySession->participantQuery = '';
            error_log("GENERATE-PARTICIPANT-PERFORMANCE-REPORT-EXCEL--" . $exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }

    public function exportCorrectiveActionsReport($params)
    {

        $headings = array('Corrective Action', 'No. of Responses having this corrective action');
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
            $sheet->getCellByColumnAndRow(1, 1)->setValueExplicit(html_entity_decode('Participant Corrective Action Overview', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            if (isset($params['shipmentName']) && trim($params['shipmentName']) != "") {
                $sheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode('Shipment', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(2, 2)->setValueExplicit(html_entity_decode($params['shipmentName'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $sheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode('Selected Date Range', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getCellByColumnAndRow(2, 3)->setValueExplicit(html_entity_decode($params['dateRange'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);


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
            $sheet->getCellByColumnAndRow(1, 4)->setValueExplicit(html_entity_decode('Total shipped :' . $totalShipped, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow(1, 4, null, null)->getFont()->setBold(true);
            $sheet->mergeCells('A5:B5');
            $sheet->getCellByColumnAndRow(1, 5)->setValueExplicit(html_entity_decode('Total number of responses :' . $totalResp, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow(1, 5, null, null)->getFont()->setBold(true);
            $sheet->mergeCells('A6:B6');
            $sheet->getCellByColumnAndRow(1, 6)->setValueExplicit(html_entity_decode('Total number of valid responses :' . $validResp, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow(1, 6, null, null)->getFont()->setBold(true);
            $sheet->mergeCells('A7:B7');
            //$sheet->getCellByColumnAndRow(0, 7)->setValueExplicit(html_entity_decode('Average score :' . $avgScore, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            //$sheet->getStyleByColumnAndRow(0, 7)->getFont()->setBold(true);

            foreach ($headings as $field => $value) {
                $sheet->getCellByColumnAndRow($colNo + 1, 9)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
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
                    $sheet->getCellByColumnAndRow($colNo + 1, $rowNo + 10)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
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

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'Participant-Corrective-Actions-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            return "";
            $sQuerySession->correctiveActionsQuery = '';
            error_log("GENERATE-PARTICIPANT-CORRECTIVE-ACTIONS--REPORT-EXCEL--" . $exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }

    public function exportShipmentsReport($params)
    {

        $headings = array('Scheme', 'Shipment Code', 'Sample Label', 'Reference Result', 'Total Positive Responses', 'Total Negative Responses', 'Total Indeterminate Responses', 'Total Responses', 'Total Valid Responses(Total - Excluded)', 'Total Passed');
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
            $sheet->getCellByColumnAndRow(1, 1)->setValueExplicit(html_entity_decode('Shipment Response Overview', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            if (isset($params['shipmentName']) && trim($params['shipmentName']) != "") {
                $sheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode('Shipment', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(2, 2)->setValueExplicit(html_entity_decode($params['shipmentName'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $sheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode('Selected Date Range', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getCellByColumnAndRow(2, 3)->setValueExplicit(html_entity_decode($params['dateRange'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);


            $sheet->getStyleByColumnAndRow(1, 3, null, null)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(1, 2, null, null)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(1, 1, null, null)->getFont()->setBold(true);
            foreach ($headings as $field => $value) {
                $sheet->getCellByColumnAndRow($colNo + 1, 5)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
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
                    $sheet->getCellByColumnAndRow($colNo + 1, $rowNo + 6)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
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

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'shipment-response-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            return "";
            $sQuerySession->shipmentExportQuery = '';
            error_log("GENERATE-SHIPMENT_RESPONSE-REPORT-EXCEL--" . $exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }

    public function exportParticipantPerformanceReportInPdf()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuerySession = new Zend_Session_Namespace('participantPerformanceExcel');
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
            $common = new Application_Service_Common();
            $totalQuery = $totalQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($params['dateStartDate']));
            $totalQuery = $totalQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($params['dateEndDate']));
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
        if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1) {
            $sQuery = $sQuery->where("p.country IN(".$authNameSpace->ptccMappedCountries.")");
        }

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($parameters['endDate']));
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
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($parameters['endDate']));
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
        if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1) {
            $sQuery = $sQuery->where("p.country IN(".$authNameSpace->ptccMappedCountries.")");
        }
        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($parameters['endDate']));
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
        if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1) {
            $sQuery = $sQuery->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('region'));
            $sQuery = $sQuery->where("p.country IN(".$authNameSpace->ptccMappedCountries.")");
        }
        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($parameters['endDate']));
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
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($parameters['endDate']));
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
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("s.shipment_date >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("s.shipment_date <= ?", $common->dbDateFormat($parameters['endDate']));
        }
        $sQuery = $sQuery->where("tn.TestKit_Name IS NOT NULL");

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->having($sWhere);
        }
        if (isset($sOrder) && $sOrder != "") {
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
                $common = new Application_Service_Common();
                $sQuery = $sQuery->where("s.shipment_date >= ?", $common->dbDateFormat($parameters['startDate']));
                $sQuery = $sQuery->where("s.shipment_date <= ?", $common->dbDateFormat($parameters['endDate']));
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
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }

        if (isset($sOrder) && $sOrder != "") {
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
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($parameters['endDate']));
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
            $row[] = Pt_Commons_General::humanDateFormat($aRow['shipment_test_date']);
            $row[] = Pt_Commons_General::humanDateFormat($aRow['shipment_receipt_date']);
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
        $common = new Application_Service_Common();
        $sQuery = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date',))
            ->where("s.status <= ?", 'finalized')
            ->order("s.shipment_id");
        if (!empty($startDate) && !empty($endDate)) {
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($startDate));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($endDate));
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
            $common = new Application_Service_Common();
            $startDate = $common->dbDateFormat($params['startDate']);
            $endDate = $common->dbDateFormat($params['endDate']);

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
                $shipmentId[$val['scheme_type']][] = $val['shipment_id'];
                $shipmentCodeArray[$val['scheme_type']][] = $val['shipment_code'];
                $impShipmentId = implode(",", $shipmentIdArray);
            }

            $sQuery = $db->select()
                ->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id', 'spm.participant_id', 'spm.shipment_test_report_date', 'spm.shipment_score', 'spm.documentation_score', 'spm.final_result', 'spm.attributes'))
                ->join(array('s' => 'shipment'), 's.shipment_id=spm.shipment_id', array('shipment_code', 'scheme_type', 'lastdate_response'))
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
            //Zend_Debug::dump($shipmentCodeArray);die;
            $shipmentParticipantResult = $db->fetchAll($sQuery);
            $participants = [];
            foreach ($shipmentParticipantResult as $shipment) {
                //count($participants);
                if (in_array($shipment['unique_identifier'], $participants)) {
                    //$participants[$shipment['unique_identifier']]['finalResult']=$shipment['final_result'];
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']] = $shipment['shipment_score'];
                } else {
                    //$participants[$shipment['unique_identifier']]=$shipment['unique_identifier'];
                    $participants[$shipment['unique_identifier']]['labName'] = $shipment['first_name'] . " " . $shipment['last_name'];
                    $participants[$shipment['unique_identifier']]['address'] = $shipment['address'];
                    $participants[$shipment['unique_identifier']]['city'] = $shipment['city'];
                    $participants[$shipment['unique_identifier']]['district'] = $shipment['district'];
                    $participants[$shipment['unique_identifier']]['state'] = $shipment['state'];
                    $participants[$shipment['unique_identifier']]['country_name'] = $shipment['country_name'];
                    $participants[$shipment['unique_identifier']]['contact_name'] = isset($shipment['contact_name']) ? $shipment['contact_name'] : '';
                    $participants[$shipment['unique_identifier']]['email'] = $shipment['email'];
                    $participants[$shipment['unique_identifier']]['additional_email'] = isset($shipment['additional_email']) ? $shipment['additional_email'] : '';
                    //					$participants[$shipment['unique_identifier']]['attributes']=$shipment['attributes'];
                    //$participants[$shipment['unique_identifier']]['finalResult']=$shipment['final_result'];
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['score'] = (float)($shipment['shipment_score'] + $shipment['documentation_score']);
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['result'] = $shipment['final_result'];
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['attributes'] = json_decode($shipment['attributes'], true);
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['lastdate_response'] = $shipment['lastdate_response'];
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['shipment_test_report_date'] = $shipment['shipment_test_report_date'];
                    //$participants[$shipment['unique_identifier']][$shipment['shipment_code']]=$shipment['shipment_score'];
                }
            }
            return $this->generateAnnualReport($shipmentCodeArray, $participants, $startDate, $endDate);
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
                "noOfResponded" => new Zend_Db_Expr("SUM(CASE WHEN (sp.shipment_test_date not like '' AND sp.shipment_test_date not like '0000-00-00' AND sp.shipment_test_date not like 'NULL') THEN 1 ELSE 0 END)"),
                "noOfNotResponded" => new Zend_Db_Expr("SUM(CASE WHEN (sp.response_status not like '' AND sp.response_status like 'noresponse' AND sp.response_status not like 'NULL') THEN 1 ELSE 0 END)"),
                "noOfPassed" => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result like 1) THEN 1 ELSE 0 END)"),
                "noOfFailed" => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result like 2) THEN 1 ELSE 0 END)")
            ))
            ->join(array('s' => 'shipment'), 's.shipment_id=sp.shipment_id', array('shipment_code', 'scheme_type', 'lastdate_response'))
            ->group('s.scheme_type');

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type like ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($parameters['endDate']));
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

        if (isset($sOrder) && $sOrder != "") {
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
        $sQuery = $dbAdapter->select()->from(array('p' => 'participant'), array('noOfParticipants' => new Zend_Db_Expr("COUNT(*)")))
            ->join(array('sp' => 'shipment_participant_map'), 'p.participant_id=sp.participant_id', array(
                "noOfResponded" => new Zend_Db_Expr("SUM(CASE WHEN (sp.shipment_test_date not like '' AND sp.shipment_test_date not like '0000-00-00' AND sp.shipment_test_date not like 'NULL') THEN 1 ELSE 0 END)"),
                "noOfNotResponded" => new Zend_Db_Expr("SUM(CASE WHEN (sp.response_status not like '' AND sp.response_status like 'noresponse' AND sp.response_status not like 'NULL') THEN 1 ELSE 0 END)"),
                "noOfPassed" => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result like 1) THEN 1 ELSE 0 END)"),
                "noOfFailed" => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result like 2) THEN 1 ELSE 0 END)")
            ))
            ->join(array('s' => 'shipment'), 's.shipment_id=sp.shipment_id', array('shipment_code', 'scheme_type', 'lastdate_response'))
            ->group('s.scheme_type');

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type like ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $common->dbDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $common->dbDateFormat($parameters['endDate']));
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
            $row[] = $aRow['noOfParticipants'];
            $row[] = $aRow['noOfResponded'];
            $row[] = $aRow['noOfNotResponded'];
            $row[] = $aRow['noOfPassed'];
            $row[] = $aRow['noOfFailed'];
            $output['aaData'][] = $row;
        }
        echo json_encode($output);
    }

    public function generateAnnualReport($shipmentCodeArray, $participants, $startDate, $endDate)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        //$shipmentParticipantResult=$db->fetchAll($sQuery);

        $schemeService = new Application_Service_Schemes();

        $vlAssayArray = $schemeService->getVlAssay();
        $eidAssayArray = $schemeService->getEidExtractionAssay();

        $headings = array('Participant ID', 'Participant Name', 'Address', 'City', 'District', 'State', 'Country', 'Email', 'Additional Email');
        foreach ($shipmentCodeArray as $arrayVal) {
            //
            foreach ($arrayVal as $shipmentCode) {
                $headings[] = "Assay/Platform - " . $shipmentCode;
                $headings[] = "Score - " . $shipmentCode;
            }

            $headings[] = 'Certificate Type';
        }


        $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $output = [];

        $sheet = $excel->getActiveSheet();
        $firstSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, '');
        $excel->addSheet($firstSheet, 0);
        $firstSheet->getDefaultColumnDimension()->setWidth(20);
        $firstSheet->getDefaultRowDimension()->setRowHeight(18);
        $firstSheet->setTitle('ePT Annual Report', true);

        $colNo = 0;
        $headingStyle = array(
            'alignment' => array(
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            )
        );

        foreach ($headings as $field => $value) {
            $firstSheet->getCellByColumnAndRow($colNo + 1, 1)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getStyleByColumnAndRow($colNo + 1, 1, null, null)->applyFromArray($headingStyle, true);
            $firstSheet->getStyleByColumnAndRow($colNo + 1, 1, null, null)->getFont()->setBold(true);
            $colNo++;
        }


        foreach ($participants as $uniqueIdentifier => $arrayVal) {
            $firstSheetRow = [];
            $firstSheetRow[] = $uniqueIdentifier;
            $firstSheetRow[] = $arrayVal['labName'];
            $firstSheetRow[] = $arrayVal['address'];
            $firstSheetRow[] = $arrayVal['city'];
            $firstSheetRow[] = $arrayVal['district'];
            $firstSheetRow[] = $arrayVal['state'];
            $firstSheetRow[] = $arrayVal['country_name'];
            $firstSheetRow[] = $arrayVal['email'];
            $firstSheetRow[] = $arrayVal['additional_email'];
            foreach ($shipmentCodeArray as $shipmentType => $shipmentsList) {
                $certificate = true;
                $participated = true;
                foreach ($shipmentsList as $shipmentCode) {
                    $assayName = "";
                    if ($shipmentType == 'vl' && !empty($arrayVal[$shipmentType][$shipmentCode]['attributes']['vl_assay'])) {
                        $assayName = $vlAssayArray[$arrayVal[$shipmentType][$shipmentCode]['attributes']['vl_assay']];
                    } else if ($shipmentType == 'eid' && !empty($arrayVal[$shipmentType][$shipmentCode]['attributes']['extraction_assay'])) {
                        $assayName = $eidAssayArray[$arrayVal[$shipmentType][$shipmentCode]['attributes']['extraction_assay']];
                    }
                    $firstSheetRow[] = $assayName;
                    if (!empty($arrayVal[$shipmentType][$shipmentCode]['result']) && $arrayVal[$shipmentType][$shipmentCode]['result'] != 3) {

                        $firstSheetRow[] = $arrayVal[$shipmentType][$shipmentCode]['score'];

                        if ($arrayVal[$shipmentType][$shipmentCode]['result'] != 1) {
                            $certificate = false;
                        }
                    } else {
                        if (!empty($arrayVal[$shipmentType][$shipmentCode]['result']) && $arrayVal[$shipmentType][$shipmentCode]['result'] == 3) {
                            $firstSheetRow[] = 'Excluded';
                        } else {
                            $firstSheetRow[] = '-';
                        }
                        //$participated = false;
                        $certificate = false;
                    }

                    if (empty($arrayVal[$shipmentType][$shipmentCode]['shipment_test_report_date'])) {
                        $participated = false;
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


        foreach ($output as $rowNo => $rowData) {
            $colNo = 0;
            foreach ($rowData as $field => $value) {
                $decimalFormat = false;
                $cellStyle = array(
                    'alignment' => array(
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                    )
                );
                if (empty($value)) {
                    $value = "";
                    $cellDataType = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
                } else if (is_float($value)) {
                    $cellDataType = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC;
                    $decimalFormat = true;
                    $cellStyle = array(
                        'alignment' => array(
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                        )
                    );
                } else if (is_numeric($value)) {
                    $cellDataType = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC;
                    $cellStyle = array(
                        'alignment' => array(
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                        )
                    );
                } else {
                    $cellDataType = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
                }
                $firstSheet->getCellByColumnAndRow($colNo + 1, $rowNo + 2)->getStyle()->applyFromArray($cellStyle, true);
                $firstSheet->getCellByColumnAndRow($colNo + 1, $rowNo + 2)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), $cellDataType);
                if ($decimalFormat) {
                    $firstSheet->getCellByColumnAndRow($colNo + 1, $rowNo + 2)->getStyle()->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);;
                }
                if ($colNo == (sizeof($headings) - 1)) {
                    //$firstSheet->getColumnDimensionByColumn($colNo)->setWidth(100);
                    $firstSheet->getStyleByColumnAndRow($colNo + 1, $rowNo + 2, null, null)->getAlignment()->setWrapText(true);
                }
                $colNo++;
            }
        }

        if (!file_exists(UPLOAD_PATH) && !is_dir(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH);
        }

        if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "annual-reports") && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "annual-reports")) {
            mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "annual-reports");
        }
        $excel->setActiveSheetIndex(0);
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
        $filename = 'ePT-Annual-Report-' . rand() . date('d-M-Y-H-i-s') . '.xlsx';
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "annual-reports" . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }

    public function scheduleCertificationGeneration($params)
    {
        $scheduledDb = new Application_Model_DbTable_ScheduledJobs();
        return $scheduledDb->scheduleCertificationGeneration($params);
    }

    public function getXtptIndicatorsReport($params) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheetHeaderStyle = array(
            'font' => array(
                'bold' => true,
                'size' => 16,
            ),
        );
        $columnHeaderStyle = array(
            'font' => array(
                'bold' => true,
                'size' => 12,
            ),
            'alignment' => array(
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ),
            )
        );
        $rowHeaderStyle = array(
            'font' => array(
                'bold' => true,
                'size' => 12,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ),
            )
        );
        $nonConcordanceStyle = array(
            'font' => array(
                'color' => array('rgb' => 'FF0000'),
            ),
        );
        $sampleLabelStyle = array(
            'font' => array(
                'bold' => true,
                'size' => 13,
            ),
            'alignment' => array(
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
            ),
        );
        $sheetIndex = 0;
        $panelStatisticsQuery = "SELECT COUNT(spm.map_id) AS participating_sites,
            SUM(CASE WHEN SUBSTRING(spm.evaluation_status, 3, 1) = '1' THEN 1 ELSE 0 END) AS response_received,
            SUM(CASE WHEN spm.is_excluded = 'yes' THEN 1 ELSE 0 END) AS excluded,
            SUM(CASE WHEN IFNULL(spm.is_pt_test_not_performed, 'no') = 'no' THEN 1 ELSE 0 END) AS able_to_submit,
            SUM(CASE WHEN spm.shipment_score >= 80 THEN 1 ELSE 0 END) AS scored_higher_than_80,
            SUM(CASE WHEN spm.shipment_score = 100 THEN 1 ELSE 0 END) AS scored_100
            FROM shipment_participant_map AS spm
            JOIN participant AS p ON p.participant_id = spm.participant_id
            WHERE spm.shipment_id = ?";

                    $errorCodesQuery = "SELECT res.error_code, COUNT(*) AS number_of_occurrences
            FROM shipment_participant_map AS spm
            JOIN response_result_tb AS res ON res.shipment_map_id = spm.map_id
            JOIN participant AS p ON p.participant_id = spm.participant_id
            WHERE spm.shipment_id = ?
            AND res.error_code <> ''";

                    $nonParticipatingCountriesQuery = "SELECT countries.iso_name AS country_name,
            CASE WHEN IFNULL(spm.is_pt_test_not_performed, 'no') = 'yes' THEN IFNULL(rntr.not_tested_reason, 'Unknown') ELSE NULL END AS not_tested_reason,
            SUM(CASE WHEN IFNULL(spm.is_pt_test_not_performed, 'no') = 'yes' THEN 1 ELSE 0 END) AS is_pt_test_not_performed,
            COUNT(spm.map_id) AS number_of_participants
            FROM shipment_participant_map AS spm
            JOIN participant AS p ON p.participant_id = spm.participant_id
            JOIN countries ON countries.id = p.country
            LEFT JOIN r_response_not_tested_reasons AS rntr ON rntr.not_tested_reason_id = spm.not_tested_reason
            WHERE spm.shipment_id = ?";

                    $discordantResultsInnerQuery = "FROM (
            SELECT p.unique_identifier,
                p.lab_name,
                ref.sample_id,
                ref.sample_label,
                res.mtb_detected AS res_mtb,
                CASE WHEN a.short_name = 'MTB Ultra' THEN ref.ultra_mtb_detected ELSE ref.mtb_rif_mtb_detected END AS ref_mtb,
                res.rif_resistance AS res_rif,
                CASE WHEN a.short_name = 'MTB Ultra' THEN ref.ultra_rif_resistance ELSE ref.mtb_rif_rif_resistance END AS ref_rif,
                CASE WHEN res.mtb_detected IN ('detected', 'high', 'medium', 'low', 'veryLow', 'trace') THEN 1 ELSE 0 END AS res_mtb_detected,
                CASE WHEN (a.short_name = 'MTB Ultra' AND ref.ultra_mtb_detected IN ('detected', 'high', 'medium', 'low', 'veryLow', 'trace')) OR (IFNULL(a.short_name, 'MTB/RIF') = 'MTB/RIF' AND ref.mtb_rif_mtb_detected IN ('detected', 'high', 'medium', 'low', 'veryLow', 'trace')) THEN 1 ELSE 0 END AS ref_mtb_detected,
                CASE WHEN res.mtb_detected = 'notDetected' THEN 1 ELSE 0 END AS res_mtb_not_detected,
                CASE WHEN (a.short_name = 'MTB Ultra' AND ref.ultra_mtb_detected = 'notDetected') OR (IFNULL(a.short_name, 'MTB/RIF') = 'MTB/RIF' AND ref.mtb_rif_mtb_detected = 'notDetected') THEN 1 ELSE 0 END AS ref_mtb_not_detected,
                CASE WHEN res.mtb_detected IN ('detected', 'high', 'medium', 'low', 'veryLow', 'trace') AND res.rif_resistance = 'detected' THEN 1 ELSE 0 END AS res_rif_resistance_detected,
                CASE WHEN (a.short_name = 'MTB Ultra' AND ref.ultra_rif_resistance = 'detected') OR (IFNULL(a.short_name, 'MTB/RIF') = 'MTB/RIF' AND ref.mtb_rif_rif_resistance = 'detected') THEN 1 ELSE 0 END AS ref_rif_resistance_detected,
                CASE WHEN res.mtb_detected IN ('notDetected', 'detected', 'high', 'medium', 'low', 'veryLow') AND IFNULL(res.rif_resistance, '') IN ('notDetected', 'na', '') THEN 1 ELSE 0 END AS res_rif_resistance_not_detected,
                CASE WHEN (a.short_name = 'MTB Ultra' AND ref.ultra_rif_resistance <> 'detected') OR (IFNULL(a.short_name, 'MTB/RIF') = 'MTB/RIF' AND ref.mtb_rif_rif_resistance <> 'detected') THEN 1 ELSE 0 END AS ref_rif_resistance_not_detected
            FROM shipment_participant_map AS spm
            JOIN participant AS p ON p.participant_id = spm.participant_id
            JOIN response_result_tb AS res ON res.shipment_map_id = spm.map_id
            JOIN reference_result_tb AS ref ON ref.shipment_id = spm.shipment_id
                                            AND ref.sample_id = res.sample_id
            LEFT JOIN r_tb_assay AS a ON a.id = JSON_UNQUOTE(JSON_EXTRACT(spm.attributes, \"$.assay\"))
            WHERE spm.shipment_id = ?
            AND SUBSTR(spm.evaluation_status, 3, 1) = '1'
            AND IFNULL(spm.is_pt_test_not_performed, 'no') <> 'yes'";

                    $discordantCountriesQuery = "SELECT mtb_rif_detection_results.country_name,
            SUM(CASE WHEN (mtb_rif_detection_results.res_mtb_detected = 1 AND mtb_rif_detection_results.ref_mtb_not_detected = 1) OR (mtb_rif_detection_results.res_mtb_not_detected = 1 AND mtb_rif_detection_results.ref_mtb_detected = 1) OR (mtb_rif_detection_results.res_rif_resistance_detected = 1 AND mtb_rif_detection_results.ref_rif_resistance_not_detected = 1) THEN 1 ELSE 0 END) AS discordant,
            COUNT(mtb_rif_detection_results.country_id) AS total_results
            FROM (
            SELECT countries.id AS country_id,
                countries.iso_name AS country_name,
                CASE WHEN res.mtb_detected IN ('detected', 'high', 'medium', 'low', 'veryLow', 'trace') THEN 1 ELSE 0 END AS res_mtb_detected,
                CASE WHEN (a.short_name = 'MTB Ultra' AND ref.ultra_mtb_detected IN ('detected', 'high', 'medium', 'low', 'veryLow', 'trace')) OR (IFNULL(a.short_name, 'MTB/RIF') = 'MTB/RIF' AND ref.mtb_rif_mtb_detected IN ('detected', 'high', 'medium', 'low', 'veryLow', 'trace')) THEN 1 ELSE 0 END AS ref_mtb_detected,
                CASE WHEN res.mtb_detected = 'notDetected' THEN 1 ELSE 0 END AS res_mtb_not_detected,
                CASE WHEN (a.short_name = 'MTB Ultra' AND ref.ultra_mtb_detected = 'notDetected') OR (IFNULL(a.short_name, 'MTB/RIF') = 'MTB/RIF' AND ref.mtb_rif_mtb_detected = 'notDetected') THEN 1 ELSE 0 END AS ref_mtb_not_detected,
                CASE WHEN res.mtb_detected IN ('detected', 'high', 'medium', 'low', 'veryLow', 'trace') AND res.rif_resistance = 'detected' THEN 1 ELSE 0 END AS res_rif_resistance_detected,
                CASE WHEN (a.short_name = 'MTB Ultra' AND ref.ultra_rif_resistance = 'detected') OR (IFNULL(a.short_name, 'MTB/RIF') = 'MTB/RIF' AND ref.mtb_rif_rif_resistance = 'detected') THEN 1 ELSE 0 END AS ref_rif_resistance_detected,
                CASE WHEN res.mtb_detected IN ('notDetected', 'detected', 'high', 'medium', 'low', 'veryLow') AND IFNULL(res.rif_resistance, '') IN ('notDetected', 'na', '') THEN 1 ELSE 0 END AS res_rif_resistance_not_detected,
                CASE WHEN (a.short_name = 'MTB Ultra' AND ref.ultra_rif_resistance <> 'detected') OR (IFNULL(a.short_name, 'MTB/RIF') = 'MTB/RIF' AND ref.mtb_rif_rif_resistance <> 'detected') THEN 1 ELSE 0 END AS ref_rif_resistance_not_detected
            FROM shipment_participant_map AS spm
            JOIN participant AS p ON p.participant_id = spm.participant_id
            JOIN countries ON countries.id = p.country
            JOIN response_result_tb AS res ON res.shipment_map_id = spm.map_id
            JOIN reference_result_tb AS ref ON ref.shipment_id = spm.shipment_id
                                            AND ref.sample_id = res.sample_id
            LEFT JOIN r_tb_assay AS a ON a.id = JSON_UNQUOTE(JSON_EXTRACT(spm.attributes, \"$.assay\"))
            WHERE spm.shipment_id = 23
            AND SUBSTR(spm.evaluation_status, 3, 1) = '1'
            AND IFNULL(spm.is_pt_test_not_performed, 'no') <> 'yes'";
                    if ($authNameSpace->is_ptcc_coordinator) {
                        $panelStatisticsQuery .= "
            AND p.country IN (".implode(",",$authNameSpace->countries).")";
                        $errorCodesQuery .= "
            AND p.country IN (".implode(",",$authNameSpace->countries).")";
                        $nonParticipatingCountriesQuery .= "
            AND p.country IN (".implode(",",$authNameSpace->countries).")";
                        $discordantResultsInnerQuery .= "
            AND p.country IN (".implode(",",$authNameSpace->countries).")";
                        $discordantCountriesQuery .= "
            AND p.country IN (".implode(",",$authNameSpace->countries).")";
                    }
                    $panelStatisticsQuery .= ";";
                    $errorCodesQuery .= "
            GROUP BY res.error_code
            ORDER BY error_code ASC;";
                    $nonParticipatingCountriesQuery .= "
            GROUP BY countries.iso_name, rntr.not_tested_reason
            ORDER BY countries.iso_name, rntr.not_tested_reason ASC;";
                    $discordantResultsInnerQuery .= "
            ) AS mtb_rif_detection_results";

                    $discordantResultsQuery = "SELECT mtb_rif_detection_results.sample_label,
            SUM(CASE WHEN mtb_rif_detection_results.res_mtb_detected = 1 AND mtb_rif_detection_results.ref_mtb_not_detected = 1 THEN 1 ELSE 0 END) AS false_positives,
            SUM(CASE WHEN mtb_rif_detection_results.res_mtb_not_detected = 1 AND mtb_rif_detection_results.ref_mtb_detected = 1 THEN 1 ELSE 0 END) AS false_negatives,
            SUM(CASE WHEN mtb_rif_detection_results.res_rif_resistance_detected = 1 AND mtb_rif_detection_results.ref_rif_resistance_not_detected = 1 THEN 1 ELSE 0 END) AS false_resistances
            ".$discordantResultsInnerQuery."
            GROUP BY mtb_rif_detection_results.sample_id
            ORDER BY mtb_rif_detection_results.sample_id ASC;";

                    $discordantResultsParticipantsQuery = "SELECT LPAD(mtb_rif_detection_results.unique_identifier, 10, '0') AS sorting_unique_identifier,
            mtb_rif_detection_results.unique_identifier,
            mtb_rif_detection_results.lab_name,
            mtb_rif_detection_results.sample_label,
            mtb_rif_detection_results.sample_id,
            CASE
                WHEN mtb_rif_detection_results.res_mtb = 'error' THEN 'Error'
                WHEN mtb_rif_detection_results.res_mtb = 'notDetected' THEN 'Not Detected'
                WHEN mtb_rif_detection_results.res_mtb = 'noResult' THEN 'No Result'
                WHEN mtb_rif_detection_results.res_mtb = 'veryLow' THEN 'Very Low'
                WHEN mtb_rif_detection_results.res_mtb = 'trace' THEN 'Trace'
                WHEN mtb_rif_detection_results.res_mtb = 'na' THEN 'N/A'
                WHEN IFNULL(mtb_rif_detection_results.res_mtb, '') = '' THEN NULL
                ELSE CONCAT(UPPER(SUBSTRING(mtb_rif_detection_results.res_mtb, 1, 1)), SUBSTRING(mtb_rif_detection_results.res_mtb, 2, 254))
            END AS res_mtb_detected,
            CASE
                WHEN mtb_rif_detection_results.ref_mtb = 'error' THEN 'Error'
                WHEN mtb_rif_detection_results.ref_mtb = 'notDetected' THEN 'Not Detected'
                WHEN mtb_rif_detection_results.ref_mtb = 'noResult' THEN 'No Result'
                WHEN mtb_rif_detection_results.ref_mtb = 'veryLow' THEN 'Very Low'
                WHEN mtb_rif_detection_results.ref_mtb = 'trace' THEN 'Trace'
                WHEN mtb_rif_detection_results.ref_mtb = 'na' THEN 'N/A'
                WHEN IFNULL(mtb_rif_detection_results.ref_mtb, '') = '' THEN NULL
                ELSE CONCAT(UPPER(SUBSTRING(mtb_rif_detection_results.ref_mtb, 1, 1)), SUBSTRING(mtb_rif_detection_results.ref_mtb, 2, 254))
            END AS ref_mtb_detected,
            CASE
                WHEN mtb_rif_detection_results.res_mtb = 'error' THEN 'Error'
                WHEN mtb_rif_detection_results.res_mtb = 'notDetected' THEN 'Not Detected'
                WHEN mtb_rif_detection_results.res_mtb = 'noResult' THEN 'No Result'
                WHEN mtb_rif_detection_results.res_mtb = 'invalid' THEN 'Invalid'
                WHEN mtb_rif_detection_results.res_mtb IN ('detected', 'trace', 'veryLow', 'low', 'medium', 'high') AND IFNULL(mtb_rif_detection_results.res_rif, 'na') = 'na' THEN 'Not Detected'
                WHEN mtb_rif_detection_results.res_rif = 'notDetected' THEN 'Not Detected'
                WHEN mtb_rif_detection_results.res_rif = 'noResult' THEN 'No Result'
                WHEN mtb_rif_detection_results.res_rif = 'veryLow' THEN 'Very Low'
                WHEN mtb_rif_detection_results.res_rif = 'na' THEN 'N/A'
                WHEN mtb_rif_detection_results.res_rif = 'notDetected' AND IFNULL(mtb_rif_detection_results.res_rif, '') = '' THEN 'N/A'
                WHEN mtb_rif_detection_results.res_rif IN ('noResult', 'notDetected', 'invalid') AND IFNULL(mtb_rif_detection_results.res_rif, '') = '' THEN 'N/A'
                ELSE CONCAT(UPPER(SUBSTRING(mtb_rif_detection_results.res_rif, 1, 1)), SUBSTRING(mtb_rif_detection_results.res_rif, 2, 254))
            END AS res_rif_resistance,
            CASE
                WHEN mtb_rif_detection_results.ref_mtb = 'error' THEN 'Error'
                WHEN mtb_rif_detection_results.ref_mtb = 'notDetected' THEN 'Not Detected'
                WHEN mtb_rif_detection_results.ref_mtb = 'noResult' THEN 'No Result'
                WHEN mtb_rif_detection_results.ref_mtb = 'invalid' THEN 'Invalid'
                WHEN mtb_rif_detection_results.ref_mtb IN ('detected', 'trace', 'veryLow', 'low', 'medium', 'high') AND IFNULL(mtb_rif_detection_results.ref_rif, 'na') = 'na' THEN 'Not Detected'
                WHEN mtb_rif_detection_results.ref_rif = 'notDetected' THEN 'Not Detected'
                WHEN mtb_rif_detection_results.ref_rif = 'noResult' THEN 'No Result'
                WHEN mtb_rif_detection_results.ref_rif = 'veryLow' THEN 'Very Low'
                WHEN mtb_rif_detection_results.ref_rif = 'na' THEN 'N/A'
                WHEN mtb_rif_detection_results.ref_rif = 'notDetected' AND IFNULL(mtb_rif_detection_results.ref_rif, '') = '' THEN 'N/A'
                WHEN mtb_rif_detection_results.ref_mtb IN ('noResult', 'notDetected', 'invalid') AND IFNULL(mtb_rif_detection_results.ref_rif, '') = '' THEN 'N/A'
                ELSE CONCAT(UPPER(SUBSTRING(mtb_rif_detection_results.ref_rif, 1, 1)), SUBSTRING(mtb_rif_detection_results.ref_rif, 2, 254))
            END AS ref_rif_resistance,
            CASE
                WHEN mtb_rif_detection_results.res_mtb_detected = 1 AND mtb_rif_detection_results.ref_mtb_not_detected = 1 THEN 'False Positive'
                WHEN mtb_rif_detection_results.res_mtb_not_detected = 1 AND mtb_rif_detection_results.ref_mtb_detected = 1 THEN 'False Negative'
                WHEN mtb_rif_detection_results.res_rif_resistance_detected = 1 AND mtb_rif_detection_results.ref_rif_resistance_not_detected = 1 THEN 'False Resistance Detected'
            END AS non_concordance_reason
            ".$discordantResultsInnerQuery."
            WHERE (mtb_rif_detection_results.res_mtb_detected = 1 AND mtb_rif_detection_results.ref_mtb_not_detected = 1)
            OR (mtb_rif_detection_results.res_mtb_not_detected = 1 AND mtb_rif_detection_results.ref_mtb_detected = 1)
            OR (mtb_rif_detection_results.res_rif_resistance_detected = 1 AND mtb_rif_detection_results.ref_rif_resistance_not_detected = 1)
            ORDER BY sorting_unique_identifier ASC, sample_id ASC;";

                    $discordantCountriesQuery .= "
            ) AS mtb_rif_detection_results
            GROUP BY mtb_rif_detection_results.country_id
            ORDER BY mtb_rif_detection_results.country_name ASC;";
                    $panelStatistics = $db->query($panelStatisticsQuery, array($params['shipmentId']))->fetchAll()[0];
                    $shipmentQuery = $db->select('shipment_code')
                        ->from('shipment')
                        ->where('shipment_id=?', $params['shipmentId']);
                    $shipmentResult = $db->fetchRow($shipmentQuery);
                    $panelStatisticsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, "Panel Statistics");
                    $excel->addSheet($panelStatisticsSheet, $sheetIndex);
                    $sheetIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode("Panel Statistics for " . $shipmentResult['shipment_code'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow(0, 1)->applyFromArray($sheetHeaderStyle);
                    $panelStatisticsSheet->getRowDimension(1)->setRowHeight(25);
                    $rowIndex = 3;
                    $columnIndex = 0;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Number of Participating Sites", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($panelStatistics["participating_sites"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $rowIndex++;
                    $columnIndex = 0;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Number of Responses Received", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($panelStatistics["response_received"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $rowIndex++;
                    $columnIndex = 0;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Number of Responses Excluded", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($panelStatistics["excluded"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $rowIndex++;
                    $columnIndex = 0;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Number of Participants Able to Submit", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($panelStatistics["able_to_submit"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $rowIndex++;
                    $columnIndex = 0;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Number of Participants Scoring 80% or Higher", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($panelStatistics["scored_higher_than_80"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $rowIndex++;
                    $columnIndex = 0;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Number of Participants Scoring 100%", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($panelStatistics["scored_100"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $rowIndex++;
                    $rowIndex++;
                    $columnIndex = 0;

                    $nonParticipantingCountries = $db->query($nonParticipatingCountriesQuery, array($params['shipmentId']))->fetchAll();
                    $nonParticipatingCountriesExist = false;
                    $nonParticipationReasons = [];
                    foreach ($nonParticipantingCountries as $nonParticipantingCountry) {
                        if (isset($nonParticipantingCountry['not_tested_reason']) && !in_array($nonParticipantingCountry['not_tested_reason'], $nonParticipationReasons)) {
                            $nonParticipatingCountriesExist = true;
                            array_push($nonParticipationReasons, $nonParticipantingCountry['not_tested_reason']);
                        }
                    }
                    sort($nonParticipationReasons);
                    if ($nonParticipatingCountriesExist) {
                        $nonParticipatingCountriesMap = [];
                        foreach ($nonParticipantingCountries as $nonParticipantingCountry) {
                            if (!array_key_exists($nonParticipantingCountry['country_name'], $nonParticipatingCountriesMap)) {
                                $nonParticipatingCountriesMap[$nonParticipantingCountry['country_name']] = array(
                                    'not_participated' => 0,
                                    'total_participants' => 0
                                );
                                foreach ($nonParticipationReasons as $nonParticipationReason) {
                                    $nonParticipatingCountriesMap[$nonParticipantingCountry['country_name']][$nonParticipationReason] = 0;
                                }
                            }
                            $nonParticipatingCountriesMap[$nonParticipantingCountry['country_name']]['total_participants'] += intval($nonParticipantingCountry['number_of_participants']);
                            if (isset($nonParticipantingCountry['not_tested_reason'])) {
                                $nonParticipatingCountriesMap[$nonParticipantingCountry['country_name']][$nonParticipantingCountry['not_tested_reason']] = intval($nonParticipantingCountry['is_pt_test_not_performed']);
                                $nonParticipatingCountriesMap[$nonParticipantingCountry['country_name']]['not_participated'] += intval($nonParticipantingCountry['is_pt_test_not_performed']);
                            }
                        }
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("List of countries with non-participating sites", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                        $columnIndex++;
                        foreach ($nonParticipationReasons as $nonParticipationReason) {
                            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($nonParticipationReason, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                            $columnIndex++;
                        }
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Total", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                        $columnIndex++;
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Rate non-participation", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);

                        $rowIndex++;
                        foreach($nonParticipatingCountriesMap as $nonParticipatingCountryName => $nonParticipatingCountryData) {
                            if ($nonParticipatingCountryData['not_participated'] > 0) {
                                $columnIndex = 0;
                                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($nonParticipatingCountryName, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                $columnIndex++;
                                foreach ($nonParticipationReasons as $nonParticipationReason) {
                                    if (isset($nonParticipatingCountryData[$nonParticipationReason])) {
                                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($nonParticipatingCountryData[$nonParticipationReason], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    }
                                    $columnIndex++;
                                }
                                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($nonParticipatingCountryData['not_participated'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                $columnIndex++;
                                $notParticipatedRatio = 0;
                                if ($nonParticipatingCountryData['total_participants'] > 0) {
                                    $notParticipatedRatio = $nonParticipatingCountryData['not_participated'] / $nonParticipatingCountryData['total_participants'];
                                }
                                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($notParticipatedRatio, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->getNumberFormat()->applyFromArray(
                                    array(
                                        'code' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00
                                    )
                                );
                                $rowIndex++;
                            }
                        }
                        $rowIndex++;
                        $columnIndex = 0;
                    }

                    $errorCodes = $db->query($errorCodesQuery, array($params['shipmentId']))->fetchAll();
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Error Codes Encountered", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Number of Occurrences", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $rowIndex++;
                    $columnIndex = 0;
                    foreach ($errorCodes as $errorCode) {
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($errorCode['error_code'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $columnIndex++;
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($errorCode['number_of_occurrences'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $rowIndex++;
                        $columnIndex = 0;
                    }

                    $discordantResults = $db->query($discordantResultsQuery, array($params['shipmentId']))->fetchAll();
                    $rowIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Discordant Results", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    foreach ($discordantResults as $discordantResultAggregate) {
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantResultAggregate['sample_label'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                        $columnIndex++;
                    }
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Total", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $rowIndex++;
                    $columnIndex = 0;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("False positives", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $falsePositivesTotal = 0;
                    foreach ($discordantResults as $discordantResultAggregate) {
                        $columnIndex++;
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantResultAggregate['false_positives'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $falsePositivesTotal += intval($discordantResultAggregate['false_positives']);
                    }
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($falsePositivesTotal, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $rowIndex++;
                    $columnIndex = 0;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("False negatives", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $falseNegativesTotal = 0;
                    foreach ($discordantResults as $discordantResultAggregate) {
                        $columnIndex++;
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantResultAggregate['false_negatives'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $falseNegativesTotal += intval($discordantResultAggregate['false_negatives']);
                    }
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($falseNegativesTotal, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $rowIndex++;
                    $columnIndex = 0;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("False resistance", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $falseResistanceTotal = 0;
                    foreach ($discordantResults as $discordantResultAggregate) {
                        $columnIndex++;
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantResultAggregate['false_resistances'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $falseResistanceTotal += intval($discordantResultAggregate['false_resistances']);
                    }
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($falseResistanceTotal, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

                    $discordantCountries = $db->query($discordantCountriesQuery, array($params['shipmentId']))->fetchAll();
                    $rowIndex++;
                    $rowIndex++;
                    $columnIndex = 0;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("List the countries reporting discordant results + count of discordant results", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex + 1, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex + 2, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $panelStatisticsSheet->mergeCells("A" . ($rowIndex) . ":C" . ($rowIndex));
                    $rowIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Country", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("# Discordant", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("% Discordant", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    foreach ($discordantCountries as $discordantCountry) {
                        $rowIndex++;
                        $columnIndex = 0;
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantCountry['country_name'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $columnIndex++;
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode(intval($discordantCountry['discordant']), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $columnIndex++;
                        $countryDiscordantRatio = 0;
                        if (intval($discordantCountry['total_results']) > 0) {
                            $countryDiscordantRatio = intval($discordantCountry['discordant']) /  intval($discordantCountry['total_results']);
                        }
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($countryDiscordantRatio, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->getNumberFormat()->applyFromArray(
                            array(
                                'code' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00
                            )
                        );
                    }

                    $discordantParticipants = $db->query($discordantResultsParticipantsQuery, array($params['shipmentId']))->fetchAll();
                    $rowIndex++;
                    $rowIndex++;
                    $columnIndex = 0;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("List the participants reporting discordant results", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex + 1, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex + 2, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $panelStatisticsSheet->mergeCells("A" . ($rowIndex) . ":H" . ($rowIndex));
                    $rowIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("PT ID", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Participant", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Sample", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("MTB Detected", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Expected MTB Detected", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Rif Resistance", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Expected Rif Resistance", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    $columnIndex++;
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Reason for Discordance", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $panelStatisticsSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($rowHeaderStyle);
                    foreach ($discordantParticipants as $discordantParticipant) {
                        $rowIndex++;
                        $columnIndex = 0;
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantParticipant['unique_identifier'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $columnIndex++;
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantParticipant['lab_name'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $columnIndex++;
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantParticipant['sample_label'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $columnIndex++;
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantParticipant['res_mtb_detected'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $columnIndex++;
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantParticipant['ref_mtb_detected'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $columnIndex++;
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantParticipant['res_rif_resistance'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $columnIndex++;
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantParticipant['ref_rif_resistance'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $columnIndex++;
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantParticipant['non_concordance_reason'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }

                    foreach (range('A', 'Z') as $columnID) {
                        $panelStatisticsSheet->getColumnDimension($columnID)->setAutoSize(true);
                    }

                    if (!$authNameSpace->is_ptcc_coordinator) {
                        $this->getTbAllSitesResultsSheet($db, $params['shipmentId'], $excel, $sheetIndex);
                        $nonConcordanceThreshold = 2;
                        $expectedConcordance = 0.8;
                        $mtbRifAssayName = "Xpert MTB/RIF";
                        $mtbRifSubmissions = $db->query("SELECT s.shipment_id,
                    s.shipment_code,
                    LPAD(p.unique_identifier, 10, '0') AS sorting_unique_identifier,
                    p.unique_identifier,
                    c.iso_name AS country,
                    CONCAT(p.lab_name, COALESCE(CONCAT(' - ', CASE WHEN p.state = '' THEN NULL ELSE p.state END), CONCAT(' - ', CASE WHEN p.city = '' THEN NULL ELSE p.city END), '')) AS participant_name,
                    a.name AS assay,
                    ref.sample_id,
                    ref.sample_label,
                    DATEDIFF(res.date_tested, s.shipment_date) AS days_between_shipment_and_test,
                    res.probe_1 AS probe_d_ct,
                    res.probe_2 AS probe_c_ct,
                    res.probe_3 AS probe_e_ct,
                    res.probe_4 AS probe_b_ct,
                    res.probe_5 AS probe_spc_ct,
                    res.probe_6 AS probe_a_ct,
                    IFNULL(ref.mtb_rif_probe_d, 0) AS expected_probe_d_ct,
                    IFNULL(ref.mtb_rif_probe_c, 0) AS expected_probe_c_ct,
                    IFNULL(ref.mtb_rif_probe_e, 0) AS expected_probe_e_ct,
                    IFNULL(ref.mtb_rif_probe_b, 0) AS expected_probe_b_ct,
                    IFNULL(ref.mtb_rif_probe_spc, 0) AS expected_probe_spc_ct,
                    IFNULL(ref.mtb_rif_probe_a, 0) AS expected_probe_a_ct,
                    res.calculated_score
                    FROM shipment_participant_map AS spm
                    JOIN shipment AS s ON s.shipment_id = spm.shipment_id
                    JOIN response_result_tb AS res ON res.shipment_map_id = spm.map_id
                    JOIN reference_result_tb AS ref ON ref.shipment_id = s.shipment_id
                                                    AND ref.sample_id = res.sample_id
                    JOIN participant AS p ON p.participant_id = spm.participant_id
                    JOIN countries AS c ON c.id = p.country
                    JOIN r_tb_assay AS a ON a.id = JSON_UNQUOTE(JSON_EXTRACT(spm.attributes, \"$.assay\"))
                    WHERE spm.shipment_id = ?
                    AND IFNULL(spm.is_pt_test_not_performed, 'no') = 'no'
                    AND SUBSTRING(spm.evaluation_status, 3, 1) = '1'
                    AND SUBSTRING(spm.evaluation_status, 4, 1) = '1'
                    AND IFNULL(spm.is_excluded, 'no') = 'no'
                    AND a.name = ?
                    ORDER BY sorting_unique_identifier ASC, res.sample_id ASC;", array($params['shipmentId'], $mtbRifAssayName))
                                    ->fetchAll();
                                if (count($mtbRifSubmissions) > 0) {
                                    $mtbRifStability = $db->query("SELECT stability_mtb_rif.shipment_id,
                    stability_mtb_rif.shipment_code,
                    stability_mtb_rif.assay,
                    stability_mtb_rif.sample_label,
                    stability_mtb_rif.number_of_valid_submissions,
                    ROUND(stability_mtb_rif.sum_probe_d_ct / stability_mtb_rif.number_of_valid_submissions_probe_d, 2) AS mean_probe_d_ct,
                    ROUND(stability_mtb_rif.sum_probe_c_ct / stability_mtb_rif.number_of_valid_submissions_probe_c, 2) AS mean_probe_c_ct,
                    ROUND(stability_mtb_rif.sum_probe_e_ct / stability_mtb_rif.number_of_valid_submissions_probe_e, 2) AS mean_probe_e_ct,
                    ROUND(stability_mtb_rif.sum_probe_b_ct / stability_mtb_rif.number_of_valid_submissions_probe_b, 2) AS mean_probe_b_ct,
                    ROUND(stability_mtb_rif.sum_probe_spc_ct / stability_mtb_rif.number_of_valid_submissions_probe_spc, 2) AS mean_probe_spc_ct,
                    ROUND(stability_mtb_rif.sum_probe_a_ct / stability_mtb_rif.number_of_valid_submissions_probe_a, 2) AS mean_probe_a_ct,
                    stability_mtb_rif.expected_probe_d_ct,
                    stability_mtb_rif.expected_probe_c_ct,
                    stability_mtb_rif.expected_probe_e_ct,
                    stability_mtb_rif.expected_probe_b_ct,
                    stability_mtb_rif.expected_probe_spc_ct,
                    stability_mtb_rif.expected_probe_a_ct
                    FROM (SELECT s.shipment_id,
                    s.shipment_code,
                    a.name AS assay,
                    ref.sample_id,
                    ref.sample_label,
                    SUM(CASE WHEN (IFNULL(res.probe_1, 0) > 0 OR IFNULL(ref.mtb_rif_probe_d, 0) = 0) AND
                                (IFNULL(res.probe_2, 0) > 0 OR IFNULL(ref.mtb_rif_probe_c, 0) = 0) AND
                                (IFNULL(res.probe_3, 0) > 0 OR IFNULL(ref.mtb_rif_probe_e, 0) = 0) AND
                                (IFNULL(res.probe_4, 0) > 0 OR IFNULL(ref.mtb_rif_probe_b, 0) = 0) AND
                                (IFNULL(res.probe_5, 0) > 0 OR IFNULL(ref.mtb_rif_probe_spc, 0) = 0) AND
                                (IFNULL(res.probe_6, 0) > 0 OR IFNULL(ref.mtb_rif_probe_a, 0) = 0) THEN 1 ELSE 0 END) AS number_of_valid_submissions,
                    SUM(CASE WHEN IFNULL(res.probe_1, 0) > 0 THEN 1 ELSE 1 END) AS number_of_valid_submissions_probe_d,
                    SUM(CASE WHEN IFNULL(res.probe_2, 0) > 0 THEN 1 ELSE 1 END) AS number_of_valid_submissions_probe_c,
                    SUM(CASE WHEN IFNULL(res.probe_3, 0) > 0 THEN 1 ELSE 1 END) AS number_of_valid_submissions_probe_e,
                    SUM(CASE WHEN IFNULL(res.probe_4, 0) > 0 THEN 1 ELSE 1 END) AS number_of_valid_submissions_probe_b,
                    SUM(CASE WHEN IFNULL(res.probe_5, 0) > 0 THEN 1 ELSE 1 END) AS number_of_valid_submissions_probe_spc,
                    SUM(CASE WHEN IFNULL(res.probe_6, 0) > 0 THEN 1 ELSE 1 END) AS number_of_valid_submissions_probe_a,
                    SUM(IFNULL(res.probe_1, 0)) AS sum_probe_d_ct,
                    SUM(IFNULL(res.probe_2, 0)) AS sum_probe_c_ct,
                    SUM(IFNULL(res.probe_3, 0)) AS sum_probe_e_ct,
                    SUM(IFNULL(res.probe_4, 0)) AS sum_probe_b_ct,
                    SUM(IFNULL(res.probe_5, 0)) AS sum_probe_spc_ct,
                    SUM(IFNULL(res.probe_6, 0)) AS sum_probe_a_ct,
                    IFNULL(ref.mtb_rif_probe_d, 0) AS expected_probe_d_ct,
                    IFNULL(ref.mtb_rif_probe_c, 0) AS expected_probe_c_ct,
                    IFNULL(ref.mtb_rif_probe_e, 0) AS expected_probe_e_ct,
                    IFNULL(ref.mtb_rif_probe_b, 0) AS expected_probe_b_ct,
                    IFNULL(ref.mtb_rif_probe_spc, 0) AS expected_probe_spc_ct,
                    IFNULL(ref.mtb_rif_probe_a, 0) AS expected_probe_a_ct
                    FROM reference_result_tb AS ref
                    JOIN shipment AS s ON s.shipment_id = ref.shipment_id
                    LEFT JOIN shipment_participant_map AS spm ON spm.shipment_id = s.shipment_id
                                                                AND IFNULL(spm.is_pt_test_not_performed, 'no') = 'no'
                                                                AND SUBSTRING(spm.evaluation_status, 3, 1) = '1'
                                                                AND SUBSTRING(spm.evaluation_status, 4, 1) = '1'
                                                                AND IFNULL(spm.is_excluded, 'no') = 'no'
                    LEFT JOIN r_tb_assay AS a ON a.id = JSON_UNQUOTE(JSON_EXTRACT(spm.attributes, \"$.assay\"))
                    LEFT JOIN response_result_tb AS res ON res.shipment_map_id = spm.map_id
                                                        AND ref.sample_id = res.sample_id
                                                        AND IFNULL(res.calculated_score, 'pass') = 'pass'
                    WHERE ref.shipment_id = ?
                    AND a.name = ?
                    GROUP BY ref.sample_id) AS stability_mtb_rif
                    ORDER BY stability_mtb_rif.sample_id;", array($params['shipmentId'], $mtbRifAssayName))
                                ->fetchAll();

                            $mtbRifStabilitySheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, "Xpert MTB RIF Stability");
                            $excel->addSheet($mtbRifStabilitySheet, $sheetIndex);
                            $sheetIndex++;
                            $mtbRifStabilitySheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode("MTB/RIF Panel Stability for " . $mtbRifSubmissions[0]["shipment_code"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifStabilitySheet->getStyleByColumnAndRow(0, 1)->applyFromArray($sheetHeaderStyle);
                            $mtbRifStabilitySheet->getRowDimension(1)->setRowHeight(25);
                            $rowIndex = 3;
                            $columnIndex = 0;
                            $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Sample", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("# Valid Submissions", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe D", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe C", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe E", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe B", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe SPC", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe A", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $rowIndex++;
                            foreach ($mtbRifStability as $mtbRifStabilitySample) {
                                try {
                                    $columnIndex = 0;
                                    $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifStabilitySample["sample_label"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                    $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($sampleLabelStyle);
                                    $columnIndex++;
                                    $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifStabilitySample["number_of_valid_submissions"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($sampleLabelStyle);
                                    $columnIndex++;
                                    $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Mean", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                    $columnIndex++;
                                    $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifStabilitySample["mean_probe_d_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    if ($mtbRifStabilitySample["mean_probe_d_ct"] - $mtbRifStabilitySample["expected_probe_d_ct"] > $nonConcordanceThreshold) {
                                        $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                                    }
                                    $columnIndex++;
                                    $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifStabilitySample["mean_probe_c_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    if ($mtbRifStabilitySample["mean_probe_c_ct"] - $mtbRifStabilitySample["expected_probe_c_ct"] > $nonConcordanceThreshold) {
                                        $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                                    }
                                    $columnIndex++;
                                    $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifStabilitySample["mean_probe_e_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    if ($mtbRifStabilitySample["mean_probe_e_ct"] - $mtbRifStabilitySample["expected_probe_e_ct"] > $nonConcordanceThreshold) {
                                        $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                                    }
                                    $columnIndex++;
                                    $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifStabilitySample["mean_probe_b_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    if ($mtbRifStabilitySample["mean_probe_b_ct"] - $mtbRifStabilitySample["expected_probe_b_ct"] > $nonConcordanceThreshold) {
                                        $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                                    }
                                    $columnIndex++;
                                    $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifStabilitySample["mean_probe_spc_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    if ($mtbRifStabilitySample["mean_probe_spc_ct"] - $mtbRifStabilitySample["expected_probe_spc_ct"] > $nonConcordanceThreshold) {
                                        $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                                    }
                                    $columnIndex++;
                                    $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifStabilitySample["mean_probe_a_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    if ($mtbRifStabilitySample["mean_probe_a_ct"] - $mtbRifStabilitySample["expected_probe_a_ct"] > $nonConcordanceThreshold) {
                                        $mtbRifStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                                    }
                                    $columnIndex = 2;
                                    $rowIndex++;
                                    $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Expected", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                    $columnIndex++;
                                    $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifStabilitySample["expected_probe_d_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    $columnIndex++;
                                    $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifStabilitySample["expected_probe_c_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    $columnIndex++;
                                    $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifStabilitySample["expected_probe_e_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    $columnIndex++;
                                    $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifStabilitySample["expected_probe_b_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    $columnIndex++;
                                    $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifStabilitySample["expected_probe_spc_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    $columnIndex++;
                                    $mtbRifStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifStabilitySample["expected_probe_a_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    $rowIndex++;
                                } catch (Exception $e) {
                                    error_log($e->getMessage(), 0);
                                    error_log($e->getTraceAsString(), 0);
                                }
                            }
                            $rowIndex = 4;
                            foreach ($mtbRifStability as $mtbRifStabilitySample) {
                                $mtbRifStabilitySheet->mergeCells("A" . ($rowIndex) . ":A" . ($rowIndex + 1));
                                $mtbRifStabilitySheet->mergeCells("B" . ($rowIndex) . ":B" . ($rowIndex + 1));
                                $rowIndex++;
                                $rowIndex++;
                            }
                            $rowIndex++;
                            $mtbRifStabilitySheet->getCellByColumnAndRow(3, $rowIndex)->setValueExplicit(html_entity_decode("Values highlighted in red indicate mean Ct values which are above ".$nonConcordanceThreshold." cycles from the expected value", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifStabilitySheet->getStyleByColumnAndRow(3, $rowIndex)->applyFromArray($nonConcordanceStyle);
                            $mtbRifStabilitySheet->mergeCells("D" . ($rowIndex) . ":I" . ($rowIndex));

                            $mtbRifStabilitySheet->getDefaultRowDimension()->setRowHeight(15);
                            foreach (range('A', 'Z') as $columnID) {
                                $mtbRifStabilitySheet->getColumnDimension($columnID)->setAutoSize(true);
                            }

                            $mtbRifConcordanceSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, "Xpert MTB RIF Concordance");
                            $excel->addSheet($mtbRifConcordanceSheet, $sheetIndex);
                            $sheetIndex++;

                            $mtbRifConcordanceSheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode("MTB/RIF Panel Concordance for " . $mtbRifSubmissions[0]["shipment_code"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow(0, 1)->applyFromArray($sheetHeaderStyle);
                            $mtbRifConcordanceSheet->getRowDimension(1)->setRowHeight(25);
                            $rowIndex = 3;
                            $columnIndex = 0;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Expected Values", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $mtbRifConcordanceSheet->mergeCells("A" . ($rowIndex) . ":C" . ($rowIndex));
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Concordance", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe D", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe C", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe E", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe B", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe SPC", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe A", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);

                            $mtbRifConcordance = [];
                            foreach ($mtbRifSubmissions as $mtbRifSubmission) {
                                if (!isset($mtbRifConcordance[$mtbRifSubmission['sample_label']])) {
                                    $mtbRifConcordance[$mtbRifSubmission['sample_label']] = array(
                                        "withinRange" => 0,
                                        "outsideOfRange" => 0,
                                        "totalValidSubmissions" => 0,
                                    );
                                }
                                if ($mtbRifSubmission["calculated_score"] == 'pass' &&
                                    ($mtbRifSubmission["probe_d_ct"] > 0 ||
                                        $mtbRifSubmission["probe_c_ct"] > 0 ||
                                        $mtbRifSubmission["probe_e_ct"] > 0 ||
                                        $mtbRifSubmission["probe_b_ct"] > 0 ||
                                        $mtbRifSubmission["probe_spc_ct"] > 0 ||
                                        $mtbRifSubmission["probe_a_ct"] > 0)
                                ) {
                                    $mtbRifConcordance[$mtbRifSubmission['sample_label']]["totalValidSubmissions"]++;
                                    if (
                                        $mtbRifSubmission["probe_d_ct"] - $mtbRifSubmission["expected_probe_d_ct"] > $nonConcordanceThreshold ||
                                        $mtbRifSubmission["probe_c_ct"] - $mtbRifSubmission["expected_probe_c_ct"] > $nonConcordanceThreshold ||
                                        $mtbRifSubmission["probe_e_ct"] - $mtbRifSubmission["expected_probe_e_ct"] > $nonConcordanceThreshold ||
                                        $mtbRifSubmission["probe_b_ct"] - $mtbRifSubmission["expected_probe_b_ct"] > $nonConcordanceThreshold ||
                                        $mtbRifSubmission["probe_spc_ct"] - $mtbRifSubmission["expected_probe_spc_ct"] > $nonConcordanceThreshold ||
                                        $mtbRifSubmission["probe_a_ct"] - $mtbRifSubmission["expected_probe_a_ct"] > $nonConcordanceThreshold
                                    ) {
                                        $mtbRifConcordance[$mtbRifSubmission['sample_label']]["outsideOfRange"]++;
                                    } else {
                                        $mtbRifConcordance[$mtbRifSubmission['sample_label']]["withinRange"]++;
                                    }
                                }
                            }

                            $rowIndex = 4;
                            $columnIndex = 0;
                            $currentParticipantId = $mtbRifSubmissions[0]["unique_identifier"];
                            $recordIndex = 0;
                            while ($mtbRifSubmissions[$recordIndex]["unique_identifier"] == $currentParticipantId) {
                                $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmissions[$recordIndex]["sample_label"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                $columnIndex++;
                                $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmissions[$recordIndex]["sample_label"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                $columnIndex++;
                                $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmissions[$recordIndex]["sample_label"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                $mtbRifConcordanceSheet->mergeCells("A" . ($rowIndex) . ":C" . ($rowIndex));
                                $columnIndex++;
                                $concordanceTotals = $mtbRifConcordance[$mtbRifSubmissions[$recordIndex]["sample_label"]];
                                $sampleConcordance = 0;
                                if ($concordanceTotals["totalValidSubmissions"] > 0) {
                                    $sampleConcordance = $concordanceTotals["withinRange"] / $concordanceTotals["totalValidSubmissions"];
                                }
                                $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($sampleConcordance, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                if ($sampleConcordance < $expectedConcordance) {
                                    $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                                }
                                $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->getNumberFormat()->applyFromArray(
                                    array(
                                        'code' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00
                                    )
                                );
                                $columnIndex++;
                                $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmissions[$recordIndex]["expected_probe_d_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                $columnIndex++;
                                $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmissions[$recordIndex]["expected_probe_c_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                $columnIndex++;
                                $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmissions[$recordIndex]["expected_probe_b_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                $columnIndex++;
                                $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmissions[$recordIndex]["expected_probe_e_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                $columnIndex++;
                                $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmissions[$recordIndex]["expected_probe_spc_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                $columnIndex++;
                                $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmissions[$recordIndex]["expected_probe_a_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                $rowIndex++;
                                $columnIndex = 0;
                                $recordIndex++;
                            }

                            $rowIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow(2, $rowIndex)->setValueExplicit(html_entity_decode("Values highlighted in red indicate that the percentage of valid results, where a Ct value for a probe was ".$nonConcordanceThreshold." cycles higher than the expected value, is outside of the acceptable range of ".($expectedConcordance * 100)."%", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow(2, $rowIndex)->applyFromArray($nonConcordanceStyle);
                            $mtbRifConcordanceSheet->mergeCells("C" . ($rowIndex) . ":J" . ($rowIndex ));

                            $rowIndex++;
                            $rowIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("PT ID", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Participant", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Country", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Sample", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("# Days Tested After Shipment", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe D", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe C", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe E", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe B", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe SPC", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $columnIndex++;
                            $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe A", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                            $rowIndex++;

                            $currentParticipantId = "";
                            foreach ($mtbRifSubmissions as $mtbRifSubmission) {
                                try {
                                    $columnIndex = 0;
                                    if ($currentParticipantId != $mtbRifSubmission["unique_identifier"]) {
                                        $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmission["unique_identifier"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                        $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($sampleLabelStyle);
                                        $columnIndex++;
                                        $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmission["participant_name"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                        $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($sampleLabelStyle);
                                        $columnIndex++;
                                        $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmission["country"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                        $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($sampleLabelStyle);
                                        $columnIndex++;
                                        $currentParticipantId = $mtbRifSubmission["unique_identifier"];
                                    } else {
                                        $columnIndex++;
                                        $columnIndex++;
                                        $columnIndex++;
                                    }
                                    $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmission["sample_label"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                    $columnIndex++;
                                    $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmission["days_between_shipment_and_test"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    $columnIndex++;
                                    $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmission["probe_d_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    if ($mtbRifSubmission["probe_d_ct"] - $mtbRifSubmission["expected_probe_d_ct"] > $nonConcordanceThreshold) {
                                        $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                                    }
                                    $columnIndex++;
                                    $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmission["probe_c_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    if ($mtbRifSubmission["probe_c_ct"] - $mtbRifSubmission["expected_probe_c_ct"] > $nonConcordanceThreshold) {
                                        $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                                    }
                                    $columnIndex++;
                                    $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmission["probe_e_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    if ($mtbRifSubmission["probe_e_ct"] - $mtbRifSubmission["expected_probe_e_ct"] > $nonConcordanceThreshold) {
                                        $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                                    }
                                    $columnIndex++;
                                    $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmission["probe_b_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    if ($mtbRifSubmission["probe_b_ct"] - $mtbRifSubmission["expected_probe_b_ct"] > $nonConcordanceThreshold) {
                                        $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                                    }
                                    $columnIndex++;
                                    $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmission["probe_spc_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    if ($mtbRifSubmission["probe_spc_ct"] - $mtbRifSubmission["expected_probe_spc_ct"] > $nonConcordanceThreshold) {
                                        $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                                    }
                                    $columnIndex++;
                                    $mtbRifConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbRifSubmission["probe_a_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                    if ($mtbRifSubmission["probe_a_ct"] - $mtbRifSubmission["expected_probe_a_ct"] > $nonConcordanceThreshold) {
                                        $mtbRifConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                                    }
                                    $rowIndex++;
                                } catch (Exception $e) {
                                    error_log($e->getMessage(), 0);
                                    error_log($e->getTraceAsString(), 0);
                                }
                            }

                            foreach (range('A', 'Z') as $columnID) {
                                $mtbRifConcordanceSheet->getColumnDimension($columnID)->setAutoSize(true);
                            }
                        }

                        $mtbUltraAssayName = "Xpert MTB Ultra";
                        $mtbUltraSubmissions = $db->query("SELECT s.shipment_id,
            s.shipment_code,
            LPAD(p.unique_identifier, 10, '0') AS sorting_unique_identifier,
            p.unique_identifier,
            c.iso_name AS country,
            CONCAT(p.lab_name, COALESCE(CONCAT(' - ', CASE WHEN p.state = '' THEN NULL ELSE p.state END), CONCAT(' - ', CASE WHEN p.city = '' THEN NULL ELSE p.city END), '')) AS participant_name,
            a.name AS assay,
            ref.sample_id,
            ref.sample_label,
            DATEDIFF(res.date_tested, s.shipment_date) AS days_between_shipment_and_test,
            res.probe_1 AS probe_spc_ct,
            res.probe_2 AS probe_is1081_is6110_ct,
            res.probe_3 AS probe_rpo_b1_ct,
            res.probe_4 AS probe_rpo_b2_ct,
            res.probe_5 AS probe_rpo_b3_ct,
            res.probe_6 AS probe_rpo_b4_ct,
            IFNULL(ref.ultra_probe_spc, 0) AS expected_probe_spc_ct,
            IFNULL(ref.ultra_probe_is1081_is6110, 0) AS expected_probe_is1081_is6110_ct,
            IFNULL(ref.ultra_probe_rpo_b1, 0) AS expected_probe_rpo_b1_ct,
            IFNULL(ref.ultra_probe_rpo_b2, 0) AS expected_probe_rpo_b2_ct,
            IFNULL(ref.ultra_probe_rpo_b3, 0) AS expected_probe_rpo_b3_ct,
            IFNULL(ref.ultra_probe_rpo_b4, 0) AS expected_probe_rpo_b4_ct,
            res.calculated_score
            FROM shipment_participant_map AS spm
            JOIN shipment AS s ON s.shipment_id = spm.shipment_id
            JOIN response_result_tb AS res ON res.shipment_map_id = spm.map_id
            JOIN reference_result_tb AS ref ON ref.shipment_id = s.shipment_id
                                            AND ref.sample_id = res.sample_id
            JOIN participant AS p ON p.participant_id = spm.participant_id
            JOIN countries AS c ON c.id = p.country
            JOIN r_tb_assay AS a ON a.id = JSON_UNQUOTE(JSON_EXTRACT(spm.attributes, \"$.assay\"))
            WHERE spm.shipment_id = ?
            AND IFNULL(spm.is_pt_test_not_performed, 'no') = 'no'
            AND SUBSTRING(spm.evaluation_status, 3, 1) = '1'
            AND SUBSTRING(spm.evaluation_status, 4, 1) = '1'
            AND IFNULL(spm.is_excluded, 'no') = 'no'
            AND a.name = ?
            ORDER BY sorting_unique_identifier ASC, res.sample_id ASC;", array($params['shipmentId'], $mtbUltraAssayName))
                            ->fetchAll();
                        if (count($mtbUltraSubmissions) > 0) {
                            $mtbUltraStability = $db->query("SELECT stability_mtb_ultra.shipment_id,
            stability_mtb_ultra.shipment_code,
            stability_mtb_ultra.assay,
            stability_mtb_ultra.sample_label,
            stability_mtb_ultra.number_of_valid_submissions,
            ROUND(stability_mtb_ultra.sum_probe_spc_ct / stability_mtb_ultra.number_of_valid_submissions_probe_spc, 2) AS mean_probe_spc_ct,
            ROUND(stability_mtb_ultra.sum_probe_is1081_is6110_ct / stability_mtb_ultra.number_of_valid_submissions_probe_is1081_is6110, 2) AS mean_probe_is1081_is6110_ct,
            ROUND(stability_mtb_ultra.sum_probe_rpo_b1_ct / stability_mtb_ultra.number_of_valid_submissions_probe_rpo_b1, 2) AS mean_probe_rpo_b1_ct,
            ROUND(stability_mtb_ultra.sum_probe_rpo_b2_ct / stability_mtb_ultra.number_of_valid_submissions_probe_rpo_b2, 2) AS mean_probe_rpo_b2_ct,
            ROUND(stability_mtb_ultra.sum_probe_rpo_b3_ct / stability_mtb_ultra.number_of_valid_submissions_probe_rpo_b3, 2) AS mean_probe_rpo_b3_ct,
            ROUND(stability_mtb_ultra.sum_probe_rpo_b4_ct / stability_mtb_ultra.number_of_valid_submissions_probe_rpo_b4, 2) AS mean_probe_rpo_b4_ct,
            stability_mtb_ultra.expected_probe_spc_ct,
            stability_mtb_ultra.expected_probe_is1081_is6110_ct,
            stability_mtb_ultra.expected_probe_rpo_b1_ct,
            stability_mtb_ultra.expected_probe_rpo_b2_ct,
            stability_mtb_ultra.expected_probe_rpo_b3_ct,
            stability_mtb_ultra.expected_probe_rpo_b4_ct
            FROM (SELECT s.shipment_id,
                    s.shipment_code,
                    a.name AS assay,
                    ref.sample_id,
                    ref.sample_label,
                    SUM(CASE WHEN (IFNULL(res.probe_1, 0) > 0 OR IFNULL(ref.ultra_probe_spc, 0) = 0) AND
                                (IFNULL(res.probe_2, 0) > 0 OR IFNULL(ref.ultra_probe_is1081_is6110, 0) = 0) AND
                                (IFNULL(res.probe_3, 0) > 0 OR IFNULL(ref.ultra_probe_rpo_b1, 0) = 0) AND
                                (IFNULL(res.probe_4, 0) > 0 OR IFNULL(ref.ultra_probe_rpo_b2, 0) = 0) AND
                                (IFNULL(res.probe_5, 0) > 0 OR IFNULL(ref.ultra_probe_rpo_b3, 0) = 0) AND
                                (IFNULL(res.probe_6, 0) > 0 OR IFNULL(ref.ultra_probe_rpo_b4, 0) = 0) THEN 1 ELSE 0 END) AS number_of_valid_submissions,
                    SUM(CASE WHEN IFNULL(res.probe_1, 0) > 0 THEN 1 ELSE 1 END) AS number_of_valid_submissions_probe_spc,
                    SUM(CASE WHEN IFNULL(res.probe_2, 0) > 0 THEN 1 ELSE 1 END) AS number_of_valid_submissions_probe_is1081_is6110,
                    SUM(CASE WHEN IFNULL(res.probe_3, 0) > 0 THEN 1 ELSE 1 END) AS number_of_valid_submissions_probe_rpo_b1,
                    SUM(CASE WHEN IFNULL(res.probe_4, 0) > 0 THEN 1 ELSE 1 END) AS number_of_valid_submissions_probe_rpo_b2,
                    SUM(CASE WHEN IFNULL(res.probe_5, 0) > 0 THEN 1 ELSE 1 END) AS number_of_valid_submissions_probe_rpo_b3,
                    SUM(CASE WHEN IFNULL(res.probe_6, 0) > 0 THEN 1 ELSE 1 END) AS number_of_valid_submissions_probe_rpo_b4,
                    SUM(IFNULL(res.probe_1, 0)) AS sum_probe_spc_ct,
                    SUM(IFNULL(res.probe_2, 0)) AS sum_probe_is1081_is6110_ct,
                    SUM(IFNULL(res.probe_3, 0)) AS sum_probe_rpo_b1_ct,
                    SUM(IFNULL(res.probe_4, 0)) AS sum_probe_rpo_b2_ct,
                    SUM(IFNULL(res.probe_5, 0)) AS sum_probe_rpo_b3_ct,
                    SUM(IFNULL(res.probe_6, 0)) AS sum_probe_rpo_b4_ct,
                    IFNULL(ref.ultra_probe_spc, 0) AS expected_probe_spc_ct,
                    IFNULL(ref.ultra_probe_is1081_is6110, 0) AS expected_probe_is1081_is6110_ct,
                    IFNULL(ref.ultra_probe_rpo_b1, 0) AS expected_probe_rpo_b1_ct,
                    IFNULL(ref.ultra_probe_rpo_b2, 0) AS expected_probe_rpo_b2_ct,
                    IFNULL(ref.ultra_probe_rpo_b3, 0) AS expected_probe_rpo_b3_ct,
                    IFNULL(ref.ultra_probe_rpo_b4, 0) AS expected_probe_rpo_b4_ct
                FROM reference_result_tb AS ref
                JOIN shipment AS s ON s.shipment_id = ref.shipment_id
                LEFT JOIN shipment_participant_map AS spm ON spm.shipment_id = s.shipment_id
                                                            AND IFNULL(spm.is_pt_test_not_performed, 'no') = 'no'
                                                            AND SUBSTRING(spm.evaluation_status, 3, 1) = '1'
                                                            AND SUBSTRING(spm.evaluation_status, 4, 1) = '1'
                                                            AND IFNULL(spm.is_excluded, 'no') = 'no'
                LEFT JOIN r_tb_assay AS a ON a.id = JSON_UNQUOTE(JSON_EXTRACT(spm.attributes, \"$.assay\"))
                LEFT JOIN response_result_tb AS res ON res.shipment_map_id = spm.map_id
                                                    AND ref.sample_id = res.sample_id
                                                    AND IFNULL(res.calculated_score, 'pass') = 'pass'
                WHERE ref.shipment_id = ?
                AND a.name = ?
                GROUP BY ref.sample_id) AS stability_mtb_ultra
            ORDER BY stability_mtb_ultra.sample_id;", array($params['shipmentId'], $mtbUltraAssayName))
                    ->fetchAll();

                $mtbUltraStabilitySheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, "Xpert MTB Ultra Stability");
                $excel->addSheet($mtbUltraStabilitySheet, $sheetIndex);
                $sheetIndex++;
                $mtbUltraStabilitySheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode("MTB Ultra Panel Stability for " . $mtbUltraSubmissions[0]["shipment_code"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraStabilitySheet->getStyleByColumnAndRow(0, 1)->applyFromArray($sheetHeaderStyle);
                $mtbUltraStabilitySheet->getRowDimension(1)->setRowHeight(25);
                $rowIndex = 3;
                $columnIndex = 0;
                $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Sample", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("# Valid Submissions", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe SPC", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe IS1081-IS6110", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe rpoB1", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe rpoB2", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe rpoB3", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe rpoB4", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $rowIndex++;
                foreach ($mtbUltraStability as $mtbUltraStabilitySample) {
                    try {
                        $columnIndex = 0;
                        $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraStabilitySample["sample_label"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($sampleLabelStyle);
                        $columnIndex++;
                        $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraStabilitySample["number_of_valid_submissions"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($sampleLabelStyle);
                        $columnIndex++;
                        $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Mean", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $columnIndex++;
                        $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraStabilitySample["mean_probe_spc_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        if ($mtbUltraStabilitySample["mean_probe_spc_ct"] - $mtbUltraStabilitySample["expected_probe_spc_ct"] > $nonConcordanceThreshold) {
                            $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                        }
                        $columnIndex++;
                        $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraStabilitySample["mean_probe_is1081_is6110_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        if ($mtbUltraStabilitySample["mean_probe_is1081_is6110_ct"] - $mtbUltraStabilitySample["expected_probe_is1081_is6110_ct"] > $nonConcordanceThreshold) {
                            $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                        }
                        $columnIndex++;
                        $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraStabilitySample["mean_probe_rpo_b1_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        if ($mtbUltraStabilitySample["mean_probe_rpo_b1_ct"] - $mtbUltraStabilitySample["expected_probe_rpo_b1_ct"] > $nonConcordanceThreshold) {
                            $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                        }
                        $columnIndex++;
                        $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraStabilitySample["mean_probe_rpo_b2_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        if ($mtbUltraStabilitySample["mean_probe_rpo_b2_ct"] - $mtbUltraStabilitySample["expected_probe_rpo_b2_ct"] > $nonConcordanceThreshold) {
                            $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                        }
                        $columnIndex++;
                        $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraStabilitySample["mean_probe_rpo_b3_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        if ($mtbUltraStabilitySample["mean_probe_rpo_b3_ct"] - $mtbUltraStabilitySample["expected_probe_rpo_b3_ct"] > $nonConcordanceThreshold) {
                            $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                        }
                        $columnIndex++;
                        $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraStabilitySample["mean_probe_rpo_b4_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        if ($mtbUltraStabilitySample["mean_probe_rpo_b4_ct"] - $mtbUltraStabilitySample["expected_probe_rpo_b4_ct"] > $nonConcordanceThreshold) {
                            $mtbUltraStabilitySheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                        }
                        $columnIndex = 2;
                        $rowIndex++;
                        $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Expected", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $columnIndex++;
                        $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraStabilitySample["expected_probe_spc_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $columnIndex++;
                        $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraStabilitySample["expected_probe_is1081_is6110_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $columnIndex++;
                        $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraStabilitySample["expected_probe_rpo_b1_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $columnIndex++;
                        $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraStabilitySample["expected_probe_rpo_b2_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $columnIndex++;
                        $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraStabilitySample["expected_probe_rpo_b3_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $columnIndex++;
                        $mtbUltraStabilitySheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraStabilitySample["expected_probe_rpo_b4_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $rowIndex++;
                    } catch (Exception $e) {
                        error_log($e->getMessage(), 0);
                        error_log($e->getTraceAsString(), 0);
                    }
                }
                $rowIndex = 4;
                foreach ($mtbUltraStability as $mtbUltraStabilitySample) {
                    $mtbUltraStabilitySheet->mergeCells("A" . ($rowIndex) . ":A" . ($rowIndex + 1));
                    $mtbUltraStabilitySheet->mergeCells("B" . ($rowIndex) . ":B" . ($rowIndex + 1));
                    $rowIndex++;
                    $rowIndex++;
                }
                $rowIndex++;
                $mtbUltraStabilitySheet->getCellByColumnAndRow(3, $rowIndex)->setValueExplicit(html_entity_decode("Values highlighted in red indicate mean Ct values which are above ".$nonConcordanceThreshold." cycles from the expected value", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraStabilitySheet->getStyleByColumnAndRow(3, $rowIndex)->applyFromArray($nonConcordanceStyle);
                $mtbUltraStabilitySheet->mergeCells("D" . ($rowIndex) . ":I" . ($rowIndex));

                $mtbUltraStabilitySheet->getDefaultRowDimension()->setRowHeight(15);
                foreach (range('A', 'Z') as $columnID) {
                    $mtbUltraStabilitySheet->getColumnDimension($columnID)->setAutoSize(true);
                }

                $mtbUltraConcordanceSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, "Xpert MTB Ultra Concordance");
                $excel->addSheet($mtbUltraConcordanceSheet, $sheetIndex);
                $sheetIndex++;

                $mtbUltraConcordanceSheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode("MTB Ultra Panel Concordance for " . $mtbUltraSubmissions[0]["shipment_code"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow(0, 1)->applyFromArray($sheetHeaderStyle);
                $mtbUltraConcordanceSheet->getRowDimension(1)->setRowHeight(25);
                $rowIndex = 3;
                $columnIndex = 0;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Expected Values", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $mtbUltraConcordanceSheet->mergeCells("A" . ($rowIndex) . ":C" . ($rowIndex));
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Concordance", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe SPC", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe IS1081-IS6110", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe rpoB1", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe rpoB2", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe rpoB3", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe rpoB3", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);

                $mtbUltraConcordance = [];
                foreach ($mtbUltraSubmissions as $mtbUltraSubmission) {
                    if (!isset($mtbUltraConcordance[$mtbUltraSubmission['sample_label']])) {
                        $mtbUltraConcordance[$mtbUltraSubmission['sample_label']] = array(
                            "withinRange" => 0,
                            "outsideOfRange" => 0,
                            "totalValidSubmissions" => 0,
                        );
                    }
                    if ($mtbUltraSubmission["calculated_score"] == 'pass' &&
                        ($mtbUltraSubmission["probe_spc_ct"] > 0 ||
                            $mtbUltraSubmission["probe_is1081_is6110_ct"] > 0 ||
                            $mtbUltraSubmission["probe_rpo_b1_ct"] > 0 ||
                            $mtbUltraSubmission["probe_rpo_b2_ct"] > 0 ||
                            $mtbUltraSubmission["probe_rpo_b3_ct"] > 0 ||
                            $mtbUltraSubmission["probe_rpo_b4_ct"] > 0)
                    ) {
                        $mtbUltraConcordance[$mtbUltraSubmission['sample_label']]["totalValidSubmissions"]++;
                        if (
                            $mtbUltraSubmission["probe_spc_ct"] - $mtbUltraSubmission["expected_probe_spc_ct"] > $nonConcordanceThreshold ||
                            $mtbUltraSubmission["probe_is1081_is6110_ct"] - $mtbUltraSubmission["expected_probe_is1081_is6110_ct"] > $nonConcordanceThreshold ||
                            $mtbUltraSubmission["probe_rpo_b1_ct"] - $mtbUltraSubmission["expected_probe_rpo_b1_ct"] > $nonConcordanceThreshold ||
                            $mtbUltraSubmission["probe_rpo_b2_ct"] - $mtbUltraSubmission["expected_probe_rpo_b2_ct"] > $nonConcordanceThreshold ||
                            $mtbUltraSubmission["probe_rpo_b3_ct"] - $mtbUltraSubmission["expected_probe_rpo_b3_ct"] > $nonConcordanceThreshold ||
                            $mtbUltraSubmission["probe_rpo_b4_ct"] - $mtbUltraSubmission["expected_probe_rpo_b4_ct"] > $nonConcordanceThreshold
                        ) {
                            $mtbUltraConcordance[$mtbUltraSubmission['sample_label']]["outsideOfRange"]++;
                        } else {
                            $mtbUltraConcordance[$mtbUltraSubmission['sample_label']]["withinRange"]++;
                        }
                    }
                }

                $rowIndex = 4;
                $columnIndex = 0;
                $currentParticipantId = $mtbUltraSubmissions[0]["unique_identifier"];
                $recordIndex = 0;
                while ($mtbUltraSubmissions[$recordIndex]["unique_identifier"] == $currentParticipantId) {
                    $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmissions[$recordIndex]["sample_label"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $columnIndex++;
                    $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmissions[$recordIndex]["sample_label"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $columnIndex++;
                    $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmissions[$recordIndex]["sample_label"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $mtbUltraConcordanceSheet->mergeCells("A" . ($rowIndex) . ":C" . ($rowIndex));
                    $columnIndex++;
                    $concordanceTotals = $mtbUltraConcordance[$mtbUltraSubmissions[$recordIndex]["sample_label"]];
                    $sampleConcordance = 0;
                    if ($concordanceTotals["totalValidSubmissions"] > 0) {
                        $sampleConcordance = $concordanceTotals["withinRange"] / $concordanceTotals["totalValidSubmissions"];
                    }
                    $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($sampleConcordance, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    if ($sampleConcordance < $expectedConcordance) {
                        $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                    }
                    $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->getNumberFormat()->applyFromArray(
                        array(
                            'code' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00
                        )
                    );
                    $columnIndex++;
                    $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmissions[$recordIndex]["expected_probe_spc_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $columnIndex++;
                    $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmissions[$recordIndex]["expected_probe_is1081_is6110_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $columnIndex++;
                    $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmissions[$recordIndex]["expected_probe_rpo_b1_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $columnIndex++;
                    $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmissions[$recordIndex]["expected_probe_rpo_b2_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $columnIndex++;
                    $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmissions[$recordIndex]["expected_probe_rpo_b3_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $columnIndex++;
                    $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmissions[$recordIndex]["expected_probe_rpo_b4_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $rowIndex++;
                    $columnIndex = 0;
                    $recordIndex++;
                }

                $rowIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow(2, $rowIndex)->setValueExplicit(html_entity_decode("Values highlighted in red indicate that the percentage of valid results, where a Ct value for a probe was ".$nonConcordanceThreshold." cycles higher than the expected value, is outside of the acceptable range of ".($expectedConcordance * 100)."%", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow(2, $rowIndex)->applyFromArray($nonConcordanceStyle);
                $mtbUltraConcordanceSheet->mergeCells("C" . ($rowIndex) . ":J" . ($rowIndex));

                $rowIndex++;
                $rowIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("PT ID", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Participant", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Country", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Sample", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("# Days Tested After Shipment", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe SPC", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe IS1081-IS6110", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe rpoB1", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe rpoB2", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe rpoB3", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $columnIndex++;
                $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Ct for Probe rpoB4", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($columnHeaderStyle);
                $rowIndex++;

                $currentParticipantId = "";
                foreach ($mtbUltraSubmissions as $mtbUltraSubmission) {
                    try {
                        $columnIndex = 0;
                        if ($currentParticipantId != $mtbUltraSubmission["unique_identifier"]) {
                            $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmission["unique_identifier"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($sampleLabelStyle);
                            $columnIndex++;
                            $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmission["participant_name"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($sampleLabelStyle);
                            $columnIndex++;
                            $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmission["country"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($sampleLabelStyle);
                            $columnIndex++;
                            $currentParticipantId = $mtbUltraSubmission["unique_identifier"];
                        } else {
                            $columnIndex++;
                            $columnIndex++;
                            $columnIndex++;
                        }
                        $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmission["sample_label"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $columnIndex++;
                        $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmission["days_between_shipment_and_test"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $columnIndex++;
                        $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmission["probe_spc_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        if ($mtbUltraSubmission["probe_spc_ct"] - $mtbUltraSubmission["expected_probe_spc_ct"] > $nonConcordanceThreshold) {
                            $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                        }
                        $columnIndex++;
                        $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmission["probe_is1081_is6110_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        if ($mtbUltraSubmission["probe_is1081_is6110_ct"] - $mtbUltraSubmission["expected_probe_is1081_is6110_ct"] > $nonConcordanceThreshold) {
                            $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                        }
                        $columnIndex++;
                        $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmission["probe_rpo_b1_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        if ($mtbUltraSubmission["probe_rpo_b1_ct"] - $mtbUltraSubmission["expected_probe_rpo_b1_ct"] > $nonConcordanceThreshold) {
                            $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                        }
                        $columnIndex++;
                        $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmission["probe_rpo_b2_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        if ($mtbUltraSubmission["probe_rpo_b2_ct"] - $mtbUltraSubmission["expected_probe_rpo_b2_ct"] > $nonConcordanceThreshold) {
                            $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                        }
                        $columnIndex++;
                        $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmission["probe_rpo_b3_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        if ($mtbUltraSubmission["probe_rpo_b3_ct"] - $mtbUltraSubmission["expected_probe_rpo_b3_ct"] > $nonConcordanceThreshold) {
                            $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                        }
                        $columnIndex++;
                        $mtbUltraConcordanceSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($mtbUltraSubmission["probe_rpo_b4_ct"], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        if ($mtbUltraSubmission["probe_rpo_b4_ct"] - $mtbUltraSubmission["expected_probe_rpo_b4_ct"] > $nonConcordanceThreshold) {
                            $mtbUltraConcordanceSheet->getStyleByColumnAndRow($columnIndex, $rowIndex)->applyFromArray($nonConcordanceStyle);
                        }
                        $rowIndex++;
                    } catch (Exception $e) {
                        error_log($e->getMessage(), 0);
                        error_log($e->getTraceAsString(), 0);
                    }
                }

                foreach (range('A', 'Z') as $columnID) {
                    $mtbUltraConcordanceSheet->getColumnDimension($columnID)->setAutoSize(true);
                }
            }
        }

        $excel->setActiveSheetIndex(0);
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Excel5');
        if (!file_exists(UPLOAD_PATH  . DIRECTORY_SEPARATOR . "generated-reports")) {
            mkdir(UPLOAD_PATH  . DIRECTORY_SEPARATOR . "generated-reports", 0777, true);
        }
        $fileSafeShipmentCode = str_replace( ' ', '-', str_replace(array_merge(
            array_map('chr', range(0, 31)),
            array('<', '>', ':', '"', '/', '\\', '|', '?', '*')
        ), '', $shipmentResult['shipment_code']));
        $filename = $fileSafeShipmentCode . '-xtpt-indicators' . '.xls';
        $writer->save(UPLOAD_PATH  . DIRECTORY_SEPARATOR . "generated-reports" . DIRECTORY_SEPARATOR . $filename);

        return array(
            "report-name" => $filename
        );
    }

    private function getTbAllSitesResultsSheet($db, $shipmentId, $excel, $sheetIndex) {
        $borderStyle = array(
            'font' => array(
                'bold' => true,
                'size'  => 12,
            ),
            'alignment' => array(
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ),
            )
        );


        $queryString = file_get_contents(sprintf('%s/Reports/getTbAllSitesResultsSheet.sql', __DIR__));

        $authNameSpace = new Zend_Session_Namespace('administrators');
        if ($authNameSpace->is_ptcc_coordinator) {
            // Strip out non-PTCC fields
            $pattern = '/--\s[-]+ START NON-PTCC COORDINATOR FIELDS [-]+(?s).*--\s[-]+ END NON-PTCC COORDINATOR FIELDS [-]+/';
            $queryString = preg_replace($pattern, '', $queryString);
            $query = $db->query($queryString, [$shipmentId, implode(',', $authNameSpace->countries)]);
        } else {
            // Strip out non-PTCC filters
            $pattern = '/--\s[-]+ START PTCC COORDINATOR FILTER [-]+(?s).*--\s[-]+ END PTCC COORDINATOR FILTER [-]+/';
            $queryString = preg_replace($pattern, '', $queryString);
            $query = $db->query($queryString, [$shipmentId]);
        }

        $results = $query->fetchAll();

        $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, "All Sites' Results");
        $excel->addSheet($sheet, $sheetIndex);
        $columnIndex = 0;
        if (count($results) > 0 && count($results[0]) > 0) {
            foreach(array_diff_key($results[0], array_flip($columnExcludes)) as $columnName => $value) {
                $sheet->getCellByColumnAndRow($columnIndex, 1)->setValueExplicit(html_entity_decode($columnName, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getStyleByColumnAndRow($columnIndex, 1)->applyFromArray($borderStyle);
                $columnIndex++;
            }
        }

        if (is_array($csSurvey) && array_key_exists('questions', $csSurvey)) {
            foreach ($csSurvey['questions'] as $ix => $node) {
                $sheet->getCellByColumnAndRow($columnIndex, 1)->setValueExplicit(html_entity_decode($node['text'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getStyleByColumnAndRow($columnIndex, 1)->applyFromArray($borderStyle);
                $columnIndex++;
            }
        }

        $sheet->getDefaultRowDimension()->setRowHeight(15);

        $rowNumber = 1; // $row 0 is already the column headings

        foreach(range('A','Z') as $columnID) {
            $sheet->getColumnDimension($columnID)
                ->setAutoSize(true);
        }
        return $sheet;
    }
}
