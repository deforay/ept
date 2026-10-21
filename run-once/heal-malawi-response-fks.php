<?php

// One-off repair for orphan participant results and missing foreign keys (Malawi only).
//
// History: Malawi production has 5 response_result_dts rows for shipment_map_id 39310, a
// shipment_participant_map row that no longer exists. The results were entered on
// 30 May 2026 and the lab's map row was removed later while foreign key checks were off.
// The foreign key is still defined, so every dump emits it, and restoring a dump fails at
// response_result_dts_ibfk_1 (MySQL 1452). The import stops there, so the 12 foreign keys
// after it are never added either.
//
// What this script does:
//   1. Deletes result rows in response_result_dts/_eid/_vl whose shipment_participant_map row
//      is gone. The app reaches results only through that row, so these rows cannot be seen
//      or scored. Each deleted row is written to the log first.
//   2. Adds any of the 13 foreign keys at the end of the Malawi dump that are missing. On
//      production they all exist and this step changes nothing. On a copy restored from a
//      failed import it adds them back. Checks stay on, so every row is validated.
//
// Orphans under any other of these foreign keys are not deleted: the script stops and
// reports them, because that data may still matter.
//
// Exits non-zero on failure so bin/run-once.php does not record it and retries next upgrade.

ini_set('memory_limit', '-1');
require_once __DIR__ . '/../cli-bootstrap.php';

const TARGET_INSTANCE = 'malawi';

// Result tables whose orphan rows are safe to delete
const RESULT_TABLES = ['response_result_dts', 'response_result_eid', 'response_result_vl'];

// [table, constraint, column, parent table, parent column, referential actions], as in the dump
const FOREIGN_KEYS = [
    ['response_result_dts', 'response_result_dts_ibfk_1', 'shipment_map_id', 'shipment_participant_map', 'map_id', ''],
    ['response_result_eid', 'response_result_eid_ibfk_1', 'shipment_map_id', 'shipment_participant_map', 'map_id', ''],
    ['response_result_vl', 'response_result_vl_ibfk_1', 'shipment_map_id', 'shipment_participant_map', 'map_id', ''],
    ['r_participant_feedback_form', 'r_participant_feedback_form_ibfk_1', 'shipment_id', 'shipment', 'shipment_id', 'ON DELETE RESTRICT ON UPDATE RESTRICT'],
    ['r_participant_feedback_form', 'r_participant_feedback_form_ibfk_2', 'question_id', 'r_feedback_questions', 'question_id', 'ON DELETE RESTRICT ON UPDATE RESTRICT'],
    ['r_participant_feedback_form_files_map', 'r_participant_feedback_form_files_map_ibfk_1', 'shipment_id', 'shipment', 'shipment_id', 'ON DELETE RESTRICT ON UPDATE RESTRICT'],
    ['r_participant_feedback_form_question_map', 'r_participant_feedback_form_question_map_ibfk_1', 'shipment_id', 'shipment', 'shipment_id', ''],
    ['r_participant_feedback_form_question_map', 'r_participant_feedback_form_question_map_ibfk_2', 'question_id', 'r_feedback_questions', 'question_id', ''],
    ['r_participant_feedback_form_question_map', 'r_participant_feedback_form_question_map_ibfk_3', 'rpff_id', 'r_participant_feedback_form', 'rpff_id', 'ON DELETE RESTRICT ON UPDATE RESTRICT'],
    ['shipment', 'shipment_ibfk_2', 'distribution_id', 'distributions', 'distribution_id', ''],
    ['shipment_participant_map', 'shipment_participant_map_ibfk_1', 'shipment_id', 'shipment', 'shipment_id', ''],
    ['shipment_participant_map', 'shipment_participant_map_ibfk_2', 'participant_id', 'participant', 'participant_id', ''],
    ['tb_instruments', 'tb_instruments_ibfk_1', 'participant_id', 'participant', 'participant_id', 'ON DELETE RESTRICT ON UPDATE RESTRICT'],
];

function say(string $msg): void
{
    echo "[malawi-fks] {$msg}\n";
}

