#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . '/../cli-bootstrap.php';

use PhpMyAdmin\SqlParser\Parser;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

// Ensure the script only runs for VLSM APP VERSION >= 4.4.3
if (version_compare(APP_VERSION, '4.4.3', '<')) {
    exit("This script requires VERSION 4.4.3 or higher. Current version: " . APP_VERSION . "\n");
}

// Define the logs directory path
$logsDir = ROOT_PATH . "/logs";

const MIG_NOT_HANDLED = 0;
const MIG_EXECUTED    = 1;
const MIG_SKIPPED     = 2;

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
} catch (Exception $e) {
    echo "Error: Failed to connect to database: " . $e->getMessage() . "\n";
    exit(1);
}

/* ---------------------- CLI flags ---------------------- */

$options = getopt("yqdv:");  // -y auto-continue on error, -q quiet, -d dry-run, -v version
$autoContinueOnError = isset($options['y']);
$quietMode           = isset($options['q']);
$DRY_RUN             = isset($options['d']); // global-ish flag (read inside helpers)
$showProgress        = !$quietMode;

if ($quietMode) {
    error_reporting(0);
}

/* ---------------------- Local helpers (idempotent DDL + progress) ---------------------- */

function current_db(Zend_Db_Adapter_Abstract $db): string
{
    static $dbName = null;
    if ($dbName === null) {
        $dbName = (string)$db->fetchOne('SELECT DATABASE()');
    }
    return $dbName;
}

function table_exists(Zend_Db_Adapter_Abstract $db, string $table): bool
{
    $sql = "SELECT 1 FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1";
    return (bool)$db->fetchOne($sql, [current_db($db), $table]);
}

/** Return ordered primary-key columns for a table (lowercased, no backticks) */
function table_primary_key(Zend_Db_Adapter_Abstract $db, string $table): array
{
    $sql = "SELECT k.COLUMN_NAME
                FROM information_schema.TABLE_CONSTRAINTS t
                JOIN information_schema.KEY_COLUMN_USAGE k
                    ON t.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                AND t.TABLE_SCHEMA = k.TABLE_SCHEMA
                AND t.TABLE_NAME   = k.TABLE_NAME
                WHERE t.TABLE_SCHEMA = ?
                AND t.TABLE_NAME   = ?
                AND t.CONSTRAINT_TYPE = 'PRIMARY KEY'
                ORDER BY k.ORDINAL_POSITION";
    $rows = $db->fetchCol($sql, [current_db($db), $table]);
    if (!$rows) return [];
    return array_map(function ($c) {
        return strtolower(trim($c, "` \t\r\n"));
    }, $rows);
}

/** Any inbound foreign keys referencing this table? (returns array of refs) */
function inbound_foreign_keys(Zend_Db_Adapter_Abstract $db, string $table): array
{
    $sql = "SELECT CONSTRAINT_NAME, TABLE_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_SCHEMA = ?
                AND REFERENCED_TABLE_NAME   = ?";
    return (array)$db->fetchAll($sql, [current_db($db), $table]);
}

/** Parse a column list like "`a`(10) ASC, `b`" into ['a','b'] (normalized). */
function parse_cols_list(string $list): array
{
    $parts = preg_split('/\s*,\s*/', trim($list));
    return array_map(static function ($c) {
        $c = trim($c, " \t\r\n`");
        // drop optional length like (10) or (10,2)
        $c = preg_replace('/\s*\(\s*\d+(?:\s*,\s*\d+)?\s*\)\s*/', '', $c);
        // drop ASC/DESC if present
        $c = preg_replace('/\s+(ASC|DESC)\b/i', '', $c);
        return strtolower($c);
    }, $parts);
}

/** Column exists? */
function column_exists(Zend_Db_Adapter_Abstract $db, string $table, string $column): bool
{
    $sql = "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    return (bool)$db->fetchOne($sql, [current_db($db), $table, $column]);
}

/** Index exists by name? */
function index_exists(Zend_Db_Adapter_Abstract $db, string $table, string $index): bool
{
    $sql = "SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1";
    return (bool)$db->fetchOne($sql, [current_db($db), $table, $index]);
}

/** Execute or preview SQL based on --dry-run */
function run_sql(Zend_Db_Adapter_Abstract $db, string $sql): void
{
    global $DRY_RUN;
    if ($DRY_RUN) {
        echo "[DRY-RUN] $sql\n";
        return;
    }
    $db->query($sql);
}

