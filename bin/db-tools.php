#!/usr/bin/env php
<?php

use Ifsnop\Mysqldump\Mysqldump;

// bin/db-tools.php - Database management CLI tool for ePT

if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . '/../cli-bootstrap.php';

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

$db = Zend_Db::factory($conf->resources->db);
Zend_Db_Table::setDefaultAdapter($db);

const DEFAULT_OPERATION = 'backup';

$backupFolder = APPLICATION_PATH . '/../backups/db';
if (!is_dir($backupFolder)) {
    mkdir($backupFolder, 0755, true);
}
$backupFolder = realpath($backupFolder) ?: $backupFolder;
$backupFolder = rtrim($backupFolder, DIRECTORY_SEPARATOR);

$arguments = $_SERVER['argv'] ?? [];
array_shift($arguments); // remove script name
$command = strtolower($arguments[0] ?? DEFAULT_OPERATION);
$commandArgs = array_slice($arguments, 1);

$mainConfig = [
    'host' => $conf->resources->db->params->host,
    'username' => $conf->resources->db->params->username,
    'password' => $conf->resources->db->params->password ?? '',
    'db' => $conf->resources->db->params->dbname,
    'port' => $conf->resources->db->params->port ?? '3306',
    'charset' => $conf->resources->db->params->charset ?? 'utf8mb4',
];

validateConfiguration($mainConfig);

try {
    switch ($command) {
        case 'backup':
            handleBackup($backupFolder, $mainConfig, $commandArgs);
            exit(0);
        case 'export':
            handleExport($backupFolder, $mainConfig, $commandArgs);
            exit(0);
        case 'import':
            handleImport($backupFolder, $mainConfig, $commandArgs);
            exit(0);
        case 'list':
            handleList($backupFolder);
            exit(0);
        case 'restore':
            handleRestore($backupFolder, $mainConfig, $commandArgs[0] ?? null);
            exit(0);
        case 'mysqlcheck':
            handleMysqlCheck($mainConfig, $commandArgs);
            exit(0);
        case 'purge-binlogs':
        case 'purge-binlog':
            handlePurgeBinlogs($mainConfig, $commandArgs);
            exit(0);
        case 'verify':
            handleVerify($backupFolder, $commandArgs);
            exit(0);
        case 'clean':
            handleClean($backupFolder, $commandArgs);
            exit(0);
        case 'size':
            handleSize($mainConfig, $commandArgs);
            exit(0);
        case 'config-test':
            handleConfigTest($backupFolder, $mainConfig, $commandArgs);
            exit(0);
        case 'maintain':
            handleMaintain($mainConfig, $commandArgs);
            exit(0);
        case 'collation':
            $cmd = sprintf('%s %s/change-db-collation.php', PHP_BINARY, BIN_PATH);
            passthru($cmd);
            break;
        case 'help':
        case '--help':
        case '-h':
            printUsage();
            exit(0);
        default:
            echo "Unknown command: {$command}\n\n";
            printUsage();
            exit(1);
    }
} catch (Throwable $e) {
    handleUserFriendlyError($e);
    exit(1);
}

function printUsage()
{
    $script = basename(__FILE__);
    echo <<<USAGE
Usage: php bin/{$script} [command] [options]

Commands:
    backup                  Create encrypted backup of database
    export [file]           Export database as plain SQL file
                            file: optional output filename
    import [file]           Import SQL file to database (supports .sql, .sql.gz, .zip, and .sql.zip)
                            file: path to file (optional - shows selection if omitted)
    list                    List available backups and SQL files
    restore [file]          Restore encrypted backup; shows selection if no file specified
    verify [file]           Verify backup integrity without restoring
    clean <--keep=N | --days=N>
                            Delete old backups (keep N recent OR keep newer than N days)
    size                    Show database size and table breakdown
    config-test             Test database configuration and connectivity
    mysqlcheck              Run database maintenance
    purge-binlogs [--days=N]
                            Clean up binary logs older than N days (default 7)
    maintain [--days=N]     Run full maintenance (mysqlcheck + purge binlogs)
    help                    Show this help message

Examples:
    php bin/{$script} backup
    php bin/{$script} verify
    php bin/{$script} clean --keep=7
    php bin/{$script} clean --days=30
    php bin/{$script} size
    php bin/{$script} config-test
    php bin/{$script} export ept_backup.sql
    php bin/{$script} import
    php bin/{$script} restore
    php bin/{$script} maintain

Note: For security, all file operations are restricted to the backups directory.
USAGE;
}

function validateConfiguration(array $mainConfig)
{
    $required = ['host', 'username', 'db'];

    foreach ($required as $field) {
        if (empty($mainConfig[$field])) {
            throw new Exception("Database configuration missing: {$field}");
        }
    }
}

function verifyBackupIntegrity(string $zipPath)
{
    $zip = new ZipArchive();
    $result = $zip->open($zipPath, ZipArchive::CHECKCONS);

    if ($result !== true) {
        return false;
    }

    $zip->close();
    return true;
}

// Command Handlers

function handleBackup(string $backupFolder, array $mainConfig, array $args)
{
    echo "Creating database backup...\n";
    $zip = createBackupArchive('ept', $mainConfig, $backupFolder);
    echo '  Created: ' . basename($zip) . PHP_EOL;
}

function handleExport(string $backupFolder, array $mainConfig, array $args)
{
    $outputFile = $args[0] ?? null;

    if (!$outputFile) {
        $timestamp = date('dmY-His');
        $outputFile = $backupFolder . DIRECTORY_SEPARATOR . "ept-export-{$timestamp}.sql";
    } else {
        // If relative path, make it relative to backup folder
        if (!str_contains($outputFile, DIRECTORY_SEPARATOR)) {
            $outputFile = $backupFolder . DIRECTORY_SEPARATOR . $outputFile;
        }

        // Security: Validate output path is within backup folder
        if (!validateSecureFilePath(dirname($outputFile), $backupFolder)) {
            throw new Exception('Output file must be within the backups directory for security reasons.');
        }
    }

    echo "Exporting database to " . basename($outputFile) . "...\n";

    $dsn = sprintf('mysql:host=%s;dbname=%s', $mainConfig['host'], $mainConfig['db']);
    if (!empty($mainConfig['port'])) {
        $dsn .= ';port=' . $mainConfig['port'];
    }

    try {
        $dump = new Ifsnop\Mysqldump\Mysqldump($dsn, $mainConfig['username'], $mainConfig['password'] ?? '');
        $dump->start($outputFile);
        echo "Export completed: " . $outputFile . PHP_EOL;
    } catch (Exception $e) {
        throw new Exception('Database export failed: ' . $e->getMessage());
    }
}

