#!/usr/bin/env php
<?php

// bin/migrate.php — idempotent schema migrator.
//
// Usage:
//   php bin/migrate.php              run pending migrations (re-applies the current version too)
//   php bin/migrate.php -y           auto-continue on non-benign error (no prompt)
//   php bin/migrate.php -q           quiet mode
//   php bin/migrate.php -d           dry-run: print statements, touch nothing
//   php bin/migrate.php -v 7.4.1     replay inclusively from a specific version (ignores DB version)
//   php bin/migrate.php --status     show current version + pending files, then exit
//
// Env: MIG_VERBOSE=1 traces idempotent routing; MIG_REPLACE_PK=1 forces PK replacement.

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . '/../cli-bootstrap.php';
require_once __DIR__ . '/lib/migration-sql.php';
require_once __DIR__ . '/lib/migration-helpers.php';

use PhpMyAdmin\SqlParser\Parser;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

// Ensure the script only runs for VLSM APP VERSION >= 4.4.3
if (version_compare(APP_VERSION, '4.4.3', '<')) {
    exit('This script requires VERSION 4.4.3 or higher. Current version: ' . APP_VERSION . "\n");
}

// Define the logs directory path
$logsDir = ROOT_PATH . '/logs';

$canLog = false;
if (!file_exists($logsDir)) {
    if (!@mkdir($logsDir, 0755, true)) {
        echo "Failed to create directory: $logsDir\n";
    } else {
        echo "Directory created: $logsDir\n";
        $canLog = is_readable($logsDir) && is_writable($logsDir);
    }
} else {
    $canLog = is_readable($logsDir) && is_writable($logsDir);
}

try {
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);
} catch (Throwable $e) {
    echo 'Error: Failed to connect to database: ' . $e->getMessage() . "\n";
    exit(1);
}

/* ---------------------- CLI flags ---------------------- */

$options = getopt('yqdv:', ['status']);  // -y auto-continue on error, -q quiet, -d dry-run, -v version, --status preview pending
$autoContinueOnError = isset($options['y']);
$quietMode = isset($options['q']);
$DRY_RUN = isset($options['d']); // global-ish flag (read inside helpers)
$showStatus = isset($options['status']);
$showProgress = !$quietMode;

if ($quietMode) {
    error_reporting(0);
}

// read current app version from DB (handle missing table for fresh installs)
if (table_exists($db, 'system_config')) {
    $currentVersion = (string) $db->fetchOne(
        $db->select()->from('system_config', ['value'])
            ->where('config = ?', 'app_version')
    );
} else {
    // Table doesn't exist - start from beginning (run all migrations)
    $currentVersion = '0.0.0';
    if (!$quietMode) {
        echo "Note: system_config table not found. Running all migrations from the beginning.\n";
    }
}

// Override version if -v flag is provided
if (isset($options['v'])) {
    $currentVersion = $options['v'];
    if (!$quietMode) {
        echo "Starting from version: $currentVersion (overridden by -v flag)\n";
    }
}

// collect migrations
$migrationFiles = (array) glob(DB_PATH . '/migrations/*.sql');
$versions = array_map(fn ($file) => basename($file, '.sql'), $migrationFiles);
usort($versions, 'version_compare');

// --status: report current version + pending migrations, then exit without touching the DB.
// Pending uses the same `>=` rule as the runner, so the current version shows up as
// pending too — by design, ept re-applies the current version idempotently on every run.
if ($showStatus) {
    $pending = array_values(array_filter($versions, fn ($v) => version_compare($v, $currentVersion, '>=')));
    echo 'Current DB version : ' . ($currentVersion ?: '(none)') . PHP_EOL;
    echo 'Pending migrations :' . PHP_EOL;
    if (empty($pending)) {
        echo '  (none — database is up to date)' . PHP_EOL;
    } else {
        foreach ($pending as $v) {
            echo "  {$v}.sql" . PHP_EOL;
        }
    }
    exit(0);
}

// counters
$totalMigrations = 0;
$totalQueries = 0;
$successfulQueries = 0;
$skippedQueries = 0;
$totalErrors = 0;

