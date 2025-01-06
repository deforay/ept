<?php
// PTCC manager location wise mapping issue fixing auto runner
// set php memeroy limit
ini_set('memory_limit', '-1');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php'); //Initiate the cron

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV); // Define the db resource config object
$general = new Application_Service_Common(); // Declare the common functionality access object
try {
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);
    /* To get list of ptcc manager */
    $sQuery = $db->select()
        ->from(array('rt' => 'r_testkitnames'));
    $result = $db->fetchAll($sQuery);
    if (!empty($result)) {
        foreach ($result as $key => $row) {
            if (isset($row['scheme_type']) && !empty($row['scheme_type'])) {
                $db->delete('scheme_testkit_map', 'scheme_type  = "' . $row['scheme_type'] . '" AND testkit_id = "' . $row['TestKitName_ID'] . '"');
                $mapData = [
                    'scheme_type' => $row['scheme_type'],
                    'testkit_id' => $row['TestKitName_ID'],
                    'testkit_1' => $row['testkit_1'],
                    'testkit_2' => $row['testkit_2'],
                    'testkit_3' => $row['testkit_3']
                ];
                $db->insert('scheme_testkit_map', $mapData);
            }
        }
    }
} catch (Exception $e) {
    error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
    error_log($e->getTraceAsString());
}