function handleImport(string $backupFolder, array $mainConfig, array $args)
{
    $sourceFile = $args[0] ?? null;

    // If no file specified, show interactive selection
    if ($sourceFile === null) {
        $sqlPath = promptForImportFileSelection($backupFolder);
        if ($sqlPath === null) {
            echo 'Import cancelled.' . PHP_EOL;
            return;
        }
    } else {
        // Resolve file path with security validation
        $sqlPath = resolveImportFileSecure($sourceFile, $backupFolder);
        if (!$sqlPath) {
            throw new Exception("Import file not found or access denied: {$sourceFile}");
        }
    }

    $fileExtension = strtolower(pathinfo($sqlPath, PATHINFO_EXTENSION));
    $isZipFile = in_array($fileExtension, ['zip', 'gz'], true);

    echo "Creating safety backup of current database before import...\n";
    $note = 'pre-import-' . date('His');
    $preImportZip = createBackupArchive('ept-backup', $mainConfig, $backupFolder, $note);
    echo '  Created: ' . basename($preImportZip) . PHP_EOL;

    if ($fileExtension === 'gz') {
        echo "Processing gzipped SQL file...\n";
        $extractedSqlPath = extractGzipFile($sqlPath, $backupFolder);
        TempFileRegistry::register($extractedSqlPath);
    } elseif ($isZipFile) {
        $isPasswordProtected = isZipPasswordProtected($sqlPath);

        if ($isPasswordProtected) {
            echo "Processing password-protected archive...\n";
            $extractedSqlPath = extractSqlFromBackupWithFallback($sqlPath, $mainConfig['password'] ?? '', $backupFolder);
            TempFileRegistry::register($extractedSqlPath);
        } else {
            echo "Processing unprotected archive...\n";
            $extractedSqlPath = extractUnprotectedZip($sqlPath, $backupFolder);
            TempFileRegistry::register($extractedSqlPath);
        }
    } else {
        echo "Processing SQL file...\n";
        $extractedSqlPath = $sqlPath;
    }

    echo "Resetting database...\n";
    recreateDatabase($mainConfig);

    echo "Importing data to database...\n";
    importSqlDump($mainConfig, $extractedSqlPath);

    // Cleanup is handled by TempFileRegistry shutdown function
    echo "Import completed successfully.\n";
}

function handleList(string $backupFolder)
{
    $backups = getSortedBackups($backupFolder);
    $sqlFiles = getSortedSqlFiles($backupFolder);

    if (empty($backups) && empty($sqlFiles)) {
        echo 'No backups or SQL files found in ' . $backupFolder . PHP_EOL;
        return;
    }

    if (!empty($backups)) {
        echo "Encrypted backups:\n";
        showBackupsWithIndex($backups);
        echo "\n";
    }

    if (!empty($sqlFiles)) {
        echo "SQL files:\n";
        showBackupsWithIndex($sqlFiles);
    }
}

function handleRestore(string $backupFolder, array $mainConfig, ?string $requestedFile)
{
    $backups = getSortedBackups($backupFolder);
    if (empty($backups)) {
        echo 'No encrypted backups found to restore.' . PHP_EOL;
        return;
    }

    $selectedPath = null;

    if ($requestedFile) {
        $selectedPath = resolveBackupFileSecure($requestedFile, $backupFolder);
        if (!$selectedPath) {
            throw new Exception("Backup file not found or access denied: {$requestedFile}");
        }
    } else {
        showBackupsWithIndex($backups);
        $selectedPath = promptForBackupSelection($backups);
        if ($selectedPath === null) {
            echo 'Restore cancelled.' . PHP_EOL;
            return;
        }
    }

    // Integrity check
    if (!verifyBackupIntegrity($selectedPath)) {
        echo "Warning: Backup file may be corrupted. Continue anyway? (y/N): ";
        $input = trim(fgets(STDIN) ?: '');
        if (strtolower($input) !== 'y') {
            echo 'Restore cancelled due to integrity check failure.' . PHP_EOL;
            return;
        }
    }

    $basename = basename($selectedPath);

    echo 'Creating safety backup of current database before restore...' . PHP_EOL;
    $note = 'restoreof-' . slugifyForFilename($basename, 32);
    $preRestoreZip = createBackupArchive('pre-restore-ept', $mainConfig, $backupFolder, $note);
    echo '  Created: ' . basename($preRestoreZip) . PHP_EOL;

    echo 'Decrypting and extracting backup...' . PHP_EOL;
    $sqlPath = extractSqlFromBackupWithFallback($selectedPath, $mainConfig['password'] ?? '', $backupFolder);
    TempFileRegistry::register($sqlPath);

    echo 'Resetting database...' . PHP_EOL;
    recreateDatabase($mainConfig);

    echo 'Restoring database from ' . $basename . '...' . PHP_EOL;
    importSqlDump($mainConfig, $sqlPath);

    echo 'Restore completed successfully.' . PHP_EOL;
}

function handleMysqlCheck(array $mainConfig, array $args)
{
    echo 'Running database maintenance...' . PHP_EOL;
    $output = runMysqlCheckCommand($mainConfig);
    if ($output !== '') {
        foreach (explode("\n", $output) as $line) {
            if ($line !== '') {
                echo '  ' . $line . PHP_EOL;
            }
        }
    }
    echo '  Maintenance completed' . PHP_EOL;
}

function handlePurgeBinlogs(array $mainConfig, array $args)
{
    $days = extractDaysOption($args, 7);

    $sql = sprintf('PURGE BINARY LOGS BEFORE DATE(NOW() - INTERVAL %d DAY);', $days);

    echo sprintf('Purging binary logs older than %d day(s)...', $days) . PHP_EOL;
    $result = runMysqlQuery($mainConfig, $sql);
    if ($result !== '') {
        foreach (explode("\n", $result) as $line) {
            if ($line !== '') {
                echo '  ' . $line . PHP_EOL;
            }
        }
    }
    echo '  Log cleanup completed' . PHP_EOL;
}

/**
 * Interactive file selection for import
 */
function promptForImportFileSelection(string $backupFolder)
{
    $backups = getSortedBackups($backupFolder);
    $sqlFiles = getSortedSqlFiles($backupFolder);

    $allFiles = array_merge($backups, $sqlFiles);

    if (empty($allFiles)) {
        echo 'No import files found in ' . $backupFolder . PHP_EOL;
        return null;
    }

    echo "Available files for import:\n";
    showBackupsWithIndex($allFiles);

    $count = count($allFiles);
    while (true) {
        $prompt = sprintf('Select file [1-%d] (or press Enter to cancel): ', $count);
        $input = function_exists('readline') ? readline($prompt) : null;
        if ($input === null) {
            echo $prompt;
            $input = fgets(STDIN) ?: '';
        }
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (!ctype_digit($input)) {
            echo 'Please enter a number.' . PHP_EOL;
            continue;
        }

        $index = (int) $input;
        if ($index < 1 || $index > $count) {
            echo 'Selection out of range.' . PHP_EOL;
            continue;
        }

        return $allFiles[$index - 1]['path'];
    }
}

