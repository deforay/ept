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
        $sql = $db->select()->from(['r_test_type_covid19'])
            ->where("scheme_type = 'covid19'");

        if ($countryAdapted) {
            $sql = $sql->where('country_adapted = 1');
        }

        return $db->fetchAll($sql);
    }

    public function getRecommededCovid19TestTypes($testPlatforms = null)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['covid19_recommended_test_types']);

        if ($testPlatforms != null && (int) $testPlatforms > 0 && (int) $testPlatforms <= 3) {
            $sql = $sql->where('test_no = ' . (int) $testPlatforms);
        }

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
        $db->beginTransaction();
        try {
            $db->delete('dts_recommended_testkits', 'dts_test_mode = "' . $testMode . '"');
            foreach ($recommended as $testNo => $kits) {
                if (!empty($kits)) {
                    foreach ($kits as $kit) {
                        $db->insert('dts_recommended_testkits', [
                            'test_no' => $testNo,
                            'testkit' => $kit,
                            'dts_test_mode' => $testMode,
                        ]);
                    }
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            Pt_Commons_LoggerUtility::logError('setRecommededDtsTestkit rolled back: ' . $e->getMessage(), [
                'testMode' => $testMode,
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 8000),
            ]);
            throw $e;
        }
    }

    public function setRecommededCovid19TestTypes($recommended)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $db->delete('covid19_recommended_test_types');
            foreach ($recommended as $testNo => $types) {
                if (!empty($types)) {
                    foreach ($types as $type) {
                        $db->insert('covid19_recommended_test_types', [
                            'test_no' => $testNo,
                            'test_type' => $type,
                        ]);
                    }
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            Pt_Commons_LoggerUtility::logError('setRecommededCovid19TestTypes rolled back: ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 8000),
            ]);
            throw $e;
        }
    }

    public function setRecommededCustomTestTypes($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $db->delete('generic_recommended_test_types', 'scheme_id = "' . $params['schemeCode'] . '"');
            if (!empty($params['customTestkit'])) {
                foreach ($params['customTestkit'] as $kit) {
                    $db->insert('generic_recommended_test_types', [
                        'scheme_id' => $params['schemeCode'],
                        'testkit' => $kit,
                    ]);
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            Pt_Commons_LoggerUtility::logError('setRecommededCustomTestTypes rolled back: ' . $e->getMessage(), [
                'schemeCode' => $params['schemeCode'] ?? null,
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 8000),
            ]);
            throw $e;
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
        $sql = $db->select()->from(['ref' => 'reference_result_covid19'])
            ->join(['s' => 'shipment'], 's.shipment_id=ref.shipment_id')
            ->join(['sp' => 'shipment_participant_map'], 's.shipment_id=sp.shipment_id')
            ->joinLeft(['res' => 'response_result_covid19'], 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', [
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
            ])
            ->where('sp.shipment_id = ? ', $sId)
            ->where('sp.participant_id = ? ', $pId);
        return $db->fetchAll($sql);
    }

    public function getGenericSamples($sId, $pId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['ref' => 'reference_result_generic_test'])
            ->join(['s' => 'shipment'], 's.shipment_id=ref.shipment_id')
            ->join(['sp' => 'shipment_participant_map'], 's.shipment_id=sp.shipment_id')
            ->joinLeft(['res' => 'response_result_generic_test'], 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', [
                'shipment_map_id',
                'result_1',
                'result_2',
                'result_3',
                'reported_result',
                'additional_detail',
                'comments',
                'calculated_score',
            ])
            ->where('sp.shipment_id = ? ', $sId)
            ->where('sp.participant_id = ? ', $pId);

        return $db->fetchAll($sql);
    }

    public function getDtsReferenceData($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['reference_result_dts'])
            ->where('shipment_id = ? ', $shipmentId);
        return $db->fetchAll($sql);
    }

    public function getCovid19ReferenceData($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['reference_result_covid19'])
            ->where('shipment_id = ? ', $shipmentId);
        return $db->fetchAll($sql);
    }
    public function getEidReferenceData($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['reference_result_eid'])
            ->where('shipment_id = ? ', $shipmentId);
        return $db->fetchAll($sql);
    }

    public function getVlReferenceData($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['reference_result_vl'])
            ->where('shipment_id = ? ', $shipmentId);
        return $db->fetchAll($sql);
    }

    public function getRecencyReferenceData($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['reference_result_recency'])
            ->where('shipment_id = ? ', $shipmentId);
        return $db->fetchAll($sql);
    }

    public function getDbsSamples($sId, $pId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['ref' => 'reference_result_dbs'])
            ->join(['s' => 'shipment'], 's.shipment_id=ref.shipment_id')
            ->join(['sp' => 'shipment_participant_map'], 's.shipment_id=sp.shipment_id')
            ->joinLeft(['res' => 'response_result_dbs'], 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', [
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
            ])
            ->where('sp.shipment_id = ? ', $sId)
            ->where('sp.participant_id = ? ', $pId);

        return $db->fetchAll($sql);
    }

    public function getEidSamples($sId, $pId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['ref' => 'reference_result_eid'])
            ->join(['s' => 'shipment'], 's.shipment_id=ref.shipment_id')
            ->join(['sp' => 'shipment_participant_map'], 's.shipment_id=sp.shipment_id')
            ->joinLeft(['res' => 'response_result_eid'], 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', ['reported_result', 'hiv_ct_od', 'ic_qs'])
            ->where('sp.shipment_id = ? ', $sId)
            ->where('sp.participant_id = ? ', $pId);
        return $db->fetchAll($sql);
    }

    public function getRecencySamples($sId, $pId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['ref' => 'reference_result_recency'])
            ->join(['s' => 'shipment'], 's.shipment_id=ref.shipment_id')
            ->join(['sp' => 'shipment_participant_map'], 's.shipment_id=sp.shipment_id')
            ->joinLeft(['res' => 'response_result_recency'], 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', ['reported_result', 'control_line', 'diagnosis_line', 'longterm_line'])
            ->where('sp.shipment_id = ? ', $sId)
            ->where('sp.participant_id = ? ', $pId);
        return $db->fetchAll($sql);
    }

    public function getVlSamples($sId, $pId, $withoutControls = true)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['ref' => 'reference_result_vl'])
            ->join(['s' => 'shipment'], 's.shipment_id=ref.shipment_id')
            ->join(['sp' => 'shipment_participant_map'], 's.shipment_id=sp.shipment_id')
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['unique_identifier'])
            ->joinLeft(['res' => 'response_result_vl'], 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', ['reported_viral_load', 'is_tnd', 'responseDate' => 'res.created_on', 'is_result_invalid', 'error_code', 'module_number', 'comment', 'z_score', 'calculated_score'])
            ->where('sp.shipment_id = ? ', $sId)
            ->where('sp.participant_id = ? ', $pId);
        if ($withoutControls) {
            $sql = $sql->where('ref.control = 0');
        }
        return $db->fetchAll($sql);
    }

    /**
     * Batch fetch VL samples for multiple participants.
     * Returns data grouped by map_id to avoid N+1 queries.
     *
     * @param int $shipmentId
     * @param array $mapIds Array of map_ids to fetch
     * @param bool $withoutControls
     * @return array keyed by map_id
     */
    public function getVlSamplesBatch(int $shipmentId, array $mapIds, bool $withoutControls = true): array
    {
        $mapIds = array_values(array_filter(array_map('intval', $mapIds)));
        if (empty($mapIds)) {
            return [];
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['ref' => 'reference_result_vl'])
            ->join(['s' => 'shipment'], 's.shipment_id=ref.shipment_id')
            ->join(['sp' => 'shipment_participant_map'], 's.shipment_id=sp.shipment_id', ['sp.map_id', 'sp.participant_id', 'sp.attributes', 'sp.shipment_receipt_date', 'sp.shipment_test_date', 'sp.is_pt_test_not_performed', 'sp.is_excluded', 'sp.shipment_test_report_date', 'sp.user_comment', 'sp.shipment_score'])
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['unique_identifier'])
            ->joinLeft(['res' => 'response_result_vl'], 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', ['reported_viral_load', 'is_tnd', 'responseDate' => 'res.created_on', 'is_result_invalid', 'error_code', 'module_number', 'comment', 'z_score', 'calculated_score'])
            ->where('sp.shipment_id = ? ', $shipmentId)
            ->where('sp.map_id IN (?)', $mapIds);

        if ($withoutControls) {
            $sql = $sql->where('ref.control = 0');
        }

        $rows = $db->fetchAll($sql);

        // Group by map_id
        $grouped = [];
        foreach ($rows as $row) {
            $mid = (int) ($row['map_id'] ?? 0);
            if ($mid <= 0) {
                continue;
            }
            if (!isset($grouped[$mid])) {
                $grouped[$mid] = [];
            }
            $grouped[$mid][] = $row;
        }

        return $grouped;
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
                'sample_score',
            ]
        )
            ->join(['s' => 'shipment'], 's.shipment_id=ref.shipment_id')
            ->join(['sp' => 'shipment_participant_map'], 's.shipment_id=sp.shipment_id')
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['unique_identifier'])
            ->joinLeft(['res' => 'response_result_tb'], 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', ['resMtb' => 'mtb_detected', 'resRif' => 'rif_resistance', 'responseDate' => 'res.created_on', 'calculated_score'])
            ->where('sp.shipment_id = ? ', $sId)
            ->where('sp.participant_id = ? ', $pId);
        if ($withoutControls) {
            $sql = $sql->where('ref.control = 0');
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

    /**
     * Fetch the possible result options for a scheme.
     *
     * @param string      $schemeId    Scheme code.
     * @param string|null $context     display_context filter ('participant', 'admin', ...).
     * @param string|null $resultGroup Restrict to one TEST/FINAL namespace. Schemes can define two
     *                                 parallel option families via r_possibleresult.scheme_sub_group
     *                                 that share the same labels (e.g. mRDT MAL-T-* test vs MAL-F-*
     *                                 final). Pass 'TEST' or 'FINAL' to get only that set; null/empty
     *                                 (or 'BOTH') returns every option. Older schemes define only one
     *                                 namespace (or none) — if the requested group is empty we fall
     *                                 back to the full list so callers never get an empty dropdown.
     */
    public function getPossibleResults($schemeId, $context = null, $resultGroup = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $fetch = function ($group) use ($db, $schemeId, $context) {
            $sql = $db->select()->from('r_possibleresult')
                ->where('scheme_id = ?', $schemeId)
                ->order('sort_order ASC');
            if (isset($context) && !empty($context)) {
                $sql->where("display_context IN ('all', ?)", $context);
            }
            if (!empty($group)) {
                $sql->where('UPPER(TRIM(scheme_sub_group)) = ?', $group);
            }
            return $db->fetchAll($sql);
        };

        $group = is_string($resultGroup) ? strtoupper(trim($resultGroup)) : '';
        if ($group !== 'TEST' && $group !== 'FINAL') {
            return $fetch(null);
        }

        $rows = $fetch($group);
        return !empty($rows) ? $rows : $fetch(null);
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
            return $schemeListDb->fetchRow($schemeListDb->select()->where('scheme_id = ?', $sid));
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
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
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
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
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
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
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
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
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
            $sessionAlert->message = 'Mapped Successfully';
            $sessionAlert->status = 'success';
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
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
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
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

    public function getNotTestedReasons($testType = '')
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['r_response_not_tested_reasons'])
            ->where('ntr_status = ? ', 'active');
        if (isset($testType) && $testType != '') {
            $sql = $sql->where("JSON_SEARCH(`ntr_test_type`, 'all', '$testType') IS NOT NULL");
        }
        return $db->fetchAll($sql);
    }

    public function getVlNotTestedReasons()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['r_response_vl_not_tested_reason'])
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
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
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
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Build a portable representation of one or more custom (user-configured) tests so
     * they can be carried to another ePT instance. Test kits are exported by NAME (not
     * the local TestKitName_ID, which differs between instances) so they can be re-mapped
     * on import. Pass an array of scheme_ids to export specific tests, or null for all.
     */
    public function exportGenericTests($schemeIds = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()
            ->from('scheme_list', ['scheme_id', 'scheme_name', 'test_format', 'user_test_config', 'status'])
            ->where('is_user_configured = ?', 'yes')
            ->order('scheme_name');
        if (!empty($schemeIds)) {
            $select->where('scheme_id IN (?)', $schemeIds);
        }
        $schemes = $db->fetchAll($select);

        $tests = [];
        foreach ($schemes as $scheme) {
            $schemeId = $scheme['scheme_id'];

            $possibleResults = $db->fetchAll(
                $db->select()->from('r_possibleresult', [
                    'scheme_sub_group', 'sub_scheme', 'result_type', 'response', 'result_code',
                    'display_context', 'high_range', 'threshold_range', 'low_range',
                    'sd_scaling_factor', 'uncertainy_scaling_factor', 'uncertainy_threshold',
                    'minimum_number_of_responses', 'sort_order',
                ])->where('scheme_id = ?', $schemeId)->order('sort_order asc')
            );

            $configRaw = $db->fetchOne(
                $db->select()->from('scheme_config', ['scheme_config_value'])
                    ->where('scheme_config_name = ?', $schemeId)
            );
            $config = $configRaw ? json_decode($configRaw, true) : null;

            $testkitNames = $db->fetchCol(
                $db->select()->from(['g' => 'generic_recommended_test_types'], [])
                    ->join(['t' => 'r_testkitnames'], 't.TestKitName_ID = g.testkit', ['TestKit_Name'])
                    ->where('g.scheme_id = ?', $schemeId)
            );

            $tests[] = [
                'scheme' => [
                    'scheme_id'        => $scheme['scheme_id'],
                    'scheme_name'      => $scheme['scheme_name'],
                    'test_format'      => $scheme['test_format'],
                    'user_test_config' => $scheme['user_test_config'] !== null ? json_decode($scheme['user_test_config'], true) : null,
                    'status'           => $scheme['status'],
                ],
                'possibleResults'     => $possibleResults,
                'config'              => $config,
                'recommendedTestkits' => $testkitNames,
            ];
        }

        return [
            'format'     => 'ept-custom-test',
            'version'    => 1,
            'appVersion' => defined('APP_VERSION') ? APP_VERSION : null,
            'exportedAt' => date('Y-m-d H:i:s'),
            'tests'      => $tests,
        ];
    }

    /**
     * Import custom tests produced by exportGenericTests(). Each test writes to scheme_list,
     * r_possibleresult, scheme_config and generic_recommended_test_types in one transaction.
     * A test whose code already exists is skipped unless $overwrite is true; built-in (non
     * user-configured) schemes are never touched. Returns a per-test summary for the UI.
     */
    public function importGenericTests($payload, $overwrite = false)
    {
        $summary = ['imported' => [], 'skipped' => [], 'warnings' => [], 'errors' => []];

        if (empty($payload['format']) || $payload['format'] !== 'ept-custom-test' || empty($payload['tests']) || !is_array($payload['tests'])) {
            $summary['errors'][] = 'Unrecognized file — this does not look like an ePT custom test export.';
            return $summary;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        foreach ($payload['tests'] as $test) {
            $scheme = $test['scheme'] ?? [];
            $schemeId = trim((string) ($scheme['scheme_id'] ?? ''));
            $schemeName = trim((string) ($scheme['scheme_name'] ?? ''));
            if ($schemeId === '' || $schemeName === '') {
                $summary['errors'][] = 'A test entry was missing its code or name and was skipped.';
                continue;
            }

            $db->beginTransaction();
            try {
                $existing = $db->fetchRow('SELECT is_user_configured FROM scheme_list WHERE scheme_id = ?', [$schemeId]);
                if ($existing) {
                    if ($existing['is_user_configured'] !== 'yes') {
                        $db->rollBack();
                        $summary['skipped'][] = $schemeId . ' — conflicts with a built-in scheme';
                        continue;
                    }
                    if (!$overwrite) {
                        $db->rollBack();
                        $summary['skipped'][] = $schemeId . ' — already exists (enable overwrite to replace)';
                        continue;
                    }
                }

                $schemeData = [
                    'scheme_name'        => $schemeName,
                    'is_user_configured' => 'yes',
                    'test_format'        => $scheme['test_format'] ?? null,
                    'user_test_config'   => isset($scheme['user_test_config']) && $scheme['user_test_config'] !== null ? json_encode($scheme['user_test_config']) : null,
                    'status'             => $scheme['status'] ?? 'active',
                ];

                if ($existing) {
                    $db->update('scheme_list', $schemeData, $db->quoteInto('scheme_id = ?', $schemeId));
                    $db->delete('r_possibleresult', $db->quoteInto('scheme_id = ?', $schemeId));
                    $db->delete('generic_recommended_test_types', $db->quoteInto('scheme_id = ?', $schemeId));
                    $db->delete('scheme_config', $db->quoteInto('scheme_config_name = ?', $schemeId));
                } else {
                    $schemeData['scheme_id'] = $schemeId;
                    $db->insert('scheme_list', $schemeData);
                }

                foreach (($test['possibleResults'] ?? []) as $pr) {
                    $db->insert('r_possibleresult', [
                        'scheme_id'                   => $schemeId,
                        'scheme_sub_group'            => $pr['scheme_sub_group'] ?? null,
                        'sub_scheme'                  => $pr['sub_scheme'] ?? null,
                        'result_type'                 => $pr['result_type'] ?? null,
                        'response'                    => $pr['response'] ?? null,
                        'result_code'                 => $pr['result_code'] ?? null,
                        'display_context'             => $pr['display_context'] ?? 'all',
                        'high_range'                  => $pr['high_range'] ?? null,
                        'threshold_range'             => $pr['threshold_range'] ?? null,
                        'low_range'                   => $pr['low_range'] ?? null,
                        'sd_scaling_factor'           => $pr['sd_scaling_factor'] ?? null,
                        'uncertainy_scaling_factor'   => $pr['uncertainy_scaling_factor'] ?? null,
                        'uncertainy_threshold'        => $pr['uncertainy_threshold'] ?? null,
                        'minimum_number_of_responses' => $pr['minimum_number_of_responses'] ?? null,
                        'sort_order'                  => $pr['sort_order'] ?? null,
                    ]);
                }

                if (!empty($test['config']) && is_array($test['config'])) {
                    $db->insert('scheme_config', [
                        'scheme_config_name'  => $schemeId,
                        'scheme_config_value' => json_encode($test['config']),
                    ]);
                }

                foreach (($test['recommendedTestkits'] ?? []) as $kitName) {
                    $kitId = $db->fetchOne('SELECT TestKitName_ID FROM r_testkitnames WHERE TestKit_Name = ? LIMIT 1', [$kitName]);
                    if ($kitId) {
                        $db->insert('generic_recommended_test_types', ['scheme_id' => $schemeId, 'testkit' => $kitId]);
                    } else {
                        $summary['warnings'][] = $schemeId . ': test kit "' . $kitName . '" not found on this instance — enforcement skipped';
                    }
                }

                $db->commit();
                $summary['imported'][] = $schemeId . ($existing ? ' (updated)' : ' (new)');
            } catch (Throwable $e) {
                $db->rollBack();
                Pt_Commons_LoggerUtility::logError('importGenericTests failed: ' . $e->getMessage(), [
                    'schemeId' => $schemeId,
                    'file'     => $e->getFile(),
                    'line'     => $e->getLine(),
                    'trace'    => substr($e->getTraceAsString(), 0, 8000),
                ]);
                $summary['errors'][] = $schemeId . ' — ' . $e->getMessage();
            }
        }

        return $summary;
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
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
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
        $sql = $db->select()->from(['r_covid19_gene_types'], ['gene_id', 'gene_name'])
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
                $sessionAlert->message = 'Saved Successfully';
                $sessionAlert->status = 'success';
                $db->commit();
            } else {
                $sessionAlert->message = 'Something went wrong. Please try again later.';
                $sessionAlert->status = 'failure';
                $db->rollBack();
            }
        } catch (Exception $e) {
            $db->rollBack();
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function getNotTestedReasonById($id)
    {
        $ntrDb = new Application_Model_DbTable_ResponseNotTestedReasons();
        return $ntrDb->fetchNotTestedReasonById($id);
    }
}
