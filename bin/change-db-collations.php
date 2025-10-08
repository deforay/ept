#!/usr/bin/env php
<?php

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

/**
 * Echo message with optional colorization and verbosity check
 *
 * @param string $message
 * @param string|null $color
 * @param bool $alwaysShow
 */
function echoMessage(string $message, ?string $color = null, bool $alwaysShow = false)
{
    global $verbose, $colors;

    if (!$verbose && !$alwaysShow) return;

    if ($color && isset($colors[$color])) {
        echo $colors[$color] . $message . $colors['reset'] . PHP_EOL;
    } else {
        echo $message . PHP_EOL;
    }
}

/**
 * Custom progress bar that shows the current table name
 *
 * @param int $current Current position
 * @param int $total Total items
 * @param string $tableName Current table name
 * @param int $size Progress bar size
 */
function customProgressBar(int $current, int $total, string $tableName, int $size = 30): void
{
    static $startTime;

    // Initialize the timer on the first call
    if (!isset($startTime)) {
        $startTime = time();
    }

    // Calculate elapsed time
    $elapsed = time() - $startTime;

    // Calculate progress percentage
    $progress = ($current / $total);
    $barLength = (int) floor($progress * $size);

    // Generate the progress bar
    $progressBar = str_repeat('=', $barLength) . str_repeat(' ', $size - $barLength);

    // Truncate table name if too long
    $displayName = (strlen($tableName) > 20) ? substr($tableName, 0, 17) . '...' : $tableName;


    echo PHP_EOL;
    echo PHP_EOL;
    // Output the progress bar with current table name
    printf(
        "\r[%s] %3d%% (%d/%d) - %s - %d sec elapsed",
        $progressBar,
        $progress * 100,
        $current,
        $total,
        $displayName,
        $elapsed
    );

    echo PHP_EOL;

    // Flush output for real-time updates
    fflush(STDOUT);

    // Print a newline and reset the timer when done
    if ($current === $total) {
        echo PHP_EOL;
        $startTime = null; // Reset timer for reuse
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
function tableNeedsConversion(Zend_Db_Adapter_Abstract $db, string $tableName, string $targetCollation): array
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
function getColumnsNeedingConversion(Zend_Db_Adapter_Abstract $db, string $schema, string $tableName, string $targetCollation): array
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
function getColumnIndexes(Zend_Db_Adapter_Abstract $db, string $schema, string $tableName, string $columnName): array
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
function convertTableAndColumns(Zend_Db_Adapter_Abstract $db, string $schema, string $tableName, bool $dryRun = false, bool $skipColumnConversion = false): array
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
        echoMessage("✅ Table $tableName ($tableSize MB) already uses $collation - skipping table conversion", 'green', true); // Add true here
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
        echoMessage("✅ All columns in $tableName already use correct collation", 'green', true); // Add true here
        return $result;
    }

    echoMessage("⚙ Found " . count($columnsNeedingConversion) . " columns needing conversion in $tableName", 'cyan');

    if (!$dryRun) {
        $totalColumns = count($columnsNeedingConversion);

        foreach ($columnsNeedingConversion as $index => $column) {
            $currentColumn = $index + 1;
            $columnName = $column['COLUMN_NAME'];

            // Show column progress for tables with multiple columns
            if ($totalColumns > 1) {
                printf("\r  Column %d/%d: %s", $currentColumn, $totalColumns, $columnName);
                fflush(STDOUT);
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

        // Add a newline after column progress completes
        if ($totalColumns > 1) {
            echo PHP_EOL;
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
function fetchTables(Zend_Db_Adapter_Abstract $db, string $schema, ?string $specificTable = null): array
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
function processBatches(array $tables, int $batchSize, callable $processFunction, bool $verbose = true): array
{
    $totalTables = count($tables);
    $batches = ceil($totalTables / $batchSize);
    $results = [];

    echoMessage("Processing $totalTables tables in $batches batches of up to $batchSize tables each", 'bold', true);

    for ($i = 0; $i < $totalTables; $i += $batchSize) {
        $batchTables = array_slice($tables, $i, $batchSize);

        foreach ($batchTables as $index => $tableName) {
            $currentPosition = $i + $index + 1;
            // Show overall progress using custom progress bar with table name
            customProgressBar($currentPosition, $totalTables, $tableName);

            if ($verbose) {
                echo PHP_EOL; // Add a line break for verbose output
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

    // Process tables in batches and collect results
    $results = processBatches($tablesList, $batchSize, function ($tableName, $current, $total) use ($db, $dbName, $dryRun, $skipColumnConversion) {
        return convertTableAndColumns($db, $dbName, $tableName, $dryRun, $skipColumnConversion);
    }, $verbose);

    $totalDuration = microtime(true) - $scriptStartTime;

    // Display summary
    displaySummary($results);

    echo PHP_EOL . "Total execution time: " . round($totalDuration, 2) . " seconds" . PHP_EOL;
} catch (Exception $e) {
    echoMessage("An error occurred during the conversion process: " . $e->getMessage(), 'red', true);
    echoMessage("File: " . $e->getFile() . " Line: " . $e->getLine(), 'red', true);
    exit(1);
}