// Archive and Password Handling Functions

function isZipPasswordProtected(string $zipPath)
{
    $zip = new ZipArchive();
    $status = $zip->open($zipPath);

    if ($status !== true) {
        return true;
    }

    $isProtected = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if ($stat !== false) {
            if (isset($stat['encryption_method']) && $stat['encryption_method'] !== 0) {
                $isProtected = true;
                break;
            }
        }
    }

    $zip->close();
    return $isProtected;
}

function extractUnprotectedZip(string $zipPath, string $backupFolder)
{
    $zip = new ZipArchive();
    $status = $zip->open($zipPath);

    if ($status !== true) {
        throw new Exception(sprintf('Failed to open archive. (Status code: %s)', $status));
    }

    if ($zip->numFiles < 1) {
        $zip->close();
        throw new Exception('Archive is empty.');
    }

    $sqlEntryName = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name !== false && str_ends_with(strtolower($name), '.sql')) {
            $sqlEntryName = $name;
            break;
        }
    }

    if ($sqlEntryName === null) {
        $zip->close();
        throw new Exception('No SQL file found in archive.');
    }

    $tempDir = $backupFolder . DIRECTORY_SEPARATOR . '.tmp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    $destination = $tempDir . DIRECTORY_SEPARATOR . basename($sqlEntryName);
    if (is_file($destination) && !unlink($destination)) {
        $zip->close();
        throw new Exception('Unable to clear previous temporary file.');
    }

    if (!$zip->extractTo($tempDir, [$sqlEntryName])) {
        $zip->close();
        throw new Exception('Failed to extract archive.');
    }

    $zip->close();

    $sqlPath = $tempDir . DIRECTORY_SEPARATOR . basename($sqlEntryName);
    if (!is_file($sqlPath)) {
        throw new Exception('Extracted SQL file not found.');
    }

    return $sqlPath;
}

function extractSqlFromBackupWithFallback(string $zipPath, string $dbPassword, string $backupFolder)
{
    try {
        return extractSqlFromBackup($zipPath, $dbPassword, $backupFolder);
    } catch (Exception $e) {
        if (str_contains(strtolower($e->getMessage()), 'password')) {
            echo "  Built-in password mechanism failed.\n";

            $userPassword = promptForPassword();
            if ($userPassword !== null) {
                echo "  Trying with user-provided password...\n";
                return extractSqlFromBackupWithPassword($zipPath, $userPassword, $backupFolder);
            } else {
                echo "  No password provided.\n";
            }
        }

        throw $e;
    }
}

function extractSqlFromBackupWithPassword(string $zipPath, string $password, string $backupFolder)
{
    $zip = new ZipArchive();
    $status = $zip->open($zipPath);
    if ($status !== true) {
        throw new Exception(sprintf('Failed to open backup archive. (Status code: %s)', $status));
    }

    if (!$zip->setPassword($password)) {
        $zip->close();
        throw new Exception('Failed to set password for archive. Password may be incorrect.');
    }

    if ($zip->numFiles < 1) {
        $zip->close();
        throw new Exception('Backup archive is empty.');
    }

    $sqlEntryName = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name !== false && str_ends_with(strtolower($name), '.sql')) {
            $sqlEntryName = $name;
            break;
        }
    }

    if ($sqlEntryName === null) {
        $zip->close();
        throw new Exception('No SQL file found in backup archive.');
    }

    $tempDir = $backupFolder . DIRECTORY_SEPARATOR . '.tmp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    $destination = $tempDir . DIRECTORY_SEPARATOR . basename($sqlEntryName);
    if (is_file($destination) && !unlink($destination)) {
        $zip->close();
        throw new Exception('Unable to clear previous temporary file.');
    }

    if (!$zip->extractTo($tempDir, [$sqlEntryName])) {
        $zip->close();
        throw new Exception('Failed to extract backup archive. Password may be incorrect.');
    }

    $zip->close();

    $sqlPath = $tempDir . DIRECTORY_SEPARATOR . basename($sqlEntryName);
    if (!is_file($sqlPath)) {
        throw new Exception('Extracted SQL file not found.');
    }

    return $sqlPath;
}

function promptForPassword(): ?string
{
    echo "Please enter the archive password: ";

    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $password = null;

        exec('stty -echo 2>/dev/null', $output, $returnCode);
        $sttyWorked = ($returnCode === 0);

        $handle = fopen('php://stdin', 'r');
        if ($handle !== false) {
            $password = fgets($handle);
            fclose($handle);
        }

        if ($sttyWorked) {
            exec('stty echo 2>/dev/null');
        }

        echo "\n";

        if ($password === false || trim($password) === '') {
            return null;
        }

        return trim($password);
    } else {
        echo "(Note: Password will be visible as you type)\n";
        echo "Password: ";

        $handle = fopen('php://stdin', 'r');
        if ($handle === false) {
            return null;
        }

        $password = fgets($handle);
        fclose($handle);

        if ($password === false || trim($password) === '') {
            return null;
        }

        return trim($password);
    }
}

function extractSqlFromBackup(string $zipPath, string $dbPassword, string $backupFolder)
{
    $zip = new ZipArchive();
    $status = $zip->open($zipPath);
    if ($status !== true) {
        throw new Exception(sprintf('Failed to open backup archive. (Status code: %s)', $status));
    }

    $token = extractRandomTokenFromBackup($zipPath);
    $zipPassword = $dbPassword . $token;

    if (!$zip->setPassword($zipPassword)) {
        $zip->close();
        throw new Exception('Failed to set password for archive.');
    }

    if ($zip->numFiles < 1) {
        $zip->close();
        throw new Exception('Backup archive is empty.');
    }

    $entryName = $zip->getNameIndex(0);
    if ($entryName === false) {
        $zip->close();
        throw new Exception('Failed to read backup archive contents.');
    }

    $tempDir = $backupFolder . DIRECTORY_SEPARATOR . '.tmp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    $destination = $tempDir . DIRECTORY_SEPARATOR . basename($entryName);
    if (is_file($destination) && !unlink($destination)) {
        $zip->close();
        throw new Exception('Unable to clear previous temporary file.');
    }

    if (!$zip->extractTo($tempDir, [$entryName])) {
        $zip->close();
        throw new Exception('Failed to extract backup archive.');
    }

    $zip->close();

    $sqlPath = $tempDir . DIRECTORY_SEPARATOR . basename($entryName);
    if (!is_file($sqlPath)) {
        throw new Exception('Extracted SQL file not found.');
    }

    return $sqlPath;
}

