<?php

// One-off repair for the orphaned HBV / SYP scheme types (Zimbabwe only).
//
// History: the Hepatitis B and Syphilis DTS schemes were renamed in place -- their
// scheme_list rows became 'HBV RDT' and 'SYPH RDT' -- but the six shipments already
// recorded under the old 'HBV' and 'SYP' scheme_type strings were left behind. Those
// two scheme ids now exist nowhere in scheme_list or r_possibleresult, so the shipments
// are orphans:
//
//   * Invisible. Application_Service_Reports::getShipments and
//     Application_Service_Evaluation::getShipmentToEvaluate both INNER JOIN scheme_list,
//     so all six drop out of the reports listing AND cannot be evaluated at all.
//   * Inert. All six sit at status 'processing', which SHIPMENT_EPHEMERAL_STATUSES makes
//     disable every button in getShipmentButtonStates. previous_status and
//     processing_started_at are NULL, so setShipmentProcessingState never set it -- it is
//     a legacy value frozen in place precisely because nothing can reach them.
//   * Enrollments blank. 109 enrollment rows carry scheme_id 'HBV'; the scheme_name join
//     yields NULL, so those participants show an empty scheme column, and
//     countEnrollmentSchemes() reports zero enrolled for Hepatitis B.
//   * Labels unresolvable. No r_possibleresult rows exist for the HBV-* / SYP-* codes the
//     shipments' 14,208 response cells are recorded in.
//
// Repair: adopt the legacy scheme ids rather than merging them into the live schemes.
//
// Merging was considered and rejected. Application_Service_Evaluation::getPassingScore
// reads scheme_list.user_test_config.passingScore for user-configured schemes, so
// re-pointing scheme_type would move these historical shipments onto the live 83% pass
// mark. The stored results show they were scored at 100% (a participant on 83.33 failed
// in HBV-DTS-2024-B, HBVDTS-2025-A and SYPH-DTS-2024-B), and two of the six are finalized
// with reports already issued. Merging would silently invalidate results participants
// have already received.
//
// So each legacy scheme gets its own inactive scheme_list row carrying the pass mark that
// was actually in force. 'inactive' keeps it out of the new-shipment picker
// (getAllSchemes() filters status='active') while still satisfying every INNER JOIN.
// scheme_name carries a UNIQUE key and the live schemes hold the plain names, so the
// legacy rows are suffixed "(Legacy)" -- which also makes them read as legacy in listings.
//
// SYP is set to 83 on instruction: SYPDTS-2025-A (5000 participants) was scored at 83,
// SYPH-DTS-2024-B at 100, and one row cannot be both. The 2024 shipment is finalized, so
// its Re-Evaluate button stays disabled and the difference cannot be triggered by accident.
//
// Nothing here recomputes a score. Stored results stay exactly as issued.
//
// Why run-once and not a migration: these scheme ids are instance-specific data, and the
// rename that orphaned them happened only here. Other instances exit 0 immediately and
// bin/run-once.php records the script as done, so it never runs again anywhere.
//
// Exits non-zero on failure so bin/run-once.php does not record it and retries next upgrade.

ini_set('memory_limit', '-1');
require_once __DIR__ . '/../cli-bootstrap.php';

const TARGET_INSTANCE = 'zimbabwe';

// Legacy scheme id => how to reconstitute it. `passingScore` is the pass mark that was in
// force when its shipments were evaluated, NOT the live scheme's.
const LEGACY_SCHEMES = [
    'HBV' => [
        'name' => 'Dried Tube Specimen - Hepatitis B Serology (Legacy)',
        'passingScore' => '100',
        'codes' => [
            ['code' => 'HBV-P', 'response' => 'Positive', 'context' => 'all',         'sort' => 1],
            ['code' => 'HBV-N', 'response' => 'Negative', 'context' => 'all',         'sort' => 2],
            ['code' => 'HBV-I', 'response' => 'Invalid',  'context' => 'participant', 'sort' => 3],
        ],
    ],
    'SYP' => [
        'name' => 'Dried Tube Specimen - Syphilis Serology (Legacy)',
        'passingScore' => '83',
        'codes' => [
            ['code' => 'SYP-P', 'response' => 'Positive', 'context' => 'all',         'sort' => 1],
            ['code' => 'SYP-N', 'response' => 'Negative', 'context' => 'all',         'sort' => 2],
            ['code' => 'SYP-I', 'response' => 'Invalid',  'context' => 'participant', 'sort' => 3],
        ],
    ],
];

