<?php

use Application_Service_QuantitativeCalculations as QuantitativeCalculations;

class Application_Service_Schemes
{

    public function getAllSchemes()
    {
        $schemeListDb = new Application_Model_DbTable_SchemeList();
        return $schemeListDb->getAllSchemes();
    }
    public function getFullSchemeList()
    {
        $schemeListDb = new Application_Model_DbTable_SchemeList();
        return $schemeListDb->getFullSchemeList();
    }

    public function getAllDtsTestKit($countryAdapted = false)
    {

        $testkitsDb = new Application_Model_DbTable_Testkitnames();
        return $testkitsDb->getActiveTestKitsNamesForScheme('dts', $countryAdapted);
    }

    public function getAllCovid19TestType($countryAdapted = false)
    {

        $testPlatformsDb = new Application_Model_DbTable_TestTypenameCovid19();
        return $testPlatformsDb->getActiveTestTypesNamesForScheme('covid19', $countryAdapted);
    }

    public function getAllCovid19TestTypeResponseWise($scheme, $countryAdapted = false)
    {

        $testPlatformsDb = new Application_Model_DbTable_TestTypenameCovid19();
        return $testPlatformsDb->getActiveTestTypesNamesForSchemeResponseWise($scheme, $countryAdapted);
    }


    public function getAllCovid19TestTypeList($countryAdapted = false)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('r_test_type_covid19'))
            ->where("scheme_type = 'covid19'");

        if ($countryAdapted) {
            $sql = $sql->where('country_adapted = 1');
        }

        return $db->fetchAll($sql);
    }

    public function getRecommededCovid19TestTypes($testPlatforms = null)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('covid19_recommended_test_types'));

        if ($testPlatforms != null && (int) $testPlatforms > 0 && (int) $testPlatforms <= 3) {
            $sql = $sql->where('test_no = ' . (int) $testPlatforms);
        }
        // die($sql);
        $stmt = $db->fetchAll($sql);
        $retval = [];
        foreach ($stmt as $t) {
            $retval[$t['test_no']][] = $t['test_type'];
        }
        return $retval;
    }

    public function setRecommededDtsTestkit($recommended, $testMode = 'dts')
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->delete('dts_recommended_testkits', 'dts_test_mode = "' . $testMode . '"');
        foreach ($recommended as $testNo => $kits) {
            if (!empty($kits)) {
                foreach ($kits as $kit) {
                    $data = array(
                        'test_no' => $testNo,
                        'testkit' => $kit,
                        'dts_test_mode' => $testMode,
                    );
                    $db->insert('dts_recommended_testkits', $data);
                }
            }
        }
    }

    public function setRecommededCovid19TestTypes($recommended)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->delete('covid19_recommended_test_types');
        foreach ($recommended as $testNo => $types) {
            if (isset($types) && $types != NULL) {
                foreach ($types as $type) {
                    $data = array(
                        'test_no' => $testNo,
                        'test_type' => $type,
                    );
                    $db->insert('covid19_recommended_test_types', $data);
                }
            }
        }
    }
    public function setRecommededCustomTestTypes($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->delete('generic_recommended_test_types', 'scheme_id = "' . $params['schemeCode'] . '"');
        if (isset($params['customTestkit']) && !empty($params['customTestkit'])) {
            foreach ($params['customTestkit'] as $kit) {
                $data = array(
                    'scheme_id' => $params['schemeCode'],
                    'testkit' => $kit,
                );
                $db->insert('generic_recommended_test_types', $data);
            }
        }
    }

    public function getEidExtractionAssay()
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $res = $db->fetchAll($db->select()
            ->from('r_eid_extraction_assay')
            ->where("`status` like 'active'")
            ->order('sort_order'));
        $response = [];
        foreach ($res as $row) {
            $response[$row['id']] = $row['name'];
        }
        return $response;
    }

    public function getEidDetectionAssay()
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $res = $db->fetchAll($db->select()->from('r_eid_detection_assay')->where("`status` like 'active'")->order('sort_order'));
        $response = [];
        foreach ($res as $row) {
            $response[$row['id']] = $row['name'];
        }
        return $response;
    }
    public function getRecencyAssay()
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $res = $db->fetchAll($db->select()->from('r_recency_assay')->order('sort_order'));
        $response = [];
        foreach ($res as $row) {
            $response[$row['id']] = $row['name'];
        }
        return $response;
    }

    public function getVlAssay($option = true)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $res = $db->fetchAll($db->select()->from('r_vl_assay')->where("`status` like 'active'"));
        $response = [];
        if ($option) {
            foreach ($res as $row) {
                $response[$row['id']] = $row['name'];
            }
            return $response;
        } else {
            return $res;
        }
    }

    public function getDbsEia()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $res = $db->fetchAll($db->select()->from('r_dbs_eia'));
        $response = [];
        foreach ($res as $row) {
            $response[$row['eia_id']] = $row['eia_name'];
        }
        return $response;
    }

    public function getCovid19CorrectiveActions()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $res = $db->fetchAll($db->select()->from('r_covid19_corrective_actions'));
        $response = [];
        foreach ($res as $row) {
            $response[$row['action_id']] = $row['corrective_action'];
        }
        return $response;
    }

    public function getDbsWb()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $res = $db->fetchAll($db->select()->from('r_dbs_wb'));
        $response = [];
        foreach ($res as $row) {
            $response[$row['wb_id']] = $row['wb_name'];
        }
        return $response;
    }

    public function getCovid19Samples($sId, $pId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('ref' => 'reference_result_covid19'))
            ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
            ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
            ->joinLeft(array('res' => 'response_result_covid19'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array(
                'test_type_1',
                'name_of_pcr_reagent_1',
                'pcr_reagent_lot_no_1',
                'pcr_reagent_exp_date_1',
                'lot_no_1',
                'exp_date_1',
                'test_result_1',
                'test_type_2',
                'name_of_pcr_reagent_2',
                'pcr_reagent_lot_no_2',
                'pcr_reagent_exp_date_2',
                'lot_no_2',
                'exp_date_2',
                'test_result_2',
                'test_type_3',
                'name_of_pcr_reagent_3',
                'pcr_reagent_lot_no_3',
                'pcr_reagent_exp_date_3',
                'lot_no_3',
                'exp_date_3',
                'test_result_3',
                'reported_result',
            ))
            ->where('sp.shipment_id = ? ', $sId)
            ->where('sp.participant_id = ? ', $pId);
        return $db->fetchAll($sql);
    }

    public function getGenericSamples($sId, $pId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('ref' => 'reference_result_generic_test'))
            ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
            ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
            ->joinLeft(array('res' => 'response_result_generic_test'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array(
                'shipment_map_id',
                'result',
                'repeat_result',
                'reported_result',
                'additional_detail',
                'comments',
                'calculated_score'
            ))
            ->where('sp.shipment_id = ? ', $sId)
            ->where('sp.participant_id = ? ', $pId);
        // die($sql);
        return $db->fetchAll($sql);
    }

    public function getDtsReferenceData($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('reference_result_dts'))
            ->where('shipment_id = ? ', $shipmentId);
        return $db->fetchAll($sql);
    }

    public function getCovid19ReferenceData($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('reference_result_covid19'))
            ->where('shipment_id = ? ', $shipmentId);
        return $db->fetchAll($sql);
    }
    public function getEidReferenceData($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('reference_result_eid'))
            ->where('shipment_id = ? ', $shipmentId);
        return $db->fetchAll($sql);
    }

    public function getVlReferenceData($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('reference_result_vl'))
            ->where('shipment_id = ? ', $shipmentId);
        return $db->fetchAll($sql);
    }

    public function getRecencyReferenceData($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('reference_result_recency'))
            ->where('shipment_id = ? ', $shipmentId);
        return $db->fetchAll($sql);
    }

    public function getDbsSamples($sId, $pId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('ref' => 'reference_result_dbs'))
            ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
            ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
            ->joinLeft(array('res' => 'response_result_dbs'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array(
                'eia_1',
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
                'reported_result',
            ))
            ->where('sp.shipment_id = ? ', $sId)
            ->where('sp.participant_id = ? ', $pId);

        return $db->fetchAll($sql);
    }

    public function getEidSamples($sId, $pId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('ref' => 'reference_result_eid'))
            ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
            ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
            ->joinLeft(array('res' => 'response_result_eid'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('reported_result', 'hiv_ct_od', 'ic_qs'))
            ->where('sp.shipment_id = ? ', $sId)
            ->where('sp.participant_id = ? ', $pId);
        return $db->fetchAll($sql);
    }

    public function getRecencySamples($sId, $pId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('ref' => 'reference_result_recency'))
            ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
            ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
            ->joinLeft(array('res' => 'response_result_recency'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('reported_result', 'control_line', 'diagnosis_line', 'longterm_line'))
            ->where('sp.shipment_id = ? ', $sId)
            ->where('sp.participant_id = ? ', $pId);
        return $db->fetchAll($sql);
    }

    public function getVlSamples($sId, $pId, $withoutControls = true)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('ref' => 'reference_result_vl'))
            ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
            ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
            ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('unique_identifier'))
            ->joinLeft(array('res' => 'response_result_vl'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('reported_viral_load', 'is_tnd', 'responseDate' => 'res.created_on', 'is_result_invalid', 'error_code', 'module_number', 'comment', 'z_score', 'calculated_score'))
            ->where('sp.shipment_id = ? ', $sId)
            ->where('sp.participant_id = ? ', $pId);
        if ($withoutControls) {
            $sql = $sql->where("ref.control = 0");
        }
        return $db->fetchAll($sql);
    }

    public function getTBSamples($sId, $pId, $withoutControls = true)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(
            ['ref' => 'reference_result_tb'],
            [
                'refMtb' => new Zend_Db_Expr("CASE
                                            WHEN ref.mtb_detected = 'detected' THEN 'Detected'
                                            WHEN ref.mtb_detected = 'not-detected' THEN 'Not Detected'
                                            WHEN ref.mtb_detected = 'noResult' THEN 'No Result'
                                            WHEN ref.mtb_detected = 'na' THEN 'N/A'
                                            WHEN IFNULL(ref.mtb_detected, '') = '' THEN NULL
                                            ELSE UPPER(ref.mtb_detected)
                                        END"),
                'refRif' => new Zend_Db_EXPR("CASE
                                WHEN ref.rif_resistance = 'na' THEN 'N/A'
                                WHEN ref.rif_resistance = 'detected' THEN 'Detected'
                                WHEN ref.rif_resistance = 'not-detected' THEN 'Not Detected'
                                WHEN ref.rif_resistance = 'indeterminate' THEN 'Indeterminate'
                                WHEN ref.rif_resistance = 'testing-not-performed' THEN 'Testing Not Performed'
                                WHEN IFNULL(ref.rif_resistance, '') = '' THEN 'N/A'
                                ELSE UPPER(ref.rif_resistance)
                            END"),
                'control',
                'mandatory',
                'sample_score'
            ]
        )
            ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
            ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
            ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('unique_identifier'))
            ->joinLeft(array('res' => 'response_result_tb'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('resMtb' => 'mtb_detected', 'resRif' => 'rif_resistance', 'responseDate' => 'res.created_on', 'calculated_score'))
            ->where('sp.shipment_id = ? ', $sId)
            ->where('sp.participant_id = ? ', $pId);
        if ($withoutControls) {
            $sql = $sql->where("ref.control = 0");
        }
        return $db->fetchAll($sql);
    }

    public function getVlRange($sId, $sampleId = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['rvc' => 'reference_vl_calculation'])
            ->join(['ref' => 'reference_result_vl'], 'rvc.sample_id = ref.sample_id', ['sample_label'])
            ->join(['a' => 'r_vl_assay'], 'a.id = rvc.vl_assay', ['assay_name' => 'name'])
            ->where('rvc.shipment_id = ?', $sId);

        if ($sampleId != null) {
            $sql = $sql->where('rvc.sample_id = ?', $sampleId);
        }

        $res = $db->fetchAll($sql);
        $response = [];
        foreach ($res as $row) {

            $assay = $row['vl_assay'];
            $sampleId = $row['sample_id'];

            $response[$assay][$sampleId]['sample_id'] = $row['sample_id'];
            $response[$assay][$sampleId]['vl_assay'] = $row['vl_assay'];
            $response[$assay][$sampleId]['no_of_responses'] = $row['no_of_responses'];
            $response[$assay][$sampleId]['assay_name'] = $row['assay_name'];
            $response[$assay][$sampleId]['sample_label'] = $row['sample_label'];
            $response[$assay][$sampleId]['use_range'] = $row['use_range'] ?? 'calculated';

            if (!empty($row['use_range']) && $row['use_range'] == 'manual') {
                $response[$assay][$sampleId]['q1'] = $row['manual_q1'];
                $response[$assay][$sampleId]['q3'] = $row['manual_q3'];
                $response[$assay][$sampleId]['quartile_low'] = $row['manual_quartile_low'];
                $response[$assay][$sampleId]['quartile_high'] = $row['manual_quartile_high'];
                $response[$assay][$sampleId]['low'] = $row['manual_low_limit'];
                $response[$assay][$sampleId]['high'] = $row['manual_high_limit'];
                $response[$assay][$sampleId]['mean'] = $row['manual_mean'];
                $response[$assay][$sampleId]['median'] = $row['manual_median'];
                $response[$assay][$sampleId]['sd'] = $row['manual_sd'];
                $response[$assay][$sampleId]['standard_uncertainty'] = $row['manual_standard_uncertainty'];
                $response[$assay][$sampleId]['is_uncertainty_acceptable'] = $row['manual_is_uncertainty_acceptable'];
            } else {
                $response[$assay][$sampleId]['q1'] = $row['q1'];
                $response[$assay][$sampleId]['q3'] = $row['q3'];
                $response[$assay][$sampleId]['quartile_low'] = $row['quartile_low'];
                $response[$assay][$sampleId]['quartile_high'] = $row['quartile_high'];
                $response[$assay][$sampleId]['low'] = $row['low_limit'];
                $response[$assay][$sampleId]['high'] = $row['high_limit'];
                $response[$assay][$sampleId]['mean'] = $row['mean'];
                $response[$assay][$sampleId]['median'] = $row['median'];
                $response[$assay][$sampleId]['sd'] = $row['sd'];
                $response[$assay][$sampleId]['standard_uncertainty'] = $row['standard_uncertainty'];
                $response[$assay][$sampleId]['is_uncertainty_acceptable'] = $row['is_uncertainty_acceptable'];
            }
        }
        return $response;
    }

    public function getVlRangeInformation($sId, $sampleId = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['rvc' => 'reference_vl_calculation'], ['*'])
            ->join(['ref' => 'reference_result_vl'], 'rvc.sample_id = ref.sample_id AND ref.shipment_id=' . $sId, ['sample_label'])
            ->joinLeft(['a' => 'r_vl_assay'], 'a.id = rvc.vl_assay', ['assay_name' => 'name'])
            ->join(['s' => 'shipment'], 'rvc.shipment_id = s.shipment_id')
            ->where('rvc.shipment_id = ?', $sId)
            ->order(['sample_label', 'assay_name']);

        if (!empty($sampleId)) {
            $sql = $sql->where('rvc.sample_id = ?', $sampleId);
        }

        $res = $db->fetchAll($sql);

        // if no data found, then it means we do not have enough responses to calculate
        // get the data from r_vl_assay table and show blank or 0 values for all fields
        if (empty($res)) {
            $sql = $db->select()->from(['a' => 'r_vl_assay'], ['assay_name' => 'name', 'vl_assay' => 'id'])
                ->joinLeft(['s' => 'shipment'], "s.shipment_id = $sId")
                ->join(['ref' => 'reference_result_vl'], "ref.shipment_id= $sId", ['sample_label', 'sample_id'])
                ->order(['sample_label', 'assay_name']);

            $res = $db->fetchAll($sql);
        }

        $shipmentAttributes = !empty($res[0]['shipment_attributes']) ? json_decode($res[0]['shipment_attributes'], true) : null;
        $methodOfEvaluation = $shipmentAttributes['methodOfEvaluation'] ?? 'standard';


        $response = [];

        $response['method_of_evaluation'] = $methodOfEvaluation;

        foreach ($res as $row) {

            $response[$row['sample_id']][$row['vl_assay']]['shipment_id'] = $row['shipment_id'] ?? null;
            $response[$row['sample_id']][$row['vl_assay']]['sample_label'] = $row['sample_label'] ?? null;
            $response[$row['sample_id']][$row['vl_assay']]['sample_id'] = $row['sample_id'] ?? null;
            $response[$row['sample_id']][$row['vl_assay']]['vl_assay'] = $row['vl_assay'] ?? null;
            $response[$row['sample_id']][$row['vl_assay']]['assay_name'] = $row['assay_name'] ?? null;
            $response[$row['sample_id']][$row['vl_assay']]['low'] = $row['low_limit'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['high'] = $row['high_limit'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['mean'] = $row['mean'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['median'] = $row['median'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['sd'] = $row['sd'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['standard_uncertainty'] = $row['standard_uncertainty'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['is_uncertainty_acceptable'] = $row['is_uncertainty_acceptable'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['manual_mean'] = $row['manual_mean'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['manual_median'] = $row['manual_median'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['manual_sd'] = $row['manual_sd'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['manual_low_limit'] = $row['manual_low_limit'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['manual_high_limit'] = $row['manual_high_limit'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['use_range'] = $row['use_range'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['method_of_evaluation'] = $methodOfEvaluation;

            if (!isset($response['updated_on'])) {
                $response['updated_on'] = $row['updated_on'] ?? null;
            }
            if (!isset($response['calculated_on'])) {
                $response['calculated_on'] = $row['calculated_on'] ?? null;
            }
        }

        return $response;
    }

    public function updateVlInformation($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        foreach ($params['sampleId'] as $assayId => $samples) {
            foreach ($samples as $sampid) {
                //$data['manual_low_limit'] = $params['manualLow'][$assayId][$sampid];
                //$data['manual_high_limit'] = $params['manualHigh'][$assayId][$sampid];
                $data['use_range'] = $params['useRange'][$assayId][$sampid];
                $data['updated_on'] = new Zend_Db_Expr('now()');
                //echo "shipment_id = ".base64_decode($params['sid'])." and sample_id = " . $sampid . " and "." vl_assay = " . $assayId ;
                $db->update('reference_vl_calculation', $data, "shipment_id = " . base64_decode($params['sid']) . " and sample_id = " . $sampid . " and " . " vl_assay = " . $assayId);
            }
        }
    }


    public function setVlRange($sId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['s' => 'shipment'])
            ->where('shipment_id = ? ', $sId);
        $shipment = $db->fetchRow($sql);


        $beforeSetVlRangeData = $db->fetchAll($db->select()->from('reference_vl_calculation', ['*'])
            ->where("shipment_id = $sId"));
        $oldSetVlRange = [];
        foreach ($beforeSetVlRangeData as $beforeSetVlRangeRow) {
            $oldSetVlRange[$beforeSetVlRangeRow['vl_assay']][$beforeSetVlRangeRow['sample_id']] = $beforeSetVlRangeRow;
        }


        $shipmentAttributes = json_decode($shipment['shipment_attributes'], true);

        $method = isset($shipmentAttributes['methodOfEvaluation']) ? $shipmentAttributes['methodOfEvaluation'] : 'standard';

        $db->delete('reference_vl_calculation', "use_range IS NOT NULL and use_range not like 'manual' AND shipment_id=$sId");

        $sql = $db->select()->from(['ref' => 'reference_result_vl'], ['shipment_id', 'sample_id'])
            ->join(['s' => 'shipment'], 's.shipment_id=ref.shipment_id', [])
            ->join(['sp' => 'shipment_participant_map'], 's.shipment_id=sp.shipment_id', ['participant_id', 'assay' => new Zend_Db_Expr('sp.attributes->>"$.vl_assay"')])
            ->joinLeft(['res' => 'response_result_vl'], 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', ['reported_viral_load', 'z_score', 'is_result_invalid'])
            ->where('sp.shipment_id = ? ', $sId)
            ->where('DATE(sp.shipment_test_report_date) <= s.lastdate_response')
            //->where("(sp.is_excluded LIKE 'yes') IS NOT TRUE")
            ->where("(sp.is_pt_test_not_performed LIKE 'yes') IS NOT TRUE");

        $response = $db->fetchAll($sql);

        $sampleWise = [];
        foreach ($response as $row) {
            $invalidValues = ['invalid', 'error'];

            if (!empty($row['is_result_invalid']) && in_array($row['is_result_invalid'], $invalidValues)) {
                $row['reported_viral_load'] = null;
            }

            $sampleWise[$row['assay']][$row['sample_id']][] = $row['reported_viral_load'];
        }


        $vlAssayArray = $this->getVlAssay();

        $skippedAssays = [];
        $skippedAssays[] = 6; // adding "Others" to skippedAssays as it will always be skipped

        $responseCounter = [];


        foreach ($vlAssayArray as $vlAssayId => $vlAssayName) {


            if (!isset($sampleWise[$vlAssayId])) {
                continue;
            }

            if ('standard' == $method) {
                $minimumRequiredSamples = 6;
            } elseif ('iso17043' == $method) {
                $minimumRequiredSamples = 18;
            }

            // IMPORTANT: If the reported samples for an Assay are < $minimumRequiredSamples
            // then we use the ranges of the Assay with maximum responses

            foreach ($sampleWise[$vlAssayId] as $sample => $reportedVl) {

                if ($vlAssayId != 6  && !empty($reportedVl) && count($reportedVl) > $minimumRequiredSamples) {
                    $responseCounter[$vlAssayId] = count($reportedVl);

                    $inputArray = $reportedVl;

                    $finalHigh = null;
                    $finalLow = null;
                    $quartileHighLimit = null;
                    $quartileLowLimit = null;
                    $iqr = null;
                    $cv = null;
                    $finalLow = null;
                    $finalHigh = null;
                    $avg = null;
                    $median = null;
                    $standardUncertainty = null;
                    $isUncertaintyAcceptable = null;
                    $q1 = $q3 = 0;

                    // removing all null values
                    $inputArray = array_filter(
                        $inputArray,
                        function ($value) {
                            return !is_null($value);
                        }
                    );

                    if ('standard' == $method) {
                        sort($inputArray);
                        $q1 = QuantitativeCalculations::calculateQuantile($inputArray, 0.25);
                        $q3 = QuantitativeCalculations::calculateQuantile($inputArray, 0.75);
                        $iqr = $q3 - $q1;
                        $iqrMultiplier = $iqr * 1.5;
                        $quartileLowLimit = $q1 - $iqrMultiplier;
                        $quartileHighLimit = $q3 + $iqrMultiplier;

                        $newDataSet = [];
                        $removeArray = [];
                        foreach ($inputArray as $a) {
                            if ($a >= round($quartileLowLimit, 2) && $a <= round($quartileHighLimit, 2)) {
                                $newDataSet[] = $a;
                            } else {
                                $removeArray[] = $a;
                            }
                        }

                        //Zend_Debug::dump("Under Assay $vlAssayId-Sample $sample - COUNT AFTER REMOVING OUTLIERS: ".count($newArray) . " FOLLOWING ARE OUTLIERS");
                        //Zend_Debug::dump($removeArray);

                        $avg = QuantitativeCalculations::calculateMean($newDataSet);
                        $sd = QuantitativeCalculations::calculateStandardDeviation($newDataSet);

                        $cv = QuantitativeCalculations::calculateCoefficientOfVariation($newDataSet, $avg, $sd);
                        $threeTimesSd = $sd * 3;
                        $finalLow = $avg - $threeTimesSd;
                        $finalHigh = $avg + $threeTimesSd;
                    } elseif ('iso17043' == $method) {
                        sort($inputArray);
                        $median = QuantitativeCalculations::calculateMedian($inputArray);
                        $finalLow = $quartileLowLimit = $q1 = QuantitativeCalculations::calculateQuantile($inputArray, 0.25);
                        $finalHigh = $quartileHighLimit = $q3 = QuantitativeCalculations::calculateQuantile($inputArray, 0.75);
                        $iqr = $q3 - $q1;
                        $sd = 0.7413 * $iqr;
                        if (!empty($inputArray)) {
                            $standardUncertainty = (1.25 * $sd) / sqrt(count($inputArray));
                        }
                        if ($median == 0) {
                            $isUncertaintyAcceptable = 'NA';
                        } elseif ($standardUncertainty < (0.3 * $sd)) {
                            $isUncertaintyAcceptable = 'yes';
                        } else {
                            $isUncertaintyAcceptable = 'no';
                        }
                    }


                    $data = [
                        'shipment_id' => $sId,
                        'vl_assay' => $vlAssayId,
                        'no_of_responses' => count($inputArray),
                        'sample_id' => $sample,
                        'q1' => $q1,
                        'q3' => $q3,
                        'iqr' => $iqr ?? 0,
                        'quartile_low' => $quartileLowLimit,
                        'quartile_high' => $quartileHighLimit,
                        'mean' => $avg ?? 0,
                        'median' => $median ?? 0,
                        'sd' => $sd ?? 0,
                        'standard_uncertainty' => $standardUncertainty ?? 0,
                        'is_uncertainty_acceptable' => $isUncertaintyAcceptable ?? 'NA',
                        'cv' => $cv ?? 0,
                        'low_limit' => $finalLow,
                        'high_limit' => $finalHigh,
                        'calculated_on' => new Zend_Db_Expr('now()'),
                    ];

                    if (isset($oldSetVlRange[$vlAssayId][$sample]) && !empty($oldSetVlRange[$vlAssayId][$sample]) && $oldSetVlRange[$vlAssayId][$sample]['use_range'] == 'manual') {
                        $data['manual_q1'] = $oldSetVlRange[$vlAssayId][$sample]['manual_q1'] ?? null;
                        $data['manual_q3'] = $oldSetVlRange[$vlAssayId][$sample]['manual_q3'] ?? null;
                        $data['manual_cv'] = $oldSetVlRange[$vlAssayId][$sample]['manual_cv'] ?? null;
                        $data['manual_iqr'] = $oldSetVlRange[$vlAssayId][$sample]['manual_iqr'] ?? null;
                        $data['manual_quartile_high'] = $oldSetVlRange[$vlAssayId][$sample]['manual_quartile_high'] ?? null;
                        $data['manual_quartile_low'] = $oldSetVlRange[$vlAssayId][$sample]['manual_quartile_low'] ?? null;
                        $data['manual_low_limit'] = $oldSetVlRange[$vlAssayId][$sample]['manual_low_limit'] ?? null;
                        $data['manual_high_limit'] = $oldSetVlRange[$vlAssayId][$sample]['manual_high_limit'] ?? null;
                        $data['manual_mean'] = $oldSetVlRange[$vlAssayId][$sample]['manual_mean'] ?? null;
                        $data['manual_median'] = $oldSetVlRange[$vlAssayId][$sample]['manual_median'] ?? null;
                        $data['manual_sd'] = $oldSetVlRange[$vlAssayId][$sample]['manual_sd'] ?? null;
                        $data['manual_standard_uncertainty'] = $oldSetVlRange[$vlAssayId][$sample]['manual_standard_uncertainty'] ?? null;
                        $data['manual_is_uncertainty_acceptable'] = $oldSetVlRange[$vlAssayId][$sample]['manual_is_uncertainty_acceptable'] ?? null;
                        $data['updated_on'] = $oldSetVlRange[$vlAssayId][$sample]['updated_on'] ?? null;
                        $data['use_range'] = $oldSetVlRange[$vlAssayId][$sample]['use_range'] ?? 'calculated';
                    }

                    $db->delete('reference_vl_calculation', "vl_assay = $vlAssayId AND sample_id=$sample AND shipment_id=$sId");

                    $db->insert('reference_vl_calculation', $data);
                } else {

                    if (isset($oldSetVlRange[$vlAssayId][$sample]) && !empty($oldSetVlRange[$vlAssayId][$sample]) && $oldSetVlRange[$vlAssayId][$sample]['use_range'] != 'manual') {
                        $db->delete('reference_vl_calculation', "vl_assay = $vlAssayId AND shipment_id = $sId");
                    }

                    $skippedAssays[] = $vlAssayId;
                    $skippedResponseCounter[$vlAssayId] = count($reportedVl);
                }
            }
        }

        // Okay now we are going to take the assay with maximum responses and use its range for assays having < $minimumRequiredSamples

        $skippedAssays = array_unique($skippedAssays);
        arsort($responseCounter);
        reset($responseCounter);
        $maxAssay = key($responseCounter);

        $sql = $db->select()->from(['rvc' => 'reference_vl_calculation'])
            // ->where('rvc.vl_assay = ?', $maxAssay)
            ->where('rvc.shipment_id = ?', $sId);

        if (isset($maxAssay) && $maxAssay != "") {
            $sql->where('rvc.vl_assay = ?', $maxAssay);
        }
        $res = $db->fetchAll($sql);

        foreach ($skippedAssays as $vlAssayId) {
            foreach ($res as $row) {

                $sample = $row['sample_id'];
                $row['vl_assay'] = $vlAssayId;
                $row['no_of_responses'] = $skippedResponseCounter[$vlAssayId];

                // if there are no responses then continue
                // (this is especially put to check and remove vl assay = 6 if no one used "Others")
                // Why? because we manually inserted "6" into skippedAssays at the top of this function
                if (empty($row['no_of_responses'])) {
                    continue;
                }

                if (isset($oldSetVlRange[$vlAssayId][$sample]) && !empty($oldSetVlRange[$vlAssayId][$sample]) && $oldSetVlRange[$vlAssayId][$sample]['use_range'] == 'manual') {
                    $row['manual_q1'] = $oldSetVlRange[$vlAssayId][$sample]['manual_q1'] ?? null;
                    $row['manual_q3'] = $oldSetVlRange[$vlAssayId][$sample]['manual_q3'] ?? null;
                    $row['manual_cv'] = $oldSetVlRange[$vlAssayId][$sample]['manual_cv'] ?? null;
                    $row['manual_iqr'] = $oldSetVlRange[$vlAssayId][$sample]['manual_iqr'] ?? null;
                    $row['manual_quartile_high'] = $oldSetVlRange[$vlAssayId][$sample]['manual_quartile_high'] ?? null;
                    $row['manual_quartile_low'] = $oldSetVlRange[$vlAssayId][$sample]['manual_quartile_low'] ?? null;
                    $row['manual_low_limit'] = $oldSetVlRange[$vlAssayId][$sample]['manual_low_limit'] ?? null;
                    $row['manual_high_limit'] = $oldSetVlRange[$vlAssayId][$sample]['manual_high_limit'] ?? null;
                    $row['manual_mean'] = $oldSetVlRange[$vlAssayId][$sample]['manual_mean'] ?? null;
                    $row['manual_median'] = $oldSetVlRange[$vlAssayId][$sample]['manual_median'] ?? null;
                    $row['manual_sd'] = $oldSetVlRange[$vlAssayId][$sample]['manual_sd'] ?? null;
                    $row['manual_standard_uncertainty'] = $oldSetVlRange[$vlAssayId][$sample]['manual_standard_uncertainty'] ?? null;
                    $row['manual_is_uncertainty_acceptable'] = $oldSetVlRange[$vlAssayId][$sample]['manual_is_uncertainty_acceptable'] ?? null;
                    $row['updated_on'] = $oldSetVlRange[$vlAssayId][$sample]['updated_on'] ?? null;
                    $row['use_range'] = $oldSetVlRange[$vlAssayId][$sample]['use_range'] ?? 'calculated';
                }

                $db->delete('reference_vl_calculation', "vl_assay = " . $row['vl_assay'] . " AND sample_id= " . $row['sample_id'] . " AND shipment_id=  " . $row['shipment_id']);
                $db->insert('reference_vl_calculation', $row);
            }
        }
    }

    public function getShipmentData($sId, $pId)
    {

        $db = new Application_Model_DbTable_Shipments();
        return $db->getShipmentData($sId, $pId);
    }

    //public function getShipmentVl($sId,$pId){
    //
    //    $db = new Application_Model_DbTable_ShipmentVl();
    //    return $db->getShipmentVl($sId,$pId);
    //
    //}

    public function getSchemeControls($schemeId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('r_control')
            ->where("for_scheme='$schemeId'"));
    }

    public function getSchemeEvaluationComments($schemeId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('r_evaluation_comments')->where("scheme='$schemeId'"));
    }

    public function getPossibleResults($schemeId, $context = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from('r_possibleresult')
            ->where("scheme_id='$schemeId'")
            ->order('sort_order ASC');
        if (isset($context) && !empty($context)) {
            $sql = $sql->where("display_context IN ('all', '$context')");
        }
        return $db->fetchAll($sql);
    }

    public function countEnrollmentSchemes()
    {
        $schemeListDb = new Application_Model_DbTable_SchemeList();
        return $schemeListDb->countEnrollmentSchemes();
    }
    public function getScheme($sid)
    {
        if ($sid != null) {
            $schemeListDb = new Application_Model_DbTable_SchemeList();
            return $schemeListDb->fetchRow($schemeListDb->select()->where("scheme_id = ?", $sid));
        } else {
            return null;
        }
    }

    public function addTestkit($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $testkitsDb = new Application_Model_DbTable_Testkitnames();
            $testkitsDb->addTestkitDetails($params);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log($e->getMessage());
        }
    }

    public function updateTestkit($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $testkitsDb = new Application_Model_DbTable_Testkitnames();
            $testkitsDb->updateTestkitDetails($params);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log($e->getMessage());
        }
    }
    public function addTestType($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $testPlatformsDb = new Application_Model_DbTable_TestTypenameCovid19();
            $testPlatformsDb->addTestTypeDetails($params);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log($e->getMessage());
        }
    }

    public function updateTestType($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $testPlatformsDb = new Application_Model_DbTable_TestTypenameCovid19();
            $testPlatformsDb->updateTestTypeDetails($params);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log($e->getMessage());
        }
    }

    public function updateTestkitStage($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $testkitsDb = new Application_Model_DbTable_Testkitnames();
            $testkitsDb->updateTestkitStageDetails($params);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log($e->getMessage());
        }
    }

    public function updateTestTypeStage($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $testPlatformsDb = new Application_Model_DbTable_TestTypenameCovid19();
            $testPlatformsDb->updateTestTypeStageDetails($params);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log($e->getMessage());
        }
    }

    public function getAllDtsTestKitInGrid($parameters)
    {
        $testkitsDb = new Application_Model_DbTable_Testkitnames();
        return $testkitsDb->getAllTestKitsForAllSchemes($parameters);
    }

    public function getAllGenericTestInGrid($parameters)
    {
        $schemeDb = new Application_Model_DbTable_SchemeList();
        return $schemeDb->fetchAllGenericTestInGrid($parameters);
    }

    public function getGenericSchemeLists()
    {
        $schemeDb = new Application_Model_DbTable_SchemeList();
        return $schemeDb->fetchGenericSchemeLists();
    }

    public function getGenericTest($id)
    {
        $schemeDb = new Application_Model_DbTable_SchemeList();
        return $schemeDb->fetchGenericTest($id);
    }

    public function getAllCovid19TestTypeInGrid($parameters)
    {
        $testPlatformsDb = new Application_Model_DbTable_TestTypenameCovid19();
        return $testPlatformsDb->getAllTestTypesForAllSchemes($parameters);
    }

    public function getDtsTestkit($testkitId)
    {
        $testkitsDb = new Application_Model_DbTable_Testkitnames();
        return $testkitsDb->getDtsTestkitDetails($testkitId);
    }

    public function getCovid19TestType($testtypeId)
    {
        $testPlatformsDb = new Application_Model_DbTable_TestTypenameCovid19();
        return $testPlatformsDb->getCovid19TestTypeDetails($testtypeId);
    }

    public function getVlManualValue($shipmentId, $sampleId, $vlAssay)
    {
        if (trim($shipmentId) != "" && trim($sampleId) != "" && trim($vlAssay) != "") {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sql = $db->select()->from(['rvc' => 'reference_vl_calculation'], ['shipment_id', 'sample_id', 'low_limit', 'high_limit', 'vl_assay', 'manual_q1', 'manual_q3', 'manual_iqr', 'manual_quartile_low', 'manual_quartile_high', 'manual_mean', 'manual_sd', 'manual_cv', 'manual_high_limit', 'manual_low_limit', 'manual_standard_uncertainty', 'manual_is_uncertainty_acceptable', 'manual_median', 'use_range'])
                ->join(['ref' => 'reference_result_vl'], 'rvc.sample_id = ref.sample_id AND ref.shipment_id=' . $shipmentId, ['sample_label'])
                ->join(['a' => 'r_vl_assay'], 'a.id = rvc.vl_assay', ['assay_name' => 'name'])
                ->join(['s' => 'shipment'], 'rvc.shipment_id = s.shipment_id')
                ->where('rvc.shipment_id = ?', $shipmentId)
                ->where('rvc.sample_id = ?', $sampleId)
                ->where('rvc.vl_assay = ?', $vlAssay);
            return $db->fetchRow($sql);
        }
    }

    public function updateVlManualValue($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $shipmentId = base64_decode($params['shipmentId']);
            $sampleId = base64_decode($params['sampleId']);
            $vlAssay = base64_decode($params['vlAssay']);
            if (trim($shipmentId) != "" && trim($sampleId) != "" && trim($vlAssay) != "") {
                $data['manual_q1'] = !empty($params['manualQ1']) ? $params['manualQ1'] : null;
                $data['manual_q3'] = !empty($params['manualQ3']) ? $params['manualQ3'] : null;
                $data['manual_iqr'] = !empty($params['manualIqr']) ? $params['manualIqr'] : null;
                $data['manual_quartile_low'] = !empty($params['manualQuartileLow']) ? $params['manualQuartileLow'] : null;
                $data['manual_quartile_high'] = !empty($params['manualQuartileHigh']) ? $params['manualQuartileHigh'] : null;
                $data['low_limit'] = !empty($params['lowLimit']) ? $params['lowLimit'] : null;
                $data['high_limit'] = !empty($params['highLimit']) ? $params['highLimit'] : null;
                $data['manual_mean'] = !empty($params['manualMean']) ? $params['manualMean'] : null;
                $data['manual_median'] = !empty($params['manualMedian']) ? $params['manualMedian'] : null;
                $data['manual_sd'] = !empty($params['manualSd']) ? $params['manualSd'] : null;
                $data['manual_standard_uncertainty'] = !empty($params['manualStandardUncertainty']) ? $params['manualStandardUncertainty'] : null;
                $data['manual_is_uncertainty_acceptable'] = !empty($params['manualIsUncertaintyAcceptable']) ? $params['manualIsUncertaintyAcceptable'] : null;
                $data['manual_cv'] = !empty($params['manualCv']) ? $params['manualCv'] : null;
                $data['manual_low_limit'] = !empty($params['manualLowLimit']) ? $params['manualLowLimit'] : null;
                $data['manual_high_limit'] = !empty($params['manualHighLimit']) ? $params['manualHighLimit'] : null;
                $db->update('reference_vl_calculation', $data, "shipment_id = " . $shipmentId . " and sample_id = " . $sampleId . " and " . " vl_assay = " . $vlAssay);
                $db->commit();
                return $params['shipmentId'];
            }
        } catch (Exception $e) {
            $db->rollBack();
            error_log($e->getMessage());
        }
    }

    public function getNotTestedReasons($testType = "")
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['r_response_not_tested_reasons'])
            ->where('ntr_status = ? ', 'active');
        if (isset($testType) && $testType != "") {
            $sql = $sql->where("JSON_SEARCH(`ntr_test_type`, 'all', '$testType') IS NOT NULL");
        }
        return $db->fetchAll($sql);
    }

    public function getVlNotTestedReasons()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('r_response_vl_not_tested_reason'))
            ->where('status = ? ', 'active');
        return $db->fetchAll($sql);
    }

    public function getCovid19NotTestedReasons()
    {
        return $this->getNotTestedReasons('covid19');
    }

    public function getAllCovid19GeneTypeInGrid($parameters)
    {
        $geneTypesDb = new Application_Model_DbTable_RCovid19GeneTypes();
        return $geneTypesDb->fetchAllCovid19GeneTypeInGrid($parameters);
    }

    public function addGeneType($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $geneTypesDb = new Application_Model_DbTable_RCovid19GeneTypes();
            $geneTypesDb->addGeneTypeDetails($params);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log($e->getMessage());
        }
    }

    public function saveGenericTest($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $geneTypesDb = new Application_Model_DbTable_SchemeList();
            $geneTypesDb->saveGenericTestDetails($params);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log($e->getMessage());
        }
    }

    public function updateCovid19GeneType($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $geneTypesDb = new Application_Model_DbTable_RCovid19GeneTypes();
            $geneTypesDb->updateCovid19GeneType($params);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log($e->getMessage());
        }
    }

    public function getCovid19GeneType($testtypeId)
    {
        $geneTypesDb = new Application_Model_DbTable_RCovid19GeneTypes();
        return $geneTypesDb->getCovid19GeneTypeDetails($testtypeId);
    }

    public function getAllCovid19GeneTypeList()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('r_covid19_gene_types'), array('gene_id', 'gene_name'))
            ->where("scheme_type = 'covid19'")
            ->order('gene_name');
        return $db->fetchAll($sql);
    }

    public function getAllCovid19GeneTypeResponseWise()
    {
        $geneTypesDb = new Application_Model_DbTable_RCovid19GeneTypes();
        return $geneTypesDb->fetchAllCovid19GeneTypeResponseWise('covid19');
    }

    public function getAllCovid19IdentifiedGeneTypeResponseWise($mapId)
    {
        $geneIdentifiedTypesDb = new Application_Model_DbTable_Covid19IdentifiedGenes();
        return $geneIdentifiedTypesDb->getAllCovid19IdentifiedGeneTypeResponseWise($mapId);
    }

    public function getAllSampleNotTeastedReasonsInGrid($parameters)
    {
        $db = new Application_Model_DbTable_ResponseNotTestedReasons();
        return $db->fetchAllSampleNotTeastedReasonsInGrid($parameters);
    }

    public function saveNotTestedReasons($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $sessionAlert = new Zend_Session_Namespace('alertSpace');
            $ntrDb = new Application_Model_DbTable_ResponseNotTestedReasons();
            $status = $ntrDb->saveNotTestedReasonsDetails($params);
            if ($status) {
                $sessionAlert->message = "Saved Successfully";
                $sessionAlert->status = "success";
                $db->commit();
            } else {
                $sessionAlert->message = "Something went wrong. Please try again later.";
                $sessionAlert->status = "failure";
                $db->rollBack();
            }
        } catch (Exception $e) {
            $db->rollBack();
            error_log($e->getMessage());
        }
    }

    public function getNotTestedReasonById($id)
    {
        $ntrDb = new Application_Model_DbTable_ResponseNotTestedReasons();
        return $ntrDb->fetchNotTestedReasonById($id);
    }
}
