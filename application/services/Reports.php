<?php

include_once "PHPExcel.php";

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
            ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('report_generated', 'participant_count' => new Zend_Db_Expr('count("participant_id")'), 'reported_count' => new Zend_Db_Expr("SUM(shipment_test_date not like  '0000-00-00' OR is_pt_test_not_performed ='yes')"), 'reported_percentage' => new Zend_Db_Expr("ROUND((SUM(shipment_test_date not like  '0000-00-00' OR is_pt_test_not_performed ='yes')/count('participant_id'))*100,2)"), 'number_passed' => new Zend_Db_Expr("SUM(final_result = 1)")))
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array())
            //->joinLeft(array('pmm'=>'participant_manager_map'),'pmm.participant_id=p.participant_id')
            ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id', array())
            ->group(array('s.shipment_id'));



        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $sQuery = $sQuery->where("s.shipment_date >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("s.shipment_date <= ?", $parameters['endDate']);
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

        //echo($sQuery);

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


        $shipmentDb = new Application_Model_DbTable_Shipments();
        //$aColumns = array('distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')",
        //'s.shipment_code' ,'sl.scheme_name' ,'s.number_of_samples' ,
        //'sp.participant_count','sp.reported_count','sp.number_passed','s.status');
        foreach ($rResult as $aRow) {
            $download = ' No Download Available ';
            if (isset($aRow['status']) && $aRow['status'] == 'finalized') {
                if (file_exists(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . "-summary.pdf")) {
                    $filePath = base64_encode(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . "reports" . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . "-summary.pdf");
                    $download = '<a href="/d/' . $filePath . '" class=\'btn btn-info btn-xs\'><i class=\'icon-download\'></i> Summary</a>';
                }
            }
            $shipmentResults = $shipmentDb->getPendingShipmentsByDistribution($aRow['distribution_id']);
            $responsePercentage = ($aRow['reported_percentage'] != "") ? $aRow['reported_percentage'] : "0";
            $row = array();
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
            $row[] = $download;


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
        if (isset($params['scheme']) && $params['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $params['scheme']);
        }

        //die($sQuery);
        if (isset($params['startDate']) && $params['startDate'] != "" && isset($params['endDate']) && $params['endDate'] != "") {
            $sQuery = $sQuery->where("s.shipment_date >= ?", $params['startDate']);
            $sQuery = $sQuery->where("s.shipment_date <= ?", $params['endDate']);
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
            $aColumns = array('s.shipment_code', 'sl.scheme_name', 'network_name', 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')");
        } else if (isset($parameters['reportType']) && $parameters['reportType'] == "affiliation") {
            $aColumns = array('s.shipment_code', 'sl.scheme_name', 'affiliate', 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')");
        } else if (isset($parameters['reportType']) && $parameters['reportType'] == "region") {
            $aColumns = array('s.shipment_code', 'sl.scheme_name', 'region', 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')");
        } else if (isset($parameters['reportType']) && $parameters['reportType'] == "enrolled-programs") {
            $aColumns = array('s.shipment_code', 'sl.scheme_name', 'enrolled_programs', 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')");
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
                ->joinLeft(array('p' => 'participant'), 'p.network_tier=n.network_id', array())
                ->joinLeft(array('shp' => 'shipment_participant_map'), 'shp.participant_id=p.participant_id', array())
                ->joinLeft(array('s' => 'shipment'), 's.shipment_id=shp.shipment_id', array('shipment_code', 'lastdate_response'))
                ->joinLeft(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_name'))
                ->joinLeft(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('distribution_code', 'distribution_date'))
                ->group('n.network_id')->group('s.shipment_id')/* ->where("p.status = 'active'") */;
        } else if (isset($parameters['reportType']) && $parameters['reportType'] == "affiliation") {
            $sQuery = $dbAdapter->select()->from(array('pa' => 'r_participant_affiliates'))
                ->joinLeft(array('p' => 'participant'), 'p.affiliation=pa.affiliate', array())
                ->joinLeft(array('shp' => 'shipment_participant_map'), 'shp.participant_id=p.participant_id', array())
                ->joinLeft(array('s' => 'shipment'), 's.shipment_id=shp.shipment_id', array('shipment_code', 'lastdate_response'))
                ->joinLeft(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_name'))
                ->joinLeft(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('distribution_code', 'distribution_date'))
                ->group('pa.aff_id')->group('s.shipment_id')/* ->where("p.status = 'active'") */;
        } else if (isset($parameters['reportType']) && $parameters['reportType'] == "region") {
            $sQuery = $dbAdapter->select()->from(array('p' => 'participant'), array('p.region'))
                ->joinLeft(array('shp' => 'shipment_participant_map'), 'shp.participant_id=p.participant_id', array())
                ->joinLeft(array('s' => 'shipment'), 's.shipment_id=shp.shipment_id', array('shipment_code', 'lastdate_response'))
                ->joinLeft(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_name'))
                ->joinLeft(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('distribution_code', 'distribution_date'))
                ->group('p.region')->where("p.region IS NOT NULL")->where("p.region != ''")->group('s.shipment_id')/* ->where("p.status = 'active'") */;
        } else if (isset($parameters['reportType']) && $parameters['reportType'] == "enrolled-programs") {


            $sQuery = $dbAdapter->select()->from(array('p' => 'participant'), array())
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


        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $sQuery = $sQuery->where("s.shipment_date >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("s.shipment_date <= ?", $parameters['endDate']);
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
            $row = array();
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



        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
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
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
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


            $row = array();
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
                    "total_responses" => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
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
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
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
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
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
            $row = array();
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
            $sQuery = $sQuery->where("s.shipment_date >= ?", $params['startDate']);
            $sQuery = $sQuery->where("s.shipment_date <= ?", $params['endDate']);
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
            $sQuery = $sQuery->where("s.shipment_date >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("s.shipment_date <= ?", $parameters['endDate']);
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
            $row = array();
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

        $responseResult = array();
        $responseDate = array();
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
            return $this->generateDtsRapidHivExcelReport($shipmentId);
        } else if ($schemeType == 'vl') {
            return $this->generateDtsViralLoadExcelReport($shipmentId);
        } else if ($schemeType == 'eid') {
            return $this->generateDbsEidExcelReport($shipmentId);
        } else if ($schemeType == 'recency') {
            return $this->generateRecencyExcelReport($shipmentId);
        } else if ($schemeType == 'covid19') {
            return $this->generateCovid19ExcelReport($shipmentId);
        } else {
            return false;
        }
    }


    public function generateDtsRapidHivExcelReport($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, APPLICATION_ENV);

        $excel = new PHPExcel();
        //$sheet = $excel->getActiveSheet();


        $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
        $cacheSettings = array('memoryCacheSize' => '80MB');

        $styleArray = array(
            'font' => array(
                'bold' => true,
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                ),
            )
        );

        $borderStyle = array(
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                ),
            )
        );

        $query = $db->select()->from('shipment', array('shipment_id', 'shipment_code', 'scheme_type', 'number_of_samples', 'number_of_controls'))
            ->where("shipment_id = ?", $shipmentId);
        $result = $db->fetchRow($query);

        if ($result['scheme_type'] == 'dts') {

            $refQuery = $db->select()->from(array('refRes' => 'reference_result_dts'), array('refRes.sample_label', 'sample_id', 'refRes.sample_score'))
                ->joinLeft(array('r' => 'r_possibleresult'), 'r.id=refRes.reference_result', array('referenceResult' => 'r.response'))
                ->where("refRes.shipment_id = ?", $shipmentId);
            $refResult = $db->fetchAll($refQuery);
            if (count($refResult) > 0) {
                foreach ($refResult as $key => $refRes) {
                    $refDtsQuery = $db->select()->from(array('refDts' => 'reference_dts_rapid_hiv'), array('refDts.lot_no', 'refDts.expiry_date', 'refDts.result'))
                        ->joinLeft(array('r' => 'r_possibleresult'), 'r.id=refDts.result', array('referenceKitResult' => 'r.response'))
                        ->joinLeft(array('tk' => 'r_testkitname_dts'), 'tk.TestKitName_ID=refDts.testkit', array('testKitName' => 'tk.TestKit_Name'))
                        ->where("refDts.shipment_id = ?", $shipmentId)
                        ->where("refDts.sample_id = ?", $refRes['sample_id']);
                    $refResult[$key]['kitReference'] = $db->fetchAll($refDtsQuery);
                }
            }
        }

        $firstSheet = new PHPExcel_Worksheet($excel, 'Instructions');
        $excel->addSheet($firstSheet, 0);
        $firstSheet->setTitle('Instructions');
        //$firstSheet->getDefaultColumnDimension()->setWidth(44);
        //$firstSheet->getDefaultRowDimension()->setRowHeight(45);
        $firstSheetHeading = array('Tab Name', 'Description');
        $firstSheetColNo = 0;
        $firstSheetRow = 1;

        $firstSheetStyle = array(
            'alignment' => array(
                //'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                ),
            )
        );

        foreach ($firstSheetHeading as $value) {
            $firstSheet->getCellByColumnAndRow($firstSheetColNo, $firstSheetRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getStyleByColumnAndRow($firstSheetColNo, $firstSheetRow)->getFont()->setBold(true);
            $cellName = $firstSheet->getCellByColumnAndRow($firstSheetColNo, $firstSheetRow)->getColumn();
            $firstSheet->getStyle($cellName . $firstSheetRow)->applyFromArray($firstSheetStyle);
            $firstSheetColNo++;
        }

        $firstSheet->getCellByColumnAndRow(0, 2)->setValueExplicit(html_entity_decode("Participant List", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode("Includes the following: region, department etc", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $firstSheet->getDefaultRowDimension()->setRowHeight(10);
        $firstSheet->getColumnDimensionByColumn(0)->setWidth(20);
        $firstSheet->getDefaultRowDimension(1)->setRowHeight(70);
        $firstSheet->getColumnDimensionByColumn(1)->setWidth(100);

        $firstSheet->getCellByColumnAndRow(0, 3)->setValueExplicit(html_entity_decode("Results Reported", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode("This tab should include no commentary from PT Admin staff.  All fields should only reflect results or comments reported on the results form.  If no report was submitted, highlight site data cells in red.  Explanation of missing results should only be comments that the site made, not PT staff.  All dates should be formatted as DD/MM/YY.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(0, 4)->setValueExplicit(html_entity_decode("Panel Score", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(1, 4)->setValueExplicit(html_entity_decode("Columns are populated automatically.  Panel score calculated based on Panel created by PT Admin.  If a panel member must be omitted from the calculation (ie, loss of sample, etc) you must revise the equation manually by changing the number 6 to 5,4,etc. accordingly.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(0, 5)->setValueExplicit(html_entity_decode("Documentation Score", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(1, 5)->setValueExplicit(html_entity_decode("Columns are populated automatically. ", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(0, 6)->setValueExplicit(html_entity_decode("Total Score", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(1, 6)->setValueExplicit(html_entity_decode("Columns are populated automatically based on the evaluation algorithm.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        // $firstSheet->getCellByColumnAndRow(0, 7)->setValueExplicit(html_entity_decode("Follow-up Calls", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        // $firstSheet->getCellByColumnAndRow(1, 7)->setValueExplicit(html_entity_decode("Final comments or outcomes should be updated continuously with receipt dates included.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        // $firstSheet->getCellByColumnAndRow(0, 8)->setValueExplicit(html_entity_decode("Dropdown Lists", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        // $firstSheet->getCellByColumnAndRow(1, 8)->setValueExplicit(html_entity_decode("This tab contains all of the dropdown lists included in the rest of the database, any modifications should be performed with caution.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        // $firstSheet->getCellByColumnAndRow(0, 9)->setValueExplicit(html_entity_decode("Decision Tree", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        // $firstSheet->getCellByColumnAndRow(1, 9)->setValueExplicit(html_entity_decode("Lists all of the appropriate corrective actions and scoring critieria.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        // $firstSheet->getCellByColumnAndRow(0, 10)->setValueExplicit(html_entity_decode("Feedback Report", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        // $firstSheet->getCellByColumnAndRow(1, 10)->setValueExplicit(html_entity_decode("This tab is populated automatically and used to export data into the Feedback Reports generated in MS Word.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        // $firstSheet->getCellByColumnAndRow(0, 11)->setValueExplicit(html_entity_decode("Comments", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        // $firstSheet->getCellByColumnAndRow(1, 11)->setValueExplicit(html_entity_decode("This tab lists all of the more detailed comments that will be given to the sites during site visits and phone calls.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);


        for ($counter = 1; $counter <= 11; $counter++) {
            $firstSheet->getStyleByColumnAndRow(1, $counter)->getAlignment()->setWrapText(true);
            $firstSheet->getStyle("A$counter")->applyFromArray($firstSheetStyle);
            $firstSheet->getStyle("B$counter")->applyFromArray($firstSheetStyle);
        }
        //<------------ Participant List Details Start -----

        $headings = array('Participant Code', 'Participant Name',  'Institute Name', 'Department', 'Address', 'Region', 'City', 'Facility Telephone', 'Email');

        $sheet = new PHPExcel_Worksheet($excel, 'Participant List');
        $excel->addSheet($sheet, 1);
        $sheet->setTitle('Participant List');

        $sql = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.number_of_samples', 's.number_of_controls'))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('sp.map_id', 'sp.participant_id', 'sp.attributes', 'sp.shipment_test_date', 'sp.shipment_receipt_date', 'sp.shipment_test_report_date', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_score', 'sp.documentation_score', 'sp.final_result', 'sp.is_excluded', 'sp.failure_reason', 'sp.user_comment'))
            ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.lab_name', 'p.region', 'p.first_name', 'p.last_name', 'p.address', 'p.city', 'p.mobile', 'p.email', 'p.status'))
            ->joinLeft(array('pmp' => 'participant_manager_map'), 'pmp.participant_id=p.participant_id', array('pmp.dm_id'))
            ->joinLeft(array('dm' => 'data_manager'), 'dm.dm_id=pmp.dm_id', array('dm.institute', 'dataManagerFirstName' => 'dm.first_name', 'dataManagerLastName' => 'dm.last_name'))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country', array('iso_name'))
            ->joinLeft(array('st' => 'r_site_type'), 'st.r_stid=p.site_type', array('st.site_type'))
            ->joinLeft(array('en' => 'enrollments'), 'en.participant_id=p.participant_id', array('en.enrolled_on'))
            ->where("s.shipment_id = ?", $shipmentId)
            ->group(array('sp.map_id'));
        //echo $sql;die;
        $shipmentResult = $db->fetchAll($sql);
        //die;
        $colNo = 0;
        $currentRow = 1;
        $type = PHPExcel_Cell_DataType::TYPE_STRING;
        //$sheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode("Participant List", ENT_QUOTES, 'UTF-8'), $type);
        //$sheet->getStyleByColumnAndRow(0,1)->getFont()->setBold(true);
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getDefaultRowDimension()->setRowHeight(18);

        foreach ($headings as $field => $value) {
            $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow($colNo, $currentRow)->getFont()->setBold(true);
            $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
            $colNo++;
        }

        if (isset($shipmentResult) && count($shipmentResult) > 0) {
            $currentRow += 1;
            foreach ($shipmentResult as $key => $aRow) {
                if ($result['scheme_type'] == 'dts') {
                    $resQuery = $db->select()->from(array('rrdts' => 'response_result_dts'))
                        ->joinLeft(array('tk1' => 'r_testkitname_dts'), 'tk1.TestKitName_ID=rrdts.test_kit_name_1', array('testKitName1' => 'tk1.TestKit_Name'))
                        ->joinLeft(array('tk2' => 'r_testkitname_dts'), 'tk2.TestKitName_ID=rrdts.test_kit_name_2', array('testKitName2' => 'tk2.TestKit_Name'))
                        ->joinLeft(array('tk3' => 'r_testkitname_dts'), 'tk3.TestKitName_ID=rrdts.test_kit_name_3', array('testKitName3' => 'tk3.TestKit_Name'))
                        ->joinLeft(array('r' => 'r_possibleresult'), 'r.id=rrdts.test_result_1', array('testResult1' => 'r.response'))
                        ->joinLeft(array('rp' => 'r_possibleresult'), 'rp.id=rrdts.test_result_2', array('testResult2' => 'rp.response'))
                        ->joinLeft(array('rpr' => 'r_possibleresult'), 'rpr.id=rrdts.test_result_3', array('testResult3' => 'rpr.response'))
                        ->joinLeft(array('rpr1' => 'r_possibleresult'), 'rpr1.id=rrdts.repeat_test_result_1', array('repeatTestResult1' => 'rpr1.response'))
                        ->joinLeft(array('rpr2' => 'r_possibleresult'), 'rpr2.id=rrdts.repeat_test_result_2', array('repeatTestResult2' => 'rpr2.response'))
                        ->joinLeft(array('rpr3' => 'r_possibleresult'), 'rpr3.id=rrdts.repeat_test_result_3', array('repeatTestResult3' => 'rpr3.response'))
                        ->joinLeft(array('fr' => 'r_possibleresult'), 'fr.id=rrdts.reported_result', array('finalResult' => 'fr.response'))
                        ->where("rrdts.shipment_map_id = ?", $aRow['map_id']);
                    $shipmentResult[$key]['response'] = $db->fetchAll($resQuery);
                }


                $sheet->getCellByColumnAndRow(0, $currentRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(1, $currentRow)->setValueExplicit($aRow['first_name'] . $aRow['last_name'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(2, $currentRow)->setValueExplicit($aRow['institute_name'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(3, $currentRow)->setValueExplicit($aRow['department_name'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(4, $currentRow)->setValueExplicit($aRow['address'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(5, $currentRow)->setValueExplicit($aRow['city'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(6, $currentRow)->setValueExplicit($aRow['region'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(7, $currentRow)->setValueExplicit($aRow['mobile'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(8, $currentRow)->setValueExplicit(strtolower($aRow['email']), PHPExcel_Cell_DataType::TYPE_STRING);

                for ($i = 0; $i <= 8; $i++) {
                    $cellName = $sheet->getCellByColumnAndRow($i, $currentRow)->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
                }

                $currentRow++;
                $shipmentCode = $aRow['shipment_code'];
            }
        }

        //------------- Participant List Details End ------>
        //<-------- Second sheet start
        $reportHeadings = array('Participant Code', 'Participant Name', 'Point of Contact', 'Region', 'Shipment Receipt Date', 'Sample Rehydration Date', 'Testing Date', 'Reported On', 'Test#1 Name', 'Kit Lot #', 'Expiry Date');
        if ((isset($config->evaluation->dts->displaySampleConditionFields) && $config->evaluation->dts->displaySampleConditionFields == "yes")) {
            $reportHeadings = array('Participant Code', 'Participant Name', 'Point of Contact', 'Region', 'Shipment Receipt Date', 'Testing Date', 'Reported On', 'Condition Of PT Samples', 'Refridgerator', 'Room Temperature', 'Stop Watch', 'Test#1 Name', 'Kit Lot #', 'Expiry Date');
        }
        if ($result['scheme_type'] == 'dts') {
            $reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
            array_push($reportHeadings, 'Test#2 Name', 'Kit Lot #', 'Expiry Date');
            $reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
            if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
                array_push($reportHeadings, 'Test#3 Name', 'Kit Lot #', 'Expiry Date');
                $reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
            }
            /* Repeat test section */
            if (isset($config->evaluation->dts->allowRepeatTests) && $config->evaluation->dts->allowRepeatTests == 'yes') {
                $reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
                $reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
                if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
                    $reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
                }
            }
            // For final result
            $reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
            array_push($reportHeadings, 'Comments');
        }

        $sheet = new PHPExcel_Worksheet($excel, 'Results Reported');
        $excel->addSheet($sheet, 2);
        $sheet->setTitle('Results Reported');
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getDefaultRowDimension()->setRowHeight(18);

        $colNo = 0;
        $repeatCellNo = 0;
        $currentRow = 2;
        $n = count($reportHeadings);

        $finalResColoumn = $n - ($result['number_of_samples'] + $result['number_of_controls'] + 1);
        $c = 1;
        $repeatCell = 1;
        $endMergeCell = ($finalResColoumn + $result['number_of_samples'] + $result['number_of_controls']) - 1;

        $firstCellName = $sheet->getCellByColumnAndRow($finalResColoumn, 1)->getColumn();
        $secondCellName = $sheet->getCellByColumnAndRow($endMergeCell, 1)->getColumn();
        $sheet->mergeCells($firstCellName . "1:" . $secondCellName . "1");
        $sheet->getStyle($firstCellName . "1")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle($firstCellName . "1")->applyFromArray($borderStyle);
        $sheet->getStyle($secondCellName . "1")->applyFromArray($borderStyle);
        /* Repeat test section */
        if (isset($config->evaluation->dts->allowRepeatTests) && $config->evaluation->dts->allowRepeatTests == 'yes') {
            $repeatHeadingColumn = $n - (($result['number_of_samples'] * 3) + $result['number_of_controls'] + 1);
            if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
                $repeatHeadingColumn = $n - (($result['number_of_samples'] * 4) + $result['number_of_controls'] + 1);
            }
            $endRepeatMergeCell = ($repeatHeadingColumn + ($result['number_of_samples'] * 2) + $result['number_of_controls']) - 1;
            if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
                $endRepeatMergeCell = ($repeatHeadingColumn + ($result['number_of_samples'] * 3) + $result['number_of_controls']) - 1;
            }
            $repeatFirstCellName = $sheet->getCellByColumnAndRow($repeatHeadingColumn, 1)->getColumn();
            $repeatSecondCellName = $sheet->getCellByColumnAndRow($endRepeatMergeCell, 1)->getColumn();
            $sheet->mergeCells($repeatFirstCellName . "1:" . $repeatSecondCellName . "1");
            $sheet->getStyle($repeatFirstCellName . "1")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            $sheet->getStyle($repeatFirstCellName . "1")->applyFromArray($borderStyle);
            $sheet->getStyle($repeatSecondCellName . "1")->applyFromArray($borderStyle);
        }
        foreach ($reportHeadings as $field => $value) {

            $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow($colNo, $currentRow)->getFont()->setBold(true);
            $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);

            $cellName = $sheet->getCellByColumnAndRow($colNo, 3)->getColumn();
            $sheet->getStyle($cellName . "3")->applyFromArray($borderStyle);
            /* Repeat test section */
            if (isset($config->evaluation->dts->allowRepeatTests) && $config->evaluation->dts->allowRepeatTests == 'yes') {
                if ($repeatCellNo >= $repeatHeadingColumn) {
                    // Zend_Debug::dump($repeatCell);
                    if ($repeatCell <= ($result['number_of_samples'] + $result['number_of_controls'])) {
                        $sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode("Repeat Tests", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
                        $sheet->getStyle($cellName . $currentRow)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                    }
                    $repeatCell++;
                }
                $repeatCellNo++;
            }

            if ($colNo >= $finalResColoumn) {
                if ($c <= ($result['number_of_samples'] + $result['number_of_controls'])) {
                    $sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode("Final Results", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                    $l = $c - 1;
                    $sheet->getCellByColumnAndRow($colNo, 3)->setValueExplicit(html_entity_decode($refResult[$l]['referenceResult'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                }
                $c++;
            }
            $sheet->getStyle($cellName . '3')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFA0A0A0');
            $sheet->getStyle($cellName . '3')->getFont()->getColor()->setARGB('FFFFFF00');

            $colNo++;
        }

        $sheet->getStyle("A2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("B2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("C2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("D2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');

        //$sheet->getStyle("D2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');
        //$sheet->getStyle("E2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');
        //$sheet->getStyle("F2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');

        $cellName = $sheet->getCellByColumnAndRow($n, 3)->getColumn();
        //$sheet->getStyle('A3:'.$cellName.'3')->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#969696');
        //$sheet->getStyle('A3:'.$cellName.'3')->applyFromArray($borderStyle);
        //<-------- Sheet three heading -------
        $sheetThree = new PHPExcel_Worksheet($excel, 'Panel Score');
        $excel->addSheet($sheetThree, 3);
        $sheetThree->setTitle('Panel Score');
        $sheetThree->getDefaultColumnDimension()->setWidth(20);
        $sheetThree->getDefaultRowDimension()->setRowHeight(18);
        $panelScoreHeadings = array('Participant Code', 'Participant Name');
        $panelScoreHeadings = $this->addSampleNameInArray($shipmentId, $panelScoreHeadings);
        array_push($panelScoreHeadings, 'Test# Correct', '% Correct');
        $sheetThreeColNo = 0;
        $sheetThreeRow = 1;
        $panelScoreHeadingCount = count($panelScoreHeadings);
        $sheetThreeColor = 1 + $result['number_of_samples'] + $result['number_of_controls'];
        foreach ($panelScoreHeadings as $sheetThreeHK => $value) {
            $sheetThree->getCellByColumnAndRow($sheetThreeColNo, $sheetThreeRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheetThree->getStyleByColumnAndRow($sheetThreeColNo, $sheetThreeRow)->getFont()->setBold(true);
            $cellName = $sheetThree->getCellByColumnAndRow($sheetThreeColNo, $sheetThreeRow)->getColumn();
            $sheetThree->getStyle($cellName . $sheetThreeRow)->applyFromArray($borderStyle);

            if ($sheetThreeHK > 1 && $sheetThreeHK <= $sheetThreeColor) {
                $cellName = $sheetThree->getCellByColumnAndRow($sheetThreeColNo, $sheetThreeRow)->getColumn();
                $sheetThree->getStyle($cellName . $sheetThreeRow)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            }

            $sheetThreeColNo++;
        }
        //---------- Sheet Three heading ------->
        //<-------- Document Score Sheet Heading (Sheet Four)-------

        if ($result['scheme_type'] == 'dts') {
            $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
            $config = new Zend_Config_Ini($file, APPLICATION_ENV);
            $shipmentAttributes = json_decode($aRow['shipment_attributes'], true);
            $attributes = json_decode($aRow['attributes'], true);
            if (isset($shipmentAttributes['sampleType']) && $shipmentAttributes['sampleType'] == 'dried') {
                // for Dried Samples, we will have 2 documentation checks for rehydration - Rehydration Date and Date Diff between Rehydration and Testing
                $totalDocumentationItems = 5;
            } else {
                // for Non Dried Samples, we will NOT have rehydration documentation scores 
                // there are 2 conditions for rehydration so 5 - 2 = 3
                $totalDocumentationItems = 3;
                // Myanmar does not have Supervisor scoring so it has one less documentation item
                if ($attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
                    $totalDocumentationItems -= 1;
                }
            }
        }

        $documentationScorePerItem = round(($config->evaluation->dts->documentationScore / $totalDocumentationItems), 2);

        $docScoreSheet = new PHPExcel_Worksheet($excel, 'Documentation Score');
        $excel->addSheet($docScoreSheet, 4);
        $docScoreSheet->setTitle('Documentation Score');
        $docScoreSheet->getDefaultColumnDimension()->setWidth(20);
        //$docScoreSheet->getDefaultRowDimension()->setRowHeight(20);
        $docScoreSheet->getDefaultRowDimension('G')->setRowHeight(25);

        $docScoreHeadings = array('Participant Code', 'Participant Name', 'Supervisor signature', 'Panel Receipt Date', 'Sample Rehydration Date', 'Tested Date', 'Rehydration Test In Specified Time', 'Documentation Score %');

        $docScoreSheetCol = 0;
        $docScoreRow = 1;
        $docScoreHeadingsCount = count($docScoreHeadings);
        foreach ($docScoreHeadings as $sheetThreeHK => $value) {
            $docScoreSheet->getCellByColumnAndRow($docScoreSheetCol, $docScoreRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $docScoreSheet->getStyleByColumnAndRow($docScoreSheetCol, $docScoreRow)->getFont()->setBold(true);
            $cellName = $docScoreSheet->getCellByColumnAndRow($docScoreSheetCol, $docScoreRow)->getColumn();
            $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);
            $docScoreSheet->getStyleByColumnAndRow($docScoreSheetCol, $docScoreRow)->getAlignment()->setWrapText(true);
            $docScoreSheetCol++;
        }
        $docScoreRow = 2;
        $secondRowcellName = $docScoreSheet->getCellByColumnAndRow(1, $docScoreRow);
        $secondRowcellName->setValueExplicit(html_entity_decode("Points Breakdown", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $docScoreSheet->getStyleByColumnAndRow(1, $docScoreRow)->getFont()->setBold(true);
        $cellName = $secondRowcellName->getColumn();
        $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);

        for ($r = 2; $r <= 7; $r++) {

            $secondRowcellName = $docScoreSheet->getCellByColumnAndRow($r, $docScoreRow);
            if ($r != 7) {
                $secondRowcellName->setValueExplicit(html_entity_decode($documentationScorePerItem, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            }
            $docScoreSheet->getStyleByColumnAndRow($r, $docScoreRow)->getFont()->setBold(true);
            $cellName = $secondRowcellName->getColumn();
            $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);
        }

        //---------- Document Score Sheet Heading (Sheet Four)------->
        //<-------- Total Score Sheet Heading (Sheet Four)-------


        $totalScoreSheet = new PHPExcel_Worksheet($excel, 'Total Score');
        $excel->addSheet($totalScoreSheet, 5);
        $totalScoreSheet->setTitle('Total Score');
        $totalScoreSheet->getDefaultColumnDimension()->setWidth(20);
        $totalScoreSheet->getDefaultRowDimension(1)->setRowHeight(30);
        $totalScoreHeadings = array('Participant Code', 'Participant Name', 'City', 'Region', 'Country', 'No. of Panels Correct (N=' . $result['number_of_samples'] . ')', 'Panel Score(100% Conv.)', 'Panel Score(90% Conv.)', 'Documentation Score(100% Conv.)', 'Documentation Score(10% Conv.)', 'Total Score', 'Overall Performance', 'Warnings OR Reasons for Failure');

        $totScoreSheetCol = 0;
        $totScoreRow = 1;
        $totScoreHeadingsCount = count($totalScoreHeadings);
        foreach ($totalScoreHeadings as $sheetThreeHK => $value) {
            $totalScoreSheet->getCellByColumnAndRow($totScoreSheetCol, $totScoreRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $totalScoreSheet->getStyleByColumnAndRow($totScoreSheetCol, $totScoreRow)->getFont()->setBold(true);
            $cellName = $totalScoreSheet->getCellByColumnAndRow($totScoreSheetCol, $totScoreRow)->getColumn();
            $totalScoreSheet->getStyle($cellName . $totScoreRow)->applyFromArray($borderStyle);
            $totalScoreSheet->getStyleByColumnAndRow($totScoreSheetCol, $totScoreRow)->getAlignment()->setWrapText(true);
            $totScoreSheetCol++;
        }

        //---------- Document Score Sheet Heading (Sheet Four)------->

        $ktr = 9;
        $kitId = 7; //Test Kit coloumn count 
        if (isset($refResult) && count($refResult) > 0) {
            foreach ($refResult as $keyv => $row) {
                $keyv = $keyv + 1;
                $ktr = $ktr + $keyv;
                if (count($row['kitReference']) > 0) {

                    if ($keyv == 1) {
                        //In Excel Third row added the Test kit name1,kit lot,exp date
                        if (trim($row['kitReference'][0]['expiry_date']) != "") {
                            $row['kitReference'][0]['expiry_date'] = Pt_Commons_General::excelDateFormat($row['kitReference'][0]['expiry_date']);
                        }
                        $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][0]['testKitName'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][0]['lot_no'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][0]['expiry_date'], PHPExcel_Cell_DataType::TYPE_STRING);

                        $kitId = $kitId + $aRow['number_of_samples'] + $aRow['number_of_controls'];
                        if (isset($row['kitReference'][1]['referenceKitResult'])) {
                            //In Excel Third row added the Test kit name2,kit lot,exp date
                            if (trim($row['kitReference'][1]['expiry_date']) != "") {
                                $row['kitReference'][1]['expiry_date'] = Pt_Commons_General::excelDateFormat($row['kitReference'][1]['expiry_date']);
                            }
                            $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][1]['testKitName'], PHPExcel_Cell_DataType::TYPE_STRING);
                            $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][1]['lot_no'], PHPExcel_Cell_DataType::TYPE_STRING);
                            $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][1]['expiry_date'], PHPExcel_Cell_DataType::TYPE_STRING);
                        }

                        if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
                            $kitId = $kitId + $aRow['number_of_samples'] + $aRow['number_of_controls'];
                            if (isset($row['kitReference'][2]['referenceKitResult'])) {
                                //In Excel Third row added the Test kit name3,kit lot,exp date
                                if (trim($row['kitReference'][2]['expiry_date']) != "") {
                                    $row['kitReference'][2]['expiry_date'] = Pt_Commons_General::excelDateFormat($row['kitReference'][2]['expiry_date']);
                                }
                                $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][2]['testKitName'], PHPExcel_Cell_DataType::TYPE_STRING);
                                $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][2]['lot_no'], PHPExcel_Cell_DataType::TYPE_STRING);
                                $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][2]['expiry_date'], PHPExcel_Cell_DataType::TYPE_STRING);
                            }
                        }
                    }

                    $sheet->getCellByColumnAndRow($ktr, 3)->setValueExplicit($row['kitReference'][0]['referenceKitResult'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $ktr = ($aRow['number_of_samples'] + $aRow['number_of_controls'] - $keyv) + $ktr + 3;

                    if (isset($row['kitReference'][1]['referenceKitResult'])) {
                        $ktr = $ktr + $keyv;
                        $sheet->getCellByColumnAndRow($ktr, 3)->setValueExplicit($row['kitReference'][1]['referenceKitResult'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $ktr = ($aRow['number_of_samples'] + $aRow['number_of_controls'] - $keyv) + $ktr + 3;
                    }
                    if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
                        if (isset($row['kitReference'][2]['referenceKitResult'])) {
                            $ktr = $ktr + $keyv;
                            $sheet->getCellByColumnAndRow($ktr, 3)->setValueExplicit($row['kitReference'][2]['referenceKitResult'], PHPExcel_Cell_DataType::TYPE_STRING);
                        }
                    }
                }
                $ktr = 9;
            }
        }

        $currentRow = 4;
        $sheetThreeRow = 2;
        $docScoreRow = 3;
        $totScoreRow = 2;
        if (isset($shipmentResult) && count($shipmentResult) > 0) {

            foreach ($shipmentResult as $aRow) {
                $r = 0;
                $k = 0;
                $rehydrationDate = "";
                $shipmentTestDate = "";
                $sheetThreeCol = 0;
                $docScoreCol = 0;
                $totScoreCol = 0;
                $countCorrectResult = $totPer = 0;

                $finalResult = array(1 => 'Pass', 2 => 'Fail', 3 => 'Excluded', 4 => 'Not Evaluated');

                $colCellObj = $sheet->getCellByColumnAndRow($r++, $currentRow);
                $colCellObj->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $cellName = $colCellObj->getColumn();
                //$sheet->getStyle($cellName.$currentRow)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                //$sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['first_name'] . $aRow['last_name'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['dataManagerFirstName'] . $aRow['dataManagerLastName'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['region'], PHPExcel_Cell_DataType::TYPE_STRING);
                $shipmentReceiptDate = "";
                if (isset($aRow['shipment_receipt_date']) && trim($aRow['shipment_receipt_date']) != "") {
                    $shipmentReceiptDate = $aRow['shipment_receipt_date'] = Pt_Commons_General::excelDateFormat($aRow['shipment_receipt_date']);
                }
                $shipmentReportDate = "";
                if (isset($aRow['shipment_test_report_date']) && trim($aRow['shipment_test_report_date']) != "") {
                    $shipmentReportDate = $aRow['shipment_test_report_date'] = Pt_Commons_General::excelDateFormat($aRow['shipment_test_report_date']);
                }

                if (isset($aRow['shipment_test_date']) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {
                    $shipmentTestDate = Pt_Commons_General::excelDateFormat($aRow['shipment_test_date']);
                }
                if (trim($aRow['attributes']) != "") {
                    $attributes = json_decode($aRow['attributes'], true);
                    $sampleRehydrationDate = new Zend_Date($attributes['sample_rehydration_date']);
                    $rehydrationDate = Pt_Commons_General::excelDateFormat($attributes["sample_rehydration_date"]);
                    if (isset($config->evaluation->dts->displaySampleConditionFields) || $config->evaluation->dts->displaySampleConditionFields == 'yes') {
                        $conditionOfPTSamples = (isset($attributes['condition_pt_samples']) && $attributes['condition_pt_samples'] != "") ? ucwords(str_replace('-', ' ', $attributes['condition_pt_samples'])) : "";
                        $refridgerator = (isset($attributes['refridgerator']) && $attributes['refridgerator'] != "") ? ucwords(str_replace('-', ' ', $attributes['refridgerator'])) : "";
                        $roomTemperature = (isset($attributes['room_temperature']) && $attributes['room_temperature'] != "") ? $attributes['room_temperature'] : "";
                        $stopWatch = (isset($attributes['stop_watch']) && $attributes['stop_watch'] != "") ? ucwords(str_replace('-', ' ', $attributes['stop_watch'])) : "";
                    }
                }

                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($shipmentReceiptDate, PHPExcel_Cell_DataType::TYPE_STRING);
                if (!isset($config->evaluation->dts->displaySampleConditionFields) || $config->evaluation->dts->displaySampleConditionFields != 'yes') {
                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($rehydrationDate, PHPExcel_Cell_DataType::TYPE_STRING);
                }
                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($shipmentTestDate, PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($shipmentReportDate, PHPExcel_Cell_DataType::TYPE_STRING);

                if (isset($config->evaluation->dts->displaySampleConditionFields) || $config->evaluation->dts->displaySampleConditionFields == 'yes') {
                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($conditionOfPTSamples, PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($refridgerator, PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($roomTemperature, PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($stopWatch, PHPExcel_Cell_DataType::TYPE_STRING);
                }


                $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($aRow['first_name'] . $aRow['last_name'], PHPExcel_Cell_DataType::TYPE_STRING);

                //<-------------Document score sheet------------

                $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($aRow['first_name'] . $aRow['last_name'], PHPExcel_Cell_DataType::TYPE_STRING);

                if (isset($shipmentReceiptDate) && trim($shipmentReceiptDate) != "") {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_NUMERIC);
                } else {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_NUMERIC);
                }

                // For Myanmar National Algorithm, they do not want to check for Supervisor Approval
                if ($attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit('-', PHPExcel_Cell_DataType::TYPE_STRING);
                } else {
                    if (isset($aRow['supervisor_approval']) && strtolower($aRow['supervisor_approval']) == 'yes' && isset($aRow['participant_supervisor']) && trim($aRow['participant_supervisor']) != "") {
                        $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_NUMERIC);
                    } else {
                        $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                    }
                }

                if ($attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit('-', PHPExcel_Cell_DataType::TYPE_STRING);
                } else {
                    if (isset($rehydrationDate) && trim($rehydrationDate) != "") {
                        $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                    } else {
                        $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                    }
                }

                if (isset($aRow['shipment_test_date']) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_NUMERIC);
                } else {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_NUMERIC);
                }

                if ($attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit('-', PHPExcel_Cell_DataType::TYPE_STRING);
                } else if (isset($sampleRehydrationDate) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {


                    $config = new Zend_Config_Ini(APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini", APPLICATION_ENV);
                    $sampleRehydrationDate = new DateTime($attributes['sample_rehydration_date']);
                    $testedOnDate = new DateTime($aRow['shipment_test_date']);
                    $interval = $sampleRehydrationDate->diff($testedOnDate);

                    // Testing should be done within 24*($config->evaluation->dts->sampleRehydrateDays) hours of rehydration.
                    $sampleRehydrateDays = $config->evaluation->dts->sampleRehydrateDays;
                    $rehydrateHours = $sampleRehydrateDays * 24;

                    if ($interval->days < $sampleRehydrateDays || $interval->days > ($sampleRehydrateDays + 1)) {

                        $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_NUMERIC);
                    } else {
                        $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_NUMERIC);
                    }
                } else {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_NUMERIC);
                }

                $documentScore = (($aRow['documentation_score'] / $config->evaluation->dts->documentationScore) * 100);
                $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentScore, PHPExcel_Cell_DataType::TYPE_NUMERIC);

                //-------------Document score sheet------------>
                //<------------ Total score sheet ------------

                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['first_name'] . $aRow['last_name'], PHPExcel_Cell_DataType::TYPE_STRING);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['city'], PHPExcel_Cell_DataType::TYPE_STRING);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['region'], PHPExcel_Cell_DataType::TYPE_STRING);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['iso_name'], PHPExcel_Cell_DataType::TYPE_STRING);

                //------------ Total score sheet ------------>
                //Zend_Debug::dump($aRow['response']);
                if (count($aRow['response']) > 0) {

                    if (isset($aRow['response'][0]['exp_date_1']) && trim($aRow['response'][0]['exp_date_1']) != "") {
                        $aRow['response'][0]['exp_date_1'] = Pt_Commons_General::excelDateFormat($aRow['response'][0]['exp_date_1']);
                    }
                    if (isset($aRow['response'][0]['exp_date_2']) && trim($aRow['response'][0]['exp_date_2']) != "") {
                        $aRow['response'][0]['exp_date_2'] = Pt_Commons_General::excelDateFormat($aRow['response'][0]['exp_date_2']);
                    }
                    if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
                        if (isset($aRow['response'][0]['exp_date_3']) && trim($aRow['response'][0]['exp_date_3']) != "") {
                            $aRow['response'][0]['exp_date_3'] = Pt_Commons_General::excelDateFormat($aRow['response'][0]['exp_date_3']);
                        }
                    }

                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['testKitName1'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['lot_no_1'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['exp_date_1'], PHPExcel_Cell_DataType::TYPE_STRING);

                    for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
                        //$row[] = $aRow[$k]['testResult1'];
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][$k]['testResult1'], PHPExcel_Cell_DataType::TYPE_STRING);
                    }
                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['testKitName2'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['lot_no_2'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['exp_date_2'], PHPExcel_Cell_DataType::TYPE_STRING);

                    for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
                        //$row[] = $aRow[$k]['testResult2'];
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][$k]['testResult2'], PHPExcel_Cell_DataType::TYPE_STRING);
                    }

                    if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['testKitName3'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['lot_no_3'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['exp_date_3'], PHPExcel_Cell_DataType::TYPE_STRING);

                        for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
                            //$row[] = $aRow[$k]['testResult3'];
                            $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][$k]['testResult3'], PHPExcel_Cell_DataType::TYPE_STRING);
                        }
                    }
                    if (isset($config->evaluation->dts->allowRepeatTests) && $config->evaluation->dts->allowRepeatTests == 'yes') {
                        for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
                            $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][$k]['repeatTestResult1'], PHPExcel_Cell_DataType::TYPE_STRING);
                        }
                        for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
                            $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][$k]['repeatTestResult2'], PHPExcel_Cell_DataType::TYPE_STRING);
                        }
                        if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
                            for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
                                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][$k]['repeatTestResult3'], PHPExcel_Cell_DataType::TYPE_STRING);
                            }
                        }
                    }

                    for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
                        //$row[] = $aRow[$k]['finalResult'];
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][$k]['finalResult'], PHPExcel_Cell_DataType::TYPE_STRING);

                        $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($aRow['response'][$k]['finalResult'], PHPExcel_Cell_DataType::TYPE_STRING);
                        if (isset($aRow['response'][$k]['calculated_score']) && $aRow['response'][$k]['calculated_score'] == 'Pass' && $aRow['response'][$k]['sample_id'] == $refResult[$k]['sample_id']) {
                            $countCorrectResult++;
                        }
                    }
                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['user_comment'], PHPExcel_Cell_DataType::TYPE_STRING);

                    $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($countCorrectResult, PHPExcel_Cell_DataType::TYPE_STRING);

                    $totPer = round((($countCorrectResult / $aRow['number_of_samples']) * 100), 2);
                    $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($totPer, PHPExcel_Cell_DataType::TYPE_NUMERIC);
                }
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($countCorrectResult, PHPExcel_Cell_DataType::TYPE_NUMERIC);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($totPer, PHPExcel_Cell_DataType::TYPE_NUMERIC);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(($totPer * 0.9), PHPExcel_Cell_DataType::TYPE_NUMERIC);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($documentScore, PHPExcel_Cell_DataType::TYPE_NUMERIC);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['documentation_score'], PHPExcel_Cell_DataType::TYPE_NUMERIC);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(($aRow['shipment_score'] + $aRow['documentation_score']), PHPExcel_Cell_DataType::TYPE_NUMERIC);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($finalResult[$aRow['final_result']], PHPExcel_Cell_DataType::TYPE_STRING);
                if (!empty($aRow['failure_reason'])) {
                    $failureReasonJson = $aRow['failure_reason'];
                    $warningsArray = json_decode($failureReasonJson, true);
                    $warnings = array();
                    foreach ($warningsArray as $w) {
                        $warnings[] = strip_tags($w['warning']);
                    }
                    $warnings = implode(", ", $warnings);
                } else {
                    $warnings = "";
                }
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($warnings, PHPExcel_Cell_DataType::TYPE_STRING);

                for ($i = 0; $i < $panelScoreHeadingCount; $i++) {
                    $cellName = $sheetThree->getCellByColumnAndRow($i, $sheetThreeRow)->getColumn();
                    $sheetThree->getStyle($cellName . $sheetThreeRow)->applyFromArray($borderStyle);
                }

                for ($i = 0; $i < $n; $i++) {
                    $cellName = $sheet->getCellByColumnAndRow($i, $currentRow)->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
                }

                for ($i = 0; $i < $docScoreHeadingsCount; $i++) {
                    $cellName = $docScoreSheet->getCellByColumnAndRow($i, $docScoreRow)->getColumn();
                    $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);
                }

                for ($i = 0; $i < $totScoreHeadingsCount; $i++) {
                    $cellName = $totalScoreSheet->getCellByColumnAndRow($i, $totScoreRow)->getColumn();
                    $totalScoreSheet->getStyle($cellName . $totScoreRow)->applyFromArray($borderStyle);
                    $totalScoreSheet->getStyleByColumnAndRow($i, $totScoreRow)->getAlignment()->setWrapText(true);
                }

                $currentRow++;

                $sheetThreeRow++;
                $docScoreRow++;
                $totScoreRow++;
            }
        }

        //----------- Second Sheet End----->

        $excel->setActiveSheetIndex(0);

        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $filename = $shipmentCode . '-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }

    public function generateCovid19ExcelReport($shipmentId)
    {
        $config = new Zend_Config_Ini(APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini", APPLICATION_ENV);
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $excel = new PHPExcel();
        //$sheet = $excel->getActiveSheet();


        $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
        $cacheSettings = array('memoryCacheSize' => '80MB');

        $styleArray = array(
            'font' => array(
                'bold' => true,
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                ),
            )
        );

        $borderStyle = array(
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                ),
            )
        );

        $query = $db->select()->from('shipment', array('shipment_id', 'shipment_code', 'scheme_type', 'number_of_samples'))
            ->where("shipment_id = ?", $shipmentId);
        $result = $db->fetchRow($query);

        if ($result['scheme_type'] == 'covid19') {

            $refQuery = $db->select()->from(array('refRes' => 'reference_result_covid19'), array('refRes.sample_label', 'sample_id', 'refRes.sample_score'))
                ->joinLeft(array('r' => 'r_possibleresult'), 'r.id=refRes.reference_result', array('referenceResult' => 'r.response'))
                ->where("refRes.shipment_id = ?", $shipmentId);
            $refResult = $db->fetchAll($refQuery);
            if (count($refResult) > 0) {
                foreach ($refResult as $key => $refRes) {
                    $refCovid19Query = $db->select()->from(array('refCovid19' => 'reference_covid19_test_type'), array('refCovid19.lot_no', 'refCovid19.expiry_date', 'refCovid19.result'))
                        ->joinLeft(array('r' => 'r_possibleresult'), 'r.id=refCovid19.result', array('referenceTypeResult' => 'r.response'))
                        ->joinLeft(array('tt' => 'r_test_type_covid19'), 'tt.test_type_id=refCovid19.test_type', array('testPlatformName' => 'tt.test_type_name'))
                        ->where("refCovid19.shipment_id = ?", $shipmentId)
                        ->where("refCovid19.sample_id = ?", $refRes['sample_id']);
                    $refResult[$key]['typeReference'] = $db->fetchAll($refCovid19Query);
                }
            }
        }

        $firstSheet = new PHPExcel_Worksheet($excel, 'Instructions');
        $excel->addSheet($firstSheet, 0);
        $firstSheet->setTitle('Instructions');
        //$firstSheet->getDefaultColumnDimension()->setWidth(44);
        //$firstSheet->getDefaultRowDimension()->setRowHeight(45);
        $firstSheetHeading = array('Tab Name', 'Description');
        $firstSheetColNo = 0;
        $firstSheetRow = 1;

        $firstSheetStyle = array(
            'alignment' => array(
                //'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                ),
            )
        );

        foreach ($firstSheetHeading as $value) {
            $firstSheet->getCellByColumnAndRow($firstSheetColNo, $firstSheetRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getStyleByColumnAndRow($firstSheetColNo, $firstSheetRow)->getFont()->setBold(true);
            $cellName = $firstSheet->getCellByColumnAndRow($firstSheetColNo, $firstSheetRow)->getColumn();
            $firstSheet->getStyle($cellName . $firstSheetRow)->applyFromArray($firstSheetStyle);
            $firstSheetColNo++;
        }

        $firstSheet->getCellByColumnAndRow(0, 2)->setValueExplicit(html_entity_decode("Participant List", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode("Includes dropdown lists for the following: region, department, position, RT, ELISA, received logbook", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $firstSheet->getDefaultRowDimension()->setRowHeight(10);
        $firstSheet->getColumnDimensionByColumn(0)->setWidth(20);
        $firstSheet->getDefaultRowDimension(1)->setRowHeight(70);
        $firstSheet->getColumnDimensionByColumn(1)->setWidth(100);

        $firstSheet->getCellByColumnAndRow(0, 3)->setValueExplicit(html_entity_decode("Results Reported", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode("This tab should include no commentary from PT Admin staff.  All fields should only reflect results or comments reported on the results form.  If no report was submitted, highlight site data cells in red.  Explanation of missing results should only be comments that the site made, not PT staff.  All dates should be formatted as DD/MM/YY.  Dropdown menu legend is as followed: negative (NEG), positive (POS), invalid (INV), indeterminate (IND), not entered or reported (NE), not tested (NT) and should be used according to the way the site reported it.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(0, 4)->setValueExplicit(html_entity_decode("Panel Score", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(1, 4)->setValueExplicit(html_entity_decode("This tab is automatically populated.  Panel score calculated 6/6.  If a panel member must be omitted from the calculation (ie, loss of sample, etc) you must revise the equation manually by changing the number 6 to 5,4,etc. accordingly. Example seen for Akai House Clinic.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(0, 5)->setValueExplicit(html_entity_decode("Documentation Score", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(1, 5)->setValueExplicit(html_entity_decode("The points breakdown for this tab are listed in the row above the sites for each column.  Data should be entered in manually by PT staff.  A site scores 1.5/3 if they used the wrong test kits got a 100% panel score.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(0, 6)->setValueExplicit(html_entity_decode("Total Score", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(1, 6)->setValueExplicit(html_entity_decode("Columns C-F are populated automatically.  Columns G, H and I must be selected from the dropdown menu for each site based on the criteria listed in the 'Decision Tree' tab.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(0, 7)->setValueExplicit(html_entity_decode("Follow-up Calls", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(1, 7)->setValueExplicit(html_entity_decode("Final comments or outcomes should be updated continuously with receipt dates included.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(0, 8)->setValueExplicit(html_entity_decode("Dropdown Lists", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(1, 8)->setValueExplicit(html_entity_decode("This tab contains all of the dropdown lists included in the rest of the database, any modifications should be performed with caution.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(0, 9)->setValueExplicit(html_entity_decode("Decision Tree", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(1, 9)->setValueExplicit(html_entity_decode("Lists all of the appropriate corrective actions and scoring critieria.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(0, 10)->setValueExplicit(html_entity_decode("Feedback Report", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(1, 10)->setValueExplicit(html_entity_decode("This tab is populated automatically and used to export data into the Feedback Reports generated in MS Word.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(0, 11)->setValueExplicit(html_entity_decode("Comments", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(1, 11)->setValueExplicit(html_entity_decode("This tab lists all of the more detailed comments that will be given to the sites during site visits and phone calls.", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);


        for ($counter = 1; $counter <= 11; $counter++) {
            $firstSheet->getStyleByColumnAndRow(1, $counter)->getAlignment()->setWrapText(true);
            $firstSheet->getStyle("A$counter")->applyFromArray($firstSheetStyle);
            $firstSheet->getStyle("B$counter")->applyFromArray($firstSheetStyle);
        }
        //<------------ Participant List Details Start -----

        $headings = array('Participant Code', 'Participant Name',  'Institute Name', 'Department', 'Address', 'Region', 'City', 'Facility Telephone', 'Email');

        $sheet = new PHPExcel_Worksheet($excel, 'Participant List');
        $excel->addSheet($sheet, 1);
        $sheet->setTitle('Participant List');

        $sql = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.number_of_samples'))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('sp.map_id', 'sp.participant_id', 'sp.attributes', 'sp.shipment_test_date', 'sp.shipment_receipt_date', 'sp.shipment_test_report_date', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_score', 'sp.documentation_score', 'sp.user_comment'))
            ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.lab_name', 'p.region', 'p.first_name', 'p.last_name', 'p.address', 'p.city', 'p.mobile', 'p.email', 'p.status'))
            ->joinLeft(array('pmp' => 'participant_manager_map'), 'pmp.participant_id=p.participant_id', array('pmp.dm_id'))
            ->joinLeft(array('dm' => 'data_manager'), 'dm.dm_id=pmp.dm_id', array('dm.institute', 'dataManagerFirstName' => 'dm.first_name', 'dataManagerLastName' => 'dm.last_name'))
            ->joinLeft(array('st' => 'r_site_type'), 'st.r_stid=p.site_type', array('st.site_type'))
            ->joinLeft(array('en' => 'enrollments'), 'en.participant_id=p.participant_id', array('en.enrolled_on'))
            ->where("s.shipment_id = ?", $shipmentId)
            ->group(array('sp.map_id'));
        //echo $sql;die;
        $shipmentResult = $db->fetchAll($sql);
        //die;
        $colNo = 0;
        $currentRow = 1;
        $type = PHPExcel_Cell_DataType::TYPE_STRING;
        //$sheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode("Participant List", ENT_QUOTES, 'UTF-8'), $type);
        //$sheet->getStyleByColumnAndRow(0,1)->getFont()->setBold(true);
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getDefaultRowDimension()->setRowHeight(18);

        foreach ($headings as $field => $value) {
            $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow($colNo, $currentRow)->getFont()->setBold(true);
            $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
            $colNo++;
        }

        if (isset($shipmentResult) && count($shipmentResult) > 0) {
            $currentRow += 1;
            foreach ($shipmentResult as $key => $aRow) {
                if ($result['scheme_type'] == 'covid19') {
                    $resQuery = $db->select()->from(array('rrcovid19' => 'response_result_covid19'))
                        ->joinLeft(array('tt1' => 'r_test_type_covid19'), 'tt1.test_type_id=rrcovid19.test_type_1', array('testPlatformName1' => 'tt1.test_type_name'))
                        ->joinLeft(array('tt2' => 'r_test_type_covid19'), 'tt2.test_type_id=rrcovid19.test_type_2', array('testPlatformName2' => 'tt2.test_type_name'))
                        ->joinLeft(array('tt3' => 'r_test_type_covid19'), 'tt3.test_type_id=rrcovid19.test_type_3', array('test~PlatformName3' => 'tt3.test_type_name'))
                        ->joinLeft(array('r' => 'r_possibleresult'), 'r.id=rrcovid19.test_result_1', array('testResult1' => 'r.response'))
                        ->joinLeft(array('rp' => 'r_possibleresult'), 'rp.id=rrcovid19.test_result_2', array('testResult2' => 'rp.response'))
                        ->joinLeft(array('rpr' => 'r_possibleresult'), 'rpr.id=rrcovid19.test_result_3', array('testResult3' => 'rpr.response'))
                        ->joinLeft(array('fr' => 'r_possibleresult'), 'fr.id=rrcovid19.reported_result', array('finalResult' => 'fr.response'))
                        ->where("rrcovid19.shipment_map_id = ?", $aRow['map_id']);
                    $shipmentResult[$key]['response'] = $db->fetchAll($resQuery);
                }


                $sheet->getCellByColumnAndRow(0, $currentRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(1, $currentRow)->setValueExplicit($aRow['first_name'] . $aRow['last_name'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(2, $currentRow)->setValueExplicit($aRow['institute_name'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(3, $currentRow)->setValueExplicit($aRow['department_name'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(4, $currentRow)->setValueExplicit($aRow['address'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(5, $currentRow)->setValueExplicit($aRow['city'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(6, $currentRow)->setValueExplicit($aRow['region'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(7, $currentRow)->setValueExplicit($aRow['mobile'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(8, $currentRow)->setValueExplicit(strtolower($aRow['email']), PHPExcel_Cell_DataType::TYPE_STRING);

                for ($i = 0; $i <= 8; $i++) {
                    $cellName = $sheet->getCellByColumnAndRow($i, $currentRow)->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
                }

                $currentRow++;
                $shipmentCode = $aRow['shipment_code'];
            }
        }

        //------------- Participant List Details End ------>

        //<-------- Second sheet start
        $reportHeadings = array('Participant Code', 'Participant Name', 'Point of Contact', 'Region', 'Shipment Receipt Date', 'Sample Rehydration Date', 'Testing Date', 'Test#1 Name', 'Name of PCR reagent #1', 'PCR reagent Lot #1', 'PCR reagent expiry date #1', 'Type Lot #1', 'Expiry Date');
        $maximumAllowed = $config->evaluation->covid19->covid19MaximumTestAllowed;
        if ($result['scheme_type'] == 'covid19') {
            $reportHeadings = $this->addCovid19SampleNameInArray($shipmentId, $reportHeadings);
            if ($maximumAllowed >= 2) {
                array_push($reportHeadings, 'Test#2 Name', 'Name of PCR reagent #2', 'PCR reagent Lot #2', 'PCR reagent expiry date #2', 'Type Lot #2', 'Expiry Date');
                $reportHeadings = $this->addCovid19SampleNameInArray($shipmentId, $reportHeadings);
            }
            if ($maximumAllowed == 3) {
                array_push($reportHeadings, 'Test#3 Name', 'Name of PCR reagent #3', 'PCR reagent Lot #3', 'PCR reagent expiry date #3', 'Type Lot #3', 'Expiry Date');
                $reportHeadings = $this->addCovid19SampleNameInArray($shipmentId, $reportHeadings);
            }
            $reportHeadings = $this->addCovid19SampleNameInArray($shipmentId, $reportHeadings);
            array_push($reportHeadings, 'Comments');
        }

        $sheet = new PHPExcel_Worksheet($excel, 'Results Reported');
        $excel->addSheet($sheet, 2);
        $sheet->setTitle('Results Reported');
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getDefaultRowDimension()->setRowHeight(18);


        $colNo = 0;
        $currentRow = 2;
        $n = count($reportHeadings);
        $finalResColoumn = $n - ($result['number_of_samples'] + 1);
        $c = 1;
        $endMergeCell = ($finalResColoumn + $result['number_of_samples']) - 1;

        $firstCellName = $sheet->getCellByColumnAndRow($finalResColoumn, 1)->getColumn();
        $secondCellName = $sheet->getCellByColumnAndRow($endMergeCell, 1)->getColumn();
        $sheet->mergeCells($firstCellName . "1:" . $secondCellName . "1");
        $sheet->getStyle($firstCellName . "1")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle($firstCellName . "1")->applyFromArray($borderStyle);
        $sheet->getStyle($secondCellName . "1")->applyFromArray($borderStyle);

        foreach ($reportHeadings as $field => $value) {

            $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow($colNo, $currentRow)->getFont()->setBold(true);
            $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);

            $cellName = $sheet->getCellByColumnAndRow($colNo, 3)->getColumn();
            $sheet->getStyle($cellName . "3")->applyFromArray($borderStyle);

            if ($colNo >= $finalResColoumn) {
                if ($c <= $result['number_of_samples']) {

                    $sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode("Final Results", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                    $l = $c - 1;
                    $sheet->getCellByColumnAndRow($colNo, 3)->setValueExplicit(html_entity_decode($refResult[$l]['referenceResult'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                }
                $c++;
            }
            $sheet->getStyle($cellName . '3')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFA0A0A0');
            $sheet->getStyle($cellName . '3')->getFont()->getColor()->setARGB('FFFFFF00');

            $colNo++;
        }

        $sheet->getStyle("A2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("B2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("C2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("D2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');

        //$sheet->getStyle("D2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');
        //$sheet->getStyle("E2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');
        //$sheet->getStyle("F2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');

        $cellName = $sheet->getCellByColumnAndRow($n, 3)->getColumn();
        //$sheet->getStyle('A3:'.$cellName.'3')->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#969696');
        //$sheet->getStyle('A3:'.$cellName.'3')->applyFromArray($borderStyle);
        //<-------- Sheet three heading -------
        $sheetThree = new PHPExcel_Worksheet($excel, 'Panel Score');
        $excel->addSheet($sheetThree, 3);
        $sheetThree->setTitle('Panel Score');
        $sheetThree->getDefaultColumnDimension()->setWidth(20);
        $sheetThree->getDefaultRowDimension()->setRowHeight(18);
        $panelScoreHeadings = array('Participant Code', 'Participant Name');
        $panelScoreHeadings = $this->addCovid19SampleNameInArray($shipmentId, $panelScoreHeadings);
        array_push($panelScoreHeadings, 'Test# Correct', '% Correct');
        $sheetThreeColNo = 0;
        $sheetThreeRow = 1;
        $panelScoreHeadingCount = count($panelScoreHeadings);
        $sheetThreeColor = 1 + $result['number_of_samples'];
        foreach ($panelScoreHeadings as $sheetThreeHK => $value) {
            $sheetThree->getCellByColumnAndRow($sheetThreeColNo, $sheetThreeRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheetThree->getStyleByColumnAndRow($sheetThreeColNo, $sheetThreeRow)->getFont()->setBold(true);
            $cellName = $sheetThree->getCellByColumnAndRow($sheetThreeColNo, $sheetThreeRow)->getColumn();
            $sheetThree->getStyle($cellName . $sheetThreeRow)->applyFromArray($borderStyle);

            if ($sheetThreeHK > 1 && $sheetThreeHK <= $sheetThreeColor) {
                $cellName = $sheetThree->getCellByColumnAndRow($sheetThreeColNo, $sheetThreeRow)->getColumn();
                $sheetThree->getStyle($cellName . $sheetThreeRow)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            }

            $sheetThreeColNo++;
        }
        //---------- Sheet Three heading ------->
        //<-------- Document Score Sheet Heading (Sheet Four)-------

        /* if ($result['scheme_type'] == 'covid19') {
            $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
            $config = new Zend_Config_Ini($file, APPLICATION_ENV);
            $shipmentAttributes = json_decode($aRow['shipment_attributes'], true);
            if (isset($shipmentAttributes['sampleType']) && $shipmentAttributes['sampleType'] == 'dried') {
                // for Dried Samples, we will have rehydration as one of the documentation scores
                $documentationScorePerItem = round(($config->evaluation->covid19->documentationScore / 5), 2);
            } else {
                // for Non Dried Samples, we will NOT have rehydration documentation scores 
                // there are 2 conditions for rehydration so 5 - 2 = 3
                $documentationScorePerItem = round(($config->evaluation->covid19->documentationScore / 3), 2);
            }            
        } */

        /* $docScoreSheet = new PHPExcel_Worksheet($excel, 'Documentation Score');
        $excel->addSheet($docScoreSheet, 4);
        $docScoreSheet->setTitle('Documentation Score');
        $docScoreSheet->getDefaultColumnDimension()->setWidth(20);
        //$docScoreSheet->getDefaultRowDimension()->setRowHeight(20);
        $docScoreSheet->getDefaultRowDimension('G')->setRowHeight(25);

        $docScoreHeadings = array('Participant Code', 'Participant Name', 'Supervisor signature', 'Panel Receipt Date', 'Rehydration Date', 'Tested Date', 'Rehydration Test In Specified Time', 'Documentation Score %');

        $docScoreSheetCol = 0;
        $docScoreRow = 1;
        $docScoreHeadingsCount = count($docScoreHeadings);
        foreach ($docScoreHeadings as $sheetThreeHK => $value) {
            $docScoreSheet->getCellByColumnAndRow($docScoreSheetCol, $docScoreRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $docScoreSheet->getStyleByColumnAndRow($docScoreSheetCol, $docScoreRow)->getFont()->setBold(true);
            $cellName = $docScoreSheet->getCellByColumnAndRow($docScoreSheetCol, $docScoreRow)->getColumn();
            $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);
            $docScoreSheet->getStyleByColumnAndRow($docScoreSheetCol, $docScoreRow)->getAlignment()->setWrapText(true);
            $docScoreSheetCol++;
        }
        $docScoreRow = 2;
        $secondRowcellName = $docScoreSheet->getCellByColumnAndRow(1, $docScoreRow);
        $secondRowcellName->setValueExplicit(html_entity_decode("Points Breakdown", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $docScoreSheet->getStyleByColumnAndRow(1, $docScoreRow)->getFont()->setBold(true);
        $cellName = $secondRowcellName->getColumn();
        $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);

        for ($r = 2; $r <= 7; $r++) {

            $secondRowcellName = $docScoreSheet->getCellByColumnAndRow($r, $docScoreRow);
            if ($r != 7) {
                $secondRowcellName->setValueExplicit(html_entity_decode($documentationScorePerItem, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            }
            $docScoreSheet->getStyleByColumnAndRow($r, $docScoreRow)->getFont()->setBold(true);
            $cellName = $secondRowcellName->getColumn();
            $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);
        } */

        //---------- Document Score Sheet Heading (Sheet Four)------->
        //<-------- Total Score Sheet Heading (Sheet Four)-------


        $totalScoreSheet = new PHPExcel_Worksheet($excel, 'Total Score');
        $excel->addSheet($totalScoreSheet, 4);
        $totalScoreSheet->setTitle('Total Score');
        $totalScoreSheet->getDefaultColumnDimension()->setWidth(20);
        $totalScoreSheet->getDefaultRowDimension(1)->setRowHeight(30);
        $totalScoreHeadings = array('Participant Code', 'Participant Name', 'No. of Panels Correct (N=' . $result['number_of_samples'] . ')', 'Panel Score(100% Conv.)', 'Panel Score(90% Conv.)', 'Documentation Score(100% Conv.)', 'Documentation Score(10% Conv.)', 'Total Score', 'Overall Performance');

        $totScoreSheetCol = 0;
        $totScoreRow = 1;
        $totScoreHeadingsCount = count($totalScoreHeadings);
        foreach ($totalScoreHeadings as $sheetThreeHK => $value) {
            $totalScoreSheet->getCellByColumnAndRow($totScoreSheetCol, $totScoreRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $totalScoreSheet->getStyleByColumnAndRow($totScoreSheetCol, $totScoreRow)->getFont()->setBold(true);
            $cellName = $totalScoreSheet->getCellByColumnAndRow($totScoreSheetCol, $totScoreRow)->getColumn();
            $totalScoreSheet->getStyle($cellName . $totScoreRow)->applyFromArray($borderStyle);
            $totalScoreSheet->getStyleByColumnAndRow($totScoreSheetCol, $totScoreRow)->getAlignment()->setWrapText(true);
            $totScoreSheetCol++;
        }

        //---------- Document Score Sheet Heading (Sheet Four)------->

        $ktr = 9;
        $kitId = 7; //Test Kit coloumn count 
        if (isset($refResult) && count($refResult) > 0) {
            foreach ($refResult as $keyv => $row) {
                $keyv = $keyv + 1;
                $ktr = $ktr + $keyv;
                if (count($row['typeReference']) > 0) {

                    if ($keyv == 1) {
                        //In Excel Third row added the Test kit name1,kit lot,exp date
                        if (trim($row['typeReference'][0]['expiry_date']) != "") {
                            $row['typeReference'][0]['expiry_date'] = Pt_Commons_General::excelDateFormat($row['typeReference'][0]['expiry_date']);
                        }
                        $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['typeReference'][0]['testPlatformName'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['typeReference'][0]['lot_no'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['typeReference'][0]['expiry_date'], PHPExcel_Cell_DataType::TYPE_STRING);

                        $kitId = $kitId + $aRow['number_of_samples'];
                        if (isset($row['typeReference'][1]['referenceTypeResult'])) {
                            //In Excel Third row added the Test kit name2,kit lot,exp date
                            if (trim($row['typeReference'][1]['expiry_date']) != "") {
                                $row['typeReference'][1]['expiry_date'] = Pt_Commons_General::excelDateFormat($row['typeReference'][1]['expiry_date']);
                            }
                            $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['typeReference'][1]['testPlatformName'], PHPExcel_Cell_DataType::TYPE_STRING);
                            $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['typeReference'][1]['lot_no'], PHPExcel_Cell_DataType::TYPE_STRING);
                            $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['typeReference'][1]['expiry_date'], PHPExcel_Cell_DataType::TYPE_STRING);
                        }
                        $kitId = $kitId + $aRow['number_of_samples'];
                        if (isset($row['typeReference'][2]['referenceTypeResult'])) {
                            //In Excel Third row added the Test kit name3,kit lot,exp date
                            if (trim($row['typeReference'][2]['expiry_date']) != "") {
                                $row['typeReference'][2]['expiry_date'] = Pt_Commons_General::excelDateFormat($row['typeReference'][2]['expiry_date']);
                            }
                            $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['typeReference'][2]['testPlatformName'], PHPExcel_Cell_DataType::TYPE_STRING);
                            $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['typeReference'][2]['lot_no'], PHPExcel_Cell_DataType::TYPE_STRING);
                            $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['typeReference'][2]['expiry_date'], PHPExcel_Cell_DataType::TYPE_STRING);
                        }
                    }

                    $sheet->getCellByColumnAndRow($ktr, 3)->setValueExplicit($row['typeReference'][0]['referenceTypeResult'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $ktr = ($aRow['number_of_samples'] - $keyv) + $ktr + 3;

                    if (isset($row['typeReference'][1]['referenceTypeResult'])) {
                        $ktr = $ktr + $keyv;
                        $sheet->getCellByColumnAndRow($ktr, 3)->setValueExplicit($row['typeReference'][1]['referenceTypeResult'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $ktr = ($aRow['number_of_samples'] - $keyv) + $ktr + 3;
                    }
                    if (isset($row['typeReference'][2]['referenceTypeResult'])) {
                        $ktr = $ktr + $keyv;
                        $sheet->getCellByColumnAndRow($ktr, 3)->setValueExplicit($row['typeReference'][2]['referenceTypeResult'], PHPExcel_Cell_DataType::TYPE_STRING);
                    }
                }
                $ktr = 9;
            }
        }

        $currentRow = 4;
        $sheetThreeRow = 2;
        $docScoreRow = 3;
        $totScoreRow = 2;
        if (isset($shipmentResult) && count($shipmentResult) > 0) {

            foreach ($shipmentResult as $aRow) {
                $r = 0;
                $k = 0;
                $rehydrationDate = "";
                $shipmentTestDate = "";
                $sheetThreeCol = 0;
                $docScoreCol = 0;
                $totScoreCol = 0;
                $countCorrectResult = 0;

                $colCellObj = $sheet->getCellByColumnAndRow($r++, $currentRow);
                $colCellObj->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $cellName = $colCellObj->getColumn();
                //$sheet->getStyle($cellName.$currentRow)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                //$sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['first_name'] . $aRow['last_name'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['dataManagerFirstName'] . $aRow['dataManagerLastName'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['region'], PHPExcel_Cell_DataType::TYPE_STRING);
                $shipmentReceiptDate = "";
                if (isset($aRow['shipment_receipt_date']) && trim($aRow['shipment_receipt_date']) != "") {
                    $shipmentReceiptDate = $aRow['shipment_receipt_date'] = Pt_Commons_General::excelDateFormat($aRow['shipment_receipt_date']);
                }

                if (isset($aRow['shipment_test_date']) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {
                    $shipmentTestDate = Pt_Commons_General::excelDateFormat($aRow['shipment_test_date']);
                }

                if (trim($aRow['attributes']) != "") {
                    $attributes = json_decode($aRow['attributes'], true);
                    $sampleRehydrationDate = new Zend_Date($attributes['sample_rehydration_date']);
                    $rehydrationDate = Pt_Commons_General::excelDateFormat($attributes["sample_rehydration_date"]);
                }

                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['shipment_receipt_date'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($rehydrationDate, PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($shipmentTestDate, PHPExcel_Cell_DataType::TYPE_STRING);



                $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($aRow['first_name'] . $aRow['last_name'], PHPExcel_Cell_DataType::TYPE_STRING);

                //<-------------Document score sheet------------

                /* $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($aRow['first_name'] . $aRow['last_name'], PHPExcel_Cell_DataType::TYPE_STRING);

                if (isset($shipmentReceiptDate) && trim($shipmentReceiptDate) != "") {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                } else {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                }

                // For Myanmar National Algorithm, they do not want to check for Supervisor Approval
                if ($attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                } else {
                    if (isset($aRow['supervisor_approval']) && strtolower($aRow['supervisor_approval']) == 'yes' && isset($aRow['participant_supervisor']) && trim($aRow['participant_supervisor']) != "") {
                        $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                    } else {
                        $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                    }
                }


                if (isset($rehydrationDate) && trim($rehydrationDate) != "") {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                } else {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                }

                if (isset($aRow['shipment_test_date']) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                } else {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                }

                if (isset($sampleRehydrationDate) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {


                    $config = new Zend_Config_Ini(APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini", APPLICATION_ENV);
                    $sampleRehydrationDate = new DateTime($attributes['sample_rehydration_date']);
                    $testedOnDate = new DateTime($aRow['shipment_test_date']);
                    $interval = $sampleRehydrationDate->diff($testedOnDate);

                    // Testing should be done within 24*($config->evaluation->covid19->sampleRehydrateDays) hours of rehydration.
                    $sampleRehydrateDays = $config->evaluation->covid19->sampleRehydrateDays;
                    $rehydrateHours = $sampleRehydrateDays * 24;

                    if ($interval->days < $sampleRehydrateDays || $interval->days > ($sampleRehydrateDays + 1)) {

                        $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                    } else {
                        $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                    }
                } else {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                }
                */
                $documentScore = (($aRow['documentation_score'] / $config->evaluation->covid19->documentationScore) * 100);
                /*
                $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentScore, PHPExcel_Cell_DataType::TYPE_STRING);
                */
                //-------------Document score sheet------------>
                //<------------ Total score sheet ------------

                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['first_name'] . $aRow['last_name'], PHPExcel_Cell_DataType::TYPE_STRING);

                //------------ Total score sheet ------------>
                //Zend_Debug::dump($aRow['response']);
                if (count($aRow['response']) > 0) {

                    if (isset($aRow['response'][0]['exp_date_1']) && trim($aRow['response'][0]['exp_date_1']) != "") {
                        $aRow['response'][0]['exp_date_1'] = Pt_Commons_General::excelDateFormat($aRow['response'][0]['exp_date_1']);
                    }
                    if (isset($aRow['response'][0]['exp_date_2']) && trim($aRow['response'][0]['exp_date_2']) != "") {
                        $aRow['response'][0]['exp_date_2'] = Pt_Commons_General::excelDateFormat($aRow['response'][0]['exp_date_2']);
                    }
                    if (isset($aRow['response'][0]['exp_date_3']) && trim($aRow['response'][0]['exp_date_3']) != "") {
                        $aRow['response'][0]['exp_date_3'] = Pt_Commons_General::excelDateFormat($aRow['response'][0]['exp_date_3']);
                    }

                    if (isset($aRow['response'][0]['pcr_reagent_exp_date_1']) && trim($aRow['response'][0]['pcr_reagent_exp_date_1']) != "") {
                        $aRow['response'][0]['pcr_reagent_exp_date_1'] = Pt_Commons_General::excelDateFormat($aRow['response'][0]['pcr_reagent_exp_date_1']);
                    }
                    if (isset($aRow['response'][0]['pcr_reagent_exp_date_2']) && trim($aRow['response'][0]['pcr_reagent_exp_date_2']) != "") {
                        $aRow['response'][0]['pcr_reagent_exp_date_2'] = Pt_Commons_General::excelDateFormat($aRow['response'][0]['pcr_reagent_exp_date_2']);
                    }
                    if (isset($aRow['response'][0]['pcr_reagent_exp_date_3']) && trim($aRow['response'][0]['pcr_reagent_exp_date_3']) != "") {
                        $aRow['response'][0]['pcr_reagent_exp_date_3'] = Pt_Commons_General::excelDateFormat($aRow['response'][0]['pcr_reagent_exp_date_3']);
                    }

                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['testPlatformName1'], PHPExcel_Cell_DataType::TYPE_STRING);

                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['name_of_pcr_reagent_1'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['pcr_reagent_lot_no_1'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['pcr_reagent_exp_date_1'], PHPExcel_Cell_DataType::TYPE_STRING);

                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['lot_no_1'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['exp_date_1'], PHPExcel_Cell_DataType::TYPE_STRING);

                    for ($k = 0; $k < $aRow['number_of_samples']; $k++) {
                        //$row[] = $aRow[$k]['testResult1'];
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][$k]['testResult1'], PHPExcel_Cell_DataType::TYPE_STRING);
                    }
                    if ($maximumAllowed >= 2) {
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['testPlatformName2'], PHPExcel_Cell_DataType::TYPE_STRING);

                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['name_of_pcr_reagent_2'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['pcr_reagent_lot_no_2'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['pcr_reagent_exp_date_2'], PHPExcel_Cell_DataType::TYPE_STRING);

                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['lot_no_2'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['exp_date_2'], PHPExcel_Cell_DataType::TYPE_STRING);
                        for ($k = 0; $k < $aRow['number_of_samples']; $k++) {
                            //$row[] = $aRow[$k]['testResult2'];
                            $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][$k]['testResult2'], PHPExcel_Cell_DataType::TYPE_STRING);
                        }
                    }

                    if ($maximumAllowed == 3) {
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['testPlatformName3'], PHPExcel_Cell_DataType::TYPE_STRING);

                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['name_of_pcr_reagent_3'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['pcr_reagent_lot_no_3'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['pcr_reagent_exp_date_3'], PHPExcel_Cell_DataType::TYPE_STRING);

                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['lot_no_3'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][0]['exp_date_3'], PHPExcel_Cell_DataType::TYPE_STRING);

                        for ($k = 0; $k < $aRow['number_of_samples']; $k++) {
                            //$row[] = $aRow[$k]['testResult3'];
                            $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][$k]['testResult3'], PHPExcel_Cell_DataType::TYPE_STRING);
                        }
                    }

                    for ($f = 0; $f < $aRow['number_of_samples']; $f++) {
                        //$row[] = $aRow[$f]['finalResult'];
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][$f]['finalResult'], PHPExcel_Cell_DataType::TYPE_STRING);

                        $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($aRow['response'][$f]['finalResult'], PHPExcel_Cell_DataType::TYPE_STRING);
                        if (isset($aRow['response'][$f]['calculated_score']) && $aRow['response'][$f]['calculated_score'] == 'Pass' && $aRow['response'][$f]['sample_id'] == $refResult[$f]['sample_id']) {
                            $countCorrectResult++;
                        }
                    }
                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['user_comment'], PHPExcel_Cell_DataType::TYPE_STRING);

                    $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($countCorrectResult, PHPExcel_Cell_DataType::TYPE_STRING);

                    $totPer = round((($countCorrectResult / $aRow['number_of_samples']) * 100), 2);
                    $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($totPer, PHPExcel_Cell_DataType::TYPE_STRING);

                    $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($countCorrectResult, PHPExcel_Cell_DataType::TYPE_STRING);
                    $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($totPer, PHPExcel_Cell_DataType::TYPE_STRING);

                    $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(($totPer * 0.9), PHPExcel_Cell_DataType::TYPE_STRING);
                }
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($documentScore, PHPExcel_Cell_DataType::TYPE_STRING);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['documentation_score'], PHPExcel_Cell_DataType::TYPE_STRING);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(($aRow['shipment_score'] + $aRow['documentation_score']), PHPExcel_Cell_DataType::TYPE_STRING);

                for ($i = 0; $i < $panelScoreHeadingCount; $i++) {
                    $cellName = $sheetThree->getCellByColumnAndRow($i, $sheetThreeRow)->getColumn();
                    $sheetThree->getStyle($cellName . $sheetThreeRow)->applyFromArray($borderStyle);
                }

                for ($i = 0; $i < $n; $i++) {
                    $cellName = $sheet->getCellByColumnAndRow($i, $currentRow)->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
                }

                /* for ($i = 0; $i < $docScoreHeadingsCount; $i++) {
                    $cellName = $docScoreSheet->getCellByColumnAndRow($i, $docScoreRow)->getColumn();
                    $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);
                } */

                for ($i = 0; $i < $totScoreHeadingsCount; $i++) {
                    $cellName = $totalScoreSheet->getCellByColumnAndRow($i, $totScoreRow)->getColumn();
                    $totalScoreSheet->getStyle($cellName . $totScoreRow)->applyFromArray($borderStyle);
                }

                $currentRow++;

                $sheetThreeRow++;
                $docScoreRow++;
                $totScoreRow++;
            }
        }

        //----------- Second Sheet End----->

        $excel->setActiveSheetIndex(0);

        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $filename = $shipmentCode . '-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }

    public function generateDtsViralLoadExcelReport($shipmentId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $excel = new PHPExcel();

        $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
        $cacheSettings = array('memoryCacheSize' => '180MB');

        $styleArray = array(
            'font' => array(
                'bold' => true,
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                ),
            )
        );

        $boldStyleArray = array(
            'font' => array(
                'bold' => true,
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            )
        );

        $borderStyle = array(
            'font' => array(
                'bold' => true,
                'size'  => 12,
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                ),
            )
        );
        $vlBorderStyle = array(
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                ),
            )
        );


        $query = $db->select()->from('shipment')
            ->where("shipment_id = ?", $shipmentId);
        $result = $db->fetchRow($query);

        $shipmentAttributes = json_decode($result['shipment_attributes'], true);
        $methodOfEvaluation = isset($shipmentAttributes['methodOfEvaluation']) ? $shipmentAttributes['methodOfEvaluation'] : 'standard';

        $refQuery = $db->select()->from(array('refRes' => 'reference_result_vl'))->where("refRes.shipment_id = ?", $shipmentId)->where("refRes.control!=1");
        $refResult = $db->fetchAll($refQuery);

        $colNamesArray = array();
        $colNamesArray[] = "Participant ID";
        //$colNamesArray[] = "Lab Name";
        //$colNamesArray[] = "Department Name";
        //$colNamesArray[] = "Region";
        //$colNamesArray[] = "Site Type";
        //$colNamesArray[] = "Assay";
        //$colNamesArray[] = "Assay Expiration Date";
        //$colNamesArray[] = "Assay Lot Number";
        //$colNamesArray[] = "Specimen Volume";

        $firstSheet = new PHPExcel_Worksheet($excel, 'Overall Results');
        $excel->addSheet($firstSheet, 0);

        $firstSheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode("Participant ID", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(1, 1)->setValueExplicit(html_entity_decode("Participant Name", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(2, 1)->setValueExplicit(html_entity_decode("Country", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(3, 1)->setValueExplicit(html_entity_decode("Response Status", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        //$firstSheet->getCellByColumnAndRow(4, 1)->setValueExplicit(html_entity_decode("Site Type", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        //$firstSheet->getCellByColumnAndRow(5, 1)->setValueExplicit(html_entity_decode("Assay", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        //$firstSheet->getCellByColumnAndRow(6, 1)->setValueExplicit(html_entity_decode("Assay Expiration Date", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        //$firstSheet->getCellByColumnAndRow(7, 1)->setValueExplicit(html_entity_decode("Assay Lot Number", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        //$firstSheet->getCellByColumnAndRow(8, 1)->setValueExplicit(html_entity_decode("Specimen Volume", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $firstSheet->getStyleByColumnAndRow(0, 1)->applyFromArray($borderStyle);
        $firstSheet->getStyleByColumnAndRow(1, 1)->applyFromArray($borderStyle);
        $firstSheet->getStyleByColumnAndRow(2, 1)->applyFromArray($borderStyle);
        $firstSheet->getStyleByColumnAndRow(3, 1)->applyFromArray($borderStyle);
        //$firstSheet->getStyleByColumnAndRow(4, 1)->applyFromArray($borderStyle);
        //$firstSheet->getStyleByColumnAndRow(5, 1)->applyFromArray($borderStyle);
        //$firstSheet->getStyleByColumnAndRow(6, 1)->applyFromArray($borderStyle);
        //$firstSheet->getStyleByColumnAndRow(7, 1)->applyFromArray($borderStyle);
        //$firstSheet->getStyleByColumnAndRow(8, 1)->applyFromArray($borderStyle);

        $firstSheet->getDefaultRowDimension()->setRowHeight(15);

        $colNameCount = 4;
        foreach ($refResult as $refRow) {
            $colNamesArray[] = $refRow['sample_label'];
            if ($methodOfEvaluation == 'iso17043') {
                $colNamesArray[] = "z Score for " . $refRow['sample_label'];

                $colNamesArray[] = "Grade for " . $refRow['sample_label'];
            }
            $firstSheet->getCellByColumnAndRow($colNameCount, 1)->setValueExplicit(html_entity_decode($refRow['sample_label'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getStyleByColumnAndRow($colNameCount, 1)->applyFromArray($borderStyle);
            $colNameCount++;
        }

        $firstSheet->getStyleByColumnAndRow($colNameCount, 1)->applyFromArray($borderStyle);
        $firstSheet->getCellByColumnAndRow($colNameCount++, 1)->setValueExplicit(html_entity_decode("Final Score", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $colNamesArray[] = "Final Score";


        $firstSheet->getStyleByColumnAndRow($colNameCount, 1)->applyFromArray($borderStyle);
        $firstSheet->getCellByColumnAndRow($colNameCount++, 1)->setValueExplicit(html_entity_decode("Date Received", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $colNamesArray[] = "Date Received";

        $firstSheet->getStyleByColumnAndRow($colNameCount, 1)->applyFromArray($borderStyle);
        $firstSheet->getCellByColumnAndRow($colNameCount++, 1)->setValueExplicit(html_entity_decode("Date Tested", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $colNamesArray[] = "Date Tested";


        $firstSheet->getStyleByColumnAndRow($colNameCount, 1)->applyFromArray($borderStyle);
        $firstSheet->getCellByColumnAndRow($colNameCount++, 1)->setValueExplicit(html_entity_decode("Assay", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $colNamesArray[] = "Assay";

        $firstSheet->getStyleByColumnAndRow($colNameCount, 1)->applyFromArray($borderStyle);
        $firstSheet->getCellByColumnAndRow($colNameCount++, 1)->setValueExplicit(html_entity_decode("Institute Name", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $colNamesArray[] = "Institute Name";
        $firstSheet->getCellByColumnAndRow($colNameCount++, 1)->setValueExplicit(html_entity_decode("Department Name", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $colNamesArray[] = "Department Name";
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1)->applyFromArray($borderStyle);
        $firstSheet->getCellByColumnAndRow($colNameCount++, 1)->setValueExplicit(html_entity_decode("Region", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $colNamesArray[] = "Region";
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1)->applyFromArray($borderStyle);
        $firstSheet->getCellByColumnAndRow($colNameCount++, 1)->setValueExplicit(html_entity_decode("Site Type", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $colNamesArray[] = "Site Type";
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1)->applyFromArray($borderStyle);
        $firstSheet->getCellByColumnAndRow($colNameCount++, 1)->setValueExplicit(html_entity_decode("Assay Expiration Date", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $colNamesArray[] = "Assay Expiration Date";
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1)->applyFromArray($borderStyle);
        $firstSheet->getCellByColumnAndRow($colNameCount++, 1)->setValueExplicit(html_entity_decode("Assay Lot Number", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $colNamesArray[] = "Assay Lot Number";
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1)->applyFromArray($borderStyle);
        $firstSheet->getCellByColumnAndRow($colNameCount++, 1)->setValueExplicit(html_entity_decode("Specimen Volume", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $colNamesArray[] = "Specimen Volume";
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1)->applyFromArray($borderStyle);
        $firstSheet->getCellByColumnAndRow($colNameCount++, 1)->setValueExplicit(html_entity_decode("Supervisor Name", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $colNamesArray[] = "Supervisor Name";
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1)->applyFromArray($borderStyle);
        $firstSheet->getCellByColumnAndRow($colNameCount++, 1)->setValueExplicit(html_entity_decode("Participant Comment", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $colNamesArray[] = "Participant Comments";

        $firstSheet->setTitle('OVERALL');

        $queryOverAll = $db->select()->from(array('s' => 'shipment'))
            ->joinLeft(array('spm' => 'shipment_participant_map'), "spm.shipment_id = s.shipment_id")
            ->joinLeft(array('p' => 'participant'), "p.participant_id = spm.participant_id")
            ->joinLeft(array('c' => 'countries'), "c.id = p.country", array('country_name' => 'iso_name'))
            ->joinLeft(array('st' => 'r_site_type'), "st.r_stid=p.site_type")
            ->where("s.shipment_id = ?", $shipmentId);

        $resultOverAll = $db->fetchAll($queryOverAll);

        $row = 1; // $row 0 is already the column headings

        $schemeService = new Application_Service_Schemes();
        $assayList = $schemeService->getVlAssay();

        $assayWiseData = array();

        foreach ($resultOverAll as $rowOverAll) {
            $row++;

            $queryResponse = $db->select()
                ->from(array('res' => 'response_result_vl'))
                ->joinLeft(array('refRes' => 'reference_result_vl'), "refRes.sample_id = res.sample_id")
                ->where("refRes.control!=1")
                ->where("refRes.shipment_id = ?", $shipmentId)
                ->where("res.shipment_map_id = ?", $rowOverAll['map_id']);
            //echo $queryResponse;
            $resultResponse = $db->fetchAll($queryResponse);

            $attributes = json_decode($rowOverAll['attributes'], true);

            if (isset($attributes['other_assay']) && $attributes['other_assay'] != "") {
                $assayName = "Other - " . $attributes['other_assay'];
            } else {
                $assayName = (array_key_exists($attributes['vl_assay'], $assayList)) ? $assayList[$attributes['vl_assay']] : "";
            }

            $assayExpirationDate = "";
            if (isset($attributes['assay_expiration_date']) && $attributes['assay_expiration_date'] != "") {
                $assayExpirationDate = Pt_Commons_General::humanDateFormat($attributes['assay_expiration_date']);
            }

            $assayLotNumber = "";
            if (isset($attributes['assay_lot_number']) && $attributes['assay_lot_number'] != "") {
                $assayLotNumber = ($attributes['assay_lot_number']);
            }

            $specimenVolume = "";
            if (isset($attributes['specimen_volume']) && $attributes['specimen_volume'] != "") {
                $specimenVolume = ($attributes['specimen_volume']);
            }
            // we are also building the data required for other Assay Sheets
            if ($attributes['vl_assay'] > 0) {
                $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['unique_identifier'];
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['first_name']." ".$rowOverAll['last_name'];
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = ucwords($rowOverAll['institute_name']);
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = ucwords($rowOverAll['department_name']);
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['region'];
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['site_type'];
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $assayName;
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $assayExpirationDate;
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $assayLotNumber;
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $specimenVolume;
            }

            $firstSheet->getCellByColumnAndRow(0, $row)->setValueExplicit(html_entity_decode($rowOverAll['unique_identifier'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(1, $row)->setValueExplicit(utf8_encode($rowOverAll['lab_name']), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(2, $row)->setValueExplicit(html_entity_decode($rowOverAll['country_name'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

            //$firstSheet->getCellByColumnAndRow(4, $row)->setValueExplicit(html_entity_decode($rowOverAll['site_type'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            //$firstSheet->getCellByColumnAndRow(5, $row)->setValueExplicit(html_entity_decode($assayName, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            //$firstSheet->getCellByColumnAndRow(6, $row)->setValueExplicit(html_entity_decode($assayExpirationDate, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            //$firstSheet->getCellByColumnAndRow(7, $row)->setValueExplicit(html_entity_decode($assayLotNumber, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            //$firstSheet->getCellByColumnAndRow(8, $row)->setValueExplicit(html_entity_decode($specimenVolume, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);


            $col = 4;
            if ($rowOverAll['is_pt_test_not_performed'] == 'yes') {
                $firstSheet->getCellByColumnAndRow(3, $row)->setValueExplicit(html_entity_decode("PT TEST NOT PERFORMED", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                $col = 4 + count($refResult);
            } else if (count($resultResponse) > 0) {
                $firstSheet->getCellByColumnAndRow(3, $row)->setValueExplicit(html_entity_decode("Responded", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                foreach ($resultResponse as $responseRow) {
                    $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($responseRow['reported_viral_load'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    // we are also building the data required for other Assay Sheets
                    if ($attributes['vl_assay'] > 0) {
                        $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $responseRow['reported_viral_load'];
                        if ($methodOfEvaluation == 'iso17043') {
                            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $responseRow['z_score'];
                            if (isset($responseRow['calculated_score']) && $responseRow['calculated_score'] == 'pass') {
                                $grade = 'Acceptable';
                            } else if (isset($responseRow['calculated_score']) && $responseRow['calculated_score'] == 'fail') {
                                $grade = 'Unacceptable';
                            } else if (isset($responseRow['calculated_score']) && $responseRow['calculated_score'] == 'warn') {
                                $grade = 'Warning';
                            } else {
                                $grade = 'N.A.';
                            }
                            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $grade;
                        }
                    }
                }
            } else {
                $firstSheet->getCellByColumnAndRow(3, $row)->setValueExplicit(html_entity_decode("Not Responded", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                $col = 4 + count($refResult);
            }


            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit($rowOverAll['shipment_score'], PHPExcel_Cell_DataType::TYPE_STRING);

            $receiptDate = ($rowOverAll['shipment_receipt_date'] != "" && $rowOverAll['shipment_receipt_date'] != "0000-00-00") ? Pt_Commons_General::humanDateFormat($rowOverAll['shipment_receipt_date']) : "";
            $testDate = ($rowOverAll['shipment_test_date'] != "" && $rowOverAll['shipment_test_date'] != "0000-00-00") ? Pt_Commons_General::humanDateFormat($rowOverAll['shipment_test_date']) : "";
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($receiptDate, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($testDate, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

            // we are also building the data required for other Assay Sheets
            if ($attributes['vl_assay'] > 0) {
                $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['shipment_score'];
                $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $receiptDate;
                $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $testDate;
            }


            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($assayName, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode(ucwords($rowOverAll['institute_name']), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode(ucwords($rowOverAll['department_name']), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($rowOverAll['region'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($rowOverAll['site_type'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($assayExpirationDate, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($assayLotNumber, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($specimenVolume, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($rowOverAll['participant_supervisor'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($rowOverAll['user_comment'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $assayName;
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['institute_name'];
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['department_name'];
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['region'];
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['site_type'];
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $assayExpirationDate;
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $assayLotNumber;
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $specimenVolume;
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['participant_supervisor'];
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['user_comment'];


            if ($rowOverAll['is_pt_test_not_performed'] == 'yes') {
                unset($assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']]);
            }
        }


        foreach (range('A', 'Z') as $columnID) {
            $firstSheet->getColumnDimension($columnID)
                ->setAutoSize(true);
        }
        //Zend_Debug::dump($assayWiseData);die;

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $assayRes = $db->fetchAll($db->select()->from('r_vl_assay')->where("`status` like 'active'"));

        $countOfVlAssaySheet = 1;
        foreach ($assayRes as $assayRow) {
            $newsheet = new PHPExcel_Worksheet($excel, '');
            $excel->addSheet($newsheet, $countOfVlAssaySheet);

            $newsheet->getDefaultRowDimension()->setRowHeight(15);


            foreach (range('A', 'Z') as $columnID) {
                $newsheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            $i = 0;
            $startAt = 28;
            foreach ($colNamesArray as $colName) {
                $newsheet->getCellByColumnAndRow($i, $startAt)->setValueExplicit(html_entity_decode($colName, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                $newsheet->getStyleByColumnAndRow($i, $startAt)->applyFromArray($borderStyle);
                $i++;
            }
            //get vl_assay wise low high limit
            $refVlCalci = $db->fetchAll($db->select()->from(array('rvc' => 'reference_vl_calculation'))
                ->join(array('rrv' => 'reference_result_vl'), 'rrv.sample_id=rvc.sample_id AND rrv.shipment_id=' . $result['shipment_id'], array('sample_label'))
                ->where('rvc.shipment_id=' . $result['shipment_id'])->where('rvc.vl_assay=' . $assayRow['id'])
                ->where('rrv.control!=1'));
            if (count($refVlCalci) > 0) {

                if ($methodOfEvaluation == 'standard') {


                    //write in excel low and high limit title
                    $newsheet->mergeCells('A1:F1');
                    $newsheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode('System Generated', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 2)->setValueExplicit(html_entity_decode('Sample', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 3)->setValueExplicit(html_entity_decode('Q1', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 4)->setValueExplicit(html_entity_decode('Q3', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 5)->setValueExplicit(html_entity_decode('IQR', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 6)->setValueExplicit(html_entity_decode('Quartile Low', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 7)->setValueExplicit(html_entity_decode('Quartile High', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 8)->setValueExplicit(html_entity_decode('Mean', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 9)->setValueExplicit(html_entity_decode('SD', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 10)->setValueExplicit(html_entity_decode('CV', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 11)->setValueExplicit(html_entity_decode('Low Limit', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 12)->setValueExplicit(html_entity_decode('High Limit', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

                    $newsheet->getStyleByColumnAndRow(0, 1)->applyFromArray($boldStyleArray);
                    $newsheet->getStyleByColumnAndRow(0, 2)->applyFromArray($styleArray);
                    $newsheet->getStyleByColumnAndRow(0, 3)->applyFromArray($styleArray);
                    $newsheet->getStyleByColumnAndRow(0, 4)->applyFromArray($styleArray);
                    $newsheet->getStyleByColumnAndRow(0, 5)->applyFromArray($styleArray);
                    $newsheet->getStyleByColumnAndRow(0, 6)->applyFromArray($styleArray);
                    $newsheet->getStyleByColumnAndRow(0, 7)->applyFromArray($styleArray);
                    $newsheet->getStyleByColumnAndRow(0, 8)->applyFromArray($styleArray);
                    $newsheet->getStyleByColumnAndRow(0, 9)->applyFromArray($styleArray);
                    $newsheet->getStyleByColumnAndRow(0, 10)->applyFromArray($styleArray);
                    $newsheet->getStyleByColumnAndRow(0, 11)->applyFromArray($styleArray);
                    $newsheet->getStyleByColumnAndRow(0, 12)->applyFromArray($styleArray);

                    $k = 1;
                    $manual = array();
                    foreach ($refVlCalci as $calculation) {
                        $newsheet->getCellByColumnAndRow($k, 2)->setValueExplicit(html_entity_decode($calculation['sample_label'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k, 3)->setValueExplicit(html_entity_decode(round($calculation['q1'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k, 4)->setValueExplicit(html_entity_decode(round($calculation['q3'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k, 5)->setValueExplicit(html_entity_decode(round($calculation['iqr'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k, 6)->setValueExplicit(html_entity_decode(round($calculation['quartile_low'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k, 7)->setValueExplicit(html_entity_decode(round($calculation['quartile_high'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k, 8)->setValueExplicit(html_entity_decode(round($calculation['mean'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k, 9)->setValueExplicit(html_entity_decode(round($calculation['sd'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k, 10)->setValueExplicit(html_entity_decode(round($calculation['cv'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k, 11)->setValueExplicit(html_entity_decode(round($calculation['low_limit'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k, 12)->setValueExplicit(html_entity_decode(round($calculation['high_limit'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

                        $newsheet->getStyleByColumnAndRow($k, 2)->applyFromArray($vlBorderStyle);
                        $newsheet->getStyleByColumnAndRow($k, 3)->applyFromArray($vlBorderStyle);
                        $newsheet->getStyleByColumnAndRow($k, 4)->applyFromArray($vlBorderStyle);
                        $newsheet->getStyleByColumnAndRow($k, 5)->applyFromArray($vlBorderStyle);
                        $newsheet->getStyleByColumnAndRow($k, 6)->applyFromArray($vlBorderStyle);
                        $newsheet->getStyleByColumnAndRow($k, 7)->applyFromArray($vlBorderStyle);
                        $newsheet->getStyleByColumnAndRow($k, 8)->applyFromArray($vlBorderStyle);
                        $newsheet->getStyleByColumnAndRow($k, 9)->applyFromArray($vlBorderStyle);
                        $newsheet->getStyleByColumnAndRow($k, 10)->applyFromArray($vlBorderStyle);
                        $newsheet->getStyleByColumnAndRow($k, 11)->applyFromArray($vlBorderStyle);
                        $newsheet->getStyleByColumnAndRow($k, 12)->applyFromArray($vlBorderStyle);
                        if ($calculation['manual_mean'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_sd'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_low_limit'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_high_limit'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_cv'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_q1'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_q3'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_iqr'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_quartile_low'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_quartile_high'] != 0) {
                            $manual[] = 'yes';
                        }
                        $k++;
                    }
                    if (count($manual) > 0) {
                        $newsheet->mergeCells('A15:F15');
                        $newsheet->getCellByColumnAndRow(0, 15)->setValueExplicit(html_entity_decode('Manual Generated', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(0, 16)->setValueExplicit(html_entity_decode('Sample', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(0, 17)->setValueExplicit(html_entity_decode('Manual Q1', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(0, 18)->setValueExplicit(html_entity_decode('Manual Q3', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(0, 19)->setValueExplicit(html_entity_decode('Manual IQR', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(0, 20)->setValueExplicit(html_entity_decode('Manual Quartile Low', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(0, 21)->setValueExplicit(html_entity_decode('Manual Quartile High', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(0, 22)->setValueExplicit(html_entity_decode('Manual Mean', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(0, 23)->setValueExplicit(html_entity_decode('Manual SD', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(0, 24)->setValueExplicit(html_entity_decode('Manual CV', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(0, 25)->setValueExplicit(html_entity_decode('Manual Low Limit', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(0, 26)->setValueExplicit(html_entity_decode('Manual High Limit', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

                        $newsheet->getStyleByColumnAndRow(0, 15)->applyFromArray($boldStyleArray);
                        $newsheet->getStyleByColumnAndRow(0, 16)->applyFromArray($styleArray);
                        $newsheet->getStyleByColumnAndRow(0, 17)->applyFromArray($styleArray);
                        $newsheet->getStyleByColumnAndRow(0, 18)->applyFromArray($styleArray);
                        $newsheet->getStyleByColumnAndRow(0, 19)->applyFromArray($styleArray);
                        $newsheet->getStyleByColumnAndRow(0, 20)->applyFromArray($styleArray);
                        $newsheet->getStyleByColumnAndRow(0, 21)->applyFromArray($styleArray);
                        $newsheet->getStyleByColumnAndRow(0, 22)->applyFromArray($styleArray);
                        $newsheet->getStyleByColumnAndRow(0, 23)->applyFromArray($styleArray);
                        $newsheet->getStyleByColumnAndRow(0, 24)->applyFromArray($styleArray);
                        $newsheet->getStyleByColumnAndRow(0, 25)->applyFromArray($styleArray);
                        $newsheet->getStyleByColumnAndRow(0, 26)->applyFromArray($styleArray);
                        $k = 1;
                        foreach ($refVlCalci as $calculation) {
                            $newsheet->getCellByColumnAndRow($k, 16)->setValueExplicit(html_entity_decode($calculation['sample_label'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k, 17)->setValueExplicit(html_entity_decode(round($calculation['manual_q1'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k, 18)->setValueExplicit(html_entity_decode(round($calculation['manual_q3'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k, 19)->setValueExplicit(html_entity_decode(round($calculation['manual_iqr'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k, 20)->setValueExplicit(html_entity_decode(round($calculation['manual_quartile_low'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k, 21)->setValueExplicit(html_entity_decode(round($calculation['manual_quartile_high'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k, 22)->setValueExplicit(html_entity_decode(round($calculation['manual_mean'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k, 23)->setValueExplicit(html_entity_decode(round($calculation['manual_sd'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k, 24)->setValueExplicit(html_entity_decode(round($calculation['manual_cv'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k, 25)->setValueExplicit(html_entity_decode(round($calculation['manual_low_limit'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k, 26)->setValueExplicit(html_entity_decode(round($calculation['manual_high_limit'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

                            $newsheet->getStyleByColumnAndRow($k, 16)->applyFromArray($vlBorderStyle);
                            $newsheet->getStyleByColumnAndRow($k, 17)->applyFromArray($vlBorderStyle);
                            $newsheet->getStyleByColumnAndRow($k, 18)->applyFromArray($vlBorderStyle);
                            $newsheet->getStyleByColumnAndRow($k, 19)->applyFromArray($vlBorderStyle);
                            $newsheet->getStyleByColumnAndRow($k, 20)->applyFromArray($vlBorderStyle);
                            $newsheet->getStyleByColumnAndRow($k, 21)->applyFromArray($vlBorderStyle);
                            $newsheet->getStyleByColumnAndRow($k, 22)->applyFromArray($vlBorderStyle);
                            $newsheet->getStyleByColumnAndRow($k, 23)->applyFromArray($vlBorderStyle);
                            $newsheet->getStyleByColumnAndRow($k, 24)->applyFromArray($vlBorderStyle);
                            $newsheet->getStyleByColumnAndRow($k, 25)->applyFromArray($vlBorderStyle);
                            $newsheet->getStyleByColumnAndRow($k, 26)->applyFromArray($vlBorderStyle);

                            $k++;
                        }
                    }
                } else if ($methodOfEvaluation == 'iso17043') {
                    $newsheet->mergeCells('A1:F1');
                    $newsheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode('System Generated', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 2)->setValueExplicit(html_entity_decode('Sample', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 3)->setValueExplicit(html_entity_decode('Median', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 4)->setValueExplicit(html_entity_decode('Upper Limit (Q3)', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 5)->setValueExplicit(html_entity_decode('Lower Limit (Q1)', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 6)->setValueExplicit(html_entity_decode('Robust SD', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 7)->setValueExplicit(html_entity_decode('Standard Uncertainty', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(0, 8)->setValueExplicit(html_entity_decode('Is Uncertainty Acceptable?', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

                    $newsheet->getStyleByColumnAndRow(0, 1)->applyFromArray($boldStyleArray);
                    $newsheet->getStyleByColumnAndRow(0, 2)->applyFromArray($styleArray);
                    $newsheet->getStyleByColumnAndRow(0, 3)->applyFromArray($styleArray);
                    $newsheet->getStyleByColumnAndRow(0, 4)->applyFromArray($styleArray);
                    $newsheet->getStyleByColumnAndRow(0, 5)->applyFromArray($styleArray);
                    $newsheet->getStyleByColumnAndRow(0, 6)->applyFromArray($styleArray);
                    $newsheet->getStyleByColumnAndRow(0, 7)->applyFromArray($styleArray);
                    $newsheet->getStyleByColumnAndRow(0, 8)->applyFromArray($styleArray);

                    $k = 1;
                    $manual = array();
                    foreach ($refVlCalci as $calculation) {
                        $newsheet->getCellByColumnAndRow($k, 2)->setValueExplicit(html_entity_decode($calculation['sample_label'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k, 3)->setValueExplicit(html_entity_decode(round($calculation['median'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k, 4)->setValueExplicit(html_entity_decode(round($calculation['q3'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k, 5)->setValueExplicit(html_entity_decode(round($calculation['q1'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k, 6)->setValueExplicit(html_entity_decode(round($calculation['sd'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k, 7)->setValueExplicit(html_entity_decode(round($calculation['standard_uncertainty'], 4), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k, 8)->setValueExplicit(html_entity_decode($calculation['is_uncertainty_acceptable'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);


                        $newsheet->getStyleByColumnAndRow($k, 2)->applyFromArray($vlBorderStyle);
                        $newsheet->getStyleByColumnAndRow($k, 3)->applyFromArray($vlBorderStyle);
                        $newsheet->getStyleByColumnAndRow($k, 4)->applyFromArray($vlBorderStyle);
                        $newsheet->getStyleByColumnAndRow($k, 5)->applyFromArray($vlBorderStyle);
                        $newsheet->getStyleByColumnAndRow($k, 6)->applyFromArray($vlBorderStyle);
                        $newsheet->getStyleByColumnAndRow($k, 7)->applyFromArray($vlBorderStyle);
                        $newsheet->getStyleByColumnAndRow($k, 8)->applyFromArray($vlBorderStyle);


                        $k++;
                    }
                }
            }
            //

            $assayData = isset($assayWiseData[$assayRow['id']]) ? $assayWiseData[$assayRow['id']] : array();
            //var_dump($assayData);die;
            $newsheet->setTitle(strtoupper($assayRow['short_name']));
            $row = $startAt; // $row 1-$startAt already occupied

            foreach ($assayData as $assayKey => $assayRow) {
                $row++;
                $noOfCols = count($assayRow);
                for ($c = 0; $c < $noOfCols; $c++) {
                    $newsheet->getCellByColumnAndRow($c, $row)->setValueExplicit(html_entity_decode($assayRow[$c], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    $newsheet->getStyleByColumnAndRow($c, $row)->applyFromArray($vlBorderStyle);
                }
            }

            $countOfVlAssaySheet++;
        }

        //var_dump($assayList);die;

        $excel->setActiveSheetIndex(0);

        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $filename = $result['shipment_code'] . '-' . date('d-M-Y-H-i-s') . rand() . '.xlsx';
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }


    public function generateDbsEidExcelReport($shipmentId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $excel = new PHPExcel();

        $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
        $cacheSettings = array('memoryCacheSize' => '180MB');

        $styleArray = array(
            'font' => array(
                'bold' => true,
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                ),
            )
        );

        $borderStyle = array(
            'font' => array(
                'bold' => true,
                'size'  => 12,
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                ),
            )
        );
        $patientResponseColor = array(
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => '18bc9c')
            )
        );
        $referenceColor = array(
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'F0E68C')
            )
        );

        $query = $db->select()->from('shipment')
            ->where("shipment_id = ?", $shipmentId);
        $result = $db->fetchRow($query);


        $refQuery = $db->select()->from(array('refRes' => 'reference_result_eid'))
            ->where("refRes.shipment_id = ?", $shipmentId);
        $refResult = $db->fetchAll($refQuery);

        $firstSheet = new PHPExcel_Worksheet($excel, 'EID PT Results');
        $excel->addSheet($firstSheet, 0);

        $firstSheet->mergeCells('A1:A2');
        $firstSheet->setCellValue('A1', html_entity_decode("Lab ID", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('A1:A2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('B1:B2');
        $firstSheet->setCellValue('B1', html_entity_decode("Lab Name", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('B1:B2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('C1:C2');
        $firstSheet->setCellValue('C1', html_entity_decode("Institute", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('C1:C2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('D1:D2');
        $firstSheet->setCellValue('D1', html_entity_decode("Department", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('D1:D2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('E1:E2');
        $firstSheet->setCellValue('E1', html_entity_decode("Region", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('E1:E2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('F1:F2');
        $firstSheet->setCellValue('F1', html_entity_decode("Site Type", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('F1:F2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('G1:G2');
        $firstSheet->setCellValue('G1', html_entity_decode("Sample Rehydration Date", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('G1:G2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('H1:H2');
        $firstSheet->setCellValue('H1', html_entity_decode("Extraction", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('H1:H2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('I1:I2');
        $firstSheet->setCellValue('I1', html_entity_decode("Detection", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('I1:I2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('J1:J2');
        $firstSheet->setCellValue('J1', html_entity_decode("Date Received", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('J1:J2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('K1:K2');
        $firstSheet->setCellValue('K1', html_entity_decode("Date Tested", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('K1:K2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('L1:L2');
        $firstSheet->setCellValue('L1', html_entity_decode("Response Status", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('L1:L2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('M1:M2');
        $firstSheet->setCellValue('M1', html_entity_decode("Final Score", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('M1:M2')->applyFromArray($borderStyle);

        $firstSheet->getDefaultRowDimension()->setRowHeight(15);

        $colNameCount = 13;
        $cellName1 = $firstSheet->getCellByColumnAndRow($colNameCount)->getColumn();

        foreach ($refResult as $refRow) {
            $firstSheet->getCellByColumnAndRow($colNameCount, 2)->setValueExplicit(html_entity_decode($refRow['sample_label'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getStyleByColumnAndRow($colNameCount, 2)->applyFromArray($borderStyle);
            $colNameCount++;
        }

        $cellName2 = $firstSheet->getCellByColumnAndRow($colNameCount - 1)->getColumn();
        $firstSheet->mergeCells($cellName1 . '1:' . $cellName2 . '1');
        $firstSheet->setCellValue($cellName1 . '1', html_entity_decode("PARTICIPANT RESPONSE", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle($cellName1 . '1:' . $cellName2 . '1')->applyFromArray($borderStyle);
        $firstSheet->getStyle($cellName1 . '1:' . $cellName2 . '2')->applyFromArray($patientResponseColor);

        $cellName3 = $firstSheet->getCellByColumnAndRow($colNameCount)->getColumn();
        $colNumberforReference = $colNameCount;
        foreach ($refResult as $refRow) {
            $firstSheet->getCellByColumnAndRow($colNameCount, 2)->setValueExplicit(html_entity_decode($refRow['sample_label'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getStyleByColumnAndRow($colNameCount, 2)->applyFromArray($borderStyle);
            $colNameCount++;
        }
        $cellName4 = $firstSheet->getCellByColumnAndRow($colNameCount - 1)->getColumn();
        $firstSheet->mergeCells($cellName3 . '1:' . $cellName4 . '1');
        $firstSheet->setCellValue($cellName3 . '1', html_entity_decode("REFERENCE RESULTS", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle($cellName3 . '1:' . $cellName4 . '1')->applyFromArray($borderStyle);
        $firstSheet->getStyle($cellName3 . '1:' . $cellName4 . '2')->applyFromArray($referenceColor);


        $firstSheet->setTitle('EID PT Results');

        $queryOverAll = $db->select()->from(array('s' => 'shipment'))
            ->joinLeft(array('spm' => 'shipment_participant_map'), "spm.shipment_id = s.shipment_id")
            ->joinLeft(array('p' => 'participant'), "p.participant_id = spm.participant_id")
            ->joinLeft(array('st' => 'r_site_type'), "st.r_stid=p.site_type")
            ->where("s.shipment_id = ?", $shipmentId);
        $resultOverAll = $db->fetchAll($queryOverAll);

        $row = 2; // $row 0 is already the column headings

        $schemeService = new Application_Service_Schemes();
        $extractionAssayList = $schemeService->getEidExtractionAssay();
        $detectionAssayList = $schemeService->getEidDetectionAssay();

        //Zend_Debug::dump($extractionAssayList);die;

        foreach ($resultOverAll as $rowOverAll) {
            //Zend_Debug::dump($rowOverAll);
            $row++;

            $queryResponse = $db->select()->from(array('res' => 'response_result_eid'))
                ->joinLeft(array('pr' => 'r_possibleresult'), "res.reported_result=pr.id")
                ->where("res.shipment_map_id = ?", $rowOverAll['map_id']);
            $resultResponse = $db->fetchAll($queryResponse);

            $attributes = json_decode($rowOverAll['attributes'], true);
            $extraction = (array_key_exists($attributes['extraction_assay'], $extractionAssayList)) ? $extractionAssayList[$attributes['extraction_assay']] : "";
            $detection = (array_key_exists($attributes['detection_assay'], $detectionAssayList)) ? $detectionAssayList[$attributes['detection_assay']] : "";
            $sampleRehydrationDate = (isset($attributes['sample_rehydration_date'])) ? Pt_Commons_General::humanDateFormat($attributes['sample_rehydration_date']) : "";


            $firstSheet->getCellByColumnAndRow(0, $row)->setValueExplicit(html_entity_decode($rowOverAll['unique_identifier'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(1, $row)->setValueExplicit(html_entity_decode($rowOverAll['first_name'] . " " . $rowOverAll['last_name'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(2, $row)->setValueExplicit(html_entity_decode(ucwords($rowOverAll['institute_name']), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(3, $row)->setValueExplicit(html_entity_decode(ucwords($rowOverAll['department_name']), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(4, $row)->setValueExplicit(html_entity_decode($rowOverAll['region'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(5, $row)->setValueExplicit(html_entity_decode($rowOverAll['site_type'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(6, $row)->setValueExplicit(html_entity_decode($sampleRehydrationDate, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

            $col = 7;

            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($extraction, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($detection, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

            $receiptDate = ($rowOverAll['shipment_receipt_date'] != "" && $rowOverAll['shipment_receipt_date'] != "0000-00-00" && $rowOverAll['shipment_receipt_date'] != "1970-01-01") ? Pt_Commons_General::humanDateFormat($rowOverAll['shipment_receipt_date']) : "";
            $testDate = ($rowOverAll['shipment_test_date'] != "" && $rowOverAll['shipment_test_date'] != "0000-00-00" && $rowOverAll['shipment_test_date'] != "1970-01-01") ? Pt_Commons_General::humanDateFormat($rowOverAll['shipment_test_date']) : "";
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($receiptDate, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($testDate, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            if ($rowOverAll['is_pt_test_not_performed'] == 'yes') {
                $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode("PT Test Not Performed", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            } else if ((isset($rowOverAll['shipment_test_date']) && $rowOverAll['shipment_test_date'] != "0000-00-00" && $rowOverAll['shipment_test_date'] != "")) {
                $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode("Responded", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            } else {
                $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode("Not Responded", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            }

            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($rowOverAll['shipment_score'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

            foreach ($resultResponse as $responseRow) {
                $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($responseRow['response'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            }
        }

        $queryReference = $db->select()->from(array('res' => 'reference_result_eid'))
            ->joinLeft(array('pr' => 'r_possibleresult'), "res.reference_result=pr.id")
            ->where("res.shipment_id = ?", $shipmentId);
        $referenceresult = $db->fetchAll($queryReference);
        $nRow = 3;
        for ($i = 3; $i < $row; $i++) {
            $col = $colNumberforReference;
            foreach ($referenceresult as $referenceRow) {
                $firstSheet->getCellByColumnAndRow($col++, $nRow)->setValueExplicit(html_entity_decode($referenceRow['response'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            }
            $nRow++;
        }

        foreach (range('A', 'Z') as $columnID) {
            $firstSheet->getColumnDimension($columnID)
                ->setAutoSize(true);
        }

        $excel->setActiveSheetIndex(0);

        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $filename = $result['shipment_code'] . '-' . date('d-M-Y-H-i-s') . rand() . '.xlsx';
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }

    public function generateRecencyExcelReport($shipmentId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $excel = new PHPExcel();

        $borderStyle = array(
            'font' => array(
                'bold' => true,
                'size'  => 12,
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                ),
            )
        );
        $patientResponseColor = array(
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => '18bc9c')
            )
        );
        $referenceColor = array(
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'F0E68C')
            )
        );

        $query = $db->select()->from('shipment')
            ->where("shipment_id = ?", $shipmentId);
        $result = $db->fetchRow($query);


        $refQuery = $db->select()->from(array('refRes' => 'reference_result_recency'))
            ->joinLeft(array('pr' => 'r_possibleresult'), "refRes.reference_result=pr.id")
            ->where("refRes.shipment_id = ?", $shipmentId);
        $refResult = $db->fetchAll($refQuery);


        $firstSheet = new PHPExcel_Worksheet($excel, 'Recency PT Results');
        $excel->addSheet($firstSheet, 0);

        $firstSheet->mergeCells('A1:A2');
        $firstSheet->setCellValue('A1', html_entity_decode("Lab ID", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('A1:A2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('B1:B2');
        $firstSheet->setCellValue('B1', html_entity_decode("Lab Name", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('B1:B2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('C1:C2');
        $firstSheet->setCellValue('C1', html_entity_decode("Institute", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('C1:C2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('D1:D2');
        $firstSheet->setCellValue('D1', html_entity_decode("Department", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('D1:D2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('E1:E2');
        $firstSheet->setCellValue('E1', html_entity_decode("Region", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('E1:E2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('F1:F2');
        $firstSheet->setCellValue('F1', html_entity_decode("Site Type", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('F1:F2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('G1:G2');
        $firstSheet->setCellValue('G1', html_entity_decode("Sample Rehydration Date", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('G1:G2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('H1:H2');
        $firstSheet->setCellValue('H1', html_entity_decode("Recency Assay", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('H1:H2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('I1:I2');
        $firstSheet->setCellValue('I1', html_entity_decode("Recency Assay Lot No", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('I1:I2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('J1:J2');
        $firstSheet->setCellValue('J1', html_entity_decode("Date Received", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('J1:J2')->applyFromArray($borderStyle);

        $firstSheet->mergeCells('K1:K2');
        $firstSheet->setCellValue('K1', html_entity_decode("Date Tested", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle('K1:K2')->applyFromArray($borderStyle);

        $firstSheet->getDefaultRowDimension()->setRowHeight(15);

        $colNameCount = 11;
        $cellName1 = $firstSheet->getCellByColumnAndRow($colNameCount)->getColumn();

        foreach ($refResult as $refRow) {
            $firstSheet->getCellByColumnAndRow($colNameCount, 2)->setValueExplicit(html_entity_decode($refRow['sample_label'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getStyleByColumnAndRow($colNameCount, 2)->applyFromArray($borderStyle);
            $colNameCount++;
        }

        $cellName2 = $firstSheet->getCellByColumnAndRow($colNameCount - 1)->getColumn();
        $firstSheet->mergeCells($cellName1 . '1:' . $cellName2 . '1');
        $firstSheet->setCellValue($cellName1 . '1', html_entity_decode("PARTICIPANT RESPONSE", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle($cellName1 . '1:' . $cellName2 . '1')->applyFromArray($borderStyle);
        $firstSheet->getStyle($cellName1 . '1:' . $cellName2 . '2')->applyFromArray($patientResponseColor);

        $cellName3 = $firstSheet->getCellByColumnAndRow($colNameCount)->getColumn();
        $colNumberforReference = $colNameCount;
        foreach ($refResult as $refRow) {
            $firstSheet->getCellByColumnAndRow($colNameCount, 2)->setValueExplicit(html_entity_decode($refRow['sample_label'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getStyleByColumnAndRow($colNameCount, 2)->applyFromArray($borderStyle);
            $colNameCount++;
        }
        $cellName4 = $firstSheet->getCellByColumnAndRow($colNameCount - 1)->getColumn();
        $firstSheet->mergeCells($cellName3 . '1:' . $cellName4 . '1');
        $firstSheet->setCellValue($cellName3 . '1', html_entity_decode("REFERENCE RESULTS", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $firstSheet->getStyle($cellName3 . '1:' . $cellName4 . '1')->applyFromArray($borderStyle);
        $firstSheet->getStyle($cellName3 . '1:' . $cellName4 . '2')->applyFromArray($referenceColor);

        $firstSheet->setTitle('Recency PT Results');

        $queryOverAll = $db->select()->from(array('s' => 'shipment'))
            ->joinLeft(array('spm' => 'shipment_participant_map'), "spm.shipment_id = s.shipment_id")
            ->joinLeft(array('p' => 'participant'), "p.participant_id = spm.participant_id")
            ->joinLeft(array('st' => 'r_site_type'), "st.r_stid=p.site_type")
            ->where("s.shipment_id = ?", $shipmentId);
        $resultOverAll = $db->fetchAll($queryOverAll);

        $row = 2; // $row 0 is already the column headings

        $schemeService = new Application_Service_Schemes();
        $assayList = $schemeService->getRecencyAssay();

        foreach ($resultOverAll as $rowOverAll) {
            //Zend_Debug::dump($rowOverAll);
            $row++;

            $queryResponse = $db->select()->from(array('res' => 'response_result_recency'))
                ->joinLeft(array('pr' => 'r_possibleresult'), "res.reported_result=pr.id")
                ->where("res.shipment_map_id = ?", $rowOverAll['map_id']);
            $resultResponse = $db->fetchAll($queryResponse);

            $rqResponse = $db->select()->from(array('ref' => 'reference_result_recency'))
                ->joinLeft(array('pr' => 'r_possibleresult'), "ref.reference_result=pr.id")
                ->where("ref.shipment_id = ?", $shipmentId);
            $refResponse = $db->fetchAll($rqResponse);

            $attributes = json_decode($rowOverAll['attributes'], true);
            $extraction = (array_key_exists($attributes['recency_assay'], $assayList)) ? $assayList[$attributes['recency_assay']] : "";
            $assayLot = $attributes['recency_assay_lot_no'];
            $sampleRehydrationDate = (isset($attributes['sample_rehydration_date'])) ? Pt_Commons_General::humanDateFormat($attributes['sample_rehydration_date']) : "";

            $firstSheet->getCellByColumnAndRow(0, $row)->setValueExplicit(html_entity_decode($rowOverAll['unique_identifier'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(1, $row)->setValueExplicit(html_entity_decode($rowOverAll['first_name'] . " " . $rowOverAll['last_name'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(2, $row)->setValueExplicit(html_entity_decode(ucwords($rowOverAll['institute_name']), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(3, $row)->setValueExplicit(html_entity_decode(ucwords($rowOverAll['department_name']), ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(4, $row)->setValueExplicit(html_entity_decode($rowOverAll['region'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(5, $row)->setValueExplicit(html_entity_decode($rowOverAll['site_type'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(6, $row)->setValueExplicit(html_entity_decode($sampleRehydrationDate, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

            $col = 7;

            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($extraction, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($assayLot, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

            $receiptDate = ($rowOverAll['shipment_receipt_date'] != "" && $rowOverAll['shipment_receipt_date'] != "0000-00-00" && $rowOverAll['shipment_receipt_date'] != "1970-01-01") ? Pt_Commons_General::humanDateFormat($rowOverAll['shipment_receipt_date']) : "";
            $testDate = ($rowOverAll['shipment_test_date'] != "" && $rowOverAll['shipment_test_date'] != "0000-00-00" && $rowOverAll['shipment_test_date'] != "1970-01-01") ? Pt_Commons_General::humanDateFormat($rowOverAll['shipment_test_date']) : "";
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($receiptDate, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($testDate, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

            foreach ($resultResponse as $responseRow) {
                $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($responseRow['response'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            }
            foreach ($refResponse as $responseRow) {
                $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($responseRow['response'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            }
        }

        //<------------ Participant List Details Start -----

        $headings = array('Participant Code', 'Participant Name',  'Institute Name', 'Department', 'Address', 'Region', 'City', 'Facility Telephone', 'Email');

        $sheet = new PHPExcel_Worksheet($excel, 'Participant List');
        $excel->addSheet($sheet, 1);
        $sheet->setTitle('Participant List');

        $sql = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.number_of_samples'))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('sp.map_id', 'sp.participant_id', 'sp.attributes', 'sp.shipment_test_date', 'sp.shipment_receipt_date', 'sp.shipment_test_report_date', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_score', 'sp.documentation_score', 'sp.user_comment'))
            ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.lab_name', 'p.region', 'p.first_name', 'p.last_name', 'p.address', 'p.city', 'p.mobile', 'p.email', 'p.status'))
            ->joinLeft(array('pmp' => 'participant_manager_map'), 'pmp.participant_id=p.participant_id', array('pmp.dm_id'))
            ->joinLeft(array('dm' => 'data_manager'), 'dm.dm_id=pmp.dm_id', array('dm.institute', 'dataManagerFirstName' => 'dm.first_name', 'dataManagerLastName' => 'dm.last_name'))
            ->joinLeft(array('st' => 'r_site_type'), 'st.r_stid=p.site_type', array('st.site_type'))
            ->joinLeft(array('en' => 'enrollments'), 'en.participant_id=p.participant_id', array('en.enrolled_on'))
            ->where("s.shipment_id = ?", $shipmentId)
            ->group(array('sp.map_id'));
        //echo $sql;die;
        $shipmentResult = $db->fetchAll($sql);
        //die;
        $colNo = 0;
        $currentRow = 1;
        $type = PHPExcel_Cell_DataType::TYPE_STRING;
        //$sheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode("Participant List", ENT_QUOTES, 'UTF-8'), $type);
        //$sheet->getStyleByColumnAndRow(0,1)->getFont()->setBold(true);
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getDefaultRowDimension()->setRowHeight(18);

        foreach ($headings as $field => $value) {
            $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            // $sheet->getStyleByColumnAndRow($colNo, $currentRow)->getFont()->setBold(true);
            $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
            $colNo++;
        }

        if (isset($shipmentResult) && count($shipmentResult) > 0) {
            $currentRow += 1;
            foreach ($shipmentResult as $key => $aRow) {
                if ($result['scheme_type'] == 'recency') {
                    $resQuery = $db->select()->from(array('rrr' => 'response_result_recency'))
                        ->joinLeft(array('r' => 'r_possibleresult'), 'r.id=rrr.reported_result', array('finalResult' => 'r.response'))
                        ->where("rrr.shipment_map_id = ?", $aRow['map_id']);
                    $shipmentResult[$key]['response'] = $db->fetchAll($resQuery);
                }


                $sheet->getCellByColumnAndRow(0, $currentRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(1, $currentRow)->setValueExplicit($aRow['first_name'] . $aRow['last_name'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(2, $currentRow)->setValueExplicit($aRow['institute_name'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(3, $currentRow)->setValueExplicit($aRow['department_name'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(4, $currentRow)->setValueExplicit($aRow['address'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(5, $currentRow)->setValueExplicit($aRow['city'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(6, $currentRow)->setValueExplicit($aRow['region'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(7, $currentRow)->setValueExplicit($aRow['mobile'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(8, $currentRow)->setValueExplicit(strtolower($aRow['email']), PHPExcel_Cell_DataType::TYPE_STRING);

                for ($i = 0; $i <= 8; $i++) {
                    $cellName = $sheet->getCellByColumnAndRow($i, $currentRow)->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
                }

                $currentRow++;
                $shipmentCode = $aRow['shipment_code'];
            }
        }

        //------------- Participant List Details End ------>
        //<-------- Second sheet start
        $reportHeadings = array('Participant Code', 'Participant Name', 'Point of Contact', 'Region', 'Shipment Receipt Date', 'Sample Rehydration Date', 'Testing Date');

        if ($result['scheme_type'] == 'recency') {
            foreach (range(0, $result['number_of_samples']) as $dummy) {
                array_push($reportHeadings, 'Control Line', 'Verification Line', 'Long Term Line');
            }
            array_push($reportHeadings, 'Comments');
        }

        $sheet = new PHPExcel_Worksheet($excel, 'Results Reported');
        $excel->addSheet($sheet, 2);
        $sheet->setTitle('Results Reported');
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getDefaultRowDimension()->setRowHeight(18);


        $colNo = 0;
        $currentRow = 2;
        $n = count($reportHeadings);
        $finalResColoumn = $n - (($result['number_of_samples'] + 1) * 3);
        $finalResColoumn--;
        $c = 0;

        // To get the sample list
        $samples = $this->addRecencySampleNameInArray($shipmentId);
        // Zend_Debug::dump($n);
        // Zend_Debug::dump($finalResColoumn);die;
        foreach ($reportHeadings as $value) {

            $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow($colNo, $currentRow)->getFont()->setBold(true);

            $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);

            $cellName = $sheet->getCellByColumnAndRow($colNo, 3)->getColumn();
            $sheet->getStyle($cellName . "3")->applyFromArray($borderStyle);

            if ($colNo >= $finalResColoumn) {
                if ($c <= $result['number_of_samples']) {
                    $col = 7;
                    foreach ($samples as $sample) {
                        $firstCellName = $sheet->getCellByColumnAndRow($col, 1)->getColumn();
                        $secondCellName = $sheet->getCellByColumnAndRow(($col + 2), 1)->getColumn();

                        $sheet->mergeCells($firstCellName . "1:" . $secondCellName . "1");
                        $sheet->getStyle($firstCellName . "1")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                        $sheet->getStyle($firstCellName . "1")->applyFromArray($borderStyle);
                        $sheet->getStyle($secondCellName . "1")->applyFromArray($borderStyle);
                        $sheet->getCellByColumnAndRow($col, 1)->setValueExplicit(html_entity_decode($sample, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

                        $colorCol = $col;
                        $cellNameBar = $sheet->getCellByColumnAndRow($colorCol, 1)->getColumn();
                        $sheet->getStyle($cellNameBar . 2)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                        $colorCol = $colorCol + 1;

                        $cellNameBar = $sheet->getCellByColumnAndRow($colorCol, 1)->getColumn();
                        $sheet->getStyle($cellNameBar . 2)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                        $colorCol = $colorCol + 1;

                        $cellNameBar = $sheet->getCellByColumnAndRow($colorCol, 1)->getColumn();
                        $sheet->getStyle($cellNameBar . 2)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');

                        $col = $col + 3;
                    }
                    $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                    $l = $c - 1;
                    $c++;
                    $sheet->getCellByColumnAndRow($colNo, 3)->setValueExplicit(html_entity_decode($refResult[$l]['reference_result'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                }
            }
            $sheet->getStyle($cellName . '3')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFA0A0A0');
            $sheet->getStyle($cellName . '3')->getFont()->getColor()->setARGB('FFFFFF00');

            $colNo++;
        }

        $sheet->getStyle("A2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("B2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("C2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("D2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');

        //$sheet->getStyle("D2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');
        //$sheet->getStyle("E2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');
        //$sheet->getStyle("F2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');

        $cellName = $sheet->getCellByColumnAndRow($n, 3)->getColumn();
        //$sheet->getStyle('A3:'.$cellName.'3')->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#969696');
        //$sheet->getStyle('A3:'.$cellName.'3')->applyFromArray($borderStyle);

        //<-------- Sheet three heading -------
        $sheetThree = new PHPExcel_Worksheet($excel, 'Panel Score');
        $excel->addSheet($sheetThree, 3);
        $sheetThree->setTitle('Panel Score');
        $sheetThree->getDefaultColumnDimension()->setWidth(20);
        $sheetThree->getDefaultRowDimension()->setRowHeight(18);
        $panelScoreHeadings = array('Participant Code', 'Participant Name');
        $panelScoreHeadings = $this->addRecencySampleNameInArray($shipmentId, $panelScoreHeadings);
        array_push($panelScoreHeadings, 'Test# Correct', '% Correct');
        $sheetThreeColNo = 0;
        $sheetThreeRow = 1;
        $panelScoreHeadingCount = count($panelScoreHeadings);
        $sheetThreeColor = 1 + $result['number_of_samples'];
        foreach ($panelScoreHeadings as $sheetThreeHK => $value) {
            $sheetThree->getCellByColumnAndRow($sheetThreeColNo, $sheetThreeRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheetThree->getStyleByColumnAndRow($sheetThreeColNo, $sheetThreeRow)->getFont()->setBold(true);
            $cellName = $sheetThree->getCellByColumnAndRow($sheetThreeColNo, $sheetThreeRow)->getColumn();
            $sheetThree->getStyle($cellName . $sheetThreeRow)->applyFromArray($borderStyle);

            if ($sheetThreeHK > 1 && $sheetThreeHK <= $sheetThreeColor) {
                $cellName = $sheetThree->getCellByColumnAndRow($sheetThreeColNo, $sheetThreeRow)->getColumn();
                $sheetThree->getStyle($cellName . $sheetThreeRow)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            }

            $sheetThreeColNo++;
        }
        //---------- Sheet Three heading ------->
        //<-------- Document Score Sheet Heading (Sheet Four)-------

        if ($result['scheme_type'] == 'recency') {
            $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
            $config = new Zend_Config_Ini($file, APPLICATION_ENV);
            $documentationScorePerItem = ($config->evaluation->recency->documentationScore / 5);
        }

        $docScoreSheet = new PHPExcel_Worksheet($excel, 'Documentation Score');
        $excel->addSheet($docScoreSheet, 4);
        $docScoreSheet->setTitle('Documentation Score');
        $docScoreSheet->getDefaultColumnDimension()->setWidth(20);
        //$docScoreSheet->getDefaultRowDimension()->setRowHeight(20);
        $docScoreSheet->getDefaultRowDimension('G')->setRowHeight(25);

        $docScoreHeadings = array('Participant Code', 'Participant Name', 'Supervisor signature', 'Panel Receipt Date', 'Rehydration Date', 'Tested Date', 'Rehydration Test In Specified Time', 'Documentation Score %');

        $docScoreSheetCol = 0;
        $docScoreRow = 1;
        $docScoreHeadingsCount = count($docScoreHeadings);
        foreach ($docScoreHeadings as $sheetThreeHK => $value) {
            $docScoreSheet->getCellByColumnAndRow($docScoreSheetCol, $docScoreRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $docScoreSheet->getStyleByColumnAndRow($docScoreSheetCol, $docScoreRow)->getFont()->setBold(false);
            $cellName = $docScoreSheet->getCellByColumnAndRow($docScoreSheetCol, $docScoreRow)->getColumn();
            $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);
            $docScoreSheet->getStyleByColumnAndRow($docScoreSheetCol, $docScoreRow)->getAlignment()->setWrapText(true);
            $docScoreSheetCol++;
        }
        $docScoreRow = 2;
        $secondRowcellName = $docScoreSheet->getCellByColumnAndRow(1, $docScoreRow);
        $secondRowcellName->setValueExplicit(html_entity_decode("Points Breakdown", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $docScoreSheet->getStyleByColumnAndRow(1, $docScoreRow)->getFont()->setBold(true);
        $cellName = $secondRowcellName->getColumn();
        $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);

        for ($r = 2; $r <= 7; $r++) {
            $secondRowcellName = $docScoreSheet->getCellByColumnAndRow($r, $docScoreRow);
            if ($r != 7) {
                $secondRowcellName->setValueExplicit(html_entity_decode($documentationScorePerItem, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            }
            $docScoreSheet->getStyleByColumnAndRow($r, $docScoreRow)->getFont()->setBold(false);
            $cellName = $secondRowcellName->getColumn();
            $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);
        }

        //---------- Document Score Sheet Heading (Sheet Four)------->
        //<-------- Total Score Sheet Heading (Sheet Four)-------


        $totalScoreSheet = new PHPExcel_Worksheet($excel, 'Total Score');
        $excel->addSheet($totalScoreSheet, 5);
        $totalScoreSheet->setTitle('Total Score');
        $totalScoreSheet->getDefaultColumnDimension()->setWidth(20);
        $totalScoreSheet->getDefaultRowDimension(1)->setRowHeight(30);
        $totalScoreHeadings = array('Participant Code', 'Participant Name', 'No. of Panels Correct (N=' . $result['number_of_samples'] . ')', 'Panel Score(100% Conv.)', 'Panel Score(90% Conv.)', 'Documentation Score(100% Conv.)', 'Documentation Score(10% Conv.)', 'Total Score', 'Overall Performance');

        $totScoreSheetCol = 0;
        $totScoreRow = 1;
        $totScoreHeadingsCount = count($totalScoreHeadings);
        foreach ($totalScoreHeadings as $sheetThreeHK => $value) {
            $totalScoreSheet->getCellByColumnAndRow($totScoreSheetCol, $totScoreRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $totalScoreSheet->getStyleByColumnAndRow($totScoreSheetCol, $totScoreRow)->getFont()->setBold(true);
            $cellName = $totalScoreSheet->getCellByColumnAndRow($totScoreSheetCol, $totScoreRow)->getColumn();
            $totalScoreSheet->getStyle($cellName . $totScoreRow)->applyFromArray($borderStyle);
            $totalScoreSheet->getStyleByColumnAndRow($totScoreSheetCol, $totScoreRow)->getAlignment()->setWrapText(true);
            $totScoreSheetCol++;
        }

        //---------- Document Score Sheet Heading (Sheet Four)------->
        $ktr = 9;
        $kitId = 7; //Test Kit coloumn count 
        if (isset($refResult) && count($refResult) > 0) {
            foreach ($refResult as $keyv => $row) {
                $keyv = $keyv + 1;
                $ktr = $ktr + $keyv;
                //In Excel Third row added the Test kit name1,kit lot,exp date
                $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['reference_control_line'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['reference_diagnosis_line'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['reference_longterm_line'], PHPExcel_Cell_DataType::TYPE_STRING);

                $sheet->getCellByColumnAndRow($ktr, 3)->setValueExplicit($row['response'], PHPExcel_Cell_DataType::TYPE_STRING);
                $ktr = 5;
            }
        }
        $currentRow = 4;
        $sheetThreeRow = 2;
        $docScoreRow = 3;
        $totScoreRow = 2;
        if (isset($shipmentResult) && count($shipmentResult) > 0) {

            foreach ($shipmentResult as $aRow) {
                $r = 0;
                $k = 0;
                $rehydrationDate = "";
                $shipmentTestDate = "";
                $sheetThreeCol = 0;
                $docScoreCol = 0;
                $totScoreCol = 0;
                $countCorrectResult = 0;

                $colCellObj = $sheet->getCellByColumnAndRow($r++, $currentRow);
                $colCellObj->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $cellName = $colCellObj->getColumn();
                //$sheet->getStyle($cellName.$currentRow)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                //$sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['first_name'] . $aRow['last_name'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['dataManagerFirstName'] . $aRow['dataManagerLastName'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['region'], PHPExcel_Cell_DataType::TYPE_STRING);
                $shipmentReceiptDate = "";
                if (isset($aRow['shipment_receipt_date']) && trim($aRow['shipment_receipt_date']) != "") {
                    $shipmentReceiptDate = $aRow['shipment_receipt_date'] = Pt_Commons_General::excelDateFormat($aRow['shipment_receipt_date']);
                }

                if (isset($aRow['shipment_test_date']) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {
                    $shipmentTestDate = Pt_Commons_General::excelDateFormat($aRow['shipment_test_date']);
                }

                if (trim($aRow['attributes']) != "") {
                    $attributes = json_decode($aRow['attributes'], true);
                    $sampleRehydrationDate = new Zend_Date($attributes['sample_rehydration_date']);
                    $rehydrationDate = Pt_Commons_General::excelDateFormat($attributes["sample_rehydration_date"]);
                }

                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['shipment_receipt_date'], PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($rehydrationDate, PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($shipmentTestDate, PHPExcel_Cell_DataType::TYPE_STRING);



                $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($aRow['first_name'] . $aRow['last_name'], PHPExcel_Cell_DataType::TYPE_STRING);

                //<-------------Document score sheet------------

                $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($aRow['first_name'] . $aRow['last_name'], PHPExcel_Cell_DataType::TYPE_STRING);

                if (isset($shipmentReceiptDate) && trim($shipmentReceiptDate) != "") {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                } else {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                }

                if (isset($aRow['supervisor_approval']) && strtolower($aRow['supervisor_approval']) == 'yes' && isset($aRow['participant_supervisor']) && trim($aRow['participant_supervisor']) != "") {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                } else {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                }

                if (isset($rehydrationDate) && trim($rehydrationDate) != "") {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                } else {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                }

                if (isset($aRow['shipment_test_date']) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                } else {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                }

                if (isset($sampleRehydrationDate) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {


                    $config = new Zend_Config_Ini(APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini", APPLICATION_ENV);
                    $sampleRehydrationDate = new DateTime($attributes['sample_rehydration_date']);
                    $testedOnDate = new DateTime($aRow['shipment_test_date']);
                    $interval = $sampleRehydrationDate->diff($testedOnDate);

                    // Testing should be done within 24*($config->evaluation->dts->sampleRehydrateDays) hours of rehydration.
                    $sampleRehydrateDays = $config->evaluation->dts->sampleRehydrateDays;
                    $rehydrateHours = $sampleRehydrateDays * 24;

                    if ($interval->days < $sampleRehydrateDays || $interval->days > ($sampleRehydrateDays + 1)) {

                        $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                    } else {
                        $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                    }
                } else {
                    $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                }

                $documentScore = (($aRow['documentation_score'] / $config->evaluation->dts->documentationScore) * 100);
                $docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentScore, PHPExcel_Cell_DataType::TYPE_STRING);

                //-------------Document score sheet------------>
                //<------------ Total score sheet ------------

                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['first_name'] . $aRow['last_name'], PHPExcel_Cell_DataType::TYPE_STRING);

                //------------ Total score sheet ------------>
                //Zend_Debug::dump($aRow['response']);
                if (count($aRow['response']) > 0) {

                    for ($k = 0; $k < $aRow['number_of_samples']; $k++) {
                        //$row[] = $aRow[$k]['testResult1'];
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][$k]['control_line'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][$k]['diagnosis_line'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][$k]['longterm_line'], PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['response'][$k]['finalResult'], PHPExcel_Cell_DataType::TYPE_STRING);
                        if (isset($aRow['response'][$k]['calculated_score']) && $aRow['response'][$k]['calculated_score'] == 'Pass' && $aRow['response'][$k]['sample_id'] == $refResult[$k]['sample_id']) {
                            $countCorrectResult++;
                        }
                    }

                    $sheet->getCellByColumnAndRow($r++, $currentRow)->setValueExplicit($aRow['user_comment'], PHPExcel_Cell_DataType::TYPE_STRING);

                    $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($countCorrectResult, PHPExcel_Cell_DataType::TYPE_STRING);

                    $totPer = round((($countCorrectResult / $aRow['number_of_samples']) * 100), 2);
                    $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($totPer, PHPExcel_Cell_DataType::TYPE_STRING);

                    $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($countCorrectResult, PHPExcel_Cell_DataType::TYPE_STRING);
                    $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($totPer, PHPExcel_Cell_DataType::TYPE_STRING);

                    $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(($totPer * 0.9), PHPExcel_Cell_DataType::TYPE_STRING);
                }
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($documentScore, PHPExcel_Cell_DataType::TYPE_STRING);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['documentation_score'], PHPExcel_Cell_DataType::TYPE_STRING);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(($aRow['shipment_score'] + $aRow['documentation_score']), PHPExcel_Cell_DataType::TYPE_STRING);

                for ($i = 0; $i < $panelScoreHeadingCount; $i++) {
                    $cellName = $sheetThree->getCellByColumnAndRow($i, $sheetThreeRow)->getColumn();
                    $sheetThree->getStyle($cellName . $sheetThreeRow)->applyFromArray($borderStyle);
                }

                for ($i = 0; $i < $n; $i++) {
                    $cellName = $sheet->getCellByColumnAndRow($i, $currentRow)->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
                }

                for ($i = 0; $i < $docScoreHeadingsCount; $i++) {
                    $cellName = $docScoreSheet->getCellByColumnAndRow($i, $docScoreRow)->getColumn();
                    $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);
                }

                for ($i = 0; $i < $totScoreHeadingsCount; $i++) {
                    $cellName = $totalScoreSheet->getCellByColumnAndRow($i, $totScoreRow)->getColumn();
                    $totalScoreSheet->getStyle($cellName . $totScoreRow)->applyFromArray($borderStyle);
                }

                $currentRow++;

                $sheetThreeRow++;
                $docScoreRow++;
                $totScoreRow++;
            }
        }

        $excel->setActiveSheetIndex(0);

        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $filename = $result['shipment_code'] . '-' . date('d-M-Y-H-i-s') . rand() . '.xlsx';
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }

    public function addSampleNameInArray($shipmentId, $headings)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()->from('reference_result_dts', array('sample_label'))
            ->where("shipment_id = ?", $shipmentId)->order("sample_id");
        $result = $db->fetchAll($query);
        foreach ($result as $res) {
            array_push($headings, $res['sample_label']);
        }
        return $headings;
    }

    public function addCovid19SampleNameInArray($shipmentId, $headings)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()->from('reference_result_covid19', array('sample_label'))
            ->where("shipment_id = ?", $shipmentId)->order("sample_id");
        $result = $db->fetchAll($query);
        foreach ($result as $res) {
            array_push($headings, $res['sample_label']);
        }
        return $headings;
    }

    public function addRecencySampleNameInArray($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()->from('reference_result_recency', array('sample_label'))
            ->where("shipment_id = ?", $shipmentId)->order("sample_id");
        $result =  $db->fetchAll($query);
        $samples = array();
        foreach ($result as $row) {
            $samples[] = $row['sample_label'];
        }
        return $samples;
    }

    public function getShipmentsByScheme($schemeType, $startDate, $endDate)
    {
        $resultArray = array();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sQuery = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date',))
            ->where("DATE(s.shipment_date) >= ?", $startDate)
            ->where("DATE(s.shipment_date) <= ?", $endDate)
            ->where("s.scheme_type = ?", $schemeType)
            ->order("s.shipment_id");
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
            $totalQuery = $totalQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $totalQuery = $totalQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
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
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
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
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
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
            $row = array();
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
            $excel = new PHPExcel();
            $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
            $cacheSettings = array('memoryCacheSize' => '80MB');
            PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
            $output = array();
            $sheet = $excel->getActiveSheet();
            $styleArray = array(
                'font' => array(
                    'bold' => true,
                ),
                'alignment' => array(
                    'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                    'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
                ),
                'borders' => array(
                    'outline' => array(
                        'style' => PHPExcel_Style_Border::BORDER_THIN,
                    ),
                )
            );

            $colNo = 0;
            $sheet->mergeCells('A1:I1');
            $sheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode('Participant Performance Overview Report', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            if (isset($params['shipmentName']) && trim($params['shipmentName']) != "") {
                $sheet->getCellByColumnAndRow(0, 2)->setValueExplicit(html_entity_decode('Shipment', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode($params['shipmentName'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            }
            $sheet->getCellByColumnAndRow(0, 3)->setValueExplicit(html_entity_decode('Selected Date Range', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode($params['dateRange'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

            $sheet->getStyleByColumnAndRow(0, 1)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(0, 2)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(0, 3)->getFont()->setBold(true);

            foreach ($headings as $field => $value) {
                $sheet->getCellByColumnAndRow($colNo, 5)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getStyleByColumnAndRow($colNo, 5)->getFont()->setBold(true);
                $colNo++;
            }

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuerySession = new Zend_Session_Namespace('participantPerformanceExcel');
            $rResult = $db->fetchAll($sQuerySession->participantQuery);
            foreach ($rResult as $aRow) {

                $row = array();
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
                    $sheet->getCellByColumnAndRow($colNo, $rowNo + 6)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(150);
                        $sheet->getStyleByColumnAndRow($colNo, $rowNo + 6)->getAlignment()->setWrapText(true);
                    }
                    $colNo++;
                }
            }

            if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
                mkdir(TEMP_UPLOAD_PATH);
            }

            $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
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
            $excel = new PHPExcel();
            $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
            $cacheSettings = array('memoryCacheSize' => '80MB');
            PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
            $output = array();
            $sheet = $excel->getActiveSheet();
            $styleArray = array(
                'font' => array(
                    'bold' => true,
                ),
                'alignment' => array(
                    'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                    'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
                ),
                'borders' => array(
                    'outline' => array(
                        'style' => PHPExcel_Style_Border::BORDER_THIN,
                    ),
                )
            );

            $colNo = 0;
            $sheet->mergeCells('A1:I1');
            $sheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode('Participant Corrective Action Overview', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            if (isset($params['shipmentName']) && trim($params['shipmentName']) != "") {
                $sheet->getCellByColumnAndRow(0, 2)->setValueExplicit(html_entity_decode('Shipment', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode($params['shipmentName'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            }
            $sheet->getCellByColumnAndRow(0, 3)->setValueExplicit(html_entity_decode('Selected Date Range', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode($params['dateRange'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);


            $sheet->getStyleByColumnAndRow(0, 1)->getFont()->setBold(true);

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
            $sheet->getCellByColumnAndRow(0, 4)->setValueExplicit(html_entity_decode('Total shipped :' . $totalShipped, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow(0, 4)->getFont()->setBold(true);
            $sheet->mergeCells('A5:B5');
            $sheet->getCellByColumnAndRow(0, 5)->setValueExplicit(html_entity_decode('Total number of responses :' . $totalResp, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow(0, 5)->getFont()->setBold(true);
            $sheet->mergeCells('A6:B6');
            $sheet->getCellByColumnAndRow(0, 6)->setValueExplicit(html_entity_decode('Total number of valid responses :' . $validResp, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow(0, 6)->getFont()->setBold(true);
            $sheet->mergeCells('A7:B7');
            //$sheet->getCellByColumnAndRow(0, 7)->setValueExplicit(html_entity_decode('Average score :' . $avgScore, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            //$sheet->getStyleByColumnAndRow(0, 7)->getFont()->setBold(true);

            foreach ($headings as $field => $value) {
                $sheet->getCellByColumnAndRow($colNo, 9)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getStyleByColumnAndRow($colNo, 9)->getFont()->setBold(true);
                $colNo++;
            }


            $sQuerySession = new Zend_Session_Namespace('CorrectiveActionsExcel');
            $rResult = $db->fetchAll($sQuerySession->correctiveActionsQuery);

            if (count($rResult) > 0) {
                foreach ($rResult as $aRow) {
                    $row = array();
                    $row[] = $aRow['corrective_action'];
                    $row[] = $aRow['total_corrective'];
                    $output[] = $row;
                }
            } else {
                $row = array();
                $row[] = 'No result found';
                $output[] = $row;
            }

            foreach ($output as $rowNo => $rowData) {
                $colNo = 0;
                foreach ($rowData as $field => $value) {
                    if (!isset($value)) {
                        $value = "";
                    }
                    $sheet->getCellByColumnAndRow($colNo, $rowNo + 10)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(100);
                        $sheet->getStyleByColumnAndRow($colNo, $rowNo + 10)->getAlignment()->setWrapText(true);
                    }
                    $colNo++;
                }
            }

            if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
                mkdir(TEMP_UPLOAD_PATH);
            }

            $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
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
            $excel = new PHPExcel();
            $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
            $cacheSettings = array('memoryCacheSize' => '80MB');
            PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
            $output = array();
            $sheet = $excel->getActiveSheet();
            $styleArray = array(
                'font' => array(
                    'bold' => true,
                ),
                'alignment' => array(
                    'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                    'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
                ),
                'borders' => array(
                    'outline' => array(
                        'style' => PHPExcel_Style_Border::BORDER_THIN,
                    ),
                )
            );

            $colNo = 0;
            $sheet->mergeCells('A1:I1');
            $sheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode('Shipment Response Overview', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            if (isset($params['shipmentName']) && trim($params['shipmentName']) != "") {
                $sheet->getCellByColumnAndRow(0, 2)->setValueExplicit(html_entity_decode('Shipment', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode($params['shipmentName'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            }
            $sheet->getCellByColumnAndRow(0, 3)->setValueExplicit(html_entity_decode('Selected Date Range', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode($params['dateRange'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);


            $sheet->getStyleByColumnAndRow(0, 3)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(0, 2)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(0, 1)->getFont()->setBold(true);
            foreach ($headings as $field => $value) {
                $sheet->getCellByColumnAndRow($colNo, 5)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getStyleByColumnAndRow($colNo, 5)->getFont()->setBold(true);
                $colNo++;
            }

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuerySession = new Zend_Session_Namespace('shipmentExportExcel');
            $rResult = $db->fetchAll($sQuerySession->shipmentExportQuery);
            foreach ($rResult as $aRow) {

                $row = array();
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
                    $sheet->getCellByColumnAndRow($colNo, $rowNo + 6)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(150);
                        $sheet->getStyleByColumnAndRow($colNo, $rowNo + 6)->getAlignment()->setWrapText(true);
                    }
                    $colNo++;
                }
            }

            if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
                mkdir(TEMP_UPLOAD_PATH);
            }

            $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
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
            $totalQuery = $totalQuery->where("DATE(s.shipment_date) >= ?", $params['dateStartDate']);
            $totalQuery = $totalQuery->where("DATE(s.shipment_date) <= ?", $params['dateEndDate']);
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



        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
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
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
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


            $row = array();

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

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
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

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
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
                    "beforeDueDate" => new Zend_Db_Expr("SUM(sp.shipment_test_report_date <= s.lastdate_response)"),
                    "afterDueDate" => new Zend_Db_Expr("SUM(sp.shipment_test_report_date > s.lastdate_response)"),
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
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ?", $parameters['shipmentId']);
        }
        // die($sQuery);
        $rResult = $dbAdapter->fetchAll($sQuery);
        $row = array();
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
            $excel = new PHPExcel();
            $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
            $cacheSettings = array('memoryCacheSize' => '80MB');
            PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
            $output = array();
            $sheet = $excel->getActiveSheet();
            $styleArray = array(
                'font' => array(
                    'bold' => true,
                ),
                'alignment' => array(
                    'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                    'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
                ),
                'borders' => array(
                    'outline' => array(
                        'style' => PHPExcel_Style_Border::BORDER_THIN,
                    ),
                )
            );

            $colNo = 0;
            $sheet->mergeCells('A1:I1');
            $sheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode('Region Wise Participant Performance Report ', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

            $sheet->getCellByColumnAndRow(0, 2)->setValueExplicit(html_entity_decode('Scheme', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode($params['selectedScheme'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

            $sheet->getCellByColumnAndRow(0, 3)->setValueExplicit(html_entity_decode('Shipment Date', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode($params['selectedDate'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

            $sheet->getCellByColumnAndRow(0, 4)->setValueExplicit(html_entity_decode('Shipment Code', ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $sheet->getCellByColumnAndRow(1, 4)->setValueExplicit(html_entity_decode($params['selectedCode'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

            $sheet->getStyleByColumnAndRow(0, 1)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(0, 2)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(0, 3)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(0, 4)->getFont()->setBold(true);

            foreach ($headings as $field => $value) {
                $sheet->getCellByColumnAndRow($colNo, 6)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getStyleByColumnAndRow($colNo, 6)->getFont()->setBold(true);
                $colNo++;
            }

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuerySession = new Zend_Session_Namespace('participantPerformanceExcel');
            $rResult = $db->fetchAll($sQuerySession->participantRegionQuery);
            foreach ($rResult as $aRow) {
                $row = array();
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
                    $sheet->getCellByColumnAndRow($colNo, $rowNo + 7)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(150);
                        $sheet->getStyleByColumnAndRow($colNo, $rowNo + 7)->getAlignment()->setWrapText(true);
                    }
                    $colNo++;
                }
            }

            if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
                mkdir(TEMP_UPLOAD_PATH);
            }

            $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
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
            $sQuery = $sQuery->where("s.shipment_date >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("s.shipment_date <= ?", $parameters['endDate']);
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
            $row = array();
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
                $sQuery = $sQuery->where("s.shipment_date >= ?", $parameters['startDate']);
                $sQuery = $sQuery->where("s.shipment_date <= ?", $parameters['endDate']);
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
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
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
            $sQuery = $sQuery->where("DATE(s.shipment_date) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(s.shipment_date) <= ?", $parameters['endDate']);
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
            $row = array();
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
        $vlParticipantCount = array();
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
        $totalResult = array();
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
                $totalResult = array();
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
            $resultAccept = array();
            $resultFail = array();
            $resultEx = array();
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
        $resultArray = array();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date',))
            ->where("DATE(s.shipment_date) >= ?", $startDate)
            ->where("DATE(s.shipment_date) <= ?", $endDate)
            ->order("s.shipment_id");
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
            $startDate = $params['startDate'];
            $endDate = $params['endDate'];
            $schemesService = new Application_Service_Schemes();

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
            $shipmentIdArray = array();
            foreach ($shipmentResult as $val) {
                $shipmentIdArray[] = $val['shipment_id'];
                $shipmentCodeArray[$val['scheme_type']][] = $val['shipment_code'];
                $impShipmentId = implode(",", $shipmentIdArray);
            }

            $sQuery = $db->select()
                ->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id', 'spm.participant_id', 'spm.shipment_test_report_date', 'spm.shipment_score', 'spm.documentation_score', 'spm.final_result', 'spm.attributes'))
                ->join(array('s' => 'shipment'), 's.shipment_id=spm.shipment_id', array('shipment_code', 'scheme_type', 'lastdate_response'))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('unique_identifier', 'first_name', 'last_name', 'email', 'city', 'state', 'address', 'institute_name'))
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
                $shipmentCodeArray = array();
                foreach ($shipmentResult as $val) {
                    $shipmentCodeArray[$val['scheme_type']][] = $val['shipment_code'];
                }
            } else {
                //$sQuery->where('spm.shipment_id IN(?)', $impShipmentId);
                $sQuery->where('spm.shipment_id IN (' . $impShipmentId . ')');
            }
            //Zend_Debug::dump($shipmentCodeArray);die;
            $shipmentParticipantResult = $db->fetchAll($sQuery);
            $participants = array();
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
            //Zend_Debug::dump($shipmentCodeArray);
            //die;
            return $this->generateAnnualReport($shipmentCodeArray, $participants, $startDate, $endDate);
        }
    }

    public function generateAnnualReport($shipmentCodeArray, $participants, $startDate, $endDate)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        //$shipmentParticipantResult=$db->fetchAll($sQuery);

        $schemeService = new Application_Service_Schemes();

        $vlAssayArray = $schemeService->getVlAssay();
        $eidAssayArray = $schemeService->getEidExtractionAssay();

        $headings = array('Participant ID', 'Participant Name', 'Address', 'City', 'State', 'Country', 'Email', 'Additional Email');
        foreach ($shipmentCodeArray as $arrayVal) {
            //
            foreach ($arrayVal as $shipmentCode) {
                $headings[] = "Assay/Platform - " . $shipmentCode;
                $headings[] = "Score - " . $shipmentCode;
            }

            $headings[] = 'Certificate Type';
        }


        $excel = new PHPExcel();
        $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
        $cacheSettings = array('memoryCacheSize' => '80MB');
        PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
        $output = array();

        $sheet = $excel->getActiveSheet();
        $firstSheet = new PHPExcel_Worksheet($excel, '');
        $excel->addSheet($firstSheet, 0);
        $firstSheet->getDefaultColumnDimension()->setWidth(20);
        $firstSheet->getDefaultRowDimension()->setRowHeight(18);
        $firstSheet->setTitle('ePT Annual Report');

        $colNo = 0;
        $headingStyle = array(
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            )
        );

        foreach ($headings as $field => $value) {
            $firstSheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $firstSheet->getStyleByColumnAndRow($colNo, 1)->applyFromArray($headingStyle);
            $firstSheet->getStyleByColumnAndRow($colNo, 1)->getFont()->setBold(true);
            $colNo++;
        }


        foreach ($participants as $uniqueIdentifier => $arrayVal) {
            $firstSheetRow = array();
            $firstSheetRow[] = $uniqueIdentifier;
            $firstSheetRow[] = $arrayVal['labName'];
            $firstSheetRow[] = $arrayVal['address'];
            $firstSheetRow[] = $arrayVal['city'];
            $firstSheetRow[] = $arrayVal['state'];
            $firstSheetRow[] = $arrayVal['country_name'];
            $firstSheetRow[] = $arrayVal['email'];
            $firstSheetRow[] = $arrayVal['additional_email'];

            foreach ($shipmentCodeArray as $shipmentType => $shipmentsList) {
                $certificate = true;
                $participated = false;

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



                    if (!empty($arrayVal[$shipmentType][$shipmentCode]['shipment_test_report_date'])) {
                        $reportedDateTimeArray = explode(" ", $arrayVal[$shipmentType][$shipmentCode]['shipment_test_report_date']);
                        if (trim($reportedDateTimeArray[0]) != "" && $reportedDateTimeArray[0] != null && trim($reportedDateTimeArray[0]) != "0000-00-00" && trim($reportedDateTimeArray[0]) != "1970-01-01") {

                            $reportedDate = new DateTime($reportedDateTimeArray[0]);
                            $lastDate = new DateTime($arrayVal[$shipmentType][$shipmentCode]['lastdate_response']);
                            if ($reportedDate <= $lastDate) {
                                $participated = true;
                            }
                        }
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
                        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
                    )
                );
                if (empty($value)) {
                    $value = "";
                    $cellDataType = PHPExcel_Cell_DataType::TYPE_STRING;
                } else if (is_float($value)) {
                    $cellDataType = PHPExcel_Cell_DataType::TYPE_NUMERIC;
                    $decimalFormat = true;
                    $cellStyle = array(
                        'alignment' => array(
                            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_RIGHT,
                        )
                    );
                } else if (is_numeric($value)) {
                    $cellDataType = PHPExcel_Cell_DataType::TYPE_NUMERIC;
                    $cellStyle = array(
                        'alignment' => array(
                            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_RIGHT,
                        )
                    );
                } else {
                    $cellDataType = PHPExcel_Cell_DataType::TYPE_STRING;
                }
                $firstSheet->getCellByColumnAndRow($colNo, $rowNo + 2)->getStyle()->applyFromArray($cellStyle);
                $firstSheet->getCellByColumnAndRow($colNo, $rowNo + 2)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), $cellDataType);
                if ($decimalFormat) {
                    $firstSheet->getCellByColumnAndRow($colNo, $rowNo + 2)->getStyle()->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);;
                }
                if ($colNo == (sizeof($headings) - 1)) {
                    //$firstSheet->getColumnDimensionByColumn($colNo)->setWidth(100);
                    $firstSheet->getStyleByColumnAndRow($colNo, $rowNo + 2)->getAlignment()->setWrapText(true);
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
        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $filename = 'ePT-Annual-Report-' . rand() . date('d-M-Y-H-i-s') . '.xlsx';
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "annual-reports" . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }
}
