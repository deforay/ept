#!/usr/bin/env php
<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    exit(0);
}

$options = getopt('', ['locale::', 'table::', 'skip-ai', 'help']);

if (isset($options['help'])) {
    echo <<<TXT
Usage:
  php bin/refresh-translations.php [--locale=fr_FR] [--table=r_possibleresult] [--skip-ai]

Options:
  --locale   Refresh one or more locales. Repeat the flag to pass multiple locales.
  --table    Limit DB string regeneration to one or more r_* tables for debugging.
  --skip-ai  Skip optional AI pre-translation even if AI credentials are configured.
  --help     Show this help text.

TXT;
    exit(0);
}

require_once __DIR__ . '/../cli-bootstrap.php';

chdir(ROOT_PATH);

ini_set('memory_limit', '-1');
set_time_limit(0);

const GENERATED_DB_STRINGS_FILE = APPLICATION_PATH . '/languages/db-translation-strings.php';
const GETTEXT_KEYWORDS = [
    'translate',
    '_',
    '_translate',
    'setLabel',
    'setValue',
    'setMessage',
    'setLegend',
    '_refresh',
    'append',
    'prepend',
    'jsTranslate',
    'htmlTranslate',
    'safeTranslate',
];
const SOURCE_SCAN_ROOTS = [
    'application',
    'bin',
    'library',
    'run-once',
    'scheduled-jobs',
];
const EXTRA_SOURCE_FILES = [
    'public/js/main.js.php',
    'application/languages/db-translation-strings.php',
];

