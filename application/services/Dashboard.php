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
 *   The round table (Task 3) carries every non-finalized, non-cancelled
 *   shipment. "Open for response" — response_switch = 'on' AND the deadline
 *   still ahead — only decides how the row is labelled, not whether it
 *   appears: a closed round still needs evaluating and finalizing, and the
 *   between-rounds card covers finalized rounds only, so dropping it here
 *   would leave it visible nowhere.
 *
 *   "Active participants" / "active schemes" (Task 2) come from
 *   Application_Service_Schemes::countEnrollmentSchemes(). "Out of enrolled"
 *   (Task 4) does NOT — that method keys its result by
 *   strtoupper(scheme_name), and reading it by raw scheme_name silently
 *   yielded 0 on every card. See getEnrolledCount().
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
    /**
     * How far back a lab's response still counts as "actively participating".
     * Long enough to cover an annual scheme, short enough that a lab which
     * stopped answering drops out of the count.
     */
    public const ENGAGEMENT_WINDOW_MONTHS = 12;

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
     * Task 2 — top strip counts.
     */
    public function getSummaryCounts()
    {
        // Counted directly rather than by summing Schemes::countEnrollmentSchemes(),
        // which was the original source: a lab enrolled in three schemes appears
        // in three of those buckets, so the sum overstated the headcount on
        // every multi-scheme instance. It also has to agree with the engagement
        // donut, which is the same population.
        $activeParticipants = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM participant WHERE status = 'active'"
        );

        $roundsCompletedThisYear = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM shipment
             WHERE status = 'finalized'
               AND cancelled_at IS NULL
               AND YEAR(finalized_at) = YEAR(CURDATE())"
        );

        // Supporting line under the year count. An instance that has finalized
        // nothing this year still reads as alive if the last round it did
        // finalize is named.
        $lastFinalized = $this->db->fetchOne(
            "SELECT MAX(finalized_at) FROM shipment
             WHERE status = 'finalized' AND cancelled_at IS NULL"
        );

        // No 'activeSchemes' here: the strip's scheme count now comes from
        // getSchemeEngagement(), so the number under Active participants and
        // the bars in the scheme chart can never disagree.
        return [
            'activeParticipants' => $activeParticipants,
            'roundsCompletedThisYear' => $roundsCompletedThisYear,
            'lastFinalizedDate' => $lastFinalized ? date('d M Y', strtotime($lastFinalized)) : null,
        ];
    }

    /**
     * Task 3 — table rows.
     *
     * Every non-finalized, non-cancelled shipment appears here, including ones
     * whose deadline has gone by. A closed round still needs evaluating and
     * finalizing, so per the spec's decision tree it stays in the table with a
     * "Closed" chip rather than dropping out of the dashboard — an intermediate
     * revision excluded them on deadline, which left a round awaiting
     * evaluation visible nowhere at all (the between-rounds card only covers
     * finalized rounds).
     *
     * "Open for response" is the spec's definition — response_switch = 'on'
     * AND the deadline still ahead. The switch is what actually gates result
     * entry (cron flips it at the deadline when auto_close_at_deadline is
     * 'yes'), so a manually closed round reads as Closed even with days left.
     *
     * Newest deadline first, by explicit instruction — not the spec's
     * soonest-first. Now that closed rounds stay in the table, soonest-first
     * would head the list with the oldest unfinalized round on the system.
     */
    public function getOpenRoundsStatus()
    {
        $sql = "
            SELECT
                s.shipment_id,
                s.shipment_code,
                sl.scheme_name,
                s.response_deadline,
                s.response_switch,
                s.shipment_date,
                s.average_score,
                s.evaluated_at,
                s.reports_generated_at,
                COUNT(sp.participant_id) AS total,
                SUM(sp.response_status IS NOT NULL AND sp.response_status NOT IN ('', 'noresponse')) AS responded,
                SUM(sp.response_status = 'late') AS late,
                SUM(sp.response_status = 'nottested') AS unable_to_test,
                SUM(sp.final_result IN (1, 2, 3, 4)) AS evaluated,
                SUM(sp.final_result = 1) AS passed
            FROM shipment s
            JOIN scheme_list sl ON sl.scheme_id = s.scheme_type
            JOIN shipment_participant_map sp ON sp.shipment_id = s.shipment_id
            WHERE s.status != 'finalized'
              AND s.cancelled_at IS NULL
            GROUP BY s.shipment_id
            ORDER BY s.response_deadline DESC
        ";

        $rows = $this->db->fetchAll($sql);
        $now = new DateTime();
        $out = [];

        foreach ($rows as $row) {
            $deadline = new DateTime($row['response_deadline']);
            $diffDays = (int) $now->diff($deadline)->format('%r%a'); // signed days, future positive
            $deadlinePassed = $deadline < $now;
            $switchOn = strtolower((string) $row['response_switch']) === 'on';
            $isOpen = $switchOn && !$deadlinePassed;

            // The chip's wording is composed in the view, not here: this is a
            // sentence with a number in it, and building it in PHP would put
            // "Closed 17 days ago" past the translator. The view gets a kind
            // and a day count and picks a translatable phrase for each.
            $deadlineDays = abs($diffDays);
            if ($isOpen) {
                $deadlineKind = $diffDays === 0 ? 'today' : 'future';
                // Amber only inside the last 3 days; a round with a month to
                // run shouldn't be tinted like it needs chasing.
                $rowClass = $diffDays <= 3 ? 'amber' : '';
                $chipClass = $diffDays <= 3 ? 'chip-amber' : '';
            } else {
                if (!$deadlinePassed) {
                    // Switched off ahead of the deadline — closed by hand.
                    $deadlineKind = 'closed';
                } elseif ($deadlineDays === 0) {
                    $deadlineKind = 'closed-today';
                } else {
                    $deadlineKind = 'closed-past';
                }
                // No row accent: on most instances every round is closed and
                // waiting to be finalized, so accenting them all says nothing.
                // The count in the panel heading carries that signal instead.
                $rowClass = '';
                $chipClass = 'chip-closed';
            }

            $total = (int) $row['total'];
            $responded = (int) $row['responded'];

            $out[] = [
                'shipmentId' => $row['shipment_id'],
                'code' => strtoupper($row['shipment_code']),
                'schemeName' => $row['scheme_name'],
                'isOpen' => $isOpen,
                'deadlineKind' => $deadlineKind,
                'deadlineDays' => $deadlineDays,
                'deadlineDate' => $deadline->format('Y-m-d'),
                // The chip says "Closed 17 days ago", which is the urgency but
                // not the fact. The actual date is printed under it.
                'deadlineDateLong' => $deadline->format('d M Y'),
                'shippedDate' => $row['shipment_date']
                    ? date('d M Y', strtotime($row['shipment_date']))
                    : null,
                'rowClass' => $rowClass,
                'chipClass' => $chipClass,
                'total' => $total,
                'responded' => $responded,
                'outstanding' => max(0, $total - $responded),
                'late' => (int) $row['late'],
                'unableToTest' => (int) $row['unable_to_test'],
                'evaluated' => (int) $row['evaluated'],
                // Share of the shipment that came back. The Responded column
                // carries the counts; this is the figure you compare across
                // rounds of different sizes.
                'responseRate' => $total > 0
                    ? (int) round(($responded / $total) * 100)
                    : null,
                // Out of the labs that actually got a verdict, not out of the
                // whole shipment — otherwise an unevaluated round reads as 0%
                // passing rather than "not scored yet". Null when nothing has
                // been evaluated, so a blank cell means "no verdict yet"
                // rather than "everybody failed".
                'passRate' => (int) $row['evaluated'] > 0
                    ? (int) round(((int) $row['passed'] / (int) $row['evaluated']) * 100)
                    : null,
                'nextAction' => $this->getNextAction($row, $isOpen),
            ];
        }

        return $out;
    }

    /**
     * What this round is waiting on, and where to go and do it.
     *
     * Reads the shipment's own progress stamps rather than `status`: a round
     * moves shipped → evaluated → reports generated → finalized, and only the
     * timestamps distinguish the middle two. Everything here is by definition
     * unfinalized, since getOpenRoundsStatus() excludes finalized rounds.
     *
     * Labels come back translated — the view escapes them and does not
     * translate again.
     *
     * @return array{label: string, module: string, controller: string, action: string, param: string}
     */
    private function getNextAction(array $row, $isOpen)
    {
        $sid = base64_encode((string) $row['shipment_id']);

        if ($isOpen) {
            return [
                'key' => 'awaiting-responses',
                'label' => Pt_Commons_TranslateUtility::safeTranslate('Awaiting responses'),
                'module' => 'admin',
                'controller' => 'shipment',
                'action' => 'view',
                'param' => 'id',
                'value' => $row['shipment_id'],
            ];
        }

        if (empty($row['evaluated_at'])) {
            // Nobody submitted anything, so there is nothing to score.
            // "Ready to evaluate" is technically true and practically
            // useless — send the admin to the labs to chase instead of to an
            // evaluation screen that would open with no rows in it.
            if ((int) $row['responded'] === 0) {
                return [
                    'key' => 'no-responses',
                    'label' => Pt_Commons_TranslateUtility::safeTranslate('No responses received'),
                    'module' => 'admin',
                    'controller' => 'shipment',
                    'action' => 'round-participants',
                    'param' => 'id',
                    'value' => $row['shipment_id'],
                ];
            }

            return [
                'key' => 'evaluate',
                'label' => Pt_Commons_TranslateUtility::safeTranslate('Ready to evaluate'),
                'module' => 'admin',
                'controller' => 'evaluate',
                'action' => 'shipment',
                'param' => 'sid',
                'value' => $sid,
            ];
        }

        if (empty($row['reports_generated_at'])) {
            return [
                'key' => 'reports',
                'label' => Pt_Commons_TranslateUtility::safeTranslate('Generate reports'),
                'module' => 'reports',
                'controller' => 'distribution',
                'action' => 'shipment',
                'param' => 'sid',
                'value' => $sid,
            ];
        }

        return [
            'key' => 'finalize',
            'label' => Pt_Commons_TranslateUtility::safeTranslate('Ready to finalize'),
            'module' => 'reports',
            'controller' => 'distribution',
            'action' => 'shipment',
            'param' => 'sid',
            'value' => $sid,
        ];
    }

    /**
     * Unfinalized rounds grouped by what each is waiting on, in pipeline order.
     *
     * Derived from getOpenRoundsStatus() rather than queried, so the chart costs
     * nothing and can never disagree with the table beneath it.
     *
     * Stages with no rounds are kept. A pipeline that reads
     * "evaluate 8, reports 0, finalize 4" says something a chart with the empty
     * bar dropped does not.
     *
     * @param array<int, array<string, mixed>> $openRounds
     * @return array<int, array{key: string, label: string, count: int, waitingOnLabs: bool}>
     */
    public function summarisePipeline(array $openRounds)
    {
        // Translated where the literal is written, not later through
        // safeTranslate($stage['label']). xgettext only sees string literals
        // sitting inside the call parentheses, so a label translated via a
        // variable never reaches the POT and refresh-translations.php drops
        // its catalog entry as obsolete. These five survived only because the
        // same wording appears as a literal in getNextAction().
        $stages = [
            'awaiting-responses' => [
                'label' => Pt_Commons_TranslateUtility::safeTranslate('Awaiting responses'),
                'waitingOnLabs' => true,
            ],
            'no-responses' => [
                'label' => Pt_Commons_TranslateUtility::safeTranslate('No responses received'),
                'waitingOnLabs' => false,
            ],
            'evaluate' => [
                'label' => Pt_Commons_TranslateUtility::safeTranslate('Ready to evaluate'),
                'waitingOnLabs' => false,
            ],
            'reports' => [
                'label' => Pt_Commons_TranslateUtility::safeTranslate('Generate reports'),
                'waitingOnLabs' => false,
            ],
            'finalize' => [
                'label' => Pt_Commons_TranslateUtility::safeTranslate('Ready to finalize'),
                'waitingOnLabs' => false,
            ],
        ];

        $counts = array_fill_keys(array_keys($stages), 0);
        foreach ($openRounds as $round) {
            $key = $round['nextAction']['key'] ?? null;
            if ($key !== null && array_key_exists($key, $counts)) {
                $counts[$key]++;
            }
        }

        $out = [];
        foreach ($stages as $key => $stage) {
            $out[] = [
                'key' => $key,
                'label' => $stage['label'],
                'count' => $counts[$key],
                'waitingOnLabs' => $stage['waitingOnLabs'],
            ];
        }

        return $out;
    }

    /**
     * Distinct labs still owing a response on a round that is open for
     * response, and distinct labs in those rounds.
     *
     * Two things this deliberately is not:
     *
     * Not a sum over the round table. Adding each round's outstanding count
     * together counts a lab once per round it owes, which reported "22,560
     * labs yet to respond" on a Zimbabwe instance with 12,474 labs in total.
     * The card says labs, so it counts labs.
     *
     * Not every unfinalized round. A closed round's non-responders can no
     * longer respond, so putting them under a heading the admin is meant to
     * act on made a number nobody could move. Those labs are still reachable
     * through the table's outstanding links and the engagement donut. "Open
     * for response" matches getOpenRoundsStatus(): switch on, deadline ahead.
     *
     * @return array{outstanding: int, total: int}
     */
    public function getOutstandingLabs()
    {
        $row = $this->db->fetchRow(
            "SELECT
                COUNT(DISTINCT sp.participant_id) AS total,
                COUNT(DISTINCT CASE
                    WHEN sp.response_status IS NULL
                      OR sp.response_status IN ('', 'noresponse')
                    THEN sp.participant_id END) AS outstanding
             FROM shipment_participant_map sp
             JOIN shipment s ON s.shipment_id = sp.shipment_id
            WHERE s.status != 'finalized'
              AND s.cancelled_at IS NULL
              AND LOWER(s.response_switch) = 'on'
              AND s.response_deadline >= NOW()"
        );

        return [
            'outstanding' => (int) $row['outstanding'],
            'total' => (int) $row['total'],
        ];
    }

    /**
     * The whole active participant base, split by how engaged it is.
     *
     * Deliberately not a per-round cut: the round table and its Response %
     * column already answer "how is this round doing". This answers a question
     * nothing else on the page answers — of every lab on the books, how many
     * are actually taking part.
     *
     * "In a scheme" means enrolled in one OR shipped a round of one, not
     * enrolled alone. The enrollments table is optional in practice: on the
     * Malawi instance only `dts` has enrollment rows, while HBV and Syphilis
     * each have ~6,500 labs that only ever arrive through a shipment. Reading
     * enrollment alone reported those schemes as empty.
     *
     * The three buckets are disjoint and sum to the active participant count:
     *   participating    responded to a round shipped inside the window
     *   inSchemeIdle     in a scheme, but responded to nothing in the window
     *   notInScheme      active on the books, in no scheme at all
     *
     * A window is needed or the first bucket only ever grows: a lab that
     * answered once in 2019 is not "actively participating" today. Retired
     * schemes are excluded by scheme_list.status, so a scheme nobody runs any
     * more does not sit here as a permanent block of red.
     *
     * @return array{total: int, windowMonths: int, buckets: array<int, array{key: string, label: string, count: int}>}
     */
    public function getParticipantEngagement()
    {
        $window = self::ENGAGEMENT_WINDOW_MONTHS;

        $totalActive = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM participant WHERE status = 'active'"
        );

        $inScheme = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM (
                SELECT e.participant_id
                  FROM enrollments e
                  JOIN participant p ON p.participant_id = e.participant_id AND p.status = 'active'
                  JOIN scheme_list sl ON sl.scheme_id = e.scheme_id AND sl.status = 'active'
                UNION
                SELECT sp.participant_id
                  FROM shipment_participant_map sp
                  JOIN shipment s ON s.shipment_id = sp.shipment_id AND s.cancelled_at IS NULL
                  JOIN participant p ON p.participant_id = sp.participant_id AND p.status = 'active'
                  JOIN scheme_list sl ON sl.scheme_id = s.scheme_type AND sl.status = 'active'
             ) membership"
        );

        $participating = (int) $this->db->fetchOne(
            "SELECT COUNT(DISTINCT sp.participant_id)
               FROM shipment_participant_map sp
               JOIN shipment s ON s.shipment_id = sp.shipment_id AND s.cancelled_at IS NULL
               JOIN participant p ON p.participant_id = sp.participant_id AND p.status = 'active'
               JOIN scheme_list sl ON sl.scheme_id = s.scheme_type AND sl.status = 'active'
              WHERE s.shipment_date >= DATE_SUB(CURDATE(), INTERVAL {$window} MONTH)
                AND sp.response_status IS NOT NULL
                AND sp.response_status NOT IN ('', 'noresponse')"
        );

        // Clamped so a lab that responded under a scheme it has since left can
        // never push the buckets past the total.
        $inScheme = min($inScheme, $totalActive);
        $participating = min($participating, $inScheme);

        // Same reason as summarisePipeline(): translate the literal in place.
        // "Not in any scheme" appears nowhere else in the codebase, so when it
        // was translated through a variable it never reached the POT and the
        // first catalog refresh deleted its fr and vi translations.
        $buckets = [
            [
                'key' => 'participating',
                'label' => Pt_Commons_TranslateUtility::safeTranslate('Actively participating'),
                'count' => $participating,
            ],
            [
                'key' => 'in-scheme-idle',
                'label' => Pt_Commons_TranslateUtility::safeTranslate('In a scheme, not responding'),
                'count' => $inScheme - $participating,
            ],
            [
                'key' => 'no-scheme',
                'label' => Pt_Commons_TranslateUtility::safeTranslate('Not in any scheme'),
                'count' => $totalActive - $inScheme,
            ],
        ];

        return [
            'total' => $totalActive,
            'windowMonths' => $window,
            'buckets' => $buckets,
        ];
    }

    /**
     * The same engagement split, per scheme, for a stacked bar.
     *
     * The donut says how big the idle share is. This says which scheme it sits
     * in, which is the part that decides who to chase.
     *
     * Membership is enrolment UNION shipment history, for the reason given on
     * getParticipantEngagement(). Only the participating half is windowed, so a
     * scheme that has shipped nothing for a year still appears — as a full
     * amber bar, which is the finding.
     *
     * @return array<int, array{schemeId: mixed, schemeName: string, enrolled: int, participating: int, notResponding: int}>
     */
    public function getSchemeEngagement()
    {
        $window = self::ENGAGEMENT_WINDOW_MONTHS;

        $rows = $this->db->fetchAll(
            "SELECT
                sl.scheme_id,
                sl.scheme_name,
                COUNT(DISTINCT m.participant_id) AS enrolled,
                COUNT(DISTINCT CASE WHEN m.responded = 1 THEN m.participant_id END) AS participating
             FROM (
                SELECT e.scheme_id, e.participant_id, 0 AS responded
                  FROM enrollments e
                  JOIN participant p ON p.participant_id = e.participant_id AND p.status = 'active'
                UNION ALL
                SELECT s.scheme_type AS scheme_id, sp.participant_id,
                       CASE WHEN s.shipment_date >= DATE_SUB(CURDATE(), INTERVAL {$window} MONTH)
                             AND sp.response_status IS NOT NULL
                             AND sp.response_status NOT IN ('', 'noresponse')
                            THEN 1 ELSE 0 END AS responded
                  FROM shipment_participant_map sp
                  JOIN shipment s ON s.shipment_id = sp.shipment_id AND s.cancelled_at IS NULL
                  JOIN participant p ON p.participant_id = sp.participant_id AND p.status = 'active'
             ) m
             JOIN scheme_list sl ON sl.scheme_id = m.scheme_id AND sl.status = 'active'
             GROUP BY sl.scheme_id, sl.scheme_name
             HAVING enrolled > 0
             ORDER BY enrolled DESC, sl.scheme_name ASC"
        );

        $out = [];
        foreach ($rows as $row) {
            $enrolled = (int) $row['enrolled'];
            $participating = min((int) $row['participating'], $enrolled);
            $out[] = [
                'schemeId' => $row['scheme_id'],
                'schemeName' => trim((string) $row['scheme_name']),
                'enrolled' => $enrolled,
                'participating' => $participating,
                'notResponding' => $enrolled - $participating,
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

        foreach ($latestShipments as $shipment) {
            $schemeType = $shipment['scheme_type'];
            $shipmentId = $shipment['shipment_id'];

            $schemeName = $this->db->fetchOne(
                'SELECT scheme_name FROM scheme_list WHERE scheme_id = ?',
                [$schemeType]
            );

            // Enrolled count "out of enrolled" — not out of this shipment's
            // participant list. Queried by scheme_id rather than read out of
            // countEnrollmentSchemes(): that method keys its array by
            // strtoupper(scheme_name), so the earlier lookup by raw
            // scheme_list.scheme_name never matched and every card silently
            // rendered "out of enrolled 0".
            $enrolledCount = $this->getEnrolledCount($schemeType);

            // The three participation buckets are disjoint here, unlike the
            // round table's "responded" (which folds late + nottested in so
            // that responded + outstanding = total). The card prints all
            // three side by side against `enrolled`, so counting a lab as
            // both "took part" and "unable to test" would read as an error.
            // Blank/NULL response_status is treated as no-response, matching
            // the column's 'noresponse' default.
            $participantStats = $this->db->fetchRow(
                "SELECT
                    COUNT(*) AS in_shipment,
                    SUM(response_status IS NOT NULL AND response_status NOT IN ('', 'noresponse', 'nottested')) AS took_part,
                    SUM(response_status IS NULL OR response_status IN ('', 'noresponse')) AS no_response,
                    SUM(response_status = 'nottested') AS unable_to_test,
                    SUM(final_result = 1) AS pass,
                    SUM(final_result = 2) AS fail,
                    SUM(final_result = 3) AS excluded,
                    SUM(final_result = 4) AS not_evaluated,
                    SUM(final_result IS NULL OR final_result NOT IN (1, 2, 3, 4)) AS unrecorded,
                    SUM(individual_report_downloaded_on IS NOT NULL
                        OR JSON_LENGTH(report_download_metadata) > 0) AS report_opened
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

            // Both stay null when there is no earlier round to compare
            // against — "no previous round" and "genuinely zero" have to be
            // distinguishable on the card, and a plain 0 conflates them.
            $scoreDelta = null;
            $repeatFailerCount = null;
            if ($previousShipment) {
                $scoreDelta = round($shipment['average_score'] - $previousShipment['average_score'], 1);

                // Repeat failers: failed this round AND failed the round before.
                $repeatFailerCount = (int) $this->db->fetchOne(
                    'SELECT COUNT(*) FROM shipment_participant_map cur
                     JOIN shipment_participant_map prev
                       ON prev.participant_id = cur.participant_id
                      AND prev.shipment_id = ?
                     WHERE cur.shipment_id = ?
                       AND cur.final_result = 2
                       AND prev.final_result = 2',
                    [$previousShipment['shipment_id'], $shipmentId]
                );
            }

            // Never responded across this scheme's last few finalized rounds.
            // The window is up to 3 rounds but tolerates 2, and the actual
            // size travels with the count so the card can say which window it
            // means. Previously this required exactly 3 rounds and returned a
            // bare 0 otherwise, which read as "no problem labs" on any scheme
            // that had only run once or twice.
            $windowShipmentIds = $this->db->fetchCol(
                "SELECT shipment_id FROM shipment
                 WHERE scheme_type = ? AND status = 'finalized' AND cancelled_at IS NULL
                 ORDER BY finalized_at DESC
                 LIMIT 3",
                [$schemeType]
            );
            $neverRespondedWindow = count($windowShipmentIds);
            $neverRespondedCount = null;
            if ($neverRespondedWindow >= 2) {
                $placeholders = implode(',', array_fill(0, $neverRespondedWindow, '?'));
                $neverRespondedCount = (int) $this->db->fetchOne(
                    "SELECT COUNT(*) FROM (
                        SELECT participant_id
                        FROM shipment_participant_map
                        WHERE shipment_id IN ($placeholders)
                        GROUP BY participant_id
                        HAVING SUM(response_status IS NOT NULL
                                   AND response_status NOT IN ('', 'noresponse')) = 0
                     ) t",
                    $windowShipmentIds
                );
            }

            $tookPart = (int) $participantStats['took_part'];

            $cards[] = [
                'shipmentId' => $shipmentId,
                'schemeName' => $schemeName,
                'roundCode' => strtoupper($shipment['shipment_code']),
                // Always carries the year: the between-rounds heading reads
                // "Last round finalized 31 Jul", which on an instance whose
                // newest round closed in a previous year looks like a stale
                // page rather than an old round.
                'finalizedDate' => date('d M Y', strtotime($shipment['finalized_at'])),
                'enrolledCount' => $enrolledCount,
                'inShipment' => (int) $participantStats['in_shipment'],
                'tookPart' => $tookPart,
                'noResponse' => (int) $participantStats['no_response'],
                'unableToTest' => (int) $participantStats['unable_to_test'],
                'pass' => (int) $participantStats['pass'],
                'fail' => (int) $participantStats['fail'],
                'excluded' => (int) $participantStats['excluded'],
                'notEvaluated' => (int) $participantStats['not_evaluated'],
                // Labs the evaluation never stamped a verdict on. Without this
                // the results line quietly drops them and stops reconciling
                // with "took part".
                'unrecorded' => (int) $participantStats['unrecorded'],
                'averageScore' => $shipment['average_score'],
                'scoreDelta' => $scoreDelta,
                'previousRoundCode' => $previousShipment ? strtoupper($previousShipment['shipment_code']) : null,
                'repeatFailerCount' => $repeatFailerCount,
                'neverRespondedCount' => $neverRespondedCount,
                'neverRespondedWindow' => $neverRespondedWindow,
                'reportOpenedCount' => (int) $participantStats['report_opened'],
            ];
        }

        return $cards;
    }

    /**
     * Active participants enrolled in a scheme.
     *
     * Same definition Application_Model_DbTable_SchemeList::countEnrollmentSchemes()
     * uses (active participant × enrollment row), queried by scheme_id so the
     * caller doesn't have to know that method keys its result by
     * strtoupper(scheme_name) — which is what broke the between-rounds card.
     */
    private function getEnrolledCount($schemeId)
    {
        return (int) $this->db->fetchOne(
            "SELECT COUNT(DISTINCT e.participant_id)
             FROM enrollments e
             JOIN participant p ON p.participant_id = e.participant_id
             WHERE e.scheme_id = ? AND p.status = 'active'",
            [$schemeId]
        );
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

        // Resolved once, up front: 'repeatfailers' and 'neverresponded' are
        // cross-shipment buckets, so the draw filters on the resolved
        // participant IDs rather than on a response_status equality. Shipments
        // here run to a few hundred labs, so the IN list stays small.
        $bucket = $this->resolveDrilldown($shipment, $status);
        $bucketIds = $bucket['ids'];

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
            // Empty bucket: a sentinel that matches no participant, since an
            // empty IN () list is a SQL syntax error and DataTables still
            // needs a well-formed empty response.
            ->where('p.participant_id IN (?)', $bucketIds ?: [0])
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
                // $aColumns is a fixed whitelist above, but $search is raw request
                // input, so it is bound rather than concatenated.
                $orParts = [];
                foreach ($aColumns as $column) {
                    $orParts[] = $this->db->quoteInto(((string) $column) . ' LIKE ?', '%' . $search . '%');
                }
                $sWhereSub .= implode(' OR ', $orParts) . ')';
            }
            $sQuery = $sQuery->where($sWhereSub);
        }

        // Per-column search boxes (bSearchable_N / sSearch_N), same pattern as
        // Application_Model_DbTable_Participants::getAllParticipants().
        for ($i = 0; $i < count($aColumns); $i++) {
            if (($parameters['bSearchable_' . $i] ?? '') == 'true' && !empty($parameters['sSearch_' . $i])) {
                $sQuery = $sQuery->where($aColumns[$i] . ' LIKE ?', '%' . $parameters['sSearch_' . $i] . '%');
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
        $iTotal = count($bucketIds);

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
     * Drill-down — the labs behind one linked number on the dashboard, for
     * whichever bucket the URL names. Both dashboard blocks land here: the
     * round table's Outstanding / Late / Unable-to-test columns, and the
     * between-rounds card's Failed / Repeat failers / Never responded figures.
     *
     * The bucket is resolved by resolveDrilldown(), shared with the DataTables
     * draw so the two can't disagree about what a status means.
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

        $bucket = $this->resolveDrilldown($shipment, $status);
        $labs = $this->fetchLabDetails($bucket['ids'], ['context' => $bucket['context']]);

        return [
            'shipment' => $shipment,
            'status' => $bucket['status'],
            'statusLabel' => $bucket['label'],
            'tabs' => self::drilldownStatuses($shipment['status']),
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
            'SELECT s.shipment_id, s.shipment_code, s.scheme_type, sl.scheme_name,
                    s.response_deadline, s.status, s.finalized_at
            FROM shipment s
            JOIN scheme_list sl ON sl.scheme_id = s.scheme_type
            WHERE s.shipment_id = ?',
            [$shipmentId]
        );
        if (!$row) {
            return null;
        }
        return [
            'shipmentId' => $row['shipment_id'],
            'code' => strtoupper($row['shipment_code']),
            'schemeType' => $row['scheme_type'],
            'schemeName' => $row['scheme_name'],
            'deadlineDate' => (new DateTime($row['response_deadline']))->format('Y-m-d'),
            'status' => $row['status'],
            'finalizedAt' => $row['finalized_at'],
        ];
    }

    /**
     * Drill-down buckets the round-participants page offers, keyed by URL
     * status, valued by tab label. Which set applies depends on the shipment:
     * an open round is described by where its responses stand, a finalized one
     * by the follow-up the between-rounds card points at.
     *
     * @param string $shipmentStatus shipment.status
     * @return array<string, string>
     */
    public static function drilldownStatuses($shipmentStatus)
    {
        if ($shipmentStatus === 'finalized') {
            return [
                'failed' => Pt_Commons_TranslateUtility::safeTranslate('Failed'),
                'repeatfailers' => Pt_Commons_TranslateUtility::safeTranslate('Repeat failers'),
                'neverresponded' => Pt_Commons_TranslateUtility::safeTranslate('Never responded'),
                'outstanding' => Pt_Commons_TranslateUtility::safeTranslate('No response'),
                'unabletotest' => Pt_Commons_TranslateUtility::safeTranslate('Unable to test'),
            ];
        }

        return [
            'outstanding' => Pt_Commons_TranslateUtility::safeTranslate('Outstanding'),
            'late' => Pt_Commons_TranslateUtility::safeTranslate('Late'),
            'unabletotest' => Pt_Commons_TranslateUtility::safeTranslate('Unable to test'),
        ];
    }

    /**
     * Resolves a drill-down status to the participant IDs it covers, plus the
     * heading copy for the page.
     *
     * Shared by getRoundParticipantsList() (initial page load) and
     * getRoundParticipantsDataTable() (the AJAX draw) so both agree on what a
     * status means — the two used to carry their own copy of the
     * status→response_status mapping, which only worked while every bucket was
     * a plain equality on one column. 'repeatfailers' and 'neverresponded'
     * span shipments, so they can't be.
     *
     * An unrecognized status falls back to the shipment's first bucket rather
     * than 404ing: an admin fat-fingering the URL should see something useful.
     *
     * @return array{ids: int[], status: string, label: string, context: string}
     */
    private function resolveDrilldown(array $shipment, $status)
    {
        $shipmentId = $shipment['shipmentId'];
        $available = self::drilldownStatuses($shipment['status']);
        if (!isset($available[$status])) {
            $status = (string) array_key_first($available);
        }

        switch ($status) {
            case 'late':
                $ids = $this->db->fetchCol(
                    "SELECT participant_id FROM shipment_participant_map
                    WHERE shipment_id = ? AND response_status = 'late'",
                    [$shipmentId]
                );
                $context = Pt_Commons_TranslateUtility::safeTranslate('Responded after the deadline for this round');
                break;

            case 'unabletotest':
                $ids = $this->db->fetchCol(
                    "SELECT participant_id FROM shipment_participant_map
                    WHERE shipment_id = ? AND response_status = 'nottested'",
                    [$shipmentId]
                );
                $context = Pt_Commons_TranslateUtility::safeTranslate('Reported unable to run the PT panel this round');
                break;

            case 'failed':
                $ids = $this->db->fetchCol(
                    'SELECT participant_id FROM shipment_participant_map
                    WHERE shipment_id = ? AND final_result = 2',
                    [$shipmentId]
                );
                $context = Pt_Commons_TranslateUtility::safeTranslate('Failed this round');
                break;

            case 'repeatfailers':
                // Failed this round and the scheme's previous finalized round.
                $previousShipmentId = $this->db->fetchOne(
                    "SELECT shipment_id FROM shipment
                    WHERE scheme_type = ? AND status = 'finalized' AND cancelled_at IS NULL
                      AND finalized_at < ?
                    ORDER BY finalized_at DESC
                    LIMIT 1",
                    [$shipment['schemeType'], $shipment['finalizedAt']]
                );
                $ids = $previousShipmentId ? $this->db->fetchCol(
                    'SELECT cur.participant_id FROM shipment_participant_map cur
                    JOIN shipment_participant_map prev
                      ON prev.participant_id = cur.participant_id
                     AND prev.shipment_id = ?
                    WHERE cur.shipment_id = ?
                      AND cur.final_result = 2
                      AND prev.final_result = 2',
                    [$previousShipmentId, $shipmentId]
                ) : [];
                $context = Pt_Commons_TranslateUtility::safeTranslate('Failed this round and the one before it');
                break;

            case 'neverresponded':
                $windowShipmentIds = $this->db->fetchCol(
                    "SELECT shipment_id FROM shipment
                    WHERE scheme_type = ? AND status = 'finalized' AND cancelled_at IS NULL
                    ORDER BY finalized_at DESC
                    LIMIT 3",
                    [$shipment['schemeType']]
                );
                $ids = [];
                if (count($windowShipmentIds) >= 2) {
                    $placeholders = implode(',', array_fill(0, count($windowShipmentIds), '?'));
                    $ids = $this->db->fetchCol(
                        "SELECT participant_id
                        FROM shipment_participant_map
                        WHERE shipment_id IN ($placeholders)
                        GROUP BY participant_id
                        HAVING SUM(response_status IS NOT NULL
                                   AND response_status NOT IN ('', 'noresponse')) = 0",
                        $windowShipmentIds
                    );
                }
                $context = sprintf(
                    Pt_Commons_TranslateUtility::safeTranslate('No response across this scheme\'s last %d finalized rounds'),
                    count($windowShipmentIds)
                );
                break;

            case 'outstanding':
            default:
                $ids = $this->db->fetchCol(
                    "SELECT participant_id FROM shipment_participant_map
                    WHERE shipment_id = ?
                      AND (response_status IS NULL OR response_status IN ('', 'noresponse'))",
                    [$shipmentId]
                );
                // Each literal is its own direct argument: xgettext only
                // extracts string literals passed straight to a keyword, so a
                // ternary *inside* the call leaves both strings out of the POT
                // and they can never be translated.
                $context = $shipment['status'] === 'finalized'
                    ? Pt_Commons_TranslateUtility::safeTranslate('Never responded to this round')
                    : Pt_Commons_TranslateUtility::safeTranslate('No response recorded yet for this round');
                $status = 'outstanding'; // normalize for the view (tab highlighting)
                break;
        }

        return [
            'ids' => array_map('intval', $ids),
            'status' => $status,
            'label' => $available[$status],
            'context' => $context,
        ];
    }
}
