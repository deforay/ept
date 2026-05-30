<?php
// process-shipment-deadlines.php
//
// Auto-flip shipment.response_switch from 'on' to 'off' once the response
// deadline (response_deadline, a DATETIME) has passed — but ONLY for shipments
// that opted in via auto_close_at_deadline='yes'. Shipments that didn't opt in
// keep the historical "allow response after due date" behaviour (late responses
// accepted, just flagged). The deadline's time component is the exact close time;
// a date-only value (or 23:59:59) means end of day. The instant is interpreted in
// the cutoff timezone via Pt_Commons_DateUtility::shipmentCutoff() — the same
// logic the evaluation late-check uses — so cron and reports agree. Finalized
// shipments are left untouched. Idempotent — re-runs flip nothing already off.
//
// After closing a shipment, an evaluation is queued for it by default so results
// are ready for human review (reports + finalization stay manual). Pass --skip-eval
// to only flip the switch and not auto-evaluate.

require_once __DIR__ . '/../cli-bootstrap.php';

$options = getopt('', ['skip-eval']);
$skipEval = isset($options['skip-eval']);

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$db = Zend_Db::factory($conf->resources->db);
Zend_Db_Table::setDefaultAdapter($db);

try {
    // Candidates: currently open, not finalized, with a real deadline date.
    // The TZ-aware "has it actually passed?" test is done in PHP below so we
    // reuse the exact cutoff logic the rest of the app uses.
    $candidates = $db->fetchAll(
        "SELECT shipment_id, response_deadline
           FROM shipment
          WHERE response_switch = 'on'
            AND auto_close_at_deadline = 'yes'
            AND (status IS NULL OR status != 'finalized')
            AND response_deadline IS NOT NULL
            AND response_deadline != '0000-00-00'"
    );

    if (empty($candidates)) {
        return;
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $expiredIds = [];

    foreach ($candidates as $row) {
        $cutoff = Pt_Commons_DateUtility::shipmentCutoff($row['response_deadline']);
        // Strictly after the cutoff instant => the deadline day is over.
        if ($cutoff !== null && $now > $cutoff) {
            $expiredIds[] = (int) $row['shipment_id'];
        }
    }

    if (empty($expiredIds)) {
        return;
    }

    $closed = $db->update(
        'shipment',
        ['response_switch' => 'off'],
        'shipment_id IN (' . implode(',', $expiredIds) . ')'
    );

    if ($closed > 0) {
        Pt_Commons_LoggerUtility::logInfo("process-shipment-deadlines: turned off response_switch for {$closed} expired shipment(s): " . implode(',', $expiredIds));

        // Auto-evaluate each just-closed shipment (default; --skip-eval opts out) so results
        // are ready for human review. Reports and finalization stay manual. Re-evaluation is
        // intentional — it captures responses that landed before the deadline. System context:
        // no admin session, so requested_by is null. execute-job-queue.php drains the eval job.
        if (!$skipEval) {
            $evalService = new Application_Service_Evaluation();
            foreach ($expiredIds as $sid) {
                try {
                    $evalService->scheduleEvaluation($sid, null);
                } catch (Exception $evalErr) {
                    Pt_Commons_LoggerUtility::logError('auto-eval enqueue failed for shipment ' . $sid . ': ' . $evalErr->getMessage());
                }
            }
        }
    }
} catch (Throwable $e) {
    Pt_Commons_LoggerUtility::logError($e->getMessage(), [
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString()
    ]);
}
