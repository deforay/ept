#!/usr/bin/env php
<?php

// bin/dev/backfill-generic-final-codes.php — repair drifted Final-Interpretation codes on
// historical custom (user-configured) shipments (dev-only, run-once).
//
// Background: user-configured schemes store the result_code (not the id) in their result
// tables. When a scheme's codes were later restructured to split Test vs Final groups (e.g.
// SYP-P "Positive" -> SYP-F-P), the historical rows kept the OLD code, which no longer exists
// in r_possibleresult -- so their labels render blank on reports. Scoring is unaffected
// (reference and response drifted together), this only repairs the display.
//
// Scope: ONLY the two Final-Interpretation columns —
//   * reference_result_generic_test.reference_result
//   * response_result_generic_test.reported_result
// result_1/2/3 (Test Result columns) are deliberately NOT touched: legacy shipments recorded a
// single final result and duplicated it into result_1, so those cells are final codes sitting
// in a test column. Mapping them would cement a Final code under a Test heading, and there is
// no correct Test code to infer, so they are left as-is (they simply render blank).
//
// Mapping is auto-discovered per user-configured scheme and applied ONLY when safe: for each
// orphaned code "{SCHEME}-{SUFFIX}" the target "{SCHEME}-F-{SUFFIX}" must already exist in
// r_possibleresult for that scheme. Anything that doesn't resolve is reported and left alone.
// Idempotent: once applied, the old codes are gone, so re-running is a no-op.
//
// Modes:
//   (default)   dry-run: print exactly what would change, write nothing
//   --apply     write the changes (inside a transaction)

declare(strict_types=1);

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

if (php_sapi_name() !== 'cli') {
    echo 'This script can only be run from the command line.' . PHP_EOL;
    exit(1);
}

require_once __DIR__ . '/../../cli-bootstrap.php';
ini_set('memory_limit', '-1');
set_time_limit(0);

$io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$db = Zend_Db::factory($conf->resources->db);
Zend_Db_Table::setDefaultAdapter($db);

$apply = in_array('--apply', $_SERVER['argv'], true);

$io->title('Backfill generic Final-Interpretation codes' . ($apply ? ' (APPLY)' : ' (dry-run)'));

// The two Final-Interpretation columns and how to scope each to a scheme. Each entry provides
// a COUNT builder and an UPDATE builder given (scheme, old, new).
$targets = [
    'reference_result_generic_test.reference_result' => [
        'count' => 'SELECT COUNT(*) FROM reference_result_generic_test g'
            . ' JOIN shipment s ON s.shipment_id = g.shipment_id'
            . ' WHERE s.scheme_type = ? AND g.reference_result = ?',
        'update' => 'UPDATE reference_result_generic_test g'
            . ' JOIN shipment s ON s.shipment_id = g.shipment_id'
            . ' SET g.reference_result = ?'
            . ' WHERE s.scheme_type = ? AND g.reference_result = ?',
    ],
    'response_result_generic_test.reported_result' => [
        'count' => 'SELECT COUNT(*) FROM response_result_generic_test r'
            . ' JOIN shipment_participant_map spm ON spm.map_id = r.shipment_map_id'
            . ' JOIN shipment s ON s.shipment_id = spm.shipment_id'
            . ' WHERE s.scheme_type = ? AND r.reported_result = ?',
        'update' => 'UPDATE response_result_generic_test r'
            . ' JOIN shipment_participant_map spm ON spm.map_id = r.shipment_map_id'
            . ' JOIN shipment s ON s.shipment_id = spm.shipment_id'
            . ' SET r.reported_result = ?'
            . ' WHERE s.scheme_type = ? AND r.reported_result = ?',
    ],
];

// User-configured schemes only.
$schemes = $db->fetchCol(
    $db->select()->from('scheme_list', ['scheme_id'])->where('is_user_configured = ?', 'yes')
);

if (empty($schemes)) {
    $io->warning('No user-configured schemes found. Nothing to do.');
    exit(0);
}

// Valid current codes per scheme (target-existence check).
$validCodesByScheme = [];
foreach ($schemes as $scheme) {
    $validCodesByScheme[$scheme] = array_flip($db->fetchCol(
        $db->select()->from('r_possibleresult', ['result_code'])->where('scheme_id = ?', $scheme)
    ));
}

$plan = [];      // [ [scheme, column, old, new, rows] ]
$skipped = [];   // [ [scheme, column, old, reason, rows] ]

