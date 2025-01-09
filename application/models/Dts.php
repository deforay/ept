<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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

			$shipmentTestReportDateUser = explode(" ", $shipment['shipment_test_report_date'] ?? '');
			if (trim($shipmentTestReportDateUser[0]) != "" && $shipmentTestReportDateUser[0] != null && trim($shipmentTestReportDateUser[0]) != "0000-00-00") {
				$shipmentTestReportDate = new DateTime($shipmentTestReportDateUser[0]);
			} else {
				$shipmentTestReportDate = new DateTime('1970-01-01');
			}


			$results = $resultsForShipment[$shipment['participant_id']];

			$totalScore = 0;
			$maxScore = 0;
			$mandatoryResult = "";
			$lotResult = "";
			$testKit1 = "";
			$testKit2 = "";
			$testKit3 = "";
			$testKitExpiryResult = "";
			$lotResult = "";
			$scoreResult = "";
			$failureReason = [];
			$correctiveActionList = [];
			$algoResult = "";
			$lastDateResult = "";
			$controlTesKitFail = "";

			$attributes = json_decode($shipment['attributes'] ?? '{}', true);

			$attributes['algorithm'] ??= null;
			//$attributes['sample_rehydration_date'] = $attributes['sample_rehydration_date'] ?: null;

			$isScreening =  ((isset($shipmentAttributes['screeningTest']) && $shipmentAttributes['screeningTest'] == 'yes') || (isset($attributes['dts_test_panel_type']) && $attributes['dts_test_panel_type'] === 'screening')) ? true : false;
			$isConfirmatory =  (isset($attributes['dts_test_panel_type']) && $attributes['dts_test_panel_type'] === 'confirmatory') ? true : false;

			//Response was submitted after the last response date.
			$lastDate = new DateTime($shipment['lastdate_response']);
			if ($shipmentTestReportDate > $lastDate) {
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
			if ($shipment['response_switch'] == 'on') {
				$lastDateResult = '';
				$shipment['is_response_late'] = 'no';
				$shipment['is_excluded'] = 'no';
			}

			// CORRECT SERIAL RESPONSES 'NXX','PNN','PPX','PNP';
			// CORRECT PARALLEL RESPONSES 'PPX','PNP','PNN','NNX','NPN','NPP';

			// 3 tests algo added for Myanmar initally, might be used in other places eventually
			//$threeTestCorrectResponses = array('NXX','PPP');

			$testedOn = new DateTime($results[0]['shipment_test_date'] ?? $shipment['shipment_test_report_date']);

			// Getting the Test Date string to show in Corrective Actions and other sentences
			$testDate = $testedOn->format('d-M-Y');

			// Getting test kit expiry dates as reported
			$expDate1 = "";
			//die($results[0]['exp_date_1']);
			if (!empty($results[0]['exp_date_1']) && trim($results[0]['exp_date_1']) != "0000-00-00" && trim(strtotime($results[0]['exp_date_1'])) != "") {
				$expDate1 = new DateTime($results[0]['exp_date_1']);
			}
			$expDate2 = "";
			if (!empty($results[0]['exp_date_2']) && trim($results[0]['exp_date_2']) != "0000-00-00" && trim(strtotime($results[0]['exp_date_2'])) != "") {
				$expDate2 = new DateTime($results[0]['exp_date_2']);
			}
			$expDate3 = "";
			if (!empty($results[0]['exp_date_3']) && trim($results[0]['exp_date_3']) != "0000-00-00" && trim(strtotime($results[0]['exp_date_3'])) != "") {
				$expDate3 = new DateTime($results[0]['exp_date_3']);
			}

			// Getting Test Kit Names

			$testKitDb = new Application_Model_DbTable_Testkitnames();
			$testKit1 = "";

			$testKitName = $testKitDb->getTestKitNameById($results[0]['test_kit_name_1']);
			if (isset($testKitName[0])) {
				$testKit1 = $testKitName[0];
			}

			$testKit2 = "";
			if (!empty($results[0]['test_kit_name_2']) && trim($results[0]['test_kit_name_2']) != "") {
				$testKitName = $testKitDb->getTestKitNameById($results[0]['test_kit_name_2']);
				if (isset($testKitName[0])) {
					$testKit2 = $testKitName[0];
				}
			}
			$testKit3 = "";
			if (!empty($results[0]['test_kit_name_3']) && trim($results[0]['test_kit_name_3']) != "") {
				$testKitName = $testKitDb->getTestKitNameById($results[0]['test_kit_name_3']);
				if (isset($testKitName[0])) {
					$testKit3 = $testKitName[0];
				}
			}


			// T.7 Checking for Expired Test Kits

			if ($testKit1 != "") {
				if ($expDate1 != "") {
					if ($testedOn > $expDate1) {
						$difference = $testedOn->diff($expDate1);
						$failureReason[] = [
							'warning' => "Test Kit 1 (<strong>" . $testKit1 . "</strong>) expired " . $difference->format('%a') . " days before the test date " . $testDate,
							'correctiveAction' => $correctiveActions[5]
						];
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
					if (($testKit1 != "") && ($testKit2 != "") && ($testKit1 == $testKit2)) {
						//$testKitRepeatResult = 'Fail';
						$failureReason[] = array(
							'warning' => "<strong>$testKit1</strong> repeated as Test Kit 1 and Test Kit 2",
							'correctiveAction' => $correctiveActions[9]
						);
						$correctiveActionList[] = 9;
					}
					if (($testKit2 != "") && ($testKit3 != "") && ($testKit2 == $testKit3)) {
						//$testKitRepeatResult = 'Fail';
						$failureReason[] = array(
							'warning' => "<strong>$testKit2</strong> repeated as Test Kit 2 and Test Kit 3",
							'correctiveAction' => $correctiveActions[9]
						);
						$correctiveActionList[] = 9;
					}
					if (($testKit1 != "") && ($testKit3 != "") && ($testKit1 == $testKit3)) {
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

					/* if (isset($result['is_this_retest']) && !empty($result['is_this_retest']) && $result['is_this_retest'] == 'yes') {
						$isRetest = 'yes';
					} else {
						$isRetest = '-';
					} */

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
					if ($isScreening) {
						// no algorithm to check
					} elseif ((isset($dtsSchemeType) && $dtsSchemeType == 'updated-3-tests') || $isConfirmatory) {

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
						} elseif ($result1 == 'R' && $result2 == 'R' && $result3 == '-' && $reportedResultCode == 'P') {
							$algoResult = 'Pass';
						} elseif ($result1 == 'NR' && $result2 == 'NR' && $result3 == 'NR' && $reportedResultCode == 'N') {
							$algoResult = 'Pass';
						} elseif ($result1 == 'NR' && $result2 == '-' && $result3 == '-' && $reportedResultCode == 'N') {
							$algoResult = 'Pass';
						} elseif ($result1 == 'R' && $result2 == 'R' && $result3 == 'R' && $reportedResultCode == 'R') {
							$algoResult = 'Pass';
						} elseif ($result1 == 'R' && $result2 == 'NR' && $result3 == 'NR' && $reportedResultCode == 'N') {
							$algoResult = 'Pass';
						} elseif ($result1 == 'R' && $result2 == 'NR' && $result3 == 'R' && $reportedResultCode == 'I') {
							$algoResult = 'Pass';
						} elseif (($result1 == 'R' && $result2 == 'R' && $result3 == 'NR' && $reportedResultCode == 'P')) {
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
						$algoResult = 'Fail';
						$failureReason[] = array(
							'warning' => "For <strong>" . $result['sample_label'] . "</strong> National HIV Testing algorithm was not followed.",
							'correctiveAction' => $correctiveActions[2]
						);
						$correctiveActionList[] = 2;
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
				$algScore = $config->evaluation->dts->dtsAlgorithmScore ?? 0;
				$scorePercentageForAlgorithm = (isset($algScore) && !empty($algScore) && $algScore > 0) ? $algScore : $scorePercentageForAlgorithm;
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
					$failureReason[] = [
						'warning' => "Sample <strong>" . $result['sample_label'] . "</strong> was not reported. Result not evaluated.",
						'correctiveAction' => $correctiveActions[4]
					];
					$correctiveActionList[] = 4;
				} else {
					if ($controlTesKitFail != 'Fail') {

						// Keeping this as always true so that even for the
						// non-syphilis samples scores can be calculated
						$correctSyphilisResponse = true;
						if ($syphilisEnabled == true) {
							if ($reportedSyphilisResult == $result['syphilis_reference_result']) {
								$correctSyphilisResponse = ($sypAlgoResult != 'Fail') ? true : false;
							} else {
								$correctSyphilisResponse = false;
								$failureReason[] = [
									'warning' => "<strong>" . $result['sample_label'] . "</strong> - Reported Syphilis result does not match the expected result",
									'correctiveAction' => "Final interpretation not matching with the expected result. Please review the SOP and/or job aide to ensure test procedures are followed and interpretation of results are reported accurately."
								];
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
								$failureReason[] = [
									'warning' => "<strong>" . $result['sample_label'] . "</strong> - Reported RTRI result does not match the expected result",
									'correctiveAction' => "Final interpretation not matching with the expected result. Please review the RTRI SOP and/or job aide to ensure test procedures are followed and  interpretation of results are reported accurately."
								];
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
								$failureReason[] = [
									'warning' => "<strong>" . $result['sample_label'] . "</strong> - Reported HIV result does not match the expected result. Passed with warning.",
									'correctiveAction' => $correctiveActions[3]
								];
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
								if ($dtsSchemeType != 'drc' && $algoResult != 'Fail') {
									$totalScore += $scoreForAlgorithm;
								}


								$failureReason[] = [
									'warning' => "<strong>" . $result['sample_label'] . "</strong> - Reported HIV result does not match the expected result",
									'correctiveAction' => $correctiveActions[3]
								];
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
						$failureReason[] = [
							'warning' => "Result not evaluated : name of Test kit 1 not reported.",
							'correctiveAction' => $correctiveActions[7]
						];
						$correctiveActionList[] = 7;
						$shipment['is_excluded'] = 'yes';
					}
					//T.5 Ensure expiry date information is submitted for all performed tests.
					//T.15 Testing performed with a test kit that is not recommended by MOH
					if ((isset($tk1Expired) && $tk1Expired) || (isset($tk1RecommendedUsed) && !$tk1RecommendedUsed)) {
						$testKitExpiryResult = 'Fail';
						if ($correctResponse) {
							$totalScore -= $scoreForSample;
						}
						if ($algoResult == 'Pass') {
							$totalScore -= $scoreForAlgorithm;
						}
						$correctResponse = false;
						$algoResult = 'Fail';
					}
				}
				if (isset($result['test_result_2']) && !empty($result['test_result_2']) && trim($result['test_result_2']) != false && trim($result['test_result_2']) != '24') {
					//T.1 Ensure test kit name is reported for all performed tests.
					if (($testKit2 == "")) {
						$failureReason[] = [
							'warning' => "Result not evaluated : name of Test kit 2 not reported.",
							'correctiveAction' => $correctiveActions[7]
						];
						$correctiveActionList[] = 7;
						$shipment['is_excluded'] = 'yes';
					}
					//T.5 Ensure expiry date information is submitted for all performed tests.
					//T.15 Testing performed with a test kit that is not recommended by MOH
					if ((isset($tk2Expired) && $tk2Expired) || (isset($tk2RecommendedUsed) && !$tk2RecommendedUsed)) {
						$testKitExpiryResult = 'Fail';
						if ($correctResponse) {
							$totalScore -= $scoreForSample;
						}
						if ($algoResult == 'Pass') {
							$totalScore -= $scoreForAlgorithm;
						}
						$correctResponse = false;
						$algoResult = 'Fail';
					}
				}
				if (isset($result['test_result_3']) && !empty($result['test_result_3']) && trim($result['test_result_3']) != false && trim($result['test_result_3']) != '24') {
					//T.1 Ensure test kit name is reported for all performed tests.
					if ($testKit3 == "") {
						$failureReason[] = [
							'warning' => "Result not evaluated : name of Test kit 3 not reported.",
							'correctiveAction' => $correctiveActions[7]
						];
						$correctiveActionList[] = 7;
						$shipment['is_excluded'] = 'yes';
					}
					//T.5 Ensure expiry date information is submitted for all performed tests.
					//T.15 Testing performed with a test kit that is not recommended by MOH
					if ((isset($tk3Expired) && $tk3Expired) || (isset($tk3RecommendedUsed) && !$tk3RecommendedUsed)) {
						$testKitExpiryResult = 'Fail';
						if ($correctResponse) {
							$totalScore -= $scoreForSample;
						}
						if ($algoResult == 'Pass') {
							$totalScore -= $scoreForAlgorithm;
						}
						$correctResponse = false;
						$algoResult = 'Fail';
					}
				}
				$interpretationResult = ($result['reference_result'] == $result['reported_result']) ? 'Pass' : 'Fail';

				if (!$correctResponse || $algoResult == 'Fail' || $mandatoryResult == 'Fail' || ($result['reference_result'] != $result['reported_result'])) {
					$this->db->update('response_result_dts', ['calculated_score' => "Fail", 'algorithm_result' => $algoResult, 'interpretation_result' => $interpretationResult], "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
				} else {
					$this->db->update('response_result_dts', ['calculated_score' => "Pass", 'algorithm_result' => $algoResult, 'interpretation_result' => $interpretationResult], "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
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
			}


			// Myanmar does not have Supervisor scoring so it has one less documentation item
			if ($dtsSchemeType == 'myanmar' ||   $attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
				$totalDocumentationItems -= 1;
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
				$sampleRehydrateDays = null;
				$interval = null;
				if (!empty($attributes['sample_rehydration_date'])) {
					$sampleRehydrationDate = new DateTime($attributes['sample_rehydration_date']);
					$testedOnDate = new DateTime($results[0]['shipment_test_date']);
					$interval = $sampleRehydrationDate->diff($testedOnDate);
					$sampleRehydrateDays = $config->evaluation->dts->sampleRehydrateDays;
				}
				//$rehydrateHours = $sampleRehydrateDays * 24;
				// we can allow testers to test upto sampleRehydrateDays or sampleRehydrateDays + 1
				if (
					!isset($attributes['sample_rehydration_date']) ||
					$attributes['sample_rehydration_date'] === null
					|| $interval->days < $sampleRehydrateDays
					|| $interval->days > ($sampleRehydrateDays + 1)
				) {
					$failureReason[] = [
						'warning' => "Testing not done within specified time of rehydration as per SOP.",
						'correctiveAction' => $correctiveActions[14]
					];
					$correctiveActionList[] = 14;
				} else {
					$documentationScore += $documentationScorePerItem;
				}
			}

			//D.8
			// For Myanmar National Algorithm, they do not want to check for Supervisor Approval
			if ($dtsSchemeType != 'myanmar' && $attributes['algorithm'] != 'myanmarNationalDtsAlgo') {
				if (isset($results[0]['supervisor_approval']) && strtolower($results[0]['supervisor_approval']) == 'yes' && trim($results[0]['participant_supervisor']) != "") {
					$documentationScore += $documentationScorePerItem;
				} else {
					$failureReason[] = [
						'warning' => "Supervisor approval absent",
						'correctiveAction' => $correctiveActions[11]
					];
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
					'warning' => "Participant did not meet the score criteria (Participant Score is <strong>" . round($grandTotal) . "</strong> and Required Score is <strong>" . round($config->evaluation->dts->passPercentage) . "</strong>)",
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
		// if ($shipment['is_excluded'] == 'yes' && $shipment['is_pt_test_not_performed'] == 'yes') {
		// 	$this->db->update('shipment', array('max_score' => 0, 'average_score' => 0, 'status' => 'not-evaluated'), "shipment_id = " . $shipmentId);
		// } else {
		$this->db->update('shipment', array('max_score' => $maxScore, 'average_score' => $averageScore, 'status' => 'evaluated'), "shipment_id = " . $shipmentId);
		// }
		return $shipmentResult;
	}

	public function getDtsSamples($sId, $pId = null)
	{
		$sql = $this->db->select()->from(array('ref' => 'reference_result_dts'))
			->join(['s' => 'shipment'], 's.shipment_id=ref.shipment_id')
			->join(['sp' => 'shipment_participant_map'], 's.shipment_id=sp.shipment_id')
			->joinLeft(['res' => 'response_result_dts'], 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', [
				'test_kit_name_1',
				'lot_no_1',
				'qc_done_1',
				'repeat_qc_done_1',
				'qc_date_1',
				'repeat_qc_date_1',
				'qc_done_2',
				'repeat_qc_done_2',
				'qc_date_2',
				'repeat_qc_date_2',
				'qc_done_3',
				'repeat_qc_done_3',
				'qc_date_3',
				'repeat_qc_date_3',
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
				'dts_rtri_is_editable',
				'kit_additional_info'
			])
			->joinLeft(['rp' => 'r_possibleresult'], 'rp.id = res.reported_result', ['result_code'])
			->joinLeft(['srp' => 'r_possibleresult'], 'srp.id = res.syphilis_final', ['syp_result_code' => 'result_code'])
			->joinLeft(['rtri_rp' => 'r_possibleresult'], 'rtri_rp.id = res.dts_rtri_reported_result', ['rtri_result_code' => 'result_code'])
			->where('sp.shipment_id = ? ', $sId);
		if (!empty($pId)) {
			$sql = $sql->where('sp.participant_id = ? ', $pId);
		}

		return $this->db->fetchAll($sql);
	}

	public function getRecommededDtsTestkits($testMode = 'dts', $testNumber = null, $nonDts = false)
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
			if ($nonDts) {
				$retval[] = $t['testkit'];
			} else {
				$retval[$t['test_no']][] = $t['testkit'];
			}
		}
		return $retval;
	}

	public function getAllDtsTestKitList($countryAdapted = false, $stage = null)
	{

		$sql = $this->db->select()
			->from(
				['t' => 'r_testkitnames'],
				[
					'TESTKITNAMEID' => 'TestKitName_ID',
					'TESTKITNAME' => 'TestKit_Name',
					'attributes'
				]
			)
			->joinLeft(['stm' => 'scheme_testkit_map'], 't.TESTKITNAMEID = stm.testkit_id', ['scheme_type', 'testkit_1', 'testkit_2', 'testkit_3'])
			->order("TESTKITNAME ASC");
		if (isset($stage) && !empty($stage) && !in_array($stage, ['testkit_1', 'testkit_2', 'testkit_3'])) {
			if ($stage == 'custom-tests')
				$sql = $sql->where("scheme_type IS NULL OR scheme_type = ''");
			else
				$sql = $sql->where("scheme_type != '$stage'");
		} else {
			$sql = $sql->where("scheme_type = 'dts'");
		}
		if ($countryAdapted) {
			$sql = $sql->where('COUNTRYADAPTED = 1');
		}
		$stmt = $this->db->fetchAll($sql);

		return $stmt;
	}

	public function updateTestKitStatus($params)
	{
		return $this->db->update("r_testkitnames", array("testkit_status" => $params['status']), "testkit_status = 'pending'");
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
		$globalConfigDb = new Application_Model_DbTable_GlobalConfig();

		$customField1 = $globalConfigDb->getValue('custom_field_1') ?? null;
		$customField2 = $globalConfigDb->getValue('custom_field_2') ?? null;
		$haveCustom = $globalConfigDb->getValue('custom_field_needed') ?? null;

		$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
		$config = new Zend_Config_Ini($file, APPLICATION_ENV);

		$finalResultArray = $this->getFinalResults();

		$excel = new Spreadsheet();

		$borderStyle = array(
			'alignment' => array(
				'horizontal' => Alignment::HORIZONTAL_CENTER,
			),
			'borders' => array(
				'outline' => array(
					'style' => Border::BORDER_THIN,
				),
			)
		);

		$kitQuery = $db->select()
			->from(['s' => 'shipment'], ['s.shipment_id', 's.shipment_code', 's.scheme_type', 's.number_of_samples', 's.number_of_controls'])
			->joinLeft(['spm' => 'shipment_participant_map'], 's.shipment_id = spm.shipment_id', [''])
			->joinLeft(['rrd' => 'response_result_dts'], 'spm.map_id = rrd.shipment_map_id', ['test_kit_name_1', 'test_kit_name_2', 'test_kit_name_3', 'kit_additional_info'])
			->joinLeft(['rtd1' => 'r_testkitnames'], 'rrd.test_kit_name_1 = rtd1.TestKitName_ID', ['kit1Attributes' => 'rtd1.attributes'])
			->joinLeft(['rtd2' => 'r_testkitnames'], 'rrd.test_kit_name_2 = rtd2.TestKitName_ID', ['kit2Attributes' => 'rtd2.attributes'])
			->joinLeft(['rtd3' => 'r_testkitnames'], 'rrd.test_kit_name_3 = rtd3.TestKitName_ID', ['kit3Attributes' => 'rtd3.attributes'])
			->where("(JSON_EXTRACT(rtd1.attributes, '$.additional_info') = 'yes' OR JSON_EXTRACT(rtd2.attributes, '$.additional_info') = 'yes' OR JSON_EXTRACT(rtd3.attributes, '$.additional_info') = 'yes' OR rrd.kit_additional_info != '')")
			->where("s.shipment_id = ?", $shipmentId);
		$kitResult = $db->fetchRow($kitQuery);
		$kit1Result = (array)json_decode($kitResult['kit1Attributes']);
		$kit2Result = (array)json_decode($kitResult['kit2Attributes']);
		$kit3Result = (array)json_decode($kitResult['kit3Attributes']);
		$query = $db->select()
			->from('shipment', ['shipment_id', 'shipment_code', 'scheme_type', 'number_of_samples', 'number_of_controls'])
			->where("shipment_id = ?", $shipmentId);
		$result = $db->fetchRow($query);

		$refResult = $this->getShipmentReferenceResults($shipmentId);

		$shipmentResult = $this->getShipmentResult($shipmentId);
		$shipmentCode = $shipmentResult[0]['shipment_code'] ?? 'DTS';
		$colNo = 0;

		$participantListSheetData = [];
		$docScoreSheetData = [];
		$totalScoreSheetData = [];
		$panelScoreSheetData = [];
		$resultReportedSheetData = [];

		$shipmentAttributes = [];
		if (isset($shipmentResult) && !empty($shipmentResult)) {

			$shipmentAttributes = json_decode($shipmentResult[0]['shipment_attributes'], true);

			foreach ($shipmentResult as  $aRow) {
				$participantRow = [];
				$participantRow[] = $aRow['unique_identifier'];
				$participantRow[] = $aRow['first_name'] . ' ' . $aRow['last_name'];
				$participantRow[] = $aRow['institute_name'];
				$participantRow[] = $aRow['department_name'];
				$participantRow[] = $aRow['iso_name'];
				$participantRow[] = $aRow['address'];
				$participantRow[] = $aRow['province'];
				$participantRow[] = $aRow['district'];
				$participantRow[] = $aRow['city'];
				$participantRow[] = $aRow['mobile'];
				$participantRow[] = $aRow['email'];

				$participantListSheetData[] = $participantRow;
				unset($participantRow);
			}
		}
		$reportHeadings = ['Participant Code', 'Participant Name', 'Institute Name', 'Province', 'District', 'Shipment Receipt Date', 'Test Type', 'Sample Rehydration Date', 'Testing Date', 'Reported On', 'Test#1 Kit Name', 'Kit Lot#1', 'Expiry Date#1', 'QC Done#1', 'QC Expiry Date#1'];
		if ((isset($config->evaluation->dts->displaySampleConditionFields) && $config->evaluation->dts->displaySampleConditionFields == "yes")) {
			$reportHeadings = ['Participant Code', 'Participant Name', 'Institute Name', 'Province', 'District', 'Shipment Receipt Date', 'Test Type', 'Testing Date', 'Reported On', 'Condition Of PT Samples', 'Refridgerator', 'Room Temperature', 'Stop Watch', 'Test#1 Kit Name', 'Kit Lot#1', 'Expiry Date#1', 'QC Done#1', 'QC Expiry Date#1'];
		}

		$reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
		if (isset($kit1Result['additional_info_label']) && !empty($kit1Result['additional_info_label'])) {
			// To search the kit name postion
			$index = array_search('QC Expiry Date#1', $reportHeadings);
			// Insert the value after this index
			foreach (range(($index + 1), (count($reportHeadings) - 1)) as $row) {
				$reportHeadings[] = $kit1Result['additional_info_label'] . ' for (' . $reportHeadings[$row] . ')';
			}
		}
		array_push($reportHeadings, 'Test#2 Kit Name', 'Kit Lot#2', 'Expiry Date#2', 'QC Done#2', 'QC Expiry Date#2');
		$reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
		if (isset($kit2Result['additional_info_label']) && !empty($kit2Result['additional_info_label'])) {
			// To search the kit name postion
			$index = array_search('QC Expiry Date#2', $reportHeadings);
			// Insert the value after this index
			foreach (range(($index + 1), (count($reportHeadings) - 1)) as $row) {
				$reportHeadings[] = $kit2Result['additional_info_label'] . ' for (' . $reportHeadings[$row] . ')';
			}
		}
		if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
			array_push($reportHeadings, 'Test#3 Kit Name', 'Kit Lot#3', 'Expiry Date#3', 'QC Done#3', 'QC Expiry Date#3');
			$reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
			if (isset($kit3Result['additional_info_label']) && !empty($kit3Result['additional_info_label'])) {
				// To search the kit name postion
				$index = array_search('QC Expiry Date#3', $reportHeadings);
				// Insert the value after this index
				foreach (range(($index + 1), (count($reportHeadings) - 1)) as $row) {
					$reportHeadings[] = $kit3Result['additional_info_label'] . ' for (' . $reportHeadings[$row] . ')';
				}
			}
		}
		$addWithFinalResultCol = 2;
		/* Repeat test section */
		if (isset($config->evaluation->dts->allowRepeatTests) && $config->evaluation->dts->allowRepeatTests == 'yes') {
			$reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
			$reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
			// $addWithFinalResultCol = 0;
			if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
				$reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
				// $addWithFinalResultCol = -1;
			}
		}
		// For final result
		$reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
		// For RTRI and test results final result
		if (isset($shipmentAttributes['enableRtri']) && $shipmentAttributes['enableRtri'] == 'yes') {
			foreach (range(1, $result['number_of_samples']) as $key => $row) {
				$reportHeadings[] = "Is Editable";
			}
			$reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
			$reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
			$reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
			$reportHeadings = $this->addSampleNameInArray($shipmentId, $reportHeadings);
		}
		$finalResultStartCellCount = 0;
		if (!empty($haveCustom) && $haveCustom == 'yes') {
			if (isset($customField1) && $customField1 != "") {
				$finalResultStartCellCount += 1;
				array_push($reportHeadings, $customField1);
			}
			if (isset($customField2) && $customField2 != "") {
				$finalResultStartCellCount += 1;
				array_push($reportHeadings, $customField2);
			}
		}
		array_push($reportHeadings, 'Comments');
		$finalResultStartCellCount += 1;
		$finalResultStartCellCount += $result['number_of_samples'];
		if ($result['number_of_controls'] > 0)
			$finalResultStartCellCount += $result['number_of_controls'];


		$common = new Application_Service_Common();
		$feedbackOption = $common->getConfig('feed_back_option');
		if (isset($feedbackOption) && !empty($feedbackOption) && $feedbackOption == 'yes') {
			/* Feed Back Response Section */
			// $questions = $common->getFeedBackQuestions($shipmentId, $reportHeadings);
			// if(isset($questions) && count($questions['question']) > 0){
			// 	$reportHeadings = $questions['heading'];
			// }
		}
		$colNo = 0;
		$repeatCellNo = 0;
		$rtriCellNo = 0;
		$currentRow = 2;
		//$n = (count($reportHeadings) - count($questions['question']) + 1);
		$n = count($reportHeadings);
		/* if (isset($shipmentAttributes['enableRtri']) && $shipmentAttributes['enableRtri'] == 'yes') {
			$rCount = 14 + ($result['number_of_samples'] * 2);
			if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
				$rCount = 17 + ($result['number_of_samples'] * 3);
			}
			$finalResColoumn = $rCount;
		} else {
			$finalResColoumn = $n - ($result['number_of_samples'] + $result['number_of_controls'] + $addWithFinalResultCol);
		} */
		$finalResColoumn = $n - $finalResultStartCellCount;

		$c = 1;
		$z = 1;
		$repeatCell = 1;
		$rtriCell = 1;
		$endMergeCell = ($finalResColoumn + ($result['number_of_samples'] + $result['number_of_controls']));


		$resultsReportedSheet = new Worksheet($excel, 'Results Reported');
		$excel->addSheet($resultsReportedSheet, 1);
		$resultsReportedSheet->setTitle('Results Reported', true);
		$resultsReportedSheet->getDefaultColumnDimension()->setWidth(24);
		$resultsReportedSheet->getDefaultRowDimension()->setRowHeight(18);

		/* Final result merge section */
		$firstCellName = Coordinate::stringFromColumnIndex($finalResColoumn + 1);
		$secondCellName = Coordinate::stringFromColumnIndex($endMergeCell);
		$resultsReportedSheet->mergeCells($firstCellName . "1:" . $secondCellName . "1");
		$resultsReportedSheet->getStyle($firstCellName . "1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
		$resultsReportedSheet->getStyle($firstCellName . "1")->applyFromArray($borderStyle, true);
		$resultsReportedSheet->getStyle($secondCellName . "1")->applyFromArray($borderStyle, true);

		/* RTRI Panel section */
		if (isset($shipmentAttributes['enableRtri']) && $shipmentAttributes['enableRtri'] == 'yes') {
			$rtriHeadingColumn = $endMergeCell + 2;
			$endRtriMergeCell = $endMergeCell + ($result['number_of_samples'] * 4) + 1;
			$rtriFirstCellName = Coordinate::stringFromColumnIndex($rtriHeadingColumn);
			$rtriSecondCellName = Coordinate::stringFromColumnIndex($endRtriMergeCell);
			$resultsReportedSheet->mergeCells($rtriFirstCellName . "1:" . $rtriSecondCellName . "1");
			$resultsReportedSheet->getStyle($rtriFirstCellName . "1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFAB00');
			$resultsReportedSheet->getStyle($rtriFirstCellName . "1")->applyFromArray($borderStyle, true);
			$resultsReportedSheet->getStyle($rtriSecondCellName . "1")->applyFromArray($borderStyle, true);
			/* RTRI Final result merge section */
			$rtriFirstCellName = Coordinate::stringFromColumnIndex($endRtriMergeCell);
			$rtriSecondCellName = Coordinate::stringFromColumnIndex($n - 1);
			$resultsReportedSheet->mergeCells($rtriFirstCellName . "1:" . $rtriSecondCellName . "1");
			$resultsReportedSheet->getStyle($rtriFirstCellName . "1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
			$resultsReportedSheet->getStyle($rtriFirstCellName . "1")->applyFromArray($borderStyle, true);
			$resultsReportedSheet->getStyle($rtriSecondCellName . "1")->applyFromArray($borderStyle, true);
		}
		/* Repeat test section */
		if (isset($config->evaluation->dts->allowRepeatTests) && $config->evaluation->dts->allowRepeatTests == 'yes') {
			$repeatHeadingColumn = $n - (($result['number_of_samples'] * 3) + $result['number_of_controls'] + 1);
			if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
				$repeatHeadingColumn = $n - (($result['number_of_samples'] * 4) + $result['number_of_controls'] + 1);
			}
			$endRepeatMergeCell = ($repeatHeadingColumn + ($result['number_of_samples'] * 2) + $result['number_of_controls']);
			if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
				$endRepeatMergeCell = ($repeatHeadingColumn + ($result['number_of_samples'] * 3) + $result['number_of_controls']);
			}
			$repeatFirstCellName = Coordinate::stringFromColumnIndex($repeatHeadingColumn + 1);
			$repeatSecondCellName = Coordinate::stringFromColumnIndex($endRepeatMergeCell);
			$resultsReportedSheet->mergeCells($repeatFirstCellName . "1:" . $repeatSecondCellName . "1");
			$resultsReportedSheet->getStyle($repeatFirstCellName . "1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
			$resultsReportedSheet->getStyle($repeatFirstCellName . "1")->applyFromArray($borderStyle, true);
			$resultsReportedSheet->getStyle($repeatSecondCellName . "1")->applyFromArray($borderStyle, true);
		}
		if (isset($feedbackOption) && !empty($feedbackOption) && $feedbackOption == 'yes') {
			/* Feed Back Response Section */
			// if (isset($questions) && count($questions['question']) > 0) {
			// 	$lastCol = count($reportHeadings) - count($questions['question']);
			// 	$feedbackHeadingColumn = ($lastCol + 1);
			// 	$endFeedbackMergeCell =  count($reportHeadings);
			// 	$feedbackFirstCellName = Coordinate::stringFromColumnIndex($feedbackHeadingColumn);
			// 	$feedbackSecondCellName = Coordinate::stringFromColumnIndex($endFeedbackMergeCell);
			// 	$resultsReportedSheet->mergeCells($feedbackFirstCellName . "1:" . $feedbackSecondCellName . "1");
			// 	$resultsReportedSheet->getStyle($feedbackFirstCellName . "1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFAB00');
			// 	$resultsReportedSheet->getStyle($feedbackFirstCellName . "1")->applyFromArray($borderStyle, true);
			// 	$resultsReportedSheet->getStyle($feedbackSecondCellName . "1")->applyFromArray($borderStyle, true);
			// }
		}
		foreach ($reportHeadings as $field => $value) {
			$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow, $value);
			$resultsReportedSheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow)->getFont()->setBold(true);
			$resultsReportedSheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow)->applyFromArray($borderStyle, true);

			$resultsReportedSheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . "3")->applyFromArray($borderStyle, true);

			$resultsReportedSheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . '3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFA0A0A0');
			$resultsReportedSheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . '3')->getFont()->getColor()->setARGB('FFFFFF00');
			/* Repeat test section */
			if (isset($config->evaluation->dts->allowRepeatTests) && $config->evaluation->dts->allowRepeatTests == 'yes') {
				if ($repeatCellNo >= $repeatHeadingColumn) {
					if ($repeatCell <= ($result['number_of_samples'] + $result['number_of_controls'])) {
						$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($repeatCellNo + 1) . 1, "Repeat Tests");
					}
					$repeatCell++;
				}
				$repeatCellNo++;
			}
			/* RTRI panel section */
			if (isset($shipmentAttributes['enableRtri']) && $shipmentAttributes['enableRtri'] == 'yes') {
				if (($rtriCellNo >= $rtriHeadingColumn)) {
					if ($rtriCell <= ($result['number_of_samples'] + $result['number_of_controls'])) {
						$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($rtriCellNo) . '1', "RTRI Panel");
					}
					$rtriCell++;
				}
				$rtriCellNo++;
			}
			if ($colNo >= $finalResColoumn) {
				if ($c <= ($result['number_of_samples'] + $result['number_of_controls'])) {
					$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($colNo + 1) . '1', "Final Results");
					$resultsReportedSheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
					$l = $c - 1;
					$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($colNo + 1) . '3', $refResult[$l]['referenceResult']);
				}
				$c++;
			}
			/* RTRI Final Result Section */
			if (isset($shipmentAttributes['enableRtri']) && $shipmentAttributes['enableRtri'] == 'yes') {
				if ($colNo >= ($endRtriMergeCell + 1)) {
					if ($z <= ($result['number_of_samples'] + $result['number_of_controls'])) {
						$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '1', "RTRI Final Results");
						$resultsReportedSheet->getStyle(Coordinate::stringFromColumnIndex($colNo) . $currentRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
						$l = $z - 1;
						$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '3', $refResult[$l]['referenceResult']);
					}
					$z++;
				}
			}
			if (isset($feedbackOption) && !empty($feedbackOption) && $feedbackOption == 'yes') {
				/* Feed Back Response Section */
				// if (isset($questions) && count($questions['question']) > 0) {
				// 	$lastCol = count($reportHeadings) - count($questions['question']);
				// 	if ($colNo >= ($lastCol + 1)) {
				// 		$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '1', "Feedback Questions/Response");
				// 		$resultsReportedSheet->getStyle(Coordinate::stringFromColumnIndex($colNo) . 1)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
				// 	}
				// }
			}
			$colNo++;
		}

		//$shipmentAttributes = json_decode($aRow['shipment_attributes'], true);
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
		$docScore = $config->evaluation->dts->documentationScore ?? 0;
		$documentationScorePerItem = ($docScore > 0) ? round($docScore / $totalDocumentationItems, 2) : 0;


		$ktr = 9;
		$kitId = 7; //Test Kit column count
		if (!empty($refResult)) {
			foreach ($refResult as $keyv => $row) {
				$keyv = $keyv + 1;
				$ktr = $ktr + $keyv;
				if (!empty($row['kitReference'])) {

					if ($keyv == 1) {
						//In Excel Third row added the Test kit name1,kit lot,exp date
						if (trim($row['kitReference'][0]['expiry_date']) != "") {
							$row['kitReference'][0]['expiry_date'] = Pt_Commons_General::excelDateFormat($row['kitReference'][0]['expiry_date']);
						}

						$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($kitId++) . '3', $row['kitReference'][0]['testKitName']);
						$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($kitId++) . '3', $row['kitReference'][0]['lot_no']);
						$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($kitId++) . '3', $row['kitReference'][0]['expiry_date']);

						$kitId = $kitId + $aRow['number_of_samples'] + $aRow['number_of_controls'];
						if (isset($row['kitReference'][1]['referenceKitResult'])) {
							//In Excel Third row added the Test kit name2,kit lot,exp date
							if (trim($row['kitReference'][1]['expiry_date']) != "") {
								$row['kitReference'][1]['expiry_date'] = Pt_Commons_General::excelDateFormat($row['kitReference'][1]['expiry_date']);
							}
							$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($kitId++) . '3', $row['kitReference'][1]['testKitName']);
							$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($kitId++) . '3', $row['kitReference'][1]['lot_no']);
							$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($kitId++) . '3', $row['kitReference'][1]['expiry_date']);
						}

						if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
							$kitId = $kitId + $aRow['number_of_samples'] + $aRow['number_of_controls'];
							if (isset($row['kitReference'][2]['referenceKitResult'])) {
								//In Excel Third row added the Test kit name3,kit lot,exp date
								if (trim($row['kitReference'][2]['expiry_date']) != "") {
									$row['kitReference'][2]['expiry_date'] = Pt_Commons_General::excelDateFormat($row['kitReference'][2]['expiry_date']);
								}
								$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($kitId++) . '3', $row['kitReference'][2]['testKitName']);
								$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($kitId++) . '3', $row['kitReference'][2]['lot_no']);
								$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($kitId++) . '3', $row['kitReference'][2]['expiry_date']);
							}
						}
					}

					$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($ktr + 1) . '3', $row['kitReference'][0]['referenceKitResult']);
					$ktr = ($aRow['number_of_samples'] + $aRow['number_of_controls'] - $keyv) + $ktr + 3;

					if (isset($row['kitReference'][1]['referenceKitResult'])) {
						$ktr = $ktr + $keyv;
						$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($ktr + 1) . '3', $row['kitReference'][1]['referenceKitResult']);
						$ktr = ($aRow['number_of_samples'] + $aRow['number_of_controls'] - $keyv) + $ktr + 3;
					}
					if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
						if (isset($row['kitReference'][2]['referenceKitResult'])) {
							$ktr = $ktr + $keyv;
							$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($ktr + 1) . '3', $row['kitReference'][2]['referenceKitResult']);
						}
					}
				}
				$ktr = 9;
			}
		}

		if (!empty($shipmentResult)) {

			foreach ($shipmentResult as $aRow) {
				$r = 1;
				$k = 0;
				$rehydrationDate = "";
				$shipmentTestDate = "";
				$countCorrectResult = 0;

				$resultReportRow = [];
				$resultReportRow[] = $aRow['unique_identifier'];
				$resultReportRow[] = $aRow['first_name'] . ' ' . $aRow['last_name'];
				$resultReportRow[] = $aRow['institute_name'];
				$resultReportRow[] = $aRow['province'];
				$resultReportRow[] = $aRow['district'];


				$shipmentReceiptDate = $aRow['shipment_receipt_date'] = Pt_Commons_General::excelDateFormat($aRow['shipment_receipt_date']);

				$resultReportRow[] = $shipmentReceiptDate;

				$attributes = !empty($aRow['attributes']) ? json_decode($aRow['attributes'], true) : [];


				if (isset($attributes['dts_test_panel_type']) && !empty($attributes['dts_test_panel_type'])) {
					$resultReportRow[] = ucwords($attributes['dts_test_panel_type']);
				} else {
					$resultReportRow[] = 'HIV SEROLOGY';
				}

				if (isset($attributes['sample_rehydration_date']) && !empty($attributes['sample_rehydration_date'])) {
					$sampleRehydrationDate = new Zend_Date($attributes['sample_rehydration_date']);
					$rehydrationDate = Pt_Commons_General::excelDateFormat($attributes["sample_rehydration_date"]);
				}

				if (!isset($config->evaluation->dts->displaySampleConditionFields) || $config->evaluation->dts->displaySampleConditionFields != 'yes') {
					$resultReportRow[] =  $rehydrationDate;
				}

				$resultReportRow[] = Pt_Commons_General::excelDateFormat($aRow['shipment_test_date']);
				$resultReportRow[] =  Pt_Commons_General::excelDateFormat($aRow['shipment_test_report_date']);

				if (isset($config->evaluation->dts->displaySampleConditionFields) && $config->evaluation->dts->displaySampleConditionFields == 'yes') {

					$conditionOfPTSamples = (isset($attributes['condition_pt_samples']) && $attributes['condition_pt_samples'] != "") ? ucwords(str_replace('-', ' ', $attributes['condition_pt_samples'])) : "";
					$refridgerator = (isset($attributes['refridgerator']) && $attributes['refridgerator'] != "") ? ucwords(str_replace('-', ' ', $attributes['refridgerator'])) : "";
					$roomTemperature = (isset($attributes['room_temperature']) && $attributes['room_temperature'] != "") ? $attributes['room_temperature'] : "";
					$stopWatch = (isset($attributes['stop_watch']) && $attributes['stop_watch'] != "") ? ucwords(str_replace('-', ' ', $attributes['stop_watch'])) : "";

					$resultReportRow[] = $conditionOfPTSamples;
					$resultReportRow[] = $refridgerator;
					$resultReportRow[] = $roomTemperature;
					$resultReportRow[] = $stopWatch;
				}

				$panelScoreRow = [];
				$panelScoreRow[] = $aRow['unique_identifier'];
				$panelScoreRow[] = $aRow['first_name'] . ' ' . $aRow['last_name'];
				$panelScoreRow[] = $aRow['institute_name'];
				$panelScoreRow[] = $aRow['province'];

				$docScoreRow = [];
				$docScoreRow[] = $aRow['unique_identifier'];
				$docScoreRow[] = $aRow['first_name'] . ' ' . $aRow['last_name'];
				$docScoreRow[] = $aRow['institute_name'];
				$docScoreRow[] = $aRow['province'];


				if (!empty($shipmentReceiptDate)) {
					$docScoreRow[] = $documentationScorePerItem;
				} else {
					$docScoreRow[] = 0;
				}

				// For Myanmar National Algorithm, they do not want to check for Supervisor Approval
				if (isset($attributes['algorithm']) && $attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
					$docScoreRow[] = '-';
				} else {
					if (isset($aRow['supervisor_approval']) && strtolower($aRow['supervisor_approval']) == 'yes' && isset($aRow['participant_supervisor']) && trim($aRow['participant_supervisor']) != "") {
						$docScoreRow[] = $documentationScorePerItem;
					} else {
						$docScoreRow[] = 0;
					}
				}

				if (isset($attributes['algorithm']) && $attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
					$docScoreRow[] = '-';
				} else {
					if (isset($rehydrationDate) && trim($rehydrationDate) != "") {
						$docScoreRow[] = $documentationScorePerItem;
					} else {
						$docScoreRow[] = 0;
					}
				}

				if (isset($aRow['shipment_test_date']) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {
					$docScoreRow[] = $documentationScorePerItem;
				} else {
					$docScoreRow[] = 0;
				}

				if (isset($attributes['algorithm']) && $attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
					$docScoreRow[] = '-';
				} elseif (isset($sampleRehydrationDate) && isset($aRow['shipment_test_date']) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {

					$sampleRehydrationDate = new DateTime($attributes['sample_rehydration_date']);
					$testedOnDate = new DateTime($aRow['shipment_test_date']);
					$interval = $sampleRehydrationDate->diff($testedOnDate);

					// Testing should be done within 24*($config->evaluation->dts->sampleRehydrateDays) hours of rehydration.
					$sampleRehydrateDays = $config->evaluation->dts->sampleRehydrateDays;
					//$rehydrateHours = $sampleRehydrateDays * 24;

					if ($interval->days < $sampleRehydrateDays || $interval->days > ($sampleRehydrateDays + 1)) {
						$docScoreRow[] = 0;
					} else {
						$docScoreRow[] = $documentationScorePerItem;
					}
				} else {
					$docScoreRow[] = 0;
				}

				//$panelScore = !empty($config->evaluation->dts->panelScore) && (int) $config->evaluation->dts->panelScore > 0 ? ($config->evaluation->dts->panelScore/100) : 0.9;
				$documentScore = !empty($config->evaluation->dts->documentationScore) && (int) $config->evaluation->dts->documentationScore > 0 ? (($aRow['documentation_score'] / $config->evaluation->dts->documentationScore) * 100) : 0;
				$docScoreRow[] = $documentScore;



				$totalScoreRow = [];
				$totalScoreRow[] = $aRow['unique_identifier'];
				$totalScoreRow[] = $aRow['first_name'] . ' ' . $aRow['last_name'];
				$totalScoreRow[] = $aRow['institute_name'];
				$totalScoreRow[] = $aRow['province'];
				$totalScoreRow[] = $aRow['district'];
				$totalScoreRow[] = $aRow['city'];
				$totalScoreRow[] = $aRow['iso_name'];

				$participantResponse = $this->getParticipantResponse($aRow['map_id']);

				if (!empty($participantResponse)) {

					foreach (range(1, 2) as $no) {
						$resultReportRow[] = $participantResponse[0]['testKitName' . $no];
						$resultReportRow[] = $participantResponse[0]['lot_no_' . $no];
						$resultReportRow[] = Pt_Commons_General::excelDateFormat($participantResponse[0]['exp_date_' . $no]);
						$resultReportRow[] = $participantResponse[0]['qc_done_' . $no];
						$resultReportRow[] = Pt_Commons_General::excelDateFormat($participantResponse[0]['qc_date_' . $no]);
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$resultReportRow[] = $participantResponse[$k]['testResult' . $no];
						}

						/* Kit Additional Field Value Filling */
						$kitResultCheck = false;
						if (isset($kit1Result['additional_info_label']) && !empty($kit1Result['additional_info_label']) && $no == 1) {
							$kitResultCheck = true;
						}
						if (isset($kit2Result['additional_info_label']) && !empty($kit2Result['additional_info_label']) && $no == 2) {
							$kitResultCheck = true;
						}
						if ($kitResultCheck) {
							for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
								$additionalValue = (array)json_decode($participantResponse[$k]['kit_additional_info']);
								$resultReportRow[] = $additionalValue['test' . $no];
							}
						}
					}
					/* // TEST 1
					$resultReportRow[] = $participantResponse[0]['testKitName1'];
					$resultReportRow[] = $participantResponse[0]['lot_no_1'];
					$resultReportRow[] = Pt_Commons_General::excelDateFormat($participantResponse[0]['exp_date_1']);
					$resultReportRow[] = $participantResponse[0]['qc_done_1'];
					$resultReportRow[] = Pt_Commons_General::excelDateFormat($participantResponse[0]['qc_date_1']);
					for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
						$resultReportRow[] = $participantResponse[$k]['testResult1'];
					}

					// TEST 2
					$resultReportRow[] = $participantResponse[0]['testKitName2'];
					$resultReportRow[] = $participantResponse[0]['lot_no_2'];
					$resultReportRow[] = Pt_Commons_General::excelDateFormat($participantResponse[0]['exp_date_2']);
					$resultReportRow[] = $participantResponse[0]['qc_done_2'];
					// $resultReportRow[] = Pt_Commons_General::excelDateFormat($participantResponse[0]['qc_date_2']);
					for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
						$resultReportRow[] = $participantResponse[$k]['testResult2'];
					}
					if (isset($kit2Result['additional_info_label']) && !empty($kit2Result['additional_info_label'])) {
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$additionalValue = (array)json_decode($participantResponse[$k]['kit_additional_info']);
							$resultReportRow[] = $additionalValue['test2'];
						}
					}*/

					// TEST 3
					if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
						$resultReportRow[] = $participantResponse[0]['testKitName3'];
						$resultReportRow[] = $participantResponse[0]['lot_no_3'];
						$resultReportRow[] = Pt_Commons_General::excelDateFormat($participantResponse[0]['exp_date_3']);
						$resultReportRow[] = $participantResponse[0]['qc_done_3'];
						$resultReportRow[] = Pt_Commons_General::excelDateFormat($participantResponse[0]['qc_date_3']);
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$resultReportRow[] = $participantResponse[$k]['testResult3'];
						}
						if (isset($kit3Result['additional_info_label']) && !empty($kit3Result['additional_info_label'])) {
							for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
								$additionalValue = (array)json_decode($participantResponse[$k]['kit_additional_info']);
								$resultReportRow[] = $additionalValue['test3'];
							}
						}
					}

					// Repeat Tests
					if (isset($config->evaluation->dts->allowRepeatTests) && $config->evaluation->dts->allowRepeatTests == 'yes') {
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$resultReportRow[] = $participantResponse[$k]['repeatTestResult1'];
						}
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$resultReportRow[] = $participantResponse[$k]['repeatTestResult2'];
						}
						if (!isset($config->evaluation->dts->dtsOptionalTest3) || $config->evaluation->dts->dtsOptionalTest3 == 'no') {
							for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
								$resultReportRow[] = $participantResponse[$k]['repeatTestResult3'];
							}
						}
					}

					// Final Result
					for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
						$resultReportRow[] = $participantResponse[$k]['finalResult'];
						$panelScoreRow[] = $participantResponse[$k]['finalResult'];
						if (isset($participantResponse[$k]['calculated_score']) && $participantResponse[$k]['calculated_score'] == 'Pass' && $participantResponse[$k]['sample_id'] == $refResult[$k]['sample_id']) {
							$countCorrectResult++;
						}
					}

					// RTRI Panel
					if (isset($shipmentAttributes['enableRtri']) && $shipmentAttributes['enableRtri'] == 'yes') {
						/* -- RTRI SECTION STARTED -- */
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$participantResponse[$k]['dts_rtri_is_editable'] = (isset($participantResponse[$k]['dts_rtri_is_editable']) && $participantResponse[$k]['dts_rtri_is_editable']) ? $participantResponse[$k]['dts_rtri_is_editable'] : null;
							$rr = $r++;
							$resultReportRow[] = $participantResponse[$k]['dts_rtri_is_editable'];
							/* For showing samples labels */
							$resultReportRow[] = $refResult[$k]['sample_label'];
						}
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$participantResponse[$k]['dts_rtri_control_line'] = (isset($participantResponse[$k]['dts_rtri_control_line']) && $participantResponse[$k]['dts_rtri_control_line']) ? $participantResponse[$k]['dts_rtri_control_line'] : null;
							$rr = $r++;
							$resultReportRow[] = ucwords($participantResponse[$k]['dts_rtri_control_line']);
							/* Merge titiles */
							if ($k == 0) {
								/* For showing which sample for wich tittle */
								$rtriFirstCellName = Coordinate::stringFromColumnIndex($rr);
								$rtriSecondCellName = Coordinate::stringFromColumnIndex(($rr + ($aRow['number_of_samples'] - 1)));
								$resultsReportedSheet->mergeCells($rtriFirstCellName . "3:" . $rtriSecondCellName . "3");
								$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($rr) . '3', "Control Line");
							}
						}
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$participantResponse[$k]['dts_rtri_diagnosis_line'] = (isset($participantResponse[$k]['dts_rtri_diagnosis_line']) && $participantResponse[$k]['dts_rtri_diagnosis_line']) ? $participantResponse[$k]['dts_rtri_diagnosis_line'] : null;
							$rr = $r++;
							$resultReportRow[] = ucwords($participantResponse[$k]['dts_rtri_diagnosis_line']);
							/* Merge titiles */
							if ($k == 0) {
								/* For showing which sample for wich tittle */
								$rtriFirstCellName = Coordinate::stringFromColumnIndex($rr);
								$rtriSecondCellName = Coordinate::stringFromColumnIndex(($rr + ($aRow['number_of_samples'] - 1)));
								$resultsReportedSheet->mergeCells($rtriFirstCellName . "3:" . $rtriSecondCellName . "3");
								$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($rr) . '3', "Verification Line");
							}
						}
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$participantResponse[$k]['dts_rtri_longterm_line'] = (isset($participantResponse[$k]['dts_rtri_longterm_line']) && $participantResponse[$k]['dts_rtri_longterm_line']) ? $participantResponse[$k]['dts_rtri_longterm_line'] : null;
							$rr = $r++;
							$resultReportRow[] = ucwords($participantResponse[$k]['dts_rtri_longterm_line']);
							/* Merge titiles */
							if ($k == 0) {
								/* For showing which sample for wich tittle */
								$rtriFirstCellName = Coordinate::stringFromColumnIndex($rr);
								$rtriSecondCellName = Coordinate::stringFromColumnIndex(($rr + ($aRow['number_of_samples'] - 1)));
								$resultsReportedSheet->mergeCells($rtriFirstCellName . "3:" . $rtriSecondCellName . "3");
								$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($rr) . '3', "Longterm Line");
							}
						}
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$participantResponse[$k]['rtrifinalResult'] = (isset($participantResponse[$k]['rtrifinalResult']) && $participantResponse[$k]['rtrifinalResult']) ? $participantResponse[$k]['rtrifinalResult'] : null;
							$resultReportRow[] = ucwords($participantResponse[$k]['rtrifinalResult']);
						}
						/* -- RTRI SECTION END -- */
					}

					$customFields = ['custom_field_1', 'custom_field_2'];

					if (!empty($haveCustom) && $haveCustom == 'yes') {
						foreach ($customFields as $field) {
							if (!empty(${$field})) {
								$resultReportRow[] = $row[$field];
							}
						}
					}

					$resultReportRow[] = $aRow['user_comment'];
					if (isset($feedbackOption) && !empty($feedbackOption) && $feedbackOption == 'yes') {
						/* Feed Back Response Section */
						// $feedbackDb = new Application_Model_DbTable_FeedBackTable();
						// $answers = $feedbackDb->fetchFeedBackAnswers($aRow['shipment_id'], $aRow['participant_id'], $aRow['map_id']);
						// if (isset($questions['question']) && count($questions['question']) > 0 && isset($answers) && count($answers) > 0) {
						// 	foreach ($questions['question'] as $q) {
						// 		$resultReportRow[] = $answers[$q];
						// 	}
						// }
					}

					$panelScoreRow[] = $countCorrectResult;
					$panelScoreRow[] = $aRow['shipment_score'];
				}

				$totalScoreRow[] = $countCorrectResult;
				$totalScoreRow[] = $aRow['shipment_score'];
				$totalScoreRow[] = round((float) $aRow['documentation_score'], 2);
				$totalScoreRow[] = round((float) ($aRow['shipment_score'] + $aRow['documentation_score']), 2);
				$totalScoreRow[] = !empty($aRow['final_result']) ? $finalResultArray[$aRow['final_result']] : '';

				$warnings = "";
				if (!empty($aRow['failure_reason'])) {
					$warningsArray = json_decode($aRow['failure_reason'], true);
					$warnings = implode(", ", array_map('strip_tags', array_column($warningsArray, 'warning')));
				}
				$totalScoreRow[] = $warnings;

				$docScoreSheetData[] = $docScoreRow;
				unset($docScoreRow);

				$totalScoreSheetData[] = $totalScoreRow;
				unset($totalScoreRow);

				$resultReportedSheetData[] = $resultReportRow;
				unset($resultReportRow);

				$panelScoreSheetData[] = $panelScoreRow;
				unset($panelScoreRow);

				$currentRow++;
			}
		}


		$styleArray = [
			'font' => [
				'bold' => true,
			],
			'alignment' => [
				'horizontal' => Alignment::HORIZONTAL_CENTER,
				'vertical' => Alignment::VERTICAL_CENTER,
			],
		];


		//SHEET 1 - Participant List
		$participantListSheet = new Worksheet($excel, 'Participant List');
		$participantListSheet->getDefaultColumnDimension()->setWidth(24);
		$participantListSheet->getDefaultRowDimension()->setRowHeight(18);
		$excel->addSheet($participantListSheet, 0);
		$participantListSheet->setTitle('Participant List', true);
		$participantListHeadings = ['Participant Code', 'Participant Name',  'Institute Name', 'Department', 'Country', 'Address', 'Province', 'District', 'City', 'Telephone', 'Email'];
		$participantListSheet->fromArray($participantListHeadings, null, "A1");
		$participantListSheet->getStyle('A1:' . $participantListSheet->getHighestColumn() . '1')->applyFromArray($styleArray);

		$participantListSheet->fromArray($participantListSheetData, null, 'A2');
		unset($participantListSheetData, $participantListSheet);



		// SHEET 2 - Result Reported
		// Rearrange this sheet, since this was added already
		$excel->removeSheetByIndex($excel->getIndex($resultsReportedSheet));
		// Re-add the sheet at the desired index
		$excel->addSheet($resultsReportedSheet, 1);
		$resultsReportedSheet->fromArray($resultReportedSheetData, null, 'A4');
		unset($resultReportedSheetData, $resultsReportedSheet);


		// SHEET 3 - Panel Score
		$panelScoreSheet = new Worksheet($excel, 'Panel Score');
		$excel->addSheet($panelScoreSheet, 2);
		$panelScoreSheet->setTitle('Panel Score', true);
		$panelScoreSheet->getDefaultColumnDimension()->setWidth(20);
		$panelScoreSheet->getDefaultRowDimension()->setRowHeight(18);
		$panelScoreHeadings = ['Participant Code', 'Participant Name', 'Institude Name', 'Province'];
		$panelScoreHeadings = $this->addSampleNameInArray($shipmentId, $panelScoreHeadings);
		array_push($panelScoreHeadings, 'No. of Correct Responses', '% Correct');
		$panelScoreSheet->fromArray($panelScoreHeadings, null, 'A1');
		$panelScoreSheet->getStyle('A1:' . $panelScoreSheet->getHighestColumn() . '1')->applyFromArray($styleArray);

		$panelScoreSheet->fromArray($panelScoreSheetData, null, 'A2');
		unset($panelScoreSheetData, $panelScoreSheet);


		// SHEET 4 - Documentation Score
		$docScoreSheet = new Worksheet($excel, 'Documentation Score');
		$excel->addSheet($docScoreSheet, 3);
		$docScoreSheet->setTitle('Documentation Score', true);
		$docScoreSheet->getDefaultColumnDimension()->setWidth(20);
		$docScoreSheet->getDefaultRowDimension()->setRowHeight(25);
		$docScoreHeadings = ['Participant Code', 'Participant Name', 'Institute Name', 'Province', 'Supervisor signature', 'Panel Receipt Date', 'Sample Rehydration Date', 'Tested Date', 'Rehydration Test In Specified Time', 'Documentation Score %'];
		$docScoreSheet->fromArray($docScoreHeadings, null, 'A1');
		$docScoreSheet->getStyle('A1:' . $docScoreSheet->getHighestColumn() . '1')->applyFromArray($styleArray);

		$docScoreSheet->fromArray(['', '', '', '', $documentationScorePerItem, $documentationScorePerItem, $documentationScorePerItem, $documentationScorePerItem, $documentationScorePerItem, '', ''], null, 'A2');
		$docScoreSheet->getStyle('A2:' . $docScoreSheet->getHighestColumn() . '2')->applyFromArray($styleArray);

		$docScoreSheet->fromArray($docScoreSheetData, null, 'A3');
		unset($docScoreSheetData, $docScoreSheet);


		// SHEET 5 - Total Score
		$totalScoreSheet = new Worksheet($excel, 'Total Score');
		$excel->addSheet($totalScoreSheet, 4);
		$totalScoreSheet->setTitle('Total Score', true);
		$totalScoreSheet->getDefaultColumnDimension()->setWidth(20);
		$totalScoreSheet->getDefaultRowDimension()->setRowHeight(30);
		$totalScoreHeadings = ['Participant Code', 'Participant Name', 'Institute Name', 'Province', 'District', 'City', 'Country', 'No. of Panels Correct (N=' . $result['number_of_samples'] . ')', 'Panel Score', 'Documentation Score', 'Total Score', 'Overall Performance', 'Warnings and/or Reasons for Failure'];
		$totalScoreSheet->fromArray($totalScoreHeadings, null, 'A1');
		$totalScoreSheet->getStyle('A1:' . $totalScoreSheet->getHighestColumn() . '1')->applyFromArray($styleArray);

		$totalScoreSheet->fromArray($totalScoreSheetData, null, 'A2');
		unset($totalScoreSheetData, $totalScoreSheet);



		$excel->setActiveSheetIndex(0);

		$writer = IOFactory::createWriter($excel, 'Xlsx');
		$filename = $shipmentCode . '-' . date('d-M-Y-H-i-s') . '.xlsx';
		$writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
		return $filename;
	}

	private function getShipmentResult($shipmentId)
	{

		$authNameSpace = new Zend_Session_Namespace('datamanagers');

		$sql = $this->db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.number_of_samples', 's.number_of_controls', 'shipment_attributes'))
			->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('sp.map_id', 'sp.participant_id', 'sp.attributes', 'sp.shipment_test_date', 'sp.shipment_receipt_date', 'sp.shipment_test_report_date', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_score', 'sp.documentation_score', 'sp.final_result', 'sp.is_excluded', 'sp.failure_reason', 'sp.user_comment', 'sp.custom_field_1', 'sp.custom_field_2'))
			->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.lab_name', 'p.region', 'p.first_name', 'p.last_name', 'p.address', 'p.city', 'p.mobile', 'p.email', 'p.status', 'province' => 'p.state', 'p.district'))
			->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array('pmm.dm_id'))
			->joinLeft(array('dm' => 'data_manager'), 'dm.dm_id=pmm.dm_id', array('dm.institute', 'dataManagerFirstName' => 'dm.first_name', 'dataManagerLastName' => 'dm.last_name'))
			->joinLeft(array('c' => 'countries'), 'c.id=p.country', array('iso_name'))
			->joinLeft(array('st' => 'r_site_type'), 'st.r_stid=p.site_type', array('st.site_type'))
			->joinLeft(array('en' => 'enrollments'), 'en.participant_id=p.participant_id', array('en.enrolled_on'))
			->where("s.shipment_id = ?", $shipmentId)
			->group(array('sp.map_id'));
		if (!empty($authNameSpace->dm_id)) {
			$sql = $sql
				->where("pmm.dm_id = ?", $authNameSpace->dm_id);
		}
		return $this->db->fetchAll($sql);
	}
	private function getParticipantResponse($mapId)
	{

		$responseQuery = $this->db->select()->from(array('rrdts' => 'response_result_dts'))
			->joinLeft(array('tk1' => 'r_testkitnames'), 'tk1.TestKitName_ID=rrdts.test_kit_name_1', array('testKitName1' => 'tk1.TestKit_Name'))
			->joinLeft(array('tk2' => 'r_testkitnames'), 'tk2.TestKitName_ID=rrdts.test_kit_name_2', array('testKitName2' => 'tk2.TestKit_Name'))
			->joinLeft(array('tk3' => 'r_testkitnames'), 'tk3.TestKitName_ID=rrdts.test_kit_name_3', array('testKitName3' => 'tk3.TestKit_Name'))
			->joinLeft(array('r' => 'r_possibleresult'), 'r.id=rrdts.test_result_1', array('testResult1' => 'r.response'))
			->joinLeft(array('rp' => 'r_possibleresult'), 'rp.id=rrdts.test_result_2', array('testResult2' => 'rp.response'))
			->joinLeft(array('rpr' => 'r_possibleresult'), 'rpr.id=rrdts.test_result_3', array('testResult3' => 'rpr.response'))
			->joinLeft(array('rpr1' => 'r_possibleresult'), 'rpr1.id=rrdts.repeat_test_result_1', array('repeatTestResult1' => 'rpr1.response'))
			->joinLeft(array('rpr2' => 'r_possibleresult'), 'rpr2.id=rrdts.repeat_test_result_2', array('repeatTestResult2' => 'rpr2.response'))
			->joinLeft(array('rpr3' => 'r_possibleresult'), 'rpr3.id=rrdts.repeat_test_result_3', array('repeatTestResult3' => 'rpr3.response'))
			->joinLeft(array('fr' => 'r_possibleresult'), 'fr.id=rrdts.reported_result', array('finalResult' => 'fr.response'))
			->joinLeft(array('rtrifr' => 'r_possibleresult'), 'rtrifr.id=rrdts.dts_rtri_reported_result', array('rtrifinalResult' => 'rtrifr.response'))
			->where("rrdts.shipment_map_id = ?", $mapId);
		return $this->db->fetchAll($responseQuery);
	}
	private function getShipmentReferenceResults($shipmentId)
	{

		$referenceResultsQuery = $this->db->select()->from(array('refRes' => 'reference_result_dts'), array('refRes.sample_label', 'sample_id', 'refRes.sample_score'))
			->joinLeft(array('r' => 'r_possibleresult'), 'r.id=refRes.reference_result', array('referenceResult' => 'r.response'))
			->where("refRes.shipment_id = ?", $shipmentId);
		$refResult = $this->db->fetchAll($referenceResultsQuery);
		if (!empty($refResult)) {
			foreach ($refResult as $key => $refRes) {
				$refDtsQuery = $this->db->select()->from(array('refDts' => 'reference_dts_rapid_hiv'), array('refDts.lot_no', 'refDts.expiry_date', 'refDts.result'))
					->joinLeft(array('r' => 'r_possibleresult'), 'r.id=refDts.result', array('referenceKitResult' => 'r.response'))
					->joinLeft(array('tk' => 'r_testkitnames'), 'tk.TestKitName_ID=refDts.testkit', array('testKitName' => 'tk.TestKit_Name'))
					->where("refDts.shipment_id = ?", $shipmentId)
					->where("refDts.sample_id = ?", $refRes['sample_id']);
				$refResult[$key]['kitReference'] = $this->db->fetchAll($refDtsQuery);
			}
		}

		return $refResult;
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
