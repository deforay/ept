#!/usr/bin/env php
<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    exit(0);
}

$options = getopt('', ['output::', 'table::', 'prune', 'help']);

if (isset($options['help'])) {
    echo <<<TXT
Usage:
  php bin/generate-db-translation-strings.php [--output=/path/to/file.php] [--table=r_possibleresult] [--prune]

Options:
  --output    Output PHP file. Defaults to application/languages/db-translation-strings.php
  --table     Limit extraction to one or more r_* tables. Repeat the flag to pass multiple tables.
  --prune     Rebuild from this database alone, dropping strings the file already holds.
  --help      Show this help text.

By default the output file is merged, not replaced. Strings this database does
not have are kept, so running the command on one instance never deletes another
instance's lookup values from the shared catalog. Use --prune for a deliberate
cleanup when strings really have gone for good.

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
            'variants' => ['default', 'upper', 'lower'],
        ],
    ],
    'r_response_not_tested_reasons' => ['ntr_reason'],
    'r_response_vl_not_tested_reason' => ['vl_not_tested_reason'],
    'r_results' => [
        [
            'column' => 'result_name',
            'variants' => ['default', 'upper', 'lower'],
        ],
    ],
    'r_site_type' => ['site_type'],
    'scheme_list' => ['scheme_name'],
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
    $fromThisDatabase = count($translatableStrings);

    // Merge rather than replace. This file is never executed — it exists only so
    // xgettext can see DB-backed strings — so dropping a string gains nothing and
    // costs another instance its translation. Overwriting meant two instances
    // deleted each other's lookup values on every run.
    $carriedOver = 0;
    if (!isset($options['prune'])) {
        $existing = readExistingTranslationFile($outputFile);
        $carriedOver = count(array_diff_key($existing['strings'], $translatableStrings));
        $translatableStrings = mergeStringSources($existing['strings'], $translatableStrings);
        $tableHeadings = mergeTableHeadings($existing['tables'], describeTableColumns($tableColumns));
    } else {
        $tableHeadings = describeTableColumns($tableColumns);
    }

    writeTranslationFile($outputFile, $tableHeadings, $translatableStrings);

    $tableCount = count($tableColumns);
    $columnCount = array_sum(array_map('count', $tableColumns));
    $stringCount = count($translatableStrings);

    $io->definitionList(
        ['Output file' => $outputFile],
        ['Database' => $databaseName],
        ['Mode' => isset($options['prune']) ? 'prune (rebuild from this database)' : 'merge'],
        ['Tables scanned' => (string) $tableCount],
        ['Columns scanned' => (string) $columnCount],
        ['Strings in this database' => (string) $fromThisDatabase],
        ['Kept from other instances' => (string) $carriedOver],
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
    $tables = array_map(static fn ($table): string => trim((string) $table), $tables);
    $tables = array_values(array_filter($tables, static fn (string $table): bool => $table !== ''));

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
/**
 * Table/column heading lines, keyed by table name.
 *
 * @param array<string, array<int, array{column:string, variants:array<int, string>}>> $tableColumns
 * @return array<string, string>
 */
function describeTableColumns(array $tableColumns): array
{
    $headings = [];

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
        $headings[$tableName] = implode(', ', $columnDescriptions);
    }

    return $headings;
}

/**
 * Reads back a previously generated file so its strings survive a run against a
 * database that does not have them.
 *
 * Parsing uses PHP's own tokenizer rather than a regex: the values are written
 * with var_export(), so they can contain quotes and backslashes that a regex
 * would mis-split.
 *
 * A missing or unreadable file is not an error. It just means there is nothing
 * to carry over.
 *
 * @return array{strings: array<string, array<string, string>>, tables: array<string, string>}
 */