/** Add column only if missing */
function add_column_if_missing(Zend_Db_Adapter_Abstract $db, string $table, string $column, string $ddl): int
{
    if (!column_exists($db, $table, $column)) {
        run_sql($db, $ddl);
        return MIG_EXECUTED;
    }
    return MIG_SKIPPED;
}

/** Create index only if missing */
function add_index_if_missing(Zend_Db_Adapter_Abstract $db, string $table, string $index, string $ddl): int
{
    if (!index_exists($db, $table, $index)) {
        run_sql($db, $ddl);
        return MIG_EXECUTED;
    }
    return MIG_SKIPPED;
}

/** Create table only if missing */
function create_table_if_missing(Zend_Db_Adapter_Abstract $db, string $table, string $ddl): int
{
    if (!table_exists($db, $table)) {
        run_sql($db, $ddl);
        return MIG_EXECUTED;
    }
    return MIG_SKIPPED;
}

/** Drop column if exists */
function drop_column_if_exists(Zend_Db_Adapter_Abstract $db, string $table, string $column): int
{
    if (column_exists($db, $table, $column)) {
        run_sql($db, "ALTER TABLE `{$table}` DROP `{$column}`");
        return MIG_EXECUTED;
    }
    return MIG_SKIPPED;
}

/** Drop index if exists */
function drop_index_if_exists(Zend_Db_Adapter_Abstract $db, string $table, string $index): int
{
    if (index_exists($db, $table, $index)) {
        run_sql($db, "ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        return MIG_EXECUTED;
    }
    return MIG_SKIPPED;
}

/** Drop table if exists */
function drop_table_if_exists(Zend_Db_Adapter_Abstract $db, string $table): int
{
    if (table_exists($db, $table)) {
        run_sql($db, "DROP TABLE `{$table}`");
        return MIG_EXECUTED;
    }
    return MIG_SKIPPED;
}

/** Common handler for both ADD PRIMARY KEY syntaxes (with/without CONSTRAINT, optional USING BTREE). */
function _apply_add_primary_key(Zend_Db_Adapter_Abstract $db, string $table, string $colsList, string $originalSql): int
{
    $wantedCols = parse_cols_list($colsList);
    $haveCols   = table_primary_key($db, $table);

    if (empty($haveCols)) {
        run_sql($db, $originalSql);
        return MIG_EXECUTED;
    }
    if ($haveCols === $wantedCols) {
        return MIG_SKIPPED;
    }

    // Replacing an existing PK: protect against inbound FKs unless explicitly forced
    if (getenv('MIG_REPLACE_PK')) {
        $fkRefs = inbound_foreign_keys($db, $table);
        if (!empty($fkRefs) && !getenv('MIG_FORCE_PK_WITH_FK')) {
            echo "NOTE: Skipping PK replacement on `{$table}` due to inbound foreign keys. Set MIG_FORCE_PK_WITH_FK=1 to force.\n";
            return MIG_SKIPPED;
        }
        run_sql($db, "ALTER TABLE `{$table}` DROP PRIMARY KEY");
        $colsSql = implode(',', array_map(fn($c) => "`$c`", $wantedCols));
        run_sql($db, "ALTER TABLE `{$table}` ADD PRIMARY KEY ($colsSql)");
        return MIG_EXECUTED;
    }

    if (getenv('MIG_VERBOSE')) {
        echo "NOTE: Skipping PK change on {$table} (have: "
            . implode(',', $haveCols) . " want: "
            . implode(',', $wantedCols)
            . "). Set MIG_REPLACE_PK=1 to force.\n";
    }
    return MIG_SKIPPED;
}

/**
 * Route known DDL patterns through idempotent helpers.
 * Returns MIG_* status (handled and executed/skipped) or MIG_NOT_HANDLED to execute raw.
 */
function handle_idempotent_ddl(Zend_Db_Adapter_Abstract $db, string $query): int
{
    $q = trim($query);
    // Normalize occasional parser artifacts
    $q = preg_replace('/NULL\s*AFTER/i', 'NULL AFTER', $q);

    // CREATE TABLE [IF NOT EXISTS] `table` (...)
    if (preg_match('/^create\s+table\s+(?:if\s+not\s+exists\s+)?`?([^`]+)`?\s*\(/i', $q, $m)) {
        return create_table_if_missing($db, $m[1], $q);
    }

    // DROP TABLE [IF EXISTS] `table`
    if (preg_match('/^drop\s+table\s+(?:if\s+exists\s+)?`?([^`]+)`?/i', $q, $m)) {
        return drop_table_if_exists($db, $m[1]);
    }

    // ALTER TABLE ... ADD [COLUMN] `col` ...
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+add\s+(?:column\s+)?`?([a-z0-9_]+)`?\s+/i', $q, $m)) {
        return add_column_if_missing($db, $m[1], $m[2], $q);
    }

    // CREATE [UNIQUE] INDEX idx [USING BTREE] ON table (...) [USING BTREE]
    if (preg_match('/^create\s+(unique\s+)?index\s+`?([^`]+)`?\s*(?:using\s+btree)?\s+on\s+`?([^`]+)`?\s*\((.+?)\)\s*(?:using\s+btree)?\s*;?$/is', $q, $m)) {
        return add_index_if_missing($db, $m[3], $m[2], $q);
    }

    // ALTER TABLE ... ADD [UNIQUE] INDEX idx (...)   -> CREATE INDEX if missing
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+add\s+(unique\s+)?index\s+`?([a-z0-9_]+)`?\s*\((.+)\)\s*;?$/is', $q, $m)) {
        $table = $m[1];
        $uniqueKw = !empty($m[2]) ? 'UNIQUE ' : '';
        $index = $m[3];
        $cols  = trim($m[4]);
        $ddl   = sprintf('CREATE %sINDEX `%s` ON `%s` (%s)', $uniqueKw, $index, $table, $cols);
        return add_index_if_missing($db, $table, $index, $ddl);
    }

    // ALTER TABLE ... ADD [UNIQUE] KEY idx (...) (synonym)
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+add\s+(unique\s+)?key\s+`?([a-z0-9_]+)`?\s*\((.+)\)\s*;?$/is', $q, $m)) {
        $table = $m[1];
        $uniqueKw = !empty($m[2]) ? 'UNIQUE ' : '';
        $index = $m[3];
        $cols  = trim($m[4]);
        $ddl   = sprintf('CREATE %sINDEX `%s` ON `%s` (%s)', $uniqueKw, $index, $table, $cols);
        return add_index_if_missing($db, $table, $index, $ddl);
    }

    // ALTER TABLE ... DROP COLUMN `col`
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+drop\s+column\s+`?([a-z0-9_]+)`?/i', $q, $m)) {
        return drop_column_if_exists($db, $m[1], $m[2]);
    }

    // ALTER TABLE ... DROP `col` (shorthand)
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+drop\s+`?([a-z0-9_]+)`?/i', $q, $m)) {
        return drop_column_if_exists($db, $m[1], $m[2]);
    }

    // ALTER TABLE ... DROP INDEX `idx`
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+drop\s+index\s+`?([a-z0-9_]+)`?/i', $q, $m)) {
        return drop_index_if_exists($db, $m[1], $m[2]);
    }

    // ALTER TABLE ... ADD PRIMARY KEY [USING BTREE] (...) [USING BTREE]
    if (preg_match('/^alter\s+table\s+`?([^`]+)`?\s+add\s+primary\s+key\s*(?:using\s+btree)?\s*\((.+?)\)\s*(?:using\s+btree)?\s*;?$/is', $q, $m)) {
        return _apply_add_primary_key($db, $m[1], $m[2], $q);
    }

    // ALTER TABLE ... ADD CONSTRAINT `name` PRIMARY KEY [USING BTREE] (...) [USING BTREE]
    if (preg_match('/^alter\s+table\s+`?([^`]+)`?\s+add\s+constraint\s+`?([^`]+)`?\s+primary\s+key\s*(?:using\s+btree)?\s*\((.+?)\)\s*(?:using\s+btree)?\s*;?$/is', $q, $m)) {
        return _apply_add_primary_key($db, $m[1], $m[3], $q);
    }

    return MIG_NOT_HANDLED;
}

