<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php';

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

$db = Zend_Db::factory($conf->resources->db);
Zend_Db_Table::setDefaultAdapter($db);

$common = new Application_Service_Common();

$sql = "SELECT `dm_id`, `password` FROM `data_manager`";
$dataManagers = $db->fetchAll($sql);
foreach ($dataManagers as $dm) {
    if (!empty($dm['password'])) {
        $dmData = [
            'password' => $common->passwordHash($dm['password'])
        ];

        $db->update('data_manager', $dmData, 'dm_id = ' . $db->quote($dm['dm_id']));
    }
}

$sql = "SELECT `admin_id`, `password` FROM `system_admin`";
$systemAdmin = $db->fetchAll($sql);
foreach ($systemAdmin as $sa) {
    if (!empty($sa['password'])) {
        $saData = [
            'password' => $common->passwordHash($sa['password'])
        ];

        $db->update('system_admin', $saData, 'admin_id = ' . $db->quote($sa['admin_id']));
    }
}
