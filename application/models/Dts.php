<?php
class Application_Model_Dts
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
		$correctiveActions = $schemeService->getDtsCorrectiveActions();
		$recommendedTestkits = $schemeService->getRecommededDtsTestkit();
		foreach ($shipmentResult as $shipment) {
			//Zend_Debug::dump($shipment);

			$shipment['is_excluded'] = 'no'; // setting it as no by default. It will become 'yes' if some condition matches.

			$createdOnUser = explode(" ", $shipment['shipment_test_report_date']);
			if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {
				$createdOn = new DateTime($createdOnUser[0]);
			} else {
				$createdOn = new DateTime('1970-01-01');
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
				$shipment['is_excluded'] = 'yes';
			}


			//$serialCorrectResponses = array('NXX','PNN','PPX','PNP');				
			//$parallelCorrectResponses = array('PPX','PNP','PNN','NNX','NPN','NPP');

			// 3 tests algo added for Myanmar initally, might be used in other places eventually
			//$threeTestCorrectResponses = array('NXX','PPP');  

			$testedOn = new DateTime($results[0]['shipment_test_date']);

			// Getting the Test Date string to show in Corrective Actions and other sentences
			$testDate = $testedOn->format('d-M-Y');

			// Getting test kit expiry dates as reported
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
					if ($testedOn > ($expDate1)) {
						$difference = $testedOn->diff($expDate1);
						$failureReason[] = array(
							'warning' => "Test Kit 1 (<strong>" . $testKit1 . "</strong>) expired " . $difference->format('%a') . " days before the test date " . $testDate,
							'correctiveAction' => $correctiveActions[5]
						);
						$correctiveActionList[] = 5;
						$tk1Expired = true;
					} else {
						$tk1Expired = false;
					}
				} else {
					$failureReason[] = array(
						'warning' => "Result not evaluated – Test kit 1 expiry date is not reported with PT response.",
						'correctiveAction' => $correctiveActions[6]
					);
					$correctiveActionList[] = 6;
					$shipment['is_excluded'] = 'yes';
				}
				if (isset($recommendedTestkits[1]) && count($recommendedTestkits[1]) > 0) {
					if (!in_array($results[0]['test_kit_name_1'], $recommendedTestkits[1])) {
						$tk1RecommendedUsed = false;
						$failureReason[] = array(
							'warning' => "For Test 1, testing is not performed with country approved test kit.",
							'correctiveAction' => $correctiveActions[17]
						);
					} else {
						$tk1RecommendedUsed = true;
					}
				}
			}


			if ($testKit2 != "") {
				if ($expDate2 != "") {
					if ($testedOn > ($expDate2)) {
						$difference = $testedOn->diff($expDate2);

						$failureReason[] = array(
							'warning' => "Test Kit 2 (<strong>" . $testKit2 . "</strong>) expired " . round($difference->format("%a")) . " days before the test date " . $testDate,
							'correctiveAction' => $correctiveActions[5]
						);
						$correctiveActionList[] = 5;
						$tk2Expired = true;
					} else {
						$tk2Expired = false;
					}
				} else {
					$failureReason[] = array(
						'warning' => "Result not evaluated – Test kit 2 expiry date is not reported with PT response.",
						'correctiveAction' => $correctiveActions[6]
					);
					$correctiveActionList[] = 6;
					$shipment['is_excluded'] = 'yes';
				}

				if (isset($recommendedTestkits[2]) && count($recommendedTestkits[2]) > 0) {
					if (!in_array($results[0]['test_kit_name_2'], $recommendedTestkits[2])) {
						$tk2RecommendedUsed = false;
						$failureReason[] = array(
							'warning' => "For Test 2, testing is not performed with country approved test kit.",
							'correctiveAction' => $correctiveActions[17]
						);
					} else {
						$tk2RecommendedUsed = true;
					}
				}
			}


			if ($testKit3 != "") {
				if ($expDate3 != "") {
					if ($testedOn > ($expDate3)) {
						$difference = $testedOn->diff($expDate3);
						$failureReason[] = array(
							'warning' => "Test Kit 3 (<strong>" . $testKit3 . "</strong>) expired " . round($difference->format("%a")) . " days before the test date " . $testDate,
							'correctiveAction' => $correctiveActions[5]
						);
						$correctiveActionList[] = 5;
						$tk3Expired = true;
					} else {
						$tk3Expired = false;
					}
				} else {

					$failureReason[] = array(
						'warning' => "Result not evaluated – Test kit 3 expiry date is not reported with PT response.",
						'correctiveAction' => $correctiveActions[6]
					);
					$correctiveActionList[] = 6;
					$shipment['is_excluded'] = 'yes';
				}

				if (isset($recommendedTestkits[3]) && count($recommendedTestkits[3]) > 0) {
					if (!in_array($results[0]['test_kit_name_3'], $recommendedTestkits[3])) {
						$tk3RecommendedUsed = false;
						$failureReason[] = array(
							'warning' => "For Test 3, testing is not performed with country approved test kit.",
							'correctiveAction' => $correctiveActions[17]
						);
					} else {
						$tk3RecommendedUsed = true;
					}
				}
			}
			//checking if testkits were repeated
			// T.9 Test kit repeated for confirmatory or tiebreaker test (T1/T2/T3).
			if (($testKit1 == "") && ($testKit2 == "") && ($testKit3 == "")) {
				$failureReason[] = array(
					'warning' => "No Test Kit reported. Result not evaluated",
					'correctiveAction' => $correctiveActions[7]
				);
				$correctiveActionList[] = 7;
				$shipment['is_excluded'] = 'yes';
			} else if (($testKit1 != "") && ($testKit2 != "") && ($testKit3 != "") && ($testKit1 == $testKit2) && ($testKit2 == $testKit3)) {
				//$testKitRepeatResult = 'Fail';
				$failureReason[] = array(
					'warning' => "<strong>$testKit1</strong> repeated for all three Test Kits",
					'correctiveAction' => $correctiveActions[8]
				);
				$correctiveActionList[] = 8;
			} else {
				if (($testKit1 != "") && ($testKit2 != "") && ($testKit1 == $testKit2) && $testKit1 != "" && $testKit2 != "") {
					//$testKitRepeatResult = 'Fail';
					$failureReason[] = array(
						'warning' => "<strong>$testKit1</strong> repeated as Test Kit 1 and Test Kit 2",
						'correctiveAction' => $correctiveActions[9]
					);
					$correctiveActionList[] = 9;
				}
				if (($testKit2 != "") && ($testKit3 != "") && ($testKit2 == $testKit3) && $testKit2 != "" && $testKit3 != "") {
					//$testKitRepeatResult = 'Fail';
					$failureReason[] = array(
						'warning' => "<strong>$testKit2</strong> repeated as Test Kit 2 and Test Kit 3",
						'correctiveAction' => $correctiveActions[9]
					);
					$correctiveActionList[] = 9;
				}
				if (($testKit1 != "") && ($testKit3 != "") && ($testKit1 == $testKit3) && $testKit1 != "" && $testKit3 != "") {
					//$testKitRepeatResult = 'Fail';
					$failureReason[] = array(
						'warning' => "<strong>$testKit1</strong> repeated as Test Kit 1 and Test Kit 3",
						'correctiveAction' => $correctiveActions[9]
					);
					$correctiveActionList[] = 9;
				}
			}


			// checking if all LOT details were entered
			// T.3 Ensure test kit lot number is reported for all performed tests. 
			if ($testKit1 != "" && (!isset($results[0]['lot_no_1']) || $results[0]['lot_no_1'] == "" || $results[0]['lot_no_1'] == null)) {
				if (isset($results[0]['test_result_1']) && $results[0]['test_result_1'] != "" && $results[0]['test_result_1'] != null) {
					$lotResult = 'Fail';
					$failureReason[] = array(
						'warning' => "Result not evaluated – Test Kit lot number 1 is not reported.",
						'correctiveAction' => $correctiveActions[10]
					);
					$correctiveActionList[] = 10;
					$shipment['is_excluded'] = 'yes';
				}
			}
			if ($testKit2 != "" && (!isset($results[0]['lot_no_2']) || $results[0]['lot_no_2'] == "" || $results[0]['lot_no_2'] == null)) {
				if (isset($results[0]['test_result_2']) && $results[0]['test_result_2'] != "" && $results[0]['test_result_2'] != null) {
					$lotResult = 'Fail';
					$failureReason[] = array(
						'warning' => "Result not evaluated – Test Kit lot number 2 is not reported.",
						'correctiveAction' => $correctiveActions[10]
					);
					$correctiveActionList[] = 10;
					$shipment['is_excluded'] = 'yes';
				}
			}
			if ($testKit3 != "" && (!isset($results[0]['lot_no_3']) || $results[0]['lot_no_3'] == "" || $results[0]['lot_no_3'] == null)) {
				if (isset($results[0]['test_result_3']) && $results[0]['test_result_3'] != "" && $results[0]['test_result_3'] != null) {
					$lotResult = 'Fail';
					$failureReason[] = array(
						'warning' => "Result not evaluated – Test Kit lot number 3 is not reported.",
						'correctiveAction' => $correctiveActions[10]
					);
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
				if (0 == $result['control']) {
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

					//$algoString = "Wrongly reported in the pattern : <strong>" . $r1 . "</strong> <strong>" . $r2 . "</strong> <strong>" . $r3 . "</strong>";

					if (isset($shipmentAttributes['screeningTest']) && $shipmentAttributes['screeningTest'] == 'yes') {
						// no algorithm to check
					} else if (isset($attributes['algorithm']) && $attributes['algorithm'] == 'serial') {
						if ($r1 == 'NR') {
							if (($r2 == '-') && ($r3 == '-' || $r3 == 'X')) {
								$algoResult = 'Pass';
							} else {
								$algoResult = 'Fail';
								$failureReason[] = array(
									'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]
								);
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
								$failureReason[] = array(
									'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]
								);
								$correctiveActionList[] = 2;
							}
						} else if ($r1 == 'R' && $r2 == 'NR' && ($r3 == 'R' || $r3 == 'X')) {
							$algoResult = 'Pass';
						} else {
							$algoResult = 'Fail';
							$failureReason[] = array(
								'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
								'correctiveAction' => $correctiveActions[2]
							);
							$correctiveActionList[] = 2;
						}
					} else if ($attributes['algorithm'] == 'parallel') {

						if ($r1 == 'R' && $r2 == 'R') {
							if (($r3 == '-' || $r3 == 'X')) {
								$algoResult = 'Pass';
							} else {

								$algoResult = 'Fail';
								$failureReason[] = array(
									'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]
								);
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
								$failureReason[] = array(
									'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]
								);
								$correctiveActionList[] = 2;
							}
						} else if ($r1 == 'NR' && $r2 == 'R' && ($r3 == 'NR' || $r3 == 'X')) {
							$algoResult = 'Pass';
						} else if ($r1 == 'NR' && $r2 == 'R' && ($r3 == 'R' || $r3 == 'X')) {
							$algoResult = 'Pass';
						} else {
							$algoResult = 'Fail';
							$failureReason[] = array(
								'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
								'correctiveAction' => $correctiveActions[2]
							);
							$correctiveActionList[] = 2;
						}
					} else if ($attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
						if ($r1 == 'R' && $r2 == 'R' && $r3 == 'R') {
							$algoResult = 'Pass';
						} else if ($r1 == 'R' && $r2 == 'NR' && $r3 == 'NR') {
							$algoResult = 'Pass';
						} else if ($r1 == 'R' && $r2 == 'NR' && $r3 == 'R') {
							$algoResult = 'Pass';
						} else if (($r1 == 'R' && $r2 == 'R' && $r3 == 'NR') || ($r1 == 'R' && $r2 == 'R' && $r3 == 'I')) {
							$algoResult = 'Pass';
						} else if ($r1 == 'NR' && $r2 == '-' && $r3 == '-') {
							$algoResult = 'Pass';
						} else if ($r1 == 'NR' && $r2 == 'NR' && $r3 == '-') {
							$algoResult = 'Pass';
						} else if ($r1 == 'NR' && $r2 == 'NR' && $r3 == 'NR') {
							$algoResult = 'Pass';
						} else {
							$algoResult = 'Fail';
							$failureReason[] = array(
								'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
								'correctiveAction' => $correctiveActions[2]
							);
							$correctiveActionList[] = 2;
						}
					} else {
					}
				} else {
					// If there are two kit used for the participants then the control
					// needs to be tested with at least both kit.
					// If three then all three kits required and one then atleast one.

					if ($testKit1 != "") {
						if (!isset($result['test_result_1']) || $result['test_result_1'] == "") {
							$controlTesKitFail = 'Fail';
							$failureReason[] = array(
								'warning' => "For the Control Sample <strong>" . $result['sample_label'] . "</strong>, Test Kit 1 (<strong>$testKit1</strong>) was not used",
								'correctiveAction' => $correctiveActions[2]
							);
							$correctiveActionList[] = 2;
						}
					}

					if ($testKit2 != "") {
						if (!isset($result['test_result_2']) || $result['test_result_2'] == "") {
							$controlTesKitFail = 'Fail';
							$failureReason[] = array(
								'warning' => "For the Control Sample <strong>" . $result['sample_label'] . "</strong>, Test Kit 2 (<strong>$testKit2</strong>) was not used",
								'correctiveAction' => $correctiveActions[2]
							);
							$correctiveActionList[] = 2;
						}
					}


					if ($testKit3 != "") {
						if (!isset($result['test_result_3']) || $result['test_result_3'] == "") {
							$controlTesKitFail = 'Fail';
							$failureReason[] = array(
								'warning' => "For the Control Sample <strong>" . $result['sample_label'] . "</strong>, Test Kit 3 (<strong>$testKit3</strong>) was not used",
								'correctiveAction' => $correctiveActions[2]
							);
							$correctiveActionList[] = 2;
						}
					}
				}

				if ((!isset($result['reported_result']) || $result['reported_result'] == "" || $result['reported_result'] == null)) {
					$mandatoryResult = 'Fail';
					$shipment['is_excluded'] = 'yes';
					$failureReason[] = array(
						'warning' => "Sample <strong>" . $result['sample_label'] . "</strong> was not reported. Result not evaluated.",
						'correctiveAction' => $correctiveActions[4]
					);
					$correctiveActionList[] = 4;
				}

				// matching reported and reference results
				$correctResponse = false;
				if (isset($result['reported_result']) && $result['reported_result'] != null) {
					if ($controlTesKitFail != 'Fail') {
						if ($result['reference_result'] == $result['reported_result']) {
							if ($algoResult != 'Fail' && $mandatoryResult != 'Fail') {
								$totalScore += $result['sample_score'];
								$correctResponse = true;
							} else {
								$correctResponse = false;
								// $totalScore remains the same	
							}
						} else {
							if ($result['sample_score'] > 0) {
								$failureReason[] = array(
									'warning' => "<strong>" . $result['sample_label'] . "</strong> - Reported result does not match the expected result",
									'correctiveAction' => $correctiveActions[3]
								);
								$correctiveActionList[] = 3;
							}
							$correctResponse = false;
						}
					} else {
						$correctResponse = false;
					}
				}

				$maxScore += $result['sample_score'];

				if (isset($result['test_result_1']) && !empty($result['test_result_1']) && trim($result['test_result_1']) != false) {
					//T.1 Ensure test kit name is reported for all performed tests.
					if (($testKit1 == "")) {
						$failureReason[] = array(
							'warning' => "Result not evaluated – name of Test kit 1 not reported.",
							'correctiveAction' => $correctiveActions[7]
						);
						$correctiveActionList[] = 7;
						$shipment['is_excluded'] = 'yes';
					}
					//T.5 Ensure expiry date information is submitted for all performed tests.
					//T.15 Testing performed with a test kit that is not recommended by MOH
					if ((isset($tk1Expired) && $tk1Expired) || (isset($tk1RecommendedUsed) && !$tk1RecommendedUsed)) {
						$testKitExpiryResult = 'Fail';
						if ($correctResponse) {
							$totalScore -= $result['sample_score'];
						}
						$correctResponse = false;
					}
				}
				if (isset($result['test_result_2']) && !empty($result['test_result_2']) && trim($result['test_result_2']) != false) {
					//T.1 Ensure test kit name is reported for all performed tests.
					if (($testKit2 == "")) {
						$failureReason[] = array(
							'warning' => "Result not evaluated – name of Test kit 2 not reported.",
							'correctiveAction' => $correctiveActions[7]
						);
						$correctiveActionList[] = 7;
						$shipment['is_excluded'] = 'yes';
					}
					//T.5 Ensure expiry date information is submitted for all performed tests.
					//T.15 Testing performed with a test kit that is not recommended by MOH
					if ((isset($tk2Expired) && $tk2Expired) || (isset($tk2RecommendedUsed) && !$tk2RecommendedUsed)) {
						$testKitExpiryResult = 'Fail';
						if ($correctResponse) {
							$totalScore -= $result['sample_score'];
						}
						$correctResponse = false;
					}
				}
				if (isset($result['test_result_3']) && !empty($result['test_result_3']) && trim($result['test_result_3']) != false) {
					//T.1 Ensure test kit name is reported for all performed tests.
					if (($testKit3 == "")) {
						$failureReason[] = array(
							'warning' => "Result not evaluated – name of Test kit 3 not reported.",
							'correctiveAction' => $correctiveActions[7]
						);
						$correctiveActionList[] = 7;
						$shipment['is_excluded'] = 'yes';
					}
					//T.5 Ensure expiry date information is submitted for all performed tests.
					//T.15 Testing performed with a test kit that is not recommended by MOH
					if ((isset($tk3Expired) && $tk3Expired) || (isset($tk3RecommendedUsed) && !$tk3RecommendedUsed)) {
						$testKitExpiryResult = 'Fail';
						if ($correctResponse) {
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

			//if ((isset($config->evaluation->dts->dtsEnforceAlgorithmCheck) && $config->evaluation->dts->dtsEnforceAlgorithmCheck == 'yes')) {
			if (empty($attributes['algorithm']) || strtolower($attributes['algorithm']) == 'not-reported') {
				$failureReason[] = array(
					'warning' => "Result not evaluated. Testing algorithm not reported.",
					'correctiveAction' => $correctiveActions[2]
				);
				$correctiveActionList[] = 2;
				$shipment['is_excluded'] = 'yes';
			}
			//}

			//Let us now calculate documentation score
			$documentationScore = 0;
			if (isset($shipmentAttributes['sampleType']) && $shipmentAttributes['sampleType'] == 'dried') {
				// for Dried Samples, we will have rehydration as one of the documentation scores
				$documentationScorePerItem = ($config->evaluation->dts->documentationScore / 5);
			} else {
				// for Non Dried Samples, we will NOT have rehydration documentation scores 
				// there are 2 conditions for rehydration so 5 - 2 = 3
				$documentationScorePerItem = ($config->evaluation->dts->documentationScore / 3);
			}


			// D.1
			if (isset($results[0]['shipment_receipt_date']) && !empty($results[0]['shipment_receipt_date'])) {
				$documentationScore += $documentationScorePerItem;
			} else {
				$failureReason[] = array(
					'warning' => "Shipment Receipt Date not provided",
					'correctiveAction' => $correctiveActions[16]
				);
				$correctiveActionList[] = 16;
			}

			//D.3
			if (isset($shipmentAttributes['sampleType']) && $shipmentAttributes['sampleType'] == 'dried') {
				// Only for Dried Samples we will check Sample Rehydration
				if (isset($attributes['sample_rehydration_date']) && trim($attributes['sample_rehydration_date']) != "") {
					$documentationScore += $documentationScorePerItem;
				} else {
					$failureReason[] = array(
						'warning' => "Missing reporting rehydration date for DTS Panel",
						'correctiveAction' => $correctiveActions[12]
					);
					$correctiveActionList[] = 12;
				}
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
			if (isset($shipmentAttributes['sampleType']) && $shipmentAttributes['sampleType'] == 'dried') {

				// Only for Dried samples we will do this check

				// Testing should be done within 24*($config->evaluation->dts->sampleRehydrateDays) hours of rehydration.
				$sampleRehydrationDate = new DateTime($attributes['sample_rehydration_date']);
				$testedOnDate = new DateTime($results[0]['shipment_test_date']);
				$interval = $sampleRehydrationDate->diff($testedOnDate);

				$sampleRehydrateDays = $config->evaluation->dts->sampleRehydrateDays;
				$rehydrateHours = $sampleRehydrateDays * 24;

				if (empty($attributes['sample_rehydration_date']) || $interval->days > $sampleRehydrateDays) {
					$failureReason[] = array(
						'warning' => "Testing should be done within $rehydrateHours hours of rehydration.",
						'correctiveAction' => $correctiveActions[14]
					);
					$correctiveActionList[] = 14;
				} else {
					$documentationScore += $documentationScorePerItem;
				}
			}

			//D.8
			// For Myanmar National Algorithm, they do not want to check for Supervisor Approval
			if ($attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
				//$documentationScore += $documentationScorePerItem;
			} else {
				if (isset($results[0]['supervisor_approval']) && strtolower($results[0]['supervisor_approval']) == 'yes' && trim($results[0]['participant_supervisor']) != "") {
					$documentationScore += $documentationScorePerItem;
				} else {
					$failureReason[] = array(
						'warning' => "Supervisor approval absent",
						'correctiveAction' => $correctiveActions[11]
					);
					$correctiveActionList[] = 11;
				}
			}

			$grandTotal = ($responseScore + $documentationScore);
			if ($grandTotal < $config->evaluation->dts->passPercentage) {
				$scoreResult = 'Fail';
				$failureReason[] = array(
					'warning' => "Participant did not meet the score criteria (Participant Score is <strong>" . $grandTotal . "</strong> and Required Score is <strong>" . $config->evaluation->dts->passPercentage . "</strong>)",
					'correctiveAction' => $correctiveActions[15]
				);
				$correctiveActionList[] = 15;
			} else {
				$scoreResult = 'Pass';
			}


			// if we are excluding this result, then let us not give pass/fail				
			if ($shipment['is_excluded'] == 'yes') {
				$finalResult = '';
				$shipment['is_excluded'] = 'yes';
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
					$shipment['is_excluded'] = 'no';
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
			if(isset($shipment['manual_override']) && $shipment['manual_override'] == 'yes'){
				$sql = $db->select()->from('shipment_participant_map')->where("map_id = ?", $shipment['map_id']);
				$shipmentOverall = $db->fetchRow($sql);
				if(sizeof($shipmentOverall) > 0){
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
			}else{
				// let us update the total score in DB
				$nofOfRowsUpdated = $db->update('shipment_participant_map', array('shipment_score' => $responseScore, 'documentation_score' => $documentationScore, 'final_result' => $finalResult, "is_followup" => $shipmentResult[$counter]['is_followup'], 'is_excluded' => $shipment['is_excluded'], 'failure_reason' => $failureReason), "map_id = " . $shipment['map_id']);
			}
			$nofOfRowsDeleted = $db->delete('dts_shipment_corrective_action_map', "shipment_map_id = " . $shipment['map_id']);
			$correctiveActionList = array_unique($correctiveActionList);
			foreach ($correctiveActionList as $ca) {
				$db->insert('dts_shipment_corrective_action_map', array('shipment_map_id' => $shipment['map_id'], 'corrective_action_id' => $ca), "map_id = " . $shipment['map_id']);
			}

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
