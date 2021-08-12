<?php

class Application_Service_Evaluation
{

	public function getAllDistributions($parameters)
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
			->joinLeft(array('s' => 'shipment'), 's.distribution_id=d.distribution_id', array('shipments' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT s.shipment_code SEPARATOR ', ')"), 'not_finalized_count' => new Zend_Db_Expr("SUM(IF(s.status!='finalized',1,0))")))
			->where("s.status!='finalized'")
			->group('s.distribution_id');

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

		// die($sQuery);

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

	public function getShipments($distributionId)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('s' => 'shipment'))
			->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id')
			->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('map_id', 'responseDate' => 'shipment_test_report_date', 'participant_count' => new Zend_Db_Expr('count("participant_id")'), 'reported_count' => new Zend_Db_Expr("SUM(shipment_test_date not like  '0000-00-00' OR is_pt_test_not_performed ='yes')"), 'number_passed' => new Zend_Db_Expr("SUM(final_result = 1)"), 'last_not_participated_mailed_on', 'last_not_participated_mail_count', 'shipment_status' => 's.status'))
			->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type')
			->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id')
			->where("s.distribution_id = ?", $distributionId)
			->group('s.shipment_id');
		return $db->fetchAll($sql);
	}

	public function getResponseCount($shipmentId, $distributionId)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('s' => 'shipment'), array(''))
			->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array(''))
			->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('reported_count' => new Zend_Db_Expr("SUM((shipment_test_date not like  '0000-00-00' AND shipment_test_date is NOT NULL AND shipment_test_date not like '' ) OR is_pt_test_not_performed ='yes')")))
			->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array(''))
			->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id', array(''))
			->where("s.shipment_id = ?", $shipmentId)
			//->where("sp.is_excluded!='yes' AND sp.is_pt_test_not_performed is NULL")
			->where("s.distribution_id = ?", $distributionId)
			->group('s.shipment_id');
		return $db->fetchRow($sql);
	}

	public function getShipmentToEvaluate($shipmentId, $reEvaluate = false, $override = '')
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.shipment_attributes', 's.scheme_type', 's.shipment_date', 's.lastdate_response', 's.distribution_id', 's.number_of_samples', 's.max_score', 's.shipment_comment', 's.created_by_admin', 's.created_on_admin', 's.updated_by_admin', 's.updated_on_admin', 'shipment_status' => 's.status', 's.corrective_action_file'))
			->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id')
			->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id')
			->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type')
			->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id')
			->where("s.shipment_id = ?", $shipmentId);
		//->where("substring(sp.evaluation_status,4,1) != '0'");
		if ($override != "") {
			$sql = $sql->where("sp.manual_override = ?", $override);
		}
		$shipmentResult = $db->fetchAll($sql);

		$schemeService = new Application_Service_Schemes();

		if ($shipmentResult[0]['scheme_type'] == 'eid') {
			$eidModel = new Application_Model_Eid();
			$shipmentResult =  $eidModel->evaluate($shipmentResult, $shipmentId);
		} else if ($shipmentResult[0]['scheme_type'] == 'recency') {
			$recencyModel = new Application_Model_Recency($db);
			$shipmentResult =  $recencyModel->evaluate($shipmentResult, $shipmentId);
		} else if ($shipmentResult[0]['scheme_type'] == 'dbs') {
			$counter = 0;
			$maxScore = 0;
			foreach ($shipmentResult as $shipment) {
				$createdOnUser = explode(" ", $shipment['created_on_user']);
				if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {

					$createdOn = new DateTime($createdOnUser[0]);
				} else {
					$createdOn = new DateTime('1970-01-01');
				}

				$lastDate = new DateTime($shipment['lastdate_response']);
				if ($createdOn > $lastDate) {

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

					$testedOn = new DateTime($results[0]['shipment_test_date']);
					$testDate = $testedOn->format('d-M-Y');
					$expDate1 = "";
					if (trim(strtotime($results[0]['exp_date_1'])) != "") {
						$expDate1 = new DateTime($results[0]['exp_date_1']);
					}
					$expDate2 = "";
					if (trim(strtotime($results[0]['exp_date_2'])) != "") {
						$expDate2 = new DateTime($results[0]['exp_date_2']);
					}

					$expDate3 = "";
					if (trim(strtotime($results[0]['exp_date_3'])) != "") {
						$expDate3 = new DateTime($results[0]['exp_date_3']);
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
						if ($testedOn > ($expDate1)) {
							$difference = $testedOn->diff($expDate1);

							$testKitExpiryResult = 'Fail';
							$failureReason[]['warning'] = "EIA 1 (<strong>" . $testKit1 . "</strong>) expired " . $difference->format('%a')  . " days before the test date " . $testDate;
						}
					}

					if ($expDate2 != "") {
						if ($testedOn > ($expDate2)) {
							$difference = $testedOn->diff($expDate2);

							$testKitExpiryResult = 'Fail';
							$failureReason[]['warning'] = "EIA 2 (<strong>" . $testKit2 . "</strong>) expired " . $difference->format('%a')  . " days before the test date " . $testDate;
						}
					}


					if ($expDate3 != "") {
						if ($testedOn > ($expDate3)) {
							$difference = $testedOn->diff($expDate3);

							$testKitExpiryResult = 'Fail';
							$failureReason[]['warning'] = "EIA 3 (<strong>" . $testKit3 . "</strong>) expired " . $difference->format('%a')  . " days before the test date " . $testDate;
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
					if (isset($shipment['manual_override']) && $shipment['manual_override'] == 'yes') {
						// let us update the total score in DB
						$nofOfRowsUpdated = $db->update('shipment_participant_map', array('failure_reason' => $failureReason), "map_id = " . $shipment['map_id']);
					} else {
						// let us update the total score in DB
						$nofOfRowsUpdated = $db->update('shipment_participant_map', array('shipment_score' => $totalScore, 'final_result' => $finalResult, 'failure_reason' => $failureReason), "map_id = " . $shipment['map_id']);
					}
					$counter++;
				} else {
					$failureReason = array('warning' => "Response was submitted after the last response date.");
					$db->update('shipment_participant_map', array('failure_reason' => json_encode($failureReason)), "map_id = " . $shipment['map_id']);
				}
			}
			$db->update('shipment', array('max_score' => $maxScore), "shipment_id = " . $shipmentId);
		} else if ($shipmentResult[0]['scheme_type'] == 'dts') {
			$dtsModel = new Application_Model_Dts();
			$shipmentResult = $dtsModel->evaluate($shipmentResult, $shipmentId);
		} else if ($shipmentResult[0]['scheme_type'] == 'vl') {
			$vlModel = new Application_Model_Vl();
			$shipmentResult = $vlModel->evaluate($shipmentResult, $shipmentId, $reEvaluate);
		} else if ($shipmentResult[0]['scheme_type'] == 'covid19') {
			$covid19Model = new Application_Model_Covid19();
			$shipmentResult = $covid19Model->evaluate($shipmentResult, $shipmentId);
		}

		return $shipmentResult;
	}

	public function editEvaluation($shipmentId, $participantId, $scheme)
	{
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
		} else if ($scheme == 'recency') {
			$possibleResults = $schemeService->getPossibleResults('recency');
			$evalComments = $schemeService->getSchemeEvaluationComments('recency');
			$results = $schemeService->getRecencySamples($shipmentId, $participantId);
		} else if ($scheme == 'covid19') {
			$possibleResults = $schemeService->getPossibleResults('covid19');
			$evalComments = $schemeService->getSchemeEvaluationComments('covid19');
			$results = $schemeService->getCovid19Samples($shipmentId, $participantId);
		}


		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('s' => 'shipment'))
			->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id')
			->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('fullscore' => new Zend_Db_Expr("SUM(if(s.max_score = sp.shipment_score, 1, 0))")))
			->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id')
			->where("sp.shipment_id = ?", $shipmentId)
			//->where("substring(sp.evaluation_status,4,1) != '0'")
			->group('sp.map_id');
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



		return array(
			'participant' => $participantData,
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

	public function viewEvaluation($shipmentId, $participantId, $scheme)
	{
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
		} else if ($scheme == 'recency') {
			$possibleResults = $schemeService->getPossibleResults('recency');
			$evalComments = $schemeService->getSchemeEvaluationComments('recency');
			$results = $schemeService->getRecencySamples($shipmentId, $participantId);
		} else if ($scheme == 'covid19') {
			$possibleResults = $schemeService->getPossibleResults('covid19');
			$evalComments = $schemeService->getSchemeEvaluationComments('covid19');
			$results = $schemeService->getCovid19Samples($shipmentId, $participantId);
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
			//->where("substring(sp.evaluation_status,4,1) != '0'")
			->group('sp.map_id');

		$shipmentOverall = $db->fetchAll($sql);
		//     Zend_Debug::dump($shipmentOverall);die;

		$noOfParticipants = count($shipmentOverall);
		$numScoredFull = 0;
		foreach ($shipmentOverall as $shipment) {
			$numScoredFull += $shipment['fullscore'];
		}

		return array(
			'participant' => $participantData,
			'shipment' => $shipmentData,
			'possibleResults' => $possibleResults,
			'totalParticipants' => $noOfParticipants,
			'fullScorers' => $numScoredFull,
			'evalComments' => $evalComments,
			'controlResults' => $controlRes,
			'results' => $sampleRes
		);
	}

	public function updateShipmentResults($params)
	{
		// Zend_Debug::dump($params);die;
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$authNameSpace = new Zend_Session_Namespace('administrators');
		$admin = $authNameSpace->admin_id;
		$size = count($params['sampleId']);
		$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
		$config = new Zend_Config_Ini($file, APPLICATION_ENV);
		$failureReason = array();
		/* Manual result override changes */
		if (isset($params['manualOverride']) && $params['manualOverride'] == "yes") {
			$shipmentDB = new Application_Model_DbTable_Shipments();
			$shipmentDeails = $shipmentDB->fetchRow("shipment_id = " . $params['shipmentId']);
			$maxScore = ((isset($shipmentDeails['max_score']) && $shipmentDeails['max_score'] != "") ? $shipmentDeails['max_score'] : 0);
			$shipmentScore = ((isset($params['shipmentScore']) && $params['shipmentScore'] != "") ? $params['shipmentScore'] : 0);
			$docScore = ((isset($params['documentationScore']) && $params['documentationScore'] != "") ? $params['documentationScore'] : 0);
			if (isset($params['manualCorrective']) && $params['manualCorrective'] != "") {
				$i = 0;
				foreach ($params['manualCorrective'] as $warning => $correctiveAction) {
					$failureReason[$i]['warning'] = $warning;
					$failureReason[$i]['correctiveAction'] = $correctiveAction;
					$i++;
				}
			}
		}
		if ($params['scheme'] == 'eid') {

			$attributes = array(
				"sample_rehydration_date" => Pt_Commons_General::dateFormat($params['sampleRehydrationDate']),
				"extraction_assay" => $params['extractionAssay'],
				"detection_assay" => $params['detectionAssay'],
				"extraction_assay_expiry_date" => Pt_Commons_General::dateFormat($params['extractionAssayExpiryDate']),
				"detection_assay_expiry_date" => Pt_Commons_General::dateFormat($params['detectionAssayExpiryDate']),
				"extraction_assay_lot_no" => $params['extractionAssayLotNo'],
				"detection_assay_lot_no" => $params['detectionAssayLotNo'],
			);

			if (isset($params['otherAssay']) && $params['otherAssay'] != "") {
				$attributes['other_assay'] = $params['otherAssay'];
			}
			if (isset($params['uploadedFilePath']) && $params['uploadedFilePath'] != "") {
				$attributes['uploadedFilePath'] = $params['uploadedFilePath'];
			}

			$attributes = json_encode($attributes);
			$mapData = array(
				"shipment_receipt_date" => Pt_Commons_General::dateFormat($params['receiptDate']),
				"attributes" => $attributes,
				"mode_id" => $params['modeOfReceipt'],
				"supervisor_approval" => $params['supervisorApproval'],
				"participant_supervisor" => $params['participantSupervisor'],
				"user_comment" => $params['userComments'],
				"updated_by_admin" => $admin,
				"updated_on_admin" => new Zend_Db_Expr('now()')
			);


			if (isset($params['testDate']) && trim($params['testDate']) != '') {
				$mapData['shipment_test_date'] = Pt_Commons_General::dateFormat($params['testDate']);
			} else {
				$mapData['shipment_test_date'] = new Zend_Db_Expr('now()');
			}

			if (isset($params['testReportedDate']) && trim($params['testReportedDate']) != '') {
				$mapData['shipment_test_report_date'] = Pt_Commons_General::dateFormat($params['testReportedDate']);
			} else {
				$mapData['shipment_test_report_date'] = new Zend_Db_Expr('now()');
			}	

			if (isset($params['customField1']) && trim($params['customField1']) != "") {
				$mapData['custom_field_1'] = $params['customField1'];
			}

			if (isset($params['customField2']) && trim($params['customField2']) != "") {
				$mapData['custom_field_2'] = $params['customField2'];
			}

			$db->update('shipment_participant_map', $mapData, "map_id = " . $params['smid']);
			$db->delete('response_result_eid', "shipment_map_id = " . $params['smid']);
			for ($i = 0; $i < $size; $i++) {


				/* $db = Zend_Db_Table_Abstract::getDefaultAdapter();
				$sql = $db->select()->from('response_result_eid')
					->where("shipment_map_id = " . $params['smid'] . " AND sample_id = " . $params['sampleId'][$i]);
				$respResult = $db->fetchRow($sql); */

				/* if (false != $respResult) {
					$resultData = array(
						'reported_result' => $params['reported'][$i],
						'updated_by' => $admin,
						'updated_on' => new Zend_Db_Expr('now()')
					);
					$db->update('response_result_eid', $resultData, "shipment_map_id = " . $params['smid'] . " AND sample_id = " . $params['sampleId'][$i]);
				} else { */
				$resultData = array(
					'shipment_map_id' => $params['smid'],
					'sample_id' => $params['sampleId'][$i],
					'reported_result' => $params['reported'][$i],
					'hiv_ct_od' => '',
					'ic_qs' => '',
					'created_by' => $admin,
					'created_on' => new Zend_Db_Expr('now()')
				);
				$db->insert('response_result_eid', $resultData);
				// }
			}
			/* Manual result override changes */
			if (isset($params['manualOverride']) && $params['manualOverride'] == "yes") {

				$grandTotal = ($shipmentScore + $docScore);
				if ($grandTotal < $config->evaluation->dts->passPercentage) {
					$finalResult = 2;
				} else {
					$finalResult = 1;
				}
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
			if (isset($params['customField1']) && trim($params['customField1']) != "") {
				$mapdata['custom_field_1'] = $params['customField1'];
			}

			if (isset($params['customField2']) && trim($params['customField2']) != "") {
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
					'updated_on' => new Zend_Db_Expr('now()')
				), "shipment_map_id = " . $params['smid'] . " AND sample_id = " . $params['sampleId'][$i]);
			}
			/* Manual result override changes */
			if (isset($params['manualOverride']) && $params['manualOverride'] == "yes") {

				$grandTotal = number_format($shipmentScore + $docScore);
				if ($grandTotal < $config->evaluation->dts->passPercentage) {
					$finalResult = 2;
				} else {
					$finalResult = 1;
				}
			}
		} else if ($params['scheme'] == 'vl') {

			$attributes = array(
				"sample_rehydration_date" => Pt_Commons_General::dateFormat($params['sampleRehydrationDate']),
				"vl_assay" => $params['vlAssay'],
				"assay_lot_number" => $params['assayLotNumber'],
				"assay_expiration_date" => Pt_Commons_General::dateFormat($params['assayExpirationDate']),
				"specimen_volume" => $params['specimenVolume']
			);

			if (isset($params['otherAssay']) && $params['otherAssay'] != "") {
				$attributes['other_assay'] = $params['otherAssay'];
			}
			if (isset($params['uploadedFilePath']) && $params['uploadedFilePath'] != "") {
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

			if (isset($params['customField1']) && trim($params['customField1']) != "") {
				$mapData['custom_field_1'] = $params['customField1'];
			}

			if (isset($params['customField2']) && trim($params['customField2']) != "") {
				$mapData['custom_field_2'] = $params['customField2'];
			}

			$db->update('shipment_participant_map', $mapData, "map_id = " . $params['smid']);
			$db->delete('response_result_vl', "shipment_map_id = " . $params['smid']);
			/* $shipmentOverall = $db->fetchRow($db->select()->from('response_result_vl')
			->where("shipment_map_id = ?", $params['smid'])); */
			$resVlDb = new Application_Model_DbTable_ResponseVl();

			for ($i = 0; $i < $size; $i++) {
				// if (!$shipmentOverall) {
				$resData = array(
					'shipment_map_id' 		=> $params['smid'],
					'vl_assay' 				=> $params['vlAssay'],
					'sample_id' 			=> $params['sampleId'][$i],
					'reported_viral_load' 	=> $params['reported'][$i],
					'created_by' 			=> $admin,
					'created_on' 			=> new Zend_Db_Expr('now()'),
					'updated_by' 			=> $admin,
					'updated_on' 			=> new Zend_Db_Expr('now()')
				);
				$id = $resVlDb->insert($resData);
				// } else {
				// 	$resData = array(
				// 		'reported_viral_load'	=> $params['reported'][$i],
				// 		'updated_by' 			=> $admin,
				// 		'updated_on' 			=> new Zend_Db_Expr('now()')
				// 	);
				// 	$id = $resVlDb->update($resData, "shipment_map_id = " . $params['smid'] . " AND sample_id = " . $params['sampleId'][$i]);
				// }
			}
			/* Manual result override changes */
			if (isset($params['manualOverride']) && $params['manualOverride'] == "yes") {

				$grandTotal = ($shipmentScore + $docScore);
				if ($shipmentScore != $maxScore) {
					$finalResult = 2;
				} else {
					$finalResult = 1;
				}
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
					'updated_on' => new Zend_Db_Expr('now()')
				), "shipment_map_id = " . $params['smid'] . " AND sample_id = " . $params['sampleId'][$i]);
			}
		} else if ($params['scheme'] == 'recency') {


			$attributes["sample_rehydration_date"] = Pt_Commons_General::dateFormat($params['rehydrationDate']);
			$attributes["algorithm"] = $params['algorithm'];
			$attributes = array(
				"sample_rehydration_date" => Pt_Commons_General::dateFormat($params['sampleRehydrationDate']),
				"recency_assay" => $params['recencyAssay'],
				"recency_assay_lot_no" => $params['recencyAssayLotNo'],
				"recency_assay_expiry_date" => Pt_Commons_General::dateFormat($params['recencyAssayExpiryDate']),
			);

			$attributes = json_encode($attributes);
			$mapdata = array(
				"shipment_receipt_date" => Pt_Commons_General::dateFormat($params['receiptDate']),
				"shipment_test_date" => Pt_Commons_General::dateFormat($params['testedOn']),
				"attributes" => $attributes,
				"supervisor_approval" => $params['supervisorApproval'],
				"participant_supervisor" => $params['participantSupervisor'],
				"user_comment" => $params['userComments'],
				"updated_by_admin" => $admin,
				"updated_on_admin" => new Zend_Db_Expr('now()')
			);
			if (isset($params['customField1']) && trim($params['customField1']) != "") {
				$mapdata['custom_field_1'] = $params['customField1'];
			}

			if (isset($params['customField2']) && trim($params['customField2']) != "") {
				$mapdata['custom_field_2'] = $params['customField2'];
			}
			$db->update('shipment_participant_map', $mapdata, "map_id = " . $params['smid']);

			for ($i = 0; $i < $size; $i++) {
				$db->update('response_result_recency', array(
					'reported_result' => $params['reported_result'][$i],
					'control_line' => $params['controlLine'][$i],
					'diagnosis_line' => $params['verificationLine'][$i],
					'longterm_line' => $params['longtermLine'][$i],
					'updated_by' => $admin,
					'updated_on' => new Zend_Db_Expr('now()')
				), "shipment_map_id = " . $params['smid'] . " and sample_id = " . $params['sampleId'][$i]);
			}
			/* Manual result override changes */
			if (isset($params['manualOverride']) && $params['manualOverride'] == "yes") {
				$grandTotal = ($shipmentScore + $docScore);
				if ($grandTotal < $config->evaluation->recency->passPercentage) {
					$finalResult = 2;
				} else {
					$finalResult = 1;
				}
			}
		} else if ($params['scheme'] == 'covid19') {

			$attributes["sample_rehydration_date"] = Pt_Commons_General::dateFormat($params['rehydrationDate']);
			// $attributes["algorithm"] = $params['algorithm'];
			$attributes = json_encode($attributes);

			$mapdata = array(
				"shipment_receipt_date"		=> Pt_Commons_General::dateFormat($params['receivedOn']),
				"shipment_test_date" 		=> Pt_Commons_General::dateFormat($params['testedOn']),
				"attributes" 				=> $attributes,
				"supervisor_approval" 		=> $params['supervisorApproval'],
				"participant_supervisor" 	=> $params['participantSupervisor'],
				"number_of_tests" 			=> $params['numberOfParticipantTest'],
				"specimen_volume" 			=> $params['specimenVolume'],
				"user_comment" 				=> $params['userComments'],
				"updated_by_admin" 			=> $admin,
				"updated_on_admin" 			=> new Zend_Db_Expr('now()')
			);
			if (isset($params['customField1']) && trim($params['customField1']) != "") {
				$mapdata['custom_field_1'] = $params['customField1'];
			}

			if (isset($params['customField2']) && trim($params['customField2']) != "") {
				$mapdata['custom_field_2'] = $params['customField2'];
			}
			$db->update('shipment_participant_map', $mapdata, "map_id = " . $params['smid']);

			for ($i = 0; $i < $size; $i++) {
				$db->update('response_result_covid19', array(
					'test_type_1' => $params['test_type_1'],
					'lot_no_1' => $params['lot_no_1'],
					'exp_date_1' => Pt_Commons_General::dateFormat($params['exp_date_1']),
					'test_result_1' => $params['test_result_1'][$i],
					'test_type_2' => $params['test_type_2'],
					'lot_no_2' => $params['lot_no_2'],
					'exp_date_2' => Pt_Commons_General::dateFormat($params['exp_date_2']),
					'test_result_2' => $params['test_result_2'][$i],
					'test_type_3' => $params['test_type_3'],
					'lot_no_3' => $params['lot_no_3'],
					'exp_date_3' => Pt_Commons_General::dateFormat($params['exp_date_3']),
					'test_result_3' => $params['test_result_3'][$i],
					'reported_result' => $params['reported_result'][$i],
					'updated_by' => $admin,
					'updated_on' => new Zend_Db_Expr('now()')
				), "shipment_map_id = " . $params['smid'] . " AND sample_id = " . $params['sampleId'][$i]);
			}

			/* Save Gene Type */
			$geneIdentifyTypesDb = new Application_Model_DbTable_Covid19IdentifiedGenes();
			$geneIdentifyTypesDb->saveCovid19IdentifiedGenesResults($params);
			/* Manual result override changes */
			if (isset($params['manualOverride']) && $params['manualOverride'] == "yes") {
				$grandTotal = ($shipmentScore + $docScore);
				if ($grandTotal < $config->evaluation->covid19->passPercentage) {
					$finalResult = 2;
				} else {
					$finalResult = 1;
				}
			}
		}

		$params['isFollowUp'] = (isset($params['isFollowUp']) && $params['isFollowUp'] != "") ? $params['isFollowUp'] : "no";

		$updateArray = array('evaluation_comment' => $params['comment'], 'optional_eval_comment' => $params['optionalComments'], 'is_followup' => $params['isFollowUp'], 'is_excluded' => $params['isExcluded'], 'updated_by_admin' => $admin, 'updated_on_admin' => new Zend_Db_Expr('now()'));
		if ($params['isExcluded'] == 'yes') {
			$updateArray['final_result'] = 3;
		}
		/* Manual result override changes */
		if (isset($params['manualOverride']) && $params['manualOverride'] == "yes") {
			$updateArray['shipment_score'] = $shipmentScore;
			$updateArray['documentation_score'] = $docScore;
			$updateArray['final_result'] = $finalResult;
			if (isset($failureReason) && $failureReason != "") {
				$updateArray['failure_reason'] = json_encode($failureReason);
			}
		}
		$updateArray['manual_override'] = (isset($params['manualOverride']) && $params['manualOverride'] != "") ? $params['manualOverride'] : 'no';
		// Zend_Debug::dump($params['smid']);
		// Zend_Debug::dump($updateArray);
		$id = $db->update('shipment_participant_map', $updateArray, "map_id = " . $params['smid']);
		// Zend_Debug::dump($id);die;
	}

	public function updateShipmentComment($params)
	{
		// Zend_Debug::dump($params);
		// die;
		$shipmentId = base64_decode($params['shipmentId']);
		$comment = $params['shipmentComment'];
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$authNameSpace = new Zend_Session_Namespace('administrators');
		$admin = $authNameSpace->admin_id;
		$noOfRows = $db->update('shipment', array('shipment_comment' => $comment, 'updated_by_admin' => $admin, 'updated_on_admin' => new Zend_Db_Expr('now()')), "shipment_id = " . $shipmentId);
		if (isset($_FILES['correctiveActionFile']['name']) && count($_FILES['correctiveActionFile']) > 0) {
			if (isset($_FILES['correctiveActionFile']['name']) && trim($_FILES['correctiveActionFile']['name']) != '') {
				if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'corrective-action-files') && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'corrective-action-files')) {
					mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'corrective-action-files');
				}

				$extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['correctiveActionFile']['name'], PATHINFO_EXTENSION));
				$fileName = "corrective-action-files" . $shipmentId . "." . $extension;
				if (move_uploaded_file($_FILES["correctiveActionFile"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR . "corrective-action-files" . DIRECTORY_SEPARATOR . $fileName)) {
					$db->update('shipment', array('corrective_action_file' => $fileName), "shipment_id = " . $shipmentId);
				}
			}
		}
		if ($noOfRows > 0) {
			return "Comment updated";
		} else {
			return "Unable to update shipment comment. Please try again later.";
		}
	}

	public function updateShipmentStatus($shipmentId, $status)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$authNameSpace = new Zend_Session_Namespace('administrators');
		$admin = $authNameSpace->admin_id;
		$noOfRows = $db->update('shipment', array('status' => $status, 'updated_by_admin' => $admin, 'updated_on_admin' => new Zend_Db_Expr('now()')), "shipment_id = " . $shipmentId);
		if ($noOfRows > 0) {
			return "Status updated";
		} else {
			return "Unable to update shipment status. Please try again later.";
		}
	}

	public function getShipmentToEvaluateReports($shipmentId, $reEvaluate = false)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()
			->from(array('sp' => 'shipment_participant_map'))
			->join(array('s' => 'shipment'), 'sp.shipment_id=s.shipment_id')
			->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('distribution_code', 'distribution_date'))
			->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
			->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('first_name', 'last_name', 'lab_name', 'unique_identifier', 'country'))
			->join(array('c' => 'countries'), 'p.country=c.id', array('country_name' => 'iso_name'))
			->joinLeft(array('res' => 'r_results'), 'res.result_id=sp.final_result')
			->where("s.shipment_id = ?", $shipmentId);
		$shipmentResult = $db->fetchAll($sql);
		return $shipmentResult;
	}

	public function getReportStatus($shipmentId, $type = '')
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		return $db->fetchRow($db->select()->from('evaluation_queue')->where('shipment_id = ?', $shipmentId)->where('report_type = ?', $type));
	}

	public function getEvaluateReportsInPdf($shipmentId, $sLimit = null, $sOffset = null)
	{
		$vlGraphResult = array();
		$mapRes = array();
		$shipmentResult = array();
		$vlGraphResult = array();

		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$schemeService = new Application_Service_Schemes();
		$sql = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date', 's.lastdate_response', 's.max_score', 's.shipment_comment', 'shipment_attributes', 'pt_co_ordinator_name'))
			->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('d.distribution_id', 'd.distribution_code', 'd.distribution_date'))
			->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('sp.map_id', 'sp.participant_id', 'sp.shipment_test_date', 'sp.shipment_receipt_date', 'sp.shipment_test_report_date', 'sp.supervisor_approval', 'sp.final_result', 'sp.failure_reason', 'sp.shipment_score', 'sp.final_result', 'sp.attributes', 'sp.is_followup', 'sp.is_excluded', 'sp.optional_eval_comment', 'sp.evaluation_comment', 'sp.documentation_score', 'sp.participant_supervisor', 'sp.custom_field_1', 'sp.custom_field_2', 'sp.specimen_volume', 'sp.manual_override'))
			->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('sl.scheme_id', 'sl.scheme_name'))
			->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status', 'p.institute_name', 'p.state', 'p.city', 'p.region', 'p.lab_name'))
			->joinLeft(array('res' => 'r_results'), 'res.result_id=sp.final_result', array('result_name'))
			->joinLeft(array('ec' => 'r_evaluation_comments'), 'ec.comment_id=sp.evaluation_comment', array('evaluationComments' => 'comment'))
			->where("s.shipment_id = ?", $shipmentId)
			->where("sp.is_excluded = 'no'")
			//->where("substring(sp.evaluation_status,4,1) != '0'")
		;
		if (isset($sLimit) && isset($sOffset)) {
			$sql = $sql->limit($sLimit, $sOffset);
		}
		// echo ($sql);die;
		$sRes = $shipmentResult = $db->fetchAll($sql);

		$i = 0;
		//$mapRes="";

		foreach ($sRes as $res) {
			// Zend_Debug::dump($res['shipment_test_report_date']);die;
			$dmResult = $db->fetchAll($db->select()->from(array('pmm' => 'participant_manager_map'))
				->join(array('dm' => 'data_manager'), 'dm.dm_id=pmm.dm_id', array('institute'))
				->where("pmm.participant_id=" . $res['participant_id']));
			if (isset($res['last_name']) && trim($res['last_name']) != "") {
				$res['last_name'] = "_" . $res['last_name'];
			}

			foreach ($dmResult as $dmRes) {
				$participantFileName = $res['shipment_code'] . "-" . ($res['map_id']);
				$participantFileName = str_replace(" ", "-", $participantFileName);
				if (count($mapRes) == 0) {
					$mapRes[$dmRes['dm_id']] =  $dmRes['participant_id'] . "#" . $participantFileName;
				} else if (array_key_exists($dmRes['dm_id'], $mapRes)) {
					$mapRes[$dmRes['dm_id']] .= "," . $dmRes['participant_id'] . "#" . $participantFileName;
				} else {
					$mapRes[$dmRes['dm_id']] = $dmRes['participant_id'] . "#" . $participantFileName;
				}
			}
			if ($res['scheme_type'] == 'dbs') {
				$sQuery = $db->select()->from(array('resdbs' => 'response_result_dbs'), array('resdbs.shipment_map_id', 'resdbs.sample_id', 'resdbs.reported_result', 'responseDate' => 'resdbs.created_on'))
					->join(array('respr' => 'r_possibleresult'), 'respr.id=resdbs.reported_result', array('labResult' => 'respr.response'))
					->join(array('sp' => 'shipment_participant_map'), 'sp.map_id=resdbs.shipment_map_id', array('sp.shipment_id', 'sp.participant_id', 'sp.participant_supervisor'))
					->join(array('refdbs' => 'reference_result_dbs'), 'refdbs.shipment_id=sp.shipment_id and refdbs.sample_id=resdbs.sample_id', array('refdbs.reference_result', 'refdbs.sample_label', 'resdbs.mandatory'))
					->join(array('refpr' => 'r_possibleresult'), 'refpr.id=refdbs.reference_result', array('referenceResult' => 'refpr.response'))
					->where("resdbs.shipment_map_id = ?", $res['map_id']);


				$shipmentResult[$i]['responseResult'] = $db->fetchAll($sQuery);
			} else if ($res['scheme_type'] == 'dts') {

				$sQuery = $db->select()->from(array('resdts' => 'response_result_dts'), array('resdts.shipment_map_id', 'resdts.sample_id', 'resdts.reported_result', 'responseDate' => 'resdts.created_on', 'calculated_score', 'test_kit_name_1', 'lot_no_1', 'exp_date_1', 'test_kit_name_2', 'lot_no_2', 'exp_date_2', 'test_kit_name_3', 'lot_no_3', 'exp_date_3', 'test_result_1', 'test_result_2', 'test_result_3'))
					->join(array('respr' => 'r_possibleresult'), 'respr.id=resdts.reported_result', array('labResult' => 'respr.response'))
					->join(array('sp' => 'shipment_participant_map'), 'sp.map_id=resdts.shipment_map_id', array('sp.shipment_id', 'sp.shipment_receipt_date', 'sp.participant_id', 'sp.attributes', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_test_date', 'sp.failure_reason'))
					->join(array('refdts' => 'reference_result_dts'), 'refdts.shipment_id=sp.shipment_id and refdts.sample_id=resdts.sample_id', array('refdts.reference_result', 'refdts.sample_label', 'refdts.mandatory', 'refdts.sample_score', 'refdts.control'))
					->joinLeft(array('dtstk1' => 'r_testkitname_dts'), 'dtstk1.TestKitName_ID=resdts.test_kit_name_1', array('testkit1' => 'dtstk1.TestKit_Name'))
					->joinLeft(array('dtstk2' => 'r_testkitname_dts'), 'dtstk2.TestKitName_ID=resdts.test_kit_name_2', array('testkit2' => 'dtstk2.TestKit_Name'))
					->joinLeft(array('dtstk3' => 'r_testkitname_dts'), 'dtstk3.TestKitName_ID=resdts.test_kit_name_3', array('testkit3' => 'dtstk3.TestKit_Name'))
					->join(array('refpr' => 'r_possibleresult'), 'refpr.id=refdts.reference_result', array('referenceResult' => 'refpr.response'))
					->where("resdts.shipment_map_id = ?", $res['map_id']);


				$shipmentResult[$i]['responseResult'] = $db->fetchAll($sQuery);
				//Zend_Debug::dump($shipmentResult);
			} else if ($res['scheme_type'] == 'recency') {

				$sQuery = $db->select()->from(array('resrecency' => 'response_result_recency'), array('resrecency.shipment_map_id', 'resrecency.sample_id', 'resrecency.reported_result', 'responseDate' => 'resrecency.created_on', 'calculated_score', 'control_line', 'diagnosis_line', 'longterm_line'))
					->join(array('respr' => 'r_possibleresult'), 'respr.id=resrecency.reported_result', array('labResult' => 'respr.response'))
					->join(array('sp' => 'shipment_participant_map'), 'sp.map_id=resrecency.shipment_map_id', array('sp.shipment_score', 'sp.shipment_id', 'sp.shipment_receipt_date', 'sp.participant_id', 'sp.attributes', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_test_date', 'sp.failure_reason'))
					->join(array('refrecency' => 'reference_result_recency'), 'refrecency.shipment_id=sp.shipment_id and refrecency.sample_id=resrecency.sample_id', array('refrecency.reference_result', 'refControlLine' => 'refrecency.reference_control_line', 'refverificationLine' => 'refrecency.reference_diagnosis_line', 'refLongTermLine' => 'refrecency.reference_longterm_line', 'refrecency.sample_label', 'refrecency.mandatory', 'refrecency.sample_score', 'refrecency.control'))
					->join(array('refpr' => 'r_possibleresult'), 'refpr.id=refrecency.reference_result', array('referenceResult' => 'refpr.response'))
					->where("resrecency.shipment_map_id = ?", $res['map_id']);

				$shipmentResult[$i]['responseResult'] = $db->fetchAll($sQuery);
				//Zend_Debug::dump($shipmentResult);
			} else if ($res['scheme_type'] == 'eid') {

				$extractionAssay = $schemeService->getEidExtractionAssay();
				$detectionAssay = $schemeService->getEidDetectionAssay();
				$attributes = json_decode($res['attributes'], true);

				if (isset($attributes['extraction_assay'])) {
					//$shipmentResult[$i]['extractionAssayVal']=$extractionAssay[$attributes['extraction_assay']];
					$shipmentResult[$i]['extractionAssayVal'] = (isset($extractionAssay[$attributes['extraction_assay']]) ? $extractionAssay[$attributes['extraction_assay']] : "");
				}
				if (isset($attributes['detection_assay'])) {

					$shipmentResult[$i]['detectionAssayVal'] = (isset($detectionAssay[$attributes['detection_assay']]) ? $detectionAssay[$attributes['detection_assay']] : "");
				}

				$sQuery = $db->select()->from(array('reseid' => 'response_result_eid'), array('reseid.shipment_map_id', 'reseid.sample_id', 'reseid.reported_result', 'responseDate' => 'reseid.created_on'))
					->join(array('respr' => 'r_possibleresult'), 'respr.id=reseid.reported_result', array('labResult' => 'respr.response'))
					->join(array('sp' => 'shipment_participant_map'), 'sp.map_id=reseid.shipment_map_id', array('sp.shipment_id', 'sp.participant_id', 'sp.shipment_receipt_date', 'sp.shipment_test_date', 'sp.attributes', 'sp.shipment_test_report_date'))
					->join(array('refeid' => 'reference_result_eid'), 'refeid.shipment_id=sp.shipment_id and refeid.sample_id=reseid.sample_id', array('refeid.reference_result', 'refeid.sample_label', 'refeid.mandatory'))
					->join(array('refpr' => 'r_possibleresult'), 'refpr.id=refeid.reference_result', array('referenceResult' => 'refpr.response'))
					->where("refeid.control = 0")
					->where("reseid.shipment_map_id = ?", $res['map_id'])
					->order(array('refeid.sample_id'));

				//error_log($sQuery);
				$eidDetectionAssayResultSet = $schemeService->getEidDetectionAssay();
				$result = $db->fetchAll($sQuery);
				$response = array();
				foreach ($result as $key => $row) {
					if (isset($row['attributes'])) {
						$attributes = json_decode($row['attributes'], true);
					}
					$row['vl_assay'] = $eidDetectionAssayResultSet[$attributes['extraction_assay']];
					$response[$key] = $row;
				}
				$shipmentResult[$i]['responseResult'] = $response;
			} else if ($res['scheme_type'] == 'vl') {
				$vlAssayResultSet = $schemeService->getVlAssay();
				$vlAssayList = array();
				$vlRange = $schemeService->getVlRange($shipmentId);
				$results = $schemeService->getVlSamples($shipmentId, $res['participant_id']);
				//$assayResults = $schemeService->getShipmentParticipantBassedAssay($shipmentId);
				$attributes = json_decode($res['attributes'], true);
				$shipmentAttributes = json_decode($res['shipment_attributes'], true);

				$methodOfEvaluation = isset($shipmentAttributes['methodOfEvaluation']) ? $shipmentAttributes['methodOfEvaluation'] : 'standard';
				if ($vlRange == null || $vlRange == "" || count($vlRange) == 0) {
					$schemeService->setVlRange($shipmentId, $methodOfEvaluation);
					$vlRange = $schemeService->getVlRange($shipmentId);
				}

				$sql = $db->select()->from(array('ref' => 'reference_result_vl'), array('sample_id', 'ref.sample_label', 'control', 'mandatory'))
					->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id', array('*'))
					->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id', array('sp.map_id', 'sp.attributes', 'sp.shipment_receipt_date', 'sp.shipment_test_date', 'sp.is_pt_test_not_performed', 'sp.is_excluded'))
					->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.unique_identifier'))
					->joinLeft(array('res' => 'response_result_vl'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('reported_viral_load', 'z_score'))
					//->where("sp.is_pt_test_not_performed is NULL")
					->where("sp.is_excluded ='no'")
					->where("sp.shipment_test_date IS NOT NULL AND sp.shipment_test_date not like '' AND sp.shipment_test_date not like '0000-00-00' AND sp.shipment_test_date not like '0000-00-00'")
					->where('sp.shipment_id = ? ', $shipmentId);

				//echo $sql;die;

				$spmResult = $db->fetchAll($sql);

				$vlGraphResult = array();
				foreach ($spmResult as $val) {
					$valAttributes = json_decode($val['attributes'], true);
					if ((isset($attributes['id']) && isset($valAttributes['vl_assay'])) && ($attributes['vl_assay'] == $valAttributes['vl_assay'])) {
						if (array_key_exists($val['sample_label'], $vlGraphResult)) {
							if (isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low']) && $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low'] <= $val['reported_viral_load'] && isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high']) && $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high'] >= $val['reported_viral_load']) {
								$vlGraphResult[$val['sample_label']]['vl'][] = $val['reported_viral_load'];
							} else {
								$vlGraphResult[$val['sample_label']]['NA'][] = $val['reported_viral_load'];
							}
							//$vlGraphResult[$val['sample_label']]['pId'][]="lab ".$val['unique_identifier'];
						} else {
							$vlGraphResult[$val['sample_label']] = array();
							if (isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low']) && $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low'] <= $val['reported_viral_load'] && isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high']) && $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high'] >= $val['reported_viral_load']) {
								$vlGraphResult[$val['sample_label']]['vl'][] = $val['reported_viral_load'];
							} else {
								$vlGraphResult[$val['sample_label']]['NA'][] = $val['reported_viral_load'];
							}
							if (isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low'])) {
								$vlGraphResult[$val['sample_label']]['low'] = $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low'];
							}
							if (isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high'])) {
								$vlGraphResult[$val['sample_label']]['high'] = $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high'];
							}
						}
					}
				}
				//print_r($vlGraphResult);

				//<-- count no.of labs participans in particular sample
				$cQuery = $db->select()->from(array('ref' => 'reference_result_vl'), array('sample_id', 'ref.sample_label'))
					->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id', array('s.*'))
					->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id', array('sp.map_id', 'sp.attributes', 'sp.shipment_receipt_date', 'sp.shipment_test_date', 'sp.is_pt_test_not_performed', 'sp.is_excluded'))
					->joinLeft(array('res' => 'response_result_vl'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('reported_viral_load', 'z_score'))
					->where("sp.is_pt_test_not_performed is NULL")
					->where("sp.is_excluded ='no'")
					->where('sp.shipment_id = ? ', $shipmentId);

				//echo $cQuery;die;

				$cResult = $db->fetchAll($cQuery);

				$labResult = array();
				foreach ($cResult as $val) {
					$valAttributes = json_decode($val['attributes'], true);
					if ((isset($attributes['vl_assay']) && isset($valAttributes['vl_assay'])) && ($attributes['vl_assay'] == $valAttributes['vl_assay'])) {
						if ($valAttributes['vl_assay'] == 6) {
							$assayName = $valAttributes['other_assay'];
						} else {
							$assayName = $vlAssayResultSet[$valAttributes['vl_assay']];
						}



						if (isset($labResult[$assayName][$val['sample_label']])) {
							$labResult[$assayName][$val['sample_label']] += 1;
						} else {
							$labResult[$assayName][$val['sample_label']] = array();
							$labResult[$assayName][$val['sample_label']] = 1;
						}
					}
				}
				//-------->

				// Zend_Debug::dump($labResult);
				// die;
				$counter = 0;
				$zScore = null;
				$toReturn = array();
				foreach ($results as $result) {
					//$toReturn = array();
					$responseAssay = json_decode($result['attributes'], true);
					if ($responseAssay['vl_assay'] == 6) {
						$assayName = $responseAssay['other_assay'];
					} else {
						$assayName = $vlAssayResultSet[$responseAssay['vl_assay']];
					}
					$toReturn[$counter]['vl_assay'] = isset($vlAssayResultSet[$responseAssay['vl_assay']]) ? $vlAssayResultSet[$responseAssay['vl_assay']] : "";
					$responseAssay = $responseAssay['vl_assay'];

					$vlGraphResult[$result['sample_label']]['pVal'] = $result['reported_viral_load'];
					//print_r($vlGraphResult[$result['sample_label']]['vl']);die;

					$toReturn[$counter]['sample_label'] = $result['sample_label'];
					$toReturn[$counter]['shipment_map_id'] = $result['map_id'];
					$toReturn[$counter]['shipment_id'] = $result['shipment_id'];
					$toReturn[$counter]['responseDate'] = $result['responseDate'];
					$toReturn[$counter]['shipment_score'] = $result['shipment_score'];
					$toReturn[$counter]['shipment_test_date'] = $result['shipment_test_date'];
					$toReturn[$counter]['is_excluded'] = $result['is_excluded'];
					$toReturn[$counter]['is_pt_test_not_performed'] = $result['is_pt_test_not_performed'];
					$toReturn[$counter]['shipment_receipt_date'] = $result['shipment_receipt_date'];
					$toReturn[$counter]['max_score'] = $result['max_score'];
					$toReturn[$counter]['reported_viral_load'] = $result['reported_viral_load'];
					$toReturn[$counter]['no_of_participants'] = $labResult[$assayName][$result['sample_label']];
					if (isset($vlRange[$responseAssay])) {


						if ($methodOfEvaluation == 'standard') {
							// matching reported and low/high limits
							if (isset($result['reported_viral_load']) && $result['reported_viral_load'] != null) {
								if ($vlRange[$responseAssay][$result['sample_id']]['low'] <= $result['reported_viral_load'] && $vlRange[$responseAssay][$result['sample_id']]['high'] >= $result['reported_viral_load']) {
									$grade = 'Acceptable';
								} else {
									$grade = 'Unacceptable';
								}
							}

							if (isset($result['reported_viral_load']) && $result['reported_viral_load'] != null && trim($result['reported_viral_load']) != null) {
								if ($vlRange[$responseAssay][$result['sample_id']]['low'] <= $result['reported_viral_load'] && $vlRange[$responseAssay][$result['sample_id']]['high'] >= $result['reported_viral_load']) {
									$grade = 'Acceptable';
								} else {
									if ($result['sample_score'] > 0) {
										$grade = 'Unacceptable';
									} else {
										$grade = '-';
									}
								}
							} else {
								$grade = 'Unacceptable';
							}
							$toReturn[$counter]['low'] = $vlRange[$responseAssay][$result['sample_id']]['low'];
							$toReturn[$counter]['high'] = $vlRange[$responseAssay][$result['sample_id']]['high'];
							$toReturn[$counter]['sd'] = $vlRange[$responseAssay][$result['sample_id']]['sd'];
							$toReturn[$counter]['mean'] = $vlRange[$responseAssay][$result['sample_id']]['mean'];
							$toReturn[$counter]['median'] = $vlRange[$responseAssay][$result['sample_id']]['median'];
							$toReturn[$counter]['zscore'] = $result['z_score'];
						} else if ($methodOfEvaluation == 'iso17043') {
							// matching reported and low/high limits
							if (isset($result['calculated_score']) && $result['calculated_score'] == 'pass') {
								$grade = 'Acceptable';
							} else if (isset($result['calculated_score']) && $result['calculated_score'] == 'fail') {
								$grade = 'Unacceptable';
							} else if (isset($result['calculated_score']) && $result['calculated_score'] == 'warn') {
								$grade = 'Warning';
							}

							$toReturn[$counter]['low'] = $vlRange[$responseAssay][$result['sample_id']]['q1'];
							$toReturn[$counter]['high'] = $vlRange[$responseAssay][$result['sample_id']]['q3'];
							$toReturn[$counter]['sd'] = $vlRange[$responseAssay][$result['sample_id']]['sd'];
							$toReturn[$counter]['median'] = $vlRange[$responseAssay][$result['sample_id']]['median'];
							$toReturn[$counter]['zscore'] = $result['z_score'];
						}
					} else {
						$toReturn[$counter]['low'] = 'Not Applicable';
						$toReturn[$counter]['high'] = 'Not Applicable';
						$toReturn[$counter]['sd'] = 'Not Applicable';
						$toReturn[$counter]['mean'] = 'Not Applicable';
						$toReturn[$counter]['median'] = 'Not Applicable';
						$grade = 'Not Applicable';
						$toReturn[$counter]['zscore'] = 0;
					}
					$toReturn[$counter]['grade'] = $grade;

					$counter++;
				}

				$shipmentResult[$i]['responseResult'] = $toReturn;
			} else if ($res['scheme_type'] == 'covid19') {

				$sQuery = $db->select()->from(array('resc19' => 'response_result_covid19'), array('resc19.shipment_map_id', 'resc19.sample_id', 'resc19.reported_result', 'responseDate' => 'resc19.created_on', 'calculated_score', 'test_type_1', 'lot_no_1', 'exp_date_1', 'test_type_2', 'lot_no_2', 'exp_date_2', 'test_type_3', 'lot_no_3', 'exp_date_3', 'test_result_1', 'test_result_2', 'test_result_3'))
					->join(array('respr' => 'r_possibleresult'), 'respr.id=resc19.reported_result', array('labResult' => 'respr.response'))
					->join(array('sp' => 'shipment_participant_map'), 'sp.map_id=resc19.shipment_map_id', array('sp.shipment_id', 'sp.shipment_receipt_date', 'sp.participant_id', 'sp.attributes', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_test_date', 'sp.failure_reason', 'sp.specimen_volume'))
					->join(array('refc19' => 'reference_result_covid19'), 'refc19.shipment_id=sp.shipment_id and refc19.sample_id=resc19.sample_id', array('refc19.reference_result', 'refc19.sample_label', 'refc19.mandatory', 'refc19.sample_score', 'refc19.control'))
					->joinLeft(array('c19tk1' => 'r_test_type_covid19'), 'c19tk1.test_type_id=resc19.test_type_1', array('testPlatform1' => 'c19tk1.test_type_name'))
					->joinLeft(array('c19tk2' => 'r_test_type_covid19'), 'c19tk2.test_type_id=resc19.test_type_2', array('testPlatform2' => 'c19tk2.test_type_name'))
					->joinLeft(array('c19tk3' => 'r_test_type_covid19'), 'c19tk3.test_type_id=resc19.test_type_3', array('testPlatform3' => 'c19tk3.test_type_name'))
					->join(array('refpr' => 'r_possibleresult'), 'refpr.id=refc19.reference_result', array('referenceResult' => 'refpr.response'))
					->where("resc19.shipment_map_id = ?", $res['map_id']);


				$shipmentResult[$i]['responseResult'] = $db->fetchAll($sQuery);
				//Zend_Debug::dump($shipmentResult);
			}
			//Zend_Debug::dump($shipmentResult);die;
			$i++;
			$db->update('shipment_participant_map', array('report_generated' => 'yes'), "map_id=" . $res['map_id']);
			$db->update('shipment', array('status' => 'evaluated'), "shipment_id=" . $shipmentId);
		}

		//Zend_Debug::dump($shipmentResult);die;

		$result = array('shipment' => $shipmentResult, 'dmResult' => $mapRes, 'vlGraphResult' => $vlGraphResult);
		return $result;
	}

	public function getSummaryReportsInPdf($shipmentId)
	{
		$responseResult = array();
		$vlCalculation = array();
		$vlAssayRes = array();
		$penResult = array();
		$shipmentResult = array();
		$config = new Zend_Config_Ini(APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini", APPLICATION_ENV);
		$pass = $config->evaluation->dts->passPercentage;

		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date', 's.lastdate_response', 's.max_score', 'shipment_attributes', 'pt_co_ordinator_name'))
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
					//->where("substring(spm.evaluation_status,4,1) != '0'")
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
						//->where("substring(spm.evaluation_status,4,1) != '0'")
						->group(array("refdbs.sample_id"));

					$shipmentResult['summaryResult'][] = $sQueryRes;
					//Zend_Debug::dump($shipmentResult['summaryResult']);die;
					$shipmentResult['summaryResult'][count($shipmentResult['summaryResult']) - 1]['correctCount'] = $db->fetchAll($tQuery);


					$rQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id'))
						->join(array('resdbs' => 'response_result_dbs'), 'resdbs.shipment_map_id=spm.map_id', array('resdbs.eia_1', 'resdbs.eia_2', 'resdbs.eia_3', 'resdbs.wb'))
						//->where("substring(spm.evaluation_status,4,1) != '0'")
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

				$sQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array(
					'spm.map_id', 'spm.shipment_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.attributes', 'spm.is_excluded',
					'number_failed' => new Zend_Db_Expr("SUM(CASE WHEN (spm.final_result = 2 AND spm.is_excluded != 'yes') THEN 1 ELSE 0 END)"),
					'number_passed' => new Zend_Db_Expr("SUM(CASE WHEN (spm.final_result = 1 AND spm.is_excluded != 'yes') THEN 1 ELSE 0 END)"),
					'totalScore' => new Zend_Db_Expr("SUM(spm.documentation_score+spm.shipment_score)"),
					'0-59' => new Zend_Db_Expr("SUM(spm.documentation_score+spm.shipment_score) >= 0 AND SUM(spm.documentation_score+spm.shipment_score) <= 59"),
					'60-69' => new Zend_Db_Expr("SUM(spm.documentation_score+spm.shipment_score) >= 60 AND SUM(spm.documentation_score+spm.shipment_score) <= 69"),
					'70-' . $pass => new Zend_Db_Expr("SUM(spm.documentation_score+spm.shipment_score) >= 70 AND SUM(spm.documentation_score+spm.shipment_score) <= $pass"),
					'above ' . $pass => new Zend_Db_Expr("SUM(spm.documentation_score+spm.shipment_score) >= $pass"),
					'failed' => new Zend_Db_Expr("SUM(spm.documentation_score+spm.shipment_score) >= $pass AND spm.final_result = 2"),
				))
					->join(array('s' => 'shipment'), 's.shipment_id=spm.shipment_id', array(''))
					->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status'))
					->joinLeft(array('res' => 'r_results'), 'res.result_id=spm.final_result', array('result_name'))
					->where("spm.shipment_id = ?", $shipmentId)
					->where("spm.final_result IS NOT NULL")
					->where("spm.final_result !=''")
					->group('spm.map_id');
				// die($sQuery);
				$sQueryRes = $db->fetchAll($sQuery);
				if (count($sQueryRes) > 0) {

					$tQuery = $db->select()->from(array('refdts' => 'reference_result_dts'), array('refdts.sample_id', 'refdts.sample_label'))
						->join(array('s' => 'shipment'), 's.shipment_id=refdts.shipment_id', array(''))
						->join(array('resdts' => 'response_result_dts'), 'resdts.sample_id=refdts.sample_id', array('correctRes' => new Zend_Db_Expr("SUM(CASE WHEN (resdts.reported_result=refdts.reference_result AND spm.is_excluded='no') THEN 1 ELSE 0 END)")))
						->join(array('spm' => 'shipment_participant_map'), 'resdts.shipment_map_id=spm.map_id and refdts.shipment_id=spm.shipment_id', array(
							'number_failed' => new Zend_Db_Expr("SUM(CASE WHEN (spm.final_result = 2 AND spm.shipment_test_date <= s.lastdate_response AND spm.is_excluded != 'yes') THEN 1 ELSE 0 END)"),
							'number_passed' => new Zend_Db_Expr("SUM(CASE WHEN (spm.final_result = 1 AND spm.shipment_test_date <= s.lastdate_response AND spm.is_excluded != 'yes') THEN 1 ELSE 0 END)"),
						))
						->where("spm.shipment_id = ?", $shipmentId)
						->where("spm.final_result IS NOT NULL")
						->where("spm.final_result!=''")
						//->where("substring(spm.evaluation_status,4,1) != '0'")
						->group(array("refdts.sample_id"));
					$shipmentResult['summaryResult'][] = $sQueryRes;
					$shipmentResult['summaryResult'][count($shipmentResult['summaryResult']) - 1]['correctCount'] = $db->fetchAll($tQuery);

					$kitNameRes = $db->fetchAll($db->select()->from(array('rtdts' => 'r_testkitname_dts'))
						->where("scheme_type='dts'"));

					$rQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array(''))
						->join(array('resdts' => 'response_result_dts'), 'resdts.shipment_map_id=spm.map_id', array(
							'testkit1Total' => new Zend_Db_Expr('COUNT(DISTINCT(CONCAT(resdts.test_kit_name_1,resdts.shipment_map_id)))')
						))
						->join(array('rtdts' => 'r_testkitname_dts'), 'rtdts.TestKitName_ID=resdts.test_kit_name_1', array('TestKit_Name'))
						->where("spm.final_result IS NOT NULL")
						->where("spm.final_result!=''")
						->where("spm.is_excluded!='yes'")
						->where("spm.shipment_id = ?", $shipmentId)
						->group('resdts.test_kit_name_1')
						->order('testkit1Total DESC');
					$rQueryRes = $db->fetchAll($rQuery);
					$shipmentResult['pieChart'] = $rQueryRes;
				}
				// DTS Participants Perfomance chart
				$sQuery = $db->select()->from(array('s' => 'shipment'), array('shipment_code'))
					->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array(''))
					->join(
						array('sp' => 'shipment_participant_map'),
						'sp.shipment_id=s.shipment_id',
						array(
							"shipmentDate" => new Zend_Db_Expr("DATE_FORMAT(s.shipment_date,'%d-%b-%Y')"),
							"total_shipped" => new Zend_Db_Expr('count("sp.map_id")'),
							"beforeDueDate" => new Zend_Db_Expr("SUM(CASE WHEN (sp.shipment_test_date <= s.lastdate_response AND sp.is_excluded != 'yes') THEN 1 ELSE 0 END)"),
							"afterDueDate" => new Zend_Db_Expr("SUM(CASE WHEN (sp.shipment_test_report_date > s.lastdate_response) THEN 1 ELSE 0 END)"),
							"pass_percentage" => new Zend_Db_Expr("((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100"),
						)
					)->where("s.shipment_id = ?", $shipmentId);
				// die($sQuery);
				$shipmentResult['participantBeforeAfterDueChart'] = $db->fetchRow($sQuery);

				// DTS Aberrant test result chart
				$sQuery = $db->select()->from(array('s' => 'shipment'))->columns(array('shipment_code'))
					->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array(''))
					->joinLeft(
						array('sp' => 'shipment_participant_map'),
						'sp.shipment_id=s.shipment_id',
						array(
							"shipmentDate" => new Zend_Db_Expr("DATE_FORMAT(s.shipment_date,'%d-%b-%Y')"),
							"total_shipped" => new Zend_Db_Expr('count("sp.map_id")'),
							"fail_percentage" => new Zend_Db_Expr("((SUM(sp.final_result = 2))/(SUM(sp.final_result = 2) + SUM(sp.final_result = 1)))*100"),
							"pass_percentage" => new Zend_Db_Expr("((SUM(sp.final_result = 1))/(SUM(sp.final_result = 1) + SUM(sp.final_result = 2)))*100"),
							'number_failed' => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result = 2 AND sp.is_excluded != 'yes') THEN 1 ELSE 0 END)"),
							'number_passed' => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result = 1 AND sp.is_excluded != 'yes') THEN 1 ELSE 0 END)"),
						)
					)
					->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('region'))
					->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id', array(''))
					->where("s.shipment_id = ?", $shipmentId);
				// die($sQuery);
				$shipmentResult['participantAberrantChart'] = $db->fetchRow($sQuery);

				// DTS Aberrant test result failed chart
				$sQuery = $db->select()->from(array('s' => 'shipment'))->columns(array('shipment_code'))
					->joinLeft(
						array('sp' => 'shipment_participant_map'),
						'sp.shipment_id=s.shipment_id',
						array(
							"shipmentDate" => new Zend_Db_Expr("DATE_FORMAT(s.shipment_date,'%d-%b-%Y')"),
							"total_shipped" => new Zend_Db_Expr('count("sp.map_id")'),
							"departmentCount" => new Zend_Db_Expr('count("p.department_name")'),
							"beforeDueDate" => new Zend_Db_Expr("SUM(sp.shipment_test_report_date <= s.lastdate_response)"),
							"afterDueDate" => new Zend_Db_Expr("SUM(sp.shipment_test_report_date > s.lastdate_response)"),
							"fail_percentage" => new Zend_Db_Expr("((SUM(final_result = 2))/(SUM(final_result = 2) + SUM(final_result = 1)))*100"),
						)
					)
					->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('participant_id', 'institute_name', 'region', 'department_name'))
					->where('final_result = 2')
					// ->where("sp.shipment_test_date <= s.lastdate_response")
					->where("s.shipment_id = ?", $shipmentId)
					// ->group(array('p.network_tier'));
					->group(array('p.department_name'));
				// die($sQuery);
				$rResult = $db->fetchAll($sQuery);
				$row = array();
				$row['totalN'] = 0;
				foreach ($rResult as $key => $aRow) {
					$row['department_name'][$key]      = $aRow['department_name'] . ' (N : ' . round($aRow['departmentCount'], 2) . ')';
					$row['totalShipped'][$key]      = $aRow['total_shipped'];
					$row['beforeDueDate'][$key]     = round($aRow['beforeDueDate'], 2);
					$row['afterDueDate'][$key]      = round($aRow['afterDueDate'], 2);
					$row['fail_percentage'][$key]   = round($aRow['fail_percentage'], 2);
					$row['departmentCount'][$key]   = round($aRow['departmentCount'], 2);
					$row['totalN'] += $aRow['departmentCount'];
				}
				$shipmentResult['participantAberrantDepartmentChart'] = $row;

				$sql = $db->select()->from(array('p' => 'participant'))
					->join(array('spm' => 'shipment_participant_map'), 'spm.participant_id=p.participant_id')
					->where("spm.shipment_id = ?", $shipmentId);
				$shipmentResult['participantScores'] = $db->fetchAll($sql);

				$sitesSql = $db->select()->from(array('p' => 'participant'), array('department_name', 'totalSites' => new Zend_Db_Expr('COUNT(department_name)')))
					->join(array('spm' => 'shipment_participant_map'), 'spm.participant_id=p.participant_id', array(''))
					->where("spm.shipment_id = ?", $shipmentId)
					->group('p.department_name')
					->order('totalSites DESC');
				$shipmentResult['siteChart'] = $db->fetchAll($sitesSql);
			} else if ($shipmentResult['scheme_type'] == 'recency') {
				$sql = $db->select()->from(array('refrecency' => 'reference_result_recency'), array('refrecency.reference_result', 'refrecency.sample_label', 'refrecency.mandatory'))
					->join(array('refpr' => 'r_possibleresult'), 'refpr.id=refrecency.reference_result', array('referenceResult' => 'refpr.response'))
					->where("refrecency.shipment_id = ?", $shipmentResult['shipment_id']);
				$sqlRes = $db->fetchAll($sql);

				$shipmentResult['referenceResult'] = $sqlRes;

				$sQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.attributes', 'spm.is_excluded'))
					->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status'))
					->joinLeft(array('res' => 'r_results'), 'res.result_id=spm.final_result', array('result_name'))
					->where("spm.shipment_id = ?", $shipmentId)
					->where("spm.final_result IS NOT NULL")
					->where("spm.final_result!=''")
					// ->where("spm.final_result = ?",'2')
					//->where("substring(spm.evaluation_status,4,1) != '0'")
					->group('spm.map_id');
				$sQueryRes = $db->fetchAll($sQuery);
				//error_log($sQuery);
				if (count($sQueryRes) > 0) {

					$tQuery = $db->select()->from(array('refrecency' => 'reference_result_recency'), array('refrecency.sample_id', 'refrecency.sample_label'))
						->join(array('resrecency' => 'response_result_recency'), 'resrecency.sample_id=refrecency.sample_id', array('correctRes' => new Zend_Db_Expr("SUM(CASE WHEN (resrecency.reported_result=refrecency.reference_result AND spm.is_excluded='no') THEN 1 ELSE 0 END)")))
						->join(array('spm' => 'shipment_participant_map'), 'resrecency.shipment_map_id=spm.map_id and refrecency.shipment_id=spm.shipment_id', array())
						->where("spm.shipment_id = ?", $shipmentId)
						->where("spm.final_result IS NOT NULL")
						->where("spm.final_result!=''")
						//->where("substring(spm.evaluation_status,4,1) != '0'")
						->group(array("refrecency.sample_id"));
					$shipmentResult['summaryResult'][] = $sQueryRes;
					$shipmentResult['summaryResult'][count($shipmentResult['summaryResult']) - 1]['correctCount'] = $db->fetchAll($tQuery);

					$rQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id'))
						->join(array('resrecency' => 'response_result_recency'), 'resrecency.shipment_map_id=spm.map_id', array('resrecency.control_line', 'resrecency.diagnosis_line', 'resrecency.diagnosis_line'))
						->where("spm.final_result IS NOT NULL")
						->where("spm.final_result!=''")
						//->where("substring(spm.evaluation_status,4,1) != '0'")
						->where("spm.shipment_id = ?", $shipmentId)
						->group('spm.map_id');
					$rQueryRes = $db->fetchAll($rQuery);
				}

				$sql = $db->select()->from(array('p' => 'participant'))
					->join(array('spm' => 'shipment_participant_map'), 'spm.participant_id=p.participant_id')
					->where("spm.shipment_id = ?", $shipmentId);


				$shipmentResult['participantScores'] = $db->fetchAll($sql);
			} else if ($shipmentResult['scheme_type'] == 'eid') {
				$schemeService = new Application_Service_Schemes();
				$extractionAssay = $schemeService->getEidExtractionAssay();
				$detectionAssay = $schemeService->getEidDetectionAssay();
				$pQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id', 'spm.documentation_score', 'participant_count' => new Zend_Db_Expr('count("participant_id")'), 'reported_count' => new Zend_Db_Expr("SUM(shipment_test_date not like  '0000-00-00' OR is_pt_test_not_performed !='yes')")))
					->joinLeft(array('res' => 'r_results'), 'res.result_id=spm.final_result', array('result_name'))
					->where("spm.shipment_id = ?", $shipmentId)
					->group('spm.shipment_id');
				$totParticipantsRes = $db->fetchRow($pQuery);
				if ($totParticipantsRes != "") {
					$shipmentResult['participant_count'] = $totParticipantsRes['participant_count'];
					//Zend_Debug::dump($shipmentResult);die;
				}

				$sQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.attributes'))
					//->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status'))
					->joinLeft(array('res' => 'r_results'), 'res.result_id=spm.final_result', array('result_name'))
					->where("spm.shipment_id = ?", $shipmentId)
					//->where("spm.shipment_test_date IS NOT NULL AND spm.shipment_test_date not like '' AND spm.shipment_test_date not like '0000-00-00' OR spm.is_pt_test_not_performed ='yes'")
					->where("spm.shipment_test_date IS NOT NULL AND spm.shipment_test_date not like '' AND spm.shipment_test_date not like '0000-00-00'")
					//->where("spm.is_pt_test_not_performed not like 'yes'")
					->group('spm.map_id');

				$sQueryRes = $db->fetchAll($sQuery);

				//echo($sQuery);die;

				if (count($sQueryRes) > 0) {
					$shipmentResult['summaryResult'][] = $sQueryRes;
				}

				$cQuery = $db->select()->from(array('refeid' => 'reference_result_eid'), array('refeid.sample_id', 'refeid.sample_label', 'refeid.reference_result', 'refeid.mandatory'))
					->join(array('s' => 'shipment'), 's.shipment_id=refeid.shipment_id', array('s.shipment_id'))
					->join(array('spm' => 'shipment_participant_map'), 's.shipment_id=spm.shipment_id', array('spm.map_id', 'spm.attributes', 'spm.shipment_score'))
					->joinLeft(array('reseid' => 'response_result_eid'), 'reseid.shipment_map_id = spm.map_id and reseid.sample_id = refeid.sample_id', array('reported_result'))
					->where('spm.shipment_id = ? ', $shipmentId)
					->where("spm.shipment_test_date IS NOT NULL AND spm.shipment_test_date not like '' AND spm.shipment_test_date not like '0000-00-00' OR spm.is_pt_test_not_performed ='yes'")
					->where("spm.is_excluded!='yes'")
					->where("refeid.control = 0");

				$cResult = $db->fetchAll($cQuery);
				$correctResult = array();
				foreach ($cResult as $cVal) {
					//Formed correct result
					if (array_key_exists($cVal['sample_label'], $correctResult)) {
						if ($cVal['reported_result'] == $cVal['reference_result']) {
							$correctResult[$cVal['sample_label']] += 1;
						}
					} else {
						$correctResult[$cVal['sample_label']] = array();
						if ($cVal['reported_result'] == $cVal['reference_result']) {
							$correctResult[$cVal['sample_label']] = 1;
						} else {
							$correctResult[$cVal['sample_label']] = 0;
						}
					}
				}


				$shipmentResult['correctRes'] = $correctResult;

				$extAssayResult = array();
				$correctRes = 0;
				$maxScore = 0;
				$belowScore = 0;
				//foreach ($extractionAssay as $eKey=>$extractionAssayVal) {
				//	
				//	$extAssayResult[$eKey]=array();
				//	$extAssayResult[$eKey]['vlAssay']=$extractionAssayVal;
				//	$extAssayResult[$eKey]['participantCount']=1;
				//	if ($shipmentResult['max_score'] == $sVal['shipment_score']) {
				//		$extAssayResult[$eKey]['maxScore']=1;
				//	}else{
				//		$extAssayResult[$eKey]['belowScore']=1;
				//	}
				//	
				//}

				foreach ($sQueryRes as $sVal) {
					$valAttributes = json_decode($sVal['attributes'], true);
					//Zend_Debug::dump($extractionAssay);die;

					$cQuery = $db->select()->from(array('refeid' => 'reference_result_eid'), array('refeid.sample_id', 'refeid.sample_label', 'refeid.reference_result', 'refeid.mandatory'))
						->joinLeft(array('reseid' => 'response_result_eid'), 'reseid.sample_id = refeid.sample_id', array('reported_result'))
						->where('refeid.shipment_id = ? ', $shipmentId)
						->where("refeid.control = 0")
						->where('reseid.shipment_map_id = ? ', $sVal['map_id']);

					$cResult = $db->fetchAll($cQuery);

					foreach ($extractionAssay as $eKey => $extractionAssayVal) {
						if ($eKey == $valAttributes['extraction_assay']) {
							if (array_key_exists($eKey, $extAssayResult)) {
								$extAssayResult[$eKey]['participantCount'] = (isset($extAssayResult[$eKey]['participantCount']) ? $extAssayResult[$eKey]['participantCount'] + 1 : 1);
								if ($shipmentResult['max_score'] == $sVal['shipment_score']) {
									$extAssayResult[$eKey]['maxScore'] = (isset($extAssayResult[$eKey]['maxScore']) ? $extAssayResult[$eKey]['maxScore'] + 1 : 1);
								} else {
									$extAssayResult[$eKey]['belowScore'] = (isset($extAssayResult[$eKey]['belowScore']) ? $extAssayResult[$eKey]['belowScore'] + 1 : 1);
								}
							} else {
								$extAssayResult[$eKey] = array();
								$extAssayResult[$eKey]['vlAssay'] = $extractionAssayVal;
								$extAssayResult[$eKey]['participantCount'] = 1;
								if ($shipmentResult['max_score'] == $sVal['shipment_score']) {
									$extAssayResult[$eKey]['maxScore'] = 1;
								} else {
									$extAssayResult[$eKey]['belowScore'] = 1;
								}
							}

							foreach ($cResult as $val) {
								if ($val['reported_result'] == $val['reference_result']) {
									$extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes'] = (isset($extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes']) ? $extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes'] + 1 : 1);
								} else {
									$extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes'] = (isset($extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes']) ? $extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes'] : 0);
								}
							}
						}
					}
				}

				ksort($extAssayResult);

				// clubbing all the results with less than or equal to 5 responses with Others
				$eresult = array();
				foreach ($extAssayResult as $exid => $edata) {
					if ($exid == 8) continue;
					if ($edata['participantCount'] <= 5) {
						$extAssayResult[8]['vlAssay'] .= ", " . $edata['vlAssay'];
						$extAssayResult[8]['participantCount'] += $edata['participantCount'];
						$extAssayResult[8]['maxScore'] += $edata['maxScore'];
						//$extAssayResult[8]['belowScore'] += isset($edata['belowScore']) ? $edata['belowScore'] : 0;
						$extAssayResult[8]['belowScore']  += isset($edata['belowScore']) ? $edata['belowScore'] : 0;


						foreach ($cResult as $val) {
							$extAssayResult[8]['specimen'][$val['sample_label']]['correctRes'] += $edata['specimen'][$val['sample_label']]['correctRes'];
						}


						unset($extAssayResult[$exid]);
					}
				}
				//Zend_Debug::dump($extAssayResult);die;
				$shipmentResult['avgAssayResult'] = $extAssayResult;
				//Zend_Debug::dump($extAssayResult);die;
			} else if ($shipmentResult['scheme_type'] == 'vl') {

				$sQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.attributes', 'spm.is_excluded'))
					->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status'))
					->joinLeft(array('res' => 'r_results'), 'res.result_id=spm.final_result', array('result_name'))
					->where("spm.shipment_id = ?", $shipmentId)
					->where("spm.shipment_test_date IS NOT NULL AND spm.shipment_test_date not like '' AND spm.shipment_test_date not like '0000-00-00' OR spm.is_pt_test_not_performed ='yes'")
					->group('spm.map_id');

				$sQueryRes = $db->fetchAll($sQuery);
				if (count($sQueryRes) > 0) {
					$shipmentResult['summaryResult'][] = $sQueryRes;
				}

				$query = $db->select()->from(array('refvl' => 'reference_result_vl'), array('refvl.sample_score'))

					->where('refvl.control!=1')
					->where('refvl.shipment_id = ? ', $shipmentId);
				$smpleResult = $db->fetchAll($query);
				$shipmentResult['no_of_samples'] = count($smpleResult);



				//print_r($shipmentResult);die;
				$refVlQuery = $db->select()->from(array('ref' => 'reference_vl_calculation'), array('ref.vl_assay'))
					->where('ref.shipment_id = ? ', $shipmentId)
					->group('vl_assay');

				$countedAssayResult =  $db->fetchAll($refVlQuery);

				$regexpArray = array();
				$regexp = '';
				foreach ($countedAssayResult as $crow) {
					$regexpArray[] = '\'%"vl_assay":"' . $crow['vl_assay'] . '"%\'';
				}
				// select * from shipment_participant_map where `attributes` NOT REGEXP '\"vl_assay\":\"1\" |\"vl_assay\":\"4\" |\"vl_assay\":\"2\"' and shipment_id = 11
				if (count($regexpArray) > 0) {
					$regexp = implode(' AND `attributes` NOT LIKE ', $regexpArray);
				} else {
					$regexp = '""';
				}

				$vlQuery = $db->select()->from(array('spm' => 'shipment_participant_map'))
					->where("`attributes` NOT LIKE  $regexp ")
					->where("is_excluded!='yes'")
					->where("is_pt_test_not_performed is NULL")
					->where("spm.shipment_id = ?", $shipmentId);

				// echo($vlQuery);die;
				$pendingResult =  $db->fetchAll($vlQuery);


				$schemeService = new Application_Service_Schemes();
				$vlAssayList = $schemeService->getVlAssay();
				$penResult = array();
				foreach ($pendingResult as $pendingRow) {
					$valAttributes = json_decode($pendingRow['attributes'], true);
					if (isset($vlAssayList[$valAttributes['vl_assay']])) {
						if ($valAttributes['vl_assay'] == 6) {
							$penResult['assayNames'][] = $valAttributes['other_assay'];
						} else {
							$penResult['assayNames'][] = $vlAssayList[$valAttributes['vl_assay']];
						}
					}
				}
				if (isset($penResult['assayNames']) && count($penResult['assayNames']) > 0) {
					$penResult['assayNames'] = array_unique($penResult['assayNames']);
					sort($penResult['assayNames']);
				}
				$penResult['count'] = count($pendingResult);


				$vlCalculation = array();
				$vlAssayResultSet = $db->fetchAll($db->select()->from('r_vl_assay')->where("`status` like 'active'"));
				$otherAssayCounter = array();
				/* VL Assay for chart */
				$vlAssayQuery = $db->select()->from(array('vlCal' => 'reference_vl_calculation'), array('no_of_responses'))
					->join(array('refVl' => 'reference_result_vl'), 'refVl.shipment_id=vlCal.shipment_id and vlCal.sample_id=refVl.sample_id', array('no_of_samples' => new Zend_Db_Expr("COUNT(DISTINCT refVl.sample_id)")))
					->join(array('rvla' => 'r_vl_assay'), 'rvla.id=vlCal.vl_assay', array('assay_name' => 'name'))
					->join(array('sp' => 'shipment_participant_map'), 'vlCal.shipment_id=sp.shipment_id', array(
						'numberPassed' => new Zend_Db_Expr("SUM(CASE WHEN final_result = 1 THEN 1 ELSE 0 END)/COUNT(DISTINCT refVl.sample_id)"),
						'numberFailed' => new Zend_Db_Expr("SUM(CASE WHEN final_result != 1 THEN 1 ELSE 0 END)/COUNT(DISTINCT refVl.sample_id)"),
					))
					->where("vlCal.shipment_id=?", $shipmentId)
					->where("refVl.control!=1")
					->where("(sp.attributes like CONCAT('%\"vl_assay\":\"', vlCal.vl_assay, '\"%') )")
					->where("sp.is_excluded not like 'yes'")
					->group('rvla.name')
					->order('vlCal.no_of_responses DESC');
				$vlAssayRes = $db->fetchAll($vlAssayQuery);
				// Zend_Debug::dump($vlAssayRes);die;

				foreach ($vlAssayResultSet as $vlAssayRow) {
					$vlQuery = $db->select()->from(array('vlCal' => 'reference_vl_calculation'), array('mean', 'no_of_responses', 'median', 'low_limit', 'high_limit', 'sd', 'cv'))
						->join(array('refVl' => 'reference_result_vl'), 'refVl.shipment_id=vlCal.shipment_id and vlCal.sample_id=refVl.sample_id', array('refVl.sample_label', 'refVl.mandatory'))
						->join(array('sp' => 'shipment_participant_map'), 'vlCal.shipment_id=sp.shipment_id', array())
						->join(array('res' => 'response_result_vl'), 'res.shipment_map_id = sp.map_id and res.sample_id = refVl.sample_id', array(
							'NumberPassed' => new Zend_Db_Expr("SUM(CASE WHEN calculated_score = 'pass' OR calculated_score = 'warn' THEN 1 ELSE 0 END)"), 'z_score', 'calculated_score'
						))
						->where("vlCal.shipment_id=?", $shipmentId)
						->where("vlCal.vl_assay=?", $vlAssayRow['id'])
						->where("refVl.control!=1")
						->where('sp.attributes like ? ', '%"vl_assay":"' . $vlAssayRow['id'] . '"%')
						->where("sp.is_excluded not like 'yes'")
						->group('refVl.sample_id');
					// die($vlQuery);
					$vlCalRes = $db->fetchAll($vlQuery);

					if ($vlAssayRow['id'] == 6) {
						$cQuery = $db->select()->from(array('sp' => 'shipment_participant_map'), array('sp.map_id', 'sp.attributes'))
							->where("sp.is_excluded not like 'yes'")
							->where('sp.attributes like ? ', '%"vl_assay":"6"%')
							->where('sp.shipment_id = ? ', $shipmentId);
						$cResult = $db->fetchAll($cQuery);


						foreach ($cResult as $val) {
							$valAttributes = json_decode($val['attributes'], true);
							if (isset($valAttributes['other_assay'])) {
								if (!empty($otherAssayCounter[$valAttributes['other_assay']])) {
									$otherAssayCounter[$valAttributes['other_assay']]++;
								} else {
									$otherAssayCounter[$valAttributes['other_assay']] = 1;
								}
							}
						}
						//var_dump($otherAssayCounter);
						// Zend_Debug::dump($vlAssayRow['id']);die;
					}
					if (count($vlCalRes) > 0) {
						$vlCalculation[$vlAssayRow['id']] = $vlCalRes;
						$vlCalculation[$vlAssayRow['id']]['vlAssay'] = $vlAssayRow['name'];
						$vlCalculation[$vlAssayRow['id']]['shortName'] = $vlAssayRow['short_name'];
						$vlCalculation[$vlAssayRow['id']]['participant-count'] = $vlCalRes[0]['no_of_responses'];
						// $labResult[$vlCalRes[0]['no_of_responses']];
						if ($vlAssayRow['id'] == 6) {
							$vlCalculation[$vlAssayRow['id']]['otherAssayName'] = $otherAssayCounter;
						}
					}
				}

				array_multisort(array_column($vlCalculation, 'participant-count'), SORT_DESC, $vlCalculation);
			} else if ($shipmentResult['scheme_type'] == 'covid19') {
				$sql = $db->select()->from(array('refcovid19' => 'reference_result_covid19'), array('refcovid19.reference_result', 'refcovid19.sample_label', 'refcovid19.mandatory'))
					->join(array('refpr' => 'r_possibleresult'), 'refpr.id=refcovid19.reference_result', array('referenceResult' => 'refpr.response'))
					->where("refcovid19.shipment_id = ?", $shipmentResult['shipment_id']);
				$sqlRes = $db->fetchAll($sql);

				$shipmentResult['referenceResult'] = $sqlRes;

				$sQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.attributes', 'spm.is_excluded'))
					->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status'))
					->joinLeft(array('res' => 'r_results'), 'res.result_id=spm.final_result', array('result_name'))
					->where("spm.shipment_id = ?", $shipmentId)
					->where("spm.final_result IS NOT NULL")
					->where("spm.final_result!=''")
					// ->where("spm.final_result = ?",'2')
					//->where("substring(spm.evaluation_status,4,1) != '0'")
					->group('spm.map_id');
				$sQueryRes = $db->fetchAll($sQuery);
				//error_log($sQuery);
				if (count($sQueryRes) > 0) {

					$tQuery = $db->select()->from(array('refcovid19' => 'reference_result_covid19'), array('refcovid19.sample_id', 'refcovid19.sample_label'))
						->join(array('rescovid19' => 'response_result_covid19'), 'rescovid19.sample_id=refcovid19.sample_id', array('correctRes' => new Zend_Db_Expr("SUM(CASE WHEN (rescovid19.reported_result=refcovid19.reference_result AND spm.is_excluded='no') THEN 1 ELSE 0 END)")))
						->join(array('spm' => 'shipment_participant_map'), 'rescovid19.shipment_map_id=spm.map_id and refcovid19.shipment_id=spm.shipment_id', array())
						->where("spm.shipment_id = ?", $shipmentId)
						->where("spm.final_result IS NOT NULL")
						->where("spm.final_result!=''")
						//->where("substring(spm.evaluation_status,4,1) != '0'")
						->group(array("refcovid19.sample_id"));

					$shipmentResult['summaryResult'][] = $sQueryRes;
					$shipmentResult['summaryResult'][count($shipmentResult['summaryResult']) - 1]['correctCount'] = $db->fetchAll($tQuery);

					$typeNameRes = $db->fetchAll($db->select()->from('r_test_type_covid19')->where("scheme_type='covid19'"));

					/* $rQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id'))
						->join(array('rescovid19' => 'response_result_covid19'), 'rescovid19.shipment_map_id=spm.map_id', array('rescovid19.test_type_1', 'rescovid19.test_type_2', 'rescovid19.test_type_3'))
						->where("spm.final_result IS NOT NULL")
						->where("spm.final_result!=''")
						//->where("substring(spm.evaluation_status,4,1) != '0'")
						->where("spm.shipment_id = ?", $shipmentId)
						->group('spm.map_id');
					$rQueryRes = $db->fetchAll($rQuery);
					$p = 0;
					$typeName = array();
					foreach ($typeNameRes as $res) {
						$k = 1;
						foreach ($rQueryRes as $rVal) {
							if ($res['test_type_id'] == $rVal['test_type_1']) {
								$typeName[$p]['type_name'] = $res['test_type_name'];
								$typeName[$p]['count'] = $k++;
							}
							if ($res['test_type_id'] == $rVal['test_type_2']) {
								$typeName[$p]['type_name'] = $res['test_type_name'];
								$typeName[$p]['count'] = $k++;
							}
							if ($res['test_type_id'] == $rVal['test_type_3']) {
								$typeName[$p]['type_name'] = $res['test_type_name'];
								$typeName[$p]['count'] = $k++;
							}
						}

						$p++;
					} */
					$rQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array(''))
						->join(array('resC19' => 'response_result_covid19'), 'resC19.shipment_map_id=spm.map_id', array(
							'testPlatform1Total' => new Zend_Db_Expr('COUNT(DISTINCT(CONCAT(resC19.test_type_1,resC19.shipment_map_id)))')
						))
						->join(array('testPlatformC19' => 'r_test_type_covid19'), 'testPlatformC19.test_type_id=resC19.test_type_1', array('test_type_name'))
						->where("spm.final_result IS NOT NULL")
						->where("spm.final_result!=''")
						->where("spm.is_excluded!='yes'")
						->where("spm.shipment_id = ?", $shipmentId)
						->group('testPlatformC19.test_type_name')
						->order('testPlatform1Total DESC');
					// die($rQuery);
					$rQueryRes = $db->fetchAll($rQuery);
					$shipmentResult['pieChart'] = $rQueryRes;
					// $shipmentResult['pieChart'] = $typeName;
				}

				$sql = $db->select()->from(array('p' => 'participant'))
					->join(array('spm' => 'shipment_participant_map'), 'spm.participant_id=p.participant_id')
					->where("spm.shipment_id = ?", $shipmentId);


				$shipmentResult['participantScores'] = $db->fetchAll($sql);
			}

			$i++;
		}
		$result = array('shipment' => $shipmentResult, 'vlCalculation' => $vlCalculation, 'vlAssayRes' => $vlAssayRes, 'pendingAssay' => $penResult);

		return $result;
		//Zend_Debug::dump($shipmentResult);die;
		//return $shipmentResult;
	}

	public function getResponseReports($shipmentId)
	{
		$dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

		$sQuery = $dbAdapter->select()->from(array('p' => 'participant'), array())
			->join(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array(
				"total_shipped" => new Zend_Db_Expr('count("sp.map_id")'),
				'not_responded' => new Zend_Db_Expr("SUM(CASE WHEN ((sp.shipment_test_date like '0000-00-00' OR sp.shipment_test_date IS NULL) AND sp.is_excluded like 'yes%') THEN 1 ELSE 0 END)"),
				'excluded' => new Zend_Db_Expr("SUM(CASE WHEN ((sp.shipment_test_date != '0000-00-00' OR sp.shipment_test_date IS NOT NULL) AND sp.is_excluded like 'yes%') THEN 1 ELSE 0 END)"),
				'number_failed' => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result = 2 AND sp.is_excluded != 'yes') THEN 1 ELSE 0 END)"),
				'number_passed' => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result = 1 AND sp.is_excluded != 'yes') THEN 1 ELSE 0 END)"),
				'number_late' => new Zend_Db_Expr(
					"SUM(CASE WHEN (sp.shipment_test_report_date > s.lastdate_response) THEN 1 ELSE 0 END)"
				)
			))
			->join(array('s' => 'shipment'), 's.shipment_id=sp.shipment_id', array('shipment_code'))
			->where("sp.shipment_id = ?", $shipmentId);
		// die($sQuery);
		return $dbAdapter->fetchRow($sQuery);
	}



	public function addShipmentEvaluationToQueue($shipmentId)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$data = array(
			'shipment_id' => $shipmentId,
			'requested_on' => new Zend_Db_Expr('now()'),
			'last_updated_on' => new Zend_Db_Expr('now()'),
			'status'	=>	'pending'
		);
		$db->insert('evaluation_queue', $data);
	}

	public function saveBulkGenerateReports($params)
	{
		$shipmentId = base64_decode($params['sid']);
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$existData = $db->fetchRow($db->select()->from('evaluation_queue')->where("shipment_id = ?", $shipmentId)->where("report_type = ?", $params['type']));
		if (!$existData) {
			$authNameSpace 	= new Zend_Session_Namespace('administrators');
			$sql = $db->select()->from(array('s' => 'shipment', array('shipment_id', 'shipment_code', 'status', 'number_of_samples')))
				->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('distribution_code', 'distribution_date'))
				->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id')
				->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
				->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('first_name', 'last_name', 'lab_name', 'unique_identifier', 'country'))
				->join(array('c' => 'countries'), 'p.country=c.id', array('country_name' => 'iso_name'))
				->joinLeft(array('res' => 'r_results'), 'res.result_id=sp.final_result')
				->where("s.shipment_id = ?", $shipmentId);
			$shipmentResult = $db->fetchAll($sql);
			if (isset($shipmentResult) && count($shipmentResult) > 0) {
				$data = array(
					'shipment_id' 		=> $shipmentId,
					'report_type' 		=> $params['type'],
					'requested_by' 		=> $authNameSpace->admin_id,
					'requested_on' 		=> new Zend_Db_Expr('now()'),
					'status'			=> 'pending'
				);
				$saved = $db->insert('evaluation_queue', $data);
				if ($saved > 0) {
					return $db->update('shipment', array('report_in_queue' => 'yes'), "shipment_id = " . $shipmentId);
				}
			}
		} else {
			$data = array(
				'shipment_id' 		=> $shipmentId,
				'report_type' 		=> $params['type'],
				'last_updated_on'	=> new Zend_Db_Expr('now()'),
				'status'			=> 'pending'
			);
			// Zend_Debug::dump($data);die;
			return $db->update('evaluation_queue', $data, "id = " . $existData['id']);
		}
	}
}
