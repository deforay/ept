<?php

// Second fold of the retired HBV RDT result codes onto the canonical family (Zimbabwe only).
//
// History: fix-hbv-rdt-result-codes.php (Aug 2026) folded HBV RDT-HBVP/HBVN/HBVI into
// HBV-P/N/I and retired the long codes. The custom-test response form kept offering the
// retired codes in the Test Result dropdown until Sep 22, so responses entered in between
// picked them up again -- mostly in result_1, occasionally in reported_result. A long code in
// reported_result is scored as wrong (evaluation compares codes as plain strings), and a long
// code anywhere breaks the response form for that participant.
//
// This repeats the fold for the response data only. The option rows are already canonical
// (HBV-P/N/I active FINAL, long codes inactive) and are verified, not changed.
//
// Runs after fix-hbv-rdt-forced-test-results.php, which needs the long codes to find the
// forced Test Results. bin/run-once.php runs scripts in natural sort order.
//
// Scoring is NOT recalculated here. Affected shipments are listed at the end and must be
// re-evaluated (and their reports regenerated) for corrected scores to reach participants.
//
// Exits non-zero on failure so bin/run-once.php does not record it and retries next upgrade.

ini_set('memory_limit', '-1');
require_once __DIR__ . '/../cli-bootstrap.php';

const SCHEME_ID = 'HBV RDT';
const TARGET_INSTANCE = 'zimbabwe';

const CODE_MAP = [
    'HBV RDT-HBVP' => 'HBV-P',
    'HBV RDT-HBVN' => 'HBV-N',
    'HBV RDT-HBVI' => 'HBV-I',
];

const RESULT_COLUMNS = ['result_1', 'result_2', 'result_3', 'reported_result'];

function say(string $msg): void
{
    echo "[hbv-refold] {$msg}\n";
}

try {
    $db = Zend_Db_Table_Abstract::getDefaultAdapter();

    // --- Gate: Zimbabwe only -------------------------------------------------------
    $instance = (string) $db->fetchOne("SELECT value FROM global_config WHERE name = 'instance'");
    if (strtolower(trim($instance)) !== TARGET_INSTANCE) {
        say('Instance is ' . ($instance !== '' ? "'{$instance}'" : '(unset)') . ", not '" . TARGET_INSTANCE . "'. Nothing to do.");
        exit(0);
    }

    // --- Safety: the canonical options must be in place ----------------------------
    $options = $db->fetchAssoc(
        'SELECT result_code, response, scheme_sub_group, status FROM r_possibleresult WHERE scheme_id = ?',
        [SCHEME_ID]
    );
    foreach (CODE_MAP as $old => $new) {
        if (!isset($options[$new])
            || $options[$new]['status'] !== 'active'
            || strtoupper(trim((string) $options[$new]['scheme_sub_group'])) !== 'FINAL') {
            throw new RuntimeException("Canonical option '{$new}' is missing, inactive or not FINAL. Run fix-hbv-rdt-result-codes.php first.");
        }
        if (isset($options[$old]) && strcasecmp(trim($options[$old]['response']), trim($options[$new]['response'])) !== 0) {
            throw new RuntimeException(
                "Refusing to map '{$old}' ({$options[$old]['response']}) -> '{$new}' ({$options[$new]['response']}): "
                    . 'the two codes do not mean the same thing.'
            );
        }
    }
    say('Verified the canonical options and that every mapping is a same-meaning rename.');

    $oldCodes = array_keys(CODE_MAP);
    $placeholders = implode(',', array_fill(0, count($oldCodes), '?'));
    $hitSql = ' FROM response_result_generic_test g'
        . ' JOIN shipment_participant_map m ON m.map_id = g.shipment_map_id'
        . ' JOIN shipment s ON s.shipment_id = m.shipment_id AND s.scheme_type = ?'
        . ' WHERE ' . implode(' OR ', array_map(fn($c) => "g.{$c} IN ({$placeholders})", RESULT_COLUMNS));
    $hitParams = array_merge([SCHEME_ID], $oldCodes, $oldCodes, $oldCodes, $oldCodes);

    $affected = $db->fetchAll(
        'SELECT DISTINCT s.shipment_id, s.shipment_code, s.status' . $hitSql . ' ORDER BY s.shipment_code',
        $hitParams
    );
    if (empty($affected)) {
        say('No HBV RDT responses on retired codes. Nothing to do.');
        exit(0);
    }

    $db->beginTransaction();

    $renamed = 0;
    foreach (CODE_MAP as $old => $new) {
        foreach (RESULT_COLUMNS as $col) {
            $renamed += $db->query(
                "UPDATE response_result_generic_test g"
                    . ' JOIN shipment_participant_map m ON m.map_id = g.shipment_map_id'
                    . ' JOIN shipment s ON s.shipment_id = m.shipment_id AND s.scheme_type = ?'
                    . " SET g.{$col} = ? WHERE g.{$col} = ?",
                [SCHEME_ID, $new, $old]
            )->rowCount();
        }
    }
    say("Rewrote {$renamed} response cell(s) onto canonical codes.");

    // --- Verify before committing ---------------------------------------------------
    $leftover = (int) $db->fetchOne('SELECT COUNT(*)' . $hitSql, $hitParams);
    if ($leftover !== 0) {
        throw new RuntimeException("Verification failed: {$leftover} response row(s) still on retired codes.");
    }

    $db->commit();
    say('Committed.');

    say('');
    say('ACTION REQUIRED -- re-evaluate and regenerate reports for:');
    foreach ($affected as $s) {
        say("  - {$s['shipment_code']} (shipment_id {$s['shipment_id']}, status: {$s['status']})");
    }
    say('Scores recorded against the retired codes are wrong until those shipments are re-evaluated.');

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
        Pt_Commons_LoggerUtility::logError('refold-hbv-rdt-result-codes failed', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
    exit(1);
}
