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
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
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
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
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
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
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
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
        }
    }

    public function updateTestkitStage($params)
    {
        $sessionAlert = new Zend_Session_Namespace('alertSpace');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $testkitsDb = new Application_Model_DbTable_Testkitnames();
            $testkitsDb->updateTestkitStageDetails($params);
            $sessionAlert->message = "Mapped Successfully";
            $sessionAlert->status = "success";
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
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
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
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

    public function getSchemeById($id)
    {
        $schemeDb = new Application_Model_DbTable_SchemeList();
        return $schemeDb->fetchSchemeById($id);
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
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
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
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
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
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
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
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
        }
    }

    public function getNotTestedReasonById($id)
    {
        $ntrDb = new Application_Model_DbTable_ResponseNotTestedReasons();
        return $ntrDb->fetchNotTestedReasonById($id);
    }
}