function extractRandomTokenFromBackup(string $zipPath)
{
    $name = basename($zipPath);
    if (str_ends_with($name, '.zip')) {
        $name = substr($name, 0, -4);
    }
    if (str_ends_with($name, '.sql')) {
        $name = substr($name, 0, -4);
    }

    $parts = explode('-', $name);
    $token = $parts[count($parts) - 1] ?? '';

    if ($token === '') {
        throw new Exception('Unable to derive password token from backup filename.');
    }

    return $token;
}

function createBackupArchive(string $prefix, array $config, string $backupFolder, ?string $note = null)
{
    $randomString = Pt_Commons_MiscUtility::generateRandomString(12);
    $parts = [$prefix, date('dmYHis')];
    if ($note) {
        $parts[] = $note;
    }
    $parts[] = $randomString;

    $baseName = implode('-', array_filter($parts));
    $sqlFileName = $backupFolder . DIRECTORY_SEPARATOR . $baseName . '.sql';

    $dsn = sprintf('mysql:host=%s;dbname=%s', $config['host'], $config['db']);
    if (!empty($config['port'])) {
        $dsn .= ';port=' . $config['port'];
    }

    try {
        $dump = new Mysqldump($dsn, $config['username'], $config['password'] ?? '');
        $dump->start($sqlFileName);
    } catch (Exception $e) {
        throw new Exception("Failed to create database dump for {$config['db']}: " . $e->getMessage());
    }

    $zipPath = $sqlFileName . '.zip';
    $zip = new ZipArchive();
    $zipStatus = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($zipStatus !== true) {
        @unlink($sqlFileName);
        throw new Exception(sprintf('Failed to create zip archive. (Status code: %s)', $zipStatus));
    }

    $zipPassword = ($config['password'] ?? '') . $randomString;
    if (!$zip->setPassword($zipPassword)) {
        $zip->close();
        @unlink($sqlFileName);
        throw new Exception('Failed to set password for backup archive');
    }

    $baseNameSql = basename($sqlFileName);
    if (!$zip->addFile($sqlFileName, $baseNameSql)) {
        $zip->close();
        @unlink($sqlFileName);
        throw new Exception(sprintf('Failed to add SQL file to archive: %s', $sqlFileName));
    }

    if (!$zip->setEncryptionName($baseNameSql, ZipArchive::EM_AES_256)) {
        $zip->close();
        @unlink($sqlFileName);
        throw new Exception(sprintf('Failed to encrypt file in archive: %s', $baseNameSql));
    }

    $zip->close();
    @unlink($sqlFileName);

    return $zipPath;
}

// File Operations and Listing Functions
function extractGzipFile(string $gzPath, string $backupFolder)
{
    if (!function_exists('gzopen')) {
        throw new Exception('PHP gzip extension is not installed. Cannot process .gz files.');
    }

    $tempDir = $backupFolder . DIRECTORY_SEPARATOR . '.tmp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    $outputPath = $tempDir . DIRECTORY_SEPARATOR . basename($gzPath, '.gz');

    $gz = gzopen($gzPath, 'rb');
    if (!$gz) {
        throw new Exception('Could not open gzip file.');
    }

    $output = fopen($outputPath, 'wb');
    if (!$output) {
        gzclose($gz);
        throw new Exception('Could not create temporary file.');
    }

    while (!gzeof($gz)) {
        $data = gzread($gz, 8192);
        if ($data === false) {
            gzclose($gz);
            fclose($output);
            @unlink($outputPath);
            throw new Exception('Error reading gzip file.');
        }
        fwrite($output, $data);
    }

    gzclose($gz);
    fclose($output);

    return $outputPath;
}

function getSortedBackups(string $backupFolder): array
{
    $pattern = $backupFolder . DIRECTORY_SEPARATOR . '*.sql.zip';
    $files = glob($pattern) ?: [];

    $backups = [];
    foreach ($files as $file) {
        $backups[] = [
            'path' => $file,
            'basename' => basename($file),
            'mtime' => @filemtime($file) ?: 0,
            'size' => @filesize($file) ?: 0,
        ];
    }

    usort($backups, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);

    return $backups;
}

function getSortedSqlFiles(string $backupFolder): array
{
    $patterns = [
        $backupFolder . DIRECTORY_SEPARATOR . '*.sql',
        $backupFolder . DIRECTORY_SEPARATOR . '*.sql.gz',
    ];

    $files = [];
    foreach ($patterns as $pattern) {
        $matches = glob($pattern) ?: [];
        $files = array_merge($files, $matches);
    }

    $sqlFiles = [];
    foreach ($files as $file) {
        $sqlFiles[] = [
            'path' => $file,
            'basename' => basename($file),
            'mtime' => @filemtime($file) ?: 0,
            'size' => @filesize($file) ?: 0,
        ];
    }

    usort($sqlFiles, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);

    return $sqlFiles;
}

function showBackupsWithIndex(array $backups)
{
    foreach ($backups as $index => $backup) {
        $position = $index + 1;
        $timestamp = date('Y-m-d H:i:s', $backup['mtime']);
        $size = formatFileSize($backup['size']);
        echo sprintf('[%d] %s  %s  %s', $position, $backup['basename'], $timestamp, $size) . PHP_EOL;
    }
}

function promptForBackupSelection(array $backups): ?string
{
    $count = count($backups);
    while (true) {
        $prompt = sprintf('Select backup [1-%d] (or press Enter to cancel): ', $count);
        $input = function_exists('readline') ? readline($prompt) : null;
        if ($input === null) {
            echo $prompt;
            $input = fgets(STDIN) ?: '';
        }
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (!ctype_digit($input)) {
            echo 'Please enter a numeric value.' . PHP_EOL;
            continue;
        }

        $index = (int) $input;
        if ($index < 1 || $index > $count) {
            echo 'Selection out of range.' . PHP_EOL;
            continue;
        }

        return $backups[$index - 1]['path'];
    }
}

// MySQL Operations

