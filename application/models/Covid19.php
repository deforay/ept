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
				$createdOn = new DateTime($createdOnUser[0]);
			} else {
				$createdOn = new DateTime('1970-01-01');
			}

			$results = $schemeService->getCovid19Samples($shipmentId, $shipment['participant_id']);

			$totalScore = 0;
			$maxScore = 0;
			$mandatoryResult = "";
			$lotResult = "";
			$testPlatform1 = "";
			$testPlatform2 = "";
			$testPlatform3 = "";
			$testPlatformRepeatResult = "";
			$testPlatformExpiryResult = "";
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
			$lastDate = new DateTime($shipment['lastdate_response']);
			if ($createdOn > $lastDate) {
				$lastDateResult = 'Fail';
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

			$testedOn = new DateTime($results[0]['shipment_test_date']);

			// Getting the Test Date string to show in Corrective Actions and other sentences
			$testDate = $testedOn->format('d-M-Y');

			// Getting test type expiry dates as reported
			$expDate1 = "";
			//die($results[0]['exp_date_1']);
			if (isset($results[0]['exp_date_1']) && trim($results[0]['exp_date_1']) != "0000-00-00" && trim(strtotime($results[0]['exp_date_1'])) != "") {
				$expDate1 = new DateTime($results[0]['exp_date_1']);
			}
			$expDate2 = "";
			if (isset($results[0]['exp_date_2']) && trim($results[0]['exp_date_2']) != "0000-00-00" && trim(strtotime($results[0]['exp_date_2'])) != "") {
				$expDate2 = new DateTime($results[0]['exp_date_2']);
			}
			$expDate3 = "";
			if (isset($results[0]['exp_date_3']) && trim($results[0]['exp_date_3']) != "0000-00-00" && trim(strtotime($results[0]['exp_date_3'])) != "") {
				$expDate3 = new DateTime($results[0]['exp_date_3']);
			}

			// Getting Test Platform Names

			$testPlatformDb = new Application_Model_DbTable_TestTypenameCovid19();
			$testPlatform1 = "";

			$testPlatformName = $testPlatformDb->getTestTypeNameById($results[0]['test_type_1']);
			if (isset($testPlatformName[0])) {
				$testPlatform1 = $testPlatformName[0];
			}

			$testPlatform2 = "";
			if (trim($results[0]['test_type_2']) != "") {
				$testPlatformName = $testPlatformDb->getTestTypeNameById($results[0]['test_type_2']);
				if (isset($testPlatformName[0])) {
					$testPlatform2 = $testPlatformName[0];
				}
			}
			$testPlatform3 = "";
			if (trim($results[0]['test_type_3']) != "") {
				$testPlatformName = $testPlatformDb->getTestTypeNameById($results[0]['test_type_3']);
				if (isset($testPlatformName[0])) {
					$testPlatform3 = $testPlatformName[0];
				}
			}


			// T.7 Checking for Expired Test Platforms

			if ($testPlatform1 != "") {
				// if ($expDate1 != "") {
				// 	if ($testedOn > ($expDate1)) {
				// 		$difference = $testedOn->diff($expDate1);
				// 		$failureReason[] = array(
				// 			'warning' => "Test Platform 1 (<strong>" . $testPlatform1 . "</strong>) expired " . $difference->format('%a') . " days before the test date " . $testDate,
				// 			'correctiveAction' => $correctiveActions[5]
				// 		);
				// 		$correctiveActionList[] = 5;
				// 		$tt1Expired = true;
				// 	} else {
				// 		$tt1Expired = false;
				// 	}
				// } else {
				// 	$failureReason[] = array(
				// 		'warning' => "Result not evaluated – Test platform 1 expiry date is not reported with PT response.",
				// 		'correctiveAction' => $correctiveActions[6]
				// 	);
				// 	$correctiveActionList[] = 6;
				// 	$shipment['is_excluded'] = 'yes';
				// }
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

			if ($testPlatform2 != "") {
				// if ($expDate2 != "") {
				// 	if ($testedOn > ($expDate2)) {
				// 		$difference = $testedOn->diff($expDate2);
				// 		$failureReason[] = array(
				// 			'warning' => "Test Platform 2 (<strong>" . $testPlatform2 . "</strong>) expired " . $difference->format('%a')  . " days before the test date " . $testDate,
				// 			'correctiveAction' => $correctiveActions[5]
				// 		);
				// 		$correctiveActionList[] = 5;
				// 		$tt2Expired = true;
				// 	} else {
				// 		$tt2Expired = false;
				// 	}
				// } else {
				// 	$failureReason[] = array(
				// 		'warning' => "Result not evaluated – Test platform 2 expiry date is not reported with PT response.",
				// 		'correctiveAction' => $correctiveActions[6]
				// 	);
				// 	$correctiveActionList[] = 6;
				// 	$shipment['is_excluded'] = 'yes';
				// }

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


			if ($testPlatform3 != "") {
				// if ($expDate3 != "") {
				// 	if ($testedOn > ($expDate2)) {
				// 		$difference = $testedOn->diff($expDate2);
				// 		$failureReason[] = array(
				// 			'warning' => "Test Platform 3 (<strong>" . $testPlatform3 . "</strong>) expired " . $difference->format('%a')  . " days before the test date " . $testDate,
				// 			'correctiveAction' => $correctiveActions[5]
				// 		);
				// 		$correctiveActionList[] = 5;
				// 		$tt3Expired = true;
				// 	} else {
				// 		$tt3Expired = false;
				// 	}
				// } else {

				// 	$failureReason[] = array(
				// 		'warning' => "Result not evaluated – Test platform 3 expiry date is not reported with PT response.",
				// 		'correctiveAction' => $correctiveActions[6]
				// 	);
				// 	$correctiveActionList[] = 6;
				// 	$shipment['is_excluded'] = 'yes';
				// }

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
			// T.9 Test platform repeated for confirmatory or tiebreaker test (T1/T2/T3).
			if (($testPlatform1 == "") && ($testPlatform2 == "") && ($testPlatform3 == "")) {
				$failureReason[] = array(
					'warning' => "No Test Platform reported. Result not evaluated",
					'correctiveAction' => $correctiveActions[7]
				);
				$correctiveActionList[] = 7;
				$shipment['is_excluded'] = 'yes';
			} else if (($testPlatform1 != "") && ($testPlatform2 != "") && ($testPlatform3 != "") && ($testPlatform1 == $testPlatform2) && ($testPlatform2 == $testPlatform3)) {
				//$testPlatformRepeatResult = 'Fail';
				$failureReason[] = array(
					'warning' => "<strong>$testPlatform1</strong> repeated for all three Test Platforms",
					'correctiveAction' => $correctiveActions[8]
				);
				$correctiveActionList[] = 8;
			} else {
				if (($testPlatform1 != "") && ($testPlatform2 != "") && ($testPlatform1 == $testPlatform2) && $testPlatform1 != "" && $testPlatform2 != "") {
					//$testPlatformRepeatResult = 'Fail';
					$failureReason[] = array(
						'warning' => "<strong>$testPlatform1</strong> repeated as Test Platform 1 and Test Platform 2",
						'correctiveAction' => $correctiveActions[9]
					);
					$correctiveActionList[] = 9;
				}
				if (($testPlatform2 != "") && ($testPlatform3 != "") && ($testPlatform2 == $testPlatform3) && $testPlatform2 != "" && $testPlatform3 != "") {
					//$testPlatformRepeatResult = 'Fail';
					$failureReason[] = array(
						'warning' => "<strong>$testPlatform2</strong> repeated as Test Platform 2 and Test Platform 3",
						'correctiveAction' => $correctiveActions[9]
					);
					$correctiveActionList[] = 9;
				}
				if (($testPlatform1 != "") && ($testPlatform3 != "") && ($testPlatform1 == $testPlatform3) && $testPlatform1 != "" && $testPlatform3 != "") {
					//$testPlatformRepeatResult = 'Fail';
					$failureReason[] = array(
						'warning' => "<strong>$testPlatform1</strong> repeated as Test Platform 1 and Test Platform 3",
						'correctiveAction' => $correctiveActions[9]
					);
					$correctiveActionList[] = 9;
				}
			}


			// checking if all LOT details were entered
			// T.3 Ensure test type lot number is reported for all performed tests. 
			// if ($testPlatform1 != "" && (!isset($results[0]['lot_no_1']) || $results[0]['lot_no_1'] == "" || $results[0]['lot_no_1'] == null)) {
			// 	if (isset($results[0]['test_result_1']) && $results[0]['test_result_1'] != "" && $results[0]['test_result_1'] != null) {
			// 		$lotResult = 'Fail';
			// 		$failureReason[] = array(
			// 			'warning' => "Result not evaluated – Test Platform lot number 1 is not reported.",
			// 			'correctiveAction' => $correctiveActions[10]
			// 		);
			// 		$correctiveActionList[] = 10;
			// 		$shipment['is_excluded'] = 'yes';
			// 	}
			// }
			// if ($testPlatform2 != "" && (!isset($results[0]['lot_no_2']) || $results[0]['lot_no_2'] == "" || $results[0]['lot_no_2'] == null)) {
			// 	if (isset($results[0]['test_result_2']) && $results[0]['test_result_2'] != "" && $results[0]['test_result_2'] != null) {
			// 		$lotResult = 'Fail';
			// 		$failureReason[] = array(
			// 			'warning' => "Result not evaluated – Test Platform lot number 2 is not reported.",
			// 			'correctiveAction' => $correctiveActions[10]
			// 		);
			// 		$correctiveActionList[] = 10;
			// 		$shipment['is_excluded'] = 'yes';
			// 	}
			// }
			// if ($testPlatform3 != "" && (!isset($results[0]['lot_no_3']) || $results[0]['lot_no_3'] == "" || $results[0]['lot_no_3'] == null)) {
			// 	if (isset($results[0]['test_result_3']) && $results[0]['test_result_3'] != "" && $results[0]['test_result_3'] != null) {
			// 		$lotResult = 'Fail';
			// 		$failureReason[] = array(
			// 			'warning' => "Result not evaluated – Test Platform lot number 3 is not reported.",
			// 			'correctiveAction' => $correctiveActions[10]
			// 		);
			// 		$correctiveActionList[] = 10;
			// 		$shipment['is_excluded'] = 'yes';
			// 	}
			// }

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
				} else {
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
					'warning' => "Sample rehydration date not recorded",
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
			//$rehydrateHours = $sampleRehydrateDays * 24;

			// we can allow testers to test upto sampleRehydrateDays or sampleRehydrateDays + 1
            if (empty($attributes['sample_rehydration_date']) || $interval->days < $sampleRehydrateDays || $interval->days > ($sampleRehydrateDays + 1)) {
                $failureReason[] = array(
                    'warning' => "Testing not done within specified time of rehydration as per SOP.",
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
			$shipment['is_excluded'] = 'no';
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
				if ($algoResult == 'Fail' || $scoreResult == 'Fail' || $lastDateResult == 'Fail' || $mandatoryResult == 'Fail' || $lotResult == 'Fail' || $testPlatformExpiryResult == 'Fail') {
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

			/* Manual result override changes */
			if (isset($shipment['manual_override']) && $shipment['manual_override'] == 'yes') {
				$sql = $db->select()->from('shipment_participant_map')->where("map_id = ?", $shipment['map_id']);
				$shipmentOverall = $db->fetchRow($sql);
				if (sizeof($shipmentOverall) > 0) {
					$shipmentResult[$counter]['shipment_score'] = $shipmentOverall['shipment_score'];
					$shipmentResult[$counter]['documentation_score'] = $shipmentOverall['documentation_score'];
					if(!isset($shipmentOverall['final_result']) || $shipmentOverall['final_result'] == ""){
						$shipmentOverall['final_result'] = 2;
					}
					$fRes = $db->fetchCol($db->select()->from('r_results', array('result_name'))->where('result_id = ' . $shipmentOverall['final_result']));
					$shipmentResult[$counter]['display_result'] = $fRes[0];
					// Zend_Debug::dump($shipmentResult);die;
					$nofOfRowsUpdated = $db->update('shipment_participant_map', array('shipment_score' => $shipmentOverall['shipment_score'], 'documentation_score' => $shipmentOverall['documentation_score'], 'final_result' => $shipmentOverall['final_result']), "map_id = " . $shipment['map_id']);
				}
			} else {
				// let us update the total score in DB
				$nofOfRowsUpdated = $db->update('shipment_participant_map', array('shipment_score' => $responseScore, 'documentation_score' => 0, 'final_result' => $finalResult, "is_followup" => $shipmentResult[$counter]['is_followup'], 'is_excluded' => $shipment['is_excluded'], 'failure_reason' => null), "map_id = " . $shipment['map_id']);
			}
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