/** simple CLI progress bar (no external deps) */
function progress_bar(int $current, int $total, int $size = 30): void
{
    static $startTime;
    if (!isset($startTime)) $startTime = time();

    $elapsed = time() - $startTime;
    $pct = ($total > 0) ? $current / $total : 0;
    $bar = (int)floor($pct * $size);
    $line = sprintf(
        "\r[%s%s] %3d%% Complete (%d/%d) - %d sec elapsed",
        str_repeat('=', $bar),
        str_repeat(' ', max(0, $size - $bar)),
        (int)round($pct * 100),
        $current,
        $total,
        $elapsed
    );
    echo $line;

    if ($total > 0 && $current >= $total) {
        echo PHP_EOL;
        $startTime = null;
    }
}

/* ---------------------- End helpers ---------------------- */

// read current app version from DB (handle missing table for fresh installs)
if (table_exists($db, 'system_config')) {
    $currentVersion = (string)$db->fetchOne(
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
$migrationFiles = (array)glob(DB_PATH . '/migrations/*.sql');
$versions = array_map(fn($file) => basename($file, '.sql'), $migrationFiles);
usort($versions, 'version_compare');

// counters
$totalMigrations   = 0;
$totalQueries      = 0;
$successfulQueries = 0;
$skippedQueries    = 0;
$totalErrors       = 0;



foreach ($versions as $version) {
    $file = DB_PATH . '/migrations/' . $version . '.sql';
    // Only run strictly newer versions (avoid re-running the current file)
    if (version_compare($version, $currentVersion, '>=')) {

        echo "Migrating to version $version..." . PHP_EOL;
        $totalMigrations++;

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
            if ($q !== '') $builtStatements[] = $q;
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
                $db->query("SET FOREIGN_KEY_CHECKS = 0;");
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
                } catch (Exception $e) {
                    $msg = $e->getMessage();
                    $qLower = strtolower($query);

                    // treat duplicate/absent as benign idempotence (context-aware)
                    $isCreateTableBenign = (strpos($qLower, 'create table') === 0) &&
                        (strpos($msg, '1050') !== false || stripos($msg, 'already exists') !== false);
                    $isDropTableBenign = (strpos($qLower, 'drop table') === 0) &&
                        (strpos($msg, '1146') !== false || stripos($msg, "doesn't exist") !== false);

                    $isOtherBenign =
                        stripos($msg, 'Duplicate column name') !== false ||
                        stripos($msg, 'Duplicate key name') !== false   ||
                        (stripos($msg, "Can't DROP") !== false && stripos($msg, 'check that column/key exists') !== false) ||
                        stripos($msg, 'Multiple primary key defined') !== false || // MySQL #1068
                        strpos($msg, '1068') !== false;

                    if ($isCreateTableBenign || $isDropTableBenign || $isOtherBenign) {
                        if (!$quietMode && getenv('MIG_VERBOSE')) {
                            echo "Benign idempotence:\n{$query}\n{$msg}\n";
                        }
                        $skippedQueries++;
                    } else {
                        $totalErrors++;
                        if (!$quietMode) {
                            echo "Error executing query:\n{$query}\n{$msg}\n";
                            if ($canLog) {
                                error_log('[migration:error] ' . $msg);
                            }
                        }
                        if (!$autoContinueOnError) {
                            echo "Do you want to continue? (y/n): ";
                            $handle = fopen("php://stdin", "r");
                            $response = trim(fgets($handle));
                            fclose($handle);
                            if (strtolower($response) !== 'y') {
                                $aborted = true;
                                throw new RuntimeException("Migration aborted by user.");
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
                    $db->query("SET FOREIGN_KEY_CHECKS = 1;");
                } else {
                    echo "[DRY-RUN] SET FOREIGN_KEY_CHECKS = 1;\n";
                }
            } catch (Exception $e) { /* ignore */
            }

            if ($aborted) {
                if (!$DRY_RUN) $db->rollBack();
                exit("Migration aborted by user.\n");
            }

            // Persist the version only if the run wasn't aborted and not a dry-run
            if (!$DRY_RUN) {
                try {
                    $db->update('system_config', ['value' => $version], $db->quoteInto('config = ?', 'app_version'));
                } catch (Exception $e) {
                    if (!$quietMode) {
                        echo "Warning: failed to persist app_version to {$version}: " . $e->getMessage() . PHP_EOL;
                    }
                }
            } else {
                echo "[DRY-RUN] Would update system_config.app_version => {$version}\n";
            }

            if (!$DRY_RUN) {
                try {
                    $db->commit();
                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }
            }
        }
        unset($sql_contents, $parser, $builtStatements);
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
