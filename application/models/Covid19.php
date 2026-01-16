<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class Application_Model_Covid19
{

    public function __construct() {}

    public function evaluate($shipmentResult, $shipmentId)
    {

        $counter = 0;
        $maxScore = 0;
        $scoreHolder = [];
        $schemeService = new Application_Service_Schemes();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        //$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        //$config = new Zend_Config_Ini($file, APPLICATION_ENV);
        $config = json_decode(Pt_Commons_SchemeConfig::get('covid19'));
        $correctiveActions = $schemeService->getCovid19CorrectiveActions();
        $recommendedTesttypes = $schemeService->getRecommededCovid19TestTypes();

        foreach ($shipmentResult as $shipment) {
            Pt_Commons_MiscUtility::updateHeartbeat('shipment', 'shipment_id', $shipmentId);

            //$shipment['is_excluded'] = 'no'; // setting it as no by default. It will become 'yes' if some condition matches.

            $createdOnUser = explode(" ", $shipment['shipment_test_report_date'] ?? '');
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
            $failureReason = [];
            $correctiveActionList = [];
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
                // 		'warning' => "Result not evaluated : Test platform 1 expiry date is not reported with PT response.",
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
                // 		'warning' => "Result not evaluated : Test platform 2 expiry date is not reported with PT response.",
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
                // 		'warning' => "Result not evaluated : Test platform 3 expiry date is not reported with PT response.",
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
            } elseif (($testPlatform1 != "") && ($testPlatform2 != "") && ($testPlatform3 != "") && ($testPlatform1 == $testPlatform2) && ($testPlatform2 == $testPlatform3)) {
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
            // 			'warning' => "Result not evaluated : Test Platform lot number 1 is not reported.",
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
            // 			'warning' => "Result not evaluated : Test Platform lot number 2 is not reported.",
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
            // 			'warning' => "Result not evaluated : Test Platform lot number 3 is not reported.",
            // 			'correctiveAction' => $correctiveActions[10]
            // 		);
            // 		$correctiveActionList[] = 10;
            // 		$shipment['is_excluded'] = 'yes';
            // 	}
            // }

            $samplePassOrFail = [];
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

            $configuredDocScore = ((isset($config['documentationScore']) && $config['documentationScore'] != "" && $config['documentationScore'] != null) ? $config['documentationScore'] : 0);
            // Response Score
            if ($maxScore == 0 || $totalScore == 0) {
                $responseScore = 0;
            } else {
                // $responseScore = round(($totalScore / $maxScore) * 100 * (100 - $configuredDocScore) / 100, 2);
                $responseScore = round(($totalScore / $maxScore) * 100, 2);
            }

            //Let us now calculate documentation score
            $documentationScore = 0;
            $documentationScorePerItem = ($config['documentationScore'] / 3);

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

            $sampleRehydrateDays = $config['sampleRehydrateDays'];
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
            if ($grandTotal < $config['passPercentage']) {
                $scoreResult = 'Fail';
                $failureReason[] = array(
                    'warning' => "Participant did not meet the score criteria (Participant Score is <strong>" . $grandTotal . "</strong> and Required Score is <strong>" . $config['passPercentage'] . "</strong>)",
                    'correctiveAction' => $correctiveActions[15]
                );
                $correctiveActionList[] = 15;
            } else {
                $scoreResult = 'Pass';
            }


            // if we are excluding this result, then let us not give pass/fail

            if (isset($shipment['is_excluded']) && $shipment['is_excluded'] == 'yes') {
                $finalResult = '';
                $shipment['is_excluded'] = 'yes';
                $shipmentResult[$counter]['shipment_score'] = $responseScore = 0;
                $shipmentResult[$counter]['documentation_score'] = 0;
                $shipmentResult[$counter]['display_result'] = '';
                $shipmentResult[$counter]['is_followup'] = 'yes';
                $failureReason[] = ['warning' => 'Excluded from Evaluation'];
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
                if (!empty($shipmentOverall)) {
                    $shipmentResult[$counter]['shipment_score'] = $shipmentOverall['shipment_score'];
                    $shipmentResult[$counter]['documentation_score'] = $shipmentOverall['documentation_score'];
                    if (!isset($shipmentOverall['final_result']) || $shipmentOverall['final_result'] == "") {
                        $shipmentOverall['final_result'] = 2;
                    }
                    $fRes = $db->fetchCol($db->select()->from('r_results', array('result_name'))->where('result_id = ' . $shipmentOverall['final_result']));
                    $shipmentResult[$counter]['display_result'] = $fRes[0];
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


        $db->update('shipment', array('max_score' => $maxScore, 'average_score' => $averageScore, 'status' => 'evaluated'), "shipment_id = " . $shipmentId);
        return $shipmentResult;
    }

    public function generateCovid19ExcelReport($shipmentId)
    {
        //$config = new Zend_Config_Ini(APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini", APPLICATION_ENV);
        $config = json_decode(Pt_Commons_SchemeConfig::get('covid19'));
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        //$sheet = $excel->getActiveSheet();
        $common = new Application_Service_Common();
        $feedbackOption = $common->getConfig('participant_feedback');

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

        $query = $db->select()->from('shipment', array('shipment_id', 'shipment_code', 'scheme_type', 'number_of_samples'))
            ->where("shipment_id = ?", $shipmentId);
        $result = $db->fetchRow($query);

        if ($result['scheme_type'] == 'covid19') {

            $refQuery = $db->select()->from(array('refRes' => 'reference_result_covid19'), array('refRes.sample_label', 'sample_id', 'refRes.sample_score'))
                ->joinLeft(array('r' => 'r_possibleresult'), 'r.id=refRes.reference_result', array('referenceResult' => 'r.response'))
                ->where("refRes.shipment_id = ?", $shipmentId);
            $refResult = $db->fetchAll($refQuery);
            if (count($refResult) > 0) {
                foreach ($refResult as $key => $refRes) {
                    $refCovid19Query = $db->select()->from(array('refCovid19' => 'reference_covid19_test_type'), array('refCovid19.lot_no', 'refCovid19.expiry_date', 'refCovid19.result'))
                        ->joinLeft(array('r' => 'r_possibleresult'), 'r.id=refCovid19.result', array('referenceTypeResult' => 'r.response'))
                        ->joinLeft(array('tt' => 'r_test_type_covid19'), 'tt.test_type_id=refCovid19.test_type', array('testPlatformName' => 'tt.test_type_name'))
                        ->where("refCovid19.shipment_id = ?", $shipmentId)
                        ->where("refCovid19.sample_id = ?", $refRes['sample_id']);
                    $refResult[$key]['typeReference'] = $db->fetchAll($refCovid19Query);
                }
            }
        }

        $firstSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Instructions');
        $excel->addSheet($firstSheet, 0);
        $firstSheet->setTitle('Instructions', true);
        //$firstSheet->getDefaultColumnDimension()->setWidth(44);
        //$firstSheet->getDefaultRowDimension()->setRowHeight(45);
        $firstSheetHeading = array('Tab Name', 'Description');
        $firstSheetColNo = 0;
        $firstSheetRow = 1;

        $firstSheetStyle = array(
            'alignment' => array(
                //'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ),
            )
        );

        foreach ($firstSheetHeading as $value) {
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($firstSheetColNo + 1), $firstSheetRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
            $firstSheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstSheetColNo + 1) . $firstSheetRow, null, null)->getFont()->setBold(true);
            $cellName = $firstSheet->getCell(Coordinate::stringFromColumnIndex($firstSheetColNo + 1), $firstSheetRow)->getColumn();
            $firstSheet->getStyle($cellName . $firstSheetRow)->applyFromArray($firstSheetStyle, true);
            $firstSheetColNo++;
        }

        $firstSheet->getCell(Coordinate::stringFromColumnIndex(1), 2)->setValueExplicit(html_entity_decode("Participant List", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getCell(Coordinate::stringFromColumnIndex(2), 2)->setValueExplicit(html_entity_decode("Includes dropdown lists for the following: region, department, position, RT, ELISA, received logbook", ENT_QUOTES, 'UTF-8'));

        $firstSheet->getDefaultRowDimension()->setRowHeight(10);
        $firstSheet->getColumnDimensionByColumn(0)->setWidth(20);
        $firstSheet->getDefaultRowDimension()->setRowHeight(70);
        $firstSheet->getColumnDimensionByColumn(1)->setWidth(100);

        $firstSheet->getCell(Coordinate::stringFromColumnIndex(1), 3)->setValueExplicit(html_entity_decode("Results Reported", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getCell(Coordinate::stringFromColumnIndex(2), 3)->setValueExplicit(html_entity_decode("This tab should include no commentary from PT Admin staff.  All fields should only reflect results or comments reported on the results form.  If no report was submitted, highlight site data cells in red.  Explanation of missing results should only be comments that the site made, not PT staff.  All dates should be formatted as DD/MM/YY.  Dropdown menu legend is as followed: negative (NEG), positive (POS), invalid (INV), indeterminate (IND), not entered or reported (NE), not tested (NT) and should be used according to the way the site reported it.", ENT_QUOTES, 'UTF-8'));

        $firstSheet->getCell(Coordinate::stringFromColumnIndex(1), 4)->setValueExplicit(html_entity_decode("Panel Score", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getCell(Coordinate::stringFromColumnIndex(2), 4)->setValueExplicit(html_entity_decode("This tab is automatically populated.  Panel score calculated 6/6.  If a panel member must be omitted from the calculation (ie, loss of sample, etc) you must revise the equation manually by changing the number 6 to 5,4,etc. accordingly. Example seen for Akai House Clinic.", ENT_QUOTES, 'UTF-8'));

        $firstSheet->getCell(Coordinate::stringFromColumnIndex(1), 5)->setValueExplicit(html_entity_decode("Documentation Score", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getCell(Coordinate::stringFromColumnIndex(2), 5)->setValueExplicit(html_entity_decode("The points breakdown for this tab are listed in the row above the sites for each column.  Data should be entered in manually by PT staff.  A site scores 1.5/3 if they used the wrong test kits got a 100% panel score.", ENT_QUOTES, 'UTF-8'));

        $firstSheet->getCell(Coordinate::stringFromColumnIndex(1), 6)->setValueExplicit(html_entity_decode("Total Score", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getCell(Coordinate::stringFromColumnIndex(2), 6)->setValueExplicit(html_entity_decode("Columns C-F are populated automatically.  Columns G, H and I must be selected from the dropdown menu for each site based on the criteria listed in the 'Decision Tree' tab.", ENT_QUOTES, 'UTF-8'));

        $firstSheet->getCell(Coordinate::stringFromColumnIndex(1), 7)->setValueExplicit(html_entity_decode("Follow-up Calls", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getCell(Coordinate::stringFromColumnIndex(2), 7)->setValueExplicit(html_entity_decode("Final comments or outcomes should be updated continuously with receipt dates included.", ENT_QUOTES, 'UTF-8'));

        $firstSheet->getCell(Coordinate::stringFromColumnIndex(1), 8)->setValueExplicit(html_entity_decode("Dropdown Lists", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getCell(Coordinate::stringFromColumnIndex(2), 8)->setValueExplicit(html_entity_decode("This tab contains all of the dropdown lists included in the rest of the database, any modifications should be performed with caution.", ENT_QUOTES, 'UTF-8'));

        $firstSheet->getCell(Coordinate::stringFromColumnIndex(1), 9)->setValueExplicit(html_entity_decode("Decision Tree", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getCell(Coordinate::stringFromColumnIndex(2), 9)->setValueExplicit(html_entity_decode("Lists all of the appropriate corrective actions and scoring critieria.", ENT_QUOTES, 'UTF-8'));
        if (isset($feedbackOption) && !empty($feedbackOption) && $feedbackOption == 'yes') {
            $firstSheet->getCell(Coordinate::stringFromColumnIndex(1), 10)->setValueExplicit(html_entity_decode("Feedback Report", ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex(2), 10)->setValueExplicit(html_entity_decode("This tab is populated automatically and used to export data into the Feedback Reports generated in MS Word.", ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex(1), 11)->setValueExplicit(html_entity_decode("Comments", ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex(2), 11)->setValueExplicit(html_entity_decode("This tab lists all of the more detailed comments that will be given to the sites during site visits and phone calls.", ENT_QUOTES, 'UTF-8'));
        } else {
            $firstSheet->getCell(Coordinate::stringFromColumnIndex(1), 10)->setValueExplicit(html_entity_decode("Comments", ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex(2), 10)->setValueExplicit(html_entity_decode("This tab lists all of the more detailed comments that will be given to the sites during site visits and phone calls.", ENT_QUOTES, 'UTF-8'));
        }



        for ($counter = 1; $counter <= 11; $counter++) {
            $firstSheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2) . $counter, null, null)->getAlignment()->setWrapText(true);
            $firstSheet->getStyle("A$counter")->applyFromArray($firstSheetStyle, true);
            $firstSheet->getStyle("B$counter")->applyFromArray($firstSheetStyle, true);
        }
        //<------------ Participant List Details Start -----

        $headings = array('Participant Code', 'Participant Name',  'Institute Name', 'Department', 'Country', 'Address', 'Province', 'District', 'City', 'Facility Telephone', 'Email');

        $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Participant List');
        $excel->addSheet($sheet, 1);
        $sheet->setTitle('Participant List', true);

        $sql = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.number_of_samples'))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('sp.map_id', 'sp.participant_id', 'sp.attributes', 'sp.shipment_test_date', 'sp.shipment_receipt_date', 'sp.shipment_test_report_date', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_score', 'sp.documentation_score', 'sp.user_comment'))
            ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.lab_name', 'p.region', 'p.first_name', 'p.last_name', 'p.address', 'p.city', 'p.mobile', 'p.email', 'p.status', 'province' => 'p.state', 'p.district'))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country', array('iso_name'))
            ->joinLeft(array('pmp' => 'participant_manager_map'), 'pmp.participant_id=p.participant_id', array('pmp.dm_id'))
            ->joinLeft(array('dm' => 'data_manager'), 'dm.dm_id=pmp.dm_id', array('dm.institute', 'dataManagerFirstName' => 'dm.first_name', 'dataManagerLastName' => 'dm.last_name'))
            ->joinLeft(array('st' => 'r_site_type'), 'st.r_stid=p.site_type', array('st.site_type'))
            ->joinLeft(array('en' => 'enrollments'), 'en.participant_id=p.participant_id', array('en.enrolled_on'))
            ->where("s.shipment_id = ?", $shipmentId)
            ->group(array('sp.map_id'));
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sql = $sql
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array('pmm.dm_id'))
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }

        $shipmentResult = $db->fetchAll($sql);
        $colNo = 0;
        $currentRow = 1;
        //$sheet->getCell(Coordinate::stringFromColumnIndex(0), 1)->setValueExplicit(html_entity_decode("Participant List", ENT_QUOTES, 'UTF-8'), $type);
        //$sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(0) . 1)->getFont()->setBold(true);
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getDefaultRowDimension()->setRowHeight(18);

        foreach ($headings as $field => $value) {
            $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1), $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
            $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow, null, null)->getFont()->setBold(true);
            $cellName = $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1), $currentRow)->getColumn();
            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle, true);
            $colNo++;
        }

        if (isset($shipmentResult) && !empty($shipmentResult)) {
            $currentRow += 1;
            foreach ($shipmentResult as $key => $aRow) {
                if ($result['scheme_type'] == 'covid19') {
                    $resQuery = $db->select()->from(array('rrcovid19' => 'response_result_covid19'))
                        ->joinLeft(array('tt1' => 'r_test_type_covid19'), 'tt1.test_type_id=rrcovid19.test_type_1', array('testPlatformName1' => 'tt1.test_type_name'))
                        ->joinLeft(array('tt2' => 'r_test_type_covid19'), 'tt2.test_type_id=rrcovid19.test_type_2', array('testPlatformName2' => 'tt2.test_type_name'))
                        ->joinLeft(array('tt3' => 'r_test_type_covid19'), 'tt3.test_type_id=rrcovid19.test_type_3', array('test~PlatformName3' => 'tt3.test_type_name'))
                        ->joinLeft(array('r' => 'r_possibleresult'), 'r.id=rrcovid19.test_result_1', array('testResult1' => 'r.response'))
                        ->joinLeft(array('rp' => 'r_possibleresult'), 'rp.id=rrcovid19.test_result_2', array('testResult2' => 'rp.response'))
                        ->joinLeft(array('rpr' => 'r_possibleresult'), 'rpr.id=rrcovid19.test_result_3', array('testResult3' => 'rpr.response'))
                        ->joinLeft(array('fr' => 'r_possibleresult'), 'fr.id=rrcovid19.reported_result', array('finalResult' => 'fr.response'))
                        ->where("rrcovid19.shipment_map_id = ?", $aRow['map_id']);
                    $shipmentResult[$key]['response'] = $db->fetchAll($resQuery);
                }


                $sheet->getCell(Coordinate::stringFromColumnIndex(1), $currentRow)->setValueExplicit(ucwords($aRow['unique_identifier']));
                $sheet->getCell(Coordinate::stringFromColumnIndex(2), $currentRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(3), $currentRow)->setValueExplicit($aRow['institute_name']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(4), $currentRow)->setValueExplicit($aRow['department_name']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(5), $currentRow)->setValueExplicit($aRow['iso_name']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(6), $currentRow)->setValueExplicit($aRow['address']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(7), $currentRow)->setValueExplicit($aRow['province']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(8), $currentRow)->setValueExplicit($aRow['district']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(9), $currentRow)->setValueExplicit($aRow['city']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(10), $currentRow)->setValueExplicit($aRow['mobile']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(11), $currentRow)->setValueExplicit(strtolower($aRow['email']));

                for ($i = 0; $i <= 11; $i++) {
                    $cellName = $sheet->getCell(Coordinate::stringFromColumnIndex($i + 1), $currentRow)->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle, true);
                }

                $currentRow++;
                $shipmentCode = $aRow['shipment_code'];
            }
        }

        //------------- Participant List Details End ------>

        //<-------- Second sheet start
        $reportHeadings = array('Participant Code', 'Participant Name', 'Point of Contact', 'Region', 'Shipment Receipt Date', 'Sample Rehydration Date', 'Testing Date', 'Test#1 Name', 'Name of PCR reagent #1', 'PCR reagent Lot #1', 'PCR reagent expiry date #1', 'Type Lot #1', 'Expiry Date');
        $maximumAllowed = $config['covid19MaximumTestAllowed'];
        if ($result['scheme_type'] == 'covid19') {
            $reportHeadings = $this->addCovid19SampleNameInArray($shipmentId, $reportHeadings);
            if ($maximumAllowed >= 2) {
                array_push($reportHeadings, 'Test#2 Name', 'Name of PCR reagent #2', 'PCR reagent Lot #2', 'PCR reagent expiry date #2', 'Type Lot #2', 'Expiry Date');
                $reportHeadings = $this->addCovid19SampleNameInArray($shipmentId, $reportHeadings);
            }
            if ($maximumAllowed == 3) {
                array_push($reportHeadings, 'Test#3 Name', 'Name of PCR reagent #3', 'PCR reagent Lot #3', 'PCR reagent expiry date #3', 'Type Lot #3', 'Expiry Date');
                $reportHeadings = $this->addCovid19SampleNameInArray($shipmentId, $reportHeadings);
            }
            $reportHeadings = $this->addCovid19SampleNameInArray($shipmentId, $reportHeadings);
            array_push($reportHeadings, 'Comments');
        }

        $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Results Reported');
        $excel->addSheet($sheet, 2);
        $sheet->setTitle('Results Reported', true);
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getDefaultRowDimension()->setRowHeight(18);


        $colNo = 0;
        $currentRow = 2;
        $n = count($reportHeadings);
        $finalResColoumn = $n - ($result['number_of_samples'] + 1);
        $c = 1;
        $endMergeCell = ($finalResColoumn + $result['number_of_samples']) - 1;

        $firstCellName = $sheet->getCell(Coordinate::stringFromColumnIndex($finalResColoumn + 1), 1)->getColumn();
        $secondCellName = $sheet->getCell(Coordinate::stringFromColumnIndex($endMergeCell + 1), 1)->getColumn();
        $sheet->mergeCells($firstCellName . "1:" . $secondCellName . "1");
        $sheet->getStyle($firstCellName . "1")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle($firstCellName . "1")->applyFromArray($borderStyle, true);
        $sheet->getStyle($secondCellName . "1")->applyFromArray($borderStyle, true);

        foreach ($reportHeadings as $field => $value) {

            $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1), $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
            $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow, null, null)->getFont()->setBold(true);
            $cellName = $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1), $currentRow)->getColumn();
            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle, true);

            $cellName = $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1), 3)->getColumn();
            $sheet->getStyle($cellName . "3")->applyFromArray($borderStyle, true);

            if ($colNo >= $finalResColoumn) {
                if ($c <= $result['number_of_samples']) {

                    $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1), 1)->setValueExplicit(html_entity_decode("Final Results", ENT_QUOTES, 'UTF-8'));
                    $cellName = $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1), $currentRow)->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                    $l = $c - 1;
                    $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1), 3)->setValueExplicit(html_entity_decode($refResult[$l]['referenceResult'], ENT_QUOTES, 'UTF-8'));
                }
                $c++;
            }
            $sheet->getStyle($cellName . '3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFA0A0A0');
            $sheet->getStyle($cellName . '3')->getFont()->getColor()->setARGB('FFFFFF00');

            $colNo++;
        }

        $sheet->getStyle("A2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("B2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("C2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("D2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');

        //$sheet->getStyle("D2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');
        //$sheet->getStyle("E2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');
        //$sheet->getStyle("F2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');

        $cellName = $sheet->getCell(Coordinate::stringFromColumnIndex($n + 1), 3)->getColumn();
        //$sheet->getStyle('A3:'.$cellName.'3')->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#969696');
        //$sheet->getStyle('A3:'.$cellName.'3')->applyFromArray($borderStyle);
        //<-------- Sheet three heading -------
        $sheetThree = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Panel Score');
        $excel->addSheet($sheetThree, 3);
        $sheetThree->setTitle('Panel Score', true);
        $sheetThree->getDefaultColumnDimension()->setWidth(20);
        $sheetThree->getDefaultRowDimension()->setRowHeight(18);
        $panelScoreHeadings = array('Participant Code', 'Participant Name');
        $panelScoreHeadings = $this->addCovid19SampleNameInArray($shipmentId, $panelScoreHeadings);
        array_push($panelScoreHeadings, 'Test# Correct', '% Correct');
        $sheetThreeColNo = 0;
        $sheetThreeRow = 1;
        $panelScoreHeadingCount = count($panelScoreHeadings);
        $sheetThreeColor = 1 + $result['number_of_samples'];
        foreach ($panelScoreHeadings as $sheetThreeHK => $value) {
            $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeColNo + 1), $sheetThreeRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
            $sheetThree->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($sheetThreeColNo + 1) . $sheetThreeRow, null, null)->getFont()->setBold(true);
            $cellName = $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeColNo + 1), $sheetThreeRow)->getColumn();
            $sheetThree->getStyle($cellName . $sheetThreeRow)->applyFromArray($borderStyle, true);

            if ($sheetThreeHK > 1 && $sheetThreeHK <= $sheetThreeColor) {
                $cellName = $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeColNo + 1), $sheetThreeRow)->getColumn();
                $sheetThree->getStyle($cellName . $sheetThreeRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            }

            $sheetThreeColNo++;
        }
        //---------- Sheet Three heading ------->
        //<-------- Document Score Sheet Heading (Sheet Four)-------

        /* if ($result['scheme_type'] == 'covid19') {
            $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
            $config = new Zend_Config_Ini($file, APPLICATION_ENV);
            $shipmentAttributes = json_decode($aRow['shipment_attributes'], true);
            if (isset($shipmentAttributes['sampleType']) && $shipmentAttributes['sampleType'] == 'dried') {
                // for Dried Samples, we will have rehydration as one of the documentation scores
                $documentationScorePerItem = round(($config->evaluation->covid19->documentationScore / 5), 2);
            } else {
                // for Non Dried Samples, we will NOT have rehydration documentation scores
                // there are 2 conditions for rehydration so 5 - 2 = 3
                $documentationScorePerItem = round(($config->evaluation->covid19->documentationScore / 3), 2);
            }
        } */

        /* $docScoreSheet = new PHPExcel_Worksheet($excel, 'Documentation Score');
        $excel->addSheet($docScoreSheet, 4);
        $docScoreSheet->setTitle('Documentation Score');
        $docScoreSheet->getDefaultColumnDimension()->setWidth(20);
        //$docScoreSheet->getDefaultRowDimension()->setRowHeight(20);
        $docScoreSheet->getDefaultRowDimension('G')->setRowHeight(25);

        $docScoreHeadings = array('Participant Code', 'Participant Name', 'Supervisor signature', 'Panel Receipt Date', 'Rehydration Date', 'Tested Date', 'Rehydration Test In Specified Time', 'Documentation Score %');

        $docScoreSheetCol = 0;
        $docScoreRow = 1;
        $docScoreHeadingsCount = count($docScoreHeadings);
        foreach ($docScoreHeadings as $sheetThreeHK => $value) {
            $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreSheetCol), $docScoreRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            $docScoreSheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($docScoreSheetCol) . $docScoreRow)->getFont()->setBold(true);
            $cellName = $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreSheetCol), $docScoreRow)->getColumn();
            $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);
            $docScoreSheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($docScoreSheetCol) . $docScoreRow)->getAlignment()->setWrapText(true);
            $docScoreSheetCol++;
        }
        $docScoreRow = 2;
        $secondRowcellName = $docScoreSheet->getCell(Coordinate::stringFromColumnIndex(1), $docScoreRow);
        $secondRowcellName->setValueExplicit(html_entity_decode("Points Breakdown", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        $docScoreSheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(1) . $docScoreRow)->getFont()->setBold(true);
        $cellName = $secondRowcellName->getColumn();
        $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);

        for ($r = 2; $r <= 7; $r++) {

            $secondRowcellName = $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($r), $docScoreRow);
            if ($r != 7) {
                $secondRowcellName->setValueExplicit(html_entity_decode($documentationScorePerItem, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            }
            $docScoreSheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r) . $docScoreRow)->getFont()->setBold(true);
            $cellName = $secondRowcellName->getColumn();
            $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);
        } */

        //---------- Document Score Sheet Heading (Sheet Four)------->
        //<-------- Total Score Sheet Heading (Sheet Four)-------


        $totalScoreSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Total Score');
        $excel->addSheet($totalScoreSheet, 4);
        $totalScoreSheet->setTitle('Total Score', true);
        $totalScoreSheet->getDefaultColumnDimension()->setWidth(20);
        $totalScoreSheet->getDefaultRowDimension()->setRowHeight(30);
        $totalScoreHeadings = array('Participant Code', 'Participant Name', 'No. of Panels Correct (N=' . $result['number_of_samples'] . ')', 'Panel Score(100% Conv.)', 'Panel Score(90% Conv.)', 'Documentation Score(100% Conv.)', 'Documentation Score(10% Conv.)', 'Total Score', 'Overall Performance');

        $totScoreSheetCol = 0;
        $totScoreRow = 1;
        $totScoreHeadingsCount = count($totalScoreHeadings);
        foreach ($totalScoreHeadings as $sheetThreeHK => $value) {
            $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreSheetCol + 1), $totScoreRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
            $totalScoreSheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totScoreSheetCol + 1) . $totScoreRow, null, null)->getFont()->setBold(true);
            $cellName = $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreSheetCol + 1), $totScoreRow)->getColumn();
            $totalScoreSheet->getStyle($cellName . $totScoreRow)->applyFromArray($borderStyle, true);
            $totalScoreSheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totScoreSheetCol + 1) . $totScoreRow, null, null)->getAlignment()->setWrapText(true);
            $totScoreSheetCol++;
        }

        //---------- Document Score Sheet Heading (Sheet Four)------->

        $ktr = 9;
        $kitId = 7; //Test Kit coloumn count
        if (isset($refResult) && !empty($refResult)) {
            foreach ($refResult as $keyv => $row) {
                $keyv = $keyv + 1;
                $ktr = $ktr + $keyv;
                if (!empty($row['typeReference'])) {

                    if ($keyv == 1) {
                        //In Excel Third row added the Test kit name1,kit lot,exp date
                        if (trim($row['typeReference'][0]['expiry_date']) != "") {
                            $row['typeReference'][0]['expiry_date'] = Pt_Commons_General::excelDateFormat($row['typeReference'][0]['expiry_date']);
                        }
                        $sheet->getCell(Coordinate::stringFromColumnIndex($kitId++), 3)->setValueExplicit($row['typeReference'][0]['testPlatformName']);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($kitId++), 3)->setValueExplicit($row['typeReference'][0]['lot_no']);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($kitId++), 3)->setValueExplicit($row['typeReference'][0]['expiry_date']);

                        $kitId = $kitId + $aRow['number_of_samples'];
                        if (isset($row['typeReference'][1]['referenceTypeResult'])) {
                            //In Excel Third row added the Test kit name2,kit lot,exp date
                            if (trim($row['typeReference'][1]['expiry_date']) != "") {
                                $row['typeReference'][1]['expiry_date'] = Pt_Commons_General::excelDateFormat($row['typeReference'][1]['expiry_date']);
                            }
                            $sheet->getCell(Coordinate::stringFromColumnIndex($kitId++), 3)->setValueExplicit($row['typeReference'][1]['testPlatformName']);
                            $sheet->getCell(Coordinate::stringFromColumnIndex($kitId++), 3)->setValueExplicit($row['typeReference'][1]['lot_no']);
                            $sheet->getCell(Coordinate::stringFromColumnIndex($kitId++), 3)->setValueExplicit($row['typeReference'][1]['expiry_date']);
                        }
                        $kitId = $kitId + $aRow['number_of_samples'];
                        if (isset($row['typeReference'][2]['referenceTypeResult'])) {
                            //In Excel Third row added the Test kit name3,kit lot,exp date
                            if (trim($row['typeReference'][2]['expiry_date']) != "") {
                                $row['typeReference'][2]['expiry_date'] = Pt_Commons_General::excelDateFormat($row['typeReference'][2]['expiry_date']);
                            }
                            $sheet->getCell(Coordinate::stringFromColumnIndex($kitId++), 3)->setValueExplicit($row['typeReference'][2]['testPlatformName']);
                            $sheet->getCell(Coordinate::stringFromColumnIndex($kitId++), 3)->setValueExplicit($row['typeReference'][2]['lot_no']);
                            $sheet->getCell(Coordinate::stringFromColumnIndex($kitId++), 3)->setValueExplicit($row['typeReference'][2]['expiry_date']);
                        }
                    }

                    $sheet->getCell(Coordinate::stringFromColumnIndex($ktr + 1), 3)->setValueExplicit($row['typeReference'][0]['referenceTypeResult']);
                    $ktr = ($aRow['number_of_samples'] - $keyv) + $ktr + 3;

                    if (isset($row['typeReference'][1]['referenceTypeResult'])) {
                        $ktr = $ktr + $keyv;
                        $sheet->getCell(Coordinate::stringFromColumnIndex($ktr + 1), 3)->setValueExplicit($row['typeReference'][1]['referenceTypeResult']);
                        $ktr = ($aRow['number_of_samples'] - $keyv) + $ktr + 3;
                    }
                    if (isset($row['typeReference'][2]['referenceTypeResult'])) {
                        $ktr = $ktr + $keyv;
                        $sheet->getCell(Coordinate::stringFromColumnIndex($ktr + 1), 3)->setValueExplicit($row['typeReference'][2]['referenceTypeResult']);
                    }
                }
                $ktr = 9;
            }
        }

        $currentRow = 4;
        $sheetThreeRow = 2;
        $docScoreRow = 3;
        $totScoreRow = 2;
        if (isset($shipmentResult) && count($shipmentResult) > 0) {

            foreach ($shipmentResult as $aRow) {
                $r = 0;
                $k = 0;
                $rehydrationDate = "";
                $shipmentTestDate = "";
                $sheetThreeCol = 0;
                $docScoreCol = 0;
                $totScoreCol = 0;
                $countCorrectResult = 0;

                $colCellObj = $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow);
                $colCellObj->setValueExplicit(ucwords($aRow['unique_identifier']));
                $cellName = $colCellObj->getColumn();
                $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);
                $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['dataManagerFirstName'] . ' ' . $aRow['dataManagerLastName']);
                $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['region']);
                $shipmentReceiptDate = "";
                if (isset($aRow['shipment_receipt_date']) && trim($aRow['shipment_receipt_date']) != "") {
                    $shipmentReceiptDate = $aRow['shipment_receipt_date'] = Pt_Commons_General::excelDateFormat($aRow['shipment_receipt_date']);
                }

                if (isset($aRow['shipment_test_date']) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {
                    $shipmentTestDate = Pt_Commons_General::excelDateFormat($aRow['shipment_test_date']);
                }

                if (trim($aRow['attributes']) != "") {
                    $attributes = json_decode($aRow['attributes'], true);
                    $sampleRehydrationDate = new Zend_Date($attributes['sample_rehydration_date']);
                    $rehydrationDate = Pt_Commons_General::excelDateFormat($attributes["sample_rehydration_date"]);
                }

                $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['shipment_receipt_date']);
                $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($rehydrationDate);
                $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($shipmentTestDate);



                $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeCol++), $sheetThreeRow)->setValueExplicit(ucwords($aRow['unique_identifier']));
                $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeCol++), $sheetThreeRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);

                //<-------------Document score sheet------------

                /* $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++), $docScoreRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++), $docScoreRow)->setValueExplicit($aRow['first_name'] . ' ' .$aRow['last_name'], PHPExcel_Cell_DataType::TYPE_STRING);

                if (isset($shipmentReceiptDate) && trim($shipmentReceiptDate) != "") {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++), $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                } else {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++), $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                }

                // For Myanmar National Algorithm, they do not want to check for Supervisor Approval
                if ($attributes['algorithm'] == 'myanmarNationalDtsAlgo') {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++), $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                } else {
                    if (isset($aRow['supervisor_approval']) && strtolower($aRow['supervisor_approval']) == 'yes' && isset($aRow['participant_supervisor']) && trim($aRow['participant_supervisor']) != "") {
                        $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++), $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                    } else {
                        $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++), $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                    }
                }


                if (isset($rehydrationDate) && trim($rehydrationDate) != "") {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++), $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                } else {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++), $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                }

                if (isset($aRow['shipment_test_date']) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++), $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                } else {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++), $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                }

                if (isset($sampleRehydrationDate) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {


                    $config = new Zend_Config_Ini(APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini", APPLICATION_ENV);
                    $sampleRehydrationDate = new DateTime($attributes['sample_rehydration_date']);
                    $testedOnDate = new DateTime($aRow['shipment_test_date']);
                    $interval = $sampleRehydrationDate->diff($testedOnDate);

                    // Testing should be done within 24*($config->evaluation->covid19->sampleRehydrateDays) hours of rehydration.
                    $sampleRehydrateDays = $config->evaluation->covid19->sampleRehydrateDays;
                    $rehydrateHours = $sampleRehydrateDays * 24;

                    if ($interval->days < $sampleRehydrateDays || $interval->days > ($sampleRehydrateDays + 1)) {

                        $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++), $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                    } else {
                        $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++), $docScoreRow)->setValueExplicit($documentationScorePerItem, PHPExcel_Cell_DataType::TYPE_STRING);
                    }
                } else {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++), $docScoreRow)->setValueExplicit(0, PHPExcel_Cell_DataType::TYPE_STRING);
                }
                */
                $documentScore = (($aRow['documentation_score'] / $config['documentationScore']) * 100);
                /*
                $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++), $docScoreRow)->setValueExplicit($documentScore, PHPExcel_Cell_DataType::TYPE_STRING);
                */
                //-------------Document score sheet------------>
                //<------------ Total score sheet ------------

                $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++), $totScoreRow)->setValueExplicit(ucwords($aRow['unique_identifier']));
                $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++), $totScoreRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);

                //------------ Total score sheet ------------>
                //Zend_Debug::dump($aRow['response']);
                if (!empty($aRow['response'])) {

                    if (isset($aRow['response'][0]['exp_date_1']) && trim($aRow['response'][0]['exp_date_1']) != "") {
                        $aRow['response'][0]['exp_date_1'] = Pt_Commons_General::excelDateFormat($aRow['response'][0]['exp_date_1']);
                    }
                    if (isset($aRow['response'][0]['exp_date_2']) && trim($aRow['response'][0]['exp_date_2']) != "") {
                        $aRow['response'][0]['exp_date_2'] = Pt_Commons_General::excelDateFormat($aRow['response'][0]['exp_date_2']);
                    }
                    if (isset($aRow['response'][0]['exp_date_3']) && trim($aRow['response'][0]['exp_date_3']) != "") {
                        $aRow['response'][0]['exp_date_3'] = Pt_Commons_General::excelDateFormat($aRow['response'][0]['exp_date_3']);
                    }

                    if (isset($aRow['response'][0]['pcr_reagent_exp_date_1']) && trim($aRow['response'][0]['pcr_reagent_exp_date_1']) != "") {
                        $aRow['response'][0]['pcr_reagent_exp_date_1'] = Pt_Commons_General::excelDateFormat($aRow['response'][0]['pcr_reagent_exp_date_1']);
                    }
                    if (isset($aRow['response'][0]['pcr_reagent_exp_date_2']) && trim($aRow['response'][0]['pcr_reagent_exp_date_2']) != "") {
                        $aRow['response'][0]['pcr_reagent_exp_date_2'] = Pt_Commons_General::excelDateFormat($aRow['response'][0]['pcr_reagent_exp_date_2']);
                    }
                    if (isset($aRow['response'][0]['pcr_reagent_exp_date_3']) && trim($aRow['response'][0]['pcr_reagent_exp_date_3']) != "") {
                        $aRow['response'][0]['pcr_reagent_exp_date_3'] = Pt_Commons_General::excelDateFormat($aRow['response'][0]['pcr_reagent_exp_date_3']);
                    }

                    $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['testPlatformName1']);

                    $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['name_of_pcr_reagent_1']);
                    $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['pcr_reagent_lot_no_1']);
                    $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['pcr_reagent_exp_date_1']);

                    $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['lot_no_1']);
                    $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['exp_date_1']);

                    for ($k = 0; $k < $aRow['number_of_samples']; $k++) {
                        //$row[] = $aRow[$k]['testResult1'];
                        $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][$k]['testResult1']);
                    }
                    if ($maximumAllowed >= 2) {
                        $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['testPlatformName2']);

                        $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['name_of_pcr_reagent_2']);
                        $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['pcr_reagent_lot_no_2']);
                        $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['pcr_reagent_exp_date_2']);

                        $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['lot_no_2']);
                        $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['exp_date_2']);
                        for ($k = 0; $k < $aRow['number_of_samples']; $k++) {
                            //$row[] = $aRow[$k]['testResult2'];
                            $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][$k]['testResult2']);
                        }
                    }

                    if ($maximumAllowed == 3) {
                        $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['testPlatformName3']);

                        $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['name_of_pcr_reagent_3']);
                        $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['pcr_reagent_lot_no_3']);
                        $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['pcr_reagent_exp_date_3']);

                        $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['lot_no_3']);
                        $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][0]['exp_date_3']);

                        for ($k = 0; $k < $aRow['number_of_samples']; $k++) {
                            //$row[] = $aRow[$k]['testResult3'];
                            $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][$k]['testResult3']);
                        }
                    }

                    for ($f = 0; $f < $aRow['number_of_samples']; $f++) {
                        //$row[] = $aRow[$f]['finalResult'];
                        $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][$f]['finalResult']);

                        $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeCol++), $sheetThreeRow)->setValueExplicit($aRow['response'][$f]['finalResult']);
                        if (isset($aRow['response'][$f]['calculated_score']) && $aRow['response'][$f]['calculated_score'] == 'Pass' && $aRow['response'][$f]['sample_id'] == $refResult[$f]['sample_id']) {
                            $countCorrectResult++;
                        }
                    }
                    $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['user_comment']);

                    $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeCol++), $sheetThreeRow)->setValueExplicit($countCorrectResult);

                    $totPer = round((($countCorrectResult / $aRow['number_of_samples']) * 100), 2);
                    $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeCol++), $sheetThreeRow)->setValueExplicit($totPer);

                    $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++), $totScoreRow)->setValueExplicit($countCorrectResult);
                    $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++), $totScoreRow)->setValueExplicit($totPer);

                    $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++), $totScoreRow)->setValueExplicit(($totPer * 0.9));
                }
                $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++), $totScoreRow)->setValueExplicit($documentScore);
                $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++), $totScoreRow)->setValueExplicit($aRow['documentation_score']);
                $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++), $totScoreRow)->setValueExplicit(($aRow['shipment_score'] + $aRow['documentation_score']));

                for ($i = 0; $i < $panelScoreHeadingCount; $i++) {
                    $cellName = $sheetThree->getCell(Coordinate::stringFromColumnIndex($i + 1), $sheetThreeRow)->getColumn();
                    $sheetThree->getStyle($cellName . $sheetThreeRow)->applyFromArray($borderStyle, true);
                }

                for ($i = 0; $i < $n; $i++) {
                    $cellName = $sheet->getCell(Coordinate::stringFromColumnIndex($i + 1), $currentRow)->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle, true);
                }

                /* for ($i = 0; $i < $docScoreHeadingsCount; $i++) {
                    $cellName = $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($i), $docScoreRow)->getColumn();
                    $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);
                } */

                for ($i = 0; $i < $totScoreHeadingsCount; $i++) {
                    $cellName = $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($i + 1), $totScoreRow)->getColumn();
                    $totalScoreSheet->getStyle($cellName . $totScoreRow)->applyFromArray($borderStyle, true);
                }

                $currentRow++;

                $sheetThreeRow++;
                $docScoreRow++;
                $totScoreRow++;
            }
        }

        //----------- Second Sheet End----->

        $firstName = $authNameSpace->first_name;
        $lastName = $authNameSpace->last_name;
        $name = $firstName . " " . $lastName;
        $userName = isset($name) != '' ? $name : $authNameSpace->primary_email;
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog("Covid 19 excel report downloaded by $userName", "shipment");

        $excel->setActiveSheetIndex(0);

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
        $filename = $shipmentCode . '-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }

    public function addCovid19SampleNameInArray($shipmentId, $headings)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()->from('reference_result_covid19', array('sample_label'))
            ->where("shipment_id = ?", $shipmentId)->order("sample_id");
        $result = $db->fetchAll($query);
        foreach ($result as $res) {
            array_push($headings, $res['sample_label']);
        }
        return $headings;
    }
}
