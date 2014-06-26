<?php

class Application_Service_Reports {
	
	public function getAllShipments($parameters)
	{
	    /* Array of database columns which should be read and sent back to DataTables. Use a space where
	     * you want to insert a non-database field (for example a counter or static image)
	     */
    
	    $aColumns = array('distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", 's.shipment_code',"DATE_FORMAT(s.lastdate_response,'%d-%b-%Y')",'sl.scheme_name' ,'s.number_of_samples' ,new Zend_Db_Expr('count("participant_id")'),new Zend_Db_Expr("SUM(shipment_test_date <> '')"),new Zend_Db_Expr("(SUM(shipment_test_date <> '')/count('participant_id'))*100"),new Zend_Db_Expr("SUM(final_result = 1)"),'s.status');
	    $searchColumns = array('distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", 's.shipment_code',"DATE_FORMAT(s.lastdate_response,'%d-%b-%Y')",'sl.scheme_name' ,'s.number_of_samples','participant_count','reported_count','reported_percentage','number_passed','s.status');
	    $havingColumns = array('participant_count','reported_count');
	    $orderColumns = array('distribution_code','distribution_date', 's.shipment_code','s.lastdate_response' ,'sl.scheme_name' ,'s.number_of_samples' ,new Zend_Db_Expr('count("participant_id")'),new Zend_Db_Expr("SUM(shipment_test_date <> '')"),new Zend_Db_Expr("(SUM(shipment_test_date <> '')/count('participant_id'))*100"),new Zend_Db_Expr("SUM(final_result = 1)"),'s.status');
    
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
			if($searchColumns[$i] == "" || $searchColumns[$i] == null){
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
		
	    //
	    //
	    //$sHaving = "";
	    //if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
	    //    $searchArray = explode(" ", $parameters['sSearch']);
	    //    $sHavingSub = "";
	    //    foreach ($searchArray as $search) {
	    //        if ($sHavingSub == "") {
	    //            $sHavingSub .= "(";
	    //        } else {
	    //            $sHavingSub .= " AND (";
	    //        }
	    //        $colSize = count($havingColumns);
	    //
	    //        for ($i = 0; $i < $colSize; $i++) {
	    //            if($havingColumns[$i] == "" || $havingColumns[$i] == null){
	    //                continue;
	    //            }
	    //            if ($i < $colSize - 1) {
	    //                $sHavingSub .= $havingColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
	    //            } else {
	    //                $sHavingSub .= $havingColumns[$i] . " LIKE '%" . ($search) . "%' ";
	    //            }
	    //        }
	    //        $sHavingSub .= ")";
	    //    }
	    //    $sHaving .= $sHavingSub;
	    //}			
		
	    /*
	     * SQL queries
	     * Get data to display
	     */
		    
		    $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
		    $sQuery = $dbAdapter->select()->from(array('s'=>'shipment'))
				    ->join(array('sl'=>'scheme_list'),'s.scheme_type=sl.scheme_id')
				    ->join(array('d'=>'distributions'),'d.distribution_id=s.distribution_id')
				    ->joinLeft(array('sp'=>'shipment_participant_map'),'sp.shipment_id=s.shipment_id',array('participant_count' => new Zend_Db_Expr('count("participant_id")'), 'reported_count'=> new Zend_Db_Expr("SUM(shipment_test_date <> '')"),'reported_percentage' => new Zend_Db_Expr("ROUND((SUM(shipment_test_date <> '')/count('participant_id'))*100,2)"), 'number_passed'=> new Zend_Db_Expr("SUM(final_result = 1)")))
				    ->joinLeft(array('p'=>'participant'),'p.participant_id=sp.participant_id')
				    ->joinLeft(array('pmm'=>'participant_manager_map'),'pmm.participant_id=p.participant_id')
				    ->joinLeft(array('rr'=>'r_results'),'sp.final_result=rr.result_id')
				    ->group('s.shipment_id');
					
		    if(isset($parameters['scheme']) && $parameters['scheme'] !=""){
			    $sQuery = $sQuery->where("s.scheme_type = ?",$parameters['scheme']);
		    }
		    
		    if(isset($parameters['startDate']) && $parameters['startDate'] !="" && isset($parameters['endDate']) && $parameters['endDate'] !=""){
			    $sQuery = $sQuery->where("s.shipment_date >= ?",$parameters['startDate']);
			    $sQuery = $sQuery->where("s.shipment_date <= ?",$parameters['endDate']);
		    }
		    
		    if(isset($parameters['dataManager']) && $parameters['dataManager'] !=""){
			    $sQuery = $sQuery->where("pmm.dm_id = ?",$parameters['dataManager']);
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
    
	    //error_log($sQuery);
    
	    $rResult = $dbAdapter->fetchAll($sQuery);
    
    
	    /* Data set length after filtering */
	    $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
	    $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
	    $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
	    $iFilteredTotal = count($aResultFilterTotal);
    
	    /* Total data set length */
	    $sQuery = $dbAdapter->select()->from(array('s'=>'shipment'), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
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
		
		    $shipmentResults = $shipmentDb->getPendingShipmentsByDistribution($aRow['distribution_id']);
		    $responseCount=($aRow['reported_count'] != "") ? $aRow['reported_count'] : 0;
		    $row = array();
		    $row[] = $aRow['distribution_code'];
		    $row[] = Pt_Commons_General::humanDateFormat($aRow['distribution_date']);
		    $row[] = $aRow['shipment_code'];
		    $row[] = Pt_Commons_General::humanDateFormat($aRow['lastdate_response']);
		    $row[] = $aRow['scheme_name'];
		    $row[] = $aRow['number_of_samples'];
		    $row[] = $aRow['participant_count'];
		    $row[] = '<a href="/reports/shipments/response-chart/id/'.base64_encode($aRow['shipment_id']).'/shipmentDate/'.base64_encode($aRow['distribution_date']).'/shipmentCode/'.base64_encode($aRow['distribution_code']).'" target="_blank">'.$responseCount.'</a>';
		   // $row[] = ($aRow['reported_count'] != "") ? $aRow['reported_count'] : 0;
		    $row[] = ($aRow['reported_percentage'] != "") ? $aRow['reported_percentage'] : "0";
		    $row[] = $aRow['number_passed'];
		    $row[] = ucwords($aRow['status']);
		
		
		    $output['aaData'][] = $row;
	    }
    
	    echo json_encode($output);
	}
    
    public function updateReportConfigs($params){
	$filterRules = array('*' => 'StripTags','*' => 'StringTrim');
        $filter = new Zend_Filter_Input($filterRules, null, $params);
        if ($filter->isValid()) {
            //$params = $filter->getEscaped();
            $db = new Application_Model_DbTable_ReportConfig();
            $db->getAdapter()->beginTransaction();
            try {
                $result=$db->updateReportDetails($params);
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
    
    public function getReportConfigValue($name){
	$db = new Application_Model_DbTable_ReportConfig();
	return $db->getValue($name);
    }
    public function getParticipantDetailedReport($params){
	 $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
			
	if(isset($params['reportType']) && $params['reportType']=="network"){
		$sQuery = $dbAdapter->select()->from(array('n'=>'r_network_tiers'))
			->joinLeft(array('p'=>'participant'),'p.network_tier=n.network_id',array())
			//->joinLeft(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array('participant_count'=> new Zend_Db_Expr("SUM(shipment_test_date = '') + SUM(shipment_test_date <> '')"), 'reported_count'=> new Zend_Db_Expr("SUM(shipment_test_date <> '')"), 'number_passed'=> new Zend_Db_Expr("SUM(final_result = 1)")))
			->joinLeft(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array('others'=> new Zend_Db_Expr("SUM(final_result IS NULL)"), 'number_failed'=> new Zend_Db_Expr("SUM(final_result = 2)"), 'number_passed'=> new Zend_Db_Expr("SUM(final_result = 1)")))
			->joinLeft(array('s'=>'shipment'),'s.shipment_id=sp.shipment_id',array())
			->joinLeft(array('sl'=>'scheme_list'),'s.scheme_type=sl.scheme_id',array())
			->joinLeft(array('d'=>'distributions'),'d.distribution_id=s.distribution_id',array())			
			->joinLeft(array('rr'=>'r_results'),'sp.final_result=rr.result_id',array())
			->group('n.network_id')/*->where("p.status = 'active'")*/;
	}
	
	if(isset($params['reportType']) && $params['reportType']=="affiliation"){
		$sQuery = $dbAdapter->select()->from(array('pa'=>'r_participant_affiliates'))
			->joinLeft(array('p'=>'participant'),'p.affiliation=pa.affiliate',array())
			//->joinLeft(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array('participant_count'=> new Zend_Db_Expr("SUM(shipment_test_date = '') + SUM(shipment_test_date <> '')"), 'reported_count'=> new Zend_Db_Expr("SUM(shipment_test_date <> '')"), 'number_passed'=> new Zend_Db_Expr("SUM(final_result = 1)")))
			->joinLeft(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array('others'=> new Zend_Db_Expr("SUM(final_result IS NULL)"), 'number_failed'=> new Zend_Db_Expr("SUM(final_result = 2)"), 'number_passed'=> new Zend_Db_Expr("SUM(final_result = 1)")))
			->joinLeft(array('s'=>'shipment'),'s.shipment_id=sp.shipment_id',array())
			->joinLeft(array('sl'=>'scheme_list'),'s.scheme_type=sl.scheme_id',array())
			->joinLeft(array('d'=>'distributions'),'d.distribution_id=s.distribution_id',array())			
			->joinLeft(array('rr'=>'r_results'),'sp.final_result=rr.result_id',array())
			->group('pa.aff_id')/*->where("p.status = 'active'")*/;
	}
	if(isset($params['reportType']) && $params['reportType']=="region"){
		$sQuery = $dbAdapter->select()->from(array('p'=>'participant'),array('p.region'))
			//->joinLeft(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array('participant_count'=> new Zend_Db_Expr("SUM(shipment_test_date = '') + SUM(shipment_test_date <> '')"), 'reported_count'=> new Zend_Db_Expr("SUM(shipment_test_date <> '')"), 'number_passed'=> new Zend_Db_Expr("SUM(final_result = 1)")))
			->joinLeft(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array('others'=> new Zend_Db_Expr("SUM(final_result IS NULL)"), 'number_failed'=> new Zend_Db_Expr("SUM(final_result = 2)"), 'number_passed'=> new Zend_Db_Expr("SUM(final_result = 1)")))
			->joinLeft(array('s'=>'shipment'),'s.shipment_id=sp.shipment_id',array())
			->joinLeft(array('sl'=>'scheme_list'),'s.scheme_type=sl.scheme_id',array())
			->joinLeft(array('d'=>'distributions'),'d.distribution_id=s.distribution_id',array())			
			->joinLeft(array('rr'=>'r_results'),'sp.final_result=rr.result_id',array())
			->group('p.region')->where("p.region IS NOT NULL")->where("p.region != ''")/*->where("p.status = 'active'")*/;
	}		    
	if(isset($params['scheme']) && $params['scheme'] !=""){
		$sQuery = $sQuery->where("s.scheme_type = ?",$params['scheme']);
	}
	
	if(isset($params['startDate']) && $params['startDate'] !="" && isset($params['endDate']) && $params['endDate'] !=""){
		$sQuery = $sQuery->where("s.shipment_date >= ?",$params['startDate']);
		$sQuery = $sQuery->where("s.shipment_date <= ?",$params['endDate']);
	}
	return $dbAdapter->fetchAll($sQuery);
    }
    public function getAllParticipantDetailedReport($parameters)
	{
	    /* Array of database columns which should be read and sent back to DataTables. Use a space where
	     * you want to insert a non-database field (for example a counter or static image)
	     */
    
	    $aColumns = array('s.shipment_code','sl.scheme_name','distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')");
	    
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
		$sQuery = $dbAdapter->select()->from(array('s'=>'shipment'))
				->join(array('sl'=>'scheme_list'),'s.scheme_type=sl.scheme_id')
				->join(array('d'=>'distributions'),'d.distribution_id=s.distribution_id')
				->group('s.shipment_id');
		if(isset($parameters['startDate']) && $parameters['startDate'] !="" && isset($parameters['endDate']) && $parameters['endDate'] !=""){
			$sQuery = $sQuery->where("s.shipment_date >= ?",$parameters['startDate']);
			$sQuery = $sQuery->where("s.shipment_date <= ?",$parameters['endDate']);
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
		    $row[] = $aRow['distribution_code'];
		    $row[] = Pt_Commons_General::humanDateFormat($aRow['distribution_date']);
		    $output['aaData'][] = $row;
	    }
    
	    echo json_encode($output);
	}
	public function getTestKitReport($params){
		$dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sQuery = $dbAdapter->select()->from(array('res'=>'response_result_dts'),array('totalTest'=>new Zend_Db_Expr("CAST((COUNT('shipment_map_id')/s.number_of_samples) as UNSIGNED)")))
			->joinLeft(array('sp'=>'shipment_participant_map'),'sp.map_id=res.shipment_map_id',array())
			->joinLeft(array('p'=>'participant'),'sp.participant_id=p.participant_id',array('p.region'))
			->joinLeft(array('s'=>'shipment'),'s.shipment_id=sp.shipment_id',array());
				
		if(isset($params['kitType']) && $params['kitType']=="testkit1"){
		$sQuery = $sQuery->joinLeft(array('tn'=>'r_testkitname_dts'),'tn.TestKitName_ID=res.test_kit_name_1',array('TestKit_Name'))
				->group('tn.TestKitName_ID');
		}
		if(isset($params['kitType']) && $params['kitType']=="testkit2"){
		$sQuery = $sQuery->joinLeft(array('tn'=>'r_testkitname_dts'),'tn.TestKitName_ID=res.test_kit_name_2',array('TestKit_Name'))
				->group('tn.TestKitName_ID');
		}
		if(isset($params['kitType']) && $params['kitType']=="testkit3"){
		$sQuery = $sQuery->joinLeft(array('tn'=>'r_testkitname_dts'),'tn.TestKitName_ID=res.test_kit_name_3',array('TestKit_Name'))
				->group('tn.TestKitName_ID');
		}
		if(isset($params['reportType']) && $params['reportType']=="network"){
			if(isset($params['networkValue']) && $params['networkValue']!=""){
				$sQuery = $sQuery->where("p.network_tier = ?",$params['networkValue']);
			}else{
			 $sQuery = $sQuery->joinLeft(array('n'=>'r_network_tiers'),'p.network_tier=n.network_id',array())->group('n.network_id');
			}
			
		}
		if(isset($params['reportType']) && $params['reportType']=="affiliation"){
			if(isset($params['affiliateValue']) && $params['affiliateValue']!=""){
				$sQuery = $sQuery->where("p.affiliation= ?",$params['affiliateValue']);
			}else{
			 $sQuery = $sQuery->joinLeft(array('pa'=>'r_participant_affiliates'),'p.affiliation=pa.affiliate',array())->group('pa.aff_id');
			}
			
		}
		if(isset($params['reportType']) && $params['reportType']=="region"){
			if(isset($params['regionValue']) && $params['regionValue']!=""){
				$sQuery = $sQuery->where("p.region= ?",$params['regionValue']);
			}else{
			 $sQuery = $sQuery->group('p.region')->where("p.region IS NOT NULL")->where("p.region != ''");
			}
			
		}
		if(isset($params['startDate']) && $params['startDate'] !="" && isset($params['endDate']) && $params['endDate'] !=""){
			$sQuery = $sQuery->where("s.shipment_date >= ?",$params['startDate']);
			$sQuery = $sQuery->where("s.shipment_date <= ?",$params['endDate']);
		}
		return $dbAdapter->fetchAll($sQuery);
        }
	public function getTestKitDetailedReport($parameters)
	{
		//Zend_Debug::dump($parameters);die;
	    /* Array of database columns which should be read and sent back to DataTables. Use a space where
	     * you want to insert a non-database field (for example a counter or static image)
	     */
    
	    $aColumns = array('p.lab_name','tn.TestKit_Name');
	    
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
		$sQuery = $dbAdapter->select()->from(array('res'=>'response_result_dts'),array())
			->joinLeft(array('sp'=>'shipment_participant_map'),'sp.map_id=res.shipment_map_id',array())
			->joinLeft(array('p'=>'participant'),'sp.participant_id=p.participant_id',array('p.lab_name','participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
			->joinLeft(array('s'=>'shipment'),'s.shipment_id=sp.shipment_id',array())
                        ->group("p.participant_id");
				
		if(isset($parameters['kitType']) && $parameters['kitType']=="testkit1"){
		$sQuery = $sQuery->joinLeft(array('tn'=>'r_testkitname_dts'),'tn.TestKitName_ID=res.test_kit_name_1',array('tn.TestKit_Name'))
				->group('tn.TestKitName_ID');
		}
		if(isset($parameters['kitType']) && $parameters['kitType']=="testkit2"){
		$sQuery = $sQuery->joinLeft(array('tn'=>'r_testkitname_dts'),'tn.TestKitName_ID=res.test_kit_name_2',array('tn.TestKit_Name'))
				->group('tn.TestKitName_ID');
		}
		if(isset($parameters['kitType']) && $parameters['kitType']=="testkit3"){
		$sQuery = $sQuery->joinLeft(array('tn'=>'r_testkitname_dts'),'tn.TestKitName_ID=res.test_kit_name_3',array('tn.TestKit_Name'))
				->group('tn.TestKitName_ID');
		}
		if(isset($parameters['reportType']) && $parameters['reportType']=="network"){
			if(isset($parameters['networkValue']) && $parameters['networkValue']!=""){
				$sQuery = $sQuery->where("p.network_tier = ?",$parameters['networkValue']);
			}else{
			 $sQuery = $sQuery->joinLeft(array('n'=>'r_network_tiers'),'p.network_tier=n.network_id',array())->group('n.network_id');
			}
			
		}
		if(isset($parameters['reportType']) && $parameters['reportType']=="affiliation"){
			if(isset($parameters['affiliateValue']) && $parameters['affiliateValue']!=""){
				$sQuery = $sQuery->where("p.affiliation= ?",$parameters['affiliateValue']);
			}else{
			 $sQuery = $sQuery->joinLeft(array('pa'=>'r_participant_affiliates'),'p.affiliation=pa.affiliate',array())->group('pa.aff_id');
			}
			
		}
		if(isset($parameters['reportType']) && $parameters['reportType']=="region"){
			if(isset($parameters['regionValue']) && $parameters['regionValue']!=""){
				$sQuery = $sQuery->where("p.region= ?",$parameters['regionValue']);
			}else{
			 $sQuery = $sQuery->group('p.region')->where("p.region IS NOT NULL")->where("p.region != ''");
			}
			
		}
		if(isset($parameters['startDate']) && $parameters['startDate'] !="" && isset($parameters['endDate']) && $parameters['endDate'] !=""){
			$sQuery = $sQuery->where("s.shipment_date >= ?",$parameters['startDate']);
			$sQuery = $sQuery->where("s.shipment_date <= ?",$parameters['endDate']);
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
		    $row[] = $aRow['participantName'];
		    $row[] = stripslashes($aRow['TestKit_Name']);
		    $output['aaData'][] = $row;
	    }
    
	    echo json_encode($output);
	}
	
	
	public function getShipmentResponseCount($shipmentId, $date, $step=5,$maxDays = 60){
	 $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

	$responseResult=array();
	$responseDate=array();
        $initialStartDate=$date;
	for($i=$step; $i<=$maxDays;$i+=$step){
		
	 $sQuery = $dbAdapter->select()->from(array('s'=>'shipment'),array(''))
				       ->joinLeft(array('sp'=>'shipment_participant_map'),'sp.shipment_id=s.shipment_id',array('reported_count'=> new Zend_Db_Expr("SUM(shipment_test_date <> '')")))
				      ->where("s.shipment_id = ?",$shipmentId)
				       ->group('s.shipment_id');
		$endDate = strftime("%Y-%m-%d", strtotime("$date + $i day"));
		
		if(isset($date) && $date !="" && $endDate!='' && $i<$maxDays){
			$sQuery = $sQuery->where("sp.shipment_test_date >= ?",$date);
			$sQuery = $sQuery->where("sp.shipment_test_date <= ?",$endDate);
			$result= $dbAdapter->fetchAll($sQuery);
			$count = (isset($result[0]['reported_count'])&& $result[0]['reported_count'] != "") ? $result[0]['reported_count'] : 0;
			$responseResult[] = (int)$count;
			$responseDate[] = Pt_Commons_General::humanDateFormat($date).' '.Pt_Commons_General::humanDateFormat($endDate);
		$date=strftime("%Y-%m-%d", strtotime("$endDate +1 day"));	
		}
		
		if($i==$maxDays){
			$sQuery = $sQuery->where("sp.shipment_test_date >= ?",$date);
			$result= $dbAdapter->fetchAll($sQuery);
			$count = (isset($result[0]['reported_count'])&& $result[0]['reported_count'] != "") ? $result[0]['reported_count'] : 0;
		        $responseResult[] = (int)$count;
			$responseDate[] = Pt_Commons_General::humanDateFormat($date).'  and Above';
		}

        }
          return json_encode($responseResult).'#'.json_encode($responseDate);
    }
}

