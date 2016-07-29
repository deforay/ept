<?php

class Application_Service_Evaluation {

    public function getAllDistributions($parameters) {

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
                ->joinLeft(array('s' => 'shipment'), 's.distribution_id=d.distribution_id', array('shipments' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT s.shipment_code SEPARATOR ', ')"),'not_finalized_count' => new Zend_Db_Expr("SUM(IF(s.status!='finalized',1,0))")))
                ->where("d.status='shipped'")
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
		
		$sQuery = $dbAdapter->select()->from(array('temp' => $sQuery))->where("not_finalized_count>0");
		
        //die($sQuery);

        $rResult = $dbAdapter->fetchAll($sQuery);


        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        //$sQuery = $dbAdapter->select()->from('distributions', new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"))->where("status='shipped'");
        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

        /*
         * Output
         */
        $output = array(
            "sEcho" => isset($parameters['sEcho']) ? intval($parameters['sEcho']) : 0,
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
            $row[] = '<a class="btn btn-primary btn-xs" href="javascript:void(0);" onclick="getShipments(\'' . ($aRow['distribution_id']) . '\')"><span><i class="icon-search"></i> View</span></a>';



            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getShipments($distributionId) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('s' => 'shipment'))
                ->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id')
                ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('map_id', 'responseDate' => 'shipment_test_report_date', 'participant_count' => new Zend_Db_Expr('count("participant_id")'), 'reported_count' => new Zend_Db_Expr("SUM(shipment_test_date <> '0000-00-00')"), 'number_passed' => new Zend_Db_Expr("SUM(final_result = 1)"), 'last_not_participated_mailed_on', 'last_not_participated_mail_count','shipment_status'=>'s.status'))
                ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type')
                ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id')
                ->where("s.distribution_id = ?", $distributionId)
                ->group('s.shipment_id');
        return $db->fetchAll($sql);
    }
    
