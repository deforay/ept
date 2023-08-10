<?php

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

        $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
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

    public function getVlAssay()
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $res = $db->fetchAll($db->select()->from('r_vl_assay')->where("`status` like 'active'"));
        $response = [];
        foreach ($res as $row) {
            $response[$row['id']] = $row['name'];
        }
        return $response;
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
            ->joinLeft(array('res' => 'response_result_vl'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('reported_viral_load', 'is_tnd', 'responseDate' => 'res.created_on', 'z_score', 'calculated_score'))
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
        $sql = $db->select()->from(array('rvc' => 'reference_vl_calculation'))
            ->join(array('ref' => 'reference_result_vl'), 'rvc.sample_id = ref.sample_id', array('sample_label'))
            ->join(array('a' => 'r_vl_assay'), 'a.id = rvc.vl_assay', array('assay_name' => 'name'))
            ->where('rvc.shipment_id = ?', $sId);
        if ($sampleId != null) {
            $sql = $sql->where('rvc.sample_id = ?', $sampleId);
        }
        $res = $db->fetchAll($sql);
        $response = [];
        foreach ($res as $row) {
            $response[$row['vl_assay']][$row['sample_id']]['sample_id'] = $row['sample_id'];
            $response[$row['vl_assay']][$row['sample_id']]['vl_assay'] = $row['vl_assay'];

            if (isset($row['use_range']) && $row['use_range'] != "") {
                if ($row['use_range'] == 'manual') {
                    $response[$row['vl_assay']][$row['sample_id']]['q1'] = $row['manual_q1'];
                    $response[$row['vl_assay']][$row['sample_id']]['q3'] = $row['manual_q3'];
                    $response[$row['vl_assay']][$row['sample_id']]['quartile_low'] = $row['manual_quartile_low'];
                    $response[$row['vl_assay']][$row['sample_id']]['quartile_high'] = $row['manual_quartile_high'];
                    $response[$row['vl_assay']][$row['sample_id']]['low'] = $row['manual_low_limit'];
                    $response[$row['vl_assay']][$row['sample_id']]['high'] = $row['manual_high_limit'];
                    $response[$row['vl_assay']][$row['sample_id']]['mean'] = $row['manual_mean'];
                    $response[$row['vl_assay']][$row['sample_id']]['median'] = $row['manual_median'];
                    $response[$row['vl_assay']][$row['sample_id']]['sd'] = $row['manual_sd'];
                    $response[$row['vl_assay']][$row['sample_id']]['standard_uncertainty'] = $row['manual_standard_uncertainty'];
                    $response[$row['vl_assay']][$row['sample_id']]['is_uncertainty_acceptable'] = $row['manual_is_uncertainty_acceptable'];
                    $response[$row['vl_assay']][$row['sample_id']]['assay_name'] = $row['assay_name'];
                    $response[$row['vl_assay']][$row['sample_id']]['sample_label'] = $row['sample_label'];
                } else {
                    $response[$row['vl_assay']][$row['sample_id']]['q1'] = $row['q1'];
                    $response[$row['vl_assay']][$row['sample_id']]['q3'] = $row['q3'];
                    $response[$row['vl_assay']][$row['sample_id']]['quartile_low'] = $row['quartile_low'];
                    $response[$row['vl_assay']][$row['sample_id']]['quartile_high'] = $row['quartile_high'];
                    $response[$row['vl_assay']][$row['sample_id']]['low'] = $row['low_limit'];
                    $response[$row['vl_assay']][$row['sample_id']]['high'] = $row['high_limit'];
                    $response[$row['vl_assay']][$row['sample_id']]['mean'] = $row['mean'];
                    $response[$row['vl_assay']][$row['sample_id']]['median'] = $row['median'];
                    $response[$row['vl_assay']][$row['sample_id']]['sd'] = $row['sd'];
                    $response[$row['vl_assay']][$row['sample_id']]['standard_uncertainty'] = $row['standard_uncertainty'];
                    $response[$row['vl_assay']][$row['sample_id']]['is_uncertainty_acceptable'] = $row['is_uncertainty_acceptable'];
                    $response[$row['vl_assay']][$row['sample_id']]['assay_name'] = $row['assay_name'];
                    $response[$row['vl_assay']][$row['sample_id']]['sample_label'] = $row['sample_label'];
                }
            } else {
                $response[$row['vl_assay']][$row['sample_id']]['q1'] = $row['q1'];
                $response[$row['vl_assay']][$row['sample_id']]['q3'] = $row['q3'];
                $response[$row['vl_assay']][$row['sample_id']]['quartile_low'] = $row['quartile_low'];
                $response[$row['vl_assay']][$row['sample_id']]['quartile_high'] = $row['quartile_high'];
                $response[$row['vl_assay']][$row['sample_id']]['low'] = $row['low_limit'];
                $response[$row['vl_assay']][$row['sample_id']]['high'] = $row['high_limit'];
                $response[$row['vl_assay']][$row['sample_id']]['mean'] = $row['mean'];
                $response[$row['vl_assay']][$row['sample_id']]['median'] = $row['median'];
                $response[$row['vl_assay']][$row['sample_id']]['sd'] = $row['sd'];
                $response[$row['vl_assay']][$row['sample_id']]['standard_uncertainty'] = $row['standard_uncertainty'];
                $response[$row['vl_assay']][$row['sample_id']]['is_uncertainty_acceptable'] = $row['is_uncertainty_acceptable'];
                $response[$row['vl_assay']][$row['sample_id']]['assay_name'] = $row['assay_name'];
                $response[$row['vl_assay']][$row['sample_id']]['sample_label'] = $row['sample_label'];
            }
        }
        return $response;
    }

    public function getVlRangeInformation($sId, $sampleId = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('rvc' => 'reference_vl_calculation'), array('shipment_id', 'sample_id', 'vl_assay', 'low_limit', 'high_limit', 'calculated_on', 'manual_high_limit', 'manual_low_limit', 'mean', 'sd', 'standard_uncertainty', 'is_uncertainty_acceptable', 'median', 'manual_standard_uncertainty', 'manual_is_uncertainty_acceptable', 'manual_median', 'updated_on', 'use_range'))
            ->join(array('ref' => 'reference_result_vl'), 'rvc.sample_id = ref.sample_id AND ref.shipment_id=' . $sId, array('sample_label'))
            ->join(array('a' => 'r_vl_assay'), 'a.id = rvc.vl_assay', array('assay_name' => 'name'))
            ->join(array('s' => 'shipment'), 'rvc.shipment_id = s.shipment_id')
            ->where('rvc.shipment_id = ?', $sId)
            ->order(array('sample_label', 'assay_name'));

        if ($sampleId != null) {
            $sql = $sql->where('rvc.sample_id = ?', $sampleId);
        }


        //die($sql);
        $res = $db->fetchAll($sql);

        $shipmentAttributes = !empty($res[0]['shipment_attributes']) ? json_decode($res[0]['shipment_attributes'], true) : null;
        $methodOfEvaluation = isset($shipmentAttributes['methodOfEvaluation']) ? $shipmentAttributes['methodOfEvaluation'] : 'standard';


        $response = [];

        $response['method_of_evaluation'] = $methodOfEvaluation;

        foreach ($res as $row) {



            //$response[$row['vl_assay']][$row['sample_id']]['sample_label'] = $row['sample_label'];
            //$response[$row['vl_assay']][$row['sample_id']]['sample_id'] = $row['sample_id'];
            //$response[$row['vl_assay']][$row['sample_id']]['vl_assay'] = $row['vl_assay'];
            //$response[$row['vl_assay']][$row['sample_id']]['assay_name'] = $row['assay_name'];
            //$response[$row['vl_assay']][$row['sample_id']]['low'] = $row['low_limit'];
            //$response[$row['vl_assay']][$row['sample_id']]['high'] = $row['high_limit'];
            //$response[$row['vl_assay']][$row['sample_id']]['manual_low_limit'] = $row['manual_low_limit'];
            //$response[$row['vl_assay']][$row['sample_id']]['manual_high_limit'] = $row['manual_high_limit'];
            //$response[$row['vl_assay']][$row['sample_id']]['use_range'] = $row['use_range'];

            $response[$row['sample_id']][$row['vl_assay']]['shipment_id'] = $row['shipment_id'];
            $response[$row['sample_id']][$row['vl_assay']]['sample_label'] = $row['sample_label'];
            $response[$row['sample_id']][$row['vl_assay']]['sample_id'] = $row['sample_id'];
            $response[$row['sample_id']][$row['vl_assay']]['vl_assay'] = $row['vl_assay'];
            $response[$row['sample_id']][$row['vl_assay']]['assay_name'] = $row['assay_name'];
            $response[$row['sample_id']][$row['vl_assay']]['low'] = $row['low_limit'];
            $response[$row['sample_id']][$row['vl_assay']]['high'] = $row['high_limit'];
            $response[$row['sample_id']][$row['vl_assay']]['mean'] = $row['mean'];
            $response[$row['sample_id']][$row['vl_assay']]['median'] = $row['median'];
            $response[$row['sample_id']][$row['vl_assay']]['sd'] = $row['sd'];
            $response[$row['sample_id']][$row['vl_assay']]['standard_uncertainty'] = $row['standard_uncertainty'];
            $response[$row['sample_id']][$row['vl_assay']]['is_uncertainty_acceptable'] = $row['is_uncertainty_acceptable'];
            $response[$row['sample_id']][$row['vl_assay']]['manual_low_limit'] = $row['manual_low_limit'];
            $response[$row['sample_id']][$row['vl_assay']]['manual_high_limit'] = $row['manual_high_limit'];
            $response[$row['sample_id']][$row['vl_assay']]['use_range'] = $row['use_range'];
            $response[$row['sample_id']][$row['vl_assay']]['method_of_evaluation'] = $methodOfEvaluation;

            if (!isset($response['updated_on'])) {
                $response['updated_on'] = $row['updated_on'];
            }
            if (!isset($response['calculated_on'])) {
                $response['calculated_on'] = $row['calculated_on'];
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
        $sql = $db->select()->from(array('s' => 'shipment'))
            ->where('shipment_id = ? ', $sId);
        $shipment = $db->fetchRow($sql);

        $shipmentAttributes = json_decode($shipment['shipment_attributes'], true);

        $method = isset($shipmentAttributes['methodOfEvaluation']) ? $shipmentAttributes['methodOfEvaluation'] : 'standard';

        $db->delete('reference_vl_calculation', "use_range != 'manual' AND shipment_id=$sId");


        $sql = $db->select()->from(array('ref' => 'reference_result_vl'), array('shipment_id', 'sample_id'))
            ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id', array())
            ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id', array('participant_id', 'assay' => new Zend_Db_Expr('sp.attributes->>"$.vl_assay"')))
            ->joinLeft(array('res' => 'response_result_vl'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('reported_viral_load', 'z_score'))
            ->where('sp.shipment_id = ? ', $sId)
            ->where('DATE(sp.shipment_test_report_date) <= s.lastdate_response')
            //->where("(sp.is_excluded LIKE 'yes') IS NOT TRUE")
            ->where("(sp.is_pt_test_not_performed LIKE 'yes') IS NOT TRUE");

        //echo $sql;die;

        $response = $db->fetchAll($sql);

        $sampleWise = [];
        foreach ($response as $row) {
            $sampleWise[$row['assay']][$row['sample_id']][] = ($row['reported_viral_load']);
        }

        $vlAssayArray = $this->getVlAssay();

        $skippedAssays = [];
        $skippedAssays[] = 6; // adding "Others" to skippedAssays as it will always be skipped

        $responseCounter = [];

        foreach ($vlAssayArray as $vlAssayId => $vlAssayName) {


            if (!isset($sampleWise[$vlAssayId])) {
                continue;
            }

            // echo "<pre>";
            // echo ("<h1>$vlAssayId</h1>");
            // var_dump($sampleWise);
            // echo "</pre>";

            if ('standard' == $method) {
                $minimumRequiredSamples = 6;
            } elseif ('iso17043' == $method) {
                $minimumRequiredSamples = 18;
            }

            // IMPORTANT: If the reported samples for an Assay are < $minimumRequiredSamples
            // then we use the ranges of the Assay with maximum responses

            foreach ($sampleWise[$vlAssayId] as $sample => $reportedVl) {


                if (
                    $vlAssayId != 6  && $reportedVl != ""
                    && !empty($reportedVl)
                    && count($reportedVl) > $minimumRequiredSamples
                ) {

                    $responseCounter[$vlAssayId] = count($reportedVl);

                    $rvcRow = $db->fetchRow(
                        $db->select()->from('reference_vl_calculation')
                            ->where('shipment_id = ?', $sId)
                            ->where('sample_id = ?', $sample)
                            ->where('vl_assay = ?', $vlAssayId)
                    );

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

                    if ('standard' == $method) {
                        sort($inputArray);
                        $q1 = $this->getQuartile($inputArray, 0.25);
                        $q3 = $this->getQuartile($inputArray, 0.75);
                        $iqr = $q3 - $q1;
                        $quartileLowLimit = $q1 - ($iqr * 1.5);
                        $quartileHighLimit = $q3 + ($iqr * 1.5);

                        $newArray = [];
                        $removeArray = [];
                        foreach ($inputArray as $a) {
                            if ($a >= round($quartileLowLimit, 2) && $a <= round($quartileHighLimit, 2)) {
                                $newArray[] = $a;
                            } else {
                                $removeArray[] = $a;
                            }
                        }

                        //Zend_Debug::dump("Under Assay $vlAssayId-Sample $sample - COUNT AFTER REMOVING OUTLIERS: ".count($newArray) . " FOLLOWING ARE OUTLIERS");
                        //Zend_Debug::dump($removeArray);

                        $avg = $this->getAverage($newArray);
                        $sd = $this->getStdDeviation($newArray);

                        if ($avg == 0) {
                            $cv = 0;
                        } else {
                            $cv = $sd / $avg;
                        }

                        $finalLow = $avg - (3 * $sd);
                        $finalHigh = $avg + (3 * $sd);
                    } elseif ('iso17043' == $method) {
                        sort($inputArray);
                        $median = $this->getMedian($inputArray);
                        $finalLow = $quartileLowLimit = $q1 = $this->getQuartile($inputArray, 0.25);
                        $finalHigh = $quartileHighLimit = $q3 = $this->getQuartile($inputArray, 0.75);
                        if (empty($finalLow) || empty($finalHigh)) {
                            continue;
                        }
                        $sd = 0.7413 * ($q3 - $q1);
                        $standardUncertainty = (1.25 * $sd) / sqrt(count($inputArray));
                        if ($median == 0) {
                            $isUncertaintyAcceptable = 'NA';
                        } elseif ($standardUncertainty < (0.3 * $sd)) {
                            $isUncertaintyAcceptable = 'yes';
                        } else {
                            $isUncertaintyAcceptable = 'no';
                        }
                    }


                    $data = array(
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
                        "calculated_on" => new Zend_Db_Expr('now()'),
                    );

                    if (isset($rvcRow['manual_low_limit']) && $rvcRow['manual_low_limit'] != "") {
                        $data['manual_low_limit'] = $rvcRow['manual_low_limit'];
                    }
                    if (isset($rvcRow['manual_high_limit']) && $rvcRow['manual_high_limit'] != "") {
                        $data['manual_high_limit'] = $rvcRow['manual_high_limit'];
                    }
                    if (isset($rvcRow['updated_on']) && $rvcRow['updated_on'] != "") {
                        $data['updated_on'] = $rvcRow['updated_on'];
                    }
                    if (isset($rvcRow['use_range']) && $rvcRow['use_range'] != "") {
                        $data['use_range'] = $rvcRow['use_range'];
                    }
                    $db->delete('reference_vl_calculation', "vl_assay = " . $vlAssayId . " AND sample_id=$sample AND shipment_id=$sId");

                    $db->insert('reference_vl_calculation', $data);
                } else {
                    $db->delete('reference_vl_calculation', "vl_assay = " . $vlAssayId . " AND shipment_id=$sId");
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

        $sql = $db->select()->from(array('rvc' => 'reference_vl_calculation'))
            // ->where('rvc.vl_assay = ?', $maxAssay)
            ->where('rvc.shipment_id = ?', $sId);

        if (isset($maxAssay) && $maxAssay != "") {
            $sql->where('rvc.vl_assay = ?', $maxAssay);
        }
        $res = $db->fetchAll($sql);

        foreach ($skippedAssays as $assay) {
            foreach ($res as $row) {

                $row['vl_assay'] = $assay;
                $row['no_of_responses'] = $skippedResponseCounter[$assay];

                // if there are no responses then continue
                // (this is especially put to check and remove vl assay = 6 if no one used "Others")
                // Why? because we manually inserted "6" into skippedAssays at the top of this function
                if (empty($row['no_of_responses'])) {
                    continue;
                }

                $db->delete('reference_vl_calculation', "vl_assay = " . $row['vl_assay'] . " AND sample_id= " . $row['sample_id'] . " AND shipment_id=  " . $row['shipment_id']);
                $db->insert('reference_vl_calculation', $row);
            }
        }
    }

    public function getQuartile($inputArray, $quartile)
    {
        $pos = (count($inputArray) - 1) * $quartile;

        $base = floor($pos);
        $rest = $pos - $base;

        if (isset($inputArray[$base + 1])) {
            return $inputArray[$base] + $rest * ($inputArray[$base + 1] - $inputArray[$base]);
        } else {
            return $inputArray[$base];
        }
    }

    public function getMedian($arr = array())
    {
        $count = count($arr); //total numbers in array
        $middleval = floor(($count - 1) / 2); // find the middle value, or the lowest middle value
        if ($count % 2) { // odd number, middle is the median
            $median = $arr[$middleval];
        } else { // even number, calculate avg of 2 medians
            $low = $arr[$middleval];
            $high = $arr[$middleval + 1];
            $median = (($low + $high) / 2);
        }
        return $median;
    }

    public function getAverage($inputArray)
    {
        return array_sum($inputArray) / count($inputArray);
    }

    public function getStdDeviation($inputArray)
    {
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
        return $db->fetchAll($db->select()->from('r_control')->where("for_scheme='$schemeId'"));
    }

    public function getSchemeEvaluationComments($schemeId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('r_evaluation_comments')->where("scheme='$schemeId'"));
    }

    public function getPossibleResults($schemeId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('r_possibleresult')->where("scheme_id='$schemeId'"));
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
            $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
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
            $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
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
            $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
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
        $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
        return $testkitsDb->getAllTestKitsForAllSchemes($parameters);
    }

    public function getAllCovid19TestTypeInGrid($parameters)
    {
        $testPlatformsDb = new Application_Model_DbTable_TestTypenameCovid19();
        return $testPlatformsDb->getAllTestTypesForAllSchemes($parameters);
    }

    public function getDtsTestkit($testkitId)
    {
        $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
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
            $sql = $db->select()->from(array('rvc' => 'reference_vl_calculation'), array('shipment_id', 'sample_id', 'vl_assay', 'manual_q1', 'manual_q3', 'manual_iqr', 'manual_quartile_low', 'manual_quartile_high', 'manual_mean', 'manual_sd', 'manual_cv', 'manual_high_limit', 'manual_low_limit', 'manual_standard_uncertainty', 'manual_is_uncertainty_acceptable', 'manual_median', 'use_range'))
                ->join(array('ref' => 'reference_result_vl'), 'rvc.sample_id = ref.sample_id AND ref.shipment_id=' . $shipmentId, array('sample_label'))
                ->join(array('a' => 'r_vl_assay'), 'a.id = rvc.vl_assay', array('assay_name' => 'name'))
                ->join(array('s' => 'shipment'), 'rvc.shipment_id = s.shipment_id')
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
        $sql = $db->select()->from(array('r_response_not_tested_reasons'))
            ->where('ntr_status = ? ', 'active');
        if (isset($testType) && $testType != "") {
            $sql = $sql->where("JSON_SEARCH(`ntr_test_type`, 'all', '" . $testType . "') IS NOT NULL");
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

    public function updateGeneType($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $geneTypesDb = new Application_Model_DbTable_RCovid19GeneTypes();
            $geneTypesDb->updateGeneTypeDetails($params);
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
}
