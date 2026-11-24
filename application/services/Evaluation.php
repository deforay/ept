<?php

class Application_Service_Evaluation
{
    /**
     * Did this participant come back to us at all?
     *
     * 'nottested' counts: a lab that writes in to say it had no kits or nobody trained has
     * responded, it just has no results. An empty shipment_test_date is NOT this question --
     * the date is cleared on purpose for such a declaration, and a submission can carry
     * results with the date left blank.
     */
    private const SQL_RESPONDED = "(IFNULL(sp.response_status, 'noresponse') IN ('responded', 'late', 'nottested'))";

    /** The complement of self::SQL_RESPONDED, aggregated. Drafts count as no response. */
    private const SQL_DID_NOT_RESPOND = "SUM(IFNULL(sp.response_status, 'noresponse') NOT IN ('responded', 'late', 'nottested'))";

    /**
     * SQL condition: the participant gets an individual report. Report generation and
     * the job-tracking progress total both use it, so the two always agree.
     *
     * Default (flag OFF / absent): only those whose response_status = 'responded' and
     * whose submission survived evaluation. An excluded submission still carries
     * response_status 'responded', so is_excluded has to be checked too.
     *
     * Flag ON ('generate_reports_for_excluded' = 'yes'): every participant who actually
     * submitted something -- responded, late, entered a test date, or marked the PT test
     * as not performed -- even if it was later excluded from evaluation. Bare
     * non-responders (excluded with no submission of their own) still get no report.
     */
    public static function sqlGetsIndividualReport(string $mapAlias = 'sp'): string
    {
        $includeExcluded = strtolower((string) Application_Service_Common::getSetting('generate_reports_for_excluded', 'report')) === 'yes';
        if ($includeExcluded) {
            return "($mapAlias.response_status LIKE 'responded' OR $mapAlias.response_status LIKE 'late'"
                . " OR $mapAlias.shipment_test_date > '1970-01-01' OR IFNULL($mapAlias.is_pt_test_not_performed, 'no') = 'yes')";
        }

        return "($mapAlias.response_status IS NOT NULL AND $mapAlias.response_status LIKE 'responded'"
            . " AND IFNULL($mapAlias.is_excluded, 'no') = 'no')";
    }

    /**
     * SQL condition: the participant has submitted a response, whatever became of it
     * (on time, late, could not test, or a late submission awaiting approval). A draft
     * is not a submission. This is what "View" rather than "Enter Result" means in the
     * participant listings, and "response received" in reports.
     *
     * It replaces the third character of shipment_participant_map.evaluation_status,
     * which is no longer read. That code counted saved drafts as responses.
     */
    public static function sqlResponseSubmitted(string $mapAlias = 'sp'): string
    {
        return "(IFNULL($mapAlias.response_status, 'noresponse') NOT IN ('noresponse', 'draft', ''))";
    }

    /**
     * SQL condition: the participant submitted, after the shipment deadline. Judged
     * from the submission time, so it holds before the shipment is evaluated too.
     */
    public static function sqlResponseSubmittedLate(string $mapAlias = 'sp', string $shipmentAlias = 's'): string
    {
        $afterDeadline = Pt_Commons_DateUtility::sqlSubmittedAfterDeadline(
            "$mapAlias.shipment_test_report_date",
            "$shipmentAlias.response_deadline"
        );

        return '(' . self::sqlResponseSubmitted($mapAlias) . " AND IFNULL($afterDeadline, 0))";
    }

    /**
     * Fields that participants and data managers can type into, which report
     * layouts place in PDF HTML. Results, scores and unique_identifier stay
     * out: layouts compare results as numbers ("<20" would break), and the
     * identifier names the PDF files. Admin-written fields such as
     * evaluation comments may carry HTML on purpose and stay out too.
     */
    private const REPORT_TEXT_FIELDS = [
        'first_name', 'last_name', 'institute_name', 'department_name', 'lab_name', 'affiliation',
        'address', 'shipping_address', 'city', 'district', 'region', 'state', 'zip',
        'email', 'additional_email', 'phone', 'mobile', 'contact_name',
        'contact_person_name', 'contact_person_email', 'contact_person_telephone',
        'lab_director_name', 'lab_director_email', 'participant_supervisor', 'qc_done_by',
        'user_comment', 'pt_support_comments', 'pt_test_not_performed_comments',
        'custom_field_1', 'custom_field_2', 'other_assay', 'comment', 'comments', 'lab_comment',
        'additional_detail', 'lot_no_1', 'lot_no_2', 'lot_no_3',
        'repeat_lot_no_1', 'repeat_lot_no_2', 'repeat_lot_no_3',
    ];

