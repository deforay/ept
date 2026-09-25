<?php

// One-off repair for HBV RDT Test Results that participants were forced into (Zimbabwe only).
//
// History: fix-hbv-rdt-result-codes.php (Aug 2026) retired the long HBV RDT-HBVP/HBVN/HBVI
// codes, but the custom-test response form kept offering them in the Test Result dropdown
// until Sep 22 (the Test dropdown falls back to the full option list for HBV RDT, and that
// fallback still included retired rows). Once a participant saved a long code, the form
// re-rendered with the Test dropdown holding only their stored long codes. A participant who
// had picked Positive on a sample found it gone, and because Test Result is required they
// could only get past validation by picking the one option left: Negative. Their Final
// Result stayed correct.
//
// Signature: Test Result on a long code that contradicts a canonical Final Result
// (HBV RDT-HBVN with HBV-P, or HBV RDT-HBVP with HBV-N). HBV RDT is a single-test scheme,
// so Test and Final are the same reading; the Test Result is corrected to match the Final.
// Rows with the contradiction on canonical codes are genuine entries and are left alone.
//
// This must run BEFORE refold-hbv-rdt-result-codes.php: the signature relies on the long
// codes, which that script folds away. bin/run-once.php runs scripts in natural sort order,
// so the filename keeps it first. To skip this correction, delete this file before deploying.
//
// Exits non-zero on failure so bin/run-once.php does not record it and retries next upgrade.

ini_set('memory_limit', '-1');
require_once __DIR__ . '/../cli-bootstrap.php';

const SCHEME_ID = 'HBV RDT';
const TARGET_INSTANCE = 'zimbabwe';

// stored Test Result => contradicting Final Result it is corrected to
const FORCED_PAIRS = [
    'HBV RDT-HBVN' => 'HBV-P',
    'HBV RDT-HBVP' => 'HBV-N',
];

function say(string $msg): void
{
    echo "[hbv-forced] {$msg}\n";
}

try {
    $db = Zend_Db_Table_Abstract::getDefaultAdapter();

    // --- Gate: Zimbabwe only -------------------------------------------------------
    $instance = (string) $db->fetchOne("SELECT value FROM global_config WHERE name = 'instance'");
    if (strtolower(trim($instance)) !== TARGET_INSTANCE) {
        say('Instance is ' . ($instance !== '' ? "'{$instance}'" : '(unset)') . ", not '" . TARGET_INSTANCE . "'. Nothing to do.");
        exit(0);
    }

    $scopeSql = ' FROM response_result_generic_test g'
        . ' JOIN shipment_participant_map m ON m.map_id = g.shipment_map_id'
        . ' JOIN shipment s ON s.shipment_id = m.shipment_id AND s.scheme_type = ?';
    $matchSql = ' WHERE g.result_1 = ? AND g.reported_result = ?';

    $db->beginTransaction();

    $corrected = 0;
    foreach (FORCED_PAIRS as $testCode => $finalCode) {
        $rows = $db->fetchAll(
            'SELECT s.shipment_code, p.unique_identifier, g.shipment_map_id, g.sample_id'
                . $scopeSql
                . ' JOIN participant p ON p.participant_id = m.participant_id'
                . $matchSql
                . ' ORDER BY s.shipment_code, p.unique_identifier, g.sample_id',
            [SCHEME_ID, $testCode, $finalCode]
        );
        foreach ($rows as $row) {
            $db->update(
                'response_result_generic_test',
                ['result_1' => $finalCode],
                [
                    'shipment_map_id = ?' => $row['shipment_map_id'],
                    'sample_id = ?' => $row['sample_id'],
                    'result_1 = ?' => $testCode,
                ]
            );
            say("{$row['shipment_code']} / {$row['unique_identifier']} / sample {$row['sample_id']}: Test Result {$testCode} -> {$finalCode}");
            $corrected++;
        }
    }

    // --- Verify before committing ---------------------------------------------------
    foreach (FORCED_PAIRS as $testCode => $finalCode) {
        $left = (int) $db->fetchOne('SELECT COUNT(*)' . $scopeSql . $matchSql, [SCHEME_ID, $testCode, $finalCode]);
        if ($left !== 0) {
            throw new RuntimeException("Verification failed: {$left} row(s) still on {$testCode} with Final {$finalCode}.");
        }
    }

    $db->commit();
    say($corrected === 0 ? 'No forced Test Results found. Nothing to do.' : "Committed. Corrected {$corrected} Test Result(s).");
    exit(0);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof Zend_Db_Adapter_Abstract) {
        try {
            $db->rollBack();
            say('Rolled back.');
        } catch (Throwable $rollbackError) {
            say('Rollback failed: ' . $rollbackError->getMessage());
        }
    }
    say('FAILED: ' . $e->getMessage());
    if (class_exists('Pt_Commons_LoggerUtility')) {
        Pt_Commons_LoggerUtility::logError('fix-hbv-rdt-forced-test-results failed', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
    exit(1);
}
