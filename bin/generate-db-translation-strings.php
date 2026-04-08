#!/usr/bin/env php
<?php

declare(strict_types=1);

use Symfony\Component\Console\Style\SymfonyStyle;

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
require_once __DIR__ . '/console-helpers.php';

ini_set('memory_limit', '-1');
set_time_limit(0);

const DEFAULT_OUTPUT_FILE = APPLICATION_PATH . '/languages/db-translation-strings.php';

/**
 * Define the lookup tables/columns that should feed gettext.
 * Values can change at runtime, but this structure is expected to stay stable.
 *
 * Column entries support two forms:
 * - 'column_name'
 * - ['column' => 'column_name', 'variants' => ['default', 'upper', 'lower']]
 *
 * Supported variants:
 * - default: original normalized DB value
 * - lower: strtolower(value)
 * - upper: strtoupper(value)
 */
$tablesToTranslate = [
    'r_control' => ['control_name'],
    'r_covid19_corrective_actions' => ['corrective_action', 'description'],
    'r_dts_corrective_actions' => ['corrective_action', 'description'],
    'r_evaluation_comments' => ['comment'],
    'r_feedback_questions' => ['question_text'],
    'r_modes_of_receipt' => ['mode_name'],
    'r_network_tiers' => ['network_name'],
    'r_participant_affiliates' => ['affiliate'],
    'r_possibleresult' => [
        [
            'column' => 'response',
            'variants' => ['default', 'upper', 'lower']
        ]
    ],
    'r_response_not_tested_reasons' => ['ntr_reason'],
    'r_response_vl_not_tested_reason' => ['vl_not_tested_reason'],
    'r_results' => [
        [
            'column' => 'result_name',
            'variants' => ['default', 'upper', 'lower']
        ]
    ],
    'r_site_type' => ['site_type'],
];

try {
    $io = createCliStyle();
    $io->title('Generate DB Translation Strings');

    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
    if (!$db instanceof Zend_Db_Adapter_Abstract) {
        throw new RuntimeException('Default database adapter is not available. Check cli-bootstrap.php and DB configuration.');
    }

    $databaseName = (string) $db->fetchOne('SELECT DATABASE()');
    if ($databaseName === '') {
        throw new RuntimeException('Could not determine the active database for this instance.');
    }

    $outputFile = (string) ($options['output'] ?? DEFAULT_OUTPUT_FILE);
    $requestedTables = normalizeTableFilter($options['table'] ?? []);

    $tableColumns = resolveTranslatableColumns($db, $databaseName, $tablesToTranslate, $requestedTables);
    $translatableStrings = fetchTranslatableStrings($db, $tableColumns);

    writeTranslationFile($outputFile, $databaseName, $tableColumns, $translatableStrings);

    $tableCount = count($tableColumns);
    $columnCount = array_sum(array_map('count', $tableColumns));
    $stringCount = count($translatableStrings);

    $io->definitionList(
        ['Output file' => $outputFile],
        ['Database' => $databaseName],
        ['Tables scanned' => (string) $tableCount],
        ['Columns scanned' => (string) $columnCount],
        ['Unique strings' => (string) $stringCount]
    );
    $io->success('DB-backed translation strings generated successfully.');
} catch (Throwable $e) {
    $io ??= createCliStyle();
    $io->error('DB translation string generation failed: ' . $e->getMessage());
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
    $tables = array_map(static fn($table): string => trim((string) $table), $tables);
    $tables = array_values(array_filter($tables, static fn(string $table): bool => $table !== ''));

    foreach ($tables as $table) {
        if (!preg_match('/^r_[A-Za-z0-9_]+$/', $table)) {
            throw new InvalidArgumentException("Invalid table filter '{$table}'. Only r_* tables are allowed.");
        }
    }

    return array_values(array_unique($tables));
}

/**
 * @param array<string, array<int, string|array{column:string, variants?:array<int, string>}>> $tablesToTranslate
 * @param string[] $requestedTables
 * @return array<string, array<int, array{column:string, variants:array<int, string>}>>
 */