function executeMysqlCommand(array $config, array $baseCommand, ?string $inputData = null, ?string $sql = null)
{
    $command = $baseCommand;
    $command[] = '--host=' . $config['host'];
    if (!empty($config['port'])) {
        $command[] = '--port=' . $config['port'];
    }
    $command[] = '--user=' . $config['username'];
    $charset = $config['charset'] ?? 'utf8mb4';
    $command[] = '--default-character-set=' . $charset;

    if ($sql !== null) {
        $command[] = '--batch';
        $command[] = '--raw';
        $command[] = '--silent';
        $command[] = '--execute=' . $sql;
    }

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $env = buildProcessEnv([
        'MYSQL_PWD' => $config['password'] ?? '',
    ]);

    $process = proc_open($command, $descriptorSpec, $pipes, null, $env);
    if (!is_resource($process)) {
        throw new Exception('Could not connect to the database. Please check your configuration and network connection.');
    }

    if ($inputData !== null) {
        if (is_file($inputData)) {
            $source = fopen($inputData, 'rb');
            if (!$source) {
                fclose($pipes[0]);
                proc_close($process);
                throw new Exception('Could not read the SQL file. Please check file permissions.');
            }

            $fileSize = filesize($inputData);
            $bytesRead = 0;
            $lastProgress = 0;

            while (!feof($source)) {
                $chunk = fread($source, 8192);
                if ($chunk === false) break;

                fwrite($pipes[0], $chunk);
                $bytesRead += strlen($chunk);

                if ($fileSize > 1048576 && $fileSize > 0) {
                    $progress = intval(($bytesRead / $fileSize) * 100);
                    if ($progress >= $lastProgress + 10) {
                        echo "  Progress: {$progress}%\n";
                        $lastProgress = $progress;
                    }
                }
            }
            fclose($source);
        } else {
            fwrite($pipes[0], $inputData);
        }
    }

    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]) ?: '';
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        $errorMessage = trim($stderr) !== '' ? trim($stderr) : trim($stdout);
        throw new Exception("Database operation failed: {$errorMessage}");
    }

    return trim($stdout);
}

function importSqlDump(array $config, string $sqlFilePath)
{
    if (!commandExists('mysql')) {
        throw new Exception('MySQL tools are not installed or not in your system PATH. Please install MySQL client tools.');
    }

    if (!validateSecureFilePath($sqlFilePath, dirname($sqlFilePath))) {
        throw new Exception('Invalid file path provided for security reasons.');
    }

    try {
        executeMysqlCommand($config, ['mysql', $config['db']], $sqlFilePath);
    } catch (Exception $e) {
        throw new Exception('Database import failed: ' . $e->getMessage());
    }
}

function runMysqlCheckCommand(array $config)
{
    if (!commandExists('mysqlcheck')) {
        throw new Exception('MySQL maintenance tools are not installed. Please install MySQL client tools.');
    }

    // Run a single action per invocation, in this order
    $steps = [
        ['label' => 'REPAIR (auto)', 'args' => ['--auto-repair']], // no-op on InnoDB, safe on MyISAM
        ['label' => 'OPTIMIZE',      'args' => ['--optimize']],
        ['label' => 'ANALYZE',       'args' => ['--analyze']],
    ];

    $combined = [];

    foreach ($steps as $step) {
        $command = ['mysqlcheck'];

        // Connection flags first
        $command[] = '--host=' . $config['host'];
        if (!empty($config['port'])) {
            $command[] = '--port=' . $config['port'];
        }
        $command[] = '--user=' . $config['username'];
        $charset = $config['charset'] ?? 'utf8mb4';
        $command[] = '--default-character-set=' . $charset;

        // Action flag (only one per run)
        foreach ($step['args'] as $a) {
            $command[] = $a;
        }

        // Database last (positional)
        $command[] = $config['db'];

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = buildProcessEnv([
            'MYSQL_PWD' => $config['password'] ?? '',
        ]);

        $proc = proc_open($command, $descriptorSpec, $pipes, null, $env);
        if (!is_resource($proc)) {
            throw new Exception('Could not run database maintenance. Please verify MySQL tools are installed.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);

        $exit = proc_close($proc);
        if ($exit !== 0) {
            $msg = trim($stderr) !== '' ? trim($stderr) : trim($stdout);
            throw new Exception("Database maintenance failed during {$step['label']}: {$msg}");
        }

        $out = trim($stdout);
        $combined[] = $out !== '' ? "[{$step['label']}]\n{$out}" : "[{$step['label']}] OK";
    }

    return implode("\n", $combined);
}


function runMysqlQuery(array $config, string $sql)
{
    if (!commandExists('mysql')) {
        throw new Exception('MySQL tools are not installed or not in your system PATH. Please install MySQL client tools.');
    }

    try {
        $baseCommand = ['mysql'];

        $upperSql = strtoupper(trim($sql));
        $containsDbOperations = str_contains($upperSql, 'CREATE DATABASE') ||
            str_contains($upperSql, 'DROP DATABASE') ||
            str_contains($upperSql, 'USE ');

        if (!$containsDbOperations) {
            $baseCommand[] = $config['db'];
        }

        return executeMysqlCommand($config, $baseCommand, null, $sql);
    } catch (Exception $e) {
        throw new Exception('Database query failed: ' . $e->getMessage());
    }
}

function recreateDatabase(array $config)
{
    $dbName = $config['db'] ?? '';
    if ($dbName === '') {
        throw new Exception('Database configuration is missing or invalid.');
    }

    $sanitizedDb = '`' . str_replace('`', '``', $dbName) . '`';

    $charset = $config['charset'] ?? 'utf8mb4';
    if (!preg_match('/^[A-Za-z0-9_]+$/', $charset)) {
        throw new Exception('Invalid database charset in configuration.');
    }

    $collation = $config['collation'] ?? null;
    if ($collation !== null && $collation !== '') {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $collation)) {
            throw new Exception('Invalid database collation in configuration.');
        }
    }

    $clauses = ' CHARACTER SET ' . $charset;
    if ($collation) {
        $clauses .= ' COLLATE ' . $collation;
    }

    $sql = sprintf(
        'DROP DATABASE IF EXISTS %1$s; CREATE DATABASE %1$s%2$s;',
        $sanitizedDb,
        $clauses
    );

    try {
        executeMysqlCommand($config, ['mysql'], null, $sql);
    } catch (Exception $e) {
        throw new Exception('Failed to recreate database: ' . $e->getMessage());
    }
}

// Utility Functions

function slugifyForFilename(string $value, int $maxLength = 32)
{
    $slug = strtolower($value);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    if ($maxLength > 0 && strlen($slug) > $maxLength) {
        $slug = substr($slug, 0, $maxLength);
    }

    return $slug !== '' ? $slug : 'note';
}

function extractDaysOption(array $args, int $default): int
{
    foreach ($args as $arg) {
        if (preg_match('/^--days=(\d+)$/', $arg, $matches)) {
            $value = (int) $matches[1];
            if ($value < 1) {
                throw new Exception('Days value must be greater than zero.');
            }
            return $value;
        }
    }

    return $default;
}

function formatFileSize(int $bytes)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $size = $bytes;
    $unitIndex = 0;

    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }

    return sprintf('%.1f%s', $size, $units[$unitIndex]);
}

function buildProcessEnv(array $extra = []): array
{
    $env = [];

    if (isset($_ENV) && is_array($_ENV) && $_ENV !== []) {
        $env = $_ENV;
    } else {
        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) {
                $env[$key] = (string) $value;
            }
        }
    }

    if (empty($env['PATH'])) {
        $path = getenv('PATH');
        if (!is_string($path) || $path === '') {
            $path = '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/opt/homebrew/bin:/opt/homebrew/sbin';
        }
        $env['PATH'] = $path;
    }

    foreach ($extra as $key => $value) {
        if ($value === null) {
            unset($env[$key]);
        } else {
            $env[$key] = $value;
        }
    }

    return $env;
}

