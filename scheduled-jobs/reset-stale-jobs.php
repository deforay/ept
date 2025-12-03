<?php
// reset-stale-jobs.php
require_once __DIR__ . '/../cli-bootstrap.php';

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$db = Zend_Db::factory($conf->resources->db);
Zend_Db_Table::setDefaultAdapter($db);

$staleThresholdMinutes = 1; // Configurable

try {
    // Reset stale shipments
    $resetCount = $db->update(
        'shipment',
        [
            'status' => new Zend_Db_Expr('previous_status'),
            'processing_started_at' => null,
            'previous_status' => null,
            'last_heartbeat' => null
        ],
        "status = 'processing' 
         AND previous_status IS NOT NULL 
         AND last_heartbeat < DATE_SUB(NOW(), INTERVAL {$staleThresholdMinutes} MINUTE)"
    );

    error_log("Reset {$resetCount} stale shipment jobs");

    // Reset stale reports
    $reportResetCount = $db->update(
        'queue_report_generation',
        [
            'status' => new Zend_Db_Expr('previous_status'),
            'processing_started_at' => null,
            'previous_status' => null,
            'last_heartbeat' => null
        ],
        "status IN ('not-evaluated', 'not-finalized') 
         AND previous_status IS NOT NULL
         AND last_heartbeat < DATE_SUB(NOW(), INTERVAL {$staleThresholdMinutes} MINUTE)"
    );

    error_log("Reset {$reportResetCount} stale report jobs");
} catch (Exception $e) {
    error_log("Stale job reset failed: {$e->getMessage()}");
}
