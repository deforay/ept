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
        error_log("Reset {$resetCount} stale shipment jobs");
    }

    // Reset stale reports
    $reportResetCount = $db->update(
        'queue_report_generation',
        [
            'status' => new Zend_Db_Expr("CASE WHEN previous_status IN ('queued','processing') THEN 'shipped' ELSE previous_status END"),
            'processing_started_at' => null,
            'previous_status' => null,
            'last_heartbeat' => null
        ],
        $db->quoteInto(
            "status IN ('not-evaluated', 'not-finalized') 
                AND previous_status IS NOT NULL
                AND last_heartbeat < DATE_SUB(NOW(), INTERVAL ? SECOND)",
            $staleThresholdSeconds
        )
    );
    if ($reportResetCount > 0) {
        error_log("Reset {$reportResetCount} stale report jobs");
    }
} catch (Exception $e) {
    Pt_Commons_LoggerUtility::logError($e->getMessage(), [
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString()
    ]);
}
