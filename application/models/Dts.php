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
			$dtsSchemeType = (isset($shipmentAttributes["dtsSchemeType"]) && $shipmentAttributes["dtsSchemeType"] != '') ? $shipmentAttributes["dtsSchemeType"] : null;
			$syphilisEnabled = ($dtsSchemeType == 'ghana' && isset($shipmentAttributes['enableSyphilis']) && $shipmentAttributes['enableSyphilis'] == "yes") ? true : false;



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
						'warning' => "Result not evaluated : Test kit 1 expiry date is not reported with PT response.",
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
						'warning' => "Result not evaluated : Test kit 2 expiry date is not reported with PT response.",
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
						'warning' => "Result not evaluated : Test kit 3 expiry date is not reported with PT response.",
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
						'warning' => "Result not evaluated : Test Kit lot number 1 is not reported.",
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
						'warning' => "Result not evaluated : Test Kit lot number 2 is not reported.",
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
						'warning' => "Result not evaluated : Test Kit lot number 3 is not reported.",
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

				$reportedResultCode = isset($result['result_code']) ? $result['result_code'] : null;
				$reportedSyphilisResult = isset($result['syphilis_final']) ? $result['syphilis_final'] : null;


				// Checking algorithm Pass/Fail only if it is NOT a control.
				if (0 == $result['control']) {
					$syphilisResult = $result1 = $result2 = $result3 = $isRetest = '';
					$repeatResult1 = $repeatResult2 = $repeatResult3 = '';
					if ($result['syphilis_result'] == 1) {
						$syphilisResult = 'R';
					} else if ($result['syphilis_result'] == 2) {
						$syphilisResult = 'NR';
					} else if ($result['syphilis_result'] == 3) {
						$syphilisResult = 'I';
					} else {
						$syphilisResult = '-';
					}
					if ($result['test_result_1'] == 1) {
						$result1 = 'R';
					} else if ($result['test_result_1'] == 2) {
						$result1 = 'NR';
					} else if ($result['test_result_1'] == 3) {
						$result1 = 'I';
					} else {
						$result1 = '-';
					}

					if (isset($result['is_this_retest']) && !empty($result['is_this_retest']) && $result['is_this_retest'] == 'yes') {
						$isRetest = 'yes';
					} else {
						$isRetest = '-';
					}

					if ($result['repeat_test_result_1'] == 1) {
						$repeatResult1 = 'R';
					} else if ($result['repeat_test_result_1'] == 2) {
						$repeatResult1 = 'NR';
					} else if ($result['repeat_test_result_1'] == 3) {
						$repeatResult1 = 'I';
					} else {
						$repeatResult1 = '-';
					}
					if ($result['test_result_2'] == 1) {
						$result2 = 'R';
					} else if ($result['test_result_2'] == 2) {
						$result2 = 'NR';
					} else if ($result['test_result_2'] == 3) {
						$result2 = 'I';
					} else {
						$result2 = '-';
					}
					if ($result['repeat_test_result_2'] == 1) {
						$repeatResult2 = 'R';
					} else if ($result['repeat_test_result_2'] == 2) {
						$repeatResult2 = 'NR';
					} else if ($result['repeat_test_result_2'] == 3) {
						$repeatResult2 = 'I';
					} else {
						$repeatResult2 = '-';
					}
					if ($attributes['algorithm'] != 'myanmarNationalDtsAlgo' && isset($config->evaluation->dts->dtsOptionalTest3) && $config->evaluation->dts->dtsOptionalTest3 == 'yes') {
						$result3 = 'X';
						$repeatResult3 = 'X';
					} else {
						if ($result['test_result_3'] == 1) {
							$result3 = 'R';
						} else if ($result['test_result_3'] == 2) {
							$result3 = 'NR';
						} else if ($result['test_result_3'] == 3) {
							$result3 = 'I';
						} else {
							$result3 = '-';
						}
						if ($result['repeat_test_result_3'] == 1) {
							$repeatResult3 = 'R';
						} else if ($result['repeat_test_result_3'] == 2) {
							$repeatResult3 = 'NR';
						} else if ($result['repeat_test_result_3'] == 3) {
							$repeatResult3 = 'I';
						} else {
							$repeatResult3 = '-';
						}
					}

					//$algoString = "Wrongly reported in the pattern : <strong>" . $result1 . "</strong> <strong>" . $result2 . "</strong> <strong>" . $result3 . "</strong>";

					$scorePercentageForAlgorithm = 0; // Most countries do not give score for getting algorithm right
					if (isset($shipmentAttributes['screeningTest']) && $shipmentAttributes['screeningTest'] == 'yes') {
						// no algorithm to check
					} else if (isset($attributes['algorithm']) && $attributes['algorithm'] == 'serial') {
						if ($result1 == 'NR') {
							if (($result2 == '-') && ($result3 == '-' || $result3 == 'X')) {
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
						//			else if ($result1 == 'R' && $result2 == 'NR' && $result3 == 'NR') {
						//                            $algoResult = 'Pass';
						//                        }
						else if ($result1 == 'R' && $result2 == 'R') {
							if (($result3 == '-' || $result3 == 'X')) {
								$algoResult = 'Pass';
							} else {
								$algoResult = 'Fail';
								$failureReason[] = array(
									'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]
								);
								$correctiveActionList[] = 2;
							}
						} else if ($result1 == 'R' && $result2 == 'NR' && ($result3 == 'R' || $result3 == 'X')) {
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

						if ($result1 == 'R' && $result2 == 'R') {
							if (($result3 == '-' || $result3 == 'X')) {
								$algoResult = 'Pass';
							} else {

								$algoResult = 'Fail';
								$failureReason[] = array(
									'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]
								);
								$correctiveActionList[] = 2;
							}
						} else if ($result1 == 'R' && $result2 == 'NR' && ($result3 == 'R' || $result3 == 'X')) {
							$algoResult = 'Pass';
						} else if ($result1 == 'R' && $result2 == 'NR' && ($result3 == 'NR' || $result3 == 'X')) {
							$algoResult = 'Pass';
						} else if ($result1 == 'NR' && $result2 == 'NR') {
							if (($result3 == '-' || $result3 == 'X')) {
								$algoResult = 'Pass';
							} else {
								$algoResult = 'Fail';
								$failureReason[] = array(
									'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]
								);
								$correctiveActionList[] = 2;
							}
						} else if ($result1 == 'NR' && $result2 == 'R' && ($result3 == 'NR' || $result3 == 'X')) {
							$algoResult = 'Pass';
						} else if ($result1 == 'NR' && $result2 == 'R' && ($result3 == 'R' || $result3 == 'X')) {
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

						$scorePercentageForAlgorithm = 0.5; // Myanmar gives 50% score for getting algorithm right
						// NR-- => N
						// R-R-R => P
						// R-NR-NR => N
						// R-NR-R => I
						// R-R-NR => I

						//$rstring = $result1."-".$result2."-".$result3."-".$reportedResultCode;

						if ($result1 == 'NR' && $result2 == '-' && $result3 == '-' && $reportedResultCode == 'N') {
							$algoResult = 'Pass';
						} else if ($result1 == 'R' && $result2 == 'R' && $result3 == 'R' && $reportedResultCode == 'P') {
							$algoResult = 'Pass';
						} else if ($result1 == 'R' && $result2 == 'R' && $result3 == 'R' && $reportedResultCode == 'R') {
							$algoResult = 'Pass';
						} else if ($result1 == 'R' && $result2 == 'NR' && $result3 == 'NR' && $reportedResultCode == 'N') {
							$algoResult = 'Pass';
						} else if ($result1 == 'R' && $result2 == 'NR' && $result3 == 'R' && $reportedResultCode == 'I') {
							$algoResult = 'Pass';
						} else if (($result1 == 'R' && $result2 == 'R' && $result3 == 'NR' && $reportedResultCode == 'I') || ($result1 == 'R' && $result2 == 'R' && $result3 == 'I' && $reportedResultCode == 'I')) {
							$algoResult = 'Pass';
						} else {
							$algoResult = 'Fail';
							$failureReason[] = array(
								'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
								'correctiveAction' => $correctiveActions[2]
							);
							$correctiveActionList[] = 2;
						}
					} else if ($attributes['algorithm'] == 'malawiNationalDtsAlgo') {

						if ($result1 == 'NR' && $reportedResultCode == 'N') {
							if ($result2 == '-' && $repeatResult1 == '-' && $repeatResult2 == '-') {
								$algoResult = 'Pass';
							} else {
								$algoResult = 'Fail';
								$failureReason[] = array(
									'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]
								);
								$correctiveActionList[] = 2;
							}
						} else if ($result1 == 'R') {
							if ($result2 == 'R' && $reportedResultCode == 'P' && $repeatResult1 == '-' && $repeatResult2 == '-') {
								$algoResult = 'Pass';
							} else if ($result2 == 'NR') {
								// if Result 2 is NR then, we go for repeat tests
								if ($repeatResult1 == 'NR' && $repeatResult2 == 'NR' && $reportedResultCode == 'N') {
									$algoResult = 'Pass';
								} else if ($repeatResult1 == 'R' && $repeatResult2 == 'R' && $reportedResultCode == 'P') {
									$algoResult = 'Pass';
								} else if ($repeatResult1 == 'R' && $repeatResult2 == 'NR' && $reportedResultCode == 'I') {
									$algoResult = 'Pass';
								} else if ($repeatResult1 == 'NR' && $repeatResult2 == 'N' && $reportedResultCode == 'I') {
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
								$algoResult = 'Fail';
								$failureReason[] = array(
									'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]
								);
								$correctiveActionList[] = 2;
							}
						}
					} else if ($dtsSchemeType == 'ghana') {

						if ($syphilisEnabled == true) {
							if ($syphilisResult == 'R' && $reportedSyphilisResult == 4) {
								$sypAlgoResult = 'Pass';
							} else if ($syphilisResult == 'NR' && $reportedSyphilisResult == 5) {
								$sypAlgoResult = 'Pass';
							} else {
								$sypAlgoResult = 'Fail';
							}
						}

						if ($result1 == 'NR' && $reportedResultCode == 'N') {
							if (($result2 == '-' && $result3 == '-' && $isRetest == '-')) {
								$algoResult = 'Pass';
							} else {
								$algoResult = 'Fail';
								$failureReason[] = array(
									'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]
								);
								$correctiveActionList[] = 2;
							}
						} else if ($result1 == 'R') {
							if ($result2 == 'R' && $result3 == 'R' && $reportedResultCode == 'P' && $isRetest == '-') {
								$algoResult = 'Pass';
							} else if ($result2 == 'NR') {
								// if Result 2 is NR then, we go for repeat tests
								if ($repeatResult1 == 'NR' && $repeatResult2 == 'NR' && $reportedResultCode == 'N') {
									$algoResult = 'Pass';
								} else if ($repeatResult1 == 'R' && $repeatResult2 == 'R' && $reportedResultCode == 'P') {
									$algoResult = 'Pass';
								} else if ($repeatResult1 == 'R' && $repeatResult2 == 'NR' && $reportedResultCode == 'I') {
									$algoResult = 'Pass';
								} else if ($repeatResult1 == 'NR' && $repeatResult2 == 'N' && $reportedResultCode == 'I') {
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
								$algoResult = 'Fail';
								$failureReason[] = array(
									'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]
								);
								$correctiveActionList[] = 2;
							}
						}
					} else {
					}

					// END OF SAMPLE CHECK
				} else {
					// CONTROLS
					// If there are two kits used for the participants then the control
					// needs to be tested with at least both kit.
					// If three then all three kits required and one then atleast one.

					if ($testKit1 != "") {
						if (!isset($result['test_result_1']) || $result['test_result_1'] == "") {
							$controlTesKitFail = 'Fail';
							$failureReason[] = array(
								'warning' => "For the Control <strong>" . $result['sample_label'] . "</strong>, Test Kit 1 (<strong>$testKit1</strong>) was not used",
								'correctiveAction' => $correctiveActions[2]
							);
							$correctiveActionList[] = 2;
						}
					}

					if ($testKit2 != "") {
						if (!isset($result['test_result_2']) || $result['test_result_2'] == "") {
							$controlTesKitFail = 'Fail';
							$failureReason[] = array(
								'warning' => "For the Control <strong>" . $result['sample_label'] . "</strong>, Test Kit 2 (<strong>$testKit2</strong>) was not used",
								'correctiveAction' => $correctiveActions[2]
							);
							$correctiveActionList[] = 2;
						}
					}


					if ($testKit3 != "") {
						if (!isset($result['test_result_3']) || $result['test_result_3'] == "") {
							$controlTesKitFail = 'Fail';
							$failureReason[] = array(
								'warning' => "For the Control <strong>" . $result['sample_label'] . "</strong>, Test Kit 3 (<strong>$testKit3</strong>) was not used",
								'correctiveAction' => $correctiveActions[2]
							);
							$correctiveActionList[] = 2;
						}
					}

					// END OF CONTROLS
				}

				// Matching reported and reference results
				$correctResponse = false;
				$scoreForSample = $result['sample_score'];
				$scoreForAlgorithm = 0;
				if ($scorePercentageForAlgorithm > 0 && $scorePercentageForAlgorithm < 1) {
					$scoreForAlgorithm = $scorePercentageForAlgorithm * $result['sample_score'];
					$scoreForSample = $result['sample_score'] - $scoreForAlgorithm;
				}




				// If final resut was not reported then the participant is failed 
				if (!isset($result['reported_result']) || empty(trim($result['reported_result']))) {
					$mandatoryResult = 'Fail';
					$shipment['is_excluded'] = 'yes';
					$failureReason[] = array(
						'warning' => "Sample <strong>" . $result['sample_label'] . "</strong> was not reported. Result not evaluated.",
						'correctiveAction' => $correctiveActions[4]
					);
					$correctiveActionList[] = 4;
				} else {
					if ($controlTesKitFail != 'Fail') {

						// Keeping this as always true so that even for the
						// non-syphilis samples scores can be calculated
						$correctSyphilisResponse = true;
						if ($syphilisEnabled == true) {
							if ($reportedSyphilisResult == $result['syphilis_reference_result']) {
								if ($sypAlgoResult != 'Fail') {
									$correctSyphilisResponse = true;
								} else {
									$correctSyphilisResponse = false;
								}
							} else {
								$correctSyphilisResponse = false;
							}
						}

						if ($result['reference_result'] == $result['reported_result']) {
							if ($correctSyphilisResponse && $algoResult != 'Fail') {
								$totalScore += ($scoreForSample + $scoreForAlgorithm);
								$correctResponse = true;
							} else if ($correctSyphilisResponse && ($scorePercentageForAlgorithm > 0 && $algoResult == 'Fail')) {
								$totalScore += $scoreForSample;
								$correctResponse = false;
							} else {
								// $totalScore remains the same	if algoResult == fail and there is no score for algo
								$correctResponse = false;
							}
						} else {
							if ($result['sample_score'] > 0) {
								if ($algoResult != 'Fail') {
									$totalScore += ($scoreForAlgorithm);
								}
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

				// Calculating the max score -- will be used in calculations later
				$maxScore += $result['sample_score'];

				if (isset($result['test_result_1']) && !empty($result['test_result_1']) && trim($result['test_result_1']) != false) {
					//T.1 Ensure test kit name is reported for all performed tests.
					if (($testKit1 == "")) {
						$failureReason[] = array(
							'warning' => "Result not evaluated : name of Test kit 1 not reported.",
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
							$totalScore -= ($scoreForSample);
						}
						if ($algoResult == 'Pass') {
							$totalScore -= ($scoreForAlgorithm);
						}
						$correctResponse = false;
						$algoResult = 'Fail';
					}
				}
				if (isset($result['test_result_2']) && !empty($result['test_result_2']) && trim($result['test_result_2']) != false) {
					//T.1 Ensure test kit name is reported for all performed tests.
					if (($testKit2 == "")) {
						$failureReason[] = array(
							'warning' => "Result not evaluated : name of Test kit 2 not reported.",
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
							$totalScore -= ($scoreForSample);
						}
						if ($algoResult == 'Pass') {
							$totalScore -= ($scoreForAlgorithm);
						}
						$correctResponse = false;
						$algoResult = 'Fail';
					}
				}
				if (isset($result['test_result_3']) && !empty($result['test_result_3']) && trim($result['test_result_3']) != false) {
					//T.1 Ensure test kit name is reported for all performed tests.
					if (($testKit3 == "")) {
						$failureReason[] = array(
							'warning' => "Result not evaluated : name of Test kit 3 not reported.",
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
							$totalScore -= ($scoreForSample);
						}
						if ($algoResult == 'Pass') {
							$totalScore -= ($scoreForAlgorithm);
						}
						$correctResponse = false;
						$algoResult = 'Fail';
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

			if ($attributes['algorithm'] == 'malawiNationalDtsAlgo') {
				// For Malawi we have 4 more documentation items to consider - Sample Condition, Fridge, Stop Watch and Room Temp
				$totalDocumentationItems += 4;
			}

			$documentationScorePerItem = round(($config->evaluation->dts->documentationScore / $totalDocumentationItems), 2);


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

			//echo "Receipt Date : $documentationScore <br>";

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

			//echo "Test Date : $documentationScore <br>";

			//D.7
			if (isset($shipmentAttributes['sampleType']) && $shipmentAttributes['sampleType'] == 'dried') {

				// Only for Dried samples we will do this check

				// Testing should be done within 24*($config->evaluation->dts->sampleRehydrateDays) hours of rehydration.
				$sampleRehydrationDate = new DateTime($attributes['sample_rehydration_date']);
				$testedOnDate = new DateTime($results[0]['shipment_test_date']);
				$interval = $sampleRehydrationDate->diff($testedOnDate);

				$sampleRehydrateDays = $config->evaluation->dts->sampleRehydrateDays;
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
			}

			//D.8
			// For Myanmar National Algorithm, they do not want to check for Supervisor Approval
			if ($attributes['algorithm'] != 'myanmarNationalDtsAlgo') {
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

			if ($attributes['algorithm'] == 'malawiNationalDtsAlgo') {
				if (!empty($attributes['condition_pt_samples'])) {
					$documentationScore += $documentationScorePerItem;
				} else {
					$failureReason[] = array(
						'warning' => "Condition of PT Samples not reported",
						'correctiveAction' => $correctiveActions[18]
					);
					$correctiveActionList[] = 18;
				}
				if (!empty($attributes['refridgerator'])) {
					$documentationScore += $documentationScorePerItem;
				} else {
					$failureReason[] = array(
						'warning' => "Refridgerator availability not reported",
						'correctiveAction' => $correctiveActions[19]
					);
					$correctiveActionList[] = 18;
				}
				if (!empty($attributes['room_temperature'])) {
					$documentationScore += $documentationScorePerItem;
				} else {
					$failureReason[] = array(
						'warning' => "Room Temperature not reported",
						'correctiveAction' => $correctiveActions[20]
					);
					$correctiveActionList[] = 18;
				}
				if (!empty($attributes['stop_watch'])) {
					$documentationScore += $documentationScorePerItem;
				} else {
					$failureReason[] = array(
						'warning' => "Stop Watch Availability not reported",
						'correctiveAction' => $correctiveActions[21]
					);
					$correctiveActionList[] = 18;
				}
			}

			$documentationScore = round($documentationScore);
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
			if (isset($shipment['manual_override']) && $shipment['manual_override'] == 'yes') {
				$sql = $db->select()->from('shipment_participant_map')->where("map_id = ?", $shipment['map_id']);
				$shipmentOverall = $db->fetchRow($sql);
				if (sizeof($shipmentOverall) > 0) {
					$shipmentResult[$counter]['shipment_score'] = $shipmentOverall['shipment_score'];
					$shipmentResult[$counter]['documentation_score'] = $shipmentOverall['documentation_score'];
					if (!isset($shipmentOverall['final_result']) || $shipmentOverall['final_result'] == "") {
						$shipmentOverall['final_result'] = 2;
					}
					$fRes = $db->fetchCol($db->select()->from('r_results', array('result_name'))->where('result_id = ' . $shipmentOverall['final_result']));
					$shipmentResult[$counter]['display_result'] = $fRes[0];
					// Zend_Debug::dump($shipmentResult);die;
					$nofOfRowsUpdated = $db->update('shipment_participant_map', array('shipment_score' => $shipmentOverall['shipment_score'], 'documentation_score' => $shipmentOverall['documentation_score'], 'final_result' => $shipmentOverall['final_result']), "map_id = " . $shipment['map_id']);
				}
			} else {
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
