#!/usr/bin/env php
<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    exit(0);
}

$options = getopt('', ['output::', 'table::', 'help']);

if (isset($options['help'])) {
    echo <<<TXT
Usage:
  php bin/generate-db-translation-strings.php [--output=/path/to/file.php] [--table=r_possibleresult]

Options:
  --output    Output PHP file. Defaults to application/languages/db-translation-strings.php
  --table     Limit extraction to one or more r_* tables. Repeat the flag to pass multiple tables.
  --help      Show this help text.

TXT;
    exit(0);
}

require_once __DIR__ . '/../cli-bootstrap.php';

ini_set('memory_limit', '-1');
set_time_limit(0);

const DEFAULT_OUTPUT_FILE = APPLICATION_PATH . '/languages/db-translation-strings.php';

/**
 * Define the lookup tables/columns that should feed gettext.
 * Values can change at runtime, but this structure is expected to stay stable.
 */
$tablesToTranslate = [
    'r_control' => ['control_name'],
    'r_covid19_corrective_actions' => ['corrective_action', 'description'],
    'r_covid19_gene_types' => ['gene_name'],
    'r_dbs_eia' => ['eia_name'],
    'r_dbs_wb' => ['wb_name'],
    'r_dts_corrective_actions' => ['corrective_action', 'description'],
    'r_eid_detection_assay' => ['name'],
    'r_eid_extraction_assay' => ['name'],
    'r_enrolled_programs' => ['enrolled_programs'],
    'r_evaluation_comments' => ['comment'],
    'r_feedback_questions' => ['question_text'],
    'r_modes_of_receipt' => ['mode_name'],
    'r_network_tiers' => ['network_name'],
    'r_participant_affiliates' => ['affiliate'],
    'r_possibleresult' => ['response'],
    'r_recency_assay' => ['name'],
    'r_response_not_tested_reasons' => ['ntr_reason'],
    'r_response_vl_not_tested_reason' => ['vl_not_tested_reason'],
    'r_results' => ['result_name'],
    'r_site_type' => ['site_type'],
    'r_tb_assay' => ['name', 'short_name'],
    'r_test_type_covid19' => ['test_type_name', 'test_type_short_name'],
    'r_testkitnames' => ['TestKit_Name', 'TestKit_Name_Short'],
    'r_vl_assay' => ['name', 'short_name'],
];

