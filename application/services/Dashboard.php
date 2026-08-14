<?php
/**
 * Application_Service_Dashboard
 *
 * Backs the rebuilt admin dashboard (Tasks 2–4 of the "round status" spec).
 *
 * Schema notes / assumptions:
 *   Confirmed against Application_Service_Shipments as you shared it:
 *     shipment(shipment_id, shipment_code, scheme_type, response_deadline,
 *              status, cancelled_at, shipment_date, max_score, average_score)
 *     shipment_participant_map(participant_id, shipment_id, shipment_test_date,
 *              shipment_score, documentation_score, response_status)
 *     scheme_list(scheme_id, scheme_name)
 *     shipment.scheme_type is actually the FK to scheme_list.scheme_id, despite
 *     the name — kept as-is to match the existing model.
 *
 *   Confirmed:
 *     shipment_participant_map.response_status is a STRING enum:
 *       'responded' | 'noresponse' | 'late' | 'nottested'
 *     "Took part / responded" = response_status != 'noresponse' (late and
 *     nottested both count as having responded — the round table's numbers
 *     only reconcile if responded + outstanding = total, which requires
 *     late/nottested to be a subset of responded, not separate from it).
 *     "Outstanding" = response_status = 'noresponse'.
 *     "Late" = response_status = 'late'. "Unable to test" = response_status
 *     = 'nottested'. This replaced an earlier guess at separate
 *     is_response_late / is_pt_test_not_performed boolean columns, which
 *     don't exist — everything comes off this one enum column.
 *
 *   NOT visible in the code you shared, taken on trust from the spec's
 *   "Where the numbers come from" table — verify these exist before deploying:
 *     shipment.finalized_at
 *     shipment_participant_map.final_result   (1 pass, 2 fail, 3 excluded, 4 not evaluated)
 *     shipment_participant_map.report_download_metadata
 *
 *   "Open" for the round table (Task 3), per explicit instruction: status
 *   != 'finalized' AND response_deadline >= NOW(). Expired-deadline
 *   shipments are excluded from the table entirely — they don't appear
 *   styled differently, they just don't show up. response_switch, which
 *   the original spec mentioned, is NOT used anywhere in this file.
 *
 *   "Active participants" / "active schemes" (Task 2) and "out of enrolled"
 *   (Task 4) all come from Application_Service_Schemes::countEnrollmentSchemes(),
 *   not a raw query — see getSummaryCounts() and getBetweenRoundsSummary()
 *   for the keyed-by-scheme-name caveat noted in PATCH_NOTES.md.
 *
 *   Per the spec's warning: shipments finalized before
 *   Evaluation::excludeNonResponder() landed over-counted non-responders as
 *   fails, so their fail numbers aren't comparable to newer rounds. This
 *   service does not attempt to correct historical data — it surfaces
 *   whatever final_result says. If you want the between-rounds card to flag
 *   old rounds as non-comparable, that needs a cutover date/flag this
 *   service doesn't have.
 */
class Application_Service_Dashboard
{
    /** @var Zend_Db_Adapter_Abstract */
    protected $db;

    /** @var Application_Service_Schemes */
    protected $schemeService;

    public function __construct()
    {
        $this->db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->schemeService = new Application_Service_Schemes();
    }

    /**
     * Task 2 — top strip. Three flat counts, no trends.
     */
    public function getSummaryCounts()
    {
        // Reuses the same method the original (pre-rebuild) dashboard controller
        // called to feed the removed "Active Participants enrolled per PT Scheme"
        // chart — it already returns per-scheme enrolled-participant counts, so
        // summing it gives total active participants without guessing a table.
        // (Keyed by scheme name per the original phtml's usage — key identity
        // doesn't matter here since we're only summing/counting values.)
        $schemeCountsByName = $this->schemeService->countEnrollmentSchemes();
        $activeParticipants = (int) array_sum($schemeCountsByName);

        // Schemes with at least one enrolled participant. TODO: if "active
        // scheme" should instead mean something like a status flag on the
        // scheme itself (independent of enrollment), swap this for
        // count($this->schemeService->getAllSchemes()) filtered accordingly —
        // I don't have visibility into whether scheme_list carries its own
        // active/inactive flag.
        $activeSchemes = count(array_filter($schemeCountsByName, function ($count) {
            return $count > 0;
        }));

        $roundsCompletedThisYear = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM shipment
             WHERE status = 'finalized'
               AND cancelled_at IS NULL
               AND YEAR(finalized_at) = YEAR(CURDATE())"
        );

