#!/usr/bin/env php
<?php

use Pt_Commons_MiscUtility as MiscUtility;
use Symfony\Component\Console\Helper\ProgressBar;


/**
 * This script converts the database tables and columns to use the utf8mb4 character set.
 * It ensures compatibility with emojis and other special characters.
 * Optimized for performance on large tables by only converting what needs to be converted.
 * Preserves all column properties including constraints, defaults, comments, etc.
 *
 * Note: This script should only be run from the command line.
 */

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/../cli-bootstrap.php';

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

$db = Zend_Db::factory($conf->resources->db);
Zend_Db_Table::setDefaultAdapter($db);

// Parse command line arguments
$options = getopt('dbtsv', ['dry-run', 'batch-size:', 'table:', 'skip-columns', 'verbose']);
$dryRun = isset($options['dry-run']) || isset($options['d']);
$batchSize = isset($options['batch-size']) ? (int)$options['batch-size'] : (isset($options['b']) ? (int)$options['b'] : 10);
$specificTable = isset($options['table']) ? $options['table'] : (isset($options['t']) ? $options['t'] : null);
$skipColumnConversion = isset($options['skip-columns']) || isset($options['s']);
$verbose = isset($options['verbose']) || isset($options['v']);

// Collection of errors for summary at the end
$tableErrors = [];
$columnErrors = [];
$successfulTables = [];
$skippedTables = [];

$__activeProgressBar = null;

// Terminal colors for better readability
$colors = [
    'reset' => "\033[0m",
    'red' => "\033[31m",
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'magenta' => "\033[35m",
    'cyan' => "\033[36m",
    'white' => "\033[37m",
    'bold' => "\033[1m"
];

/** Register (or clear) the active spinner ProgressBar used by echoMessage */
function setEchoProgressBar(?ProgressBar $bar): void
{
    $GLOBALS['__activeProgressBar'] = $bar;
}

/** Echo message with color + verbosity, auto-pausing the spinner if active */
function echoMessage(string $message, ?string $color = null, bool $alwaysShow = false): void
{
    global $verbose, $colors;

    if (!$verbose && !$alwaysShow) return;

    $line = $message;
    if ($color && isset($colors[$color])) {
        $line = $colors[$color] . $message . $colors['reset'];
    }

    $bar = $GLOBALS['__activeProgressBar'] ?? null;

    if ($bar instanceof ProgressBar) {
        // pause the spinner, print, then resume
        MiscUtility::spinnerPausePrint($bar, function () use ($line) {
            echo $line . PHP_EOL;
        });
    } else {
        echo $line . PHP_EOL;
    }
}

if ($specificTable) {
    echoMessage("Processing specific table: $specificTable", 'bold', true);
}
if ($skipColumnConversion) {
    echoMessage("Skipping individual column conversion (only converting tables)", 'yellow', true);
}

$dbName = $conf->resources->db->params->dbname;

/**
 * Check if MySQL version is 8.0 or higher
 *
 * @param Zend_Db_Adapter_Abstract $db
 * @return bool
 */
