<?php

// Permanent, data-safe heal for the participant_feedback_answer_ibfk_3 saga.
//
// History: migration 7.2.2 rebuilt r_feedback_questions with CREATE TABLE AS SELECT,
// silently dropping its PRIMARY KEY (and AUTO_INCREMENT). Earlier migrations pointed
// participant_feedback_answer.question_id at r_participant_feedback_form_question_map,
// whose question_id column has no unique key. MySQL 8.4+ rejects that FK on restore
// with error 6125, so any dump from an unhealed instance cannot be imported. The
// migration-based heals (7.3.6, 7.5.0, 7.6.8, 7.6.9) missed some instances: 7.3.6's
// PREPARE was silently dropped by migrate.php's parser, and on in-place 5.x->8.0
// upgrades information_schema.KEY_COLUMN_USAGE desyncs from the InnoDB dictionary,
// making migrate.php's idempotence check skip the repair while dumps keep the bad parent.
//
// This script reads the real state from SHOW CREATE TABLE (what mysqldump uses, immune
// to the desync) and heals in place:
//   1. r_feedback_questions: dedupe question_id (survivor = latest updated_datetime),
//      restore PRIMARY KEY and AUTO_INCREMENT
//   2. participant_feedback_answer: back-fill inactive stub questions for orphan
//      question_ids so no answer row is ever deleted
//   3. drop ibfk_3 by name (whatever it points at) and re-add it against
//      r_feedback_questions with FOREIGN_KEY_CHECKS=1 so every row is validated
//
// Safety lifecycle (all-or-nothing):
//   - Before touching anything, both tables are snapshotted in full
//     (*_fkheal_snapshot: structure via CREATE TABLE LIKE + data via INSERT SELECT).
//   - Any failure reverts r_feedback_questions wholesale from its snapshot
//     (drop + rename = exact pre-run structure AND data); participant_feedback_answer
//     is never written to, only its FK definition changes.
//   - Snapshots are dropped only after final verification passes. A leftover snapshot
//     found on startup (previous run interrupted mid-flight) is reconciled first:
//     the pristine snapshot wins and the heal restarts from it.
//   - Exits non-zero on failure so bin/run-once.php does not record it and retries
//     next upgrade.

ini_set('memory_limit', '-1');
require_once __DIR__ . '/../cli-bootstrap.php';

const FK_NAME = 'participant_feedback_answer_ibfk_3';
const PARENT_TABLE = 'r_feedback_questions';
const CHILD_TABLE = 'participant_feedback_answer';
const PARENT_SNAPSHOT = 'r_feedback_questions_fkheal_snapshot';
const CHILD_SNAPSHOT = 'participant_feedback_answer_fkheal_snapshot';
// Snapshots are built under a _tmp name and atomically renamed once complete, so a
// snapshot table existing under its final name is guaranteed to be a full copy.
const PARENT_SNAPSHOT_TMP = PARENT_SNAPSHOT . '_tmp';
const CHILD_SNAPSHOT_TMP = CHILD_SNAPSHOT . '_tmp';

function say(string $msg): void
{
    echo "[fk-heal] {$msg}\n";
}

