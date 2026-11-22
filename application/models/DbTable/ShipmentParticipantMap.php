<?php

class Application_Model_DbTable_ShipmentParticipantMap extends Zend_Db_Table_Abstract
{
    protected $_name = 'shipment_participant_map';
    protected $_primary = 'map_id';

    /** @var array<string, string>|null table => map-id column, cached per request */
    private static $childTables = null;

    /** @var string[]|null tables keyed by participant_id + shipment_id, cached per request */
    private static $pairTables = null;

    /**
     * Every table that stores data for one participant in one shipment,
     * with the column that holds the shipment_participant_map id. Read from
     * the schema, so new result tables are covered without a code change.
     *
     * @return array<string, string>
     */
    public static function childTables(Zend_Db_Adapter_Abstract $db): array
    {
        if (self::$childTables !== null) {
            return self::$childTables;
        }
        $rows = $db->fetchAll(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND COLUMN_NAME IN ('shipment_map_id', 'map_id')
               AND TABLE_NAME <> 'shipment_participant_map'"
        );
        $tables = [];
        foreach ($rows as $row) {
            $tables[(string) $row['TABLE_NAME']] = (string) $row['COLUMN_NAME'];
        }
        self::$childTables = $tables;
        return $tables;
    }

    /**
     * Tables that hold per-shipment participant data keyed by participant_id
     * and shipment_id instead of the map id, such as participant_testkit_map.
     *
     * @return string[]
     */
    public static function pairTables(Zend_Db_Adapter_Abstract $db): array
    {
        if (self::$pairTables !== null) {
            return self::$pairTables;
        }
        $mapKeyed = self::childTables($db);
        $tables = $db->fetchCol(
            "SELECT p.TABLE_NAME FROM information_schema.COLUMNS p
             JOIN information_schema.COLUMNS s
               ON s.TABLE_SCHEMA = p.TABLE_SCHEMA AND s.TABLE_NAME = p.TABLE_NAME AND s.COLUMN_NAME = 'shipment_id'
             WHERE p.TABLE_SCHEMA = DATABASE() AND p.COLUMN_NAME = 'participant_id'
               AND p.TABLE_NAME <> 'shipment_participant_map'"
        );
        self::$pairTables = array_values(array_filter(
            array_map('strval', $tables),
            static fn (string $table) => !isset($mapKeyed[$table])
        ));
        return self::$pairTables;
    }

