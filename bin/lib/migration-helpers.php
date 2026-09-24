<?php

// Idempotent-DDL and progress helpers used by bin/migrate.php, kept in their own file so
// tests can load them without bootstrapping the app or connecting to a database.
// Helpers read the global $DRY_RUN flag set by the caller.

const MIG_NOT_HANDLED = 0;
const MIG_EXECUTED = 1;
const MIG_SKIPPED = 2;

function current_db(Zend_Db_Adapter_Abstract $db): string
{
    static $dbName = null;
    if ($dbName === null) {
        $dbName = (string) $db->fetchOne('SELECT DATABASE()');
    }
    return $dbName;
}

function table_exists(Zend_Db_Adapter_Abstract $db, string $table): bool
{
    $sql = 'SELECT 1 FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1';
    return (bool) $db->fetchOne($sql, [current_db($db), $table]);
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
    if (!$rows) {
        return [];
    }
    return array_map(function ($c) {
        return strtolower(trim($c, "` \t\r\n"));
    }, $rows);
}

/** Any inbound foreign keys referencing this table? (returns array of refs) */
function inbound_foreign_keys(Zend_Db_Adapter_Abstract $db, string $table): array
{
    $sql = 'SELECT CONSTRAINT_NAME, TABLE_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_SCHEMA = ?
                AND REFERENCED_TABLE_NAME   = ?';
    return (array) $db->fetchAll($sql, [current_db($db), $table]);
}

/** Does a foreign key with this name exist on the given table? */
function foreign_key_exists(Zend_Db_Adapter_Abstract $db, string $table, string $name): bool
{
    $sql = "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY' LIMIT 1";
    return (bool) $db->fetchOne($sql, [current_db($db), $table, $name]);
}

/** Does an existing FK match the intended columns/reference? (case-insensitive) */
function foreign_key_matches(
    Zend_Db_Adapter_Abstract $db,
    string $table,
    string $name,
    array $cols,
    string $refTable,
    array $refCols
): bool {
    $sql = 'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
            ORDER BY ORDINAL_POSITION';
    $rows = $db->fetchAll($sql, [current_db($db), $table, $name]);
    if (count($rows) !== count($cols)) {
        return false;
    }
    foreach ($rows as $i => $r) {
        if (strtolower($r['COLUMN_NAME']) !== strtolower($cols[$i])) {
            return false;
        }
        if (strtolower((string) $r['REFERENCED_TABLE_NAME']) !== strtolower($refTable)) {
            return false;
        }
        if (strtolower((string) $r['REFERENCED_COLUMN_NAME']) !== strtolower($refCols[$i])) {
            return false;
        }
    }
    return true;
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
    $sql = 'SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1';
    return (bool) $db->fetchOne($sql, [current_db($db), $table, $column]);
}

/** Index exists by name? */
function index_exists(Zend_Db_Adapter_Abstract $db, string $table, string $index): bool
{
    $sql = 'SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1';
    return (bool) $db->fetchOne($sql, [current_db($db), $table, $index]);
}

/** Execute or preview SQL based on --dry-run */
function run_sql(Zend_Db_Adapter_Abstract $db, string $sql): void
{
    global $DRY_RUN;
    if ($DRY_RUN) {
        echo "[DRY-RUN] $sql\n";
        return;
    }
    mig_trace('run_sql', substr($sql, 0, 200));
    $db->query($sql);
}

/** Add column only if missing */
function add_column_if_missing(Zend_Db_Adapter_Abstract $db, string $table, string $column, string $ddl): int
{
    $exists = column_exists($db, $table, $column);
    mig_trace('add_column_if_missing', "{$table}.{$column} exists=" . ($exists ? 'true' : 'false'));
    if ($exists) {
        return MIG_SKIPPED;
    }
    // Schema drifts across installs: if the column is positioned `AFTER <anchor>`
    // and that anchor column doesn't exist here, drop the AFTER clause so the ADD
    // still lands (column order is cosmetic). Prevents a 1054 "Unknown column" on
    // `AFTER <missing>` — the failure mode when an earlier migration that should
    // have created the anchor never landed on this install.
    if (preg_match('/\bafter\s+`?([a-z0-9_]+)`?\s*;?\s*$/i', $ddl, $am) && !column_exists($db, $table, $am[1])) {
        $ddl = preg_replace('/\s+after\s+`?[a-z0-9_]+`?(\s*;?\s*)$/i', '$1', $ddl);
        mig_trace('add_column_if_missing', "{$table}.{$column}: stripped dangling AFTER `{$am[1]}`");
    }
    run_sql($db, $ddl);
    return MIG_EXECUTED;
}

