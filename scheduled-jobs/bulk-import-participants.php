<?php

ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

include_once 'CronInit.php';

require_once(__DIR__ . "/General.php");

$general = new General();

$directory = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . 'bulk-upload' . DIRECTORY_SEPARATOR;

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
try {
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $files = glob("$directory{*.xlsx,*.xls,*.csv}", GLOB_BRACE);

    $participantDb = new Application_Model_DbTable_Participants();
    $common = new Application_Service_Common();

    foreach ($files as $fileName) {

        try {

            if (file_exists($fileName)) {
                $response = $participantDb->processBulkImport($fileName, true);
            } else {
                error_log("File not found - $fileName");
                continue;
            }

        } catch (Exception $exc) {
            error_log("Issue with $fileName");
            error_log($exc->getMessage());
            error_log($exc->getTraceAsString());
            error_log('File not uploaded. Something went wrong please try again later!');
            continue;
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/bulk-import-participants.php');
}
