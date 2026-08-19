<?php

// One-off repair for the HBV RDT possible-result codes (Zimbabwe only).
//
// History: the HBV RDT scheme carries two families of qualitative result codes --
// the short HBV-P / HBV-N pair (scheme_sub_group 'TEST') and the long
// HBV RDT-HBVP / HBVN / HBVI trio (scheme_sub_group NULL). Neither family was
// labelled 'FINAL'.
//
// Application_Service_Schemes::getPossibleResults($scheme, $ctx, 'FINAL') asks for the
// FINAL group and, finding none, falls back to returning EVERY row for the scheme. So
// the expected-result picker and the participant result picker both offered "Positive"
// and "Negative" twice -- once per family -- with nothing to tell them apart.
//
// Every HBV reference result ever recorded uses the short family, and so do 165 of the
// 176 responses. The 11 participants who happened to pick a long-code option scored 0
// on every sample, because Application_Model_CustomTest::evaluate() compares the stored
// code to the reference code as a plain string. Three of them had answered all six
// samples correctly and were recorded as Fail.
//
// Repair, matching the shape of the sibling single-test serology schemes (HCV RDT,
// SYPH RDT), which each carry exactly one family of Positive/Negative/Invalid:
//   1. fold the long codes into the short ones (a pure rename -- the paired rows carry
//      the same `response` label, so no answer changes meaning)
//   2. restore the missing HBV-I, absent from r_possibleresult even though participants
//      already have it stored against earlier HBV shipments
//   3. label the surviving family 'FINAL' so getPossibleResults() stops falling back
//   4. retire the long codes via the 7.6.12 status column -- inactive, not deleted, so
//      any historical row still pointing at one keeps resolving
//
// Why run-once and not a migration: HBV RDT is a user-configured scheme
// (scheme_list.is_user_configured = 'yes'), so its codes are admin-created per instance
// and differ everywhere. This repair is meaningful only on the Zimbabwe instance. Other
// instances exit 0 immediately and bin/run-once.php records the script as done, so it
// never runs again anywhere.
//
// Scoring is NOT recalculated here -- that is an admin action. Affected shipments are
// listed at the end and must be re-evaluated (and their reports regenerated) for the
// corrected scores to reach participants.
//
// Exits non-zero on failure so bin/run-once.php does not record it and retries next upgrade.

ini_set('memory_limit', '-1');
require_once __DIR__ . '/../cli-bootstrap.php';

const SCHEME_ID = 'HBV RDT';
const TARGET_INSTANCE = 'zimbabwe';

// old code => canonical code. Each pair is verified to share a `response` label before use.
const CODE_MAP = [
    'HBV RDT-HBVP' => 'HBV-P',
    'HBV RDT-HBVN' => 'HBV-N',
    'HBV RDT-HBVI' => 'HBV-I',
];

// The canonical family, in sibling-scheme order. Invalid is offered to participants only.
const CANONICAL = [
    ['code' => 'HBV-P', 'response' => 'Positive', 'context' => 'all',         'sort' => 1],
    ['code' => 'HBV-N', 'response' => 'Negative', 'context' => 'all',         'sort' => 2],
    ['code' => 'HBV-I', 'response' => 'Invalid',  'context' => 'participant', 'sort' => 3],
];

const RESULT_COLUMNS = ['result_1', 'result_2', 'result_3', 'reported_result'];

function say(string $msg): void
{
    echo "[hbv-codes] {$msg}\n";
}

