<?php

class Application_Model_DbTable_ResponseCovid19 extends Zend_Db_Table_Abstract
{

    protected $_name = 'response_result_covid19';
    protected $_primary = array('shipment_map_id', 'sample_id');

    public function updateResults($params)
    {
        $sampleIds = $params['sampleId'];
        if (isset($params['isPtTestNotPerformed']) && $params['isPtTestNotPerformed'] == 'yes') {
            return $this->removeShipmentResults($params['smid']);
        }
        foreach ($sampleIds as $key => $sampleId) {
            // die("shipment_map_id = ".$params['smid'] . " and sample_id = ".$sampleId);
            $res = $this->fetchRow("shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $testTypeDb = new Application_Model_DbTable_TestTypenameCovid19();
            if (isset($params['test_type_1']) && trim($params['test_type_1']) == 'other') {
                $otherTestkitId1 = $testTypeDb->addTestTypeInParticipant($params['test_type_other_update_1'], $params['test_type_other_1'], 'covid19', 1);
                $params['test_type_1'] = $otherTestkitId1;
            }

            if (isset($params['test_type_2']) && trim($params['test_type_2']) == 'other') {
                $otherTestkitId2 = $testTypeDb->addTestTypeInParticipant($params['test_type_other_update_2'], $params['test_type_other_2'], 'covid19', 2);
                $params['test_type_2'] = $otherTestkitId2;
            }

            if (isset($params['test_type_3']) && trim($params['test_type_3']) == 'other') {
                $otherTestkitId3 = $testTypeDb->addTestTypeInParticipant($params['test_type_other_update_3'], $params['test_type_other_3'], 'covid19', 3);
                $params['test_type_3'] = $otherTestkitId3;
            }
            $data = array(
                'test_type_1'       => $params['test_type_1'],
                'lot_no_1'          => $params['lot_no_1'],
                'exp_date_1'        => Pt_Commons_General::dateFormat($params['exp_date_1']),
                'test_result_1'     => $params['test_result_1'][$key],
                'test_type_2'       => (isset($params['numberOfParticipantTest']) && $params['numberOfParticipantTest'] >= 2)?$params['test_type_2']:null,
                'lot_no_2'          => (isset($params['numberOfParticipantTest']) && $params['numberOfParticipantTest'] >= 2)?$params['lot_no_2']:null,
                'exp_date_2'        => (isset($params['numberOfParticipantTest']) && $params['numberOfParticipantTest'] >= 2)?Pt_Commons_General::dateFormat($params['exp_date_2']):null,
                'test_result_2'     => (isset($params['numberOfParticipantTest']) && $params['numberOfParticipantTest'] >= 2)?$params['test_result_2'][$key]:null,
                'test_type_3'       => (isset($params['numberOfParticipantTest']) && $params['numberOfParticipantTest'] >= 3)?$params['test_type_3']:null,
                'lot_no_3'          => (isset($params['numberOfParticipantTest']) && $params['numberOfParticipantTest'] >= 3)?$params['lot_no_3']:null,
                'exp_date_3'        => (isset($params['numberOfParticipantTest']) && $params['numberOfParticipantTest'] >= 3)?Pt_Commons_General::dateFormat($params['exp_date_3']):null,
                'test_result_3'     => (isset($params['numberOfParticipantTest']) && $params['numberOfParticipantTest'] >= 3)?$params['test_result_3'][$key]:null,
                'reported_result'   => $params['reported_result'][$key],
            );
            
            // Zend_Debug::dump($params);die;
            if ($res == null || count($res) == 0) {
                $data['shipment_map_id'] = $params['smid'];
                $data['sample_id'] = $sampleId;
                $data['created_by'] = $authNameSpace->dm_id;
                $data['created_on'] = new Zend_Db_Expr('now()');
                $this->insert($data);
            } else {
                $data['updated_by'] = $authNameSpace->dm_id;
                $data['updated_on'] = new Zend_Db_Expr('now()');
                $this->update($data, "shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            }
        }
    }
    public function removeShipmentResults($mapId)
    {

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $data = array(
            'test_type_1' => '',
            'lot_no_1' => '',
            'exp_date_1' => '',
            'test_result_1' => '',
            'test_type_2' => '',
            'lot_no_2' => '',
            'exp_date_2' => '',
            'test_result_2' => '',
            'test_type_3' => '',
            'lot_no_3' => '',
            'exp_date_3' => '',
            'test_result_3' => '',
            'reported_result' => '',
            'updated_by' => $authNameSpace->dm_id,
            'updated_on' => new Zend_Db_Expr('now()')
        );

        return $this->update($data, "shipment_map_id = " . $mapId);
    }

    public function updateResultsByAPI($params, $dm, $allSamples)
    {
        if (isset($params['covid19Data']->Section3->data->isPtTestNotPerformedRadio) && $params['covid19Data']->Section3->data->isPtTestNotPerformedRadio == 'yes') {
            return $this->removeShipmentResults($params['mapId']);
        }
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, APPLICATION_ENV);
        $testThreeOptional = false;$testTwoOptional = false;

        $testAllowed = $config->evaluation->covid19->covid19MaximumTestAllowed;
        if (isset($testAllowed) && ($testAllowed == '1' || $testAllowed == '2')) {
            $testThreeOptional = true;
        }
        
        if (isset($testAllowed) && $testAllowed != '3' && $testAllowed != '2') {
            $testTwoOptional = true;
        }
        
        $sampleIds = $params['covid19Data']->Section5->data->samples->id;
        foreach ($sampleIds as $key => $sampleId) {
            $testTypeDb = new Application_Model_DbTable_TestTypenameCovid19();
            if (isset($params['covid19Data']->Section3->data->typeValue[0]) && trim($params['covid19Data']->Section3->data->typeValue[0]) == 'other') {
                $otherTestkitId1 = $testTypeDb->addTestTypeInParticipantByAPI($allSamples[0]["test_type_1"], $params['covid19Data']->Section3->data->kitOther[0], 'covid19', 1);
                $params['test_type_1'] = $otherTestkitId1;
            } else {
                $params['test_type_1'] = (isset($params['covid19Data']->Section3->data->typeValue[0]) && $params['covid19Data']->Section3->data->typeValue[0] != '') ? $params['covid19Data']->Section3->data->typeValue[0] : '';
            }
            if (isset($params['covid19Data']->Section3->data->typeValue[1]) && trim($params['covid19Data']->Section3->data->typeValue[1]) == 'other') {
                $otherTestkitId2 = $testTypeDb->addTestTypeInParticipantByAPI($allSamples[0]["test_type_2"], $params['covid19Data']->Section3->data->kitOther[1], 'covid19', 2);
                $params['test_type_2'] = $otherTestkitId2;
            } else {
                $params['test_type_2'] = (isset($params['covid19Data']->Section3->data->typeValue[1]) && $params['covid19Data']->Section3->data->typeValue[1] != '') ? $params['covid19Data']->Section3->data->typeValue[1] : '';
            }
            if (isset($params['covid19Data']->Section3->data->typeValue[2]) && trim($params['covid19Data']->Section3->data->typeValue[2]) == 'other') {
                $otherTestkitId3 = $testTypeDb->addTestTypeInParticipantByAPI($allSamples[0]["test_type_3"], $params['covid19Data']->Section3->data->kitOther[2], 'covid19', 3);
                $params['test_type_3'] = $otherTestkitId3;
            } else {
                $params['test_type_3'] = (isset($params['covid19Data']->Section3->data->typeValue[2]) && $params['covid19Data']->Section3->data->typeValue[2] != '') ? $params['covid19Data']->Section3->data->typeValue[2] : '';
            }
            $result2 = (isset($params['covid19Data']->Section5->data->samples->result2[$key]->value) && $params['covid19Data']->Section5->data->samples->result2[$key]->value != '') ? (string)$params['covid19Data']->Section5->data->samples->result2[$key]->value : '';
            $result3 = (isset($params['covid19Data']->Section5->data->samples->result3[$key]->value) && $params['covid19Data']->Section5->data->samples->result3[$key]->value != '') ? (string)$params['covid19Data']->Section5->data->samples->result3[$key]->value : '';

            if ($testTwoOptional) {
                $params['test_type_2'] = '';
                $result2 = '';
            }
            if ($testThreeOptional) {
                $params['test_type_3'] = '';
                $result3 = '';
            }
            /* Fetching long data into the single variable */            
            $noOfTest       = $params['covid19Data']->Section2->data->numberOfTestsSelected;
            $lot1           = (isset($params['covid19Data']->Section3->data->lot[0]) && $params['covid19Data']->Section3->data->lot[0] != "")?$params['covid19Data']->Section3->data->lot[0]:null;
            $lot2           = (isset($params['covid19Data']->Section3->data->lot[1]) && $params['covid19Data']->Section3->data->lot[1] != "")?$params['covid19Data']->Section3->data->lot[1]:null;
            $lot3           = (isset($params['covid19Data']->Section3->data->lot[2]) && $params['covid19Data']->Section3->data->lot[2] != "")?$params['covid19Data']->Section3->data->lot[2]:null;
            $expDate1       = (isset($params['covid19Data']->Section3->data->expDate[0]) && $params['covid19Data']->Section3->data->expDate[0] != "")?$params['covid19Data']->Section3->data->expDate[0]:null;
            $expDate2       = (isset($params['covid19Data']->Section3->data->expDate[1]) && $params['covid19Data']->Section3->data->expDate[1] != "")?$params['covid19Data']->Section3->data->expDate[1]:null;
            $expDate3       = (isset($params['covid19Data']->Section3->data->expDate[2]) && $params['covid19Data']->Section3->data->expDate[2] != "")?$params['covid19Data']->Section3->data->expDate[2]:null;
            $result1        = (isset($params['covid19Data']->Section3->data->samples->result1[$key]->value) && $params['covid19Data']->Section3->data->samples->result1[$key]->value != "")?$params['covid19Data']->Section3->data->samples->result1[$key]->value:null;
            $reportedResult = (isset($params['covid19Data']->Section5->data->samples->finalResult[$key]->value) && $params['covid19Data']->Section5->data->samples->finalResult[$key]->value != '') ? $params['covid19Data']->Section5->data->samples->finalResult[$key]->value : '';
            
            $data = array(
                'test_type_1'       => $params['test_type_1'],
                'lot_no_1'          => (isset($noOfTest) && $noOfTest >=1)?$lot1:null,
                'exp_date_1'        => (isset($noOfTest) && $noOfTest >=1)?$expDate1:null,
                'test_result_1'     => (isset($noOfTest) && $noOfTest >=1)?$result1:null,
                'test_type_2'       => $params['test_type_2'],
                'lot_no_1'          => (isset($noOfTest) && $noOfTest >=2)?$lot2:null,
                'exp_date_1'        => (isset($noOfTest) && $noOfTest >=2)?$expDate2:null,
                'test_result_1'     => (isset($noOfTest) && $noOfTest >=2)?$result2:null,
                'test_type_3'       => $params['test_type_3'],
                'lot_no_1'          => (isset($noOfTest) && $noOfTest >=3)?$lot3:null,
                'exp_date_1'        => (isset($noOfTest) && $noOfTest >=3)?$expDate3:null,
                'test_result_1'     => (isset($noOfTest) && $noOfTest >=3)?$result3:null,
                'reported_result'   => $reportedResult,
            );
            // Zend_Debug::dump($params);die;
            $res = $this->fetchRow("shipment_map_id = " . $params['mapId'] . " and sample_id = " . $sampleId);
            if ($res == null || $res === FALSE) {
                $data['shipment_map_id'] = $params['mapId'];
                $data['sample_id'] = $sampleId;
                $data['created_by'] = $dm['dm_id'];
                $data['created_on'] = new Zend_Db_Expr('now()');
                $this->insert($data);
            } else {
                $data['updated_by'] = $dm['dm_id'];
                $data['updated_on'] = new Zend_Db_Expr('now()');
                $this->update($data, "shipment_map_id = " . $params['mapId'] . " and sample_id = " . $sampleId);
            }
        }
        return true;
    }
}
