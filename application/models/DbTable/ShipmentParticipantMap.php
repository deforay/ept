<?php

class Application_Model_DbTable_ShipmentParticipantMap extends Zend_Db_Table_Abstract
{
    protected $_name = 'shipment_participant_map';
    protected $_primary = 'map_id';

    public function shipItNow($params)
    {

        try {
            $commonServices = new Application_Service_Common();
            $db = $this->getAdapter();
            $db->beginTransaction();
            $authNameSpace = new Zend_Session_Namespace('administrators');
            // Build a participant_id => map_id lookup from the existing
            // shipment_participant_map rows. fetchParticipantListByShipmentId
            // returns two GROUP_CONCAT strings in the same row order, so the
            // i-th participant id pairs with the i-th map id.
            $participantList = $this->fetchParticipantListByShipmentId($params['shipmentId']);
            $existingMap = [];
            if (!empty($participantList) && !empty($participantList['participantId']) && !empty($participantList['mapId'])) {
                $pIds = array_map('trim', explode(',', (string) $participantList['participantId']));
                $mIds = array_map('trim', explode(',', (string) $participantList['mapId']));
                foreach ($pIds as $i => $pid) {
                    if ($pid !== '' && isset($mIds[$i]) && $mIds[$i] !== '') {
                        $existingMap[(int) $pid] = (int) $mIds[$i];
                    }
                }
            }
            // Decode the JSON-encoded enrollment list posted by ship-it.phtml.
            $decoded = json_decode((string) ($params['selectedForEnrollment'] ?? ''), true);
            if (!is_array($decoded)) {
                $db->rollBack();
                $alertMsg = new Zend_Session_Namespace('alertSpace');
                $alertMsg->message = 'Invalid shipment payload. Please reload the page and try again.';
                return false;
            }
            $params['selectedForEnrollment'] = array_values(array_unique(array_filter(array_map('intval', $decoded))));
            // Remove participants previously mapped but no longer selected.
            // Do the delete inline within the existing transaction — calling
            // removeShipmentParticipant() here would open a second transaction,
            // which Zend_Db does not support ("There is already an active
            // transaction") and bubbled up as a generic "Shipping failed".
            $selectedSet = array_flip($params['selectedForEnrollment']);
            $mapIdsToRemove = [];
            foreach ($existingMap as $pid => $mid) {
                if (!isset($selectedSet[$pid])) {
                    $mapIdsToRemove[] = $mid;
                }
            }
            if (!empty($mapIdsToRemove)) {
                $idList = implode(',', array_map('intval', $mapIdsToRemove));
                $responseTables = ['response_result_dbs', 'response_result_dts', 'response_result_eid', 'response_result_recency', 'response_result_tb', 'response_result_vl'];
                $db->query('SET FOREIGN_KEY_CHECKS = 0');
                foreach ($responseTables as $tbl) {
                    $db->delete($tbl, 'shipment_map_id IN (' . $idList . ')');
                }
                $db->delete('shipment_participant_map', 'map_id IN (' . $idList . ')');
                $db->query('SET FOREIGN_KEY_CHECKS = 1');
            }
            foreach ($params['selectedForEnrollment'] as $participant) {
                $data = [
                    'shipment_id' => $params['shipmentId'],
                    'participant_id' => $participant,
                    'evaluation_status' => '19901190',
                    'created_by_admin' => $authNameSpace->admin_id,
                    'created_on_admin' => new Zend_Db_Expr('now()'),
                ];
                $commonServices->insertIgnore($this->_name, $data);
                // $this->insert($data);

                if (isset($params['listName']) && $params['listName'] != '' && (isset($params['showName']) && !empty($params['showName']) && $params['showName'] == 'yes')) {
                    $db = Zend_Db_Table_Abstract::getAdapter();
                    if (isset($params['participantList']) && $params['participantList'] != '') {
                        $ids = [];
                        foreach ($params['participantList'] as $d) {
                            $ids[] = base64_decode($d);
                        }
                        $exist = $db->fetchAll($db->select()->from(['eln' => 'enrollments'])
                            ->where('list_name IN ("' . implode('", "', $ids) . '") AND participant_id = ' . $participant));
                        if (isset($exist[0]['list_name']) && $exist[0]['list_name']) {
                            $db->delete('enrollments', 'list_name IN ("' . implode('", "', $ids) . '") AND participant_id IN(' . implode(',', $params['selectedForEnrollment']) . ')');
                        }
                        $db->insert('enrollments', [
                            'list_name' => $params['listName'],
                            'scheme_id' => $params['schemeId'],
                            'participant_id' => $participant,
                        ]);
                    } else {
                        $db->insert('enrollments', [
                            'list_name' => $params['listName'],
                            'scheme_id' => $params['schemeId'],
                            'participant_id' => $participant,
                        ]);
                    }
                }
            }

            $shipmentDb = new Application_Model_DbTable_Shipments();
            $shipmentDb->updateShipmentStatus($params['shipmentId'], 'ready');

            $shipmentRow = $shipmentDb->fetchRow('shipment_id=' . $params['shipmentId']);

            $resultSet = $shipmentDb->fetchAll($shipmentDb->select()->where("status = 'pending' AND distribution_id = " . $shipmentRow['distribution_id']));

            if (!empty($resultSet)) {
                $distroService = new Application_Service_Distribution();
                $distroService->updateDistributionStatus($shipmentRow['distribution_id'], 'configured');
            }
            /* New shipment mail alert start */
            $notParticipatedMailContent = $commonServices->getEmailTemplate('new_shipment');
            $subQuery = $this->select()
                ->from(['s' => 'shipment'], ['shipment_code', 'scheme_type'])
                ->join(['spm' => 'shipment_participant_map'], 'spm.shipment_id=s.shipment_id', ['map_id'])
                ->join(['pmm' => 'participant_manager_map'], 'pmm.participant_id=spm.participant_id', ['dm_id'])
                ->join(['p' => 'participant'], 'p.participant_id=pmm.participant_id', ['participantName' => new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameGroupConcatExpr('p'))])
                ->join(['dm' => 'data_manager'], 'pmm.dm_id=dm.dm_id', ['primary_email'])
                ->where('s.shipment_id=?', $shipmentRow['shipment_id'])
                ->group('dm.dm_id')->setIntegrityCheck(false);
            $subResult = $this->fetchAll($subQuery);
            foreach ($subResult as $dm) {
                $search = ['##NAME##', '##SHIPCODE##', '##SHIPTYPE##', '##SURVEYCODE##', '##SURVEYDATE##',];
                $replace = [$dm['participantName'], $dm['shipment_code'], $dm['scheme_type'], '', ''];
                $content = $notParticipatedMailContent['mail_content'];
                $message = str_replace($search, $replace, $content);
                $subject = $notParticipatedMailContent['mail_subject'];
                $fromEmail = $notParticipatedMailContent['mail_from'];
                $fromFullName = $notParticipatedMailContent['from_name'];
                $toEmail = $dm['primary_email'];
                $cc = $notParticipatedMailContent['mail_cc'];
                $bcc = $notParticipatedMailContent['mail_bcc'];
                $commonServices->insertTempMail($toEmail, $cc, $bcc, $subject, $message, $fromEmail, $fromFullName);
            }
            /* New shipment mail alert end */
            $this->getAdapter()->commit();

            $participantCount = is_array($params['selectedForEnrollment']) ? count($params['selectedForEnrollment']) : 0;
            $shipmentCode = $shipmentRow['shipment_code'] ?? "#{$params['shipmentId']}";
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog(
                "Shipped shipment - {$shipmentCode} ({$participantCount} participants)",
                'shipment'
            );

            return true;
        } catch (Throwable $e) {
            try {
                $this->getAdapter()->rollBack();
            } catch (Throwable $ignored) {
                // best-effort rollback
            }
            $traceId = 'ship-' . bin2hex(random_bytes(4));
            Pt_Commons_LoggerUtility::logError('shipItNow failed', [
                'trace_id' => $traceId,
                'shipment_id' => $params['shipmentId'] ?? null,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = 'Shipping failed. Please try again.';
            return false;
        }
    }

    public function updateShipment($params, $shipmentMapId, $lastDate)
    {
        try {
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            $commonService = new Application_Service_Common();

            $ipAddress = $commonService->getIPAddress();
            $operatingSystem = $commonService->getOperatingSystem($userAgent);
            $browser = $commonService->getBrowser($userAgent);

            $params['user_client_info'] = json_encode([
                'ip' => $ipAddress,
                'os' => $operatingSystem,
                'browser' => $browser,
            ]);

            $row = $this->fetchRow('map_id = ' . $shipmentMapId);
            if ($row != '') {
                if (trim($row['created_on_user']) == '' || $row['created_on_user'] == null) {
                    $this->update(['created_on_user' => new Zend_Db_Expr('now()')], 'map_id = ' . $shipmentMapId);
                }
            }

            $params['evaluation_status'] = $row['evaluation_status'];

            // changing evaluation status 3rd character to 1 = responded
            $params['evaluation_status'][2] = 1;

            // changing evaluation status 5th character to 1 = via web user
            $params['evaluation_status'][4] = 1;

            // changing evaluation status 4th character to 1 = timely response or 2 = delayed response

            // only if current date is LATER than last date we make status = 2
            $date = new DateTime();
            $lastDate = new DateTime($lastDate);

            // only if current date is LATER than last date we make status = 2
            if ($date > $lastDate) {
                $params['evaluation_status'][3] = 2;
            } else {
                $params['evaluation_status'][3] = 1;
            }
            $params['mode_of_response'] = 'web';
            return $this->update($params, 'map_id = ' . $shipmentMapId);
        } catch (Throwable $e) {
            // If any of the queries failed and threw an exception,
            // we want to roll back the whole transaction, reversing
            // changes made in the transaction, even those that succeeded.
            // Thus all changes are committed together, or none are.
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function removeShipmentMapDetails($params, $mapId)
    {
        $row = $this->fetchRow('map_id = ' . $mapId);
        if ($row != '') {
            if (trim($row['created_on_user']) == '' || $row['created_on_user'] == null) {
                $this->update(['created_on_user' => new Zend_Db_Expr('now()')], 'map_id = ' . $mapId);
            }
        }
        $params['evaluation_status'] = $row['evaluation_status'];
        // changing evaluation status 3rd character to 9 = not responded
        $params['evaluation_status'][2] = 9;

        // changing evaluation status 5th character to 1 = via web user
        $params['evaluation_status'][4] = 1;

        // changing evaluation status 4th character to 0 = no response
        $params['evaluation_status'][3] = 0;

        return $this->update($params, 'map_id = ' . $mapId);
    }

    public function isShipmentEditable($shipmentId, $participantId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $shipment = $db->fetchRow($db->select()->from(['s' => 'shipment'])
            ->where('s.shipment_id = ?', $shipmentId));
        // A cancelled shipment is locked exactly like a finalized one — no responses.
        if (!empty($shipment['cancelled_at'])) {
            return false;
        }
        if ((isset($shipment['status']) && $shipment['status'] == 'finalized') || (isset($shipment['response_switch']) && $shipment['response_switch'] == 'off')) {
            return false;
        } else {
            return true;
        }
    }

    public function addEnrollementDetails($params)
    {
        try {
            $this->getAdapter()->beginTransaction();
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $size = count($params['participants']);
            for ($i = 0; $i < $size; $i++) {
                $data = [
                    'shipment_id' => base64_decode($params['shipmentId']),
                    'participant_id' => base64_decode($params['participants'][$i]),
                    'evaluation_status' => '19901190',
                    'created_by_admin' => $authNameSpace->admin_id,
                    'created_on_admin' => new Zend_Db_Expr('now()'),
                ];
                $this->insert($data);
            }
            $this->getAdapter()->commit();
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = 'Participants added successfully';
        } catch (Throwable $e) {
            $this->getAdapter()->rollBack();
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    public function enrollShipmentParticipant($shipmentId, $participantId)
    {
        $insertCount = 0;
        try {
            $this->getAdapter()->beginTransaction();
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $participantId = explode(',', $participantId);
            $count = count($participantId);
            for ($i = 0; $i < $count; $i++) {
                $data = [
                    'shipment_id' => $shipmentId,
                    'participant_id' => base64_decode($participantId[$i]),
                    'evaluation_status' => '19901190',
                    'created_by_admin' => $authNameSpace->admin_id,
                    'created_on_admin' => new Zend_Db_Expr('now()'),
                ];
                $insertCount = $this->insert($data);
            }
            $this->getAdapter()->commit();
            return $insertCount;
        } catch (Throwable $e) {
            $this->getAdapter()->rollBack();
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 0;
        }
    }

    public function addQcInfo($params)
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (isset($params['mapId']) && trim($params['mapId']) != '') {
            $participantMapId = explode(',', $params['mapId']);
            $count = count($participantMapId);
            $qcDate = Pt_Commons_DateUtility::isoDateFormat($params['qcDate']);
            for ($i = 0; $i < $count; $i++) {
                if (trim($participantMapId[$i]) != '') {
                    $data = [
                        'qc_date' => $qcDate,
                        'qc_done_by' => $authNameSpace->dm_id,
                        'qc_created_on' => new Zend_Db_Expr('now()'),
                    ];
                    $result = $this->update($data, 'map_id = ' . $participantMapId[$i]);
                }
            }
            return $result;
        }
    }

    public function fetchParticipantShipments($pId)
    {
        $query = $this->getAdapter()->select()->distinct()->from(['sp' => 'shipment_participant_map'], ['shipment_id'])
            ->join(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', ['scheme_type', 'year' => 'YEAR(shipment_date)'])
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['p.unique_identifier', 'p.participant_id'])
            ->joinLeft(['cb' => 'certificate_batches'], 'sp.shipment_id IN(cb.shipment_ids)', ['batch_name'])
            ->where('sp.participant_id = ?', $pId)
            // ->where("s.scheme_type ='vl' OR s.scheme_type='eid'")
            ->where("sp.shipment_test_date!='0000-00-00'")
            ->group('year')
            ->group('s.scheme_type');
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $query = $query->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $authNameSpace->dm_id);
        }
        return $this->getAdapter()->fetchAll($query);
    }

    public function updateShipmentByAPI($data, $dm, $params)
    {
        $row = $this->fetchRow('map_id = ' . $params['mapId']);
        if ($row != '') {
            if (trim($row['created_on_user']) == '' || $row['created_on_user'] == null) {
                $this->update(['created_on_user' => new Zend_Db_Expr('now()')], 'map_id = ' . $params['mapId']);
            }
        }
        $data['shipment_id'] = $params['shipmentId'];
        $data['participant_id'] = $params['participantId'];
        $data['evaluation_status'] = $params['evaluationStatus'];
        $data['updated_by_user'] = $dm['dm_id'];
        if ($params['schemeType'] == 'dts') {
            $lastDate = $params['dtsData']->Section2->data->resultDueDate;
        }
        if ($params['schemeType'] == 'vl') {
            $lastDate = $params['vlData']->Section2->data->resultDueDate;
        }
        if ($params['schemeType'] == 'eid') {
            $lastDate = $params['eidData']->Section2->data->resultDueDate;
        }
        if ($params['schemeType'] == 'recency') {
            $lastDate = $params['recencyData']->Section2->data->resultDueDate;
        }
        if ($params['schemeType'] == 'covid19') {
            $lastDate = $params['covid19Data']->Section2->data->resultDueDate;
        }

        // changing evaluation status 3rd character to 1 = responded
        $data['evaluation_status'][2] = 1;

        // changing evaluation status 5th character to 1 = via web user
        $data['evaluation_status'][4] = 1;

        // changing evaluation status 4th character to 1 = timely response or 2 = delayed response
        $date = new DateTime();
        $lastDate = new DateTime($lastDate);

        // only if current date is LATER than last date we make status = 2
        if ($date > $lastDate) {
            $data['evaluation_status'][3] = 2;
        } else {
            $data['evaluation_status'][3] = 1;
        }
        $data['synced'] = 'yes';
        $data['synced_on'] = new Zend_Db_Expr('now()');
        $data['mode_of_response'] = 'app';

        return $this->update($data, 'map_id = ' . $params['mapId']);
    }

    public function fetchParticipantListByShipmentId($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['spm' => $this->_name], [
            'mapId' => new Zend_Db_Expr('GROUP_CONCAT(spm.map_id)'),
            'participantId' => new Zend_Db_Expr('GROUP_CONCAT(spm.participant_id)'),
        ])->where('spm.shipment_id = ' . $shipmentId)->group('spm.shipment_id');
        return $db->fetchRow($sql);
    }

    public function updateShipmentByAPIV2($data, $dm, $params)
    {
        try {
            $commonService = new Application_Service_Common();
            $row = $this->fetchRow('map_id = ' . $params['mapId']);
            if ($row != '') {
                if (trim($row['created_on_user']) == '' || $row['created_on_user'] == null) {
                    $this->update(['created_on_user' => new Zend_Db_Expr('now()')], 'map_id = ' . $params['mapId']);
                }
            }
            $data['shipment_id'] = $params['shipmentId'];
            $data['participant_id'] = $params['participantId'];
            $data['evaluation_status'] = $params['evaluationStatus'];
            $data['updated_by_user'] = $dm;
            $lastDate = $commonService->isoDateFormat($params['resultDueDate']);
            // changing evaluation status 3rd character to 1 = responded
            $data['evaluation_status'][2] = 1;

            // changing evaluation status 5th character to 1 = via web user
            $data['evaluation_status'][4] = 1;

            // changing evaluation status 4th character to 1 = timely response or 2 = delayed response
            $date = new DateTime();
            $lastDate = new DateTime($lastDate);

            // only if current date is LATER than last date we make status = 2
            if ($date > $lastDate) {
                $data['evaluation_status'][3] = 2;
            } else {
                $data['evaluation_status'][3] = 1;
            }
            $data['synced'] = 'yes';
            $data['synced_on'] = new Zend_Db_Expr('now()');
            $data['mode_of_response'] = 'app';
            return $this->update($data, 'map_id = ' . $params['mapId']);
        } catch (Throwable $e) {
            // If any of the queries failed and threw an exception,
            // we want to roll back the whole transaction, reversing
            // changes made in the transaction, even those that succeeded.
            // Thus all changes are committed together, or none are.
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
