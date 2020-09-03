<?php


class Application_Model_Eid
{

    public function __construct()
    {
        
    }

    public function evaluate($shipmentResult, $shipmentId)
    {
        $counter = 0;
        $maxScore = 0;
        $scoreHolder = array();
        $finalResult = null;
        $schemeService = new Application_Service_Schemes();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        foreach ($shipmentResult as $shipment) {
            $createdOnUser = explode(" ", $shipment['shipment_test_report_date']);
            if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {

                $createdOn = new Zend_Date($createdOnUser[0], Zend_Date::ISO_8601);
            } else {
                $datearray = array('year' => 1970, 'month' => 1, 'day' => 01);
                $createdOn = new Zend_Date($datearray);
            }

            $lastDate = new Zend_Date($shipment['lastdate_response'], Zend_Date::ISO_8601);
            if ($createdOn->compare($lastDate) <= 0) {
                $results = $schemeService->getEidSamples($shipmentId, $shipment['participant_id']);
                $totalScore = 0;
                $maxScore = 0;
                $mandatoryResult = "";
                $scoreResult = "";
                $failureReason = array();
                foreach ($results as $result) {
                    // matching reported and reference results
                    if (isset($result['reported_result']) && $result['reported_result'] != null) {
                        if ($result['reference_result'] == $result['reported_result']) {
                            if (0 == $result['control']) {
                                $totalScore += $result['sample_score'];
                            }
                        } else {
                            if ($result['sample_score'] > 0) {
                                $failureReason[]['warning'] = "Control/Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                            }
                        }
                    }
                    if (0 == $result['control']) {
                        $maxScore += $result['sample_score'];
                    }

                    // checking if mandatory fields were entered and were entered right
                    //if ($result['mandatory'] == 1) {
                    //    if ((!isset($result['reported_result']) || $result['reported_result'] == "" || $result['reported_result'] == null)) {
                    //        $mandatoryResult = 'Fail';
                    //        $failureReason[]['warning'] = "Mandatory Control/Sample <strong>" . $result['sample_label'] . "</strong> was not reported";
                    //    } else if (($result['reference_result'] != $result['reported_result'])) {
                    //        $mandatoryResult = 'Fail';
                    //        $failureReason[]['warning'] = "Mandatory Control/Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                    //    }
                    //}
                }


                $totalScore = ($totalScore / $maxScore) * 100;
                $maxScore = 100;



                // if we are excluding this result, then let us not give pass/fail				
                if ($shipment['is_excluded'] == 'yes' || $shipment['is_pt_test_not_performed'] == 'yes') {
                    $finalResult = '';
                    $totalScore = 0;
                    $shipmentResult[$counter]['shipment_score'] = $responseScore = 0;
                    $shipmentResult[$counter]['documentation_score'] = 0;
                    $shipmentResult[$counter]['display_result'] = '';
                    $shipmentResult[$counter]['is_followup'] = 'yes';
                    $failureReason[] = array('warning' => 'Excluded from Evaluation');
                    $finalResult = 3;
                    $shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);
                } else {
                    $shipment['is_excluded'] = 'no';


                    // checking if total score and maximum scores are the same
                    if ($totalScore != $maxScore) {
                        $scoreResult = 'Fail';
                        $failureReason[]['warning'] = "Participant did not meet the score criteria (Participant Score - <strong>$totalScore</strong> and Required Score - <strong>$maxScore</strong>)";
                    } else {
                        $scoreResult = 'Pass';
                    }

                    // if any of the results have failed, then the final result is fail
                    if ($scoreResult == 'Fail' || $mandatoryResult == 'Fail') {
                        $finalResult = 2;
                    } else {
                        $finalResult = 1;
                    }
                    $shipmentResult[$counter]['shipment_score'] = $totalScore = round($totalScore, 2);
                    $shipmentResult[$counter]['max_score'] = 100; //$maxScore;
                    $shipmentResult[$counter]['final_result'] = $finalResult;


                    $fRes = $db->fetchCol($db->select()->from('r_results', array('result_name'))->where('result_id = ' . $finalResult));

                    $shipmentResult[$counter]['display_result'] = $fRes[0];
                    $shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);
                }


                // let us update the total score in DB
                $db->update('shipment_participant_map', array('shipment_score' => $totalScore, 'final_result' => $finalResult, 'failure_reason' => $failureReason), "map_id = " . $shipment['map_id']);
                //$counter++;
            } else {
                $failureReason = array('warning' => "Response was submitted after the last response date.");
                $db->update('shipment_participant_map', array('failure_reason' => json_encode($failureReason)), "map_id = " . $shipment['map_id']);
            }
            $counter++;
        }
        $db->update('shipment', array('max_score' => $maxScore, 'status' => 'evaluated'), "shipment_id = " . $shipmentId);

        //Zend_Debug::dump($shipmentResult);die;

        return $shipmentResult;
    }
}
