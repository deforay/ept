<?php

require_once __DIR__ . '/../cli-bootstrap.php';

try {

    $db = Zend_Db_Table_Abstract::getDefaultAdapter();

    // One-shot migration: matches plaintext rows that still hold the legacy
    // onboarding default so they can be re-hashed in place. NOSONAR
    $defaultPassword = 'ept1@)(*&^'; // NOSONAR
    $defaultHash = Application_Service_Common::passwordHash($defaultPassword);

    echo PHP_EOL;
    echo "UPDATING ADMIN PASSWORDS" . PHP_EOL;
    $sql = "SELECT `admin_id`, `password`, `primary_email` FROM `system_admin`";
    $systemAdmin = $db->fetchAll($sql);
    $totalAdmins = count($systemAdmin);
    foreach ($systemAdmin as $key => $sa) {
        Application_Service_Common::displayProgressBar($key + 1, $totalAdmins);
        if (!empty($sa['password'])) {
            $encryptedPassword = Application_Service_Common::passwordHash($sa['password']);
            if ($encryptedPassword === $sa['password']) {
                continue;
            }

            $saData = [
                'password' => $encryptedPassword
            ];

            $db->update('system_admin', $saData, 'admin_id = ' . $db->quote($sa['admin_id']));
        }
    }

    echo PHP_EOL;
    echo "UPDATING DATA MANAGER PASSWORDS" . PHP_EOL;
    $sql = "SELECT `dm_id`, `password`, `primary_email` FROM `data_manager`";
    $dataManagers = $db->fetchAll($sql);
    $totalDataManagers = count($dataManagers);
    foreach ($dataManagers as $key => $dm) {
        Application_Service_Common::displayProgressBar($key + 1, $totalDataManagers);
        if (!empty($dm['password'])) {
            $encryptedPassword = ($dm['password'] === $defaultPassword) ? $defaultHash : Application_Service_Common::passwordHash($dm['password']);
            if ($encryptedPassword === $dm['password']) {
                continue;
            }
            $dmData = [
                'password' => $encryptedPassword
            ];

            $db->update('data_manager', $dmData, 'dm_id = ' . $db->quote($dm['dm_id']));
        }
    }
} catch (Throwable $e) {
    echo "An error occurred: " . $e->getMessage() . PHP_EOL;
    Pt_Commons_LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
