<?php

use Symfony\Component\Uid\Ulid;

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php');

try {
    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $phpPath = !empty($conf->php->path) ? $conf->php->path : PHP_BINARY;
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);
    $ulid = Pt_Commons_General::generateULID();
    $id = $db->update('system_metadata', ['metadata_value' => $ulid], "metadata_id = 'instance-id'");
    if ($id) {
        echo "Created ULID: " . $ulid . PHP_EOL;
    }
} catch (Exception $e) {
    error_log("ERROR : {$e->getFile()} on line {$e->getLine()} : {$e->getMessage()}");
    error_log($e->getTraceAsString());
}
