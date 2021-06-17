<?php

class Application_Model_Recency
{

    private $db = null;
    private $failureReason = array();
    public function __construct($db = null)
    {
        $this->db = $db;
    }

    public function evaluate($shipmentResult, $shipmentId)
    {

        $counter = 0;
        $maxScore = 0;
        $scoreHolder = array();
        $finalResult = null;
        $schemeService = new Application_Service_Schemes();

        $possibleResultsArray = $schemeService->getPossibleResults('recency');
        $possibleResults = array();
        foreach($possibleResultsArray as $pr){
            $possibleResults['result_code'] =  $possibleResults['id'];
        }


        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, APPLICATION_ENV);

        foreach ($shipmentResult as $shipment) {

            $createdOnUser = explode(" ", $shipment['shipment_test_report_date']);
            if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {
                $createdOn = new DateTime($createdOnUser[0]);
            } else {
                $createdOn = new DateTime('1970-01-01');
            }

            $lastDate = new DateTime($shipment['lastdate_response']);

            if ($createdOn <= $lastDate) {

                $attributes = json_decode($shipment['attributes'], true);
                $shipmentAttributes = json_decode($shipment['shipment_attributes'], true);
                $results = $schemeService->getRecencySamples($shipmentId, $shipment['participant_id']);
                

                $documentationScore = $this->getDocumentationScore($results, $attributes, $shipmentAttributes);

                $totalScore = 0;
                $maxScore = 0;
                $mandatoryResult = "";
                $scoreResult = "";
                $this->failureReason = array();

                foreach ($results as $result) {

                    $controlLine = $result['control_line'];
                    $verificationLine = $result['diagnosis_line'];
                    $longtermLine = $result['longterm_line'];




                    // matching reported and reference results
                    if (isset($result['reported_result']) && $result['reported_result'] != null) {
                        if ($result['reference_result'] == $result['reported_result']) {
                            $score = "Pass";
                        } else {
                            if ($result['sample_score'] > 0) {
                                $this->failureReason[] = array(
                                    'warning' => "Final interpretation for sample <strong>" . $result['sample_label'] . "</strong> reported wrongly",
                                    'correctiveAction' => "Final interpretation not matching with the expected results. Please review the RTRI SOP and/or job aide to ensure test procedures are followed and  interpretation of results are reported accurately."
                                );
                            }
                            $score = "Fail";
                        }
                    } else {
                        $score = "Fail";
                    }
                    if (0 == $result['control']) {
                        $maxScore += $result['sample_score'];
                    }

                    
                    $isAlgoWrong = false;

                    if (empty($controlLine) || empty($verificationLine) || empty($longtermLine)) {
                        $isAlgoWrong = true;
                    } else if ($controlLine == 'absent') {
                        $isAlgoWrong = true;
                    }

                    // if final result was reported as Recent
                    if ($result['reported_result'] == $possibleResults['R']) {
                        if ($controlLine == 'present' && $verificationLine == 'present' && $longtermLine == 'absent') {
                        } else {
                            $isAlgoWrong = true;
                        }
                    }

                    // if final result was reported as Long term
                    if ($result['reported_result'] == $possibleResults['LT']) {
                        if ($controlLine == 'present' && $verificationLine == 'present' && $longtermLine == 'present') {
                        } else {
                            $isAlgoWrong = true;
                        }
                    }
                    if ($isAlgoWrong) {
                        $score = "Fail";
                        $this->failureReason[] =  array(
                            'warning' => "Algorithm reported wrongly for sample <strong>" . $result['sample_label'] . "</strong>",
                            'correctiveAction' => "Identification of the presence or absence of RTRI lines/bands do not match the Final Interpretation reported. Please follow the RTRI SOP and/or job aide to report the presence or absence of RTRI lines/bands correctly."
                        );
                    }

                    if (0 == $result['control'] && $score == "Pass") {
                        $totalScore += $result['sample_score'];
                    }


                    if ($score == 'Fail' || (!isset($result['reported_result']) || $result['reported_result'] == "" || $result['reported_result'] == null) || ($result['reference_result'] != $result['reported_result'])) {
                        $this->db->update('response_result_recency', array('calculated_score' => "Fail"), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
                    } else {
                        $this->db->update('response_result_recency', array('calculated_score' => "Pass"), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
                    }
                }


                $configuredDocScore = ((isset($config->evaluation->recency->documentationScore) && $config->evaluation->recency->documentationScore != "" && $config->evaluation->recency->documentationScore != null) ? $config->evaluation->recency->documentationScore : 10);

                // Response Score
                if ($maxScore == 0 || $totalScore == 0) {
                    $responseScore = 0;
                } else {
                    $responseScore = round(($totalScore / $maxScore) * 100 * (100 - $configuredDocScore) / 100, 2);
                }

                $grandTotal = ($responseScore + $documentationScore);
                if ($grandTotal < $config->evaluation->recency->passPercentage) {
                    $scoreResult = 'Fail';
                    /* $this->failureReason[] = array(
                        'warning' => "Participant did not meet the score criteria (Participant Score is <strong>" . $grandTotal . "</strong> and Required Score is <strong>" . $config->evaluation->recency->passPercentage . "</strong>)",
                        'correctiveAction' => "Participant did not meet the score criteria (Participant Score is <strong>" . $grandTotal . "</strong> and Required Score is <strong>" . $config->evaluation->recency->passPercentage . "</strong>)",
                    ); */
                    $correctiveActionList[] = 15;
                } else {
                    $scoreResult = 'Pass';
                }

                // if we are excluding this result, then let us not give pass/fail				
                if ($shipment['is_excluded'] == 'yes' || $shipment['is_pt_test_not_performed'] == 'yes') {
                    $finalResult = '';
                    $totalScore = 0;
                    $shipmentResult[$counter]['shipment_score'] = $responseScore = 0;
                    $shipmentResult[$counter]['documentation_score'] = 0;
                    $shipmentResult[$counter]['display_result'] = '';
                    $shipmentResult[$counter]['is_followup'] = 'yes';
                    // $this->failureReason[] = array('warning' => 'Excluded from Evaluation');
                    $finalResult = 3;
                    $shipmentResult[$counter]['failure_reason'] = $this->failureReason = json_encode($this->failureReason);
                } else {
                    $shipment['is_excluded'] = 'no';

                    // if any of the results have failed, then the final result is fail
                    if ($scoreResult == 'Fail' || $mandatoryResult == 'Fail') {
                        $finalResult = 2;
                    } else {
                        $finalResult = 1;
                    }
                    $shipmentResult[$counter]['shipment_score'] = $responseScore = round($responseScore, 2);
                    $shipmentResult[$counter]['documentation_score'] = $documentationScore;
                    $scoreHolder[$shipment['map_id']] = $responseScore + $documentationScore;
                    $shipmentResult[$counter]['max_score'] = 100; //$maxScore;
                    $shipmentResult[$counter]['final_result'] = $finalResult;


                    $fRes = $this->db->fetchCol($this->db->select()->from('r_results', array('result_name'))->where('result_id = ' . $finalResult));

                    $shipmentResult[$counter]['display_result'] = $fRes[0];
                    $shipmentResult[$counter]['failure_reason'] = $this->failureReason = json_encode($this->failureReason);
                }
                // let us update the total score in DB
                $this->db->update('shipment_participant_map', array('shipment_score' => $responseScore, 'documentation_score' => $documentationScore, 'final_result' => $finalResult, 'failure_reason' => $this->failureReason), "map_id = " . $shipment['map_id']);

                //$counter++;
            } else {
                $this->failureReason[]['warning'] =  "Response was submitted after the last response date.";
                $this->db->update('shipment_participant_map', array('failure_reason' => json_encode($this->failureReason)), "map_id = " . $shipment['map_id']);
            }
            $counter++;
        }
        $this->db->update('shipment', array('max_score' => $maxScore, 'status' => 'evaluated'), "shipment_id = " . $shipmentId);


        return $shipmentResult;
    }


    // public function getSampleScore()
    // {

    //     $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
    //     $config = new Zend_Config_Ini($file, APPLICATION_ENV);

    //     $sampleScore = 0;
    //     return $sampleScore;
    // }

    public function getDocumentationScore($results, $attributes, $shipmentAttributes)
    {


        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, APPLICATION_ENV);

        //Let us now calculate documentation score
        $documentationScore = 0;
        $documentationPercentage = !empty($config->evaluation->recency->documentationScore) ? $config->evaluation->recency->documentationScore : 10;



        if (isset($shipmentAttributes['sampleType']) && $shipmentAttributes['sampleType'] == 'dried') {
            // for Dried Samples, we will have rehydration as one of the documentation scores
            $documentationScorePerItem = ($documentationPercentage / 5);
        } else {
            // for Non Dried Samples, we will NOT have rehydration documentation scores 
            // there are 2 conditions for rehydration so 5 - 2 = 3
            $documentationScorePerItem = ($documentationPercentage / 3);
        }

        // D.1
        if (isset($results[0]['shipment_receipt_date']) && strtolower($results[0]['shipment_receipt_date']) != '') {
            $documentationScore += $documentationScorePerItem;
        } else {
            /* $this->failureReason[] = array(
                'warning' => "Shipment Receipt Date not provided",
                'correctiveAction' => "Provide Shipment Receipt Date"
            ); */
        }

        //D.3
        if (isset($shipmentAttributes['sampleType']) && $shipmentAttributes['sampleType'] == 'dried') {
            // Only for Dried Samples we will check Sample Rehydration
            if (isset($attributes['sample_rehydration_date']) && trim($attributes['sample_rehydration_date']) != "") {
                $documentationScore += $documentationScorePerItem;
            } else {
                /* $this->failureReason[] = array(
                    'warning' => "Missing reporting rehydration date for Shipment Panel",
                    'correctiveAction' => "Provide Shipment rehydration Date"
                ); */
            }
        }

        //D.5
        if (isset($results[0]['shipment_test_date']) && trim($results[0]['shipment_test_date']) != "") {
            $documentationScore += $documentationScorePerItem;
        } else {
            /* $this->failureReason[] = array(
                'warning' => "Shipment test date not provided",
                'correctiveAction' => "Provide Shipment test date"
            ); */
        }

        //D.7
        if (isset($shipmentAttributes['sampleType']) && $shipmentAttributes['sampleType'] == 'dried') {

            // Only for Dried samples we will do this check

            // Testing should be done within 24*($config->evaluation->recency->sampleRehydrateDays) hours of rehydration.
            $sampleRehydrationDate = new DateTime($attributes['sample_rehydration_date']);
            $testedOnDate = new DateTime($results[0]['shipment_test_date']);
            $interval = $sampleRehydrationDate->diff($testedOnDate);

            $sampleRehydrateDays = $config->evaluation->recency->sampleRehydrateDays;
            $rehydrateHours = $sampleRehydrateDays * 24;

            if ($interval->days > $sampleRehydrateDays) {
                /* $this->failureReason[] = array(
                    'warning' => "Testing should be done within $rehydrateHours hours of rehydration.",
                    'correctiveAction' => "Testing should be done within $rehydrateHours hours of rehydration."
                ); */
            } else {
                $documentationScore += $documentationScorePerItem;
            }
        }

        //D.8
        if (isset($results[0]['supervisor_approval']) && strtolower($results[0]['supervisor_approval']) == 'yes' && trim($results[0]['participant_supervisor']) != "") {
            $documentationScore += $documentationScorePerItem;
        } else {
            /* $this->failureReason[] = array(
                'warning' => "Supervisor approval absent",
                'correctiveAction' => "Provide Supervisor approval",
            ); */
        }
        return $documentationScore;
    }
}
