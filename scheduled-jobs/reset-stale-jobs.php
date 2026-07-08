<?php
// reset-stale-jobs.php
require_once __DIR__ . '/../cli-bootstrap.php';

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$db = Zend_Db::factory($conf->resources->db);
Zend_Db_Table::setDefaultAdapter($db);

$staleThresholdSeconds = 10; // Configurable

try {
    // Reset stale shipments
    $resetCount = $db->update(
        'shipment',
        [
            'status' => new Zend_Db_Expr("CASE WHEN previous_status IN ('queued','processing') THEN 'shipped' ELSE previous_status END"),
            'processing_started_at' => null,
            'previous_status' => null,
            'last_heartbeat' => null
        ],
        $db->quoteInto(
            "status = 'processing' 
                AND previous_status IS NOT NULL 
                AND last_heartbeat < DATE_SUB(NOW(), INTERVAL ? SECOND)",
            $staleThresholdSeconds
        )
    );

    if ($resetCount > 0) {
        Pt_Commons_LoggerUtility::logInfo("Reset {$resetCount} stale shipment jobs");
    }

    // Recover crashed report jobs (worker killed / fatal mid-run, so the in-process
    // failure handler never ran). Report generation can legitimately run for a long
    // time, so use a generous silence window: only a job with no heartbeat for well
    // beyond any real chunk is treated as dead. Unlike the old behaviour, which
    // silently reverted to 'evaluated', we mark these 'failed' with a reason so the
    // distribution page can tell the admin something went wrong.
    $reportStaleSeconds = 300; // 5 min of no heartbeat = dead worker

    $staleReportJobs = $db->fetchAll(
        $db->select()
            ->from('queue_report_generation', ['id', 'shipment_id', 'report_type'])
            ->where("status IN ('generating', 'finalizing')")
            ->where("previous_status IS NOT NULL")
            ->where("last_heartbeat < DATE_SUB(NOW(), INTERVAL ? SECOND)", $reportStaleSeconds)
    );

    if (!empty($staleReportJobs)) {
        $jobIds = array_map('intval', array_column($staleReportJobs, 'id'));

        $db->update(
            'queue_report_generation',
            [
                'status' => 'failed',
                'error_message' => 'Report generation stopped unexpectedly (the worker crashed or was killed) — no progress was recorded for over 5 minutes. Please try generating the reports again.',
                'completed_at' => new Zend_Db_Expr('now()'),
                'previous_status' => null,
                'last_heartbeat' => null
            ],
            'id IN (' . implode(',', $jobIds) . ')'
        );

        // Un-stick the shipments so the admin can retry from the UI. A finalize crash
        // leaves the previously-generated reports intact ('reports generated'); a plain
        // generation crash rests at 'evaluated'. Both are non-ephemeral, so buttons re-enable.
        foreach ($staleReportJobs as $job) {
            $restingStatus = (($job['report_type'] ?? '') === 'finalized') ? 'reports generated' : 'evaluated';
            $db->update(
                'shipment',
                [
                    'status' => $restingStatus,
                    'report_in_queue' => 'no',
                    'processing_started_at' => null,
                    'previous_status' => null,
                    'last_heartbeat' => null
                ],
                $db->quoteInto('shipment_id = ?', (int) $job['shipment_id'])
            );
        }

        Pt_Commons_LoggerUtility::logInfo('Marked ' . count($jobIds) . ' stale report jobs as failed');
    }
} catch (Throwable $e) {
    Pt_Commons_LoggerUtility::logError($e->getMessage(), [
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString()
    ]);
}
