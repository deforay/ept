<?php
// PTCC manager location wise mapping issue fixing auto runner
// set php memeroy limit
ini_set('memory_limit', '-1');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php'); //Initiate the cron

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV); // Define the db resource config object
$general = new Application_Service_Common(); // Declare the common functionality access object
try{
    $db = Zend_Db::factory($conf->resources->db);
	Zend_Db_Table::setDefaultAdapter($db);
    /* To get list of ptcc manager */
    $sQuery = $db->select()
        ->from(array('dm' => 'data_manager'), array('dm_id'))
        ->joinLeft(array('pcm' => 'ptcc_countries_map'), 'dm.dm_id=pcm.ptcc_id', array('country_id', 'state', 'district'))
        ->where("dm.ptcc like 'yes'");
    // echo($sQuery);die;
    $result = $db->fetchAll($sQuery);
    if(isset($result[0]) && sizeof($result) > 0){
        foreach($result as $key => $value){
            $locationwiseparticipants = [];
            $sql = $db->select()->from(array('p' => 'participant'), array('participant_id')); // Initiate the participants list table
            // Based on district wise
            if(isset($value['district']) && count($value['district']) > 0){
                $sql = $sql->orWhere('district IN("'.implode('","', $value['district']).'")');
            }
            // Based on province wise
            if(isset($value['province']) && count($value['province']) > 0){
                $sql = $sql->orWhere('state IN("'.implode('","', $value['province']).'")');
            }
            // Based on country wise
            if(isset($value['country']) && count($value['country']) > 0){
                $sql = $sql->orWhere('country IN("'.implode('","', $value['country']).'")');
            }
            $sql = $sql->group('participant_id');
            // Fetch list of participants from location wise
            $locationwiseparticipants = $db->fetchAll($sql);

            if(isset($locationwiseparticipants[0]) && sizeof($locationwiseparticipants) > 0){ // check the participants avaiablity
                $db->delete('participant_manager_map', 'dm_id = ' . $value['dm_id']); // Reomve the outdated records from the pmm table
                foreach($locationwiseparticipants as $pkey => $pvalue){
                    if($pkey > 10)
                        continue;
                    $multipleData[] = array('participant_id'=> $pvalue['participant_id'], 'dm_id' => $value['dm_id']); // create the map data for pmm creation
                }
            }
            // Insert the multiple records
            if(isset($multipleData[0]) && sizeof($multipleData) > 0){
                $general->insertMultiple('participant_manager_map', $multipleData);
            }
        }
    }
}catch (Exception $e) {
	error_log($e->getMessage());
	error_log($e->getTraceAsString());
	error_log('whoops! Something went wrong in scheduled-jobs/ptcc-auto-mapping.php');
}