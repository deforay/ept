<?php

class Application_Service_ApiServices
{
    protected $db;
    protected $common;
    protected $dataManagerDb;
    protected $configDb;
    protected $schemeService;
    protected $shipmentService;
    protected $mapDb;

    public function __construct()
    {
        $this->db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->common = new Application_Service_Common();
        $this->dataManagerDb = new Application_Model_DbTable_DataManagers();
        $this->configDb = new Application_Model_DbTable_SystemConfig();
        $this->schemeService = new Application_Service_Schemes();
        $this->shipmentService = new Application_Service_Shipments();
        $this->mapDb = new Application_Model_DbTable_ShipmentParticipantMap();
    }

    public function getApiReferences($params)
    {
        if (!isset($params['authToken'])) {
            return array('status' => 'auth-fail', 'message' => 'Please check your credentials and try to log in again');
        }
        /* Check the app versions */
        /* if (!isset($params['appVersion'])) {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        $appVersion = $this->configDb->getValue($params['appVersion']);
        /* Check the app versions */
        /* if (!$appVersion) {
            return array('status' => 'version-failed', 'message' => 'app-version-failed');
        } */
        $aResult = $this->dataManagerDb->fetchAuthToken($params);
        /* Validate new auth token and app-version */
        if (!$aResult) {
            return array('status' => 'auth-fail', 'message' => 'Please check your credentials and try to log in again');
        }
        $response = [];
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, APPLICATION_ENV);
        $sql = $this->db->select()->from(array('dm' => 'data_manager'), array(''))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.dm_id=dm.dm_id')
            ->join(array('p' => 'participant'), 'p.participant_id=pmm.participant_id', array('*'))
            ->where("dm.auth_token=?", $params['authToken']);
        $response['participants'] = $this->db->fetchAll($sql);

        $response['modeOfReceipt'] = $this->common->getAllModeOfReceipt();


        /* Started DTS (HIV Serology) References */
        $dtsModel = new Application_Model_Dts();
        // Load dts configuration into a init as array
        $response['dts']['config'] = $config->evaluation->dts->toArray();

        // Load dts testing kit into a init as separate tests
        $allTestKits = $dtsModel->getAllDtsTestKitList();
        // To load kit attributes for each kit
        $testKit = [];
        foreach ($allTestKits as $kit) {
            foreach (range(1, 3) as $no) {
                if ($kit['testkit_' . $no] == 1) {
                    $testKit['testKit' . $no][$kit['TESTKITNAMEID']]['kitid'] = $kit['TESTKITNAMEID'];
                    $testKit['testKit' . $no][$kit['TESTKITNAMEID']]['kitname'] = $kit['TESTKITNAME'];
                    $testKit['testKit' . $no][$kit['TESTKITNAMEID']]['kitattributes'] = (isset($kit['attributes']) && !empty($kit['attributes'])) ? json_decode($kit['attributes'], true) : '';
                }
            }
        }
        $response['dts']['testKits'] = $testKit;
        // To load possible results for DTS shpments
        $dtsPossibleResults = $this->schemeService->getPossibleResults('dts', 'participant');
        $dtsResults = [];
        foreach ($dtsPossibleResults as $pr) {
            $pr['scheme_sub_group'] = (isset($pr['scheme_sub_group']) && !empty($pr['scheme_sub_group']) && trim($pr['scheme_sub_group']) != '') ? $pr['scheme_sub_group'] : 'DEFAULT';
            $dtsResults[$pr['scheme_sub_group']][] = [
                'id' => $pr['id'],
                'name' => strtoupper($pr['response'])
            ];
        }
        $response['dts']['possibleResults'] = $dtsResults;
        // To load possible results for recency RTRI
        $recencyPossibleResults = $this->schemeService->getPossibleResults('recency', 'participant');
        $recencyPossibleResults = [];
        foreach ($recencyPossibleResults as $pr) {
            $pr['scheme_sub_group'] = (isset($pr['scheme_sub_group']) && !empty($pr['scheme_sub_group']) && trim($pr['scheme_sub_group']) != '') ? $pr['scheme_sub_group'] : 'DEFAULT';
            $recencyPossibleResults[$pr['scheme_sub_group']][] = [
                'id' => $pr['id'],
                'name' => strtoupper($pr['response'])
            ];
        }
        $response['dts']['recencyPossibleResults'] = $recencyPossibleResults;
        // To load not tested reason for DTS shipment
        $response['dts']['notTestedReason'] = $this->schemeService->getNotTestedReasons("dts");
        /* END DTS (HIV Serology) References */