try {
    $db = Zend_Db_Table_Abstract::getDefaultAdapter();

    // --- Gate: Malawi only ------------------------------------------------------------
    $instance = (string) $db->fetchOne("SELECT value FROM global_config WHERE name = 'instance'");
    if (strtolower(trim($instance)) !== TARGET_INSTANCE) {
        say('Instance is ' . ($instance !== '' ? "'{$instance}'" : '(unset)') . ", not '" . TARGET_INSTANCE . "'. Nothing to do.");
        exit(0);
    }

    $dbName = (string) $db->fetchOne('SELECT DATABASE()');
    $tableExists = function (string $table) use ($db, $dbName): bool {
        return (bool) $db->fetchOne(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$dbName, $table]
        );
    };
    // SHOW CREATE TABLE reads the InnoDB dictionary, the same source a dump uses, so it
    // stays correct where information_schema has drifted after an in-place upgrade.
    $hasForeignKey = function (string $table, string $constraint) use ($db): bool {
        $row = $db->fetchRow("SHOW CREATE TABLE `{$table}`");
        return str_contains($row['Create Table'] ?? '', "CONSTRAINT `{$constraint}` FOREIGN KEY");
    };
    $orphanSql = function (array $fk, string $select): string {
        [$table, , $column, $parent, $parentColumn] = $fk;
        return "SELECT {$select} FROM `{$table}` c LEFT JOIN `{$parent}` p ON p.`{$parentColumn}` = c.`{$column}`"
            . " WHERE c.`{$column}` IS NOT NULL AND p.`{$parentColumn}` IS NULL";
    };

    $foreignKeys = array_values(array_filter(
        FOREIGN_KEYS,
        fn (array $fk): bool => $tableExists($fk[0]) && $tableExists($fk[3])
    ));

    // --- Step 1: delete result rows whose lab entry is gone ------------------------------
    $db->beginTransaction();
    $deleted = 0;
    foreach ($foreignKeys as $fk) {
        if (!in_array($fk[0], RESULT_TABLES, true)) {
            continue;
        }
        $rows = $db->fetchAll($orphanSql($fk, 'c.*'));
        if (empty($rows)) {
            continue;
        }
        Pt_Commons_LoggerUtility::logInfo("heal-malawi-response-fks: deleting orphan {$fk[0]} rows", ['rows' => $rows]);
        $mapIds = array_values(array_unique(array_column($rows, $fk[2])));
        $count = $db->delete($fk[0], [$db->quoteIdentifier($fk[2]) . ' IN (?)' => $mapIds]);
        say("{$fk[0]}: deleted {$count} row(s) for missing shipment_map_id " . implode(', ', $mapIds) . '.');
        $deleted += $count;
    }
    $db->commit();

    // --- Stop if any other foreign key still has orphans --------------------------------
    $blocked = [];
    foreach ($foreignKeys as $fk) {
        $left = (int) $db->fetchOne($orphanSql($fk, 'COUNT(*)'));
        if ($left > 0) {
            $blocked[] = "{$fk[1]} ({$left} row(s) in {$fk[0]} with no {$fk[3]})";
        }
    }
    if (!empty($blocked)) {
        throw new RuntimeException('Orphan rows block these foreign keys: ' . implode('; ', $blocked) . '.');
    }

    // --- Step 2: add the foreign keys that are missing ----------------------------------
    $db->query('SET FOREIGN_KEY_CHECKS = 1');
    $added = 0;
    foreach ($foreignKeys as [$table, $constraint, $column, $parent, $parentColumn, $actions]) {
        if ($hasForeignKey($table, $constraint)) {
            continue;
        }
        $db->query(trim(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`{$column}`)"
                . " REFERENCES `{$parent}` (`{$parentColumn}`) {$actions}"
        ));
        say("Added {$constraint}.");
        $added++;
    }

    // --- Verify -------------------------------------------------------------------------
    foreach ($foreignKeys as [$table, $constraint]) {
        if (!$hasForeignKey($table, $constraint)) {
            throw new RuntimeException("Verification failed: {$constraint} is still missing.");
        }
    }

    say($deleted === 0 && $added === 0
        ? 'No orphan results and no missing foreign keys. Nothing to do.'
        : "Done. Deleted {$deleted} orphan result row(s) and added {$added} foreign key(s).");
    exit(0);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof Zend_Db_Adapter_Abstract) {
        try {
            $db->rollBack();
        } catch (Throwable $ignored) {
            // no open transaction
        }
    }
    say('FAILED: ' . $e->getMessage());
    if (class_exists('Pt_Commons_LoggerUtility')) {
        Pt_Commons_LoggerUtility::logError('heal-malawi-response-fks failed', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
    exit(1);
}
