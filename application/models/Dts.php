<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Application_Service_Common as Common;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class Application_Model_Dts
{
	private $db = null;

	/** @var Zend_Translate */
	protected $translator;

	public function __construct()
	{
		$this->db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$this->translator = Zend_Registry::get('translate');
	}

	private function getFinalResults()
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

		//$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
		//$config = new Zend_Config_Ini($file, APPLICATION_ENV);
		$config = (array)json_decode(Pt_Commons_SchemeConfig::get('dts'));
		$schemeService = new Application_Service_Schemes();
		$shipmentAttributes = json_decode($shipmentResult[0]['shipment_attributes'], true);
		$dtsSchemeType = (isset($shipmentAttributes["dtsSchemeType"]) && $shipmentAttributes["dtsSchemeType"] != '') ? $shipmentAttributes["dtsSchemeType"] : null;
		$syphilisEnabled = (isset($shipmentAttributes['enableSyphilis']) && $shipmentAttributes['enableSyphilis'] == "yes") ? true : false;
		$rtriEnabled = (isset($shipmentAttributes['enableRtri']) && $shipmentAttributes['enableRtri'] == 'yes') ? true : false;

		if ($rtriEnabled) {
			$possibleResultsArray = $schemeService->getPossibleResults('recency');
			$possibleRecencyResults = [];
			foreach ($possibleResultsArray as $row) {
				$row['result_code'] = $row['id'];
				$possibleRecencyResults[] = $row;
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

		$shipmentWhere = $this->db->quoteInto('shipment_id = ?', $shipmentId);
		$this->db->update('shipment_participant_map', ['failure_reason' => null, 'is_followup' => 'no', 'is_excluded' => 'no'], $shipmentWhere);
		$this->db->update(
			'shipment_participant_map',
			['is_excluded' => 'yes'],
			[
				$shipmentWhere,
				"IFNULL(is_pt_test_not_performed, 'no') = 'yes'",
			]
		);


		foreach ($shipmentResult as $index => $shipment) {
			Pt_Commons_MiscUtility::updateHeartbeat('shipment', 'shipment_id', $shipmentId);

			$participantResults = $resultsForShipment[$shipment['participant_id']] ?? [];
			$evaluationOutcome = $this->evaluateSingleShipment(
				$shipment,
				$participantResults,
				[
					'config' => $config,
					'correctiveActions' => $correctiveActions,
					'recommendedTestkits' => $recommendedTestkits,
					'finalResultArray' => $finalResultArray,
					'shipmentAttributes' => $shipmentAttributes,
					'dtsSchemeType' => $dtsSchemeType,
					'syphilisEnabled' => $syphilisEnabled,
					'rtriEnabled' => $rtriEnabled,
					'possibleRecencyResults' => $possibleRecencyResults ?? [],
				]
			);

			if (!empty($evaluationOutcome['shouldSkip'])) {
				continue;
			}

			if (isset($evaluationOutcome['shipmentResultEntry'])) {
				$shipmentResult[$index] = $evaluationOutcome['shipmentResultEntry'];
			}

			if (!empty($evaluationOutcome['scoreHolderEntry'])) {
				foreach ($evaluationOutcome['scoreHolderEntry'] as $mapId => $score) {
					$scoreHolder[$mapId] = $score;
				}
			}

			if (isset($evaluationOutcome['maxScore'])) {
				$maxScore = $evaluationOutcome['maxScore'];
			}
		}


		if (!empty($scoreHolder)) {
			$averageScore = round(array_sum($scoreHolder) / count($scoreHolder), 2);
		} else {
			$averageScore = 0;
		}

		// if ($shipment['is_excluded'] == 'yes' && $shipment['is_pt_test_not_performed'] == 'yes') {
		// 	$this->db->update('shipment', array('max_score' => 0, 'average_score' => 0, 'status' => 'not-evaluated'), $this->db->quoteInto('shipment_id = ?', $shipmentId));
		// } else {
		$this->db->update('shipment', array('max_score' => $maxScore, 'average_score' => $averageScore, 'status' => 'evaluated'), $shipmentWhere);
		// }
		return $shipmentResult;
	}

	private function evaluateSingleShipment(array $shipment, array $results, array $context)
	{
		$shipmentResultEntry = [];
		$scoreHolderEntry = [];
		$config = $context['config'];
		$correctiveActions = $context['correctiveActions'];
		$recommendedTestkits = $context['recommendedTestkits'];
		$finalResultArray = $context['finalResultArray'];
		$shipmentAttributes = $context['shipmentAttributes'];
		$dtsSchemeType = $context['dtsSchemeType'];
		$syphilisEnabled = $context['syphilisEnabled'];
		$rtriEnabled = $context['rtriEnabled'];
		$possibleRecencyResults = $context['possibleRecencyResults'];

		// dump($results[0]['map_id']);
		// dump($results[0]['response_status']);
		// dump(empty($results) || $results[0]['is_pt_test_not_performed'] == 'yes' || $results[0]['response_status'] == 'noresponse');
		if (empty($results) || $results[0]['is_pt_test_not_performed'] == 'yes' || $results[0]['response_status'] == 'noresponse') {
			$shipment['is_excluded'] = 'yes';
			return [
				'shouldSkip' => true,
				'shipmentResultEntry' => $shipment,
				'scoreHolderEntry' => [],
				'maxScore' => 0,
			];
		}

		// setting the following as no by default. Might become 'yes' if some conditions match
		$shipment['is_excluded'] = 'no';
		$shipment['is_followup'] = 'no';
		$mapWhere = $this->db->quoteInto('map_id = ?', $shipment['map_id']);

		if (Common::isDateValid($shipment['shipment_test_report_date'])) {
			$shipmentTestReportDate = new DateTimeImmutable($shipment['shipment_test_report_date']);
		} else {
			$shipmentTestReportDate = new DateTimeImmutable('1970-01-01');
		}


		$totalScore = 0;
		$maxScore = 0;
		$mandatoryResult = "";
		$lotResult = "";
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

		$isScreening = ((isset($shipmentAttributes['screeningTest']) && $shipmentAttributes['screeningTest'] == 'yes') || (isset($attributes['dts_test_panel_type']) && $attributes['dts_test_panel_type'] === 'screening')) ? true : false;
		$isConfirmatory = (isset($attributes['dts_test_panel_type']) && $attributes['dts_test_panel_type'] === 'confirmatory') ? true : false;

		//Response was submitted after the last response date.
		$lastDate = new DateTimeImmutable($shipment['lastdate_response']);
		if ($shipmentTestReportDate > $lastDate) {
			$lastDateResult = 'Fail';
			$failureReason[] = [
				'warning' => "Response was submitted after the last response date.",
				'correctiveAction' => $correctiveActions[1]
			];
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
		//$threeTestCorrectResponses = ['NXX','PPP'];


		if (empty($results[0]['shipment_test_date'])) {
			echo "Shipment Test Date is missing for shipment map id: " . $shipment['map_id'] . ". Cannot evaluate DTS results.\n";
		}

		$testedOn = new DateTimeImmutable($results[0]['shipment_test_date'] ?? $shipment['shipment_test_report_date']);

		// Getting the Test Date string to show in Corrective Actions and other sentences
		$testDate = $testedOn->format('d-M-Y');

		$testKitDb = new Application_Model_DbTable_Testkitnames();
		$testKitMeta = [
			1 => [
				'nameField' => 'test_kit_name_1',
				'lotField' => 'lot_no_1',
				'expField' => 'exp_date_1',
				'resultField' => 'test_result_1',
			],
			2 => [
				'nameField' => 'test_kit_name_2',
				'lotField' => 'lot_no_2',
				'expField' => 'exp_date_2',
				'resultField' => 'test_result_2',
			],
			3 => [
				'nameField' => 'test_kit_name_3',
				'lotField' => 'lot_no_3',
				'expField' => 'exp_date_3',
				'resultField' => 'test_result_3',
			],
		];

		$testKitNames = [];
		$testKitExpired = [];
		$testKitRecommendedUsed = [];

		foreach ($testKitMeta as $kitIndex => $fields) {
			$testKitNames[$kitIndex] = '';
			$testKitExpired[$kitIndex] = null;
			$testKitRecommendedUsed[$kitIndex] = null;

			// Resolve kit ID by scanning all result rows for the first non-empty value
			$resolvedKitId = $results[0][$fields['nameField']] ?? null;
			if (empty($resolvedKitId) || trim((string) $resolvedKitId) === '') {
				foreach ($results as $rRow) {
					if (!empty($rRow[$fields['nameField']]) && trim((string) $rRow[$fields['nameField']]) !== '') {
						$resolvedKitId = $rRow[$fields['nameField']];
						break;
					}
				}
			}

			// Lookup human-readable test kit name if we have an ID (string per schema)
			if (!empty($resolvedKitId)) {
				$kitName = $testKitDb->getTestKitNameById((string) $resolvedKitId);
				if (isset($kitName[0])) {
					$testKitNames[$kitIndex] = $kitName[0];
				}
			}

			// Resolve expiry date by scanning all result rows for the first valid date
			$expDate = null;
			$expDateString = $results[0][$fields['expField']] ?? '';
			if (!Common::isDateValid($expDateString)) {
				foreach ($results as $rRow) {
					if (!empty($rRow[$fields['expField']]) && Common::isDateValid($rRow[$fields['expField']])) {
						$expDateString = $rRow[$fields['expField']];
						break;
					}
				}
			}
			if (Common::isDateValid($expDateString)) {
				$expDate = new DateTimeImmutable($expDateString);
			}

			if ($testKitNames[$kitIndex] !== '') {
				if ($expDate instanceof DateTimeInterface) {
					if ($testedOn > $expDate) {
						$difference = $testedOn->diff($expDate);
						$daysExpired = (int) $difference->format('%a');
						$failureReason[] = [
							'warning' => "Test Kit {$kitIndex} (<strong>" . $testKitNames[$kitIndex] . "</strong>) expired " . $daysExpired . " days before the test date " . $testDate,
							'correctiveAction' => $correctiveActions[5]
						];
						$correctiveActionList[] = 5;
						$testKitExpired[$kitIndex] = true;
					} else {
						$testKitExpired[$kitIndex] = false;
					}
				} else {
					$failureReason[] = [
						'warning' => "Result not evaluated : Test kit {$kitIndex} expiry date is not reported with PT response.",
						'correctiveAction' => $correctiveActions[6]
					];
					$correctiveActionList[] = 6;
					$shipment['is_excluded'] = 'yes';
				}

				if (isset($recommendedTestkits[$kitIndex]) && !empty($recommendedTestkits[$kitIndex])) {
					$kitIdForCheck = $resolvedKitId;
					if (!in_array($kitIdForCheck, $recommendedTestkits[$kitIndex])) {
						$testKitRecommendedUsed[$kitIndex] = false;
						$warning = $kitIndex === 1
							? "For Test 1, testing is not performed with country approved test kit.--- " . $kitIdForCheck
							: "For Test {$kitIndex}, testing is not performed with country approved test kit.";
						$failureReason[] = [
							'warning' => $warning,
							'correctiveAction' => $correctiveActions[17]
						];
					} else {
						$testKitRecommendedUsed[$kitIndex] = true;
					}
				}
			}
		}
		//checking if testkits were repeated
		// T.9 Test kit repeated for confirmatory or tiebreaker test (T1/T2/T3).
		$nonEmptyTestKits = array_filter($testKitNames, static function ($name) {
			return $name !== '';
		});
		if (empty($nonEmptyTestKits)) {
			$failureReason[] = [
				'warning' => "No Test Kit reported. Result not evaluated",
				'correctiveAction' => $correctiveActions[7]
			];
			$correctiveActionList[] = 7;
			$shipment['is_excluded'] = 'yes';
		} elseif (count($nonEmptyTestKits) === 3 && count(array_unique($nonEmptyTestKits)) === 1) {

			//Myanmar does not mind if all three test kits are same.
			if ($dtsSchemeType != 'myanmar') {
				//$testKitRepeatResult = 'Fail';
				$failureReason[] = [
					'warning' => "<strong>" . reset($nonEmptyTestKits) . "</strong> repeated for all three Test Kits",
					'correctiveAction' => $correctiveActions[8]
				];
				$correctiveActionList[] = 8;
			}
		} else {
			//Myanmar does not mind if test kits are repeated
			if ($dtsSchemeType != 'myanmar') {
				foreach ([[1, 2], [2, 3], [1, 3]] as $pair) {
					[$first, $second] = $pair;
					if (
						($testKitNames[$first] ?? '') !== '' &&
						($testKitNames[$second] ?? '') !== '' &&
						$testKitNames[$first] === $testKitNames[$second]
					) {
						//$testKitRepeatResult = 'Fail';
						$failureReason[] = [
							'warning' => "<strong>" . $testKitNames[$first] . "</strong> repeated as Test Kit {$first} and Test Kit {$second}",
							'correctiveAction' => $correctiveActions[9]
						];
						$correctiveActionList[] = 9;
					}
				}
			}
		}


		// checking if all LOT details were entered
		// T.3 Ensure test kit lot number is reported for all performed tests.
		foreach ($testKitMeta as $kitIndex => $fields) {
			if ($testKitNames[$kitIndex] !== '' && (!isset($results[0][$fields['lotField']]) || $results[0][$fields['lotField']] == "" || $results[0][$fields['lotField']] == null)) {
				if (isset($results[0][$fields['resultField']]) && $results[0][$fields['resultField']] != "" && $results[0][$fields['resultField']] != null) {
					$lotResult = 'Fail';
					$failureReason[] = [
						'warning' => "Result not evaluated : Test Kit lot number {$kitIndex} is not reported.",
						'correctiveAction' => $correctiveActions[10]
					];
					$correctiveActionList[] = 10;
					$shipment['is_excluded'] = 'yes';
				}
			}
		}

		foreach ($results as $result) {
			//if Sample is not mandatory, we will skip the evaluation
			if (0 == $result['mandatory']) {
				$this->db->update(
					'response_result_dts',
					['calculated_score' => "N.A."],
					[
						$this->db->quoteInto('shipment_map_id = ?', $result['map_id']),
						$this->db->quoteInto('sample_id = ?', $result['sample_id']),
					]
				);
				continue;
			}

			$reportedResultCode = $result['result_code'] ?? null;
			$reportedSyphilisResultCode = $result['syp_result_code'] ?? null;
			$reportedSyphilisResult = $result['syphilis_final'] ?? null;
			$expectedResultCode = $this->getResultCodeFromId($result['reference_result']);


			// Checking algorithm Pass/Fail only if it is NOT a control.
			if (0 == $result['control']) {
				$syphilisResult = $result1 = $result2 = $result3 = '';
				$repeatResult1 = $repeatResult2 = $repeatResult3 = '';
				if ($syphilisEnabled === true) {
					// getting syphilis result code : R, NR, I or -
					$syphilisResult = $this->getResultCodeFromId($result['syphilis_result'] ?? '');
				}

				// getting results from test_result_1 and test_result_2 : R, NR, I or -
				$result1 = $this->getResultCodeFromId($result['test_result_1'] ?? '');
				$result2 = $this->getResultCodeFromId($result['test_result_2'] ?? '');


				// getting results from test_result_1 and test_result_2 : R, NR, I or -
				$result1 = $this->getResultCodeFromId($result['test_result_1'] ?? '');
				$result2 = $this->getResultCodeFromId($result['test_result_2'] ?? '');


				if (!empty($attributes['algorithm']) && $attributes['algorithm'] != 'myanmarNationalDtsAlgo' && isset($config['dtsOptionalTest3']) && $config['dtsOptionalTest3'] == 'yes') {
					$result3 = 'X';
					$repeatResult3 = 'X';
				} else {
					// getting $result2 from test_result_3 : R, NR, I or -
					$result3 = $this->getResultCodeFromId($result['test_result_3'] ?? '');

					// getting repeat result from repeat_test_result_3 : R, NR, I or -
					$repeatResult3 = $this->getResultCodeFromId($result['repeat_test_result_3'] ?? '');
				}

				//$algoString = "Wrongly reported in the pattern : <strong>" . $result1 . "</strong> <strong>" . $result2 . "</strong> <strong>" . $result3 . "</strong>";
				$scorePercentageForAlgorithm = 0; // Most countries do not give score for getting algorithm right

				$failureReason ??= [];
				$correctiveActionList = $correctiveActionList ?? [];

				// derive RTRI context locally from $result
				$rtriEnabled ??= false; // keep existing flag if already set
				$didReportRTRI = (($result['dts_rtri_is_editable'] ?? '') === 'yes');
				$rtriReportedResult = $result['dts_rtri_reported_result'] ?? null;
				$rtriReferenceResult = $result['dts_rtri_reference_result'] ?? null;

				// line visibility used later
				$verificationLine = $result['dts_rtri_diagnosis_line'] ?? null;

				$algo = [
					'algoResult' => 'Fail',
					'scorePct' => 0.0,
					'sypAlgoResult' => null,
					'rtriAlgoResult' => null,
					'failureReason' => [],
					'correctiveActionList' => [],
				];

				// pull from the algo dispatcher (if available)
				$rtriAlgoResult = $algo['rtriAlgoResult'] ?? null;

				$correctiveActionList ??= [];


				$algo = $this->evaluateAlgorithm(
					$attributes,
					$dtsSchemeType,
					$result,                // full sample row (for sample_label/RTRI fields)
					$result1,
					$result2,
					$result3,
					$reportedResultCode,
					$expectedResultCode ?? null,
					$repeatResult1,
					$repeatResult2,
					$isScreening,
					$isConfirmatory,
					$rtriEnabled,
					$syphilisEnabled,
					$syphilisResult ?? null,
					$reportedSyphilisResultCode ?? null,
					$correctiveActions,
					$possibleRecencyResults
				);

				$algoResult = $algo['algoResult'];
				$scorePercentageForAlgorithm = $algo['scorePct'];            // 0 or 0.5 for Myanmar
				$sypAlgoResult = $algo['sypAlgoResult'];
				$rtriAlgoResult = $algo['rtriAlgoResult'];
				$failureReason = [...$failureReason, ...$algo['failureReason']];
				$correctiveActionList = [...$correctiveActionList, ...$algo['correctiveActionList']];
			} else {
				// CONTROLS
				// If there are two kits used for the participants then the control
				// needs to be tested with at least both kit.
				// If three then all three kits required and one then atleast one.

				foreach ($testKitMeta as $kitIndex => $fields) {
					if ($testKitNames[$kitIndex] !== "") {
						if (!isset($result[$fields['resultField']]) || $result[$fields['resultField']] == "") {
							$controlTesKitFail = 'Fail';
							$failureReason[] = [
								'warning' => "For the Control <strong>" . $result['sample_label'] . "</strong>, Test Kit {$kitIndex} (<strong>" . $testKitNames[$kitIndex] . "</strong>) was not used",
								'correctiveAction' => $correctiveActions[2]
							];
							$correctiveActionList[] = 2;
						}
					}
				}

				// END OF CONTROLS
			}
			$algScore = $config['dtsAlgorithmScore'] ?? 0;
			// Ensure $scorePercentageForAlgorithm is always between 0 and 1 (as a fraction)
			if (isset($algScore) && !empty($algScore) && $algScore > 0) {
				$scorePercentageForAlgorithm = ($algScore > 1) ? ($algScore / 100) : $algScore;
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
							$totalScore += $scoreForSample + $scoreForAlgorithm;
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
			$maxScore += $scoreForSample + $scoreForAlgorithm;

			$interpretationResult = ($result['reference_result'] == $result['reported_result']) ? 'Pass' : 'Fail';

			foreach ($testKitMeta as $kitIndex => $fields) {
				$kitResultValue = $result[$fields['resultField']] ?? null;
				if (isset($kitResultValue) && !empty($kitResultValue) && trim((string) $kitResultValue) != false && trim((string) $kitResultValue) != '24') {
					//T.1 Ensure test kit name is reported for all performed tests.
					if ($testKitNames[$kitIndex] === "") {
						$failureReason[] = [
							'warning' => "Result not evaluated : name of Test kit {$kitIndex} not reported.",
							'correctiveAction' => $correctiveActions[7]
						];
						$correctiveActionList[] = 7;
						$shipment['is_excluded'] = 'yes';
					}
					//T.5 Ensure expiry date information is submitted for all performed tests.
					//T.15 Testing performed with a test kit that is not recommended by MOH
					if (
						(isset($testKitExpired[$kitIndex]) && $testKitExpired[$kitIndex]) ||
						(isset($testKitRecommendedUsed[$kitIndex]) && $testKitRecommendedUsed[$kitIndex] === false)
					) {
						$testKitExpiryResult = 'Fail';
						$totalScore = 0;
						$correctResponse = false;
						$algoResult = 'Fail';
						$interpretationResult = 'Fail';
					}
				}
			}



			$this->db->update(
				'response_result_dts',
				[
					'calculated_score' => ($correctResponse && $algoResult != 'Fail' && $mandatoryResult != 'Fail' && $result['reference_result'] == $result['reported_result']) ? "Pass" : "Fail",
					'algorithm_result' => $algoResult,
					'interpretation_result' => $interpretationResult,
				],
				[
					$this->db->quoteInto('shipment_map_id = ?', $result['map_id']),
					$this->db->quoteInto('sample_id = ?', $result['sample_id']),
				]
			);
		}

		$configuredDocScore = (isset($config['documentationScore']) && (int) $config['documentationScore'] > 0) ? $config['documentationScore'] : 0;


		// Response Score
		if ($maxScore == 0 || $totalScore == 0) {
			$responseScore = 0;
		} else {
			$responseScore = round(($totalScore / $maxScore) * 100 * (100 - $configuredDocScore) / 100, 2);
		}

		if (empty($attributes['algorithm']) || strtolower($attributes['algorithm']) == 'not-reported') {
			$failureReason[] = [
				'warning' => "Result not evaluated. Testing algorithm not reported.",
				'correctiveAction' => $correctiveActions[2]
			];
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
		if ($dtsSchemeType == 'myanmar' || $attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
			$totalDocumentationItems -= 1;
		}

		if ($dtsSchemeType == 'malawi' || $attributes['algorithm'] == 'malawiNationalDtsAlgo') {
			// For Malawi we have 4 more documentation items to consider - Sample Condition, Fridge, Stop Watch and Room Temp
			$totalDocumentationItems += 4;
		}
		$docScore = $config['documentationScore'] ?? 0;
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
				$failureReason[] = [
					'warning' => "Missing reporting rehydration date for DTS Panel",
					'correctiveAction' => $correctiveActions[12]
				];
				$correctiveActionList[] = 12;
			}
		}

		//D.5
		if (isset($results[0]['shipment_test_date']) && trim($results[0]['shipment_test_date']) != "") {
			$documentationScore += $documentationScorePerItem;
		} else {
			$failureReason[] = [
				'warning' => "Shipment test date not provided",
				'correctiveAction' => $correctiveActions[13]
			];
			$correctiveActionList[] = 13;
		}
		//D.7
		if (isset($shipmentAttributes['sampleType']) && $shipmentAttributes['sampleType'] == 'dried') {

			// Only for Dried samples we will do this check

			// Testing should be done within 24*($config->evaluation->dts->sampleRehydrateDays) hours of rehydration.
			$sampleRehydrateDays = null;
			$interval = null;
			if (!empty($attributes['sample_rehydration_date'])) {
				$sampleRehydrationDate = new DateTimeImmutable($attributes['sample_rehydration_date']);
				$testedOnDate = new DateTimeImmutable($results[0]['shipment_test_date']);
				$interval = $sampleRehydrationDate->diff($testedOnDate);
				$sampleRehydrateDays = $config['sampleRehydrateDays'];
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
				$failureReason[] = [
					'warning' => "Condition of PT Samples not reported",
					'correctiveAction' => $correctiveActions[18]
				];
				$correctiveActionList[] = 18;
			}
			if (!empty($attributes['refridgerator'])) {
				$documentationScore += $documentationScorePerItem;
			} else {
				$failureReason[] = [
					'warning' => "Refridgerator availability not reported",
					'correctiveAction' => $correctiveActions[19]
				];
				$correctiveActionList[] = 18;
			}
			if (!empty($attributes['room_temperature'])) {
				$documentationScore += $documentationScorePerItem;
			} else {
				$failureReason[] = [
					'warning' => "Room Temperature not reported",
					'correctiveAction' => $correctiveActions[20]
				];
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
		$grandTotal = $responseScore + $documentationScore;
		$passPercentage = $config['passPercentage'] ?? 100;
		if ($grandTotal < $config['passPercentage']) {
			$scoreResult = 'Fail';
			$failureReason[] = [
				'warning' => "Participant did not meet the score criteria (Participant Score is <strong>" . round($grandTotal) . "</strong> and Required Score is <strong>" . round($config->evaluation->dts->passPercentage) . "</strong>)",
				'correctiveAction' => $correctiveActions[15]
			];
			$correctiveActionList[] = 15;
		} else {
			$scoreResult = 'Pass';
		}

		// if we are excluding this result, then let us not give pass/fail
		if ($shipment['is_excluded'] == 'yes' || $shipment['is_pt_test_not_performed'] == 'yes') {
			$shipment['is_excluded'] = 'yes';
			$shipment['is_followup'] = 'yes';
			$shipmentResultEntry['shipment_score'] = $responseScore = 0;
			$shipmentResultEntry['documentation_score'] = 0;
			$shipmentResultEntry['display_result'] = '';
			$failureReason[] = ['warning' => 'Excluded from Evaluation'];
			$finalResult = 3;
			$shipmentResultEntry['failure_reason'] = $failureReason = json_encode($failureReason);
		} else {
			$shipment['is_excluded'] = 'no';

			// if any of the results have failed, then the final result is fail
			if ($algoResult == 'Fail' || $scoreResult == 'Fail' || $lastDateResult == 'Fail' || $mandatoryResult == 'Fail' || $lotResult == 'Fail' || $testKitExpiryResult == 'Fail') {
				$finalResult = 2;
				$shipmentResultEntry['is_followup'] = 'yes';
				$shipment['is_followup'] = 'yes';
			} else {
				$shipment['is_excluded'] = 'no';
				$shipment['is_followup'] = 'no';
				$finalResult = 1;
			}
			$shipmentResultEntry['shipment_score'] = $responseScore;
			$shipmentResultEntry['documentation_score'] = $documentationScore;
			$scoreHolderEntry[$shipment['map_id']] = $responseScore + $documentationScore;


			$shipmentResultEntry['display_result'] = $finalResultArray[$finalResult];
			$shipmentResultEntry['failure_reason'] = $failureReason = (isset($failureReason) && !empty($failureReason)) ? json_encode($failureReason) : "";
			//$shipmentResultEntry['corrective_actions'] = implode(",",$correctiveActionList);
		}

		$shipmentResultEntry['max_score'] = $maxScore;
		$shipmentResultEntry['final_result'] = $finalResult;
		if ($shipment['is_excluded'] == 'yes' || $shipment['is_pt_test_not_performed'] == 'yes') {
			// let us update the total score in DB
			$this->db->update(
				'shipment_participant_map',
				[
					'shipment_score' => 0,
					'documentation_score' => 0,
					'final_result' => 3,
					'is_followup' => 'yes',
					'is_excluded' => 'yes',
					'failure_reason' => $failureReason,
					'is_response_late' => $shipment['is_response_late']
				],
				$mapWhere
			);
		} else {
			/* Manual result override changes */
			if (isset($shipment['manual_override']) && $shipment['manual_override'] == 'yes') {
				$sql = $this->db->select()->from('shipment_participant_map')->where("map_id = ?", $shipment['map_id']);
				$shipmentOverall = $this->db->fetchRow($sql);
				if (!empty($shipmentOverall)) {
					$shipmentResultEntry['shipment_score'] = $shipmentOverall['shipment_score'];
					$shipmentResultEntry['documentation_score'] = $shipmentOverall['documentation_score'];
					if (!isset($shipmentOverall['final_result']) || $shipmentOverall['final_result'] == "") {
						$shipmentOverall['final_result'] = 2;
					}

					$shipmentResultEntry['display_result'] = $finalResultArray[$shipmentOverall['final_result']];
					$this->db->update(
						'shipment_participant_map',
						[
							'shipment_score' => $shipmentOverall['shipment_score'],
							'documentation_score' => $shipmentOverall['documentation_score'],
							'final_result' => $shipmentOverall['final_result'],
						],
						$mapWhere
					);
				}
			} else {

				// let us update the total score in DB
				$this->db->update(
					'shipment_participant_map',
					[
						'shipment_score' => $responseScore,
						'documentation_score' => $documentationScore,
						'final_result' => $finalResult,
						'is_followup' => $shipment['is_followup'],
						'is_excluded' => $shipment['is_excluded'],
						'failure_reason' => $failureReason,
						'is_response_late' => $shipment['is_response_late']
					],
					$mapWhere
				);
			}
		}

		$nofOfRowsDeleted = $this->db->delete('dts_shipment_corrective_action_map', $this->db->quoteInto('shipment_map_id = ?', $shipment['map_id']));
		if ($shipment['is_excluded'] != 'yes' && $shipment['is_pt_test_not_performed'] != 'yes') {
			$correctiveActionList = array_unique($correctiveActionList);
			foreach ($correctiveActionList as $ca) {
				$this->db->insert('dts_shipment_corrective_action_map', array('shipment_map_id' => $shipment['map_id'], 'corrective_action_id' => $ca));
			}
		}

		$shipmentResultEntry = array_merge($shipment, $shipmentResultEntry);

		return [
			'shouldSkip' => false,
			'shipmentResultEntry' => $shipmentResultEntry,
			'scoreHolderEntry' => $scoreHolderEntry,
			'maxScore' => $maxScore,
		];
	}
	public function getDtsSamples($sId, $pId = null)
	{
		$sql = $this->db->select()->from(['ref' => 'reference_result_dts'])
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
		$sql = $this->db->select()->from(['dts_recommended_testkits']);

		if ($testNumber != null && (int) $testNumber > 0 && (int) $testNumber <= 3) {
			$sql = $sql->where('test_no = ?', (int) $testNumber);
		}

		if ($testMode != null) {
			$sql = $sql->where('dts_test_mode = ?', $testMode);
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
			->joinLeft(['stm' => 'scheme_testkit_map'], 't.TestKitName_ID = stm.testkit_id', ['scheme_type', 'testkit_1', 'testkit_2', 'testkit_3'])
			->order("TESTKITNAME ASC");
		if ($stage == 'custom-tests') {
		} elseif (isset($stage) && !empty($stage) && !in_array($stage, ['testkit_1', 'testkit_2', 'testkit_3'])) {
			$sql = $sql->where('scheme_type != ?', $stage);
		} else {
			$sql = $sql->where('scheme_type = ?', 'dts');
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

		//$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
		//$config = new Zend_Config_Ini($file, APPLICATION_ENV);
		$config = json_decode(Pt_Commons_SchemeConfig::get('dts'));

		$finalResultArray = $this->getFinalResults();

		$excel = new Spreadsheet();

		$borderStyle = [
			'alignment' => [
				'horizontal' => Alignment::HORIZONTAL_CENTER,
			],
			'borders' => [
				'outline' => [
					'style' => Border::BORDER_THIN,
				],
			]
		];

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
		$kit1Result = (array) json_decode($kitResult['kit1Attributes']);
		$kit2Result = (array) json_decode($kitResult['kit2Attributes']);
		$kit3Result = (array) json_decode($kitResult['kit3Attributes']);
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

			foreach ($shipmentResult as $aRow) {
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
		$reportHeadings = [
			$this->translator->_('Participant Code'),
			$this->translator->_('Participant Name'),
			$this->translator->_('Institute Name'),
			$this->translator->_('Province'),
			$this->translator->_('District'),
			$this->translator->_('Shipment Receipt Date'),
			$this->translator->_('Test Type'),
			$this->translator->_('Sample Rehydration Date'),
			$this->translator->_('Testing Date'),
			$this->translator->_('Reported On'),
			$this->translator->_('Test #1 Kit Name'),
			$this->translator->_('Kit Lot #1'),
			$this->translator->_('Expiry Date #1'),
			$this->translator->_('QC Done #1'),
			$this->translator->_('QC Expiry Date #1'),
		];
		if ((isset($config['displaySampleConditionFields']) && $config['displaySampleConditionFields'] == "yes")) {
			$reportHeadings = [
				$this->translator->_('Participant Code'),
				$this->translator->_('Participant Name'),
				$this->translator->_('Institute Name'),
				$this->translator->_('Province'),
				$this->translator->_('District'),
				$this->translator->_('Shipment Receipt Date'),
				$this->translator->_('Test Type'),
				$this->translator->_('Testing Date'),
				$this->translator->_('Reported On'),
				$this->translator->_('Condition Of PT Samples'),
				$this->translator->_('Refridgerator'),
				$this->translator->_('Room Temperature'),
				$this->translator->_('Stop Watch'),
				$this->translator->_('Test #1 Kit Name'),
				$this->translator->_('Kit Lot #1'),
				$this->translator->_('Expiry Date #1'),
				$this->translator->_('QC Done #1'),
				$this->translator->_('QC Expiry Date #1')
			];
		}

		$sampleLabels = $this->getSampleLabels($shipmentId);
		$reportHeadings = $this->appendSampleLabels($reportHeadings, $sampleLabels);
		if (isset($kit1Result['additional_info_label']) && !empty($kit1Result['additional_info_label'])) {
			// To search the kit name postion
			$index = array_search('QC Expiry Date#1', $reportHeadings);
			// Insert the value after this index
			foreach (range(($index + 1), (count($reportHeadings) - 1)) as $row) {
				$reportHeadings[] = $kit1Result['additional_info_label'] . ' for (' . $reportHeadings[$row] . ')';
			}
		}
		array_push(
			$reportHeadings,
			$this->translator->_('Test #2 Kit Name'),
			$this->translator->_('Kit Lot #2'),
			$this->translator->_('Expiry Date #2'),
			$this->translator->_('QC Done #2'),
			$this->translator->_('QC Expiry Date #2')
		);
		$reportHeadings = $this->appendSampleLabels($reportHeadings, $sampleLabels);
		if (isset($kit2Result['additional_info_label']) && !empty($kit2Result['additional_info_label'])) {
			// To search the kit name postion
			$index = array_search('QC Expiry Date#2', $reportHeadings);
			// Insert the value after this index
			foreach (range(($index + 1), (count($reportHeadings) - 1)) as $row) {
				$reportHeadings[] = $kit2Result['additional_info_label'] . ' for (' . $reportHeadings[$row] . ')';
			}
		}

		$dtsSchemeType = (isset($config['dtsSchemeType']) && $config['dtsSchemeType'] != "") ? $config['dtsSchemeType'] : 'standard';
		$participantAttributes = [];
		if (!empty($shipmentResult[0]['attributes'])) {
			$participantAttributes = is_array($shipmentResult[0]['attributes'])
				? $shipmentResult[0]['attributes']
				: (json_decode($shipmentResult[0]['attributes'], true) ?? []);
		}

		$panelSettings = $this->getDtsPanelSettings(
			$config,
			$shipmentAttributes,
			$participantAttributes,
			$dtsSchemeType
		);
		$testThreeHidden = $panelSettings['testThreeHidden'];

		if ($testThreeHidden !== true) {
			array_push(
				$reportHeadings,
				$this->translator->_('Test#3 Kit Name'),
				$this->translator->_('Kit Lot #3'),
				$this->translator->_('Expiry Date #3'),
				$this->translator->_('QC Done #3'),
				$this->translator->_('QC Expiry Date #3')
			);
			$reportHeadings = $this->appendSampleLabels($reportHeadings, $sampleLabels);
			if (isset($kit3Result['additional_info_label']) && !empty($kit3Result['additional_info_label'])) {
				// To search the kit name postion
				$index = array_search('QC Expiry Date#3', $reportHeadings);
				// Insert the value after this index
				foreach (range($index + 1, count($reportHeadings) - 1) as $row) {
					$reportHeadings[] = $kit3Result['additional_info_label'] . ' for (' . $reportHeadings[$row] . ')';
				}
			}
		}
		$addWithFinalResultCol = 2;
		/* Repeat test section */
		if (isset($config['allowRepeatTests']) && $config['allowRepeatTests'] == 'yes') {
			$reportHeadings = $this->appendSampleLabels($reportHeadings, $sampleLabels);
			$reportHeadings = $this->appendSampleLabels($reportHeadings, $sampleLabels);
			// $addWithFinalResultCol = 0;
			if ($testThreeHidden !== true) {
				$reportHeadings = $this->appendSampleLabels($reportHeadings, $sampleLabels);
				// $addWithFinalResultCol = -1;
			}
		}
		// For final result
		$finalResultsStartIndex = count($reportHeadings);
		$reportHeadings = $this->appendSampleLabels($reportHeadings, $sampleLabels);
		// For RTRI and test results final result
		$rtriPanelStartIndex = null;
		$rtriPanelEndIndex = null;
		$rtriFinalStartIndex = null;
		$rtriFinalEndIndex = null;
		if (isset($shipmentAttributes['enableRtri']) && $shipmentAttributes['enableRtri'] == 'yes') {
			$rtriPanelStartIndex = count($reportHeadings);
			// foreach ($sampleLabels as $label) {
			// 	$reportHeadings[] = $label;
			// }
			for ($i = 0; $i < 3; $i++) {
				foreach ($sampleLabels as $label) {
					$reportHeadings[] = $label;
				}
			}
			$rtriFinalStartIndex = count($reportHeadings);
			foreach ($sampleLabels as $label) {
				$reportHeadings[] = $label;
			}
			$rtriPanelEndIndex = $rtriFinalStartIndex - 1;
			$rtriFinalEndIndex = count($reportHeadings) - 1;
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
		array_push(
			$reportHeadings,
			$this->translator->_('Comments')
		);
		$finalResultStartCellCount += 1;
		$finalResultStartCellCount += $result['number_of_samples'];
		if ($result['number_of_controls'] > 0)
			$finalResultStartCellCount += $result['number_of_controls'];


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
		$finalResColoumn = $finalResultsStartIndex;

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
			$totalSamples = $result['number_of_samples'] + $result['number_of_controls'];
			$rtriHeadingColumn = $rtriPanelStartIndex + 1;
			$endRtriMergeCell = $rtriPanelEndIndex + 1;
			$rtriFinalStartColumn = $rtriFinalStartIndex + 1;
			$rtriFinalEndColumn = $rtriFinalEndIndex + 1;
			$rtriFirstCellName = Coordinate::stringFromColumnIndex($rtriHeadingColumn);
			$rtriSecondCellName = Coordinate::stringFromColumnIndex($endRtriMergeCell);
			$resultsReportedSheet->mergeCells($rtriFirstCellName . "1:" . $rtriSecondCellName . "1");
			$resultsReportedSheet->getStyle($rtriFirstCellName . "1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFAB00');
			$resultsReportedSheet->getStyle($rtriFirstCellName . "1")->applyFromArray($borderStyle, true);
			$resultsReportedSheet->getStyle($rtriSecondCellName . "1")->applyFromArray($borderStyle, true);
			/* RTRI Final result merge section */
			$rtriFirstCellName = Coordinate::stringFromColumnIndex($rtriFinalStartColumn);
			$rtriSecondCellName = Coordinate::stringFromColumnIndex($rtriFinalEndColumn);
			$resultsReportedSheet->mergeCells($rtriFirstCellName . "1:" . $rtriSecondCellName . "1");
			$resultsReportedSheet->getStyle($rtriFirstCellName . "1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
			$resultsReportedSheet->getStyle($rtriFirstCellName . "1")->applyFromArray($borderStyle, true);
			$resultsReportedSheet->getStyle($rtriSecondCellName . "1")->applyFromArray($borderStyle, true);
		}
		/* Repeat test section */
		if (isset($config['allowRepeatTests']) && $config['allowRepeatTests'] == 'yes') {
			$repeatHeadingColumn = $n - (($result['number_of_samples'] * 3) + $result['number_of_controls'] + 1);
			if ($testThreeHidden !== true) {
				$repeatHeadingColumn = $n - ($result['number_of_samples'] * 4 + $result['number_of_controls'] + 1);
			}
			$endRepeatMergeCell = ($repeatHeadingColumn + ($result['number_of_samples'] * 2) + $result['number_of_controls']);
			if ($testThreeHidden !== true) {
				$endRepeatMergeCell = ($repeatHeadingColumn + ($result['number_of_samples'] * 3) + $result['number_of_controls']);
			}
			$repeatFirstCellName = Coordinate::stringFromColumnIndex($repeatHeadingColumn + 1);
			$repeatSecondCellName = Coordinate::stringFromColumnIndex($endRepeatMergeCell);
			$resultsReportedSheet->mergeCells($repeatFirstCellName . "1:" . $repeatSecondCellName . "1");
			$resultsReportedSheet->getStyle($repeatFirstCellName . "1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
			$resultsReportedSheet->getStyle($repeatFirstCellName . "1")->applyFromArray($borderStyle, true);
			$resultsReportedSheet->getStyle($repeatSecondCellName . "1")->applyFromArray($borderStyle, true);
		}
		foreach ($reportHeadings as $field => $value) {
			$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow, $value);
			$resultsReportedSheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow)->getFont()->setBold(true);
			$resultsReportedSheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow)->applyFromArray($borderStyle, true);

			$resultsReportedSheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . "3")->applyFromArray($borderStyle, true);

			$resultsReportedSheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . '3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFA0A0A0');
			$resultsReportedSheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . '3')->getFont()->getColor()->setARGB('FFFFFF00');
			/* Repeat test section */
			if (isset($config['allowRepeatTests']) && $config['allowRepeatTests'] == 'yes') {
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
				if ($colNo >= $rtriFinalStartColumn) {
					if ($z <= ($result['number_of_samples'] + $result['number_of_controls'])) {
						$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '1', "RTRI Final Results");
						$resultsReportedSheet->getStyle(Coordinate::stringFromColumnIndex($colNo) . $currentRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
						$l = $z - 1;
						$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '3', $refResult[$l]['rtriReferenceResult'] ?? '');
					}
					$z++;
				}
			}
			$colNo++;
		}

		//$shipmentAttributes = json_decode($aRow['shipment_attributes'], true);

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
		$docScore = $config['documentationScore'] ?? 0;
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

						if ($testThreeHidden !== true) {
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
					if ($testThreeHidden !== true) {
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
				$rtriEnabled = (isset($shipmentAttributes['enableRtri']) && $shipmentAttributes['enableRtri'] == 'yes');
				if ($rtriEnabled) {
					$totalSamples = $result['number_of_samples'] + $result['number_of_controls'];
					$r = $rtriHeadingColumn + $totalSamples;
				} else {
					$r = 1;
				}
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

				if (!isset($config['displaySampleConditionFields']) || $config['displaySampleConditionFields'] != 'yes') {
					$resultReportRow[] = $rehydrationDate;
				}

				$resultReportRow[] = Pt_Commons_General::excelDateFormat($aRow['shipment_test_date']);
				$resultReportRow[] = Pt_Commons_General::excelDateFormat($aRow['shipment_test_report_date']);

				if (isset($config['displaySampleConditionFields']) && $config['displaySampleConditionFields'] == 'yes') {

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

				if (isset($aRow['shipment_test_date']) && Common::isDateValid($aRow['shipment_test_date'])) {
					$docScoreRow[] = $documentationScorePerItem;
				} else {
					$docScoreRow[] = 0;
				}

				if (isset($attributes['algorithm']) && $attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
					$docScoreRow[] = '-';
				} elseif (isset($sampleRehydrationDate) && isset($aRow['shipment_test_date']) && Common::isDateValid($aRow['shipment_test_date'])) {

					$sampleRehydrationDate = new DateTimeImmutable($attributes['sample_rehydration_date']);
					$testedOnDate = new DateTimeImmutable($aRow['shipment_test_date']);
					$interval = $sampleRehydrationDate->diff($testedOnDate);

					// Testing should be done within 24*($config->evaluation->dts->sampleRehydrateDays) hours of rehydration.
					$sampleRehydrateDays = $config['sampleRehydrateDays'];
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
				$documentScore = !empty($config['documentationScore']) && (int) $config['documentationScore'] > 0 ? (($aRow['documentation_score'] / $config['documentationScore']) * 100) : 0;
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
								$additionalValue = (array) json_decode($participantResponse[$k]['kit_additional_info']);
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
					if ($testThreeHidden !== true) {
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
								$additionalValue = (array) json_decode($participantResponse[$k]['kit_additional_info']);
								$resultReportRow[] = $additionalValue['test3'];
							}
						}
					}

					// Repeat Tests
					if (isset($config['allowRepeatTests']) && $config['allowRepeatTests'] == 'yes') {
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$resultReportRow[] = $participantResponse[$k]['repeatTestResult1'];
						}
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$resultReportRow[] = $participantResponse[$k]['repeatTestResult2'];
						}
						if ($testThreeHidden !== true) {
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
						// for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
						// 	/* For showing samples labels */
						// 	$resultReportRow[] = $refResult[$k]['sample_label'];
						// }
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$participantResponse[$k]['dts_rtri_control_line'] = (isset($participantResponse[$k]['dts_rtri_control_line']) && $participantResponse[$k]['dts_rtri_control_line']) ? $participantResponse[$k]['dts_rtri_control_line'] : null;
							$rr = $r++;
							$resultReportRow[] = ucwords($participantResponse[$k]['dts_rtri_control_line']);
							/* Merge titiles */
							if ($k == 0) {
								$controlStart = $rtriHeadingColumn;
								/* For showing which sample for wich tittle */
								$rtriFirstCellName = Coordinate::stringFromColumnIndex($controlStart);
								$rtriSecondCellName = Coordinate::stringFromColumnIndex(($controlStart + $totalSamples - 1));
								$resultsReportedSheet->mergeCells($rtriFirstCellName . "3:" . $rtriSecondCellName . "3");
								$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($controlStart) . '3', "Control Line");
							}
						}
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$participantResponse[$k]['dts_rtri_diagnosis_line'] = (isset($participantResponse[$k]['dts_rtri_diagnosis_line']) && $participantResponse[$k]['dts_rtri_diagnosis_line']) ? $participantResponse[$k]['dts_rtri_diagnosis_line'] : null;
							$rr = $r++;
							$resultReportRow[] = ucwords($participantResponse[$k]['dts_rtri_diagnosis_line']);
							/* Merge titiles */
							if ($k == 0) {
								$verificationStart = $rtriHeadingColumn + $totalSamples;
								/* For showing which sample for wich tittle */
								$rtriFirstCellName = Coordinate::stringFromColumnIndex($verificationStart);
								$rtriSecondCellName = Coordinate::stringFromColumnIndex(($verificationStart + $totalSamples - 1));
								$resultsReportedSheet->mergeCells($rtriFirstCellName . "3:" . $rtriSecondCellName . "3");
								$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($verificationStart) . '3', "Verification Line");
							}
						}
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$participantResponse[$k]['dts_rtri_longterm_line'] = (isset($participantResponse[$k]['dts_rtri_longterm_line']) && $participantResponse[$k]['dts_rtri_longterm_line']) ? $participantResponse[$k]['dts_rtri_longterm_line'] : null;
							$rr = $r++;
							$resultReportRow[] = ucwords($participantResponse[$k]['dts_rtri_longterm_line']);
							/* Merge titiles */
							if ($k == 0) {
								$longtermStart = $rtriHeadingColumn + ($totalSamples * 2);
								/* For showing which sample for wich tittle */
								$rtriFirstCellName = Coordinate::stringFromColumnIndex($longtermStart);
								$rtriSecondCellName = Coordinate::stringFromColumnIndex(($longtermStart + $totalSamples - 1));
								$resultsReportedSheet->mergeCells($rtriFirstCellName . "3:" . $rtriSecondCellName . "3");
								$resultsReportedSheet->setCellValue(Coordinate::stringFromColumnIndex($longtermStart) . '3', "Longterm Line");
							}
						}
						for ($k = 0; $k < ($aRow['number_of_samples'] + $aRow['number_of_controls']); $k++) {
							$participantResponse[$k]['rtrifinalResult'] = (isset($participantResponse[$k]['rtrifinalResult']) && $participantResponse[$k]['rtrifinalResult']) ? $participantResponse[$k]['rtrifinalResult'] : null;
							$resultReportRow[] = ucwords($participantResponse[$k]['rtrifinalResult']);
						}
						/* -- RTRI SECTION END -- */
					}

					if (!empty($haveCustom) && $haveCustom == 'yes') {
						if (isset($customField1) && $customField1 != "") {
							$resultReportRow[] = $aRow['custom_field_1'];
						}
						if (isset($customField2) && $customField2 != "") {
							$resultReportRow[] = $aRow['custom_field_2'];
						}
					}

					$resultReportRow[] = $aRow['user_comment'];

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
		$participantListHeadings = [
			$this->translator->_('Participant Code'),
			$this->translator->_('Participant Name'),
			$this->translator->_('Institute Name'),
			$this->translator->_('Department'),
			$this->translator->_('Country'),
			$this->translator->_('Address'),
			$this->translator->_('Province'),
			$this->translator->_('District'),
			$this->translator->_('City'),
			$this->translator->_('Telephone'),
			$this->translator->_('Email')
		];
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
		$panelScoreHeadings = [
			$this->translator->_('Participant Code'),
			$this->translator->_('Participant Name'),
			$this->translator->_('Institute Name'),
			$this->translator->_('Province')
		];
		$panelScoreHeadings = $this->addSampleNameInArray($shipmentId, $panelScoreHeadings);
		array_push($panelScoreHeadings, $this->translator->_('No. of Correct Responses'), $this->translator->_('% Correct'));
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
		$docScoreHeadings = [
			$this->translator->_('Participant Code'),
			$this->translator->_('Participant Name'),
			$this->translator->_('Institute Name'),
			$this->translator->_('Province'),
			$this->translator->_('Supervisor signature'),
			$this->translator->_('Panel Receipt Date'),
			$this->translator->_('Sample Rehydration Date'),
			$this->translator->_('Tested Date'),
			$this->translator->_('Rehydration Test In Specified Time'),
			$this->translator->_('Documentation Score %')
		];
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
		$totalScoreHeadings = [
			$this->translator->_('Participant Code'),
			$this->translator->_('Participant Name'),
			$this->translator->_('Institute Name'),
			$this->translator->_('Province'),
			$this->translator->_('District'),
			$this->translator->_('City'),
			$this->translator->_('Country'),
			$this->translator->_('No. of Correct Samples') . ' (N=' . $result['number_of_samples'] . ')',
			$this->translator->_('Panel Score'),
			$this->translator->_('Documentation Score'),
			$this->translator->_('Total Score'),
			$this->translator->_('Overall Performance'),
			$this->translator->_('Warnings and/or Reasons for Failure')
		];
		$totalScoreSheet->fromArray($totalScoreHeadings, null, 'A1');
		$totalScoreSheet->getStyle('A1:' . $totalScoreSheet->getHighestColumn() . '1')->applyFromArray($styleArray);

		$totalScoreSheet->fromArray($totalScoreSheetData, null, 'A2');
		unset($totalScoreSheetData, $totalScoreSheet);



		$excel->setActiveSheetIndex(0);

		$firstName = $authNameSpace->first_name;
		$lastName = $authNameSpace->last_name;

		$name = $firstName . " " . $lastName;
		$userName = isset($name) != '' ? $name : $authNameSpace->primary_email;
		$auditDb = new Application_Model_DbTable_AuditLog();
		$auditDb->addNewAuditLog("DTS Rapid HIV report downloaded by $userName", "shipment");

		$writer = IOFactory::createWriter($excel, 'Xlsx');
		$filename = $shipmentCode . '-' . date('d-M-Y-H-i-s') . '.xlsx';
		$writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
		return $filename;
	}

	private function getShipmentResult($shipmentId)
	{

		$authNameSpace = new Zend_Session_Namespace('datamanagers');

		$sql = $this->db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.number_of_samples', 's.number_of_controls', 'shipment_attributes'))
			->join(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', ['sp.map_id', 'sp.participant_id', 'sp.attributes', 'sp.shipment_test_date', 'sp.shipment_receipt_date', 'sp.shipment_test_report_date', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_score', 'sp.documentation_score', 'sp.final_result', 'sp.is_excluded', 'sp.failure_reason', 'sp.user_comment', 'sp.custom_field_1', 'sp.custom_field_2'])
			->join(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.lab_name', 'p.region', 'p.first_name', 'p.last_name', 'p.address', 'p.city', 'p.mobile', 'p.email', 'p.status', 'province' => 'p.state', 'p.district'])
			->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', ['pmm.dm_id'])
			->joinLeft(['dm' => 'data_manager'], 'dm.dm_id=pmm.dm_id', ['dm.institute', 'dataManagerFirstName' => 'dm.first_name', 'dataManagerLastName' => 'dm.last_name'])
			->joinLeft(['c' => 'countries'], 'c.id=p.country', ['iso_name'])
			->joinLeft(['st' => 'r_site_type'], 'st.r_stid=p.site_type', ['st.site_type'])
			->joinLeft(['en' => 'enrollments'], 'en.participant_id=p.participant_id', ['en.enrolled_on'])
			->where("s.shipment_id = ?", $shipmentId)
			->group(['sp.map_id']);
		if (!empty($authNameSpace->dm_id)) {
			$sql = $sql
				->where("pmm.dm_id = ?", $authNameSpace->dm_id);
		}
		return $this->db->fetchAll($sql);
	}
	private function getParticipantResponse($mapId)
	{

		$responseQuery = $this->db->select()->from(array('rrdts' => 'response_result_dts'))
			->joinLeft(['tk1' => 'r_testkitnames'], 'tk1.TestKitName_ID=rrdts.test_kit_name_1', ['testKitName1' => 'tk1.TestKit_Name'])
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

		$referenceResultsQuery = $this->db->select()->from(array('refRes' => 'reference_result_dts'), array('refRes.sample_label', 'sample_id', 'refRes.sample_score', 'refRes.dts_rtri_reference_result'))
			->joinLeft(array('r' => 'r_possibleresult'), 'r.id=refRes.reference_result', array('referenceResult' => 'r.response'))
			->joinLeft(array('rtri' => 'r_possibleresult'), 'rtri.id=refRes.dts_rtri_reference_result', array('rtriReferenceResult' => 'rtri.response'))
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

	private function getResultCodeFromId($resultId)
	{
		if ($resultId == null || $resultId == '') {
			return '-';
		}
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$query = $db->select()->from('r_possibleresult', ['result_code'])->where("id = ?", $resultId);
		return $db->fetchOne($query) ?? '-';
	}

	public function addSampleNameInArray($shipmentId, $headings)
	{
		foreach ($this->getSampleLabels($shipmentId) as $label) {
			$headings[] = $label;
		}
		return $headings;
	}

	private function getSampleLabels($shipmentId)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$query = $db->select()->from('reference_result_dts', ['sample_label'])
			->where("shipment_id = ?", $shipmentId)->order("sample_id");
		$result = $db->fetchAll($query);
		return array_column($result, 'sample_label');
	}

	private function appendSampleLabels(array $headings, array $sampleLabels): array
	{
		foreach ($sampleLabels as $label) {
			$headings[] = $label;
		}
		return $headings;
	}

	// Returns an array:
	// [
	//   'algoResult' => 'Pass'|'Fail',
	//   'scorePct' => float,            // e.g. 0.5 for Myanmar, else 0
	//   'sypAlgoResult' => 'Pass'|'Fail'|null,
	//   'rtriAlgoResult' => 'Pass'|'Fail'|null,
	//   'failureReason' => array<array{warning:string, correctiveAction:string}>,
	//   'correctiveActionList' => int[]
	// ]
	private function evaluateAlgorithm(
		array $attributes,
		string $dtsSchemeType,
		array $result,                            // full $result row (for sample_label + RTRI fields)
		?string $result1,
		?string $result2,
		?string $result3,
		?string $reportedResultCode,
		?string $expectedResultCode,
		string $repeatResult1 = '-',
		string $repeatResult2 = '-',
		bool $isScreening = false,
		bool $isConfirmatory = false,
		bool $rtriEnabled = false,
		bool $syphilisEnabled = false,
		?string $syphilisResult = null,
		?string $reportedSyphilisResultCode = null,
		array $correctiveActions = [],
		array $possibleRecencyResults = []
	): array {
		$out = [
			'algoResult' => 'Fail',
			'scorePct' => 0.0,
			'sypAlgoResult' => null,
			'rtriAlgoResult' => null,
			'failureReason' => [],
			'correctiveActionList' => [],
		];

		// Screening: no algorithm check
		if ($isScreening) {
			$out['algoResult'] = 'Pass';
			return $out;
		}

		// Confirmatory / updated-3-tests
		if ((isset($dtsSchemeType) && $dtsSchemeType === 'updated-3-tests') || $isConfirmatory) {

			$this->algoUpdatedThreeTests(
				$result,
				$result1,
				$result2,
				$result3,
				$repeatResult1,
				$reportedResultCode,
				$correctiveActions,
				$out
			);

			// Optional RTRI check (only if enabled & sample allowed)
			if ($rtriEnabled && (($result['dts_rtri_is_editable'] ?? '') === 'yes')) {
				$this->algoRTRI($result, $possibleRecencyResults, $out);
			}
			return $out;
		}

		// Serial
		elseif (($attributes['algorithm'] ?? '') === 'serial') {
			$this->algoSerial($result, $result1, $result2, $result3, $reportedResultCode, $correctiveActions, $out);
			return $out;
		}

		// Parallel
		elseif (($attributes['algorithm'] ?? '') === 'parallel') {
			$this->algoParallel($result, $result1, $result2, $result3, $reportedResultCode, $correctiveActions, $out);
			return $out;
		}

		// Sierra Leone
		elseif ($dtsSchemeType === 'sierraLeone' || ($attributes['algorithm'] ?? '') === 'sierraLeoneNationalDtsAlgo') {
			$this->algoSierraLeone($result, $result1, $result2, $result3, $reportedResultCode, $correctiveActions, $out);
			return $out;
		}

		// Cote d'Ivoire
		elseif ($dtsSchemeType === 'cotedivoire' || ($attributes['algorithm'] ?? '') === 'cotedivoireNationalDtsAlgo') {
			$this->algoCoteDivoire(
				$result,
				$result1,
				$result2,
				$result3,
				$reportedResultCode,
				$repeatResult1,
				$correctiveActions,
				$out
			);
			return $out;
		}


		// Myanmar (50% score weight if algorithm right)
		elseif ($dtsSchemeType === 'myanmar' || ($attributes['algorithm'] ?? '') === 'myanmarNationalDtsAlgo') {
			$this->algoMyanmar(
				$result,
				$result1,
				$result2,
				$result3,
				$expectedResultCode,
				$reportedResultCode,
				$correctiveActions,
				$out
			);
			return $out;
		}

		// Malawi
		elseif ($dtsSchemeType === 'malawi' || ($attributes['algorithm'] ?? '') === 'malawiNationalDtsAlgo') {
			$this->algoMalawi(
				$result,
				$result1,
				$result2,
				$reportedResultCode,
				$repeatResult1,
				$repeatResult2,
				$correctiveActions,
				$out
			);
			return $out;
		}

		// Ghana (+ optional syphilis)
		elseif ($dtsSchemeType === 'ghana') {
			if ($syphilisEnabled) {
				$out['sypAlgoResult'] = $this->algoGhanaSyphilis($syphilisResult, $reportedSyphilisResultCode);
			}
			$this->algoGhana(
				$result,
				$result1,
				$result2,
				$result3,
				$reportedResultCode,
				$repeatResult1,
				$repeatResult2,
				$correctiveActions,
				$out
			);
			return $out;
		}

		// Default: Fail with standard warning
		$this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
		return $out;
	}


	private function warningForAlgo(array &$out, array $correctiveActions, string $sampleLabel)
	{
		$out['algoResult'] = 'Fail';
		$out['failureReason'][] = [
			'warning' => "For <strong>{$sampleLabel}</strong> National HIV Testing algorithm was not followed.",
			'correctiveAction' => $correctiveActions[2] ?? ''
		];
		$out['correctiveActionList'][] = 2;
	}

	private function normalizeAlgoResult(?string $result): string
	{
		return (in_array(trim($result), [null, '', 'X', 'x'], true)) ? '-' : $result;
	}

	/** Updated-3-tests / confirmatory path */
	private function algoUpdatedThreeTests(
		array $result,
		?string $result1,
		?string $result2,
		?string $result3,
		?string $repeatResult1,
		?string $reportedResultCode,
		array $correctiveActions,
		array &$out
	) {

		$result1 = $this->normalizeAlgoResult($result1);
		$result2 = $this->normalizeAlgoResult($result2);
		$result3 = $this->normalizeAlgoResult($result3);
		$repeatResult1 = $this->normalizeAlgoResult($repeatResult1);

		if ($result1 == 'NR' && $reportedResultCode == 'N') {
			if ($result2 == '-' && $result3 == '-' && $repeatResult1 == '-') {
				$out['algoResult'] = 'Pass';
			} else {
				$this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
			}
		} elseif ($result1 == 'R') {
			if ($result2 == 'R' && $reportedResultCode == 'P' && $repeatResult1 == '-') {
				$out['algoResult'] = 'Pass';
			} elseif ($result2 == 'NR') {
				if ($repeatResult1 == 'NR' && $reportedResultCode == 'N') {
					$out['algoResult'] = 'Pass';
				} elseif ($repeatResult1 == 'R' && $reportedResultCode == 'I') {
					$out['algoResult'] = 'Pass';
				} else {
					$this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
				}
			} else {
				$this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
			}
		} else {
			$this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
		}
	}

	/** RTRI rule-check, only sets rtriAlgoResult; leaves HIV algo as-is */
	private function algoRTRI(array $result, array $possibleRecencyResults, array &$out)
	{
		$control = $result['dts_rtri_control_line'] ?? '';
		$verify = $result['dts_rtri_diagnosis_line'] ?? '';
		$longterm = $result['dts_rtri_longterm_line'] ?? '';
		$refRes = $result['dts_rtri_reference_result'] ?? null;

		$r = 'Pass'; // optimistic; fail on rule breaks

		// basic invalids
		if ((empty($control) && empty($verify) && empty($longterm)) || $control === 'absent') {
			$r = 'Fail';
		}

		if ($refRes === ($possibleRecencyResults['N'] ?? null)) {
			if (!($control === 'present' && $verify === 'absent' && $longterm === 'absent')) {
				$r = 'Fail';
			}
		} elseif ($refRes === ($possibleRecencyResults['R'] ?? null)) {
			if (!($control === 'present' && $verify === 'present' && $longterm === 'absent')) {
				$r = 'Fail';
			}
		} elseif ($refRes === ($possibleRecencyResults['LT'] ?? null)) {
			if (!($control === 'present' && $verify === 'present' && $longterm === 'present')) {
				$r = 'Fail';
			}
		}

		$out['rtriAlgoResult'] = $r;
	}

	/** Serial */
	private function algoSerial(
		array $result,
		?string $result1,
		?string $result2,
		?string $result3,
		?string $reportedResultCode,
		array $correctiveActions,
		array &$out
	) {
		if ($result1 === 'NR') {
			if ($result2 === '-' && ($result3 === '-' || $result3 === 'X')) {
				$out['algoResult'] = 'Pass';
				return;
			}
			return $this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
		}

		if ($result1 === 'R' && $result2 === 'NR' && $result3 === 'NR') {
			$out['algoResult'] = 'Pass';
			return;
		}

		if ($result1 === 'R' && $result2 === 'R') {
			if (in_array($result3, ['R', '-', 'X'], true)) {
				$out['algoResult'] = 'Pass';
				return;
			}
			return $this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
		}

		if ($result1 === 'R' && $result2 === 'NR' && in_array($result3, ['R', 'X'], true)) {
			$out['algoResult'] = 'Pass';
			return;
		}

		$this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
	}

	/** Parallel */
	private function algoParallel(
		array $result,
		?string $result1,
		?string $result2,
		?string $result3,
		?string $reportedResultCode,
		array $correctiveActions,
		array &$out
	) {
		if ($result1 === 'R' && $result2 === 'R') {
			if (in_array($result3, ['-', 'X'], true)) {
				$out['algoResult'] = 'Pass';
				return;
			}
			return $this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
		}

		if ($result1 === 'R' && $result2 === 'NR' && in_array($result3, ['R', 'X', 'NR'], true)) {
			$out['algoResult'] = 'Pass';
			return;
		}

		if ($result1 === 'NR' && $result2 === 'NR') {
			if (in_array($result3, ['-', 'X'], true)) {
				$out['algoResult'] = 'Pass';
				return;
			}
			return $this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
		}

		if ($result1 === 'NR' && $result2 === 'R' && in_array($result3, ['NR', 'X', 'R'], true)) {
			$out['algoResult'] = 'Pass';
			return;
		}

		$this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
	}

	/** Sierra Leone */
	private function algoSierraLeone(
		array $result,
		?string $result1,
		?string $result2,
		?string $result3,
		?string $reportedResultCode,
		array $correctiveActions,
		array &$out
	) {
		if ($result1 === 'NR' && $result2 === '-' && $result3 === '-' && $reportedResultCode === 'N') {
			$out['algoResult'] = 'Pass';
			return;
		}
		if ($result1 === 'R' && $result2 === 'R' && $result3 === '-' && in_array($reportedResultCode, ['P', 'R'], true)) {
			$out['algoResult'] = 'Pass';
			return;
		}
		if ($result1 === 'R' && $result2 === 'NR' && $result3 === 'NR' && $reportedResultCode === 'N') {
			$out['algoResult'] = 'Pass';
			return;
		}
		if ($result1 === 'R' && $result2 === 'NR' && $result3 === 'R' && in_array($reportedResultCode, ['P', 'R'], true)) {
			$out['algoResult'] = 'Pass';
			return;
		}
		if (
			($result1 === 'R' && $result2 === 'R' && $result3 === 'NR' && $reportedResultCode === 'I')
			|| ($result1 === 'R' && $result2 === 'R' && $result3 === 'I' && $reportedResultCode === 'I')
		) {
			$out['algoResult'] = 'Pass';
			return;
		}

		$this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
	}

	/** Cote d'Ivoire */
	private function algoCoteDivoire(
		array $result,
		?string $result1,
		?string $result2,
		?string $result3,
		?string $reportedResultCode,
		string $repeatResult1,
		array $correctiveActions,
		array &$out
	) {

		// for ease of checking, we map all positive reported codes to 'P'
		if (in_array(strtoupper(trim($reportedResultCode)), ['VIH1', 'VH1', 'VIH2', 'VH2', 'VIH1&2', 'VH1&2', 'P'])) {
			$reportedResultCode = 'P';
		}
		if ($result1 === 'NR' && $reportedResultCode === 'N') {
			if ($result2 === '-' && $result3 === '-' && $repeatResult1 === '-') {
				$out['algoResult'] = 'Pass';
				return;
			}
			return $this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
		}
		if ($result1 === 'R') {
			if ($result2 === 'R' && $result3 === 'R' && $reportedResultCode === 'P' && $repeatResult1 === '-') {
				$out['algoResult'] = 'Pass';
				return;
			}
			if ($result2 === 'NR') {
				if ($repeatResult1 === 'NR' && $reportedResultCode === 'N') {
					$out['algoResult'] = 'Pass';
					return;
				}
				if (($repeatResult1 === 'R' || $repeatResult1 === 'I') && in_array($reportedResultCode, ['P', 'I'], true)) {
					$out['algoResult'] = 'Pass';
					return;
				}
				return $this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
			}
			return $this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
		}
	}

	/** Myanmar (sets 50% score if algorithm is right) */
	private function algoMyanmar(
		array $result,
		?string $result1,
		?string $result2,
		?string $result3,
		?string $expectedResultCode,
		?string $reportedResultCode,
		array $correctiveActions,
		array &$out
	) {
		$pass = false;

		if ($result1 === 'NR' && $result2 === '-' && $result3 === '-' && $expectedResultCode === 'N' && $reportedResultCode === 'N')
			$pass = true;
		elseif ($result1 === 'R' && $result2 === 'R' && in_array($result3, ['R', '-'], true) && $expectedResultCode === 'P' && $reportedResultCode === 'P')
			$pass = true;
		elseif ($result1 === 'R' && $result2 === 'R' && $result3 === 'R' && $expectedResultCode === 'R' && $reportedResultCode === 'R')
			$pass = true;
		elseif ($result1 === 'R' && $result2 === 'NR' && $result3 === 'NR' && $expectedResultCode === 'N' && $reportedResultCode === 'N')
			$pass = true;
		elseif ($result1 === 'R' && $result2 === 'NR' && $result3 === 'R' && $expectedResultCode === 'I' && $reportedResultCode === 'I')
			$pass = true;
		elseif ($result1 === 'R' && $result2 === 'R' && $result3 === 'NR' && in_array($expectedResultCode, ['P', 'I'], true) && $reportedResultCode === $expectedResultCode)
			$pass = true;

		if ($pass) {
			$out['algoResult'] = 'Pass';
			$out['scorePct'] = 0.5; // Myanmar rule
			return;
		}
		$this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
	}

	/** Malawi */
	private function algoMalawi(
		array $result,
		?string $result1,
		?string $result2,
		?string $reportedResultCode,
		string $repeatResult1,
		string $repeatResult2,
		array $correctiveActions,
		array &$out
	) {
		if ($result1 === 'NR' && $reportedResultCode === 'N') {
			if ($result2 === '-' && $repeatResult1 === '-' && $repeatResult2 === '-') {
				$out['algoResult'] = 'Pass';
				return;
			}
			return $this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
		}

		if ($result1 === 'R') {
			if ($result2 === 'R' && $reportedResultCode === 'P' && $repeatResult1 === '-' && $repeatResult2 === '-') {
				$out['algoResult'] = 'Pass';
				return;
			}
			if ($result2 === 'NR') {
				if ($repeatResult1 === 'NR' && $repeatResult2 === 'NR' && $reportedResultCode === 'N') {
					$out['algoResult'] = 'Pass';
					return;
				}
				if ($repeatResult1 === 'R' && $repeatResult2 === 'R' && $reportedResultCode === 'P') {
					$out['algoResult'] = 'Pass';
					return;
				}
				if ($repeatResult1 === 'R' && $repeatResult2 === 'NR' && $reportedResultCode === 'I') {
					$out['algoResult'] = 'Pass';
					return;
				}
				if ($repeatResult1 === 'NR' && $repeatResult2 === 'N' && $reportedResultCode === 'I') {
					$out['algoResult'] = 'Pass';
					return;
				}
				return $this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
			}
			return $this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
		}
	}

	/** Ghana (HIV algo) */
	private function algoGhana(
		array $result,
		?string $result1,
		?string $result2,
		?string $result3,
		?string $reportedResultCode,
		string $repeatResult1,
		string $repeatResult2,
		array $correctiveActions,
		array &$out
	) {
		if ($result1 === 'NR' && $reportedResultCode === 'N') {
			if ($result2 === '-' && $result3 === '-') {
				$out['algoResult'] = 'Pass';
				return;
			}
			return $this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
		}

		if ($result1 === 'R') {
			if ($result2 === 'R' && $result3 === 'R' && $reportedResultCode === 'P') {
				$out['algoResult'] = 'Pass';
				return;
			}
			if ($result2 === 'NR') {
				if ($repeatResult1 === 'NR' && $repeatResult2 === 'NR' && $reportedResultCode === 'N') {
					$out['algoResult'] = 'Pass';
					return;
				}
				if ($repeatResult1 === 'R' && $repeatResult2 === 'R' && $reportedResultCode === 'P') {
					$out['algoResult'] = 'Pass';
					return;
				}
				if ($repeatResult1 === 'R' && $repeatResult2 === 'NR' && $reportedResultCode === 'I') {
					$out['algoResult'] = 'Pass';
					return;
				}
				if ($repeatResult1 === 'NR' && $repeatResult2 === 'N' && $reportedResultCode === 'I') {
					$out['algoResult'] = 'Pass';
					return;
				}
				return $this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
			}
			return $this->warningForAlgo($out, $correctiveActions, $result['sample_label'] ?? '');
		}
	}

	/** Ghana syphilis mini-check */
	private function algoGhanaSyphilis(?string $syphilisResult, ?string $reportedSyphilisResultCode): ?string
	{
		if ($syphilisResult === null || $reportedSyphilisResultCode === null) {
			return null;
		}
		if ($syphilisResult === 'R' && $reportedSyphilisResultCode === 'P')
			return 'Pass';
		if ($syphilisResult === 'NR' && $reportedSyphilisResultCode === 'N')
			return 'Pass';
		return 'Fail';
	}

	public function getDtsPanelSettings($config, array $shipmentAttributes = [], array $participantAttributes = [], ?string $dtsSchemeType = null, ?array $allowedAlgorithms = null): array
	{
		$allowRepeatTests = (isset($config['allowRepeatTests']) && $config['allowRepeatTests'] != "") ? $config['allowRepeatTests'] : 'no';
		$allowRepeatTests = ($allowRepeatTests === 'yes');
		$repeatTest1 = false;
		$testThreeHidden = false;
		$testTwoHidden = false;
		$screeningTest = false;
		$syphilisActive = false;
		$displaySampleConditionFields = false;
		$isThisRetestField = false;

		if ($allowedAlgorithms === null) {
			$allowedAlgorithms = isset($config['allowedAlgorithms']) ? explode(",", $config['allowedAlgorithms']) : [];
		}
		if ($dtsSchemeType === null) {
			$dtsSchemeType = (isset($config['dtsSchemeType']) && $config['dtsSchemeType'] != "") ? $config['dtsSchemeType'] : 'standard';
		}

		if (isset($participantAttributes['dts_test_panel_type']) && $participantAttributes['dts_test_panel_type'] == 'confirmatory') {
			$testThreeHidden = false;
		} elseif (isset($config['dtsOptionalTest3']) && $config['dtsOptionalTest3'] == 'yes') {
			$testThreeHidden = true;
		}
		if ($dtsSchemeType == 'updated-3-tests') {
			$allowedAlgorithms = ['dts-3-tests'];
			$allowRepeatTests = $repeatTest1 = true;
			$testThreeHidden = false;
			$testTwoHidden = false;
		} elseif ($dtsSchemeType == 'malawi') {
			$displaySampleConditionFields = true;
			$allowRepeatTests = $repeatTest1 = true;
			$testThreeHidden = false;
			$testTwoHidden = false;
		} elseif ($dtsSchemeType == 'myanmar') {
			$testThreeHidden = false;
			$testTwoHidden = false;
		} elseif ($dtsSchemeType == 'ghana') {
			$testThreeHidden = false;
			$testTwoHidden = false;
			if (isset($shipmentAttributes['enableSyphilis']) && $shipmentAttributes['enableSyphilis'] == "yes") {
				$syphilisActive = true;
				$isThisRetestField = true;
			}
		}

		if (isset($shipmentAttributes['noOfTestsInPanel']) && !empty($shipmentAttributes['noOfTestsInPanel'])) {
			if ($shipmentAttributes['noOfTestsInPanel'] == 2) {
				$testTwoHidden = false;
				$testThreeHidden = true;
			} elseif ($shipmentAttributes['noOfTestsInPanel'] == 3) {
				$testTwoHidden = $testThreeHidden = false;
			}
		}

		if (
			(isset($shipmentAttributes['screeningTest']) && $shipmentAttributes['screeningTest'] == 'yes')
			|| (isset($participantAttributes['dts_test_panel_type']) && $participantAttributes['dts_test_panel_type'] === 'screening')
		) {
			$testTwoHidden = true;
			$testThreeHidden = true;
			$screeningTest = true;
			$allowRepeatTests = $repeatTest1 = false;
			if (isset($participantAttributes["algorithm"]) && $participantAttributes["algorithm"] != 'myanmarNationalDtsAlgo') {
				$allowedAlgorithms = ['screening'];
			}
		}

		$noTest = $testTwoHidden ? 1 : ($testThreeHidden ? 2 : 3);

		return [
			'allowRepeatTests' => $allowRepeatTests,
			'repeatTest1' => $repeatTest1,
			'testThreeHidden' => $testThreeHidden,
			'testTwoHidden' => $testTwoHidden,
			'screeningTest' => $screeningTest,
			'syphilisActive' => $syphilisActive,
			'displaySampleConditionFields' => $displaySampleConditionFields,
			'isThisRetestField' => $isThisRetestField,
			'allowedAlgorithms' => $allowedAlgorithms,
			'dtsSchemeType' => $dtsSchemeType,
			'noTest' => $noTest,
		];
	}
}