foreach ($versions as $version) {
    $file = DB_PATH . '/migrations/' . $version . '.sql';
    // Only run strictly newer versions (avoid re-running the current file)
    if (version_compare($version, $currentVersion, '>=')) {

        echo "Migrating to version $version..." . PHP_EOL;
        $totalMigrations++;
        $versionErrors = 0;

        // Every migration file bumps app_version itself on its first line, so the
        // recorded version says "this file was opened", not "this file applied".
        // Snapshot what the DB claims now; a failed run restores it below.
        $versionBefore = null;
        if (!$DRY_RUN && table_exists($db, 'system_config')) {
            try {
                $versionBefore = (string) $db->fetchOne(
                    $db->select()->from('system_config', ['value'])
                        ->where('config = ?', 'app_version')
                );
            } catch (Throwable $e) {
                $versionBefore = null;
            }
        }

        $sql_contents = file_get_contents($file);
        // Normalize SQL comments: "-- comment" requires a space after "--" per the
        // SQL standard, but migration files sometimes omit it (e.g. "--Insert ...").
        // Without the space the parser treats the line as a statement, causing errors.
        $sql_contents = preg_replace('/^(\s*--)(?=\S)/m', '$1 ', $sql_contents);
        $parser = new Parser($sql_contents);

        // pre-build/trim statements for accurate per-version progress
        $builtStatements = [];
        foreach ($parser->statements as $st) {
            $q = trim($st->build() ?? '');
            if ($q !== '') {
                $builtStatements[] = $q;
            }
        }
        $versionTotal = count($builtStatements);
        $processedForVersion = 0;
        if ($showProgress && $versionTotal > 0) {
            progress_bar(0, $versionTotal);
        }

        // run
        if (!$DRY_RUN) {
            $db->beginTransaction();
        }
        $aborted = false;

        try {
            if (!$DRY_RUN) {
                $db->query('SET FOREIGN_KEY_CHECKS = 0;');
            } else {
                echo "[DRY-RUN] SET FOREIGN_KEY_CHECKS = 0;\n";
            }

            foreach ($builtStatements as $query) {
                $totalQueries++;
                try {
                    $status = handle_idempotent_ddl($db, $query);

                    if ($status === MIG_SKIPPED) {
                        $skippedQueries++;
                        // idempotent target state already satisfied (count as success-like)
                        continue;
                    }
                    if ($status === MIG_EXECUTED) {
                        $successfulQueries++;
                        continue;
                    }

                    // Execute raw (or print if dry-run)
                    if ($DRY_RUN) {
                        echo "[DRY-RUN] $query\n";
                    } else {
                        $db->query($query);
                    }
                    $successfulQueries++;
                } catch (Throwable $e) {
                    $msg = $e->getMessage();
                    $qLower = strtolower($query);
                    // Prefer the stable numeric MySQL errno; fall back to English text
                    // matching below in case the driver doesn't surface a code.
                    $errno = mysql_errno_from_exception($e);

                    // treat duplicate/absent as benign idempotence (context-aware)
                    $isCreateTableBenign = (strpos($qLower, 'create table') === 0) &&
                        ($errno === 1050 || stripos($msg, 'already exists') !== false);
                    $isDropTableBenign = (strpos($qLower, 'drop table') === 0) &&
                        ($errno === 1146 || stripos($msg, "doesn't exist") !== false);

                    // RENAME TABLE old->new: dest already exists (1050) or source
                    // already gone (1146) both mean the rename effectively happened.
                    $isRenameTableBenign = (strpos($qLower, 'rename table') === 0 ||
                        preg_match('/^alter\s+table\s+`?[a-z0-9_]+`?\s+rename\b/', $qLower) === 1) &&
                        ($errno === 1050 || $errno === 1146 ||
                            stripos($msg, 'already exists') !== false ||
                            stripos($msg, "doesn't exist") !== false);

                    // Seed-style INSERTs in migrations are re-runnable: a 1062 on a
                    // pre-existing PK/UNIQUE row means the seed already landed.
                    $isInsertDupBenign = (strpos($qLower, 'insert') === 0) &&
                        ($errno === 1062 || stripos($msg, 'Duplicate entry') !== false);

                    // Context-free idempotence codes: 1060 dup column, 1061 dup key,
                    // 1068 multi-PK, 1091 can't-drop-missing, 1826 dup FK name.
                    $isOtherBenign =
                        in_array($errno, [1060, 1061, 1068, 1091, 1826], true) ||
                        stripos($msg, 'Duplicate column name') !== false ||
                        stripos($msg, 'Duplicate key name') !== false ||
                        (stripos($msg, "Can't DROP") !== false && stripos($msg, 'check that column/key exists') !== false) ||
                        stripos($msg, 'Multiple primary key defined') !== false; // MySQL #1068

                    // Replay tolerance: an old data-fix UPDATE/DELETE may reference a
                    // column/table that a LATER migration renamed or dropped. When the
                    // full history is replayed onto an already-newer schema, such a
                    // statement is a no-op that effectively already happened. Treat 1054
                    // (unknown column) and 1146 (table missing) on UPDATE/DELETE as
                    // benign — but always surface it (not just under MIG_VERBOSE) so a
                    // genuine typo in a fresh migration isn't silently swallowed.
                    //
                    // INSERT ... SELECT backfills are the same one-time-migration shape:
                    // on replay the source table/column that a later migration dropped is
                    // gone (1146/1054), and the rows they would have copied are already in
                    // place. Cover them too (a plain INSERT ... VALUES can't hit 1054/1146,
                    // so this only ever matches genuine SELECT-from-source backfills).
                    $isDataDml = (strpos($qLower, 'update') === 0 || strpos($qLower, 'delete') === 0);
                    $isInsertSelect = (strpos($qLower, 'insert') === 0 && strpos($qLower, ' select ') !== false);
                    $isStaleRefBenign = ($isDataDml || $isInsertSelect) &&
                        ($errno === 1054 || $errno === 1146 ||
                            stripos($msg, 'Unknown column') !== false ||
                            stripos($msg, "doesn't exist") !== false);

                    // A data-fix UPDATE that renames a PK/UNIQUE value collides (1062) on
                    // replay because the rename already happened — the destination row
                    // already carries the new key. No-op, benign (e.g. global_config
                    // feed_back_option -> participant_feedback re-run).
                    $isRenameDmlBenign = $isDataDml &&
                        ($errno === 1062 || stripos($msg, 'Duplicate entry') !== false);

                    if ($isStaleRefBenign || $isRenameDmlBenign) {
                        if (!$quietMode) {
                            echo "Skipping data fix (stale column/table ref on replay):\n{$query}\n{$msg}\n";
                        }
                        if ($canLog) {
                            Pt_Commons_LoggerUtility::logWarning('[migration:skip] stale column/table ref on replay: ' . $msg);
                        }
                        $skippedQueries++;
                    } elseif ($isCreateTableBenign || $isDropTableBenign || $isRenameTableBenign || $isInsertDupBenign || $isOtherBenign) {
                        if (!$quietMode && getenv('MIG_VERBOSE')) {
                            echo "Benign idempotence:\n{$query}\n{$msg}\n";
                        }
                        $skippedQueries++;
                    } else {
                        $totalErrors++;
                        $versionErrors++;
                        // Always surface a non-benign error, quiet mode included: -q is
                        // how `composer migrate` runs during an upgrade, and swallowing
                        // this left the chain halting with no visible reason.
                        fwrite(STDERR, "Error executing query:\n{$query}\n{$msg}\n");
                        if ($canLog) {
                            Pt_Commons_LoggerUtility::logError('[migration:error] ' . $msg);
                        }
                        if (!$autoContinueOnError) {
                            echo 'Do you want to continue? (y/n): ';
                            $handle = fopen('php://stdin', 'r');
                            $response = trim(fgets($handle));
                            fclose($handle);
                            if (strtolower($response) !== 'y') {
                                $aborted = true;
                                throw new RuntimeException('Migration aborted by user.');
                            }
                        }
                    }
                } finally {
                    $processedForVersion++;
                    if ($showProgress && $versionTotal > 0) {
                        progress_bar($processedForVersion, $versionTotal);
                    }
                }
            }

            echo "Migration to version $version completed." . PHP_EOL;
        } finally {
            // restore FKs and finish transaction
            try {
                if (!$DRY_RUN) {
                    $db->query('SET FOREIGN_KEY_CHECKS = 1;');
                } else {
                    echo "[DRY-RUN] SET FOREIGN_KEY_CHECKS = 1;\n";
                }
            } catch (Throwable $e) { /* ignore */
            }

            if ($aborted) {
                if (!$DRY_RUN) {
                    $db->rollBack();
                    // The file's own app_version bump is statement 1 and any DDL after it
                    // committed it implicitly, so the rollback above cannot reach it.
                    // Put the recorded version back by hand, exactly as the error path
                    // does -- otherwise answering "n" leaves the DB claiming a version it
                    // never finished and check-version-sync reports it as in sync.
                    if ($versionBefore !== null && $versionBefore !== '') {
                        try {
                            $db->update('system_config', ['value' => $versionBefore], $db->quoteInto('config = ?', 'app_version'));
                        } catch (Throwable $e) {
                            fwrite(STDERR, "Warning: failed to restore app_version to {$versionBefore}: " . $e->getMessage() . PHP_EOL);
                        }
                    }
                }
                // exit() with a string prints it and exits 0, which reported an
                // aborted migration as a success. Print, then exit with a failure code.
                fwrite(STDERR, "Migration aborted by user.\n");
                exit(1);
            }

            // Persist the version only if the run wasn't aborted, not a dry-run, AND
            // no non-benign errors occurred. Previously, errors under -y (or a user
            // answering "y" to continue) were logged but app_version was still bumped,
            // leaving the DB in a state that claimed to be migrated while silently
            // missing DDL (e.g. the Malawi participant_feedback_answer FK rewrite).
            $shouldBumpVersion = $versionErrors === 0;
            if (!$DRY_RUN) {
                if ($shouldBumpVersion) {
                    try {
                        $db->update('system_config', ['value' => $version], $db->quoteInto('config = ?', 'app_version'));
                    } catch (Throwable $e) {
                        // Left unreported this reads as a clean migration while the DB
                        // still records the older version, so count it like any other
                        // failure and let the exit code carry it.
                        $totalErrors++;
                        fwrite(STDERR, "Warning: failed to persist app_version to {$version}: " . $e->getMessage() . PHP_EOL);
                    }
                } else {
                    // The file's own bump already ran inside this transaction; undo it so
                    // a failed migration cannot report itself done. Restoring the snapshot
                    // (rather than stripping the statement) leaves every migration file
                    // usable standalone. Only restore when there was a value to begin with.
                    if ($versionBefore !== null && $versionBefore !== '') {
                        try {
                            $db->update('system_config', ['value' => $versionBefore], $db->quoteInto('config = ?', 'app_version'));
                        } catch (Throwable $e) {
                            fwrite(STDERR, "Warning: failed to restore app_version to {$versionBefore}: " . $e->getMessage() . PHP_EOL);
                        }
                    }
                    fwrite(STDERR, "\n*** app_version NOT bumped to {$version}: {$versionErrors} non-benign error(s) occurred. ***\n");
                    fwrite(STDERR, "    Fix the underlying issue(s) above and re-run migrate.php.\n");
                }
            } else {
                if ($shouldBumpVersion) {
                    echo "[DRY-RUN] Would update system_config.app_version => {$version}\n";
                } else {
                    echo "[DRY-RUN] Would NOT bump app_version to {$version} ({$versionErrors} error(s)).\n";
                }
            }

            if (!$DRY_RUN) {
                try {
                    $db->commit();
                } catch (Throwable $e) {
                    $db->rollBack();
                    throw $e;
                }
            }
        }
        unset($sql_contents, $parser, $builtStatements);

        // Halt the migration chain on unresolved errors: downstream migrations often
        // assume prior versions applied cleanly, so continuing risks cascading damage.
        if ($versionErrors > 0) {
            fwrite(STDERR, "Halting further migrations after {$version} due to unresolved errors.\n");
            break;
        }
    }

    gc_collect_cycles();
}

// Migration summary
if (!$quietMode) {
    echo "\n=======================================\n";
    echo "Migration summary:\n";
    echo "  Migrations attempted : $totalMigrations\n";
    echo "  Queries executed     : $totalQueries\n";
    echo "  Successful queries   : $successfulQueries\n";
    echo "  Skipped queries      : $skippedQueries\n";
    echo "  Errors logged        : $totalErrors\n";
    echo "=======================================\n\n";
}

// A failed migration has to be a failed command. `composer migrate` runs
// `migrate.php -yq`, so -y keeps the run going past an error to apply what it can
// and the chain then halts -- but a zero exit told composer the step succeeded, so
// it went on to @collation and the real error scrolled away unnoticed.
// The exit code now reports what the run already knows.
if ($totalErrors > 0) {
    exit(1);
}
