#!/usr/bin/env php
<?php

if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . '/../cli-bootstrap.php';

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

$io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

$runOnceDir = ROOT_PATH . '/run-once';
if (!is_dir($runOnceDir)) {
    $io->warning('No run-once scripts directory found. Skipping.');
    exit(0);
}

$db = Zend_Db_Table_Abstract::getDefaultAdapter();

try {
    $db->query("SELECT 1 FROM `run_once_scripts` LIMIT 1");
} catch (Exception $e) {
    // Table doesn't exist - log warning and exit gracefully (don't block upgrade)
    $io->warning('Missing table run_once_scripts. Run-once scripts will be skipped. Run migrations (7.3.3+) to enable.');
    exit(0);
}

$scripts = glob($runOnceDir . '/*.php') ?: [];
sort($scripts, SORT_NATURAL | SORT_FLAG_CASE);

if (!$scripts) {
    $io->warning('No run-once scripts found. Skipping.');
    exit(0);
}

$phpBin = defined('PHP_BINARY') ? PHP_BINARY : 'php';
$hadFailures = false;

foreach ($scripts as $scriptPath) {
    $scriptName = basename($scriptPath);
    $alreadyRan = (bool) $db->fetchOne(
        "SELECT 1 FROM `run_once_scripts` WHERE script_name = ? LIMIT 1",
        [$scriptName]
    );

    if ($alreadyRan) {
        $io->text("Skipping run-once script (already ran): {$scriptName}");
        continue;
    }

    $io->text("Running run-once script: {$scriptName}");
    $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($scriptPath);
    system($cmd, $exitCode);

    if ($exitCode !== 0) {
        Pt_Commons_LoggerUtility::logError('Run-once script failed', [
            'script' => $scriptName,
            'exit_code' => $exitCode,
        ]);
        $io->warning("Run-once script failed: {$scriptName}");
        $hadFailures = true;
        continue;
    }

    try {
        $db->query(
            "INSERT INTO `run_once_scripts` (script_name, executed_at) VALUES (?, NOW())",
            [$scriptName]
        );
        $io->success("Completed run-once script: {$scriptName}");
    } catch (Exception $e) {
        Pt_Commons_LoggerUtility::logError('Failed to record run-once script execution', [
            'script' => $scriptName,
            'error' => $e->getMessage(),
        ]);
        $io->warning("Failed to record run-once script execution: {$scriptName}");
        $hadFailures = true;
    }
}

if ($hadFailures) {
    // Log failures but don't block the upgrade process - exit gracefully
    $io->warning('Some run-once scripts had failures (see logs above). Continuing with upgrade.');
}
exit(0);