function readExistingTranslationFile(string $outputFile): array
{
    $empty = ['strings' => [], 'tables' => []];

    if (!is_file($outputFile) || !is_readable($outputFile)) {
        return $empty;
    }

    $contents = file_get_contents($outputFile);
    if ($contents === false || trim($contents) === '') {
        return $empty;
    }

    $strings = [];
    $tables = [];
    $pendingSources = [];

    $tokens = token_get_all($contents);
    $count = count($tokens);

    for ($index = 0; $index < $count; $index++) {
        $token = $tokens[$index];

        if (is_array($token) && $token[0] === T_COMMENT) {
            $comment = trim(ltrim(trim($token[1]), '/'));

            // "r_site_type: site_type" is a table heading. "r_site_type.site_type"
            // is the source list above one string. The separator tells them apart.
            if (preg_match('/^(r_[A-Za-z0-9_]+|scheme_list):\s*(.+)$/', $comment, $matches) === 1) {
                $tables[$matches[1]] = trim($matches[2]);
                continue;
            }

            $sources = array_filter(array_map('trim', explode(',', $comment)));
            $pendingSources = [];
            foreach ($sources as $source) {
                if (preg_match('/^[A-Za-z0-9_]+\.[A-Za-z0-9_]+$/', $source) === 1) {
                    $pendingSources[$source] = $source;
                }
            }
            continue;
        }

        if (!is_array($token) || $token[0] !== T_STRING || $token[1] !== '_') {
            continue;
        }

        $openIndex = nextMeaningfulTokenIndex($tokens, $index + 1);
        if ($openIndex === null || $tokens[$openIndex] !== '(') {
            continue;
        }

        $valueIndex = nextMeaningfulTokenIndex($tokens, $openIndex + 1);
        if ($valueIndex === null) {
            continue;
        }

        $valueToken = $tokens[$valueIndex];
        if (!is_array($valueToken) || $valueToken[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }

        $value = decodeSingleQuotedLiteral($valueToken[1]);
        if ($value === null || $value === '') {
            continue;
        }

        $strings[$value] ??= [];
        foreach ($pendingSources as $source) {
            $strings[$value][$source] = $source;
        }
        $pendingSources = [];
        $index = $valueIndex;
    }

    return ['strings' => $strings, 'tables' => $tables];
}

/**
 * @param array<int, mixed> $tokens
 */
function nextMeaningfulTokenIndex(array $tokens, int $start): ?int
{
    $count = count($tokens);

    for ($index = $start; $index < $count; $index++) {
        $token = $tokens[$index];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        return $index;
    }

    return null;
}

/**
 * Undoes var_export()'s single-quoted escaping. Returns null for anything that
 * is not a single-quoted literal, since only var_export() output is expected.
 */
function decodeSingleQuotedLiteral(string $literal): ?string
{
    if (strlen($literal) < 2 || $literal[0] !== "'" || substr($literal, -1) !== "'") {
        return null;
    }

    $inner = substr($literal, 1, -1);

    return str_replace(['\\\\', "\\'"], ['\\', "'"], $inner);
}

/**
 * @param array<string, array<string, string>> $existing
 * @param array<string, array<string, string>> $current
 * @return array<string, array<string, string>>
 */
function mergeStringSources(array $existing, array $current): array
{
    $merged = $existing;

    foreach ($current as $string => $sources) {
        $merged[$string] ??= [];
        foreach ($sources as $source) {
            $merged[$string][$source] = $source;
        }
    }

    foreach ($merged as $string => $sources) {
        ksort($sources, SORT_NATURAL | SORT_FLAG_CASE);
        $merged[$string] = $sources;
    }

    ksort($merged, SORT_NATURAL | SORT_FLAG_CASE);

    return $merged;
}

/**
 * @param array<string, string> $existing
 * @param array<string, string> $current
 * @return array<string, string>
 */
function mergeTableHeadings(array $existing, array $current): array
{
    $merged = $current + $existing;
    ksort($merged, SORT_NATURAL | SORT_FLAG_CASE);

    return $merged;
}

/**
 * @param array<string, string> $tableHeadings
 * @param array<string, array<string, string>> $translatableStrings
 */
function writeTranslationFile(string $outputFile, array $tableHeadings, array $translatableStrings): void
{
    $outputDirectory = dirname($outputFile);
    if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
        throw new RuntimeException("Failed to create output directory '{$outputDirectory}'.");
    }

    // The header deliberately does not name a database. This file is the union of
    // every instance the command has been run against, so naming one of them both
    // misleads and churns the diff each time a different instance regenerates it.
    $lines = [
        '<?php',
        '',
        '// SYSTEM GENERATED FILE. DO NOT EDIT.',
        '// Generated by bin/generate-db-translation-strings.php.',
        '// This file exists only so gettext/xgettext can discover DB-backed strings.',
        '// Regenerating merges: strings absent from the current database are kept.',
        '// Run with --prune to rebuild from the current database alone.',
        '',
    ];

    foreach ($tableHeadings as $tableName => $columnDescriptions) {
        $lines[] = sprintf('// %s: %s', $tableName, $columnDescriptions);
    }

    if ($translatableStrings !== []) {
        $lines[] = '';
    }

    foreach ($translatableStrings as $string => $sources) {
        if ($sources !== []) {
            $lines[] = '// ' . implode(', ', $sources);
        }
        $lines[] = '_(' . var_export($string, true) . ');';
    }

    $lines[] = '';

    $result = file_put_contents($outputFile, implode(PHP_EOL, $lines));
    if ($result === false) {
        throw new RuntimeException("Failed to write output file '{$outputFile}'.");
    }
}
