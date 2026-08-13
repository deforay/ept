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
 *   "Open" (clarified in chat over the spec): status = 'shipped' AND
 *   response_deadline not yet crossed. response_switch, which the original
 *   spec mentioned, is NOT used anywhere in this file.
 *
 *   Also assumed: a participants table (participant_id PK) with an
 *   is_active/status column for "active participants", and scheme_list has
 *   some notion of active/inactive for "active schemes". Swap the TODO'd
 *   lines in getSummaryCounts() for whatever your real columns are, or
 *   better, call existing Application_Service_Participants /
 *   Application_Service_Schemes methods if they already compute this.
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
     * "Open" (per clarified definition) = status = 'shipped' AND deadline
     * not yet crossed. That's the strict definition used for the "N rounds
     * open for response" count.
     *
     * The TABLE, however, shows a wider set: every shipment with
     * status = 'shipped' (deadline crossed or not) — because a shipment
     * whose deadline has passed but hasn't moved past 'shipped' (i.e. not
     * yet evaluated) stays visible as a closed/red row until it is. The
     * moment a shipment moves to 'evaluated' or 'finalized' it drops out of
     * this table entirely (see class-level note on the 'evaluated' status
     * gap in PATCH_NOTES.md).
     *
     * Each row carries both 'isOpen' and 'isClosed' so callers can count
     * the strictly-open subset separately from the full row set.
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
            WHERE s.status = 'shipped'
              AND s.cancelled_at IS NULL
            GROUP BY s.shipment_id
            ORDER BY s.response_deadline ASC
        ";

        $rows = $this->db->fetchAll($sql);
        $now = new DateTime();
        $out = [];

        foreach ($rows as $row) {
            $deadline = new DateTime($row['response_deadline']);
            $diffDays = (int) $now->diff($deadline)->format('%r%a'); // signed days, future positive
            $isClosed = $deadline < $now;   // deadline crossed => not open, per clarified definition
            $isOpen = !$isClosed;           // status='shipped' (query filter) AND deadline not crossed

            if ($isClosed) {
                $deadlineLabel = $this->relativePast($now, $deadline) . ' ago';
                $rowClass = 'red';
            } elseif ($diffDays <= 3) {
                $deadlineLabel = 'In ' . $diffDays . ' day' . ($diffDays === 1 ? '' : 's');
                $rowClass = 'amber';
            } else {
                $deadlineLabel = 'In ' . $diffDays . ' days';
                $rowClass = '';
            }

            $total = (int) $row['total'];
            $responded = (int) $row['responded'];

            $out[] = [
                'shipmentId' => $row['shipment_id'],
                'code' => strtoupper($row['shipment_code']),
                'schemeName' => $row['scheme_name'],
                'deadlineLabel' => $deadlineLabel,
                'deadlineDate' => $deadline->format('Y-m-d'),
                'isOpen' => $isOpen,
                'isClosed' => $isClosed,
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
     * Strictly-open count for the table header ("N rounds open for
     * response") — a subset of getOpenRoundsStatus()'s rows.
     */
    public function countStrictlyOpenRounds(array $rounds)
    {
        return count(array_filter($rounds, function ($r) {
            return $r['isOpen'];
        }));
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

    private function relativePast(DateTime $now, DateTime $then)
    {
        $days = $now->diff($then)->days;
        if ($days === 0) {
            return 'today';
        }
        return $days . ' day' . ($days === 1 ? '' : 's');
    }
}