     public function getResponseCount($shipmentId,$distributionId) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('s' => 'shipment'),array(''))
                ->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id',array(''))
                ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('reported_count' => new Zend_Db_Expr("SUM(shipment_test_date <> '0000-00-00')")))
                ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type',array(''))
                ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id',array(''))
		->where("s.shipment_id = ?", $shipmentId)
                ->where("s.distribution_id = ?", $distributionId)
                ->group('s.shipment_id');
        return $db->fetchRow($sql);
    }

    public function getShipmentToEvaluate($shipmentId, $reEvaluate = false) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date', 's.lastdate_response', 's.distribution_id', 's.number_of_samples', 's.max_score', 's.shipment_comment', 's.created_by_admin', 's.created_on_admin', 's.updated_by_admin', 's.updated_on_admin', 'shipment_status' => 's.status'))
                ->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id')
                ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id')
                ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type')
                ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id')
                ->where("s.shipment_id = ?", $shipmentId)
                ->where("substring(sp.evaluation_status,4,1) != '0'");
        $shipmentResult = $db->fetchAll($sql);

        $schemeService = new Application_Service_Schemes();

        if ($shipmentResult[0]['scheme_type'] == 'eid') {
			$shipmentResult =  $this->evaluateEid($shipmentResult,$shipmentId);
        } else if ($shipmentResult[0]['scheme_type'] == 'dbs') {
            $counter = 0;
            $maxScore = 0;
            foreach ($shipmentResult as $shipment) {
                $createdOnUser = explode(" ", $shipment['created_on_user']);
                if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {

                    $createdOn = new Zend_Date($createdOnUser[0], Zend_Date::ISO_8601);
                } else {
                    $datearray = array('year' => 1970, 'month' => 1, 'day' => 01);
                    $createdOn = new Zend_Date($datearray);
                }

                $lastDate = new Zend_Date($shipment['lastdate_response'], Zend_Date::ISO_8601);
                if ($createdOn->isEarlier($lastDate)) {

                    $results = $schemeService->getDbsSamples($shipmentId, $shipment['participant_id']);
                    $totalScore = 0;
                    $maxScore = 0;
                    $mandatoryResult = "";
                    $lotResult = "";
                    $testKit1 = "";
                    $testKit2 = "";
                    $testKit3 = "";
                    $testKitRepeatResult = "";
                    $testKitExpiryResult = "";
                    $lotResult = "";
                    $scoreResult = "";
                    $failureReason = array();

                    $attributes = json_decode($shipment['attributes'], true);

                    foreach ($results as $result) {

                        // matching reported and reference results
                        if (isset($result['reported_result']) && $result['reported_result'] != null) {
                            if ($result['reference_result'] == $result['reported_result']) {
                                $totalScore += $result['sample_score'];
                            } else {
                                if ($result['sample_score'] > 0) {
                                    $failureReason[]['warning'] = "Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                                }
                            }
                        }
                        $maxScore += $result['sample_score'];

                        // checking if mandatory fields were entered and were entered right
                        if ($result['mandatory'] == 1) {
                            if ((!isset($result['reported_result']) || $result['reported_result'] == "" || $result['reported_result'] == null)) {
                                $mandatoryResult = 'Fail';
                                $failureReason[]['warning'] = "Mandatory Sample <strong>" . $result['sample_label'] . "</strong> was not reported";
                            }
                            //else if(($result['reference_result'] != $result['reported_result'])){
                            //	$mandatoryResult = 'Fail';
                            //	$failureReason[]= "Mandatory Sample <strong>".$result['sample_label']."</strong> was reported wrongly";
                            //}
                        }
                    }

                    // checking if all LOT details were entered
                    if (!isset($results[0]['lot_no_1']) || $results[0]['lot_no_1'] == "" || $results[0]['lot_no_1'] == null) {
                        $lotResult = 'Fail';
                        $failureReason[]['warning'] = "<strong>Lot No. 1</strong> was not reported";
                    }
                    if (!isset($results[0]['lot_no_2']) || $results[0]['lot_no_2'] == "" || $results[0]['lot_no_2'] == null) {
                        $lotResult = 'Fail';
                        $failureReason[]['warning'] = "<strong>Lot No. 2</strong> was not reported";
                    }
                    if (!isset($results[0]['lot_no_3']) || $results[0]['lot_no_3'] == "" || $results[0]['lot_no_3'] == null) {
                        $lotResult = 'Fail';
                        $failureReason[]['warning'] = "<strong>Lot No. 3</strong> was not reported";
                    }

                    // checking test kit expiry dates

                    $testedOn = new Zend_Date($results[0]['shipment_test_date'], Zend_Date::ISO_8601);
                    $testDate = $testedOn->toString('dd-MMM-YYYY');
                    $expDate1 = "";
                    if (trim(strtotime($results[0]['exp_date_1'])) != "") {
                        $expDate1 = new Zend_Date($results[0]['exp_date_1'], Zend_Date::ISO_8601);
                    }
                    $expDate2 = "";
                    if (trim(strtotime($results[0]['exp_date_2'])) != "") {
                        $expDate2 = new Zend_Date($results[0]['exp_date_2'], Zend_Date::ISO_8601);
                    }

                    $expDate3 = "";
                    if (trim(strtotime($results[0]['exp_date_3'])) != "") {
                        $expDate3 = new Zend_Date($results[0]['exp_date_3'], Zend_Date::ISO_8601);
                    }


                    $testKitName = $db->fetchCol($db->select()->from('r_dbs_eia', 'eia_name')->where("eia_id = '" . $results[0]['eia_1'] . "'"));
                    $testKit1 = $testKitName[0];
                    $testKit2 = "";
                    if (trim($results[0]['eia_2']) != 0) {
                        $testKitName = $db->fetchCol($db->select()->from('r_dbs_eia', 'eia_name')->where("eia_id = '" . $results[0]['eia_2'] . "'"));
                        $testKit2 = $testKitName[0];
                    }

                    $testKit3 = "";
                    if (trim($results[0]['eia_3']) != 0) {
                        $testKitName = $db->fetchCol($db->select()->from('r_dbs_eia', 'eia_name')->where("eia_id = '" . $results[0]['eia_3'] . "'"));
                        $testKit3 = $testKitName[0];
                    }
                    if ($expDate1 != "") {
                        if ($testedOn->isLater($expDate1)) {
                            $difference = $testedOn->sub($expDate1);

                            $measure = new Zend_Measure_Time($difference->toValue(), Zend_Measure_Time::SECOND);
                            $measure->convertTo(Zend_Measure_Time::DAY);

                            $testKitExpiryResult = 'Fail';
                            $failureReason[]['warning'] = "EIA 1 (<strong>" . $testKit1 . "</strong>) expired " . round($measure->getValue()) . " days before the test date " . $testDate;
                        }
                    }
                    $testedOn = new Zend_Date($results[0]['shipment_test_date'], Zend_Date::ISO_8601);
                    $testDate = $testedOn->toString('dd-MMM-YYYY');
                    if ($expDate2 != "") {
                        if ($testedOn->isLater($expDate2)) {
                            $difference = $testedOn->sub($expDate2);

                            $measure = new Zend_Measure_Time($difference->toValue(), Zend_Measure_Time::SECOND);
                            $measure->convertTo(Zend_Measure_Time::DAY);

                            $testKitExpiryResult = 'Fail';
                            $failureReason[]['warning'] = "EIA 2 (<strong>" . $testKit2 . "</strong>) expired " . round($measure->getValue()) . " days before the test date " . $testDate;
                        }
                    }

                    $testedOn = new Zend_Date($results[0]['shipment_test_date'], Zend_Date::ISO_8601);
                    $testDate = $testedOn->toString('dd-MMM-YYYY');
                    if ($expDate3 != "") {
                        if ($testedOn->isLater($expDate3)) {
                            $difference = $testedOn->sub($expDate3);

                            $measure = new Zend_Measure_Time($difference->toValue(), Zend_Measure_Time::SECOND);
                            $measure->convertTo(Zend_Measure_Time::DAY);

                            $testKitExpiryResult = 'Fail';
                            $failureReason[]['warning'] = "EIA 3 (<strong>" . $testKit3 . "</strong>) expired " . round($measure->getValue()) . " days before the test date " . $testDate;
                        }
                    }
                    //checking if testkits were repeated
                    if (($testKit1 == $testKit2) && ($testKit2 == $testKit3)) {
                        //$testKitRepeatResult = 'Fail';
                        $failureReason[]['warning'] = "<strong>$testKit1</strong> repeated for all three EIA";
                    } else {
                        if (($testKit1 == $testKit2) && $testKit1 != "" && $testKit2 != "") {
                            //$testKitRepeatResult = 'Fail';
                            $failureReason[]['warning'] = "<strong>$testKit1</strong> repeated as EIA 1 and EIA 2";
                        }
                        if (($testKit2 == $testKit3) && $testKit2 != "" && $testKit3 != "") {
                            //$testKitRepeatResult = 'Fail';
                            $failureReason[]['warning'] = "<strong>$testKit2</strong> repeated as EIA 2 and EIA 3";
                        }
                        if (($testKit1 == $testKit3) && $testKit1 != "" && $testKit3 != "") {
                            //$testKitRepeatResult = 'Fail';
                            $failureReason[]['warning'] = "<strong>$testKit1</strong> repeated as EIA 1 and EIA 3";
                        }
                    }

                    // checking if total score and maximum scores are the same
                    if ($totalScore != $maxScore) {
                        $scoreResult = 'Fail';
                        $failureReason[]['warning'] = "Participant did not meet the score criteria (Participant Score - <strong>$totalScore</strong> and Required Score - <strong>$maxScore</strong>)";
                    } else {
                        $scoreResult = 'Pass';
                    }

                    // if any of the results have failed, then the final result is fail
                    if ($scoreResult == 'Fail' || $mandatoryResult == 'Fail' || $lotResult == 'Fail' || $testKitExpiryResult == 'Fail') {
                        $finalResult = 2;
                    } else {
                        $finalResult = 1;
                    }
                    $shipmentResult[$counter]['shipment_score'] = $totalScore;
                    $shipmentResult[$counter]['max_score'] = $maxScore;

                    $fRes = $db->fetchCol($db->select()->from('r_results', array('result_name'))->where('result_id = ' . $finalResult));

                    $shipmentResult[$counter]['display_result'] = $fRes[0];
                    $shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);

                    // let us update the total score in DB
                    $nofOfRowsUpdated = $db->update('shipment_participant_map', array('shipment_score' => $totalScore, 'final_result' => $finalResult, 'failure_reason' => $failureReason), "map_id = " . $shipment['map_id']);
                    $counter++;
                } else {
                    $failureReason = array('warning' => "Response was submitted after the last response date.");
                    $db->update('shipment_participant_map', array('failure_reason' => json_encode($failureReason)), "map_id = " . $shipment['map_id']);
                }
            }
            $db->update('shipment', array('max_score' => $maxScore), "shipment_id = " . $shipmentId);
        } else if ($shipmentResult[0]['scheme_type'] == 'dts') {
			$shipmentResult = $this->evaluateDtsHivSerology($shipmentResult,$shipmentId);
        } else if ($shipmentResult[0]['scheme_type'] == 'vl') {
            $shipmentResult = $this->evaluateDtsViralLoad($shipmentResult,$shipmentId, $reEvaluate);
        }

        return $shipmentResult;
    }

    public function editEvaluation($shipmentId, $participantId, $scheme) {
        $participantService = new Application_Service_Participants();
        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();


        $participantData = $participantService->getParticipantDetails($participantId);
        $shipmentData = $schemeService->getShipmentData($shipmentId, $participantId);

        if ($scheme == 'eid') {
            $possibleResults = $schemeService->getPossibleResults('eid');
            $evalComments = $schemeService->getSchemeEvaluationComments('eid');
            $results = $schemeService->getEidSamples($shipmentId, $participantId);
        } else if ($scheme == 'vl') {
            $possibleResults = "";
            $evalComments = $schemeService->getSchemeEvaluationComments('vl');
            $results = $schemeService->getVlSamples($shipmentId, $participantId);
        } else if ($scheme == 'dts') {
            $possibleResults = $schemeService->getPossibleResults('dts');
            $evalComments = $schemeService->getSchemeEvaluationComments('dts');
            $results = $schemeService->getDtsSamples($shipmentId, $participantId);
        } else if ($scheme == 'dbs') {
            $possibleResults = $schemeService->getPossibleResults('dbs');
            $evalComments = $schemeService->getSchemeEvaluationComments('dbs');
            $results = $schemeService->getDbsSamples($shipmentId, $participantId);
        }


        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('s' => 'shipment'))
                ->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id')
                ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('fullscore' => new Zend_Db_Expr("SUM(if(s.max_score = sp.shipment_score, 1, 0))")))
                ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id')
                ->where("sp.shipment_id = ?", $shipmentId)
                ->where("substring(sp.evaluation_status,4,1) != '0'")->group('sp.map_id');
        $shipmentOverall = $db->fetchAll($sql);

        $noOfParticipants = count($shipmentOverall);
        $numScoredFull = $shipmentOverall[0]['fullscore'];
        $maxScore = $shipmentOverall[0]['max_score'];
        

        $controlRes = array();
        $sampleRes = array();
        if (isset($results) && count($results) > 0) {
            foreach ($results as $res) {
                if ($res['control'] == 1) {
                    $controlRes[] = $res;
                } else {
                    $sampleRes[] = $res;
                }
            }
        }



        return array('participant' => $participantData,
            'shipment' => $shipmentData,
            'possibleResults' => $possibleResults,
            'totalParticipants' => $noOfParticipants,
            'fullScorers' => $numScoredFull,
            'maxScore' => $maxScore,
            'evalComments' => $evalComments,
            'controlResults' => $controlRes,
            'results' => $sampleRes
                );
    }

    public function viewEvaluation($shipmentId, $participantId, $scheme) {


        $participantService = new Application_Service_Participants();
        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();


        $participantData = $participantService->getParticipantDetails($participantId);
        $shipmentData = $schemeService->getShipmentData($shipmentId, $participantId);



        if ($scheme == 'eid') {
            $possibleResults = $schemeService->getPossibleResults('eid');
            $evalComments = $schemeService->getSchemeEvaluationComments('eid');
            $results = $schemeService->getEidSamples($shipmentId, $participantId);
        } else if ($scheme == 'vl') {
            $possibleResults = "";
            $evalComments = $schemeService->getSchemeEvaluationComments('vl');
            $results = $schemeService->getVlSamples($shipmentId, $participantId);
        } else if ($scheme == 'dts') {
            $possibleResults = $schemeService->getPossibleResults('dts');
            $evalComments = $schemeService->getSchemeEvaluationComments('dts');
            $results = $schemeService->getDtsSamples($shipmentId, $participantId);
        } else if ($scheme == 'dbs') {
            $possibleResults = $schemeService->getPossibleResults('dbs');
            $evalComments = $schemeService->getSchemeEvaluationComments('dbs');
            $results = $schemeService->getDtsSamples($shipmentId, $participantId);
        }


        $controlRes = array();
        $sampleRes = array();

        if (isset($results) && count($results) > 0) {
            foreach ($results as $res) {
                if ($res['control'] == 1) {
                    $controlRes[] = $res;
                } else {
                    $sampleRes[] = $res;
                }
            }
        }


        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $config = new Zend_Config_Ini(APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini", APPLICATION_ENV);
        $sql = $db->select()->from(array('s' => 'shipment'))
                ->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id')
                ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('fullscore' => new Zend_Db_Expr("(if((sp.shipment_score+sp.documentation_score) >= " . $config->evaluation->dts->passPercentage . ", 1, 0))")))
                ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id')
                ->where("sp.shipment_id = ?", $shipmentId)
                ->where("substring(sp.evaluation_status,4,1) != '0'")
               ->group('sp.map_id');
        
        $shipmentOverall = $db->fetchAll($sql);
   //     Zend_Debug::dump($shipmentOverall);die;

        $noOfParticipants = count($shipmentOverall);
        $numScoredFull = 0;
        foreach ($shipmentOverall as $shipment) {
            $numScoredFull += $shipment['fullscore'];
        }

        return array('participant' => $participantData,
            'shipment' => $shipmentData,
            'possibleResults' => $possibleResults,
            'totalParticipants' => $noOfParticipants,
            'fullScorers' => $numScoredFull,
            'evalComments' => $evalComments,
            'controlResults' => $controlRes,
            'results' => $sampleRes                
                );
    }

    public function updateShipmentResults($params) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $admin = $authNameSpace->primary_email;
        $size = count($params['sampleId']);


        if ($params['scheme'] == 'eid') {
			
            $attributes = array("sample_rehydration_date" => Pt_Commons_General::dateFormat($params['sampleRehydrationDate']),
                "extraction_assay" => $params['extractionAssay'],
                "detection_assay" => $params['detectionAssay'],
				"extraction_assay_expiry_date" => Pt_Commons_General::dateFormat($params['extractionAssayExpiryDate']),
                "detection_assay_expiry_date" => Pt_Commons_General::dateFormat($params['detectionAssayExpiryDate']),
                "extraction_assay_lot_no" => $params['extractionAssayLotNo'],
                "detection_assay_lot_no" => $params['detectionAssayLotNo'],
				);
			
			if(isset($params['otherAssay']) && $params['otherAssay'] != ""){
				$attributes['other_assay'] = $params['otherAssay'];
			}
			if(isset($params['uploadedFilePath']) && $params['uploadedFilePath'] != ""){
				$attributes['uploadedFilePath'] = $params['uploadedFilePath'];
			}

            $attributes = json_encode($attributes);
            $mapData = array(
                "shipment_receipt_date" => Pt_Commons_General::dateFormat($params['receiptDate']),
                "shipment_test_date" => Pt_Commons_General::dateFormat($params['testDate']),
                "attributes" => $attributes,
				"supervisor_approval" => $params['supervisorApproval'],
				"participant_supervisor" => $params['participantSupervisor'],
				"user_comment" => $params['userComments'],
				"updated_by_admin" => $admin,
			    "updated_on_admin" => new Zend_Db_Expr('now()')
            );
			
			if(isset($params['customField1']) && trim($params['customField1']) != ""){
				$mapData['custom_field_1'] = $params['customField1'];
			}
			
			if(isset($params['customField2']) && trim($params['customField2']) != ""){
				$mapData['custom_field_2'] = $params['customField2'];
			}				

            $db->update('shipment_participant_map', $mapData, "map_id = " . $params['smid']);
			
            for ($i = 0; $i < $size; $i++) {
                $db->update('response_result_eid', array('reported_result' => $params['reported'][$i], 'updated_by' => $admin, 'updated_on' => new Zend_Db_Expr('now()')), "shipment_map_id = " . $params['smid'] . " AND sample_id = " . $params['sampleId'][$i]);
            }
        } else if ($params['scheme'] == 'dts') {
			
			
			$attributes["sample_rehydration_date"] = Pt_Commons_General::dateFormat($params['rehydrationDate']);
			$attributes["algorithm"] = $params['algorithm'];
			$attributes = json_encode($attributes);
	
			$mapdata = array(
				"shipment_receipt_date" => Pt_Commons_General::dateFormat($params['receivedOn']),
				"shipment_test_date" => Pt_Commons_General::dateFormat($params['testedOn']),
				"attributes" => $attributes,
				"supervisor_approval" => $params['supervisorApproval'],
				"participant_supervisor" => $params['participantSupervisor'],
				"user_comment" => $params['userComments'],
				"updated_by_admin" => $admin,
				"updated_on_admin" => new Zend_Db_Expr('now()')
			);
			if(isset($params['customField1']) && trim($params['customField1']) != ""){
				$mapdata['custom_field_1'] = $params['customField1'];
			}
			
			if(isset($params['customField2']) && trim($params['customField2']) != ""){
				$mapdata['custom_field_2'] = $params['customField2'];
			}
			$db->update('shipment_participant_map', $mapdata, "map_id = " . $params['smid']);			
			
            for ($i = 0; $i < $size; $i++) {
                $db->update('response_result_dts', array(
                    'test_kit_name_1' => $params['test_kit_name_1'],
                    'lot_no_1' => $params['lot_no_1'],
                    'exp_date_1' => Pt_Commons_General::dateFormat($params['exp_date_1']),
                    'test_result_1' => $params['test_result_1'][$i],
                    'test_kit_name_2' => $params['test_kit_name_2'],
                    'lot_no_2' => $params['lot_no_2'],
                    'exp_date_2' => Pt_Commons_General::dateFormat($params['exp_date_2']),
                    'test_result_2' => $params['test_result_2'][$i],
                    'test_kit_name_3' => $params['test_kit_name_3'],
                    'lot_no_3' => $params['lot_no_3'],
                    'exp_date_3' => Pt_Commons_General::dateFormat($params['exp_date_3']),
                    'test_result_3' => $params['test_result_3'][$i],
                    'reported_result' => $params['reported_result'][$i],
                    'updated_by' => $admin,
                    'updated_on' => new Zend_Db_Expr('now()')), "shipment_map_id = " . $params['smid'] . " AND sample_id = " . $params['sampleId'][$i]);
            }
        } else if ($params['scheme'] == 'vl') {
		   
			$attributes = array(
				"sample_rehydration_date" => Pt_Commons_General::dateFormat($params['sampleRehydrationDate']),
                "vl_assay" => $params['vlAssay'],
                "assay_lot_number" => $params['assayLotNumber'],
                "assay_expiration_date" => Pt_Commons_General::dateFormat($params['assayExpirationDate']),
                "specimen_volume" => $params['specimenVolume']
				);
			
			if(isset($params['otherAssay']) && $params['otherAssay'] != ""){
				$attributes['other_assay'] = $params['otherAssay'];
			}
			if(isset($params['uploadedFilePath']) && $params['uploadedFilePath'] != ""){
				$attributes['uploadedFilePath'] = $params['uploadedFilePath'];
			}
			
            $attributes = json_encode($attributes);
            $mapData = array(
                "shipment_receipt_date" => Pt_Commons_General::dateFormat($params['receiptDate']),
                "shipment_test_date" => Pt_Commons_General::dateFormat($params['testDate']),
                "attributes" => $attributes,
				"supervisor_approval" => $params['supervisorApproval'],
				"participant_supervisor" => $params['participantSupervisor'],
				"user_comment" => $params['userComments'],
				"updated_by_admin" => $admin,
			   "updated_on_admin" => new Zend_Db_Expr('now()')
            );
			
			if(isset($params['customField1']) && trim($params['customField1']) != ""){
				$mapData['custom_field_1'] = $params['customField1'];
			}
			
			if(isset($params['customField2']) && trim($params['customField2']) != ""){
				$mapData['custom_field_2'] = $params['customField2'];
			}				

            $db->update('shipment_participant_map', $mapData, "map_id = " . $params['smid']);	

            for ($i = 0; $i < $size; $i++) {

                $db->update('response_result_vl', array(
                    'reported_viral_load' => $params['reported'][$i],
                    'updated_by' => $admin,
                    'updated_on' => new Zend_Db_Expr('now()')), "shipment_map_id = " . $params['smid'] . " AND sample_id = " . $params['sampleId'][$i]);
            }
        } else if ($params['scheme'] == 'dbs') {
            for ($i = 0; $i < $size; $i++) {
                $db->update('response_result_dbs', array(
                    'eia_1' => $params['eia_1'],
                    'lot_no_1' => $params['lot_no_1'],
                    'exp_date_1' => Pt_Commons_General::dateFormat($params['exp_date_1']),
                    'od_1' => $params['od_1'][$i],
                    'cutoff_1' => $params['cutoff_1'][$i],
                    'eia_2' => $params['eia_2'],
                    'lot_no_2' => $params['lot_no_2'],
                    'exp_date_2' => Pt_Commons_General::dateFormat($params['exp_date_2']),
                    'od_2' => $params['od_2'][$i],
                    'cutoff_2' => $params['cutoff_2'][$i],
                    'eia_3' => $params['eia_3'],
                    'lot_no_3' => $params['lot_no_3'],
                    'exp_date_3' => Pt_Commons_General::dateFormat($params['exp_date_3']),
                    'od_3' => $params['od_3'][$i],
                    'cutoff_3' => $params['cutoff_3'][$i],
                    'wb' => $params['wb'],
                    'wb_lot' => $params['wb_lot'],
                    'wb_exp_date' => Pt_Commons_General::dateFormat($params['wb_exp_date']),
                    'wb_160' => $params['wb_160'][$i],
                    'wb_120' => $params['wb_120'][$i],
                    'wb_66' => $params['wb_66'][$i],
                    'wb_55' => $params['wb_55'][$i],
                    'wb_51' => $params['wb_51'][$i],
                    'wb_41' => $params['wb_41'][$i],
                    'wb_31' => $params['wb_31'][$i],
                    'wb_24' => $params['wb_24'][$i],
                    'wb_17' => $params['wb_17'][$i],
                    'reported_result' => $params['reported_result'][$i],
                    'updated_by' => $admin,
                    'updated_on' => new Zend_Db_Expr('now()')), "shipment_map_id = " . $params['smid'] . " AND sample_id = " . $params['sampleId'][$i]);
            }
        }

        $params['isFollowUp'] = (isset($params['isFollowUp']) && $params['isFollowUp'] != "" ) ? $params['isFollowUp'] : "no";

		$updateArray = array('evaluation_comment' => $params['comment'], 'optional_eval_comment' => $params['optionalComments'], 'is_followup' => $params['isFollowUp'], 'is_excluded' => $params['isExcluded'], 'updated_by_admin' => $admin, 'updated_on_admin' => new Zend_Db_Expr('now()'));
		if($params['isExcluded'] == 'yes'){
			$updateArray['final_result'] = 3;
		}
		
        $db->update('shipment_participant_map', $updateArray, "map_id = " . $params['smid']);
    }

    public function updateShipmentComment($shipmentId, $comment) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $admin = $authNameSpace->primary_email;
        $noOfRows = $db->update('shipment', array('shipment_comment' => $comment, 'updated_by_admin' => $admin, 'updated_on_admin' => new Zend_Db_Expr('now()')), "shipment_id = " . $shipmentId);
        if ($noOfRows > 0) {
            return "Comment updated";
        } else {
            return "Unable to update shipment comment. Please try again later.";
        }
    }

    public function updateShipmentStatus($shipmentId, $status) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $admin = $authNameSpace->primary_email;
        $noOfRows = $db->update('shipment', array('status' => $status, 'updated_by_admin' => $admin, 'updated_on_admin' => new Zend_Db_Expr('now()')), "shipment_id = " . $shipmentId);
        if ($noOfRows > 0) {
            return "Status updated";
        } else {
            return "Unable to update shipment status. Please try again later.";
        }
    }

    public function getShipmentToEvaluateReports($shipmentId, $reEvaluate = false) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('s' => 'shipment', array('shipment_id', 'shipment_code', 'status', 'number_of_samples')))
                ->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('distribution_code', 'distribution_date'))
                ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id')
                ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
                ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('first_name', 'last_name','lab_name','unique_identifier'))
                ->joinLeft(array('res' => 'r_results'), 'res.result_id=sp.final_result')
                ->where("s.shipment_id = ?", $shipmentId)
                ->where("substring(sp.evaluation_status,4,1) != '0'");

        $shipmentResult = $db->fetchAll($sql);
        return $shipmentResult;
    }

    public function getEvaluateReportsInPdf($shipmentId,$sLimit,$sOffset) {
        //echo $shipmentId;die;
        $responseResult = "";
        $vlCalculation = "";
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$schemeService = new Application_Service_Schemes();
        $sql = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date', 's.lastdate_response', 's.max_score', 's.shipment_comment'))
                ->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('d.distribution_id', 'd.distribution_code', 'd.distribution_date'))
                ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('sp.map_id', 'sp.participant_id', 'sp.shipment_test_date', 'sp.shipment_receipt_date', 'sp.shipment_test_report_date', 'sp.final_result', 'sp.failure_reason', 'sp.shipment_score', 'sp.final_result', 'sp.attributes', 'sp.is_followup', 'sp.is_excluded', 'sp.optional_eval_comment', 'sp.evaluation_comment', 'sp.documentation_score','sp.participant_supervisor'))
                ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('sl.scheme_id', 'sl.scheme_name'))
                ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status'))
                ->joinLeft(array('res' => 'r_results'), 'res.result_id=sp.final_result', array('result_name'))
                ->joinLeft(array('ec' => 'r_evaluation_comments'), 'ec.comment_id=sp.evaluation_comment', array('evaluationComments' => 'comment'))
                ->where("s.shipment_id = ?", $shipmentId)
                ->where("substring(sp.evaluation_status,4,1) != '0'");
		 if (isset($sLimit) && isset($sOffset)) {
		    $sql = $sql->limit($sLimit, $sOffset);
		}
        //error_log($sql);die;
        $shipmentResult = $db->fetchAll($sql);

        //Zend_Debug::dump($shipmentResult);die;
        $i = 0;
        //$mapRes="";
        $mapRes = array();
        $penResult = array();
		$vlGraphResult = array();
        foreach ($shipmentResult as $res) {
            $dmResult = $db->fetchAll($db->select()->from(array('pmm' => 'participant_manager_map'))
                            ->join(array('dm' => 'data_manager'), 'dm.dm_id=pmm.dm_id', array('institute'))
                            ->where("pmm.participant_id=" . $res['participant_id']));
            if (isset($res['last_name']) && trim($res['last_name']) != "") {
                $res['last_name'] = "_" . $res['last_name'];
            }

            foreach ($dmResult as $dmRes) {
				$participantFileName = preg_replace('/[^A-Za-z0-9.]/', '-', $res['first_name'] . $res['last_name'] . "-" . $res['map_id']);
				$participantFileName = str_replace(" ", "-", $participantFileName);
                if (count($mapRes) == 0) {
                    $mapRes[$dmRes['dm_id']] = $dmRes['institute'] . "#" . $dmRes['participant_id'] . "#" . $participantFileName;
                } else if (array_key_exists($dmRes['dm_id'], $mapRes)) {
                    $mapRes[$dmRes['dm_id']].="," . $dmRes['institute'] . "#" . $dmRes['participant_id'] . "#" . $participantFileName;
                } else {
                    $mapRes[$dmRes['dm_id']] = $dmRes['institute'] . "#" . $dmRes['participant_id'] . "#" . $participantFileName;
                }
            }
            if ($res['scheme_type'] == 'dbs') {
                $sQuery = $db->select()->from(array('resdbs' => 'response_result_dbs'), array('resdbs.shipment_map_id', 'resdbs.sample_id', 'resdbs.reported_result', 'responseDate' => 'resdbs.created_on'))
                        ->join(array('respr' => 'r_possibleresult'), 'respr.id=resdbs.reported_result', array('labResult' => 'respr.response'))
                        ->join(array('sp' => 'shipment_participant_map'), 'sp.map_id=resdbs.shipment_map_id', array('sp.shipment_id', 'sp.participant_id','sp.participant_supervisor'))
                        ->join(array('refdbs' => 'reference_result_dbs'), 'refdbs.shipment_id=sp.shipment_id and refdbs.sample_id=resdbs.sample_id', array('refdbs.reference_result', 'refdbs.sample_label', 'resdbs.mandatory'))
                        ->join(array('refpr' => 'r_possibleresult'), 'refpr.id=refdbs.reference_result', array('referenceResult' => 'refpr.response'))
                        ->where("resdbs.shipment_map_id = ?", $res['map_id']);
			
               
                $shipmentResult[$i]['responseResult'] = $db->fetchAll($sQuery);
            } else if ($res['scheme_type'] == 'dts') {

                $sQuery = $db->select()->from(array('resdts' => 'response_result_dts'), array('resdts.shipment_map_id', 'resdts.sample_id', 'resdts.reported_result', 'responseDate' => 'resdts.created_on', 'calculated_score','test_kit_name_1','lot_no_1','exp_date_1','test_kit_name_2','lot_no_2','exp_date_2','test_kit_name_3','lot_no_3','exp_date_3','test_result_1','test_result_2','test_result_3'))
                        ->join(array('respr' => 'r_possibleresult'), 'respr.id=resdts.reported_result', array('labResult' => 'respr.response'))
                        ->join(array('sp' => 'shipment_participant_map'), 'sp.map_id=resdts.shipment_map_id', array('sp.shipment_id','sp.shipment_receipt_date', 'sp.participant_id', 'sp.attributes', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_test_date', 'sp.failure_reason'))
                        ->join(array('refdts' => 'reference_result_dts'), 'refdts.shipment_id=sp.shipment_id and refdts.sample_id=resdts.sample_id', array('refdts.reference_result', 'refdts.sample_label', 'refdts.mandatory', 'refdts.sample_score', 'refdts.control'))
						->joinLeft(array('dtstk1' => 'r_testkitname_dts'), 'dtstk1.TestKitName_ID=resdts.test_kit_name_1', array('testkit1'=>'dtstk1.TestKit_Name'))
						->joinLeft(array('dtstk2' => 'r_testkitname_dts'), 'dtstk2.TestKitName_ID=resdts.test_kit_name_2', array('testkit2'=>'dtstk2.TestKit_Name'))
						->joinLeft(array('dtstk3' => 'r_testkitname_dts'), 'dtstk3.TestKitName_ID=resdts.test_kit_name_3', array('testkit3'=>'dtstk3.TestKit_Name'))
                        ->join(array('refpr' => 'r_possibleresult'), 'refpr.id=refdts.reference_result', array('referenceResult' => 'refpr.response'))
                        ->where("resdts.shipment_map_id = ?", $res['map_id']);
			
			 
                $shipmentResult[$i]['responseResult'] = $db->fetchAll($sQuery);
            } else if ($res['scheme_type'] == 'eid') {
				
				$extractionAssay = $schemeService->getEidExtractionAssay();
                $detectionAssay = $schemeService->getEidDetectionAssay();
				$attributes = json_decode($res['attributes'], true);
				
				if(isset($attributes['extraction_assay'])){
					//$shipmentResult[$i]['extractionAssayVal']=$extractionAssay[$attributes['extraction_assay']];
					$shipmentResult[$i]['extractionAssayVal']=(isset($extractionAssay[$attributes['extraction_assay']]) ? $extractionAssay[$attributes['extraction_assay']] : "");
				}
				if(isset($attributes['detection_assay'])){
					
					$shipmentResult[$i]['detectionAssayVal']=(isset($detectionAssay[$attributes['detection_assay']]) ? $detectionAssay[$attributes['detection_assay']] : "");
				}
				
                $sQuery = $db->select()->from(array('reseid' => 'response_result_eid'), array('reseid.shipment_map_id', 'reseid.sample_id', 'reseid.reported_result', 'responseDate' => 'reseid.created_on'))
                        ->join(array('respr' => 'r_possibleresult'), 'respr.id=reseid.reported_result', array('labResult' => 'respr.response'))
                        ->join(array('sp' => 'shipment_participant_map'), 'sp.map_id=reseid.shipment_map_id', array('sp.shipment_id', 'sp.participant_id'))
                        ->join(array('refeid' => 'reference_result_eid'), 'refeid.shipment_id=sp.shipment_id and refeid.sample_id=reseid.sample_id', array('refeid.reference_result', 'refeid.sample_label', 'refeid.mandatory')) 
                        ->join(array('refpr' => 'r_possibleresult'), 'refpr.id=refeid.reference_result', array('referenceResult' => 'refpr.response'))
						->where("refeid.control = 0")
                        ->where("reseid.shipment_map_id = ?", $res['map_id'])
						->order(array('refeid.sample_id'));
			
				//$vlAssayResultSet[$responseAssay['vl_assay']]
                //error_log($sQuery);
                $shipmentResult[$i]['responseResult'] = $db->fetchAll($sQuery);
				
            } else if ($res['scheme_type'] == 'vl') {
                $vlAssayResultSet = $schemeService->getVlAssay();
                $vlAssayList = array();
				
                $vlRange = $schemeService->getVlRange($shipmentId);
                $results = $schemeService->getVlSamples($shipmentId, $res['participant_id']);
                //$assayResults = $schemeService->getShipmentParticipantBassedAssay($shipmentId);
				
                $attributes = json_decode($res['attributes'], true);
				
				
				$sql = $db->select()->from(array('ref' => 'reference_result_vl'),array('sample_id','ref.sample_label'))
					->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id',array('*'))
					->join(array('sp' => 'shipment_participant_map'),'s.shipment_id=sp.shipment_id',array('sp.map_id','sp.attributes'))
					->join(array('p' => 'participant'),'p.participant_id=sp.participant_id',array('p.unique_identifier'))
					->joinLeft(array('res' => 'response_result_vl'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('reported_viral_load'))
					->where("is_excluded=?",'no')
					->where('sp.shipment_id = ? ', $shipmentId);
				$spmResult=$db->fetchAll($sql);
				
				$vlGraphResult = array();
				foreach($spmResult as $val){
					$valAttributes = json_decode($val['attributes'], true);
					if($attributes['vl_assay']==$valAttributes['vl_assay']){
						if (array_key_exists($val['sample_label'], $vlGraphResult)) {
							if (isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low']) && $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low'] <= $val['reported_viral_load'] && isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high']) && $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high'] >= $val['reported_viral_load']) {
                                $vlGraphResult[$val['sample_label']]['vl'][]=$val['reported_viral_load'];
                            } else {
                                $vlGraphResult[$val['sample_label']]['NA'][]=$val['reported_viral_load'];
                            }
							//$vlGraphResult[$val['sample_label']]['pId'][]="lab ".$val['unique_identifier'];
						}else{
							$vlGraphResult[$val['sample_label']]=array();
							if (isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low']) && $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low'] <= $val['reported_viral_load'] && isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high']) && $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high'] >= $val['reported_viral_load']) {
                                $vlGraphResult[$val['sample_label']]['vl'][]=$val['reported_viral_load'];
                            } else {
                                $vlGraphResult[$val['sample_label']]['NA'][]=$val['reported_viral_load'];
                            }
							if(isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low'])){
								$vlGraphResult[$val['sample_label']]['low']= $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low'];
							}
							if(isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high'])){
								$vlGraphResult[$val['sample_label']]['high']= $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high'];
							}
						}
					}
				}
				//print_r($vlGraphResult);
				
				//<-- count no.of labs participans in particular sample
				$cQuery = $db->select()->from(array('ref' => 'reference_result_vl'),array('sample_id','ref.sample_label'))
					->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id',array('s.*'))
					->join(array('sp' => 'shipment_participant_map'),'s.shipment_id=sp.shipment_id',array('sp.map_id','sp.attributes'))
					->joinLeft(array('res' => 'response_result_vl'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('reported_viral_load'))
					->where('sp.shipment_id = ? ', $shipmentId);
				
				$cResult=$db->fetchAll($cQuery);
				
				$labResult = array();
				foreach($cResult as $val){
					$valAttributes = json_decode($val['attributes'], true);
					if($attributes['vl_assay']==$valAttributes['vl_assay']){
						if (array_key_exists($val['sample_label'], $labResult)) {
							$labResult[$val['sample_label']]+=1;
						}else{
							$labResult[$val['sample_label']]=array();
							$labResult[$val['sample_label']]=1;
						}
					}
				}
				//-------->
				
				//print_r($labResult);
				//die;
                $counter = 0;
                $toReturn = array();
                foreach ($results as $result) {
                    //$toReturn = array();
                    $responseAssay = json_decode($result['attributes'], true);
                    $toReturn[$counter]['vl_assay'] = $vlAssayResultSet[$responseAssay['vl_assay']];
                    $responseAssay = $responseAssay['vl_assay'];
					
					$vlGraphResult[$result['sample_label']]['pVal']=$result['reported_viral_load'];
					//print_r($vlGraphResult[$result['sample_label']]['vl']);die;
					
                    $toReturn[$counter]['sample_label'] = $result['sample_label'];
                    $toReturn[$counter]['shipment_map_id'] = $result['map_id'];
                    $toReturn[$counter]['shipment_id'] = $result['shipment_id'];
                    $toReturn[$counter]['responseDate'] = $result['responseDate'];
                    $toReturn[$counter]['shipment_score'] = $result['shipment_score'];
                    $toReturn[$counter]['shipment_test_date'] = $result['shipment_test_date'];
                    $toReturn[$counter]['shipment_receipt_date'] = $result['shipment_receipt_date'];
                    $toReturn[$counter]['max_score'] = $result['max_score'];
                    $toReturn[$counter]['reported_viral_load'] = $result['reported_viral_load'];
                    $toReturn[$counter]['no_of_participants'] = $labResult[$result['sample_label']];
                    if (isset($vlRange[$responseAssay])) {
                        // matching reported and low/high limits
                        if (isset($result['reported_viral_load']) && $result['reported_viral_load'] != null) {
                            if ($vlRange[$responseAssay][$result['sample_id']]['low'] <= $result['reported_viral_load'] && $vlRange[$responseAssay][$result['sample_id']]['high'] >= $result['reported_viral_load']) {
                                $grade = 'Acceptable';
                            } else {
                                $grade = 'Not Acceptable';
                            }
                        }

                        if (isset($result['reported_viral_load']) && $result['reported_viral_load'] != null && trim($result['reported_viral_load']) != null) {
                            if ($vlRange[$responseAssay][$result['sample_id']]['low'] <= $result['reported_viral_load'] && $vlRange[$responseAssay][$result['sample_id']]['high'] >= $result['reported_viral_load']) {
                                $grade = 'Acceptable';
                            } else {
                                if ($result['sample_score'] > 0) {
                                    $grade = 'Not Acceptable';
                                } else {
                                    $grade = '-';
                                }
                            }
                        }else{
							$grade = 'Not Acceptable';
						}

                        $toReturn[$counter]['low'] = $vlRange[$responseAssay][$result['sample_id']]['low'];
                        $toReturn[$counter]['high'] = $vlRange[$responseAssay][$result['sample_id']]['high'];
                        $toReturn[$counter]['sd'] = $vlRange[$responseAssay][$result['sample_id']]['sd'];
                        $toReturn[$counter]['mean'] = $vlRange[$responseAssay][$result['sample_id']]['mean'];
                    } else {
                        $toReturn[$counter]['low'] = 'Not Applicable';
                        $toReturn[$counter]['high'] = 'Not Applicable';
                        $toReturn[$counter]['sd'] = 'Not Applicable';
                        $toReturn[$counter]['mean'] = 'Not Applicable';
                        $grade = 'Not Applicable';
                    }
                    $toReturn[$counter]['grade'] = $grade;
					
                    $counter++;
                }
				
                $shipmentResult[$i]['responseResult'] = $toReturn;
				
            }
			
            $i++;
            $db->update('shipment_participant_map', array('report_generated' => 'yes'), "map_id=" . $res['map_id']);
            $db->update('shipment', array('status' => 'evaluated'), "shipment_id=" . $shipmentId);
        }
        
		
        $result = array('shipment' => $shipmentResult,'dmResult' => $mapRes,'vlGraphResult'=>$vlGraphResult);

        return $result;
    }

    public function getSummaryReportsInPdf($shipmentId) {
        $responseResult = "";
		$vlCalculation = "";
		$penResult = "";
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date', 's.lastdate_response', 's.max_score'))
                ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('sl.scheme_name'))
                ->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('d.distribution_code'))
                ->where("s.shipment_id = ?", $shipmentId);
		
        $shipmentResult = $db->fetchRow($sql);
        $i = 0;
        if ($shipmentResult != "") {
            $db->update('shipment', array('status' => 'evaluated'), "shipment_id = " . $shipmentId);
            if ($shipmentResult['scheme_type'] == 'dbs') {
                $sql = $db->select()->from(array('refdbs' => 'reference_result_dbs'), array('refdbs.reference_result', 'refdbs.sample_label', 'refdbs.mandatory'))
                        ->join(array('refpr' => 'r_possibleresult'), 'refpr.id=refdbs.reference_result', array('referenceResult' => 'refpr.response'))
                        ->where("refdbs.shipment_id = ?", $shipmentResult['shipment_id']);
                $sqlRes = $db->fetchAll($sql);

                $shipmentResult['referenceResult'] = $sqlRes;
                //Zend_Debug::dump($shipmentResult['referenceResult']);die;

                $sQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.attributes'))
                        ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status'))
                        ->joinLeft(array('res' => 'r_results'), 'res.result_id=spm.final_result', array('result_name'))
                        ->where("spm.shipment_id = ?", $shipmentId)
                        ->where("substring(spm.evaluation_status,4,1) != '0'")
                        ->where("spm.final_result IS NOT NULL")
                        ->where("spm.final_result!=''")
                        ->group('spm.map_id');
                $sQueryRes = $db->fetchAll($sQuery);
                //Zend_Debug::dump($sQueryRes);die;

                if (count($sQueryRes) > 0) {

                    $tQuery = $db->select()->from(array('refdbs' => 'reference_result_dbs'), array('refdbs.sample_id', 'refdbs.sample_label'))
                            ->join(array('resdbs' => 'response_result_dbs'), 'resdbs.sample_id=refdbs.sample_id', array('correctRes' => new Zend_Db_Expr("SUM(CASE WHEN resdbs.reported_result=refdbs.reference_result THEN 1 ELSE 0 END)")))
                            ->join(array('spm' => 'shipment_participant_map'), 'resdbs.shipment_map_id=spm.map_id and refdbs.shipment_id=spm.shipment_id', array())
                            ->where("spm.shipment_id = ?", $shipmentId)
                            ->where("spm.final_result IS NOT NULL")
                            ->where("spm.final_result!=''")
                            ->where("substring(spm.evaluation_status,4,1) != '0'")
                            ->group(array("refdbs.sample_id"));

                    $shipmentResult['summaryResult'][] = $sQueryRes;
                    //Zend_Debug::dump($shipmentResult['summaryResult']);die;
                    $shipmentResult['summaryResult'][count($shipmentResult['summaryResult']) - 1]['correctCount'] = $db->fetchAll($tQuery);


                    $rQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id'))
                            ->join(array('resdbs' => 'response_result_dbs'), 'resdbs.shipment_map_id=spm.map_id', array('resdbs.eia_1', 'resdbs.eia_2', 'resdbs.eia_3', 'resdbs.wb'))
                            ->where("substring(spm.evaluation_status,4,1) != '0'")
                            ->where("spm.final_result IS NOT NULL")
                            ->where("spm.final_result!=''")
                            ->where("spm.shipment_id = ?", $shipmentId)
                            ->group('spm.map_id');

                    $rQueryRes = $db->fetchAll($rQuery);
                    $eiaEiaEiaWb = '';
                    $eiaEiaEia = '';
                    $eiaEiaWb = '';
                    $eiaEia = '';
                    $eiaWb = '';
                    $eia = '';
                    foreach ($rQueryRes as $rVal) {
                        if ($rVal['eia_1'] != 0 && $rVal['eia_2'] != 0 && $rVal['eia_3'] != 0 && $rVal['wb'] != 0) {
                            ++$eiaEiaEiaWb;
                        } elseif ($rVal['eia_1'] != 0 && $rVal['eia_2'] != 0 && $rVal['eia_3'] != 0) {
                            ++$eiaEiaEia;
                        } elseif ($rVal['eia_1'] != 0 && ($rVal['eia_2'] != 0 || $rVal['eia_3'] != 0) && $rVal['wb'] != 0) {
                            ++$eiaEiaWb;
                        } elseif ($rVal['eia_1'] != 0 && ($rVal['eia_2'] != 0 || $rVal['eia_3'] != 0)) {
                            ++$eiaEia;
                        } elseif ($rVal['eia_1'] != 0 && $rVal['wb'] != 0) {
                            ++$eiaWb;
                        } elseif ($rVal['eia_1'] != 0) {
                            ++$eia;
                        }
                    }

                    $shipmentResult['dbsPieChart']['EIA/EIA/EIA/WB'] = $eiaEiaEiaWb;
                    $shipmentResult['dbsPieChart']['EIA/EIA/EIA'] = $eiaEiaEia;
                    $shipmentResult['dbsPieChart']['EIA/EIA/WB'] = $eiaEiaWb;
                    $shipmentResult['dbsPieChart']['EIA/EIA'] = $eiaEia;
                    $shipmentResult['dbsPieChart']['EIA/WB'] = $eiaWb;
                    $shipmentResult['dbsPieChart']['EIA'] = $eia;
                    //Zend_Debug::dump($shipmentResult['dbsPieChart']);die;
                }
                //die;
            } else if ($shipmentResult['scheme_type'] == 'dts') {
                $sql = $db->select()->from(array('refdts' => 'reference_result_dts'), array('refdts.reference_result', 'refdts.sample_label', 'refdts.mandatory'))
                        ->join(array('refpr' => 'r_possibleresult'), 'refpr.id=refdts.reference_result', array('referenceResult' => 'refpr.response'))
                        ->where("refdts.shipment_id = ?", $shipmentResult['shipment_id']);
                $sqlRes = $db->fetchAll($sql);

                $shipmentResult['referenceResult'] = $sqlRes;

                $sQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.attributes','spm.is_excluded'))
                        ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status'))
                        ->joinLeft(array('res' => 'r_results'), 'res.result_id=spm.final_result', array('result_name'))
                        ->where("spm.shipment_id = ?", $shipmentId)
                        ->where("spm.final_result IS NOT NULL")
                        ->where("spm.final_result!=''")
                        // ->where("spm.final_result = ?",'2')
                        ->where("substring(spm.evaluation_status,4,1) != '0'")
                        ->group('spm.map_id');
                $sQueryRes = $db->fetchAll($sQuery);
                //error_log($sQuery);
                if (count($sQueryRes) > 0) {

                    $tQuery = $db->select()->from(array('refdts' => 'reference_result_dts'), array('refdts.sample_id', 'refdts.sample_label'))
                            ->join(array('resdts' => 'response_result_dts'), 'resdts.sample_id=refdts.sample_id', array('correctRes' => new Zend_Db_Expr("SUM(CASE WHEN (resdts.reported_result=refdts.reference_result AND spm.is_excluded='no') THEN 1 ELSE 0 END)")))
                            ->join(array('spm' => 'shipment_participant_map'), 'resdts.shipment_map_id=spm.map_id and refdts.shipment_id=spm.shipment_id', array())
                            ->where("spm.shipment_id = ?", $shipmentId)
                            ->where("spm.final_result IS NOT NULL")
                            ->where("spm.final_result!=''")
                            ->where("substring(spm.evaluation_status,4,1) != '0'")
                            ->group(array("refdts.sample_id"));

                    $shipmentResult['summaryResult'][] = $sQueryRes;
                    $shipmentResult['summaryResult'][count($shipmentResult['summaryResult']) - 1]['correctCount'] = $db->fetchAll($tQuery);

                    $kitNameRes = $db->fetchAll($db->select()->from('r_testkitname_dts')->where("scheme_type='dts'"));
					
                    $rQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id'))
                            ->join(array('resdts' => 'response_result_dts'), 'resdts.shipment_map_id=spm.map_id', array('resdts.test_kit_name_1', 'resdts.test_kit_name_2', 'resdts.test_kit_name_3'))
                            ->where("spm.final_result IS NOT NULL")
                            ->where("spm.final_result!=''")
                            ->where("substring(spm.evaluation_status,4,1) != '0'")
                            ->where("spm.shipment_id = ?", $shipmentId)
                            ->group('spm.map_id');
                    $rQueryRes = $db->fetchAll($rQuery);
                    $p = 0;
                    $kitName = array();
                    foreach ($kitNameRes as $res) {
                        $k = 1;
                        foreach ($rQueryRes as $rVal) {
                            if ($res['TestKitName_ID'] == $rVal['test_kit_name_1']) {
                                $kitName[$p]['kit_name'] = $res['TestKit_Name'];
                                $kitName[$p]['count'] = $k++;
                            }
                            if ($res['TestKitName_ID'] == $rVal['test_kit_name_2']) {
                                $kitName[$p]['kit_name'] = $res['TestKit_Name'];
                                $kitName[$p]['count'] = $k++;
                            }
                            if ($res['TestKitName_ID'] == $rVal['test_kit_name_3']) {
                                $kitName[$p]['kit_name'] = $res['TestKit_Name'];
                                $kitName[$p]['count'] = $k++;
                            }
                        }

                        $p++;
                    }
                    $shipmentResult['pieChart'] = $kitName;
                }

                $sql = $db->select()->from(array('p' => 'participant'))
                        ->join(array('spm' => 'shipment_participant_map'), 'spm.participant_id=p.participant_id')
                        ->where("spm.shipment_id = ?", $shipmentId);


                $shipmentResult['participantScores'] = $db->fetchAll($sql);
            } else if ($shipmentResult['scheme_type'] == 'eid') {
                $schemeService = new Application_Service_Schemes();
                $extractionAssay = $schemeService->getEidExtractionAssay();
                $detectionAssay = $schemeService->getEidDetectionAssay();
				
				$sQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.attributes'))
                        //->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status'))
                        ->joinLeft(array('res' => 'r_results'), 'res.result_id=spm.final_result', array('result_name'))
                        ->where("spm.shipment_id = ?", $shipmentId)
                        ->where("spm.shipment_test_report_date IS NOT NULL")
                        ->where("spm.shipment_test_report_date!=''")
                        ->group('spm.map_id');
				
                $sQueryRes = $db->fetchAll($sQuery);
				
				if (count($sQueryRes) > 0) {
					$shipmentResult['summaryResult'][] = $sQueryRes;
				}
				
				$cQuery = $db->select()->from(array('refeid' => 'reference_result_eid'),array('refeid.sample_id', 'refeid.sample_label','refeid.reference_result','refeid.mandatory'))
						->join(array('s' => 'shipment'), 's.shipment_id=refeid.shipment_id',array('s.shipment_id'))
						->join(array('spm' => 'shipment_participant_map'),'s.shipment_id=spm.shipment_id',array('spm.map_id','spm.attributes','spm.shipment_score'))
						->joinLeft(array('reseid' => 'response_result_eid'), 'reseid.shipment_map_id = spm.map_id and reseid.sample_id = refeid.sample_id', array('reported_result'))
						->where('spm.shipment_id = ? ', $shipmentId)
						->where("spm.shipment_test_report_date IS NOT NULL")
						->where("refeid.control = 0")
						->where("spm.shipment_test_report_date!=''");

				$cResult=$db->fetchAll($cQuery);
				$correctResult = array();
				foreach($cResult as $cVal){
					//Formed correct result
					if (array_key_exists($cVal['sample_label'], $correctResult)) {
						if($cVal['reported_result']==$cVal['reference_result']){
							$correctResult[$cVal['sample_label']]+=1;
						}
					}else{
						$correctResult[$cVal['sample_label']]=array();
						if($cVal['reported_result']==$cVal['reference_result']){
							$correctResult[$cVal['sample_label']]=1;
						}else{
							$correctResult[$cVal['sample_label']]=0;
						}
					}
				}
				
				
				$shipmentResult['correctRes'] = $correctResult;
				
				$extAssayResult = array();
				$correctRes=0;
				$maxScore = 0;
				$belowScore = 0;
				
				foreach($sQueryRes as $sVal){
					$valAttributes = json_decode($sVal['attributes'], true);
					foreach ($extractionAssay as $eKey=>$extractionAssayVal) {
						if($eKey==$valAttributes['extraction_assay']){
							if (array_key_exists($eKey,$extAssayResult)) {
								
								$extAssayResult[$eKey]['participantCount']=(isset($extAssayResult[$eKey]['participantCount']) ? $extAssayResult[$eKey]['participantCount']+1 : "1");
								
								if ($shipmentResult['max_score'] == $sVal['shipment_score']) {
									$extAssayResult[$eKey]['maxScore']=(isset($extAssayResult[$eKey]['maxScore']) ? $extAssayResult[$eKey]['maxScore']+1 : "1");
								}else{
									$extAssayResult[$eKey]['belowScore']=(isset($extAssayResult[$eKey]['belowScore']) ? $extAssayResult[$eKey]['belowScore']+1 : "1");
								}
								
								$cQuery = $db->select()->from(array('refeid' => 'reference_result_eid'),array('refeid.sample_id', 'refeid.sample_label','refeid.reference_result','refeid.mandatory'))
									->joinLeft(array('reseid' => 'response_result_eid'), 'reseid.sample_id = refeid.sample_id', array('reported_result'))
									->where('refeid.shipment_id = ? ', $shipmentId)
									->where("refeid.control = 0")
									->where('reseid.shipment_map_id = ? ', $sVal['map_id']);
									
								$cResult=$db->fetchAll($cQuery);
								foreach($cResult as $val){
									if($val['reported_result']==$val['reference_result']){
										$extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes']=(isset($extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes']) ? $extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes']+1 : "1");
									}else{
										$extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes']=(isset($extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes']) ? $extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes'] : "0");
									}
								}
							}else{
								
								$extAssayResult[$eKey]=array();
								$extAssayResult[$eKey]['vlAssay']=$extractionAssayVal;
								$extAssayResult[$eKey]['participantCount']=1;
								if ($shipmentResult['max_score'] == $sVal['shipment_score']) {
									$extAssayResult[$eKey]['maxScore']=1;
								}else{
									$extAssayResult[$eKey]['belowScore']=1;
								}
								
								
								$cQuery = $db->select()->from(array('refeid' => 'reference_result_eid'),array('refeid.sample_id', 'refeid.sample_label','refeid.reference_result','refeid.mandatory'))
									->joinLeft(array('reseid' => 'response_result_eid'), 'reseid.sample_id = refeid.sample_id', array('reported_result'))
									->where('refeid.shipment_id = ? ', $shipmentId)
									->where("refeid.control = 0")
									->where('reseid.shipment_map_id = ? ', $sVal['map_id']);
								
								$cResult=$db->fetchAll($cQuery);
								
								foreach($cResult as $val){
									if($val['reported_result']==$val['reference_result']){
										$extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes']=(isset($extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes']) ? $extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes']+1 : "1");
									}else{
										$extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes']=(isset($extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes']) ? $extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes'] : "0");
									}
								}
								//$extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes']=$correctRes;
							}
							
						}
					}
				}
				
				$shipmentResult['avgAssayResult'] = $extAssayResult;
				//Zend_Debug::dump($shipmentResult);
				//die;
            } else if ($shipmentResult['scheme_type'] == 'vl') {
                
				$sQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.attributes','spm.is_excluded'))
                        ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status'))
                        ->joinLeft(array('res' => 'r_results'), 'res.result_id=spm.final_result', array('result_name'))
                        ->where("spm.shipment_id = ?", $shipmentId)
                        ->where("spm.shipment_test_report_date IS NOT NULL")
                        ->where("spm.shipment_test_report_date!=''")
                        ->group('spm.map_id');
				
                $sQueryRes = $db->fetchAll($sQuery);
				if (count($sQueryRes) > 0) {
					$shipmentResult['summaryResult'][] = $sQueryRes;
				}
				
				$query=$db->select()->from(array('refvl' => 'reference_result_vl'),array('refvl.sample_score'))
								->where('refvl.shipment_id = ? ',$shipmentId);
				$smpleResult=$db->fetchAll($query);
				$shipmentResult['no_of_samples']=count($smpleResult);
				
				$vlAssayResultSet = $db->fetchAll($db->select()->from('r_vl_assay'));
				
				//print_r($shipmentResult);die;
				$refVlQuery=$db->select()->from(array('ref' => 'reference_vl_calculation'),array('ref.vl_assay'))
								->where('ref.shipment_id = ? ',$shipmentId)
								->group('vl_assay');
				
				$vlQuery=$db->select()->from(array('vl' => 'r_vl_assay'),array('vl.id','vl.name','vl.short_name'))
								->where("vl.id NOT IN ($refVlQuery)");
				$pendingResult=$db->fetchAll($vlQuery);
				$penResult = array();
				foreach ($pendingResult as $pendingRow) {
					$cQuery = $db->select()->from(array('ref' => 'reference_result_vl'),array('ref.sample_id','ref.sample_label'))
							->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id',array('s.shipment_id'))
							->join(array('sp' => 'shipment_participant_map'),'s.shipment_id=sp.shipment_id',array('sp.map_id','sp.attributes'))
							->joinLeft(array('res' => 'response_result_vl'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('reported_viral_load'))
							->where('sp.shipment_id = ? ', $shipmentId);
					
					$cResult=$db->fetchAll($cQuery);
					
					foreach($cResult as $val){
						$valAttributes = json_decode($val['attributes'], true);
						if($pendingRow['id']==$valAttributes['vl_assay']){
							if (array_key_exists($pendingRow['id'], $penResult)) {
								$penResult[$pendingRow['id']]['specimen'][$val['sample_label']][]=$val['reported_viral_load'];
								if($pendingRow['id']==6){
									if(isset($penResult[$pendingRow['id']]['otherAssayName'])){
										$valAttributes['other_assay'] = (isset($valAttributes['other_assay']) ? $valAttributes['other_assay'] : "");
										if(!in_array($valAttributes['other_assay'],$penResult[$pendingRow['id']]['otherAssayName'])){
											$penResult[$pendingRow['id']]['otherAssayName'][]=$valAttributes['other_assay'];	
										}
									}
								}
							}else{
								$penResult[$pendingRow['id']]=array();
								$penResult[$pendingRow['id']]['specimen'][$val['sample_label']][]=$val['reported_viral_load'];
								$penResult[$pendingRow['id']]['vlAssay']=$pendingRow['name'];
								$penResult[$pendingRow['id']]['shortName']=$pendingRow['short_name'];
								if($pendingRow['id']==6){
									$penResult[$pendingRow['id']]['otherAssayName'][]=(isset($valAttributes['other_assay']) ? $valAttributes['other_assay'] : "");
								}
								
							}
						}
					}
				}
				//print_r($penResult);
				//die;
				foreach ($vlAssayResultSet as $vlAssayRow) {
					$vlCalRes = $db->fetchAll($db->select()->from(array('vlCal' => 'reference_vl_calculation'))
									->join(array('refVl' => 'reference_result_vl'), 'refVl.shipment_id=vlCal.shipment_id and vlCal.sample_id=refVl.sample_id', array('refVl.sample_label', 'refVl.mandatory'))
									->where("vlCal.shipment_id=?", $shipmentId)->where("vlCal.vl_assay=?", $vlAssayRow['id']));
					
					$cQuery = $db->select()->from(array('ref' => 'reference_result_vl'),array('ref.sample_id','ref.sample_label'))
						->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id',array('s.shipment_id'))
						->join(array('sp' => 'shipment_participant_map'),'s.shipment_id=sp.shipment_id',array('sp.map_id','sp.attributes'))
						->joinLeft(array('res' => 'response_result_vl'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('reported_viral_load'))
						->where('sp.shipment_id = ? ', $shipmentId);
					
					$cResult=$db->fetchAll($cQuery);
					
					$labResult = array();
					$otherAssayName = array();
					
					foreach($cResult as $val){
						$valAttributes = json_decode($val['attributes'], true);
						if($vlAssayRow['id']==$valAttributes['vl_assay']){
							if($vlAssayRow['id']==6){
								$otherAssayName[]=$valAttributes['other_assay'];
							}
							if (array_key_exists($val['sample_label'], $labResult)) {
								$labResult[$val['sample_label']]+=1;
							}else{
								$labResult[$val['sample_label']]=array();
								$labResult[$val['sample_label']]=1;
							}
						}
					}
					
					if (count($vlCalRes) > 0) {
						
						
						$vlCalculation[$vlAssayRow['id']] = $vlCalRes;
						$vlCalculation[$vlAssayRow['id']]['vlAssay'] = $vlAssayRow['name'];
						$vlCalculation[$vlAssayRow['id']]['shortName'] = $vlAssayRow['short_name'];
						$vlCalculation[$vlAssayRow['id']]['participant-count'] = $labResult;
						if($vlAssayRow['id']==6){
							$vlCalculation[$vlAssayRow['id']]['otherAssayName']=array_unique($otherAssayName);
						}
					}
				}
				//print_r($vlCalculation);
				//die;
			}
            
            $i++;
        }
		$result = array('shipment' => $shipmentResult, 'vlCalculation' => $vlCalculation,'pendingAssay'=>$penResult);

        return $result;
        //Zend_Debug::dump($shipmentResult);die;
        //return $shipmentResult;
    }

    public function getResponseReports($shipmentId) {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sQuery = $dbAdapter->select()->from(array('p' => 'participant'), array())
                ->joinLeft(array('shp' => 'shipment_participant_map'), 'shp.participant_id=p.participant_id', array())
                ->joinLeft(array('s' => 'shipment'), 's.shipment_id=shp.shipment_id', array('shipment_code'))
                ->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array('others' => new Zend_Db_Expr("SUM(sp.shipment_test_date IS NULL)"), 'excluded' => new Zend_Db_Expr("SUM(if(sp.is_excluded = 'yes', 1, 0))"), 'number_failed' => new Zend_Db_Expr("SUM(sp.final_result = 2 AND sp.shipment_test_date <= s.lastdate_response AND sp.is_excluded != 'yes')"), 'number_passed' => new Zend_Db_Expr("SUM(sp.final_result = 1 AND sp.shipment_test_date <= s.lastdate_response AND sp.is_excluded != 'yes')"), 'number_late' => new Zend_Db_Expr("SUM(sp.shipment_test_date > s.lastdate_response AND sp.is_excluded != 'yes')"), 'map_id'))
                ->joinLeft(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array())
                ->joinLeft(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array())
                ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id', array())
                ->where("s.shipment_id = ?", $shipmentId)
                //->where("p.status = 'active'")
                ->group('s.shipment_id');

        return $dbAdapter->fetchRow($sQuery);
    }
	
	
	public function evaluateDtsViralLoad($shipmentResult,$shipmentId,$reEvaluate){
		$counter = 0;
		$maxScore = 0;
		$scoreHolder = array();
		$finalResult = null;
		$schemeService = new Application_Service_Schemes();
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();

		$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
		$config = new Zend_Config_Ini($file, APPLICATION_ENV);
		$passPercentage = $config->evaluation->vl->passPercentage;
	
		$vlRange = $schemeService->getVlRange($shipmentId);
		
		if ($reEvaluate || $vlRange == null || $vlRange == "" || count($vlRange) == 0) {
			$schemeService->setVlRange($shipmentId);
			$vlRange = $schemeService->getVlRange($shipmentId);
		}

		foreach ($shipmentResult as $shipment) {
			$createdOnUser = explode(" ", $shipment['created_on_user']);
			if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {

				$createdOn = new Zend_Date($createdOnUser[0], Zend_Date::ISO_8601);
			} else {
				$datearray = array('year' => 1970, 'month' => 1, 'day' => 01);
				$createdOn = new Zend_Date($datearray);
			}

			$lastDate = new Zend_Date($shipment['lastdate_response'], Zend_Date::ISO_8601);
			//Zend_Debug::dump($createdOn->isEarlier($lastDate));die;
			if ($createdOn->compare($lastDate,Zend_date::DATES) <= 0) {

				$results = $schemeService->getVlSamples($shipmentId, $shipment['participant_id']);
				$totalScore = 0;
				$maxScore = 0;
				$mandatoryResult = "";
				$scoreResult = "";
				$failureReason = array();

				$attributes = json_decode($shipment['attributes'], true);

				foreach ($results as $result) {
					$calcResult = "";
					$responseAssay = json_decode($result['attributes'], true);
					$responseAssay = isset($responseAssay['vl_assay']) ? $responseAssay['vl_assay'] : "";
					if (isset($vlRange[$responseAssay])) {
						// matching reported and low/high limits
						if (isset($result['reported_viral_load']) && $result['reported_viral_load'] != null) {
							if ($vlRange[$responseAssay][$result['sample_id']]['low'] <= $result['reported_viral_load'] && $vlRange[$responseAssay][$result['sample_id']]['high'] >= $result['reported_viral_load']) {
								$totalScore += $result['sample_score'];
								$calcResult = "pass";
							} else {
								if ($result['sample_score'] > 0) {
									$failureReason[]['warning'] = "Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
								}
								$calcResult = "fail";
							}
						}
					} else {
						$totalScore = "N/A";
						$calcResult = "excluded";
					}

					$maxScore += $result['sample_score'];
					
					$db->update('response_result_vl', array('calculated_score' => $calcResult), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);

					//// checking if mandatory fields were entered and were entered right
					//if ($result['mandatory'] == 1) {
					//	if ((!isset($result['reported_viral_load']) || $result['reported_viral_load'] == "" || $result['reported_viral_load'] == null)) {
					//		$mandatoryResult = 'Fail';
					//		$failureReason[]['warning'] = "Mandatory Sample <strong>" . $result['sample_label'] . "</strong> was not reported";
					//	}
					//	//else if(($result['reported_viral_load'] != $result['reported_viral_load'])){
					//	//	$mandatoryResult = 'Fail';
					//	//	$failureReason[]= "Mandatory Sample <strong>".$result['sample_label']."</strong> was reported wrongly";
					//	//}
					//}
				}

					
					
					// if we are excluding this result, then let us not give pass/fail				
					if ($shipment['is_excluded'] == 'yes') {
						$finalResult = '';
						$totalScore = 0;
						$failureReason = array();
						$shipmentResult[$counter]['shipment_score'] = $responseScore = 0;
						$shipmentResult[$counter]['documentation_score'] = 0;
						$shipmentResult[$counter]['display_result'] = 'Excluded';
						$shipmentResult[$counter]['is_followup'] = 'yes';
						$failureReason[] = array('warning' => 'Excluded from Evaluation');
						$finalResult = 3;
						$shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);
					} else {
						$shipment['is_excluded'] = 'no';
								
		
						// checking if total score and maximum scores are the same
						if ($totalScore == 'N/A') {
							$failureReason[]['warning'] = "Could not determine score. Not enough responses found in the chosen VL Assay.";
							$scoreResult = 'Not Evaluated';
						} else if ($totalScore != $maxScore) {
							$scoreResult = 'Fail';
							if($maxScore != 0){
								$totalScore = ($totalScore/$maxScore)*100;
							}
							$failureReason[]['warning'] = "Participant did not meet the score criteria (Participant Score - <strong>$totalScore</strong> and Required Score - <strong>$passPercentage</strong>)";
						} else {
							if($maxScore != 0){
								$totalScore = ($totalScore/$maxScore)*100;
							}
							$scoreResult = 'Pass';
						}
		
		
						// if $finalResult == 3 , then  excluded
						
						if ($scoreResult == 'Not Evaluated') {
							$finalResult = 4;
						}
						else if ($scoreResult == 'Fail' || $mandatoryResult == 'Fail') {
							$finalResult = 2;
						} else {
							$finalResult = 1;
						}
						
						$shipmentResult[$counter]['shipment_score'] = $totalScore;
						$shipmentResult[$counter]['max_score'] = $passPercentage; //$maxScore;
						
						
		
						$fRes = $db->fetchCol($db->select()->from('r_results', array('result_name'))->where('result_id = ' . $finalResult));
		
						$shipmentResult[$counter]['display_result'] = $fRes[0];
						$shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);
						//Zend_Debug::dump($shipmentResult[$counter]);
						// let us update the total score in DB
						if ($totalScore == 'N/A') {
							$totalScore = 0;
						}
						
						
						
						
						
					}				
				
				
				
				$nofOfRowsUpdated = $db->update('shipment_participant_map', array('shipment_score' => $totalScore, 'final_result' => $finalResult, 'failure_reason' => $failureReason), "map_id = " . $shipment['map_id']);
				
			} else {
				$failureReason = array('warning' => "Response was submitted after the last response date.");

				$db->update('shipment_participant_map', array('failure_reason' => json_encode($failureReason)), "map_id = " . $shipment['map_id']);
			}
			$counter++;
		}
		$db->update('shipment', array('max_score' => $maxScore), "shipment_id = " . $shipmentId);
		return $shipmentResult;
	}
	
	
	
	public function evaluateEid($shipmentResult,$shipmentId){
		
		$counter = 0;
		$maxScore = 0;
		$scoreHolder = array();
		$finalResult = null;
		$schemeService = new Application_Service_Schemes();
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		
            foreach ($shipmentResult as $shipment) {
                $createdOnUser = explode(" ", $shipment['created_on_user']);
                if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {

                    $createdOn = new Zend_Date($createdOnUser[0], Zend_Date::ISO_8601);
                } else {
                    $datearray = array('year' => 1970, 'month' => 1, 'day' => 01);
                    $createdOn = new Zend_Date($datearray);
                }

                $lastDate = new Zend_Date($shipment['lastdate_response'], Zend_Date::ISO_8601);
                if ($createdOn->compare($lastDate) <=0) {
                    $results = $schemeService->getEidSamples($shipmentId, $shipment['participant_id']);
                    $totalScore = 0;
                    $maxScore = 0;
                    $mandatoryResult = "";
                    $scoreResult = "";
                    $failureReason = array();
                    foreach ($results as $result) {
                        // matching reported and reference results
                        if (isset($result['reported_result']) && $result['reported_result'] != null) {
                            if ($result['reference_result'] == $result['reported_result']) {
								if(0 == $result['control']) {
									$totalScore += $result['sample_score'];
								}
                            } else {
                                if ($result['sample_score'] > 0) {
                                    $failureReason[]['warning'] = "Control/Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                                }
                            }
                        }
						if(0 == $result['control']) {
							$maxScore += $result['sample_score'];
						}

                        // checking if mandatory fields were entered and were entered right
                        //if ($result['mandatory'] == 1) {
                        //    if ((!isset($result['reported_result']) || $result['reported_result'] == "" || $result['reported_result'] == null)) {
                        //        $mandatoryResult = 'Fail';
                        //        $failureReason[]['warning'] = "Mandatory Control/Sample <strong>" . $result['sample_label'] . "</strong> was not reported";
                        //    } else if (($result['reference_result'] != $result['reported_result'])) {
                        //        $mandatoryResult = 'Fail';
                        //        $failureReason[]['warning'] = "Mandatory Control/Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                        //    }
                        //}
                    }
					
					
						$totalScore = ($totalScore/$maxScore)*100;
						$maxScore = 100; 
					
					
					
					// if we are excluding this result, then let us not give pass/fail				
					if ($shipment['is_excluded'] == 'yes') {
						$finalResult = '';
						$totalScore = 0;
						$shipmentResult[$counter]['shipment_score'] = $responseScore = 0;
						$shipmentResult[$counter]['documentation_score'] = 0;
						$shipmentResult[$counter]['display_result'] = '';
						$shipmentResult[$counter]['is_followup'] = 'yes';
						$failureReason[] = array('warning' => 'Excluded from Evaluation');
						$finalResult = 3;
						$shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);
					} else {
						$shipment['is_excluded'] = 'no';
						
						
						// checking if total score and maximum scores are the same
						if ($totalScore != $maxScore) {
							$scoreResult = 'Fail';
							$failureReason[]['warning'] = "Participant did not meet the score criteria (Participant Score - <strong>$totalScore</strong> and Required Score - <strong>$maxScore</strong>)";
						} else {
							$scoreResult = 'Pass';
						}
	
						// if any of the results have failed, then the final result is fail
						if ($scoreResult == 'Fail' || $mandatoryResult == 'Fail') {
							$finalResult = 2;
						} else {
							$finalResult = 1;
						}
						$shipmentResult[$counter]['shipment_score'] = $totalScore = round($totalScore,2);
						$shipmentResult[$counter]['max_score'] = 100; //$maxScore;
						$shipmentResult[$counter]['final_result'] = $finalResult;
	
	
						$fRes = $db->fetchCol($db->select()->from('r_results', array('result_name'))->where('result_id = ' . $finalResult));
	
						$shipmentResult[$counter]['display_result'] = $fRes[0];
						$shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);						
							
						

					}
					
					
                    // let us update the total score in DB
                    $db->update('shipment_participant_map', array('shipment_score' => $totalScore, 'final_result' => $finalResult, 'failure_reason' => $failureReason), "map_id = " . $shipment['map_id']);
                    //$counter++;
                } else {
                    $failureReason = array('warning' => "Response was submitted after the last response date.");
                    $db->update('shipment_participant_map', array('failure_reason' => json_encode($failureReason)), "map_id = " . $shipment['map_id']);
                }
				$counter++;
            }
            $db->update('shipment', array('max_score' => $maxScore), "shipment_id = " . $shipmentId);
			
			//Zend_Debug::dump($shipmentResult);die;
			
			return $shipmentResult;
	}
	
	
	
	public function evaluateDtsHivSerology($shipmentResult,$shipmentId){

		$counter = 0;
		$maxScore = 0;
		$scoreHolder = array();
		$schemeService = new Application_Service_Schemes();
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();

		$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
		$config = new Zend_Config_Ini($file, APPLICATION_ENV);
		$correctiveActions = $schemeService->getDtsCorrectiveActions();
		$recommendedTestkits = $schemeService->getRecommededDtsTestkit();

		foreach ($shipmentResult as $shipment) {
			//Zend_Debug::dump($shipment);
			$createdOnUser = explode(" ", $shipment['created_on_user']);
			if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {
				$createdOn = new Zend_Date($createdOnUser[0], Zend_Date::ISO_8601);
			} else {
				$datearray = array('year' => 1970, 'month' => 1, 'day' => 01);
				$createdOn = new Zend_Date($datearray);
			}

			$results = $schemeService->getDtsSamples($shipmentId, $shipment['participant_id']);

			$totalScore = 0;
			$maxScore = 0;
			$mandatoryResult = "";
			$lotResult = "";
			$testKit1 = "";
			$testKit2 = "";
			$testKit3 = "";
			$testKitRepeatResult = "";
			$testKitExpiryResult = "";
			$lotResult = "";
			$scoreResult = "";
			$failureReason = array();
			$correctiveActionList = array();
			$algoResult = "";
			$lastDateResult = "";
			$controlTesKitFail = "";

			$attributes = json_decode($shipment['attributes'], true);


			//Response was submitted after the last response date.
			$lastDate = new Zend_Date($shipment['lastdate_response'], Zend_Date::ISO_8601);
			if ($createdOn->compare($lastDate,Zend_date::DATES) > 0) {
				//$lastDateResult = 'Fail';
				$failureReason[] = array('warning' => "Response was submitted after the last response date.",
					'correctiveAction' => $correctiveActions[1]);
				$correctiveActionList[] = 1;
			}


			//$serialCorrectResponses = array('NXX','PNN','PPX','PNP');				
			//$parallelCorrectResponses = array('PPX','PNP','PNN','NNX','NPN','NPP');

			$testedOn = new Zend_Date($results[0]['shipment_test_date'], Zend_Date::ISO_8601);
			
			// Getting the Test Date string to show in Corrective Actions and other sentences
			$testDate = $testedOn->toString('dd-MMM-YYYY');
			
			// Getting test kit expiry dates as reported
			$expDate1 = "";
			//die($results[0]['exp_date_1']);
			if (isset($results[0]['exp_date_1']) && trim($results[0]['exp_date_1']) != "0000-00-00" && trim(strtotime($results[0]['exp_date_1'])) != "") {
				$expDate1 = new Zend_Date($results[0]['exp_date_1'], Zend_Date::ISO_8601);
			}
			$expDate2 = "";
			if (isset($results[0]['exp_date_2']) && trim($results[0]['exp_date_2']) != "0000-00-00" && trim(strtotime($results[0]['exp_date_2'])) != "") {
				$expDate2 = new Zend_Date($results[0]['exp_date_2'], Zend_Date::ISO_8601);
			}
			$expDate3 = "";
			if (isset($results[0]['exp_date_3']) && trim($results[0]['exp_date_3']) != "0000-00-00" && trim(strtotime($results[0]['exp_date_3'])) != "") {
				$expDate3 = new Zend_Date($results[0]['exp_date_3'], Zend_Date::ISO_8601);
			}

			// Getting Test Kit Names
			
			$testKitDb = new Application_Model_DbTable_TestkitnameDts();
			$testKit1 = "";
			
			$testKitName = $testKitDb->getTestKitNameById($results[0]['test_kit_name_1']);
			if (isset($testKitName[0])) {
				$testKit1 = $testKitName[0];
			}

			$testKit2 = "";
			if (trim($results[0]['test_kit_name_2']) != "") {
				$testKitName = $testKitDb->getTestKitNameById($results[0]['test_kit_name_2']);
				if (isset($testKitName[0])) {
					$testKit2 = $testKitName[0];
				}
			}
			$testKit3 = "";
			if (trim($results[0]['test_kit_name_3']) != "") {
				$testKitName = $testKitDb->getTestKitNameById($results[0]['test_kit_name_3']);
				if (isset($testKitName[0])) {
					$testKit3 = $testKitName[0];
				}
			}
			
			
			// T.7 Checking for Expired Test Kits

			if ($testKit1 != "") {
				if ($expDate1 != "") {
					if ($testedOn->isLater($expDate1)) {
						$difference = $testedOn->sub($expDate1);

						$measure = new Zend_Measure_Time($difference->toValue(), Zend_Measure_Time::SECOND);
						$measure->convertTo(Zend_Measure_Time::DAY);
						$failureReason[] = array('warning' => "Test Kit 1 (<strong>" . $testKit1 . "</strong>) expired " . round($measure->getValue()) . " days before the test date " . $testDate,
							'correctiveAction' => $correctiveActions[5]);
						$correctiveActionList[] = 5;
						$tk1Expired = true;
					}else{
						$tk1Expired = false;
					}
					
				} else {
					$failureReason[] = array('warning' => "Result not evaluated – Test kit 1 expiry date is not reported with PT response.",
						'correctiveAction' => $correctiveActions[6]);
					$correctiveActionList[] = 6;
					$shipment['is_excluded'] = 'yes';
				}
				
				if(isset($recommendedTestkits[1]) && count($recommendedTestkits[1]) > 0){
					if(!in_array($results[0]['test_kit_name_1'],$recommendedTestkits[1])){
						$tk1RecommendedUsed = false;
						$failureReason[] = array('warning' => "For Test 1, testing is not performed with country approved test kit.",
							'correctiveAction' => $correctiveActions[17]);							
					}else{
						$tk1RecommendedUsed = true;
					}
				}
				
			}

			if ($testKit2 != "") {
				if ($expDate2 != "") {
					if ($testedOn->isLater($expDate2)) {
						$difference = $testedOn->sub($expDate2);

						$measure = new Zend_Measure_Time($difference->toValue(), Zend_Measure_Time::SECOND);
						$measure->convertTo(Zend_Measure_Time::DAY);
						$failureReason[] = array('warning' => "Test Kit 2 (<strong>" . $testKit2 . "</strong>) expired " . round($measure->getValue()) . " days before the test date " . $testDate,
							'correctiveAction' => $correctiveActions[5]);
						$correctiveActionList[] = 5;
						$tk2Expired = true;
					}else{
						$tk2Expired = false;
					}
				} else {
					$failureReason[] = array('warning' => "Result not evaluated – Test kit 2 expiry date is not reported with PT response.",
						'correctiveAction' => $correctiveActions[6]);
					$correctiveActionList[] = 6;
					$shipment['is_excluded'] = 'yes';
				}
				
				if(isset($recommendedTestkits[2]) && count($recommendedTestkits[2]) > 0){
					if(!in_array($results[0]['test_kit_name_2'],$recommendedTestkits[2])){
						$tk2RecommendedUsed = false;
						$failureReason[] = array('warning' => "For Test 2, testing is not performed with country approved test kit.",
							'correctiveAction' => $correctiveActions[17]);							
					}else{
						$tk2RecommendedUsed = true;
					}
				}
			}


			if ($testKit3 != "") {
				if ($expDate3 != "") {
					if ($testedOn->isLater($expDate3)) {
						$difference = $testedOn->sub($expDate3);

						$measure = new Zend_Measure_Time($difference->toValue(), Zend_Measure_Time::SECOND);
						$measure->convertTo(Zend_Measure_Time::DAY);
						$failureReason[] = array('warning' => "Test Kit 3 (<strong>" . $testKit3 . "</strong>) expired " . round($measure->getValue()) . " days before the test date " . $testDate,
							'correctiveAction' => $correctiveActions[5]);
						$correctiveActionList[] = 5;
						$tk3Expired = true;
					}else{
						$tk3Expired = false;
					}
				} else {

					$failureReason[] = array('warning' => "Result not evaluated – Test kit 3 expiry date is not reported with PT response.",
						'correctiveAction' => $correctiveActions[6]);
					$correctiveActionList[] = 6;
					$shipment['is_excluded'] = 'yes';
				}
				
				if(isset($recommendedTestkits[3]) && count($recommendedTestkits[3]) > 0){
					if(!in_array($results[0]['test_kit_name_3'],$recommendedTestkits[3])){
						$tk3RecommendedUsed = false;
						$failureReason[] = array('warning' => "For Test 3, testing is not performed with country approved test kit.",
							'correctiveAction' => $correctiveActions[17]);							
					}else{
						$tk3RecommendedUsed = true;
					}
				}
			}
			//checking if testkits were repeated
			// T.9 Test kit repeated for confirmatory or tiebreaker test (T1/T2/T3).
			if (($testKit1 == "") && ($testKit2 == "") && ($testKit3 == "")) {
				$failureReason[] = array('warning' => "No Test Kit reported. Result not evaluated",
					'correctiveAction' => $correctiveActions[7]);
				$correctiveActionList[] = 7;
				$shipment['is_excluded'] = 'yes';
			} else if (($testKit1 != "") && ($testKit2 != "") && ($testKit3 != "") && ($testKit1 == $testKit2) && ($testKit2 == $testKit3)) {
				//$testKitRepeatResult = 'Fail';
				$failureReason[] = array('warning' => "<strong>$testKit1</strong> repeated for all three Test Kits",
					'correctiveAction' => $correctiveActions[8]);
				$correctiveActionList[] = 8;
			} else {
				if (($testKit1 != "") && ($testKit2 != "") && ($testKit1 == $testKit2) && $testKit1 != "" && $testKit2 != "") {
					//$testKitRepeatResult = 'Fail';
					$failureReason[] = array('warning' => "<strong>$testKit1</strong> repeated as Test Kit 1 and Test Kit 2",
						'correctiveAction' => $correctiveActions[9]);
					$correctiveActionList[] = 9;
				}
				if (($testKit2 != "") && ($testKit3 != "") && ($testKit2 == $testKit3) && $testKit2 != "" && $testKit3 != "") {
					//$testKitRepeatResult = 'Fail';
					$failureReason[] = array('warning' => "<strong>$testKit2</strong> repeated as Test Kit 2 and Test Kit 3",
						'correctiveAction' => $correctiveActions[9]);
					$correctiveActionList[] = 9;
				}
				if (($testKit1 != "") && ($testKit3 != "") && ($testKit1 == $testKit3) && $testKit1 != "" && $testKit3 != "") {
					//$testKitRepeatResult = 'Fail';
					$failureReason[] = array('warning' => "<strong>$testKit1</strong> repeated as Test Kit 1 and Test Kit 3",
						'correctiveAction' => $correctiveActions[9]);
					$correctiveActionList[] = 9;
				}
			}


			// checking if all LOT details were entered
			// T.3 Ensure test kit lot number is reported for all performed tests. 
			if ($testKit1 != "" && (!isset($results[0]['lot_no_1']) || $results[0]['lot_no_1'] == "" || $results[0]['lot_no_1'] == null)) {
				if (isset($result['test_result_1']) && $result['test_result_1'] != "" && $result['test_result_1'] != null) {
					$lotResult = 'Fail';
					$failureReason[] = array('warning' => "Result not evaluated – Test Kit lot number 1 is not reported.",
						'correctiveAction' => $correctiveActions[10]);
					$correctiveActionList[] = 10;
					$shipment['is_excluded'] = 'yes';
				}
			}
			if ($testKit2 != "" && (!isset($results[0]['lot_no_2']) || $results[0]['lot_no_2'] == "" || $results[0]['lot_no_2'] == null)) {
				if (isset($result['test_result_2']) && $result['test_result_2'] != "" && $result['test_result_2'] != null) {
					$lotResult = 'Fail';
					$failureReason[] = array('warning' => "Result not evaluated – Test Kit lot number 2 is not reported.",
						'correctiveAction' => $correctiveActions[10]);
					$correctiveActionList[] = 10;
					$shipment['is_excluded'] = 'yes';
				}
			}
			if ($testKit3 != "" && (!isset($results[0]['lot_no_3']) || $results[0]['lot_no_3'] == "" || $results[0]['lot_no_3'] == null)) {
				if (isset($result['test_result_3']) && $result['test_result_3'] != "" && $result['test_result_3'] != null) {
					$lotResult = 'Fail';
					$failureReason[] = array('warning' => "Result not evaluated – Test Kit lot number 3 is not reported.",
						'correctiveAction' => $correctiveActions[10]);
					$correctiveActionList[] = 10;
					$shipment['is_excluded'] = 'yes';
				}
			}
			
			$samplePassOrFail = array();
			foreach ($results as $result) {
				//if Sample is not mandatory, we will skip the evaluation
				if (0 == $result['mandatory']) {
					$db->update('response_result_dts', array('calculated_score' => "N.A."), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
					continue;
				}
				

				// Checking algorithm Pass/Fail only if it is NOT a control.
				if(0 == $result['control']) {
					$r1 = $r2 = $r3 = '';
					if ($result['test_result_1'] == 1) {
						$r1 = 'R';
					} else if ($result['test_result_1'] == 2) {
						$r1 = 'NR';
					} else if ($result['test_result_1'] == 3) {
						$r1 = 'I';
					} else {
						$r1 = '-';
					}
					if ($result['test_result_2'] == 1) {
						$r2 = 'R';
					} else if ($result['test_result_2'] == 2) {
						$r2 = 'NR';
					} else if ($result['test_result_2'] == 3) {
						$r2 = 'I';
					} else {
						$r2 = '-';
					}
					if (isset($config->evaluation->dts->dtsOptionalTest3) && $config->evaluation->dts->dtsOptionalTest3 == 'yes') {
						$r3 = 'X';
					} else {
						if ($result['test_result_3'] == 1) {
							$r3 = 'R';
						} else if ($result['test_result_3'] == 2) {
							$r3 = 'NR';
						} else if ($result['test_result_3'] == 3) {
							$r3 = 'I';
						} else {
							$r3 = '-';
						}
					}

					$algoString = "Wrongly reported in the pattern : <strong>" . $r1 . "</strong> <strong>" . $r2 . "</strong> <strong>" . $r3 . "</strong>";

					if ($attributes['algorithm'] == 'serial') {
						if ($r1 == 'NR') {
							if (($r2 == '-') && ($r3 == '-' || $r3 == 'X')) {
								$algoResult = 'Pass';
							} else {
								$algoResult = 'Fail';
								$failureReason[] = array('warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]);
								$correctiveActionList[] = 2;
							}
						}
						//			else if ($r1 == 'R' && $r2 == 'NR' && $r3 == 'NR') {
						//                            $algoResult = 'Pass';
						//                        }
						else if ($r1 == 'R' && $r2 == 'R') {
							if (($r3 == '-' || $r3 == 'X')) {
								$algoResult = 'Pass';
							} else {
								$algoResult = 'Fail';
								$failureReason[] = array('warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]);
								$correctiveActionList[] = 2;
							}
						} else if ($r1 == 'R' && $r2 == 'NR' && ($r3 == 'R' || $r3 == 'X')) {
							$algoResult = 'Pass';
						} else {
							$algoResult = 'Fail';
							$failureReason[] = array('warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
								'correctiveAction' => $correctiveActions[2]);
							$correctiveActionList[] = 2;
						}
					} else if ($attributes['algorithm'] == 'parallel') {

						if ($r1 == 'R' && $r2 == 'R') {
							if (($r3 == '-' || $r3 == 'X')) {
								$algoResult = 'Pass';
							} else {

								$algoResult = 'Fail';
								$failureReason[] = array('warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]);
								$correctiveActionList[] = 2;
							}
						} else if ($r1 == 'R' && $r2 == 'NR' && ($r3 == 'R' || $r3 == 'X')) {
							$algoResult = 'Pass';
						} else if ($r1 == 'R' && $r2 == 'NR' && ($r3 == 'NR' || $r3 == 'X')) {
							$algoResult = 'Pass';
						} else if ($r1 == 'NR' && $r2 == 'NR') {
							if (($r3 == '-' || $r3 == 'X')) {
								$algoResult = 'Pass';
							} else {
								$algoResult = 'Fail';
								$failureReason[] = array('warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]);
								$correctiveActionList[] = 2;
							}
						} else if ($r1 == 'NR' && $r2 == 'R' && ($r3 == 'NR' || $r3 == 'X')) {
							$algoResult = 'Pass';
						} else if ($r1 == 'NR' && $r2 == 'R' && ($r3 == 'R' || $r3 == 'X')) {
							$algoResult = 'Pass';
						} else {
							$algoResult = 'Fail';
							$failureReason[] = array('warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
								'correctiveAction' => $correctiveActions[2]);
							$correctiveActionList[] = 2;
						}
					}
				}else{
					// If there are two kit used for the participants then the control
					// needs to be tested with at least both kit.
					// If three then all three kits required and one then atleast one.
					
					if($testKit1 != ""){
						if(!isset($result['test_result_1']) || $result['test_result_1'] == ""){
							$controlTesKitFail = 'Fail';
							$failureReason[] = array('warning' => "For the Control Sample <strong>" . $result['sample_label'] . "</strong>, Test Kit 1 (<strong>$testKit1</strong>) was not used",
								'correctiveAction' => $correctiveActions[2]);
							$correctiveActionList[] = 2;
						}
					}
					
					if($testKit2 != ""){
						if(!isset($result['test_result_2']) || $result['test_result_2'] == ""){
							$controlTesKitFail = 'Fail';
							$failureReason[] = array('warning' => "For the Control Sample <strong>" . $result['sample_label'] . "</strong>, Test Kit 2 (<strong>$testKit2</strong>) was not used",
								'correctiveAction' => $correctiveActions[2]);
							$correctiveActionList[] = 2;
						}
					}
					
					
					if($testKit3 != ""){
						if(!isset($result['test_result_3']) || $result['test_result_3'] == ""){
							$controlTesKitFail = 'Fail';
							$failureReason[] = array('warning' => "For the Control Sample <strong>" . $result['sample_label'] . "</strong>, Test Kit 3 (<strong>$testKit3</strong>) was not used",
								'correctiveAction' => $correctiveActions[2]);
							$correctiveActionList[] = 2;
						}
					}
					
				}

				if ((!isset($result['reported_result']) || $result['reported_result'] == "" || $result['reported_result'] == null)) {
					$mandatoryResult = 'Fail';
					$shipment['is_excluded'] = 'yes';
					$failureReason[] = array('warning' => "Sample <strong>" . $result['sample_label'] . "</strong> was not reported. Result not evaluated.",
						'correctiveAction' => $correctiveActions[4]);
					$correctiveActionList[] = 4;
				}

				// matching reported and reference results
				$correctResponse = false;
				if (isset($result['reported_result']) && $result['reported_result'] != null) {
					if($controlTesKitFail != 'Fail'){
						if ($result['reference_result'] == $result['reported_result']) {
							if($algoResult != 'Fail' && $mandatoryResult != 'Fail'){
								$totalScore += $result['sample_score'];
								$correctResponse = true;
							}else{
								$correctResponse = false;
								// $totalScore remains the same	
							}
						} else {
							if ($result['sample_score'] > 0) {
								$failureReason[] = array('warning' => "<strong>" . $result['sample_label'] . "</strong> - Reported result does not match the expected result",
									'correctiveAction' => $correctiveActions[3]);
								$correctiveActionList[] = 3;
							}
							$correctResponse = false;
						}
					}else{
						$correctResponse = false;
					}
					
				}
				
				$maxScore += $result['sample_score'];
				
				if(isset($result['test_result_1']) && $result['test_result_1'] != "" && $result['test_result_1'] != null){
					//T.1 Ensure test kit name is reported for all performed tests.
					if (($testKit1 == "")) {
						$failureReason[] = array('warning' => "Result not evaluated – name of Test kit 1 not reported.",
							'correctiveAction' => $correctiveActions[7]);
						$correctiveActionList[] = 7;
						$shipment['is_excluded'] = 'yes';
					}
					//T.5 Ensure expiry date information is submitted for all performed tests.
					//T.15 Testing performed with a test kit that is not recommended by MOH
					if ((isset($tk1Expired) && $tk1Expired) || (isset($tk1RecommendedUsed) && !$tk1RecommendedUsed)) {
						$testKitExpiryResult = 'Fail';
						if($correctResponse){
							$totalScore -= $result['sample_score'];
						}
						$correctResponse = false;
					}
				}
				if(isset($result['test_result_2']) && $result['test_result_2'] != "" && $result['test_result_2'] != null){
					//T.1 Ensure test kit name is reported for all performed tests.
					if (($testKit2 == "")) {
						$failureReason[] = array('warning' => "Result not evaluated – name of Test kit 2 not reported.",
							'correctiveAction' => $correctiveActions[7]);
						$correctiveActionList[] = 7;
						$shipment['is_excluded'] = 'yes';
					}
					//T.5 Ensure expiry date information is submitted for all performed tests.
					//T.15 Testing performed with a test kit that is not recommended by MOH
					if ((isset($tk2Expired) && $tk2Expired) || (isset($tk2RecommendedUsed) && !$tk2RecommendedUsed)) {
						$testKitExpiryResult = 'Fail';
						if($correctResponse){
							$totalScore -= $result['sample_score'];
						}
						$correctResponse = false;
					}
				}
				if(isset($result['test_result_3']) && $result['test_result_3'] != "" && $result['test_result_3'] != null){
					//T.1 Ensure test kit name is reported for all performed tests.
					if (($testKit3 == "")) {
						$failureReason[] = array('warning' => "Result not evaluated – name of Test kit 3 not reported.",
							'correctiveAction' => $correctiveActions[7]);
						$correctiveActionList[] = 7;
						$shipment['is_excluded'] = 'yes';
					}
					//T.5 Ensure expiry date information is submitted for all performed tests.
					//T.15 Testing performed with a test kit that is not recommended by MOH
					if ((isset($tk3Expired) && $tk3Expired) || (isset($tk3RecommendedUsed) && !$tk3RecommendedUsed)) {
						$testKitExpiryResult = 'Fail';
						if($correctResponse){
							$totalScore -= $result['sample_score'];
						}
						$correctResponse = false;
					}
				}
				
				if (!$correctResponse || $algoResult == 'Fail' || $mandatoryResult == 'Fail' || ($result['reference_result'] != $result['reported_result'])) {
					$db->update('response_result_dts', array('calculated_score' => "Fail"), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
				} else {
					$db->update('response_result_dts', array('calculated_score' => "Pass"), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
				}
			}

			
			
			$configuredDocScore = ((isset($config->evaluation->dts->documentationScore) && $config->evaluation->dts->documentationScore != "" && $config->evaluation->dts->documentationScore != null) ? $config->evaluation->dts->documentationScore : 0);

			// Response Score
			if ($maxScore == 0 || $totalScore == 0) {
				$responseScore = 0;
			} else {
				$responseScore = round(($totalScore / $maxScore) * 100 * (100 - $configuredDocScore) / 100, 2);
			}


			//Let us now calculate documentation score
			$documentationScore = 0;
			$documentationScorePerItem = ($config->evaluation->dts->documentationScore / 5);

			// D.1
			if (isset($results[0]['shipment_receipt_date']) && strtolower($results[0]['shipment_receipt_date']) != '') {
				$documentationScore += $documentationScorePerItem;
			} else {
				$failureReason[] = array('warning' => "Shipment Receipt Date not provided",
					'correctiveAction' => $correctiveActions[16]);
				$correctiveActionList[] = 16;
			}
			
			//D.3
			if (isset($attributes['sample_rehydration_date']) && trim($attributes['sample_rehydration_date']) != "") {
				$documentationScore += $documentationScorePerItem;
			} else {
				$failureReason[] = array('warning' => "Missing reporting rehydration date for DTS Panel",
					'correctiveAction' => $correctiveActions[12]);
				$correctiveActionList[] = 12;
			}

			//D.5
			if (isset($results[0]['shipment_test_date']) && trim($results[0]['shipment_test_date']) != "") {
				$documentationScore += $documentationScorePerItem;
			} else {
				$failureReason[] = array('warning' => "Shipment received test date not provided",
					'correctiveAction' => $correctiveActions[13]);
				$correctiveActionList[] = 13;
			}

			//D.7
			// Testing should be done within 24*($config->evaluation->dts->sampleRehydrateDays) hours of rehydration.
			$sampleRehydrationDate = new DateTime($attributes['sample_rehydration_date']);
			$testedOnDate = new DateTime($results[0]['shipment_test_date']);
			$interval = $sampleRehydrationDate->diff($testedOnDate);
			
			$sampleRehydrateDays = $config->evaluation->dts->sampleRehydrateDays;
			$rehydrateHours = $sampleRehydrateDays*24;

			if ($interval->days > $sampleRehydrateDays) {
				$failureReason[] = array('warning' => "Testing should be done within $rehydrateHours hours of rehydration.",
					'correctiveAction' => $correctiveActions[14]);
				$correctiveActionList[] = 14;
			} else {
				$documentationScore += $documentationScorePerItem;
			}
			
			//D.8
			if (isset($result['supervisor_approval']) && strtolower($result['supervisor_approval']) == 'yes' && trim($result['participant_supervisor']) != "") {
				$documentationScore += $documentationScorePerItem;
			} else {
				$failureReason[] = array('warning' => "Supervisor approval absent",
					'correctiveAction' => $correctiveActions[11]);
				$correctiveActionList[] = 11;
			}

			$grandTotal = ($responseScore + $documentationScore);
			if ($grandTotal < $config->evaluation->dts->passPercentage) {
				$scoreResult = 'Fail';
				$failureReason[] = array('warning' => "Participant did not meet the score criteria (Participant Score is <strong>" . $grandTotal . "</strong> and Required Score is <strong>" . $config->evaluation->dts->passPercentage . "</strong>)",
					'correctiveAction' => $correctiveActions[15]);
				$correctiveActionList[] = 15;
			} else {
				$scoreResult = 'Pass';
			}


			// if we are excluding this result, then let us not give pass/fail				
			if ($shipment['is_excluded'] == 'yes') {
				$finalResult = '';
				$shipmentResult[$counter]['shipment_score'] = $responseScore = 0;
				$shipmentResult[$counter]['documentation_score'] = 0;
				$shipmentResult[$counter]['display_result'] = '';
				$shipmentResult[$counter]['is_followup'] = 'yes';
				$failureReason[] = array('warning' => 'Excluded from Evaluation');
				$finalResult = 3;
				$shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);
			} else {
				$shipment['is_excluded'] = 'no';
				// if any of the results have failed, then the final result is fail
				if ($algoResult == 'Fail' || $scoreResult == 'Fail' || $lastDateResult == 'Fail' || $mandatoryResult == 'Fail' || $lotResult == 'Fail' || $testKitExpiryResult == 'Fail') {
					$finalResult = 2;
					$shipmentResult[$counter]['is_followup'] = 'yes';
				} else {
					$finalResult = 1;
				}
				$shipmentResult[$counter]['shipment_score'] = $responseScore;
				$shipmentResult[$counter]['documentation_score'] = $documentationScore;
				$scoreHolder[$shipment['map_id']] = $responseScore + $documentationScore;

				$fRes = $db->fetchCol($db->select()->from('r_results', array('result_name'))->where('result_id = ' . $finalResult));

				$shipmentResult[$counter]['display_result'] = $fRes[0];
				$shipmentResult[$counter]['failure_reason'] = $failureReason = (isset($failureReason) && count($failureReason) > 0) ? json_encode($failureReason) : "";
				//$shipmentResult[$counter]['corrective_actions'] = implode(",",$correctiveActionList);
			}

			$shipmentResult[$counter]['max_score'] = $maxScore;
			$shipmentResult[$counter]['final_result'] = $finalResult;

			// let us update the total score in DB
			$nofOfRowsUpdated = $db->update('shipment_participant_map', array('shipment_score' => $responseScore, 'documentation_score' => $documentationScore, 'final_result' => $finalResult,"is_followup" => $shipmentResult[$counter]['is_followup'], 'is_excluded' => $shipment['is_excluded'], 'failure_reason' => $failureReason), "map_id = " . $shipment['map_id']);
			$nofOfRowsDeleted = $db->delete('dts_shipment_corrective_action_map', "shipment_map_id = " . $shipment['map_id']);
			$correctiveActionList = array_unique($correctiveActionList);
			foreach ($correctiveActionList as $ca) {
				$db->insert('dts_shipment_corrective_action_map', array('shipment_map_id' => $shipment['map_id'], 'corrective_action_id' => $ca), "map_id = " . $shipment['map_id']);
			}

			$counter++;
		}
		
		if(count($scoreHolder) > 0){
			$averageScore = round(array_sum($scoreHolder)/count($scoreHolder),2);	
		}else{
			$averageScore = 0 ;
		}
		

		$db->update('shipment', array('max_score' => $maxScore,'average_score' => $averageScore), "shipment_id = " . $shipmentId);
		return $shipmentResult;
	}

}