function say(string $msg): void
{
    echo "[legacy-schemes] {$msg}\n";
}

// Mirrors the live user-configured schemes' config shape so nothing downstream trips over
// a missing key. Single-test: every orphan shipment's result_2/result_3 cells are empty.
function legacyTestConfig(string $passingScore): string
{
    return json_encode([
        'testType' => 'qualitative',
        'passingScore' => $passingScore,
        'effectiveDate' => '',
        'numberOfTests' => '1',
        'reportVersion' => '',
        'disableOtherTestkit' => 'no',
        'minNumberOfResponses' => '',
        'additionalDetailLabel' => '',
        'captureAdditionalDetails' => 'no',
    ]);
}

try {
    $db = Zend_Db_Table_Abstract::getDefaultAdapter();

    // --- Gate: Zimbabwe only -------------------------------------------------------
    $instance = (string) $db->fetchOne("SELECT value FROM global_config WHERE name = 'instance'");
    if (strtolower(trim($instance)) !== TARGET_INSTANCE) {
        say('Instance is ' . ($instance !== '' ? "'{$instance}'" : '(unset)') . ", not '" . TARGET_INSTANCE . "'. Nothing to do.");
        exit(0);
    }

    // --- Gate: only adopt a scheme id that shipments actually reference and that is
    //     genuinely absent from scheme_list. Never invent a scheme. --------------------
    $adopt = [];
    foreach (LEGACY_SCHEMES as $schemeId => $spec) {
        $shipments = (int) $db->fetchOne('SELECT COUNT(*) FROM shipment WHERE scheme_type = ?', [$schemeId]);
        if ($shipments === 0) {
            continue;
        }
        $inList = (int) $db->fetchOne('SELECT COUNT(*) FROM scheme_list WHERE scheme_id = ?', [$schemeId]);
        $adopt[$schemeId] = ['spec' => $spec, 'shipments' => $shipments, 'inList' => $inList > 0];
    }

    if (!$adopt) {
        say('No shipments reference the legacy scheme ids. Nothing to do.');
        exit(0);
    }

    // Frozen shipments only: status 'processing' with no processing_started_at is the
    // stuck legacy value. A genuinely in-flight evaluation stamps that column, and must
    // never be stomped by an upgrade running alongside it.
    $frozen = $db->fetchAll(
        "SELECT shipment_id, shipment_code, scheme_type, evaluated_at, reports_generated_at, finalized_at"
            . " FROM shipment WHERE scheme_type IN (" . implode(',', array_map([$db, 'quote'], array_keys($adopt))) . ")"
            . " AND status = 'processing' AND processing_started_at IS NULL"
    );

    $needsList = array_filter($adopt, fn($a) => !$a['inList']);
    if (!$needsList && !$frozen) {
        say('Legacy schemes are already adopted and no shipment is frozen. Nothing to do.');
        exit(0);
    }

    say('Adopting: ' . implode(', ', array_keys($adopt)) . '; ' . count($frozen) . ' frozen shipment(s) to release.');

    $db->beginTransaction();

    foreach ($adopt as $schemeId => $info) {
        $spec = $info['spec'];

        // 1. scheme_list row -- inactive, so it satisfies the INNER JOINs without offering
        //    itself for new shipments.
        if (!$info['inList']) {
            $db->insert('scheme_list', [
                'scheme_id' => $schemeId,
                'scheme_name' => $spec['name'],
                'is_user_configured' => 'yes',
                'test_format' => 'qualitative',
                'user_test_config' => legacyTestConfig($spec['passingScore']),
                'status' => 'inactive',
            ]);
            say("Created scheme_list row {$schemeId} ('{$spec['name']}', passingScore {$spec['passingScore']}, inactive).");
        } else {
            say("scheme_list row {$schemeId} already present -- left as is.");
        }

        // 2. Possible results, in the same shape as the live sibling schemes. Labelled
        //    FINAL so getPossibleResults() resolves them directly instead of falling back
        //    to "return every code for the scheme".
        foreach ($spec['codes'] as $opt) {
            $exists = (int) $db->fetchOne(
                'SELECT COUNT(*) FROM r_possibleresult WHERE scheme_id = ? AND result_code = ?',
                [$schemeId, $opt['code']]
            );
            if ($exists > 0) {
                continue;
            }
            $db->insert('r_possibleresult', [
                'scheme_id' => $schemeId,
                'scheme_sub_group' => 'FINAL',
                'result_type' => 'qualitative',
                'response' => $opt['response'],
                'result_code' => $opt['code'],
                'display_context' => $opt['context'],
                'sort_order' => $opt['sort'],
                'status' => 'active',
            ]);
            say("Created option {$opt['code']} ({$opt['response']}) for {$schemeId}.");
        }
    }

    // 3. Release the frozen shipments. Status is set from the milestone timestamps using
    //    the same precedence getShipmentButtonStates already applies for its display
    //    status, so the column simply catches up with what the UI would show anyway.
    foreach ($frozen as $s) {
        if (!empty($s['finalized_at'])) {
            $status = 'finalized';
        } elseif (!empty($s['reports_generated_at'])) {
            $status = 'reports generated';
        } elseif (!empty($s['evaluated_at'])) {
            $status = 'evaluated';
        } else {
            say("Leaving {$s['shipment_code']} at 'processing': no milestone timestamp to derive a status from.");
            continue;
        }
        $db->update('shipment', ['status' => $status], $db->quoteInto('shipment_id = ?', $s['shipment_id']));
        say("Released {$s['shipment_code']} -> '{$status}'.");
    }

    // --- Verify before committing ---------------------------------------------------
    foreach (array_keys($adopt) as $schemeId) {
        $missing = (int) $db->fetchOne('SELECT COUNT(*) FROM scheme_list WHERE scheme_id = ?', [$schemeId]);
        if ($missing === 0) {
            throw new RuntimeException("Verification failed: scheme_list row for '{$schemeId}' is still absent.");
        }
    }

    // Every response code these shipments use must now resolve within its own scheme.
    $orphans = (int) $db->fetchOne(
        'SELECT COUNT(*) FROM response_result_generic_test g'
            . ' JOIN shipment_participant_map m ON m.map_id = g.shipment_map_id'
            . ' JOIN shipment s ON s.shipment_id = m.shipment_id'
            . ' LEFT JOIN r_possibleresult pr ON pr.scheme_id = s.scheme_type AND pr.result_code = g.reported_result'
            . ' WHERE s.scheme_type IN (' . implode(',', array_map([$db, 'quote'], array_keys($adopt))) . ')'
            . " AND COALESCE(g.reported_result, '') <> '' AND pr.id IS NULL"
    );
    if ($orphans !== 0) {
        throw new RuntimeException("Verification failed: {$orphans} response(s) still point at an unresolvable code.");
    }

    $stillFrozen = (int) $db->fetchOne(
        "SELECT COUNT(*) FROM shipment WHERE scheme_type IN (" . implode(',', array_map([$db, 'quote'], array_keys($adopt))) . ")"
            . " AND status = 'processing' AND processing_started_at IS NULL AND evaluated_at IS NOT NULL"
    );
    if ($stillFrozen !== 0) {
        throw new RuntimeException("Verification failed: {$stillFrozen} shipment(s) still frozen at 'processing'.");
    }

    $db->commit();
    say('Committed.');

    say('');
    say('These shipments are now visible and actionable in the admin listings for the');
    say('first time. Their stored scores are untouched and correct as issued -- do NOT');
    say('re-evaluate them unless you intend to re-score under the legacy pass mark');
    say('(HBV 100%, SYP 83%).');

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
        Pt_Commons_LoggerUtility::logError('adopt-legacy-hbv-syp-schemes failed', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
    exit(1);
}