try {
    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
    if (!$db instanceof Zend_Db_Adapter_Abstract) {
        throw new RuntimeException('Default database adapter is not available. Check cli-bootstrap.php and DB configuration.');
    }

    $databaseName = (string)$db->fetchOne('SELECT DATABASE()');
    if ($databaseName === '') {
        throw new RuntimeException('Could not determine the active database for this instance.');
    }

    $outputFile = (string)($options['output'] ?? DEFAULT_OUTPUT_FILE);
    $requestedTables = normalizeTableFilter($options['table'] ?? []);

    $tableColumns = resolveTranslatableColumns($db, $databaseName, $tablesToTranslate, $requestedTables);
    $translatableStrings = fetchTranslatableStrings($db, $tableColumns);

    writeTranslationFile($outputFile, $databaseName, $tableColumns, $translatableStrings);

    $tableCount = count($tableColumns);
    $columnCount = array_sum(array_map('count', $tableColumns));
    $stringCount = count($translatableStrings);

    echo "Generated {$outputFile}" . PHP_EOL;
    echo "Database: {$databaseName}" . PHP_EOL;
    echo "Tables scanned: {$tableCount}" . PHP_EOL;
    echo "Columns scanned: {$columnCount}" . PHP_EOL;
    echo "Unique strings: {$stringCount}" . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * @param mixed $tableOption
 * @return string[]
 */
function normalizeTableFilter($tableOption): array
{
    if ($tableOption === []) {
        return [];
    }

    $tables = is_array($tableOption) ? $tableOption : [$tableOption];
    $tables = array_map(static fn($table): string => trim((string)$table), $tables);
    $tables = array_values(array_filter($tables, static fn(string $table): bool => $table !== ''));

    foreach ($tables as $table) {
        if (!preg_match('/^r_[A-Za-z0-9_]+$/', $table)) {
            throw new InvalidArgumentException("Invalid table filter '{$table}'. Only r_* tables are allowed.");
        }
    }

    return array_values(array_unique($tables));
}

/**
 * @param array<string, string[]> $tablesToTranslate
 * @param string[] $requestedTables
 * @return array<string, string[]>
 */
function resolveTranslatableColumns(Zend_Db_Adapter_Abstract $db, string $databaseName, array $tablesToTranslate, array $requestedTables): array
{
    $tableColumns = $tablesToTranslate;

    if ($requestedTables !== []) {
        $tableColumns = array_intersect_key($tableColumns, array_flip($requestedTables));
    }

    if ($tableColumns === []) {
        throw new RuntimeException('No translatable tables matched the requested table filter.');
    }

    $tableColumns = validateMappedColumns($db, $databaseName, $tableColumns);

    if ($tableColumns === []) {
        throw new RuntimeException('None of the configured translation tables exist in the active database.');
    }

    ksort($tableColumns);

    return $tableColumns;
}

/**
 * @param array<string, string[]> $tableColumns
 * @return array<string, string[]>
 */
function validateMappedColumns(Zend_Db_Adapter_Abstract $db, string $databaseName, array $tableColumns): array
{
    $select = $db->select()
        ->from(
            ['c' => new Zend_Db_Expr('information_schema.COLUMNS')],
            ['TABLE_NAME', 'COLUMN_NAME']
        )
        ->where('c.TABLE_SCHEMA = ?', $databaseName)
        ->where('c.TABLE_NAME IN (?)', array_keys($tableColumns));

    $rows = $db->fetchAll($select);
    $availableColumns = [];

    foreach ($rows as $row) {
        $availableColumns[(string)$row['TABLE_NAME']][(string)$row['COLUMN_NAME']] = true;
    }

    foreach ($tableColumns as $tableName => $columns) {
        if (!isset($availableColumns[$tableName])) {
            fwrite(
                STDERR,
                "Warning: Skipping translation table '{$tableName}' because it was not found in database '{$databaseName}'." . PHP_EOL
            );
            unset($tableColumns[$tableName]);
            continue;
        }

        foreach ($columns as $columnName) {
            if (!isset($availableColumns[$tableName][$columnName])) {
                throw new RuntimeException("Configured translation column '{$tableName}.{$columnName}' was not found in database '{$databaseName}'.");
            }
        }
    }

    return $tableColumns;
}

/**
 * @param array<string, string[]> $tableColumns
 * @return array<string, list<string>>
 */
function fetchTranslatableStrings(Zend_Db_Adapter_Abstract $db, array $tableColumns): array
{
    $stringSources = [];

    foreach ($tableColumns as $tableName => $columns) {
        foreach ($columns as $columnName) {
            $sql = sprintf(
                'SELECT DISTINCT TRIM(%1$s) AS value FROM %2$s WHERE %1$s IS NOT NULL AND TRIM(%1$s) <> \'\' ORDER BY value',
                $db->quoteIdentifier($columnName),
                $db->quoteIdentifier($tableName)
            );

            $values = $db->fetchCol($sql);

            foreach ($values as $value) {
                $normalizedValue = normalizeTranslationString((string)$value);
                if ($normalizedValue === '') {
                    continue;
                }

                $source = "{$tableName}.{$columnName}";
                $stringSources[$normalizedValue] ??= [];
                $stringSources[$normalizedValue][$source] = $source;
            }
        }
    }

    ksort($stringSources, SORT_NATURAL | SORT_FLAG_CASE);

    return $stringSources;
}

function normalizeTranslationString(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim((string)$value);
}

/**
 * @param array<string, string[]> $tableColumns
 * @param array<string, list<string>> $translatableStrings
 */
function writeTranslationFile(string $outputFile, string $databaseName, array $tableColumns, array $translatableStrings): void
{
    $outputDirectory = dirname($outputFile);
    if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
        throw new RuntimeException("Failed to create output directory '{$outputDirectory}'.");
    }

    $lines = [
        '<?php',
        '',
        '// SYSTEM GENERATED FILE. DO NOT EDIT.',
        "// Generated by bin/generate-db-translation-strings.php from database: {$databaseName}",
        '// This file exists only so gettext/xgettext can discover DB-backed strings.',
        '',
    ];

    foreach ($tableColumns as $tableName => $columns) {
        $lines[] = sprintf('// %s: %s', $tableName, implode(', ', $columns));
    }

    if ($translatableStrings !== []) {
        $lines[] = '';
    }

    foreach ($translatableStrings as $string => $sources) {
        $lines[] = '// ' . implode(', ', $sources);
        $lines[] = '_translate(' . var_export($string, true) . ');';
    }

    $lines[] = '';

    $result = file_put_contents($outputFile, implode(PHP_EOL, $lines));
    if ($result === false) {
        throw new RuntimeException("Failed to write output file '{$outputFile}'.");
    }
}