        /* Started HIV Viral Load References */
        $response['vl']['vlAssay'] = $this->schemeService->getVlAssay(false);
        /* End HIV Viral Load References */

        /* Started Dried Blood Spot - Early Infant Diagnosis References */
        $eidPossibleResults = $this->schemeService->getPossibleResults('eid', 'participant');
        $eidResults = [];
        foreach ($eidPossibleResults as $pr) {
            $pr['scheme_sub_group'] = (isset($pr['scheme_sub_group']) && !empty($pr['scheme_sub_group']) && trim($pr['scheme_sub_group']) != '') ? $pr['scheme_sub_group'] : 'DEFAULT';
            $eidResults[$pr['scheme_sub_group']][] = [
                'id' => $pr['id'],
                'name' => strtoupper($pr['response'])
            ];
        }
        $response['eid']['possibleResults'] = $eidResults;
        /* End Dried Blood Spot - Early Infant Diagnosis References */

        /* Started Custom Test References */
        $schemeList = $this->schemeService->getGenericSchemeLists();
        if (isset($schemeList) && !empty($schemeList)) {
            foreach ($schemeList as $list) {
                $customPossibleResults = $this->schemeService->getPossibleResults($list['scheme_id'], 'participant');
                $customResults = [];
                foreach ($customPossibleResults as $pr) {
                    $pr['scheme_sub_group'] = (isset($pr['scheme_sub_group']) && !empty($pr['scheme_sub_group']) && trim($pr['scheme_sub_group']) != '') ? $pr['scheme_sub_group'] : 'DEFAULT';
                    $customResults[$pr['scheme_sub_group']][] = [
                        'id' => $pr['id'],
                        'name' => strtoupper($pr['response'])
                    ];
                }
                $response[$list['scheme_id']]['possibleResults'] = $customResults;
            }
        }
        /* End Custom Test References */
        $payload = array('status' => 'success', 'data' => $response);

