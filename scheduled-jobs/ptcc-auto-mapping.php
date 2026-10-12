<?php

// Maps every PTCC to the labs in its countries, provinces or districts, and unmaps labs
// that have left them. Adding, editing and importing labs already map them as they are
// saved; this daily run catches anything those paths missed. Safe to re-run.

ini_set('memory_limit', '-1');
require_once __DIR__ . '/../cli-bootstrap.php';

try {
    $result = (new Application_Model_DbTable_DataManagers())->syncPtccParticipants();
    echo "PTCC lab mapping: {$result['added']} added, {$result['removed']} removed" . PHP_EOL;
} catch (Throwable $e) {
    Pt_Commons_LoggerUtility::logError($e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    fwrite(STDERR, 'ptcc-auto-mapping failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
