<?php


class Application_Model_GenericTest
{

    public function __construct()
    {
    }

    public function evaluate($shipmentResult, $shipmentId)
    {
        $counter = 0;
        $maxScore = 0;
        $finalResult = null;
        $passingScore = 100;

        $schemeService = new Application_Service_Schemes();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        foreach ($shipmentResult as $shipment) {

            $shipment['is_excluded'] = 'no'; // setting it as no by default. It will become 'yes' if some condition matches.

            $createdOnUser = explode(" ", $shipment['shipment_test_report_date']);
            if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {

                $createdOn = new DateTime($createdOnUser[0]);
            } else {
                $createdOn = new DateTime('1970-01-01');
            }

            $lastDate = new DateTime($shipment['lastdate_response']);
            $results = $this->getSamplesForParticipant($shipmentId, $shipment['participant_id']);

            $totalScore = 0;
            $calculatedScore = 0;
            $maxScore = 0;
            $failureReason = array();
            $mandatoryResult = "";
            $scoreResult = "";
            if ($createdOn >= $lastDate) {
                $failureReason[] = array(
                    'warning' => "Response was submitted after the last response date."
                );
                $shipment['is_excluded'] = 'yes';
                $failureReason = array('warning' => "Response was submitted after the last response date.");
                $db->update('shipment_participant_map', array('failure_reason' => json_encode($failureReason)), "map_id = " . $shipment['map_id']);
            }
            foreach ($results as $result) {
                if(isset($result['reference_result']) && !empty($result['reference_result']) && isset($result['reported_result']) && !empty($result['reported_result'])){

                    // matching reported and reference results without Rif
                    if ($result['reference_result'] == $result['reported_result']) {
                        if (0 == $result['control']) {
                            $totalScore += $result['sample_score'];
                            $calculatedScore = $result['sample_score'];
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

                $db->update('response_result_generic_test', array('calculated_score' => $calculatedScore), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);

            }
            if ($maxScore > 0 && $totalScore > 0) {
                $totalScore = ($totalScore / $maxScore) * 100;
            }

            // if we are excluding this result, then let us not give pass/fail				
            if ($shipment['is_excluded'] == 'yes' || $shipment['is_pt_test_not_performed'] == 'yes') {
                $finalResult = '';
                $totalScore = 0;
                $responseScore = 0;
                $shipmentResult[$counter]['shipment_score'] = $responseScore;
                $shipmentResult[$counter]['documentation_score'] = 0;
                $shipmentResult[$counter]['display_result'] = '';
                $shipmentResult[$counter]['is_followup'] = 'yes';
                $shipmentResult[$counter]['is_excluded'] = 'yes';
                $failureReason[] = array('warning' => 'Excluded from Evaluation');
                $finalResult = 3;
                $shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);
            } else {
                $shipment['is_excluded'] = 'no';


                // checking if total score >= passing score
                if ($totalScore >= $passingScore) {
                    $scoreResult = 'Pass';
                } else {
                    $scoreResult = 'Fail';
                    $failureReason[]['warning'] = "Participant did not meet the score criteria (Participant Score - <strong>$totalScore</strong> and Required Score - <strong>$passingScore</strong>)";
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
                $db->update('shipment_participant_map', array('shipment_score' => $totalScore, 'final_result' => $finalResult, 'failure_reason' => $failureReason), "map_id = " . $shipment['map_id']);
            }
            $counter++;


        }

        $db->update('shipment', array('max_score' => $maxScore, 'status' => 'evaluated'), "shipment_id = " . $shipmentId);
        return $shipmentResult;
    }

    public function getSamplesForParticipant($sId, $pId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(array('ref' => 'reference_result_generic_test'),array('shipment_id', 'sample_id', 'sample_label', 'reference_result', 'control', 'mandatory'))
            ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
            ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
            ->joinLeft(array('res' => 'response_result_generic_test'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('shipment_map_id', 'result', 'repeat_result', 'reported_result', 'additional_detail', 'comments'))
            ->where('sp.shipment_id = '.$sId.' AND sp.participant_id = '.$pId.'');
        // die($sql);
        return $db->fetchAll($sql);
    }

    public function getAllTbAssays()
    {
        $tbAssayDb = new Application_Model_DbTable_TbAssay();
        return $tbAssayDb->fetchAllTbAssay();
    }

    public function getTbAssayName($assayId)
    {
        $tbAssayDb = new Application_Model_DbTable_TbAssay();
        return $tbAssayDb->getTbAssayName($assayId);
    }
    
    public function getTbAssayDrugResistanceStatus($assayId)
    {
        $tbAssayDb = new Application_Model_DbTable_TbAssay();
        return $tbAssayDb->fetchTbAssayDrugResistanceStatus($assayId);
    }
}