/** Create index only if missing */
function add_index_if_missing(Zend_Db_Adapter_Abstract $db, string $table, string $index, string $ddl): int
{
    $exists = index_exists($db, $table, $index);
    mig_trace('add_index_if_missing', "{$table}.{$index} exists=" . ($exists ? 'true' : 'false'));
    if (!$exists) {
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

/**
 * Drop a column if present. MySQL refuses the drop (errno 1072) while any index
 * or foreign key still references it, and hand-rolled migrations don't always
 * drop those first. Sweep referencing FKs and non-PRIMARY indexes before the
 * drop so it always lands even on a drifted install. (Borrowed from vlsm.)
 */
function drop_column_if_exists(Zend_Db_Adapter_Abstract $db, string $table, string $column): int
{
    if (!column_exists($db, $table, $column)) {
        return MIG_SKIPPED;
    }

    $dbName = current_db($db);

    // 1. Drop source-side FK constraints that include this column. (FKs where this
    //    column is the referenced parent are left alone — auto-dropping those would
    //    silently break integrity elsewhere.)
    $fks = (array) $db->fetchCol(
        'SELECT DISTINCT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL',
        [$dbName, $table, $column]
    );
    foreach ($fks as $fk) {
        run_sql($db, "ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`");
    }

    // 2. Drop non-PRIMARY indexes that include this column. PRIMARY is left alone
    //    so we don't silently remove the table's primary key.
    $idx = (array) $db->fetchCol(
        "SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
              AND INDEX_NAME != 'PRIMARY'",
        [$dbName, $table, $column]
    );
    foreach ($idx as $i) {
        run_sql($db, "ALTER TABLE `{$table}` DROP INDEX `{$i}`");
    }

    // 3. Now the column drop will succeed.
    run_sql($db, "ALTER TABLE `{$table}` DROP `{$column}`");
    return MIG_EXECUTED;
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
    // Schema drift: the table may not exist on this install (e.g. a feature whose
    // tables were never created here). There's nothing to add a PK to, so skip
    // rather than fail with 1146 "Base table or view not found". The PK-heal only
    // matters where the table actually exists.
    if (!table_exists($db, $table)) {
        mig_trace('_apply_add_primary_key', "{$table} missing; skip");
        return MIG_SKIPPED;
    }

    $wantedCols = parse_cols_list($colsList);
    $haveCols = table_primary_key($db, $table);

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
        $colsSql = implode(',', array_map(fn ($c) => "`$c`", $wantedCols));
        run_sql($db, "ALTER TABLE `{$table}` ADD PRIMARY KEY ($colsSql)");
        return MIG_EXECUTED;
    }

    if (getenv('MIG_VERBOSE')) {
        echo "NOTE: Skipping PK change on {$table} (have: "
            . implode(',', $haveCols) . ' want: '
            . implode(',', $wantedCols)
            . "). Set MIG_REPLACE_PK=1 to force.\n";
    }
    return MIG_SKIPPED;
}

/** Verbose trace helper. Set MIG_VERBOSE=1 to enable. */
function mig_trace(string $tag, string $detail = ''): void
{
    if (getenv('MIG_VERBOSE')) {
        echo "[trace] {$tag}" . ($detail !== '' ? " :: {$detail}" : '') . PHP_EOL;
    }
}

/**
 * Pull the MySQL driver errno out of a thrown DB exception. Zend_Db/PDO surface
 * the SQLSTATE (e.g. '42S21') via getCode(), NOT the numeric MySQL errno — the
 * real code (1060, 1062, …) lives in the chained PDOException's errorInfo[1], or
 * embedded in the message as "SQLSTATE[..]: ...: <errno> ...". Returns 0 if none
 * can be determined. Numeric codes are stable across MySQL/MariaDB versions and
 * locales, so benign-idempotence checks key off this rather than English text.
 */
function mysql_errno_from_exception(Throwable $e): int
{
    for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
        if ($cur instanceof PDOException && isset($cur->errorInfo[1])) {
            return (int) $cur->errorInfo[1];
        }
    }
    if (preg_match('/SQLSTATE\[[^\]]*\]:.*?:\s*(\d{3,4})\b/s', $e->getMessage(), $m)) {
        return (int) $m[1];
    }
    return 0;
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
    mig_trace('handle_idempotent_ddl', substr($q, 0, 160));

    // A successful first ADD does not prove that the remaining ADDs landed.
    // Split only column additions. Other ALTER actions can depend on atomic execution.
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+(.+)$/is', $q, $alter)) {
        $clauses = migration_split_clauses($alter[2]);
        if (count($clauses) > 1) {
            $onlyColumnAdds = true;
            foreach ($clauses as $clause) {
                if (!preg_match('/^add\s+(?:column\s+)?(?!(?:primary|unique|key|index|constraint|foreign|check|fulltext|spatial)\b)`?[a-z0-9_]+`?\s+/i', $clause)) {
                    $onlyColumnAdds = false;
                    break;
                }
            }
            if ($onlyColumnAdds) {
                $result = MIG_SKIPPED;
                foreach ($clauses as $clause) {
                    if (handle_idempotent_ddl($db, "ALTER TABLE `{$alter[1]}` {$clause}") === MIG_EXECUTED) {
                        $result = MIG_EXECUTED;
                    }
                }
                return $result;
            }
            // Do not let single-action handlers silently skip part of a mixed ALTER.
            return MIG_NOT_HANDLED;
        }
    }

    // CREATE TABLE [IF NOT EXISTS] `table` (...)
    if (preg_match('/^create\s+table\s+(?:if\s+not\s+exists\s+)?`?([^`]+)`?\s*\(/i', $q, $m)) {
        return create_table_if_missing($db, $m[1], $q);
    }

    // DROP TABLE [IF EXISTS] `table`
    if (preg_match('/^drop\s+table\s+(?:if\s+exists\s+)?`?([^`]+)`?/i', $q, $m)) {
        return drop_table_if_exists($db, $m[1]);
    }

    // ALTER TABLE ... ADD [COLUMN] `col` ...
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+add\s+(?:column\s+)?(?!(?:primary|unique|key|index|constraint|foreign|check|fulltext|spatial)\b)`?([a-z0-9_]+)`?\s+/i', $q, $m)) {
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
        $cols = trim($m[4]);
        $ddl = sprintf('CREATE %sINDEX `%s` ON `%s` (%s)', $uniqueKw, $index, $table, $cols);
        return add_index_if_missing($db, $table, $index, $ddl);
    }

    // ALTER TABLE ... ADD [UNIQUE] KEY idx (...) (synonym)
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+add\s+(unique\s+)?key\s+`?([a-z0-9_]+)`?\s*\((.+)\)\s*;?$/is', $q, $m)) {
        $table = $m[1];
        $uniqueKw = !empty($m[2]) ? 'UNIQUE ' : '';
        $index = $m[3];
        $cols = trim($m[4]);
        $ddl = sprintf('CREATE %sINDEX `%s` ON `%s` (%s)', $uniqueKw, $index, $table, $cols);
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

    // ALTER TABLE t ADD CONSTRAINT `name` FOREIGN KEY (cols) REFERENCES `ref` (refCols) ...
    // Idempotent: if a FK with the same name already exists and matches the intended
    // reference, skip. If the name exists but points elsewhere, drop and re-add so the
    // migration cannot silently leave a stale FK in place (root cause of the Malawi
    // participant_feedback_answer_ibfk_3 issue).
    if (
        preg_match(
            '/^alter\s+table\s+`?([a-z0-9_]+)`?\s+add\s+constraint\s+`?([a-z0-9_]+)`?\s+foreign\s+key\s*\(([^)]+)\)\s+references\s+`?([a-z0-9_]+)`?\s*\(([^)]+)\)/is',
            $q,
            $m
        )
    ) {
        $table = $m[1];
        $fkName = $m[2];
        $cols = parse_cols_list($m[3]);
        $refTable = $m[4];
        $refCols = parse_cols_list($m[5]);

        // Schema drift: on an install where either the child or the referenced
        // parent table was never created, the FK is moot — skip rather than fail
        // with 1146. (Same rationale as the ADD PRIMARY KEY guard above.)
        if (!table_exists($db, $table) || !table_exists($db, $refTable)) {
            mig_trace('add_constraint_fk', "{$table} -> {$refTable}: missing table; skip");
            return MIG_SKIPPED;
        }

        if (foreign_key_exists($db, $table, $fkName)) {
            if (foreign_key_matches($db, $table, $fkName, $cols, $refTable, $refCols)) {
                return MIG_SKIPPED;
            }
            run_sql($db, "ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
        }
        run_sql($db, $q);
        return MIG_EXECUTED;
    }

    // ALTER TABLE t DROP FOREIGN KEY `name`
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+drop\s+foreign\s+key\s+`?([a-z0-9_]+)`?/i', $q, $m)) {
        if (foreign_key_exists($db, $m[1], $m[2])) {
            run_sql($db, $q);
            return MIG_EXECUTED;
        }
        return MIG_SKIPPED;
    }

    // ALTER TABLE t MODIFY [COLUMN] `col` <definition...>
    // A column redefinition assumes the column is there. On an instance whose table
    // predates it (Malawi's user_login_history had no login_context), MySQL answers
    // 1054 and the whole migration chain halts on a statement whose intent -- "the
    // column should look like this" -- is satisfiable by creating it. Route it through
    // add_column_if_missing, which adds it with this very definition when absent and
    // otherwise runs the MODIFY untouched. Single-clause statements only: a
    // comma-separated ALTER carries clauses this rewrite would drop.
    if (
        preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+modify\s+(?:column\s+)?`?([a-z0-9_]+)`?\s+(.+?)\s*;?$/is', $q, $m) &&
        preg_match('/,\s*(?:add|drop|modify|change)\s/i', $q) !== 1
    ) {
        $table = $m[1];
        $column = $m[2];
        // No table, nothing to redefine and nothing to add it to: adding the column
        // would only trade 1054 for 1146 and halt the chain just the same. Skip, as
        // the ADD PRIMARY KEY and ADD CONSTRAINT handlers do on the same drift.
        if (!table_exists($db, $table)) {
            mig_trace('modify->skip', "{$table} missing");
            return MIG_SKIPPED;
        }
        if (!column_exists($db, $table, $column)) {
            $ddl = sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, trim($m[3]));
            echo "Healing: `{$table}`.`{$column}` is missing; adding it with the definition this MODIFY asks for." . PHP_EOL;
            mig_trace('modify->add', $ddl);
            // Through add_column_if_missing rather than run_sql, so a MODIFY carrying
            // `AFTER <anchor>` gets the same dangling-anchor strip as an ADD would.
            return add_column_if_missing($db, $table, $column, $ddl);
        }
        return MIG_NOT_HANDLED;
    }

    // ALTER TABLE t CHANGE [COLUMN] `old` `new` <definition...>  (column rename)
    // Idempotent: if the rename already happened (old column gone, new present), skip.
    // If `old` still exists we fall through to raw exec to perform it; a same-name
    // CHANGE (re-type, old == new) also falls through and runs normally.
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+change\s+(?:column\s+)?`?([a-z0-9_]+)`?\s+`?([a-z0-9_]+)`?\s+/i', $q, $m)) {
        if (strcasecmp($m[2], $m[3]) !== 0 && !column_exists($db, $m[1], $m[2]) && column_exists($db, $m[1], $m[3])) {
            return MIG_SKIPPED;
        }
    }

    // ALTER TABLE t RENAME COLUMN `old` TO `new`  (MySQL 8.0+ syntax)
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+rename\s+column\s+`?([a-z0-9_]+)`?\s+to\s+`?([a-z0-9_]+)`?/i', $q, $m)) {
        if (!column_exists($db, $m[1], $m[2]) && column_exists($db, $m[1], $m[3])) {
            return MIG_SKIPPED;
        }
    }

    // RENAME TABLE `old` TO `new`  /  ALTER TABLE `old` RENAME [TO] `new`
    // Idempotent: if the source is already gone, the rename has happened (or the
    // source never existed) — skip rather than blow up with 1050 (dest exists) or
    // 1146 (source missing). Only run when source exists and dest is absent.
    if (
        preg_match('/^rename\s+table\s+`?([a-z0-9_]+)`?\s+to\s+`?([a-z0-9_]+)`?\s*;?$/i', $q, $m) ||
        preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+rename\s+(?:to\s+)?`?([a-z0-9_]+)`?\s*;?$/i', $q, $m)
    ) {
        $from = $m[1];
        $to = $m[2];
        $fromExists = table_exists($db, $from);
        $toExists = table_exists($db, $to);
        // already renamed (dest present) or nothing to rename (source absent)
        if (!$fromExists || $toExists) {
            return MIG_SKIPPED;
        }
        run_sql($db, $q);
        return MIG_EXECUTED;
    }

    return MIG_NOT_HANDLED;
}

/** simple CLI progress bar (no external deps) */
function progress_bar(int $current, int $total, int $size = 30): void
{
    static $startTime;
    if (!isset($startTime)) {
        $startTime = time();
    }

    $elapsed = time() - $startTime;
    $pct = ($total > 0) ? $current / $total : 0;
    $bar = (int) floor($pct * $size);
    $line = sprintf(
        "\r[%s%s] %3d%% Complete (%d/%d) - %d sec elapsed",
        str_repeat('=', $bar),
        str_repeat(' ', max(0, $size - $bar)),
        (int) round($pct * 100),
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
