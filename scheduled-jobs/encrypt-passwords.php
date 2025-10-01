<?php

require_once __DIR__ . '/../cli-bootstrap.php';

try {
    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $defaultPassword = 'ept1@)(*&^';
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
} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage() . PHP_EOL;
    error_log($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage());
    error_log($e->getTraceAsString());
}
