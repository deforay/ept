#!/usr/bin/env php
<?php

// bin/check-version-sync.php — verify the code's APP_VERSION matches the DB's
// recorded schema version (system_config.app_version).
//
// Migrations run as part of `composer post-update` during upgrade.sh, but they
// can fail to fully apply (a bad statement, an aborted run, a drifted schema)
// and leave the DB behind the code with no obvious signal. This script makes
// that drift explicit at the end of an upgrade.
//
// Exit codes:
//   0  in sync (code == DB)
//   2  code ahead of DB  (APP_VERSION > DB)  -> migrations pending/failed
//   3  DB ahead of code  (DB > APP_VERSION)  -> code older than schema
//   1  could not determine (DB/connection error)

if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . '/../cli-bootstrap.php';

$appVersion = defined('APP_VERSION') ? (string) APP_VERSION : '';
if ($appVersion === '') {
    fwrite(STDERR, "Could not read APP_VERSION from constants.php.\n");
    exit(1);
}

try {
    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $db = Zend_Db::factory($conf->resources->db);
    $dbVersion = (string) $db->fetchOne(
        $db->select()->from('system_config', ['value'])->where('config = ?', 'app_version')
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'Could not read DB schema version: ' . $e->getMessage() . "\n");
    exit(1);
}

if ($dbVersion === '') {
    fwrite(STDERR, "DB schema version (system_config.app_version) is missing or empty.\n");
    exit(1);
}

$cmp = version_compare($appVersion, $dbVersion);

if ($cmp === 0) {
    echo "Version in sync: {$appVersion}\n";
    exit(0);
}

if ($cmp > 0) {
    echo "VERSION MISMATCH: code is at {$appVersion} but the database is still at {$dbVersion}.\n";
    echo "Migrations did not fully apply. Re-run them and check the output for errors.\n";
    exit(2);
}

echo "VERSION MISMATCH: the database is at {$dbVersion} but the code is only {$appVersion}.\n";
echo "The code is older than the schema — deploy the matching (newer) code.\n";
exit(3);
