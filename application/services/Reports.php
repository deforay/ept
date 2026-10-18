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
        $isDraftReports = $stage === 'reports';
        $enrolled = (int) $aRow['enrolled'];
        $responded = (int) $aRow['responded'];
        $evaluated = (int) $aRow['passed'] + (int) $aRow['failed'];

        // Shipment: code, with the PT survey on a second line when it differs. The
        // chevron opens the participation breakdown under the row.
        $shipment = '<strong>' . $esc($aRow['shipment_code']) . '</strong>';
        if ($enrolled > 0 && !$forParticipant) {
            $shipment = '<a href="javascript:void(0);" class="toggle-stats-row" data-sid="' . $sid . '" title="' . $esc($t->_('Breakdown')) . '">'
                . '<i class="icon-chevron-right" aria-hidden="true"></i></a> ' . $shipment;
        }
        $survey = [];
        if ((string) $aRow['distribution_code'] !== (string) $aRow['shipment_code']) {
            $survey[] = $esc($aRow['distribution_code']);
        }
        $survey[] = $esc(Pt_Commons_DateUtility::humanReadableDateFormat($aRow['shipment_date']));
        $shipment .= '<span class="cell-sub">' . implode(' &middot; ', $survey) . '</span>';

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

        return [$shipment, $esc($aRow['scheme_name']), $due, $responses, $pass, $status, $this->shipmentReportDownloads($aRow, $forParticipant)];
    }

    // Download buttons for one shipment row: the generated reports (PDF and ZIP
    // files) first, then the Excel exports.
    private function shipmentReportDownloads(array $aRow, bool $forParticipant): string
    {
        $t = $this->translator;
        $esc = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        // Each download is a small button with its own icon, so they can be told
        // apart at a glance. The title says what the file holds.
        $link = fn (string $label, string $title, string $icon, string $attrs) => '<a class="dl-btn" title="' . $esc($title) . '" ' . $attrs . '>'
            . '<i class="' . $icon . '" aria-hidden="true"></i> ' . $esc($label) . '</a>';
        $sid = base64_encode((string) $aRow['shipment_id']);
        $stage = $aRow['stage'];
        $isCancelled = $stage === 'cancelled';

        $reports = [];
        if (in_array($stage, ['reports', 'finalized'], true)) {
            if (!$forParticipant) {
                $viewHref = $stage === 'finalized'
                    ? '/reports/finalize/view-finalized-shipment/sid/' . $sid
                    : '/reports/distribution/shipment/sid/' . $sid;
                $reports[] = $link($t->_('View'), $t->_('Open the reports page for this shipment'), 'icon-eye-open', 'href="' . $viewHref . '"');
            }
            // Both buttons always show once reports exist for the shipment. A file
            // missing from this server shows as a disabled button that says so,
            // rather than the button silently disappearing.
            // Until the shipment is finalized the files are drafts, and say so.
            $isDraft = $stage === 'reports';
            $folder = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR;
            $files = [
                [$isDraft ? $t->_('Summary (draft)') : $t->_('Summary'), $t->_('Summary report (PDF)'), 'icon-file-text',
                    $folder . $aRow['shipment_code'] . DIRECTORY_SEPARATOR . $aRow['shipment_code'] . '-summary.pdf'],
                [$isDraft ? $t->_('All reports (draft)') : $t->_('All reports'), $t->_("Every participant's report in one ZIP file"), 'icon-archive',
                    $folder . $aRow['shipment_code'] . '.zip'],
            ];
            foreach ($files as [$label, $title, $icon, $path]) {
                $reports[] = file_exists($path)
                    ? $link($label, $title, $icon, ($isDraft ? 'data-draft="yes" ' : '') . 'target="_blank" href="' . $esc(Pt_Commons_SignedDownload::url($path)) . '"')
                    : '<span class="dl-btn is-missing" title="' . $esc($t->_('This file is not on this server. Generate the reports again to create it.')) . '">'
                        . '<i class="' . $icon . '" aria-hidden="true"></i> ' . $esc($label) . '</span>';
            }
        }
        if (!$forParticipant && $aRow['scheme_type'] === 'tb' && Application_Service_Common::getConfig('instance') === 'mtbept') {
            $tbFormPath = Application_Service_Shipments::resolveTbFormsZip($aRow['shipment_code']);
            if ($tbFormPath !== null) {
                $reports[] = $link($t->_('TB forms'), $t->_('Result forms for every participant (ZIP)'), 'icon-file-alt', 'target="_blank" href="/admin/shipment/download-tb/sid/' . (int) $aRow['shipment_id'] . '/file/' . base64_encode($tbFormPath) . '"');
            }
        }

        // The participant copy of the page shows the reports only.
        $exports = [];
        if (!$forParticipant && $stage !== 'notshipped' && !$isCancelled) {
            $exports[] = $link($t->_('Overview'), $t->_('Participant list, reported results and scores (Excel workbook)'), 'icon-table', 'href="javascript:void(0);" onclick="generateShipmentParticipantList(\'' . $sid . '\', \'' . $esc($aRow['scheme_type']) . '\')"');
            if ($stage !== 'finalized') {
                $exports[] = $link($t->_('Non-responders'), $t->_('Participants who have not responded yet (Excel)'), 'icon-warning-sign', 'href="javascript:void(0);" onclick="exportNotRespondedShipment(\'' . $sid . '\', \''
                    . base64_encode((string) $aRow['shipment_code']) . '\', \''
                    . base64_encode((string) Pt_Commons_DateUtility::humanReadableDateFormat($aRow['distribution_date'])) . '\')"');
            }
        }
        if (!$forParticipant && (int) $aRow['feedback_count'] > 0) {
            $exports[] = $link($t->_('Feedback'), $t->_("Participants' feedback answers (Excel)"), 'icon-comments', 'href="javascript:void(0);" onclick="downloadFeedbackReport(' . (int) $aRow['shipment_id'] . ')"');
        }

        $links = array_merge($reports, $exports);
        return $links ? '<div class="download-links">' . implode('', $links) . '</div>' : '<span class="cell-muted">&ndash;</span>';
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
     * Per-participant totals across shipments, charted by the detailed and participant
     * response reports.
     *
     * 'others' is the report's label for rounds the participant did not respond to. It used
     * to count an empty shipment_test_date, which is not the same question: the date is
     * cleared on purpose when a lab declares it could not test, and a submission can carry
     * results with the date left blank. It now asks response_status, matching
     * Shipments::getShipmentParticipationStats() and the non-participation mailer.
     */
    public function getParticipantDetailedReport($params)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        if (isset($params['reportType']) && $params['reportType'] == 'network') {
            $sQuery = $dbAdapter->select()->from(['n' => 'r_network_tiers'])
                ->joinLeft(['p' => 'participant'], 'p.network_tier=n.network_id', [])
                //->joinLeft(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array('participant_count'=> new Zend_Db_Expr("SUM(shipment_test_date = '') + SUM(shipment_test_date <> '')"), 'reported_count'=> new Zend_Db_Expr("SUM(shipment_test_date <> '')"), 'number_passed'=> new Zend_Db_Expr("SUM(final_result = 1)")))
                ->joinLeft(['shp' => 'shipment_participant_map'], 'shp.participant_id=p.participant_id', [])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=shp.shipment_id', ['response_deadline'])
                ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.participant_id=p.participant_id', ['others' => new Zend_Db_Expr("SUM(IFNULL(sp.response_status, 'noresponse') NOT IN ('responded', 'late', 'nottested'))"), 'excluded' => new Zend_Db_Expr("SUM(if(sp.is_excluded = 'yes', 1, 0))"), 'number_failed' => new Zend_Db_Expr("SUM(sp.final_result = 2 AND sp.shipment_test_date <= DATE(s.response_deadline) AND sp.is_excluded != 'yes')"), 'number_passed' => new Zend_Db_Expr("SUM(sp.final_result = 1 AND sp.shipment_test_date <= DATE(s.response_deadline) AND sp.is_excluded != 'yes')"), 'number_late' => new Zend_Db_Expr("SUM(sp.shipment_test_date > DATE(s.response_deadline) AND sp.is_excluded != 'yes')"), 'map_id'])
                ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', [])
                ->joinLeft(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', [])
                ->joinLeft(['rr' => 'r_results'], 'sp.final_result=rr.result_id', [])
                ->group('n.network_id')/* ->where("p.status = 'active'") */ ;
        }

        if (isset($params['reportType']) && $params['reportType'] == 'affiliation') {
            $sQuery = $dbAdapter->select()->from(['pa' => 'r_participant_affiliates'])
                ->joinLeft(['p' => 'participant'], 'p.affiliation=pa.affiliate', [])
                //->joinLeft(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array('participant_count'=> new Zend_Db_Expr("SUM(shipment_test_date = '') + SUM(shipment_test_date <> '')"), 'reported_count'=> new Zend_Db_Expr("SUM(shipment_test_date <> '')"), 'number_passed'=> new Zend_Db_Expr("SUM(final_result = 1)")))
                ->joinLeft(['shp' => 'shipment_participant_map'], 'shp.participant_id=p.participant_id', [])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=shp.shipment_id', ['response_deadline'])
                ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.participant_id=p.participant_id', ['others' => new Zend_Db_Expr("SUM(IFNULL(sp.response_status, 'noresponse') NOT IN ('responded', 'late', 'nottested'))"), 'excluded' => new Zend_Db_Expr("SUM(if(sp.is_excluded = 'yes', 1, 0))"), 'number_failed' => new Zend_Db_Expr("SUM(sp.final_result = 2 AND sp.shipment_test_date <= DATE(s.response_deadline) AND sp.is_excluded != 'yes')"), 'number_passed' => new Zend_Db_Expr("SUM(sp.final_result = 1 AND sp.shipment_test_date <= DATE(s.response_deadline) AND sp.is_excluded != 'yes')"), 'number_late' => new Zend_Db_Expr("SUM(sp.shipment_test_date > DATE(s.response_deadline) AND sp.is_excluded != 'yes')")])
                ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', [])
                ->joinLeft(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', [])
                ->joinLeft(['rr' => 'r_results'], 'sp.final_result=rr.result_id', [])
                ->group('pa.aff_id')/* ->where("p.status = 'active'") */ ;
        }
        if (isset($params['reportType']) && $params['reportType'] == 'region') {
            $sQuery = $dbAdapter->select()->from(['p' => 'participant'], ['p.region'])
                //->joinLeft(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array('participant_count'=> new Zend_Db_Expr("SUM(shipment_test_date = '') + SUM(shipment_test_date <> '')"), 'reported_count'=> new Zend_Db_Expr("SUM(shipment_test_date <> '')"), 'number_passed'=> new Zend_Db_Expr("SUM(final_result = 1)")))
                ->joinLeft(['shp' => 'shipment_participant_map'], 'shp.participant_id=p.participant_id', [])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=shp.shipment_id', ['response_deadline', 'shipment_code'])
                ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.participant_id=p.participant_id', ['others' => new Zend_Db_Expr("SUM(IFNULL(sp.response_status, 'noresponse') NOT IN ('responded', 'late', 'nottested'))"), 'excluded' => new Zend_Db_Expr("SUM(if(sp.is_excluded = 'yes', 1, 0))"), 'number_failed' => new Zend_Db_Expr("SUM(sp.final_result = 2 AND sp.shipment_test_date <= DATE(s.response_deadline) AND sp.is_excluded != 'yes')"), 'number_passed' => new Zend_Db_Expr("SUM(sp.final_result = 1 AND sp.shipment_test_date <= DATE(s.response_deadline) AND sp.is_excluded != 'yes')"), 'number_late' => new Zend_Db_Expr("SUM(sp.shipment_test_date > DATE(s.response_deadline) AND sp.is_excluded != 'yes')")])
                ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', [])
                ->joinLeft(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', [])
                ->joinLeft(['rr' => 'r_results'], 'sp.final_result=rr.result_id', [])
                ->group('p.region')->where('p.region IS NOT NULL')->where("p.region != ''")/* ->where("p.status = 'active'") */ ;
        }
        if (isset($params['reportType']) && $params['reportType'] == 'district') {
            $sQuery = $dbAdapter->select()->from(['p' => 'participant'], ['p.district'])
                ->joinLeft(['shp' => 'shipment_participant_map'], 'shp.participant_id=p.participant_id', [])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=shp.shipment_id', ['response_deadline', 'shipment_code'])
                ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.participant_id=p.participant_id', ['others' => new Zend_Db_Expr("SUM(IFNULL(sp.response_status, 'noresponse') NOT IN ('responded', 'late', 'nottested'))"), 'excluded' => new Zend_Db_Expr("SUM(if(sp.is_excluded = 'yes', 1, 0))"), 'number_failed' => new Zend_Db_Expr("SUM(sp.final_result = 2 AND sp.shipment_test_date <= DATE(s.response_deadline) AND sp.is_excluded != 'yes')"), 'number_passed' => new Zend_Db_Expr("SUM(sp.final_result = 1 AND sp.shipment_test_date <= DATE(s.response_deadline) AND sp.is_excluded != 'yes')"), 'number_late' => new Zend_Db_Expr("SUM(sp.shipment_test_date > DATE(s.response_deadline) AND sp.is_excluded != 'yes')")])
                ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', [])
                ->joinLeft(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', [])
                ->joinLeft(['rr' => 'r_results'], 'sp.final_result=rr.result_id', [])
                ->group('p.district')->where('p.district IS NOT NULL')->where("p.district != ''")/* ->where("p.status = 'active'") */ ;
        }
        if (isset($params['reportType']) && $params['reportType'] == 'enrolled-programs') {
            $sQuery = $dbAdapter->select()->from(['p' => 'participant'], [])
                //->joinLeft(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array('participant_count'=> new Zend_Db_Expr("SUM(shipment_test_date = '') + SUM(shipment_test_date <> '')"), 'reported_count'=> new Zend_Db_Expr("SUM(shipment_test_date <> '')"), 'number_passed'=> new Zend_Db_Expr("SUM(final_result = 1)")))
                ->joinLeft(['pe' => 'participant_enrolled_programs_map'], 'pe.participant_id=p.participant_id', [])
                ->joinLeft(['rep' => 'r_enrolled_programs'], 'rep.r_epid=pe.ep_id', ['rep.enrolled_programs'])
                ->joinLeft(['shp' => 'shipment_participant_map'], 'shp.participant_id=p.participant_id', [])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=shp.shipment_id', ['response_deadline'])
                ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.participant_id=p.participant_id', ['others' => new Zend_Db_Expr("SUM(IFNULL(sp.response_status, 'noresponse') NOT IN ('responded', 'late', 'nottested'))"), 'excluded' => new Zend_Db_Expr("SUM(if(sp.is_excluded = 'yes', 1, 0))"), 'number_failed' => new Zend_Db_Expr("SUM(sp.final_result = 2 AND sp.shipment_test_date <= DATE(s.response_deadline) AND sp.is_excluded != 'yes')"), 'number_passed' => new Zend_Db_Expr("SUM(sp.final_result = 1 AND sp.shipment_test_date <= DATE(s.response_deadline) AND sp.is_excluded != 'yes')"), 'number_late' => new Zend_Db_Expr("SUM(sp.shipment_test_date > DATE(s.response_deadline) AND sp.is_excluded != 'yes')")])
                ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', [])
                ->joinLeft(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', [])
                ->joinLeft(['rr' => 'r_results'], 'sp.final_result=rr.result_id', [])
                ->group('rep.r_epid');
        }
        $scopedDmId = Application_Service_Common::getScopedDmId();
        if (!empty($scopedDmId)) {
            $sQuery = $sQuery
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $scopedDmId);
        }
        if (isset($params['scheme']) && $params['scheme'] != '') {
            $sQuery = $sQuery->where('s.scheme_type = ?', $params['scheme']);
        }

        if (isset($params['startDate']) && $params['startDate'] != '' && isset($params['endDate']) && $params['endDate'] != '') {

            $sQuery = $sQuery->where('s.shipment_date >= ?', $this->common->isoDateFormat($params['startDate']));
            $sQuery = $sQuery->where('s.shipment_date <= ?', $this->common->isoDateFormat($params['endDate']));
        }
        $sQuery = $sQuery->order(new Zend_Db_Expr('(number_passed + number_failed + others + number_late + excluded) DESC'));

        return $dbAdapter->fetchAll($sQuery);
    }

    public function getAllParticipantDetailedReport($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        if (isset($parameters['reportType']) && $parameters['reportType'] == 'network') {
            $aColumns = ['s.shipment_code', 'sl.scheme_name', 'network_name', 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'state', 'district'];
        } elseif (isset($parameters['reportType']) && $parameters['reportType'] == 'affiliation') {
            $aColumns = ['s.shipment_code', 'sl.scheme_name', 'affiliate', 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'state', 'district'];
        } elseif (isset($parameters['reportType']) && $parameters['reportType'] == 'region') {
            $aColumns = ['s.shipment_code', 'sl.scheme_name', 'region', 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'state', 'district'];
        } elseif (isset($parameters['reportType']) && $parameters['reportType'] == 'enrolled-programs') {
            $aColumns = ['s.shipment_code', 'sl.scheme_name', 'enrolled_programs', 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'state', 'district'];
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
        //////////////

        if (isset($parameters['reportType']) && $parameters['reportType'] == 'network') {
            $sQuery = $dbAdapter->select()->from(['n' => 'r_network_tiers'])
                ->joinLeft(['p' => 'participant'], 'p.network_tier=n.network_id', ['p.state', 'p.district'])
                ->joinLeft(['shp' => 'shipment_participant_map'], 'shp.participant_id=p.participant_id', [])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=shp.shipment_id', ['shipment_code', 'response_deadline'])
                ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', ['scheme_name'])
                ->joinLeft(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_code', 'distribution_date'])
                ->group('n.network_id')->group('s.shipment_id')/* ->where("p.status = 'active'") */ ;
        } elseif (isset($parameters['reportType']) && $parameters['reportType'] == 'affiliation') {
            $sQuery = $dbAdapter->select()->from(['pa' => 'r_participant_affiliates'])
                ->joinLeft(['p' => 'participant'], 'p.affiliation=pa.affiliate', ['p.state', 'p.district'])
                ->joinLeft(['shp' => 'shipment_participant_map'], 'shp.participant_id=p.participant_id', [])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=shp.shipment_id', ['shipment_code', 'response_deadline'])
                ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', ['scheme_name'])
                ->joinLeft(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_code', 'distribution_date'])
                ->group('pa.aff_id')->group('s.shipment_id')/* ->where("p.status = 'active'") */ ;
        } elseif (isset($parameters['reportType']) && $parameters['reportType'] == 'region') {
            $sQuery = $dbAdapter->select()->from(['p' => 'participant'], ['p.region', 'p.state', 'p.district'])
                ->joinLeft(['shp' => 'shipment_participant_map'], 'shp.participant_id=p.participant_id', [])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=shp.shipment_id', ['shipment_code', 'response_deadline'])
                ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', ['scheme_name'])
                ->joinLeft(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_code', 'distribution_date'])
                ->group('p.region')->where('p.region IS NOT NULL')->where("p.region != ''")->group('s.shipment_id')/* ->where("p.status = 'active'") */ ;
        } elseif (isset($parameters['reportType']) && $parameters['reportType'] == 'enrolled-programs') {
            $sQuery = $dbAdapter->select()->from(['p' => 'participant'], ['p.state', 'p.district'])
                ->joinLeft(['pe' => 'participant_enrolled_programs_map'], 'pe.participant_id=p.participant_id', [])
                ->joinLeft(['rep' => 'r_enrolled_programs'], 'rep.r_epid=pe.ep_id', ['rep.enrolled_programs'])
                ->joinLeft(['shp' => 'shipment_participant_map'], 'shp.participant_id=p.participant_id', [])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=shp.shipment_id', ['shipment_code', 'response_deadline'])
                ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', ['scheme_name'])
                ->joinLeft(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_code', 'distribution_date'])
                ->group('rep.r_epid')->group('s.shipment_id')/* ->where("p.status = 'active'") */ ;
        }
        //        else{
        //          $sQuery = $dbAdapter->select()->from(array('s' => 'shipment'))
        //                ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id')
        //                ->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id')
        //                ->group('s.shipment_id');
        //        }
        ///////////

        $scopedDmId = Application_Service_Common::getScopedDmId();
        if (!empty($scopedDmId)) {
            $sQuery = $sQuery
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $scopedDmId);
        }
        if (isset($parameters['startDate']) && $parameters['startDate'] != '' && isset($parameters['endDate']) && $parameters['endDate'] != '') {

            $sQuery = $sQuery->where('s.shipment_date >= ?', $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where('s.shipment_date <= ?', $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['scheme']) && $parameters['scheme'] != '') {
            $sQuery = $sQuery->where('s.scheme_type = ?', $parameters['scheme']);
        }

        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }
        //   echo ($sQuery);
        $rResult = $this->getParticipantDetailedReport($parameters);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */

        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = sizeof($aResultTotal);

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
            $row[] = $aRow['shipment_code'];
            $row[] = ucwords($aRow['scheme_name']);
            if (isset($parameters['reportType']) && $parameters['reportType'] == 'network') {
                $row[] = $aRow['network_name'];
            } elseif (isset($parameters['reportType']) && $parameters['reportType'] == 'affiliation') {
                $row[] = $aRow['affiliate'];
            } elseif (isset($parameters['reportType']) && $parameters['reportType'] == 'region') {
                $row[] = $aRow['region'];
            } elseif (isset($parameters['reportType']) && $parameters['reportType'] == 'enrolled-programs') {
                $row[] = (isset($aRow['enrolled_programs']) && $aRow['enrolled_programs'] != '' && $aRow['enrolled_programs'] != null) ? $aRow['enrolled_programs'] : 'No Program';
            }

            $row[] = $aRow['distribution_code'];
            $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($aRow['distribution_date']);
            $row[] = ucwords($aRow['state']);
            $row[] = ucwords($aRow['district']);
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getParticipantTrendsReportData($parameters)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sQuery = $dbAdapter->select()
            ->from(['sp' => 'shipment_participant_map'], [])
            ->join(['p' => 'participant'], 'sp.participant_id = p.participant_id', [])
            ->join(['s' => 'shipment'], 'sp.shipment_id = s.shipment_id', ['shipment_id', 'shipment_code'])
            ->join(['sl' => 'scheme_list'], 's.scheme_type = sl.scheme_id', ['scheme_name']);

        if (isset($parameters['reportType']) && $parameters['reportType'] == 'network') {
            // Get total participants for each shipment code in each network

            $sQuery = $sQuery->join(['n' => 'r_network_tiers'], 'p.network_tier = n.network_id', ['network_name'])
                ->columns([
                    'network' => 'n.network_name',
                    'shipment_code' => 's.shipment_code',
                    'total_participants' => new Zend_Db_Expr('COUNT(DISTINCT sp.participant_id)'),
                ])
                ->group(['n.network_name', 's.shipment_code'])
                ->where('n.network_name IS NOT NULL')
                ->where("n.network_name != ''");
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == 'affiliation') {
            // Get total participants for each shipment code in each affiliation

            $sQuery = $sQuery->join(['pa' => 'r_participant_affiliates'], 'p.affiliation = pa.affiliate', ['affiliate'])
                ->columns([
                    'affiliation' => 'pa.affiliate',
                    'shipment_code' => 's.shipment_code',
                    'total_participants' => new Zend_Db_Expr('COUNT(DISTINCT sp.participant_id)'),
                ])
                ->group(['pa.affiliate', 's.shipment_code'])
                ->where('pa.affiliate IS NOT NULL')
                ->where("pa.affiliate != ''");
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == 'region') {
            // Get total participants for each shipment code in each region

            $sQuery = $sQuery->columns([
                'region' => 'p.region',
                'shipment_code' => 's.shipment_code',
                'total_participants' => new Zend_Db_Expr('COUNT(DISTINCT sp.participant_id)'),
            ])
                ->group(['p.region', 's.shipment_code'])
                ->where('p.region IS NOT NULL')
                ->where("p.region != ''");
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == 'district') {
            // Get total participants for each shipment code in each district

            $sQuery = $sQuery->columns([
                'district' => 'p.district',
                'shipment_code' => 's.shipment_code',
                'total_participants' => new Zend_Db_Expr('COUNT(DISTINCT sp.participant_id)'),
            ])
                ->group(['p.district', 's.shipment_code'])
                ->where('p.district IS NOT NULL')
                ->where("p.district != ''");
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == 'enrolled-programs') {
            // Get total participants for each shipment code in each enrolled program

            $sQuery = $sQuery->join(['pe' => 'participant_enrolled_programs_map'], 'pe.participant_id = p.participant_id', [])
                ->join(['rep' => 'r_enrolled_programs'], 'rep.r_epid = pe.ep_id', ['enrolled_programs'])
                ->columns([
                    'enrolled-programs' => 'rep.enrolled_programs',
                    'shipment_code' => 's.shipment_code',
                    'total_participants' => new Zend_Db_Expr('COUNT(DISTINCT sp.participant_id)'),
                ])
                ->group(['rep.r_epid', 's.shipment_code'])
                ->where('rep.enrolled_programs IS NOT NULL')
                ->where("rep.enrolled_programs != ''");
        }
        // Modified query: Get total participants for each shipment code in each region

        if (isset($parameters['scheme']) && $parameters['scheme'] != '') {
            $sQuery = $sQuery->where('s.scheme_type = ?', $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != '' && isset($parameters['endDate']) && $parameters['endDate'] != '') {
            $sQuery = $sQuery->where('s.shipment_date >= ?', $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where('s.shipment_date <= ?', $this->common->isoDateFormat($parameters['endDate']));
        }
        // die($sQuery);
        return $dbAdapter->fetchAll($sQuery);
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

    public function getParticipantPerformanceReport($parameters)
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

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1 && !empty($authNameSpace->ptccMappedCountries)) {
            $sQuery = $sQuery->where("p.country IN({$authNameSpace->ptccMappedCountries})");
        } elseif (isset($authNameSpace->mappedParticipants) && !empty($authNameSpace->mappedParticipants)) {
            $sQuery = $sQuery
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $authNameSpace->dm_id);
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

        if (isset($sOrder) && $sOrder != '') {
            $sQuery = $sQuery->order($sOrder);
        }

        $chartSession = new Zend_Session_Namespace('timelinessChart');
        $chartSession->timelinessChartQuery = $sQuery;

        $sQuerySession = new Zend_Session_Namespace('participantPerformanceExcel');
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

    public function getTestKitReport($params)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(['res' => 'response_result_dts'], ['totalTest' => new Zend_Db_Expr("CAST((COUNT('shipment_map_id')/s.number_of_samples) as UNSIGNED)")])
            ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.map_id=res.shipment_map_id', [])
            ->joinLeft(['p' => 'participant'], 'sp.participant_id=p.participant_id', [])
            ->joinLeft(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', []);
        if (isset($params['kitType']) && $params['kitType'] == 'testkit1') {
            $sQuery = $sQuery->joinLeft(['tn' => 'r_testkitnames'], 'tn.TestKitName_ID=res.test_kit_name_1', ['TestKit_Name', 'TestKitName_ID'])
                ->group('tn.TestKitName_ID');
        } elseif (isset($params['kitType']) && $params['kitType'] == 'testkit2') {
            $sQuery = $sQuery->joinLeft(['tn' => 'r_testkitnames'], 'tn.TestKitName_ID=res.test_kit_name_2', ['TestKit_Name', 'TestKitName_ID'])
                ->group('tn.TestKitName_ID');
        } elseif (isset($params['kitType']) && $params['kitType'] == 'testkit3') {
            $sQuery = $sQuery->joinLeft(['tn' => 'r_testkitnames'], 'tn.TestKitName_ID=res.test_kit_name_3', ['TestKit_Name', 'TestKitName_ID'])
                ->group('tn.TestKitName_ID');
        } else {
            $sQuery = $sQuery->joinLeft(['tn' => 'r_testkitnames'], 'tn.TestKitName_ID=res.test_kit_name_1 or tn.TestKitName_ID=res.test_kit_name_2 or tn.TestKitName_ID=res.test_kit_name_3', ['TestKit_Name', 'TestKitName_ID'])
                ->group('tn.TestKitName_ID');
        }
        if (isset($params['reportType']) && $params['reportType'] == 'network') {
            if (isset($params['networkValue']) && $params['networkValue'] != '') {
                $sQuery = $sQuery->where('p.network_tier = ?', $params['networkValue']);
            } else {
                $sQuery = $sQuery->joinLeft(['n' => 'r_network_tiers'], 'p.network_tier=n.network_id', []);
            }
        }

        if (isset($params['reportType']) && $params['reportType'] == 'affiliation') {
            if (isset($params['affiliateValue']) && $params['affiliateValue'] != '') {
                $iQuery = $dbAdapter->select()->from(['rpa' => 'r_participant_affiliates'])
                    ->where('rpa.aff_id=?', $params['affiliateValue']);
                $iResult = $dbAdapter->fetchRow($iQuery);
                $appliate = $iResult['affiliate'];
                $sQuery = $sQuery->where('p.affiliation = ' . $dbAdapter->quote($appliate) . ' OR p.affiliation = ' . $dbAdapter->quote($params['affiliateValue']));
            } else {
                $sQuery = $sQuery->joinLeft(['pa' => 'r_participant_affiliates'], 'p.affiliation=pa.affiliate', []);
            }
        }
        if (isset($params['reportType']) && $params['reportType'] == 'region') {
            if (isset($params['regionValue']) && $params['regionValue'] != '') {
                $sQuery = $sQuery->where('p.region= ?', $params['regionValue']);
            } else {
                $sQuery = $sQuery->where('p.region IS NOT NULL')->where("p.region != ''");
            }
        }
        if (isset($params['reportType']) && $params['reportType'] == 'enrolled-programs') {
            if (isset($params['enrolledProgramsValue']) && $params['enrolledProgramsValue'] != '') {
                $sQuery = $sQuery->joinLeft(['pe' => 'participant_enrolled_programs_map'], 'pe.participant_id=p.participant_id', [])
                    ->joinLeft(['rep' => 'r_enrolled_programs'], 'rep.r_epid=pe.ep_id', ['rep.enrolled_programs'])
                    ->where('rep.r_epid= ?', $params['enrolledProgramsValue']);
            } else {
                $sQuery = $sQuery->joinLeft(['pe' => 'participant_enrolled_programs_map'], 'pe.participant_id=p.participant_id', [])
                    ->joinLeft(['rep' => 'r_enrolled_programs'], 'rep.r_epid=pe.ep_id', ['rep.enrolled_programs']);
            }
        }

        if (isset($params['startDate']) && $params['startDate'] != '' && isset($params['endDate']) && $params['endDate'] != '') {

            $sQuery = $sQuery->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($params['startDate']));
            $sQuery = $sQuery->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($params['endDate']));
        }
        $sQuery = $sQuery->where('tn.TestKit_Name IS NOT NULL');
        return $dbAdapter->fetchAll($sQuery);
    }

    public function getTestKitDetailedReport($parameters)
    {
        $searchColumns = [
            'tn.TestKit_Name',
            'totalTest',
        ];
        $orderColumns = [
            'tn.TestKit_Name',
            'totalTest',
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
        $sQuery = $dbAdapter->select()->from(['res' => 'response_result_dts'], ['totalTest' => new Zend_Db_Expr("CAST((COUNT('shipment_map_id')/s.number_of_samples) as UNSIGNED)")])
            ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.map_id=res.shipment_map_id', [])
            ->joinLeft(['p' => 'participant'], 'sp.participant_id=p.participant_id', ['p.lab_name', 'participantName' => new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameGroupConcatExpr('p'))])
            ->joinLeft(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', []);
        //  ->group("p.participant_id");

        if (isset($parameters['kitType']) && $parameters['kitType'] == 'testkit1') {
            $sQuery = $sQuery->joinLeft(['tn' => 'r_testkitnames'], 'tn.TestKitName_ID=res.test_kit_name_1', ['tn.TestKit_Name', 'TestKitName_ID'])
                ->group('tn.TestKitName_ID');
        } elseif (isset($parameters['kitType']) && $parameters['kitType'] == 'testkit2') {
            $sQuery = $sQuery->joinLeft(['tn' => 'r_testkitnames'], 'tn.TestKitName_ID=res.test_kit_name_2', ['tn.TestKit_Name', 'TestKitName_ID'])
                ->group('tn.TestKitName_ID');
        } elseif (isset($parameters['kitType']) && $parameters['kitType'] == 'testkit3') {
            $sQuery = $sQuery->joinLeft(['tn' => 'r_testkitnames'], 'tn.TestKitName_ID=res.test_kit_name_3', ['tn.TestKit_Name', 'TestKitName_ID'])
                ->group('tn.TestKitName_ID');
        } else {
            $sQuery = $sQuery->joinLeft(['tn' => 'r_testkitnames'], 'tn.TestKitName_ID=res.test_kit_name_1 or tn.TestKitName_ID=res.test_kit_name_2 or tn.TestKitName_ID=res.test_kit_name_3', ['TestKit_Name', 'TestKitName_ID'])
                ->group('tn.TestKitName_ID');
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == 'network') {
            if (isset($parameters['networkValue']) && $parameters['networkValue'] != '') {
                $sQuery = $sQuery->where('p.network_tier = ?', $parameters['networkValue']);
            } else {
                $sQuery = $sQuery->joinLeft(['n' => 'r_network_tiers'], 'p.network_tier=n.network_id', []);
            }
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == 'affiliation') {
            if (isset($parameters['affiliateValue']) && $parameters['affiliateValue'] != '') {
                $iQuery = $dbAdapter->select()->from(['rpa' => 'r_participant_affiliates'])
                    ->where('rpa.aff_id=?', $parameters['affiliateValue']);
                $iResult = $dbAdapter->fetchRow($iQuery);
                $appliate = $iResult['affiliate'];
                $sQuery = $sQuery->where('p.affiliation = ' . $dbAdapter->quote($appliate) . ' OR p.affiliation = ' . $dbAdapter->quote($parameters['affiliateValue']));
            } else {
                $sQuery = $sQuery->joinLeft(['pa' => 'r_participant_affiliates'], 'p.affiliation=pa.affiliate', []);
            }
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == 'enrolled-programs') {
            if (isset($parameters['enrolledProgramsValue']) && $parameters['enrolledProgramsValue'] != '') {
                $sQuery = $sQuery->joinLeft(['pe' => 'participant_enrolled_programs_map'], 'pe.participant_id=p.participant_id', [])
                    ->joinLeft(['rep' => 'r_enrolled_programs'], 'rep.r_epid=pe.ep_id', ['rep.enrolled_programs'])
                    ->where('rep.r_epid= ?', $parameters['enrolledProgramsValue']);
            } else {
                $sQuery = $sQuery->joinLeft(['pe' => 'participant_enrolled_programs_map'], 'pe.participant_id=p.participant_id', [])
                    ->joinLeft(['rep' => 'r_enrolled_programs'], 'rep.r_epid=pe.ep_id', ['rep.enrolled_programs']);
            }
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == 'region') {
            if (isset($parameters['regionValue']) && $parameters['regionValue'] != '') {
                $sQuery = $sQuery->where('p.region= ?', $parameters['regionValue']);
            } else {
                $sQuery = $sQuery->where('p.region IS NOT NULL')->where("p.region != ''");
            }
        }
        if (isset($parameters['startDate']) && $parameters['startDate'] != '' && isset($parameters['endDate']) && $parameters['endDate'] != '') {

            $sQuery = $sQuery->where('s.shipment_date >= ?', $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where('s.shipment_date <= ?', $this->common->isoDateFormat($parameters['endDate']));
        }
        $sQuery = $sQuery->where('tn.TestKit_Name IS NOT NULL');

        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->having($sWhere);
        }
        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }
        $sQuerySession = new Zend_Session_Namespace('TestkitActionsExcel');
        $sQuerySession->testkitActionsQuery = $sQuery;

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

        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = sizeof($aResultTotal);

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
            $row['DT_RowId'] = 'testkitId' . $aRow['TestKitName_ID'];
            //  $row[] = $aRow['participantName'];
            $row[] = "<a href='javascript:void(0);' onclick='participantReport(\"" . $aRow['TestKitName_ID'] . '","' . $aRow['TestKit_Name'] . "\")'>" . stripslashes($aRow['TestKit_Name']) . '</a>';
            $row[] = $aRow['totalTest'];
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
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

    public function getCorrectiveActionReport($parameters)
    {

        $searchColumns = ['total_corrective', 'ca.corrective_action'];
        $orderColumns = [new Zend_Db_Expr('count("cam.corrective_action_id")'), 'ca.corrective_action'];

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

        $totalQuery = $dbAdapter->select()->from(['s' => 'shipment'], ['average_score'])
            ->join(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', [
                'total_shipped' => new Zend_Db_Expr('count("sp.map_id")'),
                'total_responses' => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
                'valid_responses' => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
            ]);

        if (isset($parameters['scheme']) && $parameters['scheme'] != '') {
            $totalQuery = $totalQuery->where('s.scheme_type = ?', $parameters['scheme']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != '' && isset($parameters['endDate']) && $parameters['endDate'] != '') {

            $totalQuery = $totalQuery->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($parameters['startDate']));
            $totalQuery = $totalQuery->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != '') {
            $totalQuery = $totalQuery->where('s.shipment_id = ?', $parameters['shipmentId']);
        }
        //die($totalQuery);
        $totalResult = $dbAdapter->fetchRow($totalQuery);

        $totalShipped = ($totalResult['total_shipped']);
        $totalResp = ($totalResult['total_responses']);
        $validResp = ($totalResult['valid_responses']);
        $avgScore = ($totalResult['average_score']);

        $sQuery = $dbAdapter->select()->from(['s' => 'shipment'], [])
            ->join(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', [])
            ->join(['cam' => 'dts_shipment_corrective_action_map'], 'sp.map_id=cam.shipment_map_id', ['total_corrective' => new Zend_Db_Expr("count('corrective_action_id')")])
            ->join(['ca' => 'r_dts_corrective_actions'], 'cam.corrective_action_id=ca.action_id', ['action_id', 'corrective_action'])
            ->where("sp.is_excluded = 'no'")
            ->group(['ca.action_id']);

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

        $sQuerySession = new Zend_Session_Namespace('CorrectiveActionsExcel');
        $sQuerySession->correctiveActionsQuery = $sQuery;

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
        //$sQuery = $dbAdapter->select()->from(array('s'=>'shipment'), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));

        $sQuery = $dbAdapter->select()->from(['s' => 'shipment'], new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"))
            ->join(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', [])
            ->join(['cam' => 'dts_shipment_corrective_action_map'], 'sp.map_id=cam.shipment_map_id', [])
            ->join(['ca' => 'r_dts_corrective_actions'], 'cam.corrective_action_id=ca.action_id', [])
            ->where("sp.is_excluded = 'no'")
            ->group(['ca.action_id']);

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

        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

        /*
         * Output
         */

        $output = [
            'sEcho' => intval($parameters['sEcho']),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
            'totalShipped' => (int) $totalShipped,
            'totalResponses' => (int) $totalResp,
            'validResponses' => (int) $validResp,
            'averageScore' => round((float) $avgScore, 2),
        ];

        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = $aRow['corrective_action'];
            $row[] = $aRow['total_corrective'];

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
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

    public function exportParticipantTrendsReport($params)
    {

        $headings = [
            $this->translator->_('Scheme'),
            $this->translator->_('Shipment Date'),
            $this->translator->_('Shipment Code'),
            $this->translator->_('No. of Shipments'),
            $this->translator->_('No. of Responses'),
            $this->translator->_('No. of Valid Responses'),
            $this->translator->_('No. of Passed Responses'),
            $this->translator->_('Pass Percentage'),
        ];
        try {
            $excel = new Spreadsheet();

            $output = [];
            $sheet = $excel->getActiveSheet();

            $colNo = 0;
            $sheet->mergeCells('A1:I1');
            $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 1)
                ->setValueExplicit(html_entity_decode('Participant Performance Overview Report', ENT_QUOTES, 'UTF-8'));
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

            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 1, null, null)->getFont()->setBold(true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 2, null, null)->getFont()->setBold(true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 3, null, null)->getFont()->setBold(true);

            foreach ($headings as $value) {
                $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . 5)
                    ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . 5, null, null)->getFont()->setBold(true);
                $colNo++;
            }

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuerySession = new Zend_Session_Namespace('ParticipantTrendsExcel');
            $rResult = $db->fetchAll($sQuerySession->participantQuery);
            foreach ($rResult as $aRow) {

                $row = [];
                $row[] = $aRow['scheme_name'];
                $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($aRow['shipment_date']);
                $row[] = $aRow['shipment_code'];
                $row[] = $aRow['total_shipped'];
                $row[] = $aRow['total_responses'];
                $row[] = $aRow['valid_responses'];
                $row[] = $aRow['total_passed'];
                $row[] = round($aRow['pass_percentage'], 2);
                //$row[] = round($aRow['average_score'], 2);
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
            $filename = 'participant-trends-report-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            $sQuerySession->participantQuery = '';
            Pt_Commons_LoggerUtility::logError('Failed to generate participant trends report (Excel): ' . $exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);

            return '';
        }
    }

    public function exportCorrectiveActionsReport($params)
    {

        $headings = [
            $this->translator->_('Corrective Action'),
            $this->translator->_('No. of Responses having this corrective action'),
        ];
        try {
            $excel = new Spreadsheet();
            $params['dateStartDate'] = $this->common->isoDateFormat($params['dateStartDate']);
            $params['dateEndDate'] = $this->common->isoDateFormat($params['dateEndDate']);

            $output = [];
            $sheet = $excel->getActiveSheet();

            $colNo = 0;
            $sheet->mergeCells('A1:I1');
            $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 1)
                ->setValueExplicit(html_entity_decode('Participant Corrective Action Overview', ENT_QUOTES, 'UTF-8'));
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

            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 1, null, null)->getFont()->setBold(true);

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $totalQuery = $db->select()->from(['s' => 'shipment'], ['average_score'])
                ->join(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', [
                    'total_shipped' => new Zend_Db_Expr('count("sp.map_id")'),
                    'total_responses' => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
                    'valid_responses' => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
                ]);

            if (isset($params['scheme']) && $params['scheme'] != '') {
                $totalQuery = $totalQuery->where('s.scheme_type = ?', $params['scheme']);
            }

            if (isset($params['dateStartDate']) && $params['dateStartDate'] != '' && isset($params['dateEndDate']) && $params['dateEndDate'] != '') {
                $totalQuery = $totalQuery->where('DATE(s.shipment_date) >= ?', $params['dateStartDate']);
                $totalQuery = $totalQuery->where('DATE(s.shipment_date) <= ?', $params['dateEndDate']);
            }

            if (isset($params['shipmentId']) && $params['shipmentId'] != '') {
                $totalQuery = $totalQuery->where('s.shipment_id = ?', $params['shipmentId']);
            }
            //die($totalQuery);
            $totalResult = $db->fetchRow($totalQuery);

            $totalShipped = ($totalResult['total_shipped']);
            $totalResp = ($totalResult['total_responses']);
            $validResp = ($totalResult['valid_responses']);

            $sheet->mergeCells('A4:B4');
            $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 4)
                ->setValueExplicit(html_entity_decode('Total shipped :' . $totalShipped, ENT_QUOTES, 'UTF-8'));
            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 4, null, null)->getFont()->setBold(true);
            $sheet->mergeCells('A5:B5');
            $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 5)
                ->setValueExplicit(html_entity_decode('Total number of responses :' . $totalResp, ENT_QUOTES, 'UTF-8'));
            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 5, null, null)->getFont()->setBold(true);
            $sheet->mergeCells('A6:B6');
            $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 6)
                ->setValueExplicit(html_entity_decode('Total number of valid responses :' . $validResp, ENT_QUOTES, 'UTF-8'));
            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 6, null, null)->getFont()->setBold(true);
            $sheet->mergeCells('A7:B7');
            //$sheet->getCell(Coordinate::stringFromColumnIndex(0) . 7)
            //->setValueExplicit(html_entity_decode('Average score :' . $avgScore, ENT_QUOTES, 'UTF-8'));
            //$sheet->getStyle(Coordinate::stringFromColumnIndex(0) . 7)->getFont()->setBold(true);

            foreach ($headings as $value) {
                $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . 9)
                    ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . 9, null, null)->getFont()->setBold(true);
                $colNo++;
            }

            $sQuerySession = new Zend_Session_Namespace('CorrectiveActionsExcel');
            $rResult = $db->fetchAll($sQuerySession->correctiveActionsQuery);

            if (count($rResult) > 0) {
                foreach ($rResult as $aRow) {
                    $row = [];
                    $row[] = $aRow['corrective_action'];
                    $row[] = $aRow['total_corrective'];
                    $output[] = $row;
                }
            } else {
                $row = [];
                $row[] = 'No result found';
                $output[] = $row;
            }

            foreach ($output as $rowNo => $rowData) {
                $colNo = 0;
                foreach ($rowData as $value) {
                    if (!isset($value)) {
                        $value = '';
                    }
                    $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 10)
                        ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(100);
                        $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 10, null, null)->getAlignment()->setWrapText(true);
                    }
                    $colNo++;
                }
            }

            if (!file_exists($this->tempUploadDirectory) && !is_dir($this->tempUploadDirectory)) {
                mkdir($this->tempUploadDirectory);
            }

            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'Participant-Corrective-Actions-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            $sQuerySession = new Zend_Session_Namespace('CorrectiveActionsExcel');
            $sQuerySession->correctiveActionsQuery = '';
            Pt_Commons_LoggerUtility::logError('Failed to generate participant corrective actions report (Excel): ' . $exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);

            return '';
        }
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

    public function exportParticipantTrendsReportInPdf()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuerySession = new Zend_Session_Namespace('ParticipantTrendsExcel');
        return $db->fetchAll($sQuerySession->participantQuery);
    }

    public function exportCorrectiveActionsReportInPdf($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $totalQuery = $db->select()->from(['s' => 'shipment'], [])
            ->join(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', [
                'total_shipped' => new Zend_Db_Expr('count("sp.map_id")'),
                'total_responses' => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
                'valid_responses' => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
                'average_score' => new Zend_Db_Expr("((SUM(Case When sp.is_excluded='yes' Then 0 Else sp.shipment_score End)+SUM(Case When sp.is_excluded='yes' Then 0 Else sp.documentation_score End))/(SUM(final_result = 1) + SUM(final_result = 2)))"),
            ]);

        if (isset($params['scheme']) && $params['scheme'] != '') {
            $totalQuery = $totalQuery->where('s.scheme_type = ?', $params['scheme']);
        }

        if (isset($params['dateStartDate']) && $params['dateStartDate'] != '' && isset($params['dateEndDate']) && $params['dateEndDate'] != '') {

            $totalQuery = $totalQuery->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($params['dateStartDate']));
            $totalQuery = $totalQuery->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($params['dateEndDate']));
        }

        if (isset($params['shipmentId']) && $params['shipmentId'] != '') {
            $totalQuery = $totalQuery->where('s.shipment_id = ?', $params['shipmentId']);
        }
        //die($totalQuery);
        $totalResult = $db->fetchRow($totalQuery);

        $sQuerySession = new Zend_Session_Namespace('CorrectiveActionsExcel');
        $rResult = $db->fetchAll($sQuerySession->correctiveActionsQuery);

        return ['countCorrectiveAction' => $totalResult, 'correctiveAction' => $rResult];
    }

    public function exportShipmentsReportInPdf()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuerySession = new Zend_Session_Namespace('shipmentExportExcel');
        return $db->fetchAll($sQuerySession->shipmentExportQuery);
    }

    public function getParticipantTrendsRegionWiseReport($parameters)
    {
        $searchColumns = [
            'p.region',
            'total_responses',
            'valid_responses',
            'total_passed',
            'pass_percentage',
            'average_score',
        ];
        $orderColumns = [
            'p.region',
            new Zend_Db_Expr('count("sp.map_id")'),
            new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
            new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
            new Zend_Db_Expr('SUM(final_result = 1)'),
            new Zend_Db_Expr('((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100'),
            'average_score',
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
            ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', ["DATE_FORMAT(s.shipment_date,'%d-%b-%Y')", 'total_shipped' => new Zend_Db_Expr('count("sp.map_id")'), 'total_responses' => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"), 'valid_responses' => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"), 'total_passed' => new Zend_Db_Expr('(SUM(final_result = 1))'), 'pass_percentage' => new Zend_Db_Expr('((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100'), 'average_score' => new Zend_Db_Expr("((SUM(Case When sp.is_excluded='yes' Then 0 Else sp.shipment_score End)+SUM(Case When sp.is_excluded='yes' Then 0 Else sp.documentation_score End))/(SUM(final_result = 1) + SUM(final_result = 2)))")])
            ->joinLeft(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['region'])
            ->joinLeft(['rr' => 'r_results'], 'sp.final_result=rr.result_id')
            ->group(['p.region']);

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

        $sQuerySession = new Zend_Session_Namespace('ParticipantTrendsExcel');
        $sQuerySession->participantRegionQuery = $sQuery;

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
        //$sQuery = $dbAdapter->select()->from(array('s'=>'shipment'), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));

        $sQuery = $dbAdapter->select()->from(['s' => 'shipment'], new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"))
            ->join(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id')
            ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', [])
            ->joinLeft(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['region'])
            ->joinLeft(['rr' => 'r_results'], 'sp.final_result=rr.result_id')
            ->group(['p.region']);
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

        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

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

            $row[] = $aRow['region'];
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

    public function getParticipantPerformanceRegionWiseReport($parameters)
    {
        $searchColumns = [
            'p.region',
            'total_responses',
            'valid_responses',
            'total_passed',
            'pass_percentage',
            'average_score',
        ];
        $orderColumns = [
            'p.region',
            new Zend_Db_Expr('count("sp.map_id")'),
            new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"),
            new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"),
            new Zend_Db_Expr('SUM(final_result = 1)'),
            new Zend_Db_Expr('((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100'),
            'average_score',
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
            ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', ["DATE_FORMAT(s.shipment_date,'%d-%b-%Y')", 'total_shipped' => new Zend_Db_Expr('count("sp.map_id")'), 'total_responses' => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')"), 'valid_responses' => new Zend_Db_Expr("(SUM(sp.shipment_test_date not like '0000-00-00') - SUM(is_excluded = 'yes'))"), 'total_passed' => new Zend_Db_Expr('(SUM(final_result = 1))'), 'pass_percentage' => new Zend_Db_Expr('((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100'), 'average_score' => new Zend_Db_Expr("((SUM(Case When sp.is_excluded='yes' Then 0 Else sp.shipment_score End)+SUM(Case When sp.is_excluded='yes' Then 0 Else sp.documentation_score End))/(SUM(final_result = 1) + SUM(final_result = 2)))")])
            ->joinLeft(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['region'])
            ->joinLeft(['rr' => 'r_results'], 'sp.final_result=rr.result_id')
            ->group(['p.region']);

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1 && !empty($authNameSpace->ptccMappedCountries)) {
            $sQuery = $sQuery->where('p.country IN(' . $authNameSpace->ptccMappedCountries . ')');
        } elseif (isset($authNameSpace->mappedParticipants) && !empty($authNameSpace->mappedParticipants)) {
            $sQuery = $sQuery
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $authNameSpace->dm_id);
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

        if (isset($sOrder) && $sOrder != '') {
            $sQuery = $sQuery->order($sOrder);
        }

        $sQuerySession = new Zend_Session_Namespace('participantPerformanceExcel');
        $sQuerySession->participantRegionQuery = $sQuery;

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
        //$sQuery = $dbAdapter->select()->from(array('s'=>'shipment'), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));

        $sQuery = $dbAdapter->select()->from(['s' => 'shipment'], new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"))
            ->join(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id')
            ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.shipment_id=s.shipment_id', [])
            ->joinLeft(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['region'])
            ->joinLeft(['rr' => 'r_results'], 'sp.final_result=rr.result_id')
            ->group(['p.region']);
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

        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

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

            $row[] = $aRow['region'];
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
    public function getChartInfo($parameters)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(['s' => 'shipment'], ['shipment_code'])
            ->join(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', [''])
            ->joinLeft(
                ['sp' => 'shipment_participant_map'],
                'sp.shipment_id=s.shipment_id',
                [
                    'shipmentDate' => new Zend_Db_Expr("DATE_FORMAT(s.shipment_date,'%d-%b-%Y')"),
                    'total_shipped' => new Zend_Db_Expr('count("sp.map_id")'),
                    'beforeDueDate' => new Zend_Db_Expr('SUM(sp.shipment_test_report_date <= s.response_deadline)'),
                    'afterDueDate' => new Zend_Db_Expr('SUM(sp.shipment_test_report_date > s.response_deadline)'),
                    'pass_percentage' => new Zend_Db_Expr('((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100'),
                ]
            )
            ->joinLeft(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['region'])
            ->joinLeft(['rr' => 'r_results'], 'sp.final_result=rr.result_id', [''])
            ->where('s.cancelled_at IS NULL');

        if (isset($parameters['scheme']) && $parameters['scheme'] != '') {
            $sQuery = $sQuery->where('s.scheme_type = ?', $parameters['scheme']);
        }
        $scopedDmId = Application_Service_Common::getScopedDmId();
        if (!empty($scopedDmId)) {
            $sQuery = $sQuery
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $scopedDmId);
        }
        if (isset($parameters['startDate']) && $parameters['startDate'] != '' && isset($parameters['endDate']) && $parameters['endDate'] != '') {

            $sQuery = $sQuery->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != '') {
            $sQuery = $sQuery->where('s.shipment_id = ?', $parameters['shipmentId']);
        }
        return $dbAdapter->fetchRow($sQuery);
    }

    public function getAberrantChartInfo($parameters)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(['s' => 'shipment'], ['shipment_code'])
            ->join(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', [''])
            ->joinLeft(
                ['sp' => 'shipment_participant_map'],
                'sp.shipment_id=s.shipment_id',
                [
                    'shipmentDate' => new Zend_Db_Expr("DATE_FORMAT(s.shipment_date,'%d-%b-%Y')"),
                    'total_shipped' => new Zend_Db_Expr('count("sp.map_id")'),
                    'fail_percentage' => new Zend_Db_Expr('((SUM(final_result = 2))/(SUM(final_result = 2) + SUM(final_result = 1)))*100'),
                    'pass_percentage' => new Zend_Db_Expr('((SUM(final_result = 1))/(SUM(final_result = 1) + SUM(final_result = 2)))*100'),
                ]
            );
        $scopedDmId = Application_Service_Common::getScopedDmId();
        if (!empty($scopedDmId)) {
            $sQuery = $sQuery
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=sp.participant_id', [])
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

        $rResult = $dbAdapter->fetchRow($sQuery);
        $rResult['failed'] = $this->getFaileParticipants($parameters);
        return $rResult;
    }

    public function getFaileParticipants($parameters)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(['s' => 'shipment'], ['shipment_code'])
            ->join(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', [''])
            ->joinLeft(
                ['sp' => 'shipment_participant_map'],
                'sp.shipment_id=s.shipment_id',
                [
                    'shipmentDate' => new Zend_Db_Expr("DATE_FORMAT(s.shipment_date,'%d-%b-%Y')"),
                    'total_shipped' => new Zend_Db_Expr('count("sp.map_id")'),
                    'network_id' => new Zend_Db_Expr('count("p.network_tier")'),
                    'beforeDueDate' => new Zend_Db_Expr('SUM(sp.shipment_test_report_date <= s.response_deadline)'),
                    'afterDueDate' => new Zend_Db_Expr('SUM(sp.shipment_test_report_date > s.response_deadline)'),
                    'fail_percentage' => new Zend_Db_Expr('((SUM(final_result = 2))/(SUM(final_result = 2) + SUM(final_result = 1)))*100'),
                ]
            )
            ->joinLeft(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['participant_id', 'institute_name', 'region'])
            ->joinLeft(['rn' => 'r_network_tiers'], 'p.network_tier=rn.network_id', ['network_name'])
            ->where('final_result = 2')
            ->group(['p.network_tier']);

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

        $rResult = $dbAdapter->fetchAll($sQuery);
        $row = [];
        foreach ($rResult as $key => $aRow) {
            $row['network_name'][$key] = '"' . $aRow['network_name'] . '"';
            $row['totalShipped'][$key] = '"N=' . $aRow['total_shipped'] . '"';
            $row['beforeDueDate'][$key] = round($aRow['beforeDueDate'], 2);
            $row['afterDueDate'][$key] = round($aRow['afterDueDate'], 2);
            $row['fail_percentage'][$key] = round($aRow['fail_percentage'], 2);
            $row['network_id'][$key] = round($aRow['network_id'], 2);
        }
        return $row;
    }
    public function exportParticipantTrendsRegionReport($params)
    {
        $headings = [
            $this->translator->_('Region'),
            $this->translator->_('No. of Shipments'),
            $this->translator->_('No. of Responses'),
            $this->translator->_('No. of Valid Responses'),
            $this->translator->_('No. of Passed Responses'),
            $this->translator->_('Pass %'),
        ];

        $sQuerySession = new Zend_Session_Namespace('ParticipantTrendsExcel');
        try {
            $excel = new Spreadsheet();

            $output = [];
            $sheet = $excel->getActiveSheet();
            $colNo = 0;
            $sheet->mergeCells('A1:I1');
            $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 1)
                ->setValueExplicit(html_entity_decode('Region Wise Participant Performance Report ', ENT_QUOTES, 'UTF-8'));

            $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 2)
                ->setValueExplicit(html_entity_decode('Scheme', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell(Coordinate::stringFromColumnIndex(2) . 2)
                ->setValueExplicit(html_entity_decode($params['selectedScheme'], ENT_QUOTES, 'UTF-8'));

            $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 3)
                ->setValueExplicit(html_entity_decode('Shipment Date', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell(Coordinate::stringFromColumnIndex(2) . 3)
                ->setValueExplicit(html_entity_decode($params['selectedDate'], ENT_QUOTES, 'UTF-8'));

            $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 4)
                ->setValueExplicit(html_entity_decode('Shipment Code', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell(Coordinate::stringFromColumnIndex(2) . 4)
                ->setValueExplicit(html_entity_decode($params['selectedCode'], ENT_QUOTES, 'UTF-8'));

            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 1, null, null)->getFont()->setBold(true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 2, null, null)->getFont()->setBold(true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 3, null, null)->getFont()->setBold(true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 4, null, null)->getFont()->setBold(true);

            foreach ($headings as $value) {
                $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . 6)
                    ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . 6, null, null)->getFont()->setBold(true);
                $colNo++;
            }

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $rResult = $db->fetchAll($sQuerySession->participantRegionQuery);
            foreach ($rResult as $aRow) {
                $row = [];
                $row[] = $aRow['region'];
                $row[] = $aRow['total_shipped'];
                $row[] = $aRow['total_responses'];
                $row[] = $aRow['valid_responses'];
                $row[] = $aRow['total_passed'];
                $row[] = round($aRow['pass_percentage'], 2);
                //$row[] = round($aRow['average_score'], 2);
                $output[] = $row;
            }

            foreach ($output as $rowNo => $rowData) {
                $colNo = 0;
                foreach ($rowData as $value) {
                    if (!isset($value)) {
                        $value = '';
                    }
                    $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 7)
                        ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(150);
                        $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 7, null, null)->getAlignment()->setWrapText(true);
                    }
                    $colNo++;
                }
            }

            if (!file_exists($this->tempUploadDirectory) && !is_dir($this->tempUploadDirectory)) {
                mkdir($this->tempUploadDirectory);
            }

            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'participant-performance-region-wise' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            $sQuerySession->participantRegionQuery = '';
            Pt_Commons_LoggerUtility::logError('Failed to generate region-wise participant performance report (Excel): ' . $exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);

            return '';
        }
    }

    public function exportParticipantPerformanceRegionReport($params)
    {
        $headings = [
            $this->translator->_('Region'),
            $this->translator->_('No. of Shipments'),
            $this->translator->_('No. of Responses'),
            $this->translator->_('No. of Valid Responses'),
            $this->translator->_('No. of Passed Responses'),
            $this->translator->_('Pass %'),
        ];
        try {
            $excel = new Spreadsheet();

            $output = [];
            $sheet = $excel->getActiveSheet();
            $colNo = 0;
            $sheet->mergeCells('A1:I1');
            $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 1)
                ->setValueExplicit(html_entity_decode('Region Wise Participant Performance Report ', ENT_QUOTES, 'UTF-8'));

            $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 2)
                ->setValueExplicit(html_entity_decode('Scheme', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell(Coordinate::stringFromColumnIndex(2) . 2)
                ->setValueExplicit(html_entity_decode($params['selectedScheme'], ENT_QUOTES, 'UTF-8'));

            $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 3)
                ->setValueExplicit(html_entity_decode('Shipment Date', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell(Coordinate::stringFromColumnIndex(2) . 3)
                ->setValueExplicit(html_entity_decode($params['selectedDate'], ENT_QUOTES, 'UTF-8'));

            $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 4)
                ->setValueExplicit(html_entity_decode('Shipment Code', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell(Coordinate::stringFromColumnIndex(2) . 4)
                ->setValueExplicit(html_entity_decode($params['selectedCode'], ENT_QUOTES, 'UTF-8'));

            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 1, null, null)->getFont()->setBold(true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 2, null, null)->getFont()->setBold(true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 3, null, null)->getFont()->setBold(true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 4, null, null)->getFont()->setBold(true);

            foreach ($headings as $value) {
                $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . 6)
                    ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . 6, null, null)->getFont()->setBold(true);
                $colNo++;
            }

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuerySession = new Zend_Session_Namespace('participantPerformanceExcel');
            $rResult = $db->fetchAll($sQuerySession->participantRegionQuery);
            foreach ($rResult as $aRow) {
                $row = [];
                $row[] = $aRow['region'];
                $row[] = $aRow['total_shipped'];
                $row[] = $aRow['total_responses'];
                $row[] = $aRow['valid_responses'];
                $row[] = $aRow['total_passed'];
                $row[] = round($aRow['pass_percentage'], 2);
                //$row[] = round($aRow['average_score'], 2);
                $output[] = $row;
            }

            foreach ($output as $rowNo => $rowData) {
                $colNo = 0;
                foreach ($rowData as $value) {
                    if (!isset($value)) {
                        $value = '';
                    }
                    $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 7)
                        ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(150);
                        $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 7, null, null)->getAlignment()->setWrapText(true);
                    }
                    $colNo++;
                }
            }

            if (!file_exists($this->tempUploadDirectory) && !is_dir($this->tempUploadDirectory)) {
                mkdir($this->tempUploadDirectory);
            }

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'participant-performance-region-wise' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            $sQuerySession->participantRegionQuery = '';
            Pt_Commons_LoggerUtility::logError('Failed to generate region-wise participant performance report (Excel): ' . $exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            return '';
        }
    }

    public function getTestKitParticipantReport($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        if (isset($parameters['reportType']) && $parameters['reportType'] == 'network') {
            $aColumns = ['p.first_name', 'network_name'];
        } elseif (isset($parameters['reportType']) && $parameters['reportType'] == 'affiliation') {
            $aColumns = ['p.first_name', 'affiliate'];
        } elseif (isset($parameters['reportType']) && $parameters['reportType'] == 'region') {
            $aColumns = ['p.first_name', 'region'];
        } else {
            $aColumns = ['p.first_name'];
        }

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
                    if (!isset($aColumns[$colIdx])) {
                        continue;
                    }
                    $sOrder .= $aColumns[$colIdx] . '
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
        $sQuery = $dbAdapter->select()->from(['res' => 'response_result_dts'], [])
            ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.map_id=res.shipment_map_id', [])
            ->joinLeft(['p' => 'participant'], 'sp.participant_id=p.participant_id', ['p.first_name', 'p.last_name', 'p.region', 'p.affiliation'])
            ->joinLeft(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', [])
            ->group('p.participant_id');

        if (isset($parameters['kitType']) && $parameters['kitType'] == 'testkit1') {
            $sQuery = $sQuery->joinLeft(['tn' => 'r_testkitnames'], 'tn.TestKitName_ID=res.test_kit_name_1', [])->where('tn.TestKitName_ID = ?', $parameters['testkitId']);
        } elseif (isset($parameters['kitType']) && $parameters['kitType'] == 'testkit2') {
            $sQuery = $sQuery->joinLeft(['tn' => 'r_testkitnames'], 'tn.TestKitName_ID=res.test_kit_name_2', [])->where('tn.TestKitName_ID = ?', $parameters['testkitId']);
        } elseif (isset($parameters['kitType']) && $parameters['kitType'] == 'testkit3') {
            $sQuery = $sQuery->joinLeft(['tn' => 'r_testkitnames'], 'tn.TestKitName_ID=res.test_kit_name_3', [])->where('tn.TestKitName_ID = ?', $parameters['testkitId']);
        } else {
            $sQuery = $sQuery->joinLeft(['tn' => 'r_testkitnames'], 'tn.TestKitName_ID=res.test_kit_name_1 or tn.TestKitName_ID=res.test_kit_name_2 or tn.TestKitName_ID=res.test_kit_name_3', ['TestKit_Name', 'TestKitName_ID'])
                ->group('tn.TestKitName_ID');
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == 'network') {
            if (isset($parameters['networkValue']) && $parameters['networkValue'] != '') {
                $sQuery = $sQuery->joinLeft(['n' => 'r_network_tiers'], 'p.network_tier=n.network_id', ['network_name'])->where('p.network_tier = ?', $parameters['networkValue']);
            } else {
                $sQuery = $sQuery->joinLeft(['n' => 'r_network_tiers'], 'p.network_tier=n.network_id', ['network_name']);
            }
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == 'affiliation') {
            if (isset($parameters['affiliateValue']) && $parameters['affiliateValue'] != '') {
                $iQuery = $dbAdapter->select()->from(['rpa' => 'r_participant_affiliates'], ['affiliation' => 'affiliate'])
                    ->where('rpa.aff_id=?', $parameters['affiliateValue']);
                $iResult = $dbAdapter->fetchRow($iQuery);
                $appliate = $iResult['affiliation'];
                $sQuery = $sQuery->where('p.affiliation = ' . $dbAdapter->quote($appliate) . ' OR p.affiliation = ' . $dbAdapter->quote($parameters['affiliateValue']));
            } else {
                $sQuery = $sQuery->joinLeft(['pa' => 'r_participant_affiliates'], 'p.affiliation=pa.affiliate', ['affiliation' => 'affiliate']);
            }
        }
        if (isset($parameters['reportType']) && $parameters['reportType'] == 'region') {
            if (isset($parameters['regionValue']) && $parameters['regionValue'] != '') {
                $sQuery = $sQuery->where('p.region= ?', $parameters['regionValue']);
            } else {
                $sQuery = $sQuery->where('p.region IS NOT NULL')->where("p.region != ''");
            }
        }
        if (isset($parameters['startDate']) && $parameters['startDate'] != '' && isset($parameters['endDate']) && $parameters['endDate'] != '') {

            $sQuery = $sQuery->where('s.shipment_date >= ?', $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where('s.shipment_date <= ?', $this->common->isoDateFormat($parameters['endDate']));
        }
        $sQuery = $sQuery->where('tn.TestKit_Name IS NOT NULL');

        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->having($sWhere);
        }
        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

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

        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = sizeof($aResultTotal);

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
            $row[] = $aRow['first_name'] . ' ' . $aRow['last_name'];
            if (isset($parameters['reportType']) && $parameters['reportType'] == 'network') {
                $row[] = $aRow['network_name'];
            } elseif (isset($parameters['reportType']) && $parameters['reportType'] == 'affiliation') {
                $row[] = $aRow['affiliation'];
            } elseif (isset($parameters['reportType']) && $parameters['reportType'] == 'region') {
                $row[] = $aRow['region'];
            } else {
                $row[] = '';
            }
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function generatePdfTestKitDetailedReport($parameters)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuerySession = new Zend_Session_Namespace('TestkitActionsExcel');
        $rResult = $dbAdapter->fetchAll($sQuerySession->testkitActionsQuery);
        $pResult = '';
        if (isset($parameters['testkitId']) && $parameters['testkitId'] != '') {
            $sQuery = $dbAdapter->select()->from(['res' => 'response_result_dts'], [])
                ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.map_id=res.shipment_map_id', [])
                ->joinLeft(['p' => 'participant'], 'sp.participant_id=p.participant_id', ['p.first_name', 'p.last_name', 'p.region', 'p.affiliation'])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', [])
                ->group('p.participant_id');

            if (isset($parameters['kitType']) && $parameters['kitType'] == 'testkit1') {
                $sQuery = $sQuery->joinLeft(['tn' => 'r_testkitnames'], 'tn.TestKitName_ID=res.test_kit_name_1', [])->where('tn.TestKitName_ID = ?', $parameters['testkitId']);
            }
            if (isset($parameters['kitType']) && $parameters['kitType'] == 'testkit2') {
                $sQuery = $sQuery->joinLeft(['tn' => 'r_testkitnames'], 'tn.TestKitName_ID=res.test_kit_name_2', [])->where('tn.TestKitName_ID = ?', $parameters['testkitId']);
            }
            if (isset($parameters['kitType']) && $parameters['kitType'] == 'testkit3') {
                $sQuery = $sQuery->joinLeft(['tn' => 'r_testkitnames'], 'tn.TestKitName_ID=res.test_kit_name_3', [])->where('tn.TestKitName_ID = ?', $parameters['testkitId']);
            }
            if (isset($parameters['reportType']) && $parameters['reportType'] == 'network') {
                if (isset($parameters['networkValue']) && $parameters['networkValue'] != '') {
                    $sQuery = $sQuery->joinLeft(['n' => 'r_network_tiers'], 'p.network_tier=n.network_id', ['network_name'])->where('p.network_tier = ?', $parameters['networkValue']);
                } else {
                    $sQuery = $sQuery->joinLeft(['n' => 'r_network_tiers'], 'p.network_tier=n.network_id', ['network_name']);
                }
            }
            if (isset($parameters['reportType']) && $parameters['reportType'] == 'affiliation') {
                if (isset($parameters['affiliateValue']) && $parameters['affiliateValue'] != '') {
                    $iQuery = $dbAdapter->select()->from(['rpa' => 'r_participant_affiliates'], ['affiliation' => 'affiliate'])
                        ->where('rpa.aff_id=?', $parameters['affiliateValue']);
                    $iResult = $dbAdapter->fetchRow($iQuery);
                    $appliate = $iResult['affiliation'];
                    $sQuery = $sQuery->where('p.affiliation = ' . $dbAdapter->quote($appliate) . ' OR p.affiliation = ' . $dbAdapter->quote($parameters['affiliateValue']));
                } else {
                    $sQuery = $sQuery->joinLeft(['pa' => 'r_participant_affiliates'], 'p.affiliation=pa.affiliate', ['affiliation' => 'affiliate']);
                }
            }
            if (isset($parameters['reportType']) && $parameters['reportType'] == 'region') {
                if (isset($parameters['regionValue']) && $parameters['regionValue'] != '') {
                    $sQuery = $sQuery->where('p.region= ?', $parameters['regionValue']);
                } else {
                    $sQuery = $sQuery->where('p.region IS NOT NULL')->where("p.region != ''");
                }
            }
            if (isset($parameters['reportType']) && $parameters['reportType'] == 'enrolled-programs') {
                if (isset($parameters['enrolledProgramsValue']) && $parameters['enrolledProgramsValue'] != '') {
                    $sQuery = $sQuery->joinLeft(['pe' => 'participant_enrolled_programs_map'], 'pe.participant_id=p.participant_id', [])
                        ->joinLeft(['rep' => 'r_enrolled_programs'], 'rep.r_epid=pe.ep_id', ['rep.enrolled_programs'])
                        ->where('rep.r_epid= ?', $parameters['enrolledProgramsValue']);
                } else {
                    $sQuery = $sQuery->joinLeft(['pe' => 'participant_enrolled_programs_map'], 'pe.participant_id=p.participant_id', [])
                        ->joinLeft(['rep' => 'r_enrolled_programs'], 'rep.r_epid=pe.ep_id', ['rep.enrolled_programs']);
                }
            }
            if (isset($parameters['startDate']) && $parameters['startDate'] != '' && isset($parameters['endDate']) && $parameters['endDate'] != '') {

                $sQuery = $sQuery->where('s.shipment_date >= ?', $this->common->isoDateFormat($parameters['startDate']));
                $sQuery = $sQuery->where('s.shipment_date <= ?', $this->common->isoDateFormat($parameters['endDate']));
            }
            $sQuery = $sQuery->where('tn.TestKit_Name IS NOT NULL');
            $pResult = $dbAdapter->fetchAll($sQuery);
        }
        $pieChart = $this->getTestKitReport($parameters);

        return ['testkitDtsReport' => $rResult, 'testkitDtsParticipantReport' => $pResult, 'testkitChart' => $pieChart];
    }

    //get vl assay distribution
    public function getAllVlAssayDistributionReports($parameters)
    {
        $searchColumns = [
            'sl.scheme_name',
            "DATE_FORMAT(s.shipment_date,'%d-%b-%Y')",
            's.shipment_code',
            'sp.shipment_score',
            'sp.documentation_score',
            "DATE_FORMAT(sp.shipment_test_date,'%d-%b-%Y')",
            "DATE_FORMAT(sp.shipment_receipt_date,'%d-%b-%Y')",
        ];
        $orderColumns = [
            'sl.scheme_name',
            's.shipment_date',
            's.shipment_code',
            'sp.shipment_score',
            'sp.documentation_score',
            'sp.shipment_test_date',
            'sp.shipment_receipt_date',
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
            ->join(
                ['sp' => 'shipment_participant_map'],
                'sp.shipment_id=s.shipment_id',
                ['s.shipment_date', 'sp.shipment_test_date', 'sp.shipment_receipt_date', 'sp.shipment_score', 'sp.documentation_score']
            )
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id')
            ->where("s.scheme_type ='vl'");

        if (isset($parameters['startDate']) && $parameters['startDate'] != '' && isset($parameters['endDate']) && $parameters['endDate'] != '') {

            $sQuery = $sQuery->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != '') {
            $sQuery = $sQuery->where('s.shipment_id = ?', $parameters['shipmentId']);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

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
        $sQuery = $dbAdapter->select()->from(['s' => 'shipment'])
            ->join(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id')
            ->join(
                ['sp' => 'shipment_participant_map'],
                'sp.shipment_id=s.shipment_id',
                ['s.shipment_date', 'sp.shipment_test_date', 'sp.shipment_receipt_date', 'sp.shipment_score', 'sp.documentation_score']
            )
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id')
            ->where("s.scheme_type ='vl'");

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

        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

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
            $row[] = $aRow['lab_name'];
            $row[] = $aRow['shipment_score'];
            $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($aRow['shipment_test_date']);
            $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($aRow['shipment_receipt_date']);
            $output['aaData'][] = $row;
        }
        echo json_encode($output);
    }

    //vl assay particpant count pie chart
    public function getAllVlAssayParticipantCount($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $shipmentId = null;
        if (isset($params['shipmentId']) && $params['shipmentId'] != '') {
            $shipmentId = $params['shipmentId'];
        }
        $vlQuery = $db->select()->from(['vl' => 'r_vl_assay'], ['vl.id', 'vl.name', 'vl.short_name'])->where("`status` like 'active'");
        $assayResult = $db->fetchAll($vlQuery);

        $i = 0;
        $vlParticipantCount = [];
        foreach ($assayResult as $assayRow) {
            $cQuery = $db->select()->from(['sp' => 'shipment_participant_map'], ['sp.map_id', 'sp.attributes']);
            if ($shipmentId != null) {
                $cQuery = $cQuery->where('sp.shipment_id = ?', $shipmentId);
            }

            $cResult = $db->fetchAll($cQuery);
            $k = 0;
            foreach ($cResult as $val) {
                $valAttributes = Pt_Commons_JsonUtility::safeDecode($val['attributes']);
                if ($assayRow['id'] == $valAttributes['vl_assay']) {
                    $k = $k + 1;
                }
            }
            $vlParticipantCount[$i]['count'] = $k;
            $vlParticipantCount[$i]['name'] = $assayRow['short_name'];
            $i++;
        }
        return $vlParticipantCount;
    }

    /**
     * Per-assay summary: how many participants used each VL assay and how many
     * passed / failed / were excluded. Scope is, in order of precedence:
     *   - a single shipment (shipmentId), else
     *   - a date range on shipment_date (startDate/endDate), else
     *   - all VL shipments (when allTime is set or no range is given).
     */
    public function getVlAssaySummary($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $assayExpr = new Zend_Db_Expr("JSON_UNQUOTE(JSON_EXTRACT(sp.attributes, '$.vl_assay'))");
        $byYear = !empty($params['groupBy']) && $params['groupBy'] === 'yearAssay';

        // A participant who responded "PT panel not tested" is treated as excluded during VL
        // evaluation (see Application_Model_Vl), so fold that flag into the excluded test here too.
        $isExcluded = "(IFNULL(sp.is_excluded, 'no') = 'yes' OR IFNULL(sp.is_pt_test_not_performed, 'no') = 'yes')";

        $columns = [
            'participants' => new Zend_Db_Expr('COUNT(sp.map_id)'),
            // Valid = responded on time ('responded' already excludes 'late'/'nottested'/'noresponse') and not excluded.
            'validResponses' => new Zend_Db_Expr("SUM(sp.response_status = 'responded' AND NOT $isExcluded)"),
            'passed' => new Zend_Db_Expr("SUM(sp.final_result = 1 AND NOT $isExcluded)"),
            'failed' => new Zend_Db_Expr("SUM(sp.final_result = 2 AND NOT $isExcluded)"),
            'excluded' => new Zend_Db_Expr("SUM($isExcluded)"),
            'pending' => new Zend_Db_Expr("SUM(COALESCE(sp.final_result, 0) NOT IN (1, 2) AND NOT $isExcluded)"),
        ];
        if ($byYear) {
            $columns['year'] = new Zend_Db_Expr('YEAR(s.shipment_date)');
        }

        $select = $db->select()
            ->from(['sp' => 'shipment_participant_map'], $columns)
            ->join(['s' => 'shipment'], 's.shipment_id = sp.shipment_id', [])
            ->join(['va' => 'r_vl_assay'], "va.id = $assayExpr", ['assayId' => 'va.id', 'name' => 'va.short_name', 'fullName' => 'va.name'])
            ->where("s.scheme_type = 'vl'")
            ->where("s.status = 'finalized'");

        if ($byYear) {
            $select->group([new Zend_Db_Expr('YEAR(s.shipment_date)'), 'va.id'])
                ->order(new Zend_Db_Expr('YEAR(s.shipment_date) DESC'))
                ->order(new Zend_Db_Expr('participants DESC'));
        } else {
            $select->group('va.id')
                ->order(new Zend_Db_Expr('participants DESC'));
        }

        $shipmentIds = [];
        if (!empty($params['shipmentId'])) {
            $shipmentIds = is_array($params['shipmentId']) ? $params['shipmentId'] : [$params['shipmentId']];
            $shipmentIds = array_values(array_filter($shipmentIds, static fn ($v) => $v !== '' && $v !== null));
        }

        if (!empty($shipmentIds)) {
            $select->where('sp.shipment_id IN (?)', $shipmentIds);
        } elseif (empty($params['allTime']) && !empty($params['startDate']) && !empty($params['endDate'])) {
            $select->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($params['startDate']));
            $select->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($params['endDate']));
        }

        return $db->fetchAll($select);
    }

    public function getAllVlSampleResult($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $totalResult = [];
        if ($params['shipmentId'] != '') {
            $shipmentId = $params['shipmentId'];
            $shQuery = $db->select()->from(['s' => 'shipment'])->where('s.shipment_id = ?', $shipmentId);
            $shimentResult = $db->fetchAll($shQuery);
        } else {
            $shQuery = $db->select()->from(['s' => 'shipment'])->where("s.scheme_type='vl'")->where('s.cancelled_at IS NULL');
            if (isset($params['start']) && $params['start'] != '' && isset($params['end']) && $params['end'] != '') {
                $shQuery = $shQuery->where('DATE(s.shipment_date) >= ?', $this->common->isoDateFormat($params['start']));
                $shQuery = $shQuery->where('DATE(s.shipment_date) <= ?', $this->common->isoDateFormat($params['end']));
            }
            $shimentResult = $db->fetchAll($shQuery);
        }
        if ($shimentResult) {
            $vlQuery = $db->select()->from(['vl' => 'r_vl_assay'], ['vl.id', 'vl.name', 'vl.short_name'])->where("`status` like 'active'");
            $assayResult = $db->fetchAll($vlQuery);
            $s = 0;
            foreach ($shimentResult as $shipData) {
                $shipmentId = $shipData['shipment_id'];
                $i = 0;
                $totalResult = [];
                foreach ($assayResult as $assayRow) {
                    $a = 0;
                    $f = 0;
                    $e = 0;
                    $cQuery = $db->select()->from(['sp' => 'shipment_participant_map'], ['sp.map_id', 'sp.attributes'])
                        ->where('sp.shipment_id = ?', $shipmentId);
                    $cResult = $db->fetchAll($cQuery);
                    foreach ($cResult as $val) {
                        $valAttributes = Pt_Commons_JsonUtility::safeDecode($val['attributes']);
                        if ($assayRow['id'] == $valAttributes['vl_assay']) {
                            //check pass result
                            $pQuery = $db->select()->from(['rrv' => 'response_result_vl'], ['passResult' => new Zend_Db_Expr("SUM(IF(rrv.calculated_score='pass',1,0))"), 'failResult' => new Zend_Db_Expr("SUM(IF(rrv.calculated_score='fail',1,0))"), 'exResult' => new Zend_Db_Expr("SUM(IF(rrv.calculated_score='excluded',1,0))")])
                                ->where('rrv.shipment_map_id = ?', $val['map_id'])
                                ->group('rrv.shipment_map_id');
                            $pResult = $db->fetchRow($pQuery);
                            if ($pResult) {
                                $a = $a + $pResult['passResult'];
                                $f = $f + $pResult['failResult'];
                                $e = $e + $pResult['exResult'];
                            }
                        }
                    }
                    $totalResult[$s][$i]['accept'] = $a;
                    $totalResult[$s][$i]['fail'] = $f;
                    $totalResult[$s][$i]['excluded'] = $e;
                    $totalResult[$s][$i]['name'] = $assayRow['short_name'];
                    $i++;
                }
            }
            $resultAccept = [];
            $resultFail = [];
            $resultEx = [];
            foreach ($totalResult as $result) {
                foreach ($result as $data) {
                    array_push($resultAccept, $data['accept']);
                    array_push($resultFail, $data['fail']);
                    array_push($resultEx, $data['excluded']);
                }
            }
            $resultAcc[] = $resultAccept;
            $resultFa[] = $resultFail;
            $resultExe[] = $resultEx;

            $resultAcc['name'] = 'Acceptable Result';
            $resultFa['name'] = 'Unacceptable Result';
            $resultExe['name'] = 'Excluded from evaluation';
            $totalResult = [$resultAcc, $resultFa, $resultExe, 'nameList' => $totalResult];
        }
        return $totalResult;
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
        if (isset($schemeType) && !empty($schemeType)) {
            $sWhere = '';
            foreach ($schemeType as $val) {
                if ($sWhere != '') {
                    $sWhere .= ' OR ';
                }
                $sWhere .= " s.scheme_type='" . $val . "' ";
            }
            if (!empty($sWhere)) {
                $sQuery = $sQuery->where($sWhere);
            }
        }
        $resultArray = $db->fetchAll($sQuery);
        return $resultArray;
    }

    public function getAnnualReport($params)
    {
        if (isset($params['startDate']) && trim($params['startDate']) != '' && trim($params['endDate']) != '') {

            $startDate = $this->common->isoDateFormat($params['startDate']);
            $endDate = $this->common->isoDateFormat($params['endDate']);

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $query = $db->select()
                ->from(['s' => 'shipment'], ['s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date', 's.response_deadline'])
                ->where('DATE(s.shipment_date) >= ?', $startDate)
                ->where('DATE(s.shipment_date) <= ?', $endDate)
                ->where('s.cancelled_at IS NULL')
                ->order('s.scheme_type');

            if (isset($params['scheme']) && !empty($params['scheme']) && count($params['scheme']) > 0) {
                $sWhere = '';
                foreach ($params['scheme'] as $val) {
                    if ($sWhere != '') {
                        $sWhere .= ' OR ';
                    }
                    $sWhere .= $db->quoteInto('s.scheme_type = ?', $val);
                }
                if (!empty($sWhere)) {
                    $query = $query->where($sWhere);
                }
            }
            $shipmentResult = $db->fetchAll($query);
            $shipmentIdArray = [];
            foreach ($shipmentResult as $val) {
                $shipmentIdArray[] = $val['shipment_id'];
                //$shipmentId[$val['scheme_type']][] = $val['shipment_id'];
                $shipmentCodeArray[$val['scheme_type']][] = $val['shipment_code'];
            }

            $sQuery = $db->select()
                ->from(['spm' => 'shipment_participant_map'], [
                    'spm.map_id',
                    'spm.shipment_id',
                    'spm.participant_id',
                    'spm.shipment_test_report_date',
                    'spm.shipment_score',
                    'spm.documentation_score',
                    'spm.final_result',
                    'spm.attributes',
                    'finalResult' => new Zend_Db_Expr("
                    CASE WHEN (spm.final_result = 1) THEN 'PASS' ELSE
                        (CASE WHEN (spm.final_result = 2) THEN 'FAIL' ELSE 'EXCLUDED' END)
                    END"),
                    'failure_reason',
                ])
                ->join(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', ['*'])
                ->join(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', ['scheme_name'])
                ->join(['p' => 'participant'], 'p.participant_id=spm.participant_id', ['unique_identifier', 'first_name', 'last_name', 'email', 'city', 'district', 'state', 'address', 'institute_name'])
                ->joinLeft(['c' => 'countries'], 'c.id=p.country', ['country_name' => 'iso_name'])
                ->where('spm.final_result = 1 OR spm.final_result = 2')
                // ->where("spm.is_excluded NOT LIKE 'yes'")
                ->order('unique_identifier ASC')
                ->order('scheme_type ASC');

            if (isset($params['shipmentId']) && !empty($params['shipmentId']) && count($params['shipmentId']) > 0) {
                $sQuery->where('spm.shipment_id IN (?)', $params['shipmentId']);
                $shQuery = $db->select()->from(['s' => 'shipment'], ['s.shipment_code', 's.scheme_type'])
                    ->where('s.shipment_id IN (?)', $params['shipmentId'])
                    ->order('s.scheme_type');
                $shipmentResult = $db->fetchAll($shQuery);
                $shipmentCodeArray = [];

                foreach ($shipmentResult as $val) {
                    $shipmentCodeArray[$val['scheme_type']][] = $val['shipment_code'];
                }
            } else {
                if (empty($shipmentIdArray)) {
                    return json_encode(['fileName' => '']);
                }
                $sQuery->where('spm.shipment_id IN (?)', $shipmentIdArray);
            }

            $shipmentParticipantResult = $db->fetchAll($sQuery);
            $participants = [];
            foreach ($shipmentParticipantResult as $shipment) {
                if (in_array($shipment['unique_identifier'], $participants)) {
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']] = $shipment['shipment_score'];
                } else {
                    $participants[$shipment['unique_identifier']]['labName'] = trim($shipment['first_name'] . ' ' . $shipment['last_name']);
                    $participants[$shipment['unique_identifier']]['institute_name'] = $shipment['institute_name'] ?? '';
                    $participants[$shipment['unique_identifier']]['department_name'] = $shipment['department_name'] ?? '';
                    $participants[$shipment['unique_identifier']]['address'] = $shipment['address'] ?? '';
                    $participants[$shipment['unique_identifier']]['city'] = $shipment['city'] ?? '';
                    $participants[$shipment['unique_identifier']]['district'] = $shipment['district'] ?? '';
                    $participants[$shipment['unique_identifier']]['state'] = $shipment['state'] ?? '';
                    $participants[$shipment['unique_identifier']]['country_name'] = $shipment['country_name'] ?? '';
                    $participants[$shipment['unique_identifier']]['contact_name'] = isset($shipment['contact_name']) ? $shipment['contact_name'] : '';
                    $participants[$shipment['unique_identifier']]['email'] = $shipment['email'] ?? '';
                    $participants[$shipment['unique_identifier']]['additional_email'] = isset($shipment['additional_email']) ? $shipment['additional_email'] : '';
                    $participants[$shipment['unique_identifier']]['scheme_name'] = isset($shipment['scheme_name']) ? $shipment['scheme_name'] : '';
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['score'] = (float) ($shipment['shipment_score'] + $shipment['documentation_score']);
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['result'] = $shipment['final_result'] ?? '';
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['finalResult'] = $shipment['finalResult'] ?? '';
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['failure_reason'] = $shipment['failure_reason'] ?? '';
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['number_of_samples'] = $shipment['number_of_samples'] ?? '';
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['attributes'] = Pt_Commons_JsonUtility::safeDecode($shipment['attributes']);
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['shipment_date'] = $shipment['shipment_date'] ?? '';
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['response_deadline'] = $shipment['response_deadline'] ?? '';
                    $participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['shipment_test_report_date'] = $shipment['shipment_test_report_date'] ?? '';
                }
            }
            $params['reportType'] = $params['reportType'] ?? 'csv';
            if ($params['reportType'] == 'csv') {
                $filename = $this->generateAnnualReportCSV($shipmentCodeArray, $participants);
            } elseif ($params['reportType'] == 'excel') {
                $filename = $this->generateAnnualReportExcel($shipmentCodeArray, $participants);
            } else {
                $filename = '';
            }

            return json_encode(['fileName' => $filename, 'reportType' => $params['reportType']]);
        }
    }

    public function getShipmentResponseReportReport($parameters)
    {
        $searchColumns = [
            'noOfParticipants',
            'noOfResponded',
            'noOfNotResponded',  // fixed: was missing
            'noOfPassed',
            'noOfFailed',
        ];
        $orderColumns = [
            'noOfParticipants',
            'noOfResponded',
            'noOfNotResponded',  // fixed: was missing
            'noOfPassed',
            'noOfFailed',
        ];

        $sLimit = '';
        $sOffset = 0;
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = '';
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == 'true') {
                    $colIdx = intval($parameters['iSortCol_' . $i]);
                    if (!isset($orderColumns[$colIdx])) {
                        continue;
                    }
                    $sOrder .= $orderColumns[$colIdx] . ' '
                        . Pt_Commons_General::sanitizeSortDirection($parameters['sSortDir_' . $i]) . ', ';
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

        $sQuery = $dbAdapter->select()
            ->from(['p' => 'participant'], [
                'noOfParticipants' => new Zend_Db_Expr('COUNT(*)'),
            ])
            ->join(['sp' => 'shipment_participant_map'], 'p.participant_id=sp.participant_id', [
                'noOfResponded' => new Zend_Db_Expr("SUM(CASE WHEN sp.response_status IN ('responded', 'nottested') THEN 1 ELSE 0 END)"),
                'noOfNotResponded' => new Zend_Db_Expr("SUM(CASE WHEN (sp.response_status LIKE '' OR sp.response_status LIKE 'noresponse' OR sp.response_status IS NULL) THEN 1 ELSE 0 END)"),
                'noOfPassed' => new Zend_Db_Expr('SUM(CASE WHEN (sp.final_result LIKE 1) THEN 1 ELSE 0 END)'),
                'noOfFailed' => new Zend_Db_Expr('SUM(CASE WHEN (sp.final_result LIKE 2) THEN 1 ELSE 0 END)'),
            ])
            ->join(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', [
                'shipment_code',
                'scheme_type',
                'response_deadline',
            ]);

        if (isset($parameters['scheme']) && $parameters['scheme'] != '') {
            $sQuery = $sQuery->where('s.scheme_type IN (?)', (array) $parameters['scheme']);
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != '') {
            $sQuery = $sQuery->where('s.shipment_id IN (?)', (array) $parameters['shipmentId']);
        }

        if (isset($parameters['country']) && $parameters['country'] != '') {
            $sQuery = $sQuery->where('p.country = ?', $parameters['country']);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (!empty($sLimit)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $dbAdapter->fetchAll($sQuery);

        $iFilteredTotal = $iTotal = count($rResult);

        $output = [
            'sEcho' => intval($parameters['sEcho']),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = $aRow['noOfParticipants'];   // col 1: # of Participants
            $row[] = $aRow['noOfResponded'];      // col 2: # Responded
            $row[] = $aRow['noOfNotResponded'];   // col 3: # Not Responded
            $row[] = $aRow['noOfPassed'];         // col 4: # Passed
            $row[] = $aRow['noOfFailed'];         // col 5: # Failed
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function generateAnnualReportExcel($shipmentCodeArray, $participants)
    {

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
                $certificate = true;
                $participated = true;
                foreach ($shipmentsList as $shipmentCode) {
                    $firstSheetRow[] = $shipmentCode;
                    $firstSheetRow[] = $participantArray[$shipmentType][$shipmentCode]['number_of_samples'];
                    $assayName = '';
                    if ($shipmentType == 'vl' && !empty($participantArray[$shipmentType][$shipmentCode]['attributes']['vl_assay'])) {
                        $assayName = $vlAssayArray[$participantArray[$shipmentType][$shipmentCode]['attributes']['vl_assay']];
                    } elseif ($shipmentType == 'eid' && !empty($participantArray[$shipmentType][$shipmentCode]['attributes']['extraction_assay'])) {
                        $assayName = $eidAssayArray[$participantArray[$shipmentType][$shipmentCode]['attributes']['extraction_assay']];
                    }
                    $firstSheetRow[] = $assayName;
                    if (!empty($participantArray[$shipmentType][$shipmentCode]['result']) && $participantArray[$shipmentType][$shipmentCode]['result'] != 3) {

                        $firstSheetRow[] = $participantArray[$shipmentType][$shipmentCode]['score'];

                        if ($participantArray[$shipmentType][$shipmentCode]['result'] != 1) {
                            $certificate = false;
                        }
                    } else {
                        if (!empty($participantArray[$shipmentType][$shipmentCode]['result']) && $participantArray[$shipmentType][$shipmentCode]['result'] == 3) {
                            $firstSheetRow[] = 'EXCLUDED';
                        } else {
                            $firstSheetRow[] = '-';
                        }
                        //$participated = false;
                        $certificate = false;
                    }

                    if (empty($participantArray[$shipmentType][$shipmentCode]['shipment_test_report_date'])) {
                        $participated = false;
                    }
                    $firstSheetRow[] = $participantArray[$shipmentType][$shipmentCode]['finalResult'];
                    if (isset($participantArray[$shipmentType][$shipmentCode]['failure_reason']) && !empty($participantArray[$shipmentType][$shipmentCode]['failure_reason']) && $participantArray[$shipmentType][$shipmentCode]['failure_reason'] != '[]') {
                        $warnings = Pt_Commons_JsonUtility::safeDecode($participantArray[$shipmentType][$shipmentCode]['failure_reason']);
                        $txt = $note = '';
                        foreach ($warnings as $w) {
                            $txt .= $w['warning'] ?? '';
                            $note .= $w['correctiveAction'] ?? '';
                        }
                        $firstSheetRow[] = strip_tags($txt);
                        $firstSheetRow[] = strip_tags($note);
                    } else {
                        $firstSheetRow[] = '';
                        $firstSheetRow[] = '';
                    }
                }
                if ($certificate && $participated) {
                    $firstSheetRow[] = 'Excellence';
                } elseif ($participated) {
                    $firstSheetRow[] = 'Participation';
                } else {
                    $firstSheetRow[] = 'N.A.';
                }
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

        $chunkSize = 50; // Adjust this value based on your memory constraints
        $participantCount = count($participants);

        // Write the header row
        fputcsv($tempFileHandle, $headings);

        for ($i = 0; $i < $participantCount; $i += $chunkSize) {
            $participantChunk = array_slice($participants, $i, $chunkSize);
            foreach ($participantChunk as $uniqueIdentifier => $participantArray) {
                foreach ($shipmentCodeArray as $shipmentType => $shipmentsList) {
                    foreach ($shipmentsList as $shipmentCode) {
                        if (empty($participantArray[$shipmentType][$shipmentCode]['result'])) {
                            continue;
                        }

                        $csvRow = [];
                        $csvRow[] = $uniqueIdentifier;
                        $csvRow[] = $participantArray['labName'];
                        $csvRow[] = $participantArray['institute_name'];
                        $csvRow[] = $participantArray['department_name'];
                        $csvRow[] = $participantArray['address'];
                        $csvRow[] = $participantArray['city'];
                        $csvRow[] = $participantArray['district'];
                        $csvRow[] = $participantArray['state'];
                        $csvRow[] = $participantArray['country_name'];
                        $csvRow[] = $participantArray['email'];
                        $csvRow[] = $participantArray['additional_email'];
                        $csvRow[] = $participantArray['scheme_name'];
                        $csvRow[] = $shipmentCode;
                        $csvRow[] = $participantArray[$shipmentType][$shipmentCode]['number_of_samples'];
                        $csvRow[] = Pt_Commons_DateUtility::humanReadableDateFormat($participantArray[$shipmentType][$shipmentCode]['shipment_date']);
                        $csvRow[] = Pt_Commons_DateUtility::humanReadableDateFormat($participantArray[$shipmentType][$shipmentCode]['response_deadline']);
                        $csvRow[] = Pt_Commons_DateUtility::humanReadableDateFormat($participantArray[$shipmentType][$shipmentCode]['shipment_test_report_date']);

                        $assayName = '';
                        if ($shipmentType == 'vl' && !empty($participantArray[$shipmentType][$shipmentCode]['attributes']['vl_assay'])) {
                            $assayName = $vlAssayArray[$participantArray[$shipmentType][$shipmentCode]['attributes']['vl_assay']];
                        } elseif ($shipmentType == 'eid' && !empty($participantArray[$shipmentType][$shipmentCode]['attributes']['extraction_assay'])) {
                            $assayName = $eidAssayArray[$participantArray[$shipmentType][$shipmentCode]['attributes']['extraction_assay']];
                        }
                        $csvRow[] = $assayName;

                        if (!empty($participantArray[$shipmentType][$shipmentCode]['result']) && $participantArray[$shipmentType][$shipmentCode]['result'] != 3) {
                            $csvRow[] = $participantArray[$shipmentType][$shipmentCode]['score'];
                        } else {
                            if (!empty($participantArray[$shipmentType][$shipmentCode]['result']) && $participantArray[$shipmentType][$shipmentCode]['result'] == 3) {
                                $csvRow[] = 'EXCLUDED';
                            } else {
                                $csvRow[] = '-';
                            }
                        }

                        $csvRow[] = $participantArray[$shipmentType][$shipmentCode]['finalResult'];

                        if (isset($participantArray[$shipmentType][$shipmentCode]['failure_reason']) && !empty($participantArray[$shipmentType][$shipmentCode]['failure_reason']) && $participantArray[$shipmentType][$shipmentCode]['failure_reason'] != '[]') {
                            $warnings = Pt_Commons_JsonUtility::safeDecode($participantArray[$shipmentType][$shipmentCode]['failure_reason']);
                            $txt = $note = '';
                            foreach ($warnings as $w) {
                                $txt .= $w['warning'] ?? '';
                                $note .= $w['correctiveAction'] ?? '';
                            }
                            $csvRow[] = strip_tags($txt);
                            $csvRow[] = strip_tags($note);
                        } else {
                            $csvRow[] = '';
                            $csvRow[] = '';
                        }

                        fputcsv($tempFileHandle, $csvRow);
                    }
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

    public function scheduleCertificationGeneration($params)
    {
        $scheduledDb = new Application_Model_DbTable_ScheduledJobs();
        return $scheduledDb->scheduleCertificationGeneration($params);
    }

    public function getResultsPerSiteReport($parameters)
    {

        $searchColumns = ['p.unique_identifier', 'c.iso_name', 'p.region', 'p.lab_name', 'spm.shipment_score', 'smp.final_result'];

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
                    if (!isset($searchColumns[$colIdx])) {
                        continue;
                    }
                    $sOrder .= $searchColumns[$colIdx] . '
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
        $sQuery = $dbAdapter->select()
            ->from(
                ['spm' => 'shipment_participant_map'],
                ['map_id', 'final_result', 'shipment_score', 'documentation_score', 'shipment_test_report_date']
            )
            ->join(
                ['p' => 'participant'],
                'spm.participant_id=p.participant_id',
                ['unique_identifier', 'lab_name', 'region']
            )
            ->join(['c' => 'countries'], 'p.country=c.id', ['iso_name'])
            ->order(new Zend_Db_Expr("CASE WHEN p.unique_identifier REGEXP '\d*' THEN CAST(CAST(p.unique_identifier AS DECIMAL) AS CHAR) ELSE TRIM(LEADING '0' FROM p.unique_identifier) END"));

        $scopedDmId = Application_Service_Common::getScopedDmId();
        if (!empty($scopedDmId)) {
            $sQuery = $sQuery
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $scopedDmId);
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != '') {
            $sQuery = $sQuery->where('spm.shipment_id like ?', $parameters['shipmentId']);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

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
        $sQuery = $dbAdapter->select()
            ->from(['spm' => 'shipment_participant_map'], new Zend_Db_Expr("COUNT('spm.map_id')"))
            ->where('spm.shipment_id = ?', $parameters['shipmentId']);
        if (!empty($scopedDmId)) {
            $sQuery = $sQuery
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=spm.participant_id', [])
                ->where('pmm.dm_id = ?', $scopedDmId);
        }
        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->where($sWhere);
        }

        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

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
            $row[] = $aRow['lab_name'] . ' (' . $aRow['unique_identifier'] . ')';
            $row[] = $aRow['iso_name'];
            $row[] = $aRow['region'];
            $row[] = $aRow['shipment_score'] + $aRow['documentation_score'];
            if ($aRow['shipment_test_report_date'] == null) {
                $row[] = 'Not Participated';
            } elseif ($aRow['final_result'] == 1) {
                $row[] = 'Satisfactory';
            }
            if ($aRow['final_result'] == 2) {
                $row[] = 'Unsatisfactory';
            } elseif ($aRow['final_result'] == 3) {
                $row[] = 'Excluded';
            }
            $output['aaData'][] = $row;
        }
        echo json_encode($output);
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

    public function getResultsPerSiteCount($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $resultsQuery = $db->select()
            ->from(
                ['spm' => 'shipment_participant_map'],
                [
                    'satisfactory' => 'sum(spm.final_result = 1)',
                    'unsatisfactory' => 'sum(spm.final_result = 2)',
                    'excluded' => 'sum(spm.final_result = 3)',
                    'not_participated' => 'sum(spm.shipment_test_report_date is null)',
                ]
            )
            ->where('spm.shipment_id = ?', $params['shipmentId'])
            ->group(['spm.shipment_id']);
        $scopedDmId = Application_Service_Common::getScopedDmId();
        if (!empty($scopedDmId)) {
            $resultsQuery = $resultsQuery
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=spm.participant_id', [])
                ->where('pmm.dm_id = ?', $scopedDmId);
        }
        $resultsCountResult = $db->fetchRow($resultsQuery);
        return [
            ['name' => 'Satisfactory', 'count' => $resultsCountResult['satisfactory']],
            ['name' => 'Unsatisfactory', 'count' => $resultsCountResult['unsatisfactory']],
            ['name' => 'Excluded', 'count' => $resultsCountResult['excluded']],
            ['name' => 'Not Participated', 'count' => $resultsCountResult['not_participated']],
        ];
    }

    public function getParticipantsPerCountryReport($parameters)
    {

        $searchColumns = ['country_name', 'participant_count'];

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
                    if (!isset($searchColumns[$colIdx])) {
                        continue;
                    }
                    $sOrder .= $searchColumns[$colIdx] . '
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
        $sQuery = $dbAdapter->select()
            ->from(['spm' => 'shipment_participant_map'], [])
            ->join(
                ['p' => 'participant'],
                'spm.participant_id = p.participant_id',
                ['participant_count' => new Zend_Db_Expr('COUNT(p.participant_id)')]
            )
            ->join(
                ['c' => 'countries'],
                'p.country = c.id',
                ['id', 'country_name' => 'c.iso_name']
            )
            ->group('c.id');

        $scopedDmId = Application_Service_Common::getScopedDmId();
        if (!empty($scopedDmId)) {
            $sQuery = $sQuery
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $scopedDmId);
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != '') {
            $sQuery = $sQuery->where('spm.shipment_id like ?', $parameters['shipmentId']);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

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
        $sQuery = $dbAdapter->select()
            ->from(['spm' => 'shipment_participant_map'], [])
            ->join(
                ['p' => 'participant'],
                'spm.participant_id = p.participant_id',
                new Zend_Db_Expr('COUNT(DISTINCT p.country)')
            );
        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != '') {
            $sQuery = $sQuery->where('spm.shipment_id like ?', $parameters['shipmentId']);
        }
        if (!empty($scopedDmId)) {
            $sQuery = $sQuery
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $scopedDmId);
        }
        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->where($sWhere);
        }

        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

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
            $row[] = $aRow['country_name'];
            $row[] = $aRow['participant_count'];
            $output['aaData'][] = $row;
        }
        echo json_encode($output);
    }

    public function getParticipantsPerCountryCount($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $resultsQuery = $db->select()
            ->from(['spm' => 'shipment_participant_map'], [])
            ->join(
                ['p' => 'participant'],
                'spm.participant_id = p.participant_id',
                ['participant_count' => new Zend_Db_Expr('COUNT(p.participant_id)')]
            )
            ->join(
                ['c' => 'countries'],
                'p.country = c.id',
                ['id', 'country_name' => 'c.iso_name']
            )
            ->where('spm.shipment_id = ?', $params['shipmentId'])
            ->group(['c.id'])
            ->order('participant_count DESC');
        $scopedDmId = Application_Service_Common::getScopedDmId();
        if (!empty($scopedDmId)) {
            $resultsQuery = $resultsQuery
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $scopedDmId);
        }
        $resultsCountResult = $db->fetchAll($resultsQuery);

        $output = [];
        foreach ($resultsCountResult as $aRow) {
            $row = [];
            $row['name'] = $aRow['country_name'];
            $row['count'] = $aRow['participant_count'];
            $output[] = $row;
        }

        return $output;
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

    public function getParticipantShipmentPerformanceReport($parameters)
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sQuery = $dbAdapter->select()->from(['p' => 'participant'], ['p.first_name', 'p.participant_id', 'participantName' => new Zend_Db_Expr("CONCAT(p.first_name, '(' ,p.unique_identifier, ')')")])
            ->join(['spm' => 'shipment_participant_map'], 'p.participant_id = spm.participant_id', ['score' => new Zend_Db_Expr('AVG(spm.shipment_score + spm.documentation_score)')])
            ->join(['s' => 'shipment'], 'spm.shipment_id = s.shipment_id', ['s.shipment_code', 's.shipment_id'])
            ->where('spm.response_status like "responded"')
            ->where('spm.is_pt_test_not_performed not like "yes"')
            ->where('spm.is_excluded not like "yes"')
            ->group('s.shipment_id')
            ->group('p.participant_id');

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
        $sQuerySession = new Zend_Session_Namespace('ParticipantPerformanceExcel');
        $sQuerySession->participantPerformanceQuery = $sQuery;
        return $dbAdapter->fetchAll($sQuery);
    }

    public function exportParticipantPerformanceReport()
    {

        try {
            $excel = new Spreadsheet();

            $output = [];
            $sheet = $excel->getActiveSheet();

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuerySession = new Zend_Session_Namespace('ParticipantTrendsExcel');
            $sQuerySession = new Zend_Session_Namespace('ParticipantPerformanceExcel');
            $rResult = $db->fetchAll($sQuerySession->participantPerformanceQuery);
            $results = [];
            $shipments = [];
            foreach ($rResult as $row) {
                $results[$row['participantName']][$row['shipment_code']] = round($row['score']);
                if (!in_array($row['shipment_code'], $shipments)) {
                    $shipments[] = $row['shipment_code'];
                }
            }

            $headings = ['Participant Name'];
            foreach ($shipments as $code) {
                array_push($headings, $code);
            }
            array_push($headings, 'Average Score');
            $colNo = 0;
            $sheet->mergeCells('A1:B1');
            $sheet->getCell(Coordinate::stringFromColumnIndex(1) . 1)
                ->setValueExplicit(html_entity_decode('Participant Performance Report', ENT_QUOTES, 'UTF-8'));
            $sheet->getStyle(Coordinate::stringFromColumnIndex(1) . 1, null, null)->getFont()->setBold(true);
            foreach ($headings as $value) {
                $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . 5)
                    ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . 5, null, null)->getFont()->setBold(true);
                $colNo++;
            }

            foreach ($results as $name => $row) {
                $row = [];
                $row[] = $name;
                foreach ($shipments as $vl) {
                    $scores[] = $results[$name][$vl] ?? 0;
                    $row[] = $results[$name][$vl] ?? 0;
                }
                $score_count = count($scores);
                $score_sum = array_sum($scores);
                $mean_average = $score_sum / $score_count;
                $row[] = $mean_average;
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
            foreach (range('A', 'Z') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }
            if (!file_exists($this->tempUploadDirectory) && !is_dir($this->tempUploadDirectory)) {
                mkdir($this->tempUploadDirectory);
            }

            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'participant-performance-report-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            $sQuerySession->participantQuery = '';
            Pt_Commons_LoggerUtility::logError('Failed to generate participant performance report (Excel): ' . $exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);

            return '';
        }
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

            $t = fn ($text) => html_entity_decode($this->translator->_($text), ENT_QUOTES, 'UTF-8');
            $pct = fn (int $part, int $whole) => $whole > 0 ? round($part / $whole * 100, 1) : null;
            $headings = [
                $t('Enrolled'),
                $t('Responded'),
                $t('Response rate') . ' (%)',
                $t('On time'),
                $t('On time') . ' (%)',
                $t('Passed'),
                $t('Failed'),
                $t('Pass rate') . ' (%)',
                $t('Excellent'),
                $t('Satisfactory'),
                $t('Unsatisfactory'),
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
            $sheet->setTitle(mb_substr($t('Shipments'), 0, 31));
            $sheet->fromArray(array_merge([$t('Scheme'), $t('Shipment Code'), $t('Shipment Date')], $headings), null, 'A1');
            $rowNo = 2;
            foreach ($shipments as $r) {
                $sheet->fromArray(array_merge(
                    [$r['scheme_name'], $r['shipment_code'], Pt_Commons_DateUtility::humanReadableDateFormat($r['shipment_date'])],
                    $figures($r)
                ), null, 'A' . $rowNo++);
            }

            $regionSheet = $excel->createSheet();
            $regionSheet->setTitle(mb_substr($t('Regions'), 0, 31));
            $regionSheet->fromArray(array_merge([$t('Shipment Code'), $t('Region')], $headings), null, 'A1');
            $rowNo = 2;
            foreach ($shipments as $shipment) {
                $regions = $this->getParticipantTrendsSummary(array_merge($params, ['shipmentId' => $shipment['shipment_id']]), true);
                foreach ($regions as $r) {
                    $regionSheet->fromArray(array_merge(
                        [$shipment['shipment_code'], $r['region'] !== '' ? $r['region'] : $t('No region')],
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
}
