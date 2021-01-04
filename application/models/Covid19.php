<?php
class Application_Model_Covid19
{

	public function __construct()
	{
	}

	public function evaluate($shipmentResult, $shipmentId)
	{

		$counter = 0;
		$maxScore = 0;
		$scoreHolder = array();
		$schemeService = new Application_Service_Schemes();
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();

		$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
		$config = new Zend_Config_Ini($file, APPLICATION_ENV);
		$correctiveActions = $schemeService->getCovid19CorrectiveActions();
		$recommendedTesttypes = $schemeService->getRecommededCovid19TestTypes();

		foreach ($shipmentResult as $shipment) {
			//Zend_Debug::dump($shipment);

			//$shipment['is_excluded'] = 'no'; // setting it as no by default. It will become 'yes' if some condition matches.

			$createdOnUser = explode(" ", $shipment['created_on_user']);
			if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {
				$createdOn = new Zend_Date($createdOnUser[0], Zend_Date::ISO_8601);
			} else {
				$datearray = array('year' => 1970, 'month' => 1, 'day' => 01);
				$createdOn = new Zend_Date($datearray);
			}

			$results = $schemeService->getCovid19Samples($shipmentId, $shipment['participant_id']);

			$totalScore = 0;
			$maxScore = 0;
			$mandatoryResult = "";
			$lotResult = "";
			$testType1 = "";
			$testType2 = "";
			$testType3 = "";
			$testTypeRepeatResult = "";
			$testTypeExpiryResult = "";
			$lotResult = "";
			$scoreResult = "";
			$failureReason = array();
			$correctiveActionList = array();
			$algoResult = "";
			$lastDateResult = "";
			$controlTesTypeFail = "";

			$attributes = json_decode($shipment['attributes'], true);
			$shipmentAttributes = json_decode($shipment['shipment_attributes'], true);


			//Response was submitted after the last response date.
			$lastDate = new Zend_Date($shipment['lastdate_response'], Zend_Date::ISO_8601);
			if ($createdOn->compare($lastDate, Zend_date::DATES) > 0) {
				//$lastDateResult = 'Fail';
				$failureReason[] = array(
					'warning' => "Response was submitted after the last response date.",
					'correctiveAction' => $correctiveActions[1]
				);
				$correctiveActionList[] = 1;
			}


			//$serialCorrectResponses = array('NXX','PNN','PPX','PNP');				
			//$parallelCorrectResponses = array('PPX','PNP','PNN','NNX','NPN','NPP');

			// 3 tests algo added for Myanmar initally, might be used in other places eventually
			//$threeTestCorrectResponses = array('NXX','PPP');  

			$testedOn = new Zend_Date($results[0]['shipment_test_date'], Zend_Date::ISO_8601);

			// Getting the Test Date string to show in Corrective Actions and other sentences
			$testDate = $testedOn->toString('dd-MMM-YYYY');

			// Getting test type expiry dates as reported
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

			// Getting Test Type Names

			$testTypeDb = new Application_Model_DbTable_TestTypenameCovid19();
			$testType1 = "";

			$testTypeName = $testTypeDb->getTestTypeNameById($results[0]['test_type_1']);
			if (isset($testTypeName[0])) {
				$testType1 = $testTypeName[0];
			}

			$testType2 = "";
			if (trim($results[0]['test_type_2']) != "") {
				$testTypeName = $testTypeDb->getTestTypeNameById($results[0]['test_type_2']);
				if (isset($testTypeName[0])) {
					$testType2 = $testTypeName[0];
				}
			}
			$testType3 = "";
			if (trim($results[0]['test_type_3']) != "") {
				$testTypeName = $testTypeDb->getTestTypeNameById($results[0]['test_type_3']);
				if (isset($testTypeName[0])) {
					$testType3 = $testTypeName[0];
				}
			}


			// T.7 Checking for Expired Test Types

			if ($testType1 != "") {
				if ($expDate1 != "") {
					if ($testedOn->isLater($expDate1)) {
						$difference = $testedOn->sub($expDate1);

						$measure = new Zend_Measure_Time($difference->toValue(), Zend_Measure_Time::SECOND);
						$measure->convertTo(Zend_Measure_Time::DAY);
						$failureReason[] = array(
							'warning' => "Test Type 1 (<strong>" . $testType1 . "</strong>) expired " . round($measure->getValue()) . " days before the test date " . $testDate,
							'correctiveAction' => $correctiveActions[5]
						);
						$correctiveActionList[] = 5;
						$tt1Expired = true;
					} else {
						$tt1Expired = false;
					}
				} else {
					$failureReason[] = array(
						'warning' => "Result not evaluated – Test type 1 expiry date is not reported with PT response.",
						'correctiveAction' => $correctiveActions[6]
					);
					$correctiveActionList[] = 6;
					$shipment['is_excluded'] = 'yes';
				}
				if (isset($recommendedTesttypes[1]) && count($recommendedTesttypes[1]) > 0) {
					if (!in_array($results[0]['test_type_1'], $recommendedTesttypes[1])) {
						$tt1RecommendedUsed = false;
						$failureReason[] = array(
							'warning' => "For Test 1, testing is not performed with country approved test type.",
							'correctiveAction' => $correctiveActions[17]
						);
					} else {
						$tt1RecommendedUsed = true;
					}
				}
			}

			if ($testType2 != "") {
				if ($expDate2 != "") {
					if ($testedOn->isLater($expDate2)) {
						$difference = $testedOn->sub($expDate2);

						$measure = new Zend_Measure_Time($difference->toValue(), Zend_Measure_Time::SECOND);
						$measure->convertTo(Zend_Measure_Time::DAY);
						$failureReason[] = array(
							'warning' => "Test Type 2 (<strong>" . $testType2 . "</strong>) expired " . round($measure->getValue()) . " days before the test date " . $testDate,
							'correctiveAction' => $correctiveActions[5]
						);
						$correctiveActionList[] = 5;
						$tt2Expired = true;
					} else {
						$tt2Expired = false;
					}
				} else {
					$failureReason[] = array(
						'warning' => "Result not evaluated – Test type 2 expiry date is not reported with PT response.",
						'correctiveAction' => $correctiveActions[6]
					);
					$correctiveActionList[] = 6;
					$shipment['is_excluded'] = 'yes';
				}

				if (isset($recommendedTesttypes[2]) && count($recommendedTesttypes[2]) > 0) {
					if (!in_array($results[0]['test_type_2'], $recommendedTesttypes[2])) {
						$tt2RecommendedUsed = false;
						$failureReason[] = array(
							'warning' => "For Test 2, testing is not performed with country approved test type.",
							'correctiveAction' => $correctiveActions[17]
						);
					} else {
						$tt2RecommendedUsed = true;
					}
				}
			}


			if ($testType3 != "") {
				if ($expDate3 != "") {
					if ($testedOn->isLater($expDate3)) {
						$difference = $testedOn->sub($expDate3);

						$measure = new Zend_Measure_Time($difference->toValue(), Zend_Measure_Time::SECOND);
						$measure->convertTo(Zend_Measure_Time::DAY);
						$failureReason[] = array(
							'warning' => "Test Type 3 (<strong>" . $testType3 . "</strong>) expired " . round($measure->getValue()) . " days before the test date " . $testDate,
							'correctiveAction' => $correctiveActions[5]
						);
						$correctiveActionList[] = 5;
						$tt3Expired = true;
					} else {
						$tt3Expired = false;
					}
				} else {

					$failureReason[] = array(
						'warning' => "Result not evaluated – Test type 3 expiry date is not reported with PT response.",
						'correctiveAction' => $correctiveActions[6]
					);
					$correctiveActionList[] = 6;
					$shipment['is_excluded'] = 'yes';
				}

				if (isset($recommendedTesttypes[3]) && count($recommendedTesttypes[3]) > 0) {
					if (!in_array($results[0]['test_type_3'], $recommendedTesttypes[3])) {
						$tt3RecommendedUsed = false;
						$failureReason[] = array(
							'warning' => "For Test 3, testing is not performed with country approved test type.",
							'correctiveAction' => $correctiveActions[17]
						);
					} else {
						$tt3RecommendedUsed = true;
					}
				}
			}
			//checking if testtypes were repeated
			// T.9 Test type repeated for confirmatory or tiebreaker test (T1/T2/T3).
			if (($testType1 == "") && ($testType2 == "") && ($testType3 == "")) {
				$failureReason[] = array(
					'warning' => "No Test Type reported. Result not evaluated",
					'correctiveAction' => $correctiveActions[7]
				);
				$correctiveActionList[] = 7;
				$shipment['is_excluded'] = 'yes';
			} else if (($testType1 != "") && ($testType2 != "") && ($testType3 != "") && ($testType1 == $testType2) && ($testType2 == $testType3)) {
				//$testTypeRepeatResult = 'Fail';
				$failureReason[] = array(
					'warning' => "<strong>$testType1</strong> repeated for all three Test Types",
					'correctiveAction' => $correctiveActions[8]
				);
				$correctiveActionList[] = 8;
			} else {
				if (($testType1 != "") && ($testType2 != "") && ($testType1 == $testType2) && $testType1 != "" && $testType2 != "") {
					//$testTypeRepeatResult = 'Fail';
					$failureReason[] = array(
						'warning' => "<strong>$testType1</strong> repeated as Test Type 1 and Test Type 2",
						'correctiveAction' => $correctiveActions[9]
					);
					$correctiveActionList[] = 9;
				}
				if (($testType2 != "") && ($testType3 != "") && ($testType2 == $testType3) && $testType2 != "" && $testType3 != "") {
					//$testTypeRepeatResult = 'Fail';
					$failureReason[] = array(
						'warning' => "<strong>$testType2</strong> repeated as Test Type 2 and Test Type 3",
						'correctiveAction' => $correctiveActions[9]
					);
					$correctiveActionList[] = 9;
				}
				if (($testType1 != "") && ($testType3 != "") && ($testType1 == $testType3) && $testType1 != "" && $testType3 != "") {
					//$testTypeRepeatResult = 'Fail';
					$failureReason[] = array(
						'warning' => "<strong>$testType1</strong> repeated as Test Type 1 and Test Type 3",
						'correctiveAction' => $correctiveActions[9]
					);
					$correctiveActionList[] = 9;
				}
			}


			// checking if all LOT details were entered
			// T.3 Ensure test type lot number is reported for all performed tests. 
			if ($testType1 != "" && (!isset($results[0]['lot_no_1']) || $results[0]['lot_no_1'] == "" || $results[0]['lot_no_1'] == null)) {
				if (isset($results[0]['test_result_1']) && $results[0]['test_result_1'] != "" && $results[0]['test_result_1'] != null) {
					$lotResult = 'Fail';
					$failureReason[] = array(
						'warning' => "Result not evaluated – Test Type lot number 1 is not reported.",
						'correctiveAction' => $correctiveActions[10]
					);
					$correctiveActionList[] = 10;
					$shipment['is_excluded'] = 'yes';
				}
			}
			if ($testType2 != "" && (!isset($results[0]['lot_no_2']) || $results[0]['lot_no_2'] == "" || $results[0]['lot_no_2'] == null)) {
				if (isset($results[0]['test_result_2']) && $results[0]['test_result_2'] != "" && $results[0]['test_result_2'] != null) {
					$lotResult = 'Fail';
					$failureReason[] = array(
						'warning' => "Result not evaluated – Test Type lot number 2 is not reported.",
						'correctiveAction' => $correctiveActions[10]
					);
					$correctiveActionList[] = 10;
					$shipment['is_excluded'] = 'yes';
				}
			}
			if ($testType3 != "" && (!isset($results[0]['lot_no_3']) || $results[0]['lot_no_3'] == "" || $results[0]['lot_no_3'] == null)) {
				if (isset($results[0]['test_result_3']) && $results[0]['test_result_3'] != "" && $results[0]['test_result_3'] != null) {
					$lotResult = 'Fail';
					$failureReason[] = array(
						'warning' => "Result not evaluated – Test Type lot number 3 is not reported.",
						'correctiveAction' => $correctiveActions[10]
					);
					$correctiveActionList[] = 10;
					$shipment['is_excluded'] = 'yes';
				}
			}

			$samplePassOrFail = array();
			foreach ($results as $result) {
				if (isset($result['reported_result']) && $result['reported_result'] != null) {
					if ($result['reference_result'] == $result['reported_result']) {
						if (0 == $result['control']) {
							$totalScore += $result['sample_score'];
						}
						$score = "Pass";
					} else {
						if ($result['sample_score'] > 0) {
							/* $this->failureReason[]['warning'] = "Control/Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly"; */
						}
						$score = "Fail";
					}
				} else{
					$score = "Fail";
				}
				if (0 == $result['control']) {
					$maxScore += $result['sample_score'];
				}

				if ($score == 'Fail' || (!isset($result['reported_result']) || $result['reported_result'] == "" || $result['reported_result'] == null) || ($result['reference_result'] != $result['reported_result'])) {
					$db->update('response_result_covid19', array('calculated_score' => "Fail"), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
				} else {
					$db->update('response_result_covid19', array('calculated_score' => "Pass"), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
				}
			}

			$configuredDocScore = ((isset($config->evaluation->covid19->documentationScore) && $config->evaluation->covid19->documentationScore != "" && $config->evaluation->covid19->documentationScore != null) ? $config->evaluation->covid19->documentationScore : 0);
			// Response Score
			if ($maxScore == 0 || $totalScore == 0) {
				$responseScore = 0;
			} else {
				// $responseScore = round(($totalScore / $maxScore) * 100 * (100 - $configuredDocScore) / 100, 2);
				$responseScore = round(($totalScore / $maxScore) * 100, 2);
			}

			//Let us now calculate documentation score
			$documentationScore = 0;
			$documentationScorePerItem = ($config->evaluation->covid19->documentationScore / 3);

			// D.1
			if (isset($results[0]['shipment_receipt_date']) && strtolower($results[0]['shipment_receipt_date']) != '') {
				$documentationScore += $documentationScorePerItem;
			} else {
				$failureReason[] = array(
					'warning' => "Shipment Receipt Date not provided",
					'correctiveAction' => $correctiveActions[16]
				);
				$correctiveActionList[] = 16;
			}

			//D.3
			if (isset($attributes['sample_rehydration_date']) && trim($attributes['sample_rehydration_date']) != "") {
				$documentationScore += $documentationScorePerItem;
			} else {
				$failureReason[] = array(
					'warning' => "Missing reporting rehydration date for DTS Panel",
					'correctiveAction' => $correctiveActions[12]
				);
				$correctiveActionList[] = 12;
			}

			//D.5
			if (isset($results[0]['shipment_test_date']) && trim($results[0]['shipment_test_date']) != "") {
				$documentationScore += $documentationScorePerItem;
			} else {
				$failureReason[] = array(
					'warning' => "Shipment received test date not provided",
					'correctiveAction' => $correctiveActions[13]
				);
				$correctiveActionList[] = 13;
			}

			//D.7

			// Testing should be done within 24*($config->evaluation->covid19->sampleRehydrateDays) hours of rehydration.
			$sampleRehydrationDate = new DateTime($attributes['sample_rehydration_date']);
			$testedOnDate = new DateTime($results[0]['shipment_test_date']);
			$interval = $sampleRehydrationDate->diff($testedOnDate);

			$sampleRehydrateDays = $config->evaluation->covid19->sampleRehydrateDays;
			$rehydrateHours = $sampleRehydrateDays * 24;

			if ($interval->days > $sampleRehydrateDays) {
				$failureReason[] = array(
					'warning' => "Testing should be done within $rehydrateHours hours of rehydration.",
					'correctiveAction' => $correctiveActions[14]
				);
				$correctiveActionList[] = 14;
			} else {
				$documentationScore += $documentationScorePerItem;
			}

			//D.8
			$grandTotal = ($responseScore + $documentationScore);
			if ($grandTotal < $config->evaluation->covid19->passPercentage) {
				$scoreResult = 'Fail';
				$failureReason[] = array(
					'warning' => "Participant did not meet the score criteria (Participant Score is <strong>" . $grandTotal . "</strong> and Required Score is <strong>" . $config->evaluation->covid19->passPercentage . "</strong>)",
					'correctiveAction' => $correctiveActions[15]
				);
				$correctiveActionList[] = 15;
			} else {
				$scoreResult = 'Pass';
			}


			// if we are excluding this result, then let us not give pass/fail				
			if ($shipment['is_excluded'] == 'yes') {
				$finalResult = '';
				$shipment['is_excluded'] == 'yes';
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
				if ($algoResult == 'Fail' || $scoreResult == 'Fail' || $lastDateResult == 'Fail' || $mandatoryResult == 'Fail' || $lotResult == 'Fail' || $testTypeExpiryResult == 'Fail') {
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
			$nofOfRowsUpdated = $db->update('shipment_participant_map', array('shipment_score' => $responseScore, 'documentation_score' => 0, 'final_result' => $finalResult, "is_followup" => $shipmentResult[$counter]['is_followup'], 'is_excluded' => $shipment['is_excluded'], 'failure_reason' => null), "map_id = " . $shipment['map_id']);
			/* $nofOfRowsDeleted = $db->delete('covid19_shipment_corrective_action_map', "shipment_map_id = " . $shipment['map_id']);
			$correctiveActionList = array_unique($correctiveActionList);
			foreach ($correctiveActionList as $ca) {
				$db->insert('covid19_shipment_corrective_action_map', array('shipment_map_id' => $shipment['map_id'], 'corrective_action_id' => $ca), "map_id = " . $shipment['map_id']);
			} */

			$counter++;
		}

		if (count($scoreHolder) > 0) {
			$averageScore = round(array_sum($scoreHolder) / count($scoreHolder), 2);
		} else {
			$averageScore = 0;
		}

		//die('here');

		$db->update('shipment', array('max_score' => $maxScore, 'average_score' => $averageScore, 'status' => 'evaluated'), "shipment_id = " . $shipmentId);
		return $shipmentResult;
	}
}
