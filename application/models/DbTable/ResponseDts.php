<?php

class Application_Model_DbTable_ResponseDts extends Zend_Db_Table_Abstract
{

    protected $_name = 'response_result_dts';
    protected $_primary = array('shipment_map_id', 'sample_id');

    public function updateResults($params)
    {
        $res = [];
        $sampleIds = $params['sampleId'];
        try {
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
                $data = array(
                    'shipment_map_id'           => $params['smid'],
                    'sample_id'                 => $sampleId,
                    'test_kit_name_1'           => $params['test_kit_name_1'],
                    'lot_no_1'                  => $params['lot_no_1'],
                    'qc_done_1'                 => $params['qc_done_1'] ?? null,
                    'qc_date_1'                 => Pt_Commons_General::isoDateFormat($params['qc_date_1']),
                    'repeat_qc_done_1'          => $params['repeat_qc_done_1'] ?? null,
                    'repeat_qc_date_1'          => Pt_Commons_General::isoDateFormat($params['repeat_qc_date_1']),
                    'qc_done_2'                 => $params['qc_done_2'] ?? null,
                    'qc_date_2'                 => Pt_Commons_General::isoDateFormat($params['qc_date_2']),
                    'repeat_qc_done_2'          => $params['repeat_qc_done_2'] ?? null,
                    'repeat_qc_date_2'          => Pt_Commons_General::isoDateFormat($params['repeat_qc_date_2']),
                    'qc_done_3'                 => $params['qc_done_3'] ?? null,
                    'qc_date_3'                 => Pt_Commons_General::isoDateFormat($params['qc_date_3']),
                    'repeat_qc_done_3'          => $params['repeat_qc_done_3'] ?? null,
                    'repeat_qc_date_3'          => Pt_Commons_General::isoDateFormat($params['repeat_qc_date_3']),

                    'exp_date_1'                => Pt_Commons_General::isoDateFormat($params['exp_date_1']),
                    'test_result_1'             => $params['test_result_1'][$key] ?? null,
                    'syphilis_result'           => $params['syphilis_result'][$key] ?? null,
                    'test_kit_name_2'           => $params['test_kit_name_2'],
                    'lot_no_2'                  => $params['lot_no_2'],
                    'exp_date_2'                => Pt_Commons_General::isoDateFormat($params['exp_date_2']),
                    'test_result_2'             => $params['test_result_2'][$key] ?? null,
                    'test_kit_name_3'           => $params['test_kit_name_3'],
                    'lot_no_3'                  => $params['lot_no_3'],
                    'exp_date_3'                => Pt_Commons_General::isoDateFormat($params['exp_date_3']),
                    'test_result_3'             => $params['test_result_3'][$key] ?? null,
                    'repeat_test_kit_name_1'    => $params['repeat_test_kit_name_1'] ?? null,
                    'repeat_test_kit_name_2'    => $params['repeat_test_kit_name_2'] ?? null,
                    'repeat_test_kit_name_3'    => $params['repeat_test_kit_name_3'] ?? null,
                    'repeat_lot_no_1'           => $params['repeat_lot_no_1'] ?? null,
                    'repeat_lot_no_2'           => $params['repeat_lot_no_2'] ?? null,
                    'repeat_lot_no_3'           => $params['repeat_lot_no_3'] ?? null,
                    'repeat_exp_date_1'         => $params['repeat_exp_date_1'] ?? null,
                    'repeat_exp_date_2'         => $params['repeat_exp_date_2'] ?? null,
                    'repeat_exp_date_3'         => $params['repeat_exp_date_3'] ?? null,
                    'repeat_test_result_1'      => $params['repeat_test_result_1'][$key] ?? null,
                    'repeat_test_result_2'      => $params['repeat_test_result_2'][$key] ?? null,
                    'repeat_test_result_3'      => $params['repeat_test_result_3'][$key] ?? null,
                    'kit_additional_info'       => !empty($params['additionalInfoKit'][$sampleId]) ? json_encode($params['additionalInfoKit'][$sampleId], true) : null,
                    'reported_result'           => (isset($params['reported_result'][$key])) ? $params['reported_result'][$key] : null,
                    'syphilis_final'            => (isset($params['syphilis_final'][$key])) ? $params['syphilis_final'][$key] : null,
                    'is_this_retest'            => (isset($params['is_this_retest'][$key])) ? $params['is_this_retest'][$key] : null
                );

                if (isset($params['enableRtri']) && $params['enableRtri'] == 'yes') {
                    $data['dts_rtri_control_line'] = (isset($params['controlLine'][$key]) && !empty($params['controlLine'][$key])) ? $params['controlLine'][$key] : null;
                    $data['dts_rtri_diagnosis_line'] = (isset($params['verificationLine'][$key]) && !empty($params['verificationLine'][$key])) ? $params['verificationLine'][$key] : null;
                    $data['dts_rtri_longterm_line'] = (isset($params['longtermLine'][$key]) && !empty($params['longtermLine'][$key])) ? $params['longtermLine'][$key] : null;
                    $data['dts_rtri_reported_result'] = (isset($params['rtriResult'][$key]) && !empty($params['rtriResult'][$key])) ? $params['rtriResult'][$key] : null;
                    $data['dts_rtri_is_editable'] = (isset($params['dtsRtriIsEditable'][$key]) && !empty($params['dtsRtriIsEditable'][$key])) ? $params['dtsRtriIsEditable'][$key] : null;
                }
                $id = 0;
                if ($res == null) {
                    $data['created_by'] = $authNameSpace->dm_id;
                    $data['created_on'] = new Zend_Db_Expr('now()');
                    $id = $this->insert($data);
                } else {
                    $data['updated_by'] = $authNameSpace->dm_id;
                    $data['updated_on'] = new Zend_Db_Expr('now()');
                    $id = $this->update($data, "shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
                    echo "updated => " . $id;
                }
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
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
            'reported_result' => '',
            'syphilis_final' => '',
            'is_this_retest' => '',
            'dts_rtri_control_line' => '',
            'dts_rtri_diagnosis_line' => '',
            'dts_rtri_longterm_line' => '',
            'dts_rtri_reported_result' => '',
            'kit_additional_info' => null,
            'updated_by' => $authNameSpace->dm_id,
            'updated_on' => new Zend_Db_Expr('now()')
        );
        // Zend_Debug::dump($mapId);die;
        return $this->update($data, "shipment_map_id = " . $mapId);
    }

    public function updateResultsByAPI($params, $dm, $allSamples)
    {
        try {
            $res = [];
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
            // Zend_Debug::dump($key);die;

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

                if (isset($params['dtsData']->Section3->data->kitValue[3]) && trim($params['dtsData']->Section3->data->kitValue[3]) == 'other') {
                    $otherRepeatTestkitId1 = $testkitsDb->addTestkitInParticipantByAPI($allSamples[0]["repeat_test_kit_name_1"], $params['dtsData']->Section3->data->kitOther[3], 'dts', 1);
                    $params['repeat_test_kit_name_1'] = $otherRepeatTestkitId1;
                } else {
                    $params['repeat_test_kit_name_1'] = (isset($params['dtsData']->Section3->data->kitValue[3]) && $params['dtsData']->Section3->data->kitValue[3] != '') ? $params['dtsData']->Section3->data->kitValue[3] : '';
                }
                if (isset($params['dtsData']->Section3->data->kitValue[4]) && trim($params['dtsData']->Section3->data->kitValue[4]) == 'other') {
                    $otherRepeatTestkitId2 = $testkitsDb->addTestkitInParticipantByAPI($allSamples[1]["repeat_test_kit_name_2"], $params['dtsData']->Section3->data->kitOther[4], 'dts', 2);
                    $params['repeat_test_kit_name_2'] = $otherRepeatTestkitId2;
                } else {
                    $params['repeat_test_kit_name_2'] = (isset($params['dtsData']->Section3->data->kitValue[4]) && $params['dtsData']->Section3->data->kitValue[4] != '') ? $params['dtsData']->Section3->data->kitValue[4] : '';
                }
                if (isset($params['dtsData']->Section3->data->kitValue[5]) && trim($params['dtsData']->Section3->data->kitValue[5]) == 'other') {
                    $otherRepeatTestkitId3 = $testkitsDb->addTestkitInParticipantByAPI($allSamples[2]["repeat_test_kit_name_3"], $params['dtsData']->Section3->data->kitOther[5], 'dts', 3);
                    $params['repeat_test_kit_name_3'] = $otherRepeatTestkitId3;
                } else {
                    $params['repeat_test_kit_name_3'] = (isset($params['dtsData']->Section3->data->kitValue[5]) && $params['dtsData']->Section3->data->kitValue[5] != '') ? $params['dtsData']->Section3->data->kitValue[5] : '';
                }

                $result3 = (isset($params['dtsData']->Section4->data->samples->result3[$key]->value) && $params['dtsData']->Section4->data->samples->result3[$key]->value != '') ? (string)$params['dtsData']->Section4->data->samples->result3[$key]->value : '';
                $repeatResult3 = (isset($params['dtsData']->Section4->data->samples->repeatResult3[$key]->value) && $params['dtsData']->Section4->data->samples->repeatResult3[$key]->value != '') ? (string)$params['dtsData']->Section4->data->samples->repeatResult3[$key]->value : '';
                if ($testThreeOptional) {
                    $params['test_kit_name_3'] = '';
                    $result3 = '';
                    $repeatResult3 = '';
                }

                if (((isset($params['dtsData']->Section3->data->expDate[0]) && $params['dtsData']->Section3->data->expDate[0] != '')) || isset($params['dtsData']->Section3->data->expdate[0]) && $params['dtsData']->Section3->data->expdate[0] != '') {
                    $expDate1 = (isset($params['dtsData']->Section3->data->expDate[0])) ? $params['dtsData']->Section3->data->expDate[0] : $params['dtsData']->Section3->data->expdate[0];
                }
                if (((isset($params['dtsData']->Section3->data->expDate[1]) && $params['dtsData']->Section3->data->expDate[1] != '')) || isset($params['dtsData']->Section3->data->expdate[1]) && $params['dtsData']->Section3->data->expdate[1] != '') {
                    $expDate2 = (isset($params['dtsData']->Section3->data->expDate[1])) ? $params['dtsData']->Section3->data->expDate[1] : $params['dtsData']->Section3->data->expdate[1];
                }
                if (((isset($params['dtsData']->Section3->data->expDate[2]) && $params['dtsData']->Section3->data->expDate[2] != '')) || isset($params['dtsData']->Section3->data->expdate[2]) && $params['dtsData']->Section3->data->expdate[2] != '') {
                    $expDate3 = (isset($params['dtsData']->Section3->data->expDate[2])) ? $params['dtsData']->Section3->data->expDate[2] : $params['dtsData']->Section3->data->expdate[2];
                }
                $data = array(
                    'shipment_map_id'           => $params['mapId'],
                    'sample_id'                 => $sampleId,
                    'test_kit_name_1'           => $params['test_kit_name_1'],
                    'lot_no_1'                  => (isset($params['dtsData']->Section3->data->lot[0]) && $params['dtsData']->Section3->data->lot[0] != '') ? $params['dtsData']->Section3->data->lot[0] : '',
                    'exp_date_1'                => date('Y-m-d', strtotime($expDate1)),
                    'test_result_1'             => (isset($params['dtsData']->Section4->data->samples->result1[$key]->value) && $params['dtsData']->Section4->data->samples->result1[$key]->value != '') ? (string)$params['dtsData']->Section4->data->samples->result1[$key]->value : '',
                    'test_kit_name_2'           => $params['test_kit_name_2'],
                    'lot_no_2'                  => (isset($params['dtsData']->Section3->data->lot[1]) && $params['dtsData']->Section3->data->lot[1] != '') ? $params['dtsData']->Section3->data->lot[1] : '',
                    'exp_date_2'                => date('Y-m-d', strtotime($expDate2)),
                    'test_result_2'             => (isset($params['dtsData']->Section4->data->samples->result2[$key]->value) && $params['dtsData']->Section4->data->samples->result2[$key]->value != '') ? (string)$params['dtsData']->Section4->data->samples->result2[$key]->value : '',
                    'test_kit_name_3'           => $params['test_kit_name_3'],
                    'lot_no_3'                  => (isset($params['dtsData']->Section3->data->lot[2]) && $params['dtsData']->Section3->data->lot[2] != '' && !$testThreeOptional) ? $params['dtsData']->Section3->data->lot[2] : '',
                    'exp_date_3'                => date('Y-m-d', strtotime($expDate3)),
                    'test_result_3'             => $result3,
                    'repeat_test_kit_name_1'    => $params['repeat_test_kit_name_1'],
                    'repeat_test_kit_name_2'    => $params['repeat_test_kit_name_2'],
                    'repeat_test_kit_name_3'    => $params['repeat_test_kit_name_3'],
                    'repeat_lot_no_1'           => (isset($params['dtsData']->Section3->data->lot[3]) && $params['dtsData']->Section3->data->lot[3] != '') ? $params['dtsData']->Section3->data->lot[3] : '',
                    'repeat_lot_no_2'           => (isset($params['dtsData']->Section3->data->lot[4]) && $params['dtsData']->Section3->data->lot[4] != '') ? $params['dtsData']->Section3->data->lot[4] : '',
                    'repeat_lot_no_3'           => (isset($params['dtsData']->Section3->data->lot[5]) && $params['dtsData']->Section3->data->lot[5] != '' && !$testThreeOptional) ? $params['dtsData']->Section3->data->lot[5] : '',
                    'repeat_exp_date_1'         => (isset($params['dtsData']->Section3->data->expDate[3]) && $params['dtsData']->Section3->data->expDate[3] != '') ? date('Y-m-d', strtotime($params['dtsData']->Section3->data->expDate[3])) : null,
                    'repeat_exp_date_2'         => (isset($params['dtsData']->Section3->data->expDate[4]) && $params['dtsData']->Section3->data->expDate[4] != '') ? date('Y-m-d', strtotime($params['dtsData']->Section3->data->expDate[4])) : null,
                    'repeat_exp_date_3'         => (isset($params['dtsData']->Section3->data->expDate[5]) && $params['dtsData']->Section3->data->expDate[5] != '' && !$testThreeOptional) ? date('Y-m-d', strtotime($params['dtsData']->Section3->data->expDate[5])) : null,
                    'repeat_test_result_1'      => (isset($params['dtsData']->Section4->data->samples->repeatResult1[$key]->value) && $params['dtsData']->Section4->data->samples->repeatResult1[$key]->value != '') ? $params['dtsData']->Section4->data->samples->repeatResult1[$key]->value : '',
                    'repeat_test_result_2'      => (isset($params['dtsData']->Section4->data->samples->repeatResult2[$key]->value) && $params['dtsData']->Section4->data->samples->repeatResult2[$key]->value != '') ? $params['dtsData']->Section4->data->samples->repeatResult2[$key]->value : '',
                    'repeat_test_result_3'      => $repeatResult3,
                    'reported_result'           => (isset($params['dtsData']->Section4->data->samples->finalResult[$key]->value) && $params['dtsData']->Section4->data->samples->finalResult[$key]->value != '') ? (string)$params['dtsData']->Section4->data->samples->finalResult[$key]->value : ''
                );

                if (empty($res)) {
                    $data['created_by'] = $dm['dm_id'];
                    $data['created_on'] = new Zend_Db_Expr('now()');
                    $this->insert($data);
                } else {
                    $data['updated_by'] = $dm['dm_id'];
                    $data['updated_on'] = new Zend_Db_Expr('now()');
                    $this->update($data, "shipment_map_id = " . $params['mapId'] . " and sample_id = " . $sampleId);
                }
                $key++;
            }
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
        }
    }
}
