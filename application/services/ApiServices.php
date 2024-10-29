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

        return array('status' => 'success', 'data' => $response);
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

    public function saveShipmentDetailsFromAPI($params)
    {
        if (!isset($params['authToken'])) {
            return array('status' => 'auth-fail', 'message' => 'Please check your credentials and try to log in again');
        }
        /* Check the app versions */
        /* if (!isset($params['appVersion'])) {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        }
        $appVersion = $this->configDb->getValue($params['appVersion']); */
        /* Check the app versions */
        /* if (!$appVersion) {
            return array('status' => 'version-failed', 'message' => 'app-version-failed');
        } */
        $aResult = $this->dataManagerDb->fetchAuthToken($params);
        /* Validate new auth token and app-version */
        if (!$aResult) {
            return array('status' => 'auth-fail', 'message' => 'Please check your credentials and try to log in again');
        }
        if (!$this->shipmentService->isShipmentEditable($params['shipmentId'], $params['participantId']) && (!isset($params['reqAccessFrom']) || empty($params['reqAccessFrom']) || $params['reqAccessFrom'] != 'admin')) {
            return array('status' => 'fail', 'message' => 'Responding for this shipment is not allowed at this time. Please contact your PT Provider for any clarifications..');
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $mandatoryFields = array('receiptDate', 'testDate', 'sampleRehydrationDate', 'algorithm');
        $db->beginTransaction();

        $mandatoryCheckErrors = $this->shipmentService->mandatoryFieldsCheck($params, $mandatoryFields);
        if (count($mandatoryCheckErrors) > 0) {
            // $userAgent = $_SERVER['HTTP_USER_AGENT'];
            // $ipAddress = $commonService->getIPAddress();
            // $operatingSystem = $commonService->getOperatingSystem($userAgent);
            // $browser = $commonService->getBrowser($userAgent);
            // error_log(date('Y-m-d H:i:s') . '|FORMERROR|Missed mandatory fields - ' . implode(",", $mandatoryCheckErrors) . '|' . $params['schemeCode'] . '|' . $params['participantId'] . '|' . $ipAddress . '|' . $operatingSystem . '|' . $browser  . PHP_EOL, 3, DOWNLOADS_FOLDER . " /../errors.log");
            return array('status' => 'fail', 'message' => 'Please send the required Fields and sync the shipment data');
        }
        $attributes["sample_rehydration_date"] = Pt_Commons_General::isoDateFormat($params['sampleRehydrationDate'] ?? '');
        $attributes["algorithm"] = $params['algorithm'];
        if (isset($params['conditionOfPTSamples']) && !empty($params['conditionOfPTSamples'])) {
            $attributes["condition_pt_samples"] = (isset($params['conditionOfPTSamples']) && !empty($params['conditionOfPTSamples'])) ? $params['conditionOfPTSamples'] : '';
            $attributes["refridgerator"] = (isset($params['refridgerator']) && !empty($params['refridgerator'])) ? $params['refridgerator'] : '';
            $attributes["room_temperature"] = (isset($params['roomTemperature']) && !empty($params['roomTemperature'])) ? $params['roomTemperature'] : '';
            $attributes["stop_watch"] = (isset($params['stopWatch']) && !empty($params['stopWatch'])) ? $params['stopWatch'] : '';
        }
        $attributes["dts_test_panel_type"] = $params['dtsTestPanelType'] ?? null;
        $attributes = json_encode($attributes);
        $responseStatus = "responded";
        if (isset($params['isPtTestNotPerformed']) && $params['isPtTestNotPerformed'] == "yes") {
            $responseStatus = "nottested";
        }
        $data = [
            "shipment_receipt_date" => Pt_Commons_General::isoDateFormat($params['receiptDate']),
            "shipment_test_date" => Pt_Commons_General::isoDateFormat($params['testDate']),
            "attributes" => $attributes,
            "supervisor_approval" => $params['supervisorApproval'],
            "participant_supervisor" => $params['participantSupervisor'],
            "user_comment" => $params['userComments'],
            "mode_id" => $params['modeOfReceipt'] ?? null,
            "response_status" => $responseStatus,
        ];

        if (!empty($authNameSpace->dm_id)) {
            $data["updated_by_user"] = $authNameSpace->dm_id ?? null;
            $data["updated_on_user"] = new Zend_Db_Expr('now()');
        } elseif (!empty($adminAuthNameSpace->admin_id)) {
            $data["updated_by_admin"] = $adminAuthNameSpace->admin_id ?? null;
            $data["updated_on_admin"] = new Zend_Db_Expr('now()');
        }

        if (isset($params['testReceiptDate']) && trim($params['testReceiptDate']) != '') {
            $data['shipment_test_report_date'] = Pt_Commons_General::isoDateFormat($params['testReceiptDate']);
        } else {
            $data['shipment_test_report_date'] = new Zend_Db_Expr('now()');
        }

        if (isset($authNameSpace->qc_access) && $authNameSpace->qc_access == 'yes') {
            $data['qc_done'] = $params['qcDone'];
            if (isset($data['qc_done']) && trim($data['qc_done']) == "yes") {
                $data['qc_date'] = Pt_Commons_General::isoDateFormat($params['qcDate']);
                $data['qc_done_by'] = trim($params['qcDoneBy']);
                $data['qc_created_on'] = new Zend_Db_Expr('now()');
            } else {
                $data['qc_date'] = null;
                $data['qc_done_by'] = null;
                $data['qc_created_on'] = null;
            }
        }

        if (isset($params['isPtTestNotPerformed']) && $params['isPtTestNotPerformed'] == 'yes') {
            $data['is_pt_test_not_performed'] = 'yes';
            $data['shipment_test_date'] = null;
            $data['vl_not_tested_reason'] = $params['vlNotTestedReason'];
            $data['pt_test_not_performed_comments'] = $params['ptNotTestedComments'];
            $data['pt_support_comments'] = $params['ptSupportComments'];
        } else {
            $data['is_pt_test_not_performed'] = null;
            $data['vl_not_tested_reason'] = null;
            $data['pt_test_not_performed_comments'] = null;
            $data['pt_support_comments'] = null;
        }

        if (isset($params['customField1']) && !empty(trim($params['customField1']))) {
            $data['custom_field_1'] = trim($params['customField1']);
        }

        if (isset($params['customField2']) && !empty(trim($params['customField2']))) {
            $data['custom_field_2'] = trim($params['customField2']);
        }

        $this->mapDb->updateShipment($data, $params['smid'], $params['hdLastDate']);
        $dtsResponseDb = new Application_Model_DbTable_ResponseDts();
        $dtsResponseDb->updateResults($params);
        $testkitDb = new Application_Model_DbTable_TestkitnameDts();
        foreach ($params['avilableTestKit'] as $kit) {
            $kitId = "";
            if ($testkitDb->getDtsTestkitDetails($kit)) {
                $kitId = $kit;
            } else {
                $randomStr = $this->common->getRandomString(13);
                $testkitId = "tk" . $randomStr;
                $tkId = $testkitDb->checkTestkitId($testkitId, 'dts');
                $testkitDb->insert(array(
                    'TestKitName_ID'    => $tkId,
                    'TestKit_Name'      => $kit,
                    'scheme_type'       => 'dts',
                    'Approval'          => '0',
                    'CountryAdapted'    => '0',
                    'testkit_status'    => 'pending',
                    'Created_On'        => new Zend_Db_Expr('now()')
                ));
                $kitId = $tkId;
            }
            $db->insert('participant_testkit_map', array(
                "participant_id" => $params['participantId'],
                "shipment_id" => $params['shipmentId'],
                "testkit_id" => $kitId
            ));
        }
        $db->commit();
    }
}
