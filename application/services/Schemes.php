<?php

class Application_Service_Schemes {

    public function getAllSchemes() {
        $schemeListDb = new Application_Model_DbTable_SchemeList();
        return $schemeListDb->getAllSchemes();
    }

    public function getAllDtsTestKit($countryAdapted = false) {

        $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
        return $testkitsDb->getActiveTestKitsNamesForScheme('dts',$countryAdapted);
    
    }
    public function getAllDtsTestKitList($countryAdapted = false) {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('r_testkitname_dts'), array('TESTKITNAMEID' => 'TESTKITNAME_ID', 'TESTKITNAME' => 'TESTKIT_NAME','testkit_1','testkit_2','testkit_3'))
                        ->where("scheme_type = 'dts'");

        if ($countryAdapted) {
            $sql = $sql->where('COUNTRYADAPTED = 1');
        }

        $stmt = $db->fetchAll($sql);

//        foreach ($stmt as $kitName) {
//            $retval[$kitName['TESTKITNAMEID']] = $kitName['TESTKITNAME'];
//        }
        return $stmt;
    }


    public function getEidExtractionAssay() {

        $db = new Application_Model_DbTable_EidExtractionAssay();
        return $db->fetchAll();
    }

    public function getEidDetectionAssay() {

        $db = new Application_Model_DbTable_EidDetectionAssay();
        return $db->fetchAll();
    }

    public function getVlAssay() {

        $db = new Application_Model_DbTable_VlAssay();
        return $db->fetchAll();
    }

    public function getDbsEia() {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $res = $db->fetchAll($db->select()->from('r_dbs_eia'));
        $response = array();
        foreach ($res as $row) {
            $response[$row['eia_id']] = $row['eia_name'];
        }
        return $response;
    }
    public function getDtsCorrectiveActions() {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $res = $db->fetchAll($db->select()->from('r_dts_corrective_actions'));
        $response = array();
        foreach ($res as $row) {
            $response[$row['action_id']] = $row['corrective_action'];
        }
        return $response;
    }

    public function getDbsWb() {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $res = $db->fetchAll($db->select()->from('r_dbs_wb'));
        $response = array();
        foreach ($res as $row) {
            $response[$row['wb_id']] = $row['wb_name'];
        }
        return $response;
    }

    public function getDtsSamples($sId, $pId) {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('ref' => 'reference_result_dts'))
                ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
                ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
                ->joinLeft(array('res' => 'response_result_dts'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('test_kit_name_1',
                    'lot_no_1',
                    'exp_date_1',
                    'test_result_1',
                    'test_kit_name_2',
                    'lot_no_2',
                    'exp_date_2',
                    'test_result_2',
                    'test_kit_name_3',
                    'lot_no_3',
                    'exp_date_3',
                    'test_result_3',
                    'reported_result'
                ))
                ->where('sp.shipment_id = ? ', $sId)
                ->where('sp.participant_id = ? ', $pId);
        return $db->fetchAll($sql);
    }
    
    public function getDtsReferenceData($shipmentId){
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('reference_result_dts'))
                ->where('shipment_id = ? ', $shipmentId);
        return $db->fetchAll($sql);        
    }

    public function getDbsSamples($sId, $pId) {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('ref' => 'reference_result_dbs'))
                ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
                ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
                ->joinLeft(array('res' => 'response_result_dbs'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('eia_1',
                    'lot_no_1',
                    'exp_date_1',
                    'od_1',
                    'cutoff_1',
                    'eia_2',
                    'lot_no_2',
                    'exp_date_2',
                    'od_2',
                    'cutoff_2',
                    'eia_3',
                    'lot_no_3',
                    'exp_date_3',
                    'od_3',
                    'cutoff_3',
                    'wb',
                    'wb_lot',
                    'wb_exp_date',
                    'wb_160',
                    'wb_120',
                    'wb_66',
                    'wb_55',
                    'wb_51',
                    'wb_41',
                    'wb_31',
                    'wb_24',
                    'wb_17',
                    'reported_result'
                ))
                ->where('sp.shipment_id = ? ', $sId)
                ->where('sp.participant_id = ? ', $pId);

        return $db->fetchAll($sql);
    }

    public function getEidSamples($sId, $pId) {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('ref' => 'reference_result_eid'))
                ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
                ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
                ->joinLeft(array('res' => 'response_result_eid'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('reported_result', 'hiv_ct_od', 'ic_qs'))
                ->where('sp.shipment_id = ? ', $sId)
                ->where('sp.participant_id = ? ', $pId);
        return $db->fetchAll($sql);
    }

    public function getVlSamples($sId, $pId) {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('ref' => 'reference_result_vl'))
                ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
                ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
                ->joinLeft(array('res' => 'response_result_vl'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('reported_viral_load', 'responseDate' => 'res.created_on'))
                ->where('sp.shipment_id = ? ', $sId)
                ->where('sp.participant_id = ? ', $pId);
        return $db->fetchAll($sql);
    }
    public function getTbSamples($sId, $pId) {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('ref' => 'reference_result_tb'))
                ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
                ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
                ->joinLeft(array('res' => 'response_result_tb'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('date_tested','mtb_detected','rif_resistance','probe_d','probe_c','probe_e','probe_b','spc','probe_a','responseDate' => 'res.created_on'))
                ->where('sp.shipment_id = ? ', $sId)
                ->where('sp.participant_id = ? ', $pId);
        return $db->fetchAll($sql);
    }

    public function getVlRange($sId) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $res = $db->fetchAll($db->select()->from('reference_vl_calculation', array('sample_id', 'vl_assay', 'low_limit', 'high_limit', 'sd', 'mean'))->where('shipment_id = ?', $sId));
        $response = array();
        foreach ($res as $row) {
            $response[$row['vl_assay']][$row['sample_id']]['low'] = $row['low_limit'];
            $response[$row['vl_assay']][$row['sample_id']]['high'] = $row['high_limit'];
            $response[$row['vl_assay']][$row['sample_id']]['sd'] = $row['sd'];
            $response[$row['vl_assay']][$row['sample_id']]['mean'] = $row['mean'];
        }

        return $response;
    }

    public function setVlRange($sId) {


        $db = Zend_Db_Table_Abstract::getDefaultAdapter();


        $vlAssayArray = $this->getVlAssay();

        foreach ($vlAssayArray as $vlAssay) {
            $sql = $db->select()->from(array('ref' => 'reference_result_vl'), array('shipment_id', 'sample_id'))
                    ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id', array())
                    ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id', array('participant_id'))
                    ->joinLeft(array('res' => 'response_result_vl'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('reported_viral_load'))
                    ->where('sp.shipment_id = ? ', $sId)
                    ->where('sp.attributes like ? ', '%"vl_assay":"' . $vlAssay['id'] . '"%');

            $response = $db->fetchAll($sql);
            $sampleWise = array();
            foreach ($response as $row) {
                $sampleWise[$vlAssay['id']][$row['sample_id']][] = $row['reported_viral_load'];
            }
            if (!isset($sampleWise[$vlAssay['id']])) {
                continue;
            }
            foreach ($sampleWise[$vlAssay['id']] as $sample => $reportedVl) {

                if ($reportedVl != "" && $reportedVl != null && count($reportedVl) > 7) {
                    $inputArray = $origArray = $reportedVl;

                    sort($inputArray);
                    $q1 = $this->getQuartile($inputArray, 0.25);
                    $q3 = $this->getQuartile($inputArray, 0.75);
                    $iqr = $q3 - $q1;
                    $quartileLowLimit = $q1 - ($iqr * 1.5);
                    $quartileHighLimit = $q3 + ($iqr * 1.5);

                    $newArray = array();
                    foreach ($inputArray as $a) {
                        if ($a >= $quartileLowLimit && $a <= $quartileHighLimit) {
                            $newArray[] = $a;
                        }
                    }
                    $avg = $this->getAverage($newArray);
                    $sd = $this->getStdDeviation($newArray);

                    $cv = $sd / $avg;

                    $finalLow = $avg - (3 * $sd);
                    $finalHigh = $avg + (3 * $sd);

                    //
                    //$newArray = array();
                    //foreach($origArray as $a){
                    //  if($a >= $finalLow && $a <= $finalHigh){
                    //	$newArray[] = $a;
                    //  }else{
                    //	$newArray[] = 'fail';
                    //  }
                    //}

                    $data = array('shipment_id' => $sId,
                        'vl_assay' => $vlAssay['id'],
                        'sample_id' => $sample,
                        'q1' => $q1,
                        'q3' => $q3,
                        'iqr' => $iqr,
                        'quartile_low' => $quartileLowLimit,
                        'quartile_high' => $quartileHighLimit,
                        'mean' => $avg,
                        'sd' => $sd,
                        'cv' => $cv,
                        'low_limit' => $finalLow,
                        'high_limit' => $finalHigh,
                    );

                    $db->delete('reference_vl_calculation', "sample_id=$sample and shipment_id=$sId");
                    $db->insert('reference_vl_calculation', $data);
                }
            }
        }
    }

    public function getQuartile($inputArray, $quartile) {
        $pos = (count($inputArray) - 1) * $quartile;

        $base = floor($pos);
        $rest = $pos - $base;

        if (isset($inputArray[$base + 1])) {
            return $inputArray[$base] + $rest * ($inputArray[$base + 1] - $inputArray[$base]);
        } else {
            return $inputArray[$base];
        }
    }

    public function getAverage($inputArray) {
        return array_sum($inputArray) / count($inputArray);
    }

    public function getStdDeviation($inputArray) {
        if (count($inputArray) < 2) {
            return;
        }

        $avg = $this->getAverage($inputArray);

        $sum = 0;
        foreach ($inputArray as $value) {
            $sum += pow($value - $avg, 2);
        }

        return sqrt((1 / (count($inputArray) - 1)) * $sum);
    }

    public function getShipmentData($sId, $pId) {

        $db = new Application_Model_DbTable_Shipments();
        return $db->getShipmentData($sId, $pId);
    }

    //public function getShipmentVl($sId,$pId){
    //	
    //	$db = new Application_Model_DbTable_ShipmentVl();
    //	return $db->getShipmentVl($sId,$pId);
    //	
    //}


    public function getSchemeControls($schemeId) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('r_control')->where("for_scheme='$schemeId'"));
    }

    public function getSchemeEvaluationComments($schemeId) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('r_evaluation_comments')->where("scheme='$schemeId'"));
    }

    public function getPossibleResults($schemeId) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('r_possibleresult')->where("scheme_id='$schemeId'"));
    }

    public function countEnrollmentSchemes() {
        $schemeListDb = new Application_Model_DbTable_SchemeList();
        return $schemeListDb->countEnrollmentSchemes();
    }
    public function getScheme($sid) {
        $schemeListDb = new Application_Model_DbTable_SchemeList();
        return $schemeListDb->fetchRow($schemeListDb->select()->where("scheme_id = ?", $sid));
    }

    public function addTestkit($params) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
            $testkitsDb->addTestkitDetails($params);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log($e->getMessage());
        }
    }

    public function updateTestkit($params) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
            $testkitsDb->updateTestkitDetails($params);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log($e->getMessage());
        }
    }
    public function updateTestkitStage($params) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
            $testkitsDb->updateTestkitStageDetails($params);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log($e->getMessage());
        }
    }

    public function getAllDtsTestKitInGrid($parameters) {
        $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
        return $testkitsDb->getAllTestKitsForAllSchemes($parameters);
    }

    public function getDtsTestkit($testkitId) {
        $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
        return $testkitsDb->getDtsTestkitDetails($testkitId);
    }

}
