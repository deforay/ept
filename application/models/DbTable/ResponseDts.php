<?php

class Application_Model_DbTable_ResponseDts extends Zend_Db_Table_Abstract
{

    protected $_name = 'response_result_dts';
    protected $_primary = array('shipment_map_id', 'sample_id');

    public function updateResults($params)
    {
        $res = array();
        $sampleIds = $params['sampleId'];

        foreach ($sampleIds as $key => $sampleId) {
            //die("shipment_map_id = ".$params['smid'] . " and sample_id = ".$sampleId);
            $res = $this->fetchRow("shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
            if (isset($params['test_kit_name_1']) && trim($params['test_kit_name_1']) == 'other') {
                $otherTestkitId1 = $testkitsDb->addTestkitInParticipant($params['test_kit_other_name_update_1'], $params['test_kit_other_name_1'], 'dts', 1);
                $params['test_kit_name_1'] = $otherTestkitId1;
            }
            if (isset($params['test_kit_name_2']) && trim($params['test_kit_name_2']) == 'other') {
                $otherTestkitId2 = $testkitsDb->addTestkitInParticipant($params['test_kit_other_name_update_2'], $params['test_kit_other_name_2'], 'dts', 2);
                $params['test_kit_name_2'] = $otherTestkitId2;
            }
            if (isset($params['test_kit_name_3']) && trim($params['test_kit_name_3']) == 'other') {
                $otherTestkitId3 = $testkitsDb->addTestkitInParticipant($params['test_kit_other_name_update_3'], $params['test_kit_other_name_3'], 'dts', 3);
                $params['test_kit_name_3'] = $otherTestkitId3;
            }

            if (isset($params['repeat_test_kit_name_1']) && trim($params['repeat_test_kit_name_1']) == 'other') {
                $otherRepeatTestkitId1 = $testkitsDb->addTestkitInParticipant($params['retest_test_kit_other_name_update_1'], $params['repeat_test_kit_name_1'], 'dts', 2);
                $params['repeat_test_kit_name_1'] = $otherRepeatTestkitId1;
            }
            if (isset($params['repeat_test_kit_name_2']) && trim($params['repeat_test_kit_name_2']) == 'other') {
                $otherRepeatTestkitId1 = $testkitsDb->addTestkitInParticipant($params['retest_test_kit_other_name_update_2'], $params['repeat_test_kit_name_2'], 'dts', 2);
                $params['repeat_test_kit_name_2'] = $otherRepeatTestkitId1;
            }
            if (isset($params['repeat_test_kit_name_3']) && trim($params['repeat_test_kit_name_3']) == 'other') {
                $otherRepeatTestkitId3 = $testkitsDb->addTestkitInParticipant($params['retest_test_kit_other_name_update_3'], $params['repeat_test_kit_name_3'], 'dts', 3);
                $params['repeat_test_kit_name_3'] = $otherRepeatTestkitId3;
            }

            // Zend_Debug::dump($params);die;
            if ($res == null || count($res) == 0) {
                $this->insert(array(
                    'shipment_map_id'           => $params['smid'],
                    'sample_id'                 => $sampleId,
                    'test_kit_name_1'           => $params['test_kit_name_1'],
                    'lot_no_1'                  => $params['lot_no_1'],
                    'exp_date_1'                => Pt_Commons_General::dateFormat($params['exp_date_1']),
                    'test_result_1'             => $params['test_result_1'][$key],
                    'test_kit_name_2'           => $params['test_kit_name_2'],
                    'lot_no_2'                  => $params['lot_no_2'],
                    'exp_date_2'                => Pt_Commons_General::dateFormat($params['exp_date_2']),
                    'test_result_2'             => $params['test_result_2'][$key],
                    'test_kit_name_3'           => $params['test_kit_name_3'],
                    'lot_no_3'                  => $params['lot_no_3'],
                    'exp_date_3'                => Pt_Commons_General::dateFormat($params['exp_date_3']),
                    'test_result_3'             => $params['test_result_3'][$key],
                    'repeat_test_kit_name_1'    => $params['repeat_test_kit_name_1'],
                    'repeat_test_kit_name_2'    => $params['repeat_test_kit_name_2'],
                    'repeat_test_kit_name_3'    => $params['repeat_test_kit_name_3'],
                    'repeat_lot_no_1'           => $params['repeat_lot_no_1'],
                    'repeat_lot_no_2'           => $params['repeat_lot_no_2'],
                    'repeat_lot_no_3'           => $params['repeat_lot_no_3'],
                    'repeat_exp_date_1'         => $params['repeat_exp_date_1'],
                    'repeat_exp_date_2'         => $params['repeat_exp_date_2'],
                    'repeat_exp_date_3'         => $params['repeat_exp_date_3'],
                    'repeat_test_result_1'      => $params['repeat_test_result_1'][$key],
                    'repeat_test_result_2'      => $params['repeat_test_result_2'][$key],
                    'repeat_test_result_3'      => $params['repeat_test_result_3'][$key],
                    'reported_result'           => $params['reported_result'][$key],
                    'created_by'                => $authNameSpace->dm_id,
                    'created_on'                => new Zend_Db_Expr('now()')
                ));
            } else {
                $this->update(array(
                    'test_kit_name_1'           => $params['test_kit_name_1'],
                    'lot_no_1'                  => $params['lot_no_1'],
                    'exp_date_1'                => Pt_Commons_General::dateFormat($params['exp_date_1']),
                    'test_result_1'             => $params['test_result_1'][$key],
                    'test_kit_name_2'           => $params['test_kit_name_2'],
                    'lot_no_2'                  => $params['lot_no_2'],
                    'exp_date_2'                => Pt_Commons_General::dateFormat($params['exp_date_2']),
                    'test_result_2'             => $params['test_result_2'][$key],
                    'test_kit_name_3'           => $params['test_kit_name_3'],
                    'lot_no_3'                  => $params['lot_no_3'],
                    'exp_date_3'                => Pt_Commons_General::dateFormat($params['exp_date_3']),
                    'test_result_3'             => $params['test_result_3'][$key],
                    'repeat_test_kit_name_1'    => $params['repeat_test_kit_name_1'],
                    'repeat_test_kit_name_2'    => $params['repeat_test_kit_name_2'],
                    'repeat_test_kit_name_3'    => $params['repeat_test_kit_name_3'],
                    'repeat_lot_no_1'           => $params['repeat_lot_no_1'],
                    'repeat_lot_no_2'           => $params['repeat_lot_no_2'],
                    'repeat_lot_no_3'           => $params['repeat_lot_no_3'],
                    'repeat_exp_date_1'         => Pt_Commons_General::dateFormat($params['repeat_exp_date_1']),
                    'repeat_exp_date_2'         => Pt_Commons_General::dateFormat($params['repeat_exp_date_2']),
                    'repeat_exp_date_3'         => Pt_Commons_General::dateFormat($params['repeat_exp_date_3']),
                    'repeat_test_result_1'      => $params['repeat_test_result_1'][$key],
                    'repeat_test_result_2'      => $params['repeat_test_result_2'][$key],
                    'repeat_test_result_3'      => $params['repeat_test_result_3'][$key],
                    'repeat_test_result_1'      => $params['repeat_test_result_1'][$key],
                    'repeat_test_result_2'      => $params['repeat_test_result_2'][$key],
                    'repeat_test_result_3'      => $params['repeat_test_result_3'][$key],
                    'reported_result'           => $params['reported_result'][$key],
                    'updated_by'                => $authNameSpace->dm_id,
                    'updated_on'                => new Zend_Db_Expr('now()')
                ), "shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            }
        }
    }
    public function removeShipmentResults($mapId)
    {

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $data = array(
            'test_kit_name_1' => '',
            'lot_no_1' => '',
            'exp_date_1' => '',
            'test_result_1' => '',
            'test_kit_name_2' => '',
            'lot_no_2' => '',
            'exp_date_2' => '',
            'test_result_2' => '',
            'test_kit_name_3' => '',
            'lot_no_3' => '',
            'exp_date_3' => '',
            'test_result_3' => '',
            'repeat_test_result_1' => '',
            'repeat_test_result_2' => '',
            'repeat_test_result_3' => '',
            'repeat_test_kit_name_1' => '',
            'repeat_test_kit_name_2' => '',
            'repeat_test_kit_name_3' => '',
            'repeat_lot_no_1' => '',
            'repeat_lot_no_2' => '',
            'repeat_lot_no_3' => '',
            'repeat_exp_date_1' => '',
            'repeat_exp_date_2' => '',
            'repeat_exp_date_3' => '',
            'repeat_test_result_1' => '',
            'repeat_test_result_2' => '',
            'repeat_test_result_3' => '',
            'reported_result' => '',
            'updated_by' => $authNameSpace->dm_id,
            'updated_on' => new Zend_Db_Expr('now()')
        );

        return $this->update($data, "shipment_map_id = " . $mapId);
    }

    public function updateResultsByAPI($params, $dm, $allSamples)
    {
        $res = array();
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, APPLICATION_ENV);
        $testThreeOptional = false;
        if (isset($config->evaluation->dts->dtsOptionalTest3) && $config->evaluation->dts->dtsOptionalTest3 == 'yes') {
            if (isset($params['dtsData']->Section2->data->algorithmUsedSelected) && $params['dtsData']->Section2->data->algorithmUsedSelected == 'myanmarNationalDtsAlgo') {
                $testThreeOptional = false;
            } else {
                $testThreeOptional = true;
            }
        }
        $sampleIds = $params['dtsData']->Section4->data->samples->id;
        foreach ($sampleIds as $key => $sampleId) {
            $res = $this->fetchRow("shipment_map_id = " . $params['mapId'] . " and sample_id = " . $sampleId);

            $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
            if (isset($params['dtsData']->Section3->data->kitValue[0]) && trim($params['dtsData']->Section3->data->kitValue[0]) == 'other') {
                $otherTestkitId1 = $testkitsDb->addTestkitInParticipantByAPI($allSamples[0]["test_kit_name_1"], $params['dtsData']->Section3->data->kitOther[0], 'dts', 1);
                $params['test_kit_name_1'] = $otherTestkitId1;
            } else {
                $params['test_kit_name_1'] = (isset($params['dtsData']->Section3->data->kitValue[0]) && $params['dtsData']->Section3->data->kitValue[0] != '') ? $params['dtsData']->Section3->data->kitValue[0] : '';
            }
            if (isset($params['dtsData']->Section3->data->kitValue[1]) && trim($params['dtsData']->Section3->data->kitValue[1]) == 'other') {
                $otherTestkitId2 = $testkitsDb->addTestkitInParticipantByAPI($allSamples[0]["test_kit_name_2"], $params['dtsData']->Section3->data->kitOther[1], 'dts', 2);
                $params['test_kit_name_2'] = $otherTestkitId2;
            } else {
                $params['test_kit_name_2'] = (isset($params['dtsData']->Section3->data->kitValue[1]) && $params['dtsData']->Section3->data->kitValue[1] != '') ? $params['dtsData']->Section3->data->kitValue[1] : '';
            }
            if (isset($params['dtsData']->Section3->data->kitValue[2]) && trim($params['dtsData']->Section3->data->kitValue[2]) == 'other') {
                $otherTestkitId3 = $testkitsDb->addTestkitInParticipantByAPI($allSamples[0]["test_kit_name_3"], $params['dtsData']->Section3->data->kitOther[2], 'dts', 3);
                $params['test_kit_name_3'] = $otherTestkitId3;
            } else {
                $params['test_kit_name_3'] = (isset($params['dtsData']->Section3->data->kitValue[2]) && $params['dtsData']->Section3->data->kitValue[2] != '') ? $params['dtsData']->Section3->data->kitValue[2] : '';
            }

            if (isset($params['dtsData']->Section3->data->repeatKitValue[0]) && trim($params['dtsData']->Section3->data->repeatKitValue[0]) == 'other') {
                $otherTestkitId1 = $testkitsDb->addTestkitInParticipantByAPI($allSamples[0]["repeat_test_kit_name_1"], $params['dtsData']->Section3->data->repeatKitOther[0], 'dts', 1);
                $params['repeat_test_kit_name_1'] = $otherTestkitId1;
            } else {
                $params['repeat_test_kit_name_1'] = (isset($params['dtsData']->Section3->data->repeatKitValue[0]) && $params['dtsData']->Section3->data->repeatKitValue[0] != '') ? $params['dtsData']->Section3->data->repeatKitValue[0] : '';
            }
            if (isset($params['dtsData']->Section3->data->repeatKitValue[1]) && trim($params['dtsData']->Section3->data->repeatKitValue[1]) == 'other') {
                $otherTestkitId2 = $testkitsDb->addTestkitInParticipantByAPI($allSamples[1]["repeat_test_kit_name_2"], $params['dtsData']->Section3->data->repeatKitOther[1], 'dts', 2);
                $params['repeat_test_kit_name_2'] = $otherTestkitId2;
            } else {
                $params['repeat_test_kit_name_2'] = (isset($params['dtsData']->Section3->data->repeatKitValue[1]) && $params['dtsData']->Section3->data->repeatKitValue[1] != '') ? $params['dtsData']->Section3->data->repeatKitValue[1] : '';
            }
            if (isset($params['dtsData']->Section3->data->repeatKitValue[2]) && trim($params['dtsData']->Section3->data->repeatKitValue[2]) == 'other') {
                $otherTestkitId3 = $testkitsDb->addTestkitInParticipantByAPI($allSamples[2]["repeat_test_kit_name_3"], $params['dtsData']->Section3->data->repeatKitOther[2], 'dts', 3);
                $params['repeat_test_kit_name_3'] = $otherTestkitId3;
            } else {
                $params['repeat_test_kit_name_3'] = (isset($params['dtsData']->Section3->data->repeatKitValue[2]) && $params['dtsData']->Section3->data->repeatKitValue[2] != '') ? $params['dtsData']->Section3->data->repeatKitValue[2] : '';
            }

            $result3 = (isset($params['dtsData']->Section4->data->samples->result3[$key]->value) && $params['dtsData']->Section4->data->samples->result3[$key]->value != '') ? (string)$params['dtsData']->Section4->data->samples->result3[$key]->value : '';
            $repeatResult3 = (isset($params['dtsData']->Section4->data->samples->repeatResult3[$key]->value) && $params['dtsData']->Section4->data->samples->repeatResult3[$key]->value != '') ? (string)$params['dtsData']->Section4->data->samples->repeatResult3[$key]->value : '';
            if ($testThreeOptional) {
                $params['test_kit_name_3'] = '';
                $result3 = '';
                $repeatResult3 = '';
            }
            // Zend_Debug::dump($params);die;
            $count = (isset($res) && $res != "") ? count($res) : 0;
            if ($res == null || $count == 0) {
                $this->insert(array(
                    'shipment_map_id'           => $params['mapId'],
                    'sample_id'                 => $sampleId,
                    'test_kit_name_1'           => $params['test_kit_name_1'],
                    'lot_no_1'                  => (isset($params['dtsData']->Section3->data->lot[0]) && $params['dtsData']->Section3->data->lot[0] != '') ? $params['dtsData']->Section3->data->lot[0] : '',
                    'exp_date_1'                => (isset($params['dtsData']->Section3->data->expDate[0]) && $params['dtsData']->Section3->data->expDate[0] != '') ? date('Y-m-d', strtotime($params['dtsData']->Section3->data->expDate[0])) : '',
                    'test_result_1'             => (isset($params['dtsData']->Section4->data->samples->result1[$key]->value) && $params['dtsData']->Section4->data->samples->result1[$key]->value != '') ? (string)$params['dtsData']->Section4->data->samples->result1[$key]->value : '',
                    'test_kit_name_2'           => $params['test_kit_name_2'],
                    'lot_no_2'                  => (isset($params['dtsData']->Section3->data->lot[1]) && $params['dtsData']->Section3->data->lot[1] != '') ? $params['dtsData']->Section3->data->lot[1] : '',
                    'exp_date_2'                => (isset($params['dtsData']->Section3->data->expDate[1]) && $params['dtsData']->Section3->data->expDate[1] != '') ? date('Y-m-d', strtotime($params['dtsData']->Section3->data->expDate[1])) : '',
                    'test_result_2'             => (isset($params['dtsData']->Section4->data->samples->result2[$key]->value) && $params['dtsData']->Section4->data->samples->result2[$key]->value != '') ? (string)$params['dtsData']->Section4->data->samples->result2[$key]->value : '',
                    'test_kit_name_3'           => $params['test_kit_name_3'],
                    'lot_no_3'                  => (isset($params['dtsData']->Section3->data->lot[2]) && $params['dtsData']->Section3->data->lot[2] != '' && !$testThreeOptional) ? $params['dtsData']->Section3->data->lot[2] : '',
                    'exp_date_3'                => (isset($params['dtsData']->Section3->data->expDate[2]) && $params['dtsData']->Section3->data->expDate[2] != '' && !$testThreeOptional) ? date('Y-m-d', strtotime($params['dtsData']->Section3->data->expDate[2])) : null,
                    'test_result_3'             => $result3,
                    'repeat_test_kit_name_1'    => $params['repeat_test_kit_name_1'],
                    'repeat_test_kit_name_2'    => $params['repeat_test_kit_name_2'],
                    'repeat_test_kit_name_3'    => $params['repeat_test_kit_name_3'],
                    'repeat_lot_no_1'           => (isset($params['dtsData']->Section3->data->repeatLot[0]) && $params['dtsData']->Section3->data->repeatLot[0] != '') ? $params['dtsData']->Section3->data->repeatLot[0] : '',
                    'repeat_lot_no_2'           => (isset($params['dtsData']->Section3->data->repeatLot[1]) && $params['dtsData']->Section3->data->repeatLot[1] != '') ? $params['dtsData']->Section3->data->repeatLot[1] : '',
                    'repeat_lot_no_3'           => (isset($params['dtsData']->Section3->data->repeatLot[2]) && $params['dtsData']->Section3->data->repeatLot[2] != '' && !$testThreeOptional) ? $params['dtsData']->Section3->data->repeatLot[2] : '',
                    'repeat_exp_date_1'         => (isset($params['dtsData']->Section3->data->repeatExpDate[0]) && $params['dtsData']->Section3->data->repeatExpDate[0] != '') ? date('Y-m-d', strtotime($params['dtsData']->Section3->data->repeatExpDate[0])) : null,
                    'repeat_exp_date_2'         => (isset($params['dtsData']->Section3->data->repeatExpDate[1]) && $params['dtsData']->Section3->data->repeatExpDate[1] != '') ? date('Y-m-d', strtotime($params['dtsData']->Section3->data->repeatExpDate[1])) : null,
                    'repeat_exp_date_3'         => (isset($params['dtsData']->Section3->data->repeatExpDate[2]) && $params['dtsData']->Section3->data->repeatExpDate[2] != '' && !$testThreeOptional) ? date('Y-m-d', strtotime($params['dtsData']->Section3->data->repeatExpDate[2])) : null,
                    'repeat_test_result_1'      => (isset($params['dtsData']->Section4->data->samples->repeatResult1[$key]->value) && $params['dtsData']->Section4->data->samples->repeatResult1[$key]->value != '') ? $params['dtsData']->Section4->data->samples->repeatResult1[$key]->value : '',
                    'repeat_test_result_2'      => (isset($params['dtsData']->Section4->data->samples->repeatResult2[$key]->value) && $params['dtsData']->Section4->data->samples->repeatResult2[$key]->value != '') ? $params['dtsData']->Section4->data->samples->repeatResult2[$key]->value : '',
                    'repeat_test_result_3'      => $repeatResult3,
                    'reported_result'           => (isset($params['dtsData']->Section4->data->samples->finalResult[$key]->value) && $params['dtsData']->Section4->data->samples->finalResult[$key]->value != '') ? (string)$params['dtsData']->Section4->data->samples->finalResult[$key]->value : '',
                    'created_by'                => $dm['dm_id'],
                    'created_on'                => new Zend_Db_Expr('now()')
                ));
            } else {
                $this->update(array(
                    'test_kit_name_1'           => $params['test_kit_name_1'],
                    'lot_no_1'                  => (isset($params['dtsData']->Section3->data->lot[0]) && $params['dtsData']->Section3->data->lot[0] != '') ? $params['dtsData']->Section3->data->lot[0] : '',
                    'exp_date_1'                => (isset($params['dtsData']->Section3->data->expDate[0]) && $params['dtsData']->Section3->data->expDate[0] != '') ? date('Y-m-d', strtotime($params['dtsData']->Section3->data->expDate[0])) : '',
                    'test_result_1'             => (isset($params['dtsData']->Section4->data->samples->result1[$key]->value) && $params['dtsData']->Section4->data->samples->result1[$key]->value != '') ? $params['dtsData']->Section4->data->samples->result1[$key]->value : '',
                    'test_kit_name_2'           => $params['test_kit_name_2'],
                    'lot_no_2'                  => (isset($params['dtsData']->Section3->data->lot[1]) && $params['dtsData']->Section3->data->lot[1] != '') ? $params['dtsData']->Section3->data->lot[1] : '',
                    'exp_date_2'                => (isset($params['dtsData']->Section3->data->expDate[1]) && $params['dtsData']->Section3->data->expDate[1] != '') ? date('Y-m-d', strtotime($params['dtsData']->Section3->data->expDate[1])) : '',
                    'test_result_2'             => (isset($params['dtsData']->Section4->data->samples->result2[$key]->value) && $params['dtsData']->Section4->data->samples->result2[$key]->value != '') ? $params['dtsData']->Section4->data->samples->result2[$key]->value : '',
                    'test_kit_name_3'           => $params['test_kit_name_3'],
                    'lot_no_3'                  => (isset($params['dtsData']->Section3->data->lot[2]) && $params['dtsData']->Section3->data->lot[2] != '' && !$testThreeOptional) ? $params['dtsData']->Section3->data->lot[2] : '',
                    'exp_date_3'                => (isset($params['dtsData']->Section3->data->expDate[2]) && $params['dtsData']->Section3->data->expDate[2] != '' && !$testThreeOptional) ? date('Y-m-d', strtotime($params['dtsData']->Section3->data->expDate[2])) : null,
                    'test_result_3'             => $result3,
                    'repeat_test_kit_name_1'    => $params['repeat_test_kit_name_1'],
                    'repeat_test_kit_name_2'    => $params['repeat_test_kit_name_2'],
                    'repeat_test_kit_name_3'    => $params['repeat_test_kit_name_3'],
                    'repeat_lot_no_1'           => (isset($params['dtsData']->Section3->data->repeatLot[0]) && $params['dtsData']->Section3->data->repeatLot[0] != '') ? $params['dtsData']->Section3->data->repeatLot[0] : '',
                    'repeat_lot_no_2'           => (isset($params['dtsData']->Section3->data->repeatLot[1]) && $params['dtsData']->Section3->data->repeatLot[1] != '') ? $params['dtsData']->Section3->data->repeatLot[1] : '',
                    'repeat_lot_no_3'           => (isset($params['dtsData']->Section3->data->repeatLot[2]) && $params['dtsData']->Section3->data->repeatLot[2] != '' && !$testThreeOptional) ? $params['dtsData']->Section3->data->repeatLot[2] : '',
                    'repeat_exp_date_1'         => (isset($params['dtsData']->Section3->data->repeatExpDate[0]) && $params['dtsData']->Section3->data->repeatExpDate[0] != '') ? date('Y-m-d', strtotime($params['dtsData']->Section3->data->repeatExpDate[0])) : null,
                    'repeat_exp_date_2'         => (isset($params['dtsData']->Section3->data->repeatExpDate[1]) && $params['dtsData']->Section3->data->repeatExpDate[1] != '') ? date('Y-m-d', strtotime($params['dtsData']->Section3->data->repeatExpDate[1])) : null,
                    'repeat_exp_date_3'         => (isset($params['dtsData']->Section3->data->repeatExpDate[2]) && $params['dtsData']->Section3->data->repeatExpDate[2] != '' && !$testThreeOptional) ? date('Y-m-d', strtotime($params['dtsData']->Section3->data->repeatExpDate[2])) : null,
                    'repeat_test_result_1'      => (isset($params['dtsData']->Section4->data->samples->repeatResult1[$key]->value) && $params['dtsData']->Section4->data->samples->repeatResult1[$key]->value != '') ? $params['dtsData']->Section4->data->samples->repeatResult1[$key]->value : '',
                    'repeat_test_result_2'      => (isset($params['dtsData']->Section4->data->samples->repeatResult2[$key]->value) && $params['dtsData']->Section4->data->samples->repeatResult2[$key]->value != '') ? $params['dtsData']->Section4->data->samples->repeatResult2[$key]->value : '',
                    'repeat_test_result_3'      => $repeatResult3,
                    'reported_result'           => (isset($params['dtsData']->Section4->data->samples->finalResult[$key]->value) && $params['dtsData']->Section4->data->samples->finalResult[$key]->value != '') ? $params['dtsData']->Section4->data->samples->finalResult[$key]->value : '',
                    'updated_by'                => $dm['dm_id'],
                    'updated_on'                => new Zend_Db_Expr('now()')
                ), "shipment_map_id = " . $params['mapId'] . " and sample_id = " . $sampleId);
            }
        }
        return true;
    }
}
