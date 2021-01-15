<?php


class Application_Model_Vl
{

    public function __construct()
    {
        
    }

    public function evaluate($shipmentResult, $shipmentId, $reEvaluate)
    {
        $counter = 0;
        $maxScore = 0;
        $scoreHolder = array();
        $finalResult = null;
        $schemeService = new Application_Service_Schemes();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, APPLICATION_ENV);
        $passPercentage = $config->evaluation->vl->passPercentage;

        $vlRange = $schemeService->getVlRange($shipmentId);

        if ($reEvaluate || $vlRange == null || $vlRange == "" || count($vlRange) == 0) {
            $schemeService->setVlRange($shipmentId);
            $vlRange = $schemeService->getVlRange($shipmentId);
        }

        foreach ($shipmentResult as $shipment) {

            $createdOnUser = explode(" ", $shipment['shipment_test_report_date']);
            if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {

                $createdOn = new DateTime($createdOnUser[0]);
            } else {
                $createdOn = null;
            }

            $lastDate = new DateTime($shipment['lastdate_response']);

            //Zend_Debug::dump($createdOn->isEarlier($lastDate));die;
            if (!empty($createdOn) && $createdOn <= $lastDate) {

                $results = $schemeService->getVlSamples($shipmentId, $shipment['participant_id']);
                $totalScore = 0;
                $maxScore = 0;
                $mandatoryResult = "";
                $scoreResult = "";
                $failureReason = array();

                $attributes = json_decode($shipment['attributes'], true);

                foreach ($results as $result) {
                    if ($result['control'] == 1) continue;
                    $calcResult = "";
                    $responseAssay = json_decode($result['attributes'], true);
                    $responseAssay = isset($responseAssay['vl_assay']) ? $responseAssay['vl_assay'] : "";
                    if (isset($vlRange[$responseAssay])) {
                        // matching reported and low/high limits
                        if (isset($result['reported_viral_load']) && $result['reported_viral_load'] != null) {
                            if (isset($vlRange[$responseAssay][$result['sample_id']]) && $vlRange[$responseAssay][$result['sample_id']]['low'] <= $result['reported_viral_load'] && $vlRange[$responseAssay][$result['sample_id']]['high'] >= $result['reported_viral_load']) {
                                $totalScore += $result['sample_score'];
                                $calcResult = "pass";
                            } else {
                                if ($result['sample_score'] > 0) {
                                    $failureReason[]['warning'] = "Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                                }
                                $calcResult = "fail";
                            }
                        }
                    } else {
                        $totalScore = "N/A";
                        $calcResult = "excluded";
                    }

                    $maxScore += $result['sample_score'];

                    $db->update('response_result_vl', array('calculated_score' => $calcResult), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);

                    //// checking if mandatory fields were entered and were entered right
                    //if ($result['mandatory'] == 1) {
                    //	if ((!isset($result['reported_viral_load']) || $result['reported_viral_load'] == "" || $result['reported_viral_load'] == null)) {
                    //		$mandatoryResult = 'Fail';
                    //		$failureReason[]['warning'] = "Mandatory Sample <strong>" . $result['sample_label'] . "</strong> was not reported";
                    //	}
                    //	//else if(($result['reported_viral_load'] != $result['reported_viral_load'])){
                    //	//	$mandatoryResult = 'Fail';
                    //	//	$failureReason[]= "Mandatory Sample <strong>".$result['sample_label']."</strong> was reported wrongly";
                    //	//}
                    //}
                }



                // if we are excluding this result, then let us not give pass/fail				
                if ($shipment['is_excluded'] == 'yes' || $shipment['is_pt_test_not_performed'] == 'yes') {
                    $finalResult = '';
                    $totalScore = 0;
                    $failureReason = array();
                    $shipmentResult[$counter]['shipment_score'] = $responseScore = 0;
                    $shipmentResult[$counter]['documentation_score'] = 0;
                    $shipmentResult[$counter]['display_result'] = 'Excluded';
                    $shipmentResult[$counter]['is_followup'] = 'yes';
                    $failureReason[] = array('warning' => 'Excluded from Evaluation');
                    $finalResult = 3;
                    $shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);
                } else {
                    $shipment['is_excluded'] = 'no';


                    // checking if total score and maximum scores are the same
                    if ($totalScore == 'N/A') {
                        $failureReason[]['warning'] = "Could not determine score. Not enough responses found in the chosen VL Assay.";
                        $scoreResult = 'Not Evaluated';
                        $shipment['is_excluded'] = 'yes';
                    } else if ($totalScore != $maxScore) {
                        $scoreResult = 'Fail';
                        if ($maxScore != 0) {
                            $totalScore = ($totalScore / $maxScore) * 100;
                        }
                        $failureReason[]['warning'] = "Participant did not meet the score criteria (Participant Score - <strong>$totalScore</strong> and Required Score - <strong>$passPercentage</strong>)";
                    } else {
                        if ($maxScore != 0) {
                            $totalScore = ($totalScore / $maxScore) * 100;
                        }
                        $scoreResult = 'Pass';
                    }


                    // if $finalResult == 3 , then  excluded

                    if ($scoreResult == 'Not Evaluated') {
                        $finalResult = 4;
                    } else if ($scoreResult == 'Fail' || $mandatoryResult == 'Fail') {
                        $finalResult = 2;
                    } else {
                        $finalResult = 1;
                    }

                    $shipmentResult[$counter]['shipment_score'] = $totalScore;
                    $shipmentResult[$counter]['max_score'] = $passPercentage; //$maxScore;



                    $fRes = $db->fetchCol($db->select()->from('r_results', array('result_name'))->where('result_id = ' . $finalResult));

                    $shipmentResult[$counter]['display_result'] = $fRes[0];
                    $shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);
                    //Zend_Debug::dump($shipmentResult[$counter]);
                    // let us update the total score in DB
                    if ($totalScore == 'N/A') {
                        $totalScore = 0;
                    }
                }



                $nofOfRowsUpdated = $db->update('shipment_participant_map', array('shipment_score' => $totalScore, 'final_result' => $finalResult, 'failure_reason' => $failureReason), "map_id = " . $shipment['map_id']);
            } else {
                $failureReason = array('warning' => "Response was submitted after the last response date.");

                $db->update('shipment_participant_map', array('failure_reason' => json_encode($failureReason)), "map_id = " . $shipment['map_id']);
            }
            $counter++;
        }
        $db->update('shipment', array('max_score' => $maxScore, 'status' => 'evaluated'), "shipment_id = " . $shipmentId);
        return $shipmentResult;
    }
}
