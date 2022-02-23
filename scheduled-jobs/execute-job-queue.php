<?php

include_once 'CronInit.php';

try {
    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $phpPath = (!empty($conf->php->path) ? $conf->php->path : PHP_BINARY);

    $scheduledDb = new Application_Model_DbTable_ScheduledJobs();
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $scheduledResult = $scheduledDb->fetchAll("status = 'pending'");
    if (isset($scheduledResult)) {
        foreach ($scheduledResult as $key => $sj) {
            $output = realpath($phpPath . " " . APPLICATION_PATH . "/ ../scheduled-jobs/" . $sj['job']);
            if ($output) {
                $scheduledDb->update(array("completed_on" => new Zend_Db_Expr('now()'), "status" => "completed"), array("job_id" => $sj['job_id']));
            }
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/GenerateCertificate.php');
}
