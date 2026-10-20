<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Application_Service_Reports
{
    protected $db;
    protected $common;
    protected $tempUploadDirectory;

    /** @var Zend_Translate */
    protected $translator;

    public function __construct()
    {
        $this->common = new Application_Service_Common();
        $this->tempUploadDirectory = realpath(TEMP_UPLOAD_PATH);
        $this->translator = Zend_Registry::get('translate');
        $this->db = Zend_Db_Table_Abstract::getDefaultAdapter();
    }

    /**
     * Rows for the Shipments report grid (reports/shipments), as DataTables JSON.
     *
     * Columns: Shipment, Scheme, Due date, Responses, Pass rate, Status, Downloads.
     *
     * Filters: startDate/endDate (shipment date), scheme, stages[] (see
     * SHIPMENT_STAGE_SQL), needsAttention ('yes') and includeCancelled ('yes').
     * The counts follow Application_Service_Shipments::getShipmentParticipationStats():
     * responded = responded, late or nottested, and only responders count as failed.
     *
     * $context 'participant' serves the data-manager copy of the page
     * (participant/shipments-reports): the response chart link uses that module,
     * and the admin-only Breakdown and PT admin downloads are left out.
     */
    private const SHIPMENT_STAGE_SQL = "CASE
            WHEN s.cancelled_at IS NOT NULL THEN 'cancelled'
            WHEN s.finalized_at IS NOT NULL OR s.status = 'finalized' THEN 'finalized'
            WHEN s.reports_generated_at IS NOT NULL THEN 'reports'
            WHEN s.evaluated_at IS NOT NULL OR s.status = 'evaluated' THEN 'evaluated'
            WHEN s.status = 'shipped' THEN 'shipped'
            ELSE 'notshipped'
        END";

    public function getAllShipments($parameters, string $context = 'admin')
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $stage = new Zend_Db_Expr(self::SHIPMENT_STAGE_SQL);
        $responded = "IFNULL(sp.response_status, '') IN ('responded','late','nottested')";
        $passed = 'IFNULL(sp.final_result, 0) = 1';
        $failed = "IFNULL(sp.final_result, 0) = 2 AND IFNULL(sp.response_status, '') IN ('responded','late')";

        $sortable = [
            's.shipment_date',
            'sl.scheme_name',
            's.response_deadline',
            new Zend_Db_Expr("SUM($responded) / NULLIF(COUNT(sp.map_id), 0)"),
            new Zend_Db_Expr("SUM($passed) / NULLIF(SUM($passed) + SUM($failed), 0)"),
            new Zend_Db_Expr("FIELD(stage, 'notshipped','shipped','evaluated','reports','finalized','cancelled')"),
        ];

        $query = $db->select()
            ->from(['s' => 'shipment'], [
                's.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date', 's.response_deadline',
                's.status', 's.evaluated_at', 's.reports_generated_at', 's.finalized_at', 's.cancelled_at',
                'stage' => $stage,
                'feedback_count' => new Zend_Db_Expr('(SELECT COUNT(*) FROM participant_feedback_answer pfa WHERE pfa.shipment_id = s.shipment_id)'),
            ])
            ->join(['sl' => 'scheme_list'], 's.scheme_type = sl.scheme_id', ['scheme_name'])
            ->join(['d' => 'distributions'], 'd.distribution_id = s.distribution_id', ['distribution_code', 'distribution_date'])
            ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.shipment_id = s.shipment_id', [
                'enrolled' => new Zend_Db_Expr('COUNT(sp.map_id)'),
                'responded' => new Zend_Db_Expr("SUM($responded)"),
                'passed' => new Zend_Db_Expr("SUM($passed)"),
                'failed' => new Zend_Db_Expr("SUM($failed)"),
            ])
            ->group('s.shipment_id');

        $scopedDmId = Application_Service_Common::getScopedDmId();
        if (!empty($scopedDmId)) {
            $query->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id = sp.participant_id', [])
                ->where('pmm.dm_id = ?', $scopedDmId);
        }
        if (!empty($parameters['scheme'])) {
            $query->where('s.scheme_type = ?', $parameters['scheme']);
        }
        if (!empty($parameters['startDate']) && !empty($parameters['endDate'])) {
            $query->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($parameters['startDate']))
                ->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($parameters['endDate']));
        }
        // Cancelling is our soft-delete: cancelled shipments stay out unless asked for.
        $includeCancelled = ($parameters['includeCancelled'] ?? '') === 'yes';
        if (!$includeCancelled) {
            $query->where('s.cancelled_at IS NULL');
        }
        $stages = array_values(array_intersect(
            (array) ($parameters['stages'] ?? []),
            ['notshipped', 'shipped', 'evaluated', 'reports', 'finalized', 'cancelled']
        ));
        if (!empty($stages)) {
            $query->where(self::SHIPMENT_STAGE_SQL . ' IN (?)', $stages);
        }
        // Needs attention: open past its due date but not evaluated, or evaluated
        // but not finalized yet.
        if (($parameters['needsAttention'] ?? '') === 'yes') {
            $query->where('(' . self::SHIPMENT_STAGE_SQL . " = 'shipped' AND s.response_deadline < NOW())
                OR " . self::SHIPMENT_STAGE_SQL . " IN ('evaluated','reports')");
        }
        if (!empty($parameters['sSearch'])) {
            foreach (preg_split('/\s+/', trim($parameters['sSearch'])) as $term) {
                $like = Pt_Commons_General::sqlLikeContains($term);
                $query->where("(s.shipment_code LIKE $like OR d.distribution_code LIKE $like OR sl.scheme_name LIKE $like)");
            }
        }

        $filtered = clone $query;
        $iFilteredTotal = (int) $db->fetchOne($db->select()->from(['t' => $filtered], [new Zend_Db_Expr('COUNT(*)')]));

        $order = [];
        for ($i = 0; $i < (int) ($parameters['iSortingCols'] ?? 0); $i++) {
            $col = (int) ($parameters['iSortCol_' . $i] ?? -1);
            if (isset($sortable[$col]) && ($parameters['bSortable_' . $col] ?? '') === 'true') {
                $order[] = new Zend_Db_Expr($sortable[$col] . ' ' . Pt_Commons_General::sanitizeSortDirection($parameters['sSortDir_' . $i] ?? 'asc'));
            }
        }
        $query->order($order ?: ['s.shipment_date DESC']);
        if (isset($parameters['iDisplayStart'], $parameters['iDisplayLength']) && $parameters['iDisplayLength'] != '-1') {
            $query->limit((int) $parameters['iDisplayLength'], (int) $parameters['iDisplayStart']);
        }
        $rows = $db->fetchAll($query);

        // Scoped the same way as the grid, so the "of N" count never includes
        // cancelled rows the grid is hiding.
        $total = $db->select()->from(['s' => 'shipment'], [new Zend_Db_Expr('COUNT(DISTINCT s.shipment_id)')]);
        if (!$includeCancelled) {
            $total->where('s.cancelled_at IS NULL');
        }
        if (!empty($scopedDmId)) {
            $total->join(['sp' => 'shipment_participant_map'], 'sp.shipment_id = s.shipment_id', [])
                ->join(['pmm' => 'participant_manager_map'], 'pmm.participant_id = sp.participant_id', [])
                ->where('pmm.dm_id = ?', $scopedDmId);
        }

        $output = [
            'sEcho' => (int) ($parameters['sEcho'] ?? 0),
            'iTotalRecords' => (int) $db->fetchOne($total),
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];
        foreach ($rows as $aRow) {
            $output['aaData'][] = $this->shipmentReportRow($aRow, $context === 'participant');
        }
        echo json_encode($output);
    }

    private function shipmentReportRow(array $aRow, bool $forParticipant): array
    {
        $t = $this->translator;
        $esc = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $sid = base64_encode((string) $aRow['shipment_id']);
        $stage = $aRow['stage'];
        $enrolled = (int) $aRow['enrolled'];
        $responded = (int) $aRow['responded'];
        $evaluated = (int) $aRow['passed'] + (int) $aRow['failed'];

        // Shipment: code, with the PT survey on a second line when it differs. The
        // chevron opens the participation breakdown under the row. Every row has
        // one, so the codes line up. With no enrolments the breakdown says so.
        $survey = [];
        if ((string) $aRow['distribution_code'] !== (string) $aRow['shipment_code']) {
            $survey[] = $esc($aRow['distribution_code']);
        }
        $survey[] = $esc(Pt_Commons_DateUtility::humanReadableDateFormat($aRow['shipment_date']));
        $toggle = '';
        if (!$forParticipant) {
            $toggle = '<a href="javascript:void(0);" class="toggle-stats-row" data-sid="' . $sid . '" title="' . $esc($t->_('Breakdown')) . '">'
                . '<i class="icon-chevron-right" aria-hidden="true"></i></a>';
        }
        // The toggle sits in its own column, so the date lines up under the code.
        $shipment = '<div class="shipment-cell">' . $toggle
            . '<div><strong>' . $esc($aRow['shipment_code']) . '</strong>'
            . '<span class="cell-sub">' . implode(' &middot; ', $survey) . '</span></div></div>';

        // Due date. The cutoff timezone is named once, in the column header, and in
        // full on hover. An end-of-day deadline leaves its time out.
        $due = '';
        if (!empty($aRow['response_deadline'])) {
            $endOfDay = in_array(substr((string) $aRow['response_deadline'], 11, 5), ['23:59', '00:00', ''], true);
            $due = '<span title="' . $esc(Pt_Commons_DateUtility::cutoffTimezoneLabel($aRow['response_deadline'])) . '">'
                . $esc(Pt_Commons_DateUtility::humanReadableDateFormat($aRow['response_deadline'], !$endOfDay)) . '</span>';
        }

        // Responses and pass rate share one shape: the rate, a bar, then "x of y".
        $rateCell = function (?float $rate, string $barClass, string $sub, ?string $href = null) use ($esc) {
            $figure = $rate === null ? '' : $rate . '%';
            if ($href !== null) {
                $figure = '<a href="' . $href . '" target="_blank" title="' . $esc($this->translator->_('Response chart')) . '">' . $figure . '</a>';
            }
            return '<span class="rate-figure">' . $figure . '</span>'
                . '<span class="rate-bar ' . $barClass . '"><span style="width:' . (float) $rate . '%"></span></span>'
                . '<span class="cell-sub">' . $sub . '</span>';
        };
        $chartHref = ($forParticipant ? '/participant/response-chart/id/' : '/reports/shipments/response-chart/id/') . $sid
            . '/shipmentDate/' . base64_encode((string) $aRow['distribution_date'])
            . '/shipmentCode/' . base64_encode((string) $aRow['distribution_code']);
        $responses = $rateCell(
            $enrolled > 0 ? round($responded / $enrolled * 100, 1) : 0.0,
            '',
            sprintf($esc($t->_('%s of %s')), number_format($responded), number_format($enrolled)),
            $chartHref
        );

        // Pass rate over evaluated responses only.
        $pass = $evaluated > 0
            ? $rateCell(
                round((int) $aRow['passed'] / $evaluated * 100, 1),
                'is-pass',
                sprintf($esc($t->_('%s of %s evaluated')), number_format((int) $aRow['passed']), number_format($evaluated))
            )
            : '<span class="cell-muted">' . $esc($t->_('Not evaluated yet')) . '</span>';

        $stageLabels = [
            'notshipped' => $t->_('Not shipped'),
            'shipped' => $t->_('Open for responses'),
            'evaluated' => $t->_('Evaluated'),
            'reports' => $t->_('Reports generated'),
            'finalized' => $t->_('Finalized'),
            'cancelled' => $t->_('Cancelled'),
        ];
        $status = '<span class="stage-pill stage-' . $esc($stage) . '">' . $esc($stageLabels[$stage] ?? $stage) . '</span>';
        if ($stage === 'shipped' && !empty($aRow['response_deadline']) && strtotime($aRow['response_deadline']) < time()) {
            $status .= '<span class="cell-sub is-warning">' . $esc($t->_('Past due date')) . '</span>';
        }
        // Once reports exist, the status links to the shipment's reports page,
        // which holds the summary, the ZIP of all reports and download tracking.
        if (!$forParticipant && in_array($stage, ['reports', 'finalized'], true)) {
            $status .= $stage === 'finalized'
                ? '<a class="status-link" href="/reports/finalize/view-finalized-shipment/sid/' . $sid . '">' . $esc($t->_('Reports')) . ' &rarr;</a>'
                : '<a class="status-link is-draft" href="/reports/distribution/shipment/sid/' . $sid . '">' . $esc($t->_('Draft reports')) . ' &rarr;</a>';
        }

        return [$shipment, $esc($aRow['scheme_name']), $due, $responses, $pass, $status, $this->shipmentReportDownloads($aRow, $forParticipant)];
    }

    // Downloads for one shipment row, on one line: Overview as the button, then
    // whichever working files apply as small links. The generated reports are
    // reached from the Status cell instead (see shipmentReportRow()).
    //
    // The participant copy of the page has no reports page to link to, so it
    // lists the summary and the ZIP of all reports here when they exist.
    private function shipmentReportDownloads(array $aRow, bool $forParticipant): string
    {
        $t = $this->translator;
        $esc = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        // Each download has its own icon. The title says what the file holds.
        $link = fn (string $label, string $title, string $icon, string $attrs, string $class = 'dl-link') => '<a class="' . $class . '" title="' . $esc($title) . '" ' . $attrs . '>'
            . '<i class="' . $icon . '" aria-hidden="true"></i> ' . $esc($label) . '</a>';
        $sid = base64_encode((string) $aRow['shipment_id']);
        $stage = $aRow['stage'];
        // A cancelled shipment offers nothing to download.
        if ($stage === 'cancelled') {
            return '<span class="cell-muted">&ndash;</span>';
        }
        $isShipped = $stage !== 'notshipped';

        $primary = '';
        $extras = [];
        if ($forParticipant) {
            if (in_array($stage, ['reports', 'finalized'], true)) {
                $folder = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR;
                $summaryPath = $folder . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . '-summary.pdf';
                $zipPath = $folder . $aRow['shipment_code'] . '.zip';
                if (file_exists($summaryPath)) {
                    $extras[] = $link($t->_('Summary'), $t->_('Summary report (PDF)'), 'icon-file-text', 'target="_blank" href="' . $esc(Pt_Commons_SignedDownload::url($summaryPath)) . '"');
                }
                if (file_exists($zipPath)) {
                    $extras[] = $link($t->_('All reports'), $t->_("Every participant's report in one ZIP file"), 'icon-archive', 'target="_blank" href="' . $esc(Pt_Commons_SignedDownload::url($zipPath)) . '"');
                }
            }
        } elseif ($isShipped) {
            $primary = $link($t->_('Overview'), $t->_('Participant list, reported results and scores (Excel workbook)'), 'icon-table', 'href="javascript:void(0);" onclick="generateShipmentParticipantList(\'' . $sid . '\', \'' . $esc($aRow['scheme_type']) . '\')"', 'dl-btn');
            if ($stage !== 'finalized') {
                $extras[] = $link($t->_('Non-responders'), $t->_('Participants who have not responded yet (Excel)'), 'icon-warning-sign', 'href="javascript:void(0);" onclick="exportNotRespondedShipment(\'' . $sid . '\', \''
                    . base64_encode((string) $aRow['shipment_code']) . '\', \''
                    . base64_encode((string) Pt_Commons_DateUtility::humanReadableDateFormat($aRow['distribution_date'])) . '\')"');
            }
            if ((int) $aRow['feedback_count'] > 0) {
                $extras[] = $link($t->_('Feedback'), $t->_("Participants' feedback answers (Excel)"), 'icon-comments', 'href="javascript:void(0);" onclick="downloadFeedbackReport(' . (int) $aRow['shipment_id'] . ')"');
            }
        }
        // The forms are prepared before the round ships and matter while it runs,
        // not once it is finalized.
        if (!$forParticipant && $stage !== 'finalized' && $aRow['scheme_type'] === 'tb' && Application_Service_Common::getConfig('instance') === 'mtbept') {
            $tbFormPath = Application_Service_Shipments::resolveTbFormsZip($aRow['shipment_code']);
            if ($tbFormPath !== null) {
                $extras[] = $link($t->_('TB forms'), $t->_('Result forms for every participant (ZIP)'), 'icon-file-alt', 'target="_blank" href="/admin/shipment/download-tb/sid/' . (int) $aRow['shipment_id'] . '/file/' . base64_encode($tbFormPath) . '"');
            }
        }

        if ($primary === '' && !$extras) {
            return '<span class="cell-muted">&ndash;</span>';
        }
        return '<div class="download-links">' . $primary . implode('<span class="dl-sep" aria-hidden="true">&middot;</span>', $extras) . '</div>';
    }

    public function updateReportConfigs($params)
    {
        $filterRules = ['*' => 'StripTags', '*' => 'StringTrim'];
        $filter = new Zend_Filter_Input($filterRules, null, $params);
        if ($filter->isValid()) {
            //$params = $filter->getEscaped();
            $db = new Application_Model_DbTable_GlobalConfig();
            $db->getAdapter()->beginTransaction();
            try {
                $result = $db->updateReportDetails($params);
                //$alertMsg = new Zend_Session_Namespace('alert');
                //$alertMsg->msg=" documents submitted successfully.";

                $db->getAdapter()->commit();
                return $result;
            } catch (Throwable $exc) {
                $db->getAdapter()->rollBack();
                Pt_Commons_LoggerUtility::logError($exc->getMessage(), [
                    'file' => $exc->getFile(),
                    'line' => $exc->getLine(),
                    'trace' => $exc->getTraceAsString(),
                ]);
            }
        }
    }

    /** Report-context settings; the report-scoped counterpart of Common::getConfig(). */
    public function getReportConfigValue($name)
    {
        return Application_Service_Common::getSetting($name, Application_Model_DbTable_GlobalConfig::CONTEXT_REPORT);
    }

    /**
     * The groupings of the Participant Response Report. Each maps to the SQL
     * that names a participant's group. An empty name means the participant
     * has no value for that grouping.
     */
    private const RESPONSE_GROUPINGS = [
        'region' => "IFNULL(NULLIF(NULLIF(TRIM(p.region), ''), 'NULL'), '')",
        'district' => "IFNULL(NULLIF(NULLIF(TRIM(p.district), ''), 'NULL'), '')",
        'network' => "IFNULL(NULLIF(NULLIF(TRIM(n.network_name), ''), 'NULL'), '')",
        'affiliation' => "IFNULL(NULLIF(NULLIF(TRIM(p.affiliation), ''), 'NULL'), '')",
        'enrolled-programs' => "IFNULL(NULLIF(NULLIF(TRIM(rep.enrolled_programs), ''), 'NULL'), '')",
    ];

    /** The HAVING condition behind each performance filter button. */
    private const PERFORMANCE_FILTERS = [
        'failed' => 'failed > 0',
        'missed' => 'not_responded > 0',
        'clean' => 'passed > 0 AND failed = 0',
    ];

    /** Outcome => [label, pill class]. The labels go through the translator. */
    private const PERFORMANCE_OUTCOMES = [
        'pass' => ['Pass', 'is-ok'],
        'fail' => ['Fail', 'is-bad'],
        'none' => ['Not responded', 'is-bad'],
        'nottested' => ['Could not test', 'is-warn'],
        'excluded' => ['Excluded', 'is-muted'],
        'pending' => ['Not evaluated', 'is-muted'],
    ];

    /**
     * One outcome per enrolment, the SQL twin of performanceOutcome(): none
     * (did not respond), nottested, excluded, pass, fail or pending (responded,
     * not evaluated yet). Each enrolment gets exactly one.
     */
    private const RESPONSE_OUTCOME_SQL = "CASE
            WHEN IFNULL(sp.response_status, '') NOT IN ('responded','late','nottested') THEN 'none'
            WHEN sp.response_status = 'nottested' OR IFNULL(sp.is_pt_test_not_performed, 'no') = 'yes' THEN 'nottested'
            WHEN IFNULL(sp.is_excluded, 'no') = 'yes' OR IFNULL(sp.final_result, 0) = 3 THEN 'excluded'
            WHEN sp.final_result = 1 THEN 'pass'
            WHEN sp.final_result = 2 THEN 'fail'
            ELSE 'pending'
        END";

    /** TB outcome => [label, pill class]. TB reports say Satisfactory and Unsatisfactory. */
    private const TB_RESULT_OUTCOMES = [
        'pass' => ['Satisfactory', 'is-ok'],
        'fail' => ['Unsatisfactory', 'is-bad'],
        'excluded' => ['Excluded', 'is-muted'],
        'nottested' => ['Could not test', 'is-warn'],
        'none' => ['Not responded', 'is-bad'],
        'pending' => ['Not evaluated', 'is-muted'],
    ];

    /**
     * Participant Response Report: response and result counts per group of
     * participants (region, district, network tier, affiliation or enrolled
     * program). With $byShipment, one row per group and shipment.
     *
     * The response counts split every enrolment into exactly one of on time,
     * late, could not test and not responded, so they add up to enrolled.
     * Pass and fail follow Application_Service_Shipments::getShipmentParticipationStats().
     *
     * $params: groupBy, startDate, endDate, scheme, and group (one group's name,
     * to narrow the rows to that group).
     */
    public function getParticipantResponseGroups(array $params, bool $byShipment = false): array
    {
        $groupBy = isset(self::RESPONSE_GROUPINGS[$params['groupBy'] ?? '']) ? $params['groupBy'] : 'region';
        $groupExpr = self::RESPONSE_GROUPINGS[$groupBy];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $responded = "IFNULL(sp.response_status, '') IN ('responded','late','nottested')";
        $notTested = "(sp.response_status = 'nottested' OR IFNULL(sp.is_pt_test_not_performed, '') = 'yes')";
        $late = "(sp.response_status = 'late' OR IFNULL(sp.is_response_late, '') = 'yes')";

        $query = $db->select()
            ->from(['sp' => 'shipment_participant_map'], [
                'group_name' => new Zend_Db_Expr($groupExpr),
                'participants' => new Zend_Db_Expr('COUNT(DISTINCT sp.participant_id)'),
                'enrolled' => new Zend_Db_Expr('COUNT(sp.map_id)'),
                'responded' => new Zend_Db_Expr("SUM($responded)"),
                'on_time' => new Zend_Db_Expr("SUM($responded AND NOT $notTested AND NOT $late)"),
                'late' => new Zend_Db_Expr("SUM($responded AND NOT $notTested AND $late)"),
                'not_tested' => new Zend_Db_Expr("SUM($responded AND $notTested)"),
                'passed' => new Zend_Db_Expr('SUM(IFNULL(sp.final_result, 0) = 1)'),
                'failed' => new Zend_Db_Expr("SUM(IFNULL(sp.final_result, 0) = 2 AND sp.response_status IN ('responded','late'))"),
                'excluded' => new Zend_Db_Expr("SUM(IFNULL(sp.is_excluded, '') = 'yes')"),
            ])
            ->join(['s' => 'shipment'], 's.shipment_id = sp.shipment_id', [])
            ->join(['p' => 'participant'], 'p.participant_id = sp.participant_id', [])
            ->where('s.cancelled_at IS NULL');

        if ($groupBy === 'network') {
            $query->joinLeft(['n' => 'r_network_tiers'], 'n.network_id = p.network_tier', []);
        } elseif ($groupBy === 'enrolled-programs') {
            // A participant in two programs counts in both groups.
            $query->joinLeft(['pe' => 'participant_enrolled_programs_map'], 'pe.participant_id = p.participant_id', [])
                ->joinLeft(['rep' => 'r_enrolled_programs'], 'rep.r_epid = pe.ep_id', []);
        }

        if ($byShipment) {
            $query->join(['sl' => 'scheme_list'], 'sl.scheme_id = s.scheme_type', [])
                ->columns(['s.shipment_id', 's.shipment_code', 's.shipment_date', 'sl.scheme_name'])
                ->group([new Zend_Db_Expr($groupExpr), 's.shipment_id'])
                ->order([new Zend_Db_Expr("$groupExpr = ''"), new Zend_Db_Expr($groupExpr), new Zend_Db_Expr('s.shipment_date DESC')]);
        } else {
            // The groups with the most enrolments come first. Participants without a group go last.
            $query->group(new Zend_Db_Expr($groupExpr))
                ->order([new Zend_Db_Expr("$groupExpr = ''"), new Zend_Db_Expr('COUNT(sp.map_id) DESC'), new Zend_Db_Expr($groupExpr)]);
        }

        if (array_key_exists('group', $params) && $params['group'] !== null) {
            $query->where("$groupExpr = ?", (string) $params['group']);
        }
        if (!empty($params['scheme'])) {
            $query->where('s.scheme_type = ?', $params['scheme']);
        }
        if (!empty($params['startDate'])) {
            $query->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($params['startDate']));
        }
        if (!empty($params['endDate'])) {
            $query->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($params['endDate']));
        }
        $scopedDmId = Application_Service_Common::getScopedDmId();
        if (!empty($scopedDmId)) {
            $query->join(['pmm' => 'participant_manager_map'], 'pmm.participant_id = p.participant_id', [])
                ->where('pmm.dm_id = ?', $scopedDmId);
        }

        $rows = [];
        foreach ($db->fetchAll($query) as $row) {
            foreach (['participants', 'enrolled', 'responded', 'on_time', 'late', 'not_tested', 'passed', 'failed', 'excluded'] as $key) {
                $row[$key] = (int) ($row[$key] ?? 0);
            }
            $row['not_responded'] = $row['enrolled'] - $row['responded'];
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Excel export of the Participant Response Report: a sheet with one row per
     * group, and a sheet with one row per group and shipment.
     * Returns the file name in the temporary folder, or '' on failure.
     */
    public function exportParticipantResponseGroups(array $params): string
    {
        try {
            $groups = $this->getParticipantResponseGroups($params);
            if (empty($groups)) {
                return '';
            }

            $t = fn ($text) => html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $pct = fn (int $part, int $whole) => $whole > 0 ? round($part / $whole * 100, 1) : null;
            $groupLabels = [
                'region' => $this->translator->_('Region'),
                'district' => $this->translator->_('District'),
                'network' => $this->translator->_('Network Tier'),
                'affiliation' => $this->translator->_('Affiliation'),
                'enrolled-programs' => $this->translator->_('Enrolled Program'),
            ];
            $groupLabel = html_entity_decode($groupLabels[$params['groupBy'] ?? ''] ?? $groupLabels['region'], ENT_QUOTES, 'UTF-8');
            $notSet = $t($this->translator->_('Not set'));
            $headings = [
                $t($this->translator->_('Enrolled')),
                $t($this->translator->_('Responded')),
                $t($this->translator->_('Response rate')) . ' (%)',
                $t($this->translator->_('On time')),
                $t($this->translator->_('Late')),
                $t($this->translator->_('Could not test')),
                $t($this->translator->_('Not responded')),
                $t($this->translator->_('Passed')),
                $t($this->translator->_('Failed')),
                $t($this->translator->_('Pass rate')) . ' (%)',
                $t($this->translator->_('Excluded')),
            ];
            $figures = fn (array $r) => [
                $r['enrolled'],
                $r['responded'],
                $pct($r['responded'], $r['enrolled']),
                $r['on_time'],
                $r['late'],
                $r['not_tested'],
                $r['not_responded'],
                $r['passed'],
                $r['failed'],
                $pct($r['passed'], $r['passed'] + $r['failed']),
                $r['excluded'],
            ];

            $excel = new Spreadsheet();
            $sheet = $excel->getActiveSheet();
            $sheet->setTitle(mb_substr(html_entity_decode($this->translator->_('Groups'), ENT_QUOTES, 'UTF-8'), 0, 31));
            $sheet->fromArray(array_merge([$groupLabel, $t($this->translator->_('Participants'))], $headings), null, 'A1');
            $rowNo = 2;
            foreach ($groups as $r) {
                $sheet->fromArray(array_merge([$r['group_name'] !== '' ? $r['group_name'] : $notSet, $r['participants']], $figures($r)), null, 'A' . $rowNo++);
            }

            $shipmentSheet = $excel->createSheet();
            $shipmentSheet->setTitle(mb_substr($t($this->translator->_('By shipment')), 0, 31));
            $shipmentSheet->fromArray(array_merge([$groupLabel, $t($this->translator->_('Scheme')), $t($this->translator->_('Shipment Code')), $t($this->translator->_('Shipment Date'))], $headings), null, 'A1');
            $rowNo = 2;
            foreach ($this->getParticipantResponseGroups($params, true) as $r) {
                $shipmentSheet->fromArray(array_merge([
                    $r['group_name'] !== '' ? $r['group_name'] : $notSet,
                    $r['scheme_name'],
                    $r['shipment_code'],
                    Pt_Commons_DateUtility::humanReadableDateFormat($r['shipment_date']),
                ], $figures($r)), null, 'A' . $rowNo++);
            }

            foreach ([$sheet, $shipmentSheet] as $ws) {
                $ws->getStyle('1:1')->getFont()->setBold(true);
                $ws->freezePane('A2');
                foreach ($ws->getColumnIterator() as $column) {
                    $ws->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
                }
            }
            $excel->setActiveSheetIndex(0);

            if (!is_dir($this->tempUploadDirectory)) {
                mkdir($this->tempUploadDirectory);
            }
            $filename = 'participant-response-' . date('d-M-Y-H-i-s') . '.xlsx';
            IOFactory::createWriter($excel, 'Xlsx')->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Throwable $exc) {
            Pt_Commons_LoggerUtility::logError('Failed to generate participant response report (Excel): ' . $exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            return '';
        }
    }

    public function getParticipantTrendsReport($parameters)
    {
        $searchColumns = [
            'sl.scheme_name',
            "DATE_FORMAT(s.shipment_date,'%d-%b-%Y')",
            's.shipment_code',
            'total_shipped',
            'total_responses',
            'valid_responses',
            'total_passed',
            'pass_percentage',
            //'average_score'
        ];
        $orderColumns = [
            'sl.scheme_name',
            's.shipment_date',
            's.shipment_code',
            new Zend_Db_Expr('count("sp.map_id")'),
            new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
            new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
            new Zend_Db_Expr('SUM(final_result = 1)'),
            new Zend_Db_Expr('((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100'),
            //'average_score'
        ];

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = 'shipment_id';

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
                $colSize = count($searchColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($searchColumns[$i] == '' || $searchColumns[$i] == null) {
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $searchColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' OR ';
                    } else {
                        $sWhereSub .= $searchColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' ';
                    }
                }
                $sWhereSub .= ')';
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($searchColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == 'true' && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == '') {
                    $sWhere .= $searchColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($parameters['sSearch_' . $i]) . ' ';
                } else {
                    $sWhere .= ' AND ' . $searchColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($parameters['sSearch_' . $i]) . ' ';
                }
            }
        }

        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(['s' => 'shipment'])
            ->join(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id')
            ->joinLeft(
                ['sp' => 'shipment_participant_map'],
                'sp.shipment_id=s.shipment_id',
                [
                    "DATE_FORMAT(s.shipment_date,'%d-%b-%Y')",
                    'total_shipped' => new Zend_Db_Expr('count("sp.map_id")'),
                    'total_responses' => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
                    'valid_responses' => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00%' AND is_excluded != 'yes'))"),
                    'total_passed' => new Zend_Db_Expr('(SUM(final_result = 1))'),
                    'pass_percentage' => new Zend_Db_Expr('((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100'),
                ]
            )
            ->joinLeft(['p' => 'participant'], 'p.participant_id=sp.participant_id')
            ->joinLeft(['rr' => 'r_results'], 'sp.final_result=rr.result_id')
            ->where('s.cancelled_at IS NULL')
            ->group(['s.shipment_id']);

        $scopedDmId = Application_Service_Common::getScopedDmId();
        if (!empty($scopedDmId)) {
            $sQuery = $sQuery
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $scopedDmId);
        }
        if (isset($parameters['scheme']) && $parameters['scheme'] != '') {
            $sQuery = $sQuery->where('s.scheme_type = ?', $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != '' && isset($parameters['endDate']) && $parameters['endDate'] != '') {

            $sQuery = $sQuery->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != '') {
            $sQuery = $sQuery->where('s.shipment_id = ?', $parameters['shipmentId']);
        }

        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->having($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        $chartSession = new Zend_Session_Namespace('timelinessChart');
        $chartSession->timelinessChartQuery = $sQuery;

        $sQuerySession = new Zend_Session_Namespace('ParticipantTrendsExcel');
        $sQuerySession->participantQuery = $sQuery;

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $dbAdapter->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sWhere = '';
        $sQuery = $dbAdapter->select()->from(['s' => 'shipment'], new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
        if (isset($parameters['scheme']) && $parameters['scheme'] != '') {
            $sQuery = $sQuery->where('s.scheme_type = ?', $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != '' && isset($parameters['endDate']) && $parameters['endDate'] != '') {

            $sQuery = $sQuery->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != '') {
            $sQuery = $sQuery->where('s.shipment_id = ?', $parameters['shipmentId']);
        }

        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->where($sWhere);
        }

        $aResultTotal = $dbAdapter->fetchCol($sQuery);
        $iTotal = $aResultTotal[0];

        /*
         * Output
         */
        $output = [
            'sEcho' => intval($parameters['sEcho']),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        foreach ($rResult as $aRow) {

            $row = [];
            $row['DT_RowId'] = 'shipment' . $aRow['shipment_id'];
            $row[] = $aRow['scheme_name'];
            $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($aRow['shipment_date']);
            $row[] = "<a href='javascript:void(0);' onclick='shipmetRegionReport(\"" . $aRow['shipment_id'] . '"),regionDetails("' . $aRow['scheme_name'] . '","' . Pt_Commons_DateUtility::humanReadableDateFormat($aRow['shipment_date']) . '","' . $aRow['shipment_code'] . "\")'>" . $aRow['shipment_code'] . '</a>';
            $row[] = $aRow['total_shipped'];
            $row[] = $aRow['total_responses'];
            $row[] = $aRow['valid_responses'];
            $row[] = $aRow['total_passed'];
            $row[] = round($aRow['pass_percentage'], 2);
            $row[] = round($aRow['average_score'], 2);

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getShipmentResponseReport($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = [
            'sl.scheme_name',
            'ref.sample_label',
            'ref.reference_result',
            'positive_responses',
            'negative_responses',
            'invalid_responses',
            new Zend_Db_Expr("SUM(response_status IN ('responded', 'nottested'))"),
            new Zend_Db_Expr('SUM(sp.final_result=1)'),
            new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00'))"),
        ];

        $searchColumns = [
            'sl.scheme_name',
            'ref.sample_label',
            'ref.reference_result',
            'positive_responses',
            'negative_responses',
            'invalid_responses',
            'total_responses',
            'total_passed',
            'valid_responses',
        ];
        $orderColumns = [
            'sl.scheme_name',
            'ref.sample_label',
            'ref.reference_result',
            'positive_responses',
            'negative_responses',
            'invalid_responses',
            new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
            new Zend_Db_Expr('SUM(sp.final_result=1)'),
            new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
        ];

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
                $colSize = count($searchColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($searchColumns[$i] == '' || $searchColumns[$i] == null) {
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $searchColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' OR ';
                    } else {
                        $sWhereSub .= $searchColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' ';
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

        if (isset($parameters['scheme']) && is_string($parameters['scheme']) && preg_match('/^[a-z0-9_]+$/', $parameters['scheme'])) {
            $refTable = 'reference_result_' . $parameters['scheme'];
            $resTable = 'response_result_' . $parameters['scheme'];

            // to count the total positive and negative, we need to know which r_possibleresults are positive and negative
            // so the following ...
            $rInderminate = 0;
            if ($parameters['scheme'] == 'dts') {
                $rPositive = 4;
                $rNegative = 5;
                $rInderminate = 6;
            } elseif ($parameters['scheme'] == 'dbs') {
                $rPositive = 7;
                $rNegative = 8;
            } elseif ($parameters['scheme'] == 'eid') {
                $rPositive = 10;
                $rNegative = 11;
            }
        }

        //$aColumns = array('sl.scheme_name', "ref.sample_label", 'ref.reference_result', 'positive_responses', 'negative_responses', new Zend_Db_Expr("SUM(sp.shipment_test_date <> '')"), new Zend_Db_Expr("SUM(sp.final_result = 1) + SUM(sp.final_result = 2)"));
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(['s' => 'shipment'], ['shipment_code'])
            ->join(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id')
            ->join(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', ['total_responses' => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"), 'total_passed' => new Zend_Db_Expr('SUM(sp.final_result=1)'), 'valid_responses' => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))")])
            //->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id')
            ->join(['ref' => $refTable], 's.shipment_id=ref.shipment_id')
            ->join(['res' => $resTable], 'sp.map_id=res.shipment_map_id', ['positive_responses' => new Zend_Db_Expr('SUM(if(res.reported_result = ' . $rPositive . ', 1, 0))'), 'negative_responses' => new Zend_Db_Expr('SUM(if(res.reported_result = ' . $rNegative . ', 1, 0))'), 'invalid_responses' => new Zend_Db_Expr('SUM(if(res.reported_result = ' . $rInderminate . ', 1, 0))')])
            ->join(['rr' => 'r_results'], 'sp.final_result=rr.result_id')
            ->join(['rp' => 'r_possibleresult'], 'ref.reference_result=rp.id')
            ->where('res.sample_id = ref.sample_id')
            ->group(['sp.shipment_id', 'ref.sample_label']);

        if (isset($parameters['scheme']) && $parameters['scheme'] != '') {
            $sQuery = $sQuery->where('s.scheme_type = ?', $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != '' && isset($parameters['endDate']) && $parameters['endDate'] != '') {

            $sQuery = $sQuery->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != '') {
            $sQuery = $sQuery->where('s.shipment_id = ?', $parameters['shipmentId']);
        }

        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->having($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        $sQuerySession = new Zend_Session_Namespace('shipmentExportExcel');
        $sQuerySession->shipmentExportQuery = $sQuery;

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $dbAdapter->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sWhere = '';
        $sQuery = $dbAdapter->select()->from(['ref' => $refTable], new Zend_Db_Expr("COUNT('ref.sample_label')"))
            ->join(['s' => 'shipment'], 's.shipment_id=ref.shipment_id', []);

        if (isset($parameters['scheme']) && $parameters['scheme'] != '') {
            $sQuery = $sQuery->where('s.scheme_type = ?', $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != '' && isset($parameters['endDate']) && $parameters['endDate'] != '') {

            $sQuery = $sQuery->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != '') {
            $sQuery = $sQuery->where('s.shipment_id = ?', $parameters['shipmentId']);
        }

        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->having($sWhere);
        }

        $aResultTotal = $dbAdapter->fetchCol($sQuery);
        $iTotal = $aResultTotal[0];

        /*
         * Output
         */
        $output = [
            'sEcho' => intval($parameters['sEcho']),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        foreach ($rResult as $aRow) {
            $row = [];
            $exclamation = '';
            if ($aRow['mandatory'] == 0) {
                $exclamation = "&nbsp;&nbsp;&nbsp;<i class='icon-exclamation' style='color:red;'></i>";
            }
            $row[] = $aRow['scheme_name'];
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['sample_label'] . $exclamation;
            $row[] = $aRow['response'];
            $row[] = $aRow['positive_responses'];
            $row[] = $aRow['negative_responses'];
            $row[] = $aRow['invalid_responses'];
            $row[] = $aRow['total_responses'];
            $row[] = $aRow['valid_responses'];
            // $row[] = $aRow['total_passed'];
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    /**
     * The test kits of the DTS responses that match the filters: one row per
     * response, kit and test (1, 2 or 3). A response takes its kits from its
     * first sample row, since a response uses the same kits for every sample.
     * $position narrows it to one test.
     */
    private function testKitUses(array $params, int $position = 0): Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $responses = $db->select()
            ->from(['res' => 'response_result_dts'], [
                'map_id' => 'res.shipment_map_id',
                'k1' => new Zend_Db_Expr("MAX(NULLIF(TRIM(res.test_kit_name_1), ''))"),
                'k2' => new Zend_Db_Expr("MAX(NULLIF(TRIM(res.test_kit_name_2), ''))"),
                'k3' => new Zend_Db_Expr("MAX(NULLIF(TRIM(res.test_kit_name_3), ''))"),
            ])
            ->join(['sp' => 'shipment_participant_map'], 'sp.map_id=res.shipment_map_id', [])
            ->join(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', [])
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id', [])
            ->where("sp.response_status IN ('responded','late')")
            ->where("IFNULL(sp.is_pt_test_not_performed, 'no') <> 'yes'")
            ->group('res.shipment_map_id');
        Application_Model_DbTable_Participants::applyResponseReportFilters($responses, ['scheme' => 'dts'] + $params);

        $positions = in_array($position, [1, 2, 3], true) ? [$position] : [1, 2, 3];
        $numbers = implode(' UNION ALL ', array_map(fn ($n) => "SELECT $n AS pos", $positions));

        return $db->select()
            ->from(['r' => $responses], [
                'r.map_id',
                'n.pos',
                'kit' => new Zend_Db_Expr('CASE n.pos WHEN 1 THEN r.k1 WHEN 2 THEN r.k2 ELSE r.k3 END'),
            ])
            ->join(['n' => new Zend_Db_Expr("($numbers)")], '1=1', [])
            ->having('kit IS NOT NULL');
    }

    /**
     * Test Kit Report summary: the totals over the matching responses that
     * name a kit, and one row per kit, most used first.
     */
    public function getTestKitSummary(array $params): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $rows = $db->fetchAll(
            $db->select()
                ->from(['u' => $this->testKitUses($params, (int) ($params['position'] ?? 0))], ['u.map_id', 'u.pos', 'u.kit'])
                ->join(['sp' => 'shipment_participant_map'], 'sp.map_id=u.map_id', ['sp.participant_id', 'sp.final_result']),
            [],
            Zend_Db::FETCH_NUM
        );

        // Counted in PHP: one pass over the rows is much quicker than
        // COUNT(DISTINCT) per column in MySQL. Sets are keyed by map id.
        $kits = [];
        $responses = [];
        $participants = [];
        foreach ($rows as [$mapId, $position, $kit, $participantId, $finalResult]) {
            $kits[$kit] ??= ['responses' => [], 'participants' => [], 'test1' => [], 'test2' => [], 'test3' => [], 'passed' => [], 'failed' => []];
            $kits[$kit]['responses'][$mapId] = true;
            $kits[$kit]['participants'][$participantId] = true;
            $kits[$kit]['test' . $position][$mapId] = true;
            if ((int) $finalResult === 1) {
                $kits[$kit]['passed'][$mapId] = true;
            } elseif ((int) $finalResult === 2) {
                $kits[$kit]['failed'][$mapId] = true;
            }
            $responses[$mapId] = true;
            $participants[$participantId] = true;
        }

        $names = $this->testKitNames();
        $out = [];
        foreach ($kits as $kit => $sets) {
            $out[] = ['id' => (string) $kit, 'name' => $names[(string) $kit] ?? ''] + array_map('count', $sets);
        }
        usort($out, fn ($a, $b) => [$b['responses'], $a['name']] <=> [$a['responses'], $b['name']]);

        return [
            'responses' => count($responses),
            'participants' => count($participants),
            'kits' => count($out),
            'rows' => $out,
        ];
    }

    /**
     * The responses that used a kit (or every kit, with $kitId null), newest
     * shipment first. Each row lists the tests the kit was used for.
     */
    public function getTestKitResponses(array $params, ?string $kitId = null, ?int $limit = null): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $db->select()
            ->from(['u' => $this->testKitUses($params, (int) ($params['position'] ?? 0))], [
                'kit_id' => 'u.kit',
                'tests' => new Zend_Db_Expr("GROUP_CONCAT(u.pos ORDER BY u.pos SEPARATOR ', ')"),
            ])
            ->join(['sp' => 'shipment_participant_map'], 'sp.map_id=u.map_id', [
                'sp.final_result',
                'score' => new Zend_Db_Expr('IFNULL(sp.shipment_score, 0) + IFNULL(sp.documentation_score, 0)'),
            ])
            ->join(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', ['s.shipment_code', 's.shipment_date'])
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id', [
                'p.unique_identifier',
                'p.institute_name',
                'p.district',
                'p.state',
                'participant_name' => new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameExpr('p')),
            ])
            ->joinLeft(['c' => 'countries'], 'c.id=p.country', ['c.iso_name'])
            ->group(['u.map_id', 'u.kit'])
            ->order(['s.shipment_date DESC', 'participant_name ASC', 'u.kit ASC']);

        if ($kitId !== null) {
            $sQuery->where('u.kit = ?', $kitId);
        }
        if ($limit !== null) {
            $sQuery->limit($limit);
        }
        $names = $this->testKitNames();
        return array_map(function ($row) use ($names) {
            $row['kit_name'] = $names[$row['kit_id']] ?? '';
            return $row;
        }, $db->fetchAll($sQuery));
    }

    /**
     * Test kit id => name. Read once: r_testkitnames has no index on the id
     * on some installs, so joining it row by row is slow.
     */
    private function testKitNames(): array
    {
        $names = [];
        $rows = Zend_Db_Table_Abstract::getDefaultAdapter()->fetchPairs('SELECT TestKitName_ID, TestKit_Name FROM r_testkitnames');
        foreach ($rows as $id => $name) {
            $names[(string) $id] = html_entity_decode(stripslashes((string) $name), ENT_QUOTES, 'UTF-8');
        }
        return $names;
    }

    /**
     * The drawer under a kit: the first $limit responses that used it, as
     * table rows.
     */
    public function getTestKitDrawer(array $params, string $kitId, int $limit = 100): string
    {
        $rows = $this->getTestKitResponses($params, $kitId, $limit + 1);
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8', false);
        $date = fn ($d) => Pt_Commons_DateUtility::humanReadableDateFormat($d ?? '');
        $results = [1 => ['Pass', 'is-ok'], 2 => ['Fail', 'is-bad']];

        $html = '<table class="table tk-responses"><thead><tr>'
            . '<th>' . $esc($this->translator->_('Participant')) . '</th>'
            . '<th>' . $esc($this->translator->_('Location')) . '</th>'
            . '<th>' . $esc($this->translator->_('Shipment')) . '</th>'
            . '<th>' . $esc($this->translator->_('Used as test')) . '</th>'
            . '<th>' . $esc($this->translator->_('Score')) . '</th>'
            . '<th>' . $esc($this->translator->_('Result')) . '</th>'
            . '</tr></thead><tbody>';
        foreach (array_slice($rows, 0, $limit) as $r) {
            $result = $results[(int) $r['final_result']] ?? ['Not evaluated', 'is-muted'];
            $sub = array_values(array_filter([$r['unique_identifier'], $r['institute_name']]));
            $name = trim((string) $r['participant_name']) !== '' ? $r['participant_name'] : array_shift($sub);
            $html .= '<tr>'
                . '<td><span class="cell-main">' . $esc($name) . '</span>'
                . (!empty($sub) ? '<span class="cell-sub">' . $esc(implode(' · ', $sub)) . '</span>' : '') . '</td>'
                . '<td>' . $esc(implode(', ', array_filter([$r['district'], $r['state'], $r['iso_name']]))) . '</td>'
                . '<td><span class="cell-main">' . $esc($r['shipment_code']) . '</span><span class="cell-sub">' . $esc($date($r['shipment_date'])) . '</span></td>'
                . '<td>' . $esc($r['tests']) . '</td>'
                . '<td>' . (isset($results[(int) $r['final_result']]) ? round((float) $r['score']) : '') . '</td>'
                . '<td><span class="resp-pill ' . $result[1] . '">' . $esc($this->translator->_($result[0])) . '</span></td>'
                . '</tr>';
        }
        $html .= '</tbody></table>';
        if (count($rows) > $limit) {
            $html .= '<p class="tk-more">' . $esc(sprintf($this->translator->_('The first %s responses. Download Excel for the full list.'), $limit)) . '</p>';
        }
        return $html;
    }

    /**
     * Test Kit Report as Excel: one sheet with a row per kit, one with a row
     * per response and kit.
     */
    public function exportTestKitReport(array $params): string
    {
        try {
            // The Responses sheet holds a row per response and kit.
            ini_set('memory_limit', '-1');
            $summary = $this->getTestKitSummary($params);
            if (empty($summary['rows'])) {
                return '';
            }
            $t = fn ($text) => html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $decode = fn ($text) => html_entity_decode(stripslashes((string) $text), ENT_QUOTES, 'UTF-8');
            $results = [1 => $t($this->translator->_('Pass')), 2 => $t($this->translator->_('Fail'))];
            $pct = fn (int $part, int $whole) => $whole > 0 ? round($part / $whole * 100, 1) : null;

            $excel = new Spreadsheet();
            $sheet = $excel->getActiveSheet();
            $sheet->setTitle(mb_substr($t($this->translator->_('Test kits')), 0, 31));
            $sheet->fromArray([
                $t($this->translator->_('Test kit')),
                $t($this->translator->_('Responses')),
                $t($this->translator->_('Share of responses')) . ' (%)',
                $t($this->translator->_('Participants')),
                $t($this->translator->_('Test 1')),
                $t($this->translator->_('Test 2')),
                $t($this->translator->_('Test 3')),
                $t($this->translator->_('Passed')),
                $t($this->translator->_('Failed')),
                $t($this->translator->_('Pass rate')) . ' (%)',
            ], null, 'A1');
            $rowNo = 2;
            foreach ($summary['rows'] as $k) {
                $sheet->fromArray([
                    $k['name'] !== '' ? $k['name'] : $k['id'],
                    $k['responses'],
                    $pct($k['responses'], $summary['responses']),
                    $k['participants'],
                    $k['test1'],
                    $k['test2'],
                    $k['test3'],
                    $k['passed'],
                    $k['failed'],
                    $pct($k['passed'], $k['passed'] + $k['failed']),
                ], null, 'A' . $rowNo++, true);
            }
            $sheet->getStyle('1:1')->getFont()->setBold(true);
            $sheet->freezePane('A2');

            $detail = $excel->createSheet();
            $detail->setTitle(mb_substr($t($this->translator->_('Responses')), 0, 31));
            $detail->fromArray([
                $t($this->translator->_('Test kit')),
                $t($this->translator->_('Used as test')),
                $t($this->translator->_('Participant ID')),
                $t($this->translator->_('Participant')),
                $t($this->translator->_('Institute')),
                $t($this->translator->_('District')),
                $t($this->translator->_('Province')),
                $t($this->translator->_('Country')),
                $t($this->translator->_('Shipment')),
                $t($this->translator->_('Shipment Date')),
                $t($this->translator->_('Score')),
                $t($this->translator->_('Result')),
            ], null, 'A1');
            $rowNo = 2;
            foreach ($this->getTestKitResponses($params) as $r) {
                $evaluated = isset($results[(int) $r['final_result']]);
                $detail->fromArray([
                    $r['kit_name'] !== '' ? $r['kit_name'] : $r['kit_id'],
                    $r['tests'],
                    $r['unique_identifier'],
                    $decode($r['participant_name']),
                    $decode($r['institute_name']),
                    $r['district'],
                    $r['state'],
                    $r['iso_name'],
                    $r['shipment_code'],
                    Pt_Commons_DateUtility::humanReadableDateFormat($r['shipment_date'] ?? ''),
                    $evaluated ? round((float) $r['score'], 2) : null,
                    $evaluated ? $results[(int) $r['final_result']] : $t($this->translator->_('Not evaluated')),
                ], null, 'A' . $rowNo++, true);
            }
            $detail->getStyle('1:1')->getFont()->setBold(true);
            $detail->freezePane('A2');

            foreach ($sheet->getColumnIterator() as $column) {
                $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }
            // Autosizing tens of thousands of rows takes long, so the
            // Responses sheet gets fixed widths.
            foreach (['A' => 30, 'B' => 12, 'C' => 16, 'D' => 34, 'E' => 34, 'F' => 18, 'G' => 18, 'H' => 16, 'I' => 16, 'J' => 14, 'K' => 8, 'L' => 14] as $col => $width) {
                $detail->getColumnDimension($col)->setWidth($width);
            }
            $excel->setActiveSheetIndex(0);

            if (!is_dir($this->tempUploadDirectory)) {
                mkdir($this->tempUploadDirectory);
            }
            $filename = 'test-kit-report-' . date('d-M-Y-H-i-s') . '.xlsx';
            IOFactory::createWriter($excel, 'Xlsx')->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Throwable $exc) {
            Pt_Commons_LoggerUtility::logError('Failed to generate test kit report (Excel): ' . $exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            return '';
        }
    }

    public function getShipmentResponseCount($shipmentId, $date, $step = 5, $maxDays = 60)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        $responseResult = [];
        $responseDate = [];

        for ($i = $step; $i <= $maxDays; $i += $step) {
            $sQuery = $dbAdapter->select()->from(['s' => 'shipment'], [''])
                ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', ['reported_count' => new Zend_Db_Expr("SUM(response_status IN ('responded', 'nottested'))")])
                ->where('s.shipment_id = ?', $shipmentId)
                ->group('s.shipment_id');

            // Create DateTime object and calculate end date
            $dateObj = new DateTime($date);
            $dateObj->add(new DateInterval('P' . $i . 'D'));
            $endDate = $dateObj->format('Y-m-d');

            $scopedDmId = Application_Service_Common::getScopedDmId();
            if (!empty($scopedDmId)) {
                $sQuery = $sQuery
                    ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=sp.participant_id', [])
                    ->where('pmm.dm_id = ?', $scopedDmId);
            }

            if (isset($date) && $date != '' && $endDate != '' && $i < $maxDays) {
                $sQuery = $sQuery->where('sp.shipment_receipt_date >= ?', $date);
                $sQuery = $sQuery->where('sp.shipment_receipt_date <= ?', $endDate);
                $result = $dbAdapter->fetchAll($sQuery);
                $count = (isset($result[0]['reported_count']) && $result[0]['reported_count'] != '') ? $result[0]['reported_count'] : 0;
                $responseResult[] = (int) $count;
                $responseDate[] = Pt_Commons_DateUtility::humanReadableDateFormat($date) . ' - ' . Pt_Commons_DateUtility::humanReadableDateFormat($endDate);

                // Update date for next iteration
                $nextDateObj = new DateTime($endDate);
                $nextDateObj->add(new DateInterval('P1D'));
                $date = $nextDateObj->format('Y-m-d');
            }

            if ($i == $maxDays) {
                $sQuery = $sQuery->where('sp.shipment_receipt_date >= ?', $date);
                $result = $dbAdapter->fetchAll($sQuery);
                $count = (isset($result[0]['reported_count']) && $result[0]['reported_count'] != '') ? $result[0]['reported_count'] : 0;
                $responseResult[] = (int) $count;
                $responseDate[] = Pt_Commons_DateUtility::humanReadableDateFormat($date) . ' and Above';
            }
        }

        return json_encode($responseResult) . '#' . json_encode($responseDate);
    }

    public function getShipmentParticipant($shipmentId, $schemeType = null)
    {
        $schemeDb = new Application_Model_DbTable_SchemeList();
        $uc = $schemeDb->checkUserConfig($schemeType);
        if ($schemeType == 'dts') {
            $dtsObj = new Application_Model_Dts();
            return $dtsObj->generateDtsRapidHivExcelReport($shipmentId);
        } elseif ($schemeType == 'vl') {
            $vlObj = new Application_Model_Vl();
            return $vlObj->generateDtsViralLoadExcelReport($shipmentId);
        } elseif ($schemeType == 'eid') {
            $eidObj = new Application_Model_Eid();
            return $eidObj->generateDbsEidExcelReport($shipmentId);
        } elseif ($schemeType == 'recency') {
            $recencyObj = new Application_Model_Recency();
            return $recencyObj->generateRecencyExcelReport($shipmentId);
        } elseif ($schemeType == 'covid19') {
            $rcovid19Obj = new Application_Model_Covid19();
            return $rcovid19Obj->generateCovid19ExcelReport($shipmentId);
        } elseif ($schemeType == 'tb') {
            $tbObj = new Application_Model_Tb();
            return $tbObj->generateTbExcelReport($shipmentId);
        } elseif ($schemeType == 'generic-test' || $uc == 'yes') {
            $genericTestObj = new Application_Model_CustomTest();
            return $genericTestObj->generateGenericTestExcelReport($shipmentId, $schemeType);
        } else {
            return false;
        }
    }

    public function getShipmentsByScheme($schemeType, $startDate, $endDate)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sQuery = $db->select()->from(['s' => 'shipment'], ['s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date',])
            ->where('s.scheme_type = ?', $schemeType)
            ->where('s.cancelled_at IS NULL')
            ->order('s.shipment_date DESC');
        if (isset($startDate) && !empty($startDate) && isset($endDate) && !empty($endDate)) {
            $sQuery = $sQuery->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($startDate));
            $sQuery = $sQuery->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($endDate));
        }
        return $db->fetchAll($sQuery);
    }

    public function getCorrectiveActionReportByShipmentId($shipmentId, $testType = '')
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(['s' => 'shipment'], ['s.shipment_code'])
            ->join(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id')
            ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', ['map_id'])
            ->join(['cam' => 'dts_shipment_corrective_action_map'], 'cam.shipment_map_id=sp.map_id', ['total_corrective' => new Zend_Db_Expr('count("cam.corrective_action_id")')])
            ->join(['ca' => 'r_dts_corrective_actions'], 'ca.action_id=cam.corrective_action_id', ['action_id', 'corrective_action'])
            ->where('s.shipment_id = ?', $shipmentId)
            ->group(['cam.corrective_action_id'])
            ->order(['total_corrective DESC']);
        if (isset($testType) && !empty($testType)) {
            $sQuery = $sQuery->where("JSON_EXTRACT(sp.attributes, '$.dts_test_panel_type') = ?", $testType);
        }
        return $dbAdapter->fetchAll($sQuery);
    }

    public function exportShipmentsReport($params)
    {

        $headings = [
            $this->translator->_('Scheme'),
            $this->translator->_('Shipment Code'),
            $this->translator->_('Sample Label'),
            $this->translator->_('Reference Result'),
            $this->translator->_('Total Positive Responses'),
            $this->translator->_('Total Negative Responses'),
            $this->translator->_('Total Indeterminate Responses'),
            $this->translator->_('Total Responses'),
            $this->translator->_('Total Valid Responses(Total - Excluded)'),
            $this->translator->_('Total Passed'),
        ];
        try {
            $excel = new Spreadsheet();

            $output = [];
            $sheet = $excel->getActiveSheet();

            $colNo = 0;
            $sheet->mergeCells('A1:I1');
            $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 1)
                ->setValueExplicit(html_entity_decode('Shipment Response Overview', ENT_QUOTES, 'UTF-8'));
            if (isset($params['shipmentName']) && trim($params['shipmentName']) != '') {
                $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 2)
                    ->setValueExplicit(html_entity_decode('Shipment', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell(Coordinate::stringFromColumnIndex(2) . 2)
                    ->setValueExplicit(html_entity_decode($params['shipmentName'], ENT_QUOTES, 'UTF-8'));
            }
            $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 3)
                ->setValueExplicit(html_entity_decode('Selected Date Range', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell(Coordinate::stringFromColumnIndex(2) . 3)
                ->setValueExplicit(html_entity_decode($params['dateRange'], ENT_QUOTES, 'UTF-8'));

            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 3, null, null)->getFont()->setBold(true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 2, null, null)->getFont()->setBold(true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 1, null, null)->getFont()->setBold(true);
            foreach ($headings as $value) {
                $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . 5)
                    ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . 5, null, null)->getFont()->setBold(true);
                $colNo++;
            }

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuerySession = new Zend_Session_Namespace('shipmentExportExcel');
            $rResult = $db->fetchAll($sQuerySession->shipmentExportQuery);
            foreach ($rResult as $aRow) {

                $row = [];
                $row[] = $aRow['scheme_name'];
                $row[] = $aRow['shipment_code'];
                $row[] = $aRow['sample_label'];
                $row[] = $aRow['response'];
                $row[] = $aRow['positive_responses'];
                $row[] = $aRow['negative_responses'];
                $row[] = $aRow['invalid_responses'];
                $row[] = $aRow['total_responses'];
                $row[] = $aRow['valid_responses'];
                $row[] = $aRow['total_passed'];
                $output[] = $row;
            }

            foreach ($output as $rowNo => $rowData) {
                $colNo = 0;
                foreach ($rowData as $value) {
                    if (!isset($value)) {
                        $value = '';
                    }
                    $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 6)
                        ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(150);
                        $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 6, null, null)->getAlignment()->setWrapText(true);
                    }
                    $colNo++;
                }
            }

            if (!file_exists($this->tempUploadDirectory) && !is_dir($this->tempUploadDirectory)) {
                mkdir($this->tempUploadDirectory);
            }

            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'shipment-response-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            $sQuerySession = new Zend_Session_Namespace('shipmentExportExcel');

            $sQuerySession->shipmentExportQuery = '';
            Pt_Commons_LoggerUtility::logError('Failed to generate shipment response report (Excel): ' . $exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);

            return '';
        }
    }

    public function exportShipmentsReportInPdf()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuerySession = new Zend_Session_Namespace('shipmentExportExcel');
        return $db->fetchAll($sQuerySession->shipmentExportQuery);
    }

    /** The VL Assay Distribution outcomes, in column order. */
    private const VL_ASSAY_OUTCOMES = ['pass', 'fail', 'excluded', 'nottested', 'none', 'pending'];

    /**
     * VL Assay Distribution: the outcome of every VL enrolment that matches
     * the filters, counted per assay. With groupBy=year, one row per year and
     * assay. The assay comes from the response, so enrolments without a known
     * assay (non-responders, mostly) share one row with an empty name. That
     * row comes last in its year.
     */
    public function getVlAssayDistribution(array $params): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $byYear = ($params['groupBy'] ?? '') === 'year';

        $columns = [
            'assay_id' => 'va.id',
            'assay' => new Zend_Db_Expr("IFNULL(TRIM(MAX(va.short_name)), '')"),
            'assay_name' => new Zend_Db_Expr("IFNULL(TRIM(MAX(va.name)), '')"),
            'enrolled' => new Zend_Db_Expr('COUNT(sp.map_id)'),
        ];
        foreach (self::VL_ASSAY_OUTCOMES as $outcome) {
            $columns[$outcome] = new Zend_Db_Expr('SUM((' . self::RESPONSE_OUTCOME_SQL . ") = '$outcome')");
        }
        if ($byYear) {
            $columns['year'] = new Zend_Db_Expr('YEAR(s.shipment_date)');
        }

        $sQuery = $db->select()
            ->from(['sp' => 'shipment_participant_map'], $columns)
            ->join(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', [])
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id', [])
            // Compared as text: old responses hold values such as "--Select--".
            ->joinLeft(['va' => 'r_vl_assay'], "CAST(va.id AS CHAR) = JSON_UNQUOTE(JSON_EXTRACT(sp.attributes, '$.vl_assay'))", [])
            ->group($byYear ? [new Zend_Db_Expr('YEAR(s.shipment_date)'), 'va.id'] : 'va.id')
            ->order(array_merge(
                $byYear ? [new Zend_Db_Expr('YEAR(s.shipment_date) DESC')] : [],
                [new Zend_Db_Expr('va.id IS NULL'), 'enrolled DESC', 'assay ASC']
            ));

        Application_Model_DbTable_Participants::applyResponseReportFilters($sQuery, ['scheme' => 'vl'] + $params);

        return array_map(function ($row) use ($byYear) {
            $out = [
                'year' => $byYear ? (int) $row['year'] : null,
                'assay' => (string) $row['assay'],
                'assayName' => (string) $row['assay_name'],
                'enrolled' => (int) $row['enrolled'],
            ];
            foreach (self::VL_ASSAY_OUTCOMES as $outcome) {
                $out[$outcome] = (int) $row[$outcome];
            }
            return $out;
        }, $db->fetchAll($sQuery));
    }

    /** VL Assay Distribution as Excel, one row per assay and a total row. */
    public function exportVlAssayDistribution(array $params): string
    {
        try {
            $rows = $this->getVlAssayDistribution($params);
            if (empty($rows)) {
                return '';
            }
            $byYear = ($params['groupBy'] ?? '') === 'year';
            $t = fn ($text) => html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $passRate = fn (array $r) => ($r['pass'] + $r['fail']) > 0 ? round($r['pass'] / ($r['pass'] + $r['fail']) * 100, 1) : null;

            $header = [
                $t($this->translator->_('Assay')),
                $t($this->translator->_('Assay name')),
                $t($this->translator->_('Enrolments')),
                $t($this->translator->_('Passed')),
                $t($this->translator->_('Failed')),
                $t($this->translator->_('Pass rate')) . ' (%)',
                $t($this->translator->_('Excluded')),
                $t($this->translator->_('Could not test')),
                $t($this->translator->_('Not responded')),
                $t($this->translator->_('Not evaluated')),
            ];
            if ($byYear) {
                array_unshift($header, $t($this->translator->_('Year')));
            }
            $notReported = $t($this->translator->_('No assay reported'));

            $excel = new Spreadsheet();
            $sheet = $excel->getActiveSheet();
            $sheet->setTitle(mb_substr($t($this->translator->_('Assays')), 0, 31));
            $sheet->fromArray($header, null, 'A1');
            $rowNo = 2;
            $total = array_fill_keys(array_merge(['enrolled'], self::VL_ASSAY_OUTCOMES), 0);
            foreach ($rows as $r) {
                $line = [
                    $r['assay'] !== '' ? $r['assay'] : $notReported,
                    $r['assayName'],
                    $r['enrolled'],
                    $r['pass'],
                    $r['fail'],
                    $passRate($r),
                    $r['excluded'],
                    $r['nottested'],
                    $r['none'],
                    $r['pending'],
                ];
                if ($byYear) {
                    array_unshift($line, $r['year']);
                }
                $sheet->fromArray($line, null, 'A' . $rowNo++, true);
                foreach ($total as $key => $value) {
                    $total[$key] = $value + $r[$key];
                }
            }
            $line = [
                $t($this->translator->_('Total')),
                '',
                $total['enrolled'],
                $total['pass'],
                $total['fail'],
                $passRate($total),
                $total['excluded'],
                $total['nottested'],
                $total['none'],
                $total['pending'],
            ];
            if ($byYear) {
                array_unshift($line, '');
            }
            $sheet->fromArray($line, null, 'A' . $rowNo, true);
            $sheet->getStyle('1:1')->getFont()->setBold(true);
            $sheet->getStyle($rowNo . ':' . $rowNo)->getFont()->setBold(true);
            $sheet->freezePane('A2');
            foreach ($sheet->getColumnIterator() as $column) {
                $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }

            if (!is_dir($this->tempUploadDirectory)) {
                mkdir($this->tempUploadDirectory);
            }
            $filename = 'vl-assay-distribution-' . date('d-M-Y-H-i-s') . '.xlsx';
            IOFactory::createWriter($excel, 'Xlsx')->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Throwable $exc) {
            Pt_Commons_LoggerUtility::logError('Failed to generate VL assay distribution report (Excel): ' . $exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            return '';
        }
    }

    /** VL sample score => label, in column order. Responses not scored yet count as pending. */
    private const VL_SAMPLE_SCORES = [
        'pass' => 'Acceptable',
        'warn' => 'Warning',
        'fail' => 'Unacceptable',
        'excluded' => 'Excluded',
        'pending' => 'Not scored',
    ];

    /**
     * VL Sample Analysis: the sample results of one shipment, counted per
     * sample and per assay. Only submitted responses count, so participants
     * who could not test never show.
     */
    public function getVlSampleAnalysis(int $shipmentId): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $score = "CASE WHEN rrv.calculated_score IN ('pass','warn','fail','excluded') THEN rrv.calculated_score ELSE 'pending' END";
        $counts = ['results' => new Zend_Db_Expr('COUNT(*)')];
        foreach (array_keys(self::VL_SAMPLE_SCORES) as $key) {
            $counts[$key] = new Zend_Db_Expr("SUM(($score) = '$key')");
        }

        $base = fn () => $db->select()
            ->from(['rrv' => 'response_result_vl'], $counts)
            ->join(['sp' => 'shipment_participant_map'], 'sp.map_id=rrv.shipment_map_id', [])
            ->join(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', [])
            ->where('sp.shipment_id = ?', $shipmentId)
            ->where("s.scheme_type = 'vl'")
            ->where('s.cancelled_at IS NULL')
            ->where("sp.response_status IN ('responded','late')")
            ->where("IFNULL(sp.is_pt_test_not_performed, 'no') <> 'yes'");

        $samples = $base()
            ->joinLeft(['ref' => 'reference_result_vl'], 'ref.shipment_id=sp.shipment_id AND ref.sample_id=rrv.sample_id', [
                'label' => new Zend_Db_Expr("IFNULL(MAX(ref.sample_label), '')"),
                'reference' => new Zend_Db_Expr("IFNULL(MAX(ref.reference_result), '')"),
                'control' => new Zend_Db_Expr('IFNULL(MAX(ref.control), 0)'),
            ])
            ->columns(['sample_id' => 'rrv.sample_id'])
            ->group('rrv.sample_id')
            ->order(new Zend_Db_Expr('MIN(ref.sample_id)'));

        $assays = $base()
            // Compared as text: old responses hold values such as "--Select--".
            ->joinLeft(['va' => 'r_vl_assay'], "CAST(va.id AS CHAR) = JSON_UNQUOTE(JSON_EXTRACT(sp.attributes, '$.vl_assay'))", [
                'assay' => new Zend_Db_Expr("IFNULL(TRIM(MAX(va.short_name)), '')"),
                'assayName' => new Zend_Db_Expr("IFNULL(TRIM(MAX(va.name)), '')"),
                'participants' => new Zend_Db_Expr('COUNT(DISTINCT sp.map_id)'),
            ])
            ->group('va.id')
            ->order([new Zend_Db_Expr('va.id IS NULL'), 'participants DESC', 'assay ASC']);

        $toInts = function (array $row) {
            foreach (array_merge(['results', 'participants', 'control'], array_keys(self::VL_SAMPLE_SCORES)) as $key) {
                if (isset($row[$key])) {
                    $row[$key] = (int) $row[$key];
                }
            }
            return $row;
        };

        return [
            'samples' => array_map($toInts, $db->fetchAll($samples)),
            'assays' => array_map($toInts, $db->fetchAll($assays)),
        ];
    }

    /** VL Sample Analysis as Excel: one sheet per sample, one per assay. */
    public function exportVlSampleAnalysis(int $shipmentId): string
    {
        try {
            $data = $this->getVlSampleAnalysis($shipmentId);
            if (empty($data['samples'])) {
                return '';
            }
            $shipmentCode = (string) Zend_Db_Table_Abstract::getDefaultAdapter()->fetchOne(
                'SELECT shipment_code FROM shipment WHERE shipment_id = ?',
                [$shipmentId]
            );
            $t = fn ($text) => html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $scoreLabels = array_map(fn ($label) => $t($this->translator->_($label)), self::VL_SAMPLE_SCORES);
            $passRate = fn (array $r) => ($r['pass'] + $r['warn'] + $r['fail']) > 0
                ? round(($r['pass'] + $r['warn']) / ($r['pass'] + $r['warn'] + $r['fail']) * 100, 1) : null;

            $excel = new Spreadsheet();
            $sheets = [
                [
                    $t($this->translator->_('Samples')),
                    [$t($this->translator->_('Sample')), $t($this->translator->_('Reference result'))],
                    array_map(fn ($r) => [$r['label'] !== '' ? $r['label'] : $r['sample_id'], $r['reference']], $data['samples']),
                    $data['samples'],
                ],
                [
                    $t($this->translator->_('Assays')),
                    [$t($this->translator->_('Assay')), $t($this->translator->_('Assay name')), $t($this->translator->_('Participants'))],
                    array_map(fn ($r) => [
                        $r['assay'] !== '' ? $r['assay'] : $t($this->translator->_('No assay reported')),
                        $r['assayName'],
                        $r['participants'],
                    ], $data['assays']),
                    $data['assays'],
                ],
            ];
            foreach ($sheets as $index => [$title, $lead, $leadRows, $rows]) {
                $sheet = $index === 0 ? $excel->getActiveSheet() : $excel->createSheet();
                $sheet->setTitle(mb_substr($title, 0, 31));
                $sheet->fromArray(array_merge(
                    $lead,
                    [$t($this->translator->_('Results'))],
                    array_values($scoreLabels),
                    [$t($this->translator->_('Pass rate')) . ' (%)']
                ), null, 'A1');
                $rowNo = 2;
                foreach ($rows as $i => $r) {
                    $line = array_merge($leadRows[$i], [$r['results']]);
                    foreach (array_keys(self::VL_SAMPLE_SCORES) as $key) {
                        $line[] = $r[$key];
                    }
                    $line[] = $passRate($r);
                    $sheet->fromArray($line, null, 'A' . $rowNo++, true);
                }
                $sheet->getStyle('1:1')->getFont()->setBold(true);
                $sheet->freezePane('A2');
                foreach ($sheet->getColumnIterator() as $column) {
                    $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
                }
            }
            $excel->setActiveSheetIndex(0);

            if (!is_dir($this->tempUploadDirectory)) {
                mkdir($this->tempUploadDirectory);
            }
            $code = preg_replace('/[^A-Za-z0-9_-]+/', '-', $shipmentCode);
            $filename = 'vl-sample-analysis-' . $code . '-' . date('d-M-Y-H-i-s') . '.xlsx';
            IOFactory::createWriter($excel, 'Xlsx')->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Throwable $exc) {
            Pt_Commons_LoggerUtility::logError('Failed to generate VL sample analysis report (Excel): ' . $exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            return '';
        }
    }

    // VL response distribution by assay for a given shipment (ranges already computed in reference_vl_calculation)
    public function getVlAssayResponseDistributions($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (empty($params['shipmentId'])) {
            return [];
        }

        $shipmentId = $params['shipmentId'];
        $shipment = $db->fetchRow($db->select()->from(['s' => 'shipment'], ['shipment_attributes'])->where('s.shipment_id = ?', $shipmentId));
        $shipmentAttributes = !empty($shipment['shipment_attributes']) ? Pt_Commons_JsonUtility::safeDecode($shipment['shipment_attributes']) : [];
        $methodOfEvaluation = $shipmentAttributes['methodOfEvaluation'] ?? 'standard';

        $assayQuery = $db->select()
            ->from(['vl' => 'r_vl_assay'], ['vl.id', 'vl.name', 'vl.short_name'])
            ->where("`status` like 'active'")
            ->order('vl.short_name ASC');
        $assays = $db->fetchAll($assayQuery);
        if (empty($assays)) {
            return [];
        }

        $assayLookup = [];
        foreach ($assays as $assay) {
            $assayLookup[$assay['id']] = $assay;
        }

        $valueQuery = $db->select()
            ->from(['rrv' => 'response_result_vl'], ['rrv.sample_id', 'rrv.reported_viral_load', 'rrv.is_result_invalid', 'rrv.z_score'])
            ->join(
                ['sp' => 'shipment_participant_map'],
                'sp.map_id = rrv.shipment_map_id',
                ['sp.attributes']
            )
            ->joinLeft(
                ['ref' => 'reference_result_vl'],
                'ref.shipment_id = sp.shipment_id AND ref.sample_id = rrv.sample_id',
                ['ref.sample_label', 'ref.reference_result']
            )
            ->joinLeft(
                ['rvc' => 'reference_vl_calculation'],
                'rvc.shipment_id = sp.shipment_id AND rvc.sample_id = rrv.sample_id AND rvc.vl_assay = sp.attributes->>"$.vl_assay"',
                [
                    'rvc.low_limit',
                    'rvc.high_limit',
                    'rvc.manual_low_limit',
                    'rvc.manual_high_limit',
                    'rvc.mean',
                    'rvc.sd',
                    'rvc.median',
                    'rvc.manual_mean',
                    'rvc.manual_sd',
                    'rvc.manual_median',
                    'rvc.use_range',
                    'rvc.no_of_responses',
                ]
            )
            ->where('sp.shipment_id = ?', $shipmentId)
            ->where('rrv.reported_viral_load IS NOT NULL')
            ->where("rrv.reported_viral_load != ''");

        $rows = $db->fetchAll($valueQuery);
        if (empty($rows)) {
            return [];
        }

        $fallbackRanges = [];
        $fallbackThreshold = null;
        if ($methodOfEvaluation === 'iso17043') {
            $fallbackThreshold = 18;
        } elseif ($methodOfEvaluation === 'standard') {
            $fallbackThreshold = 8;
        }

        if ($fallbackThreshold !== null) {
            $maxAssay = $db->fetchOne(
                $db->select()
                    ->from(['rvc' => 'reference_vl_calculation'], ['vl_assay'])
                    ->where('rvc.shipment_id = ?', $shipmentId)
                    ->group('rvc.vl_assay')
                    ->order('SUM(rvc.no_of_responses) DESC')
                    ->limit(1)
            );

            if (!empty($maxAssay)) {
                $fallbackRows = $db->fetchAll(
                    $db->select()
                        ->from(['rvc' => 'reference_vl_calculation'], [
                            'sample_id',
                            'low_limit',
                            'high_limit',
                            'manual_low_limit',
                            'manual_high_limit',
                            'mean',
                            'sd',
                            'manual_mean',
                            'manual_sd',
                            'use_range',
                        ])
                        ->where('rvc.shipment_id = ?', $shipmentId)
                        ->where('rvc.vl_assay = ?', $maxAssay)
                );

                foreach ($fallbackRows as $row) {
                    $fallbackRanges[$row['sample_id']] = $row;
                }
            }
        }

        $sampleLabels = [];
        $sampleResponseCounts = [];
        $sampleValues = [];
        $sampleLimits = [];
        $undetectable = [];
        foreach ($rows as $row) {
            $invalidValues = ['invalid', 'error'];
            if (!empty($row['is_result_invalid']) && in_array($row['is_result_invalid'], $invalidValues)) {
                continue;
            }

            $attributes = Pt_Commons_JsonUtility::safeDecode($row['attributes']);
            if (empty($attributes) || empty($attributes['vl_assay'])) {
                continue;
            }
            $assayId = $attributes['vl_assay'];
            if (!isset($assayLookup[$assayId])) {
                continue;
            }

            $value = (float) $row['reported_viral_load'];
            if (!is_numeric($value)) {
                continue;
            }

            if (!empty($row['sample_label'])) {
                $sampleLabels[$row['sample_id']] = $row['sample_label'];
            }
            if (isset($row['no_of_responses']) && $row['no_of_responses'] !== null) {
                if (!isset($sampleResponseCounts[$assayId])) {
                    $sampleResponseCounts[$assayId] = [];
                }
                $current = $sampleResponseCounts[$assayId][$row['sample_id']] ?? 0;
                $sampleResponseCounts[$assayId][$row['sample_id']] = max($current, (int) $row['no_of_responses']);
            }

            if (!isset($sampleValues[$assayId])) {
                $sampleValues[$assayId] = [];
            }
            if (!isset($sampleValues[$assayId][$row['sample_id']])) {
                $sampleValues[$assayId][$row['sample_id']] = [];
            }

            if ($value <= 0) {
                if (!isset($undetectable[$assayId])) {
                    $undetectable[$assayId] = 0;
                }
                $undetectable[$assayId]++;
                $value = 0.0;
            }

            $useRange = $row['use_range'] ?? 'calculated';
            $lowLimit = null;
            $highLimit = null;
            $meanValue = null;
            $medianValue = null;
            $sdValue = null;
            if ($useRange === 'manual') {
                $lowLimit = isset($row['manual_low_limit']) ? (float) $row['manual_low_limit'] : null;
                $highLimit = isset($row['manual_high_limit']) ? (float) $row['manual_high_limit'] : null;
                $meanValue = isset($row['manual_mean']) ? (float) $row['manual_mean'] : null;
                $medianValue = isset($row['manual_median']) ? (float) $row['manual_median'] : null;
                $sdValue = isset($row['manual_sd']) ? (float) $row['manual_sd'] : null;
            } else {
                $lowLimit = isset($row['low_limit']) ? (float) $row['low_limit'] : null;
                $highLimit = isset($row['high_limit']) ? (float) $row['high_limit'] : null;
                $meanValue = isset($row['mean']) ? (float) $row['mean'] : null;
                $medianValue = isset($row['median']) ? (float) $row['median'] : null;
                $sdValue = isset($row['sd']) ? (float) $row['sd'] : null;
            }

            if ($fallbackThreshold !== null && $useRange !== 'manual') {
                $responseCount = isset($row['no_of_responses']) ? (int) $row['no_of_responses'] : 0;
                if ($responseCount < $fallbackThreshold && isset($fallbackRanges[$row['sample_id']])) {
                    $fallback = $fallbackRanges[$row['sample_id']];
                    if (!empty($fallback['use_range']) && $fallback['use_range'] === 'manual') {
                        $lowLimit = isset($fallback['manual_low_limit']) ? (float) $fallback['manual_low_limit'] : $lowLimit;
                        $highLimit = isset($fallback['manual_high_limit']) ? (float) $fallback['manual_high_limit'] : $highLimit;
                        $meanValue = isset($fallback['manual_mean']) ? (float) $fallback['manual_mean'] : $meanValue;
                        $medianValue = isset($fallback['manual_median']) ? (float) $fallback['manual_median'] : $medianValue;
                        $sdValue = isset($fallback['manual_sd']) ? (float) $fallback['manual_sd'] : $sdValue;
                    } else {
                        $lowLimit = isset($fallback['low_limit']) ? (float) $fallback['low_limit'] : $lowLimit;
                        $highLimit = isset($fallback['high_limit']) ? (float) $fallback['high_limit'] : $highLimit;
                        $meanValue = isset($fallback['mean']) ? (float) $fallback['mean'] : $meanValue;
                        $medianValue = isset($fallback['median']) ? (float) $fallback['median'] : $medianValue;
                        $sdValue = isset($fallback['sd']) ? (float) $fallback['sd'] : $sdValue;
                    }
                }
            }

            if (!isset($sampleLimits[$assayId])) {
                $sampleLimits[$assayId] = [];
            }
            if (!isset($sampleLimits[$assayId][$row['sample_id']])) {
                $sampleLimits[$assayId][$row['sample_id']] = [
                    'low' => $lowLimit,
                    'high' => $highLimit,
                    'mean' => $meanValue,
                    'median' => $medianValue,
                    'sd' => $sdValue,
                ];
            }

            $sampleValues[$assayId][$row['sample_id']][] = [
                'value' => $value,
                'z' => isset($row['z_score']) ? (float) $row['z_score'] : null,
            ];
        }

        $distributions = [];
        foreach ($assayLookup as $assayId => $assay) {
            if (empty($sampleValues[$assayId])) {
                continue;
            }
            $samples = [];
            $totalCount = 0;
            $sampleList = $sampleValues[$assayId];
            uksort($sampleList, function ($a, $b) use ($sampleLabels) {
                $labelA = $sampleLabels[$a] ?? (string) $a;
                $labelB = $sampleLabels[$b] ?? (string) $b;
                return strnatcasecmp($labelA, $labelB);
            });
            foreach ($sampleList as $sampleId => $values) {
                if (empty($values)) {
                    continue;
                }
                $points = [];
                $warnPoints = [];
                $failPoints = [];
                $logPoints = [];
                $logWarnPoints = [];
                $logFailPoints = [];
                $index = 1;
                $limits = $sampleLimits[$assayId][$sampleId] ?? ['low' => null, 'high' => null];
                foreach ($values as $entry) {
                    $val = $entry['value'];
                    $z = isset($entry['z']) ? (float) $entry['z'] : null;
                    $jitter = (($index % 11) - 5) / 50;
                    if ($z === null) {
                        $points[] = ['x' => $index, 'y' => $val, 'z' => null];
                        $logPoints[] = ['x' => $val, 'y' => $jitter, 'z' => null];
                    } elseif (abs($z) < 2) {
                        $points[] = ['x' => $index, 'y' => $val, 'z' => $z];
                        $logPoints[] = ['x' => $val, 'y' => $jitter, 'z' => $z];
                    } elseif (abs($z) < 3) {
                        $warnPoints[] = ['x' => $index, 'y' => $val, 'z' => $z];
                        $logWarnPoints[] = ['x' => $val, 'y' => $jitter, 'z' => $z];
                    } else {
                        $failPoints[] = ['x' => $index, 'y' => $val, 'z' => $z];
                        $logFailPoints[] = ['x' => $val, 'y' => $jitter, 'z' => $z];
                    }
                    $index++;
                }
                $displayCount = count($values);
                $samples[] = [
                    'sampleId' => $sampleId,
                    'label' => $sampleLabels[$sampleId] ?? ('Sample ' . $sampleId),
                    'count' => $displayCount,
                    'points' => $points,
                    'warnPoints' => $warnPoints,
                    'failPoints' => $failPoints,
                    'logPoints' => $logPoints,
                    'logWarnPoints' => $logWarnPoints,
                    'logFailPoints' => $logFailPoints,
                    'lowLimit' => $limits['low'],
                    'highLimit' => $limits['high'],
                    'mean' => $limits['mean'],
                    'median' => $limits['median'],
                    'sd' => $limits['sd'],
                ];
                $totalCount = max($totalCount, $displayCount);
            }

            if (empty($samples)) {
                continue;
            }

            $distributions[] = [
                'assayId' => $assayId,
                'name' => $assay['short_name'] ?? $assay['name'],
                'fullName' => $assay['name'],
                'count' => $totalCount,
                'undetectable' => $undetectable[$assayId] ?? 0,
                'samples' => $samples,
            ];
        }

        usort($distributions, function ($a, $b) {
            if ($a['count'] === $b['count']) {
                return strcmp($a['name'], $b['name']);
            }
            return $b['count'] <=> $a['count'];
        });

        return $distributions;
    }

    public function getShipmentsByDate($schemeType, $startDate, $endDate, $notFinalized = true)
    {

        $resultArray = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sQuery = $db->select()->from(['s' => 'shipment'], ['s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date',])
            ->order('s.shipment_date DESC');
        if ($notFinalized == true) {
            $sQuery = $sQuery->where('s.status = ?', 'finalized');
        }
        if (!empty($startDate) && !empty($endDate)) {
            $sQuery = $sQuery->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($startDate));
            $sQuery = $sQuery->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($endDate));
        }
        if (!empty($schemeType)) {
            $sQuery = $sQuery->where('s.scheme_type IN (?)', (array) $schemeType);
        }
        $resultArray = $db->fetchAll($sQuery);
        return $resultArray;
    }

    /**
     * The shipments the Annual Performance Report covers: finalized, not
     * cancelled, shipped in the date range, of the picked schemes. Picked
     * shipments narrow the list further.
     */
    private function annualReportShipments(array $params): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()
            ->from(['s' => 'shipment'], ['s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date'])
            ->where('s.cancelled_at IS NULL')
            ->where("s.finalized_at IS NOT NULL OR s.status = 'finalized'")
            ->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($params['startDate']))
            ->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($params['endDate']))
            ->order(['s.scheme_type', 's.shipment_date', 's.shipment_id']);

        $schemes = array_filter((array) ($params['scheme'] ?? []), fn ($v) => $v !== '' && $v !== null);
        if (!empty($schemes)) {
            $query->where('s.scheme_type IN (?)', $schemes);
        }
        $shipmentIds = array_filter(array_map('intval', (array) ($params['shipmentId'] ?? [])));
        if (!empty($shipmentIds)) {
            $query->where('s.shipment_id IN (?)', $shipmentIds);
        }
        return $db->fetchAll($query);
    }

    /** The Shipment filter options of the Annual Performance Report. */
    public function getAnnualReportShipmentOptions(array $params): array
    {
        if (empty($params['startDate']) || empty($params['endDate'])) {
            return [];
        }
        unset($params['shipmentId']);
        return array_map(fn ($row) => [
            'id' => (int) $row['shipment_id'],
            'text' => $row['shipment_code'] . ' (' . Pt_Commons_DateUtility::humanReadableDateFormat($row['shipment_date'] ?? '') . ')',
            'scheme' => $row['scheme_type'],
        ], $this->annualReportShipments($params));
    }

    /**
     * Annual Performance Report: every evaluated response to the finalized
     * shipments in the range, as CSV (one row per response) or as the Excel
     * sheet reviewed before certificates (one row per participant).
     *
     * Only passes and real fails count. Older evaluations marked labs that did
     * not respond as failed, so a fail needs a submitted response.
     */
    public function getAnnualReport($params)
    {
        if (empty($params['startDate']) || empty($params['endDate'])) {
            return json_encode(['fileName' => '']);
        }

        $shipments = $this->annualReportShipments($params);
        if (empty($shipments)) {
            return json_encode(['fileName' => '']);
        }
        $shipmentCodeArray = [];
        foreach ($shipments as $shipment) {
            $shipmentCodeArray[$shipment['scheme_type']][] = $shipment['shipment_code'];
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $db->select()
            ->from(['spm' => 'shipment_participant_map'], [
                'spm.shipment_test_report_date',
                'spm.shipment_score',
                'spm.documentation_score',
                'spm.final_result',
                'spm.attributes',
                'spm.failure_reason',
                'finalResult' => new Zend_Db_Expr("CASE WHEN spm.final_result = 1 THEN 'PASS' ELSE 'FAIL' END"),
            ])
            ->join(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', ['s.shipment_code', 's.scheme_type', 's.number_of_samples', 's.shipment_date', 's.response_deadline'])
            ->join(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', ['sl.scheme_name'])
            ->join(['p' => 'participant'], 'p.participant_id=spm.participant_id', [
                'p.unique_identifier',
                'labName' => new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameExpr('p')),
                'p.institute_name',
                'p.department_name',
                'p.contact_name',
                'p.email',
                'p.additional_email',
                'p.address',
                'p.city',
                'p.district',
                'p.state',
            ])
            ->joinLeft(['c' => 'countries'], 'c.id=p.country', ['country_name' => 'c.iso_name'])
            ->where('spm.shipment_id IN (?)', array_column($shipments, 'shipment_id'))
            ->where(self::ANNUAL_RESULT_FILTER)
            ->order(['p.unique_identifier ASC', 's.scheme_type ASC', 's.shipment_date ASC']);

        $participants = [];
        foreach ($db->fetchAll($sQuery) as $row) {
            $uid = $row['unique_identifier'];
            if (!isset($participants[$uid])) {
                $participants[$uid] = [
                    'labName' => $row['labName'],
                    'institute_name' => $row['institute_name'] ?? '',
                    'department_name' => $row['department_name'] ?? '',
                    'contact_name' => $row['contact_name'] ?? '',
                    'address' => $row['address'] ?? '',
                    'city' => $row['city'] ?? '',
                    'district' => $row['district'] ?? '',
                    'state' => $row['state'] ?? '',
                    'country_name' => $row['country_name'] ?? '',
                    'email' => $row['email'] ?? '',
                    'additional_email' => $row['additional_email'] ?? '',
                    'schemes' => [],
                ];
            }
            $participants[$uid]['schemes'][$row['scheme_name']] = true;
            $participants[$uid][$row['scheme_type']][$row['shipment_code']] = [
                'scheme_name' => $row['scheme_name'],
                'score' => (float) $row['shipment_score'] + (float) $row['documentation_score'],
                'result' => $row['final_result'],
                'finalResult' => $row['finalResult'],
                'failure_reason' => $row['failure_reason'] ?? '',
                'number_of_samples' => $row['number_of_samples'] ?? '',
                'attributes' => Pt_Commons_JsonUtility::safeDecode($row['attributes']),
                'shipment_date' => $row['shipment_date'] ?? '',
                'response_deadline' => $row['response_deadline'] ?? '',
                'shipment_test_report_date' => $row['shipment_test_report_date'] ?? '',
            ];
        }
        foreach ($participants as &$participant) {
            $participant['scheme_name'] = implode(', ', array_keys($participant['schemes']));
        }
        unset($participant);

        if (empty($participants)) {
            return json_encode(['fileName' => '']);
        }

        $reportType = ($params['reportType'] ?? 'csv') === 'excel' ? 'excel' : 'csv';
        $filename = $reportType === 'excel'
            ? $this->generateAnnualReportExcel($shipmentCodeArray, $participants)
            : $this->generateAnnualReportCSV($shipmentCodeArray, $participants);

        return json_encode(['fileName' => $filename, 'reportType' => $reportType]);
    }

    /**
     * Shipment Response Report summary: one row per shipment, counted over the
     * participants that match the page's filters. The counts follow
     * Application_Service_Shipments::getShipmentParticipationStats().
     */
    public function getShipmentResponseReportReport($parameters)
    {
        $orderColumns = ['s.shipment_date', 'sl.scheme_name', 'enrolled', 'responded / enrolled', 'notResponded', 'passed / NULLIF(passed + failed, 0)', 'failed'];

        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                $colIdx = intval($parameters['iSortCol_' . $i]);
                if (($parameters['bSortable_' . $colIdx] ?? '') !== 'true' || !isset($orderColumns[$colIdx])) {
                    continue;
                }
                $sOrder[] = new Zend_Db_Expr($orderColumns[$colIdx] . ' ' . Pt_Commons_General::sanitizeSortDirection($parameters['sSortDir_' . $i]));
            }
        }

        $responded = "sp.response_status IN ('responded','late','nottested')";
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()
            ->from(['p' => 'participant'], [new Zend_Db_Expr('SQL_CALC_FOUND_ROWS s.shipment_id')])
            ->join(['sp' => 'shipment_participant_map'], 'p.participant_id=sp.participant_id', [
                'enrolled' => new Zend_Db_Expr('COUNT(DISTINCT sp.map_id)'),
                'responded' => new Zend_Db_Expr("SUM($responded)"),
                'notResponded' => new Zend_Db_Expr("SUM(NOT ($responded) OR sp.response_status IS NULL)"),
                'passed' => new Zend_Db_Expr('SUM(sp.final_result = 1)'),
                'failed' => new Zend_Db_Expr("SUM(sp.final_result = 2 AND sp.response_status IN ('responded','late'))"),
            ])
            ->join(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', [
                's.shipment_code',
                's.shipment_date',
            ])
            ->joinLeft(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['sl.scheme_name'])
            ->group('s.shipment_id');

        Application_Model_DbTable_Participants::applyResponseReportFilters($sQuery, $parameters);

        if (!empty($parameters['sSearch'])) {
            $like = Pt_Commons_General::sqlLikeContains($parameters['sSearch']);
            $sQuery->where("s.shipment_code LIKE $like OR sl.scheme_name LIKE $like");
        }

        $sQuery->order(!empty($sOrder) ? $sOrder : new Zend_Db_Expr('s.shipment_date DESC'));

        if (isset($parameters['iDisplayStart']) && ($parameters['iDisplayLength'] ?? '-1') != '-1') {
            $sQuery->limit((int) $parameters['iDisplayLength'], (int) $parameters['iDisplayStart']);
        }

        $rResult = $dbAdapter->fetchAll($sQuery);
        $iTotal = $dbAdapter->fetchOne('SELECT FOUND_ROWS()');

        $output = [
            'sEcho' => intval($parameters['sEcho'] ?? 0),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iTotal,
            'aaData' => [],
        ];

        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8', false);
        $translate = Zend_Registry::get('translate');
        $rate = function (int $n, int $of, string $barClass = '') use ($translate) {
            if ($of === 0) {
                return '<span class="cell-muted">' . $translate->_('Not evaluated yet') . '</span>';
            }
            $pct = round($n * 100 / $of);
            return '<span class="rate-figure">' . $pct . '%</span>'
                . '<span class="rate-bar ' . $barClass . '"><span style="width:' . $pct . '%"></span></span>'
                . '<span class="cell-sub">' . sprintf($translate->_('%s of %s'), number_format($n), number_format($of)) . '</span>';
        };

        foreach ($rResult as $aRow) {
            $sid = (int) $aRow['shipment_id'];
            $enrolled = (int) $aRow['enrolled'];
            $notResponded = (int) $aRow['notResponded'];
            $failed = (int) $aRow['failed'];
            $output['aaData'][] = [
                '<div class="shipment-cell">'
                    . '<a href="javascript:void(0);" class="toggle-stats-row" data-sid="' . base64_encode((string) $sid) . '" title="' . $esc($translate->_('Breakdown')) . '">'
                    . '<i class="icon-chevron-right" aria-hidden="true"></i></a>'
                    . '<div><a href="#" class="js-pick-shipment cell-main" data-sid="' . $sid . '" title="' . $esc($translate->_('List only the participants of this shipment')) . '">' . $esc($aRow['shipment_code']) . '</a>'
                    . '<span class="cell-sub">' . $esc(Pt_Commons_DateUtility::humanReadableDateFormat($aRow['shipment_date'] ?? '')) . '</span></div>'
                    . '</div>',
                $esc($aRow['scheme_name']),
                number_format($enrolled),
                $rate((int) $aRow['responded'], $enrolled),
                $notResponded > 0
                    ? '<a href="#" class="js-pick-shipment count-link is-bad" data-sid="' . $sid . '" data-response="noresponse" title="' . $esc($translate->_('List the participants who have not responded')) . '">' . number_format($notResponded) . '</a>'
                    : '0',
                $rate((int) $aRow['passed'], (int) $aRow['passed'] + $failed, 'is-pass'),
                $failed > 0
                    ? '<a href="#" class="js-pick-shipment count-link is-bad" data-sid="' . $sid . '" data-response="failed" title="' . $esc($translate->_('List the participants who failed')) . '">' . number_format($failed) . '</a>'
                    : '0',
            ];
        }

        echo json_encode($output);
    }

    /**
     * The shipments the Shipment Response Report can show: every shipment that is
     * not cancelled, of the given schemes, shipped in the date range.
     */
    public function getResponseReportShipments($schemes, ?string $startDate, ?string $endDate): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $db->select()
            ->from(['s' => 'shipment'], ['s.shipment_id', 's.shipment_code', 's.shipment_date'])
            ->where('s.cancelled_at IS NULL')
            ->order('s.shipment_date DESC');

        $schemes = array_filter((array) $schemes, fn ($v) => $v !== '' && $v !== null);
        if (!empty($schemes)) {
            $sQuery->where('s.scheme_type IN (?)', $schemes);
        }
        if (!empty($startDate)) {
            $sQuery->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($startDate));
        }
        if (!empty($endDate)) {
            $sQuery->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($endDate));
        }

        $dmId = Application_Service_Common::getScopedDmId();
        if ($dmId !== null) {
            $sQuery->where(
                's.shipment_id IN (?)',
                $db->select()->from(['sp' => 'shipment_participant_map'], ['sp.shipment_id'])
                    ->join(['pmm' => 'participant_manager_map'], 'pmm.participant_id=sp.participant_id', [])
                    ->where('pmm.dm_id = ?', $dmId)
            );
        }

        return array_map(fn ($row) => [
            'id' => (int) $row['shipment_id'],
            'text' => $row['shipment_code'] . ' (' . Pt_Commons_DateUtility::humanReadableDateFormat($row['shipment_date'] ?? '') . ')',
            'scheme' => $row['scheme_type'],
        ], $db->fetchAll($sQuery));
    }

    /**
     * Participant Performance Report: one row per participant, counted over the
     * shipments that match the filters. The counts follow
     * Application_Service_Shipments::getShipmentParticipationStats(). The
     * performance filter is a HAVING condition, so it reads the counts.
     */
    private function participantPerformanceSelect(array $params): Zend_Db_Select
    {
        $status = "IFNULL(sp.response_status, '')";
        $result = 'IFNULL(sp.final_result, 0)';
        $failed = "$result = 2 AND $status IN ('responded','late')";

        $sQuery = Zend_Db_Table_Abstract::getDefaultAdapter()->select()
            ->from(['p' => 'participant'], [
                'p.participant_id',
                'p.unique_identifier',
                'p.institute_name',
                'p.district',
                'p.state',
                'participant_name' => new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameExpr('p')),
            ])
            ->join(['sp' => 'shipment_participant_map'], 'sp.participant_id=p.participant_id', [
                'enrolled' => new Zend_Db_Expr('COUNT(sp.map_id)'),
                'responded' => new Zend_Db_Expr("SUM($status IN ('responded','late','nottested'))"),
                'not_responded' => new Zend_Db_Expr("SUM($status NOT IN ('responded','late','nottested'))"),
                'passed' => new Zend_Db_Expr("SUM($result = 1)"),
                'failed' => new Zend_Db_Expr("SUM($failed)"),
                'average_score' => new Zend_Db_Expr("AVG(CASE WHEN $result = 1 OR ($failed) THEN IFNULL(sp.shipment_score, 0) + IFNULL(sp.documentation_score, 0) END)"),
            ])
            ->join(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', [])
            ->joinLeft(['c' => 'countries'], 'c.id=p.country', ['c.iso_name'])
            ->group('p.participant_id');

        Application_Model_DbTable_Participants::applyResponseReportFilters($sQuery, $params);

        if (!empty($params['sSearch'])) {
            $searchColumns = [Application_Model_DbTable_Participants::participantNameExpr('p'), 'p.unique_identifier', 'p.institute_name', 'c.iso_name', 'p.state', 'p.district'];
            foreach (explode(' ', $params['sSearch']) as $search) {
                if ($search === '') {
                    continue;
                }
                $like = Pt_Commons_General::sqlLikeContains($search);
                $sQuery->where(implode(' OR ', array_map(fn ($col) => "$col LIKE $like", $searchColumns)));
            }
        }

        return $sQuery;
    }

    /**
     * The shipments of the given participants that match the filters, oldest
     * first, with the outcome of each.
     */
    private function participantPerformanceHistory(array $params, ?array $participantIds = null): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $db->select()
            ->from(['p' => 'participant'], [
                'p.participant_id',
                'p.unique_identifier',
                'participant_name' => new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameExpr('p')),
            ])
            ->join(['sp' => 'shipment_participant_map'], 'sp.participant_id=p.participant_id', [
                'sp.response_status',
                'sp.is_response_late',
                'sp.is_pt_test_not_performed',
                'sp.is_excluded',
                'sp.final_result',
                'sp.shipment_test_report_date',
                'score' => new Zend_Db_Expr('IFNULL(sp.shipment_score, 0) + IFNULL(sp.documentation_score, 0)'),
            ])
            ->join(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', ['s.shipment_id', 's.shipment_code', 's.shipment_date'])
            ->joinLeft(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['sl.scheme_name'])
            ->order(['participant_name ASC', 'p.participant_id ASC', 's.shipment_date ASC', 's.shipment_id ASC']);

        Application_Model_DbTable_Participants::applyResponseReportFilters($sQuery, $params);
        if ($participantIds !== null) {
            if (empty($participantIds)) {
                return [];
            }
            $sQuery->where('p.participant_id IN (?)', $participantIds);
        }

        $rows = $db->fetchAll($sQuery);
        foreach ($rows as &$row) {
            $row['outcome'] = self::performanceOutcome($row);
            $row['late'] = $row['response_status'] === 'late' || ($row['response_status'] === 'responded' && $row['is_response_late'] === 'yes');
        }
        return $rows;
    }

    /**
     * One outcome per enrolment: none (did not respond), nottested, excluded,
     * pass, fail or pending (responded, not evaluated yet).
     */
    private static function performanceOutcome(array $row): string
    {
        $status = (string) $row['response_status'];
        if (!in_array($status, ['responded', 'late', 'nottested'], true)) {
            return 'none';
        }
        if ($status === 'nottested' || $row['is_pt_test_not_performed'] === 'yes') {
            return 'nottested';
        }
        if ($row['is_excluded'] === 'yes' || (int) $row['final_result'] === 3) {
            return 'excluded';
        }
        return match ((int) $row['final_result']) {
            1 => 'pass',
            2 => 'fail',
            default => 'pending',
        };
    }

    /**
     * The rows of the Participant Performance table, in the DataTables
     * server-side format, plus the number of participants behind each
     * performance filter button.
     */
    public function getParticipantPerformance(array $params): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $passRate = 'passed / NULLIF(passed + failed, 0)';
        $orderColumns = [
            'participant_name',
            "CONCAT_WS(', ', p.district, p.state, c.iso_name)",
            'enrolled',
            $passRate,
            'failed',
            'average_score',
        ];

        $counts = $db->fetchRow(
            $db->select()->from(['t' => $this->participantPerformanceSelect($params)], [
                'all' => new Zend_Db_Expr('COUNT(*)'),
                'failed' => new Zend_Db_Expr('IFNULL(SUM(' . self::PERFORMANCE_FILTERS['failed'] . '), 0)'),
                'missed' => new Zend_Db_Expr('IFNULL(SUM(' . self::PERFORMANCE_FILTERS['missed'] . '), 0)'),
                'clean' => new Zend_Db_Expr('IFNULL(SUM(' . self::PERFORMANCE_FILTERS['clean'] . '), 0)'),
            ])
        );
        $counts = array_map('intval', $counts ?: ['all' => 0, 'failed' => 0, 'missed' => 0, 'clean' => 0]);

        $filter = $params['performance'] ?? '';
        $sQuery = $this->participantPerformanceSelect($params);
        if (isset(self::PERFORMANCE_FILTERS[$filter])) {
            $sQuery->having(self::PERFORMANCE_FILTERS[$filter]);
        } else {
            $filter = 'all';
        }

        $sOrder = [];
        if (isset($params['iSortCol_0'])) {
            for ($i = 0; $i < intval($params['iSortingCols']); $i++) {
                $colIdx = intval($params['iSortCol_' . $i]);
                if (($params['bSortable_' . $colIdx] ?? '') !== 'true' || !isset($orderColumns[$colIdx])) {
                    continue;
                }
                $direction = Pt_Commons_General::sanitizeSortDirection($params['sSortDir_' . $i]);
                // Participants with nothing to rate go last either way.
                if (in_array($orderColumns[$colIdx], [$passRate, 'average_score'], true)) {
                    $sOrder[] = new Zend_Db_Expr("({$orderColumns[$colIdx]}) IS NULL");
                }
                $sOrder[] = new Zend_Db_Expr($orderColumns[$colIdx] . ' ' . $direction);
            }
        }
        $sOrder[] = new Zend_Db_Expr('participant_name ASC');
        $sQuery->order($sOrder);

        if (isset($params['iDisplayStart']) && ($params['iDisplayLength'] ?? '-1') != '-1') {
            $sQuery->limit((int) $params['iDisplayLength'], (int) $params['iDisplayStart']);
        }

        $rows = $db->fetchAll($sQuery);
        $history = [];
        $columns = [];
        foreach ($this->participantPerformanceHistory($params, array_column($rows, 'participant_id')) as $h) {
            $history[$h['participant_id']][$h['shipment_id']] = $h;
            $columns[$h['shipment_id']] = $h;
        }

        $output = [
            'sEcho' => intval($params['sEcho'] ?? 0),
            'iTotalRecords' => $counts['all'],
            'iTotalDisplayRecords' => $counts[$filter],
            'counts' => $counts,
            'aaData' => [],
        ];

        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8', false);
        $translate = Zend_Registry::get('translate');
        $date = fn ($d) => Pt_Commons_DateUtility::humanReadableDateFormat($d ?? '');
        $outcomes = array_map(fn ($o) => [$translate->_($o[0]), $o[1]], self::PERFORMANCE_OUTCOMES);
        $late = $translate->_('Late');
        $maxChips = 12;

        // The strip has one slot per shipment on this page, oldest first, so a
        // slot means the same shipment in every row. Only the latest fit.
        uasort($columns, fn ($a, $b) => [$a['shipment_date'], $a['shipment_id']] <=> [$b['shipment_date'], $b['shipment_id']]);
        $hidden = count($columns) - $maxChips;
        $columns = array_slice($columns, max(0, $hidden), null, true);
        $earlierLabel = $translate->_('Earlier shipments not shown: %s');
        // When older shipments are cut, every row keeps a first slot that counts
        // its own, so a row whose results are all older still shows them.
        $axis = $hidden > 0 ? '<span class="perf-axis-label"></span>' : '';
        foreach ($columns as $c) {
            $axis .= '<span class="perf-axis-label" title="' . $esc(implode(' · ', array_filter([$c['shipment_code'], $c['scheme_name'], $date($c['shipment_date'])]))) . '">'
                . $esc($c['shipment_code']) . '</span>';
        }
        $output['axis'] = '<div class="perf-chips perf-axis">' . $axis . '</div>'
            . ($hidden > 0 ? '<span class="perf-more">' . $esc(sprintf($earlierLabel, $hidden)) . '</span>' : '');
        $notEnrolled = $translate->_('Not enrolled');

        foreach ($rows as $r) {
            $evaluated = (int) $r['passed'] + (int) $r['failed'];
            $failed = (int) $r['failed'];
            $shipments = $history[$r['participant_id']] ?? [];

            // Some participants have no name, so the first of ID and institute stands in.
            $sub = array_values(array_filter([$r['unique_identifier'], $r['institute_name']]));
            $name = trim((string) $r['participant_name']) !== '' ? $r['participant_name'] : array_shift($sub);
            $participant = '<span class="cell-main">' . $esc($name) . '</span>';
            if (!empty($sub)) {
                $participant .= '<span class="cell-sub">' . $esc(implode(' · ', $sub)) . '</span>';
            }

            $passRateCell = '<span class="cell-muted">' . $esc($translate->_('Not evaluated yet')) . '</span>';
            if ($evaluated > 0) {
                $pct = round((int) $r['passed'] * 100 / $evaluated);
                $passRateCell = '<span class="rate-figure">' . $pct . '%</span>'
                    . '<span class="rate-bar is-pass"><span style="width:' . $pct . '%"></span></span>'
                    . '<span class="cell-sub">' . $esc(sprintf($translate->_('%s of %s'), number_format((int) $r['passed']), number_format($evaluated))) . '</span>';
            }

            $chips = '';
            if ($hidden > 0) {
                $earlier = count(array_diff_key($shipments, $columns));
                $chips .= $earlier > 0
                    ? '<span class="perf-chip is-more" title="' . $esc(sprintf($earlierLabel, $earlier)) . '">+' . $earlier . '</span>'
                    : '<span class="perf-chip is-spacer"></span>';
            }
            foreach ($columns as $shipmentId => $c) {
                $h = $shipments[$shipmentId] ?? null;
                if ($h === null) {
                    $chips .= '<span class="perf-chip is-gap" title="' . $esc($c['shipment_code'] . ' · ' . $notEnrolled) . '"></span>';
                    continue;
                }
                [$label] = $outcomes[$h['outcome']];
                $scored = in_array($h['outcome'], ['pass', 'fail'], true);
                $title = implode(' · ', array_filter([
                    $h['shipment_code'],
                    $date($h['shipment_date']),
                    $label,
                    $h['late'] ? $late : '',
                    $scored ? sprintf($translate->_('Score %s'), round((float) $h['score'])) : '',
                ]));
                $chips .= '<span class="perf-chip is-' . $h['outcome'] . '" title="' . $esc($title) . '">'
                    . ($scored ? round((float) $h['score']) : '') . '</span>';
            }

            $detail = '<table class="table perf-history"><thead><tr>'
                . '<th>' . $esc($translate->_('Shipment')) . '</th>'
                . '<th>' . $esc($translate->_('Scheme')) . '</th>'
                . '<th>' . $esc($translate->_('Responded On')) . '</th>'
                . '<th>' . $esc($translate->_('Score')) . '</th>'
                . '<th>' . $esc($translate->_('Result')) . '</th>'
                . '</tr></thead><tbody>';
            foreach (array_reverse($shipments) as $h) {
                [$label, $class] = $outcomes[$h['outcome']];
                $detail .= '<tr>'
                    . '<td><span class="cell-main">' . $esc($h['shipment_code']) . '</span><span class="cell-sub">' . $esc($date($h['shipment_date'])) . '</span></td>'
                    . '<td>' . $esc($h['scheme_name']) . '</td>'
                    . '<td>' . $esc($date($h['shipment_test_report_date']))
                    . ($h['late'] ? ' <span class="resp-pill is-warn">' . $esc($late) . '</span>' : '') . '</td>'
                    . '<td>' . (in_array($h['outcome'], ['pass', 'fail'], true) ? round((float) $h['score']) : '') . '</td>'
                    . '<td><span class="resp-pill ' . $class . '">' . $esc($label) . '</span></td>'
                    . '</tr>';
            }
            $detail .= '</tbody></table>';

            $output['aaData'][] = [
                '<div class="perf-participant"><span class="perf-toggle" aria-hidden="true"><i class="icon-chevron-right"></i></span><div>' . $participant . '</div></div>',
                $esc(implode(', ', array_filter([$r['district'], $r['state'], $r['iso_name']]))),
                number_format((int) $r['enrolled'])
                    . '<span class="cell-sub">' . $esc(sprintf($translate->_('%s responded'), number_format((int) $r['responded']))) . '</span>',
                $passRateCell,
                $failed > 0 ? '<span class="perf-failed">' . number_format($failed) . '</span>' : '0',
                $r['average_score'] !== null ? (string) round((float) $r['average_score']) : '<span class="cell-muted">' . $esc($translate->_('No score')) . '</span>',
                '<div class="perf-chips">' . $chips . '</div>',
                $detail,
            ];
        }

        return $output;
    }

    /**
     * Participant Performance Report as Excel: one sheet with a row per
     * participant, one with a row per participant and shipment.
     */
    public function exportParticipantPerformance(array $params): string
    {
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $filter = $params['performance'] ?? '';
            $sQuery = $this->participantPerformanceSelect($params)
                ->order(['failed DESC', 'participant_name ASC']);
            if (isset(self::PERFORMANCE_FILTERS[$filter])) {
                $sQuery->having(self::PERFORMANCE_FILTERS[$filter]);
            }
            $rows = $db->fetchAll($sQuery);
            if (empty($rows)) {
                return '';
            }

            $t = fn ($text) => html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $outcomes = array_map(fn ($o) => $t($this->translator->_($o[0])), self::PERFORMANCE_OUTCOMES);

            $excel = new Spreadsheet();
            $sheet = $excel->getActiveSheet();
            $sheet->setTitle(mb_substr($t($this->translator->_('Participants')), 0, 31));
            $sheet->fromArray([
                $t($this->translator->_('Participant')),
                $t($this->translator->_('Participant ID')),
                $t($this->translator->_('Institute Name')),
                $t($this->translator->_('District')),
                $t($this->translator->_('Province')),
                $t($this->translator->_('Country')),
                $t($this->translator->_('Shipments')),
                $t($this->translator->_('Responded')),
                $t($this->translator->_('Not responded')),
                $t($this->translator->_('Passed')),
                $t($this->translator->_('Failed')),
                $t($this->translator->_('Pass rate')) . ' (%)',
                $t($this->translator->_('Average score')),
            ], null, 'A1');
            $rowNo = 2;
            foreach ($rows as $r) {
                $evaluated = (int) $r['passed'] + (int) $r['failed'];
                $sheet->fromArray([
                    $r['participant_name'],
                    $r['unique_identifier'],
                    $r['institute_name'],
                    $r['district'],
                    $r['state'],
                    $r['iso_name'],
                    (int) $r['enrolled'],
                    (int) $r['responded'],
                    (int) $r['not_responded'],
                    (int) $r['passed'],
                    (int) $r['failed'],
                    $evaluated > 0 ? round((int) $r['passed'] / $evaluated * 100, 1) : null,
                    $r['average_score'] !== null ? round((float) $r['average_score'], 1) : null,
                ], null, 'A' . $rowNo++);
            }

            $shipmentSheet = $excel->createSheet();
            $shipmentSheet->setTitle(mb_substr($t($this->translator->_('By shipment')), 0, 31));
            $shipmentSheet->fromArray([
                $t($this->translator->_('Participant')),
                $t($this->translator->_('Participant ID')),
                $t($this->translator->_('Shipment Code')),
                $t($this->translator->_('Shipment Date')),
                $t($this->translator->_('Scheme')),
                $t($this->translator->_('Responded On')),
                $t($this->translator->_('Late')),
                $t($this->translator->_('Score')),
                $t($this->translator->_('Result')),
            ], null, 'A1');
            $rowNo = 2;
            $included = array_flip(array_column($rows, 'participant_id'));
            foreach ($this->participantPerformanceHistory($params) as $h) {
                if (!isset($included[$h['participant_id']])) {
                    continue;
                }
                $shipmentSheet->fromArray([
                    $h['participant_name'],
                    $h['unique_identifier'],
                    $h['shipment_code'],
                    Pt_Commons_DateUtility::humanReadableDateFormat($h['shipment_date'] ?? ''),
                    $h['scheme_name'],
                    Pt_Commons_DateUtility::humanReadableDateFormat($h['shipment_test_report_date'] ?? ''),
                    $h['late'] ? $t($this->translator->_('Yes')) : '',
                    in_array($h['outcome'], ['pass', 'fail'], true) ? round((float) $h['score'], 1) : null,
                    $outcomes[$h['outcome']],
                ], null, 'A' . $rowNo++);
            }

            foreach ([$sheet, $shipmentSheet] as $ws) {
                $ws->getStyle('1:1')->getFont()->setBold(true);
                $ws->freezePane('A2');
                foreach ($ws->getColumnIterator() as $column) {
                    $ws->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
                }
            }
            $excel->setActiveSheetIndex(0);

            if (!is_dir($this->tempUploadDirectory)) {
                mkdir($this->tempUploadDirectory);
            }
            $filename = 'participant-performance-' . date('d-M-Y-H-i-s') . '.xlsx';
            IOFactory::createWriter($excel, 'Xlsx')->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Throwable $exc) {
            Pt_Commons_LoggerUtility::logError('Failed to generate participant performance report (Excel): ' . $exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            return '';
        }
    }

    /**
     * Corrective Actions Report: the DTS responses that match the filters.
     * Only submitted responses that are not excluded count. Rounds evaluated
     * before the non-responder guard gave corrective actions to participants
     * who never responded, and those would swamp the counts.
     */
    private function correctiveActionResponses(array $params): Zend_Db_Select
    {
        $sQuery = Zend_Db_Table_Abstract::getDefaultAdapter()->select()
            ->from(['p' => 'participant'], [])
            ->join(['sp' => 'shipment_participant_map'], 'sp.participant_id=p.participant_id', [])
            ->join(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', [])
            ->where("sp.response_status IN ('responded','late')")
            ->where("IFNULL(sp.is_excluded, 'no') != 'yes'");

        Application_Model_DbTable_Participants::applyResponseReportFilters($sQuery, ['scheme' => 'dts'] + $params);
        return $sQuery;
    }

    /**
     * Corrective Actions Report summary: the totals over the matching
     * responses, and one row per corrective action, most frequent first.
     */
    public function getCorrectiveActionSummary(array $params): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $evaluated = 'IFNULL(sp.final_result, 0) IN (1, 2)';

        $totals = $db->fetchRow(
            $this->correctiveActionResponses($params)->columns([
                'shipments' => new Zend_Db_Expr('COUNT(DISTINCT s.shipment_id)'),
                'responses' => new Zend_Db_Expr('COUNT(DISTINCT sp.map_id)'),
                'failed' => new Zend_Db_Expr('COUNT(DISTINCT CASE WHEN sp.final_result = 2 THEN sp.map_id END)'),
                'average_score' => new Zend_Db_Expr("AVG(CASE WHEN $evaluated THEN IFNULL(sp.shipment_score, 0) + IFNULL(sp.documentation_score, 0) END)"),
            ])
        );

        // The map table has no index on shipment_map_id, so a join (which
        // MySQL hashes) is far quicker here than a correlated EXISTS.
        $withAction = (int) $db->fetchOne(
            $this->correctiveActionResponses($params)
                ->join(['cam' => 'dts_shipment_corrective_action_map'], 'cam.shipment_map_id=sp.map_id', [])
                ->columns(['with_action' => new Zend_Db_Expr('COUNT(DISTINCT sp.map_id)')])
        );

        $actions = $db->fetchAll(
            $this->correctiveActionResponses($params)
                ->join(['cam' => 'dts_shipment_corrective_action_map'], 'cam.shipment_map_id=sp.map_id', [])
                ->join(['ca' => 'r_dts_corrective_actions'], 'ca.action_id=cam.corrective_action_id', ['ca.action_id', 'ca.corrective_action'])
                ->columns([
                    'responses' => new Zend_Db_Expr('COUNT(DISTINCT sp.map_id)'),
                    'participants' => new Zend_Db_Expr('COUNT(DISTINCT p.participant_id)'),
                    'failed' => new Zend_Db_Expr('COUNT(DISTINCT CASE WHEN sp.final_result = 2 THEN sp.map_id END)'),
                ])
                ->group('ca.action_id')
                ->order(['responses DESC', 'ca.action_id ASC'])
        );

        foreach ($actions as &$action) {
            $action['corrective_action'] = $this->translator->_(trim((string) $action['corrective_action']));
            foreach (['responses', 'participants', 'failed'] as $key) {
                $action[$key] = (int) $action[$key];
            }
        }

        return [
            'shipments' => (int) ($totals['shipments'] ?? 0),
            'responses' => (int) ($totals['responses'] ?? 0),
            'withAction' => $withAction,
            'failed' => (int) ($totals['failed'] ?? 0),
            'averageScore' => isset($totals['average_score']) ? round((float) $totals['average_score'], 1) : null,
            'actions' => $actions,
        ];
    }

    /**
     * The responses that received corrective actions, newest shipment first.
     * With $actionId, only the responses that received that action.
     */
    public function getCorrectiveActionResponses(array $params, ?int $actionId = null, ?int $limit = null): array
    {
        $sQuery = $this->correctiveActionResponses($params)
            ->columns([
                'p.participant_id',
                'p.unique_identifier',
                'p.institute_name',
                'p.district',
                'p.state',
                'participant_name' => new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameExpr('p')),
                's.shipment_code',
                's.shipment_date',
                'sp.final_result',
                'sp.shipment_test_report_date',
                'score' => new Zend_Db_Expr('IFNULL(sp.shipment_score, 0) + IFNULL(sp.documentation_score, 0)'),
            ])
            ->join(['cam' => 'dts_shipment_corrective_action_map'], 'cam.shipment_map_id=sp.map_id', [])
            ->join(['ca' => 'r_dts_corrective_actions'], 'ca.action_id=cam.corrective_action_id', ['ca.action_id', 'ca.corrective_action'])
            ->joinLeft(['c' => 'countries'], 'c.id=p.country', ['c.iso_name'])
            ->order(['s.shipment_date DESC', 'participant_name ASC', 'ca.action_id ASC']);

        if ($actionId !== null) {
            $sQuery->where('ca.action_id = ?', $actionId);
        }
        if ($limit !== null) {
            $sQuery->limit($limit);
        }

        $rows = Zend_Db_Table_Abstract::getDefaultAdapter()->fetchAll($sQuery);
        foreach ($rows as &$row) {
            $row['corrective_action'] = $this->translator->_(trim((string) $row['corrective_action']));
        }
        return $rows;
    }

    /**
     * The drawer under a corrective action: the first $limit responses that
     * received it, as table rows.
     */
    public function getCorrectiveActionDrawer(array $params, int $actionId, int $limit = 100): string
    {
        $rows = $this->getCorrectiveActionResponses($params, $actionId, $limit + 1);
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8', false);
        $date = fn ($d) => Pt_Commons_DateUtility::humanReadableDateFormat($d ?? '');
        $results = [1 => ['Pass', 'is-ok'], 2 => ['Fail', 'is-bad']];

        $html = '<table class="table ca-responses"><thead><tr>'
            . '<th>' . $esc($this->translator->_('Participant')) . '</th>'
            . '<th>' . $esc($this->translator->_('Location')) . '</th>'
            . '<th>' . $esc($this->translator->_('Shipment')) . '</th>'
            . '<th>' . $esc($this->translator->_('Score')) . '</th>'
            . '<th>' . $esc($this->translator->_('Result')) . '</th>'
            . '</tr></thead><tbody>';
        foreach (array_slice($rows, 0, $limit) as $r) {
            $result = $results[(int) $r['final_result']] ?? ['Not evaluated', 'is-muted'];
            $sub = array_values(array_filter([$r['unique_identifier'], $r['institute_name']]));
            $name = trim((string) $r['participant_name']) !== '' ? $r['participant_name'] : array_shift($sub);
            $html .= '<tr>'
                . '<td><span class="cell-main">' . $esc($name) . '</span>'
                . (!empty($sub) ? '<span class="cell-sub">' . $esc(implode(' · ', $sub)) . '</span>' : '') . '</td>'
                . '<td>' . $esc(implode(', ', array_filter([$r['district'], $r['state'], $r['iso_name']]))) . '</td>'
                . '<td><span class="cell-main">' . $esc($r['shipment_code']) . '</span><span class="cell-sub">' . $esc($date($r['shipment_date'])) . '</span></td>'
                . '<td>' . (isset($results[(int) $r['final_result']]) ? round((float) $r['score']) : '') . '</td>'
                . '<td><span class="resp-pill ' . $result[1] . '">' . $esc($this->translator->_($result[0])) . '</span></td>'
                . '</tr>';
        }
        $html .= '</tbody></table>';
        if (count($rows) > $limit) {
            $html .= '<p class="ca-more">' . $esc(sprintf($this->translator->_('The first %s responses. Download Excel for the full list.'), $limit)) . '</p>';
        }
        return $html;
    }

    /**
     * Corrective Actions Report as Excel: one sheet with a row per corrective
     * action, one with a row per response and corrective action.
     */
    public function exportCorrectiveActions(array $params): string
    {
        try {
            $summary = $this->getCorrectiveActionSummary($params);
            if (empty($summary['actions'])) {
                return '';
            }
            $t = fn ($text) => html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $decode = fn ($text) => html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8');
            $results = [1 => $t($this->translator->_('Pass')), 2 => $t($this->translator->_('Fail'))];

            $excel = new Spreadsheet();
            $sheet = $excel->getActiveSheet();
            $sheet->setTitle(mb_substr($t($this->translator->_('Corrective actions')), 0, 31));
            $sheet->fromArray([$t($this->translator->_('Corrective Action')), $t($this->translator->_('Responses')), $t($this->translator->_('Share of responses')) . ' (%)', $t($this->translator->_('Participants')), $t($this->translator->_('Failed'))], null, 'A1');
            $rowNo = 2;
            foreach ($summary['actions'] as $a) {
                $sheet->fromArray([
                    $decode($a['corrective_action']),
                    $a['responses'],
                    $summary['responses'] > 0 ? round($a['responses'] / $summary['responses'] * 100, 1) : null,
                    $a['participants'],
                    $a['failed'],
                ], null, 'A' . $rowNo++);
            }

            $responseSheet = $excel->createSheet();
            $responseSheet->setTitle(mb_substr($t($this->translator->_('Responses')), 0, 31));
            $responseSheet->fromArray([
                $t($this->translator->_('Participant')),
                $t($this->translator->_('Participant ID')),
                $t($this->translator->_('Institute Name')),
                $t($this->translator->_('District')),
                $t($this->translator->_('Province')),
                $t($this->translator->_('Country')),
                $t($this->translator->_('Shipment Code')),
                $t($this->translator->_('Shipment Date')),
                $t($this->translator->_('Score')),
                $t($this->translator->_('Result')),
                $t($this->translator->_('Corrective Action')),
            ], null, 'A1');
            $rowNo = 2;
            foreach ($this->getCorrectiveActionResponses($params) as $r) {
                $evaluated = isset($results[(int) $r['final_result']]);
                $responseSheet->fromArray([
                    $r['participant_name'],
                    $r['unique_identifier'],
                    $r['institute_name'],
                    $r['district'],
                    $r['state'],
                    $r['iso_name'],
                    $r['shipment_code'],
                    Pt_Commons_DateUtility::humanReadableDateFormat($r['shipment_date'] ?? ''),
                    $evaluated ? round((float) $r['score'], 1) : null,
                    $results[(int) $r['final_result']] ?? $t($this->translator->_('Not evaluated')),
                    $decode($r['corrective_action']),
                ], null, 'A' . $rowNo++);
            }

            foreach ([$sheet, $responseSheet] as $ws) {
                $ws->getStyle('1:1')->getFont()->setBold(true);
                $ws->freezePane('A2');
                foreach ($ws->getColumnIterator() as $column) {
                    $ws->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
                }
            }
            // The corrective action text is long, so it wraps instead.
            $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(80);
            $sheet->getStyle('A:A')->getAlignment()->setWrapText(true);
            $responseSheet->getColumnDimension('K')->setAutoSize(false)->setWidth(80);
            $excel->setActiveSheetIndex(0);

            if (!is_dir($this->tempUploadDirectory)) {
                mkdir($this->tempUploadDirectory);
            }
            $filename = 'corrective-actions-' . date('d-M-Y-H-i-s') . '.xlsx';
            IOFactory::createWriter($excel, 'Xlsx')->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Throwable $exc) {
            Pt_Commons_LoggerUtility::logError('Failed to generate corrective actions report (Excel): ' . $exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            return '';
        }
    }

    /**
     * TB Participants per Country: one row per country, counted over the TB
     * shipments that match the filters. The counts follow
     * Application_Service_Shipments::getShipmentParticipationStats().
     * Participants without a country come last, with an empty name.
     */
    public function getTbParticipantsPerCountry(array $params): array
    {
        $status = "IFNULL(sp.response_status, '')";
        $sQuery = Zend_Db_Table_Abstract::getDefaultAdapter()->select()
            ->from(['p' => 'participant'], [
                'country_id' => 'p.country',
                'participants' => new Zend_Db_Expr('COUNT(DISTINCT p.participant_id)'),
            ])
            ->join(['sp' => 'shipment_participant_map'], 'sp.participant_id=p.participant_id', [
                'enrolled' => new Zend_Db_Expr('COUNT(sp.map_id)'),
                'responded' => new Zend_Db_Expr("SUM($status IN ('responded','late','nottested'))"),
                'passed' => new Zend_Db_Expr('SUM(IFNULL(sp.final_result, 0) = 1)'),
                'failed' => new Zend_Db_Expr("SUM(IFNULL(sp.final_result, 0) = 2 AND $status IN ('responded','late'))"),
            ])
            ->join(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', [])
            ->joinLeft(['c' => 'countries'], 'c.id=p.country', ['country_name' => new Zend_Db_Expr("IFNULL(MAX(c.iso_name), '')")])
            ->group('p.country')
            ->order([new Zend_Db_Expr('MAX(c.iso_name) IS NULL'), 'participants DESC', 'country_name ASC']);

        Application_Model_DbTable_Participants::applyResponseReportFilters($sQuery, ['scheme' => 'tb'] + $params);

        return array_map(fn ($row) => [
            'country' => (string) $row['country_name'],
            'participants' => (int) $row['participants'],
            'enrolled' => (int) $row['enrolled'],
            'responded' => (int) $row['responded'],
            'passed' => (int) $row['passed'],
            'failed' => (int) $row['failed'],
        ], Zend_Db_Table_Abstract::getDefaultAdapter()->fetchAll($sQuery));
    }

    /** TB Participants per Country as Excel, one row per country. */
    public function exportTbParticipantsPerCountry(array $params): string
    {
        try {
            $rows = $this->getTbParticipantsPerCountry($params);
            if (empty($rows)) {
                return '';
            }
            $t = fn ($text) => html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $pct = fn (int $part, int $whole) => $whole > 0 ? round($part / $whole * 100, 1) : null;

            $excel = new Spreadsheet();
            $sheet = $excel->getActiveSheet();
            $sheet->setTitle(mb_substr($t($this->translator->_('Countries')), 0, 31));
            $sheet->fromArray([
                $t($this->translator->_('Country')),
                $t($this->translator->_('Participants')),
                $t($this->translator->_('Enrolled')),
                $t($this->translator->_('Responded')),
                $t($this->translator->_('Response rate')) . ' (%)',
                $t($this->translator->_('Passed')),
                $t($this->translator->_('Failed')),
                $t($this->translator->_('Pass rate')) . ' (%)',
            ], null, 'A1');
            $rowNo = 2;
            foreach ($rows as $r) {
                $sheet->fromArray([
                    $r['country'] !== '' ? $r['country'] : $t($this->translator->_('Not set')),
                    $r['participants'],
                    $r['enrolled'],
                    $r['responded'],
                    $pct($r['responded'], $r['enrolled']),
                    $r['passed'],
                    $r['failed'],
                    $pct($r['passed'], $r['passed'] + $r['failed']),
                ], null, 'A' . $rowNo++);
            }
            $sheet->getStyle('1:1')->getFont()->setBold(true);
            $sheet->freezePane('A2');
            foreach ($sheet->getColumnIterator() as $column) {
                $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }

            if (!is_dir($this->tempUploadDirectory)) {
                mkdir($this->tempUploadDirectory);
            }
            $filename = 'tb-participants-per-country-' . date('d-M-Y-H-i-s') . '.xlsx';
            IOFactory::createWriter($excel, 'Xlsx')->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Throwable $exc) {
            Pt_Commons_LoggerUtility::logError('Failed to generate TB participants per country report (Excel): ' . $exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            return '';
        }
    }

    /**
     * The results that count for the annual report and its certificates.
     * Older evaluations marked labs that did not respond as failed, so a fail
     * needs a submitted response. generate-certificates.php uses the same
     * filter, so the review Excel matches the certificates.
     */
    public const ANNUAL_RESULT_FILTER = "spm.final_result = 1 OR (spm.final_result = 2 AND spm.response_status IN ('responded','late'))";

    /**
     * The certificate a participant earns for one scheme: 'excellence' when
     * every selected shipment passed, 'participation' when every one has a
     * submitted response, otherwise ''. $entries maps shipment code to the
     * response's result and shipment_test_report_date. $codes lists every
     * selected shipment code of the scheme.
     */
    public static function annualCertificateType(array $entries, array $codes): string
    {
        $passedAll = true;
        foreach ($codes as $code) {
            $entry = $entries[$code] ?? null;
            if ($entry === null || empty($entry['shipment_test_report_date'])) {
                return '';
            }
            if ((int) $entry['result'] !== 1) {
                $passedAll = false;
            }
        }
        return $passedAll ? 'excellence' : 'participation';
    }

    /** The VL assay or EID extraction assay of a response, by name. */
    public static function annualReportAssayName(string $shipmentType, $attributes, array $vlAssays, array $eidAssays): string
    {
        $attributes = (array) $attributes;
        if ($shipmentType === 'vl' && !empty($attributes['vl_assay'])) {
            // Assay 6 is "Other": the lab typed the assay name in other_assay.
            if ((int) $attributes['vl_assay'] === 6 && !empty($attributes['other_assay'])) {
                return (string) $attributes['other_assay'];
            }
            return (string) ($vlAssays[$attributes['vl_assay']] ?? '');
        }
        if ($shipmentType === 'eid' && !empty($attributes['extraction_assay'])) {
            return (string) ($eidAssays[$attributes['extraction_assay']] ?? '');
        }
        return '';
    }

    /** The warnings and the corrective actions of a response, as plain text. */
    private function annualReportWarnings($failureReason): array
    {
        if (empty($failureReason) || $failureReason === '[]') {
            return ['', ''];
        }
        $txt = $note = '';
        foreach ((array) Pt_Commons_JsonUtility::safeDecode($failureReason) as $w) {
            $txt .= $w['warning'] ?? '';
            $note .= $w['correctiveAction'] ?? '';
        }
        return [strip_tags($txt), strip_tags($note)];
    }

    /** The TB enrolments that match the filters and the search. */
    private function tbResultsSelect(array $params): Zend_Db_Select
    {
        $name = Application_Model_DbTable_Participants::participantNameExpr('p');
        $sQuery = Zend_Db_Table_Abstract::getDefaultAdapter()->select()
            ->from(['p' => 'participant'], [])
            ->join(['sp' => 'shipment_participant_map'], 'sp.participant_id=p.participant_id', [])
            ->join(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', [])
            ->joinLeft(['c' => 'countries'], 'c.id=p.country', []);

        Application_Model_DbTable_Participants::applyResponseReportFilters($sQuery, ['scheme' => 'tb'] + $params);

        if (!empty($params['sSearch'])) {
            $searchColumns = [$name, 'p.unique_identifier', 'p.institute_name', 'p.region', 'c.iso_name', 's.shipment_code'];
            foreach (explode(' ', $params['sSearch']) as $search) {
                if ($search === '') {
                    continue;
                }
                $like = Pt_Commons_General::sqlLikeContains($search);
                $sQuery->where(implode(' OR ', array_map(fn ($col) => "$col LIKE $like", $searchColumns)));
            }
        }
        return $sQuery;
    }

    /** The TB enrolment rows, with the columns the table and the export show. */
    private function tbResultsColumns(Zend_Db_Select $sQuery): Zend_Db_Select
    {
        return $sQuery->columns([
            'p.unique_identifier',
            'p.institute_name',
            'p.region',
            'participant_name' => new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameExpr('p')),
            'country_name' => 'c.iso_name',
            's.shipment_code',
            's.shipment_date',
            'sp.shipment_test_report_date',
            'score' => new Zend_Db_Expr('IFNULL(sp.shipment_score, 0) + IFNULL(sp.documentation_score, 0)'),
            'outcome' => new Zend_Db_Expr(self::RESPONSE_OUTCOME_SQL),
        ]);
    }

    /**
     * TB: Result of PT Panel per Site. One row per participant and shipment,
     * in the DataTables server-side format, plus the number of enrolments of
     * each outcome (over the filters and the search, before the outcome
     * filter).
     */
    public function getTbResultsPerSite(array $params): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $outcomeExpr = self::RESPONSE_OUTCOME_SQL;

        $counts = array_fill_keys(array_keys(self::TB_RESULT_OUTCOMES), 0);
        $countRows = $db->fetchPairs(
            $this->tbResultsSelect($params)
                ->columns(['outcome' => new Zend_Db_Expr($outcomeExpr), 'n' => new Zend_Db_Expr('COUNT(*)')])
                ->group('outcome')
        );
        foreach ($countRows as $outcome => $n) {
            $counts[$outcome] = (int) $n;
        }
        $counts['all'] = array_sum($counts);

        $outcome = $params['outcome'] ?? '';
        $sQuery = $this->tbResultsColumns($this->tbResultsSelect($params));
        if (isset(self::TB_RESULT_OUTCOMES[$outcome])) {
            $sQuery->where("($outcomeExpr) = ?", $outcome);
        } else {
            $outcome = 'all';
        }

        $orderColumns = [
            'participant_name',
            "CONCAT_WS(', ', p.region, c.iso_name)",
            's.shipment_date',
            "IF(($outcomeExpr) IN ('pass','fail'), 0, 1), score",
            "FIELD(($outcomeExpr), 'pass', 'fail', 'excluded', 'nottested', 'none', 'pending')",
        ];
        $sOrder = [];
        if (isset($params['iSortCol_0'])) {
            for ($i = 0; $i < intval($params['iSortingCols']); $i++) {
                $colIdx = intval($params['iSortCol_' . $i]);
                if (($params['bSortable_' . $colIdx] ?? '') !== 'true' || !isset($orderColumns[$colIdx])) {
                    continue;
                }
                $direction = Pt_Commons_General::sanitizeSortDirection($params['sSortDir_' . $i]);
                // Scored responses first either way, then by score.
                if ($colIdx === 3) {
                    $sOrder[] = new Zend_Db_Expr("IF(($outcomeExpr) IN ('pass','fail'), 0, 1) ASC");
                    $sOrder[] = new Zend_Db_Expr("score $direction");
                    continue;
                }
                $sOrder[] = new Zend_Db_Expr($orderColumns[$colIdx] . ' ' . $direction);
            }
        }
        $sOrder[] = new Zend_Db_Expr('s.shipment_date DESC');
        $sOrder[] = new Zend_Db_Expr('participant_name ASC');
        $sQuery->order($sOrder);

        if (isset($params['iDisplayStart']) && ($params['iDisplayLength'] ?? '-1') != '-1') {
            $sQuery->limit((int) $params['iDisplayLength'], (int) $params['iDisplayStart']);
        }

        $output = [
            'sEcho' => intval($params['sEcho'] ?? 0),
            'iTotalRecords' => $counts['all'],
            'iTotalDisplayRecords' => $counts[$outcome],
            'counts' => $counts,
            'aaData' => [],
        ];

        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8', false);
        $date = fn ($d) => Pt_Commons_DateUtility::humanReadableDateFormat($d ?? '');
        foreach ($db->fetchAll($sQuery) as $r) {
            $sub = array_values(array_filter([$r['unique_identifier'], $r['institute_name']]));
            $name = trim((string) $r['participant_name']) !== '' ? $r['participant_name'] : array_shift($sub);
            [$label, $class] = self::TB_RESULT_OUTCOMES[$r['outcome']];
            $scored = in_array($r['outcome'], ['pass', 'fail'], true);
            $output['aaData'][] = [
                '<span class="cell-main">' . $esc($name) . '</span>'
                    . (!empty($sub) ? '<span class="cell-sub">' . $esc(implode(' · ', $sub)) . '</span>' : ''),
                $esc(implode(', ', array_filter([$r['region'], $r['country_name']]))),
                '<span class="cell-main">' . $esc($r['shipment_code']) . '</span><span class="cell-sub">' . $esc($date($r['shipment_date'])) . '</span>',
                $scored ? (string) round((float) $r['score']) : '',
                '<span class="resp-pill ' . $class . '">' . $esc($this->translator->_($label)) . '</span>',
            ];
        }

        return $output;
    }

    /** TB: Result of PT Panel per Site as Excel, one row per participant and shipment. */
    public function exportTbResultsPerSite(array $params): string
    {
        try {
            $sQuery = $this->tbResultsColumns($this->tbResultsSelect($params))
                ->order(['s.shipment_date DESC', 'participant_name ASC']);
            $outcome = $params['outcome'] ?? '';
            if (isset(self::TB_RESULT_OUTCOMES[$outcome])) {
                $sQuery->where('(' . self::RESPONSE_OUTCOME_SQL . ') = ?', $outcome);
            }
            $rows = Zend_Db_Table_Abstract::getDefaultAdapter()->fetchAll($sQuery);
            if (empty($rows)) {
                return '';
            }

            $t = fn ($text) => html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $labels = array_map(fn ($o) => $t($this->translator->_($o[0])), self::TB_RESULT_OUTCOMES);

            $excel = new Spreadsheet();
            $sheet = $excel->getActiveSheet();
            $sheet->setTitle(mb_substr($t($this->translator->_('Results')), 0, 31));
            $sheet->fromArray([
                $t($this->translator->_('Participant')),
                $t($this->translator->_('Participant ID')),
                $t($this->translator->_('Institute Name')),
                $t($this->translator->_('Region')),
                $t($this->translator->_('Country')),
                $t($this->translator->_('Shipment Code')),
                $t($this->translator->_('Shipment Date')),
                $t($this->translator->_('Responded On')),
                $t($this->translator->_('Score')),
                $t($this->translator->_('Result')),
            ], null, 'A1');
            $rowNo = 2;
            foreach ($rows as $r) {
                $sheet->fromArray([
                    $r['participant_name'],
                    $r['unique_identifier'],
                    $r['institute_name'],
                    $r['region'],
                    $r['country_name'],
                    $r['shipment_code'],
                    Pt_Commons_DateUtility::humanReadableDateFormat($r['shipment_date'] ?? ''),
                    Pt_Commons_DateUtility::humanReadableDateFormat($r['shipment_test_report_date'] ?? ''),
                    in_array($r['outcome'], ['pass', 'fail'], true) ? round((float) $r['score'], 1) : null,
                    $labels[$r['outcome']],
                ], null, 'A' . $rowNo++);
            }
            $sheet->getStyle('1:1')->getFont()->setBold(true);
            $sheet->freezePane('A2');
            foreach ($sheet->getColumnIterator() as $column) {
                $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }

            if (!is_dir($this->tempUploadDirectory)) {
                mkdir($this->tempUploadDirectory);
            }
            $filename = 'tb-results-per-site-' . date('d-M-Y-H-i-s') . '.xlsx';
            IOFactory::createWriter($excel, 'Xlsx')->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Throwable $exc) {
            Pt_Commons_LoggerUtility::logError('Failed to generate TB results per site report (Excel): ' . $exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            return '';
        }
    }

    public function generateAnnualReportExcel($shipmentCodeArray, $participants)
    {

        // One row per participant and seven columns per shipment: a year of a
        // large programme runs past the default memory limit.
        ini_set('memory_limit', '-1');
        $schemeService = new Application_Service_Schemes();
        $vlModel = new Application_Model_Vl();
        $vlAssayArray = $vlModel->getVlAssay();
        $eidAssayArray = $schemeService->getEidExtractionAssay();

        $headings = ['Participant ID', 'Participant Name', 'Institute Name', 'Department', 'Address', 'City', 'District', 'State', 'Country', 'Email', 'Additional Email', 'Scheme Type'];
        foreach ($shipmentCodeArray as $participantArray) {
            foreach ($participantArray as $shipmentCode) {
                $headings[] = 'Shipment Code';
                $headings[] = 'Number of Samples - ' . $shipmentCode;
                $headings[] = 'Assay/Platform/Kit - ' . $shipmentCode;
                $headings[] = 'Score - ' . $shipmentCode;
                $headings[] = 'Final Result - ' . $shipmentCode;
                $headings[] = 'Warning/Errors - ' . $shipmentCode;
                $headings[] = 'Corrective Actions - ' . $shipmentCode;
            }

            $headings[] = 'Certificate Type';
        }

        $excel = new Spreadsheet();

        $output = [];

        //$sheet = $excel->getActiveSheet();
        $firstSheet = new Worksheet($excel, '');
        $excel->addSheet($firstSheet, 0);
        $firstSheet->getDefaultColumnDimension()->setWidth(20);
        $firstSheet->getDefaultRowDimension()->setRowHeight(18);
        $firstSheet->setTitle('ePT Annual Report', true);

        foreach ($participants as $uniqueIdentifier => $participantArray) {
            $firstSheetRow = [];
            $firstSheetRow[] = $uniqueIdentifier;
            $firstSheetRow[] = $participantArray['labName'];
            $firstSheetRow[] = $participantArray['institute_name'];
            $firstSheetRow[] = $participantArray['department_name'];
            $firstSheetRow[] = $participantArray['address'];
            $firstSheetRow[] = $participantArray['city'];
            $firstSheetRow[] = $participantArray['district'];
            $firstSheetRow[] = $participantArray['state'];
            $firstSheetRow[] = $participantArray['country_name'];
            $firstSheetRow[] = $participantArray['email'];
            $firstSheetRow[] = $participantArray['additional_email'];
            $firstSheetRow[] = $participantArray['scheme_name'];
            foreach ($shipmentCodeArray as $shipmentType => $shipmentsList) {
                foreach ($shipmentsList as $shipmentCode) {
                    $entry = $participantArray[$shipmentType][$shipmentCode] ?? null;
                    $firstSheetRow[] = $shipmentCode;
                    if ($entry === null) {
                        // No evaluated response to this shipment.
                        array_push($firstSheetRow, '', '', '-', '', '', '');
                        continue;
                    }
                    $firstSheetRow[] = $entry['number_of_samples'];
                    $firstSheetRow[] = self::annualReportAssayName($shipmentType, $entry['attributes'], $vlAssayArray, $eidAssayArray);
                    $firstSheetRow[] = $entry['score'];
                    $firstSheetRow[] = $entry['finalResult'];
                    [$warnings, $actions] = $this->annualReportWarnings($entry['failure_reason']);
                    $firstSheetRow[] = $warnings;
                    $firstSheetRow[] = $actions;
                }
                $certificateType = self::annualCertificateType($participantArray[$shipmentType] ?? [], $shipmentsList);
                $firstSheetRow[] = $certificateType === '' ? 'N.A.' : ucfirst($certificateType);
            }

            $output[] = $firstSheetRow;
        }

        // Generating Excel from the data
        $firstSheet->fromArray($headings, null, 'A1');
        $firstSheet->fromArray($output, null, 'A2');

        $firstSheet = $this->common->centerAndBoldRowInSheet($firstSheet, 'A1');
        $firstSheet = $this->common->applyBordersToSheet($firstSheet);
        $firstSheet = $this->common->setAllColumnWidthsInSheet($firstSheet, 20);

        if (!is_dir($this->tempUploadDirectory . DIRECTORY_SEPARATOR . 'annual-reports')) {
            mkdir($this->tempUploadDirectory . DIRECTORY_SEPARATOR . 'annual-reports', 0777, true);
        }

        $excel->setActiveSheetIndex(0);
        $writer = IOFactory::createWriter($excel, 'Xlsx');
        $filename = 'ePT-Annual-Performance-Report-' . $this->common->generateRandomString(16) . '-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . 'annual-reports' . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }

    public function generateAnnualReportCSV($shipmentCodeArray, $participants)
    {
        $schemeService = new Application_Service_Schemes();
        $vlModel = new Application_Model_Vl();
        $vlAssayArray = $vlModel->getVlAssay();
        $eidAssayArray = $schemeService->getEidExtractionAssay();

        $headings = ['Participant ID', 'Participant Name', 'Institute Name', 'Department', 'Address', 'City', 'District', 'State', 'Country', 'Email', 'Additional Email', 'Scheme Type', 'Shipment Code', 'Number of Samples', 'Shipment Date', 'Result Due Date', 'Responded On', 'Assay/Platform/Kit', 'Score', 'Final Result', 'Warning/Errors', 'Corrective Actions'];

        $tempFile = tempnam(sys_get_temp_dir(), 'annual-report-');
        $tempFileHandle = fopen($tempFile, 'w');

        // Write the header row
        fputcsv($tempFileHandle, $headings, ',', '"', '');

        foreach ($participants as $uniqueIdentifier => $participantArray) {
            foreach ($shipmentCodeArray as $shipmentType => $shipmentsList) {
                foreach ($shipmentsList as $shipmentCode) {
                    $entry = $participantArray[$shipmentType][$shipmentCode] ?? null;
                    if ($entry === null) {
                        continue;
                    }
                    [$warnings, $actions] = $this->annualReportWarnings($entry['failure_reason']);
                    fputcsv($tempFileHandle, [
                        (string) $uniqueIdentifier,
                        $participantArray['labName'],
                        $participantArray['institute_name'],
                        $participantArray['department_name'],
                        $participantArray['address'],
                        $participantArray['city'],
                        $participantArray['district'],
                        $participantArray['state'],
                        $participantArray['country_name'],
                        $participantArray['email'],
                        $participantArray['additional_email'],
                        $entry['scheme_name'],
                        $shipmentCode,
                        $entry['number_of_samples'],
                        Pt_Commons_DateUtility::humanReadableDateFormat($entry['shipment_date']),
                        Pt_Commons_DateUtility::humanReadableDateFormat($entry['response_deadline']),
                        Pt_Commons_DateUtility::humanReadableDateFormat($entry['shipment_test_report_date']),
                        $this->annualReportAssayName($shipmentType, $entry['attributes'], $vlAssayArray, $eidAssayArray),
                        $entry['score'],
                        $entry['finalResult'],
                        $warnings,
                        $actions,
                    ], ',', '"', '');
                }
            }
        }

        fclose($tempFileHandle);

        if (!is_dir($this->tempUploadDirectory . DIRECTORY_SEPARATOR . 'annual-reports')) {
            mkdir($this->tempUploadDirectory . DIRECTORY_SEPARATOR . 'annual-reports', 0777, true);
        }

        $filename = 'ePT-Annual-Performance-Report-' . $this->common->generateRandomString(16) . '-' . date('d-M-Y-H-i-s') . '.csv';
        $csvFile = $this->tempUploadDirectory . DIRECTORY_SEPARATOR . 'annual-reports' . DIRECTORY_SEPARATOR . $filename;
        rename($tempFile, $csvFile);

        return $filename;
    }

    public function getFinalisedShipmentsByScheme($schemeType, $startDate, $endDate)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $db->select()->from(['s' => 'shipment'], ['s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date',]);
        if (isset($startDate) && $startDate != '') {
            $sQuery->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($startDate));
        }
        if (isset($endDate) && $endDate != '') {
            $sQuery->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($endDate));
        }
        if (isset($schemeType) && $schemeType != '') {
            $sQuery->where('s.scheme_type = ?', $schemeType);
        }
        $sQuery->where("s.status = 'finalized'");
        $sQuery->order('s.shipment_id');
        $resultArray = $db->fetchAll($sQuery);
        return $resultArray;
    }

    public function getXtptIndicatorsReport($params)
    {
        $db = new Application_Model_Tb();
        return $db->fetchXtptIndicatorsReport($params);
    }

    public function getTbAllSitesResultsSheet($db, $shipmentId, $excel, $sheetIndex)
    {
        $tbDb = new Application_Model_Tb();
        return $tbDb->fetchTbAllSitesResultsSheet($db, $shipmentId, $excel, $sheetIndex);
    }

    public function getTbAllSitesResultsReport($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $tbDb = new Application_Model_Tb();
        $excel = new Spreadsheet();
        $tbDb->fetchTbAllSitesResultsSheet($db, $params['shipmentId'], $excel, 0);
        // The sheet goes in front of the empty default one.
        $excel->removeSheetByIndex($excel->getSheetCount() - 1);
        $excel->setActiveSheetIndex(0);

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $shipmentQuery = $db->select()->from('shipment', ['shipment_code'])->where('shipment_id=?', $params['shipmentId']);
        $shipmentResult = $db->fetchRow($shipmentQuery);
        $writer = IOFactory::createWriter($excel, 'Xlsx');
        if (!file_exists($this->tempUploadDirectory . DIRECTORY_SEPARATOR . 'generated-tb-reports')) {
            mkdir($this->tempUploadDirectory . DIRECTORY_SEPARATOR . 'generated-tb-reports', 0777, true);
        }
        $fileSafeShipmentCode = str_replace(' ', '-', str_replace(array_merge(
            array_map('chr', range(0, 31)),
            ['<', '>', ':', '"', '/', '\\', '|', '?', '*']
        ), '', $shipmentResult['shipment_code']));
        $filename = $fileSafeShipmentCode . '-TB-ALL-SITES-RESULTS-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . 'generated-tb-reports' . DIRECTORY_SEPARATOR . $filename);

        return [
            'report-name' => $filename,
        ];
    }

    public function getStatusOfMappedSites($parameters)
    {
        try {
            $db = new Application_Model_DbTable_Shipments();
            $resultSet = $db->getStatusOfMappedSites($parameters);
            if (isset($resultSet) && count($resultSet) > 0) {

                $excel = new Spreadsheet();

                $output = [];
                $sheet = $excel->getActiveSheet();
                $colNo = 0;

                $sheet->getCell('A1')->setValue(html_entity_decode('Scheme', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('B1')->setValue(html_entity_decode('Shipment Code', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('C1')->setValue(html_entity_decode('Shipment Date', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('D1')->setValue(html_entity_decode('Result Due Date', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('E1')->setValue(html_entity_decode('Participant ID', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('F1')->setValue(html_entity_decode('Participant', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('G1')->setValue(html_entity_decode('Instritute Name', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('H1')->setValue(html_entity_decode('Department', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('I1')->setValue(html_entity_decode('Email', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('J1')->setValue(html_entity_decode('Mobile', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('K1')->setValue(html_entity_decode('Address', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('L1')->setValue(html_entity_decode('City', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('M1')->setValue(html_entity_decode('State', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('N1')->setValue(html_entity_decode('District', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('O1')->setValue(html_entity_decode('Country', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('P1')->setValue(html_entity_decode('Response Status', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('Q1')->setValue(html_entity_decode('Response Date', ENT_QUOTES, 'UTF-8'));
                $sheet = $this->common->centerAndBoldRowInSheet($sheet, 'A1');

                foreach ($resultSet as $aRow) {
                    $row = [];
                    $row[] = ($aRow['panelName'] ?? $aRow['scheme_name']);
                    $row[] = $aRow['shipment_code'];
                    $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($aRow['shipment_date']);
                    $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($aRow['response_deadline']);
                    $row[] = $aRow['unique_identifier'];
                    $row[] = $aRow['first_name'] . ' ' . $aRow['last_name'];
                    $row[] = $aRow['institute_name'];
                    $row[] = $aRow['department_name'];
                    $row[] = $aRow['email'];
                    $row[] = $aRow['mobile'];
                    $row[] = $aRow['address'];
                    $row[] = $aRow['city'];
                    $row[] = $aRow['state'];
                    $row[] = $aRow['district'];
                    $row[] = $aRow['iso_name'];
                    $row[] = strtoupper($aRow['response_status']);
                    $row[] = $aRow['response_date'];

                    $output[] = $row;
                }

                foreach ($output as $rowNo => $rowData) {
                    $colNo = 0;
                    foreach ($rowData as $value) {
                        $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . ($rowNo + 2))
                            ->setValueExplicit(html_entity_decode($value));

                        $colNo++;
                    }
                }

                $writer = IOFactory::createWriter($excel, 'Xlsx');
                $shipmentCode = (string) ($resultSet[0]['shipment_code'] ?? '');
                $filename = $shipmentCode . '-Response-Status-' . date('d-M-Y-H-i-s') . '.xlsx';
                $writer->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
                $rowCount = count($resultSet);
                $detail = $shipmentCode !== '' ? " - {$shipmentCode}" : '';
                $auditDb = new Application_Model_DbTable_AuditLog();
                $auditDb->addNewAuditLog("Downloaded pending sites report{$detail} ({$rowCount} rows)", 'shipment');
                return $filename;
            } else {
                return '';
            }
        } catch (Exception $exc) {
            Pt_Commons_LoggerUtility::logError($exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);

            return '';
        }
    }

    public function saveReportDownloadDateTime($id, $type)
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $dmId = !empty($authNameSpace->dm_id) ? (int) $authNameSpace->dm_id : null;
        $id = (int) $id;
        // Build WHERE clause based on type. For 'summary' $id is the shipment id
        // and the update touches every row mapped to this data manager; for
        // 'individual' $id is the map id of a single row.
        if ($type == 'summary') {
            if (empty($dmId)) {
                return 0;
            }
            $participantIds = $dbAdapter->fetchCol(
                $dbAdapter->select()
                    ->from(['pmm' => 'participant_manager_map'], ['participant_id'])
                    ->where('pmm.dm_id = ?', $dmId)
            );
            if (empty($participantIds)) {
                return 0;
            }
            $where = 'participant_id IN(' . implode(',', array_map('intval', $participantIds)) . ") AND shipment_id = $id";
            $prefix = 'summary';
        } else {
            $where = "map_id = $id";
            $prefix = 'individual';
        }
        $shipmentId = $dbAdapter->fetchCol($dbAdapter->select()->from(['spm' => 'shipment_participant_map'], ['shipment_id'])
        ->where($where))[0];
        $common = new Application_Service_Common();
        $common->saveDownloadedHistoryDetails([ 'shipment_id' => $shipmentId, 'report_type' => $prefix ], $dmId);

        $now = $dbAdapter->quote(Pt_Commons_DateUtility::getCurrentDateTime());
        $meta = 'report_download_metadata';

        // JSON_SET merges into each row's own metadata, so a multi-row summary
        // update cannot clobber another row's individual-download history.
        $sets = [
            "'$.first_{$prefix}_report_on', COALESCE(JSON_UNQUOTE(JSON_EXTRACT($meta, '$.first_{$prefix}_report_on')), $now)",
            "'$.latest_{$prefix}_report_on', $now",
            // Legacy key: the participant (lab) the download belongs to
            "'$.first_{$prefix}_report_by', COALESCE(JSON_UNQUOTE(JSON_EXTRACT($meta, '$.first_{$prefix}_report_by')), participant_id)",
            "'$.latest_{$prefix}_report_by', participant_id",
            /* To track whether the report gets downloaded or not during login redirection. */
            "'$.report_downloaded', 'yes'",
        ];
        // The logged-in data manager who actually downloaded the report
        if (!empty($dmId)) {
            $sets[] = "'$.first_{$prefix}_report_by_dm', COALESCE(JSON_UNQUOTE(JSON_EXTRACT($meta, '$.first_{$prefix}_report_by_dm')), $dmId)";
            $sets[] = "'$.latest_{$prefix}_report_by_dm', $dmId";
        }

        $expr = "JSON_SET(COALESCE($meta, JSON_OBJECT()), " . implode(', ', $sets) . ')';

        return $dbAdapter->update(
            'shipment_participant_map',
            ['report_download_metadata' => new Zend_Db_Expr($expr)],
            $where
        );
    }

    public function getAvailableShipments($schemeType = null, $startDate = null, $endDate = null)
    {
        $sql = 'SELECT DISTINCT s.shipment_id, s.shipment_code, s.scheme_type
                FROM shipment s
                INNER JOIN shipment_participant_map spm ON s.shipment_id = spm.shipment_id
                WHERE 1=1';

        $params = [];

        if ($schemeType) {
            $sql .= ' AND s.scheme_type = ?';
            $params[] = $schemeType;
        }

        if ($startDate && $endDate) {
            $sql .= ' AND s.shipment_date BETWEEN ? AND ?';
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $sql .= ' ORDER BY s.shipment_code';

        return $this->db->fetchAll($sql, $params);
    }

    public function getTestingFacilityByOwnerShips($params)
    {
        $startDate = null;
        $endDate = null;
        $schemeType = null;

        // Parse date range if provided
        if (!empty($params['dateRange'])) {
            $dateRange = explode(' to ', $params['dateRange']);
            if (count($dateRange) == 2) {
                $startDate = date('Y-m-d', strtotime($dateRange[0]));
                $endDate = date('Y-m-d', strtotime($dateRange[1]));
            }
        }

        // Parse scheme type if provided
        if (!empty($params['scheme'])) {
            $schemeType = $params['scheme'];
        }

        // Get all available shipments
        $shipments = $this->getAvailableShipments($schemeType, $startDate, $endDate);

        // Get funding sources
        $fundingSources = $this->getFundingSources($schemeType, $startDate, $endDate);

        $shipmentsData = [];
        foreach ($shipments as $shipment) {
            $shipmentData = $this->getShipmentDataByFundingSource($shipment['shipment_id']);
            $shipmentsData[] = [
                'shipment_id' => $shipment['shipment_id'],
                'shipment_code' => $shipment['shipment_code'],
                'data' => $shipmentData,
            ];
        }

        // Get overall statistics by funding source
        $overallStats = $this->getOverallStatisticsByFundingSource($schemeType, $startDate, $endDate);

        return [
            'shipments' => $shipmentsData,
            'fundingSources' => $fundingSources,
            'overallStats' => $overallStats,
        ];
    }

    public function getFundingSources($schemeType = null, $startDate = null, $endDate = null)
    {
        $sql = "SELECT DISTINCT p.funding_source
            FROM participant p
            INNER JOIN shipment_participant_map spm ON p.participant_id = spm.participant_id
            INNER JOIN shipment s ON spm.shipment_id = s.shipment_id
            WHERE p.funding_source IS NOT NULL AND p.funding_source != ''";

        $params = [];

        if ($schemeType) {
            $sql .= ' AND s.scheme_type = ?';
            $params[] = $schemeType;
        }

        if ($startDate && $endDate) {
            $sql .= ' AND s.shipment_date BETWEEN ? AND ?';
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $sql .= ' ORDER BY p.funding_source';

        return $this->db->fetchAll($sql, $params);
    }

    protected function getShipmentDataByFundingSource($shipmentId)
    {
        $sql = "SELECT
                p.funding_source,
                COUNT(DISTINCT p.participant_id) as count
            FROM shipment_participant_map spm
            INNER JOIN participant p ON spm.participant_id = p.participant_id
            WHERE spm.shipment_id = ?
            AND p.funding_source IS NOT NULL
            AND p.funding_source != ''
            GROUP BY p.funding_source
            ORDER BY p.funding_source";

        $results = $this->db->fetchAll($sql, [$shipmentId]);

        // Calculate total for percentage calculation
        $total = array_sum(array_column($results, 'count'));

        $response = [];
        foreach ($results as $row) {
            $percentage = $total > 0 ? round(($row['count'] / $total) * 100, 2) : 0;
            $response[$row['funding_source']] = [
                'count' => $row['count'],
                'percentage' => $percentage,
            ];
        }

        return $response;
    }

    public function getOverallStatisticsByFundingSource($schemeType = null, $startDate = null, $endDate = null)
    {
        $sql = "SELECT
                p.funding_source,
                COUNT(DISTINCT p.participant_id) as count
            FROM participant p
            INNER JOIN shipment_participant_map spm ON p.participant_id = spm.participant_id
            INNER JOIN shipment s ON spm.shipment_id = s.shipment_id
            WHERE p.funding_source IS NOT NULL
            AND p.funding_source != ''";

        $params = [];

        if ($schemeType) {
            $sql .= ' AND s.scheme_type = ?';
            $params[] = $schemeType;
        }

        if ($startDate && $endDate) {
            $sql .= ' AND s.shipment_date BETWEEN ? AND ?';
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $sql .= ' GROUP BY p.funding_source ORDER BY p.funding_source';

        $results = $this->db->fetchAll($sql, $params);

        // Calculate total for percentage calculation
        $total = array_sum(array_column($results, 'count'));

        $response = [];
        foreach ($results as $row) {
            $percentage = $total > 0 ? round(($row['count'] / $total) * 100, 2) : 0;
            $response[$row['funding_source']] = [
                'count' => $row['count'],
                'percentage' => $percentage,
            ];
        }

        // Add total
        $response['total'] = $total;

        return $response;
    }

    public function exportTestingFacilityByOwnership($params)
    {
        try {
            $data = $this->getTestingFacilityByOwnerShips($params);

            // Create new Spreadsheet object
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set sheet title
            $sheet->setTitle('Testing Facility by Ownership');

            // Create headers
            $headers = ['Type of Testing Facility', 'Total Registered No.', 'Total Registered %'];
            // Add shipment headers
            foreach ($data['shipments'] as $shipment) {
                $headers[] = $shipment['shipment_code'] . ' No.';
                $headers[] = $shipment['shipment_code'] . ' %';
            }

            // Set headers in first row
            $column = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($column . '1', $header);
                $column++;
            }

            // Style headers
            $headerRange = 'A1:' . chr(64 + count($headers)) . '1';
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE0E0E0');

            $row = 2;

            // Add rows for each funding source
            foreach ($data['fundingSources'] as $fundingSource) {
                $fundingSourceName = $fundingSource['funding_source'];
                $rowData = [
                    ucfirst($fundingSourceName),
                    $data['overallStats'][$fundingSourceName]['count'] ?? 0,
                    ($data['overallStats'][$fundingSourceName]['percentage'] ?? 0) . '%',
                ];

                // Add shipment data for this funding source
                foreach ($data['shipments'] as $shipment) {
                    $count = $shipment['data'][$fundingSourceName]['count'] ?? 0;
                    $percentage = $shipment['data'][$fundingSourceName]['percentage'] ?? 0;
                    $rowData[] = $count;
                    $rowData[] = $percentage . '%';
                }

                $column = 'A';
                foreach ($rowData as $value) {
                    $sheet->setCellValue($column . $row, $value);
                    $column++;
                }
                $row++;
            }

            // Total row
            $totalData = ['Total Participants', $data['overallStats']['total'], '100%'];
            foreach ($data['shipments'] as $shipment) {
                $shipmentTotal = array_sum(array_column($shipment['data'], 'count'));
                $totalData[] = $shipmentTotal;
                $totalData[] = '100%';
            }

            $column = 'A';
            foreach ($totalData as $value) {
                $sheet->setCellValue($column . $row, $value);
                $column++;
            }

            // Style total row
            $totalRange = 'A' . $row . ':' . chr(64 + count($totalData)) . $row;
            $sheet->getStyle($totalRange)->getFont()->setBold(true);

            // Auto-size columns safely
            $highestColumn = Coordinate::stringFromColumnIndex(count($headers));
            //foreach (range('A', chr(64 + count($headers))) as $columnID) {
            foreach (range('A', $highestColumn) as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            // Add borders to all data
            $dataRange = 'A1:' . chr(64 + count($headers)) . $row;
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            // Create file
            $fileName = 'testing_facility_by_ownership_' . date('Y_m_d_H_i_s') . '.xlsx';
            $filePath = APPLICATION_PATH . '/../public/temporary/' . $fileName;

            // Ensure directory exists
            if (!is_dir(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }

            // Write file
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($filePath);

            // Clean up memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return $fileName;
        } catch (Throwable $e) {
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Participation and performance per shipment for the Participant Trends report.
     *
     * The counts follow Application_Service_Shipments::getShipmentParticipationStats():
     * a participant responded when response_status is responded, late or nottested,
     * and only responders count as failed. The pass rate is passed over evaluated
     * (passed + failed), so a shipment that is not evaluated yet has no pass rate
     * instead of showing 0%.
     *
     * With $byRegion the rows are per participant region, for the one shipment in
     * $params['shipmentId'].
     */
    public function getParticipantTrendsSummary(array $params, bool $byRegion = false): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $responded = "IFNULL(sp.response_status, '') IN ('responded','late','nottested')";
        $late = "(IFNULL(sp.response_status, '') = 'late'
                    OR IFNULL(sp.is_response_late, '') = 'yes'
                    OR IFNULL(sp.shipment_test_report_date > s.response_deadline, 0))";
        $passed = 'IFNULL(sp.final_result, 0) = 1';
        $failed = "IFNULL(sp.final_result, 0) = 2 AND IFNULL(sp.response_status, '') IN ('responded','late')";
        $score = 'IFNULL(sp.shipment_score, 0) + IFNULL(sp.documentation_score, 0)';

        $counts = [
            'enrolled' => new Zend_Db_Expr('COUNT(sp.map_id)'),
            'responded' => new Zend_Db_Expr("SUM($responded)"),
            'on_time' => new Zend_Db_Expr("SUM($responded AND NOT $late)"),
            'passed' => new Zend_Db_Expr("SUM($passed)"),
            'failed' => new Zend_Db_Expr("SUM($failed)"),
            // Scores are decimal(5,2) and some schemes round, so a full score can be stored as 99.99.
            'excellent' => new Zend_Db_Expr("SUM($passed AND ($score) >= 99.99)"),
        ];

        $query = $db->select()
            ->from(['s' => 'shipment'], [])
            ->join(['sl' => 'scheme_list'], 'sl.scheme_id = s.scheme_type', [])
            ->join(['sp' => 'shipment_participant_map'], 'sp.shipment_id = s.shipment_id', $counts)
            ->join(['p' => 'participant'], 'p.participant_id = sp.participant_id', [])
            ->where('s.cancelled_at IS NULL');

        if ($byRegion) {
            $query->columns(['region' => new Zend_Db_Expr("IFNULL(NULLIF(TRIM(p.region), ''), '')")])
                ->group(new Zend_Db_Expr("IFNULL(NULLIF(TRIM(p.region), ''), '')"))
                // Participants without a region go last.
                ->order([new Zend_Db_Expr("IFNULL(NULLIF(TRIM(p.region), ''), '') = ''"), 'region']);
        } else {
            $query->columns([
                's.shipment_id',
                's.shipment_code',
                's.shipment_date',
                's.scheme_type',
                'sl.scheme_name',
            ])
                ->group('s.shipment_id')
                ->order(['s.shipment_date', 's.shipment_id']);
        }

        $scopedDmId = Application_Service_Common::getScopedDmId();
        if (!empty($scopedDmId)) {
            $query->join(['pmm' => 'participant_manager_map'], 'pmm.participant_id = p.participant_id', [])
                ->where('pmm.dm_id = ?', $scopedDmId);
        }
        if (!empty($params['scheme'])) {
            $query->where('s.scheme_type = ?', $params['scheme']);
        } else {
            // All schemes means the schemes the Scheme filter offers: active,
            // and only the admin's own when they are limited to some.
            $schemeIds = array_column((new Application_Model_DbTable_SchemeList())->getAllSchemes(), 'scheme_id');
            if (empty($schemeIds)) {
                return [];
            }
            $query->where('s.scheme_type IN (?)', $schemeIds);
        }
        if (!empty($params['startDate']) && !empty($params['endDate'])) {
            $query->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($params['startDate']))
                ->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($params['endDate']));
        }
        if (!empty($params['shipmentId'])) {
            $query->where('s.shipment_id = ?', $params['shipmentId']);
        } elseif ($byRegion) {
            return [];
        }

        $rows = [];
        foreach ($db->fetchAll($query) as $row) {
            foreach (['enrolled', 'responded', 'on_time', 'passed', 'failed', 'excellent'] as $key) {
                $row[$key] = (int) ($row[$key] ?? 0);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Excel export of the Participant Trends report: a sheet with one row per shipment,
     * and one sheet with the region breakdown of every shipment in the filters.
     * Returns the file name in the temporary folder, or '' on failure.
     */
    public function exportParticipantTrendsSummary(array $params): string
    {
        try {
            $shipments = $this->getParticipantTrendsSummary($params);
            if (empty($shipments)) {
                return '';
            }

            $t = fn ($text) => html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $pct = fn (int $part, int $whole) => $whole > 0 ? round($part / $whole * 100, 1) : null;
            $headings = [
                $t($this->translator->_('Enrolled')),
                $t($this->translator->_('Responded')),
                $t($this->translator->_('Response rate')) . ' (%)',
                $t($this->translator->_('On time')),
                $t($this->translator->_('On time')) . ' (%)',
                $t($this->translator->_('Passed')),
                $t($this->translator->_('Failed')),
                $t($this->translator->_('Pass rate')) . ' (%)',
                $t($this->translator->_('Excellent')),
                $t($this->translator->_('Satisfactory')),
                $t($this->translator->_('Unsatisfactory')),
            ];
            $figures = fn (array $r) => [
                $r['enrolled'],
                $r['responded'],
                $pct($r['responded'], $r['enrolled']),
                $r['on_time'],
                $pct($r['on_time'], $r['responded']),
                $r['passed'],
                $r['failed'],
                $pct($r['passed'], $r['passed'] + $r['failed']),
                $r['excellent'],
                $r['passed'] - $r['excellent'],
                $r['failed'],
            ];

            $excel = new Spreadsheet();
            $sheet = $excel->getActiveSheet();
            $sheet->setTitle(mb_substr($t($this->translator->_('Shipments')), 0, 31));
            $sheet->fromArray(array_merge([$t($this->translator->_('Scheme')), $t($this->translator->_('Shipment Code')), $t($this->translator->_('Shipment Date'))], $headings), null, 'A1');
            $rowNo = 2;
            foreach ($shipments as $r) {
                $sheet->fromArray(array_merge(
                    [$r['scheme_name'], $r['shipment_code'], Pt_Commons_DateUtility::humanReadableDateFormat($r['shipment_date'])],
                    $figures($r)
                ), null, 'A' . $rowNo++);
            }

            $regionSheet = $excel->createSheet();
            $regionSheet->setTitle(mb_substr($t($this->translator->_('Regions')), 0, 31));
            $regionSheet->fromArray(array_merge([$t($this->translator->_('Shipment Code')), $t($this->translator->_('Region'))], $headings), null, 'A1');
            $rowNo = 2;
            foreach ($shipments as $shipment) {
                $regions = $this->getParticipantTrendsSummary(array_merge($params, ['shipmentId' => $shipment['shipment_id']]), true);
                foreach ($regions as $r) {
                    $regionSheet->fromArray(array_merge(
                        [$shipment['shipment_code'], $r['region'] !== '' ? $r['region'] : $t($this->translator->_('No region'))],
                        $figures($r)
                    ), null, 'A' . $rowNo++);
                }
            }

            foreach ([$sheet, $regionSheet] as $ws) {
                $ws->getStyle('1:1')->getFont()->setBold(true);
                $ws->freezePane('A2');
                foreach ($ws->getColumnIterator() as $column) {
                    $ws->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
                }
            }
            $excel->setActiveSheetIndex(0);

            if (!is_dir($this->tempUploadDirectory)) {
                mkdir($this->tempUploadDirectory);
            }
            $filename = 'participant-trends-' . date('d-M-Y-H-i-s') . '.xlsx';
            IOFactory::createWriter($excel, 'Xlsx')->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Throwable $exc) {
            Pt_Commons_LoggerUtility::logError('Failed to generate participant trends report (Excel): ' . $exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            return '';
        }
    }

    public function getVlAssayByShipmentId($sid)
    {
        $vlObj = new Application_Model_Vl();
        return $vlObj->fetchVlAssayByShipmentId($sid);
    }

    public function getEidAssayByShipmentId($sid)
    {
        $eidObj = new Application_Model_Eid();
        return $eidObj->fetchEidAssayByShipmentId($sid);
    }

    public function bulkDownloadExcel($shipments)
    {
        foreach ($shipments as $item) {
            $shipmentId = base64_decode($item['id']);
            $schemeType = $item['scheme'];

            // Call the exact same service your existing generateShipmentParticipantList uses
            $filename = $this->getShipmentParticipant(
                $shipmentId,
                $schemeType
            );

            if (!empty($filename)) {
                $filePath = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename;
                if (file_exists($filePath)) {
                    $generatedFiles[] = $filePath;
                }
            }
        }

        if (empty($generatedFiles)) {
            echo '';
            return;
        }

        // If only one file, return it directly (no zip needed)
        if (count($generatedFiles) === 1) {
            echo basename($generatedFiles[0]);
            return;
        }

        // Zip all generated files together
        $zipFilename = 'bulk-overview-' . date('Ymd-His') . '.zip';
        $zipPath = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $zipFilename;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            foreach ($generatedFiles as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
            echo $zipFilename;
        } else {
            echo '';
        }
    }

    /** Shipment-wide pass/fail counts. Report generation and the zimbabwe participant layouts read it. */
    public function getParticipantPerformanceReportByShipmentId($shipmentId, $testType = '')
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(['s' => 'shipment'])
            ->join(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id')
            ->joinLeft(
                ['sp' => 'shipment_participant_map'],
                'sp.shipment_id=s.shipment_id',
                [
                    "DATE_FORMAT(s.shipment_date,'%d-%b-%Y')",
                    'total_shipped' => new Zend_Db_Expr('count("sp.map_id")'),
                    // "total_responses" => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
                    'total_responses' => new Zend_Db_Expr("SUM(response_status IN ('responded', 'nottested'))"),
                    'valid_responses' => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00%' AND is_excluded != 'yes'))"),
                    'number_failed' => new Zend_Db_Expr('SUM(CASE WHEN (sp.final_result = 2 AND sp.shipment_test_report_date <= s.response_deadline) THEN 1 ELSE 0 END)'),
                    'score' => new Zend_Db_Expr('(SUM(sp.shipment_score) + SUM(sp.documentation_score))'),
                    'number_passed' => new Zend_Db_Expr('SUM(CASE WHEN (sp.final_result = 1 AND sp.shipment_test_report_date <= s.response_deadline) THEN 1 ELSE 0 END)'),
                ]
            )
            ->where('s.shipment_id = ?', $shipmentId);
        if (isset($testType) && !empty($testType)) {
            $sQuery = $sQuery->where("JSON_EXTRACT(sp.attributes, '$.dts_test_panel_type') = ?", $testType);
        }

        return $dbAdapter->fetchRow($sQuery);
    }
}