function commandExists(string $command)
{
    if ($command === '') {
        return false;
    }

    if (str_contains($command, DIRECTORY_SEPARATOR)) {
        return is_file($command) && is_executable($command);
    }

    $env = buildProcessEnv();
    $paths = explode(PATH_SEPARATOR, $env['PATH'] ?? '');
    foreach ($paths as $path) {
        $fullPath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $command;
        if (is_file($fullPath) && is_executable($fullPath)) {
            return true;
        }
    }

    return false;
}

// Security and Path Validation Functions

function validateSecureFilePath(string $filePath, string $allowedDirectory)
{
    $realFilePath = realpath($filePath);
    $realAllowedDir = realpath($allowedDirectory);

    if ($realFilePath === false || $realAllowedDir === false) {
        return false;
    }

    if ($realFilePath === $realAllowedDir) {
        return true;
    }

    $normalizedAllowedDir = rtrim($realAllowedDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    return str_starts_with($realFilePath . DIRECTORY_SEPARATOR, $normalizedAllowedDir);
}

function resolveImportFileSecure(string $sourceFile, string $backupFolder): ?string
{
    $sourceFile = trim($sourceFile);
    if ($sourceFile === '') {
        return null;
    }

    $candidates = [];

    if (str_contains($sourceFile, DIRECTORY_SEPARATOR)) {
        $candidates[] = $sourceFile;
    } else {
        $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile;

        $lowerSource = strtolower($sourceFile);
        if (!str_ends_with($lowerSource, '.sql') && !str_ends_with($lowerSource, '.zip') && !str_ends_with($lowerSource, '.gz')) {
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.sql';
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.zip';
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.sql.zip';
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $sourceFile . '.sql.gz';
        }
    }

    foreach ($candidates as $candidate) {
        if (is_file($candidate) && validateSecureFilePath($candidate, $backupFolder)) {
            return realpath($candidate);
        }
    }

    return null;
}

function resolveBackupFileSecure(string $requested, string $backupFolder): ?string
{
    $requested = trim($requested);
    if ($requested === '') {
        return null;
    }

    $candidates = [];

    if (str_contains($requested, DIRECTORY_SEPARATOR)) {
        $candidates[] = $requested;
    } else {
        $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $requested;
        if (!str_ends_with($requested, '.zip')) {
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $requested . '.zip';
        }
        if (!str_ends_with($requested, '.sql.zip')) {
            $candidates[] = $backupFolder . DIRECTORY_SEPARATOR . $requested . '.sql.zip';
        }
    }

    foreach ($candidates as $candidate) {
        if (is_file($candidate) && validateSecureFilePath($candidate, $backupFolder)) {
            return realpath($candidate);
        }
    }

    return null;
}

/**
 * Global cleanup registry for temporary files
 */
class TempFileRegistry
{
    private static array $tempFiles = [];
    private static bool $shutdownRegistered = false;

    public static function register(string $filePath)
    {
        self::$tempFiles[] = $filePath;

        if (!self::$shutdownRegistered) {
            register_shutdown_function([self::class, 'cleanup']);
            self::$shutdownRegistered = true;
        }
    }

    public static function unregister(string $filePath)
    {
        $key = array_search($filePath, self::$tempFiles, true);
        if ($key !== false) {
            unset(self::$tempFiles[$key]);
        }
    }

    public static function cleanup()
    {
        foreach (self::$tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        self::$tempFiles = [];
    }
}

/**
 * User-friendly error handler
 */
function handleUserFriendlyError(Throwable $e)
{
    $message = $e->getMessage();
    $userMessage = translateErrorMessage($message);

    error_log('[db-tools error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    fwrite(STDERR, 'Error: ' . $userMessage . PHP_EOL);
}

/**
 * Translates technical error messages to user-friendly ones
 */
function translateErrorMessage(string $technicalMessage)
{
    $translations = [
        'Failed to open mysql process' => 'Could not connect to the database. Please check your configuration and network connection.',
        'Failed to open mysqlcheck process' => 'Could not run database maintenance. Please verify MySQL tools are installed.',
        'mysql command not found' => 'MySQL tools are not installed or not in your system PATH. Please install MySQL client tools.',
        'mysqlcheck command not found' => 'MySQL maintenance tools are not installed. Please install MySQL client tools.',
        'Failed to create zip archive' => 'Could not create backup file. Please check disk space and permissions.',
        'Failed to open backup archive' => 'Could not open the backup file. It may be corrupted or in an unsupported format.',
        'Failed to set password for archive' => 'Incorrect password for the backup file.',
        'No SQL file found in archive' => 'The backup file does not contain a valid database dump.',
        'Database name not configured' => 'Database configuration is missing or invalid.',
    ];

    foreach ($translations as $technical => $friendly) {
        if (str_contains($technicalMessage, $technical)) {
            return $friendly;
        }
    }

    return $technicalMessage;
}


/**
 * Run comprehensive database maintenance
 * - mysqlcheck (optimize, repair, analyze)
 * - purge old binary logs
 */
function handleMaintain(array $mainConfig, array $args)
{
    $days = extractDaysOption($args, 7);

    echo "===========================================\n";
    echo "  DATABASE MAINTENANCE\n";
    echo "===========================================\n\n";

    // Step 1: MySQL Check
    echo "Step 1/2: Running database optimization and repair...\n";
    echo str_repeat('-', 43) . "\n";

    try {
        $output = runMysqlCheckCommand($mainConfig);
        if ($output !== '') {
            foreach (explode("\n", $output) as $line) {
                if ($line !== '') {
                    echo '  ' . $line . PHP_EOL;
                }
            }
        }
        echo "  ✓ Database maintenance completed\n\n";
    } catch (Exception $e) {
        echo "  ✗ Database maintenance failed: " . $e->getMessage() . "\n\n";
        // Continue to next step even if this fails
    }

    // Step 2: Purge Binary Logs
    echo "Step 2/2: Cleaning up old binary logs...\n";
    echo str_repeat('-', 43) . "\n";

    try {
        $sql = sprintf('PURGE BINARY LOGS BEFORE DATE(NOW() - INTERVAL %d DAY);', $days);
        echo sprintf("  Purging logs older than %d day(s)...\n", $days);

        $result = runMysqlQuery($mainConfig, $sql);
        if ($result !== '') {
            foreach (explode("\n", $result) as $line) {
                if ($line !== '') {
                    echo '  ' . $line . PHP_EOL;
                }
            }
        }
        echo "  ✓ Binary log cleanup completed\n\n";
    } catch (Exception $e) {
        echo "  ✗ Binary log cleanup failed: " . $e->getMessage() . "\n\n";
    }

    echo "===========================================\n";
    echo "  MAINTENANCE COMPLETE\n";
    echo "===========================================\n";
}


/**
 * Verify backup integrity
 */
function handleVerify(string $backupFolder, array $args)
{
    $backupFile = $args[0] ?? null;

    if ($backupFile === null) {
        $backups = getSortedBackups($backupFolder);
        if (empty($backups)) {
            echo 'No backups found to verify.' . PHP_EOL;
            return;
        }

        echo "Select backup to verify:\n";
        showBackupsWithIndex($backups);

        $selectedPath = promptForBackupSelection($backups);
        if ($selectedPath === null) {
            echo 'Verification cancelled.' . PHP_EOL;
            return;
        }
    } else {
        $selectedPath = resolveBackupFileSecure($backupFile, $backupFolder);
        if (!$selectedPath) {
            throw new Exception("Backup file not found or access denied: {$backupFile}");
        }
    }

    $basename = basename($selectedPath);
    $fileSize = formatFileSize(filesize($selectedPath));

    echo "Verifying backup: {$basename} ({$fileSize})\n";
    echo str_repeat('-', 50) . "\n";

    echo "✓ File exists and is readable\n";

    echo "Checking ZIP integrity... ";
    if (!verifyBackupIntegrity($selectedPath)) {
        echo "✗ FAILED\n";
        echo "  Error: ZIP archive is corrupted or invalid\n";
        exit(1);
    }
    echo "✓ PASSED\n";

    echo "Checking archive contents... ";
    $zip = new ZipArchive();
    $status = $zip->open($selectedPath);

    if ($status !== true) {
        echo "✗ FAILED\n";
        echo "  Error: Cannot open archive (code: {$status})\n";
        exit(1);
    }

    if ($zip->numFiles < 1) {
        echo "✗ FAILED\n";
        echo "  Error: Archive is empty\n";
        $zip->close();
        exit(1);
    }

    $sqlFound = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name !== false && str_ends_with(strtolower($name), '.sql')) {
            $sqlFound = true;
            $stat = $zip->statIndex($i);
            $sqlSize = $stat ? formatFileSize($stat['size']) : 'unknown';
            echo "✓ PASSED\n";
            echo "  Found: {$name} ({$sqlSize})\n";
            break;
        }
    }

    if (!$sqlFound) {
        echo "✗ FAILED\n";
        echo "  Error: No SQL file found in archive\n";
        $zip->close();
        exit(1);
    }

    $zip->close();

    echo "Checking encryption... ";
    if (isZipPasswordProtected($selectedPath)) {
        echo "✓ Password protected (AES-256)\n";
    } else {
        echo "⚠ WARNING: Archive is not password protected\n";
    }

    echo str_repeat('-', 50) . "\n";
    echo "✓ Backup verification PASSED\n";
    echo "\nBackup is valid and can be restored.\n";
}

/**
 * Clean old backups based on retention policy
 */
function handleClean(string $backupFolder, array $args)
{
    $keepCount = extractKeepOption($args);
    $keepDays = extractDaysOption($args, 0);

    if ($keepCount === null && $keepDays === 0) {
        throw new Exception('Please specify --keep=N or --days=N for retention policy');
    }

    $backups = getSortedBackups($backupFolder);

    if (empty($backups)) {
        echo 'No backups found to clean.' . PHP_EOL;
        return;
    }

    echo "Backup cleanup\n";
    echo str_repeat('-', 50) . "\n";
    echo "Total backups: " . count($backups) . "\n";

    $toDelete = [];

    if ($keepCount !== null) {
        if (count($backups) <= $keepCount) {
            echo "Retention policy: Keep {$keepCount} most recent\n";
            echo "Action: Nothing to delete (have " . count($backups) . " backups)\n";
            return;
        }

        $toDelete = array_slice($backups, $keepCount);
        echo "Retention policy: Keep {$keepCount} most recent\n";
    } elseif ($keepDays > 0) {
        $cutoffTime = time() - ($keepDays * 86400);

        foreach ($backups as $backup) {
            if ($backup['mtime'] < $cutoffTime) {
                $toDelete[] = $backup;
            }
        }

        echo "Retention policy: Keep backups newer than {$keepDays} day(s)\n";

        if (empty($toDelete)) {
            echo "Action: Nothing to delete (all backups are within retention period)\n";
            return;
        }
    }

    echo "Backups to delete: " . count($toDelete) . "\n";
    echo str_repeat('-', 50) . "\n";

    $totalSize = 0;
    foreach ($toDelete as $backup) {
        $age = floor((time() - $backup['mtime']) / 86400);
        $size = $backup['size'];
        $totalSize += $size;
        echo sprintf(
            "  %s (%s, %d days old)\n",
            $backup['basename'],
            formatFileSize($size),
            $age
        );
    }

    echo str_repeat('-', 50) . "\n";
    echo "Total space to free: " . formatFileSize($totalSize) . "\n\n";

    echo "Proceed with deletion? (y/N): ";
    $input = trim(fgets(STDIN) ?: '');

    if (strtolower($input) !== 'y') {
        echo "Cleanup cancelled.\n";
        return;
    }

    $deleted = 0;
    $failed = 0;

    foreach ($toDelete as $backup) {
        if (@unlink($backup['path'])) {
            $deleted++;
            echo "✓ Deleted: " . $backup['basename'] . "\n";
        } else {
            $failed++;
            echo "✗ Failed to delete: " . $backup['basename'] . "\n";
        }
    }

    echo str_repeat('-', 50) . "\n";
    echo "Cleanup complete: {$deleted} deleted, {$failed} failed\n";

    if ($deleted > 0) {
        echo "Freed: " . formatFileSize($totalSize) . "\n";
    }
}

/**
 * Show database size information
 */
function handleSize(array $mainConfig, array $args)
{
    echo "===========================================\n";
    echo "  DATABASE SIZE\n";
    echo "===========================================\n\n";

    try {
        $sizeInfo = getDatabaseSize($mainConfig);

        echo "Total database size: " . formatFileSize($sizeInfo['total_size']) . "\n";
        echo "Total tables: " . $sizeInfo['table_count'] . "\n\n";

        if (!empty($sizeInfo['tables'])) {
            echo "Top tables by size:\n";
            echo str_repeat('-', 80) . "\n";
            echo sprintf("%-40s %12s %12s %12s\n", "Table", "Data", "Index", "Total");
            echo str_repeat('-', 80) . "\n";

            $tablesToShow = array_slice($sizeInfo['tables'], 0, 20);

            foreach ($tablesToShow as $table) {
                echo sprintf(
                    "%-40s %12s %12s %12s\n",
                    $table['name'],
                    formatFileSize($table['data_size']),
                    formatFileSize($table['index_size']),
                    formatFileSize($table['total_size'])
                );
            }

            if (count($sizeInfo['tables']) > 20) {
                $remaining = count($sizeInfo['tables']) - 20;
                echo str_repeat('-', 80) . "\n";
                echo "... and {$remaining} more table(s)\n";
            }
        }

        echo "\n";
    } catch (Exception $e) {
        echo "✗ Failed to get size information: " . $e->getMessage() . "\n\n";
    }
}

/**
 * Test database configuration and connectivity
 */
function handleConfigTest(string $backupFolder, array $mainConfig, array $args)
{
    echo "===========================================\n";
    echo "  DATABASE CONFIGURATION TEST\n";
    echo "===========================================\n\n";

    $allPassed = true;

    echo "Testing database...\n";
    echo str_repeat('-', 50) . "\n";

    // Test 1: Configuration completeness
    echo "1. Configuration completeness... ";
    $required = ['host', 'username', 'db'];
    $missing = [];
    foreach ($required as $field) {
        if (empty($mainConfig[$field])) {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        echo "✗ FAILED\n";
        echo "   Missing: " . implode(', ', $missing) . "\n";
        $allPassed = false;
    } else {
        echo "✓ PASSED\n";
    }

    // Test 2: MySQL client tools
    echo "2. MySQL client tools... ";
    if (!commandExists('mysql')) {
        echo "✗ FAILED\n";
        echo "   mysql command not found in PATH\n";
        $allPassed = false;
    } else {
        echo "✓ PASSED\n";
    }

    if (!commandExists('mysqlcheck')) {
        echo "   ⚠ WARNING: mysqlcheck not found (maintenance commands unavailable)\n";
    }

    // Test 3: Database connectivity
    echo "3. Database connectivity... ";
    try {
        $result = testDatabaseConnection($mainConfig);
        echo "✓ PASSED\n";
        echo "   Connected to: {$mainConfig['host']}:{$mainConfig['port']}\n";
        echo "   Database: {$mainConfig['db']}\n";
        echo "   MySQL version: {$result['version']}\n";
    } catch (Exception $e) {
        echo "✗ FAILED\n";
        echo "   " . $e->getMessage() . "\n";
        $allPassed = false;
    }

    // Test 4: Database permissions
    echo "4. Database permissions... ";
    try {
        testDatabasePermissions($mainConfig);
        echo "✓ PASSED\n";
    } catch (Exception $e) {
        echo "✗ FAILED\n";
        echo "   " . $e->getMessage() . "\n";
        $allPassed = false;
    }

    // Test 5: Backup folder
    echo "5. Backup folder access... ";
    if (!is_dir($backupFolder)) {
        echo "✗ FAILED\n";
        echo "   Directory does not exist: {$backupFolder}\n";
        $allPassed = false;
    } elseif (!is_writable($backupFolder)) {
        echo "✗ FAILED\n";
        echo "   Directory is not writable: {$backupFolder}\n";
        $allPassed = false;
    } else {
        echo "✓ PASSED\n";
        $freeSpace = disk_free_space($backupFolder);
        if ($freeSpace !== false) {
            echo "   Free space: " . formatFileSize($freeSpace) . "\n";

            if ($freeSpace < 1073741824) {
                echo "   ⚠ WARNING: Low disk space (< 1GB remaining)\n";
            }
        }
    }

    // Test 6: Character set
    echo "6. Character set configuration... ";
    $charset = $mainConfig['charset'] ?? 'utf8mb4';
    if ($charset === 'utf8mb4') {
        echo "✓ PASSED\n";
        echo "   Using: {$charset}\n";
    } else {
        echo "⚠ WARNING\n";
        echo "   Using: {$charset} (utf8mb4 recommended)\n";
    }

    echo "\n";
    echo "===========================================\n";
    if ($allPassed) {
        echo "  ✓ ALL TESTS PASSED\n";
    } else {
        echo "  ✗ SOME TESTS FAILED\n";
    }
    echo "===========================================\n";

    exit($allPassed ? 0 : 1);
}

/**
 * Extract --keep option from arguments
 */
function extractKeepOption(array $args): ?int
{
    foreach ($args as $arg) {
        if (preg_match('/^--keep=(\d+)$/', $arg, $matches)) {
            $value = (int) $matches[1];
            if ($value < 1) {
                throw new Exception('Keep value must be greater than zero.');
            }
            return $value;
        }
    }

    return null;
}

/**
 * Get database size information
 */
function getDatabaseSize(array $config): array
{
    $sql = "
        SELECT 
            TABLE_NAME as name,
            DATA_LENGTH as data_size,
            INDEX_LENGTH as index_size,
            DATA_LENGTH + INDEX_LENGTH as total_size
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = '{$config['db']}'
        ORDER BY total_size DESC
    ";

    if (!commandExists('mysql')) {
        throw new Exception('MySQL tools are not installed or not in your system PATH.');
    }

    try {
        $output = executeMysqlCommand(
            $config,
            ['mysql', '--skip-column-names'],
            null,
            $sql
        );

        $tables = [];
        $totalSize = 0;

        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (trim($line) === '') continue;

            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 4) {
                $dataSize = (int) $parts[1];
                $indexSize = (int) $parts[2];
                $tableTotal = (int) $parts[3];

                $tables[] = [
                    'name' => $parts[0],
                    'data_size' => $dataSize,
                    'index_size' => $indexSize,
                    'total_size' => $tableTotal,
                ];

                $totalSize += $tableTotal;
            }
        }

        return [
            'total_size' => $totalSize,
            'table_count' => count($tables),
            'tables' => $tables,
        ];
    } catch (Exception $e) {
        throw new Exception('Failed to retrieve database size: ' . $e->getMessage());
    }
}

/**
 * Test database connection
 */
function testDatabaseConnection(array $config): array
{
    $sql = "SELECT VERSION() as version;";

    try {
        $output = executeMysqlCommand(
            $config,
            ['mysql', $config['db'], '--skip-column-names'],
            null,
            $sql
        );

        return [
            'version' => trim($output) ?: 'Unknown',
        ];
    } catch (Exception $e) {
        throw new Exception('Cannot connect to database: ' . $e->getMessage());
    }
}

/**
 * Test database permissions
 */
function testDatabasePermissions(array $config)
{
    $sql = "SELECT 1;";

    try {
        executeMysqlCommand(
            $config,
            ['mysql', $config['db'], '--skip-column-names'],
            null,
            $sql
        );
    } catch (Exception $e) {
        throw new Exception('SELECT permission denied');
    }

    $sql = "SHOW TABLES;";

    try {
        executeMysqlCommand(
            $config,
            ['mysql', $config['db'], '--skip-column-names'],
            null,
            $sql
        );
    } catch (Exception $e) {
        throw new Exception('Cannot list tables');
    }
}