        $transactionId = $transactionId ?? Pt_Commons_General::generateULID();
        self::addApiTracking($transactionId, $aResult['dm_id'], count($response), 'save-shipments', 'common', $_SERVER['REQUEST_URI'], $params, $payload, 'json');
        return $payload;
    }

    public function getAggregatedInsightsAPIData()
    {
        // To get the instance domain name from application ini
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $response['instance_domain'] = $conf->domain;

        // To get the instance ID from the system meta data model
        $systemMetaDataDb = new Application_Model_DbTable_SystemMetaData();
        $response['instance_id'] = $systemMetaDataDb->getValue('instance-id')['metadata_value'];

        // To get the list of active participants list
        $participantSql = $this->db->select()->from(array('p' => 'participant'), array(
            'no_of_active_participants' => new Zend_Db_Expr("SUM(CASE WHEN (status = 'active') THEN 1 ELSE 0 END)"),
        ));
        $response['active_participants'] = $this->db->fetchRow($participantSql)['no_of_active_participants'];

        // To get the list of active and finalized shipment list scheme wise
        $schemeSql = $this->db->select()->from(array('sl' => 'scheme_list'), array('name' => 'sl.scheme_name'))
            ->join(
                array('s' => 'shipment'),
                'sl.scheme_id=s.scheme_type',
                array(
                    's.shipment_id',
                    'type' => 's.scheme_type',
                    'active' => new Zend_Db_Expr("SUM(CASE WHEN ((s.status IN ('shipped', 'evaluated') AND s.status != 'finalized') AND (IFNULL(s.response_switch, 'off') = 'on')) THEN 1 ELSE 0 END)"),
                    'finalized' => new Zend_Db_Expr("SUM(CASE WHEN (s.status = 'finalized') THEN 1 ELSE 0 END)")
                ),
            )->group('s.scheme_type');
        $shipments = $this->db->fetchAll($schemeSql);
        $response['schemes'] = [];
        if (isset($shipments) && !empty($shipments)) {
            foreach ($shipments as $key => $list) {
                $response['schemes'][$key] = [
                    "name"  => $list['name'],
                    "type"  => $list['type'],
                    "active"  => $list['active'],
                    "finalized"  => $list['finalized'],
                    "shipments"  => $this->fetchShipmentDetails($list['type'])
                ];
            }
        }
        return $response;
    }

    public function fetchShipmentDetails($schemeId)
    {
        $sql = $this->db->select()->from(array('s' => 'shipment'), array('s.shipment_code', 's.shipment_date', 's.number_of_samples', 'shipment_status' => 's.status'))
            ->joinLeft(
                array('spm' => 'shipment_participant_map'),
                's.shipment_id=spm.shipment_id',
                array(
                    'enrolled_participants' => new Zend_Db_Expr("count(spm.participant_id)"),
                    'responded_participants' => new Zend_Db_Expr("SUM(spm.response_status is not null AND spm.response_status like 'responded')"),
                    'passed_participants' => new Zend_Db_Expr("SUM(spm.final_result = 1)"),
                    'failed_participants' => new Zend_Db_Expr("SUM(spm.final_result != 1)"),
                    'excluded_participants' => new Zend_Db_Expr("SUM(spm.is_excluded = 'yes')"),
                ),
            )->where("s.scheme_type = '" . $schemeId . "'")
            ->group('spm.shipment_id');
        return $this->db->fetchAll($sql);
    }

    public function saveShipmentDetailsFromAPI($parameters)
    {
        if (!isset($parameters['authToken'])) {
            return array('status' => 'auth-fail', 'message' => 'Please check your credentials and try to log in again');
        }
        /* Check the app versions */
        /* if (!isset($parameters['appVersion'])) {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        }
        $appVersion = $this->configDb->getValue($parameters['appVersion']); */
        /* Check the app versions */
        /* if (!$appVersion) {
            return array('status' => 'version-failed', 'message' => 'app-version-failed');
        } */
        $aResult = $this->dataManagerDb->fetchAuthToken($parameters);
        /* Validate new auth token and app-version */
        if (!$aResult) {
            return array('status' => 'auth-fail', 'message' => 'Please check your credentials and try to log in again');
        }
        $response = [];
        $schemeType = "";
        foreach ($parameters['data'] as $key => $param) {
            $param = (array)$param;
            $schemeType = $param['schemeType'];
            if (!$this->shipmentService->isShipmentEditable($param['shipmentId'], $param['participantId'])) {
                return array('status' => 'fail', 'message' => 'Responding for this shipment is not allowed at this time. Please contact your PT Provider for any clarifications..');
            }
            $mandatoryFields = array('shipmentDate', 'testingDate', 'sampleRehydrationDate', 'algorithm');

            $mandatoryCheckErrors = $this->shipmentService->mandatoryFieldsCheck($param, $mandatoryFields);
            if (count($mandatoryCheckErrors) > 0) {
                return array('status' => 'fail', 'message' => 'Please send the required Fields and sync the shipment data');
            }
            $attributes["sample_rehydration_date"] = Pt_Commons_General::isoDateFormat($param['sampleRehydrationDate'] ?? '');
            if (isset($param['schemeType']) && !empty($param['schemeType']) && $param['schemeType'] == 'dts') {
                $attributes["algorithm"] = $param['algorithm'];
                if (isset($param['conditionPtSamples']) && !empty($param['conditionPtSamples'])) {
                    $attributes["condition_pt_samples"] = (isset($param['conditionPtSamples']) && !empty($param['conditionPtSamples'])) ? $param['conditionPtSamples'] : '';
                    $attributes["refridgerator"] = (isset($param['refridgerator']) && !empty($param['refridgerator'])) ? $param['refridgerator'] : '';
                    $attributes["room_temperature"] = (isset($param['roomTemperature']) && !empty($param['roomTemperature'])) ? $param['roomTemperature'] : '';
                    $attributes["stop_watch"] = (isset($param['stopWatch']) && !empty($param['stopWatch'])) ? $param['stopWatch'] : '';
                }
                $attributes["dts_test_panel_type"] = $param['dtsTestPanelType'] ?? null;
            }
            if (isset($param['schemeType']) && !empty($param['schemeType']) && $param['schemeType'] == 'vl') {
                $attributes["vl_assay"] = $param['vlAssay'] ?? null;
                $attributes["assay_lot_number"] = $param['assayLotNumber'] ?? null;
                $attributes["assay_expiration_date"] = Pt_Commons_General::isoDateFormat($param['assayExpirationDate'] ?? null);
                $attributes["specimen_volume"] = $param['specimenVolume'] ?? null;
                $attributes["date_of_xpert_instrument_calibration"] = $param['geneXpertInstrument'] ?? null;
                $attributes["instrument_sn"] = $param['instrumentSn'] ?? null;
                $attributes["uploaded_file"] = $param['uploadedFilePath'] ?? null;
                $attributes["extraction"] = (isset($param['extraction']) && $param['extraction'] != "" && $param['platformType'] == 'htp') ? $param['extraction'] :  null;
                $attributes["amplification"] = (isset($param['amplification']) && $param['amplification'] != "" && $param['platformType'] == 'htp') ? $param['amplification'] :  null;
            }
            if (isset($param['schemeType']) && !empty($param['schemeType']) && $param['schemeType'] == 'eid') {
                $attributes["extraction_assay"] = $param['extractionAssay'] ?? null;
                $attributes["extraction_assay_lot_no"] = $param['extractionAssayLotNo'] ?? null;
                $attributes["extraction_assay_expiry_date"] = Pt_Commons_General::isoDateFormat($param['extractionAssayExpiryDate'] ?? null);
                $attributes["detection_assay"] = $param['detectionAssay'] ?? null;
                $attributes["detection_assay_lot_no"] = $param['detectionAssayLotNo'] ?? null;
                $attributes["detection_assay_expiry_date"] = Pt_Commons_General::isoDateFormat($param['detectionAssayExpiryDate'] ?? null);
            }
            if (isset($param['schemeType']) && !empty($param['schemeType']) && $param['schemeType'] == 'custom-tests') {
                $attributes = array(
                    "analyst_name" => $param['analystName'] ?? null,
                    "kit_name" => $param['kitName'] ?? null,
                    "kit_lot_number" => $param['kitLot'] ?? null,
                    "kit_expiry_date" => Pt_Commons_General::isoDateFormat($param['expiryDate'] ?? null),
                );
            }
            $attributes = json_encode($attributes);
            $responseStatus = "responded";
            if (isset($param['isPtTestNotPerformed']) && $param['isPtTestNotPerformed'] == "yes") {
                $responseStatus = "nottested";
            }
            $data = [
                "shipment_receipt_date" => Pt_Commons_General::isoDateFormat($param['shipmentDate']),
                "shipment_test_date" => Pt_Commons_General::isoDateFormat($param['testingDate']),
                "attributes" => $attributes,
                "supervisor_approval" => $param['supervisorReview'],
                "participant_supervisor" => $param['supervisorName'],
                "user_comment" => $param['comments'],
                "mode_id" => $param['modeOfReceipt'] ?? null,
                "response_status" => $responseStatus,
            ];

            if (!empty($aResult['dm_id'])) {
                $data["updated_by_user"] = $aResult['dm_id'] ?? null;
                $data["updated_on_user"] = new Zend_Db_Expr('now()');
            }

            if (isset($param['responseDate']) && trim($param['responseDate']) != '') {
                $data['shipment_test_report_date'] = Pt_Commons_General::isoDateFormat($param['responseDate']);
            } else {
                $data['shipment_test_report_date'] = new Zend_Db_Expr('now()');
            }

            if (isset($aResult['qc_access']) && $aResult['qc_access'] == 'yes') {
                $data['qc_done'] = $param['qcDone'];
                if (isset($param['qcDone']) && trim($param['qcDone']) == "yes") {
                    $data['qc_date'] = Pt_Commons_General::isoDateFormat($param['qcDate']);
                    $data['qc_done_by'] = trim($param['qcDoneBy']);
                    $data['qc_created_on'] = new Zend_Db_Expr('now()');
                } else {
                    $data['qc_date'] = null;
                    $data['qc_done_by'] = null;
                    $data['qc_created_on'] = null;
                }
            }

            if (isset($param['isPtTestNotPerformed']) && $param['isPtTestNotPerformed'] == 'yes') {
                $data['is_pt_test_not_performed'] = 'yes';
                $data['shipment_test_date'] = null;
                $data['vl_not_tested_reason'] = $param['notTestedReason'];
                $data['pt_test_not_performed_comments'] = $param['ptNotTestedComments'];
                $data['pt_support_comments'] = $param['ptSupportComment'];
            } else {
                $data['is_pt_test_not_performed'] = 'no';
                $data['vl_not_tested_reason'] = null;
                $data['pt_test_not_performed_comments'] = null;
                $data['pt_support_comments'] = null;
            }

            if (isset($param['custom_field_1']) && !empty(trim($param['custom_field_1']))) {
                $data['custom_field_1'] = trim($param['custom_field_1']);
            }

            if (isset($param['custom_field_2']) && !empty(trim($param['custom_field_2']))) {
                $data['custom_field_2'] = trim($param['custom_field_2']);
            }

            if (isset($param['labDirectorName']) && $param['labDirectorName'] != "") {
                $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
                /* Shipment Participant table updation */
                $dbAdapter->update(
                    'shipment_participant_map',
                    array(
                        'lab_director_name'         => $param['labDirectorName'],
                        'lab_director_email'        => $param['labDirectorEmail'],
                        'contact_person_name'       => $param['contactPersonName'],
                        'contact_person_email'      => $param['contactPersonEmail'],
                        'contact_person_telephone'  => $param['contactPersonTelephone']
                    ),
                    'map_id = ' . $param['mapId']
                );
                /* Participant table updation */
                $dbAdapter->update(
                    'participant',
                    array(
                        'lab_director_name'         => $param['labDirectorName'],
                        'lab_director_email'        => $param['labDirectorEmail'],
                        'contact_person_name'       => $param['contactPersonName'],
                        'contact_person_email'      => $param['contactPersonEmail'],
                        'contact_person_telephone'  => $param['contactPersonTelephone']
                    ),
                    'participant_id = ' . $param['participantId']
                );
            }
            $shipmentUpdate = $this->mapDb->updateShipmentByAPIV2($data, $param['dmId'], $param);
            $resultUpdate = $this->updateResults($param);
            if ($shipmentUpdate || $resultUpdate) {
                $response[$key]['status'] = 'success';
            } else {
                $response[$key]['status'] = 'fail';
            }
        }
        if (isset($response) && !empty($response)) {
            $payload = array(
                'status'  => 'success',
                'data'    => $response,
                'message' => 'Shipment form saved successfully.'
            );
        } else {
            $payload = array(
                'status'  => 'fail',
                'message' => 'Shipment form not saved. Please re-sync again'
            );
        }
        $transactionId = $transactionId ?? Pt_Commons_General::generateULID();
        self::addApiTracking($transactionId, $aResult['dm_id'], count($parameters['data']), 'save-shipments', $schemeType, $_SERVER['REQUEST_URI'], $parameters, $payload, 'json');
        return $payload;
    }

    public function updateResults($params)
    {
        $status = false;
        if (isset($params['schemeType']) && !empty($params['schemeType']) && $params['schemeType'] == 'dts') {
            $responseDb = new Application_Model_DbTable_ResponseDts();
            $status = $responseDb->updateResultsByAPIV2($params);
        }
        if (isset($params['schemeType']) && !empty($params['schemeType']) && $params['schemeType'] == 'vl') {
            $responseDb = new Application_Model_DbTable_ResponseVl();
            $status = $responseDb->updateResultsByAPIV2($params);
        }
        if (isset($params['schemeType']) && !empty($params['schemeType']) && $params['schemeType'] == 'eid') {
            $responseDb = new Application_Model_DbTable_ResponseEid();
            $status = $responseDb->updateResultsByAPIV2($params);
        }
        if (isset($params['schemeType']) && !empty($params['schemeType']) && $params['schemeType'] == 'custom-tests') {
            $responseDb = new Application_Model_DbTable_ResponseGenericTest();
            $status = $responseDb->updateResultsByAPIV2($params);
        }
        return $status;
    }

    public function addApiTracking($transactionId, $user, $numberOfRecords, $requestType, $testType, $url = null, $requestData = null, $responseData = null, $format = null)
    {
        $common = new Application_Service_Common();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        try {

            $requestData = Pt_Commons_JsonUtility::encodeUtf8Json($requestData ?? '{}');
            $responseData = Pt_Commons_JsonUtility::encodeUtf8Json($responseData ?? '{}');

            $folderPath = realpath(UPLOAD_PATH) . DIRECTORY_SEPARATOR . 'track-api';
            if (!empty($requestData) && $requestData != '[]') {
                $common->makeDirectory($folderPath . DIRECTORY_SEPARATOR . 'requests');
                $common->dataToZippedFile($requestData, "$folderPath/requests/$transactionId.json");
            }
            if (!empty($responseData) && $responseData != '[]') {
                $common->makeDirectory($folderPath . DIRECTORY_SEPARATOR . 'responses');
                $common->dataToZippedFile($responseData, "$folderPath/responses/$transactionId.json");
            }

            $data = [
                'transaction_id' => $transactionId ?? null,
                'requested_by' => $user ?? 'system',
                'requested_on' => Pt_Commons_General::getDateTime(),
                'number_of_records' => $numberOfRecords ?? 0,
                'request_type' => $requestType ?? null,
                'test_type' => $testType ?? null,
                'api_url' => $url ?? null,
                'data_format' => $format ?? null
            ];
            return $db->insert("track_api_requests", $data);
        } catch (Throwable $exc) {
            Pt_Commons_LoggerUtility::log('error', $exc->getFile() . ":" . $exc->getLine() . " - " . $exc->getMessage());
            return 0;
        }
    }

    public function fetchTrackApiHistoryList()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $response['requestType'] = $db->fetchCol($db->select()->from('track_api_requests', 'request_type')->group('request_type'));
        $response['testType'] = $db->fetchCol($db->select()->from('track_api_requests', 'test_type')->group('test_type'));
        $response['manager'] = $db->fetchAll($db->select()->from(['tar' => 'track_api_requests'], 'requested_by')->group('requested_by')->joinLeft(['dm' => 'data_manager'], 'tar.requested_by = dm.dm_id', ['name' => new Zend_Db_Expr("CONCAT( COALESCE(dm.first_name,''),' ', COALESCE(dm.last_name,''), '(', COALESCE(dm.primary_email,''), ')' )")]));
        return $response;
    }

    public function fetchAllApiSyncDetailsByGrid($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */


        $aColumns = ['transaction_id', 'number_of_records', 'request_type', 'test_type', "api_url", "DATE_FORMAT(requested_on,'%d-%b-%Y')"];
        $orderColumns = ['transaction_id', 'number_of_records', 'request_type', 'test_type', 'api_url', 'requested_on'];
        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */
        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ($parameters['sSortDir_' . $i]) . ", ";
                }
            }

            $sOrder = substr_replace($sOrder, "", -2);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */
        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $db->select()->from(array('tar' => 'track_api_requests'))
            ->joinLeft(['dm' => 'data_manager'], 'tar.requested_by = dm.dm_id', ['name' => new Zend_Db_Expr("CONCAT( COALESCE(dm.first_name,''),' ', COALESCE(dm.last_name,''), '(', COALESCE(dm.primary_email,''), ')' )")]);

        if (isset($parameters['createdBy']) && $parameters['createdBy'] != "") {
            $sQuery = $sQuery->where("tar.created_by = ? ", $parameters['createdBy']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(tar.created_on) >= ?", $common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(tar.created_on) <= ?", $common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['syncType']) && $parameters['syncType'] != "") {
            $sQuery = $sQuery->where("tar.request_type = ? ", $parameters['syncType']);
        }

        if (isset($parameters['schemeType']) && $parameters['schemeType'] != "") {
            $sQuery = $sQuery->where("tar.test_type = ? ", $parameters['schemeType']);
        }

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        // echo ($sQuery);die;
        $rResult = $db->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $db->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $db->select()->from(array("tar" => "track_api_requests"), new Zend_Db_Expr("COUNT('api_track_id')"));

        if (isset($parameters['createdBy']) && $parameters['createdBy'] != "") {
            $sQuery = $sQuery->where("tar.created_by = ? ", $parameters['createdBy']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(tar.created_on) >= ?", $common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(tar.created_on) <= ?", $common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['syncType']) && $parameters['syncType'] != "") {
            $sQuery = $sQuery->where("tar.request_type = ? ", $parameters['syncType']);
        }

        if (isset($parameters['schemeType']) && $parameters['schemeType'] != "") {
            $sQuery = $sQuery->where("tar.test_type = ? ", $parameters['schemeType']);
        }
        $aResultTotal = $db->fetchCol($sQuery);
        $iTotal = $aResultTotal[0];

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = $aRow['transaction_id'];
            $row[] = $aRow['number_of_records'];
            $row[] = strtoupper(str_replace("-", " ", (string) $aRow['request_type']));
            $row[] = strtoupper((string) $aRow['test_type']);
            $row[] = $aRow['api_url'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['requested_on'], true);
            $row[] = '<a href="javascript:void(0);" class="btn btn-success btn-xs" style="margin-right: 2px;" title="Result" onclick="layoutModal(\'/admin/api-history/api-params?id=' . base64_encode((string) $aRow['api_track_id']) . '\',1200,720);"> Show Params</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getTrackApiParams($id)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchRow($db->select()->from('track_api_requests', '*')->where('api_track_id = ?', $id));
    }
}
