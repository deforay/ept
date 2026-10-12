<?php

// PTCCs were mapped to the labs in their areas only when the PTCC was saved, so labs
// added or moved since then were missing from their emails and their view of ePT. This
// brings every instance up to date once on upgrade; adding, editing and importing labs,
// and the daily ptcc-auto-mapping job, keep it that way. Safe to re-run.

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
    fwrite(STDERR, 'sync-ptcc-participant-mapping failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