    /**
     * Remove participants from shipments: their results, corrective actions,
     * feedback answers and other per-shipment rows first, then the map rows.
     * Runs inside the caller's transaction when there is one.
     *
     * Writes one audit entry per shipment listing the participants removed.
     *
     * @param int[] $mapIds
     * @param string $how where the removal came from, recorded in the audit entry
     * @return int number of shipment_participant_map rows deleted
     */
    public static function deleteMapsWithData(Zend_Db_Adapter_Abstract $db, array $mapIds, string $how = ''): int
    {
        $mapIds = array_values(array_unique(array_filter(array_map('intval', $mapIds))));
        if ($mapIds === []) {
            return 0;
        }
        $removed = $db->fetchAll(
            $db->select()
                ->from(['m' => 'shipment_participant_map'], ['map_id', 'shipment_id', 'response_status', 'shipment_test_report_date'])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id = m.shipment_id', ['shipment_code'])
                ->joinLeft(['p' => 'participant'], 'p.participant_id = m.participant_id', ['unique_identifier', 'lab_name', 'first_name', 'last_name'])
                ->where('m.map_id IN (?)', $mapIds)
        );
        $pairs = $db->fetchAll(
            $db->select()->from('shipment_participant_map', ['participant_id', 'shipment_id'])->where('map_id IN (?)', $mapIds)
        );
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (self::childTables($db) as $table => $column) {
                $db->delete($table, $db->quoteInto($db->quoteIdentifier($column) . ' IN (?)', $mapIds));
            }
            foreach (self::pairTables($db) as $table) {
                foreach ($pairs as $pair) {
                    $db->delete($table, [
                        'participant_id = ?' => (int) $pair['participant_id'],
                        'shipment_id = ?' => (int) $pair['shipment_id'],
                    ]);
                }
            }
            $deleted = (int) $db->delete('shipment_participant_map', $db->quoteInto('map_id IN (?)', $mapIds));
        } finally {
            $db->query('SET FOREIGN_KEY_CHECKS = 1');
        }
        self::auditRemoval($removed, $how);
        return $deleted;
    }

    /**
     * One audit entry per shipment for participants removed from it.
     *
     * @param array<int, array<string, mixed>> $removed
     */
    private static function auditRemoval(array $removed, string $how): void
    {
        $byShipment = [];
        foreach ($removed as $row) {
            $byShipment[(string) $row['shipment_id']][] = $row;
        }
        foreach ($byShipment as $shipmentId => $rows) {
            $code = (string) ($rows[0]['shipment_code'] ?? $shipmentId);
            $withResults = 0;
            $list = [];
            foreach ($rows as $row) {
                $name = trim((string) ($row['lab_name'] ?? ''));
                if ($name === '') {
                    $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                }
                $hadResults = in_array($row['response_status'] ?? '', ['responded', 'nottested'], true)
                    || !empty($row['shipment_test_report_date']);
                if ($hadResults) {
                    $withResults++;
                }
                $list[] = trim(($row['unique_identifier'] ?? '') . ' - ' . $name, ' -')
                    . ($hadResults ? ' (had submitted results)' : '');
            }
            $count = count($rows);
            $facts = [];
            if ($how !== '') {
                $facts['Removed through'] = $how;
            }
            $facts['Removed'] = $count;
            $facts['Had submitted results'] = $withResults;
            Application_Model_DbTable_AuditLog::record(
                sprintf('Removed %d participant%s from shipment %s', $count, $count === 1 ? '' : 's', $code),
                'shipment',
                [
                    'entity' => ['type' => 'shipment', 'id' => $shipmentId, 'label' => $code],
                    'facts' => $facts,
                    'list' => ['title' => 'Participants removed', 'rows' => $list],
                    'important' => $withResults > 0,
                    'reason' => $withResults > 0
                        ? sprintf('%d of the removed participants had submitted results, which were deleted', $withResults)
                        : '',
                ]
            );
        }
    }

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
                self::deleteMapsWithData($db, $mapIdsToRemove, 'Shipment participant list saved');
            }
            // "Save as a list": adds the selected participants to that list of this
            // scheme. It no longer removes anyone from the preset lists that were applied.
            $saveAsList = null;
            $listNameTaken = false;
            if (!empty($params['listName']) && ($params['showName'] ?? '') === 'yes') {
                $saveAsList = Application_Model_DbTable_Enrollments::normalizeListName($params['listName']);
                $enrollments = new Application_Model_DbTable_Enrollments();
                if (!$enrollments->listExists((string) $params['schemeId'], $saveAsList)
                    && $enrollments->listNameOwner($saveAsList, (string) $params['schemeId']) !== null) {
                    $listNameTaken = true;
                    $saveAsList = null;
                }
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

                if ($saveAsList !== null) {
                    $commonServices->insertIgnore('enrollments', [
                        'enrollment_id' => Pt_Commons_General::generateULID(),
                        'list_name' => $saveAsList,
                        'scheme_id' => $params['schemeId'],
                        'participant_id' => $participant,
                        'status' => 'enrolled',
                        'enrolled_on' => new Zend_Db_Expr('now()'),
                    ]);
                }
            }

            $shipmentDb = new Application_Model_DbTable_Shipments();
            $shipmentDb->updateShipmentStatus($params['shipmentId'], 'ready');

            $shipmentRow = $shipmentDb->fetchRow($shipmentDb->getAdapter()->quoteInto('shipment_id = ?', $params['shipmentId']));

            $resultSet = $shipmentDb->fetchAll($shipmentDb->select()->where("status = 'pending'")->where('distribution_id = ?', $shipmentRow['distribution_id']));

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
            if ($listNameTaken) {
                $alertMsg = new Zend_Session_Namespace('alertSpace');
                $alertMsg->message = Zend_Registry::get('translate')->_('Participants enrolled. The list was not saved because another scheme already has a list with this name. Each list name can be used in one scheme only.');
            }

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

            $row = $this->fetchRow(['map_id = ?' => $shipmentMapId]);
            if ($row != '') {
                if (trim($row['created_on_user']) == '' || $row['created_on_user'] == null) {
                    $this->update(['created_on_user' => new Zend_Db_Expr('now()')], ['map_id = ?' => $shipmentMapId]);
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
            return $this->update($params, ['map_id = ?' => $shipmentMapId]);
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
        $row = $this->fetchRow(['map_id = ?' => $mapId]);
        if ($row != '') {
            if (trim($row['created_on_user']) == '' || $row['created_on_user'] == null) {
                $this->update(['created_on_user' => new Zend_Db_Expr('now()')], ['map_id = ?' => $mapId]);
            }
        }
        $params['evaluation_status'] = $row['evaluation_status'];
        // changing evaluation status 3rd character to 9 = not responded
        $params['evaluation_status'][2] = 9;

        // changing evaluation status 5th character to 1 = via web user
        $params['evaluation_status'][4] = 1;

        // changing evaluation status 4th character to 0 = no response
        $params['evaluation_status'][3] = 0;

        return $this->update($params, ['map_id = ?' => $mapId]);
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
        return !((isset($shipment['status']) && $shipment['status'] == 'finalized') || (isset($shipment['response_switch']) && $shipment['response_switch'] == 'off'));
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
                    $result = $this->update($data, ['map_id = ?' => $participantMapId[$i]]);
                }
            }
            return $result;
        }
    }

    public function fetchParticipantShipments($pId)
    {
        // One row per certificate batch the participant took part in, plus one
        // per year/scheme with no batch. shipment_ids is a comma list, so it
        // needs FIND_IN_SET: IN() casts the string to its first id only.
        $query = $this->getAdapter()->select()
            ->from(['sp' => 'shipment_participant_map'], ['shipment_id' => new Zend_Db_Expr('MIN(sp.shipment_id)')])
            ->join(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', ['scheme_type', 'year' => new Zend_Db_Expr('MIN(YEAR(s.shipment_date))')])
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id', [
                'unique_identifier' => new Zend_Db_Expr('MAX(p.unique_identifier)'),
                'participant_id' => new Zend_Db_Expr('MAX(p.participant_id)'),
            ])
            // Only distributed batches: a rejected, failed or unapproved batch
            // has no certificate for the participant to download.
            ->joinLeft(['cb' => 'certificate_batches'], "FIND_IN_SET(sp.shipment_id, REPLACE(cb.shipment_ids, ' ', '')) > 0 AND cb.status = 'distributed'", ['batch_name' => new Zend_Db_Expr('MAX(cb.batch_name)')])
            ->where('sp.participant_id = ?', $pId)
            // A submitted response, the test generate-certificates.php uses.
            // Some forms (TB) never fill shipment_test_date.
            ->where("sp.shipment_test_report_date IS NOT NULL OR (sp.shipment_test_date IS NOT NULL AND sp.shipment_test_date != '0000-00-00')")
            ->where('s.cancelled_at IS NULL')
            ->group('s.scheme_type')
            ->group(new Zend_Db_Expr("COALESCE(CONCAT('batch-', cb.batch_id), YEAR(s.shipment_date))"));
        Application_Service_Common::scopeToSessionDataManager($query);
        $rows = $this->getAdapter()->fetchAll($query);

        // A year/scheme row without a batch only says "no certificate"; drop it
        // when a batch already covers that year and scheme.
        $batched = [];
        foreach ($rows as $row) {
            if (!empty($row['batch_name'])) {
                $batched[$row['year'] . '|' . $row['scheme_type']] = true;
            }
        }
        return array_values(array_filter(
            $rows,
            fn ($row) => !empty($row['batch_name']) || !isset($batched[$row['year'] . '|' . $row['scheme_type']])
        ));
    }

    public function updateShipmentByAPI($data, $dm, $params)
    {
        $row = $this->fetchRow(['map_id = ?' => $params['mapId']]);
        if ($row != '') {
            if (trim($row['created_on_user']) == '' || $row['created_on_user'] == null) {
                $this->update(['created_on_user' => new Zend_Db_Expr('now()')], ['map_id = ?' => $params['mapId']]);
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

        return $this->update($data, ['map_id = ?' => $params['mapId']]);
    }

    public function fetchParticipantListByShipmentId($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['spm' => $this->_name], [
            'mapId' => new Zend_Db_Expr('GROUP_CONCAT(spm.map_id)'),
            'participantId' => new Zend_Db_Expr('GROUP_CONCAT(spm.participant_id)'),
        ])->where('spm.shipment_id = ?', $shipmentId)->group('spm.shipment_id');
        return $db->fetchRow($sql);
    }

    public function updateShipmentByAPIV2($data, $dm, $params)
    {
        try {
            $commonService = new Application_Service_Common();
            $row = $this->fetchRow(['map_id = ?' => $params['mapId']]);
            if ($row != '') {
                if (trim($row['created_on_user']) == '' || $row['created_on_user'] == null) {
                    $this->update(['created_on_user' => new Zend_Db_Expr('now()')], ['map_id = ?' => $params['mapId']]);
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
            return $this->update($data, ['map_id = ?' => $params['mapId']]);
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

    public function fetchAllFinalResults()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from('r_results');
        return $db->fetchAll($sql);
    }
}
