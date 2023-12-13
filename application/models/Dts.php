<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

error_reporting(E_ALL ^ E_NOTICE);

class Application_Model_Dts
{

	private $db = null;

	public function __construct()
	{
		$this->db = Zend_Db_Table_Abstract::getDefaultAdapter();
	}

	public function getFinalResults()
	{
		$fRes = $this->db->fetchAll("SELECT * FROM r_results");
		$response = [];
		foreach ($fRes as $r) {
			$response[$r['result_id']] = $r['result_name'];
		}
		return $response;
	}

	public function evaluate($shipmentResult, $shipmentId, $reEvaluate = false)
	{

		ini_set('memory_limit', '-1');

		$counter = 0;
		$maxScore = 0;
		$scoreHolder = [];

		$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
		$config = new Zend_Config_Ini($file, APPLICATION_ENV);
		$schemeService = new Application_Service_Schemes();


		$shipmentAttributes = json_decode($shipmentResult[0]['shipment_attributes'], true);
		$dtsSchemeType = (isset($shipmentAttributes["dtsSchemeType"]) && $shipmentAttributes["dtsSchemeType"] != '') ? $shipmentAttributes["dtsSchemeType"] : null;
		$syphilisEnabled = (isset($shipmentAttributes['enableSyphilis']) && $shipmentAttributes['enableSyphilis'] == "yes") ? true : false;
		$rtriEnabled = (isset($shipmentAttributes['enableRtri']) && $shipmentAttributes['enableRtri'] == 'yes') ? true : false;

		if ($rtriEnabled) {
			$possibleResultsArray = $schemeService->getPossibleResults('recency');
			$possibleRecencyResults = [];
			foreach ($possibleResultsArray as $possibleRecencyResults) {
				$possibleRecencyResults['result_code'] =  $possibleRecencyResults['id'];
			}
		}

		$correctiveActions = $this->getDtsCorrectiveActions();
		if ($syphilisEnabled) {
			$testMode = 'dts+syphilis';
		} elseif ($rtriEnabled) {
			$testMode = 'dts+rtri';
		} else {
			$testMode = 'dts';
		}
		$recommendedTestkits = $this->getRecommededDtsTestkits($testMode);
		$resultsForShipmentDataset = $this->getDtsSamples($shipmentId);
		$resultsForShipment = [];
		foreach ($resultsForShipmentDataset as $r) {
			$resultsForShipment[$r['participant_id']][] = $r;
		}

		$finalResultArray = $this->getFinalResults();

		$this->db->update('shipment_participant_map', array('failure_reason' => null, 'is_followup' => 'no', 'is_excluded' => 'no'), "shipment_id = $shipmentId");
		$this->db->update('shipment_participant_map', array('is_excluded' => 'yes'), "shipment_id = $shipmentId AND IFNULL(is_pt_test_not_performed, 'no') = 'yes'");


		foreach ($shipmentResult as $shipment) {

			// setting the following as no by default. Might become 'yes' if some conditions match
			$shipment['is_excluded'] = 'no';
			$shipment['is_followup'] = 'no';

			$createdOnUser = explode(" ", $shipment['shipment_test_report_date']);
			if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {
				$createdOn = new DateTime($createdOnUser[0]);
			} else {
				$createdOn = new DateTime('1970-01-01');
			}


			$results = $resultsForShipment[$shipment['participant_id']];

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
			$failureReason = [];
			$correctiveActionList = [];
			$algoResult = "";
			$lastDateResult = "";
			$controlTesKitFail = "";

			$attributes = json_decode($shipment['attributes'], true);

			$attributes['algorithm'] = $attributes['algorithm'] ?: null;
			//$attributes['sample_rehydration_date'] = $attributes['sample_rehydration_date'] ?: null;


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
				$shipment['is_response_late'] = 'yes';
			} else {
				$shipment['is_response_late'] = 'no';
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
			} elseif (($testKit1 != "") && ($testKit2 != "") && ($testKit3 != "") && ($testKit1 == $testKit2) && ($testKit2 == $testKit3)) {

				//Myanmar does not mind if all three test kits are same.
				if ($dtsSchemeType != 'myanmar') {
					//$testKitRepeatResult = 'Fail';
					$failureReason[] = array(
						'warning' => "<strong>$testKit1</strong> repeated for all three Test Kits",
						'correctiveAction' => $correctiveActions[8]
					);
					$correctiveActionList[] = 8;
				}
			} else {
				//Myanmar does not mind if test kits are repeated
				if ($dtsSchemeType != 'myanmar') {
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

			$samplePassOrFail = [];
			foreach ($results as $result) {
				//if Sample is not mandatory, we will skip the evaluation
				if (0 == $result['mandatory']) {
					$this->db->update('response_result_dts', array('calculated_score' => "N.A."), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
					continue;
				}

				$reportedResultCode = isset($result['result_code']) ? $result['result_code'] : null;
				$reportedSyphilisResultCode = isset($result['syp_result_code']) ? $result['syp_result_code'] : null;
				$reportedSyphilisResult = isset($result['syphilis_final']) ? $result['syphilis_final'] : null;


				// Checking algorithm Pass/Fail only if it is NOT a control.
				if (0 == $result['control']) {
					$syphilisResult = $result1 = $result2 = $result3 = $isRetest = '';
					$repeatResult1 = $repeatResult2 = $repeatResult3 = '';
					if ($syphilisEnabled === true) {
						if ($result['syphilis_result'] == 25) {
							$syphilisResult = 'R';
						} elseif ($result['syphilis_result'] == 26) {
							$syphilisResult = 'NR';
						} elseif ($result['syphilis_result'] == 27) {
							$syphilisResult = 'I';
						} else {
							$syphilisResult = '-';
						}
					}
					if ($result['test_result_1'] == 1) {
						$result1 = 'R';
					} elseif ($result['test_result_1'] == 2) {
						$result1 = 'NR';
					} elseif ($result['test_result_1'] == 3) {
						$result1 = 'I';
					} else {
						$result1 = '-';
					}

					if (isset($result['is_this_retest']) && !empty($result['is_this_retest']) && $result['is_this_retest'] == 'yes') {
						$isRetest = 'yes';
					} else {
						$isRetest = '-';
					}

					if ($result['test_result_2'] == 1) {
						$result2 = 'R';
					} elseif ($result['test_result_2'] == 2) {
						$result2 = 'NR';
					} elseif ($result['test_result_2'] == 3) {
						$result2 = 'I';
					} else {
						$result2 = '-';
					}

					if ($result['repeat_test_result_1'] == 1) {
						$repeatResult1 = 'R';
					} elseif ($result['repeat_test_result_1'] == 2) {
						$repeatResult1 = 'NR';
					} elseif ($result['repeat_test_result_1'] == 3) {
						$repeatResult1 = 'I';
					} else {
						$repeatResult1 = '-';
					}

					if ($result['repeat_test_result_2'] == 1) {
						$repeatResult2 = 'R';
					} elseif ($result['repeat_test_result_2'] == 2) {
						$repeatResult2 = 'NR';
					} elseif ($result['repeat_test_result_2'] == 3) {
						$repeatResult2 = 'I';
					} else {
						$repeatResult2 = '-';
					}

					if (!empty($attributes['algorithm']) && $attributes['algorithm'] != 'myanmarNationalDtsAlgo' && isset($config->evaluation->dts->dtsOptionalTest3) && $config->evaluation->dts->dtsOptionalTest3 == 'yes') {
						$result3 = 'X';
						$repeatResult3 = 'X';
					} else {
						if ($result['test_result_3'] == 1) {
							$result3 = 'R';
						} elseif ($result['test_result_3'] == 2) {
							$result3 = 'NR';
						} elseif ($result['test_result_3'] == 3) {
							$result3 = 'I';
						} else {
							$result3 = '-';
						}
						if ($result['repeat_test_result_3'] == 1) {
							$repeatResult3 = 'R';
						} elseif ($result['repeat_test_result_3'] == 2) {
							$repeatResult3 = 'NR';
						} elseif ($result['repeat_test_result_3'] == 3) {
							$repeatResult3 = 'I';
						} else {
							$repeatResult3 = '-';
						}
					}

					//$algoString = "Wrongly reported in the pattern : <strong>" . $result1 . "</strong> <strong>" . $result2 . "</strong> <strong>" . $result3 . "</strong>";

					$scorePercentageForAlgorithm = 0; // Most countries do not give score for getting algorithm right
					if (isset($shipmentAttributes['screeningTest']) && $shipmentAttributes['screeningTest'] == 'yes') {
						// no algorithm to check
					} elseif (isset($dtsSchemeType) && $dtsSchemeType == 'updated-3-tests') {

						if ($result1 == 'NR' && $reportedResultCode == 'N') {
							if ($result2 == '-' && $result3 == '-' && $repeatResult1 == '-') {
								$algoResult = 'Pass';
							} else {
								$algoResult = 'Fail';
								$failureReason[] = array(
									'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]
								);
								$correctiveActionList[] = 2;
							}
						} elseif ($result1 == 'R') {
							if ($result2 == 'R' && $reportedResultCode == 'P' && $repeatResult1 == '-') {
								$algoResult = 'Pass';
							} elseif ($result2 == 'NR') {
								// if Result 2 is NR then, we go for repeat test 1
								if ($repeatResult1 == 'NR' && $reportedResultCode == 'N') {
									$algoResult = 'Pass';
								} elseif ($repeatResult1 == 'R' && $reportedResultCode == 'I') {
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
						// RTRI Algo Stuff Starts

						$didReportRTRI = (isset($result['dts_rtri_is_editable']) && $result['dts_rtri_is_editable'] == 'yes') ? true : false;
						if ($rtriEnabled && $didReportRTRI) {

							$rtriAlgoResult = '';
							$controlLine = $result['dts_rtri_control_line'];
							$verificationLine = $result['dts_rtri_diagnosis_line'];
							$longtermLine = $result['dts_rtri_longterm_line'];
							$rtriReferenceResult = $result['dts_rtri_reference_result'];
							$rtriReportedResult = $result['dts_rtri_reported_result'];


							// CHECK RTRI Algorithm Correctness
							if (empty($controlLine) && empty($verificationLine) && empty($longtermLine)) {
								$rtriAlgoResult = 'Fail';
							} elseif (empty($controlLine) || $controlLine == 'absent') {
								$rtriAlgoResult = 'Fail';
							}
							// elseif ($verificationLine == 'absent') {
							//     $isAlgoWrong = true;
							// }

							// if final result was expected as Negative
							if ($rtriReferenceResult == $possibleRecencyResults['N']) {
								if ($controlLine == 'present' && $verificationLine == 'absent' && $longtermLine == 'absent') {
								} else {
									$rtriAlgoResult = 'Fail';
								}
							}

							// if final result was expected as Recent
							if ($result['dts_rtri_reference_result'] == $possibleRecencyResults['R']) {
								if ($controlLine == 'present' && $verificationLine == 'present' && $longtermLine == 'absent') {
								} else {
									$rtriAlgoResult = 'Fail';
								}
							}

							// if final result was expected as Long term
							if ($result['dts_rtri_reference_result'] == $possibleRecencyResults['LT']) {
								if ($controlLine == 'present' && $verificationLine == 'present' && $longtermLine == 'present') {
								} else {
									$rtriAlgoResult = 'Fail';
								}
							}
						}

						// RTRI Algo Stuff Ends


					} elseif (isset($attributes['algorithm']) && $attributes['algorithm'] == 'serial') {
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
						} elseif ($result1 == 'R' && $result2 == 'NR' && $result3 == 'NR') {
							$algoResult = 'Pass';
						} elseif ($result1 == 'R' && $result2 == 'R') {
							if (($result3 == 'R' || $result3 == '-' || $result3 == 'X')) {
								$algoResult = 'Pass';
							} else {
								$algoResult = 'Fail';
								$failureReason[] = array(
									'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]
								);
								$correctiveActionList[] = 2;
							}
						} elseif ($result1 == 'R' && $result2 == 'NR' && ($result3 == 'R' || $result3 == 'X')) {
							$algoResult = 'Pass';
						} else {
							$algoResult = 'Fail';
							$failureReason[] = array(
								'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
								'correctiveAction' => $correctiveActions[2]
							);
							$correctiveActionList[] = 2;
						}
					} elseif (isset($attributes['algorithm']) && $attributes['algorithm'] == 'parallel') {

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
						} elseif ($result1 == 'R' && $result2 == 'NR' && ($result3 == 'R' || $result3 == 'X')) {
							$algoResult = 'Pass';
						} elseif ($result1 == 'R' && $result2 == 'NR' && ($result3 == 'NR' || $result3 == 'X')) {
							$algoResult = 'Pass';
						} elseif ($result1 == 'NR' && $result2 == 'NR') {
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
						} elseif ($result1 == 'NR' && $result2 == 'R' && ($result3 == 'NR' || $result3 == 'X')) {
							$algoResult = 'Pass';
						} elseif ($result1 == 'NR' && $result2 == 'R' && ($result3 == 'R' || $result3 == 'X')) {
							$algoResult = 'Pass';
						} else {
							$algoResult = 'Fail';
							$failureReason[] = array(
								'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
								'correctiveAction' => $correctiveActions[2]
							);
							$correctiveActionList[] = 2;
						}
					} elseif ($dtsSchemeType == 'sierraLeone' || $attributes['algorithm'] == 'sierraLeoneNationalDtsAlgo') {


						// array('NXX','PNN','PPX','PNP')

						//$rstring = $result1."-".$result2."-".$result3."-".$reportedResultCode;

						if ($result1 == 'NR' && $result2 == '-' && $result3 == '-' && $reportedResultCode == 'N') {
							$algoResult = 'Pass';
						} elseif ($result1 == 'R' && $result2 == 'R' && $result3 == '-' && $reportedResultCode == 'P') {
							$algoResult = 'Pass';
						} elseif ($result1 == 'R' && $result2 == 'R' && $result3 == '-' && $reportedResultCode == 'R') {
							$algoResult = 'Pass';
						} elseif ($result1 == 'R' && $result2 == 'NR' && $result3 == 'NR' && $reportedResultCode == 'N') {
							$algoResult = 'Pass';
						} elseif ($result1 == 'R' && $result2 == 'NR' && $result3 == 'R' && $reportedResultCode == 'P') {
							$algoResult = 'Pass';
						} elseif ($result1 == 'R' && $result2 == 'NR' && $result3 == 'R' && $reportedResultCode == 'R') {
							$algoResult = 'Pass';
						} elseif (($result1 == 'R' && $result2 == 'R' && $result3 == 'NR' && $reportedResultCode == 'I') || ($result1 == 'R' && $result2 == 'R' && $result3 == 'I' && $reportedResultCode == 'I')) {
							$algoResult = 'Pass';
						} else {
							$algoResult = 'Fail';
							$failureReason[] = array(
								'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
								'correctiveAction' => $correctiveActions[2]
							);
							$correctiveActionList[] = 2;
						}
					} elseif ($dtsSchemeType == 'myanmar' || $attributes['algorithm'] == 'myanmarNationalDtsAlgo') {

						$scorePercentageForAlgorithm = 0.5; // Myanmar gives 50% score for getting algorithm right
						// NR-- => N
						// R-R-R => P
						// R-NR-NR => N
						// R-NR-R => I
						// R-R-NR => I

						//$rstring = $result1."-".$result2."-".$result3."-".$reportedResultCode;

						if ($result1 == 'NR' && $result2 == '-' && $result3 == '-' && $reportedResultCode == 'N') {
							$algoResult = 'Pass';
						} elseif ($result1 == 'R' && $result2 == 'R' && $result3 == 'R' && $reportedResultCode == 'P') {
							$algoResult = 'Pass';
						} elseif ($result1 == 'R' && $result2 == 'R' && $result3 == 'R' && $reportedResultCode == 'R') {
							$algoResult = 'Pass';
						} elseif ($result1 == 'R' && $result2 == 'NR' && $result3 == 'NR' && $reportedResultCode == 'N') {
							$algoResult = 'Pass';
						} elseif ($result1 == 'R' && $result2 == 'NR' && $result3 == 'R' && $reportedResultCode == 'I') {
							$algoResult = 'Pass';
						} elseif (($result1 == 'R' && $result2 == 'R' && $result3 == 'NR' && $reportedResultCode == 'I') || ($result1 == 'R' && $result2 == 'R' && $result3 == 'I' && $reportedResultCode == 'I')) {
							$algoResult = 'Pass';
						} else {
							$algoResult = 'Fail';
							$failureReason[] = array(
								'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
								'correctiveAction' => $correctiveActions[2]
							);
							$correctiveActionList[] = 2;
						}
					} elseif ($dtsSchemeType == 'malawi' || $attributes['algorithm'] == 'malawiNationalDtsAlgo') {

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
						} elseif ($result1 == 'R') {
							if ($result2 == 'R' && $reportedResultCode == 'P' && $repeatResult1 == '-' && $repeatResult2 == '-') {
								$algoResult = 'Pass';
							} elseif ($result2 == 'NR') {
								// if Result 2 is NR then, we go for repeat tests
								if ($repeatResult1 == 'NR' && $repeatResult2 == 'NR' && $reportedResultCode == 'N') {
									$algoResult = 'Pass';
								} elseif ($repeatResult1 == 'R' && $repeatResult2 == 'R' && $reportedResultCode == 'P') {
									$algoResult = 'Pass';
								} elseif ($repeatResult1 == 'R' && $repeatResult2 == 'NR' && $reportedResultCode == 'I') {
									$algoResult = 'Pass';
								} elseif ($repeatResult1 == 'NR' && $repeatResult2 == 'N' && $reportedResultCode == 'I') {
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
					} elseif ($dtsSchemeType == 'ghana') {

						if ($syphilisEnabled == true) {
							if ($syphilisResult == 'R' && $reportedSyphilisResultCode == 'P') {
								$sypAlgoResult = 'Pass';
							} elseif ($syphilisResult == 'NR' && $reportedSyphilisResultCode == 'N') {
								$sypAlgoResult = 'Pass';
							} else {
								$sypAlgoResult = 'Fail';
							}
						}

						if ($result1 == 'NR' && $reportedResultCode == 'N') {
							if (($result2 == '-' && $result3 == '-')) {
								$algoResult = 'Pass';
							} else {
								$algoResult = 'Fail';
								$failureReason[] = array(
									'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
									'correctiveAction' => $correctiveActions[2]
								);
								$correctiveActionList[] = 2;
							}
						} elseif ($result1 == 'R') {
							if ($result2 == 'R' && $result3 == 'R' && $reportedResultCode == 'P') {
								$algoResult = 'Pass';
							} elseif ($result2 == 'NR') {
								// if Result 2 is NR then, we go for repeat tests
								if ($repeatResult1 == 'NR' && $repeatResult2 == 'NR' && $reportedResultCode == 'N') {
									$algoResult = 'Pass';
								} elseif ($repeatResult1 == 'R' && $repeatResult2 == 'R' && $reportedResultCode == 'P') {
									$algoResult = 'Pass';
								} elseif ($repeatResult1 == 'R' && $repeatResult2 == 'NR' && $reportedResultCode == 'I') {
									$algoResult = 'Pass';
								} elseif ($repeatResult1 == 'NR' && $repeatResult2 == 'N' && $reportedResultCode == 'I') {
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
							//echo $algoResult;die;
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




				// If final HIV result was not reported then the participant is failed
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
								$failureReason[] = array(
									'warning' => "<strong>" . $result['sample_label'] . "</strong> - Reported Syphilis result does not match the expected result",
									'correctiveAction' => "Final interpretation not matching with the expected result. Please review the SOP and/or job aide to ensure test procedures are followed and interpretation of results are reported accurately."
								);
							}
						}

						// Keeping this as always true so that even for the
						// non-RTRI samples scores can be calculated
						$correctRTRIResponse = true;
						if ($rtriEnabled && $didReportRTRI) {
							if ($rtriReportedResult == $rtriReferenceResult) {
								if ($rtriAlgoResult != 'Fail') {
									$correctRTRIResponse = true;
								} else {
									$correctRTRIResponse = false;
								}
							} else {
								$correctRTRIResponse = false;
								$failureReason[] = array(
									'warning' => "<strong>" . $result['sample_label'] . "</strong> - Reported RTRI result does not match the expected result",
									'correctiveAction' => "Final interpretation not matching with the expected result. Please review the RTRI SOP and/or job aide to ensure test procedures are followed and  interpretation of results are reported accurately."
								);
							}
						}


						$assumedFinalHivResult = 0;
						// Even if participants report HIV Diagnosis incorrectly, we will check if they reported
						// correctly for RTRI Diagnosis. If they did, then we will pass them with a warning
						if ($rtriEnabled && $didReportRTRI) {
							if ($verificationLine == 'present') {
								$assumedFinalHivResult = 4; // POSITIVE = 4 from r_possibleresult
							}
							if ($verificationLine == 'absent') {
								$assumedFinalHivResult = 5; // NEGATIVE = 4 from r_possibleresult
							}
						}

						if ($result['reference_result'] == $result['reported_result']) {
							if ($correctRTRIResponse && $correctSyphilisResponse && $algoResult != 'Fail') {
								$totalScore += ($scoreForSample + $scoreForAlgorithm);
								$correctResponse = true;
							} elseif ($correctRTRIResponse && $correctSyphilisResponse && ($scorePercentageForAlgorithm > 0 && $algoResult == 'Fail')) {
								$totalScore += $scoreForSample;
								$correctResponse = false;
							} else {
								// $totalScore remains the same	if algoResult == fail and there is no allocated score for algo
								$correctResponse = false;
							}
						} elseif ($result['reference_result'] == $assumedFinalHivResult) {
							if ($correctRTRIResponse && $correctSyphilisResponse && $algoResult != 'Fail') {
								$totalScore += ($scoreForSample + $scoreForAlgorithm);
								$correctResponse = true;
								$failureReason[] = array(
									'warning' => "<strong>" . $result['sample_label'] . "</strong> - Reported HIV result does not match the expected result. Passed with warning.",
									'correctiveAction' => $correctiveActions[3]
								);
							} elseif ($correctRTRIResponse && $correctSyphilisResponse && ($scorePercentageForAlgorithm > 0 && $algoResult == 'Fail')) {
								$totalScore += $scoreForSample;
								$correctResponse = false;
							} else {
								// $totalScore remains the same	if algoResult == fail and there is no allocated score for algo
								$correctResponse = false;
							}
						} else {
							if ($result['sample_score'] > 0) {

								// In some countries, they allow partial score for algorithms
								// So even if the participant got the final result wrong,
								// they still get some points for the Algorithm
								if ($algoResult != 'Fail') {
									$totalScore += ($scoreForAlgorithm);
								}


								$failureReason[] = array(
									'warning' => "<strong>" . $result['sample_label'] . "</strong> - Reported HIV result does not match the expected result",
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

				if (isset($result['test_result_1']) && !empty($result['test_result_1']) && trim($result['test_result_1']) != false && trim($result['test_result_1']) != '24') {
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
				if (isset($result['test_result_2']) && !empty($result['test_result_2']) && trim($result['test_result_2']) != false && trim($result['test_result_2']) != '24') {
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
				if (isset($result['test_result_3']) && !empty($result['test_result_3']) && trim($result['test_result_3']) != false && trim($result['test_result_3']) != '24') {
					//T.1 Ensure test kit name is reported for all performed tests.
					if ($testKit3 == "") {
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
					$this->db->update('response_result_dts', array('calculated_score' => "Fail"), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
				} else {
					$this->db->update('response_result_dts', array('calculated_score' => "Pass"), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
				}
			}



			$configuredDocScore = ((isset($config->evaluation->dts->documentationScore) && (int) $config->evaluation->dts->documentationScore > 0) ? $config->evaluation->dts->documentationScore : 0);

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
				if ($dtsSchemeType == 'myanmar' ||   $attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
					$totalDocumentationItems -= 1;
				}
			}

			if ($dtsSchemeType == 'malawi' || $attributes['algorithm'] == 'malawiNationalDtsAlgo') {
				// For Malawi we have 4 more documentation items to consider - Sample Condition, Fridge, Stop Watch and Room Temp
				$totalDocumentationItems += 4;
			}

			$docScore = $config->evaluation->dts->documentationScore ?? 0;
			$documentationScorePerItem = ($docScore > 0) ? round($docScore / $totalDocumentationItems, 2) : 0;

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
				$sampleRehydrateDays = null;
				if (!empty($attributes['sample_rehydration_date'])) {
					$sampleRehydrationDate = new DateTime($attributes['sample_rehydration_date']);
					$testedOnDate = new DateTime($results[0]['shipment_test_date']);
					$interval = $sampleRehydrationDate->diff($testedOnDate);
					$sampleRehydrateDays = $config->evaluation->dts->sampleRehydrateDays;
				}


				//$rehydrateHours = $sampleRehydrateDays * 24;

				// we can allow testers to test upto sampleRehydrateDays or sampleRehydrateDays + 1
				if (empty($attributes['sample_rehydration_date']) || empty($sampleRehydrateDays) || $interval->days < $sampleRehydrateDays || $interval->days > ($sampleRehydrateDays + 1)) {
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

			if ($dtsSchemeType == 'malawi' || $attributes['algorithm'] == 'malawiNationalDtsAlgo') {
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
			if ($shipment['is_excluded'] == 'yes' || $shipment['is_pt_test_not_performed'] == 'yes') {
				$finalResult = '';
				$shipment['is_excluded'] = 'yes';
				$shipment['is_followup'] = 'yes';
				$shipmentResult[$counter]['shipment_score'] = $responseScore = 0;
				$shipmentResult[$counter]['documentation_score'] = 0;
				$shipmentResult[$counter]['display_result'] = '';
				$failureReason[] = array('warning' => 'Excluded from Evaluation');
				$finalResult = 3;
				$shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);
			} else {
				$shipment['is_excluded'] = 'no';
				// if any of the results have failed, then the final result is fail
				if ($algoResult == 'Fail' || $scoreResult == 'Fail' || $lastDateResult == 'Fail' || $mandatoryResult == 'Fail' || $lotResult == 'Fail' || $testKitExpiryResult == 'Fail') {
					$finalResult = 2;
					$shipmentResult[$counter]['is_followup'] = 'yes';
					$shipment['is_followup'] = 'yes';
				} else {
					$shipment['is_excluded'] = 'no';
					$shipment['is_followup'] = 'no';
					$finalResult = 1;
				}
				$shipmentResult[$counter]['shipment_score'] = $responseScore;
				$shipmentResult[$counter]['documentation_score'] = $documentationScore;
				$scoreHolder[$shipment['map_id']] = $responseScore + $documentationScore;


				$shipmentResult[$counter]['display_result'] = $finalResultArray[$finalResult];
				$shipmentResult[$counter]['failure_reason'] = $failureReason = (isset($failureReason) && count($failureReason) > 0) ? json_encode($failureReason) : "";
				//$shipmentResult[$counter]['corrective_actions'] = implode(",",$correctiveActionList);
			}

			$shipmentResult[$counter]['max_score'] = $maxScore;
			$shipmentResult[$counter]['final_result'] = $finalResult;
			if ($shipment['is_excluded'] != 'yes' && $shipment['is_pt_test_not_performed'] != 'yes') {

				/* Manual result override changes */
				if (isset($shipment['manual_override']) && $shipment['manual_override'] == 'yes') {
					$sql = $this->db->select()->from('shipment_participant_map')->where("map_id = ?", $shipment['map_id']);
					$shipmentOverall = $this->db->fetchRow($sql);
					if (!empty($shipmentOverall)) {
						$shipmentResult[$counter]['shipment_score'] = $shipmentOverall['shipment_score'];
						$shipmentResult[$counter]['documentation_score'] = $shipmentOverall['documentation_score'];
						if (!isset($shipmentOverall['final_result']) || $shipmentOverall['final_result'] == "") {
							$shipmentOverall['final_result'] = 2;
						}

						$shipmentResult[$counter]['display_result'] = $finalResultArray[$shipmentOverall['final_result']];
						// Zend_Debug::dump($shipmentResult);die;
						$nofOfRowsUpdated = $this->db->update('shipment_participant_map', array('shipment_score' => $shipmentOverall['shipment_score'], 'documentation_score' => $shipmentOverall['documentation_score'], 'final_result' => $shipmentOverall['final_result']), "map_id = " . $shipment['map_id']);
					}
				} else {
					// let us update the total score in DB
					$nofOfRowsUpdated = $this->db->update(
						'shipment_participant_map',
						array(
							'shipment_score' => $responseScore,
							'documentation_score' => $documentationScore,
							'final_result' => $finalResult,
							'is_followup' => $shipment['is_followup'],
							'is_excluded' => $shipment['is_excluded'],
							'failure_reason' => $failureReason,
							'is_response_late' => $shipment['is_response_late']
						),
						'map_id = ' . $shipment['map_id']
					);
				}
			}
			$nofOfRowsDeleted = $this->db->delete('dts_shipment_corrective_action_map', "shipment_map_id = " . $shipment['map_id']);
			if ($shipment['is_excluded'] != 'yes' && $shipment['is_pt_test_not_performed'] != 'yes') {
				$correctiveActionList = array_unique($correctiveActionList);
				foreach ($correctiveActionList as $ca) {
					$this->db->insert('dts_shipment_corrective_action_map', array('shipment_map_id' => $shipment['map_id'], 'corrective_action_id' => $ca));
				}
			}

			$counter++;
		}

		if (!empty($scoreHolder)) {
			$averageScore = round(array_sum($scoreHolder) / count($scoreHolder), 2);
		} else {
			$averageScore = 0;
		}

		//die('here');
		if ($shipment['is_excluded'] == 'yes' && $shipment['is_pt_test_not_performed'] == 'yes') {
			$this->db->update('shipment', array('max_score' => 0, 'average_score' => 0, 'status' => 'not-evaluated'), "shipment_id = " . $shipmentId);
		} else {
			$this->db->update('shipment', array('max_score' => $maxScore, 'average_score' => $averageScore, 'status' => 'evaluated'), "shipment_id = " . $shipmentId);
		}
		return $shipmentResult;
	}

	public function getDtsSamples($sId, $pId = null)
	{
		$sql = $this->db->select()->from(array('ref' => 'reference_result_dts'))
			->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
			->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
			->joinLeft(array('res' => 'response_result_dts'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array(
				'test_kit_name_1',
				'lot_no_1',
				'exp_date_1',
				'test_result_1',
				'syphilis_result',
				'test_kit_name_2',
				'lot_no_2',
				'exp_date_2',
				'test_result_2',
				'test_kit_name_3',
				'lot_no_3',
				'exp_date_3',
				'test_result_3',
				'repeat_test_kit_name_1',
				'repeat_test_kit_name_2',
				'repeat_test_kit_name_3',
				'repeat_lot_no_1',
				'repeat_lot_no_2',
				'repeat_lot_no_3',
				'repeat_exp_date_1',
				'repeat_exp_date_2',
				'repeat_exp_date_3',
				'repeat_test_result_1',
				'repeat_test_result_2',
				'repeat_test_result_3',
				'reported_result',
				'syphilis_final',
				'dts_rtri_control_line',
				'dts_rtri_diagnosis_line',
				'dts_rtri_longterm_line',
				'dts_rtri_reported_result',
				'dts_rtri_is_editable'
			))
			->joinLeft(array('rp' => 'r_possibleresult'), 'rp.id = res.reported_result', array('result_code'))
			->joinLeft(array('srp' => 'r_possibleresult'), 'srp.id = res.syphilis_final', array('syp_result_code' => 'result_code'))
			->joinLeft(array('rtri_rp' => 'r_possibleresult'), 'rtri_rp.id = res.dts_rtri_reported_result', array('rtri_result_code' => 'result_code'))
			->where('sp.shipment_id = ? ', $sId);
		if (!empty($pId)) {
			$sql = $sql->where('sp.participant_id = ? ', $pId);
		}

		return $this->db->fetchAll($sql);
	}

	public function getRecommededDtsTestkits($testMode = 'dts', $testNumber = null)
	{
		$sql = $this->db->select()->from(array('dts_recommended_testkits'));

		if ($testNumber != null && (int) $testNumber > 0 && (int) $testNumber <= 3) {
			$sql = $sql->where('test_no = ' . (int) $testNumber);
		}

		if ($testMode != null) {
			$sql = $sql->where("dts_test_mode = '$testMode'");
		}

		$stmt = $this->db->fetchAll($sql);
		$retval = [];
		foreach ($stmt as $t) {
			$retval[$t['test_no']][] = $t['testkit'];
		}
		return $retval;
	}

	public function getAllDtsTestKitList($countryAdapted = false)
	{

		$sql = $this->db->select()
			->from(
				array('r_testkitname_dts'),
				array(
					'TESTKITNAMEID' => 'TESTKITNAME_ID',
					'TESTKITNAME' => 'TESTKIT_NAME',
					'testkit_1',
					'testkit_2',
					'testkit_3'
				)
			)
			->where("scheme_type = 'dts'")
			->order("TESTKITNAME ASC");

		if ($countryAdapted) {
			$sql = $sql->where('COUNTRYADAPTED = 1');
		}
		$stmt = $this->db->fetchAll($sql);

		return $stmt;
	}


	public function getDtsCorrectiveActions()
	{
		$res = $this->db->fetchAll($this->db->select()->from('r_dts_corrective_actions'));
		$response = [];
		foreach ($res as $row) {
			$response[$row['action_id']] = $row['corrective_action'];
		}
		return $response;
	}

	public function generateDtsRapidHivExcelReport($shipmentId)
	{

		ini_set('memory_limit', '-1');
		ini_set('max_execution_time', '30000');

		$db = Zend_Db_Table_Abstract::getDefaultAdapter();

		$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
		$config = new Zend_Config_Ini($file, APPLICATION_ENV);

		$excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		//$sheet = $excel->getActiveSheet();

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

		$borderStyle = array(
			'alignment' => array(
				'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
			),
			'borders' => array(
				'outline' => array(
					'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
				),
			)
		);

		$query = $db->select()
			->from('shipment', ['shipment_id', 'shipment_code', 'scheme_type', 'number_of_samples', 'number_of_controls'])
			->where("shipment_id = ?", $shipmentId);
		$result = $db->fetchRow($query);

		if ($result['scheme_type'] == 'dts') {

			$refQuery = $db->select()->from(array('refRes' => 'reference_result_dts'), array('refRes.sample_label', 'sample_id', 'refRes.sample_score'))
				->joinLeft(array('r' => 'r_possibleresult'), 'r.id=refRes.reference_result', array('referenceResult' => 'r.response'))
				->where("refRes.shipment_id = ?", $shipmentId);
			$refResult = $db->fetchAll($refQuery);
			if (!empty($refResult)) {
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

		//<------------ Participant List Details Start -----

		$headings = array('Participant Code', 'Participant Name',  'Institute Name', 'Department', 'Country', 'Address', 'Province', 'District', 'City', 'Facility Telephone', 'Email');

		$sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Participant List');
		$excel->addSheet($sheet, 0);
		$sheet->setTitle('Participant List', true);

		$sql = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.number_of_samples', 's.number_of_controls', 'shipment_attributes'))
			->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('sp.map_id', 'sp.participant_id', 'sp.attributes', 'sp.shipment_test_date', 'sp.shipment_receipt_date', 'sp.shipment_test_report_date', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_score', 'sp.documentation_score', 'sp.final_result', 'sp.is_excluded', 'sp.failure_reason', 'sp.user_comment', 'sp.custom_field_1', 'sp.custom_field_2'))
			->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.lab_name', 'p.region', 'p.first_name', 'p.last_name', 'p.address', 'p.city', 'p.mobile', 'p.email', 'p.status', 'province' => 'p.state', 'p.district'))
			->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array('pmm.dm_id'))
			->joinLeft(array('dm' => 'data_manager'), 'dm.dm_id=pmm.dm_id', array('dm.institute', 'dataManagerFirstName' => 'dm.first_name', 'dataManagerLastName' => 'dm.last_name'))
			->joinLeft(array('c' => 'countries'), 'c.id=p.country', array('iso_name'))
			->joinLeft(array('st' => 'r_site_type'), 'st.r_stid=p.site_type', array('st.site_type'))
			->joinLeft(array('en' => 'enrollments'), 'en.participant_id=p.participant_id', array('en.enrolled_on'))
			->where("s.shipment_id = ?", $shipmentId)
			->group(array('sp.map_id'));
		$authNameSpace = new Zend_Session_Namespace('datamanagers');
		if (!empty($authNameSpace->dm_id)) {
			$sql = $sql
				->where("pmm.dm_id = ?", $authNameSpace->dm_id);
		}
		$shipmentResult = $db->fetchAll($sql);
		//die;
		$colNo = 0;
		$currentRow = 1;


		foreach ($headings as $field => $value) {
			$sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow, html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
			$sheet->getStyle($colNo + 1, $currentRow, null, null)->getFont()->setBold(true);
			$sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow)
				->applyFromArray($borderStyle, true);
			$colNo++;
		}

		if (isset($shipmentResult) && !empty($shipmentResult)) {
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
						->joinLeft(array('rtrifr' => 'r_possibleresult'), 'rtrifr.id=rrdts.dts_rtri_reported_result', array('rtrifinalResult' => 'rtrifr.response'))
						->where("rrdts.shipment_map_id = ?", $aRow['map_id']);
					$shipmentResult[$key]['response'] = $db->fetchAll($resQuery);
				}


				$sheet->setCellValue('A' . $currentRow, ($aRow['unique_identifier']));
				$sheet->setCellValue('B' . $currentRow, $aRow['first_name'] . ' ' . $aRow['last_name']);
				$sheet->setCellValue('C' . $currentRow, $aRow['institute_name']);
				$sheet->setCellValue('D' . $currentRow, $aRow['department_name']);
				$sheet->setCellValue('E' . $currentRow, ucwords($aRow['iso_name']));
				$sheet->setCellValue('F' . $currentRow, $aRow['address']);
				$sheet->setCellValue('G' . $currentRow, $aRow['province']);
				$sheet->setCellValue('H' . $currentRow, $aRow['district']);
				$sheet->setCellValue('I' . $currentRow, $aRow['city']);
				$sheet->setCellValue('J' . $currentRow, $aRow['mobile']);
				$sheet->setCellValue('K' . $currentRow, strtolower($aRow['email']));

				$sheet->getStyle('A' . $currentRow . ":" . 'K' . $currentRow)->applyFromArray($borderStyle, true);


				$currentRow++;
				$shipmentCode = $aRow['shipment_code'];
			}
		}

		//------------- Participant List Details End ------>
		//<-------- Second sheet start
		$reportHeadings = array('Participant Code', 'Participant Name', 'Institute Name', 'Province', 'District', 'Shipment Receipt Date', 'Sample Rehydration Date', 'Testing Date', 'Reported On', 'Test#1 Name', 'Kit Lot #', 'Expiry Date');
		if ((isset($config->evaluation->dts->displaySampleConditionFields) && $config->evaluation->dts->displaySampleConditionFields == "yes")) {
			$reportHeadings = array('Participant Code', 'Participant Name', 'Institute Name', 'Province', 'District', 'Shipment Receipt Date', 'Testing Date', 'Reported On', 'Condition Of PT Samples', 'Refridgerator', 'Room Temperature', 'Stop Watch', 'Test#1 Name', 'Kit Lot #', 'Expiry Date');
		}
		if ($result['scheme_type'] == 'dts') {
			$rtrishipmentAttributes = json_decode($shipmentResult[0]['shipment_attributes'], true);

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
			// For RTRI and test results final result
			if (isset($rtrishipmentAttributes['enableRtri']) && $rtrishipmentAttributes['enableRtri'] == 'yes') {
				foreach (range(1, $result['number_of_samples']) as $key => $row) {
					$reportHeadings[] = "Is Editable";
				}
				$reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
				$reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
				$reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
				$reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
			}
			$globalConfigDb = new Application_Model_DbTable_GlobalConfig();
			$customField1 = $globalConfigDb->getValue('custom_field_1');
			$customField2 = $globalConfigDb->getValue('custom_field_2');
			$haveCustom = $globalConfigDb->getValue('custom_field_needed');

			if (isset($haveCustom) && $haveCustom == 'yes') {

				if (isset($customField1) && $customField1 != "") {
					array_push($reportHeadings, $customField1);
				}
				if (isset($customField2) && $customField2 != "") {
					array_push($reportHeadings, $customField2);
				}
			}
			array_push($reportHeadings, 'Comments');
		}
		$sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Results Reported');
		$excel->addSheet($sheet, 1);
		$sheet->setTitle('Results Reported', true);
		$sheet->getDefaultColumnDimension()->setWidth(24);
		$sheet->getDefaultRowDimension()->setRowHeight(18);

		$colNo = 0;
		$repeatCellNo = 0;
		$rtriCellNo = 0;
		$currentRow = 2;
		$n = count($reportHeadings);
		if (isset($rtrishipmentAttributes['enableRtri']) && $rtrishipmentAttributes['enableRtri'] == 'yes') {
			$rCount = 14 + ($result['number_of_samples'] * 2);
			if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
				$rCount = 17 + ($result['number_of_samples'] * 3);
			}
			$finalResColoumn = $rCount;
		} else {
			$finalResColoumn = $n - ($result['number_of_samples'] + $result['number_of_controls'] + 1);
		}

		$c = 1;
		$z = 1;
		$repeatCell = 1;
		$rtriCell = 1;
		if (isset($rtrishipmentAttributes['enableRtri']) && $rtrishipmentAttributes['enableRtri'] == 'yes') {
			$endMergeCell = ($finalResColoumn + $result['number_of_samples'] + $result['number_of_controls']) - 2;
		} else {
			$endMergeCell = ($finalResColoumn + $result['number_of_samples'] + $result['number_of_controls']) - 2;
		}

		/* Final result merge section */
		$firstCellName = $sheet->getCellByColumnAndRow($finalResColoumn, 1)->getColumn();
		$secondCellName = $sheet->getCellByColumnAndRow($endMergeCell + 1, 1)->getColumn();
		$sheet->mergeCells($firstCellName . "1:" . $secondCellName . "1");
		$sheet->getStyle($firstCellName . "1")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
		$sheet->getStyle($firstCellName . "1")->applyFromArray($borderStyle, true);
		$sheet->getStyle($secondCellName . "1")->applyFromArray($borderStyle, true);
		/* RTRI Panel section */
		if (isset($rtrishipmentAttributes['enableRtri']) && $rtrishipmentAttributes['enableRtri'] == 'yes') {
			$rtriHeadingColumn = $endMergeCell + 2;
			$endRtriMergeCell = $endMergeCell + ($result['number_of_samples'] * 4) + 1;
			$rtriFirstCellName = $sheet->getCellByColumnAndRow($rtriHeadingColumn, 1)->getColumn();
			$rtriSecondCellName = $sheet->getCellByColumnAndRow($endRtriMergeCell, 1)->getColumn();
			$sheet->mergeCells($rtriFirstCellName . "1:" . $rtriSecondCellName . "1");
			$sheet->getStyle($rtriFirstCellName . "1")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFAB00');
			$sheet->getStyle($rtriFirstCellName . "1")->applyFromArray($borderStyle, true);
			$sheet->getStyle($rtriSecondCellName . "1")->applyFromArray($borderStyle, true);
			/* RTRI Final result merge section */
			$rtriFirstCellName = $sheet->getCellByColumnAndRow($endRtriMergeCell + 1, 1)->getColumn();
			$rtriSecondCellName = $sheet->getCellByColumnAndRow($n - 1, 1)->getColumn();
			$sheet->mergeCells($rtriFirstCellName . "1:" . $rtriSecondCellName . "1");
			$sheet->getStyle($rtriFirstCellName . "1")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
			$sheet->getStyle($rtriFirstCellName . "1")->applyFromArray($borderStyle, true);
			$sheet->getStyle($rtriSecondCellName . "1")->applyFromArray($borderStyle, true);
		}
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
			$repeatFirstCellName = $sheet->getCellByColumnAndRow($repeatHeadingColumn + 1, 1)->getColumn();
			$repeatSecondCellName = $sheet->getCellByColumnAndRow($endRepeatMergeCell + 1, 1)->getColumn();
			$sheet->mergeCells($repeatFirstCellName . "1:" . $repeatSecondCellName . "1");
			$sheet->getStyle($repeatFirstCellName . "1")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
			$sheet->getStyle($repeatFirstCellName . "1")->applyFromArray($borderStyle, true);
			$sheet->getStyle($repeatSecondCellName . "1")->applyFromArray($borderStyle, true);
		}

		foreach ($reportHeadings as $field => $value) {
			$sheet->getCellByColumnAndRow($colNo + 1, $currentRow)->setValueExplicit(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8'));
			$sheet->getStyleByColumnAndRow($colNo + 1, $currentRow, null, null)->getFont()->setBold(true);
			$cellName = $sheet->getCellByColumnAndRow($colNo + 1, $currentRow)->getColumn();
			$sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle, true);

			$cellName = $sheet->getCellByColumnAndRow($colNo + 1, 3)->getColumn();
			$sheet->getStyle($cellName . "3")->applyFromArray($borderStyle, true);

			$sheet->getStyle($cellName . '3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFA0A0A0');
			$sheet->getStyle($cellName . '3')->getFont()->getColor()->setARGB('FFFFFF00');
			/* Repeat test section */
			if (isset($config->evaluation->dts->allowRepeatTests) && $config->evaluation->dts->allowRepeatTests == 'yes') {
				if ($repeatCellNo >= $repeatHeadingColumn) {
					if ($repeatCell <= ($result['number_of_samples'] + $result['number_of_controls'])) {
						$sheet->getCellByColumnAndRow($repeatCellNo + 1, 1)->setValueExplicit(html_entity_decode("Repeat Tests", ENT_QUOTES, 'UTF-8'));
						$cellName = $sheet->getCellByColumnAndRow($repeatCellNo + 1, $currentRow)->getColumn();
						$sheet->getStyle($cellName . $currentRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
					}
					$repeatCell++;
				}
				$repeatCellNo++;
			}
			/* RTRI panel section */
			if (isset($rtrishipmentAttributes['enableRtri']) && $rtrishipmentAttributes['enableRtri'] == 'yes') {
				if (($rtriCellNo >= $rtriHeadingColumn)) {
					if ($rtriCell <= ($result['number_of_samples'] + $result['number_of_controls'])) {
						$sheet->getCellByColumnAndRow($rtriCellNo, 1)->setValueExplicit(html_entity_decode("RTRI Panel", ENT_QUOTES, 'UTF-8'));
						$cellName = $sheet->getCellByColumnAndRow($rtriCellNo, 1)->getColumn();
					}
					$rtriCell++;
				}
				$rtriCellNo++;
			}
			if ($colNo >= $finalResColoumn) {
				// die($colNo);
				if ($c <= ($result['number_of_samples'] + $result['number_of_controls'])) {
					$sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode("Final Results", ENT_QUOTES, 'UTF-8'));
					$cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
					$sheet->getStyle($cellName . $currentRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
					$l = $c - 1;
					$sheet->getCellByColumnAndRow($colNo, 3)->setValueExplicit(html_entity_decode($refResult[$l]['referenceResult'], ENT_QUOTES, 'UTF-8'));
				}
				$c++;
			}

			/* RTRI Final Result Section */
			if (isset($rtrishipmentAttributes['enableRtri']) && $rtrishipmentAttributes['enableRtri'] == 'yes') {
				if ($colNo >= ($endRtriMergeCell + 1)) {
					if ($z <= ($result['number_of_samples'] + $result['number_of_controls'])) {
						$sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode("RTRI Final Results", ENT_QUOTES, 'UTF-8'));
						$cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
						$sheet->getStyle($cellName . $currentRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
						$l = $z - 1;
						$sheet->getCellByColumnAndRow($colNo, 3)->setValueExplicit(html_entity_decode($refResult[$l]['referenceResult'], ENT_QUOTES, 'UTF-8'));
					}
					$z++;
				}
			}
			$colNo++;
		}
		$sheet->getStyle("A2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
		$sheet->getStyle("B2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
		$sheet->getStyle("C2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
		$sheet->getStyle("D2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');

		//$sheet->getStyle("D2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');
		//$sheet->getStyle("E2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');
		//$sheet->getStyle("F2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');

		$cellName = $sheet->getCellByColumnAndRow($n + 1, 3)->getColumn();
		//$sheet->getStyle('A3:'.$cellName.'3')->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#969696');
		//$sheet->getStyle('A3:'.$cellName.'3')->applyFromArray($borderStyle);
		//<-------- Sheet three heading -------
		$sheetThree = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Panel Score');
		$excel->addSheet($sheetThree, 2);
		$sheetThree->setTitle('Panel Score', true);
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
			$sheetThree->getCellByColumnAndRow($sheetThreeColNo + 1, $sheetThreeRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
			$sheetThree->getStyleByColumnAndRow($sheetThreeColNo + 1, $sheetThreeRow, null, null)->getFont()->setBold(true);
			$cellName = $sheetThree->getCellByColumnAndRow($sheetThreeColNo + 1, $sheetThreeRow)->getColumn();
			$sheetThree->getStyle($cellName . $sheetThreeRow)->applyFromArray($borderStyle, true);

			if ($sheetThreeHK > 1 && $sheetThreeHK <= $sheetThreeColor) {
				$cellName = $sheetThree->getCellByColumnAndRow($sheetThreeColNo + 1, $sheetThreeRow)->getColumn();
				$sheetThree->getStyle($cellName . $sheetThreeRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
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
			if (empty($shipmentAttributes['sampleType']) || $shipmentAttributes['sampleType'] === 'dried') {
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

		$docScore = $config->evaluation->dts->documentationScore ?? 0;
		$documentationScorePerItem = ($docScore > 0) ? round($docScore / $totalDocumentationItems, 2) : 0;


		$docScoreSheet = new Worksheet($excel, 'Documentation Score');
		$excel->addSheet($docScoreSheet, 3);
		$docScoreSheet->setTitle('Documentation Score', true);
		$docScoreSheet->getDefaultColumnDimension()->setWidth(20);
		//$docScoreSheet->getDefaultRowDimension()->setRowHeight(20);
		$docScoreSheet->getDefaultRowDimension()->setRowHeight(25);

		$docScoreHeadings = array('Participant Code', 'Participant Name', 'Supervisor signature', 'Panel Receipt Date', 'Sample Rehydration Date', 'Tested Date', 'Rehydration Test In Specified Time', 'Documentation Score %');

		$docScoreSheetCol = 0;
		$docScoreRow = 1;
		$docScoreHeadingsCount = count($docScoreHeadings);
		foreach ($docScoreHeadings as $sheetThreeHK => $value) {
			$docScoreSheet->getCellByColumnAndRow($docScoreSheetCol + 1, $docScoreRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
			$docScoreSheet->getStyleByColumnAndRow($docScoreSheetCol + 1, $docScoreRow, null, null)->getFont()->setBold(true);
			$cellName = $docScoreSheet->getCellByColumnAndRow($docScoreSheetCol + 1, $docScoreRow)->getColumn();
			$docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle, true);
			$docScoreSheet->getStyleByColumnAndRow($docScoreSheetCol + 1, $docScoreRow, null, null)->getAlignment()->setWrapText(true);
			$docScoreSheetCol++;
		}
		$docScoreRow = 2;
		/* $secondRowcellName = $docScoreSheet->getCellByColumnAndRow(2, $docScoreRow);
        $secondRowcellName->setValueExplicit(html_entity_decode("Points Breakdown", ENT_QUOTES, 'UTF-8'));
        $docScoreSheet->getStyleByColumnAndRow(2, $docScoreRow, null, null)->getFont()->setBold(true);
        $cellName = $secondRowcellName->getColumn();
        $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle, true); */

		for ($r = 2; $r <= 7; $r++) {

			$secondRowcellName = $docScoreSheet->getCellByColumnAndRow($r + 1, $docScoreRow);
			if ($r != 7) {
				$secondRowcellName->setValueExplicit(html_entity_decode($documentationScorePerItem, ENT_QUOTES, 'UTF-8'));
			}
			$docScoreSheet->getStyleByColumnAndRow($r + 1, $docScoreRow, null, null)->getFont()->setBold(true);
			$cellName = $secondRowcellName->getColumn();
			$docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle, true);
		}

		//---------- Document Score Sheet Heading (Sheet Four)------->
		//<-------- Total Score Sheet Heading (Sheet Four)-------


		$totalScoreSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Total Score');
		$excel->addSheet($totalScoreSheet, 4);
		$totalScoreSheet->setTitle('Total Score', true);
		$totalScoreSheet->getDefaultColumnDimension()->setWidth(20);
		$totalScoreSheet->getDefaultRowDimension()->setRowHeight(30);
		$totalScoreHeadings = array('Participant Code', 'Participant Name', 'Province', 'District', 'City', 'Country', 'No. of Panels Correct (N=' . $result['number_of_samples'] . ')', 'Panel Score', 'Documentation Score', 'Total Score', 'Overall Performance', 'Warnings and/or Reasons for Failure');

		$totScoreSheetCol = 0;
		$totScoreRow = 1;
		$totScoreHeadingsCount = count($totalScoreHeadings);
		foreach ($totalScoreHeadings as $sheetThreeHK => $value) {
			$totalScoreSheet->getCellByColumnAndRow($totScoreSheetCol + 1, $totScoreRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
			$totalScoreSheet->getStyleByColumnAndRow($totScoreSheetCol + 1, $totScoreRow, null, null)->getFont()->setBold(true);
			$cellName = $totalScoreSheet->getCellByColumnAndRow($totScoreSheetCol + 1, $totScoreRow)->getColumn();
			$totalScoreSheet->getStyle($cellName . $totScoreRow)->applyFromArray($borderStyle, true);
			$totalScoreSheet->getStyleByColumnAndRow($totScoreSheetCol + 1, $totScoreRow, null, null)->getAlignment()->setWrapText(true);
			$totScoreSheetCol++;
		}

		//---------- Document Score Sheet Heading (Sheet Four)------->

		$ktr = 9;
		$kitId = 7; //Test Kit coloumn count
		if (isset($refResult) && !empty($refResult)) {
			foreach ($refResult as $keyv => $row) {
				$keyv = $keyv + 1;
				$ktr = $ktr + $keyv;
				if (count($row['kitReference']) > 0) {

					if ($keyv == 1) {
						//In Excel Third row added the Test kit name1,kit lot,exp date
						if (trim($row['kitReference'][0]['expiry_date']) != "") {
							$row['kitReference'][0]['expiry_date'] = Pt_Commons_General::excelDateFormat($row['kitReference'][0]['expiry_date']);
						}
						$sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][0]['testKitName']);
						$sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][0]['lot_no']);
						$sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][0]['expiry_date']);

						$kitId = $kitId + $aRow['number_of_samples'] + $aRow['number_of_controls'];
						if (isset($row['kitReference'][1]['referenceKitResult'])) {
							//In Excel Third row added the Test kit name2,kit lot,exp date
							if (trim($row['kitReference'][1]['expiry_date']) != "") {
								$row['kitReference'][1]['expiry_date'] = Pt_Commons_General::excelDateFormat($row['kitReference'][1]['expiry_date']);
							}
							$sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][1]['testKitName']);
							$sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][1]['lot_no']);
							$sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][1]['expiry_date']);
						}

						if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
							$kitId = $kitId + $aRow['number_of_samples'] + $aRow['number_of_controls'];
							if (isset($row['kitReference'][2]['referenceKitResult'])) {
								//In Excel Third row added the Test kit name3,kit lot,exp date
								if (trim($row['kitReference'][2]['expiry_date']) != "") {
									$row['kitReference'][2]['expiry_date'] = Pt_Commons_General::excelDateFormat($row['kitReference'][2]['expiry_date']);
								}
								$sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][2]['testKitName']);
								$sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][2]['lot_no']);
								$sheet->getCellByColumnAndRow($kitId++, 3)->setValueExplicit($row['kitReference'][2]['expiry_date']);
							}
						}
					}

					$sheet->getCellByColumnAndRow($ktr + 1, 3)->setValueExplicit($row['kitReference'][0]['referenceKitResult']);
					$ktr = ($aRow['number_of_samples'] + $aRow['number_of_controls'] - $keyv) + $ktr + 3;

					if (isset($row['kitReference'][1]['referenceKitResult'])) {
						$ktr = $ktr + $keyv;
						$sheet->getCellByColumnAndRow($ktr + 1, 3)->setValueExplicit($row['kitReference'][1]['referenceKitResult']);
						$ktr = ($aRow['number_of_samples'] + $aRow['number_of_controls'] - $keyv) + $ktr + 3;
					}
					if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
						if (isset($row['kitReference'][2]['referenceKitResult'])) {
							$ktr = $ktr + $keyv;
							$sheet->getCellByColumnAndRow($ktr + 1, 3)->setValueExplicit($row['kitReference'][2]['referenceKitResult']);
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
		if (isset($shipmentResult) && !empty($shipmentResult)) {

			foreach ($shipmentResult as $aRow) {
				$r = 1;
				$k = 0;
				$rehydrationDate = "";
				$shipmentTestDate = "";
				$sheetThreeCol = 0;
				$docScoreCol = 1;
				$totScoreCol = 0;
				$countCorrectResult = $totPer = 0;

				$finalResult = array(1 => 'Pass', 2 => 'Fail', 3 => 'Excluded', 4 => 'Not Evaluated');

				$colCellObj = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow);
				$colCellObj->setValueExplicit(ucwords($aRow['unique_identifier']));
				$cellName = $colCellObj->getColumn();

				$sheet->setCellValue(Coordinate::stringFromColumnIndex($r++) . $currentRow, $aRow['first_name'] . ' ' . $aRow['last_name']);
				$sheet->setCellValue(Coordinate::stringFromColumnIndex($r++) . $currentRow, $aRow['institute_name']);
				$sheet->setCellValue(Coordinate::stringFromColumnIndex($r++) . $currentRow, $aRow['province']);
				$sheet->setCellValue(Coordinate::stringFromColumnIndex($r++) . $currentRow, $aRow['district']);

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

				$attributes = !empty($aRow['attributes']) ? json_decode($aRow['attributes'], true) : [];
				if (isset($attributes['sample_rehydration_date']) && !empty($attributes['sample_rehydration_date'])) {
					$sampleRehydrationDate = new Zend_Date($attributes['sample_rehydration_date']);
					$rehydrationDate = Pt_Commons_General::excelDateFormat($attributes["sample_rehydration_date"]);
					if (isset($config->evaluation->dts->displaySampleConditionFields) && $config->evaluation->dts->displaySampleConditionFields == 'yes') {
						$conditionOfPTSamples = (isset($attributes['condition_pt_samples']) && $attributes['condition_pt_samples'] != "") ? ucwords(str_replace('-', ' ', $attributes['condition_pt_samples'])) : "";
						$refridgerator = (isset($attributes['refridgerator']) && $attributes['refridgerator'] != "") ? ucwords(str_replace('-', ' ', $attributes['refridgerator'])) : "";
						$roomTemperature = (isset($attributes['room_temperature']) && $attributes['room_temperature'] != "") ? $attributes['room_temperature'] : "";
						$stopWatch = (isset($attributes['stop_watch']) && $attributes['stop_watch'] != "") ? ucwords(str_replace('-', ' ', $attributes['stop_watch'])) : "";
					}
				}

				$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($shipmentReceiptDate);
				if (!isset($config->evaluation->dts->displaySampleConditionFields) || $config->evaluation->dts->displaySampleConditionFields != 'yes') {
					$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($rehydrationDate);
				}
				$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($shipmentTestDate);
				$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($shipmentReportDate);

				if (isset($config->evaluation->dts->displaySampleConditionFields) && $config->evaluation->dts->displaySampleConditionFields == 'yes') {
					$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($conditionOfPTSamples);
					$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($refridgerator);
					$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($roomTemperature);
					$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($stopWatch);
				}
				$sheetThreeCol = 1;
				$sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit(ucwords($aRow['unique_identifier']));
				$sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);

				//<-------------Document score sheet------------

				$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(ucwords($aRow['unique_identifier']));
				$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);

				if (isset($shipmentReceiptDate) && trim($shipmentReceiptDate) != "") {
					$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
				} else {
					$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
				}

				// For Myanmar National Algorithm, they do not want to check for Supervisor Approval
				if (isset($attributes['algorithm']) && $attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
					$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit('-');
				} else {
					if (isset($aRow['supervisor_approval']) && strtolower($aRow['supervisor_approval']) == 'yes' && isset($aRow['participant_supervisor']) && trim($aRow['participant_supervisor']) != "") {
						$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
					} else {
						$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0);
					}
				}

				if (isset($attributes['algorithm']) && $attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
					$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit('-');
				} else {
					if (isset($rehydrationDate) && trim($rehydrationDate) != "") {
						$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem);
					} else {
						$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0);
					}
				}

				if (isset($aRow['shipment_test_date']) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {
					$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
				} else {
					$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
				}

				if (isset($attributes['algorithm']) && $attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
					$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit('-');
				} elseif (isset($sampleRehydrationDate) && isset($aRow['shipment_test_date']) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {


					$config = new Zend_Config_Ini(APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini", APPLICATION_ENV);
					$sampleRehydrationDate = new DateTime($attributes['sample_rehydration_date']);
					$testedOnDate = new DateTime($aRow['shipment_test_date']);
					$interval = $sampleRehydrationDate->diff($testedOnDate);

					// Testing should be done within 24*($config->evaluation->dts->sampleRehydrateDays) hours of rehydration.
					$sampleRehydrateDays = $config->evaluation->dts->sampleRehydrateDays;
					$rehydrateHours = $sampleRehydrateDays * 24;

					if ($interval->days < $sampleRehydrateDays || $interval->days > ($sampleRehydrateDays + 1)) {

						$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
					} else {
						$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentationScorePerItem, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
					}
				} else {
					$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit(0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
				}

				//$panelScore = !empty($config->evaluation->dts->panelScore) && (int) $config->evaluation->dts->panelScore > 0 ? ($config->evaluation->dts->panelScore/100) : 0.9;
				$documentScore = !empty($config->evaluation->dts->documentationScore) && (int) $config->evaluation->dts->documentationScore > 0 ? (($aRow['documentation_score'] / $config->evaluation->dts->documentationScore) * 100) : 0;
				$docScoreSheet->getCellByColumnAndRow($docScoreCol++, $docScoreRow)->setValueExplicit($documentScore, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

				//-------------Document score sheet------------>
				//<------------ Total score sheet ------------
				$totScoreCol = 1;
				$totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(ucwords($aRow['unique_identifier']));
				$totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);
				$totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['province']);
				$totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['district']);
				$totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['city']);
				$totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['iso_name']);

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

					$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['testKitName1']);
					$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['lot_no_1']);
					$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['exp_date_1']);

					for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
						//$row[] = $aRow[$k]['testResult1'];
						$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][$k]['testResult1']);
					}
					$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['testKitName2']);
					$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['lot_no_2']);
					$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['exp_date_2']);

					for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
						//$row[] = $aRow[$k]['testResult2'];
						$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][$k]['testResult2']);
					}

					if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
						$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['testKitName3']);
						$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['lot_no_3']);
						$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['exp_date_3']);

						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							//$row[] = $aRow[$k]['testResult3'];
							$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][$k]['testResult3']);
						}
					}
					if (isset($config->evaluation->dts->allowRepeatTests) && $config->evaluation->dts->allowRepeatTests == 'yes') {
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][$k]['repeatTestResult1']);
						}
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][$k]['repeatTestResult2']);
						}
						if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
							for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
								$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][$k]['repeatTestResult3']);
							}
						}
					}

					for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
						//$row[] = $aRow[$k]['finalResult'];
						$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][$k]['finalResult']);

						$sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($aRow['response'][$k]['finalResult']);
						if (isset($aRow['response'][$k]['calculated_score']) && $aRow['response'][$k]['calculated_score'] == 'Pass' && $aRow['response'][$k]['sample_id'] == $refResult[$k]['sample_id']) {
							$countCorrectResult++;
						}
					}
					if (isset($rtrishipmentAttributes['enableRtri']) && $rtrishipmentAttributes['enableRtri'] == 'yes') {
						/* -- RTRI SECTION STARTED -- */
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$aRow['response'][$k]['dts_rtri_is_editable'] = (isset($aRow['response'][$k]['dts_rtri_is_editable']) && $aRow['response'][$k]['dts_rtri_is_editable']) ? $aRow['response'][$k]['dts_rtri_is_editable'] : null;
							$rr = $r++;
							$sheet->getCellByColumnAndRow($rr, $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['dts_rtri_is_editable']));
							/* For showing samples labels */
							$sheet->getCellByColumnAndRow($rr, 3)->setValueExplicit($refResult[$k]['sample_label']);
						}
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$aRow['response'][$k]['dts_rtri_control_line'] = (isset($aRow['response'][$k]['dts_rtri_control_line']) && $aRow['response'][$k]['dts_rtri_control_line']) ? $aRow['response'][$k]['dts_rtri_control_line'] : null;
							$rr = $r++;
							$sheet->getCellByColumnAndRow($rr, $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['dts_rtri_control_line']));
							/* Merge titiles */
							if ($k == 0) {
								/* For showing which sample for wich tittle */
								$rtriFirstCellName = $sheet->getCellByColumnAndRow($rr, 3)->getColumn();
								$rtriSecondCellName = $sheet->getCellByColumnAndRow(($rr + ($aRow['number_of_samples'] - 1)), 3)->getColumn();
								$sheet->mergeCells($rtriFirstCellName . "3:" . $rtriSecondCellName . "3");
								$sheet->getCellByColumnAndRow($rr, 3)->setValueExplicit("Control Line");
							}
						}
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$aRow['response'][$k]['dts_rtri_diagnosis_line'] = (isset($aRow['response'][$k]['dts_rtri_diagnosis_line']) && $aRow['response'][$k]['dts_rtri_diagnosis_line']) ? $aRow['response'][$k]['dts_rtri_diagnosis_line'] : null;
							$rr = $r++;
							$sheet->getCellByColumnAndRow($rr, $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['dts_rtri_diagnosis_line']));
							/* Merge titiles */
							if ($k == 0) {
								/* For showing which sample for wich tittle */
								$rtriFirstCellName = $sheet->getCellByColumnAndRow($rr, 3)->getColumn();
								$rtriSecondCellName = $sheet->getCellByColumnAndRow(($rr + ($aRow['number_of_samples'] - 1)), 3)->getColumn();
								$sheet->mergeCells($rtriFirstCellName . "3:" . $rtriSecondCellName . "3");
								$sheet->getCellByColumnAndRow($rr, 3)->setValueExplicit("Verification Line");
							}
						}
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$aRow['response'][$k]['dts_rtri_longterm_line'] = (isset($aRow['response'][$k]['dts_rtri_longterm_line']) && $aRow['response'][$k]['dts_rtri_longterm_line']) ? $aRow['response'][$k]['dts_rtri_longterm_line'] : null;
							$rr = $r++;
							$sheet->getCellByColumnAndRow($rr, $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['dts_rtri_longterm_line']));
							/* Merge titiles */
							if ($k == 0) {
								/* For showing which sample for wich tittle */
								$rtriFirstCellName = $sheet->getCellByColumnAndRow($rr, 3)->getColumn();
								$rtriSecondCellName = $sheet->getCellByColumnAndRow(($rr + ($aRow['number_of_samples'] - 1)), 3)->getColumn();
								$sheet->mergeCells($rtriFirstCellName . "3:" . $rtriSecondCellName . "3");
								$sheet->getCellByColumnAndRow($rr, 3)->setValueExplicit("Longterm Line");
							}
						}
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$aRow['response'][$k]['rtrifinalResult'] = (isset($aRow['response'][$k]['rtrifinalResult']) && $aRow['response'][$k]['rtrifinalResult']) ? $aRow['response'][$k]['rtrifinalResult'] : null;
							$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['rtrifinalResult']));
						}
						/* -- RTRI SECTION END -- */
					}
					if (isset($haveCustom) && $haveCustom == 'yes') {

						if (isset($customField1) && $customField1 != "") {
							$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['custom_field_1']);
						}
						if (isset($customField2) && $customField2 != "") {
							$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['custom_field_2']);
						}
					}
					$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['user_comment']);

					$sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($countCorrectResult);

					//$totPer = round((($countCorrectResult / $aRow['number_of_samples']) * 100), 2);
					$totPer = $aRow['shipment_score'];
					$sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($totPer, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
				}
				$totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($countCorrectResult, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
				$totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($totPer, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
				// $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(($totPer * $panelScore), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
				// $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($documentScore, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
				$totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['documentation_score'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
				$totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(($aRow['shipment_score'] + $aRow['documentation_score']), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
				$finalResultCell = (isset($aRow['final_result']) && !empty($aRow['final_result'])) ? $aRow['final_result'] : '';
				$totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($finalResultCell);
				if (!empty($aRow['failure_reason'])) {
					$failureReasonJson = $aRow['failure_reason'];
					$warningsArray = json_decode($failureReasonJson, true);
					$warnings = [];
					foreach ($warningsArray as $w) {
						$warnings[] = strip_tags($w['warning']);
					}
					$warnings = implode(", ", $warnings);
				} else {
					$warnings = "";
				}
				$totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($warnings);

				for ($i = 0; $i < $panelScoreHeadingCount; $i++) {
					$cellName = $sheetThree->getCellByColumnAndRow($i + 1, $sheetThreeRow)->getColumn();
					$sheetThree->getStyle($cellName . $sheetThreeRow)->applyFromArray($borderStyle, true);
				}

				for ($i = 0; $i < $n; $i++) {
					$cellName = $sheet->getCellByColumnAndRow($i + 1, $currentRow)->getColumn();
					$sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle, true);
				}

				for ($i = 0; $i < $docScoreHeadingsCount; $i++) {
					$cellName = $docScoreSheet->getCellByColumnAndRow($i + 1, $docScoreRow)->getColumn();
					$docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle, true);
				}

				for ($i = 0; $i < $totScoreHeadingsCount; $i++) {
					$cellName = $totalScoreSheet->getCellByColumnAndRow($i + 1, $totScoreRow)->getColumn();
					$totalScoreSheet->getStyle($cellName . $totScoreRow)->applyFromArray($borderStyle, true);
					$totalScoreSheet->getStyleByColumnAndRow($i + 1, $totScoreRow, null, null)->getAlignment()->setWrapText(true);
				}

				$currentRow++;

				$sheetThreeRow++;
				$docScoreRow++;
				$totScoreRow++;
			}
		}

		//----------- Second Sheet End----->

		$excel->setActiveSheetIndex(0);

		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
		$filename = $shipmentCode . '-' . date('d-M-Y-H-i-s') . '.xlsx';
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
}