foreach ($schemes as $scheme) {
    $prefix = $scheme . '-';
    foreach ($targets as $columnLabel => $sql) {
        // Distinct orphaned codes actually present in this column for this scheme.
        $orphans = discoverOrphans($db, $scheme, $columnLabel);
        foreach ($orphans as $old => $rows) {
            // Only remap "{SCHEME}-{SUFFIX}" -> "{SCHEME}-F-{SUFFIX}" when the target exists.
            if (strpos($old, $prefix) !== 0) {
                $skipped[] = [$scheme, $columnLabel, $old, 'unrecognized prefix', $rows];
                continue;
            }
            $suffix = substr($old, strlen($prefix));
            // Already namespaced (e.g. -F- / -T-)? Leave alone; not a legacy flat code.
            if (strpos($suffix, 'F-') === 0 || strpos($suffix, 'T-') === 0) {
                $skipped[] = [$scheme, $columnLabel, $old, 'already namespaced', $rows];
                continue;
            }
            $new = $prefix . 'F-' . $suffix;
            if (!isset($validCodesByScheme[$scheme][$new])) {
                $skipped[] = [$scheme, $columnLabel, $old, "target {$new} not in r_possibleresult", $rows];
                continue;
            }
            $plan[] = [$scheme, $columnLabel, $old, $new, $rows];
        }
    }
}

if (!empty($skipped)) {
    $io->section('Skipped (left as-is — resolve manually if needed)');
    $io->table(['Scheme', 'Column', 'Orphan code', 'Reason', 'Rows'], $skipped);
}

if (empty($plan)) {
    $io->success('No remappable orphaned Final-Interpretation codes found. Nothing to do.');
    exit(0);
}

$io->section('Planned remaps');
$io->table(
    ['Scheme', 'Column', 'From', 'To', 'Rows'],
    array_map(fn ($r) => [$r[0], $r[1], $r[2], $r[3], $r[4]], $plan)
);
$io->text('Total rows to update: ' . array_sum(array_column($plan, 4)));

if (!$apply) {
    $io->note('Dry-run only. Re-run with --apply to write these changes.');
    exit(0);
}

$db->beginTransaction();
try {
    $updated = 0;
    foreach ($plan as [$scheme, $columnLabel, $old, $new, $rows]) {
        $stmt = $db->query($targets[$columnLabel]['update'], [$new, $scheme, $old]);
        $affected = $stmt->rowCount();
        $updated += $affected;
        $io->text(sprintf('  %s / %s: %s -> %s  (%d rows)', $scheme, $columnLabel, $old, $new, $affected));
    }
    $db->commit();
    $io->success("Applied. {$updated} rows updated.");
} catch (Throwable $e) {
    $db->rollBack();
    $io->error('Rolled back: ' . $e->getMessage());
    exit(1);
}

/**
 * Distinct codes present in the given "table.column" for a scheme that no longer exist in
 * r_possibleresult, with their row counts. Scoped to the scheme via its shipments.
 *
 * @return array<string,int> [ orphan_code => row_count ]
 */
function discoverOrphans(Zend_Db_Adapter_Abstract $db, string $scheme, string $columnLabel): array
{
    if ($columnLabel === 'reference_result_generic_test.reference_result') {
        $sql = 'SELECT g.reference_result AS code, COUNT(*) AS n'
            . ' FROM reference_result_generic_test g'
            . ' JOIN shipment s ON s.shipment_id = g.shipment_id'
            . ' WHERE s.scheme_type = ? AND g.reference_result <> \'\''
            . '   AND NOT EXISTS (SELECT 1 FROM r_possibleresult p WHERE p.result_code = g.reference_result)'
            . ' GROUP BY g.reference_result';
    } else {
        $sql = 'SELECT r.reported_result AS code, COUNT(*) AS n'
            . ' FROM response_result_generic_test r'
            . ' JOIN shipment_participant_map spm ON spm.map_id = r.shipment_map_id'
            . ' JOIN shipment s ON s.shipment_id = spm.shipment_id'
            . ' WHERE s.scheme_type = ? AND r.reported_result <> \'\''
            . '   AND NOT EXISTS (SELECT 1 FROM r_possibleresult p WHERE p.result_code = r.reported_result)'
            . ' GROUP BY r.reported_result';
    }
    $out = [];
    foreach ($db->fetchAll($sql, [$scheme]) as $row) {
        $out[(string) $row['code']] = (int) $row['n'];
    }
    return $out;
}