function resolveTranslatableColumns(Zend_Db_Adapter_Abstract $db, string $databaseName, array $tablesToTranslate, array $requestedTables): array
{
    $tableColumns = normalizeTableTranslationConfig($tablesToTranslate);

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
 * @param array<string, array<int, string|array{column:string, variants?:array<int, string>}>> $tableColumns
 * @return array<string, array<int, array{column:string, variants:array<int, string>}>>
 */
function normalizeTableTranslationConfig(array $tableColumns): array
{
    $normalizedConfig = [];

    foreach ($tableColumns as $tableName => $columns) {
        foreach ($columns as $columnConfig) {
            if (is_string($columnConfig)) {
                $normalizedConfig[$tableName][] = [
                    'column' => $columnConfig,
                    'variants' => ['default'],
                ];
                continue;
            }

            $columnName = trim((string) ($columnConfig['column'] ?? ''));
            if ($columnName === '') {
                throw new InvalidArgumentException("Translation config for table '{$tableName}' is missing a column name.");
            }

            $variants = $columnConfig['variants'] ?? ['default'];
            if (!is_array($variants) || $variants === []) {
                $variants = ['default'];
            }

            $normalizedVariants = [];
            foreach ($variants as $variant) {
                $variant = strtolower(trim((string) $variant));
                if (!in_array($variant, ['default', 'lower', 'upper'], true)) {
                    throw new InvalidArgumentException("Unsupported variant '{$variant}' configured for {$tableName}.{$columnName}.");
                }
                $normalizedVariants[$variant] = $variant;
            }

            if ($normalizedVariants === []) {
                $normalizedVariants['default'] = 'default';
            }

            $normalizedConfig[$tableName][] = [
                'column' => $columnName,
                'variants' => array_values($normalizedVariants),
            ];
        }
    }

    return $normalizedConfig;
}

/**
 * @param array<string, array<int, array{column:string, variants:array<int, string>}>> $tableColumns
 * @return array<string, array<int, array{column:string, variants:array<int, string>}>>
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
        $availableColumns[(string) $row['TABLE_NAME']][(string) $row['COLUMN_NAME']] = true;
    }

    foreach ($tableColumns as $tableName => $columns) {
        if (!isset($availableColumns[$tableName])) {
            // Missing tables are tolerated so the same script works across deployments with slightly different schemas.
            fwrite(
                STDERR,
                "Warning: Skipping translation table '{$tableName}' because it was not found in database '{$databaseName}'." . PHP_EOL
            );
            unset($tableColumns[$tableName]);
            continue;
        }

        foreach ($columns as $columnConfig) {
            $columnName = $columnConfig['column'];
            if (!isset($availableColumns[$tableName][$columnName])) {
                throw new RuntimeException("Configured translation column '{$tableName}.{$columnName}' was not found in database '{$databaseName}'.");
            }
        }
    }

    return $tableColumns;
}

/**
 * @param array<string, array<int, array{column:string, variants:array<int, string>}>> $tableColumns
 * @return array<string, list<string>>
 */
function fetchTranslatableStrings(Zend_Db_Adapter_Abstract $db, array $tableColumns): array
{
    $stringSources = [];

    foreach ($tableColumns as $tableName => $columns) {
        foreach ($columns as $columnConfig) {
            $columnName = $columnConfig['column'];
            $sql = sprintf(
                'SELECT DISTINCT TRIM(%1$s) AS value FROM %2$s WHERE %1$s IS NOT NULL AND TRIM(%1$s) <> \'\' ORDER BY value',
                $db->quoteIdentifier($columnName),
                $db->quoteIdentifier($tableName)
            );

            $values = $db->fetchCol($sql);

            foreach ($values as $value) {
                $normalizedValue = normalizeTranslationString((string) $value);
                if ($normalizedValue === '') {
                    continue;
                }

                $source = "{$tableName}.{$columnName}";
                foreach (buildStringVariants($normalizedValue, $columnConfig['variants']) as $variantValue) {
                    $stringSources[$variantValue] ??= [];
                    $stringSources[$variantValue][$source] = $source;
                }
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
    return trim((string) $value);
}

/**
 * @param array<int, string> $variants
 * @return array<int, string>
 */
function buildStringVariants(string $value, array $variants): array
{
    $variantValues = [];

    foreach ($variants as $variant) {
        $variantValue = match ($variant) {
            'lower' => mb_strtolower($value, 'UTF-8'),
            'upper' => mb_strtoupper($value, 'UTF-8'),
            default => $value,
        };

        $variantValue = normalizeTranslationString($variantValue);
        if ($variantValue === '') {
            continue;
        }

        $variantValues[$variantValue] = $variantValue;
    }

    return array_values($variantValues);
}

/**
 * @param array<string, array<int, array{column:string, variants:array<int, string>}>> $tableColumns
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
        $columnDescriptions = array_map(
            static function (array $columnConfig): string {
                $variants = $columnConfig['variants'] === ['default']
                    ? ''
                    : ' [' . implode(', ', $columnConfig['variants']) . ']';
                return $columnConfig['column'] . $variants;
            },
            $columns
        );
        $lines[] = sprintf('// %s: %s', $tableName, implode(', ', $columnDescriptions));
    }

    if ($translatableStrings !== []) {
        $lines[] = '';
    }

    foreach ($translatableStrings as $string => $sources) {
        $lines[] = '// ' . implode(', ', $sources);
        $lines[] = '_(' . var_export($string, true) . ');';
    }

    $lines[] = '';

    $result = file_put_contents($outputFile, implode(PHP_EOL, $lines));
    if ($result === false) {
        throw new RuntimeException("Failed to write output file '{$outputFile}'.");
    }
}