try {
    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
    $dbName = $db->fetchOne('SELECT DATABASE()');

    $tableExists = function (string $table) use ($db, $dbName): bool {
        return (bool) $db->fetchOne(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$dbName, $table]
        );
    };

    // ------------------------------------------------------------------
    // Startup reconciliation: a lingering snapshot means a previous run was
    // interrupted. The snapshot is the pristine pre-run copy — restore it and
    // start over from known-good state.
    // ------------------------------------------------------------------
    // Half-built snapshots from a run killed mid-copy carry no completeness guarantee —
    // the live tables were untouched at that point, so just discard them.
    foreach ([PARENT_SNAPSHOT_TMP, CHILD_SNAPSHOT_TMP] as $tmp) {
        if ($tableExists($tmp)) {
            $db->query('DROP TABLE `' . $tmp . '`');
            say("Dropped incomplete snapshot {$tmp} from an interrupted run.");
        }
    }

    if ($tableExists(PARENT_SNAPSHOT)) {
        say('Found leftover ' . PARENT_SNAPSHOT . ' from an interrupted run. Restoring it before proceeding.');
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        if ($tableExists(PARENT_TABLE)) {
            $db->query('DROP TABLE `' . PARENT_TABLE . '`');
        }
        $db->query('RENAME TABLE `' . PARENT_SNAPSHOT . '` TO `' . PARENT_TABLE . '`');
        $db->query('SET FOREIGN_KEY_CHECKS = 1');
        say('Restored ' . PARENT_TABLE . ' from snapshot.');
    }
    if ($tableExists(CHILD_SNAPSHOT)) {
        // Answers are never modified by this script, so the live table is
        // authoritative; the stale snapshot is just a leftover copy.
        $db->query('DROP TABLE `' . CHILD_SNAPSHOT . '`');
        say('Dropped stale ' . CHILD_SNAPSHOT . '.');
    }

    if (!$tableExists(CHILD_TABLE) || !$tableExists(PARENT_TABLE)) {
        say('Feedback tables not present on this instance. Nothing to do.');
        exit(0);
    }

    // ------------------------------------------------------------------
    // Snapshot both tables before touching anything. CREATE TABLE LIKE clones
    // structure exactly (including the broken PK-less state, minus FKs), so a
    // revert restores both data AND structure to the pre-run byte-for-byte state.
    // ------------------------------------------------------------------
    $db->query('CREATE TABLE `' . PARENT_SNAPSHOT_TMP . '` LIKE `' . PARENT_TABLE . '`');
    $db->query('INSERT INTO `' . PARENT_SNAPSHOT_TMP . '` SELECT * FROM `' . PARENT_TABLE . '`');
    $db->query('CREATE TABLE `' . CHILD_SNAPSHOT_TMP . '` LIKE `' . CHILD_TABLE . '`');
    $db->query('INSERT INTO `' . CHILD_SNAPSHOT_TMP . '` SELECT * FROM `' . CHILD_TABLE . '`');
    // Single atomic RENAME = the snapshots' commit marker: they only exist under their
    // final names once both are complete copies.
    $db->query(
        'RENAME TABLE `' . PARENT_SNAPSHOT_TMP . '` TO `' . PARENT_SNAPSHOT . '`,'
        . ' `' . CHILD_SNAPSHOT_TMP . '` TO `' . CHILD_SNAPSHOT . '`'
    );
    $questionCount = (int) $db->fetchOne('SELECT COUNT(*) FROM `' . PARENT_SNAPSHOT . '`');
    $answerCount = (int) $db->fetchOne('SELECT COUNT(*) FROM `' . CHILD_SNAPSHOT . '`');
    say("Snapshotted {$questionCount} question row(s) and {$answerCount} answer row(s) before making any change.");
} catch (Throwable $e) {
    // Nothing has been modified yet — clean up any half-built snapshot and exit.
    foreach ([PARENT_SNAPSHOT_TMP, CHILD_SNAPSHOT_TMP] as $tmp) {
        try {
            $db->query('DROP TABLE IF EXISTS `' . $tmp . '`');
        } catch (Throwable $ignored) {
            // best effort — startup reconciliation clears leftovers next run
        }
    }
    Pt_Commons_LoggerUtility::logError('heal-feedback-question-fk failed before snapshot', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    say('ERROR (no changes made): ' . $e->getMessage());
    exit(1);
}

// Wholesale revert from snapshot: exact pre-run structure and data. Child FKs in
// other tables re-bind by name across the drop+rename (FOREIGN_KEY_CHECKS=0).
$revert = function () use ($db): void {
    say('Reverting ' . PARENT_TABLE . ' from snapshot...');
    $db->query('SET FOREIGN_KEY_CHECKS = 0');
    $db->query('DROP TABLE IF EXISTS `' . PARENT_TABLE . '`');
    $db->query('RENAME TABLE `' . PARENT_SNAPSHOT . '` TO `' . PARENT_TABLE . '`');
    $db->query('SET FOREIGN_KEY_CHECKS = 1');
    say('Reverted. ' . PARENT_TABLE . ' is byte-for-byte back to its pre-run state; '
        . CHILD_TABLE . ' was never written to (snapshot ' . CHILD_SNAPSHOT . ' kept for inspection).');
};

$fail = function (string $msg) use ($revert): void {
    say("ERROR: {$msg}");
    try {
        $revert();
    } catch (Throwable $revertError) {
        // Should not happen; the snapshot table remains on disk for manual recovery
        // and the startup reconciliation completes the restore on the next run.
        Pt_Commons_LoggerUtility::logError('heal-feedback-question-fk revert failed', [
            'error' => $revertError->getMessage(),
        ]);
        say('Revert hit an error (' . $revertError->getMessage() . '); snapshot tables are retained — startup reconciliation will restore on next run.');
    }
    Pt_Commons_LoggerUtility::logError('heal-feedback-question-fk failed', ['error' => $msg]);
    exit(1);
};

try {
    // SHOW CREATE TABLE reads the InnoDB dictionary directly — the same source mysqldump
    // uses — so unlike KEY_COLUMN_USAGE it cannot lie to us on desynced servers.
    $showCreate = function (string $table) use ($db): string {
        $row = $db->fetchRow("SHOW CREATE TABLE `{$table}`");
        return $row['Create Table'] ?? '';
    };

    $fkParent = function () use ($showCreate): ?string {
        $create = $showCreate(CHILD_TABLE);
        if (preg_match('/CONSTRAINT `' . FK_NAME . '` FOREIGN KEY \(`question_id`\) REFERENCES `([^`]+)`/', $create, $m)) {
            return $m[1];
        }
        return null;
    };

    // Keep any legacy question_id = 0 rows stable through auto-increment DDL/inserts.
    $db->query("SET SESSION sql_mode = CONCAT(@@SESSION.sql_mode, ',NO_AUTO_VALUE_ON_ZERO')");

    // ------------------------------------------------------------------
    // Step 1: r_feedback_questions must have PRIMARY KEY (question_id)
    // ------------------------------------------------------------------
    $parentCreate = $showCreate(PARENT_TABLE);
    $hasPk = str_contains($parentCreate, 'PRIMARY KEY (`question_id`)');

    if (!$hasPk) {
        say(PARENT_TABLE . ' is missing its PRIMARY KEY (the 7.2.2 CREATE TABLE AS SELECT bug). Repairing.');

        $dupIds = $db->fetchCol(
            'SELECT question_id FROM `' . PARENT_TABLE . '` GROUP BY question_id HAVING COUNT(*) > 1'
        );

        if (!empty($dupIds)) {
            say('Found ' . count($dupIds) . ' duplicated question_id value(s): ' . implode(', ', $dupIds));

            // No PK means no unique handle, so number the rows ourselves, then keep one
            // row per question_id: latest updated_datetime wins, physical order breaks ties.
            // Every removed copy stays available in the snapshot until final verification.
            $db->query('ALTER TABLE `' . PARENT_TABLE . '` ADD COLUMN `_fkheal_rn` INT NULL');
            $db->query('SET @fkheal_rn := 0');
            $db->query('UPDATE `' . PARENT_TABLE . '` SET `_fkheal_rn` = (@fkheal_rn := @fkheal_rn + 1)');
            // Checks off just for the dedupe: children referencing a duplicated value stay
            // valid because the surviving row keeps that value present. Some instances have
            // child FKs bound to the non-unique index whose RESTRICT would otherwise block
            // deleting even a redundant copy.
            $db->query('SET FOREIGN_KEY_CHECKS = 0');
            $deleted = $db->query(
                'DELETE FROM `' . PARENT_TABLE . '` WHERE `_fkheal_rn` NOT IN (
                    SELECT keep_rn FROM (
                        SELECT `_fkheal_rn` AS keep_rn,
                               ROW_NUMBER() OVER (
                                   PARTITION BY question_id
                                   ORDER BY COALESCE(updated_datetime, \'1000-01-01\') DESC, `_fkheal_rn` DESC
                               ) AS rnk
                        FROM `' . PARENT_TABLE . '`
                    ) ranked WHERE rnk = 1
                )'
            )->rowCount();
            $db->query('SET FOREIGN_KEY_CHECKS = 1');
            $db->query('ALTER TABLE `' . PARENT_TABLE . '` DROP COLUMN `_fkheal_rn`');
            say("Removed {$deleted} duplicate row(s); kept the most recently updated copy of each question.");
        }

        $db->query('ALTER TABLE `' . PARENT_TABLE . '` ADD PRIMARY KEY (`question_id`)');
        say('Restored PRIMARY KEY on ' . PARENT_TABLE . '.');
    }

    // The same 7.2.2 rebuild also lost AUTO_INCREMENT; without it, saving a new feedback
    // question inserts question_id = 0. Restore it.
    $extra = (string) $db->fetchOne(
        'SELECT EXTRA FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$dbName, PARENT_TABLE, 'question_id']
    );
    if (stripos($extra, 'auto_increment') === false) {
        say(PARENT_TABLE . '.question_id lost AUTO_INCREMENT. Restoring.');
        $db->query('ALTER TABLE `' . PARENT_TABLE . '` MODIFY `question_id` INT NOT NULL AUTO_INCREMENT');
        say('Restored AUTO_INCREMENT.');
    }

    // ------------------------------------------------------------------
    // Step 2: back-fill parents for orphan answers — answers are never deleted
    // ------------------------------------------------------------------
    $orphanIds = $db->fetchCol(
        'SELECT DISTINCT a.question_id
         FROM `' . CHILD_TABLE . '` a
         LEFT JOIN `' . PARENT_TABLE . '` q ON q.question_id = a.question_id
         WHERE q.question_id IS NULL'
    );

    if (!empty($orphanIds)) {
        say(count($orphanIds) . ' orphan question_id(s) referenced by answers: ' . implode(', ', $orphanIds));
        foreach ($orphanIds as $qid) {
            $db->insert(PARENT_TABLE, [
                'question_id' => (int) $qid,
                'question_text' => '[Recovered] Original question text was lost; row restored to preserve participant answers',
                'question_type' => 'text',
                'question_status' => 'inactive',
                'updated_datetime' => date('Y-m-d H:i:s'),
                'modified_by' => 'fk-heal-script',
            ]);
        }
        say('Inserted ' . count($orphanIds) . ' inactive stub question(s); every participant answer keeps its parent.');
    }

    // Test hook: FKHEAL_TEST_FAILPOINT=1 aborts here, after all data mutations, to
    // exercise the snapshot revert on a staging copy. Never set in production.
    if (getenv('FKHEAL_TEST_FAILPOINT')) {
        throw new RuntimeException('Test failpoint triggered via FKHEAL_TEST_FAILPOINT');
    }

    // ------------------------------------------------------------------
    // Step 3: repoint the FK itself
    // ------------------------------------------------------------------
    $parent = $fkParent();
    if ($parent === PARENT_TABLE) {
        say('FK ' . FK_NAME . ' already references ' . PARENT_TABLE . ' in the data dictionary. No FK change needed.');
    } else {
        say($parent === null
            ? 'FK ' . FK_NAME . ' is missing entirely. Adding it.'
            : 'FK ' . FK_NAME . " references wrong parent `{$parent}`. Repointing.");

        if ($parent !== null) {
            $db->query('ALTER TABLE `' . CHILD_TABLE . '` DROP FOREIGN KEY `' . FK_NAME . '`');
        }
        // Checks ON: after the back-fill above this must validate cleanly; if it cannot,
        // we want the loud failure (and snapshot revert), not a silently-unchecked FK.
        $db->query('SET FOREIGN_KEY_CHECKS = 1');
        $db->query(
            'ALTER TABLE `' . CHILD_TABLE . '`
             ADD CONSTRAINT `' . FK_NAME . '` FOREIGN KEY (`question_id`)
             REFERENCES `' . PARENT_TABLE . '` (`question_id`)
             ON DELETE RESTRICT ON UPDATE RESTRICT'
        );
        say('FK repointed to ' . PARENT_TABLE . ' and validated against all existing rows.');
    }

    // ------------------------------------------------------------------
    // Final verification — assert what the next mysqldump will emit, and that
    // not a single answer row was lost or changed.
    // ------------------------------------------------------------------
    if ($fkParent() !== PARENT_TABLE) {
        $fail('Post-heal verification failed: ' . FK_NAME . ' still does not reference ' . PARENT_TABLE . '.');
    }
    if (!str_contains($showCreate(PARENT_TABLE), 'PRIMARY KEY (`question_id`)')) {
        $fail('Post-heal verification failed: ' . PARENT_TABLE . ' still has no PRIMARY KEY.');
    }
    $remaining = (int) $db->fetchOne(
        'SELECT COUNT(*) FROM `' . CHILD_TABLE . '` a
         LEFT JOIN `' . PARENT_TABLE . '` q ON q.question_id = a.question_id
         WHERE q.question_id IS NULL'
    );
    if ($remaining > 0) {
        $fail("Post-heal verification failed: {$remaining} orphan answer row(s) remain.");
    }
    // Answers must be untouched: identical checksum to the pre-run snapshot.
    $checksums = $db->fetchAll('CHECKSUM TABLE `' . CHILD_TABLE . '`, `' . CHILD_SNAPSHOT . '`');
    if (count($checksums) === 2 && (string) $checksums[0]['Checksum'] !== (string) $checksums[1]['Checksum']) {
        $fail('Post-heal verification failed: ' . CHILD_TABLE . ' data no longer matches its pre-run snapshot.');
    }

    // All verified — the snapshots have served their purpose.
    $db->query('DROP TABLE `' . CHILD_SNAPSHOT . '`');
    $db->query('DROP TABLE `' . PARENT_SNAPSHOT . '`');
    say('Verification passed; snapshot tables dropped.');

    $answers = (int) $db->fetchOne('SELECT COUNT(*) FROM `' . CHILD_TABLE . '`');
    say("Healed. {$answers} answer row(s) intact and checksum-verified, FK validated, dumps from this instance will now restore on MySQL 8.4+.");
    exit(0);
} catch (Throwable $e) {
    Pt_Commons_LoggerUtility::logError('heal-feedback-question-fk failed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    $fail($e->getMessage());
}
