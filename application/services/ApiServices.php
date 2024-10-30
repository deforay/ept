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
        $authToken = $params['authToken'];
        if (!isset($authToken)) {
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
        Zend_Debug::dump($authToken);
        // $aResult = $this->dataManagerDb->fetchAuthToken($authToken);
        // Zend_Debug::dump($aResult);
        die;
        /* Validate new auth token and app-version */
        if (!$aResult) {
            return array('status' => 'auth-fail', 'message' => 'Please check your credentials and try to log in again');
        }
        $response = [];
        foreach ($params['data'] as $key => $param) {
            $param = (array)$param;
            if (!$this->shipmentService->isShipmentEditable($param['shipmentId'], $param['participantId'])) {
                return array('status' => 'fail', 'message' => 'Responding for this shipment is not allowed at this time. Please contact your PT Provider for any clarifications..');
            }
            $mandatoryFields = array('receiptDate', 'testDate', 'sampleRehydrationDate', 'algorithm');
            $this->db->beginTransaction();

            $mandatoryCheckErrors = $this->shipmentService->mandatoryFieldsCheck($param, $mandatoryFields);
            if (count($mandatoryCheckErrors) > 0) {
                return array('status' => 'fail', 'message' => 'Please send the required Fields and sync the shipment data');
            }
            $attributes["sample_rehydration_date"] = Pt_Commons_General::isoDateFormat($param['sampleRehydrationDate'] ?? '');
            $attributes["algorithm"] = $param['algorithm'];
            if (isset($param['schemeType']) && !empty($param['schemeType']) && $param['schemeType'] == 'dts') {
                if (isset($param['conditionPtSamples']) && !empty($param['conditionPtSamples'])) {
                    $attributes["condition_pt_samples"] = (isset($param['conditionPtSamples']) && !empty($param['conditionPtSamples'])) ? $param['conditionPtSamples'] : '';
                    $attributes["refridgerator"] = (isset($param['refridgerator']) && !empty($param['refridgerator'])) ? $param['refridgerator'] : '';
                    $attributes["room_temperature"] = (isset($param['roomTemperature']) && !empty($param['roomTemperature'])) ? $param['roomTemperature'] : '';
                    $attributes["stop_watch"] = (isset($param['stopWatch']) && !empty($param['stopWatch'])) ? $param['stopWatch'] : '';
                }
                $attributes["dts_test_panel_type"] = $param['dtsTestPanelType'] ?? null;
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
                $data['is_pt_test_not_performed'] = null;
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
            Zend_Debug::dump($data);
            die;
            $shipmentUpdate = $this->mapDb->updateShipment($data, $param['mapId'], $param['resultDueDate']);
            $resultUpdate = $this->updateResults($param);
            if ($shipmentUpdate || $resultUpdate) {
                $response[$key]['status'] = 'success';
            } else {
                $response[$key]['status'] = 'fail';
            }
        }
        if (isset($response) && !empty($response)) {
            return array(
                'status' => 'success',
                'message'   => 'Shipment form saved successfully.'
            );
            $this->db->commit();
        } else {
            return array(
                'status' => 'fail',
                'message'   => 'Shipment form not saved. Please re-sync again'
            );
            $this->db->rollback();
        }
    }

    public function updateShipment($data, $shipmentMapId, $lastDate)
    {
        /* $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $ipAddress = $this->common->getIPAddress();
        $operatingSystem = $this->common->getOperatingSystem($userAgent);
        $browser = $this->common->getBrowser($userAgent);

        $data['user_client_info'] = json_encode(array(
            'ip' => $ipAddress,
            'os' => $operatingSystem,
            'browser' => $browser
        )); */

        $row = $this->mapDb->fetchRow("map_id = " . $shipmentMapId);
        if ($row != "") {
            if (trim($row['created_on_user']) == "" || $row['created_on_user'] == NULL) {
                $this->mapDb->update(array('created_on_user' => new Zend_Db_Expr('now()')), "map_id = " . $shipmentMapId);
            }
        }

        $data['evaluation_status'] = $row['evaluation_status'];

        // changing evaluation status 3rd character to 1 = responded
        $data['evaluation_status'][2] = 1;

        // only if current date is LATER than last date we make status = 2
        $date = new DateTime();
        $lastDate = new DateTime($lastDate);

        // only if current date is LATER than last date we make status = 2
        if ($date > $lastDate) {
            $data['evaluation_status'][3] = 2;
        } else {
            $data['evaluation_status'][3] = 1;
        }
        $data['mode_of_response'] = 'app';
        return $this->mapDb->update($data, "map_id = " . $shipmentMapId);
    }

    public function updateResults($params)
    {
        $status = false;
        // For HIV serology result updation
        if (isset($params['schemeType']) && !empty($params['schemeType']) && $params['schemeType'] == 'dts') {
            $responseDts = new Application_Model_DbTable_ResponseDts();
            $status = $responseDts->updateResultsByAPIV2($params);

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
                $this->db->insert('participant_testkit_map', array(
                    "participant_id" => $params['participantId'],
                    "shipment_id" => $params['shipmentId'],
                    "testkit_id" => $kitId
                ));
            }
        }
        return $status;
    }
}