try {
    $db = Zend_Db_Table_Abstract::getDefaultAdapter();

    // --- Gate: Zimbabwe only -------------------------------------------------------
    $instance = (string) $db->fetchOne("SELECT value FROM global_config WHERE name = 'instance'");
    if (strtolower(trim($instance)) !== TARGET_INSTANCE) {
        say('Instance is ' . ($instance !== '' ? "'{$instance}'" : '(unset)') . ", not '" . TARGET_INSTANCE . "'. Nothing to do.");
        exit(0);
    }

    // --- Gate: is the drift actually present? ---------------------------------------
    $oldCodes = array_keys(CODE_MAP);
    $placeholders = implode(',', array_fill(0, count($oldCodes), '?'));

    $optionRows = $db->fetchAll(
        "SELECT id, response, result_code, scheme_sub_group, status FROM r_possibleresult WHERE scheme_id = ?",
        [SCHEME_ID]
    );
    if (empty($optionRows)) {
        say("Scheme '" . SCHEME_ID . "' has no possible results on this instance. Nothing to do.");
        exit(0);
    }

    $byCode = [];
    foreach ($optionRows as $row) {
        $byCode[$row['result_code']] = $row;
    }

    $responseHits = (int) $db->fetchOne(
        'SELECT COUNT(*) FROM response_result_generic_test WHERE '
            . implode(' OR ', array_map(fn($c) => "{$c} IN ({$placeholders})", RESULT_COLUMNS)),
        array_merge($oldCodes, $oldCodes, $oldCodes, $oldCodes)
    );
    $staleOptions = array_filter($oldCodes, fn($c) => isset($byCode[$c]) && $byCode[$c]['status'] === 'active');
    $missingCanonical = array_filter(CANONICAL, fn($c) => !isset($byCode[$c['code']]));
    $wrongGroup = array_filter(
        CANONICAL,
        fn($c) => isset($byCode[$c['code']]) && strtoupper(trim((string) $byCode[$c['code']]['scheme_sub_group'])) !== 'FINAL'
    );

    if ($responseHits === 0 && !$staleOptions && !$missingCanonical && !$wrongGroup) {
        say('HBV RDT result codes are already canonical. Nothing to do.');
        exit(0);
    }

    say("Found {$responseHits} response cell(s) on legacy codes, " . count($staleOptions) . ' stale option(s), '
        . count($missingCanonical) . ' missing option(s), ' . count($wrongGroup) . ' mis-grouped option(s).');

    // --- Safety: every mapping must be a same-meaning rename ------------------------
    // A long code may only fold into a short code that already exists and carries the
    // identical `response` label. Anything else is not a rename and is refused.
    foreach (CODE_MAP as $old => $new) {
        if (!isset($byCode[$old])) {
            continue; // already folded
        }
        if (!isset($byCode[$new])) {
            // The canonical row is created below; only HBV-I is legitimately absent.
            if (!in_array($new, array_column(CANONICAL, 'code'), true)) {
                throw new RuntimeException("Refusing to map '{$old}' -> '{$new}': target is not a canonical code.");
            }
            continue;
        }
        if (strcasecmp(trim($byCode[$old]['response']), trim($byCode[$new]['response'])) !== 0) {
            throw new RuntimeException(
                "Refusing to map '{$old}' ({$byCode[$old]['response']}) -> '{$new}' ({$byCode[$new]['response']}): "
                    . 'the two codes do not mean the same thing.'
            );
        }
    }
    say('Verified every mapping is a same-meaning rename.');

    // --- Which shipments will need re-evaluating? -----------------------------------
    $affected = $db->fetchAll(
        'SELECT DISTINCT s.shipment_id, s.shipment_code, s.status FROM response_result_generic_test g'
            . ' JOIN shipment_participant_map m ON m.map_id = g.shipment_map_id'
            . ' JOIN shipment s ON s.shipment_id = m.shipment_id'
            . ' WHERE ' . implode(' OR ', array_map(fn($c) => "g.{$c} IN ({$placeholders})", RESULT_COLUMNS))
            . ' ORDER BY s.shipment_code',
        array_merge($oldCodes, $oldCodes, $oldCodes, $oldCodes)
    );

    $db->beginTransaction();

    // 1. Fold the long codes into the short ones.
    $renamed = 0;
    foreach (CODE_MAP as $old => $new) {
        foreach (RESULT_COLUMNS as $col) {
            $renamed += (int) $db->update('response_result_generic_test', [$col => $new], [$db->quoteInto("{$col} = ?", $old)]);
        }
    }
    say("Rewrote {$renamed} response cell(s) onto canonical codes.");

    // 2/3. Ensure each canonical option exists, is active, sits in the FINAL group and
    //      is ordered like the sibling schemes.
    foreach (CANONICAL as $opt) {
        if (isset($byCode[$opt['code']])) {
            $db->update('r_possibleresult', [
                'scheme_sub_group' => 'FINAL',
                'display_context' => $opt['context'],
                'sort_order' => $opt['sort'],
                'status' => 'active',
            ], [$db->quoteInto('id = ?', $byCode[$opt['code']]['id'])]);
            say("Normalised option {$opt['code']} (FINAL, {$opt['context']}, sort {$opt['sort']}).");
        } else {
            $db->insert('r_possibleresult', [
                'scheme_id' => SCHEME_ID,
                'scheme_sub_group' => 'FINAL',
                'result_type' => 'qualitative',
                'response' => $opt['response'],
                'result_code' => $opt['code'],
                'display_context' => $opt['context'],
                'sort_order' => $opt['sort'],
                'status' => 'active',
            ]);
            say("Created missing option {$opt['code']} ({$opt['response']}).");
        }
    }

    // 4. Retire the long codes rather than deleting them.
    $retired = (int) $db->update(
        'r_possibleresult',
        ['status' => 'inactive'],
        [
            $db->quoteInto('scheme_id = ?', SCHEME_ID),
            $db->quoteInto("result_code IN ({$placeholders})", $oldCodes),
        ]
    );
    say("Retired {$retired} legacy option(s).");

    // --- Verify before committing ---------------------------------------------------
    $leftover = (int) $db->fetchOne(
        'SELECT COUNT(*) FROM response_result_generic_test WHERE '
            . implode(' OR ', array_map(fn($c) => "{$c} IN ({$placeholders})", RESULT_COLUMNS)),
        array_merge($oldCodes, $oldCodes, $oldCodes, $oldCodes)
    );
    if ($leftover !== 0) {
        throw new RuntimeException("Verification failed: {$leftover} response cell(s) still on legacy codes.");
    }

    $finalCount = (int) $db->fetchOne(
        "SELECT COUNT(*) FROM r_possibleresult WHERE scheme_id = ? AND status = 'active' AND UPPER(TRIM(scheme_sub_group)) = 'FINAL'",
        [SCHEME_ID]
    );
    if ($finalCount !== count(CANONICAL)) {
        throw new RuntimeException('Verification failed: expected ' . count(CANONICAL) . " active FINAL options, found {$finalCount}.");
    }

    $orphans = (int) $db->fetchOne(
        'SELECT COUNT(*) FROM response_result_generic_test g'
            . ' JOIN shipment_participant_map m ON m.map_id = g.shipment_map_id'
            . ' JOIN shipment s ON s.shipment_id = m.shipment_id AND s.scheme_type = ?'
            . ' LEFT JOIN r_possibleresult pr ON pr.scheme_id = s.scheme_type AND pr.result_code = g.reported_result'
            . " WHERE COALESCE(g.reported_result, '') <> '' AND pr.id IS NULL",
        [SCHEME_ID]
    );
    if ($orphans !== 0) {
        throw new RuntimeException("Verification failed: {$orphans} response(s) point at a code that does not exist.");
    }

    $db->commit();
    say('Committed.');

    if ($affected) {
        say('');
        say('ACTION REQUIRED -- re-evaluate and regenerate reports for:');
        foreach ($affected as $s) {
            say("  - {$s['shipment_code']} (shipment_id {$s['shipment_id']}, status: {$s['status']})");
        }
        say('Scores recorded against the legacy codes are wrong until those shipments are re-evaluated.');
    }

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
        Pt_Commons_LoggerUtility::logError('fix-hbv-rdt-result-codes failed', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
    exit(1);
}