    /**
     * Escapes REPORT_TEXT_FIELDS anywhere in report data, so text a participant
     * typed prints as text and cannot add tags, links or styling to a report.
     * Existing entities are kept, since some names are stored already escaped.
     */
    private static function escapeReportText(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::escapeReportText($value);
            } elseif (is_string($value) && in_array($key, self::REPORT_TEXT_FIELDS, true)) {
                $data[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
            }
        }
        return $data;
    }

    /**
     * Split a getResponseReports() row into the disjoint slices of the participation pie.
     *
     * Returns one entry per non-empty slice, in reading order, each with a stable key, its
     * count and its percentage of the shipment. Colour stays with the layout because the
     * country palettes differ. Labels come back in English; callers that translate run them
     * through their own translator, which is why the label and the key are separate.
     *
     * The counts partition the shipment, so the slices reconcile to total_shipped by
     * construction rather than by luck. "Not Yet Scored" only appears while a shipment still
     * has responders awaiting a verdict, so a finished round shows the familiar four.
     */
    public static function participationSlices(array $responseResult): array
    {
        $total = (int) ($responseResult['total_shipped'] ?? 0);
        if ($total <= 0) {
            return [];
        }

        $slices = [
            ['key' => 'passed', 'label' => 'Passed'],
            ['key' => 'failed', 'label' => 'Failed'],
            ['key' => 'no_response', 'label' => 'Not Responded'],
            ['key' => 'excluded', 'label' => 'Excluded'],
            ['key' => 'unscored', 'label' => 'Not Yet Scored'],
        ];

        $out = [];
        foreach ($slices as $slice) {
            $n = (int) ($responseResult['slice_' . $slice['key']] ?? 0);
            if ($n <= 0) {
                continue;
            }
            $slice['n'] = $n;
            $slice['pct'] = number_format(($n / $total) * 100, 2);
            $out[] = $slice;
        }

        return $out;
    }

    /**
     * Common "not a valid scored response" guard for scheme evaluate() loops.
     *
     * A participant who did not produce an on-time, testable response cannot pass or
     * fail — they are simply EXCLUDED from evaluation (final_result = 3). This is a
     * single rule shared by every scheme so they all behave identically. It covers:
     *
     *   - No response: response_status 'noresponse' / 'draft' / empty, or an empty
     *     result set (nothing to score).
     *   - Could not test: is_pt_test_not_performed = 'yes'.
     *   - Late response: submitted after the shipment cut-off — treated exactly like a
     *     non-response (excluded), and tagged response_status = 'late'. Skipped when the
     *     shipment's response window is explicitly switched on (response_switch = 'on').
     *
     * Historically only DTS excluded these; the other schemes fell through to scoring
     * and stamped final_result = 2 (Fail) on people who never validly tested, which
     * badly inflated the failed count and skewed pass rates. Centralising it here keeps
     * "late" and "no response" handled the same way across DTS/EID/VL/Covid19/TB/Recency
     * and custom tests.
     *
     * Call it at the top of each per-participant iteration. When it returns true it has
     * already written the exclusion (is_excluded = 'yes', final_result = 3, score 0, plus
     * response_status = 'late' for a late response) to the DB; the caller should then set
     * $shipment['is_excluded'] = 'yes' so its own in-memory bookkeeping (the
     * $shipmentResult entry used for report generation) takes the excluded path.
     *
     * This only ever runs during (re-)evaluation, so already-finalized shipments are
     * left untouched — the fix applies to ongoing and future evaluations only.
     *
     * @param Zend_Db_Adapter_Abstract $db       the scheme model's DB adapter
     * @param array                    $shipment  one shipment_participant_map row
     * @param array|null               $results   the participant's fetched sample results;
     *                                            pass null when the caller hasn't fetched
     *                                            them yet (then only response_status decides)
     * @return bool true if the participant was excluded (caller should treat as excluded)
     */
    public static function excludeNonResponder($db, array &$shipment, ?array $results = null): bool
    {
        // A response stamped 'late' by an earlier evaluation goes back to 'responded'
        // when it is on time now, e.g. after the deadline was extended. Evaluation is
        // the only writer of 'late', and the schemes score only 'responded' rows, so
        // without this a lab stays excluded however often the shipment is re-evaluated.
        self::restoreOnTimeResponse($db, $shipment);

        $responseStatus = strtolower(trim((string) ($shipment['response_status'] ?? '')));
        $notTested = strtolower(trim((string) ($shipment['is_pt_test_not_performed'] ?? ''))) === 'yes';

        $didNotParticipate = $responseStatus === ''
            || $responseStatus === 'noresponse'
            || $responseStatus === 'draft'
            // Empty result set only counts as non-participation when the caller
            // actually fetched and passed the results (null = "not fetched yet").
            || ($results !== null && count($results) === 0);

        // Late response = submitted after the cut-off. Excluded just like a non-response,
        // unless the response window is explicitly switched on for this shipment.
        $isLate = !$didNotParticipate && !$notTested && self::isLateResponse($shipment);

        if (!$didNotParticipate && !$notTested && !$isLate) {
            return false;
        }

        if (!empty($shipment['map_id'])) {
            $update = [
                'is_excluded'    => 'yes',
                'is_followup'    => 'yes',
                'final_result'   => 3, // Excluded — never Fail for a non-valid response
                'shipment_score' => 0,
            ];
            if ($isLate) {
                $update['is_response_late'] = 'yes';
                $update['response_status'] = 'late';
            }
            $db->update(
                'shipment_participant_map',
                $update,
                $db->quoteInto('map_id = ?', $shipment['map_id'])
            );
        }

        return true;
    }

    /**
     * Whether a response counts as late: submitted after the shipment cut-off while
     * the response window is not switched on.
     *
     * @param array $shipment one shipment_participant_map row joined to its shipment
     */
    public static function isLateResponse(array $shipment): bool
    {
        if (strtolower(trim((string) ($shipment['response_switch'] ?? ''))) === 'on') {
            return false;
        }

        return Pt_Commons_DateUtility::submittedAfterDeadline($shipment['shipment_test_report_date'] ?? null, $shipment['response_deadline'] ?? null);
    }

    /**
     * Put a response that an earlier evaluation stamped 'late' back to 'responded'
     * when it is no longer late, in the database and in $shipment.
     *
     * Only the plain 'late' status is touched. 'late_submitted' and 'late_rejected'
     * belong to the late-submission capture flow and keep their meaning.
     *
     * The exclusion is cleared too. The late stamp set it, and EID, COVID-19, Recency
     * and custom tests do not reset is_excluded before evaluating, so without this the
     * lab stays excluded and gets no report. The scheme's evaluation then applies any
     * exclusion that still holds. A manual exclusion an admin added to a response that
     * was also stamped late is cleared with it; TB, DTS and VL already clear manual
     * exclusions on every re-evaluation.
     *
     * @param Zend_Db_Adapter_Abstract $db
     * @param array                    $shipment one shipment_participant_map row, updated in place
     */
    public static function restoreOnTimeResponse($db, array &$shipment): void
    {
        if (strtolower(trim((string) ($shipment['response_status'] ?? ''))) !== 'late') {
            return;
        }
        // Dates only, not the response switch: the schemes' own late checks ignore the
        // switch, so a response restored on the switch alone would be neither scored
        // nor excluded and would keep its old result.
        if (Pt_Commons_DateUtility::submittedAfterDeadline($shipment['shipment_test_report_date'] ?? null, $shipment['response_deadline'] ?? null)) {
            return;
        }

        $restored = ['response_status' => 'responded', 'is_response_late' => 'no', 'is_excluded' => 'no'];
        $shipment = array_merge($shipment, $restored);
        if (!empty($shipment['map_id'])) {
            $db->update(
                'shipment_participant_map',
                $restored,
                $db->quoteInto('map_id = ?', $shipment['map_id'])
            );
        }
    }

    /**
     * Passing score (as a percentage) for a shipment's scheme.
     *
     * Different schemes carry it in different places:
     *  - User-configured ("custom") tests store their own passing score in
     *    user_test_config.passingScore — it varies per test, so it always wins.
     *  - Built-in schemes read <scheme>.passPercentage from SchemeConfig
     *    (the scheme_config table).
     * Falls back to 100 when neither is set.
     *
     * @param array $shipment shipment row carrying is_user_configured,
     *                        user_test_config and scheme_type
     */
    public static function getPassingScore(array $shipment): float
    {
        // Custom test: per-test passing score takes precedence.
        if (($shipment['is_user_configured'] ?? 'no') === 'yes' && !empty($shipment['user_test_config'])) {
            $cfg = is_array($shipment['user_test_config'])
                ? $shipment['user_test_config']
                : Pt_Commons_JsonUtility::safeDecode($shipment['user_test_config']);
            if (isset($cfg['passingScore']) && is_numeric($cfg['passingScore']) && $cfg['passingScore'] > 0) {
                return (float) $cfg['passingScore'];
            }
        }

        // Built-in scheme: <scheme>.passPercentage from SchemeConfig.
        $schemeType = trim((string) ($shipment['scheme_type'] ?? ''));
        if ($schemeType !== '') {
            $configured = Pt_Commons_SchemeConfig::get($schemeType . '.passPercentage');
            if (is_numeric($configured) && $configured > 0) {
                return (float) $configured;
            }
        }

        return 100.0;
    }

    /**
     * Shipments of a scheme whose stored scores were computed under the previous
     * settings, i.e. already evaluated (or had reports generated) but not finalized.
     * Used by the settings pages to nudge an admin to re-evaluate after a config change.
     *
     * @param string $schemeType scheme_type as stored on the shipment row
     *                           (e.g. 'dts', 'vl', 'covid19', or a custom-test code)
     * @return int[]
     */
    public function getReEvaluatableShipmentIds(string $schemeType): array
    {
        if ($schemeType === '') {
            return [];
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from('shipment', ['shipment_id'])
            ->where('scheme_type = ?', $schemeType)
            ->where('status IN (?)', ['evaluated', 'reports generated'])
            ->order('shipment_id ASC');

        return array_map('intval', $db->fetchCol($select));
    }

    /**
     * Shipments whose stored scores are out of date because a response was submitted
     * (created or edited) AFTER the shipment was last evaluated. Drives the "needs
     * re-evaluation" nudge on the Evaluate landing page. Finalized and cancelled
     * shipments are excluded; 'reports generated' shipments are included (re-evaluating
     * sends them back to 'evaluated'), and flagged via reports_generated so the view can
     * warn that their reports will need regenerating.
     *
     * @return array<int,array{shipment_id:int,shipment_code:string,scheme_type:string,reports_generated:bool}>
     */
    public function getShipmentsNeedingReEvaluation(): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $rows = $db->fetchAll(
            "SELECT s.shipment_id, s.shipment_code, s.scheme_type,
                    (s.reports_generated_at IS NOT NULL) AS reports_generated
               FROM shipment s
              WHERE s.evaluated_at IS NOT NULL
                AND s.finalized_at IS NULL
                AND s.cancelled_at IS NULL
                AND s.status IN ('evaluated', 'reports generated')
                AND EXISTS (
                    SELECT 1 FROM shipment_participant_map sp
                     WHERE sp.shipment_id = s.shipment_id
                       AND sp.response_status = 'responded'
                       AND (sp.updated_on_user > s.evaluated_at
                            OR sp.created_on_user > s.evaluated_at)
                )
              ORDER BY s.shipment_code"
        );
        foreach ($rows as &$r) {
            $r['shipment_id'] = (int) $r['shipment_id'];
            $r['reports_generated'] = (bool) $r['reports_generated'];
        }
        return $rows;
    }

    protected function setShipmentProcessingState($db, $shipmentId, array $shipmentResult)
    {
        $previousStatus = $shipmentResult[0]['shipment_status'];
        if (in_array($previousStatus, ['queued', 'processing'], true)) {
            $previousStatus = 'shipped';
        }
        $db->update('shipment', [
            'status' => 'processing',
            'previous_status' => $previousStatus,
            'processing_started_at' => new Zend_Db_Expr('NOW()'),
            'last_heartbeat' => new Zend_Db_Expr('NOW()'),
        ], "shipment_id = {$shipmentId}");
    }

    /**
     * Escape a plain-text value for a DataTables cell, which the grid renders as HTML.
     * Values already escaped when stored are not escaped twice.
     */
    private static function escapeCell($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8', false);
    }

    public function getAllDistributions($parameters)
    {
        $translator = Zend_Registry::get('translate');

        $aColumns = ["DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'distribution_code', 's.shipment_code', 'd.status'];
        $orderColumns = ['distribution_date', 'distribution_code', 's.shipment_code', 'd.status'];

        $sLimit = '';
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = '';
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = '';
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == 'true') {
                    $colIdx = intval($parameters['iSortCol_' . $i]);
                    if (!isset($orderColumns[$colIdx])) {
                        continue;
                    }
                    $sOrder .= $orderColumns[$colIdx] . '
					' . Pt_Commons_General::sanitizeSortDirection($parameters['sSortDir_' . $i]) . ', ';
                }
            }

            $sOrder = substr_replace($sOrder, '', -2);
        }

        $sWhere = '';
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != '') {
            $searchArray = explode(' ', $parameters['sSearch']);
            $sWhereSub = '';
            foreach ($searchArray as $search) {
                if ($sWhereSub == '') {
                    $sWhereSub .= '(';
                } else {
                    $sWhereSub .= ' AND (';
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($aColumns[$i] == '' || $aColumns[$i] == null) {
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' OR ';
                    } else {
                        $sWhereSub .= $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' ';
                    }
                }
                $sWhereSub .= ')';
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == 'true' && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == '') {
                    $sWhere .= $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($parameters['sSearch_' . $i]) . ' ';
                } else {
                    $sWhere .= ' AND ' . $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($parameters['sSearch_' . $i]) . ' ';
                }
            }
        }

        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sQuery = $dbAdapter->select()->from(['d' => 'distributions'])
            ->joinLeft(['s' => 'shipment'], 's.distribution_id=d.distribution_id', ['shipments' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT s.shipment_code SEPARATOR ', ')"), 'not_finalized_count' => new Zend_Db_Expr("SUM(IF(s.status!='finalized',1,0))")])
            ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', ['is_user_configured'])
            ->where("s.status!='finalized'")
            ->where('s.cancelled_at IS NULL')
            ->group('s.distribution_id');

        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->where($sWhere);
        }

        // Scheme-type filter (dropdown above the table). Narrows the listing to
        // distributions that contain a shipment of this scheme, and narrows the
        // GROUP_CONCAT of shipment codes to that scheme.
        $schemeType = isset($parameters['schemeType']) ? trim((string) $parameters['schemeType']) : '';
        if ($schemeType !== '') {
            $sQuery = $sQuery->where('s.scheme_type = ?', $schemeType);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $sQuery = $dbAdapter->select()->from(['temp' => $sQuery])->where('not_finalized_count>0');

        $rResult = $dbAdapter->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        //$sQuery = $dbAdapter->select()->from('distributions', new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"))->where("status='shipped'");
        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

        /*
         * Output
         */
        $output = [
            'sEcho' => isset($parameters['sEcho']) ? intval($parameters['sEcho']) : 0,
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        foreach ($rResult as $aRow) {

            $row = [];
            $row['DT_RowId'] = 'dist' . $aRow['distribution_id'];
            $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($aRow['distribution_date']);
            $row[] = self::escapeCell($aRow['distribution_code']);
            $row[] = self::escapeCell($aRow['shipments']);
            $row[] = $translator->_(ucwords($aRow['status'] ?? ''));
            $row[] = '<a class="btn btn-primary btn-xs" href="javascript:void(0);" onclick="getShipments(\'' . ($aRow['distribution_id']) . '\', \'' . $aRow['is_user_configured'] . '\')"><span><i class="icon-search"></i> View</span></a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    /**
     * Audit one finished shipment evaluation: the scoring settings the
     * evaluator read and the outcome. Flags the entry when the settings differ
     * from the previous evaluation of the same shipment, because the scores
     * are then not comparable. Called only after scoring runs, never on a
     * page view.
     *
     * @param array<string, string> $settings from evaluationSettings(), read before scoring
     */
    public function auditEvaluation(int $shipmentId, array $settings): void
    {
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $shipment = $db->fetchRow(
                $db->select()
                    ->from(['s' => 'shipment'], ['shipment_id', 'shipment_code'])
                    ->where('s.shipment_id = ?', $shipmentId)
            );
            if (empty($shipment)) {
                return;
            }
            $counts = $db->fetchRow(
                $db->select()
                    ->from('shipment_participant_map', [
                        'participants' => new Zend_Db_Expr('COUNT(*)'),
                        'responses' => new Zend_Db_Expr("SUM(response_status IN ('responded', 'nottested'))"),
                        'passed' => new Zend_Db_Expr('SUM(final_result = 1)'),
                    ])
                    ->where('shipment_id = ?', $shipmentId)
            );

            $code = (string) $shipment['shipment_code'];
            $auditLog = new Application_Model_DbTable_AuditLog();
            $previous = $auditLog->latestSnapshot('shipment', (string) $shipmentId, 'Evaluated shipment');
            $labels = Application_Model_DbTable_SchemeConfig::FIELD_LABELS;
            $changes = $previous !== null
                ? Application_Model_DbTable_AuditLog::changes($previous, $settings, $labels)
                : [];

            $facts = [
                'Participants' => (int) ($counts['participants'] ?? 0),
                'Responses' => (int) ($counts['responses'] ?? 0),
                'Passed' => (int) ($counts['passed'] ?? 0),
            ];
            foreach ($settings as $key => $value) {
                $facts[$labels[$key] ?? Application_Model_DbTable_AuditLog::humanize($key)] = $value;
            }

            $auditLog->addNewAuditLog('Evaluated shipment ' . $code, 'shipment', null, [
                'entity' => ['type' => 'shipment', 'id' => $shipmentId, 'label' => $code],
                'facts' => $facts,
                'snapshot' => $settings,
                'changes' => $changes !== [] ? $changes : null,
                'important' => $changes !== [],
                'reason' => $changes !== [] ? 'Evaluated with different settings than the previous evaluation of this shipment' : '',
            ]);
        } catch (Throwable $e) {
            // The audit must never fail an evaluation that already finished.
            Pt_Commons_LoggerUtility::logWarning('auditEvaluation failed: ' . $e->getMessage(), [
                'shipmentId' => $shipmentId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * The scoring settings an evaluation of this shipment reads: the scheme
     * settings, the shipment's own overrides and, for custom tests, the
     * test's configuration. Report text and display options are left out,
     * since they would flag evaluations that score the same.
     *
     * @param array<string, mixed> $shipment a shipment row with scheme_type and shipment_attributes
     * @return array<string, string>
     */
    private function evaluationSettings(array $shipment): array
    {
        try {
            $schemeType = (string) ($shipment['scheme_type'] ?? '');
            $settings = array_intersect_key(
                self::scalarSettings(Pt_Commons_SchemeConfig::get($schemeType)),
                array_flip(Application_Model_DbTable_SchemeConfig::SCORING_FIELDS)
            );
            // Shipment attributes override the scheme default for this shipment.
            $attributes = Pt_Commons_JsonUtility::safeDecode((string) ($shipment['shipment_attributes'] ?? ''));
            if (is_array($attributes)) {
                if (!empty($attributes['dtsSchemeType'])) {
                    $settings['dtsSchemeType'] = (string) $attributes['dtsSchemeType'];
                }
                foreach (['enableSyphilis', 'enableRtri'] as $key) {
                    if (isset($attributes[$key]) && is_scalar($attributes[$key])) {
                        $settings['shipment.' . $key] = (string) $attributes[$key];
                    }
                }
            }
            // Custom tests keep their scoring settings on the scheme itself.
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $custom = json_decode((string) $db->fetchOne(
                $db->select()->from('scheme_list', ['user_test_config'])->where('scheme_id = ?', $schemeType)
            ), true);
            foreach (self::scalarSettings(is_array($custom) ? $custom : null) as $key => $value) {
                $settings['customTest.' . $key] = $value;
            }
            ksort($settings);
            return $settings;
        } catch (Throwable $e) {
            Pt_Commons_LoggerUtility::logWarning('evaluationSettings failed: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return [];
        }
    }

    /**
     * Settings flattened to key => string, with lists joined. Nested objects
     * are skipped because evaluators read top-level keys.
     *
     * @return array<string, string>
     */
    private static function scalarSettings($config): array
    {
        if ($config instanceof Zend_Config) {
            $config = $config->toArray();
        }
        $out = [];
        if (!is_array($config)) {
            return $out;
        }
        foreach ($config as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $out[(string) $key] = (string) $value;
            } elseif (is_array($value) && array_is_list($value)) {
                $out[(string) $key] = implode(', ', array_map('strval', array_filter($value, 'is_scalar')));
            }
        }
        return $out;
    }

    public function getShipments($distributionId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['s' => 'shipment'])
            ->join(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_code', 'distribution_date'])
            ->join(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', ['map_id', 'responseDate' => 'shipment_test_report_date', 'report_generated', 'participant_count' => new Zend_Db_Expr('count("participant_id")'), 'reported_count' => new Zend_Db_Expr("SUM(response_status IN ('responded', 'nottested'))"), 'number_passed' => new Zend_Db_Expr('SUM(final_result = 1)'), 'last_not_participated_mailed_on', 'last_not_participated_mail_count', 'shipment_status' => 's.status'])
            ->join(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['scheme_name', 'is_user_configured'])
            ->joinLeft(['rr' => 'r_results'], 'sp.final_result=rr.result_id')
            ->where('s.distribution_id = ?', $distributionId)
            // Cancelling is the shipment soft-delete: a cancelled shipment has nothing
            // to evaluate, so it is hidden here as it is everywhere else.
            ->where('s.cancelled_at IS NULL')
            ->group('s.shipment_id');

        return $db->fetchAll($sql);
    }

    public function getResponseCount($shipmentId, $distributionId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['s' => 'shipment'], [''])
            ->join(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', [''])
            ->join(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', ['reported_count' => new Zend_Db_Expr("SUM(response_status IN ('responded', 'nottested'))")])
            ->join(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', [''])
            ->joinLeft(['rr' => 'r_results'], 'sp.final_result=rr.result_id', [''])
            ->where('s.shipment_id = ?', $shipmentId)
            //->where("sp.is_excluded!='yes' AND sp.is_pt_test_not_performed is NULL")
            ->where('s.distribution_id = ?', $distributionId)
            ->group('s.shipment_id');
        return $db->fetchRow($sql);
    }

    public function getShipmentToEvaluate($shipmentId, $reEvaluate = false, $override = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        // Update heartbeat
        $db->update(
            'shipment',
            ['last_heartbeat' => new Zend_Db_Expr('NOW()')],
            "shipment_id = {$shipmentId}"
        );

        $sql = $db->select()->from(['s' => 'shipment'], ['s.shipment_id', 's.shipment_code', 's.shipment_attributes', 's.scheme_type', 's.shipment_date', 's.response_deadline', 's.distribution_id', 's.number_of_samples', 's.response_switch', 's.max_score', 's.shipment_comment', 's.created_by_admin', 's.created_on_admin', 's.updated_by_admin', 's.updated_on_admin', 'shipment_status' => 's.status', 's.corrective_action_file'])
            ->join(['d' => 'distributions'], 'd.distribution_id=s.distribution_id')
            ->join(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id')
            ->join(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type')
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id')
            ->where('s.shipment_id = ?', $shipmentId);
        //->where("substring(sp.evaluation_status,4,1) != '0'");
        if (!empty($override)) {
            $sql = $sql->where('sp.manual_override = ?', $override);
        }
        $shipmentResult = $db->fetchAll($sql);

        $schemeService = new Application_Service_Schemes();

        // Each scheme branch below dispatches the heavy scoring only when $reEvaluate is true.
        // Evaluation is queue-driven: the worker (evaluate-shipments.php / generate-shipment-reports.php)
        // and the explicit Re-Evaluate action all pass $reEvaluate = true. The admin GET page load
        // passes false and is display-only — it must NOT trigger a synchronous re-score on every view.
        // (Historically this also OR-ed on $shipmentResult[0]['status'] == 'shipped', but the SELECT
        // aliases the shipment status as 'shipment_status'; the joined 'status' key actually resolves
        // to participant.status, so that term was always false and silently dead. Dropped to make the
        // queue-driven intent explicit.)
        // Read the scoring settings before scoring. The evaluators read the same
        // per-request cache, so the audit records exactly what they used.
        $auditSettings = $reEvaluate == true ? $this->evaluationSettings($shipmentResult[0]) : [];

        if ($shipmentResult[0]['scheme_type'] == 'eid') {
            if ($reEvaluate == true) {
                // Set processing state
                $this->setShipmentProcessingState($db, $shipmentId, $shipmentResult);
                $eidModel = new Application_Model_Eid();
                $shipmentResult = $eidModel->evaluate($shipmentResult, $shipmentId);
            }
        } elseif ($shipmentResult[0]['scheme_type'] == 'recency') {
            if ($reEvaluate == true) {
                // Set processing state
                $this->setShipmentProcessingState($db, $shipmentId, $shipmentResult);
                $recencyModel = new Application_Model_Recency($db);
                $shipmentResult = $recencyModel->evaluate($shipmentResult, $shipmentId);
            }
        } elseif ($shipmentResult[0]['scheme_type'] == 'dts') {
            if ($reEvaluate == true) {
                // Set processing state
                $this->setShipmentProcessingState($db, $shipmentId, $shipmentResult);
                $dtsModel = new Application_Model_Dts();
                $shipmentResult = $dtsModel->evaluate($shipmentResult, $shipmentId, $reEvaluate);
            }
        } elseif ($shipmentResult[0]['scheme_type'] == 'vl') {
            if ($reEvaluate == true) {
                // Set processing state
                $this->setShipmentProcessingState($db, $shipmentId, $shipmentResult);
                $vlModel = new Application_Model_Vl();
                $shipmentResult = $vlModel->evaluate($shipmentResult, $shipmentId, $reEvaluate);
            }
        } elseif ($shipmentResult[0]['scheme_type'] == 'covid19') {
            if ($reEvaluate == true) {
                // Set processing state
                $this->setShipmentProcessingState($db, $shipmentId, $shipmentResult);
                $covid19Model = new Application_Model_Covid19();
                $shipmentResult = $covid19Model->evaluate($shipmentResult, $shipmentId);
            }
        } elseif ($shipmentResult[0]['scheme_type'] == 'tb') {
            if ($reEvaluate == true) {
                // Set processing state
                $this->setShipmentProcessingState($db, $shipmentId, $shipmentResult);
                $tbModel = new Application_Model_Tb();
                $shipmentResult = $tbModel->evaluate($shipmentResult, $shipmentId);
            }
        } elseif ($shipmentResult[0]['scheme_type'] == 'generic-test' || $shipmentResult[0]['is_user_configured'] == 'yes') {
            if ($reEvaluate == true) {
                // Set processing state
                $this->setShipmentProcessingState($db, $shipmentId, $shipmentResult);
                $genericTestModel = new Application_Model_CustomTest();
                $shipmentResult = $genericTestModel->evaluate($shipmentResult, $shipmentId, $reEvaluate);
            }
        }
        if ($reEvaluate == true) {
            $this->auditEvaluation((int) $shipmentId, $auditSettings);
        }
        return $shipmentResult;
    }

    public function editEvaluation($shipmentId, $participantId, $scheme, $uc = 'no')
    {
        $participantService = new Application_Service_Participants();
        $schemeService = new Application_Service_Schemes();

        $participantData = $participantService->getParticipantDetails($participantId);
        $shipmentData = $schemeService->getShipmentData($shipmentId, $participantId);

        if ($scheme == 'eid') {
            $possibleResults = $schemeService->getPossibleResults('eid');
            $evalComments = $schemeService->getSchemeEvaluationComments('eid');
            $results = $schemeService->getEidSamples($shipmentId, $participantId);
        } elseif ($scheme == 'vl') {
            $possibleResults = '';
            $evalComments = $schemeService->getSchemeEvaluationComments('vl');
            $results = $schemeService->getVlSamples($shipmentId, $participantId);
        } elseif ($scheme == 'tb') {
            $possibleResults = '';
            $evalComments = $schemeService->getSchemeEvaluationComments('tb');
            $results = $schemeService->getTBSamples($shipmentId, $participantId);
        } elseif ($scheme == 'dts') {
            $dtsModel = new Application_Model_Dts();
            $possibleResults = $schemeService->getPossibleResults('dts');
            $evalComments = $schemeService->getSchemeEvaluationComments('dts');
            $results = $dtsModel->getDtsSamples($shipmentId, $participantId);
            $shipmentAttributes = isset($results[0]['shipment_attributes']) ? Pt_Commons_JsonUtility::safeDecode($results[0]['shipment_attributes']) : [];
            if (isset($shipmentAttributes['enableRtri']) && $shipmentAttributes['enableRtri'] == 'yes') {
                $possibleResults['recency'] = $schemeService->getPossibleResults('recency');
            }
        } elseif ($scheme == 'recency') {
            $possibleResults = $schemeService->getPossibleResults('recency');
            $evalComments = $schemeService->getSchemeEvaluationComments('recency');
            $results = $schemeService->getRecencySamples($shipmentId, $participantId);
        } elseif ($scheme == 'covid19') {
            $possibleResults = $schemeService->getPossibleResults('covid19');
            $evalComments = $schemeService->getSchemeEvaluationComments('covid19');
            $results = $schemeService->getCovid19Samples($shipmentId, $participantId);
        } elseif ($scheme == 'generic-test' || $uc == 'yes') {
            $evalComments = $schemeService->getSchemeEvaluationComments('generic-test');
            $results = $schemeService->getGenericSamples($shipmentId, $participantId);
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['s' => 'shipment'])
            ->join(['d' => 'distributions'], 'd.distribution_id=s.distribution_id')
            ->join(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', ['fullscore' => new Zend_Db_Expr('SUM(if(s.max_score = sp.shipment_score, 1, 0))')])
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id')
            ->where('sp.shipment_id = ?', $shipmentId)
            //->where("substring(sp.evaluation_status,4,1) != '0'")
            ->group('sp.map_id');
        $shipmentOverall = $db->fetchAll($sql);

        $noOfParticipants = count($shipmentOverall);
        $numScoredFull = $shipmentOverall[0]['fullscore'];
        $maxScore = $shipmentOverall[0]['max_score'];

        $controlRes = [];
        $sampleRes = [];
        if (isset($results) && !empty($results)) {
            foreach ($results as $res) {
                if ($res['control'] == 1) {
                    $controlRes[] = $res;
                } else {
                    $sampleRes[] = $res;
                }
            }
        }

        return [
            'participant' => $participantData,
            'shipment' => $shipmentData,
            'possibleResults' => (isset($possibleResults) && !empty($possibleResults)) ? $possibleResults : '',
            'totalParticipants' => $noOfParticipants,
            'fullScorers' => $numScoredFull,
            'maxScore' => $maxScore,
            'evalComments' => (isset($evalComments) && !empty($evalComments)) ? $evalComments : '',
            'controlResults' => $controlRes,
            'results' => $sampleRes,
        ];
    }

    public function viewEvaluation($shipmentId, $participantId, $scheme)
    {
        $participantService = new Application_Service_Participants();
        $schemeService = new Application_Service_Schemes();

        $participantData = $participantService->getParticipantDetails($participantId);
        $shipmentData = $schemeService->getShipmentData($shipmentId, $participantId);

        if ($scheme == 'eid') {
            $possibleResults = $schemeService->getPossibleResults('eid');
            $evalComments = $schemeService->getSchemeEvaluationComments('eid');
            $results = $schemeService->getEidSamples($shipmentId, $participantId);
        } elseif ($scheme == 'vl') {
            $possibleResults = '';
            $evalComments = $schemeService->getSchemeEvaluationComments('vl');
            $results = $schemeService->getVlSamples($shipmentId, $participantId);
        } elseif ($scheme == 'dts') {
            $dtsModel = new Application_Model_Dts();
            $possibleResults = $schemeService->getPossibleResults('dts');
            $evalComments = $schemeService->getSchemeEvaluationComments('dts');
            $results = $dtsModel->getDtsSamples($shipmentId, $participantId);
        } elseif ($scheme == 'recency') {
            $possibleResults = $schemeService->getPossibleResults('recency');
            $evalComments = $schemeService->getSchemeEvaluationComments('recency');
            $results = $schemeService->getRecencySamples($shipmentId, $participantId);
        } elseif ($scheme == 'covid19') {
            $possibleResults = $schemeService->getPossibleResults('covid19');
            $evalComments = $schemeService->getSchemeEvaluationComments('covid19');
            $results = $schemeService->getCovid19Samples($shipmentId, $participantId);
        }

        $controlRes = [];
        $sampleRes = [];

        if (isset($results) && !empty($results)) {
            foreach ($results as $res) {
                if ($res['control'] == 1) {
                    $controlRes[] = $res;
                } else {
                    $sampleRes[] = $res;
                }
            }
        }
        $dtsPasspercentage = Pt_Commons_SchemeConfig::get('dts.passPercentage') ?? 100;
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['s' => 'shipment'])
            ->join(['d' => 'distributions'], 'd.distribution_id=s.distribution_id')
            ->join(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', ['fullscore' => new Zend_Db_Expr('(if((sp.shipment_score+sp.documentation_score) >= ' . $db->quote($dtsPasspercentage) . ', 1, 0))')])
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id')
            ->where('sp.shipment_id = ?', $shipmentId)
            //->where("substring(sp.evaluation_status,4,1) != '0'")
            ->group('sp.map_id');

        $shipmentOverall = $db->fetchAll($sql);
        $noOfParticipants = count($shipmentOverall);
        $numScoredFull = 0;
        foreach ($shipmentOverall as $shipment) {
            $numScoredFull += $shipment['fullscore'];
        }

        return [
            'participant' => $participantData,
            'shipment' => $shipmentData,
            'possibleResults' => $possibleResults,
            'totalParticipants' => $noOfParticipants,
            'fullScorers' => $numScoredFull,
            'evalComments' => $evalComments,
            'controlResults' => $controlRes,
            'results' => $sampleRes,
        ];
    }

    public function updateShipmentResults($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $admin = $authNameSpace->admin_id;
            $size = count($params['sampleId']);
            $auditBefore = $this->responseEditSnapshot((string) ($params['scheme'] ?? ''), (int) ($params['smid'] ?? 0));

            $failureReason = [];
            /* Manual result override changes */
            if (isset($params['manualOverride']) && $params['manualOverride'] == 'yes') {
                $shipmentScore = ((isset($params['shipmentScore']) && $params['shipmentScore'] != '') ? $params['shipmentScore'] : 0);
                $docScore = ((isset($params['documentationScore']) && $params['documentationScore'] != '') ? $params['documentationScore'] : 0);
                if (isset($params['manualCorrective']) && $params['manualCorrective'] != '') {
                    $i = 0;
                    foreach ($params['manualCorrective'] as $warning => $correctiveAction) {
                        $failureReason[$i]['warning'] = $warning;
                        $failureReason[$i]['correctiveAction'] = $correctiveAction;
                        $i++;
                    }
                }
            }
            if ($params['scheme'] == 'eid') {

                $eidPassPercentage = Pt_Commons_SchemeConfig::get('eid.passPercentage') ?? 100;

                if (isset($params['extractionAssayOther']) && $params['extractionAssayOther'] != '') {
                    $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
                    $ifExist = $dbAdapter->fetchRow($dbAdapter->select()->from(['rea' => 'r_eid_extraction_assay'])->where('name LIKE ?', $params['extractionAssayOther'] . '%'));
                    if ($ifExist && $ifExist['name'] != '') {
                        $dbAdapter->update(
                            'r_eid_extraction_assay',
                            [
                                'name' => $params['extractionAssayOther'],
                                'status' => 'active',
                            ],
                            'id = ' . $ifExist['id']
                        );
                        $lastInsertAssayId = $ifExist['id'];
                    } else {
                        $dbAdapter->insert(
                            'r_eid_extraction_assay',
                            [
                                'name' => $params['extractionAssayOther'],
                                'status' => 'active',
                            ]
                        );
                        $lastInsertAssayId = $dbAdapter->lastInsertId();
                    }
                    $params['extractionAssay'] = $lastInsertAssayId;
                }

                $attributes = [
                    'sample_rehydration_date' => Pt_Commons_DateUtility::isoDateFormat($params['sampleRehydrationDate'] ?? null),
                    'extraction_assay' => $params['extractionAssay'] ?? null,
                    'detection_assay' => $params['detectionAssay'] ?? null,
                    'extraction_assay_expiry_date' => Pt_Commons_DateUtility::isoDateFormat($params['extractionAssayExpiryDate'] ?? null),
                    'detection_assay_expiry_date' => Pt_Commons_DateUtility::isoDateFormat($params['detectionAssayExpiryDate'] ?? null),
                    'extraction_assay_lot_no' => $params['extractionAssayLotNo'] ?? null,
                    'detection_assay_lot_no' => $params['detectionAssayLotNo'] ?? null,
                ];

                if (isset($params['otherAssay']) && $params['otherAssay'] != '') {
                    $attributes['other_assay'] = $params['otherAssay'];
                }
                if (isset($params['uploadedFilePath']) && $params['uploadedFilePath'] != '') {
                    $attributes['uploadedFilePath'] = $params['uploadedFilePath'];
                }

                $attributes = json_encode($attributes);
                $mapData = [
                    'shipment_receipt_date' => Pt_Commons_DateUtility::isoDateFormat($params['receiptDate']),
                    'attributes' => $attributes,
                    'mode_id' => $params['modeOfReceipt'],
                    'supervisor_approval' => $params['supervisorApproval'],
                    'participant_supervisor' => $params['participantSupervisor'],
                    'user_comment' => $params['userComments'],
                    'updated_by_admin' => $admin,
                    'updated_on_admin' => new Zend_Db_Expr('now()'),
                ];

                if (isset($params['testDate']) && trim($params['testDate']) != '') {
                    $mapData['shipment_test_date'] = Pt_Commons_DateUtility::isoDateFormat($params['testDate']);
                } else {
                    $mapData['shipment_test_date'] = new Zend_Db_Expr('now()');
                }

                if (isset($params['testReportedDate']) && trim($params['testReportedDate']) != '') {
                    $mapData['shipment_test_report_date'] = Pt_Commons_DateUtility::isoDateFormat($params['testReportedDate'], true);
                } else {
                    $mapData['shipment_test_report_date'] = Pt_Commons_DateUtility::getCurrentDateTime();
                }

                if (isset($params['customField1']) && trim($params['customField1']) != '') {
                    $mapData['custom_field_1'] = $params['customField1'];
                }

                if (isset($params['customField2']) && trim($params['customField2']) != '') {
                    $mapData['custom_field_2'] = $params['customField2'];
                }

                $db->update('shipment_participant_map', $mapData, ['map_id = ?' => $params['smid']]);
                $db->delete('response_result_eid', ['shipment_map_id = ?' => $params['smid']]);
                for ($i = 0; $i < $size; $i++) {

                    /* $db = Zend_Db_Table_Abstract::getDefaultAdapter();
                                                        $sql = $db->select()->from('response_result_eid')
                                                            ->where('shipment_map_id = ?', $params['smid'])->where('sample_id = ?', $params['sampleId'][$i]);
                                                        $respResult = $db->fetchRow($sql); */

                    /* if (false != $respResult) {
                                                            $resultData = array(
                                                                'reported_result' => $params['reported'][$i],
                                                                'updated_by' => $admin,
                                                                'updated_on' => new Zend_Db_Expr('now()')
                                                            );
                                                            $db->update('response_result_eid', $resultData, ['shipment_map_id = ?' => $params['smid'], 'sample_id = ?' => $params['sampleId'][$i]]);
                                                        } else { */
                    $resultData = [
                        'shipment_map_id' => $params['smid'],
                        'sample_id' => $params['sampleId'][$i],
                        'reported_result' => $params['reported'][$i],
                        'hiv_ct_od' => '',
                        'ic_qs' => '',
                        'created_by' => $admin,
                        'created_on' => new Zend_Db_Expr('now()'),
                    ];
                    $db->insert('response_result_eid', $resultData);
                    // }
                }
                /* Manual result override changes */
                if (isset($params['manualOverride']) && $params['manualOverride'] == 'yes') {
                    $grandTotal = ($shipmentScore + $docScore);
                    $finalResult = ($grandTotal < $eidPassPercentage) ? 2 : 1;
                }

                if (isset($params['labDirectorName']) && $params['labDirectorName'] != '') {
                    $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
                    /* Shipment Participant table updation */
                    $dbAdapter->update(
                        'shipment_participant_map',
                        [
                            'lab_director_name' => $params['labDirectorName'],
                            'lab_director_email' => $params['labDirectorEmail'],
                            'contact_person_name' => $params['contactPersonName'],
                            'contact_person_email' => $params['contactPersonEmail'],
                            'contact_person_telephone' => $params['contactPersonTelephone'],
                        ],
                        ['map_id = ?' => (int) $params['smid']]
                    );
                    /* Participant table updation */
                    $dbAdapter->update(
                        'participant',
                        [
                            'lab_director_name' => $params['labDirectorName'],
                            'lab_director_email' => $params['labDirectorEmail'],
                            'contact_person_name' => $params['contactPersonName'],
                            'contact_person_email' => $params['contactPersonEmail'],
                            'contact_person_telephone' => $params['contactPersonTelephone'],
                        ],
                        ['participant_id = ?' => (int) $params['participantId']]
                    );
                }
            } elseif ($params['scheme'] == 'dts') {

                $dtsPasspercentage = Pt_Commons_SchemeConfig::get('dts.passPercentage') ?? 100;
                $attributes['sample_rehydration_date'] = Pt_Commons_DateUtility::isoDateFormat($params['rehydrationDate']);
                $attributes['algorithm'] = $params['algorithm'];
                $attributes['condition_pt_samples'] = (isset($params['conditionOfPTSamples']) && !empty($params['conditionOfPTSamples'])) ? $params['conditionOfPTSamples'] : '';
                $attributes['refridgerator'] = (isset($params['refridgerator']) && !empty($params['refridgerator'])) ? $params['refridgerator'] : '';
                $attributes['room_temperature'] = (isset($params['roomTemperature']) && !empty($params['roomTemperature'])) ? $params['roomTemperature'] : '';
                $attributes['stop_watch'] = (isset($params['stopWatch']) && !empty($params['stopWatch'])) ? $params['stopWatch'] : '';
                $attributes = json_encode($attributes);

                $mapdata = [
                    'shipment_receipt_date' => Pt_Commons_DateUtility::isoDateFormat($params['receivedOn']),
                    'shipment_test_date' => Pt_Commons_DateUtility::isoDateFormat($params['testedOn']),
                    'attributes' => $attributes,
                    'supervisor_approval' => $params['supervisorApproval'],
                    'participant_supervisor' => $params['participantSupervisor'],
                    'user_comment' => $params['userComments'],
                    'updated_by_admin' => $admin,
                    'updated_on_admin' => new Zend_Db_Expr('now()'),
                ];
                if (isset($params['customField1']) && trim($params['customField1']) != '') {
                    $mapdata['custom_field_1'] = $params['customField1'];
                }

                if (isset($params['customField2']) && trim($params['customField2']) != '') {
                    $mapdata['custom_field_2'] = $params['customField2'];
                }
                $db->update('shipment_participant_map', $mapdata, ['map_id = ?' => $params['smid']]);

                for ($i = 0; $i < $size; $i++) {
                    $db->update('response_result_dts', [
                        'test_kit_name_1' => $params['test_kit_name_1'],
                        'lot_no_1' => $params['lot_no_1'],
                        'exp_date_1' => Pt_Commons_DateUtility::isoDateFormat($params['exp_date_1']),
                        'test_result_1' => $params['test_result_1'][$i],
                        'syphilis_result' => $params['syphilis_result'][$i],
                        'repeat_test_result_1' => $params['repeat_test_result_1'][$i],
                        'test_kit_name_2' => $params['test_kit_name_2'],
                        'lot_no_2' => $params['lot_no_2'],
                        'exp_date_2' => Pt_Commons_DateUtility::isoDateFormat($params['exp_date_2']),
                        'test_result_2' => $params['test_result_2'][$i],
                        'repeat_test_result_2' => $params['repeat_test_result_2'][$i],
                        'test_kit_name_3' => $params['test_kit_name_3'],
                        'lot_no_3' => $params['lot_no_3'],
                        'exp_date_3' => Pt_Commons_DateUtility::isoDateFormat($params['exp_date_3']),
                        'test_result_3' => $params['test_result_3'][$i],
                        'repeat_test_result_3' => $params['repeat_test_result_3'][$i],
                        'reported_result' => $params['reported_result'][$i],
                        'syphilis_final' => $params['syphilis_final'][$i],
                        'is_this_retest' => $params['is_this_retest'][$i],
                        'dts_rtri_control_line' => $params['controlLine'][$i],
                        'dts_rtri_diagnosis_line' => $params['verificationLine'][$i],
                        'dts_rtri_longterm_line' => $params['longtermLine'][$i],
                        'dts_rtri_reported_result' => $params['rtriResult'][$i],
                        'dts_rtri_is_editable' => $params['dtsRtriIsEditable'][$i],
                        'kit_additional_info' => json_encode($params['additionalInfoKit'][$i], true),
                        'updated_by' => $admin,
                        'updated_on' => new Zend_Db_Expr('now()'),
                    ], ['shipment_map_id = ?' => $params['smid'], 'sample_id = ?' => $params['sampleId'][$i]]);
                }
                /* Manual result override changes */
                if (isset($params['manualOverride']) && $params['manualOverride'] == 'yes') {
                    $grandTotal = number_format($shipmentScore + $docScore);
                    $finalResult = ($grandTotal < $dtsPasspercentage) ? 2 : 1;
                }
            } elseif ($params['scheme'] == 'vl') {

                $shipmentService = new Application_Service_Shipments();
                // $mandatoryFields = array('receiptDate', 'testDate', 'vlAssay', 'assayExpirationDate', 'assayLotNumber');
                $mandatoryFields = ['receiptDate', 'testDate'];
                $mandatoryCheckErrors = $shipmentService->mandatoryFieldsCheck($params, $mandatoryFields);

                // "Other" (id 6) requires the participant to name the platform; without this
                // the report prints a bare "Other" with nothing to identify what was actually used.
                // Skipped, like the checks above, when the PT test wasn't performed at all.
                if (
                    empty($mandatoryCheckErrors)
                    && (empty($params['isPtTestNotPerformed']) || $params['isPtTestNotPerformed'] !== 'yes')
                    && (int) ($params['vlAssay'] ?? 0) === 6
                    && trim((string) ($params['otherAssay'] ?? '')) === ''
                ) {
                    $mandatoryCheckErrors[] = 'otherAssay';
                }

                if (!empty($mandatoryCheckErrors)) {

                    //throw new Exception('Missed mandatory fields - ' . implode(",", $mandatoryCheckErrors));

                    return false;
                }

                $attributes = [
                    'sample_rehydration_date' => Pt_Commons_DateUtility::isoDateFormat($params['sampleRehydrationDate'] ?? null),
                    'vl_assay' => (isset($params['vlAssay']) && !empty($params['vlAssay'])) ? (int) $params['vlAssay'] : null,
                    'assay_lot_number' => $params['assayLotNumber'] ?? null,
                    'assay_expiration_date' => Pt_Commons_DateUtility::isoDateFormat($params['assayExpirationDate'] ?? null),
                    'specimen_volume' => $params['specimenVolume'] ?? null,
                ];

                if (isset($params['otherAssay']) && $params['otherAssay'] != '') {
                    $attributes['other_assay'] = $params['otherAssay'];
                }
                if (isset($params['uploadedFilePath']) && $params['uploadedFilePath'] != '') {
                    $attributes['uploadedFilePath'] = $params['uploadedFilePath'];
                }

                $attributes = json_encode($attributes);
                $mapData = [
                    'shipment_receipt_date' => Pt_Commons_DateUtility::isoDateFormat($params['receiptDate']),
                    'shipment_test_date' => Pt_Commons_DateUtility::isoDateFormat($params['testDate']),
                    'attributes' => $attributes,
                    'mode_id' => $params['modeOfReceipt'],
                    'supervisor_approval' => $params['supervisorApproval'],
                    'participant_supervisor' => $params['participantSupervisor'],
                    'user_comment' => $params['userComments'],
                    'updated_by_admin' => $admin,
                    'updated_on_admin' => new Zend_Db_Expr('now()'),
                ];

                if (isset($params['testReportedDate']) && trim($params['testReportedDate']) != '') {
                    $mapData['shipment_test_report_date'] = Pt_Commons_DateUtility::isoDateFormat($params['testReportedDate'], true);
                } else {
                    $mapData['shipment_test_report_date'] = Pt_Commons_DateUtility::getCurrentDateTime();
                }

                if (isset($params['customField1']) && trim($params['customField1']) != '') {
                    $mapData['custom_field_1'] = $params['customField1'];
                }

                if (isset($params['customField2']) && trim($params['customField2']) != '') {
                    $mapData['custom_field_2'] = $params['customField2'];
                }

                $db->update('shipment_participant_map', $mapData, ['map_id = ?' => $params['smid']]);
                $db->delete('response_result_vl', ['shipment_map_id = ?' => $params['smid']]);
                /* $shipmentOverall = $db->fetchRow($db->select()->from('response_result_vl')
                                           ->where("shipment_map_id = ?", $params['smid'])); */
                $resVlDb = new Application_Model_DbTable_ResponseVl();

                for ($i = 0; $i < $size; $i++) {
                    // if (!$shipmentOverall) {
                    $resData = [
                        'shipment_map_id' => $params['smid'],
                        'vl_assay' => (isset($params['vlAssay']) && !empty($params['vlAssay'])) ? (int) $params['vlAssay'] : null,
                        'sample_id' => $params['sampleId'][$i],
                        'reported_viral_load' => $params['reported'][$i],
                        'created_by' => $admin,
                        'created_on' => new Zend_Db_Expr('now()'),
                        'updated_by' => $admin,
                        'updated_on' => new Zend_Db_Expr('now()'),
                    ];
                    $resVlDb->insert($resData);
                    // } else {
                    // 	$resData = array(
                    // 		'reported_viral_load'	=> $params['reported'][$i],
                    // 		'updated_by' 			=> $admin,
                    // 		'updated_on' 			=> new Zend_Db_Expr('now()')
                    // 	);
                    // 	$id = $resVlDb->update($resData, "shipment_map_id = " . $params['smid'] . " AND sample_id = " . $params['sampleId'][$i]);
                    // }
                }
                /* Manual result override changes */
                if (isset($params['manualOverride']) && $params['manualOverride'] == 'yes') {

                    // Judge the override against VL's pass mark, as the EID and DTS overrides do.
                    // The shipment's stored maximum is not on the score's scale on older rounds.
                    $vlPassPercentage = Pt_Commons_SchemeConfig::get('vl.passPercentage') ?? 100;
                    $grandTotal = ($shipmentScore + $docScore);
                    $finalResult = ($grandTotal < $vlPassPercentage) ? 2 : 1;
                }
            } elseif ($params['scheme'] == 'recency') {

                $recencyPassPercentage = Pt_Commons_SchemeConfig::get('recency.passPercentage') ?? 100;
                $attributes['sample_rehydration_date'] = Pt_Commons_DateUtility::isoDateFormat($params['rehydrationDate']);
                $attributes['algorithm'] = $params['algorithm'];
                $attributes = [
                    'sample_rehydration_date' => Pt_Commons_DateUtility::isoDateFormat($params['sampleRehydrationDate'] ?? null),
                    'recency_assay' => $params['recencyAssay'] ?? null,
                    'recency_assay_lot_no' => $params['recencyAssayLotNo'] ?? null,
                    'recency_assay_expiry_date' => Pt_Commons_DateUtility::isoDateFormat($params['recencyAssayExpiryDate'] ?? null),
                ];

                $attributes = json_encode($attributes);
                $mapdata = [
                    'shipment_receipt_date' => Pt_Commons_DateUtility::isoDateFormat($params['receiptDate']),
                    'shipment_test_date' => Pt_Commons_DateUtility::isoDateFormat($params['testedOn']),
                    'attributes' => $attributes,
                    'supervisor_approval' => $params['supervisorApproval'],
                    'participant_supervisor' => $params['participantSupervisor'],
                    'user_comment' => $params['userComments'],
                    'updated_by_admin' => $admin,
                    'updated_on_admin' => new Zend_Db_Expr('now()'),
                ];
                if (isset($params['customField1']) && trim($params['customField1']) != '') {
                    $mapdata['custom_field_1'] = $params['customField1'];
                }

                if (isset($params['customField2']) && trim($params['customField2']) != '') {
                    $mapdata['custom_field_2'] = $params['customField2'];
                }
                $db->update('shipment_participant_map', $mapdata, ['map_id = ?' => $params['smid']]);

                for ($i = 0; $i < $size; $i++) {
                    $db->update('response_result_recency', [
                        'reported_result' => $params['reported_result'][$i],
                        'control_line' => $params['controlLine'][$i],
                        'diagnosis_line' => $params['verificationLine'][$i],
                        'longterm_line' => $params['longtermLine'][$i],
                        'updated_by' => $admin,
                        'updated_on' => new Zend_Db_Expr('now()'),
                    ], ['shipment_map_id = ?' => (int) $params['smid'], 'sample_id = ?' => $params['sampleId'][$i]]);
                }
                /* Manual result override changes */
                if (isset($params['manualOverride']) && $params['manualOverride'] == 'yes') {
                    $grandTotal = ($shipmentScore + $docScore);
                    if ($grandTotal < $recencyPassPercentage) {
                        $finalResult = 2;
                    } else {
                        $finalResult = 1;
                    }
                }
            } elseif ($params['scheme'] == 'covid19') {
                $covid19PassPercentage = Pt_Commons_SchemeConfig::get('covid19.passPercentage') ?? 100;
                $attributes['sample_rehydration_date'] = Pt_Commons_DateUtility::isoDateFormat($params['rehydrationDate']);
                // $attributes["algorithm"] = $params['algorithm'];
                $attributes = json_encode($attributes);

                $mapdata = [
                    'shipment_receipt_date' => Pt_Commons_DateUtility::isoDateFormat($params['receivedOn']),
                    'shipment_test_date' => Pt_Commons_DateUtility::isoDateFormat($params['testedOn']),
                    'attributes' => $attributes,
                    'supervisor_approval' => $params['supervisorApproval'],
                    'participant_supervisor' => $params['participantSupervisor'],
                    'number_of_tests' => $params['numberOfParticipantTest'],
                    'specimen_volume' => $params['specimenVolume'],
                    'user_comment' => $params['userComments'],
                    'updated_by_admin' => $admin,
                    'updated_on_admin' => new Zend_Db_Expr('now()'),
                ];
                if (isset($params['customField1']) && trim($params['customField1']) != '') {
                    $mapdata['custom_field_1'] = $params['customField1'];
                }

                if (isset($params['customField2']) && trim($params['customField2']) != '') {
                    $mapdata['custom_field_2'] = $params['customField2'];
                }
                $db->update('shipment_participant_map', $mapdata, ['map_id = ?' => $params['smid']]);

                for ($i = 0; $i < $size; $i++) {
                    $db->update('response_result_covid19', [
                        'test_type_1' => $params['test_type_1'],
                        'lot_no_1' => $params['lot_no_1'],
                        'exp_date_1' => Pt_Commons_DateUtility::isoDateFormat($params['exp_date_1']),
                        'test_result_1' => $params['test_result_1'][$i],
                        'test_type_2' => $params['test_type_2'],
                        'lot_no_2' => $params['lot_no_2'],
                        'exp_date_2' => Pt_Commons_DateUtility::isoDateFormat($params['exp_date_2']),
                        'test_result_2' => $params['test_result_2'][$i],
                        'test_type_3' => $params['test_type_3'],
                        'lot_no_3' => $params['lot_no_3'],
                        'exp_date_3' => Pt_Commons_DateUtility::isoDateFormat($params['exp_date_3']),
                        'test_result_3' => $params['test_result_3'][$i],

                        'name_of_pcr_reagent_1' => $params['name_of_pcr_reagent_1'],
                        'name_of_pcr_reagent_2' => (isset($params['numberOfParticipantTest']) && $params['numberOfParticipantTest'] >= 2) ? $params['name_of_pcr_reagent_2'] : null,
                        'name_of_pcr_reagent_3' => (isset($params['numberOfParticipantTest']) && $params['numberOfParticipantTest'] >= 3) ? $params['name_of_pcr_reagent_3'] : null,

                        'pcr_reagent_lot_no_1' => $params['pcr_reagent_lot_no_1'],
                        'pcr_reagent_lot_no_2' => (isset($params['numberOfParticipantTest']) && $params['numberOfParticipantTest'] >= 2) ? $params['pcr_reagent_lot_no_2'] : null,
                        'pcr_reagent_lot_no_3' => (isset($params['numberOfParticipantTest']) && $params['numberOfParticipantTest'] >= 3) ? $params['pcr_reagent_lot_no_3'] : null,

                        'pcr_reagent_exp_date_1' => Pt_Commons_DateUtility::isoDateFormat($params['pcr_reagent_exp_date_1']),
                        'pcr_reagent_exp_date_2' => (isset($params['numberOfParticipantTest']) && $params['numberOfParticipantTest'] >= 2) ? Pt_Commons_DateUtility::isoDateFormat($params['pcr_reagent_exp_date_2']) : null,
                        'pcr_reagent_exp_date_3' => (isset($params['numberOfParticipantTest']) && $params['numberOfParticipantTest'] >= 3) ? Pt_Commons_DateUtility::isoDateFormat($params['pcr_reagent_exp_date_3']) : null,

                        'reported_result' => $params['reported_result'][$i],
                        'updated_by' => $admin,
                        'updated_on' => new Zend_Db_Expr('now()'),
                    ], ['shipment_map_id = ?' => $params['smid'], 'sample_id = ?' => $params['sampleId'][$i]]);
                }

                /* Save Gene Type */
                $geneIdentifyTypesDb = new Application_Model_DbTable_Covid19IdentifiedGenes();
                $geneIdentifyTypesDb->saveCovid19IdentifiedGenesResults($params);
                /* Manual result override changes */
                if (isset($params['manualOverride']) && $params['manualOverride'] == 'yes') {
                    $grandTotal = $shipmentScore + $docScore;
                    if ($grandTotal < $covid19PassPercentage) {
                        $finalResult = 2;
                    } else {
                        $finalResult = 1;
                    }
                }
            } elseif ($params['scheme'] == 'tb') {

                $attributes = [
                    'sample_rehydration_date' => Pt_Commons_DateUtility::isoDateFormat($params['sampleRehydrationDate'] ?? null),
                    'assay_name' => $params['assayName'] ?? null,
                    'other_assay_name' => $params['otherAssayName'] ?? null,
                    'assay_lot_number' => $params['assayLot'] ?? null,
                    'mtb_rif_kit_lot_no' => $params['mtbRifKitLotNo'] ?? null,
                    'expiry_date' => $params['expiryDate'] ?? null,
                    'attestation' => $params['attestation'] ?? null,
                    'attestation_statement' => $params['attestationStatement'] ?? null,
                ];
                $attributes = json_encode($attributes);
                $mapData = [
                    'shipment_receipt_date' => (isset($params['receiptDate']) && !empty($params['receiptDate'])) ? Pt_Commons_DateUtility::isoDateFormat($params['receiptDate']) : '',
                    'attributes' => $attributes,
                    //"shipment_test_report_date" => new Zend_Db_Expr('now()'),
                    'supervisor_approval' => $params['supervisorApproval'],
                    'participant_supervisor' => $params['participantSupervisor'],
                    'user_comment' => $params['userComments'],
                    'mode_id' => (isset($params['modeOfReceipt']) && !empty($params['modeOfReceipt'])) ? $params['modeOfReceipt'] : '',
                    'updated_by_user' => $authNameSpace->dm_id,
                    'updated_on_user' => new Zend_Db_Expr('now()'),
                ];

                if (isset($params['testDate']) && trim($params['testDate']) != '') {
                    $mapData['shipment_test_date'] = Pt_Commons_DateUtility::isoDateFormat($params['testDate']);
                } else {
                    $mapData['shipment_test_date'] = new Zend_Db_Expr('now()');
                }

                if (isset($params['testReportedDate']) && trim($params['testReportedDate']) != '') {
                    $mapData['shipment_test_report_date'] = Pt_Commons_DateUtility::isoDateFormat($params['testReportedDate'], true);
                } else {
                    $mapData['shipment_test_report_date'] = Pt_Commons_DateUtility::getCurrentDateTime();
                }

                if (isset($params['customField1']) && trim($params['customField1']) != '') {
                    $mapData['custom_field_1'] = $params['customField1'];
                }

                if (isset($params['customField2']) && trim($params['customField2']) != '') {
                    $mapData['custom_field_2'] = $params['customField2'];
                }

                $db->update('shipment_participant_map', $mapData, ['map_id = ?' => $params['smid']]);
                $db->delete('response_result_tb', ['shipment_map_id = ?' => $params['smid']]);
                for ($i = 0; $i < $size; $i++) {
                    $resultData = [
                        'shipment_map_id' => $params['smid'],
                        'sample_id' => $params['sampleId'][$i],
                        'mtb_detected' => $params['mtbcDetected'][$i],
                        'rif_resistance' => $params['rifResistance'][$i],
                        'probe_d' => $params['probeD'][$i],
                        'probe_c' => $params['probeC'][$i],
                        'probe_e' => $params['probeE'][$i],
                        'probe_b' => $params['probeB'][$i],
                        'probe_a' => $params['probeA'][$i],
                        'is1081_is6110' => (isset($params['ISI'][$i]) && !empty($params['ISI'][$i])) ? $params['ISI'][$i] : null,
                        'rpo_b1' => (isset($params['rpoB1'][$i]) && !empty($params['rpoB1'][$i])) ? $params['rpoB1'][$i] : null,
                        'rpo_b2' => (isset($params['rpoB2'][$i]) && !empty($params['rpoB2'][$i])) ? $params['rpoB2'][$i] : null,
                        'rpo_b3' => (isset($params['rpoB3'][$i]) && !empty($params['rpoB3'][$i])) ? $params['rpoB3'][$i] : null,
                        'rpo_b4' => (isset($params['rpoB4'][$i]) && !empty($params['rpoB4'][$i])) ? $params['rpoB4'][$i] : null,
                        'test_date' => Pt_Commons_DateUtility::isoDateFormat($params['dateTested'][$i]),
                        'tester_name' => $params['testerName'][$i],
                        'error_code' => $params['errCode'][$i],
                        'created_by' => $admin,
                        'created_on' => new Zend_Db_Expr('now()'),
                    ];
                    /* Check if assay xpert or ultra */
                    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
                    $sQuery = $db->select()->from('r_tb_assay', 'short_name')->where('id = ?', $params['assayName']);
                    $assayName = $db->fetchRow($sQuery);
                    if (isset($assayName['short_name']) && !empty($assayName['short_name']) && $assayName['short_name'] == 'xpert-mtb-rif') {
                        $resultData['spc_xpert'] = $params['spc'] ?? null;
                    } elseif (isset($assayName['short_name']) && !empty($assayName['short_name']) && $assayName['short_name'] == 'xpert-mtb-rif-ultra') {
                        $resultData['spc_xpert_ultra'] = $params['spc'] ?? null;
                    }
                    $db->insert('response_result_tb', $resultData);
                }
            } elseif ($params['scheme'] == 'generic-test') {

                $attributes = [
                    'analyst_name' => (isset($params['analystName']) && !empty($params['analystName'])) ? $params['analystName'] : '',
                    'kit_name' => (isset($params['kitName']) && !empty($params['kitName'])) ? $params['kitName'] : '',
                    'kit_lot_number' => (isset($params['kitLot']) && !empty($params['kitLot'])) ? $params['kitLot'] : '',
                    'kit_expiry_date' => (isset($params['expiryDate']) && !empty($params['expiryDate'])) ? Pt_Commons_DateUtility::isoDateFormat($params['expiryDate']) : '',
                ];
                $attributes = json_encode($attributes);
                $mapData = [
                    'shipment_receipt_date' => (isset($params['receiptDate']) && !empty($params['receiptDate'])) ? Pt_Commons_DateUtility::isoDateFormat($params['receiptDate']) : '',
                    'shipment_test_date' => (isset($params['testDate']) && !empty($params['testDate'])) ? Pt_Commons_DateUtility::isoDateFormat($params['testDate']) : '',
                    'attributes' => $attributes,
                    'supervisor_approval' => $params['supervisorApproval'],
                    'participant_supervisor' => $params['participantSupervisor'],
                    'user_comment' => $params['userComments'],
                    'updated_by_user' => $authNameSpace->dm_id,
                    'updated_on_user' => new Zend_Db_Expr('now()'),
                ];

                if (isset($params['testReceiptDate']) && trim($params['testReceiptDate']) != '') {
                    $data['shipment_test_report_date'] = Pt_Commons_DateUtility::isoDateFormat($params['testReceiptDate'], true);
                } else {
                    $data['shipment_test_report_date'] = Pt_Commons_DateUtility::getCurrentDateTime();
                }
                if (isset($params['customField1']) && trim($params['customField1']) != '') {
                    $data['custom_field_1'] = $params['customField1'];
                }

                if (isset($params['customField2']) && trim($params['customField2']) != '') {
                    $data['custom_field_2'] = $params['customField2'];
                }

                $db->update('shipment_participant_map', $mapData, ['map_id = ?' => $params['smid']]);
                $db->delete('response_result_generic_test', ['shipment_map_id = ?' => $params['smid']]);
                for ($i = 0; $i < $size; $i++) {
                    $resultData = [
                        'shipment_map_id' => $params['smid'],
                        'sample_id' => $params['sampleId'][$i],
                        'result_1' => (isset($params['result_1'][$i]) && !empty($params['result_1'][$i])) ? $params['result_1'][$i] : '',
                        'result_2' => (isset($params['result_2'][$i]) && !empty($params['result_2'][$i])) ? $params['result_2'][$i] : '',
                        'result_3' => (isset($params['result_3'][$i]) && !empty($params['result_3'][$i])) ? $params['result_3'][$i] : '',
                        'reported_result' => (isset($params['finalResult'][$i]) && !empty($params['finalResult'][$i])) ? $params['finalResult'][$i] : '',
                        'additional_detail' => (isset($params['additionalDetail'][$i]) && !empty($params['additionalDetail'][$i])) ? $params['additionalDetail'][$i] : '',
                        'comments' => (isset($params['comments'][$i]) && !empty($params['comments'][$i])) ? $params['comments'][$i] : '',
                        'created_by' => $admin,
                        'created_on' => new Zend_Db_Expr('now()'),
                    ];
                    $db->insert('response_result_generic_test', $resultData);
                }
            }

            $params['isFollowUp'] = (isset($params['isFollowUp']) && $params['isFollowUp'] != '') ? $params['isFollowUp'] : 'no';

            $updateArray = ['evaluation_comment' => $params['comment'], 'optional_eval_comment' => $params['optionalComments'], 'is_followup' => $params['isFollowUp'], 'is_excluded' => $params['isExcluded'], 'updated_by_admin' => $admin, 'updated_on_admin' => new Zend_Db_Expr('now()')];
            if ($params['isExcluded'] == 'yes') {
                $updateArray['final_result'] = 3;
            }
            /* Manual result override changes */
            if (isset($params['manualOverride']) && $params['manualOverride'] == 'yes') {
                $updateArray['shipment_score'] = $shipmentScore;
                $updateArray['documentation_score'] = $docScore;
                $updateArray['final_result'] = $finalResult;
                if (isset($failureReason) && $failureReason != '') {
                    $updateArray['failure_reason'] = json_encode($failureReason);
                }
            }
            $updateArray['manual_override'] = (isset($params['manualOverride']) && $params['manualOverride'] != '') ? $params['manualOverride'] : 'no';
            $db->update('shipment_participant_map', $updateArray, ['map_id = ?' => $params['smid']]);
            $this->auditResponseEdit((string) ($params['scheme'] ?? ''), (int) ($params['smid'] ?? 0), $auditBefore);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            Pt_Commons_LoggerUtility::logError('updateShipmentResults rolled back: ' . $e->getMessage(), [
                'shipmentId' => $params['shipmentId'] ?? null,
                'smid' => $params['smid'] ?? null,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 8000),
            ]);
            throw $e;
        }
    }

    /** Response result table for each scheme the admin edit form saves. */
    private const RESPONSE_AUDIT_TABLES = [
        'eid' => 'response_result_eid',
        'dts' => 'response_result_dts',
        'vl' => 'response_result_vl',
        'recency' => 'response_result_recency',
        'covid19' => 'response_result_covid19',
        'tb' => 'response_result_tb',
        'generic-test' => 'response_result_generic_test',
    ];

    /** Response columns that are bookkeeping or computed by scoring, not entered results. */
    private const RESPONSE_AUDIT_IGNORE = [
        'shipment_map_id', 'sample_id', 'dts_id', 'calculated_score', 'z_score', 'algorithm_result',
        'interpretation_result', 'dts_rtri_is_editable', 'created_by', 'created_on', 'updated_by', 'updated_on',
    ];

    /** Response fields on shipment_participant_map that an admin edit can change. */
    private const RESPONSE_AUDIT_MAP_FIELDS = [
        'attributes', 'shipment_receipt_date', 'shipment_test_date', 'shipment_test_report_date', 'mode_id',
        'supervisor_approval', 'participant_supervisor', 'user_comment', 'custom_field_1', 'custom_field_2',
        'number_of_tests', 'specimen_volume', 'lab_director_name', 'lab_director_email', 'contact_person_name',
        'contact_person_email', 'contact_person_telephone', 'failure_reason',
    ];

    /** Scheme names used in response statements. */
    private const RESPONSE_AUDIT_SCHEMES = [
        'eid' => 'EID', 'dts' => 'DTS', 'vl' => 'VL', 'recency' => 'Recency',
        'covid19' => 'COVID-19', 'tb' => 'TB', 'generic-test' => 'Generic Test',
    ];

    /**
     * A response's results and evaluation fields, read before an admin edit.
     * Returns null when they cannot be read, so the edit itself goes on.
     *
     * @return array{evaluation: array<string, mixed>|null, samples: array<string, array<string, mixed>>, response: array<string, mixed>}|null
     */
    private function responseEditSnapshot(string $scheme, int $mapId): ?array
    {
        $table = self::RESPONSE_AUDIT_TABLES[$scheme] ?? null;
        if ($table === null || $mapId <= 0) {
            return null;
        }
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $samples = [];
            foreach ($db->fetchAll($db->select()->from($table)->where('shipment_map_id = ?', $mapId)->order('sample_id')) as $row) {
                $sample = array_diff_key($row, array_flip(self::RESPONSE_AUDIT_IGNORE));
                if (isset($sample['response_attributes'])) {
                    $decoded = json_decode((string) $sample['response_attributes'], true);
                    $sample['response_attributes'] = is_array($decoded) ? $decoded : $sample['response_attributes'];
                }
                $samples['Sample ' . $row['sample_id']] = $sample;
            }
            // The response's own fields: dates, algorithm, supervisor and the rest.
            $map = $db->fetchRow($db->select()->from('shipment_participant_map')->where('map_id = ?', $mapId)) ?: [];
            $response = array_intersect_key($map, array_flip(self::RESPONSE_AUDIT_MAP_FIELDS));
            foreach (['attributes', 'failure_reason'] as $jsonField) {
                if (isset($response[$jsonField]) && is_string($response[$jsonField])) {
                    $decoded = json_decode($response[$jsonField], true);
                    $response[$jsonField] = is_array($decoded) ? $decoded : $response[$jsonField];
                }
            }
            return [
                'evaluation' => Application_Service_Shipments::adminEvaluationSnapshot($mapId),
                'samples' => $samples,
                'response' => $response,
            ];
        } catch (Throwable $e) {
            Pt_Commons_LoggerUtility::logWarning('responseEditSnapshot failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Log an admin edit of a participant's response with what changed.
     * Flagged when results, the score or the exclusion changed.
     *
     * @param array{evaluation: array<string, mixed>|null, samples: array<string, array<string, mixed>>, response: array<string, mixed>}|null $before
     */
    private function auditResponseEdit(string $scheme, int $mapId, ?array $before): void
    {
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $row = $db->fetchRow(
                $db->select()
                    ->from(['spm' => 'shipment_participant_map'], ['shipment_id'])
                    ->join(['s' => 'shipment'], 's.shipment_id = spm.shipment_id', ['shipment_code'])
                    ->join(['p' => 'participant'], 'p.participant_id = spm.participant_id', ['unique_identifier'])
                    ->where('spm.map_id = ?', $mapId)
            );
            if (!$row) {
                return;
            }
            $extra = [
                'entity' => ['type' => 'shipment', 'id' => (int) $row['shipment_id'], 'label' => $row['shipment_code']],
                'facts' => ['Lab ID' => $row['unique_identifier']],
            ];
            $after = $before !== null ? $this->responseEditSnapshot($scheme, $mapId) : null;
            if ($before !== null && $after !== null) {
                $extra['changes'] = array_merge(
                    Application_Service_Shipments::adminEvaluationChangesSince($mapId, $before['evaluation']),
                    Application_Model_DbTable_AuditLog::changes(['samples' => $before['samples']], ['samples' => $after['samples']]),
                    Application_Model_DbTable_AuditLog::changes($before['response'], $after['response'])
                );
                $extra['importantFields'] = array_merge(Application_Service_Shipments::ADMIN_EVALUATION_SCORING_FIELDS, ['samples']);
                $extra['reason'] = "Changes a lab's results or score";
            }
            $schemeName = self::RESPONSE_AUDIT_SCHEMES[$scheme] ?? $scheme;
            Application_Model_DbTable_AuditLog::record(
                "Updated {$schemeName} response - {$row['shipment_code']} / {$row['unique_identifier']}",
                'response',
                $extra
            );
        } catch (Throwable $e) {
            Pt_Commons_LoggerUtility::logWarning('auditResponseEdit failed: ' . $e->getMessage());
        }
    }

    public function updateShipmentComment($params)
    {
        // $comment = preg_replace('/[^a-zA-Z0-9_-]/', '', $params['comment']);
        $shipmentId = preg_replace('/[^0-9]/', '-', base64_decode($params['sid']));

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $admin = $authNameSpace->admin_id;
        $before = $db->fetchRow($db->select()->from('shipment', ['shipment_code', 'shipment_comment', 'corrective_action_file'])->where('shipment_id = ?', $shipmentId));
        $noOfRows = $db->update('shipment', ['shipment_comment' => $params['comment'], 'updated_by_admin' => $admin, 'updated_on_admin' => new Zend_Db_Expr('now()')], ['shipment_id = ?' => $shipmentId]);
        if (isset($_FILES['correctiveActionFile']['name']) && count($_FILES['correctiveActionFile']) > 0) {
            $uploadDirectory = realpath(UPLOAD_PATH);
            if (isset($_FILES['correctiveActionFile']['name']) && trim($_FILES['correctiveActionFile']['name']) != '') {
                $pathComponents = ['corrective-action-files', $shipmentId];
                $pathname = Application_Service_Common::buildSafePath($uploadDirectory, $pathComponents);
                $fileName = Application_Service_Common::cleanFileName($_FILES['correctiveActionFile']['name']);
                $pathname = $pathname . DIRECTORY_SEPARATOR . $fileName;

                if (move_uploaded_file($_FILES['correctiveActionFile']['tmp_name'], $pathname)) {
                    $db->update('shipment', ['corrective_action_file' => $fileName], ['shipment_id = ?' => $shipmentId]);
                }
            }
        }
        if ($noOfRows > 0 && $before) {
            $after = $db->fetchRow($db->select()->from('shipment', ['shipment_comment', 'corrective_action_file'])->where('shipment_id = ?', $shipmentId));
            Application_Model_DbTable_AuditLog::record('Updated shipment comment - ' . $before['shipment_code'], 'shipment', [
                'entity' => ['type' => 'shipment', 'id' => (int) $shipmentId, 'label' => $before['shipment_code']],
                'changes' => Application_Model_DbTable_AuditLog::changes(
                    ['shipment_comment' => $before['shipment_comment'], 'corrective_action_file' => $before['corrective_action_file']],
                    $after ?: []
                ),
            ]);
        }
        if ($noOfRows > 0) {
            return 'Comment updated';
        } else {
            return 'Unable to update shipment comment. Please try again later.';
        }
    }

    public function updateShipmentStatus($shipmentId, $status)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $admin = $authNameSpace->admin_id;
        $before = $db->fetchRow($db->select()->from('shipment', ['shipment_code', 'status'])->where('shipment_id = ?', $shipmentId));
        $noOfRows = $db->update('shipment', ['status' => $status, 'updated_by_admin' => $admin, 'updated_on_admin' => new Zend_Db_Expr('now()')], ['shipment_id = ?' => $shipmentId]);
        if ($noOfRows > 0 && $before) {
            Application_Model_DbTable_AuditLog::record('Updated shipment status - ' . $before['shipment_code'], 'shipment', [
                'entity' => ['type' => 'shipment', 'id' => (int) $shipmentId, 'label' => $before['shipment_code']],
                'changes' => Application_Model_DbTable_AuditLog::changes(['status' => $before['status']], ['status' => $status]),
                'important' => $before['status'] === 'finalized',
                'reason' => 'Moves a finalized shipment back to an earlier status',
            ]);
        }
        if ($noOfRows > 0) {
            return 'Status updated';
        } else {
            return 'Unable to update shipment status. Please try again later.';
        }
    }

    public function getShipmentToEvaluateReports($shipmentId, $reEvaluate = false)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(['sp' => 'shipment_participant_map'])
            ->join(['s' => 'shipment'], 'sp.shipment_id=s.shipment_id')
            ->join(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_code', 'distribution_date'])
            ->join(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['scheme_name'])
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['first_name', 'last_name', 'lab_name', 'unique_identifier', 'country', 'state', 'district'])
            ->join(['c' => 'countries'], 'p.country=c.id', ['country_name' => 'iso_name'])
            ->joinLeft(['res' => 'r_results'], 'res.result_id=sp.final_result')
            ->joinLeft(
                ['p1' => 'participant'],
                "p1.participant_id = JSON_UNQUOTE(COALESCE(JSON_EXTRACT(sp.report_download_metadata, '$.first_individual_report_by'), JSON_EXTRACT(sp.report_download_metadata, '$.latest_individual_report_by')))",
                []
            )
            ->joinLeft(
                ['dm1' => 'data_manager'],
                "dm1.dm_id = JSON_UNQUOTE(COALESCE(JSON_EXTRACT(sp.report_download_metadata, '$.first_individual_report_by_dm'), JSON_EXTRACT(sp.report_download_metadata, '$.latest_individual_report_by_dm')))",
                ['individualParticipantName' => new Zend_Db_Expr("COALESCE(NULLIF(TRIM(CONCAT(COALESCE(dm1.first_name,''), ' ', COALESCE(dm1.last_name,''))), ''), TRIM(CONCAT(COALESCE(p1.first_name,''), ' ', COALESCE(p1.last_name,''))))")]
            )
            ->joinLeft(
                ['dm2' => 'data_manager'],
                "dm2.dm_id = JSON_UNQUOTE(COALESCE(JSON_EXTRACT(sp.report_download_metadata, '$.first_summary_report_by_dm'), JSON_EXTRACT(sp.report_download_metadata, '$.latest_summary_report_by_dm')))",
                ['summaryParticipantName' => new Zend_Db_Expr("TRIM(CONCAT(COALESCE(dm2.first_name,''), ' ', COALESCE(dm2.last_name,'')))")]
            )
            ->joinLeft(
                ['dmu' => 'data_manager'],
                'dmu.dm_id = sp.updated_by_user',
                [
                    'updatedByName' => new Zend_Db_Expr("TRIM(CONCAT(COALESCE(dmu.first_name,''), ' ', COALESCE(dmu.last_name,'')))"),
                    'updatedByEmail' => 'dmu.primary_email',
                    // Vietnam-only flag exposed for client-side row filtering on the
                    // /reports/distribution/finalize page. 'yes' = at least one sample's
                    // primary kit isn't in the country-approved test_no=1 set.
                    'has_non_ref_kit' => new Zend_Db_Expr(
                        "(CASE WHEN EXISTS (
                            SELECT 1 FROM response_result_dts rrd
                             WHERE rrd.shipment_map_id = sp.map_id
                               AND rrd.test_kit_name_1 IS NOT NULL
                               AND rrd.test_kit_name_1 <> ''
                               AND rrd.test_kit_name_1 NOT IN (SELECT testkit FROM dts_recommended_testkits WHERE test_no = 1)
                         ) THEN 'yes' ELSE 'no' END)"
                    ),
                ]
            )
            ->where('s.shipment_id = ?', $shipmentId)
            // Responders first (responded, then late), non-responders and not-tested
            // below; participant/tester ID orders within each group. Consumed as-is by
            // the client-side tables (their DataTables init keeps this server order).
            ->order([
                new Zend_Db_Expr("CASE WHEN sp.response_status = 'responded' THEN 0 WHEN sp.response_status = 'late' THEN 1 ELSE 2 END"),
                'p.unique_identifier',
            ]);
        return $db->fetchAll($sql);
    }

    // Lightweight header (single row) for the page chrome — no participant join.
    /**
     * Per-shipment listing filters applied to the DataTables base select.
     *
     * Universal (every scheme, every country):
     *   - filterResponseStatus ∈ {'responded','noresponse','late','nottested'}
     *     → sp.response_status = <value>
     *   - filterResult ∈ {1,2,3,'notevaluated'} (comma-separated for multi-select)
     *     → sp.final_result IN (1,2,3) and/or final_result IS NULL / 0 / 4
     *
     * Vietnam-specific (rendered + sent only when the shipment is Vietnam):
     *   - vietnamTier ∈ {'screening','confirmatory'}
     *     → match sp.attributes.dts_test_panel_type
     *   - vietnamNonStandardKit = 'yes'
     *     → at least one sample reported on a non-reference primary kit
     *       (test_kit_name_1 NOT IN dts_recommended_testkits.testkit WHERE test_no = 1)
     *
     * Unknown / empty params are ignored — params absent in the request leave the
     * base select untouched.
     */
    private function applyShipmentListFilters(Zend_Db_Select $baseSelect, array $parameters): void
    {
        // Universal: response_status
        /*  $responseStatus = trim((string) ($parameters['filterResponseStatus'] ?? ''));
          if (in_array($responseStatus, ['responded', 'noresponse', 'late', 'nottested'], true)) {
              $baseSelect->where('sp.response_status = ?', $responseStatus);
          }

          // Universal: final_result (Pass / Fail / Excluded)
          $resultMap = ['pass' => 1, 'fail' => 2, 'excluded' => 3];
          $resultKey = strtolower(trim((string) ($parameters['filterResult'] ?? '')));
          if (isset($resultMap[$resultKey])) {
              $baseSelect->where('sp.final_result = ?', $resultMap[$resultKey]);
          }*/
        $statusArr = explode(',', $parameters['filterResponseStatus']);
        if (isset($parameters['filterResponseStatus']) && $parameters['filterResponseStatus'] != '') {
            if (in_array('nottested', $statusArr)) {
                $key = array_search('nottested', $statusArr);
                unset($statusArr[$key]);
                $baseSelect = $baseSelect->where('(sp.response_status IN ("nottested") OR sp.is_pt_test_not_performed = "yes")');
            } else {
                $baseSelect = $baseSelect->where('sp.response_status IN (?)', $statusArr);
            }
        }
        // Final Result: 1 Satisfactory / 2 Unsatisfactory / 3 Excluded, plus the
        // pseudo-value 'notevaluated' (final_result 4, or NULL / 0 until evaluated).
        $resultTokens = array_filter(
            array_map('trim', explode(',', (string) ($parameters['filterResult'] ?? ''))),
            static fn (string $token): bool => $token !== ''
        );
        if (!empty($resultTokens)) {
            $resultConditions = [];
            $resultIds = array_values(array_intersect($resultTokens, ['1', '2', '3']));
            if (!empty($resultIds)) {
                $resultConditions[] = $baseSelect->getAdapter()->quoteInto('sp.final_result IN (?)', $resultIds);
            }
            if (in_array('notevaluated', $resultTokens, true)) {
                $resultConditions[] = '(sp.final_result IS NULL OR sp.final_result IN (0, 4))';
            }
            if (!empty($resultConditions)) {
                $baseSelect = $baseSelect->where('(' . implode(' OR ', $resultConditions) . ')');
            }
        }
        if (isset($parameters['country']) && $parameters['country'] != '') {
            $country = explode(',', $parameters['country']);
            $baseSelect = $baseSelect->where('p.country IN (?)', $country);
        }
        if (isset($parameters['region']) && $parameters['region'] != '') {
            $region = explode(',', $parameters['region']);
            $baseSelect = $baseSelect->where('p.region IN (?)', $region);
        }
        if (isset($parameters['state']) && $parameters['state'] != '') {
            $state = explode(',', $parameters['state']);
            $baseSelect = $baseSelect->where('p.state IN (?)', $state);
        }
        if (isset($parameters['district']) && $parameters['district'] != '') {
            $district = explode(',', $parameters['district']);
            $baseSelect = $baseSelect->where('p.district IN (?)', $district);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != '' && isset($parameters['endDate']) && $parameters['endDate'] != '') {
            $baseSelect = $baseSelect->where('DATE(sp.shipment_test_report_date) >= ?', Pt_Commons_DateUtility::isoDateFormat($parameters['startDate']));
            $baseSelect = $baseSelect->where('DATE(sp.shipment_test_report_date) <= ?', Pt_Commons_DateUtility::isoDateFormat($parameters['endDate']));
        }

        // Vietnam-specific: tier
        $tier = trim((string) ($parameters['vietnamTier'] ?? ''));
        if ($tier === 'screening' || $tier === 'confirmatory') {
            $baseSelect->where(
                "JSON_UNQUOTE(JSON_EXTRACT(sp.attributes, '$.dts_test_panel_type')) = ?",
                $tier
            );
        }

        // Vietnam-specific: primary kit not in the country-approved set
        $kitFlag = strtolower(trim((string) ($parameters['vietnamNonStandardKit'] ?? '')));
        if ($kitFlag === 'yes') {
            $baseSelect->where(
                "EXISTS (SELECT 1 FROM response_result_dts rrd
                          WHERE rrd.shipment_map_id = sp.map_id
                            AND rrd.test_kit_name_1 IS NOT NULL
                            AND rrd.test_kit_name_1 <> ''
                            AND rrd.test_kit_name_1 NOT IN (SELECT testkit FROM dts_recommended_testkits WHERE test_no = 1))"
            );
        }
    }

    public function getFinalizedShipmentHeader($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(['s' => 'shipment'])
            ->join(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_id', 'distribution_code', 'distribution_date'])
            ->join(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['scheme_name'])
            ->where('s.shipment_id = ?', $shipmentId)
            ->limit(1);
        return $db->fetchRow($sql) ?: null;
    }

    // Lightweight per-participant URLs for the prev/next navigation session list.
    public function getFinalizedShipmentNavUrls($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(['sp' => 'shipment_participant_map'], ['participant_id', 'evaluation_status'])
            ->join(['s' => 'shipment'], 'sp.shipment_id=s.shipment_id', ['shipment_id', 'scheme_type'])
            ->where('s.shipment_id = ?', $shipmentId)
            ->order('sp.map_id ASC');
        $rows = $db->fetchAll($sql);

        $editList = [];
        $viewList = [];
        foreach ($rows as $r) {
            $editList[] = '/' . $r['scheme_type'] . '/response/sid/' . $r['shipment_id'] . '/pid/' . $r['participant_id'] . '/eid/' . $r['evaluation_status'] . '/from/admin';
            $viewList[] = '/admin/evaluate/view/sid/' . base64_encode($r['shipment_id']) . '/pid/' . base64_encode($r['participant_id']) . '/scheme/' . base64_encode($r['scheme_type']);
        }
        return ['editUrlList' => $editList, 'viewUrlList' => $viewList];
    }

    // DataTables server-side endpoint for the finalized shipment participant table.
    // Echoes JSON; does not return.
    public function getFinalizedShipmentParticipantsForDataTable($shipmentId, $parameters)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Column index → searchable/sortable expression (null = not searchable/sortable).
        // 0 expand, 1 unique_identifier, 2 name, 3 country, 4 score, 5 doc-score,
        // 6 result, 7 response_status, 8 responded_on, 9 participant report,
        // 10 summary report, 11 last modified.
        $searchColumns = [
            null,
            'p.unique_identifier',
            new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameExpr('p')),
            'c.iso_name',
            'sp.shipment_score',
            'sp.documentation_score',
            'res.result_name',
            'sp.response_status',
            'sp.shipment_test_report_date',
            null,
            null,
            new Zend_Db_Expr("TRIM(CONCAT(COALESCE(dmu.first_name,''), ' ', COALESCE(dmu.last_name,'')))"),
        ];
        $orderColumns = $searchColumns;

        // Per-lab download counts. The history table has no participant column, so a
        // lab's downloads are those made by data managers mapped to that participant.
        // The list itself loads on demand through getParticipantDownloadHistory().
        $downloadCountExpr = static fn (string $type): Zend_Db_Expr => new Zend_Db_Expr(
            '(SELECT COUNT(*) FROM track_report_downloaded_history th'
            . ' INNER JOIN participant_manager_map pmm ON pmm.dm_id = th.downloaded_by'
            . " WHERE th.shipment_id = sp.shipment_id AND th.report_type = '$type'"
            . ' AND pmm.participant_id = sp.participant_id)'
        );

        $baseSelect = $db->select()
            ->from(['sp' => 'shipment_participant_map'])
            ->join(['s' => 'shipment'], 'sp.shipment_id=s.shipment_id')
            ->join(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_code', 'distribution_date'])
            ->join(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['scheme_name', 'is_user_configured'])
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['first_name', 'last_name', 'lab_name', 'unique_identifier', 'country', 'state', 'district'])
            ->join(['c' => 'countries'], 'p.country=c.id', ['country_name' => 'iso_name'])
            ->joinLeft(['res' => 'r_results'], 'res.result_id=sp.final_result')
            ->joinLeft(
                ['p1' => 'participant'],
                "p1.participant_id = JSON_UNQUOTE(COALESCE(JSON_EXTRACT(sp.report_download_metadata, '$.first_individual_report_by'), JSON_EXTRACT(sp.report_download_metadata, '$.latest_individual_report_by')))",
                []
            )
            ->joinLeft(
                ['dm1' => 'data_manager'],
                "dm1.dm_id = JSON_UNQUOTE(COALESCE(JSON_EXTRACT(sp.report_download_metadata, '$.first_individual_report_by_dm'), JSON_EXTRACT(sp.report_download_metadata, '$.latest_individual_report_by_dm')))",
                ['individualParticipantName' => new Zend_Db_Expr("COALESCE(NULLIF(TRIM(CONCAT(COALESCE(dm1.first_name,''), ' ', COALESCE(dm1.last_name,''))), ''), TRIM(CONCAT(COALESCE(p1.first_name,''), ' ', COALESCE(p1.last_name,''))))")]
            )
            ->joinLeft(
                ['dm2' => 'data_manager'],
                "dm2.dm_id = JSON_UNQUOTE(COALESCE(JSON_EXTRACT(sp.report_download_metadata, '$.first_summary_report_by_dm'), JSON_EXTRACT(sp.report_download_metadata, '$.latest_summary_report_by_dm')))",
                ['summaryParticipantName' => new Zend_Db_Expr("TRIM(CONCAT(COALESCE(dm2.first_name,''), ' ', COALESCE(dm2.last_name,'')))")]
            )
            ->joinLeft(
                ['dmu' => 'data_manager'],
                'dmu.dm_id = sp.updated_by_user',
                [
                    'updatedByName' => new Zend_Db_Expr("TRIM(CONCAT(COALESCE(dmu.first_name,''), ' ', COALESCE(dmu.last_name,'')))"),
                    'updatedByEmail' => 'dmu.primary_email',
                ]
            )
            ->columns([
                'individualDownloadCount' => $downloadCountExpr('individual'),
                'summaryDownloadCount' => $downloadCountExpr('summary'),
            ])
            ->where('s.shipment_id = ?', $shipmentId);

        $this->applyShipmentListFilters($baseSelect, $parameters);

        // Total before filtering.
        $countSelect = clone $baseSelect;
        $countSelect->reset(Zend_Db_Select::COLUMNS)->reset(Zend_Db_Select::ORDER)->reset(Zend_Db_Select::LIMIT_COUNT)->reset(Zend_Db_Select::LIMIT_OFFSET);
        $countSelect->columns(new Zend_Db_Expr('COUNT(sp.map_id)'));
        $iTotal = (int) $db->fetchOne($countSelect);

        $filtered = $baseSelect;

        // Global search across configured columns.
        if (!empty($parameters['sSearch'])) {
            $search = $parameters['sSearch'];
            $ors = [];
            foreach ($searchColumns as $col) {
                if ($col === null) {
                    continue;
                }
                $expr = ($col instanceof Zend_Db_Expr) ? (string) $col : $col;
                $ors[] = $expr . ' LIKE ' . $db->quote('%' . $search . '%');
            }
            if (!empty($ors)) {
                $filtered->where('(' . implode(' OR ', $ors) . ')');
            }
        }

        // Per-column search.
        $colCount = count($searchColumns);
        for ($i = 0; $i < $colCount; $i++) {
            if (!isset($parameters['sSearch_' . $i]) || $parameters['sSearch_' . $i] === '' || $searchColumns[$i] === null) {
                continue;
            }
            $expr = ($searchColumns[$i] instanceof Zend_Db_Expr) ? (string) $searchColumns[$i] : $searchColumns[$i];
            $filtered->where($expr . ' LIKE ?', '%' . $parameters['sSearch_' . $i] . '%');
        }

        // Sorting.
        // Float responders to the top (responded, then late / late-submitted), leaving
        // non-responders and not-tested below; the user's chosen column then sorts within
        // each group.
        $sortClauses = [new Zend_Db_Expr("CASE WHEN sp.response_status = 'responded' THEN 0 WHEN sp.response_status IN ('late', 'late_submitted') THEN 1 ELSE 2 END")];
        if (isset($parameters['iSortCol_0'])) {
            $sortingCols = (int) ($parameters['iSortingCols'] ?? 1);
            for ($i = 0; $i < $sortingCols; $i++) {
                $colIdx = (int) $parameters['iSortCol_' . $i];
                if (empty($parameters['bSortable_' . $colIdx]) || $parameters['bSortable_' . $colIdx] !== 'true') {
                    continue;
                }
                if (!isset($orderColumns[$colIdx]) || $orderColumns[$colIdx] === null) {
                    continue;
                }
                $dir = Pt_Commons_General::sanitizeSortDirection($parameters['sSortDir_' . $i] ?? 'asc');
                $expr = ($orderColumns[$colIdx] instanceof Zend_Db_Expr) ? (string) $orderColumns[$colIdx] : $orderColumns[$colIdx];
                $sortClauses[] = new Zend_Db_Expr($expr . ' ' . $dir);
            }
        }
        $filtered->order($sortClauses);

        // Filtered total (post-where, pre-limit).
        $filteredCountSelect = clone $filtered;
        $filteredCountSelect->reset(Zend_Db_Select::COLUMNS)->reset(Zend_Db_Select::ORDER)->reset(Zend_Db_Select::LIMIT_COUNT)->reset(Zend_Db_Select::LIMIT_OFFSET);
        $filteredCountSelect->columns(new Zend_Db_Expr('COUNT(sp.map_id)'));
        $iFilteredTotal = (int) $db->fetchOne($filteredCountSelect);

        // Pagination.
        if (isset($parameters['iDisplayStart']) && isset($parameters['iDisplayLength']) && $parameters['iDisplayLength'] != '-1') {
            $filtered->limit((int) $parameters['iDisplayLength'], (int) $parameters['iDisplayStart']);
        }

        $rResult = $db->fetchAll($filtered);

        $output = [
            'sEcho' => (int) ($parameters['sEcho'] ?? 0),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        foreach ($rResult as $shipment) {
            $output['aaData'][] = $this->renderFinalizedShipmentRow($shipment);
        }

        echo json_encode($output);
    }

    /**
     * Downloads of one report type by the data managers mapped to a shipment row's
     * participant, newest first. Feeds the on-demand list on the finalized page.
     */
    public function getParticipantDownloadHistory(int $mapId, string $reportType): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll(
            $db->select()
                ->from(['sp' => 'shipment_participant_map'], [])
                ->join(['th' => 'track_report_downloaded_history'], 'th.shipment_id = sp.shipment_id', ['downloaded_on'])
                ->join(['pmm' => 'participant_manager_map'], 'pmm.dm_id = th.downloaded_by AND pmm.participant_id = sp.participant_id', [])
                ->join(['dm' => 'data_manager'], 'dm.dm_id = pmm.dm_id', [
                    'name' => new Zend_Db_Expr("TRIM(CONCAT(COALESCE(dm.first_name, ''), ' ', COALESCE(dm.last_name, '')))"),
                    'email' => 'dm.primary_email',
                ])
                ->where('sp.map_id = ?', $mapId)
                ->where('th.report_type = ?', $reportType)
                ->order('th.downloaded_on DESC')
        );
    }

    // Builds the row array for the finalized-shipment participant DataTable.
    // Markup mirrors what the view used to render server-side.
    // The response page URL that admins open for one participant row.
    private function adminResponseUrl(array $shipment): string
    {
        $schemeType = (($shipment['is_user_configured'] ?? '') === 'yes') ? 'custom-test' : (string) ($shipment['scheme_type'] ?? '');
        if ($schemeType === '') {
            return '';
        }
        return '/' . rawurlencode($schemeType) . '/response/sid/' . (int) $shipment['shipment_id'] . '/pid/' . (int) $shipment['participant_id']
            . '/eid/' . rawurlencode((string) ($shipment['evaluation_status'] ?? '')) . '/from/admin';
    }

    // A View button that opens the response read-only. It needs no edit privilege.
    // Only these schemes have a read-only mode on their response page.
    private function responseViewButton(array $shipment): string
    {
        $url = $this->adminResponseUrl($shipment);
        $schemeType = explode('/', $url)[1] ?? '';
        if ($url === '' || !in_array($schemeType, ['dts', 'vl', 'eid', 'tb', 'recency', 'covid19', 'custom-test'], true)) {
            return '';
        }
        $translator = Zend_Registry::get('translate');
        return '<a class="btn btn-default btn-xs" href="' . htmlspecialchars($url . '/mode/view', ENT_QUOTES, 'UTF-8') . '"><span><i class="icon-eye-open"></i> '
            . htmlspecialchars($translator->_('View'), ENT_QUOTES, 'UTF-8') . '</span></a>';
    }

    private function renderFinalizedShipmentRow($shipment)
    {
        $translator = Zend_Registry::get('translate');
        $btnClassName = 'btn-success';
        $warnings = Pt_Commons_JsonUtility::safeDecode($shipment['failure_reason']);
        $finalResult = (isset($shipment['final_result']) && $shipment['final_result'] != '' && $shipment['final_result'] != 0)
            ? $shipment['result_name'] : $translator->_('Not Evaluated');

        if (isset($shipment['display_result']) && $shipment['display_result'] === 'Fail') {
            $btnClassName = 'btn-danger';
        } elseif ((isset($shipment['final_result']) && $shipment['final_result'] == 3) || $finalResult === $translator->_('Excluded')) {
            $btnClassName = 'btn-default';
            $finalResult = $translator->_('Excluded');
        } elseif ($finalResult === $translator->_('Not Evaluated')) {
            $btnClassName = 'btn-default';
        } elseif (isset($warnings) && count($warnings) > 0) {
            $btnClassName = 'btn-warning';
        }

        $shipmentScore = (isset($shipment['shipment_score']) && $shipment['shipment_score'] !== '') ? $shipment['shipment_score'] : '0';
        $documentationScore = (isset($shipment['documentation_score']) && $shipment['documentation_score'] !== '') ? $shipment['documentation_score'] : '0';

        if (empty($shipment['response_status']) || $shipment['response_status'] === 'noresponse') {
            $responseStatus = $translator->_('Not Responded');
        } elseif ($shipment['response_status'] === 'responded') {
            $responseStatus = $translator->_('Responded');
        } elseif ($shipment['response_status'] === 'late') {
            $responseStatus = $translator->_('Late Response');
        } elseif ($shipment['response_status'] === 'late_submitted') {
            $responseStatus = $translator->_('Late Submission (pending approval)');
        } elseif ($shipment['response_status'] === 'late_rejected') {
            $responseStatus = $translator->_('Late Submission (rejected)');
        } else {
            $responseStatus = $translator->_('Not Tested');
        }

        if (($shipment['status'] ?? '') === 'finalized') {
            $invididualFilePath = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . $shipment['shipment_code'] . DIRECTORY_SEPARATOR . $shipment['shipment_code'] . '-' . $shipment['map_id'] . '.pdf';
            if (!file_exists($invididualFilePath)) {
                $files = glob(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . $shipment['shipment_code'] . DIRECTORY_SEPARATOR . '*-' . $shipment['map_id'] . '.pdf');
                $invididualFilePath = $files[0] ?? '';
            }
            if ($invididualFilePath !== '' && file_exists($invididualFilePath)) {
                // btn-info, as on the other grids: the card treatment quietens the
                // generic colours, and this cell is the participant's report download.
                $finalResult = '<a href="' . Pt_Commons_SignedDownload::url($invididualFilePath) . '" class="btn btn-sm btn-info" style="text-decoration:none;overflow:hidden;margin-top:4px;width:100%;" target="_blank"><i class="icon icon-download"></i> ' . htmlspecialchars($finalResult) . '</a>';
            } else {
                $finalResult = htmlspecialchars($finalResult);
            }
        } else {
            $finalResult = htmlspecialchars($finalResult);
        }

        $reportDownloadedOn = Pt_Commons_JsonUtility::safeDecode($shipment['report_download_metadata']);

        $expandBtn = '<a href="javascript:void(0);" class="btn btn-xs clicker ' . $btnClassName . '" data-btn-class="' . $btnClassName . '" data-map-id="' . (int) $shipment['map_id'] . '"><i class="icon-plus"></i></a>';

        $participantId = '<div style="text-align:center;">' . htmlspecialchars($shipment['unique_identifier'] ?? '') . '</div>';
        $participantName = htmlspecialchars(trim(($shipment['first_name'] ?? '') . ' ' . ($shipment['last_name'] ?? '')));
        $country = htmlspecialchars($shipment['country_name'] ?? '');
        $score = '<div style="text-align:center;">' . htmlspecialchars($shipmentScore) . '</div>';
        $docScore = '<div style="text-align:center;">' . htmlspecialchars($documentationScore) . '</div>';
        $resultCell = '<div style="text-align:center;">' . $finalResult . '</div>';
        $responseStatusCell = htmlspecialchars($responseStatus);
        $respondedOn = Pt_Commons_DateUtility::humanReadableDateFormat($shipment['shipment_test_report_date'] ?? null, true) ?? '';

        $notDownloadedBadge = '<span class="badge rounded-pill" style="background-color:#fff3cd;color:#856404;font-weight:500;padding:6px 12px;"><i class="icon-remove"></i> ' . $translator->_('Not downloaded') . '</span>';

        // A badge with downloads opens that lab's download list in a child row.
        $downloadedBadge = function (int $count, string $type) use ($translator, $shipment): string {
            $label = '<i class="icon-check"></i> ' . $translator->_('Downloaded') . ($count > 1 ? ' (' . $count . 'x)' : '');
            $style = 'background-color:#d4edda;color:#155724;font-weight:500;padding:6px 12px;';
            if ($count === 0) {
                return '<span class="badge rounded-pill" style="' . $style . '">' . $label . '</span>';
            }
            return '<a href="javascript:void(0);" class="badge rounded-pill toggle-downloads-row"'
                . ' data-map-id="' . (int) $shipment['map_id'] . '" data-report-type="' . $type . '"'
                . ' title="' . htmlspecialchars($translator->_('Show who downloaded this report'), ENT_QUOTES, 'UTF-8') . '"'
                . ' style="' . $style . 'cursor:pointer;text-decoration:none;">' . $label . ' <i class="icon-caret-down"></i></a>';
        };

        // ---- Participant (individual) report cell ----
        $hasIndividual = !empty($reportDownloadedOn['first_individual_report_on']) || !empty($reportDownloadedOn['latest_individual_report_on']);
        if ($hasIndividual) {
            $indDate = Pt_Commons_DateUtility::humanReadableDateFormat($reportDownloadedOn['first_individual_report_on'] ?? $reportDownloadedOn['latest_individual_report_on'], true);
            $indBadge = $downloadedBadge((int) ($shipment['individualDownloadCount'] ?? 0), 'individual');
            $participantReport = $indBadge . '<br>'
                . '<strong>' . htmlspecialchars($shipment['individualParticipantName'] ?? '') . '</strong><br>'
                . '<small class="text-body-secondary">' . htmlspecialchars((string) $indDate) . '</small>';
        } else {
            $participantReport = $notDownloadedBadge;
        }

        // ---- Summary report cell ----
        $hasSummary = !empty($reportDownloadedOn['first_summary_report_on']) || !empty($reportDownloadedOn['latest_summary_report_on']);
        if ($hasSummary) {
            $sumDate = Pt_Commons_DateUtility::humanReadableDateFormat($reportDownloadedOn['first_summary_report_on'] ?? $reportDownloadedOn['latest_summary_report_on'], true);
            $sumBadge = $downloadedBadge((int) ($shipment['summaryDownloadCount'] ?? 0), 'summary');
            $summaryReport = $sumBadge . '<br>'
                . '<strong>' . htmlspecialchars($shipment['summaryParticipantName'] ?? '') . '</strong><br>'
                . '<small class="text-body-secondary">' . htmlspecialchars((string) $sumDate) . '</small>';
        } else {
            $summaryReport = $notDownloadedBadge;
        }

        $updatedByName = trim($shipment['updatedByName'] ?? '');
        $updatedByEmail = trim($shipment['updatedByEmail'] ?? '');
        $updatedOnRaw = (!empty($shipment['updated_on_user']) && $shipment['updated_on_user'] !== '0000-00-00 00:00:00') ? $shipment['updated_on_user'] : '';
        if ($updatedByName !== '' || $updatedByEmail !== '' || $updatedOnRaw !== '') {
            $lastModified = '';
            if ($updatedByName !== '' || $updatedByEmail !== '') {
                $lastModified .= '<strong>' . htmlspecialchars($updatedByName !== '' ? $updatedByName : '—') . '</strong>';
                if ($updatedByEmail !== '') {
                    $lastModified .= '<br><small class="text-body-secondary">(' . htmlspecialchars($updatedByEmail) . ')</small>';
                }
            }
            if ($updatedOnRaw !== '') {
                $updatedOnFormatted = Pt_Commons_DateUtility::humanReadableDateFormat($updatedOnRaw, true);
                $lastModified .= '<br><small class="text-body-secondary">' . htmlspecialchars((string) $updatedOnFormatted) . '</small>';
            }
        } else {
            $lastModified = '<span class="text-muted">&mdash;</span>';
        }

        return [
            $expandBtn,
            $participantId,
            $participantName,
            $country,
            $score,
            $docScore,
            $resultCell,
            $responseStatusCell,
            $respondedOn,
            $participantReport,
            $summaryReport,
            $lastModified,
            '<div style="text-align:center;">' . $this->responseViewButton($shipment) . '</div>',
            (int) $shipment['map_id'],
        ];
    }

    // DataTables server-side endpoint for the distribution shipment participant table.
    // Echoes JSON; does not return.
    public function getDistributionShipmentParticipantsForDataTable($shipmentId, $parameters, $reportQueue = '')
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Column index → searchable/sortable expression (null = not searchable/sortable).
        // 0 expand, 1 participant (name + uid), 2 state, 3 district, 4 score,
        // 5 doc-score, 6 result, 7 response_status, 8 responded_on, 9 report (action),
        // 10 last modified.
        $searchColumns = [
            null,
            new Zend_Db_Expr('CONCAT(' . Application_Model_DbTable_Participants::participantNameExpr('p') . ", ' (', COALESCE(p.unique_identifier, ''), ')')"),
            'p.state',
            'p.district',
            'sp.shipment_score',
            'sp.documentation_score',
            'res.result_name',
            'sp.response_status',
            'sp.shipment_test_report_date',
            null,
            new Zend_Db_Expr("TRIM(CONCAT(COALESCE(dmu.first_name,''), ' ', COALESCE(dmu.last_name,'')))"),
        ];
        $orderColumns = $searchColumns;

        $baseSelect = $db->select()
            ->from(['sp' => 'shipment_participant_map'])
            ->join(['s' => 'shipment'], 'sp.shipment_id=s.shipment_id')
            ->join(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_code', 'distribution_date'])
            ->join(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['scheme_name', 'is_user_configured'])
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['first_name', 'last_name', 'lab_name', 'unique_identifier', 'country', 'state', 'district'])
            ->join(['c' => 'countries'], 'p.country=c.id', ['country_name' => 'iso_name'])
            ->joinLeft(['res' => 'r_results'], 'res.result_id=sp.final_result')
            ->joinLeft(
                ['dmu' => 'data_manager'],
                'dmu.dm_id = sp.updated_by_user',
                [
                    'updatedByName' => new Zend_Db_Expr("TRIM(CONCAT(COALESCE(dmu.first_name,''), ' ', COALESCE(dmu.last_name,'')))"),
                    'updatedByEmail' => 'dmu.primary_email',
                ]
            )
            ->where('s.shipment_id = ?', $shipmentId);

        $this->applyShipmentListFilters($baseSelect, $parameters);

        $countSelect = clone $baseSelect;
        $countSelect->reset(Zend_Db_Select::COLUMNS)->reset(Zend_Db_Select::ORDER)->reset(Zend_Db_Select::LIMIT_COUNT)->reset(Zend_Db_Select::LIMIT_OFFSET);
        $countSelect->columns(new Zend_Db_Expr('COUNT(sp.map_id)'));
        $iTotal = (int) $db->fetchOne($countSelect);

        $filtered = $baseSelect;

        if (!empty($parameters['sSearch'])) {
            $search = $parameters['sSearch'];
            $ors = [];
            foreach ($searchColumns as $col) {
                if ($col === null) {
                    continue;
                }
                $expr = ($col instanceof Zend_Db_Expr) ? (string) $col : $col;
                $ors[] = $expr . ' LIKE ' . $db->quote('%' . $search . '%');
            }
            if (!empty($ors)) {
                $filtered->where('(' . implode(' OR ', $ors) . ')');
            }
        }

        $colCount = count($searchColumns);
        for ($i = 0; $i < $colCount; $i++) {
            if (!isset($parameters['sSearch_' . $i]) || $parameters['sSearch_' . $i] === '' || $searchColumns[$i] === null) {
                continue;
            }
            $expr = ($searchColumns[$i] instanceof Zend_Db_Expr) ? (string) $searchColumns[$i] : $searchColumns[$i];
            $filtered->where($expr . ' LIKE ?', '%' . $parameters['sSearch_' . $i] . '%');
        }

        // Float responders to the top (responded, then late / late-submitted), leaving
        // non-responders and not-tested below; the user's chosen column then sorts within
        // each group.
        $sortClauses = [new Zend_Db_Expr("CASE WHEN sp.response_status = 'responded' THEN 0 WHEN sp.response_status IN ('late', 'late_submitted') THEN 1 ELSE 2 END")];
        if (isset($parameters['iSortCol_0'])) {
            $sortingCols = (int) ($parameters['iSortingCols'] ?? 1);
            for ($i = 0; $i < $sortingCols; $i++) {
                $colIdx = (int) $parameters['iSortCol_' . $i];
                if (empty($parameters['bSortable_' . $colIdx]) || $parameters['bSortable_' . $colIdx] !== 'true') {
                    continue;
                }
                if (!isset($orderColumns[$colIdx]) || $orderColumns[$colIdx] === null) {
                    continue;
                }
                $dir = Pt_Commons_General::sanitizeSortDirection($parameters['sSortDir_' . $i] ?? 'asc');
                $expr = ($orderColumns[$colIdx] instanceof Zend_Db_Expr) ? (string) $orderColumns[$colIdx] : $orderColumns[$colIdx];
                $sortClauses[] = new Zend_Db_Expr($expr . ' ' . $dir);
            }
        }
        $filtered->order($sortClauses);

        $filteredCountSelect = clone $filtered;
        $filteredCountSelect->reset(Zend_Db_Select::COLUMNS)->reset(Zend_Db_Select::ORDER)->reset(Zend_Db_Select::LIMIT_COUNT)->reset(Zend_Db_Select::LIMIT_OFFSET);
        $filteredCountSelect->columns(new Zend_Db_Expr('COUNT(sp.map_id)'));
        $iFilteredTotal = (int) $db->fetchOne($filteredCountSelect);

        if (isset($parameters['iDisplayStart']) && isset($parameters['iDisplayLength']) && $parameters['iDisplayLength'] != '-1') {
            $filtered->limit((int) $parameters['iDisplayLength'], (int) $parameters['iDisplayStart']);
        }
        $rResult = $db->fetchAll($filtered);

        $output = [
            'sEcho' => (int) ($parameters['sEcho'] ?? 0),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        foreach ($rResult as $shipment) {
            $output['aaData'][] = $this->renderDistributionShipmentRow($shipment, $reportQueue);
        }

        echo json_encode($output);
    }

    // Builds the row array for the distribution shipment participant DataTable.
    private function renderDistributionShipmentRow($shipment, $reportQueue)
    {
        $translator = Zend_Registry::get('translate');
        $btnClassName = 'btn-success';
        $warnings = Pt_Commons_JsonUtility::safeDecode($shipment['failure_reason']);
        $finalResult = (isset($shipment['final_result']) && $shipment['final_result'] != '' && $shipment['final_result'] != 0)
            ? $shipment['result_name'] : $translator->_('Not Evaluated');

        if (isset($shipment['final_result']) && $shipment['final_result'] == 2) {
            $btnClassName = 'btn-danger';
        } elseif (isset($shipment['final_result']) && $shipment['final_result'] == 3) {
            $btnClassName = 'btn-default';
            $finalResult = $translator->_('Excluded');
        } elseif ($finalResult === $translator->_('Not Evaluated')) {
            $btnClassName = 'btn-default';
        } elseif (isset($warnings) && count($warnings) > 0) {
            $btnClassName = 'btn-warning';
        }

        $shipmentScore = (isset($shipment['shipment_score']) && $shipment['shipment_score'] !== '') ? $shipment['shipment_score'] : '0';
        $documentationScore = (isset($shipment['documentation_score']) && $shipment['documentation_score'] !== '') ? $shipment['documentation_score'] : '0';

        $individualReports = '';
        // Excluded participants (final_result 3) get their report link too when a
        // PDF was actually produced for them — the file_exists check below is the
        // only availability gate.
        if (($shipment['status'] ?? '') === 'reports generated') {
            $invididualFilePath = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . $shipment['shipment_code'] . DIRECTORY_SEPARATOR . $shipment['shipment_code'] . '-' . $shipment['map_id'] . '.pdf';
            if (!file_exists($invididualFilePath)) {
                $files = glob(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . $shipment['shipment_code'] . DIRECTORY_SEPARATOR . '*-' . $shipment['map_id'] . '.pdf');
                $invididualFilePath = $files[0] ?? '';
            }
            if ($invididualFilePath !== '' && file_exists($invididualFilePath) && $reportQueue !== 'disabled') {
                // btn-info rather than btn-primary: the card treatment quietens the
                // generic button colours, and a download is what people come to this
                // column to do — it keeps its own colour like the other exports.
                $individualReports = '<a href="' . Pt_Commons_SignedDownload::url($invididualFilePath) . '" class="btn btn-sm btn-info" style="text-decoration:none;overflow:hidden;margin-top:4px;width:100%;" target="_blank"><i class="icon icon-download"></i> ' . $translator->_('Download Report') . '</a>';
            }
        }

        if (empty($shipment['response_status']) || $shipment['response_status'] === 'noresponse') {
            $responseStatus = $translator->_('Not Responded');
        } elseif ($shipment['response_status'] === 'responded') {
            if ($shipment['is_pt_test_not_performed'] === 'yes') {
                $responseStatus = $translator->_('PT Not Tested');
            } else {
                $responseStatus = $translator->_('Responded');
            }
        } elseif ($shipment['response_status'] === 'late') {
            $responseStatus = $translator->_('Late Response');
        } elseif ($shipment['response_status'] === 'late_submitted') {
            $responseStatus = $translator->_('Late Submission (pending approval)');
        } elseif ($shipment['response_status'] === 'late_rejected') {
            $responseStatus = $translator->_('Late Submission (rejected)');
        } else {
            $responseStatus = $translator->_('Not Tested');
        }

        $expandBtn = '<a href="javascript:void(0);" class="btn btn-xs clicker ' . $btnClassName . '" data-btn-class="' . $btnClassName . '" data-map-id="' . (int) $shipment['map_id'] . '"><i class="icon-plus"></i></a>';

        $participantCell = htmlspecialchars(trim(($shipment['first_name'] ?? '') . ' ' . ($shipment['last_name'] ?? ''))) . ' (' . htmlspecialchars($shipment['unique_identifier'] ?? '') . ')';
        $state = '<div style="text-align:center;">' . htmlspecialchars($shipment['state'] ?? '') . '</div>';
        $district = '<div style="text-align:center;">' . htmlspecialchars($shipment['district'] ?? '') . '</div>';
        $score = '<div style="text-align:center;">' . htmlspecialchars($shipmentScore) . '</div>';
        $docScore = '<div style="text-align:center;">' . htmlspecialchars($documentationScore) . '</div>';
        $resultCell = '<div style="text-align:center;">' . htmlspecialchars($finalResult) . '</div>';
        $responseStatusCell = htmlspecialchars($responseStatus);
        $respondedOn = Pt_Commons_DateUtility::humanReadableDateFormat($shipment['shipment_test_report_date'] ?? null, true) ?? '';

        $updatedByName = trim($shipment['updatedByName'] ?? '');
        $updatedByEmail = trim($shipment['updatedByEmail'] ?? '');
        $updatedOnRaw = (!empty($shipment['updated_on_user']) && $shipment['updated_on_user'] !== '0000-00-00 00:00:00') ? $shipment['updated_on_user'] : '';
        if ($updatedByName !== '' || $updatedByEmail !== '' || $updatedOnRaw !== '') {
            $lastModified = '';
            if ($updatedByName !== '' || $updatedByEmail !== '') {
                $lastModified .= '<strong>' . htmlspecialchars($updatedByName !== '' ? $updatedByName : '—') . '</strong>';
                if ($updatedByEmail !== '') {
                    $lastModified .= '<br><small class="text-body-secondary">(' . htmlspecialchars($updatedByEmail) . ')</small>';
                }
            }
            if ($updatedOnRaw !== '') {
                $updatedOnFormatted = Pt_Commons_DateUtility::humanReadableDateFormat($updatedOnRaw, true);
                $lastModified .= '<br><small class="text-body-secondary">' . htmlspecialchars((string) $updatedOnFormatted) . '</small>';
            }
        } else {
            $lastModified = '<span class="text-muted">&mdash;</span>';
        }

        return [
            $expandBtn,
            $participantCell,
            $state,
            $district,
            $score,
            $docScore,
            $resultCell,
            $responseStatusCell,
            $respondedOn,
            $this->responseViewButton($shipment) . ($individualReports !== '' ? '<br>' . $individualReports : ''),
            $lastModified,
            (int) $shipment['map_id'],
        ];
    }

    // DataTables server-side endpoint for /admin/evaluate/shipment.
    // Read-only — assumes initial GET already triggered any required evaluation.
    // Echoes JSON and does not return.
    public function getEvaluateShipmentParticipantsForDataTable($shipmentId, $parameters, $override = '', $privileges = [])
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Column index → searchable/sortable expression (null = not searchable/sortable).
        // 0 expand, 1 participant (name + uid), 2 score, 3 doc-score, 4 result, 5 response_status,
        // 6 responded_on, 7 last_modified, 8 map_id (hidden), 9 action.
        $searchColumns = [
            null,
            new Zend_Db_Expr('CONCAT(' . Application_Model_DbTable_Participants::participantNameExpr('p') . ", ' (', COALESCE(p.unique_identifier, ''), ')')"),
            'sp.shipment_score',
            'sp.documentation_score',
            new Zend_Db_Expr("CASE sp.final_result WHEN 1 THEN 'Pass' WHEN 2 THEN 'Fail' WHEN 3 THEN 'Excluded' ELSE '' END"),
            'sp.response_status',
            'sp.shipment_test_report_date',
            new Zend_Db_Expr("TRIM(CONCAT(COALESCE(dmu.first_name,''), ' ', COALESCE(dmu.last_name,'')))"),
            null,
            null,
        ];
        $orderColumns = $searchColumns;

        $baseSelect = $db->select()
            ->from(['sp' => 'shipment_participant_map'])
            ->join(['s' => 'shipment'], 'sp.shipment_id=s.shipment_id', ['shipment_id', 'shipment_code', 'scheme_type', 'corrective_action_file', 'shipment_status' => 's.status'])
            ->join(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_code', 'distribution_date'])
            ->join(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['scheme_name', 'is_user_configured'])
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['first_name', 'last_name', 'lab_name', 'unique_identifier', 'state', 'district'])
            ->joinLeft(['res' => 'r_results'], 'res.result_id=sp.final_result', ['result_name'])
            ->joinLeft(
                ['dmu' => 'data_manager'],
                'dmu.dm_id = sp.updated_by_user',
                [
                    'updatedByName' => new Zend_Db_Expr("TRIM(CONCAT(COALESCE(dmu.first_name,''), ' ', COALESCE(dmu.last_name,'')))"),
                    'updatedByEmail' => 'dmu.primary_email',
                ]
            )
            ->where('s.shipment_id = ?', $shipmentId);

        if ($override === 'yes' || $override === 'no') {
            $baseSelect->where('sp.manual_override = ?', $override);
        }

        $this->applyShipmentListFilters($baseSelect, $parameters);

        $countSelect = clone $baseSelect;
        $countSelect->reset(Zend_Db_Select::COLUMNS)->reset(Zend_Db_Select::ORDER)->reset(Zend_Db_Select::LIMIT_COUNT)->reset(Zend_Db_Select::LIMIT_OFFSET);
        $countSelect->columns(new Zend_Db_Expr('COUNT(sp.map_id)'));
        $iTotal = (int) $db->fetchOne($countSelect);

        $filtered = $baseSelect;

        if (!empty($parameters['sSearch'])) {
            $search = $parameters['sSearch'];
            $ors = [];
            foreach ($searchColumns as $col) {
                if ($col === null) {
                    continue;
                }
                $expr = ($col instanceof Zend_Db_Expr) ? (string) $col : $col;
                $ors[] = $expr . ' LIKE ' . $db->quote('%' . $search . '%');
            }
            if (!empty($ors)) {
                $filtered->where('(' . implode(' OR ', $ors) . ')');
            }
        }

        $colCount = count($searchColumns);
        for ($i = 0; $i < $colCount; $i++) {
            if (!isset($parameters['sSearch_' . $i]) || $parameters['sSearch_' . $i] === '' || $searchColumns[$i] === null) {
                continue;
            }
            $expr = ($searchColumns[$i] instanceof Zend_Db_Expr) ? (string) $searchColumns[$i] : $searchColumns[$i];
            $filtered->where($expr . ' LIKE ?', '%' . $parameters['sSearch_' . $i] . '%');
        }

        // Float responders to the top (responded, then late / late-submitted), leaving
        // non-responders and not-tested below; the user's chosen column then sorts within
        // each group.
        $sortClauses = [new Zend_Db_Expr("CASE WHEN sp.response_status = 'responded' THEN 0 WHEN sp.response_status IN ('late', 'late_submitted') THEN 1 ELSE 2 END")];
        if (isset($parameters['iSortCol_0'])) {
            $sortingCols = (int) ($parameters['iSortingCols'] ?? 1);
            for ($i = 0; $i < $sortingCols; $i++) {
                $colIdx = (int) $parameters['iSortCol_' . $i];
                if (empty($parameters['bSortable_' . $colIdx]) || $parameters['bSortable_' . $colIdx] !== 'true') {
                    continue;
                }
                if (!isset($orderColumns[$colIdx]) || $orderColumns[$colIdx] === null) {
                    continue;
                }
                $dir = Pt_Commons_General::sanitizeSortDirection($parameters['sSortDir_' . $i] ?? 'asc');
                $expr = ($orderColumns[$colIdx] instanceof Zend_Db_Expr) ? (string) $orderColumns[$colIdx] : $orderColumns[$colIdx];
                $sortClauses[] = new Zend_Db_Expr($expr . ' ' . $dir);
            }
        }
        $filtered->order($sortClauses);

        $filteredCountSelect = clone $filtered;
        $filteredCountSelect->reset(Zend_Db_Select::COLUMNS)->reset(Zend_Db_Select::ORDER)->reset(Zend_Db_Select::LIMIT_COUNT)->reset(Zend_Db_Select::LIMIT_OFFSET);
        $filteredCountSelect->columns(new Zend_Db_Expr('COUNT(sp.map_id)'));
        $iFilteredTotal = (int) $db->fetchOne($filteredCountSelect);

        if (isset($parameters['iDisplayStart']) && isset($parameters['iDisplayLength']) && $parameters['iDisplayLength'] != '-1') {
            $filtered->limit((int) $parameters['iDisplayLength'], (int) $parameters['iDisplayStart']);
        }

        $rResult = $db->fetchAll($filtered);

        $canEdit = in_array('edit-participant-response', $privileges, true);

        $output = [
            'sEcho' => (int) ($parameters['sEcho'] ?? 0),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        foreach ($rResult as $row) {
            $output['aaData'][] = $this->renderEvaluateShipmentRow($row, $canEdit);
        }

        echo json_encode($output);
    }

    // Builds the row array for the /admin/evaluate/shipment participant DataTable.
    private function renderEvaluateShipmentRow($shipment, $canEdit)
    {
        $translator = Zend_Registry::get('translate');

        // Action-row scheme_type follows the same mapping the view used for editUrl:
        // custom-test for user-configured, otherwise the raw scheme_type.
        $rowSchemeType = (($shipment['is_user_configured'] ?? '') === 'yes')
            ? 'custom-test' : ($shipment['scheme_type'] ?? '');

        $btnClassName = 'btn-success';
        $displayResult = '';
        if (isset($shipment['final_result']) && $shipment['final_result'] == 1) {
            $displayResult = $translator->_('Pass');
        } elseif (isset($shipment['final_result']) && $shipment['final_result'] == 2) {
            $btnClassName = 'btn-danger';
            $displayResult = $translator->_('Fail');
        } elseif (isset($shipment['final_result']) && $shipment['final_result'] == 3) {
            $btnClassName = 'btn-default';
            $displayResult = $translator->_('Excluded');
        } elseif (empty($shipment['final_result'])) {
            $btnClassName = 'btn-default';
        }
        $warnings = Pt_Commons_JsonUtility::safeDecode($shipment['failure_reason']);
        if ($displayResult === '' && empty($shipment['final_result']) && !empty($warnings)) {
            $btnClassName = 'btn-warning';
        }

        $shipmentScore = (isset($shipment['shipment_score']) && $shipment['shipment_score'] !== '') ? $shipment['shipment_score'] : '0';
        $documentationScore = (isset($shipment['documentation_score']) && $shipment['documentation_score'] !== '') ? $shipment['documentation_score'] : '0';

        if (empty($shipment['response_status']) || $shipment['response_status'] === 'noresponse') {
            $responseStatus = $translator->_('Not Responded');
        } elseif ($shipment['response_status'] === 'responded') {
            $responseStatus = $translator->_('Responded');
        } elseif ($shipment['response_status'] === 'late') {
            $responseStatus = $translator->_('Late Response');
        } elseif ($shipment['response_status'] === 'late_submitted') {
            $responseStatus = $translator->_('Late Submission (pending approval)');
        } elseif ($shipment['response_status'] === 'late_rejected') {
            $responseStatus = $translator->_('Late Submission (rejected)');
        } else {
            $responseStatus = $translator->_('Not Tested');
        }

        $expandBtn = '<a href="javascript:void(0);" class="btn btn-xs clicker ' . $btnClassName . '" data-btn-class="' . $btnClassName . '" data-map-id="' . (int) $shipment['map_id'] . '"><i class="icon-plus"></i></a>';

        $participantCell = htmlspecialchars(trim(($shipment['first_name'] ?? '') . ' ' . ($shipment['last_name'] ?? ''))) . ' (' . htmlspecialchars($shipment['unique_identifier'] ?? '') . ')';
        $score = '<div style="text-align:center;">' . htmlspecialchars($shipmentScore) . '</div>';
        $docScore = '<div style="text-align:center;">' . htmlspecialchars($documentationScore) . '</div>';
        $resultCell = '<div style="text-align:center;">' . htmlspecialchars($displayResult !== '' ? $displayResult : $translator->_('Not Evaluated')) . '</div>';
        $responseStatusCell = htmlspecialchars($responseStatus);
        if (!empty($shipment['late_submit_status'])) {
            // late_submit_status stays 1 even after an admin approves the late submission
            // (status flips back to 'responded'), so it doubles as the "was late" history flag.
            if ($shipment['response_status'] === 'late_submitted') {
                $responseStatusCell = '<span class="label label-warning">' . htmlspecialchars($responseStatus) . '</span>';
            } elseif ($shipment['response_status'] === 'late_rejected') {
                $responseStatusCell = '<span class="label label-danger">' . htmlspecialchars($responseStatus) . '</span>';
            } elseif ($shipment['response_status'] === 'responded') {
                $responseStatusCell .= ' <span class="label label-info">' . $translator->_('late — approved') . '</span>';
            }
        }
        $respondedOn = !empty($shipment['shipment_test_report_date'])
            ? (Pt_Commons_DateUtility::humanReadableDateFormat($shipment['shipment_test_report_date'], true) ?? '-')
            : '-';

        // Last Modified cell.
        $updatedByName = trim($shipment['updatedByName'] ?? '');
        $updatedByEmail = trim($shipment['updatedByEmail'] ?? '');
        $updatedOnRaw = (!empty($shipment['updated_on_user']) && $shipment['updated_on_user'] !== '0000-00-00 00:00:00') ? $shipment['updated_on_user'] : '';
        if ($updatedByName !== '' || $updatedByEmail !== '' || $updatedOnRaw !== '') {
            $lastModified = '';
            if ($updatedByName !== '' || $updatedByEmail !== '') {
                $lastModified .= '<strong>' . htmlspecialchars($updatedByName !== '' ? $updatedByName : '—') . '</strong>';
                if ($updatedByEmail !== '') {
                    $lastModified .= '<br><small class="text-body-secondary">(' . htmlspecialchars($updatedByEmail) . ')</small>';
                }
            }
            if ($updatedOnRaw !== '') {
                $updatedOnFormatted = Pt_Commons_DateUtility::humanReadableDateFormat($updatedOnRaw, true);
                $lastModified .= '<br><small class="text-body-secondary">' . htmlspecialchars((string) $updatedOnFormatted) . '</small>';
            }
        } else {
            $lastModified = '<span class="text-muted">&mdash;</span>';
        }

        // Action cell.
        $actionParts = [];
        $viewButton = $this->responseViewButton($shipment);
        if ($viewButton !== '') {
            $actionParts[] = $viewButton;
        }
        if ($canEdit && $rowSchemeType !== '') {
            $actionParts[] = '<a class="btn btn-primary btn-xs" href="' . htmlspecialchars($this->adminResponseUrl($shipment), ENT_QUOTES, 'UTF-8') . '"><span><i class="icon-pencil"></i> ' . $translator->_('Edit') . '</span></a>';
        }

        $shipmentStatus = $shipment['shipment_status'] ?? '';
        $reportDate = $shipment['shipment_test_report_date'] ?? '';
        if ($shipmentStatus !== 'finalized' && $reportDate !== '' && $reportDate !== '0000-00-00 00:00:00') {
            $participantFullName = trim(($shipment['first_name'] ?? '') . ' ' . ($shipment['last_name'] ?? ''));
            $jsonOpts = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;
            $onclick = 'removeSchemes('
                . json_encode($rowSchemeType, $jsonOpts) . ', '
                . json_encode(base64_encode($shipment['map_id']), $jsonOpts) . ', '
                . json_encode($shipment['is_user_configured'] ?? '', $jsonOpts) . ', '
                . json_encode($participantFullName, $jsonOpts) . ', '
                . json_encode($shipment['unique_identifier'] ?? '', $jsonOpts)
                . ')';
            $actionParts[] = '<a class="btn btn-primary btn-xs" href="javascript:void(0);" onclick=\'' . $onclick . '\'><span><i class="icon-remove"></i> ' . $translator->_('Delete') . '</span></a>';
        }
        if ($displayResult === $translator->_('Fail') && !empty($shipment['corrective_action_file'])) {
            $actionParts[] = '<br><a class="btn btn-info btn-xs" href="/uploads/corrective-action-files/' . htmlspecialchars($shipment['corrective_action_file']) . '" download=""><span><i class="icon-download"></i> ' . $translator->_('Download Corrective Action') . '</span></a>';
        }

        // Late-submission review: started on/before the deadline, submitted after it.
        // Approve -> response_status back to 'responded' (kept traceable by
        // late_submit_status = 1); Reject -> 'late_rejected'. A rejected one can still be
        // approved later.
        if ($canEdit && !empty($shipment['late_submit_status'])
            && in_array($shipment['response_status'] ?? '', ['late_submitted', 'late_rejected'], true)) {
            $midArg = json_encode(base64_encode((string) $shipment['map_id']), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            $actionParts[] = '<br><a class="btn btn-success btn-xs" href="javascript:void(0);" onclick=\'decideLateSubmission(' . $midArg . ', "approve")\'><span><i class="icon-ok"></i> ' . $translator->_('Approve Late Submission') . '</span></a>';
            if (($shipment['response_status'] ?? '') === 'late_submitted') {
                $actionParts[] = '<a class="btn btn-danger btn-xs" href="javascript:void(0);" onclick=\'decideLateSubmission(' . $midArg . ', "reject")\'><span><i class="icon-remove"></i> ' . $translator->_('Reject') . '</span></a>';
            }
        }

        $actionCell = '<div style="white-space:nowrap;">' . implode(' ', $actionParts) . '</div>';

        return [
            $expandBtn,
            $participantCell,
            $score,
            $docScore,
            $resultCell,
            $responseStatusCell,
            $respondedOn,
            $lastModified,
            (int) $shipment['map_id'],
            $actionCell,
        ];
    }

    /**
     * Admin decision on a pending late submission (see Application_Service_Shipments::lateSubmissionCapture()).
     *
     * approve -> response_status = 'responded'; reject -> response_status = 'late_rejected'.
     * late_submit_status is left at 1 either way, so the row stays traceable as "was late".
     * Only rows currently flagged late (late_submit_status = 1) and sitting in
     * 'late_submitted' / 'late_rejected' can be decided. Re-evaluating the shipment
     * afterwards is a separate, explicit admin action.
     *
     * @return array{ok: bool, message: string, status?: string}
     */
    public function decideLateSubmission($mapId, string $decision, $adminId = null): array
    {
        $translator = Zend_Registry::get('translate');
        $mapId = (int) $mapId;
        $decision = strtolower(trim($decision));
        if ($mapId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
            return ['ok' => false, 'message' => $translator->_('Invalid request.')];
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $row = $db->fetchRow(
            $db->select()
                ->from(['sp' => 'shipment_participant_map'], ['map_id', 'response_status', 'late_submit_status', 'shipment_id', 'participant_id'])
                ->where('sp.map_id = ?', $mapId)
        );
        if (!$row || (int) $row['late_submit_status'] !== 1
            || !in_array($row['response_status'], ['late_submitted', 'late_rejected'], true)) {
            return ['ok' => false, 'message' => $translator->_('This response is not a pending late submission.')];
        }

        $newStatus = $decision === 'approve' ? 'responded' : 'late_rejected';
        if ($row['response_status'] === $newStatus) {
            return ['ok' => true, 'message' => $translator->_('No change.'), 'status' => $newStatus];
        }

        $db->update('shipment_participant_map', [
            'response_status'  => $newStatus,
            'late_submit_status' => 1,
            'updated_by_admin' => $adminId,
            'updated_on_admin' => new Zend_Db_Expr('now()'),
        ], $db->quoteInto('map_id = ?', $mapId));

        $shipmentCode = (string) $db->fetchOne($db->select()->from('shipment', 'shipment_code')->where('shipment_id = ?', $row['shipment_id']));
        $uid = (string) $db->fetchOne($db->select()->from('participant', 'unique_identifier')->where('participant_id = ?', $row['participant_id']));
        Application_Model_DbTable_AuditLog::record(
            ($decision === 'approve' ? 'Approved' : 'Rejected') . " late submission - {$shipmentCode} / {$uid}",
            'response',
            [
                'entity' => ['type' => 'shipment', 'id' => (int) $row['shipment_id'], 'label' => $shipmentCode],
                'facts' => ['Lab ID' => $uid],
                'changes' => Application_Model_DbTable_AuditLog::changes(
                    ['response_status' => $row['response_status']],
                    ['response_status' => $newStatus]
                ),
                'important' => true,
                'reason' => 'Decides whether a late response is scored',
            ]
        );

        return [
            'ok' => true,
            'status' => $newStatus,
            'message' => $decision === 'approve'
                ? $translator->_('Late submission approved. Its status is now Responded (kept marked as late in history).')
                : $translator->_('Late submission rejected.'),
        ];
    }

    public function getReportStatus($shipmentId, $type = '', $evaluate = false)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($evaluate) {
            $response = [];
            $result = $db->fetchAll($db->select()->from('queue_report_generation')->where('shipment_id = ?', $shipmentId)->order('id desc'));
            foreach ($result as $row) {
                $response[$row['report_type']] = $row;
            }
            return $response;
        } else {
            return $db->fetchRow($db->select()->from('queue_report_generation')->where('shipment_id = ?', $shipmentId)->where('report_type = ?', $type));
        }
    }

    public function getIndividualReportsDataForPDF($shipmentId, $sLimit = null, $sOffset = null)
    {
        $vlGraphResult = [];
        $mapRes = [];
        $shipmentResult = [];
        $vlGraphResult = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $schemeService = new Application_Service_Schemes();
        $sql = $db->select()->from(['s' => 'shipment'], ['s.*'])
            ->join(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['d.distribution_id', 'd.distribution_code', 'd.distribution_date'])
            ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', ['sp.map_id', 'sp.participant_id', 'sp.shipment_test_date', 'sp.shipment_receipt_date', 'sp.shipment_test_report_date', 'sp.supervisor_approval', 'sp.final_result', 'sp.failure_reason', 'sp.shipment_score', 'sp.final_result', 'sp.attributes', 'sp.is_followup', 'sp.is_excluded', 'sp.optional_eval_comment', 'sp.evaluation_comment', 'sp.documentation_score', 'sp.participant_supervisor', 'sp.custom_field_1', 'sp.custom_field_2', 'sp.specimen_volume', 'sp.manual_override', 'sp.user_comment', 'sp.shipment_test_report_date', 'sp.response_status', 'sp.is_pt_test_not_performed', 'sp.shipment_test_date', 'sp.pt_not_tested_reason', 'sp.pt_test_not_performed_comments', 'sp.pt_support_comments'])
            ->join(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['sl.scheme_id', 'sl.scheme_name', 'is_user_configured', 'user_test_config'])
            ->join(
                ['p' => 'participant'],
                'p.participant_id=sp.participant_id',
                [
                    'p.unique_identifier',
                    'p.first_name',
                    'p.last_name',
                    'p.status',
                    'p.institute_name',
                    'p.state',
                    'p.city',
                    'p.district',
                    'p.phone',
                    'p.mobile',
                    'p.email',
                    'p.region',
                    'p.site_type',
                    'p.department_name',
                    'labName' => new Zend_Db_Expr("
							CASE WHEN p.`lab_name` != '' THEN p.`lab_name` ELSE
								COALESCE(
									CONCAT(
										' ',
										CASE WHEN p.first_name != '' THEN p.first_name ELSE NULL END
									),
									CONCAT(
										' ',
										CASE WHEN p.last_name != '' THEN p.last_name ELSE NULL END
									)
								)
							END
						"),
                ]
            )
            //->joinLeft(array('rst' => 'r_site_type'), 'p.site_type=rst.r_stid', array('siteType' => 'rst.site_type'))
            ->joinLeft(['rta' => 'r_tb_assay'], 'rta.id=json_unquote(
                json_extract(
                    sp.attributes,
                    "$.assay_name"
                )
            )', ['assayName' => 'name', 'assayShortName' => 'short_name'])
            ->joinLeft(['c' => 'countries'], 'p.country=c.id', ['iso_name'])
            ->joinLeft(['rnt' => 'r_response_not_tested_reasons'], 'rnt.ntr_id=sp.pt_not_tested_reason', ['ntr_reason', 'reason_code'])
            ->joinLeft(['res' => 'r_results'], 'res.result_id=sp.final_result', ['result_name'])
            ->joinLeft(['ec' => 'r_evaluation_comments'], 'ec.comment_id=sp.evaluation_comment', ['evaluationComments' => 'comment'])
            ->where('s.shipment_id = ?', $shipmentId);

        $sql->where(new Zend_Db_Expr(self::sqlGetsIndividualReport('sp')));

        // Workers each fetch one page at the same time. Without a fixed order,
        // MySQL can return rows in a different order to each query, so pages
        // overlap and some participants get no report.
        $sql->order('sp.map_id');
        if (isset($sLimit) && isset($sOffset)) {
            $sql = $sql->limit($sLimit, $sOffset);
        }
        $sRes = $shipmentResult = $db->fetchAll($sql);
        if (empty($sRes)) {
            return [
                'shipment' => [],
                'dmResult' => [],
                'vlGraphResult' => [],
                'meanScore' => [],
            ];
        }

        $meanScore = [];
        $tableType = ($shipmentResult[0]['is_user_configured'] == 'yes') ? 'generic_test' : $shipmentResult[0]['scheme_type'];

        if (Application_Service_Common::isInstance('malawi')) {
            $q = $db->select()->from(['spm' => 'shipment_participant_map'], [
                'mean_score' => new Zend_Db_Expr('AVG(spm.shipment_score + spm.documentation_score)'),
            ])
                ->where('spm.shipment_id = ?', $shipmentId)
                ->where("IFNULL(spm.response_status, 'noresponse') = 'responded'")
                ->where("IFNULL(spm.is_excluded, 'no') = 'no'")
                ->group('spm.shipment_id');
            $meanScore = $db->fetchRow($q);
        }
        $i = 0;
        $mapRes = [];

        // Preload participant → data manager mapping for this chunk to avoid N+1 queries.
        $participantIds = array_values(array_unique(array_map(static function ($row) {
            return (int) ($row['participant_id'] ?? 0);
        }, $sRes)));
        $participantIds = array_filter($participantIds);
        $dmByParticipant = [];
        if (!empty($participantIds)) {
            $dmRows = $db->fetchAll(
                $db->select()
                    ->from(['pmm' => 'participant_manager_map'], ['participant_id', 'dm_id'])
                    ->join(['dm' => 'data_manager'], 'dm.dm_id=pmm.dm_id', ['institute'])
                    ->where('pmm.participant_id IN (?)', $participantIds)
            );
            foreach ($dmRows as $dmRow) {
                $pid = (int) ($dmRow['participant_id'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }
                if (!isset($dmByParticipant[$pid])) {
                    $dmByParticipant[$pid] = [];
                }
                $dmByParticipant[$pid][] = $dmRow;
            }
        }

        // The Zimbabwe reports don't use these analytics fields and computing them is expensive.
        $includeAnalytics = !Application_Service_Common::isInstance('zimbabwe');
        $shipmentStatistics = [];
        $shipmentPerformance3 = [];
        if ($includeAnalytics) {
            // Survey-wide participation counts, constant per shipment. Sourced from the
            // canonical participation-stats helper so the report's "STATISTICS FOR THIS
            // SURVEY" table matches the shipment dashboard exactly. Pass/Fail come from
            // final_result there, so non-responders and "unable to test" participants are
            // never miscounted as sub-threshold failures.
            $participation = Application_Service_Shipments::getShipmentParticipationStats($shipmentId);
            $shipmentStatistics = [
                'number_responded'     => $participation['responded'],
                'number_not_responded' => $participation['not_responded'],
                'providersWith100'     => $participation['scored_full'],
                'providers>80'         => $participation['passed'],
                'providers<80'         => $participation['failed'],
            ];

            $unionQuery = $db->select()->from('response_result_' . $tableType, ['sample_id', 'shipment_map_id', 'calculated_score'])
                ->where('shipment_map_id IN (
                            SELECT shipment_participant_map.map_id
                            FROM shipment_participant_map
                            where shipment_participant_map.shipment_id = ' . (int) $shipmentId . ')');

            // Get sample count for this shipment from reference_result_dts
            $sampleCountRow = $db->fetchRow(
                $db->select()
                    ->from('reference_result_dts', [
                        'sample_count' => new Zend_Db_Expr('COUNT(sample_id)'),
                    ])
                    ->where('shipment_id = ?', (int) $shipmentId)
            );

            $sampleCount = (!empty($sampleCountRow['sample_count'])) ? (int) $sampleCountRow['sample_count'] : 0;

            // Avoid division by zero — fall back to a sane default if no samples found
            $dynamicScoreValue = ($sampleCount > 0) ? (100 / $sampleCount) : 20;

            $scoreType = ($tableType != 'generic_test') ? 'Pass' : $dynamicScoreValue;

            // When scoreType is 'Pass', rounding doesn't make sense, so only compare the raw value.
            // When scoreType is numeric, compare both the rounded and raw value (as before).
            if ($scoreType === 'Pass') {
                $passedCondition = "(rrd.calculated_score = '" . $scoreType . "')";
                $failedCondition = "(rrd.calculated_score != '" . $scoreType . "')";
            } else {
                $passedCondition = "(ROUND(rrd.calculated_score) = '" . $scoreType . "')";
                $failedCondition = "(ROUND(rrd.calculated_score) != '" . $scoreType . "')";
            }

            $performance3Sql = $db->select()->from(['ref' => 'reference_result_' . $tableType], [
                'distribution_code' => new Zend_Db_Expr("'" . $shipmentResult[0]['distribution_code'] . "'"),
                'ref.sample_label',
                'rrd.sample_id',
                'passed' => new Zend_Db_Expr('COUNT(DISTINCT CASE WHEN ' . $passedCondition . ' THEN rrd.shipment_map_id ELSE NULL END)'),
                'failed' => new Zend_Db_Expr('COUNT(DISTINCT CASE WHEN ' . $failedCondition . ' THEN rrd.shipment_map_id ELSE NULL END)'),
            ])
                ->join(['rrd' => $unionQuery], 'rrd.sample_id=ref.sample_id', [])
                // sample_id restarts at 1 in every shipment, so without this the label can come
                // from another shipment's reference row
                ->where('ref.shipment_id = ?', (int) $shipmentId)
                ->group(['rrd.sample_id'])
                ->order('rrd.sample_id ASC');

            $shipmentPerformance3 = $db->fetchAll($performance3Sql);
        }

        // Preload response results for DTS in one query to avoid N+1 queries per participant.
        $mapIds = array_values(array_unique(array_map(static function ($row) {
            return (int) ($row['map_id'] ?? 0);
        }, $sRes)));
        $mapIds = array_filter($mapIds);
        $dtsResponseByMapId = [];
        if (!empty($mapIds) && ($shipmentResult[0]['scheme_type'] ?? '') === 'dts') {
            $dtsQuery = $db->select()->from(
                ['resdts' => 'response_result_dts'],
                [
                    'resdts.shipment_map_id',
                    'resdts.sample_id',
                    'resdts.reported_result',
                    'calculated_score',
                    'algorithm_result',
                    'interpretation_result',
                    'test_kit_name_1',
                    'lot_no_1',
                    'exp_date_1',
                    'test_kit_name_2',
                    'lot_no_2',
                    'exp_date_2',
                    'test_kit_name_3',
                    'lot_no_3',
                    'exp_date_3',
                    'test_result_1',
                    'test_result_2',
                    'test_result_3',
                    'repeat_test_result_1',
                    'repeat_test_result_2',
                    'repeat_test_result_3',
                    'is_this_retest',
                    'syphilis_result',
                    'syphilis_final',
                    'dts_rtri_control_line',
                    'dts_rtri_diagnosis_line',
                    'dts_rtri_longterm_line',
                    'dts_rtri_reported_result',
                    'dts_rtri_is_editable',
                    'kit_additional_info',
                    // Vietnam (NIHE) carries assessment weight on the lab's comment
                    // ("Sent for confirmation" / "Retest after 14 days") — the participant
                    // report renders it beside the conclusion.
                    'lab_comment',
                ]
            )
                ->joinLeft(['respr' => 'r_possibleresult'], 'respr.id=resdts.reported_result', ['labResult' => 'respr.response'])
                ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.map_id=resdts.shipment_map_id', ['sp.shipment_id', 'sp.shipment_receipt_date', 'sp.participant_id', 'responseDate' => 'sp.shipment_test_report_date', 'sp.attributes', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_test_date', 'sp.failure_reason'])
                ->joinLeft(['refdts' => 'reference_result_dts'], 'refdts.shipment_id=sp.shipment_id and refdts.sample_id=resdts.sample_id', ['refdts.reference_result', 'refdts.sample_label', 'refdts.mandatory', 'refdts.sample_score', 'refdts.control', 'dts_rtri_reference_result'])
                ->joinLeft(['dtstk1' => 'r_testkitnames'], 'dtstk1.TestKitName_ID=resdts.test_kit_name_1', ['testkit1' => 'dtstk1.TestKit_Name'])
                ->joinLeft(['dtstk2' => 'r_testkitnames'], 'dtstk2.TestKitName_ID=resdts.test_kit_name_2', ['testkit2' => 'dtstk2.TestKit_Name'])
                ->joinLeft(['dtstk3' => 'r_testkitnames'], 'dtstk3.TestKitName_ID=resdts.test_kit_name_3', ['testkit3' => 'dtstk3.TestKit_Name'])
                ->joinLeft(['refpr' => 'r_possibleresult'], 'refpr.id=refdts.reference_result', ['referenceResult' => 'refpr.response', 'referenceResultCode' => 'refpr.result_code'])
                ->joinLeft(['refSypPR' => 'r_possibleresult'], 'refSypPR.id=refdts.syphilis_reference_result', ['referenceSyphilisResult' => 'refSypPR.response', 'referenceSyphilisResultCode' => 'refSypPR.result_code'])
                ->joinLeft(['refRtriPr' => 'r_possibleresult'], 'refRtriPr.id=resdts.dts_rtri_reported_result', ['dtsRtriReportedResult' => 'refSypPR.response', 'dtsRtriReportedResultCode' => 'refSypPR.result_code'])
                ->where('resdts.shipment_map_id IN (?)', $mapIds)
                ->order(['resdts.shipment_map_id ASC', 'refdts.sample_id ASC']);

            $dtsRows = $db->fetchAll($dtsQuery);
            foreach ($dtsRows as $dtsRow) {
                $mid = (int) ($dtsRow['shipment_map_id'] ?? 0);
                if ($mid <= 0) {
                    continue;
                }
                if (!isset($dtsResponseByMapId[$mid])) {
                    $dtsResponseByMapId[$mid] = [];
                }
                $dtsResponseByMapId[$mid][] = $dtsRow;
            }
        }

        // Preload TB response results and consensus once per chunk to avoid N+1 queries.
        $tbResponseByMapId = [];
        $tbConsensus = null;
        if (!empty($mapIds) && ($shipmentResult[0]['scheme_type'] ?? '') === 'tb') {
            $tbModel = new Application_Model_Tb();
            $tbResponseByMapId = $tbModel->getDataForIndividualPDFBatch($mapIds);
            $tbConsensus = $tbModel->getConsensusResults($shipmentId); // Fetch consensus once
        }

        // Preload VL response results once per chunk to avoid N+1 queries.
        $vlResponseByMapId = [];
        if (!empty($mapIds) && ($shipmentResult[0]['scheme_type'] ?? '') === 'vl') {
            $vlModel = new Application_Model_Vl();
            $shipmentAttrsJson = $shipmentResult[0]['shipment_attributes'] ?? '{}';
            $vlResponseByMapId = $vlModel->getDataForIndividualPDFBatch($shipmentId, $mapIds, $shipmentAttrsJson);
        }

        // Preload EID response results once per chunk to avoid N+1 queries.
        $eidResponseByMapId = [];
        $eidExtractionAssay = [];
        $eidDetectionAssay = [];
        if (!empty($mapIds) && ($shipmentResult[0]['scheme_type'] ?? '') === 'eid') {
            // Preload assay lookups (static data)
            $eidExtractionAssay = $schemeService->getEidExtractionAssay();
            $eidDetectionAssay = $schemeService->getEidDetectionAssay();

            // Batch fetch all EID response results
            $eidQuery = $db->select()->from(['reseid' => 'response_result_eid'], ['reseid.shipment_map_id', 'reseid.sample_id', 'reseid.reported_result'])
                ->join(['respr' => 'r_possibleresult'], 'respr.id=reseid.reported_result', ['labResult' => 'respr.response'])
                ->join(['sp' => 'shipment_participant_map'], 'sp.map_id=reseid.shipment_map_id', ['sp.shipment_id', 'sp.participant_id', 'sp.shipment_receipt_date', 'sp.shipment_test_date', 'sp.attributes', 'responseDate' => 'sp.shipment_test_report_date', 'sp.failure_reason'])
                ->join(['refeid' => 'reference_result_eid'], 'refeid.shipment_id=sp.shipment_id and refeid.sample_id=reseid.sample_id', ['refeid.reference_result', 'refeid.sample_label', 'refeid.mandatory', 'refeid.sample_score'])
                ->join(['refpr' => 'r_possibleresult'], 'refpr.id=refeid.reference_result', ['referenceResult' => 'refpr.response'])
                ->where('refeid.control = 0')
                ->where(new Zend_Db_Expr("IFNULL(sp.is_excluded, 'no') = 'no'"))
                ->where('reseid.shipment_map_id IN (?)', $mapIds)
                ->order(['reseid.shipment_map_id ASC', 'refeid.sample_id ASC']);

            $eidRows = $db->fetchAll($eidQuery);
            foreach ($eidRows as $eidRow) {
                $mid = (int) ($eidRow['shipment_map_id'] ?? 0);
                if ($mid <= 0) {
                    continue;
                }
                // Post-process with assay names
                $rowAttributes = Pt_Commons_JsonUtility::safeDecode($eidRow['attributes']);
                $eidRow['extraction_assay_name'] = $eidExtractionAssay[$rowAttributes['extraction_assay']] ?? null;
                $eidRow['vl_assay'] = $eidDetectionAssay[$rowAttributes['extraction_assay']] ?? null;

                if (!isset($eidResponseByMapId[$mid])) {
                    $eidResponseByMapId[$mid] = [];
                }
                $eidResponseByMapId[$mid][] = $eidRow;
            }
        }

        $mapIdsToUpdate = [];
        foreach ($sRes as $res) {
            $pid = (int) ($res['participant_id'] ?? 0);
            $dmResult = ($pid > 0 && isset($dmByParticipant[$pid])) ? $dmByParticipant[$pid] : [];
            if (isset($res['last_name']) && trim($res['last_name']) != '') {
                $res['last_name'] = '_' . $res['last_name'];
            }

            foreach ($dmResult as $dmRes) {
                $participantFileName = $res['shipment_code'] . '-' . ($res['map_id']);
                $participantFileName = str_replace(' ', '-', $participantFileName);
                $dmId = (int) ($dmRes['dm_id'] ?? 0);
                $dmParticipantId = (int) ($dmRes['participant_id'] ?? 0);
                if ($dmId <= 0 || $dmParticipantId <= 0) {
                    continue;
                }
                if (array_key_exists($dmId, $mapRes)) {
                    $mapRes[$dmId] .= ',' . $dmParticipantId . '#' . $participantFileName;
                } else {
                    $mapRes[$dmId] = $dmParticipantId . '#' . $participantFileName;
                }
            }
            if ($res['scheme_type'] == 'dts') {
                $mid = (int) ($res['map_id'] ?? 0);
                $shipmentResult[$i]['responseResult'] = $mid > 0 && isset($dtsResponseByMapId[$mid]) ? $dtsResponseByMapId[$mid] : [];
            } elseif ($res['scheme_type'] == 'recency') {

                $sQuery = $db->select()->from(['resrecency' => 'response_result_recency'], ['resrecency.shipment_map_id', 'resrecency.sample_id', 'resrecency.reported_result', 'calculated_score', 'control_line', 'diagnosis_line', 'longterm_line'])
                    ->join(['respr' => 'r_possibleresult'], 'respr.id=resrecency.reported_result', ['labResult' => 'respr.response'])
                    ->join(['sp' => 'shipment_participant_map'], 'sp.map_id=resrecency.shipment_map_id', ['sp.shipment_score', 'sp.shipment_id', 'sp.shipment_receipt_date', 'sp.participant_id', 'responseDate' => 'sp.shipment_test_report_date', 'sp.attributes', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_test_date', 'sp.failure_reason'])
                    ->join(['refrecency' => 'reference_result_recency'], 'refrecency.shipment_id=sp.shipment_id and refrecency.sample_id=resrecency.sample_id', ['refrecency.reference_result', 'refControlLine' => 'refrecency.reference_control_line', 'refverificationLine' => 'refrecency.reference_diagnosis_line', 'refLongTermLine' => 'refrecency.reference_longterm_line', 'refrecency.sample_label', 'refrecency.mandatory', 'refrecency.sample_score', 'refrecency.control'])
                    ->join(['refpr' => 'r_possibleresult'], 'refpr.id=refrecency.reference_result', ['referenceResult' => 'refpr.response'])
                    ->where('resrecency.shipment_map_id = ?', $res['map_id']);
                $shipmentResult[$i]['responseResult'] = $db->fetchAll($sQuery);
                //Zend_Debug::dump($shipmentResult);
            } elseif ($res['scheme_type'] == 'eid') {
                // Use batch-loaded EID response data to avoid N+1 queries
                $attributes = Pt_Commons_JsonUtility::safeDecode($res['attributes']);

                // Set participant-specific assay values (use preloaded lookup tables)
                if (isset($attributes['extraction_assay'])) {
                    $shipmentResult[$i]['extractionAssayVal'] = $eidExtractionAssay[$attributes['extraction_assay']] ?? '';
                }
                if (isset($attributes['detection_assay'])) {
                    $shipmentResult[$i]['detectionAssayVal'] = $eidDetectionAssay[$attributes['detection_assay']] ?? '';
                }

                // Use batch-loaded response results
                $mid = (int) ($res['map_id'] ?? 0);
                if ($mid > 0 && isset($eidResponseByMapId[$mid])) {
                    $shipmentResult[$i]['responseResult'] = $eidResponseByMapId[$mid];
                } else {
                    $shipmentResult[$i]['responseResult'] = [];
                }
            } elseif ($res['scheme_type'] == 'vl') {
                // Use batch-loaded VL response data to avoid N+1 queries
                $mid = (int) ($res['map_id'] ?? 0);
                if ($mid > 0 && isset($vlResponseByMapId[$mid])) {
                    $shipmentResult[$i]['responseResult'] = $vlResponseByMapId[$mid];
                } else {
                    $shipmentResult[$i]['responseResult'] = [];
                }
            } elseif ($res['scheme_type'] == 'covid19') {

                $sQuery = $db->select()->from(['resc19' => 'response_result_covid19'], ['resc19.shipment_map_id', 'resc19.sample_id', 'resc19.reported_result', 'calculated_score', 'test_type_1', 'lot_no_1', 'exp_date_1', 'test_type_2', 'lot_no_2', 'exp_date_2', 'test_type_3', 'lot_no_3', 'exp_date_3', 'test_result_1', 'test_result_2', 'test_result_3'])
                    ->join(['respr' => 'r_possibleresult'], 'respr.id=resc19.reported_result', ['labResult' => 'respr.response'])
                    ->join(['sp' => 'shipment_participant_map'], 'sp.map_id=resc19.shipment_map_id', ['sp.shipment_id', 'sp.shipment_receipt_date', 'responseDate' => 'sp.shipment_test_report_date', 'sp.participant_id', 'sp.attributes', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_test_date', 'sp.failure_reason', 'sp.specimen_volume'])
                    ->join(['refc19' => 'reference_result_covid19'], 'refc19.shipment_id=sp.shipment_id and refc19.sample_id=resc19.sample_id', ['refc19.reference_result', 'refc19.sample_label', 'refc19.mandatory', 'refc19.sample_score', 'refc19.control'])
                    ->joinLeft(['c19tk1' => 'r_test_type_covid19'], 'c19tk1.test_type_id=resc19.test_type_1', ['testPlatform1' => 'c19tk1.test_type_name'])
                    ->joinLeft(['c19tk2' => 'r_test_type_covid19'], 'c19tk2.test_type_id=resc19.test_type_2', ['testPlatform2' => 'c19tk2.test_type_name'])
                    ->joinLeft(['c19tk3' => 'r_test_type_covid19'], 'c19tk3.test_type_id=resc19.test_type_3', ['testPlatform3' => 'c19tk3.test_type_name'])
                    ->join(['refpr' => 'r_possibleresult'], 'refpr.id=refc19.reference_result', ['referenceResult' => 'refpr.response'])
                    ->where('resc19.shipment_map_id = ?', $res['map_id']);

                $shipmentResult[$i]['responseResult'] = $db->fetchAll($sQuery);
                //Zend_Debug::dump($shipmentResult);
            } elseif ($res['scheme_type'] == 'tb') {
                // Use batch-loaded TB response data to avoid N+1 queries
                $mid = (int) ($res['map_id'] ?? 0);
                if ($mid > 0 && isset($tbResponseByMapId[$mid])) {
                    $shipmentResult[$i]['responseResult'] = $tbResponseByMapId[$mid];
                } else {
                    $shipmentResult[$i]['responseResult'] = [];
                }
                // Consensus is already preloaded once per chunk (not per participant)
                $shipmentResult[$i]['consensusResult'] = $tbConsensus;
            } elseif ($res['is_user_configured'] == 'yes') {

                $sQuery = $db->select()->from(
                    ['reseid' => 'response_result_generic_test'],
                    ['reseid.shipment_map_id', 'reseid.sample_id', 'reseid.result_1', 'reseid.result_2', 'reseid.result_3', 'reseid.reported_result', 'calculated_score', 'additional_detail', 'z_score']
                )
                    ->joinLeft(
                        ['spm' => 'shipment_participant_map'],
                        'spm.map_id=reseid.shipment_map_id',
                        ['spm.shipment_id', 'spm.participant_id', 'spm.shipment_receipt_date', 'spm.shipment_test_date', 'spm.attributes', 'responseDate' => 'spm.shipment_test_report_date', 'spm.failure_reason']
                    )
                    ->joinLeft(
                        ['refeid' => 'reference_result_generic_test'],
                        'refeid.shipment_id=spm.shipment_id and refeid.sample_id=reseid.sample_id',
                        ['refeid.reference_result', 'refeid.sample_label', 'refeid.mandatory']
                    )
                    ->joinLeft(
                        ['rgtc' => 'reference_generic_test_calculations'],
                        'spm.shipment_id=rgtc.shipment_id and reseid.sample_id=rgtc.sample_id',
                        ['median', 'sd']
                    )
                    // ->where("refeid.control = 0")
                    ->where(new Zend_Db_Expr("IFNULL(spm.is_excluded, 'no') = 'no'  OR spm.is_excluded = '' "))
                    ->where('reseid.shipment_map_id = ?', $res['map_id'])
                    ->order(['refeid.sample_id'])
                    ->group(['refeid.sample_id']);
                $result = $db->fetchAll($sQuery);
                $response = [];
                foreach ($result as $key => $row) {
                    if (isset($row['attributes'])) {
                        //$attributes = Pt_Commons_JsonUtility::safeDecode($row['attributes']);
                    }
                    $response[$key] = $row;
                }
                $shipmentResult[$i]['responseResult'] = $response;
            }

            if ($includeAnalytics) {
                $shipmentResult[$i]['statistics'] = $shipmentStatistics;

                // PT Survey Participant Scored
                /* To get the last 4 PT survey */
                $noSurveySql = $db->select()->from(['d' => 'distributions'], ['distribution_code'])
                    ->join(['s' => 'shipment'], 'd.distribution_id=s.distribution_id', [''])
                    ->join(['spm' => 'shipment_participant_map'], 's.shipment_id=spm.shipment_id', ['scored' => new Zend_Db_Expr('(spm.shipment_score + spm.documentation_score)')])
                    ->where('spm.participant_id = ?', $res['participant_id'])
                    ->where('s.scheme_type = ?', $res['scheme_type'])
                    ->where('DATE(d.distribution_date) < ?', $res['distribution_date'])
                    ->limit(4);
                $noSurvey = $db->fetchAll($noSurveySql);
                $surveysList = [];
                foreach ($noSurvey as $row) {
                    $surveysList[] = (string) $row['distribution_code'];
                }
                $surveysList[] = (string) $res['distribution_code'];

                /* Chart 1 Your Performance for the last 5 surveys */
                $performance1Sql = $db->select()->from(['d' => 'distributions'], ['distribution_code'])
                    ->join(['s' => 'shipment'], 'd.distribution_id=s.distribution_id', [''])
                    ->join(['spm' => 'shipment_participant_map'], 's.shipment_id=spm.shipment_id', ['is_excluded', 'scored' => new Zend_Db_Expr('(spm.shipment_score + spm.documentation_score)')])
                    ->where('spm.participant_id = ?', $res['participant_id'])
                    ->where('s.scheme_type = ?', $res['scheme_type'])
                    ->where('d.distribution_code IN (?)', $surveysList)
                    ->where('DATE(d.distribution_date) <= ?', $res['distribution_date'])
                    ->order('d.distribution_date ASC')
                    ->group(['d.distribution_id'])->limit(5);
                $shipmentResult[$i]['performance1'] = $db->fetchAll($performance1Sql);

                /* Chart 2 Participants performance for the last 5 surveys */
                $performancePassFaile2Sql = $db->select()->from(['d' => 'distributions'], ['distribution_code'])
                    ->join(['s' => 'shipment'], 'd.distribution_id=s.distribution_id', [''])
                    ->join(['spm' => 'shipment_participant_map'], 's.shipment_id=spm.shipment_id', [
                        'passed' => new Zend_Db_Expr('SUM(CASE WHEN (spm.response_status = \'responded\' AND spm.final_result = 1) THEN 1 ELSE 0 END)'),
                        'failed' => new Zend_Db_Expr('SUM(CASE WHEN (spm.response_status = \'responded\' AND spm.final_result = 2) THEN 1 ELSE 0 END)'),
                    ])
                    ->where('s.scheme_type = ?', $res['scheme_type'])
                    ->where('d.distribution_code IN (?)', $surveysList)
                    ->where('DATE(d.distribution_date) <= ?', $res['distribution_date'])
                    ->order('d.distribution_date ASC')
                    ->group(['d.distribution_id'])->limit(5);
                $shipmentResult[$i]['performance2'] = $db->fetchAll($performancePassFaile2Sql);

                $shipmentResult[$i]['performance3'] = $shipmentPerformance3;
            }
            // PT Survey Participant Pass / Fail
            $i++;
            $mapIdsToUpdate[] = (int) $res['map_id'];
        }

        // NOTE: report_generated is now updated in generate-shipment-reports.php
        // AFTER the actual PDF files are created, not here during data fetch.

        // No status write here. This only reads data, and it also runs when an admin
        // opens the Distribution or Finalize page. It used to set the shipment to
        // 'evaluated', so viewing a shipment mid-run flipped it out of 'processing'
        // and offered Regenerate and Finalize on a half-written report set. The
        // generator sets the status when a run starts and when it ends.

        $result = [
            'shipment' => $shipmentResult,
            'dmResult' => $mapRes,
            'vlGraphResult' => $vlGraphResult,
            'meanScore' => $meanScore,
        ];
        return self::escapeReportText($result);
    }

    public function getSummaryReportsDataForPDF($shipmentId, $testType = '')
    {
        $vlCalculation = $penResult = $shipmentResult = [];
        $dtsPasspercentage = Pt_Commons_SchemeConfig::get('dts.passPercentage') ?? 100;

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['s' => 'shipment'], ['s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date', 's.response_deadline', 's.max_score', 'shipment_attributes', 's.pt_co_ordinator_name', 's.pt_co_ordinator_phone', 's.pt_co_ordinator_email', 'shipment_comment', 's.issuing_authority'])
            ->join(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['sl.scheme_name', 'is_user_configured'])
            ->join(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['d.distribution_code'])
            ->where('s.shipment_id = ?', $shipmentId);
        $shipmentResult = $db->fetchRow($sql);
        if (!empty($shipmentResult)) {
            // No status write: see getShipmentToEvaluateReports(). The generator sets
            // the final status after the summary is written.

            if ($shipmentResult['scheme_type'] == 'dts') {
                $pass = $dtsPasspercentage ?? 100;
                $sql = $db->select()->from(
                    ['refdts' => 'reference_result_dts'],
                    [
                        'refdts.reference_result',
                        'refdts.sample_label',
                        'refdts.mandatory',
                    ]
                )
                    ->join(
                        ['refpr' => 'r_possibleresult'],
                        'refpr.id=refdts.reference_result',
                        ['referenceResult' => 'refpr.response']
                    )
                    ->where('refdts.shipment_id = ?', $shipmentResult['shipment_id']);
                $sqlRes = $db->fetchAll($sql);

                $shipmentResult['referenceResult'] = $sqlRes;

                $tests = [
                    'testkitid' => 'TestKitName_ID',
                    'testkitname' => 'TestKit_Name',
                    'Test-1' => new Zend_Db_Expr('SUM(CASE WHEN (rrd.test_kit_name_1 = rtd.TestKitName_ID) THEN 1 ELSE 0 END)'),
                    'Test-2' => new Zend_Db_Expr('SUM(CASE WHEN (rrd.test_kit_name_2 = rtd.TestKitName_ID) THEN 1 ELSE 0 END)'),
                    'Test-3' => new Zend_Db_Expr('SUM(CASE WHEN (rrd.test_kit_name_3 = rtd.TestKitName_ID) THEN 1 ELSE 0 END)'),
                    'Test-1-Repeat' => new Zend_Db_Expr('SUM(CASE WHEN (rrd.repeat_test_kit_name_1 = rtd.TestKitName_ID) THEN 1 ELSE 0 END)'),
                    'Test-2-Repeat' => new Zend_Db_Expr('SUM(CASE WHEN (rrd.repeat_test_kit_name_2 = rtd.TestKitName_ID) THEN 1 ELSE 0 END)'),
                    'Test-3-Repeat' => new Zend_Db_Expr('SUM(CASE WHEN (rrd.repeat_test_kit_name_3 = rtd.TestKitName_ID) THEN 1 ELSE 0 END)'),
                ];
                $testkitGroup = ['rrd.test_kit_name_1', 'rrd.test_kit_name_2', 'rrd.test_kit_name_3', 'rrd.repeat_test_kit_name_1', 'rrd.repeat_test_kit_name_2', 'rrd.repeat_test_kit_name_3'];
                $testkitjoin = 'rtd.TestKitName_ID = rrd.test_kit_name_1
				OR rtd.TestKitName_ID = rrd.test_kit_name_2
				OR rtd.TestKitName_ID = rrd.test_kit_name_3
				OR rtd.TestKitName_ID = rrd.repeat_test_kit_name_1
				OR rtd.TestKitName_ID = rrd.repeat_test_kit_name_2
				OR rtd.TestKitName_ID = rrd.repeat_test_kit_name_3';
                if (isset($testType) && !empty($testType) && $testType == 'screening') {
                    $tests = [
                        'testkitid' => 'TestKitName_ID',
                        'testkitname' => 'TestKit_Name',
                        'Test-1' => new Zend_Db_Expr('SUM(CASE WHEN (rrd.test_kit_name_1 = rtd.TestKitName_ID) THEN 1 ELSE 0 END)'),
                        'Test-1-Repeat' => new Zend_Db_Expr('SUM(CASE WHEN (rrd.repeat_test_kit_name_1 = rtd.TestKitName_ID) THEN 1 ELSE 0 END)'),
                    ];
                    $testkitGroup = ['rrd.test_kit_name_1', 'rrd.repeat_test_kit_name_1'];
                    $testkitjoin = 'rtd.TestKitName_ID = rrd.test_kit_name_1 OR rtd.TestKitName_ID = rrd.repeat_test_kit_name_1';
                }
                /* Test Kit Report for summary pdf */
                $tksql = $db->select()->from(['rtd' => 'r_testkitnames'], $tests)
                    ->join(['rrd' => 'response_result_dts'], $testkitjoin, [''])
                    ->join(['spm' => 'shipment_participant_map'], 'rrd.shipment_map_id=spm.map_id', [])
                    ->join(['s' => 'shipment'], 'spm.shipment_id=s.shipment_id', ['shipment_code'])
                    ->where('s.shipment_id = ?', $shipmentResult['shipment_id'])
                    ->group(['rtd.TestKitName_ID']);
                // ->order('total desc');

                if (isset($testType) && !empty($testType)) {
                    $tksql = $tksql->where("JSON_EXTRACT(spm.attributes, '$.dts_test_panel_type') = ?", $testType);
                }
                // $shipmentResult['testKit'] = $db->fetchAll($tksql);
                $tksql->group($testkitGroup);
                $shipmentResult['testKitByTestNumber'] = $db->fetchAll($tksql);

                // testkit chart
                $tkcsql = $db->select()->from(['rtd' => 'r_testkitnames'], [
                    'testkitid' => 'TestKitName_ID',
                    'testkitname' => 'TestKit_Name',
                    'Pass' => new Zend_Db_Expr("SUM(CASE WHEN (rrd.calculated_score like 'Pass') THEN 1 ELSE 0 END)"),
                    'Fail' => new Zend_Db_Expr("SUM(CASE WHEN (rrd.calculated_score like 'Fail') THEN 1 ELSE 0 END)"),
                ])
                    ->join(['rrd' => 'response_result_dts'], $testkitjoin, [''])
                    ->join(['spm' => 'shipment_participant_map'], 'rrd.shipment_map_id=spm.map_id', [
                        'number_responded' => new Zend_Db_Expr("SUM(CASE WHEN (spm.response_status is not null and spm.response_status = 'responded') THEN 1 ELSE 0 END)"),
                        'number_failed' => new Zend_Db_Expr('SUM(CASE WHEN (spm.final_result = 2 AND spm.shipment_test_date <= DATE(s.response_deadline)) THEN 1 ELSE 0 END)'),
                        'number_passed' => new Zend_Db_Expr('SUM(CASE WHEN (spm.final_result = 1 AND spm.shipment_test_date <= DATE(s.response_deadline)) THEN 1 ELSE 0 END)'),
                        'reported_count' => new Zend_Db_Expr("SUM(response_status IN ('responded', 'nottested'))"),
                    ])
                    ->join(['p' => 'participant'], 'p.participant_id=spm.participant_id', [''])
                    ->join(['s' => 'shipment'], 'spm.shipment_id=s.shipment_id', ['shipment_code'])
                    ->where('s.shipment_id = ?', $shipmentResult['shipment_id'])
                    ->group(['rtd.TestKitName_ID'])
                    ->order(['number_failed desc'])
                    ->order(['number_passed desc']);
                if (isset($testType) && !empty($testType)) {
                    $tkcsql = $tkcsql->where("JSON_EXTRACT(spm.attributes, '$.dts_test_panel_type') = ?", $testType);
                }

                $shipmentResult['testKitChart'] = $db->fetchAll($tkcsql);
                $sQuery = $db->select()->from(
                    ['spm' => 'shipment_participant_map'],
                    [
                        'spm.map_id',
                        'spm.shipment_id',
                        'spm.shipment_score',
                        'spm.documentation_score',
                        'spm.attributes',
                        'spm.is_excluded',
                        'number_failed' => new Zend_Db_Expr('SUM(CASE WHEN (spm.final_result = 2) THEN 1 ELSE 0 END)'),
                        'number_passed' => new Zend_Db_Expr('SUM(CASE WHEN (spm.final_result = 1) THEN 1 ELSE 0 END)'),
                        'totalScore' => new Zend_Db_Expr('SUM(spm.documentation_score+spm.shipment_score)'),
                        '0-59' => new Zend_Db_Expr('SUM(spm.documentation_score+spm.shipment_score) >= 0 AND SUM(spm.documentation_score+spm.shipment_score) <= 59'),
                        '60-69' => new Zend_Db_Expr('SUM(spm.documentation_score+spm.shipment_score) >= 60 AND SUM(spm.documentation_score+spm.shipment_score) <= 69'),
                        "70-$pass" => new Zend_Db_Expr("SUM(spm.documentation_score+spm.shipment_score) >= 70 AND SUM(spm.documentation_score+spm.shipment_score) <= $pass"),
                        "above $pass" => new Zend_Db_Expr("SUM(spm.documentation_score+spm.shipment_score) >= $pass"),
                        'failed' => new Zend_Db_Expr("SUM(spm.documentation_score+spm.shipment_score) >= $pass AND spm.final_result = 2"),
                        'reported_count' => new Zend_Db_Expr("SUM(response_status IN ('responded', 'nottested'))"),
                    ]
                )
                    ->join(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', [''])
                    ->join(['p' => 'participant'], 'p.participant_id=spm.participant_id', ['p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status'])
                    ->joinLeft(['res' => 'r_results'], 'res.result_id=spm.final_result', ['result_name'])
                    ->where('spm.shipment_id = ?', $shipmentId)
                    ->where('spm.final_result IS NOT NULL')
                    ->where("spm.final_result !=''")
                    ->group('spm.map_id');
                if (isset($testType) && !empty($testType)) {
                    $sQuery = $sQuery->where("JSON_EXTRACT(spm.attributes, '$.dts_test_panel_type') = ?", $testType);
                }
                $sQueryRes = $db->fetchAll($sQuery);
                if (!empty($sQueryRes)) {

                    $tQuery = $db->select()->from(['refdts' => 'reference_result_dts'], ['refdts.sample_id', 'refdts.sample_label'])
                        ->join(['s' => 'shipment'], 's.shipment_id=refdts.shipment_id', [''])
                        ->join(
                            ['resdts' => 'response_result_dts'],
                            'resdts.sample_id=refdts.sample_id',
                            ['correctRes' => new Zend_Db_Expr("SUM(CASE WHEN (resdts.reported_result=refdts.reference_result AND spm.is_excluded='no') THEN 1 ELSE 0 END)")]
                        )
                        ->join(
                            ['spm' => 'shipment_participant_map'],
                            'resdts.shipment_map_id=spm.map_id and refdts.shipment_id=spm.shipment_id',
                            [
                                'number_responded' => new Zend_Db_Expr("SUM(CASE WHEN (spm.response_status is not null and spm.response_status = 'responded') THEN 1 ELSE 0 END)"),
                                'number_failed' => new Zend_Db_Expr('SUM(CASE WHEN (spm.final_result = 2 AND spm.shipment_test_date <= DATE(s.response_deadline)) THEN 1 ELSE 0 END)'),
                                'number_passed' => new Zend_Db_Expr('SUM(CASE WHEN (spm.final_result = 1 AND spm.shipment_test_date <= DATE(s.response_deadline)) THEN 1 ELSE 0 END)'),
                                'reported_count' => new Zend_Db_Expr("SUM(response_status IN ('responded', 'nottested'))"),
                            ]
                        )
                        ->where('spm.shipment_id = ?', $shipmentId)
                        ->where('spm.final_result IS NOT NULL')
                        ->where("spm.final_result!=''")
                        //->where("substring(spm.evaluation_status,4,1) != '0'")
                        ->group(['refdts.sample_id']);
                    if (isset($testType) && !empty($testType)) {
                        $tQuery = $tQuery->where("JSON_EXTRACT(spm.attributes, '$.dts_test_panel_type') = ?", $testType);
                    }
                    $shipmentResult['summaryResult'][] = $sQueryRes;
                    $shipmentResult['summaryResult'][count($shipmentResult['summaryResult']) - 1]['correctCount'] = $db->fetchAll($tQuery);

                    $rQuery = $db->select()->from(['spm' => 'shipment_participant_map'], [''])
                        ->join(
                            ['resdts' => 'response_result_dts'],
                            'resdts.shipment_map_id=spm.map_id',
                            [
                                'testkit1Total' => new Zend_Db_Expr('COUNT(DISTINCT(CONCAT(resdts.test_kit_name_1,resdts.shipment_map_id)))'),
                            ]
                        )
                        ->join(['rtdts' => 'r_testkitnames'], 'rtdts.TestKitName_ID=resdts.test_kit_name_1', ['TestKit_Name'])
                        ->where('spm.final_result IS NOT NULL')
                        ->where("spm.final_result!=''")
                        ->where("spm.is_excluded!='yes'")
                        ->where('spm.shipment_id = ?', $shipmentId)
                        ->group('resdts.test_kit_name_1')
                        ->order('testkit1Total DESC');
                    if (isset($testType) && !empty($testType)) {
                        $rQuery = $rQuery->where("JSON_EXTRACT(spm.attributes, '$.dts_test_panel_type') = ?", $testType);
                    }
                    $rQueryRes = $db->fetchAll($rQuery);
                    $shipmentResult['pieChart'] = $rQueryRes;

                    $rQuery = $db->select()->from(['spm' => 'shipment_participant_map'], [''])
                        ->join(
                            ['resdts' => 'response_result_dts'],
                            'resdts.shipment_map_id=spm.map_id',
                            [
                                'testkit2Total' => new Zend_Db_Expr('COUNT(DISTINCT(CONCAT(resdts.test_kit_name_2,resdts.shipment_map_id)))'),
                            ]
                        )
                        ->join(
                            ['rtdts' => 'r_testkitnames'],
                            'rtdts.TestKitName_ID=resdts.test_kit_name_2',
                            ['TestKit_Name']
                        )
                        ->where('spm.final_result IS NOT NULL')
                        ->where("spm.final_result!=''")
                        ->where("spm.is_excluded!='yes'")
                        ->where('spm.shipment_id = ?', $shipmentId)
                        ->group('resdts.test_kit_name_2')
                        ->order('testkit2Total DESC');
                    if (isset($testType) && !empty($testType)) {
                        $rQuery = $rQuery->where("JSON_EXTRACT(spm.attributes, '$.dts_test_panel_type') = ?", $testType);
                    }
                    $rQueryRes = $db->fetchAll($rQuery);
                    $shipmentResult['pieChart2'] = $rQueryRes;

                    $rQuery = $db->select()->from(['spm' => 'shipment_participant_map'], [''])
                        ->join(
                            ['resdts' => 'response_result_dts'],
                            'resdts.shipment_map_id=spm.map_id',
                            [
                                'testkit3Total' => new Zend_Db_Expr('COUNT(DISTINCT(CONCAT(resdts.test_kit_name_3,resdts.shipment_map_id)))'),
                            ]
                        )
                        ->join(
                            ['rtdts' => 'r_testkitnames'],
                            'rtdts.TestKitName_ID=resdts.test_kit_name_3',
                            ['TestKit_Name']
                        )
                        ->where('spm.final_result IS NOT NULL')
                        ->where("spm.final_result!=''")
                        ->where("spm.is_excluded!='yes'")
                        ->where('spm.shipment_id = ?', $shipmentId)
                        ->group('resdts.test_kit_name_3')
                        ->order('testkit3Total DESC');
                    if (isset($testType) && !empty($testType)) {
                        $rQuery = $rQuery->where("JSON_EXTRACT(spm.attributes, '$.dts_test_panel_type') = ?", $testType);
                    }
                    $rQueryRes = $db->fetchAll($rQuery);
                    $shipmentResult['pieChart3'] = $rQueryRes;
                }
                // DTS Participants Perfomance chart
                $sQuery = $db->select()->from(['s' => 'shipment'], ['shipment_code'])
                    ->join(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', [''])
                    ->join(
                        ['sp' => 'shipment_participant_map'],
                        'sp.shipment_id=s.shipment_id',
                        [
                            'shipmentDate' => new Zend_Db_Expr("DATE_FORMAT(s.shipment_date,'%d-%b-%Y')"),
                            'not_responded' => new Zend_Db_Expr(self::SQL_DID_NOT_RESPOND),
                            'excluded' => new Zend_Db_Expr("SUM(CASE WHEN (sp. final_result is not null AND sp. final_result != 2 AND sp. is_excluded is not null AND sp. is_excluded not like '' AND sp. is_excluded like 'yes') THEN 1 ELSE 0 END)"),
                            'total_shipped' => new Zend_Db_Expr('count("sp.map_id")'),
                            'reported_count' => new Zend_Db_Expr("SUM(shipment_test_report_date not like  '0000-00-00' OR is_pt_test_not_performed !='yes')"),
                            'beforeDueDate' => new Zend_Db_Expr('SUM(CASE WHEN (NOT ' . Pt_Commons_DateUtility::sqlSubmittedAfterDeadline() . ') THEN 1 ELSE 0 END)'),
                            'afterDueDate' => new Zend_Db_Expr('SUM(CASE WHEN (' . Pt_Commons_DateUtility::sqlSubmittedAfterDeadline() . ') THEN 1 ELSE 0 END)'),
                            'pass_percentage' => new Zend_Db_Expr('((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100'),
                        ]
                    )->where('s.shipment_id = ?', $shipmentId);

                if (isset($testType) && !empty($testType)) {
                    $sQuery = $sQuery->where("JSON_EXTRACT(sp.attributes, '$.dts_test_panel_type') = ?", $testType);
                }
                $shipmentResult['participantBeforeAfterDueChart'] = $db->fetchRow($sQuery);

                // DTS Aberrant test result chart
                $sQuery = $db->select()->from(['s' => 'shipment'], ['shipment_code'])
                    ->join(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', [''])
                    ->joinLeft(
                        ['sp' => 'shipment_participant_map'],
                        'sp.shipment_id=s.shipment_id',
                        [
                            'shipmentDate' => new Zend_Db_Expr("DATE_FORMAT(s.shipment_date,'%d-%b-%Y')"),
                            'total_shipped' => new Zend_Db_Expr('count("sp.map_id")'),
                            'number_responded' => new Zend_Db_Expr("SUM(CASE WHEN (sp.response_status is not null and sp.response_status = 'responded') THEN 1 ELSE 0 END)"),
                            'reported_count' => new Zend_Db_Expr("SUM(response_status IN ('responded', 'nottested'))"),
                            'fail_percentage' => new Zend_Db_Expr('((SUM(sp.final_result = 2))/(SUM(sp.final_result = 2) + SUM(sp.final_result = 1)))*100'),
                            'pass_percentage' => new Zend_Db_Expr('((SUM(sp.final_result = 1))/(SUM(sp.final_result = 1) + SUM(sp.final_result = 2)))*100'),
                            'number_failed' => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result = 2 AND sp.is_excluded != 'yes') THEN 1 ELSE 0 END)"),
                            'number_passed' => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result = 1 AND sp.is_excluded != 'yes') THEN 1 ELSE 0 END)"),
                        ]
                    )
                    ->joinLeft(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['region'])
                    ->joinLeft(['rr' => 'r_results'], 'sp.final_result=rr.result_id', [''])
                    ->where('s.shipment_id = ?', $shipmentId);

                if (isset($testType) && !empty($testType)) {
                    $sQuery = $sQuery->where("JSON_EXTRACT(sp.attributes, '$.dts_test_panel_type') = ?", $testType);
                }
                $shipmentResult['participantAberrantChart'] = $db->fetchRow($sQuery);

                // DTS Aberrant test result failed chart
                $sQuery = $db->select()->from(['s' => 'shipment'], ['shipment_code'])
                    ->joinLeft(
                        ['sp' => 'shipment_participant_map'],
                        'sp.shipment_id=s.shipment_id',
                        [
                            'shipmentDate' => new Zend_Db_Expr("DATE_FORMAT(s.shipment_date,'%d-%b-%Y')"),
                            'total_shipped' => new Zend_Db_Expr('count("sp.map_id")'),
                            'number_responded' => new Zend_Db_Expr("SUM(CASE WHEN (sp.response_status is not null and sp.response_status = 'responded') THEN 1 ELSE 0 END)"),
                            'reported_count' => new Zend_Db_Expr("SUM(response_status IN ('responded', 'nottested'))"),
                            'departmentCount' => new Zend_Db_Expr('count("p.department_name")'),
                            'beforeDueDate' => new Zend_Db_Expr('SUM(NOT ' . Pt_Commons_DateUtility::sqlSubmittedAfterDeadline() . ')'),
                            'afterDueDate' => new Zend_Db_Expr('SUM(' . Pt_Commons_DateUtility::sqlSubmittedAfterDeadline() . ')'),
                            'fail_percentage' => new Zend_Db_Expr('((SUM(final_result = 2))/(SUM(final_result = 2) + SUM(final_result = 1)))*100'),
                        ]
                    )
                    ->joinLeft(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['participant_id', 'institute_name', 'region', 'department_name'])
                    ->where('final_result = 2')
                    // ->where("sp.shipment_test_date <= DATE(s.response_deadline)")
                    ->where('s.shipment_id = ?', $shipmentId)
                    // ->group(array('p.network_tier'));
                    ->group(['p.department_name']);

                if (isset($testType) && !empty($testType)) {
                    $sQuery = $sQuery->where("JSON_EXTRACT(sp.attributes, '$.dts_test_panel_type') = ?", $testType);
                }
                $rResult = $db->fetchAll($sQuery);
                $row = [];
                $row['totalN'] = 0;
                $row['totalShipped'] = 0;
                foreach ($rResult as $key => $aRow) {
                    $row['department_name'][$key] = $aRow['department_name'] . ' (N : ' . round($aRow['departmentCount'], 2) . ')';
                    $row['totalShipped'] += $aRow['total_shipped'];
                    $row['beforeDueDate'][$key] = round($aRow['beforeDueDate'], 2);
                    $row['afterDueDate'][$key] = round($aRow['afterDueDate'], 2);
                    $row['fail_percentage'][$key] = round($aRow['fail_percentage'], 2);
                    $row['departmentCount'][$key] = round($aRow['departmentCount'], 2);
                    $row['totalN'] += $aRow['departmentCount'];
                }
                $shipmentResult['participantAberrantDepartmentChart'] = $row;

                $sql = $db->select()->from(['p' => 'participant'])
                    ->join(['spm' => 'shipment_participant_map'], 'spm.participant_id=p.participant_id')
                    ->where('spm.shipment_id = ?', $shipmentId);
                if (isset($testType) && !empty($testType)) {
                    $sql = $sql->where("JSON_EXTRACT(spm.attributes, '$.dts_test_panel_type') = ?", $testType);
                }
                $shipmentResult['participantScores'] = $db->fetchAll($sql);

                $sitesSql = $db->select()->from(['p' => 'participant'], ['department_name', 'totalSites' => new Zend_Db_Expr('COUNT(department_name)')])
                    ->join(['spm' => 'shipment_participant_map'], 'spm.participant_id=p.participant_id', [''])
                    ->where('spm.shipment_id = ?', $shipmentId)
                    ->group('p.department_name')
                    ->order('totalSites DESC');
                if (isset($testType) && !empty($testType)) {
                    $sitesSql = $sitesSql->where("JSON_EXTRACT(spm.attributes, '$.dts_test_panel_type') = ?", $testType);
                }
                $shipmentResult['siteChart'] = $db->fetchAll($sitesSql);
            } elseif ($shipmentResult['scheme_type'] == 'recency') {
                $sql = $db->select()->from(['refrecency' => 'reference_result_recency'], ['refrecency.reference_result', 'refrecency.sample_label', 'refrecency.mandatory'])
                    ->join(['refpr' => 'r_possibleresult'], 'refpr.id=refrecency.reference_result', ['referenceResult' => 'refpr.response'])
                    ->where('refrecency.shipment_id = ?', $shipmentResult['shipment_id']);
                $sqlRes = $db->fetchAll($sql);

                $shipmentResult['referenceResult'] = $sqlRes;

                $sQuery = $db->select()->from(
                    ['spm' => 'shipment_participant_map'],
                    [
                        'spm.map_id',
                        'spm.shipment_id',
                        'spm.shipment_score',
                        'spm.documentation_score',
                        'spm.attributes',
                        'spm.is_excluded',
                    ]
                )
                    ->join(['p' => 'participant'], 'p.participant_id=spm.participant_id', ['p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status'])
                    ->joinLeft(['res' => 'r_results'], 'res.result_id=spm.final_result', ['result_name'])
                    ->where('spm.shipment_id = ?', $shipmentId)
                    ->where('spm.final_result IS NOT NULL')
                    ->where("spm.final_result!=''")
                    // ->where("spm.final_result = ?",'2')
                    //->where("substring(spm.evaluation_status,4,1) != '0'")
                    ->group('spm.map_id');
                $sQueryRes = $db->fetchAll($sQuery);

                if (!empty($sQueryRes)) {

                    $tQuery = $db->select()->from(['refrecency' => 'reference_result_recency'], ['refrecency.sample_id', 'refrecency.sample_label'])
                        ->join(['resrecency' => 'response_result_recency'], 'resrecency.sample_id=refrecency.sample_id', ['correctRes' => new Zend_Db_Expr("SUM(CASE WHEN (resrecency.reported_result=refrecency.reference_result AND spm.is_excluded='no') THEN 1 ELSE 0 END)")])
                        ->join(['spm' => 'shipment_participant_map'], 'resrecency.shipment_map_id=spm.map_id and refrecency.shipment_id=spm.shipment_id', [
                            'number_responded' => new Zend_Db_Expr("SUM(CASE WHEN (spm.response_status is not null and spm.response_status = 'responded') THEN 1 ELSE 0 END)"),
                            'number_failed' => new Zend_Db_Expr('SUM(CASE WHEN (spm.final_result = 2 AND spm.shipment_test_date <= DATE(s.response_deadline)) THEN 1 ELSE 0 END)'),
                            'number_passed' => new Zend_Db_Expr('SUM(CASE WHEN (spm.final_result = 1 AND spm.shipment_test_date <= DATE(s.response_deadline)) THEN 1 ELSE 0 END)'),
                            'reported_count' => new Zend_Db_Expr("SUM(response_status IN ('responded', 'nottested'))"),
                        ])
                        ->join(['s' => 'shipment'], 'spm.shipment_id=s.shipment_id')
                        ->where('spm.shipment_id = ?', $shipmentId)
                        ->where('spm.final_result IS NOT NULL')
                        ->where("spm.final_result!=''")
                        //->where("substring(spm.evaluation_status,4,1) != '0'")
                        ->group(['refrecency.sample_id']);
                    $shipmentResult['summaryResult'][] = $sQueryRes;
                    $shipmentResult['summaryResult'][count($shipmentResult['summaryResult']) - 1]['correctCount'] = $db->fetchAll($tQuery);
                }

                $sql = $db->select()->from(['p' => 'participant'])
                    ->join(['spm' => 'shipment_participant_map'], 'spm.participant_id=p.participant_id')
                    ->where('spm.shipment_id = ?', $shipmentId);

                $shipmentResult['participantScores'] = $db->fetchAll($sql);
            } elseif ($shipmentResult['scheme_type'] == 'eid') {
                $schemeService = new Application_Service_Schemes();
                $extractionAssay = $schemeService->getEidExtractionAssay();
                //$detectionAssay = $schemeService->getEidDetectionAssay();
                $pQuery = $db->select()->from(
                    ['spm' => 'shipment_participant_map'],
                    [
                        'spm.map_id',
                        'spm.shipment_id',
                        'spm.documentation_score',
                        'participant_count' => new Zend_Db_Expr('count("participant_id")'),
                        'reported_count' => new Zend_Db_Expr("SUM(response_status IN ('responded', 'nottested'))"),
                    ]
                )
                    ->joinLeft(
                        ['res' => 'r_results'],
                        'res.result_id=spm.final_result',
                        ['result_name']
                    )
                    ->where('spm.shipment_id = ?', $shipmentId)
                    ->group('spm.shipment_id');
                $totParticipantsRes = $db->fetchRow($pQuery);
                if ($totParticipantsRes != '') {
                    $shipmentResult['participant_count'] = $totParticipantsRes['participant_count'];
                }

                $sQuery = $db->select()->from(['spm' => 'shipment_participant_map'], ['spm.map_id', 'spm.shipment_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.attributes'])
                    //->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status'))
                    ->joinLeft(['res' => 'r_results'], 'res.result_id=spm.final_result', ['result_name'])
                    ->where('spm.shipment_id = ?', $shipmentId)
                    //->where("spm.shipment_test_date IS NOT NULL AND spm.shipment_test_date not like '' AND spm.shipment_test_date not like '0000-00-00' OR IFNULL(spm.is_pt_test_not_performed, 'no') ='yes'")
                    ->where("spm.shipment_test_date IS NOT NULL AND spm.shipment_test_date not like '' AND spm.shipment_test_date not like '0000-00-00'")
                    //->where("IFNULL(spm.is_pt_test_not_performed, 'no') not like 'yes'")
                    ->group('spm.map_id');

                $sQueryRes = $db->fetchAll($sQuery);

                if (!empty($sQueryRes)) {
                    $shipmentResult['summaryResult'][] = $sQueryRes;
                }

                $cQuery = $db->select()->from(['refeid' => 'reference_result_eid'], ['refeid.sample_id', 'refeid.sample_label', 'refeid.reference_result', 'refeid.mandatory'])
                    ->join(['s' => 'shipment'], 's.shipment_id=refeid.shipment_id', ['s.shipment_id'])
                    ->join(['spm' => 'shipment_participant_map'], 's.shipment_id=spm.shipment_id', ['spm.map_id', 'spm.attributes', 'spm.shipment_score'])
                    ->joinLeft(['reseid' => 'response_result_eid'], 'reseid.shipment_map_id = spm.map_id and reseid.sample_id = refeid.sample_id', ['reported_result'])
                    ->where('spm.shipment_id = ? ', $shipmentId)
                    ->where("spm.shipment_test_date IS NOT NULL AND spm.shipment_test_date not like '' AND spm.shipment_test_date not like '0000-00-00' OR IFNULL(spm.is_pt_test_not_performed, 'no') ='yes'")
                    ->where("spm.is_excluded!='yes'")
                    ->where('refeid.control = 0');

                $cResult = $db->fetchAll($cQuery);
                $correctResult = [];
                foreach ($cResult as $cVal) {
                    //Formed correct result
                    if (array_key_exists($cVal['sample_label'], $correctResult)) {
                        if ($cVal['reported_result'] == $cVal['reference_result']) {
                            $correctResult[$cVal['sample_label']] += 1;
                        }
                    } else {
                        $correctResult[$cVal['sample_label']] = [];
                        if ($cVal['reported_result'] == $cVal['reference_result']) {
                            $correctResult[$cVal['sample_label']] = 1;
                        } else {
                            $correctResult[$cVal['sample_label']] = 0;
                        }
                    }
                }

                $shipmentResult['correctRes'] = $correctResult;

                $extAssayResult = [];

                foreach ($sQueryRes as $sVal) {
                    $valAttributes = Pt_Commons_JsonUtility::safeDecode($sVal['attributes']);

                    $cQuery = $db->select()->from(['refeid' => 'reference_result_eid'], ['refeid.sample_id', 'refeid.sample_label', 'refeid.reference_result', 'refeid.mandatory'])
                        ->joinLeft(['reseid' => 'response_result_eid'], 'reseid.sample_id = refeid.sample_id', ['reported_result'])
                        ->where('refeid.shipment_id = ? ', $shipmentId)
                        ->where('refeid.control = 0')
                        ->where('reseid.shipment_map_id = ? ', $sVal['map_id']);

                    $cResult = $db->fetchAll($cQuery);

                    foreach ($extractionAssay as $eKey => $extractionAssayVal) {
                        if ($eKey == $valAttributes['extraction_assay']) {
                            if (array_key_exists($eKey, $extAssayResult)) {
                                $extAssayResult[$eKey]['participantCount'] = (isset($extAssayResult[$eKey]['participantCount']) ? $extAssayResult[$eKey]['participantCount'] + 1 : 1);
                                if ($shipmentResult['max_score'] == $sVal['shipment_score']) {
                                    $extAssayResult[$eKey]['maxScore'] = (isset($extAssayResult[$eKey]['maxScore']) ? $extAssayResult[$eKey]['maxScore'] + 1 : 1);
                                } else {
                                    $extAssayResult[$eKey]['belowScore'] = (isset($extAssayResult[$eKey]['belowScore']) ? $extAssayResult[$eKey]['belowScore'] + 1 : 1);
                                }
                            } else {
                                $extAssayResult[$eKey] = [];
                                $extAssayResult[$eKey]['eidAssay'] = $extractionAssayVal;
                                $extAssayResult[$eKey]['participantCount'] = 1;
                                if ($shipmentResult['max_score'] == $sVal['shipment_score']) {
                                    $extAssayResult[$eKey]['maxScore'] = 1;
                                } else {
                                    $extAssayResult[$eKey]['belowScore'] = 1;
                                }
                            }

                            foreach ($cResult as $val) {
                                if ($val['reported_result'] == $val['reference_result']) {
                                    $extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes'] = (isset($extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes']) ? $extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes'] + 1 : 1);
                                } else {
                                    $extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes'] = (isset($extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes']) ? $extAssayResult[$eKey]['specimen'][$val['sample_label']]['correctRes'] : 0);
                                }
                            }
                        }
                    }
                }

                ksort($extAssayResult);

                // clubbing all the results with less than or equal to 5 responses with Others
                foreach ($extAssayResult as $exid => $edata) {
                    if ($exid == 8) {
                        continue;
                    }
                    $extAssayResult[8]['maxScore'] ??= 0;
                    $extAssayResult[8]['participantCount'] ??= 0;
                    $extAssayResult[8]['belowScore'] ??= 0;
                    $extAssayResult[8]['specimen'][$val['sample_label']]['correctRes'] ??= 0;
                    if ($edata['participantCount'] <= 5) {
                        // Key 8 is the "Other" extraction assay. When labs reported it, the
                        // bucket already holds that assay's name as a string, and appending
                        // to a string is fatal. Turn it into a list, keeping its own count.
                        if (isset($extAssayResult[8]['eidAssay']) && !is_array($extAssayResult[8]['eidAssay'])) {
                            $extAssayResult[8]['eidAssayWithCount'] = [$extAssayResult[8]['eidAssay'] . '(n=' . $extAssayResult[8]['participantCount'] . ')'];
                            $extAssayResult[8]['eidAssay'] = [$extAssayResult[8]['eidAssay']];
                        }
                        $extAssayResult[8]['eidAssay'][] = $edata['eidAssay'];
                        $extAssayResult[8]['eidAssayWithCount'][] = $edata['eidAssay'] . '(n=' . $edata['participantCount'] . ')';
                        $extAssayResult[8]['participantCount'] += $edata['participantCount'];
                        $extAssayResult[8]['maxScore'] += $edata['maxScore'] ?? 0;
                        //$extAssayResult[8]['belowScore'] += isset($edata['belowScore']) ? $edata['belowScore'] : 0;
                        $extAssayResult[8]['belowScore'] += isset($edata['belowScore']) ? $edata['belowScore'] : 0;

                        foreach ($cResult as $val) {
                            $extAssayResult[8]['specimen'][$val['sample_label']] ??= [];
                            $extAssayResult[8]['specimen'][$val['sample_label']]['correctRes'] ??= 0;
                            $extAssayResult[8]['specimen'][$val['sample_label']]['correctRes'] += $edata['specimen'][$val['sample_label']]['correctRes'];
                        }

                        unset($extAssayResult[$exid]);
                    }
                }
                $shipmentResult['avgAssayResult'] = $extAssayResult;
            } elseif ($shipmentResult['scheme_type'] == 'vl') {

                $sQuery = $db->select()->from(
                    ['spm' => 'shipment_participant_map'],
                    ['spm.map_id', 'spm.shipment_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.attributes', 'spm.is_excluded']
                )
                    ->join(
                        ['p' => 'participant'],
                        'p.participant_id=spm.participant_id',
                        ['p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status']
                    )
                    ->joinLeft(
                        ['res' => 'r_results'],
                        'res.result_id=spm.final_result',
                        ['result_name']
                    )
                    ->where('spm.shipment_id = ?', $shipmentId)
                    ->where("spm.shipment_test_date IS NOT NULL AND spm.shipment_test_date not like '' AND spm.shipment_test_date not like '0000-00-00' OR IFNULL(spm.is_pt_test_not_performed, 'no') ='yes'")
                    ->group('spm.map_id');

                $sQueryRes = $db->fetchAll($sQuery);
                if (!empty($sQueryRes)) {
                    $shipmentResult['summaryResult'][] = $sQueryRes;
                }

                $query = $db->select()->from(['refvl' => 'reference_result_vl'], ['refvl.sample_score'])

                    ->where('refvl.control!=1')
                    ->where('refvl.shipment_id = ? ', $shipmentId);
                $smpleResult = $db->fetchAll($query);
                $shipmentResult['no_of_samples'] = count($smpleResult);

                $refVlQuery = $db->select()->from(['ref' => 'reference_vl_calculation'], ['ref.vl_assay'])
                    ->where('ref.shipment_id = ? ', $shipmentId)
                    ->group('vl_assay');

                $countedAssayResult = $db->fetchAll($refVlQuery);

                $regexpArray = [];
                $regexp = '';
                foreach ($countedAssayResult as $crow) {
                    $regexpArray[] = $db->quote('%"vl_assay":"' . $crow['vl_assay'] . '"%');
                }
                // select * from shipment_participant_map where `attributes` NOT REGEXP '\"vl_assay\":\"1\" |\"vl_assay\":\"4\" |\"vl_assay\":\"2\"' and shipment_id = 11
                if (isset($regexpArray) && !empty($regexpArray)) {
                    $regexp = implode(' AND `attributes` NOT LIKE ', $regexpArray);
                } else {
                    $regexp = '""';
                }

                $vlQuery = $db->select()->from(['spm' => 'shipment_participant_map'])
                    ->where("`attributes` NOT LIKE  $regexp ")
                    ->where("(spm.is_excluded LIKE 'yes') IS NOT TRUE")
                    ->where("(spm.is_pt_test_not_performed LIKE 'yes') IS NOT TRUE")
                    ->where('spm.shipment_id = ?', $shipmentId);
                $pendingResult = $db->fetchAll($vlQuery);

                $schemeService = new Application_Service_Schemes();
                $vlAssayList = $schemeService->getVlAssay();
                $penResult = [];
                foreach ($pendingResult as $pendingRow) {
                    $valAttributes = Pt_Commons_JsonUtility::safeDecode($pendingRow['attributes']);
                    if (isset($vlAssayList[$valAttributes['vl_assay']])) {
                        if ($valAttributes['vl_assay'] == 6) {
                            $penResult['assayNames'][] = $valAttributes['other_assay'];
                        } else {
                            $penResult['assayNames'][] = $vlAssayList[$valAttributes['vl_assay']];
                        }
                    }
                }
                if (isset($penResult['assayNames']) && count($penResult['assayNames']) > 0) {
                    $penResult['assayNames'] = array_unique($penResult['assayNames']);
                    sort($penResult['assayNames']);
                }
                $penResult['count'] = count($pendingResult);

                $vlCalculation = [];
                $vlAssayResultSet = $db->fetchAll($db->select()
                    ->from('r_vl_assay')
                    ->where("`status` like 'active'"));
                $otherAssayCounter = [];
                /* VL Assay for chart */
                // $vlAssayQuery = $db->select()->from(
                // 	array('vlCal' => 'reference_vl_calculation'),
                // 	array('no_of_responses')
                // )
                // 	->join(
                // 		array('refVl' => 'reference_result_vl'),
                // 		'refVl.shipment_id=vlCal.shipment_id and vlCal.sample_id=refVl.sample_id',
                // 		array('no_of_samples' => new Zend_Db_Expr("COUNT(DISTINCT refVl.sample_id)"))
                // 	)
                // 	->join(array('rvla' => 'r_vl_assay'), 'rvla.id=vlCal.vl_assay', array('assay_name' => 'name'))
                // 	->join(
                // 		array('sp' => 'shipment_participant_map'),
                // 		'vlCal.shipment_id=sp.shipment_id',
                // 		array(
                // 			'numberPassed' => new Zend_Db_Expr("SUM(CASE WHEN final_result = 1 THEN 1 ELSE 0 END)/COUNT(DISTINCT refVl.sample_id)"),
                // 			'numberFailed' => new Zend_Db_Expr("SUM(CASE WHEN final_result != 1 THEN 1 ELSE 0 END)/COUNT(DISTINCT refVl.sample_id)"),
                // 		)
                // 	)
                // 	->where("vlCal.shipment_id=?", $shipmentId)
                // 	->where("refVl.control!=1")
                // 	//->where("(sp.attributes like CONCAT('%\"vl_assay\":\"', vlCal.vl_assay, '\"%') )")
                // 	->where('sp.attributes->>"$.vl_assay" = vlCal.vl_assay')
                // 	->where("sp.is_excluded not like 'yes'")
                // 	->group('rvla.name')
                // 	->order('vlCal.no_of_responses DESC');
                // $vlAssayRes = $db->fetchAll($vlAssayQuery);

                foreach ($vlAssayResultSet as $vlAssayRow) {
                    $vlQuery = $db->select()->from(['vlCal' => 'reference_vl_calculation'], ['*'])
                        ->join(['refVl' => 'reference_result_vl'], 'refVl.shipment_id=vlCal.shipment_id and vlCal.sample_id=refVl.sample_id', ['refVl.sample_label', 'refVl.mandatory'])
                        ->join(['sp' => 'shipment_participant_map'], 'vlCal.shipment_id=sp.shipment_id', [])
                        ->join(
                            ['res' => 'response_result_vl'],
                            'res.shipment_map_id = sp.map_id and res.sample_id = refVl.sample_id',
                            [
                                'NumberPassed' => new Zend_Db_Expr("SUM(CASE WHEN calculated_score = 'pass' OR calculated_score = 'warn' THEN 1 ELSE 0 END)"),
                                // Participants on THIS platform for this sample -- the denominator of
                                // "Participants with Passing Results". Deliberately not
                                // reference_vl_calculation.no_of_responses: that is the n behind the
                                // assigned value, which is a larger, shared number whenever several
                                // platforms are pooled into one peer group. The two were historically
                                // equal, so the layouts used no_of_responses for both; they diverge the
                                // moment platforms are merged, and the pass rate then reads far too low.
                                'participants_on_platform' => new Zend_Db_Expr('COUNT(*)'),
                            ]
                        )
                        ->where('vlCal.shipment_id=?', $shipmentId)
                        ->where('vlCal.vl_assay=?', $vlAssayRow['id'])
                        ->where('refVl.control!=1')
                        ->where('sp.attributes->>"$.vl_assay" = ' . (int) $vlAssayRow['id'])
                        ->where("sp.is_excluded not like 'yes' OR sp.is_excluded like '' OR sp.is_excluded is null")
                        ->where('sp.final_result = 1 OR sp.final_result = 2')
                        ->group('refVl.sample_id');

                    $vlCalRes = $db->fetchAll($vlQuery);

                    if ($vlAssayRow['id'] == 6) {
                        $cQuery = $db->select()->from(['sp' => 'shipment_participant_map'], ['sp.map_id', 'sp.attributes'])
                            ->where("sp.is_excluded not like 'yes'")
                            ->where('sp.attributes->>"$.vl_assay" = 6')
                            ->where('sp.shipment_id = ? ', $shipmentId);
                        $cResult = $db->fetchAll($cQuery);

                        foreach ($cResult as $val) {
                            $valAttributes = Pt_Commons_JsonUtility::safeDecode($val['attributes']);
                            if (isset($valAttributes['other_assay'])) {
                                if (!empty($otherAssayCounter[$valAttributes['other_assay']])) {
                                    $otherAssayCounter[$valAttributes['other_assay']]++;
                                } else {
                                    $otherAssayCounter[$valAttributes['other_assay']] = 1;
                                }
                            }
                        }
                    }
                    if (isset($vlCalRes) && !empty($vlCalRes)) {
                        $vlCalculation[$vlAssayRow['id']] = $vlCalRes;
                        $vlCalculation[$vlAssayRow['id']]['vlAssay'] = $vlAssayRow['name'];
                        $vlCalculation[$vlAssayRow['id']]['shortName'] = $vlAssayRow['short_name'];
                        $vlCalculation[$vlAssayRow['id']]['participant_response_count'] = $vlCalRes[0]['no_of_responses'];
                        // $labResult[$vlCalRes[0]['no_of_responses']];
                        if ($vlAssayRow['id'] == 6) {
                            $vlCalculation[$vlAssayRow['id']]['otherAssayName'] = $otherAssayCounter;
                        }
                    }
                }
                array_multisort(array_column($vlCalculation, 'participant_response_count'), SORT_DESC, $vlCalculation);
                $shipmentResult['vlCalculation'] = $vlCalculation;
            } elseif ($shipmentResult['scheme_type'] == 'covid19') {
                $sql = $db->select()->from(['refcovid19' => 'reference_result_covid19'], ['refcovid19.reference_result', 'refcovid19.sample_label', 'refcovid19.mandatory'])
                    ->join(['refpr' => 'r_possibleresult'], 'refpr.id=refcovid19.reference_result', ['referenceResult' => 'refpr.response'])
                    ->where('refcovid19.shipment_id = ?', $shipmentResult['shipment_id']);
                $sqlRes = $db->fetchAll($sql);

                $shipmentResult['referenceResult'] = $sqlRes;

                $sQuery = $db->select()->from(['spm' => 'shipment_participant_map'], ['spm.map_id', 'spm.shipment_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.attributes', 'spm.is_excluded'])
                    ->join(['p' => 'participant'], 'p.participant_id=spm.participant_id', ['p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status'])
                    ->joinLeft(['res' => 'r_results'], 'res.result_id=spm.final_result', ['result_name'])
                    ->where('spm.shipment_id = ?', $shipmentId)
                    ->where('spm.final_result IS NOT NULL')
                    ->where("spm.final_result!=''")
                    // ->where("spm.final_result = ?",'2')
                    //->where("substring(spm.evaluation_status,4,1) != '0'")
                    ->group('spm.map_id');
                $sQueryRes = $db->fetchAll($sQuery);

                if (!empty($sQueryRes)) {

                    $tQuery = $db->select()->from(['refcovid19' => 'reference_result_covid19'], ['refcovid19.sample_id', 'refcovid19.sample_label'])
                        ->join(['rescovid19' => 'response_result_covid19'], 'rescovid19.sample_id=refcovid19.sample_id', ['correctRes' => new Zend_Db_Expr("SUM(CASE WHEN (rescovid19.reported_result=refcovid19.reference_result AND spm.is_excluded='no') THEN 1 ELSE 0 END)")])
                        ->join(['spm' => 'shipment_participant_map'], 'rescovid19.shipment_map_id=spm.map_id and refcovid19.shipment_id=spm.shipment_id', [])
                        ->where('spm.shipment_id = ?', $shipmentId)
                        ->where('spm.final_result IS NOT NULL')
                        ->where("spm.final_result!=''")
                        //->where("substring(spm.evaluation_status,4,1) != '0'")
                        ->group(['refcovid19.sample_id']);

                    $shipmentResult['summaryResult'][] = $sQueryRes;
                    $shipmentResult['summaryResult'][count($shipmentResult['summaryResult']) - 1]['correctCount'] = $db->fetchAll($tQuery);

                    $rQuery = $db->select()->from(['spm' => 'shipment_participant_map'], [''])
                        ->join(
                            ['resC19' => 'response_result_covid19'],
                            'resC19.shipment_map_id=spm.map_id',
                            [
                                'testPlatform1Total' => new Zend_Db_Expr('COUNT(DISTINCT(CONCAT(resC19.test_type_1,resC19.shipment_map_id)))'),
                            ]
                        )
                        ->join(['testPlatformC19' => 'r_test_type_covid19'], 'testPlatformC19.test_type_id=resC19.test_type_1', ['test_type_name'])
                        ->where('spm.final_result IS NOT NULL')
                        ->where("spm.final_result!=''")
                        ->where("spm.is_excluded!='yes'")
                        ->where('spm.shipment_id = ?', $shipmentId)
                        ->group('testPlatformC19.test_type_name')
                        ->order('testPlatform1Total DESC');
                    // die($rQuery);
                    $rQueryRes = $db->fetchAll($rQuery);
                    $shipmentResult['pieChart'] = $rQueryRes;
                    // $shipmentResult['pieChart'] = $typeName;
                }

                $sql = $db->select()->from(['p' => 'participant'])
                    ->join(['spm' => 'shipment_participant_map'], 'spm.participant_id=p.participant_id')
                    ->where('spm.shipment_id = ?', $shipmentId);

                $shipmentResult['participantScores'] = $db->fetchAll($sql);
            } elseif ($shipmentResult['scheme_type'] == 'tb') {
                $tbModel = new Application_Model_Tb();
                $summaryPDFData = $tbModel->getDataForSummaryPDF($shipmentId);
                $shipmentResult = array_merge($shipmentResult, $summaryPDFData);
            } elseif ($shipmentResult['scheme_type'] == 'generic-test' || $shipmentResult['is_user_configured'] == 'yes') {
                $genericModel = new Application_Model_CustomTest();
                $summaryPDFData = $genericModel->getDataForSummaryPDF($shipmentId);
                $shipmentResult = array_merge($shipmentResult, $summaryPDFData);
            }
        }
        return ['shipment' => self::escapeReportText($shipmentResult)];
    }

    public function getResponseReports($shipmentId, $testType = '')
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sQuery = $dbAdapter->select()->from(['p' => 'participant'], [])
            ->join(
                ['sp' => 'shipment_participant_map'],
                'sp.participant_id=p.participant_id',
                [
                    'total_shipped' => new Zend_Db_Expr('count("sp.map_id")'),
                    // An empty shipment_test_date is not "did not respond": it is cleared on purpose
                    // when a lab declares it could not test, and a submission can carry results with
                    // the date left blank. Same test as the non-participation mailer and
                    // Shipments::getShipmentParticipationStats(), so every surface agrees.
                    'not_responded' => new Zend_Db_Expr(self::SQL_DID_NOT_RESPOND),
                    'excluded' => new Zend_Db_Expr("SUM(CASE WHEN (sp.is_excluded like 'yes') THEN 1 ELSE 0 END)"),
                    'number_failed' => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result = 2 AND sp.is_excluded != 'yes') THEN 1 ELSE 0 END)"),
                    'number_passed' => new Zend_Db_Expr("SUM(CASE WHEN (sp.final_result = 1 AND sp.is_excluded != 'yes') THEN 1 ELSE 0 END)"),
                    'number_late' => new Zend_Db_Expr(
                        'SUM(CASE WHEN (' . Pt_Commons_DateUtility::sqlSubmittedAfterDeadline() . ') THEN 1 ELSE 0 END)'
                    ),
                    // Five disjoint buckets for the participation pie -- see
                    // self::participationSlices(). Every participant matches exactly one, so the
                    // slices always reconcile to total_shipped.
                    //
                    // The chart used to take number_passed/number_failed/not_responded and derive
                    // the fourth by subtracting non-responders from the excluded count. That broke
                    // three ways: it erased genuine exclusions on shipments whose non-responders
                    // were never flagged excluded, it counted non-responders carrying a stale
                    // Pass/Fail in two slices at once, and responders still awaiting a verdict had
                    // no slice at all and silently vanished.
                    'slice_no_response' => new Zend_Db_Expr(self::SQL_DID_NOT_RESPOND),
                    'slice_excluded' => new Zend_Db_Expr(
                        'SUM(' . self::SQL_RESPONDED . " AND sp.is_excluded = 'yes')"
                    ),
                    'slice_passed' => new Zend_Db_Expr(
                        'SUM(' . self::SQL_RESPONDED . " AND IFNULL(sp.is_excluded, 'no') <> 'yes' AND sp.final_result = 1)"
                    ),
                    'slice_failed' => new Zend_Db_Expr(
                        'SUM(' . self::SQL_RESPONDED . " AND IFNULL(sp.is_excluded, 'no') <> 'yes' AND sp.final_result = 2)"
                    ),
                    'slice_unscored' => new Zend_Db_Expr(
                        'SUM(' . self::SQL_RESPONDED . " AND IFNULL(sp.is_excluded, 'no') <> 'yes' AND IFNULL(sp.final_result, 0) NOT IN (1, 2))"
                    ),
                ]
            )
            ->join(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', ['shipment_code'])
            ->where('sp.shipment_id = ?', $shipmentId);
        if (isset($testType) && !empty($testType)) {
            $sQuery = $sQuery->where("JSON_EXTRACT(sp.attributes, '$.dts_test_panel_type') = ?", $testType);
        }

        return $dbAdapter->fetchRow($sQuery);
    }

    public function addShipmentEvaluationToQueue($shipmentId)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $data = [
            'shipment_id' => $shipmentId,
            'initated_by' => $authNameSpace->admin_id,
            'requested_on' => new Zend_Db_Expr('now()'),
            'last_updated_on' => new Zend_Db_Expr('now()'),
            'status' => 'pending',
        ];
        $db->insert('queue_report_generation', $data);
    }

    /**
     * Record who approved a shipment's results, and when.
     *
     * Reports print a "Date of Approval" that was really the report-generation timestamp,
     * re-stamped on every regeneration. Approval now happens at a physical meeting that can
     * sit well after the reports were generated, so finalizing offers the admin a date and an
     * approver name to enter.
     *
     * Left blank it falls back to now, and to the standing approver from report-config,
     * then to the finalizing admin's name -- which is what the report effectively claimed
     * before this existed. An approval already on record is never overwritten by a blank
     * re-finalize, so regenerating the finalized reports keeps the date the meeting
     * actually agreed on rather than silently moving it to today.
     */
    private function recordResultsApproval(int $shipmentId, array $params): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $existing = $db->fetchRow(
            $db->select()
                ->from('shipment', ['results_approved_on', 'results_approved_by'])
                ->where('shipment_id = ?', $shipmentId)
        ) ?: [];

        $approvedOn = trim((string) ($params['resultsApprovedOn'] ?? ''));
        $approvedBy = trim((string) ($params['resultsApprovedBy'] ?? ''));
        $data = [];

        if ($approvedOn !== '') {
            $data['results_approved_on'] = Pt_Commons_DateUtility::isoDateFormat($approvedOn);
        } elseif (empty($existing['results_approved_on'])) {
            $data['results_approved_on'] = new Zend_Db_Expr('NOW()');
        }

        if ($approvedBy !== '') {
            $data['results_approved_by'] = $approvedBy;
        } elseif (empty($existing['results_approved_by'])) {
            // Finalizing from a grid row sends no approver at all, so fall back the way the
            // finalize page pre-fills its field: the standing approver from report-config
            // first, and only then whichever admin happened to click Finalize. Reports print
            // this name, so the admin is a last resort rather than the default.
            $name = trim((string) ((new Application_Service_Reports())->getReportConfigValue('report-approver-name') ?? ''));
            if ($name === '') {
                $authNameSpace = new Zend_Session_Namespace('administrators');
                $name = trim(($authNameSpace->first_name ?? '') . ' ' . ($authNameSpace->last_name ?? ''));
            }
            $data['results_approved_by'] = $name !== '' ? $name : null;
        }

        if ($data !== []) {
            $db->update('shipment', $data, ['shipment_id = ?' => $shipmentId]);
        }
    }

    /**
     * Correct the approval date and approver on a shipment that is already finalized.
     *
     * Results are often approved at a meeting weeks after finalizing, so the values entered
     * at finalize can be wrong. This saves only those two fields and does not finalize
     * again. As in recordResultsApproval, a blank field leaves the stored value as it is.
     *
     * @param array<string, mixed> $params resultsApprovedOn, resultsApprovedBy
     * @return array{status: string, message: string}
     */
    public function updateResultsApproval(int $shipmentId, array $params): array
    {
        $translator = Zend_Registry::get('translate');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $existing = $db->fetchRow(
            $db->select()
                ->from('shipment', ['shipment_code', 'status', 'results_approved_on', 'results_approved_by'])
                ->where('shipment_id = ?', $shipmentId)
        );
        if (!$existing || ($existing['status'] ?? '') !== 'finalized') {
            return ['status' => 'fail', 'message' => $translator->_('Only a finalized shipment can have its approval corrected.')];
        }

        $approvedOn = trim((string) ($params['resultsApprovedOn'] ?? ''));
        $approvedBy = mb_substr(trim((string) ($params['resultsApprovedBy'] ?? '')), 0, 256);
        $oldOn = (string) ($existing['results_approved_on'] ?? '');
        $oldBy = (string) ($existing['results_approved_by'] ?? '');
        $data = [];

        if ($approvedOn !== '') {
            $isoOn = Pt_Commons_DateUtility::isoDateFormat($approvedOn);
            if (empty($isoOn)) {
                return ['status' => 'fail', 'message' => $translator->_('Please enter a valid Date of Approval.')];
            }
            // The column is a DATETIME. Keep the stored time when the day is the same.
            if (substr($oldOn, 0, 10) !== $isoOn) {
                $data['results_approved_on'] = $isoOn;
            }
        }
        if ($approvedBy !== '' && $approvedBy !== $oldBy) {
            $data['results_approved_by'] = $approvedBy;
        }

        if ($data === []) {
            return ['status' => 'success', 'message' => $translator->_('The approval is unchanged.')];
        }

        $db->update('shipment', $data, ['shipment_id = ?' => $shipmentId]);

        $code = (string) $existing['shipment_code'];
        $before = ['Date of Approval' => $oldOn, 'Approved By' => $oldBy];
        $after = [
            'Date of Approval' => $data['results_approved_on'] ?? $oldOn,
            'Approved By' => $data['results_approved_by'] ?? $oldBy,
        ];
        try {
            (new Application_Model_DbTable_AuditLog())->addNewAuditLog(
                'Corrected results approval for finalized shipment - ' . $code,
                'shipment',
                null,
                [
                    'entity' => ['type' => 'shipment', 'id' => $shipmentId, 'label' => $code],
                    'changes' => Application_Model_DbTable_AuditLog::changes($before, $after),
                    'important' => true,
                    'reason' => 'Reports already sent keep the old approval until they are generated again',
                ]
            );
        } catch (Throwable $e) {
            // The audit log must never undo a correction that was saved.
            Pt_Commons_LoggerUtility::logWarning('updateResultsApproval audit failed: ' . $e->getMessage());
        }

        return ['status' => 'success', 'message' => $translator->_('Approval saved.')];
    }

    /** queueReportsGeneration(): reports for this shipment are being generated right now. */
    public const QUEUE_ALREADY_RUNNING = -1;

    public function queueReportsGeneration($params)
    {
        $shipmentId = base64_decode($params['sid']);
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Finalizing opens the participant feedback window, so refuse to finalize a
        // feedback-enabled shipment whose feedback form has not been built yet. The
        // grid already hides the button; this is the guard for direct POSTs.
        if (($params['type'] ?? '') === 'finalized' && Application_Service_FeedBack::isAwaitingFeedbackForm($shipmentId)) {
            return 0;
        }

        $existData = $db->fetchRow($db->select()->from('queue_report_generation')
            ->where('shipment_id = ?', $shipmentId)
            ->where('report_type = ?', $params['type']));

        // A run in progress reuses this same row. Re-queueing now would reset it to
        // 'pending' under the running workers, and the run would then mark the row
        // done, so the new request was silently lost. Refuse instead, before anything
        // is written; the admin generates again once the run has finished.
        if ($existData && in_array($existData['status'], ['generating', 'finalizing'], true)) {
            return self::QUEUE_ALREADY_RUNNING;
        }

        // Finalizing is where the results are signed off, so it is where the approval gets
        // recorded. Reports read it from here instead of the report-generation timestamp.
        if (($params['type'] ?? '') === 'finalized') {
            $this->recordResultsApproval((int) $shipmentId, $params);
        }

        if (!$existData) {
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $sql = $db->select()->from(['s' => 'shipment'], ['shipment_id', 'shipment_code', 'status', 'number_of_samples', 'shipment_status' => 's.status'])
                ->join(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_code', 'distribution_date'])
                ->join(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id')
                ->join(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['scheme_name'])
                ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['first_name', 'last_name', 'lab_name', 'unique_identifier', 'country'])
                ->join(['c' => 'countries'], 'p.country=c.id', ['country_name' => 'iso_name'])
                ->joinLeft(['res' => 'r_results'], 'res.result_id=sp.final_result')
                ->where('s.shipment_id = ?', $shipmentId);
            $shipmentResult = $db->fetchAll($sql);
            if (isset($shipmentResult) && count($shipmentResult) > 0) {
                $data = [
                    'shipment_id' => $shipmentId,
                    'report_type' => $params['type'],
                    'requested_by' => $authNameSpace->admin_id,
                    'initated_by' => $authNameSpace->admin_id,
                    'requested_on' => new Zend_Db_Expr('now()'),
                    'previous_status' => 'evaluated',
                    'processing_started_at' => new Zend_Db_Expr('NOW()'),
                    'last_heartbeat' => new Zend_Db_Expr('NOW()'),
                    'date_finalised' => new Zend_Db_Expr('NOW()'),
                    'status' => 'pending',
                ];
                $saved = $db->insert('queue_report_generation', $data);
                if ($saved > 0) {
                    $db->update('shipment_participant_map', ['report_generated' => 'no'], ['shipment_id = ?' => $shipmentId]);
                    $queued = $db->update('shipment', ['report_in_queue' => 'yes', 'status' => 'queued'], ['shipment_id = ?' => $shipmentId]);
                    $this->auditReportQueue((int) $shipmentId, (string) $params['type'], false, $params);
                    return $queued;
                }
            }
        } else {
            $data = [
                'shipment_id' => $shipmentId,
                'report_type' => $params['type'],
                'last_updated_on' => new Zend_Db_Expr('now()'),
                'previous_status' => 'evaluated',
                'requested_on' => new Zend_Db_Expr('now()'),
                'processing_started_at' => new Zend_Db_Expr('NOW()'),
                'last_heartbeat' => new Zend_Db_Expr('NOW()'),
                'date_finalised' => new Zend_Db_Expr('NOW()'),
                'status' => 'pending',
                // This row is reused for every run of the shipment. Clear what the
                // previous run left, or the job page shows its end time and error
                // against the new run.
                'completed_at' => null,
                'error_message' => null,
            ];
            $updated = $db->update('queue_report_generation', $data, ['id = ?' => $existData['id']]);
            if ($updated > 0) {
                // Reset report_generated flags so progress starts from 0%
                $db->update('shipment_participant_map', ['report_generated' => 'no'], ['shipment_id = ?' => $shipmentId]);
                $db->update('shipment', ['report_in_queue' => 'yes', 'status' => 'queued'], ['shipment_id = ?' => $shipmentId]);
                $this->auditReportQueue((int) $shipmentId, (string) $params['type'], true, $params);
            }
            return $updated;
        }
    }

    /**
     * Log a report generation or finalization request. Finalizing and
     * generating reports again are flagged.
     *
     * @param array<string, mixed> $params the request, for the approval fields
     */
    private function auditReportQueue(int $shipmentId, string $type, bool $again, array $params): void
    {
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $code = (string) $db->fetchOne($db->select()->from('shipment', ['shipment_code'])->where('shipment_id = ?', $shipmentId));
            $finalize = $type === 'finalized';
            $facts = ['Generated before' => $again];
            if ($finalize) {
                $facts['Results approved on'] = trim((string) ($params['resultsApprovedOn'] ?? ''));
                $facts['Results approved by'] = trim((string) ($params['resultsApprovedBy'] ?? ''));
            }
            Application_Model_DbTable_AuditLog::record(
                ($finalize ? 'Queued finalization of shipment - ' : 'Queued report generation for shipment - ') . $code,
                'shipment',
                [
                    'entity' => ['type' => 'shipment', 'id' => $shipmentId, 'label' => $code],
                    'facts' => $facts,
                    'important' => $finalize || $again,
                    'reason' => $finalize ? 'Finalizing publishes results to participants' : 'Replaces reports generated earlier',
                ]
            );
        } catch (Throwable $e) {
            Pt_Commons_LoggerUtility::logWarning('auditReportQueue failed: ' . $e->getMessage());
        }
    }

    public function scheduleEvaluation($shipmentId, $requestedBy = false)
    {
        $scheduledDb = new Application_Model_DbTable_ScheduledJobs();
        return $scheduledDb->scheduleEvaluation($shipmentId, $requestedBy);
    }

    public function getEvaluateReportsInPdf($shipmentId, $sLimit, $sOffset)
    {
    }

    /**
     * Get job progress for a specific shipment (for AJAX polling)
     * @param int $shipmentId
     * @return array
     */
    public function getJobProgress($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Get queue status
        $queueRow = $db->fetchRow(
            $db->select()
                ->from('queue_report_generation')
                ->where('shipment_id = ?', $shipmentId)
                ->order('id DESC')
                ->limit(1)
        );

        if (!$queueRow) {
            // No report job yet (for example a first evaluation). shipment_status still
            // has to come back: shipment-run-status.js refreshes a row once the shipment
            // leaves queued/processing, and would refresh in a loop without it.
            return [
                'status' => 'idle',
                'in_progress' => false,
                'shipment_status' => (string) ($db->fetchOne(
                    $db->select()->from('shipment', 'status')->where('shipment_id = ?', $shipmentId)
                ) ?: 'unknown'),
                'participant_reports' => ['total' => 0, 'completed' => 0, 'percentage' => 0],
                'summary_report' => ['status' => 'not_started'],
            ];
        }

        // Participants who get a report, by the same rule report generation uses.
        $gets = self::sqlGetsIndividualReport('sp');
        $progressRow = $db->fetchRow(
            $db->select()
                ->from(['sp' => 'shipment_participant_map'], [
                    'total' => new Zend_Db_Expr("SUM(CASE WHEN $gets THEN 1 ELSE 0 END)"),
                    'completed' => new Zend_Db_Expr("SUM(CASE WHEN sp.report_generated = 'yes' AND $gets THEN 1 ELSE 0 END)"),
                ])
                ->where('sp.shipment_id = ?', $shipmentId)
        );

        $total = (int) ($progressRow['total'] ?? 0);
        $completed = (int) ($progressRow['completed'] ?? 0);

        // Determine overall status
        $inProgress = in_array($queueRow['status'], ['pending', 'generating', 'finalizing']);

        // Get shipment info for summary status
        $shipmentRow = $db->fetchRow(
            $db->select()
                ->from('shipment', ['status', 'shipment_code'])
                ->where('shipment_id = ?', $shipmentId)
        );

        // Workers set report_generated only when their whole share is done, so a
        // running job read 0% until the end. The output folder is emptied when a run
        // starts, so the participant PDFs already in it are the live count.
        if (in_array($queueRow['status'], ['generating', 'finalizing'], true) && !empty($shipmentRow['shipment_code'])) {
            // Every PDF in the folder, by name pattern only for what is NOT a participant
            // report: the summary files (<code>-summary.pdf, -screening-summary.pdf,
            // -confirmatory-summary.pdf) and the temporary <name>-tmp.pdf a layout writes
            // before flattening. File names are normalised (repeated hyphens collapse), so
            // matching on the shipment code would miss reports.
            $written = glob(DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR
                . $shipmentRow['shipment_code'] . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
            $participantPdfs = count(array_filter(
                $written,
                static fn ($file) => !str_ends_with(basename($file), 'summary.pdf') && !str_ends_with($file, '-tmp.pdf')
            ));
            $completed = max($completed, min($participantPdfs, $total));
        }
        $percentage = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

        // Check if summary report exists
        $summaryStatus = 'pending';
        if ($shipmentRow) {
            $summaryPath = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR .
                $shipmentRow['shipment_code'] . DIRECTORY_SEPARATOR .
                $shipmentRow['shipment_code'] . '-summary.pdf';

            if (file_exists($summaryPath)) {
                $summaryStatus = 'completed';
            } else {
                // Check for DTS-style summary reports
                $screeningPath = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR .
                    $shipmentRow['shipment_code'] . DIRECTORY_SEPARATOR .
                    $shipmentRow['shipment_code'] . '-screening-summary.pdf';
                $confirmatoryPath = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR .
                    $shipmentRow['shipment_code'] . DIRECTORY_SEPARATOR .
                    $shipmentRow['shipment_code'] . '-confirmatory-summary.pdf';

                if (file_exists($screeningPath) || file_exists($confirmatoryPath)) {
                    $summaryStatus = 'completed';
                } elseif (!$inProgress) {
                    $summaryStatus = 'not_started';
                }
            }
        }

        return [
            'status' => $queueRow['status'],
            'report_type' => $queueRow['report_type'],
            'in_progress' => $inProgress,
            'shipment_status' => $shipmentRow['status'] ?? 'unknown',
            'shipment_code' => $shipmentRow['shipment_code'] ?? '',
            'participant_reports' => [
                'total' => $total,
                'completed' => $completed,
                'percentage' => $percentage,
            ],
            'summary_report' => [
                'status' => $summaryStatus,
            ],
            'started_at' => $queueRow['processing_started_at'],
            'last_heartbeat' => $queueRow['last_heartbeat'],
            'requested_by' => $queueRow['requested_by'],
        ];
    }

    /**
     * Get all active jobs from both queues (unified view for job tracking page)
     * @param array $params DataTables parameters
     * @return array
     */
    public function getAllActiveJobs($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Parse DataTables parameters
        $sOffset = isset($params['iDisplayStart']) ? (int) $params['iDisplayStart'] : 0;
        $sLimit = isset($params['iDisplayLength']) && $params['iDisplayLength'] != '-1'
            ? (int) $params['iDisplayLength'] : 25;

        $filterJobType = $params['jobType'] ?? '';
        $filterStatus = $params['status'] ?? '';

        // Date range filter - default to last 6 months
        // Handle DD-MMM-YYYY format from daterangepicker (e.g., "13-Jul-2025")
        $dateFrom = date('Y-m-d', strtotime('-6 months'));
        $dateTo = date('Y-m-d');
        if (!empty($params['dateFrom'])) {
            $parsed = date_create_from_format('d-M-Y', $params['dateFrom']);
            if ($parsed) {
                $dateFrom = $parsed->format('Y-m-d');
            }
        }
        if (!empty($params['dateTo'])) {
            $parsed = date_create_from_format('d-M-Y', $params['dateTo']);
            if ($parsed) {
                $dateTo = $parsed->format('Y-m-d');
            }
        }

        $allJobs = [];

        // Query report generation jobs
        if (empty($filterJobType) || $filterJobType === 'Report Generation') {
            $reportJobsQuery = $db->select()
                ->from(['qrg' => 'queue_report_generation'], [
                    'job_id' => 'id',
                    'queue_type' => new Zend_Db_Expr("'report_generation'"),
                    'job_type' => new Zend_Db_Expr("'Report Generation'"),
                    'status',
                    'report_type',
                    'error_message',
                    'started_at' => 'processing_started_at',
                    'completed_at',
                    'last_heartbeat',
                    'last_updated_on',
                    'requested_on',
                ])
                ->joinLeft(['s' => 'shipment'], 'qrg.shipment_id = s.shipment_id', ['shipment_code', 'shipment_id'])
                ->joinLeft(['sa' => 'system_admin'], 'qrg.requested_by = sa.admin_id', [
                    'requested_by_name' => new Zend_Db_Expr("CONCAT(sa.first_name, ' ', sa.last_name)"),
                ])
                ->order('qrg.id DESC');

            // Apply status filter
            if (!empty($filterStatus)) {
                if ($filterStatus === 'processing') {
                    $reportJobsQuery->where("qrg.status IN ('generating', 'finalizing')");
                } elseif ($filterStatus === 'completed') {
                    $reportJobsQuery->where("qrg.status IN ('evaluated', 'reports generated', 'finalized')");
                } else {
                    $reportJobsQuery->where('qrg.status = ?', $filterStatus);
                }
            }

            // Apply date range filter
            $reportJobsQuery->where('DATE(COALESCE(qrg.processing_started_at, qrg.requested_on)) >= ?', $dateFrom);
            $reportJobsQuery->where('DATE(COALESCE(qrg.processing_started_at, qrg.requested_on)) <= ?', $dateTo);

            $reportJobsResult = $db->fetchAll($reportJobsQuery);

            // Add progress for report generation jobs
            foreach ($reportJobsResult as $job) {
                if (isset($job['shipment_id'])) {
                    $progress = $this->getJobProgress($job['shipment_id']);
                    $job['progress'] = $progress['participant_reports']['percentage'];
                    $job['progress_completed'] = $progress['participant_reports']['completed'];
                    $job['progress_total'] = $progress['participant_reports']['total'];
                    $job['summary_status'] = $progress['summary_report']['status'];
                } else {
                    $job['progress'] = 0;
                    $job['progress_completed'] = 0;
                    $job['progress_total'] = 0;
                    $job['summary_status'] = 'unknown';
                }
                $allJobs[] = $job;
            }
        }

        // Query scheduled jobs (excluding Send Emails which has its own tracking)
        if (empty($filterJobType) || !in_array($filterJobType, ['Report Generation', 'Send Emails'])) {
            $scheduledJobsQuery = $db->select()
                ->from(['sj' => 'scheduled_jobs'], [
                    'job_id',
                    'queue_type' => new Zend_Db_Expr("'scheduled_jobs'"),
                    'job_type' => new Zend_Db_Expr("CASE
						WHEN sj.job LIKE '%evaluate%' THEN 'Evaluation'
						WHEN sj.job LIKE '%certificate%' THEN 'Certificate Generation'
						WHEN sj.job LIKE '%send-reports-mail%' THEN 'Email Reports'
						WHEN sj.job LIKE '%send-emails%' THEN 'Send Emails'
						WHEN sj.job LIKE '%generate-tb-forms%' THEN 'TB Forms Generation'
						WHEN sj.job LIKE '%bulk-reset-passwords%' THEN 'Bulk Password Reset'
						ELSE 'Scheduled Job'
					END"),
                    'status',
                    'report_type' => new Zend_Db_Expr('NULL'),
                    'started_at' => 'started_on',
                    'completed_at' => 'completed_on',
                    'last_heartbeat' => new Zend_Db_Expr('NULL'),
                    'requested_on',
                    'job',
                ])
                ->joinLeft(['sa' => 'system_admin'], 'sj.requested_by = sa.admin_id', [
                    'requested_by_name' => new Zend_Db_Expr("CONCAT(sa.first_name, ' ', sa.last_name)"),
                ])
                // Exclude send-emails jobs from scheduled_jobs since we track them via temp_mail
                ->where("sj.job NOT LIKE '%send-emails%'")
                ->order('sj.job_id DESC');

            // Apply job type filter
            if (!empty($filterJobType)) {
                switch ($filterJobType) {
                    case 'Evaluation':
                        $scheduledJobsQuery->where("sj.job LIKE '%evaluate%'");
                        break;
                    case 'Certificate Generation':
                        $scheduledJobsQuery->where("sj.job LIKE '%certificate%'");
                        break;
                    case 'Email Reports':
                        $scheduledJobsQuery->where("sj.job LIKE '%send-reports-mail%'");
                        break;
                    case 'TB Forms Generation':
                        $scheduledJobsQuery->where("sj.job LIKE '%generate-tb-forms%'");
                        break;
                    case 'Bulk Password Reset':
                        $scheduledJobsQuery->where("sj.job LIKE '%bulk-reset-passwords%'");
                        break;
                }
            }

            // Apply status filter
            if (!empty($filterStatus)) {
                $scheduledJobsQuery->where('sj.status = ?', $filterStatus);
            }

            // Apply date range filter
            $scheduledJobsQuery->where('DATE(sj.requested_on) >= ?', $dateFrom);
            $scheduledJobsQuery->where('DATE(sj.requested_on) <= ?', $dateTo);

            $scheduledJobsResult = $db->fetchAll($scheduledJobsQuery);

            foreach ($scheduledJobsResult as $job) {
                $job['shipment_code'] = $this->extractShipmentFromJob($job['job'] ?? '');
                $job['shipment_id'] = null;
                $job['progress'] = null;
                $job['progress_completed'] = null;
                $job['progress_total'] = null;
                $job['summary_status'] = null;
                $allJobs[] = $job;
            }
        }

        // Query email queue (temp_mail) for Send Emails tracking
        if (empty($filterJobType) || $filterJobType === 'Send Emails') {
            // Group emails by date to show daily email batches
            $emailStatsQuery = $db->select()
                ->from(['tm' => 'temp_mail'], [
                    'queue_date' => new Zend_Db_Expr('DATE(tm.queued_on)'),
                    'total_emails' => new Zend_Db_Expr('COUNT(*)'),
                    'sent_count' => new Zend_Db_Expr("SUM(CASE WHEN tm.status = 'sent' THEN 1 ELSE 0 END)"),
                    'pending_count' => new Zend_Db_Expr("SUM(CASE WHEN tm.status IN ('pending', 'picked-to-process') THEN 1 ELSE 0 END)"),
                    'failed_count' => new Zend_Db_Expr("SUM(CASE WHEN tm.status IN ('failed', 'not-sent') THEN 1 ELSE 0 END)"),
                    'first_queued' => new Zend_Db_Expr('MIN(tm.queued_on)'),
                    'last_activity' => new Zend_Db_Expr('MAX(COALESCE(tm.sent_at, tm.updated_at, tm.queued_on))'),
                ])
                ->group('DATE(tm.queued_on)')
                ->order('queue_date DESC');

            // Apply date range filter
            $emailStatsQuery->where('DATE(tm.queued_on) >= ?', $dateFrom);
            $emailStatsQuery->where('DATE(tm.queued_on) <= ?', $dateTo);

            // Apply status filter for email jobs. Each condition matches the
            // status the loop below gives the day, so a filter shows the same
            // rows the Status column labels that way.
            if (!empty($filterStatus)) {
                if ($filterStatus === 'pending') {
                    $emailStatsQuery->having('pending_count > 0 AND sent_count = 0');
                } elseif ($filterStatus === 'processing') {
                    $emailStatsQuery->having('pending_count > 0 AND sent_count > 0');
                } elseif ($filterStatus === 'failed') {
                    $emailStatsQuery->having('pending_count = 0 AND failed_count > 0 AND sent_count = 0');
                } elseif ($filterStatus === 'completed') {
                    $emailStatsQuery->having('pending_count = 0 AND NOT (failed_count > 0 AND sent_count = 0)');
                } else {
                    // Any other status (cancelled, ...) never applies to an email day.
                    $emailStatsQuery->having('1 = 0');
                }
            }

            $emailStatsResult = $db->fetchAll($emailStatsQuery);

            foreach ($emailStatsResult as $emailBatch) {
                $total = (int) $emailBatch['total_emails'];
                $sent = (int) $emailBatch['sent_count'];
                $pending = (int) $emailBatch['pending_count'];
                $failed = (int) $emailBatch['failed_count'];

                // Determine status based on counts
                $status = 'completed';
                if ($pending > 0) {
                    $status = $sent > 0 ? 'processing' : 'pending';
                } elseif ($failed > 0 && $sent === 0) {
                    $status = 'failed';
                }

                $progress = $total > 0 ? round(($sent / $total) * 100, 1) : 0;

                $allJobs[] = [
                    'job_id' => 'email_' . $emailBatch['queue_date'],
                    'queue_type' => 'email_queue',
                    'job_type' => 'Send Emails',
                    'status' => $status,
                    'report_type' => null,
                    'started_at' => $emailBatch['first_queued'],
                    'last_heartbeat' => $emailBatch['last_activity'],
                    'requested_on' => $emailBatch['first_queued'],
                    'job' => null,
                    'shipment_code' => date('M j, Y', strtotime($emailBatch['queue_date'])),
                    'shipment_id' => null,
                    'requested_by_name' => 'System',
                    'progress' => $progress,
                    'progress_completed' => $sent,
                    'progress_total' => $total,
                    'summary_status' => $failed > 0 ? "{$failed} failed" : null,
                ];
            }
        }

        // Sort by started_at/requested_on descending
        usort($allJobs, function ($a, $b) {
            $aTime = $a['started_at'] ?? $a['requested_on'] ?? '';
            $bTime = $b['started_at'] ?? $b['requested_on'] ?? '';
            return strcmp($bTime, $aTime);
        });

        $iTotal = count($allJobs);
        $pagedJobs = array_slice($allJobs, $sOffset, $sLimit);

        return [
            'sEcho' => isset($params['sEcho']) ? (int) $params['sEcho'] : 0,
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iTotal,
            'aaData' => $pagedJobs,
        ];
    }

    /**
     * Extract shipment reference from job command string
     * @param string $jobCommand
     * @return string|null
     */
    private function extractShipmentFromJob($jobCommand)
    {
        // Try to extract shipment ID from job command like "send-reports-mail.php -s 123"
        if (preg_match('/-s\s+(\d+)/', $jobCommand, $matches)) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $shipment = $db->fetchRow(
                $db->select()
                    ->from('shipment', ['shipment_code'])
                    ->where('shipment_id = ?', $matches[1])
            );
            return $shipment['shipment_code'] ?? null;
        }
        return null;
    }

    /**
     * Cancel a job in the queue
     * @param int $jobId
     * @param string $queueType - 'report_generation' or 'scheduled_jobs'
     * @return array
     */
    public function cancelJob($jobId, $queueType)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        try {
            if ($queueType === 'report_generation') {
                // Get the job details first
                $job = $db->fetchRow(
                    $db->select()
                        ->from('queue_report_generation')
                        ->where('id = ?', $jobId)
                );

                if (!$job) {
                    return ['success' => false, 'message' => 'Job not found'];
                }

                // Check if job can be cancelled (only pending or processing jobs)
                $cancellableStatuses = ['pending', 'generating', 'finalizing'];
                if (!in_array($job['status'], $cancellableStatuses)) {
                    return ['success' => false, 'message' => 'Job cannot be cancelled - already completed or not in progress'];
                }

                // Update queue status to cancelled
                $db->update(
                    'queue_report_generation',
                    [
                        'status' => 'cancelled',
                        'previous_status' => $job['status'],
                        'last_updated_on' => new Zend_Db_Expr('NOW()'),
                        'processing_started_at' => null,
                        'last_heartbeat' => null,
                    ],
                    'id = ' . (int) $jobId
                );

                // Reset shipment report_in_queue flag so it can be re-queued
                $db->update(
                    'shipment',
                    ['report_in_queue' => 'no'],
                    'shipment_id = ' . (int) $job['shipment_id']
                );

                // Try to remove lock file if it exists
                $lockFile = '/tmp/ept-generate-shipment-reports-' . $job['shipment_id'] . '.lock';
                if (file_exists($lockFile)) {
                    @unlink($lockFile);
                }

                $code = (string) $db->fetchOne($db->select()->from('shipment', ['shipment_code'])->where('shipment_id = ?', (int) $job['shipment_id']));
                Application_Model_DbTable_AuditLog::record('Cancelled report generation for shipment - ' . $code, 'shipment', [
                    'entity' => ['type' => 'shipment', 'id' => (int) $job['shipment_id'], 'label' => $code],
                    'facts' => ['Report type' => $job['report_type'] ?? '', 'Job status' => $job['status']],
                ]);

                return ['success' => true, 'message' => 'Job cancelled successfully'];
            } elseif ($queueType === 'scheduled_jobs') {
                // Get the job details first
                $job = $db->fetchRow(
                    $db->select()
                        ->from('scheduled_jobs')
                        ->where('job_id = ?', $jobId)
                );

                if (!$job) {
                    return ['success' => false, 'message' => 'Job not found'];
                }

                // Check if job can be cancelled
                if (!in_array($job['status'], ['pending', 'processing'])) {
                    return ['success' => false, 'message' => 'Job cannot be cancelled - already completed'];
                }

                // Update job status to cancelled
                $db->update(
                    'scheduled_jobs',
                    [
                        'status' => 'cancelled',
                        'completed_on' => new Zend_Db_Expr('NOW()'),
                    ],
                    'job_id = ' . (int) $jobId
                );

                // Release the shipment's queued-flag so the "Generating TB Forms..."
                // button doesn't stay disabled forever (mirrors the report_in_queue
                // reset in the report_generation branch above).
                if (preg_match('/generate-tb-forms\.php\s+-s\s+\'?(\d+)/', $job['job'] ?? '', $m)) {
                    $db->update(
                        'shipment',
                        ['tb_form_generated' => 'no'],
                        'shipment_id = ' . (int) $m[1] . " AND tb_form_generated = 'queued'"
                    );
                }

                Application_Model_DbTable_AuditLog::record('Cancelled scheduled job #' . (int) $jobId, 'shipment', [
                    'facts' => ['Job' => (string) ($job['job'] ?? ''), 'Job status' => $job['status']],
                ]);

                return ['success' => true, 'message' => 'Job cancelled successfully'];
            } else {
                return ['success' => false, 'message' => 'Invalid queue type'];
            }
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Error cancelling job: ' . $e->getMessage()];
        }
    }

    /**
     * Get shipment participants with server-side pagination for DataTables
     *
     * @param int $shipmentId
     * @param array $parameters DataTables parameters (sEcho, iDisplayStart, iDisplayLength, sSearch, etc.)
     * @return array DataTables-compatible response
     */
    public function getShipmentParticipantsPaginated($shipmentId, $parameters)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $aColumns = [
            'sp.map_id',
            new Zend_Db_Expr('CONCAT(' . Application_Model_DbTable_Participants::participantNameExpr('p') . ", '(', COALESCE(p.unique_identifier, ''), ')')"),
            'p.state',
            'p.district',
            'sp.shipment_score',
            'sp.documentation_score',
            'res.result_name',
            'sp.response_status',
            'sp.shipment_test_report_date',
        ];

        $sLimit = '';
        $sOffset = 0;
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols'] ?? 1); $i++) {
                $colIdx = intval($parameters['iSortCol_' . $i] ?? 0);
                if (isset($parameters['bSortable_' . $colIdx]) && $parameters['bSortable_' . $colIdx] == 'true') {
                    if (isset($aColumns[$colIdx])) {
                        $sortDir = Pt_Commons_General::sanitizeSortDirection($parameters['sSortDir_' . $i] ?? 'asc');
                        $sOrder[] = new Zend_Db_Expr(((string) $aColumns[$colIdx]) . ' ' . $sortDir);
                    }
                }
            }
        }

        $sWhere = '';
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != '') {
            $searchArray = explode(' ', $parameters['sSearch']);
            $sWhereSub = '';
            foreach ($searchArray as $search) {
                if ($sWhereSub == '') {
                    $sWhereSub .= '(';
                } else {
                    $sWhereSub .= ' AND (';
                }
                $sWhereSub .= 'p.first_name LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' OR ';
                $sWhereSub .= 'p.last_name LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' OR ';
                $sWhereSub .= 'p.unique_identifier LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' OR ';
                $sWhereSub .= 'p.state LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' OR ';
                $sWhereSub .= 'p.district LIKE ' . Pt_Commons_General::sqlLikeContains($search) . '';
                $sWhereSub .= ')';
            }
            $sWhere .= $sWhereSub;
        }

        $sQuery = $db->select()
            ->from(['sp' => 'shipment_participant_map'], [
                new Zend_Db_Expr('SQL_CALC_FOUND_ROWS sp.map_id'),
                'sp.shipment_id',
                'sp.participant_id',
                'sp.shipment_score',
                'sp.documentation_score',
                'sp.final_result',
                'sp.response_status',
                'sp.shipment_test_report_date',
                'sp.evaluation_status',
                'sp.failure_reason',
                'sp.manual_override',
            ])
            ->join(['s' => 'shipment'], 's.shipment_id = sp.shipment_id', [
                'shipment_code',
                'scheme_type',
                'shipment_status' => 's.status',
            ])
            ->join(['sl' => 'scheme_list'], 'sl.scheme_id = s.scheme_type', ['scheme_name'])
            ->join(['p' => 'participant'], 'p.participant_id = sp.participant_id', [
                'first_name',
                'last_name',
                'unique_identifier',
                'state',
                'district',
            ])
            ->joinLeft(['res' => 'r_results'], 'res.result_id = sp.final_result', ['result_name'])
            ->where('sp.shipment_id = ?', $shipmentId);

        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->where($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        } else {
            $sQuery = $sQuery->order(['p.first_name ASC', 'p.last_name ASC']);
        }

        if ($sLimit !== '') {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $db->fetchAll($sQuery);
        $iTotal = $iFilteredTotal = $db->fetchOne('SELECT FOUND_ROWS()');

        $output = [
            'sEcho' => intval($parameters['sEcho'] ?? 0),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        foreach ($rResult as $aRow) {
            $row = [];

            // Determine button class based on result
            $btnClassName = 'btn-success';
            if (isset($aRow['final_result']) && $aRow['final_result'] == 2) {
                $btnClassName = 'btn-danger';
            } elseif (isset($aRow['final_result']) && $aRow['final_result'] == 3) {
                $btnClassName = 'btn-default';
            } elseif (!empty($aRow['failure_reason'])) {
                $warnings = Pt_Commons_JsonUtility::safeDecode($aRow['failure_reason']);
                if (!empty($warnings)) {
                    $btnClassName = 'btn-warning';
                }
            }

            // Determine response status text
            $responseStatus = 'Not Responded';
            if ($aRow['response_status'] == 'responded') {
                $responseStatus = 'Responded';
            } elseif ($aRow['response_status'] == 'nottested') {
                $responseStatus = 'Not Tested';
            }

            // Determine result text
            $resultText = 'Not Evaluated';
            if (isset($aRow['final_result']) && $aRow['final_result'] != '' && $aRow['final_result'] != 0) {
                $resultText = $aRow['result_name'] ?? 'Not Evaluated';
            }
            if ($resultText === 'Not Evaluated') {
                $btnClassName = 'btn-default';
            }

            // Format the row data
            $row[] = $aRow['map_id']; // Hidden column for expand functionality
            $row[] = '<a href="javascript:void(0);" class="btn btn-xs clicker ' . $btnClassName . '" data-btn-class="' . $btnClassName . '"><i class="icon-plus"></i></a>';
            $row[] = htmlspecialchars($aRow['first_name'] . ' ' . $aRow['last_name'] . ' (' . $aRow['unique_identifier'] . ')');
            $row[] = htmlspecialchars($aRow['state'] ?? '');
            $row[] = htmlspecialchars($aRow['district'] ?? '');
            $row[] = number_format((float) ($aRow['shipment_score'] ?? 0), 2);
            $row[] = number_format((float) ($aRow['documentation_score'] ?? 0), 2);
            $row[] = self::escapeCell($resultText);
            $row[] = $responseStatus;
            $row[] = !empty($aRow['shipment_test_report_date']) && $aRow['shipment_test_report_date'] != '0000-00-00 00:00:00'
                ? date('d-M-Y', strtotime($aRow['shipment_test_report_date']))
                : '-';

            $output['aaData'][] = $row;
        }

        return $output;
    }
}