function isMySQL8OrHigher(Zend_Db_Adapter_Abstract $db): bool
{
    try {
        $version = $db->fetchOne("SELECT VERSION()");
        return version_compare($version, '8.0.0', '>=');
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if a table needs conversion based on its current charset and collation
 *
 * @param Zend_Db_Adapter_Abstract $db
 * @param string $tableName
 * @param string $targetCollation
 * @return array [bool $needsConversion, string $currentCollation]
 */
function tableNeedsConversion(Zend_Db_Adapter_Abstract $db, string $tableName, string $targetCollation)
{
    $tableStatus = $db->fetchAll("SHOW TABLE STATUS LIKE ?", [$tableName]);

    if (empty($tableStatus)) {
        return [false, 'unknown'];
    }

    $table = $tableStatus[0];
    $currentCollation = $table['Collation'];
    return [$currentCollation !== $targetCollation, $currentCollation];
}

/**
 * Get complete column information for columns that need conversion
 *
 * @param Zend_Db_Adapter_Abstract $db
 * @param string $schema
 * @param string $tableName
 * @param string $targetCollation
 * @return array
 */
function getColumnsNeedingConversion(Zend_Db_Adapter_Abstract $db, string $schema, string $tableName, string $targetCollation)
{
    $query = "SELECT
                c.COLUMN_NAME,
                c.COLUMN_TYPE,
                c.IS_NULLABLE,
                c.COLUMN_DEFAULT,
                c.EXTRA,
                c.COLLATION_NAME,
                c.COLUMN_COMMENT,
                c.ORDINAL_POSITION
              FROM information_schema.columns c
              WHERE c.table_schema = ?
              AND c.table_name = ?
              AND c.DATA_TYPE IN ('char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext', 'enum', 'set')
              AND c.COLLATION_NAME IS NOT NULL
              AND c.COLLATION_NAME != ?
              ORDER BY c.ORDINAL_POSITION";

    return $db->fetchAll($query, [$schema, $tableName, $targetCollation]);
}

/**
 * Get indexes that reference a specific column
 *
 * @param Zend_Db_Adapter_Abstract $db
 * @param string $schema
 * @param string $tableName
 * @param string $columnName
 * @return array
 */
function getColumnIndexes(Zend_Db_Adapter_Abstract $db, string $schema, string $tableName, string $columnName)
{
    $query = "SELECT DISTINCT
                INDEX_NAME,
                NON_UNIQUE,
                INDEX_TYPE,
                COLUMN_NAME
              FROM information_schema.statistics
              WHERE table_schema = ?
              AND table_name = ?
              AND column_name = ?
              AND INDEX_NAME != 'PRIMARY'";

    return $db->fetchAll($query, [$schema, $tableName, $columnName]);
}

/**
 * Build proper column definition preserving all properties
 *
 * @param array $column Column information from information_schema
 * @param string $targetCollation Target collation
 * @return string Complete column definition
 */
function buildColumnDefinition(array $column, string $targetCollation): string
{
    $definition = "`{$column['COLUMN_NAME']}` {$column['COLUMN_TYPE']} CHARACTER SET utf8mb4 COLLATE $targetCollation";

    // Handle NULL/NOT NULL
    if ($column['IS_NULLABLE'] === 'NO') {
        $definition .= " NOT NULL";
    } else {
        $definition .= " NULL";
    }

    // Handle DEFAULT values with proper escaping and special cases
    if ($column['COLUMN_DEFAULT'] !== null) {
        $defaultValue = $column['COLUMN_DEFAULT'];

        // Special function defaults that don't need quotes
        $functionDefaults = [
            'CURRENT_TIMESTAMP',
            'current_timestamp()',
            'now()',
            'CURRENT_TIMESTAMP()',
            'NULL',
            'CURRENT_DATE',
            'CURRENT_TIME',
            'LOCALTIME',
            'LOCALTIMESTAMP'
        ];

        if (in_array(strtoupper($defaultValue), array_map('strtoupper', $functionDefaults))) {
            $definition .= " DEFAULT $defaultValue";
        } else {
            // Escape and quote string defaults
            $escapedDefault = str_replace("'", "''", $defaultValue);
            $definition .= " DEFAULT '$escapedDefault'";
        }
    }

    // Handle EXTRA attributes (AUTO_INCREMENT, ON UPDATE, etc.)
    if (!empty($column['EXTRA'])) {
        $definition .= " {$column['EXTRA']}";
    }

    // Handle COMMENT
    if (!empty($column['COLUMN_COMMENT'])) {
        $escapedComment = str_replace("'", "''", $column['COLUMN_COMMENT']);
        $definition .= " COMMENT '$escapedComment'";
    }

    return $definition;
}

/**
 * Verify that column conversion was successful
 *
 * @param Zend_Db_Adapter_Abstract $db
 * @param string $schema
 * @param string $tableName
 * @param string $columnName
 * @param string $targetCollation
 * @return bool
 */
function verifyColumnConversion(Zend_Db_Adapter_Abstract $db, string $schema, string $tableName, string $columnName, string $targetCollation): bool
{
    $query = "SELECT COLLATION_NAME
              FROM information_schema.columns
              WHERE table_schema = ?
              AND table_name = ?
              AND column_name = ?";

    $result = $db->fetchAll($query, [$schema, $tableName, $columnName]);

    return !empty($result) && $result[0]['COLLATION_NAME'] === $targetCollation;
}

/**
 * Converts a table and only the necessary columns to utf8mb4 character set.
 *
 * @param Zend_Db_Adapter_Abstract $db
 * @param string $schema
 * @param string $tableName
 * @param bool $dryRun
 * @param bool $skipColumnConversion
 * @return array Results [success, error, skipped counts, etc.]
 */
function convertTableAndColumns(Zend_Db_Adapter_Abstract $db, string $schema, string $tableName, bool $dryRun = false, bool $skipColumnConversion = false, ?ProgressBar $bar = null)
{
    global $tableErrors, $columnErrors, $successfulTables, $skippedTables, $verbose;

    $result = [
        'tableName' => $tableName,
        'tableConverted' => false,
        'tableSkipped' => false,
        'tableError' => null,
        'columnsConverted' => 0,
        'columnsSkipped' => 0,
        'columnsWithErrors' => 0,
        'columnErrors' => []
    ];

    $collation = isMySQL8OrHigher($db) ? 'utf8mb4_0900_ai_ci' : 'utf8mb4_unicode_ci';

    // Check if table needs conversion
    list($tableNeedsConversion, $currentCollation) = tableNeedsConversion($db, $tableName, $collation);

    // Get table size information
    try {
        $tableSizeInfo = $db->fetchAll(
            "SELECT ROUND((data_length + index_length) / 1024 / 1024, 2) AS 'Size'
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = ?",
            [$tableName]
        );
        $tableSize = !empty($tableSizeInfo) ? $tableSizeInfo[0]['Size'] : 'unknown';
    } catch (Exception $e) {
        $tableSize = 'unknown';
    }

    if (!$tableNeedsConversion) {
        echoMessage("✅ Table $tableName ($tableSize MB) already uses $collation - skipping table conversion", 'green');

        $result['tableSkipped'] = true;
        $skippedTables[] = "$tableName (already using $collation)";
    } else {
        echoMessage("⚙ Converting table: $tableName ($tableSize MB) from $currentCollation to $collation", 'cyan');


        if (!$dryRun) {
            try {
                $startTime = microtime(true);
                $db->query("ALTER TABLE `$tableName` CONVERT TO CHARACTER SET utf8mb4 COLLATE $collation");
                $duration = round(microtime(true) - $startTime, 2);
                echoMessage("✅ Table converted successfully in $duration seconds", 'green');
                $result['tableConverted'] = true;
                $successfulTables[] = $tableName;
            } catch (Exception $e) {
                $errorMsg = "Failed to convert table '$tableName': " . $e->getMessage();
                echoMessage("❌ $errorMsg", 'red', true); // Always show errors
                $result['tableError'] = $errorMsg;
                $tableErrors[$tableName] = $errorMsg;
                return $result; // Skip column conversion if table conversion failed
            }
        } else {
            echoMessage("🔍 DRY RUN: Would convert table structure to utf8mb4 with $collation", 'yellow');
        }
    }

    if ($skipColumnConversion) {
        echoMessage("⏩ Skipping individual column conversion as requested", 'yellow');
        return $result;
    }

    // Only get columns that need conversion
    $columnsNeedingConversion = getColumnsNeedingConversion($db, $schema, $tableName, $collation);

    if (empty($columnsNeedingConversion)) {
        echoMessage("✅ All columns in $tableName already use correct collation", 'green');
        return $result;
    }

    echoMessage("⚙ Found " . count($columnsNeedingConversion) . " columns needing conversion in $tableName", 'cyan');

    if (!$dryRun) {
        $totalColumns = count($columnsNeedingConversion);

        foreach ($columnsNeedingConversion as $index => $column) {
            $currentColumn = $index + 1;
            $columnName = $column['COLUMN_NAME'];

            // reflect current work on the single bar
            if ($bar) {
                MiscUtility::spinnerUpdate($bar, $tableName, null, $currentColumn - 1, $totalColumns);
            }

            try {
                // Show indexes that might be affected in verbose mode
                if ($verbose) {
                    $indexes = getColumnIndexes($db, $schema, $tableName, $columnName);
                    if (!empty($indexes)) {
                        echoMessage("    ⚠ Column $columnName has " . count($indexes) . " indexes that may be affected", 'yellow');
                        foreach ($indexes as $index) {
                            $indexType = $index['NON_UNIQUE'] == '0' ? 'UNIQUE' : 'INDEX';
                            echoMessage("      - {$index['INDEX_NAME']} ($indexType, {$index['INDEX_TYPE']})", 'yellow');
                        }
                    }
                }


                echoMessage("  ⚙ Converting column: $columnName (current collation: {$column['COLLATION_NAME']})", 'cyan');


                // Build complete column definition preserving all properties
                $columnDefinition = buildColumnDefinition($column, $collation);

                if ($verbose) {
                    echoMessage("    SQL: ALTER TABLE `$tableName` MODIFY COLUMN $columnDefinition", 'blue');
                }

                $startTime = microtime(true);
                $db->query("ALTER TABLE `$tableName` MODIFY COLUMN $columnDefinition");

                // Verify the conversion was successful
                if (verifyColumnConversion($db, $schema, $tableName, $columnName, $collation)) {
                    $duration = round(microtime(true) - $startTime, 2);
                    echoMessage("  ✅ Column $columnName converted and verified in $duration seconds", 'green');
                    $result['columnsConverted']++;
                } else {
                    $errorMsg = "Column $tableName.$columnName conversion appeared to succeed but verification failed";
                    echoMessage("  ❌ $errorMsg", 'red', true);
                    $result['columnsWithErrors']++;
                    $result['columnErrors'][] = $errorMsg;
                    $columnErrors[] = "$tableName.$columnName: Verification failed";
                }
            } catch (Exception $e) {
                $errorMsg = "Failed to convert column '$columnName' in table '$tableName': " . $e->getMessage();
                echoMessage("  ❌ $errorMsg", 'red', true); // Always show errors

                // Log the complete column definition that failed
                $failedDefinition = buildColumnDefinition($column, $collation);
                echoMessage("    Failed SQL: ALTER TABLE `$tableName` MODIFY COLUMN $failedDefinition", 'red', true);

                $result['columnsWithErrors']++;
                $result['columnErrors'][] = $errorMsg;
                $columnErrors[] = "$tableName.$columnName: " . $e->getMessage();
            }
        }
    } else {
        foreach ($columnsNeedingConversion as $column) {
            $columnDefinition = buildColumnDefinition($column, $collation);
            echoMessage("  🔍 DRY RUN: Would execute: ALTER TABLE `$tableName` MODIFY COLUMN $columnDefinition", 'yellow');
            $result['columnsSkipped']++;
        }
    }

    return $result;
}

/**
 * Retrieves a list of tables from the database.
 *
 * @param Zend_Db_Adapter_Abstract $db
 * @param string $schema
 * @param string|null $specificTable
 * @return array
 * @throws Exception
 */
function fetchTables(Zend_Db_Adapter_Abstract $db, string $schema, ?string $specificTable = null)
{
    $query = "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = ?";
    $params = [$schema];

    if ($specificTable) {
        $query .= " AND TABLE_NAME = ?";
        $params[] = $specificTable;
    }

    $tables = $db->fetchAll($query, $params);

    if (!$tables) {
        if ($specificTable) {
            throw new Exception("Table '$specificTable' not found in the database $schema.");
        } else {
            throw new Exception("No tables found in the database $schema.");
        }
    }

    // Return just the table names
    return array_map(function ($table) {
        return $table['TABLE_NAME'];
    }, $tables);
}

/**
 * Process tables in batches to prevent memory issues
 *
 * @param array $tables
 * @param int $batchSize
 * @param callable $processFunction
 * @param bool $verbose
 * @return array
 */
function processBatches(array $tables, int $batchSize, callable $processFunction, bool $verbose = true, ?ProgressBar $bar = null)
{
    $totalTables = count($tables);
    $batches = ceil($totalTables / $batchSize);
    $results = [];

    echoMessage("Processing $totalTables tables in $batches batches of up to $batchSize tables each", 'bold', true);

    for ($i = 0; $i < $totalTables; $i += $batchSize) {
        $batchTables = array_slice($tables, $i, $batchSize);

        foreach ($batchTables as $index => $tableName) {
            $currentPosition = $i + $index + 1;
            if ($verbose) {
                echoMessage("Processing table $currentPosition of $totalTables: {$tableName}", 'bold', true);
            }

            $results[] = $processFunction($tableName, $currentPosition, $totalTables);
        }

        // Force garbage collection between batches
        if ($batches > 1) {
            echoMessage("Cleaning up memory between batches...", null);
            gc_collect_cycles();
        }
    }

    return $results;
}

/**
 * Display summary of conversion results
 *
 * @param array $results
 */
function displaySummary(array $results): void
{
    global $tableErrors, $columnErrors, $successfulTables, $skippedTables, $colors;

    $totalTablesConverted = count($successfulTables);
    $totalTablesSkipped = count($skippedTables);
    $totalColumnsConverted = 0;

    foreach ($results as $result) {
        if (isset($result['columnsConverted'])) {
            $totalColumnsConverted += $result['columnsConverted'];
        }
    }

    echo PHP_EOL . $colors['bold'] . "CONVERSION SUMMARY:" . $colors['reset'] . PHP_EOL;
    echo "  Tables converted: " . $colors['green'] . $totalTablesConverted . $colors['reset'] . PHP_EOL;
    echo "  Tables skipped (already correct): " . $colors['yellow'] . $totalTablesSkipped . $colors['reset'] . PHP_EOL;
    echo "  Columns converted: " . $colors['green'] . $totalColumnsConverted . $colors['reset'] . PHP_EOL;

    // Display errors if any
    if (!empty($tableErrors)) {
        echo PHP_EOL . $colors['bold'] . $colors['red'] . "TABLE ERRORS:" . $colors['reset'] . PHP_EOL;
        foreach ($tableErrors as $table => $error) {
            echo "  - $table: $error" . PHP_EOL;
        }
    }

    if (!empty($columnErrors)) {
        echo PHP_EOL . $colors['bold'] . $colors['red'] . "COLUMN ERRORS:" . $colors['reset'] . PHP_EOL;
        foreach ($columnErrors as $error) {
            echo "  - $error" . PHP_EOL;
        }
    }

    // Final status message
    if (empty($tableErrors) && empty($columnErrors)) {
        if ($totalTablesConverted > 0 || $totalColumnsConverted > 0) {
            echo PHP_EOL . $colors['bold'] . $colors['green'] . "✅ All conversions completed successfully!" . $colors['reset'] . PHP_EOL;
        } else {
            echo PHP_EOL . $colors['bold'] . $colors['green'] . "✅ Database already uses correct collation - no changes needed!" . $colors['reset'] . PHP_EOL;
        }
    } else {
        echo PHP_EOL . $colors['bold'] . $colors['yellow'] . "⚠ Conversion completed with some errors." . $colors['reset'] . PHP_EOL;
    }
}

try {
    // Fetch the list of tables
    $tablesList = fetchTables($db, $dbName, $specificTable);

    $totalTables = count($tablesList);
    echoMessage("Starting conversion process for $totalTables tables...", 'bold', true);

    // Start timer
    $scriptStartTime = microtime(true);
    $bar = MiscUtility::spinnerStart($totalTables, "Preparing…");
    setEchoProgressBar($bar);


    // Process tables in batches and collect results
    $results = processBatches(
        $tablesList,
        $batchSize,
        function ($tableName, $current, $total) use ($db, $dbName, $dryRun, $skipColumnConversion, $bar) {
            // Show which table is being worked on,
            // but progress should be “done so far”, not including this one.
            MiscUtility::spinnerUpdate($bar, $tableName, null, $current - 1, $total);

            $result = convertTableAndColumns($db, $dbName, $tableName, $dryRun, $skipColumnConversion, $bar);

            // Now the table is done -> bump progress
            MiscUtility::spinnerAdvance($bar, 1);

            return $result;
        },
        $verbose,
        $bar
    );


    $totalDuration = microtime(true) - $scriptStartTime;
    MiscUtility::spinnerUpdate($bar, '', null, $totalTables, $totalTables);
    MiscUtility::spinnerFinish($bar);
    setEchoProgressBar(null);

    // Display summary
    displaySummary($results);

    echo PHP_EOL . "Total execution time: " . round($totalDuration, 2) . " seconds" . PHP_EOL;
} catch (Exception $e) {
    echoMessage("An error occurred during the conversion process: " . $e->getMessage(), 'red', true);
    echoMessage("File: " . $e->getFile() . " Line: " . $e->getLine(), 'red', true);
    exit(1);
}
