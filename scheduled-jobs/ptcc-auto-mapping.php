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
        ->from(array('dm' => 'data_manager'), array('dm_id'))
        ->joinLeft(array('pcm' => 'ptcc_countries_map'), 'dm.dm_id=pcm.ptcc_id', array('country_id', 'state', 'district'))
        ->where("dm.ptcc like 'yes'");
    // echo($sQuery);die;
    $result = $db->fetchAll($sQuery);
    if (!empty($result)) {
        foreach ($result as $key => $value) {
            $dm = new Application_Model_DbTable_DataManagers();
            $dm->dmParticipantMap($value, $value['dm_id'], true);

            /* $locationwiseparticipants = [];
            $sql = $db->select()->from(array('p' => 'participant'), array('participant_id')); // Initiate the participants list table
            // Based on district wise
            if (isset($value['district']) && !empty($value['district']) && count($value['district']) > 0) {
                $sql = $sql->orWhere('district IN("' . $value['district'] . '")');
            }
            // Based on province wise
            if (isset($value['province']) && !empty($value['province']) && count($value['province']) > 0) {
                $sql = $sql->orWhere('state IN("' . $value['province'] . '")');
            }
            // Based on country wise
            if (isset($value['country']) && !empty($value['country']) && count($value['country']) > 0) {
                $sql = $sql->orWhere('country IN("' . $value['country'] . '")');
            }
            $sql = $sql->group('participant_id');
            // Fetch list of participants from location wise
            $locationwiseparticipants = $db->fetchAll($sql);
            $multipleData = [];
            if (isset($locationwiseparticipants[0]) && sizeof($locationwiseparticipants) > 0) { // check the participants avaiablity
                $dm = new Application_Model_DbTable_DataManagers();
                $params['participantsList'][] = $params['participant_id'];
                $dm->dmParticipantMap($params, $locationwiseparticipants, false, true);

                $db->delete('participant_manager_map', 'dm_id = ' . $value['dm_id']); // Reomve the outdated records from the pmm table
                foreach ($locationwiseparticipants as $pkey => $pvalue) {
                    $multipleData[] = array('participant_id' => $pvalue['participant_id'], 'dm_id' => $value['dm_id']); // create the map data for pmm creation
                }
            }
            // Insert the multiple records
            if (isset($multipleData[0]) && sizeof($multipleData) > 0) {
                $general->insertMultiple('participant_manager_map', $multipleData, true);
            } */
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/ptcc-auto-mapping.php');
}