try {
    $requestedLocales = normalizeLocaleFilter($options['locale'] ?? []);
    $requestedTables = normalizeTableFilter($options['table'] ?? []);
    $shouldRunAi = isAiPretranslationConfigured() && !isset($options['skip-ai']);

    assertGettextBinariesAvailable();

    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
    if (!$db instanceof Zend_Db_Adapter_Abstract) {
        throw new RuntimeException('Default database adapter is not available. Check cli-bootstrap.php and DB configuration.');
    }

    $databaseName = (string)$db->fetchOne('SELECT DATABASE()');
    if ($databaseName === '') {
        throw new RuntimeException('Could not determine the active database for this instance.');
    }

    echo "Refreshing translations for database: {$databaseName}" . PHP_EOL;

    runDbStringGenerator($requestedTables);

    $sourceFiles = collectSourceFiles();
    if ($sourceFiles === []) {
        throw new RuntimeException('No translation source files were found.');
    }

    $potFile = createTemporaryPotFile();

    try {
        generatePotFile($potFile, $sourceFiles);

        $locales = discoverLocales($requestedLocales);
        if ($locales === []) {
            throw new RuntimeException('No locale PO files were found to refresh.');
        }

        foreach ($locales as $locale => $poFile) {
            echo "Merging locale {$locale}" . PHP_EOL;
            mergeCatalog($poFile, $potFile);

            if ($shouldRunAi) {
                runAiPretranslation($locale);
                continue;
            }

            compileCatalog($poFile, localePoToMoPath($poFile));
        }
    } finally {
        if (is_file($potFile)) {
            @unlink($potFile);
        }
    }

    echo 'Translation refresh completed successfully.' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Translation refresh failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * @param mixed $localeOption
 * @return string[]
 */
function normalizeLocaleFilter($localeOption): array
{
    if ($localeOption === []) {
        return [];
    }

    $locales = is_array($localeOption) ? $localeOption : [$localeOption];
    $locales = array_map(static fn($locale): string => trim((string)$locale), $locales);
    $locales = array_values(array_filter($locales, static fn(string $locale): bool => $locale !== ''));

    foreach ($locales as $locale) {
        if (!preg_match('/^[A-Za-z_@.]+$/', $locale)) {
            throw new InvalidArgumentException("Invalid locale '{$locale}'.");
        }
    }

    return array_values(array_unique($locales));
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

function assertGettextBinariesAvailable(): void
{
    foreach (['xgettext', 'msgmerge', 'msgfmt'] as $binary) {
        $path = trim((string)shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null'));
        if ($path === '') {
            throw new RuntimeException("Required gettext binary '{$binary}' was not found in PATH.");
        }
    }
}

/**
 * @param string[] $requestedTables
 */
function runDbStringGenerator(array $requestedTables): void
{
    $command = ['php', ROOT_PATH . '/bin/generate-db-translation-strings.php'];

    foreach ($requestedTables as $tableName) {
        $command[] = '--table=' . $tableName;
    }

    echo 'Regenerating DB-backed translation strings' . PHP_EOL;
    runCommand($command);

    if (!is_file(GENERATED_DB_STRINGS_FILE)) {
        throw new RuntimeException('DB translation string file was not generated.');
    }
}

/**
 * @return string[]
 */
function collectSourceFiles(): array
{
    $files = [];

    foreach (SOURCE_SCAN_ROOTS as $root) {
        $absoluteRoot = ROOT_PATH . DIRECTORY_SEPARATOR . $root;
        if (!is_dir($absoluteRoot)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absoluteRoot, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $extension = strtolower($fileInfo->getExtension());
            if (!in_array($extension, ['php', 'phtml'], true)) {
                continue;
            }

            $relativePath = normalizeRelativePath($fileInfo->getPathname());
            $files[$relativePath] = $relativePath;
        }
    }

    foreach (EXTRA_SOURCE_FILES as $extraFile) {
        $absolutePath = ROOT_PATH . DIRECTORY_SEPARATOR . $extraFile;
        if (is_file($absolutePath)) {
            $relativePath = normalizeRelativePath($absolutePath);
            $files[$relativePath] = $relativePath;
        }
    }

    ksort($files, SORT_NATURAL | SORT_FLAG_CASE);

    return array_values($files);
}

function normalizeRelativePath(string $absolutePath): string
{
    $relativePath = str_replace(ROOT_PATH . DIRECTORY_SEPARATOR, '', $absolutePath);
    return str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
}

function createTemporaryPotFile(): string
{
    $tempFile = tempnam(sys_get_temp_dir(), 'ept-i18n-');
    if ($tempFile === false) {
        throw new RuntimeException('Failed to allocate a temporary POT file.');
    }

    @unlink($tempFile);

    return $tempFile . '.pot';
}

/**
 * @param string[] $sourceFiles
 */
function generatePotFile(string $potFile, array $sourceFiles): void
{
    $fileList = tempnam(sys_get_temp_dir(), 'ept-i18n-files-');
    if ($fileList === false) {
        throw new RuntimeException('Failed to allocate a temporary source file list.');
    }

    try {
        $fileListContent = implode(PHP_EOL, $sourceFiles) . PHP_EOL;
        if (file_put_contents($fileList, $fileListContent) === false) {
            throw new RuntimeException('Failed to write temporary xgettext source file list.');
        }

        $command = [
            'xgettext',
            '--language=PHP',
            '--from-code=UTF-8',
            '--force-po',
            '--output=' . $potFile,
            '--package-name=ePT',
            '--msgid-bugs-address=support@deforay.com',
            '--files-from=' . $fileList,
        ];

        foreach (GETTEXT_KEYWORDS as $keyword) {
            $command[] = '--keyword=' . $keyword;
        }

        echo 'Generating source POT catalog' . PHP_EOL;
        runCommand($command);
    } finally {
        @unlink($fileList);
    }
}

/**
 * @param string[] $requestedLocales
 * @return array<string, string>
 */
function discoverLocales(array $requestedLocales): array
{
    $languageRoot = APPLICATION_PATH . '/languages';
    if (!is_dir($languageRoot)) {
        throw new RuntimeException('Language directory does not exist.');
    }

    $localeFiles = [];
    $iterator = new DirectoryIterator($languageRoot);

    /** @var DirectoryIterator $directory */
    foreach ($iterator as $directory) {
        if (!$directory->isDir() || $directory->isDot()) {
            continue;
        }

        $locale = $directory->getFilename();
        $poFile = $directory->getPathname() . DIRECTORY_SEPARATOR . $locale . '.po';

        if (!is_file($poFile)) {
            continue;
        }

        $localeFiles[$locale] = $poFile;
    }

    ksort($localeFiles, SORT_NATURAL | SORT_FLAG_CASE);

    if ($requestedLocales === []) {
        return $localeFiles;
    }

    $filteredLocales = array_intersect_key($localeFiles, array_flip($requestedLocales));
    $missingLocales = array_diff($requestedLocales, array_keys($filteredLocales));

    if ($missingLocales !== []) {
        throw new RuntimeException(
            'Requested locale PO file not found for: ' . implode(', ', $missingLocales)
        );
    }

    return $filteredLocales;
}

function mergeCatalog(string $poFile, string $potFile): void
{
    $command = [
        'msgmerge',
        '--update',
        '--backup=none',
        $poFile,
        $potFile,
    ];

    runCommand($command);
}

function isAiPretranslationConfigured(): bool
{
    return trim((string)getenv('EPT_AI_API_URL')) !== ''
        && trim((string)getenv('EPT_AI_API_KEY')) !== ''
        && trim((string)getenv('EPT_AI_MODEL')) !== '';
}

function runAiPretranslation(string $locale): void
{
    $command = ['php', ROOT_PATH . '/bin/ai-pretranslate.php', '--locale=' . $locale];
    echo "AI pre-translating locale {$locale}" . PHP_EOL;
    runCommand($command);
}

function compileCatalog(string $poFile, string $moFile): void
{
    $command = [
        'msgfmt',
        '--check-format',
        '--output-file=' . $moFile,
        $poFile,
    ];

    echo 'Compiling ' . basename($moFile) . PHP_EOL;
    runCommand($command);
}

function localePoToMoPath(string $poFile): string
{
    return preg_replace('/\.po$/', '.mo', $poFile) ?: $poFile . '.mo';
}

/**
 * @param string[] $command
 */
function runCommand(array $command): void
{
    $escapedCommand = implode(' ', array_map('escapeshellarg', $command));
    $output = [];
    $exitCode = 0;

    exec($escapedCommand . ' 2>&1', $output, $exitCode);

    if ($output !== []) {
        echo implode(PHP_EOL, $output) . PHP_EOL;
    }

    if ($exitCode !== 0) {
        throw new RuntimeException('Command failed: ' . implode(' ', $command));
    }
}