        return [
            'activeParticipants' => $activeParticipants,
            'activeSchemes' => $activeSchemes,
            'roundsCompletedThisYear' => $roundsCompletedThisYear,
        ];
    }

    /**
     * Task 3 — table rows.
     *
     * Explicit instruction overriding the earlier "keep closed rows visible
     * until evaluated" behavior: this table shows ONLY currently-running
     * shipments — status != 'finalized' AND response_deadline not yet
     * crossed. Expired-deadline shipments are excluded entirely, not just
     * styled differently. As a side effect this also closes the 'evaluated'
     * status gap noted earlier in PATCH_NOTES.md — status != 'finalized'
     * catches 'shipped' and 'evaluated' alike, as long as the deadline
     * hasn't passed.
     */
    public function getOpenRoundsStatus()
    {
        $sql = "
            SELECT
                s.shipment_id,
                s.shipment_code,
                sl.scheme_name,
                s.response_deadline,
                COUNT(sp.participant_id) AS total,
                SUM(sp.response_status != 'noresponse') AS responded,
                SUM(sp.response_status = 'late') AS late,
                SUM(sp.response_status = 'nottested') AS unable_to_test
            FROM shipment s
            JOIN scheme_list sl ON sl.scheme_id = s.scheme_type
            JOIN shipment_participant_map sp ON sp.shipment_id = s.shipment_id
            WHERE s.status != 'finalized'
              AND s.cancelled_at IS NULL
              AND s.response_deadline >= NOW()
            GROUP BY s.shipment_id
            ORDER BY s.response_deadline ASC
        ";

        $rows = $this->db->fetchAll($sql);
        $now = new DateTime();
        $out = [];

        foreach ($rows as $row) {
            $deadline = new DateTime($row['response_deadline']);
            $diffDays = (int) $now->diff($deadline)->format('%r%a'); // signed days, future positive
            $rowClass = $diffDays <= 3 ? 'amber' : '';
            $deadlineLabel = 'In ' . $diffDays . ' day' . ($diffDays === 1 ? '' : 's');

            $total = (int) $row['total'];
            $responded = (int) $row['responded'];

            $out[] = [
                'shipmentId' => $row['shipment_id'],
                'code' => strtoupper($row['shipment_code']),
                'schemeName' => $row['scheme_name'],
                'deadlineLabel' => $deadlineLabel,
                'deadlineDate' => $deadline->format('Y-m-d'),
                'rowClass' => $rowClass,
                'total' => $total,
                'responded' => $responded,
                'outstanding' => max(0, $total - $responded),
                'late' => (int) $row['late'],
                'unableToTest' => (int) $row['unable_to_test'],
            ];
        }

        return $out;
    }

    /**
     * Task 4 — most recently finalized round per scheme, newest first,
     * capped at $limit. Only reachable when getOpenRoundsStatus() is empty.
     */
    public function getBetweenRoundsSummary($limit = 5)
    {
        // Most recent finalized shipment per scheme.
        $latestSql = "
            SELECT s.*
            FROM shipment s
            INNER JOIN (
                SELECT scheme_type, MAX(finalized_at) AS max_finalized
                FROM shipment
                WHERE status = 'finalized' AND cancelled_at IS NULL
                GROUP BY scheme_type
            ) latest ON latest.scheme_type = s.scheme_type AND latest.max_finalized = s.finalized_at
            WHERE s.status = 'finalized' AND s.cancelled_at IS NULL
            ORDER BY s.finalized_at DESC
            LIMIT " . (int) $limit;

        $latestShipments = $this->db->fetchAll($latestSql);
        $cards = [];
        // NOTE: the original (pre-rebuild) dashboard iterated this method's
        // result as `foreach ($schemeCountResult as $schemeName => $pCount)`,
        // which means it's keyed by scheme NAME, not scheme_id/scheme_type.
        // Keying off name below to match that — but two schemes sharing a
        // display name would collide under this keying. If
        // countEnrollmentSchemes() actually returns numeric scheme_type/id
        // keys and the original loop variable was just misleadingly named,
        // switch this back to keying by $schemeType.
        $schemeCountsByName = $this->schemeService->countEnrollmentSchemes();

        foreach ($latestShipments as $shipment) {
            $schemeType = $shipment['scheme_type'];
            $shipmentId = $shipment['shipment_id'];

            $schemeName = $this->db->fetchOne(
                'SELECT scheme_name FROM scheme_list WHERE scheme_id = ?',
                [$schemeType]
            );

            // Enrolled count "out of enrolled" — not out of this shipment's
            // participant list. Same source as getSummaryCounts(): the
            // existing countEnrollmentSchemes() method, keyed by scheme name.
            $enrolledCount = (int) ($schemeCountsByName[$schemeName] ?? 0);

            $participantStats = $this->db->fetchRow(
                "SELECT
                    SUM(response_status != 'noresponse') AS took_part,
                    SUM(response_status = 'noresponse') AS no_response,
                    SUM(response_status = 'nottested') AS unable_to_test,
                    SUM(final_result = 1) AS pass,
                    SUM(final_result = 2) AS fail,
                    SUM(final_result = 3) AS excluded,
                    SUM(final_result = 4) AS not_evaluated,
                    SUM(report_download_metadata IS NOT NULL) AS report_opened
                 FROM shipment_participant_map
                 WHERE shipment_id = ?",
                [$shipmentId]
            );

            // Previous finalized round for this scheme, for the score trend.
            $previousShipment = $this->db->fetchRow(
                "SELECT shipment_id, shipment_code, average_score
                 FROM shipment
                 WHERE scheme_type = ? AND status = 'finalized' AND cancelled_at IS NULL
                   AND finalized_at < ?
                 ORDER BY finalized_at DESC
                 LIMIT 1",
                [$schemeType, $shipment['finalized_at']]
            );

            $scoreDelta = null;
            $repeatFailerCount = 0;
            if ($previousShipment) {
                $scoreDelta = round($shipment['average_score'] - $previousShipment['average_score'], 1);

                // Repeat failers: failed this round AND failed the round before.
                $repeatFailerCount = (int) $this->db->fetchOne(
                    "SELECT COUNT(*) FROM shipment_participant_map cur
                     JOIN shipment_participant_map prev
                       ON prev.participant_id = cur.participant_id
                      AND prev.shipment_id = ?
                     WHERE cur.shipment_id = ?
                       AND cur.final_result = 2
                       AND prev.final_result = 2",
                    [$previousShipment['shipment_id'], $shipmentId]
                );
            }

            // Never responded in the last 3 rounds for this scheme.
            $last3ShipmentIds = $this->db->fetchCol(
                "SELECT shipment_id FROM shipment
                 WHERE scheme_type = ? AND status = 'finalized' AND cancelled_at IS NULL
                 ORDER BY finalized_at DESC
                 LIMIT 3",
                [$schemeType]
            );
            $neverRespondedCount = 0;
            if (count($last3ShipmentIds) === 3) {
                $placeholders = implode(',', array_fill(0, count($last3ShipmentIds), '?'));
                $neverRespondedCount = (int) $this->db->fetchOne(
                    "SELECT COUNT(*) FROM (
                        SELECT participant_id
                        FROM shipment_participant_map
                        WHERE shipment_id IN ($placeholders)
                        GROUP BY participant_id
                        HAVING SUM(response_status != 'noresponse') = 0
                     ) t",
                    $last3ShipmentIds
                );
            }

            $tookPart = (int) $participantStats['took_part'];

            $cards[] = [
                'shipmentId' => $shipmentId,
                'schemeName' => $schemeName,
                'roundCode' => strtoupper($shipment['shipment_code']),
                'finalizedDateShort' => date('d M', strtotime($shipment['finalized_at'])),
                'finalizedDateFull' => date('d M Y', strtotime($shipment['finalized_at'])),
                'enrolledCount' => $enrolledCount,
                'tookPart' => $tookPart,
                'noResponse' => (int) $participantStats['no_response'],
                'unableToTest' => (int) $participantStats['unable_to_test'],
                'pass' => (int) $participantStats['pass'],
                'fail' => (int) $participantStats['fail'],
                'excluded' => (int) $participantStats['excluded'],
                'notEvaluated' => (int) $participantStats['not_evaluated'],
                'averageScore' => $shipment['average_score'],
                'scoreDelta' => $scoreDelta,
                'repeatFailerCount' => $repeatFailerCount,
                'neverRespondedCount' => $neverRespondedCount,
                'reportOpenedCount' => (int) $participantStats['report_opened'],
            ];
        }

        return $cards;
    }

    /**
     * Drill-down list for the "N outstanding" link on a round-table row —
     * labs on a specific shipment that haven't responded yet.
     *
     * This is the only lab-list method left in the class: the equivalents
     * for "never responded" and "repeat failers" were removed along with
     * their links on the between-rounds card, since that card describes an
     * already-finalized round and this page is scoped to currently-running
     * shipments only. Not deleting the finalized-round versions blind —
     * this comment is here so it's easy to find where they'd need to be
     * rebuilt if a finalized-round drill-down page gets built later
     * (queries would look like getBetweenRoundsSummary()'s scoreDelta/
     * repeatFailerCount/neverRespondedCount blocks, returning rows instead
     * of counts).
     */
    public function getOutstandingLabs($shipmentId)
    {
        $participantIds = $this->db->fetchCol(
            "SELECT participant_id
             FROM shipment_participant_map
             WHERE shipment_id = ? AND response_status = 'noresponse'",
            [$shipmentId]
        );

        return $this->fetchLabDetails($participantIds, [
            'context' => 'No response recorded yet for this round',
        ]);
    }

    /**
     * Shared lab-detail lookup for the drill-down list above.
     *
     * ASSUMPTION — unconfirmed against your real schema: a `participant`
     * table keyed by participant_id with lab_name/email/phone columns.
     * None of this was visible in anything you've shared so far. Adjust
     * the column list/table name to match your actual participants table
     * (it may live in Application_Service_Participants already — if so,
     * prefer calling a method there over this raw query).
     */
    private function fetchLabDetails(array $participantIds, array $extra = [])
    {
        if (empty($participantIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($participantIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT participant_id, lab_name, first_name, last_name, email, phone, unique_identifier, c.iso_name, p.state, p.district   
             FROM participant As p
             JOIN countries AS c ON c.id = p.country 
             WHERE participant_id IN ($placeholders)
             ORDER BY lab_name ASC",
            $participantIds
        );

        return array_map(function ($row) use ($extra) {
            return array_merge([
                'participantId' => $row['participant_id'],
                'labName' => $row['lab_name'] ?? trim($row['first_name'] . ' ' . $row['last_name']),
                'email' => $row['email'],
                'phone' => $row['phone'],
                'iso_name' => $row['iso_name'],
                'state' => $row['state'],
                'district' => $row['district'],
            ], $extra);
        }, $rows);
    }

    /**
     * Task 3 drill-down — DataTables server-side source for the round
     * participants list (Outstanding / Late / Unable to test). Same calling
     * convention as Application_Model_DbTable_Participants::getAllParticipants():
     * echoes DataTables JSON directly, caller returns immediately after.
     *
     * Supports the same three filters as the main participants grid (lab/institute,
     * country, participant status) plus DataTables' own paging/search/sort — but
     * scoped underneath the shipment_id + response_status bucket, which is not
     * user-changeable (that's what the tab strip controls, via a page reload).
     *
     * ASSUMPTION: p.phone exists on `participant` — same assumption
     * fetchLabDetails() already carries; adjust if your schema differs.
     */
    public function getRoundParticipantsDataTable($shipmentId, $status, array $parameters)
    {
        $shipment = $this->getShipmentHeaderForDrilldown($shipmentId);
        if (!$shipment) {
            echo json_encode([
                'sEcho' => intval($parameters['sEcho'] ?? 0),
                'iTotalRecords' => 0,
                'iTotalDisplayRecords' => 0,
                'aaData' => [],
            ]);
            return;
        }

        $responseStatusMap = [
            'late' => 'late',
            'unabletotest' => 'nottested',
            'outstanding' => 'noresponse',
        ];
        $responseStatus = $responseStatusMap[$status] ?? 'noresponse';

        // Column order must match the view's <thead> / aoColumns exactly:
        // Participant ID, Name, Email, Phone, Country, State, District.
        $aColumns = [
            'p.participant_id',
            new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameExpr('p')),
            'p.email',
            'p.phone',
            'c.iso_name',
            'p.state',
            'p.district',
        ];

        $sLimit = '';
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                $colIdx = intval($parameters['iSortCol_' . $i]);
                if (!isset($aColumns[$colIdx])) {
                    continue;
                }
                if (($parameters['bSortable_' . $colIdx] ?? '') == 'true') {
                    $sortDir = Pt_Commons_General::sanitizeSortDirection($parameters['sSortDir_' . $i]);
                    $sOrder[] = new Zend_Db_Expr(((string) $aColumns[$colIdx]) . ' ' . $sortDir);
                }
            }
        }

        $sQuery = $this->db->select()
            ->from(['p' => 'participant'], [
                'p.participant_id', 'p.lab_name', 'p.first_name', 'p.last_name',
                'p.email', 'p.phone', 'p.state', 'p.district',
                'participantName' => new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameExpr('p')),
            ])
            ->join(['c' => 'countries'], 'c.id = p.country', ['iso_name'])
            ->join(['sp' => 'shipment_participant_map'], 'sp.participant_id = p.participant_id', [])
            ->where('sp.shipment_id = ?', $shipmentId)
            ->where('sp.response_status = ?', $responseStatus)
            ->group('p.participant_id');

        // Same three filters the main participants grid offers, reused here so
        // an admin can narrow "43 outstanding" down by lab/country/status
        // without leaving the shipment scope.
        if (!empty($parameters['pid'])) {
            $pid = is_array($parameters['pid']) ? $parameters['pid'] : explode(',', $parameters['pid']);
            $sQuery = $sQuery->where('p.institute_name IN (?)', $pid);
        }
        if (!empty($parameters['country'])) {
            $cid = is_array($parameters['country']) ? $parameters['country'] : explode(',', $parameters['country']);
            $sQuery = $sQuery->where('p.country IN (?)', $cid);
        }
        if (!empty($parameters['pstatus'])) {
            $sQuery = $sQuery->where('p.status LIKE ?', $parameters['pstatus']);
        }

        // DataTables global search box.
        if (!empty($parameters['sSearch'])) {
            $searchArray = explode(' ', $parameters['sSearch']);
            $sWhereSub = '';
            foreach ($searchArray as $search) {
                $sWhereSub .= ($sWhereSub === '' ? '(' : ' AND (');
                $colSize = count($aColumns);
                for ($i = 0; $i < $colSize; $i++) {
                    $sWhereSub .= $aColumns[$i] . " LIKE '%" . $search . "%'" . ($i < $colSize - 1 ? ' OR ' : ' ');
                }
                $sWhereSub .= ')';
            }
            $sQuery = $sQuery->where($sWhereSub);
        }

        // Per-column search boxes (bSearchable_N / sSearch_N), same pattern as
        // Application_Model_DbTable_Participants::getAllParticipants().
        for ($i = 0; $i < count($aColumns); $i++) {
            if (($parameters['bSearchable_' . $i] ?? '') == 'true' && !empty($parameters['sSearch_' . $i])) {
                $sQuery = $sQuery->where($aColumns[$i] . " LIKE ?", '%' . $parameters['sSearch_' . $i] . '%');
            }
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }
        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $this->db->fetchAll($sQuery);

        $countQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT)->reset(Zend_Db_Select::LIMIT_OFFSET);
        $iFilteredTotal = count($this->db->fetchAll($countQuery));

        // Unfiltered total for this shipment+status bucket (ignores the three
        // filters above, matches the "of N" DataTables shows before any filter
        // is applied — same semantics as getAllShipments()'s iTotalRecords).
        $iTotal = (int) $this->db->fetchOne(
            "SELECT COUNT(DISTINCT sp.participant_id) FROM shipment_participant_map sp
            WHERE sp.shipment_id = ? AND sp.response_status = ?",
            [$shipmentId, $responseStatus]
        );

        $output = [
            'sEcho' => intval($parameters['sEcho'] ?? 0),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        foreach ($rResult as $aRow) {
            $output['aaData'][] = [
                $aRow['participant_id'],
                $aRow['participantName'] ?: trim($aRow['first_name'] . ' ' . $aRow['last_name']),
                $aRow['email'],
                $aRow['phone'],
                $aRow['iso_name'],
                $aRow['state'],
                $aRow['district'],
            ];
        }

        echo json_encode($output);
    }

    /**
     * Task 3 drill-down — labs on a specific shipment matching one of the three
     * response-status buckets shown in the round table: outstanding (no
     * response yet), late (responded after the deadline), or unable to test.
     *
     * Replaces the earlier single-purpose getOutstandingLabs() with a shared
     * shipment lookup + a status-keyed query, so all three round-table links
     * (Outstanding / Late / Unable to test) land on one page/method pair
     * instead of three near-identical ones.
     *
     * @param int    $shipmentId
     * @param string $status 'outstanding' | 'late' | 'unabletotest'
     * @return array|null null if the shipment doesn't exist
     */
    public function getRoundParticipantsList($shipmentId, $status)
    {
        $shipment = $this->getShipmentHeaderForDrilldown($shipmentId);
        if (!$shipment) {
            return null;
        }

        switch ($status) {
            case 'late':
                $participantIds = $this->db->fetchCol(
                    "SELECT participant_id FROM shipment_participant_map
                    WHERE shipment_id = ? AND response_status = 'late'",
                    [$shipmentId]
                );
                $context = 'Responded after the deadline for this round';
                $statusLabel = 'Late';
                break;

            case 'unabletotest':
                $participantIds = $this->db->fetchCol(
                    "SELECT participant_id FROM shipment_participant_map
                    WHERE shipment_id = ? AND response_status = 'nottested'",
                    [$shipmentId]
                );
                $context = 'Reported unable to run the PT panel this round';
                $statusLabel = 'Unable to test';
                break;

            case 'outstanding':
            default:
                // Unrecognized/missing status falls back here rather than 404ing —
                // an admin fat-fingering the URL should see something useful.
                $participantIds = $this->db->fetchCol(
                    "SELECT participant_id FROM shipment_participant_map
                    WHERE shipment_id = ? AND response_status = 'noresponse'",
                    [$shipmentId]
                );
                $context = 'No response recorded yet for this round';
                $statusLabel = 'Outstanding';
                $status = 'outstanding'; // normalize for the view (tab highlighting)
                break;
        }

        $labs = $this->fetchLabDetails($participantIds, ['context' => $context]);

        return [
            'shipment' => $shipment,
            'status' => $status,
            'statusLabel' => $statusLabel,
            'count' => count($labs),
            'labs' => $labs,
        ];
    }

    /**
     * Minimal shipment header for the drill-down page's title/breadcrumb —
     * code, scheme name, deadline, status. Deliberately NOT scoped to
     * currently-open shipments like getOpenRoundsStatus() is: this page needs
     * to keep working for a shipment that has since closed or been evaluated
     * (e.g. an admin following a bookmark or an old reminder email), so it
     * looks the shipment up directly instead of reusing the Task 3 query.
     */
    private function getShipmentHeaderForDrilldown($shipmentId)
    {
        $row = $this->db->fetchRow(
            "SELECT s.shipment_id, s.shipment_code, sl.scheme_name, s.response_deadline, s.status
            FROM shipment s
            JOIN scheme_list sl ON sl.scheme_id = s.scheme_type
            WHERE s.shipment_id = ?",
            [$shipmentId]
        );
        if (!$row) {
            return null;
        }
        return [
            'shipmentId' => $row['shipment_id'],
            'code' => strtoupper($row['shipment_code']),
            'schemeName' => $row['scheme_name'],
            'deadlineDate' => (new DateTime($row['response_deadline']))->format('Y-m-d'),
            'status' => $row['status'],
        ];
    }
}