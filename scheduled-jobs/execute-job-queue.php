<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php');

try {
    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $phpPath = (!empty($conf->php->path) ? $conf->php->path : PHP_BINARY);

    $scheduledDb = new Application_Model_DbTable_ScheduledJobs();
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $scheduledResult = $scheduledDb->fetchAll("status = 'pending'");
    if (isset($scheduledResult)) {
        foreach ($scheduledResult as $key => $sj) {
            exec($phpPath . " " . realpath(APPLICATION_PATH . "/../scheduled-jobs") . DIRECTORY_SEPARATOR .  $sj['job']);
            $db->update('scheduled_jobs', array("completed_on" => new Zend_Db_Expr('now()'), "status" => "completed"), "job_id = " . $sj['job_id']);
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/execute-job-queue.php');
}
